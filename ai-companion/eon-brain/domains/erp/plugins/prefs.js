/* ============================================================
   EON ability · Preferences memory
   What the boss wants EON to remember about *him*: what to call
   him, how to show money, when to brief, which language, how
   brief to be, whether the companion should keep quiet, which
   company to focus on — plus free-form notes ("remember that …").

   Storage
     • localStorage 'eon_prefs'   (browser, always)
     • window.EonBrain.mergeStore('prefs', …)   when the brain store exists
     • server/api/prefs.php       NOT YET BUILT — see SERVER_PREFS below; until that
       endpoint ships, preferences are a browser-local record

   Surfaces
     • window.EON_PREFS  — the live object (decisions.js reads .name)
     • window.EonPrefs   — { get, set, note, forget, all, money, reset, sync }
     • Ask EON domain 'prefs' (priority 95): remember / call me / show money
       in … / be brief / brief me at … / speak bangla / forget … /
       what do you remember
     • panel on the Ask screen: "What EON remembers"

   Money units
     window.fmtBDTk is wrapped here (the original is kept) so callers
     that go through the window function — intel/deck.js, the adapter,
     any plug-in using window.fmtBDTk — honour money_unit. The ES-module
     export fmtBDTk from dataset.js is immutable, so qa.js, decisions.js
     and the app's own k() keep the automatic unit; new code that wants
     the boss's unit should call window.EonPrefs.money(n).
   ============================================================ */
import { fmtBDT, fmtBDTk } from '../dataset.js';
import { addProvider } from '../decisions.js';

const KEY = 'eon_prefs';
const DEFAULTS = { name: null, money_unit: 'auto', brief_hour: 8, language: 'en', brevity: 'normal', mute_companion: false, focus_company: null, notes: [] };
const UNITS = ['auto', 'lakh', 'crore', 'full'];
const W = typeof window !== 'undefined' ? window : null;

/* ---------- load ---------- */
function load() {
  let p = {};
  try { if (W && W.localStorage) p = JSON.parse(W.localStorage.getItem(KEY) || '{}') || {}; } catch { p = {}; }
  const out = Object.assign({}, DEFAULTS, p);
  out.notes = Array.isArray(out.notes) ? out.notes.map((n) => (typeof n === 'string' ? { text: n, at: null } : n)).filter((n) => n && n.text) : [];
  return out;
}
const PREFS = load();
if (W) {
  // something may have set window.EON_PREFS before this module (fill gaps only), then the live object IS window.EON_PREFS
  const prior = W.EON_PREFS && W.EON_PREFS !== PREFS ? W.EON_PREFS : null;
  if (prior) {
    Object.keys(DEFAULTS).forEach((k) => { if (k !== 'notes' && prior[k] != null && (PREFS[k] == null || PREFS[k] === DEFAULTS[k])) PREFS[k] = prior[k]; });
    /* A preference this file has never heard of is still the boss's preference.
       `autoConfirm` is act.js's — documented as window.EON_PREFS.autoConfirm and
       set on the page BEFORE the modules load, which is the only moment that
       works for an inline script — and walking DEFAULTS alone dropped it on the
       floor the instant this object replaced his. The setting looked accepted
       and did nothing, which is the worst way for a confirmation switch to fail. */
    Object.keys(prior).forEach((k) => { if (!(k in DEFAULTS) && prior[k] !== undefined) PREFS[k] = prior[k]; });
  }
  W.EON_PREFS = PREFS;
}

