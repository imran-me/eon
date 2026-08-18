/* ============================================================
   EON ability · COMPANY HEALTH SCORE
   One number per company (and one for the group) that the boss
   can rank, question and drill into. Reads the four decision
   layers — nothing is re-derived — and turns them into a 0–100
   score with a grade, sub-scores and the three drivers pulling
   it down. The identical model lives in server/lib/tools/health.php
   so the screen, the voice and the language model agree.

   ───────────── THE MODEL (keep in sync with health.php) ─────────────
   score = finance×0.40 + people×0.25 + sales×0.20 + ops×0.15   (0–100)
   grade: A ≥ 85 · B ≥ 70 · C ≥ 55 · D below

   FINANCE (mean of five parts, each 0–100)
     margin        net margin % last calendar month
                   → 50 + 2.5×margin  (−20% → 0 · 0% → 50 · +20% → 100)
     ar_overdue    overdue receivable ÷ open receivable
                   → 100 − 150×ratio  (0 → 100 · ⅔ overdue → 0); no AR → 100
     ap_overdue    overdue payable ÷ open payable, same curve; no AP → 100
     cash_cover    cash ÷ average monthly outflow of the previous 3 months
                   (direct cost + opex) → months ÷ 6 × 100 (6 months → 100);
                   no outflow history → 100
     budget        categories over budget this month → 100 − 25 per category
   PEOPLE (attendance 40% · lateness 30% · payroll 30%)
     attendance    average attendance % over the last 30 days
                   → (pct − 70) × 4  (70% → 0 · 95% → 100); no rows → 100
     late          chronic-late employees (late on 30%+ of days, 4+ times)
                   ÷ active headcount → 100 − 400×share (25% → 0)
     payroll       unpaid payslips ÷ payslips in the latest run
                   → 100 − 100×share, floored at 60 before the 5th
                   (a run generated on the 1st is normally paid by the 5th)
   SALES (three parts, equal weight)
     conversion    won ÷ (won+lost) % → 2×pct (50% → 100); undecided → 60
     stale         cold leads (idle 10+ days or follow-up missed) ÷ open leads
                   → 100 − 200×share (half cold → 0); no open leads → 100
     pipeline      open pipeline value ÷ last month's revenue
                   → ratio ÷ 3 × 100 (3× a month of revenue → 100);
                   no revenue last month → 100 if there is a pipeline, else 50
   OPERATIONS (tasks 50% · projects 50%)
     tasks         overdue ÷ open tasks → 100 − 250×share (40% overdue → 0);
                   nothing open → 100
     projects      at-risk ÷ active projects → 100 − 200×share; none active → 100
   Every part is clamped to 0–100. "Drivers" are the three parts that cost
   the most points (weight × (100 − part)), written as sentences.
   ─────────────────────────────────────────────────────────────────────
   ============================================================ */
import { fmtBDT, fmtBDTk } from '../dataset.js';
import * as F from '../finance.js';
import * as P from '../people.js';
import * as C from '../crm.js';
import * as O from '../ops.js';
import { addProvider } from '../decisions.js';
import { companyIn } from '../qa.js';

const NAME = 'health';
const clamp = (n) => Math.max(0, Math.min(100, +n || 0));
const r1 = (n) => Math.round(n * 10) / 10;
const pctOf = (a, b) => (b ? Math.round((a / b) * 100) : 0);
const grade = (s) => (s >= 85 ? 'A' : s >= 70 ? 'B' : s >= 55 ? 'C' : 'D');
const lastMonthKey = (t) => { const d = new Date(t + 'T00:00:00'); d.setDate(1); d.setMonth(d.getMonth() - 1); return d.toISOString().slice(0, 7); };

