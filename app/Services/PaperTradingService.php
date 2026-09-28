<?php

namespace App\Services;

use App\Models\SimulatedTrade;
use App\Models\SimulationAccount;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PaperTradingService
{
    /** Max simultaneous open positions per user, across all symbols. */
    protected const MAX_CONCURRENT_POSITIONS = 10;

    /**
     * Approximate perpetual-futures funding interval and rate. Real exchanges
     * settle funding every 8h at a rate that floats with the market; Gate.io's
     * futures funding-rate endpoint would need a second per-symbol network
     * call on every poll, so this uses a fixed, documented approximation
     * (~0.01%/8h is a typical stable-market rate on major exchanges) rather
     * than an unverified extra API dependency.
     */
    protected const FUNDING_INTERVAL_HOURS = 8;
    protected const FUNDING_RATE_PCT = 0.01; // % of notional position size, per interval

    public function __construct(
        protected MarketDataFetcherService $marketFetcher
    ) {}

    /**
     * Estimated maintenance-margin liquidation price for a leveraged futures
     * position. Public + static so FuturesSignalEngineService can show the
     * SAME real formula (0.5% MMR) on a signal card BEFORE the user opens
     * the position, instead of the PRD's cruder flat "x0.90" approximation
     * (brain/engine_v1/FUTURES_SIGNAL_ENGINE.md) — one formula, one place.
     * Returns null for spot / unleveraged (1x) since there's nothing to
     * liquidate.
     */
    public static function estimateLiquidationPrice(float $entryPrice, int $leverage, string $side = 'long'): ?float
    {
        if ($leverage <= 1 || $entryPrice <= 0) {
            return null;
        }

        $maintenanceMarginRate = 0.005; // 0.5% MMR

        return strtolower($side) === 'short'
            ? $entryPrice * (1.0 + (1.0 / $leverage) - $maintenanceMarginRate)
            : $entryPrice * max(0.0, (1.0 - (1.0 / $leverage) + $maintenanceMarginRate));
    }

    /**
     * Get or create virtual simulation account for the user.
     */
    public function getOrCreateAccount(User $user): SimulationAccount
    {
        return SimulationAccount::firstOrCreate(
            ['user_id' => $user->id],
            [
                'virtual_balance' => 10000.00,
                'initial_balance' => 10000.00,
                'total_trades' => 0,
                'winning_trades' => 0,
                'losing_trades' => 0,
            ]
        );
    }

    /**
     * Calculate available free cash (Saldo Kas Bebas Dompet).
     */
    public function getAvailableCash(User $user, ?SimulationAccount $account = null): float
    {
        $account ??= $this->getOrCreateAccount($user);
        $lockedMargin = (float) SimulatedTrade::where('user_id', $user->id)
            ->where('mode', 'paper_trade')
            ->where('status', 'open')
            ->sum('margin_amount');

        return max(0.0, (float) $account->virtual_balance - $lockedMargin);
    }

    /**
     * Fetch the user's simulation account row locked FOR UPDATE, so concurrent
     * opens/closes/resets serialize instead of racing on virtual_balance or
     * double-processing the same trade (see openPaperTrade/closeTradeManually/
     * resetAccount, all of which call this as their first step inside a
     * DB::transaction()).
     */
    protected function lockAccountForUpdate(User $user): SimulationAccount
    {
        $this->getOrCreateAccount($user);

        return SimulationAccount::where('user_id', $user->id)->lockForUpdate()->firstOrFail();
    }

    /**
     * Open a new forward paper trading position (Spot or Futures).
     */
    public function openPaperTrade(
        User $user,
        string $symbol,
        string $timeframe,
        float $entryPrice,
        float $tp1,
        float $tp2,
        float $sl,
        float $allocatedAmount = 1000.00,
        string $tradingType = 'spot',
        string $side = 'long',
        int $leverage = 1,
        ?string $notes = null,
        ?float $riskPct = null
    ): SimulatedTrade {
        return DB::transaction(function () use (
            $user, $symbol, $timeframe, $entryPrice, $tp1, $tp2, $sl,
            $allocatedAmount, $tradingType, $side, $leverage, $notes, $riskPct
        ) {
            // Lock the account row first so a concurrent open/close for this
            // user can't read a stale available-cash figure and over-allocate
            // margin beyond the real balance.
            $account = $this->lockAccountForUpdate($user);
            $symbol = strtoupper($symbol);

            // Guard: no stacking a second open position on the same symbol —
            // prevents silently fragmenting risk across duplicate entries.
            $hasOpenOnSymbol = SimulatedTrade::where('user_id', $user->id)
                ->where('mode', 'paper_trade')
                ->where('symbol', $symbol)
                ->where('status', 'open')
                ->exists();
            if ($hasOpenOnSymbol) {
                throw new \RuntimeException("Sudah ada posisi terbuka di {$symbol}. Tutup posisi itu dulu sebelum membuka yang baru.");
            }

            // Guard: cap total concurrent open positions so exposure can't be
            // fragmented into an unbounded number of simultaneous bets.
            $openCount = SimulatedTrade::where('user_id', $user->id)
                ->where('mode', 'paper_trade')
                ->where('status', 'open')
                ->count();
            if ($openCount >= self::MAX_CONCURRENT_POSITIONS) {
                throw new \RuntimeException('Batas maksimal ' . self::MAX_CONCURRENT_POSITIONS . ' posisi terbuka bersamaan sudah tercapai. Tutup salah satu posisi dulu.');
            }

            $availableCash = $this->getAvailableCash($user, $account);

            $tradingType = in_array(strtolower($tradingType), ['futures', 'future']) ? 'futures' : 'spot';
            $side = strtolower($side) === 'short' ? 'short' : 'long';
            $leverage = $tradingType === 'futures' ? max(1, min(100, $leverage)) : 1;

            // Risk-based position sizing (professional risk management): if a
            // risk % of account is given, back-calculate margin from the
            // entry-to-SL distance instead of trusting an arbitrary $ figure —
            // risking a FIXED % of the account per trade regardless of setup,
            // rather than a fixed dollar amount regardless of stop distance.
            if ($riskPct !== null && $riskPct > 0 && $sl > 0) {
                $riskAmount = $account->virtual_balance * ($riskPct / 100);
                $riskPerUnit = abs($entryPrice - $sl);
                if ($riskPerUnit > 0 && $entryPrice > 0) {
                    $positionSizeUnits = $riskAmount / $riskPerUnit;
                    $positionValue = $positionSizeUnits * $entryPrice;
                    $allocatedAmount = $leverage > 1 ? $positionValue / $leverage : $positionValue;
                }
            }

            // Deduct from available free cash
            $margin = max(1.0, min($allocatedAmount, $availableCash > 0 ? $availableCash : $allocatedAmount));
            $totalPositionSize = $margin * $leverage;

            // Calculate Estimated Liquidation Price for Futures
            $liquidationPrice = $tradingType === 'futures'
                ? self::estimateLiquidationPrice($entryPrice, $leverage, $side)
                : null;

            $trade = SimulatedTrade::create([
                'user_id' => $user->id,
                'simulation_account_id' => $account->id,
                'mode' => 'paper_trade',
                'trading_type' => $tradingType,
                'side' => $side,
                'leverage' => $leverage,
                'symbol' => $symbol,
                'timeframe' => $timeframe,
                'status' => 'open',
                'initial_capital' => $account->virtual_balance,
                'allocated_amount' => $totalPositionSize,
                'margin_amount' => $margin,
                'entry_price' => $entryPrice,
                'take_profit_1' => $tp1,
                'take_profit_2' => $tp2,
                'stop_loss' => $sl,
                'liquidation_price' => $liquidationPrice,
                'entry_time' => now(),
                'last_funding_at' => now(),
                'notes' => $notes ?? ("Simulasi " . strtoupper($tradingType) . " " . strtoupper($side) . ($leverage > 1 ? " {$leverage}x" : "") . " {$timeframe}"),
            ]);

            return $trade;
        });
    }

    /**
     * Evaluate all open paper trades against real-time live prices.
     * Note: Trades are NOT auto-closed on TP/SL; positions remain open for manual exit.
     */
    public function evaluateOpenTrades(User $user): Collection
    {
        $openTrades = SimulatedTrade::where('user_id', $user->id)
            ->where('mode', 'paper_trade')
            ->where('status', 'open')
            ->get();

        if ($openTrades->isEmpty()) {
            return collect();
        }

        $liveCoins = collect($this->marketFetcher->getTopCoinsList());

        foreach ($openTrades as $trade) {
            $coinMatch = $liveCoins->firstWhere('symbol', $trade->symbol);
            $currentPrice = $coinMatch ? (float) $coinMatch['price'] : (float) $trade->entry_price;
            $entry = (float) $trade->entry_price;
            $tp1 = (float) $trade->take_profit_1;
            $tp2 = (float) $trade->take_profit_2;
            $sl = (float) $trade->stop_loss;
            $leverage = max(1, (int) ($trade->leverage ?? 1));
            $isShort = strtolower($trade->side ?? 'long') === 'short';
            $margin = (float) ($trade->margin_amount > 0 ? $trade->margin_amount : $trade->allocated_amount);

            // Funding rate: real perpetual futures charge/pay this every ~8h
            // based on position notional. Longs pay shorts when the rate is
            // positive (typical bullish-skew market), and vice versa — this
            // periodically erodes (or occasionally pads) a leveraged
            // position's PnL over time, unlike spot which has no funding.
            if ($trade->trading_type === 'futures' && $leverage > 1) {
                $this->applyFundingIfDue($trade, $margin, $leverage, $isShort);
            }

            // Price change calculation based on Side (Long vs Short)
            if ($isShort) {
                $rawPriceChangePct = (($entry - $currentPrice) / max($entry, 0.00000001)) * 100;
            } else {
                $rawPriceChangePct = (($currentPrice - $entry) / max($entry, 0.00000001)) * 100;
            }

            // Leverage-adjusted PnL, net of accumulated funding charges.
            $unrealizedPct = round($rawPriceChangePct * $leverage, 2);
            $unrealizedAmt = round(($margin * ($unrealizedPct / 100)) - (float) $trade->funding_paid, 2);

            // Target level indicators for visual cues
            $targetStatus = null;
            if ($isShort) {
                if ($tp2 > 0 && $currentPrice <= $tp2) $targetStatus = 'TP2';
                elseif ($tp1 > 0 && $currentPrice <= $tp1) $targetStatus = 'TP1';
                elseif ($sl > 0 && $currentPrice >= $sl) $targetStatus = 'SL';
            } else {
                if ($tp2 > 0 && $currentPrice >= $tp2) $targetStatus = 'TP2';
                elseif ($tp1 > 0 && $currentPrice >= $tp1) $targetStatus = 'TP1';
                elseif ($sl > 0 && $currentPrice <= $sl) $targetStatus = 'SL';
            }

            $trade->current_price = $currentPrice;
            $trade->unrealized_pnl_percent = $unrealizedPct;
            $trade->unrealized_pnl_amount = $unrealizedAmt;
            $trade->target_status = $targetStatus;
            $trade->current_position_value = round($margin + $unrealizedAmt, 2);
        }

        return $openTrades;
    }

    /**
     * Charge (or credit) funding for a leveraged futures position if a full
     * funding interval has elapsed since it was last charged. Persists
     * immediately via increment()/update() rather than only decorating the
     * in-memory object, so the charge survives across polls and is reflected
     * in the eventual realized PnL when the position closes.
     */
    protected function applyFundingIfDue(SimulatedTrade $trade, float $margin, int $leverage, bool $isShort): void
    {
        $lastFunding = $trade->last_funding_at ?? $trade->entry_time ?? now();
        if (abs(now()->diffInHours($lastFunding)) < self::FUNDING_INTERVAL_HOURS) {
            return;
        }

        $notional = $margin * $leverage;
        // Longs pay (negative PnL impact) when funding rate is positive;
        // shorts receive in that case — sign flips per side.
        $fundingAmount = round($notional * (self::FUNDING_RATE_PCT / 100) * ($isShort ? -1 : 1), 2);

        $trade->increment('funding_paid', $fundingAmount);
        $trade->update(['last_funding_at' => now()]);
    }

    /**
     * Get complete breakdown of wallet balance, locked margin, and live equity.
     */
    public function getWalletSummary(User $user): array
    {
        $account = $this->getOrCreateAccount($user);
        $openTrades = $this->evaluateOpenTrades($user);

        $lockedMargin = (float) $openTrades->sum('margin_amount');
        $availableCash = max(0.0, (float) $account->virtual_balance - $lockedMargin);
        $floatingPnlUsd = (float) $openTrades->sum('unrealized_pnl_amount');
        $totalPositionsValue = round($lockedMargin + $floatingPnlUsd, 2);
        $totalVirtualEquity = round($availableCash + $totalPositionsValue, 2);
        $floatingPnlPct = $lockedMargin > 0 ? round(($floatingPnlUsd / $lockedMargin) * 100, 2) : 0.0;

        return [
            'virtual_balance' => (float) $account->virtual_balance,
            'formatted_virtual_balance' => '$' . number_format($account->virtual_balance, 2),
            'initial_balance' => (float) $account->initial_balance,
            'formatted_initial_balance' => '$' . number_format($account->initial_balance, 2),
            'available_cash' => $availableCash,
            'formatted_available_cash' => '$' . number_format($availableCash, 2),
            'locked_margin' => $lockedMargin,
            'formatted_locked_margin' => '$' . number_format($lockedMargin, 2),
            'total_positions_value' => $totalPositionsValue,
            'formatted_positions_value' => '$' . number_format($totalPositionsValue, 2),
            'floating_unrealized_pnl_usd' => $floatingPnlUsd,
            'formatted_floating_pnl_usd' => ($floatingPnlUsd >= 0 ? '+$' : '-$') . number_format(abs($floatingPnlUsd), 2),
            'floating_unrealized_pnl_pct' => $floatingPnlPct,
            'total_virtual_equity' => $totalVirtualEquity,
            'formatted_total_equity' => '$' . number_format($totalVirtualEquity, 2),
            'win_rate' => $account->win_rate,
            'total_trades' => $account->total_trades,
            'winning_trades' => $account->winning_trades,
            'losing_trades' => $account->losing_trades,
            'net_profit_amount' => $account->net_profit_amount,
            'formatted_net_profit' => ($account->net_profit_amount >= 0 ? '+$' : '-$') . number_format(abs($account->net_profit_amount), 2),
            'open_trades' => $openTrades,
        ];
    }

    /**
     * Finalize and close a paper trade.
     */
    public function finalizeTrade(
        SimulatedTrade $trade,
        SimulationAccount $account,
        float $exitPrice,
        string $status
    ): void {
        $entry = (float) $trade->entry_price;
        $leverage = max(1, (int) ($trade->leverage ?? 1));
        $isShort = strtolower($trade->side ?? 'long') === 'short';

        if ($isShort) {
            $rawPriceChangePct = (($entry - $exitPrice) / max($entry, 0.00000001)) * 100;
        } else {
            $rawPriceChangePct = (($exitPrice - $entry) / max($entry, 0.00000001)) * 100;
        }

        $pnlPercent = round($rawPriceChangePct * $leverage, 2);
        $margin = (float) ($trade->margin_amount > 0 ? $trade->margin_amount : $trade->allocated_amount);
        // Net out any accumulated funding charges (futures-leveraged
        // positions only — funding_paid stays 0 for spot/unleveraged trades).
        $pnlAmount = round(($margin * ($pnlPercent / 100)) - (float) $trade->funding_paid, 2);

        $trade->update([
            'status' => $status,
            'exit_price' => $exitPrice,
            'exit_time' => now(),
            'pnl_amount' => $pnlAmount,
            'pnl_percent' => $pnlPercent,
        ]);

        $isWin = $pnlAmount >= 0;

        $account->update([
            'virtual_balance' => round($account->virtual_balance + $pnlAmount, 2),
            'total_trades' => $account->total_trades + 1,
            'winning_trades' => $account->winning_trades + ($isWin ? 1 : 0),
            'losing_trades' => $account->losing_trades + ($isWin ? 0 : 1),
        ]);
    }

    /**
     * Close a paper trade manually at market price.
     */
    public function closeTradeManually(User $user, int $tradeId): ?SimulatedTrade
    {
        return DB::transaction(function () use ($user, $tradeId) {
            $account = $this->lockAccountForUpdate($user);

            // lockForUpdate() + the status='open' filter means a second,
            // concurrent close request for the same trade finds no row here
            // (it blocks until this transaction commits, by which point the
            // status is no longer 'open') — prevents double-crediting PnL.
            $trade = SimulatedTrade::where('user_id', $user->id)
                ->where('id', $tradeId)
                ->where('status', 'open')
                ->lockForUpdate()
                ->first();

            if (!$trade) {
                return null;
            }

            $liveCoins = collect($this->marketFetcher->getTopCoinsList());
            $coinMatch = $liveCoins->firstWhere('symbol', $trade->symbol);
            $exitPrice = $coinMatch ? (float) $coinMatch['price'] : (float) $trade->entry_price;

            // Win/loss must account for position side: for a short, price falling
            // is profitable, not the other way around.
            $entry = (float) $trade->entry_price;
            $isShort = strtolower($trade->side ?? 'long') === 'short';
            $isProfitable = $isShort ? ($exitPrice <= $entry) : ($exitPrice >= $entry);
            $status = $isProfitable ? 'win_manual' : 'loss_manual';
            $this->finalizeTrade($trade, $account, $exitPrice, $status);

            return $trade;
        });
    }

    /**
     * Reset simulation account balance with custom initial balance.
     */
    public function resetAccount(User $user, float $initialBalance = 10000.00): SimulationAccount
    {
        return DB::transaction(function () use ($user, $initialBalance) {
            $account = $this->lockAccountForUpdate($user);
            $initialBalance = max(100.0, $initialBalance);

            // Delete previous paper trades
            SimulatedTrade::where('user_id', $user->id)
                ->where('mode', 'paper_trade')
                ->delete();

            $account->update([
                'virtual_balance' => $initialBalance,
                'initial_balance' => $initialBalance,
                'total_trades' => 0,
                'winning_trades' => 0,
                'losing_trades' => 0,
            ]);

            return $account;
        });
    }
}
