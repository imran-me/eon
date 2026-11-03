#!/usr/bin/env node
/* ============================================================
   EON · voice-commands — the orders, said the way people say them.

   The question bank (qa-corpus.json) proves EON answers questions.
   This proves it carries out ORDERS, and that it hears them through a
   microphone: every command is generated across

     · language   English (formal and sloppy), বাংলা, Banglish, and the
                  mix people actually speak ("রাব্বির statement টা pdf দাও")
     · the ear    what speech recognition writes — "led jar", "re fund",
                  "P N R V K 8 V U 3", "fifteen thousand", "পনেরো হাজার"
     · the mouth  fillers, politeness, word order, names in lower case,
                  possessives in both scripts
     · near-miss  questions that use the same words and must NOT act —
                  "how much refund did we give", "কতজন অফলোড হয়েছে"

   and each case asserts which part answered and what it would do (the
   address it opens, or the fields of the plan waiting for a yes).

     node tools/voice-commands.mjs [--dataset file.json] [--fail] [--json out.json]

   The ERP is mocked at the network: the ticket lookup and the forms are
   answered by fixtures, so nothing is posted anywhere.
   ============================================================ */
import { readFileSync, writeFileSync, existsSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
const arg = (k, d) => { const i = process.argv.indexOf(k); return i > 0 ? process.argv[i + 1] : d; };
const flag = (k) => process.argv.includes(k);
const dsPath = arg('--dataset', join(HERE, '.qa-dataset.json'));
if (!existsSync(dsPath)) { console.error(`no dataset at ${dsPath} — run qa-run.php once, or pass --dataset`); process.exit(2); }
const raw = JSON.parse(readFileSync(dsPath, 'utf8'));
const DS = raw.dataset || raw;

/* ---------- a browser, as far as the plug-ins can tell ---------- */
globalThis.window = { EonErp: { dataset: () => DS }, addEventListener() {} };
globalThis.location = { origin: 'https://erp', pathname: '/super-admin/dashboard' };
globalThis.localStorage = { getItem: () => null, setItem() {} };
const PAX = { purchase_id: 501, passenger: 'KARIM UDDIN', pnr: 'VK8VU3', route: 'DAC-JED', sale_price: 85000 };
const PAX2 = { purchase_id: 502, passenger: 'RAHIMA BEGUM', pnr: 'VK8VU3 (a)', route: 'DAC-JED', sale_price: 85000 };
globalThis.fetch = async (u) => {
  if (/ticket-invoice-lookup/.test(u)) {
    if (/ZZ99ZZ/.test(u)) return { status: 200, url: u, json: async () => ({ success: false, message: 'not found' }) };
    const two = /pnr=GRP777/.test(u);
    return { status: 200, url: u, json: async () => ({ success: true, passengers: two ? [PAX, { ...PAX2, pnr: 'GRP777 (a)' }].map((p, i) => ({ ...p, pnr: i ? 'GRP777 (a)' : 'GRP777' })) : [PAX] }) };
  }
  if (/ticket-purchase$/.test(u)) return { status: 200, url: u, text: async () => '<meta name="csrf-token" content="T">' };
  throw new Error('unexpected request ' + u);
};
const P = join(HERE, '..', 'ai-companion', 'eon-brain', 'domains', 'erp', 'plugins');
const load = async (f) => (await import(pathToFileURL(join(P, f)).href)).default;
const act = await import(pathToFileURL(join(P, 'act.js')).href);
const PARTS = [['ticket', await load('ticket.js')], ['contract-flight', await load('cflight.js')], ['statement', await load('statement.js')]];
const ticketMod = await import(pathToFileURL(join(P, 'ticket.js')).href);
const statement = await import(pathToFileURL(join(P, 'statement.js')).href);
const cflight = await import(pathToFileURL(join(P, 'cflight.js')).href);

async function route(q) {
  act.cancel();
  ticketMod.forget();
  for (const [id, m] of PARTS) {
    const r = await m.answer(q);
    if (r) return { id, r, plan: act.pendingPlan() };
  }
  return { id: null, r: null, plan: null };
}

/* ---------- real names from the dataset ---------- */
const parties = statement.parties(DS).filter((p) => p.name.split(/\s+/).some((w) => w.length >= 4));
const rabbi = parties.find((p) => /rabbi/i.test(p.name)) || parties[0];
const party2 = parties.find((p) => p !== rabbi && !/rabbi/i.test(p.name));
const pax = (DS.contract_flight_passengers || []).find((p) => p.agent_id && p.name.split(/\s+/).length >= 2);
const BN_NAME = { rabbi: 'রাব্বি', amit: 'অমিত', baidya: 'বৈদ্য' };
/* the word a person would say: "RABBI", "BAIDYA" — never a title, nor the
   bracketed tag the ERP files agents under ("MR RABBI (CYP)") */
const key = (n) => n.replace(/\([^)]*\)/g, ' ').split(/\s+/).map((w) => w.replace(/[^A-Za-z]/g, ''))
  .filter((w) => w.length >= 4 && !/^(MR|MRS|MST|MISS|ENTERPRISE|TRAVELS?)$/i.test(w)).pop();

