#!/usr/bin/env node
/* ============================================================
   EON · voice-dialogues — whole conversations, not single orders.

   voice-commands.mjs proves each order is understood on its own. This
   proves what only shows over several turns:

     · context      "open X's statement" → "pdf for october" → "print it";
                    "refund …" → "actually void it"; "same for Y";
                    "তার ফাইল কোন অবস্থায়"
     · near misses  a name one or two letters off still reaches the record,
                    and EON says the full name it chose
     · reasoning    a travel date already gone is asked again; a refund
                    larger than the sale, a file moved backwards, a visa
                    decision reversed — each is said on the card

     node tools/voice-dialogues.mjs --dataset file.json [--verbose]

   Names are drawn from the dataset, so the dialogues follow the ERP.
   ============================================================ */
import { readFileSync, existsSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
const arg = (k, d) => { const i = process.argv.indexOf(k); return i > 0 ? process.argv[i + 1] : d; };
const verbose = process.argv.includes('--verbose');
const dsPath = arg('--dataset', join(HERE, '.qa-dataset.json'));
if (!existsSync(dsPath)) { console.error(`no dataset at ${dsPath}`); process.exit(2); }
const raw = JSON.parse(readFileSync(dsPath, 'utf8')); const DS = raw.dataset || raw;

globalThis.window = { EonErp: { dataset: () => DS }, addEventListener() {} };
globalThis.location = { origin: 'https://erp', pathname: '/super-admin/dashboard' };
globalThis.localStorage = { getItem: () => null, setItem() {} };
globalThis.fetch = async (u) => {
  if (/ticket-purchase$/.test(u)) return { status: 200, url: u, text: async () => '<meta name="csrf-token" content="T">' };
  if (/ticket-invoice-lookup/.test(u)) return { status: 200, url: u, json: async () => ({ success: true, passengers: [{ purchase_id: 9, passenger: 'TEST PAX', pnr: 'AB12CD', route: 'DAC-DXB', sale_price: 50000 }] }) };
  throw new Error('unexpected ' + u);
};
const P = join(HERE, '..', 'ai-companion', 'eon-brain', 'domains', 'erp', 'plugins');
const mod = async (f) => import(pathToFileURL(join(P, f)).href);
const act = await mod('act.js'), ctx = await mod('context.js');
const ticket = await mod('ticket.js'), cases = await mod('cases.js');
const PARTS = [['ticket', ticket.default], ['contract-flight', (await mod('cflight.js')).default], ['statement', (await mod('statement.js')).default], ['cases', cases.default]];
async function say(q) {
  for (const [id, m] of PARTS) { const r = await m.answer(q); if (r) return { id, r, plan: act.pendingPlan() }; }
  return { id: null, r: null, plan: null };
}

/* ---------- names from the data ---------- */
const party = (() => { const m = new Map(); for (const t of DS.party_transactions || []) if (t.user_id != null) m.set(t.user_id, t.party_name); const e = [...m.entries()].find(([, n]) => /rabbi/i.test(n)) || [...m.entries()].find(([, n]) => /^[A-Z]+\s+[A-Z]{5,}/.test(n)); return e && { id: e[0], name: e[1] }; })();
const word = (n) => String(n).replace(/\([^)]*\)/g, ' ').split(/\s+/).filter((w) => /^[A-Za-z]{4,}$/.test(w) && !/^(MR|MRS|MST)$/i.test(w)).pop();
const once = (rows, k) => { const c = {}; rows.forEach((r) => r[k] && (c[r[k]] = (c[r[k]] || 0) + 1)); return rows.find((r) => r[k] && c[r[k]] === 1 && /\s/.test(r[k])); };
const live = (DS.ticket_index || []).filter((r) => !['cancelled', 'void'].includes(r.status));
const pax1 = once(live, 'passenger');
const pax2 = live.find((r) => r.passenger && pax1 && r.passenger !== pax1.passenger && once([r], 'passenger') && (live.filter((x) => x.passenger === r.passenger).length === 1));
const file1 = once(DS.contract_files || [], 'applicant');
const visa1 = (DS.visa_processes || []).find((v) => v.status === 'approved' && v.applicant && (DS.visa_processes.filter((x) => x.applicant === v.applicant).length === 1));
const typo = (w) => w.length >= 5 ? w.slice(0, -2) + w.slice(-1) : w + w.slice(-1);   // drop one letter: RABBI → RABI

const D = [];
const dialogue = (title, steps) => D.push({ title, steps });
const href = (re) => (o) => !!o.r && re.test(o.r.navigate || '');
const speaks = (re) => (o) => !!o.r && re.test(o.r.speak);
const plan = (want) => (o) => !!o.plan && Object.entries(want).every(([k, v]) => String(o.plan.values[k]) === String(v));
const note = (re) => (o) => !!o.plan && (o.r.detail || []).some((d) => re.test(d));

