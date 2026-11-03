/* ============================================================
   EON · formfill — EON works IN the ERP's own forms, on screen.

   Docked in the workspace, the ERP is a same-origin frame beside EON, so
   EON can open any create / edit screen there and type into it the way a
   person would: "নতুন টিকেট এন্ট্রি — রফিকুল, PNR AB12CD, ঢাকা-দাম্মাম,
   ১২ নভেম্বর, ৫২ হাজার". Every value is matched to a field by the form's
   own labels and names (passenger, PNR, route, date, amount, passport,
   phone, airline, vendor, agent…), set with real input/change events so
   the ERP's own scripts react, and highlighted. Required fields still
   empty are asked for. "সেভ করো" presses the form's own Save — only after
   a yes. "প্রিন্ট করো" prints the screen or opens its print view; "এডিট
   করো" opens the record's edit form.

   It covers every form in the ERP generically; the plugins for ticket
   changes, offload/deport, ledgers, expenses and payments keep their own
   exact flows and run before this one.
   ============================================================ */
import { speaksBangla, normalize } from './voicefix.js';
import { recall, remember } from './context.js';

const N = () => (typeof window !== 'undefined' ? window.EonNavigator : null);
const T = (bn, en, b) => (bn ? b : en);
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

/** the ERP frame's window, when EON is docked beside it */
function erpWin() {
  try { const f = window.parent && window.parent.document.getElementById('erp'); return f ? f.contentWindow : null; } catch (e) { return null; }
}
async function openAndWait(href) {
  const w0 = erpWin(); const before = w0 ? w0.location.href : '';
  N().go(href);
  for (let i = 0; i < 60; i++) {
    await sleep(250);
    const w = erpWin();
    try { if (w && w.location.href !== before && w.document.readyState === 'complete' && w.document.querySelector('form')) return w; } catch (e) {}
  }
  return erpWin();
}

/* ---------- what the sentence carries ---------- */
const BN_DIGIT = (s) => String(s).replace(/[০-৯]/g, (d) => '০১২৩৪৫৬৭৮৯'.indexOf(d));
const MONTHS = { jan: 1, feb: 2, mar: 3, apr: 4, may: 5, jun: 6, jul: 7, aug: 8, sep: 9, oct: 10, nov: 11, dec: 12, জানুয়ারি: 1, ফেব্রুয়ারি: 2, মার্চ: 3, এপ্রিল: 4, মে: 5, জুন: 6, জুলাই: 7, আগস্ট: 8, সেপ্টেম্বর: 9, অক্টোবর: 10, নভেম্বর: 11, ডিসেম্বর: 12 };
const CITY = { ঢাকা: 'DAC', dhaka: 'DAC', দাম্মাম: 'DMM', dammam: 'DMM', দুবাই: 'DXB', dubai: 'DXB', রিয়াদ: 'RUH', riyadh: 'RUH', জেদ্দা: 'JED', jeddah: 'JED', দোহা: 'DOH', doha: 'DOH', কুয়েত: 'KWI', kuwait: 'KWI', মাসকাট: 'MCT', muscat: 'MCT', ইস্তাম্বুল: 'IST', istanbul: 'IST', এরকান: 'ECN', ercan: 'ECN', কুয়ালালামপুর: 'KUL', 'kuala lumpur': 'KUL', সিঙ্গাপুর: 'SIN', singapore: 'SIN', চট্টগ্রাম: 'CGP', chittagong: 'CGP', সিলেট: 'ZYL', sylhet: 'ZYL', বাহরাইন: 'BAH', bahrain: 'BAH', মদিনা: 'MED', medina: 'MED', আবুধাবি: 'AUH', 'abu dhabi': 'AUH', শারজাহ: 'SHJ', sharjah: 'SHJ', লন্ডন: 'LHR', london: 'LHR', কলকাতা: 'CCU', kolkata: 'CCU' };

