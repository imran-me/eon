/* ============================================================
   EON · ERP operations layer — tasks, projects, to-dos and the
   monitoring view of who is carrying what. Decisions on overdue
   work, projects at risk, overloaded people, idle capacity.
   ============================================================ */
import { iso, addDays, daysBetween, indexBy, groupBy, sum, fmtBDT, fmtBDTk, pct } from './dataset.js';

const T = (D) => (D.meta && D.meta.today) || iso(new Date());
const inCompany = (row, cid) => cid == null || row.company_id === cid;
const companyName = (D, id) => (D.companies.find((c) => c.id === id) || {}).short_name || ('#' + id);
const label = (s) => String(s || '').replace(/_/g, ' ');

export function tasks(D, { company = null } = {}) {
  const t = T(D); const E = indexBy(D.employees || []);
  const all = (D.tasks || []).filter((k) => inCompany(k, company)).map((k) => Object.assign({}, k, { assignees: (k.assigned_to || []).map((id) => (E.get(id) || {}).name).filter(Boolean), company: companyName(D, k.company_id) }));
  const open = all.filter((k) => k.status !== 'done');
  const overdue = open.filter((k) => k.due_date < t).map((k) => Object.assign(k, { days_overdue: daysBetween(k.due_date, t) })).sort((a, b) => b.days_overdue - a.days_overdue);
  const dueToday = open.filter((k) => k.due_date === t), dueWeek = open.filter((k) => k.due_date > t && daysBetween(t, k.due_date) <= 7);
  const done7 = all.filter((k) => k.status === 'done' && k.completed_at && daysBetween(k.completed_at, t) <= 7), done7prev = all.filter((k) => k.status === 'done' && k.completed_at && daysBetween(k.completed_at, t) > 7 && daysBetween(k.completed_at, t) <= 14);
  const byStatus = ['todo', 'in_progress', 'review', 'done'].map((s) => ({ status: s, label: label(s), count: all.filter((k) => k.status === s).length }));
  const byPriority = ['high', 'medium', 'low'].map((p) => ({ priority: p, open: open.filter((k) => k.priority === p).length, overdue: overdue.filter((k) => k.priority === p).length }));
  // load per person
  const load = new Map();
  open.forEach((k) => (k.assigned_to || []).forEach((id) => { const e = E.get(id); if (!e) return; const r = load.get(id) || { id, name: e.name, department: e.department, company: companyName(D, e.company_id), open: 0, overdue: 0, high: 0 }; r.open++; if (k.due_date < t) r.overdue++; if (k.priority === 'high') r.high++; load.set(id, r); }));
  const loadRows = [...load.values()].sort((a, b) => b.overdue - a.overdue || b.open - a.open);
  const idle = (D.employees || []).filter((e) => e.status === 'active' && inCompany(e, company) && !load.has(e.id) && /Engineer|Designer|Developer|Officer|Executive|Coordinator|Supervisor/.test(e.designation)).map((e) => ({ id: e.id, name: e.name, designation: e.designation, company: companyName(D, e.company_id) }));
  return { all, open, overdue, dueToday, dueWeek, done7, done7prev, velocity: done7.length, velocityPrev: done7prev.length, byStatus, byPriority, load: loadRows, overloaded: loadRows.filter((r) => r.open >= 6 || r.overdue >= 3), idle };
}
export function projects(D, { company = null } = {}) {
  const t = T(D); const E = indexBy(D.employees || []);
  const all = (D.projects || []).filter((p) => inCompany(p, company)).map((p) => {
    const total = Math.max(1, daysBetween(p.start_date, p.end_date)); const elapsed = Math.min(1, Math.max(0, daysBetween(p.start_date, t) / total));
    const tk = (D.tasks || []).filter((k) => k.project_id === p.id); const tkDone = tk.filter((k) => k.status === 'done').length; const tkOverdue = tk.filter((k) => k.status !== 'done' && k.due_date < t).length;
    const scheduleGap = Math.round(elapsed * 100) - (p.progress || 0);   // + means behind
    const budgetPct = p.budget ? Math.round((p.spent || 0) / p.budget * 100) : null;
    const overBudget = budgetPct != null && budgetPct > 100; const burnAhead = budgetPct != null && budgetPct > Math.round(elapsed * 100) + 15;
    const late = p.status !== 'completed' && p.end_date < t;
    const risk = (late ? 3 : 0) + (scheduleGap > 25 ? 2 : scheduleGap > 12 ? 1 : 0) + (overBudget ? 2 : burnAhead ? 1 : 0) + (tkOverdue >= 3 ? 1 : 0) + (p.status === 'on_hold' ? 1 : 0);
    return Object.assign({}, p, { company: companyName(D, p.company_id), elapsedPct: Math.round(elapsed * 100), scheduleGap, budgetPct, overBudget, burnAhead, late, tasks: tk.length, tasksDone: tkDone, tasksOverdue: tkOverdue, risk, riskLabel: risk >= 4 ? 'critical' : risk >= 2 ? 'at risk' : 'on track', daysLeft: daysBetween(t, p.end_date), manager: p.manager || (E.get(p.manager_id) || {}).name });
  });
  const active = all.filter((p) => p.status !== 'completed' && p.status !== 'cancelled');
  const atRisk = active.filter((p) => p.risk >= 2).sort((a, b) => b.risk - a.risk || b.budget - a.budget);
  const dueSoon = active.filter((p) => !p.late && p.daysLeft <= 14).sort((a, b) => a.daysLeft - b.daysLeft);
  return { all, active, atRisk, dueSoon, completed: all.filter((p) => p.status === 'completed'), onHold: all.filter((p) => p.status === 'on_hold'), budget: sum(active, 'budget'), spent: sum(active, 'spent') };
}
export function todos(D, { company = null } = {}) {
  const t = T(D);
  const all = (D.office_todos || []).filter((k) => inCompany(k, company)).map((k) => Object.assign({}, k, { company: companyName(D, k.company_id) }));
  const open = all.filter((k) => k.status !== 'completed');
  return { all, open, overdue: open.filter((k) => k.due_date < t).sort((a, b) => a.due_date.localeCompare(b.due_date)), dueWeek: open.filter((k) => k.due_date >= t && daysBetween(t, k.due_date) <= 7), high: open.filter((k) => k.priority === 'high') };
}
export function decisions(D, { company = null } = {}) {
  const out = []; const push = (d) => out.push(Object.assign({ layer: 'ops', company_id: company }, d));
  const tk = tasks(D, { company }), pj = projects(D, { company }), td = todos(D, { company });
  if (pj.atRisk.length) { const p = pj.atRisk[0]; push({ id: 'projects-risk', severity: pj.atRisk.some((x) => x.riskLabel === 'critical') ? 4 : 3, title: `${pj.atRisk.length} project${pj.atRisk.length > 1 ? 's' : ''} at risk — worst: ${p.project_name}`, why: pj.atRisk.slice(0, 4).map((x) => `${x.project_name} (${x.company}) — ${x.late ? `${-x.daysLeft}d past end date` : `${x.progress}% done at ${x.elapsedPct}% of time`}${x.budgetPct != null ? `, budget ${x.budgetPct}%` : ''}${x.tasksOverdue ? `, ${x.tasksOverdue} tasks overdue` : ''} · ${x.manager}`), recommend: `Ask ${p.manager} for a recovery plan on ${p.project_name} by tomorrow: re-baseline the end date, or add ${p.tasksOverdue >= 3 ? 'one engineer' : 'client sign-off'} — and stop new scope.`, amount: p.budget, actions: [{ label: 'Open projects', kind: 'navigate', href: 'operations.html#projects' }], entities: pj.atRisk.slice(0, 4).map((x) => ({ type: 'project', id: x.id, label: x.project_name })) }); }
  if (tk.overdue.length) push({ id: 'tasks-overdue', severity: tk.overdue.filter((k) => k.priority === 'high').length >= 3 ? 4 : tk.overdue.length >= 10 ? 3 : 2, title: `${tk.overdue.length} tasks are overdue (${tk.overdue.filter((k) => k.priority === 'high').length} high priority)`, why: tk.overdue.slice(0, 4).map((k) => `${k.title} — ${k.project} · ${k.assignees.join(', ') || 'unassigned'} · ${k.days_overdue}d late`).concat(tk.load[0] && tk.load[0].overdue ? [`${tk.load[0].name} holds ${tk.load[0].overdue} of them`] : []), recommend: tk.overloaded.length ? `${tk.overloaded[0].name} is overloaded (${tk.overloaded[0].open} open, ${tk.overloaded[0].overdue} overdue) — move two tasks to ${tk.idle[0] ? tk.idle[0].name + ' who has nothing assigned' : 'someone with capacity'}.` : 'Ask each owner for a new date today; anything unowned gets an owner by noon.', actions: [{ label: 'Open tasks', kind: 'navigate', href: 'operations.html#tasks' }] });
  if (tk.overloaded.length && !tk.overdue.length) push({ id: 'overloaded', severity: 2, title: `${tk.overloaded.length} people carry 6+ open tasks`, why: tk.overloaded.slice(0, 3).map((r) => `${r.name} — ${r.open} open, ${r.high} high`), recommend: 'Rebalance before it becomes overdue work.' });
  if (tk.idle.length >= 3) push({ id: 'idle-capacity', severity: 1, title: `${tk.idle.length} skilled staff have no task assigned`, why: tk.idle.slice(0, 5).map((e) => `${e.name} — ${e.designation}, ${e.company}`), recommend: 'Either the board is out of date or there is spare capacity — both worth knowing.', actions: [{ label: 'Open load', kind: 'navigate', href: 'operations.html#load' }] });
  if (tk.velocityPrev && tk.velocity < tk.velocityPrev * 0.6) push({ id: 'velocity-drop', severity: 2, title: `Delivery slowed: ${tk.velocity} tasks closed this week vs ${tk.velocityPrev} last week`, why: [`Open tasks: ${tk.open.length}`], recommend: 'Check for a blocker — one stuck dependency usually explains it.' });
  if (td.overdue.length) push({ id: 'todos-overdue', severity: td.overdue.some((k) => k.priority === 'high') ? 3 : 2, title: `${td.overdue.length} office to-dos are overdue${td.overdue.some((k) => /VAT|licence|tax|insurance/i.test(k.title)) ? ' — including a compliance item' : ''}`, why: td.overdue.slice(0, 4).map((k) => `${k.title} — ${(k.assignee_names || []).join(', ')} · due ${k.due_date}`), recommend: 'Compliance first (licence, VAT, insurance); the rest by Thursday.', actions: [{ label: 'Open to-dos', kind: 'navigate', href: 'operations.html#todos' }] });
  if (pj.dueSoon.length) push({ id: 'projects-due', severity: 2, title: `${pj.dueSoon.length} project${pj.dueSoon.length > 1 ? 's' : ''} due within 14 days`, why: pj.dueSoon.slice(0, 4).map((x) => `${x.project_name} — ${x.daysLeft}d left, ${x.progress}% done`), recommend: 'Confirm handover dates with each client now, not on the day.' });
  return out.sort((a, b) => b.severity - a.severity);
}

export const EonErpOps = { tasks, projects, todos, decisions };
if (typeof window !== 'undefined') window.EonErpOps = Object.assign(window.EonErpOps || {}, EonErpOps);
export default EonErpOps;
