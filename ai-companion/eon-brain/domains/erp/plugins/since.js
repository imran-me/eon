/* ============================================================
   EON ability · SINCE YESTERDAY — memory of change.

   Every time a dataset loads, EON keeps a small snapshot of the day
   (KPI numbers, the decision keys, the approvals count) and compares
   it with the latest earlier snapshot: what moved, what is new, what
   got resolved. Answers "what changed / since yesterday / anything
   new", raises one decision when new critical items appeared, and
   shows a compact "Since <date>" card on the brief.

   Storage: localStorage 'eon_since_snapshots' (30 days), mirrored to
   EonBrain.mergeStore('snapshots', …) when the brain is present.
   window.EonSince = { snapshot(), diff(), history(), reset() }.
   ============================================================ */
import { fmtBDTk } from '../dataset.js';
import { addProvider } from '../decisions.js';
import { money as bnMoney, digits as bnDigits } from './bangla.js';

const KEY = 'eon_since_snapshots';
const MAX_DAYS = 30;

/* KPI catalogue: label, money?, unit, and whether "up" is good (null = neutral) */
const KPIS = [
  { key: 'cash', label: 'Cash & bank', money: true, upGood: true },
  { key: 'receivables', label: 'Receivables', money: true, upGood: null },
  { key: 'receivables_overdue', label: 'Overdue receivables', money: true, upGood: false },
  { key: 'payables', label: 'Payables', money: true, upGood: null },
  { key: 'payables_overdue', label: 'Overdue payables', money: true, upGood: false },
  { key: 'revenue', label: 'Revenue MTD', money: true, upGood: true },
  { key: 'headcount', label: 'Headcount', money: false, upGood: null },
  { key: 'present_pct', label: 'Present today', money: false, unit: '%', upGood: true },
  { key: 'pipeline_value', label: 'Pipeline', money: true, upGood: true },
  { key: 'tasks_overdue', label: 'Overdue tasks', money: false, upGood: false },
  { key: 'projects_at_risk', label: 'Projects at risk', money: false, upGood: false },
];

const W = typeof window !== 'undefined' ? window : null;
const erp = () => (W && W.EonErp && W.EonErp.dataset ? W.EonErp : null);
const todayOf = (D) => (D && D.meta && D.meta.today) || new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);
const scopeOf = (D) => ({ source: (D && D.meta && D.meta.source) || 'demo', company: erp() && erp().company() != null ? +erp().company() : null });
const num = (v) => { const n = +v; return Number.isFinite(n) ? Math.round(n * 100) / 100 : 0; };
const decKey = (d) => `${d.layer || 'ops'}|${d.title || ''}`;
const fmtVal = (k, v) => (k.money ? fmtBDTk(v) : k.unit === '%' ? `${v}%` : String(v));

/* ---------- storage ---------- */
function load() {
  try { const raw = W && W.localStorage ? W.localStorage.getItem(KEY) : null; const a = raw ? JSON.parse(raw) : []; return Array.isArray(a) ? a : []; } catch { return []; }
}
function save(list) {
  try { if (W && W.localStorage) W.localStorage.setItem(KEY, JSON.stringify(list)); } catch {}
}
function prune(list) {
  const dates = [...new Set(list.map((s) => s.date))].sort().slice(-MAX_DAYS);
  return list.filter((s) => dates.includes(s.date)).sort((a, b) => a.date.localeCompare(b.date));
}
function mirror(snap) {
  try {
    const B = W && W.EonBrain;
    if (B && typeof B.mergeStore === 'function') { const p = B.mergeStore('snapshots', { [`${snap.source}|${snap.company == null ? 'all' : snap.company}|${snap.date}`]: snap }); if (p && typeof p.catch === 'function') p.catch(() => {}); }
  } catch {}
}

/* ---------- snapshot of today ---------- */
let _busy = false;          // re-entrancy guard: our own decision provider must not feed the snapshot
let _cache = null;          // { key, diff } for the current scope/day

