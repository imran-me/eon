/* ============================================================
   EON · ERP knowledge — what EON knows about the Epal ERP as a
   system: the chart of accounts, how entries post, how payroll is
   computed, how approvals flow, which reports exist, who may do
   what. This is EON's *understanding*; the numbers come from the
   dataset (see dataset.js) and the decision layers.

   Two uses:
     • offline answers — explain(q) matches concept questions
     • the language model — systemContext() renders the same
       knowledge as compact text for the server-side prompt
   Source of truth: docs/erp-domain-map.md (from the ERP repo).
   ============================================================ */

/* ---------- 1. chart of accounts scheme (live numbering, 2026-08-12) ---------- */
export const COA_RANGES = [
  { from: 1000, to: 1099, type: 'asset',     group: 'Cash',                     note: '1011 Petty Cash – Head Office (petty-cash pool), 1013 Office Cash, 1015 Petty Cash Float' },
  { from: 1100, to: 1199, type: 'asset',     group: 'Bank accounts',            note: 'one leaf per bank; banks.account_id links here' },
  { from: 1300, to: 1399, type: 'asset',     group: 'Receivables & advances',   note: '1311 Customer Receivable, 1351 Director’s Current Account' },
  { from: 1400, to: 1499, type: 'asset',     group: 'Inventory, prepaid, staff loans', note: '1400 Inventory, 1455–1457 Employee Loan, 1470 Prepaid' },
  { from: 2100, to: 2199, type: 'liability', group: 'Payables',                 note: '2111 Supplier Payable' },
  { from: 2200, to: 2299, type: 'liability', group: 'Accrued & statutory',      note: '2210 Salaries Payable, 2240 Employee Expense Reimbursement Payable, 2270 Income Tax Payable, 2280 Withholding Tax (TDS/VDS) Payable' },
  { from: 2400, to: 2599, type: 'liability', group: 'Borrowings',               note: '2410 Short-term Loan, 2440 Credit Card Payable, 2510 Bank Loan LT, 2520 Party Loan LT, 2560 Vehicle Loan' },
  { from: 3000, to: 3999, type: 'equity',    group: 'Equity',                   note: '3110 Owner Investment, 3210 Drawings, 3310 Retained Earnings, 3400 Opening Balance Equity' },
  { from: 4000, to: 4999, type: 'income',    group: 'Income',                   note: '4110 Air Ticket Sales, 4120 Visa Service Income, 4150 Contract Flight & File Revenue, 4160 Travel Commission, 4610 Product Sales' },
  { from: 5000, to: 5999, type: 'expense',   group: 'Direct cost of sales',     note: '5110 Air Ticket Purchase Cost, 5120 Visa Processing Cost, 5150 Contract Flight & File Cost, 5610 Purchase / COGS' },
  { from: 6000, to: 7999, type: 'expense',   group: 'Operating expenses',       note: '6110 Salary Expense, 6xxx staff costs, 7xxx admin & office, 7400 Miscellaneous (fallback)' },
  { from: 8000, to: 8999, type: 'expense',   group: 'Finance income & cost',    note: '8110 Interest Income (typed expense in the chart — known bug), 8520 Interest Expense, 8530 Loan Processing Fee' },
];

export const COA_LEAVES = {
  '1011': 'Petty Cash – Head Office (the petty-cash pool)', '1013': 'Office Cash', '1015': 'Petty Cash Float',
  '1311': 'Customer Receivable', '1351': 'Director’s Current Account', '1400': 'Inventory', '1455': 'Employee Loan', '1470': 'Prepaid Expenses',
  '2111': 'Supplier Payable', '2210': 'Salaries Payable', '2240': 'Employee Expense Reimbursement Payable', '2270': 'Income Tax Payable', '2280': 'Withholding Tax (TDS/VDS) Payable',
  '2410': 'Short-term Loan', '2440': 'Credit Card Payable', '2510': 'Bank Loan (long term)', '2520': 'Party Loan (long term)', '2560': 'Vehicle Loan',
  '3110': 'Owner Investment', '3210': 'Drawings', '3310': 'Retained Earnings', '3400': 'Opening Balance Equity',
  '4110': 'Air Ticket Sales', '4120': 'Visa Service Income', '4150': 'Contract Flight & File Revenue', '4160': 'Travel Commission', '4610': 'Product Sales',
  '5110': 'Air Ticket Purchase Cost', '5120': 'Visa Processing Cost', '5150': 'Contract Flight & File Cost', '5610': 'Purchase / Cost of Goods Sold',
  '6110': 'Salary Expense', '7400': 'Miscellaneous Expenses',
  '8110': 'Interest Income', '8520': 'Interest Expense', '8530': 'Loan Processing Fee',
};

