/* ============================================================
   EON · contract flight — offload and deport, and finding a passenger.

   "offload Amit Baidya, immigration, agent's fault, hold"
   "deport passport A15132091, we bear it"
   "অমিত বৈদ্য ইমিগ্রেশন অফলোড, এজেন্টের দোষ"
   "open Amit Baidya's flight"

   Offload and deport post to the ERP's own endpoint
   (ContractFlightRebookController::offload,
   POST contract-flight-passengers/{id}/offload) with the three things
   that decide the case: the event (immigration offload, boarding offload,
   deported), whose fault it is (company → we bear it, agent → bill the
   agent), and what happens next (hold, close, rebook; a deportation is
   always held, by the ERP's rule).

   The same form also takes per-ticket refunds, service refunds, charges
   and deport costs per vendor. Those are itemised against the passenger's
   own tickets, and a figure attached to the wrong line is a wrong journal —
   so when the boss names amounts, EON opens that passenger's flight with
   the Offload form to be itemised there, and says so, rather than guessing
   which line each figure belongs to.

   Always through the confirmation gate: an offload moves money.
   ============================================================ */
import { url } from './navigator.js';
import { propose, latinSkeleton, banglaSkeleton, CASE_ENDING } from './act.js';
import { csrf } from './ticket.js';
import { normalize, speaksBangla } from './voicefix.js';
import { remember, recall, POINTS_BACK } from './context.js';

const D0 = () => (typeof window !== 'undefined' && window.EonErp && window.EonErp.dataset ? window.EonErp.dataset() : null);
const BN = /[ঀ-৿]/;
const latinDigits = (s) => String(s || '').replace(/[০-৯]/g, (d) => String('০১২৩৪৫৬৭৮৯'.indexOf(d)));

