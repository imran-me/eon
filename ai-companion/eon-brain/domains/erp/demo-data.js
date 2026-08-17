/* ============================================================
   EON · ERP demo dataset — a deterministic, schema-faithful
   mirror of the Epal ERP so EON is fully demonstrable with no
   server: eight companies, chart of accounts on the live
   numbering, six months of balanced journal entries, banks,
   receivables/payables diary, expenses with budgets, ~70 staff
   with attendance, leaves, payroll computed by the ERP’s own
   formulas, loans, CRM pipeline, projects, tasks and to-dos.

   Everything is generated relative to *today* from a fixed seed,
   so the numbers are stable within a day and always “live”.
   ============================================================ */
import { iso, addDays, daysInMonth, monthKey, MONTHS } from './dataset.js';
import { COA_LEAVES } from './knowledge.js';

function mulberry32(a) { return function () { a |= 0; a = a + 0x6D2B79F5 | 0; let t = Math.imul(a ^ a >>> 15, 1 | a); t = t + Math.imul(t ^ t >>> 7, 61 | t) ^ t; return ((t ^ t >>> 14) >>> 0) / 4294967296; }; }

const FIRST = ['Md Imran', 'Afiqur', 'Rafi', 'Sadia', 'Tanvir', 'Nusrat', 'Mahmud', 'Farhana', 'Rakib', 'Sumaiya', 'Arif', 'Jannat', 'Shakil', 'Mitu', 'Hasan', 'Tasnim', 'Rezaul', 'Priya', 'Nayeem', 'Fahim', 'Sabbir', 'Nabila', 'Rifat', 'Mehedi', 'Ayesha', 'Zahid', 'Sharmin', 'Kamrul', 'Lamia', 'Tanjil', 'Ruhul', 'Ishrat', 'Sohel', 'Rumana', 'Abir', 'Maisha', 'Jubayer', 'Anika', 'Sajid', 'Faria', 'Emon', 'Nadia', 'Rasel', 'Shanta', 'Tarek', 'Momo', 'Nahid', 'Bithi', 'Asif', 'Rima', 'Mizan', 'Tuli', 'Sakib', 'Zara', 'Ripon', 'Nishat', 'Rony', 'Sumi', 'Habib', 'Orin', 'Kawsar', 'Tania', 'Alamin', 'Purnima', 'Jamil', 'Sinthia', 'Mamun', 'Ritu', 'Shuvo', 'Ela', 'Ovi', 'Meem'];
const LAST = ['Hossain', 'Rahman', 'Islam', 'Ahmed', 'Khan', 'Chowdhury', 'Akter', 'Uddin', 'Karim', 'Hasan', 'Sultana', 'Begum', 'Ali', 'Mia', 'Sarker', 'Bhuiyan', 'Talukder', 'Siddique', 'Haque', 'Mahmud'];
const CUSTOMER_NAMES = ['Nova Textiles Ltd', 'Green Delta Traders', 'Meghna Foods', 'Rahimafrooz Distribution', 'City Hospital Dhaka', 'Uttara University', 'Padma Ceramics', 'Bengal Agro', 'Square Pharma (Uttara)', 'Akij Motors', 'Beximco Retail', 'Bashundhara Housing', 'Aarong Gulshan', 'Pran Dairy', 'Grameen Telecom Trust', 'Summit Power', 'BRAC Enterprises', 'Runner Auto', 'Walton Plaza', 'ACI Logistics', 'Ha-Meem Group', 'DBL Ceramics', 'Envoy Textiles', 'Fair Electronics', 'Ispahani Tea', 'Labaid Diagnostics', 'Navana Furniture', 'Otobi Interiors', 'Partex Star', 'RFL Plastics', 'Shwapno', 'Transcom Beverages', 'United Hospital', 'Viyellatex', 'Yellow by Beximco', 'Zaman Traders', 'Daffodil Int. University', 'Rangs Properties', 'Doreen Hotel', 'Sena Kalyan Sangstha'];
const SUPPLIER_NAMES = ['Biman Bangladesh Airlines', 'US-Bangla Airlines', 'Emirates GSA Dhaka', 'Qatar Airways GSA', 'Hatil Timber Supply', 'Akij Board Mills', 'Bengal Plywood', 'Berger Paints', 'BSRM Steel', 'Crown Cement', 'Amazon Web Services', 'Hostinger', 'Google Workspace', 'Grameenphone Business', 'Link3 Technologies', 'Dell Bangladesh (Smart Tech)', 'Rangs Motors', 'Ryans Computers', 'Star Tech', 'Meena Bazar Corporate'];

