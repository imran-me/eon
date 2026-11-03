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

const LABELS = {
  kind: ['Change', 'পরিবর্তন'], refund: ['Refund to customer', 'কাস্টমারকে ফেরত'], penalty: ['Airline penalty', 'এয়ারলাইন পেনাল্টি'],
  service_fee: ['Our service fee', 'আমাদের সার্ভিস ফি'], fare_difference: ['Fare difference', 'ভাড়ার পার্থক্য'],
  agent_reversal: ['Agent price reversed', 'এজেন্টের দাম ফেরত'], bearer: ['Who bears the costs', 'খরচ কে বহন করবে'],
  new_travel_at: ['New travel date', 'নতুন যাত্রার তারিখ'], note: ['Note', 'নোট'],
};

async function answer(q) {
  const s = normalize(String(q || '').trim());
  if (!s) return null;
  const o = readOrder(s);
  if (!o || (!TICKETISH.test(s) && !o.pnr && !o.invoice)) return null;
  const bn = BN.test(s);
  const T = (en, b) => (bn ? b : en);

  if (!o.pnr && !o.invoice) {
    const href = url('/{role}/ticket-purchase');
    return {
      speak: T(`Which ticket? Tell me its PNR or invoice number and I will set up the ${o.kind.en}.`, `কোন টিকিট? পিএনআর বা ইনভয়েস নম্বর বলুন, ${o.kind.bn} সাজিয়ে দেব।`),
      detail: [], actions: [{ label: T('Tickets', 'টিকিট'), kind: 'erp-open', href }],
    };
  }

  let found;
  try { found = await lookup(o); } catch (e) {
    if (e.signIn) return { speak: T('I have changed nothing — the ERP wants you signed in first. Sign in to the ERP in this browser and ask again.', 'কিছুই বদলাইনি — ইআরপি আগে সাইন ইন চায়। এই ব্রাউজারে ইআরপিতে লগইন করে আবার বলুন।'), detail: [] };
    return { speak: T(`I could not reach the ticket lookup: ${e.message}. Nothing was changed.`, 'টিকিট খুঁজে দেখার পাতায় পৌঁছাতে পারিনি। কিছুই বদলানো হয়নি।'), detail: [] };
  }
  const ref = o.invoice || `PNR ${o.pnr}`;
  if (!found || !found.success) {
    return { speak: T(`The ERP has no ticket sale for ${ref}. Nothing was changed.`, `${ref}-এর কোনো টিকিট বিক্রি ইআরপিতে নেই। কিছুই বদলানো হয়নি।`), detail: found && found.message ? [found.message] : [] };
  }
  let pax = (found.passengers || []).filter((p) => p.purchase_id);
  if (o.pnr) {
    const same = pax.filter((p) => String(p.pnr || '').toUpperCase().replace(/\s*\(.*$/, '') === o.pnr);
    if (same.length) pax = same;
  }
  if (o.passenger && pax.length > 1) {
    const want = o.passenger.toLowerCase().split(/\s+/).filter((w) => w.length >= 3);
    const hit = pax.filter((p) => want.some((w) => String(p.passenger).toLowerCase().includes(w)));
    if (hit.length) pax = hit;
  }
  if (!pax.length) return { speak: T(`${ref} has no ticket on it that can be changed.`, `${ref}-এ বদলানোর মতো কোনো টিকিট নেই।`), detail: [] };
  if (pax.length > 1) {
    return {
      speak: T(`${ref} has ${pax.length} passengers — which one? Say it again with "for <name>".`, `${ref}-এ ${pax.length} জন যাত্রী — কার টিকিট? নামসহ আবার বলুন।`),
      detail: pax.map((p) => `${p.passenger} · ${p.pnr} · ${p.route}`),
      actions: pax.slice(0, 6).map((p) => ({ label: p.passenger, kind: 'erp-open', href: url(`/{role}/ticket-purchase/${p.purchase_id}`) })),
    };
  }
  const p = pax[0];

  const v = Object.assign({}, o.values, { token: newToken(), note: T(`Entered by EON on the boss's order`, 'বসের নির্দেশে EON দিয়েছে') });
  const missing = [];
  if (o.kind.kind === 'refund' && v.refund == null) missing.push(T('the refund amount', 'ফেরতের পরিমাণ'));
  if (o.kind.kind === 'reschedule' && !v.new_travel_at) missing.push(T('the new travel date (YYYY-MM-DD)', 'নতুন যাত্রার তারিখ (বছর-মাস-দিন)'));

  let token = null;
  try { token = await csrf(); } catch (e) {
    return { speak: T('I have changed nothing — the ERP wants you signed in first.', 'কিছুই বদলাইনি — ইআরপি আগে সাইন ইন চায়।'), detail: [] };
  }
  const fields = Object.keys(LABELS).map((name) => ({ name, label: LABELS[name][bn ? 1 : 0] }));
  const what = `${o.kind.en} ${p.passenger}'s ticket ${p.pnr} (${p.route}, sold for ৳${Math.round(p.sale_price).toLocaleString('en-IN')})`;
  const bnDigits = (x) => String(x).replace(/[0-9]/g, (d) => '০১২৩৪৫৬৭৮৯'[d]);
  const whatBn = `${p.passenger}-এর টিকিট ${p.pnr} (${p.route}, বিক্রি ৳${bnDigits(Math.round(p.sale_price).toLocaleString('en-IN'))}) ${o.kind.bn}`;
  const plan = {
    what: 'ticket_change',
    lang: bn ? 'bn' : 'en',
    money: true,
    missing,
    summary: what.charAt(0).toUpperCase() + what.slice(1),
    summary_bn: whatBn,
    form: { action: url(`/{role}/ticket-changes/${p.purchase_id}`), token, method: 'POST', fields, from: `/{role}/ticket-purchase/${p.purchase_id}` },
    values: v,
    notes: [T('The ERP posts the journal and the agent\'s ledger by its own rules; a second yes is recognised and not posted twice.', 'জার্নাল আর এজেন্টের লেজার ইআরপি নিজের নিয়মে পোস্ট করবে; দুবার হ্যাঁ বললেও একবারই পোস্ট হবে।')],
  };
  if (missing.length) {
    return {
      speak: T(`To ${what} I still need ${missing.join(' and ')}. Say the whole order again with it — nothing has been posted.`,
        `${whatBn} করতে আরও লাগবে: ${missing.join(' ও ')}। সেটাসহ পুরো নির্দেশটা আবার বলুন — কিছুই পোস্ট হয়নি।`),
      detail: [], actions: [{ label: T('Open the ticket', 'টিকিট খুলুন'), kind: 'erp-open', href: url(plan.form.from) }],
    };
  }
  return propose(plan);
}

if (typeof window !== 'undefined') {
  window.EonTicket = { answer, readOrder };
  // above act (99): "refund … customer pays" would otherwise read as a payment order
  (window.__eonDomainQueue = window.__eonDomainQueue || []).push({ id: 'ticket', priority: 99.6, answer });
}

export default { answer, readOrder };
