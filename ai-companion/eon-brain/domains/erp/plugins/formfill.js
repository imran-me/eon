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
import { latinSkeleton, banglaSkeleton, CASE_ENDING } from './act.js';

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
  const tm = s.match(/\b(\d{1,2}):(\d{2})\s*(am|pm)?/i) || s.match(/(সকাল|ভোর|দুপুর|বিকাল|বিকেল|সন্ধ্যা|রাত)\s*(\d{1,2})\s*টা/u);
  if (tm) {
    let h, mi = 0;
    if (/:/.test(tm[0])) { h = +tm[1]; mi = +tm[2]; if (/pm/i.test(tm[3] || '') && h < 12) h += 12; if (/am/i.test(tm[3] || '') && h === 12) h = 0; }
    else { h = +tm[2]; if (/দুপুর|বিকাল|বিকেল|সন্ধ্যা|রাত/u.test(tm[1]) && h < 12) h += 12; }
    out.time = String(h).padStart(2, '0') + ':' + String(mi).padStart(2, '0');
  }
  const fl = s.match(/\b([A-Z]{2}\s?-?\d{2,4})\b/); if (fl && !/^CF/i.test(fl[1])) out.flight_no = fl[1].replace(/[\s-]/g, '');
  const localDay = (d) => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  if (!out.date && /আজ|today/i.test(s)) out.date = localDay(today);
  if (!out.date && /আগামীকাল|tomorrow/i.test(s)) out.date = localDay(new Date(Date.now() + 864e5));
  // amounts: "৫২ হাজার", "1.5 lakh", "52000"; a second one is the cost / purchase when both are said
  // passport and phone digits are not money
  const sm = [out.passport, out.phone, out.ticket_no].filter(Boolean).reduce((t, x) => t.split(x).join(' '), s)
    .replace(/(\d+(?:\.\d+)?)\s*(?:লাখ|লক্ষ|lakh|lac)\s+(\d+)\s*(?:হাজার|hajar|thousand)/gi, (m, a, b) => String(Math.round(parseFloat(a) * 1e5 + parseFloat(b) * 1e3)) + ' টাকা');
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
  const nm = s.match(/(?:যাত্রী|passenger|নাম|name)\s*[:\-]?\s*([\p{L}\p{M}.\s]{3,40}?)(?=,|।|$|\s+(?:এর|জন্য|PNR|পিএনআর))/u) || s.match(/([\p{L}\p{M}.]+(?:\s+[\p{L}\p{M}.]+){0,4}?)\s*-?\s*(?:এর|য়ের|ের)\s+জন্য/u) || s.match(/\bfor\s+((?:(?!dhaka|from|to\b|on\b|pnr|price|passport|phone|at\b)[A-Za-z.]+\s*){1,4})/i);
  if (nm) out.name = nm[1].replace(/(এর|ের|য়ের)$/u, '').replace(/^.*?(খোলো|খুলুন|করো|করুন|দাও|দিন|বানাও|এন্ট্রি|entry|নতুন|new|open|create|add|অ্যাপ্লাই|apply)\s+/iu, '').trim();
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
/* Forms known exactly (read from the ERP's own Blade views): what each spoken detail is called there.
   The generic label matcher above covers every other form. */
