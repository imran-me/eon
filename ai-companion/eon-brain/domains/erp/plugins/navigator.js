/* ============================================================
   EON · navigator — EON knows the ERP it lives in.

   Everything here comes from the ERP's own source, extracted by
   tools/erp-map.mjs: 1,376 routes, the sidebar menu the way the boss
   reads it, 245 controllers with the actions each screen can perform,
   175 models with their tables, and the 203 real tables and columns.

   So EON can answer, instantly and without a language model:

     "where is payroll"            → the menu path and the address
     "open journal entries"        → navigates there
     "take me to invoice 4821"     → opens that record
     "what can I do on leads"      → create, edit, convert, delete…
     "which table holds salaries"  → employee_salaries, and its columns
     "how do I add an employee"    → the screen, the button, the route

   Every screen lives under a {role} segment (/super-admin/payroll).
   EON fills that from the address it is running on, so a link it gives
   the accountant is the accountant's link.

   Registered at priority 97: above the answerers, but it only claims a
   question that is really about finding or opening something.
   ============================================================ */

const MAP_URL = new URL('../erp-map.json', import.meta.url).href;
let MAP = null;
let loading = null;

async function load() {
  if (MAP) return MAP;
  if (!loading) {
    loading = fetch(MAP_URL)
      .then((r) => (r.ok ? r.json() : null))
      .then((m) => { MAP = m; return m; })
      .catch(() => null);
  }
  return loading;
}
if (typeof window !== 'undefined') load();          // warm it early; it is only read on demand

/* ---------- where are we, and as whom? ---------- */
const ROLE_SEGMENTS = ['super-admin', 'admin', 'accountant', 'hr', 'agent', 'employee', 'operation', 'visa', 'crm', 'task', 'vendor', 'customer', 'travels-accountant', 'ticketing-flight'];
export function currentRole() {
  if (typeof location === 'undefined') return 'super-admin';
  const seg = location.pathname.split('/').filter(Boolean)[0];
  if (seg && ROLE_SEGMENTS.includes(seg)) return seg;
  try {
    const saved = localStorage.getItem('eon_erp_role');
    if (saved) return saved;
  } catch {}
  return 'super-admin';
}
if (typeof window !== 'undefined') {
  const r = currentRole();
  try { if (r) localStorage.setItem('eon_erp_role', r); } catch {}
}

/** the ERP's own origin — the companion runs on it; the panel sits beside it */
function erpBase() {
  if (typeof location === 'undefined') return '';
  return location.origin;
}
export function url(uri) {
  return erpBase() + String(uri || '').replace('{role}', currentRole());
}

/* ---------- matching a question to a screen ---------- */
const norm = (s) => String(s || '').toLowerCase().replace(/[^a-z0-9 ]+/g, ' ').replace(/\s+/g, ' ').trim();
/* Question words are not search terms. Without this, "what can I do on customers"
   matches "What'sApp Marketing" on the word "what", and "how do I add an employee"
   matches "Add Vendor" on "add". Intent verbs are read separately (WANT_CREATE). */
const STOP = new Set(['the', 'and', 'for', 'with', 'from', 'this', 'that', 'these', 'those', 'our', 'its',
  'what', 'which', 'where', 'when', 'who', 'whom', 'whose', 'why', 'how', 'can', 'does', 'did', 'are', 'was', 'were',
  'you', 'your', 'yours', 'let', 'get', 'got', 'see', 'show', 'find', 'open', 'goto', 'take', 'bring', 'give',
  'add', 'new', 'create', 'make', 'edit', 'want', 'need', 'please', 'now', 'here', 'there', 'about',
  'page', 'screen', 'menu', 'section', 'module', 'tab', 'button', 'link', 'system', 'erp', 'eon',
  'all', 'any', 'some', 'list', 'view', 'manage', 'into', 'onto', 'have', 'has', 'been', 'being']);
const words = (s) => norm(s).split(' ').filter((w) => w.length > 2 && !STOP.has(w));

/* The words the boss uses are not always the words on the menu: he says
   "payroll", the screen is called "Salary Manage" and the route is
   employee-salaries. Each entry adds search terms to a question. */
