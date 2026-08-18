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
use Carbon\Carbon;

class TaskReportExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithEvents
{
    protected $tasksByUser;
    protected $summary;

    public function __construct($tasksByUser, $summary)
    {
        $this->tasksByUser = $tasksByUser;
        $this->summary = $summary;
    }

    public function collection()
    {
        $data = collect();
        
        foreach ($this->tasksByUser as $userData) {
            // Add user header row
            $data->push((object)[
                'type' => 'user_header',
                'user_name' => $userData['user']->name,
                'task_count' => $userData['tasks']->count(),
            ]);
            
            // Add tasks for this user
            foreach ($userData['tasks'] as $task) {
                $data->push((object)[
                    'type' => 'task',
                    'task' => $task,
                ]);
            }
            
            // Add spacer
            $data->push((object)['type' => 'spacer']);
        }
        
        // Add summary
        $data->push((object)['type' => 'summary_header']);
        $data->push((object)['type' => 'summary', 'label' => 'Total Tasks', 'value' => $this->summary['total_tasks']]);
        $data->push((object)['type' => 'summary', 'label' => 'High Priority', 'value' => $this->summary['by_priority']['high']]);
        $data->push((object)['type' => 'summary', 'label' => 'Medium Priority', 'value' => $this->summary['by_priority']['medium']]);
        $data->push((object)['type' => 'summary', 'label' => 'Low Priority', 'value' => $this->summary['by_priority']['low']]);
        $data->push((object)['type' => 'summary', 'label' => 'Overdue Tasks', 'value' => $this->summary['overdue']]);
        
        return $data;
    }

    public function headings(): array
    {
        return ['#', 'Task Title', 'Project', 'Status', 'Priority', 'Start Date', 'Due Date'];
    }

    public function map($row): array
    {
        if ($row->type === 'user_header') {
            return [
                '👤 ' . $row->user_name,
                'Tasks: ' . $row->task_count,
                '',
                '',
                '',
                '',
                '',
            ];
        }
        
        if ($row->type === 'task') {
            $task = $row->task;
            $dueDate = $task->due_date ? Carbon::parse($task->due_date) : null;
            $startDate = $task->start_date ? Carbon::parse($task->start_date) : null;
            
            return [
                '',
                $task->title,
                $task->board->project->project_name ?? 'N/A',
                $task->column->name ?? 'N/A',
                $task->priority ? ucfirst($task->priority) : 'N/A',
                $startDate ? $startDate->format('M d, Y') : '-',
                $dueDate ? $dueDate->format('M d, Y') : '-',
            ];
        }
        
        if ($row->type === 'spacer') {
            return ['', '', '', '', '', '', ''];
        }
        
        if ($row->type === 'summary_header') {
            return ['📊 SUMMARY', '', '', '', '', '', ''];
        }
        
        if ($row->type === 'summary') {
            return [$row->label, $row->value, '', '', '', '', ''];
        }
        
        return ['', '', '', '', '', '', ''];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 12], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'E2E8F0']]],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet;
                
                // Insert header rows
                $sheet->insertNewRowBefore(1, 3);
                
                $sheet->mergeCells('A1:G1');
                $sheet->mergeCells('A2:G2');
                
                $sheet->setCellValue('A1', 'TASK REPORT');
                $sheet->setCellValue('A2', 'Generated on: ' . now()->format('F d, Y h:i A'));
                
                // Style header
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 16],
                    'alignment' => ['horizontal' => 'center'],
                ]);
                
                $sheet->getStyle('A2')->applyFromArray([
                    'font' => ['size' => 11],
                    'alignment' => ['horizontal' => 'center'],
                ]);
                
                // Auto-filter
                $sheet->getDelegate()->setAutoFilter('A4:G4');
                
                // Column widths
                $sheet->getColumnDimension('A')->setWidth(5);
                $sheet->getColumnDimension('B')->setWidth(35);
                $sheet->getColumnDimension('C')->setWidth(20);
                $sheet->getColumnDimension('D')->setWidth(15);
                $sheet->getColumnDimension('E')->setWidth(12);
                $sheet->getColumnDimension('F')->setWidth(15);
                $sheet->getColumnDimension('G')->setWidth(15);
            },
        ];
    }
}
