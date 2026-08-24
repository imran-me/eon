/* ============================================================
   tools/kb/phrasing.mjs — the language layer of the corpus.

   Two jobs:
     • FIELD_NOTES  — what a field name actually means, so a
       field record says something rather than restating its own
       name. Keyed by exact name first, then by suffix/keyword.
     • templates and helpers — English and Bangla sentence
       builders, plus the vertical-noun swap.
   ============================================================ */

/* ---------- what common field names mean, in ERP terms ---------- */
export const FIELD_NOTES = {
  /* identity and reference */
  'code': 'the short unique key the rest of the system points at; changing it after transactions exist breaks history',
  'name': 'the human label shown in lists and reports; it may repeat, so it must never be the key',
  'number': 'a system-issued sequence number; it must be gapless where the law or an auditor expects it to be',
  'reference': 'the outside world identifier for this record — a supplier number, a bank reference, a challan',
  'status': 'where the record sits in its own lifecycle; it decides what may be done to it next, not just how it is displayed',
  'serial': 'the order in a queue, issued at registration and called in sequence',
  'barcode': 'the scannable identity that makes counter and warehouse work fast enough to be accurate',
  'tag number': 'the identity physically attached to the item, so the register can be walked rather than believed',
  'unique id': 'the one identity that survives across activities, so the same person is never counted twice',

  /* money */
  'amount': 'the value of the transaction in the recording currency, before any allocation',
  'rate': 'the price of one unit; the amount is derived from it, so the rate is what needs the control',
  'total': 'the sum after tax and discount — the figure the counterparty actually owes or is owed',
  'balance': 'what remains after everything applied so far; it is derived, never typed',
  'paid': 'the part already settled, used to age the rest',
  'discount': 'the amount given away against list price, which is why it needs an approver and a reason',
  'tax': 'the tax carried by this document, held in its own account and never mixed with income',
  'cost': 'what it cost us, as against what it was sold for; both are needed or margin is unknowable',
  'price': 'what the customer is charged, driven from a price list rather than typed at the counter',
  'value': 'the money worth of the record, used for approval thresholds and reporting',
  'fee': 'the charge for the service itself, kept apart from anything collected on behalf of somebody else',
  'currency': 'the currency this record is denominated in; a rate and a date must travel with it',
  'opening balance': 'what was carried in from before the system started, posted once against opening balance equity',
  'credit limit': 'the ceiling on unpaid exposure to this party, which is only real if a screen refuses to exceed it',
  'deposit': 'money held as security, a liability until it is returned or applied',
  'advance': 'money received or paid before the work, held against the future document rather than treated as settled',

  /* time */
  'date': 'the date the event happened, which may differ from the date it was entered — both matter',
  'due date': 'when it must be settled; ageing and every reminder are computed from this, not from the document date',
  'from': 'the start of the period this record covers',
  'to': 'the end of the period this record covers',
  'effective from': 'the date this version starts to apply, so history stays computable after a change',
  'expiry': 'the date after which this is no longer valid; alerts belong before it, not after',
  'expires': 'the date after which this is no longer valid; alerts belong before it, not after',
  'valid until': 'the last date this stays binding, after which it must be reissued rather than assumed',
  'created on': 'when the record was first raised, which is the clock every ageing report runs on',
  'period': 'the accounting or reporting window this belongs to, which is what a closed period locks',

  /* people and control */
  'approved by': 'who authorised it; without this the document is a draft with a confident font',
  'requested by': 'who asked for it, which is also who may not approve it',
  'entered by': 'who keyed it, kept for the audit trail rather than for blame',
  'issued by': 'who released the item or the document, and is answerable for the quantity',
  'owner': 'the named person answerable for this record; anything unowned is never closed',
  'custodian': 'who physically holds it now, so a shortfall has an address',
  'assigned to': 'who is doing the work; unassigned work is work nobody is doing',
  'approver': 'the person the workflow routes this to, decided by rule and value, not by convenience',
  'consent': 'whether this person agreed to be contacted or to have their data used, which decides what may be sent',

  /* structure */
  'company': 'which legal entity this belongs to; balances must never be shared across entities',
  'branch': 'which location it belongs to, both a cost centre and a physical door',
  'department': 'the organisational unit that carries the cost',
  'cost centre': 'the reporting bucket the cost lands in, which lets one ledger answer per unit',
  'category': 'the classification that decides the default account and every grouped report',
  'type': 'a fixed list, not free text — reporting depends on it being closed',
  'parent': 'the record above this one in the tree; parents total their children and take no postings themselves',
  'project': 'the project this cost or revenue belongs to, without which project profitability is guesswork',
  'location': 'where it physically is, which is what makes a count possible',
  'store': 'which store or warehouse holds it; quantity without a location cannot be verified',

  /* quantity */
  'quantity': 'how many, in the item base unit — mixing cartons and pieces here is a classic stock disaster',
  'unit': 'the unit of measure this quantity is expressed in, fixed once per item with exact conversions',
  'capacity': 'the most this can hold or serve, which is what utilisation is measured against',
  'weight': 'the weight used for freight costing and for allocating landed cost',
  'lines': 'the individual rows of the document; the header totals are derived from them',

  /* notes and evidence */
  'reason': 'why this was done, chosen from a fixed list so it can be counted rather than read',
  'note': 'free text for the human reader; nothing that a report needs should live only here',
  'narration': 'the sentence that explains the entry to whoever reads the ledger a year later',
  'description': 'what this is, in words, for the counterparty rather than for the system',
  'document': 'the attached evidence; an approval with no document behind it is the first thing an auditor samples',
  'attachment': 'the scanned paper that supports the transaction, versioned rather than overwritten',
};

