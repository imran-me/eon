/* ============================================================
   EON · reports — the ERP's own reports, for any period, opened,
   printed or downloaded by voice:
     "গত মাসের ট্রায়াল ব্যালেন্স প্রিন্ট দাও"   "income statement for October pdf"
     "ব্যালেন্স শিট এক্সেলে দাও"                "সিটি ব্যাংকের গত মাসের স্টেটমেন্ট"
     "এই মাসের খরচের রিপোর্ট প্রিন্ট করো"       "monthly profit report"
   statement.js keeps party / agent / vendor / company ledgers, cash flow and
   the GL; this covers the statements it hands on.
   ============================================================ */
import { speaksBangla, normalize } from './voicefix.js';
import { periodIn } from './statement.js';

const N = () => (typeof window !== 'undefined' ? window.EonNavigator : null);
const T = (bn, en, b) => (bn ? b : en);

const REPORTS = [
  { rx: /trial\s*balance|ট্রায়াল\s*ব্যালেন্স|ট্রায়াল\s*ব্যালান্স|রেওয়ামিল|\btb\b/i, path: 'report/trial-balance', print: 'report/trial-balance', excel: null, en: 'Trial balance', bn: 'ট্রায়াল ব্যালেন্স' },
  { rx: /income\s*statement|profit\s*(and|&)?\s*loss|\bp\s*&?\s*l\b|আয়\s*বিবরণী|আয়\s*বিবরণী|লাভ\s*ক্ষতি|লাভ-ক্ষতি|প্রফিট\s*লস|ইনকাম\s*স্টেটমেন্ট/i, path: 'report/profit-loss', print: 'report/profit-loss/print', excel: 'report/profit-loss/export', en: 'Income statement', bn: 'আয়-ব্যয় বিবরণী (P&L)' },
  { rx: /balance\s*sheet|ব্যালেন্স\s*শিট|ব্যালান্স\s*শিট|উদ্বৃত্তপত্র/i, path: 'report/balance-sheet', print: 'report/balance-sheet/print', excel: 'report/balance-sheet/export', asOf: true, en: 'Balance sheet', bn: 'ব্যালেন্স শিট' },
  { rx: /monthly\s*profit|মাসিক\s*লাভ|মাস\s*ভিত্তিক\s*লাভ/i, path: 'report/monthly-profit', print: 'report/monthly-profit/print', excel: null, en: 'Monthly profit', bn: 'মাসিক লাভ' },
  { rx: /expense\s*report|খরচের\s*রিপোর্ট|খরচ\s*রিপোর্ট|এক্সপেন্স\s*রিপোর্ট/i, path: 'expenses/report', print: 'expenses/report/print', excel: 'expenses/report/export', en: 'Expense report', bn: 'খরচের রিপোর্ট' },
];
const BANK = /(ব্যাংক|ব্যাঙ্ক|bank|বিকাশ|bkash|নগদ|nagad).*(স্টেটমেন্ট|statement|লেনদেন)|(স্টেটমেন্ট|statement).*(ব্যাংক|bank)/i;

function format(s) {
  if (/excel|এক্সেল|xlsx|ডাউনলোড|download/i.test(s)) return 'excel';
  if (/print|প্রিন্ট|pdf|পিডিএফ/i.test(s)) return 'print';
  return 'open';
}
const q = (p) => p ? `?date_from=${p.from}&date_to=${p.to}&from=${p.from}&to=${p.to}` : '';

