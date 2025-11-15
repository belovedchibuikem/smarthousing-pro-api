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

class InvestmentReportExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle
{
    protected $investments;
    protected $stats;

    public function __construct($investments, $stats)
    {
        $this->investments = collect($investments);
        $this->stats = $stats;
    }

    public function collection()
    {
        return $this->investments;
    }

    public function headings(): array
    {
        return [
            'Investment Name',
            'Type',
            'Amount Invested (₦)',
            'Current Value (₦)',
            'ROI (%)',
            'Status',
            'Date',
        ];
    }

    public function map($investment): array
    {
        return [
            $investment['name'] ?? 'N/A',
            $investment['type'] ?? 'N/A',
            number_format($investment['amount'], 2),
            number_format($investment['current_value'], 2),
            number_format($investment['roi'], 2),
            ucfirst($investment['status']),
            $investment['date'] ?? 'N/A',
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
        return 'Investments';
    }
}

