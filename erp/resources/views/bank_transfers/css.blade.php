<style>
/* ── Summary Cards ── */
.bt-summary-card {
    border-left: 4px solid transparent;
    transition: transform .15s ease, box-shadow .15s ease;
}
.bt-summary-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0,0,0,.08);
}
.bt-summary-card--total     { border-left-color: #6b7280; }
.bt-summary-card--completed { border-left-color: #10b981; }
.bt-summary-card--pending   { border-left-color: #f59e0b; }
.bt-summary-card--amount    { border-left-color: #3b82f6; }

/* ── Status Badges ── */
.bt-badge {
    display: inline-flex;
    align-items: center;
    padding: .2rem .6rem;
    border-radius: 9999px;
    font-size: .7rem;
    font-weight: 700;
    letter-spacing: .04em;
    text-transform: uppercase;
}
.bt-badge--completed { background: #d1fae5; color: #065f46; }
.bt-badge--pending   { background: #fef3c7; color: #92400e; }
.bt-badge--cancelled { background: #fee2e2; color: #991b1b; }

/* ── Transfer Arrow (create / edit / show) ── */
.bt-arrow-wrapper {
    display: flex;
    align-items: center;
    justify-content: center;
}
.bt-arrow {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: .25rem;
    color: #3b82f6;
}
.bt-arrow svg { width: 2rem; height: 2rem; }
.bt-arrow span { font-size: .75rem; font-weight: 700; color: #1d4ed8; }

/* ── Bank Selection Card (create / edit) ── */
.bt-bank-box {
    border: 2px solid #e5e7eb;
    border-radius: .75rem;
    padding: 1rem;
    transition: border-color .2s, background .2s;
}
.bt-bank-box:focus-within {
    border-color: #3b82f6;
    background: #eff6ff;
}

/* ── Ledger Timeline (show) ── */
.bt-timeline { position: relative; padding-left: 1.75rem; }
.bt-timeline-item { position: relative; padding-bottom: 1.25rem; }
.bt-timeline-item::before {
    content: '';
    position: absolute;
    left: -1.25rem;
    top: 1rem;
    bottom: 0;
    width: 2px;
    background: #e5e7eb;
}
.bt-timeline-item:last-child::before { display: none; }
.bt-timeline-dot {
    position: absolute;
    left: -1.625rem;
    top: .3rem;
    width: .75rem;
    height: .75rem;
    border-radius: 50%;
    border: 2px solid #fff;
}
.bt-timeline-dot--debit  { background: #ef4444; box-shadow: 0 0 0 2px #ef4444; }
.bt-timeline-dot--credit { background: #10b981; box-shadow: 0 0 0 2px #10b981; }

/* ── Sticky Sidebar ── */
.bt-sidebar-sticky {
    position: sticky;
    top: 1rem;
}

/* ── Table Hover ── */
.bt-table tr:hover td { background: #f9fafb; }

/* ── Print ── */
@media print {
    .no-print { display: none !important; }
    .bt-summary-card { break-inside: avoid; }
}

/* ── Insufficient balance shake animation ── */
@keyframes bt-shake {
    0%, 100% { transform: translateX(0); }
    25%       { transform: translateX(-4px); }
    75%       { transform: translateX(4px); }
}
.bt-shake { animation: bt-shake .3s ease; }
</style>