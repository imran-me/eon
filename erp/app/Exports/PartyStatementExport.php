<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class PartyStatementExport implements FromArray, WithHeadings, WithTitle, WithStyles, ShouldAutoSize
{
    protected array $rows;
    protected string $partyName;
    protected float $openingBalance;
    protected float $totalDebit;
    protected float $totalCredit;
    protected float $closingBalance;

    public function __construct(array $rows, string $partyName, float $openingBalance, float $totalDebit, float $totalCredit, float $closingBalance)
    {
        $this->rows           = $rows;
        $this->partyName      = $partyName;
        $this->openingBalance = $openingBalance;
        $this->totalDebit     = $totalDebit;
        $this->totalCredit    = $totalCredit;
        $this->closingBalance = $closingBalance;
    }

    public function headings(): array
    {
        return ['Date', 'Voucher #', 'Description', 'Type', 'Debit (৳)', 'Credit (৳)', 'Balance (৳)'];
    }

    public function array(): array
    {
        $data = [];

        // Opening row
        $data[] = ['', '', 'Opening Balance B/F', '', '', '', number_format($this->openingBalance, 2)];

        foreach ($this->rows as $t) {
            $data[] = [
                $t['payment_date'],
                $t['reference_no'] ?? '—',
                $t['remarks'] ?? '—',
                strtoupper(str_replace('_', ' ', $t['type'])),
                $t['debit'] > 0 ? number_format($t['debit'], 2) : '',
                $t['credit'] > 0 ? number_format($t['credit'], 2) : '',
                number_format($t['balance'], 2),
            ];
        }

        // Totals row
        $data[] = ['', '', 'CLOSING BALANCE', '', number_format($this->totalDebit, 2), number_format($this->totalCredit, 2), number_format($this->closingBalance, 2)];

        return $data;
    }

    public function title(): string
    {
        return 'Statement';
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = count($this->rows) + 3;

        // Header row style
        $sheet->insertNewRowBefore(1, 2);
        $sheet->setCellValue('A1', 'Party Statement — ' . $this->partyName);
        $sheet->setCellValue('A2', 'Generated: ' . now()->format('d M Y H:i'));
        $sheet->mergeCells('A1:G1');
        $sheet->mergeCells('A2:G2');

        return [
            1 => ['font' => ['bold' => true, 'size' => 14], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]],
            2 => ['font' => ['size' => 10, 'color' => ['rgb' => '6B7280']], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]],
            3 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1D4ED8']],
            ],
            $lastRow + 2 => [
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBEAFE']],
            ],
        ];
    }
}
