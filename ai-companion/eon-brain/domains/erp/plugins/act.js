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

async function fetchDoc(url) {
  const r = await fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'EON' } });
  if (!r.ok) throw new Error(`${url} → ${r.status}`);
  const html = await r.text();
  return new DOMParser().parseFromString(html, 'text/html');
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

  const token = (form.querySelector('input[name=_token]') || {}).value
    || (doc.querySelector('meta[name=csrf-token]') || {}).content || '';
  const fields = [];
  form.querySelectorAll('input,select,textarea').forEach((el) => {
    const name = el.getAttribute('name');
    if (!name || /^_(token|method)$/.test(name)) return;
    const label = (el.closest('.form-group,.mb-3,div')?.querySelector('label')?.textContent || '').trim().replace(/\s+/g, ' ');
    const f = {
      name,
      type: (el.tagName === 'SELECT' ? 'select' : el.tagName === 'TEXTAREA' ? 'textarea' : (el.getAttribute('type') || 'text')).toLowerCase(),
      label: label || name.replace(/[_\[\]]/g, ' ').trim(),
      required: el.hasAttribute('required'),
      value: el.getAttribute('value') || '',
    };
    if (el.tagName === 'SELECT') f.options = [...el.querySelectorAll('option')].map((o) => ({ value: o.value, text: o.textContent.trim() })).filter((o) => o.value !== '');
    if (!fields.some((x) => x.name === name)) fields.push(f);
  });

  const out = {
    action: form.getAttribute('action') || origin() + pageUri,
    method: (form.querySelector('input[name=_method]') || {}).value || 'POST',
    token, fields, from: pageUri,
  };
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

  const said = (json && (json.message || json.success)) || '';
  return { ok: true, status: r.status, message: typeof said === 'string' && said ? said : 'Saved in the ERP.', data: json };
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

