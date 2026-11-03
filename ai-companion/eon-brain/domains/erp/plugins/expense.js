/* ============================================================
   EON · expense — "স্টেশনারি ১০ হাজার টাকা দিছি, খরচে তুলো"

   An expense said the way it is said — by category, amount, where the money
   came from, which business it was for — filled into the ERP's OWN expense
   form (role.expenses.create → role.expenses.store), read off the page so
   every id is the ERP's: company, cost centre (department), category, the
   bank / petty cash it left from. Whatever the form insists on and the
   sentence did not say is asked for, one question at a time, and any answer
   may carry several at once. Money: nothing is posted without the boss's
   "yes" (act.js's gate).
   ============================================================ */
import { discover, propose } from './act.js';
import { normalize, speaksBangla } from './voicefix.js';

const FRESH = 10 * 60 * 1000;
let convo = null;

const ENTRY = /\b(expense|khoroch|khorch)\b.*\b(entry|add|record|tolo|tulo|lekho|likho|dao|koro)\b|\b(record|add|enter)\b.*\bexpense\b|খরচ.*(এন্ট্রি|তুলো|তুলে|লেখো|লিখো|লিখে|যোগ|দাও|করো)|খরচে\s*(তুলো|লেখো|লিখো|তোলো)|খরচ\s*হিসেবে|(\d|[০-৯]).*(গেছে|দিছি|দিয়েছি|দিলাম|নিছে|নিয়েছে)\s*(,|।)?\s*(খরচ|$)|\bdisi\b.*\bkhoroch/i;
const SCALE = [[/^(হাজার|hajar|thousand|k)$/i, 1e3], [/(লাখ|লক্ষ|lakh|lac|lakhs)/i, 1e5], [/(কোটি|crore|koti)/i, 1e7]];

/** the amount, however it was said: 10000 · ১০ হাজার · 1.5 lakh · 50,000 */
function amountIn(s) {
  const t = s.replace(/[০-৯]/g, (d) => '০১২৩৪৫৬৭৮৯'.indexOf(d)).replace(/(\d),(\d{3})/g, '$1$2');
  const m = t.match(/(\d+(?:\.\d+)?)\s*(হাজার|hajar|thousand|k\b|লাখ|লক্ষ|lakhs?|lac|কোটি|crore|koti)?/i);
  if (!m) return null;
  let n = parseFloat(m[1]);
  if (m[2]) for (const [rx, k] of SCALE) if (rx.test(m[2])) { n *= k; break; }
  return n > 0 ? Math.round(n) : null;
}

/* what a category is called in speech → words its ERP name will contain */
const CAT = [
  [/স্টেশনারি|stationery|stationary/i, ['stationer']], [/তেল|fuel|petrol|diesel|octane/i, ['fuel', 'oil', 'petrol', 'vehicle']],
  [/ভাড়া|rent|vara|bhara/i, ['rent']], [/যাতায়াত|যাতায়াত|transport|jatayat|conveyance|রিকশা|উবার/i, ['convey', 'transport', 'travel']],
  [/চা-নাস্তা|চা নাস্তা|নাস্তা|tea|snack/i, ['entertain', 'refresh', 'tea', 'food']], [/আপ্যায়ন|entertain/i, ['entertain']],
  [/মোবাইল|mobile|phone/i, ['mobile', 'phone', 'telephone']], [/ইন্টারনেট|internet|wifi/i, ['internet']],
  [/বিদ্যুৎ|current|electric/i, ['electric', 'utility']], [/কুরিয়ার|courier/i, ['courier', 'postage']],
  [/বুস্টিং|boost|facebook|ফেসবুক|marketing|মার্কেটিং|অ্যাড/i, ['market', 'advert', 'boost', 'promotion']],
  [/মেরামত|repair|maintenance/i, ['repair', 'maint']], [/লেবার|labou?r|শ্রমিক/i, ['labour', 'labor', 'wage']],
  [/ব্যাংক চার্জ|bank charge/i, ['bank charge', 'charge']], [/বেতন|salary/i, ['salary']], [/খাবার|lunch|food/i, ['food', 'meal', 'entertain']],
];
const BANKS = [[/বিকাশ|bkash/i, 'bkash'], [/নগদ|nagad/i, 'nagad'], [/সিটি|city/i, 'city'], [/ব্র্যাক|ব্রাক|brac/i, 'brac'], [/ইসলামী|ইসলামি|islami/i, 'islami'],
  [/ডাচ|dutch|dbbl/i, 'dutch'], [/সোনালী|sonali/i, 'sonali'], [/ইস্টার্ন|eastern|ebl/i, 'eastern'], [/ঢাকা ব্যাংক|dhaka bank/i, 'dhaka'], [/অফিস ক্যাশ|office cash|ক্যাশ থেকে|নগদে|cash e|in cash/i, 'office cash'], [/পেটি|petty/i, 'petty']];
