<script>
// ── Sending: statement by email, reminder by email + SMS, WhatsApp ──
// Email and reminder go through Party Statement's own endpoints, so what
// the agent receives is exactly what that screen would send; WhatsApp is
// composed here (wa.me carries text, never a file) around the short PDF
// link the share-link endpoint mints.
const CFA_SEND_URLS = {
    statement: @json(route('role.party-statement.send-statement', ['role' => $role])),
    reminder:  @json(route('role.party-statement.send-reminder', ['role' => $role])),
    share:     @json(route('role.contract-flights.accounts.share-link', ['role' => $role])),
};
const CFA_RANGE = { from: @json($from?->format('Y-m-d')), to: @json($to?->format('Y-m-d')) };
const CFA_CSRF = document.querySelector('meta[name="csrf-token"]')?.content;
const cfaMoney = v => '৳' + Math.round(Math.abs(v)).toLocaleString('en-US');

async function cfaSend(kind, partyId, name, btn) {
    const ask = await Swal.fire({
        title: kind === 'statement' ? `Email statement to ${name}?` : `Send payment reminder to ${name}?`,
        text: kind === 'statement'
            ? (CFA_RANGE.from || CFA_RANGE.to ? 'The statement for the range shown, as PDF.' : 'Their full statement, as PDF.')
            : 'A reminder of the outstanding balance goes by email and SMS.',
        icon: 'question', showCancelButton: true, confirmButtonText: 'Send', confirmButtonColor: '#4f46e5',
    });
    if (!ask.isConfirmed) return;
    const orig = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    try {
        const body = { party_id: partyId };
        if (kind === 'statement') { body.from = CFA_RANGE.from; body.to = CFA_RANGE.to; }
        const res = await fetch(CFA_SEND_URLS[kind], { method: 'POST', credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CFA_CSRF },
            body: JSON.stringify(body) });
        const data = await res.json().catch(() => ({}));
        Swal.fire({ icon: data.success ? 'success' : 'error', title: data.success ? 'Sent' : 'Not sent',
            text: data.message || (res.ok ? '' : 'Request failed'), confirmButtonColor: '#4f46e5' });
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Not sent', text: e.message });
    } finally { btn.disabled = false; btn.innerHTML = orig; }
}

async function cfaWhatsApp(partyId, name, due, type, btn) {
    const orig = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    try {
        const qs = new URLSearchParams({ party_id: partyId, from: CFA_RANGE.from || '', to: CFA_RANGE.to || '' });
        const res = await fetch(`${CFA_SEND_URLS.share}?${qs}`, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
        const p = await res.json();
        const digits = String(p.phone || '').replace(/\D/g, '');
        if (digits.length < 10) {
            Swal.fire({ icon: 'info', title: 'No phone on file', text: `${name} has no usable phone number. Add one under Party Management first.` });
            return;
        }
        const intl = digits.startsWith('0') ? '880' + digits.slice(1) : digits;
        const today = new Date().toLocaleDateString('en-GB');
        const line = due > 0
            ? (type === 'vendor' ? `Payable to you for contract flights: ${cfaMoney(due)}` : `Outstanding due for contract flights: ${cfaMoney(due)}`)
            : 'Contract flight account: settled';
        const text = `Dear ${p.name},\n${line} as of ${today} with ${p.company}.\n`
            + (p.share_pdf_url ? `Statement (PDF):\n${p.share_pdf_url}\n` : (p.email ? 'The detailed statement (PDF) has been emailed to you.\n' : 'Please contact us for the detailed statement.\n'))
            + 'Thank you.';
        window.open(`https://wa.me/${intl}?text=${encodeURIComponent(text)}`, '_blank', 'noopener');
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Could not prepare the message', text: e.message });
    } finally { btn.disabled = false; btn.innerHTML = orig; }
}

// Agent row → the due-passengers table below shows only that agent's people,
// with their own total; click again (or "Show all") to lift it. The rows'
// money cells are read off the DOM so the footer needs no second data set.
function cfaPickAgent(agentId, name) {
    const rows = document.querySelectorAll('#cfaDueTable tbody tr[data-agent]');
    const agentRows = document.querySelectorAll('.cfa-agent-row');
    const on = agentId !== null && agentId !== undefined;
    let shown = 0, charge = 0, paid = 0, due = 0;
    const num = td => Number((td?.textContent || '').replace(/[^\d.-]/g, '')) || 0;
    rows.forEach(r => {
        const match = !on || r.dataset.agent === String(agentId);
        r.hidden = !match;
        if (match) {
            shown++;
            // Agent Charge cell: the amount, then "Received: …" and "Due: …" lines.
            const cell = r.querySelectorAll('td')[7];
            const smalls = cell ? cell.querySelectorAll('small') : [];
            charge += num({ textContent: cell ? cell.firstChild?.textContent : '' });
            paid += num(smalls[0]); due += num(smalls[1]);
        }
    });
    agentRows.forEach(r => { const sel = on && r.dataset.agent === String(agentId); r.classList.toggle('is-on', sel); r.setAttribute('aria-pressed', sel ? 'true' : 'false'); });
    document.getElementById('cfaDueNone').hidden = !(on && shown === 0);
    document.getElementById('cfaDueClear').hidden = !on;
    document.getElementById('cfaDueHint').textContent = on
        ? `${name} — ${shown} passenger${shown === 1 ? '' : 's'} with an agent charge not fully received`
        : 'Every passenger whose agent charge is not fully received, oldest departure first · click an agent above to show only theirs';
    const foot = document.getElementById('cfaDueFoot');
    foot.hidden = !(on && shown > 0);
    if (on && shown > 0) {
        const fmt = v => '৳' + Math.round(v).toLocaleString('en-US');
        document.getElementById('cfaDueFootName').textContent = name;
        document.getElementById('cfaDueFootCharge').textContent = fmt(charge);
        document.getElementById('cfaDueFootPaid').textContent = 'Received: ' + fmt(paid);
        document.getElementById('cfaDueFootDue').textContent = 'Due: ' + fmt(due);
        document.getElementById('cfaDueHead').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}
document.querySelectorAll('.cfa-agent-row').forEach(row => {
    const pick = () => cfaPickAgent(row.classList.contains('is-on') ? null : row.dataset.agent, row.dataset.agentName);
    row.addEventListener('click', event => { if (event.target.closest('a, button')) return; pick(); });
    row.addEventListener('keydown', event => { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); pick(); } });
});

</script>
