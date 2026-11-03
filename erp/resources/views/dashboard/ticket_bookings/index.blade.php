@extends('layout.app')
@section('meta-information')<title>Ticket Bookings</title>@endsection
@section('css')
<style>
 .tb-table td{font-size:12px;color:#374151}.tb-table tr:hover td{background:#f8fafc}
 span[aria-current="page"] span{background:#2563eb!important;color:#fff;border-color:#2563eb}
</style>
@endsection
@section('main-content')
@include('layout.ticketing-tabs')
@php $role=Str::slug(Auth::user()->getRoleNames()->first()); @endphp

<section class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
 @foreach([
  ['bg-blue-50','from-blue-400 to-blue-600','text-blue-900','text-blue-700','fa-bookmark',$stats['total'],'Total Bookings','All booking records'],
  ['bg-indigo-50','from-indigo-400 to-indigo-600','text-indigo-900','text-indigo-700','fa-clock',$stats['active'],'Active Holds','Booked and not expired'],
  ['bg-amber-50','from-amber-400 to-amber-600','text-amber-900','text-amber-700','fa-hourglass-half',$stats['expiring'],'Expiring Soon','Within the next 3 hours'],
  ['bg-emerald-50','from-emerald-400 to-emerald-600','text-emerald-900','text-emerald-700','fa-check-circle',$stats['confirmed'],'Confirmed','Successfully confirmed holds']
 ] as [$bg,$gradient,$numberColor,$labelColor,$icon,$number,$label,$sub])
 <div class="rounded-xl {{$bg}} p-4">
  <div class="h-12 w-12 rounded-xl bg-gradient-to-br {{$gradient}} flex items-center justify-center text-white text-xl mb-3 shadow-md"><i class="fas {{$icon}}"></i></div>
  <div class="text-3xl font-black {{$numberColor}} leading-none mb-1">{{$number}}</div>
  <div class="text-[10px] font-bold uppercase tracking-wide {{$labelColor}}">{{$label}}</div><div class="text-xs text-slate-500 mt-1">{{$sub}}</div>
 </div>
 @endforeach
</section>

@if(session('success'))<div class="bg-emerald-50 text-emerald-700 text-sm px-4 py-3 rounded-xl mb-4">{{session('success')}}</div>@endif

<header class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm mb-4">
 <span class="flex h-11 w-11 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600"><i class="fas fa-clock"></i></span>
 <div class="flex-1"><h1 class="text-lg font-bold text-slate-900">Ticket Bookings</h1><p class="text-xs text-slate-500">Manage time-limited ticket holds, custom expiry times and boss notifications</p></div>
 @can('create ticket purchase')<a href="{{route('role.ticket-bookings.create',compact('role'))}}" class="rounded-lg border border-indigo-100 bg-indigo-50 px-4 py-2 text-xs font-semibold text-indigo-700 hover:bg-indigo-100"><i class="fas fa-plus mr-1"></i> New Ticket Booking</a>@endcan
</header>

<div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
 <div class="flex flex-wrap gap-2 px-4 pt-4">
 @foreach([''=>'All bookings','booked'=>'Booked','confirmed'=>'Confirmed','expired'=>'Expired','cancelled'=>'Cancelled'] as $value=>$label)
  <a href="{{route('role.ticket-bookings.index',array_merge(['role'=>$role],request()->except(['status','page']),$value?['status'=>$value]:[]))}}" class="px-3 py-1.5 rounded-full border text-xs font-semibold {{request('status','')===$value?'bg-indigo-600 text-white border-indigo-600':'bg-white text-slate-600 border-slate-300 hover:bg-slate-50'}}">{{$label}}</a>
 @endforeach
 </div>
 <form method="GET">
  <x-ticketing-filter-bar search="search" placeholder="Search reference / PNR…" :reset="route('role.ticket-bookings.index',['role'=>$role])" :total="$bookings->total()">
   <select name="ticket_id" onchange="this.form.requestSubmit()" aria-label="Route"><option value="">All Routes</option>@foreach($tickets as $ticket)<option value="{{$ticket->id}}" @selected(request('ticket_id')==$ticket->id)>{{$ticket->title}}</option>@endforeach</select>
   <select name="status" onchange="this.form.requestSubmit()" aria-label="Status"><option value="">All Status</option>@foreach(['booked','confirmed','expired','cancelled'] as $status)<option value="{{$status}}" @selected(request('status')===$status)>{{ucfirst($status)}}</option>@endforeach</select>
  </x-ticketing-filter-bar>
 </form>
 <div class="overflow-x-auto" data-tfb-swap><table class="min-w-full divide-y divide-slate-200 tb-table" data-cf-sort>
  <thead class="bg-slate-50"><tr>
   @foreach(['SL','Passenger / PNR','Ticket Route','Vendor / Portal','Booked At','Expiry / Time Left','Notify To','Status','Actions'] as $heading)<th class="px-4 py-3 {{$heading==='Status'||$heading==='Actions'?'text-center':'text-left'}} text-[10px] font-bold uppercase tracking-wide text-slate-500">{{$heading}}</th>@endforeach
  </tr></thead>
  <tbody class="divide-y divide-slate-100">
  @forelse($bookings as $i=>$booking)
   @php $badge=['booked'=>'bg-blue-100 text-blue-700','confirmed'=>'bg-emerald-100 text-emerald-700','expired'=>'bg-red-100 text-red-700','cancelled'=>'bg-slate-100 text-slate-600'][$booking->status]??'bg-slate-100 text-slate-600'; @endphp
   <tr class="hover:bg-slate-50">
    <td class="px-4 py-3">{{$bookings->firstItem()+$i}}</td>
    <td class="px-4 py-3"><div class="text-sm font-semibold text-slate-800">{{$booking->passenger?->name??'—'}}</div><div class="text-xs text-slate-400 font-mono">{{$booking->pnr?:$booking->booking_reference}}</div></td>
    <td class="px-4 py-3"><div class="text-sm font-medium text-slate-800">{{$booking->ticket?->title??'—'}}</div><span class="rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-semibold text-indigo-700 mt-1 inline-block">{{$booking->ticket_class==='first'?'First Class':ucfirst($booking->ticket_class)}}</span></td>
    <td class="px-4 py-3 text-slate-600">{{$booking->vendor?->name??$booking->portal?->name??'—'}}</td>
    <td class="px-4 py-3 text-xs text-slate-500">{{$booking->booking_at?->format('d M Y')??'—'}}<div class="text-[10px] text-slate-400">{{$booking->booking_at?->format('h:i A')}}</div></td>
    <td class="px-4 py-3" data-expiry="{{$booking->expires_at?->toIso8601String()}}" data-active="{{$booking->status==='booked'?'1':'0'}}"><div class="text-xs font-semibold text-slate-700">{{$booking->expires_at?->format('d M Y, h:i A')??'— no deadline'}}</div><div class="countdown mt-1 text-[10px] font-bold text-amber-600"></div></td>
    <td class="px-4 py-3"><div class="font-medium text-slate-700">{{$booking->recipient?->name??'—'}}</div><div class="mt-1 flex gap-2 text-[10px] text-slate-400">@if($booking->notify_in_app)<i class="fas fa-bell" title="In-app"></i>@endif @if($booking->notify_email)<i class="fas fa-envelope" title="Email"></i>@endif @if($booking->notify_sms)<i class="fas fa-comment-alt" title="SMS"></i>@endif</div></td>
    <td class="px-4 py-3 text-center"><span class="rounded-full px-2.5 py-1 text-[10px] font-semibold {{$badge}}">{{ucfirst($booking->status)}}</span></td>
    <td class="px-4 py-3"><div class="flex justify-center gap-1"><a href="{{route('role.ticket-bookings.show',['role'=>$role,'ticket_booking'=>$booking])}}" class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 hover:bg-slate-50" title="View"><i class="fas fa-eye text-xs"></i></a>@can('edit ticket purchase')<a href="{{route('role.ticket-bookings.edit',['role'=>$role,'ticket_booking'=>$booking])}}" class="flex h-8 w-8 items-center justify-center rounded-lg border border-blue-200 bg-blue-50 text-blue-600 hover:bg-blue-100" title="Edit / extend"><i class="fas fa-pencil-alt text-xs"></i></a>@endcan</div></td>
   </tr>
  @empty<tr><td colspan="9" class="p-12 text-center"><i class="fas fa-clock mb-3 block text-4xl text-slate-300"></i><p class="text-sm font-semibold text-slate-500">No ticket bookings found</p><p class="mt-1 text-xs text-slate-400">Create a booking or adjust the filters.</p></td></tr>@endforelse
  </tbody>
 </table></div>
 <div class="p-4" data-tfb-swap>{{$bookings->appends(request()->all())->links()}}</div>
</div>
@endsection
@section('js')
<script>
document.querySelectorAll('[data-expiry]').forEach(function(el){if(el.dataset.active!=='1')return;const end=new Date(el.dataset.expiry),out=el.querySelector('.countdown');function tick(){let ms=end-new Date;if(ms<=0){out.textContent='EXPIRED';out.className='countdown mt-1 text-[10px] font-bold text-red-600';return}const d=Math.floor(ms/86400000);ms%=86400000;const h=Math.floor(ms/3600000),m=Math.floor(ms%3600000/60000);out.textContent='LEFT: '+(d?d+'D ':'')+h+'H '+m+'M';if(!d&&h<3)out.className='countdown mt-1 text-[10px] font-bold text-red-600'}tick();setInterval(tick,60000)})
</script>
@endsection
