#!/usr/bin/env node
/* ============================================================
   make-kb.mjs — build the ERP knowledge corpus EON retrieves against.

   Crosses every vertical (verticals.mjs) with every module it
   actually runs (modules-*.mjs) and writes one record per fact:
   what the module is for here, each entity and its fields and
   states, each process and its steps, each measure and how to
   read it, each report, each role, each trap and its fix, how it
   posts, what it talks to, and the scenarios a director asks about.

     node tools/kb/make-kb.mjs                  → JSONL shards
     node tools/kb/make-kb.mjs --count          → composition only
     node tools/kb/make-kb.mjs --out DIR        → write elsewhere

   Deterministic: same inputs, same ids, same order, every run.
   ============================================================ */

import { writeFileSync, mkdirSync, rmSync, readdirSync } from 'node:fs';
import { dirname, resolve, join } from 'node:path';
import { fileURLToPath } from 'node:url';

import { VERTICALS } from './verticals.mjs';
import { CORE_MODULES } from './modules-core.mjs';
import { VERTICAL_MODULES_A } from './modules-vertical-a.mjs';
import { VERTICAL_MODULES_B1 } from './modules-vertical-b1.mjs';
import { VERTICAL_MODULES_B2 } from './modules-vertical-b2.mjs';
import { VERTICAL_MODULES_B3 } from './modules-vertical-b3.mjs';
import { fieldNote, term, cap, list, bnList, bnNum, makeId, keywords, an, art } from './phrasing.mjs';

const HERE = dirname(fileURLToPath(import.meta.url));
const argv = process.argv.slice(2);
const COUNT_ONLY = argv.includes('--count');
const OUT_DIR = (() => {
  const i = argv.indexOf('--out');
  return resolve(i >= 0 && argv[i + 1] ? argv[i + 1] : join(HERE, '..', 'corpus'));
})();
const SHARD_SIZE = 20000;

const MODULES = { ...CORE_MODULES, ...VERTICAL_MODULES_A, ...VERTICAL_MODULES_B1, ...VERTICAL_MODULES_B2, ...VERTICAL_MODULES_B3 };

/* which modules a vertical actually runs: every core module, plus its own pack */
function modulesFor(v) {
  const core = Object.keys(MODULES).filter((id) => !MODULES[id].onlyFor);
  const extra = (v.extra || []).filter((id) => MODULES[id]);
  return [...core, ...extra];
}

const rows = [];
const composition = {};
function push(rec) {
  composition[rec.facet] = (composition[rec.facet] || 0) + 1;
  rows.push(rec);
}

/** one knowledge record */
function rec({ id, facet, vertical, module, subject, lang, title, text, tags = [], extra = {} }) {
  return {
    id, facet, lang,
    vertical: vertical ? vertical.id : null,
    vertical_name: vertical ? (lang === 'bn' ? vertical.bn : vertical.en) : null,
    module: module ? module.__id : null,
    module_name: module ? (lang === 'bn' ? module.bn : module.en) : null,
    subject: subject || null,
    title, text,
    tags,
    keywords: keywords(title, text, tags, vertical ? [vertical.en, vertical.bn] : [], module ? [module.en, module.bn] : []),
    ...extra,
  };
}

/* ------------------------------------------------------------------
   1. the vertical itself — what kind of business this is
   ------------------------------------------------------------------ */
for (const v of VERTICALS) {
  const mods = modulesFor(v);
  push(rec({
    id: makeId('kb', v.id, 'profile'), facet: 'vertical_profile', vertical: v, lang: 'en',
    title: `What an ERP for ${an(v.en)} has to cover`,
    text: `A ${v.en} (also called ${list(v.aliases.filter((a) => /^[\x20-\x7e]+$/.test(a)), 'or')}) runs ${mods.length} modules in practice. Beyond the ledger, payroll and stock that every business keeps, it specifically needs ${list((v.extra || []).map((e) => MODULES[e] ? MODULES[e].en : e))}. Its own vocabulary matters: a customer is ${an(term(v, 'customer'))}, what it sells is ${an(term(v, 'product'))}, income arrives as ${term(v, 'revenue')}, the operating unit is ${an(term(v, 'unit'))} and the cycle everything is measured over is the ${term(v, 'cycle')}. The measures the owner asks for first are ${list(v.kpis)}.`,
    tags: ['profile', v.kind],
  }));
  push(rec({
    id: makeId('kb', v.id, 'profile', 'bn'), facet: 'vertical_profile', vertical: v, lang: 'bn',
    title: `${v.bn}-এর জন্য ইআরপিতে যা যা লাগে`,
    text: `একটি ${v.bn} বাস্তবে ${bnNum(mods.length)}টি মডিউল চালায়। সাধারণ খতিয়ান, বেতন ও মজুদের বাইরে এর নিজস্ব যা লাগে তা হলো ${bnList((v.extra || []).map((e) => MODULES[e] ? MODULES[e].bn : e))}। এখানে গ্রাহক মানে ${term(v, 'customer', 1)}, যা বিক্রি হয় তা ${term(v, 'product', 1)}, আয় আসে ${term(v, 'revenue', 1)} হিসেবে, পরিচালন একক ${term(v, 'unit', 1)}, আর হিসাবের চক্র ${term(v, 'cycle', 1)}।`,
    tags: ['profile', v.kind],
  }));
  push(rec({
    id: makeId('kb', v.id, 'roles'), facet: 'vertical_roles', vertical: v, lang: 'en',
    title: `Who does what in ${an(v.en)}`,
    text: `The roles an ERP for ${an(v.en)} must recognise are ${list(v.roles)}. Access is scoped per role and per ${term(v, 'unit')}: a role decides which verbs are allowed — view, create, edit, approve, post, export — and the scope decides which rows those verbs reach. The owner sees every ${term(v, 'unit')}; ${an(v.roles[v.roles.length - 1])} sees only their own.`,
    tags: ['roles', 'access'],
  }));
  for (const c of v.compliance) {
    push(rec({
      id: makeId('kb', v.id, 'compliance', c), facet: 'compliance', vertical: v, subject: c, lang: 'en',
      title: `${cap(c)} in ${an(v.en)}`,
      text: `A ${v.en} has to satisfy ${c}. In the system this means the underlying records must already be complete when the requirement falls due, rather than being assembled afterwards: the documents attached to their transactions, the dates recorded as they happened, and the responsible person named. Treat it as a dated obligation with an owner and an alert ahead of the deadline, not as an annual scramble.`,
      tags: ['compliance', 'regulatory'],
    }));
  }
  for (const k of v.kpis) {
    push(rec({
      id: makeId('kb', v.id, 'headline', k), facet: 'vertical_kpi', vertical: v, subject: k, lang: 'en',
      title: `${cap(k)} — a headline measure for ${an(v.en)}`,
      text: `${cap(k)} is one of the first numbers the owner of ${an(v.en)} asks for. It is only meaningful against a stated period and a stated ${term(v, 'unit')}, so any answer must name both. Compare it against the same ${term(v, 'cycle')} last year rather than against the previous one, because this trade is seasonal, and read it beside ${list(v.kpis.filter((x) => x !== k).slice(0, 2))} — on its own it can be improved in ways that damage the business.`,
      tags: ['kpi', 'headline'],
    }));
  }
}

