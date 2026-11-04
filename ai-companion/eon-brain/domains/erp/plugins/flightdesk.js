/* ============================================================
   EON · flight desk — the contract-flight and agent questions the boss
   asks one after another, in বাংলা, Banglish or English:
     "flight dashboard e jaw"  "Flights & Passengers e jaw"  "agent ledger e jaw"
     "ajker flight open koro"  "koyta flight baki ache"  "kalke koyjon deported"
     "kon flight theke koto lav"  "ei mase koyjon agent flight niyeche"
     "kon agent theke koto lav"  "CF-060 er print dao"
     "MR SALEH CYP er kache paona koto"  "se koyta service niyeche"
     "tar kon service e loss beshi"
   Asked before the server: these are exact figures from the dataset and
   screens to open, and a confident general answer from elsewhere is worse.
   ============================================================ */
import { speaksBangla, normalize } from './voicefix.js';
import { remember, recall } from './context.js';

const N = () => (typeof window !== 'undefined' ? window.EonNavigator : null);
const D0 = () => (typeof window !== 'undefined' && window.EonErp && window.EonErp.dataset ? window.EonErp.dataset() : null);
const num = (x) => Number(x) || 0;
const tk = (n, bn) => {
  const a = Math.abs(n), s = n < 0 ? '−' : '';
  const v = a >= 1e7 ? (a / 1e7).toFixed(2).replace(/\.?0+$/, '') + (bn ? ' কোটি' : ' Cr') : a >= 1e5 ? (a / 1e5).toFixed(2).replace(/\.?0+$/, '') + (bn ? ' লাখ' : ' L') : a >= 1e3 ? (a / 1e3).toFixed(1).replace(/\.0$/, '') + (bn ? ' হাজার' : 'k') : String(Math.round(a));
  const out = s + '৳' + v;
  return bn ? out.replace(/[0-9]/g, (d) => '০১২৩৪৫৬৭৮৯'[d]) : out;
};
const bnN = (n, bn) => (bn ? String(n).replace(/[0-9]/g, (d) => '০১২৩৪৫৬৭৮৯'[d]) : String(n));
const ymd = (d) => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
const dayOf = (s) => String(s || '').slice(0, 10);

const FLIGHT = /flight|ফ্লাইট|flite|flaight/i;
const AGENT = /agent|এজেন্ট|ejent/i;
const GO = /\b(jaw|jao|jan|niye jao|open|kholo|khulo|take me|go to|show)\b|যাও|নিয়ে যাও|খোলো|খুলুন|ওপেন/i;
const PRINT = /print|প্রিন্ট|pdf|পিডিএফ/i;
const HOWMANY = /\b(koyta|koita|kayta|koyjon|koijon|kojon|how many|count)\b|কয়টা|কয়টা|কয়জন|কয়জন|কতজন|কতগুলো/i;
const PROFIT = /\b(lav|labh|luv|lab|profit|loss|lokshan|loksan)\b|লাভ|লোকসান|প্রফিট|লস/i;
const LOSS = /\b(loss|lokshan|loksan)\b|লোকসান|লস|ক্ষতি/i;
const DUE = /\b(paona|pawna|paw na|pabo|due|owe|owes|baki)\b|পাওনা|বাকি|বকেয়া/i;
const SERVICE = /\b(service|services|sheba|seba)\b|সার্ভিস|সেবা/i;
const WHICH = /\b(kon|which|kar|who)\b|কোন|কার|কে /i;
const TODAY = /\b(ajk?e?r?|today|aj)\b|আজ/i;
const YEST = /\b(kalke|kal|yesterday|gotokal)\b|গতকাল|কাল(?![ঀ-৿])|কালকে/i;
const THIS_MONTH = /\b(ei mase|e mase|this month|ai mo?nth|ei month)\b|এই মাসে|এ মাসে/i;
const PENDING = /\b(baki|pending|left|upcoming|remaining)\b|বাকি|সামনে/i;