/* ---------- the cases ---------- */
const C = [];
const add = (part, q, check, tag) => C.push({ part, q, check, tag });
const href = (re) => (o) => o.r && re.test(o.r.navigate || '');
const plan = (want) => (o) => o.plan && Object.entries(want).every(([k, v]) => String(o.plan.values[k]) === String(v));
const ESSENTIAL = /bears|বহন|travel date|তারিখ|refund to the customer|ফেরত দেব/;
const planOrAsk = (want) => (o) => plan(want)(o) || (!o.plan && o.r && ESSENTIAL.test(o.r.speak) && (() => { const c = ticketMod.conversation(); return c && Object.entries(want).every(([k, v]) => String(c.values[k]) === String(v)); })());
const asks = (re) => (o) => o.r && !o.plan && re.test(o.r.speak);
const none = (o) => o.id === null;
const fill = ['', 'EON, ', 'umm ', 'please ', 'acha ', 'ok so ', 'একটু ', 'আচ্ছা '];
const tail = ['', ' please', ' now', ' quickly', ' thanks', ' দ্রুত'];
const spin = (arr, i) => arr[i % arr.length];

if (rabbi) {
  const w = key(rabbi.name), lw = w.toLowerCase(), bn = BN_NAME[lw] || null;
  const id = rabbi.id;
  const OPEN = new RegExp(`party-statement\\?party_id=${id}$`), PDF = new RegExp(`export/pdf\\?party_id=${id}`), XLS = new RegExp(`export/excel\\?party_id=${id}`);
  const OCT = /from=2026-10-01&to=2026-10-31/;
  const opens = [
    `open ${rabbi.name}'s statement`, `show ${w} statement`, `${lw} er statement dekhao`, `${lw} statement ta kholo`,
    `can you open the statement for ${lw}`, `take me to ${lw}'s ledger`, `${lw} led jar dekhaw`, `${lw} er ledger ta dekhan`,
    `statement of ${lw}`, `pull up ${lw} ledger`, `I want to see ${lw} statement`, `${w.toUpperCase()} statment open`,
  ];
  if (bn) opens.push(`${bn}র স্টেটমেন্ট দেখাও`, `${bn} এর লেজার খোলো`, `${bn}র হিসাব দেখান`, `${bn}র ষ্টেটমেন্ট টা দেখি`, `${bn}র statement দেখাও`, `${bn} এর লেজারটা খুলুন`);
  opens.forEach((q, i) => add('statement', spin(fill, i) + q + spin(tail, i), href(OPEN), 'open'));
  const pdfs = [
    `${lw} statement pdf for october`, `download ${lw}'s statement as pdf october`, `${lw} er october er statement pdf dao`,
    `pdf of ${lw} ledger for October 2026`, `${lw} statement p d f october`, `give me ${lw} statement in pdf for oct`,
  ];
  if (bn) pdfs.push(`${bn}র অক্টোবরের স্টেটমেন্ট পিডিএফ দাও`, `${bn}র স্টেটমেন্ট অক্টোবর পিডিএফ`, `${bn}র অক্টোবর মাসের লেজার পিডিএফ চাই`, `${bn}র statement টা october এর pdf দাও`);
  pdfs.forEach((q, i) => add('statement', spin(fill, i + 3) + q, (o) => href(PDF)(o) && OCT.test(o.r.navigate), 'pdf+period'));
  const xls = [`${lw} statement excel`, `download ${lw} ledger excel last month`, `${lw} er statement excel file e dao`, `export ${lw} statement to excel`, `${lw} ledger exel sheet goto mash`];
  if (bn) xls.push(`${bn}র স্টেটমেন্ট এক্সেলে দাও`, `${bn}র গত মাসের লেজার এক্সেল ডাউনলোড`);
  xls.forEach((q, i) => add('statement', spin(fill, i) + q, href(XLS), 'excel'));
  [`print ${lw}'s statement`, `${lw} statement print koro`, bn && `${bn}র স্টেটমেন্ট প্রিন্ট করো`].filter(Boolean)
    .forEach((q) => add('statement', q, href(PDF), 'print→pdf'));
  // near-miss: the same nouns, asked as questions about a figure
  [`how much does ${lw} owe`, `${lw} er koto baki`, `what is ${lw}'s balance`, bn && `${bn}র কত টাকা বাকি`, `is ${lw} paid up`, bn && `${bn} কি টাকা দিয়েছে`]
    .filter(Boolean).forEach((q) => add('none', q, none, 'figure question'));
}
// a report is a statement too, and a company is also a party: neither is a party statement
['income statement for Epal Travels', 'show the income statement', 'bank statement of city bank', 'balance sheet dekhao', 'trial balance pdf', 'লাভ ক্ষতির হিসাব দেখাও']
  .forEach((q) => add('none', q, none, 'report, not a party'));
