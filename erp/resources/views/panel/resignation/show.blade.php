@extends('layout.app')
@section('meta-information')
    <title>Resignation Letter</title>
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

    .sig-block { margin-top: 40px; }
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

    .docs-card {
        max-width: 210mm; margin: 0 auto 30px; background: #fff;
        border-radius: 10px; padding: 18px 22px; box-shadow: 0 2px 14px rgba(0,0,0,.06);
        font-family: 'Segoe UI', sans-serif;
    }
    .docs-card h4 { margin: 0 0 12px; font-size: .95rem; font-weight: 700; color: #1e293b; display:flex; align-items:center; gap:8px; }
    .docs-list { display: flex; flex-direction: column; gap: 8px; }
    .docs-item {
        display: flex; align-items: center; justify-content: space-between;
        background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 7px; padding: 8px 12px;
    }
    .docs-item a { color: #2563eb; text-decoration: none; font-size: .85rem; }
    .docs-item a:hover { text-decoration: underline; }
    .docs-empty { color: #94a3b8; font-size: .85rem; }

    @@media print {
        body * { visibility: hidden; }
        .a4-wrapper, .a4-wrapper * { visibility: visible; }
        .a4-wrapper { position: absolute; top: 0; left: 0; width: 100%; margin: 0; }
        .a4-paper   { box-shadow: none; width: 100%; padding: 18mm 20mm 18mm 22mm; }
        .letter-toolbar, .status-row, .docs-card { display: none !important; }
        @@page { size: A4; margin: 0; }
    }
</style>
@endsection

@section('main-content')

@php
    $employee    = $resignation->user;
    $profile     = optional($employee->profile);
    $company     = optional($employee->company);
    $designation = optional($profile->designation)->name ?? 'Employee';
    $department  = optional($profile->department)->name;
    $companyName = $company->name ?? config('app.name');
    $companyAddr = $company->address ?? '';
    $companyEmail= $company->email ?? '';
    $companyPhone= $company->phone ?? '';
    $approver    = $resignation->approvedBy;
    $hrName      = $approver ? $approver->name : 'HR Manager';

    $reasonMap = [
        'personal'             => 'personal reasons',
        'health'               => 'health-related reasons',
        'better_opportunity'   => 'a better career opportunity',
        'company_policy_issue' => 'company policy concerns',
        'career_change'        => 'a career change',
        'relocation'           => 'relocation',
        'other'                => 'personal reasons',
    ];
    $reasonText = $reasonMap[$resignation->reason] ?? 'personal reasons';
@endphp

{{-- Toolbar --}}
<div class="letter-toolbar">
    <div>
        <h1><i class="fas fa-file-alt mr-2" style="color:#2563eb"></i>Resignation Letter</h1>
        <p>{{ $employee->name }} &mdash; {{ $resignation->resign_date->format('d M Y') }}</p>
    </div>
    <div class="toolbar-actions">
        <a href="{{ route('role.resignations.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
            class="btn-toolbar btn-back">
            <i class="fas fa-arrow-left"></i> Back
        </a>
        @canany(['edit resignation'])
            <a href="{{ route('role.resignations.edit', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'resignation' => $resignation->id]) }}"
                class="btn-toolbar" style="background:#6366f1;color:#fff;">
                <i class="fas fa-edit"></i> Edit
            </a>
        @endcanany
        <button onclick="window.print()" class="btn-toolbar btn-print">
            <i class="fas fa-print"></i> Print
        </button>
        <button onclick="printAsPdf()" class="btn-toolbar btn-pdf">
            <i class="fas fa-file-pdf"></i> Save as PDF
        </button>
    </div>
</div>

{{-- Status row --}}
<div class="status-row">
    <span>Status:</span>
    @if($resignation->status === 'approved')
        <span class="s-badge s-approved"><i class="fas fa-check-circle"></i> Approved</span>
    @elseif($resignation->status === 'rejected')
        <span class="s-badge s-rejected"><i class="fas fa-times-circle"></i> Rejected</span>
    @else
        <span class="s-badge s-pending"><i class="fas fa-clock"></i> Pending</span>
    @endif

    @if($resignation->status === 'pending')
        @canany(['edit resignation'])
            <form action="{{ route('role.resignations.approve', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'id' => $resignation->id]) }}" method="POST" style="display:inline">
                @csrf
                <button type="submit" class="btn-toolbar" style="background:#10b981;color:#fff;padding:5px 14px;font-size:.8rem;">
                    <i class="fas fa-check"></i> Approve
                </button>
            </form>
            <form action="{{ route('role.resignations.reject', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'id' => $resignation->id]) }}" method="POST" style="display:inline">
                @csrf
                <button type="submit" class="btn-toolbar" style="background:#f59e0b;color:#fff;padding:5px 14px;font-size:.8rem;">
                    <i class="fas fa-times"></i> Reject
                </button>
            </form>
        @endcanany
    @endif
</div>

{{-- A4 Paper --}}
<div class="a4-wrapper">
<div class="a4-paper">

    {{-- Letterhead --}}
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
            <strong>RESIGNATION LETTER</strong><br>
            Ref No: RES-{{ str_pad($resignation->id, 4, '0', STR_PAD_LEFT) }}<br>
            Date: {{ $resignation->resign_date->format('d F Y') }}<br>
            @if($resignation->status === 'approved')
                <span style="color:#059669;font-weight:700;">&#10003; Approved</span>
            @endif
        </div>
    </div>

    {{-- Date --}}
    <div class="letter-date">
        {{ $resignation->resign_date->format('F d, Y') }}
    </div>

    {{-- To --}}
    <div class="letter-to">
        <strong>{{ $hrName }}</strong><br>
        HR Manager / Authorized Signatory<br>
        {{ $companyName }}<br>
        @if($companyAddr)
            {{ $companyAddr }}<br>
        @endif
    </div>

    {{-- Salutation --}}
    <div class="letter-salute">Dear {{ $hrName }},</div>

    {{-- Subject --}}
    <div class="letter-subject">
        Subject: Formal Resignation &ndash; {{ $designation }}
        @if($department)
            , {{ $department }}
        @endif
    </div>

    {{-- Body --}}
    <div class="letter-body">
        <p>
            Please accept this letter as my formal notification of resignation from my position as
            <strong>{{ $designation }}</strong>
            @if($department)
                in the <strong>{{ $department }}</strong> department
            @endif
            at <strong>{{ $companyName }}</strong>.
            My last day of employment will be
            <strong>
                @if($resignation->last_working_day)
                    {{ $resignation->last_working_day->format('F d, Y') }}
                @else
                    [Last Working Day]
                @endif
            </strong>,
            in accordance with my required notice period of
            <strong>{{ $resignation->notice_period_days ?? 0 }} days</strong>.
        </p>

        @if($resignation->exit_note)
            <p>{{ $resignation->exit_note }}</p>
        @else
            <p>
                I have decided to resign due to {{ $reasonText }}. This decision was not made lightly,
                and I have given it careful and thoughtful consideration over the past weeks.
            </p>
            <p>
                I am deeply grateful for the opportunities I have had during my time with
                <strong>{{ $companyName }}</strong>. I sincerely thank you for your guidance, mentorship,
                and the collaborative working environment. The professional experience I have gained here
                has been invaluable to my career growth and personal development.
            </p>
        @endif

        <p>
            During my remaining time here, I am fully committed to ensuring a smooth and seamless
            transition of my responsibilities. I will complete all outstanding tasks, document ongoing
            projects thoroughly, and assist in any handover process as required. Please let me know
            if there are specific priority areas you would like me to focus on during this period.
        </p>

        <p>
            I wish <strong>{{ $companyName }}</strong> and the entire team continued success in all
            future endeavors. I hope we can stay in touch going forward.
        </p>
    </div>

    {{-- Closing --}}
    <div class="letter-closing">
        <p>Yours sincerely,</p>
    </div>

    {{-- Signature --}}
    <div class="sig-block">
        <div class="sig-line"></div>
        <div class="sig-name">{{ $employee->name }}</div>
        <div class="sig-detail">
            {{ $designation }}
            @if($department)
                , {{ $department }}
            @endif
            <br>
            @if($employee->phone)
                {{ $employee->phone }}<br>
            @endif
            {{ $employee->email }}
        </div>
    </div>

    {{-- HR Stamp --}}
    <div class="stamp-area">
        <div class="stamp-box">HR Stamp &amp;<br>Signature</div>
        <div style="font-size:8.5pt;color:#94a3b8;margin-top:5px;font-family:'Segoe UI',sans-serif;">Authorized by HR</div>
    </div>

</div>{{-- .a4-paper --}}
</div>{{-- .a4-wrapper --}}

{{-- Documents (not printed) --}}
<div class="docs-card">
    <h4><i class="fas fa-paperclip" style="color:#2563eb"></i> Attached Documents</h4>
    @if($resignation->attachments->isNotEmpty())
        <div class="docs-list">
            @foreach($resignation->attachments as $att)
                <div class="docs-item">
                    <a href="{{ asset($att->file_path) }}" target="_blank">
                        <i class="fas fa-file mr-1"></i>{{ $att->file_name }}
                    </a>
                </div>
            @endforeach
        </div>
    @else
        <p class="docs-empty">No documents attached for this resignation.</p>
    @endif
</div>

@endsection

@section('js')
<script>
    function printAsPdf() {
        if (confirm("In the print dialog, set Destination to 'Save as PDF' and Paper size to A4.\n\nProceed?")) {
            window.print();
        }
    }

    @if(request('print'))
        window.onload = function () {
            window.print();
        };
    @endif
</script>
@endsection
