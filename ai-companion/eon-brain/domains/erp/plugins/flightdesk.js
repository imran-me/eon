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
import { latinSkeleton, banglaSkeleton } from './act.js';

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

const FLAG_BN = { deported: 'ডিপোর্ট', immigration_offload: 'অফলোড', rescheduled: 'রিশিডিউল' };
const STAGES = [
  ['information_entered', /information|info|তথ্য|ইনফরমেশন/i, 'Information Entered', 'তথ্য এন্ট্রি'],
  ['documents_ready', /documents?\s*ready|ডকুমেন্ট\s*রেডি|কাগজ\s*রেডি/i, 'Documents Ready', 'ডকুমেন্ট রেডি'],
  ['documents_verified', /verified|ভেরিফাই|যাচাই/i, 'Documents Verified', 'ডকুমেন্ট যাচাই'],
  ['route_confirmed', /route\s*confirm|রুট\s*কনফার্ম/i, 'Route Confirmed', 'রুট কনফার্ম'],
  ['ticket_issued', /ticket\s*issued|টিকেট\s*ইস্যু|টিকিট\s*ইস্যু/i, 'Ticket Issued', 'টিকেট ইস্যু'],
  ['case_closed', /closed|ক্লোজড|বন্ধ/i, 'Case Closed', 'কেস ক্লোজড'],
];
const stageName = (k, bn) => { const x = STAGES.find((z) => z[0] === k); return x ? (bn ? x[3] : x[2]) : String(k || '—').replace(/_/g, ' '); };