const BUSINESS = [[/ট্রাভেল|travel|টিকেটিং|ticket|ভিসা|visa|কন্ট্রাক্ট ফ্লাইট|contract flight/i, 'travel'], [/আইটি|\bit\b|it solution/i, ' it'], [/কনস্ট্রাকশন|construction/i, 'construct'],
  [/ইন্টেরিয়র|interior|wood ?art|উড আর্ট/i, 'wood'], [/প্রপার্টি|property/i, 'propert'], [/শপ|shop/i, 'shop'], [/এক্সপোর্ট|import|export/i, 'export'], [/গ্রুপ|group/i, 'group']];

const opt = (f, words) => {
  if (!f || !f.options) return null;
  for (const w of [].concat(words)) { const o = f.options.find((x) => x.text.toLowerCase().includes(String(w).toLowerCase())); if (o) return o; }
  return null;
};
const field = (form, rx) => form.fields.find((f) => rx.test(f.name));

/** read what the sentence says into the conversation's slots */
function read(c, s, form) {
  const amt = amountIn(s); if (amt && !c.amount) c.amount = amt;
  const fCat = field(form, /^expense_category_id$/) || field(form, /expense_category_id/);
  for (const [rx, words] of CAT) if (rx.test(s) && !c.category) { const o = opt(fCat, words); if (o) { c.category = o; c.what = s.match(rx)[0]; } else c.what = c.what || s.match(rx)[0]; }
  if (!c.category && c.asked === 'category') { const o = fCat && fCat.options.find((x) => s.toLowerCase().includes(x.text.toLowerCase().slice(0, 5))); if (o) c.category = o; }
  const fBank = field(form, /^bank_id$/), fPetty = field(form, /^petty_cash_float_id$/);
  for (const [rx, key] of BANKS) if (rx.test(s) && !c.source) {
    if (key === 'petty' && fPetty && fPetty.options.length) c.source = { field: 'petty_cash_float_id', o: fPetty.options[0] };
    else { const o = opt(fBank, key === 'office cash' ? ['office cash', 'cash'] : key); if (o) c.source = { field: 'bank_id', o }; }
  }
  const fCo = field(form, /^company_id$/);
  for (const [rx, key] of BUSINESS) if (rx.test(s) && !c.company) { const o = opt(fCo, key); if (o) c.company = o; }
  if (!c.company && c.asked === 'company' && fCo) { const o = fCo.options.find((x) => s.toLowerCase().includes(x.text.toLowerCase().split(/\s+/).pop())); if (o) c.company = o; }
  const fDep = field(form, /^expense_department_id$/);
  if (fDep && !c.department) {
    const o = fDep.options.find((x) => s.toLowerCase().includes(x.text.toLowerCase())) || (fDep.options.length === 1 ? fDep.options[0] : null)
      || (c.asked === 'department' ? fDep.options.find((x) => x.text.toLowerCase().split(/\s+/).some((w) => w.length > 3 && s.toLowerCase().includes(w))) : null);
    if (o) c.department = o;
  }
}

