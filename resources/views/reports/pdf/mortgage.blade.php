<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Mortgage Report</title>
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
        <h1>Mortgage Report</h1>
        <p>{{ $member->user->name ?? 'Member' }}</p>
        <p>Generated: {{ now()->format('Y-m-d H:i:s') }}</p>
        @if($dateFrom && $dateTo)
            <p>Period: {{ $dateFrom->format('Y-m-d') }} to {{ $dateTo->format('Y-m-d') }}</p>
        @endif
    </div>

    <div class="stats">
        <table>
            <tr>
                <td>Total Mortgages</td>
                <td>{{ $stats['total_mortgages'] }}</td>
            </tr>
            <tr>
                <td>Total Mortgage Amount</td>
                <td>₦{{ number_format($stats['total_mortgage_amount'], 2) }}</td>
            </tr>
            <tr>
                <td>Total Repaid</td>
                <td>₦{{ number_format($stats['total_repaid'], 2) }}</td>
            </tr>
            <tr>
                <td>Outstanding Balance</td>
                <td>₦{{ number_format($stats['outstanding_balance'], 2) }}</td>
            </tr>
            <tr>
                <td>Interest Paid</td>
                <td>₦{{ number_format($stats['interest_paid'], 2) }}</td>
            </tr>
        </table>
    </div>

    <table>
        <thead>
            <tr>
                <th>Reference</th>
                <th>Type</th>
                <th>Provider</th>
                <th>Amount</th>
                <th>Repaid</th>
                <th>Balance</th>
                <th>Interest Rate</th>
                <th>Monthly Payment</th>
                <th>Progress</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($mortgages as $mortgage)
                <tr>
                    <td>{{ $mortgage['reference'] }}</td>
                    <td>{{ $mortgage['type'] === 'mortgage' ? 'External' : 'Internal' }}</td>
                    <td>{{ $mortgage['provider'] }}</td>
                    <td>₦{{ number_format($mortgage['amount'], 2) }}</td>
                    <td>₦{{ number_format($mortgage['repaid'], 2) }}</td>
                    <td>₦{{ number_format($mortgage['balance'], 2) }}</td>
                    <td>{{ number_format($mortgage['interest_rate'], 2) }}%</td>
                    <td>₦{{ number_format($mortgage['monthly_payment'], 2) }}</td>
                    <td>{{ number_format($mortgage['progress'], 1) }}%</td>
                    <td>{{ ucfirst($mortgage['status']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" style="text-align: center;">No mortgages found</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <p>This is a computer-generated report. No signature required.</p>
    </div>
</body>
</html>