const ALIAS = {
  payroll: ['employee salaries', 'salary manage', 'payslip', 'salary'],
  salary: ['employee salaries', 'salary manage', 'payslip'],
  payslip: ['payslips', 'salary'],
  'chart of accounts': ['accounts', 'account'],
  coa: ['accounts'],
  ledger: ['journals', 'journal', 'accounts', 'party statement'],
  'general ledger': ['journals', 'accounts'],
  gl: ['journals', 'accounts'],
  'journal entry': ['journals'],
  voucher: ['journals'],
  receivable: ['party statement', 'customers', 'schedules'],
  payable: ['party statement', 'vendor', 'schedules', 'suppliers'],
  ar: ['party statement', 'customers'],
  ap: ['party statement', 'vendor'],
  invoice: ['sales', 'estimates', 'contract file sale'],
  bill: ['expenses', 'purchase'],
  staff: ['user management', 'employee', 'users'],
  employee: ['user', 'users', 'employee documents', 'attendance'],
  'add employee': ['add user', 'user create'],
  'new employee': ['add user', 'user create'],
  'add user': ['user create'],
  hire: ['add user', 'user create'],
  hr: ['hrm', 'user management', 'attendance', 'leave'],
  hiring: ['user', 'employee'],
  leave: ['leaves', 'leave types'],
  holiday: ['holidays'],
  attendance: ['attendances', 'attendence settings'],
  lead: ['lead manager', 'lead source', 'crm'],
  crm: ['lead manager', 'customers'],
  deal: ['lead manager', 'crm'],
  customer: ['customers', 'party statement'],
  supplier: ['vendor', 'party statement'],
  vendor: ['vendor'],
  expense: ['expenses', 'expense categories'],
  budget: ['expense budgets', 'expenses'],
  bank: ['banks', 'bank transfer', 'manage cash'],
  cash: ['manage cash', 'banks'],
  loan: ['loans'],
  advance: ['advance salaries'],
  task: ['tasks', 'boards', 'task report'],
  project: ['projects', 'project'],
  ticket: ['tickets', 'ticket purchase', 'ticket sale'],
  visa: ['visa', 'other visa services'],
  report: ['reports', 'report'],
  setting: ['settings', 'attendence settings'],
  dashboard: ['dashboard', 'company dashboard'],
  company: ['company', 'companies', 'company dashboard'],
  role: ['role', 'permission'],
  permission: ['role', 'permission'],
  notice: ['notice', 'notices'],
  todo: ['office todos', 'todo'],
};
function expand(query) {
  const nq = ' ' + norm(query) + ' ';
  const extra = [];
  for (const [k, vals] of Object.entries(ALIAS)) {
    // whole words only — 'ar' must not fire inside "are", 'hr' inside "through"
    if (nq.includes(' ' + k + ' ') || nq.includes(' ' + k + 's ')) extra.push(...vals);
  }
  return extra.length ? `${query} ${extra.join(' ')}` : query;
}

/** words that mean "the list of" vs "make a new one" */
const WANT_CREATE = /\b(add|create|new|make|register|enter)\b/;
const WANT_LIST = /\b(list|all|manage|show|see|view|open|go to|take me|where)\b/;

/* a route name reads like role.advance-salaries.index → "advance salaries index" */
const routeWords = (name) => norm(String(name || '').replace(/^role\./, '').replace(/\./g, ' ').replace(/-/g, ' '));

function score(query, item) {
  const q = words(query);
  if (!q.length) return 0;
  const hayLabel = norm(item.label || '');
  const hayRoute = routeWords(item.route || item.name || '');
  const hayUri = norm((item.uri || '').replace('{role}', ' ').replace(/[/{}]/g, ' '));
  const hay = `${hayLabel} ${hayRoute} ${hayUri}`;
  let s = 0;
  for (const w of q) {
    if (!hay.includes(w)) continue;
    s += 1;
    if (hayLabel.includes(w)) s += 1.5;                  // the menu label is what the boss says
    if (new RegExp(`\\b${w}\\b`).test(hayLabel)) s += 1;  // whole word, not a fragment
  }
  // a phrase match on the label is worth a lot
  const nq = norm(query).replace(/^(where is|where are|open|go to|take me to|show me|find)\s+/, '');
  if (hayLabel && (hayLabel === nq || hayLabel.includes(nq))) s += 4;
  // prefer the index screen unless the boss asked to create
  if (/\.index$/.test(item.route || item.name || '')) s += WANT_CREATE.test(query) ? -1 : 1.2;
  if (/\.create$/.test(item.route || item.name || '')) s += WANT_CREATE.test(query) ? 2.5 : -1;
  if (/\.(edit|show|destroy|update|store)$/.test(item.route || item.name || '')) s -= 1.5;
  return s;
}

