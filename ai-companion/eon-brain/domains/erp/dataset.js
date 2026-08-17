/* ============================================================
   EON · ERP dataset — the one shape EON reads, whoever fills it.

   Filled by
     • server/api/dataset.php   (live MySQL, read-only user)   or
     • demo-data.js             (deterministic synthetic mirror)

   Every array is flat, every record has an id, dates are ISO
   YYYY-MM-DD, money is BDT numbers. Column names follow the ERP
   tables (docs/erp-domain-map.md) so a query maps 1:1.
   ============================================================ */

export const DATASET_SHAPE = {
  meta: { source: 'demo|erp', generated_at: 'ISO', currency: 'BDT', today: 'YYYY-MM-DD', company_id: 'number|null' },
  companies:         ['id', 'name', 'short_name', 'status'],
  accounts:          ['id', 'code', 'name', 'type', 'parent_id', 'opening_balance', 'company_id'],
  journal_entries:   ['id', 'company_id', 'date', 'reference', 'source', 'description', 'items[]{account_id,account_code,debit,credit,party_type,party_id,note}'],
  banks:             ['id', 'company_id', 'name', 'type', 'account_id', 'account_code', 'balance'],
  payment_schedules: ['id', 'company_id', 'type(receive|pay)', 'party_type', 'party_id', 'party_name', 'source_label', 'amount', 'paid_amount', 'scheduled_date', 'original_scheduled_date', 'reschedule_count', 'status(pending|paid|overdue|cancelled)', 'priority', 'paid_date'],
  expenses:          ['id', 'company_id', 'title', 'amount', 'expense_date', 'category', 'category_id', 'account_code', 'department', 'payment_mode', 'approval_status', 'user_id', 'user_name', 'bank_id'],
  expense_budgets:   ['id', 'company_id', 'category', 'category_id', 'period', 'amount', 'threshold'],
  departments:       ['id', 'name'],
  designations:      ['id', 'name'],
  employees:         ['id', 'name', 'email', 'phone', 'company_id', 'department_id', 'department', 'designation', 'joining_date', 'salary', 'employment_type', 'status', 'shift_start', 'shift_end', 'overtime_eligible', 'last_seen_at', 'role'],
  attendances:       ['id', 'user_id', 'company_id', 'date', 'check_in', 'check_out', 'status(present|absent|leave|holiday)', 'source', 'late_minutes', 'early_minutes', 'overtime_minutes'],
  leave_types:       ['id', 'name', 'max_leaves_count'],
  leaves:            ['id', 'user_id', 'company_id', 'leave_type', 'start_date', 'end_date', 'days', 'status', 'reason', 'applied_at'],
  holidays:          ['id', 'name', 'start_date', 'end_date'],
  payroll:           ['id', 'user_id', 'company_id', 'month', 'year', 'gross_salary', 'absent_deduction', 'leave_deduction', 'late_deduction', 'early_leave_deduction', 'loan_deduction', 'advance_salary_deduction', 'overtime_salary', 'total_deductions', 'net_salary', 'status(Pending|Paid)', 'payment_date'],
  loans:             ['id', 'user_id', 'amount', 'remaining_amount', 'monthly_deduction', 'status(Running|Completed)', 'start_date', 'end_date'],
  advance_salaries:  ['id', 'user_id', 'amount', 'month', 'status', 'payment_status'],
  employee_requests: ['id', 'user_id', 'category', 'request_type', 'amount', 'status', 'deadline', 'created_at'],
  customers:         ['id', 'company_id', 'name', 'phone', 'type'],
  suppliers:         ['id', 'company_id', 'name', 'phone'],
  leads:             ['id', 'company_id', 'name', 'phone', 'lead_type', 'source', 'status', 'assigned_to', 'value', 'created_at', 'last_followup_at', 'next_followup_at'],
  deals:             ['id', 'company_id', 'lead_id', 'title', 'stage', 'amount', 'closing_date', 'status(open|won|lost)', 'agent_id'],
  projects:          ['id', 'company_id', 'project_name', 'customer_id', 'status', 'start_date', 'end_date', 'budget', 'spent', 'progress', 'manager_id', 'team[]'],
  tasks:             ['id', 'company_id', 'project_id', 'title', 'priority', 'status(todo|in_progress|review|done)', 'assigned_to[]', 'created_by', 'start_date', 'due_date', 'completed_at', 'label'],
  office_todos:      ['id', 'company_id', 'title', 'department', 'priority', 'status', 'due_date', 'assignees[]'],
  sales:             ['id', 'company_id', 'invoice_no', 'customer_id', 'date', 'total', 'paid_amount', 'due_amount', 'payment_status', 'due_date'],
  purchases:         ['id', 'company_id', 'supplier_id', 'date', 'total', 'paid_amount', 'due_amount', 'payment_status', 'due_date'],
  notices:           ['id', 'company_id', 'title', 'published_at', 'expires_at'],
};

