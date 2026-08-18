/* ============================================================
   EON · ERP finance layer — the accountant in the brain.
   Pure functions over the dataset: ledger balances, trial balance,
   P&L, balance sheet, cash position, receivables/payables aging,
   expenses vs budget, spend anomalies, runway — and the decisions
   they imply. Every number is traceable to journal lines or
   schedule rows, and every decision says why.
   ============================================================ */
import { iso, addDays, daysBetween, monthKey, startOfMonth, indexBy, groupBy, sum, fmtBDT, fmtBDTk, pct } from './dataset.js';
import { accountTypeOf, COA_LEAVES } from './knowledge.js';

const inCompany = (row, cid) => cid == null || row.company_id == null || row.company_id === cid;
const inRange = (d, from, to) => (!from || d >= from) && (!to || d <= to);
const nameOf = (D, code) => { const a = (D.accounts || []).find((x) => x.code === code); return a ? a.name : (COA_LEAVES[code] || code); };
const T = (D) => (D.meta && D.meta.today) || iso(new Date());

/* ---------- 1. ledger ---------- */
export function ledgerBalances(D, { from = null, to = null, company = null } = {}) {
  const bal = new Map();  // code → { debit, credit }
  (D.journal_entries || []).forEach((je) => {
    if (company != null && je.company_id !== company) return;
    if (!inRange(je.date, from, to)) return;
    je.items.forEach((it) => {
      const b = bal.get(it.account_code) || { code: it.account_code, debit: 0, credit: 0 };
      b.debit += +it.debit || 0; b.credit += +it.credit || 0; bal.set(it.account_code, b);
    });
  });
  bal.forEach((b) => { b.type = accountTypeOf(b.code); b.name = nameOf(D, b.code); b.balance = (b.type === 'asset' || b.type === 'expense') ? b.debit - b.credit : b.credit - b.debit; });
  return bal;
}
export function accountLedger(D, code, { from = null, to = null, company = null } = {}) {
  const rows = [];
  let run = 0; const type = accountTypeOf(code);
  (D.journal_entries || []).filter((je) => (company == null || je.company_id === company) && inRange(je.date, from, to)).sort((a, b) => a.date.localeCompare(b.date) || a.id - b.id).forEach((je) => {
    je.items.forEach((it) => { if (it.account_code !== code) return; run += (type === 'asset' || type === 'expense') ? it.debit - it.credit : it.credit - it.debit; rows.push({ date: je.date, reference: je.reference, description: je.description, source: je.source, debit: it.debit, credit: it.credit, balance: run, company_id: je.company_id }); });
  });
  return { code, name: nameOf(D, code), type, rows, closing: run };
}
export function trialBalance(D, opts = {}) {
  const bal = ledgerBalances(D, opts);
  const rows = [...bal.values()].sort((a, b) => a.code.localeCompare(b.code)).map((b) => ({ code: b.code, name: b.name, type: b.type, debit: Math.max(0, (b.type === 'asset' || b.type === 'expense') ? b.balance : -b.balance), credit: Math.max(0, (b.type === 'asset' || b.type === 'expense') ? -b.balance : b.balance) }));
  const totalDebit = sum(rows, 'debit'), totalCredit = sum(rows, 'credit');
  return { rows, totalDebit, totalCredit, balanced: Math.abs(totalDebit - totalCredit) < 2 };
}
export function profitAndLoss(D, { from = null, to = null, company = null } = {}) {
  const bal = ledgerBalances(D, { from, to, company });
  const pickType = (fn) => [...bal.values()].filter(fn);
  const income = pickType((b) => b.type === 'income');
  const direct = pickType((b) => b.type === 'expense' && /^5/.test(b.code));
  const opex = pickType((b) => b.type === 'expense' && /^[67]/.test(b.code));
  const fin = pickType((b) => b.type === 'expense' && /^8/.test(b.code));
  const s = (a) => sum(a, 'balance');
  const totalIncome = s(income), totalDirect = s(direct), totalOpex = s(opex), totalFin = s(fin);
  const gross = totalIncome - totalDirect, net = gross - totalOpex - totalFin;
  const top = (a) => a.filter((x) => x.balance).sort((x, y) => y.balance - x.balance).map((x) => ({ code: x.code, name: x.name, amount: x.balance }));
  return { from, to, company, income: top(income), directCost: top(direct), opex: top(opex), finance: top(fin), totalIncome, totalDirect, totalOpex, totalFin, grossProfit: gross, netProfit: net, margin: totalIncome ? Math.round(net / totalIncome * 100) : 0 };
}
export function balanceSheet(D, { asOf = null, company = null } = {}) {
  const bal = ledgerBalances(D, { to: asOf, company });
  const rows = (fn) => [...bal.values()].filter(fn).filter((b) => Math.abs(b.balance) > 0.5).sort((a, b) => a.code.localeCompare(b.code)).map((b) => ({ code: b.code, name: b.name, amount: b.balance }));
  const assets = rows((b) => b.type === 'asset'), liabilities = rows((b) => b.type === 'liability'), equity = rows((b) => b.type === 'equity');
  const pl = profitAndLoss(D, { to: asOf, company });
  const tA = sum(assets, 'amount'), tL = sum(liabilities, 'amount'), tE = sum(equity, 'amount') + pl.netProfit;
  return { asOf, assets, liabilities, equity: equity.concat([{ code: '—', name: 'Current period profit', amount: pl.netProfit }]), totalAssets: tA, totalLiabilities: tL, totalEquity: tE, balanced: Math.abs(tA - (tL + tE)) < 2 };
}

