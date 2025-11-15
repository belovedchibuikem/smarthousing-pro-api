<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Financial Summary Report</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 24px; }
        .header p { margin: 5px 0; color: #666; }
        .net-worth { text-align: center; margin: 20px 0; padding: 20px; background-color: #f0f0f0; }
        .net-worth h2 { margin: 0; font-size: 32px; color: #2563eb; }
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
        <h1>Financial Summary Report</h1>
        <p>{{ $member->user->name ?? 'Member' }}</p>
        <p>Generated: {{ now()->format('Y-m-d H:i:s') }}</p>
        @if($dateFrom && $dateTo)
            <p>Period: {{ $dateFrom->format('Y-m-d') }} to {{ $dateTo->format('Y-m-d') }}</p>
        @endif
    </div>

    <div class="net-worth">
        <h2>Net Worth: ₦{{ number_format($netWorth, 2) }}</h2>
    </div>

    <div class="stats">
        <h3>Assets</h3>
        <table>
            <tr>
                <td>Contributions</td>
                <td>₦{{ number_format($financialData['total_contributions'], 2) }}</td>
            </tr>
            <tr>
                <td>Investments</td>
                <td>₦{{ number_format($financialData['total_investments'], 2) }}</td>
            </tr>
            <tr>
                <td>Property Equity</td>
                <td>₦{{ number_format($financialData['property_equity'], 2) }}</td>
            </tr>
            <tr>
                <td>Wallet Balance</td>
                <td>₦{{ number_format($financialData['wallet_balance'], 2) }}</td>
            </tr>
            <tr>
                <td><strong>Total Assets</strong></td>
                <td><strong>₦{{ number_format($totalAssets, 2) }}</strong></td>
            </tr>
        </table>
    </div>

    <div class="stats">
        <h3>Liabilities</h3>
        <table>
            <tr>
                <td>Loan Balance</td>
                <td>₦{{ number_format($financialData['loan_balance'], 2) }}</td>
            </tr>
            <tr>
                <td><strong>Total Liabilities</strong></td>
                <td><strong>₦{{ number_format($totalLiabilities, 2) }}</strong></td>
            </tr>
        </table>
    </div>

    <div class="stats">
        <h3>Monthly Trends (Last 6 Months)</h3>
        <table>
            <thead>
                <tr>
                    <th>Month</th>
                    <th>Income</th>
                    <th>Expenses</th>
                    <th>Net</th>
                </tr>
            </thead>
            <tbody>
                @foreach($monthlyData as $month)
                    <tr>
                        <td>{{ $month['month'] }}</td>
                        <td>₦{{ number_format($month['income'], 2) }}</td>
                        <td>₦{{ number_format($month['expenses'], 2) }}</td>
                        <td>₦{{ number_format($month['income'] - $month['expenses'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="footer">
        <p>This is a computer-generated report. No signature required.</p>
    </div>
</body>
</html>

