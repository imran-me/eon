/* ============================================================
   EON · train-intent — EON's own intent model, learned from the owner's
   labelled questions (tools/owner-100k.json: 100,000 questions, 92 intents).

   Features: whole words, word pairs and letter trigrams (so a misspelling,
   a case ending or Banglish still lands near its word), digits folded.
   Model: a multiclass averaged perceptron — a linear classifier trained on
   every question, pushed hardest on the pairs it confuses — then pruned to
   the weights that matter. Runs in PHP on the server
   (server/lib/IntentModel.php) in milliseconds — no Google, no quota.

   10% of the questions are held back and never trained on; the accuracy
   printed is on those alone.

     node --max-old-space-size=6144 tools/train-intent.mjs [--epochs 8] [--keep 1500]
   ============================================================ */
import { readFileSync, writeFileSync } from 'node:fs';
const ROOT = new URL('..', import.meta.url);
const arg = (k, d) => { const i = process.argv.indexOf(k); return i > 0 ? +process.argv[i + 1] : d; };
const EPOCHS = arg('--epochs', 8), KEEP = arg('--keep', 1500);

// --- features: must match server/lib/IntentModel.php exactly ---
export function features(q) {
  const t = String(q).toLowerCase().normalize('NFC').replace(/[০-৯]/g, (d) => '০১২৩৪৫৬৭৮৯'.indexOf(d)).replace(/\d+/g, '#');
  const words = t.split(/[^\p{L}\p{M}#]+/u).filter(Boolean);
  const f = new Set();
  for (const w of words) {
    f.add('w:' + w);
    const cs = [...('<' + w + '>')];
    for (let i = 0; i + 3 <= cs.length; i++) f.add('c:' + cs.slice(i, i + 3).join(''));
  }
  for (let i = 0; i + 1 < words.length; i++) f.add('b:' + words[i] + '_' + words[i + 1]);
  return [...f];
}

const rows = JSON.parse(readFileSync(new URL('tools/owner-100k.json', ROOT), 'utf8'));
const train = rows.filter((_, i) => i % 10 !== 0), test = rows.filter((_, i) => i % 10 === 0);
const intents = [...new Set(rows.map((r) => r.intent))];
const X = train.map((r) => features(r.q)), Y = train.map((r) => intents.indexOf(r.intent));

// averaged perceptron, sparse: W[class] Map(feature → weight), with lazy averaging
const W = intents.map(() => new Map()), U = intents.map(() => new Map());   // U accumulates c·update for averaging
let c = 1;
const dot = (m, fs) => { let s = 0; for (const f of fs) { const x = m.get(f); if (x) s += x; } return s; };
const order = [...X.keys()];
let seed = 7; const rnd = () => ((seed = (seed * 1103515245 + 12345) % 2147483648) / 2147483648);
for (let e = 0; e < EPOCHS; e++) {
  for (let i = order.length - 1; i > 0; i--) { const j = Math.floor(rnd() * (i + 1)); [order[i], order[j]] = [order[j], order[i]]; }
  let err = 0;
  for (const i of order) {
    const fs = X[i], y = Y[i];
    let best = -1, bs = -Infinity;
    for (let k = 0; k < W.length; k++) { const s = dot(W[k], fs); if (s > bs) { bs = s; best = k; } }
    if (best !== y) {
      err++;
      for (const f of fs) {
        W[y].set(f, (W[y].get(f) || 0) + 1); U[y].set(f, (U[y].get(f) || 0) + c);
        W[best].set(f, (W[best].get(f) || 0) - 1); U[best].set(f, (U[best].get(f) || 0) - c);
      }
    }
    c++;
  }
  console.log(`epoch ${e + 1}: training errors ${err} (${(100 * err / order.length).toFixed(2)}%)`);
}
// averaged weights w - u/c, pruned to the strongest per class
const model = { version: new Date().toISOString().slice(0, 10), kind: 'averaged-perceptron', trained_on: train.length, intents: {} };
let kept = 0;
W.forEach((m, k) => {
  const avg = []; for (const [f, w] of m) { const a = w - (U[k].get(f) || 0) / c; if (Math.abs(a) > 1e-6) avg.push([f, a]); }
  avg.sort((a, b) => Math.abs(b[1]) - Math.abs(a[1]));
  model.intents[intents[k]] = Object.fromEntries(avg.slice(0, KEEP).map(([f, a]) => [f, +a.toFixed(3)]));
  kept += Math.min(KEEP, avg.length);
});

const score = (q) => { const fs = features(q); return Object.entries(model.intents).map(([k, w]) => { let s = 0; for (const f of fs) if (w[f]) s += w[f]; return [k, s]; }).sort((a, b) => b[1] - a[1]); };
let ok = 0, top3 = 0; const conf = {};
for (const r of test) { const s = score(r.q); if (s[0][0] === r.intent) ok++; else conf[r.intent + ' → ' + s[0][0]] = (conf[r.intent + ' → ' + s[0][0]] || 0) + 1; if (s.slice(0, 3).some((x) => x[0] === r.intent)) top3++; }
console.log(`held-out ${test.length}: top-1 ${(100 * ok / test.length).toFixed(1)}%  top-3 ${(100 * top3 / test.length).toFixed(1)}%`);
console.log('most confused:', Object.entries(conf).sort((a, b) => b[1] - a[1]).slice(0, 8));
writeFileSync(new URL('server/kb/intent-model.json', ROOT), JSON.stringify(model));
console.log('weights kept', kept, '· model bytes', JSON.stringify(model).length);
