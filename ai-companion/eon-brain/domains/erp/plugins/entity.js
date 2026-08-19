/* ============================================================
   EON · entity — questions about one person, one party, one thing.

   The layers answer at the level of the company ("who is absent",
   "cash position"). This answers at the level of a name:

     "what are the active tasks of Md Imran Hossain?"
     "how much is Mofiz Driver due?"
     "any ticket sale today?"       "vendor meeting?"
     "assign Md Imran Hossain a task: check ledger entries"
     "any accounting error?"        "last transaction?"
     "take me to Fahim's task board"

   Every answer carries the number, where it came from, and a button
   that opens the exact ERP screen — the navigator supplies the
   address, so nothing here invents a link.
   ============================================================ */
import * as F from '../finance.js';
import * as P from '../people.js';
import * as O from '../ops.js';
import { fmtBDT, fmtBDTk, iso, daysBetween, MONTHS } from '../dataset.js';

const D0 = () => (typeof window !== 'undefined' && window.EonErp && window.EonErp.dataset ? window.EonErp.dataset() : null);
const T = (D) => (D && D.meta && D.meta.today) || iso(new Date());
const scope = (ctx) => ({ company: (ctx && ctx.company != null) ? +ctx.company : (typeof window !== 'undefined' && window.EonErp && window.EonErp.company ? window.EonErp.company() : null) });
const nav = () => (typeof window !== 'undefined' ? window.EonNavigator : null);
const screenUrl = (query) => { const N = nav(); if (!N) return null; const hit = (N.find(query, 1) || [])[0]; return hit ? N.url(hit.uri) : null; };
const openAction = (label, query) => { const u = screenUrl(query); return u ? [{ label, kind: 'erp-open', href: u }] : []; };

