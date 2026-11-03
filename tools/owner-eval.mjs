/* ============================================================
   EON · owner-eval — does EON do what the owner's question asks?

   Samples N questions per intent from the owner's bank (tools/owner-100k.json,
   92 intents), puts each to the live EON, then has Gemini judge the reply
   against the owner's own expected_action / expected_response:
     pass     the reply answers / carries out what was asked
     partial  right subject, but incomplete or a step short
     fail     wrong subject, refused, or did nothing useful
   A "do" reply is judged on the command EON would run (the panel runs it).

     node tools/owner-eval.mjs --cookie "…" [--per 2] [--out tools/owner-eval.json]
   ============================================================ */
import { readFileSync, writeFileSync } from 'node:fs';
const ROOT = new URL('..', import.meta.url);
const arg = (k, d) => { const i = process.argv.indexOf(k); return i > 0 ? process.argv[i + 1] : d; };
const cookie = arg('--cookie', ''); const per = +arg('--per', 2);
const KEY = readFileSync(new URL('server/storage/gemini.key', ROOT), 'utf8').trim();
const bank = JSON.parse(readFileSync(new URL('tools/owner-100k.json', ROOT), 'utf8'));

// deterministic stratified sample: per intent, spread across the list
const byIntent = {}; for (const r of bank) (byIntent[r.intent] = byIntent[r.intent] || []).push(r);
const sample = []; for (const [k, rows] of Object.entries(byIntent)) for (let i = 0; i < per; i++) sample.push(rows[Math.floor((i + 0.5) * rows.length / per)]);

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
async function gemini(system, user) {
  for (let t = 0; t < 4; t++) {
    const res = await fetch('https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite:generateContent', { method: 'POST', headers: { 'Content-Type': 'application/json', 'x-goog-api-key': KEY },
      body: JSON.stringify({ systemInstruction: { parts: [{ text: system }] }, contents: [{ role: 'user', parts: [{ text: user }] }], generationConfig: { responseMimeType: 'application/json', temperature: 0 } }) });
    if (res.status === 429 || res.status >= 500) { await sleep(6000 * (t + 1)); continue; }
    const j = await res.json(); try { return JSON.parse(j.candidates[0].content.parts[0].text); } catch { return null; }
  }
  return null;
}
const JUDGE = 'You grade an ERP assistant for a Bangladeshi travel group. Given the boss question, what the owner expects (action + response), and what the assistant did, reply JSON {"grade":"pass|partial|fail","why":"<one short reason in English>"}. pass = it answered or carried out what was asked (exact figures may differ from the example — the example response is illustrative; judge the SUBJECT, the BREAKDOWN asked, and the ACTION). partial = right subject but missing the breakdown/filter/step asked, or asks a sensible clarifying question. fail = wrong subject, refusal, generic menu/apology, or nothing done.';

const out = []; const tally = {};
for (const r of sample) {
  let j = {};
  try { const res = await fetch('https://eon.gulfrabit.com/server/api/ask.php', { method: 'POST', headers: { 'Content-Type': 'application/json', Cookie: cookie }, body: JSON.stringify({ question: r.q }) }); j = await res.json(); } catch (e) { j = { text: 'ERROR ' + e.message }; }
  const did = j.client_command ? `[will run command] ${j.client_command}` : j.navigate_query ? `[opens screen] ${j.navigate_query}` : `${j.text || ''}`.slice(0, 700);
  const g = await gemini(JUDGE, `Question: ${r.q}\nIntent: ${r.intent} (${r.type})\nOwner expects action: ${r.action}\nOwner expects response like: ${r.response}\n\nAssistant did (${j.intent || '?'}): ${did}`) || { grade: 'fail', why: 'judge unavailable' };
  out.push({ id: r.id, intent: r.intent, type: r.type, module: r.module, q: r.q, eon: j.intent, did, grade: g.grade, why: g.why });
  const k = r.intent; tally[k] = tally[k] || { pass: 0, partial: 0, fail: 0 }; tally[k][g.grade] = (tally[k][g.grade] || 0) + 1;
  await sleep(1500);
}
const tot = { pass: 0, partial: 0, fail: 0 }; for (const x of out) tot[x.grade] = (tot[x.grade] || 0) + 1;
const rows = Object.entries(tally).map(([k, v]) => [k, v.pass, v.partial, v.fail]).sort((a, b) => b[3] - a[3]);
for (const [k, p, pa, f] of rows) console.log(`${String(p).padStart(2)} ✓ ${String(pa).padStart(2)} ~ ${String(f).padStart(2)} ✗  ${k}`);
console.log(`TOTAL pass ${tot.pass} · partial ${tot.partial} · fail ${tot.fail} of ${out.length}  →  ${(100 * tot.pass / out.length).toFixed(1)}% pass, ${(100 * (tot.pass + tot.partial) / out.length).toFixed(1)}% pass+partial`);
writeFileSync(new URL(arg('--out', 'tools/owner-eval.json'), ROOT), JSON.stringify(out, null, 1));
