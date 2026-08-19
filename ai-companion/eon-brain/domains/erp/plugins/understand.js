/* ============================================================
   EON · understand — one grammar instead of a hundred patterns.

   The boss does not phrase things twice the same way, and half the
   time he says it in Bangla. So EON stops matching whole sentences
   and reads them the way a person does — three parts:

       VERB          what to do        show / how much / assign / open / pay
       SUBJECT       what about        payroll, leads, journals, attendance…
       WHO / WHEN    about whom        a person, a party, a month, an amount

   The subjects come from the ERP's own 192 menu labels and 203
   tables, so every module the ERP has is understood without anyone
   writing an intent for it. The verbs are a small bilingual lexicon.
   That combination is what covers "কে টাকা পাবে?", "মফিজের কত বাকি",
   "ইমরানকে একটা টাস্ক দাও", "show Fahim's attendance last month".

   It answers in the language it was asked in, and it never invents:
   when it cannot tell what was meant it says so and offers the
   nearest thing it does know.
   ============================================================ */
import * as P from '../people.js';
import * as F from '../finance.js';
import * as O from '../ops.js';
import { fmtBDT, iso, MONTHS } from '../dataset.js';

const D0 = () => (typeof window !== 'undefined' && window.EonErp ? window.EonErp.dataset() : null);
const N = () => (typeof window !== 'undefined' ? window.EonNavigator : null);
const BN = /[ঀ-৿]/;
const norm = (s) => String(s || '').toLowerCase().replace(/\s+/g, ' ').trim();

/* ---------- 1. the verb: what does he want done ---------- */
const VERBS = [
  { id: 'howmuch', en: /\b(how much|how many|what is the (total|amount|balance)|total)\b/, bn: /(কত|কতটুকু|কয়টা|কতজন|মোট)/ },
  /* Paying is tested before assigning, because "বেতন দাও" carries both words. */
  { id: 'pay',     en: /\b(pay|clear|settle|release)\b/,                                  bn: /(পরিশোধ|ক্লিয়ার|পে করো|(বেতন|টাকা|বিল|পেমেন্ট|বকেয়া)[^।]{0,12}(দাও|দিন))/ },
  /* "দাও" on its own means "give me" — an instruction needs its noun. */
  { id: 'assign',  en: /\b(assign|give .* (a )?task|allocate|delegate)\b/,                bn: /(অ্যাসাইন|নিয়োগ|(টাস্ক|কাজ)[^।]{0,10}(দাও|দিন|দে))/ },
  { id: 'message', en: /\b(message|msg|write to|tell|inform|notify)\b/,                    bn: /(মেসেজ|বার্তা|জানাও|বলে দাও)/ },
  { id: 'open',    en: /\b(open|go to|take me|navigate|show me the|bring up)\b/,           bn: /(খোলো|খুলুন|নিয়ে চলো|দেখাও পেজ|যাও)/ },
  { id: 'where',   en: /\b(where is|where are|where can i|which (page|screen|menu))\b/,    bn: /(কোথায়|কোন পেজ|কোন মেনু)/ },
  { id: 'list',    en: /\b(list|show|what are|which|who are|give me)\b/,                   bn: /(দেখাও|তালিকা|কারা|কোনগুলো|কী কী)/ },
  { id: 'any',     en: /\b(any|is there|are there|anything)\b/,                            bn: /(কোনো|আছে কি|কিছু আছে)/ },
  { id: 'why',     en: /\b(why|explain|how does|how is .* calculated)\b/,                  bn: /(কেন|কিভাবে|ব্যাখ্যা)/ },
];
function verbOf(q) {
  const s = norm(q);
  for (const v of VERBS) if ((BN.test(q) ? v.bn : v.en).test(s) || v.en.test(s)) return v.id;
  return 'list';
}

/* ---------- 2. the subject: which part of the business ----------
   Built from the ERP's own menu and tables, plus the words the boss
   uses for them in both languages. */
