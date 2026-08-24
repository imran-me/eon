#!/usr/bin/env node
/* ============================================================
   make-questions.mjs — the question bank, one order of magnitude
   past tools/make-qa-corpus.mjs.

   That file asks the Epal director about Epal. This one asks
   every kind of owner about every module their trade runs, in
   English, বাংলা and romanised Bangla, formally and the way it is
   actually typed on a phone — and tags each question with the
   vertical, module, intent and facet it must land on, so coverage
   can be proved rather than asserted.

     node tools/kb/make-questions.mjs             → JSONL shards
     node tools/kb/make-questions.mjs --count     → composition only
     node tools/kb/make-questions.mjs --limit N   → cap the corpus

   Deterministic: no randomness anywhere.
   ============================================================ */

import { writeFileSync, mkdirSync, rmSync, existsSync } from 'node:fs';
import { dirname, resolve, join } from 'node:path';
import { fileURLToPath } from 'node:url';

import { VERTICALS } from './verticals.mjs';
import { CORE_MODULES } from './modules-core.mjs';
import { VERTICAL_MODULES_A } from './modules-vertical-a.mjs';
import { VERTICAL_MODULES_B1 } from './modules-vertical-b1.mjs';
import { VERTICAL_MODULES_B2 } from './modules-vertical-b2.mjs';
import { VERTICAL_MODULES_B3 } from './modules-vertical-b3.mjs';
import { term, makeId } from './phrasing.mjs';

const HERE = dirname(fileURLToPath(import.meta.url));
const argv = process.argv.slice(2);
const COUNT_ONLY = argv.includes('--count');
const LIMIT = (() => { const i = argv.indexOf('--limit'); return i >= 0 ? parseInt(argv[i + 1], 10) : Infinity; })();
const OUT_DIR = (() => { const i = argv.indexOf('--out'); return resolve(i >= 0 && argv[i + 1] ? argv[i + 1] : join(HERE, '..', 'corpus')); })();
const SHARD_SIZE = 20000;

const MODULES = { ...CORE_MODULES, ...VERTICAL_MODULES_A, ...VERTICAL_MODULES_B1, ...VERTICAL_MODULES_B2, ...VERTICAL_MODULES_B3 };
const modulesFor = (v) => [...Object.keys(MODULES).filter((id) => !MODULES[id].onlyFor), ...(v.extra || []).filter((id) => MODULES[id])];

/* ------------------------------------------------------------------
   phrasings — the same question asked the way people really ask it
   ------------------------------------------------------------------ */

/* a measure: "how much / what is / show me …" */
const KPI_FORMS = {
  en: [
    (x) => `what is our ${x}`,
    (x) => `how much is the ${x}`,
    (x) => `show me the ${x}`,
    (x) => `${x}?`,
    (x) => `give me the ${x}`,
    (x) => `what's the ${x} right now`,
    (x) => `how is our ${x} doing`,
    (x) => `${x} this month`,
    (x) => `tell me the ${x}`,
    (x) => `i want to see the ${x}`,
  ],
  bn: [
    (x) => `${x} কত`,
    (x) => `আমাদের ${x} কেমন`,
    (x) => `${x} দেখাও`,
    (x) => `${x} কত হয়েছে`,
    (x) => `${x} বলো`,
    (x) => `${x} জানতে চাই`,
    (x) => `এই মাসের ${x} কত`,
  ],
  bl: [
    (x) => `${x} koto`,
    (x) => `amader ${x} kemon`,
    (x) => `${x} dekhao`,
    (x) => `${x} ktotó hoyeche`,
  ],
};

/* how a thing is done */
const PROCESS_FORMS = {
  en: [
    (x) => `how do I ${x}`,
    (x) => `what is the process for ${x}`,
    (x) => `steps to ${x}`,
    (x) => `explain ${x}`,
    (x) => `how does ${x} work`,
    (x) => `walk me through ${x}`,
    (x) => `what is the procedure for ${x}`,
    (x) => `teach me ${x}`,
  ],
  bn: [
    (x) => `${x} কীভাবে করব`,
    (x) => `${x} এর নিয়ম কী`,
    (x) => `${x} বুঝিয়ে বলো`,
    (x) => `${x} এর ধাপগুলো কী`,
    (x) => `${x} কীভাবে হয়`,
  ],
  bl: [
    (x) => `${x} kivabe korbo`,
    (x) => `${x} er niyom ki`,
    (x) => `${x} bujhiye bolo`,
  ],
};

