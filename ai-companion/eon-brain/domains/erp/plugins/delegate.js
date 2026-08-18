/* ============================================================
   EON · delegation & follow-through.

   The boss says it once — "tell Afiqur to call DBL Ceramics by
   Thursday" — and EON remembers who, what, and by when, then keeps
   asking about it until it is closed. Delegations live in the
   browser (localStorage + the brain's store) and, when the server
   is up, they are logged as actions so the morning brief can chase
   them too.

     window.EonDelegate.list()            → open + closed
     window.EonDelegate.add(to, task, due)
     window.EonDelegate.close(id)

   Intents: "tell X to …", "ask X to … by Sunday", "remind X to …",
   "what did I delegate", "my follow-ups", "mark <text> done".
   ============================================================ */
import * as P from '../people.js';
import { addProvider } from '../decisions.js';
import { iso, addDays, daysBetween, MONTHS } from '../dataset.js';

const KEY = 'eon_delegations';
const D0 = () => (typeof window !== 'undefined' && window.EonErp && window.EonErp.dataset ? window.EonErp.dataset() : null);
const today = () => { const D = D0(); return (D && D.meta && D.meta.today) || iso(new Date()); };

/* ---------- storage ---------- */
function read() {
  try { const raw = localStorage.getItem(KEY); const a = raw ? JSON.parse(raw) : []; return Array.isArray(a) ? a : []; } catch { return []; }
}
function write(list) {
  try { localStorage.setItem(KEY, JSON.stringify(list.slice(-200))); } catch {}
  try { if (window.EonBrain && window.EonBrain.mergeStore) window.EonBrain.mergeStore('delegations', { items: list }); } catch {}
  return list;
}

/* ---------- when? ---------- */
const WEEKDAYS = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
const isWeekend = (d) => [5, 6].includes(new Date(d + 'T00:00:00').getDay());   // Fri/Sat in Bangladesh
function nextWorkday(d, n = 2) { let x = d; let left = n; while (left > 0) { x = iso(addDays(x, 1)); if (!isWeekend(x)) left--; } return x; }

export function parseDue(text, from = today()) {
  const s = String(text || '').toLowerCase();
  if (/\btoday\b|\bআজ\b/.test(s)) return from;
  if (/\btomorrow\b|\bআগামীকাল\b/.test(s)) return iso(addDays(from, 1));
  if (/\bnext week\b/.test(s)) return nextWorkday(from, 5);
  if (/\bend of (the )?month\b/.test(s)) { const d = new Date(from + 'T00:00:00'); return iso(new Date(d.getFullYear(), d.getMonth() + 1, 0)); }
  const wd = WEEKDAYS.findIndex((w) => new RegExp('\\b' + w + '\\b').test(s));
  if (wd >= 0) { let x = from; for (let i = 0; i < 8; i++) { x = iso(addDays(x, 1)); if (new Date(x + 'T00:00:00').getDay() === wd) return x; } }
  const dm = s.match(/\b(\d{1,2})[\/\-](\d{1,2})(?:[\/\-](\d{2,4}))?\b/);          // 20/08 or 20-08-2026
  if (dm) { const y = dm[3] ? (dm[3].length === 2 ? '20' + dm[3] : dm[3]) : from.slice(0, 4); return `${y}-${String(+dm[2]).padStart(2, '0')}-${String(+dm[1]).padStart(2, '0')}`; }
  const md = s.match(new RegExp('\\b(\\d{1,2})\\s+(' + MONTHS.map((m) => m.toLowerCase().slice(0, 3)).join('|') + ')', 'i'));
  if (md) { const mi = MONTHS.findIndex((m) => m.toLowerCase().startsWith(md[2])); return `${from.slice(0, 4)}-${String(mi + 1).padStart(2, '0')}-${String(+md[1]).padStart(2, '0')}`; }
  const inDays = s.match(/\bin (\d{1,2}) days?\b/); if (inDays) return iso(addDays(from, +inDays[1]));
  return null;
}
const pretty = (d) => { const x = new Date(d + 'T00:00:00'); return `${['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][x.getDay()]} ${x.getDate()} ${MONTHS[x.getMonth()].slice(0, 3)}`; };