/* ---------- money ---------- */
const trim = (s) => s.replace(/\.0$/, '');
function money(n, unit) {
  const u = unit || PREFS.money_unit || 'auto';
  const v = +n || 0, a = Math.abs(v), sign = v < 0 ? '−' : '';
  if (u === 'full') return fmtBDT(v);
  if (u === 'lakh') return a < 1e3 ? fmtBDT(v) : sign + '৳' + trim((a / 1e5).toFixed(a >= 1e6 ? 1 : 2)) + ' L';
  if (u === 'crore') return a < 1e5 ? money(v, 'lakh') : sign + '৳' + trim((a / 1e7).toFixed(a >= 1e8 ? 1 : 2)) + ' Cr';
  return fmtBDTk(v);
}
if (W) {
  try {
    const orig = typeof W.fmtBDTk === 'function' && !W.fmtBDTk.__eonPrefs ? W.fmtBDTk : fmtBDTk;
    const wrapped = (n) => ((PREFS.money_unit || 'auto') === 'auto' ? orig(n) : money(n));
    wrapped.__eonPrefs = true; wrapped.original = orig;
    W.fmtBDTk = wrapped;
  } catch {}
}

/* ---------- persist + apply ---------- */
let syncTimer = null;
function persist(opts = {}) {
  try { if (W && W.localStorage) W.localStorage.setItem(KEY, JSON.stringify(PREFS)); } catch {}
  try { if (W && W.EonBrain && typeof W.EonBrain.mergeStore === 'function') W.EonBrain.mergeStore('prefs', Object.assign({}, PREFS, { notes: PREFS.notes.slice() })); } catch {}
  if (!opts.noServer) { clearTimeout(syncTimer); syncTimer = setTimeout(() => sync('POST'), 400); }
  try { W && W.dispatchEvent(new CustomEvent('eon:prefs', { detail: Object.assign({}, PREFS) })); } catch {}
}
/* server/api/prefs.php is not built yet, so preferences live in the browser. Asking for
   it only produced a 404 on every keystroke that changed a preference. Flip this to true
   the day the endpoint ships — nothing else here needs to change. */
const SERVER_PREFS = false;
async function sync(method = 'POST') {
  try {
    if (!SERVER_PREFS) return null;
    const app = W && W.EonApp; if (!app || !app.env || !app.env().serverOk || typeof app.api !== 'function') return null;
    if (!app.env().authed) return null;                       // fail-closed server: no session, no request
    if (method === 'GET') { const r = await app.api('prefs.php'); return r && r.prefs ? r.prefs : null; }
    const r = await app.api('prefs.php', { method: 'POST', body: JSON.stringify(PREFS) });
    return r && r.prefs ? r.prefs : null;
  } catch (e) { return null; }
}
function applyLanguage() {
  if (!W) return;
  const code = PREFS.language === 'bn' ? 'bn-BD' : 'en-US';
  try { if (W.EonApp && W.EonApp.state) { W.EonApp.state.lang = code; W.localStorage.setItem('eon_lang', code); } } catch {}
  try { if (W.EonVoice && W.EonVoice.setLang) W.EonVoice.setLang(code); } catch {}
}
function applyMute() { try { if (W && W.EonVoice && W.EonVoice.mute) W.EonVoice.mute(!!PREFS.mute_companion); } catch {} }
function applyFocus() { try { if (W && W.EonErp && W.EonErp.setCompany && PREFS.focus_company != null && W.EonErp.company() !== +PREFS.focus_company) W.EonErp.setCompany(PREFS.focus_company); } catch {} }
function apply(k) {
  if (k === 'language') applyLanguage();
  if (k === 'mute_companion') applyMute();
  if (k === 'focus_company') applyFocus();
  if (k === 'name' && W && W.EonApp && W.EonApp.state && W.EonApp.state.section === 'brief') { try { W.EonApp.render(); } catch {} }
}
function coerce(k, v) {
  switch (k) {
    case 'name': return v == null || v === '' ? null : String(v).trim().slice(0, 40);
    case 'money_unit': { const s = String(v || 'auto').toLowerCase(); return s === 'lac' || s === 'lakhs' ? 'lakh' : s === 'exact' ? 'full' : UNITS.includes(s) ? s : 'auto'; }
    case 'brief_hour': { const h = parseInt(v, 10); return isNaN(h) ? DEFAULTS.brief_hour : Math.max(0, Math.min(23, h)); }
    case 'language': return /^b/i.test(String(v)) ? 'bn' : 'en';
    case 'brevity': return /short|brief/i.test(String(v)) ? 'short' : 'normal';
    case 'mute_companion': return v === true || v === 'true' || v === 1 || v === '1';
    case 'focus_company': return v == null || v === '' ? null : +v;
    default: return v;
  }
}

