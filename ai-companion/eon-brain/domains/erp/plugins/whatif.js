/* ============================================================
   EON · what-if — the boss thinks out loud, EON does the arithmetic.

     "what if DBL Ceramics pays half of what they owe?"
     "what if we hire 5 engineers?"
     "if we raise salaries 10%"
     "what if revenue drops 15% and opex goes up 20%?"

   Levers (all optional):
     revenue_change_pct · opex_change_pct · collect_overdue_pct
     pay_overdue_pct · headcount_delta · salary_raise_pct
     one_off_cash · months (default 3)

   The baseline is the real ledger: the average of the last closed
   months, today's cash, today's overdue AR/AP, today's payroll.
   The same arithmetic lives in server/py/plugins/scenario.py so the
   server and the browser always answer the same number.
   ============================================================ */
import * as F from '../finance.js';
import * as P from '../people.js';
import { monthKey, MONTHS, fmtBDTk, iso } from '../dataset.js';

const D0 = () => (typeof window !== 'undefined' && window.EonErp && window.EonErp.dataset ? window.EonErp.dataset() : null);
const scope = (ctx) => ({ company: (ctx && ctx.company != null) ? +ctx.company : (typeof window !== 'undefined' && window.EonErp && window.EonErp.company ? window.EonErp.company() : null) });
const T = (D) => (D && D.meta && D.meta.today) || iso(new Date());
const round = (n) => Math.round(Number(n) || 0);

/* ---------- baseline: what the company does in an ordinary month ---------- */
export function baseline(D, opts = {}) {
  const t = T(D), thisMk = monthKey(t);
  const months = [];
  for (let i = 1; i <= 3; i++) {                       // the last three CLOSED months
    const d = new Date(thisMk + '-01T00:00:00'); d.setMonth(d.getMonth() - i);
    const mk = monthKey(d);
    const pl = F.profitAndLoss(D, Object.assign({ from: mk + '-01', to: mk + '-31' }, opts));
    months.push({ mk, income: pl.totalIncome, outflow: (pl.totalOpex || 0) + (pl.totalCogs || 0) });
  }
  const live = months.filter((m) => m.income > 0 || m.outflow > 0);
  const use = live.length ? live : months;
  const income = use.reduce((n, m) => n + m.income, 0) / use.length;
  const outflow = use.reduce((n, m) => n + m.outflow, 0) / use.length;
  const hc = P.headcount(D, opts);
  return {
    income, outflow, net: income - outflow,
    cash: F.cashPosition(D, opts).total,
    arOverdue: F.receivables(D, opts).overdueTotal,
    apOverdue: F.payables(D, opts).overdueTotal,
    payroll: hc.monthlyPayroll, headcount: hc.total, avgSalary: hc.avgSalary,
    months: use.map((m) => m.mk),
  };
}

/* ---------- the simulation ---------- */
export function simulate(D, levers = {}, opts = {}) {
  const b = baseline(D, opts);
  const L = Object.assign({ revenue_change_pct: 0, opex_change_pct: 0, collect_overdue_pct: 0, pay_overdue_pct: 0, headcount_delta: 0, salary_raise_pct: 0, one_off_cash: 0, months: 3 }, levers);
  const n = Math.max(1, Math.min(24, +L.months || 3));

  const payrollDelta = (+L.headcount_delta || 0) * (b.avgSalary || 0) + b.payroll * ((+L.salary_raise_pct || 0) / 100);
  const income = b.income * (1 + (+L.revenue_change_pct || 0) / 100);
  const outflow = b.outflow * (1 + (+L.opex_change_pct || 0) / 100) + payrollDelta;
  const oneOff = (b.arOverdue * ((+L.collect_overdue_pct || 0) / 100)) - (b.apOverdue * ((+L.pay_overdue_pct || 0) / 100)) + (+L.one_off_cash || 0);

  const t = T(D); const start = new Date(monthKey(t) + '-01T00:00:00');
  const rows = []; let cash = b.cash + oneOff, baseCash = b.cash;
  for (let i = 0; i < n; i++) {
    const d = new Date(start.getTime()); d.setMonth(d.getMonth() + i + 1);
    const net = income - outflow;
    cash += net; baseCash += b.net;
    rows.push({ month: monthKey(d), label: `${MONTHS[d.getMonth()].slice(0, 3)} ${d.getFullYear()}`, income: round(income), outflow: round(outflow), payroll_delta: round(payrollDelta), net: round(net), cash_end: round(cash), cash_end_baseline: round(baseCash) });
  }
  const last = rows[rows.length - 1];
  const burn = outflow - income;
  return {
    levers: L, baseline: { income: round(b.income), outflow: round(b.outflow), net: round(b.net), cash: round(b.cash), arOverdue: round(b.arOverdue), apOverdue: round(b.apOverdue), payroll: round(b.payroll), headcount: b.headcount, avgSalary: round(b.avgSalary), months: b.months },
    rows,
    summary: {
      months: n,
      one_off: round(oneOff),
      net_per_month: round(income - outflow),
      net_change: round((income - outflow) - b.net),
      cash_at_horizon: last.cash_end,
      cash_at_horizon_baseline: last.cash_end_baseline,
      cash_change: round(last.cash_end - last.cash_end_baseline),
      runway_months: burn > 0 ? Math.floor(cash > 0 ? (b.cash + oneOff) / burn : 0) : null,
    },
  };
}

