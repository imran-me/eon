<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AttendanceExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithEvents
{
    protected $attendances;
    protected $user;

    public function __construct($attendances, $user)
    {
        $this->attendances = $attendances;
        $this->user = $user;
    }

    public function collection()
    {
        return $this->attendances;
    }

    public function headings(): array
    {
        // These are the column headers starting at Row 6 (due to our custom header)
        return ['Date', 'Day', 'Shift', 'Check In', 'Check Out', 'Worked Hour', 'Status', 'Note'];
    }

    public function map($attendance): array
    {
        $dateValue = $attendance->date;
        $dayName = '';

        // If it's a date record
        if ($dateValue && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateValue)) {
            $date = \Carbon\Carbon::parse($dateValue);
            $formattedDate = $date->format('d-M-Y');
            $dayName = $date->format('l');

            return [
                $formattedDate,
                $dayName,
                $attendance->shift_name ?? '-',
                $attendance->check_in ? \Carbon\Carbon::parse($attendance->check_in)->format('h:i A') : '--:--',
                $attendance->check_out ? \Carbon\Carbon::parse($attendance->check_out)->format('h:i A') : '--:--',
                $attendance->worked_display ? $attendance->worked_display : '--:--',
                ucfirst($attendance->status),
                $attendance->note ?? '',
            ];
        }

        // If it's a Summary record (we put the value in the second column)
        return [
            $attendance->date,
            $attendance->shift_name, // This is where we stored the count value in the controller
            '',
            '',
            '',
            '',
            '',
            ''
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet;

                $sheet->insertNewRowBefore(1, 7);
                $headingRow = 8;
                $dateRows = $this->attendances->filter(fn ($row) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $row->date))->count();
                $dataStart = $headingRow + 1;
                $dataEnd = $headingRow + $dateRows;
                $summaryTitleRow = $dataEnd + 2;
                $highestRow = $sheet->getHighestRow();
                $firstDate = $this->attendances->first(fn ($row) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $row->date))?->date;
                $period = $firstDate ? \Carbon\Carbon::parse($firstDate)->format('F Y') : 'N/A';
                $employeeId = $this->user->employee_id_no ?: 'EMP-' . str_pad((string) $this->user->id, 4, '0', STR_PAD_LEFT);

                $sheet->mergeCells('A1:H1');
                $sheet->mergeCells('A2:H2');
                $sheet->mergeCells('A4:D4');
                $sheet->mergeCells('E4:H4');
                $sheet->mergeCells('A5:D5');
                $sheet->mergeCells('E5:H5');
                $sheet->mergeCells('A6:D6');
                $sheet->mergeCells('E6:H6');

                $sheet->setCellValue('A1', strtoupper($this->user->company?->name ?? config('app.name')));
                $sheet->setCellValue('A2', 'MONTHLY ATTENDANCE STATEMENT');
                $sheet->setCellValue('A4', 'Employee: ' . ($this->user->name ?? 'N/A'));
                $sheet->setCellValue('E4', 'Employee ID: ' . $employeeId);
                $sheet->setCellValue('A5', 'Email: ' . ($this->user->email ?? 'N/A'));
                $sheet->setCellValue('E5', 'Phone: ' . ($this->user->phone ?? 'N/A'));
                $sheet->setCellValue('A6', 'Period: ' . $period);
                $sheet->setCellValue('E6', 'Generated: ' . now()->format('d M Y') . ' | By: ' . (auth()->user()?->name ?? 'System'));

                $sheet->getStyle('A1:H1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '3730A3']],
                    'alignment' => ['horizontal' => 'center'],
                ]);
                $sheet->getStyle('A2:H2')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '564FC8']],
                    'alignment' => ['horizontal' => 'center'],
                ]);
                $sheet->getStyle('A4:H6')->applyFromArray([
                    'font' => ['size' => 10, 'color' => ['rgb' => '334155']],
                    'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'F8F9FF']],
                    'borders' => ['outline' => ['borderStyle' => 'thin', 'color' => ['rgb' => 'E2E8F0']]],
                ]);
                $sheet->getStyle("A{$headingRow}:H{$headingRow}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '564FC8']],
                    'alignment' => ['horizontal' => 'center'],
                ]);

                for ($row = $dataStart; $row <= $dataEnd; $row++) {
                    if (($row - $dataStart) % 2 === 1) {
                        $sheet->getStyle("A{$row}:H{$row}")->getFill()->setFillType('solid')->getStartColor()->setRGB('F8F9FF');
                    }
                    $sheet->getStyle("A{$row}:H{$row}")->getBorders()->getBottom()->setBorderStyle('hair')->getColor()->setRGB('E2E8F0');
                    $status = strtolower((string) $sheet->getCell("G{$row}")->getValue());
                    $color = str_starts_with($status, 'present') ? '15803D'
                        : (str_starts_with($status, 'late') ? 'B45309'
                        : ($status === 'absent' ? 'BE123C'
                        : (str_contains($status, 'leave') ? '7E22CE' : '64748B')));
                    $sheet->getStyle("G{$row}")->getFont()->setBold(true)->getColor()->setRGB($color);
                }

                if ($summaryTitleRow <= $highestRow) {
                    $sheet->mergeCells("A{$summaryTitleRow}:H{$summaryTitleRow}");
                    $sheet->getStyle("A{$summaryTitleRow}:H{$summaryTitleRow}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => '3730A3']],
                        'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'F5F7FF']],
                    ]);
                    $sheet->getStyle('A' . ($summaryTitleRow + 1) . ':B' . $highestRow)->applyFromArray([
                        'font' => ['bold' => true],
                        'borders' => ['allBorders' => ['borderStyle' => 'thin', 'color' => ['rgb' => 'E2E8F0']]],
                    ]);
                }

                foreach (['A' => 14, 'B' => 13, 'C' => 18, 'D' => 14, 'E' => 14, 'F' => 15, 'G' => 16, 'H' => 34] as $column => $width) {
                    $sheet->getColumnDimension($column)->setAutoSize(false)->setWidth($width);
                }
                $sheet->freezePane("A{$dataStart}");
                $sheet->setAutoFilter("A{$headingRow}:H{$dataEnd}");
                $sheet->getPageSetup()->setOrientation('portrait')->setPaperSize('9')->setFitToWidth(1)->setFitToHeight(0);
                $sheet->getPageSetup()->setFitToPage(true);
                $sheet->getPageMargins()->setTop(0.35)->setBottom(0.35)->setLeft(0.3)->setRight(0.3);
                $sheet->getHeaderFooter()->setOddFooter('&L' . ($this->user->company?->name ?? config('app.name')) . '&RPage &P of &N');
                $sheet->getStyle("A1:H{$highestRow}")->getAlignment()->setVertical('center')->setWrapText(true);
            },
        ];
    }
}
