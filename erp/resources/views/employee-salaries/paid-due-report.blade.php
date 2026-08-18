@extends('layout.app')
@section('meta-information')
    <title>Employee Salary Paid / Due Report</title>
@endsection
@section('main-content')
    @php
        $role = Str::slug(Auth::user()->getRoleNames()->first());
    @endphp
    <div class="p-4 md:p-6 space-y-6">

        {{-- ── Header ── --}}
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <h1 class="text-xl font-extrabold text-gray-900 leading-tight">
                    <i class="fas fa-file-invoice-dollar text-blue-500 mr-1.5"></i>Salary Paid / Due Report
                </h1>
                <p class="text-sm text-gray-500 mt-0.5">
                    Running totals per employee
                    @if($from || $to)
                        — {{ $from ? \Carbon\Carbon::createFromFormat('Y-m', $from)->format('M Y') : 'the beginning' }}
                        to {{ $to ? \Carbon\Carbon::createFromFormat('Y-m', $to)->format('M Y') : 'now' }}
                    @else
                        — all time
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <form method="GET" class="flex flex-wrap items-center gap-2">
                    @if($companyId)
                        <input type="hidden" name="company_id" value="{{ $companyId }}">
                    @endif
                    <label class="text-sm text-gray-500 font-medium hidden sm:inline">From:</label>
                    <input type="month" name="from" value="{{ $from }}"
                        class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <label class="text-sm text-gray-500 font-medium hidden sm:inline">To:</label>
                    <input type="month" name="to" value="{{ $to }}"
                        class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <button type="submit"
                        class="bg-gray-800 hover:bg-gray-900 text-white px-4 py-1.5 rounded-lg text-sm font-semibold transition-colors">
                        Filter
                    </button>
                    @if($from || $to)
                        <a href="{{ request()->fullUrlWithQuery(['from' => null, 'to' => null]) }}"
                            class="text-sm text-gray-500 hover:text-gray-700 underline">Clear</a>
                    @endif
                </form>
                <a href="{{ route('role.employee-salaries.paid-due-report.print', array_merge(['role' => $role], request()->only(['company_id', 'from', 'to']))) }}"
                    target="_blank"
                    class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors">
                    <i class="fas fa-print"></i> Print
                </a>
            </div>
        </div>

        {{-- ── Company Switcher ── --}}
        @if($companies->count() > 1)
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ request()->fullUrlWithQuery(['company_id' => null]) }}"
                    class="px-4 py-2 rounded-full text-sm font-semibold border transition-colors {{ !$companyId ? 'bg-blue-600 text-white border-blue-600 shadow-sm' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50' }}">
                    <i class="fas fa-layer-group mr-1 text-xs"></i>All Companies
                </a>
                @foreach ($companies as $c)
                    <a href="{{ request()->fullUrlWithQuery(['company_id' => $c->id]) }}"
                        class="px-4 py-2 rounded-full text-sm font-semibold border transition-colors {{ $companyId == $c->id ? 'bg-blue-600 text-white border-blue-600 shadow-sm' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50' }}">
                        {{ $c->short_name ?? $c->name }}
                    </a>
                @endforeach
            </div>
        @endif

        {{-- ── Grand Total Summary ── --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white rounded-2xl border border-gray-200 p-4 flex items-start gap-3 shadow-sm">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0" style="background:#dbeafe;color:#2563eb;">
                    <i class="fas fa-sack-dollar"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-medium">Total Gross</p>
                    <p class="text-xl font-extrabold text-gray-900 mt-0.5">৳ {{ number_format($grandTotals['total_gross'], 0) }}</p>
                </div>
            </div>
            <div class="bg-white rounded-2xl border border-gray-200 p-4 flex items-start gap-3 shadow-sm">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0" style="background:#dcfce7;color:#16a34a;">
                    <i class="fas fa-circle-check"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-medium">Total Paid</p>
                    <p class="text-xl font-extrabold text-green-600 mt-0.5">৳ {{ number_format($grandTotals['total_paid'], 0) }}</p>
                </div>
            </div>
            <div class="bg-white rounded-2xl border border-gray-200 p-4 flex items-start gap-3 shadow-sm">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0" style="background:#fee2e2;color:#dc2626;">
                    <i class="fas fa-hourglass-half"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-medium">Total Due</p>
                    <p class="text-xl font-extrabold text-red-600 mt-0.5">৳ {{ number_format($grandTotals['total_due'], 0) }}</p>
                </div>
            </div>
        </div>

        {{-- ── Per-Employee Table ── --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-4 py-2.5 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">#</th>
                            <th class="px-4 py-2.5 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Employee</th>
                            <th class="px-4 py-2.5 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Total Gross</th>
                            <th class="px-4 py-2.5 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Paid</th>
                            <th class="px-4 py-2.5 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Due</th>
                            <th class="px-4 py-2.5 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Records</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @forelse ($rows as $i => $row)
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-3 text-sm text-gray-500">{{ $i + 1 }}</td>
                                <td class="px-4 py-3 text-sm font-semibold text-gray-800">{{ $row->employee_name }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-right text-sm text-gray-700">৳ {{ number_format($row->total_gross, 2) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-semibold text-green-600">৳ {{ number_format($row->total_paid, 2) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-semibold {{ $row->total_due > 0 ? 'text-red-600' : 'text-gray-400' }}">৳ {{ number_format($row->total_due, 2) }}</td>
                                <td class="px-4 py-3 text-center text-xs text-gray-400">
                                    {{ $row->paid_count }} paid
                                    @if($row->partial_count > 0)
                                        · {{ $row->partial_count }} partial
                                    @endif
                                    · {{ $row->due_count }} due
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-12">
                                    <div class="flex flex-col items-center gap-2">
                                        <div class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center">
                                            <i class="fas fa-receipt fa-2x text-gray-300"></i>
                                        </div>
                                        <h4 class="text-gray-500 text-base font-semibold mt-1">No salary records found</h4>
                                        <p class="text-gray-400 text-sm">Try widening the date range or company filter.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if($rows->isNotEmpty())
                        <tfoot>
                            <tr class="bg-gray-50 font-bold">
                                <td class="px-4 py-3 text-sm text-gray-700" colspan="2">Grand Total</td>
                                <td class="px-4 py-3 whitespace-nowrap text-right text-sm text-gray-800">৳ {{ number_format($grandTotals['total_gross'], 2) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-right text-sm text-green-700">৳ {{ number_format($grandTotals['total_paid'], 2) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-right text-sm text-red-700">৳ {{ number_format($grandTotals['total_due'], 2) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>
@endsection
