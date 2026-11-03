/* ============================================================
   EON · make-semantic-bank — the questions EON already answers, the
   bank app/semantic.js matches a differently-worded question against.

   Reads tools/qa-corpus.json (English, বাংলা, Banglish) and keeps up to
   PER distinct phrasings per intent per language. The browser embeds
   them with multilingual-e5-small on first use and caches the vectors,
   so no model is needed here.

     node tools/make-semantic-bank.mjs
   ============================================================ */
import { readFileSync, writeFileSync } from 'node:fs';

const ROOT = new URL('..', import.meta.url);
const PER = 6;
const corpus = JSON.parse(readFileSync(new URL('tools/qa-corpus.json', ROOT), 'utf8'));
const all = Object.values(corpus).flat().filter((x) => x && x.q && x.intent);
const seen = new Set(), count = {}, items = [];
for (const x of all) {
  const key = x.q.toLowerCase().replace(/\s+/g, ' ').trim();
  const slot = x.intent + '|' + x.lang;
  if (seen.has(key) || (count[slot] || 0) >= PER) continue;
  seen.add(key); count[slot] = (count[slot] || 0) + 1;
  items.push({ q: x.q, intent: x.intent, lang: x.lang });
}
writeFileSync(new URL('app/semantic-bank.json', ROOT), JSON.stringify({
  model: 'Xenova/multilingual-e5-small', dim: 384, prefix: 'query: ', version: items.length + '-' + PER, items,
}));
console.log(items.length, 'questions,', Object.keys(count).length, 'intent×language slots');
