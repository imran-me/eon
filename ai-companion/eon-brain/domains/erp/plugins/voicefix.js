/* ============================================================
   EON · voicefix — what was said, before what it means.

   Orders arrive through speech recognition, and speech recognition
   writes what it heard: "led jar", "re fund", "P N R V K 8 V U 3",
   "fifteen thousand", "পনেরো হাজার", "দেড় লাখ", "15k", "avoid the
   ticket". The plug-ins that act (statement, ticket, contract flight,
   pay…) read amounts, references and kind words with exact patterns,
   so they run the sentence through normalize() first.

   Three rules keep this from inventing meaning:
     · only the domain's own words are repaired, and only spellings that
       cannot be another word in context ("avoid" becomes void only next
       to ticket/PNR/invoice);
     · a number word becomes digits only where it is a number — "one"
       on its own stays "one" ("open the one for Karim");
     · the result is used for reading, never shown: what the boss sees
       quoted back is what EON understood, built from the parts it read.
   ============================================================ */

/* ---------- speech-recognition repairs ---------- */
const REPAIR = [
  [/\b(?:led\s*jar|lead\s*jar|ledgar|lejar|lezar|lazer|ledjer|lejer)\b/gi, 'ledger'],
  [/\b(?:state\s*ment|statment|stetment|statemant|stement|statemnt)s?\b/gi, 'statement'],
  [/\b(?:re[\s-]*fund|refand|rifund|refun)\b/gi, 'refund'],
  [/\b(?:re[\s-]+issue|reissu|rissue|re-?ishu|reishu)\b/gi, 'reissue'],
  [/\b(?:re[\s-]+schedule|reshedule|reschedul|reskedule|re-?shedule)\b/gi, 'reschedule'],
  [/\b(?:off[\s-]+load(ed)?|of\s+load(ed)?|offlod|ofload|offlode)\b/gi, (m, a, b) => 'offload' + (a || b || '')],
  [/\b(?:de[\s-]+port(ed)?|diport(ed)?|deportt)\b/gi, (m, a, b) => 'deport' + (a || b || '')],
  [/\b(?:avoid|boyd|voyd|void(?:e|ed))(?=\s+(?:the\s+|this\s+|that\s+)?(?:ticket|pnr|invoice|inv))/gi, 'void'],
  [/\b(?:p\.?\s*n\.?\s*r\.?|pee\s*en\s*are?|pnr\s*number|pnr\s*no\.?)\b/gi, 'PNR'],
  [/\b(?:inv\.?\s*no\.?|invoice\s*no\.?|invoice\s*number)\b/gi, 'invoice'],
  [/\b(?:pdf\s*file|p\s*d\s*f)\b/gi, 'pdf'],
  [/\b(?:excel\s*file|excel\s*sheet|xl\s*sheet|exel|excell)\b/gi, 'excel'],
  [/\b(?:cash\s*flo|kash\s*flow|cashflow)\b/gi, 'cash flow'],
  [/\b(?:pass\s*port)\b/gi, 'passport'],
  [/\b(?:ticked|tiket|tikit|ticket\s*no\.?)\b/gi, 'ticket'],
  [/\b(?:pay\s*roll)\b/gi, 'payroll'],
  // Banglish orders: the verb is the boss's own, the noun is English
  [/\b(?:dekhaw|dekhau|dekhan|dekho)\b/gi, 'dekhao'],
  [/\b(?:koren|korun|koro|kor|kore\s*dao|kore\s*den)\b/gi, 'koro'],
  // বাংলা spellings recognition produces for the same words
  [/ষ্টেটমেন্ট|স্টেটমেন(?!্ট)|স্টেটমেন্ট/g, 'স্টেটমেন্ট'],
  [/লেজার|লেজ্জার|লেডজার/g, 'লেজার'],
  [/রিফান(?!্ড)|রিফান্ট|রিফান্ড/g, 'রিফান্ড'],
  [/ভোয়েড|ভয়েড|ভয়েড|ভয়ড/g, 'ভয়েড'],
  [/অফ\s+লোড|অফলোড|অফলোডেড/g, 'অফলোড'],
  [/ডিপোর্টেড|ডিপোট/g, 'ডিপোর্ট'],
  [/পি\s*এন\s*আর/g, 'পিএনআর'],
  [/রি\s*ইস্যু|রিইসু|রি-ইস্যু/g, 'রিইস্যু'],
  [/রি\s*শিডিউল|রিসিডিউল|রিশিডুল/g, 'রিশিডিউল'],
];

