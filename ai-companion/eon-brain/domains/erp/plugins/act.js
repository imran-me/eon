/* ============================================================
   EON · act — EON does the work, through the ERP itself.

   Not a second write path into the database: EON opens the ERP's own
   form, reads the fields it declares, fills them, and posts them back
   with the boss's session and the form's CSRF token. So every rule the
   ERP enforces — validation, permissions, observers, ledger postings —
   applies exactly as if a person had typed it.

     "assign Imran a task: check ledger entries"   → role.tasks.store
     "message Fahim: bring the file"               → role.chat.send
     "write a notice about Friday's holiday"       → role.notices.store
     "pay Imran's July due"                        → the payment form

   Three rules, always:
     1. nothing is posted until the boss confirms — EON shows the exact
        fields first, in business words;
     2. only routes that exist in the ERP map, only this origin;
     3. what comes back is reported honestly, including the ERP's own
        validation errors, and never dressed up as success.
   ============================================================ */
import * as P from '../people.js';

const nav = () => (typeof window !== 'undefined' ? window.EonNavigator : null);
const D0 = () => (typeof window !== 'undefined' && window.EonErp ? window.EonErp.dataset() : null);
const origin = () => (typeof location !== 'undefined' ? location.origin : '');

/* ---------- talking to the ERP as the boss ---------- */
const forms = new Map();          // route → discovered form

/* A signed-out browser gets no error from Laravel. It gets a 302 to the login
   page and then a perfectly good 200 with a form on it — so "there is no form
   here" is the one thing that is never true. Left unread, EON would offer to
   fill in Email and Password as if they were the task's fields. Every fetch
   therefore looks for the sign-in page and says exactly what happened. */
const SIGN_IN = 'the ERP asked me to sign in before it would show the form';
function signInError() { const e = new Error(SIGN_IN); e.signIn = true; return e; }
function looksLikeLogin(doc, url) {
  if (!doc) return false;
  if (doc.querySelector('input[type=password]')) return true;
  if (/\/(login|signin|sign-in|auth)(\/|\?|$)/i.test(String(url || ''))) return true;
  return !!doc.querySelector('form[action*="login" i], form[action*="signin" i]');
}

async function fetchDoc(url) {
  const r = await fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'EON' } });
  if (r.status === 401 || r.status === 419) throw signInError();
  if (!r.ok) throw new Error(`${url} → ${r.status}`);
  const html = await r.text();
  const doc = new DOMParser().parseFromString(html, 'text/html');
  if (looksLikeLogin(doc, r.url)) throw signInError();
  return doc;
}

/** one control of an ERP form, described the way the ERP declares it */
function readField(el, nameOverride, labelOverride) {
  const name = nameOverride || el.getAttribute('name');
  if (!name) return null;
  const label = labelOverride
    || (el.closest('.form-group,.mb-3,div')?.querySelector('label')?.textContent || '').trim().replace(/\s+/g, ' ');
  const f = {
    name,
    type: (el.tagName === 'SELECT' ? 'select' : el.tagName === 'TEXTAREA' ? 'textarea' : (el.getAttribute('type') || 'text')).toLowerCase(),
    label: label || name.replace(/[_\[\]]/g, ' ').trim(),
    required: el.hasAttribute('required'),
    value: el.getAttribute('value') || '',
  };
  if (el.tagName === 'SELECT') f.options = [...el.querySelectorAll('option')].map((o) => ({ value: o.value, text: o.textContent.trim() })).filter((o) => o.value !== '');
  return f;
}

/** everything a <form> declares: where it posts, its token, its fields */
function readForm(doc, form, pageUri) {
  const token = (form.querySelector('input[name=_token]') || {}).value
    || (doc.querySelector('meta[name=csrf-token]') || {}).content || '';
  const fields = [];
  form.querySelectorAll('input,select,textarea').forEach((el) => {
    const name = el.getAttribute('name');
    if (!name || /^_(token|method)$/.test(name)) return;
    const f = readField(el);
    if (f && !fields.some((x) => x.name === name)) fields.push(f);
  });
  return {
    action: form.getAttribute('action') || origin() + pageUri,
    method: (form.querySelector('input[name=_method]') || {}).value || 'POST',
    token, fields, from: pageUri,
  };
}

/** read a real ERP form: its action, its token, and every field it declares */
export async function discover(pageUri) {
  if (forms.has(pageUri)) return forms.get(pageUri);
  const doc = await fetchDoc(origin() + pageUri);
  // the form that posts somewhere, not a search box
  const form = [...doc.querySelectorAll('form')]
    .filter((f) => (f.getAttribute('method') || 'get').toLowerCase() === 'post')
    .sort((a, b) => b.querySelectorAll('input,select,textarea').length - a.querySelectorAll('input,select,textarea').length)[0];
  if (!form) throw new Error('no form on ' + pageUri);

  const out = readForm(doc, form, pageUri);
  forms.set(pageUri, out);
  return out;
}

