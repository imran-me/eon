/* ============================================================
   EON · semantic — understanding by meaning, not by keyword.

   When EON's own brain only half-understood a question, this finds the
   question it *does* know that means the same thing — in English,
   বাংলা or Banglish, whatever the wording. A clear winner is answered;
   two subjects neck and neck are put back to the boss as a choice.

   The model (multilingual-e5-small, open source, ~120 MB) runs in
   app/ml-worker.js, off the page. The bank of known questions
   (tools/make-semantic-bank.mjs) is embedded once in this browser and
   cached. Until both are ready, nothing changes.
   ============================================================ */
import { ml } from './ml.js';

const THRESHOLD = 0.86;   // e5 scores run high; below this the "nearest" question is a different question

let bank = null, loading = null;

function load() {
  if (loading) return loading;
  loading = fetch(new URL('semantic-bank.json', import.meta.url)).then((r) => r.json()).then(async (b) => {
    const KEY = 'eon_sem_' + b.version;
    let cached = null; try { cached = localStorage.getItem(KEY); } catch (e) {}
    if (cached) b.v = new Int8Array(Uint8Array.from(atob(cached), (c) => c.charCodeAt(0)).buffer);
    else {
      b.v = new Int8Array(b.items.length * b.dim);
      for (let i = 0; i < b.items.length; i += 16) {
        const batch = b.items.slice(i, i + 16).map((x) => b.prefix + x.q);
        const d = await ml('embed', { texts: batch });
        for (let j = 0; j < batch.length * b.dim; j++) b.v[i * b.dim + j] = Math.max(-127, Math.min(127, Math.round(d[j] * 127)));
      }
      try { let s = ''; const u = new Uint8Array(b.v.buffer); for (let i = 0; i < u.length; i += 8192) s += String.fromCharCode.apply(null, u.subarray(i, i + 8192)); localStorage.setItem(KEY, btoa(s)); } catch (e) {}
    }
    bank = b;
    console.info('[EON semantic] ready —', b.items.length, 'known questions');
    return true;
  }).catch((e) => { console.warn('[EON semantic] unavailable', e); return false; });
  return loading;
}

/** the nearest known question for each of the n closest *different* subjects — so a caller can
    tell a clear match from two subjects running neck and neck, and ask which one was meant */
async function candidates(q, n = 2) {
  if (!bank) return [];
  const e = await ml('embed', { texts: [bank.prefix + q] }), dim = bank.dim, v = bank.v;
  const best = {};
  for (let i = 0; i < bank.items.length; i++) {
    let s = 0; const o = i * dim;
    for (let j = 0; j < dim; j++) s += e[j] * v[o + j];
    s /= 127;
    const it = bank.items[i];
    if (!best[it.intent] || s > best[it.intent].score) best[it.intent] = Object.assign({ score: +s.toFixed(3) }, it);
  }
  return Object.values(best).sort((a, b) => b.score - a.score).slice(0, n);
}
async function match(q) { const c = await candidates(q, 1); return c[0] && c[0].score >= THRESHOLD ? c[0] : null; }

const EonSemantic = { load, match, candidates, ready: () => !!bank, threshold: THRESHOLD };
window.EonSemantic = EonSemantic;
setTimeout(load, 4000);
export default EonSemantic;
