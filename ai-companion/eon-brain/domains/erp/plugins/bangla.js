/* ============================================================
   EON · বাংলা — the bilingual layer.

   Two jobs:
   1. window.EonBangla.brief(b)  — the morning brief, spoken in Bangla.
   2. an Ask EON domain that answers Bangla questions.

   The answers are built from the DATA, not translated from English
   prose: every intent reads the same layer functions the English
   answerer reads and writes the sentence in Bangla. Numbers are
   spoken the Bangladeshi way — ৳৫.২ কোটি, ৳৪২ লক্ষ, ৳৫২ হাজার,
   in Bangla digits — because that is how the boss hears money.

   Registered at priority 95 (above the English 'erp' domain, 90) and
   it only ever claims a question that actually contains Bangla text.
   ============================================================ */
import * as F from '../finance.js';
import * as P from '../people.js';
import * as C from '../crm.js';
import * as O from '../ops.js';
import * as X from '../decisions.js';
import { monthKey, MONTHS, iso } from '../dataset.js';

/* ---------- numbers, money, dates in Bangla ---------- */
const BN = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];
export const digits = (s) => String(s).replace(/\d/g, (d) => BN[+d]);
const trim = (x) => {
  const dec = x >= 100 ? 0 : x >= 10 ? 1 : 2;
  return String(Number(x.toFixed(dec))).replace(/\.0+$/, '');
};
export function money(n) {
  const v = Math.abs(Number(n) || 0), sign = Number(n) < 0 ? '−' : '';
  if (v >= 1e7) return `${sign}৳${digits(trim(v / 1e7))} কোটি`;
  if (v >= 1e5) return `${sign}৳${digits(trim(v / 1e5))} লক্ষ`;
  if (v >= 1e3) return `${sign}৳${digits(trim(v / 1e3))} হাজার`;
  return `${sign}৳${digits(Math.round(v))}`;
}
const num = (n) => digits(Math.round(Number(n) || 0));
const percent = (n) => `${digits(Math.abs(Math.round(Number(n) || 0)))} শতাংশ`;

const BN_MONTHS = ['জানুয়ারি', 'ফেব্রুয়ারি', 'মার্চ', 'এপ্রিল', 'মে', 'জুন', 'জুলাই', 'আগস্ট', 'সেপ্টেম্বর', 'অক্টোবর', 'নভেম্বর', 'ডিসেম্বর'];
const BN_DAYS = ['রবিবার', 'সোমবার', 'মঙ্গলবার', 'বুধবার', 'বৃহস্পতিবার', 'শুক্রবার', 'শনিবার'];
const bnDate = (d) => { const x = new Date(String(d).slice(0, 10) + 'T00:00:00'); return `${digits(x.getDate())} ${BN_MONTHS[x.getMonth()]}`; };
const bnDay = (d) => BN_DAYS[new Date(String(d).slice(0, 10) + 'T00:00:00').getDay()];
const bnMonthName = (mk) => `${BN_MONTHS[+String(mk).slice(5) - 1]} ${digits(String(mk).slice(0, 4))}`;

/* fixed vocabulary — statuses and departments the ERP speaks in English */
const WORD = {
  overdue: 'বকেয়া', pending: 'অপেক্ষমাণ', approved: 'অনুমোদিত', rejected: 'বাতিল', paid: 'পরিশোধিত',
  absent: 'অনুপস্থিত', present: 'উপস্থিত', late: 'দেরি', leave: 'ছুটি', holiday: 'ছুটির দিন', weekend: 'সাপ্তাহিক ছুটি',
  expense: 'খরচ', salary: 'বেতন', customer: 'ক্রেতা', supplier: 'সরবরাহকারী', task: 'কাজ', project: 'প্রকল্প', lead: 'লিড',
};
export const word = (en) => WORD[String(en || '').toLowerCase()] || en;

const D0 = () => (typeof window !== 'undefined' && window.EonErp && window.EonErp.dataset ? window.EonErp.dataset() : null);
const scope = (ctx) => ({ company: (ctx && ctx.company != null) ? +ctx.company : (typeof window !== 'undefined' && window.EonErp && window.EonErp.company ? window.EonErp.company() : null) });
const T = (D) => (D && D.meta && D.meta.today) || iso(new Date());
const greetNow = () => { const h = new Date().getHours(); return h < 12 ? 'শুভ সকাল' : h < 17 ? 'শুভ অপরাহ্ন' : 'শুভ সন্ধ্যা'; };

/* ============================================================
   0. THE DECISIONS, IN BANGLA.

   The layers write their headline and their recommendation in English,
   with the figures already baked into the sentence — "৳53 L payable is
   past due (58 items)". The Bangla brief then quoted those sentences
   verbatim, so the boss heard a paragraph of বাংলা with three English
   clauses embedded in it. That is the exact failure CLAUDE.md forbids,
   sitting in the first question anyone would ask in Bangla.

   These are not translations. Each one is written here from the same
   layer functions the English side reads, so the numbers are the same
   numbers and the sentence is a Bangla sentence. What stays English is
   what the ERP itself stores in English — a person's name, a party, a
   project, an account, a spending category — exactly as a Bangla
   speaker would say them out loud.

   A decision may also carry its own `title_bn` / `recommend_bn` (the
   compliance and health plug-ins do); that always wins. Anything with
   neither falls back to the English line rather than going silent.
   ============================================================ */