const SUBJECTS = [
  { id: 'cash',        en: /\b(cash|bank|balance|liquidity|fund)s?\b/,               bn: /(ক্যাশ|নগদ|ব্যাংক|তহবিল)/,        ask: 'cash position' },
  { id: 'receivable',  en: /\b(receivable|owes? us|collect|debtor|due from)\b/,      bn: /(পাওনা|আদায়|বাকি টাকা)/,          ask: 'who owes us money' },
  { id: 'payable',     en: /\b(payable|we owe|creditor|supplier due|bill)s?\b/,      bn: /(দেনা|পরিশোধযোগ্য|পাওনাদার)/,     ask: 'what do we owe' },
  { id: 'dues',        en: /(owes?|owing|due|dues|outstanding|balance)/,             bn: /(বাকি|বকেয়া|পাওনা কত|দেনা কত)/,   ask: 'who owes us money' },
  { id: 'payroll',     en: /\b(payroll|salary|salaries|payslip|wage)s?\b/,           bn: /(বেতন|স্যালারি|পে-?রোল|মজুরি)/,    ask: 'payroll' },
  { id: 'attendance',  en: /\b(attendance|present|absent|late|punch)\b/,             bn: /(উপস্থিত|অনুপস্থিত|হাজিরা|দেরি)/,  ask: 'who is absent today' },
  { id: 'leave',       en: /\b(leave|holiday|vacation)s?\b/,                         bn: /(ছুটি)/,                          ask: 'leave requests' },
  { id: 'task',        en: /\b(task|to-?do|work|assignment|board)s?\b/,              bn: /(কাজ|টাস্ক|বোর্ড)/,               ask: 'overdue tasks' },
  { id: 'project',     en: /\b(project|delivery|milestone)s?\b/,                     bn: /(প্রজেক্ট|প্রকল্প)/,               ask: 'projects' },
  { id: 'lead',        en: /\b(lead|pipeline|prospect|crm|deal)s?\b/,                bn: /(লিড|পাইপলাইন|সম্ভাব্য)/,          ask: 'pipeline' },
  { id: 'sale',        en: /\b(sale|invoice|ticket sale|booking|revenue)s?\b/,       bn: /(বিক্রি|সেল|ইনভয়েস|টিকিট)/,       ask: 'any ticket sale today' },
  { id: 'expense',     en: /\b(expense|spend|cost|budget)s?\b/,                      bn: /(খরচ|ব্যয়|বাজেট)/,                ask: 'spending' },
  { id: 'journal',     en: /\b(journal|ledger|entry|entries|voucher|posting)s?\b/,   bn: /(জার্নাল|লেজার|খতিয়ান|ভাউচার)/,   ask: 'last transaction' },
  { id: 'account',     en: /\b(account|chart of accounts|coa)s?\b/,                  bn: /(হিসাব|একাউন্ট|চার্ট)/,            ask: 'chart of accounts' },
  { id: 'employee',    en: /\b(employee|staff|people|headcount|worker)s?\b/,         bn: /(কর্মী|স্টাফ|জনবল|লোক)/,           ask: 'headcount' },
  { id: 'customer',    en: /\b(customer|client|party)s?\b/,                          bn: /(ক্রেতা|গ্রাহক|পার্টি)/,           ask: 'top customers' },
  { id: 'vendor',      en: /\b(vendor|supplier)s?\b/,                                bn: /(সরবরাহকারী|ভেন্ডর)/,             ask: 'top suppliers' },
  { id: 'meeting',     en: /\b(meeting|appointment|visit)s?\b/,                      bn: /(মিটিং|সভা|অ্যাপয়েন্টমেন্ট)/,     ask: 'vendor meeting' },
  { id: 'notice',      en: /\b(notice|announcement|circular)s?\b/,                   bn: /(নোটিশ|বিজ্ঞপ্তি)/,               ask: 'office todos' },
  { id: 'loan',        en: /\b(loan|advance)s?\b/,                                   bn: /(ঋণ|লোন|অগ্রিম)/,                 ask: 'loans' },
  { id: 'error',       en: /\b(error|mistake|wrong|problem|issue|unbalanced)s?\b/,   bn: /(ভুল|সমস্যা|গরমিল|এরর)/,          ask: 'any accounting error' },
  { id: 'profit',      en: /\b(profit|loss|margin|p&l|income statement)\b/,          bn: /(লাভ|মুনাফা|লোকসান)/,             ask: 'profit' },
  { id: 'profile',     en: /\b(profile|details|record|information|info|who is)\b/,       bn: /(প্রোফাইল|তথ্য|বিস্তারিত|পরিচয়)/,  ask: 'user' },
  { id: 'payslip',     en: /\b(payslip|pay slip|salary slip|pay statement)\b/,           bn: /(পে-?স্লিপ|বেতন স্লিপ)/,          ask: 'payslips' },
  { id: 'brief',       en: /\b(brief|summary|situation|status|overview|today)\b/,    bn: /(ব্রিফ|সারসংক্ষেপ|অবস্থা|আজকের)/,  ask: 'brief' },
];
function subjectOf(q) {
  const s = norm(q);
  const bn = BN.test(q);
  const hits = SUBJECTS.filter((x) => (bn ? x.bn.test(q) : false) || x.en.test(s));
  return hits.length ? hits[0] : null;
}