/* weights of every part inside the total (sum = 1) */
const W = {
  margin: 0.40 / 5, ar_overdue: 0.40 / 5, ap_overdue: 0.40 / 5, cash_cover: 0.40 / 5, budget: 0.40 / 5,
  attendance: 0.25 * 0.4, late: 0.25 * 0.3, payroll: 0.25 * 0.3,
  conversion: 0.20 / 3, stale: 0.20 / 3, pipeline: 0.20 / 3,
  tasks: 0.15 * 0.5, projects: 0.15 * 0.5,
};
const LAYER_OF = { margin: 'finance', ar_overdue: 'finance', ap_overdue: 'finance', cash_cover: 'finance', budget: 'finance', attendance: 'people', late: 'people', payroll: 'people', conversion: 'sales', stale: 'sales', pipeline: 'sales', tasks: 'ops', projects: 'ops' };

/** score one scope (company id or null = whole group) — the same numbers health.php computes */
export function scoreCompany(D, company = null) {
  const t = (D.meta && D.meta.today) || new Date().toISOString().slice(0, 10);
  const day = +t.slice(8, 10);
  const lm = lastMonthKey(t);
  const o = { company };
  // ---- finance
  const pl = F.profitAndLoss(D, { from: lm + '-01', to: lm + '-31', company });
  const ar = F.receivables(D, o), ap = F.payables(D, o), rw = F.runway(D, o), bud = F.expensesVsBudget(D, o);
  const arRatio = ar.total > 0 ? ar.overdueTotal / ar.total : 0;
  const apRatio = ap.total > 0 ? ap.overdueTotal / ap.total : 0;
  const cover = rw.avgMonthlyOutflow > 0 ? rw.cash / rw.avgMonthlyOutflow : null;
  const parts = {};
  parts.margin = clamp(50 + 2.5 * pl.margin);
  parts.ar_overdue = ar.total > 0 ? clamp(100 - 150 * arRatio) : 100;
  parts.ap_overdue = ap.total > 0 ? clamp(100 - 150 * apRatio) : 100;
  parts.cash_cover = cover == null ? 100 : clamp(cover / 6 * 100);
  parts.budget = clamp(100 - 25 * bud.over.length);
  // ---- people
  const hc = P.headcount(D, o), pt = P.patterns(D, { company, days: 30 }), pr = P.payroll(D, o);
  const lateShare = hc.total ? pt.chronicLate.length / hc.total : 0;
  const payShare = pr.heads ? pr.pending.length / pr.heads : 0;
  parts.attendance = pt.rows.length ? clamp((pt.avgAttendance - 70) * 4) : 100;
  parts.late = clamp(100 - 400 * lateShare);
  parts.payroll = payShare ? Math.max(day < 5 ? 60 : 0, clamp(100 - 100 * payShare)) : 100;
  // ---- sales
  const pipe = C.pipeline(D, o), st = C.stale(D, { company, days: 10 });
  const staleShare = pipe.open.length ? st.count / pipe.open.length : 0;
  const pipeRatio = pl.totalIncome > 0 ? pipe.openValue / pl.totalIncome : null;
  parts.conversion = pipe.conversion == null ? 60 : clamp(2 * pipe.conversion);
  parts.stale = pipe.open.length ? clamp(100 - 200 * staleShare) : 100;
  parts.pipeline = pipeRatio == null ? (pipe.openValue > 0 ? 100 : 50) : clamp(pipeRatio / 3 * 100);
  // ---- ops
  const tk = O.tasks(D, o), pj = O.projects(D, o);
  const taskShare = tk.open.length ? tk.overdue.length / tk.open.length : 0;
  const riskShare = pj.active.length ? pj.atRisk.length / pj.active.length : 0;
  parts.tasks = tk.open.length ? clamp(100 - 250 * taskShare) : 100;
  parts.projects = pj.active.length ? clamp(100 - 200 * riskShare) : 100;

  const sub = {
    finance: r1((parts.margin + parts.ar_overdue + parts.ap_overdue + parts.cash_cover + parts.budget) / 5),
    people: r1(parts.attendance * 0.4 + parts.late * 0.3 + parts.payroll * 0.3),
    sales: r1((parts.conversion + parts.stale + parts.pipeline) / 3),
    ops: r1(parts.tasks * 0.5 + parts.projects * 0.5),
  };
  const score = Math.round(sub.finance * 0.40 + sub.people * 0.25 + sub.sales * 0.20 + sub.ops * 0.15);

  // ---- facts behind every part (for "why is X at 62")
  const facts = {
    margin: `Net margin last month ${pl.margin}% (${pl.netProfit >= 0 ? 'profit' : 'loss'} ${fmtBDTk(Math.abs(pl.netProfit))} on ${fmtBDTk(pl.totalIncome)} revenue)`,
    ar_overdue: ar.total > 0 ? `${Math.round(arRatio * 100)}% of receivables overdue (${fmtBDTk(ar.overdueTotal)} of ${fmtBDTk(ar.total)})` : 'No open receivables',
    ap_overdue: ap.total > 0 ? `${Math.round(apRatio * 100)}% of payables past due (${fmtBDTk(ap.overdueTotal)} of ${fmtBDTk(ap.total)})` : 'No open payables',
    cash_cover: cover == null ? `Cash ${fmtBDTk(rw.cash)}, no outflow history` : `Cash ${fmtBDTk(rw.cash)} covers ${r1(cover)} months of outflow (${fmtBDTk(rw.avgMonthlyOutflow)}/month)`,
    budget: bud.over.length ? `${bud.over.length} categor${bud.over.length > 1 ? 'ies' : 'y'} over budget this month (${bud.over.slice(0, 3).map((r) => `${r.category} ${r.pct}%`).join(', ')})` : 'No category over budget this month',
    attendance: pt.rows.length ? `Attendance ${pt.avgAttendance}% over the last 30 days` : 'No attendance rows in the last 30 days',
    late: pt.chronicLate.length ? `${pt.chronicLate.length} of ${hc.total} employees chronically late (${Math.round(lateShare * 100)}%)` : 'Nobody chronically late',
    payroll: pr.pending.length ? `${pr.pending.length} of ${pr.heads} payslips for ${pr.month} unpaid (${fmtBDTk(pr.pending.reduce((n, p) => n + (+p.net_salary || 0), 0))})` : `Payroll ${pr.month} fully paid`,
    conversion: pipe.conversion == null ? 'No decided leads yet' : `Lead conversion ${pipe.conversion}% (${pipe.won} won / ${pipe.lost} lost)`,
    stale: pipe.open.length ? `${st.count} of ${pipe.open.length} open leads gone cold (${Math.round(staleShare * 100)}%)` : 'No open leads',
    pipeline: pipeRatio == null ? `Pipeline ${fmtBDTk(pipe.openValue)}, no revenue last month to compare` : `Pipeline ${fmtBDTk(pipe.openValue)} = ${r1(pipeRatio)}× last month's revenue`,
    tasks: tk.open.length ? `${tk.overdue.length} of ${tk.open.length} open tasks overdue (${Math.round(taskShare * 100)}%)` : 'No open tasks',
    projects: pj.active.length ? `${pj.atRisk.length} of ${pj.active.length} active projects at risk` : 'No active projects',
  };
  const drivers = Object.keys(parts).map((k) => ({ part: k, layer: LAYER_OF[k], score: Math.round(parts[k]), lost: r1((100 - parts[k]) * W[k]), text: facts[k] }))
    .filter((d) => d.lost > 0).sort((a, b) => b.lost - a.lost).slice(0, 3);
  const co = company == null ? null : (D.companies || []).find((c) => c.id === company);
  // nothing to judge is not the same as healthy: a company with no people, no money owed
  // either way, no leads and no work must not score 90/A
  const signal = hc.total + ar.total + ap.total + pipe.open.length + tk.open.length + pj.active.length + Math.abs(pl.totalIncome);
  const insufficient = signal <= 0;
  return {
    insufficient_data: insufficient,
    company_id: company, company: co ? co.name : 'Epal Group (all companies)', short_name: co ? co.short_name : 'GROUP',
    score, grade: grade(score), sub, parts: Object.fromEntries(Object.keys(parts).map((k) => [k, Math.round(parts[k])])), facts, drivers,
    top_driver: insufficient ? 'No data for this company yet' : (drivers[0] ? drivers[0].text : 'Nothing is pulling the score down'), trend: null,
    formula: 'finance×0.40 + people×0.25 + sales×0.20 + ops×0.15',
  };
}

