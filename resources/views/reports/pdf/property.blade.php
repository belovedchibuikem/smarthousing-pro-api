<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Property Report</title>
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
        <h1>Property Report</h1>
        <p>{{ $member->user->name ?? 'Member' }}</p>
        <p>Generated: {{ now()->format('Y-m-d H:i:s') }}</p>
        @if($dateFrom && $dateTo)
            <p>Period: {{ $dateFrom->format('Y-m-d') }} to {{ $dateTo->format('Y-m-d') }}</p>
        @endif
    </div>

    <div class="stats">
        <table>
            <tr>
                <td>Total Properties</td>
                <td>{{ $stats['total_properties'] }}</td>
            </tr>
            <tr>
                <td>Completed</td>
                <td>{{ $stats['completed_properties'] }}</td>
            </tr>
            <tr>
                <td>Ongoing</td>
                <td>{{ $stats['ongoing_properties'] }}</td>
            </tr>
            <tr>
                <td>Total Invested</td>
                <td>₦{{ number_format($stats['total_invested'], 2) }}</td>
            </tr>
            <tr>
                <td>Total Value</td>
                <td>₦{{ number_format($stats['total_value'], 2) }}</td>
            </tr>
        </table>
    </div>

    <table>
        <thead>
            <tr>
                <th>Property Name</th>
                <th>Type</th>
                <th>Location</th>
                <th>Total Cost</th>
                <th>Amount Paid</th>
                <th>Balance</th>
                <th>Progress</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($properties as $property)
                @php
                    $balance = $property['total_cost'] - $property['amount_paid'];
                    $progress = $property['total_cost'] > 0 ? ($property['amount_paid'] / $property['total_cost']) * 100 : 0;
                @endphp
                <tr>
                    <td>{{ $property['name'] }}</td>
                    <td>{{ ucfirst($property['type']) }}</td>
                    <td>{{ $property['location'] }}</td>
                    <td>₦{{ number_format($property['total_cost'], 2) }}</td>
                    <td>₦{{ number_format($property['amount_paid'], 2) }}</td>
                    <td>₦{{ number_format($balance, 2) }}</td>
                    <td>{{ number_format($progress, 1) }}%</td>
                    <td>{{ ucfirst($property['payment_status']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" style="text-align: center;">No properties found</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <p>This is a computer-generated report. No signature required.</p>
    </div>
</body>
</html>

