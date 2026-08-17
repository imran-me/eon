/* ============================================================
   EON · ERP answerer — turns a question (typed or spoken) into a
   grounded answer over the ERP dataset: the number, the rule that
   produced it, and what to do. Registers itself as the highest-
   priority domain in Ask EON, so the same intents work from the
   companion chip, the Command Center and the voice loop.

   Every intent returns { speak, detail[], items?, actions?, view? }.
   `view` tells the app which panel to open alongside the answer.
   ============================================================ */
import { iso, monthKey, MONTHS, fmtBDT, fmtBDTk, sum, daysBetween, addDays } from './dataset.js';
import * as K from './knowledge.js';
import * as F from './finance.js';
import * as P from './people.js';
import * as C from './crm.js';
import * as O from './ops.js';
import * as X from './decisions.js';

const store = { get: () => (typeof window !== 'undefined' && window.EonErp && window.EonErp.dataset ? window.EonErp.dataset() : null) };
const money = fmtBDT, k = fmtBDTk;
const pick = (a) => a[Math.floor(Math.random() * a.length)];
const cap = (s) => s.charAt(0).toUpperCase() + s.slice(1);
const list = (arr, fn, n = 6) => arr.slice(0, n).map((x, i) => `${i + 1}. ${fn(x)}`);
const monthName = (mk) => `${MONTHS[+mk.slice(5) - 1]} ${mk.slice(0, 4)}`;

/* ---------- scope: which company is the question about? ---------- */
export function companyIn(D, q) {
  const s = q.toLowerCase();
  const hits = (D.companies || []).filter((c) => {
    const n = c.name.toLowerCase(), sn = (c.short_name || '').toLowerCase();
    return s.includes(n) || (sn && sn.length > 2 && new RegExp('\\b' + sn.replace(/[^a-z0-9 ]/g, '') + '\\b').test(s)) || (/wood ?art/.test(s) && /wood/.test(n)) || (/travels?\b/.test(s) && /travel/.test(n)) || (/\bit solutions?|epal it\b/.test(s) && /it solutions/.test(n)) || (/online shop|\bshop\b|e-?commerce/.test(s) && /shop/.test(n)) || (/manufactur/.test(s) && /manufactur/.test(n)) && !/wood/.test(s) || (/propert/.test(s) && /propert/.test(n)) || (/construction|\beci\b/.test(s) && /construction/.test(n));
  });
  return hits.length === 1 ? hits[0] : (hits.find((c) => s.includes(c.name.toLowerCase())) || null);
}
const scopeLabel = (c) => (c ? ` at ${c.name}` : ' across the group');

/* ---------- month in question ---------- */
function monthIn(D, q) {
  const s = q.toLowerCase(); const t = new Date((D.meta && D.meta.today) || Date.now());
  if (/last month|previous month/.test(s)) return monthKey(new Date(t.getFullYear(), t.getMonth() - 1, 1));
  const m = MONTHS.findIndex((mn) => s.includes(mn.toLowerCase()) || s.includes(mn.toLowerCase().slice(0, 3) + ' '));
  if (m >= 0) { const y = (s.match(/20\d\d/) || [])[0] || t.getFullYear(); return `${y}-${String(m + 1).padStart(2, '0')}`; }
  return monthKey(t);
}
const monthRange = (mk) => ({ from: mk + '-01', to: mk + '-31' });

/* ---------- drafts (letters EON writes for the boss) ---------- */
export function draft(kind, ctx = {}) {
  const today = iso(new Date());
  if (kind === 'payment-reminder') return {
    title: `Payment reminder — ${ctx.party}`,
    body: `Subject: Gentle reminder — outstanding balance of ${money(ctx.amount)}\n\nDear ${ctx.party},\n\nGreetings from ${ctx.company || 'Epal Group'}. Our records show an outstanding balance of ${money(ctx.amount)}${ctx.days ? `, now ${ctx.days} days past its due date` : ''}${ctx.ref ? ` against ${ctx.ref}` : ''}.\n\nWe value our relationship and would appreciate settlement within the next 7 days. If payment has already been made, please share the reference so we can update our ledger; if you need a revised schedule, reply to this message and we will arrange it.\n\nWith thanks,\n${ctx.signer || 'Accounts Department'}\n${ctx.company || 'Epal Group'} · ${today}`,
  };
  if (kind === 'warning-letter') return {
    title: `Attendance warning — ${ctx.name}`,
    body: `Subject: Written notice regarding punctuality\n\nDear ${ctx.name},\n\nOur attendance records for the last ${ctx.days || 30} days show late arrival on ${ctx.lateDays} of ${ctx.presentDays} working days (${ctx.lateMinutes} minutes in total)${ctx.absent ? ` and ${ctx.absent} unapproved absence${ctx.absent > 1 ? 's' : ''}` : ''}. Per company policy, late minutes are deducted once they reach 120 in a month.\n\nPlease treat this as a formal notice. We would like to see regular attendance from ${iso(addDays(today, 1))}. If there is a difficulty behind this pattern, speak to your line manager or HR — we would rather solve it than penalise it.\n\nRegards,\nHR & Admin\n${ctx.company || 'Epal Group'} · ${today}`,
  };
  if (kind === 'notice') return {
    title: `Notice — ${ctx.subject}`,
    body: `NOTICE\n\nDate: ${today}\nTo: All employees${ctx.company ? `, ${ctx.company}` : ''}\n\nSubject: ${ctx.subject}\n\n${ctx.text || 'This is to inform all concerned that the matter stated in the subject takes effect as described. Department heads are requested to ensure compliance within their teams.'}\n\nBy order of the Management\nMd Imran Hossain\nManaging Director, Epal Group`,
  };
  if (kind === 'meeting-agenda') return {
    title: `Agenda — ${ctx.subject || 'Management review'}`,
    body: `MEETING AGENDA — ${ctx.subject || 'Management review'}\nDate: ${today}\n\n${(ctx.points || []).map((p, i) => `${i + 1}. ${p}`).join('\n')}\n\nDecisions required from the chair are marked ★.`,
  };
  return null;
}