async function answer(text) {
  const raw = String(text || '').trim(); const s = normalize(raw); const bn = speaksBangla(raw);
  if (!N()) return null;
  try { await N().ready(); } catch (e) {}
  const fmt = format(s);
  const per = periodIn(s);   // {from,to,label} or null
  const BNL = { 'last month': 'গত মাস', 'this month': 'এই মাস', 'last week': 'গত সপ্তাহ', 'this week': 'এই সপ্তাহ', today: 'আজ', yesterday: 'গতকাল', 'this year': 'এই বছর', 'last year': 'গত বছর' };
  const lab = per ? (per.label || per.from + ' – ' + per.to) : '';
  const when = per ? ` (${bn ? (BNL[String(lab).toLowerCase()] || lab) : lab})` : '';

  /* one invoice or one ticket, by its number: open its own page and print it once it has loaded
     ("INV000178 প্রিন্ট করো", "VPLX4H টিকেটটা প্রিন্ট দাও") */
  const inv = raw.match(/\bINV-?\d{3,}\b/i), pnr = raw.match(/\b(?=[A-Z0-9]*\d)(?=[A-Z0-9]*[A-Z])[A-Z0-9]{6}\b/);
  if ((inv || pnr) && /print|প্রিন্ট|pdf|পিডিএফ|খোলো|ওপেন|open|দেখাও|show/i.test(s)) {
    const D = window.EonErp && window.EonErp.dataset ? window.EonErp.dataset() : null;
    let path = null, what = '';
    if (inv) { const k = inv[0].toUpperCase().replace('-', ''); const sale = ((D && D.ticket_sales) || []).find((x) => String(x.invoice).toUpperCase().replace('-', '') === k);
      if (sale) { path = `/{role}/ticket-sales/${sale.id}`; what = `${sale.invoice} · ${sale.client}`; } }
    if (!path && pnr) { const t = ((D && D.ticket_index) || []).find((x) => String(x.pnr).toUpperCase() === pnr[0].toUpperCase());
      if (t) { path = `/{role}/ticket-purchase/${t.purchase_id}`; what = `${t.pnr} · ${t.passenger}`; } }
    if (!path) return { speak: T(bn, `I don't find ${(inv || pnr)[0]} in the ERP.`, `ERP-তে ${(inv || pnr)[0]} পাইনি।`), detail: [] };
    const href = N().url(path); N().go(href);
    const doPrint = /print|প্রিন্ট|pdf|পিডিএফ/i.test(s);
    if (doPrint) {
      // print the ERP frame once the record's page is in it
      const t0 = Date.now();
      const tick = () => { try { const f = window.parent.document.getElementById('erp'); const w = f && f.contentWindow;
        if (w && w.location.pathname.endsWith(path.replace('/{role}', '').split('/').slice(-2).join('/')) && w.document.readyState === 'complete') { setTimeout(() => w.print(), 600); return; } } catch (e) {}
        if (Date.now() - t0 < 15000) setTimeout(tick, 400); };
      setTimeout(tick, 500);
    }
    return { speak: T(bn, `${what} — ${doPrint ? 'opening it to print' : 'opening it'}.`, `${what} — ${doPrint ? 'প্রিন্টের জন্য খুলছি' : 'খুলছি'}।`), detail: [], navigate: href, actions: [{ label: T(bn, 'Open', 'খুলুন'), kind: 'erp-open', href }] };
  }

  // a bank's own statement
  if (BANK.test(s)) {
    const D = window.EonErp && window.EonErp.dataset ? window.EonErp.dataset() : null;
    const banks = (D && D.banks) || [];
    const low = s.toLowerCase();
    const KEY = [['বিকাশ', 'bkash'], ['নগদ', 'nagad'], ['সিটি', 'city'], ['ব্র্যাক', 'brac'], ['ব্রাক', 'brac'], ['ইসলামী', 'islami'], ['ডাচ', 'dutch'], ['ঢাকা', 'dhaka'], ['সোনালী', 'sonali'], ['ইস্টার্ন', 'eastern']];
    let key = null; for (const [b, e] of KEY) if (s.includes(b) || low.includes(e)) { key = e; break; }
    const hit = key ? banks.find((b) => String(b.name).toLowerCase().includes(key)) : null;
    if (!hit) return { speak: T(bn, 'Which bank’s statement? Say its name — City, BRAC, Islami, Dutch-Bangla, bKash…', 'কোন ব্যাংকের স্টেটমেন্ট? নামটা বলুন — সিটি, ব্র্যাক, ইসলামী, ডাচ-বাংলা, বিকাশ…'), detail: [] };
    const href = N().url(`/{role}/banks/${hit.id}/statement${fmt === 'print' ? '/pdf' : ''}`) + q(per);
    N().go(href);
    return { speak: T(bn, `${hit.name} statement${when}${fmt === 'print' ? ' — opening the PDF' : ''}.`, `${hit.name}-এর স্টেটমেন্ট${when}${fmt === 'print' ? ' — PDF খুলছি' : ' খুলছি'}।`), detail: [], navigate: href, actions: [{ label: T(bn, 'Open', 'খুলুন'), kind: 'erp-open', href }] };
  }

  const r = REPORTS.find((x) => x.rx.test(s));
  if (!r) return null;
  // "লাভ ক্ষতি কত" wants the figure; only an explicit report / print / file / open opens the screen
  if (fmt === 'open' && !/রিপোর্ট|report|খোলো|খুলুন|ওপেন|open|শিট|sheet|স্টেটমেন্ট|statement|বিবরণী|তালিকা/i.test(s)) return null;
  const path = fmt === 'excel' && r.excel ? r.excel : fmt === 'print' ? r.print : r.path;
  const params = r.asOf && per ? `?as_of=${per.to}` : q(per);
  const href = N().url('/{role}/' + path) + params;
  N().go(href);
  const verb = fmt === 'excel' ? T(bn, 'downloading as Excel', 'এক্সেলে ডাউনলোড করছি') : fmt === 'print' ? T(bn, 'opening to print', 'প্রিন্টের জন্য খুলছি') : T(bn, 'opening', 'খুলছি');
  const noXl = fmt === 'excel' && !r.excel ? T(bn, ' (this report has no Excel export in the ERP, so it opens on screen)', ' (ERP-তে এই রিপোর্টের এক্সেল নেই, তাই স্ক্রিনে খুলছি)') : '';
  return { speak: T(bn, `${r.en}${when} — ${verb}${noXl}.`, `${r.bn}${when} — ${verb}${noXl}।`), detail: [], navigate: href, actions: [{ label: T(bn, 'Open', 'খুলুন'), kind: 'erp-open', href }] };
}

if (typeof window !== 'undefined') {
  window.EonReports = { answer };
  (window.__eonDomainQueue = window.__eonDomainQueue || []).push({ id: 'reports', priority: 99.45, answer });
}
export default { answer };
