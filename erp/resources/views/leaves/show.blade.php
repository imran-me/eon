@extends('layout.app')
@section('meta-information')
    <title>Leave Application</title>
@endsection

@section('css')
<style>
    .letter-toolbar {
        display: flex; align-items: center; justify-content: space-between;
        flex-wrap: wrap; gap: 12px; margin-bottom: 24px;
        font-family: 'Segoe UI', sans-serif;
    }
    .letter-toolbar h1 { margin: 0; font-size: 1.25rem; font-weight: 700; color: #1e293b; }
    .letter-toolbar p  { margin: 3px 0 0; font-size: 0.83rem; color: #64748b; }
    .toolbar-actions   { display: flex; gap: 10px; flex-wrap: wrap; }

    .btn-toolbar {
        display: inline-flex; align-items: center; gap: 7px;
        padding: 9px 18px; border-radius: 6px; font-weight: 600;
        font-size: 0.875rem; cursor: pointer; border: none;
        text-decoration: none; transition: all .2s;
    }
    .btn-back  { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
    .btn-back:hover  { background: #e2e8f0; color: #1e293b; text-decoration: none; }
    .btn-print { background: #2563eb; color: #fff; }
    .btn-print:hover { background: #1d4ed8; }
    .btn-pdf   { background: #059669; color: #fff; }
    .btn-pdf:hover   { background: #047857; }

    .status-row { display: flex; align-items: center; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
    .s-badge { display: inline-flex; align-items: center; gap: 6px; padding: 5px 14px; border-radius: 20px; font-size: 0.8rem; font-weight: 700; }
    .s-pending  { background: #fef3c7; color: #92400e; }
    .s-approved { background: #d1fae5; color: #065f46; }
    .s-rejected { background: #fee2e2; color: #991b1b; }

    .a4-wrapper { display: flex; justify-content: center; margin-bottom: 40px; }
    .a4-paper {
        width: 210mm; min-height: 297mm;
        background: #fff;
        box-shadow: 0 4px 30px rgba(0,0,0,.15);
        padding: 20mm 22mm 20mm 25mm;
        box-sizing: border-box;
        font-family: 'Times New Roman', Times, serif;
        font-size: 12pt; line-height: 1.7; color: #111;
        position: relative;
    }

    .lh-top {
        display: flex; justify-content: space-between; align-items: flex-start;
        padding-bottom: 10px; border-bottom: 2.5px solid #1e3a8a; margin-bottom: 18px;
    }
    .lh-company-name { font-size: 18pt; font-weight: 700; color: #1e3a8a; line-height: 1.2; font-family: 'Segoe UI', sans-serif; }
    .lh-company-sub  { font-size: 9pt; color: #64748b; font-family: 'Segoe UI', sans-serif; margin-top: 3px; }
    .lh-ref { text-align: right; font-size: 9pt; color: #64748b; font-family: 'Segoe UI', sans-serif; line-height: 1.5; }
    .lh-ref strong { color: #1e293b; font-size: 10pt; }

    .letter-date    { margin-bottom: 18px; }
    .letter-to      { margin-bottom: 18px; line-height: 1.6; }
    .letter-salute  { margin-bottom: 14px; font-weight: 700; }
    .letter-subject { margin-bottom: 18px; font-weight: 700; text-decoration: underline; font-size: 12.5pt; }
    .letter-body p  { margin: 0 0 14px; text-align: justify; }
    .letter-closing { margin-top: 28px; }
    .letter-closing p { margin: 0 0 6px; }

    .sig-grid { display: flex; justify-content: space-between; gap: 40px; margin-top: 44px; }
    .sig-block { min-width: 190px; }
    .sig-line   { width: 200px; border-top: 1px solid #111; margin-bottom: 6px; }
    .sig-block .sig-name   { font-weight: 700; font-size: 12pt; }
    .sig-block .sig-detail { font-size: 10.5pt; color: #374151; line-height: 1.5; }

    .stamp-area { position: absolute; bottom: 22mm; right: 22mm; text-align: center; }
    .stamp-box  {
        width: 130px; height: 80px;
        border: 1.5px dashed #94a3b8; border-radius: 6px;
        display: flex; align-items: center; justify-content: center;
        font-size: 9pt; color: #94a3b8; font-family: 'Segoe UI', sans-serif;
    }

    @@media print {
        body * { visibility: hidden; }
        .a4-wrapper, .a4-wrapper * { visibility: visible; }
        .a4-wrapper { position: absolute; top: 0; left: 0; width: 100%; margin: 0; }
        .a4-paper   { box-shadow: none; width: 100%; padding: 18mm 20mm 18mm 22mm; }
        .letter-toolbar, .status-row { display: none !important; }
        @@page { size: A4; margin: 0; }
    }
</style>
@endsection

@section('main-content')

@php
    $employee    = $leave->user;
    $profile     = optional($employee)->profile;
    $company     = $leave->company ?: optional($employee)->company;
    $designation = optional(optional($profile)->designation)->name ?? 'Employee';
    $department  = optional(optional($profile)->department)->name;
    $companyName = optional($company)->name ?? config('app.name');
    $companyAddr = optional($company)->address ?? '';
    $companyEmail= optional($company)->email ?? '';
    $companyPhone= optional($company)->phone ?? '';
    $leaveType   = optional($leave->leave_type)->name ?? 'Leave';
    $startDate   = \Carbon\Carbon::parse($leave->start_date);
    $endDate     = \Carbon\Carbon::parse($leave->end_date);
    $totalDays   = $startDate->diffInDays($endDate) + 1;
    $leaveDateText = $startDate->isSameDay($endDate)
        ? $startDate->format('F d, Y')
        : $startDate->format('F d, Y') . ' to ' . $endDate->format('F d, Y');
@endphp

<div class="letter-toolbar">
    <div>
        <h1><i class="fas fa-file-alt mr-2" style="color:#2563eb"></i>Leave Application</h1>
        <p>{{ optional($employee)->name }} &mdash; {{ $leaveDateText }}</p>
    </div>
    <div class="toolbar-actions">
        <a href="{{ route('role.leaves.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
            class="btn-toolbar btn-back">
            <i class="fas fa-arrow-left"></i> Back
        </a>
        <button onclick="window.print()" class="btn-toolbar btn-print">
            <i class="fas fa-print"></i> Print
        </button>
        <button onclick="printAsPdf()" class="btn-toolbar btn-pdf">
            <i class="fas fa-file-pdf"></i> Save as PDF
        </button>
    </div>
</div>

<div class="status-row">
    <span>Status:</span>
    @if($leave->status === 'approved')
        <span class="s-badge s-approved"><i class="fas fa-check-circle"></i> Approved</span>
    @elseif($leave->status === 'rejected')
        <span class="s-badge s-rejected"><i class="fas fa-times-circle"></i> Rejected</span>
    @else
        <span class="s-badge s-pending"><i class="fas fa-clock"></i> Pending</span>
    @endif
</div>

<div class="a4-wrapper">
<div class="a4-paper">

    <div class="lh-top">
        <div>
            <div class="lh-company-name">{{ $companyName }}</div>
            @if($companyAddr)
                <div class="lh-company-sub">{{ $companyAddr }}</div>
            @endif
            @if($companyEmail)
                <div class="lh-company-sub">
                    {{ $companyEmail }}
                    @if($companyPhone)
                        &nbsp;|&nbsp; {{ $companyPhone }}
                    @endif
                </div>
            @endif
        </div>
        <div class="lh-ref">
            <strong>LEAVE APPLICATION</strong><br>
            Ref No: LEV-{{ str_pad($leave->id, 4, '0', STR_PAD_LEFT) }}<br>
            Date: {{ $startDate->format('d F Y') }}<br>
            @if($leave->status === 'approved')
                <span style="color:#059669;font-weight:700;">&#10003; Approved</span>
            @endif
        </div>
    </div>

    <div class="letter-date">
        {{ now()->format('F d, Y') }}
    </div>

    <div class="letter-to">
        <strong>HR Manager / Authorized Signatory</strong><br>
        {{ $companyName }}<br>
        @if($companyAddr)
            {{ $companyAddr }}<br>
        @endif
    </div>

    <div class="letter-salute">Dear Sir/Madam,</div>

    <div class="letter-subject">
        Subject: Application for {{ $leaveType }}
    </div>

    <div class="letter-body">
        <p>
            I, <strong>{{ optional($employee)->name }}</strong>, working as
            <strong>{{ $designation }}</strong>
            @if($department)
                in the <strong>{{ $department }}</strong> department
            @endif
            at <strong>{{ $companyName }}</strong>, would like to request
            <strong>{{ $leaveType }}</strong> for
            <strong>{{ $leaveDateText }}</strong>@if($leave->leave_time) starting from <strong>{{ \Carbon\Carbon::parse($leave->leave_time)->format('h:i A') }}</strong>@endif.
        </p>

        <p>
            The reason for my leave request is as follows:
            <strong>{{ $leave->reason ?: 'Personal reasons.' }}</strong>
        </p>

        <p>
            I kindly request you to approve this leave application for the mentioned period.
            I will ensure that my responsibilities are managed properly before the leave starts
            and will coordinate any necessary handover with the concerned team members.
        </p>

        <p>
            I would be grateful for your consideration and approval.
        </p>
    </div>

    <div class="letter-closing">
        <p>Yours sincerely,</p>
    </div>

    <div class="sig-grid">
        <div class="sig-block">
            <div class="sig-line"></div>
            <div class="sig-name">{{ optional($employee)->name }}</div>
            <div class="sig-detail">
                {{ $designation }}
                @if($department)
                    , {{ $department }}
                @endif
                <br>
                @if(optional($employee)->phone)
                    {{ $employee->phone }}<br>
                @endif
                {{ optional($employee)->email }}
            </div>
        </div>
        <div class="sig-block">
            <div class="sig-line"></div>
            <div class="sig-name">HR / Manager</div>
            <div class="sig-detail">Approval Signature</div>
        </div>
    </div>

    <div class="stamp-area">
        <div class="stamp-box">HR Stamp &amp;<br>Signature</div>
        <div style="font-size:8.5pt;color:#94a3b8;margin-top:5px;font-family:'Segoe UI',sans-serif;">Authorized by HR</div>
    </div>

</div>
</div>

@endsection

@section('js')
<script>
    function printAsPdf() {
        if (confirm("In the print dialog, set Destination to 'Save as PDF' and Paper size to A4.\n\nProceed?")) {
            window.print();
        }
    }
</script>
@endsection