/* ---------- 2. cash ---------- */
export function cashPosition(D, { company = null } = {}) {
  const banks = (D.banks || []).filter((b) => company == null || b.company_id === company).map((b) => ({ id: b.id, name: b.name, type: b.type, company_id: b.company_id, company: (D.companies.find((c) => c.id === b.company_id) || {}).short_name, balance: +b.balance || 0 }));
  const total = sum(banks, 'balance');
  const byCompany = [...groupBy(banks, 'company_id').entries()].map(([cid, arr]) => ({ company_id: cid, company: (D.companies.find((c) => c.id === cid) || {}).name, total: sum(arr, 'balance') })).sort((a, b) => b.total - a.total);
  const low = banks.filter((b) => b.balance < 50000 && b.type !== 'cash');
  return { banks: banks.sort((a, b) => b.balance - a.balance), total, byCompany, low, negative: banks.filter((b) => b.balance < 0) };
}

/* ---------- 3. receivables / payables ---------- */
function ageBucket(days) { return days <= 0 ? 'current' : days <= 30 ? '1–30' : days <= 60 ? '31–60' : days <= 90 ? '61–90' : '90+'; }
/* The dues that never reach payment_schedules. Epal sells air tickets, visas,
   contract files and contract flights, so the money sits on those invoices —
   reading only payment_schedules reports receivables of zero while lakhs are
   outstanding. Mirrors Analytics::invoiceDues() on the server. */
