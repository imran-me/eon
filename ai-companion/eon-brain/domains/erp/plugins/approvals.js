/* ============================================================
   EON · approvals — "আজিজুল হকের ছুটি অ্যাপ্রুভ করো",
   "তানিয়ার খরচের বিলটা অ্যাপ্রুভ করো", "reject Imran's advance request".

   Finds that person's PENDING item in the ERP (a leave, an expense bill,
   an employee request), says exactly what it is, and approves or rejects
   it through the ERP's own endpoints — role.leaves.approve,
   role.expenses.approve (which posts it to the ledger),
   role.employee-requests.approve / reject — only after the boss's yes
   (act.js's gate; approvals are never auto-confirmed).
   ============================================================ */
import { propose } from './act.js';
import { latinSkeleton, banglaSkeleton, CASE_ENDING } from './act.js';
import { speaksBangla, normalize } from './voicefix.js';

const N = () => (typeof window !== 'undefined' ? window.EonNavigator : null);
const D0 = () => (typeof window !== 'undefined' && window.EonErp && window.EonErp.dataset ? window.EonErp.dataset() : null);
const T = (bn, en, b) => (bn ? b : en);

const APPROVE = /অ্যাপ্রুভ|এপ্রুভ|approve|অনুমোদন|মঞ্জুর|পাস করো|ok করে দাও/i;
const REJECT = /রিজেক্ট|reject|বাতিল করো|নামঞ্জুর|decline/i;
const KIND = [
  ['leave', /ছুটি|লিভ|leave/i],
  ['expense', /খরচ|বিল|এক্সপেন্স|expense|bill/i],
  ['request', /রিকোয়েস্ট|রিকুয়েস্ট|request|অ্যাডভান্স|advance|লোন|loan|আবেদন/i],
];

/** people named in the sentence — by sound, either script; first name enough */
// the words of the order itself are never a name ("বিল" is not Billal)
const NOT_NAME = /^(ছুটি|লিভ|leave|খরচ|খরচের|বিল|বিলটা|বিলের|এক্সপেন্স|expense|bill|রিকোয়েস্ট|request|অ্যাডভান্স|advance|লোন|loan|আবেদন|অ্যাপ্রুভ|approve|রিজেক্ট|reject|করো|করে|দাও|পেন্ডিং|pending|the|of|for|sick|অনুমোদন|মঞ্জুর|বাতিল)$/i;
/* one sound for both scripts: Bangla জ is written z in English names (আজিজুল = Azizul), হক is Haque (q = k) */
const same = (k) => k.replace(/z/g, 'j').replace(/q/g, 'k').replace(/v/g, 'b').replace(/x/g, 'ks');
function people(raw, D) {
  const words = raw.split(/[\s,।]+/).filter(Boolean).map((w) => w.replace(CASE_ENDING, '').replace(/(এর|ের|য়ের|র)$/u, '')).filter((w) => !NOT_NAME.test(w));
  const keys = words.map((w) => same(/[ঀ-৿]/.test(w) ? banglaSkeleton(w) : latinSkeleton(w))).filter((k) => k.length >= 2);
  // the person whose name has the most words said — "আজিজুল হক" is Azizul Haque, not every Azizul
  const scored = (D.employees || []).map((e) => ({ e, n: String(e.name).split(/\s+/).filter((p) => { const k = same(latinSkeleton(p.replace(/^(mr|md|mrs|ms)\.?$/i, ''))); return k.length >= 2 && keys.includes(k); }).length })).filter((x) => x.n > 0);
  const top = Math.max(0, ...scored.map((x) => x.n));
  return scored.filter((x) => x.n === top).map((x) => x.e);
}

async function token() {
  const r = await fetch(location.origin + N().url('/{role}/dashboard').replace(location.origin, ''), { credentials: 'same-origin' });
  const html = await r.text();
  const m = html.match(/<meta name="csrf-token" content="([^"]+)"/);
  return m ? m[1] : '';
}

