<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Riwayat Backtest</title>
    <style>
        @page { margin: 24px; }
        body { font-family: 'Helvetica', Arial, sans-serif; font-size: 10px; color: #1a202c; }
        .header { margin-bottom: 16px; border-bottom: 2px solid #2563eb; padding-bottom: 10px; }
        .header h1 { font-size: 18px; margin: 0 0 4px 0; color: #1e293b; }
        .header p { font-size: 10px; color: #64748b; margin: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background-color: #1e293b; color: #ffffff; padding: 6px 5px; text-align: left; font-size: 9px; text-transform: uppercase; }
        td { padding: 5px; border-bottom: 1px solid #e2e8f0; font-size: 9px; }
        tr:nth-child(even) { background-color: #f8fafc; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .profit { color: #059669; font-weight: bold; }
        .loss { color: #e11d48; font-weight: bold; }
        .badge-valid { background-color: #d1fae5; color: #059669; padding: 2px 6px; border-radius: 3px; font-weight: bold; }
        .badge-invalid { background-color: #fee2e2; color: #e11d48; padding: 2px 6px; border-radius: 3px; font-weight: bold; }
        .footer { margin-top: 16px; font-size: 8px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 8px; }
        .empty { text-align: center; padding: 30px; color: #94a3b8; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Riwayat Backtest — ALPHAMACHINE Trading Dashboard</h1>
        <p>Akun: {{ $user->name ?? $user->email }} &bull; Dicetak: {{ $generatedAt->format('d M Y, H:i') }} &bull; Total Run: {{ $runs->count() }}</p>
    </div>

    @if($runs->isEmpty())
        <div class="empty">Belum ada riwayat backtest tersimpan.</div>
    @else
        <table>
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>Koin</th>
                    <th>TF</th>
                    <th class="text-right">Target %</th>
                    <th class="text-right">Fee/Slip %</th>
                    <th class="text-right">Modal Awal</th>
                    <th class="text-right">Saldo Akhir</th>
                    <th class="text-right">Net Profit</th>
                    <th class="text-right">Win Rate</th>
                    <th class="text-right">Profit Factor</th>
                    <th class="text-right">Max DD</th>
                    <th class="text-right">Total Sinyal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($runs as $run)
                    <tr>
                        <td>{{ $run->created_at->format('d/m/Y H:i') }}</td>
                        <td><strong>{{ $run->symbol }}</strong></td>
                        <td>{{ strtoupper($run->timeframe) }}</td>
                        <td class="text-right">+{{ number_format($run->target_min_pct, 1) }}% / +{{ number_format($run->target_max_pct, 1) }}%</td>
                        <td class="text-right">{{ number_format($run->fee_pct, 2) }}% / {{ number_format($run->slippage_pct, 2) }}%</td>
                        <td class="text-right">${{ number_format($run->initial_capital, 2) }}</td>
                        <td class="text-right">${{ number_format($run->final_balance, 2) }}</td>
                        <td class="text-right {{ $run->net_profit_amount >= 0 ? 'profit' : 'loss' }}">
                            {{ $run->net_profit_amount >= 0 ? '+' : '-' }}${{ number_format(abs($run->net_profit_amount), 2) }}
                            ({{ $run->net_profit_percent >= 0 ? '+' : '' }}{{ number_format($run->net_profit_percent, 2) }}%)
                        </td>
                        <td class="text-right">
                            <span class="{{ $run->win_rate >= 80 ? 'badge-valid' : 'badge-invalid' }}">{{ number_format($run->win_rate, 1) }}%</span>
                        </td>
                        <td class="text-right">{{ number_format($run->profit_factor, 2) }}</td>
                        <td class="text-right loss">-{{ number_format($run->max_drawdown_percent, 2) }}%</td>
                        <td class="text-right">{{ $run->total_trades }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        Catatan: Hasil backtest historis tidak menjamin performa trading di masa depan. Fee &amp; slippage sudah dimodelkan dalam perhitungan PnL. Dokumen ini dihasilkan otomatis dari data simulasi AlphaJournal.
    </div>
</body>
</html>
