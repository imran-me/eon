/* ============================================================
   EON · cases — contract files and visa processing, by the person.

   "open Razib Hossen's visa"            → visa/{id}/details
   "রাজিবের ভিসা অ্যাপ্রুভড করো"          → PATCH visa/{id}/status  (after a yes)
   "move Karim's file to submitted to vendor"
                                         → PATCH contract-files/{id}/stage
   "what stage is Karim's file at"       → answered from the record
   "Karim er file er stage change koro"  → asks which stage, then the yes

   A case is found by whoever the boss names: the applicant, the client
   or agent who brought it, or the vendor handling it, in either script,
   or the passport number. Several cases → EON asks which.

   The stage list and the visa statuses are the ERP's own
   (ContractFile::STATUSES; VisaProcessingController::updateStatus), so a
   spoken stage is mapped onto one of them or not accepted at all.
   A stage change is not money, but it is still the ERP's record of a
   person's case, so it goes through the same confirmation card.
   ============================================================ */
import { url } from './navigator.js';
import { propose } from './act.js';
import { normalize, speaksBangla } from './voicefix.js';
import { matchName } from './statement.js';
import { csrf } from './ticket.js';

const D0 = () => (typeof window !== 'undefined' && window.EonErp && window.EonErp.dataset ? window.EonErp.dataset() : null);
const BN = /[ঀ-৿]/;
const T = (bn, en, b) => (bn ? b : en);

/* ---------- the ERP's own stages, and how people say them ---------- */
const FILE_STAGES = [
  ['file_received', 'File Received', 'ফাইল গ্রহণ', /file received|received the file|ফাইল (গ্রহণ|পেয়েছি|পেয়েছি|রিসিভ)/i],
  ['information_entered', 'Information Entered', 'তথ্য এন্ট্রি', /information entered|info entered|data entered|তথ্য (এন্ট্রি|দেওয়া)/i],
  ['documents_pending', 'Documents Pending', 'কাগজ বাকি', /documents? pending|docs? pending|কাগজ(পত্র)? বাকি|ডকুমেন্ট বাকি/i],
  ['documents_verified', 'Documents Verified', 'কাগজ যাচাই', /documents? verified|docs? verified|কাগজ(পত্র)? (যাচাই|ভেরিফাই)/i],
  ['application_prepared', 'Application Prepared', 'আবেদন প্রস্তুত', /application prepared|application ready|আবেদন (প্রস্তুত|রেডি)/i],
  ['submitted_to_vendor', 'Submitted to Vendor', 'ভেন্ডরে জমা', /submitted|submit(ted)? to (the )?vendor|ভেন্ডরে জমা|জমা (দেওয়া|হয়েছে|হয়েছে|দিয়েছি|দিয়েছি)|সাবমিট/i],
  ['biometrics_appointment', 'Biometrics / Appointment', 'বায়োমেট্রিক / অ্যাপয়েন্টমেন্ট', /biometric|appointment|বায়োমেট্রিক|বায়োমেট্রিক|অ্যাপয়েন্টমেন্ট/i],
  ['under_process', 'Under Process', 'প্রসেসে আছে', /under process|processing|in process|প্রসেস(ে|িং)/i],
  ['additional_documents', 'Additional Documents', 'অতিরিক্ত কাগজ', /additional documents?|more documents?|extra documents?|অতিরিক্ত কাগজ/i],
  ['decision_received', 'Decision Received', 'সিদ্ধান্ত এসেছে', /decision (received|came)|সিদ্ধান্ত/i],
  ['approved', 'Approved', 'অনুমোদিত', /\bapproved?\b|অনুমোদ|অ্যাপ্রুভ|এপ্রুভ/i],
  ['passport_ready', 'Passport Ready', 'পাসপোর্ট প্রস্তুত', /passport (is )?ready|পাসপোর্ট (রেডি|প্রস্তুত)/i],
  ['delivered', 'Delivered', 'হস্তান্তর', /\bdelivered\b|handed over|ডেলিভার|হস্তান্তর|বুঝিয়ে দেওয়া/i],
  ['case_closed', 'Case Closed', 'কেস বন্ধ', /case closed|\bclose\b|কেস (বন্ধ|ক্লোজ)|ফাইল বন্ধ/i],
];
const VISA_STATUS = [
  ['pending', 'Pending', 'অপেক্ষমাণ', /\bpending\b|অপেক্ষ|পেন্ডিং/i],
  ['received', 'Received', 'গ্রহণ করা হয়েছে', /\breceived\b|রিসিভ|গ্রহণ/i],
  ['in_embassy', 'In Embassy', 'দূতাবাসে', /in (the )?embassy|embassy|দূতাবাস|অ্যাম্বেসি|এম্বেসি/i],
  ['approved', 'Approved', 'অনুমোদিত', /\bapproved?\b|অনুমোদ|অ্যাপ্রুভ|এপ্রুভ/i],
  ['delivered', 'Delivered', 'হস্তান্তর', /\bdelivered\b|handed over|ডেলিভার|হস্তান্তর/i],
  ['rejected', 'Rejected', 'প্রত্যাখ্যাত', /\breject(ed)?\b|refused|denied|রিজেক্ট|প্রত্যাখ্যান|বাতিল হয়েছে/i],
];
const stageOf = (list, s) => {
  // the longest spoken match wins: "documents verified" over "documents"
  let best = null, len = 0;
  for (const st of list) { const m = s.match(st[3]); if (m && m[0].length > len) { best = st; len = m[0].length; } }
  return best;
};