/* ---------- 3. who and when ----------
   The boss asks about a person once and then keeps going: "what is Imran's
   payroll" … "take me to his profile" … "give pay to him". EON remembers the
   last person named — in either language — so the follow-ups land. */
let lastWho = null;
const PRONOUN = /\b(he|him|his|she|her|hers|they|them|their|the same|that person)\b|(তার|ওর|তাকে|ওকে|উনার|তিনি)/i;
export function subject_of_last() { return lastWho; }
function whoIn(D, q) {
  // a person the ERP knows — try the longest capitalised run, then any word
  const caps = String(q).match(/\b[A-Z][a-z.]{1,15}(?:\s+[A-Z][a-z.]{1,15}){0,3}\b/g) || [];
  const skip = /^(What|How|Any|Who|Show|Take|Assign|Last|The|Is|Are|Do|Can|EON|ERP)$/;
  for (const c of caps.filter((x) => !skip.test(x)).sort((a, b) => b.length - a.length)) {
    const e = P.findEmployee(D, c);
    if (e) {
      const tok = (x) => norm(x).split(/[^a-z]+/).filter((w) => w.length >= 3);
      if (tok(c).some((w) => tok(e.name).includes(w))) return { kind: 'employee', id: e.id, name: e.name, row: e };
    }
  }
  // a party in the ledger, named in either script
  for (const type of ['receive', 'pay']) {
    for (const p of F.schedules(D, type, {}).byParty || []) {
      const nm = norm(p.party_name);
      if (nm.length > 3 && norm(q).includes(nm.split(' ')[0])) return { kind: 'party', name: p.party_name, side: type, row: p };
    }
  }
  return null;
}

/** who this sentence is about — the name in it, or the last person named */
function whoOrLast(D, q) {
  const w = whoIn(D, q);
  if (w) { lastWho = w; return w; }
  if (PRONOUN.test(q) && lastWho) return lastWho;
  // "payroll of Imran", "Imran's payroll", "give Imran's payroll" — the name may sit
  // anywhere; whoIn already scans, so a miss here means no name was said at all
  return null;
}
const BN_MONTH = ['জানুয়ারি', 'ফেব্রুয়ারি', 'মার্চ', 'এপ্রিল', 'মে', 'জুন', 'জুলাই', 'আগস্ট', 'সেপ্টেম্বর', 'অক্টোবর', 'নভেম্বর', 'ডিসেম্বর'];
function monthIn(q) {
  const s = norm(latinDigits(q));
  const i = MONTHS.findIndex((m) => s.includes(m.toLowerCase().slice(0, 3)));
  if (i >= 0) return i + 1;
  const j = BN_MONTH.findIndex((m) => q.includes(m));
  return j >= 0 ? j + 1 : null;
}
/* ৩০০০০ is thirty thousand — the boss speaks money in Bangla digits too */
const BN_DIGITS = { '০': '0', '১': '1', '২': '2', '৩': '3', '৪': '4', '৫': '5', '৬': '6', '৭': '7', '৮': '8', '৯': '9' };
const latinDigits = (x) => String(x).replace(/[০-৯]/g, (d) => BN_DIGITS[d]);
function amountIn(q) {
  const m = latinDigits(q).replace(/,/g, '').match(/\b(\d{3,9})(?:\s*(tk|৳|taka|টাকা))?\b/i);
  return m ? +m[1] : null;
}

