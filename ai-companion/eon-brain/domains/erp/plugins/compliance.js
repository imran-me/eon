/* ============================================================
   EON · Bangladesh compliance calendar (plug-in)

   One statutory calendar for a Bangladeshi group of companies —
   VAT (Mushak 9.1), TDS on salaries, advance income tax, the
   company return (Tax Day), trade and fire licences, RJSC annual
   return / AGM, TIN-BIN, and wages under the Labour Act — merged
   with what the ERP already knows: office to-dos whose titles look
   like compliance work and payroll still pending past the 7th.

   Every rule states its legal basis. Where practice varies the
   note says "verify with your accountant" — EON reminds, the
   accountant confirms.

   Registers: Ask EON domain 'compliance' (priority 95), decision
   provider (layer ops, tag compliance), panel on the ops screen.
   Mirrored in PHP by server/lib/tools/compliance.php and
   server/lib/decisions/compliance.php — keep RULES identical.
   ============================================================ */
import { iso, addDays, daysBetween, monthKey, MONTHS, sum, fmtBDT, fmtBDTk } from '../dataset.js';
import { addProvider } from '../decisions.js';

const VERIFY = 'verify with your accountant';
/* Bengali numerals — a Bangla sentence carries Bangla digits, always */
const bnDigits = (n) => String(n).replace(/\d/g, (d) => '০১২৩৪৫৬৭৮৯'[+d]);
const TODO_RE = /vat|mushak|tax|licen[cs]e|insurance|rjsc|return|audit/i;
const WINDOW = 45;          // days ahead the calendar looks
const LOOKBACK = 30;        // days back we still surface an obligation the ERP shows as unfinished

/* ---------- THE calendar (JS ↔ PHP mirror) ----------
   kind: 'monthly' (day of the following month) · 'annual' (month/day each year) · 'dates' (fixed [month,day] list)
   match: to-do titles that represent this obligation · erp: ERP-derived evidence hook */
