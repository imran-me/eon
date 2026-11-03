/* ============================================================
   EON · ticket changes — reissue, reschedule, refund, void, cancel return.

   "refund PNR VK8VU3, refund 15000, penalty 2000, customer bears"
   "void invoice INV000021"
   "reschedule PNR VK8VU3 to 2026-11-20, fare difference 3000"
   "VK8VU3 রিফান্ড করো, ফেরত ১৫০০০"

   Everything goes through the ERP's own Ticket Change endpoint
   (TicketChangeController::store, POST ticket-changes/{ticketPurchase}),
   the one the ticket screen's Change button posts to — so the journal,
   the agent's ledger and the contract-flight side are posted by the ERP's
   rules, never by EON's. EON's part is finding the ticket and filling in
   the form:

     1. the ticket — by PNR or invoice number, through the ERP's own lookup
        (ticket-invoice-lookup), which lists every passenger on the sale;
        several passengers and no name to tell them apart → EON asks;
     2. the change — kind, amounts, who bears each cost, a new date;
     3. the gate — a ticket change moves money, so it is always shown and
        waits for a yes (act.js), and a refund with no amount, or a
        reschedule with no date, is asked about rather than posted.

   The `token` the endpoint requires is its idempotency key: a second
   "yes" for the same plan is answered "Already saved" by the ERP itself.
   ============================================================ */
import { url } from './navigator.js';
import { propose } from './act.js';
import { normalize } from './voicefix.js';
import { matchName } from './statement.js';

const BN = /[ঀ-৿]/;
const NB = '(?![ঀ-৿])';
const latinDigits = (s) => String(s || '').replace(/[০-৯]/g, (d) => String('০১২৩৪৫৬৭৮৯'.indexOf(d)));

const KINDS = [
  { kind: 'return_cancel', re: new RegExp(`\\bcancel(?:l?ing)?\\s+(?:the\\s+)?return\\b|(রিটার্ন\\s*(বাতিল|ক্যান্সেল))`, 'i'), en: 'cancel the return', bn: 'রিটার্ন বাতিল' },
  { kind: 'reissue', re: new RegExp(`\\bre-?issue\\b|(রি-?ইস্যু|রিইসু)`, 'i'), en: 'reissue', bn: 'রিইস্যু' },
  { kind: 'reschedule', re: new RegExp(`\\b(reschedule|date change|change the date)\\b|(রিশিডিউল|তারিখ\\s*(বদল|পরিবর্তন))`, 'i'), en: 'reschedule', bn: 'রিশিডিউল' },
  { kind: 'refund', re: new RegExp(`\\brefund\\b|(রিফান্ড)`, 'i'), en: 'refund', bn: 'রিফান্ড' },
  { kind: 'void', re: new RegExp(`\\bvoid\\b|(ভয়েড|ভয়েড)`, 'i'), en: 'void', bn: 'ভয়েড' },
];
const TICKETISH = /\b(ticket|pnr|invoice|inv\d+)\b|টিকিট|টিকেট|পিএনআর|ইনভয়েস|ইনভয়েস/i;

/* ---------- reading the order ---------- */
const AMOUNT = (words) => new RegExp(`(?:${words})\\s*(?:of|is|=|:|tk|taka|৳)?\\s*(?:tk|৳)?\\s*([0-9][0-9,]*(?:\\.[0-9]+)?)`, 'i');
const FIELDS = [
  ['refund', AMOUNT('refund amount|refund|ferot|ferat|feret|ফেরত|রিফান্ড')],
  ['penalty', AMOUNT('penalty|fine|jorimana|পেনাল্টি|জরিমানা')],
  ['service_fee', AMOUNT('service fee|service charge|fee|সার্ভিস ফি|সার্ভিস চার্জ')],
  ['fare_difference', AMOUNT('fare difference|fare diff|difference|ভাড়ার পার্থক্য|ভাড়া পার্থক্য|ডিফারেন্স')],
  ['agent_reversal', AMOUNT('agent reversal|reverse|agent price')],
];
const BEARER_CUSTOMER = /\b(customer|agent|client)\s+(bears?|pays?|will pay)\b|\b(customer|agent|client)\s*(?:e|er)?\s*(dibe|debe|dey|bear korbe)\b|(কাস্টমার|এজেন্ট|ক্লায়েন্ট)\s*(দেবে|দিবে|বহন)/i;
const BEARER_COMPANY = /\b(we|company|office)\s+(bear|bears|pay|pays|will pay)\b|\b(amra|office|company)\s*(dibo|debo|bear korbo|dibe)\b|(আমরা|কোম্পানি|অফিস)\s*(দেব|দিব|বহন)/i;

