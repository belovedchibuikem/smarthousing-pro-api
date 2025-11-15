<?php

namespace App\Exports\MemberReports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class PropertyReportExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle
{
    protected $properties;
    protected $stats;

    public function __construct($properties, $stats)
    {
        $this->properties = collect($properties);
        $this->stats = $stats;
    }

    public function collection()
    {
        return $this->properties;
    }

    public function headings(): array
    {
        return [
            'Property Name',
            'Type',
            'Location',
            'Size',
            'Total Cost (₦)',
            'Amount Paid (₦)',
            'Balance (₦)',
            'Payment Progress (%)',
            'Payment Status',
            'Payment Method',
            'Subscription Date',
            'Last Payment',
        ];
    }

    public function map($property): array
    {
        $balance = $property['total_cost'] - $property['amount_paid'];
        $progress = $property['total_cost'] > 0 ? ($property['amount_paid'] / $property['total_cost']) * 100 : 0;

        return [
            $property['name'] ?? 'N/A',
            ucfirst($property['type'] ?? 'N/A'),
            $property['location'] ?? 'N/A',
            $property['size'] ?? 'N/A',
            number_format($property['total_cost'], 2),
            number_format($property['amount_paid'], 2),
            number_format($balance, 2),
            number_format($progress, 2),
            ucfirst($property['payment_status'] ?? 'N/A'),
            $property['payment_method'] ?? 'N/A',
            $property['subscription_date'] ?? 'N/A',
            $property['last_payment'] ?? 'N/A',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 12],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E5E7EB'],
                ],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];
    }

    public function title(): string
    {
        return 'Properties';
    }
}