/* ---------- public API ---------- */
export const EonPrefs = {
  get(k) { return k ? PREFS[k] : Object.assign({}, PREFS); },
  all() { return Object.assign({}, PREFS, { notes: PREFS.notes.slice() }); },
  set(k, v, opts) { if (!(k in DEFAULTS) || k === 'notes') return false; PREFS[k] = coerce(k, v); persist(opts); apply(k); return PREFS[k]; },
  note(t) { const text = String(t || '').trim().replace(/\s+/g, ' ').slice(0, 300); if (!text) return null; if (PREFS.notes.some((n) => n.text.toLowerCase() === text.toLowerCase())) return null; const n = { text, at: new Date().toISOString().slice(0, 10) }; PREFS.notes.unshift(n); PREFS.notes = PREFS.notes.slice(0, 50); persist(); return n; },
  /** forget by (partial) note text, or a preference key, or 'everything' */
  forget(t) {
    const s = String(t || '').trim().toLowerCase();
    if (!s) return { removed: 0 };
    if (/^(everything|all|it all|all of it|all notes)$/.test(s)) { const n = PREFS.notes.length; Object.keys(DEFAULTS).forEach((k) => { PREFS[k] = k === 'notes' ? [] : DEFAULTS[k]; }); persist(); return { removed: n, reset: true }; }
    if (/^(my )?name$/.test(s)) { PREFS.name = null; persist(); return { removed: 1, key: 'name' }; }
    const before = PREFS.notes.length;
    PREFS.notes = PREFS.notes.filter((n) => !n.text.toLowerCase().includes(s));
    if (PREFS.notes.length !== before) { persist(); return { removed: before - PREFS.notes.length }; }
    return { removed: 0 };
  },
  money,
  reset() { return this.forget('everything'); },
  sync,
  /** pull server prefs (notes union, server scalars fill gaps), then push the merged record */
  async pull() {
    const s = await sync('GET'); if (!s || typeof s !== 'object') return null;
    Object.keys(DEFAULTS).forEach((k) => { if (k === 'notes') return; if ((PREFS[k] === null || PREFS[k] === DEFAULTS[k]) && s[k] != null && s[k] !== DEFAULTS[k]) PREFS[k] = coerce(k, s[k]); });
    (Array.isArray(s.notes) ? s.notes : []).forEach((n) => { const text = typeof n === 'string' ? n : n && n.text; if (text && !PREFS.notes.some((x) => x.text.toLowerCase() === String(text).toLowerCase())) PREFS.notes.push({ text: String(text), at: (n && n.at) || null }); });
    persist(); apply('language'); apply('mute_companion');
    return this.all();
  },
};
if (W) W.EonPrefs = EonPrefs;