export async function planMessage(person, text) {
  const N = nav(); if (!N) throw new Error('the ERP map is not loaded');
  const map = N.map();
  const route = map.routes.find((r) => r.name === 'role.chat.send');
  if (!route) throw new Error('this ERP has no messaging endpoint');
  // the chat endpoint takes JSON-ish fields; the token comes from any ERP page
  const doc = await fetchDoc(origin() + N.url(map.pages.find((p) => /chat/.test(p.name))?.uri || '/'));
  const token = (doc.querySelector('meta[name=csrf-token]') || {}).content || (doc.querySelector('input[name=_token]') || {}).value || '';
  return {
    what: 'message',
    form: { action: origin() + N.url(route.uri), method: 'POST', token, fields: [], from: route.uri },
    values: { message: text, body: text, receiver_id: person ? person.id : '', user_id: person ? person.id : '' },
    missing: person ? [] : ['who to send it to'],
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

/* ---------- the confirmation gate ---------- */
let pending = null;

function card(plan) {
  const rows = Object.entries(plan.values).filter(([, v]) => v !== '' && v != null);
  const named = rows.map(([k, v]) => {
    const f = (plan.form.fields || []).find((x) => x.name === k);
    const label = f ? (f.label || k) : k;
    const shown = f && f.options ? (f.options.find((o) => String(o.value) === String(v)) || {}).text || v : v;
    return `${label}: ${shown}`;
  });
  return {
    speak: `${plan.summary}. Shall I? Say “yes” and I will put it in the ERP.`,
    detail: named.concat(plan.missing.length ? [`The form also insists on: ${plan.missing.join(', ')} — tell me and I will fill it.`] : []),
    actions: [
      { label: 'Yes, do it', kind: 'eon-confirm' },
      { label: 'Open the form instead', kind: 'erp-open', href: plan.form.action.replace(/\?.*$/, '') },
    ],
  };
}

export function propose(plan) { pending = plan; return card(plan); }
export function pendingPlan() { return pending; }

export async function confirm() {
  if (!pending) return { speak: 'There is nothing waiting for a yes.', detail: [] };
  const plan = pending; pending = null;
  try {
    const res = await submit(plan);
    if (!res.ok) return { speak: res.message, detail: res.errors || [], actions: [{ label: 'Open the form', kind: 'erp-open', href: plan.form.action }] };
    // the list screen will show it — offer to go and look
    const N = nav();
    const list = N ? (N.find(plan.what === 'task' ? 'tasks' : plan.what === 'notice' ? 'notices' : plan.what, 1)[0]) : null;
    return {
      speak: `Done — ${plan.summary.charAt(0).toLowerCase() + plan.summary.slice(1)}. ${res.message}`,
      detail: [],
      actions: list ? [{ label: 'See it in the ERP', kind: 'erp-open', href: N.url(list.uri) }] : [],
    };
  } catch (e) {
    return { speak: `I could not complete it: ${e.message}. Nothing was saved.`, detail: [] };
  }
}

/* ---------- language ---------- */
const YES = /^\s*(yes|yeah|yep|do it|go ahead|confirm|proceed|ok|okay|please do|হ্যাঁ|করো|কর)\b/i;
const NO = /^\s*(no|cancel|stop|don'?t|never mind|না|বাদ)\b/i;
const ASSIGN = /^\s*(?:eon[,\s]+)?(?:assign|give)\s+(.+?)\s+(?:a\s+)?(?:task|job|work)\b\s*(?:[,:—-]\s*)?(?:the task is\s*)?(.*)$/i;
const MESSAGE = /^\s*(?:eon[,\s]+)?(?:message|msg|write to|tell|send)\s+([A-Za-z][A-Za-z. ]{2,40}?)\s*[,:—-]\s*(.+)$/i;
const NOTICE = /^\s*(?:eon[,\s]+)?(?:write|publish|post|draft)\s+a?\s*notice\b[\s:,-]*(.*)$/i;

async function answer(q, ctx) {
  const s = String(q || '').trim();
  if (!s) return null;
  const D = D0(); if (!D) return null;

  if (pending && YES.test(s)) return await confirm();
  if (pending && NO.test(s)) { pending = null; return { speak: 'Left it alone — nothing was saved.', detail: [] }; }

  let m;
  try {
    if ((m = s.match(ASSIGN))) {
      const person = P.findEmployee(D, m[1]);
      const title = (m[2] || '').trim();
      if (!title) return { speak: `What should the task say? Try “assign ${person ? person.name : m[1]} a task: check the ledger entries”.`, detail: [] };
      if (!person) return { speak: `I could not find “${m[1].trim()}” among the employees, so I have not created anything.`, detail: [] };
      return propose(await planTask(person, title, { due: (window.EonDelegate ? (window.EonDelegate.parseDue(s) || null) : null) }));
    }
    if ((m = s.match(MESSAGE))) {
      const person = P.findEmployee(D, m[1]);
      if (!person) return { speak: `I could not find “${m[1].trim()}” among the employees.`, detail: [] };
      return propose(await planMessage(person, m[2].trim()));
    }
    if ((m = s.match(NOTICE))) {
      const subject = (m[1] || '').replace(/^about\s+/i, '').trim();
      if (!subject) return { speak: 'What should the notice be about?', detail: [] };
      const body = `This is to inform all concerned: ${subject}.\n\nBy order of the Management.`;
      return propose(await planNotice(subject.replace(/^./, (c) => c.toUpperCase()), body));
    }
  } catch (e) {
    return { speak: `I could not set that up: ${e.message}`, detail: [] };
  }
  return null;
}

const CLAIM = (q) => ASSIGN.test(q) || MESSAGE.test(q) || NOTICE.test(q) || (pending && (YES.test(q) || NO.test(q)));

if (typeof window !== 'undefined') {
  window.EonAct = { discover, submit, propose, confirm, pending: pendingPlan, planTask, planMessage, planNotice };
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

export default { discover, submit, propose, confirm };