function slotsOf(raw) {
  const s = BN_DIGIT(raw);
  const out = {};
  const pnr = s.match(/\b(?=[A-Z0-9]*\d)(?=[A-Z0-9]*[A-Z])[A-Z0-9]{6}\b/); if (pnr) out.pnr = pnr[0];
  const tno = s.match(/\b\d{3}-?\d{10}\b/); if (tno) out.ticket_no = tno[0];
  const pass = s.match(/\b[A-Z]{1,2}\d{7,8}\b/); if (pass) out.passport = pass[0];
  const phone = s.match(/\b01[3-9]\d{8}\b/); if (phone) out.phone = phone[0];
  // a route: two cities (any script) or two IATA codes
  const codes = [];
  for (const [k, v] of Object.entries(CITY)) { const i = s.toLowerCase().indexOf(k.toLowerCase()); if (i >= 0) codes.push([i, v]); }
  (s.match(/\b[A-Z]{3}\b/g) || []).forEach((c) => { if (!['PNR', 'BDT'].includes(c) && Object.values(CITY).includes(c)) codes.push([s.indexOf(c), c]); });
  codes.sort((a, b) => a[0] - b[0]); const uniq = [...new Set(codes.map((x) => x[1]))];
  if (uniq.length >= 2) { out.from = uniq[0]; out.to = uniq[1]; out.route = `${uniq[0]}-${uniq[1]}`; }
  // a date
  const today = new Date();
  for (const [name, m] of Object.entries(MONTHS)) {
    const rx = new RegExp(`(\\d{1,2})\\s*(?:st|nd|rd|th|ই|শে|তারিখ)?\\s*${name}|${name}\\s*(\\d{1,2})`, 'i'); const mm = s.match(rx);
    if (mm) { const d = +(mm[1] || mm[2]); let y = today.getFullYear(); if (new Date(y, m - 1, d) < new Date(today.getFullYear(), today.getMonth() - 4, 1)) y++; out.date = `${y}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`; break; }
  }
  if (!out.date && /আজ|today/i.test(s)) out.date = today.toISOString().slice(0, 10);
  if (!out.date && /আগামীকাল|tomorrow/i.test(s)) out.date = new Date(Date.now() + 864e5).toISOString().slice(0, 10);
  // amounts: "৫২ হাজার", "1.5 lakh", "52000"; a second one is the cost / purchase when both are said
  // passport and phone digits are not money
  const sm = [out.passport, out.phone, out.ticket_no].filter(Boolean).reduce((t, x) => t.split(x).join(' '), s);
  const am = [...sm.matchAll(/(\d+(?:\.\d+)?)\s*(হাজার|hajar|thousand|k\b|লাখ|লক্ষ|lakh|lac)?\s*(টাকা|taka|tk)?/gi)]
    .map((m) => ({ v: Math.round(parseFloat(m[1]) * (/হাজার|hajar|thousand|k/i.test(m[2] || '') ? 1e3 : /লাখ|লক্ষ|lakh|lac/i.test(m[2] || '') ? 1e5 : 1)), unit: m[2] || m[3], at: m.index, ctx: sm.slice(Math.max(0, m.index - 14), m.index) }))
    .filter((a) => a.v >= 100 && (a.unit || a.v >= 1000) && String(a.v) !== (out.phone || '') && !(out.date && out.date.endsWith(String(a.v).padStart(2, '0'))));
  for (const a of am) {
    if (/কেনা|purchase|cost|খরচ|কিনছি|ভেন্ডর|net/i.test(a.ctx)) out.cost = out.cost ?? a.v;
    else if (/বিক্রি|sale|sell|দাম|price|এজেন্ট|ক্লায়েন্ট/i.test(a.ctx)) out.sale = out.sale ?? a.v;
    else if (out.amount == null) out.amount = a.v; else out.amount2 = out.amount2 ?? a.v;
  }
  if (out.amount != null && out.sale == null && out.cost == null) out.sale = out.amount;
  if (out.amount2 != null && out.cost == null) out.cost = out.amount2;
  // a person's name: "X-এর জন্য", "for X", "passenger X"
  // vowel signs are letters too (\p{M}); an English name stops at a city, a keyword or a number
  const nm = s.match(/(?:যাত্রী|passenger|নাম|name)\s*[:\-]?\s*([\p{L}\p{M}.\s]{3,40}?)(?=,|।|$|\s+(?:এর|জন্য|PNR|পিএনআর))/u) || s.match(/([\p{L}\p{M}.]+(?:\s+[\p{L}\p{M}.]+){0,3}?)(?:এর|য়ের|ের)\s+জন্য/u) || s.match(/\bfor\s+((?:(?!dhaka|from|to\b|on\b|pnr|price|passport|phone|at\b)[A-Za-z.]+\s*){1,4})/i);
  if (nm) out.name = nm[1].replace(/(এর|ের|য়ের)$/u, '').trim();
  return out;
}