export const RULES = [
  { id: 'vat-return', kind: 'monthly', day: 15, name: 'VAT return — Mushak 9.1 for the previous month', name_bn: 'ভ্যাট রিটার্ন — আগের মাসের মুসক ৯.১', basis: 'VAT & SD Act 2012 s.64 and VAT & SD Rules 2016: the return for each tax period is filed by the 15th of the following month, with the net VAT payable deposited by the same date.', match: /vat|mushak/i },
  { id: 'vds-deposit', kind: 'monthly', day: 7, name: 'VAT deducted at source (VDS) — treasury deposit', name_bn: 'উৎসে কর্তিত ভ্যাট (ভিডিএস) — ট্রেজারিতে জমা', basis: 'VAT & SD Act 2012 s.49 with the VDS rules: VAT withheld from suppliers is deposited to the treasury and Mushak 6.6 issued to the supplier soon after deduction (within the first week of the following month in current practice).', note: VERIFY, match: /\bvds\b|vat (deposit|withh)|withholding vat/i },
  { id: 'tds-salary', kind: 'monthly', day: 14, name: 'TDS on salaries — monthly treasury deposit', name_bn: 'বেতনের উৎসে কর (টিডিএস) — মাসিক ট্রেজারি জমা', basis: 'Income Tax Act 2023 s.86 (tax deducted from salary at the average rate) read with the withholding rules: tax deducted in a month is deposited within two weeks after the month ends (June deductions on a shorter timeline).', note: VERIFY, match: /\btds\b|salary tax|withholding tax deposit|source tax/i },
  { id: 'wages', kind: 'monthly', day: 7, name: 'Wages for the previous month paid to all workers', name_bn: 'আগের মাসের মজুরি সব কর্মীকে পরিশোধ', basis: 'Bangladesh Labour Act 2006 s.123: wages are payable within seven working days after the end of the wage period. EON uses the 7th of the month as the practical line.', match: /payroll|salary|salaries|wages/i, erp: 'payroll' },
  { id: 'wht-return', kind: 'dates', dates: [[1, 31], [7, 31]], name: 'Half-yearly withholding tax return', name_bn: 'ষাণ্মাসিক উৎসে কর রিটার্ন', basis: 'Income Tax Act 2023 s.177 (successor of s.75A of the 1984 Ordinance): every tax-deducting authority files a withholding return twice a year — by 31 January and 31 July.', note: VERIFY, match: /withholding (tax )?return|75a|\b177\b/i },
  { id: 'salary-statement', kind: 'annual', month: 9, day: 1, name: 'Annual statement of salaries paid and tax deducted', name_bn: 'বার্ষিক বেতন ও কর্তিত করের বিবরণী', basis: 'Income Tax Rules: the annual salary and TDS statement for the income year (with employee TINs) is filed by 1 September.', note: VERIFY, match: /salary statement|annual (tds|salary) return|tds return/i },
  { id: 'ait', kind: 'dates', dates: [[9, 15], [12, 15], [3, 15], [6, 15]], name: 'Advance income tax — quarterly instalment', name_bn: 'অগ্রিম আয়কর — ত্রৈমাসিক কিস্তি', basis: 'Income Tax Act 2023 s.154–155: where the last assessed income exceeds the threshold, advance tax is paid in four equal instalments on 15 September, 15 December, 15 March and 15 June.', match: /advance (income )?tax|quarterly tax|\bait\b/i },
  { id: 'company-return', kind: 'annual', month: 1, day: 15, name: 'Annual income tax return of each company — Tax Day', name_bn: 'প্রতিটি কোম্পানির বার্ষিক আয়কর রিটার্ন — ট্যাক্স ডে', basis: 'Income Tax Act 2023 s.2(23) “Tax Day” for companies: the 15th day of the seventh month after the end of the income year — 15 January for a June year-end. Audited accounts under the Companies Act must accompany the return.', note: 'if a company has a different income year, its Tax Day moves accordingly', match: /income tax return|tax return|corporate tax|return of income/i },
  { id: 'tin-bin', kind: 'annual', month: 1, day: 31, name: 'TIN & BIN status and proof-of-return check for every company', name_bn: 'প্রতিটি কোম্পানির টিআইএন ও বিআইএন অবস্থা এবং রিটার্ন দাখিলের প্রমাণ যাচাই', basis: 'Income Tax Act 2023 s.264 (proof of submission of return is required for licences, bank facilities and tenders) and VAT & SD Act 2012 s.4–6 (BIN registration must reflect current address, activity and turnover). TIN and BIN do not lapse, but the yearly proof of return does — refresh it right after Tax Day.', match: /\btin\b|\bbin\b|proof of (return|submission)|\bpsr\b/i },
  { id: 'trade-licence', kind: 'annual', month: 6, day: 30, name: 'Trade licence renewal — every company and branch office', name_bn: 'ট্রেড লাইসেন্স নবায়ন — প্রতিটি কোম্পানি ও শাখা অফিস', basis: 'Local Government (City Corporation) Act 2009 with the corporation’s Model Tax Schedule: trade licences run per fiscal year and are renewed by 30 June; late renewal attracts a surcharge.', match: /trade licen[cs]e/i },
  { id: 'fire-licence', kind: 'annual', month: 6, day: 30, name: 'Fire licence renewal', name_bn: 'ফায়ার লাইসেন্স নবায়ন', basis: 'Fire Prevention and Extinction Act 2003 s.4–5: premises need a licence from the Fire Service & Civil Defence, renewed annually with the fire-safety plan and drill record.', note: 'the due date follows your licence expiry — ' + VERIFY, match: /fire (licen[cs]e|safety|certificate)/i },
  { id: 'agm', kind: 'annual', month: 12, day: 31, name: 'Annual General Meeting of each company', name_bn: 'প্রতিটি কোম্পানির বার্ষিক সাধারণ সভা', basis: 'Companies Act 1994 s.81: an AGM in every calendar year, not more than 15 months after the previous one (first AGM within 18 months of incorporation). Audited accounts and auditor appointment go before it.', note: 'enter your last AGM date with the company secretary — the 15-month clock runs from it', match: /\bagm\b|annual general meeting|board meeting/i },
  { id: 'rjsc-return', kind: 'annual', month: 1, day: 21, name: 'Annual return to RJSC (Schedule X) with audited accounts', name_bn: 'আরজেএসসিতে বার্ষিক রিটার্ন (তফসিল এক্স) ও নিরীক্ষিত হিসাব', basis: 'Companies Act 1994 s.36 and s.190: the annual return with the list of members is filed with the Registrar of Joint Stock Companies within 21 days of the AGM; the balance sheet within 30 days of the AGM.', note: 'dated 21 days after a 31 December AGM — moves with your AGM', match: /\brjsc\b|annual return|schedule x|registrar/i },
];

