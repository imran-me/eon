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

   Two rules keep it honest:

   1. **The subject decides the answer, never the sentence.** Once EON
      has read a subject out of the question it asks the layer that
      owns THAT subject — it never hands the raw sentence back to
      another matcher, because a Bangla sentence about leads
      ("লিড কেমন চলছে?") also carries the words of a morning brief
      ("কেমন চলছে") and used to come back as the brief.
   2. **It answers in the language it was asked in**, and it never
      invents: when it cannot tell what was meant it says so and
      offers the nearest thing it does know.

   NOTE FOR WHOEVER EDITS THIS FILE: write it with an editor, never
   with a shell heredoc. A heredoc turns every \b in these regexes
   into a literal backspace byte; the pattern then silently stops
   matching and the answer simply disappears. That is exactly what
   killed the `dues` subject once already — "how much does he owe"
   came back with nothing at all because of two invisible bytes.
   ============================================================ */
import * as P from '../people.js';
import * as F from '../finance.js';
import * as O from '../ops.js';
import * as BNG from './bangla.js';
import { fmtBDT, iso, MONTHS, monthKey } from '../dataset.js';

const D0 = () => (typeof window !== 'undefined' && window.EonErp ? window.EonErp.dataset() : null);
const N = () => (typeof window !== 'undefined' ? window.EonNavigator : null);
const BN = /[ঀ-৿]/;
const norm = (s) => String(s || '').toLowerCase().replace(/\s+/g, ' ').trim();

/* Bangla money and numerals, so a Bangla sentence never carries an English figure */
const bMoney = (n) => BNG.money(n);
const bNum = (n) => BNG.digits(Math.round(Number(n) || 0));
const BN_MONTH = ['জানুয়ারি', 'ফেব্রুয়ারি', 'মার্চ', 'এপ্রিল', 'মে', 'জুন', 'জুলাই', 'আগস্ট', 'সেপ্টেম্বর', 'অক্টোবর', 'নভেম্বর', 'ডিসেম্বর'];
const bMonthKey = (mk) => `${BN_MONTH[+String(mk).slice(5) - 1]} ${BNG.digits(String(mk).slice(0, 4))}`;
const T = (D) => (D && D.meta && D.meta.today) || iso(new Date());