/** post a filled form back to the ERP, exactly as the browser would */
export async function submit(plan) {
  const body = new FormData();
  body.append('_token', plan.form.token);
  if (plan.form.method && plan.form.method.toUpperCase() !== 'POST') body.append('_method', plan.form.method);
  Object.entries(plan.values).forEach(([k, v]) => body.append(k, v == null ? '' : String(v)));

  const r = await fetch(plan.form.action, {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'X-CSRF-TOKEN': plan.form.token, 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json, text/html' },
    body,
    redirect: 'follow',
  });

  const text = await r.text();
  let json = null;
  try { json = JSON.parse(text); } catch {}

  // Laravel answers a rejected form with 422 and a message per field
  if (r.status === 422 && json && json.errors) {
    const errs = Object.entries(json.errors).map(([f, m]) => `${f}: ${[].concat(m).join(', ')}`);
    return { ok: false, status: r.status, errors: errs, message: 'The ERP rejected it: ' + errs.join(' · ') };
  }
  if (!r.ok) return { ok: false, status: r.status, message: `The ERP refused (${r.status}). Nothing was saved.` };

  /* A 200 is not proof. Two of the ERP's own payment endpoints answer a refusal
     with a perfectly ordinary 200:
       · PartyStatementController::recordPayment returns {success:false, message}
         when the bank has no chart-of-accounts account behind it;
       · PaymentScheduleController::markPaid returns back()->with('error', …)
         for a schedule already paid or cancelled — a redirect to an HTML page.
     Reporting either as "Saved in the ERP" would be a lie about money, so read
     what the ERP actually said before claiming anything. */
  if (json && json.success === false) {
    const why = typeof json.message === 'string' && json.message ? json.message : 'The ERP declined it.';
    return { ok: false, status: r.status, errors: [why], message: 'The ERP did not accept it: ' + why };
  }
  const flash = readFlash(text);
  if (flash && flash.kind === 'error') {
    return { ok: false, status: r.status, errors: [flash.text], message: 'The ERP did not accept it: ' + flash.text };
  }

  const said = (json && json.message) || (flash && flash.kind === 'success' ? flash.text : '') || '';
  return { ok: true, status: r.status, message: typeof said === 'string' && said ? said : 'Saved in the ERP.', data: json };
}

/* The ERP's layout renders a flash message as a fixed toast whose colour is the
   verdict — bg-green-600 for session('success'), bg-red-600 for session('error')
   (layout/app.blade.php). It is the only thing a redirect-following POST gets
   back, so it is how EON learns whether a form post was really accepted. */
function readFlash(html) {
  const m = String(html || '').match(/class="[^"]*\bbg-(green|red)-600\b[^"]*"\s*>\s*([^<]{1,300}?)\s*</);
  if (!m) return null;
  return { kind: m[1] === 'green' ? 'success' : 'error', text: m[2].replace(/\s+/g, ' ').trim() };
}

/* ---------- turning a sentence into a plan ---------- */
const pick = (fields, ...names) => fields.find((f) => names.some((n) => f.name === n || f.name.startsWith(n + '[') || new RegExp(`^${n}$`, 'i').test(f.name)));
const guess = (fields, re) => fields.find((f) => re.test(f.name) || re.test(f.label));

function optionFor(field, text) {
  if (!field || !field.options || !text) return null;
  const t = String(text).toLowerCase();
  const exact = field.options.find((o) => o.text.toLowerCase() === t || String(o.value).toLowerCase() === t);
  if (exact) return exact.value;
  const part = field.options.find((o) => o.text.toLowerCase().includes(t) || t.includes(o.text.toLowerCase()));
  return part ? part.value : null;
}

/** the screen that creates a thing of this kind */
function createPage(what) {
  const N = nav(); if (!N) return null;
  const map = N.map(); if (!map) return null;
  const want = { task: 'tasks', notice: 'notices', message: 'chat', payslip: 'payslips', payment: 'party-statement' }[what] || what;
  const page = map.pages.find((p) => p.name === `role.${want}.create`) || map.pages.find((p) => p.name === `role.${want}.index`);
  return page ? N.url(page.uri) : null;
}

/* ---------- the actions EON can carry out ---------- */
export async function planTask(person, title, opts = {}) {
  // the sentence's question mark is not part of the task's name — "can you assign
  // Imran a task: check the ledger?" must not put "check the ledger?" on the board.
  // Here rather than at the parse, because the understander plans tasks too.
  title = cleanTitle(title);
  const N = nav(); if (!N) throw new Error('the ERP map is not loaded');
  const page = createPage('task');
  if (!page) throw new Error('this ERP has no task screen');
  const form = await discover(page.replace(origin(), ''));
  const f = form.fields;
  const values = {};
  const titleF = pick(f, 'title', 'name', 'task') || guess(f, /title|subject|task/i);
  if (titleF) values[titleF.name] = title;
  const assignee = pick(f, 'assigned_to', 'assignee', 'user_id', 'employee_id') || guess(f, /assign|employee|user/i);
  if (assignee && person) {
    const v = optionFor(assignee, person.name) || (assignee.options ? null : person.id);
    if (v != null) values[assignee.name + (assignee.name.endsWith('[]') ? '' : '')] = v;
  }
  const due = pick(f, 'due_date', 'deadline', 'end_date') || guess(f, /due|deadline/i);
  if (due && opts.due) values[due.name] = opts.due;
  const prio = pick(f, 'priority') || guess(f, /priority/i);
  if (prio) values[prio.name] = optionFor(prio, opts.priority || 'medium') || (prio.options ? prio.options[0].value : 'medium');
  const desc = guess(f, /description|detail|note/i);
  if (desc) values[desc.name] = opts.note || title;
  const company = pick(f, 'company_id') || guess(f, /company/i);
  if (company && opts.company) values[company.name] = optionFor(company, opts.companyName || '') || opts.company;

  return { what: 'task', form, values, missing: f.filter((x) => x.required && values[x.name] == null).map((x) => x.label || x.name),
    summary: `Create a task “${title}”${person ? ` for ${person.name}` : ''}${opts.due ? `, due ${opts.due}` : ''}` };
}

/** the CSRF token a document carries, wherever the ERP put it */
const tokenIn = (doc) => (doc && ((doc.querySelector('meta[name=csrf-token]') || {}).content
  || (doc.querySelector('input[name=_token]') || {}).value)) || '';

