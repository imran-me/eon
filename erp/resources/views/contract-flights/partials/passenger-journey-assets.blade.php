{{-- Styles and behaviour for the journey rows on Flights & Passengers:
     the Status button that shows/hides them (hidden on every load), and
     the stage pins that save through the same endpoint the dashboard uses.
     Every selector is mj-/mfp-journey- prefixed; nothing here reaches
     another screen. --}}
@once
<style>
    /* the toggle */
    .mfp-status-btn.is-on { border-color: #c7d2fe; background: #eef2ff; color: #4338ca; }

    /* the row: sits under its passenger, no line between the two */
    .mfp-table tr:has(+ .mfp-journey-row:not([hidden])) td { border-bottom: 0; padding-bottom: 4px; }
    /* white background as before, no coloured side edge (owner, 2026-10-03),
       room above and below, even gutters both sides */
    .mfp-table .mfp-journey-row td { padding: 10px 12px 14px; border-bottom: 1px solid #e2e8f0; background: #fff; }
    .mfp-table .mfp-journey-row:hover td { background: #f8fafc; }

    /* The journey card itself: partials/journey-card-styles (shared with the
       Flight Dashboard's ledger, which draws the same card). */
@include('contract-flights.partials.journey-card-styles')

    @media print { .mfp-journey-row, .mfp-status-btn { display: none !important; } }
</style>
<script>
(function () {
    const KEY = 'mfpStatusOpen';
    const rows = () => document.querySelectorAll('.mfp-journey-row');
    const btn  = () => document.getElementById('mfpStatusBtn');

    function paint(on) {
        rows().forEach(tr => { tr.hidden = !on; });
        const b = btn();
        if (!b) return;
        b.classList.toggle('is-on', on);
        b.setAttribute('aria-pressed', on ? 'true' : 'false');
        b.title = on ? 'Hide each passenger’s status tracker' : 'Show each passenger’s status tracker under their row';
    }
    // Hidden by default on every load (owner, 2026-10-13): the button shows
    // them all for this visit only, nothing is remembered. The one exception
    // is the passenger whose stage was just saved — his strip reopens after
    // the reload (REOPEN, read once).
    const REOPEN = 'mfpStatusReopen';
    try { localStorage.removeItem(KEY); } catch (e) {}
    window.mfpToggleStatus = function () {
        paint(!btn().classList.contains('is-on'));
    };

    // A click on a row's empty space opens / closes THAT passenger's tracker
    // only (owner, 2026-10-03). Links, buttons and form controls keep their
    // own job, and a drag that selects text is not a click.
    document.addEventListener('click', event => {
        const row = event.target.closest('tr.mfp-row');
        const strip = row?.nextElementSibling;
        if (!strip || !strip.classList.contains('mfp-journey-row')) return;
        if (event.target.closest('a, button, input, select, textarea, label, [onclick], [role="button"]')) return;
        if (String(window.getSelection?.() || '').trim()) return;
        strip.hidden = !strip.hidden;
    });

    // ?status=1 opens them all (used by links from elsewhere); otherwise hidden.
    const boot = () => {
        paint(new URLSearchParams(location.search).get('status') === '1');
        let id = null;
        try { id = sessionStorage.getItem(REOPEN); sessionStorage.removeItem(REOPEN); } catch (e) {}
        const strip = id && document.getElementById('contract-flight-passenger-' + id)?.nextElementSibling;
        if (strip && strip.classList.contains('mfp-journey-row')) strip.hidden = false;
    };
    document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', boot) : boot();

    // Stage pins — the dashboard ledger's confirm-and-PATCH, then a reload
    // because this list is server-rendered (his strip is reopened after it).
    let busy = false;
    document.addEventListener('click', async event => {
        const button = event.target.closest('.mj-stage');
        if (!button || button.disabled || busy || typeof Swal === 'undefined') return;
        const isCurrent = button.dataset.current === '1';
        const name = button.dataset.name, label = button.dataset.label;
        const from = button.closest('tr').querySelector('.mj-head strong')?.textContent || 'the current stage';
        busy = true;
        try {
            const result = await Swal.fire({
                title: label,
                text: isCurrent ? `${name} is already at this stage.`
                    : `Set ${name} from ${from} to ${label}? Earlier stages will appear completed and later stages upcoming.`,
                icon: 'info',
                showCancelButton: !isCurrent,
                confirmButtonText: isCurrent ? 'Close' : 'Mark as current stage',
                confirmButtonColor: '#4f46e5',
                showLoaderOnConfirm: !isCurrent,
                allowOutsideClick: () => !Swal.isLoading(),
                preConfirm: isCurrent ? undefined : async () => {
                    try {
                        const response = await fetch(@json(route('role.contract-flight-passengers.stage', ['role' => $role, 'contractFlightPassenger' => '__PASSENGER__'])).replace('__PASSENGER__', encodeURIComponent(button.dataset.passenger)), {
                            method: 'PATCH', credentials: 'same-origin',
                            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                            body: JSON.stringify({ stage: button.dataset.stage })
                        });
                        const data = await response.json().catch(() => ({}));
                        if (!response.ok || !data.success) throw new Error(data.message || 'Could not save the stage. Please try again.');
                        return true;
                    } catch (error) {
                        Swal.showValidationMessage(error.message);
                        return false;
                    }
                }
            });
            if (result.isConfirmed && !isCurrent) {
                try { sessionStorage.setItem(REOPEN, String(button.dataset.passenger)); } catch (e) {}
                location.reload();
            }
        } finally { busy = false; }
    });
})();
</script>
@endonce