const BN_DECISION = {
  /* ---- finance ---- */
  'ap-overdue': (D, s) => {
    const ap = F.payables(D, s), cash = F.cashPosition(D, s);
    const emp = ap.overdue.filter((p) => p.party_type === 'employee');
    const sup = ap.overdue.filter((p) => p.party_type !== 'employee');
    const empDue = emp.reduce((n, p) => n + (+p.due || 0), 0);
    const old = sup.filter((p) => p.days_overdue > 30);
    return {
      title: `${money(ap.overdueTotal)} পরিশোধের সময় পার হয়ে গেছে — ${num(ap.overdue.length)}টি বিলে`,
      recommend: emp.length
        ? `আগে ${num(emp.length)} জনের বকেয়া বেতন ছাড়ুন (${money(empDue)}) — হাতের নগদ তার ${num(Math.round(cash.total / Math.max(1, empDue)))} গুণ — তারপর ৩০ দিনের বেশি পুরনো সরবরাহকারীদের মেটান।`
        : `৩০ দিনের বেশি পুরনো ${num(old.length)} জন সরবরাহকারীকে (${money(old.reduce((n, p) => n + (+p.due || 0), 0))}) এ সপ্তাহেই মেটান, বাকিতে কেনার সুবিধা ধরে রাখতে।`,
    };
  },
  'ar-overdue': (D, s) => {
    const ar = F.receivables(D, s);
    const top = ar.byParty.filter((p) => p.overdue > 0).slice(0, 3);
    const bucket = ar.buckets[2].amount + ar.buckets[3].amount + ar.buckets[4].amount;
    return {
      title: `${money(ar.overdueTotal)} পাওনার সময় পার হয়ে গেছে — ${num(ar.overdue.length)}টি বিলে`,
      recommend: `আজই ${top[0] ? top[0].party_name : 'সবচেয়ে বড় দেনাদার'}-কে ফোন করুন, আর ${top.length > 1 ? top.slice(1).map((p) => p.party_name).join(' ও ') : 'পরের দুজনকে'} লিখিত তাগাদা পাঠান — ৩০ দিনের বেশি পুরনো অংশটুকু আদায় হলেই ${money(bucket)} ফেরত আসে।`,
    };
  },
  'cash-squeeze': (D, s) => {
    const ap = F.payables(D, s), ar = F.receivables(D, s), cash = F.cashPosition(D, s);
    return {
      title: `আগামী ৭ দিনে পরিশোধযোগ্য ${money(ap.dueSoonTotal)} — হাতের নগদের ${percent(cash.total ? (ap.dueSoonTotal / cash.total) * 100 : 0)}`,
      recommend: `পরিশোধের ক্রম ঠিক করুন: আগে বেতন ও জরুরি সরবরাহকারী; কম জরুরি ${num(ap.dueSoon.filter((p) => p.priority === 'low').length)}টি এক সপ্তাহ পিছিয়ে দিন, আর এখনই পাওনা ${money(ar.dueSoonTotal)} আদায়ের তাগাদা দিন।`,
    };
  },
  runway: (D, s) => {
    const rw = F.runway(D, s);
    return {
      title: `এই খরচের হারে হাতের নগদ চলবে আর ${num(rw.monthsToZero)} মাস`,
      recommend: 'সবচেয়ে দ্রুত বাড়তে থাকা দুটি খরচের খাত কমান, আর পাওনা আদায় এগিয়ে আনুন।',
    };
  },
  'expenses-pending': (D, s) => {
    const p = F.pendingExpenses(D, s);
    return {
      title: `${num(p.count)}টি খরচ (${money(p.total)}) অনুমোদনের অপেক্ষায়`,
      recommend: 'নিয়মিত খরচগুলো এক দফায় অনুমোদন করুন; বাজেটের বাইরে যা কিছু, তা নিয়ে প্রশ্ন করুন।',
    };
  },
  'revenue-dip': (D, s) => {
    const tr = F.revenueTrend(D, s);
    return {
      title: `আয় গত মাসের চেয়ে ${percent(Math.abs(tr.vsPrev))} কম চলছে`,
      recommend: 'পাইপলাইন দেখুন — জেতা কোন কাজগুলোর বিল এখনো করা হয়নি?',
    };
  },
  'revenue-up': (D, s) => {
    const tr = F.revenueTrend(D, s);
    return {
      title: `আয় গত মাসের চেয়ে ${percent(tr.vsPrev)} বেশি চলছে`,
      recommend: 'মাসটা ভালো যাচ্ছে — ডেলিভারির সক্ষমতা যেন পিছিয়ে না পড়ে।',
    };
  },

  /* ---- people ---- */
  'payroll-pending': (D, s) => {
    const pr = P.payroll(D, s);
    const late = new Date(T(D) + 'T00:00:00').getDate() >= 5;
    return {
      title: `${bnMonthName(pr.month)} মাসের ${num(pr.pending.length)} জনের বেতন এখনো দেওয়া হয়নি — ${money(pr.pending.reduce((n, p) => n + (+p.net_salary || 0), 0))}`,
      recommend: late
        ? 'বেতন দেরি হয়ে গেছে — আজই ছাড়ুন; দেরিতে বেতন দেওয়াই ভালো কর্মী হারানোর সবচেয়ে দ্রুত পথ।'
        : '৫ তারিখের মধ্যে যাতে যায়, সেজন্য বেতনের রানটা এখনই অনুমোদন করুন।',
    };
  },
  'payroll-jump': (D, s) => {
    const pr = P.payroll(D, s);
    return {
      title: `মোট বেতন গত মাসের চেয়ে ${percent(pr.prevGross ? ((pr.gross - pr.prevGross) / pr.prevGross) * 100 : 0)} বেড়েছে`,
      recommend: 'নতুন যোগ দেওয়া কর্মী আর বেতন বৃদ্ধিগুলো অনুমোদিত ছিল কি না, নিশ্চিত করুন।',
    };
  },
  'absent-today': (D, s) => {
    const td = P.today(D, s);
    return {
      title: `আজ ছুটি ছাড়াই ${num(td.absent.length)} জন অনুপস্থিত${td.late.length ? `, ${num(td.late.length)} জন দেরিতে এসেছেন` : ''}`,
      recommend: td.absent.length >= 3
        ? 'দুপুরের আগে এইচআরকে অনুপস্থিতদের ফোন করতে বলুন — ৫ শতাংশের বেশি অঘোষিত অনুপস্থিতি আর বিচ্ছিন্ন ঘটনা নয়, এটা প্রবণতা।'
        : 'প্রত্যেকের কারণ এইচআর লিখে রাখুক।',
    };
  },
  'chronic-late': (D, s) => {
    const pt = P.patterns(D, s);
    return {
      title: `${num(pt.chronicLate.length)} জন কর্মী গত ${num(pt.days)} দিনের ৩০ শতাংশের বেশি দিন দেরিতে এসেছেন`,
      recommend: `শীর্ষ দুজনকে লিখিত সতর্কবার্তা দিন; মাসে ১২০ মিনিট পার হলেই কর্তন শুরু হয়, অর্থাৎ তাঁদের ${num(pt.chronicLate.filter((r) => r.lateMinutes >= 120).length)} জনের কর্তন এখনই হচ্ছে।`,
    };
  },
  'chronic-absent': (D, s) => {
    const pt = P.patterns(D, s);
    return {
      title: `${num(pt.chronicAbsent.length)} জন কর্মীর উপস্থিতি ৮৫ শতাংশের নিচে, অনুপস্থিতি ৩ দিনের বেশি`,
      recommend: 'লাইন ম্যানেজাররা এ সপ্তাহেই আলাদা করে কথা বলুন — শরীর, যাতায়াত না কি আগ্রহ কমে যাওয়া, সেটা বের করুন।',
    };
  },
  'leaves-pending': (D, s) => {
    const lv = P.leaves(D, s);
    const over = lv.pending.some((l) => (l.balance.find((b) => b.type === l.leave_type) || {}).remaining < l.days);
    return {
      title: `${num(lv.pending.length)}টি ছুটির আবেদন অনুমোদনের অপেক্ষায়`,
      recommend: over
        ? 'একটি আবেদন জমা ছুটির বেশি — সেটি বাতিল করুন বা বিনা বেতনে রূপান্তর করুন; বাকিগুলো অনুমোদন করুন।'
        : 'সবগুলোই জমা ছুটির মধ্যে — কোনো প্রকল্পের সময়সীমার সঙ্গে না বাধলে অনুমোদন করে দিন।',
    };
  },
  'requests-pending': (D, s) => {
    const ln = P.loans(D, s);
    return {
      title: `${num(ln.advancesPending.length)}টি অগ্রিম বেতন ও ${num(ln.requestsPending.length)}টি কর্মীর আবেদন সিদ্ধান্তের অপেক্ষায়`,
      recommend: 'উপস্থিতি ভালো এমন কর্মীর আধা মাসের বেতনের কম অগ্রিম অনুমোদন করুন; বাকিগুলো এইচআর হয়ে আসুক।',
    };
  },
  'new-joiners': (D, s) => {
    const hc = P.headcount(D, s);
    return {
      title: `গত ৬০ দিনে ${num(hc.newJoiners.length)} জন যোগ দিয়েছেন`,
      recommend: '৩০ দিনের মাথায় একবার বসুন — নতুনদের ছেড়ে যাওয়াটা প্রথম তিন মাসেই ঘটে।',
    };
  },

  /* ---- sales ---- */
  'stale-leads': (D, s) => {
    const st = C.stale(D, s);
    return {
      title: `${num(st.count)}টি খোলা লিড ঠান্ডা হয়ে গেছে, মূল্য ${money(st.value)}`,
      recommend: `হয় নতুন করে ভাগ করে দিন, নয় বন্ধ করুন: ${st.byAgent[0] ? st.byAgent[0].name : 'দায়িত্বপ্রাপ্ত ব্যক্তি'}-কে ৪৮ ঘণ্টা সময় দিন প্রতিটিতে যোগাযোগ করতে, বাকিগুলো হারানো হিসেবে সরান — পরিষ্কার পাইপলাইনই সৎ পূর্বাভাস দেয়।`,
    };
  },
  followups: (D, s) => {
    const f = C.followups(D, s);
    return {
      title: `আজ ${num(f.today.length)}টি ফলোআপ করার কথা, ${num(f.overdue.length)}টি আগেই বাদ পড়েছে`,
      recommend: 'বিক্রয় দল দুপুরের আগে বাদ পড়া ফলোআপগুলো সেরে ফেলুক; বার্তাগুলো EON লিখে দিতে পারে।',
    };
  },
  'deals-slipped': (D, s) => {
    const d = C.deals(D, s);
    return {
      title: `${num(d.slipped.length)}টি ডিল (${money(d.slippedValue)}) ক্লোজিং তারিখ পার করেও খোলা আছে`,
      recommend: 'সৎভাবে নতুন তারিখ দিন, নয়তো হারানো হিসেবে চিহ্নিত করুন; পূর্বাভাস তার তারিখের চেয়ে ভালো হয় না।',
    };
  },
  'deals-closing': (D, s) => {
    const d = C.deals(D, s);
    return {
      title: `আগামী ১৪ দিনে ${num(d.closingSoon.length)}টি ডিল ক্লোজ হওয়ার কথা, মূল্য ${money(d.closingSoonValue)}`,
      recommend: 'এ সপ্তাহে এই ফোনগুলো আপনি নিজে করুন।',
    };
  },
  'conversion-low': (D, s) => {
    const p = C.pipeline(D, s);
    const best = (p.bySource.filter((x) => x.rate != null).sort((a, b) => b.rate - a.rate)[0] || {}).source;
    return {
      title: `লিড রূপান্তরের হার ${percent(p.conversion)} (${num(p.won)}টি জেতা / ${num(p.lost)}টি হারা)`,
      recommend: `${best || 'সবচেয়ে ভালো উৎসে'} বাজেট দিন, আর ফানেলের শুরুতেই যাচাই আরও কড়া করুন।`,
    };
  },
  'agent-top': (D, s) => {
    const ag = C.agents(D, s);
    return {
      title: `${ag.top.name} সবার আগে — ${money(ag.top.wonValue)} জিতেছেন${ag.top.rate != null ? `, হার ${percent(ag.top.rate)}` : ''}`,
      recommend: 'এ সপ্তাহে সবার সামনে স্বীকৃতি দিন; নতুন এজেন্টকে তাঁর সঙ্গে জুড়ে দিন।',
    };
  },

  /* ---- operations ---- */
  'projects-risk': (D, s) => {
    const pj = O.projects(D, s), p = pj.atRisk[0];
    return {
      title: `${num(pj.atRisk.length)}টি প্রকল্প ঝুঁকিতে — সবচেয়ে খারাপ: ${p.project_name}`,
      recommend: `${p.manager}-এর কাছে আগামীকালের মধ্যে ${p.project_name}-এর জন্য পুনরুদ্ধার পরিকল্পনা চান: শেষ তারিখ নতুন করে ঠিক করা হোক, নয়তো ${p.tasksOverdue >= 3 ? 'একজন প্রকৌশলী যোগ করা হোক' : 'ক্লায়েন্টের সম্মতি নেওয়া হোক' } — আর নতুন কাজ যোগ করা বন্ধ।`,
    };
  },
  'tasks-overdue': (D, s) => {
    const tk = O.tasks(D, s);
    const high = tk.overdue.filter((k) => k.priority === 'high').length;
    return {
      title: `${num(tk.overdue.length)}টি কাজের সময়সীমা পার (${num(high)}টি জরুরি)`,
      recommend: tk.overloaded.length
        ? `${tk.overloaded[0].name}-এর উপর চাপ বেশি (${num(tk.overloaded[0].open)}টি খোলা, ${num(tk.overloaded[0].overdue)}টির সময় পার) — দুটি কাজ ${tk.idle[0] ? tk.idle[0].name + '-কে দিন, তাঁর হাতে কিছুই নেই' : 'যাঁর সময় আছে তাঁকে দিন'}।`
        : 'আজই প্রত্যেক দায়িত্বপ্রাপ্তের কাছ থেকে নতুন তারিখ নিন; যেগুলোর মালিক নেই, দুপুরের মধ্যে মালিক ঠিক করুন।',
    };
  },
  overloaded: (D, s) => {
    const tk = O.tasks(D, s);
    return {
      title: `${num(tk.overloaded.length)} জনের হাতে ৬টি বা তার বেশি খোলা কাজ`,
      recommend: 'সময়সীমা পার হওয়ার আগেই কাজ ভাগ করে দিন।',
    };
  },
  'idle-capacity': (D, s) => {
    const tk = O.tasks(D, s);
    return {
      title: `${num(tk.idle.length)} জন দক্ষ কর্মীর হাতে কোনো কাজ নেই`,
      recommend: 'হয় কাজের বোর্ড হালনাগাদ নেই, নয়তো হাতে সময় আছে — দুটোই জানা দরকার।',
    };
  },
  'velocity-drop': (D, s) => {
    const tk = O.tasks(D, s);
    return {
      title: `ডেলিভারি ধীর হয়েছে: এ সপ্তাহে ${num(tk.velocity)}টি কাজ শেষ, গত সপ্তাহে ছিল ${num(tk.velocityPrev)}টি`,
      recommend: 'কোথাও আটকে আছে কি না দেখুন — একটা আটকে থাকা নির্ভরতাই সাধারণত এর কারণ।',
    };
  },
  'todos-overdue': (D, s) => {
    const td = O.todos(D, s);
    const comp = td.overdue.some((k) => /VAT|licence|tax|insurance/i.test(k.title));
    return {
      title: `অফিসের ${num(td.overdue.length)}টি কাজের সময় পার${comp ? ' — এর মধ্যে একটি কমপ্লায়েন্সের' : ''}`,
      recommend: 'আগে কমপ্লায়েন্স (লাইসেন্স, ভ্যাট, বিমা); বাকিগুলো বৃহস্পতিবারের মধ্যে।',
    };
  },
  'projects-due': (D, s) => {
    const pj = O.projects(D, s);
    return {
      title: `${num(pj.dueSoon.length)}টি প্রকল্প ১৪ দিনের মধ্যে শেষ হওয়ার কথা`,
      recommend: 'প্রতিটি ক্লায়েন্টের সঙ্গে হস্তান্তরের তারিখ এখনই নিশ্চিত করুন, শেষ দিনে নয়।',
    };
  },
};