export async function planMessage(person, text) {
  const N = nav(); if (!N) throw new Error('the ERP map is not loaded');
  const map = N.map();
  const route = map.routes.find((r) => r.name === 'role.chat.send');
  if (!route) throw new Error('this ERP has no messaging endpoint');
  /* N.url() already carries the origin — prefixing it again produced
     "http://hosthttp://host/…", which fetch refuses to parse, so every message
     died before it was ever proposed. */
  const chat = map.pages.find((p) => /chat/.test(p.name));
  let token = '';
  if (chat) {
    // the chat screen is a JSON endpoint in this ERP, so it may carry no token at all
    try { token = tokenIn(await fetchDoc(N.url(chat.uri))); } catch (e) { if (e && e.signIn) throw e; }
  }
  if (!token) token = tokenIn(typeof document !== 'undefined' ? document : null);
  return {
    what: 'message',
    form: { action: N.url(route.uri), method: 'POST', token, fields: [], from: route.uri },
    values: { message: text, body: text, receiver_id: person ? person.id : '', user_id: person ? person.id : '' },
    missing: (person ? [] : ['who to send it to']).concat(token ? [] : ['the security token — this page did not give me one']),
    summary: `Send ${person ? person.name : 'someone'} the message “${text}”`,
  };
}

export async function planNotice(title, body) {
  const page = createPage('notice');
  if (!page) throw new Error('this ERP has no notice screen');
  const form = await discover(page.replace(origin(), ''));
  const f = form.fields, values = {};
  const t = pick(f, 'title', 'subject') || guess(f, /title|subject/i);
  if (t) values[t.name] = title;
  const b = guess(f, /body|description|content|detail|message/i);
  if (b) values[b.name] = body;
  const d = guess(f, /date|publish/i);
  if (d && d.type === 'date') values[d.name] = (D0()?.meta?.today) || new Date().toISOString().slice(0, 10);
  return { what: 'notice', form, values, missing: f.filter((x) => x.required && values[x.name] == null).map((x) => x.label || x.name),
    summary: `Publish a notice “${title}”` };
}

/* ============================================================
   MONEY — through the ERP's own payment screens, never around them.

   Two surfaces, chosen from what the controllers actually do:

   1. An employee's salary → role.payment-schedules.mark-paid
      (PATCH /{role}/payment-schedules/{schedule}/mark-paid).
      This is the route behind the ERP's own "Record Payment" button on the
      salary sheet. PaymentScheduleController::markPaid validates
        payment_date   required|date|before_or_equal:today
        payment_method nullable|string
        bank_id        required|exists:banks,id
        note           nullable|string|max:500
        paid_amount    nullable|numeric|min:0.01
        remainder_date nullable|date
      and routes an EmployeeSalary/SalaryReconciliation schedule into
      settleEmployeeSchedule() — which is what posts the journal, moves the
      bank balance, and closes or re-schedules the remainder. The alternative,
      employee-salaries.update, only *edits the salary row* and flips its
      status; it takes gross/net/template/date and rewrites the accrual
      journal. Editing a record is not paying a person, so EON does not use it
      to move money.
      The schedule id is not something EON may invent: it is read from the
      ERP's own button on the sheet —
        <button class="… pay-item-btn" data-schedule_id data-employee_name
                data-due_amount data-scheduled_date>
      so if that month's sheet shows no open schedule for the person, there is
      nothing to pay and EON says so instead of posting anything.

   2. A supplier / customer / agent → role.party-statement.payment
      (POST /{role}/party-statement/payment).
      PartyStatementController::recordPayment validates
        party_id       required|exists:users,id
        amount         required|numeric|min:0.01
        payment_method required|string
        payment_date   required|date
      and also reads bank_id, reference_no and notes. It writes the party
      Transaction and, when a bank is named, the matching journal against
      AR (customer) or AP (supplier). payment-schedules.store is not the
      alternative: it only *schedules* money against an existing sale or
      purchase (schedulable_type required|in:sale,purchase,ticket_sale,
      ticket_purchase, status 'pending') — a promise, not a payment.

   The party screen is not a <form>: the modal's controls carry ids, no names,
   and the page's own submitPayment() posts them as party_id / amount /
   payment_method / bank_id / payment_date / reference_no / notes. EON reads
   that modal for the bank list and the CSRF token and sends exactly the same
   field names — the ERP's fields, from the ERP's screen.
   ============================================================ */

const pad2 = (n) => String(n).padStart(2, '0');
const today = () => (D0()?.meta?.today) || new Date().toISOString().slice(0, 10);
const nameTokens = (s) => String(s || '').toLowerCase().replace(/[^a-z0-9 ]+/g, ' ').split(/\s+/).filter((w) => w.length >= 3);
/** the same human, written two ways — the ERP's spelling against EON's */
function samePerson(a, b) {
  const x = nameTokens(a), y = nameTokens(b);
  if (!x.length || !y.length) return false;
  return x.filter((w) => y.includes(w)).length >= Math.min(2, Math.min(x.length, y.length));
}

/** the one bank the boss meant — never a guess between several */
function chooseAccount(field, wanted) {
  if (!field) return { value: null, why: null };
  if (wanted) {
    const v = optionFor(field, wanted);
    if (v != null) return { value: v, why: null };
    return { value: null, why: `I could not find an account called “${wanted}” on that form.` };
  }
  const opts = field.options || [];
  if (opts.length === 1) return { value: opts[0].value, why: null };   // no ambiguity to resolve
  return { value: null, why: opts.length ? `Which account should it come from? ${opts.map((o) => o.text).join(' · ')}` : null };
}

