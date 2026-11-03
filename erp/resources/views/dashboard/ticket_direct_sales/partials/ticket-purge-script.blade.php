{{-- Ticket edit page → Delete wrong entry (TicketPurgeController). Shows what
     deleting THIS ticket takes with it — its changes, ledger rows, journals,
     what happens to its invoice and the parties whose statements change —
     and needs DELETE typed before anything is removed. Super admin only;
     included by index.blade.php behind Route::has(). --}}
<script>
(function () {
    const PREVIEW = @json(route('role.ticket-purge.preview', ['role' => request()->route('role'), 'ticketPurchase' => '__ID__']));
    const PURGE = @json(route('role.ticket-purge.store', ['role' => request()->route('role'), 'ticketPurchase' => '__ID__']));
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content;
    const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

    document.addEventListener('click', async e => {
        const btn = e.target.closest('[data-ticket-purge]');
        if (!btn) return;
        const id = btn.dataset.ticketPurge;
        const orig = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        let p;
        try {
            p = await (await fetch(PREVIEW.replace('__ID__', id), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })).json();
        } catch (err) {
            p = { success: false, message: err.message };
        } finally { btn.disabled = false; btn.innerHTML = orig; }
        if (!p.success) {
            Swal.fire({ icon: 'info', title: 'Cannot delete this entry', text: p.message, confirmButtonColor: '#4f46e5' });
            return;
        }
        const inv = !p.invoice ? 'Not on any invoice.'
            : p.invoice_deleted ? `It is the last ticket on invoice <b>${esc(p.invoice)}</b> — the invoice is deleted too.`
            : `Comes off invoice <b>${esc(p.invoice)}</b>: the invoice, the agent's charge and its journal go down by <b>৳${Math.round(p.price).toLocaleString('en-US')}</b> (to ৳${Math.round(p.invoice_left).toLocaleString('en-US')}). The other tickets on it stay.`;
        const html = `<div style="text-align:left;font-size:13px">
            <p style="margin:0 0 8px">This removes <b>only this ticket</b> and everything it posted. It cannot be undone.</p>
            <div style="border:1px solid #e2e8f0;border-radius:8px;padding:6px 10px;margin-bottom:8px"><b>${esc(p.ticket)}</b></div>
            <div style="margin-bottom:6px">${inv}</div>
            <div>${p.changes} change${p.changes === 1 ? '' : 's'} · <b>${p.ledger_rows}</b> ledger rows · <b>${p.journals}</b> journal entries removed</div>
            ${p.parties.length ? `<div style="color:#64748b;margin-top:4px">Statements re-balanced: ${p.parties.map(esc).join(', ')}</div>` : ''}
            <div style="margin-top:10px">Type <b>DELETE</b> to confirm:</div></div>`;
        const ask = await Swal.fire({
            icon: 'warning', title: 'Delete this ticket?', html,
            input: 'text', inputPlaceholder: 'DELETE', showCancelButton: true, showCloseButton: true,
            allowOutsideClick: false, confirmButtonText: 'Delete this ticket', confirmButtonColor: '#dc2626',
            preConfirm: v => v === 'DELETE' || Swal.showValidationMessage('Type DELETE in capitals'),
        });
        if (!ask.isConfirmed) return;
        try {
            const r = await (await fetch(PURGE.replace('__ID__', id), { method: 'POST', credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': CSRF } })).json();
            await Swal.fire({ icon: r.success ? 'success' : 'error', title: r.success ? 'Deleted' : 'Not deleted', text: r.message, confirmButtonColor: '#4f46e5' });
            if (r.success) { if (btn.dataset.purgeDone) location.href = btn.dataset.purgeDone; else location.reload(); }
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Not deleted', text: err.message });
        }
    });
})();
</script>
