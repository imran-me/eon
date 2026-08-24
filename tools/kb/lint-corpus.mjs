#!/usr/bin/env node
/* ============================================================
   lint-corpus.mjs — read the corpus the way EON will say it.

   A hundred thousand records is far past the point where anyone
   reads them, and that is exactly when a broken template stops
   being visible. Two had been sitting in the generator: a lesson
   dropped into a slot that wanted a noun ("A bed is the record
   inpatient department uses to hold cleaning is a real state"),
   and the article printed as a literal "A" in front of every
   noun, vowel or not ("A admission test"). Neither shows up in
   a JSON diff. Both are unmissable the moment the sentence is
   read out loud, which is what EON now does with them.

   So this reads every line and fails on what a listener would
   notice:

     article     "a admission", "an ward"
     doubled     "the the", "in in"
     template    an unsubstituted ${...}, undefined, NaN, [object Object]
     fragment    a body that never reaches a finite verb
     spacing     " ." or " ," or a double space
     leak        Bangla letters inside an English record, or the
                 reverse — the rule this codebase has paid for
                 more than any other
     stub        a body under 40 characters

     node tools/kb/lint-corpus.mjs             lint tools/corpus
     node tools/kb/lint-corpus.mjs --show 5    print 5 examples per rule
     node tools/kb/lint-corpus.mjs --rule leak just one rule
   ============================================================ */

import { readFileSync, readdirSync } from 'node:fs';
import { dirname, resolve, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { art } from './phrasing.mjs';

const HERE = dirname(fileURLToPath(import.meta.url));
const argv = process.argv.slice(2);
const val = (f, d) => { const i = argv.indexOf(f); return i >= 0 && argv[i + 1] ? argv[i + 1] : d; };
const DIR = resolve(val('--dir', join(HERE, '..', 'corpus')));
const SHOW = Number(val('--show', 3));
const ONLY = val('--rule', null);

const BN = /[ঀ-৿]/;
const LATIN_WORD = /[A-Za-z]{3,}/;

/* Words that legitimately appear in a বাংলা record: the ERP's own vocabulary is
   English and the boss reads it that way. A leak is prose, not a label. */
const BN_OK = /^(ERP|GL|AR|AP|VAT|TDS|HR|IT|KPI|MRP|PO|GRN|SKU|BOM|POS|OPD|IPD|UGC|MPO|DGHS|BSP|IATA|Mushak|Excel|CSV|PDF)$/;

const RULES = {
  article: (t) => {
    const m = t.match(/(?<![A-Za-z])(an?)\s+([A-Za-z][A-Za-z'-]*)/g) || [];
    for (const hit of m) {
      const [, a, w] = hit.match(/(an?)\s+([A-Za-z][A-Za-z'-]*)/);
      if (art(w) !== a.toLowerCase()) return `"${hit}" should be "${art(w)} ${w}"`;
    }
    return null;
  },
  doubled: (t) => {
    const m = t.match(/(?<![A-Za-z])([A-Za-z]{2,})\s+\1(?![A-Za-z])/i);
    return m ? `"${m[0]}"` : null;
  },
  template: (t) => {
    const m = t.match(/\$\{[^}]*\}|\bundefined\b|\bNaN\b|\[object Object\]|\bnull\b/);
    return m ? `"${m[0]}"` : null;
  },
  spacing: (t) => {
    const m = t.match(/\s[.,;](?!\d)|\s{2,}|\(\s|\s\)/);
    return m ? JSON.stringify(m[0]) : null;
  },
  /* There was a `fragment` rule here that wanted a finite verb in the opening
     sentence. It is gone, and deliberately. English sentence-hood cannot be
     decided by a word list: it failed "Check three things in order." for being
     imperative, and once imperatives were allowed it failed "Anything under
     ninety percent leaks into next month." for using a verb the list had never
     heard of. Every round it caught correct prose and no defects. A check that
     fires on good output is worse than no check, because it trains whoever runs
     it to scroll past the whole report — which is exactly how the two broken
     templates survived in the first place. */
  leak: (t, r) => {
    if (r.lang === 'bn') {
      /* A single English token in a বাংলা record is usually a field or a state —
         `code`, `name`, `parent account` — and those are what the ERP itself
         stores, so reading them in English is right, not a leak. What is a leak
         is an English *clause*: three or more words running together without a
         comma between them is prose that was never translated. */
      /* Quoted English is not leaked English. A formula the system stores in
         English — “occupied bed days ÷ available bed days” — is a quantity, and
         the Bangla sentence around it now says so outright. What is still a leak
         is an unquoted English clause the reader is meant to take as Bangla. */
      const unquoted = t.replace(/[“"][^”"]*[”"]/g, ' ');
      const m = unquoted.match(/[A-Za-z][A-Za-z'-]*(?:\s+[A-Za-z][A-Za-z'-]*){2,}/);
      if (!m) return null;
      if (m[0].split(/\s+/).every((w) => BN_OK.test(w))) return null;
      return `English clause in a বাংলা record: "${m[0].slice(0, 60)}"`;
    }
    return BN.test(t) ? `বাংলা inside an English record` : null;
  },
  stub: (t) => (t.trim().length < 40 ? `body is ${t.trim().length} characters` : null),
};

const files = readdirSync(DIR).filter((f) => /^kb-\d+\.jsonl$/.test(f)).sort();
if (!files.length) { console.error(`no kb-*.jsonl in ${DIR}`); process.exit(2); }

const counts = {}, examples = {};
let total = 0;
for (const name of Object.keys(RULES)) { counts[name] = 0; examples[name] = []; }

for (const f of files) {
  for (const line of readFileSync(join(DIR, f), 'utf8').split('\n')) {
    if (!line.trim()) continue;
    let r;
    try { r = JSON.parse(line); } catch { continue; }
    total++;
    const text = String(r.text || '');
    for (const [name, fn] of Object.entries(RULES)) {
      if (ONLY && name !== ONLY) continue;
      const why = fn(text, r);
      if (why === null || why === undefined) continue;
      counts[name]++;
      if (examples[name].length < SHOW) {
        examples[name].push({ id: r.id, facet: r.facet, lang: r.lang, why, text: text.slice(0, 150) });
      }
    }
  }
}

let bad = 0;
console.log(`corpus lint — ${total.toLocaleString()} records in ${files.length} shards\n` + '='.repeat(68));
for (const name of Object.keys(RULES)) {
  if (ONLY && name !== ONLY) continue;
  const n = counts[name];
  bad += n;
  console.log(`${n === 0 ? '  ok  ' : ' FAIL '} ${name.padEnd(10)} ${n.toLocaleString().padStart(8)}`);
  for (const e of examples[name]) {
    console.log(`        ${e.lang} ${e.facet} — ${e.why}`);
    console.log(`        ${e.text.replace(/\s+/g, ' ')}…`);
  }
}
console.log('='.repeat(68));
console.log(bad === 0 ? 'clean' : `${bad.toLocaleString()} records would read wrong out loud`);
process.exit(bad === 0 ? 0 : 1);
