/* ============================================================
   EON · ERP people layer — HR, attendance, payroll, monitoring
   and evaluation. Pure functions over the dataset. Uses the ERP’s
   own payroll rules (docs/erp-domain-map.md §4) so what EON says
   matches what the payslip will say.
   ============================================================ */
import { iso, addDays, daysBetween, monthKey, daysInMonth, MONTHS, indexBy, groupBy, sum, fmtBDT, fmtBDTk, pct } from './dataset.js';

const T = (D) => (D.meta && D.meta.today) || iso(new Date());
const inCompany = (row, cid) => cid == null || row.company_id === cid;
const empIndex = (D) => indexBy(D.employees || []);
const companyName = (D, id) => (D.companies.find((c) => c.id === id) || {}).short_name || ('#' + id);

/* ---------- 1. headcount ---------- */
export function headcount(D, { company = null } = {}) {
  const emps = (D.employees || []).filter((e) => e.status === 'active' && inCompany(e, company));
  const byCompany = [...groupBy(emps, 'company_id').entries()].map(([cid, a]) => ({ company_id: cid, company: companyName(D, cid), count: a.length, payroll: sum(a, 'salary') })).sort((a, b) => b.count - a.count);
  const byDept = [...groupBy(emps, 'department').entries()].map(([d, a]) => ({ department: d, count: a.length, payroll: sum(a, 'salary') })).sort((a, b) => b.count - a.count);
  const newJoiners = emps.filter((e) => daysBetween(e.joining_date, T(D)) <= 60);
  return { total: emps.length, byCompany, byDept, monthlyPayroll: sum(emps, 'salary'), newJoiners, avgSalary: emps.length ? Math.round(sum(emps, 'salary') / emps.length) : 0 };
}

/* ---------- 2. today ---------- */
export function today(D, { company = null } = {}) {
  const t = T(D); const E = empIndex(D);
  const emps = (D.employees || []).filter((e) => e.status === 'active' && inCompany(e, company));
  const rows = (D.attendances || []).filter((a) => a.date === t && inCompany(a, company));
  const A = indexBy(rows, 'user_id');
  const w = new Date(t + 'T00:00:00').getDay(); const weekend = w === 5 || w === 6;
  const holiday = (D.holidays || []).find((h) => t >= h.start_date && t <= h.end_date);
  const present = [], absent = [], late = [], onLeave = [], notYet = [];
  emps.forEach((e) => {
    const a = A.get(e.id);
    const item = { id: e.id, name: e.name, department: e.department, designation: e.designation, company: companyName(D, e.company_id), company_id: e.company_id, check_in: a && a.check_in, late_minutes: a ? a.late_minutes : 0, source: a && a.source };
    if (!a) { if (!weekend && !holiday) notYet.push(item); return; }
    if (a.status === 'present') { present.push(item); if (a.late_minutes > 0) late.push(item); }
    else if (a.status === 'absent') absent.push(item);
    else if (a.status === 'leave') onLeave.push(item);
  });
  late.sort((a, b) => b.late_minutes - a.late_minutes);
  const online = emps.filter((e) => e.last_seen_at && (Date.now() - Date.parse(e.last_seen_at)) < 5 * 60000).map((e) => ({ id: e.id, name: e.name, department: e.department }));
  return { date: t, weekend, holiday: holiday ? holiday.name : null, total: emps.length, present, absent, late, onLeave, notYet, online, presentPct: emps.length ? Math.round(present.length / emps.length * 100) : 0 };
}