export function snapshot({ store = true } = {}) {
  const E = erp(); const D = E && E.dataset(); if (!D) return null;
  const { source, company } = scopeOf(D); const F = E.finance;
  let kp = null, decs = [], ap = null;
  _busy = true;
  try {
    const k = E.kpis() || {};
    const ar = F && F.receivables ? F.receivables(D, { company }) : { overdueTotal: 0 };
    const pay = F && F.payables ? F.payables(D, { company }) : { overdueTotal: 0 };
    decs = E.decisions() || []; ap = E.approvals() || { count: 0 };
    kp = {
      cash: num(k.cash && k.cash.value), receivables: num(k.receivables && k.receivables.value), receivables_overdue: num(ar.overdueTotal),
      payables: num(k.payables && k.payables.value), payables_overdue: num(pay.overdueTotal), revenue: num(k.revenue && k.revenue.value),
      headcount: num(k.headcount && k.headcount.value), present_pct: num(k.attendance && k.attendance.value), pipeline_value: num(k.pipeline && k.pipeline.value),
      tasks_overdue: num(k.tasks && k.tasks.value), projects_at_risk: num(k.projects && k.projects.value),
    };
  } finally { _busy = false; }
  const snap = { date: todayOf(D), source, company, kpis: kp, decision_keys: [...new Set(decs.filter((d) => !d.since_plugin).map(decKey))], approvals_count: ap.count || 0, taken_at: new Date().toISOString() };
  Object.defineProperty(snap, '_decisions', { value: decs, enumerable: false });   // for diff(); never serialised
  if (store) {
    const list = load().filter((s) => !(s.date === snap.date && s.source === snap.source && (s.company == null ? null : +s.company) === snap.company));
    list.push(snap); save(prune(list)); mirror(snap);
  }
  _cache = null;
  return snap;
}

/* ---------- diff vs the latest earlier snapshot ---------- */
export function diff({ now = null } = {}) {
  const E = erp(); const D = E && E.dataset(); if (!D) return null;
  const cur = now || snapshot({ store: false });
  if (!cur) return null;
  const cacheKey = `${cur.source}|${cur.company}|${cur.date}|${cur.approvals_count}|${cur.decision_keys.length}`;
  if (_cache && _cache.key === cacheKey && !now) return _cache.diff;
  const prev = load().filter((s) => s.date < cur.date && s.source === cur.source && (s.company == null ? null : +s.company) === cur.company).sort((a, b) => b.date.localeCompare(a.date))[0] || null;
  const out = { date: cur.date, since: prev ? prev.date : null, first: !prev, source: cur.source, company: cur.company, kpis: [], new_decisions: [], resolved_decisions: [], approvals: { before: prev ? prev.approvals_count : null, after: cur.approvals_count, delta: prev ? cur.approvals_count - prev.approvals_count : 0 }, changed: 0 };
  if (prev) {
    const days = Math.max(1, Math.round((Date.parse(cur.date) - Date.parse(prev.date)) / 86400000));
    out.days = days;
    KPIS.forEach((k) => {
      const b = num((prev.kpis || {})[k.key]), a = num((cur.kpis || {})[k.key]); const d = Math.round((a - b) * 100) / 100;
      const dir = d > 0 ? 'up' : d < 0 ? 'down' : 'flat';
      const good = dir === 'flat' || k.upGood == null ? null : (dir === 'up') === k.upGood;
      const pctv = b ? Math.round((d / Math.abs(b)) * 100) : (a ? null : 0);
      out.kpis.push({ key: k.key, label: k.label, before: b, after: a, delta: d, direction: dir, word: dir === 'flat' ? 'unchanged' : dir, good, pct: pctv, money: k.money, unit: k.unit || null, text: dir === 'flat' ? `${k.label} unchanged at ${fmtVal(k, a)}` : `${k.label} ${dir} ${fmtVal(k, Math.abs(d))} to ${fmtVal(k, a)}${pctv != null && Math.abs(pctv) < 1000 ? ` (${d > 0 ? '+' : '−'}${Math.abs(pctv)}%)` : ''}` });
    });
    const before = new Set(prev.decision_keys || []), nowKeys = new Set(cur.decision_keys || []);
    const live = cur._decisions || [];
    out.new_decisions = [...nowKeys].filter((x) => !before.has(x)).map((key) => { const d = live.find((x) => decKey(x) === key) || {}; return { key, layer: d.layer || key.split('|')[0], title: d.title || key.split('|').slice(1).join('|'), severity: d.severity || 0, severityLabel: d.severityLabel || '', amount: d.amount || 0 }; }).sort((a, b) => b.severity - a.severity);
    out.resolved_decisions = [...before].filter((x) => !nowKeys.has(x)).map((key) => ({ key, layer: key.split('|')[0], title: key.split('|').slice(1).join('|') }));
    out.changed = out.kpis.filter((x) => x.direction !== 'flat').length + out.new_decisions.length + out.resolved_decisions.length + (out.approvals.delta ? 1 : 0);
  }
  out.speak = speakOf(out);
  if (!now) _cache = { key: cacheKey, diff: out };
  return out;
}