/* the id carries the row it is about: bank-low-<id>, budget-over-<category>,
   anomaly-<company>-<category>. Those are looked up rather than recomputed. */
function bnById(D, s, d) {
  const id = String(d.id || '');
  if (BN_DECISION[id]) return BN_DECISION[id](D, s);
  if (id.startsWith('bank-low-')) {
    const b = F.cashPosition(D, s).low.find((x) => 'bank-low-' + x.id === id);
    if (!b) return null;
    return { title: `${b.name} (${b.company}) হিসাবে ${b.balance < 0 ? 'ঘাটতি' : 'টাকা কম'}: ${money(b.balance)}`,
      recommend: `পরের নির্ধারিত পরিশোধের আগে ${b.name}-এ কিছু টাকা সরিয়ে রাখুন।` };
  }
  if (id.startsWith('budget-over-')) {
    const r = F.expensesVsBudget(D, s).over.find((x) => 'budget-over-' + x.category === id);
    if (!r) return null;
    return { title: `${r.category} খাতে এ মাসে বাজেটের ${percent(r.pct)} খরচ হয়েছে (${money(r.spent)} / ${money(r.budget)})`,
      recommend: r.pending ? `${r.category} খাতের অপেক্ষমাণ অনুমোদনগুলো আটকে রাখুন, যতক্ষণ না দায়িত্বপ্রাপ্ত ব্যক্তি বাড়তি খরচের কারণ দেখান।`
        : `মাসের বাকি দিনগুলোতে ${r.category} খাতের ঐচ্ছিক খরচ বন্ধ রাখুন।` };
  }
  if (id.startsWith('budget-warn-')) {
    const r = F.expensesVsBudget(D, s).warn.find((x) => 'budget-warn-' + x.category === id);
    if (!r) return null;
    return { title: `${r.category} খাতে বাজেটের ${percent(r.pct)} খরচ হয়ে গেছে, মাস এখনো বাকি`,
      recommend: 'নজরে রাখুন — এখনই কিছু করার নেই।' };
  }
  if (id.startsWith('anomaly-')) {
    const a = F.expenseAnomalies(D, s).find((x) => `anomaly-${x.company_id}-${x.category}` === id);
    if (!a) return null;
    return { title: `${a.company}-এ ${a.category} খাতের খরচ স্বাভাবিক মাসের ${digits(a.ratio)} গুণ চলছে`,
      recommend: `${a.company}-এর প্রশাসনকে জিজ্ঞেস করুন ${a.category} খাতে কী বদলাল — একবারের ঘটনা, না কি নতুন হার?` };
  }
  return null;
}