/* ---------- 3. patterns over the last N days ---------- */
export function patterns(D, { company = null, days = 30 } = {}) {
  const t = T(D); const from = iso(addDays(t, -days)); const E = empIndex(D);
  const rows = (D.attendances || []).filter((a) => a.date >= from && a.date <= t && inCompany(a, company));
  const by = groupBy(rows, 'user_id'); const out = [];
  by.forEach((arr, uid) => {
    const e = E.get(uid); if (!e || e.status !== 'active') return;
    const work = arr.filter((a) => a.status !== 'holiday'); const present = work.filter((a) => a.status === 'present');
    const absent = work.filter((a) => a.status === 'absent').length; const leave = work.filter((a) => a.status === 'leave').length;
    const lateDays = present.filter((a) => a.late_minutes > 0).length; const lateMin = sum(present, 'late_minutes'); const earlyMin = sum(present, 'early_minutes'); const otMin = sum(present, 'overtime_minutes');
    out.push({ id: uid, name: e.name, department: e.department, company: companyName(D, e.company_id), company_id: e.company_id, workdays: work.length, present: present.length, absent, leave, attendancePct: work.length ? Math.round(present.length / work.length * 100) : 0, lateDays, latePct: present.length ? Math.round(lateDays / present.length * 100) : 0, lateMinutes: lateMin, earlyMinutes: earlyMin, overtimeMinutes: otMin });
  });
  const chronicLate = out.filter((r) => r.latePct >= 30 && r.lateDays >= 4).sort((a, b) => b.latePct - a.latePct);
  const chronicAbsent = out.filter((r) => r.absent >= 3 && r.attendancePct < 85).sort((a, b) => b.absent - a.absent);
  const overtimeHeavy = out.filter((r) => r.overtimeMinutes >= 600).sort((a, b) => b.overtimeMinutes - a.overtimeMinutes);
  const perfect = out.filter((r) => r.absent === 0 && r.lateDays === 0 && r.workdays >= 10);
  const avgAttendance = out.length ? Math.round(sum(out, 'attendancePct') / out.length) : 0;
  return { days, rows: out, chronicLate, chronicAbsent, overtimeHeavy, perfect, avgAttendance };
}

/* ---------- 4. leaves ---------- */
export function leaves(D, { company = null } = {}) {
  const t = T(D); const E = empIndex(D); const year = t.slice(0, 4);
  const all = (D.leaves || []).filter((l) => inCompany(l, company));
  const pending = all.filter((l) => l.status === 'pending').map((l) => Object.assign({}, l, { name: (E.get(l.user_id) || {}).name, department: (E.get(l.user_id) || {}).department, balance: leaveBalance(D, l.user_id) }));
  const onLeaveToday = all.filter((l) => l.status === 'approved' && t >= l.start_date && t <= l.end_date).map((l) => Object.assign({}, l, { name: (E.get(l.user_id) || {}).name }));
  const upcoming = all.filter((l) => l.status === 'approved' && l.start_date > t && daysBetween(t, l.start_date) <= 14).map((l) => Object.assign({}, l, { name: (E.get(l.user_id) || {}).name }));
  return { pending, onLeaveToday, upcoming, year };
}
export function leaveBalance(D, uid) {
  const t = T(D); const year = t.slice(0, 4);
  const types = D.leave_types || [];
  const taken = (D.leaves || []).filter((l) => l.user_id === uid && l.status === 'approved' && l.start_date.slice(0, 4) === year);
  return types.filter((ty) => ty.name !== 'Maternity').map((ty) => { const used = sum(taken.filter((l) => l.leave_type === ty.name), 'days'); return { type: ty.name, entitlement: ty.max_leaves_count, used, remaining: ty.max_leaves_count - used }; });
}