const IS_FILE = /\bcontract\s*files?\b|\bfiles?\b|কন্ট্রাক্ট\s*ফাইল|ফাইল/i;
const IS_VISA = /\bvisa\b|ভিসা/i;
const OPEN = /\b(open|show|view|go to|take me|pull up|dekhao|kholo|dekhan)\b|দেখাও|খোলো|খুলুন|দেখান/i;
const CHANGE = /\b(move|mark|set|change|update|make|put|stage|status|close|koro|kore dao)\b|করো|করুন|করে দাও|দাও|মার্ক|আপডেট|বদলাও|পরিবর্তন/i;
const WHERE = /\b(ki obostha|obostha ki|kon stage|kon obosthay|koto dur|kotodur)\b|\b(what|which|where|status|stage|how far|kon|ki obostha|obostha)\b.*\b(stage|status|is|at|now)\b|কোন\s*(স্টেজে|অবস্থায়|অবস্থায়|পর্যায়ে|পর্যায়ে)|কী অবস্থা|কি অবস্থা|কতদূর|স্ট্যাটাস কী|স্ট্যাটাস কি/i;
const COUNT = /\b(how many|count|list|total|koyta|kotogula)\b|কয়টা|কয়টা|কতগুলো|কতজন|তালিকা/i;
const CANCEL = /^\s*(cancel|stop|leave it|thak|bad dao)\s*[.!]?\s*$|^\s*(থাক|বাদ দাও|বাতিল)\s*[।.!]?\s*$/i;