/* ------------------------------------------------------------------
   2. every vertical × module pair
   ------------------------------------------------------------------ */
for (const v of VERTICALS) {
  for (const mid of modulesFor(v)) {
    const m = { ...MODULES[mid], __id: mid };
    const cust = term(v, 'customer'), prod = term(v, 'product'), unit = term(v, 'unit'), cycle = term(v, 'cycle');
    const base = { vertical: v, module: m };

    /* -- overview -- */
    push(rec({
      ...base, id: makeId('kb', v.id, mid, 'overview'), facet: 'module_overview', lang: 'en',
      title: `${cap(m.en)} in ${an(v.en)}`,
      text: `In ${an(v.en)}, ${m.en} ${m.purpose.en}. It holds ${list(m.entities.map((e) => e.en))}. The people who touch it are ${list(m.roles.map((r) => r.role))}, and it exchanges data with ${list((m.talksTo || []).map((t) => MODULES[t] ? MODULES[t].en : t))}. Questions about it usually arrive in the trade's own words — ${cust}, ${prod}, ${unit}, ${cycle} — rather than in accounting language.`,
      tags: ['overview', m.group],
    }));
    push(rec({
      ...base, id: makeId('kb', v.id, mid, 'overview', 'bn'), facet: 'module_overview', lang: 'bn',
      title: `${v.bn}-এ ${m.bn}`,
      text: `একটি ${v.bn}-এ ${m.bn} ${m.purpose.bn}। এতে থাকে ${bnList(m.entities.map((e) => e.bn))}। এই মডিউলটি ${bnList((m.talksTo || []).slice(0, 4).map((t) => MODULES[t] ? MODULES[t].bn : t))}-এর সাথে তথ্য বিনিময় করে। প্রশ্ন সাধারণত হিসাবের ভাষায় নয়, এই পেশার নিজস্ব শব্দে আসে — ${term(v, 'customer', 1)}, ${term(v, 'product', 1)}, ${term(v, 'unit', 1)}, ${term(v, 'cycle', 1)}।`,
      tags: ['overview', m.group],
    }));

    /* -- scope note: how to answer about this module here -- */
    push(rec({
      ...base, id: makeId('kb', v.id, mid, 'scope'), facet: 'answering_note', lang: 'en',
      title: `Answering ${an(v.en)} question about ${m.en}`,
      text: `Any figure from ${m.en} must be stated with its scope and period: which ${unit}, which ${cycle}, and whether draft records are included. Default to posted or confirmed records only, and say so. If the question names no ${unit}, answer for the whole business and name that choice in the sentence. If the ${cycle} being asked about has not finished, say what is known so far rather than reporting a total that will change.`,
      tags: ['answering', 'scope'],
    }));
    push(rec({
      ...base, id: makeId('kb', v.id, mid, 'scope', 'bn'), facet: 'answering_note', lang: 'bn',
      title: `${v.bn}-এ ${m.bn} নিয়ে প্রশ্নের উত্তর দেওয়ার নিয়ম`,
      text: `${m.bn} থেকে কোনো সংখ্যা বললে সঙ্গে পরিসর ও সময় বলতে হবে — কোন ${term(v, 'unit', 1)}, কোন ${term(v, 'cycle', 1)}, আর খসড়া রেকর্ড ধরা হয়েছে কিনা। ডিফল্ট হবে শুধু পোস্ট করা রেকর্ড, এবং সেটাও বলে দিতে হবে। প্রশ্নে কোনো ${term(v, 'unit', 1)} উল্লেখ না থাকলে পুরো প্রতিষ্ঠানের হিসাব দিতে হবে এবং বাক্যেই তা বলতে হবে। যে ${term(v, 'cycle', 1)} এখনো শেষ হয়নি, তার ক্ষেত্রে "এ পর্যন্ত যা জানা গেছে" বলতে হবে — এমন মোট নয় যা পরে বদলে যাবে।`,
      tags: ['answering', 'scope'],
    }));

    /* -- entities -- */
    for (const e of m.entities) {
      push(rec({
        ...base, id: makeId('kb', v.id, mid, 'entity', e.id), facet: 'entity', subject: e.en, lang: 'en',
        title: `${cap(e.en)} in ${an(v.en)}`,
        /* `note` is a lesson about the record, not a description of what it holds.
           Dropped into this slot it read "A bed is the record inpatient department
           uses to hold cleaning is a real state — treating it as vacant oversells
           the ward" — ungrammatical, and said with total confidence in 4,706
           records. The fields are what the record holds; the note gets its own
           sentence, exactly as `entity_fields` three templates below already does. */
        text: `${cap(an(e.en))} is the record ${m.en} keeps for each ${cycle}. In ${an(v.en)} it relates to the ${cust} and the ${prod} it was raised for, and belongs to one ${unit}. It carries ${e.fields.length} fields: ${list(e.fields)}. Worth knowing: ${e.note}.`,
        tags: ['entity', m.group],
      }));
      push(rec({
        ...base, id: makeId('kb', v.id, mid, 'entity', e.id, 'bn'), facet: 'entity', subject: e.en, lang: 'bn',
        title: `${v.bn}-এ ${e.bn}`,
        text: `${e.bn} হলো ${m.bn}-এর সেই রেকর্ড যা প্রতিটি ${term(v, 'cycle', 1)}-এর তথ্য ধরে রাখে। এটি ${term(v, 'customer', 1)} ও ${term(v, 'product', 1)}-এর সাথে যুক্ত এবং একটি ${term(v, 'unit', 1)}-এর অধীনে থাকে। এর তথ্যক্ষেত্রগুলো: ${bnList(e.fields.map((f) => `“${f}”`))}।`,
        tags: ['entity', m.group],
      }));
      push(rec({
        ...base, id: makeId('kb', v.id, mid, 'entity', e.id, 'fields'), facet: 'entity_fields', subject: e.en, lang: 'en',
        title: `What is recorded on ${an(e.en)} in ${an(v.en)}`,
        text: `${cap(an(e.en))} records ${list(e.fields)}. ${cap(e.note)}. Missing any of these is what makes a later report unanswerable: the identifying field decides whether duplicates can be found, the dated fields decide every ageing measure, and the person fields decide whether the trail names anybody.`,
        tags: ['fields', 'data model'],
      }));
      push(rec({
        ...base, id: makeId('kb', v.id, mid, 'entity', e.id, 'fields', 'bn'), facet: 'entity_fields', subject: e.en, lang: 'bn',
        title: `${v.bn}-এ ${e.bn}-এ কী কী তথ্য থাকে`,
        text: `${e.bn}-এ যে তথ্যক্ষেত্রগুলো রাখা হয়: ${bnList(e.fields.map((f) => `“${f}”`))}। এর কোনোটি ফাঁকা থাকলে পরে রিপোর্টের জবাব দেওয়া যায় না — শনাক্তকারী ক্ষেত্র দিয়ে ডুপ্লিকেট ধরা পড়ে, তারিখের ক্ষেত্র দিয়ে বকেয়ার বয়স হিসাব হয়, আর ব্যক্তির ক্ষেত্র দিয়ে বোঝা যায় কাজটি কে করেছে।`,
        tags: ['fields', 'data model'], extra: { mixed: true },
      }));
      if (e.states && e.states.length) {
        push(rec({
          ...base, id: makeId('kb', v.id, mid, 'entity', e.id, 'states'), facet: 'entity_lifecycle', subject: e.en, lang: 'en',
          title: `The lifecycle of ${an(e.en)} in ${an(v.en)}`,
          text: `${cap(an(e.en))} moves through ${e.states.length} states: ${list(e.states)}. The state is not decoration — it governs what may be done next and what the record contributes to a total. ${cap(e.note)}. When a figure looks wrong, check which states were included before checking the arithmetic.`,
          tags: ['lifecycle', 'states'],
        }));
        push(rec({
          ...base, id: makeId('kb', v.id, mid, 'entity', e.id, 'states', 'bn'), facet: 'entity_lifecycle', subject: e.en, lang: 'bn',
          title: `${v.bn}-এ ${e.bn}-এর অবস্থাগুলো`,
          text: `${e.bn} ${bnNum(e.states.length)}টি অবস্থার মধ্য দিয়ে যায়: ${bnList(e.states.map((x) => `“${x}”`))}। অবস্থা শুধু দেখানোর জিনিস নয় — এটিই ঠিক করে পরবর্তীতে কী করা যাবে এবং রেকর্ডটি কোনো মোটে যোগ হবে কিনা। কোনো সংখ্যা ভুল মনে হলে হিসাব দেখার আগে দেখুন কোন কোন অবস্থার রেকর্ড ধরা হয়েছে।`,
          tags: ['lifecycle', 'states'], extra: { mixed: true },
        }));
      }
      /* one record per field, where the field name carries real meaning */
      for (const f of e.fields) {
        const note = fieldNote(f);
        if (!note) continue;
        push(rec({
          ...base, id: makeId('kb', v.id, mid, 'field', e.id, f), facet: 'field', subject: `${e.en}.${f}`, lang: 'en',
          title: `The ${f} field on ${an(e.en)} (${v.en})`,
          text: `On ${an(e.en)} in ${an(v.en)}, ${f} is ${note}. It belongs to ${m.en}, so a question about it is a question about ${m.purpose.en.split(',')[0]}.`,
          tags: ['field', e.id],
        }));
      }
    }

    /* -- how the records inside the module hang together -- */
    for (let i = 0; i < m.entities.length - 1; i++) {
      const a = m.entities[i], b = m.entities[i + 1];
      push(rec({
        ...base, id: makeId('kb', v.id, mid, 'rel', a.id, b.id), facet: 'relationship', subject: `${a.en}~${b.en}`, lang: 'en',
        title: `How ${an(a.en)} relates to ${an(b.en)} in ${an(v.en)}`,
        /* Same misuse of `note` as the entity template: it is a lesson, not a
           list of contents, and "a progress claim holds certified and claimed are
           rarely the same number" is not a sentence. What each record holds is
           its fields; the two lessons stand on their own at the end. */
        text: `Inside ${m.en}, ${an(a.en)} and ${an(b.en)} are separate records that must be linked rather than merged. ${cap(an(a.en))} carries ${list(a.fields.slice(0, 4))}; ${an(b.en)} carries ${list(b.fields.slice(0, 4))}. Keeping them apart is what lets one be corrected without rewriting the other, and the link between them is what makes ${an(v.en)} able to answer where a figure came from. When the two disagree, the older record is usually right and the newer one was entered without reading it. Two things worth carrying into that: ${a.note}; and ${b.note}.`,
        tags: ['relationship', 'data model'],
      }));
      push(rec({
        ...base, id: makeId('kb', v.id, mid, 'rel', a.id, b.id, 'bn'), facet: 'relationship', subject: `${a.en}~${b.en}`, lang: 'bn',
        title: `${v.bn}-এ ${a.bn} ও ${b.bn}-এর সম্পর্ক`,
        text: `${m.bn}-এ ${a.bn} আর ${b.bn} আলাদা দুটি রেকর্ড — এদের যুক্ত রাখতে হয়, এক করে ফেলা যায় না। আলাদা রাখলে একটিকে সংশোধন করতে গিয়ে অন্যটি নতুন করে লিখতে হয় না, আর এদের সংযোগ থেকেই বোঝা যায় কোনো সংখ্যা কোথা থেকে এলো। দুটির তথ্যে অমিল হলে সাধারণত পুরোনো রেকর্ডটিই ঠিক থাকে।`,
        tags: ['relationship', 'data model'], extra: { mixed: true },
      }));
    }

    /* -- processes -- */
    for (const p of m.processes) {
      push(rec({
        ...base, id: makeId('kb', v.id, mid, 'process', p.id), facet: 'process', subject: p.en, lang: 'en',
        title: `${cap(p.en)} in ${an(v.en)}`,
        text: `${cap(p.en)} runs in ${p.steps.length} steps: ${p.steps.map((s, i) => `${i + 1}. ${s}`).join('; ')}. The order matters — each step is what makes the next one verifiable, and skipping one is how ${an(v.en)} ends up with records that cannot be explained afterwards.`,
        tags: ['process', m.group],
      }));
      push(rec({
        ...base, id: makeId('kb', v.id, mid, 'process', p.id, 'bn'), facet: 'process', subject: p.en, lang: 'bn',
        title: `${v.bn}-এ ${p.bn || p.en}`,
        text: `${p.bn || p.en} — এই কাজটি ${p.steps.length}টি ধাপে হয়। ধাপগুলোর ক্রম গুরুত্বপূর্ণ, কারণ প্রতিটি ধাপ পরেরটিকে যাচাইযোগ্য করে তোলে। কোনো ধাপ বাদ দিলে পরে সেই রেকর্ডের ব্যাখ্যা দেওয়া যায় না।`,
        tags: ['process', m.group],
      }));
      p.steps.forEach((s, i) => {
        push(rec({
          ...base, id: makeId('kb', v.id, mid, 'step', p.id, String(i + 1)), facet: 'process_step', subject: p.en, lang: 'en',
          title: `Step ${i + 1} of ${p.en} (${v.en})`,
          text: `Step ${i + 1} is to ${s}. It sits ${i === 0 ? 'at the start of' : i === p.steps.length - 1 ? 'at the end of' : 'in the middle of'} ${p.en} in ${m.en}. ${i === p.steps.length - 1 ? 'Once this is done the record is committed, and a correction from here on is a reversal rather than an edit.' : `The next step is to ${p.steps[i + 1]}.`}`,
          tags: ['step', p.id],
        }));
      });
    }

    /* -- measures -- */
    for (const k of m.kpis) {
      push(rec({
        ...base, id: makeId('kb', v.id, mid, 'kpi', k.id), facet: 'kpi_definition', subject: k.en, lang: 'en',
        title: `${cap(k.en)} in ${an(v.en)}`,
        text: `${cap(k.en)} is computed as ${k.formula}. In ${an(v.en)} it is read per ${unit} and per ${cycle}. ${cap(k.reads)}.`,
        tags: ['kpi', 'formula', m.group],
        extra: { formula: k.formula },
      }));
      push(rec({
        ...base, id: makeId('kb', v.id, mid, 'kpi', k.id, 'bn'), facet: 'kpi_definition', subject: k.en, lang: 'bn',
        title: `${v.bn}-এ ${k.bn}`,
        text: `${k.bn} হিসাব করা হয় এভাবে — সূত্রটি সিস্টেমে ইংরেজিতেই লেখা: “${k.formula}”। একটি ${v.bn}-এ এটি প্রতিটি ${term(v, 'unit', 1)} ও ${term(v, 'cycle', 1)} ধরে দেখা হয়। সংখ্যাটি একা পড়লে ভুল বোঝা যায় — সঙ্গে সময়কাল ও পরিসর বলতে হবে।`,
        tags: ['kpi', 'formula', m.group],
        extra: { formula: k.formula },
      }));
      push(rec({
        ...base, id: makeId('kb', v.id, mid, 'kpi', k.id, 'read'), facet: 'kpi_reading', subject: k.en, lang: 'en',
        title: `How to read ${k.en} in ${an(v.en)}`,
        text: `${cap(k.reads)}. Two readings of ${k.en} are only comparable if the period, the ${unit} and the inclusion rule are the same. A movement in it is worth explaining before it is worth reporting: check whether the underlying records changed, whether the period was reopened, and whether ${an(unit)} was added or closed inside the window.`,
        tags: ['kpi', 'interpretation'],
      }));
      push(rec({
        ...base, id: makeId('kb', v.id, mid, 'kpi', k.id, 'read', 'bn'), facet: 'kpi_reading', subject: k.en, lang: 'bn',
        title: `${v.bn}-এ ${k.bn} কীভাবে পড়তে হয়`,
        text: `${k.bn} দুইবারের হিসাব তুলনা করা যাবে তখনই, যখন সময়কাল, ${term(v, 'unit', 1)} আর কোন রেকর্ড ধরা হয়েছে — এই তিনটিই একই থাকে। সংখ্যাটি নড়লে রিপোর্ট করার আগে ব্যাখ্যা খুঁজুন: মূল রেকর্ড বদলেছে কিনা, সময়কাল পুনরায় খোলা হয়েছে কিনা, আর ওই সময়ের মধ্যে কোনো ${term(v, 'unit', 1)} যুক্ত বা বন্ধ হয়েছে কিনা।`,
        tags: ['kpi', 'interpretation'], extra: { mixed: true },
      }));
      push(rec({
        ...base, id: makeId('kb', v.id, mid, 'kpi', k.id, 'move', 'bn'), facet: 'kpi_action', subject: k.en, lang: 'bn',
        title: `${v.bn}-এ ${k.bn} কী কারণে বাড়ে বা কমে`,
        text: `${k.bn}-এর সূত্র সিস্টেমে ইংরেজিতে লেখা আছে — “${k.formula}” — অর্থাৎ কেবল দুটি জিনিস একে নড়াতে পারে: উপরের অংশ বা নিচের অংশ। ${v.bn}-এ সৎ উপায়গুলো ${m.bn}-এর ভেতরেই আছে। যেটি সাবধানে দেখতে হবে তা হলো — কাজ না বদলে শুধু কী গোনা হচ্ছে তা বদলে সংখ্যাটি ভালো দেখানো।`,
        tags: ['kpi', 'action'], extra: { mixed: true },
      }));
      push(rec({
        ...base, id: makeId('kb', v.id, mid, 'kpi', k.id, 'move'), facet: 'kpi_action', subject: k.en, lang: 'en',
        title: `What moves ${k.en} in ${an(v.en)}`,
        text: `${cap(k.en)} is ${k.formula}, so only two things can move it: the numerator or the denominator. In ${an(v.en)} the honest levers sit inside ${m.en} — the processes that feed it are ${list(m.processes.map((p) => p.en))}, and the traps that corrupt it are ${list(m.pitfalls.map((p) => p.en).slice(0, 2))}. Improving the measure by changing what is counted rather than what is done is the failure mode to watch for.`,
        tags: ['kpi', 'action'],
      }));
    }

    /* -- reports -- */
    for (const r of m.reports) {
      push(rec({
        ...base, id: makeId('kb', v.id, mid, 'report', r.id), facet: 'report', subject: r.en, lang: 'en',
        title: `The ${r.en} in ${an(v.en)}`,
        text: `The ${r.en} shows ${r.use}. It is produced from ${m.en}, so it inherits that module's scope rules: name the ${unit}, the ${cycle} and whether unposted records are included. In ${an(v.en)} it is usually the report asked for when somebody says they want to see the ${cust} or ${prod} position.`,
        tags: ['report', m.group],
      }));
      push(rec({
        ...base, id: makeId('kb', v.id, mid, 'report', r.id, 'bn'), facet: 'report', subject: r.en, lang: 'bn',
        title: `${v.bn}-এ ${r.bn}`,
        text: `${r.bn} যা দেখায় তা সিস্টেমে ইংরেজিতে লেখা: “${r.use}”। এটি ${m.bn} থেকে তৈরি হয়, তাই এর সাথে সবসময় বলতে হবে কোন ${term(v, 'unit', 1)}, কোন ${term(v, 'cycle', 1)}, আর অপোস্টেড রেকর্ড ধরা হয়েছে কিনা।`,
        tags: ['report', m.group],
      }));
    }

    /* -- roles and permissions -- */
    for (const r of m.roles) {
      push(rec({
        ...base, id: makeId('kb', v.id, mid, 'role', r.role), facet: 'permission', subject: r.role, lang: 'en',
        title: `What ${an(r.role)} may do in ${m.en} (${v.en})`,
        text: `In ${an(v.en)}, ${an(r.role)} may ${r.can}. They may not ${r.cannot}. This is a separation of duties rule rather than a convenience: the point is that the person who raises a record is not the person who approves it, and the person who holds the asset is not the person who reconciles it.`,
        tags: ['permission', 'segregation'],
      }));
      push(rec({
        ...base, id: makeId('kb', v.id, mid, 'role', r.role, 'bn'), facet: 'permission', subject: r.role, lang: 'bn',
        title: `${v.bn}-এ ${m.bn}-এ ${r.role} কী করতে পারেন`,
        text: `একটি ${v.bn}-এ “${r.role}” ভূমিকার ব্যক্তি ${m.bn}-এ যা পারেন তা সিস্টেমে ইংরেজিতে লেখা: “${r.can}”। যা পারেন না: “${r.cannot}”। এটি সুবিধার প্রশ্ন নয়, দায়িত্ব পৃথকীকরণের নিয়ম — যে রেকর্ড তোলে সে যেন অনুমোদন না করে, আর যার জিম্মায় সম্পদ থাকে সে যেন নিজেই তা মেলানোর কাজ না করে।`,
        tags: ['permission', 'segregation'], extra: { mixed: true },
      }));
    }

    /* -- this vertical's own job titles, against this module -- */
    for (const role of v.roles) {
      const touches = m.roles.some((r) => role.toLowerCase().includes(r.role.split(' ')[0].toLowerCase())
        || r.role.toLowerCase().includes(role.split(' ')[0].toLowerCase()));
      push(rec({
        ...base, id: makeId('kb', v.id, mid, 'vrole', role), facet: 'role_matrix', subject: role, lang: 'en',
        title: `Does ${an(role)} at ${an(v.en)} work in ${m.en}?`,
        text: touches
          ? `Yes. ${cap(an(role))} at ${an(v.en)} is one of the roles ${m.en} is built around, alongside ${list(m.roles.map((r) => r.role))}. Their day in this module is ${list(m.processes.map((p) => p.en).slice(0, 2))}, and the reports they live in are ${list(m.reports.map((r) => r.en).slice(0, 2))}.`
          : `Only as a reader. ${cap(an(role))} at ${an(v.en)} is not one of the operating roles in ${m.en} — those are ${list(m.roles.map((r) => r.role))} — but they are usually given view access to ${list(m.reports.map((r) => r.en).slice(0, 2))} for their own ${term(v, 'unit')}. Give them the report, not the transaction screen.`,
        tags: ['role', 'access', v.id],
      }));
      push(rec({
        ...base, id: makeId('kb', v.id, mid, 'vrole', role, 'bn'), facet: 'role_matrix', subject: role, lang: 'bn',
        title: `${v.bn}-এ ${role} কি ${m.bn}-এ কাজ করেন?`,
        text: touches
          ? `হ্যাঁ। ${v.bn}-এ “${role}” ${m.bn}-এর অন্যতম পরিচালনাকারী ভূমিকা। এই মডিউলে তাঁর প্রতিদিনের কাজ হলো ${bnList(m.processes.map((p) => p.bn).slice(0, 2))}, আর তিনি যেসব রিপোর্ট দেখেন: ${bnList(m.reports.map((r) => r.bn).slice(0, 2))}।`
          : `শুধু দেখার অধিকার। ${v.bn}-এ “${role}” ${m.bn}-এর পরিচালনাকারী ভূমিকা নন, তবে সাধারণত নিজের ${term(v, 'unit', 1)}-এর জন্য ${bnList(m.reports.map((r) => r.bn).slice(0, 2))} দেখার অনুমতি পান। তাঁকে রিপোর্ট দিন, লেনদেনের স্ক্রিন নয়।`,
        tags: ['role', 'access', v.id], extra: { mixed: true },
      }));
    }

    /* -- traps -- */
    for (const p of m.pitfalls) {
      push(rec({
        ...base, id: makeId('kb', v.id, mid, 'pitfall', p.id), facet: 'pitfall', subject: p.id, lang: 'en',
        title: `A common failure in ${m.en} at ${an(v.en)}: ${p.en}`,
        text: `${cap(p.en)} is one of the recurring failures in ${m.en}. It matters in ${an(v.en)} because ${m.en} ${m.purpose.en}, and this failure quietly breaks that. It usually shows up first as a report nobody can reconcile rather than as an error message.`,
        tags: ['pitfall', 'diagnosis'],
      }));
      push(rec({
        ...base, id: makeId('kb', v.id, mid, 'pitfall', p.id, 'fix'), facet: 'remedy', subject: p.id, lang: 'en',
        title: `Fixing ${p.en} in ${an(v.en)}`,
        text: `The fix is to ${p.fix}. Doing this at the point of entry costs a few seconds; doing it as a monthly clean-up costs days and never fully catches up. In ${an(v.en)} the check belongs in ${m.en}, before the record is committed.`,
        tags: ['remedy', 'control'],
      }));
      push(rec({
        ...base, id: makeId('kb', v.id, mid, 'pitfall', p.id, 'bn'), facet: 'pitfall', subject: p.id, lang: 'bn',
        title: `${v.bn}-এ ${m.bn}-এর একটি পরিচিত সমস্যা: ${p.en}`,
        text: `${m.bn}-এ বারবার যে সমস্যাটি হয় — সিস্টেমে এটি ইংরেজিতে লেখা: “${p.en}”। এটি ধরা পড়ে সাধারণত এমন এক রিপোর্ট দিয়ে যা কেউ মেলাতে পারে না — কোনো এরর মেসেজ দিয়ে নয়। সমাধান (ইংরেজিতে লেখা): “${p.fix}”। এন্ট্রির সময় এই যাচাই করলে কয়েক সেকেন্ড লাগে; মাস শেষে ঠিক করতে গেলে কয়েক দিন লাগে এবং কখনোই পুরোপুরি মেলে না।`,
        tags: ['pitfall', 'remedy'], extra: { mixed: true },
      }));
    }

    /* -- how it posts -- */
    if (m.posting) {
      push(rec({
        ...base, id: makeId('kb', v.id, mid, 'posting'), facet: 'posting_rule', lang: 'en',
        title: `How ${m.en} posts to the ledger in ${an(v.en)}`,
        text: `${cap(m.en)} debits ${m.posting.dr} and credits ${m.posting.cr}. The entry is written ${m.posting.when}. If the operational report and the ledger disagree, the difference is almost always timing, scope or an unposted document — check those three in that order before suspecting the arithmetic.`,
        tags: ['posting', 'accounting'],
        extra: { debit: m.posting.dr, credit: m.posting.cr, timing: m.posting.when },
      }));
      push(rec({
        ...base, id: makeId('kb', v.id, mid, 'posting', 'bn'), facet: 'posting_rule', lang: 'bn',
        title: `${v.bn}-এ ${m.bn} খতিয়ানে কীভাবে ওঠে`,
        text: `${m.bn}-এ ডেবিট-ক্রেডিটের নিয়মটি সিস্টেমে ইংরেজিতে লেখা — ডেবিট: “${m.posting.dr}”; ক্রেডিট: “${m.posting.cr}”; কখন: “${m.posting.when}”। পরিচালন রিপোর্ট আর খতিয়ান না মিললে পার্থক্যের কারণ প্রায় সবসময় তিনটির একটি — সময়ের ব্যবধান, পরিসরের পার্থক্য, বা পোস্ট না হওয়া দলিল। এই তিনটি এই ক্রমেই দেখুন, হিসাব ভুল ভাবার আগে।`,
        tags: ['posting', 'accounting'], extra: { mixed: true },
      }));
    } else {
      push(rec({
        ...base, id: makeId('kb', v.id, mid, 'posting'), facet: 'posting_rule', lang: 'en',
        title: `Why ${m.en} does not post to the ledger in ${an(v.en)}`,
        text: `${cap(m.en)} writes no journal entry of its own. It shapes what other modules post — ${list((m.talksTo || []).map((t) => MODULES[t] ? MODULES[t].en : t))} — but its own records carry no double entry. A question that asks what this module did to profit is really a question about the modules it feeds.`,
        tags: ['posting', 'accounting'],
      }));
    }

    /* -- integrations -- */
    for (const t of m.talksTo || []) {
      const other = MODULES[t];
      if (!other) continue;
      push(rec({
        ...base, id: makeId('kb', v.id, mid, 'link', t), facet: 'integration', subject: t, lang: 'en',
        title: `How ${m.en} and ${other.en} connect in ${an(v.en)}`,
        text: `${cap(m.en)} and ${other.en} share data in ${an(v.en)}: ${m.en} ${m.purpose.en}, while ${other.en} ${other.purpose.en}. The join is where reconciliation problems appear — a record complete on one side and missing on the other is the usual cause of two reports giving two answers for the same question.`,
        tags: ['integration', 'reconciliation'],
      }));
      push(rec({
        ...base, id: makeId('kb', v.id, mid, 'link', t, 'bn'), facet: 'integration', subject: t, lang: 'bn',
        title: `${v.bn}-এ ${m.bn} ও ${other.bn} কীভাবে যুক্ত`,
        text: `${m.bn} ${m.purpose.bn}, আর ${other.bn} ${other.purpose.bn}। এই দুইয়ের সংযোগস্থলেই মিলকরণের সমস্যা দেখা দেয় — এক দিকে রেকর্ড আছে অথচ অন্য দিকে নেই, এটিই সাধারণত একই প্রশ্নে দুই রিপোর্টের দুই উত্তর দেওয়ার কারণ।`,
        tags: ['integration', 'reconciliation'], extra: { mixed: true },
      }));
    }

    /* -- glossary -- */
    for (const a of m.aliases || []) {
      push(rec({
        ...base, id: makeId('kb', v.id, mid, 'alias', a), facet: 'glossary', subject: a, lang: /[ঀ-৿]/.test(a) ? 'bn' : 'en',
        title: `"${a}" in ${an(v.en)}`,
        text: /[ঀ-৿]/.test(a)
          ? `${v.bn}-এ "${a}" বলতে ${m.bn} বোঝানো হয়। ${m.purpose.bn}।`
          : `In ${an(v.en)}, "${a}" means ${m.en}. ${cap(m.purpose.en)}. Anybody asking about "${a}" is asking about ${list(m.entities.map((e) => e.en).slice(0, 3))}.`,
        tags: ['glossary', 'synonym'],
      }));
    }

    /* -- scenarios a director actually raises -- */
    const scenarios = [
      { id: 'wrong_number', q: `the ${m.en} figure looks wrong`,
        a: `Check three things in order. First the scope — which ${unit} and which ${cycle} the figure covers, and whether that matches what was asked. Second the inclusion rule — whether draft, cancelled or reversed records were counted; the states here are ${list([...new Set(m.entities.flatMap((e) => e.states || []))].slice(0, 6))}. Third the join to the ledger, since ${m.posting ? `this module posts ${m.posting.when}` : 'this module posts nothing itself and depends on the modules it feeds'}. Only after those three is it worth suspecting the arithmetic.` },
      { id: 'month_end', q: `what to check in ${m.en} before closing the ${cycle}`,
        a: `Confirm every process has run to its last step — ${list(m.processes.map((p) => p.en))}. Then look for the known traps: ${list(m.pitfalls.map((p) => p.en))}. Finally reconcile the module against ${list((m.talksTo || []).map((t) => MODULES[t] ? MODULES[t].en : t).slice(0, 3))} and record the difference by document rather than adjusting it away.` },
      { id: 'new_setup', q: `setting up ${m.en} for a new ${v.en}`,
        a: `Start from the masters, because everything else points at them. Then define the states each record may take, since those decide what counts in a total. Then set the approval rules by value, and the roles: ${list(m.roles.map((r) => r.role))}. Load opening balances last, and prove them before anyone transacts. The mistake is loading transactions before the approval and scope rules exist, which produces a history nobody can defend.` },
      { id: 'audit', q: `what an auditor asks about ${m.en} at ${an(v.en)}`,
        a: `They ask to see the trail rather than the total: who raised each record, who approved it, what document is attached, and whether the person who did one could also do the other. Expect sampling on ${list(m.entities.map((e) => e.en).slice(0, 3))}, and questions about ${list(m.pitfalls.map((p) => p.en).slice(0, 2))}. The defensible answer is the report plus the underlying documents, not the report alone.` },
      { id: 'training', q: `training a new ${m.roles[0].role} on ${m.en} at ${an(v.en)}`,
        a: `Teach the record before the screen. They need to know what ${an(m.entities[0].en)} is and which states it can be in, because that is what decides whether their work counts. Then the two processes they will run daily: ${list(m.processes.map((p) => p.en).slice(0, 2))}. Then what they may not do — ${m.roles[0].cannot} — and why that limit exists. Give them ${list(m.reports.map((r) => r.en).slice(0, 2))} on the first day, so they see the consequence of their own entries.` },
      { id: 'migration', q: `moving ${m.en} data into the system at ${an(v.en)}`,
        a: `Migrate masters first, then open items, then history — never the other way round. Masters are what everything points at, so duplicates loaded here multiply later. Open items mean the records still live at cut-over: ${list(m.entities.map((e) => e.en).slice(0, 3))} in a state other than closed. History is optional and should be loaded summarised unless somebody can name the question it will be asked. Prove each stage against a control total before starting the next, and keep the old system readable until a full ${cycle} has closed cleanly in the new one.` },
    ];
    for (const s of scenarios) {
      push(rec({
        ...base, id: makeId('kb', v.id, mid, 'scenario', s.id), facet: 'scenario', subject: s.id, lang: 'en',
        title: `${cap(s.q)} — ${v.en}`,
        text: s.a,
        tags: ['scenario', 'troubleshooting'],
        extra: { question: cap(s.q) },
      }));
    }
    push(rec({
      ...base, id: makeId('kb', v.id, mid, 'scenario', 'wrong_number', 'bn'), facet: 'scenario', subject: 'wrong_number', lang: 'bn',
      title: `${v.bn}-এ ${m.bn}-এর সংখ্যা ভুল মনে হলে`,
      text: `তিনটি জিনিস এই ক্রমে দেখুন। প্রথমে পরিসর — কোন ${term(v, 'unit', 1)} আর কোন ${term(v, 'cycle', 1)}-এর হিসাব, আর তা প্রশ্নের সাথে মেলে কিনা। দ্বিতীয়ত কোন কোন অবস্থার রেকর্ড ধরা হয়েছে — খসড়া, বাতিল বা রিভার্স করা রেকর্ড যোগ হয়ে গেছে কিনা। তৃতীয়ত খতিয়ানের সাথে মিল। এই তিনটির পরেই কেবল হিসাব ভুল সন্দেহ করা উচিত।`,
      tags: ['scenario', 'troubleshooting'], extra: { mixed: true },
    }));
    push(rec({
      ...base, id: makeId('kb', v.id, mid, 'scenario', 'month_end', 'bn'), facet: 'scenario', subject: 'month_end', lang: 'bn',
      title: `${v.bn}-এ ${term(v, 'cycle', 1)} বন্ধ করার আগে ${m.bn}-এ যা দেখতে হবে`,
      text: `প্রতিটি প্রক্রিয়া শেষ ধাপ পর্যন্ত চলেছে কিনা নিশ্চিত করুন। এরপর পরিচিত ফাঁদগুলো দেখুন — সিস্টেমে এগুলো ইংরেজিতে লেখা: ${m.pitfalls.map((p) => `“${p.en}”`).join('; ')}। শেষে ${bnList((m.talksTo || []).slice(0, 3).map((t) => MODULES[t] ? MODULES[t].bn : t))}-এর সাথে মিলিয়ে দেখুন, আর পার্থক্য থাকলে তা দলিল ধরে ধরে লিখে রাখুন — সমন্বয় করে মুছে ফেলবেন না।`,
      tags: ['scenario', 'troubleshooting'], extra: { mixed: true },
    }));
  }
}