/** every company ranked, plus the group line */
export function leaderboard(D) {
  const rows = (D.companies || []).filter((c) => !c.status || c.status === 'active').map((c) => scoreCompany(D, c.id)).sort((a, b) => b.score - a.score || a.company.localeCompare(b.company));
  return { group: scoreCompany(D, null), companies: rows, best: rows[0] || null, worst: rows.length ? rows[rows.length - 1] : null };
}

/* ---------- small persistence: last snapshot (feeds the trend later) ---------- */
function remember(lb) {
  if (typeof window === 'undefined') return;
  try {
    const snap = { date: (window.EonErp && window.EonErp.dataset() && window.EonErp.dataset().meta.today) || null, scores: Object.fromEntries(lb.companies.map((r) => [r.company_id, r.score])), group: lb.group.score };
    if (window.localStorage) window.localStorage.setItem('eon_health_last', JSON.stringify(snap));
    if (window.EonBrain && typeof window.EonBrain.mergeStore === 'function') { const p = window.EonBrain.mergeStore('eon_health', { last: snap }); if (p && p.catch) p.catch(() => {}); }
  } catch {}
}

/* ---------- Ask EON ---------- */
const scopeName = (r) => (r.company_id == null ? 'the group' : r.company);
const shortLine = (r) => `${r.company}: ${r.score} (${r.grade}) — ${r.top_driver}`;
const partLine = (d) => `${d.text} → ${d.score}/100 for ${d.part.replace('_', ' ')}, costing ${d.lost} points`;
const subsLine = (r) => `finance ${r.sub.finance}, people ${r.sub.people}, sales ${r.sub.sales}, operations ${r.sub.ops}`;