const COUNTRY = { মালয়েশিয়া: 'malaysia', মালয়েশিয়া: 'malaysia', সৌদি: 'saudi', দুবাই: 'emirates', আমিরাত: 'emirates', ওমান: 'oman', কাতার: 'qatar', কুয়েত: 'kuwait', বাহরাইন: 'bahrain', থাইল্যান্ড: 'thailand', তুরস্ক: 'turkey', সাইপ্রাস: 'cyprus', সিঙ্গাপুর: 'singapore', ভারত: 'india', ইন্ডিয়া: 'india', চীন: 'china', জাপান: 'japan', ইতালি: 'italy', রোমানিয়া: 'romania', পোল্যান্ড: 'poland', মাল্টা: 'malta', সার্বিয়া: 'serbia', ক্রোয়েশিয়া: 'croatia', যুক্তরাজ্য: 'kingdom', লন্ডন: 'kingdom', আমেরিকা: 'america', কানাডা: 'canada', অস্ট্রেলিয়া: 'australia', মালদ্বীপ: 'maldives', নেপাল: 'nepal', ভিয়েতনাম: 'vietnam', ইন্দোনেশিয়া: 'indonesia', জর্ডান: 'jordan', মিশর: 'egypt' };
const VCAT = [[/ভিজিট|visit/i, 'visit'], [/ওয়ার্ক|ওয়ার্ক|কাজের|work/i, 'work'], [/উমরাহ|umrah/i, 'umrah'], [/হজ|hajj/i, 'hajj'], [/স্টুডেন্ট|ছাত্র|student/i, 'student'], [/ট্যুরিস্ট|tourist/i, 'tourist'], [/বিজনেস|business/i, 'business'], [/ট্রানজিট|transit/i, 'transit']];
const KNOWN = [{
  // a ticket SALE: tickets sold to an agent / customer — the Create Sale modal on Ticket Sales
  path: /\/ticket-sales\/?$/,
  async open(w) {
    const b = [...w.document.querySelectorAll('button,a')].find((x) => /create sale/i.test(x.textContent));
    if (b) { b.click(); await sleep(900); }
  },
  async apply(w, slots, done) {
    const d = w.document, $ = (q) => d.querySelector(q);
    // the agent: EON's party matcher reaches the ERP's Latin name from Bangla ("সালমান ট্যুরস" → SALMAN TOURS & TRAVELS)
    const S = window.EonStatement; let p = S && S.findParty ? S.findParty(slots._raw) : null;
    /* several candidates ("৫২ হাজার" sounds like Haji / HAJEE): keep the one whose FIRST name word is
       actually said — সালমান is Salman — and for a sale an agent/customer before a supplier */
    if (p && p.ambiguous) {
      const words = slots._raw.split(/[\s,।]+/).filter(Boolean);
      const said = (nm) => { const first = String(nm).replace(/^(mr|md|mrs|ms)\.?\s+/i, '').split(/\s+/)[0]; const k = latinSkeleton(first); return k.length >= 2 && words.some((w) => (/[ঀ-৿]/.test(w) ? banglaSkeleton(w.replace(CASE_ENDING, '')) : latinSkeleton(w)) === k); };
      p = p.ambiguous.filter((c) => said(c.name)).sort((a, b) => (a.type === 'customer' ? 0 : 1) - (b.type === 'customer' ? 0 : 1))[0] || null;
    }
    const want = p && p.name ? String(p.name).toLowerCase() : null;
    // the agent list also fills itself a moment after the modal opens
    for (let i = 0; i < 10 && !(($('#ts_create_agent_picker') || {}).options || []).length > 1; i++) await sleep(300);
    for (let i = 0; i < 10 && ((($('#ts_create_agent_picker') || {}).options || []).length || 0) < 2; i++) await sleep(300);
    for (const id of ['ts_create_agent_picker', 'ts_create_customer_picker']) {
      const el = $('#' + id); if (!el || !el.options) continue;
      const o = [...el.options].find((x) => x.value && (want ? x.textContent.toLowerCase().includes(want) : slots._raw.toLowerCase().includes(x.textContent.split(' - ')[0].trim().toLowerCase())));
      if (o) { setValue(w, el, o.value); done.push('Agent / Customer: ' + o.textContent.trim()); await sleep(1500); break; }   // the unsold tickets load after the agent is chosen
    }
    if (slots.date && $('#ts_create_date')) { setValue(w, $('#ts_create_date'), slots.date); done.push('Sale date: ' + slots.date); }
    // the ticket by PNR / passenger: search it with the page's own filter, tick it, price it
    const key = slots.pnr || slots.ticket_no || slots.name;
    if (key && $('#tsTicketSearch')) {
      // choosing the agent reloads the unsold tickets — keep searching until the list has arrived
      let rows = [];
      for (let i = 0; i < 25; i++) {
        setValue(w, $('#tsTicketSearch'), key); try { w.tsFilterTickets && w.tsFilterTickets(key); } catch (e) {}
        await sleep(400);
        rows = [...d.querySelectorAll('#tsTicketsBody tr')].filter((r) => r.querySelector('input[type=checkbox]'));
        if (rows.length) break;
      }
      if (rows.length === 1 || (rows.length && slots.pnr)) {
        const r = rows.find((x) => slots.pnr && x.textContent.includes(slots.pnr)) || rows[0];
        const cb = r.querySelector('input[type=checkbox]'); if (cb && !cb.checked) cb.click();
        done.push('Ticket: ' + r.querySelector('td:nth-child(2)').textContent.trim() + ' · ' + r.querySelector('td:nth-child(3)').textContent.trim());
        const price = slots.sale ?? slots.amount;
        const pi = r.querySelector('input[type=number]'); if (pi && price != null) { setValue(w, pi, String(price)); done.push('Sale price: ' + price); }
      } else if (rows.length > 1) done.push(rows.length + ' tickets match "' + key + '" — tick the right one');
      else done.push('No unsold ticket matches "' + key + '"');
    }
    return true;
  },
}, {
  // a contract file (manpower): the Add modal on the contract-files list
  path: /\/contract-files\/?$/,
  async open(w) { const b = w.document.querySelector('.create-btn'); if (b) { b.click(); await sleep(800); } },
  apply(w, slots, done) {
    const d = w.document, $ = (q) => d.querySelector(q), raw = slots._raw.toLowerCase();
    const sel = (el, test, what) => { if (!el || !el.options) return false; const o = [...el.options].find((x) => x.value && test(x.textContent.trim().toLowerCase())); if (o) { setValue(w, el, o.value); done.push(what + ': ' + o.textContent.trim()); return true; } return false; };
    if (slots.name) sel($('#create_passport_holder_id'), (t) => slots.name.toLowerCase().split(/\s+/).every((p) => t.includes(p)), 'Applicant');
    let c = null; for (const [bn, en] of Object.entries(COUNTRY)) if (slots._raw.includes(bn)) { c = en; break; }
    if (!c) { const el = $('#create_country_id'); const o = el && el.options && [...el.options].find((x) => x.value && x.textContent.trim().length > 3 && raw.includes(x.textContent.trim().toLowerCase())); if (o) c = o.textContent.trim().toLowerCase(); }
    if (c) sel($('#create_country_id'), (t) => t.includes(c), 'Country');
    const saidAs = (t) => { const i = raw.indexOf(t); const before = i > 0 ? raw.slice(Math.max(0, i - 14), i) : ''; return /ভেন্ডর|vendor|সাপ্লায়ার/u.test(before) ? 'vendor' : /এজেন্ট|agent|ক্লায়েন্ট|client/u.test(before) ? 'client' : null; };
    // "Received From" first — agent or direct client — as the boss said it
    if ($('#create_client_type')) { const ty = /ডাইরেক্ট|direct|সরাসরি|ক্লায়েন্ট|client/i.test(slots._raw) && !/এজেন্ট|agent/i.test(slots._raw) ? 'direct' : /এজেন্ট|agent/i.test(slots._raw) ? 'agent' : null; if (ty) { setValue(w, $('#create_client_type'), ty); done.push('Received from: ' + ty); } }
    // a party option reads "NAME - phone": the name part is what is said
    const nameOf = (t) => t.split(' - ')[0].trim();
    for (const [id, what, role] of [['create_client_id', 'Agent / Client', 'client'], ['create_vendor_id', 'Vendor', 'vendor'], ['create_portal_id', 'Portal', null], ['create_contract_file_category_id', 'File category', null]])
      sel($('#' + id), (t) => nameOf(t).length > 2 && raw.includes(nameOf(t)) && (!role || (saidAs(nameOf(t)) || role) === role), what);
    if (slots.sale != null && $('#create_client_price')) { setValue(w, $('#create_client_price'), String(slots.sale)); done.push('Client charge: ' + slots.sale); }
    if (slots.cost != null && $('#create_visa_rate')) { setValue(w, $('#create_visa_rate'), String(slots.cost)); done.push('Visa rate (cost): ' + slots.cost); }
    if (slots.date && $('#create_submit_date')) { setValue(w, $('#create_submit_date'), slots.date); done.push('Submit date: ' + slots.date); }
    return true;
  },
}, {
  path: /\/visa\/?$/,
  async open(w) { const b = w.document.querySelector('.create-btn'); if (b) { b.click(); await sleep(800); } },
  apply(w, slots, done) {
    const d = w.document, $ = (q) => d.querySelector(q), raw = slots._raw.toLowerCase();
    const sel = (el, test, what) => { if (!el) return false; const o = [...el.options].find((x) => x.value && test(x.textContent.trim().toLowerCase())); if (o) { setValue(w, el, o.value); done.push(what + ': ' + o.textContent.trim()); return true; } return false; };
    let c = null; for (const [bn, en] of Object.entries(COUNTRY)) if (slots._raw.includes(bn)) { c = en; break; }
    if (!c) { const el = $('#create_country_id'); const o = el && [...el.options].find((x) => x.value && x.textContent.trim().length > 3 && raw.includes(x.textContent.trim().toLowerCase())); if (o) c = o.textContent.trim().toLowerCase(); }
    if (c) sel($('#create_country_id'), (t) => t.includes(c), 'Country');
    let cat = null; for (const [rx, k] of VCAT) if (rx.test(slots._raw)) { cat = k; break; }
    // the category list depends on the country — the page fills it after the country changes
    if (cat) setTimeout(() => sel($('#create_visa_category_id'), (t) => t.includes(cat), 'Visa category'), 900), done.push('Visa category: ' + cat);
    if (slots.name) sel($('#create_passport_holder_id'), (t) => slots.name.toLowerCase().split(/\s+/).every((p) => t.includes(p)) || t.includes(slots.name.toLowerCase()), 'Applicant');
    if (slots.date && d.querySelector('#createModal [name=travel_date]')) { setValue(w, d.querySelector('#createModal [name=travel_date]'), slots.date); done.push('Travel date: ' + slots.date); }
    if (slots.cost != null && $('#c_costing_price')) { setValue(w, $('#c_costing_price'), String(slots.cost)); done.push('Costing price: ' + slots.cost); }
    if (slots.sale != null && $('#c_sale_price')) { setValue(w, $('#c_sale_price'), String(slots.sale)); done.push('Sale price: ' + slots.sale); }
    for (const [id, what] of [['c_vendor_id', 'Vendor'], ['c_portal_id', 'Portal']]) { const el = $('#' + id); if (el && el.options) sel(el, (t) => t.length > 2 && raw.includes(t), what); }
    return true;
  },
}, {
  path: /contract-flights\/dashboard/,
  async open(w) {
    const b = [...w.document.querySelectorAll('button,a')].find((x) => /add flight/i.test(x.textContent));
    if (b) { b.click(); await sleep(800); }
  },
  apply(w, slots, done) {
    const d = w.document, $ = (q) => d.querySelector(q);
    const sel = (el, test, what) => { if (!el) return false; const o = [...el.options].find((x) => x.value && test(x.textContent.trim().toLowerCase())); if (o) { setValue(w, el, o.value); done.push(what + ': ' + o.textContent.trim()); return true; } return false; };
    if (slots.flight_no && $('#create_airline_flight_no')) { setValue(w, $('#create_airline_flight_no'), slots.flight_no); done.push('Flight number: ' + slots.flight_no); }
    if (slots.from && slots.to && $('#create_ticket_id')) {
      const el = $('#create_ticket_id'), a = slots.from.toLowerCase(), b = slots.to.toLowerCase();
      const fit = [...el.options].filter((o) => o.value && o.textContent.toLowerCase().includes(a) && o.textContent.toLowerCase().includes(b))
        // the airline the flight number names (TK0713 → Turkish, (TK)) first, then the shortest route
        .sort((x, y) => { const code = (slots.flight_no || '').slice(0, 2).toLowerCase(); const hx = code && x.textContent.toLowerCase().includes('(' + code + ')') ? 0 : 1, hy = code && y.textContent.toLowerCase().includes('(' + code + ')') ? 0 : 1; return hx - hy || x.textContent.length - y.textContent.length; });
      if (fit[0]) { setValue(w, el, fit[0].value); done.push('Route: ' + fit[0].textContent.trim()); }
    }
    if (slots.date && $('#create_departure_at')) { const v = slots.date + 'T' + (slots.time || '00:00'); setValue(w, $('#create_departure_at'), v); done.push('Departure: ' + v.replace('T', ' ')); }
    for (const [id, what] of [['create_vendor_id', 'Ticket vendor'], ['create_portal_id', 'Portal'], ['create_agent_id', 'Agent'], ['create_pricing_airline_id', 'Airline']]) {
      const el = $('#' + id); if (!el || !el.options) continue;
      sel(el, (t) => t.length > 2 && slots._raw.toLowerCase().includes(t), what);
    }
    return true;
  },
}, {
  path: /ticket-purchase\/create|ticket-purchase\/\d+\/edit/,
  apply(w, slots, done) {
    const d = w.document, $ = (q) => d.querySelector(q);
    const sel = (el, test, what) => { if (!el) return false; const o = [...el.options].find((x) => x.value && test(x.textContent.trim().toLowerCase())); if (o) { setValue(w, el, o.value); done.push(`${what}: ${o.textContent.trim()}`); return true; } return false; };
    if (slots.name) sel($('#passport_holder_id'), (t) => slots.name.toLowerCase().split(/\s+/).every((p) => t.includes(p)) || t.includes(slots.name.toLowerCase()), 'Passport Holder');
    if (slots.pnr && $('[name=ticket_no]')) { setValue(w, $('[name=ticket_no]'), slots.pnr); done.push(`Ticket No (PNR): ${slots.pnr}`); }
    if (slots.ticket_no && $('#name_ticket_no')) { setValue(w, $('#name_ticket_no'), slots.ticket_no); done.push(`Name Ticket No.: ${slots.ticket_no}`); }
    if (slots.from && slots.to && $('#isProductSelected')) {
      const el = $('#isProductSelected'), a = slots.from.toLowerCase(), b = slots.to.toLowerCase();
      const fit = [...el.options].filter((o) => o.value && o.textContent.toLowerCase().includes(a) && o.textContent.toLowerCase().includes(b))
        .sort((x, y) => (x.textContent.toLowerCase().indexOf(a) === 0 ? 0 : 1) - (y.textContent.toLowerCase().indexOf(a) === 0 ? 0 : 1) || x.textContent.length - y.textContent.length);
      if (fit[0]) { setValue(w, el, fit[0].value); try { w.selectProduct && w.selectProduct(el); } catch (e) {} done.push('Ticket (route): ' + fit[0].textContent.trim()); }
    }
    const price = slots.cost ?? slots.sale ?? slots.amount;   // on a purchase the price is what we pay
    if (price != null && $('#selectsubtotal')) { setValue(w, $('#selectsubtotal'), String(price)); try { w.applyGrandTotal && w.applyGrandTotal($('#selectsubtotal')); } catch (e) {} done.push(`Price: ${price}`); }
    for (const [k, nm] of [['vendor', 'isVendorSelected'], ['portal', 'isPortalSelected']]) {
      const el = $('#' + nm); if (!el) continue;
      const o = [...el.options].find((x) => x.value && x.textContent.trim().length > 2 && slots._raw.toLowerCase().includes(x.textContent.trim().toLowerCase())); if (o) { setValue(w, el, o.value); done.push(`${k}: ${o.textContent.trim()}`); }
    }
    // the travel leg (rendered by the page's own script once a ticket is chosen)
    setTimeout(() => {
      const f = d.querySelector('[name="legs[0][from_location]"]'), t = d.querySelector('[name="legs[0][to_location]"]'), td = d.querySelector('[name="legs[0][travel_date]"]');
      if (f && slots.from) setValue(w, f, slots.from); if (t && slots.to) setValue(w, t, slots.to); if (td && slots.date) setValue(w, td, slots.date);
    }, 1200);
    if (slots.date) done.push(`Travel date: ${slots.date}`);
    return true;
  },
}];

