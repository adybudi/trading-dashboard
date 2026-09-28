<?php

namespace App\Services;

class TradeCalculatorService
{
    /**
     * Calculate all financial metrics for a trade.
     *
     * @param array $data
     * @return array
     */
    public function calculate(array $data): array
    {
        $direction = strtolower($data['direction'] ?? 'long');
        $entryPrice = (float) ($data['entry_price'] ?? 0);
        $exitPrice = isset($data['exit_price']) && $data['exit_price'] !== null && $data['exit_price'] !== '' ? (float) $data['exit_price'] : null;
        $quantity = (float) ($data['quantity'] ?? 0);
        $fees = (float) ($data['fees'] ?? 0);
        $stopLoss = isset($data['stop_loss']) && $data['stop_loss'] !== null && $data['stop_loss'] !== '' ? (float) $data['stop_loss'] : null;
        $takeProfit = isset($data['take_profit']) && $data['take_profit'] !== null && $data['take_profit'] !== '' ? (float) $data['take_profit'] : null;

        $grossPnL = null;
        $netPnL = null;
        $roiPercentage = null;
        $plannedRR = null;
        $actualRR = null;

        // Calculate Planned Risk:Reward Ratio
        if ($stopLoss !== null && $takeProfit !== null && $entryPrice > 0) {
            $risk = ($direction === 'long') ? ($entryPrice - $stopLoss) : ($stopLoss - $entryPrice);
            $reward = ($direction === 'long') ? ($takeProfit - $entryPrice) : ($entryPrice - $takeProfit);

            if ($risk > 0 && $reward > 0) {
                $plannedRR = round($reward / $risk, 2);
            }
        }

        // If trade is closed with an exit price, calculate Gross/Net PnL and Actual RR
        if ($exitPrice !== null && $quantity > 0 && $entryPrice > 0) {
            if ($direction === 'long') {
                $grossPnL = ($exitPrice - $entryPrice) * $quantity;
            } else {
                $grossPnL = ($entryPrice - $exitPrice) * $quantity;
            }

            $netPnL = $grossPnL - $fees;
            $capitalInvested = $entryPrice * $quantity;
            $roiPercentage = $capitalInvested > 0 ? round(($netPnL / $capitalInvested) * 100, 4) : 0;

            if ($stopLoss !== null) {
                $risk = ($direction === 'long') ? ($entryPrice - $stopLoss) : ($stopLoss - $entryPrice);
                $actualGain = ($direction === 'long') ? ($exitPrice - $entryPrice) : ($entryPrice - $exitPrice);

                if ($risk > 0) {
                    $actualRR = round($actualGain / $risk, 2);
                }
            }
        }

        return [
            'gross_pnl' => $grossPnL !== null ? round($grossPnL, 2) : null,
            'net_pnl' => $netPnL !== null ? round($netPnL, 2) : null,
            'roi_percentage' => $roiPercentage,
            'planned_rr' => $plannedRR,
            'actual_rr' => $actualRR,
        ];
    }
}
