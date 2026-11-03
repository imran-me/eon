@extends('layout.app')
@section('title', 'Review Transaction Import')
@section('main-content')
@include('layout.party-tabs')
@php $editable = $batch->status === 'draft'; @endphp
<style>
.pir-wrap{max-width:1280px;margin:0 auto}.pir-head,.pir-card{background:#fff;border:1px solid #dbe3ef;border-radius:14px;margin-bottom:16px;overflow:hidden}.pir-head-top{padding:20px 24px;background:linear-gradient(135deg,#123b82,#4f46e5);color:#fff;display:flex;justify-content:space-between;gap:16px}.pir-head h1{font-size:20px;font-weight:800;margin:0}.pir-meta{padding:14px 24px;display:grid;grid-template-columns:repeat(4,1fr);gap:12px;font-size:12px}.pir-meta strong,.pir-field label{display:block;color:#64748b;font-size:10px;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px}.pir-banner{padding:12px 16px;background:#fffbeb;border:1px solid #fde68a;border-radius:10px;color:#92400e;font-size:12px;margin-bottom:16px}.pir-row-head{padding:12px 18px;background:#eff6ff;display:flex;justify-content:space-between;align-items:center}.pir-row-head h2{font-size:14px;margin:0;color:#1e3a8a}.pir-grid{padding:18px;display:grid;grid-template-columns:repeat(4,1fr);gap:13px}.pir-field input,.pir-field select{width:100%;border:1px solid #cbd5e1;border-radius:8px;padding:9px 10px;font-size:12px;background:#fff}.pir-field.wide{grid-column:span 2}.pir-posting{grid-column:1/-1;padding:10px 12px;border-radius:8px;background:#f0fdf4;color:#166534;font-size:12px}.pir-actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin:18px 0}.pir-btn{border:0;border-radius:9px;padding:10px 15px;font-size:12px;font-weight:800;cursor:pointer;text-decoration:none}.pir-save{background:#2563eb;color:#fff}.pir-approve{background:#16a34a;color:#fff}.pir-reject{background:#dc2626;color:#fff}.pir-back{background:#e2e8f0;color:#334155}.pir-alert{padding:12px 15px;border-radius:9px;margin-bottom:14px;font-size:12px}.pir-success{background:#ecfdf5;color:#047857}.pir-error{background:#fff1f2;color:#9f1239}.pir-status{padding:5px 9px;border-radius:99px;background:#fef3c7;color:#92400e;font-size:10px;font-weight:900;text-transform:uppercase}.pir-status.approved{background:#dcfce7;color:#166534}.pir-status.rejected{background:#ffe4e6;color:#9f1239}@media(max-width:900px){.pir-grid,.pir-meta{grid-template-columns:repeat(2,1fr)}}@media(max-width:600px){.pir-grid,.pir-meta{grid-template-columns:1fr}.pir-field.wide{grid-column:auto}.pir-head-top{display:block}}
</style>
<div class="pir-wrap">
 @if(session('success'))<div class="pir-alert pir-success">{{ session('success') }}</div>@endif
 @if($errors->any())<div class="pir-alert pir-error"><strong>কাজটি সম্পন্ন হয়নি:</strong>@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
 <div class="pir-head">
  <div class="pir-head-top"><div><h1>Transaction Import Review #{{ $batch->id }}</h1><div style="font-size:12px;margin-top:5px;opacity:.85">Excel থেকে আনা data যাচাই ও edit করুন</div></div><span class="pir-status {{ $batch->status }}">{{ $batch->status }}</span></div>
  <div class="pir-meta"><div><strong>Company</strong>{{ $batch->company?->name }}</div><div><strong>Source file</strong>{{ $batch->original_filename }}</div><div><strong>Uploaded by</strong>{{ $batch->uploader?->name }}</div><div><strong>Uploaded at</strong>{{ $batch->created_at?->format('d/m/Y H:i') }}</div></div>
 </div>
 @if($editable)<div class="pir-banner"><i class="fas fa-shield-halved"></i> এটি draft। Approve না করা পর্যন্ত bank balance, journal, party ledger এবং invoice due-তে কোনো পরিবর্তন হবে না।</div>@endif
 <form method="POST" action="{{ route('role.party-transaction-import.update',['role'=>$role,'partyTransactionImportBatch'=>$batch]) }}">@csrf @method('PATCH')
  @foreach($batch->rows as $i => $row)
   @php $v = old("rows.$i", $row['input']); $s = $row['snapshot'] ?? []; @endphp
   <div class="pir-card">
    <div class="pir-row-head"><h2>Entry {{ $i + 1 }} — {{ $s['party_name'] ?? $v['party_code_or_name'] }}</h2><span style="font-size:11px;color:#475569">{{ $s['party_code'] ?? '' }}</span></div>
    <div class="pir-grid">
     <div class="pir-field"><label>Party type</label><select name="rows[{{ $i }}][party_type]" @disabled(!$editable)>@foreach(['agent','customer','vendor'] as $option)<option @selected($v['party_type']===$option)>{{ $option }}</option>@endforeach</select></div>
     <div class="pir-field"><label>Party code or exact name</label><input name="rows[{{ $i }}][party_code_or_name]" value="{{ $v['party_code_or_name'] }}" @readonly(!$editable) required></div>
     <div class="pir-field"><label>Transaction kind</label><select name="rows[{{ $i }}][transaction_kind]" @disabled(!$editable)><option value="invoice_payment" @selected($v['transaction_kind']==='invoice_payment')>Invoice payment</option><option value="advance" @selected($v['transaction_kind']==='advance')>Advance</option></select></div>
     <div class="pir-field"><label>Invoice / bill reference</label><input name="rows[{{ $i }}][invoice_reference]" value="{{ $v['invoice_reference'] }}" @readonly(!$editable)></div>
     <div class="pir-field"><label>Amount</label><input type="number" min="0.01" step="0.01" name="rows[{{ $i }}][amount]" value="{{ $v['amount'] }}" @readonly(!$editable) required></div>
     <div class="pir-field"><label>Posting bank</label><input name="rows[{{ $i }}][bank_name]" value="{{ $v['bank_name'] }}" @readonly(!$editable) required></div>
     <div class="pir-field"><label>Transaction date</label><input name="rows[{{ $i }}][transaction_date]" value="{{ $v['transaction_date'] }}" @readonly(!$editable) required></div>
     <div class="pir-field"><label>Time</label><input name="rows[{{ $i }}][transaction_time]" value="{{ $v['transaction_time'] }}" @readonly(!$editable) required></div>
     <div class="pir-field"><label>Payment method</label><select name="rows[{{ $i }}][payment_method]" @disabled(!$editable)>@foreach(['cash','bank','cheque'] as $option)<option @selected($v['payment_method']===$option)>{{ $option }}</option>@endforeach</select></div>
     <div class="pir-field"><label>Unique external reference</label><input name="rows[{{ $i }}][external_reference]" value="{{ $v['external_reference'] }}" @readonly(!$editable) required></div>
     <div class="pir-field"><label>Payer / receiver bank (metadata)</label><input name="rows[{{ $i }}][payer_bank]" value="{{ $v['payer_bank'] }}" @readonly(!$editable)></div>
     <div class="pir-field"><label>Account / cheque reference</label><input name="rows[{{ $i }}][payer_account_or_cheque]" value="{{ $v['payer_account_or_cheque'] }}" @readonly(!$editable)></div>
     <div class="pir-field wide"><label>Notes</label><input name="rows[{{ $i }}][notes]" value="{{ $v['notes'] }}" @readonly(!$editable)></div>
     <div class="pir-posting"><strong>Posting preview:</strong> {{ $s['posting'] ?? 'Save draft to refresh preview.' }} @if(isset($s['document_due'])) · Current document due: ৳{{ number_format($s['document_due'],2) }} @endif</div>
    </div>
   </div>
  @endforeach
  @if($editable)<label style="display:flex;gap:8px;font-size:12px"><input type="checkbox" name="notify_parties" value="1" @checked(old('notify_parties',$batch->notify_parties))> Approval-এর পরে party notification পাঠান</label><div class="pir-actions"><button class="pir-btn pir-save">Save &amp; Revalidate Draft</button><a class="pir-btn pir-back" href="{{ route('role.party-transaction-import.index',['role'=>$role,'company'=>$batch->company_id]) }}">Back</a></div>@endif
 </form>
 @if($editable)
  <div class="pir-actions" style="border-top:1px solid #e2e8f0;padding-top:18px"><form method="POST" action="{{ route('role.party-transaction-import.approve',['role'=>$role,'partyTransactionImportBatch'=>$batch]) }}" onsubmit="return confirm('Approve করে সব accounting entry post করবেন?')">@csrf<button class="pir-btn pir-approve"><i class="fas fa-check"></i> Approve &amp; Post All</button></form><form method="POST" action="{{ route('role.party-transaction-import.reject',['role'=>$role,'partyTransactionImportBatch'=>$batch]) }}" style="display:flex;gap:8px">@csrf<input name="rejection_note" placeholder="Rejection reason" required style="border:1px solid #cbd5e1;border-radius:8px;padding:8px"><button class="pir-btn pir-reject">Reject Draft</button></form></div>
 @else
  <div class="pir-card" style="padding:16px;font-size:12px">@if($batch->status==='approved')Approved by <strong>{{ $batch->approver?->name }}</strong> on {{ $batch->approved_at?->format('d/m/Y H:i') }}. Posted references: {{ implode(', ', $batch->posted_references ?? []) }}@else Rejected by <strong>{{ $batch->rejector?->name }}</strong>: {{ $batch->rejection_note }}@endif</div>
 @endif
</div>
@endsection
