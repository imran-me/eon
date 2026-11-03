@extends('layout.app')
@section('title', 'Review Custom Invoice Import')
@section('main-content')
@include('layout.party-tabs')
@php
    $editable = $batch->status === 'draft';
    $documents = collect($batch->documents ?? []);
    $serviceLines = $documents->sum(fn ($document) => count($document['items'] ?? []));
    $grandTotal = $documents->sum('total');
@endphp
<style>
.iir-shell{width:calc(100% - 24px);max-width:1500px;margin:0 auto 32px}.iir-alert{display:flex;align-items:flex-start;gap:9px;padding:13px 15px;border-radius:11px;margin-bottom:16px;font-size:12px}.iir-alert-success{background:#ecfdf5;border:1px solid #a7f3d0;color:#047857}.iir-alert-error{background:#fff1f2;border:1px solid #fecdd3;color:#9f1239}.iir-hero{overflow:hidden;border:1px solid #d8e2ef;border-radius:17px;background:#fff;box-shadow:0 18px 42px -32px #0f172a;margin-bottom:20px}.iir-hero-main{display:flex;align-items:center;justify-content:space-between;gap:20px;padding:24px 28px;color:#fff;background:linear-gradient(120deg,#075f9d 0%,#2466d9 55%,#4555db 100%)}.iir-eyebrow{font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:.12em;opacity:.74;margin-bottom:5px}.iir-hero h1{font-size:23px;line-height:1.2;font-weight:900;letter-spacing:-.02em;margin:0}.iir-file{display:flex;align-items:center;gap:7px;font-size:11px;margin-top:8px;opacity:.9}.iir-status{display:inline-flex;align-items:center;gap:7px;padding:8px 12px;border-radius:999px;background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.24);font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:.08em;white-space:nowrap}.iir-status-dot{width:7px;height:7px;border-radius:50%;background:#fbbf24}.iir-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));border-bottom:1px solid #e5ebf3}.iir-stat{padding:16px 20px;border-right:1px solid #e5ebf3}.iir-stat:last-child{border-right:0}.iir-stat-label{display:block;font-size:9px;font-weight:900;letter-spacing:.08em;text-transform:uppercase;color:#8491a5;margin-bottom:4px}.iir-stat-value{font-size:14px;font-weight:900;color:#172033}.iir-safety{display:flex;align-items:center;gap:9px;padding:13px 20px;background:#f8fafc;color:#536174;font-size:11px}.iir-safety i{color:#2563eb}.iir-card{background:#fff;border:1px solid #dce5f0;border-radius:16px;margin-bottom:18px;overflow:hidden;box-shadow:0 12px 32px -30px #0f172a}.iir-card-head{display:flex;align-items:center;justify-content:space-between;gap:20px;padding:17px 20px;background:linear-gradient(90deg,#f8fbff,#fff);border-bottom:1px solid #e8edf4}.iir-party{display:flex;align-items:center;gap:12px;min-width:0}.iir-number{width:38px;height:38px;display:grid;place-items:center;border-radius:11px;background:#eaf3ff;color:#1764c0;font-size:13px;font-weight:900;flex:0 0 auto}.iir-party-name{font-size:15px;font-weight:900;color:#172033;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.iir-party-meta{display:flex;align-items:center;gap:7px;margin-top:4px}.iir-type{display:inline-flex;padding:3px 7px;border-radius:999px;background:#eef2ff;color:#4338ca;font-size:9px;font-weight:900;text-transform:uppercase}.iir-party-code{font-size:10px;color:#7b8798}.iir-total{text-align:right}.iir-total-label{display:block;font-size:9px;font-weight:900;text-transform:uppercase;letter-spacing:.08em;color:#8491a5}.iir-total-value{font-size:19px;font-weight:900;color:#0f766e}.iir-card-body{padding:20px}.iir-fields{display:grid;grid-template-columns:1.1fr 1.15fr .8fr .8fr;gap:14px;margin-bottom:16px}.iir-field label{display:block;font-size:9px;font-weight:900;text-transform:uppercase;letter-spacing:.07em;color:#6d7b8e;margin-bottom:6px}.iir-field input{width:100%;height:42px;padding:9px 11px;border:1px solid #cad6e5;border-radius:9px;background:#fff;color:#1f2937;font-size:12px;transition:.15s}.iir-field input:focus,.iir-items input:focus{outline:none;border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.11)}.iir-field-wide{grid-column:1/-1}.iir-items-wrap{overflow:auto;border:1px solid #dce5ef;border-radius:11px}.iir-items{width:100%;min-width:980px;border-collapse:collapse;table-layout:fixed}.iir-items th{padding:10px 11px;background:#f3f7fc;border-bottom:1px solid #dce5ef;color:#637086;font-size:9px;font-weight:900;text-align:left;text-transform:uppercase;letter-spacing:.06em}.iir-items td{padding:8px;border-bottom:1px solid #edf1f6;background:#fff}.iir-items tbody tr:last-child td{border-bottom:0}.iir-items tbody tr:hover td{background:#fbfdff}.iir-items input{width:100%;height:38px;padding:8px 9px;border:1px solid #cad6e5;border-radius:7px;background:#fff;color:#1f2937;font-size:11px}.iir-items th:nth-child(1){width:15%}.iir-items th:nth-child(2){width:28%}.iir-items th:nth-child(3){width:21%}.iir-items th:nth-child(4){width:8%}.iir-items th:nth-child(5){width:14%}.iir-items th:nth-child(6){width:14%}.iir-actionbar{position:sticky;bottom:12px;z-index:20;display:flex;align-items:center;gap:10px;padding:13px 14px;margin-top:22px;border:1px solid #d9e2ee;border-radius:14px;background:rgba(255,255,255,.96);box-shadow:0 18px 45px -24px #0f172a;backdrop-filter:blur(10px)}.iir-actionbar form{margin:0}.iir-spacer{flex:1}.iir-btn{height:42px;display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:0 16px;border:0;border-radius:9px;font-size:11px;font-weight:900;text-decoration:none;cursor:pointer;white-space:nowrap;transition:.15s}.iir-btn:hover{transform:translateY(-1px)}.iir-btn-back{background:#f1f5f9;color:#475569}.iir-btn-save{background:#2563eb;color:#fff}.iir-btn-approve{background:#16a34a;color:#fff;box-shadow:0 8px 18px -13px #166534}.iir-btn-reject{background:#fff1f2;color:#be123c;border:1px solid #fecdd3}.iir-reject-form{display:flex;align-items:center;gap:8px}.iir-reason{width:220px;height:42px;padding:9px 11px;border:1px solid #cad6e5;border-radius:9px;font-size:11px}.iir-complete{padding:16px 18px;border:1px solid #d9e2ee;border-radius:12px;background:#fff;font-size:12px;color:#475569}@media(max-width:1000px){.iir-stats{grid-template-columns:repeat(2,1fr)}.iir-stat:nth-child(2){border-right:0}.iir-fields{grid-template-columns:repeat(2,1fr)}.iir-actionbar{position:static;flex-wrap:wrap}.iir-spacer{display:none}}@media(max-width:650px){.iir-shell{width:100%}.iir-hero-main{align-items:flex-start;padding:20px;flex-direction:column}.iir-hero h1{font-size:20px}.iir-stats{grid-template-columns:1fr 1fr}.iir-stat{padding:13px}.iir-fields{grid-template-columns:1fr}.iir-field-wide{grid-column:auto}.iir-card-head{align-items:flex-start}.iir-card-body{padding:14px}.iir-actionbar,.iir-reject-form{align-items:stretch;flex-direction:column}.iir-actionbar>* ,.iir-reason,.iir-btn{width:100%}}
</style>

<div class="iir-shell">
    @if(session('success'))
        <div class="iir-alert iir-alert-success"><i class="fas fa-circle-check"></i><span>{{ session('success') }}</span></div>
    @endif
    @if($errors->any())
        <div class="iir-alert iir-alert-error"><i class="fas fa-circle-exclamation"></i><div>@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div></div>
    @endif

    <section class="iir-hero">
        <div class="iir-hero-main">
            <div>
                <div class="iir-eyebrow">Invoice import review</div>
                <h1>Custom Invoice Draft #{{ $batch->id }}</h1>
                <div class="iir-file"><i class="fas fa-building"></i> {{ $batch->company?->name }} <span>•</span> <i class="fas fa-file-excel"></i> {{ $batch->original_filename }}</div>
            </div>
            <span class="iir-status"><span class="iir-status-dot"></span>{{ $batch->status }}</span>
        </div>
        <div class="iir-stats">
            <div class="iir-stat"><span class="iir-stat-label">Invoices</span><span class="iir-stat-value">{{ $documents->count() }}</span></div>
            <div class="iir-stat"><span class="iir-stat-label">Service lines</span><span class="iir-stat-value">{{ $serviceLines }}</span></div>
            <div class="iir-stat"><span class="iir-stat-label">Grand total</span><span class="iir-stat-value">৳{{ number_format($grandTotal, 2) }}</span></div>
            <div class="iir-stat"><span class="iir-stat-label">Uploaded by</span><span class="iir-stat-value">{{ $batch->uploader?->name ?? '—' }}</span></div>
        </div>
        <div class="iir-safety"><i class="fas fa-shield-halved"></i><span>Approve না করা পর্যন্ত কোনো invoice, party ledger বা journal তৈরি হবে না। Save করলে শুধু draft revalidate হবে।</span></div>
    </section>

    <form id="invoiceDraftForm" method="POST" action="{{ route('role.party-invoice-import.update',['role'=>$role,'partyInvoiceImportBatch'=>$batch]) }}">
        @csrf
        @method('PATCH')
        @foreach($batch->documents as $d => $doc)
            <article class="iir-card">
                <header class="iir-card-head">
                    <div class="iir-party">
                        <span class="iir-number">{{ $d + 1 }}</span>
                        <div>
                            <div class="iir-party-name">{{ $doc['party_name'] }}</div>
                            <div class="iir-party-meta"><span class="iir-type">{{ $doc['party_type'] }}</span><span class="iir-party-code">{{ $doc['party_code_or_name'] }}</span></div>
                        </div>
                    </div>
                    <div class="iir-total"><span class="iir-total-label">Invoice total</span><span class="iir-total-value">৳{{ number_format($doc['total'], 2) }}</span></div>
                </header>
                <div class="iir-card-body">
                    <div class="iir-fields">
                        @foreach(['document_group'=>'Group reference','party_code_or_name'=>'Party code / name','invoice_date'=>'Invoice date','due_date'=>'Due date'] as $field => $label)
                            <div class="iir-field"><label>{{ $label }}</label><input name="documents[{{ $d }}][{{ $field }}]" value="{{ $doc[$field] }}" @readonly(!$editable)></div>
                        @endforeach
                        <input type="hidden" name="documents[{{ $d }}][party_type]" value="{{ $doc['party_type'] }}">
                        <div class="iir-field iir-field-wide"><label>Notes</label><input name="documents[{{ $d }}][notes]" value="{{ $doc['notes'] }}" @readonly(!$editable)></div>
                    </div>
                    <div class="iir-items-wrap">
                        <table class="iir-items">
                            <thead><tr><th>Service</th><th>Description</th><th>Passenger</th><th>Qty</th><th>Unit price</th><th>Revenue code</th></tr></thead>
                            <tbody>
                                @foreach($doc['items'] as $i => $item)
                                    <tr>
                                        @foreach(['service_type','description','passenger_name','quantity','unit_price','revenue_account_code'] as $field)
                                            <td><input name="documents[{{ $d }}][items][{{ $i }}][{{ $field }}]" value="{{ $item[$field] }}" @readonly(!$editable)></td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </article>
        @endforeach
    </form>

    @if($editable)
        <div class="iir-actionbar">
            <a class="iir-btn iir-btn-back" href="{{ route('role.party-transaction-import.index',['role'=>$role,'company'=>$batch->company_id,'import'=>'invoices']) }}"><i class="fas fa-arrow-left"></i> Back to imports</a>
            <button class="iir-btn iir-btn-save" type="submit" form="invoiceDraftForm"><i class="fas fa-floppy-disk"></i> Save &amp; Revalidate</button>
            <span class="iir-spacer"></span>
            <form class="iir-reject-form" method="POST" action="{{ route('role.party-invoice-import.reject',['role'=>$role,'partyInvoiceImportBatch'=>$batch]) }}">@csrf<input class="iir-reason" name="rejection_note" required placeholder="Reason for rejection"><button class="iir-btn iir-btn-reject"><i class="fas fa-xmark"></i> Reject</button></form>
            <form method="POST" action="{{ route('role.party-invoice-import.approve',['role'=>$role,'partyInvoiceImportBatch'=>$batch]) }}" onsubmit="return confirm('Approve and post all invoices?')">@csrf<button class="iir-btn iir-btn-approve"><i class="fas fa-circle-check"></i> Approve &amp; Post {{ $documents->count() }} Invoices</button></form>
        </div>
    @else
        <div class="iir-complete"><strong>{{ ucfirst($batch->status) }}</strong> — this batch is read-only. @if($batch->status === 'approved') Created invoice IDs: {{ implode(', ', $batch->created_invoice_ids ?? []) }} @else {{ $batch->rejection_note }} @endif</div>
    @endif
</div>
@endsection