/* ---------- helpers ---------- */
const T = (D) => (D && D.meta && D.meta.today) || iso(new Date());
const inCompany = (r, company) => company == null || r.company_id == null || +r.company_id === +company;
const ymd = (y, m, d) => `${y}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
const doneKey = (it) => `${it.id}@${it.date}`;
function doneStore() { try { return JSON.parse((typeof localStorage !== 'undefined' && localStorage.getItem('eon_compliance_done')) || '{}') || {}; } catch { return {}; } }
function saveDone(map) { try { localStorage.setItem('eon_compliance_done', JSON.stringify(map)); } catch {} try { if (typeof window !== 'undefined' && window.EonBrain && window.EonBrain.mergeStore) window.EonBrain.mergeStore({ compliance_done: map }); } catch {} }

/** all candidate dates of a rule from LOOKBACK days before to WINDOW days after today */
function occurrences(rule, today) {
  const t = new Date(today + 'T00:00:00'); const y = t.getFullYear(), m = t.getMonth() + 1; const out = [];
  if (rule.kind === 'monthly') for (let k = -1; k <= 2; k++) { const d = new Date(y, m - 1 + k, rule.day); out.push({ date: iso(d), period: monthKey(new Date(y, m - 2 + k, 1)) }); }
  else if (rule.kind === 'annual') for (let k = -1; k <= 1; k++) out.push({ date: ymd(y + k, rule.month, rule.day), period: String(y + k) });
  else if (rule.kind === 'dates') for (let k = -1; k <= 1; k++) rule.dates.forEach(([mm, dd]) => out.push({ date: ymd(y + k, mm, dd), period: String(y + k) }));
  return out.filter((o) => { const n = daysBetween(today, o.date); return n >= -LOOKBACK && n <= WINDOW; }).sort((a, b) => a.date.localeCompare(b.date));
}
const statusOf = (today, date, done) => done ? 'done' : (daysBetween(today, date) < 0 ? 'overdue' : daysBetween(today, date) <= 7 ? 'due soon' : 'upcoming');
const periodLabel = (rule, o) => (rule.kind === 'monthly' ? `${MONTHS[+o.period.slice(5) - 1]} ${o.period.slice(0, 4)}` : o.period);

/**
 * calendar(today, D?, company?) → { today, items[], overdue[], dueSoon[], upcoming[] }
 * item: { id, date, days, name, basis, note?, status, period, source:'statute'|'erp', todo?, amount?, company? }
 */
export function calendar(today, D, company = null) {
  D = D || (typeof window !== 'undefined' && window.EonErp && window.EonErp.dataset && window.EonErp.dataset()) || {};
  today = today || T(D); if (today instanceof Date) today = iso(today);
  if (company === undefined) company = (typeof window !== 'undefined' && window.EonErp && window.EonErp.company && window.EonErp.company()) || null;
  const done = doneStore();
  const todos = (D.office_todos || []).filter((k) => inCompany(k, company) && TODO_RE.test(String(k.title || '')));
  const used = new Set(); const items = [];
  const coName = (id) => ((D.companies || []).find((c) => +c.id === +id) || {}).short_name || '';

  // payroll evidence: pending payslips for the latest run
  let payroll = null;
  try {
    const P = typeof window !== 'undefined' && window.EonErp && window.EonErp.people;
    const pr = P && P.payroll ? P.payroll(D, { company }) : null;
    if (pr && pr.pending && pr.pending.length) { const [yy, mm] = pr.month.split('-').map(Number); payroll = { month: pr.month, pending: pr.pending.length, amount: sum(pr.pending, 'net_salary'), due: iso(new Date(yy, mm, 7)) }; }
  } catch {}

  RULES.forEach((rule) => {
    occurrences(rule, today).forEach((o) => {
      // the ERP to-do that stands for this occurrence (closest due date within 45 days)
      const cand = todos.filter((k) => !used.has(k.id) && rule.match.test(String(k.title || '')) && Math.abs(daysBetween(o.date, k.due_date || o.date)) <= WINDOW).sort((a, b) => Math.abs(daysBetween(o.date, a.due_date)) - Math.abs(daysBetween(o.date, b.due_date)));
      const todo = cand[0] || null; if (todo) used.add(todo.id);
      const past = daysBetween(today, o.date) < 0;
      let it = { id: rule.id, date: o.date, days: daysBetween(today, o.date), name: rule.name, name_bn: rule.name_bn || null, basis: rule.basis, note: rule.note || null, period: periodLabel(rule, o), source: 'statute', todo: todo ? { id: todo.id, title: todo.title, status: todo.status, due_date: todo.due_date, company: coName(todo.company_id) } : null, amount: 0 };
      if (rule.erp === 'payroll' && payroll && payroll.due === o.date) { it.amount = payroll.amount; it.evidence = `${payroll.pending} payslip${payroll.pending > 1 ? 's' : ''} for ${MONTHS[+payroll.month.slice(5) - 1]} still pending in the ERP`; }
      const todoDone = todo && /completed|done/i.test(todo.status);
      const marked = !!done[doneKey(it)];
      if (past) {
        // an obligation already past its date is only surfaced when the ERP still shows it unfinished
        if (todoDone || marked) return;
        if (!(todo || it.evidence)) return;
      }
      it.status = statusOf(today, o.date, todoDone || marked);
      items.push(it);
    });
  });

  // ERP to-dos that look like compliance work but match no rule
  todos.filter((k) => !used.has(k.id)).forEach((k) => {
    const isDone = /completed|done/i.test(k.status); const n = daysBetween(today, k.due_date || today);
    if (isDone || n < -LOOKBACK || n > WINDOW) return;
    items.push({ id: 'todo-' + k.id, date: k.due_date, days: n, name: k.title, basis: `Office to-do in the ERP (${k.department || 'admin'}, ${k.priority || 'normal'} priority) — a compliance item without a statutory rule attached`, note: null, period: null, source: 'erp', todo: { id: k.id, title: k.title, status: k.status, due_date: k.due_date, company: coName(k.company_id) }, amount: 0, status: statusOf(today, k.due_date, !!done['todo-' + k.id + '@' + k.due_date]) });
  });

  items.sort((a, b) => a.date.localeCompare(b.date) || a.name.localeCompare(b.name));
  return { today, window: WINDOW, items, overdue: items.filter((i) => i.status === 'overdue'), dueSoon: items.filter((i) => i.status === 'due soon'), upcoming: items.filter((i) => i.status === 'upcoming'), payroll };
}

/* ---------- decisions ---------- */
export function decisions(D, { company = null } = {}) {
  const cal = calendar(T(D), D, company); const out = [];
  cal.items.filter((i) => i.status === 'overdue' || i.status === 'due soon').slice(0, 6).forEach((i) => {
    const late = i.status === 'overdue';
    out.push({
      id: 'compliance-' + doneKey(i), layer: 'ops', tag: 'compliance', severity: late ? 4 : 3,
      title: late ? `Compliance overdue: ${i.name} (was due ${i.date}, ${-i.days}d ago)` : `Compliance due ${i.days === 0 ? 'today' : `in ${i.days} day${i.days > 1 ? 's' : ''}`}: ${i.name}`,
      /* বাংলা, written here rather than translated downstream: a statutory
         obligation is the boss's own language when he asks in it. An item that
         came from an ERP to-do keeps the to-do's English title, exactly as a
         party or a project name does. */
      title_bn: late
        ? `কমপ্লায়েন্স বাকি: ${i.name_bn || i.name} (${i.date} তারিখে দেওয়ার কথা ছিল, ${bnDigits(-i.days)} দিন পেরিয়েছে)`
        : `কমপ্লায়েন্স ${i.days === 0 ? 'আজই' : `${bnDigits(i.days)} দিনের মধ্যে`}: ${i.name_bn || i.name}`,
      why: [i.basis].concat(i.todo ? [`ERP to-do “${i.todo.title}” is ${i.todo.status}${i.todo.company ? ` (${i.todo.company})` : ''}`] : [], i.evidence ? [i.evidence] : [], i.note ? [i.note] : []),
      recommend: late ? `Clear it today and log the filing/payment; late statutory items carry penalties and interest. Confirm the actual position with the accountant before paying.` : `Have accounts prepare it now so it is filed before ${i.date}. ${i.note ? i.note.charAt(0).toUpperCase() + i.note.slice(1) + '.' : ''}`.trim(),
      recommend_bn: late
        ? 'আজই সেরে ফেলুন এবং দাখিল বা পরিশোধের প্রমাণ লিখে রাখুন; দেরিতে করলে জরিমানা ও সুদ যোগ হয়। টাকা দেওয়ার আগে হিসাবরক্ষকের কাছ থেকে প্রকৃত অবস্থা নিশ্চিত করে নিন।'
        : `${i.date} তারিখের আগেই যাতে দাখিল হয়, হিসাব বিভাগকে এখনই প্রস্তুত করতে বলুন।`,
      amount: i.amount || 0, date: i.date, actions: [{ label: 'Open compliance calendar', kind: 'navigate', href: 'operations.html#compliance' }],
    });
  });
  return out;
}

/* ---------- Ask EON ---------- */
const money = fmtBDT, k = fmtBDTk;
const line = (i) => `${i.date} · ${i.name} — ${i.status}${i.amount ? ` · ${k(i.amount)}` : ''}${i.todo ? ` (ERP to-do: ${i.todo.status})` : ''}`;
const say = (i) => `${i.name}, ${i.status === 'overdue' ? `overdue since ${i.date}` : i.days === 0 ? 'due today' : `due ${i.date}, in ${i.days} day${i.days > 1 ? 's' : ''}`}`;
const openAction = { label: 'Open compliance calendar', kind: 'navigate', href: 'operations.html#compliance' };

function reply(cal, items, intro, opts = {}) {
  if (!items.length) return { speak: `${intro} Nothing falls due in the next ${WINDOW} days.`, detail: [], view: 'ops', data: { items: [] } };
  const first = items[0]; const od = items.filter((i) => i.status === 'overdue');
  const speak = `${intro} ${items.length === 1 ? '' : `${items.length} items. `}${od.length ? `${od.length} overdue — first ${say(od[0])}. ` : ''}${first.status !== 'overdue' ? `Next: ${say(first)}. ` : ''}${opts.tail || ''}`.trim();
  return { speak, detail: items.slice(0, 10).map(line).concat(opts.basis ? ['Basis: ' + first.basis + (first.note ? ` (${first.note})` : '')] : []), view: 'ops', data: { items, today: cal.today }, actions: [openAction] };
}

const INTENTS = [
  { re: /\b(compliance|statutory|regulatory)\b( calendar| deadlines?| obligations?| items?)?|\bstatutory calendar\b|(what|which) (filings?|returns?) (are|is) due|government (deadlines?|filings?)/i, a(cal) { return reply(cal, cal.items, `Compliance calendar for the next ${WINDOW} days across the group.`); } },
  { re: /\bvat\b.*\b(due|return|deadline|when|file|filing|submit)\b|\b(when|what)\b.*\bvat\b|\bmushak\b/i, a(cal) { const r = RULES.find((x) => x.id === 'vat-return'); const items = cal.items.filter((i) => /^(vat-return|vds-deposit)$/.test(i.id) || (i.source === 'erp' && /vat|mushak/i.test(i.name))); return reply(cal, items, `VAT: Mushak 9.1 for each month is filed and paid by the 15th of the next month.`, { basis: true, tail: r ? '' : '' }); } },
  { re: /\btds\b|salary tax|tax deducted at source|withholding (tax|return)/i, a(cal) { const items = cal.items.filter((i) => /^(tds-salary|wht-return|salary-statement)$/.test(i.id)); return reply(cal, items, 'TDS on salaries under the Income Tax Act 2023: deduct at the average rate, deposit monthly, file the half-yearly withholding return and the annual salary statement.', { basis: true }); } },
  { re: /tax (deadlines?|calendar|dates|due dates?)|advance (income )?tax|income tax return|\btax day\b|when is (the )?(tax|return) due|company (tax )?return/i, a(cal) { const items = cal.items.filter((i) => /^(tds-salary|wht-return|salary-statement|ait|company-return|tin-bin|vat-return|vds-deposit)$/.test(i.id) || (i.source === 'erp' && /tax|return/i.test(i.name))); return reply(cal, items, 'Tax deadlines: advance income tax falls on 15 Sep, 15 Dec, 15 Mar and 15 Jun; the company return is due on Tax Day, 15 January for a June year-end; VAT and TDS run monthly.', { basis: true }); } },
  { re: /trade licen[cs]e|fire licen[cs]e|licen[cs]e renewal|renew (the |our )?licen[cs]es?/i, a(cal) { const items = cal.items.filter((i) => /licence/.test(i.id) || (i.source === 'erp' && /licen[cs]e/i.test(i.name))); return reply(cal, items, 'Trade licences renew each fiscal year by 30 June with the city corporation; the fire licence renews annually with Fire Service & Civil Defence.', { basis: true }); } },
  { re: /\brjsc\b|annual return|\bagm\b|annual general meeting|companies act/i, a(cal) { const items = cal.items.filter((i) => /^(agm|rjsc-return)$/.test(i.id) || (i.source === 'erp' && /rjsc|return|agm/i.test(i.name))); return reply(cal, items, 'RJSC: hold the AGM within 15 months of the last one and file the annual return within 21 days of it (Companies Act 1994 s.81, s.36).', { basis: true }); } },
  { re: /\btin\b|\bbin\b.*(valid|renew|expir|status)|proof of (return|submission)|\bpsr\b/i, a(cal) { const items = cal.items.filter((i) => i.id === 'tin-bin'); return reply(cal, items, 'TIN and BIN do not expire, but every company needs a fresh proof of return submission each year for licences, banks and tenders.', { basis: true }); } },
  { re: /labou?r (law|act)|salary (deadline|due date|payment deadline)|wages? (deadline|due|payment)|when (must|should|do) (we|i) pay (the )?(salar|wage)|by when .*salar/i, a(cal) { const items = cal.items.filter((i) => i.id === 'wages'); const tail = cal.payroll ? `The ERP still shows ${cal.payroll.pending} pending payslip${cal.payroll.pending > 1 ? 's' : ''} for ${MONTHS[+cal.payroll.month.slice(5) - 1]}, ${k(cal.payroll.amount)} net.` : 'The ERP shows no pending payslips.'; return reply(cal, items, 'Labour law: wages must be paid within seven working days after the wage period ends (Bangladesh Labour Act 2006 s.123).', { basis: true, tail }); } },
  { re: /what do we owe (the )?(government|govt|nbr|state)|(government|govt|nbr) (dues|payments|liabilit)|owe (the )?(government|nbr)/i, a(cal) {
    const items = cal.items.filter((i) => /^(vat-return|vds-deposit|tds-salary|ait|company-return|trade-licence|fire-licence|rjsc-return)$/.test(i.id));
    return reply(cal, items, `Government dues in the next ${WINDOW} days — VAT with the monthly return, TDS on salaries, advance tax instalments and licence fees; amounts come from the accountant’s computation, EON tracks the dates.`, { basis: false });
  } },
];

function answer(q) {
  const s = String(q || '').trim(); if (!s) return null;
  const hit = INTENTS.find((i) => i.re.test(s)); if (!hit) return null;
  const D = (typeof window !== 'undefined' && window.EonErp && window.EonErp.dataset && window.EonErp.dataset()) || null; if (!D) return null;
  const company = (window.EonErp.company && window.EonErp.company()) || null;
  try { return hit.a(calendar(T(D), D, company), s); } catch (e) { console.warn('[EON compliance] answer failed:', e); return null; }
}

/* ---------- panel (ops, order 40) ---------- */
function renderPanel() {
  const esc = (window.EonApp && window.EonApp.esc) || ((s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]));
  const D = window.EonErp && window.EonErp.dataset && window.EonErp.dataset(); if (!D) return '<div class="empty">No data yet.</div>';
  const cal = calendar(T(D), D, window.EonErp.company ? window.EonErp.company() : null);
  const rows = cal.items.filter((i) => i.status !== 'done').slice(0, 8);
  const cls = (s) => s === 'overdue' ? 'no' : s === 'due soon' ? 'warn' : 'ok';
  const head = `<div class="hint" id="compliance">Next ${WINDOW} days · ${cal.overdue.length} overdue · ${cal.dueSoon.length} due within 7 days · ${cal.upcoming.length} upcoming</div>`;
  if (!rows.length) return head + '<div class="empty">Nothing statutory falls due in the window.</div>';
  return head + `<div class="list">${rows.map((i) => `<div class="item"><div><div class="t"><span class="tag ${cls(i.status)}">${esc(i.status)}</span>${esc(i.date)} · ${esc(i.name)}${i.amount ? ` · ${esc(k(i.amount))}` : ''}</div><div class="why">${esc(i.basis)}${i.note ? ' — ' + esc(i.note) : ''}${i.todo ? ` · ERP to-do “${esc(i.todo.title)}” ${esc(i.todo.status)}` : ''}${i.evidence ? ' · ' + esc(i.evidence) : ''}</div></div><div class="meta">${i.days < 0 ? esc(`${-i.days}d late`) : i.days === 0 ? 'today' : esc(`in ${i.days}d`)}<br><button class="btn sm ok" data-compliance-done="${esc(doneKey(i))}">done</button></div></div>`).join('')}</div>`;
}

/* ---------- registration (never throws at import) ---------- */
if (typeof window !== 'undefined') {
  (window.__eonDomainQueue = window.__eonDomainQueue || []).push({ id: 'compliance', priority: 95, answer(q) { return answer(q); } });
  try { addProvider((D, o) => decisions(D, o || {})); } catch {}
  const reg = () => { try { window.EonApp && window.EonApp.registerPanel && window.EonApp.registerPanel('ops', { id: 'compliance', title: 'Compliance calendar', order: 40, render: renderPanel }); } catch {} };
  if (window.EonApp) reg(); else window.addEventListener('eon:app-ready', reg);
  if (!window.__eonComplianceClicks && typeof document !== 'undefined') {
    window.__eonComplianceClicks = true;
    document.addEventListener('click', (ev) => {
      const b = ev.target && ev.target.closest && ev.target.closest('[data-compliance-done]'); if (!b) return;
      const map = doneStore(); map[b.dataset.complianceDone] = iso(new Date()); saveDone(map);
      try { window.EonApp && window.EonApp.toast && window.EonApp.toast('Marked done in the compliance calendar'); } catch {}
      try { window.EonApp && window.EonApp.render && window.EonApp.render(); } catch {}
    });
  }
  window.EonCompliance = Object.assign(window.EonCompliance || {}, { RULES, calendar, decisions, answer, render: renderPanel });
}
export default { RULES, calendar, decisions, answer };