/** the Bangla headline for a decision, and the Bangla recommendation */
export function decisionBn(d) {
  if (!d) return { title: '', recommend: '' };
  const D = D0(); const s = D ? scope({}) : { company: null };
  let x = null;
  if (d.title_bn || d.recommend_bn) x = { title: d.title_bn || null, recommend: d.recommend_bn || null };
  if (!x || !x.title || !x.recommend) {
    let derived = null;
    try { derived = D ? bnById(D, s, d) : null; } catch (e) { derived = null; }
    x = { title: (x && x.title) || (derived && derived.title) || d.title,
          recommend: (x && x.recommend) || (derived && derived.recommend) || d.recommend };
  }
  return x;
}
const dTitle = (d) => decisionBn(d).title;
const dRec = (d) => decisionBn(d).recommend;

/* ============================================================
   1. the brief, in Bangla — same numbers as decisions.js brief()
   ============================================================ */
export function brief(b) {
  if (!b || !b.kpis) return { speak: '', lines: [] };
  const k = b.kpis, prefs = (typeof window !== 'undefined' && window.EON_PREFS) || {};
  const who = prefs.name || X.firstName((b.boss && b.boss.name) || '') || 'স্যার';
  const lines = [];

  lines.push(`${greetNow()}, ${who}। আজ ${bnDay(b.date)}, ${bnDate(b.date)}। হাতে নগদ ${money(k.cash.value)}; এ মাসে আয় ${money(k.revenue.value)}, গত মাসের তুলনায় ${percent(k.revenue.trend)} ${k.revenue.trend >= 0 ? 'বেশি' : 'কম'}। গত মাস শেষ হয়েছে ${money(Math.abs(k.profit.value))} নিট ${k.profit.value >= 0 ? 'মুনাফায়' : 'লোকসানে'}।`);

  const arOver = (k.receivables.value && k.receivables.alert) || k.payables.alert;
  if (arOver) {
    const D = D0(); const s = D ? scope({}) : {};
    const ar = D ? F.receivables(D, s).overdueTotal : 0, ap = D ? F.payables(D, s).overdueTotal : 0;
    lines.push(`বকেয়া: আদায় করতে হবে ${money(ar)}, পরিশোধ করতে হবে ${money(ap)}।`);
  }
  if (k.attendance && k.attendance.value != null && !/weekend|holiday/i.test(String(k.attendance.sub || ''))) {
    lines.push(`আজ ${percent(k.attendance.value)} উপস্থিত — ${String(k.attendance.sub || '')}`
      .replace(/(\d+) absent/, (m, n) => `${digits(n)} জন অনুপস্থিত`)
      .replace(/(\d+) late/, (m, n) => `${digits(n)} জন দেরিতে`)
      .replace(' · ', ', ') + '।');
  }
  const crit = (b.critical || []).slice(0, 3);
  if (crit.length) lines.push(`আজ ${num(b.critical.length)}টি বিষয়ে আপনার নজর দরকার: ${crit.map(dTitle).join('; ')}।`);
  else lines.push('জরুরি কিছু নেই। দিনটি এগিয়ে যাওয়ার জন্য ভালো।');
  if (b.approvals && b.approvals.count) lines.push(`${num(b.approvals.count)}টি অনুমোদন আপনার অপেক্ষায়${b.approvals.amount ? `, মোট ${money(b.approvals.amount)}` : ''}।`);
  if (b.top) lines.push(`একটি কাজ যদি করেন: ${dRec(b.top)}`);

  return { speak: lines.join(' '), lines, greeting: greetNow() };
}