/* ---------- 5. payroll ---------- */
const keyOf = (p) => p.month_key || `${p.year}-${String(MONTHS.indexOf(p.month) + 1).padStart(2, '0')}`;
/** Latest month that has a payroll run — the ERP generates month M on the 1st of M+1, so mid-month the “current” run is last month’s. */
export function latestPayrollMonth(D, { company = null } = {}) {
  const keys = (D.payroll || []).filter((p) => inCompany(p, company)).map(keyOf).sort();
  return keys.length ? keys[keys.length - 1] : monthKey(T(D));
}
export function payroll(D, { company = null, month = null } = {}) {
  const t = T(D); const mk = month || latestPayrollMonth(D, { company }); const E = empIndex(D);
  const rows = (D.payroll || []).filter((p) => keyOf(p) === mk && inCompany(p, company)).map((p) => Object.assign({}, p, { name: (E.get(p.user_id) || {}).name, department: (E.get(p.user_id) || {}).department, company: companyName(D, p.company_id) }));
  const prevKey = (() => { const d = new Date(mk + '-01T00:00:00'); d.setMonth(d.getMonth() - 1); return monthKey(d); })();
  const prev = (D.payroll || []).filter((p) => keyOf(p) === prevKey && inCompany(p, company));
  const S = (a, k) => sum(a, k);
  const byCompany = [...groupBy(rows, 'company_id').entries()].map(([cid, a]) => ({ company_id: cid, company: companyName(D, cid), gross: S(a, 'gross_salary'), net: S(a, 'net_salary'), deductions: S(a, 'total_deductions'), heads: a.length, pending: a.filter((p) => p.status === 'Pending').length })).sort((a, b) => b.net - a.net);
  const topDeductions = rows.filter((p) => p.total_deductions > 0).sort((a, b) => b.total_deductions - a.total_deductions).slice(0, 5);
  return { month: mk, rows, heads: rows.length, gross: S(rows, 'gross_salary'), net: S(rows, 'net_salary'), deductions: S(rows, 'total_deductions'), late: S(rows, 'late_deduction'), absent: S(rows, 'absent_deduction'), overtime: S(rows, 'overtime_salary'), loans: S(rows, 'loan_deduction'), advances: S(rows, 'advance_salary_deduction'), pending: rows.filter((p) => p.status === 'Pending'), paid: rows.filter((p) => p.status === 'Paid'), prevNet: S(prev, 'net_salary'), prevGross: S(prev, 'gross_salary'), byCompany, topDeductions };
}
export function loans(D, { company = null } = {}) {
  const E = empIndex(D);
  const rows = (D.loans || []).map((l) => Object.assign({}, l, { name: (E.get(l.user_id) || {}).name, company_id: (E.get(l.user_id) || {}).company_id })).filter((l) => inCompany(l, company));
  const running = rows.filter((l) => l.status === 'Running');
  const adv = (D.advance_salaries || []).map((a) => Object.assign({}, a, { name: (E.get(a.user_id) || {}).name, company_id: (E.get(a.user_id) || {}).company_id })).filter((a) => inCompany(a, company));
  const req = (D.employee_requests || []).map((r) => Object.assign({}, r, { name: (E.get(r.user_id) || {}).name, company_id: (E.get(r.user_id) || {}).company_id })).filter((r) => inCompany(r, company));
  return { running, outstanding: sum(running, 'remaining_amount'), monthlyRecovery: sum(running, 'monthly_deduction'), advancesPending: adv.filter((a) => a.status === 'Pending'), advancesApproved: adv.filter((a) => a.status === 'Approved'), requestsPending: req.filter((r) => ['pending', 'under_review'].includes(r.status)) };
}

