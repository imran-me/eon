<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * "Expenses by Category" as a spreadsheet — the layout the owner's accountant already works from:
 *
 *     row 1  company
 *     row 2  Expenses by Category
 *     row 3  From … To …
 *     row 4  account_code | account_name | amount | amount_with_tax
 *     rows   one per expense account that moved in the period, A→Z by name
 *     last   Total
 *
 * `account_code` is the ERP's own chart code (the sample carried the old system's internal id).
 * `amount_with_tax` equals `amount`: VAT is booked to its own expense accounts here (e.g. "VAT On
 * Sales"), so no extra tax is hidden inside a line.
 *
 * WithStrictNullComparison, so a 0.00 is written as 0.00 and not as an empty cell.
 */
class ExpensesByCategoryExport implements FromArray, WithTitle, WithStyles, WithColumnFormatting, WithStrictNullComparison, ShouldAutoSize
{
    /** @param list<array{code:string,name:string,amount:float}> $lines */
    public function __construct(private array $lines, private string $company, private string $from, private string $to)
    {
    }

    public function title(): string
    {
        return 'Expense Summary by Category';
    }

    public function array(): array
    {
        $rows = [
            [$this->company, '', '', ''],
            ['Expenses by Category', '', '', ''],
            ['From ' . date('d M Y', strtotime($this->from)) . ' To ' . date('d M Y', strtotime($this->to)), '', '', ''],
            ['account_code', 'account_name', 'amount', 'amount_with_tax'],
        ];

        $total = 0.0;
        foreach ($this->lines as $l) {
            $a = round((float) $l['amount'], 2);
            $rows[] = [(string) $l['code'], $l['name'], $a, $a];
            $total += $a;
        }
        $total = round($total, 2);
        $rows[] = ['', 'Total', $total, $total];

        return $rows;
    }

    public function columnFormats(): array
    {
        return ['C' => '#,##0.00', 'D' => '#,##0.00', 'A' => NumberFormat::FORMAT_TEXT];
    }

    public function styles(Worksheet $sheet): array
    {
        $last = count($this->lines) + 5;

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A3')->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF64748B'));

        $sheet->getStyle('A4:D4')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF0F766E']],
        ]);
        $sheet->getStyle('C4:D4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("C5:D{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $sheet->getStyle("A{$last}:D{$last}")->applyFromArray([
            'font' => ['bold' => true],
            'borders' => ['top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF0F172A']]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF1F5F9']],
        ]);

        $sheet->freezePane('A5');

        return [];
    }
}