/* ============================================================
   2. Bangla questions → Bangla answers
   ============================================================ */
const BANGLA = /[ঀ-৿]/;

const INTENTS = [
  { id: 'brief', re: /ব্রিফ|সারসংক্ষেপ|আজকের অবস্থা|অবস্থা (কি|কী)|কেমন চলছে|খবর (কি|কী)/, a(D, s) {
    const b = X.brief(D, s); const bb = brief(b);
    return { speak: bb.speak, detail: bb.lines.slice(1), view: 'brief' };
  } },
  { id: 'decisions', re: /(কি|কী) করা উচিত|জরুরি|গুরুত্বপূর্ণ|সিদ্ধান্ত|নজর দেওয়া|মনোযোগ|সমস্যা (কি|কী)/, a(D, s) {
    const list = X.all(D, s), crit = list.filter((d) => d.severity >= 4);
    if (!list.length) return { speak: 'এই মুহূর্তে জরুরি কিছু নেই।', detail: [], view: 'decisions' };
    return {
      speak: `${num(list.length)}টি বিষয় সামনে আছে, তার মধ্যে ${num(crit.length)}টি জরুরি। সবচেয়ে আগে: ${dTitle(list[0])}। পরামর্শ — ${dRec(list[0])}`,
      detail: list.slice(0, 5).map((d, i) => `${digits(i + 1)}. ${dTitle(d)}${d.amount ? ` — ${money(d.amount)}` : ''}`),
      view: 'decisions',
    };
  } },
  { id: 'cash', re: /ক্যাশ|নগদ|ব্যাংক|হাতে (কত|কী) (টাকা|আছে)|টাকা আছে|তহবিল/, a(D, s) {
    const c = F.cashPosition(D, s);
    return {
      speak: `এই মুহূর্তে মোট নগদ ও ব্যাংক ${money(c.total)}, ${num(c.banks.length)}টি হিসাবে।`,
      detail: c.banks.slice(0, 8).map((b) => `${b.name}: ${money(b.balance)}`),
      view: 'finance',
    };
  } },
  { id: 'payroll', re: /বেতন|স্যালারি|পে-?রোল|মজুরি/, a(D, s) {
    const pr = P.payroll(D, s);
    return {
      speak: `${bnMonthName(pr.month)} মাসের বেতন: মোট ${money(pr.gross)}, কর্তনের পর নিট ${money(pr.net)}${pr.pending.length ? `; ${num(pr.pending.length)} জনের বেতন এখনও বাকি` : '; সবার বেতন পরিশোধ হয়েছে'}।`,
      detail: pr.pending.slice(0, 8).map((p) => `${WORD.pending}: ${p.name} — ${money(p.net)}`),
      view: 'people',
    };
  } },
  { id: 'receivables', re: /পাওনা|আদায়|টাকা (দেবে|দিবে|পাব)|বাকি টাকা|গ্রাহক.*(বাকি|পাওনা)|রিসিভেবল|কারা টাকা দেবে/, a(D, s) {
    const r = F.receivables(D, s), top = r.byParty.filter((x) => x.overdue > 0)[0];
    return {
      speak: `মোট পাওনা ${money(r.total)}, তার মধ্যে ${money(r.overdueTotal)} সময় পার হয়ে গেছে। সবচেয়ে বড় বকেয়া ${top ? `${top.party_name} — ${money(top.overdue)}, ${num(top.oldest)} দিন পার` : 'নেই'}।`,
      detail: r.byParty.filter((x) => x.overdue > 0).slice(0, 6).map((x, i) => `${digits(i + 1)}. ${x.party_name} — ${money(x.overdue)} · ${num(x.oldest)} দিন ${WORD.overdue}`),
      view: 'finance',
    };
  } },
  { id: 'payables', re: /দেনা|কাকে (টাকা )?দিতে|সরবরাহকারী.*(পাওনা|বাকি|দিতে)|পেয়েবল|বিল পরিশোধ|পাওনাদার/, a(D, s) {
    const p = F.payables(D, s);
    return {
      speak: `মোট পরিশোধযোগ্য ${money(p.total)}, তার মধ্যে ${money(p.overdueTotal)} সময়মতো দেওয়া হয়নি।`,
      detail: p.byParty.filter((x) => x.overdue > 0).slice(0, 6).map((x, i) => `${digits(i + 1)}. ${x.party_name} — ${money(x.overdue)} · ${num(x.oldest)} দিন ${WORD.overdue}`),
      view: 'finance',
    };
  } },
  { id: 'profit', re: /লাভ|মুনাফা|লোকসান|আয়(-| )?ব্যয়|আয় কত|বিক্রি কত|টার্নওভার/, a(D, s) {
    const t = T(D), mk = monthKey(t);
    const pl = F.profitAndLoss(D, Object.assign({ from: mk + '-01', to: t }, s));
    return {
      speak: `${bnMonthName(mk)} মাসে এ পর্যন্ত আয় ${money(pl.totalIncome)}, খরচ ${money(pl.totalOpex + (pl.totalCogs || 0))}, নিট ${pl.netProfit >= 0 ? 'মুনাফা' : 'লোকসান'} ${money(Math.abs(pl.netProfit))} — মার্জিন ${percent(pl.margin)}।`,
      detail: [`আয়: ${money(pl.totalIncome)}`, `পরিচালন খরচ: ${money(pl.totalOpex)}`, `নিট: ${money(pl.netProfit)}`],
      view: 'finance',
    };
  } },
  { id: 'attendance', re: /উপস্থিত|অনুপস্থিত|হাজিরা|কে আসেনি|কে আসছে|কতজন এসেছে/, a(D, s) {
    const td = P.today(D, s);
    /* An answer must name what it is about. "অফিস বন্ধ" on its own is true but
       reads as a non-sequitur to "কে কে আসেনি" — the English side already says
       "no attendance expected", so the Bangla says whose হাজিরা is not expected. */
    if (td.weekend || td.holiday) return { speak: `আজ ${td.holiday ? td.holiday : WORD.weekend} — অফিস বন্ধ, তাই কারও হাজিরা প্রত্যাশিত নয়।`, detail: [], view: 'people' };
    return {
      speak: `আজ ${num(td.total)} জনের মধ্যে ${num(td.present.length)} জন উপস্থিত, ${num(td.absent.length)} জন অনুপস্থিত এবং ${num(td.late.length)} জন দেরিতে এসেছেন।`,
      detail: td.absent.slice(0, 8).map((e) => `${WORD.absent}: ${e.name} — ${e.department || ''}`),
      view: 'people',
    };
  } },
  { id: 'late', re: /দেরি|লেট|সময়মতো আসে না|বিলম্ব/, a(D, s) {
    const pt = P.patterns(D, s), td = P.today(D, s);
    return {
      speak: `আজ ${num(td.late.length)} জন দেরিতে এসেছেন। গত ${num(pt.days)} দিনে নিয়মিত দেরি করছেন ${num(pt.chronicLate.length)} জন।`,
      detail: pt.chronicLate.slice(0, 6).map((e) => `${e.name} — ${num(e.lateDays)} দিন দেরি, মোট ${num(e.lateMinutes)} মিনিট`),
      view: 'people',
    };
  } },
  { id: 'headcount', re: /কতজন (কর্মী|লোক|স্টাফ|আছে)|জনবল|কর্মী সংখ্যা|টিম কত বড়|বিভাগ/, a(D, s) {
    const h = P.headcount(D, s);
    return {
      speak: `মোট ${num(h.total)} জন কর্মী, মাসিক বেতন বাবদ ${money(h.monthlyPayroll)}।`,
      detail: (h.byDept || []).slice(0, 8).map((d) => `${d.department}: ${num(d.count)} জন · ${money(d.payroll)}`),
      view: 'people',
    };
  } },
  { id: 'pipeline', re: /লিড|পাইপলাইন|সম্ভাব্য (ক্রেতা|গ্রাহক)|বিক্রয়.*(সম্ভাবনা|পাইপ)|ডিল/, a(D, s) {
    const p = C.pipeline(D, s);
    return {
      speak: `পাইপলাইনে ${num(p.open.length)}টি সক্রিয় লিড, সম্ভাব্য মূল্য ${money(p.openValue)}। রূপান্তরের হার ${p.conversion == null ? 'এখনও হিসাব হয়নি' : percent(p.conversion)}।`,
      detail: (p.stages || []).filter((x) => x.count).map((x) => `${x.label}: ${num(x.count)}টি — ${money(x.value)}`),
      view: 'crm',
    };
  } },
  { id: 'tasks', re: /কাজ (বাকি|জমে|দেরি)|টাস্ক|অসমাপ্ত কাজ|কাজের অবস্থা/, a(D, s) {
    const t = O.tasks(D, s);
    return {
      speak: `${num(t.overdue.length)}টি কাজ সময়সীমা পার করেছে, খোলা আছে ${num(t.open.length)}টি।`,
      detail: t.overdue.slice(0, 6).map((x) => `${x.title} — ${(x.assignees || []).join(', ') || 'কেউ নির্ধারিত নেই'} · ${num(x.days_overdue)} দিন দেরি`),
      view: 'ops',
    };
  } },
  { id: 'projects', re: /প্রজেক্ট|প্রকল্প|ডেলিভারি|ঝুঁকি/, a(D, s) {
    const p = O.projects(D, s);
    return {
      speak: `${num(p.active.length)}টি প্রকল্প চলমান, তার মধ্যে ${num(p.atRisk.length)}টি ঝুঁকিতে আছে।`,
      /* the column is project_name, not name — this line read "undefined — সময় পার" */
      detail: p.atRisk.slice(0, 6).map((x) => `${x.project_name || x.name} — ${x.late ? 'সময় পার' : x.overBudget ? 'বাজেট ছাড়িয়েছে' : 'পিছিয়ে আছে'} · অগ্রগতি ${percent(x.progress || 0)}`),
      view: 'ops',
    };
  } },
  { id: 'approvals', re: /অনুমোদন|সই|অনুমতি|পেন্ডিং/, a(D, s) {
    const ap = X.approvals(D, s);
    if (!ap.count) return { speak: 'আপনার অনুমোদনের অপেক্ষায় কিছু নেই।', detail: [], view: 'approvals' };
    return {
      speak: `${num(ap.count)}টি অনুমোদন আপনার অপেক্ষায়, মোট ${money(ap.amount)}।`,
      detail: ap.items.slice(0, 6).map((x) => `${x.title} — ${x.who}${x.amount ? ` · ${money(x.amount)}` : ''}`),
      view: 'approvals',
    };
  } },
  { id: 'employee', re: /কেমন (করছে|করছেন|পারফর্ম)|মূল্যায়ন|রিপোর্ট কার্ড/, a(D, s, q) {
    const latin = (q.match(/[A-Za-z][A-Za-z. ]{2,}/) || [])[0];
    const emp = latin ? P.findEmployee(D, latin.trim()) : null;
    if (!emp) return { speak: 'কার কথা বলছেন? নামটি ইংরেজিতে লিখুন — যেমন “Afiqur Rahman কেমন করছে”।', detail: [], view: 'people' };
    const ev = P.evaluate(D, emp.id);
    return {
      speak: `${emp.name} — ${digits(ev.score)} নম্বর, গ্রেড ${ev.grade}। উপস্থিতি ${percent(ev.attendancePct)}, দেরি ${num(ev.lateDays)} দিন, কাজ সম্পন্ন ${num(ev.tasksDone)}টি।`,
      detail: [].concat((ev.strengths || []).map((x) => `+ ${x}`), (ev.concerns || []).map((x) => `− ${x}`)).slice(0, 5),
      view: 'people',
    };
  } },
];