function speakOf(x) {
  if (x.first) return 'This is the first day I have a snapshot for — nothing earlier to compare against yet. From tomorrow I will tell you what moved.';
  const since = x.days === 1 ? 'yesterday' : `${x.since} (${x.days} days ago)`;
  const moved = x.kpis.filter((k) => k.direction !== 'flat');
  const parts = [];
  if (!x.changed) return `Nothing has changed since ${since}: same numbers, same open items, ${x.approvals.after} approvals waiting.`;
  const headline = moved.filter((k) => ['cash', 'receivables_overdue', 'payables_overdue', 'revenue'].includes(k.key)).slice(0, 4).map((k) => k.text.replace(/^./, (c) => c.toLowerCase()));
  if (headline.length) parts.push(`Since ${since}: ${headline.join('; ')}.`); else parts.push(`Since ${since}, ${moved.length} indicator${moved.length === 1 ? '' : 's'} moved.`);
  const rest = moved.filter((k) => !['cash', 'receivables_overdue', 'payables_overdue', 'revenue'].includes(k.key)).slice(0, 4);
  if (rest.length) parts.push(rest.map((k) => k.text).join('; ') + '.');
  if (x.new_decisions.length) { const crit = x.new_decisions.filter((d) => d.severity >= 4); parts.push(`${x.new_decisions.length} new item${x.new_decisions.length === 1 ? '' : 's'} on the list${crit.length ? `, ${crit.length} critical or high` : ''}: ${x.new_decisions.slice(0, 3).map((d) => d.title).join('; ')}.`); }
  if (x.resolved_decisions.length) parts.push(`${x.resolved_decisions.length} item${x.resolved_decisions.length === 1 ? '' : 's'} cleared: ${x.resolved_decisions.slice(0, 2).map((d) => d.title).join('; ')}.`);
  if (x.approvals.delta) parts.push(`Approvals ${x.approvals.delta > 0 ? 'up' : 'down'} by ${Math.abs(x.approvals.delta)} to ${x.approvals.after}.`);
  return parts.join(' ');
}

/* ---------- the same answer, written in Bangla ----------

   "গতকাল থেকে কী বদলেছে" is the second question anyone asks in বাংলা and this
   plug-in had no Bangla at all — it did not even match, so the boss got
   silence. The sentence is written here from the same diff the English side
   reads, never translated from it, and every figure goes through the Bangla
   money formatter: converting only the digits leaves "৳১৩ L" with an English
   unit word, which is this layer's oldest trap. */
