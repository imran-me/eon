{{-- Round trip on a ticket route (owner, 2026-10-18: "I need an option to make round trip route —
     DAC-ECN-DAC, in one ticket or multiple tickets"). Sits under Via airports in Manage Tickets → Add /
     Edit and in Ticket Entry's "+" route. From / To stay origin → destination.
       Trip: One-way | Round trip
       Return (round trip): Same ticket — every ticket of the route is a round-trip ticket (no extra cards)
                            Separate ticket — the return is its own ticket(s), To → From, with its own via
                            airports (transit / new ticket), airline, seller and price.
     Posts trip_type, return_mode, return_stops[i][airport_id|new_ticket]; the return tickets' cards join
     legs[] after the outbound ones (tickets.route-via-script). Hidden until the trip_type migration ran. --}}
@if (\App\Models\Ticket::roundTripAvailable())
@once
<style>
.tk-trip-wrap{grid-column:1 / -1;display:flex;flex-direction:column;gap:8px;}
.tk-seg{display:inline-flex;border:1px solid #e4e1f7;border-radius:10px;overflow:hidden;align-self:flex-start;}
.tk-seg button{border:0;background:#fff;padding:7px 13px;font-size:12px;font-weight:700;color:#64748b;cursor:pointer;white-space:nowrap;display:inline-flex;align-items:center;gap:6px;}
.tk-seg button + button{border-left:1px solid #e4e1f7;}
.tk-seg button.on{background:#6d28d9;color:#fff;}
.tk-trip-row{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
.tk-trip-row .tk-trip-k{font-size:11.5px;font-weight:700;color:#475569;min-width:52px;}
.tk-return{display:flex;flex-direction:column;gap:8px;border:1px dashed #ddd6fe;border-radius:12px;background:#faf5ff;padding:10px 12px;}
.tk-return[hidden],.tk-return-sep[hidden]{display:none;}
.tk-return-note{font-size:11.5px;color:#6b7280;margin:0;line-height:1.45;}
.tk-return-sep{display:flex;flex-direction:column;gap:8px;}
</style>
@endonce
<div class="tk-trip-wrap full" data-trip-prefix="{{ $prefix }}">
    <div class="tk-trip-row">
        <span class="tk-trip-k">Trip</span>
        <span class="tk-seg tk-trip-seg">
            <button type="button" class="tk-trip-btn on" data-trip="one-way"><i class="fas fa-arrow-right-long"></i> One-way</button>
            <button type="button" class="tk-trip-btn" data-trip="two-way"><i class="fas fa-arrow-right-arrow-left"></i> Round trip</button>
        </span>
        <input type="hidden" name="trip_type" id="{{ $prefix }}_trip_type" value="one-way">
    </div>
    <div class="tk-return" id="{{ $prefix }}_return" hidden>
        <div class="tk-trip-row">
            <span class="tk-trip-k">Return</span>
            <span class="tk-seg tk-return-seg">
                <button type="button" class="tk-return-btn on" data-mode="same">Same ticket</button>
                <button type="button" class="tk-return-btn" data-mode="separate">Separate ticket</button>
            </span>
            <input type="hidden" name="return_mode" id="{{ $prefix }}_return_mode" value="same">
        </div>
        <p class="tk-return-note" id="{{ $prefix }}_return_note"></p>
        <div class="tk-return-sep" id="{{ $prefix }}_return_sep" hidden>
            <label class="tk-lbl ph-modal-lbl" style="margin:0">Return via airports <span style="font-weight:400;color:#8a94a6;">— optional, in flying order from the destination back</span></label>
            <div class="tk-stops" id="{{ $prefix }}_rstops"></div>
            <button type="button" class="tk-stop-add" data-prefix="{{ $prefix }}" data-ret="1"><i class="fas fa-plus"></i> Add return via airport</button>
        </div>
    </div>
</div>
@endif