if (party2) add('statement', `open ${party2.name} statement`, href(new RegExp(`party_id=${party2.id}$`)), 'second party');

[['open the vendor ledger', /vendors$/], ['vendor ledger dekhao', /vendors$/], ['show supplier ledger', /vendors$/], ['ভেন্ডর লেজার খোলো', /vendors$/],
 ['agent ledger', null], ['open agent ledger', /agents$/], ['এজেন্ট লেজার দেখাও', /agents$/], ['agent der ledger ta kholo', /agents$/],
 ['open client ledger', /clients$/], ['customer ledger dekhao', /clients$/], ['ক্লায়েন্ট লেজার দেখাও', /clients$/],
 ['open the company ledger', /company$/], ['কোম্পানি লেজার দেখাও', /company$/], ['company ledger ta dekhao', /company$/],
 ['print the company ledger for october', /company\?print=1&from=2026-10-01/], ['কোম্পানির লেজার প্রিন্ট করো অক্টোবর', /company\?print=1&from=2026-10-01/],
 ['company ledger pdf this month', /company\?print=1/],
 ['download cash flow excel', /cash-flow\/export/], ['cash flo excel download', /cash-flow\/export/], ['ক্যাশ ফ্লো এক্সেল দাও', /cash-flow\/export/],
 ['print the cash flow', /cash-flow\/print/], ['open cash flow statement', /cash-flow$/], ['cashflow dekhao', /cash-flow$/],
 ['print the general ledger this month', /general-ledger\/print\?from=2026-11-01/], ['জেনারেল লেজার দেখাও', /general-ledger$/], ['open GL', /general-ledger$/],
 ['print all parties', /parties\/print/], ['সব পার্টির হিসাব প্রিন্ট', /parties\/print/], ['open party statement', /party-statement$/], ['পার্টি স্টেটমেন্ট খোলো', /party-statement$/]]
  .forEach(([q, re]) => re ? add('statement', q, href(re), 'ledgers') : add('none', q, none, 'bare noun'));