/* what a record or field is */
const ENTITY_FORMS = {
  en: [
    (x) => `what is a ${x}`,
    (x) => `explain ${x}`,
    (x) => `what does a ${x} contain`,
    (x) => `what fields are on a ${x}`,
    (x) => `tell me about ${x}`,
    (x) => `what is the ${x} used for`,
  ],
  bn: [
    (x) => `${x} কী`,
    (x) => `${x} বলতে কী বোঝায়`,
    (x) => `${x} তে কী কী থাকে`,
    (x) => `${x} কেন লাগে`,
  ],
  bl: [
    (x) => `${x} ki`,
    (x) => `${x} mane ki`,
  ],
};

/* a report */
const REPORT_FORMS = {
  en: [
    (x) => `show me the ${x}`,
    (x) => `open the ${x}`,
    (x) => `i need the ${x}`,
    (x) => `can I get the ${x}`,
    (x) => `${x} please`,
    (x) => `export the ${x}`,
  ],
  bn: [
    (x) => `${x} দেখাও`,
    (x) => `${x} বের করো`,
    (x) => `${x} লাগবে`,
    (x) => `${x} টা দাও`,
  ],
  bl: [
    (x) => `${x} dekhao`,
    (x) => `${x} lagbe`,
  ],
};

/* who may do what */
const PERMISSION_FORMS = {
  en: [
    (r, m) => `can a ${r} ${m}`,
    (r, m) => `is a ${r} allowed to ${m}`,
    (r, m) => `who can ${m}`,
    (r, m) => `should a ${r} be able to ${m}`,
    (r, m) => `what access does a ${r} need for ${m}`,
  ],
  bn: [
    (r, m) => `${r} কি ${m} করতে পারবে`,
    (r, m) => `${m} কে করতে পারে`,
    (r, m) => `${r} এর কী অনুমতি লাগবে ${m} এর জন্য`,
  ],
  bl: [
    (r, m) => `${r} ki ${m} korte parbe`,
    (r, m) => `${m} ke korte pare`,
  ],
};

/* something has gone wrong */
const TROUBLE_FORMS = {
  en: [
    (x) => `why is my ${x} wrong`,
    (x) => `${x} doesn't match`,
    (x) => `the ${x} figure looks wrong`,
    (x) => `${x} is not tallying`,
    (x) => `what should I check if the ${x} is off`,
    (x) => `problem with ${x}`,
  ],
  bn: [
    (x) => `${x} মিলছে না কেন`,
    (x) => `${x} এর হিসাব ভুল মনে হচ্ছে`,
    (x) => `${x} এ গরমিল`,
    (x) => `${x} ঠিক না হলে কী দেখব`,
  ],
  bl: [
    (x) => `${x} milche na keno`,
    (x) => `${x} e gormil`,
  ],
};

/* posting and the ledger */
const POSTING_FORMS = {
  en: [
    (x) => `how does ${x} post to the ledger`,
    (x) => `what is the journal entry for ${x}`,
    (x) => `which accounts does ${x} hit`,
    (x) => `debit and credit for ${x}`,
    (x) => `when does ${x} affect profit`,
  ],
  bn: [
    (x) => `${x} খতিয়ানে কীভাবে ওঠে`,
    (x) => `${x} এর জার্নাল এন্ট্রি কী`,
    (x) => `${x} কোন হিসাবে যায়`,
  ],
  bl: [
    (x) => `${x} khotiyane kivabe othe`,
    (x) => `${x} er journal entry ki`,
  ],
};

/* setup and fit */
const SETUP_FORMS = {
  en: [
    (m, v) => `how do I set up ${m} for a ${v}`,
    (m, v) => `what do I need to configure in ${m} for a ${v}`,
    (m, v) => `does a ${v} need ${m}`,
    (m, v) => `is ${m} useful for a ${v}`,
    (m, v) => `what modules does a ${v} need`,
  ],
  bn: [
    (m, v) => `${v} এর জন্য ${m} কীভাবে সেটআপ করব`,
    (m, v) => `${v} এ কি ${m} লাগে`,
  ],
  bl: [
    (m, v) => `${v} er jonno ${m} kivabe setup korbo`,
  ],
};

