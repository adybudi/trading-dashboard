<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Trade;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AnalyticsService
{
    /**
     * Get trade query builder filtered by user, optional account, and date range.
     */
    protected function getFilteredQuery(User $user, ?int $accountId = null, ?string $dateFrom = null, ?string $dateTo = null): Builder
    {
        $query = Trade::query()->whereHas('account', function ($q) use ($user, $accountId) {
            $q->where('user_id', $user->id);
            if ($accountId) {
                $q->where('id', $accountId);
            }
        });

        if ($dateFrom) {
            $query->whereDate('entry_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('entry_at', '<=', $dateTo);
        }

        return $query;
    }

    /**
     * Calculate core summary KPIs.
     */
    public function getSummaryMetrics(User $user, ?int $accountId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $baseQuery = $this->getFilteredQuery($user, $accountId, $dateFrom, $dateTo);
        
        $trades = (clone $baseQuery)->with(['strategy', 'account'])->orderBy('entry_at', 'asc')->get();
        $closedTrades = $trades->where('status', 'closed');
        $openTrades = $trades->where('status', 'open');

        $totalTrades = $trades->count();
        $totalClosed = $closedTrades->count();
        $totalOpen = $openTrades->count();

        $winningTrades = $closedTrades->filter(fn($t) => (float) $t->net_pnl > 0);
        $losingTrades = $closedTrades->filter(fn($t) => (float) $t->net_pnl < 0);
        $beTrades = $closedTrades->filter(fn($t) => (float) $t->net_pnl == 0);

        $winCount = $winningTrades->count();
        $lossCount = $losingTrades->count();
        $beCount = $beTrades->count();

        $winRate = $totalClosed > 0 ? round(($winCount / $totalClosed) * 100, 1) : 0;

        $grossProfit = (float) $winningTrades->sum('net_pnl');
        $grossLoss = abs((float) $losingTrades->sum('net_pnl'));
        $netPnL = round($grossProfit - $grossLoss, 2);
        $totalFees = (float) $trades->sum('fees');

        $profitFactor = $grossLoss > 0 ? round($grossProfit / $grossLoss, 2) : ($grossProfit > 0 ? 99.0 : 0);

        $avgWin = $winCount > 0 ? round($grossProfit / $winCount, 2) : 0;
        $avgLoss = $lossCount > 0 ? round($grossLoss / $lossCount, 2) : 0;
        $avgRR = $closedTrades->whereNotNull('actual_rr')->count() > 0 
            ? round($closedTrades->whereNotNull('actual_rr')->avg('actual_rr'), 2) 
            : 0;

        // Expectancy formula: (WinRate * AvgWin) - (LossRate * AvgLoss)
        $winRateDecimal = $totalClosed > 0 ? ($winCount / $totalClosed) : 0;
        $lossRateDecimal = $totalClosed > 0 ? ($lossCount / $totalClosed) : 0;
        $expectancy = round(($winRateDecimal * $avgWin) - ($lossRateDecimal * $avgLoss), 2);

        // Calculate Maximum Drawdown & Peak Equity
        $accounts = $accountId 
            ? Account::where('id', $accountId)->where('user_id', $user->id)->get()
            : Account::where('user_id', $user->id)->get();
        $initialCapital = (float) $accounts->sum('initial_balance');
        $currentBalance = (float) $accounts->sum('current_balance');

        $runningBalance = $initialCapital;
        $peakBalance = $initialCapital;
        $maxDrawdownAmount = 0;
        $maxDrawdownPercent = 0;

        foreach ($closedTrades as $trade) {
            $runningBalance += (float) $trade->net_pnl;
            if ($runningBalance > $peakBalance) {
                $peakBalance = $runningBalance;
            }
            $drawdown = $peakBalance - $runningBalance;
            if ($drawdown > $maxDrawdownAmount) {
                $maxDrawdownAmount = $drawdown;
                $maxDrawdownPercent = $peakBalance > 0 ? round(($drawdown / $peakBalance) * 100, 2) : 0;
            }
        }

        $netReturnPercent = $initialCapital > 0 ? round(($netPnL / $initialCapital) * 100, 2) : 0;

        return [
            'total_trades' => $totalTrades,
            'total_closed' => $totalClosed,
            'total_open' => $totalOpen,
            'win_count' => $winCount,
            'loss_count' => $lossCount,
            'break_even_count' => $beCount,
            'win_rate' => $winRate,
            'net_pnl' => $netPnL,
            'gross_profit' => $grossProfit,
            'gross_loss' => $grossLoss,
            'profit_factor' => $profitFactor,
            'avg_win' => $avgWin,
            'avg_loss' => $avgLoss,
            'avg_rr' => $avgRR,
            'expectancy' => $expectancy,
            'initial_capital' => $initialCapital,
            'current_balance' => $currentBalance,
            'net_return_percent' => $netReturnPercent,
            'max_drawdown_amount' => round($maxDrawdownAmount, 2),
            'max_drawdown_percent' => $maxDrawdownPercent,
            'total_fees' => round($totalFees, 2),
        ];
    }

    /**
     * Generate Equity Curve time-series data for chart rendering.
     */
    public function getEquityCurveData(User $user, ?int $accountId = null): array
    {
        $accounts = $accountId 
            ? Account::where('id', $accountId)->where('user_id', $user->id)->get()
            : Account::where('user_id', $user->id)->get();
        $startingBalance = (float) $accounts->sum('initial_balance');

        $closedTrades = $this->getFilteredQuery($user, $accountId)
            ->where('status', 'closed')
            ->orderBy('exit_at', 'asc')
            ->get();

        $dataPoints = [];
        $runningBalance = $startingBalance;
        $cumulativePnL = 0;

        // Base point (Initial)
        $firstDate = $closedTrades->first() ? $closedTrades->first()->entry_at->format('Y-m-d') : Carbon::now()->format('Y-m-d');
        $dataPoints[] = [
            'date' => $firstDate,
            'balance' => round($startingBalance, 2),
            'cumulative_pnl' => 0,
            'trade_label' => 'Initial Capital',
        ];

        foreach ($closedTrades as $index => $trade) {
            $pnl = (float) $trade->net_pnl;
            $runningBalance += $pnl;
            $cumulativePnL += $pnl;

            $dataPoints[] = [
                'date' => $trade->exit_at ? $trade->exit_at->format('Y-m-d H:i') : $trade->entry_at->format('Y-m-d H:i'),
                'balance' => round($runningBalance, 2),
                'cumulative_pnl' => round($cumulativePnL, 2),
                'symbol' => $trade->symbol,
                'pnl' => $pnl,
                'trade_index' => $index + 1,
            ];
        }

        return $dataPoints;
    }

    /**
     * Generate calendar heatmap day-by-day PnL mapping.
     */
    public function getCalendarHeatmapData(User $user, ?int $accountId = null, ?int $year = null, ?int $month = null): array
    {
        $year = $year ?? (int) date('Y');
        $month = $month ?? (int) date('m');

        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $trades = $this->getFilteredQuery($user, $accountId)
            ->where('status', 'closed')
            ->whereBetween('exit_at', [$startDate, $endDate])
            ->get();

        $daysMap = [];
        $current = $startDate->copy();
        while ($current->lte($endDate)) {
            $dateStr = $current->format('Y-m-d');
            $daysMap[$dateStr] = [
                'date' => $dateStr,
                'day' => (int) $current->format('j'),
                'day_of_week' => (int) $current->format('N'), // 1 (Mon) - 7 (Sun)
                'trades_count' => 0,
                'win_count' => 0,
                'loss_count' => 0,
                'net_pnl' => 0.0,
            ];
            $current->addDay();
        }

        foreach ($trades as $trade) {
            $dateStr = $trade->exit_at->format('Y-m-d');
            if (isset($daysMap[$dateStr])) {
                $daysMap[$dateStr]['trades_count']++;
                $daysMap[$dateStr]['net_pnl'] += (float) $trade->net_pnl;
                if ((float) $trade->net_pnl > 0) {
                    $daysMap[$dateStr]['win_count']++;
                } elseif ((float) $trade->net_pnl < 0) {
                    $daysMap[$dateStr]['loss_count']++;
                }
            }
        }

        return [
            'year' => $year,
            'month' => $month,
            'month_name' => $startDate->translatedFormat('F Y'),
            'days' => array_values($daysMap),
        ];
    }

    /**
     * Strategy Breakdown Performance.
     */
    public function getStrategyBreakdown(User $user, ?int $accountId = null): Collection
    {
        return $this->getFilteredQuery($user, $accountId)
            ->where('status', 'closed')
            ->with('strategy')
            ->get()
            ->groupBy(fn($t) => $t->strategy ? $t->strategy->name : 'No Strategy')
            ->map(function ($group, $strategyName) {
                $total = $group->count();
                $wins = $group->filter(fn($t) => (float) $t->net_pnl > 0)->count();
                $netPnL = (float) $group->sum('net_pnl');
                $winRate = $total > 0 ? round(($wins / $total) * 100, 1) : 0;
                $color = $group->first()->strategy ? $group->first()->strategy->color_code : '#64748B';

                return [
                    'name' => $strategyName,
                    'trades_count' => $total,
                    'win_count' => $wins,
                    'win_rate' => $winRate,
                    'net_pnl' => round($netPnL, 2),
                    'color' => $color,
                ];
            })
            ->sortByDesc('net_pnl')
            ->values();
    }
}