/* "V K 8 V U 3" — a reference spelled out letter by letter. Four or more single
   letters/digits in a row are one token; three could be "a b c" in a sentence. */
function joinSpelled(s) {
  return s.replace(/\b(?:[A-Za-z0-9](?:\s+|-)){3,}[A-Za-z0-9]\b/g, (m) => {
    const t = m.replace(/[\s-]+/g, '');
    return /\d/.test(t) || t.length >= 5 ? t.toUpperCase() : m;
  });
}

/* ---------- numbers said as words ---------- */
const EN_UNITS = { zero: 0, one: 1, two: 2, three: 3, four: 4, five: 5, six: 6, seven: 7, eight: 8, nine: 9, ten: 10, eleven: 11, twelve: 12, thirteen: 13, fourteen: 14, fifteen: 15, sixteen: 16, seventeen: 17, eighteen: 18, nineteen: 19 };
const EN_TENS = { twenty: 20, thirty: 30, forty: 40, fourty: 40, fifty: 50, sixty: 60, seventy: 70, eighty: 80, ninety: 90 };
const EN_SCALE = { hundred: 100, thousand: 1e3, k: 1e3, lakh: 1e5, lac: 1e5, lakhs: 1e5, lacs: 1e5, million: 1e6, crore: 1e7, crores: 1e7 };
const EN_WORD = `(?:${[...Object.keys(EN_UNITS), ...Object.keys(EN_TENS)].join('|')})`;
const EN_RUN = new RegExp(`\\b(?:(?:${EN_WORD}|\\d+(?:\\.\\d+)?|and|a|half)[\\s-]*)+(?:hundred|thousand|lakhs?|lacs?|million|crores?)(?:[\\s-]*(?:${EN_WORD}|\\d+|and|hundred|thousand|lakhs?|lacs?))*\\b`, 'gi');

function enWordsToNumber(phrase) {
  let total = 0, cur = 0, seen = false;
  for (const w of phrase.toLowerCase().split(/[\s-]+/).filter(Boolean)) {
    if (w === 'and') continue;
    if (w === 'a') { cur = cur || 1; continue; }
    if (w === 'half') { cur += 0.5; seen = true; continue; }
    if (/^\d+(\.\d+)?$/.test(w)) { cur += parseFloat(w); seen = true; continue; }
    if (w in EN_UNITS) { cur += EN_UNITS[w]; seen = true; continue; }
    if (w in EN_TENS) { cur += EN_TENS[w]; seen = true; continue; }
    const sc = EN_SCALE[w];
    if (!sc) return null;
    if (sc === 100) { cur = (cur || 1) * 100; continue; }
    total += (cur || 1) * sc; cur = 0; seen = true;
  }
  return seen ? Math.round(total + cur) : null;
}

/* বাংলা numbers 1–99 are irregular words, not composed from tens and units */
const BN_1_99 = ('এক দুই তিন চার পাঁচ ছয় সাত আট নয় দশ এগারো বারো তেরো চোদ্দ পনেরো ষোলো সতেরো আঠারো উনিশ বিশ ' +
  'একুশ বাইশ তেইশ চব্বিশ পঁচিশ ছাব্বিশ সাতাশ আঠাশ ঊনত্রিশ ত্রিশ একত্রিশ বত্রিশ তেত্রিশ চৌত্রিশ পঁয়ত্রিশ ছত্রিশ সাঁইত্রিশ আটত্রিশ ঊনচল্লিশ চল্লিশ ' +
  'একচল্লিশ বিয়াল্লিশ তেতাল্লিশ চুয়াল্লিশ পঁয়তাল্লিশ ছেচল্লিশ সাতচল্লিশ আটচল্লিশ ঊনপঞ্চাশ পঞ্চাশ একান্ন বাহান্ন তিপ্পান্ন চুয়ান্ন পঞ্চান্ন ছাপ্পান্ন সাতান্ন আটান্ন ঊনষাট ষাট ' +
  'একষট্টি বাষট্টি তেষট্টি চৌষট্টি পঁয়ষট্টি ছেষট্টি সাতষট্টি আটষট্টি ঊনসত্তর সত্তর একাত্তর বাহাত্তর তিয়াত্তর চুয়াত্তর পঁচাত্তর ছিয়াত্তর সাতাত্তর আটাত্তর ঊনআশি আশি ' +
  'একাশি বিরাশি তিরাশি চুরাশি পঁচাশি ছিয়াশি সাতাশি অষ্টআশি ঊননব্বই নব্বই একানব্বই বিরানব্বই তিরানব্বই চুরানব্বই পঁচানব্বই ছিয়ানব্বই সাতানব্বই আটানব্বই নিরানব্বই').split(' ');