/* ---------- English → levers ---------- */
const num = (s) => (s == null ? null : parseFloat(String(s).replace(/,/g, '')));
const shareWord = (s) => (/\bhalf\b/.test(s) ? 50 : /\ball|everything|in full|fully\b/.test(s) ? 100 : /\ba third\b/.test(s) ? 33 : /\ba quarter\b/.test(s) ? 25 : null);

export function parse(q) {
  const s = String(q || '').toLowerCase();
  const L = {}; let touched = false;
  const pct = (re) => { const m = s.match(re); return m ? num(m[1]) : null; };

  let v = pct(/revenue (?:goes |went |go )?(?:up|grows?|rises?|increases?)(?: by)? (\d+(?:\.\d+)?)\s*%/);
  if (v == null) { const d = pct(/revenue (?:drops?|falls?|declines?|goes down|down)(?: by)? (\d+(?:\.\d+)?)\s*%/); if (d != null) v = -d; }
  if (v == null) { const m = s.match(/(\d+(?:\.\d+)?)\s*%\s*(?:more|higher|increase in) (?:revenue|sales)/); if (m) v = num(m[1]); }
  if (v != null) { L.revenue_change_pct = v; touched = true; }

  let o = pct(/(?:opex|expenses?|costs?|overheads?) (?:goes? |go |went )?(?:up|rises?|increases?)(?: by)? (\d+(?:\.\d+)?)\s*%/);
  if (o == null) { const d = pct(/(?:opex|expenses?|costs?|overheads?) (?:drops?|falls?|goes? down|down|decreases?)(?: by)? (\d+(?:\.\d+)?)\s*%/); if (d != null) o = -d; }
  if (o == null) { const c = pct(/cut (?:opex|expenses?|costs?|overheads?)(?: by)? (\d+(?:\.\d+)?)\s*%/); if (c != null) o = -c; }
  if (o != null) { L.opex_change_pct = o; touched = true; }

  if (/collect|pays? us|pay us|they pay|customers? pay|receivable|pays? (?:half|all|a third|a quarter|\d+\s*%)|pay (?:what|their|the) (?:they )?owe/.test(s)) {
    const p = pct(/(\d+(?:\.\d+)?)\s*%/) ?? shareWord(s);
    if (p != null) { L.collect_overdue_pct = p; touched = true; }
  }
  if (/\bwe pay\b|pay (?:the |our |all )?(?:suppliers?|creditors?|payables?|bills?)|settle (?:the |our |all )?(?:suppliers?|payables?)/.test(s)) {
    const p = pct(/(\d+(?:\.\d+)?)\s*%/) ?? shareWord(s) ?? 100;
    L.pay_overdue_pct = p; touched = true;
  }
  const hire = s.match(/hire (\d+)/); if (hire) { L.headcount_delta = +hire[1]; touched = true; }
  const fire = s.match(/(?:let go|lay off|cut|reduce headcount by|remove) (\d+)/); if (fire) { L.headcount_delta = -(+fire[1]); touched = true; }
  const raise = s.match(/(?:raise|increase) (?:salar\w+|pay|wages)(?: by)? (\d+(?:\.\d+)?)\s*%|(\d+(?:\.\d+)?)\s*% (?:salary|pay) (?:raise|rise|increase)/);
  if (raise) { L.salary_raise_pct = num(raise[1] || raise[2]); touched = true; }
  const mo = s.match(/(?:over|for|in) (?:the )?(?:next )?(\d{1,2}) months?/); if (mo) L.months = +mo[1];
  if (/next quarter/.test(s)) L.months = 3;
  if (/next (?:year|12 months)/.test(s)) L.months = 12;

  return touched ? L : null;
}