export function readOrder(q) {
  const s = latinDigits(q);
  const k = KINDS.find((x) => x.re.test(s));
  if (!k) return null;
  const inv = s.match(/\bINV-?\s*0*(\d{1,9})\b/i) || s.match(/\binvoice\s*(?:no\.?|number|#)?\s*0*(\d{1,9})\b/i) || s.match(/(?:ইনভয়েস|ইনভয়েস)\s*0*(\d{1,9})/);
  const invoice = inv ? `INV${String(inv[1]).padStart(6, '0')}` : null;
  /* a PNR is six-ish letters and digits; the kind words and amounts are not */
  let pnr = null;
  const pm = s.match(/\bpnr\s*(?:no\.?|number|#|:)?\s*([A-Z0-9]{5,8})\b/i) || s.match(/(?:পিএনআর)\s*([A-Z0-9]{5,8})\b/i);
  if (pm) pnr = pm[1].toUpperCase();
  if (!pnr && !invoice) {
    const bare = (s.match(/\b(?=[A-Z0-9]*[A-Z])(?=[A-Z0-9]*\d)[A-Z0-9]{6}\b/g) || []).filter((x) => !/^INV/i.test(x));
    if (bare.length) pnr = bare[0];
  }
  const values = { kind: k.kind };
  for (const [name, re] of FIELDS) {
    // the kind word itself is not an amount: "refund PNR …" has no figure after "refund"
    const m = s.match(re);
    if (m) values[name] = Number(m[1].replace(/,/g, ''));
  }
  if (BEARER_CUSTOMER.test(s)) values.bearer = 'customer';
  else if (BEARER_COMPANY.test(s)) values.bearer = 'company';
  const date = s.match(/\b(20\d\d-\d\d-\d\d)(?:[ T](\d\d:\d\d))?\b/);
  if (date) values.new_travel_at = date[2] ? `${date[1]} ${date[2]}` : date[1];
  const name = (s.match(/\bfor\s+(?:passenger\s+)?([A-Za-z][A-Za-z .]{2,40}?)(?=\s*(?:,|$|\b(?:refund|penalty|fee|pnr|invoice|on|to|customer|we)\b))/i) || [])[1] || null;
  return { kind: k, pnr, invoice, values, passenger: name ? name.trim() : null };
}

/* ---------- talking to the ERP ---------- */
export async function csrf() {
  if (typeof document !== 'undefined') {
    const m = document.querySelector('meta[name="csrf-token"]');
    if (m && m.content) return m.content;
  }
  const r = await fetch(url('/{role}/ticket-purchase'), { credentials: 'same-origin', headers: { Accept: 'text/html' } });
  const html = await r.text();
  const m = html.match(/<meta\s+name="csrf-token"\s+content="([^"]+)"/i);
  if (!m) { const e = new Error('sign in'); e.signIn = true; throw e; }
  return m[1];
}
async function lookup(o) {
  const q = o.invoice ? `invoice_no=${encodeURIComponent(o.invoice)}` : `pnr=${encodeURIComponent(o.pnr)}`;
  const r = await fetch(url(`/{role}/ticket-invoice-lookup?${q}`), { credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
  if (r.status === 401 || r.status === 419 || /\/login/.test(r.url)) { const e = new Error('sign in'); e.signIn = true; throw e; }
  try { return await r.json(); } catch { return { success: false, message: `The ERP answered ${r.status}.` }; }
}
const newToken = () => 'eon-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 10);

/* ============================================================
   THE CONVERSATION.

   "do reissue for Rabbi" is a perfectly good order with almost nothing
   in it. EON finds the ticket — through the client's ticket sales when no
   PNR is given, asking which invoice and which passenger when there is
   more than one — opens it in the ERP, and then asks for what the change
   needs, one thing at a time. The boss may answer one thing ("2000"),
   several ("penalty 2000, customer pays, 20 November"), out of order, or
   skip one ("none"). Only when nothing is left does the plan go to the
   confirmation gate; nothing is posted before the yes.

   A held conversation is not a trap: anything that carries no answer
   ("what is our cash position?") is left for the other parts to answer,
   and the conversation waits; "cancel" ends it; four quiet minutes end it.
   ============================================================ */
const LABELS = {
  kind: ['Change', 'পরিবর্তন'], refund: ['Refund to customer', 'কাস্টমারকে ফেরত'], penalty: ['Airline penalty', 'এয়ারলাইন পেনাল্টি'],
  service_fee: ['Our service fee', 'আমাদের সার্ভিস ফি'], fare_difference: ['Fare difference', 'ভাড়ার পার্থক্য'],
  agent_reversal: ['Agent price reversed', 'এজেন্টের দাম ফেরত'], bearer: ['Who bears the costs', 'খরচ কে বহন করবে'],
  new_travel_at: ['New travel date', 'নতুন যাত্রার তারিখ'], note: ['Note', 'নোট'],
};

/* what each change asks for, in the order a ticketing officer would ask it */
const SLOTS = {
  reissue: ['new_travel_at', 'penalty', 'fare_difference', 'service_fee', 'bearer'],
  reschedule: ['new_travel_at', 'penalty', 'fare_difference', 'bearer'],
  refund: ['refund', 'penalty', 'service_fee', 'bearer'],
  void: [],
  return_cancel: [],
};
/* a refund with no amount, or a new date with no date, cannot be posted at all */
const REQUIRED = { refund: ['refund'], reissue: ['new_travel_at'], reschedule: ['new_travel_at'] };

const QUESTION = {
  new_travel_at: ['What is the new travel date?', 'নতুন যাত্রার তারিখ কবে?'],
  penalty: ['How much is the airline penalty? Say "none" if there is none.', 'এয়ারলাইন পেনাল্টি কত? না থাকলে "নেই" বলুন।'],
  fare_difference: ['Any fare difference? Give the amount, or say "none".', 'ভাড়ার পার্থক্য আছে? অঙ্কটা বলুন, না থাকলে "নেই"।'],
  service_fee: ['Our service fee for this? Or "none".', 'এর জন্য আমাদের সার্ভিস ফি কত? না থাকলে "নেই"।'],
  refund: ['How much do we refund to the customer?', 'কাস্টমারকে কত টাকা ফেরত দেব?'],
  bearer: ['Who bears these costs — the customer, or us?', 'এই খরচ কে বহন করবে — কাস্টমার, না আমরা?'],
};
const NONE = /^\s*(none|no|nothing|nil|zero|0|skip|na|nai|nei|nah|no penalty|no fee)\s*[.!]?\s*$|^\s*(নেই|না|নাই|কিছু না|শূন্য|০)\s*[।.!]?\s*$/i;
const CANCEL = /^\s*(cancel|stop|forget it|leave it|never mind|thak|bad dao|baad dao)\s*[.!]?\s*$|^\s*(থাক|বাদ দাও|বাতিল|বাদ|দরকার নেই)\s*[।.!]?\s*$/i;
const FRESH = 4 * 60 * 1000;

/* ---------- a date, the way it is said ---------- */
const EN_M = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];
const BN_M = [['জানুয়ারি', 'জানুয়ারি'], ['ফেব্রুয়ারি', 'ফেব্রুয়ারি'], ['মার্চ'], ['এপ্রিল'], ['মে'], ['জুন'], ['জুলাই'], ['আগস্ট', 'আগষ্ট'], ['সেপ্টেম্বর'], ['অক্টোবর'], ['নভেম্বর'], ['ডিসেম্বর']];
const pad = (n) => String(n).padStart(2, '0');
const ymd = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
export function dateIn(text, today = new Date()) {
  const s = latinDigits(text);
  const time = (s.match(/\b(?:at\s*)?(\d{1,2}):(\d{2})\b/) || []);
  const hhmm = time[1] ? ` ${pad(time[1])}:${time[2]}` : '';
  const iso = s.match(/\b(20\d\d)-(\d\d)-(\d\d)\b/);
  if (iso) return `${iso[1]}-${iso[2]}-${iso[3]}${hhmm}`;
  const rel = (n) => { const d = new Date(today); d.setDate(d.getDate() + n); return ymd(d) + hhmm; };
  if (/\b(day after tomorrow|porshu|porsu)\b|পরশু/i.test(s)) return rel(2);
  if (/\b(tomorrow|agamikal|agami kal)\b|আগামীকাল|আগামী কাল/i.test(s)) return rel(1);
  let mi = -1, day = null;
  const en = s.toLowerCase().match(/\b(\d{1,2})(?:st|nd|rd|th)?\s+(?:of\s+)?([a-z]{3,9})\b|\b([a-z]{3,9})\s+(\d{1,2})(?:st|nd|rd|th)?\b/);
  if (en) {
    const word = (en[2] || en[3] || '').slice(0, 3); const i = EN_M.indexOf(word);
    if (i >= 0) { mi = i; day = +(en[1] || en[4]); }
  }
  if (mi < 0) BN_M.forEach((names, i) => {
    for (const n of names) { const m = s.match(new RegExp(`(\\d{1,2})\\s*(?:তারিখ|শে|ই|লা)?\\s*${n}|${n}\\s*(?:মাসের)?\\s*(\\d{1,2})`)); if (m) { mi = i; day = +(m[1] || m[2]); } }
  });
  if (mi < 0 || !day || day > 31) return null;
  const yr = (s.match(/\b(20\d\d)\b/) || [])[1];
  let d = new Date(yr ? +yr : today.getFullYear(), mi, day);
  if (!yr && d < new Date(today.getFullYear(), today.getMonth(), today.getDate())) d = new Date(today.getFullYear() + 1, mi, day);
  return ymd(d) + hhmm;
}

/* ---------- reading answers ---------- */
function readSlots(text, asking) {
  const s = latinDigits(text);
  const got = {};
  for (const [name, re] of FIELDS) { const m = s.match(re); if (m) got[name] = Number(m[1].replace(/,/g, '')); }
  if (BEARER_CUSTOMER.test(s) || /^\s*(customer|agent|client|the customer|kastomar)\s*\.?$|^\s*(কাস্টমার|এজেন্ট|ক্লায়েন্ট)\s*।?$/i.test(s)) got.bearer = 'customer';
  else if (BEARER_COMPANY.test(s) || /^\s*(we|us|we do|company|office|amra)\s*\.?$|^\s*(আমরা|অফিস|কোম্পানি)\s*।?$/i.test(s)) got.bearer = 'company';
  const d = dateIn(s);
  if (d) got.new_travel_at = d;
  // a bare figure, or "none", answers the question that was asked
  if (asking && got[asking] == null) {
    if (NONE.test(s) && asking !== 'new_travel_at' && asking !== 'bearer') got[asking] = 0;
    else if (asking !== 'new_travel_at' && asking !== 'bearer') {
      const bare = s.replace(/,/g, '').match(/^\s*(?:tk|৳|taka)?\s*(\d+(?:\.\d+)?)\s*(?:tk|taka|টাকা)?\s*[.।]?\s*$/i);
      if (bare) got[asking] = Number(bare[1]);
    }
  }
  return got;
}

/* ---------- the held conversation ---------- */
let convo = null;   // { stage: 'sale'|'pax'|'slots', kind, values, bn, at, sales?, pax?, p?, asking? }
export function conversation() { return convo; }
export function forget() { convo = null; }

function clients(D) {
  const m = new Map();
  for (const s of (D && D.ticket_sales) || []) if (s.client_id != null && s.client) m.set(s.client_id, { id: s.client_id, name: String(s.client).trim() });
  return [...m.values()];
}
const D0 = () => (typeof window !== 'undefined' && window.EonErp && window.EonErp.dataset ? window.EonErp.dataset() : null);
const bnNum = (x, bn) => (bn ? String(x).replace(/[0-9]/g, (d) => '০১২৩৪৫৬৭৮৯'[d]) : String(x));
const money = (n, bn) => { const t = Math.round(+n || 0).toLocaleString('en-IN'); return '৳' + (bn ? t.replace(/[0-9]/g, (d) => '০১২৩৪৫৬৭৮৯'[d]) : t); };
const T = (bn, en, b) => (bn ? b : en);

function signIn(bn) { return { speak: T(bn, 'I have changed nothing — the ERP wants you signed in first. Sign in to the ERP in this browser and ask again.', 'কিছুই বদলাইনি — ইআরপি আগে সাইন ইন চায়। এই ব্রাউজারে ইআরপিতে লগইন করে আবার বলুন।'), detail: [] }; }

/* found the sale: pick the passenger, or ask */
async function fromSale(c, ref) {
  let found;
  try { found = await lookup(ref); } catch (e) { convo = null; return e.signIn ? signIn(c.bn) : { speak: T(c.bn, 'I could not reach the ticket lookup. Nothing was changed.', 'টিকিট খোঁজার পাতায় পৌঁছাতে পারিনি। কিছুই বদলানো হয়নি।'), detail: [] }; }
  const label = ref.invoice || `PNR ${ref.pnr}`;
  if (!found || !found.success) { convo = null; return { speak: T(c.bn, `The ERP has no ticket sale for ${label}. Nothing was changed.`, `${label}-এর কোনো টিকিট বিক্রি ইআরপিতে নেই। কিছুই বদলানো হয়নি।`), detail: found && found.message ? [found.message] : [] }; }
  let pax = (found.passengers || []).filter((p) => p.purchase_id);
  if (ref.pnr) { const same = pax.filter((p) => String(p.pnr || '').toUpperCase().replace(/\s*\(.*$/, '') === ref.pnr); if (same.length) pax = same; }
  if (c.passenger && pax.length > 1) {
    const want = c.passenger.toLowerCase().split(/\s+/).filter((w) => w.length >= 3);
    const hit = pax.filter((p) => want.some((w) => String(p.passenger).toLowerCase().includes(w)));
    if (hit.length) pax = hit;
  }
  if (!pax.length) { convo = null; return { speak: T(c.bn, `${label} has no ticket on it that can be changed.`, `${label}-এ বদলানোর মতো কোনো টিকিট নেই।`), detail: [] }; }
  if (pax.length > 1) {
    Object.assign(c, { stage: 'pax', pax, at: Date.now() });
    return {
      speak: T(c.bn, `${label} has ${pax.length} passengers — which one? ${pax.map((p, i) => `${i + 1}. ${p.passenger}`).join(', ')}.`,
        `${label}-এ ${pax.length} জন যাত্রী — কার টিকিট? ${pax.map((p, i) => `${i + 1}. ${p.passenger}`).join(', ')}।`),
      detail: pax.map((p) => `${p.passenger} · ${p.pnr} · ${p.route}`),
    };
  }
  return startSlots(c, pax[0]);
}

async function startSlots(c, p) {
  Object.assign(c, { stage: 'slots', p, at: Date.now() });
  const href = url(`/{role}/ticket-purchase/${p.purchase_id}`);
  const r = await next(c);
  const opened = T(c.bn, `Opened ${p.passenger}'s ticket ${p.pnr} (${p.route}) for the ${c.kind.en}.`, `${p.passenger}-এর টিকিট ${p.pnr} (${p.route}) ${c.kind.bn}-এর জন্য খুলেছি।`);
  return Object.assign({}, r, { speak: `${opened} ${r.speak}`, navigate: href, actions: [{ label: T(c.bn, 'The ticket', 'টিকিট'), kind: 'erp-open', href }].concat(r.actions || []) });
}

/* ask the next thing, or hand the finished plan to the gate */
function next(c) {
  c.at = Date.now();
  const v = c.values;
  const todo = (SLOTS[c.kind.kind] || []).filter((k) => v[k] == null)
    .filter((k) => !c.detailed || k === 'bearer' || (REQUIRED[c.kind.kind] || []).includes(k))
    // who bears nothing is not a question
    .filter((k) => k !== 'bearer' || (+v.penalty || 0) + (+v.fare_difference || 0) > 0 || (!c.detailed && (v.penalty == null || v.fare_difference == null)));
  const pendingNumbers = todo.filter((k) => k !== 'bearer');
  const ask = pendingNumbers[0] || (todo.includes('bearer') && (+v.penalty || 0) + (+v.fare_difference || 0) > 0 ? 'bearer' : null);
  if (ask) {
    c.asking = ask;
    const q = QUESTION[ask][c.bn ? 1 : 0];
    return { speak: q, detail: said(c), actions: [] };
  }
  return finish(c);
}

function said(c) {
  return Object.entries(c.values).filter(([k, v]) => k !== 'kind' && v != null)
    .map(([k, v]) => `${LABELS[k] ? LABELS[k][c.bn ? 1 : 0] : k}: ${typeof v === 'number' ? money(v, c.bn) : k === 'bearer' ? T(c.bn, v === 'customer' ? 'the customer' : 'we do', v === 'customer' ? 'কাস্টমার' : 'আমরা') : v}`);
}

async function finish(c) {
  convo = null;
  const p = c.p, bn = c.bn;
  let token;
  try { token = await csrf(); } catch (e) { return signIn(bn); }
  const v = Object.assign({}, c.values, { token: newToken(), note: T(bn, "Entered by EON on the boss's order", 'বসের নির্দেশে EON দিয়েছে') });
  for (const k of Object.keys(v)) if (v[k] === 0 && k !== 'refund') delete v[k];   // "none" is an empty field, not a zero line
  const fields = Object.keys(LABELS).map((name) => ({ name, label: LABELS[name][bn ? 1 : 0] }));
  const what = `${c.kind.en} ${p.passenger}'s ticket ${p.pnr} (${p.route}, sold for ${money(p.sale_price)})`;
  const whatBn = `${p.passenger}-এর টিকিট ${p.pnr} (${p.route}, বিক্রি ${money(p.sale_price, true)}) ${c.kind.bn}`;
  return propose({
    what: 'ticket_change', lang: bn ? 'bn' : 'en', money: true, missing: [],
    summary: what.charAt(0).toUpperCase() + what.slice(1), summary_bn: whatBn,
    form: { action: url(`/{role}/ticket-changes/${p.purchase_id}`), token, method: 'POST', fields, from: `/{role}/ticket-purchase/${p.purchase_id}` },
    values: v,
    notes: [T(bn, "The ERP posts the journal and the agent's ledger by its own rules; a second yes is recognised and not posted twice.", 'জার্নাল আর এজেন্টের লেজার ইআরপি নিজের নিয়মে পোস্ট করবে; দুবার হ্যাঁ বললেও একবারই পোস্ট হবে।')],
  });
}

/* an answer inside a held conversation */
async function reply(s, raw) {
  const c = convo;
  if (CANCEL.test(raw) || CANCEL.test(s)) { convo = null; return { speak: T(c.bn, `Left the ${c.kind.en} — nothing was posted.`, `${c.kind.bn} বাদ দিলাম — কিছুই পোস্ট হয়নি।`), detail: [] }; }
  if (c.stage === 'sale') {
    const pick = choose(s, c.sales, (x) => [x.invoice, String(x.invoice).replace(/^INV0*/i, '')]);
    if (!pick) return null;
    return fromSale(c, { invoice: pick.invoice });
  }
  if (c.stage === 'rows') {
    const pick = choose(s, c.rows, (x) => [x.pnr, x.passenger]);
    if (!pick) return null;
    return startSlots(c, pick);
  }
  if (c.stage === 'pax') {
    const pick = choose(s, c.pax, (x) => [x.passenger, x.pnr]);
    if (!pick) return null;
    return startSlots(c, pick);
  }
  const got = readSlots(s, c.asking);
  if (!Object.keys(got).length) return null;   // not an answer — let the others reply, the conversation waits
  Object.assign(c.values, got);
  return next(c);
}

/* "the second", "2", "INV 21", "Karim" — the boss's way of pointing at a list */
const ORD = { first: 1, second: 2, third: 3, fourth: 4, fifth: 5, last: -1, latest: 1, 'প্রথম': 1, 'দ্বিতীয়': 2, 'দ্বিতীয়': 2, 'তৃতীয়': 3, 'তৃতীয়': 3, 'শেষ': -1, prothom: 1, ditiyo: 2 };
function choose(s, list, keys) {
  const low = latinDigits(s).toLowerCase();
  for (const [w, n] of Object.entries(ORD)) if (low.includes(w)) return n === -1 ? list[list.length - 1] : list[n - 1] || null;
  const num = low.match(/^\s*(?:no\.?|number|nombor)?\s*(\d{1,2})\s*[.।]?\s*$/);
  if (num && list[+num[1] - 1]) return list[+num[1] - 1];
  return list.find((x) => keys(x).some((k) => k && low.includes(String(k).toLowerCase()))) || matchName(list.map((x, i) => ({ id: i, name: keys(x)[0] })), s) && list[(matchName(list.map((x, i) => ({ id: i, name: keys(x)[0] })), s) || {}).id] || null;
}

async function answer(q) {
  const raw = String(q || '').trim();
  const s = normalize(raw);
  if (!s) return null;
  if (convo && Date.now() - convo.at > FRESH) convo = null;
  const o = readOrder(s);

  // inside a conversation, anything that is not a new order is an answer to it
  if (convo && !o) return reply(s, raw);
  if (!o) return null;
  // an offload or deportation carries its own refund — that is contract flight's order, not a ticket change
  if (/\b(offload|offloaded|deport|deported)\b|অফলোড|ডিপোর্ট/i.test(s)) return null;
  if (!o.pnr && !o.invoice && /\b(how much|how many|total|did we|have we|koto|kotojon|hoise|hoyeche)\b|কত|কতো|কয়টা|কয়টা|হয়েছে|হয়েছে|হলো/i.test(s)) return null;

  const bn = BN.test(raw);
  const c = { kind: o.kind, values: Object.assign({}, o.values), bn, at: Date.now(), passenger: o.passenger };
  /* an order that already carries details was thought through: ask only what the ERP
     cannot do without. A bare "do reissue for X" is walked through field by field. */
  c.detailed = Object.keys(o.values).some((k) => k !== 'kind') || !!dateIn(s);
  delete c.values.kind; c.values.kind = o.kind.kind;
  const d = dateIn(s); if (d && !c.values.new_travel_at) c.values.new_travel_at = d;
  convo = c;

  if (o.pnr || o.invoice) return fromSale(c, o.invoice ? { invoice: o.invoice } : { pnr: o.pnr });

  /* no reference: whose ticket? A name can be the passenger who flies, the
     client or agent who bought it, or the vendor it was bought from — the
     boss says whichever he remembers. ticket_index carries all three. */
  const D = D0();
  const idx = (D && D.ticket_index) || null;
  if (idx && idx.length) {
    const names = [];
    const seen = new Set();
    for (const r of idx) for (const [role, name] of [['passenger', r.passenger], ['client', r.client], ['vendor', r.vendor]]) {
      if (!name) continue; const k = role + '|' + name; if (seen.has(k)) continue; seen.add(k);
      names.push({ id: k, name: String(name).trim(), role });
    }
    const who = matchName(names, s);
    if (!who) {
      convo = null;
      if (!TICKETISH.test(s)) return null;
      return { speak: T(bn, `Which ticket? Tell me the passenger, the client or agent, the vendor, the PNR or the invoice number and I will set up the ${o.kind.en}.`, `কোন টিকিট? যাত্রী, ক্লায়েন্ট বা এজেন্ট, ভেন্ডর, পিএনআর বা ইনভয়েস নম্বর — যেকোনো একটা বলুন, ${o.kind.bn} সাজিয়ে দেব।`), detail: [], actions: [{ label: T(bn, 'Tickets', 'টিকিট'), kind: 'erp-open', href: url('/{role}/ticket-purchase') }] };
    }
    if (who.ambiguous) {
      convo = null;
      const ROLE = { passenger: ['passenger', 'যাত্রী'], client: ['client', 'ক্লায়েন্ট'], vendor: ['vendor', 'ভেন্ডর'] };
      const list = who.ambiguous.map((x) => `${x.name} (${ROLE[x.role][bn ? 1 : 0]})`).join(', ');
      return { speak: T(bn, `Whose ticket? These match: ${list}. Say the full name.`, `কার টিকিট? মিলছে: ${list}। পুরো নামটা বলুন।`), detail: [] };
    }
    const [role, name] = [who.role, who.name];
    const rows = idx.filter((r) => String(r[role] || '').trim() === name && r.status !== 'cancelled' && r.status !== 'void');
    c.via = { role, name };
    if (!rows.length) { convo = null; return { speak: T(bn, `${name} has no live ticket on record.`, `${name}-এর কোনো চালু টিকিট রেকর্ডে নেই।`), detail: [] }; }
    const asPax = (r) => ({ purchase_id: r.purchase_id, passenger: r.passenger || name, pnr: r.pnr, route: r.route || '—', sale_price: +r.sale_price || 0 });
    if (rows.length === 1) return startSlots(c, asPax(rows[0]));
    const pick = /\b(first|second|third|fourth|fifth|last|latest|1st|2nd|3rd|4th|5th)\b|প্রথম|দ্বিতীয়|তৃতীয়|শেষ/i.test(s)
      ? choose(s.replace(/\b(\d)(st|nd|rd|th)\b/gi, (m, d) => ['', 'first', 'second', 'third', 'fourth', 'fifth'][d] || m), rows, (x) => [x.pnr]) : null;
    if (pick) return startSlots(c, asPax(pick));
    Object.assign(c, { stage: 'rows', rows: rows.map(asPax) });
    const ROLE_EN = { passenger: 'flies on', client: 'bought', vendor: 'sold us' }[role];
    const ROLE_BN = { passenger: 'যাত্রী হিসেবে', client: 'কিনেছেন', vendor: 'আমাদের কাছে বিক্রি করেছেন' }[role];
    const list = rows.slice(0, 6).map((r, i) => `${bnNum(i + 1, bn)}. ${role === 'passenger' ? '' : (r.passenger || '') + ' — '}${r.pnr}, ${r.route || ''}, ${bnNum(String(r.date || '').slice(0, 10), bn)}`);
    return { speak: T(bn, `${name} ${ROLE_EN} ${rows.length} tickets — which one? ${list.join('; ')}${rows.length > 6 ? '; …' : ''}. Say the number, the PNR or the passenger.`,
      `${name} ${ROLE_BN} ${bnNum(rows.length, true)}টা টিকিট — কোনটা? ${list.join('; ')}${rows.length > 6 ? '; …' : ''}। নম্বর, পিএনআর বা যাত্রীর নাম বলুন।`), detail: [] };
  }

  // an older server sends no ticket_index: find the client's ticket sales instead, newest first
  const who = D ? matchName(clients(D), s) : null;
  if (!who) {
    if (!TICKETISH.test(s)) { convo = null; return null; }   // "how much refund…" is not an order to anyone
    convo = null;
    return { speak: T(bn, `Which ticket? Tell me the client's name, the PNR or the invoice number and I will set up the ${o.kind.en}.`, `কোন টিকিট? ক্লায়েন্টের নাম, পিএনআর বা ইনভয়েস নম্বর বলুন, ${o.kind.bn} সাজিয়ে দেব।`), detail: [], actions: [{ label: T(bn, 'Tickets', 'টিকিট'), kind: 'erp-open', href: url('/{role}/ticket-purchase') }] };
  }
  if (who.ambiguous) {
    convo = null;
    return { speak: T(bn, `Whose ticket? These match: ${who.ambiguous.map((x) => x.name).join(', ')}. Say the full name.`, `কার টিকিট? মিলছে: ${who.ambiguous.map((x) => x.name).join(', ')}। পুরো নামটা বলুন।`), detail: [] };
  }
  const sales = ((D && D.ticket_sales) || []).filter((x) => x.client_id === who.id && x.status !== 'cancelled')
    .sort((a, b) => String(b.date).localeCompare(String(a.date)));
  if (!sales.length) { convo = null; return { speak: T(bn, `${who.name} has no ticket sale on record.`, `${who.name}-এর কোনো টিকিট বিক্রি রেকর্ডে নেই।`), detail: [] }; }
  if (sales.length > 1) {
    Object.assign(c, { stage: 'sale', sales });
    // "…for rabbi, the 3rd invoice" / "…INV 94" already said which
    const said = /\b(first|second|third|fourth|fifth|last|latest|1st|2nd|3rd|4th|5th|inv\s*0*\d+|invoice\s*0*\d+)\b|প্রথম|দ্বিতীয়|তৃতীয়|শেষ/i.test(s) ? choose(s.replace(/\b(\d)(st|nd|rd|th)\b/gi, (m, d) => ['', 'first', 'second', 'third', 'fourth', 'fifth'][d] || m), sales, (x) => [x.invoice]) : null;
    if (said) return fromSale(c, { invoice: said.invoice });
    const list = sales.slice(0, 6).map((x, i) => `${bnNum(i + 1, bn)}. ${x.invoice} (${bnNum(x.date, bn)}, ${money(x.total, bn)})`);
    return { speak: T(bn, `${who.name} has ${sales.length} ticket invoices — which one? ${list.join('; ')}.`, `${who.name}-এর ${bnNum(sales.length, true)}টা টিকিট ইনভয়েস — কোনটা? ${list.join('; ')}।`), detail: [] };
  }
  return fromSale(c, { invoice: sales[0].invoice });
}

if (typeof window !== 'undefined') {
  window.EonTicket = { answer, readOrder, conversation, forget, dateIn };
  // above act (99): "refund … customer pays" would otherwise read as a payment order
  (window.__eonDomainQueue = window.__eonDomainQueue || []).push({ id: 'ticket', priority: 99.6, answer });
}

export default { answer, readOrder, conversation, forget, dateIn };
