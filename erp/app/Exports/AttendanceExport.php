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
        return [
            6 => ['font' => ['bold' => true]], // Heading row is now 6
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet;

                // Insert 5 empty rows at the top for our header
                $sheet->insertNewRowBefore(1, 5);

                // Merge cells for professional look
                $sheet->mergeCells('A1:G1');
                $sheet->mergeCells('A2:G2');
                $sheet->mergeCells('A3:G3');
                $sheet->mergeCells('A4:G4');

                // Set Header Content
                $sheet->setCellValue('A1', 'Company: ' . ($this->user->company?->name ?? 'N/A'));
                $sheet->setCellValue('A2', 'Employee: ' . ($this->user->name ?? 'N/A'));
                $sheet->setCellValue('A3', 'Email: ' . ($this->user->email ?? 'N/A'));
                $sheet->setCellValue('A4', 'Phone: ' . ($this->user->phone ?? 'N/A'));

                // Header Styling
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
                $sheet->getStyle('A2:A4')->getFont()->setBold(true);

                // Bold the Summary Labels at the bottom
                $highestRow = $sheet->getHighestRow();
                $sheet->getStyle('A' . ($highestRow - 5) . ':A' . $highestRow)->getFont()->setBold(true);
            },
        ];
    }
}