/** the salary sheet for one month, and the ERP's own pay button on it */
async function salarySchedule(person, month, year) {
  const N = nav(); if (!N) throw new Error('the ERP map is not loaded');
  const map = N.map(); if (!map) throw new Error('the ERP map is not loaded');
  const sheet = map.pages.find((p) => p.name === 'role.employee-salaries.index');
  if (!sheet) throw new Error('this ERP has no salary sheet');
  const period = `${year}-${pad2(month)}`;
  const uri = N.url(sheet.uri) + '?date=' + period;
  const doc = await fetchDoc(uri);

  const buttons = [...doc.querySelectorAll('.pay-item-btn')];
  const hit = buttons.find((b) => samePerson(b.getAttribute('data-employee_name'), person.name));
  if (!hit) {
    const listed = buttons.length;
    throw new Error(listed
      ? `${period}'s salary sheet has ${listed} payment${listed === 1 ? '' : 's'} still open, but none of them is ${person.name} — that month is either already paid or not generated for them`
      : `${period}'s salary sheet has no open payment at all, so there is nothing of ${person.name}'s to pay`);
  }
  const formEl = doc.querySelector('#markPaidForm');
  if (!formEl) throw new Error('the salary sheet has no payment form on it');
  const form = readForm(doc, formEl, sheet.uri);
  // the blade prints an absolute route with REPLACE_ID standing in for the schedule
  const tmpl = formEl.getAttribute('data-url-template') || '';
  if (!tmpl) throw new Error('the payment form does not say where it posts');
  form.action = N.url(tmpl.replace(origin(), '')).replace('REPLACE_ID', hit.getAttribute('data-schedule_id'));
  return {
    form,
    scheduleId: hit.getAttribute('data-schedule_id'),
    due: parseFloat(hit.getAttribute('data-due_amount')) || 0,
    scheduledDate: hit.getAttribute('data-scheduled_date') || '',
    erpName: hit.getAttribute('data-employee_name') || person.name,
    period,
  };
}

/* The party payment modal carries ids, not names. This is the mapping the
   page's own submitPayment() uses, with `required` taken from the controller's
   validate() rather than the markup (the markup validates in JS). */
const PARTY_CONTROLS = [
  { id: 'pmAmount', name: 'amount', label: 'Amount (৳)', required: true },
  { id: 'pmMode', name: 'payment_method', label: 'Mode', required: true },
  { id: 'pmBank', name: 'bank_id', label: 'Bank Account', required: false },
  { id: 'pmDate', name: 'payment_date', label: 'Payment Date', required: true },
  { id: 'pmRef', name: 'reference_no', label: 'Reference No. / Cheque / TXN ID', required: false },
  { id: 'pmNotes', name: 'notes', label: 'Notes', required: false },
];

/** the party statement's payment modal, read as if it declared itself a form */
async function partyPaymentForm() {
  const N = nav(); if (!N) throw new Error('the ERP map is not loaded');
  const map = N.map(); if (!map) throw new Error('the ERP map is not loaded');
  const page = map.pages.find((p) => p.name === 'role.party-statement.index');
  const route = map.routes.find((r) => r.name === 'role.party-statement.payment');
  if (!page || !route) throw new Error('this ERP has no party payment screen');
  const doc = await fetchDoc(N.url(page.uri));
  const modal = doc.querySelector('#paymentModal');
  if (!modal) throw new Error('the party statement has no payment modal on it');

  const fields = [{ name: 'party_id', type: 'hidden', label: 'Party', required: true }];
  PARTY_CONTROLS.forEach((c) => {
    const el = modal.querySelector('#' + c.id);
    if (!el) return;
    const f = readField(el, c.name, c.label);
    if (f) { f.required = c.required; fields.push(f); }
  });
  const token = (doc.querySelector('meta[name=csrf-token]') || {}).content
    || (doc.querySelector('input[name=_token]') || {}).value || '';
  return { action: N.url(route.uri), method: 'POST', token, fields, from: page.uri };
}

/** who the ERP thinks this party is — its own search endpoint, not a guess */
async function findParty(name) {
  const N = nav(); if (!N) return null;
  const route = (N.map()?.routes || []).find((r) => r.name === 'role.party-statement.search');
  if (!route) return null;
  const r = await fetch(N.url(route.uri) + '?q=' + encodeURIComponent(name), {
    credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
  });
  if (!r.ok) return null;
  const list = await r.json().catch(() => null);
  if (!Array.isArray(list) || !list.length) return null;
  return list.find((p) => samePerson(p.name, name)) || list.find((p) => String(p.name || '').toLowerCase() === String(name).toLowerCase()) || list[0];
}

/**
 * Pay someone, through whichever of the ERP's payment screens is the right one.
 *
 *   who   { kind:'employee'|'party', name, id? }   — required
 *   opts  { month, year, amount, bank, method, note, date }
 *
 * who and amount are always required; a salary also needs the month, because a
 * salary payment settles one month's schedule and the ERP has no such thing as
 * a month-less salary. A party payment has no month field on the form at all —
 * if a month is given it is carried into the note, where the ERP keeps it.
 *
 * Nothing is ever posted from here. The plan is marked `money`, which keeps the
 * confirmation gate shut until the boss says yes out loud.
 */