const SALES_LINES = { ticket_sales: 'air ticket', visa_sales: 'visa', contract_file_sales: 'contract file', contract_flight_bookings: 'contract flight' };
export function invoiceDues(D, type, { company = null } = {}) {
  const today = T(D); const out = [];
  const row = (r, kind, label, name, id, when) => {
    const paid = +(r.paid_amount || 0);
    const due = r.due_amount != null ? +r.due_amount : +(r.total || 0) - paid;
    if (Math.round(due) <= 0) return;
    out.push({ id: label + '-' + r.id, company_id: r.company_id ?? null, type, party_type: kind, party_id: id ?? null,
      party_name: name || ('Unnamed ' + kind), source_label: label + ' ' + (r.invoice || r.id || ''),
      amount: paid + due, paid_amount: paid, scheduled_date: String(when || r.date || today).slice(0, 10),
      status: 'pending', from_invoice: true });
  };
  if (type === 'receive') {
    for (const [table, label] of Object.entries(SALES_LINES)) {
      for (const r of (D[table] || [])) { if (!inCompany(r, company)) continue; row(r, 'client', label, r.client, r.client_id, r.due_date || r.receivable_date); }
    }
  } else if (type === 'pay') {
    for (const r of (D.ticket_purchases || [])) {
      if (!inCompany(r, company)) continue;
      // bought on a booking portal (BSP/IATA) → the money is owed to the portal
      const name = r.vendor || r.portal || r.airline || 'Ticket vendor';
      row(r, 'vendor', 'ticket purchase', name, r.vendor_id ?? (r.portal_id ? 'portal:' + r.portal_id : null), r.due_date);
    }
    for (const r of (D.visa_processes || [])) {
      const paid = +(r.cost_paid_amount || 0), due = +(r.costing_price || 0) - paid;
      if (Math.round(due) <= 0) continue;
      out.push({ id: 'visa-cost-' + r.id, company_id: null, type: 'pay', party_type: 'vendor', party_id: r.vendor_id ?? null,
        party_name: 'Visa vendor' + (r.country ? ' — ' + r.country : ''), source_label: 'visa ' + (r.application_id || r.id || ''),
        amount: paid + due, paid_amount: paid, scheduled_date: String(r.payable_date || today).slice(0, 10), status: 'pending', from_invoice: true });
    }
  }
  return out;
}

export function schedules(D, type, { company = null } = {}) {
  const today = T(D);
  const open = (D.payment_schedules || []).filter((p) => p.type === type && ['pending', 'overdue'].includes(p.status) && inCompany(p, company))
    .concat(invoiceDues(D, type, { company }))
    .map((p) => { const due = +p.amount - (+p.paid_amount || 0); const days = daysBetween(p.scheduled_date, today); return Object.assign({}, p, { due, days_overdue: Math.max(0, days), bucket: ageBucket(days), overdue: days > 0 }); })
    .filter((p) => Math.round(p.due) > 0);
  const overdue = open.filter((p) => p.overdue).sort((a, b) => b.days_overdue - a.days_overdue || b.due - a.due);
  const buckets = ['current', '1–30', '31–60', '61–90', '90+'].map((b) => ({ bucket: b, amount: sum(open.filter((p) => p.bucket === b), 'due'), count: open.filter((p) => p.bucket === b).length }));
  const byParty = [...groupBy(open, (p) => p.party_type + ':' + p.party_id).values()].map((arr) => ({ party_type: arr[0].party_type, party_id: arr[0].party_id, party_name: arr[0].party_name, due: sum(arr, 'due'), overdue: sum(arr.filter((p) => p.overdue), 'due'), count: arr.length, oldest: Math.max(...arr.map((p) => p.days_overdue)) })).sort((a, b) => b.overdue - a.overdue || b.due - a.due);
  const dueSoon = open.filter((p) => !p.overdue && daysBetween(today, p.scheduled_date) <= 7).sort((a, b) => a.scheduled_date.localeCompare(b.scheduled_date));
  return { type, open, total: sum(open, 'due'), overdue, overdueTotal: sum(overdue, 'due'), buckets, byParty, dueSoon, dueSoonTotal: sum(dueSoon, 'due'), rescheduled: open.filter((p) => p.reschedule_count > 0) };
}
export const receivables = (D, o) => schedules(D, 'receive', o);
export const payables = (D, o) => schedules(D, 'pay', o);