async function answer(q) {
  const raw = String(q || '').trim(); const s = normalize(raw); const bn = speaksBangla(raw);
  const approve = APPROVE.test(s), reject = !approve && REJECT.test(s);
  if (!approve && !reject) return null;
  const kind = (KIND.find(([, rx]) => rx.test(s)) || [])[0];
  if (!kind || !N()) return null;
  const D = D0(); if (!D) return null;
  const who = people(raw, D);
  const ids = new Set(who.map((e) => +e.id));
  const name = (id) => ((D.employees || []).find((e) => +e.id === +id) || {}).name || '';

  let items = [];
  if (kind === 'leave') items = (D.leaves || []).filter((l) => String(l.status).toLowerCase() === 'pending' && (!ids.size || ids.has(+l.user_id)))
    .map((l) => ({ id: l.id, path: `/{role}/leaves/${l.id}/approve`, label: `${name(l.user_id)} — ${l.leave_type || 'leave'}, ${l.start_date}${l.end_date && l.end_date !== l.start_date ? ' → ' + l.end_date : ''} (${l.days} ${T(bn, 'days', 'দিন')})`, rejectable: false }));
  if (kind === 'expense') items = (D.expenses || []).filter((e) => String(e.approval_status).toLowerCase() === 'pending' && (!ids.size || ids.has(+e.user_id) || who.some((w) => String(e.user_name || '').toLowerCase().includes(String(w.name).toLowerCase().split(' ')[0]))))
    .map((e) => ({ id: e.id, path: `/{role}/expenses/${e.id}/approve`, label: `${e.user_name || ''} — ${e.title || e.category}, ৳${Number(e.amount).toLocaleString('en-IN')} (${e.expense_date})`, rejectable: false, money: true }));
  if (kind === 'request') items = (D.employee_requests || []).filter((r) => ['pending', 'under_review'].includes(String(r.status).toLowerCase()) && (!ids.size || ids.has(+r.user_id)))
    .map((r) => ({ id: r.id, path: `/{role}/employee-requests/${r.id}/${reject ? 'reject' : 'approve'}`, label: `${name(r.user_id)} — ${r.category || r.request_type}${r.amount ? ', ৳' + Number(r.amount).toLocaleString('en-IN') : ''}`, rejectable: true }));

  const what = { leave: T(bn, 'leave', 'ছুটি'), expense: T(bn, 'expense bill', 'খরচের বিল'), request: T(bn, 'request', 'রিকোয়েস্ট') }[kind];
  if (!items.length) return { speak: T(bn, `There is no pending ${what}${who.length ? ' for ' + who.map((w) => w.name).join(', ') : ''}.`, `${who.length ? who.map((w) => w.name).join(', ') + '-এর ' : ''}কোনো পেন্ডিং ${what} নেই।`), detail: [] };
  if (items.length > 1) return { speak: T(bn, `${items.length} pending ${what}s — which one?`, `${items.length}টা পেন্ডিং ${what} — কোনটা?`), detail: items.slice(0, 6).map((x, i) => `${i + 1}. ${x.label}`) };
  const it = items[0];
  if (reject && !it.rejectable) {
    const href = N().url(kind === 'leave' ? '/{role}/leaves' : '/{role}/expenses');
    return { speak: T(bn, `The ERP has no reject button for a ${what} here — opening the list so you can change it there.`, `ERP-তে এখানে ${what} রিজেক্ট করার বোতাম নেই — তালিকাটা খুলছি, ওখান থেকে বদলে দিন।`), detail: [it.label], navigate: href };
  }
  const action = N().url(it.path);
  return propose({
    what: 'approval', money: !!it.money, missing: [], lang: bn ? 'bn' : 'en',
    form: { action, method: 'POST', token: await token(), fields: [], from: N().url(kind === 'leave' ? '/{role}/leaves' : kind === 'expense' ? '/{role}/expenses' : '/{role}/employee-requests').replace(location.origin, '') },
    values: reject ? { remarks: (raw.match(/(?:কারণ|because|reason)[:\s]+(.+)$/i) || [null, T(bn, 'Rejected by the boss', 'বস রিজেক্ট করেছেন')])[1] } : {},
    summary: `${reject ? 'Reject' : 'Approve'} ${it.label}${kind === 'expense' && !reject ? ' — it will be posted to the ledger' : ''}`,
    summary_bn: `${it.label} — ${reject ? 'রিজেক্ট' : 'অ্যাপ্রুভ'} করব${kind === 'expense' && !reject ? ' (ledger-এ পোস্ট হবে)' : ''}`,
  });
}

if (typeof window !== 'undefined') {
  window.EonApprovals = { answer };
  (window.__eonDomainQueue = window.__eonDomainQueue || []).push({ id: 'approvals', priority: 99.66, answer });
}
export default { answer };