export async function planPayment(who, opts = {}) {
  if (!who || !who.name) throw new Error('I need to know who to pay before I touch a payment form');
  const amount = Number(opts.amount);
  if (!amount || !(amount > 0)) throw new Error(`I will not post a payment to ${who.name} without an amount`);

  const isEmployee = who.kind === 'employee';
  if (isEmployee && !opts.month) throw new Error(`Which month of ${who.name}'s salary? A salary payment settles one month, and I will not pick it for you`);

  const now = today();
  const nowYear = +now.slice(0, 4), nowMonth = +now.slice(5, 7);
  // Payroll is paid in arrears: asking in August to pay December means last December.
  const year = opts.year || (opts.month && +opts.month > nowMonth ? nowYear - 1 : nowYear);
  const date = opts.date || now;

  if (isEmployee) {
    const sch = await salarySchedule(who, opts.month, year);
    const f = sch.form.fields;
    const values = {};
    const dateF = pick(f, 'payment_date'); if (dateF) values[dateF.name] = date;
    const methodF = pick(f, 'payment_method');
    if (methodF) values[methodF.name] = optionFor(methodF, opts.method || 'bank_transfer') || (methodF.options?.[0]?.value ?? '');
    const bankF = pick(f, 'bank_id');
    const acct = chooseAccount(bankF, opts.bank);
    if (bankF && acct.value != null) values[bankF.name] = acct.value;
    const amtF = pick(f, 'paid_amount'); if (amtF) values[amtF.name] = amount;
    const remF = pick(f, 'remainder_date');
    if (remF && amount < sch.due) values[remF.name] = sch.scheduledDate || date;
    const noteF = pick(f, 'note');
    if (noteF) values[noteF.name] = opts.note || `Salary ${sch.period} — paid through EON`;

    const missing = f.filter((x) => x.required && (values[x.name] == null || values[x.name] === '')).map((x) => x.label || x.name);
    const notes = [`The ERP has ৳${sch.due.toLocaleString('en-US')} open on this schedule (#${sch.scheduleId}).`];
    if (amount < sch.due - 0.001) notes.push(`৳${amount.toLocaleString('en-US')} is a partial payment; the ERP will re-schedule the remaining ৳${(sch.due - amount).toLocaleString('en-US')}.`);
    if (amount > sch.due + 0.001) notes.push(`You said ৳${amount.toLocaleString('en-US')}, which is more than is open — the ERP caps a settlement at the amount due, so ৳${sch.due.toLocaleString('en-US')} would leave the account.`);
    if (acct.why) notes.push(acct.why);

    return {
      what: 'payment', money: true, form: sch.form, values, missing, notes,
      // markPaid has bank_id => required: without it there is nothing to propose, only a question
      needsAccount: !!bankF && acct.value == null,
      accounts: (bankF && bankF.options) || [],
      summary: `Pay ${sch.erpName} ৳${amount.toLocaleString('en-US')} against the ${sch.period} salary schedule`,
    };
  }

  // a supplier / customer / agent in the party ledger
  const form = await partyPaymentForm();
  const party = who.id ? { id: who.id, name: who.name } : await findParty(who.name);
  if (!party) throw new Error(`the ERP's party search does not know anyone called “${who.name}”, so there is no ledger to pay against`);
  const f = form.fields;
  const values = { party_id: party.id, amount };
  const methodF = pick(f, 'payment_method');
  if (methodF) values[methodF.name] = optionFor(methodF, opts.method || 'bank_transfer') || (methodF.options?.[0]?.value ?? 'bank_transfer');
  const dateF = pick(f, 'payment_date'); if (dateF) values[dateF.name] = date;
  const bankF = pick(f, 'bank_id');
  const acct = chooseAccount(bankF, opts.bank);
  if (bankF && acct.value != null) values[bankF.name] = acct.value;
  const noteF = pick(f, 'notes');
  const period = opts.month ? ` (${year}-${pad2(opts.month)})` : '';
  if (noteF) values[noteF.name] = opts.note || `Payment${period} — recorded through EON`;

  const missing = f.filter((x) => x.required && (values[x.name] == null || values[x.name] === '')).map((x) => x.label || x.name);
  const notes = [];
  // recordPayment writes the journal only when a bank is named — say so rather than let it pass quietly
  if (bankF && acct.value == null) notes.push('Without an account the ERP records the ledger entry but posts no journal and moves no bank balance.');
  if (acct.why) notes.push(acct.why);

  return {
    what: 'payment', money: true, form, values, missing, notes,
    // recordPayment leaves bank_id optional, so a party payment is never blocked on it
    needsAccount: false,
    accounts: (bankF && bankF.options) || [],
    summary: `Pay ${party.name} ৳${amount.toLocaleString('en-US')} and record it on their party statement`,
  };
}

/* ============================================================
   THE CONFIRMATION GATE — and the one preference that opens it.

   Money and deletions always stop for a yes. Nothing the boss can say
   turns that off: `EON_PREFS.autoConfirm` is read against a whitelist of
   three low-risk creates, so a new kind of action is confirmed by
   default and only becomes automatic when someone puts it on this list
   deliberately.

     window.EON_PREFS.autoConfirm = true   → a task, a message or a
     notice is carried out at once and EON reports what it did.

   Even then EON refuses to act on a half-filled form: if the ERP is
   still insisting on a field, the boss is asked for it rather than
   having something incomplete posted in his name.
   ============================================================ */
let pending = null;

const JUST_DO_IT = ['task', 'message', 'notice'];   // never money, never a deletion
function autoConfirms(plan) {
  const prefs = (typeof window !== 'undefined' && window.EON_PREFS) || {};
  if (prefs.autoConfirm !== true) return false;
  if (plan.money) return false;
  if (!JUST_DO_IT.includes(plan.what)) return false;
  return !(plan.missing || []).length;
}

/** the plan's own words, in the language the boss used */
const isBn = (plan) => plan && plan.lang === 'bn';
const summaryOf = (plan) => (isBn(plan) && plan.summary_bn) || plan.summary;