/* ---------- which field a value belongs in, by the form's own words ---------- */
const FIELD = {
  name: /passenger|traveller|traveler|guest|customer name|full name|applicant|^name$|name\b|যাত্রী|নাম/i,
  pnr: /pnr|booking ref|reference/i, ticket_no: /ticket ?(no|number)|e-?ticket/i,
  passport: /passport/i, phone: /phone|mobile|contact/i,
  route: /route|sector|itinerary/i, from: /^from|origin|departure (city|airport)|from_/i, to: /^to\b|destination|arrival (city|airport)|to_/i,
  date: /travel ?date|departure|flight ?date|journey|issue ?date|purchase ?date|date/i,
  sale: /sale|selling|price|fare(?! diff)|amount|total/i, cost: /cost|purchase ?price|buying|net ?fare|vendor ?price/i,
};
function labelOf(doc, el) {
  const id = el.id && doc.querySelector(`label[for="${el.id}"]`);
  return ((id && id.textContent) || el.getAttribute('placeholder') || el.getAttribute('aria-label') || (el.closest('.form-group,.mb-3,.col,div') || {}).querySelector?.('label')?.textContent || el.name || '').trim();
}
function setValue(w, el, v) {
  const proto = el.tagName === 'SELECT' ? w.HTMLSelectElement.prototype : el.tagName === 'TEXTAREA' ? w.HTMLTextAreaElement.prototype : w.HTMLInputElement.prototype;
  const desc = Object.getOwnPropertyDescriptor(proto, 'value'); desc.set.call(el, v);
  el.dispatchEvent(new w.Event('input', { bubbles: true })); el.dispatchEvent(new w.Event('change', { bubbles: true }));
  if (w.jQuery) try { w.jQuery(el).trigger('change'); } catch (e) {}   // select2 and friends listen through jQuery
  el.style.outline = '2px solid #5b5bf0'; el.style.background = '#eef0ff';
}
function chooseOption(sel, text) {
  const t = String(text).toLowerCase();
  return [...sel.options].find((o) => o.value && o.textContent.toLowerCase().includes(t)) || [...sel.options].find((o) => o.value && t.includes(o.textContent.trim().toLowerCase()) && o.textContent.trim().length > 2);
}
function fill(w, slots) {
  const doc = w.document;
  const form = [...doc.querySelectorAll('form')].filter((f) => (f.getAttribute('method') || 'get').toLowerCase() === 'post').sort((a, b) => b.elements.length - a.elements.length)[0];
  if (!form) return null;
  const els = [...form.querySelectorAll('input,select,textarea')].filter((e) => !['hidden', 'submit', 'button', 'checkbox', 'radio', 'file'].includes((e.type || '').toLowerCase()) && !/_token|_method/.test(e.name || ''));
  const used = new Set(), done = [];
  const put = (key, val, prefer) => {
    if (val == null) return;
    const cand = els.filter((e) => !used.has(e) && FIELD[key].test(labelOf(doc, e) + ' ' + (e.name || '')));
    const el = (prefer ? cand.find(prefer) : null) || cand[0];
    if (!el) return;
    if (el.tagName === 'SELECT') { const o = chooseOption(el, val); if (!o) return; setValue(w, el, o.value); done.push(`${labelOf(doc, el)}: ${o.textContent.trim()}`); }
    else { setValue(w, el, el.type === 'date' ? val : String(val)); done.push(`${labelOf(doc, el)}: ${val}`); }
    used.add(el);
  };
  put('pnr', slots.pnr); put('ticket_no', slots.ticket_no); put('passport', slots.passport); put('phone', slots.phone);
  put('name', slots.name, (e) => e.tagName !== 'SELECT' || chooseOption(e, slots.name));
  put('from', slots.from); put('to', slots.to); if (!used.size || !slots.from) put('route', slots.route);
  put('date', slots.date, (e) => e.type === 'date' || /travel|depart|flight/i.test(labelOf(doc, e)));
  put('cost', slots.cost); put('sale', slots.sale);
  // free names in the sentence (airline, vendor, agent) against the form's dropdowns
  for (const sel of els.filter((e) => e.tagName === 'SELECT' && !used.has(e))) {
    const o = [...sel.options].find((x) => x.value && x.textContent.trim().length > 3 && slots._raw.toLowerCase().includes(x.textContent.trim().toLowerCase()));
    if (o) { setValue(w, sel, o.value); used.add(sel); done.push(`${labelOf(doc, sel)}: ${o.textContent.trim()}`); }
  }
  const missing = els.filter((e) => e.required && !e.value && !used.has(e)).map((e) => labelOf(doc, e)).filter(Boolean);
  return { form, done, missing };
}

