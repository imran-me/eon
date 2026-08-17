/* ============================================================
   EON · Multi Decision Layer — one brain, four layers, one list.
   Collects the decisions every layer proposes (finance, people,
   CRM, operations), ranks them for the boss, builds the KPI tiles,
   the approvals queue and the spoken morning brief.
   ============================================================ */
import { iso, monthKey, MONTHS, sum, fmtBDT, fmtBDTk, pct, daysBetween } from './dataset.js';
import * as F from './finance.js';
import * as P from './people.js';
import * as C from './crm.js';
import * as O from './ops.js';

const T = (D) => (D.meta && D.meta.today) || iso(new Date());
const LAYERS = { finance: 'Finance', people: 'People', crm: 'Sales & CRM', ops: 'Operations' };
export const SEVERITY = { 5: 'critical', 4: 'high', 3: 'medium', 2: 'low', 1: 'info' };

export function kpis(D, { company = null } = {}) {
  const t = T(D); const mk = monthKey(t);
  const cash = F.cashPosition(D, { company }), ar = F.receivables(D, { company }), ap = F.payables(D, { company });
  const pl = F.profitAndLoss(D, { from: mk + '-01', to: t, company }), tr = F.revenueTrend(D, { company });
  const lastMk = (() => { const d = new Date(mk + '-01T00:00:00'); d.setMonth(d.getMonth() - 1); return monthKey(d); })();
  const plLast = F.profitAndLoss(D, { from: lastMk + '-01', to: lastMk + '-31', company });   // '-31' is a safe upper bound: dates compare as ISO strings
  const hc = P.headcount(D, { company }), td = P.today(D, { company }), pr = P.payroll(D, { company });
  const pipe = C.pipeline(D, { company }), tk = O.tasks(D, { company }), pj = O.projects(D, { company }), bud = F.expensesVsBudget(D, { company });
  return {
    cash: { value: cash.total, label: 'Cash & bank', sub: `${cash.banks.length} accounts` },
    receivables: { value: ar.total, label: 'Receivables', sub: `${fmtBDTk(ar.overdueTotal)} overdue`, alert: ar.overdueTotal > 0 },
    payables: { value: ap.total, label: 'Payables', sub: `${fmtBDTk(ap.overdueTotal)} overdue`, alert: ap.overdueTotal > 0 },
    revenue: { value: pl.totalIncome, label: 'Revenue MTD', sub: `${tr.vsPrev >= 0 ? '+' : ''}${tr.vsPrev}% vs last month`, trend: tr.vsPrev },
    expenses: { value: pl.totalOpex, label: 'Opex MTD', sub: bud.over.length ? `${bud.over.length} over budget` : `${pct(bud.totalSpent, bud.totalBudget)}% of budget`, alert: bud.over.length > 0 },
    profit: { value: plLast.netProfit, label: `Net profit · ${MONTHS[+lastMk.slice(5) - 1].slice(0, 3)}`, sub: `${plLast.margin}% margin · MTD ${fmtBDTk(pl.netProfit)}`, alert: plLast.netProfit < 0, mtd: pl.netProfit },
    headcount: { value: hc.total, label: 'Headcount', sub: `${fmtBDTk(hc.monthlyPayroll)}/month`, money: false },
    attendance: { value: td.presentPct, label: 'Present today', sub: td.weekend || td.holiday ? (td.holiday || 'weekend') : `${td.absent.length} absent · ${td.late.length} late`, unit: '%', money: false, alert: !td.weekend && !td.holiday && td.absent.length >= 5 },
    payroll: { value: pr.net, label: `Payroll · ${MONTHS[+pr.month.slice(5) - 1].slice(0, 3)}`, sub: pr.pending.length ? `${pr.pending.length} unpaid` : 'paid', alert: pr.pending.length > 0 },
    pipeline: { value: pipe.openValue, label: 'Pipeline', sub: `${pipe.open.length} open · ${pipe.conversion == null ? '—' : pipe.conversion + '%'} win`, money: true },
    tasks: { value: tk.overdue.length, label: 'Overdue tasks', sub: `${tk.open.length} open · ${tk.velocity} closed this week`, money: false, alert: tk.overdue.length > 0 },
    projects: { value: pj.atRisk.length, label: 'Projects at risk', sub: `${pj.active.length} active`, money: false, alert: pj.atRisk.length > 0 },
  };
}

