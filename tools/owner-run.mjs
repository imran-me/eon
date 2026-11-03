/* ============================================================
   EON · owner-run — the owner's own 2,824 questions (tools/owner-questions.json,
   from EON_Chatbot_Question_Variants), put to the live EON.

   A question fails when EON did not understand it (weak answer, apology,
   "I did not catch"), answered about the table instead of the business, or
   answered a Bangla/Banglish question in English. Correctness of the figure
   is not judged here — that is what `expected_response` is for, by hand.

     node tools/owner-run.mjs --cookie "laravel_session=…; XSRF-TOKEN=…" [--type Navigate] [--limit N]
   ============================================================ */
import { readFileSync, writeFileSync } from 'node:fs';
const ROOT = new URL('..', import.meta.url);
const arg = (k) => { const i = process.argv.indexOf(k); return i > 0 ? process.argv[i + 1] : null; };
const cookie = arg('--cookie') || '';
const only = arg('--type'); const limit = +(arg('--limit') || 0);
let rows = JSON.parse(readFileSync(new URL('tools/owner-questions.json', ROOT), 'utf8'));
if (only) rows = rows.filter((r) => r.type === only);
if (limit) rows = rows.filter((_, i) => i % Math.ceil(rows.length / limit) === 0);
const APOLOGY = /did not catch|ধরতে পারিনি|i do not know|জানি না|not sure which|বুঝতে পারিনি/i;
const out = []; const by = {};
for (const r of rows) {
  let j = null;
  try { const res = await fetch('https://eon.gulfrabit.com/server/api/ask.php', { method: 'POST', headers: { 'Content-Type': 'application/json', Cookie: cookie }, body: JSON.stringify({ question: r.q }) }); j = await res.json(); } catch (e) { j = { text: 'ERR ' + e.message }; }
  const t = String(j.text || '');
  const wantBn = r.lang !== 'English';
  const why = j.weak || APOLOGY.test(t) ? 'not understood' : /^schema/.test(j.intent || '') ? 'answered about tables' : wantBn && !/\p{Script=Bengali}/u.test(t) ? 'answered in English' : null;
  const k = r.type; by[k] = by[k] || [0, 0]; by[k][1]++; if (!why) by[k][0]++;
  out.push({ id: r.id, type: r.type, module: r.module, intent: r.intent, q: r.q, got: j.intent || '', why, answer: t.slice(0, 300) });
}
for (const [k, [a, b]] of Object.entries(by).sort((x, y) => y[1][1] - x[1][1])) console.log(k.padEnd(12), `${a}/${b}`, (100 * a / b).toFixed(1) + '%');
const ok = out.filter((x) => !x.why).length;
console.log(`TOTAL ${ok}/${out.length} ${(100 * ok / out.length).toFixed(1)}%`);
writeFileSync(new URL('tools/owner-report.json', ROOT), JSON.stringify(out, null, 1));