/* ---------- intents ---------- */
const INTENTS = [
  // ---- brief / decisions / approvals
  { re: /\b(brief|briefing|morning report|daily report|summary of the day|what('?s| is) (happening|going on|the situation)|how (are|is) (things|business|the company|we doing)|status report|update me|catch me up)\b/, a(q, D, c) {
    const b = X.brief(D, { company: c && c.id });
    return { speak: b.speak, detail: b.decisions.slice(0, 6).map((d) => `[${d.severityLabel}] ${d.title}`), view: 'brief', data: b };
  } },
  { re: /what (should|do) i (do|focus|look at)|top (priorit|issue|risk|decision)|decisions?|what needs (my )?attention|urgent|critical|what('?s| is) (wrong|burning|on fire)/, a(q, D, c) {
    const all = X.all(D, { company: c && c.id }); const top = all.slice(0, 5);
    if (!top.length) return { speak: 'Nothing needs you right now — every layer is quiet.', detail: [] };
    return { speak: `${top.length} decision${top.length > 1 ? 's' : ''} in priority order${scopeLabel(c)}. First: ${top[0].title}. ${top[0].recommend}`, detail: top.map((d, i) => `${i + 1}. [${d.layerLabel} · ${d.severityLabel}] ${d.title} → ${d.recommend}`), view: 'decisions', data: top };
  } },
  { re: /approv(al|als|e)|waiting (on|for) me|pending (my )?(sign|approval)|sign[- ]?offs?|what('?s| is) (waiting|pending)/, a(q, D, c) {
    const ap = X.approvals(D, { company: c && c.id });
    if (!ap.count) return { speak: 'Your approval queue is empty.', detail: [] };
    return { speak: `${ap.count} approvals are waiting${scopeLabel(c)} — ${k(ap.amount)} in total: ${ap.byKind.map((x) => `${x.count} ${x.kind}${x.count > 1 ? 's' : ''}`).join(', ')}. The biggest is ${ap.items[0].title} for ${ap.items[0].amount ? money(ap.items[0].amount) : ap.items[0].who}.`, detail: list(ap.items, (a) => `${cap(a.kind)}: ${a.title} — ${a.who}${a.amount ? ' · ' + money(a.amount) : ''}${a.flag ? ' ⚠ ' + a.flag : ''}`, 8), view: 'approvals', data: ap };
  } },
  // ---- finance: cash
  { re: /cash (position|balance|in hand|available|do we have)|bank balances?|how much (cash|money) (do we have|is there|in the bank)|liquidity|our cash/, a(q, D, c) {
    const cp = F.cashPosition(D, { company: c && c.id });
    return { speak: `Cash and bank${scopeLabel(c)}: ${k(cp.total)} across ${cp.banks.length} accounts. Largest: ${cp.banks[0].name} (${cp.banks[0].company}) ${k(cp.banks[0].balance)}.${cp.low.length ? ` ${cp.low.length} account${cp.low.length > 1 ? 's are' : ' is'} low.` : ''}`, detail: (c ? cp.banks : cp.byCompany).slice(0, 8).map((b) => c ? `${b.name}: ${money(b.balance)}` : `${b.company}: ${money(b.total)}`), view: 'cash', data: cp };
  } },
  // ---- receivables / payables
  { re: /receivables?|who owes (us|money)|customer dues?|collect|debtors?|outstanding (from|invoices?)|\bar\b/, a(q, D, c) {
    const ar = F.receivables(D, { company: c && c.id }); const top = ar.byParty.slice(0, 5);
    if (!ar.open.length) return { speak: `No open receivables${scopeLabel(c)}.`, detail: [] };
    return { speak: `Receivables${scopeLabel(c)}: ${k(ar.total)} open, of which ${k(ar.overdueTotal)} is overdue across ${ar.overdue.length} dues. ${top[0] ? `${top[0].party_name} owes the most — ${k(top[0].due)}${top[0].overdue ? `, ${k(top[0].overdue)} overdue, oldest ${top[0].oldest} days` : ''}.` : ''} Aging: ${ar.buckets.map((b) => `${b.bucket} ${k(b.amount)}`).join(' · ')}.`, detail: list(top, (p) => `${p.party_name}: ${money(p.due)} (${p.count} dues${p.overdue ? `, ${money(p.overdue)} overdue` : ''})`), actions: top[0] ? [{ label: `Draft reminder to ${top[0].party_name}`, kind: 'draft', payload: { kind: 'payment-reminder', party: top[0].party_name, amount: top[0].overdue || top[0].due, days: top[0].oldest } }] : [], view: 'receivables', data: ar };
  } },
  { re: /payables?|what do we owe|supplier dues?|creditors?|bills? (to pay|due)|\bap\b|payments? due/, a(q, D, c) {
    const ap = F.payables(D, { company: c && c.id }); const top = ap.byParty.slice(0, 5); const emp = ap.overdue.filter((p) => p.party_type === 'employee');
    if (!ap.open.length) return { speak: `Nothing to pay${scopeLabel(c)}.`, detail: [] };
    return { speak: `Payables${scopeLabel(c)}: ${k(ap.total)} open, ${k(ap.overdueTotal)} overdue (${ap.overdue.length} items)${emp.length ? `, including ${emp.length} late salaries worth ${k(sum(emp, 'due'))}` : ''}. Due in the next 7 days: ${k(ap.dueSoonTotal)}.`, detail: list(top, (p) => `${p.party_name}: ${money(p.due)}${p.overdue ? ` — ${money(p.overdue)} overdue (${p.oldest}d)` : ''}`), view: 'payables', data: ap };
  } },
  { re: /overdue|past due|late payments?/, a(q, D, c) {
    if (/task|project|todo|to-do|follow/.test(q)) return null;
    const ar = F.receivables(D, { company: c && c.id }), ap = F.payables(D, { company: c && c.id });
    return { speak: `Overdue${scopeLabel(c)}: ${k(ar.overdueTotal)} to collect from ${ar.overdue.length} dues and ${k(ap.overdueTotal)} to pay on ${ap.overdue.length} items. Oldest receivable: ${ar.overdue[0] ? `${ar.overdue[0].party_name}, ${ar.overdue[0].days_overdue} days` : 'none'}.`, detail: ar.overdue.slice(0, 4).map((p) => `Collect: ${p.party_name} ${money(p.due)} · ${p.days_overdue}d`).concat(ap.overdue.slice(0, 4).map((p) => `Pay: ${p.party_name} ${money(p.due)} · ${p.days_overdue}d`)), view: 'receivables' };
  } },
  { re: /aging|ageing/, a(q, D, c) {
    const ar = F.receivables(D, { company: c && c.id }), ap = F.payables(D, { company: c && c.id });
    return { speak: `Receivable aging${scopeLabel(c)}: ${ar.buckets.map((b) => `${b.bucket} ${k(b.amount)}`).join(', ')}. Payable aging: ${ap.buckets.map((b) => `${b.bucket} ${k(b.amount)}`).join(', ')}.`, detail: ['The ERP has no aging report yet — this is computed from payment schedules.'], view: 'receivables' };
  } },
  // ---- statements
  { re: /trial balance/, a(q, D, c) {
    const tb = F.trialBalance(D, { company: c && c.id }); const big = tb.rows.filter((r) => r.debit + r.credit > 0).sort((a, b) => (b.debit + b.credit) - (a.debit + a.credit)).slice(0, 8);
    return { speak: `Trial balance${scopeLabel(c)} ${tb.balanced ? 'balances' : 'does NOT balance'}: debits ${k(tb.totalDebit)}, credits ${k(tb.totalCredit)} across ${tb.rows.length} accounts.`, detail: big.map((r) => `${r.code} ${r.name}: Dr ${money(r.debit)} · Cr ${money(r.credit)}`), view: 'trial-balance', data: tb };
  } },
  { re: /profit|p&l|income statement|(are we|is the company) (making|losing) money|margin|bottom line|revenue|sales (this|last) month|turnover|earn/, a(q, D, c) {
    const mk = monthIn(D, q); const pl = F.profitAndLoss(D, Object.assign(monthRange(mk), { company: c && c.id })); const tr = F.revenueTrend(D, { company: c && c.id });
    const isCur = mk === monthKey((D.meta && D.meta.today) || new Date());
    return { speak: `${monthName(mk)}${scopeLabel(c)}: revenue ${k(pl.totalIncome)}, direct cost ${k(pl.totalDirect)}, operating expenses ${k(pl.totalOpex)} — net ${pl.netProfit >= 0 ? 'profit' : 'loss'} ${k(Math.abs(pl.netProfit))} (${pl.margin}% margin).${isCur ? ` Run-rate ${k(tr.runRate)}, ${tr.vsPrev >= 0 ? 'up' : 'down'} ${Math.abs(tr.vsPrev)}% on last month.` : ''}`, detail: pl.income.slice(0, 4).map((r) => `Income ${r.code} ${r.name}: ${money(r.amount)}`).concat(pl.opex.slice(0, 4).map((r) => `Opex ${r.code} ${r.name}: ${money(r.amount)}`)), view: 'pnl', data: pl };
  } },
  { re: /balance sheet|net worth|assets? and liabilit|what (do we|does the company) own|equity/, a(q, D, c) {
    const bs = F.balanceSheet(D, { company: c && c.id });
    return { speak: `Balance sheet${scopeLabel(c)}: assets ${k(bs.totalAssets)}, liabilities ${k(bs.totalLiabilities)}, equity ${k(bs.totalEquity)} — ${bs.balanced ? 'it balances' : 'it does not balance'}.`, detail: bs.assets.slice(0, 5).map((r) => `Asset ${r.code} ${r.name}: ${money(r.amount)}`).concat(bs.liabilities.slice(0, 4).map((r) => `Liability ${r.code} ${r.name}: ${money(r.amount)}`)), view: 'balance-sheet', data: bs };
  } },
  { re: /ledger (of|for) (\d{4})|account (\d{4}) (ledger|history|statement)|(show|open) (\d{4})/, a(q, D, c) {
    const code = (q.match(/\b(\d{4})\b/) || [])[1]; if (!code) return null;
    const lg = F.accountLedger(D, code, { company: c && c.id }); if (!lg.rows.length) return { speak: `No postings on ${code}${scopeLabel(c)}.`, detail: [] };
    return { speak: `${code} ${lg.name}: ${lg.rows.length} postings, closing balance ${money(lg.closing)}.`, detail: lg.rows.slice(-6).map((r) => `${r.date} ${r.reference} ${r.description.slice(0, 40)}: Dr ${money(r.debit)} Cr ${money(r.credit)} → ${money(r.balance)}`), view: 'ledger', data: lg };
  } },
  // ---- expenses & budgets
  { re: /anomal|unusual|spike|suspicious|leak|abnormal|out of (pattern|line)/, a(q, D, c) {
    const an = F.expenseAnomalies(D, { company: c && c.id });
    if (!an.length) return { speak: `No spending anomalies${scopeLabel(c)} — every category is inside its normal band.`, detail: [] };
    return { speak: `${an.length} spending anomal${an.length > 1 ? 'ies' : 'y'}${scopeLabel(c)}. Biggest: ${an[0].category} at ${an[0].company} — ${k(an[0].amount)} in ${monthName(an[0].month)}, ${an[0].ratio}× its usual ${k(an[0].mean)}.`, detail: an.slice(0, 6).map((a) => `${a.company} · ${a.category} · ${monthName(a.month)}: ${money(a.amount)} vs normal ${money(a.mean)} (${a.ratio}×)`), view: 'expenses', data: an };
  } },
  { re: /budget|over ?spend|expense(s)? (this|last) month|where (is|does) (the )?money go|spending|opex|cost breakdown|biggest expenses?/, a(q, D, c) {
    const mk = monthIn(D, q); const bud = F.expensesVsBudget(D, { month: mk, company: c && c.id }); const an = F.expenseAnomalies(D, { company: c && c.id });
    return { speak: `${monthName(mk)} expenses${scopeLabel(c)}: ${k(bud.totalSpent)}${bud.totalBudget ? ` against a budget of ${k(bud.totalBudget)} (${Math.round(bud.totalSpent / bud.totalBudget * 100)}%)` : ''}. ${bud.over.length ? `Over budget: ${bud.over.map((r) => `${r.category} ${r.pct}%`).join(', ')}.` : 'Nothing over budget.'}${an.length ? ` Unusual: ${an[0].category} at ${an[0].company} is ${an[0].ratio}× normal.` : ''}`, detail: bud.rows.slice(0, 8).map((r) => `${r.category}: ${money(r.spent)}${r.budget ? ` of ${money(r.budget)} (${r.pct}%)` : ''}${r.pending ? ` · ${money(r.pending)} pending` : ''}`), view: 'budgets', data: bud };
  } },
  { re: /runway|burn|how long (will|does) (the )?cash last|survive|months? of (cash|cover)/, a(q, D, c) {
    const rw = F.runway(D, { company: c && c.id });
    return { speak: rw.burning ? `At the current burn of ${k(-rw.avgMonthlyNet)} a month, cash lasts ${rw.monthsToZero} months.` : `We are not burning cash${scopeLabel(c)} — average monthly net is +${k(rw.avgMonthlyNet)}; cash of ${k(rw.cash)} covers ${rw.monthsOfCover} months of total outflow.`, detail: [`Cash ${money(rw.cash)}`, `Average monthly outflow ${money(rw.avgMonthlyOutflow)}`], view: 'cash' };
  } },
  { re: /top (customers?|clients?|suppliers?|vendors?)|best customers?|biggest (customers?|suppliers?)|who (buys|are our) (the )?most/, a(q, D, c) {
    const tp = F.topParties(D, { company: c && c.id }); const sup = /supplier|vendor/.test(q);
    const rows = sup ? tp.suppliers : tp.customers;
    return { speak: `Top ${sup ? 'suppliers' : 'customers'} in the last ${tp.window} days${scopeLabel(c)}: ${rows.slice(0, 3).map((r) => `${r.name} ${k(r.total)}`).join(', ')}.`, detail: list(rows, (r) => `${r.name}: ${money(r.total)} (${r.invoices || r.bills} ${sup ? 'bills' : 'invoices'}${r.due ? `, ${money(r.due)} due` : ''})`), view: sup ? 'payables' : 'receivables' };
  } },
  // ---- people: today
  { re: /who (is|are|'s) (absent|missing|not (in|here|present))|absent (today|list)|absentees?|attendance (today|now|status)|who('?s| is) (in|here|present|at work)( today)?|how many (are|people|staff) (in|present|absent)|present today/, a(q, D, c) {
    const td = P.today(D, { company: c && c.id });
    if (td.weekend || td.holiday) return { speak: `Today is ${td.holiday || 'the weekend'} — no attendance expected.`, detail: [] };
    return { speak: `Today${scopeLabel(c)}: ${td.present.length} of ${td.total} present (${td.presentPct}%), ${td.absent.length} absent, ${td.late.length} late, ${td.onLeave.length} on approved leave${td.notYet.length ? `, ${td.notYet.length} not punched yet` : ''}. ${td.absent.length ? `Absent: ${td.absent.slice(0, 5).map((a) => a.name).join(', ')}${td.absent.length > 5 ? ` and ${td.absent.length - 5} more` : ''}.` : ''}`, detail: td.late.slice(0, 5).map((a) => `Late: ${a.name} (${a.department}) +${a.late_minutes} min, in at ${a.check_in}`).concat(td.onLeave.slice(0, 3).map((a) => `On leave: ${a.name}`)), view: 'today', data: td, items: td.absent.map((a) => ({ entity: 'employees', id: a.id, label: a.name })) };
  } },
  { re: /who (is|was|came|arrived) late|late (comers?|arrivals?|today|list)|punctual|chronic|habitual|always late|late (pattern|habit)/, a(q, D, c) {
    const td = P.today(D, { company: c && c.id }); const pt = P.patterns(D, { company: c && c.id });
    return { speak: `${td.late.length} came late today${td.late[0] ? ` — latest ${td.late[0].name} by ${td.late[0].late_minutes} minutes` : ''}. Over 30 days, ${pt.chronicLate.length} people are late on 30%+ of their days${pt.chronicLate[0] ? `; worst is ${pt.chronicLate[0].name} (${pt.chronicLate[0].lateDays}/${pt.chronicLate[0].present} days, ${pt.chronicLate[0].lateMinutes} min)` : ''}.`, detail: pt.chronicLate.slice(0, 6).map((r) => `${r.name} (${r.department}, ${r.company}): late ${r.lateDays}/${r.present} days · ${r.lateMinutes} min${r.lateMinutes >= 120 ? ' · deduction applies' : ''}`), actions: pt.chronicLate[0] ? [{ label: `Draft warning letter to ${pt.chronicLate[0].name}`, kind: 'draft', payload: { kind: 'warning-letter', name: pt.chronicLate[0].name, lateDays: pt.chronicLate[0].lateDays, presentDays: pt.chronicLate[0].present, lateMinutes: pt.chronicLate[0].lateMinutes, absent: pt.chronicLate[0].absent } }] : [], view: 'patterns' };
  } },
  { re: /who is online|online (now|right now|status)|active (now|users)|logged in/, a(q, D, c) {
    const td = P.today(D, { company: c && c.id });
    return { speak: `${td.online.length} people are online right now (seen in the last 5 minutes)${td.online.length ? `: ${td.online.slice(0, 6).map((o) => o.name).join(', ')}${td.online.length > 6 ? '…' : ''}` : ''}.`, detail: [], view: 'today' };
  } },
  { re: /headcount|how many (employees|staff|people)( do we have| work)|team size|workforce|departments?/, a(q, D, c) {
    const hc = P.headcount(D, { company: c && c.id });
    return { speak: `${hc.total} active employees${scopeLabel(c)}, monthly payroll ${k(hc.monthlyPayroll)}, average salary ${money(hc.avgSalary)}.${hc.newJoiners.length ? ` ${hc.newJoiners.length} joined in the last 60 days.` : ''}`, detail: (c ? hc.byDept : hc.byCompany).slice(0, 8).map((r) => c ? `${r.department}: ${r.count} · ${money(r.payroll)}` : `${r.company}: ${r.count} · ${money(r.payroll)}`), view: 'people' };
  } },
  { re: /leave (request|application|pending|balance|today|approv)|on leave|who is on leave|leaves?\b/, a(q, D, c) {
    const lv = P.leaves(D, { company: c && c.id });
    const emp = P.findEmployee(D, q); if (emp && /balance/.test(q)) { const b = P.leaveBalance(D, emp.id); return { speak: `${emp.name}'s leave balance: ${b.map((x) => `${x.type} ${x.remaining} of ${x.entitlement}`).join(', ')}.`, detail: [], view: 'people' }; }
    return { speak: `${lv.pending.length} leave request${lv.pending.length === 1 ? '' : 's'} pending${scopeLabel(c)}${lv.pending[0] ? ` — ${lv.pending[0].name}, ${lv.pending[0].leave_type} ${lv.pending[0].days} day${lv.pending[0].days > 1 ? 's' : ''} from ${lv.pending[0].start_date}` : ''}. ${lv.onLeaveToday.length} on leave today, ${lv.upcoming.length} approved for the next two weeks.`, detail: lv.pending.map((l) => `${l.name} (${l.department}): ${l.leave_type} ${l.days}d ${l.start_date}→${l.end_date} · ${l.reason} · balance ${(l.balance.find((b) => b.type === l.leave_type) || {}).remaining}`), view: 'approvals' };
  } },
  { re: /payroll|salar(y|ies)|payslips?|wage|net pay|how much (do we|did we) pay (staff|employees|salar)/, a(q, D, c) {
    const emp = P.findEmployee(D, q);
    if (emp && !/total|payroll|all/.test(q)) { const ev = P.evaluate(D, emp.id); const p = ev.lastPay; return { speak: p ? `${emp.name}: gross ${money(p.gross_salary)}, deductions ${money(p.total_deductions)} (late ${money(p.late_deduction)}, absence ${money(p.absent_deduction)}, loan ${money(p.loan_deduction)}), overtime ${money(p.overtime_salary)} → net ${money(p.net_salary)} for ${p.month} — ${p.status}.` : `${emp.name} has no payslip yet.`, detail: [], view: 'payroll' }; }
    const mk = /last month|previous/.test(q) ? null : (MONTHS.some((m) => q.toLowerCase().includes(m.toLowerCase())) ? monthIn(D, q) : null);
    const pr = P.payroll(D, { company: c && c.id, month: mk });
    return { speak: `Payroll for ${monthName(pr.month)}${scopeLabel(c)}: ${pr.heads} payslips, gross ${k(pr.gross)}, deductions ${k(pr.deductions)}, net ${k(pr.net)}${pr.prevNet ? ` (${pr.net >= pr.prevNet ? '+' : ''}${Math.round((pr.net - pr.prevNet) / pr.prevNet * 100)}% vs the month before)` : ''}. ${pr.pending.length ? `${pr.pending.length} are still unpaid — ${k(sum(pr.pending, 'net_salary'))}.` : 'All paid.'}`, detail: [`Late deductions ${money(pr.late)} · absence ${money(pr.absent)} · loan recoveries ${money(pr.loans)} · advances ${money(pr.advances)} · overtime paid ${money(pr.overtime)}`].concat(pr.byCompany.slice(0, 6).map((r) => `${r.company}: ${r.heads} heads, net ${money(r.net)}${r.pending ? ` · ${r.pending} unpaid` : ''}`)), view: 'payroll', data: pr };
  } },
  { re: /loans?|advance(s| salary)|employee requests?|who (owes|borrowed)/, a(q, D, c) {
    const ln = P.loans(D, { company: c && c.id });
    return { speak: `${ln.running.length} staff loans running${scopeLabel(c)}, ${k(ln.outstanding)} outstanding, recovered at ${k(ln.monthlyRecovery)} a month through payroll. ${ln.advancesPending.length} salary advance${ln.advancesPending.length === 1 ? '' : 's'} and ${ln.requestsPending.length} employee request${ln.requestsPending.length === 1 ? '' : 's'} await a decision.`, detail: ln.running.slice(0, 5).map((l) => `${l.name}: ${money(l.remaining_amount)} of ${money(l.amount)} left · EMI ${money(l.monthly_deduction)}`).concat(ln.advancesPending.map((a) => `Advance: ${a.name} ${money(a.amount)} (${a.month})`)), view: 'people' };
  } },
  { re: /evaluat|performance of|how (is|good is|does) .* (doing|performing)|rate |review .*(employee|staff)|appraise|score of|assess/, a(q, D, c) {
    const emp = P.findEmployee(D, q); if (!emp) return null;
    const ev = P.evaluate(D, emp.id);
    return { speak: ev.narrative, detail: [`Attendance ${ev.attendancePct}% (${ev.presentDays}/${ev.workdays} days) · punctuality ${ev.punctuality}% · late ${ev.lateDays} days`, `Tasks: ${ev.tasksDone} done, ${ev.tasksOnTime} on time, ${ev.tasksOverdue} overdue, ${ev.load} open`, ev.salesRate != null ? `Sales: ${ev.leadsWon} won / ${ev.leadsLost} lost (${ev.salesRate}%)` : null, `Leave left: ${ev.leaveBalance.map((b) => `${b.type} ${b.remaining}`).join(', ')}`, ev.loan ? `Loan outstanding ${money(ev.loan.remaining_amount)}` : null, ev.lastPay ? `Last net pay ${money(ev.lastPay.net_salary)} (${ev.lastPay.month})` : null].filter(Boolean), view: 'employee', data: ev, items: [{ entity: 'employees', id: emp.id, label: emp.name }] };
  } },
  { re: /(best|top|worst|weakest|lowest|strongest) (performer|employee|staff|people)|rank(ing)? (employees|staff|team)|leaderboard|who (performs|is performing) (best|worst)/, a(q, D, c) {
    const r = P.ranking(D, { company: c && c.id, limit: 5 }); const worst = /worst|weakest|lowest/.test(q);
    const rows = worst ? r.bottom : r.top;
    return { speak: `${worst ? 'Lowest' : 'Top'} performers${scopeLabel(c)} over the last 30 days: ${rows.slice(0, 3).map((e) => `${e.employee.name} ${e.score}/100`).join(', ')}.`, detail: rows.map((e, i) => `${i + 1}. ${e.employee.name} (${e.employee.designation}) — ${e.score} · attendance ${e.attendancePct}% · ${e.strengths.filter((x) => !/^attendance/.test(x)).slice(0, 2).join(', ') || e.concerns[0] || ''}`), view: 'ranking', data: r };
  } },
  { re: /who is (\w+)|tell me about|profile of|details of|find (employee|staff)|search (employee|staff)/, a(q, D, c) {
    const emp = P.findEmployee(D, q); if (!emp) return null;
    const ev = P.evaluate(D, emp.id); const td = P.today(D).present.find((p) => p.id === emp.id);
    return { speak: `${emp.name} — ${emp.designation}, ${emp.department}, ${(D.companies.find((x) => x.id === emp.company_id) || {}).name}; joined ${emp.joining_date}, salary ${money(emp.salary)}. ${td ? `In today${td.late_minutes ? `, ${td.late_minutes} min late` : ''}.` : 'Not in today.'} Score ${ev.score}/100 (${ev.grade}).`, detail: [emp.email, emp.phone, `Shift ${emp.shift_start}–${emp.shift_end}${emp.overtime_eligible ? ' · overtime eligible' : ''}`, `Open tasks ${ev.load}, overdue ${ev.tasksOverdue}`], view: 'employee', data: ev, items: [{ entity: 'employees', id: emp.id, label: emp.name }] };
  } },
  // ---- CRM
  { re: /pipeline|leads?\b|prospects?|sales funnel|conversion|win rate|crm/, a(q, D, c) {
    const p = C.pipeline(D, { company: c && c.id }); const st = C.stale(D, { company: c && c.id });
    if (/stale|cold|neglect|untouched|no follow/.test(q)) return { speak: `${st.count} open leads worth ${k(st.value)} have gone cold${scopeLabel(c)}${st.byAgent[0] ? ` — most with ${st.byAgent[0].name} (${st.byAgent[0].count})` : ''}.`, detail: st.rows.slice(0, 6).map((l) => `${l.name} (${l.lead_type.replace('_', ' ')}, ${l.company}) — ${l.idle_days}d idle · ${money(l.value)} · ${l.assigned_name}`), view: 'stale', data: st };
    return { speak: `Pipeline${scopeLabel(c)}: ${p.open.length} open leads worth ${k(p.openValue)} (${k(p.expectedValue)} probability-weighted); ${p.won} won, ${p.lost} lost — ${p.conversion == null ? 'no conversion yet' : `${p.conversion}% conversion`}. ${p.newLast30} new in 30 days. ${st.count ? `${st.count} have gone cold.` : ''}`, detail: p.stages.map((s) => `${s.label}: ${s.count} · ${money(s.value)}`).concat(p.byType.slice(0, 3).map((t) => `By type — ${t.type}: ${t.open} open, ${t.won} won`)), view: 'pipeline', data: p };
  } },
  { re: /follow[- ]?ups?|call (list|today)|who (should|do) (i|we) call/, a(q, D, c) {
    const f = C.followups(D, { company: c && c.id });
    return { speak: `${f.today.length} follow-ups due today, ${f.overdue.length} missed, ${f.week.length} this week${scopeLabel(c)}.`, detail: f.today.slice(0, 5).map((l) => `Today: ${l.name} — ${l.assigned_name} · ${money(l.value)}`).concat(f.overdue.slice(0, 5).map((l) => `Missed ${l.next_followup_at}: ${l.name} — ${l.assigned_name}`)), view: 'followups' };
  } },
  { re: /deals?|closing (soon|this)|expected (to close|revenue)|forecast/, a(q, D, c) {
    const d = C.deals(D, { company: c && c.id });
    return { speak: `${d.open.length} open deals worth ${k(d.openValue)}${scopeLabel(c)}; ${d.closingSoon.length} (${k(d.closingSoonValue)}) close within 14 days, ${d.slipped.length} (${k(d.slippedValue)}) have slipped past their date. Last 30 days: won ${k(d.won30Value)}, lost ${k(d.lost30Value)}.`, detail: d.closingSoon.slice(0, 5).map((x) => `${x.title}: ${money(x.amount)} on ${x.closing_date}`), view: 'deals' };
  } },
  { re: /(sales|best|top) (agent|rep|person|team member)|who (sells|is selling|closes) (the )?most|agent performance/, a(q, D, c) {
    const a = C.agents(D, { company: c && c.id });
    return { speak: a.top ? `${a.top.name} leads sales${scopeLabel(c)} — ${k(a.top.wonValue)} won${a.top.rate != null ? ` at ${a.top.rate}%` : ''}, ${a.top.open} open.` : 'No sales activity to rank.', detail: a.rows.slice(0, 6).map((r, i) => `${i + 1}. ${r.name}: ${money(r.wonValue)} won (${r.won}W/${r.lost}L${r.rate != null ? `, ${r.rate}%` : ''}), ${r.open} open`), view: 'agents' };
  } },
  // ---- operations
  { re: /overdue tasks?|tasks? (overdue|late|behind|due)|what('?s| is) (late|overdue) (in|on) (work|tasks?)|task (status|board|list)|\btasks?\b/, a(q, D, c) {
    const tk = O.tasks(D, { company: c && c.id });
    if (/due today/.test(q)) return { speak: `${tk.dueToday.length} tasks due today, ${tk.dueWeek.length} more this week.`, detail: tk.dueToday.slice(0, 6).map((t) => `${t.title} — ${t.project} · ${t.assignees.join(', ')}`), view: 'tasks' };
    return { speak: `${tk.overdue.length} tasks are overdue${scopeLabel(c)} (${tk.overdue.filter((t) => t.priority === 'high').length} high priority) out of ${tk.open.length} open; ${tk.velocity} closed this week vs ${tk.velocityPrev} last week.${tk.load[0] && tk.load[0].overdue ? ` ${tk.load[0].name} holds the most overdue (${tk.load[0].overdue}).` : ''}`, detail: tk.overdue.slice(0, 6).map((t) => `${t.title} — ${t.project} · ${t.assignees.join(', ') || 'unassigned'} · ${t.days_overdue}d late · ${t.priority}`), view: 'tasks', data: tk };
  } },
  { re: /projects?|delivery|at risk|behind schedule|milestone/, a(q, D, c) {
    const pj = O.projects(D, { company: c && c.id });
    return { speak: `${pj.active.length} active projects${scopeLabel(c)}, ${pj.atRisk.length} at risk${pj.atRisk[0] ? ` — worst is ${pj.atRisk[0].project_name} (${pj.atRisk[0].riskLabel}: ${pj.atRisk[0].progress}% done at ${pj.atRisk[0].elapsedPct}% of time${pj.atRisk[0].budgetPct != null ? `, budget ${pj.atRisk[0].budgetPct}%` : ''})` : ''}. Budget in play ${k(pj.budget)}, spent ${k(pj.spent)}.`, detail: pj.atRisk.slice(0, 5).map((p) => `${p.project_name} (${p.company}) — ${p.riskLabel}: ${p.progress}% at ${p.elapsedPct}% time · ${p.tasksOverdue} overdue tasks · ${p.manager}`), view: 'projects', data: pj };
  } },
  { re: /workload|who (is|'s) (overloaded|busy|free|idle)|capacity|bandwidth|load/, a(q, D, c) {
    const tk = O.tasks(D, { company: c && c.id });
    return { speak: `${tk.overloaded.length} people are overloaded (6+ open or 3+ overdue)${tk.overloaded[0] ? ` — ${tk.overloaded[0].name} with ${tk.overloaded[0].open} open` : ''}; ${tk.idle.length} skilled staff have nothing assigned.`, detail: tk.load.slice(0, 6).map((r) => `${r.name} (${r.department}): ${r.open} open · ${r.overdue} overdue · ${r.high} high`).concat(tk.idle.slice(0, 3).map((e) => `Free: ${e.name} — ${e.designation}`)), view: 'load' };
  } },
  { re: /to-?dos?|office (task|checklist)|compliance|licen[cs]e|vat return|mushak/, a(q, D, c) {
    const td = O.todos(D, { company: c && c.id });
    return { speak: `${td.open.length} office to-dos open, ${td.overdue.length} overdue${td.overdue[0] ? ` — oldest: ${td.overdue[0].title} (${td.overdue[0].due_date})` : ''}.`, detail: td.overdue.slice(0, 6).map((k2) => `${k2.title} — ${(k2.assignee_names || []).join(', ')} · due ${k2.due_date} · ${k2.priority}`), view: 'todos' };
  } },
  // ---- companies
  { re: /compare (companies|the companies)|which company (is|makes|earns|performs)|company (ranking|comparison)|best company|per company/, a(q, D) {
    const mk = monthIn(D, q); const rows = D.companies.filter((x) => x.id !== 1).map((x) => { const pl = F.profitAndLoss(D, Object.assign(monthRange(mk), { company: x.id })); return { name: x.name, income: pl.totalIncome, net: pl.netProfit, margin: pl.margin }; }).sort((a, b) => b.net - a.net);
    return { speak: `${monthName(mk)} by company: ${rows.slice(0, 3).map((r) => `${r.name} net ${k(r.net)}`).join(', ')}. Weakest: ${rows[rows.length - 1].name} (${k(rows[rows.length - 1].net)}).`, detail: rows.map((r) => `${r.name}: revenue ${money(r.income)} · net ${money(r.net)} · ${r.margin}%`), view: 'companies', data: rows };
  } },
  // ---- drafts
  { re: /draft|write (a|an|the)? ?(letter|notice|reminder|email|message|memo|agenda)|compose/, a(q, D, c) {
    const s = q.toLowerCase();
    if (/remind|payment|dues?|collect/.test(s)) { const ar = F.receivables(D, { company: c && c.id }); const party = ar.byParty.find((p) => s.includes(p.party_name.toLowerCase())) || ar.byParty[0]; if (!party) return null; const d = draft('payment-reminder', { party: party.party_name, amount: party.overdue || party.due, days: party.oldest, company: c && c.name }); return { speak: `Here is a payment reminder for ${party.party_name} — ${money(party.overdue || party.due)}${party.oldest ? `, ${party.oldest} days overdue` : ''}. Say “send” and I will queue it.`, detail: [d.body], view: 'draft', data: d }; }
    if (/warn|late|attendance|punctual/.test(s)) { const emp = P.findEmployee(D, q) || (P.patterns(D).chronicLate[0] ? D.employees.find((e) => e.id === P.patterns(D).chronicLate[0].id) : null); if (!emp) return null; const pt = P.patterns(D).rows.find((r) => r.id === emp.id) || {}; const d = draft('warning-letter', { name: emp.name, lateDays: pt.lateDays || 0, presentDays: pt.present || 0, lateMinutes: pt.lateMinutes || 0, absent: pt.absent || 0 }); return { speak: `Warning letter drafted for ${emp.name}.`, detail: [d.body], view: 'draft', data: d }; }
    if (/agenda|meeting/.test(s)) { const top = X.all(D, { company: c && c.id }).slice(0, 6); const d = draft('meeting-agenda', { subject: 'Management review', points: top.map((t) => `${t.severity >= 4 ? '★ ' : ''}${t.title} — ${t.recommend}`) }); return { speak: 'Agenda drafted from today’s top decisions.', detail: [d.body], view: 'draft', data: d }; }
    const subj = (q.match(/(?:notice|memo)\s+(?:about|on|for|regarding)?\s*(.+)$/i) || [])[1] || 'Office announcement'; const d = draft('notice', { subject: cap(subj.replace(/[.?!]$/, '')), company: c && c.name }); return { speak: `Notice drafted: “${d.title}”.`, detail: [d.body], view: 'draft', data: d };
  } },
  // ---- explain / knowledge (last — concept questions)
  { re: /what (is|are|does)|explain|meaning of|how (is|are|does|do)|why|define|difference between|gl code|account code|\b\d{4}\b/, a(q) { return K.explain(q); } },
];

export function answer(q, ctx = {}) {
  const D = store.get(); if (!D) return null;
  const s = String(q || '').trim(); const nq = s.toLowerCase();
  let c = companyIn(D, nq);
  if (!c && !/across the group|whole group|all compan/.test(nq) && ctx && ctx.company != null) c = (D.companies || []).find((x) => x.id === +ctx.company) || null;
  if (!c && !/across the group|whole group|all compan/.test(nq) && typeof window !== 'undefined' && window.EonErp && window.EonErp.company && window.EonErp.company() != null) c = (D.companies || []).find((x) => x.id === +window.EonErp.company()) || null;
  for (const it of INTENTS) {
    if (!it.re.test(nq)) continue;
    try { const r = it.a(nq, D, c); if (r) return Object.assign({ company: c ? c.name : null }, r); } catch (e) { console.warn('[EON erp] intent failed:', e); }
  }
  return null;
}
/** commands the ERP domain owns outright (so the generic agent does not hijack them) */
export function claims(q) {
  return /\b(brief|approv|payroll|salar|receivable|payable|trial balance|balance sheet|profit|cash|budget|pipeline|leads?|absent|attendance|evaluate|draft|ledger)\b/i.test(String(q || ''));
}

export const EonErpQA = { answer, claims, companyIn, draft, INTENTS };
if (typeof window !== 'undefined') {
  window.EonErpQA = Object.assign(window.EonErpQA || {}, EonErpQA);
  (window.__eonDomainQueue = window.__eonDomainQueue || []).push({ id: 'erp', priority: 90, claims, answer: (q, ctx) => answer(q, ctx) });
}
export default EonErpQA;