async function fill(w, slots) {
  const doc = w.document;
  const known = KNOWN.find((k) => k.path.test(w.location.pathname));
  if (known) {
    const done = []; await known.apply(w, slots, done);
    const form = [...doc.querySelectorAll('form')].filter((f) => (f.getAttribute('method') || 'get').toLowerCase() === 'post').sort((a, b) => b.elements.length - a.elements.length)[0];
    const missing = form ? [...form.querySelectorAll('input,select,textarea')].filter((e) => e.required && !e.value && e.type !== 'hidden').map((e) => labelOf(doc, e)).filter(Boolean) : [];
    return { form, done, missing };
  }
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
const CREATE = /(নতুন|new|add|অ্যাড|এন্ট্রি|entry|তৈরি|create|বানাও|খোলো নতুন|ইস্যু করো|issue|অ্যাপ্লাই|এপ্লাই|apply|আবেদন)/i;
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

  const wantSale = /(টিকেট|টিকিট|ticket|pnr|পিএনআর)/i.test(s) && /(বিক্রি করো|বিক্রি করে দাও|বেচো|sell|sale entry|সেল এন্ট্রি|ইনভয়েস বানাও|invoice (it|them|করো))/i.test(s);
  const wantEdit = !wantSale && EDIT.test(s), wantCreate = wantSale || (!wantEdit && CREATE.test(s) && SUBJECT.test(s));
  if (!wantEdit && !wantCreate) return null;
  const slots = Object.assign(slotsOf(raw), { _raw: raw });
  try { await N().ready(); } catch (e) {}   // the ERP map loads lazily — a search before it arrives finds nothing

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
    /* the thing being made is the FIRST one named — "নতুন টিকেট এন্ট্রি … ভেন্ডর BSP" is a ticket,
       the vendor is one of its details. Its create screen is looked up by that word alone. */
    const subj = (s.match(SUBJECT) || [''])[0];
    const isCreate = (h) => h && /create|add|new/i.test((h.route || '') + ' ' + (h.label || ''));
    const hit = (N().find('add ' + subj, 3) || []).find(isCreate) || (N().find(raw, 3) || []).find(isCreate);
    if (hit) { href = N().url(hit.uri); label = hit.label; }
    if (!href) return null;
  }

  if (wantSale) { href = N().url('/{role}/ticket-sales'); label = T(bn, 'Create Sale', 'টিকেট বিক্রি'); }
  else if (wantCreate && /কন্ট্রাক্ট ফাইল|contract file|ফাইল খোলো|নতুন ফাইল|new file/i.test(s)) { href = N().url('/{role}/contract-files'); label = T(bn, 'New contract file', 'নতুন কন্ট্রাক্ট ফাইল'); }
  else if (wantCreate && /ভিসা|visa/i.test(s)) { href = N().url('/{role}/visa/create'); label = T(bn, 'New visa', 'নতুন ভিসা'); }
  else if (wantCreate && /ফ্লাইট|flight/i.test(s) && !/টিকেট|ticket/i.test(s)) { href = N().url('/{role}/contract-flights/dashboard'); label = T(bn, 'Add Flight', 'নতুন ফ্লাইট'); }
  const w = await openAndWait(href);
  const k0 = w && KNOWN.find((k) => k.path.test(w.location.pathname));
  if (k0 && k0.open) await k0.open(w);
  if (!w) return { speak: T(bn, `Opening ${label}.`, `${label} খুলছি।`), detail: [], navigate: href };
  const r = await fill(w, slots);
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