const LEVER_WORDS = {
  revenue_change_pct: (v) => `revenue ${v >= 0 ? 'up' : 'down'} ${Math.abs(v)}%`,
  opex_change_pct: (v) => `opex ${v >= 0 ? 'up' : 'down'} ${Math.abs(v)}%`,
  collect_overdue_pct: (v) => `${v}% of the overdue receivable collected`,
  pay_overdue_pct: (v) => `${v}% of the overdue payable settled`,
  headcount_delta: (v) => (v >= 0 ? `${v} more people` : `${-v} fewer people`),
  salary_raise_pct: (v) => `salaries ${v >= 0 ? '+' : ''}${v}%`,
  one_off_cash: (v) => `${fmtBDTk(v)} one-off`,
};
export function say(r, headOverride) {
  const L = r.levers, S = r.summary, last = r.rows[r.rows.length - 1];
  const parts = Object.keys(LEVER_WORDS).filter((k) => L[k]).map((k) => LEVER_WORDS[k](L[k]));
  const head = headOverride != null ? headOverride : (parts.length ? `If ${parts.join(' and ')}: ` : '');
  const a = fmtBDTk(S.cash_at_horizon), b = fmtBDTk(S.cash_at_horizon_baseline);
  const cash = a === b                                            // both round to the same words — say the delta only
    ? `cash at the end of ${last.label} is ${a}, ${S.cash_change >= 0 ? 'better' : 'worse'} than today's path by ${fmtBDTk(Math.abs(S.cash_change))}`
    : `cash at the end of ${last.label} is ${a} instead of ${b} — ${S.cash_change >= 0 ? 'better' : 'worse'} by ${fmtBDTk(Math.abs(S.cash_change))}`;
  const net = S.net_change === 0 ? `Monthly net is unchanged at ${fmtBDTk(S.net_per_month)}`
    : `Monthly net ${S.net_change >= 0 ? 'rises' : 'drops'} ${fmtBDTk(Math.abs(S.net_change))} to ${fmtBDTk(S.net_per_month)}`;
  return `${head}${cash}. ${net}${S.runway_months != null ? `; at that burn the cash lasts about ${S.runway_months} months` : ''}.`;
}

/** a debtor named in the question — "what if DBL Ceramics pays half …" */
export function partyIn(D, q, opts = {}) {
  const s = String(q || '').toLowerCase();
  const rows = F.receivables(D, opts).byParty.filter((p) => p.overdue > 0 && p.party_name);
  let best = null;
  rows.forEach((p) => {
    const name = p.party_name.toLowerCase();
    const words = name.split(/[^a-z]+/).filter((w) => w.length >= 4);
    const hit = s.includes(name) ? name.length : (words.filter((w) => s.includes(w)).length ? Math.max(...words.filter((w) => s.includes(w)).map((w) => w.length)) : 0);
    if (hit && (!best || hit > best.hit)) best = { hit, name: p.party_name, overdue: p.overdue, due: p.due };
  });
  return best;
}

/* ---------- the question ---------- */
const CLAIM = /^\s*(what if|suppose|imagine|if we|assume|what happens if)\b/i;
function answer(q, ctx) {
  const s = String(q || '').trim();
  if (!CLAIM.test(s)) return null;
  const D = D0(); if (!D) return null;
  let levers = parse(s);
  if (!levers) return {
    speak: 'I can model that if you give me a number. Try “what if revenue drops 15%”, “what if we collect half of the overdue”, “what if we hire 5 engineers”, or “if we raise salaries 10%”.',
    detail: [], view: 'finance',
  };
  const sc = scope(ctx);
  let head;
  const party = levers.collect_overdue_pct ? partyIn(D, s, sc) : null;
  if (party) {                                    // one debtor pays, not the whole book
    const share = levers.collect_overdue_pct / 100;
    levers = Object.assign({}, levers, { collect_overdue_pct: 0, one_off_cash: Math.round(party.overdue * share) });
    head = `If ${party.name} pays ${Math.round(share * 100)}% of the ${fmtBDTk(party.overdue)} they owe: `;
  }
  const r = simulate(D, levers, sc);
  return {
    speak: say(r, head),
    detail: r.rows.map((x) => `${x.label}: net ${fmtBDTk(x.net)} · cash ${fmtBDTk(x.cash_end)} (baseline ${fmtBDTk(x.cash_end_baseline)})`)
      .concat([`Baseline from ${r.baseline.months.join(', ')}: income ${fmtBDTk(r.baseline.income)}/month, outflow ${fmtBDTk(r.baseline.outflow)}/month.`]),
    view: 'finance',
    data: r,
  };
}