/* ---------- 6. find & evaluate ---------- */
export function findEmployee(D, text) {
  const q = String(text || '').toLowerCase().replace(/[^a-z\s]/g, ' ').trim(); if (!q) return null;
  const toks = q.split(/\s+/).filter((w) => w.length >= 3);
  let best = null, bestScore = 0;
  (D.employees || []).forEach((e) => {
    const n = e.name.toLowerCase(); let s = 0;
    if (q.includes(n)) s = 100; else toks.forEach((tk) => { if (n.split(/\s+/).some((p) => p === tk)) s += 10; else if (n.includes(tk)) s += 4; });
    if (s > bestScore) { best = e; bestScore = s; }
  });
  return bestScore >= 8 ? best : null;
}
export function evaluate(D, uid, { days = 30 } = {}) {
  const E = empIndex(D); const e = E.get(uid); if (!e) return null;
  const t = T(D); const from = iso(addDays(t, -days));
  const att = (D.attendances || []).filter((a) => a.user_id === uid && a.date >= from && a.date <= t && a.status !== 'holiday');
  const present = att.filter((a) => a.status === 'present'); const attendancePct = att.length ? present.length / att.length : 1;
  const lateDays = present.filter((a) => a.late_minutes > 0).length; const punctuality = present.length ? 1 - lateDays / present.length : 1;
  const tasks = (D.tasks || []).filter((k) => (k.assigned_to || []).includes(uid));
  const recent = tasks.filter((k) => k.due_date >= from || (k.completed_at && k.completed_at >= from) || k.status !== 'done');
  const done = recent.filter((k) => k.status === 'done'); const onTime = done.filter((k) => !k.completed_at || k.completed_at <= k.due_date);
  const overdue = recent.filter((k) => k.status !== 'done' && k.due_date < t);
  const delivery = done.length ? onTime.length / done.length : (recent.length ? 0.6 : null);
  const load = recent.filter((k) => k.status !== 'done').length;
  const leads = (D.leads || []).filter((l) => l.assigned_to === uid); const won = leads.filter((l) => l.status === 'won').length; const lost = leads.filter((l) => l.status === 'lost').length;
  const salesRate = won + lost ? won / (won + lost) : null;
  const ot = sum(present, 'overtime_minutes');
  // composite: attendance 30, punctuality 20, delivery 35 (or sales 35 for sales roles), volume 15
  let score = attendancePct * 30 + punctuality * 20;
  const isSales = /Sales/.test(e.designation) || /Sales/.test(e.department);
  const perf = isSales && salesRate != null ? salesRate : (delivery != null ? delivery : 0.7);
  score += perf * 35;
  const volume = Math.min(1, (done.length + won) / 6); score += volume * 15;
  score = Math.round(score);
  const grade = score >= 85 ? 'A' : score >= 70 ? 'B' : score >= 55 ? 'C' : 'D';
  const strengths = [], concerns = [];
  if (attendancePct >= 0.95) strengths.push(`attendance ${Math.round(attendancePct * 100)}%`); else if (attendancePct < 0.85) concerns.push(`attendance only ${Math.round(attendancePct * 100)}% (${att.length - present.length} days missed)`);
  if (punctuality >= 0.9) strengths.push('punctual'); else if (punctuality < 0.7) concerns.push(`late on ${lateDays} of ${present.length} days`);
  if (isSales && salesRate != null) { (salesRate >= 0.5 ? strengths : concerns).push(`closes ${Math.round(salesRate * 100)}% of decided leads (${won}W/${lost}L)`); }
  if (delivery != null && done.length) { (delivery >= 0.75 ? strengths : concerns).push(`${onTime.length} of ${done.length} tasks on time`); }
  if (overdue.length) concerns.push(`${overdue.length} task${overdue.length > 1 ? 's' : ''} overdue now`);
  if (ot >= 600) strengths.push(`${Math.round(ot / 60)}h overtime logged`);
  const lb = leaveBalance(D, uid); const loan = (D.loans || []).find((l) => l.user_id === uid && l.status === 'Running');
  const pay = (D.payroll || []).filter((p) => p.user_id === uid).sort((a, b) => (b.month_key || '').localeCompare(a.month_key || ''))[0];
  return { employee: e, days, score, grade, attendancePct: Math.round(attendancePct * 100), punctuality: Math.round(punctuality * 100), lateDays, presentDays: present.length, workdays: att.length, tasksDone: done.length, tasksOnTime: onTime.length, tasksOverdue: overdue.length, load, leadsWon: won, leadsLost: lost, salesRate: salesRate != null ? Math.round(salesRate * 100) : null, overtimeHours: Math.round(ot / 60), strengths, concerns, leaveBalance: lb, loan, lastPay: pay,
    narrative: `${e.name} (${e.designation}, ${companyName(D, e.company_id)}) scores ${score}/100 — grade ${grade}. ${strengths.length ? 'Strengths: ' + strengths.join(', ') + '.' : ''} ${concerns.length ? 'Watch: ' + concerns.join('; ') + '.' : 'No concerns in the last ' + days + ' days.'}` };
}
export function ranking(D, { company = null, limit = 10 } = {}) {
  const emps = (D.employees || []).filter((e) => e.status === 'active' && inCompany(e, company) && e.id !== 1);
  const rows = emps.map((e) => evaluate(D, e.id)).filter(Boolean).sort((a, b) => b.score - a.score);
  return { top: rows.slice(0, limit), bottom: rows.slice(-limit).reverse(), all: rows };
}