/* ------------------------------------------------------------------
   3. cross-vertical comparisons — the same module read two ways
   ------------------------------------------------------------------ */
for (const v of VERTICALS) {
  const others = VERTICALS.filter((o) => o.id !== v.id && o.kind !== v.kind).slice(0, 5);
  for (const mid of modulesFor(v)) {
    const m = MODULES[mid];
    if (m.onlyFor) continue;                       // only compare modules both sides run
    for (const o of others) {
      push(rec({
        vertical: v, module: { ...m, __id: mid },
        id: makeId('kb', v.id, mid, 'vs', o.id), facet: 'comparison', subject: o.id, lang: 'en',
        title: `${cap(m.en)} at ${an(v.en)} against ${an(o.en)}`,
        text: `The mechanics of ${m.en} are the same in both — ${m.purpose.en} — but what it is measured on is not. A ${v.en} reads it per ${term(v, 'unit')} over ${an(term(v, 'cycle'))}, in terms of the ${term(v, 'customer')} and the ${term(v, 'product')}; ${an(o.en)} reads the same module per ${term(o, 'unit')} over ${an(term(o, 'cycle'))}, for ${an(term(o, 'customer'))} and ${an(term(o, 'product'))}. The headline measures differ accordingly: ${list(v.kpis.slice(0, 2))} against ${list(o.kpis.slice(0, 2))}. Configuration follows the vocabulary, not the other way round.`,
        tags: ['comparison', 'cross-vertical'],
      }));
    }
  }
}

