{{-- Suspend withdraw (owner, 2026-11-02) — posts through its own ContractFlightSuspendWithdrawController.
     Laid out exactly like Reschedule / Reissue's "Charge for the change": ONE card per ticket, its fields
     inside it, Who pays last (owner: "Reschedule / Reissue is the standard"). There is one set of fields;
     partials.suspend-withdraw-script moves it into the ticket that is ticked and dims the others. UI only. --}}
<style>
    #cfpReassignOverlay #cfr_kinds_main.has-4{grid-template-columns:repeat(4,minmax(0,1fr));}
    #cfpReassignOverlay .cfr-kind:has(input[value="suspend_withdraw"]:checked){border-color:#b45309;background:#fffbeb;box-shadow:0 0 0 2px #b4530922;}
    /* Same columns as .cfr-tc (reassign-script): info · cost block · 120 + 125 (vendor spans both) · 105 · 105 · Who pays */
    #cfpReassignOverlay .cfsw-card{display:grid;grid-template-columns:minmax(0,1fr) 150px 120px 125px 105px 105px auto;gap:16px;align-items:center;border:1px solid #e2e8f0;border-left:4px solid #0f766e;border-radius:10px;padding:12px 16px;background:#fff;}
    #cfpReassignOverlay .cfsw-card.is-off{opacity:.55;border-left-color:#cbd5e1;background:#f8fafc;cursor:pointer;}
    #cfpReassignOverlay .cfsw-card .cfr-tk-route{margin-bottom:6px;}
    #cfpReassignOverlay .cfsw-on{display:flex;align-items:center;gap:5px;margin-top:8px;font-size:10.5px;font-weight:700;color:#0f766e;cursor:pointer;user-select:none;}
    #cfpReassignOverlay .cfsw-card.is-off .cfsw-on{color:#64748b;}
    #cfpReassignOverlay .cfsw-cb{font-size:11px;color:#334155;line-height:1.75;border-left:1px dashed #cbd5e1;padding-left:14px;}
    #cfpReassignOverlay .cfsw-cb div{display:flex;justify-content:space-between;gap:14px;}
    #cfpReassignOverlay .cfsw-slot,#cfpReassignOverlay .cfsw-slot #cfsw_fields{display:contents;}
    #cfpReassignOverlay .cfsw-vendor{grid-column:span 2;min-width:0;}
    #cfpReassignOverlay #cfsw_fields select,#cfpReassignOverlay #cfsw_fields input{width:100%;box-sizing:border-box;}
    #cfpReassignOverlay #cfsw_park{display:none;}
    /* Empty cells hold the columns in a dimmed card, so its cost block sits where the ticked one's does. */
    #cfpReassignOverlay .cfsw-card:not(.is-off) .cfsw-ph{display:none;}
    #cfpReassignOverlay .cfsw-ph.wide{grid-column:span 2;}
    #cfpReassignOverlay .cfsw-ph.who{width:200px;}
    /* ৳ prefix as in Reschedule's money boxes */
    #cfpReassignOverlay .cfsw-money{position:relative;}
    #cfpReassignOverlay .cfsw-money span{position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#94a3b8;font-weight:700;font-size:13px;}
    #cfpReassignOverlay .cfsw-money input{padding:7px 9px 7px 24px;font-size:13px;font-weight:700;border-radius:8px;}
    @media (max-width: 900px){
        #cfpReassignOverlay #cfr_kinds_main.has-4,#cfpReassignOverlay .cfsw-card{grid-template-columns:1fr;}
        #cfpReassignOverlay .cfsw-vendor{grid-column:auto;}
    }
</style>
<div class="cfr-sec" id="cfsw_sec" style="display:none;">
    <div class="cfr-h">Suspend withdraw — per ticket</div>
    {{-- The value posted; set by ticking a ticket card. --}}
    <select id="cfsw_ticket" hidden aria-hidden="true"></select>
    <div class="cfr-cards" id="cfsw_tickets"></div>
    <div id="cfsw_park">
        <div id="cfsw_fields">
            <div class="cfsw-vendor">
                <span class="cfr-lbl">Withdrawn by (vendor)</span>
                <select id="cfsw_vendor" class="fcm-select">
                    <option value="">Vendor — choose</option>
                    @foreach(\App\Models\ContractFlightSuspendWithdrawal::vendors() as $v)
                        <option value="{{ $v['id'] }}">{{ $v['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <span class="cfr-lbl">Vendor's cost</span>
                <div class="cfsw-money"><span>৳</span><input type="number" id="cfsw_cost" class="fcm-input" min="0" step="0.01" placeholder="0 — none" style="color:#dc2626;"></div>
            </div>
            <div>
                <span class="cfr-lbl">Our charge</span>
                <div class="cfsw-money"><span>৳</span><input type="number" id="cfsw_charge" class="fcm-input" min="0" step="0.01" placeholder="0 — none" style="color:#dc2626;"></div>
            </div>
            <div>
                <span class="cfr-lbl">Who pays</span>
                <select id="cfsw_bearer" class="fcm-select cfr-sw-src">
                    <option value="">Who bears — choose</option>
                    @foreach(\App\Models\ContractFlightSuspendWithdrawal::BEARERS as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
                <div id="cfsw_split" style="display:none;margin-top:6px;font-size:11px"><label>Agent bears<input type="number" id="cfsw_agent_amount" min="0" step="0.01" class="fcm-input" style="color:#dc2626"></label><div id="cfsw_ours" style="margin-top:4px;color:#b45309"></div></div>
            </div>
        </div>
    </div>
    <div class="cfr-card-total" id="cfsw_total" style="margin-top:8px;"></div>
    <input type="text" id="cfsw_note" class="fcm-input" maxlength="2000" placeholder="Note (optional)" style="width:100%;box-sizing:border-box;margin-top:8px;">
</div>