/* tickets: one passenger on VK8VU3, two on GRP777, none on ZZ99ZZ */
const refunds = [
  ['refund PNR VK8VU3, refund 15000, penalty 2000, customer bears', { kind: 'refund', refund: 15000, penalty: 2000, bearer: 'customer' }],
  ['re fund P N R V K 8 V U 3 refund fifteen thousand penalty two thousand', { kind: 'refund', refund: 15000, penalty: 2000 }],
  ['VK8VU3 ticket ta refund koro, ferot 15000, jorimana 2000, customer dibe', { kind: 'refund', refund: 15000, penalty: 2000, bearer: 'customer' }],
  ['VK8VU3 রিফান্ড করো, ফেরত ১৫০০০, পেনাল্টি ২০০০', { kind: 'refund', refund: 15000, penalty: 2000 }],
  ['পিএনআর VK8VU3 রিফান্ড, ফেরত পনেরো হাজার, জরিমানা দুই হাজার, আমরা বহন করব', { kind: 'refund', refund: 15000, penalty: 2000, bearer: 'company' }],
  ['please refund ticket VK8VU3 refund 15k penalty 2k we bear it', { kind: 'refund', refund: 15000, penalty: 2000, bearer: 'company' }],
  ['refund the VK8VU3 ticket, refund amount 1.5 lakh', { kind: 'refund', refund: 150000 }],
  ['VK8VU3 টিকিটটা রিফান্ড করে দাও, ফেরত দেড় লাখ', { kind: 'refund', refund: 150000 }],
  ['void invoice INV000021', { kind: 'void' }], ['void PNR VK8VU3', { kind: 'void' }], ['avoid the ticket pnr VK8VU3', { kind: 'void' }],
  ['VK8VU3 ভয়েড করো', { kind: 'void' }], ['VK8VU3 ticket void kore dao', { kind: 'void' }], ['invoice 21 void koro', { kind: 'void' }],
  ['reissue ticket VK8VU3 penalty 1500 service fee 500', { kind: 'reissue', penalty: 1500, service_fee: 500 }],
  ['re issue VK8VU3, fare difference 3000', { kind: 'reissue', fare_difference: 3000 }],
  ['VK8VU3 রিইস্যু করো, পেনাল্টি ১৫০০', { kind: 'reissue', penalty: 1500 }],
  ['reschedule PNR VK8VU3 to 2026-11-20', { kind: 'reschedule', new_travel_at: '2026-11-20' }],
  ['re schedule VK8VU3 2026-11-20 10:30 fare difference three thousand', { kind: 'reschedule', new_travel_at: '2026-11-20 10:30', fare_difference: 3000 }],
  ['VK8VU3 রিশিডিউল ২০২৬-১১-২০', { kind: 'reschedule', new_travel_at: '2026-11-20' }],
  ['cancel the return on PNR VK8VU3', { kind: 'return_cancel' }], ['VK8VU3 রিটার্ন বাতিল করো', { kind: 'return_cancel' }],
];
refunds.forEach(([q, want], i) => add('ticket', spin(fill, i) + q, planOrAsk(want), 'ticket change'));
[['refund PNR VK8VU3', /refund to the customer/], ['VK8VU3 রিফান্ড করো', /ফেরত দেব/], ['reschedule VK8VU3', /new travel date/],
 ['refund a ticket', /PNR/], ['একটা টিকিট রিফান্ড করো', /পিএনআর/],
 ['refund PNR GRP777 refund 5000', /2 passengers/], ['refund PNR ZZ99ZZ refund 5000', /no ticket sale/]]
  .forEach(([q, re]) => add('ticket', q, asks(re), 'asks'));
add('ticket', 'refund PNR GRP777 for Rahima, refund 5000', plan({ kind: 'refund', refund: 5000 }), 'passenger named');
['how much refund did we give last month', 'how many tickets were voided', 'total refund this month', 'কত টাকা রিফান্ড হয়েছে', 'refund koto hoise', 'what is the reissue fee']
  .forEach((q) => add('none', q, none, 'figure question'));