/* ---------- the people a case is known by ---------- */
function cases(D, want) {
  const out = [];
  if (want !== 'visa') for (const f of (D && D.contract_files) || []) out.push({ kind: 'file', id: f.id, ref: f.file_number, applicant: f.applicant, passport_no: f.passport_no, client: f.client, vendor: f.vendor, country: f.country, status: f.status });
  if (want !== 'file') for (const v of (D && D.visa_processes) || []) out.push({ kind: 'visa', id: v.id, ref: v.application_id, applicant: v.applicant, passport_no: null, client: null, vendor: null, country: v.country, status: v.status });
  return out;
}
function findCases(D, s, want) {
  const all = cases(D, want);
  const pass = s.match(/\b([A-Z]{1,2}\d{6,9})\b/i);
  if (pass) { const hit = all.filter((c) => String(c.passport_no || '').toUpperCase() === pass[1].toUpperCase()); if (hit.length) return { rows: hit }; }
  const ref = s.match(/\b(?:file|application)\s*(?:no\.?|number|#)?\s*([A-Z0-9-]{3,20})\b/i);
  if (ref) { const hit = all.filter((c) => c.ref && String(c.ref).toUpperCase() === ref[1].toUpperCase()); if (hit.length) return { rows: hit }; }
  const names = [], seen = new Set();
  for (const c of all) for (const role of ['applicant', 'client', 'vendor']) {
    const n = c[role]; if (!n) continue; const k = role + '|' + n; if (seen.has(k)) continue; seen.add(k);
    names.push({ id: k, name: String(n).trim(), role });
  }
  const who = matchName(names, s);
  if (!who) return null;
  if (who.ambiguous) return { ambiguous: who.ambiguous };
  return { who, rows: all.filter((c) => String(c[who.role] || '').trim() === who.name) };
}

const labelOf = (c, bn) => {
  const list = c.kind === 'file' ? FILE_STAGES : VISA_STATUS;
  const st = list.find((x) => x[0] === c.status);
  return st ? st[bn ? 2 : 1] : (c.status || '—');
};
const caseName = (c, bn) => `${c.applicant || '—'}${bn ? '-এর' : "'s"} ${c.kind === 'file' ? T(bn, 'contract file', 'কন্ট্রাক্ট ফাইল') : T(bn, 'visa', 'ভিসা')}${c.country ? ` (${c.country})` : ''}`;
const detailsUrl = (c) => url(c.kind === 'file' ? `/{role}/contract-files/${c.id}/details` : `/{role}/visa/${c.id}/details`);

/* ---------- the held conversation: which case, which stage ---------- */
let convo = null;   // { stage: 'pick'|'status', rows?, c?, bn, at, intent }
export function forget() { convo = null; }
export function conversation() { return convo; }

async function change(c, st, bn) {
  convo = null;
  if (c.status === st[0]) return { speak: T(bn, `${caseName(c, false)} is already at ${st[1]}.`, `${caseName(c, true)} আগে থেকেই ${st[2]} অবস্থায় আছে।`), detail: [] };
  let token; try { token = await csrf(); } catch (e) { return { speak: T(bn, 'I have changed nothing — the ERP wants you signed in first.', 'কিছুই বদলাইনি — ইআরপি আগে সাইন ইন চায়।'), detail: [] }; }
  const file = c.kind === 'file';
  const from = labelOf(c, bn);
  return propose({
    what: file ? 'file_stage' : 'visa_status', lang: bn ? 'bn' : 'en', money: false, missing: [],
    summary: `Move ${caseName(c, false)} from ${labelOf(c, false)} to ${st[1]}`,
    summary_bn: `${caseName(c, true)} ${from} থেকে ${st[2]}-এ নেওয়া`,
    form: {
      action: url(file ? `/{role}/contract-files/${c.id}/stage` : `/{role}/visa/${c.id}/status`), token, method: 'PATCH',
      fields: [{ name: file ? 'stage' : 'status', label: T(bn, 'New stage', 'নতুন অবস্থা'), options: (file ? FILE_STAGES : VISA_STATUS).map((x) => ({ value: x[0], text: x[bn ? 2 : 1] })) }, { name: 'note', label: T(bn, 'Note', 'নোট') }],
      from: file ? `/{role}/contract-files/${c.id}/details` : `/{role}/visa/${c.id}/details`,
    },
    values: file ? { stage: st[0], note: T(bn, "Moved by EON on the boss's order", 'বসের নির্দেশে EON সরিয়েছে') } : { status: st[0] },
    notes: [],
  });
}

function askStage(c, bn) {
  const list = c.kind === 'file' ? FILE_STAGES : VISA_STATUS;
  convo = { stage: 'status', c, bn, at: Date.now() };
  return {
    speak: T(bn, `${caseName(c, false)} is at ${labelOf(c, false)}. Move it to which stage? ${list.map((x) => x[1]).join(', ')}.`,
      `${caseName(c, true)} এখন ${labelOf(c, true)}। কোন অবস্থায় নেব? ${list.map((x) => x[2]).join(', ')}।`),
    detail: [], navigate: detailsUrl(c), actions: [{ label: T(bn, 'Open the case', 'কেস খুলুন'), kind: 'erp-open', href: detailsUrl(c) }],
  };
}

async function act(c, intent, s, bn) {
  if (intent === 'where') {
    convo = null;
    return { speak: T(bn, `${caseName(c, false)} is at ${labelOf(c, false)}.`, `${caseName(c, true)} এখন ${labelOf(c, true)} অবস্থায়।`), detail: [], actions: [{ label: T(bn, 'Open the case', 'কেস খুলুন'), kind: 'erp-open', href: detailsUrl(c) }] };
  }
  if (intent === 'open') {
    convo = null;
    const href = detailsUrl(c);
    return { speak: T(bn, `Opening ${caseName(c, false)} — it is at ${labelOf(c, false)}.`, `${caseName(c, true)} খুলছি — এখন ${labelOf(c, true)} অবস্থায়।`), detail: [], actions: [{ label: T(bn, 'Open', 'খুলুন'), kind: 'erp-open', href }], navigate: href };
  }
  const st = stageOf(c.kind === 'file' ? FILE_STAGES : VISA_STATUS, s);
  return st ? change(c, st, bn) : askStage(c, bn);
}

async function answer(q) {
  const raw = String(q || '').trim();
  const s = normalize(raw);
  if (!s) return null;
  if (convo && Date.now() - convo.at > 4 * 60 * 1000) convo = null;

  if (convo) {
    if (CANCEL.test(raw)) { const bn = convo.bn; convo = null; return { speak: T(bn, 'Left it — nothing was changed.', 'থাক, কিছুই বদলাইনি।'), detail: [] }; }
    if (convo.stage === 'status') {
      const st = stageOf(convo.c.kind === 'file' ? FILE_STAGES : VISA_STATUS, s);
      if (st) return change(convo.c, st, convo.bn);
      if (!IS_FILE.test(s) && !IS_VISA.test(s)) return null;   // not an answer — the conversation waits
    }
    if (convo && convo.stage === 'pick') {
      const n = (s.match(/^\s*(\d{1,2})\s*[.।]?\s*$/) || [])[1];
      const pick = n ? convo.rows[+n - 1] : convo.rows.find((c) => (c.ref && s.toUpperCase().includes(String(c.ref).toUpperCase())) || (c.country && new RegExp(`\\b${c.country}\\b`, 'i').test(s)));
      if (pick) { const { intent, bn } = convo; convo = null; return act(pick, intent, convo ? '' : convo_text, bn); }
      if (!IS_FILE.test(s) && !IS_VISA.test(s)) return null;
    }
  }

  const file = IS_FILE.test(s), visa = IS_VISA.test(s);
  if (!file && !visa) return null;
  if (COUNT.test(s)) return null;   // "how many visas are pending" is a figure question
  const intent = WHERE.test(s) ? 'where' : OPEN.test(s) && !stageOf(FILE_STAGES.concat(VISA_STATUS), s) ? 'open' : CHANGE.test(s) || stageOf(FILE_STAGES.concat(VISA_STATUS), s) ? 'change' : null;
  if (!intent) return null;
  const D = D0(); if (!D) return null;
  const want = file && !visa ? 'file' : visa && !file ? 'visa' : null;
  const bn = speaksBangla(raw);
  const found = findCases(D, s, want);
  if (!found) return null;   // nobody named — the navigator opens the screens themselves
  if (found.ambiguous) return { speak: T(bn, `Whose? These match: ${found.ambiguous.map((x) => x.name).join(', ')}. Say the full name.`, `কার? মিলছে: ${found.ambiguous.map((x) => x.name).join(', ')}। পুরো নামটা বলুন।`), detail: [] };
  const rows = found.rows;
  if (!rows.length) return null;
  if (rows.length === 1) { convo_text = s; return act(rows[0], intent, s, bn); }
  convo = { stage: 'pick', rows, intent, bn, at: Date.now() };
  convo_text = s;
  const list = rows.slice(0, 6).map((c, i) => `${i + 1}. ${caseName(c, bn)} — ${labelOf(c, bn)}`);
  return { speak: T(bn, `${found.who ? found.who.name : 'That name'} has ${rows.length} cases — which one? ${list.join('; ')}.`, `${found.who ? found.who.name : 'এই নামে'}-এর ${rows.length}টা কেস — কোনটা? ${list.join('; ')}।`), detail: [] };
}
let convo_text = '';

if (typeof window !== 'undefined') {
  window.EonCases = { answer, forget, conversation };
  (window.__eonDomainQueue = window.__eonDomainQueue || []).push({ id: 'cases', priority: 99.4, answer });
}
export default { answer, forget, conversation };
