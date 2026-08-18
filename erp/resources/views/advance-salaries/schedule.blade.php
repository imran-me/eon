@extends('layout.app')
@section('meta-information')
    <title>Payment Schedule – {{ $advance->user?->name }}</title>
@endsection
@section('css')
<style>
    .sched-header { background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%); color:#fff; border-radius:10px; padding:20px 24px; margin-bottom:20px; }
    .sched-header h2 { margin:0 0 4px; font-size:1.3rem; font-weight:700; }
    .sched-header .back-btn { color:#93c5fd; font-size:13px; text-decoration:none; }
    .sched-header .back-btn:hover { color:#fff; }

    .section-box { background:#fff; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,.07); overflow:hidden; margin-bottom:20px; }
    .section-head { background:#1e293b; color:#e2e8f0; padding:12px 18px; }
    .section-head h3 { margin:0; font-size:13px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; }

    .info-table { width:100%; border-collapse:collapse; }
    .info-table th { text-align:left; padding:10px 16px; background:#f8fafc; color:#64748b; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; width:180px; }
    .info-table td { padding:10px 16px; font-size:13px; color:#334155; border-bottom:1px solid #f1f5f9; }
    .info-table tr:last-child td { border-bottom:none; }

    .sched-table { width:100%; border-collapse:collapse; }
    .sched-table thead th { background:#f1f5f9; color:#475569; padding:10px 14px; text-align:left; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; border-bottom:2px solid #e2e8f0; }
    .sched-table tbody td { padding:11px 14px; font-size:13px; color:#334155; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
    .sched-table tbody tr:last-child td { border-bottom:none; }

    .s-badge { display:inline-flex; align-items:center; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; }
    .s-pending { background:#fef3c7; color:#92400e; }
    .s-paid    { background:#d1fae5; color:#065f46; }

    .badge-approved { background:#d1fae5; color:#065f46; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; }
    .badge-pending  { background:#fef3c7; color:#92400e; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; }
    .badge-rejected { background:#fee2e2; color:#991b1b; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; }
    .badge-paid     { background:#d1fae5; color:#065f46; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; }
    .badge-unpaid   { background:#fef3c7; color:#92400e; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; }

    .btn-pay { background:#1e40af; color:#fff; border:none; padding:6px 14px; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer; }
    .btn-pay:hover { background:#1e3a8a; }
    .empty-state { text-align:center; padding:40px; color:#94a3b8; }
    .empty-state i { font-size:32px; margin-bottom:10px; display:block; }
</style>
@endsection
@section('main-content')
@php use Illuminate\Support\Str; @endphp

<div style="padding:0 4px;">

    {{-- Header --}}
    <div class="sched-header">
        <div style="margin-bottom:10px;">
            <a href="{{ route('role.advance-salaries.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" class="back-btn">
                <i class="fas fa-arrow-left me-1"></i> Back to List
            </a>
        </div>
        <h2><i class="fas fa-calendar-alt me-2"></i>Payment Schedule</h2>
        <p style="margin:0; font-size:13px; opacity:.85;">{{ $advance->user?->name }}</p>
    </div>

    {{-- Advance Info --}}
    <div class="section-box">
        <div class="section-head"><h3><i class="fas fa-info-circle me-2"></i>Advance Info</h3></div>
        <table class="info-table">
            <tr>
                <th>Employee</th>
                <td>{{ $advance->user?->name }}</td>
                <th>Salary Month</th>
                <td>{{ date('F Y', strtotime($advance->month)) }}</td>
            </tr>
            <tr>
                <th>Amount</th>
                <td><strong>{{ number_format($advance->amount, 2) }}</strong></td>
                <th>Approval Status</th>
                <td><span class="badge-{{ strtolower($advance->status) }}">{{ $advance->status }}</span></td>
            </tr>
            <tr>
                <th>Schedule Date</th>
                <td>
                    @if($advance->schedule_date)
                        {{ \Carbon\Carbon::parse($advance->schedule_date)->format('d M Y') }}
                    @else
                        <span style="color:#94a3b8;">—</span>
                    @endif
                </td>
                <th>Payment Status</th>
                <td><span class="badge-{{ strtolower($advance->payment_status) }}">{{ $advance->payment_status }}</span></td>
            </tr>
        </table>
    </div>

    {{-- Schedule Table --}}
    <div class="section-box">
        <div class="section-head"><h3><i class="fas fa-list me-2"></i>Payment Schedule</h3></div>
        @if($advance->schedules->isEmpty())
            <div class="empty-state">
                <i class="fas fa-calendar-times"></i>
                <p>No schedule found.</p>
            </div>
        @else
        <div class="table-responsive">
            <table class="sched-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Schedule Date</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Paid On</th>
                        @can('edit advance salary')
                        <th>Action</th>
                        @endcan
                    </tr>
                </thead>
                <tbody>
                    @foreach($advance->schedules as $i => $s)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ \Carbon\Carbon::parse($s->scheduled_date)->format('d M Y') }}</td>
                        <td>{{ number_format($s->amount, 2) }}</td>
                        <td>
                            <span class="s-badge s-{{ $s->status }}">{{ ucfirst($s->status) }}</span>
                        </td>
                        <td>{{ $s->paid_date ? \Carbon\Carbon::parse($s->paid_date)->format('d M Y') : '—' }}</td>
                        @can('edit advance salary')
                        <td>
                            @if($s->status !== 'paid')
                            <button class="btn-pay pay-btn" data-id="{{ $s->id }}" title="Mark as Paid">
                                <i class="fas fa-check me-1"></i>Mark Paid
                            </button>
                            @else
                                <span style="color:#059669; font-size:12px;"><i class="fas fa-check-circle me-1"></i>Paid</span>
                            @endif
                        </td>
                        @endcan
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

</div>

{{-- Pay Modal --}}
<div id="payModal" class="modal fixed inset-0 flex items-center justify-center z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black opacity-50"></div>
    <div class="modal-container bg-white w-11/12 md:max-w-sm mx-auto rounded shadow-lg z-50">
        <div class="modal-content py-4 text-left px-6">
            <div class="flex justify-between items-center pb-3">
                <h3 class="text-lg font-semibold">Mark as Paid</h3>
                <button id="closePayModal"><i class="fas fa-times"></i></button>
            </div>
            <div style="margin-bottom:14px;">
                <label style="font-size:12px; font-weight:600; color:#374151; display:block; margin-bottom:4px;">Payment Date</label>
                <input type="date" id="pay_date" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="{{ date('Y-m-d') }}">
            </div>
            <div style="text-align:right;">
                <button type="button" id="closePayModalBtn" class="btn btn-secondary me-2">Cancel</button>
                <button type="button" id="paySubmit" class="btn btn-primary">Save</button>
            </div>
        </div>
    </div>
</div>

@endsection
@section('js')
<script src="https://code.jquery.com/jquery-3.7.1.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const PAY_BASE = "{{ rtrim(route('role.advance-salaries.schedule.pay', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'id' => 0]), '0') }}";

$.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

let currentId = null;

$(document).on('click', '.pay-btn', function () {
    currentId = $(this).data('id');
    $('#payModal').removeClass('hidden');
});

$('#closePayModal, #closePayModalBtn').click(() => $('#payModal').addClass('hidden'));

$('#paySubmit').click(function () {
    const paid_date = $('#pay_date').val();
    if (!paid_date) { Swal.fire('Missing', 'Please select a payment date.', 'warning'); return; }

    $.post(PAY_BASE + currentId + '/pay', { paid_date })
        .done(res => {
            if (res.success) {
                $('#payModal').addClass('hidden');
                Swal.fire({ icon: 'success', title: 'Done', text: res.message, timer: 1200, showConfirmButton: false })
                    .then(() => location.reload());
            } else {
                Swal.fire('Error', res.message, 'error');
            }
        }).fail(() => Swal.fire('Error', 'Request failed.', 'error'));
});
</script>
@endsection
