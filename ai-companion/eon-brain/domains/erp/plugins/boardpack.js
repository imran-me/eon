/* ============================================================
   EON · Board pack — a printable A4 management report built from
   the client decision layers (works fully offline / static mode).

     window.EonBoardPack.html({ company })  → the whole HTML document
     window.EonBoardPack.open({ company })  → new window, ready for Ctrl+P

   Sections: 1 Executive brief · 2 Decisions · 3 Finance · 4 People ·
   5 Sales · 6 Operations · 7 Approvals · 8 Appendix (method notes).
   The server renders the same document at server/api/boardpack.php.

   Registers: an Ask EON domain ("board pack", "management report",
   "print the report"…) and a panel on the Brief screen.
   ============================================================ */
import { fmtBDT, fmtBDTk, monthKey, MONTHS, iso } from '../dataset.js';

const NAME = 'boardpack';
const LS_KEY = 'eon_boardpack_last';
const money = (n) => fmtBDT(+n || 0), k = (n) => fmtBDTk(+n || 0);
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const erp = () => (typeof window !== 'undefined' && window.EonErp) || null;
const monthName = (mk) => `${MONTHS[+mk.slice(5) - 1]} ${mk.slice(0, 4)}`;
const prevMonth = (mk) => { const d = new Date(mk + '-01T00:00:00'); d.setMonth(d.getMonth() - 1); return monthKey(d); };
const label = (s) => String(s || '').replace(/_/g, ' ');

export const SECTIONS = ['1. Executive brief', '2. Decisions', '3. Finance', '4. People', '5. Sales', '6. Operations', '7. Approvals queue', '8. Appendix — method notes'];

/* ---------- tiny HTML builders (every value escaped) ---------- */
function table(head, rows, opts = {}) {
  const align = opts.align || [];
  const th = head.map((h, i) => `<th class="${align[i] === 'r' ? 'r' : ''}">${esc(h)}</th>`).join('');
  const body = rows.length ? rows.map((r) => `<tr>${r.map((c, i) => `<td class="${align[i] === 'r' ? 'r num' : ''}">${c && c.__html !== undefined ? c.__html : esc(c)}</td>`).join('')}</tr>`).join('') : `<tr><td colspan="${head.length}" class="empty">Nothing to report</td></tr>`;
  return `<table><thead><tr>${th}</tr></thead><tbody>${body}</tbody></table>`;
}
const raw = (html) => ({ __html: html });
const sev = (n, lbl) => raw(`<span class="sev s${Math.max(1, Math.min(5, +n || 1))}">${esc(lbl || '')}</span>`);
const section = (id, title, inner) => `<section class="sec" id="${esc(id)}"><h2>${esc(title)}</h2>${inner}</section>`;
const sub = (t) => `<h3>${esc(t)}</h3>`;
const note = (t) => `<p class="note">${esc(t)}</p>`;
const kv = (pairs) => `<table class="kv"><tbody>${pairs.map(([a, b]) => `<tr><th>${esc(a)}</th><td class="r num">${esc(b)}</td></tr>`).join('')}</tbody></table>`;

