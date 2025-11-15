<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Equity Contribution Report</title>
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
        <h1>Equity Contribution Report</h1>
        <p>{{ $member->user->name ?? 'Member' }}</p>
        <p>Generated: {{ now()->format('Y-m-d H:i:s') }}</p>
        @if($dateFrom && $dateTo)
            <p>Period: {{ $dateFrom->format('Y-m-d') }} to {{ $dateTo->format('Y-m-d') }}</p>
        @endif
    </div>

    <div class="stats">
        <table>
            <tr>
                <td>Total Equity Contributions</td>
                <td>₦{{ number_format($stats['total_contributions'], 2) }}</td>
            </tr>
            <tr>
                <td>Total Payments</td>
                <td>{{ $stats['total_payments'] }}</td>
            </tr>
        </table>
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Reference</th>
                <th>Amount</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($contributions as $contribution)
                <tr>
                    <td>{{ $contribution['date'] ?? 'N/A' }}</td>
                    <td>{{ $contribution['reference'] }}</td>
                    <td>₦{{ number_format($contribution['amount'], 2) }}</td>
                    <td>{{ ucfirst($contribution['status']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" style="text-align: center;">No equity contributions found</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <p>This is a computer-generated report. No signature required.</p>
    </div>
</body>
</html>

