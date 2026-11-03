// EON · voice-persona.mjs — the browser half of tools/voice-persona-run.php.
//   node tools/voice-persona.mjs
import { humanize, warmKind, resetWarmth, phrases } from '../ai-companion/eon-brain/voice.js';

let pass = 0, fail = 0;
const check = (name, ok, d = '') => { if (ok) pass++; else { fail++; console.log('FAIL ', name, d); } };
const isBn = (s) => /[ঀ-৿]/.test(s);

const names = ['Tanvir Ahmed', 'ECN RABBI', 'Rashedul Islam', 'BANGLA AIR'];
const bnNames = ['তানভীর আহমেদ', 'রাশেদুল ইসলাম', 'শফিকুল ইসলাম'];
const all = [];
for (let i = 0; i < 150; i++) {
  all.push(`${names[i % 4]} has net pay of thirty-two thousand ${i + 1} taka for July, paid on time.`);
  all.push(`Receivables of one lakh ${i + 7} thousand taka are overdue from ${names[i % 4]}, oldest ninety days.`);
  all.push(`${bnNames[i % 3]} এর জুলাই মাসের বেতন বত্রিশ হাজার টাকা, মোট ${i} জন।`);
  all.push(`এই মাসে খরচ হয়েছে এক লাখ বিশ হাজার টাকা, গত মাসের চেয়ে কম। হিসাব নম্বর ${i}।`);
}
resetWarmth(); const a = all.map((t) => humanize(t));
resetWarmth(); const b = all.map((t) => humanize(t));
check('deterministic', JSON.stringify(a) === JSON.stringify(b));
const rate = a.filter((x, i) => x !== all[i]).length / all.length;
check('frequency band 30–85%', rate >= 0.3 && rate <= 0.85, rate.toFixed(2));
let prev = null;
a.forEach((o, i) => {
  const t = all[i];
  check('intact ' + i, o.includes(t), o);
  check('idempotent ' + i, humanize(o) === o, o);
  if (isBn(t)) check('bn pure ' + i, !/[A-Za-z]/.test(o), o);
  else check('en pure ' + i, !isBn(o), o);
  if (o !== t && o.indexOf(t) > 0) { const op = o.slice(0, o.indexOf(t)); check('no repeat ' + i, op !== prev, op); prev = op; }
});
for (const g of ['Say yes and I will post it.', 'Shall I assign Imran the task: check ledger entries?', 'I cannot pay that supplier without a bank account.',
                 'তানভীরকে ত্রিশ হাজার টাকা দেব। হ্যাঁ বললে পোস্ট করব।', 'ব্যাংক অ্যাকাউন্ট ছাড়া এই পেমেন্ট করতে পারছি না।']) {
  check('gate ' + g.slice(0, 20), humanize(g) === g && warmKind(g) === 'gate');
}
for (const t of ['Short version: November came out with a profit of nine lakh taka.', 'একটা বিষয় বলি, জুলাই মাসের পে-রোল এগারোটা স্লিপ।'])
  for (let v = 0; v < 10; v++) { const s = t + ' ' + v; check('lead-in ' + v, humanize(s).startsWith(s)); }
check('kind ack', warmKind('Done — the task is assigned to Imran.') === 'ack');
check('kind soft bn', warmKind('তিনটি ইনভয়েস বকেয়া আছে।') === 'soft');
const ph = phrases('Hmm, let me see… Profit is nine lakh — up two. লাভ নয় লাখ। Done.');
check('phrases split at breaths', ph.length === 5, JSON.stringify(ph));
check('phrases keep every word', ph.join(' ').replace(/\s+/g, ' ') === 'Hmm, let me see… Profit is nine lakh — up two. লাভ নয় লাখ। Done.', JSON.stringify(ph));
console.log(`voice persona (browser): ${pass}/${pass + fail}`);
process.exit(fail ? 1 : 0);
