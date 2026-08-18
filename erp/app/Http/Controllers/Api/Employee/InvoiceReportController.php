<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Models\EmployeeSalary;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

class InvoiceReportController extends Controller
{
    /**
     * Employee invoice report list with filters and summary.
     */
    public function index(Request $request)
    {
        $userId = Auth::id();

        $query = EmployeeSalary::query()
            ->with([
                'user:id,name,email,phone,employee_id_no',
                'salary_template:id,name',
            ])
            ->where('user_id', $userId)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        if ($request->filled('year')) {
            $query->where('year', (int) $request->year);
        }

        if ($request->filled('month')) {
            $query->where('month', str_pad((string) $request->month, 2, '0', STR_PAD_LEFT));
        }

        if ($request->filled('from_date')) {
            $query->whereDate('salary_generation_date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('salary_generation_date', '<=', $request->to_date);
        }

        // Optional search by generated salary invoice number pattern
        if ($request->filled('invoice_no')) {
            $search = strtoupper(trim($request->invoice_no));
            $query->whereRaw(
                "UPPER(CONCAT('SAL-', user_id, '-', LPAD(month, 2, '0'), '-', year)) LIKE ?",
                ['%' . $search . '%']
            );
        }

        $summaryBase = clone $query;
        $summary = [
            'total_invoices' => (clone $summaryBase)->count(),
            'total_gross' => (float) (clone $summaryBase)->sum('gross_salary'),
            'total_net' => (float) (clone $summaryBase)->sum('net_salary'),
            'total_deductions' => (float) (clone $summaryBase)->sum('total_deductions'),
            'total_paid' => (float) (clone $summaryBase)->whereIn('status', ['paid', 'Paid'])->sum('net_salary'),
            'total_unpaid' => (float) (clone $summaryBase)->whereNotIn('status', ['paid', 'Paid'])->sum('net_salary'),
        ];

        $perPage = (int) ($request->per_page ?? 20);
        $invoices = $query->paginate($perPage);

        $invoices->getCollection()->transform(function ($salary) {
            return [
                'id' => $salary->id,
                'invoice_no' => 'SAL-' . $salary->user_id . '-' . str_pad((string) $salary->month, 2, '0', STR_PAD_LEFT) . '-' . $salary->year,
                'month' => str_pad((string) $salary->month, 2, '0', STR_PAD_LEFT),
                'year' => (int) $salary->year,
                'salary_generation_date' => $salary->salary_generation_date,
                'status' => $salary->status,
                'payment_method' => $salary->payment_method,
                'gross_salary' => (float) $salary->gross_salary,
                'total_deductions' => (float) $salary->total_deductions,
                'net_salary' => (float) $salary->net_salary,
                'bonus_label' => $salary->bonus_label,
                'bonus_amount' => (float) ($salary->bonus_amount ?? 0),
                'user' => $salary->user,
                'salary_template' => $salary->salary_template,
                'created_at' => Carbon::parse($salary->created_at)->format('Y-m-d H:i:s'),
                'updated_at' => Carbon::parse($salary->updated_at)->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Salary invoice report retrieved successfully.',
            'data' => [
                'summary' => $summary,
                'invoices' => $invoices,
            ],
        ]);
    }

    public function download(Request $request)
    {
        $id = Auth::id();
        $month = $request->input('month', date('n'));
        $year = $request->input('year', date('Y'));

        $data = EmployeeSalary::with('user.company', 'user.salary_template')
                ->where([
                    'month' => $month,
                    'year' => $year,
                    'user_id' => $id
                ])
                ->first();
        
        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Payslip data not found for the specified month and year.',
            ], 404);
        }

        $logoUrl = asset(
            $data->user->company->logo ?? 'images/site-setting/69401c60d0949.png'
        );

        $logoData = @file_get_contents($logoUrl);
        if ($logoData === false) {
            $fallbackLogoUrl = 'https://epal.com.bd/images/site-setting/69401c60d0949.png';
            $logoData = @file_get_contents($fallbackLogoUrl);
        }

        $logoBase64 = 'data:image/png;base64,' . base64_encode($logoData ?: '');
        $pdf = Pdf::loadView('employee-salaries.pdf', compact('data', 'logoBase64'));

        $slugName = Str::slug($data->user->name);
        $fileName = "{$slugName}_Payslip_{$data->month}_{$data->year}.pdf";
        $pdfPath = 'payslips/' . $fileName;
        
        $uploadPath = public_path('uploads/' . $pdfPath);
        
        if (!is_dir(dirname($uploadPath))) {
            mkdir(dirname($uploadPath), 0755, true);
        }
        
        $pdf->save($uploadPath);
        return response()->download($uploadPath, $fileName, ['Content-Type'=>'application/pdf']);

    }
}
