{{-- Via on a ticket route (owner, 2026-09-21). The Via box sits beside
     From / To. Empty = direct route, nothing else changes. With a via
     (From DAC, To ECN, via IST) the route is two tickets, and two leg
     blocks appear — DAC → IST and IST → ECN, each with its own Airline,
     Vendor, Portal and Default Price — with the total DAC-IST-ECN cost
     underneath. The route's own Airline / Vendor / Portal / Price boxes
     are hidden then (the server mirrors leg 1 into them, price = sum).

     $prefix is 'create' or 'edit'. $airports, $airlines, $vendors,
     $allPortals are the modal's own lists. --}}
@once
<style>
.tk-via-legs{grid-column:1 / -1;display:none;flex-direction:column;gap:12px;margin-top:4px;}
.tk-via-legs.is-on{display:flex;}
.tk-leg-card{position:relative;border:1px solid #e4e1f7;border-radius:14px;background:#fff;padding:14px 16px 16px 20px;box-shadow:0 1px 2px rgba(15,23,42,.04);}
.tk-leg-card::before{content:'';position:absolute;left:0;top:14px;bottom:14px;width:4px;border-radius:0 4px 4px 0;background:linear-gradient(180deg,#7c3aed,#a78bfa);}
.tk-leg-card-hd{display:flex;align-items:center;gap:10px;margin-bottom:12px;font-size:12px;font-weight:700;color:#4c1d95;}
.tk-leg-card-hd .n{width:26px;height:26px;border-radius:8px;background:linear-gradient(120deg,#6d28d9,#8b5cf6);color:#fff;font-size:12px;display:inline-flex;align-items:center;justify-content:center;box-shadow:0 4px 10px rgba(109,40,217,.25);}
.tk-leg-card-hd .sector{font-size:15px;letter-spacing:.4px;display:inline-flex;align-items:center;gap:8px;color:#1e1b4b;}
.tk-leg-card-hd .sector .arrow{color:#a78bfa;font-size:12px;}
.tk-flag{width:20px;height:14px;border-radius:3px;object-fit:cover;box-shadow:0 0 0 1px rgba(15,23,42,.12);vertical-align:middle;}
.tk-via-total .tk-flag{margin:0 3px;}
.tk-leg-card-hd small{color:#94a3b8;font-weight:500;margin-left:auto;font-size:11px;letter-spacing:.3px;text-transform:uppercase;}
.tk-leg-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px 14px;}
@media(max-width:760px){.tk-leg-grid{grid-template-columns:1fr 1fr;}}
.tk-via-total{display:flex;align-items:center;gap:12px;border-radius:14px;background:linear-gradient(120deg,#0f766e,#0d9488);padding:14px 18px;font-size:13px;color:#ecfeff;box-shadow:0 10px 24px rgba(13,148,136,.25);}
.tk-via-total i.fa-coins{width:30px;height:30px;border-radius:9px;background:rgba(255,255,255,.16);display:inline-flex;align-items:center;justify-content:center;}
.tk-via-total .lbl{font-size:10.5px;letter-spacing:1.2px;text-transform:uppercase;color:rgba(236,254,255,.75);display:block;}
.tk-via-total .route{font-size:14px;font-weight:700;display:inline-flex;align-items:center;gap:6px;}
.tk-via-total b{margin-left:auto;font-size:22px;font-weight:800;letter-spacing:-.3px;}
.tk-create-grid .tk-direct-hide{display:none !important;}
.tk-leg-cards{display:flex;flex-direction:column;gap:12px;}
.tk-stops{display:flex;flex-direction:column;gap:8px;margin-bottom:8px;}
.tk-stop{display:grid;grid-template-columns:26px minmax(0,1fr) auto 32px;gap:8px;align-items:center;}
.tk-stop .k{width:26px;height:26px;border-radius:8px;background:#f5f3ff;color:#6d28d9;font-size:11px;font-weight:800;display:inline-flex;align-items:center;justify-content:center;}
.tk-stop-kind{display:inline-flex;border:1px solid #e4e1f7;border-radius:9px;overflow:hidden;}
.tk-stop-kind button{border:0;background:#fff;padding:7px 11px;font-size:11.5px;font-weight:700;color:#64748b;cursor:pointer;white-space:nowrap;}
.tk-stop-kind button.on.new{background:#6d28d9;color:#fff;} .tk-stop-kind button.on.transit{background:#0891b2;color:#fff;}
.tk-stop-kind.tk-wait{visibility:hidden;}
.tk-stop-del{height:32px;border:1px solid #fecaca;border-radius:8px;background:#fef2f2;color:#dc2626;cursor:pointer;}
.tk-stop-add{border:1px dashed #c4b5fd;background:#faf5ff;color:#6d28d9;border-radius:9px;padding:7px 12px;font-size:12px;font-weight:700;cursor:pointer;}
.tk-leg-card-hd .sector .transit{font-size:10px;font-weight:700;color:#0891b2;background:#ecfeff;border-radius:999px;padding:1px 7px;letter-spacing:.02em;}
</style>
@endonce

<div class="tk-via-legs" id="{{ $prefix }}_via_legs">
    <div class="tk-leg-cards" id="{{ $prefix }}_leg_cards"></div>
    <div class="tk-via-total">
        <i class="fas fa-coins"></i>
        <div><span class="lbl" id="{{ $prefix }}_via_lbl">Total cost, all tickets</span><span class="route" id="{{ $prefix }}_via_title">—</span></div>
        <b id="{{ $prefix }}_via_total">৳0</b>
    </div>
</div>
{{-- one ticket card; the script clones it per ticket and turns __I__ into its index --}}
<template id="{{ $prefix }}_leg_tpl">
    <div class="tk-leg-card" data-leg="__I__">
        <div class="tk-leg-card-hd">
            <span class="n">__N__</span>
            <span class="sector" id="{{ $prefix }}_leg__I___sector">— → —</span>
            <small>Ticket __N__ · own airline, seller &amp; price</small>
        </div>
        <div class="tk-leg-grid">
            <div>
                <label class="tk-lbl">Airline</label>
                <select id="{{ $prefix }}_leg__I___airline" name="legs[__I__][airline_id]" class="tk-leg-sel tk-leg-field" style="width:100%">
                    <option value="">— Select airline —</option>
                    @foreach ($airlines as $airline)<option value="{{ $airline->id }}">{{ $airline->name }}</option>@endforeach
                </select>
            </div>
            <div class="tk-leg-seller">
                <label class="tk-lbl">Vendor</label>
                <select id="{{ $prefix }}_leg__I___vendor" name="legs[__I__][vendor_id]" class="tk-leg-sel tk-leg-field" style="width:100%">
                    <option value="">— Select vendor —</option>
                    @foreach ($vendors as $vendor)<option value="{{ $vendor->id }}">{{ $vendor->name }}</option>@endforeach
                </select>
            </div>
            <div class="tk-leg-seller">
                <label class="tk-lbl">Portal</label>
                <select id="{{ $prefix }}_leg__I___portal" name="legs[__I__][portal_id]" class="tk-leg-sel tk-leg-field" style="width:100%">
                    <option value="">— Select portal —</option>
                    @foreach ($allPortals ?? $portals ?? [] as $portal)<option value="{{ $portal->id }}">{{ $portal->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="tk-lbl">Default Cost Price</label>
                <input type="number" id="{{ $prefix }}_leg__I___cost" name="legs[__I__][cost]" min="0" step="0.01" class="tk-input tk-leg-cost" placeholder="0.00">
            </div>
        </div>
    </div>
</template>
