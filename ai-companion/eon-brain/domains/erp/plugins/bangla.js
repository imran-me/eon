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
  if (crit.length) lines.push(`আজ ${num(b.critical.length)}টি বিষয়ে আপনার নজর দরকার: ${crit.map((d) => d.title).join('; ')}।`);
  else lines.push('জরুরি কিছু নেই। দিনটি এগিয়ে যাওয়ার জন্য ভালো।');
  if (b.approvals && b.approvals.count) lines.push(`${num(b.approvals.count)}টি অনুমোদন আপনার অপেক্ষায়${b.approvals.amount ? `, মোট ${money(b.approvals.amount)}` : ''}।`);
  if (b.top) lines.push(`একটি কাজ যদি করেন: ${b.top.recommend}`);

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
      speak: `${num(list.length)}টি বিষয় সামনে আছে, তার মধ্যে ${num(crit.length)}টি জরুরি। সবচেয়ে আগে: ${list[0].title}। পরামর্শ — ${list[0].recommend}`,
      detail: list.slice(0, 5).map((d, i) => `${digits(i + 1)}. ${d.title}${d.amount ? ` — ${money(d.amount)}` : ''}`),
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
    if (td.weekend || td.holiday) return { speak: `আজ ${td.holiday ? td.holiday : WORD.weekend} — অফিস বন্ধ।`, detail: [], view: 'people' };
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
      detail: p.atRisk.slice(0, 6).map((x) => `${x.name} — ${x.late ? 'সময় পার' : x.overBudget ? 'বাজেট ছাড়িয়েছে' : 'পিছিয়ে আছে'} · অগ্রগতি ${percent(x.progress || 0)}`),
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
  // Bangla, but nothing matched — answer in Bangla rather than failing in English
  return {
    speak: 'এটা এখনও শিখিনি। জিজ্ঞাসা করতে পারেন: ব্রিফ, ক্যাশ কত, কে টাকা পাবে, কাকে দিতে হবে, আজ কে অনুপস্থিত, বেতন, লাভ কত, পাইপলাইন, কাজ বাকি, অনুমোদন।',
    detail: [],
  };
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