const COMPANIES = [
  { id: 1, name: 'Epal Group', short_name: 'GROUP', kind: 'holding', rev: 0 },
  { id: 2, name: 'Epal Travels & Consultancy', short_name: 'TRAVELS', kind: 'travel', rev: 4200000 },
  { id: 3, name: 'Epal It Solutions', short_name: 'EPAL IT', kind: 'software', rev: 2600000 },
  { id: 4, name: 'Epal Constructions & Interiors', short_name: 'ECI', kind: 'construction', rev: 3100000 },
  { id: 5, name: 'Epal Online Shop', short_name: 'SHOP', kind: 'ecommerce', rev: 1500000 },
  { id: 6, name: 'Wood Art Interiors', short_name: 'WOODART', kind: 'interior', rev: 1900000 },
  { id: 7, name: 'Epal Manufacturing', short_name: 'MFG', kind: 'manufacturing', rev: 2200000 },
  { id: 8, name: 'Epal Properties', short_name: 'PROPERTIES', kind: 'realestate', rev: 1200000 },
];
const DEPARTMENTS = ['Management', 'Accounts & Finance', 'HR & Admin', 'Sales & Marketing', 'Operations', 'Software Engineering', 'Design', 'Production', 'Customer Support', 'Logistics'];
const DESIG = [
  ['Managing Director', 250000, 'Management'], ['Chief Operating Officer', 180000, 'Management'], ['Head of Accounts', 95000, 'Accounts & Finance'], ['Senior Accountant', 55000, 'Accounts & Finance'], ['Accountant', 38000, 'Accounts & Finance'],
  ['HR Manager', 70000, 'HR & Admin'], ['HR Executive', 32000, 'HR & Admin'], ['Admin Officer', 30000, 'HR & Admin'], ['Sales Manager', 75000, 'Sales & Marketing'], ['Senior Sales Executive', 45000, 'Sales & Marketing'], ['Sales Executive', 30000, 'Sales & Marketing'], ['Digital Marketer', 35000, 'Sales & Marketing'],
  ['Operations Manager', 72000, 'Operations'], ['Visa Officer', 34000, 'Operations'], ['Ticketing Officer', 32000, 'Operations'], ['Project Manager', 85000, 'Software Engineering'], ['Senior Software Engineer', 90000, 'Software Engineering'], ['Software Engineer', 55000, 'Software Engineering'], ['QA Engineer', 45000, 'Software Engineering'],
  ['Lead Designer', 65000, 'Design'], ['Interior Designer', 48000, 'Design'], ['Production Supervisor', 42000, 'Production'], ['Machine Operator', 22000, 'Production'], ['Carpenter', 24000, 'Production'], ['Support Executive', 26000, 'Customer Support'], ['Delivery Coordinator', 25000, 'Logistics'], ['Driver', 20000, 'Logistics'], ['Office Assistant', 16000, 'HR & Admin'],
];
const EXPENSE_CATS = [
  ['Office Rent', '7110', 'monthly', 180000], ['Utilities', '7120', 'monthly', 45000], ['Internet & Telephone', '7130', 'monthly', 22000], ['Office Supplies', '7140', 'monthly', 18000], ['Travel & Conveyance', '7150', 'monthly', 40000],
  ['Marketing & Advertising', '7160', 'monthly', 90000], ['Repairs & Maintenance', '7170', 'monthly', 25000], ['Entertainment', '7180', 'monthly', 20000], ['Software Subscriptions', '7190', 'monthly', 35000], ['Fuel & Vehicle', '7200', 'monthly', 38000], ['Professional Fees', '7210', 'monthly', 30000], ['Miscellaneous', '7400', 'monthly', 15000],
];
const LEAD_TYPES = ['air_ticket', 'visa', 'software', 'interior', 'other'];
const LEAD_STATUS = ['new', 'contacted', 'qualified', 'proposal_sent', 'negotiation', 'won', 'lost'];
const SOURCES = ['Facebook', 'Website', 'Referral', 'Walk-in', 'LinkedIn', 'Phone'];

