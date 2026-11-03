@extends('layout.app')
@section('title', 'Party Transaction Import')
@section('main-content')
@php
  $role = request()->route('role');
  $importMode = request('import', $errors->has('invoice_excel_file') ? 'invoices' : 'transactions');
@endphp
@include('layout.party-tabs')
<style>
.pti-wrap{width:calc(100% - 24px);max-width:1480px;margin:0 auto}.pti-switch{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin:2px 0 22px}.pti-switch a{min-height:72px;display:flex;align-items:center;gap:14px;padding:14px 18px;border:1px solid #d8e1ee;border-radius:14px;background:#fff;color:#334155;text-decoration:none;box-shadow:0 8px 24px -20px #0f172a;transition:.18s ease}.pti-switch a:hover{border-color:#93c5fd;transform:translateY(-1px)}.pti-switch a.active{background:linear-gradient(135deg,#0877e8,#3158dc);border-color:transparent;color:#fff;box-shadow:0 14px 28px -18px #1d4ed8}.pti-switch-icon{width:42px;height:42px;display:grid;place-items:center;border-radius:11px;background:#eff6ff;color:#2563eb;font-size:16px;flex:0 0 auto}.pti-switch a.active .pti-switch-icon{background:rgba(255,255,255,.17);color:#fff}.pti-switch-copy{display:flex;flex-direction:column;gap:3px}.pti-switch-title{font-size:14px;font-weight:900}.pti-switch-subtitle{font-size:11px;font-weight:500;opacity:.72}.pti-card{background:#fff;border:1px solid #dfe7f1;border-radius:16px;box-shadow:0 18px 42px -32px #0f172a;overflow:hidden;margin-bottom:22px}.pti-head{padding:22px 28px;background:linear-gradient(120deg,#086bd8,#3f51d9);color:#fff;display:flex;flex-direction:column;justify-content:center;min-height:96px}.pti-head h1{font-size:21px;font-weight:900;margin:0;letter-spacing:-.015em}.pti-head p{font-size:12px;opacity:.86;margin:6px 0 0}.pti-body{padding:28px}.pti-grid{display:grid;grid-template-columns:minmax(280px,.9fr) minmax(360px,1.1fr);gap:24px}.pti-field label{display:block;font-size:10px;font-weight:900;color:#526176;text-transform:uppercase;letter-spacing:.08em;margin-bottom:8px}.pti-field select,.pti-field input{width:100%;min-height:46px;border:1px solid #cbd7e6;border-radius:10px;padding:10px 13px;font-size:13px;background:#fff;transition:.15s}.pti-field select:focus,.pti-field input:focus{outline:none;border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.12)}.pti-field input[type=file]{padding:5px 6px}.pti-field input[type=file]::file-selector-button{height:34px;border:0;border-radius:7px;background:#eef5ff;color:#1d4ed8;font-size:11px;font-weight:800;padding:0 13px;margin-right:10px;cursor:pointer}.pti-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:22px}.pti-btn{min-height:40px;display:inline-flex;align-items:center;justify-content:center;gap:8px;border:0;border-radius:9px;padding:10px 16px;font-size:12px;font-weight:900;text-decoration:none;cursor:pointer;transition:.15s}.pti-btn:hover{transform:translateY(-1px)}.pti-primary{background:#0877e8;color:#fff;box-shadow:0 8px 18px -12px #1d4ed8}.pti-secondary{background:#eef4ff;color:#183c91}.pti-note{margin-top:22px;padding:15px 17px;border-radius:10px;background:#f8fafc;border:1px solid #dde6f0;font-size:12px;color:#526176;line-height:1.65}.pti-alert{margin-bottom:16px;padding:12px 14px;border-radius:10px;font-size:12px}.pti-error{background:#fff1f2;border:1px solid #fecdd3;color:#9f1239}.pti-success{background:#ecfdf5;border:1px solid #a7f3d0;color:#047857}.pti-table{width:100%;border-collapse:collapse;font-size:12px}.pti-table th{padding:12px 13px;background:#f8fafc;color:#526176;font-size:10px;text-transform:uppercase;letter-spacing:.06em}.pti-table td{padding:13px;border-bottom:1px solid #edf1f6;text-align:left}.pti-table tbody tr:hover{background:#fbfdff}.pti-badge{padding:4px 8px;border-radius:99px;font-weight:800;text-transform:uppercase;font-size:10px}.pti-badge.draft{background:#fef3c7;color:#92400e}.pti-badge.approved{background:#dcfce7;color:#166534}.pti-badge.rejected{background:#ffe4e6;color:#9f1239}@media(max-width:900px){.pti-wrap{width:100%}.pti-grid{grid-template-columns:1fr}}@media(max-width:700px){.pti-switch{grid-template-columns:1fr}.pti-head{padding:19px 20px}.pti-body{padding:20px}.pti-table{display:block;overflow:auto}}
</style>
<div class="pti-wrap">
  <nav class="pti-switch" aria-label="Import type">
    <a class="{{ $importMode === 'transactions' ? 'active' : '' }}" href="{{ route('role.party-transaction-import.index',['role'=>$role,'company'=>$companyId,'import'=>'transactions']) }}"><span class="pti-switch-icon"><i class="fas fa-money-bill-transfer"></i></span><span class="pti-switch-copy"><span class="pti-switch-title">Party Transaction Import</span><span class="pti-switch-subtitle">Receipts, payments, invoice allocation and advances</span></span></a>
    <a class="{{ $importMode === 'invoices' ? 'active' : '' }}" href="{{ route('role.party-transaction-import.index',['role'=>$role,'company'=>$companyId,'import'=>'invoices']) }}"><span class="pti-switch-icon"><i class="fas fa-file-invoice"></i></span><span class="pti-switch-copy"><span class="pti-switch-title">Custom Invoice Import</span><span class="pti-switch-subtitle">Create and approve grouped service invoices</span></span></a>
  </nav>
  @if(session('success'))<div class="pti-alert pti-success">{{ session('success') }}</div>@endif
  @if($errors->has('invoice_excel_file'))<div class="pti-alert pti-error"><strong>Invoice draft was not created.</strong>@foreach($errors->get('invoice_excel_file') as $error)<div>{{ $error }}</div>@endforeach</div>@endif
  @if($errors->has('excel_file'))<div class="pti-alert pti-error"><strong>Draft তৈরি হয়নি।</strong>@foreach($errors->get('excel_file') as $error)<div>{{ $error }}</div>@endforeach</div>@endif
  @if($importMode === 'transactions')
  <div class="pti-card">
    <div class="pti-head"><h1><i class="fas fa-file-excel"></i> Party Transaction Import</h1><p>Agent/customer receipt, vendor payment, invoice allocation ও advance-এর controlled import.</p></div>
    <div class="pti-body">
      <form method="POST" enctype="multipart/form-data" action="{{ route('role.party-transaction-import.store', ['role' => $role]) }}">@csrf
        <div class="pti-grid">
          <div class="pti-field"><label>Company</label><select name="company" required onchange="window.location.href='{{ route('role.party-transaction-import.index',['role'=>$role,'import'=>'transactions']) }}&company='+this.value">@foreach($companies as $company)<option value="{{ $company->id }}" @selected((int)old('company',$companyId)===(int)$company->id)>{{ $company->name }}</option>@endforeach</select></div>
          <div class="pti-field"><label>Excel file</label><input type="file" name="excel_file" accept=".xlsx,.xls,.csv" required></div>
        </div>
        <div class="pti-actions"><button class="pti-btn pti-primary" type="submit"><i class="fas fa-file-circle-check"></i> Upload &amp; Create Draft</button><a class="pti-btn pti-secondary" href="{{ route('role.party-transaction-import.template', ['role' => $role]) }}"><i class="fas fa-download"></i> Download Demo Excel</a></div>
        <label style="display:flex;align-items:flex-start;gap:8px;margin-top:16px;font-size:12px;color:#475569"><input type="checkbox" name="notify_parties" value="1" style="width:auto;margin-top:2px" @checked(old('notify_parties'))><span>Approve করার পরে party-কে email/SMS notification পাঠান। Historical বা test import-এর জন্য এটি বন্ধ রাখুন।</span></label>
      </form>
      <div class="pti-note"><strong>Workflow:</strong> upload শুধু validated draft তৈরি করে। Review screen থেকে edit করে Approve না করা পর্যন্ত bank, journal, party ledger বা invoice due পরিবর্তন হবে না। Approval-এর সময় পুরো batch একটি database transaction-এ post হবে; কোনো row fail করলে সব row rollback হবে।</div>
    </div>
  </div>
  @endif
  @if($importMode === 'invoices')
  <div class="pti-card">
    <div class="pti-head"><h1><i class="fas fa-file-invoice"></i> Custom Invoice Import</h1><p>একই document_group-এর একাধিক service row একটি editable invoice draft হবে।</p></div>
    <div class="pti-body">
      <form method="POST" enctype="multipart/form-data" action="{{ route('role.party-invoice-import.store', ['role'=>$role]) }}">@csrf
        <div class="pti-grid"><div class="pti-field"><label>Company</label><select name="company" required onchange="window.location.href='{{ route('role.party-transaction-import.index',['role'=>$role,'import'=>'invoices']) }}&company='+this.value">@foreach($companies as $company)<option value="{{ $company->id }}" @selected((int)$companyId===(int)$company->id)>{{ $company->name }}</option>@endforeach</select></div><div class="pti-field"><label>Invoice Excel file</label><input type="file" name="invoice_excel_file" accept=".xlsx,.xls,.csv" required></div></div>
        <div class="pti-actions"><button class="pti-btn pti-primary"><i class="fas fa-file-circle-plus"></i> Upload Invoice Draft</button><a class="pti-btn pti-secondary" href="{{ route('role.party-invoice-import.template',['role'=>$role]) }}"><i class="fas fa-download"></i> Download Invoice Excel</a></div>
      </form>
      <div class="pti-note"><strong>Approval posting:</strong> Dr Accounts Receivable / Cr selected Revenue Account(s), সঙ্গে party ledger invoice। Vendor bill এই form-এ গ্রহণ করা হয় না; সেটি আলাদা payable workflow হবে।</div>
    </div>
  </div>
  <div class="pti-card"><div class="pti-body"><h2 style="font-size:16px;font-weight:800;margin:0 0 14px">Recent Invoice Drafts</h2>
    <table class="pti-table"><thead><tr><th>ID</th><th>Company / File</th><th>Invoices</th><th>Status</th><th>Uploaded by</th><th>Date</th><th></th></tr></thead><tbody>
    @forelse($invoiceBatches as $invoiceBatch)<tr><td>#{{ $invoiceBatch->id }}</td><td><strong>{{ $invoiceBatch->company?->name }}</strong><br><span style="color:#64748b">{{ $invoiceBatch->original_filename }}</span></td><td>{{ count($invoiceBatch->documents ?? []) }}</td><td><span class="pti-badge {{ $invoiceBatch->status }}">{{ $invoiceBatch->status }}</span></td><td>{{ $invoiceBatch->uploader?->name }}</td><td>{{ $invoiceBatch->created_at?->format('d/m/Y H:i') }}</td><td><a class="pti-btn pti-secondary" href="{{ route('role.party-invoice-import.show',['role'=>$role,'partyInvoiceImportBatch'=>$invoiceBatch]) }}">Review</a></td></tr>
    @empty<tr><td colspan="7" style="text-align:center;color:#64748b">No invoice drafts yet.</td></tr>@endforelse
    </tbody></table>
  </div></div>
  @endif
  @if($importMode === 'transactions')
  <div class="pti-card"><div class="pti-body"><h2 style="font-size:16px;font-weight:800;margin:0 0 14px">Recent Import Drafts</h2>
    <table class="pti-table"><thead><tr><th>ID</th><th>Company / File</th><th>Rows</th><th>Status</th><th>Uploaded by</th><th>Date</th><th></th></tr></thead><tbody>
    @forelse($batches as $batch)<tr><td>#{{ $batch->id }}</td><td><strong>{{ $batch->company?->name }}</strong><br><span style="color:#64748b">{{ $batch->original_filename }}</span></td><td>{{ count($batch->rows ?? []) }}</td><td><span class="pti-badge {{ $batch->status }}">{{ $batch->status }}</span></td><td>{{ $batch->uploader?->name }}</td><td>{{ $batch->created_at?->format('d/m/Y H:i') }}</td><td><a class="pti-btn pti-secondary" href="{{ route('role.party-transaction-import.show',['role'=>$role,'partyTransactionImportBatch'=>$batch]) }}">Review</a></td></tr>
    @empty<tr><td colspan="7" style="text-align:center;color:#64748b">No import drafts yet.</td></tr>@endforelse
    </tbody></table>
  </div></div>
  @endif
</div>
@endsection