function whyAnswer(D, r) {
  return { speak: `${scopeName(r).charAt(0).toUpperCase() + scopeName(r).slice(1)} scores ${r.score} out of 100, grade ${r.grade}. Sub-scores: ${subsLine(r)}. ${r.drivers.length ? `What pulls it down: ${r.drivers.map((d) => d.text).join('; ')}.` : 'Nothing is pulling it down.'} Formula: ${r.formula}.`,
    detail: [`Score ${r.score} = finance ${r.sub.finance}×0.40 + people ${r.sub.people}×0.25 + sales ${r.sub.sales}×0.20 + ops ${r.sub.ops}×0.15`].concat(r.drivers.map(partLine)),
    view: 'brief', data: r };
}

function answer(q) {
  if (typeof window === 'undefined' || !window.EonErp || !window.EonErp.dataset) return null;
  const D = window.EonErp.dataset(); if (!D) return null;
  const s = String(q || '').toLowerCase().trim();
  const isHealth = /\b(health ?scores?|company health|health of|how healthy|healthiest|healthy is|health (rank|ranking|leaderboard|grade))\b/.test(s);
  const isRank = /\b(rank (the |our |all )?compan(y|ies)|ranking of (the |our )?compan(y|ies)|compan(y|ies) (ranking|leaderboard|rank)|leaderboard)\b/.test(s) && !/\b(agent|sales ?(rep|team|person)|employee|staff|people|performer|seller)s?\b/.test(s);
  const isBestWorst = /\bwhich (company|business|unit) (is|has) (the )?(healthiest|weakest|strongest|best|worst|lowest|highest|sickest)\b|\b(healthiest|weakest|strongest|sickest) (company|business|unit)\b|\b(best|worst) (company|business)\b/.test(s);
  const isScoreOf = /\b(score|grade) (of|for)\b/.test(s);
  const isWhy = /\bwhy (is|does|did) .+ (at|score|scores|scored|only|get|got|graded?) \d{1,3}\b|\bwhy .+ (score|grade) (is|of) \d{1,3}\b|\bwhy (is|does) .+ (grade|graded) [a-d]\b/.test(s);
  if (!isHealth && !isRank && !isBestWorst && !isScoreOf && !isWhy) return null;
  const co = companyIn(D, s.replace(/\b(across|whole|entire|the|our|for the) group\b/g, ''));
  if ((isScoreOf || isWhy) && !co && !isHealth) return null;   // "score of Rahim" belongs to the people layer
  const wantsGroup = /\b(group|all compan|whole|across)\b/.test(s) && !co;

  if (isWhy || (co && !isRank && !isBestWorst)) return whyAnswer(D, scoreCompany(D, co ? co.id : null));
  const lb = leaderboard(D); remember(lb);
  if (!lb.companies.length) return { speak: 'No companies in the dataset to score.', detail: [] };
  if (isBestWorst) {
    const wantWorst = /weakest|worst|lowest|sickest/.test(s);
    const r = wantWorst ? lb.worst : lb.best;
    return { speak: `${wantWorst ? 'Weakest' : 'Healthiest'} is ${r.company} at ${r.score} (grade ${r.grade}); ${wantWorst ? 'healthiest' : 'weakest'} is ${(wantWorst ? lb.best : lb.worst).company} at ${(wantWorst ? lb.best : lb.worst).score}. ${r.company}: ${subsLine(r)}. ${r.drivers.length ? 'Main driver: ' + r.drivers[0].text + '.' : ''}`,
      detail: lb.companies.map((x, i) => `${i + 1}. ${shortLine(x)}`), view: 'brief', data: lb };
  }
  if (wantsGroup && !isRank) return whyAnswer(D, lb.group);
  const g = lb.group;
  return { speak: `Group health ${g.score} (grade ${g.grade}). Leaderboard: ${lb.companies.map((r) => `${r.short_name || r.company} ${r.score}`).join(', ')}. Healthiest ${lb.best.company} at ${lb.best.score}; weakest ${lb.worst.company} at ${lb.worst.score} — ${lb.worst.top_driver}.`,
    detail: [`Group: ${g.score} (${g.grade}) — ${subsLine(g)}`].concat(lb.companies.map((x, i) => `${i + 1}. ${shortLine(x)}`)), view: 'brief', data: lb };
}

