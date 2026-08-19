/* ============================================================
   EON · Command Center — the boss's screen.
   Instant screens from the client decision layers (work offline),
   language-model answers from the server when it is reachable,
   voice in and out through EonVoice, approvals and drafts logged
   as actions. One page, hash routing.
   ============================================================ */
import { EonErp } from '../ai-companion/eon-brain/domains/erp/index.js';
import { fmtBDT, fmtBDTk, MONTHS, iso } from '../ai-companion/eon-brain/domains/erp/dataset.js';
import '../ai-companion/eon-brain/voice.js';

const $ = (s, r = document) => r.querySelector(s);
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const k = (n) => fmtBDTk(+n || 0), money = (n) => fmtBDT(+n || 0);
const env = () => window.EON_ENV || { mode: 'static', serverOk: false };
const server = () => (window.EonErpAdapter && window.EonErpAdapter.server) || './server/api';
const token = () => localStorage.getItem('eon_token') || '';
const state = { section: 'brief', company: null, conv: null, messages: [], approved: JSON.parse(localStorage.getItem('eon_decided') || '{}'), voiceMode: false, lang: localStorage.getItem('eon_lang') || 'en-US' };

async function api(path, opts = {}) {
  const r = await fetch(server() + '/' + path, Object.assign({ headers: Object.assign({ 'Content-Type': 'application/json' }, token() ? { Authorization: 'Bearer ' + token() } : {}) }, opts));
  if (!r.ok) throw new Error(path + ' → ' + r.status);
  return r.json();
}
function toast(msg, ms = 3200) { const t = document.createElement('div'); t.className = 'toast'; t.textContent = msg; document.body.appendChild(t); setTimeout(() => t.remove(), ms); }
const co = () => EonErp.company();
const D = () => EonErp.dataset();
const sevClass = (n) => 's' + Math.max(1, Math.min(5, n));

/* ---------------- shell ---------------- */
function paintEnv() {
  const e = env(); const pill = $('#envPill'); const src = EonErp.source();
  // Name what the boss is actually looking at, not what the server merely holds. e.db only
  // says the server has an ERP database; without a session its private endpoints stay shut
  // (correctly) and the panel runs on the demo company — which it must admit, not hide.
  const onDemo = src !== 'erp';
  const mode = onDemo ? 'demo' : (e.llm ? 'live' : 'server');
  pill.className = 'pill ' + mode;
  $('#envText').textContent = mode === 'live' ? 'Live · ERP + language model'
    : mode === 'server' ? 'Server · ERP data · offline brain'
    : e.serverOk ? 'Demo company · offline brain' : 'Offline · demo company';
  const build = e.commit ? ` · build ${e.commit}` : '';
  // signed into the ERP → EON uses that session; otherwise say so in plain words
  const who = e.user ? ' · signed in' : (e.authError ? ' · ⚠ sign in to the ERP to see live data' : '');
  $('#footEnv').textContent = `${src || '—'} · ${e.serverOk ? 'server ok' : 'no server'}${build}${who} · voice ${window.EonVoice && window.EonVoice.available().stt ? 'on' : 'off'}`;
  if (e.deployed) $('#footEnv').title = `deployed ${new Date(e.deployed).toLocaleString()}${e.php ? ' · php ' + e.php : ''}`;
}
function paintCompanies() {
  const sel = $('#companySel'); const cur = sel.value;
  sel.innerHTML = '<option value="">Whole group</option>' + (D() ? D().companies.map((c) => `<option value="${c.id}">${esc(c.name)}</option>`).join('') : '');
  sel.value = cur; sel.onchange = () => { EonErp.setCompany(sel.value ? +sel.value : null); render(); };
}
function route() { const h = (location.hash || '#brief').slice(1).split('/'); state.section = h[0] || 'brief'; state.sub = h[1] || null; render(); }
window.addEventListener('hashchange', route);

/* ---------------- plug-in panels ----------------
   EonApp.registerPanel(section, { id, title, render: () => html, order? }) — plug-ins add cards to a section
   (brief | decisions | approvals | finance | people | crm | ops | ask). Rendered under the section's own content. */
const PANELS = {};
function registerPanel(section, p) { (PANELS[section] = PANELS[section] || []).push(p); PANELS[section].sort((a, b) => (a.order || 50) - (b.order || 50)); if (state.section === section && D()) render(); }
function pluginPanels(section) {
  const list = PANELS[section] || []; if (!list.length) return '';
  return `<div class="grid g2" style="margin-top:14px">${list.map((p) => { let html = ''; try { html = p.render() || ''; } catch (e) { console.warn('[EON panel]', p.id, e); html = `<div class="hint">${esc(e.message)}</div>`; } return html ? `<div class="card" data-panel="${esc(p.id)}"><h3>${esc(p.title)}</h3>${html}</div>` : ''; }).join('')}</div>`;
}
/* ---------------- renderers ---------------- */
const TITLES = { brief: 'Brief', decisions: 'Decisions', approvals: 'Approvals', finance: 'Finance', people: 'People', crm: 'Sales & CRM', ops: 'Operations', ask: 'Ask EON' };
/* Bangla inside an English document is read aloud by assistive tech in an
   English voice — the same failure the speech service exists to fix, one layer
   up. Rather than tag every string by hand, mark the leaves after each paint. */
const BENGALI = /[ঀ-৿]/;
function markLanguages(root) {
  if (!root) return;
  const els = root.querySelectorAll('*');
  for (let i = 0; i < els.length; i++) {
    const el = els[i];
    if (el.children.length || el.hasAttribute('lang')) continue;
    const t = el.textContent;
    if (t && BENGALI.test(t)) el.setAttribute('lang', 'bn');
  }
}

