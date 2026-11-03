@extends('layout.app')
@section('title', 'Contract File Sales History')
@section('main-content')
@php($role=request()->route('role'))
@include('layout.contract-file-tabs')
<div class="space-y-4">
 @if($errors->any())<div class="rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif
 @if(session('success'))<div class="rounded border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">{{ session('success') }}</div>@endif
 <header class="rounded-xl border bg-white p-5 shadow-sm"><h1 class="text-lg font-bold">Sales History</h1><p class="mt-1 text-xs text-slate-500">Previous contract-file invoices and outstanding payments. New files are billed from the Applications Board.</p></header>
 <form method="GET" class="flex flex-wrap gap-2 rounded-xl border bg-white p-3"><input name="search" value="{{ request('search') }}" placeholder="Search invoice, client or file" class="flex-1 rounded-lg border p-2 text-sm"><select name="payment_status" class="rounded-lg border p-2 text-sm"><option value="">All statuses</option>@foreach(['paid','partial','due'] as $status)<option value="{{ $status }}" @selected(request('payment_status')===$status)>{{ ucfirst($status) }}</option>@endforeach</select><button class="rounded-lg bg-blue-600 px-4 text-xs text-white">Filter</button></form>
 <div class="overflow-x-auto rounded-xl border bg-white"><table class="min-w-full text-xs"><thead class="bg-slate-50"><tr>@foreach(['Invoice','Client','Files','Date','Total','Paid','Due','Actions'] as $label)<th class="p-3 text-left">{{ $label }}</th>@endforeach</tr></thead><tbody>
 @forelse($datas as $sale)
 <tr class="border-t"><td class="p-3">{{ $sale->invoice_number }}</td><td class="p-3">{{ $sale->client?->name }}</td><td class="p-3">@foreach($sale->items as $item)<div>{{ $item->file?->file_number }} · {{ $item->file?->applicant_name }}</div>@endforeach</td><td class="p-3">{{ $sale->sale_date?->format('d M Y') }}</td><td class="p-3">{{ number_format($sale->total_amount,2) }}</td><td class="p-3">{{ number_format($sale->paid_amount,2) }}</td><td class="p-3">{{ number_format($sale->due_amount,2) }}</td><td class="p-3"><a class="text-blue-600" target="_blank" href="{{ route('role.contract-file-sales.voucher',['role'=>$role,'contractFileSale'=>$sale]) }}">View / Print invoice</a>
 @if($sale->due_amount>0) @can('edit contract file sale')
 <details class="mt-2"><summary class="cursor-pointer text-emerald-700">Record payment</summary><form method="POST" action="{{ route('role.contract-file-sales.payment',['role'=>$role,'contractFileSale'=>$sale]) }}" class="mt-2 space-y-2">@csrf @method('PATCH')<input name="payment_amount" type="number" min="0.01" max="{{ $sale->due_amount }}" step="0.01" required placeholder="Amount" class="w-full rounded border p-2"><select name="bank_id" required class="w-full rounded border p-2"><option value="">Select bank</option>@include('partials.bank-options',['banks'=>$banks,'selected'=>null])</select><button class="rounded bg-emerald-600 p-2 text-white">Record payment</button></form></details>
 @endcan @endif
 </td></tr>
 @empty<tr><td colspan="8" class="p-8 text-center text-slate-500">No previous sales invoices.</td></tr>@endforelse
 </tbody></table></div>{{ $datas->links() }}
</div>
@endsection