/* ------------------------------------------------------------------
   write
   ------------------------------------------------------------------ */
const langs = rows.reduce((a, r) => { a[r.lang] = (a[r.lang] || 0) + 1; return a; }, {});
const perVertical = rows.reduce((a, r) => { if (r.vertical) a[r.vertical] = (a[r.vertical] || 0) + 1; return a; }, {});

console.log(`knowledge records: ${rows.length.toLocaleString()}`);
console.log(`  languages: ${Object.entries(langs).map(([k, n]) => `${k} ${n.toLocaleString()}`).join(' · ')}`);
console.log('  by facet:');
for (const [f, n] of Object.entries(composition).sort((a, b) => b[1] - a[1])) {
  console.log(`    ${f.padEnd(20)} ${String(n).padStart(7)}`);
}
console.log(`  verticals: ${Object.keys(perVertical).length}, smallest ${Math.min(...Object.values(perVertical)).toLocaleString()}, largest ${Math.max(...Object.values(perVertical)).toLocaleString()}`);

if (!COUNT_ONLY) {
  /* Clear only our own shards. This directory is shared with
     make-questions.mjs, and wiping it wholesale silently deleted the
     188k-question bank the first time the knowledge corpus was
     regenerated — the import then reported 0 questions and the cause
     was three steps upstream of the symptom. */
  mkdirSync(OUT_DIR, { recursive: true });
  for (const f of readdirSync(OUT_DIR)) {
    if (/^kb-\d+\.jsonl$/.test(f) || f === 'kb-manifest.json') rmSync(join(OUT_DIR, f), { force: true });
  }
  let shard = 0;
  for (let i = 0; i < rows.length; i += SHARD_SIZE) {
    const part = rows.slice(i, i + SHARD_SIZE);
    const file = join(OUT_DIR, `kb-${String(++shard).padStart(3, '0')}.jsonl`);
    writeFileSync(file, part.map((r) => JSON.stringify(r)).join('\n') + '\n', 'utf8');
    console.log(`  wrote ${file} (${part.length.toLocaleString()} records)`);
  }
  /* A small, versioned index of the vocabulary, written next to the PHP that
     needs it. KbCorpus has to decide which trade a question is about before it
     can search, and re-reading 127 MB of JSONL to learn 24 names would be
     absurd. This file is committed — unlike the corpus — because it is 10 KB
     and the retrieval layer is useless without it. */
  const VOCAB = resolve(HERE, '..', '..', 'server', 'lib', 'kb-verticals.json');
  writeFileSync(VOCAB, JSON.stringify({
    generated_by: 'tools/kb/make-kb.mjs',
    verticals: VERTICALS.map((v) => ({
      id: v.id, en: v.en, bn: v.bn, kind: v.kind,
      aliases: v.aliases,
      modules: modulesFor(v),
      terms: v.terms,
    })),
    modules: Object.fromEntries(Object.keys(MODULES).map((id) => [id, {
      en: MODULES[id].en, bn: MODULES[id].bn, aliases: MODULES[id].aliases || [],
    }])),
  }, null, 1), 'utf8');
  console.log(`  wrote ${VOCAB}`);

  writeFileSync(join(OUT_DIR, 'kb-manifest.json'), JSON.stringify({
    generated_by: 'tools/kb/make-kb.mjs',
    records: rows.length, shards: shard, shard_size: SHARD_SIZE,
    languages: langs, facets: composition,
    verticals: VERTICALS.length, modules: Object.keys(MODULES).length,
  }, null, 1), 'utf8');
}
