{{--
    Step 1 — the flight itself, and nobody's personal details.

    Shared by the Add and Edit modals so the two can never drift apart;
    $prefix ('create' | 'edit') namespaces every id, exactly as the rest of
    the modal does. Everything a traveller carries — category, class, service
    mix, cost, price — is asked once per passenger in step 2 instead.

    Laid out in two columns because the modal is 1120px wide and a single
    stack of four half-empty grids wasted most of it.
--}}
@php($prefix = $prefix ?? 'create')

<div class="fs-cols">

    {{-- ── Left: the aircraft, the route, the times ── --}}
    <div class="fs-col">
        <section class="fs-card">
            <header class="fs-card-hd">
                <i class="fas fa-plane-departure"></i>
                <span>Flight</span>
                <span class="fs-hd-note">Which aircraft, which route, when</span>
            </header>
            <div class="fs-card-bd">
                <div class="fs-row-2">
                    <div class="fs-field">
                        <label class="fcm-lbl">Airline <span class="fs-req">*</span></label>
                        <select id="{{ $prefix }}_pricing_airline_id" class="fcm-select pricing-airline" style="width:100%">
                            <option value="">Select airline</option>
                            @foreach($airlines as $airline)
                                <option value="{{ $airline->id }}">{{ $airline->name }}{{ $airline->code ? ' ('.$airline->code.')' : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="fs-field">
                        <label class="fcm-lbl">Flight number <span class="fs-req">*</span></label>
                        <input type="text" id="{{ $prefix }}_airline_flight_no" name="airline_flight_no" class="fcm-input" placeholder="e.g. BG-088">
                        <p id="{{ $prefix }}_airline_flight_no_msg" class="text-red-500 hidden error-message">Flight number is required.</p>
                    </div>
                </div>

                <div class="fs-field">
                    <label class="fcm-lbl">
                        Route <span class="fs-req">*</span>
                        <span class="fs-note">— from Ticketing, carries the airline and both airports</span>
                    </label>
                    <div class="fcm-route-row">
                        <div class="fcm-select-wrap">
                            <select id="{{ $prefix }}_ticket_id" name="ticket_id" class="ticket-route-select" style="width:100%">
                                <option value="">— Select route —</option>
                                @foreach($tickets as $ticket)
                                    <option value="{{ $ticket->id }}" data-airline-id="{{ $ticket->airline_id }}" data-ff="{{ strtolower($ticket->from_airport?->country?->code ?? '') }}" data-tf="{{ strtolower($ticket->to_airport?->country?->code ?? '') }}">
                                        {{ $ticket->title }}{{ $ticket->airline ? ' — '.$ticket->airline->name : '' }}{{ ($ticket->from_airport && $ticket->to_airport) ? ' ('.$ticket->from_airport->code.' → '.$ticket->to_airport->code.')' : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <button type="button" class="fcm-add-route-btn" onclick="openTicketRouteModal('{{ $prefix }}_ticket_id')"><i class="fas fa-plus"></i> New</button>
                    </div>
                    <p id="{{ $prefix }}_ticket_id_msg" class="text-red-500 hidden error-message">Please select a route.</p>
                    <p class="fcm-hint" style="font-size:11px;color:#94a3b8;margin-top:3px;">Optional for now — passengers wait before "Route Confirmed" until it is chosen.</p>
                </div>

                <div class="fs-row-2">
                    <div class="fs-field">
                        <label class="fcm-lbl">Departure <span class="fs-req">*</span></label>
                        <input type="datetime-local" id="{{ $prefix }}_departure_at" name="departure_at" class="fcm-input">
                        <p id="{{ $prefix }}_departure_at_msg" class="text-red-500 hidden error-message">Departure date and time are required.</p>
                        <p class="fcm-hint" style="font-size:11px;color:#94a3b8;margin-top:3px;">Optional — usually known only once the ticket is issued.</p>
                    </div>
                    <div class="fs-field">
                        <label class="fcm-lbl">Arrival <span class="fs-note">— at destination</span></label>
                        <input type="datetime-local" id="{{ $prefix }}_arrival_at" name="arrival_at" class="fcm-input">
                        <p id="{{ $prefix }}_arrival_at_msg" class="text-red-500 hidden error-message">Arrival cannot be before departure.</p>
                    </div>
                </div>
            </div>
        </section>
    </div>

    {{-- ── Agents & Ticketing / Officers: kept in the DOM (the passenger cards
         seed step 1's agent list and read its vendor / portal; edit hydrates
         the officers) but not shown — the owner picks all of these per
         passenger on the cards (2026-09-21). --}}
    <div class="fs-col fs-col-hidden" hidden>
        <section class="fs-card">
            <header class="fs-card-hd">
                <i class="fas fa-user-tie"></i>
                <span>Agents &amp; Ticketing</span>
            </header>
            <div class="fs-card-bd">
                {{-- Every agent with people on this batch. The first one is the
                     default new cards are billed to; an agent picked on a card
                     is added here automatically (owner, 2026-09-21). Posts the
                     default as agent_id — the column the flight has always had. --}}
                <div class="fs-field">
                    <label class="fcm-lbl">Agents on this flight <span class="fs-note">— first is the default</span></label>
                    <select id="{{ $prefix }}_agent_ids" name="agent_ids[]" multiple class="fcm-select pb-agent-select" style="width:100%">
                        @foreach($agents as $agent)
                            <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                        @endforeach
                    </select>
                    <input type="hidden" id="{{ $prefix }}_agent_id" name="agent_id" value="">
                </div>
                {{-- One airline, one seller: the vendor or portal the batch's
                     tickets come from. Pre-filled into each passenger's
                     "issue new ticket" form until that passenger is ticketed. --}}
                <div class="fs-row-2">
                    <div class="fs-field">
                        <label class="fcm-lbl">Ticket vendor</label>
                        <select id="{{ $prefix }}_vendor_id" name="vendor_id" class="fcm-select fs-ticket-source" data-source="vendor" style="width:100%">
                            <option value=""></option>
                            @foreach($vendors as $vendor)
                                <option value="{{ $vendor->id }}">{{ $vendor->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="fs-field">
                        <label class="fcm-lbl">Portal <span class="fs-note">— or a vendor</span></label>
                        <select id="{{ $prefix }}_portal_id" name="portal_id" class="fcm-select fs-ticket-source" data-source="portal" style="width:100%">
                            <option value=""></option>
                            @foreach($portals as $portal)
                                <option value="{{ $portal->id }}">{{ $portal->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </section>

        <section class="fs-card">
            <header class="fs-card-hd">
                <i class="fas fa-user-shield"></i>
                <span>Officers</span>
            </header>
            <div class="fs-card-bd">
                <div class="fs-field">
                    <label class="fcm-lbl">Boarding officer <span class="fs-note">— optional</span></label>
                    <select id="{{ $prefix }}_boarding_officer_id" name="boarding_officer_id" class="fcm-select officer-select" style="width:100%">
                        <option value="">Select boarding officer</option>
                        @foreach($officers as $officer)
                            @if(in_array('boarding', $officer->work_roles ?? []))
                                <option value="{{ $officer->id }}" data-airline-id="{{ $officer->airline_id }}">{{ $officer->user?->name }}{{ $officer->airline ? ' — '.$officer->airline->name : '' }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>
                <div class="fs-field">
                    <label class="fcm-lbl">Immigration officer <span class="fs-note">— only if someone takes the service</span></label>
                    <select id="{{ $prefix }}_immigration_officer_id" name="immigration_officer_id" class="fcm-select officer-select immigration-officer" style="width:100%">
                        <option value="">Select immigration officer</option>
                        @foreach($officers as $officer)
                            @if(in_array('immigration', $officer->work_roles ?? []))
                                <option value="{{ $officer->id }}" data-airline-id="{{ $officer->airline_id }}">{{ $officer->user?->name }}{{ $officer->airline ? ' — '.$officer->airline->name : '' }}</option>
                            @endif
                        @endforeach
                    </select>
                    <p id="{{ $prefix }}_immigration_officer_id_msg" class="text-red-500 hidden error-message">At least one passenger takes immigration support, so an officer is required.</p>
                </div>
            </div>
        </section>

    </div>

    {{-- ── Third: notes and where the batch stands ── --}}
    <div class="fs-col">
        <section class="fs-card">
            <header class="fs-card-hd">
                <i class="fas fa-note-sticky"></i>
                <span>Notes</span>
                <span class="fs-hd-note">Internal — never shown to the agent</span>
            </header>
            <div class="fs-card-bd">
                <textarea id="{{ $prefix }}_notes" name="notes" rows="2" class="fcm-input" placeholder="Anything the team should know about this batch…"></textarea>
            </div>
        </section>

        <section class="fs-card">
            <header class="fs-card-hd">
                <i class="fas fa-flag"></i>
                <span>Status</span>
            </header>
            <div class="fs-card-bd">
                <div class="fs-field">
                    <label class="fcm-lbl">Where this batch stands</label>
                    <select id="{{ $prefix }}_status" name="status" class="fcm-select">
                        <option value="open" @if($prefix === 'create') selected @endif>Open</option>
                        <option value="boarding">Boarding</option>
                        <option value="departed">Departed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
            </div>
        </section>
    </div>
</div>