const BN_NUM = new Map(BN_1_99.map((w, i) => [w, i + 1]));
// spellings recognition and people both use
[['দুয়ে', 2], ['ছয়', 6], ['নয়', 9], ['চৌদ্দ', 14], ['ষোল', 16], ['আঠার', 18], ['উনত্রিশ', 29], ['উনচল্লিশ', 39], ['উনপঞ্চাশ', 49], ['উনষাট', 59], ['উনসত্তর', 69], ['উনআশি', 79], ['উননব্বই', 89], ['একশ', 100], ['একশো', 100]].forEach(([w, n]) => BN_NUM.set(w, n));
const BN_FRAC = { 'দেড়': 1.5, 'দেড়': 1.5, 'আড়াই': 2.5, 'আড়াই': 2.5, 'সাড়ে': 0.5, 'সাড়ে': 0.5 };
const BN_SCALE = { 'শ': 100, 'শো': 100, 'শত': 100, 'হাজার': 1e3, 'লাখ': 1e5, 'লক্ষ': 1e5, 'কোটি': 1e7 };
const BN_TOKEN = new RegExp(`(?:[০-৯0-9]+(?:\\.[০-৯0-9]+)?|${[...BN_NUM.keys(), ...Object.keys(BN_FRAC)].sort((a, b) => b.length - a.length).join('|')})`);
const BN_RUN = new RegExp(`(?:${BN_TOKEN.source}\\s*(?:${Object.keys(BN_SCALE).join('|')})?\\s*)+(?:${Object.keys(BN_SCALE).join('|')})(?:\\s*${BN_TOKEN.source})?(?![ঀ-৿])`, 'g');
const latin = (s) => String(s).replace(/[০-৯]/g, (d) => '০১২৩৪৫৬৭৮৯'.indexOf(d));

function bnWordsToNumber(phrase) {
  const toks = phrase.match(new RegExp(`${BN_TOKEN.source}|${Object.keys(BN_SCALE).join('|')}`, 'g')) || [];
  let total = 0, cur = 0, half = 0;
  for (const t of toks) {
    if (t in BN_SCALE) {
      const sc = BN_SCALE[t];
      if (sc === 100) { cur = (cur || 1) * 100; continue; }
      total += ((cur || 1) + half) * sc; cur = 0; half = 0; continue;
    }
    if (t === 'সাড়ে' || t === 'সাড়ে') { half = 0.5; continue; }
    if (t in BN_FRAC) { cur += BN_FRAC[t]; continue; }
    if (BN_NUM.has(t)) { cur += BN_NUM.get(t); continue; }
    cur += parseFloat(latin(t));
  }
  return Math.round(total + cur + half);
}

export function numbersToDigits(s) {
  let out = String(s || '');
  // 15k · 2.5k · 1.5 lakh · 2 lac (digits with a scale word)
  out = out.replace(/\b(\d+(?:\.\d+)?)\s*(k|thousand|lakhs?|lacs?|crores?|million)\b/gi, (m, n, u) => String(Math.round(parseFloat(n) * EN_SCALE[u.toLowerCase()])));
  out = out.replace(EN_RUN, (m) => { const n = enWordsToNumber(m.trim()); return n == null ? m : ` ${n} `; });
  out = out.replace(BN_RUN, (m) => { const n = bnWordsToNumber(m.trim()); return n ? ` ${n} ` : m; });
  return out.replace(/[ \t]{2,}/g, ' ').trim();
}

/** true when the boss is speaking Bangla — by the words, not by the first letter */
export function speaksBangla(q) {
  const words = String(q || '').split(/[\s,.;:!?।]+/).filter(Boolean);
  const bn = words.filter((w) => /[ঀ-৿]/.test(w)).length;
  const en = words.filter((w) => /[A-Za-z]{2,}/.test(w) && !/[ঀ-৿]/.test(w)).length;
  return bn > 0 && bn * 2 >= en;
}

export function normalize(q) {
  let s = String(q || '');
  for (const [re, to] of REPAIR) s = s.replace(re, to);
  s = joinSpelled(s);
  s = numbersToDigits(s);
  return s;
}

if (typeof window !== 'undefined') window.EonVoiceFix = { normalize, numbersToDigits };
export default { normalize, numbersToDigits, speaksBangla };
