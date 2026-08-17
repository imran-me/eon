/* ============================================================
   EON · ERP CRM layer — pipeline health, stale leads, follow-ups,
   conversion, agent performance, deals closing — and decisions.
   ============================================================ */
import { iso, addDays, daysBetween, indexBy, groupBy, sum, fmtBDT, fmtBDTk, pct } from './dataset.js';

const T = (D) => (D.meta && D.meta.today) || iso(new Date());
const inCompany = (row, cid) => cid == null || row.company_id === cid;
const STAGES = ['new', 'contacted', 'qualified', 'proposal_sent', 'negotiation', 'won', 'lost'];
const OPEN = ['new', 'contacted', 'qualified', 'proposal_sent', 'negotiation'];
const label = (s) => String(s || '').replace(/_/g, ' ');
const companyName = (D, id) => (D.companies.find((c) => c.id === id) || {}).short_name || ('#' + id);

export function pipeline(D, { company = null } = {}) {
  const t = T(D);
  const leads = (D.leads || []).filter((l) => inCompany(l, company));
  const open = leads.filter((l) => OPEN.includes(l.status));
  const stages = STAGES.map((s) => { const a = leads.filter((l) => l.status === s); return { stage: s, label: label(s), count: a.length, value: sum(a, 'value') }; });
  const won = leads.filter((l) => l.status === 'won'), lost = leads.filter((l) => l.status === 'lost');
  const decided = won.length + lost.length;
  const last30 = leads.filter((l) => daysBetween(l.created_at, t) <= 30);
  const byType = [...groupBy(leads, 'lead_type').entries()].map(([k, a]) => ({ type: label(k), count: a.length, open: a.filter((l) => OPEN.includes(l.status)).length, won: a.filter((l) => l.status === 'won').length, value: sum(a.filter((l) => OPEN.includes(l.status)), 'value') })).sort((a, b) => b.count - a.count);
  const bySource = [...groupBy(leads, 'source').entries()].map(([k, a]) => ({ source: k, count: a.length, won: a.filter((l) => l.status === 'won').length, rate: a.filter((l) => ['won', 'lost'].includes(l.status)).length ? Math.round(a.filter((l) => l.status === 'won').length / a.filter((l) => ['won', 'lost'].includes(l.status)).length * 100) : null })).sort((a, b) => b.count - a.count);
  const weighted = { new: 0.05, contacted: 0.15, qualified: 0.35, proposal_sent: 0.55, negotiation: 0.75 };
  const expected = open.reduce((n, l) => n + (+l.value || 0) * (weighted[l.status] || 0), 0);
  return { total: leads.length, open, openValue: sum(open, 'value'), expectedValue: Math.round(expected), stages, won: won.length, wonValue: sum(won, 'value'), lost: lost.length, conversion: decided ? Math.round(won.length / decided * 100) : null, newLast30: last30.length, byType, bySource };
}
export function stale(D, { company = null, days = 10 } = {}) {
  const t = T(D);
  const open = (D.leads || []).filter((l) => inCompany(l, company) && OPEN.includes(l.status));
  const rows = open.map((l) => { const last = l.last_followup_at || l.created_at; const idle = daysBetween(last, t); const overdueF = l.next_followup_at && l.next_followup_at < t ? daysBetween(l.next_followup_at, t) : 0; return Object.assign({}, l, { idle_days: idle, followup_overdue_days: overdueF, company: companyName(D, l.company_id) }); }).filter((l) => l.idle_days >= days || l.followup_overdue_days > 0).sort((a, b) => (b.value || 0) * (b.idle_days + b.followup_overdue_days) - (a.value || 0) * (a.idle_days + a.followup_overdue_days));
  const byAgent = [...groupBy(rows, 'assigned_to').entries()].map(([id, a]) => ({ id, name: a[0].assigned_name, count: a.length, value: sum(a, 'value') })).sort((a, b) => b.count - a.count);
  return { rows, count: rows.length, value: sum(rows, 'value'), byAgent };
}
export function followups(D, { company = null } = {}) {
  const t = T(D);
  const open = (D.leads || []).filter((l) => inCompany(l, company) && OPEN.includes(l.status) && l.next_followup_at);
  const today = open.filter((l) => l.next_followup_at === t), overdue = open.filter((l) => l.next_followup_at < t), week = open.filter((l) => l.next_followup_at > t && daysBetween(t, l.next_followup_at) <= 7);
  return { today, overdue, week };
}
export function deals(D, { company = null } = {}) {
  const t = T(D);
  const all = (D.deals || []).filter((d) => inCompany(d, company));
  const open = all.filter((d) => d.status === 'open');
  const closingSoon = open.filter((d) => daysBetween(t, d.closing_date) <= 14).sort((a, b) => a.closing_date.localeCompare(b.closing_date));
  const slipped = open.filter((d) => d.closing_date < t);
  const won30 = all.filter((d) => d.status === 'won' && daysBetween(d.closing_date, t) <= 30), lost30 = all.filter((d) => d.status === 'lost' && daysBetween(d.closing_date, t) <= 30);
  return { open, openValue: sum(open, 'amount'), closingSoon, closingSoonValue: sum(closingSoon, 'amount'), slipped, slippedValue: sum(slipped, 'amount'), won30, won30Value: sum(won30, 'amount'), lost30, lost30Value: sum(lost30, 'amount') };
}
export function agents(D, { company = null } = {}) {
  const leads = (D.leads || []).filter((l) => inCompany(l, company));
  const rows = [...groupBy(leads, 'assigned_to').entries()].map(([id, a]) => { const won = a.filter((l) => l.status === 'won'), lost = a.filter((l) => l.status === 'lost'), open = a.filter((l) => OPEN.includes(l.status)); return { id, name: a[0].assigned_name, leads: a.length, open: open.length, openValue: sum(open, 'value'), won: won.length, wonValue: sum(won, 'value'), lost: lost.length, rate: won.length + lost.length ? Math.round(won.length / (won.length + lost.length) * 100) : null }; }).sort((a, b) => b.wonValue - a.wonValue);
  return { rows, top: rows[0] || null };
}
export function decisions(D, { company = null } = {}) {
  const out = []; const push = (d) => out.push(Object.assign({ layer: 'crm', company_id: company }, d));
  const p = pipeline(D, { company }), s = stale(D, { company }), f = followups(D, { company }), d = deals(D, { company }), ag = agents(D, { company });
  if (s.count) push({ id: 'stale-leads', severity: s.value > p.openValue * 0.3 ? 4 : 3, title: `${s.count} open leads worth ${fmtBDTk(s.value)} have gone cold`, why: s.rows.slice(0, 4).map((l) => `${l.name} (${label(l.lead_type)}, ${l.company}) — ${l.idle_days}d silent${l.followup_overdue_days ? `, follow-up ${l.followup_overdue_days}d late` : ''}, ${l.assigned_name}`).concat(s.byAgent[0] ? [`Most cold leads sit with ${s.byAgent[0].name} (${s.byAgent[0].count})`] : []), recommend: `Reassign or close: give ${s.byAgent[0] ? s.byAgent[0].name : 'the owner'} 48 hours to touch each one, then move the rest to lost — a clean pipeline forecasts honestly.`, amount: s.value, actions: [{ label: 'Open pipeline', kind: 'navigate', href: 'crm.html#stale' }], entities: s.rows.slice(0, 4).map((l) => ({ type: 'lead', id: l.id, label: l.name })) });
  if (f.overdue.length || f.today.length) push({ id: 'followups', severity: f.overdue.length >= 5 ? 3 : 2, title: `${f.today.length} follow-ups due today, ${f.overdue.length} already missed`, why: f.today.slice(0, 3).map((l) => `Today: ${l.name} — ${l.assigned_name}`).concat(f.overdue.slice(0, 3).map((l) => `Missed: ${l.name} (${l.next_followup_at}) — ${l.assigned_name}`)), recommend: 'Sales team clears missed follow-ups before lunch; EON can draft the messages.', actions: [{ label: 'Open follow-ups', kind: 'navigate', href: 'crm.html#followups' }] });
  if (d.slipped.length) push({ id: 'deals-slipped', severity: 3, title: `${d.slipped.length} deals (${fmtBDTk(d.slippedValue)}) are past their closing date and still open`, why: d.slipped.slice(0, 4).map((x) => `${x.title} — ${fmtBDTk(x.amount)}, was closing ${x.closing_date}`), recommend: 'Re-date honestly or mark lost; the forecast is only as good as its dates.', amount: d.slippedValue, actions: [{ label: 'Open deals', kind: 'navigate', href: 'crm.html#deals' }] });
  if (d.closingSoon.length) push({ id: 'deals-closing', severity: 2, title: `${d.closingSoon.length} deals worth ${fmtBDTk(d.closingSoonValue)} close in the next 14 days`, why: d.closingSoon.slice(0, 4).map((x) => `${x.title} — ${fmtBDTk(x.amount)} on ${x.closing_date}`), recommend: 'These are the calls to make personally this week.', amount: d.closingSoonValue });
  if (p.conversion != null && p.conversion < 35 && (p.won + p.lost) >= 8) push({ id: 'conversion-low', severity: 3, title: `Lead conversion is ${p.conversion}% (${p.won} won / ${p.lost} lost)`, why: p.bySource.filter((x) => x.rate != null).slice(0, 3).map((x) => `${x.source}: ${x.rate}% (${x.count} leads)`), recommend: `Put budget behind ${(p.bySource.filter((x) => x.rate != null).sort((a, b) => b.rate - a.rate)[0] || {}).source || 'the best source'} and qualify harder at the top of the funnel.` });
  if (ag.top && ag.rows.length > 2) push({ id: 'agent-top', severity: 1, title: `${ag.top.name} leads the board — ${fmtBDTk(ag.top.wonValue)} won${ag.top.rate != null ? ` at ${ag.top.rate}%` : ''}`, why: ag.rows.slice(1, 3).map((r) => `${r.name}: ${fmtBDTk(r.wonValue)} won, ${r.open} open`), recommend: 'Recognise it publicly this week; pair the newest agent with them.' });
  return out.sort((a, b) => b.severity - a.severity || (b.amount || 0) - (a.amount || 0));
}

export const EonErpCrm = { pipeline, stale, followups, deals, agents, decisions, STAGES, OPEN };
if (typeof window !== 'undefined') window.EonErpCrm = Object.assign(window.EonErpCrm || {}, EonErpCrm);
export default EonErpCrm;