/* ---------- 4. expenses ---------- */
export function expensesVsBudget(D, { month = null, company = null } = {}) {
  const mk = month || monthKey(T(D));
  const exp = (D.expenses || []).filter((e) => e.expense_date.slice(0, 7) === mk && inCompany(e, company));
  const budgets = (D.expense_budgets || []).filter((b) => inCompany(b, company));
  const cats = new Map();
  exp.forEach((e) => { const c = cats.get(e.category) || { category: e.category, spent: 0, approved: 0, pending: 0, budget: 0, count: 0 }; c.spent += +e.amount; c.count++; if (e.approval_status === 'approved') c.approved += +e.amount; else c.pending += +e.amount; cats.set(e.category, c); });
  budgets.forEach((b) => { const c = cats.get(b.category) || { category: b.category, spent: 0, approved: 0, pending: 0, budget: 0, count: 0 }; c.budget += +b.amount; cats.set(b.category, c); });
  const rows = [...cats.values()].map((c) => Object.assign(c, { pct: c.budget ? Math.round(c.spent / c.budget * 100) : null, over: c.budget ? c.spent > c.budget : false, warn: c.budget ? c.spent >= c.budget * 0.8 : false })).sort((a, b) => (b.pct || 0) - (a.pct || 0));
  return { month: mk, rows, totalSpent: sum(rows, 'spent'), totalBudget: sum(rows, 'budget'), over: rows.filter((r) => r.over), warn: rows.filter((r) => r.warn && !r.over), pending: exp.filter((e) => e.approval_status === 'pending') };
}
export function pendingExpenses(D, { company = null } = {}) {
  const rows = (D.expenses || []).filter((e) => e.approval_status === 'pending' && inCompany(e, company)).sort((a, b) => b.amount - a.amount);
  return { rows, count: rows.length, total: sum(rows, 'amount') };
}
export function expenseAnomalies(D, { company = null } = {}) {
  // Compare each (company, category) against its own history. For the running
  // month the comparison is like-for-like: spend up to today's day-of-month vs
  // the same day-of-month in prior months (so rent paid on the 3rd is not a spike).
  const rows = (D.expenses || []).filter((e) => e.approval_status !== 'rejected' && inCompany(e, company));
  const key = (e) => e.company_id + '|' + e.category;
  const byKey = groupBy(rows, key); const out = [];
  const months = [...new Set(rows.map((e) => e.expense_date.slice(0, 7)))].sort();
  const cur = monthKey(T(D)); const dayNow = new Date(T(D) + 'T00:00:00').getDate();
  const flag = (cid, cat, month, v, prior, projected) => {
    if (prior.length < 2) return;
    const mean = prior.reduce((a, b) => a + b, 0) / prior.length; const sd = Math.sqrt(prior.reduce((a, b) => a + (b - mean) ** 2, 0) / prior.length) || mean * 0.1;
    if (v > mean * 1.5 && v > mean + 2 * sd && v > 5000) out.push({ company_id: +cid, company: (D.companies.find((c) => c.id === +cid) || {}).short_name, category: cat, month, amount: v, projected: Math.round(projected), mean: Math.round(mean), ratio: +(v / mean).toFixed(2), z: +((v - mean) / sd).toFixed(1) });
  };
  byKey.forEach((arr, k) => {
    const [cid, cat] = k.split('|');
    const full = new Map(), partial = new Map();
    arr.forEach((e) => { const mo = e.expense_date.slice(0, 7); const d = +e.expense_date.slice(8, 10); full.set(mo, (full.get(mo) || 0) + +e.amount); if (d <= dayNow) partial.set(mo, (partial.get(mo) || 0) + +e.amount); });
    const idx = months.indexOf(cur);
    // running month: like-for-like to today's day
    if (idx >= 2 && full.has(cur)) { const v = partial.get(cur) || 0; const prior = months.slice(0, idx).map((m) => partial.get(m) || 0).filter((x) => x > 0); flag(cid, cat, cur, v, prior, (full.get(cur) || 0) * (30 / Math.max(1, dayNow))); }
    // last full month vs the ones before it
    const li = idx >= 0 ? idx - 1 : months.length - 1;
    if (li >= 2) { const m = months[li]; const v = full.get(m) || 0; const prior = months.slice(0, li).map((x) => full.get(x) || 0).filter((x) => x > 0); flag(cid, cat, m, v, prior, v); }
  });
  return out.sort((a, b) => b.ratio - a.ratio);
}