/* qualifiers appended to a measure question */
const PERIOD = {
  en: ['', ' today', ' this month', ' last month', ' this year', ' last quarter', ' in June', ' year to date', ' this week', ' last year'],
  bn: ['', ' আজকের', ' এই মাসের', ' গত মাসের', ' এ বছরের', ' গত বছরের', ' জুন মাসের'],
  bl: ['', ' ajker', ' ei maser', ' goto maser'],
};

/* ------------------------------------------------------------------
   build
   ------------------------------------------------------------------ */
const rows = [];
const seen = new Set();
const composition = {};
let id = 0;

/* Rotate through the phrasings instead of multiplying by them. A full
   cross product of forms × periods gave 818k questions of which 57% were
   one measure asked a hundred near-identical ways — volume without
   coverage. The counter is global and never reset, so consecutive
   subjects get different phrasings and the variety lands across the
   corpus rather than inside one cell of it. Still deterministic. */
let rot = 0;
function pickN(arr, n) {
  const out = [];
  for (let i = 0; i < Math.min(n, arr.length * 4); i++) out.push(arr[(rot++) % arr.length]);
  return out;
}

function add(q, meta) {
  if (rows.length >= LIMIT) return;
  const text = q.replace(/\s+/g, ' ').trim();
  if (!text || text.length > 180) return;
  const key = `${meta.vertical}|${text.toLowerCase()}`;
  if (seen.has(key)) return;
  seen.add(key);
  composition[meta.facet] = (composition[meta.facet] || 0) + 1;
  rows.push({
    id: `q-${String(++id).padStart(6, '0')}`,
    q: text,
    lang: meta.lang,
    expect_lang: meta.lang === 'en' ? 'en' : 'bn',
    ...meta,
  });
}