function screens() {
  return [
    [/flights?\s*(&|and|o|এবং|আর)?\s*passengers?|ফ্লাইট\s*(ও|এবং|আর)?\s*প্যাসেঞ্জার/i, '/{role}/contract-flights', 'Flights & Passengers', 'ফ্লাইট ও প্যাসেঞ্জার'],
    [/passenger\s*board|প্যাসেঞ্জার\s*বোর্ড/i, '/{role}/contract-flight-board', 'Passenger Board', 'প্যাসেঞ্জার বোর্ড'],
    [/flight\s*accounts?|ফ্লাইট\s*(একাউন্ট|অ্যাকাউন্ট|হিসাব)/i, '/{role}/contract-flights/accounts', 'Flight Accounts', 'ফ্লাইট অ্যাকাউন্টস'],
    [/flight\s*dash\s*board|ফ্লাইট\s*ড্যাশবোর্ড|ফ্লাইট\s*ড্যাশ/i, '/{role}/contract-flights/dashboard', 'Flight Dashboard', 'ফ্লাইট ড্যাশবোর্ড'],
    [/agent\s*(ledger|statement)s?|এজেন্ট\s*(লেজার|স্টেটমেন্ট)/i, '/{role}/party-statement/agents', 'Agent Ledger', 'এজেন্ট লেজার'],
    [/client\s*(ledger|statement)s?|ক্লায়েন্ট\s*(লেজার|স্টেটমেন্ট)/i, '/{role}/party-statement/clients', 'Client Ledger', 'ক্লায়েন্ট লেজার'],
  ];
}

function open(href, bn, en, b) {
  const u = N().url(href); N().go(u);
  return { speak: bn ? b : en, detail: [], navigate: u, actions: [{ label: bn ? 'খুলুন' : 'Open', kind: 'erp-open', href: u }] };
}
function printAfterLoad(tail) {
  const t0 = Date.now();
  const tick = () => { try { const f = window.parent.document.getElementById('erp'); const w = f && f.contentWindow;
    if (w && w.location.pathname.endsWith(tail) && w.document.readyState === 'complete') { setTimeout(() => w.print(), 700); return; } } catch (e) {}
    if (Date.now() - t0 < 15000) setTimeout(tick, 400); };
  setTimeout(tick, 600);
}

/** cost of one seat on a flight, so a passenger's profit can be said */
const seatCost = (f) => (f && num(f.total_seats) > 0 ? num(f.cost_price) / num(f.total_seats) : 0);

function flightNo(s) { const m = s.match(/\bCF[\s-]?0*(\d{1,4})\b/i); return m ? 'CF-' + m[1].padStart(3, '0') : null; }

