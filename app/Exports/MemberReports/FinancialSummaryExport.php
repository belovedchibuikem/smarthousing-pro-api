<?php

namespace App\Exports\MemberReports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class FinancialSummaryExport implements FromArray, WithStyles, WithTitle
{
    protected $data;

    public function __construct($financialData, $netWorth, $totalAssets, $totalLiabilities, $monthlyData)
    {
        $this->data = [
            ['Financial Summary Report'],
            ['Generated: ' . now()->format('Y-m-d H:i:s')],
            [''],
            ['ASSETS'],
            ['Contributions', number_format($financialData['total_contributions'], 2)],
            ['Investments', number_format($financialData['total_investments'], 2)],
            ['Property Equity', number_format($financialData['property_equity'], 2)],
            ['Wallet Balance', number_format($financialData['wallet_balance'], 2)],
            ['Total Assets', number_format($totalAssets, 2)],
            [''],
            ['LIABILITIES'],
            ['Loan Balance', number_format($financialData['loan_balance'], 2)],
            ['Total Liabilities', number_format($totalLiabilities, 2)],
            [''],
            ['NET WORTH', number_format($netWorth, 2)],
            [''],
            ['MONTHLY TRENDS'],
            ['Month', 'Income', 'Expenses', 'Net'],
        ];

        foreach ($monthlyData as $month) {
            $this->data[] = [
                $month['month'],
                number_format($month['income'], 2),
                number_format($month['expenses'], 2),
                number_format($month['income'] - $month['expenses'], 2),
            ];
        }
    }

    public function array(): array
    {
        return $this->data;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 14],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            4 => [
                'font' => ['bold' => true, 'size' => 12],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E5E7EB'],
                ],
            ],
            11 => [
                'font' => ['bold' => true, 'size' => 12],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E5E7EB'],
                ],
            ],
            14 => [
                'font' => ['bold' => true, 'size' => 12],
            ],
            17 => [
                'font' => ['bold' => true, 'size' => 12],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E5E7EB'],
                ],
            ],
        ];
    }

    public function title(): string
    {
        return 'Financial Summary';
    }
}