/* ---------- answering in the language asked ---------- */
function say(q, en, bn) { return BN.test(q) && bn ? bn : en; }

/* ============================================================
   A HALF COMMAND IS STILL A COMMAND.

   "pay Imran" is not enough to move money, but it is enough to start.
   EON holds what it has, asks for the one thing it still needs, and
   accepts a bare answer — "July", "30000", "হ্যাঁ" — because that is
   how people talk, especially out loud. One question at a time, in the
   language the boss is speaking, and it never guesses the missing part.
   ============================================================ */
const NEEDS = {
  assign:  ['who', 'task'],
  pay:     ['who', 'month', 'amount'],
  message: ['who', 'text'],
};
let convo = null;              // { verb, slots, asked, at }
const FRESH = 4 * 60 * 1000;   // a held command goes stale after four quiet minutes

export function conversation() { return convo; }
export function forget() { convo = null; }

const ASK = {
  who:    { en: (v) => `Who should I ${v === 'pay' ? 'pay' : v === 'message' ? 'message' : 'give it to'}?`, bn: (v) => `কাকে ${v === 'pay' ? 'পরিশোধ করবো' : v === 'message' ? 'মেসেজ পাঠাবো' : 'দেবো'}?` },
  task:   { en: () => 'What is the task?', bn: () => 'কাজটা কী?' },
  month:  { en: () => 'Which month?', bn: () => 'কোন মাসের?' },
  amount: { en: () => 'How much?', bn: () => 'কত টাকা?' },
  text:   { en: () => 'What should the message say?', bn: () => 'বার্তায় কী লিখবো?' },
};

/** pull whatever slots this utterance carries */
function slotsIn(D, q, verb) {
  const out = {};
  const who = whoIn(D, q);
  if (who) out.who = who;
  const m = monthIn(q);
  if (m) out.month = m;
  const a = amountIn(q);
  if (a) out.amount = a;
  // the free-text part of an instruction: what is left after the verb and the name
  const after = String(q)
    .replace(/^[\s\S]*?(?:task|কাজ|টাস্ক|message|মেসেজ|বার্তা)\s*(?:is|:|—|-|হলো|হল)?\s*/i, (mm) => (mm.length < String(q).length ? '' : mm))
    .trim();
  if (after && after !== String(q).trim() && after.length > 2) {
    if (verb === 'assign') out.task = after;
    if (verb === 'message') out.text = after;
  }
  return out;
}

/** a bare reply to EON's question — "July", "30000", "Imran", "লেজার চেক করো" */
function bareSlot(D, q, need) {
  const t = String(q).trim();
  if (!t) return null;
  if (need === 'month') { const m = monthIn(t) || (/^\d{1,2}$/.test(t) && +t >= 1 && +t <= 12 ? +t : null); return m; }
  if (need === 'amount') { const a = amountIn(t) || (/^[\d,]+$/.test(t) ? +t.replace(/,/g, '') : null); return a; }
  if (need === 'who') { const w = whoIn(D, t) || (P.findEmployee(D, t) ? { kind: 'employee', name: P.findEmployee(D, t).name, id: P.findEmployee(D, t).id, row: P.findEmployee(D, t) } : null); return w; }
  return t.length > 2 ? t : null;     // task / text: take it as said
}

function nextMissing(verb, slots) {
  return (NEEDS[verb] || []).find((k) => slots[k] == null);
}

function askFor(need, verb, q) {
  const a = ASK[need];
  const bn = BN.test(q);
  return { speak: bn ? a.bn(verb) : a.en(verb), detail: [], awaiting: need };
}