async function answer(q) {
  const raw = String(q || '').trim(); if (!raw || !N()) return null;
  const s = normalize(raw); const bn = speaksBangla(raw) || /\b(jaw|jao|koro|koyta|koyjon|kon|theke|koto|lav|ache|hoyeche|niyeche|dao|dew|kache|paona|mase|ajker|kalke|er|e)\b/i.test(s);
  const T = (en, b) => (bn ? b : en);
  try { await N().ready(); } catch (e) {}
  const D = D0(); if (!D) return null;
  const F = D.contract_flights || [], P = D.contract_flight_passengers || [];
  const byId = new Map(F.map((f) => [+f.id, f]));

  // 1) go to a screen of the flight desk or the agent ledger
  if (GO.test(s) && !PROFIT.test(s) && !HOWMANY.test(s) && !DUE.test(s)) {
    for (const [rx, href, en, b] of screens()) if (rx.test(s)) return open(href, bn, `Opening ${en}.`, `${b} খুলছি।`);
  }

  // 2) one flight by number: print or open it
  const fn = flightNo(s);
  if (fn) {
    const f = F.find((x) => String(x.flight_number).toUpperCase() === fn);
    if (!f) return { speak: T(`I don't find ${fn} among the contract flights.`, `কন্ট্রাক্ট ফ্লাইটে ${fn} পাইনি।`), detail: [] };
    const pax = P.filter((p) => +p.contract_flight_id === +f.id);
    if (PROFIT.test(s)) { const pr = num(f.revenue) - num(f.cost_price);
      return { speak: T(`${fn}: revenue ${tk(num(f.revenue))}, cost ${tk(num(f.cost_price))} — ${pr >= 0 ? 'profit' : 'loss'} ${tk(Math.abs(pr))}, ${pax.length} passengers.`, `${fn}: আয় ${tk(num(f.revenue), true)}, খরচ ${tk(num(f.cost_price), true)} — ${pr >= 0 ? 'লাভ' : 'লোকসান'} ${tk(Math.abs(pr), true)}, যাত্রী ${bnN(pax.length, true)} জন।`), detail: [] }; }
    const path = `/{role}/contract-flights/${f.id}`;
    const r = open(path, bn, `${fn} (${dayOf(f.departure_at)}, ${pax.length} passengers) — ${PRINT.test(s) ? 'opening it to print' : 'opening it'}.`, `${fn} (${dayOf(f.departure_at)}, যাত্রী ${bnN(pax.length, true)} জন) — ${PRINT.test(s) ? 'প্রিন্টের জন্য খুলছি' : 'খুলছি'}।`);
    if (PRINT.test(s)) printAfterLoad(`/contract-flights/${f.id}`);
    remember({ flight: f });
    return r;
  }

  const isFlight = FLIGHT.test(s), isAgent = AGENT.test(s);
  const today = ymd(new Date()), yd = ymd(new Date(Date.now() - 864e5));
  const mon = today.slice(0, 7);

  // 3) deported / offloaded on a day
  if (/deport|ডিপোর্ট|offload|অফলোড/i.test(s) && (HOWMANY.test(s) || YEST.test(s) || TODAY.test(s) || /\b(ke ke|kara|who)\b|কারা/i.test(s))) {
    const kind = /offload|অফলোড/i.test(s) ? 'immigration_offload' : 'deported';
    const day = YEST.test(s) ? yd : TODAY.test(s) ? today : null;
    const rows = P.filter((p) => p.exception_flag === kind && (!day || dayOf(p.departure_at) === day));
    const w = kind === 'deported' ? T('deported', 'ডিপোর্ট') : T('offloaded', 'অফলোড');
    const when = day === yd ? T(' yesterday', ' গতকাল') : day === today ? T(' today', ' আজ') : T(' on record', ' রেকর্ডে');
    const names = rows.slice(0, 6).map((p) => `${p.name} (${p.flight_number}, ${p.agent || '—'})`);
    const u = N().url('/{role}/contract-flights/dashboard');
    return { speak: rows.length ? T(`${rows.length} ${w}${when}: ${names.join('; ')}.`, `${when.trim()} ${bnN(rows.length, true)} জন ${w} হয়েছে: ${names.join('; ')}।`) : T(`No one was ${w}${when}.`, `${when.trim()} কেউ ${w} হয়নি।`), detail: [], actions: [{ label: T('Flight dashboard', 'ফ্লাইট ড্যাশবোর্ড'), kind: 'erp-open', href: u }] };
  }

  // 4) today's flights / flights still to fly
  if (isFlight && !isAgent && !PROFIT.test(s) && (TODAY.test(s) || PENDING.test(s))) {
    const list = TODAY.test(s) ? F.filter((f) => dayOf(f.departure_at) === today) : F.filter((f) => dayOf(f.departure_at) >= today).sort((a, b) => String(a.departure_at).localeCompare(String(b.departure_at)));
    const pax = (f) => P.filter((p) => +p.contract_flight_id === +f.id).length;
    const lines = list.slice(0, 6).map((f) => `${f.flight_number} ${String(f.departure_at).slice(0, 16)} — ${pax(f)} ${T('pax', 'জন')}`);
    const u = N().url('/{role}/contract-flights/dashboard'); if (GO.test(s)) N().go(u);
    const what = TODAY.test(s) ? T('today', 'আজ') : T('still to fly', 'সামনে বাকি');
    return { speak: list.length ? T(`${list.length} flight${list.length > 1 ? 's' : ''} ${what}: ${lines.join('; ')}.`, `${what} ${bnN(list.length, true)}টা ফ্লাইট: ${lines.join('; ')}।`) : T(`No flight ${what}.`, `${what} কোনো ফ্লাইট নেই।`), detail: [], navigate: GO.test(s) ? u : undefined, actions: [{ label: T('Flight dashboard', 'ফ্লাইট ড্যাশবোর্ড'), kind: 'erp-open', href: u }] };
  }

  // 5) profit by flight
  if (isFlight && !isAgent && PROFIT.test(s) && WHICH.test(s)) {
    const rows = F.map((f) => ({ f, pr: num(f.revenue) - num(f.cost_price) })).filter((x) => num(x.f.revenue) || num(x.f.cost_price)).sort((a, b) => (LOSS.test(s) ? a.pr - b.pr : b.pr - a.pr));
    const top = rows.slice(0, 5).map((x) => `${x.f.flight_number} ${x.pr < 0 ? '−' : ''}${tk(Math.abs(x.pr), bn)}`);
    const tot = rows.reduce((a, x) => a + x.pr, 0);
    return { speak: T(`By flight (revenue − cost), ${LOSS.test(s) ? 'worst' : 'best'} first: ${top.join('; ')}. All ${rows.length} flights together: ${tk(tot)}.`, `ফ্লাইট অনুযায়ী (আয় − খরচ), ${LOSS.test(s) ? 'সবচেয়ে খারাপ' : 'সবচেয়ে ভালো'} আগে: ${top.join('; ')}। ${bnN(rows.length, true)}টা ফ্লাইট মিলিয়ে ${tk(tot, true)}।`), detail: [] };
  }

  // 6) agents on flights this month, and profit by agent
  if (isFlight && isAgent) {
    const scope = THIS_MONTH.test(s) ? P.filter((p) => dayOf(p.departure_at).slice(0, 7) === mon) : P;
    const agg = new Map();
    for (const p of scope) { const a = agg.get(p.agent_id) || { name: p.agent || '—', pax: 0, sale: 0, cost: 0 }; a.pax++; a.sale += num(p.agent_price); a.cost += seatCost(byId.get(+p.contract_flight_id)); agg.set(p.agent_id, a); }
    const rows = [...agg.values()];
    const when = THIS_MONTH.test(s) ? T('this month', 'এই মাসে') : T('on record', 'রেকর্ডে');
    if (PROFIT.test(s)) {
      rows.forEach((r) => (r.pr = r.sale - r.cost)); rows.sort((a, b) => (LOSS.test(s) ? a.pr - b.pr : b.pr - a.pr));
      return { speak: T(`Flight profit by agent ${when}: ${rows.slice(0, 6).map((r) => `${r.name} ${r.pr < 0 ? '−' : ''}${tk(Math.abs(r.pr))} (${r.pax} pax)`).join('; ')}.`, `${when} এজেন্ট অনুযায়ী ফ্লাইটের লাভ: ${rows.slice(0, 6).map((r) => `${r.name} ${r.pr < 0 ? '−' : ''}${tk(Math.abs(r.pr), true)} (${bnN(r.pax, true)} জন)`).join('; ')}।`), detail: [] };
    }
    rows.sort((a, b) => b.pax - a.pax);
    return { speak: T(`${rows.length} agents sent passengers on flights ${when}: ${rows.slice(0, 6).map((r) => `${r.name} ${r.pax}`).join('; ')}.`, `${when} ${bnN(rows.length, true)} জন এজেন্ট ফ্লাইটে যাত্রী দিয়েছে: ${rows.slice(0, 6).map((r) => `${r.name} ${bnN(r.pax, true)} জন`).join('; ')}।`), detail: [] };
  }

  // 7) one agent: what they owe, how many services, where we lost
  if (!(DUE.test(s) || SERVICE.test(s) || (PROFIT.test(s) && /service|সার্ভিস|সেবা|kon|কোন/i.test(s)))) return null;
  let party = null;
  try { party = window.EonStatement && window.EonStatement.findParty ? window.EonStatement.findParty(raw) : null; } catch (e) {}
  if (party && party.ambiguous) return { speak: T(`Which one — ${party.ambiguous.map((x) => x.name).join(', ')}?`, `কোনজন — ${party.ambiguous.map((x) => x.name).join(', নাকি ')}?`), detail: [] };
  if (!party && /\b(tar|tader|his|her|their|se|or|uni)\b|তার|ওর|উনার|তাদের/i.test(s)) { const c = recall('party'); if (c) party = c; }
  if (!party) return null;
  remember({ party });
  const id = +party.id;
  const ts = (D.ticket_sales || []).filter((x) => +x.client_id === id);
  const vs = (D.visa_sales || []).filter((x) => +x.client_id === id);
  const cf = (D.contract_file_sales || []).filter((x) => +x.client_id === id);
  const px = P.filter((p) => +p.agent_id === id);
  const due = ts.reduce((a, x) => a + num(x.due_amount), 0) + vs.reduce((a, x) => a + num(x.due_amount), 0) + cf.reduce((a, x) => a + num(x.due_amount), 0) + px.reduce((a, p) => a + Math.max(0, num(p.agent_price) - num(p.agent_paid_amount)), 0);
  const name = party.name;
  if (DUE.test(s) && !SERVICE.test(s)) {
    const parts = [[T('tickets', 'টিকেট'), ts.reduce((a, x) => a + num(x.due_amount), 0)], [T('visas', 'ভিসা'), vs.reduce((a, x) => a + num(x.due_amount), 0)], [T('contract files', 'কন্ট্রাক্ট ফাইল'), cf.reduce((a, x) => a + num(x.due_amount), 0)], [T('flights', 'ফ্লাইট'), px.reduce((a, p) => a + Math.max(0, num(p.agent_price) - num(p.agent_paid_amount)), 0)]].filter((x) => x[1] > 0);
    return { speak: T(`${name} owes ${tk(due)}${parts.length ? ' — ' + parts.map((x) => `${x[0]} ${tk(x[1])}`).join(', ') : ''}.`, `${name}-এর কাছে পাওনা ${tk(due, true)}${parts.length ? ' — ' + parts.map((x) => `${x[0]} ${tk(x[1], true)}`).join(', ') : ''}।`), detail: [] };
  }
  if (SERVICE.test(s) && !PROFIT.test(s)) {
    const n = ts.length + vs.length + cf.length + px.length;
    return { speak: T(`${name} has taken ${n} services: ${ts.length} ticket sales, ${vs.length} visa sales, ${cf.length} contract files, ${px.length} flight seats. Still owing ${tk(due)}.`, `${name} মোট ${bnN(n, true)}টা সার্ভিস নিয়েছে: টিকেট ${bnN(ts.length, true)}টা, ভিসা ${bnN(vs.length, true)}টা, কন্ট্রাক্ট ফাইল ${bnN(cf.length, true)}টা, ফ্লাইট সিট ${bnN(px.length, true)}টা। এখনো পাওনা ${tk(due, true)}।`), detail: [] };
  }
  // where we lost money with this agent: flight seats (price − seat cost) and contract files (price − vendor cost)
  const items = [
    ...px.map((p) => ({ what: `${T('flight', 'ফ্লাইট')} ${p.flight_number} · ${p.name}`, pr: num(p.agent_price) - seatCost(byId.get(+p.contract_flight_id)) })),
    ...cf.map((x) => ({ what: `${T('contract file', 'কন্ট্রাক্ট ফাইল')} ${x.invoice}`, pr: num(x.total) - num(x.vendor_cost) })),
  ].sort((a, b) => a.pr - b.pr);
  const losses = items.filter((x) => x.pr < 0);
  const note = T(' Ticket and visa sales carry no purchase cost per sale in the ERP, so they are not in this.', ' টিকেট আর ভিসা বিক্রির খরচ ERP-তে বিক্রি ধরে রাখা নেই, তাই সেগুলো এই হিসাবে নেই।');
  if (!items.length) return { speak: T(`${name} has no flight seats or contract files to measure profit on.${note}`, `${name}-এর লাভ মাপার মতো ফ্লাইট সিট বা কন্ট্রাক্ট ফাইল নেই।${note}`), detail: [] };
  if (!losses.length) return { speak: T(`No loss with ${name}: every one of ${items.length} flight seats/contract files made money; the thinnest was ${items[0].what} at ${tk(items[0].pr)}.${note}`, `${name}-এর সাথে কোনো লোকসান নেই: ${bnN(items.length, true)}টার সবকটাতেই লাভ; সবচেয়ে কম ${items[0].what} — ${tk(items[0].pr, true)}।${note}`), detail: [] };
  return { speak: T(`With ${name} we lost on ${losses.length}: ${losses.slice(0, 5).map((x) => `${x.what} −${tk(-x.pr)}`).join('; ')}.${note}`, `${name}-এর সাথে ${bnN(losses.length, true)}টায় লোকসান: ${losses.slice(0, 5).map((x) => `${x.what} −${tk(-x.pr, true)}`).join('; ')}।${note}`), detail: [] };
}

if (typeof window !== 'undefined') {
  window.EonFlightDesk = { answer };
  (window.__eonDomainQueue = window.__eonDomainQueue || []).push({ id: 'flightdesk', priority: 99.7, answer });
}
export default { answer };
