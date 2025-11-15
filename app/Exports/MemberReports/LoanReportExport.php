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
use PhpOffice\PhpSpreadsheet\Style\Font;

class LoanReportExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle
{
    protected $loans;
    protected $stats;

    public function __construct($loans, $stats)
    {
        $this->loans = collect($loans);
        $this->stats = $stats;
    }

    public function collection()
    {
        return $this->loans;
    }

    public function headings(): array
    {
        return [
            'Reference',
            'Type',
            'Loan Amount (₦)',
            'Repaid (₦)',
            'Balance (₦)',
            'Interest Rate (%)',
            'Progress (%)',
            'Status',
            'Due Date',
        ];
    }

    public function map($loan): array
    {
        return [
            $loan['reference'] ?? 'N/A',
            $loan['type'] ?? 'N/A',
            number_format($loan['amount'], 2),
            number_format($loan['repaid'], 2),
            number_format($loan['balance'], 2),
            number_format($loan['interest_rate'], 2),
            number_format($loan['progress'], 2),
            ucfirst($loan['status']),
            $loan['due_date'] ?? 'N/A',
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
        return 'Loans';
    }
}