/** the best screens for a question, menu first then every route */
export function find(query, limit = 5) {
  if (!MAP) return [];
  const expanded = expand(query);
  const routeByName = new Map();
  for (const p of MAP.pages) routeByName.set(p.name, p);

  const candidates = [];
  for (const m of MAP.menu) {
    const page = m.route ? routeByName.get(m.route) : null;
    // Some partials reuse one href for several captions ("Advance Salary" pointing at
    // role.user.index). When the label and its route share no word, the label is not to
    // be trusted for matching — the route from Laravel is ground truth.
    let label = m.label;
    if (label && m.route) {
      const lw = words(label), rw = new Set(routeWords(m.route).split(' '));
      if (lw.length && !lw.some((w) => rw.has(w) || [...rw].some((r) => r.startsWith(w) || w.startsWith(r)))) label = null;
    }
    candidates.push({
      label,
      section: m.section,
      route: m.route,
      uri: page ? page.uri : m.href || null,
      controller: page ? page.controller : null,
      action: page ? page.action : null,
      slug: m.slug || null,
      kind: m.kind || 'menu',
      group: m.group || null,
      from: 'menu',
    });
  }
  // a company-menu entry carries a slug ('payroll'); find the page whose route matches it
  for (const c of candidates) {
    if (c.uri || !c.slug) continue;
    const slug = String(c.slug).replace(/_/g, '-');
    const guess = MAP.pages.find((p) => p.name === `role.${slug}.index`)
      || MAP.pages.find((p) => new RegExp(`\brole\.${slug}(\.|$)`).test(p.name) && /\.index$/.test(p.name))
      || MAP.pages.find((p) => p.uri.split('/').includes(slug));
    if (guess) { c.uri = guess.uri; c.route = guess.name; c.controller = guess.controller; c.action = guess.action; }
  }
  for (const p of MAP.pages) {
    if (candidates.some((c) => c.route === p.name)) continue;
    candidates.push({ label: null, section: null, route: p.name, uri: p.uri, controller: p.controller, action: p.action, from: 'route' });
  }
  return candidates
    .map((c) => ({ c, direct: score(query, c) }))
    .map((x, _i, all) => {
      // an alias is a hint, not evidence: if the boss's own words matched something
      // properly anywhere, aliases are ignored altogether
      const anyDirect = all.some((y) => y.direct >= 3);
      const s = anyDirect ? x.direct : Math.max(x.direct, score(expanded, x.c) * 0.7);
      return { ...x.c, _s: s };
    })
    .filter((c) => c._s > 1.5 && c.uri)
    .sort((a, b) => b._s - a._s)
    .slice(0, limit);
}

/* ---------- what a screen can do ---------- */
const ACTION_WORDS = {
  index: 'see the list', create: 'open the “add new” form', store: 'save a new record',
  show: 'open one record', edit: 'open the edit form', update: 'save changes',
  destroy: 'delete a record', approve: 'approve', reject: 'reject', post: 'post to the ledger',
  print: 'print', download: 'download', export: 'export to a file', pdf: 'get a PDF',
  excel: 'get an Excel file', status: 'change the status', restore: 'restore', bulk: 'act on many at once',
};
export function abilities(controller) {
  if (!MAP || !controller) return [];
  const c = MAP.controllers.find((x) => x.controller === controller);
  if (!c) return [];
  const seen = new Set();
  return c.actions
    .map((a) => {
      const key = Object.keys(ACTION_WORDS).find((k) => a.toLowerCase() === k || a.toLowerCase().startsWith(k) || a.toLowerCase().endsWith(k));
      const text = key ? ACTION_WORDS[key] : a.replace(/([a-z0-9])([A-Z])/g, '$1 $2').toLowerCase();
      return { action: a, text };
    })
    .filter((x) => { if (seen.has(x.text)) return false; seen.add(x.text); return true; })
    .slice(0, 10);
}

/* ---------- which table holds what ---------- */
/* the business word for a table is not always its name: "salaries" is
   employee_salaries, "the ledger" is journal_items */