/* ---------- Ask EON domain ---------- */
const clean = (s) => String(s || '').replace(/^\s*(eon|hey eon|ok eon)[,:\s]+/i, '').replace(/[.!?]+$/, '').trim();
const cap = (s) => (s ? s.charAt(0).toUpperCase() + s.slice(1) : s);
const summary = () => {
  const p = PREFS; const rows = [];
  rows.push(`Name: ${p.name || '— (not set; I say "Boss")'}`);
  rows.push(`Money: ${p.money_unit === 'auto' ? 'automatic (k / L / Cr)' : p.money_unit === 'full' ? 'full figures' : p.money_unit}`);
  rows.push(`Brief at: ${String(p.brief_hour).padStart(2, '0')}:00`);
  rows.push(`Language: ${p.language === 'bn' ? 'বাংলা (Bangla)' : 'English'} · Answers: ${p.brevity}`);
  if (p.mute_companion) rows.push('Companion: muted');
  if (p.focus_company != null) { let nm = String(p.focus_company); try { const c = (W.EonErp.companies() || []).find((x) => +x.id === +p.focus_company); if (c) nm = c.name; } catch {} rows.push(`Focus company: ${nm}`); }
  return rows;
};
function answer(q) {
  const s = clean(q); if (!s) return null;
  let m;
  // what do you remember
  if (/^(what (do|did) you (remember|know)( about me)?|what have you remembered|(show|list|tell me)?\s*my (preferences|prefs|settings)|(show|list) (your )?(memory|notes))\b/i.test(s)) {
    const rows = summary(); const notes = PREFS.notes;
    const spoken = `${PREFS.name ? `I call you ${PREFS.name}. ` : ''}Money ${PREFS.money_unit === 'auto' ? 'in automatic units' : 'in ' + PREFS.money_unit}, brief at ${PREFS.brief_hour}:00, ${PREFS.language === 'bn' ? 'Bangla' : 'English'}, ${PREFS.brevity} answers. ${notes.length ? `I am holding ${notes.length} note${notes.length > 1 ? 's' : ''}: ${notes.slice(0, 3).map((n) => n.text).join('; ')}${notes.length > 3 ? '…' : ''}.` : 'No notes yet — say "remember that …".'}`;
    return { speak: spoken, detail: rows.concat(notes.length ? ['Notes:'].concat(notes.map((n, i) => `${i + 1}. ${n.text}${n.at ? ' (' + n.at + ')' : ''}`)) : []), view: 'ask', data: EonPrefs.all() };
  }
  // forget
  if ((m = /^(?:please )?forget\s+(?:that\s+|about\s+|the note\s+)?(.+)$/i.exec(s))) {
    const r = EonPrefs.forget(m[1]);
    if (r.reset) return { speak: 'Forgotten — preferences and notes cleared. I am back to defaults.', detail: ['Preferences reset to defaults', `${r.removed} note(s) removed`], view: 'ask' };
    if (r.key) return { speak: 'Done. I will call you Boss again.', detail: ['name cleared'], view: 'ask' };
    return { speak: r.removed ? `Forgotten — ${r.removed} note${r.removed > 1 ? 's' : ''} removed.` : `I do not have a note matching "${m[1]}". Say "what do you remember" to see the list.`, detail: r.removed ? [`removed: notes containing "${m[1]}"`] : [], view: 'ask' };
  }
  // name
  if ((m = /^(?:please )?(?:call me|address me as|you can call me|my name is|i am called)\s+(.+)$/i.exec(s))) {
    const name = cap(m[1].trim());
    EonPrefs.set('name', name);
    return { speak: `Noted, ${name}. I will call you ${name} from now on.`, detail: [`name = ${name}`, 'used in the brief greeting and answers'], view: 'ask' };
  }
  // money unit
  if ((m = /^(?:please )?(?:show|display|format|give|report|say|use|put)\s+(?:me\s+)?(?:all\s+)?(?:the\s+)?(?:money|amounts?|figures|numbers|currency|values|bdt|taka)?\s*(?:in|as|using)\s+(lakhs?|lacs?|crores?|full(?: figures| numbers| amounts)?|exact(?: figures)?|auto(?:matic)?(?: units)?)$/i.exec(s))) {
    const u = EonPrefs.set('money_unit', m[1].replace(/s$/, ''));
    const ex = 12345678;
    return { speak: u === 'auto' ? 'Money back to automatic units — thousands, lakh and crore as the size dictates.' : `Money in ${u === 'full' ? 'full figures' : u} from now on — for example ${money(ex)}.`, detail: [`money_unit = ${u}`, `example: ${money(ex)} · ${money(450000)} · ${money(9800)}`], view: 'ask' };
  }
  // brevity
  if (/^(?:please )?(?:be (?:more )?(?:brief|short|concise|terse)|(?:give me |i want )?(?:short|brief|shorter) answers?|keep it (?:short|brief)|less talk)$/i.test(s)) { EonPrefs.set('brevity', 'short'); return { speak: 'Short answers from now on.', detail: ['brevity = short'], view: 'ask' }; }
  if (/^(?:please )?(?:be (?:more )?(?:detailed|normal|thorough)|(?:give me |i want )?(?:normal|full|longer|detailed) answers?|normal length)$/i.test(s)) { EonPrefs.set('brevity', 'normal'); return { speak: 'Back to normal answers.', detail: ['brevity = normal'], view: 'ask' }; }
  // brief hour
  if ((m = /^(?:please )?(?:brief me|send (?:me )?(?:the |my )?brief|morning brief|(?:my )?brief(?:ing)?)\s+(?:at|by|around)\s+(\d{1,2})(?::(\d{2}))?\s*(am|pm|a\.m\.|p\.m\.|o'?clock)?(?:\s+(?:every day|daily|each morning|in the morning))?$/i.exec(s))) {
    let h = parseInt(m[1], 10); const ap = (m[3] || '').replace(/\./g, '').toLowerCase();
    if (ap === 'pm' && h < 12) h += 12; if (ap === 'am' && h === 12) h = 0;
    if (h > 23) return { speak: 'Give me an hour between 0 and 23, like "brief me at 8".', detail: [], view: 'ask' };
    EonPrefs.set('brief_hour', h);
    return { speak: `Morning brief at ${String(h).padStart(2, '0')}:00 from now on.`, detail: [`brief_hour = ${h}`, 'the server cron reads this preference for the daily brief'], view: 'ask' };
  }
  // language
  if ((m = /^(?:please )?(?:speak|talk|answer|reply|respond)\s+(?:to me\s+)?(?:in\s+)?(bangla|bengali|বাংলা|english|ইংরেজি)(?:\s+(?:by default|from now on|always))?$/i.exec(s)) || (m = /^(?:switch|change|set)\s+(?:the\s+)?(?:default\s+)?language\s+to\s+(bangla|bengali|বাংলা|english|ইংরেজি)$/i.exec(s))) {
    const bn = /^(bangla|bengali|বাংলা)$/i.test(m[1]); EonPrefs.set('language', bn ? 'bn' : 'en');
    return { speak: bn ? 'ঠিক আছে — এখন থেকে বাংলায় কথা বলব।' : 'English by default from now on.', detail: [`language = ${bn ? 'bn' : 'en'}`, 'voice recognition and speech switched too'], view: 'ask' };
  }
  // mute / unmute companion
  if (/^(?:please )?(?:mute|silence|quiet|hush)(?: the)?(?: companion| avatar| yourself)?$/i.test(s)) { EonPrefs.set('mute_companion', true); return { speak: 'Companion muted. I will show answers without speaking.', detail: ['mute_companion = true'], view: 'ask' }; }
  if (/^(?:please )?(?:unmute|speak again|talk again)(?: the)?(?: companion| avatar)?$/i.test(s)) { EonPrefs.set('mute_companion', false); return { speak: 'Companion unmuted.', detail: ['mute_companion = false'], view: 'ask' }; }
  // free note — last, and only the explicit "remember …" form
  if ((m = /^(?:please )?(?:remember|note|keep in mind|don't forget|do not forget)(?:\s+that|\s+this)?[:\s]+(.+)$/i.exec(s))) {
    const n = EonPrefs.note(m[1]);
    if (!n) return { speak: 'I already have that noted.', detail: [], view: 'ask' };
    return { speak: `Remembered: ${n.text}.`, detail: [`note added (${PREFS.notes.length} total)`, 'say "forget …" to remove it, "what do you remember" to list'], view: 'ask' };
  }
  return null;
}
if (W) (W.__eonDomainQueue = W.__eonDomainQueue || []).push({ id: 'prefs', priority: 95, answer(q) { try { return answer(q); } catch (e) { console.warn('[EON prefs]', e); return null; } } });

/* ---------- decisions: gentle nudge when EON knows nothing about the boss ---------- */
try {
  addProvider(() => {
    if (PREFS.name || PREFS.notes.length) return [];
    return [{ id: 'prefs.introduce', layer: 'ops', severity: 1, title: 'Tell EON how to address you', why: ['No name or notes remembered yet', 'Say "call me <name>", "show money in lakh", "brief me at 8", "remember that …"'], recommend: 'Spend a minute teaching EON your preferences — it keeps them across sessions and the server.', actions: [{ label: 'Open Ask EON', kind: 'navigate', href: 'index.html#ask' }] }];
  });
} catch {}

/* ---------- panel: What EON remembers (Ask screen) ---------- */
function renderPanel() {
  const app = W && W.EonApp; const esc = app ? app.esc : (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const p = PREFS;
  const chip = (label, val) => `<span class="chip">${esc(label)}: <b>${esc(val)}</b></span>`;
  let focus = p.focus_company == null ? 'all companies' : String(p.focus_company);
  try { const c = (W.EonErp.companies() || []).find((x) => +x.id === +p.focus_company); if (c) focus = c.name; } catch {}
  const chips = [chip('Name', p.name || 'Boss'), chip('Money', p.money_unit), chip('Brief at', String(p.brief_hour).padStart(2, '0') + ':00'), chip('Language', p.language === 'bn' ? 'বাংলা' : 'English'), chip('Answers', p.brevity), chip('Companion', p.mute_companion ? 'muted' : 'speaks'), chip('Focus', focus)].join('');
  const notes = p.notes.length
    ? `<div class="list">${p.notes.map((n) => `<div class="item"><span>${esc(n.text)}</span><span class="spacer"></span><span class="hint">${esc(n.at || '')}</span> <button class="btn sm no" data-prefs-forget="${esc(n.text)}">forget</button></div>`).join('')}</div>`
    : '<div class="empty">No notes yet. Say “remember that …” and it stays here, in the brain store and on the server.</div>';
  return `<div class="chips">${chips}</div><div class="hint" style="margin:8px 0">Change with: “call me …”, “show money in lakh | crore | full”, “be brief”, “brief me at 8”, “speak bangla”, “forget …”.</div>${notes}<div style="margin-top:8px"><button class="btn sm" data-prefs-sync="1">sync with server</button> <button class="btn sm no" data-prefs-forget-all="1">forget everything</button></div>`;
}
if (W) {
  const reg = () => { try { W.EonApp.registerPanel('ask', { id: 'prefs', title: 'What EON remembers', order: 20, render: renderPanel }); } catch (e) { console.warn('[EON prefs] panel', e); } };
  const onReady = () => { reg(); applyLanguage(); if (PREFS.mute_companion) applyMute(); EonPrefs.pull().then((r) => { if (r && W.EonApp && W.EonApp.state && W.EonApp.state.section === 'ask') { try { W.EonApp.render(); } catch {} } }); };
  if (W.EonApp) onReady(); else if (W.addEventListener) W.addEventListener('eon:app-ready', onReady);
  if (typeof document !== 'undefined' && document.addEventListener && !W.__eonPrefsClick) {
    W.__eonPrefsClick = true;
    document.addEventListener('click', (e) => {
      const t = e.target && e.target.closest ? e.target.closest('[data-prefs-forget],[data-prefs-forget-all],[data-prefs-sync]') : null; if (!t) return;
      const app = W.EonApp;
      if (t.dataset.prefsForget) { EonPrefs.forget(t.dataset.prefsForget); app && app.toast && app.toast('Forgotten'); }
      else if (t.dataset.prefsForgetAll) { EonPrefs.forget('everything'); app && app.toast && app.toast('Preferences and notes cleared'); }
      else if (t.dataset.prefsSync) { EonPrefs.pull().then((r) => { app && app.toast && app.toast(r ? 'Preferences synced with the server' : 'Preferences are kept in this browser'); app && app.render && app.render(); }); return; }
      app && app.render && app.render();
    });
  }
}
export default EonPrefs;