/* ---------- the register ---------- */
export function add(toName, task, dueText) {
  const D = D0();
  const typed = String(toName || '').trim();
  let emp = D ? P.findEmployee(D, typed) : null;
  // findEmployee is deliberately fuzzy; a delegation must not land on the wrong person,
  // so keep it only when the names actually share a word ("Nasrin" must not become "Nusrat").
  if (emp) {
    const tok = (x) => String(x || '').toLowerCase().split(/[^a-z]+/).filter((w) => w.length >= 3);
    const a = tok(typed), b = tok(emp.name);
    // every word the boss said must be in the matched name, so a shared surname
    // alone (Nasrin Akter vs Nusrat Akter) never redirects the instruction
    if (!a.length || !a.every((w) => b.includes(w))) emp = null;
  }
  const due = parseDue(dueText || task) || nextWorkday(today(), 2);
  const item = {
    id: 'd' + Date.now().toString(36) + Math.floor(Math.random() * 1e4).toString(36),
    to: emp ? emp.name : typed,
    to_id: emp ? emp.id : null,
    department: emp ? emp.department : null,
    company_id: emp ? emp.company_id : null,
    task: String(task || '').trim().replace(/\s+by\s+[^,]*$/i, '').trim(),
    due, created: today(), status: 'open', source: 'boss',
  };
  const list = read(); list.push(item); write(list);
  try { if (window.EonApp && window.EonApp.act) window.EonApp.act('delegate', item, `Delegated to ${item.to}: ${item.task}`); } catch {}
  return item;
}
export function close(id) {
  const list = read(); const it = list.find((x) => x.id === id); if (!it) return null;
  it.status = 'done'; it.closed = today(); write(list);
  try { if (window.EonApp && window.EonApp.act) window.EonApp.act('delegation-done', it, `Closed: ${it.task}`); } catch {}
  return it;
}
export function list() {
  const t = today(), all = read();
  const open = all.filter((x) => x.status === 'open').map((x) => Object.assign({}, x, { daysLate: Math.max(0, daysBetween(x.due, t)), overdue: x.due < t, dueToday: x.due === t }));
  open.sort((a, b) => (a.due < b.due ? -1 : a.due > b.due ? 1 : 0));
  return { all, open, overdue: open.filter((x) => x.overdue), dueToday: open.filter((x) => x.dueToday), done: all.filter((x) => x.status === 'done') };
}

/* ---------- questions ---------- */
const ORDER = /^(?:eon[,\s]+)?(tell|ask|remind|assign|instruct|get|have)\s+([A-Za-z][A-Za-z.\- ]{1,40}?)\s+to\s+(.+)$/i;
const ORDER2 = /^(?:eon[,\s]+)?(?:delegate|hand)\s+(.+?)\s+to\s+([A-Za-z][A-Za-z.\- ]{1,40})$/i;
const FOLLOW = /^(?:eon[,\s]+)?follow[- ]?up with\s+([A-Za-z][A-Za-z.\- ]{1,40}?)\s+(?:on|about)\s+(.+)$/i;

function answer(q, ctx) {
  const s = String(q || '').trim();
  if (!s) return null;

  // "tell Afiqur Rahman to call DBL Ceramics by Thursday"
  let who = null, what = null, m;
  if ((m = s.match(ORDER))) { who = m[2]; what = m[3]; }                       // tell X to Y
  else if ((m = s.match(FOLLOW))) { who = m[1]; what = `follow up on ${m[2]}`; } // follow up with X on Y
  else if ((m = s.match(ORDER2))) { who = m[2]; what = m[1]; }                 // delegate Y to X
  if (who && what) {
    const it = add(who, what, s);
    const unknown = it.to_id == null;
    return {
      speak: `Done — ${it.to} to ${it.task}, by ${pretty(it.due)}. I will follow up.${unknown ? ' (I could not match that name in the ERP, so I am tracking it by name only.)' : ''}`,
      detail: [`Assigned: ${it.to}${it.department ? ' · ' + it.department : ''}`, `Due: ${it.due}`, `Open follow-ups: ${list().open.length}`],
      view: 'brief',
    };
  }

  const nq = s.toLowerCase();
  if (/^(?:mark|close)\s+(.+?)\s+(?:as\s+)?(done|complete[d]?|finished)$/i.test(s)) {
    const text = s.match(/^(?:mark|close)\s+(.+?)\s+(?:as\s+)?(?:done|complete[d]?|finished)$/i)[1].toLowerCase();
    const hit = list().open.find((x) => (x.task + ' ' + x.to).toLowerCase().includes(text));
    if (!hit) return { speak: `I could not find an open follow-up matching “${text}”.`, detail: list().open.slice(0, 5).map((x) => `${x.to}: ${x.task}`), view: 'brief' };
    close(hit.id);
    return { speak: `Closed — ${hit.to}: ${hit.task}. ${list().open.length} follow-ups still open.`, detail: [], view: 'brief' };
  }

  if (/\b(my )?(follow[- ]?ups?|delegations?|delegated|who owes me( an)? update|what did i (ask|tell|delegate))\b/.test(nq)) {
    const L = list();
    if (!L.open.length) return { speak: 'Nothing is delegated right now. Say “tell Afiqur Rahman to call DBL Ceramics by Thursday” and I will keep track of it.', detail: [], view: 'brief' };
    return {
      speak: `${L.open.length} follow-up${L.open.length > 1 ? 's' : ''} open${L.overdue.length ? `, ${L.overdue.length} overdue` : ''}${L.dueToday.length ? `, ${L.dueToday.length} due today` : ''}. ${L.open[0].to}: ${L.open[0].task}, due ${pretty(L.open[0].due)}.`,
      detail: L.open.slice(0, 8).map((x) => `${x.to} — ${x.task} · due ${x.due}${x.overdue ? ` (${x.daysLate}d late)` : ''}`),
      view: 'brief',
    };
  }
  return null;
}
const claims = (q) => ORDER.test(String(q || '').trim()) || ORDER2.test(String(q || '').trim()) || FOLLOW.test(String(q || '').trim()) || /\b(my )?(follow[- ]?ups?|delegations?)\b/i.test(String(q || ''));

