{{-- Who-pays switch (owner, 2026-10-08): every "who bears it" <select> in the
     contract-flight re-assign system shows as the Agent pays / We bear it pill
     the per-ticket charge rows use. Mark a select with class "cfr-sw-src".
     The select stays the value of record — read and posted unchanged; the
     switch only mirrors it and writes back with a change event. A blank
     option ("— choose —") gets no button, so nothing is lit until staff pick:
     whose side is never pre-selected. Included from operations-script, so
     once per contract-flight page. --}}
<style>
    select.cfr-sw-src{position:absolute !important;width:1px !important;height:1px !important;opacity:0;pointer-events:none;}
    /* Segmented control (owner, 2026-11-02: the old pill "looks bad"): equal halves, a soft track,
       the chosen side lifted as a white card with its colour and icon. */
    .cfr-sw{display:inline-grid;grid-auto-flow:column;grid-auto-columns:98px;gap:4px;align-self:center;justify-self:start;border:0;border-radius:0;padding:0;background:transparent;vertical-align:middle;}
    .cfr-sw .cfr-sw-b{display:inline-flex;align-items:center;justify-content:center;gap:5px;min-height:28px;border:0;background:#e2e8f0;border-radius:7px;padding:4px 11px;font-size:11.5px;font-weight:600;color:#475569;cursor:pointer;white-space:nowrap;line-height:1.2;transition:background .15s,color .15s,box-shadow .15s;}
    .cfr-sw .cfr-sw-b i{font-size:10.5px;opacity:.75;}
    .cfr-sw .cfr-sw-b:hover:not(:disabled):not(.on){background:#cbd5e1;color:#0f172a;}
    .cfr-sw .cfr-sw-b:focus-visible{outline:2px solid #0d9488;outline-offset:1px;}
    .cfr-sw .cfr-sw-b.on{font-weight:700;color:#fff;}
    .cfr-sw .cfr-sw-b.on i{opacity:1;}
    .cfr-sw .cfr-sw-b.on[data-b="agent"]{background:#4f46e5;color:#fff;box-shadow:0 1px 2px rgba(79,70,229,.35);}
    .cfr-sw .cfr-sw-b.on[data-b="company"]{background:#b45309;color:#fff;box-shadow:0 1px 2px rgba(180,83,9,.35);}
    .cfr-sw .cfr-sw-b.on[data-b="split"]{background:#0f766e;color:#fff;}
    #cfpReassignOverlay .cfr-sw{grid-auto-columns:minmax(0,1fr);width:100%;}
    #cfpReassignOverlay .cfr-sw .cfr-sw-b{padding:4px 6px;}
    .cfr-rf-side + .cfr-sw{grid-auto-columns:minmax(0,1fr);width:100%;}
    .cfr-rf-side + .cfr-sw .cfr-sw-b{padding:4px 6px;}
    .cfr-sw .cfr-sw-b:disabled{opacity:.4;cursor:not-allowed;}
</style>
<script>
(function () {
    if (window.cfrSwPaint) return;
    const label = { agent: 'Agent pays', company: 'We bear it' };
    const icon = { agent: 'fa-user-tie', company: 'fa-building' };
    const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    window.cfrSwPaint = function () {
        $('select.cfr-sw-src').each(function () {
            const sel = $(this);
            let sw = sel.next('.cfr-sw');
            if (!sw.length) sw = $('<div class="cfr-sw"></div>').insertAfter(sel);
            const off = sel.prop('disabled');
            // Agent pays always first, whatever order the select lists them in (2026-11-02: same everywhere).
            sw.html(sel.find('option').get().filter(o => o.value !== '')
                .sort((a, b) => (a.value === 'agent' ? 0 : 1) - (b.value === 'agent' ? 0 : 1))
                .map(o => {
                    const v = o.value;
                    return `<button type="button" class="cfr-sw-b${v === sel.val() ? ' on' : ''}" data-b="${esc(v)}" aria-pressed="${v === sel.val()}"${off ? ' disabled' : (o.disabled ? ' disabled title="He has no agent to bill"' : '')}>${icon[v] ? `<i class="fas ${icon[v]}"></i>` : ''}${esc(label[v] || o.text)}</button>`;
                }).join('')).toggle(sel.css('display') !== 'none');
        });
    };
    $(document).on('click', '.cfr-sw .cfr-sw-b', function () {
        const sel = $(this).closest('.cfr-sw').prev('select.cfr-sw-src');
        if (!this.disabled && sel.val() !== String($(this).data('b'))) sel.val(String($(this).data('b'))).trigger('change');
        window.cfrSwPaint();
    });
    $(document).on('change', 'select.cfr-sw-src', () => window.cfrSwPaint());
    // Dialogs (SweetAlert, re-rendered rows) add selects after load.
    let queued = false;
    new MutationObserver(muts => {
        if (queued || !muts.some(m => [...m.addedNodes].some(n => n.nodeType === 1 && (n.matches('select.cfr-sw-src') || n.querySelector('select.cfr-sw-src'))))) return;
        queued = true;
        requestAnimationFrame(() => { queued = false; window.cfrSwPaint(); });
    }).observe(document.body, { childList: true, subtree: true });
})();
</script>
