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

class MortgageReportExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle
{
    protected $mortgages;
    protected $stats;

    public function __construct($mortgages, $stats)
    {
        $this->mortgages = collect($mortgages);
        $this->stats = $stats;
    }

    public function collection()
    {
        return $this->mortgages;
    }

    public function headings(): array
    {
        return [
            'Reference',
            'Type',
            'Provider',
            'Mortgage Amount (₦)',
            'Repaid (₦)',
            'Balance (₦)',
            'Interest Rate (%)',
            'Monthly Payment (₦)',
            'Progress (%)',
            'Status',
            'Created Date',
        ];
    }

    public function map($mortgage): array
    {
        return [
            $mortgage['reference'] ?? 'N/A',
            $mortgage['type'] === 'mortgage' ? 'External Mortgage' : 'Internal Mortgage',
            $mortgage['provider'] ?? 'N/A',
            number_format($mortgage['amount'], 2),
            number_format($mortgage['repaid'], 2),
            number_format($mortgage['balance'], 2),
            number_format($mortgage['interest_rate'], 2),
            number_format($mortgage['monthly_payment'], 2),
            number_format($mortgage['progress'], 2),
            ucfirst($mortgage['status']),
            $mortgage['created_at'] ?? 'N/A',
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
        return 'Mortgages';
    }
}