/** what EON is holding, said back so the boss can see it */
function heldSummary(verb, slots, bn) {
  const bits = [];
  if (slots.who) bits.push(slots.who.name);
  if (slots.month) bits.push(MONTHS[slots.month - 1]);
  if (slots.amount) bits.push(fmtBDT(slots.amount));
  if (slots.task) bits.push('“' + slots.task + '”');
  if (slots.text) bits.push('“' + slots.text + '”');
  if (!bits.length) return '';
  return bn ? 'এ পর্যন্ত: ' + bits.join(' · ') : 'So far: ' + bits.join(' · ');
}

/** carry the conversation one step; returns an answer, or null to fall through */
async function step(D, q, verb, ctx) {
  const bn = BN.test(q);
  const now = Date.now();
  if (convo && now - convo.at > FRESH) convo = null;

  // cancelling
  if (convo && /^\s*(cancel|stop|forget it|never mind|বাদ দাও|থাক|বাতিল)\b/i.test(q)) {
    convo = null;
    return { speak: bn ? 'ঠিক আছে, বাদ দিলাম।' : 'Dropped it.', detail: [] };
  }

  // A new, complete question is not an answer to the old one. "প্রজেক্ট কয়টা ঝুঁকিতে?"
  // while EON is waiting for an amount means the boss moved on — drop what was held.
  if (convo && convo.asked) {
    const ownSubject = subjectOf(q);
    const wordy = String(q).trim().split(/\s+/).length >= 3;
    const answersTheSlot = bareSlot(D, q, convo.asked) != null && !ownSubject;
    if (ownSubject && wordy && !answersTheSlot) convo = null;
  }

  // continuing a held command with a bare answer
  if (convo && convo.asked) {
    const val = bareSlot(D, q, convo.asked);
    if (val != null) {
      convo.slots[convo.asked] = val;
      convo.asked = null;
      convo.at = now;
    }
  }

  // starting, or adding to, an instruction
  if (NEEDS[verb]) {
    if (!convo || convo.verb !== verb) convo = { verb, slots: {}, asked: null, at: now };
    Object.assign(convo.slots, slotsIn(D, q, verb));
    convo.at = now;
  }
  if (!convo) return null;

  const need = nextMissing(convo.verb, convo.slots);
  if (need) {
    convo.asked = need;
    const a = askFor(need, convo.verb, q);
    const held = heldSummary(convo.verb, convo.slots, bn);
    return { speak: a.speak, detail: held ? [held] : [], awaiting: need };
  }

  // everything is in hand — hand it to the action layer, which shows it before it writes
  const { verb: v, slots } = convo;
  convo = null;
  const A = typeof window !== 'undefined' ? window.EonAct : null;
  try {
    if (v === 'assign' && A) return A.propose(await A.planTask(slots.who, slots.task, {}));
    if (v === 'message' && A) return A.propose(await A.planMessage(slots.who, slots.text));
    /* Money goes through the ERP's own payment form — the salary sheet's
       "Record Payment" for an employee, the party statement's for a supplier.
       act.js reads the real fields, fills them and shows them; nothing is
       posted until the boss says yes to the card it puts up. */
    if (v === 'pay') {
      const monthName = MONTHS[slots.month - 1];
      if (!A) throw new Error(bn ? 'অ্যাকশন লেয়ারটা লোড হয়নি' : 'the action layer is not loaded');
      const plan = await A.planPayment(slots.who, { month: slots.month, amount: slots.amount });
      const c = A.propose(plan);
      // the held summary stays on the card: the boss should see his own words beside the ERP's fields
      return Object.assign({}, c, {
        detail: [heldSummary('pay', slots, bn)].filter(Boolean).concat(c.detail || []),
        speak: bn
          ? `${slots.who.name} — ${monthName}, ${fmtBDT(slots.amount)}। ${c.speak}`
          : c.speak,
      });
    }
  } catch (e) {
    // a payment that could not be set up must say plainly that no money moved
    if (v === 'pay') {
      return {
        speak: bn
          ? `কিছুই পোস্ট করিনি — ${e.message}। কোনো টাকা যায়নি।`
          : `I have posted nothing — ${e.message}. No money has moved.`,
        detail: [],
        actions: (() => { const n = N(); if (!n) return []; const h = (n.find('payment schedules', 1) || [])[0]; return h ? [{ label: bn ? 'পেমেন্ট স্ক্রিন' : 'Open the payment screen', kind: 'erp-open', href: n.url(h.uri) }] : []; })(),
      };
    }
    return { speak: bn ? `সেটআপ করতে পারলাম না: ${e.message}` : `I could not set that up: ${e.message}`, detail: [] };
  }
  return null;
}