const CSS = `
@page { size: A4; margin: 16mm 14mm 18mm 14mm; }
* { box-sizing: border-box; }
html, body { background: #fff; color: #111; margin: 0; padding: 0; }
body { font: 11pt/1.45 Georgia, "Times New Roman", serif; }
.page { max-width: 190mm; margin: 0 auto; padding: 10mm 0; }
.hdr { display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 2px solid #111; padding-bottom: 8px; margin-bottom: 18px; }
.hdr .brand { font: 700 22pt/1 "Segoe UI", Arial, sans-serif; letter-spacing: 3px; }
.hdr .brand small { display: block; font: 600 8pt/1.4 "Segoe UI", Arial, sans-serif; letter-spacing: 1.5px; text-transform: uppercase; color: #444; margin-top: 4px; }
.hdr .meta { text-align: right; font: 10pt/1.4 "Segoe UI", Arial, sans-serif; color: #222; }
.hdr .meta b { font-size: 12pt; }
h1 { font: 700 20pt/1.2 "Segoe UI", Arial, sans-serif; margin: 0 0 4px; }
.lead { color: #333; margin: 0 0 14px; }
.sec { page-break-before: always; break-before: page; }
.sec:first-of-type { page-break-before: auto; break-before: auto; }
h2 { font: 700 15pt/1.2 "Segoe UI", Arial, sans-serif; border-bottom: 1px solid #999; padding-bottom: 4px; margin: 0 0 12px; }
h3 { font: 700 11pt/1.2 "Segoe UI", Arial, sans-serif; margin: 16px 0 6px; color: #222; text-transform: uppercase; letter-spacing: .6px; }
p { margin: 6px 0; }
.speak { font-size: 11.5pt; border-left: 3px solid #111; padding: 6px 12px; margin: 8px 0 14px; background: #f5f5f5; }
table { width: 100%; border-collapse: collapse; font-size: 9.5pt; margin: 4px 0 10px; page-break-inside: auto; }
tr { page-break-inside: avoid; break-inside: avoid; }
th, td { border-bottom: 1px solid #ddd; padding: 4px 6px; text-align: left; vertical-align: top; }
thead th { background: #eee; border-bottom: 1px solid #999; font: 600 8.5pt "Segoe UI", Arial, sans-serif; text-transform: uppercase; letter-spacing: .4px; }
td.r, th.r { text-align: right; }
.num { font-variant-numeric: tabular-nums; white-space: nowrap; }
td.empty { color: #777; font-style: italic; text-align: center; }
table.kv th { width: 60%; font-weight: normal; color: #333; }
.grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0 18px; }
.sev { display: inline-block; font: 600 8pt "Segoe UI", Arial, sans-serif; text-transform: uppercase; letter-spacing: .5px; padding: 1px 6px; border: 1px solid #111; border-radius: 3px; }
.sev.s5 { background: #111; color: #fff; } .sev.s4 { background: #444; color: #fff; } .sev.s3 { background: #ddd; } .sev.s2 { background: #f3f3f3; } .sev.s1 { color: #555; border-color: #999; }
.why { margin: 2px 0 0 0; padding-left: 14px; color: #333; font-size: 9pt; }
.rec { font-style: italic; }
.note { color: #444; font-size: 9.5pt; }
.foot { margin-top: 24px; border-top: 1px solid #999; padding-top: 6px; font: 8.5pt "Segoe UI", Arial, sans-serif; color: #555; display: flex; justify-content: space-between; }
.tools { position: fixed; top: 10px; right: 10px; font: 10pt "Segoe UI", Arial, sans-serif; }
.tools button { font: inherit; padding: 6px 12px; border: 1px solid #111; background: #fff; cursor: pointer; border-radius: 4px; }
@media print { .tools { display: none; } .page { padding: 0; max-width: none; } }
`;