for (const v of VERTICALS) {
  for (const mid of modulesFor(v)) {
    const m = MODULES[mid];
    const meta = { vertical: v.id, module: mid };
    const cycle = term(v, 'cycle'), unit = term(v, 'unit');

    /* --- measures --- */
    for (const k of m.kpis) {
      for (const lang of ['en', 'bn', 'bl']) {
        const name = lang === 'bn' ? k.bn : k.en;
        const forms = pickN(KPI_FORMS[lang], 4);
        const sufs = pickN(PERIOD[lang], 4);
        forms.forEach((form, i) => {
          add(form(name) + sufs[i], { ...meta, lang, facet: 'kpi', intent: 'kpi_value', subject: k.id, answer_facet: 'kpi_definition' });
        });
      }
      /* how to read it, and what moves it */
      add(`how should I read the ${k.en}`, { ...meta, lang: 'en', facet: 'kpi_reading', intent: 'kpi_explain', subject: k.id, answer_facet: 'kpi_reading' });
      add(`what does the ${k.en} tell me`, { ...meta, lang: 'en', facet: 'kpi_reading', intent: 'kpi_explain', subject: k.id, answer_facet: 'kpi_reading' });
      add(`how do I improve our ${k.en}`, { ...meta, lang: 'en', facet: 'kpi_action', intent: 'kpi_improve', subject: k.id, answer_facet: 'kpi_action' });
      add(`why did the ${k.en} change`, { ...meta, lang: 'en', facet: 'kpi_reading', intent: 'kpi_explain', subject: k.id, answer_facet: 'kpi_reading' });
      add(`how is ${k.en} calculated`, { ...meta, lang: 'en', facet: 'kpi', intent: 'kpi_formula', subject: k.id, answer_facet: 'kpi_definition' });
      add(`${k.bn} কীভাবে হিসাব হয়`, { ...meta, lang: 'bn', facet: 'kpi', intent: 'kpi_formula', subject: k.id, answer_facet: 'kpi_definition' });
      add(`${k.bn} কীভাবে বাড়াব`, { ...meta, lang: 'bn', facet: 'kpi_action', intent: 'kpi_improve', subject: k.id, answer_facet: 'kpi_action' });
    }

    /* --- processes --- */
    for (const p of m.processes) {
      for (const lang of ['en', 'bn', 'bl']) {
        const name = lang === 'bn' ? (p.bn || p.en) : p.en;
        for (const form of pickN(PROCESS_FORMS[lang], 2)) {
          add(form(name), { ...meta, lang, facet: 'process', intent: 'how_to', subject: p.id, answer_facet: 'process' });
        }
      }
      add(`what is the first step in ${p.en}`, { ...meta, lang: 'en', facet: 'process_step', intent: 'how_to', subject: p.id, answer_facet: 'process_step' });
      add(`what comes after ${p.steps[0]}`, { ...meta, lang: 'en', facet: 'process_step', intent: 'how_to', subject: p.id, answer_facet: 'process_step' });
      add(`who is responsible for ${p.en}`, { ...meta, lang: 'en', facet: 'permission', intent: 'who_does', subject: p.id, answer_facet: 'permission' });
    }

    /* --- records and fields --- */
    for (const e of m.entities) {
      for (const lang of ['en', 'bn', 'bl']) {
        const name = lang === 'bn' ? e.bn : e.en;
        for (const form of pickN(ENTITY_FORMS[lang], 2)) {
          add(form(name), { ...meta, lang, facet: 'entity', intent: 'define', subject: e.id, answer_facet: 'entity' });
        }
      }
      if (e.states && e.states.length) {
        add(`what states can a ${e.en} be in`, { ...meta, lang: 'en', facet: 'entity_lifecycle', intent: 'define', subject: e.id, answer_facet: 'entity_lifecycle' });
        add(`what does ${e.states[0]} mean on a ${e.en}`, { ...meta, lang: 'en', facet: 'entity_lifecycle', intent: 'define', subject: e.id, answer_facet: 'entity_lifecycle' });
        add(`${e.bn} এর অবস্থাগুলো কী কী`, { ...meta, lang: 'bn', facet: 'entity_lifecycle', intent: 'define', subject: e.id, answer_facet: 'entity_lifecycle' });
      }
      for (const f of e.fields.slice(0, 3)) {
        add(`what is the ${f} on a ${e.en}`, { ...meta, lang: 'en', facet: 'field', intent: 'define', subject: `${e.id}.${f}`, answer_facet: 'field' });
        add(`why does a ${e.en} need a ${f}`, { ...meta, lang: 'en', facet: 'field', intent: 'define', subject: `${e.id}.${f}`, answer_facet: 'field' });
        add(`${e.bn} এ ${f} কেন লাগে`, { ...meta, lang: 'bn', facet: 'field', intent: 'define', subject: `${e.id}.${f}`, answer_facet: 'field' });
      }
    }

    /* --- reports --- */
    for (const r of m.reports) {
      for (const lang of ['en', 'bn', 'bl']) {
        const name = lang === 'bn' ? r.bn : r.en;
        const forms = pickN(REPORT_FORMS[lang], 2);
        const sufs = pickN(PERIOD[lang].slice(0, 5), 2);
        forms.forEach((form, i) => {
          add(form(name) + sufs[i], { ...meta, lang, facet: 'report', intent: 'report', subject: r.id, answer_facet: 'report' });
        });
      }
      add(`what does the ${r.en} show`, { ...meta, lang: 'en', facet: 'report', intent: 'report_explain', subject: r.id, answer_facet: 'report' });
      add(`where do I find the ${r.en}`, { ...meta, lang: 'en', facet: 'report', intent: 'navigate', subject: r.id, answer_facet: 'report' });
    }

    /* --- permissions --- */
    for (const r of m.roles) {
      const verb = r.can.split(' ').slice(0, 3).join(' ');
      for (const lang of ['en', 'bn', 'bl']) {
        for (const form of pickN(PERMISSION_FORMS[lang], 1)) {
          add(form(r.role, verb), { ...meta, lang, facet: 'permission', intent: 'permission', subject: r.role, answer_facet: 'permission' });
        }
      }
      add(`what can't a ${r.role} do in ${m.en}`, { ...meta, lang: 'en', facet: 'permission', intent: 'permission', subject: r.role, answer_facet: 'permission' });
    }
    for (const role of v.roles) {
      add(`does the ${role} need access to ${m.en}`, { ...meta, lang: 'en', facet: 'role_matrix', intent: 'permission', subject: role, answer_facet: 'role_matrix' });
      add(`should the ${role} see ${m.en}`, { ...meta, lang: 'en', facet: 'role_matrix', intent: 'permission', subject: role, answer_facet: 'role_matrix' });
      add(`${role} কি ${m.bn} দেখতে পাবে`, { ...meta, lang: 'bn', facet: 'role_matrix', intent: 'permission', subject: role, answer_facet: 'role_matrix' });
    }

    /* --- trouble --- */
    for (const lang of ['en', 'bn', 'bl']) {
      const name = lang === 'bn' ? m.bn : m.en;
      for (const form of pickN(TROUBLE_FORMS[lang], 2)) {
        add(form(name), { ...meta, lang, facet: 'scenario', intent: 'troubleshoot', subject: 'wrong_number', answer_facet: 'scenario' });
      }
    }
    for (const p of m.pitfalls) {
      add(`why does ${p.en} keep happening`, { ...meta, lang: 'en', facet: 'pitfall', intent: 'troubleshoot', subject: p.id, answer_facet: 'pitfall' });
      add(`how do I stop ${p.en}`, { ...meta, lang: 'en', facet: 'remedy', intent: 'remedy', subject: p.id, answer_facet: 'remedy' });
      add(`${p.en} — কী করব`, { ...meta, lang: 'bn', facet: 'remedy', intent: 'remedy', subject: p.id, answer_facet: 'remedy' });
    }
    add(`what should I check in ${m.en} before closing the ${cycle}`, { ...meta, lang: 'en', facet: 'scenario', intent: 'period_close', subject: 'month_end', answer_facet: 'scenario' });
    add(`${m.bn} এ ${term(v, 'cycle', 1)} বন্ধ করার আগে কী দেখব`, { ...meta, lang: 'bn', facet: 'scenario', intent: 'period_close', subject: 'month_end', answer_facet: 'scenario' });
    add(`what will the auditor ask about ${m.en}`, { ...meta, lang: 'en', facet: 'scenario', intent: 'audit', subject: 'audit', answer_facet: 'scenario' });
    add(`how do I migrate ${m.en} data`, { ...meta, lang: 'en', facet: 'scenario', intent: 'migration', subject: 'migration', answer_facet: 'scenario' });
    add(`how do I train someone on ${m.en}`, { ...meta, lang: 'en', facet: 'scenario', intent: 'training', subject: 'training', answer_facet: 'scenario' });

    /* --- posting --- */
    for (const lang of ['en', 'bn', 'bl']) {
      const name = lang === 'bn' ? m.bn : m.en;
      for (const form of pickN(POSTING_FORMS[lang], 2)) {
        add(form(name), { ...meta, lang, facet: 'posting_rule', intent: 'posting', subject: 'posting', answer_facet: 'posting_rule' });
      }
    }

    /* --- setup and fit --- */
    for (const lang of ['en', 'bn', 'bl']) {
      const mn = lang === 'bn' ? m.bn : m.en;
      const vn = lang === 'bn' ? v.bn : v.en;
      for (const form of pickN(SETUP_FORMS[lang], 2)) {
        add(form(mn, vn), { ...meta, lang, facet: 'module_overview', intent: 'setup', subject: 'setup', answer_facet: 'module_overview' });
      }
    }

    /* --- integrations --- */
    for (const t of (m.talksTo || [])) {
      const other = MODULES[t];
      if (!other) continue;
      add(`how does ${m.en} connect to ${other.en}`, { ...meta, lang: 'en', facet: 'integration', intent: 'integration', subject: t, answer_facet: 'integration' });
      add(`why doesn't ${m.en} match ${other.en}`, { ...meta, lang: 'en', facet: 'integration', intent: 'troubleshoot', subject: t, answer_facet: 'integration' });
      add(`${m.bn} আর ${other.bn} মিলছে না কেন`, { ...meta, lang: 'bn', facet: 'integration', intent: 'troubleshoot', subject: t, answer_facet: 'integration' });
    }

    /* --- vocabulary --- */
    for (const a of (m.aliases || [])) {
      const lang = /[ঀ-৿]/.test(a) ? 'bn' : 'en';
      add(lang === 'bn' ? `${a} মানে কী` : `what do you mean by ${a}`,
        { ...meta, lang, facet: 'glossary', intent: 'define', subject: a, answer_facet: 'glossary' });
      add(lang === 'bn' ? `${a} কোথায় পাব` : `where is ${a} in the system`,
        { ...meta, lang, facet: 'glossary', intent: 'navigate', subject: a, answer_facet: 'glossary' });
    }

    /* --- scope discipline --- */
    add(`${m.en} for the ${unit}`, { ...meta, lang: 'en', facet: 'answering_note', intent: 'scope', subject: 'scope', answer_facet: 'answering_note' });
    add(`show ${m.en} by ${unit}`, { ...meta, lang: 'en', facet: 'answering_note', intent: 'scope', subject: 'scope', answer_facet: 'answering_note' });
  }

  /* --- vertical level --- */
  add(`what does an ERP for a ${v.en} need`, { vertical: v.id, module: null, lang: 'en', facet: 'vertical_profile', intent: 'overview', subject: 'profile', answer_facet: 'vertical_profile' });
  add(`${v.bn} এর জন্য কী কী মডিউল লাগে`, { vertical: v.id, module: null, lang: 'bn', facet: 'vertical_profile', intent: 'overview', subject: 'profile', answer_facet: 'vertical_profile' });
  add(`who are the users in a ${v.en}`, { vertical: v.id, module: null, lang: 'en', facet: 'vertical_roles', intent: 'overview', subject: 'roles', answer_facet: 'vertical_roles' });
  for (const k of v.kpis) {
    add(`what is our ${k}`, { vertical: v.id, module: null, lang: 'en', facet: 'vertical_kpi', intent: 'kpi_value', subject: k, answer_facet: 'vertical_kpi' });
    add(`${k}?`, { vertical: v.id, module: null, lang: 'en', facet: 'vertical_kpi', intent: 'kpi_value', subject: k, answer_facet: 'vertical_kpi' });
  }
  for (const c of v.compliance) {
    add(`what do I need for ${c}`, { vertical: v.id, module: null, lang: 'en', facet: 'compliance', intent: 'compliance', subject: c, answer_facet: 'compliance' });
    add(`when is ${c} due`, { vertical: v.id, module: null, lang: 'en', facet: 'compliance', intent: 'compliance', subject: c, answer_facet: 'compliance' });
  }
  for (const a of v.aliases) {
    const lang = /[ঀ-৿]/.test(a) ? 'bn' : 'en';
    add(lang === 'bn' ? `${a} এর জন্য এই সিস্টেম চলবে` : `does this work for a ${a}`,
      { vertical: v.id, module: null, lang, facet: 'vertical_profile', intent: 'fit', subject: a, answer_facet: 'vertical_profile' });
  }
}