const Q = {
  amount: ['How much was it?', 'কত টাকা খরচ হয়েছে?'],
  category: (form) => { const f = field(form, /expense_category_id/); const l = f ? f.options.slice(0, 8).map((o) => o.text).join(', ') : ''; return [`Which category — ${l}?`, `কোন খাতে — ${l}?`]; },
  company: (form) => { const f = field(form, /^company_id$/); const l = f ? f.options.map((o) => o.text).join(', ') : ''; return [`For which business — ${l}?`, `কোন বিজনেসের খরচ — ${l}?`]; },
  department: (form) => { const f = field(form, /^expense_department_id$/); const l = f ? f.options.map((o) => o.text).join(', ') : ''; return [`Which cost centre — ${l}?`, `কোন ডিপার্টমেন্টের খরচ — ${l}?`]; },
  source: ['Paid from where — which bank, bKash, office cash or petty cash?', 'টাকা কোথা থেকে গেছে — কোন ব্যাংক, বিকাশ, অফিস ক্যাশ নাকি পেটি ক্যাশ?'],
};

async function next(c) {
  const form = c.form;
  for (const k of ['amount', 'category', 'company', 'department', 'source']) {
    if (k === 'department' && !field(form, /^expense_department_id$/)) continue;
    if (!c[k]) { c.asked = k; const q = typeof Q[k] === 'function' ? Q[k](form) : Q[k]; return { speak: c.bn ? q[1] : q[0], detail: [] }; }
  }
  convo = null;
  const now = new Date(); const pad = (n) => String(n).padStart(2, '0');
  const values = {
    expense_date: `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`, transaction_time: `${pad(now.getHours())}:${pad(now.getMinutes())}`,
    company_id: c.company.value, expense_category_id: c.category.value, payment_mode: c.source.field === 'bank_id' && /bkash|nagad|rocket/i.test(c.source.o.text) ? 'mobile_banking' : c.source.field === 'bank_id' && !/cash/i.test(c.source.o.text) ? 'bank_transfer' : 'cash',
    'items[0][expense_category_id]': c.category.value, 'items[0][description]': c.what || c.category.text, 'items[0][amount]': c.amount, 'items[0][quantity]': 1, 'items[0][unit_price]': c.amount,
    [c.source.field]: c.source.o.value,
  };
  if (c.department) values.expense_department_id = c.department.value;
  const tk = (n) => '৳' + Number(n).toLocaleString('en-IN');
  return propose({
    what: 'expense', money: true, missing: [], lang: c.bn ? 'bn' : 'en', form, values,
    summary: `Record an expense of ${tk(c.amount)} — ${c.category.text}, ${c.company.text}, paid from ${c.source.o.text}`,
    summary_bn: `${tk(c.amount)} খরচ এন্ট্রি — ${c.category.text}, ${c.company.text}, ${c.source.o.text} থেকে`,
  });
}

async function answer(q) {
  const raw = String(q || '').trim(); const s = normalize(raw);
  if (convo && Date.now() - convo.at > FRESH) convo = null;
  if (convo) {
    if (/^(cancel|stop|no|না|থাক|বাদ দাও)\b/i.test(raw)) { convo = null; return { speak: speaksBangla(raw) ? 'ঠিক আছে, বাদ দিলাম।' : 'Okay, dropped it.', detail: [] }; }
    convo.at = Date.now(); read(convo, s, convo.form); return next(convo);
  }
  if (!ENTRY.test(s) || !amountIn(s)) return null;
  let form;
  try { form = await discover('/' + (location.pathname.split('/').filter(Boolean)[0] || 'super-admin') + '/expenses/create'); }
  catch (e) { return { speak: e && e.signIn ? 'Sign in to the ERP first, then say it again.' : 'I could not open the expense form: ' + (e.message || e), detail: [] }; }
  convo = { at: Date.now(), bn: speaksBangla(raw), form };
  read(convo, s, form);
  return next(convo);
}

if (typeof window !== 'undefined') {
  window.EonExpense = { answer, amountIn };
  (window.__eonDomainQueue = window.__eonDomainQueue || []).push({ id: 'expense', priority: 99.65, answer });
}
export default { answer, amountIn, _test: { read, next, start: (raw, form) => { convo = { at: Date.now(), bn: speaksBangla(raw), form }; read(convo, normalize(raw), form); return next(convo); }, reply: (raw) => answer(raw) } };