export function accountTypeOf(code) {
  const n = parseInt(String(code).slice(0, 4), 10);
  const r = COA_RANGES.find((x) => n >= x.from && n <= x.to);
  return r ? r.type : (String(code)[0] === '1' ? 'asset' : String(code)[0] === '2' ? 'liability' : String(code)[0] === '3' ? 'equity' : String(code)[0] === '4' ? 'income' : 'expense');
}
export function normalBalance(type) { return (type === 'asset' || type === 'expense') ? 'debit' : 'credit'; }
export function explainCode(code) {
  code = String(code || '').trim();
  const n = parseInt(code.slice(0, 4), 10);
  if (!n) return null;
  const r = COA_RANGES.find((x) => n >= x.from && n <= x.to);
  const leaf = COA_LEAVES[code];
  const type = r ? r.type : accountTypeOf(code);
  return {
    code, name: leaf || null, type, group: r ? r.group : null, normal: normalBalance(type),
    speak: leaf ? `${code} is ${leaf} — a ${type} account (${r ? r.group : type}); it normally carries a ${normalBalance(type)} balance.`
                : `${code} sits in the ${r ? r.group : type} range (${type}); it normally carries a ${normalBalance(type)} balance.`,
    detail: r ? [`Range ${r.from}–${r.to}: ${r.group}`, `Examples: ${r.note}`] : [],
  };
}

/* ---------- 2. posting rules (how entries hit the ledger) ---------- */
export const POSTING_RULES = {
  expense: {
    title: 'Expense → ledger',
    speak: 'An expense posts only when approved. Dr the expense account taken from its category (fallback 7400 Miscellaneous). Cr depends on who paid: a reimburse-to-employee expense credits 2240 Employee Reimbursement Payable; a petty-cash-float expense credits the float account (any over-float part goes to 2240 with the custodian as party); a bank expense credits that bank’s GL leaf; otherwise the petty-cash pool 1011.',
    detail: ['Created as approval_status = pending, no journal', 'Approve (permission “approve expense”) → journal written', 'Corrections are reversals, never edits', 'Budgets: expense_budgets per category with an 80% alert threshold'],
  },
  salary: {
    title: 'Salary → ledger',
    speak: 'Payroll posts Dr 6110 Salary Expense. If paid it credits the paying bank’s GL leaf; if not yet paid it credits 2210 Salaries Payable and a payment schedule (type pay, party employee) is opened.',
    detail: ['Also writes an employee-ledger row (salary_earned)', 'Loan EMI recorded as a loan repayment via salary', 'Approved advances for the month are deducted and marked paid'],
  },
  sale: {
    title: 'Sale → ledger',
    speak: 'A sale debits Customer Receivable 1311 (or bank/cash for the paid part) and credits the income account for the line — 4110 Air Ticket Sales, 4120 Visa Service Income, 4610 Product Sales and so on. Direct cost posts to 5xxx.',
    detail: ['Travel (company 2) revenue is summed from ticket / visa / contract tables', 'Due amounts feed the receivables schedule'],
  },
  purchase: {
    title: 'Purchase → ledger',
    speak: 'A purchase debits inventory or the direct-cost account (5610 Purchase / COGS) and credits Supplier Payable 2111, or bank/cash for the paid part.',
    detail: ['Due amounts feed the payables schedule'],
  },
  reversal: {
    title: 'Reversal',
    speak: 'The ERP never edits a posted entry — it posts a mirror-image reversal linked by reversed_journal_entry_id. If a number is wrong, reverse and re-post.',
    detail: [],
  },
  opening: {
    title: 'Opening balances',
    speak: 'Historical balances are loaded against 3400 Opening Balance Equity so the books stay balanced from day one — customer receivables, supplier payables and existing assets each post an entry with 3400 as the counter-account.',
    detail: [],
  },
  shared_accounts: {
    title: 'Shared accounts rule',
    speak: 'A posting account used by more than one company must have company_id = NULL. Reports filter accounts by company but journal items by entry — a company-owned account posted to by another company shows one side and hides the other, silently breaking that company’s trial balance.',
    detail: [],
  },
};

