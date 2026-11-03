@extends('layout.app')
@section('meta-information')<title>{{ $booking->exists ? 'Edit Ticket Booking' : 'New Ticket Booking' }}</title>@endsection
@section('css')
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<style>
 .bk-wrap{font-family:'DM Sans',sans-serif;padding:4px 0 20px}.bk-head{background:#fff;border:1px solid #e4e8f0;border-radius:14px;padding:16px 20px;display:flex;align-items:center;gap:14px;margin-bottom:14px;box-shadow:0 1px 4px rgba(30,50,100,.07)}
 .bk-logo{width:46px;height:46px;border-radius:12px;background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0}.bk-title{font-size:18px;font-weight:700;color:#1a2035}.bk-sub{font-size:12px;color:#5a6480;margin-top:1px}.bk-actions{margin-left:auto;display:flex;gap:8px}
 .bk-btn{padding:8px 14px;border-radius:8px;font-size:12px;font-weight:600;border:1px solid #e4e8f0;display:inline-flex;align-items:center;gap:6px;text-decoration:none;cursor:pointer}.bk-btn-back{background:#f0f2f8;color:#5a6480}.bk-btn-back:hover{border-color:#d0d6e8;color:#1a2035}.bk-btn-save{background:#16a34a;color:#fff;border-color:#16a34a;padding:9px 18px}.bk-btn-save:hover{background:#15803d}
 .bk-alert{padding:11px 14px;border-radius:9px;font-size:12.5px;margin-bottom:14px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
 .bk-section{background:#fff;border:1px solid #e4e8f0;border-radius:12px;margin-bottom:14px;overflow:hidden;box-shadow:0 1px 4px rgba(30,50,100,.07)}.bk-section-head{padding:13px 16px;background:#f8fafc;border-bottom:1px solid #e4e8f0;display:flex;align-items:center;gap:10px}.bk-dot{width:10px;height:10px;border-radius:50%}.bk-section-title{font-size:13.5px;font-weight:700;color:#1a2035}.bk-section-sub{font-size:11.5px;color:#5a6480}.bk-section-body{padding:16px}
 .bk-grid{display:grid;gap:12px;margin-bottom:10px}.bk-grid:last-child{margin-bottom:0}.bk-cols-4{grid-template-columns:repeat(4,1fr)}.bk-cols-5{grid-template-columns:repeat(5,1fr)}.bk-cols-3{grid-template-columns:repeat(3,1fr)}.bk-cols-2{grid-template-columns:repeat(2,1fr)}.bk-span-2{grid-column:span 2}.bk-full{grid-column:1/-1}
 .bk-label{font-size:11px;color:#5a6480;font-weight:600;margin-bottom:5px;letter-spacing:.2px;display:block}.bk-required{color:#dc2626}.bk-input,.bk-select,.bk-textarea{width:100%;padding:9px 12px;border:1px solid #e4e8f0!important;border-radius:8px!important;font-size:13px;background:#fff;color:#1a2035;outline:none;font-family:'DM Sans',sans-serif;min-height:38px}.bk-textarea{resize:vertical}.bk-input:focus,.bk-select:focus,.bk-textarea:focus{border-color:#2563eb!important;box-shadow:0 0 0 3px #eff6ff!important}.bk-expiry{border-color:#f59e0b!important;background:#fffbeb!important;font-weight:600;color:#92400e}.bk-help{display:block;font-size:10.5px;color:#9aa3bc;margin-top:4px;font-weight:400}
 .bk-wrap .select2-container,.bk-modal .select2-container{width:100%!important;min-width:0}.bk-wrap .select2-container--default .select2-selection--single,.bk-modal .select2-container--default .select2-selection--single{height:38px;border:1px solid #e4e8f0;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13px;display:flex;align-items:center}.bk-wrap .select2-container--default .select2-selection--single .select2-selection__rendered,.bk-modal .select2-container--default .select2-selection--single .select2-selection__rendered{line-height:36px;padding-left:12px;padding-right:30px;color:#1a2035}.bk-wrap .select2-container--default .select2-selection--single .select2-selection__arrow,.bk-modal .select2-container--default .select2-selection--single .select2-selection__arrow{height:36px}.bk-wrap .select2-container--default.select2-container--focus .select2-selection--single,.bk-wrap .select2-container--default.select2-container--open .select2-selection--single,.bk-modal .select2-container--default.select2-container--focus .select2-selection--single{border-color:#2563eb;box-shadow:0 0 0 3px #eff6ff}.select2-dropdown{border-color:#d0d6e8;font-family:'DM Sans',sans-serif;font-size:13px;z-index:10020}.select2-search--dropdown .select2-search__field{border:1px solid #d0d6e8!important;border-radius:6px;padding:7px 9px}.select2-results__option{padding:7px 10px}.select2-container--default .select2-results__option--highlighted.select2-results__option--selectable{background:#4f46e5}.bk-pick .select2-container{flex:1 1 auto}
 .bk-pick{display:flex;align-items:center;gap:6px}.bk-pick .bk-select{min-width:0}.bk-plus{flex:0 0 34px;width:34px;height:38px;border-radius:8px;border:1px solid #7c3aed;background:#ede9fe;color:#7c3aed;font-size:18px;font-weight:700;display:flex;align-items:center;justify-content:center;cursor:pointer}.bk-plus:hover{background:#7c3aed;color:#fff}
 .bk-expiry-panel{background:linear-gradient(135deg,#fffbeb,#fff7ed);border:1px solid #fde68a;border-radius:10px;padding:14px}.bk-expiry-icon{width:34px;height:34px;border-radius:8px;background:#f59e0b;color:#fff;display:flex;align-items:center;justify-content:center;float:left;margin-right:10px}.bk-expiry-copy{font-size:11px;color:#92400e;margin-bottom:12px;min-height:34px}.bk-expiry-copy strong{display:block;font-size:12.5px;color:#78350f}
 .bk-channel-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}.bk-channel{display:flex;align-items:center;gap:10px;padding:12px;border:1px solid #dbeafe;border-radius:9px;background:#f8fbff;cursor:pointer}.bk-channel:hover{border-color:#93c5fd;background:#eff6ff}.bk-channel input{width:16px;height:16px}.bk-channel-icon{width:32px;height:32px;border-radius:8px;background:#dbeafe;color:#2563eb;display:flex;align-items:center;justify-content:center}.bk-channel-name{font-size:12px;font-weight:700;color:#1e3a8a}.bk-channel-sub{font-size:10px;color:#64748b}.bk-reminder{margin-top:12px;padding:10px 12px;border-radius:8px;background:#eef2ff;color:#4338ca;font-size:11px;display:flex;align-items:center;gap:8px}
 .bk-footer{display:flex;align-items:center;justify-content:space-between;background:#fff;border:1px solid #e4e8f0;border-radius:12px;padding:12px 16px;box-shadow:0 1px 4px rgba(30,50,100,.07)}.bk-footer-note{font-size:11px;color:#64748b}.bk-footer-actions{display:flex;gap:8px}
 .bk-modal-wrap{position:fixed;inset:0;background:rgba(15,23,42,.48);z-index:9999;display:none;align-items:center;justify-content:center;padding:20px}.bk-modal-wrap.open{display:flex}.bk-modal{width:100%;max-width:560px;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 24px 70px rgba(15,23,42,.25)}.bk-modal-head{padding:15px 18px;background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;display:flex;align-items:center;gap:9px}.bk-modal-title{font-size:14px;font-weight:700}.bk-modal-close{margin-left:auto;border:0;background:rgba(255,255,255,.18);color:#fff;width:28px;height:28px;border-radius:6px;cursor:pointer}.bk-modal-body{padding:18px}.bk-modal-foot{padding:13px 18px;background:#f8fafc;border-top:1px solid #e4e8f0;display:flex;justify-content:flex-end;gap:8px}.bk-modal-error{display:none;background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;border-radius:8px;padding:9px 11px;font-size:12px;margin-bottom:12px}
 @media(max-width:1100px){.bk-cols-4,.bk-cols-5{grid-template-columns:repeat(2,1fr)}.bk-cols-3{grid-template-columns:repeat(2,1fr)}}@media(max-width:700px){.bk-head{align-items:flex-start}.bk-actions{margin-left:0}.bk-cols-4,.bk-cols-5,.bk-cols-3,.bk-cols-2,.bk-channel-grid{grid-template-columns:1fr}.bk-span-2{grid-column:auto}.bk-footer{align-items:flex-start;gap:10px;flex-direction:column}.bk-footer-actions{width:100%;justify-content:flex-end}}
</style>
@endsection
@section('main-content')
@include('layout.ticketing-tabs')
@php $role=Str::slug(Auth::user()->getRoleNames()->first()); @endphp
<div class="bk-wrap">
 <div class="bk-head">
  <div class="bk-logo"><i class="fas {{$booking->exists?'fa-pencil-alt':'fa-clock'}}"></i></div>
  <div><div class="bk-title">{{$booking->exists?'Edit Ticket Booking':'New Ticket Booking'}}</div><div class="bk-sub">{{$booking->exists?'Update the hold information or extend its custom expiry time.':'Create a time-limited ticket hold and notify the responsible person.'}}</div></div>
  <div class="bk-actions"><a class="bk-btn bk-btn-back" href="{{route('role.ticket-purchase.index',compact('role'))}}"><i class="fas fa-layer-group"></i> Ticket Orders</a></div>
 </div>
 @if($errors->any())<div class="bk-alert"><i class="fas fa-exclamation-circle mr-1"></i>{{$errors->first()}}</div>@endif
 <form method="POST" enctype="multipart/form-data" action="{{$booking->exists?route('role.ticket-bookings.update',['role'=>$role,'ticket_booking'=>$booking]):route('role.ticket-bookings.store',compact('role'))}}">
  @csrf @if($booking->exists) @method('PUT') @endif
  @if(request('company') || $booking->company_id)<input type="hidden" name="company" value="{{ request('company', $booking->company_id) }}">@endif

  <div class="bk-section">
   <div class="bk-section-head"><span class="bk-dot" style="background:#818cf8"></span><span class="bk-section-title">Passenger &amp; Ticket</span><span class="bk-section-sub">Core booking and itinerary information</span></div>
   <div class="bk-section-body">
    <div class="bk-grid bk-cols-4">
     <div><label class="bk-label">Passenger <span class="bk-required">*</span></label><div class="bk-pick"><select id="bookingPassenger" name="passport_holder_id" required class="bk-select"><option value="">— Select passenger —</option>@foreach($passengers as $item)<option value="{{$item->id}}" @selected(old('passport_holder_id',$booking->passport_holder_id)==$item->id)>{{$item->name}}</option>@endforeach</select><button type="button" class="bk-plus" onclick="openBookingModal('passenger')" title="Create new passport holder">+</button></div></div>
     <div><label class="bk-label">Ticket Route <span class="bk-required">*</span></label><div class="bk-pick"><select id="bookingTicket" name="ticket_id" required class="bk-select"><option value="">— Select route —</option>@foreach($tickets as $item)<option value="{{$item->id}}" data-price="{{$item->price}}" @selected(old('ticket_id',$booking->ticket_id)==$item->id)>{{$item->title}}</option>@endforeach</select><button type="button" class="bk-plus" onclick="openBookingModal('ticket')" title="Create new ticket route">+</button></div></div>
     <div><label class="bk-label">Trip Type <span class="bk-required">*</span></label><select name="trip_type" class="bk-select"><option value="one-way" @selected(old('trip_type',$booking->trip_type)==='one-way')>One-way</option><option value="two-way" @selected(old('trip_type',$booking->trip_type)==='two-way')>Round Trip</option><option value="multi-city" @selected(old('trip_type',$booking->trip_type)==='multi-city')>Multi-city</option></select></div>
     <div><label class="bk-label">Class <span class="bk-required">*</span></label><select name="ticket_class" class="bk-select">@foreach(['economy'=>'Economy','business'=>'Business','first'=>'First Class'] as $v=>$l)<option value="{{$v}}" @selected(old('ticket_class',$booking->ticket_class)===$v)>{{$l}}</option>@endforeach</select></div>
    </div>
    <div class="bk-grid bk-cols-5">
     <div><label class="bk-label">PNR / Booking Code</label><input name="pnr" value="{{old('pnr',$booking->pnr)}}" class="bk-input" placeholder="e.g. ABCD12"></div>
     <div><label class="bk-label">Name Ticket No.</label><input name="name_ticket_no" maxlength="100" value="{{old('name_ticket_no',$booking->name_ticket_no)}}" class="bk-input" placeholder="e.g. 997-2345678901"></div>
     <div><label class="bk-label">Vendor</label><select id="bookingVendor" name="vendor_id" class="bk-select"><option value="">— Select vendor —</option>@foreach($vendors as $item)<option value="{{$item->id}}" @selected(old('vendor_id',$booking->vendor_id)==$item->id)>{{$item->name}}</option>@endforeach</select></div>
     <div><label class="bk-label">Portal</label><select id="bookingPortal" name="portal_id" class="bk-select"><option value="">— Select portal —</option>@foreach($portals as $item)<option value="{{$item->id}}" @selected(old('portal_id',$booking->portal_id)==$item->id)>{{$item->name}}</option>@endforeach</select></div>
     <div><label class="bk-label">Attachment</label><input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png" class="bk-input" style="padding:7px 10px">@if($booking->attachment)<span class="bk-help"><i class="fas fa-paperclip"></i> Current attachment will remain unless replaced.</span>@endif</div>
    </div>
   </div>
  </div>

  <div class="bk-section">
   <div class="bk-section-head"><span class="bk-dot" style="background:#f59e0b"></span><span class="bk-section-title">Booking Timeline</span><span class="bk-section-sub">Set an exact custom expiry deadline</span></div>
   <div class="bk-section-body">
    <div class="bk-grid bk-cols-4">
     <div><label class="bk-label">Booking Date &amp; Time <span class="bk-required">*</span></label><input type="datetime-local" name="booking_at" required value="{{old('booking_at',optional($booking->booking_at)->format('Y-m-d\TH:i'))}}" class="bk-input"></div>
     <div><label class="bk-label">Departure Date &amp; Time</label><input type="datetime-local" name="departure_at" value="{{old('departure_at',optional($booking->departure_at)->format('Y-m-d\TH:i'))}}" class="bk-input"></div>
     <div><label class="bk-label">Return Date &amp; Time</label><input type="datetime-local" name="return_at" value="{{old('return_at',optional($booking->return_at)->format('Y-m-d\TH:i'))}}" class="bk-input"></div>
     <div class="bk-expiry-panel"><div class="bk-expiry-icon"><i class="fas fa-hourglass-half"></i></div><div class="bk-expiry-copy"><strong>Custom Expiry Deadline</strong>Defaults to a 6-hour hold; reminders use the exact selected time.</div><label class="bk-label" style="color:#92400e">Expiry Date &amp; Time <span class="bk-required">*</span></label><input type="datetime-local" name="expires_at" required value="{{old('expires_at',optional($booking->expires_at)->format('Y-m-d\TH:i'))}}" class="bk-input bk-expiry"><span class="bk-help">Must be later than the booking time and current time.</span></div>
    </div>
   </div>
  </div>

  <div class="bk-section">
   <div class="bk-section-head"><span class="bk-dot" style="background:#34d399"></span><span class="bk-section-title">Commercial &amp; Notification</span><span class="bk-section-sub">Quote, recipient and delivery channels</span></div>
   <div class="bk-section-body">
    <div class="bk-grid bk-cols-4">
     <div><label class="bk-label">Quoted Amount <span class="bk-required">*</span></label><input id="bookingQuote" type="number" min="0" step="0.01" name="quoted_amount" required value="{{old('quoted_amount',$booking->quoted_amount??0)}}" class="bk-input"></div>
     <div><label class="bk-label">Currency <span class="bk-required">*</span></label><select name="currency" class="bk-select">@foreach(['BDT','USD','EUR','SAR','AED'] as $c)<option @selected(old('currency',$booking->currency)===$c)>{{$c}}</option>@endforeach</select></div>
     <div class="bk-span-2"><label class="bk-label">Notify Boss / User <span class="bk-required">*</span></label><select name="notification_recipient_id" required class="bk-select">@foreach($recipients as $item)<option value="{{$item->id}}" @selected(old('notification_recipient_id',$defaultRecipientId)==$item->id)>{{$item->name}}{{$item->name==='Md Mohsin'?' (Boss)':''}} — {{$item->email?:$item->phone}}</option>@endforeach</select><span class="bk-help">Email and SMS are read from the selected user's profile.</span></div>
    </div>
    <div class="bk-grid bk-cols-2">
     <div><label class="bk-label">Notes</label><textarea name="notes" rows="4" class="bk-textarea" placeholder="Airline instructions, fare rules or follow-up notes…">{{old('notes',$booking->notes)}}</textarea></div>
     <div><label class="bk-label">Notification Channels</label><div class="bk-channel-grid">
      @foreach([['notify_in_app','fa-bell','In-app','Bell alert'],['notify_email','fa-envelope','Email','Detailed email'],['notify_sms','fa-comment-alt','SMS','Short alert']] as [$key,$icon,$name,$sub])
       <label class="bk-channel"><input type="hidden" name="{{$key}}" value="0"><input type="checkbox" name="{{$key}}" value="1" @checked(old($key,$booking->{$key}))><span class="bk-channel-icon"><i class="fas {{$icon}}"></i></span><span><span class="bk-channel-name">{{$name}}</span><span class="bk-channel-sub" style="display:block">{{$sub}}</span></span></label>
      @endforeach
     </div><div class="bk-reminder"><i class="fas fa-info-circle"></i><span>Automatic alerts are sent 3 hours and 1 hour before the custom expiry time, plus a final expiry alert at that deadline.</span></div></div>
    </div>
   </div>
  </div>

  <div class="bk-footer"><div class="bk-footer-note"><i class="fas fa-shield-alt mr-1"></i>A booking creates no accounting or inventory transaction.</div><div class="bk-footer-actions"><a class="bk-btn bk-btn-back" href="{{route('role.ticket-purchase.index',compact('role'))}}">Cancel</a><button class="bk-btn bk-btn-save"><i class="fas fa-save"></i>{{$booking->exists?'Save Booking Changes':'Save Booking & Notify'}}</button></div></div>
 </form>
</div>

<div class="bk-modal-wrap" id="passengerQuickModal" onclick="if(event.target===this)closeBookingModal('passenger')">
 <div class="bk-modal"><div class="bk-modal-head"><i class="fas fa-passport"></i><span class="bk-modal-title">Create New Passport Holder</span><button type="button" class="bk-modal-close" onclick="closeBookingModal('passenger')">×</button></div>
  <div class="bk-modal-body"><div class="bk-modal-error" id="passengerQuickError"></div><div class="bk-grid bk-cols-2">
   <div class="bk-full"><label class="bk-label">Full Name <span class="bk-required">*</span></label><input id="quickPassengerName" class="bk-input" placeholder="Passenger full name"></div>
   <div><label class="bk-label">Passport No <span style="font-weight:400;color:#94a3b8">— optional</span></label><input id="quickPassportNo" class="bk-input" placeholder="e.g. BD1234567"></div>
   <div><label class="bk-label">Phone <span class="bk-required">*</span></label><input id="quickPassengerPhone" class="bk-input" placeholder="01XXXXXXXXX"></div>
   <div><label class="bk-label">Nationality <span class="bk-required">*</span></label><input id="quickNationality" class="bk-input" value="Bangladeshi"></div>
   <div><label class="bk-label">Category <span class="bk-required">*</span></label><select id="quickCategory" class="bk-select"><option value="">— Select category —</option>@foreach($phCategories as $cat)<option value="{{$cat->id}}" @selected(strtolower(trim($cat->name)) === 'ordinary passport')>{{$cat->name}}</option>@endforeach</select></div>
  </div></div><div class="bk-modal-foot"><button type="button" class="bk-btn bk-btn-back" onclick="closeBookingModal('passenger')">Cancel</button><button type="button" class="bk-btn bk-btn-save" id="quickPassengerSave" onclick="saveQuickPassenger()"><i class="fas fa-save"></i> Save & Select</button></div>
 </div>
</div>

<div class="bk-modal-wrap" id="ticketQuickModal" onclick="if(event.target===this)closeBookingModal('ticket')">
 <div class="bk-modal"><div class="bk-modal-head"><i class="fas fa-plane"></i><span class="bk-modal-title">Create New Ticket Route</span><button type="button" class="bk-modal-close" onclick="closeBookingModal('ticket')">×</button></div>
  <div class="bk-modal-body"><div class="bk-modal-error" id="ticketQuickError"></div><div class="bk-grid bk-cols-2">
   <div class="bk-full"><label class="bk-label">Route Title <span class="bk-required">*</span></label><input id="quickRouteTitle" class="bk-input" placeholder="e.g. DAC – DXB"></div>
   <div><label class="bk-label">Default Cost Price</label><input type="number" min="0" step="0.01" id="quickRoutePrice" class="bk-input" placeholder="0.00"></div>
  </div></div><div class="bk-modal-foot"><button type="button" class="bk-btn bk-btn-back" onclick="closeBookingModal('ticket')">Cancel</button><button type="button" class="bk-btn bk-btn-save" id="quickTicketSave" onclick="saveQuickTicket()"><i class="fas fa-save"></i> Save & Select</button></div>
 </div>
</div>
@endsection

@section('js')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
const bookingCsrf=@json(csrf_token());
function openBookingModal(type){document.getElementById(type+'QuickError').style.display='none';document.getElementById(type+'QuickModal').classList.add('open')}
function closeBookingModal(type){document.getElementById(type+'QuickModal').classList.remove('open')}
function quickError(type,message){const el=document.getElementById(type+'QuickError');el.textContent=message;el.style.display='block'}
async function postQuick(url,data,type,button){button.disabled=true;try{const response=await fetch(url,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':bookingCsrf,'X-Requested-With':'XMLHttpRequest'},body:JSON.stringify(data)});const json=await response.json();if(!response.ok||!json.success)throw new Error(json.message||'Unable to save.');return json}catch(error){quickError(type,error.message);return null}finally{button.disabled=false}}
async function saveQuickPassenger(){const btn=document.getElementById('quickPassengerSave');const json=await postQuick(@json(route('role.passport-holder.store',['role'=>$role])),{name:document.getElementById('quickPassengerName').value.trim(),passport_no:document.getElementById('quickPassportNo').value.trim(),phone:document.getElementById('quickPassengerPhone').value.trim(),nationality:document.getElementById('quickNationality').value.trim(),category_id:document.getElementById('quickCategory').value,type:'ticket',status:1},'passenger',btn);if(!json)return;const select=document.getElementById('bookingPassenger');select.add(new Option(json.data.name,json.data.id,true,true));$(select).trigger('change');closeBookingModal('passenger')}
async function saveQuickTicket(){const btn=document.getElementById('quickTicketSave');const json=await postQuick(@json(route('role.ticket-quick-create-route',['role'=>$role])),{title:document.getElementById('quickRouteTitle').value.trim(),price:document.getElementById('quickRoutePrice').value||0},'ticket',btn);if(!json)return;const select=document.getElementById('bookingTicket'),option=new Option(json.ticket.title,json.ticket.id,true,true);option.dataset.price=json.ticket.price||0;select.add(option);$(select).trigger('change');document.getElementById('bookingQuote').value=json.ticket.price||0;closeBookingModal('ticket')}
document.getElementById('bookingTicket').addEventListener('change',function(){const option=this.options[this.selectedIndex],quote=document.getElementById('bookingQuote');if(option&&option.dataset.price&&(!quote.value||Number(quote.value)===0))quote.value=option.dataset.price});
document.getElementById('bookingVendor').addEventListener('change',function(){if(this.value)$('#bookingPortal').val(null).trigger('change.select2')});
document.getElementById('bookingPortal').addEventListener('change',function(){if(this.value)$('#bookingVendor').val(null).trigger('change.select2')});
$(function(){
 $('.bk-select').each(function(){
  const $select=$(this),$modal=$select.closest('.bk-modal');
  $select.select2({width:'100%',dropdownParent:$modal.length?$modal:$(document.body),placeholder:$select.find('option:first').text(),allowClear:!$select.prop('required')});
 });
});
</script>
@include('partials.datetime-picker'){{-- date + optional time on operational date boxes (2026-09-30) --}}
@endsection