/* ---------- the document ---------- */
export function html(opts = {}) {
  const E = erp(); const D = E && E.dataset();
  if (!D) return `<!doctype html><html><head><meta charset="utf-8"><title>EON board pack</title><style>${CSS}</style></head><body><div class="page"><h1>Board pack</h1><p>EON has no dataset loaded yet.</p></div></body></html>`;
  const company = opts.company !== undefined ? opts.company : E.company();
  const F = E.finance, P = E.people, C = E.crm, O = E.ops, X = E.decisionsLayer;
  const scope = { company };
  const co = company != null ? (D.companies || []).find((c) => c.id === company) : null;
  const orgName = co ? co.name : ((D.meta && D.meta.group) || 'Epal Group');
  const today = (D.meta && D.meta.today) || iso(new Date());
  const t = new Date(today + 'T00:00:00');
  const dateLong = `${['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][t.getDay()]}, ${t.getDate()} ${MONTHS[t.getMonth()]} ${t.getFullYear()}`;
  const generated = new Date().toISOString().replace('T', ' ').slice(0, 16) + ' UTC';
  const source = (D.meta && D.meta.source) || 'demo';
  const mk = monthKey(today), lastMk = prevMonth(mk);

  // ---- data
  const brief = X.brief(D, scope), kp = brief.kpis, decisions = brief.decisions, approvals = brief.approvals;
  const pl = F.profitAndLoss(D, { from: mk + '-01', to: today, company });
  const plLast = F.profitAndLoss(D, { from: lastMk + '-01', to: lastMk + '-31', company });
  const cash = F.cashPosition(D, scope), ar = F.receivables(D, scope), ap = F.payables(D, scope), bud = F.expensesVsBudget(D, scope);
  const hc = P.headcount(D, scope), td = P.today(D, scope), pat = P.patterns(D, scope), pr = P.payroll(D, scope);
  const pipe = C.pipeline(D, scope), stale = C.stale(D, scope), deals = C.deals(D, scope);
  const tk = O.tasks(D, scope), pj = O.projects(D, scope);

  // ---- 1 executive brief
  const kpiRows = [
    ['Cash & bank', money(kp.cash.value), kp.cash.sub],
    ['Receivables', money(kp.receivables.value), kp.receivables.sub],
    ['Payables', money(kp.payables.value), kp.payables.sub],
    ['Revenue MTD', money(kp.revenue.value), kp.revenue.sub],
    ['Opex MTD', money(kp.expenses.value), kp.expenses.sub],
    [kp.profit.label, money(kp.profit.value), kp.profit.sub],
    ['Headcount', String(kp.headcount.value), kp.headcount.sub],
    ['Present today', kp.attendance.value + '%', kp.attendance.sub],
    [kp.payroll.label, money(kp.payroll.value), kp.payroll.sub],
    ['Pipeline', money(kp.pipeline.value), kp.pipeline.sub],
    ['Overdue tasks', String(kp.tasks.value), kp.tasks.sub],
    ['Projects at risk', String(kp.projects.value), kp.projects.sub],
  ];
  const s1 = section('brief', SECTIONS[0], `<div class="speak">${esc(brief.speak)}</div>${sub('Key indicators')}${table(['Indicator', 'Value', 'Context'], kpiRows, { align: ['', 'r', ''] })}`);

  // ---- 2 decisions
  const decRows = decisions.map((d, i) => [String(i + 1), sev(d.severity, d.severityLabel), d.layerLabel || d.layer, raw(`<b>${esc(d.title)}</b>${(d.why || []).length ? `<ul class="why">${d.why.map((w) => `<li>${esc(w)}</li>`).join('')}</ul>` : ''}`), raw(`<span class="rec">${esc(d.recommend || '')}</span>`), d.amount ? k(d.amount) : '—']);
  const s2 = section('decisions', SECTIONS[1], note(`${decisions.length} decisions ranked by severity, then by amount at stake. Severity: critical > high > medium > low > info.`) + table(['#', 'Severity', 'Layer', 'Decision · why', 'Recommendation', 'At stake'], decRows, { align: ['', '', '', '', '', 'r'] }));

  // ---- 3 finance
  const plTable = table(['P&L line', monthName(mk) + ' (MTD)', monthName(lastMk)], [
    ['Income', money(pl.totalIncome), money(plLast.totalIncome)],
    ['Direct cost', money(pl.totalDirect), money(plLast.totalDirect)],
    ['Gross profit', money(pl.grossProfit), money(plLast.grossProfit)],
    ['Operating expenses', money(pl.totalOpex), money(plLast.totalOpex)],
    ['Finance cost', money(pl.totalFin), money(plLast.totalFin)],
    ['Net profit', money(pl.netProfit), money(plLast.netProfit)],
    ['Net margin', pl.margin + '%', plLast.margin + '%'],
  ], { align: ['', 'r', 'r'] });
  const cashTable = table(['Account', 'Company', 'Type', 'Balance'], cash.banks.map((b) => [b.name, b.company || '', b.type, money(b.balance)]), { align: ['', '', '', 'r'] }) + kv([['Total cash & bank', money(cash.total)]]);
  const aging = (s) => table(['Bucket', 'Items', 'Amount'], s.buckets.map((b) => [b.bucket, String(b.count), money(b.amount)]), { align: ['', 'r', 'r'] }) + kv([['Open total', money(s.total)], ['Overdue', money(s.overdueTotal)], ['Due in 7 days', money(s.dueSoonTotal)]]);
  const parties = (s) => table(['Party', 'Items', 'Oldest (days)', 'Overdue', 'Due'], s.byParty.slice(0, 10).map((p) => [p.party_name, String(p.count), String(p.oldest), money(p.overdue), money(p.due)]), { align: ['', 'r', 'r', 'r', 'r'] });
  const budTable = table(['Category', 'Budget', 'Spent', 'Pending', '% of budget'], bud.rows.map((r) => [r.category, r.budget ? money(r.budget) : '—', money(r.spent), r.pending ? money(r.pending) : '—', r.pct == null ? '—' : r.pct + '%' + (r.over ? ' (over)' : r.warn ? ' (watch)' : '')]), { align: ['', 'r', 'r', 'r', 'r'] }) + kv([['Total budget', money(bud.totalBudget)], ['Total spent', money(bud.totalSpent)]]);
  const s3 = section('finance', SECTIONS[2], sub('Profit & loss') + plTable + sub('Cash by account') + cashTable + `<div class="grid2"><div>${sub('Receivable aging')}${aging(ar)}</div><div>${sub('Payable aging')}${aging(ap)}</div></div>` + sub('Top 10 receivable parties') + parties(ar) + sub('Top 10 payable parties') + parties(ap) + sub(`Budget vs actual — ${monthName(bud.month)}`) + budTable);

  // ---- 4 people
  const hcCo = table(['Company', 'Headcount', 'Monthly payroll'], hc.byCompany.map((r) => [r.company, String(r.count), money(r.payroll)]), { align: ['', 'r', 'r'] });
  const hcDept = table(['Department', 'Headcount', 'Monthly payroll'], hc.byDept.map((r) => [r.department, String(r.count), money(r.payroll)]), { align: ['', 'r', 'r'] });
  const attKv = kv([['Date', td.date + (td.weekend ? ' (weekend)' : td.holiday ? ` (${td.holiday})` : '')], ['Employees', String(td.total)], ['Present', `${td.present.length} (${td.presentPct}%)`], ['Late', String(td.late.length)], ['Absent', String(td.absent.length)], ['On leave', String(td.onLeave.length)], ['Not punched yet', String(td.notYet.length)]]);
  const absentT = table(['Absent today', 'Department', 'Company'], td.absent.slice(0, 20).map((a) => [a.name, a.department, a.company]));
  const lateT = table(['Chronic late (last ' + pat.days + ' days)', 'Company', 'Late days / present', 'Late %', 'Minutes'], pat.chronicLate.slice(0, 15).map((r) => [r.name, r.company, `${r.lateDays} / ${r.present}`, r.latePct + '%', String(r.lateMinutes)]), { align: ['', '', 'r', 'r', 'r'] });
  const payKv = kv([['Payroll month', monthName(pr.month)], ['Payslips', String(pr.heads)], ['Gross', money(pr.gross)], ['Deductions', money(pr.deductions)], ['· late', money(pr.late)], ['· absent', money(pr.absent)], ['· loan recoveries', money(pr.loans)], ['· salary advances', money(pr.advances)], ['Overtime paid', money(pr.overtime)], ['Net payable', money(pr.net)], ['Previous month net', money(pr.prevNet)], ['Unpaid payslips', String(pr.pending.length)]]);
  const payCo = table(['Company', 'Heads', 'Gross', 'Deductions', 'Net', 'Unpaid'], pr.byCompany.map((r) => [r.company, String(r.heads), money(r.gross), money(r.deductions), money(r.net), String(r.pending)]), { align: ['', 'r', 'r', 'r', 'r', 'r'] });
  const s4 = section('people', SECTIONS[3], `<div class="grid2"><div>${sub('Headcount by company')}${hcCo}</div><div>${sub('Headcount by department')}${hcDept}</div></div>` + sub('Attendance today') + attKv + absentT + sub('Chronic late') + lateT + sub('Payroll summary') + payKv + payCo);

  // ---- 5 sales
  const funnel = table(['Stage', 'Leads', 'Value'], pipe.stages.map((s) => [s.label, String(s.count), money(s.value)]), { align: ['', 'r', 'r'] }) + kv([['Open leads', String(pipe.open.length)], ['Open value', money(pipe.openValue)], ['Weighted expected value', money(pipe.expectedValue)], ['Won / lost', `${pipe.won} / ${pipe.lost}`], ['Conversion', pipe.conversion == null ? '—' : pipe.conversion + '%'], ['New leads (30 days)', String(pipe.newLast30)]]);
  const coldT = table(['Lead', 'Type', 'Owner', 'Idle (days)', 'Follow-up overdue (days)', 'Value'], stale.rows.slice(0, 15).map((l) => [l.name, label(l.lead_type), l.assigned_name || '', String(l.idle_days), String(l.followup_overdue_days), money(l.value)]), { align: ['', '', '', 'r', 'r', 'r'] });
  const dealsT = table(['Deal', 'Stage', 'Closing', 'Amount'], deals.closingSoon.slice(0, 15).map((d) => [d.title, label(d.stage), d.closing_date, money(d.amount)]), { align: ['', '', '', 'r'] }) + kv([['Open deals', `${deals.open.length} · ${money(deals.openValue)}`], ['Closing in 14 days', `${deals.closingSoon.length} · ${money(deals.closingSoonValue)}`], ['Slipped past closing date', `${deals.slipped.length} · ${money(deals.slippedValue)}`], ['Won last 30 days', `${deals.won30.length} · ${money(deals.won30Value)}`]]);
  const s5 = section('sales', SECTIONS[4], sub('Funnel by stage') + funnel + sub('Cold leads') + coldT + sub('Deals closing') + dealsT);

  // ---- 6 operations
  const projT = table(['Project', 'Company', 'Status', 'Progress', 'Time elapsed', 'Budget used', 'Overdue tasks', 'Risk'], pj.all.map((p) => [p.project_name, p.company, label(p.status), p.progress + '%', p.elapsedPct + '%', p.budgetPct == null ? '—' : p.budgetPct + '%', String(p.tasksOverdue), raw(`<span class="sev s${p.risk >= 4 ? 5 : p.risk >= 2 ? 3 : 1}">${esc(p.riskLabel)}</span>`)]), { align: ['', '', '', 'r', 'r', 'r', 'r', ''] });
  const taskT = table(['Task', 'Project', 'Assigned', 'Priority', 'Due', 'Days late'], tk.overdue.slice(0, 25).map((x) => [x.title, x.project || '', (x.assignees || []).join(', '), x.priority, x.due_date, String(x.days_overdue)]), { align: ['', '', '', '', '', 'r'] }) + kv([['Open tasks', String(tk.open.length)], ['Overdue', String(tk.overdue.length)], ['Closed last 7 days', String(tk.velocity)], ['Overloaded people', tk.overloaded.map((r) => r.name).join(', ') || 'none']]);
  const s6 = section('ops', SECTIONS[5], sub('Projects') + projT + sub('Overdue tasks') + taskT);

  // ---- 7 approvals
  const apT = table(['Kind', 'Item', 'Who', 'Company', 'Priority', 'Amount'], approvals.items.map((a) => [a.kind, a.title + (a.flag ? ` (${a.flag})` : ''), a.who || '', a.company || '', a.priority, a.amount ? money(a.amount) : '—']), { align: ['', '', '', '', '', 'r'] });
  const s7 = section('approvals', SECTIONS[6], kv([['Items waiting', String(approvals.count)], ['Total amount', money(approvals.amount)]]) + apT);

  // ---- 8 appendix
  const s8 = section('appendix', SECTIONS[7], `<ul class="why" style="font-size:10pt">
<li>Data source: <b>${esc(source)}</b> dataset${D.meta && D.meta.generated_at ? ', generated ' + esc(String(D.meta.generated_at).replace('T', ' ').slice(0, 16)) : ''}; report date ${esc(today)}; document rendered ${esc(generated)} by EON (client decision layers).</li>
<li>Scope: ${esc(co ? co.name : 'all companies of the group')}. Company-scoped rows are filtered by company_id; group-level rows (no company) are included.</li>
<li>P&amp;L: ledger balances of income (4xxx), direct cost (5xxx), operating expense (6xxx/7xxx) and finance cost (8xxx) accounts summed from posted journal lines in the period. MTD runs from the 1st to today; last month is the full calendar month.</li>
<li>Cash: current balances of bank and cash accounts as recorded in the ERP bank table.</li>
<li>Aging: open payment schedules (pending or overdue) bucketed by days past the scheduled date — current, 1–30, 31–60, 61–90, 90+. Due = amount − paid amount. Parties ranked by overdue then by due.</li>
<li>Budget vs actual: expenses of the current month by category (excluding rejected) against the category budget; "over" above 100%, "watch" at 80%+.</li>
<li>Headcount: employees with status active. Attendance today: one record per employee for the report date; late = check-in after shift start. Chronic late: late on 30%+ of present days and at least 4 late days in the window.</li>
<li>Payroll: latest generated payroll month (the ERP generates month M on the 1st of M+1). Deductions follow the ERP payroll rules.</li>
<li>Sales: funnel counts leads by status; weighted expected value uses new 5%, contacted 15%, qualified 35%, proposal 55%, negotiation 75%. Cold lead = open lead idle 10+ days or with an overdue follow-up.</li>
<li>Projects: risk score = late (3) + schedule gap over 25% (2) or over 12% (1) + over budget (2) or burning ahead of schedule (1) + 3+ overdue tasks (1) + on hold (1); critical at 4+, at risk at 2+.</li>
<li>Decisions: each layer proposes decisions with the rule that fired; ranked by severity (5 critical … 1 info), then by amount at stake.</li>
<li>Money in Bangladeshi Taka (৳), Bangladeshi digit grouping; L = lakh, Cr = crore.</li>
</ul>`);

  const title = `Board pack — ${orgName} — ${today}`;
  return `<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>${esc(title)}</title><style>${CSS}</style></head><body>
<div class="tools"><button onclick="window.print()">Print / Save as PDF</button></div>
<div class="page">
<div class="hdr"><div class="brand">EON<small>One brain · multi decision layer</small></div><div class="meta"><b>${esc(orgName)}</b><br>${esc(co ? 'Company report' : 'Group management report')}<br>${esc(dateLong)}</div></div>
<h1>Board pack</h1><p class="lead">Management report for ${esc(orgName)} as of ${esc(today)} — brief, ranked decisions, finance, people, sales, operations and the approvals queue, with method notes in the appendix.</p>
${s1}${s2}${s3}${s4}${s5}${s6}${s7}${s8}
<div class="foot"><span>EON board pack · ${esc(orgName)}</span><span>${esc(today)} · source ${esc(source)}</span></div>
</div></body></html>`;
}