function screens() {
  return [
    [new RegExp("flights?\\s*(&|and|n|o|ar|এন্ড|অ্যান্ড|এণ্ড|ও|আর|এবং)?\\s*passengers?|ফ্লাইট\\s*(&|and|n|o|ar|এন্ড|অ্যান্ড|এণ্ড|ও|আর|এবং)?\\s*প্যাসেঞ্জার|ফ্লাইট\\s*(&|and|n|o|ar|এন্ড|অ্যান্ড|এণ্ড|ও|আর|এবং)?\\s*যাত্রী", 'i'), '/{role}/contract-flights', 'Flights & Passengers', 'ফ্লাইট ও প্যাসেঞ্জার'],
    [new RegExp("passenger\\s*board|প্যাসেঞ্জার\\s*বোর্ড|যাত্রী\\s*বোর্ড", 'i'), '/{role}/contract-flight-board', 'Passenger Board', 'প্যাসেঞ্জার বোর্ড'],
    [new RegExp("flight\\s*accounts?|ফ্লাইট\\s*(একাউন্টস?|অ্যাকাউন্টস?|হিসাব)", 'i'), '/{role}/contract-flights/accounts', 'Flight Accounts', 'ফ্লাইট অ্যাকাউন্টস'],
    [new RegExp("flight\\s*messages?|ফ্লাইট\\s*মেসেজ", 'i'), '/{role}/contract-flights/messages', 'Flight Messages', 'ফ্লাইট মেসেজ'],
    [new RegExp("flight\\s*categor|ফ্লাইট\\s*ক্যাটাগরি", 'i'), '/{role}/flight-categories', 'Flight Category', 'ফ্লাইট ক্যাটাগরি'],
    [new RegExp("flight\\s*dash\\s*board|ফ্লাইট\\s*(ড্যাশবোর্ড|ড্যাশ\\s*বোর্ড|ডেশবোর্ড|ড্যাসবোর্ড)|contract\\s*flights?$|কন্ট্রাক্ট\\s*ফ্লাইট", 'i'), '/{role}/contract-flights/dashboard', 'Flight Dashboard', 'ফ্লাইট ড্যাশবোর্ড'],
    [new RegExp("agent\\s*(ledger|statement)s?|এজেন্ট\\s*(লেজার|স্টেটমেন্ট)", 'i'), '/{role}/party-statement/agents', 'Agent Ledger', 'এজেন্ট লেজার'],
    [new RegExp("client\\s*(ledger|statement)s?|ক্লায়েন্ট\\s*(লেজার|স্টেটমেন্ট)", 'i'), '/{role}/party-statement/clients', 'Client Ledger', 'ক্লায়েন্ট লেজার'],
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

  // a swear word is frustration, not a name to look up
  if (/^(বাল|ধুর|ধ্যাৎ|fuck|shit|damn|bal)(?![ঀ-৿a-z])/i.test(s)) return { speak: T('Sorry — tell me again what to open or find.', 'দুঃখিত — আবার বলুন, কী খুলব বা কী জানতে চান?'), detail: [] };

  // 1) go to a screen of the flight desk or the agent ledger — a screen's name said alone is enough
  if ((GO.test(s) || s.split(/\s+/).length <= 5) && !PROFIT.test(s) && !HOWMANY.test(s) && !DUE.test(s)) {
    for (const [rx, href, en, b] of screens()) if (rx.test(s)) return open(href, bn, `Opening ${en}.`, `${b} খুলছি।`);
  }

  const isFlight = FLIGHT.test(s), isAgent = AGENT.test(s);
  const today = ymd(new Date()), yd = ymd(new Date(Date.now() - 864e5)), tm = ymd(new Date(Date.now() + 864e5));
  const mon = today.slice(0, 7);
  const paxOf = (f) => P.filter((p) => +p.contract_flight_id === +f.id);
  const live = (p) => !['deported', 'immigration_offload'].includes(p.exception_flag);

  // 2) one flight: by number, "the next flight", or "that flight" — details, passengers, money, open, print, pay the vendor
  let fn = flightNo(s), f1 = fn ? F.find((x) => String(x.flight_number).toUpperCase() === fn) : null;
  if (!fn && isFlight && /\b(next|porer|agami)\b|পরের|আগামী|নেক্সট/i.test(s) && !HOWMANY.test(s)) f1 = F.filter((x) => dayOf(x.departure_at) >= today).sort((a, b) => String(a.departure_at).localeCompare(String(b.departure_at)))[0] || null;
  if (!fn && !f1 && isFlight && /\b(oi|that|same)\s+flight|\b(this|ei)\s+flight\b(?!s)|ওই ফ্লাইট|এই ফ্লাইট(?!গুলো)|সেই ফ্লাইট|ওটা/i.test(s) && !THIS_MONTH.test(s) && !HOWMANY.test(s)) f1 = recall('flight');
  if (fn && !f1) return { speak: T(`I don't find ${fn} among the contract flights.`, `কন্ট্রাক্ট ফ্লাইটে ${fn} পাইনি।`), detail: [] };
  if (f1) {
    const f = f1, no = f.flight_number, pax = paxOf(f), on = pax.filter(live);
    remember({ flight: f });
    const pr = num(f.revenue) - num(f.cost_price);
    const agentDue = pax.reduce((a, p) => a + Math.max(0, num(p.agent_price) - num(p.agent_paid_amount)), 0);
    if (/\b(pay|payment|dao|dite|shodh|porishodh)\b|পে করো|পেমেন্ট|পরিশোধ|টাকা দাও|টাকা দিয়ে দাও/i.test(s) && /vendor|ভেন্ডর|ভেন্ডার/i.test(s)) {
      const r = open('/{role}/contract-flights/dashboard', bn, `${no}: the vendor${f.vendor ? ' (' + f.vendor + ')' : ''} is owed ${tk(num(f.due_amount))}. Opening the flight dashboard — press Pay Vendor on that flight's row, then fill the amount and account and save.`, `${no}: ভেন্ডর${f.vendor ? ' (' + f.vendor + ')' : ''}-কে দিতে হবে ${tk(num(f.due_amount), true)}। ফ্লাইট ড্যাশবোর্ড খুলছি — ওই ফ্লাইটের সারিতে Pay Vendor চাপুন, টাকা আর অ্যাকাউন্ট দিয়ে সেভ করুন।`);
      return r;
    }
    if (/passenger|যাত্রী|প্যাসেঞ্জার|jatri|kara|কারা|ke ke|কে কে|who is on|who's on|who are on|list|লিস্ট|তালিকা|names?|নাম/i.test(s) && !PRINT.test(s)) {
      const lines = pax.map((p) => `${p.name}${p.agent ? ' (' + p.agent + ')' : ''}${p.exception_flag ? ' — ' + (bn ? (FLAG_BN[p.exception_flag] || p.exception_flag) : p.exception_flag.replace('_', ' ')) : ''}`);
      return { speak: T(`${no} has ${pax.length} passengers: ${lines.slice(0, 10).join('; ')}${pax.length > 10 ? '…' : ''}.`, `${no}-এ ${bnN(pax.length, true)} জন যাত্রী: ${lines.slice(0, 10).join('; ')}${pax.length > 10 ? '…' : ''}।`), detail: [], actions: [{ label: T('Open flight', 'ফ্লাইট খুলুন'), kind: 'erp-open', href: N().url(`/{role}/contract-flights/${f.id}/passengers`) }] };
    }
    if (PROFIT.test(s) || DUE.test(s) || /\b(hisab|money|taka|cost|khoroch|revenue|ay)\b|হিসাব|টাকা|খরচ|আয়/i.test(s)) {
      return { speak: T(`${no}: revenue ${tk(num(f.revenue))}, cost ${tk(num(f.cost_price))} — ${pr >= 0 ? 'profit' : 'loss'} ${tk(Math.abs(pr))}. Vendor still owed ${tk(num(f.due_amount))}; agents still owe ${tk(agentDue)}.`, `${no}: আয় ${tk(num(f.revenue), true)}, খরচ ${tk(num(f.cost_price), true)} — ${pr >= 0 ? 'লাভ' : 'লোকসান'} ${tk(Math.abs(pr), true)}। ভেন্ডরকে এখনো দিতে হবে ${tk(num(f.due_amount), true)}; এজেন্টদের কাছে পাওনা ${tk(agentDue, true)}।`), detail: [] };
    }
    if (PRINT.test(s) || GO.test(s)) {
      const r = open(PRINT.test(s) ? `/{role}/contract-flights/${f.id}/invoice` : `/{role}/contract-flights/${f.id}/passengers`, bn, `${no} (${dayOf(f.departure_at)}, ${pax.length} passengers) — ${PRINT.test(s) ? 'opening it to print' : 'opening it'}.`, `${no} (${dayOf(f.departure_at)}, যাত্রী ${bnN(pax.length, true)} জন) — ${PRINT.test(s) ? 'প্রিন্টের জন্য খুলছি' : 'খুলছি'}।`);
      if (PRINT.test(s)) printAfterLoad(`/contract-flights/${f.id}/invoice`);
      return r;
    }
    // the whole picture of one flight
    const when = String(f.departure_at || '').slice(0, 16);
    const extra = [f.airline_flight_no, f.category, f.route].filter(Boolean).join(' · ');
    return { speak: T(`${no}${extra ? ' (' + extra + ')' : ''} departs ${when}, status ${f.status}. ${on.length} of ${pax.length} passengers still on it, ${num(f.seats_sold)}/${num(f.total_seats)} seats sold. Revenue ${tk(num(f.revenue))}, cost ${tk(num(f.cost_price))}, ${pr >= 0 ? 'profit' : 'loss'} ${tk(Math.abs(pr))}; vendor due ${tk(num(f.due_amount))}, agents owe ${tk(agentDue)}.`,
      `${no}${extra ? ' (' + extra + ')' : ''} ছাড়বে ${when}, স্ট্যাটাস ${f.status}। ${bnN(pax.length, true)} জনের মধ্যে ${bnN(on.length, true)} জন এখনো ফ্লাইটে, সিট বিক্রি ${bnN(num(f.seats_sold), true)}/${bnN(num(f.total_seats), true)}। আয় ${tk(num(f.revenue), true)}, খরচ ${tk(num(f.cost_price), true)}, ${pr >= 0 ? 'লাভ' : 'লোকসান'} ${tk(Math.abs(pr), true)}; ভেন্ডর বাকি ${tk(num(f.due_amount), true)}, এজেন্টদের কাছে পাওনা ${tk(agentDue, true)}।`),
      detail: [], actions: [{ label: T('Open flight', 'ফ্লাইট খুলুন'), kind: 'erp-open', href: N().url(`/{role}/contract-flights/${f.id}/passengers`) }] };
  }

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

  // 6b) one passenger by name: which flight, what stage, which agent, what is owed — or open their trail
  if (/\b(kothay|where|kon flight|which flight|status|stage|obostha|ki obostha)\b|কোথায়|কোন ফ্লাইটে|অবস্থা|স্টেজ|স্ট্যাটাস|ট্রেইল|trail/i.test(s) || (GO.test(s) && /passenger|যাত্রী|প্যাসেঞ্জার/i.test(s))) {
    const words = raw.split(/[\s,?।]+/).map((w) => w.replace(/(এর|ের|র|কে|er|ke)$/iu, '')).filter((w) => w.length >= 3);
    const key = (w) => (/[ঀ-৿]/.test(w) ? banglaSkeleton(w) : latinSkeleton(w));
    const keys = new Set(words.map(key).filter((k) => k.length >= 3));
    const scored = P.map((p) => ({ p, n: String(p.name || '').split(/\s+/).filter((x) => !/^(mr|md|mst|mrs|ms)\.?$/i.test(x)).filter((x) => keys.has(latinSkeleton(x))).length })).filter((x) => x.n > 0).sort((a, b) => b.n - a.n);
    if (scored.length) {
      const top = scored.filter((x) => x.n === scored[0].n).map((x) => x.p);
      if (top.length > 1 && new Set(top.map((p) => p.passport_holder_id)).size > 1) return { speak: T(`Which one — ${top.slice(0, 5).map((p) => `${p.name} (${p.flight_number})`).join(', ')}?`, `কোনজন — ${top.slice(0, 5).map((p) => `${p.name} (${p.flight_number})`).join(', নাকি ')}?`), detail: [], choices: top.slice(0, 5).map((p) => `${p.name} ${p.flight_number} ${bn ? 'কোথায়' : 'where'}`) };
      const p = top.sort((a, b) => String(b.departure_at).localeCompare(String(a.departure_at)))[0];
      remember({ passenger: p });
      const due = Math.max(0, num(p.agent_price) - num(p.agent_paid_amount));
      const href = N().url(`/{role}/contract-flight-passengers/${p.id}/trail`);
      if (GO.test(s) || /trail|ট্রেইল/i.test(s)) N().go(href);
      const flag = p.exception_flag ? (bn ? ` — ${FLAG_BN[p.exception_flag] || p.exception_flag}` : ` — ${p.exception_flag.replace('_', ' ')}`) : '';
      return { speak: T(`${p.name} is on ${p.flight_number} (${String(p.departure_at).slice(0, 16)}), stage ${stageName(p.stage)}${flag}. Agent ${p.agent || '—'}; price ${tk(num(p.agent_price))}, ${due > 0 ? tk(due) + ' still due' : 'fully paid'}.`, `${p.name} আছে ${p.flight_number}-এ (${String(p.departure_at).slice(0, 16)}), স্টেজ ${stageName(p.stage, true)}${flag}। এজেন্ট ${p.agent || '—'}; দাম ${tk(num(p.agent_price), true)}, ${due > 0 ? tk(due, true) + ' এখনো বাকি' : 'পুরো পরিশোধ'}।`), detail: [], navigate: (GO.test(s) || /trail|ট্রেইল/i.test(s)) ? href : undefined, actions: [{ label: T('Open passenger', 'যাত্রী খুলুন'), kind: 'erp-open', href }] };
    }
  }

  // 6c) passengers by stage
  if (/\b(stage|stages|step)\b|স্টেজ|ধাপ/i.test(s) || (HOWMANY.test(s) && STAGES.some((x) => x[1].test(s)))) {
    const st = STAGES.find((x) => x[1].test(s));
    const act = P.filter(live);
    if (st) { const rows = act.filter((p) => p.stage === st[0]);
      return { speak: T(`${rows.length} passengers at ${st[2]}${rows.length ? ': ' + rows.slice(0, 8).map((p) => `${p.name} (${p.flight_number})`).join('; ') : ''}.`, `${st[3]} ধাপে ${bnN(rows.length, true)} জন${rows.length ? ': ' + rows.slice(0, 8).map((p) => `${p.name} (${p.flight_number})`).join('; ') : ''}।`), detail: [] }; }
    const by = {}; for (const p of act) by[p.stage] = (by[p.stage] || 0) + 1;
    return { speak: T(`Passengers by stage: ${Object.entries(by).map(([k, v]) => `${stageName(k)} ${v}`).join('; ')}.`, `ধাপ অনুযায়ী যাত্রী: ${Object.entries(by).map(([k, v]) => `${stageName(k, true)} ${bnN(v, true)}`).join('; ')}।`), detail: [], actions: [{ label: T('Passenger board', 'প্যাসেঞ্জার বোর্ড'), kind: 'erp-open', href: N().url('/{role}/contract-flight-board') }] };
  }

  // 6d) rescheduled
  if (/reschedul|রিশিডিউল|রিসিডিউল/i.test(s) && (HOWMANY.test(s) || /\b(ke ke|kara|who|list)\b|কারা|তালিকা/i.test(s))) {
    const rows = P.filter((p) => p.exception_flag === 'rescheduled' && (!THIS_MONTH.test(s) || dayOf(p.departure_at).slice(0, 7) === mon));
    return { speak: T(`${rows.length} passengers rescheduled${THIS_MONTH.test(s) ? ' this month' : ''}: ${rows.slice(0, 8).map((p) => `${p.name} (now ${p.flight_number})`).join('; ')}.`, `${THIS_MONTH.test(s) ? 'এই মাসে ' : ''}${bnN(rows.length, true)} জন রিশিডিউল হয়েছে: ${rows.slice(0, 8).map((p) => `${p.name} (এখন ${p.flight_number})`).join('; ')}।`), detail: [] };
  }

  // 6e) flights by day: tomorrow, a date, this week, this month, departed, overdue
  if (isFlight && !isAgent && !PROFIT.test(s) && (YEST.test(s) || /\b(tomorrow|agamikal|kalke|this week|ei soptah|departed|overdue|chere geche|update)\b|আগামীকাল|এই সপ্তাহে|ছেড়ে গেছে|উড়ে গেছে|ওভারডিউ|আপডেট|\d{1,2}\s*(nov|dec|oct|নভেম্বর|ডিসেম্বর|অক্টোবর)/i.test(s) || THIS_MONTH.test(s))) {
    let list, what;
    const dm = s.match(/(\d{1,2})\s*(nov|dec|oct|sep|নভেম্বর|ডিসেম্বর|অক্টোবর|সেপ্টেম্বর)/i);
    if (/overdue|update|ওভারডিউ|আপডেট/i.test(s)) { list = F.filter((f) => dayOf(f.departure_at) < today && f.status === 'open'); what = T('past their date and still open (need update)', 'তারিখ পার হয়েছে কিন্তু এখনো ওপেন (আপডেট দরকার)'); }
    else if (/departed|chere geche|ছেড়ে গেছে|উড়ে গেছে/i.test(s)) { list = F.filter((f) => f.status === 'departed'); what = T('departed', 'ছেড়ে গেছে'); }
    else if (dm) { const M = { nov: 11, dec: 12, oct: 10, sep: 9, 'নভেম্বর': 11, 'ডিসেম্বর': 12, 'অক্টোবর': 10, 'সেপ্টেম্বর': 9 }[dm[2].toLowerCase()]; const d = `${today.slice(0, 4)}-${String(M).padStart(2, '0')}-${String(dm[1]).padStart(2, '0')}`; list = F.filter((f) => dayOf(f.departure_at) === d); what = T(`on ${d}`, `${d} তারিখে`); }
    else if (/\b(tomorrow|agamikal)\b|আগামীকাল/i.test(s) || (/kalke|কালকে|কাল/.test(s) && !/hoyeche|geche|হয়েছে|গেছে|ছিল/.test(s))) { list = F.filter((f) => dayOf(f.departure_at) === tm); what = T('tomorrow', 'আগামীকাল'); }
    else if (YEST.test(s)) { list = F.filter((f) => dayOf(f.departure_at) === yd); what = T('yesterday', 'গতকাল'); }
    else if (/this week|ei soptah|এই সপ্তাহে/i.test(s)) { const end = ymd(new Date(Date.now() + 7 * 864e5)); list = F.filter((f) => dayOf(f.departure_at) >= today && dayOf(f.departure_at) <= end); what = T('in the next 7 days', 'সামনের ৭ দিনে'); }
    else { list = F.filter((f) => dayOf(f.departure_at).slice(0, 7) === mon); what = T('this month', 'এই মাসে'); }
    const lines = list.slice(0, 8).map((f) => `${f.flight_number} ${String(f.departure_at).slice(0, 16)} — ${paxOf(f).filter(live).length} ${T('pax', 'জন')}`);
    if (list.length === 1) remember({ flight: list[0] });
    return { speak: list.length ? T(`${list.length} flight${list.length > 1 ? 's' : ''} ${what}: ${lines.join('; ')}.`, `${what} ${bnN(list.length, true)}টা ফ্লাইট: ${lines.join('; ')}।`) : T(`No flight ${what}.`, `${what} কোনো ফ্লাইট নেই।`), detail: [], actions: [{ label: T('Flight dashboard', 'ফ্লাইট ড্যাশবোর্ড'), kind: 'erp-open', href: N().url('/{role}/contract-flights/dashboard') }] };
  }

  // 6f) flight money: what we owe vendors, what agents owe on flights, this month's flight profit, by category
  if (isFlight && !isAgent && (/vendor|ভেন্ডর|ভেন্ডার/i.test(s) && (DUE.test(s) || /dite hobe|দিতে হবে|pay/i.test(s)))) {
    const rows = F.filter((f) => num(f.due_amount) > 0).sort((a, b) => num(b.due_amount) - num(a.due_amount));
    const tot = rows.reduce((a, f) => a + num(f.due_amount), 0);
    return { speak: rows.length ? T(`We owe flight vendors ${tk(tot)} on ${rows.length} flights: ${rows.slice(0, 6).map((f) => `${f.flight_number}${f.vendor ? ' (' + f.vendor + ')' : ''} ${tk(num(f.due_amount))}`).join('; ')}.`, `ফ্লাইটের ভেন্ডরদের দিতে হবে ${tk(tot, true)}, ${bnN(rows.length, true)}টা ফ্লাইটে: ${rows.slice(0, 6).map((f) => `${f.flight_number}${f.vendor ? ' (' + f.vendor + ')' : ''} ${tk(num(f.due_amount), true)}`).join('; ')}।`) : T('Nothing is owed to any flight vendor.', 'কোনো ফ্লাইট ভেন্ডরের কাছে দেনা নেই।'), detail: [] };
  }
  if (isFlight && PROFIT.test(s) && /categor|ক্যাটাগরি|ধরন/i.test(s)) {
    const g = {}; for (const f of F) { const k = f.category || '—'; g[k] = (g[k] || 0) + num(f.revenue) - num(f.cost_price); }
    const rows = Object.entries(g).sort((a, b) => b[1] - a[1]);
    return { speak: T(`Flight profit by category: ${rows.map(([k, v]) => `${k} ${v < 0 ? '−' : ''}${tk(Math.abs(v))}`).join('; ')}.`, `ক্যাটাগরি অনুযায়ী ফ্লাইটের লাভ: ${rows.map(([k, v]) => `${k} ${v < 0 ? '−' : ''}${tk(Math.abs(v), true)}`).join('; ')}।`), detail: [] };
  }
  if (isFlight && !isAgent && PROFIT.test(s) && !WHICH.test(s)) {
    const sc = THIS_MONTH.test(s) ? F.filter((f) => dayOf(f.departure_at).slice(0, 7) === mon) : F;
    const rev = sc.reduce((a, f) => a + num(f.revenue), 0), cost = sc.reduce((a, f) => a + num(f.cost_price), 0);
    const when = THIS_MONTH.test(s) ? T('this month', 'এই মাসে') : T('on record', 'রেকর্ডে');
    return { speak: T(`Contract flights ${when}: ${sc.length} flights, revenue ${tk(rev)}, cost ${tk(cost)} — ${rev - cost >= 0 ? 'profit' : 'loss'} ${tk(Math.abs(rev - cost))}.`, `${when} কন্ট্রাক্ট ফ্লাইট: ${bnN(sc.length, true)}টা ফ্লাইট, আয় ${tk(rev, true)}, খরচ ${tk(cost, true)} — ${rev - cost >= 0 ? 'লাভ' : 'লোকসান'} ${tk(Math.abs(rev - cost), true)}।`), detail: [] };
  }
  if (isFlight && isAgent && DUE.test(s)) {
    const g = new Map(); for (const p of P) { const d = Math.max(0, num(p.agent_price) - num(p.agent_paid_amount)); if (d > 0) g.set(p.agent || '—', (g.get(p.agent || '—') || 0) + d); }
    const rows = [...g.entries()].sort((a, b) => b[1] - a[1]); const tot = rows.reduce((a, x) => a + x[1], 0);
    return { speak: T(`Agents owe ${tk(tot)} on flight seats: ${rows.slice(0, 6).map(([k, v]) => `${k} ${tk(v)}`).join('; ')}.`, `ফ্লাইট সিট বাবদ এজেন্টদের কাছে পাওনা ${tk(tot, true)}: ${rows.slice(0, 6).map(([k, v]) => `${k} ${tk(v, true)}`).join('; ')}।`), detail: [] };
  }

  // 6g) the messages screen ("send a message to the passengers")
  if (/message|মেসেজ|sms|এসএমএস/i.test(s) && (isFlight || /passenger|যাত্রী/i.test(s))) return open('/{role}/contract-flights/messages', bn, 'Opening Flight Messages — pick the flight and the passengers there, then send.', 'ফ্লাইট মেসেজ খুলছি — ওখানে ফ্লাইট আর যাত্রী বেছে পাঠান।');

  // 7) one agent: what they owe, how many services, where we lost — a ranking ("which agents owe the most") is the server's
  if (/\b(which|kon|most|top|beshi|sobcheye)\b|কোন|সবচেয়ে|বেশি|টপ/i.test(s) && !/service|সার্ভিস|সেবা/i.test(s)) return null;
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
  // what they owe is their ledger (debit − credit), the same figure the server's agent dues use
  const led = (D.party_transactions || []).filter((t) => String(t.party_name).toUpperCase() === String(party.name).toUpperCase());
  const ledger = led.reduce((a, t) => a + num(t.debit) - num(t.credit), 0);
  const invDue = ts.reduce((a, x) => a + num(x.due_amount), 0) + vs.reduce((a, x) => a + num(x.due_amount), 0) + cf.reduce((a, x) => a + num(x.due_amount), 0) + px.reduce((a, p) => a + Math.max(0, num(p.agent_price) - num(p.agent_paid_amount)), 0);
  const due = led.length ? ledger : invDue;
  const name = party.name;
  if (DUE.test(s) && !SERVICE.test(s)) {
    const parts = [[T('tickets', 'টিকেট'), ts.reduce((a, x) => a + num(x.due_amount), 0)], [T('visas', 'ভিসা'), vs.reduce((a, x) => a + num(x.due_amount), 0)], [T('contract files', 'কন্ট্রাক্ট ফাইল'), cf.reduce((a, x) => a + num(x.due_amount), 0)], [T('flights', 'ফ্লাইট'), px.reduce((a, p) => a + Math.max(0, num(p.agent_price) - num(p.agent_paid_amount)), 0)]].filter((x) => x[1] > 0);
    const u = N().url('/{role}/party-statement') + '?party_id=' + id;
    const word = due >= 0 ? T('owes us', 'এর কাছে পাওনা') : T('is in credit by', 'এর আমাদের কাছে জমা');
    const bits = parts.length ? T(' Open invoices: ', ' খোলা ইনভয়েস: ') + parts.map((x) => `${x[0]} ${tk(x[1], bn)}`).join(', ') + (bn ? '।' : '.') : '';
    return { speak: T(`${name} ${word} ${tk(Math.abs(due))} by the ledger.${bits}`, `লেজার অনুযায়ী ${name}-${word} ${tk(Math.abs(due), true)}।${bits}`), detail: [], actions: [{ label: T('Open ledger', 'লেজার খুলুন'), kind: 'erp-open', href: u }] };
  }
  if (SERVICE.test(s) && !PROFIT.test(s)) {
    const n = ts.length + vs.length + cf.length + px.length;
    return { speak: T(`${name} has taken ${n} services: ${ts.length} ticket sales, ${vs.length} visa sales, ${cf.length} contract files, ${px.length} flight seats. Ledger balance ${tk(due)}.`, `${name} মোট ${bnN(n, true)}টা সার্ভিস নিয়েছে: টিকেট ${bnN(ts.length, true)}টা, ভিসা ${bnN(vs.length, true)}টা, কন্ট্রাক্ট ফাইল ${bnN(cf.length, true)}টা, ফ্লাইট সিট ${bnN(px.length, true)}টা। লেজারে ব্যালেন্স ${tk(due, true)}।`), detail: [] };
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