/* ---------- 1. the verb: what does he want done ---------- */
const VERBS = [
  { id: 'howmuch', en: /\b(how much|how many|what is the (total|amount|balance)|what's the (total|amount|balance)|total|how big)\b/, bn: /(কত|কতটুকু|কয়টা|কয়জন|কতজন|মোট)/ },
  /* Paying is tested before assigning, because "বেতন দাও" carries both words. */
  { id: 'pay',     en: /\b(pay|clear|settle|release)\b/,                                  bn: /(পরিশোধ|ক্লিয়ার|পে করো|(বেতন|টাকা|বিল|পেমেন্ট|বকেয়া)(?:(?!হিসাব|তালিকা|রিপোর্ট|তথ্য|খবর|স্লিপ|শিট|অবস্থা|বিবরণ|পরিমাণ)[^।]){0,12}(দাও|দিন))/ },
  /* "দাও" on its own means "give me" — an instruction needs its noun.
     And "দে" must not be read inside দেখাও: "টাস্ক দেখাও" is show me the tasks,
     not give somebody a task, and EON answered it with "কাকে দেবো?" until the
     guard went in. A Bangla letter may not follow. */
  { id: 'assign',  en: /\b(assign|give .* (a )?task|allocate|delegate)\b/,                bn: /(অ্যাসাইন|নিয়োগ|(টাস্ক|কাজ)(?:(?!দেখা|তালিকা|হিসাব|রিপোর্ট)[^।]){0,10}(দাও|দিন|দে(?![ঀ-৿])))/ },
  { id: 'message', en: /\b(message|msg|write to|tell|inform|notify)\b/,                    bn: /(মেসেজ|বার্তা|জানাও|বলে দাও)/ },
  { id: 'open',    en: /\b(open|go to|take me|navigate|show me the|bring up)\b/,           bn: /(খোলো|খুলুন|নিয়ে চলো|দেখাও পেজ|যাও)/ },
  { id: 'where',   en: /\b(where is|where are|where can i|which (page|screen|menu))\b/,    bn: /(কোথায়|কোন পেজ|কোন মেনু)/ },
  { id: 'list',    en: /\b(list|show|what are|which|who are|give me|give)\b/,              bn: /(দেখাও|তালিকা|কারা|কোনগুলো|কী কী)/ },
  { id: 'any',     en: /\b(any|is there|are there|anything)\b/,                            bn: /(কোনো|আছে কি|কিছু আছে)/ },
  { id: 'why',     en: /\b(why|explain|how does|how is .* calculated)\b/,                  bn: /(কেন|কিভাবে|ব্যাখ্যা)/ },
];
/* "did we pay the suppliers?" asks what already happened — it does not order a
   payment, and it used to open one ("Who should I pay?"). A perfect or past
   auxiliary in front of an action word makes the sentence a question about
   history, so the acting verbs are skipped and the books answer instead. */
const ASKED_NOT_TOLD = /\b(did|have|has|had|was|were)\b[^?]{0,24}\b(pay|paid|clear|cleared|settle|settled|release|released|assign|assigned|send|sent|message[d]?)\b/;
/* বাংলায় একই কথা: "পরিশোধ হয়েছে" জানতে চাওয়া, করতে বলা নয়। */
const ASKED_NOT_TOLD_BN = /(পরিশোধ|দেওয়া|দেয়া|করা|পাঠানো|অ্যাসাইন)\s*(হয়েছে|হয়েছিল|হয়ে গেছে|হলো|হয়নি)/;
const ACTING = new Set(['pay', 'assign', 'message']);
function verbOf(q) {
  const s = norm(q);
  const asking = ASKED_NOT_TOLD.test(s) || ASKED_NOT_TOLD_BN.test(String(q || ''));
  for (const v of VERBS) {
    if (asking && ACTING.has(v.id)) continue;
    if ((BN.test(q) ? v.bn : v.en).test(s) || v.en.test(s)) return v.id;
  }
  return 'list';
}

/* ---------- 2. the subject: which part of the business ----------
   Built from the ERP's own menu and tables, plus the words the boss
   actually uses for them in both languages — all of them, not one
   canonical form, because nobody says the canonical form out loud.

   ask     — an English probe that domains/erp/qa.js really matches.
             Every one below was walked against that file's INTENTS in
             order, so the probe lands on the intent named here and not
             on an earlier one. Several used to miss: "overdue tasks"
             landed on overdue *invoices* (it now reads "task board"),
             and "any ticket sale today", "last transaction", "chart of
             accounts", "any accounting error" and "vendor meeting"
             matched no intent at all — those subjects are answered
             here instead, natively, in both languages.
   askBn   — a Bangla probe that plugins/bangla.js really matches.
   rank    — 1 = a named part of the business, 5 = a vague word that
             should only win when nothing specific was said. */
const SUBJECTS = [
  /* ---- the day ---- */
  { id: 'brief', rank: 5, ask: 'brief', askBn: 'ব্রিফ',
    en: /\b(brief|briefing|morning report|daily report|status report|overview|situation|catch me up|update me|where do we stand|how (are|is) (things|business|the company|everything|we doing)|what('?s| is) (going on|happening|the situation|new)|summary of the day)\b/,
    bn: /(ব্রিফ|সারসংক্ষেপ|আজকের অবস্থা|আজকের রিপোর্ট|খবর (কি|কী)|সব কেমন চলছে|সার্বিক অবস্থা|দিনের অবস্থা)/ },
  { id: 'decision', rank: 5, ask: 'what should i do', askBn: 'সিদ্ধান্ত',
    en: /\b(what should i do|what do i do|priorit(y|ies)|urgent|critical|needs (my )?attention|top (issue|risk|decision)|decisions?|burning|on fire|action list)\b/,
    bn: /(কী করা উচিত|কি করা উচিত|জরুরি|গুরুত্বপূর্ণ|সিদ্ধান্ত|নজর দেওয়া|মনোযোগ|অগ্রাধিকার)/ },
  { id: 'approval', rank: 1, ask: 'approvals', askBn: 'অনুমোদন',
    en: /\b(approvals?|approve|waiting (on|for) me|pending (my )?(sign|approval)|sign[- ]?offs?|authoris(e|ation)|authoriz(e|ation))\b/,
    bn: /(অনুমোদন|সই|অনুমতি|পেন্ডিং|অপেক্ষমাণ)/ },

  /* ---- money ---- */
  { id: 'cash', rank: 1, ask: 'cash position', askBn: 'ক্যাশ',
    en: /\b(cash ?flows?|cash|cash position|cash in hand|bank balances?|banks?|liquidity|funds?|treasury|money (do we have|in the bank|left)|have (any )?money|how much money|taka ache|nogod)\b/,
    bn: /(ক্যাশ|নগদ|ব্যাংক|তহবিল|হাতে (কত|কী) (টাকা|আছে)|টাকা আছে)/ },
  { id: 'runway', rank: 1, ask: 'runway', askBn: null,
    en: /\b(runway|burn rate|burning cash|how long (will|does) (the )?cash last|months? of (cash|cover)|survive)\b/,
    bn: /(রানওয়ে|কতদিন চলবে|নগদ কতদিন|বার্ন রেট)/ },
  { id: 'receivable', rank: 1, ask: 'receivables', askBn: 'পাওনা',
    /* "how much do customers owe us" is a receivables question, not a question
       about customers — the whole phrase is spelled out so it wins on length
       against the bare word "customers". Same trick as payables below. */
    en: /\b((customers?|clients?|buyers?|part(y|ies)|debtors?|they|people)\s+(still\s+)?owes?\s+us|receivables?|owes? us|owed to us|money in the market|collections?|collect|debtors?|due from|customer dues?|ar|bakeya|bokeya|paona)\b/,
    bn: /(পাওনা|আদায়|রিসিভেবল|কারা টাকা দেবে|টাকা (দেবে|দিবে|পাব))/ },
  { id: 'payable', rank: 1, ask: 'payables', askBn: 'দেনা',
    /* "how much do we owe suppliers" is a payables question, not a question
       about suppliers — so the longer phrase is spelled out and wins on length. */
    en: /\b(we owe (the |our |their |any )?(suppliers?|vendors?|creditors?|part(y|ies)|them)|payables?|we owe|creditors?|supplier dues?|bills? (to pay|due)|bills?|payments? due|ap)\b/,
    bn: /(দেনা|পাওনাদার|পেয়েবল|কাকে (টাকা )?দিতে|বিল পরিশোধ)/ },
  /* "overdue" is an adjective, not a part of the business: "what tasks are
     overdue" is a question about tasks. Rank 3 lets any real noun win. */
  { id: 'overdue', rank: 3, ask: 'overdue', askBn: null,
    en: /\b(overdue|past due|late payments?|aged debt|not paid on time)\b/,
    bn: /(সময় পার|মেয়াদ পার|বকেয়া পড়ে|দেরিতে পরিশোধ)/ },
  { id: 'aging', rank: 1, ask: 'aging', askBn: null,
    en: /\b(aging|ageing|aged (analysis|buckets?)|how old are (the )?dues?)\b/,
    bn: /(এজিং|বয়সভিত্তিক|কত পুরনো বকেয়া)/ },
  { id: 'dues', rank: 5, ask: 'outstanding invoices', askBn: 'পাওনা',
    en: /\b(owes?|owing|dues?|outstanding|arrears|unpaid|unsettled|balance)\b/,
    bn: /(বাকি|বকেয়া|অপরিশোধিত|কত পাওনা|কত দেনা)/ },
  { id: 'profit', rank: 1, ask: 'profit', askBn: 'মুনাফা',
    en: /\b(profits?|loss|margins?|p&l|income statement|bottom line|net income|earnings?|revenue|turnover|making money|losing money|lav|labh|munafa|lokshan)\b/,
    bn: /(লাভ|মুনাফা|লোকসান|আয়(-| )?ব্যয়|আয় কত|টার্নওভার|নিট আয়)/ },
  { id: 'trialbalance', rank: 1, ask: 'trial balance', askBn: null,
    en: /\b(trial balance)\b/, bn: /(ট্রায়াল ব্যালেন্স|রেওয়ামিল)/ },
  { id: 'balancesheet', rank: 1, ask: 'balance sheet', askBn: null,
    en: /\b(balance sheet|net worth|assets? and liabilit|what do we own|equity)\b/,
    bn: /(ব্যালেন্স শিট|স্থিতিপত্র|সম্পদ ও দায়|নিট সম্পদ)/ },
  { id: 'anomaly', rank: 1, ask: 'anomalies', askBn: null,
    en: /\b(anomal(y|ies)|unusual|spikes?|suspicious|leakage|abnormal|out of (pattern|line)|irregular)\b/,
    bn: /(অস্বাভাবিক|সন্দেহজনক|হঠাৎ বেড়ে|অনিয়ম)/ },
  { id: 'expense', rank: 1, ask: 'spending', askBn: null,
    en: /\b(expenses?|spend(ing)?|spent|costs?|budgets?|opex|overheads?|outgoings?|where (is|does) (the )?money go|khoroch|khorcha|kharoch|byay)\b/,
    bn: /(খরচ|ব্যয়|বাজেট|খরচা|ওভারহেড)/ },
  { id: 'sale', rank: 1, ask: null, askBn: null,
    en: /\b(sales?|sold|invoices?|bookings?|ticket sales?|billed|top line)\b/,
    bn: /(বিক্রি|সেল|ইনভয়েস|টিকিট বিক্রি|বিক্রয়|চালান)/ },
  { id: 'journal', rank: 1, ask: null, askBn: null,
    en: /\b(journals?|ledgers?|entr(y|ies)|vouchers?|postings?|transactions?|book ?keeping)\b/,
    bn: /(জার্নাল|লেজার|খতিয়ান|ভাউচার|দাখিলা|লেনদেন)/ },
  { id: 'account', rank: 1, ask: null, askBn: null,
    en: /\b(chart of accounts|coa|account codes?|gl codes?|account heads?|ledger accounts?)\b/,
    bn: /(হিসাব নম্বর|একাউন্ট কোড|হিসাবের চার্ট|হিসাব খাত|হিসাবের তালিকা|হিসাব তালিকা)/ },
  { id: 'error', rank: 1, ask: null, askBn: null,
    en: /\b(errors?|mistakes?|wrong|problems?|issues?|unbalanced|discrepanc(y|ies)|mismatch|does not balance|doesn't balance)\b/,
    bn: /(ভুল|গরমিল|এরর|মিল নেই|অসামঞ্জস্য|সমস্যা)/ },

  /* ---- people ---- */
  { id: 'payroll', rank: 1, ask: 'payroll', askBn: 'বেতন',
    /* "what Imran earns" is a payroll question. `earnings` stays with profit —
       that is the company's line, not a person's — so only the verb form is here. */
    en: /\b(payroll|salar(y|ies)|wages?|pay ?bill|net pay|remuneration|salary sheet|earns?|take[- ]home|beton)\b/,
    bn: /(বেতন|স্যালারি|পে-?রোল|মজুরি|বেতনের হিসাব)/ },
  { id: 'payslip', rank: 1, ask: 'payslips', askBn: 'বেতন',
    en: /\b(pay ?slips?|salary slips?|pay statements?)\b/, bn: /(পে-?স্লিপ|বেতন স্লিপ|বেতনের স্লিপ)/ },
  { id: 'attendance', rank: 1, ask: 'who is absent today', askBn: 'অনুপস্থিত',
    en: /\b(attendance|present|absent(ees?)?|missing|punch(es|ed)?|who (came|showed up|is in|is here)|has ?n'?t shown up|checked in|in today)\b/,
    bn: /(উপস্থিত|অনুপস্থিত|হাজিরা|আসেনি|আসছে|এসেছে|এসেছেন|কে অফিসে)/ },
  { id: 'late', rank: 1, ask: 'who came late today', askBn: 'দেরি',
    en: /\b(late ?comers?|came late|arrived late|who (is|was) late|punctual(ity)?|always late|habitually late|tardy)\b/,
    bn: /(দেরি|লেট|বিলম্ব|সময়মতো আসে না)/ },
  { id: 'online', rank: 1, ask: 'who is online', askBn: null,
    en: /\b(online|logged in|active (now|users)|at (their|the) desk right now)\b/,
    bn: /(অনলাইনে|লগইন|এখন সক্রিয়)/ },
  { id: 'leave', rank: 1, ask: 'leave requests', askBn: null,
    en: /\b(leaves?|leave (request|application|balance)|on leave|holidays?|vacations?|day off|time off)\b/,
    bn: /(ছুটি|ছুটির আবেদন|ছুটিতে)/ },
  /* "staff" / "কর্মী" qualifies almost anything the boss says — "staff loans",
     "কর্মী ঋণ", "staff attendance" — so rank 2: it wins only on its own. */
  { id: 'employee', rank: 2, ask: 'headcount', askBn: 'জনবল',
    en: /\b(employees?|staff|people|headcount|workers?|manpower|workforce|team size|departments?|how many (people|staff|employees))\b/,
    bn: /(কর্মী|স্টাফ|জনবল|লোকবল|কতজন (কর্মী|লোক|স্টাফ)|কর্মী সংখ্যা|বিভাগ)/ },
  { id: 'performance', rank: 1, ask: 'evaluate', askBn: null,
    en: /\b(performance|evaluat(e|ion)|appraisals?|rating|report card|assess)\b|\bhow (is|good is) .* (doing|performing)\b/,
    bn: /(কেমন করছে|কেমন করছেন|পারফর্ম|মূল্যায়ন|রিপোর্ট কার্ড)/ },
  { id: 'ranking', rank: 1, ask: 'rank employees', askBn: null,
    en: /\b(rank(ing)? (the |our |all )?(employees|staff|team|people)|leaderboard|(best|top|worst|weakest|strongest) (performer|employee|staff)|who (performs|is performing) (best|worst))\b/,
    bn: /(র‍্যাঙ্কিং|সেরা কর্মী|সবচেয়ে ভালো কর্মী|তালিকা ক্রম)/ },
  { id: 'loan', rank: 1, ask: 'loans', askBn: null,
    en: /\b(loans?|advances?|advance salary|borrowed|employee requests?)\b/,
    bn: /(ঋণ|লোন|অগ্রিম|অ্যাডভান্স)/ },
  { id: 'workload', rank: 1, ask: 'workload', askBn: null,
    en: /\b(workload|overloaded|who is (busy|free|idle)|who (has|have) nothing (to do|assigned)|nothing to do|unassigned|capacity|bandwidth|too much work)\b/,
    bn: /(কাজের চাপ|ব্যস্ত|খালি আছে|সক্ষমতা)/ },

  /* ---- customers, sales, work ---- */
  { id: 'lead', rank: 1, ask: 'pipeline', askBn: 'পাইপলাইন',
    en: /\b(leads?|pipelines?|prospects?|crm|sales funnel|funnel|conversion|win rate)\b/,
    bn: /(লিড|পাইপলাইন|সম্ভাব্য (ক্রেতা|গ্রাহক)|ফানেল)/ },
  { id: 'deal', rank: 1, ask: 'deals closing soon', askBn: 'পাইপলাইন',
    en: /\b(deals?|closing (soon|this)|expected revenue|forecast)\b/, bn: /(ডিল|চুক্তি হবে|ক্লোজ হবে)/ },
  { id: 'followup', rank: 1, ask: 'follow ups', askBn: null,
    en: /\b(follow[- ]?ups?|call list|who (should|do) (i|we) call)\b/, bn: /(ফলোআপ|ফলো-আপ|কাকে কল)/ },
  { id: 'customer', rank: 1, ask: 'top customers', askBn: null,
    en: /\b(customers?|clients?|buyers?|part(y|ies))\b/, bn: /(ক্রেতা|গ্রাহক|পার্টি|কাস্টমার)/ },
  { id: 'vendor', rank: 1, ask: 'top suppliers', askBn: null,
    en: /\b(vendors?|suppliers?|sellers?)\b/, bn: /(সরবরাহকারী|ভেন্ডর|সাপ্লায়ার)/ },
  { id: 'task', rank: 1, ask: 'task board', askBn: 'টাস্ক',
    en: /\b(tasks?|to-?do items?|assignments?|task board|backlog|work items?|working on|kaj|kajgulo)\b/,
    bn: /(টাস্ক|কাজ (বাকি|জমে|দেরি|কী|কি)|কাজের অবস্থা|অসমাপ্ত কাজ|কাজগুলো|কী কাজ)/ },
  { id: 'project', rank: 1, ask: 'projects', askBn: 'প্রকল্প',
    en: /\b(projects?|deliver(y|ies)|milestones?|at risk|behind schedule)\b/,
    bn: /(প্রজেক্ট|প্রকল্প|ডেলিভারি|ঝুঁকিতে|ঝুঁকি)/ },
  { id: 'todo', rank: 1, ask: 'office todos', askBn: null,
    en: /\b(office to-?dos?|checklists?|compliance|licen[cs]es?|vat return|mushak|renewals?)\b/,
    bn: /(অফিস চেকলিস্ট|লাইসেন্স|ভ্যাট|নবায়ন|কমপ্লায়েন্স)/ },
  { id: 'meeting', rank: 1, ask: null, askBn: null,
    en: /\b(meetings?|appointments?|visits?)\b/, bn: /(মিটিং|সভা|অ্যাপয়েন্টমেন্ট|বৈঠক)/ },
  { id: 'notice', rank: 1, ask: null, askBn: null,
    en: /\b(notices?|announcements?|circulars?|bulletins?)\b/, bn: /(নোটিশ|বিজ্ঞপ্তি|ঘোষণা)/ },

  /* ---- a record ---- */
  { id: 'profile', rank: 5, ask: null, askBn: null,
    en: /\b(profiles?|details|records?|information|info|who is|tell me about|dossier)\b/,
    bn: /(প্রোফাইল|তথ্য|বিস্তারিত|পরিচয়|কে ইনি|সম্পর্কে বলো)/ },
];

/* "assign him a task: check the ledger" — everything after that colon is the
   task's own wording, not what the sentence is about. Only the head is read for
   the subject, otherwise "check the ledger" turns an instruction into a
   question about journals and "call the customer" into one about customers. */
const BODY_CUE = /(tasks?|to-?dos?|assignments?|messages?|msgs?|notes?|notices?|reminders?|টাস্ক|কাজ|মেসেজ|বার্তা|নোটিশ)[^:]{0,20}:/i;
function subjectText(q) {
  const s = String(q || '');
  const m = BODY_CUE.exec(s);
  return m ? s.slice(0, m.index + m[0].length) : s;
}

/* বাংলা says WHERE with a case ending, not with a preposition: "খতিয়ানে গরমিল"
   is a discrepancy IN the ledger — the question is about the discrepancy, and
   খতিয়ান is only the place it lives. Without this the longest match wins and
   EON answers with the ledger's own summary, which is a confident answer to a
   question nobody asked. So a subject whose match is immediately followed by a
   locative vowel sign steps behind any other named subject in the sentence.
   It only ever changes the order — a locative subject standing alone still
   answers, which is why "ব্যাংকে কত টাকা" is still a cash question. */
const LOCATIVE = /^[েয়]/;
/** every subject this sentence names, best first */
function subjectsIn(q) {
  const raw = subjectText(q);
  const s = norm(raw);
  const bn = BN.test(q);
  const hits = [];
  for (const x of SUBJECTS) {
    let m = bn ? x.bn.exec(raw) : x.en.exec(s);
    let loc = 0;
    if (m && bn) loc = LOCATIVE.test(raw.slice(m.index + m[0].length)) ? 1 : 0;
    if (!m && bn) m = x.en.exec(s);            // "Imran এর payroll" — a Latin word inside Bangla
    if (m) hits.push({ subj: x, len: m[0].length, loc });
  }
  // a named part of the business beats a vague word; a place beats nothing;
  // inside a class the longest match wins, so "salary bill" is payroll and not
  // a supplier's bill
  hits.sort((a, b) => a.subj.rank - b.subj.rank || a.loc - b.loc || b.len - a.len);
  return hits.map((h) => h.subj);
}
function subjectOf(q) { return subjectsIn(q)[0] || null; }

/* "cash and receivables" — cheap chains, answered in one breath */
const CHAIN = /\band\b|\bplus\b|\bas well as\b|,| ও | এবং |আর /;
function chainOf(q) {
  if (!CHAIN.test(String(q))) return null;
  const all = subjectsIn(q).filter((x) => x.rank === 1).slice(0, 2);
  if (all.length < 2) return null;
  // answer them in the order he said them: "cash and receivables" starts with cash
  const s = norm(q), bn = BN.test(q);
  const at = (x) => { const m = (bn ? x.bn.exec(q) : null) || x.en.exec(s); return m ? m.index : 1e9; };
  return all.sort((a, b) => at(a) - at(b));
}

/* ---------- 3. who and when ----------
   The boss asks about a person once and then keeps going: "what is Imran's
   payroll" … "take me to his profile" … "give pay to him". EON remembers the
   last person named — in either language — so the follow-ups land. */
let lastWho = null;
const PRONOUN = /\b(he|him|his|she|her|hers|they|them|their|the same|that person)\b|(তার|তাঁর|ওর|তাকে|ওকে|উনার|উনি|তিনি)/i;
export function subject_of_last() { return lastWho; }
/** let another layer hand EON the person it has just answered about */
export function remember(w) { if (w && w.name) lastWho = w; return lastWho; }
function whoIn(D, q) {
  if (!D) return null;
  // a person the ERP knows — try the longest capitalised run, then any word
  /* Half of a hyphenated name is not a name. "Ha-Meem Group" is a party in the
     ledger; the run after the hyphen reads as "Meem Group", which found the
     employee Meem Rahman — EON answering about a person when the boss named a
     company, which is the money-shaped version of answering about the wrong
     human. A hyphen on either side disqualifies the run. */
  const caps = String(q).match(/(?<![A-Za-z-])[A-Z][a-z.]{1,15}(?:\s+[A-Z][a-z.]{1,15}){0,3}(?![-A-Za-z])/g) || [];
  const skip = /^(What|How|Any|Who|Show|Take|Assign|Last|The|Is|Are|Do|Can|Give|Open|Where|When|Why|EON|ERP)$/;
  for (const c of caps.filter((x) => !skip.test(x)).sort((a, b) => b.length - a.length)) {
    const e = P.findEmployee(D, c);
    if (e) {
      const tok = (x) => norm(x).split(/[^a-z]+/).filter((w) => w.length >= 3);
      if (tok(c).some((w) => tok(e.name).includes(w))) return { kind: 'employee', id: e.id, name: e.name, row: e };
    }
  }
  /* A party in the ledger, named in either script — the best match, not the
     first one the loop happens to reach. Matching on the party's first word
     alone made "Bengal Plywood" (a supplier we owe) resolve to "Bengal Agro"
     (a customer who owes us), because the receivables side is scanned first.
     That was cosmetic while a party was only ever the subject of a question;
     it is money as soon as the name decides who gets paid. Counting how many
     of the party's own words the sentence actually carries settles it — two
     beats one — while a one-word name still matches on its one word, so
     "pay Berger 45000" is not lost. */
  const nq = norm(q);
  let best = null;
  for (const type of ['receive', 'pay']) {
    for (const p of F.schedules(D, type, {}).byParty || []) {
      const nm = norm(p.party_name);
      if (nm.length <= 3) continue;
      const words = nm.split(' ').filter((w) => w.length >= 3);
      if (!words.length) continue;
      const hits = words.filter((w) => nq.includes(w)).length;
      /* Half the party's own words, at least. One word out of three is how
         "Green Delta Traders" answers to a sentence about Zaman Traders —
         "traders", "ceramics", "motors", "textiles" are what these names have
         in common, not what tells them apart. */
      if (!hits || hits * 2 < words.length) continue;
      // the whole name, written out, beats any number of loose word hits
      const score = nq.includes(nm) ? words.length + 1 : hits;
      if (!best || score > best.score) best = { kind: 'party', name: p.party_name, side: type, row: p, score };
    }
  }
  if (best) { delete best.score; return best; }
  return null;
}

/** who this sentence is about — the name in it, or the last person named.
    Possessives and of-forms need no special case: whoIn scans the whole
    sentence, so "X's attendance", "attendance of X" and "X attendance" all
    reach the same person, in any order, for every subject there is. */
function whoOrLast(D, q) {
  const w = whoIn(D, q);
  if (w) { lastWho = w; return w; }
  if (PRONOUN.test(q) && lastWho) return lastWho;
  return null;
}
function monthIn(q) {
  const s = norm(latinDigits(q));
  const i = MONTHS.findIndex((m) => s.includes(m.toLowerCase().slice(0, 3)));
  if (i >= 0) return i + 1;
  const j = BN_MONTH.findIndex((m) => q.includes(m));
  return j >= 0 ? j + 1 : null;
}

/* ============================================================
   A NAMED MONTH IS PART OF THE QUESTION.

   "what did we spend on marketing in June" came back with August's
   figures — the right shape of answer about the wrong period, said
   with the same confidence as a right one. That is worse than saying
   nothing, and it is the same class of mistake as answering about the
   wrong person.

   monthIn() above is deliberately loose because it also reads the
   month out of an ORDER ("pay Imran July salary 30000"), where the
   word is nearly always meant. A question is different: "who may
   approve this" is not about May, and "march the files over" is not
   about March. So a question's month must be a whole word, and bare
   "may"/"march" only counts when a year, the word month, or a
   preposition marks it as a date.
   ============================================================ */
/* The whole word or the usual abbreviation, and nothing else. A three-letter
   prefix with a wildcard after it reads "marketing" as March, which is how
   "what did we spend on marketing in June" answered about March — a month
   nobody mentioned, taken from the middle of another word. */
const MONTH_WORD = new RegExp(`\\b(${MONTHS.map((m) => `${m.toLowerCase()}|${m.slice(0, 3).toLowerCase()}`).join('|')}|sept)\\b`, 'i');
const AMBIGUOUS = /^(may|march|mar)$/i;
function namedMonth(q) {
  const s = norm(latinDigits(q));
  const m = MONTH_WORD.exec(s);
  if (m) {
    const i = MONTHS.findIndex((x) => x.toLowerCase().startsWith(m[1].toLowerCase().slice(0, 3)));
    if (i >= 0) {
      if (AMBIGUOUS.test(m[0]) && !/\b(in|of|for|during|last|this)\s+$|month|\b(19|20)\d\d\b/.test(s.slice(0, m.index) + s.slice(m.index + m[0].length))) return null;
      return i + 1;
    }
  }
  const j = BN_MONTH.findIndex((x) => String(q).includes(x));
  return j >= 0 ? j + 1 : null;
}
/** the named month as a YYYY-MM key, never in the future of the dataset */
function monthKeyFor(D, mon) {
  if (!mon) return null;
  const t = T(D); const y = +t.slice(0, 4), cur = +t.slice(5, 7);
  const year = mon > cur ? y - 1 : y;
  return `${year}-${String(mon).padStart(2, '0')}`;
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

/* When EON has just read a list off the ERP's own form — "City Bank Current ·
   bKash Merchant" — the boss answers by naming one of them. Those names carry
   ordinary subject words ("bank", "cash"), so without this the reply reads as a
   brand-new question about cash and the held payment is thrown away. What EON
   offered a moment ago is the answer to what EON asked a moment ago. */
function offered(q) {
  const list = (convo && convo.options) || [];
  const t = String(q).trim().toLowerCase();
  if (!list.length || !t) return null;
  return list.find((o) => String(o).toLowerCase() === t)
    || list.find((o) => String(o).toLowerCase().includes(t) || t.includes(String(o).toLowerCase()))
    || null;
}

/** a bare reply to EON's question — "July", "30000", "Imran", "লেজার চেক করো" */
function bareSlot(D, q, need) {
  const t = String(q).trim();
  if (!t) return null;
  if (need === 'month') { const m = monthIn(t) || (/^\d{1,2}$/.test(t) && +t >= 1 && +t <= 12 ? +t : null); return m; }
  if (need === 'amount') { const a = amountIn(t) || (/^[\d,]+$/.test(t) ? +t.replace(/,/g, '') : null); return a; }
  if (need === 'who') { const w = whoIn(D, t) || (P.findEmployee(D, t) ? { kind: 'employee', name: P.findEmployee(D, t).name, id: P.findEmployee(D, t).id, row: P.findEmployee(D, t) } : null); return w; }
  if (need === 'account') return offered(t) || (t.length > 2 ? t : null);
  return t.length > 2 ? t : null;     // task / text: take it as said
}

/* A salary settles one month's schedule, so EON will not pick the month itself.
   A party payment is the other case entirely: recordPayment has no month field
   to fill, so holding the conversation open for one stops a payment the ERP is
   already willing to take. */
function nextMissing(verb, slots) {
  const monthless = verb === 'pay' && slots.who && slots.who.kind === 'party';
  return (NEEDS[verb] || []).find((k) => !(monthless && k === 'month') && slots[k] == null);
}

function askFor(need, verb, bn) {
  const a = ASK[need];
  return { speak: bn ? a.bn(verb) : a.en(verb), detail: [], awaiting: need };
}

/** what EON is holding, said back so the boss can see it */
function heldSummary(verb, slots, bn) {
  const bits = [];
  if (slots.who) bits.push(slots.who.name);
  // the month is EON's own word, so a Bangla card must not read "July"
  if (slots.month) bits.push((bn ? BN_MONTH : MONTHS)[slots.month - 1]);
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
    // naming one of the options EON just offered is an answer, whatever words it contains
    const answersTheSlot = bareSlot(D, q, convo.asked) != null && (!ownSubject || !!offered(q));
    if (ownSubject && wordy && !answersTheSlot) convo = null;
  }

  // continuing a held command with a bare answer
  let justAnswered = false;
  if (convo && convo.asked) {
    const val = bareSlot(D, q, convo.asked);
    if (val != null) {
      convo.slots[convo.asked] = val;
      convo.asked = null;
      convo.at = now;
      justAnswered = true;
    }
  }

  /* Starting, or adding to, an instruction. Not when the utterance was the
     answer to EON's own question: "City Bank Current" reads as a verb to the
     scorer, and rebuilding the conversation around it would throw away the who,
     the month and the amount the boss has already given. */
  if (NEEDS[verb] && !justAnswered) {
    if (!convo || convo.verb !== verb) convo = { verb, slots: {}, asked: null, at: now, lang: bn ? 'bn' : 'en' };
    Object.assign(convo.slots, slotsIn(D, q, verb));
    if (bn) convo.lang = 'bn';
    convo.at = now;
  }
  if (!convo) return null;

  /* The language belongs to the conversation, not to the last word said. EON
     asks "কোন অ্যাকাউন্ট থেকে যাবে?" and the boss answers "City Bank Current",
     because that is what the ERP calls the account — there is no Bangla spelling
     of it to type. Re-reading the language off that reply would answer a Bangla
     instruction with an English payment card. */
  const speakBn = convo.lang ? convo.lang === 'bn' : bn;

  const need = nextMissing(convo.verb, convo.slots);
  if (need) {
    convo.asked = need;
    const a = askFor(need, convo.verb, speakBn);
    const held = heldSummary(convo.verb, convo.slots, speakBn);
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
      if (!A) throw new Error(speakBn ? 'অ্যাকশন লেয়ারটা লোড হয়নি' : 'the action layer is not loaded');
      const plan = await A.planPayment(slots.who, { month: slots.month, amount: slots.amount, bank: slots.account });

      /* The ERP's mark-paid form has bank_id => required. An account is not a
         detail EON may pick on the boss's behalf — money leaves the one he
         names — so the conversation stays open for it, exactly like the month
         and the amount, and the ERP's own list is what he chooses from. */
      if (plan.needsAccount && !slots.account) {
        const names = (plan.accounts || []).map((o) => o.text);
        convo = { verb: 'pay', slots, asked: 'account', at: Date.now(), options: names, lang: speakBn ? 'bn' : 'en' };
        return {
          speak: speakBn ? 'কোন অ্যাকাউন্ট থেকে যাবে?' : 'Which account should it come from?',
          detail: [heldSummary('pay', slots, speakBn)].filter(Boolean).concat(names.length ? [names.join(' · ')] : []),
          awaiting: 'account',
        };
      }
      if (speakBn) {
        plan.lang = 'bn';
        const mon = slots.month ? BN_MONTH[slots.month - 1] : null;
        plan.summary_bn = slots.who.kind === 'employee'
          ? `${slots.who.name}-কে ${mon ? mon + ' মাসের ' : ''}বেতন বাবদ ${fmtBDT(slots.amount)} দেবো`
          : `${slots.who.name}-কে ${fmtBDT(slots.amount)} দেবো, পার্টি স্টেটমেন্টে বসিয়ে`;
      }
      const c = await A.propose(plan);
      // the held summary stays on the card: the boss should see his own words beside the ERP's fields
      return Object.assign({}, c, {
        detail: [heldSummary('pay', slots, speakBn)].filter(Boolean).concat(c.detail || []),
      });
    }
  } catch (e) {
    /* The cause is an English Error message, and dropping it whole into a Bangla
       sentence is the leak this file exists to prevent. The one cause that
       happens in the room — the ERP wanting a password first — gets its own
       Bangla sentence; anything else keeps the English detail on its own line
       rather than inside the Bangla one. */
    const why = speakBn ? (e.signIn ? 'ইআরপি ফর্মটা দেখানোর আগে সাইন ইন চেয়েছে' : 'ইআরপির ফর্মটা পাওয়া যায়নি') : e.message;
    if (v === 'pay') {
      return {
        speak: speakBn
          ? `কিছুই পোস্ট করিনি — ${why}। কোনো টাকা যায়নি।`
          : `I have posted nothing — ${e.message}. No money has moved.`,
        detail: [],
        actions: (() => { const n = N(); if (!n) return []; const h = (n.find('payment schedules', 1) || [])[0]; return h ? [{ label: speakBn ? 'পেমেন্ট স্ক্রিন' : 'Open the payment screen', kind: 'erp-open', href: n.url(h.uri) }] : []; })(),
      };
    }
    return { speak: speakBn ? `সেটআপ করতে পারলাম না — ${why}।` : `I could not set that up: ${e.message}`, detail: speakBn && !e.signIn ? [e.message] : [] };
  }
  return null;
}

/* ============================================================
   EON'S OWN WORDS — the subjects no answerer covers.

   qa.js has no intent for a chart of accounts, a notice board, the
   last voucher or today's sales; bangla.js has no intent for
   expenses, leave, loans or ageing. Rather than hand a Bangla
   question an English answer — or nothing at all — EON writes those
   sentences itself, off the same layer functions, natively in both
   languages. Nothing here is translated from the other side.
   ============================================================ */
const openA = (bn, query) => { const n = N(); if (!n) return []; const h = (n.find(query, 1) || [])[0]; return h ? [{ label: bn ? 'খুলুন' : 'Open', kind: 'erp-open', href: n.url(h.uri) }] : []; };

const NATIVE = {
  sale(D, s) {
    /* a named month replaces this one, and "today" only means anything inside
       the current month — asked about June, EON must not report June's total
       beside today's sales */
    const t = T(D), mk = s.month || monthKey(t), co = s.company;
    const thisMonth = mk === monthKey(t);
    const rows = [].concat(D.sales || [], D.ticket_sales || [], D.visa_sales || [])
      .filter((x) => co == null || +x.company_id === co);
    const today = rows.filter((x) => String(x.date || '').slice(0, 10) === t);
    const month = rows.filter((x) => String(x.date || '').slice(0, 7) === mk);
    const S = (a) => a.reduce((n, r) => n + (+r.total || 0), 0);
    const due = (a) => a.reduce((n, r) => n + (+r.due_amount || 0), 0);
    return {
      en: thisMonth
        ? `${today.length} sale${today.length === 1 ? '' : 's'} today for ${fmtBDT(S(today))}; ${month.length} this month for ${fmtBDT(S(month))}, of which ${fmtBDT(due(month))} is still unpaid.`
        : `${MONTHS[+mk.slice(5) - 1]} ${mk.slice(0, 4)}: ${month.length} sale${month.length === 1 ? '' : 's'} for ${fmtBDT(S(month))}, of which ${fmtBDT(due(month))} is still unpaid.`,
      bn: thisMonth
        ? `আজ ${bNum(today.length)}টি বিক্রি, ${bMoney(S(today))}; এ মাসে ${bNum(month.length)}টি, মোট ${bMoney(S(month))} — তার ${bMoney(due(month))} এখনো অপরিশোধিত।`
        : `${bMonthKey(mk)} মাসে ${bNum(month.length)}টি বিক্রি, মোট ${bMoney(S(month))} — তার ${bMoney(due(month))} এখনো অপরিশোধিত।`,
      detail: month.slice(-6).reverse().map((r) => `${r.date} · ${r.invoice_no || r.invoice || r.id} — ${fmtBDT(+r.total || 0)}`),
      screen: 'sales', view: 'crm',
    };
  },
  /* Profit and payroll for a NAMED month. Without these the answerers own both
     subjects, and neither of them takes a month — which is how "June" came back
     with August's numbers. */
  profit(D, s) {
    if (!s.month) return null;                    // this month is the answerers' job
    const pl = F.profitAndLoss(D, { from: s.month + '-01', to: s.month + '-31', company: s.company });
    const when = MONTHS[+s.month.slice(5) - 1] + ' ' + s.month.slice(0, 4);
    return {
      en: `${when}: revenue ${fmtBDT(pl.totalIncome)}, direct cost ${fmtBDT(pl.totalDirect || 0)}, operating expenses ${fmtBDT(pl.totalOpex)} — net ${pl.netProfit >= 0 ? 'profit' : 'loss'} ${fmtBDT(Math.abs(pl.netProfit))} (${pl.margin}% margin).`,
      bn: `${bMonthKey(s.month)} মাসে আয় ${bMoney(pl.totalIncome)}, পরিচালন খরচ ${bMoney(pl.totalOpex)}, নিট ${pl.netProfit >= 0 ? 'মুনাফা' : 'লোকসান'} ${bMoney(Math.abs(pl.netProfit))} — মার্জিন ${bNum(pl.margin)} শতাংশ।`,
      detail: [], screen: 'profit loss', view: 'finance',
    };
  },
  payroll(D, s) {
    if (!s.month) return null;
    const pr = P.payroll(D, { company: s.company, month: s.month });
    if (!pr || !pr.heads) return {
      en: `There is no payroll run for ${MONTHS[+s.month.slice(5) - 1]} ${s.month.slice(0, 4)} in the ERP.`,
      bn: `${bMonthKey(s.month)} মাসের কোনো বেতনের রান ইআরপিতে নেই।`,
      detail: [], screen: 'payroll', view: 'people',
    };
    return {
      en: `Payroll for ${MONTHS[+s.month.slice(5) - 1]} ${s.month.slice(0, 4)}: ${pr.heads} payslips, gross ${fmtBDT(pr.gross)}, deductions ${fmtBDT(pr.deductions)}, net ${fmtBDT(pr.net)}${pr.pending.length ? `; ${pr.pending.length} still unpaid` : '; all paid'}.`,
      bn: `${bMonthKey(s.month)} মাসের বেতন: ${bNum(pr.heads)}টি পে-স্লিপ, মোট ${bMoney(pr.gross)}, কর্তন ${bMoney(pr.deductions)}, নিট ${bMoney(pr.net)}${pr.pending.length ? `; ${bNum(pr.pending.length)} জনের এখনো বাকি` : '; সবার পরিশোধিত'}।`,
      detail: [], screen: 'payroll', view: 'people',
    };
  },
  expense(D, s) {
    const e = F.expensesVsBudget(D, s);
    const when = s.month ? MONTHS[+e.month.slice(5) - 1] + ' ' + e.month.slice(0, 4) : 'this month';
    return {
      en: `Spending ${s.month ? 'in ' + when : when} is ${fmtBDT(e.totalSpent)} against a budget of ${fmtBDT(e.totalBudget)}${e.over.length ? `; ${e.over.length} categor${e.over.length === 1 ? 'y is' : 'ies are'} over` : ', nothing over budget'}.`,
      bn: `${bMonthKey(e.month)} মাসে খরচ ${bMoney(e.totalSpent)}, বাজেট ${bMoney(e.totalBudget)}${e.over.length ? `; ${bNum(e.over.length)}টি খাত বাজেট ছাড়িয়েছে` : '; কোনো খাত বাজেট ছাড়ায়নি'}।`,
      detail: e.rows.slice(0, 6).map((r) => `${r.category}: ${fmtBDT(r.spent)}${r.budget ? ` / ${fmtBDT(r.budget)}` : ''}`),
      screen: 'expenses', view: 'finance',
    };
  },
  leave(D, s) {
    const l = P.leaves(D, s);
    return {
      en: `${l.pending.length} leave request${l.pending.length === 1 ? '' : 's'} waiting, ${l.onLeaveToday.length} on leave today, ${l.upcoming.length} starting within a fortnight.`,
      bn: `${bNum(l.pending.length)}টি ছুটির আবেদন অপেক্ষমাণ, আজ ছুটিতে ${bNum(l.onLeaveToday.length)} জন, দুই সপ্তাহের মধ্যে ছুটি নেবেন ${bNum(l.upcoming.length)} জন।`,
      detail: l.pending.slice(0, 6).map((x) => `${x.name} — ${x.leave_type} ${x.days}d (${x.start_date})`),
      screen: 'leave', view: 'people',
    };
  },
  loan(D, s) {
    const ln = P.loans(D, s);
    return {
      en: `${ln.running.length} staff loan${ln.running.length === 1 ? '' : 's'} running, ${fmtBDT(ln.outstanding)} outstanding, recovering ${fmtBDT(ln.monthlyRecovery)} a month; ${ln.advancesPending.length} advance request${ln.advancesPending.length === 1 ? '' : 's'} pending.`,
      bn: `${bNum(ln.running.length)}টি কর্মী ঋণ চলমান, বাকি ${bMoney(ln.outstanding)}, মাসে আদায় ${bMoney(ln.monthlyRecovery)}; ${bNum(ln.advancesPending.length)}টি অগ্রিমের আবেদন অপেক্ষমাণ।`,
      detail: ln.running.slice(0, 6).map((l) => `${l.name} — ${fmtBDT(+l.remaining_amount || 0)} left of ${fmtBDT(+l.amount || 0)}`),
      screen: 'loan', view: 'people',
    };
  },
  journal(D, s) {
    const co = s.company;
    const rows = (D.journal_entries || []).filter((e) => co == null || +e.company_id === co)
      .slice().sort((a, b) => String(b.date).localeCompare(String(a.date)) || (+b.id || 0) - (+a.id || 0));
    if (!rows.length) return { en: 'The ledger has no entries yet.', bn: 'খতিয়ানে এখনো কোনো দাখিলা নেই।', detail: [], screen: 'journals', view: 'finance' };
    const e = rows[0];
    const amt = (e.items || []).reduce((n, i) => n + (+i.debit || 0), 0);
    return {
      en: `${rows.length} journal entries on file. The last posting was ${e.date} — ${e.description || e.reference || e.source} for ${fmtBDT(amt)}.`,
      bn: `খতিয়ানে ${bNum(rows.length)}টি দাখিলা আছে। সর্বশেষ পোস্টিং ${e.date} — ${e.description || e.reference || e.source}, ${bMoney(amt)}।`,
      detail: (e.items || []).slice(0, 6).map((i) => `${i.account_code || ''} ${i.account_name || ''} — ${(+i.debit || 0) ? 'Dr ' + fmtBDT(+i.debit) : 'Cr ' + fmtBDT(+i.credit)}`),
      screen: 'journals', view: 'finance',
    };
  },
  account(D, s) {
    const co = s.company;
    const acc = (D.accounts || []).filter((a) => co == null || a.company_id == null || +a.company_id === co);
    const by = {};
    acc.forEach((a) => { const t = a.type || 'other'; by[t] = (by[t] || 0) + 1; });
    const tb = F.trialBalance(D, s);
    return {
      en: `The chart of accounts holds ${acc.length} heads (${Object.keys(by).map((k) => `${by[k]} ${k}`).join(', ')}). The trial balance ${tb.balanced ? 'balances' : 'does NOT balance'} at ${fmtBDT(tb.totalDebit)}.`,
      bn: `হিসাবের চার্টে ${bNum(acc.length)}টি খাত আছে। রেওয়ামিল ${tb.balanced ? 'মিলেছে' : 'মেলেনি'} — ${bMoney(tb.totalDebit)}।`,
      detail: Object.keys(by).map((k) => `${k}: ${by[k]}`),
      screen: 'chart of accounts', view: 'finance',
    };
  },
  error(D, s) {
    let r = null;
    try { r = typeof window !== 'undefined' && window.EonEntity ? window.EonEntity.accountingErrors(s) : null; } catch (x) { r = null; }
    if (!r) {
      const co = s.company;
      const entries = (D.journal_entries || []).filter((e) => co == null || +e.company_id === co);
      let bad = 0;
      entries.forEach((e) => {
        const dr = (e.items || []).reduce((n, i) => n + (+i.debit || 0), 0);
        const cr = (e.items || []).reduce((n, i) => n + (+i.credit || 0), 0);
        if (Math.abs(dr - cr) > 0.5) bad++;
      });
      r = { problems: bad ? [{ text: `${bad} journal entries do not balance` }] : [], checked: entries.length, samples: [] };
    }
    if (!r.problems.length) return {
      en: `I checked ${r.checked} journal entries — every one balances, every account exists, none are future-dated. Nothing is wrong in the books.`,
      bn: `${bNum(r.checked)}টি দাখিলা যাচাই করেছি — সবগুলো মিলেছে, চার্টের বাইরের কোনো হিসাব খাত নেই, ভবিষ্যৎ তারিখের দাখিলাও নেই। হিসাবে কোনো ভুল পাইনি।`,
      detail: [], screen: 'journals', view: 'finance',
    };
    return {
      en: `Yes — ${r.problems.map((p) => p.text).join('; ')}, out of ${r.checked} entries checked.`,
      bn: `হ্যাঁ — ${bNum(r.checked)}টি দাখিলার মধ্যে গরমিল আছে: ${r.problems.map((p) => p.text).join('; ')}।`,
      detail: (r.samples || []).slice(0, 6), screen: 'journals', view: 'finance',
    };
  },
  customer(D, s) {
    const tp = F.topParties(D, s), c = tp.customers, ar = F.receivables(D, s);
    return {
      en: `${(D.customers || []).length} customers on file; over the last ${tp.window} days the biggest is ${c[0] ? `${c[0].name} at ${fmtBDT(c[0].total)}` : 'none'}. ${fmtBDT(ar.total)} is receivable from customers.`,
      bn: `খাতায় ${bNum((D.customers || []).length)} জন ক্রেতা; গত ${bNum(tp.window)} দিনে সবচেয়ে বড় ${c[0] ? `${c[0].name} — ${bMoney(c[0].total)}` : 'কেউ নেই'}। ক্রেতাদের কাছে পাওনা ${bMoney(ar.total)}।`,
      detail: c.slice(0, 6).map((x, i) => `${i + 1}. ${x.name} — ${fmtBDT(x.total)}${x.due ? ` (due ${fmtBDT(x.due)})` : ''}`),
      screen: 'customers', view: 'finance',
    };
  },
  vendor(D, s) {
    const tp = F.topParties(D, s), v = tp.suppliers, ap = F.payables(D, s);
    return {
      en: `${(D.suppliers || []).length} suppliers on file; over the last ${tp.window} days the biggest is ${v[0] ? `${v[0].name} at ${fmtBDT(v[0].total)}` : 'none'}. ${fmtBDT(ap.total)} is payable to suppliers.`,
      bn: `খাতায় ${bNum((D.suppliers || []).length)} জন সরবরাহকারী; গত ${bNum(tp.window)} দিনে সবচেয়ে বড় ${v[0] ? `${v[0].name} — ${bMoney(v[0].total)}` : 'কেউ নেই'}। সরবরাহকারীদের দেনা ${bMoney(ap.total)}।`,
      detail: v.slice(0, 6).map((x, i) => `${i + 1}. ${x.name} — ${fmtBDT(x.total)}${x.due ? ` (due ${fmtBDT(x.due)})` : ''}`),
      screen: 'suppliers', view: 'finance',
    };
  },
  meeting(D, s) {
    const t = T(D);
    const want = /meeting|appointment|visit|মিটিং|সভা/i;
    const todos = (D.office_todos || []).filter((x) => (s.company == null || +x.company_id === s.company) && want.test(x.title || '') && x.status !== 'completed');
    const tk = (O.tasks(D, s).open || []).filter((k) => want.test(k.title || ''));
    const rows = todos.map((x) => ({ what: x.title, when: x.due_date })).concat(tk.map((k) => ({ what: k.title, when: k.due_date })))
      .sort((a, b) => String(a.when || '').localeCompare(String(b.when || '')));
    if (!rows.length) return { en: 'Nothing like a meeting is on the books.', bn: 'খাতায় কোনো মিটিং নেই।', detail: [], screen: 'todo', view: 'ops' };
    const today = rows.filter((r) => r.when === t);
    return {
      en: `${rows.length} meeting${rows.length === 1 ? '' : 's'} on the books${today.length ? `, ${today.length} today` : ''}. Next: ${rows[0].what}${rows[0].when ? ` on ${rows[0].when}` : ''}.`,
      bn: `খাতায় ${bNum(rows.length)}টি মিটিং আছে${today.length ? `, আজ ${bNum(today.length)}টি` : ''}। পরেরটি: ${rows[0].what}${rows[0].when ? ` — ${rows[0].when}` : ''}।`,
      detail: rows.slice(0, 6).map((r) => `${r.when || 'no date'} — ${r.what}`),
      screen: 'todo', view: 'ops',
    };
  },
  notice(D, s) {
    const t = T(D);
    const all = (D.notices || []).filter((x) => s.company == null || +x.company_id === s.company);
    const live = all.filter((x) => !x.expires_at || x.expires_at >= t);
    if (!all.length) return { en: 'No notices have been published.', bn: 'কোনো নোটিশ প্রকাশ করা হয়নি।', detail: [], screen: 'notice', view: 'ops' };
    return {
      en: `${live.length} notice${live.length === 1 ? '' : 's'} still live out of ${all.length}. Latest: ${all[all.length - 1].title}.`,
      bn: `${bNum(all.length)}টি নোটিশের মধ্যে ${bNum(live.length)}টি এখনো কার্যকর। সর্বশেষ: ${all[all.length - 1].title}।`,
      detail: live.slice(0, 6).map((x) => `${x.published_at} — ${x.title}`),
      screen: 'notice', view: 'ops',
    };
  },
  overdue(D, s) {
    const ar = F.receivables(D, s), ap = F.payables(D, s);
    return {
      en: `${fmtBDT(ar.overdueTotal)} is overdue from customers across ${ar.overdue.length} items, and ${fmtBDT(ap.overdueTotal)} is overdue to suppliers.`,
      bn: `ক্রেতাদের কাছ থেকে ${bMoney(ar.overdueTotal)} সময় পার হয়ে গেছে (${bNum(ar.overdue.length)}টি), আর সরবরাহকারীদের ${bMoney(ap.overdueTotal)} দেরিতে পড়ে আছে।`,
      detail: ar.byParty.filter((x) => x.overdue > 0).slice(0, 6).map((x, i) => `${i + 1}. ${x.party_name} — ${fmtBDT(x.overdue)} · ${x.oldest}d`),
      screen: 'payment schedule', view: 'finance',
    };
  },
  aging(D, s) {
    const ar = F.receivables(D, s);
    return {
      en: `Receivables by age: ${ar.buckets.map((b) => `${b.bucket} ${fmtBDT(b.amount)}`).join(', ')}.`,
      bn: `পাওনার বয়স অনুযায়ী: ${ar.buckets.map((b) => `${b.bucket} — ${bMoney(b.amount)}`).join(', ')}।`,
      detail: ar.buckets.map((b) => `${b.bucket}: ${b.count} items, ${fmtBDT(b.amount)}`),
      screen: 'payment schedule', view: 'finance',
    };
  },
  runway(D, s) {
    const r = F.runway(D, s);
    return {
      en: `Cash ${fmtBDT(r.cash)} against an average monthly outflow of ${fmtBDT(r.avgMonthlyOutflow)} — ${r.monthsOfCover == null ? 'no outflow history yet' : `${r.monthsOfCover} months of cover`}${r.burning ? ', and the business is burning cash' : ''}.`,
      bn: `হাতে নগদ ${bMoney(r.cash)}, মাসে গড় খরচ ${bMoney(r.avgMonthlyOutflow)} — ${r.monthsOfCover == null ? 'হিসাব করার মতো ইতিহাস নেই' : `${BNG.digits(r.monthsOfCover)} মাস চলার মতো`}${r.burning ? ', এবং নগদ কমছে' : ''}।`,
      detail: [], screen: 'bank', view: 'finance',
    };
  },
  online(D, s) {
    const now = Date.now();
    const on = (D.employees || []).filter((e) => e.status === 'active' && (s.company == null || +e.company_id === s.company) && e.last_seen_at && now - Date.parse(e.last_seen_at) < 15 * 60000);
    return {
      en: `${on.length} ${on.length === 1 ? 'person is' : 'people are'} active in the ERP right now.`,
      bn: `এই মুহূর্তে ইআরপিতে সক্রিয় আছেন ${bNum(on.length)} জন।`,
      detail: on.slice(0, 8).map((e) => `${e.name} — ${e.department || ''}`),
      screen: 'user', view: 'people',
    };
  },
  /* Who is ahead and who is behind. qa.js has an English leaderboard, but there
     was no Bangla one at all, so "সেরা কর্মী কে" came back in English — the one
     thing a Bangla answer may never do. Written natively on both sides. */
  ranking(D, s) {
    /* evaluate() returns the employee row under `employee`, not a flat `name` —
       reading x.name printed "undefined 91/100" in both languages */
    const nameOf = (x) => (x.employee && x.employee.name) || x.name || '';
    const r = P.ranking(D, s);
    const top = r.top.slice(0, 3), low = r.bottom.slice(0, 2);
    if (!top.length) return null;
    return {
      en: `Best over the last 30 days: ${top.map((x) => `${nameOf(x)} ${x.score}/100 (${x.grade})`).join(', ')}. Weakest: ${low.map((x) => `${nameOf(x)} ${x.score}`).join(', ')}.`,
      bn: `গত ৩০ দিনে সবচেয়ে এগিয়ে: ${top.map((x) => `${nameOf(x)} ${bNum(x.score)}/১০০ (গ্রেড ${x.grade})`).join(', ')}। সবচেয়ে পিছিয়ে: ${low.map((x) => `${nameOf(x)} ${bNum(x.score)}`).join(', ')}।`,
      detail: r.top.slice(0, 8).map((x, i) => `${i + 1}. ${nameOf(x)} — ${x.score}/100 (${x.grade}), attendance ${x.attendancePct}%, ${x.tasksDone} tasks done`),
      screen: 'user', view: 'people',
    };
  },
  workload(D, s) {
    const t = O.tasks(D, s);
    return {
      en: `${t.open.length} open tasks across ${t.load.length} people; ${t.overloaded.length} carry six or more, and ${t.idle.length} skilled staff have nothing assigned.`,
      bn: `${bNum(t.load.length)} জনের হাতে ${bNum(t.open.length)}টি খোলা কাজ; ${bNum(t.overloaded.length)} জনের চাপ বেশি, আর ${bNum(t.idle.length)} জন দক্ষ কর্মীর হাতে কোনো কাজ নেই।`,
      detail: t.load.slice(0, 6).map((r) => `${r.name} — ${r.open} open, ${r.overdue} overdue`),
      screen: 'tasks', view: 'ops',
    };
  },
  todo(D, s) {
    const td = O.todos(D, s);
    return {
      en: `${td.open.length} office to-dos are open, ${td.overdue.length} of them overdue${td.high.length ? `, ${td.high.length} marked high` : ''}.`,
      bn: `অফিসের ${bNum(td.open.length)}টি কাজ খোলা আছে, তার ${bNum(td.overdue.length)}টির সময় পার${td.high.length ? `, ${bNum(td.high.length)}টি জরুরি` : ''}।`,
      detail: td.overdue.slice(0, 6).map((x) => `${x.due_date} — ${x.title}`),
      screen: 'todo', view: 'ops',
    };
  },
  profile(D, s) {
    const h = P.headcount(D, s);
    const one = (D.employees || []).find((e) => e.status === 'active') || {};
    return {
      en: `I can pull a dossier on any of the ${h.total} people on the payroll — say the name, for example “tell me about ${one.name || 'Imran'}”.`,
      bn: `বেতনভুক্ত ${bNum(h.total)} জনের যে কারো পূর্ণ তথ্য দিতে পারি — নামটা বলুন, যেমন “${one.name || 'Imran'} সম্পর্কে বলো”।`,
      detail: [], screen: 'user', view: 'people',
    };
  },
  dues(D, s) {
    const ar = F.receivables(D, s), ap = F.payables(D, s);
    return {
      en: `${fmtBDT(ar.total)} is due to us and ${fmtBDT(ap.total)} is due from us; ${fmtBDT(ar.overdueTotal)} of the receivable is already overdue.`,
      bn: `আমাদের পাওনা ${bMoney(ar.total)}, দেনা ${bMoney(ap.total)}; পাওনার মধ্যে ${bMoney(ar.overdueTotal)} সময় পার হয়ে গেছে।`,
      detail: ar.byParty.slice(0, 6).map((x, i) => `${i + 1}. ${x.party_name} — ${fmtBDT(x.due)}`),
      screen: 'payment schedule', view: 'finance',
    };
  },
};

/* ---------- the subject, answered by whoever owns it ---------- */
/* the subjects whose answer is a period, and which therefore must honour a month
   the boss named rather than quietly reporting the current one */
const MONTHLY = new Set(['expense', 'sale', 'profit', 'payroll', 'payslip']);

function subjectAnswer(D, s, subj, q, bn) {
  const c = { company: s.company };
  /* A named month goes straight to EON's own sentence: qa.js and bangla.js both
     answer these subjects, and neither takes a month, so handing them the
     question returns this month's figures under June's question. */
  if (s.month && MONTHLY.has(subj.id)) {
    const nat = NATIVE[subj.id];
    try {
      const n = nat && nat(D, s);
      if (n) return { speak: bn ? n.bn : n.en, detail: n.detail || [], actions: n.screen ? openA(bn, n.screen) : [], view: n.view };
    } catch (e) { /* fall through and say so */ }
  }
  /* 1. the answerer that owns THIS subject — never the raw sentence.
        Handing the sentence back is what turned "লিড কেমন চলছে?" into the
        morning brief: bangla.js sees "কেমন চলছে" and answers that instead. */
  if (bn && subj.askBn && typeof window !== 'undefined' && window.EonBangla && window.EonBangla.answer) {
    try { const r = window.EonBangla.answer(subj.askBn, c); if (r && r.speak) return r; } catch (e) { /* fall on */ }
  }
  if (!bn && subj.ask && typeof window !== 'undefined' && window.EonErpQA) {
    try { const r = window.EonErpQA.answer(subj.ask, c); if (r && r.speak) return r; } catch (e) { /* fall on */ }
  }
  // 2. EON's own sentence, written natively in the language asked
  const nat = NATIVE[subj.id];
  if (nat) {
    try {
      const n = nat(D, s);
      if (n) return { speak: bn ? n.bn : n.en, detail: n.detail || [], actions: n.screen ? openA(bn, n.screen) : [], view: n.view };
    } catch (e) { /* fall on */ }
  }
  // 3. asked in Bangla, but only the English answerer computes this one
  if (bn && subj.ask && typeof window !== 'undefined' && window.EonErpQA) {
    try { const r = window.EonErpQA.answer(subj.ask, c); if (r && r.speak) return r; } catch (e) { /* fall on */ }
  }
  // 4. nothing computes it — open the screen rather than look deaf
  const n2 = N();
  const hit = n2 ? (n2.find(subj.ask || subj.id, 1) || [])[0] : null;
  if (hit) {
    const url = n2.url(hit.uri);
    return {
      speak: bn ? `${subj.id} নিয়ে সরাসরি হিসাব এখনো নেই, তবে স্ক্রিনটা খুলে দিতে পারি।`
                : `I do not have a figure for ${subj.id} yet, but I can open the screen.`,
      detail: [], actions: [{ label: bn ? 'খুলুন' : 'Open', kind: 'erp-open', href: url }],
    };
  }
  return null;
}

/* ---------- one person, one subject ----------
   Every subject that means something about a human answers about that
   human — in either language, with the name said in any order. */
function personAnswer(D, s, subj, who, q, bn) {
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

  if (who.kind !== 'employee') {
    // a customer or a supplier in the ledger — what they have is a balance
    if (['dues', 'receivable', 'payable', 'overdue', 'aging', 'customer', 'vendor', 'sale', 'profile'].includes(subj.id)) {
      const row = who.row || {};
      return {
        speak: say(q, `${who.name}: ${fmtBDT(row.due || 0)} outstanding, ${fmtBDT(row.overdue || 0)} of it overdue${row.oldest ? ` (oldest ${row.oldest} days)` : ''}.`,
                      `${who.name}: ${bMoney(row.due || 0)} বকেয়া, তার ${bMoney(row.overdue || 0)} সময় পার${row.oldest ? ` (সবচেয়ে পুরনো ${bNum(row.oldest)} দিন)` : ''}।`),
        detail: [], actions: openA(bn, 'party statement'), view: 'finance',
      };
    }
    return null;
  }

  const e = who.row || {};
  switch (subj.id) {
    case 'task': case 'workload': case 'todo': {
      const mine = (O.tasks(D, s).all || []).filter((t) => (t.assigned_to || []).includes(who.id));
      const open = mine.filter((t) => t.status !== 'done');
      const late = open.filter((t) => t.due_date && t.due_date < T(D));
      return {
        speak: say(q, `${who.name} has ${open.length} active task${open.length === 1 ? '' : 's'}${late.length ? `, ${late.length} overdue` : ''}${open.length ? `: ${open.slice(0, 3).map((t) => t.title).join('; ')}` : ''}.`,
          `${who.name}-এর ${bNum(open.length)}টি চলমান কাজ আছে${late.length ? `, ${bNum(late.length)}টির সময় পার` : ''}${open.length ? `: ${open.slice(0, 3).map((t) => t.title).join('; ')}` : ''}।`),
        detail: open.slice(0, 6).map((t) => `${t.title} — ${t.status}${t.due_date ? `, ${t.due_date}` : ''}`),
        actions: openA(bn, 'tasks'), view: 'ops',
      };
    }
    case 'payroll': {
      const pr = P.payroll(D, s);
      const mine = (pr.rows || []).filter((p) => p.user_id === who.id || p.name === who.name);
      const unpaid = mine.filter((p) => p.status === 'Pending').reduce((n, p) => n + (+p.net_salary || 0), 0);
      const left = (P.loans(D, s).running || []).filter((l) => l.user_id === who.id).reduce((n, l) => n + (+l.remaining_amount || 0), 0);
      const sal = +e.salary || 0;
      const bits = [
        sal ? say(q, `salary ${fmtBDT(sal)}/month`, `বেতন ${bMoney(sal)}/মাস`) : null,
        unpaid ? say(q, `${fmtBDT(unpaid)} unpaid`, `${bMoney(unpaid)} বকেয়া`) : say(q, `${pr.month} paid`, `${bMonthKey(pr.month)} পরিশোধিত`),
        left ? say(q, `loan ${fmtBDT(left)} left`, `ঋণ বাকি ${bMoney(left)}`) : null,
      ].filter(Boolean);
      return {
        speak: `${who.name} — ${bits.join(', ')}${bn ? '।' : '.'}`,
        detail: [`${e.designation || ''}${e.department ? ' · ' + e.department : ''}`.trim()].filter(Boolean),
        actions: openA(bn, 'employee salaries'), view: 'people',
      };
    }
    case 'dues': case 'receivable': case 'payable': case 'overdue': case 'loan': {
      const pr = P.payroll(D, s);
      const unpaid = (pr.pending || []).filter((p) => p.user_id === who.id || p.name === who.name)
        .reduce((n, p) => n + (+p.net_salary || +p.net || 0), 0);
      const ln = P.loans(D, s);
      const loans = (ln.running || []).filter((l) => l.user_id === who.id);
      const left = loans.reduce((n, l) => n + (+l.remaining_amount || 0), 0);
      const adv = (ln.advancesApproved || []).concat(ln.advancesPending || []).filter((a) => a.user_id === who.id);
      const advTotal = adv.reduce((n, a) => n + (+a.amount || 0), 0);
      const en = [], bnp = [];
      if (unpaid) { en.push(`unpaid salary ${fmtBDT(unpaid)}`); bnp.push(`বকেয়া বেতন ${bMoney(unpaid)}`); }
      if (left) { en.push(`loan outstanding ${fmtBDT(left)}`); bnp.push(`ঋণ বাকি ${bMoney(left)}`); }
      if (advTotal) { en.push(`advances ${fmtBDT(advTotal)}`); bnp.push(`অগ্রিম ${bMoney(advTotal)}`); }
      if (!en.length) return {
        speak: say(q, `Nothing is outstanding for ${who.name} — no unpaid salary, no loan, no advance.`,
                      `${who.name}-এর কিছুই বকেয়া নেই — বেতন, ঋণ বা অগ্রিম কোনোটাই নয়।`),
        detail: [], view: 'people',
      };
      return {
        speak: say(q, `${who.name}: ${en.join(', ')}.`, `${who.name}: ${bnp.join(', ')}।`),
        detail: loans.slice(0, 4).map((l) => `Loan ${fmtBDT(+l.amount || 0)} — ${fmtBDT(+l.remaining_amount || 0)} left, ${fmtBDT(+l.monthly_deduction || 0)}/month`),
        actions: openA(bn, 'employee salaries'), view: 'people',
      };
    }
    case 'attendance': case 'late': case 'online': {
      const ev = P.evaluate(D, who.id);
      if (!ev) return null;
      return {
        speak: say(q, `${who.name}: attendance ${ev.attendancePct}% over ${ev.days} days, late on ${ev.lateDays}${ev.absent ? `, absent ${ev.absent}` : ''}.`,
          `${who.name}: ${bNum(ev.days)} দিনে উপস্থিতি ${bNum(ev.attendancePct)} শতাংশ, দেরি ${bNum(ev.lateDays)} দিন${ev.absent ? `, অনুপস্থিত ${bNum(ev.absent)} দিন` : ''}।`),
        detail: (ev.concerns || []).slice(0, 4), actions: openA(bn, 'attendances'), view: 'people',
      };
    }
    case 'leave': {
      const bal = P.leaveBalance(D, who.id) || [];
      const left = bal.reduce((n, b) => n + b.remaining, 0);
      const mine = (P.leaves(D, s).pending || []).filter((l) => l.user_id === who.id);
      return {
        speak: say(q, `${who.name} has ${left} leave days left${mine.length ? `, and ${mine.length} request${mine.length === 1 ? '' : 's'} waiting for approval` : ''}.`,
          `${who.name}-এর ছুটি বাকি ${bNum(left)} দিন${mine.length ? `, আর ${bNum(mine.length)}টি আবেদন অনুমোদনের অপেক্ষায়` : ''}।`),
        detail: bal.map((b) => `${b.type}: ${b.used}/${b.entitlement} used, ${b.remaining} left`),
        actions: openA(bn, 'leave'), view: 'people',
      };
    }
    case 'performance': case 'employee': case 'ranking': case 'profile': {
      const ev = P.evaluate(D, who.id);
      if (!ev) return null;
      return {
        speak: say(q, `${who.name} — ${e.designation || ''}${e.department ? `, ${e.department}` : ''}. Score ${ev.score}, grade ${ev.grade}: attendance ${ev.attendancePct}%, ${ev.lateDays} late days, ${ev.tasksDone} tasks done.`,
          `${who.name} — ${e.designation || ''}${e.department ? `, ${e.department}` : ''}। স্কোর ${bNum(ev.score)}, গ্রেড ${ev.grade}: উপস্থিতি ${bNum(ev.attendancePct)} শতাংশ, দেরি ${bNum(ev.lateDays)} দিন, সম্পন্ন কাজ ${bNum(ev.tasksDone)}টি।`),
        detail: [].concat((ev.strengths || []).map((x) => `+ ${x}`), (ev.concerns || []).map((x) => `− ${x}`)).slice(0, 5),
        actions: openA(bn, 'user'), view: 'people',
      };
    }
    case 'project': {
      const pj = O.projects(D, s).all.filter((p) => p.manager_id === who.id || (p.team || []).includes(who.id));
      if (!pj.length) return {
        speak: say(q, `${who.name} is not on any project right now.`, `${who.name} এখন কোনো প্রকল্পে নেই।`),
        detail: [], view: 'ops',
      };
      return {
        speak: say(q, `${who.name} is on ${pj.length} project${pj.length === 1 ? '' : 's'}: ${pj.slice(0, 3).map((p) => p.project_name).join('; ')}.`,
          `${who.name} ${bNum(pj.length)}টি প্রকল্পে আছেন: ${pj.slice(0, 3).map((p) => p.project_name).join('; ')}।`),
        detail: pj.slice(0, 6).map((p) => `${p.project_name} — ${p.riskLabel}, ${p.progress}%`),
        actions: openA(bn, 'projects'), view: 'ops',
      };
    }
    case 'expense': {
      const co = s.company;
      const mine = (D.expenses || []).filter((x) => (co == null || +x.company_id === co) && (x.user_id === who.id || x.user_name === who.name));
      const tot = mine.reduce((n, x) => n + (+x.amount || 0), 0);
      return {
        speak: say(q, `${who.name} has claimed ${mine.length} expense${mine.length === 1 ? '' : 's'} totalling ${fmtBDT(tot)}.`,
          `${who.name} ${bNum(mine.length)}টি খরচ দাখিল করেছেন, মোট ${bMoney(tot)}।`),
        detail: mine.slice(0, 6).map((x) => `${x.expense_date} — ${x.title} ${fmtBDT(+x.amount || 0)} (${x.approval_status})`),
        actions: openA(bn, 'expenses'), view: 'finance',
      };
    }
    default:
      return null;      // not a person-shaped subject — let the company answer stand
  }
}

/* ---------- the composer ---------- */
/* Bangla marks a question with a tail word rather than word order:
   "বেতন পরিশোধ হয়েছে?" asks whether salary was paid; "বেতন দাও" orders it.
   Without this every question about paying became an order to pay. */
/* "tell me about Imran" is the boss asking, not the boss dictating. `tell`
   alone read it as an order to message somebody, so EON answered "What should
   the message say?" — and because that leaves a question open, the NEXT thing
   he said was swallowed as the body of a message he never wanted to send. */
/* "pay" is a verb and a noun, and the noun is far commoner in this business:
   net pay, pay bill, payslip, payroll, payment. "Tanvir's net pay" is a
   question about his salary — read as an order it opened a payment and asked
   "Which month?", which is EON offering to move money nobody asked it to move.
   So the verb is only the verb when no pay-noun is sitting on either side. */
const ORDER = /\b(assign|(?<!net )(?<!take[- ]home )pay(?!\s?(bill|slips?|rolls?|ments?|ables?|\s?cheque))\b|message|msg|send|write to|tell(?!\s+me\b)|notify|clear|settle|release)\b|(অ্যাসাইন|নিয়োগ|পরিশোধ করো|পে করো|ক্লিয়ার করো|(টাস্ক|কাজ)(?:(?!দেখা|তালিকা|হিসাব|রিপোর্ট)[^।]){0,10}(দাও|দিন|দে(?![ঀ-৿]))|(বেতন|টাকা|বিল|বকেয়া)(?:(?!হিসাব|তালিকা|রিপোর্ট|তথ্য|খবর|স্লিপ|শিট|অবস্থা|বিবরণ|পরিমাণ)[^।]){0,12}(দাও|দিন))/i;
/* English carries the interrogative at the front, not at the end: "did you pay
   Imran?", "should I pay him?", "who did we pay last month?" all contain the
   word pay and none of them is an instruction to pay anybody. A question mark
   is not the test — "can you pay Imran?" ends in one and is an order — so what
   is read is the opening word. Bangla needs no equivalent: its ORDER cues
   already require দাও / দিন / করো, which a question never carries. */
const ASKING = /^\s*(did|does|do|has|have|had|was|were|is|are|am|should|shall|who|whom|whose|what|which|when|where|why|how)\b/i;

async function understand(q, ctx) {
  const D = D0();
  if (!D) return null;
  const verb = verbOf(q);
  const isOrder = ORDER.test(q) && !ASKING.test(q);

  // a half command, or an answer to what EON asked a moment ago
  if ((NEEDS[verb] && isOrder) || (convo && convo.asked)) {
    const held = await step(D, q, verb, ctx);
    if (held) return held;
  }

  const bn = BN.test(q);
  const subj = subjectOf(q);
  const who = whoOrLast(D, q);
  const s = { company: (ctx && ctx.company != null) ? +ctx.company : (typeof window !== 'undefined' && window.EonErp && window.EonErp.company ? window.EonErp.company() : null) };
  const mon = namedMonth(q);
  if (mon) s.month = monthKeyFor(D, mon);

  // a question about one person
  if (who && subj) {
    const r = personAnswer(D, s, subj, who, q, bn);
    if (r) return r;
  }

  // "where is X" / "open X" — the navigator owns the ERP's geography
  if ((verb === 'where' || verb === 'open') && N()) {
    const key = subj ? (subj.ask || subj.id) : q;
    const hit = (N().find(key, 1) || [])[0];
    if (hit) {
      const url = N().url(hit.uri);
      const path = url.replace(location.origin, '');
      /* The screen's name is the ERP's own English label and stays as it is —
         but the sentence around it must be Bangla when the question was, or the
         boss is told where something lives in a language he did not ask in. */
      return {
        speak: bn
          ? (verb === 'open' ? `${hit.label || key} খুলছি — ${path}` : `${hit.label || key} আছে ${path} ঠিকানায়।`)
          : `${hit.label || key} — ${path}`,
        detail: [], actions: [{ label: say(q, 'Open', 'খুলুন'), kind: 'erp-open', href: url }],
        navigate: verb === 'open' ? url : null,
      };
    }
  }

  if (!subj) return null;

  // "cash and receivables" — two subjects, one breath
  const chain = who ? null : chainOf(q);
  if (chain) {
    const a = subjectAnswer(D, s, chain[0], q, bn);
    const b = subjectAnswer(D, s, chain[1], q, bn);
    if (a && b && a.speak !== b.speak) return {
      speak: `${a.speak} ${b.speak}`,
      detail: [].concat(a.detail || [], b.detail || []).slice(0, 8),
      actions: [].concat(a.actions || [], b.actions || []).slice(0, 3),
      view: a.view || b.view,
    };
    if (a) return a;
  }

  return subjectAnswer(D, s, subj, q, bn);
}

/* ---------- registration ---------- */
const CLAIM = (q) => {
  const s = String(q || '');
  if (!s.trim()) return false;
  if (convo && convo.asked) return true;          // EON asked something; this is the answer
  return !!subjectOf(s) || /\b(assign|pay|message)\b/i.test(s) || BN.test(s);
};

if (typeof window !== 'undefined') {
  window.EonUnderstand = { verbOf, subjectOf, subjectsIn, whoIn: (q) => whoIn(D0(), q), monthIn, amountIn, understand, conversation, forget, remember, subject_of_last, SUBJECTS };
  // below the specialists (99 act · 98 entity · 97 navigator) — it catches what
  // none of them matched, which is where the odd phrasings land; above the
  // phrase matchers (bangla 95) so "X কে টাস্ক দাও" is an instruction rather
  // than a question about tasks, and so a Bangla sentence is read subject-first
  (window.__eonDomainQueue = window.__eonDomainQueue || []).push({ id: 'understand', priority: 96, claims: CLAIM, answer: understand });
}

export default { understand, verbOf, subjectOf, subjectsIn };