export function open(opts = {}) {
  if (typeof window === 'undefined' || typeof window.open !== 'function') return false;
  let w = null;
  try { w = window.open('', '_blank'); } catch { w = null; }
  if (!w) return false;
  const doc = html(opts);
  try { w.document.open(); w.document.write(doc); w.document.close(); } catch { return false; }
  try { const rec = { at: new Date().toISOString(), company: opts.company !== undefined ? opts.company : (erp() && erp().company()) }; localStorage.setItem(LS_KEY, JSON.stringify(rec)); if (window.EonBrain && window.EonBrain.mergeStore) window.EonBrain.mergeStore('boardpack', { last: rec }); } catch {}
  return true;
}

export const EonBoardPack = { html, open, SECTIONS };
if (typeof window !== 'undefined') window.EonBoardPack = Object.assign(window.EonBoardPack || {}, EonBoardPack);

/* ---------- Ask EON domain ---------- */
const RE = /\b(board( meeting)? ?(pack|report|papers?|deck)|management (report|pack)|(prepare|make|build|generate|print|open)( me)?( the| a)? ?(board|management)? ?(report|pack)|print (the|this|my) (report|brief|pack))\b/i;
if (typeof window !== 'undefined') {
  (window.__eonDomainQueue = window.__eonDomainQueue || []).push({
    id: NAME, priority: 95,
    answer(q) {
      if (!RE.test(String(q || ''))) return null;
      const E = erp(); if (!E || !E.dataset()) return { speak: 'EON is still reading the company — the board pack needs the data first.', detail: [] };
      const opened = open();
      return { speak: opened ? 'Board pack ready — opening it.' : 'Board pack ready — use the button to open it.', detail: ['Eight sections: executive brief, decisions, finance, people, sales, operations, approvals, method notes.', 'A4, print with Ctrl+P or save as PDF.'], view: 'brief', actions: [{ label: 'Open board pack', kind: 'boardpack' }] };
    },
  });
}