/* ---------- screen: five sliders ---------- */
let wired = false;
const UI = { revenue_change_pct: 0, opex_change_pct: 0, collect_overdue_pct: 0, headcount_delta: 0, salary_raise_pct: 0 };
function panel() {
  const A = typeof window !== 'undefined' && window.EonApp;
  if (!A || !A.registerPanel) return;
  if (!wired && typeof document !== 'undefined') {
    wired = true;
    document.addEventListener('input', (e) => {
      const el = e.target && e.target.closest && e.target.closest('[data-whatif]');
      if (!el) return;
      UI[el.getAttribute('data-whatif')] = +el.value;
      const box = document.getElementById('whatif-out');
      const D = D0(); if (!box || !D) return;
      const r = simulate(D, UI, { company: A.state && A.state.company != null ? A.state.company : null });
      box.innerHTML = `<div class="tile"><div class="lbl">Cash at ${A.esc(r.rows[r.rows.length - 1].label)}</div><div class="val">${A.esc(fmtBDTk(r.summary.cash_at_horizon))}</div><div class="sub">baseline ${A.esc(fmtBDTk(r.summary.cash_at_horizon_baseline))} · ${r.summary.cash_change >= 0 ? '+' : '−'}${A.esc(fmtBDTk(Math.abs(r.summary.cash_change)))}</div></div>
        <div class="tile"><div class="lbl">Net per month</div><div class="val">${A.esc(fmtBDTk(r.summary.net_per_month))}</div><div class="sub">${r.summary.net_change >= 0 ? '+' : '−'}${A.esc(fmtBDTk(Math.abs(r.summary.net_change)))} vs today</div></div>`;
      const lbl = el.parentElement && el.parentElement.querySelector('[data-whatif-val]');
      if (lbl) lbl.textContent = el.value + (el.getAttribute('data-whatif') === 'headcount_delta' ? ' people' : '%');
    });
  }
  const row = (key, label, min, max, step) => `<label style="display:block;margin:8px 0"><span class="hint">${label} · <b data-whatif-val>${UI[key]}${key === 'headcount_delta' ? ' people' : '%'}</b></span><input type="range" data-whatif="${key}" min="${min}" max="${max}" step="${step}" value="${UI[key]}" style="width:100%"></label>`;
  A.registerPanel('finance', {
    id: 'whatif', title: 'What-if', order: 60,
    render: () => `<div class="hint">Move a lever — EON re-runs the next three months against the real ledger.</div>
      ${row('revenue_change_pct', 'Revenue', -50, 50, 5)}
      ${row('opex_change_pct', 'Operating cost', -50, 50, 5)}
      ${row('collect_overdue_pct', 'Overdue collected', 0, 100, 10)}
      ${row('headcount_delta', 'Headcount', -20, 20, 1)}
      ${row('salary_raise_pct', 'Salary raise', 0, 30, 1)}
      <div class="grid g2" id="whatif-out" style="margin-top:10px"></div>`,
  });
}

/* ---------- registration ---------- */
if (typeof window !== 'undefined') {
  window.EonWhatIf = Object.assign(window.EonWhatIf || {}, { simulate, baseline, parse, say });
  (window.__eonDomainQueue = window.__eonDomainQueue || []).push({ id: 'whatif', priority: 96, claims: (q) => CLAIM.test(String(q || '').trim()), answer });
  if (window.EonApp) panel(); else window.addEventListener('eon:app-ready', panel);
}
export default { simulate, baseline, parse, say };