const TABLE_ALIAS = {
  salary: 'employee_salaries', salaries: 'employee_salaries', payroll: 'employee_salaries',
  payslip: 'payslips', ledger: 'journal_items', journal: 'journal_entries', voucher: 'journal_entries',
  account: 'accounts', coa: 'accounts', lead: 'leads', customer: 'customers', supplier: 'suppliers',
  expense: 'expenses', attendance: 'attendances', leave: 'leaves', loan: 'loans', task: 'tasks',
  project: 'projects', employee: 'users', staff: 'users', user: 'users', company: 'companies', bank: 'banks',
};
export function tableFor(query) {
  if (!MAP) return null;
  const q = words(query);
  if (!q.length) return null;
  const pick = (name) => MAP.tables.find((t) => t.table === name);

  // the table named in full ("journal items" → journal_items) decides it first
  const nq = norm(query);
  const spelled = MAP.tables.find((t) => nq.includes(t.table.replace(/_/g, ' ')) || nq.includes(t.table));
  if (spelled) return describeTable(spelled, q);
  // then an explicit business word
  for (const w of q) {
    const t = TABLE_ALIAS[w] && pick(TABLE_ALIAS[w]);
    if (t) return describeTable(t, q);
  }
  const scoreT = (name) => {
    const segs = name.split('_');
    let s = 0;
    for (const w of q) {
      if (name === w) s += 6;
      else if (segs.includes(w)) s += 3;                    // a whole segment: salaries in employee_salaries
      else if (segs.some((g) => g === w + 's' || g + 's' === w)) s += 2.5;
      else if (name.includes(w)) s += 1;
    }
    return s;
  };
  const ranked = MAP.tables.map((t) => ({ t, s: scoreT(t.table) })).filter((x) => x.s > 0).sort((a, b) => b.s - a.s || a.t.table.length - b.t.table.length);
  if (!ranked.length) return null;
  return describeTable(ranked[0].t, q, ranked.slice(1, 4).map((x) => x.t.table));
}
function describeTable(t, q, alts = []) {
  const model = MAP.models.find((m) => m.table === t.table);
  return {
    table: t.table,
    columns: t.columns.map((c) => c.name),
    model: model ? model.model : null,
    relations: model ? model.relations : [],
    alternatives: alts,
  };
}

/* ---------- one particular record ----------
   "open invoice 4821", "show journal 1204", "employee 206" — the ERP keeps a
   screen per record (/{role}/journals/{journal}), so with the entity and the
   number EON can open the thing itself, not just the list it lives in. */
