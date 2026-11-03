/* ============================================================
   EON · semantic — understanding by meaning, not by keyword.

   When EON's own brain only half-understood a question, this finds the
   question it *does* know that means the same thing — in English,
   বাংলা or Banglish, whatever the wording — and EON answers that one,
   saying what it took the question to be.

   The model (multilingual-e5-small, open source, ~120 MB) runs in the
   browser, fetched once in the background and kept by the browser's
   cache. The bank of known questions is embedded ahead of time by
   tools/make-semantic-bank.mjs. Until both are ready, nothing changes.
   ============================================================ */
const LIB = 'https://cdn.jsdelivr.net/npm/@huggingface/transformers@3.5.1/+esm';
const THRESHOLD = 0.86;   // e5 scores run high; below this the "nearest" question is a different question

let bank = null, embed = null, loading = null;

function load() {
  if (loading) return loading;
  loading = Promise.all([
    fetch(new URL('semantic-bank.json', import.meta.url)).then((r) => r.json()),
    import(/* @vite-ignore */ LIB),
  ]).then(async ([b, T]) => {
    embed = await T.pipeline('feature-extraction', b.model, { dtype: 'q8' });
    // the bank's vectors: built once in this browser, then cached (int8, ~250 KB)
    const KEY = 'eon_sem_' + b.version;
    let cached = null; try { cached = localStorage.getItem(KEY); } catch (e) {}
    if (cached) b.v = new Int8Array(Uint8Array.from(atob(cached), (c) => c.charCodeAt(0)).buffer);
    else {
      b.v = new Int8Array(b.items.length * b.dim);
      for (let i = 0; i < b.items.length; i += 16) {
        const batch = b.items.slice(i, i + 16).map((x) => b.prefix + x.q);
        const d = (await embed(batch, { pooling: 'mean', normalize: true })).data;
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

/** the known question nearest in meaning, or null when none is near enough */
async function match(q) {
  if (!bank || !embed) return null;
  const out = await embed(bank.prefix + q, { pooling: 'mean', normalize: true });
  const e = out.data, dim = bank.dim, v = bank.v;
  let best = -1, at = -1;
  for (let i = 0; i < bank.items.length; i++) {
    let s = 0; const o = i * dim;
    for (let j = 0; j < dim; j++) s += e[j] * v[o + j];
    if (s > best) { best = s; at = i; }
  }
  best /= 127;
  return best >= THRESHOLD ? Object.assign({ score: +best.toFixed(3) }, bank.items[at]) : null;
}

const EonSemantic = { load, match, ready: () => !!(bank && embed), threshold: THRESHOLD };
window.EonSemantic = EonSemantic;
setTimeout(load, 4000);
export default EonSemantic;