/* ---------- 3. payroll & attendance rules ---------- */
export const PAYROLL_RULES = {
  base: 'The base figure is employee_profiles.salary (not the salary template).',
  daily: 'daily = salary ÷ days in the month; hourly = daily ÷ 9; per-minute = hourly ÷ 60.',
  absent: 'absent deduction = daily × absent days; leave deduction = daily × unpaid leave days.',
  late: 'late deduction = late minutes × per-minute rate, applied only when the month’s late minutes reach 120 (a two-hour monthly grace); below that it is zero.',
  early: 'early-out deduction = early minutes × per-minute rate; “early” means clocking out more than 10 minutes before shift end; waived when an approved leave covers that day.',
  overtime: 'overtime counts from 60 minutes past shift end and pays only if the employee is overtime-eligible: overtime pay = overtime minutes × per-minute rate.',
  loans: 'a running loan’s monthly EMI and any approved salary advance for the month are deducted.',
  net: 'net = gross − (absent + leave + late + early-out + loan EMI + advance) + overtime ± adjustment.',
  tax: 'no income tax, provident fund or gratuity is computed by the ERP today (Bangladesh tax module is on the roadmap).',
  leaveBalance: 'leave balance = leave type’s annual entitlement (max_leaves_count) − approved leave days taken this year; a request that exceeds it is rejected.',
  attendance: 'attendance status is present / absent / leave / holiday, from device punches or a manual selfie check-in (allowed per employee); weekends come from the shift’s holidays list; holidays from the holidays calendar.',
  presence: 'an employee is “online” when last seen within the last 5 minutes.',
  schedule: 'payroll:generate-monthly runs on the 1st at 01:00; schedules:mark-overdue runs daily at 00:05.',
};

/* ---------- 4. workflows ---------- */
export const WORKFLOWS = {
  expense: ['create (pending, no journal)', 'approve → journal posted', 'reverse if wrong'],
  paymentSchedule: ['pending → approve / reject', 'reschedule (logged, count kept)', 'mark paid (transaction linked)', 'auto overdue after the date'],
  leave: ['apply (balance checked)', 'approve / reject (permission approve leave)'],
  employeeRequest: ['pending → under review → approved / rejected', 'fulfil / disburse (cash, bank, cheque, payroll deduction)', 'recover via payslip instalments', 'close'],
  promotion: ['pending → approved / rejected, effective from a date'],
  resignation: ['apply (voluntary / terminated / abscond, notice days)', 'approve, last working day, exit note'],
  lead: ['new → contacted → qualified → proposal sent → negotiation → won / lost', 'won interior leads convert to projects'],
  task: ['board → column (to do / in progress / review / done)', 'assignees, due date, comments, activity log'],
};

/* ---------- 5. reports the ERP already produces ---------- */
export const REPORTS = [
  'General Ledger', 'Trial Balance', 'Profit & Loss', 'Balance Sheet', 'Account Ledger', 'Account Statement',
  'Journal Entries', 'Account Balances', 'Monthly Attendance', 'Task Report', 'Payroll Overview', 'Monthly Profit',
  'Petty Cash', 'Expense report', 'Party Statement', 'Bank Statement', 'Loan Statement', 'Payslip Statement',
];
export const REPORT_GAPS = ['AR/AP aging', 'cash-flow statement', 'opening-balance UI', 'Bangladesh VAT/TDS (Mushak 6.3 / 9.1, TDS certificates)', 'unified CRM analytics'];