if (typeof window !== 'undefined') {
  (window.__eonDomainQueue = window.__eonDomainQueue || []).push({ id: NAME, priority: 95, answer(q) { try { return answer(q); } catch (e) { console.warn('[EON health] answer failed:', e); return null; } } });
}

/* ---------- decisions: any company below 55 ---------- */
addProvider((D, { company } = {}) => {
  const rows = company == null ? (D.companies || []).filter((c) => !c.status || c.status === 'active').map((c) => scoreCompany(D, c.id)) : [scoreCompany(D, company)];
  return rows.filter((r) => !r.insufficient_data && r.score < 55).map((r) => ({
    id: `health-${r.company_id}`, layer: 'finance', severity: r.score < 40 ? 4 : 3, company_id: r.company_id,
    title: `${r.company} health score ${r.score} (${r.grade}) — ${r.top_driver}`,
    why: [`Sub-scores: ${subsLine(r)}`].concat(r.drivers.map((d) => `${d.text} (−${d.lost} pts)`)),
    recommend: r.drivers[0] ? `Fix the biggest driver first — ${r.drivers[0].layer === 'finance' ? 'finance' : r.drivers[0].layer} at ${r.company}: ${r.drivers[0].text.toLowerCase()}. Ask EON "why is ${r.company} at ${r.score}" for the full breakdown.` : `Review ${r.company} with its manager this week.`,
    amount: 0, actions: [{ label: `Open ${r.short_name || r.company}`, kind: 'navigate', href: 'finance.html#cash' }],
  }));
});

