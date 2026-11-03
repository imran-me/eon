/* ============================================================
   EON · calibrate-intent — when may EON trust its own model?

   Scores the held-out tenth of the owner's questions (never trained on) and
   finds the smallest lead over the runner-up at which the model is right at
   least TARGET of the time; writes it into server/kb/intent-model.json as
   confident_margin. Below it, EON asks Gemini / the rules instead.

     node tools/calibrate-intent.mjs [--target 0.97]
   ============================================================ */
import { readFileSync, writeFileSync } from 'node:fs';
import { features } from './intent-features.mjs';
const ROOT = new URL('..', import.meta.url);
const i = process.argv.indexOf('--target'); const TARGET = i > 0 ? +process.argv[i + 1] : 0.97;
const model = JSON.parse(readFileSync(new URL('server/kb/intent-model.json', ROOT), 'utf8'));
const rows = JSON.parse(readFileSync(new URL('tools/owner-100k.json', ROOT), 'utf8')).filter((_, k) => k % 10 === 0);
const res = rows.map((r) => {
  const fs = features(r.q);
  const s = Object.entries(model.intents).map(([k, w]) => { let x = 0; for (const f of fs) if (w[f]) x += w[f]; return [k, x]; }).sort((a, b) => b[1] - a[1]);
  return { ok: s[0][0] === r.intent, margin: s[0][1] - s[1][1] };
}).sort((a, b) => b.margin - a.margin);
let good = 0, pick = null;
res.forEach((r, k) => { if (r.ok) good++; const prec = good / (k + 1); if (prec >= TARGET) pick = { margin: r.margin, coverage: (k + 1) / res.length, precision: prec }; });
const all = res.filter((r) => r.ok).length / res.length;
console.log(`overall top-1 ${(100 * all).toFixed(1)}% · trusted when margin ≥ ${pick.margin.toFixed(3)}: covers ${(100 * pick.coverage).toFixed(1)}% of questions at ${(100 * pick.precision).toFixed(1)}% precision`);
model.confident_margin = +pick.margin.toFixed(4); model.calibration = { target: TARGET, coverage: +pick.coverage.toFixed(4), precision: +pick.precision.toFixed(4), overall: +all.toFixed(4) };
writeFileSync(new URL('server/kb/intent-model.json', ROOT), JSON.stringify(model));