/* ---------- 6. roles ---------- */
export const ROLES = {
  'super admin': 'everything, every company', admin: 'company administration', accountant: 'accounts, journals, reports, payment schedules',
  agent: 'sales / travel agent', vendor: 'vendor portal', customer: 'customer portal', employee: 'self-service: attendance, leave, tasks, requests, chat',
};

/* ---------- 7. glossary (concept answers) ---------- */
const G = (speak, detail) => ({ speak, detail: detail || [] });
export const GLOSSARY = [
  { re: /what('?s| is)? (a |the )?(general )?ledger\b|\bledger\b.*(mean|what)/i, a: G('The general ledger is every posted journal line, grouped by account. In the ERP that is journal_items joined to journal_entries — each line is a debit or a credit against one account, and an account’s ledger is its running history and balance.', ['Report: General Ledger / Account Ledger', 'Two side ledgers exist too — customer/supplier running balances and the employee ledger — but the GL is the truth']) },
  { re: /what('?s| is)? (a |the )?journal( entry)?\b|journal.*(mean|what is)/i, a: G('A journal entry is one balanced business event: a header (date, reference, source, company) and two or more lines whose debits equal credits. Every sale, purchase, expense approval, salary run and payment becomes one.', ['Sources: sale, purchase, expense, salary, employee_ledger, ticket_sale, visa_sale, financing', 'Wrong entry? Reverse it — the ERP never edits posted entries']) },
  { re: /trial balance/i, a: G('The trial balance lists every account with its total debits and credits for a period; the two columns must agree. It is the first proof that the books balance and the base for the P&L and balance sheet.', ['If a company’s trial balance is off, the usual cause is a company-owned account posted to by another company — shared accounts must have company_id NULL']) },
  { re: /profit (and|&) loss|\bp\s*&\s*l\b|income statement/i, a: G('The profit and loss statement is income (4xxx) minus direct cost (5xxx) minus operating expenses (6xxx–7xxx) minus finance cost (8xxx) over a period. The company dashboard’s quick version is sales − purchases − rent − other expenses − salary paid.', []) },
  { re: /balance sheet/i, a: G('The balance sheet is what the company owns and owes at a date: assets (1xxx) = liabilities (2xxx) + equity (3xxx). Cash and bank, receivables, inventory and staff loans on one side; payables, salaries payable, taxes payable and borrowings on the other, with owner investment, drawings and retained earnings.', []) },
  { re: /chart of accounts|account codes?|gl codes?/i, a: G('The chart of accounts is a coded tree: 1xxx assets, 2xxx liabilities, 3xxx equity, 4xxx income, 5xxx direct cost, 6xxx–7xxx operating expenses, 8xxx finance. Ask me any code — “what is 2210?” — and I will explain it.', COA_RANGES.map((r) => `${r.from}–${r.to} ${r.group}`)) },
  { re: /debit|credit/i, a: G('Debits increase assets and expenses; credits increase liabilities, equity and income. Every entry has equal debits and credits — that is what keeps the trial balance balanced.', ['Assets & expenses: normal debit balance', 'Liabilities, equity & income: normal credit balance']) },
  { re: /accounts? receivable|\bAR\b|receivables?/i, a: G('Receivables are money customers owe you — 1311 Customer Receivable in the chart, tracked day by day in payment schedules of type “receive”. Overdue ones are the first thing I look at each morning.', []) },
  { re: /accounts? payable|\bAP\b|payables?/i, a: G('Payables are what you owe suppliers, vendors and staff — 2111 Supplier Payable, 2210 Salaries Payable, 2240 reimbursements — scheduled as payment schedules of type “pay”.', []) },
  { re: /aging|ageing/i, a: G('An aging report buckets open receivables or payables by how long they have been due — current, 1–30, 31–60, 61–90, 90+ days. The ERP does not print one yet; I compute it from the payment schedules.', []) },
  { re: /payment schedule/i, a: G('A payment schedule is the ERP’s dues diary: every receivable or payable with a party, an amount, a scheduled date, a priority and a status (pending, paid, overdue, cancelled). It can be approved, rejected, rescheduled (logged) and marked paid.', WORKFLOWS.paymentSchedule) },
  { re: /petty cash/i, a: G('Petty cash lives in 1011 (the pool) and per-custodian floats with a limit; issues and returns are logged and float expenses post against the float account, with anything over the float booked as a reimbursement payable to the custodian.', []) },
  { re: /opening balance/i, a: G(POSTING_RULES.opening.speak) },
  { re: /how (is|are) (an? )?expenses? (posted|booked|recorded)|expense (posting|approval|workflow)/i, a: G(POSTING_RULES.expense.speak, POSTING_RULES.expense.detail) },
  { re: /how (is|are) (the )?salar(y|ies) (posted|booked)|salary journal|payroll (posting|journal)/i, a: G(POSTING_RULES.salary.speak, POSTING_RULES.salary.detail) },
  { re: /how (is|are) (a )?sales? (posted|booked)|sale journal/i, a: G(POSTING_RULES.sale.speak, POSTING_RULES.sale.detail) },
  { re: /how (is|are) (a )?purchases? (posted|booked)|purchase journal/i, a: G(POSTING_RULES.purchase.speak, POSTING_RULES.purchase.detail) },
  { re: /revers(e|al)/i, a: G(POSTING_RULES.reversal.speak) },
  { re: /shared account|company_id (null|is null)|cross[- ]company/i, a: G(POSTING_RULES.shared_accounts.speak) },
  { re: /how (is|are) (the )?(payroll|salary|net salary) (computed|calculated|worked out)|payroll formula|salary calculation/i, a: G('Payroll: ' + PAYROLL_RULES.daily + ' ' + PAYROLL_RULES.net, [PAYROLL_RULES.base, PAYROLL_RULES.absent, PAYROLL_RULES.late, PAYROLL_RULES.early, PAYROLL_RULES.overtime, PAYROLL_RULES.loans, PAYROLL_RULES.tax]) },
  { re: /late (deduction|penalt|rule)|deduct.*late|late.*deduct/i, a: G(PAYROLL_RULES.late, [PAYROLL_RULES.daily]) },
  { re: /early (out|leave|exit)/i, a: G(PAYROLL_RULES.early) },
  { re: /overtime/i, a: G(PAYROLL_RULES.overtime) },
  { re: /leave balance|how many leaves?|leave entitlement/i, a: G(PAYROLL_RULES.leaveBalance) },
  { re: /(income )?tax|provident|\bpf\b|gratuity/i, a: G(PAYROLL_RULES.tax, ['Roadmap: VAT Act 2012 (Mushak 6.3 invoice, 9.1 return), TDS under the Income Tax Act 2023, payroll tax, NBR e-filing']) },
  { re: /attendance (rule|status|source)|manual attendance|selfie/i, a: G(PAYROLL_RULES.attendance, [PAYROLL_RULES.presence]) },
  { re: /who is online|online (now|status)|presence/i, a: G(PAYROLL_RULES.presence) },
  { re: /(what|which) reports?/i, a: G('The ERP prints these reports: ' + REPORTS.join(', ') + '.', ['Not yet: ' + REPORT_GAPS.join(', ') + ' — I compute aging and cash position myself.']) },
  { re: /roles?|permissions?|who can (approve|see|edit)/i, a: G('Roles: super admin, admin, accountant, agent, vendor, customer, employee. Permissions are view/create/edit/delete per module plus workflow rights like approve expense, approve leave, approve payment schedule, disburse employee request. Roles are developer-maintained — never editable from a dashboard.', Object.entries(ROLES).map(([k, v]) => `${k}: ${v}`)) },
  { re: /how many compan|which compan|companies (do|are)/i, a: G('Twelve companies share the ERP — among them Epal Travels & Consultancy, Epal It Solutions, Epal Constructions & Interiors, Epal Group, Epal Manufacturing, Epal Online Shop, Epal Properties and Wood Art Interiors. Each has its own dashboard; I read them all.', []) },
  { re: /wood ?art/i, a: G('Wood Art Interiors (company 6) runs an isolated interior-design module — clients, projects, estimates (BOQ), spaces, phases, drawings, materials, vendors, purchases, production and installation — kept apart from the other companies by design.', []) },
  { re: /lead (status|stage|pipeline)|sales pipeline|crm (flow|stages)/i, a: G('Leads move new → contacted → qualified → proposal sent → negotiation → won or lost, typed as air ticket, visa, software, interior or other, each with follow-ups, reminders and a status history; won leads become deals, proposals, estimates, contracts — and interior wins convert straight into projects.', []) },
  { re: /task (flow|board|column|workflow)|kanban/i, a: G('Tasks live on boards inside a workspace (one per company): columns are the stages, tasks carry priority, dates, assignees, comments and an activity log; office to-dos are the lighter per-department checklist.', []) },
  { re: /employee request|require assignment/i, a: G('Employee requests are money or item requests: pending → under review → approved/rejected → fulfilled or disbursed (cash, bank, cheque or payroll deduction) → recovered through payslip instalments → closed. Requires are assigned with due dates and can be escalated.', []) },
  { re: /what can you do|help|your (abilities|skills)|how can you help/i, a: G('I am the one brain over the Epal ERP. Ask me by voice or text: cash position, receivables and payables, overdue dues, trial balance, profit this month, expenses against budget, who is absent or late today, payroll cost, leave balances, loans, the sales pipeline, stale leads, overdue tasks, project risk, an employee’s evaluation, or any concept — “what is 2210”, “how is late deduction calculated”. I also give you a ranked morning brief and an approvals queue.', ['Say “brief” for the morning briefing', 'Say “approvals” for what is waiting on you', 'Say “explain <code>” for any GL code']) },
];

export function explain(q) {
  const s = String(q || '');
  const m = s.match(/\b(\d{4})\b/);
  if (m && /code|account|gl|what is|what'?s|explain|mean/i.test(s)) { const e = explainCode(m[1]); if (e && (e.name || e.group)) return { speak: e.speak, detail: e.detail }; }
  for (const g of GLOSSARY) { if (g.re.test(s)) return g.a; }
  return null;
}

/* ---------- 8. compact system context for a language model ---------- */
export function systemContext() {
  const lines = [];
  lines.push('EPAL ERP — Laravel 12 / MySQL, twelve companies in one database, URLs /{role}/{resource}. Roles: ' + Object.keys(ROLES).join(', ') + '.');
  lines.push('CHART OF ACCOUNTS: ' + COA_RANGES.map((r) => `${r.from}-${r.to} ${r.group} (${r.type})`).join('; ') + '. Leaves: ' + Object.entries(COA_LEAVES).map(([c, n]) => `${c} ${n}`).join(', ') + '.');
  lines.push('GL: journal_entries (company_id, date, reference, source, description) + journal_items (account_id, debit, credit, party_type, party_id). Corrections by reversal. Shared accounts have company_id NULL.');
  Object.values(POSTING_RULES).forEach((p) => lines.push(p.title.toUpperCase() + ': ' + p.speak));
  lines.push('PAYROLL: ' + Object.values(PAYROLL_RULES).join(' '));
  lines.push('WORKFLOWS: ' + Object.entries(WORKFLOWS).map(([k, v]) => `${k}: ${v.join(' → ')}`).join(' | '));
  lines.push('REPORTS: ' + REPORTS.join(', ') + '. Gaps: ' + REPORT_GAPS.join(', ') + '.');
  lines.push('CRM: leads new→contacted→qualified→proposal_sent→negotiation→won/lost; types air_ticket, visa, software, interior, other; deals, proposals, estimates, contracts, projects (not_started, in_progress, completed, on_hold, cancelled).');
  lines.push('TASKS: workspace = company; boards → columns → tasks (priority low/medium/high, due_date, assignees); office_todos per department. Monitoring is attendance + presence (online = seen within 5 min).');
  lines.push('CURRENCY: Bangladeshi Taka (BDT, ৳). Dates ISO.');
  return lines.join('\n');
}

export const EonErpKnowledge = { COA_RANGES, COA_LEAVES, POSTING_RULES, PAYROLL_RULES, WORKFLOWS, REPORTS, REPORT_GAPS, ROLES, GLOSSARY, explain, explainCode, accountTypeOf, normalBalance, systemContext };
if (typeof window !== 'undefined') window.EonErpKnowledge = Object.assign(window.EonErpKnowledge || {}, EonErpKnowledge);
export default EonErpKnowledge;
