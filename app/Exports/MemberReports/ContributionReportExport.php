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

class ContributionReportExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle
{
    protected $contributions;
    protected $stats;

    public function __construct($contributions, $stats)
    {
        $this->contributions = collect($contributions);
        $this->stats = $stats;
    }

    public function collection()
    {
        return $this->contributions;
    }

    public function headings(): array
    {
        return [
            'Date',
            'Reference',
            'Type',
            'Amount (₦)',
            'Status',
            'Plan',
        ];
    }

    public function map($contribution): array
    {
        return [
            $contribution['date'] ?? 'N/A',
            $contribution['reference'] ?? 'N/A',
            $contribution['type'] ?? 'N/A',
            number_format($contribution['amount'], 2),
            ucfirst($contribution['status']),
            $contribution['plan'] ?? 'N/A',
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
        return 'Contributions';
    }
}