/* ---------- Brief panel ---------- */
function serverLink() {
  try {
    const A = window.EonApp; if (!A || !A.env || !A.env().serverOk) return '';
    const E = erp(); const cid = E && E.company();
    const tok = localStorage.getItem('eon_token') || '';
    const url = A.server() + '/boardpack.php?company=' + (cid == null ? '' : encodeURIComponent(cid)) + (tok ? '&token=' + encodeURIComponent(tok) : '');
    return ` <a class="btn sm" href="${esc(url)}" target="_blank" rel="noopener">Server version</a>`;
  } catch { return ''; }
}
function panelHtml() {
  const A = window.EonApp; const e = A ? A.esc : esc;
  const E = erp(); const D = E && E.dataset(); if (!D) return '';
  const cid = E.company(); const co = cid != null ? (D.companies || []).find((c) => c.id === cid) : null;
  let last = null; try { last = JSON.parse(localStorage.getItem(LS_KEY) || 'null'); } catch {}
  return `<div class="hint">Printable A4 management report — brief, ranked decisions, finance, people, sales, operations, approvals and method notes. Scope: ${e(co ? co.name : 'whole group')}.</div>
<div style="margin-top:10px"><button class="btn sm ok" data-boardpack="open">Prepare board pack</button>${serverLink()}</div>
${last && last.at ? `<div class="hint" style="margin-top:8px">Last prepared ${e(String(last.at).replace('T', ' ').slice(0, 16))}</div>` : ''}`;
}
if (typeof window !== 'undefined') {
  const reg = () => { try { window.EonApp && window.EonApp.registerPanel('brief', { id: NAME, title: 'Board pack', order: 90, render: panelHtml }); } catch (e) { console.warn('[EON boardpack] panel', e); } };
  if (window.EonApp) reg(); else window.addEventListener('eon:app-ready', reg);
  if (typeof document !== 'undefined' && !window.__eonBoardPackClicks) {
    window.__eonBoardPackClicks = true;
    document.addEventListener('click', (ev) => {
      const b = ev.target && ev.target.closest ? ev.target.closest('[data-boardpack],[data-act]') : null; if (!b) return;
      let hit = b.hasAttribute('data-boardpack');
      if (!hit && b.dataset && b.dataset.act) { try { hit = JSON.parse(b.dataset.act).kind === 'boardpack'; } catch {} }
      if (!hit) return;
      ev.preventDefault();
      const ok = open();
      const A = window.EonApp; if (A && A.toast) A.toast(ok ? 'Board pack opened — Ctrl+P to print' : 'Pop-up blocked — allow pop-ups for EON and try again');
      if (ok && A && A.render) { try { A.render(); } catch {} }
    });
  }
}
export default EonBoardPack;