/* ------------------------------------------------------------------
   report and write
   ------------------------------------------------------------------ */
const langs = rows.reduce((a, r) => { a[r.lang] = (a[r.lang] || 0) + 1; return a; }, {});
const intents = rows.reduce((a, r) => { a[r.intent] = (a[r.intent] || 0) + 1; return a; }, {});
const perVertical = rows.reduce((a, r) => { a[r.vertical] = (a[r.vertical] || 0) + 1; return a; }, {});

console.log(`questions: ${rows.length.toLocaleString()}`);
console.log(`  languages: ${Object.entries(langs).map(([k, n]) => `${k} ${n.toLocaleString()}`).join(' · ')}`);
console.log(`  intents: ${Object.keys(intents).length}`);
for (const [k, n] of Object.entries(intents).sort((a, b) => b[1] - a[1])) console.log(`    ${k.padEnd(16)} ${String(n).padStart(7)}`);
console.log('  by facet:');
for (const [k, n] of Object.entries(composition).sort((a, b) => b[1] - a[1])) console.log(`    ${k.padEnd(18)} ${String(n).padStart(7)}`);
console.log(`  verticals: ${Object.keys(perVertical).length}, smallest ${Math.min(...Object.values(perVertical)).toLocaleString()}, largest ${Math.max(...Object.values(perVertical)).toLocaleString()}`);

if (!COUNT_ONLY) {
  mkdirSync(OUT_DIR, { recursive: true });
  let shard = 0;
  for (let i = 0; i < rows.length; i += SHARD_SIZE) {
    const part = rows.slice(i, i + SHARD_SIZE);
    const file = join(OUT_DIR, `q-${String(++shard).padStart(3, '0')}.jsonl`);
    writeFileSync(file, part.map((r) => JSON.stringify(r)).join('\n') + '\n', 'utf8');
    console.log(`  wrote ${file} (${part.length.toLocaleString()} questions)`);
  }
  writeFileSync(join(OUT_DIR, 'q-manifest.json'), JSON.stringify({
    generated_by: 'tools/kb/make-questions.mjs',
    questions: rows.length, shards: shard, shard_size: SHARD_SIZE,
    languages: langs, intents, facets: composition,
  }, null, 1), 'utf8');
}