/* ---------- screen: leaderboard card on the brief ---------- */
function renderPanel() {
  const A = window.EonApp, esc = A.esc;
  if (!window.EonErp || !window.EonErp.dataset || !window.EonErp.dataset()) return '<div class="empty">Waiting for data…</div>';
  const lb = leaderboard(window.EonErp.dataset()); remember(lb);
  const g = lb.group;
  const barClass = (s) => (s >= 70 ? 'green' : s < 55 ? 'red' : '');
  const row = (r, i) => `<div class="item" data-health-co="${esc(String(r.company_id))}" style="cursor:pointer;grid-template-columns:auto 1fr auto" title="Open ${esc(r.company)} in Finance">
    <div class="sev" style="background:${r.score >= 70 ? 'var(--green)' : r.score < 55 ? 'var(--red)' : 'var(--amber)'}"></div>
    <div><div class="t">${i + 1}. ${esc(r.company)} <span class="tag">${esc(r.grade)}</span></div>
      <div class="bar ${barClass(r.score)}" style="margin:6px 0 4px"><i style="width:${esc(String(r.score))}%"></i></div>
      <div class="why">${esc(r.top_driver)}</div></div>
    <div class="meta"><b style="font-size:18px;color:var(--text)">${esc(String(r.score))}</b><br>F ${esc(String(Math.round(r.sub.finance)))} · P ${esc(String(Math.round(r.sub.people)))} · S ${esc(String(Math.round(r.sub.sales)))} · O ${esc(String(Math.round(r.sub.ops)))}</div>
  </div>`;
  return `<div class="hint" style="margin-bottom:8px">Group ${esc(String(g.score))} (${esc(g.grade)}) · finance ${esc(String(Math.round(g.sub.finance)))} · people ${esc(String(Math.round(g.sub.people)))} · sales ${esc(String(Math.round(g.sub.sales)))} · ops ${esc(String(Math.round(g.sub.ops)))} — click a company to open it</div>
    <div class="list">${lb.companies.map(row).join('') || '<div class="empty">No companies</div>'}</div>
    <div class="hint" style="margin-top:8px">A ≥ 85 · B ≥ 70 · C ≥ 55 · D below. Ask “why is ${esc(lb.worst ? lb.worst.company : 'a company')} at ${esc(String(lb.worst ? lb.worst.score : 0))}”.</div>`;
}
if (typeof window !== 'undefined') {
  const reg = () => { try { window.EonApp && window.EonApp.registerPanel('brief', { id: 'health-leaderboard', title: 'Company health', order: 5, render: renderPanel }); } catch (e) { console.warn('[EON health] panel failed:', e); } };
  if (window.EonApp) reg(); else window.addEventListener('eon:app-ready', reg);
  if (typeof document !== 'undefined' && !window.__eonHealthClicks) {
    window.__eonHealthClicks = true;
    document.addEventListener('click', (ev) => {
      const el = ev.target && ev.target.closest ? ev.target.closest('[data-health-co]') : null; if (!el) return;
      const id = el.getAttribute('data-health-co'); if (!window.EonErp || !window.EonErp.setCompany) return;
      window.EonErp.setCompany(id === 'null' || id === '' ? null : +id);
      location.hash = '#finance';
    });
  }
  window.EonHealth = Object.assign(window.EonHealth || {}, { scoreCompany, leaderboard, answer });
}
export const EonHealth = { scoreCompany, leaderboard, answer };
export default EonHealth;
