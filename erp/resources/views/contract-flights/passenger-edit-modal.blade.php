<style>
    /* Scoped to this modal only — the shared .fcm-section/.fcm-grid-* classes
       stay untouched so the Add/Edit Flight modal is unaffected. */
    #passengerEditOverlay .pem-panel{background:#f8fafc;border:1px solid #e9edf3;border-radius:12px;padding:16px 18px;margin-bottom:16px;}
    #passengerEditOverlay .pem-panel:last-of-type{margin-bottom:0;}
    #passengerEditOverlay .pem-panel .fcm-section-title{margin-bottom:14px;padding-bottom:0;border-bottom:none;}
    #passengerEditOverlay .pem-icon{display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:7px;background:#0f766e;color:#fff;font-size:10.5px;margin-right:8px;flex-shrink:0;}
    #passengerEditOverlay .fcm-section-title{font-size:12px;color:#334155;}
    #passengerEditOverlay .pem-totals{display:flex;gap:14px;flex-wrap:wrap;}
    #passengerEditOverlay .pem-total-card{flex:1;min-width:260px;display:flex;justify-content:space-between;align-items:center;gap:10px;border-radius:12px;padding:14px 18px;}
</style>
<div id="passengerEditOverlay" class="fcm-overlay hidden modal-backdrop">
    <div class="fcm-box" style="max-width:920px;height:auto;max-height:calc(100vh - 24px);">
        <div class="fcm-head" style="background:linear-gradient(135deg,#0f766e,#0d9488);">
            <div class="fcm-head-ic"><i class="fas fa-user-check"></i></div>
            <div>
                <div class="fcm-title" id="pem_passenger_name">Passenger</div>
                <div class="fcm-sub">Pipeline stage, documents &amp; money</div>
            </div>
            <button type="button" class="fcm-close" onclick="closePassengerEditModal()">✕</button>
        </div>
        <div class="fcm-body" style="background:#fff;">
            <input type="hidden" id="pem_id">

            <div class="fcm-section pem-panel">
                <div class="fcm-section-title"><span class="pem-icon"><i class="fas fa-route"></i></span> Pipeline &amp; Documents</div>
                <div class="fcm-grid-4">
                    <div>
                        <label class="fcm-lbl">Stage</label>
                        <select id="pem_stage" class="fcm-select">
                            @foreach(\App\Models\ContractFlightPassenger::stages() as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="fcm-lbl">Category</label>
                        <select id="pem_flight_category_id" class="fcm-select">
                            <option value="">— None —</option>
                            @foreach($categories ?? [] as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="fcm-lbl">Class</label>
                        <select id="pem_ticket_class" class="fcm-select">
                            @foreach(\App\Models\ContractFlightPassenger::TICKET_CLASSES as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="fcm-lbl">Exception flag</label>
                        <select id="pem_exception_flag" class="fcm-select">
                            <option value="">None</option>
                            {{-- Off flight is set only by Cancel from flight / Re-assign. --}}
                            @foreach(\App\Models\ContractFlightPassenger::EXCEPTION_FLAGS as $key => $label)
                                @continue($key === \App\Models\ContractFlightPassenger::OFF_FLIGHT_FLAG)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="fcm-full" style="display:flex;gap:22px;flex-wrap:wrap;align-items:center;margin-top:6px;padding:10px 12px;background:#fff;border:1px solid #e9edf3;border-radius:8px;">
                        <label style="display:flex;align-items:center;gap:6px;font-size:13px;">
                            <input type="checkbox" id="pem_contract_confirmed"> Contract confirmed with passenger
                        </label>
                        <label style="display:flex;align-items:center;gap:6px;font-size:13px;">
                            <input type="checkbox" id="pem_has_bmet"> Has BMET clearance
                        </label>
                        <label style="display:flex;align-items:center;gap:6px;font-size:13px;">
                            <input type="checkbox" id="pem_boarding_training"> Boarding training
                        </label>
                        <label style="display:none;">
                            <input type="checkbox" id="pem_has_manpower_card"> Has manpower card
                        </label>
                    </div>
                    <div id="pem_manpower_fee_wrap" style="grid-column:span 2;max-width:340px;">
                        <label class="fcm-lbl">Manpower card fee <span class="text-gray-400">(only if we arrange the card)</span></label>
                        <input type="number" min="0" step="0.01" id="pem_manpower_card_fee" class="fcm-input" placeholder="0.00">
                    </div>
                </div>
            </div>

            <div class="fcm-section pem-panel">
                <div class="fcm-section-title"><span class="pem-icon"><i class="fas fa-sack-dollar"></i></span> Agent &amp; Money</div>
                <div class="fcm-grid-3">
                    <div>
                        <label class="fcm-lbl">Agent</label>
                        <select id="pem_agent_id" class="fcm-select">
                            <option value="">— None —</option>
                            @foreach($agents ?? [] as $agent)
                                <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="fcm-lbl">Agent price</label>
                        <input type="number" min="0" step="0.01" id="pem_agent_price" class="fcm-input" placeholder="0.00">
                    </div>
                    <div>
                        <label class="fcm-lbl">Agent payment allocated <span class="text-gray-400">(receive it in Party Statement first)</span></label>
                        <input type="number" min="0" step="0.01" id="pem_agent_paid_amount" class="fcm-input" placeholder="0.00">
                    </div>
                    <div>
                        <label class="fcm-lbl">Manpower cost <span class="text-gray-400">(seeded from the flight's rate)</span></label>
                        <input type="number" min="0" step="0.01" id="pem_manpower_cost" class="fcm-input" placeholder="0.00">
                    </div>
                    <div>
                        <label class="fcm-lbl">Boarding cost <span class="text-gray-400">(seeded from the flight's rate)</span></label>
                        <input type="number" min="0" step="0.01" id="pem_boarding_cost" class="fcm-input" placeholder="0.00">
                    </div>
                    <div id="pem_immigration_wrap">
                        <label class="fcm-lbl">Immigration cost <span class="text-gray-400">(immigration-wise flights)</span></label>
                        <input type="number" min="0" step="0.01" id="pem_immigration_cost" class="fcm-input" placeholder="0.00">
                    </div>
                    <div>
                        <label class="fcm-lbl">Reissue cost <span class="text-gray-400">(not part of travel cost)</span></label>
                        <input type="number" min="0" step="0.01" id="pem_reissue_cost" class="fcm-input" placeholder="0.00">
                    </div>
                    <div>
                        <label class="fcm-lbl">Who bears the reissue? <span class="text-gray-400">(if agent, billed on Save)</span></label>
                        <select id="pem_reissue_bearer" class="fcm-select">
                            <option value="">— Not decided —</option>
                            @foreach(\App\Models\ContractFlightPassenger::REISSUE_BEARERS as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <div id="pem_reissue_hint" style="font-size:10.5px;color:#0f766e;margin-top:4px;min-height:13px;"></div>
                    </div>
                    <div>
                        <label class="fcm-lbl">Contact phone</label>
                        <input type="text" id="pem_contact_phone" class="fcm-input" placeholder="+880 1XXX-XXXXXX">
                    </div>
                    <div>
                        <label class="fcm-lbl">Tickets on this route <span class="text-gray-400">(DAC→ECN = 2)</span></label>
                        <select id="pem_tickets_required" class="fcm-input">
                            <option value="1">1 ticket</option><option value="2">2 tickets</option><option value="3">3 tickets</option>
                        </select>
                    </div>
                    <div class="fcm-full">
                        <label class="fcm-lbl">Remarks</label>
                        <textarea id="pem_remarks" rows="2" class="fcm-input" placeholder="Free-form ops note..."></textarea>
                    </div>
                </div>
            </div>

            <div class="fcm-section pem-panel">
                <div class="fcm-section-title">
                    <span class="pem-icon"><i class="fas fa-clock-rotate-left"></i></span> Record a Journey Event
                    <span style="font-weight:400;text-transform:none;letter-spacing:0;color:#9ca3af;margin-left:6px;">(optional — keeps permanent history)</span>
                </div>
                <div class="fcm-grid-2">
                    <div>
                        <select id="pem_journey_event" class="fcm-select">
                            <option value="">— No new event —</option>
                            @foreach(\App\Models\ContractFlightPassenger::EVENT_TYPES as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <input type="datetime-local" id="pem_journey_event_at" class="fcm-input">
                    </div>
                    <div class="fcm-full">
                        <textarea id="pem_journey_event_note" rows="2" class="fcm-input" placeholder="What happened? New flight/time, refund reference, transit issue, etc."></textarea>
                    </div>
                </div>
                <div style="margin-top:12px;border-top:1px solid #e9edf3;padding-top:10px;">
                    <div style="font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#94a3b8;margin-bottom:6px;">Recent history</div>
                    <div id="pem_journey_history" style="display:flex;flex-direction:column;gap:6px;"></div>
                </div>
            </div>

            <div class="fcm-section pem-panel">
                <div class="fcm-section-title"><span class="pem-icon"><i class="fas fa-ticket"></i></span> Linked Tickets (PNR)</div>
                <div id="pem_tickets_list" style="display:flex;flex-direction:column;gap:6px;margin-bottom:8px;"></div>
                {{-- ticket lanes (2026-10-08) --}}
                <style>
                    .cfl-lane{border:1px solid #e2e8f0;border-left:4px solid #94a3b8;border-radius:10px;background:#fff;padding:8px 10px;}
                    .cfl-lane.main{border-left-color:#7c3aed;}
                    .cfl-hd{display:flex;align-items:center;gap:8px;font-size:12.5px;color:#0f172a;}
                    .cfl-n{width:20px;height:20px;border-radius:50%;background:#7c3aed;color:#fff;font-size:11px;font-weight:800;display:inline-flex;align-items:center;justify-content:center;}
                    .cfl-lane:not(.main) .cfl-n{background:#64748b;}
                    .cfl-hd em{font-style:normal;font-size:10px;font-weight:700;color:#7c3aed;background:#f5f3ff;border-radius:999px;padding:1px 8px;}
                    .cfl-live{display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin:7px 0 4px;padding:6px 8px;background:#f0fdfa;border:1px solid #ccfbf1;border-radius:8px;font-size:12px;color:#334155;}
                    .cfl-pnr{font-family:ui-monospace,monospace;font-weight:800;color:#0f766e;}
                    .cfl-s{color:#94a3b8;} .cfl-none{color:#b45309;font-weight:600;}
                    .cfl-acts{margin-left:auto;display:flex;gap:5px;}
                    .cfl-btn{border:1px solid #e2e8f0;background:#fff;border-radius:7px;padding:3px 9px;font-size:11px;font-weight:700;cursor:pointer;}
                    .cfl-btn.t{color:#0f766e;border-color:#99f6e4;} .cfl-btn.r{color:#dc2626;border-color:#fecaca;} .cfl-btn.g{color:#64748b;} .cfl-btn.n{color:#4f46e5;border-color:#c7d2fe;}
                    .cfl-ev{list-style:none;margin:6px 0 0;padding:0 0 0 6px;border-left:2px solid #e2e8f0;display:flex;flex-direction:column;gap:4px;}
                    .cfl-ev li{position:relative;padding-left:16px;font-size:11.5px;display:flex;flex-wrap:wrap;gap:2px 8px;}
                    .cfl-ev li > i{position:absolute;left:-12px;top:1px;width:16px;height:16px;border-radius:50%;background:#fff;font-size:8.5px;display:inline-flex;align-items:center;justify-content:center;border:1.5px solid #cbd5e1;color:#64748b;}
                    .cfl-ev li.t-refund > i{border-color:#fca5a5;color:#dc2626;} .cfl-ev li.t-reissue > i{border-color:#5eead4;color:#0f766e;} .cfl-ev li.t-new > i,.cfl-ev li.t-issued > i{border-color:#c4b5fd;color:#7c3aed;}
                    .cfl-what{color:#0f172a;} .cfl-when{color:#94a3b8;} .cfl-ev small{flex-basis:100%;color:#64748b;}
                    .cfl-move{display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:10px;background:#fffbeb;border:1px solid #fde68a;color:#92400e;font-size:12px;}
                    .cfl-move > i{font-size:16px;} .cfl-move small{display:block;color:#a16207;}
                    .cfl-move-btn{margin-left:auto;background:#0f766e;color:#fff;border-color:#0f766e;}
                    .cfl-opt{display:block;padding:7px 9px;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:6px;cursor:pointer;}
                    /* Cancel return (2026-10-14): the window badge on a live ticket, and its dialogs */
                    .cfl-ret{font-size:11px;font-weight:700;border-radius:999px;padding:1px 9px;border:1px solid #99f6e4;background:#f0fdfa;color:#0f766e;}
                    .cfl-ret.soon{border-color:#fde68a;background:#fffbeb;color:#b45309;} .cfl-ret.late{border-color:#fecaca;background:#fef2f2;color:#b91c1c;}
                    .cfl-ret.none,.cfl-ret.done{border-color:#e2e8f0;background:#f8fafc;color:#64748b;}
                    .crt{text-align:left;font-size:13px;color:#334155;}
                    .crt-hd{margin-bottom:8px;color:#475569;}
                    .crt-win{margin-bottom:10px;padding:7px 10px;border-radius:8px;border:1px solid #99f6e4;background:#f0fdfa;color:#0f766e;font-size:12.5px;}
                    .crt-win.soon{border-color:#fde68a;background:#fffbeb;color:#92400e;} .crt-win.late{border-color:#fecaca;background:#fef2f2;color:#991b1b;}
                    .crt-win.none{border-color:#e2e8f0;background:#f8fafc;color:#64748b;}
                    .crt-g{display:grid;grid-template-columns:150px 1fr;gap:8px;align-items:center;}
                    .crt-in{width:100%;box-sizing:border-box;height:34px;border:1px solid #d2d8e7;border-radius:8px;padding:5px 9px;font-size:13px;}
                    .crt-in[readonly]{background:#fef2f2;color:#b91c1c;font-weight:700;}
                    .crt-m{display:inline-flex;justify-self:start;border:1px solid #cbd5e1;border-radius:999px;padding:2px;background:#f8fafc;}
                    .crt-m button{border:0;background:transparent;border-radius:999px;padding:5px 11px;font-size:11.5px;font-weight:700;color:#64748b;cursor:pointer;white-space:nowrap;}
                    .crt-m button.on{background:#0f766e;color:#fff;}
                    .crt-tick{display:flex;align-items:center;gap:6px;font-size:12.5px;cursor:pointer;}
                    .crt-sum{margin-top:10px;padding:8px 10px;border:1px solid #e2e8f0;border-radius:8px;background:#f8fafc;font-size:12.5px;display:flex;flex-direction:column;gap:3px;}
                    .crt-bad{color:#b91c1c;font-weight:700;}
                </style>
                <div style="display:flex;gap:8px;">
                    <input type="text" id="pem_ticket_search" class="fcm-input" placeholder="Search this passenger's ticket purchases by PNR...">
                    <button type="button" class="fcm-btn fcm-btn-ghost" onclick="searchPassengerTickets()">Search</button>
                </div>
                <div id="pem_ticket_results" style="margin-top:6px;display:flex;flex-direction:column;gap:4px;"></div>
            </div>

            <div class="fcm-section pem-panel">
                <div class="fcm-section-title">
                    <span class="pem-icon" style="background:#b45309;"><i class="fas fa-route"></i></span> Move to Another Flight
                    <span style="font-weight:400;text-transform:none;letter-spacing:0;color:#9ca3af;margin-left:6px;">currently on <b id="pem_current_flight" style="color:#334155;">—</b></span>
                </div>
                @if(\App\Models\ContractFlightPassengerChange::available())
                    {{-- The bare move skipped Re-assign's rules (open flights only,
                         same route, history, stage restart) — Re-assign replaces it. --}}
                    <div style="font-size:11.5px;color:#64748b;">To put him on another flight, use <b>Re-assign</b> on his row — it records the move, its charges and his history.</div>
                @else
                <div style="display:flex;gap:8px;">
                    <input type="text" id="pem_flight_search" class="fcm-input" placeholder="Search flight number to move this passenger to...">
                    <button type="button" class="fcm-btn fcm-btn-ghost" onclick="searchMoveFlights()">Search</button>
                </div>
                <div id="pem_flight_results" style="margin-top:6px;display:flex;flex-direction:column;gap:4px;"></div>
                <div style="font-size:10.5px;color:#94a3b8;margin-top:6px;">Moving does not change the agent charge, reissue billing, or service costs already posted for this passenger — only which flight batch they belong to.</div>
                @endif
                @if(\App\Models\ContractFlightPassengerChange::available())
                    {{-- Not travelling on this flight (documents, visa…): off it and into
                         Cancelled, to be re-assigned later with a charge. --}}
                    <div style="display:flex;align-items:center;gap:10px;margin-top:10px;padding-top:10px;border-top:1px dashed #e2e8f0;">
                        <button type="button" class="fcm-btn" style="background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;padding:6px 12px;font-size:12px;" onclick="if (window.cfpCancelFromEditModal) cfpCancelFromEditModal()"><i class="fas fa-user-xmark"></i> Cancel from this flight</button>
                        <span style="font-size:10.5px;color:#94a3b8;">He moves to Cancelled and his agent charge is voided until he is re-assigned to another flight.</span>
                    </div>
                @endif
            </div>

            {{-- His moves between flights, with the charge of each — filled by
                 renderFlightChanges() (reassign-script); hidden when empty. --}}
            <div class="fcm-section pem-panel" id="pem_changes_panel" style="display:none;">
                <div class="fcm-section-title"><span class="pem-icon" style="background:#b91c1c;"><i class="fas fa-shuffle"></i></span> Flight changes</div>
                <div id="pem_changes_list" style="display:flex;flex-direction:column;gap:6px;"></div>
            </div>

            <div class="pem-totals">
                <div class="pem-total-card" style="background:#f0fdfa;border:1px solid #99f6e4;">
                    <span style="font-size:12.5px;color:#0f766e;font-weight:600;">Total travel cost <span style="font-weight:400;color:#5eead4;">(ticket + manpower + boarding + immigration + card fee)</span></span>
                    <span id="pem_total_travel_cost" style="font-size:16px;font-weight:700;color:#0f766e;white-space:nowrap;">৳0.00</span>
                </div>
                <div class="pem-total-card" style="background:#fffbeb;border:1px solid #fde68a;">
                    <span style="font-size:12.5px;color:#92400e;font-weight:600;">Profit on this passenger <span style="font-weight:400;color:#d97706;">(agent price − travel cost)</span></span>
                    <span id="pem_profit" style="font-size:16px;font-weight:700;color:#92400e;white-space:nowrap;">৳0.00</span>
                </div>
            </div>

            <div id="pem_error" class="hidden" style="margin-top:12px;color:#dc2626;font-size:12.5px;"></div>
        </div>
        <div class="fcm-foot">
            <button type="button" class="fcm-btn fcm-btn-ghost" onclick="closePassengerEditModal()">Cancel</button>
            <button type="button" id="pem_save" class="fcm-btn fcm-btn-save" onclick="savePassengerEdit()"><i class="fas fa-save"></i> Save</button>
        </div>
    </div>
</div>