const EVENTS = [
  { key: 'deported', re: /\bdeport(?:ed|ation)?\b|ডিপোর্ট/i, en: 'deported', bn: 'ডিপোর্ট' },
  { key: 'boarding_denied', re: /\bboarding\s*(?:offload|denied)\b|\bdenied boarding\b|বোর্ডিং\s*(?:অফলোড|অফ\s*লোড)/i, en: 'boarding offload', bn: 'বোর্ডিং অফলোড' },
  { key: 'immigration_offload', re: /\boff-?load(?:ed)?\b|অফলোড|অফ\s*লোড/i, en: 'immigration offload', bn: 'ইমিগ্রেশন অফলোড' },
];
const FAULT_AGENT = /\bagent\s*(?:er)?\s*(dosh|dos|fault|dai|day)\b|\b(dosh|fault)\s+agent\b|দোষ\s*এজেন্টের|এজেন্ট\s*দায়ী|\b(agent'?s?|passenger'?s?)\s+(fault|side)\b|\bbill the agent\b|\bagent (pays|bears)\b|এজেন্টের\s*(দোষ|দায়|দায়)|যাত্রীর\s*(দোষ|দায়|দায়)|এজেন্ট\s*(দেবে|দিবে|বহন)/i;
const FAULT_COMPANY = /\b(amader|office|company)\s*(?:er)?\s*(dosh|dos|fault|vul|bhul)\b|\bamra\s*(dibo|debo|bear korbo)\b|দোষ\s*আমাদের|আমাদের\s*ভুল|অফিস\s*(দেবে|দিবে|বহন)|\b(our|company'?s?)\s+(fault|side)\b|\bwe (bear|pay)\b|\bwe'll bear\b|আমাদের\s*(দোষ|দায়|দায়)|আমরা\s*(বহন|দেব|দিব)/i;
const NEXT = [
  ['rebook', /\bre-?book\b|রিবুক/i],
  ['close', /\bclose\b|\bcase close\b|ক্লোজ|কেস\s*বন্ধ/i],
  ['hold', /\bhold\b|হোল্ড/i],
];
const HAS_AMOUNT = /\d{3,}/;
const OPEN = /\b(open|show|where is|take me|find|dekhao|dekhan|kholo|koi|kothay)\b|(দেখাও|খোলো|খুলুন|কোথায়|কোথায়)/i;
const FLIGHTISH = /\bflight\b|ফ্লাইট/i;

/* ---------- which passenger ---------- */
const STOP = /^(offload|offloaded|deport|deported|immigration|boarding|denied|agent|agents|agent's|our|we|fault|side|bear|bears|pay|pays|hold|close|rebook|the|a|for|passenger|passport|it|its|open|show|flight|flights|his|her|and|on|of|case|bill)$/i;
export function findPassenger(D, text) {
  const list = (D && D.contract_flight_passengers) || [];
  if (!list.length) return null;
  const s = latinDigits(text);
  const pass = s.match(/\b([A-Z]{1,2}\d{6,9})\b/i);
  if (pass) {
    const hit = list.filter((p) => String(p.passport_no || '').toUpperCase() === pass[1].toUpperCase());
    if (hit.length) return latest(hit);
  }
  const wanted = [];
  for (const w of s.replace(/[?.!,।:;'’"]/g, ' ').split(/\s+/).filter(Boolean)) {
    if (STOP.test(w)) continue;
    const sks = BN.test(w) ? [banglaSkeleton(w), banglaSkeleton(w.replace(CASE_ENDING, '')), banglaSkeleton(w.replace(/র$/, ''))] : [latinSkeleton(w)];
    const real = w.replace(/[^\p{L}\p{M}]/gu, '').length >= 4;
    for (const sk of sks) if ((sk.length >= 3 || (sk.length === 2 && real)) && !wanted.includes(sk)) wanted.push(sk);
  }
  if (!wanted.length) return null;
  const byHolder = new Map();
  for (const p of list) {
    const parts = String(p.name || '').split(/\s+/).map(latinSkeleton).filter((x) => x.length >= 2);
    const hits = wanted.filter((sk) => parts.includes(sk)).length;
    if (hits) (byHolder.get(p.passport_holder_id) || byHolder.set(p.passport_holder_id, { hits, rows: [] }).get(p.passport_holder_id)).rows.push(p);
  }
  const ranked = [...byHolder.values()].sort((a, b) => b.hits - a.hits);
  if (!ranked.length) return null;
  if (ranked.length > 1 && ranked[1].hits === ranked[0].hits) return { ambiguous: ranked.filter((x) => x.hits === ranked[0].hits).slice(0, 5).map((x) => latest(x.rows)) };
  return latest(ranked[0].rows);
}
/* a passenger rebooked onto a later flight has a row per flight — the live one is the latest */
const latest = (rows) => rows.slice().sort((a, b) => String(b.departure_at || '').localeCompare(String(a.departure_at || '')) || b.id - a.id)[0];

const flightPage = (p) => url(`/{role}/contract-flights/${p.contract_flight_id}/passengers`);

async function answer(q) {
  const s0 = String(q || '').trim();
  if (!s0) return null;
  const s = latinDigits(normalize(s0));
  const bn = speaksBangla(s0);
  const T = (en, b) => (bn ? b : en);
  const ev = EVENTS.find((e) => e.re.test(s));
  const opening = !ev && OPEN.test(s) && FLIGHTISH.test(s);
  if (!ev && !opening) return null;
  // "how many were offloaded this month" is a question about a figure
  if (ev && /\b(what are|what were|show|all|offloaded flights?|deported flights?|how many|how much|count|list|which|who were|kotojon|koto jon|koyjon|koijon|kojon|koto|list)\b|কোন কোন|কোনগুলো|সব |কতজন|কয়জন|কয়জন|কারা|তালিকা/i.test(s)) {
    // a question about who was offloaded / deported: the dashboard lists them under Offloaded / Deported
    const href = url('/{role}/contract-flights/dashboard');
    return { speak: T('Offloaded and deported passengers are listed under Offloaded / Deported on the flight dashboard — opening it.', 'অফলোড আর ডিপোর্ট হওয়া যাত্রীরা ফ্লাইট ড্যাশবোর্ডের Offloaded / Deported অংশে আছে — খুলছি।'), detail: [], navigate: href, actions: [{ label: T('Flight dashboard', 'ফ্লাইট ড্যাশবোর্ড'), kind: 'erp-open', href }] };
  }

  const D = D0();
  let p = D ? findPassenger(D, s) : null;
  if (!p && POINTS_BACK.test(s) && recall('passenger')) p = recall('passenger');
  if (p && !p.ambiguous) remember({ passenger: p, order: { part: 'contract-flight' } });
  if (!p) {
    if (opening) return null;   // "open the flight board" — the navigator knows that screen
    return {
      speak: T(`Which passenger? Tell me the name or passport number and I will set up the ${ev.en}.`, `কোন যাত্রী? নাম বা পাসপোর্ট নম্বর বলুন, ${ev.bn} সাজিয়ে দেব।`),
      detail: [], actions: [{ label: T('Contract flight board', 'কন্ট্রাক্ট ফ্লাইট বোর্ড'), kind: 'erp-open', href: url('/{role}/contract-flight-board') }],
    };
  }
  if (p.ambiguous) {
    return {
      speak: T(`Which one? These match: ${p.ambiguous.map((x) => x.name).join(', ')}. Say the full name or the passport number.`,
        `কোনজন? মিলছে: ${p.ambiguous.map((x) => x.name).join(', ')}। পুরো নাম বা পাসপোর্ট নম্বর বলুন।`),
      detail: [], actions: p.ambiguous.map((x) => ({ label: x.name, kind: 'erp-open', href: flightPage(x) })),
    };
  }
  const who = `${p.name} (${p.passport_no}, ${p.flight_number})`;
  if (opening) {
    const href = flightPage(p);
    return { speak: T(`Opening ${p.name}'s flight, ${p.flight_number}.`, `${p.name}-এর ফ্লাইট ${p.flight_number} খুলছি।`), detail: [], actions: [{ label: p.flight_number, kind: 'erp-open', href }], navigate: href };
  }

  if (HAS_AMOUNT.test(s.replace(/\b[A-Z]{1,2}\d{6,9}\b/gi, ''))) {
    const href = flightPage(p);
    return {
      speak: T(`Refunds, charges and deport costs are itemised against ${p.name}'s own tickets, so I have opened ${p.flight_number} — use Offload on that passenger's row and put each figure on its line. Nothing has been posted.`,
        `রিফান্ড, চার্জ আর ডিপোর্ট খরচ ${p.name}-এর প্রতিটি টিকিটের লাইনে আলাদা বসাতে হয়, তাই ${p.flight_number} খুলে দিলাম — তাঁর সারিতে অফলোড চেপে প্রতিটা অঙ্ক নিজের লাইনে দিন। কিছুই পোস্ট হয়নি।`),
      detail: [], actions: [{ label: p.flight_number, kind: 'erp-open', href }], navigate: href,
    };
  }

  const fault = FAULT_AGENT.test(s) ? 'agent' : FAULT_COMPANY.test(s) ? 'company' : null;
  const next = ev.key === 'deported' ? 'hold' : ((NEXT.find(([, re]) => re.test(s)) || [])[0] || 'hold');
  const missing = [];
  if (!fault) missing.push(T('whose fault it is — ours (we bear it) or the agent\'s (bill the agent)', 'দায় কার — আমাদের (আমরা বহন করব) নাকি এজেন্টের (এজেন্টকে বিল)'));
  if (fault === 'agent' && !p.agent_id) {
    return { speak: T(`${p.name} has no agent on this flight, so the cost cannot be billed to one — say "our side" if we bear it.`, `${p.name}-এর এই ফ্লাইটে কোনো এজেন্ট নেই, তাই এজেন্টকে বিল করা যাবে না — আমরা বহন করলে "আমাদের দায়" বলুন।`), detail: [] };
  }
  if (missing.length) {
    return { speak: T(`To record ${who} as ${ev.en} I need ${missing[0]}. Say it again with that — nothing has been posted.`, `${who}-কে ${ev.bn} হিসেবে লিখতে জানতে হবে ${missing[0]}। সেটাসহ আবার বলুন — কিছুই পোস্ট হয়নি।`), detail: [] };
  }

  let token;
  try { token = await csrf(); } catch (e) {
    return { speak: T('I have changed nothing — the ERP wants you signed in first.', 'কিছুই বদলাইনি — ইআরপি আগে সাইন ইন চায়।'), detail: [] };
  }
  const nextEn = { hold: 'hold the case', close: 'close the case', rebook: 'rebook them' }[next];
  const nextBn = { hold: 'কেস হোল্ডে', close: 'কেস বন্ধ', rebook: 'রিবুক' }[next];
  const faultEn = fault === 'agent' ? `bill ${p.agent}` : 'we bear it';
  const faultBn = fault === 'agent' ? `${p.agent}-কে বিল` : 'আমরা বহন করব';
  const plan = {
    what: 'offload',
    lang: bn ? 'bn' : 'en',
    money: true,
    missing: [],
    summary: `Record ${who} as ${ev.en} — ${faultEn}, then ${nextEn}`,
    summary_bn: `${who} — ${ev.bn}; ${faultBn}; তারপর ${nextBn}`,
    form: {
      action: url(`/{role}/contract-flight-passengers/${p.id}/offload`), token, method: 'POST',
      fields: [{ name: 'event', label: T('Event', 'ঘটনা') }, { name: 'fault', label: T('Whose side', 'দায়') }, { name: 'next', label: T('Next', 'এরপর') }],
      from: `/{role}/contract-flights/${p.contract_flight_id}/passengers`,
    },
    values: { event: ev.key, fault, next },
    notes: [T('No refunds or charges are attached — if there are any, say so and I will open the passenger\'s row to itemise them instead.', 'কোনো রিফান্ড বা চার্জ যোগ হচ্ছে না — থাকলে বলুন, তাঁর সারি খুলে দেব আলাদা বসানোর জন্য।')],
  };
  return propose(plan);
}

if (typeof window !== 'undefined') {
  window.EonContractFlight = { answer, findPassenger: (t) => findPassenger(D0(), t) };
  (window.__eonDomainQueue = window.__eonDomainQueue || []).push({ id: 'contract-flight', priority: 99.55, answer });
}

export default { answer, findPassenger };