/* ---------- who is being asked about ---------- */
const NOISE = /\b(the|a|an|of|for|is|are|show|me|what|whats|what's|how|much|many|due|task|tasks|active|open|assign|to|his|her|their|my|please|board|ledger|entries)\b/gi;
function nameIn(text) {
  const cleaned = String(text || '').replace(/[?.!,]/g, ' ').replace(/'s\b|\u2019s\b/g, '');
  // a run of capitalised words is the strongest signal ("Md Imran Hossain", "Mofiz Driver")
  const caps = cleaned.match(/\b([A-Z][a-z.]{1,15}(?:\s+[A-Z][a-z.]{1,15}){0,3})\b/g) || [];
  const skip = /^(What|How|Any|Who|Show|Take|Assign|Last|The|Is|Are|Does|Do|Can|EON|ERP|Md)$/;
  const cand = caps.map((c) => c.trim()).filter((c) => !skip.test(c) || c.split(/\s+/).length > 1);
  if (cand.length) return cand.sort((a, b) => b.length - a.length)[0];
  const bare = cleaned.replace(NOISE, ' ').replace(/\s+/g, ' ').trim();
  return bare.length >= 3 ? bare : null;
}

/** an employee, or a customer/supplier the ledger knows */
function resolve(D, text) {
  const raw = nameIn(text);
  if (!raw) return null;
  const emp = P.findEmployee(D, raw);
  if (emp) {
    const tok = (x) => String(x || '').toLowerCase().split(/[^a-z]+/).filter((w) => w.length >= 3);
    const a = tok(raw), b = tok(emp.name);
    if (!a.length || a.some((w) => b.includes(w))) return { kind: 'employee', id: emp.id, name: emp.name, row: emp };
  }
  const low = raw.toLowerCase();
  for (const type of ['receive', 'pay']) {
    const s = F.schedules(D, type, {});
    const party = (s.byParty || []).find((p) => String(p.party_name || '').toLowerCase().includes(low) || low.includes(String(p.party_name || '').toLowerCase()));
    if (party) return { kind: 'party', id: party.party_id, name: party.party_name, side: type, row: party };
  }
  return raw ? { kind: 'unknown', name: raw } : null;
}

/* ---------- tasks of one person ---------- */
function tasksOf(D, person, opts) {
  const all = O.tasks(D, opts).all || [];
  const mine = all.filter((t) => (t.assigned_to || []).includes(person.id) || (t.assignees || []).some((n) => n === person.name));
  const open = mine.filter((t) => t.status !== 'done');
  const t = T(D);
  const overdue = open.filter((k) => k.due_date && k.due_date < t);
  return { all: mine, open, overdue, done: mine.filter((k) => k.status === 'done') };
}

/* ---------- what one person or party owes / is owed ---------- */
function duesOf(D, person, opts) {
  if (person.kind === 'party') {
    const s = F.schedules(D, person.side, opts);
    const row = (s.byParty || []).find((p) => p.party_name === person.name);
    return { kind: 'party', side: person.side, due: row ? row.due : 0, overdue: row ? row.overdue : 0, oldest: row ? row.oldest : 0, count: row ? row.count : 0 };
  }
  // an employee: unpaid salary, loans outstanding, advances
  const pr = P.payroll(D, opts);
  const pending = (pr.pending || []).filter((p) => p.id === person.id || p.name === person.name);
  const ln = P.loans(D, opts);
  const loans = (ln.loans || ln.rows || []).filter((l) => l.user_id === person.id || l.name === person.name);
  const advances = (ln.advances || []).filter((a) => a.user_id === person.id || a.name === person.name);
  const unpaidSalary = pending.reduce((n, p) => n + (+p.net || +p.net_salary || 0), 0);
  const loanLeft = loans.reduce((n, l) => n + (+l.remaining_amount || 0), 0);
  const advTotal = advances.reduce((n, a) => n + (+a.amount || 0), 0);
  return { kind: 'employee', unpaidSalary, loanLeft, advTotal, loans, advances, pending };
}

/* ---------- the books: obvious mistakes ---------- */
export function accountingErrors(D, opts = {}) {
  const out = [];
  const co = opts.company != null ? +opts.company : null;
  const entries = (D.journal_entries || []).filter((e) => co == null || +e.company_id === co);
  const accounts = new Map((D.accounts || []).map((a) => [String(a.code), a]));

  let unbalanced = 0, orphan = 0, empty = 0, future = 0;
  const t = T(D);
  const samples = [];
  for (const e of entries) {
    const items = e.items || [];
    if (!items.length) { empty++; if (samples.length < 6) samples.push(`${e.date} ${e.reference || e.id}: no lines`); continue; }
    const dr = items.reduce((n, i) => n + (+i.debit || 0), 0);
    const cr = items.reduce((n, i) => n + (+i.credit || 0), 0);
    if (Math.abs(dr - cr) > 0.5) { unbalanced++; if (samples.length < 6) samples.push(`${e.date} ${e.reference || e.id}: debit ${fmtBDT(dr)} vs credit ${fmtBDT(cr)}`); }
    for (const i of items) {
      if (i.account_code != null && !accounts.has(String(i.account_code))) { orphan++; if (samples.length < 6) samples.push(`${e.date} ${e.reference || e.id}: account ${i.account_code} is not in the chart`); break; }
    }
    if (e.date > t) { future++; if (samples.length < 6) samples.push(`${e.date} ${e.reference || e.id}: dated in the future`); }
  }
  if (unbalanced) out.push({ kind: 'unbalanced', count: unbalanced, text: `${unbalanced} journal ${unbalanced === 1 ? 'entry does' : 'entries do'} not balance` });
  if (orphan) out.push({ kind: 'orphan-account', count: orphan, text: `${orphan} ${orphan === 1 ? 'entry uses an account' : 'entries use accounts'} not in the chart` });
  if (empty) out.push({ kind: 'empty', count: empty, text: `${empty} ${empty === 1 ? 'entry has' : 'entries have'} no lines` });
  if (future) out.push({ kind: 'future', count: future, text: `${future} ${future === 1 ? 'entry is' : 'entries are'} dated in the future` });
  return { problems: out, checked: entries.length, samples };
}

/* ---------- the intents ---------- */
const INTENTS = [
  /* tasks of a person, and their board */
  { id: 'tasks-of', re: /\b(task|tasks|to-?do|work|assignment)s?\b[\s\S]*\b(of|for|assigned to)\b|\b(what|which)\b[\s\S]*\btasks?\b|\btask board\b/i, a(D, s, q) {
    const person = resolve(D, q);
    if (!person || person.kind === 'unknown') return null;
    if (person.kind !== 'employee') return null;
    const t = tasksOf(D, person, s);
    const board = screenUrl('tasks');
    if (!t.all.length) return { speak: `${person.name} has no tasks on the board right now.`, detail: [], actions: board ? [{ label: 'Open the task board', kind: 'erp-open', href: board }] : [], view: 'ops' };
    const line = (k) => `${k.title}${k.project ? ` · ${k.project}` : ''} — ${k.status.replace('_', ' ')}${k.due_date ? `, due ${k.due_date}` : ''}${k.due_date && k.due_date < T(D) ? ' (overdue)' : ''}`;
    return {
      speak: `${person.name} has ${t.open.length} active task${t.open.length === 1 ? '' : 's'}${t.overdue.length ? `, ${t.overdue.length} of them overdue` : ''}. ${t.open.slice(0, 3).map((k) => k.title).join('; ')}${t.open.length > 3 ? `, and ${t.open.length - 3} more` : ''}.`,
      detail: t.open.slice(0, 8).map(line),
      actions: board ? [{ label: `Open ${person.name.split(' ').slice(-1)[0]}'s board`, kind: 'erp-open', href: board }] : [],
      view: 'ops',
    };
  } },

  /* what someone owes, or is owed */
  { id: 'dues-of', re: /\b(due|owes?|owing|outstanding|balance|payable|receivable|dues)\b/i, a(D, s, q) {
    const person = resolve(D, q);
    if (!person || person.kind === 'unknown') return null;
    const d = duesOf(D, person, s);
    if (d.kind === 'party') {
      if (!d.due) return { speak: `${person.name} has nothing outstanding.`, detail: [], view: 'finance' };
      const side = d.side === 'receive' ? 'owes us' : 'we owe';
      return {
        speak: `${person.name}: ${side} ${fmtBDT(d.due)} across ${d.count} item${d.count === 1 ? '' : 's'}${d.overdue ? `, of which ${fmtBDT(d.overdue)} is overdue (oldest ${d.oldest} days)` : ', none overdue'}.`,
        detail: [`Total ${fmtBDT(d.due)} · overdue ${fmtBDT(d.overdue)} · oldest ${d.oldest}d`],
        actions: openAction('Open party statement', 'party statement'),
        view: 'finance',
      };
    }
    const parts = [];
    if (d.unpaidSalary) parts.push(`unpaid salary ${fmtBDT(d.unpaidSalary)}`);
    if (d.loanLeft) parts.push(`loan outstanding ${fmtBDT(d.loanLeft)}`);
    if (d.advTotal) parts.push(`advances ${fmtBDT(d.advTotal)}`);
    if (!parts.length) return { speak: `Nothing is outstanding for ${person.name} — no unpaid salary, no loan, no advance.`, detail: [], view: 'people' };
    return {
      speak: `${person.name}: ${parts.join(', ')}.`,
      detail: [].concat(
        d.pending.map((p) => `Unpaid payslip — ${fmtBDT(+p.net || +p.net_salary || 0)}`),
        d.loans.map((l) => `Loan ${fmtBDT(+l.amount || 0)}, ${fmtBDT(+l.remaining_amount || 0)} left, ${fmtBDT(+l.monthly_deduction || 0)}/month`),
        d.advances.map((a) => `Advance ${fmtBDT(+a.amount || 0)} (${a.month || ''}) — ${a.status || ''}`)
      ).slice(0, 8),
      actions: openAction('Open payroll', 'employee salaries'),
      view: 'people',
    };
  } },

  /* sales today */
  { id: 'sales-today', re: /\b(ticket sale|sales?|invoice[sd]?|sold|booking)\b[\s\S]*\b(today|so far|this morning)\b|\b(any|how many)\b[\s\S]*\b(sale|sales|ticket)\b/i, a(D, s) {
    const t = T(D);
    const co = s.company;
    const rows = (D.sales || []).filter((x) => (co == null || +x.company_id === co) && String(x.date || '').slice(0, 10) === t);
    const total = rows.reduce((n, r) => n + (+r.total || 0), 0);
    const paid = rows.reduce((n, r) => n + (+r.paid_amount || 0), 0);
    if (!rows.length) return { speak: `No sales recorded today (${t}).`, detail: [], actions: openAction('Open sales', 'sales'), view: 'crm' };
    return {
      speak: `${rows.length} sale${rows.length === 1 ? '' : 's'} today totalling ${fmtBDT(total)}; ${fmtBDT(paid)} collected, ${fmtBDT(total - paid)} still due.`,
      detail: rows.slice(0, 8).map((r) => `${r.invoice_no || r.id} — ${r.customer || 'walk-in'} ${fmtBDT(+r.total || 0)}${(+r.due_amount || 0) > 0 ? ` (due ${fmtBDT(+r.due_amount)})` : ''}`),
      actions: openAction('Open sales', 'sales'),
      view: 'crm',
    };
  } },

  /* meetings — the ERP keeps them as office to-dos and tasks */
  { id: 'meetings', re: /\bmeeting|appointment|visit\b/i, a(D, s, q) {
    const t = T(D);
    const want = /\bvendor|supplier\b/i.test(q) ? /vendor|supplier/i : /meeting|appointment|visit/i;
    const todos = (D.office_todos || []).filter((x) => (s.company == null || +x.company_id === s.company) && want.test(x.title || '') && x.status !== 'done');
    const tasks = ((O.tasks(D, s).open) || []).filter((k) => want.test(k.title || ''));
    const rows = [].concat(
      todos.map((x) => ({ what: x.title, when: x.due_date, who: (x.assignee_names || []).join(', '), from: 'office to-do' })),
      tasks.map((k) => ({ what: k.title, when: k.due_date, who: (k.assignees || []).join(', '), from: 'task' }))
    ).sort((a, b) => String(a.when || '').localeCompare(String(b.when || '')));
    if (!rows.length) return { speak: `Nothing like a meeting is on the books${/vendor|supplier/i.test(q) ? ' with a vendor' : ''}.`, detail: [], actions: openAction('Open office to-dos', 'todo'), view: 'ops' };
    const today = rows.filter((r) => r.when === t);
    return {
      speak: `${rows.length} on the books${today.length ? `, ${today.length} today` : ''}. Next: ${rows[0].what}${rows[0].when ? ` on ${rows[0].when}` : ''}${rows[0].who ? ` — ${rows[0].who}` : ''}.`,
      detail: rows.slice(0, 8).map((r) => `${r.when || 'no date'} — ${r.what}${r.who ? ` · ${r.who}` : ''} (${r.from})`),
      actions: openAction('Open office to-dos', 'todo'),
      view: 'ops',
    };
  } },

  /* the last thing that hit the ledger */
  { id: 'last-transaction', re: /\b(last|latest|most recent|newest)\b[\s\S]*\b(transaction|entry|journal|payment|voucher|posting)\b|\blast transaction\b/i, a(D, s) {
    const co = s.company;
    const rows = (D.journal_entries || []).filter((e) => co == null || +e.company_id === co)
      .slice().sort((a, b) => String(b.date).localeCompare(String(a.date)) || (+b.id || 0) - (+a.id || 0));
    if (!rows.length) return { speak: 'The ledger has no entries yet.', detail: [], view: 'finance' };
    const e = rows[0];
    const amt = (e.items || []).reduce((n, i) => n + (+i.debit || 0), 0);
    const N = nav();
    const url = N ? N.findRecord(`journal ${e.id}`) : null;
    return {
      speak: `The last posting was ${e.date} — ${e.description || e.reference || e.source} for ${fmtBDT(amt)}.`,
      detail: (e.items || []).slice(0, 8).map((i) => `${i.account_code || ''} ${i.account_name || ''} — ${(+i.debit || 0) ? 'Dr ' + fmtBDT(+i.debit) : 'Cr ' + fmtBDT(+i.credit)}`)
        .concat([`Reference ${e.reference || '—'} · source ${e.source || '—'}`]),
      actions: url ? [{ label: `Open journal ${e.id}`, kind: 'erp-open', href: N.url(url.uri) }] : openAction('Open journals', 'journals'),
      view: 'finance',
    };
  } },

  /* is anything wrong in the books */
  { id: 'accounting-errors', re: /\b(accounting|ledger|book|journal|posting)s?\b[\s\S]*\b(error|mistake|problem|wrong|issue|unbalanced|off)\b|\b(any|are there)\b[\s\S]*\b(accounting|bookkeeping)\b[\s\S]*\b(error|issue|problem)\b/i, a(D, s) {
    const r = accountingErrors(D, s);
    if (!r.problems.length) {
      return { speak: `I checked ${r.checked} journal entries — every one balances, every account exists, none are future-dated. Nothing wrong in the books.`, detail: [], actions: openAction('Open journals', 'journals'), view: 'finance' };
    }
    return {
      speak: `Yes — ${r.problems.map((p) => p.text).join('; ')}, out of ${r.checked} entries checked.`,
      detail: r.samples.slice(0, 6),
      actions: openAction('Open journals', 'journals'),
      view: 'finance',
    };
  } },
];

/* ---------- assigning work (an instruction, not a query) ---------- */
const ASSIGN = /^\s*(?:eon[,\s]+)?(?:assign|give)\s+(.+?)\s+(?:a\s+)?(?:task|job|work)\s*(?:[,:—-]|\bthe task is\b|\bto\b)?\s*(.*)$/i;
function assign(D, q, s) {
  const m = String(q || '').match(ASSIGN);
  if (!m) return null;
  const who = m[1];
  let what = (m[2] || '')
    .replace(/^(?:[,:—-]\s*)?(?:the\s+)?task\s+is\s*/i, '')     // "…, the task is check ledger entries"
    .replace(/^(?:is|:|,|—|-)\s*/i, '')
    .replace(/^(?:to|for)\s+/i, '')
    .trim();
  const person = resolve(D, who);
  if (!person || person.kind !== 'employee') {
    return { speak: `I could not match “${nameIn(who) || who}” to an employee, so I have not assigned anything. Say the name as it appears in the ERP.`, detail: [], view: 'ops' };
  }
  if (!what) return { speak: `What is the task for ${person.name}? Say it as “assign ${person.name} a task: check ledger entries”.`, detail: [], view: 'ops' };

  // EON is advisory: record the instruction and take the boss to the screen that creates it
  let tracked = null;
  try { tracked = window.EonDelegate ? window.EonDelegate.add(person.name, what, q) : null; } catch {}
  try { window.EonApp && window.EonApp.act && window.EonApp.act('assign-task', { to: person.name, to_id: person.id, task: what }, `Assign ${person.name}: ${what}`); } catch {}
  const create = screenUrl('task board') || screenUrl('tasks');
  return {
    speak: `Noted — ${person.name} to ${what}${tracked && tracked.due ? `, by ${tracked.due}` : ''}. I have put it on my follow-up list; open the task board to create it in the ERP and I will keep chasing it.`,
    detail: [`Assignee: ${person.name}${person.row && person.row.department ? ` · ${person.row.department}` : ''}`, `Task: ${what}`, tracked && tracked.due ? `Follow-up date: ${tracked.due}` : null].filter(Boolean),
    actions: create ? [{ label: 'Open the task board', kind: 'erp-open', href: create }] : [],
    view: 'ops',
  };
}

/* ---------- "take me to X" for a person ---------- */
const TAKE_ME = /\b(take me|go|open|show me)\b[\s\S]*\b(task board|board|profile|payroll|salary|attendance|tasks)\b/i;
function takeMe(D, q, s) {
  if (!TAKE_ME.test(q)) return null;
  /* "take me to his profile" resolves nobody from this sentence alone — the
     person is the one named a moment ago, which the understander remembers.
     Borrow that memory rather than opening a screen with no name on it. */
  let person = resolve(D, q);
  if ((!person || person.kind !== 'employee') && /\b(he|him|his|she|her|they|them|their)\b|(তার|তাঁর|ওর|তাকে|ওকে|উনার|উনি|তিনি)/i.test(q)) {
    try { person = (window.EonUnderstand && window.EonUnderstand.subject_of_last && window.EonUnderstand.subject_of_last()) || person; } catch (e) { /* keep what we had */ }
  }
  const what = /task board|board|tasks/i.test(q) ? 'tasks'
    : /payroll|salary/i.test(q) ? 'employee salaries'
    : /attendance/i.test(q) ? 'attendances'
    : 'user';
  const url = screenUrl(what);
  if (!url) return null;
  const who = person && person.kind === 'employee' ? person.name : null;
  const label = what === 'tasks' ? 'the task board' : what === 'employee salaries' ? 'payroll' : what === 'attendances' ? 'attendance' : what;
  return {
    speak: who ? `Opening ${label} — ${who}'s rows are there.` : `Opening ${label}.`,
    detail: who ? [`EON cannot filter the ERP's own screen for you yet — ${who}'s rows are on this board.`] : [],
    actions: [{ label: 'Open', kind: 'erp-open', href: url }],
    navigate: url,
    view: 'ops',
  };
}

/* ---------- dispatch ---------- */
const CLAIM = /\b(task|tasks|due|owes?|outstanding|sale|sales|ticket|meeting|assign|last (transaction|entry|journal)|accounting|ledger|board|profile)\b/i;

function answer(q, ctx) {
  const s0 = String(q || '').trim();
  if (!s0 || !CLAIM.test(s0)) return null;
  const D = D0();
  if (!D) return null;
  const s = scope(ctx);

  try {
    const a = assign(D, s0, s);
    if (a) return a;
    const t = takeMe(D, s0, s);
    if (t) return t;
    for (const it of INTENTS) {
      if (!it.re.test(s0)) continue;
      const r = it.a(D, s, s0);
      if (r) return r;
    }
  } catch (e) {
    console.warn('[EON entity] failed:', e);
  }
  return null;
}

if (typeof window !== 'undefined') {
  window.EonEntity = { resolve: (q) => resolve(D0(), q), tasksOf, duesOf, accountingErrors: (o) => accountingErrors(D0(), o || {}) };
  (window.__eonDomainQueue = window.__eonDomainQueue || []).push({ id: 'entity', priority: 98, claims: (q) => CLAIM.test(String(q || '')), answer });
}

export default { answer, accountingErrors };
