/* ============================================================
   EON · statements and ledgers — open, print and download them.

   "open RABBI's statement", "print the company ledger for October",
   "ইসিএন রাব্বির স্টেটমেন্ট পিডিএফ দাও", "vendor ledger dekhao".

   The ERP already knows how to draw every one of these; EON only has
   to arrive at the right address with the right filter on it. The
   addresses are the ERP's own (PartyStatementController):

     party-statement?party_id=ID          → redirects to the agent / client /
                                            vendor ledger with that party open
     party-statement/export/pdf?party_id  → the statement as PDF (from, to)
     party-statement/export/excel?party_id→ the same as .xlsx
     party-statement/{agents|clients|vendors}   the three ledgers
     party-statement/company[?print=1]    → the Company Ledger, and its sheet
     party-statement/parties/print        → every party with its balance
     report/{cash-flow,general-ledger}[/print|/export]

   A single party has no print sheet of its own — the ERP prints a
   statement from its PDF — so "print X's statement" opens the PDF and
   says so rather than pretending there was a print button.

   Only ORDERS are claimed. "how much does RABBI owe" is a question about
   a figure and belongs to the answerers below this one.
   ============================================================ */
import { url } from './navigator.js';
import { latinSkeleton, banglaSkeleton, CASE_ENDING } from './act.js';

const D0 = () => (typeof window !== 'undefined' && window.EonErp && window.EonErp.dataset ? window.EonErp.dataset() : null);
const BN = /[ঀ-৿]/;
const NB = '(?![ঀ-৿])';   // \b never fires after a Bangla letter

/* ---------- what is being asked for ---------- */
const VERB = new RegExp(`\\b(open|show|see|view|display|bring up|pull up|go to|take me|print|pdf|excel|xlsx|spreadsheet|download|export|save|dekhao|dekhan|kholo|khulun|print koro|download koro)\\b|(দেখাও|দেখান|দেখি|খোলো|খুলুন|খোল|প্রিন্ট|ডাউনলোড|পিডিএফ|এক্সেল|নামাও|সেভ)${NB}`, 'i');
const LEDGERISH = new RegExp(`\\b(statement|ledger|ledgers|khatiyan|khotian|hisab)\\b|(স্টেটমেন্ট|লেজার|খতিয়ান|খতিয়ান|হিসাব|হিসেব)`, 'i');
const COMPANY_LEDGER = new RegExp(`\\bcompany(?:'s)?\\s+ledger\\b|(কোম্পানি|কোম্পানির)\\s*(লেজার|খতিয়ান|খতিয়ান)`, 'i');
const KIND = [
  { key: 'agents', re: new RegExp(`\\bagents?\\b|(এজেন্ট)`, 'i'), en: 'Agent Ledger', bn: 'এজেন্ট লেজার' },
  { key: 'vendors', re: new RegExp(`\\b(vendors?|suppliers?)\\b|(ভেন্ডর|সাপ্লায়ার|সাপ্লায়ার)`, 'i'), en: 'Vendor Ledger', bn: 'ভেন্ডর লেজার' },
  { key: 'clients', re: new RegExp(`\\b(clients?|customers?)\\b|(ক্লায়েন্ট|ক্লায়েন্ট|কাস্টমার)`, 'i'), en: 'Client Ledger', bn: 'ক্লায়েন্ট লেজার' },
];
const ALL_PARTIES = new RegExp(`\\b(all|every)\\s+(parties|party balances)\\b|(সব|সকল)\\s*(পার্টি)`, 'i');
const CASH_FLOW = new RegExp(`\\bcash\\s*-?\\s*flow\\b|(ক্যাশ\\s*ফ্লো|নগদ\\s*প্রবাহ)`, 'i');
const GENERAL_LEDGER = new RegExp(`\\b(general ledger|gl)\\b|(জেনারেল\\s*লেজার|সাধারণ\\s*খতিয়ান)`, 'i');

const FORMAT_PDF = new RegExp(`\\b(pdf)\\b|(পিডিএফ)`, 'i');
const FORMAT_XLS = new RegExp(`\\b(excel|xlsx|spreadsheet|sheet)\\b|(এক্সেল|শিটে)`, 'i');
const FORMAT_PRINT = new RegExp(`\\b(print|print koro)\\b|(প্রিন্ট)`, 'i');
const FORMAT_DOWNLOAD = new RegExp(`\\b(download|export|save)\\b|(ডাউনলোড|নামাও|সেভ)`, 'i');

