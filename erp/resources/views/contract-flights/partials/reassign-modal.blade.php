{{-- Re-assign an off-flight passenger onto another flight (owner,
     2026-09-26): how he travels (reschedule / reissue / new ticket), which
     open flight, and what the change costs and who bears it. Opened from the
     Cancelled rows by cfpOpenReassign() (partials.reassign-script).
     Closes only on the ✕ or Cancel — never on a backdrop click (owner,
     2026-09-22), so no .modal-backdrop class here. --}}
<style>
    #cfpReassignOverlay .cfr-sec{margin-bottom:10px;}
    #cfpReassignOverlay .cfr-sec:last-child{margin-bottom:0;}
    #cfpReassignOverlay .cfr-h{font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#475569;margin:0 0 6px;}
    #cfpReassignOverlay .cfr-kinds{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;}
    #cfpReassignOverlay .cfr-kind{position:relative;display:block;border:1px solid #dbe3ef;border-radius:10px;padding:8px 10px 8px 32px;cursor:pointer;background:#fff;margin:0;}
    #cfpReassignOverlay .cfr-kind input{position:absolute;left:11px;top:11px;margin:0;}
    #cfpReassignOverlay .cfr-kind:has(input:checked){border-color:#0d9488;background:#f0fdfa;box-shadow:0 0 0 2px #0d948822;}
    #cfpReassignOverlay .cfr-kind-t{font-size:12px;font-weight:800;color:#1e293b;}
    #cfpReassignOverlay .cfr-kind-s{font-size:10.5px;color:#64748b;line-height:1.3;margin-top:2px;}
    #cfpReassignOverlay .cfr-search{width:100%;box-sizing:border-box;margin-bottom:6px;}
    #cfpReassignOverlay .cfr-flights{max-height:150px;overflow-y:auto;border:1px solid #e5e7eb;border-radius:10px;background:#fff;}
    #cfpReassignOverlay .cfr-flight{display:grid;grid-template-columns:16px minmax(0,1fr) auto;gap:8px;align-items:center;padding:6px 10px;border-bottom:1px solid #f1f5f9;cursor:pointer;font-size:12px;margin:0;}
    #cfpReassignOverlay .cfr-flight:last-child{border-bottom:none;}
    #cfpReassignOverlay .cfr-flight:hover{background:#f8fafc;}
    #cfpReassignOverlay .cfr-flight:has(input:checked){background:#f0fdfa;}
    #cfpReassignOverlay .cfr-flight input{margin:0;}
    #cfpReassignOverlay .cfr-flight b{color:#0f172a;}
    #cfpReassignOverlay .cfr-flight small{display:block;color:#64748b;font-size:10.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    #cfpReassignOverlay .cfr-flight em{font-style:normal;font-size:10.5px;color:#475569;white-space:nowrap;}
    #cfpReassignOverlay .cfr-empty{padding:12px;font-size:12px;color:#94a3b8;}
    #cfpReassignOverlay .cfr-money{display:grid;grid-template-columns:150px 170px minmax(0,1fr);gap:8px;}
    #cfpReassignOverlay .cfr-money .fcm-input,#cfpReassignOverlay .cfr-money .fcm-select{box-sizing:border-box;padding:7px 10px;}
    #cfpReassignOverlay .cfr-summary{border:1px solid #99f6e4;background:#f0fdfa;border-radius:10px;padding:6px 12px;font-size:12px;color:#134e4a;}
    #cfpReassignOverlay .cfr-summary b{color:#0f766e;}
    #cfpReassignOverlay .cfr-summary li{margin:1px 0 1px 16px;list-style:disc;line-height:1.35;}
    #cfpReassignOverlay .cfr-error{margin-top:8px;color:#dc2626;font-size:12px;}
    #cfpReassignOverlay .cfr-flight.cfr-new b{color:#0f766e;}
    #cfpReassignOverlay .cfr-nf{display:none;grid-template-columns:minmax(0,1.4fr) 130px 190px;gap:8px;margin-top:6px;padding:8px;border:1px dashed #5eead4;border-radius:10px;background:#f0fdfa;}
    #cfpReassignOverlay .cfr-nf.is-open{display:grid;}
    #cfpReassignOverlay .cfr-nf .fcm-input,#cfpReassignOverlay .cfr-nf .fcm-select{box-sizing:border-box;padding:6px 9px;width:100%;}
    #cfpReassignOverlay .cfr-hint{font-size:10.5px;color:#64748b;margin-top:3px;}
    #cfpReassignOverlay .cfr-kinds.has-3,#cfpReassignOverlay .cfr-kinds-3{grid-template-columns:repeat(3,minmax(0,1fr));}
    #cfpReassignOverlay .cfr-kind:has(input[value="offload"]:checked){border-color:#b91c1c;background:#fef2f2;box-shadow:0 0 0 2px #b91c1c22;}
    #cfpReassignOverlay .cfr-oi-t{width:100%;border-collapse:separate;border-spacing:0;font-size:12px;background:#fff;border:1px solid #e2e8f0;border-left:4px solid #0f766e;border-radius:10px;overflow:hidden;}
    #cfpReassignOverlay .cfr-oi-t th{font-size:9.5px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:#94a3b8;background:#fff;text-align:left;padding:10px 8px 4px;border-bottom:1px solid #f1f5f9;}
    #cfpReassignOverlay .cfr-oi-t td{padding:6px 8px;border-bottom:1px solid #f1f5f9;vertical-align:middle;}
    #cfpReassignOverlay .cfr-oi-t th:first-child,#cfpReassignOverlay .cfr-oi-t td:first-child{padding-left:16px;}
    #cfpReassignOverlay .cfr-oi-t th:last-child,#cfpReassignOverlay .cfr-oi-t td:last-child{padding-right:16px;}
    #cfpReassignOverlay .cfr-oi-t .n{text-align:right;white-space:nowrap;}
    #cfpReassignOverlay .cfr-oi-t input,#cfpReassignOverlay .cfr-oi-t select{width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:7px;padding:5px 7px;font-size:12px;}
    #cfpReassignOverlay .cfr-oi-t small{display:block;color:#64748b;font-size:10.5px;}
    #cfpReassignOverlay .cfr-pkg{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:center;gap:4px 12px;margin-top:8px;padding:8px 10px;background:#fff;border:1px solid #e2e8f0;border-radius:10px;font-size:12px;}
    #cfpReassignOverlay .cfr-pkg small{display:block;color:#64748b;font-size:10.5px;}
    #cfpReassignOverlay .cfr-pkg-r{display:flex;align-items:center;gap:8px;}
    #cfpReassignOverlay .cfr-pkg-h{font-size:9.5px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:#64748b;}
    #cfpReassignOverlay .cfr-pkg .cfr-hint{grid-column:1 / -1;margin-top:0;}
    #cfpReassignOverlay .cfr-oi-t tfoot td{background:#f0fdfa;color:#134e4a;font-size:12px;}
    @media (max-width: 760px) {
        #cfpReassignOverlay .cfr-oi-t thead{display:none;}
        #cfpReassignOverlay .cfr-oi-t tr{display:grid;grid-template-columns:1fr 1fr;gap:4px;padding:6px 0;}
        #cfpReassignOverlay .cfr-kinds,#cfpReassignOverlay .cfr-kinds.has-3,#cfpReassignOverlay .cfr-kinds-3,#cfpReassignOverlay .cfr-money,#cfpReassignOverlay .cfr-nf.is-open{grid-template-columns:1fr;}
    }
    /* One control height in this dialog (owner, 2026-11-02: "match the height … across those 4 sections").
       Every box, dropdown, date and Who pays button = 36px, so labels and fields line up in each row. UI only. */
    #cfpReassignOverlay .fcm-body input[type="text"],#cfpReassignOverlay .fcm-body input[type="number"],
    #cfpReassignOverlay .fcm-body input[type="search"],#cfpReassignOverlay .fcm-body input[type="date"],
    #cfpReassignOverlay .fcm-body input[type="datetime-local"],#cfpReassignOverlay .fcm-body select:not(.cfr-sw-src){height:36px;box-sizing:border-box;}
    #cfpReassignOverlay .select2-container .select2-selection--single{height:36px !important;min-height:36px !important;box-sizing:border-box;display:flex;align-items:center;}
    #cfpReassignOverlay .select2-container .select2-selection--single .select2-selection__rendered{line-height:34px !important;}
    #cfpReassignOverlay .select2-container .select2-selection--single .select2-selection__arrow{height:34px !important;}
    #cfpReassignOverlay .cfr-sw .cfr-sw-b,#cfpReassignOverlay .cfr-tc-b{height:36px;min-height:36px;}
    /* One ticket card for every section (owner, 2026-11-02: "2 ticket present style … same in the 4 sections,
       like Reschedule / Reissue"). Twin of the .cfr-tc look in reassign-script; own class so no section's
       code picks up another's cards. UI only. */
    #cfpReassignOverlay .cfr-card{display:grid;gap:16px;align-items:center;border:1px solid #e2e8f0;border-left:4px solid #0f766e;border-radius:10px;padding:12px 16px;background:#fff;}
    #cfpReassignOverlay .cfr-cards{display:grid;gap:8px;}
    #cfpReassignOverlay .cfr-tk{display:flex;align-items:center;gap:9px;min-width:0;}
    #cfpReassignOverlay .cfr-tk b{display:block;font-size:13px;color:#0f172a;}
    #cfpReassignOverlay .cfr-tk small{font-size:10.5px;color:#64748b;}
    #cfpReassignOverlay .cfr-tk-route{display:flex;align-items:center;gap:5px;font-size:13px;color:#0f172a;margin-bottom:6px;white-space:nowrap;}
    #cfpReassignOverlay .cfr-tk-route img{width:18px;height:12px;object-fit:cover;border-radius:2px;box-shadow:0 0 0 1px #e2e8f0;}
    #cfpReassignOverlay .cfr-tk-route i{color:#0f766e;font-size:11px;}
    #cfpReassignOverlay .cfr-tk-tag{display:inline-block;font-size:10.5px;font-weight:700;color:#4f46e5;background:#eef2ff;border-radius:5px;padding:1px 6px;}
    #cfpReassignOverlay .cfr-lbl{display:block;font-size:9.5px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:#94a3b8;margin-bottom:2px;}
    #cfpReassignOverlay .cfr-card .cfr-val{font-size:12.5px;color:#0f172a;}
    #cfpReassignOverlay .cfr-card input[type="number"]{width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:8px;padding:6px 8px;font-size:12.5px;}
    #cfpReassignOverlay .cfr-card-total{font-size:12px;color:#475569;background:#f0fdfa;border:1px dashed #99f6e4;border-radius:10px;padding:7px 12px;}
    #cfpReassignOverlay .cfr-oi-card{grid-template-columns:minmax(0,1fr) 100px 90px 120px 90px 100px 260px;}
    #cfpReassignOverlay label.cfr-card.cfsw-tk{grid-template-columns:auto minmax(0,1fr) auto;cursor:pointer;margin:0;}
    #cfpReassignOverlay label.cfr-card.cfsw-tk:has(input:checked){background:#f0fdfa;box-shadow:0 0 0 2px #0d948822;}
    @media (max-width:900px){#cfpReassignOverlay .cfr-oi-card{grid-template-columns:1fr 1fr;}}
</style>
<div id="cfpReassignOverlay" class="fcm-overlay hidden">
    <div class="fcm-box" style="width:min(1280px, calc(100vw - 32px));max-width:min(1280px, calc(100vw - 32px));height:calc(100vh - 24px);max-height:calc(100vh - 24px);">
        <div class="fcm-head" style="background:linear-gradient(135deg,#0f766e,#0d9488);">
            <div class="fcm-head-ic"><i class="fas fa-plane-circle-check"></i></div>
            <div style="min-width:0;">
                <div class="fcm-title" id="cfr_title">Re-assign passenger</div>
                <div class="fcm-sub" id="cfr_sub" style="color:#ccfbf1;">Off flight</div>
            </div>
            @php
                // Contract Flight Trail (owner, 2026-11-01): super admin, and only when its service and routes exist
                $cftOk = false;
                try {
                    $cftOk = \App\Support\UserRoleSetting\Access::allows('travels.contract-flight', 'e') && class_exists(\App\Services\ContractFlightTrail::class) && \App\Services\ContractFlightTrail::available();
                } catch (\Throwable $e) {
                    $cftOk = false;
                }
            @endphp
            <div style="margin-left:auto;display:flex;align-items:center;gap:8px;">
                @if($cftOk)
                    <button type="button" onclick="cftOpenFromReassign()" title="Every entry of this passenger — what each posted, editable; wrong rows are replaced"
                        style="display:inline-flex;align-items:center;gap:6px;border:1px solid rgba(255,255,255,.55);background:rgba(255,255,255,.14);color:#fff;border-radius:8px;padding:6px 12px;font-size:12px;font-weight:700;cursor:pointer;white-space:nowrap;">
                        <i class="fas fa-clock-rotate-left"></i> All Trails and Edit
                    </button>
                @endif
                <button type="button" class="fcm-close" onclick="cfpCloseReassign()" title="Close">✕</button>
            </div>
        </div>
        <div class="fcm-body" style="background:#fff;padding:12px 16px;">
            <input type="hidden" id="cfr_id">

            <div class="cfr-sec">
                <div class="cfr-h">How does he travel?</div>
                <div class="cfr-kinds" id="cfr_kinds_main">
                    <label class="cfr-kind">
                        <input type="radio" name="cfr_kind" value="reschedule" checked>
                        <div class="cfr-kind-t">Reschedule / Reissue</div>
                        <div class="cfr-kind-s">Same ticket, new flight — his live PNR goes with him.</div>
                    </label>
                    <label class="cfr-kind">
                        <input type="radio" name="cfr_kind" value="reissue">
                        <div class="cfr-kind-t">Refund (Cancel) / New Ticket</div>
                        <div class="cfr-kind-s">Old ticket cancelled — it stays in Ticket Orders with the airline's refund posted beside it; issue the new one on the flight's card.</div>
                    </label>
                    {{-- Offload / Deported (owner, 2026-10-07): only for someone on a
                         flight, and only when its route is registered. --}}
                    @if(\App\Models\ContractFlightPassengerChange::offloadAvailable())
                    <label class="cfr-kind" id="cfr_kind_off" style="display:none;">
                        <input type="radio" name="cfr_kind" value="offload">
                        <div class="cfr-kind-t">Offload / Deported</div>
                        <div class="cfr-kind-s">Boarding denied, missed flight or sent back — ticket refund, penalty and charge posted to the ledgers by whose side.</div>
                    </label>
                    @endif
                    {{-- Suspend withdraw (owner, 2026-11-02): own card, own logic — partials.suspend-withdraw-script. --}}
                    @if(\App\Models\ContractFlightSuspendWithdrawal::available())
                    <label class="cfr-kind" id="cfr_kind_sw" style="display:none;">
                        <input type="radio" name="cfr_kind" value="suspend_withdraw">
                        <div class="cfr-kind-t">Suspend withdraw</div>
                        <div class="cfr-kind-s">Airline / portal suspended his ticket and another vendor lifted it — the vendor's cost, who bears it, our charge.</div>
                    </label>
                    @endif
                </div>
            </div>

            @if(\App\Models\ContractFlightSuspendWithdrawal::available())
                @include('contract-flights.partials.suspend-withdraw-section')
            @endif

            @if(\App\Models\ContractFlightPassengerChange::offloadAvailable())
            <div class="cfr-sec" id="cfr_off_sec" style="display:none;">
                <div class="cfr-h">What happened?</div>
                <div class="cfr-kinds cfr-kinds-3">
                    <label class="cfr-kind">
                        <input type="radio" name="cfr_off_event" value="immigration_offload" checked>
                        <div class="cfr-kind-t">Immigration offload</div>
                        <div class="cfr-kind-s">Stopped by immigration at departure.</div>
                    </label>
                    <label class="cfr-kind">
                        <input type="radio" name="cfr_off_event" value="boarding_denied">
                        <div class="cfr-kind-t">Boarding offload</div>
                        <div class="cfr-kind-s">Denied boarding at the gate by the airline.</div>
                    </label>
                    <label class="cfr-kind">
                        <input type="radio" name="cfr_off_event" value="deported">
                        <div class="cfr-kind-t">Deported</div>
                        <div class="cfr-kind-s">Flew, and was sent back from the destination. The case closes on this flight.</div>
                    </label>
                </div>
                {{-- Offload: every ticket and paid service, item by item (owner, 2026-10-07). --}}
                <div id="cfr_off_items_wrap">
                    <div class="cfr-h" style="margin-top:10px;">His tickets &amp; services — refund, penalty, our charge, who pays</div>
                    <div id="cfr_off_items"></div>
                </div>
                {{-- Deported: nothing refunded; the costs of sending him home. --}}
                <div id="cfr_dep_box" style="display:none;">
                    <div class="cfr-h" style="margin-top:10px;">Deportation</div>
                    <input type="text" id="cfr_dep_from" class="fcm-input" maxlength="120" placeholder="Deported from — airport / country (e.g. IST, Türkiye)" style="width:100%;box-sizing:border-box;margin-bottom:8px;">
                    <div id="cfr_dep_costs"></div>
                    {{-- His package, under the costs (owner, 2026-10-26: above the table, unlabelled, it read
                         as a second "who bears" for the same charges). Deported only — the agent still pays
                         it (agent side) or it is given back (our side); the flag follows. --}}
                    <div class="cfr-pkg" id="cfr_off_fault_row">
                        <div class="cfr-pkg-l"><b>His package</b> <span id="cfr_pkg_amt"></span><small id="cfr_pkg_who"></small></div>
                        <div class="cfr-pkg-r"><span class="cfr-pkg-h">Who pays</span>
                            <select id="cfr_off_fault" class="fcm-select cfr-sw-src" title="Who bears his package">
                                <option value="">Who bears his package? — choose</option>
                                @foreach(\App\Models\ContractFlightPassengerChange::FAULTS as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                                <option value="split">Split</option>
                            </select>
                            <div id="cfr_pkg_split" style="display:none;margin-top:6px;font-size:11px"><label>Agent bears<input type="number" id="cfr_pkg_agent" min="0" step="0.01" class="fcm-input" style="color:#dc2626"></label><div id="cfr_pkg_ours" style="color:#b45309;margin-top:4px"></div></div>
                        </div>
                        <div class="cfr-hint" id="cfr_off_fault_hint"></div>
                    </div>
                </div>
                <div class="cfr-h" style="margin-top:10px;" id="cfr_off_next_h">What next?</div>
                <div class="cfr-kinds cfr-kinds-3" id="cfr_off_next">
                    <label class="cfr-kind">
                        <input type="radio" name="cfr_off_next" value="rebook" checked>
                        <div class="cfr-kind-t">Re-book on another flight</div>
                        <div class="cfr-kind-s">Choose the flight below. Same ticket if none is refunded; otherwise issue the new one on that flight's card.</div>
                    </label>
                    <label class="cfr-kind">
                        <input type="radio" name="cfr_off_next" value="hold">
                        <div class="cfr-kind-t">Keep him here, flagged</div>
                        <div class="cfr-kind-s">Stays on this flight as offloaded — Re-assign him later when the new date is known.</div>
                    </label>
                    <label class="cfr-kind">
                        <input type="radio" name="cfr_off_next" value="close">
                        <div class="cfr-kind-t">Will not fly — close the case</div>
                        <div class="cfr-kind-s">Off the flight, package reversed on the agent's statement; shows under Cancelled as Closed.</div>
                    </label>
                </div>
            </div>
            @endif

            {{-- Refund (Cancel) / New Ticket (owner, 2026-09-30): the airline's
                 refund per old ticket, whose side the penalty is, and what next. --}}
            <div class="cfr-sec" id="cfr_refund_sec" style="display:none;">
                <div class="cfr-h" id="cfr_refund_h">Airline refund on his old ticket(s)</div>
                <div id="cfr_refund_cards" style="display:grid;gap:8px;margin-bottom:8px;"></div>
                <div class="cfr-money" id="cfr_fault_row">
                    <select id="cfr_fault" class="fcm-select cfr-sw-src" title="Whose side caused the penalty the airline keeps">
                        <option value="">Whose side caused it? — choose</option>
                        @foreach(\App\Models\ContractFlightPassengerChange::FAULTS as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <div class="cfr-hint" style="grid-column:span 2;">Our side: we bear the penalty (Company Ledger). Passenger / agent side: it is billed to the agent on the same line.</div>
                </div>
                <div class="cfr-h" style="margin-top:10px;" id="cfr_next_h">What next?</div>
                <div class="cfr-kinds" id="cfr_next_opts">
                    <label class="cfr-kind">
                        <input type="radio" name="cfr_next" value="fly" checked>
                        <div class="cfr-kind-t">Put him on a flight — new ticket</div>
                        <div class="cfr-kind-s">Choose the flight below. After confirming, his card on that flight opens with Issue New Ticket ready; the fare difference works itself out when the new ticket is linked.</div>
                    </label>
                    <label class="cfr-kind" id="cfr_next_close">
                        <input type="radio" name="cfr_next" value="close">
                        <div class="cfr-kind-t">He will not fly — close the case</div>
                        <div class="cfr-kind-s">Package reversed on the agent's statement, the penalty billed back if it was his side — net refund = package − penalty, as a credit. He shows under Cancelled as Closed.</div>
                    </label>
                </div>
            </div>

            <div class="cfr-sec" id="cfr_flight_sec">
                <div class="cfr-h">Onto which flight?</div>
                <input type="search" id="cfr_search" class="fcm-input cfr-search" placeholder="Search all flights — number, airline, route, date or status (departed too)…" autocomplete="off">
                <div class="cfr-flights" id="cfr_flights"><div class="cfr-empty">Loading open flights…</div></div>
                {{-- "+ New flight" (2026-09-28): created on Re-assign, open, next GF number. --}}
                <div class="cfr-nf" id="cfr_nf">
                    <select id="cfr_nf_route" class="fcm-select"><option value="">Route — pick later</option></select>
                    <input type="text" id="cfr_nf_no" class="fcm-input" maxlength="50" placeholder="Airline flight no.">
                    <input type="datetime-local" id="cfr_nf_dep" class="fcm-input" title="Departure (optional)">
                </div>
            </div>

            <div class="cfr-sec">
                {{-- Offload / Deported carry their charge in their own table ("Our deport charge", our
                     charge per item) — there this heading went, leaving only the note (owner, 2026-10-26). --}}
                <div class="cfr-h" id="cfr_charge_h">Charge for the change</div>
                {{-- Reschedule / Reissue (owner, 2026-09-30): one charge per ticket he
                     holds; the bearer select below applies to all of them. --}}
                <div id="cfr_ticket_charges" style="display:grid;gap:6px;margin-bottom:6px;"></div>
                <div class="cfr-money">
                    <input type="number" id="cfr_charge" style="color:#dc2626;font-weight:700;" class="fcm-input" min="0" step="0.01" placeholder="৳ 0.00 (none)">
                    <select id="cfr_bearer" class="fcm-select cfr-sw-src">
                        @foreach(\App\Models\ContractFlightPassengerChange::BEARERS as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <input type="text" id="cfr_note" class="fcm-input" maxlength="2000" placeholder="Note (optional)">
                </div>
            </div>

            <div class="cfr-sec" id="cfr_fare_sec">
                <div class="cfr-h">Fare difference on the new ticket</div>
                <div class="cfr-money">
                    <input type="number" id="cfr_fare" class="fcm-input" min="0" step="0.01" placeholder="Blank = from new ticket">
                    <select id="cfr_fare_bearer" class="fcm-select cfr-sw-src">
                        @foreach(\App\Models\ContractFlightPassengerChange::BEARERS as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @if(\App\Models\ContractFlightPassengerChange::splitFareAvailable())
                        <div><label><input type="checkbox" id="cfr_fare_split"> Split</label><div id="cfr_fare_split_fields" style="display:none"><label>Agent bears<input type="number" id="cfr_fare_agent" min="0" step="0.01" class="fcm-input" style="color:#dc2626"></label><div id="cfr_fare_ours"></div></div></div>
                    @endif
                    <div class="cfr-hint" id="cfr_fare_hint">Leave blank on a reissue: it is worked out when his new ticket is linked.</div>
                </div>
            </div>

            <div class="cfr-sec">
                <div class="cfr-summary" id="cfr_summary"></div>
                <div class="cfr-error hidden" id="cfr_error"></div>
            </div>
        </div>
        <div class="fcm-foot">
            <button type="button" class="fcm-btn fcm-btn-ghost" onclick="cfpCloseReassign()">Cancel</button>
            <button type="button" id="cfr_save" class="fcm-btn fcm-btn-save" style="background:#0f766e;" onclick="cfpSaveReassign()"><i class="fas fa-plane-circle-check"></i> Re-assign</button>
        </div>
    </div>
</div>
@if($cftOk ?? false)
    @include('contract-flights.partials.cf-trail'){{-- super admin: All Trails and Edit (2026-11-01) --}}
@endif