/* ---------- 5. revenue & runway ---------- */
export function revenueTrend(D, { company = null, months = 6 } = {}) {
  const today = new Date(T(D) + 'T00:00:00'); const out = [];
  for (let i = months - 1; i >= 0; i--) {
    const d = new Date(today.getFullYear(), today.getMonth() - i, 1); const mk = monthKey(d);
    const pl = profitAndLoss(D, { from: mk + '-01', to: iso(new Date(d.getFullYear(), d.getMonth() + 1, 0)), company });
    out.push({ month: mk, income: pl.totalIncome, direct: pl.totalDirect, opex: pl.totalOpex, net: pl.netProfit });
  }
  const mtd = out[out.length - 1]; const prev = out[out.length - 2] || mtd;
  const day = today.getDate(); const dim = new Date(today.getFullYear(), today.getMonth() + 1, 0).getDate();
  const runRate = mtd.income * (dim / Math.max(1, day));
  return { series: out, mtd, prev, runRate, vsPrev: prev.income ? Math.round((runRate - prev.income) / prev.income * 100) : 0 };
}
export function runway(D, { company = null } = {}) {
  const cash = cashPosition(D, { company }).total;
  const tr = revenueTrend(D, { company, months: 4 }).series.slice(0, -1);
  const avgNet = tr.length ? sum(tr, 'net') / tr.length : 0;
  const avgOut = tr.length ? sum(tr, (r) => r.direct + r.opex) / tr.length : 0;
  return { cash, avgMonthlyNet: avgNet, avgMonthlyOutflow: avgOut, monthsOfCover: avgOut ? +(cash / avgOut).toFixed(1) : null, burning: avgNet < 0, monthsToZero: avgNet < 0 ? +(cash / -avgNet).toFixed(1) : null };
}
export function topParties(D, { company = null } = {}) {
  const today = T(D); const from = iso(addDays(today, -90));
  const s = (D.sales || []).filter((x) => x.date >= from && inCompany(x, company)); const p = (D.purchases || []).filter((x) => x.date >= from && inCompany(x, company));
  const cust = [...groupBy(s, 'customer_id').values()].map((a) => ({ id: a[0].customer_id, name: a[0].customer, total: sum(a, 'total'), due: sum(a, 'due_amount'), invoices: a.length })).sort((a, b) => b.total - a.total).slice(0, 8);
  const sup = [...groupBy(p, 'supplier_id').values()].map((a) => ({ id: a[0].supplier_id, name: a[0].supplier, total: sum(a, 'total'), due: sum(a, 'due_amount'), bills: a.length })).sort((a, b) => b.total - a.total).slice(0, 8);
  return { customers: cust, suppliers: sup, window: 90 };
}