/* suffix and keyword fallbacks, tried in order when there is no exact match */
const FIELD_PATTERNS = [
  [/(supplier|vendor|buyer|customer|client|member|patient|student|guest|passenger|applicant|dealer|beneficiary|subscriber|donor|payer)/i,
   'the counterparty this record belongs to, taken from the master list rather than typed, so one party never splits into two balances'],
  [/(employee|staff|officer|driver|technician|operator|faculty|consultant|surgeon|picker|rider|cashier|inspector|custodian)/i,
   'the person carrying out or answerable for this record, which is what turns a total into something somebody can be asked about'],
  [/(bank|cash|account)/i, 'which account the money moves through, without which reconciliation is guesswork'],
  [/(tax|vat|duty|withholding|tds|vds)/i, 'the tax carried here, posted to its own account and reported on its own register'],
  [/(address|contact|phone|email)/i, 'how this party is reached; poor address quality is a delivery and collection cost long before it is a data problem'],
  [/(batch|lot|serial|imei|chassis|registration)/i, 'the identity of the specific physical item, which is what makes a recall or a warranty claim traceable'],
  [/(expiry|manufactured|shelf)/i, 'the date that decides whether this stock may still be sold, and in what order it should leave'],
  [/(shift|roster|schedule|session|slot)/i, 'the working window this belongs to, which is what makes lateness, overtime and capacity computable'],
  [/(policy|terms|condition|rule|scheme|plan)/i, 'the agreed basis this record is governed by; storing it here is what stops it living in somebody memory'],
  [/(target|budget|standard|planned|estimate)/i, 'the intended figure, kept beside the actual so a variance can be computed rather than argued'],
  [/(actual|achieved|delivered|received|issued|collected|produced|paid)/i, 'what really happened, which is only useful when the planned figure is stored beside it'],
  [/(variance|gap|difference|shortfall|overdue|outstanding)/i, 'a derived difference; it must be computed at read time, never stored, or it will disagree with its own inputs'],
  [/(commission|margin|markup|share)/i, 'the part of the money that is actually ours, as against the part passing through us'],
  [/(priority|severity|criticality|urgency)/i, 'how quickly this must be dealt with, which is what makes a queue an order rather than a list'],
  [/(warranty|guarantee|retention|security|lien)/i, 'the obligation or holdback attached to this record, which usually outlives the transaction itself'],
  [/(route|zone|territory|area|village|union|centre|hub|site|ward|bay|line|shed|bin|room|bed)/i,
   'the physical or operational place this belongs to, which is the level most operational questions are actually asked at'],
  [/(method|mode|channel|source|basis)/i, 'how or where this arrived, which is usually the most decision-useful field on the record and the one most often left blank'],
  [/(currency|exchange|fx)/i, 'the currency and the rate used, both of which must travel with the amount or the figure cannot be restated'],
  [/(capacity|occupied|available|utilisation|load)/i, 'the measure of how much of a fixed resource is in use, which is what every utilisation figure divides by'],
  [/(document|certificate|licence|permit|evidence|proof|acknowledgement|challan|receipt)/i,
   'the evidence behind the record; approvals without it are the first thing sampled in any audit'],
  [/(^|\s)id$/i, 'the internal identifier used to link this record to others; it is not meant to be read by people'],
  [/date$/i, 'the date this stage happened, which is what every ageing and lateness measure is computed from'],
  [/ at$/i, 'the timestamp of this stage, which turns a status into something measurable'],
  [/^(is|has|requires|allows)/i, 'a yes-or-no flag that changes how the record behaves, not merely how it looks'],
  [/(amount|value|total|charge|cost|fee|price)$/i, 'a money figure on this record, which must be derived from lines and rates rather than typed where possible'],
  [/(quantity|qty|count|hours|days|minutes)$/i, 'a measured quantity, which is only comparable if its unit is fixed and stated'],
  [/(rate|percentage|ratio)$/i, 'a rate applied to a base; store it dated, because rates change and old documents must still recompute'],
  [/(by)$/i, 'the person answerable for this step, kept so the trail names somebody'],
  [/(number|no)$/i, 'the identifying number for this record, unique within its own series'],
  [/(limit|threshold|level)$/i, 'the boundary at which behaviour changes — a warning, a block or an escalation'],
  [/(expiry|expires|valid)/i, 'a validity date that needs an alert before it, not a discovery after it'],
  [/(status|state|stage)$/i, 'where this sits in its lifecycle, which governs what may happen to it next'],
];