export function emptyDataset() {
  const d = {};
  Object.keys(DATASET_SHAPE).forEach((k) => { d[k] = k === 'meta' ? {} : []; });
  return d;
}

/* ---------- small shared helpers (dates, money) ---------- */
export const iso = (d) => { const x = d instanceof Date ? d : new Date(d); return new Date(x.getTime() - x.getTimezoneOffset() * 60000).toISOString().slice(0, 10); };
export const addDays = (d, n) => { const x = new Date(d instanceof Date ? d.getTime() : Date.parse(d)); x.setDate(x.getDate() + n); return x; };
export const daysBetween = (a, b) => Math.round((Date.parse(iso(b)) - Date.parse(iso(a))) / 86400000);
export const monthKey = (d) => iso(d).slice(0, 7);
export const startOfMonth = (d) => { const x = new Date(d instanceof Date ? d.getTime() : Date.parse(d)); x.setDate(1); return x; };
export const daysInMonth = (d) => { const x = new Date(d instanceof Date ? d.getTime() : Date.parse(d)); return new Date(x.getFullYear(), x.getMonth() + 1, 0).getDate(); };
export const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

export function fmtBDT(n) {
  n = Math.round(+n || 0);
  const neg = n < 0; n = Math.abs(n);
  // Bangladeshi grouping: 12,34,56,789
  let s = String(n);
  if (s.length > 3) { const last3 = s.slice(-3); let rest = s.slice(0, -3); rest = rest.replace(/\B(?=(\d{2})+(?!\d))/g, ','); s = rest + ',' + last3; }
  return (neg ? '−' : '') + '৳' + s;
}
export function fmtBDTk(n) {
  const a = Math.abs(+n || 0), sign = (+n || 0) < 0 ? '−' : '';
  if (a >= 1e7) return sign + '৳' + (a / 1e7).toFixed(a >= 1e8 ? 0 : 1).replace(/\.0$/, '') + ' Cr';
  if (a >= 1e5) return sign + '৳' + (a / 1e5).toFixed(a >= 1e6 ? 0 : 1).replace(/\.0$/, '') + ' L';
  if (a >= 1e3) return sign + '৳' + (a / 1e3).toFixed(1).replace(/\.0$/, '') + 'k';
  return fmtBDT(n);
}
export const pct = (a, b) => (b ? Math.round((a / b) * 100) : 0);

/* Index helpers used by every decision layer */
export function indexBy(arr, key = 'id') { const m = new Map(); (arr || []).forEach((r) => m.set(r[key], r)); return m; }
export function groupBy(arr, fn) { const m = new Map(); (arr || []).forEach((r) => { const k = typeof fn === 'function' ? fn(r) : r[fn]; if (!m.has(k)) m.set(k, []); m.get(k).push(r); }); return m; }
export const sum = (arr, fn) => (arr || []).reduce((n, r) => n + (+(typeof fn === 'function' ? fn(r) : r[fn]) || 0), 0);

if (typeof window !== 'undefined') {
  window.fmtBDT = window.fmtBDT || fmtBDT;
  window.fmtBDTk = window.fmtBDTk || fmtBDTk;
}
export default { DATASET_SHAPE, emptyDataset, iso, addDays, daysBetween, monthKey, startOfMonth, daysInMonth, MONTHS, fmtBDT, fmtBDTk, pct, indexBy, groupBy, sum };