/* ---------- 6. decisions ---------- */
export function decisions(D, { company = null } = {}) {
  const out = []; const push = (d) => out.push(Object.assign({ layer: 'finance', company_id: company }, d));
  const today = T(D); const cid = (id) => (D.companies.find((c) => c.id === id) || {}).short_name || ('#' + id);
  const ar = receivables(D, { company }), ap = payables(D, { company }), cash = cashPosition(D, { company }), rw = runway(D, { company });
  const bud = expensesVsBudget(D, { company }), pend = pendingExpenses(D, { company }), anom = expenseAnomalies(D, { company }), tr = revenueTrend(D, { company });

  if (ar.overdueTotal > 0) {
    const top = ar.byParty.filter((p) => p.overdue > 0).slice(0, 3);
    push({ id: 'ar-overdue', severity: ar.overdueTotal > cash.total * 0.25 ? 5 : ar.overdue.length > 10 ? 4 : 3, title: `${fmtBDTk(ar.overdueTotal)} receivable is overdue across ${ar.overdue.length} dues`,
      why: [`Oldest: ${ar.overdue[0].party_name} — ${ar.overdue[0].days_overdue} days (${fmtBDT(ar.overdue[0].due)})`, `90+ bucket: ${fmtBDTk(ar.buckets[4].amount)} · 61–90: ${fmtBDTk(ar.buckets[3].amount)}`, `Top debtors: ${top.map((p) => `${p.party_name} ${fmtBDTk(p.overdue)}`).join(', ')}`],
      recommend: `Call ${top[0].party_name} today and put ${top.length > 1 ? top.slice(1).map((p) => p.party_name).join(' and ') : 'the next two'} on a written reminder — collecting the 30+ day bucket alone recovers ${fmtBDTk(ar.buckets[2].amount + ar.buckets[3].amount + ar.buckets[4].amount)}.`,
      amount: ar.overdueTotal, actions: [{ label: 'Open receivables', kind: 'navigate', href: 'finance.html#receivables' }, { label: `Draft reminder to ${top[0].party_name}`, kind: 'draft', payload: { party: top[0].party_name, amount: top[0].overdue } }], entities: top.map((p) => ({ type: 'customer', id: p.party_id, label: p.party_name })) });
  }
  if (ap.overdueTotal > 0) {
    const emp = ap.overdue.filter((p) => p.party_type === 'employee'); const sup = ap.overdue.filter((p) => p.party_type !== 'employee');
    push({ id: 'ap-overdue', severity: emp.length ? 5 : ap.overdueTotal > cash.total * 0.3 ? 4 : 3, title: `${fmtBDTk(ap.overdueTotal)} payable is past due (${ap.overdue.length} items)`,
      why: [emp.length ? `${emp.length} salary payments are late — ${fmtBDTk(sum(emp, 'due'))}` : `No salary is late`, `Suppliers overdue: ${fmtBDTk(sum(sup, 'due'))} · oldest ${sup[0] ? sup[0].party_name + ' ' + sup[0].days_overdue + 'd' : '—'}`, `Cash available: ${fmtBDTk(cash.total)}`],
      recommend: emp.length ? `Release the ${emp.length} late salaries first (${fmtBDTk(sum(emp, 'due'))}) — cash covers it ${Math.round(cash.total / Math.max(1, sum(emp, 'due')))}× — then settle suppliers older than 30 days.` : `Settle the ${sup.filter((p) => p.days_overdue > 30).length} suppliers older than 30 days (${fmtBDTk(sum(sup.filter((p) => p.days_overdue > 30), 'due'))}) this week to protect credit terms.`,
      amount: ap.overdueTotal, actions: [{ label: 'Open payables', kind: 'navigate', href: 'finance.html#payables' }] });
  }
  if (ap.dueSoonTotal > 0 && ap.dueSoonTotal > cash.total * 0.6) push({ id: 'cash-squeeze', severity: 4, title: `Payables due in 7 days (${fmtBDTk(ap.dueSoonTotal)}) take ${pct(ap.dueSoonTotal, cash.total)}% of cash`, why: [`Cash ${fmtBDTk(cash.total)} vs ${ap.dueSoon.length} payments due by ${ap.dueSoon[ap.dueSoon.length - 1].scheduled_date}`, `Receivables due in the same window: ${fmtBDTk(ar.dueSoonTotal)}`], recommend: `Sequence payments: salaries and high-priority suppliers first; push ${ap.dueSoon.filter((p) => p.priority === 'low').length} low-priority items by a week and chase ${fmtBDTk(ar.dueSoonTotal)} of receivables due now.`, amount: ap.dueSoonTotal, actions: [{ label: 'Open cash view', kind: 'navigate', href: 'finance.html#cash' }] });
  if (rw.burning && rw.monthsToZero != null && rw.monthsToZero < 6) push({ id: 'runway', severity: rw.monthsToZero < 3 ? 5 : 4, title: `At the current burn, cash lasts ${rw.monthsToZero} months`, why: [`Average monthly net: ${fmtBDTk(rw.avgMonthlyNet)}`, `Cash today: ${fmtBDTk(rw.cash)}`], recommend: 'Cut the two fastest-growing expense categories and pull receivables forward.', amount: rw.cash });
  cash.low.forEach((b) => push({ id: 'bank-low-' + b.id, severity: b.balance < 0 ? 5 : 2, title: `${b.name} (${b.company}) is ${b.balance < 0 ? 'overdrawn' : 'low'}: ${fmtBDT(b.balance)}`, why: [`Other accounts hold ${fmtBDTk(cash.total - b.balance)}`], recommend: `Transfer a float into ${b.name} before the next scheduled payment.`, amount: b.balance, actions: [{ label: 'Open banks', kind: 'navigate', href: 'finance.html#cash' }] }));
  bud.over.forEach((r) => push({ id: 'budget-over-' + r.category, severity: r.pct >= 150 ? 4 : 3, title: `${r.category} is ${r.pct}% of budget this month (${fmtBDTk(r.spent)} of ${fmtBDTk(r.budget)})`, why: [`${r.count} expense lines${r.pending ? `, ${fmtBDTk(r.pending)} still pending approval` : ''}`], recommend: r.pending ? `Hold the pending ${r.category} approvals until the owner justifies the overrun.` : `Freeze discretionary ${r.category} spend for the rest of the month.`, amount: r.spent - r.budget, actions: [{ label: 'Open budgets', kind: 'navigate', href: 'finance.html#budgets' }] }));
  bud.warn.slice(0, 3).forEach((r) => push({ id: 'budget-warn-' + r.category, severity: 2, title: `${r.category} at ${r.pct}% of budget with ${new Date(T(D) + 'T00:00:00').getDate() < 20 ? 'a third of the month left' : 'days to go'}`, why: [`${fmtBDTk(r.spent)} of ${fmtBDTk(r.budget)}`], recommend: 'Watch it — no action yet.', amount: r.spent }));
  anom.slice(0, 4).forEach((a) => push({ id: `anomaly-${a.company_id}-${a.category}`, severity: a.ratio >= 2 ? 4 : 3, title: `${a.category} at ${a.company} is running ${a.ratio}× its usual month`, why: [`${a.month}: ${fmtBDTk(a.amount)}${a.projected !== a.amount ? ` (projects to ${fmtBDTk(a.projected)})` : ''} vs a normal ${fmtBDTk(a.mean)}`, `z-score ${a.z}`], recommend: `Ask ${a.company}'s admin what changed in ${a.category} — one-off or new run-rate?`, amount: a.amount, actions: [{ label: 'Open expenses', kind: 'navigate', href: 'finance.html#expenses' }] }));
  if (pend.count) push({ id: 'expenses-pending', severity: pend.total > 200000 ? 3 : 2, title: `${pend.count} expenses (${fmtBDTk(pend.total)}) are waiting for approval`, why: [`Largest: ${pend.rows[0].title} — ${fmtBDT(pend.rows[0].amount)} (${cid(pend.rows[0].company_id)}, ${pend.rows[0].user_name})`], recommend: 'Approve the routine ones in one pass; question anything above budget.', amount: pend.total, actions: [{ label: 'Open approvals', kind: 'navigate', href: 'index.html#approvals' }], approval: true });
  if (tr.vsPrev <= -15) push({ id: 'revenue-dip', severity: 3, title: `Revenue is tracking ${Math.abs(tr.vsPrev)}% below last month`, why: [`MTD ${fmtBDTk(tr.mtd.income)} → run-rate ${fmtBDTk(tr.runRate)} vs ${fmtBDTk(tr.prev.income)} last month`], recommend: 'Check the pipeline: which won deals have not been invoiced yet?', amount: tr.prev.income - tr.runRate });
  else if (tr.vsPrev >= 15) push({ id: 'revenue-up', severity: 1, title: `Revenue is tracking ${tr.vsPrev}% above last month`, why: [`Run-rate ${fmtBDTk(tr.runRate)} vs ${fmtBDTk(tr.prev.income)}`], recommend: 'Good month — make sure delivery capacity keeps up.', amount: tr.runRate - tr.prev.income });
  return out.sort((a, b) => b.severity - a.severity || (b.amount || 0) - (a.amount || 0));
}

export const EonErpFinance = { ledgerBalances, accountLedger, trialBalance, profitAndLoss, balanceSheet, cashPosition, schedules, receivables, payables, expensesVsBudget, pendingExpenses, expenseAnomalies, revenueTrend, runway, topParties, decisions };
if (typeof window !== 'undefined') window.EonErpFinance = Object.assign(window.EonErpFinance || {}, EonErpFinance);
export default EonErpFinance;