const KPI_BN = {
  cash: 'নগদ ও ব্যাংক', receivables: 'পাওনা', receivables_overdue: 'মেয়াদোত্তীর্ণ পাওনা',
  payables: 'দেনা', payables_overdue: 'মেয়াদোত্তীর্ণ দেনা', revenue: 'এ মাসের আয়',
  headcount: 'জনবল', present_pct: 'আজকের উপস্থিতি', pipeline_value: 'পাইপলাইন',
  tasks_overdue: 'সময় পার হওয়া কাজ', projects_at_risk: 'ঝুঁকিতে থাকা প্রকল্প',
};
const bnValOf = (k, v) => (k.money ? bnMoney(v) : k.unit === '%' ? `${bnDigits(v)} শতাংশ` : bnDigits(v));
const bnKpiLine = (k) => {
  const label = KPI_BN[k.key] || k.label;
  if (k.direction === 'flat') return `${label} আগের মতোই ${bnValOf(k, k.after)}`;
  const verb = k.direction === 'up' ? 'বেড়ে' : 'কমে';
  return `${label} ${bnValOf(k, Math.abs(k.delta))} ${verb} ${bnValOf(k, k.after)} হয়েছে`;
};
const HEAD = ['cash', 'receivables_overdue', 'payables_overdue', 'revenue'];
function speakBn(x) {
  if (x.first) return 'আজকেরটাই আমার প্রথম স্ন্যাপশট — তুলনা করার মতো আগের কোনো দিন নেই। কাল থেকে কী বদলাল তা বলতে পারব।';
  const since = x.days === 1 ? 'গতকাল থেকে' : `${bnDigits(x.days)} দিন আগের তুলনায়`;
  if (!x.changed) return `${since} কিছুই বদলায়নি: একই হিসাব, একই খোলা কাজ, ${bnDigits(x.approvals.after)}টি অনুমোদন অপেক্ষমাণ।`;
  const moved = x.kpis.filter((k) => k.direction !== 'flat');
  const parts = [];
  const head = moved.filter((k) => HEAD.includes(k.key)).slice(0, 4);
  if (head.length) parts.push(`${since}: ${head.map(bnKpiLine).join('; ')}।`);
  else parts.push(`${since} ${bnDigits(moved.length)}টি সূচক নড়েছে।`);
  const rest = moved.filter((k) => !HEAD.includes(k.key)).slice(0, 4);
  if (rest.length) parts.push(rest.map(bnKpiLine).join('; ') + '।');
  if (x.new_decisions.length) {
    const crit = x.new_decisions.filter((d) => d.severity >= 4);
    parts.push(`তালিকায় ${bnDigits(x.new_decisions.length)}টি নতুন বিষয় যোগ হয়েছে${crit.length ? `, তার ${bnDigits(crit.length)}টি জরুরি` : ''}।`);
  }
  if (x.resolved_decisions.length) parts.push(`${bnDigits(x.resolved_decisions.length)}টি বিষয় নিষ্পত্তি হয়েছে।`);
  if (x.approvals.delta) parts.push(`অনুমোদন ${bnDigits(Math.abs(x.approvals.delta))}টি ${x.approvals.delta > 0 ? 'বেড়ে' : 'কমে'} ${bnDigits(x.approvals.after)}টি হয়েছে।`);
  return parts.join(' ');
}