/* ---------- 7. decisions ---------- */
export function decisions(D, { company = null } = {}) {
  const out = []; const push = (d) => out.push(Object.assign({ layer: 'people', company_id: company }, d));
  const td = today(D, { company }), pt = patterns(D, { company }), lv = leaves(D, { company }), pr = payroll(D, { company }), ln = loans(D, { company });
  if (!td.weekend && !td.holiday) {
    if (td.absent.length) push({ id: 'absent-today', severity: td.absent.length >= 5 ? 3 : 2, title: `${td.absent.length} absent today without leave${td.late.length ? `, ${td.late.length} came late` : ''}`, why: [`Present ${td.present.length}/${td.total} (${td.presentPct}%)`, `Absent: ${td.absent.slice(0, 5).map((a) => a.name).join(', ')}${td.absent.length > 5 ? '…' : ''}`, td.late[0] ? `Latest arrival: ${td.late[0].name} +${td.late[0].late_minutes} min` : ''].filter(Boolean), recommend: td.absent.length >= 3 ? 'Ask HR to call the absentees before noon — unplanned absence above 5% is a pattern, not noise.' : 'HR to log the reason for each.', actions: [{ label: 'Open attendance', kind: 'navigate', href: 'people.html#today' }] });
  }
  if (pt.chronicLate.length) push({ id: 'chronic-late', severity: 3, title: `${pt.chronicLate.length} employees are late on 30%+ of days (last ${pt.days} days)`, why: pt.chronicLate.slice(0, 4).map((r) => `${r.name} (${r.department}) — late ${r.lateDays}/${r.present} days, ${r.lateMinutes} min`), recommend: `A written warning to the top two; late deduction only bites at 120 min/month, so ${pt.chronicLate.filter((r) => r.lateMinutes >= 120).length} of them are already being deducted.`, actions: [{ label: 'Open patterns', kind: 'navigate', href: 'people.html#patterns' }], entities: pt.chronicLate.slice(0, 4).map((r) => ({ type: 'employee', id: r.id, label: r.name })) });
  if (pt.chronicAbsent.length) push({ id: 'chronic-absent', severity: 3, title: `${pt.chronicAbsent.length} employees below 85% attendance with 3+ absences`, why: pt.chronicAbsent.slice(0, 4).map((r) => `${r.name} — ${r.absent} absent, ${r.attendancePct}% attendance`), recommend: 'Line managers to hold a one-to-one this week; check if it is health, commute or disengagement.', entities: pt.chronicAbsent.slice(0, 4).map((r) => ({ type: 'employee', id: r.id, label: r.name })) });
  if (lv.pending.length) push({ id: 'leaves-pending', severity: lv.pending.some((l) => daysBetween(T(D), l.start_date) <= 2) ? 3 : 2, title: `${lv.pending.length} leave request${lv.pending.length > 1 ? 's' : ''} awaiting approval`, why: lv.pending.slice(0, 4).map((l) => `${l.name} — ${l.leave_type} ${l.days}d from ${l.start_date}${(l.balance.find((b) => b.type === l.leave_type) || {}).remaining < l.days ? ' (exceeds balance!)' : ''}`), recommend: lv.pending.some((l) => (l.balance.find((b) => b.type === l.leave_type) || {}).remaining < l.days) ? 'One request exceeds the balance — reject or convert to unpaid; approve the rest.' : 'All within balance — approve unless a project deadline collides.', actions: [{ label: 'Open approvals', kind: 'navigate', href: 'index.html#approvals' }], approval: true });
  if (pr.pending.length) push({ id: 'payroll-pending', severity: new Date(T(D)).getDate() >= 5 ? 4 : 2, title: `${pr.pending.length} payslips for ${MONTHS[+pr.month.slice(5) - 1]} are unpaid — ${fmtBDTk(sum(pr.pending, 'net_salary'))}`, why: [`Net payroll ${fmtBDTk(pr.net)} vs ${fmtBDTk(pr.prevNet)} last month (${pr.prevNet ? (pr.net >= pr.prevNet ? '+' : '') + Math.round((pr.net - pr.prevNet) / pr.prevNet * 100) : 0}%)`, `Deductions ${fmtBDTk(pr.deductions)}: late ${fmtBDTk(pr.late)}, absence ${fmtBDTk(pr.absent)}, loans ${fmtBDTk(pr.loans)}`], recommend: new Date(T(D)).getDate() >= 5 ? 'Salaries are late — release today; late pay is the fastest way to lose good people.' : 'Approve the payroll run so it goes out by the 5th.', amount: sum(pr.pending, 'net_salary'), actions: [{ label: 'Open payroll', kind: 'navigate', href: 'people.html#payroll' }], approval: true });
  if (pr.prevNet && pr.gross > pr.prevGross * 1.08) push({ id: 'payroll-jump', severity: 2, title: `Gross payroll is up ${Math.round((pr.gross - pr.prevGross) / pr.prevGross * 100)}% month on month`, why: [`${fmtBDTk(pr.gross)} vs ${fmtBDTk(pr.prevGross)}`], recommend: 'Confirm the new joiners and increments were all approved.', amount: pr.gross - pr.prevGross });
  if (ln.advancesPending.length || ln.requestsPending.length) push({ id: 'requests-pending', severity: 2, title: `${ln.advancesPending.length} salary advance${ln.advancesPending.length === 1 ? '' : 's'} and ${ln.requestsPending.length} employee request${ln.requestsPending.length === 1 ? '' : 's'} need a decision`, why: ln.advancesPending.slice(0, 3).map((a) => `Advance: ${a.name} ${fmtBDT(a.amount)}`).concat(ln.requestsPending.slice(0, 3).map((r) => `Request: ${r.name} — ${r.request_type} ${fmtBDT(r.amount)}`)), recommend: 'Approve advances under half a month’s salary for staff with clean attendance; route the rest through HR.', actions: [{ label: 'Open approvals', kind: 'navigate', href: 'index.html#approvals' }], approval: true });
  const hc = headcount(D, { company }); if (hc.newJoiners.length) push({ id: 'new-joiners', severity: 1, title: `${hc.newJoiners.length} joined in the last 60 days`, why: hc.newJoiners.slice(0, 4).map((e) => `${e.name} — ${e.designation}, ${companyName(D, e.company_id)}`), recommend: 'Schedule 30-day check-ins; onboarding attrition happens in the first quarter.' });
  return out.sort((a, b) => b.severity - a.severity);
}

export const EonErpPeople = { headcount, today, patterns, leaves, leaveBalance, payroll, latestPayrollMonth, loans, findEmployee, evaluate, ranking, decisions };
if (typeof window !== 'undefined') window.EonErpPeople = Object.assign(window.EonErpPeople || {}, EonErpPeople);
export default EonErpPeople;