function card(plan) {
  const rows = Object.entries(plan.values).filter(([, v]) => v !== '' && v != null);
  const named = rows.map(([k, v]) => {
    const f = (plan.form.fields || []).find((x) => x.name === k);
    const label = f ? (f.label || k) : k;
    const shown = f && f.options ? (f.options.find((o) => String(o.value) === String(v)) || {}).text || v : v;
    return `${label}: ${shown}`;
  });
  const detail = named
    .concat(plan.notes || [])
    .concat(plan.missing.length ? [`The form also insists on: ${plan.missing.join(', ')} — tell me and I will fill it.`] : []);

  /* Money is different. A payment the ERP would reject anyway must not be
     offered as a yes/no — EON asks for the missing part instead, so "yes"
     never means "post something incomplete and see what happens". */
  const blocked = plan.money && plan.missing.length;
  const bn = isBn(plan), sum = summaryOf(plan);
  return {
    speak: blocked
      ? (bn
        ? `${sum} — কিন্তু ${plan.missing.join(' আর ')} ছাড়া ERP এটা নেবে না। ওটা বলুন, পুরো পেমেন্টটা আবার দেখিয়ে দিচ্ছি।`
        : `${sum} — but the ERP will not take it without ${plan.missing.join(' and ')}. Tell me that and I will show you the payment again.`)
      : bn
        ? `${sum}। ${plan.money ? 'কোনো অ্যাকাউন্ট থেকে এখনো টাকা যায়নি' : 'এখনো কিছু সেভ হয়নি'}। “হ্যাঁ” বললে ERP-র নিজের ফর্ম দিয়েই বসিয়ে দিচ্ছি।`
        : `${sum}. ${plan.money ? 'Nothing has left any account yet' : 'Nothing is saved yet'}. Say “yes” and I will post it through the ERP's own form.`,
    detail,
    actions: (blocked ? [] : [{ label: bn ? 'হ্যাঁ, করে দাও' : plan.money ? 'Yes, pay it' : 'Yes, do it', kind: 'eon-confirm' }])
      .concat([{ label: bn ? 'ফর্মটা খুলে দাও' : 'Open the form instead', kind: 'erp-open', href: plan.form.action.replace(/\?.*$/, '') }]),
  };
}

/**
 * Show the plan and hold it at the gate.
 *
 * Returns the card synchronously, exactly as it always has. Only when the boss
 * has "just do it" on *and* the plan is one of the three low-risk creates does
 * it return a promise instead — of the answer EON gives after doing the work.
 * Callers that await are right either way; callers that do not are unaffected
 * unless the preference is on, which is the case where waiting is the point.
 */
export function propose(plan) {
  // the gate is the proposal itself: only a plan that has been shown can be confirmed
  pending = (plan.money && plan.missing.length) ? null : Object.assign({}, plan, { proposedAt: Date.now() });
  if (pending && autoConfirms(plan)) return carryOut(plan);
  return card(plan);
}

/** the boss said "do these without asking" — so do it, and say what was done */
async function carryOut(plan) {
  const done = await confirm();
  const why = isBn(plan)
    ? 'আপনি “জিজ্ঞেস কোরো না” চালু রেখেছেন, তাই কাজ-মেসেজ-নোটিশ আমি নিজেই করে দিই। টাকা আর মুছে ফেলা সবসময় আপনার হ্যাঁ-র অপেক্ষায় থাকে।'
    : 'You have “just do it” on, so tasks, messages and notices go straight in. Money and deletions still wait for your yes.';
  return Object.assign({}, done, { detail: [].concat(done.detail || [], [why]) });
}
export function pendingPlan() { return pending; }

export async function confirm() {
  if (!pending) return { speak: 'There is nothing waiting for a yes.', detail: [] };
  const plan = pending; pending = null;
  // belt and braces: money only ever moves from a plan the boss was shown first
  if (plan.money && !plan.proposedAt) return { speak: 'That payment was never put in front of you, so I will not post it.', detail: [] };
  try {
    const res = await submit(plan);
    if (!res.ok) return { speak: res.message, detail: res.errors || [], actions: [{ label: 'Open the form', kind: 'erp-open', href: plan.form.action }] };
    // the list screen will show it — offer to go and look
    const N = nav();
    // a payment lands back on the screen it was read from, not on a search for the word "payment"
    const home = plan.what === 'payment' && plan.form.from ? { uri: plan.form.from } : null;
    const list = home || (N ? (N.find(plan.what === 'task' ? 'tasks' : plan.what === 'notice' ? 'notices' : plan.what, 1)[0]) : null);
    const sum = summaryOf(plan);
    return {
      speak: isBn(plan)
        ? `হয়ে গেছে — ${sum}। ${res.message}`
        : `Done — ${sum.charAt(0).toLowerCase() + sum.slice(1)}. ${res.message}`,
      detail: [],
      actions: list ? [{ label: isBn(plan) ? 'ERP-তে দেখুন' : 'See it in the ERP', kind: 'erp-open', href: N.url(list.uri) }] : [],
    };
  } catch (e) {
    if (e && e.signIn) return { speak: signInSpeak(isBn(plan)), detail: [] };
    return { speak: `I could not complete it: ${e.message}. Nothing was saved.`, detail: [] };
  }
}

/** the one honest sentence for a screen that wanted a password instead */
function signInSpeak(bn) {
  return bn
    /* the English cause was being spliced into the Bangla sentence and then
       explained again in Bangla — one sentence, one language */
    ? 'আমি কিছুই তৈরি করিনি — ইআরপি ফর্মটা দেখানোর আগে সাইন ইন চেয়েছে। এই ব্রাউজারে ইআরপিতে লগইন করে আবার বলুন।'
    : `I have created nothing: ${SIGN_IN}. Sign in to the ERP in this browser and ask me again.`;
}

/* ---------- language ---------- */
/* \b is defined on [A-Za-z0-9_], so it never fires after a Bangla letter:
   /হ্যাঁ\b/ matches nothing at all, and the boss's "হ্যাঁ" fell through every
   domain to no answer while a plan sat waiting for it. Bangla words carry a
   not-another-Bangla-letter guard instead, so "না" cancels but "নামগুলো
   দেখাও" does not. */
/* And a yes has to be the whole answer, not the first word of one. Matching a
   prefix meant "ok so how much cash do we have?" — a follow-up question —
   opened the gate and posted the plan waiting behind it; with a payment in the
   queue that is the gate failing at precisely the job it exists for. So the
   affirmation must be the sentence: the word, an optional polite tail, and
   nothing after it. "no idea, what does the ledger say?" likewise stops
   throwing the plan away behind the boss's back. */