export function generateDemo(opts = {}) {
  const rnd = mulberry32(opts.seed || 20260818);
  const R = (a, b) => a + rnd() * (b - a);
  const RI = (a, b) => Math.floor(R(a, b + 1));
  const pick = (arr) => arr[Math.floor(rnd() * arr.length)];
  const chance = (p) => rnd() < p;
  const today = opts.today ? new Date(opts.today) : new Date();
  const T = iso(today);
  const D = {
    meta: { source: 'demo', generated_at: new Date().toISOString(), currency: 'BDT', today: T, company_id: null, boss: { id: 1, name: 'Md Imran Hossain', title: 'Managing Director', company_id: 1 } },
    companies: COMPANIES.map(({ id, name, short_name }) => ({ id, name, short_name, status: 'active' })),
    accounts: [], journal_entries: [], banks: [], payment_schedules: [], expenses: [], expense_budgets: [],
    departments: DEPARTMENTS.map((n, i) => ({ id: i + 1, name: n })), designations: DESIG.map((d, i) => ({ id: i + 1, name: d[0] })),
    employees: [], attendances: [], leave_types: [{ id: 1, name: 'Casual', max_leaves_count: 10 }, { id: 2, name: 'Sick', max_leaves_count: 14 }, { id: 3, name: 'Earned', max_leaves_count: 16 }, { id: 4, name: 'Maternity', max_leaves_count: 112 }],
    leaves: [], holidays: [], payroll: [], loans: [], advance_salaries: [], employee_requests: [], customers: [], suppliers: [], leads: [], deals: [], projects: [], tasks: [], office_todos: [], sales: [], purchases: [], notices: [],
  };
  let ids = { acc: 1, je: 1, bank: 1, ps: 1, exp: 1, bud: 1, emp: 1, att: 1, lv: 1, hol: 1, pay: 1, loan: 1, adv: 1, req: 1, cust: 1, sup: 1, lead: 1, deal: 1, proj: 1, task: 1, todo: 1, sale: 1, pur: 1, notice: 1 };

  /* ---------- chart of accounts ---------- */
  const acc = (code, name, type, company_id = null) => { const a = { id: ids.acc++, code, name, type, parent_id: null, opening_balance: 0, company_id }; D.accounts.push(a); return a; };
  const typeOf = (c) => ({ 1: 'asset', 2: 'liability', 3: 'equity', 4: 'income', 5: 'expense', 6: 'expense', 7: 'expense', 8: 'expense' })[String(c)[0]];
  Object.entries(COA_LEAVES).forEach(([code, name]) => acc(code, name.replace(/ \(.*\)$/, ''), typeOf(code)));
  EXPENSE_CATS.forEach(([name, code]) => { if (!D.accounts.find((a) => a.code === code)) acc(code, name, 'expense'); });
  acc('4130', 'Software & IT Services Income', 'income'); acc('4140', 'Interior & Construction Revenue', 'income'); acc('4170', 'Rental Income', 'income'); acc('4180', 'Manufacturing Sales', 'income');
  acc('5130', 'Materials & Subcontract Cost', 'expense'); acc('5140', 'Manufacturing Direct Cost', 'expense'); acc('6120', 'Festival Bonus', 'expense'); acc('6130', 'Overtime Expense', 'expense');
  const byCode = (c) => D.accounts.find((a) => a.code === c);

  /* ---------- banks (each with a 11xx leaf) ---------- */
  const BANKS = ['BRAC Bank – Current', 'City Bank – Current', 'Dutch-Bangla Bank – SND', 'Islami Bank – Current', 'bKash Merchant', 'Cash in Hand'];
  let bankCode = 1101;
  COMPANIES.forEach((c) => {
    const n = c.kind === 'holding' ? 3 : RI(2, 3);
    const names = [BANKS[(c.id - 1) % 4], BANKS[(c.id) % 4], BANKS[4], BANKS[5]].slice(0, n).concat(c.kind === 'holding' ? [] : []);
    names.forEach((nm) => {
      const code = String(bankCode++);
      const a = acc(code, `${nm} · ${c.short_name}`, 'asset', c.id);
      D.banks.push({ id: ids.bank++, company_id: c.id, name: nm, type: /bKash/.test(nm) ? 'mobile_banking' : /Cash/.test(nm) ? 'cash' : 'bank', account_id: a.id, account_code: code, balance: 0 });
    });
  });

  /* ---------- people ---------- */
  const usedNames = new Set();
  const mkName = () => { for (let i = 0; i < 50; i++) { const n = pick(FIRST) + ' ' + pick(LAST); if (!usedNames.has(n)) { usedNames.add(n); return n; } } return pick(FIRST) + ' ' + pick(LAST) + ' ' + RI(1, 99); };
  const emp = (o) => { const e = Object.assign({ id: ids.emp++, email: '', phone: '01' + RI(3, 9) + String(RI(10000000, 99999999)), employment_type: chance(0.85) ? 'full_time' : (chance(0.5) ? 'part_time' : 'contractual'), status: 'active', shift_start: '09:00', shift_end: '18:00', overtime_eligible: chance(0.35), role: 'employee', traits: {} }, o); e.email = e.email || (e.name.toLowerCase().replace(/[^a-z]+/g, '.') + '@epal.com.bd'); D.employees.push(e); return e; };
  const desig = (name) => DESIG.find((d) => d[0] === name);
  const deptId = (n) => DEPARTMENTS.indexOf(n) + 1;
  const boss = emp({ name: 'Md Imran Hossain', email: 'imran@epal.com.bd', company_id: 1, department: 'Management', department_id: 1, designation: 'Managing Director', joining_date: '2019-01-01', salary: 250000, role: 'super admin', overtime_eligible: false });
  emp({ name: 'Afiqur Rahman', company_id: 3, department: 'Software Engineering', department_id: deptId('Software Engineering'), designation: 'Project Manager', joining_date: '2022-03-01', salary: 85000, role: 'admin' });
  emp({ name: 'Sadia Sultana', company_id: 1, department: 'Accounts & Finance', department_id: deptId('Accounts & Finance'), designation: 'Head of Accounts', joining_date: '2020-07-15', salary: 95000, role: 'accountant' });
  emp({ name: 'Tanvir Ahmed', company_id: 1, department: 'HR & Admin', department_id: deptId('HR & Admin'), designation: 'HR Manager', joining_date: '2021-02-01', salary: 70000, role: 'admin' });
  const plan = { 2: 14, 3: 12, 4: 9, 5: 7, 6: 8, 7: 10, 8: 5, 1: 4 };
  const deptFor = { travel: ['Operations', 'Sales & Marketing', 'Accounts & Finance', 'Customer Support'], software: ['Software Engineering', 'Software Engineering', 'Design', 'Sales & Marketing'], construction: ['Operations', 'Design', 'Production', 'Sales & Marketing'], ecommerce: ['Sales & Marketing', 'Customer Support', 'Logistics', 'Operations'], interior: ['Design', 'Production', 'Sales & Marketing', 'Operations'], manufacturing: ['Production', 'Production', 'Logistics', 'Operations'], realestate: ['Sales & Marketing', 'Operations', 'Accounts & Finance'], holding: ['Accounts & Finance', 'HR & Admin', 'Management'] };
  COMPANIES.forEach((c) => {
    for (let i = 0; i < plan[c.id]; i++) {
      const dept = pick(deptFor[c.kind]);
      const cands = DESIG.filter((d) => d[2] === dept && d[1] < 100000);
      const dg = pick(cands.length ? cands : DESIG.filter((d) => d[1] < 60000));
      const jd = addDays(today, -RI(60, 2400));
      const e = emp({ name: mkName(), company_id: c.id, department: dept, department_id: deptId(dept), designation: dg[0], joining_date: iso(jd), salary: Math.round(dg[1] * R(0.9, 1.15) / 500) * 500 });
      // behavioural traits drive attendance realism
      e.traits = { late: chance(0.18) ? R(0.35, 0.7) : R(0.02, 0.12), absent: chance(0.12) ? R(0.08, 0.16) : R(0.0, 0.04), ot: e.overtime_eligible ? R(0.2, 0.6) : 0, early: chance(0.1) ? R(0.2, 0.4) : R(0, 0.05), perf: R(0.45, 0.98) };
      if (chance(0.5)) { e.shift_start = '10:00'; e.shift_end = '19:00'; }
    }
  });
  D.employees.forEach((e) => { e.traits = e.traits && Object.keys(e.traits).length ? e.traits : { late: R(0.02, 0.1), absent: R(0, 0.03), ot: 0, early: 0.02, perf: R(0.75, 0.98) }; e.last_seen_at = null; });
  const empsOf = (cid) => D.employees.filter((e) => e.company_id === cid);

  /* ---------- holidays (BD flavour, relative) ---------- */
  const y = today.getFullYear();
  [[`${y}-02-21`, 'Shaheed Dibosh'], [`${y}-03-26`, 'Independence Day'], [`${y}-04-14`, 'Pohela Boishakh'], [`${y}-05-01`, 'May Day'], [`${y}-08-15`, 'National Mourning Day'], [`${y}-12-16`, 'Victory Day']].forEach(([d, n]) => D.holidays.push({ id: ids.hol++, name: n, start_date: d, end_date: d }));
  const eidStart = iso(addDays(today, -RI(20, 70))); D.holidays.push({ id: ids.hol++, name: 'Eid holidays', start_date: eidStart, end_date: iso(addDays(eidStart, 3)) });
  const isHoliday = (d) => D.holidays.some((h) => d >= h.start_date && d <= h.end_date);
  const isWeekend = (d) => { const w = new Date(d + 'T00:00:00').getDay(); return w === 5 || w === 6; };  // Fri, Sat
  const workday = (d) => !isWeekend(d) && !isHoliday(d);

  /* ---------- attendance: last 75 days ---------- */
  const t2m = (t) => { const [h, m] = t.split(':').map(Number); return h * 60 + m; };
  const m2t = (m) => `${String(Math.floor(m / 60)).padStart(2, '0')}:${String(m % 60).padStart(2, '0')}`;
  // before 09:30 the working day has not begun — show today as a completed day (demo stays legible at any hour)
  const nowMin0 = today.getHours() * 60 + today.getMinutes();
  const nowMin = nowMin0 < 570 ? 24 * 60 : nowMin0;
  for (let back = 75; back >= 0; back--) {
    const d = iso(addDays(today, -back));
    if (!workday(d)) continue;
    D.employees.forEach((e) => {
      if (e.joining_date > d) return;
      const tr = e.traits;
      const onLeave = D.leaves.some((l) => l.user_id === e.id && l.status === 'approved' && d >= l.start_date && d <= l.end_date);
      let status = onLeave ? 'leave' : chance(tr.absent) ? 'absent' : 'present';
      const row = { id: ids.att++, user_id: e.id, company_id: e.company_id, date: d, check_in: null, check_out: null, status, source: chance(0.8) ? 'machine' : 'manual', late_minutes: 0, early_minutes: 0, overtime_minutes: 0 };
      if (status === 'present') {
        const ss = t2m(e.shift_start), se = t2m(e.shift_end);
        const late = chance(tr.late) ? RI(6, 75) : RI(-12, 4);
        const inM = ss + late; row.check_in = m2t(inM); row.late_minutes = late > 5 ? late : 0;
        if (back === 0 && nowMin < se) { /* today: still in the office */ if (inM > nowMin) { row.status = 'absent'; row.check_in = null; row.late_minutes = 0; if (chance(0.5)) { row.status = 'present'; row.check_in = m2t(ss + RI(0, 10)); } } }
        else {
          const early = chance(tr.early) ? RI(15, 90) : 0; const ot = tr.ot && chance(tr.ot) ? RI(60, 180) : RI(-8, 20);
          const outM = early ? se - early : se + ot; row.check_out = m2t(outM); row.early_minutes = early > 10 ? early : 0; row.overtime_minutes = ot >= 60 ? ot : 0;
        }
      }
      D.attendances.push(row);
    });
    // leaves: a few approved historically + pending ahead (generated once, at the earliest day)
  }
  // leaves (approved past, pending future)
  D.employees.forEach((e) => {
    if (chance(0.55)) { const s = addDays(today, -RI(5, 70)); const n = RI(1, 3); D.leaves.push({ id: ids.lv++, user_id: e.id, company_id: e.company_id, leave_type: pick(['Casual', 'Sick', 'Earned']), start_date: iso(s), end_date: iso(addDays(s, n - 1)), days: n, status: 'approved', reason: pick(['Family event', 'Fever', 'Personal work', 'Village visit', 'Medical check-up']), applied_at: iso(addDays(s, -RI(1, 6))) }); }
    if (chance(0.09)) { const s = addDays(today, RI(1, 12)); const n = RI(1, 4); D.leaves.push({ id: ids.lv++, user_id: e.id, company_id: e.company_id, leave_type: pick(['Casual', 'Sick', 'Earned']), start_date: iso(s), end_date: iso(addDays(s, n - 1)), days: n, status: 'pending', reason: pick(['Sister’s wedding', 'Medical treatment', 'Family emergency', 'Exam', 'Hometown visit']), applied_at: iso(addDays(today, -RI(0, 3))) }); }
  });
  // mark today's leave rows consistent
  D.attendances.filter((a) => a.date === T).forEach((a) => { if (D.leaves.some((l) => l.user_id === a.user_id && l.status === 'approved' && T >= l.start_date && T <= l.end_date)) { a.status = 'leave'; a.check_in = null; a.check_out = null; a.late_minutes = 0; } });
  // presence
  D.employees.forEach((e) => { const a = D.attendances.find((x) => x.user_id === e.id && x.date === T); e.last_seen_at = a && a.status === 'present' && chance(0.7) ? new Date(today.getTime() - RI(0, 240000)).toISOString() : (a && a.status === 'present' ? new Date(today.getTime() - RI(10, 90) * 60000).toISOString() : null); });

  /* ---------- loans, advances, requests ---------- */
  D.employees.filter((e) => e.id > 4).forEach((e) => {
    if (chance(0.09)) { const amt = Math.round(e.salary * R(1.5, 4) / 1000) * 1000; const emi = Math.round(amt / RI(6, 18) / 100) * 100; const paidM = RI(1, 8); D.loans.push({ id: ids.loan++, user_id: e.id, amount: amt, remaining_amount: Math.max(0, amt - emi * paidM), monthly_deduction: emi, status: 'Running', start_date: iso(addDays(today, -paidM * 30 - 10)), end_date: iso(addDays(today, Math.ceil((amt - emi * paidM) / emi) * 30)) }); }
    if (chance(0.05)) D.advance_salaries.push({ id: ids.adv++, user_id: e.id, amount: Math.round(e.salary * R(0.3, 0.6) / 500) * 500, month: monthKey(today), status: chance(0.5) ? 'Pending' : 'Approved', payment_status: 'Unpaid' });
    if (chance(0.06)) D.employee_requests.push({ id: ids.req++, user_id: e.id, category: chance(0.6) ? 'request' : 'require', request_type: pick(['Medical advance', 'Laptop', 'Travel allowance', 'Training fee', 'Uniform', 'Mobile handset']), amount: RI(3, 40) * 1000, status: pick(['pending', 'pending', 'under_review', 'approved']), deadline: iso(addDays(today, RI(-3, 14))), created_at: iso(addDays(today, -RI(1, 12))) });
  });

  /* ---------- payroll: last 3 closed months + current (pending) ---------- */
  const monthStats = (uid, mk) => { const rows = D.attendances.filter((a) => a.user_id === uid && a.date.slice(0, 7) === mk); return { absent: rows.filter((a) => a.status === 'absent').length, leave: rows.filter((a) => a.status === 'leave').length, late: rows.reduce((n, a) => n + a.late_minutes, 0), early: rows.reduce((n, a) => n + a.early_minutes, 0), ot: rows.reduce((n, a) => n + a.overtime_minutes, 0), present: rows.filter((a) => a.status === 'present').length }; };
  const PENDING_PAYROLL_COMPANIES = [4, 7];   // last month’s run still unpaid here (approval demo)
  for (let mb = 3; mb >= 1; mb--) {
    const md = new Date(today.getFullYear(), today.getMonth() - mb, 1); const mk = monthKey(md); const dim = daysInMonth(md);
    D.employees.forEach((e) => {
      if (e.joining_date > iso(new Date(md.getFullYear(), md.getMonth() + 1, 0))) return;
      const st = mb >= 3 ? { absent: RI(0, 2), leave: RI(0, 1), late: RI(0, 200), early: RI(0, 60), ot: e.overtime_eligible ? RI(0, 400) : 0, present: 20 } : monthStats(e.id, mk);
      const daily = e.salary / dim, minute = daily / 9 / 60;
      const absent_deduction = Math.round(daily * st.absent), leave_deduction = Math.round(daily * Math.max(0, st.leave - 1));
      const late_deduction = st.late >= 120 ? Math.round(st.late * minute) : 0, early_leave_deduction = Math.round(st.early * minute);
      const loan = D.loans.find((l) => l.user_id === e.id && l.status === 'Running'); const loan_deduction = loan ? loan.monthly_deduction : 0;
      const adv = mb === 1 ? D.advance_salaries.find((a) => a.user_id === e.id && a.status === 'Approved') : null; const advance_salary_deduction = adv ? adv.amount : 0;
      const overtime_salary = e.overtime_eligible ? Math.round(st.ot * minute) : 0;
      const total_deductions = absent_deduction + leave_deduction + late_deduction + early_leave_deduction + loan_deduction + advance_salary_deduction;
      const net = e.salary - total_deductions + overtime_salary;
      const paid = !(mb === 1 && PENDING_PAYROLL_COMPANIES.includes(e.company_id)); const payDate = iso(new Date(md.getFullYear(), md.getMonth() + 1, RI(1, 5)));
      D.payroll.push({ id: ids.pay++, user_id: e.id, company_id: e.company_id, month: MONTHS[md.getMonth()], year: md.getFullYear(), month_key: mk, gross_salary: e.salary, absent_deduction, leave_deduction, late_deduction, early_leave_deduction, loan_deduction, advance_salary_deduction, overtime_salary, total_deductions, net_salary: Math.round(net), status: paid ? 'Paid' : 'Pending', payment_date: paid ? payDate : null, absent_days: st.absent, leave_days: st.leave, late_minutes: st.late, overtime_minutes: st.ot });
    });
  }

  /* ---------- customers / suppliers ---------- */
  CUSTOMER_NAMES.forEach((n, i) => D.customers.push({ id: ids.cust++, company_id: COMPANIES[1 + (i % 7)].id, name: n, phone: '01' + RI(3, 9) + String(RI(10000000, 99999999)), type: 'customer' }));
  SUPPLIER_NAMES.forEach((n, i) => D.suppliers.push({ id: ids.sup++, company_id: i < 4 ? 2 : i < 10 ? pick([4, 6, 7]) : pick([1, 3, 5]), name: n, phone: '01' + RI(3, 9) + String(RI(10000000, 99999999)) }));
  const custOf = (cid) => { const l = D.customers.filter((c) => c.company_id === cid); return l.length ? pick(l) : pick(D.customers); };
  const supOf = (cid) => { const l = D.suppliers.filter((c) => c.company_id === cid); return l.length ? pick(l) : pick(D.suppliers); };

  /* ---------- journal engine ---------- */
  const post = (company_id, date, source, description, lines, source_id = null, reference = null) => {
    if (date > T) date = T;   // the ledger never runs ahead of today
    const items = lines.map(([code, debit, credit, party_type, party_id, note]) => { const a = byCode(code); return { account_id: a ? a.id : null, account_code: code, debit: Math.round(debit || 0), credit: Math.round(credit || 0), party_type: party_type || null, party_id: party_id || null, note: note || null }; });
    const dr = items.reduce((n, i) => n + i.debit, 0), cr = items.reduce((n, i) => n + i.credit, 0);
    if (Math.abs(dr - cr) > 1) { const diff = dr - cr; if (diff > 0) items[items.length - 1].credit += diff; else items[items.length - 1].debit -= diff; }
    const je = { id: ids.je++, company_id, date, reference: reference || `${source.toUpperCase().slice(0, 3)}-${String(ids.je).padStart(5, '0')}`, source, source_id, description, items };
    D.journal_entries.push(je); return je;
  };
  const bankOf = (cid, prefer) => { const bs = D.banks.filter((b) => b.company_id === cid && b.type !== 'cash'); return prefer === 'mobile' ? (bs.find((b) => b.type === 'mobile_banking') || bs[0]) : (bs[0] || D.banks[0]); };
  const incomeCode = { travel: ['4110', '4120', '4160'], software: ['4130'], construction: ['4140'], ecommerce: ['4610'], interior: ['4140'], manufacturing: ['4180'], realestate: ['4170'], holding: ['4130'] };
  const costCode = { travel: ['5110', '5120'], software: ['5610'], construction: ['5130'], ecommerce: ['5610'], interior: ['5130'], manufacturing: ['5140'], realestate: ['5610'], holding: ['5610'] };
  const monthsBack = 6;
  const start = new Date(today.getFullYear(), today.getMonth() - monthsBack, 1);

  // opening balances (day 1)
  COMPANIES.forEach((c) => {
    if (c.kind === 'holding') return;
    const banks = D.banks.filter((b) => b.company_id === c.id);
    const lines = banks.map((b) => [b.account_code, Math.round(c.rev * R(0.5, 1.2) / (banks.length)), 0]);
    lines.push(['1311', Math.round(c.rev * R(0.4, 0.8)), 0]); lines.push(['2111', 0, Math.round(c.rev * R(0.2, 0.5))]); lines.push(['1011', 250000, 0]);
    const total = lines.reduce((n, l) => n + l[1] - l[2], 0); lines.push(['3400', 0, total]);
    post(c.id, iso(start), 'opening', 'Opening balances', lines);
  });
  // group-level opening: owner investment into holding banks
  { const banks = D.banks.filter((b) => b.company_id === 1); const lines = banks.map((b) => [b.account_code, 2500000, 0]); lines.push(['3110', 0, 2500000 * banks.length]); post(1, iso(start), 'opening', 'Owner investment', lines); }

  // monthly cycle per company
  for (let mb = monthsBack; mb >= 0; mb--) {
    const md = new Date(today.getFullYear(), today.getMonth() - mb, 1); const dim = daysInMonth(md); const mk = monthKey(md);
    const lastDay = mb === 0 ? today.getDate() : dim;
    const seasonal = 1 + 0.12 * Math.sin((md.getMonth() / 12) * Math.PI * 2) + R(-0.08, 0.08);
    COMPANIES.forEach((c) => {
      const cid = c.id; const bank = bankOf(cid);
      // ---- petty cash replenishment on the 1st (Dr 1011 / Cr main bank) sized to the month's expected cash spend
      { const scale = c.kind === 'holding' ? 0.6 : (plan[cid] / 10); const need = Math.round(EXPENSE_CATS.reduce((n, x) => n + x[3], 0) * scale * 0.42 / 1000) * 1000; const d1 = iso(new Date(md.getFullYear(), md.getMonth(), 1)); if (Date.parse(d1) <= today.getTime()) post(cid, d1, 'transfer', 'Petty cash replenishment', [['1011', need, 0], [bank.account_code, 0, need]]); }
      // ---- sales
      const nSales = c.kind === 'holding' ? 0 : c.kind === 'ecommerce' ? RI(18, 26) : RI(6, 12);
      const target = c.rev * 1.3 * seasonal * (mb === 0 ? lastDay / dim : 1);
      for (let i = 0; i < nSales; i++) {
        const day = RI(1, lastDay); const date = iso(new Date(md.getFullYear(), md.getMonth(), day));
        const total = Math.round(target / nSales * R(0.5, 1.6) / 100) * 100; const cust = custOf(cid);
        const paidNow = c.kind === 'ecommerce' ? total : (chance(0.45) ? total : Math.round(total * R(0, 0.6) / 100) * 100);
        const due = total - paidNow; const dueDate = iso(addDays(date, RI(7, 45)));
        const s = { id: ids.sale++, company_id: cid, invoice_no: `INV-${c.short_name.slice(0, 3)}-${mk.replace('-', '')}-${String(i + 1).padStart(3, '0')}`, customer_id: cust.id, customer: cust.name, date, total, paid_amount: paidNow, due_amount: due, payment_status: due === 0 ? 'paid' : paidNow ? 'partial' : 'due', due_date: dueDate };
        D.sales.push(s);
        const inc = pick(incomeCode[c.kind]);
        const recvBank = (c.kind === 'ecommerce' ? chance(0.45) : chance(0.25)) ? bankOf(cid, 'mobile') : bank;
        const lines = []; if (paidNow) lines.push([recvBank.account_code, paidNow, 0]); if (due) lines.push(['1311', due, 0, 'customer', cust.id]); lines.push([inc, 0, total]);
        post(cid, date, 'sale', `Sale ${s.invoice_no} — ${cust.name}`, lines, s.id, s.invoice_no);
        // direct cost 55–75%
        const cost = Math.round(total * R(0.42, 0.62) / 100) * 100; const sup = supOf(cid); const costPaid = chance(0.5) ? cost : Math.round(cost * R(0, 0.5) / 100) * 100;
        const p = { id: ids.pur++, company_id: cid, supplier_id: sup.id, supplier: sup.name, date, total: cost, paid_amount: costPaid, due_amount: cost - costPaid, payment_status: cost === costPaid ? 'paid' : costPaid ? 'partial' : 'due', due_date: iso(addDays(date, RI(10, 40))) };
        D.purchases.push(p);
        const pl = [[pick(costCode[c.kind]), cost, 0]]; if (costPaid) pl.push([bank.account_code, 0, costPaid]); if (cost - costPaid) pl.push(['2111', 0, cost - costPaid, 'supplier', sup.id]);
        post(cid, date, 'purchase', `Purchase — ${sup.name}`, pl, p.id);
        // schedules
        if (due) { const settled = Date.parse(dueDate) < today.getTime() - 86400000 * 3 && chance(0.8); const overdue = !settled && Date.parse(dueDate) < today.getTime(); const paidAmt = settled ? due : (chance(0.2) ? Math.round(due * R(0.2, 0.6) / 100) * 100 : 0); D.payment_schedules.push({ id: ids.ps++, company_id: cid, type: 'receive', party_type: 'customer', party_id: cust.id, party_name: cust.name, source_label: s.invoice_no, amount: due, paid_amount: paidAmt, scheduled_date: dueDate, original_scheduled_date: dueDate, reschedule_count: chance(0.15) ? RI(1, 2) : 0, status: settled ? 'paid' : overdue ? 'overdue' : 'pending', priority: due > target / nSales ? 'high' : chance(0.5) ? 'medium' : 'low', paid_date: settled ? iso(addDays(dueDate, RI(-5, 12))) : null }); if (settled) post(cid, iso(addDays(dueDate, RI(-5, 12))), 'receipt', `Receipt against ${s.invoice_no}`, [[bank.account_code, due, 0], ['1311', 0, due, 'customer', cust.id]]); }
        if (cost - costPaid) { const dd = p.due_date; const settled = Date.parse(dd) < today.getTime() - 86400000 * 3 && chance(0.85); const overdue = !settled && Date.parse(dd) < today.getTime(); D.payment_schedules.push({ id: ids.ps++, company_id: cid, type: 'pay', party_type: 'supplier', party_id: sup.id, party_name: sup.name, source_label: `PO-${p.id}`, amount: cost - costPaid, paid_amount: settled ? cost - costPaid : 0, scheduled_date: dd, original_scheduled_date: dd, reschedule_count: chance(0.1) ? 1 : 0, status: settled ? 'paid' : overdue ? 'overdue' : 'pending', priority: chance(0.3) ? 'high' : 'medium', paid_date: settled ? iso(addDays(dd, RI(-3, 10))) : null }); if (settled) post(cid, iso(addDays(dd, RI(-3, 10))), 'payment', `Payment to ${sup.name}`, [['2111', cost - costPaid, 0, 'supplier', sup.id], [bank.account_code, 0, cost - costPaid]]); }
      }
      // ---- expenses (approved → posted; a few pending this month)
      const staff = empsOf(cid);
      EXPENSE_CATS.forEach(([cat, code, , base], ci) => {
        const scale = c.kind === 'holding' ? 0.6 : (plan[cid] / 10);
        const nItems = cat === 'Office Rent' ? 1 : RI(1, 2);
        for (let k = 0; k < nItems; k++) {
          const day = cat === 'Office Rent' ? 3 : RI(1, dim); if (day > lastDay) continue;
          const date = iso(new Date(md.getFullYear(), md.getMonth(), day));
          const spike = (mb === 0 && cat === 'Marketing & Advertising' && cid === 5) ? 2.1 : (mb === 1 && cat === 'Fuel & Vehicle' && cid === 7) ? 1.6 : 1;
          const amount = Math.round(base * scale * spike / nItems * R(0.6, 1.15) / 100) * 100;
          const who = staff.length ? pick(staff) : boss;
          const pending = mb === 0 && day >= lastDay - 6 && chance(0.55);
          const mode = cat === 'Office Rent' ? 'bank_transfer' : pick(['cash', 'bank_transfer', 'bank_transfer', 'cash', 'mobile_banking']);
          const e = { id: ids.exp++, company_id: cid, title: `${cat}${nItems > 1 ? ' — ' + pick(['bill', 'invoice', 'voucher', 'receipt']) + ' ' + (k + 1) : ''}`, amount, expense_date: date, category: cat, category_id: ci + 1, account_code: code, department: who.department, payment_mode: mode, approval_status: pending ? 'pending' : 'approved', user_id: who.id, user_name: who.name, bank_id: mode === 'bank_transfer' ? bank.id : null };
          D.expenses.push(e);
          if (!pending) post(cid, date, 'expense', `${cat} — ${e.title}`, [[code, amount, 0], [mode === 'bank_transfer' ? bank.account_code : mode === 'mobile_banking' ? bankOf(cid, 'mobile').account_code : '1011', 0, amount]], e.id);
        }
        if (mb === 0 && cid !== 1) D.expense_budgets.push({ id: ids.bud++, company_id: cid, category: cat, category_id: ci + 1, period: 'Monthly', amount: Math.round(base * scale * 1.05 / 100) * 100, threshold: 80 });
      });
      // ---- payroll journal for the month (Paid → bank; Pending → 2210)
      const rows = D.payroll.filter((p) => p.company_id === cid && p.month_key === mk);
      if (rows.length) {
        const net = rows.reduce((n, r) => n + r.net_salary, 0); const gross = rows.reduce((n, r) => n + r.gross_salary, 0); const paid = rows[0].status === 'Paid';
        const date = paid ? rows[0].payment_date : iso(new Date(md.getFullYear(), md.getMonth() + 1, 0));
        if (paid || mb === 1) post(cid, date > T ? T : date, 'salary', `Salary — ${MONTHS[md.getMonth()]} ${md.getFullYear()}`, [['6110', gross, 0], paid ? [bank.account_code, 0, net] : ['2210', 0, net], ['1455', 0, gross - net, null, null, 'deductions & recoveries']], null, `SAL-${mk}`);
        if (!paid) rows.forEach((r) => D.payment_schedules.push({ id: ids.ps++, company_id: cid, type: 'pay', party_type: 'employee', party_id: r.user_id, party_name: (D.employees.find((e) => e.id === r.user_id) || {}).name, source_label: `Salary ${r.month}`, amount: r.net_salary, paid_amount: 0, scheduled_date: iso(new Date(md.getFullYear(), md.getMonth() + 1, 5)), original_scheduled_date: iso(new Date(md.getFullYear(), md.getMonth() + 1, 5)), reschedule_count: 0, status: 'pending', priority: 'high', paid_date: null }));
      }
    });
  }
  // holding-company recurring: rent for HQ, professional fees, and management fee income from subsidiaries
  for (let mb = monthsBack; mb >= 0; mb--) { const md = new Date(today.getFullYear(), today.getMonth() - mb, 1); const date = iso(new Date(md.getFullYear(), md.getMonth(), Math.min(10, mb === 0 ? today.getDate() : 10))); if (Date.parse(date) > today.getTime()) continue; const bank = bankOf(1); post(1, date, 'sale', 'Management fee — subsidiaries', [[bank.account_code, 350000, 0], ['4130', 0, 350000]]); }

  /* ---------- bank balances from the ledger (+ treasury sweeps so no account is left overdrawn) ---------- */
  const computeBankBalances = () => D.banks.forEach((b) => { let bal = 0; D.journal_entries.forEach((je) => je.items.forEach((it) => { if (it.account_code === b.account_code) bal += it.debit - it.credit; })); b.balance = bal; });
  computeBankBalances();
  D.banks.filter((b) => b.balance < 0).forEach((b) => {
    const src = D.banks.filter((x) => x.company_id === b.company_id && x.id !== b.id).sort((x, y) => y.balance - x.balance)[0]; if (!src) return;
    const amt = Math.ceil((-b.balance + 150000) / 10000) * 10000;
    post(b.company_id, iso(addDays(today, -1)), 'transfer', `Fund transfer ${src.name} → ${b.name}`, [[b.account_code, amt, 0], [src.account_code, 0, amt]]);
  });
  computeBankBalances();

  /* ---------- CRM ---------- */
  const salesPeople = D.employees.filter((e) => /Sales/.test(e.designation) || /Sales/.test(e.department));
  const leadTypeFor = { travel: ['air_ticket', 'visa', 'visa'], software: ['software'], construction: ['interior', 'other'], ecommerce: ['other'], interior: ['interior'], manufacturing: ['other'], realestate: ['other'], holding: ['software', 'other'] };
  COMPANIES.forEach((c) => {
    if (c.kind === 'holding') return;
    const n = RI(9, 16);
    for (let i = 0; i < n; i++) {
      const created = addDays(today, -RI(1, 90)); const status = pick(LEAD_STATUS.concat(['new', 'contacted', 'qualified']));
      const closed = status === 'won' || status === 'lost'; const stale = !closed && status !== 'new' && chance(0.28);
      const lastF = status === 'new' ? null : (stale ? addDays(today, -RI(13, 40)) : addDays(today, -RI(0, 8)));
      const owner = salesPeople.filter((s) => s.company_id === c.id); const asg = owner.length ? pick(owner) : (empsOf(c.id)[0] || boss);
      D.leads.push({ id: ids.lead++, company_id: c.id, name: chance(0.6) ? pick(CUSTOMER_NAMES) : mkName(), phone: '01' + RI(3, 9) + String(RI(10000000, 99999999)), lead_type: pick(leadTypeFor[c.kind]), source: pick(SOURCES), status, assigned_to: asg.id, assigned_name: asg.name, value: Math.round(c.rev / 12 * R(0.05, 0.6) / 1000) * 1000, created_at: iso(created), last_followup_at: lastF ? iso(lastF) : null, next_followup_at: closed ? null : (stale ? iso(addDays(lastF, RI(2, 5))) : iso(addDays(today, chance(0.15) ? -RI(1, 3) : RI(0, 10)))) });
    }
  });
  D.leads.filter((l) => ['proposal_sent', 'negotiation', 'won', 'lost'].includes(l.status)).forEach((l) => D.deals.push({ id: ids.deal++, company_id: l.company_id, lead_id: l.id, title: `${l.name} — ${l.lead_type.replace('_', ' ')}`, stage: l.status === 'won' ? 'closed_won' : l.status === 'lost' ? 'closed_lost' : l.status, amount: l.value, closing_date: iso(addDays(today, l.status === 'won' || l.status === 'lost' ? -RI(1, 30) : RI(-5, 30))), status: l.status === 'won' ? 'won' : l.status === 'lost' ? 'lost' : 'open', agent_id: l.assigned_to }));

  /* ---------- projects & tasks ---------- */
  const PROJ = [[3, 'Epal ERP v3 — Payment Scheduling'], [3, 'DM Portal Mobile App'], [3, 'Wood Art Estimator SPA'], [4, 'Bashundhara Duplex — Interior Fit-out'], [4, 'Uttara Office Renovation'], [6, 'Gulshan Penthouse — Kitchen & Wardrobes'], [6, 'Doreen Hotel Lobby Woodwork'], [7, 'Line-2 Furniture Batch Q3'], [2, 'Umrah Group Package — September'], [2, 'Corporate Travel Desk — Nova Textiles'], [5, 'Eid Campaign Storefront'], [8, 'Mirpur Plot Sales Drive'], [1, 'Group Consolidation & Audit'], [3, 'EON — AI Business Summit demo']];
  const engineers = (cid) => D.employees.filter((e) => e.company_id === cid && e.id > 1);
  PROJ.forEach(([cid, name]) => {
    const team = engineers(cid); const start = addDays(today, -RI(20, 120)); const len = RI(45, 150); const end = addDays(start, len);
    const elapsed = Math.min(1, Math.max(0, (today - start) / (end - start)));
    const status = elapsed >= 1 ? (chance(0.6) ? 'completed' : 'in_progress') : elapsed > 0 ? (chance(0.12) ? 'on_hold' : 'in_progress') : 'not_started';
    const progress = status === 'completed' ? 100 : Math.round(Math.min(97, elapsed * 100 * R(0.55, 1.15)));
    const budget = Math.round(R(4, 30) * 100000); const spent = Math.round(budget * Math.min(1.1, elapsed * R(0.7, 1.25)));
    const cust = custOf(cid); const mgr = team.find((e) => /Manager|Lead|Supervisor|Head/.test(e.designation)) || team[0] || boss;
    const p = { id: ids.proj++, company_id: cid, project_name: name, customer_id: cust.id, customer: cust.name, status, start_date: iso(start), end_date: iso(end), budget, spent, progress, manager_id: mgr.id, manager: mgr.name, team: team.slice(0, RI(3, 6)).map((e) => e.id) };
    D.projects.push(p);
    const nT = RI(6, 12); const cols = ['todo', 'in_progress', 'review', 'done'];
    const TITLES = ['Requirements sign-off', 'Design mock-ups', 'Client review meeting', 'Procurement of materials', 'Site measurement', 'API integration', 'QA regression pass', 'Payment milestone invoice', 'Vendor quotation', 'Install & handover', 'Training session', 'Documentation', 'Weekly status report', 'Bug triage', 'Deployment to server', 'Snag list closure', 'Drawing revision R2', 'Cost estimate update', 'UAT with client', 'Final walkthrough'];
    for (let i = 0; i < nT; i++) {
      const due = addDays(start, RI(5, len)); const created = addDays(due, -RI(5, 20));
      const donep = status === 'completed' ? 0.95 : Math.min(0.9, elapsed * 1.05);
      const isDone = chance(donep) && due <= addDays(today, 5); const st = isDone ? 'done' : (due < today ? pick(['in_progress', 'review', 'todo', 'in_progress']) : pick(cols.slice(0, 3)));
      const assignees = (p.team.length ? [pick(p.team)] : [mgr.id]).concat(chance(0.3) && p.team.length > 1 ? [pick(p.team)] : []);
      D.tasks.push({ id: ids.task++, company_id: cid, project_id: p.id, project: name, title: pick(TITLES), priority: pick(['low', 'medium', 'medium', 'high']), status: st, assigned_to: [...new Set(assignees)], created_by: mgr.id, start_date: iso(created), due_date: iso(due), completed_at: st === 'done' ? iso(addDays(due, RI(-6, 2))) : null, label: pick(['delivery', 'client', 'internal', 'finance', 'design']) });
    }
  });
  // office to-dos
  const TODO = ['Renew trade licence', 'Submit VAT return (Mushak 9.1)', 'Board meeting agenda', 'Bank reconciliation — July', 'Fire safety drill', 'Vendor contract renewal — Link3', 'Update employee handbook', 'Server backup audit', 'Office AC servicing', 'Insurance premium — vehicles', 'Payroll approval — this month', 'Quarterly tax advance', 'New hire onboarding — 3 joiners', 'Client satisfaction survey', 'Petty cash count'];
  TODO.forEach((t, i) => { const asg = pick(D.employees.filter((e) => e.company_id === 1 || e.id <= 4)); D.office_todos.push({ id: ids.todo++, company_id: pick([1, 1, 2, 3, 4]), title: t, department: asg.department, priority: pick(['high', 'medium', 'low']), status: pick(['pending', 'pending', 'in_progress', 'completed']), due_date: iso(addDays(today, RI(-6, 20))), assignees: [asg.id], assignee_names: [asg.name] }); });
  // notices
  [['Eid-ul-Adha holiday schedule', -20, 5], ['New attendance policy — device punch mandatory', -8, 30], ['AI Business Summit 2026 — company delegation', -2, 40]].forEach(([t, a, b]) => D.notices.push({ id: ids.notice++, company_id: 1, title: t, published_at: iso(addDays(today, a)), expires_at: iso(addDays(today, b)) }));

  // strip generator-only fields the ERP would not expose
  D.employees.forEach((e) => { delete e.traits; });
  return D;
}

export default { generateDemo };
