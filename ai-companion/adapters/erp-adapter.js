/* ============================================================
   EON · erp-adapter.js — the shim that lets the shared companion
   live inside the Epal ERP. Classic script; load it BEFORE the
   module entry (../ai-companion/js/boot.js) on any ERP page.

   It provides, in order:
     1. identity + money formatters (BDT) the brain expects
     2. a Firestore-compatible store — collection(c).doc(d).get()/
        .set(v,{merge}) is all the brain touches — backed by the
        EON server when reachable, else localStorage
     3. the ERP dataset → server (/server/api/dataset.php) or the
        deterministic demo generator (no server) — mapped into the
        flat entity arrays the brain’s discovery engine reads
     4. brain configuration for the ERP space (deadline entities,
        link patterns, meditation cadence)
     5. window.EON_ENV — mode (live / server / static), server URL,
        company scope — read by the app and the voice layer

   Configure by defining window.EON_CONFIG before this script:
     { server: '/eon/server/api',  // base URL of the PHP backend (optional)
       company: null,              // default company scope (id) or null = group
       demo: false,                // force the demo dataset
       owner: { name, email } }
   ============================================================ */
'use strict';
(function () {
  if (window.__EON_ERP_ADAPTER) return;
  window.__EON_ERP_ADAPTER = true;
  const CFG = Object.assign({ server: null, company: null, demo: false, owner: null, cacheMinutes: 5 }, window.EON_CONFIG || {});
  const here = (function () { try { const s = document.currentScript && document.currentScript.src; return s ? new URL('.', s).href : new URL('./', location.href).href; } catch { return './'; } })();
  // default server location: sibling of ai-companion/ → ../server/api  (both standalone and inside ERP public/eon/)
  const SERVER = CFG.server || new URL('../../server/api/', here).href.replace(/\/$/, '');

  /* ---------- 1. identity + formatters ---------- */
  const OWNER = Object.assign({ name: 'Md Imran Hossain', email: 'imran@epal.com.bd', title: 'Managing Director' }, CFG.owner || {});
  window.OWNER_EMAIL = OWNER.email;
  window.EON_OWNER = OWNER;
  const bdt = (n) => { n = Math.round(+n || 0); const neg = n < 0; n = Math.abs(n); let s = String(n); if (s.length > 3) { const last3 = s.slice(-3); let rest = s.slice(0, -3); rest = rest.replace(/\B(?=(\d{2})+(?!\d))/g, ','); s = rest + ',' + last3; } return (neg ? '−' : '') + '৳' + s; };
  if (!window.fmtBDT) window.fmtBDT = bdt;
  if (!window.fmtBDTk) window.fmtBDTk = (n) => { const a = Math.abs(+n || 0), sign = (+n || 0) < 0 ? '−' : ''; if (a >= 1e7) return sign + '৳' + (a / 1e7).toFixed(a >= 1e8 ? 0 : 1).replace(/\.0$/, '') + ' Cr'; if (a >= 1e5) return sign + '৳' + (a / 1e5).toFixed(a >= 1e6 ? 0 : 1).replace(/\.0$/, '') + ' L'; if (a >= 1e3) return sign + '৳' + (a / 1e3).toFixed(1).replace(/\.0$/, '') + 'k'; return bdt(n); };

  /* ---------- 2. Firestore-shaped store (server-backed, localStorage fallback) ---------- */
  const LS = 'eonerp::';
  const readLS = (k) => { try { return JSON.parse(localStorage.getItem(LS + k)); } catch { return null; } };
  const writeLS = (k, v) => { try { localStorage.setItem(LS + k, JSON.stringify(v)); } catch { /* quota — keep in memory */ } };
  const mem = {};
  const deepMerge = (dst, src) => { Object.keys(src || {}).forEach((k) => { if (src[k] && typeof src[k] === 'object' && !Array.isArray(src[k]) && dst[k] && typeof dst[k] === 'object' && !Array.isArray(dst[k])) deepMerge(dst[k], src[k]); else dst[k] = src[k]; }); return dst; };
  const ENV = window.EON_ENV = { mode: 'static', server: SERVER, serverOk: false, llm: false, db: false, company: CFG.company, source: null, adapter: 'erp', checkedAt: null };
  const withTimeout = (p, ms) => Promise.race([p, new Promise((_, rej) => setTimeout(() => rej(new Error('timeout')), ms))]);
  const api = async (path, opts) => { const r = await withTimeout(fetch(SERVER + '/' + path, Object.assign({ credentials: 'same-origin', headers: { 'Content-Type': 'application/json' } }, opts || {})), opts && opts.timeout || 8000); if (!r.ok) throw new Error(path + ' ' + r.status); return r.json(); };

  function docRef(col, id) {
    const key = col + '/' + id;
    const isBrain = col !== 'opptrack';               // the dataset doc is never persisted; the brain doc is
    return {
      async get() {
        let v = mem[key] !== undefined ? mem[key] : null;
        if (v == null && isBrain && ENV.serverOk) { try { const r = await api('memory.php?doc=' + encodeURIComponent(key)); v = r && r.data != null ? r.data : null; } catch { /* fall through */ } }
        if (v == null) v = readLS(key);
        if (v != null) mem[key] = v;
        return { exists: v != null, data: () => (v == null ? undefined : JSON.parse(JSON.stringify(v))) };
      },
      async set(v, o) {
        const cur = mem[key] !== undefined ? mem[key] : (readLS(key) || {});
        const next = (o && o.merge) ? deepMerge(JSON.parse(JSON.stringify(cur)), v) : JSON.parse(JSON.stringify(v));
        mem[key] = next; if (isBrain) writeLS(key, next);
        if (isBrain && ENV.serverOk) { try { await api('memory.php?doc=' + encodeURIComponent(key), { method: 'PUT', body: JSON.stringify({ data: next }) }); } catch { /* offline — localStorage has it */ } }
      },
      async update(v) { return this.set(v, { merge: true }); },
    };
  }
  const AUTH = {
    currentUser: { email: OWNER.email, uid: 'erp-boss', displayName: OWNER.name },
    onAuthStateChanged(cb) { setTimeout(() => cb(AUTH.currentUser), 0); return () => {}; },
    async signInAnonymously() { return { user: AUTH.currentUser }; },
  };
  const FS = () => ({ collection: (c) => ({ doc: (d) => docRef(c, d) }) });
  FS.FieldValue = { serverTimestamp: () => new Date().toISOString() };
  if (!window.firebase) window.firebase = { apps: [{ name: '[DEFAULT]' }], initializeApp() { return window.firebase.apps[0]; }, auth: () => AUTH, firestore: FS };

  /* ---------- 3. dataset → store shape ---------- */
  // The brain wants flat arrays with an id, a label, a date and a status. We hand it
  // the actionable slices of the ERP; the ERP domain keeps the full dataset itself.
  function toStore(D) {
    const t = D.meta.today; const E = new Map((D.employees || []).map((e) => [e.id, e]));
    const nm = (id) => (E.get(id) || {}).name || ('#' + id);
    const co = (id) => ((D.companies || []).find((c) => c.id === id) || {}).short_name || '';
    return {
      receivables: (D.payment_schedules || []).filter((p) => p.type === 'receive' && ['pending', 'overdue'].includes(p.status)).map((p) => ({ id: 'ar-' + p.id, name: `${p.party_name} — ${p.source_label || 'receivable'}`, party: p.party_name, company: co(p.company_id), amount: +p.amount - (+p.paid_amount || 0), deadline: p.scheduled_date, status: p.status, priority: p.priority })),
      payables: (D.payment_schedules || []).filter((p) => p.type === 'pay' && ['pending', 'overdue'].includes(p.status)).map((p) => ({ id: 'ap-' + p.id, name: `Pay ${p.party_name} — ${p.source_label || 'payable'}`, party: p.party_name, company: co(p.company_id), amount: +p.amount - (+p.paid_amount || 0), deadline: p.scheduled_date, status: p.status, priority: p.priority })),
      approvals: [].concat(
        (D.expenses || []).filter((e) => e.approval_status === 'pending').map((e) => ({ id: 'exp-' + e.id, name: `Expense: ${e.title} (${e.category})`, kind: 'expense', who: e.user_name, company: co(e.company_id), amount: e.amount, deadline: e.expense_date, status: 'pending' })),
        (D.leaves || []).filter((l) => l.status === 'pending').map((l) => ({ id: 'lv-' + l.id, name: `Leave: ${nm(l.user_id)} — ${l.leave_type} ${l.days}d`, kind: 'leave', who: nm(l.user_id), company: co(l.company_id), deadline: l.start_date, status: 'pending' })),
        (D.advance_salaries || []).filter((a) => a.status === 'Pending').map((a) => ({ id: 'adv-' + a.id, name: `Advance: ${nm(a.user_id)} ${bdt(a.amount)}`, kind: 'advance', who: nm(a.user_id), amount: a.amount, deadline: t, status: 'pending' })),
        (D.employee_requests || []).filter((r) => ['pending', 'under_review'].includes(r.status)).map((r) => ({ id: 'req-' + r.id, name: `Request: ${nm(r.user_id)} — ${r.request_type}`, kind: 'request', who: nm(r.user_id), amount: r.amount, deadline: r.deadline, status: r.status })),
      ),
      tasks: (D.tasks || []).filter((k) => k.status !== 'done').map((k) => ({ id: 'task-' + k.id, title: k.title, project: k.project, company: co(k.company_id), assignee: (k.assigned_to || []).map(nm).join(', '), priority: k.priority, dueDate: k.due_date, status: k.status })),
      projects: (D.projects || []).filter((p) => !['completed', 'cancelled'].includes(p.status)).map((p) => ({ id: 'proj-' + p.id, name: p.project_name, company: co(p.company_id), manager: p.manager || nm(p.manager_id), progress: p.progress + '%', budget: p.budget, deadline: p.end_date, status: p.status })),
      todos: (D.office_todos || []).filter((k) => k.status !== 'completed').map((k) => ({ id: 'todo-' + k.id, title: k.title, department: k.department, priority: k.priority, dueDate: k.due_date, status: k.status })),
      leads: (D.leads || []).filter((l) => !['won', 'lost'].includes(l.status)).map((l) => ({ id: 'lead-' + l.id, name: `${l.name} (${String(l.lead_type).replace('_', ' ')})`, company: co(l.company_id), owner: l.assigned_name || nm(l.assigned_to), value: l.value, followUp: l.next_followup_at, status: l.status })),
      deals: (D.deals || []).filter((d) => d.status === 'open').map((d) => ({ id: 'deal-' + d.id, name: d.title, company: co(d.company_id), amount: d.amount, deadline: d.closing_date, status: d.status })),
      employees: (D.employees || []).filter((e) => e.status === 'active').map((e) => ({ id: e.id, name: e.name, designation: e.designation, department: e.department, company: co(e.company_id), joined: e.joining_date, status: 'active' })),
      companies: (D.companies || []).map((c) => ({ id: c.id, name: c.name, code: c.short_name, status: c.status })),
    };
  }

  const dataDoc = docRef('opptrack', 'data');
  async function publish(D) {
    ENV.source = D.meta.source; ENV.checkedAt = new Date().toISOString();
    await dataDoc.set({ store: toStore(D), updatedAt: new Date().toISOString(), source: D.meta.source });
    if (window.EonErp && window.EonErp.setDataset) window.EonErp.setDataset(D);
    else window.__EON_ERP_PENDING = D;                       // index.js picks it up when it loads
    try { window.dispatchEvent(new CustomEvent('eon:env', { detail: ENV })); } catch {}
    try { if (window.EonBrain && window.EonBrain.meditate) window.EonBrain.meditate(); } catch {}
  }

  async function loadDataset() {
    // 1) the server, if it answers
    if (!CFG.demo) {
      try {
        const h = await api('health.php', { timeout: 3500 });
        ENV.serverOk = true; ENV.db = !!h.db; ENV.llm = !!h.llm; ENV.mode = h.db ? (h.llm ? 'live' : 'server') : 'static';
        if (h.db) {
          const cacheKey = 'dataset:' + (CFG.company || 'all'); const cached = readLS(cacheKey);
          if (cached && cached.at && Date.now() - cached.at < CFG.cacheMinutes * 60000 && cached.D) { await publish(cached.D); }
          const D = await api('dataset.php' + (CFG.company ? '?company=' + CFG.company : ''), { timeout: 30000 });
          if (D && D.meta) { D.meta.source = 'erp'; writeLS(cacheKey, { at: Date.now(), D }); await publish(D); return; }
        }
      } catch (e) { console.info('[EON erp] no server —', e.message); }
    }
    // 2) demo dataset (static mode)
    ENV.mode = ENV.serverOk && !ENV.db ? 'server' : 'static';
    const gen = () => new Promise((res, rej) => { import(new URL('../eon-brain/domains/erp/demo-data.js', here).href).then((m) => res(m.generateDemo())).catch(rej); });
    try { const D = await gen(); await publish(D); } catch (e) { console.error('[EON erp] demo generator failed:', e); }
  }

  /* ---------- 4. brain config for the ERP space ---------- */
  window.EON_BRAIN_CONFIG = Object.assign({}, window.EON_BRAIN_CONFIG || {}, {
    space: 'erp',
    ownerEmail: OWNER.email,
    deadlineEntities: ['receivables', 'payables', 'approvals', 'tasks', 'projects', 'todos', 'leads', 'deals'],
    windows: [7, 3, 1, 0],
    intervalMs: 5 * 60 * 1000,
    linkPatterns: {
      receivables: 'finance.html#receivables', payables: 'finance.html#payables', approvals: 'index.html#approvals',
      tasks: 'operations.html#tasks', projects: 'operations.html#projects', todos: 'operations.html#todos',
      leads: 'crm.html#pipeline', deals: 'crm.html#deals', employees: 'people.html', companies: 'index.html', default: 'index.html',
    },
  });

  /* ---------- 5. go ---------- */
  window.EonErpAdapter = { env: ENV, reload: loadDataset, publish, server: SERVER, config: CFG };
  loadDataset();
})();