const YES_WORD = String.raw`yes|yeah|yep|yup|ok|okay|sure|confirm|proceed|do it|go ahead|please do`;
const YES_TAIL = String.raw`(?:\s*,?\s*(?:please|do it|go ahead|post it|send it|create it|pay it|confirm|now|then))*`;
const NO_WORD = String.raw`no|nope|cancel|stop|don'?t|do not|never mind|nevermind|not now|leave it`;
const NO_TAIL = String.raw`(?:\s*,?\s*(?:thanks|thank you|please|for now|now))*`;
const BN_YES_WORD = String.raw`হ্যাঁ|হ্যা|জ্বি|জি|আচ্ছা|ঠিক আছে|করে দাও|করো|কর|দাও|ওকে`;
const BN_YES_TAIL = String.raw`(?:\s*,?\s*(?:করে দাও|করে দিন|করো|দাও|দিন|প্লিজ|এখনই))*`;
const BN_NO_WORD = String.raw`নাহ|না|বাদ দাও|বাদ|থাক|বাতিল|লাগবে না`;
const BN_NO_TAIL = String.raw`(?:\s*,?\s*(?:ধন্যবাদ|প্লিজ|এখন))*`;
const WHOLE = String.raw`\s*[।.!…]*\s*$`;
const YES = new RegExp(String.raw`^\s*(?:(?:${YES_WORD})(?![A-Za-z])${YES_TAIL}|(?:${BN_YES_WORD})(?![ঀ-৿])${BN_YES_TAIL})${WHOLE}`, 'i');
const NO = new RegExp(String.raw`^\s*(?:(?:${NO_WORD})(?![A-Za-z])${NO_TAIL}|(?:${BN_NO_WORD})(?![ঀ-৿])${BN_NO_TAIL})${WHOLE}`, 'i');
const ASSIGN = /^\s*(?:eon[,\s]+)?(?:assign|give)\s+(.+?)\s+(?:a\s+)?(?:task|job|work)\b\s*(?:[,:—-]\s*)?(?:the task is\s*)?(.*)$/i;
const MESSAGE = /^\s*(?:eon[,\s]+)?(?:message|msg|write to|tell|send)\s+([A-Za-z][A-Za-z. ]{2,40}?)\s*[,:—-]\s*(.+)$/i;
const NOTICE = /^\s*(?:eon[,\s]+)?(?:write|publish|post|draft)\s+a?\s*notice\b[\s:,-]*(.*)$/i;
const BN = /[ঀ-৿]/;
/* "ইমরানকে টাস্ক দাও: লেজার চেক করো" — an order, not a question about tasks.
   দাও/দিন/দে must not be allowed to match inside দেখাও ("show me the tasks"),
   which is why the verb is followed by a not-a-Bangla-letter guard. */
const BN_ASSIGN = /^\s*(?:eon[,\s]*)?(\S[^]{0,40}?)\s*(?:একটা|একটি)?\s*(?:টাস্ক|কাজ|task)\s*(?:দাও|দিন|দে)(?![ঀ-৿])\s*[:—–\-,]?\s*([^]*)$/i;

/* "give me a task list" and "tell me the cash position, please" are questions,
   and both fit the order shapes above: "give <who> a task" and "tell <who>:
   <what>". Answering them with "I could not find 'me the cash position' among
   the employees" is worse than not answering — act sits at priority 99, so its
   mistake is the one the boss sees. A recipient that is a pronoun or the team
   itself is nobody's name, so act stands down and the question falls through
   to the domains that can actually answer it. */
const NOT_A_PERSON = /^\s*(?:me|us|him|her|them|it|myself|everyone|everybody|anyone|all|somebody|someone)(?:\s|$)/i;

/** trailing question marks and full stops are the sentence's, not the title's */
const cleanTitle = (t) => String(t || '').trim().replace(/[?!.।\s]+$/u, '').trim();

/* ---------- a Bangla name reaching a record spelled in Latin ----------
   বাংলা writes no vowels the passport agrees with, so both sides are reduced
   to a consonant skeleton, word by word: ইমরান → m-r-n, "Imran" → m-r-n.
   Word by word matters — a flat skeleton runs across the space and borrows
   letters from the next name, which is how a search for one person lands
   confidently on another. */
const BN_CONS = {
  'ক': 'k', 'খ': 'k', 'গ': 'g', 'ঘ': 'g', 'ঙ': 'n', 'চ': 'c', 'ছ': 'c', 'জ': 'j', 'ঝ': 'j', 'ঞ': 'n',
  'ট': 't', 'ঠ': 't', 'ড': 'd', 'ঢ': 'd', 'ণ': 'n', 'ত': 't', 'থ': 't', 'দ': 'd', 'ধ': 'd', 'ন': 'n',
  'প': 'p', 'ফ': 'f', 'ব': 'b', 'ভ': 'b', 'ম': 'm', 'য': 'j', 'র': 'r', 'ল': 'l',
  'শ': 's', 'ষ': 's', 'স': 's', 'হ': 'h', 'ৎ': 't', 'ং': 'n',
};
const CASE_ENDING = /(কেই|কেও|কে|রে|দের|য়ের|এর|টা|টি)$/;
const dedupe = (s) => s.replace(/(.)\1+/g, '$1');
/* the h of a romanised digraph is spelling, not a sound: Hossain → h-s-n */
const latinSkeleton = (w) => dedupe(String(w || '').toLowerCase().replace(/[^a-z]/g, '')
  .replace(/([kgcjtdpbs])h/g, '$1').replace(/[aeiouy]/g, ''));
const banglaSkeleton = (w) => dedupe(String(w || '')
  .replace(/[‌‍]/g, '')
  .replace(/য়|য়/g, 'y').replace(/ড়|ড়/g, 'r').replace(/ঢ়|ঢ়/g, 'r')
  .split('').map((ch) => BN_CONS[ch] || '').join(''));