function formatOf(s) {
  if (FORMAT_XLS.test(s)) return 'excel';
  if (FORMAT_PDF.test(s)) return 'pdf';
  if (FORMAT_PRINT.test(s)) return 'print';
  if (FORMAT_DOWNLOAD.test(s)) return 'pdf';
  return 'open';
}

/* ---------- the period ----------
   A month must be a whole word: "marketing" is not March. Bare "may" and
   "march" are left alone — in an order they are verbs more often than dates. */
const EN_MONTHS = ['january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december'];
const BN_MONTHS = [['জানুয়ারি', 'জানুয়ারি', 'জানুয়ারী'], ['ফেব্রুয়ারি', 'ফেব্রুয়ারি'], ['মার্চ'], ['এপ্রিল'], ['মে'], ['জুন'], ['জুলাই'], ['আগস্ট', 'আগষ্ট'], ['সেপ্টেম্বর'], ['অক্টোবর'], ['নভেম্বর'], ['ডিসেম্বর']];
const pad = (n) => String(n).padStart(2, '0');
const ymd = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
function monthRange(y, m) { return { from: `${y}-${pad(m)}-01`, to: ymd(new Date(y, m, 0)) }; }

export function periodIn(s, today = new Date()) {
  const low = String(s || '').toLowerCase();
  const iso = low.match(/(\d{4}-\d{2}-\d{2})\D+(\d{4}-\d{2}-\d{2})/);
  if (iso) return { from: iso[1], to: iso[2], label: `${iso[1]} – ${iso[2]}` };
  const y = today.getFullYear(), m = today.getMonth() + 1;
  if (/\bthis month\b|এই মাস/.test(low)) return { ...monthRange(y, m), label: 'this month', label_bn: 'এই মাস' };
  if (/\blast month\b|গত মাস/.test(low)) { const d = new Date(y, m - 2, 1); return { ...monthRange(d.getFullYear(), d.getMonth() + 1), label: 'last month', label_bn: 'গত মাস' }; }
  if (/\bthis year\b|এ বছর|এই বছর/.test(low)) return { from: `${y}-01-01`, to: ymd(today), label: 'this year', label_bn: 'এ বছর' };
  let mi = -1;
  EN_MONTHS.forEach((name, i) => {
    if (name === 'may' || name === 'march') { if (new RegExp(`\\b(in|for|of)\\s+${name}\\b|\\b${name}\\s+\\d{4}\\b`).test(low)) mi = i; return; }
    if (new RegExp(`\\b(${name}|${name.slice(0, 3)})\\b`).test(low)) mi = i;
  });
  if (mi < 0) BN_MONTHS.forEach((names, i) => { if (names.some((n) => new RegExp(`(^|[^ঀ-৿])${n}${NB}`).test(s))) mi = i; });
  if (mi < 0) return null;
  const yr = (low.match(/\b(20\d\d)\b/) || [])[1];
  const year = yr ? +yr : (mi + 1 > m ? y - 1 : y);   // "October" said in March means last October
  return { ...monthRange(year, mi + 1), label: `${EN_MONTHS[mi][0].toUpperCase()}${EN_MONTHS[mi].slice(1)} ${year}`, label_bn: `${BN_MONTHS[mi][0]} ${year}` };
}

/* ---------- which party ----------
   Every party that has ever moved money is in party_transactions, under the
   ERP's users.id — the same id the statement URL takes. Names are matched
   word by word on a consonant skeleton so that রাব্বি reaches RABBI; two
   parties that match equally are asked about, never guessed between. */
