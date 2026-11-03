{{-- The Invoice window (owner, 2026-10-14: "make a big modal, then switcher,
     all happens in that modal"). Opened by the party card's Invoice button
     (openInvoiceWindow in partials/scripts):
       Sales invoice   — the party's sales listed, ticked, totalled, previewed
                         as the PDF and opened / downloaded, without leaving it;
       New invoice     — a service we list nowhere else (the old Create Invoice:
                         same fields, same endpoint), then straight back to the
                         Sales tab with the new charge ticked.
     One fixed frame for both tabs, near the full window; closes only on ✕;
     one border colour (#d2d8e7) inside. Included from partials/modals, so
     $countries is the same list the old Create Invoice form uses. --}}
@php
    // ⚙ (logo + signature): Company Settings' own permission; the company the invoice is printed for.
    $ivwUser = auth()->user();
    $ivwCanBrand = \Illuminate\Support\Facades\Route::has('role.party-statement.invoice-branding')
        && $ivwUser && ($ivwUser->is_super_admin || $ivwUser->can('edit company setting'));
    $ivwCo = $ivwCanBrand ? \App\Models\Company::find($ivwUser->is_super_admin ? (request()->integer('company') ?: 2) : ($ivwUser->company_id ?: 2)) : null;
    $ivwImg = fn ($path) => $path && is_file(public_path($path)) ? asset($path) : null;
@endphp
<style>
    .ivw-overlay { position: fixed; inset: 0; z-index: 60; background: rgba(15, 23, 42, .55); backdrop-filter: blur(2px); }
    .ivw-overlay[hidden] { display: none; }
    .ivw-frame { position: absolute; top: 12px; left: 50%; transform: translateX(-50%); width: min(1280px, calc(100vw - 32px)); height: calc(100vh - 24px);
        background: #fff; border-radius: 14px; box-shadow: 0 24px 60px rgba(15, 23, 42, .35); display: flex; flex-direction: column; overflow: hidden; }
    .ivw-head { display: flex; align-items: center; gap: 14px; padding: 16px 22px; border-bottom: 1px solid #d2d8e7; }
    .ivw-ic { width: 40px; height: 40px; border-radius: 10px; background: #eef2ff; color: #4f46e5; display: flex; align-items: center; justify-content: center; font-size: 17px; flex: none; }
    .ivw-title { font-size: 17px; font-weight: 800; color: #1e293b; line-height: 1.2; }
    .ivw-sub { font-size: 12px; color: #64748b; margin-top: 2px; }
    .ivw-sub b { color: #3730a3; font-weight: 700; }
    .ivw-switch { display: inline-flex; margin-left: auto; border: 1px solid #d2d8e7; border-radius: 10px; padding: 3px; gap: 3px; background: #f8fafc; }
    .ivw-switch button { border: 0; background: transparent; padding: 8px 16px; border-radius: 7px; font-size: 13px; font-weight: 700; color: #475569; cursor: pointer; display: inline-flex; gap: 7px; align-items: center; }
    .ivw-switch button.is-on { background: #4f46e5; color: #fff; box-shadow: 0 2px 6px rgba(79, 70, 229, .3); }
    .ivw-x { width: 36px; height: 36px; border-radius: 9px; border: 1px solid #d2d8e7; background: #fff; color: #64748b; cursor: pointer; font-size: 15px; flex: none; }
    .ivw-x:hover { color: #dc2626; border-color: #fca5a5; }
    .ivw-gear:hover, .ivw-gear.is-on { color: #4f46e5; border-color: #7c3aed; background: #f5f3ff; }

    /* ⚙ Invoice settings — logo and signature */
    .ivw-brand { flex: 1; min-width: 0; overflow: auto; display: grid; grid-template-columns: 1fr 1fr; gap: 18px; align-content: start; }
    .ivw-bcard { border: 1px solid #d2d8e7; border-radius: 12px; padding: 18px 20px; display: flex; flex-direction: column; gap: 14px; }
    .ivw-bcard h4 { font-size: 13px; font-weight: 800; color: #1e293b; margin: 0; display: flex; gap: 8px; align-items: center; }
    .ivw-bcard p { font-size: 12px; color: #64748b; margin: 0; line-height: 1.55; }
    .ivw-bprev { height: 150px; border: 1px solid #d2d8e7; border-radius: 10px; display: flex; align-items: center; justify-content: center; background: #eef0fa; overflow: hidden; }
    .ivw-bprev img { max-width: 80%; max-height: 120px; object-fit: contain; background: #fff; }
    .ivw-bprev.sig { background: #fff; flex-direction: column; justify-content: flex-end; padding-bottom: 14px; }
    .ivw-bprev.sig img { max-height: 70px; max-width: 70%; }
    .ivw-bprev.sig .line { width: 200px; border-top: 1px solid #334155; margin-top: 4px; padding-top: 3px; text-align: center; font-size: 10.5px; font-weight: 700; color: #1e293b; }
    .ivw-bprev .none { font-size: 12px; color: #94a3b8; }
    .ivw-brow { display: flex; gap: 8px; flex-wrap: wrap; }
    .ivw-file { position: relative; overflow: hidden; }
    .ivw-file input { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
    .ivw-picked { font-size: 11.5px; color: #4f46e5; font-weight: 600; }

    .ivw-body { flex: 1; min-height: 0; display: flex; gap: 18px; padding: 16px 22px 18px; }
    .ivw-body[hidden] { display: none; }
    .ivw-main { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 12px; }
    .ivw-side { width: 330px; flex: none; display: flex; flex-direction: column; border: 1px solid #d2d8e7; border-radius: 12px; overflow: hidden; }
    .ivw-side-h { padding: 12px 16px; border-bottom: 1px solid #d2d8e7; font-size: 12px; font-weight: 800; color: #1e293b; text-transform: uppercase; letter-spacing: .05em; display: flex; justify-content: space-between; }
    .ivw-side-h span { color: #64748b; font-weight: 600; text-transform: none; letter-spacing: 0; }
    .ivw-side-list { flex: 1; min-height: 0; overflow: auto; padding: 6px 0; }
    .ivw-side-row { display: flex; gap: 10px; padding: 7px 16px; font-size: 12px; align-items: baseline; }
    .ivw-side-row .r { color: #3730a3; font-weight: 700; flex: none; }
    .ivw-side-row .n { flex: 1; min-width: 0; color: #334155; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .ivw-side-row .a { flex: none; font-weight: 700; color: #111827; font-variant-numeric: tabular-nums; }
    .ivw-side-row .a.cr { color: #059669; }
    .ivw-empty { padding: 28px 16px; text-align: center; color: #94a3b8; font-size: 12.5px; line-height: 1.6; }
    .ivw-totals { border-top: 1px solid #d2d8e7; padding: 10px 16px; background: #f8fafc; }
    .ivw-totals div { display: flex; justify-content: space-between; font-size: 12.5px; padding: 4px 0; color: #475569; font-variant-numeric: tabular-nums; }
    .ivw-totals .cr { color: #059669; }
    .ivw-totals .grand { border-top: 1px solid #d2d8e7; margin-top: 4px; padding-top: 8px; font-size: 15px; font-weight: 800; color: #3730a3; }
    .ivw-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; padding: 12px 16px 16px; }
    .ivw-actions .wide { grid-column: 1 / -1; }
    .ivw-btn { display: inline-flex; align-items: center; justify-content: center; gap: 7px; padding: 10px 12px; border-radius: 9px; border: 1px solid #d2d8e7; background: #fff; color: #334155; font-size: 13px; font-weight: 700; cursor: pointer; text-decoration: none; }
    .ivw-btn:hover { border-color: #7c3aed; color: #4f46e5; }
    .ivw-btn.pri { background: #4f46e5; border-color: #4f46e5; color: #fff; }
    .ivw-btn.pri:hover { background: #4338ca; color: #fff; }
    .ivw-btn[disabled], .ivw-btn.is-off { opacity: .45; pointer-events: none; }

    /* the sales list — same row height for every row */
    /* the page's own filter bar, kept to ONE row inside the window: the search gives way first */
    #ivwModal .pb-bar { flex-wrap: nowrap; }
    #ivwModal .pb-search { flex: 1 1 150px; min-width: 120px; }
    #ivwModal .pb-count { flex: none; white-space: nowrap; }
    .ivw-table-wrap { flex: 1; min-height: 0; overflow: auto; border: 1px solid #d2d8e7; border-radius: 12px; }
    .ivw-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    .ivw-table th { position: sticky; top: 0; z-index: 1; background: #f8fafc; color: #64748b; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; text-align: left; padding: 10px 12px; border-bottom: 1px solid #d2d8e7; }
    .ivw-table th.r, .ivw-table td.r { text-align: right; }
    .ivw-table td { height: 58px; padding: 0 12px; border-bottom: 1px solid #d2d8e7; vertical-align: middle; overflow: hidden; }
    .ivw-table tbody tr { cursor: pointer; }
    .ivw-table tbody tr:hover td { background: #f8f7ff; }
    .ivw-table tbody tr.is-on td { background: #eef2ff; }
    .ivw-table .p { display: block; font-size: 13px; color: #111827; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .ivw-table .s { display: block; font-size: 11.5px; color: #64748b; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .ivw-table .ref { color: #3730a3; }
    .ivw-table .flag { color: #dc2626; }
    .ivw-table .pnr { font-weight: 700; color: #713f12; background: #fde047; padding: 0 4px; border-radius: 3px; }
    .ivw-table .cr { color: #059669; }
    .ivw-table input[type=checkbox] { width: 16px; height: 16px; accent-color: #4f46e5; cursor: pointer; }
    .ivw-preview { flex: 1; min-height: 0; border: 1px solid #d2d8e7; border-radius: 12px; overflow: hidden; display: flex; flex-direction: column; }
    .ivw-preview[hidden] { display: none; }
    .ivw-preview-bar { display: flex; align-items: center; gap: 10px; padding: 8px 12px; border-bottom: 1px solid #d2d8e7; background: #f8fafc; font-size: 12.5px; color: #475569; }
    .ivw-preview iframe { flex: 1; width: 100%; border: 0; background: #e2e8f0; }

    /* New invoice for another service — roomy form */
    .ivw-form { flex: 1; min-width: 0; overflow: auto; border: 1px solid #d2d8e7; border-radius: 12px; padding: 22px 24px; display: grid; grid-template-columns: 1fr 1fr; gap: 18px 22px; align-content: start; }
    .ivw-form .full { grid-column: 1 / -1; }
    .ivw-form label { display: block; font-size: 11.5px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 7px; }
    .ivw-form label i { color: #dc2626; font-style: normal; }
    .ivw-form input, .ivw-form select, .ivw-form textarea { width: 100%; box-sizing: border-box; padding: 11px 14px; font-size: 14px; color: #1e293b; border: 1px solid #d2d8e7; border-radius: 10px; background: #fff; font-family: inherit; }
    .ivw-form input:focus, .ivw-form select:focus, .ivw-form textarea:focus { outline: none; border-color: #7c3aed; box-shadow: 0 0 0 3px rgba(124, 58, 237, .14); }
    .ivw-form .ro { padding: 11px 14px; border: 1px solid #d2d8e7; border-radius: 10px; background: #f8fafc; font-size: 14px; font-weight: 600; color: #334155; }
    .ivw-form textarea { min-height: 92px; resize: vertical; }
    .ivw-date { position: relative; }
    .ivw-date input[type=text] { padding-right: 46px; }
    .ivw-date input.is-bad { border-color: #fca5a5; background: #fef2f2; color: #b91c1c; }
    .ivw-form .ivw-date input.pb-date-native { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); width: 26px; height: 26px; padding: 0; margin: 0; border: 0; background: none; color: transparent; overflow: hidden; cursor: pointer; box-shadow: none; }
    .ivw-date .pb-date-native::-webkit-calendar-picker-indicator { width: 26px; height: 26px; margin: 0; padding: 0; opacity: .55; cursor: pointer; }
    .ivw-time-field { width: min(100%, 320px); justify-self: end; }
    .ivw-time { display: flex; align-items: center; gap: 6px; width: 100%; }
    .ivw-form .ivw-time select { width: auto; height: 44px; padding: 0 30px 0 12px; background-color: #fff; }
    .ivw-time .hour, .ivw-time .minute { flex: 1 1 0; min-width: 0; }
    .ivw-time .period { flex: 1 1 0; min-width: 0; }
    .ivw-time .sep { flex: 0 0 auto; color: #94a3b8; font-size: 16px; font-weight: 800; text-align: center; }
    .ivw-time-help { display: block; margin-top: 6px; color: #94a3b8; font-size: 11px; line-height: 1.35; }
    @media (max-width: 640px) { .ivw-time-field { width: 100%; justify-self: stretch; } .ivw-time .hour, .ivw-time .minute { flex: 1 1 0; } }
    .ivw-post { padding: 12px 16px; display: flex; flex-direction: column; gap: 10px; flex: 1; overflow: auto; }
    .ivw-post-row { border: 1px solid #d2d8e7; border-radius: 10px; padding: 10px 12px; font-size: 12.5px; color: #334155; }
    .ivw-post-row b { display: block; color: #1e293b; margin-bottom: 3px; }
    .ivw-post-row .amt { float: right; font-weight: 800; font-variant-numeric: tabular-nums; }
    .ivw-post-row .dr { color: #dc2626; } .ivw-post-row .crr { color: #059669; }
</style>

<div class="ivw-overlay" id="ivwModal" hidden role="dialog" aria-modal="true" aria-labelledby="ivwTitle">
    <div class="ivw-frame">
        <div class="ivw-head">
            <span class="ivw-ic"><i class="fas fa-file-invoice"></i></span>
            <div style="min-width:0">
                <div class="ivw-title" id="ivwTitle">Invoice</div>
                <div class="ivw-sub"><b id="ivwParty">—</b> · <span id="ivwCode">—</span></div>
            </div>
            <div class="ivw-switch" role="tablist">
                <button type="button" data-tab="sales" class="is-on" role="tab"><i class="fas fa-file-invoice"></i> Sales invoice</button>
                <button type="button" data-tab="new" role="tab"><i class="fas fa-file-circle-plus"></i> New invoice for another service</button>
            </div>
            @if($ivwCanBrand)<button type="button" class="ivw-x ivw-gear" id="ivwGear" title="Invoice settings — logo and signature"><i class="fas fa-gear"></i></button>@endif
            <button type="button" class="ivw-x" onclick="closeInvoiceWindow()" title="Close"><i class="fas fa-xmark"></i></button>
        </div>

        {{-- ── Sales invoice ───────────────────────────────────────────── --}}
        <div class="ivw-body" data-pane="sales">
            <div class="ivw-main">
                @include('party-statement.partials.table-bar', [
                    'prefix'      => 'iv',
                    'placeholder' => 'Search ref, passenger, passport, PNR…',
                    'segments'    => [['week', 'Last Week'], ['month', 'This Month'], ['year', 'This Year'], ['all', 'All Time']],
                    'dates'       => true,
                    'print'       => false,
                ])
                <div class="ivw-table-wrap" id="ivwListWrap">
                    <table class="ivw-table">
                        <colgroup><col style="width:46px"><col style="width:150px"><col style="width:170px"><col><col style="width:150px"></colgroup>
                        <thead><tr>
                            <th><input type="checkbox" id="ivwAll" title="Tick every sale shown"></th>
                            <th>Ref / Date</th><th>Item</th><th>Passenger / Details</th><th class="r">Amount</th>
                        </tr></thead>
                        <tbody id="ivwRows"><tr><td colspan="5" class="ivw-empty">Loading sales…</td></tr></tbody>
                    </table>
                </div>
                <div class="ivw-preview" id="ivwPreview" hidden>
                    <div class="ivw-preview-bar">
                        <button type="button" class="ivw-btn" onclick="ivwShowList()"><i class="fas fa-arrow-left"></i> Back to sales</button>
                        <span id="ivwPreviewNote">The invoice exactly as the agent receives it.</span>
                    </div>
                    <iframe id="ivwFrame" title="Invoice preview"></iframe>
                </div>
            </div>
            <aside class="ivw-side">
                <div class="ivw-side-h">On this invoice <span id="ivwSelN">0 sales</span></div>
                <div class="ivw-side-list" id="ivwSelList"><div class="ivw-empty">Tick the sales to put on the invoice — one, several or all of them.</div></div>
                <div class="ivw-totals">
                    <div><span>Charges</span><span id="ivwCharges">0.00</span></div>
                    <div class="cr" id="ivwCreditsRow" hidden><span>Less credits</span><span id="ivwCredits">0.00</span></div>
                    <div class="grand"><span>Invoice total</span><span id="ivwTotal">৳ 0.00</span></div>
                </div>
                <div class="ivw-actions">
                    <button type="button" class="ivw-btn pri wide" id="ivwPreviewBtn" onclick="ivwPreview()"><i class="fas fa-eye"></i> Preview invoice</button>
                    <a class="ivw-btn" id="ivwOpen" target="_blank" rel="noopener" data-no-prefetch><i class="fas fa-up-right-from-square"></i> Open / Print</a>
                    <a class="ivw-btn" id="ivwDownload" data-no-prefetch><i class="fas fa-download"></i> Download</a>
                </div>
            </aside>
        </div>

        {{-- ── New invoice for another service ─────────────────────────── --}}
        <div class="ivw-body" data-pane="new" hidden>
            <div class="ivw-form">
                <div><label>Bill to</label><div class="ro" id="ivwNewParty">—</div></div>
                <div class="ivw-time-field"><label for="ivwTimeHour">Custom time <i>*</i></label>
                    <div class="ivw-time">
                        <select id="ivwTimeHour" class="hour" aria-label="Invoice hour">@for($hour = 1; $hour <= 12; $hour++)<option value="{{ $hour }}">{{ str_pad((string) $hour, 2, '0', STR_PAD_LEFT) }}</option>@endfor</select>
                        <span class="sep">:</span>
                        <select id="ivwTimeMinute" class="minute" aria-label="Invoice minute">@for($minute = 0; $minute < 60; $minute++)<option value="{{ str_pad((string) $minute, 2, '0', STR_PAD_LEFT) }}">{{ str_pad((string) $minute, 2, '0', STR_PAD_LEFT) }}</option>@endfor</select>
                        <select id="ivwTimePeriod" class="period" aria-label="Invoice time period"><option value="AM">AM</option><option value="PM">PM</option></select>
                    </div>
                    <small class="ivw-time-help">Required transaction time; this is the time shown in the ledger.</small>
                </div>
                <div><label for="ivwAmount">Amount (BDT) <i>*</i></label><input type="number" id="ivwAmount" min="0.01" step="0.01" placeholder="0"></div>
                <div><label for="ivwDueText">Due date</label>
                    {{-- dd/mm/yyyy typed, with the calendar beside it — the filter bars' date control --}}
                    <div class="ivw-date"><input type="text" id="ivwDueText" placeholder="dd/mm/yyyy" inputmode="numeric" maxlength="10" autocomplete="off"><input type="date" id="ivwDue" class="pb-date-native" title="Pick a date" aria-label="Pick the due date"></div></div>
                <div class="full"><label for="ivwDesc">Service description <i>*</i></label><textarea id="ivwDesc" rows="3" placeholder="Describe the service being invoiced" autocomplete="off"></textarea></div>
            </div>
            <aside class="ivw-side">
                <div class="ivw-side-h">What will be posted</div>
                <div class="ivw-post">
                    <div class="ivw-post-row"><span class="amt dr" id="ivwPostDr">৳ 0.00</span><b>Debit · <span id="ivwPostParty">—</span></b>Charged to the party's ledger — owed to us</div>
                    <div class="ivw-post-row"><span class="amt crr" id="ivwPostCr">৳ 0.00</span><b>Credit · Service charge income</b>Our income for this service</div>
                    <div class="ivw-post-row" style="color:#64748b">After it is created the window returns to <b style="display:inline">Sales invoice</b> with this charge ticked, ready to preview and give to the party.</div>
                </div>
                <div class="ivw-totals"><div class="grand"><span>Invoice total</span><span id="ivwNewTotal">৳ 0.00</span></div></div>
                <div class="ivw-actions"><button type="button" class="ivw-btn pri wide" id="ivwCreate" onclick="ivwCreate()"><i class="fas fa-check"></i> Create invoice</button></div>
            </aside>
        </div>

        @if($ivwCanBrand)
        {{-- ── ⚙ Invoice settings ────────────────────────────────────────── --}}
        <div class="ivw-body" data-pane="settings" hidden>
            <div class="ivw-brand">
                <div class="ivw-bcard">
                    <h4><i class="fas fa-image" style="color:#4f46e5"></i> Logo</h4>
                    <div class="ivw-bprev" id="ivwLogoPrev">@if($ivwImg($ivwCo?->logo))<img src="{{ $ivwImg($ivwCo->logo) }}" alt="Logo">@else<span class="none">No logo set — the EPAL logo is used</span>@endif</div>
                    <div class="ivw-brow">
                        <label class="ivw-btn ivw-file"><i class="fas fa-upload"></i> Choose new logo<input type="file" id="ivwLogoFile" accept="image/png,image/jpeg,image/webp"></label>
                        <span class="ivw-picked" id="ivwLogoPicked"></span>
                    </div>
                    <p>PNG or JPG, up to 4 MB. White margin around it is trimmed on the invoice, so the mark fills the space.</p>
                </div>
                <div class="ivw-bcard">
                    <h4><i class="fas fa-signature" style="color:#4f46e5"></i> Signature</h4>
                    <div class="ivw-bprev sig" id="ivwSigPrev">@if($ivwImg($ivwCo?->signature))<img src="{{ $ivwImg($ivwCo->signature) }}" alt="Signature">@else<span class="none" style="margin-bottom:auto;margin-top:auto">No signature yet — the line is left blank to sign by hand</span>@endif<div class="line">Authorized Signatory</div></div>
                    <div class="ivw-brow">
                        <label class="ivw-btn ivw-file"><i class="fas fa-upload"></i> Choose signature<input type="file" id="ivwSigFile" accept="image/png,image/jpeg,image/webp"></label>
                        <button type="button" class="ivw-btn" id="ivwSigRemove" @if(! $ivwImg($ivwCo?->signature)) style="display:none" @endif><i class="fas fa-trash-can"></i> Remove</button>
                        <span class="ivw-picked" id="ivwSigPicked"></span>
                    </div>
                    <p>A scan or photo of the signature on white paper (PNG with a clear background looks best). It sits on the line above "Authorized Signatory".</p>
                </div>
            </div>
            <aside class="ivw-side">
                <div class="ivw-side-h">Where they show</div>
                <div class="ivw-post">
                    <div class="ivw-post-row"><b>Logo</b>Top right of every sales invoice. It is the company logo of <b style="display:inline">{{ $ivwCo?->name ?? 'this company' }}</b>, so the party statement prints change with it too.</div>
                    <div class="ivw-post-row"><b>Signature</b>On the line above "Authorized Signatory" on the invoice — the PDF button, the email and the WhatsApp link alike.</div>
                    <div class="ivw-post-row" style="color:#64748b">The old image is kept, so a wrong upload can be put back from Company Settings.</div>
                </div>
                <div class="ivw-actions"><button type="button" class="ivw-btn pri wide" id="ivwBrandSave" onclick="ivwBrandSave()"><i class="fas fa-check"></i> Save</button></div>
            </aside>
        </div>
        @endif
    </div>
</div>