/** the employee a Bangla sentence names — or an honest "which of them?"
    The boss mixes scripts inside one sentence — "Imran কে টাস্ক দাও: check the
    ledger" — so a Latin word in a Bangla order is reduced by the Latin rule and
    a Bangla word by the Bangla one; otherwise the name he actually typed in
    English is the one word this never sees. */
function findEmployeeBn(D, text) {
  const wanted = [];
  String(text || '').split(/\s+/).filter(Boolean).forEach((w) => {
    const skeletons = /[ঀ-৿]/.test(w)
      ? [banglaSkeleton(w), banglaSkeleton(w.replace(CASE_ENDING, ''))]
      : [latinSkeleton(w)];
    skeletons.forEach((sk) => {
      if (sk.length >= 3 && !wanted.includes(sk)) wanted.push(sk);
    });
  });
  if (!wanted.length) return null;
  const scored = (D.employees || []).map((e) => {
    const parts = String(e.name || '').split(/\s+/).map(latinSkeleton).filter((x) => x.length >= 3);
    return { e, hits: wanted.filter((sk) => parts.includes(sk)).length };
  }).filter((x) => x.hits > 0).sort((a, b) => b.hits - a.hits);
  if (!scored.length) return null;
  // two people whose names reduce the same way: naming the wrong one is worse than asking
  if (scored.length > 1 && scored[1].hits === scored[0].hits) return { ambiguous: scored.slice(0, 4).map((x) => x.e) };
  return scored[0].e;
}

async function answer(q, ctx) {
  const s = String(q || '').trim();
  if (!s) return null;
  const D = D0(); if (!D) return null;
  const bn = BN.test(s);

  if (pending && YES.test(s)) return await confirm();
  if (pending && NO.test(s)) { pending = null; return { speak: bn ? 'থাক, কিছু সেভ করিনি।' : 'Left it alone — nothing was saved.', detail: [] }; }

  let m;
  try {
    if (bn && (m = s.match(BN_ASSIGN))) {
      const title = cleanTitle(m[2]);
      const found = findEmployeeBn(D, m[1]);
      if (found && found.ambiguous) {
        return { speak: `“${m[1].trim()}” নামে ${found.ambiguous.length} জন আছেন — ${found.ambiguous.map((e) => e.name).join(', ')}। কাকে দেবো?`, detail: [] };
      }
      if (!found) return { speak: `“${m[1].trim()}” নামের কাউকে কর্মীতালিকায় পেলাম না, তাই কিছু তৈরি করিনি।`, detail: [] };
      if (!title) return { speak: `${found.name}-কে কী কাজটা দেবো? বলুন — “${found.name} কে টাস্ক দাও: লেজার চেক করো”।`, detail: [] };
      const plan = await planTask(found, title, {});
      plan.lang = 'bn';
      plan.summary_bn = `${found.name}-কে একটা কাজ দেবো: “${title}”`;
      return await propose(plan);
    }
    if ((m = s.match(ASSIGN)) && !NOT_A_PERSON.test(m[1])) {
      const person = P.findEmployee(D, m[1]);
      const title = cleanTitle(m[2]);
      if (!title) return { speak: `What should the task say? Try “assign ${person ? person.name : m[1]} a task: check the ledger entries”.`, detail: [] };
      if (!person) return { speak: `I could not find “${m[1].trim()}” among the employees, so I have not created anything.`, detail: [] };
      return await propose(await planTask(person, title, { due: (window.EonDelegate ? (window.EonDelegate.parseDue(s) || null) : null) }));
    }
    if ((m = s.match(MESSAGE)) && !NOT_A_PERSON.test(m[1])) {
      const person = P.findEmployee(D, m[1]);
      if (!person) return { speak: `I could not find “${m[1].trim()}” among the employees.`, detail: [] };
      return await propose(await planMessage(person, m[2].trim()));
    }
    if ((m = s.match(NOTICE))) {
      const subject = cleanTitle((m[1] || '').replace(/^about\s+/i, ''));
      if (!subject) return { speak: 'What should the notice be about?', detail: [] };
      const body = `This is to inform all concerned: ${subject}.\n\nBy order of the Management.`;
      return await propose(await planNotice(subject.replace(/^./, (c) => c.toUpperCase()), body));
    }
  } catch (e) {
    // "no form on /x" is a lie when the ERP simply wanted a password first
    if (e && e.signIn) return { speak: signInSpeak(bn), detail: [] };
    return { speak: bn ? 'সেটআপ করতে পারলাম না — ইআরপির ফর্মটা পাওয়া যায়নি।' : `I could not set that up: ${e.message}`, detail: bn ? [e.message] : [] };
  }
  return null;
}

const named = (re, q) => { const m = String(q || '').match(re); return !!m && !NOT_A_PERSON.test(m[1]); };
const CLAIM = (q) => named(ASSIGN, q) || named(MESSAGE, q) || NOTICE.test(q) || (BN.test(q) && BN_ASSIGN.test(q))
  || (pending && (YES.test(q) || NO.test(q)));

if (typeof window !== 'undefined') {
  window.EonAct = { discover, submit, propose, confirm, pending: pendingPlan, planTask, planMessage, planNotice, planPayment };
  (window.__eonDomainQueue = window.__eonDomainQueue || []).push({ id: 'act', priority: 99, claims: CLAIM, answer });
  // the confirm button in the app
  const hook = () => {
    const A = window.EonApp; if (!A) return;
    const prev = A.act;
    A.act = function (kind, payload, note) {
      if (kind === 'eon-confirm') { confirm().then((r) => A.ask && A.ask(r.speak ? 'yes' : 'yes')); return; }
      return prev ? prev.apply(this, arguments) : undefined;
    };
  };
  if (window.EonApp) hook(); else window.addEventListener('eon:app-ready', hook);
}

export default { discover, submit, propose, confirm, planPayment };