/* ---------- Ask EON domain ---------- */
const RX = /\b(since (yesterday|last (time|snapshot|check))|what('s| has| is|s)? (changed|new|different|moved)|what changed|compared? (to|with) yesterday|daily (delta|diff|change)|anything new|any(thing)? changes?|new since|day[- ]over[- ]day)\b/i;
/* বাংলা marks "since" with a postposition (থেকে) after the day, so the cue is
   the day plus the change verb; কী নতুন / কী বদলেছে stand on their own. */
const RX_BN = /(গতকাল|গত ?কাল|আগের দিন|কালকের) ?(থেকে|তুলনায়|চেয়ে)?[^।]{0,20}(বদল|পরিবর্তন|নতুন|নড়|পাল্টা)|কী (বদলেছে|বদলাল|পাল্টেছে|নতুন)|কি (বদলেছে|বদলাল|পাল্টেছে|নতুন)|নতুন কী (এসেছে|আছে|হয়েছে)|কী কী (বদলেছে|পাল্টেছে)/;
function answer(q) {
  const s = String(q || '');
  const bn = /[ঀ-৿]/.test(s) && RX_BN.test(s);
  if (!bn && !RX.test(s)) return null;
  const E = erp(); if (!E || !E.dataset()) return null;
  const x = diff(); if (!x) return null;
  const detail = [];
  if (bn) {
    if (x.first) detail.push(`${x.date} তারিখের স্ন্যাপশট রাখা হলো। কাল আবার জিজ্ঞেস করুন।`);
    else {
      x.kpis.filter((k) => k.direction !== 'flat').forEach((k) => detail.push(`${k.good === false ? '⚠ ' : ''}${bnKpiLine(k)}`));
      if (!x.kpis.some((k) => k.direction !== 'flat')) detail.push('সব সূচক আগের মতোই।');
      x.new_decisions.slice(0, 6).forEach((d) => detail.push(`নতুন: ${d.title}`));
      x.resolved_decisions.slice(0, 6).forEach((d) => detail.push(`নিষ্পত্তি: ${d.title}`));
      detail.push(`অনুমোদন: ${bnDigits(x.approvals.before)} → ${bnDigits(x.approvals.after)}`);
    }
    return { speak: speakBn(x), detail, view: 'brief', data: x, actions: [{ label: 'ব্রিফ খুলুন', kind: 'navigate', href: 'index.html#since' }] };
  }
  if (x.first) detail.push(`Snapshot saved for ${x.date}. Ask again tomorrow.`);
  else {
    x.kpis.filter((k) => k.direction !== 'flat').forEach((k) => detail.push(`${k.good === false ? '⚠ ' : ''}${k.text}`));
    if (!x.kpis.some((k) => k.direction !== 'flat')) detail.push('All KPIs unchanged.');
    x.new_decisions.slice(0, 6).forEach((d) => detail.push(`New: ${d.title}${d.severityLabel ? ` (${d.severityLabel})` : ''}`));
    x.resolved_decisions.slice(0, 6).forEach((d) => detail.push(`Resolved: ${d.title}`));
    detail.push(`Approvals: ${x.approvals.before} → ${x.approvals.after}`);
  }
  return { speak: x.speak, detail, view: 'brief', data: x, actions: [{ label: 'Open the brief', kind: 'navigate', href: 'index.html#since' }] };
}
(function registerDomain() {
  if (!W) return;
  const d = { id: 'since', priority: 95, answer };
  try { if (W.EonDomains && typeof W.EonDomains.register === 'function') W.EonDomains.register(d); else (W.__eonDomainQueue = W.__eonDomainQueue || []).push(d); } catch {}
})();

/* ---------- decision provider ---------- */
addProvider(() => {
  if (_busy) return [];
  const E = erp(); if (!E || !E.dataset()) return [];
  let x = null; try { x = diff(); } catch { return []; }
  if (!x || x.first) return [];
  const crit = x.new_decisions.filter((d) => d.severity >= 4);
  if (!crit.length) return [];
  return [{
    id: 'since-new-critical', layer: 'ops', severity: 3, since_plugin: true,
    title: `${crit.length} new critical item${crit.length === 1 ? '' : 's'} since ${x.since}: ${crit.slice(0, 3).map((d) => d.title).join('; ')}`,
    why: crit.slice(0, 4).map((d) => `${d.title} (${d.severityLabel || 'high'})`).concat(x.resolved_decisions.length ? [`${x.resolved_decisions.length} earlier item(s) cleared`] : []),
    recommend: crit.length === 1 ? 'This was not on your list yesterday — look at it first.' : 'These were not on your list yesterday — take them before the routine items.',
    amount: crit.reduce((n, d) => n + (+d.amount || 0), 0),
    actions: [{ label: 'See the brief', kind: 'navigate', href: 'index.html#since' }],
  }];
});

/* ---------- brief panel ---------- */
function renderPanel() {
  const A = W && W.EonApp; const esc = A && A.esc ? A.esc : (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const x = diff(); if (!x) return '<div class="empty">no dataset yet</div>';
  if (x.first) return `<div class="empty">first day — no earlier snapshot. Snapshot for ${esc(x.date)} saved; tomorrow this card shows what moved.</div>`;
  const rows = x.kpis.map((k) => {
    const color = k.direction === 'flat' ? 'var(--muted)' : k.good === true ? 'var(--green)' : k.good === false ? 'var(--red)' : 'var(--text)';
    const arrow = k.direction === 'up' ? '▲' : k.direction === 'down' ? '▼' : '=';
    const dv = k.direction === 'flat' ? '—' : `${k.delta > 0 ? '+' : '−'}${fmtVal(k, Math.abs(k.delta))}`;
    return `<div class="item" style="grid-template-columns:1fr auto auto;padding:6px 0"><div class="t" style="font-size:13px">${esc(k.label)}</div><div class="num" style="color:${color};font-size:12px">${arrow} ${esc(dv)}</div><div class="num" style="font-size:12px;color:var(--muted);min-width:70px;text-align:right">${esc(fmtVal(k, k.after))}</div></div>`;
  }).join('');
  const news = x.new_decisions.slice(0, 4).map((d) => `<div class="hint" style="color:${d.severity >= 4 ? 'var(--red)' : 'var(--text)'}">+ ${esc(d.title)}</div>`).join('');
  const done = x.resolved_decisions.slice(0, 3).map((d) => `<div class="hint" style="color:var(--green)">✓ ${esc(d.title)}</div>`).join('');
  const ap = x.approvals.delta ? `<div class="hint">Approvals ${x.approvals.delta > 0 ? 'up' : 'down'} ${Math.abs(x.approvals.delta)} → ${x.approvals.after}</div>` : '';
  return `<div class="hint" style="margin-bottom:6px">vs ${esc(x.since)}${x.days > 1 ? ` · ${x.days} days` : ''} · ${x.changed ? `${x.changed} change${x.changed === 1 ? '' : 's'}` : 'nothing moved'}</div>${rows}${news || done || ap ? `<div style="margin-top:8px">${news}${done}${ap}</div>` : ''}<div class="chips" style="margin-top:8px"><span class="chip" data-q="What changed since yesterday?">Tell me</span></div>`;
}
const PANEL = { id: 'since', title: 'Since yesterday', order: 8, render: () => { try { const x = diff(); PANEL.title = x && !x.first ? `Since ${x.since}` : 'Since yesterday'; } catch {} return renderPanel(); } };
(function registerPanel() {
  if (!W) return;
  const reg = () => { try { W.EonApp && W.EonApp.registerPanel && W.EonApp.registerPanel('brief', PANEL); } catch {} };
  if (W.EonApp && W.EonApp.registerPanel) reg(); else W.addEventListener('eon:app-ready', reg);
})();

/* ---------- take today's snapshot on every dataset load (once per day per source) ---------- */
(function autoSnapshot() {
  if (!W) return;
  let last = null;
  const take = () => { try { const E = erp(); const D = E && E.dataset(); if (!D) return; const { source, company } = scopeOf(D); const key = `${source}|${company}|${todayOf(D)}`; if (key === last) return; last = key; snapshot(); } catch (e) { console.warn('[EON since] snapshot failed:', e); } };
  W.addEventListener('eon:erp-data', () => setTimeout(take, 0));
  W.addEventListener('eon:erp-company', () => setTimeout(take, 0));
  if (erp() && erp().dataset()) setTimeout(take, 0);
})();

export const EonSince = { snapshot, diff, history: () => load(), reset: () => { save([]); _cache = null; }, KPIS };
if (W) W.EonSince = Object.assign(W.EonSince || {}, EonSince);
export default EonSince;