const RECORD = /\b(?:open|show|view|find|go\s*to|take\s*me\s*to|display)?\s*(?:the\s+)?([a-z][a-z .\-]{2,28}?)\s*(?:#|no\.?|number|id)?\s*(\d{1,9})\b/i;

export function findRecord(query) {
  if (!MAP) return null;
  const m = String(query || '').match(RECORD);
  if (!m) return null;
  const entity = norm(m[1]).replace(/\b(open|show|view|find|the|go|to|take|me|display|my|a|an)\b/g, ' ').trim();
  const id = m[2];
  if (!entity) return null;

  const terms = words(entity).concat(words(expand(entity)));
  if (!terms.length) return null;

  /* the resource a screen belongs to is the first path segment:
     /{role}/expenses/{expense}/edit → "expenses". Matching the entity against
     that (not against the whole route) keeps "expense" off expense-categories. */
  const resourceOf = (uri) => (String(uri).split('/').filter((p) => p && p !== '{role}')[0] || '');
  const singular = (x) => x.replace(/ies$/, 'y').replace(/s$/, '');

  const scoreDetail = (d) => {
    const res = resourceOf(d.uri);
    const resWords = norm(res.replace(/-/g, ' '));
    const hay = routeWords(d.name) + ' ' + norm(d.uri.replace('{role}', ' ').replace(/[/{}]/g, ' '));
    let s = 0;
    for (const w of new Set(terms)) {
      if (hay.includes(w)) {
        s += 1;
        if (new RegExp(`\\b${w}s?\\b`).test(hay)) s += 1.5;
      }
      // the resource itself is the strongest signal
      if (res === w || res === w + 's' || singular(res) === singular(w)) s += 4;
      else if (resWords.split(' ').length === 1 && resWords.startsWith(w)) s += 1;
      else if (resWords.includes(w) && resWords.split(' ').length > 1) s -= 0.5;   // expense-categories for "expense"
    }
    if (/\.show$/.test(d.name)) s += 3;            // the record's own page
    else if (/\.edit$/.test(d.name)) s += 0.5;
    // a screen hanging off the record (…/{id}/status-history) is not "the record"
    const after = String(d.uri).split(`{${d.param}}`)[1] || '';
    if (after.replace(/^\//, '').length) s -= 2;
    if (/\.(destroy|update|store|print|download)$/.test(d.name)) s -= 3;
    return s;
  };
  const ranked = MAP.details.map((d) => ({ d, s: scoreDetail(d) })).sort((a, b) => b.s - a.s);
  const best = ranked[0];
  if (!best || best.s < 2.5) return null;
  // the winner must really be that entity's screen — "lead 302" has no lead page in this
  // ERP (only sub-views), and a near-miss like /accounts/302 would be worse than saying
  // nothing: fall through and answer with the screen instead.
  const res = resourceOf(best.d.uri);
  const ok = [...new Set(terms)].some((w) => res === w || res === w + 's' || singular(res) === singular(w));
  if (!ok) return null;
  return {
    id,
    entity,
    route: best.d.name,
    uri: best.d.uri.replace(`{${best.d.param}}`, id).replace(/\{[^}]*\?\}/g, ''),
    controller: best.d.controller,
  };
}

/* ---------- the answers ---------- */
const CLAIM = /\b(where\s+(is|are|can i find|do i)|how do i|how can i|open|navigate|take me|go to|show me the|which (page|screen|menu|table|section)|what can i do (on|in|with))\b/i;

function speakFor(hit) {
  const where = hit.section ? `${hit.section} → ${hit.label || hit.route}` : (hit.label || hit.route);
  return `${where} — ${url(hit.uri).replace(erpBase(), '')}`;
}

function answer(q, ctx) {
  const s = String(q || '').trim();
  if (!s) return null;
  if (!MAP) { load(); return null; }                    // not ready yet: let another domain answer

  // "open invoice 4821" — a particular record, not the list
  const rec = findRecord(s);
  if (rec && /\b(open|show|view|go\s*to|take\s*me|find|display)\b/i.test(s)) {
    const target = url(rec.uri);
    return {
      speak: `Opening ${rec.entity} ${rec.id} — ${target.replace(erpBase(), '')}`,
      detail: [`Screen: ${rec.route}`, `Address: ${target.replace(erpBase(), '')}`],
      actions: [{ label: `Open ${rec.entity} ${rec.id}`, kind: 'erp-open', href: target }],
      navigate: target,
    };
  }

  const nq = norm(s);

  // "which table holds salaries" / "where is the salary data"
  if (/\b(table|column|field|database|schema)\b/.test(nq)) {
    const t = tableFor(s.replace(/\b(which|what|table|column|field|holds?|stores?|the|is|are|in|database|schema)\b/gi, ' '));
    if (t) {
      return {
        speak: `That lives in the ${t.table} table${t.model ? `, model ${t.model}` : ''} — ${t.columns.length} columns.`,
        detail: [`Columns: ${t.columns.slice(0, 18).join(', ')}${t.columns.length > 18 ? ` … +${t.columns.length - 18}` : ''}`]
          .concat(t.relations.length ? [`Related: ${t.relations.map((r) => `${r.name} (${r.kind} ${r.model})`).slice(0, 6).join(', ')}`] : [])
          .concat((t.alternatives || []).length ? [`Could also mean: ${t.alternatives.join(', ')}`] : []),
      };
    }
  }

  // "payroll" on its own is a question about the numbers — the answerers own that.
  // This plug-in speaks only when the boss is asking where something is or to open it.
  if (!CLAIM.test(s)) return null;
  const hits = find(s, 5);
  /* No screen matched. Saying so here would be the end of the question: this
     plug-in sits at 97, above every answerer, so an apology returned from here
     is the answer the boss gets. "show me the aging" and "where is the money
     going" both read as navigation and both have a real answer one layer down.
     So it stands aside, and the apology is registered separately at the very
     bottom of the stack (`navigator-help`), where it is only reached if nobody
     else could answer either. */
  if (!hits.length) return null;

  const top = hits[0];
  const acts = abilities(top.controller);
  const wantsOpen = /\b(open|go to|take me|navigate|launch|bring up)\b/i.test(s);
  const target = url(top.uri);

  const detail = [];
  if (top.section) detail.push(`Menu: ${top.section} → ${top.label || top.route}`);
  detail.push(`Address: ${target.replace(erpBase(), '')}`);
  if (acts.length) detail.push(`On that screen you can: ${acts.map((a) => a.text).join(', ')}.`);
  if (hits.length > 1) detail.push(`Also: ${hits.slice(1, 4).map((h) => h.label || h.route).join(' · ')}`);

  return {
    speak: wantsOpen
      ? `Opening ${top.label || routeWords(top.route)} — ${speakFor(top)}`
      : `${top.label || routeWords(top.route)} is at ${speakFor(top)}.${acts.length ? ` There you can ${acts.slice(0, 3).map((a) => a.text).join(', ')}.` : ''}`,
    detail,
    actions: [{ label: `Open ${top.label || 'the screen'}`, kind: 'erp-open', href: target }]
      .concat(hits.slice(1, 3).map((h) => ({ label: h.label || routeWords(h.route), kind: 'erp-open', href: url(h.uri) }))),
    navigate: wantsOpen ? target : null,
  };
}

/* ---------- acting on it ---------- */
function go(href) {
  if (typeof window === 'undefined' || !href) return;

  // 1. inside the split workspace, EON must move the ERP frame and stay put itself.
  //    (Its own frame is never navigated — that is what keeps the thread alive.)
  try {
    if (window.parent && window.parent !== window) {
      if (window.parent.EonWorkspace && window.parent.EonWorkspace.isWorkspace) {
        window.parent.EonWorkspace.navigate(href);
        return;
      }
      // cross-document fallback: ask the shell over postMessage
      window.parent.postMessage({ type: 'eon:navigate', url: href }, location.origin);
      return;
    }
  } catch { /* not framed, or a foreign parent — fall through */ }

  // 2. riding on an ERP page (companion): just go
  const onPanel = typeof location !== 'undefined' && /\/eon(\/|$)/.test(location.pathname);
  if (!onPanel) { location.href = href; return; }

  // 3. from EON's own page, opened on its own: a new tab
  window.open(href, '_blank', 'noopener');
}

/* the ERP page the boss is looking at, when EON is docked beside it */
let erpContext = null;
if (typeof window !== 'undefined') {
  window.addEventListener('message', (e) => {
    if (e.origin !== location.origin || !e.data || e.data.type !== 'eon:erp-context') return;
    erpContext = { path: e.data.path || null, title: e.data.title || '', at: e.data.at || Date.now() };
  });
}
export function context() { return erpContext; }

let wired = false;
function wire() {
  if (wired || typeof document === 'undefined') return;
  wired = true;
  document.addEventListener('click', (e) => {
    const el = e.target && e.target.closest && e.target.closest('[data-erp-open]');
    if (!el) return;
    e.preventDefault();
    go(el.getAttribute('data-erp-open'));
  });
}

/* ---------- registration ---------- */
if (typeof window !== 'undefined') {
  wire();
  window.EonNavigator = { find, findRecord, abilities, tableFor, url, currentRole, go, context, map: () => MAP, ready: () => load() };
  (window.__eonDomainQueue = window.__eonDomainQueue || []).push({
    id: 'navigator',
    priority: 97,
    claims: (q) => CLAIM.test(String(q || '')),
    answer,
  });
  /* the last word, not the first: reached only when no screen matched AND no
     answerer had anything either — so it never eats a question that has a real
     answer, and a genuine "where is the thingamajig" still gets a reply */
  window.__eonDomainQueue.push({
    id: 'navigator-help',
    priority: 1,
    answer: (q) => {
      if (!CLAIM.test(String(q || ''))) return null;
      const bn = /[ঀ-৿]/.test(String(q || ''));
      return bn
        ? { speak: 'ওই স্ক্রিনটা ইআরপিতে খুঁজে পাইনি। মেনুর শব্দ ব্যবহার করে দেখুন — যেমন “পেরোল কোথায়”, “জার্নাল এন্ট্রি খোলো”, “নতুন কর্মী যোগ করব কোথায়”।', detail: [] }
        : { speak: 'I could not find that screen in the ERP. Try the words on the menu — for example “where is payroll”, “open journal entries”, “add an employee”.', detail: [] };
    },
  });
  // the app renders actions as buttons; teach it this kind
  const hook = () => {
    const A = window.EonApp;
    if (!A) return;
    const prev = A.act;
    A.act = function (kind, payload, note) {
      if (kind === 'erp-open' && payload && payload.href) { go(payload.href); return; }
      return prev ? prev.apply(this, arguments) : undefined;
    };
  };
  if (window.EonApp) hook(); else window.addEventListener('eon:app-ready', hook);
}

export default { find, abilities, tableFor, url, currentRole };