const STOP = /^(open|show|see|view|the|a|an|of|for|me|my|please|statement|statements|ledger|ledgers|pdf|excel|print|download|export|save|account|accounts|party|agent|vendor|client|customer|supplier|this|last|month|year|and|to|in|its|his|her|give|send|dekhao|kholo|koro|er|hisab)$/i;
export function parties(D) {
  const by = new Map();
  for (const t of (D && D.party_transactions) || []) {
    if (t.user_id == null || !t.party_name) continue;
    if (!by.has(t.user_id)) by.set(t.user_id, { id: t.user_id, name: String(t.party_name).trim(), type: t.party_type || null });
  }
  return [...by.values()];
}
export function findParty(D, text) {
  const list = parties(D);
  if (!list.length) return null;
  const words = String(text || '').replace(/[?.!,।:;'’"]/g, ' ').replace(/\b(\w+)'s\b/g, '$1').split(/\s+/).filter(Boolean);
  const wanted = [];
  for (const w of words) {
    if (STOP.test(w)) continue;
    // রাব্বির — the genitive র is grammar, not the name
    const sks = BN.test(w) ? [banglaSkeleton(w), banglaSkeleton(w.replace(CASE_ENDING, '')), banglaSkeleton(w.replace(/র$/, ''))] : [latinSkeleton(w)];
    /* RABBI reduces to "rb": two letters is short, but it came from a real word.
       A two-letter skeleton from a two-letter word ("md", "er") is noise. */
    const real = w.replace(/[^\p{L}\p{M}]/gu, '').length >= 4;
    for (const sk of sks) if ((sk.length >= 3 || (sk.length === 2 && real)) && !wanted.includes(sk)) wanted.push(sk);
  }
  if (!wanted.length) return null;
  const low = String(text || '').toLowerCase();
  const scored = list.map((p) => {
    const parts = p.name.split(/\s+/).map(latinSkeleton).filter((x) => x.length >= 2);
    const exact = low.includes(p.name.toLowerCase()) ? 10 : 0;
    // whole name words only — a skeleton never matches a fragment of one
    return { p, hits: exact + wanted.filter((sk) => parts.includes(sk)).length };
  }).filter((x) => x.hits > 0).sort((a, b) => b.hits - a.hits);
  if (!scored.length) return null;
  if (scored.length > 1 && scored[1].hits === scored[0].hits) return { ambiguous: scored.filter((x) => x.hits === scored[0].hits).slice(0, 5).map((x) => x.p) };
  return scored[0].p;
}

/* ---------- building the answer ---------- */
const qs = (o) => Object.entries(o).filter(([, v]) => v != null && v !== '').map(([k, v]) => `${k}=${encodeURIComponent(v)}`).join('&');
const addr = (path, params = {}) => { const q = qs(params); return url(`/{role}/${path}`) + (q ? `?${q}` : ''); };

function reply(bn, en, bnText, href, label, labelBn, extra = []) {
  return {
    speak: bn ? bnText : en,
    detail: extra,
    actions: [{ label: bn ? labelBn : label, kind: 'erp-open', href }],
    navigate: href,
  };
}

function answer(q) {
  const s = String(q || '').trim();
  if (!s || !VERB.test(s)) return null;
  const bn = BN.test(s);
  const fmt = formatOf(s);
  const per = periodIn(s);
  const perEn = per ? ` for ${per.label}` : '';
  const perBn = per ? ` (${per.label_bn || per.label})` : '';
  const range = per ? { from: per.from, to: per.to } : {};

  /* reports that are not about one party come first: their names contain
     "ledger" too, and no party is named in them */
  if (CASH_FLOW.test(s)) {
    const path = fmt === 'excel' ? 'report/cash-flow/export' : fmt === 'print' || fmt === 'pdf' ? 'report/cash-flow/print' : 'report/cash-flow';
    const href = addr(path, range);
    const what = fmt === 'excel' ? 'Downloading the cash-flow statement' : fmt === 'open' ? 'Opening the cash-flow statement' : 'Opening the cash-flow statement to print';
    const whatBn = fmt === 'excel' ? 'ক্যাশ ফ্লো স্টেটমেন্ট ডাউনলোড হচ্ছে' : fmt === 'open' ? 'ক্যাশ ফ্লো স্টেটমেন্ট খুলছি' : 'ক্যাশ ফ্লো স্টেটমেন্ট প্রিন্টের জন্য খুলছি';
    return reply(bn, `${what}${perEn}.`, `${whatBn}${perBn}।`, href, 'Cash flow', 'ক্যাশ ফ্লো');
  }
  if (GENERAL_LEDGER.test(s)) {
    const href = addr(fmt === 'open' ? 'report/general-ledger' : 'report/general-ledger/print', range);
    return reply(bn, `${fmt === 'open' ? 'Opening' : 'Opening to print'} the general ledger${perEn}.`,
      `জেনারেল লেজার ${fmt === 'open' ? 'খুলছি' : 'প্রিন্টের জন্য খুলছি'}${perBn}।`, href, 'General ledger', 'জেনারেল লেজার');
  }
  if (COMPANY_LEDGER.test(s)) {
    const printing = fmt !== 'open';
    const href = addr('party-statement/company', printing ? { print: 1, ...range } : {});
    const extra = printing && fmt !== 'print'
      ? [bn ? 'কোম্পানি লেজারের আলাদা পিডিএফ বা এক্সেল নেই — প্রিন্ট শিট খুলছি, সেখান থেকে পিডিএফে সেভ করা যায়।' : 'The Company Ledger has no PDF or Excel export of its own — this is its print sheet, which saves as PDF from the print dialog.']
      : [];
    return reply(bn, `${printing ? 'Opening the Company Ledger print sheet' : 'Opening the Company Ledger'}${printing ? perEn : ''}.`,
      `${printing ? 'কোম্পানি লেজারের প্রিন্ট শিট খুলছি' : 'কোম্পানি লেজার খুলছি'}${printing ? perBn : ''}।`, href, 'Company Ledger', 'কোম্পানি লেজার', extra);
  }
  if (!LEDGERISH.test(s) && !ALL_PARTIES.test(s)) return null;

  if (ALL_PARTIES.test(s)) {
    const href = addr('party-statement/parties/print');
    return reply(bn, 'Opening every party with its balance, ready to print.', 'সব পার্টির ব্যালান্সসহ তালিকা প্রিন্টের জন্য খুলছি।', href, 'All parties', 'সব পার্টি');
  }

  const D = D0();
  const party = D ? findParty(D, s) : null;
  if (party && party.ambiguous) {
    const names = party.ambiguous.map((p) => p.name).join(', ');
    return {
      speak: bn ? `কোন পার্টি? মিলছে: ${names}। পুরো নামটা বলুন।` : `Which party? These all match: ${names}. Say the full name.`,
      detail: [],
      actions: party.ambiguous.map((p) => ({ label: p.name, kind: 'erp-open', href: addr('party-statement', { party_id: p.id }) })),
    };
  }

  if (party) {
    const name = party.name;
    if (fmt === 'excel') {
      const href = addr('party-statement/export/excel', { party_id: party.id, ...range });
      return reply(bn, `Downloading ${name}'s statement as Excel${perEn}.`, `${name}-এর স্টেটমেন্ট এক্সেলে ডাউনলোড হচ্ছে${perBn}।`, href, 'Excel', 'এক্সেল');
    }
    if (fmt === 'pdf' || fmt === 'print') {
      const href = addr('party-statement/export/pdf', { party_id: party.id, ...range });
      const extra = fmt === 'print' ? [bn ? 'একক পার্টির স্টেটমেন্ট ইআরপি পিডিএফ থেকেই প্রিন্ট করে — পিডিএফ খুললে প্রিন্ট করুন।' : 'The ERP prints a single party\'s statement from its PDF — print it from there.'] : [];
      return reply(bn, `${fmt === 'print' ? 'Opening' : 'Downloading'} ${name}'s statement as PDF${perEn}.`,
        `${name}-এর স্টেটমেন্ট পিডিএফ ${fmt === 'print' ? 'খুলছি' : 'ডাউনলোড হচ্ছে'}${perBn}।`, href, 'PDF', 'পিডিএফ', extra);
    }
    const href = addr('party-statement', { party_id: party.id });
    const extra = per ? [bn ? 'স্ক্রিনে তারিখ বাছাই ইআরপির নিজের ফিল্টারে — পিডিএফ বা এক্সেল চাইলে তারিখসহ দিতে পারি।' : 'The screen sets its own dates — ask for the PDF or Excel and the period is applied.'] : [];
    return reply(bn, `Opening ${name}'s statement.`, `${name}-এর স্টেটমেন্ট খুলছি।`, href, name, name, extra);
  }

  // no party named: one of the three ledgers, or the statement dashboard
  const kind = KIND.find((k) => k.re.test(s));
  if (kind) {
    const href = addr(`party-statement/${kind.key}`);
    return reply(bn, `Opening the ${kind.en}.`, `${kind.bn} খুলছি।`, href, kind.en, kind.bn);
  }
  if (/\b(party|parties)\b|পার্টি/.test(s) || /statement|স্টেটমেন্ট/i.test(s)) {
    const href = addr('party-statement');
    return reply(bn, 'Opening Party Statement.', 'পার্টি স্টেটমেন্ট খুলছি।', href, 'Party Statement', 'পার্টি স্টেটমেন্ট',
      [bn ? 'কোন পার্টির নাম বললে সরাসরি তারটা খুলব।' : 'Name a party and I will open theirs directly.']);
  }
  return null;   // "show the ledger" with nothing else — the navigator and answerers know more
}

if (typeof window !== 'undefined') {
  window.EonStatement = { answer, findParty: (t) => findParty(D0(), t), periodIn, parties: () => parties(D0()) };
  // above entity (98) and act (99): "open RABBI's ledger" is an order, and both of
  // those also claim the word ledger
  (window.__eonDomainQueue = window.__eonDomainQueue || []).push({ id: 'statement', priority: 99.5, answer });
}

export default { answer, findParty, periodIn };