function answerBangla(q, ctx) {
  if (!BANGLA.test(String(q || ''))) return null;      // never claim an English question
  const D = D0(); if (!D) return null;
  const s = scope(ctx);
  for (const it of INTENTS) {
    if (!it.re.test(q)) continue;
    try { const r = it.a(D, s, q); if (r) return r; } catch (e) { console.warn('[EON বাংলা] intent failed:', it.id, e); }
  }
  // Bangla, but no intent of ours matched: yield, so the understander (and the
  // action layer) get their turn. Swallowing it here is what made "টাস্ক দাও"
  // come back as a task count instead of assigning anything.
  return null;
}

/* ---------- example questions on the Ask screen ---------- */
const EXAMPLES = ['আজকের ব্রিফ দাও', 'ক্যাশ কত আছে?', 'কে আমাদের টাকা দেবে?', 'কাকে টাকা দিতে হবে?', 'আজ কে অনুপস্থিত?', 'এ মাসে লাভ কত?', 'বেতন পরিশোধ হয়েছে?', 'কী করা উচিত আজ?'];

function panel() {
  const A = typeof window !== 'undefined' && window.EonApp;
  if (!A || !A.registerPanel) return;
  A.registerPanel('ask', {
    id: 'bangla-examples',
    title: 'বাংলায় জিজ্ঞাসা করুন',
    order: 20,
    render: () => `<div class="hint" style="margin-bottom:8px">EON বাংলায় প্রশ্ন বুঝে বাংলাতেই উত্তর দেয় — টাকার অঙ্ক লক্ষ ও কোটিতে।</div>
      <div class="chips">${EXAMPLES.map((q) => `<span class="chip" data-q="${A.esc(q)}">${A.esc(q)}</span>`).join('')}</div>`,
  });
}

/* ---------- registration ---------- */
if (typeof window !== 'undefined') {
  window.EonBangla = Object.assign(window.EonBangla || {}, { brief, money, digits, word, answer: answerBangla, EXAMPLES });
  (window.__eonDomainQueue = window.__eonDomainQueue || []).push({
    id: 'bangla',
    priority: 95,
    claims: (q) => BANGLA.test(String(q || '')),
    answer: (q, ctx) => answerBangla(q, ctx),
  });
  if (window.EonApp) panel(); else window.addEventListener('eon:app-ready', panel);
}

export default { brief, money, digits, word, answer: answerBangla };
