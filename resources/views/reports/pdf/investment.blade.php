<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Investment Report</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 24px; }
        .header p { margin: 5px 0; color: #666; }
        .stats { margin-bottom: 20px; }
        .stats table { width: 100%; border-collapse: collapse; }
        .stats td { padding: 8px; border: 1px solid #ddd; }
        .stats td:first-child { font-weight: bold; background-color: #f5f5f5; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background-color: #4a5568; color: white; padding: 10px; text-align: left; }
        td { padding: 8px; border: 1px solid #ddd; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .footer { margin-top: 30px; text-align: center; font-size: 10px; color: #666; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Investment Report</h1>
        <p>{{ $member->user->name ?? 'Member' }}</p>
        <p>Generated: {{ now()->format('Y-m-d H:i:s') }}</p>
        @if($dateFrom && $dateTo)
            <p>Period: {{ $dateFrom->format('Y-m-d') }} to {{ $dateTo->format('Y-m-d') }}</p>
        @endif
    </div>

    <div class="stats">
        <table>
            <tr>
                <td>Total Invested</td>
                <td>₦{{ number_format($stats['total_invested'], 2) }}</td>
            </tr>
            <tr>
                <td>Current Value</td>
                <td>₦{{ number_format($stats['current_value'], 2) }}</td>
            </tr>
            <tr>
                <td>Total ROI</td>
                <td>₦{{ number_format($stats['total_roi'], 2) }} ({{ number_format($stats['roi_percentage'], 2) }}%)</td>
            </tr>
            <tr>
                <td>Active Investments</td>
                <td>{{ $stats['active_investments'] }}</td>
            </tr>
        </table>
    </div>

    <table>
        <thead>
            <tr>
                <th>Investment</th>
                <th>Type</th>
                <th>Amount Invested</th>
                <th>Current Value</th>
                <th>ROI</th>
                <th>Status</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            @forelse($investments as $investment)
                <tr>
                    <td>{{ $investment['name'] }}</td>
                    <td>{{ $investment['type'] }}</td>
                    <td>₦{{ number_format($investment['amount'], 2) }}</td>
                    <td>₦{{ number_format($investment['current_value'], 2) }}</td>
                    <td>{{ number_format($investment['roi'], 2) }}%</td>
                    <td>{{ ucfirst($investment['status']) }}</td>
                    <td>{{ $investment['date'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align: center;">No investments found</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <p>This is a computer-generated report. No signature required.</p>
    </div>
</body>
</html>