/* contract flight */
if (pax) {
  const w = key(pax.name), lw = w.toLowerCase();
  const first = pax.name.replace(/^(MR|MRS|MD|MST)\.?\s+/i, '').split(/\s+/)[0];
  const bnFull = [first, w].map((x) => BN_NAME[x.toLowerCase()]).every(Boolean) ? [first, w].map((x) => BN_NAME[x.toLowerCase()]).join(' ') : null;
  const flight = new RegExp(`contract-flights/${pax.contract_flight_id}/passengers`);
  [[`offload ${pax.name}, immigration, agent's fault, hold`, { event: 'immigration_offload', fault: 'agent', next: 'hold' }],
   [`off load ${first} ${lw} agent er dosh`, { event: 'immigration_offload', fault: 'agent' }],
   [`${first} ${lw} ke offload koro, amader dosh, rebook`, { event: 'immigration_offload', fault: 'company', next: 'rebook' }],
   [`boarding offload ${pax.name} we bear it close the case`, { event: 'boarding_denied', fault: 'company', next: 'close' }],
   [`deport passport ${pax.passport_no}, we bear it`, { event: 'deported', fault: 'company', next: 'hold' }],
   [`${pax.passport_no} deported, bill the agent`, { event: 'deported', fault: 'agent' }],
   [`de port ${first} ${lw}, agent pays`, { event: 'deported', fault: 'agent' }],
   [`পাসপোর্ট ${pax.passport_no} অফলোড, এজেন্টের দোষ`, { event: 'immigration_offload', fault: 'agent' }],
   [`পাসপোর্ট ${pax.passport_no} ডিপোর্ট হয়েছে, আমরা বহন করব`, { event: 'deported', fault: 'company' }],
   bnFull && [`${bnFull}কে অফলোড করো, দোষ এজেন্টের`, { event: 'immigration_offload', fault: 'agent' }],
  ].filter(Boolean).forEach(([q, want], i) => add('contract-flight', spin(fill, i) + q, plan(want), 'offload/deport'));
  [[`offload ${pax.name}`, /whose fault/], [`${pax.passport_no} অফলোড করো`, /দায় কার/], ['offload the passenger', /Which passenger/], ['অফলোড করো', /কোন যাত্রী/]]
    .forEach(([q, re]) => add('contract-flight', q, asks(re), 'asks'));
  [[`offload ${pax.name} refund 25000 agent's fault`, flight], [`open ${pax.name}'s flight`, flight], [`where is ${first} ${lw} flight`, flight], [`${pax.passport_no} er flight dekhao`, flight]]
    .forEach(([q, re]) => add('contract-flight', q, href(re), 'opens flight'));
}
['how many were offloaded this month', 'কতজন অফলোড হয়েছে', 'list deported passengers', 'deport koyjon hoise', 'open the contract flight board']
  .forEach((q) => add('none', q, none, 'figure question'));

/* ---------- run ---------- */
const rows = [];
for (const c of C) {
  const o = await route(c.q);
  const partOk = c.part === 'none' ? o.id === null : o.id === c.part;
  let ok = false; try { ok = partOk && !!c.check(o); } catch { ok = false; }
  rows.push({ ...c, ok, got: o.id, speak: o.r ? o.r.speak : '', at: o.r ? (o.r.navigate || '') : '', values: o.plan ? o.plan.values : null });
}
const by = {};
for (const r of rows) { const k = `${r.part} · ${r.tag}`; (by[k] = by[k] || [0, 0])[1]++; if (r.ok) by[k][0]++; }
console.log('PART · KIND'.padEnd(36), 'PASS/TOTAL');
for (const [k, [p, t]] of Object.entries(by)) console.log(k.padEnd(36), `${p}/${t}`.padStart(9), p === t ? '' : '  ←');
const pass = rows.filter((r) => r.ok).length;
console.log('-'.repeat(48)); console.log('TOTAL'.padEnd(36), `${pass}/${rows.length}`.padStart(9), `${(100 * pass / rows.length).toFixed(1)}%`);
if (flag('--fail')) rows.filter((r) => !r.ok).forEach((r) => console.log(`\nFAIL [${r.part}/${r.tag}] ${r.q}\n   got ${r.got || 'nobody'}: ${String(r.speak).slice(0, 140)} ${r.at.replace('https://erp', '')} ${r.values ? JSON.stringify(r.values) : ''}`));
if (arg('--json')) writeFileSync(arg('--json'), JSON.stringify(rows, null, 1));
process.exit(pass === rows.length ? 0 : 1);