export function fieldNote(field) {
  const key = String(field || '').toLowerCase().trim();
  if (FIELD_NOTES[key]) return FIELD_NOTES[key];
  for (const [k, v] of Object.entries(FIELD_NOTES)) {
    if (key.endsWith(' ' + k) || key.startsWith(k + ' ')) return v;
  }
  for (const [re, note] of FIELD_PATTERNS) if (re.test(key)) return note;
  return null;
}

/* ---------- vertical noun swap ---------- */
/** term(v, 'customer') → ['passenger','যাত্রী'] with a safe default */
export function term(vertical, key, lang = 0) {
  const t = vertical.terms && vertical.terms[key];
  const fallback = { customer: ['customer', 'গ্রাহক'], product: ['item', 'পণ্য'], revenue: ['revenue', 'আয়'],
                     staff: ['staff member', 'কর্মী'], unit: ['unit', 'ইউনিট'], cycle: ['cycle', 'চক্র'] }[key] || ['record', 'রেকর্ড'];
  return (t || fallback)[lang];
}

/* ---------- small helpers ---------- */
export const cap = (s) => (s ? s.charAt(0).toUpperCase() + s.slice(1) : s);

/* "a" or "an", decided by how the word is said rather than how it is spelt.
   The templates put a variable noun straight after "A", which produced "A
   admission test", "A enrolment" and "A invoice" in thousands of records —
   invisible on a page of JSON and impossible to miss the moment EON reads one
   aloud. A bare vowel test is not enough in either direction: "a university"
   and "a user" begin with a consonant sound, "an hour" and "an honest mistake"
   with a vowel one. */
const A_NOT_AN = /^(uni|use|usu|uti|ubi|eu|ewe|one|once)/i;
const AN_NOT_A = /^(hour|honest|honou?r|heir)/i;
/* An initialism is read letter by letter, so the article follows the name of the
   first letter, not the letter: "an NGO" (en-gee-oh), "an MPO", "an SKU", but
   "a GRN" and "a PO". Spelling alone gets every one of these backwards. */
const VOWEL_LETTER_NAMES = /^[AEFHILMNORSX]$/;
export const art = (word) => {
  const w = String(word || '').trim();
  if (!w) return 'a';
  if (/^[A-Z]{2,}$/.test(w.split(/[\s-]/)[0])) {
    return VOWEL_LETTER_NAMES.test(w[0]) ? 'an' : 'a';
  }
  if (AN_NOT_A.test(w)) return 'an';
  if (A_NOT_AN.test(w)) return 'a';
  return /^[aeiou]/i.test(w) ? 'an' : 'a';
};
/** the article and the word, which is what every caller actually wants */
export const an = (word) => `${art(word)} ${word}`;

export const list = (arr, conj = 'and') => {
  const a = arr.filter(Boolean);
  if (a.length <= 1) return a[0] || '';
  if (a.length === 2) return `${a[0]} ${conj} ${a[1]}`;
  return `${a.slice(0, -1).join(', ')} ${conj} ${a[a.length - 1]}`;
};
export const bnList = (arr) => {
  const a = arr.filter(Boolean);
  if (a.length <= 1) return a[0] || '';
  return `${a.slice(0, -1).join(', ')} ও ${a[a.length - 1]}`;
};

/* Bengali digits. A Bangla sentence carrying "2টি" reads as broken to the
   person it is written for, and the speech layer says it in English. */
export const bnNum = (n) => String(n).replace(/[0-9]/g, (d) => '০১২৩৪৫৬৭৮৯'[+d]);

/* A short deterministic hash — FNV-1a, base36. Not for security; for identity. */
function fnv(s) {
  let h = 0x811c9dc5;
  for (let i = 0; i < s.length; i++) { h ^= s.charCodeAt(i); h = Math.imul(h, 0x01000193) >>> 0; }
  return h.toString(36);
}

/* Stable id: no randomness, so a regenerated corpus keeps its keys.
   The ascii slug alone is not an identity — every বাংলা segment reduces to
   the empty string, so a module's Bangla aliases all produced the same id
   and 637 glossary records silently overwrote each other on import. Any
   segment carrying a character the slug cannot represent gets a hash of
   the original appended, so it stays readable and stays unique. */
export function makeId(prefix, ...parts) {
  const slug = (p) => {
    const s = String(p).toLowerCase();
    const ascii = s.replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
    const lost = /[^ -]/.test(s);
    if (!ascii) return 'x' + fnv(s);
    return lost ? `${ascii}_${fnv(s)}` : ascii;
  };
  return `${prefix}-${parts.map(slug).join('.')}`;
}

/* a compact keyword bag for lexical retrieval */
export function keywords(...bits) {
  const seen = new Set();
  const out = [];
  for (const b of bits.flat()) {
    for (const w of String(b || '').toLowerCase().split(/[^a-z0-9ঀ-৿]+/)) {
      if (w.length < 3 || seen.has(w)) continue;
      seen.add(w); out.push(w);
    }
  }
  return out.slice(0, 40);
}

export default { FIELD_NOTES, fieldNote, term, cap, list, bnList, bnNum, makeId, keywords };