export function approvals(D, { company = null } = {}) {
  const t = T(D);
  const exp = F.pendingExpenses(D, { company }).rows.map((e) => ({ kind: 'expense', id: e.id, title: `${e.title} — ${e.category}`, who: e.user_name, company_id: e.company_id, amount: e.amount, date: e.expense_date, note: `${e.payment_mode.replace('_', ' ')} · GL ${e.account_code}`, priority: e.amount > 100000 ? 'high' : 'normal' }));
  const lv = P.leaves(D, { company }).pending.map((l) => ({ kind: 'leave', id: l.id, title: `${l.leave_type} leave · ${l.days} day${l.days > 1 ? 's' : ''} from ${l.start_date}`, who: l.name, company_id: l.company_id, amount: null, date: l.applied_at, note: `${l.reason} · balance ${(l.balance.find((b) => b.type === l.leave_type) || {}).remaining ?? '?'} left`, priority: daysBetween(t, l.start_date) <= 2 ? 'high' : 'normal', flag: (l.balance.find((b) => b.type === l.leave_type) || {}).remaining < l.days ? 'exceeds balance' : null }));
  const ln = P.loans(D, { company });
  const adv = ln.advancesPending.map((a) => ({ kind: 'advance', id: a.id, title: `Salary advance for ${a.month}`, who: a.name, company_id: a.company_id, amount: a.amount, date: null, note: 'deducted from next payslip', priority: 'normal' }));
  const req = ln.requestsPending.map((r) => ({ kind: 'request', id: r.id, title: `${r.request_type} (${r.category})`, who: r.name, company_id: r.company_id, amount: r.amount, date: r.created_at, note: `status ${r.status} · deadline ${r.deadline}`, priority: r.deadline < t ? 'high' : 'normal' }));
  const pr = P.payroll(D, { company });
  const pay = pr.pending.length ? [{ kind: 'payroll', id: pr.month, title: `Payroll run ${MONTHS[+pr.month.slice(5) - 1]} — ${pr.pending.length} payslips`, who: 'Accounts', company_id: null, amount: sum(pr.pending, 'net_salary'), date: null, note: `gross ${fmtBDTk(pr.gross)} · deductions ${fmtBDTk(pr.deductions)}`, priority: new Date(t).getDate() >= 5 ? 'high' : 'normal' }] : [];
  const ps = (D.payment_schedules || []).filter((p) => p.status === 'pending' && (company == null || p.company_id === company) && p.type === 'pay' && p.priority === 'high' && daysBetween(t, p.scheduled_date) <= 3).map((p) => ({ kind: 'payment', id: p.id, title: `Pay ${p.party_name} — ${p.source_label}`, who: p.party_type, company_id: p.company_id, amount: +p.amount - (+p.paid_amount || 0), date: p.scheduled_date, note: `due ${p.scheduled_date}${p.reschedule_count ? ` · rescheduled ${p.reschedule_count}×` : ''}`, priority: 'high' }));
  const all = [].concat(pay, exp, lv, adv, req, ps).map((a) => Object.assign(a, { company: (D.companies.find((c) => c.id === a.company_id) || {}).short_name || '' }));
  const order = { high: 0, normal: 1 };
  all.sort((a, b) => order[a.priority] - order[b.priority] || (b.amount || 0) - (a.amount || 0));
  return { items: all, count: all.length, amount: sum(all, 'amount'), byKind: ['payroll', 'expense', 'leave', 'advance', 'request', 'payment'].map((k) => ({ kind: k, count: all.filter((a) => a.kind === k).length, amount: sum(all.filter((a) => a.kind === k), 'amount') })).filter((x) => x.count) };
}