/* ---------- the composer ---------- */
/* Bangla marks a question with a tail word rather than word order:
   "বেতন পরিশোধ হয়েছে?" asks whether salary was paid; "বেতন দাও" orders it.
   Without this every question about paying became an order to pay. */
const QUESTION = /\?\s*$|\b(হয়েছে|হয়েছে কি|আছে কি|কি\b|কী\b|কেমন|কত|কারা|কোথায়|কয়টা)\b|\b(is|are|was|were|has|have|did|does|do|what|which|who|how|why|when|where|any)\b/i;
const ORDER = /\b(assign|pay|message|msg|send|write to|tell|notify|clear|settle|release)\b|(অ্যাসাইন|নিয়োগ|পরিশোধ করো|পে করো|ক্লিয়ার করো|(টাস্ক|কাজ)[^।]{0,10}(দাও|দিন|দে)|(বেতন|টাকা|বিল|বকেয়া)[^।]{0,12}(দাও|দিন))/i;

async function understand(q, ctx) {
  const D = D0();
  if (!D) return null;
  const verb = verbOf(q);
  const isOrder = ORDER.test(q) && !(QUESTION.test(q) && !ORDER.test(String(q).replace(/[?？]/g, '')));

  // a half command, or an answer to what EON asked a moment ago
  if ((NEEDS[verb] && isOrder) || (convo && convo.asked)) {
    const s = await step(D, q, verb, ctx);
    if (s) return s;
  }
  const subj = subjectOf(q);
  const who = whoOrLast(D, q);
  const month = monthIn(q);
  const amount = amountIn(q);
  const bn = BN.test(q);

  // (assign / pay / message are handled above by the dialogue)
  if (false && typeof window !== 'undefined' && window.EonAct) {
    if (verb === 'assign' && who && who.kind === 'employee') {
      const task = String(q).replace(/^[\s\S]*?(?:task|কাজ|টাস্ক)\s*(?:is|:|—|-|হলো|হল)?\s*/i, '').trim();
      if (task && task.length > 2) {
        try { return window.EonAct.propose(await window.EonAct.planTask(who, task, {})); } catch (e) { /* fall through to the plain answer */ }
      }
      return { speak: say(q, `What should ${who.name} do? Say it as “assign ${who.name} a task: check the ledger”.`, `${who.name}-কে কী কাজ দেবো? বলুন — “${who.name} কে টাস্ক দাও: লেজার চেক করো”।`), detail: [] };
    }
    if (verb === 'pay') {
      // money is never posted from a guess: name the person, the month and the amount first
      const parts = [who ? who.name : null, month ? MONTHS[month - 1] : null, amount ? fmtBDT(amount) : null].filter(Boolean);
      return {
        speak: say(q,
          `Paying is the one thing I will not do from a half-sentence. Tell me who, which month and how much — for example “pay ${who ? who.name : 'Imran'} July salary 30000” — and I will show you the exact payment before anything is posted.`,
          `টাকা পরিশোধ আমি অনুমান করে করি না। কাকে, কোন মাসের, কত — যেমন “${who ? who.name : 'ইমরান'} কে জুলাই মাসের ৩০০০০ বেতন দাও” — বললে পোস্ট করার আগে পুরোটা দেখিয়ে নেবো।`),
        detail: parts.length ? [say(q, 'So far I have: ' + parts.join(' · '), 'এ পর্যন্ত পেয়েছি: ' + parts.join(' · '))] : [],
      };
    }
  }

  // a question about one person
  if (who && subj) {
    const s = { company: null };

    // "take me to his profile" / "Imran's payslip" — the record screen for that person
    if ((subj.id === 'profile' || subj.id === 'payslip') && N()) {
      const key = subj.id === 'profile' ? 'user' : 'payslips';
      const rec = who.kind === 'employee' && subj.id === 'profile' ? N().findRecord(`user ${who.id}`) : null;
      const url = rec ? N().url(rec.uri) : (() => { const h = (N().find(key, 1) || [])[0]; return h ? N().url(h.uri) : null; })();
      if (url) return {
        speak: say(q, `${who.name} — ${subj.id === 'profile' ? 'profile' : 'payslips'}. Opening it.`,
                      `${who.name} — ${subj.id === 'profile' ? 'প্রোফাইল' : 'পে-স্লিপ'}। খুলছি।`),
        detail: [], actions: [{ label: say(q, 'Open', 'খুলুন'), kind: 'erp-open', href: url }], navigate: url,
      };
    }
    if (subj.id === 'task' && who.kind === 'employee') {
      const mine = (O.tasks(D, s).all || []).filter((t) => (t.assigned_to || []).includes(who.id));
      const open = mine.filter((t) => t.status !== 'done');
      return {
        speak: say(q, `${who.name} has ${open.length} active task${open.length === 1 ? '' : 's'}${open.length ? `: ${open.slice(0, 3).map((t) => t.title).join('; ')}` : ''}.`,
          `${who.name}-এর ${open.length}টি চলমান কাজ আছে${open.length ? `: ${open.slice(0, 3).map((t) => t.title).join('; ')}` : ''}।`),
        detail: open.slice(0, 6).map((t) => `${t.title} — ${t.status}${t.due_date ? `, ${t.due_date}` : ''}`),
        view: 'ops',
      };
    }
    if (subj.id === 'payroll' || subj.id === 'payslip' || subj.id === 'dues' || subj.id === 'receivable' || subj.id === 'payable' || subj.id === 'loan') {
      // let the entity plug-in answer the money question, then translate the lead line
      const r = typeof window !== 'undefined' && window.EonEntity ? null : null;
      const pr = P.payroll(D, s);
      const pend = (pr.pending || []).filter((p) => p.id === who.id || p.name === who.name);
      const ln = P.loans(D, s);
      const loans = (ln.loans || ln.rows || []).filter((l) => l.user_id === who.id);
      const unpaid = pend.reduce((n, p) => n + (+p.net || +p.net_salary || 0), 0);
      const left = loans.reduce((n, l) => n + (+l.remaining_amount || 0), 0);
      if (who.kind === 'party') {
        const row = who.row;
        return { speak: say(q, `${who.name}: ${fmtBDT(row.due)} outstanding, ${fmtBDT(row.overdue)} of it overdue.`, `${who.name}: ${fmtBDT(row.due)} বকেয়া, তার ${fmtBDT(row.overdue)} সময় পার।`), detail: [], view: 'finance' };
      }
      // "Imran's payroll" wants his pay, not only what is owed
      if (subj.id === 'payroll' || subj.id === 'payslip') {
        const sal = +(who.row && who.row.salary) || 0;
        const bits = [
          sal ? say(q, `salary ${fmtBDT(sal)}/month`, `বেতন ${fmtBDT(sal)}/মাস`) : null,
          unpaid ? say(q, `${fmtBDT(unpaid)} unpaid`, `${fmtBDT(unpaid)} বকেয়া`) : say(q, `${pr.month} paid`, `${pr.month} পরিশোধিত`),
          left ? say(q, `loan ${fmtBDT(left)} left`, `ঋণ বাকি ${fmtBDT(left)}`) : null,
        ].filter(Boolean);
        const n = N();
        const hit = n ? (n.find('employee salaries', 1) || [])[0] : null;
        return {
          speak: `${who.name} — ${bits.join(', ')}.`,
          detail: who.row ? [`${who.row.designation || ''}${who.row.department ? ' · ' + who.row.department : ''}`.trim()].filter(Boolean) : [],
          actions: hit ? [{ label: say(q, 'Open payroll', 'পে-রোল খুলুন'), kind: 'erp-open', href: n.url(hit.uri) }] : [],
          view: 'people',
        };
      }
      if (!unpaid && !left) return { speak: say(q, `Nothing is outstanding for ${who.name}.`, `${who.name}-এর কিছু বকেয়া নেই।`), detail: [], view: 'people' };
      return {
        speak: say(q, `${who.name}: ${unpaid ? `unpaid salary ${fmtBDT(unpaid)}` : ''}${unpaid && left ? ', ' : ''}${left ? `loan outstanding ${fmtBDT(left)}` : ''}.`,
          `${who.name}: ${unpaid ? `বকেয়া বেতন ${fmtBDT(unpaid)}` : ''}${unpaid && left ? ', ' : ''}${left ? `ঋণ বাকি ${fmtBDT(left)}` : ''}।`),
        detail: [], view: 'people',
      };
    }
    if (subj.id === 'attendance' && who.kind === 'employee') {
      const ev = P.evaluate(D, who.id);
      if (ev) return {
        speak: say(q, `${who.name}: attendance ${ev.attendancePct}% over ${ev.days} days, late on ${ev.lateDays}.`,
          `${who.name}: ${ev.days} দিনে উপস্থিতি ${ev.attendancePct}%, দেরি ${ev.lateDays} দিন।`),
        detail: (ev.concerns || []).slice(0, 4), view: 'people',
      };
    }
  }

  // "where is X" / "open X" — the navigator owns the ERP's geography
  if ((verb === 'where' || verb === 'open') && N()) {
    const key = subj ? subj.ask : q;
    const hit = (N().find(key, 1) || [])[0];
    if (hit) {
      const url = N().url(hit.uri);
      return {
        speak: say(q, `${hit.label || key} — ${url.replace(location.origin, '')}`, `${hit.label || key} — ${url.replace(location.origin, '')}`),
        detail: [], actions: [{ label: say(q, 'Open', 'খুলুন'), kind: 'erp-open', href: url }],
        navigate: verb === 'open' ? url : null,
      };
    }
  }

  // a plain question about a subject: hand it to the answerer that owns it,
  // in the language it was asked
  if (subj) {
    const askEn = subj.ask;
    let r = null;
    try {
      if (bn && typeof window !== 'undefined' && window.EonBangla && window.EonBangla.answer) r = window.EonBangla.answer(q, ctx);
      if (!r && typeof window !== 'undefined' && window.EonErpQA) r = window.EonErpQA.answer(askEn, ctx);
    } catch {}
    if (r && r.speak) return r;
    // the subject is understood but no answerer covers it — say that plainly
    // rather than returning nothing and looking deaf
    const N2 = N();
    const hit = N2 ? (N2.find(subj.ask || subj.id, 1) || [])[0] : null;
    if (hit) {
      const url = N2.url(hit.uri);
      return {
        speak: bn
          ? `${subj.id} নিয়ে সরাসরি হিসাব এখনো নেই, তবে স্ক্রিনটা খুলে দিতে পারি।`
          : `I do not have a figure for ${subj.id} yet, but I can open the screen.`,
        detail: [], actions: [{ label: bn ? 'খুলুন' : 'Open', kind: 'erp-open', href: url }],
      };
    }
  }

  return null;
}

/* ---------- registration ---------- */
const CLAIM = (q) => {
  const s = String(q || '');
  if (!s.trim()) return false;
  if (convo && convo.asked) return true;          // EON asked something; this is the answer
  return !!subjectOf(s) || /\b(assign|pay|message)\b/i.test(s) || BN.test(s);
};

if (typeof window !== 'undefined') {
  window.EonUnderstand = { verbOf, subjectOf, whoIn: (q) => whoIn(D0(), q), monthIn, amountIn, understand, conversation, forget };
  // below the specialists (99 act · 98 entity · 97 navigator · 95 bangla) — it catches
  // what none of them matched, which is where the odd phrasings land
  // above the phrase matchers (bangla 95) so "X কে টাস্ক দাও" is an instruction,
  // not a question about tasks; still below act (99) and entity (98)
  (window.__eonDomainQueue = window.__eonDomainQueue || []).push({ id: 'understand', priority: 96, claims: CLAIM, answer: understand });
}

export default { understand, verbOf, subjectOf };
