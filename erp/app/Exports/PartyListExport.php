<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * Agent / Vendor / Customer list export — the same sheet for all three,
 * `$label` just switches the title text.
 *
 * WithStrictNullComparison matters here: without it a zero balance compares
 * loosely equal to null and gets written as an empty cell instead of 0.00.
 */
class PartyListExport implements FromArray, WithHeadings, WithTitle, WithStyles, WithColumnFormatting, WithStrictNullComparison, ShouldAutoSize
{
    protected array $rows;
    protected string $label;
    protected ?string $search;

    public function __construct(array $rows, string $label, ?string $search = null)
    {
        $this->rows   = $rows;
        $this->label  = $label;
        $this->search = $search;
    }

    public function headings(): array
    {
        return [
            $this->label . ' ID',
            'Name',
            'Contact Person',
            'Email',
            'Phone',
            'Address',
            'Balance (৳)',
            'Dr/Cr',
            'Last Transaction',
            'Status',
        ];
    }

    public function array(): array
    {
        return array_map(fn($r) => [
            $r['party_id'],
            $r['name'],
            $r['contact_person'] ?: '—',
            $r['email'] ?: '—',
            $r['phone'] ?: '—',
            $r['address'] ?: '—',
            $r['balance'],
            $r['balance_type'] ?: '—',
            $r['last_transaction'] ?: '—',
            $r['status'],
        ], $this->rows);
    }

    public function title(): string
    {
        return $this->label . ' List';
    }

    public function columnFormats(): array
    {
        return ['G' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->insertNewRowBefore(1, 2);
        $sheet->setCellValue('A1', $this->label . ' List');
        $sheet->setCellValue('A2', 'Generated: ' . now()->format('d M Y H:i')
            . ($this->search ? '  |  Search: ' . $this->search : '')
            . '  |  Total: ' . count($this->rows));
        $sheet->mergeCells('A1:J1');
        $sheet->mergeCells('A2:J2');

        return [
            1 => [
                'font'      => ['bold' => true, 'size' => 14],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            2 => [
                'font'      => ['size' => 10, 'color' => ['rgb' => '6B7280']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            3 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1D4ED8']],
            ],
        ];
    }
}