/* ---------- the orders ---------- */
const CREATE = /(নতুন|new|add|অ্যাড|এন্ট্রি|entry|তৈরি|create|বানাও|খোলো নতুন|ইস্যু করো|issue)/i;
const EDIT = /(এডিট|edit|সংশোধন|ঠিক করো|change the|update|আপডেট)/i;
const PRINT = /(প্রিন্ট|print)/i;
const SAVE = /^(সেভ|save|submit|সাবমিট|জমা দাও|সংরক্ষণ)/i;
const SUBJECT = /(টিকেট|টিকিট|ticket|ফ্লাইট|flight|ভিসা|visa|ফাইল|file|পার্টি|party|এজেন্ট|agent|ভেন্ডর|vendor|ক্লায়েন্ট|client|পাসপোর্ট|passport|ইনভয়েস|invoice|সেল|sale|পারচেজ|purchase|জার্নাল|journal|ভাউচার|voucher|রিপোর্ট|report|লেজার|ledger|স্টেটমেন্ট|statement|এমপ্লয়ি|employee|টাস্ক|task|প্রজেক্ট|project|লিড|lead)/i;

let pending = null;   // a filled form waiting for "save"

async function answer(q) {
  const raw = String(q || '').trim(); const s = normalize(raw); const bn = speaksBangla(raw);
  if (!N()) return null;

  // "সেভ করো" — press the form's own Save, after a yes
  if (SAVE.test(s) || (pending && /^(হ্যাঁ|হা|yes|ok|ঠিক আছে|করো)\b/i.test(s) && pending.asked)) {
    const w = erpWin(); if (!w) return null;
    const f = [...w.document.querySelectorAll('form')].filter((x) => (x.getAttribute('method') || 'get').toLowerCase() === 'post').sort((a, b) => b.elements.length - a.elements.length)[0];
    if (!f) return { speak: T(bn, 'There is no form open to save.', 'সেভ করার মতো কোনো ফর্ম খোলা নেই।'), detail: [] };
    if (!pending || !pending.asked) { pending = { asked: true, at: Date.now() }; return { speak: T(bn, 'Save this form in the ERP? Say yes.', 'ERP-র এই ফর্মটা সেভ করব? হ্যাঁ বলুন।'), detail: [] }; }
    pending = null;
    const btn = f.querySelector('button[type=submit],input[type=submit]') || [...f.querySelectorAll('button')].find((b) => /save|submit|সেভ|জমা/i.test(b.textContent));
    if (btn) btn.click(); else f.requestSubmit ? f.requestSubmit() : f.submit();
    return { speak: T(bn, 'Saved through the ERP\'s own form — check the screen for its confirmation.', 'ERP-র নিজের ফর্ম দিয়ে সেভ করলাম — স্ক্রিনে নিশ্চিতকরণটা দেখে নিন।'), detail: [] };
  }

  // "প্রিন্ট করো" with nothing else: the screen in front of the boss
  if (PRINT.test(s) && !SUBJECT.test(s)) {
    const w = erpWin(); if (!w) return null;
    const p = w.document.querySelector('a[href*="print"],button[onclick*="print"]');
    if (p && p.href) N().go(p.href); else w.print();
    return { speak: T(bn, 'Printing this screen.', 'এই স্ক্রিনটা প্রিন্ট করছি।'), detail: [] };
  }

  const wantEdit = EDIT.test(s), wantCreate = !wantEdit && CREATE.test(s) && SUBJECT.test(s);
  if (!wantEdit && !wantCreate) return null;
  const slots = Object.assign(slotsOf(raw), { _raw: raw });

  let href = null, label = '';
  if (wantEdit) {
    // the record: a ticket by PNR / name (the last one talked about counts), else the screen in front
    const D = window.EonErp && window.EonErp.dataset ? window.EonErp.dataset() : null;
    const t = D && D.ticket_index ? D.ticket_index.find((r) => (slots.pnr && r.pnr === slots.pnr) || (slots.name && String(r.passenger || '').toLowerCase().includes(slots.name.toLowerCase()))) : null;
    const last = recall('ticket');
    const id = t ? t.purchase_id : (last && /এটা|এইটা|this|it|ওটা/i.test(s) ? last.purchase_id : null);
    if (id) { href = N().url(`/{role}/ticket-purchase/${id}/edit`); label = t ? `${t.pnr} · ${t.passenger}` : 'ticket'; }
    else { const w = erpWin(); const a = w && w.document.querySelector('a[href*="/edit"]'); if (a) { href = a.href; label = T(bn, 'this record', 'এই রেকর্ড'); } }
    if (!href) return { speak: T(bn, 'Which record should I edit? Say its PNR, name or open it first.', 'কোন রেকর্ডটা এডিট করব? PNR বা নামটা বলুন, অথবা আগে খুলুন।'), detail: [] };
  } else {
    const hit = (N().find(raw, 1) || [])[0];
    if (!hit || !/create|add|new/i.test((hit.route || '') + ' ' + (hit.label || ''))) {
      const hit2 = (N().find('add ' + (s.match(SUBJECT) || [''])[0], 1) || [])[0];
      if (hit2) { href = N().url(hit2.uri); label = hit2.label; }
    } else { href = N().url(hit.uri); label = hit.label; }
    if (!href) return null;
  }

  const w = await openAndWait(href);
  if (!w) return { speak: T(bn, `Opening ${label}.`, `${label} খুলছি।`), detail: [], navigate: href };
  const r = fill(w, slots);
  if (!r) return { speak: T(bn, `${label} is open, but it has no form I can fill.`, `${label} খুলেছে, কিন্তু ভরার মতো ফর্ম পাইনি।`), detail: [] };
  remember({ form: { href, label } });
  const filled = r.done.length ? T(bn, `I filled ${r.done.length}: `, `${r.done.length}টা ঘর ভরে দিলাম: `) + r.done.join('; ') + '.' : T(bn, 'Nothing in what you said matched a field yet.', 'আপনার কথার কিছুই এখনো কোনো ঘরে মেলেনি।');
  const miss = r.missing.length ? ' ' + T(bn, `Still needed: ${r.missing.join(', ')}. Tell me, or type them.`, `এখনো লাগবে: ${r.missing.join(', ')} — বলুন, বা টাইপ করে দিন।`) : ' ' + T(bn, 'Check it, then say "save".', 'দেখে নিয়ে "সেভ করো" বলুন।');
  return { speak: T(bn, `${label} is open. `, `${label} খুলেছি। `) + filled + miss, detail: r.done, actions: [{ label: T(bn, 'Open form', 'ফর্ম খুলুন'), kind: 'erp-open', href }] };
}

if (typeof window !== 'undefined') {
  window.EonFormFill = { answer, slotsOf };
  (window.__eonDomainQueue = window.__eonDomainQueue || []).push({ id: 'formfill', priority: 99.3, answer });
}
export default { answer, slotsOf };