/* ---------- decisions ---------- */
addProvider(() => {
  const L = list();
  const out = [];
  if (L.overdue.length) out.push({
    id: 'delegate-overdue', layer: 'ops', severity: 3,
    title: `${L.overdue.length} delegated task${L.overdue.length > 1 ? 's are' : ' is'} overdue`,
    why: L.overdue.slice(0, 3).map((x) => `${x.to}: ${x.task} — due ${x.due}, ${x.daysLate}d late`),
    recommend: `Ask ${L.overdue[0].to} for a status on “${L.overdue[0].task}” today.`,
    amount: 0,
  });
  if (L.dueToday.length) out.push({
    id: 'delegate-today', layer: 'ops', severity: 2,
    title: `${L.dueToday.length} delegated task${L.dueToday.length > 1 ? 's are' : ' is'} due today`,
    why: L.dueToday.slice(0, 3).map((x) => `${x.to}: ${x.task}`),
    recommend: 'Check them off before the day ends.',
    amount: 0,
  });
  return out;
});

/* ---------- screen ---------- */
let wired = false;
function panel() {
  const A = typeof window !== 'undefined' && window.EonApp;
  if (!A || !A.registerPanel) return;
  if (!wired && typeof document !== 'undefined') {
    wired = true;
    document.addEventListener('click', (e) => {
      const b = e.target && e.target.closest && e.target.closest('[data-delegate-done]');
      if (!b) return;
      close(b.getAttribute('data-delegate-done'));
      if (A.toast) A.toast('Follow-up closed');
      if (A.render) A.render();
    });
  }
  A.registerPanel('brief', {
    id: 'delegations', title: 'EON follow-ups', order: 12,
    render() {
      const L = list();
      if (!L.open.length) return '<div class="empty">Nothing delegated. Say “tell Afiqur Rahman to call DBL Ceramics by Thursday”.</div>';
      return `<div class="list">${L.open.slice(0, 8).map((x) => `<div class="item"><span class="sev ${x.overdue ? 's4' : x.dueToday ? 's3' : 's2'}"></span><div><div class="t">${A.esc(x.to)}${x.department ? ` <span class="tag">${A.esc(x.department)}</span>` : ''}</div><div class="why">${A.esc(x.task)}</div></div><div class="meta">${A.esc(x.due)}${x.overdue ? `<br><b>${x.daysLate}d late</b>` : x.dueToday ? '<br>today' : ''}<br><button class="btn sm ok" data-delegate-done="${A.esc(x.id)}">Done</button></div></div>`).join('')}</div>`;
    },
  });
}

/* ---------- registration ---------- */
if (typeof window !== 'undefined') {
  window.EonDelegate = Object.assign(window.EonDelegate || {}, { list, add, close, parseDue });
  (window.__eonDomainQueue = window.__eonDomainQueue || []).push({ id: 'delegate', priority: 96, claims, answer });
  if (window.EonApp) panel(); else window.addEventListener('eon:app-ready', panel);
}
export default { list, add, close, parseDue };
