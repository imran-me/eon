/* ============================================================
   EON · nav-bank — 100,000+ questions about WHERE things are in the ERP
   and HOW to reach them: every module (resource) the routes carry, every
   function a screen offers (list, add, print, export, report), asked in
   English, Banglish and Bangla — Bangla both as written and as the bn-BD
   microphone writes spoken English. Each question must reach a screen of
   the right module, and of the right function when one was asked for.

     node tools/nav-bank.mjs            fire the whole bank, print coverage
     node tools/nav-bank.mjs --fail     also list misses (first 60)
     node tools/nav-bank.mjs --write    also write tools/nav-bank.jsonl (git-ignored)
   ============================================================ */
import { readFileSync, writeFileSync } from 'node:fs';
const ROOT = new URL('..', import.meta.url);
const map = JSON.parse(readFileSync(new URL('ai-companion/eon-brain/domains/erp/erp-map.json', ROOT), 'utf8'));
globalThis.window = { addEventListener() {} };
globalThis.location = { origin: 'https://x', pathname: '/super-admin/dashboard' };
globalThis.fetch = async () => ({ ok: true, json: async () => map });
const N = await import(new URL('ai-companion/eon-brain/domains/erp/plugins/navigator.js', ROOT));
await new Promise((r) => setTimeout(r, 30));

/* the Bangla a person writes for a module word, where one is settled; else the mic's spelling */
const BN = { bank: 'ব্যাংক', banks: 'ব্যাংক', transfer: 'ট্রান্সফার', transfers: 'ট্রান্সফার', vendor: 'ভেন্ডর', vendors: 'ভেন্ডর', client: 'ক্লায়েন্ট', clients: 'ক্লায়েন্ট',
  agent: 'এজেন্ট', agents: 'এজেন্ট', ticket: 'টিকিট', visa: 'ভিসা', passport: 'পাসপোর্ট', flight: 'ফ্লাইট', contract: 'কন্ট্রাক্ট', file: 'ফাইল', files: 'ফাইল',
  employee: 'এমপ্লয়ি', user: 'ইউজার', salary: 'স্যালারি', salaries: 'স্যালারি', payroll: 'পেরোল', attendance: 'হাজিরা', attendances: 'হাজিরা', leave: 'ছুটি', leaves: 'ছুটি',
  task: 'টাস্ক', tasks: 'টাস্ক', project: 'প্রজেক্ট', projects: 'প্রজেক্ট', expense: 'খরচ', expenses: 'খরচ', invoice: 'ইনভয়েস', invoices: 'ইনভয়েস', payment: 'পেমেন্ট',
  journal: 'জার্নাল', journals: 'জার্নাল', ledger: 'লেজার', report: 'রিপোর্ট', account: 'অ্যাকাউন্ট', accounts: 'অ্যাকাউন্ট', company: 'কোম্পানি', companies: 'কোম্পানি',
  party: 'পার্টি', loan: 'লোন', loans: 'লোন', advance: 'অ্যাডভান্স', commission: 'কমিশন', commissions: 'কমিশন', airline: 'এয়ারলাইন', airlines: 'এয়ারলাইন',
  airport: 'এয়ারপোর্ট', lead: 'লিড', leads: 'লিড', settings: 'সেটিংস', budget: 'বাজেট', purchase: 'পারচেজ', sale: 'সেল', sales: 'সেল', customer: 'কাস্টমার', customers: 'কাস্টমার' };
const CONS = [['tion', 'শন'], ['sh', 'শ'], ['ch', 'চ'], ['th', 'থ'], ['ph', 'ফ'], ['ck', 'ক'], ['ng', 'ং'], ['b', 'ব'], ['c', 'ক'], ['d', 'ড'], ['f', 'ফ'], ['g', 'গ'], ['h', 'হ'],
  ['j', 'জ'], ['k', 'ক'], ['l', 'ল'], ['m', 'ম'], ['n', 'ন'], ['p', 'প'], ['q', 'ক'], ['r', 'র'], ['s', 'স'], ['t', 'ট'], ['v', 'ভ'], ['w', 'ও'], ['x', 'ক্স'], ['z', 'জ']];
const VI = { a: 'অ্যা', e: 'এ', i: 'ই', o: 'ও', u: 'আ', y: 'ই' }, VM = { a: 'া', e: 'ে', i: 'ি', o: 'ো', u: 'া', y: 'ি' };
function mic(word) {
  let w = word.toLowerCase().replace(/[^a-z]/g, '').replace(/c(?=[eiy])/g, 's').replace(/g(?=[eiy])/g, 'j').replace(/e$/, '').replace(/ee/g, 'i');
  let out = '', pc = false;
  if (/^u(?=[bcdfgjklmnprstvz][aeiouy])/.test(w)) { out = 'ইউ'; w = w.slice(1); }
  for (let i = 0; i < w.length;) {
    if (VI[w[i]]) { out += pc ? VM[w[i]] : VI[w[i]]; pc = false; i++; while (VI[w[i]]) i++; continue; }
    const c = CONS.find(([k]) => w.startsWith(k, i)); if (!c) { i++; continue; }
    if (pc) out += '্'; out += c[1]; pc = true; i += c[0].length; if (w[i] === w[i - 1]) i++;
  }
  return out;
}
const bnOf = (r, micOnly) => r.split(' ').map((w) => (!micOnly && BN[w]) || mic(w)).join(' ');