function render() {
  document.querySelectorAll('#nav a, #dockNav a').forEach((a) => a.classList.toggle('active', a.dataset.sec === state.section));
  $('#pageTitle').textContent = TITLES[state.section] || 'EON';
  if (!D()) { $('#content').innerHTML = '<div class="empty">EON is reading the company…</div>'; return; }
  const fn = { brief: rBrief, decisions: rDecisions, approvals: rApprovals, finance: rFinance, people: rPeople, crm: rCrm, ops: rOps, ask: rAsk }[state.section] || rBrief;
  try { $('#content').innerHTML = fn() + pluginPanels(state.section); } catch (e) { console.error(e); $('#content').innerHTML = `<div class="card">Something went wrong rendering this view: ${esc(e.message)}</div>`; }
  wire();
  markLanguages(document.body);
}
function tile(o, key) { const money$ = o.money !== false; const v = money$ ? k(o.value) : (o.unit === '%' ? o.value + '%' : o.value); return `<div class="card tile ${o.alert ? 'alert' : (o.trend > 0 ? 'good' : '')}" data-kpi="${key}"><div class="lbl">${esc(o.label)}</div><div class="val num">${v}</div><div class="sub">${esc(o.sub || '')}</div></div>`; }
function decisionItem(d) {
  return `<div class="item"><span class="sev ${sevClass(d.severity)}"></span><div><div class="t"><span class="tag ${d.layer}">${esc(d.layerLabel || d.layer)}</span>${esc(d.title)}</div>${(d.why || []).length ? `<div class="why">${d.why.slice(0, 3).map(esc).join(' · ')}</div>` : ''}<div class="rec">${esc(d.recommend || '')}</div>${(d.actions || []).length ? `<div class="chips">${d.actions.map((a) => `<span class="chip" data-act='${esc(JSON.stringify(a))}'>${esc(a.label)}</span>`).join('')}</div>` : ''}</div><div class="meta">${esc(d.severityLabel || '')}${d.amount ? '<br>' + k(d.amount) : ''}</div></div>`;
}
function rBrief() {
  const b0 = EonErp.brief(); const b = (state.lang === 'bn-BD' && window.EonBangla && window.EonBangla.brief) ? Object.assign({}, b0, window.EonBangla.brief(b0)) : b0; const K = b.kpis;
  const top = b.decisions.slice(0, 6);
  return `<div class="card hero"><div class="greet">${esc(b.lines[0].split('.')[0])}.</div><p id="briefText">${esc(b.lines[0].split('. ').slice(1).join('. ') + ' ' + b.lines.slice(1).join(' '))}</p><div class="chips"><span class="chip" data-q="What should I focus on today?">What should I focus on?</span><span class="chip" data-q="Who owes us money?">Who owes us?</span><span class="chip" data-q="Who is absent today?">Who is absent?</span><span class="chip" data-q="Forecast the next quarter">Forecast</span><span class="chip" data-q="Any spending anomalies?">Anomalies</span></div></div>
  <div class="grid g4" style="margin-top:14px">${['cash', 'receivables', 'payables', 'revenue', 'profit', 'headcount', 'attendance', 'payroll', 'pipeline', 'tasks', 'projects', 'expenses'].map((key) => tile(K[key], key)).join('')}</div>
  <div class="grid g2" style="margin-top:14px">
    <div class="card"><h3>Needs you today <span class="spacer"></span><a href="#decisions" class="hint">all ${b.decisions.length} →</a></h3><div class="list">${top.map(decisionItem).join('') || '<div class="empty">Nothing critical.</div>'}</div></div>
    <div class="card"><h3>Approvals <span class="spacer"></span><a href="#approvals" class="hint">${b.approvals.count} waiting →</a></h3>${approvalsTable(b.approvals.items.slice(0, 8))}</div>
  </div>
  <div class="grid g2" style="margin-top:14px">
    <div class="card"><h3>Revenue &amp; net — last 6 months</h3>${trendChart()}</div>
    <div class="card"><h3>EON reasoning trace</h3><div class="trace-live" id="traceLive">${trace()}</div></div>
  </div>`;
}
function trace() {
  const t = (window.EonDomains && window.EonDomains.trace && window.EonDomains.trace()) || [];
  const rows = [`<div><b>[read]</b> ${D().journal_entries.length} journal entries · ${D().employees.length} employees · ${D().payment_schedules.length} schedules · source ${EonErp.source()}</div>`, `<div><b>[layers]</b> finance → people → crm → ops → ranked</div>`, `<div><b>[brief]</b> ${EonErp.decisions().length} decisions, ${EonErp.approvals().count} approvals</div>`].concat(t.slice(0, 6).map((x) => `<div><b>[ask]</b> ${esc(x.q)} → ${esc(x.domain)}</div>`));
  return rows.join('');
}
function trendChart() {
  const tr = EonErp.finance.revenueTrend(D(), { company: co() }); const s = tr.series; const max = Math.max(1, ...s.map((x) => x.income));
  const W = 600, H = 150, pad = 30, bw = (W - pad * 2) / s.length;
  const bars = s.map((x, i) => { const h = Math.round((x.income / max) * (H - 40)); const nh = Math.round((Math.abs(x.net) / max) * (H - 40)); return `<g><rect x="${pad + i * bw + 6}" y="${H - 20 - h}" width="${bw - 12}" height="${h}" rx="6" fill="url(#g1)"/><rect x="${pad + i * bw + bw / 2 - 4}" y="${H - 20 - nh}" width="8" height="${nh}" rx="3" fill="${x.net >= 0 ? '#2fd18b' : '#ff5f6d'}"/><text x="${pad + i * bw + bw / 2}" y="${H - 6}" font-size="10" fill="#9aa5c4" text-anchor="middle">${MONTHS[+x.month.slice(5) - 1].slice(0, 3)}</text><text x="${pad + i * bw + bw / 2}" y="${H - 26 - h}" font-size="10" fill="#e8ecf8" text-anchor="middle">${k(x.income)}</text></g>`; }).join('');
  return `<svg class="chart" viewBox="0 0 ${W} ${H}" preserveAspectRatio="none"><defs><linearGradient id="g1" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#8f7dff"/><stop offset="1" stop-color="#34d3f2" stop-opacity=".6"/></linearGradient></defs>${bars}</svg><div class="hint">bars = income · pin = net (green profit / red loss) · run-rate ${k(tr.runRate)} (${tr.vsPrev >= 0 ? '+' : ''}${tr.vsPrev}% vs last month)</div>`;
}
function rDecisions() {
  const all = EonErp.decisions(); const layers = ['all', 'finance', 'people', 'crm', 'ops']; const f = state.sub || 'all';
  const rows = f === 'all' ? all : all.filter((d) => d.layer === f);
  return `<div class="tabs">${layers.map((l) => `<a class="btn sm ${f === l ? 'on' : ''}" href="#decisions/${l}">${l === 'all' ? 'All' : (EonErp.decisionsLayer.LAYERS[l] || l)} <span class="num">${l === 'all' ? all.length : all.filter((d) => d.layer === l).length}</span></a>`).join('')}</div><div class="card"><div class="list">${rows.map(decisionItem).join('') || '<div class="empty">Nothing here.</div>'}</div></div>`;
}
function approvalsTable(items) {
  if (!items.length) return '<div class="empty">Queue is empty.</div>';
  return `<table><thead><tr><th>Item</th><th>Who</th><th class="r">Amount</th><th></th></tr></thead><tbody>${items.map((a) => { const key = (EonErp.source() || 'demo') + ':' + a.kind + ':' + a.id; const done = state.approved[key]; return `<tr><td><span class="tag">${esc(a.kind)}</span>${esc(a.title)}${a.flag ? ` <span class="tag" style="color:var(--red)">${esc(a.flag)}</span>` : ''}<div class="hint">${esc(a.note || '')} ${a.company ? '· ' + esc(a.company) : ''}</div></td><td>${esc(a.who || '')}</td><td class="r num">${a.amount ? money(a.amount) : '—'}</td><td style="white-space:nowrap">${done ? `<span class="tag" style="color:${done === 'approve' ? 'var(--green)' : 'var(--red)'}">${done}d</span>` : `<button class="btn sm ok" data-approve="approve" data-key="${esc(key)}" data-title="${esc(a.title)}" data-amount="${a.amount || ''}">Approve</button> <button class="btn sm no" data-approve="reject" data-key="${esc(key)}" data-title="${esc(a.title)}">Reject</button>`}</td></tr>`; }).join('')}</tbody></table>`;
}
function rApprovals() { const ap = EonErp.approvals(); return `<div class="grid g4" style="margin-bottom:14px">${ap.byKind.map((x) => `<div class="card tile"><div class="lbl">${esc(x.kind)}</div><div class="val num">${x.count}</div><div class="sub">${x.amount ? k(x.amount) : ''}</div></div>`).join('')}</div><div class="card"><h3>${ap.count} waiting · ${k(ap.amount)}<span class="spacer"></span><span class="hint">decisions are logged as EON actions; the ERP executes them</span></h3>${approvalsTable(ap.items)}</div>`; }
function bucketBars(b) { const max = Math.max(1, ...b.map((x) => x.amount)); return b.map((x) => `<div style="display:flex;align-items:center;gap:10px;margin:6px 0"><span style="width:52px;font-size:12px;color:var(--muted)">${x.bucket}</span><div class="bar ${x.bucket === '90+' || x.bucket === '61–90' ? 'red' : ''}" style="flex:1"><i style="width:${Math.round(x.amount / max * 100)}%"></i></div><span class="num" style="width:80px;text-align:right;font-size:12px">${k(x.amount)}</span></div>`).join(''); }
function rFinance() {
  const F = EonErp.finance; const o = { company: co() }; const cash = F.cashPosition(D(), o), ar = F.receivables(D(), o), ap = F.payables(D(), o); const mk = iso(new Date()).slice(0, 7); const pl = F.profitAndLoss(D(), { from: mk + '-01', to: iso(new Date()), company: co() }); const bud = F.expensesVsBudget(D(), o); const tb = F.trialBalance(D(), o); const an = F.expenseAnomalies(D(), o); const rw = F.runway(D(), o);
  return `<div class="grid g4">${tile({ label: 'Cash & bank', value: cash.total, sub: cash.banks.length + ' accounts' })}${tile({ label: 'Receivables', value: ar.total, sub: k(ar.overdueTotal) + ' overdue', alert: ar.overdueTotal > 0 })}${tile({ label: 'Payables', value: ap.total, sub: k(ap.overdueTotal) + ' overdue', alert: ap.overdueTotal > 0 })}${tile({ label: 'Net profit MTD', value: pl.netProfit, sub: pl.margin + '% margin', alert: pl.netProfit < 0 })}</div>
  <div class="grid g2" style="margin-top:14px">
    <div class="card" id="receivables"><h3>Receivables aging <span class="spacer"></span><span class="hint">${ar.overdue.length} overdue</span></h3>${bucketBars(ar.buckets)}<table style="margin-top:8px"><thead><tr><th>Debtor</th><th class="r">Due</th><th class="r">Overdue</th><th class="r">Oldest</th></tr></thead><tbody>${ar.byParty.slice(0, 8).map((p) => `<tr><td>${esc(p.party_name)}</td><td class="r num">${money(p.due)}</td><td class="r num" style="color:${p.overdue ? 'var(--red)' : 'inherit'}">${p.overdue ? money(p.overdue) : '—'}</td><td class="r num">${p.oldest ? p.oldest + 'd' : '—'}</td></tr>`).join('')}</tbody></table></div>
    <div class="card" id="payables"><h3>Payables aging <span class="spacer"></span><span class="hint">${ap.overdue.length} overdue · ${k(ap.dueSoonTotal)} due in 7 days</span></h3>${bucketBars(ap.buckets)}<table style="margin-top:8px"><thead><tr><th>Creditor</th><th class="r">Due</th><th class="r">Overdue</th><th class="r">Oldest</th></tr></thead><tbody>${ap.byParty.slice(0, 8).map((p) => `<tr><td>${esc(p.party_name)} <span class="hint">${esc(p.party_type)}</span></td><td class="r num">${money(p.due)}</td><td class="r num" style="color:${p.overdue ? 'var(--red)' : 'inherit'}">${p.overdue ? money(p.overdue) : '—'}</td><td class="r num">${p.oldest ? p.oldest + 'd' : '—'}</td></tr>`).join('')}</tbody></table></div>
  </div>
  <div class="grid g3" style="margin-top:14px">
    <div class="card" id="cash"><h3>Cash by account</h3><table><tbody>${cash.banks.slice(0, 10).map((b) => `<tr><td>${esc(b.name)} <span class="hint">${esc(b.company || '')}</span></td><td class="r num" style="color:${b.balance < 0 ? 'var(--red)' : 'inherit'}">${money(b.balance)}</td></tr>`).join('')}</tbody></table><div class="hint" style="margin-top:8px">${rw.burning ? `Burning ${k(-rw.avgMonthlyNet)}/month — ${rw.monthsToZero} months of runway` : `Not burning cash · ${rw.monthsOfCover} months of outflow covered`}</div></div>
    <div class="card" id="pnl"><h3>P&amp;L · ${MONTHS[+mk.slice(5) - 1]} to date</h3><table><tbody><tr><td>Income</td><td class="r num">${money(pl.totalIncome)}</td></tr><tr><td>Direct cost</td><td class="r num">−${money(pl.totalDirect)}</td></tr><tr><td>Operating expenses</td><td class="r num">−${money(pl.totalOpex)}</td></tr><tr><td>Finance</td><td class="r num">−${money(pl.totalFin)}</td></tr><tr><td><b>Net</b></td><td class="r num" style="color:${pl.netProfit >= 0 ? 'var(--green)' : 'var(--red)'}"><b>${money(pl.netProfit)}</b></td></tr></tbody></table><div class="hint" style="margin-top:8px">Trial balance ${tb.balanced ? 'balances ✓' : '⚠ does not balance'} — Dr ${k(tb.totalDebit)} / Cr ${k(tb.totalCredit)}</div></div>
    <div class="card" id="budgets"><h3>Expenses vs budget · ${MONTHS[+bud.month.slice(5) - 1]}</h3>${bud.rows.slice(0, 9).map((r) => `<div style="display:flex;align-items:center;gap:10px;margin:6px 0"><span style="width:150px;font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(r.category)}</span><div class="bar ${r.over ? 'red' : (r.warn ? '' : 'green')}" style="flex:1"><i style="width:${Math.min(100, r.pct || 0)}%"></i></div><span class="num" style="width:56px;text-align:right;font-size:12px">${r.pct == null ? '—' : r.pct + '%'}</span></div>`).join('')}${an.length ? `<div class="hint" style="margin-top:8px">⚠ ${an.length} anomal${an.length > 1 ? 'ies' : 'y'}: ${an.slice(0, 2).map((a) => `${esc(a.category)} at ${esc(a.company)} ${a.ratio}×`).join('; ')}</div>` : ''}</div>
  </div>`;
}
function rPeople() {
  const P = EonErp.people; const o = { company: co() }; const td = P.today(D(), o), pt = P.patterns(D(), o), pr = P.payroll(D(), o), lv = P.leaves(D(), o), hc = P.headcount(D(), o), rk = P.ranking(D(), { company: co(), limit: 6 });
  return `<div class="grid g4">${tile({ label: 'Headcount', value: hc.total, money: false, sub: k(hc.monthlyPayroll) + '/month' })}${tile({ label: 'Present today', value: td.presentPct, unit: '%', money: false, sub: `${td.absent.length} absent · ${td.late.length} late · ${td.onLeave.length} on leave`, alert: td.absent.length >= 5 })}${tile({ label: 'Payroll · ' + MONTHS[+pr.month.slice(5) - 1].slice(0, 3), value: pr.net, sub: pr.pending.length ? pr.pending.length + ' unpaid' : 'paid', alert: pr.pending.length > 0 })}${tile({ label: 'Online now', value: td.online.length, money: false, sub: 'seen in last 5 min' })}</div>
  <div class="grid g2" style="margin-top:14px">
    <div class="card" id="today"><h3>Today ${td.holiday ? '· ' + esc(td.holiday) : td.weekend ? '· weekend' : ''}</h3><table><thead><tr><th>Absent</th><th>Late</th></tr></thead><tbody><tr><td>${td.absent.slice(0, 10).map((a) => `${esc(a.name)} <span class="hint">${esc(a.department)}</span>`).join('<br>') || '<span class="hint">nobody</span>'}</td><td>${td.late.slice(0, 10).map((a) => `${esc(a.name)} <span class="num" style="color:var(--amber)">+${a.late_minutes}m</span>`).join('<br>') || '<span class="hint">nobody</span>'}</td></tr></tbody></table><div class="hint" style="margin-top:8px">${td.onLeave.length ? 'On leave: ' + td.onLeave.map((a) => esc(a.name)).join(', ') : ''}</div></div>
    <div class="card" id="patterns"><h3>Patterns · last ${pt.days} days <span class="spacer"></span><span class="hint">avg attendance ${pt.avgAttendance}%</span></h3><table><thead><tr><th>Chronic late</th><th class="r">Late days</th><th class="r">Minutes</th></tr></thead><tbody>${pt.chronicLate.slice(0, 7).map((r) => `<tr><td>${esc(r.name)} <span class="hint">${esc(r.department)}</span></td><td class="r num">${r.lateDays}/${r.present}</td><td class="r num">${r.lateMinutes}${r.lateMinutes >= 120 ? ' <span class="tag" style="color:var(--red)">deducted</span>' : ''}</td></tr>`).join('') || '<tr><td colspan=3 class="hint">nobody chronically late</td></tr>'}</tbody></table>${pt.chronicAbsent.length ? `<div class="hint" style="margin-top:8px">Chronic absence: ${pt.chronicAbsent.slice(0, 4).map((r) => `${esc(r.name)} (${r.absent})`).join(', ')}</div>` : ''}</div>
  </div>
  <div class="grid g3" style="margin-top:14px">
    <div class="card" id="payroll"><h3>Payroll · ${MONTHS[+pr.month.slice(5) - 1]} ${pr.month.slice(0, 4)}</h3><table><tbody><tr><td>Gross</td><td class="r num">${money(pr.gross)}</td></tr><tr><td>Deductions</td><td class="r num">−${money(pr.deductions)}</td></tr><tr><td class="hint">late ${money(pr.late)} · absence ${money(pr.absent)} · loans ${money(pr.loans)}</td><td></td></tr><tr><td>Overtime</td><td class="r num">+${money(pr.overtime)}</td></tr><tr><td><b>Net</b></td><td class="r num"><b>${money(pr.net)}</b></td></tr></tbody></table><div class="hint" style="margin-top:8px">${pr.prevNet ? `${pr.net >= pr.prevNet ? '+' : ''}${Math.round((pr.net - pr.prevNet) / pr.prevNet * 100)}% vs previous month` : ''}${pr.pending.length ? ` · <b style="color:var(--red)">${pr.pending.length} unpaid (${k(pr.pending.reduce((n, p) => n + p.net_salary, 0))})</b>` : ''}</div></div>
    <div class="card"><h3>Leave requests <span class="spacer"></span><span class="hint">${lv.pending.length} pending</span></h3>${lv.pending.slice(0, 6).map((l) => `<div class="item" style="grid-template-columns:1fr auto"><div><div class="t">${esc(l.name)} <span class="tag">${esc(l.leave_type)}</span></div><div class="why">${l.days}d from ${l.start_date} · ${esc(l.reason)} · balance ${(l.balance.find((b) => b.type === l.leave_type) || {}).remaining}</div></div></div>`).join('') || '<div class="empty">none</div>'}</div>
    <div class="card" id="ranking"><h3>Top performers · 30 days</h3><table><tbody>${rk.top.map((e, i) => `<tr><td>${i + 1}. ${esc(e.employee.name)} <span class="hint">${esc(e.employee.designation)}</span></td><td class="r"><span class="num">${e.score}</span> <span class="tag">${e.grade}</span></td></tr>`).join('')}</tbody></table><div class="chips" style="margin-top:8px"><span class="chip" data-q="Who are the weakest performers?">Weakest</span><span class="chip" data-q="Evaluate ${esc(rk.top[0] ? rk.top[0].employee.name : '')}">Evaluate top</span></div></div>
  </div>`;
}
function rCrm() {
  const C = EonErp.crm; const o = { company: co() }; const p = C.pipeline(D(), o), st = C.stale(D(), o), f = C.followups(D(), o), d = C.deals(D(), o), ag = C.agents(D(), o);
  const max = Math.max(1, ...p.stages.map((s) => s.count));
  return `<div class="grid g4">${tile({ label: 'Open pipeline', value: p.openValue, sub: p.open.length + ' leads · ' + k(p.expectedValue) + ' weighted' })}${tile({ label: 'Conversion', value: p.conversion == null ? '—' : p.conversion, unit: '%', money: false, sub: `${p.won} won · ${p.lost} lost` })}${tile({ label: 'Cold leads', value: st.count, money: false, sub: k(st.value) + ' at risk', alert: st.count > 0 })}${tile({ label: 'Closing in 14 days', value: d.closingSoonValue, sub: d.closingSoon.length + ' deals · ' + d.slipped.length + ' slipped' })}</div>
  <div class="grid g2" style="margin-top:14px">
    <div class="card" id="pipeline"><h3>Funnel</h3>${p.stages.map((s) => `<div style="display:flex;align-items:center;gap:10px;margin:6px 0"><span style="width:110px;font-size:12px;text-transform:capitalize">${esc(s.label)}</span><div class="bar ${s.stage === 'won' ? 'green' : s.stage === 'lost' ? 'red' : ''}" style="flex:1"><i style="width:${Math.round(s.count / max * 100)}%"></i></div><span class="num" style="width:110px;text-align:right;font-size:12px">${s.count} · ${k(s.value)}</span></div>`).join('')}<div class="hint" style="margin-top:8px">${p.byType.slice(0, 4).map((t) => `${esc(t.type)}: ${t.open} open / ${t.won} won`).join(' · ')}</div></div>
    <div class="card" id="stale"><h3>Cold leads <span class="spacer"></span><span class="hint">${st.byAgent[0] ? 'most with ' + esc(st.byAgent[0].name) : ''}</span></h3><table><thead><tr><th>Lead</th><th>Owner</th><th class="r">Idle</th><th class="r">Value</th></tr></thead><tbody>${st.rows.slice(0, 8).map((l) => `<tr><td>${esc(l.name)} <span class="hint">${esc(String(l.lead_type).replace('_', ' '))} · ${esc(l.company)}</span></td><td>${esc(l.assigned_name || '')}</td><td class="r num">${l.idle_days}d${l.followup_overdue_days ? ` <span style="color:var(--red)">+${l.followup_overdue_days}</span>` : ''}</td><td class="r num">${money(l.value)}</td></tr>`).join('') || '<tr><td colspan=4 class="hint">no cold leads</td></tr>'}</tbody></table></div>
  </div>
  <div class="grid g3" style="margin-top:14px">
    <div class="card" id="followups"><h3>Follow-ups <span class="spacer"></span><span class="hint">${f.today.length} today · ${f.overdue.length} missed</span></h3>${f.today.slice(0, 5).map((l) => `<div class="item" style="grid-template-columns:1fr auto"><div><div class="t">${esc(l.name)}</div><div class="why">${esc(l.assigned_name || '')} · ${money(l.value)}</div></div><div class="meta">today</div></div>`).join('')}${f.overdue.slice(0, 4).map((l) => `<div class="item" style="grid-template-columns:1fr auto"><div><div class="t">${esc(l.name)}</div><div class="why">${esc(l.assigned_name || '')} · was ${l.next_followup_at}</div></div><div class="meta" style="color:var(--red)">missed</div></div>`).join('')}</div>
    <div class="card" id="deals"><h3>Deals closing soon</h3>${d.closingSoon.slice(0, 7).map((x) => `<div class="item" style="grid-template-columns:1fr auto"><div><div class="t">${esc(x.title)}</div><div class="why">${x.closing_date} · ${esc(x.stage)}</div></div><div class="meta num">${k(x.amount)}</div></div>`).join('') || '<div class="empty">nothing in 14 days</div>'}</div>
    <div class="card" id="agents"><h3>Sales board</h3><table><tbody>${ag.rows.slice(0, 7).map((r, i) => `<tr><td>${i + 1}. ${esc(r.name || '')}</td><td class="r num">${k(r.wonValue)} <span class="hint">${r.won}W/${r.lost}L${r.rate != null ? ' · ' + r.rate + '%' : ''}</span></td></tr>`).join('')}</tbody></table></div>
  </div>`;
}
function rOps() {
  const O = EonErp.ops; const o = { company: co() }; const tk = O.tasks(D(), o), pj = O.projects(D(), o), td = O.todos(D(), o);
  return `<div class="grid g4">${tile({ label: 'Overdue tasks', value: tk.overdue.length, money: false, sub: `${tk.open.length} open · ${tk.velocity} closed this week`, alert: tk.overdue.length > 0 })}${tile({ label: 'Projects at risk', value: pj.atRisk.length, money: false, sub: pj.active.length + ' active', alert: pj.atRisk.length > 0 })}${tile({ label: 'Project budget in play', value: pj.budget, sub: k(pj.spent) + ' spent' })}${tile({ label: 'Office to-dos overdue', value: td.overdue.length, money: false, sub: td.open.length + ' open', alert: td.overdue.length > 0 })}</div>
  <div class="grid g2" style="margin-top:14px">
    <div class="card" id="projects"><h3>Projects</h3><table><thead><tr><th>Project</th><th>Progress vs time</th><th class="r">Budget</th><th>Risk</th></tr></thead><tbody>${pj.active.slice().sort((a, b) => b.risk - a.risk).slice(0, 10).map((p) => `<tr><td>${esc(p.project_name)} <span class="hint">${esc(p.company)} · ${esc(p.manager || '')}</span></td><td style="min-width:150px"><div class="bar ${p.scheduleGap > 25 ? 'red' : ''}"><i style="width:${p.progress}%"></i></div><span class="hint num">${p.progress}% at ${p.elapsedPct}% time${p.late ? ' · late' : ''}</span></td><td class="r num">${p.budgetPct == null ? '—' : p.budgetPct + '%'}</td><td><span class="tag" style="color:${p.riskLabel === 'critical' ? 'var(--red)' : p.riskLabel === 'at risk' ? 'var(--amber)' : 'var(--green)'}">${p.riskLabel}</span></td></tr>`).join('')}</tbody></table></div>
    <div class="card" id="tasks"><h3>Overdue tasks <span class="spacer"></span><span class="hint">${tk.overdue.filter((t) => t.priority === 'high').length} high</span></h3><table><thead><tr><th>Task</th><th>Owner</th><th class="r">Late</th></tr></thead><tbody>${tk.overdue.slice(0, 10).map((t) => `<tr><td>${esc(t.title)} <span class="hint">${esc(t.project || '')}</span></td><td>${esc(t.assignees.join(', ') || '—')}</td><td class="r num" style="color:${t.days_overdue > 14 ? 'var(--red)' : 'inherit'}">${t.days_overdue}d</td></tr>`).join('') || '<tr><td colspan=3 class="hint">nothing overdue</td></tr>'}</tbody></table></div>
  </div>
  <div class="grid g2" style="margin-top:14px">
    <div class="card" id="load"><h3>Load <span class="spacer"></span><span class="hint">${tk.overloaded.length} overloaded · ${tk.idle.length} free</span></h3><table><tbody>${tk.load.slice(0, 8).map((r) => `<tr><td>${esc(r.name)} <span class="hint">${esc(r.department)}</span></td><td class="r num">${r.open} open · <span style="color:${r.overdue ? 'var(--red)' : 'inherit'}">${r.overdue} overdue</span></td></tr>`).join('')}</tbody></table>${tk.idle.length ? `<div class="hint" style="margin-top:8px">Free: ${tk.idle.slice(0, 5).map((e) => esc(e.name)).join(', ')}</div>` : ''}</div>
    <div class="card" id="todos"><h3>Office to-dos</h3>${td.open.slice().sort((a, b) => a.due_date.localeCompare(b.due_date)).slice(0, 8).map((t) => `<div class="item" style="grid-template-columns:1fr auto"><div><div class="t">${esc(t.title)}</div><div class="why">${esc((t.assignee_names || []).join(', '))} · ${esc(t.department || '')}</div></div><div class="meta" style="color:${t.due_date < iso(new Date()) ? 'var(--red)' : 'inherit'}">${t.due_date}</div></div>`).join('')}</div>
  </div>`;
}
function rAsk() {
  const e = env();
  return `<div class="card"><h3>Ask EON <span class="spacer"></span><span class="hint">${e.serverOk ? (e.llm ? 'language model + ERP tools' : 'server · offline brain') : 'offline brain (client)'} · voice ${window.EonVoice && window.EonVoice.available().stt ? 'ready' : 'unavailable in this browser'}</span></h3>
    <div class="chat" id="chat" role="log" aria-live="polite" aria-relevant="additions text" aria-label="Conversation with EON">${state.messages.map(msgHtml).join('') || '<div class="msg eon">Ask me anything about the business — by voice or by typing. Try “brief”, “who owes us money”, “who is absent today”, “payroll”, “evaluate Afiqur Rahman”, “forecast the next quarter”, “draft a payment reminder”, “what is 2210”.</div>'}</div>
    <div class="askbar"><button class="btn mic" id="btnMic2" title="Talk" aria-label="Ask by voice"><span aria-hidden="true">🎙</span></button><button class="btn lang" id="btnLang" title="Answer language" aria-label="Switch answer language, now ${state.lang === 'bn-BD' ? 'Bangla' : 'English'}"><span aria-hidden="true">${state.lang === 'bn-BD' ? 'বাং' : 'EN'}</span></button><input id="askInput" placeholder="Ask EON… (Enter to send)" aria-label="Ask EON a question" autocomplete="off"><button class="btn primary" id="btnAsk">Ask</button></div>
    <div class="chips" style="margin-top:10px"><label class="chip"><input type="checkbox" id="convMode" ${state.voiceMode ? 'checked' : ''}> conversation mode (hands-free, say “EON …”)</label><span class="chip" id="langChip">${state.lang === 'bn-BD' ? 'বাংলা' : 'English'} · switch</span><span class="chip" id="muteChip">${window.EonVoice && window.EonVoice.status && localStorage.getItem('eon_mute') === '1' ? '🔇 muted' : '🔊 speaks'}</span>${['Brief', 'What should I focus on?', 'Approvals', 'Cash position', 'Who owes us money?', 'Payroll', 'Who came late?', 'Pipeline', 'Overdue tasks', 'Forecast next quarter', 'Any anomalies?', 'Draft a payment reminder', 'How is late deduction calculated?'].map((q) => `<span class="chip" data-q="${esc(q)}">${esc(q)}</span>`).join('')}</div></div>`;
}
/** which language is this text in? assistive tech needs to be told, per message */
const langOf = (t) => (/[\u0980-\u09FF]/.test(String(t || '')) ? 'bn' : 'en');

function msgHtml(m) {
  const L = langOf(m.text);
  if (m.role === 'me') return `<div class="msg me" lang="${L}">${esc(m.text)}</div>`;
  if (m.role === 'think') return `<div class="msg eon think" lang="${L}">${esc(m.text)}</div>`;
  return `<div class="msg eon" lang="${L}">${esc(m.text)}${m.detail ? `<div class="detail" lang="${langOf(m.detail)}">${esc(m.detail)}</div>` : ''}${m.draft ? `<div class="draft" style="margin-top:8px">${esc(m.draft)}</div><div class="actions"><button class="btn sm" data-copy="${esc(m.draft)}">Copy</button><button class="btn sm ok" data-send-draft="${esc(m.draftTitle || 'draft')}">Queue to send</button></div>` : ''}${(m.actions || []).length ? `<div class="actions">${m.actions.map((a) => `<button class="btn sm" data-act='${esc(JSON.stringify(a))}'>${esc(a.label)}</button>`).join('')}</div>` : ''}${m.trace ? `<div class="trace">${esc(m.trace)}</div>` : ''}</div>`;
}

/* ---------------- ask ---------------- */
async function ask(q, { voice = false } = {}) {
  q = String(q || '').trim(); if (!q) return;
  // "yes, take me there" presses the button EON just offered — it must work hands-free
  if (state.lastOffer && isAffirmative(q)) {
    const label = state.lastOffer.label || (state.lang === 'bn-BD' ? 'ওটা' : 'that');
    state.messages.push({ role: 'me', text: q });
    if (runOfferedAction()) {
      const done = state.lang === 'bn-BD' ? 'নিয়ে যাচ্ছি — ' + label + '।' : 'Taking you there — ' + label + '.';
      state.messages.push({ role: 'eon', text: done, speak: done });
      paintChat();
      if (voice || state.voiceMode) { try { window.EonVoice.say(done, { lang: state.lang }); } catch {} }
      return;
    }
  }
  if (state.section !== 'ask') { location.hash = '#ask'; await new Promise((r) => setTimeout(r, 30)); }
  state.messages.push({ role: 'me', text: q }); state.messages.push({ role: 'think', text: 'EON is thinking…' }); paintChat();
  const t0 = Date.now(); let out = null;
  const e = env();

  /* "open bank", "take me to payroll" — a request to GO somewhere is answered by the
     client, because only it can move the ERP frame. The server's rule brain would
     describe the screen instead, which is not what was asked. So the local brain gets
     first refusal, and we use its answer whenever it carries somewhere to go. */
  let local = null;
  try {
    local = window.EonDomains ? await window.EonDomains.answer(q, { company: co() }) : null;
    const goes = local && (local.navigate || (local.actions || []).some((x) => x.kind === 'erp-open' && x.href));
    if (goes) {
      out = {
        text: local.speak, speak: local.speak,
        detail: Array.isArray(local.detail) ? local.detail.join('\n') : (local.detail || ''),
        actions: local.actions || [], view: local.view, data: local.data,
        trace: `client brain · ${local.domain || 'navigator'} · ${Date.now() - t0}ms`,
      };
      const href = local.navigate || (local.actions || []).map((x) => x.href).find(Boolean);
      if (href && window.EonNavigator) window.EonNavigator.go(href);   // moves the ERP, never this panel
    }
  } catch (err) { console.warn('[EON] local brain', err); }

  // ask.php is fail-closed like dataset.php and memory.php: without an ERP session it
  // answers 401, so a signed-out visitor paid a failed round trip on every question and
  // waited for it before the local brain replied. health.php has already said whether
  // this browser is authenticated — believe it and go straight to the brain.
  if (!out && e.serverOk && e.authed) {
    try { const facts = { kpis: EonErp.kpis(), decisions: EonErp.decisions().slice(0, 8).map((d) => ({ layer: d.layer, severity: d.severity, title: d.title, recommend: d.recommend })) }; const r = await api('ask.php', { method: 'POST', body: JSON.stringify({ question: q, conversation_id: state.conv, company: co(), voice, lang: state.lang, facts }) }); state.conv = r.conversation_id || state.conv; out = { text: r.text, speak: r.speak || r.text, trace: `${r.mode === 'llm' ? 'language model · ' + (r.model || '') : 'server offline brain'} · tools: ${(r.tools_used || []).join(', ') || '—'} · ${r.ms}ms${r.note ? ' · ' + r.note : ''}` }; }
    catch (err) { console.warn('server ask failed', err); out = null; }
  }
  /* No server answer coming (signed out, or the server refused): the thirteen registered
     domains are then the best brain in the room, and they were being thrown away unless
     the answer happened to carry somewhere to go. That is how "which table holds salaries"
     came back as the payroll figures and a Bangla question came back in English — the
     domain had answered both correctly and qa.js answered instead. */
  if (!out && local && local.speak) {
    out = {
      text: local.speak, speak: local.speak,
      detail: Array.isArray(local.detail) ? local.detail.join('\n') : (local.detail || ''),
      actions: local.actions || [], view: local.view, data: local.data,
      trace: `client brain · ${local.domain || 'domain'} · ${Date.now() - t0}ms`,
    };
  }
  if (!out) {
    let r = null; try { r = EonErp.answer(q); } catch {}
    if (!r && window.EonAsk && window.EonAsk.answer) { try { r = await window.EonAsk.answer(q); } catch {} }
    if (!r) r = { speak: 'I did not understand that yet. Ask about cash, receivables, payables, profit, budget, attendance, payroll, pipeline, tasks, projects, approvals, or a person by name.', detail: '' };
    out = { text: r.speak, speak: r.speak, detail: Array.isArray(r.detail) ? r.detail.join('\n') : (r.detail || ''), actions: r.actions || [], trace: `client brain · ${r.domain || r.view || 'rule'} · ${Date.now() - t0}ms`, view: r.view, data: r.data };
    if (r.view === 'draft' && r.data && r.data.body) { out.detail = ''; out.draft = r.data.body; out.draftTitle = r.data.title; }
  }
  state.messages.pop(); const reply = Object.assign({ role: 'eon' }, out); state.messages.push(reply); rememberOffer(reply); paintChat();
  if (voice || state.voiceMode) { try { window.EonVoice.say(out.speak, { lang: state.lang }); } catch {} }
  else if (window.EON && window.EON.ai && out.speak) { try { window.EON.ai.speak(out.speak.slice(0, 120), 4000); } catch {} }
}
function rememberOffer(msg) {
  const acts = (msg && msg.actions) || [];
  state.lastOffer = acts.length ? acts[0] : null;
}

function paintChat() { const c = $('#chat'); if (!c) return; c.innerHTML = state.messages.map(msgHtml).join(''); c.scrollTop = c.scrollHeight; wire(); markLanguages(c); }

/* ---------------- actions ---------------- */
async function act(kind, payload, summary) {
  const rec = { kind, payload, summary, at: new Date().toISOString() };
  // actions.php is fail-closed too. Without a session it 401'd and the local branch was
  // skipped, so an approval pressed while signed out was recorded nowhere at all and the
  // toast still said it was queued. Keep it in the browser instead.
  try { if (env().serverOk && env().authed) await api('actions.php', { method: 'POST', body: JSON.stringify(rec) }); else if (window.EonBrain && window.EonBrain.mergeStore) await window.EonBrain.mergeStore('actions', { [Date.now()]: rec }); } catch (e) { console.warn('action log failed', e); }
  toast(`${summary} — queued for the ERP`);
}

/* ---------------- wiring ---------------- */
function wire() {
  document.querySelectorAll('[data-q]').forEach((el) => { el.onclick = () => ask(el.dataset.q); });
  document.querySelectorAll('[data-approve]').forEach((b) => { b.onclick = () => { const kind = b.dataset.approve; state.approved[b.dataset.key] = kind; localStorage.setItem('eon_decided', JSON.stringify(state.approved)); act(kind, { key: b.dataset.key, amount: b.dataset.amount }, `${kind === 'approve' ? 'Approved' : 'Rejected'}: ${b.dataset.title}`); render(); }; });
  document.querySelectorAll('[data-act]').forEach((b) => { b.onclick = () => { const a = JSON.parse(b.dataset.act); if (a.kind === 'navigate' && a.href) { const [page, hash] = a.href.split('#'); const sec = page.replace('.html', '').replace('index', 'brief').replace('operations', 'ops'); location.hash = '#' + sec + (hash ? '/' + hash : ''); } else if (a.kind === 'draft') { ask(`draft ${a.payload && a.payload.kind === 'warning-letter' ? 'a warning letter to ' + a.payload.name : 'a payment reminder to ' + (a.payload && a.payload.party || '')}`); } else act(a.kind, a.payload || {}, a.label); }; });
  document.querySelectorAll('[data-copy]').forEach((b) => { b.onclick = () => { navigator.clipboard.writeText(b.dataset.copy).then(() => toast('Copied')); }; });
  document.querySelectorAll('[data-send-draft]').forEach((b) => { b.onclick = () => act('send_draft', { title: b.dataset.sendDraft }, 'Send: ' + b.dataset.sendDraft); });
  const inp = $('#askInput'); if (inp) { inp.onkeydown = (e) => { if (e.key === 'Enter') { const q = inp.value; inp.value = ''; ask(q); } }; $('#btnAsk').onclick = () => { const q = inp.value; inp.value = ''; ask(q); }; }
  const m2 = $('#btnMic2'); if (m2) m2.onclick = () => toggleMic();
  const cm = $('#convMode'); if (cm) cm.onchange = () => { state.voiceMode = cm.checked; if (cm.checked) { window.EonVoice.wakeWord(true); window.EonVoice.listen({ continuous: true }); toast('Conversation mode on — say “EON, …”'); } else { window.EonVoice.stop(); } };
  const lb = $('#btnLang'); if (lb) lb.onclick = () => { setLang(state.lang === 'bn-BD' ? 'en-US' : 'bn-BD'); };
  const lc = $('#langChip'); if (lc) lc.onclick = () => { state.lang = state.lang === 'bn-BD' ? 'en-US' : 'bn-BD'; localStorage.setItem('eon_lang', state.lang); window.EonVoice.setLang(state.lang); render(); };
  const mc = $('#muteChip'); if (mc) mc.onclick = () => { const on = localStorage.getItem('eon_mute') !== '1'; localStorage.setItem('eon_mute', on ? '1' : '0'); window.EonVoice.mute(on); render(); };
}
function toggleMic() {
  const V = window.EonVoice; if (!V) return;
  // EON renders Bangla itself — this machine has no Bengali voice and most do not
  try { V.setTts(server() + '/tts.php'); } catch (e) {}
  if (!V.available().stt) { toast('Voice input needs Chrome or Edge with a microphone.'); return; }
  if (V.status() === 'listening') V.stop(); else V.listen({ continuous: false });
}
function setLang(lang) {
  state.lang = lang;
  localStorage.setItem('eon_lang', lang);
  if (window.EonVoice && window.EonVoice.setLang) window.EonVoice.setLang(lang);
  render();
  toast(lang === 'bn-BD' ? 'এখন থেকে বাংলায় উত্তর দেব।' : 'Answering in English from now on.');
}

/* "yes, take me there" is the same as pressing the button EON just offered.
   Spoken confirmation has to work hands-free, so the last offer is remembered and
   a plain yes fires it — in English, বাংলা or Banglish. */
const AFFIRM_YES = ['yes','yeah','yep','yup','ok','okay','sure','please','do it','go ahead',
  'take me there','open it','show me','haa','hae','hyan','jee','ji','accha','thik ache','niye cholo','dekhao',
  'হ্যাঁ','হ্যা','জি','জ্বি','আচ্ছা','ঠিক আছে','নিয়ে চলো','নিয়ে চল','দেখাও','খুলে দাও','হ্যাঁ নিয়ে চলো'];
// A plain yes answers the offer EON just made. Matched on words rather than a pattern:
// this has to read the same in three scripts, and a regex here is a place for bugs to hide.
function isAffirmative(text) {
  let t = String(text || '').toLowerCase().trim();
  t = t.replace(/[?!.,।]/g, ' ').replace(/\s+/g, ' ').trim();
  if (!t) return false;
  if (AFFIRM_YES.includes(t)) return true;
  // "yes, take me there" — every word must belong to the yes vocabulary
  const words = t.split(' ');
  if (words.length > 5) return false;
  return words.every((w) => AFFIRM_YES.some((y) => y === w || y.split(' ').includes(w)));
}
function runOfferedAction() {
  const a = state.lastOffer;
  if (!a) return false;
  state.lastOffer = null;
  const btn = document.querySelector('[data-act]');
  if (a.kind === 'navigate' && a.href) {
    const [page, hash] = a.href.split('#');
    const sec = page.replace('.html', '').replace('index', 'brief').replace('operations', 'ops');
    location.hash = '#' + sec + (hash ? '/' + hash : '');
    return true;
  }
  if (btn) { btn.click(); return true; }
  return false;
}

function setMic(on) { document.querySelectorAll('.btn.mic').forEach((b) => b.classList.toggle('on', on)); }

/* ---------------- boot ---------------- */
function boot() {
  const V = window.EonVoice;
  if (V) {
    V.setLang(state.lang); if (localStorage.getItem('eon_mute') === '1') V.mute(true);
    V.onState((s, detail) => { setMic(s === 'listening'); const h = $('#heard'); if (h) h.textContent = s === 'listening' ? '🎙 listening…' : s === 'speaking' ? '🔊 speaking…' : s === 'error' ? '⚠ ' + detail : ''; if (s === 'error') toast(detail, 5000); });
    V.onTranscript((text, meta) => { const h = $('#heard'); if (h) h.textContent = meta.final ? '' : '🎙 ' + text; if (meta.final && text) ask(text, { voice: true }); });
  }
  $('#btnMic').onclick = toggleMic;
  $('#btnSpeakBrief').onclick = () => { let b = EonErp.brief(); if (!b) { toast('EON is still reading the company…'); return; } if (state.lang === 'bn-BD' && window.EonBangla && window.EonBangla.brief) b = Object.assign({}, b, window.EonBangla.brief(b)); if (V) V.say(b.speak, { lang: state.lang }); if (state.section !== 'brief') location.hash = '#brief'; };
  window.addEventListener('eon:env', paintEnv); window.addEventListener('eon:erp-data', () => { paintEnv(); paintCompanies(); render(); });
  EonErp.ready.then(() => { paintEnv(); paintCompanies(); route(); });
  setTimeout(paintEnv, 1500); setTimeout(paintEnv, 5000);
  // docked beside the ERP: chips instead of the sidebar, conversation first
  if (window.EON_DOCK) {
    const dn = document.getElementById('dockNav');
    if (dn) dn.hidden = false;
    if (!location.hash) location.hash = '#ask';
  }
  window.EonApp = { ask, act, render, state, api, toast, esc, k, money, registerPanel, panels: PANELS, env, server, prefs: () => window.EON_PREFS || {}, docked: () => !!window.EON_DOCK };
  try { window.dispatchEvent(new CustomEvent('eon:app-ready')); } catch {}
  render();
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