/* Plug-in decision providers: EonErpDecisions.addProvider((D, {company}) => [decision, …]) */
const _providers = [];
export function addProvider(fn) { if (typeof fn === 'function' && !_providers.includes(fn)) _providers.push(fn); return () => { const i = _providers.indexOf(fn); if (i >= 0) _providers.splice(i, 1); }; }
export function all(D, { company = null } = {}) {
  const extra = _providers.flatMap((fn) => { try { return fn(D, { company }) || []; } catch (e) { console.warn('[EON decisions] provider failed:', e); return []; } });
  const list = [].concat(F.decisions(D, { company }), P.decisions(D, { company }), C.decisions(D, { company }), O.decisions(D, { company }), extra);
  list.forEach((d) => { d.layerLabel = LAYERS[d.layer] || d.layer; d.severityLabel = SEVERITY[d.severity] || 'info'; });
  return list.sort((a, b) => b.severity - a.severity || (b.amount || 0) - (a.amount || 0));
}

/** first name the Bangladeshi way: skip Md/Mohammad/Muhammad honorifics */
export function firstName(full) { const parts = String(full || '').trim().split(/\s+/).filter(Boolean); if (!parts.length) return ''; const skip = /^(md\.?|mohammad|muhammad|mohammed|mst\.?|mrs?\.?|ms\.?|dr\.?)$/i; return parts.find((p) => !skip.test(p)) || parts[0]; }

export function brief(D, { company = null, name = null } = {}) {
  const t = T(D); const k = kpis(D, { company }); const list = all(D, { company }); const ap = approvals(D, { company });
  const critical = list.filter((d) => d.severity >= 4), medium = list.filter((d) => d.severity === 3);
  const prefs = (typeof window !== 'undefined' && window.EON_PREFS) || {};
  const who = name || prefs.name || firstName(D.meta && D.meta.boss && D.meta.boss.name) || 'Boss';
  const hour = new Date().getHours(); const greet = hour < 12 ? 'Good morning' : hour < 17 ? 'Good afternoon' : 'Good evening';
  const dt = new Date(t + 'T00:00:00'); const dayName = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][dt.getDay()];
  const lines = [];
  lines.push(`${greet}, ${who}. ${dayName} ${dt.getDate()} ${MONTHS[dt.getMonth()]}. Cash stands at ${fmtBDTk(k.cash.value)}; revenue this month ${fmtBDTk(k.revenue.value)}, tracking ${k.revenue.trend >= 0 ? 'up' : 'down'} ${Math.abs(k.revenue.trend)}% on last month, which closed at a net ${k.profit.value >= 0 ? 'profit' : 'loss'} of ${fmtBDTk(Math.abs(k.profit.value))}.`);
  if (k.receivables.alert || k.payables.alert) lines.push(`Overdue: ${fmtBDTk(F.receivables(D, { company }).overdueTotal)} to collect, ${fmtBDTk(F.payables(D, { company }).overdueTotal)} to pay.`);
  const td = P.today(D, { company }); if (!td.weekend && !td.holiday) lines.push(`${td.present.length} of ${td.total} are in today, ${td.absent.length} absent, ${td.late.length} late.`);
  if (critical.length) lines.push(`${critical.length} thing${critical.length > 1 ? 's' : ''} need${critical.length > 1 ? '' : 's'} you today: ${critical.slice(0, 3).map((d) => d.title).join('; ')}.`);
  else if (medium.length) lines.push(`Nothing critical. ${medium.length} items to watch: ${medium.slice(0, 2).map((d) => d.title).join('; ')}.`);
  else lines.push('Nothing critical, nothing burning. A good day to look forward.');
  if (ap.count) lines.push(`${ap.count} approvals are waiting on you${ap.amount ? ` — ${fmtBDTk(ap.amount)} in total` : ''}.`);
  const top = list[0];
  if (top) lines.push(`If you do one thing: ${top.recommend}`);
  return { date: t, greeting: greet, speak: lines.join(' '), lines, kpis: k, decisions: list, critical, approvals: ap, top };
}

export const EonErpDecisions = { kpis, approvals, all, brief, firstName, addProvider, LAYERS, SEVERITY };
if (typeof window !== 'undefined') window.EonErpDecisions = Object.assign(window.EonErpDecisions || {}, EonErpDecisions);
export default EonErpDecisions;