/* modules: the resource part of every route name, with the functions it offers */
const STOP = new Set(['role', 'index', 'create', 'store', 'edit', 'update', 'destroy', 'show', 'print', 'export', 'data', 'ajax', 'api', 'v2', 'json', 'list', 'get', 'post']);
const mods = new Map();
for (const p of map.pages) {
  const parts = p.name.replace(/^role\./, '').split('.');
  const action = parts[parts.length - 1];
  const res = parts.filter((x) => !STOP.has(x)).join(' ').replace(/[-_]/g, ' ').replace(/V2$/, '').trim();
  if (!res || res.split(' ').length > 4 || /^\d|\{/.test(res)) continue;
  const fn = action === 'create' ? 'add' : action === 'print' ? 'print' : action === 'export' ? 'export' : /report/.test(p.name) ? 'report' : action === 'index' ? 'list' : null;
  if (!fn) continue;
  if (!mods.has(res)) mods.set(res, new Set());
  mods.get(res).add(fn);
}

/* how each function is asked for */
const T = {
  list: { en: ['where is {r}', 'open {r}', 'show me all {r}', 'take me to {r}', 'go to the {r} list', 'where can I see {r}', 'I need the {r} page', 'where are the {r}'],
          bl: ['{r} kothay', '{r} kholo', '{r} er list dekhao', '{r} e niye jao', 'shob {r} dekhte chai', '{r} ta kothay pabo', '{r} page e jao'],
          bn: ['{b} কোথায়', '{b} খোলো', '{b} এর লিস্ট দেখাও', '{b} এ নিয়ে যাও', 'সব {b} দেখতে চাই', '{b} টা কোথায় পাব', '{b} পেজে যাও', '{b} গুলো দেখাও'] },
  add:  { en: ['where do I add a new {r}', 'create a new {r}', 'add {r}', 'how do I create {r}', 'new {r}'],
          bl: ['notun {r} add korbo kothay', 'new {r} create korbo', '{r} add koro', 'ekta {r} add korte chai'],
          bn: ['নতুন {b} যোগ করব কোথায়', 'নতুন {b} অ্যাড করো', '{b} তৈরি করব কিভাবে', 'একটা নতুন {b} অ্যাড করতে চাই'] },
  print: { en: ['print the {r}', 'where do I print {r}'], bl: ['{r} print koro'], bn: ['{b} প্রিন্ট করো', '{b} প্রিন্ট করব কোথায়'] },
  export: { en: ['export {r}', 'download {r} excel'], bl: ['{r} export koro', '{r} excel download'], bn: ['{b} এক্সপোর্ট করো', '{b} এক্সেল ডাউনলোড'] },
  report: { en: ['{r} report', 'show the {r} report'], bl: ['{r} report dekhao'], bn: ['{b} রিপোর্ট দেখাও', '{b} এর রিপোর্ট'] },
};
const PRE = { en: ['', 'EON, ', 'please ', 'hey EON ', 'okay ', 'can you '], bl: ['', 'EON ', 'ekhon ', 'acha ', 'ektu '], bn: ['', 'আচ্ছা ', 'EON, ', 'একটু ', 'হুম ', 'শোনো '] };
const POST = { en: ['', ' please', ' now', ' quickly'], bl: ['', ' please', ' ekhuni', ' to'], bn: ['', ' প্লিজ', ' এখনই', ' তো'] };

const bank = [];
for (const [r, fns] of mods) for (const fn of fns) for (const lang of ['en', 'bl', 'bn']) for (const t of T[fn][lang]) {
  const forms = lang === 'bn' ? [bnOf(r, false), bnOf(r, true)] : [r];
  for (const f of [...new Set(forms)]) for (const pre of PRE[lang]) for (const post of POST[lang])
    bank.push({ q: pre + t.replace('{r}', f).replace('{b}', f) + post, mod: r, fn, lang: lang === 'bn' && f === bnOf(r, true) && f !== bnOf(r, false) ? 'bn-mic' : lang });
}

const words = (s) => s.toLowerCase().split(/[^a-z0-9]+/).filter((w) => w.length > 2);
const ok = (h, it) => {
  if (!h) return false;
  const hay = `${h.label || ''} ${h.uri || ''} ${h.route || ''}`.toLowerCase().replace(/[-_.\/]/g, ' ');
  if (!words(it.mod).every((w) => hay.includes(w.slice(0, 5)))) return false;
  if (it.fn === 'add') return /create|add|new/.test(hay);
  if (it.fn === 'print') return /print/.test(hay);
  if (it.fn === 'export') return /export|excel|download/.test(hay);
  return true;
};
const by = {}; let pass = 0; const miss = []; const missBy = {};
const cache = new Map();
for (const it of bank) {
  let h = cache.get(it.q); if (h === undefined) { h = N.find(it.q, 1)[0] || null; cache.set(it.q, h); }
  const g = ok(h, it); if (g) pass++; else { const mk = it.lang + it.fn; missBy[mk] = (missBy[mk] || 0) + 1; if (missBy[mk] % 97 === 1 && missBy[mk] < 600) miss.push(`[${it.lang} ${it.fn} · ${it.mod}] ${it.q}  →  ${h ? h.label + ' ' + h.uri : '(none)'}`); }
  const k = it.lang + ' · ' + it.fn; by[k] = by[k] || [0, 0]; by[k][1]++; if (g) by[k][0]++;
}
for (const k of Object.keys(by).sort()) console.log(k.padEnd(18), `${by[k][0]}/${by[k][1]}`, (100 * by[k][0] / by[k][1]).toFixed(1) + '%');
console.log(`TOTAL ${pass}/${bank.length} ${(100 * pass / bank.length).toFixed(1)}%  (${mods.size} modules)`);
if (process.argv.includes('--fail')) miss.forEach((m) => console.log('MISS', m));
if (process.argv.includes('--write')) writeFileSync(new URL('tools/nav-bank.jsonl', ROOT), bank.map((x) => JSON.stringify(x)).join('\n'));
