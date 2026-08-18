<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Any one of the loan desk's three tables, as a spreadsheet.
 *
 * Fed by LoanBookService::sheet(), so the columns, the rows and the foot are the
 * ones on screen rather than a second, drifting definition of the same table.
 *
 * Money stays NUMERIC — formatted with a taka number format rather than written
 * as "৳ 1,234" text. A column of text cannot be summed, sorted or pivoted, which
 * is most of the reason anyone exports a book of loans to a spreadsheet.
 */
class PayrollBookExport implements FromArray, WithHeadings, WithTitle, WithStyles, WithEvents, ShouldAutoSize
{
    /** Two banner rows sit above the header, so data starts on row 4. */
    private const BANNER_ROWS = 2;

    public function __construct(
        private array $sheet,
        private string $scopeLabel,
        private string $filterLabel,
    ) {
    }

    public function headings(): array
    {
        return array_column($this->sheet['headings'], 'label');
    }

    public function array(): array
    {
        $rows = $this->sheet['rows'];

        if (! empty($this->sheet['totals'])) {
            $rows[] = $this->sheet['totals'];
        }

        return $rows;
    }

    public function title(): string
    {
        // Sheet names cap at 31 characters and reject / \ ? * : [ ].
        return substr(preg_replace('/[\/\\\\?*:\[\]]/', '', $this->sheet['title']), 0, 31);
    }

    public function styles(Worksheet $sheet): array
    {
        $columns = count($this->sheet['headings']);
        $lastCol = Coordinate::stringFromColumnIndex($columns);

        // The banner has to be inserted before anything is addressed by row
        // number, or every style below would land one or two rows too high.
        $sheet->insertNewRowBefore(1, self::BANNER_ROWS);
        $sheet->setCellValue('A1', $this->sheet['title'] . ' — ' . $this->scopeLabel);
        $sheet->setCellValue('A2', $this->filterLabel . ' · generated ' . now()->format('d M Y H:i'));
        $sheet->mergeCells('A1:' . $lastCol . '1');
        $sheet->mergeCells('A2:' . $lastCol . '2');

        return [
            1 => [
                'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '0F172A']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            2 => [
                'font' => ['size' => 10, 'color' => ['rgb' => '64748B']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            3 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1D4ED8']],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $columns = count($this->sheet['headings']);
                $lastCol = Coordinate::stringFromColumnIndex($columns);
                $headerRow = self::BANNER_ROWS + 1;
                $firstDataRow = $headerRow + 1;
                $lastRow = $headerRow + count($this->sheet['rows']) + (empty($this->sheet['totals']) ? 0 : 1);

                $sheet->freezePane('A' . $firstDataRow);
                $sheet->getRowDimension($headerRow)->setRowHeight(22);

                // Taka on the money columns, right-aligned — and applied here,
                // over a known row range, rather than through WithColumnFormatting,
                // which would also reformat the banner rows above the header.
                foreach ($this->sheet['headings'] as $i => $heading) {
                    $letter = Coordinate::stringFromColumnIndex($i + 1);
                    $range = $letter . $firstDataRow . ':' . $letter . $lastRow;

                    if (! empty($heading['money'])) {
                        $sheet->getStyle($range)->getNumberFormat()->setFormatCode('#,##0.00');
                    }

                    if (! empty($heading['money']) || ($heading['align'] ?? null) === 'right') {
                        $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    }
                }

                if ($lastRow >= $firstDataRow) {
                    $sheet->getStyle('A' . $headerRow . ':' . $lastCol . $lastRow)
                        ->getBorders()->getAllBorders()
                        ->setBorderStyle(Border::BORDER_THIN)
                        ->getColor()->setARGB('FFE2E8F0');
                }

                // The foot carries the same weight it has on screen.
                if (! empty($this->sheet['totals'])) {
                    $sheet->getStyle('A' . $lastRow . ':' . $lastCol . $lastRow)->applyFromArray([
                        'font' => ['bold' => true],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBEAFE']],
                        'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF1D4ED8']]],
                    ]);
                }

                // Autofilter over the data only — including the totals row would
                // let a filter hide the foot or sort it into the middle.
                if (count($this->sheet['rows'])) {
                    $sheet->setAutoFilter('A' . $headerRow . ':' . $lastCol . ($lastRow - (empty($this->sheet['totals']) ? 0 : 1)));
                }
            },
        ];
    }
}