if (party) {
  const w = word(party.name).toLowerCase();
  const id = party.id;
  dialogue('statement follow-ups', [
    [`open ${w}'s statement`, href(new RegExp(`party_id=${id}$`))],
    ['pdf for october', href(new RegExp(`export/pdf\\?party_id=${id}&from=\\d{4}-10-01`))],
    ['now excel', href(new RegExp(`export/excel\\?party_id=${id}`))],
    ['print it', href(new RegExp(`export/pdf\\?party_id=${id}`))],
  ]);
  dialogue('statement follow-ups in Bangla', [
    [`${w} er statement dekhao`, href(new RegExp(`party_id=${id}$`))],
    ['তার অক্টোবরের পিডিএফ দাও', href(new RegExp(`export/pdf\\?party_id=${id}&from=\\d{4}-10-01`))],
  ]);
  dialogue('a near miss on the name', [[`open ${typo(w)} statement`, (o) => href(new RegExp(`party_id=${id}$`))(o) && o.r.speak.includes(party.name)]]);
  dialogue('the company ledger does not take a party from context', [
    [`open ${w}'s statement`, href(/party_id=/)],
    ['print the company ledger', href(/company\?print=1/)],
  ]);
}
if (pax1) {
  const n = pax1.passenger;
  dialogue('refund, then change of mind', [
    [`refund ${n} ticket`, speaks(/refund to the customer|ফেরত দেব/)],
    ['cancel', speaks(/nothing was posted/)],
    ['actually void it', plan({ kind: 'void' })],
  ]);
  dialogue('a travel date already gone is asked again', [
    [`reissue ${n} ticket`, speaks(/travel date/)],
    ['2020-01-15', speaks(/already passed/)],
    ['2099-01-15', speaks(/penalty/)],
  ]);
  dialogue('refund larger than the sale is called out', [
    [`refund ${n} ticket, refund 99999999, customer bears`, (o) => !!o.plan || /penalty|bears/.test(o.r ? o.r.speak : '')],
  ].concat(+pax1.sale_price > 0 ? [] : []));
  if (+pax1.sale_price > 0) D[D.length - 1].steps = [[`refund ${n} ticket, refund ${Math.round(+pax1.sale_price * 2)}, penalty 0, service fee 0`, note(/more than the ticket sold|বিক্রয়মূল্য/)]];
  if (pax2) dialogue('same for someone else', [
    [`void ${n} ticket`, plan({ kind: 'void' })],
    ['no', () => true],
    [`same for ${pax2.passenger}`, (o) => plan({ kind: 'void' })(o) && o.r.speak.includes(pax2.passenger)],
  ]);
}
if (file1) {
  const n = file1.applicant;
  dialogue('a case, then "its"', [
    [`open ${n}'s contract file`, href(/contract-files\/\d+\/details/)],
    ['which stage is it at', speaks(/is at/)],
    ['তার ফাইল কোন অবস্থায়', speaks(/অবস্থায়/)],
  ]);
  if (file1.status && file1.status !== 'file_received') dialogue('a file moved backwards is said', [[`move ${n} file to file received`, note(/backwards|পিছিয়ে/)]]);
  else dialogue('a file moved forward carries no warning', [[`move ${n} file to documents verified`, (o) => plan({ stage: 'documents_verified' })(o) && !(o.r.detail || []).some((d) => /backwards/.test(d))]]);
}
if (visa1) dialogue('reversing a visa decision is said', [[`mark ${visa1.applicant} visa rejected`, note(/reverses|উল্টে/)]]);

/* ---------- run ---------- */
let pass = 0, total = 0, steps = 0, stepOk = 0;
for (const d of D) {
  ctx.forgetAll(); act.cancel(); ticket.forget(); cases.forget();
  let ok = true; const log = [];
  for (const [q, check] of d.steps) {
    if (q === 'no') { act.cancel(); log.push(`   you: no`); continue; }
    const o = await say(q);
    let good = false; try { good = !!check(o); } catch { good = false; }
    steps++; if (good) stepOk++; else ok = false;
    log.push(`   ${good ? ' ' : '✗'} you: ${q}\n     EON: ${o.r ? o.r.speak.slice(0, 160) : '(nobody)'} ${o.r && o.r.navigate ? o.r.navigate.replace('https://erp', '') : ''}`);
  }
  total++; if (ok) pass++;
  console.log(`${ok ? 'ok  ' : 'FAIL'} ${d.title}`);
  if (!ok || verbose) console.log(log.join('\n'));
}
console.log('-'.repeat(48));
console.log(`DIALOGUES ${pass}/${total}   STEPS ${stepOk}/${steps}`);
process.exit(pass === total ? 0 : 1);
