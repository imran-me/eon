/* ============================================================
   EON · context — what we were just talking about.

   People do not repeat themselves. After "open Rabbi's statement" the
   next order is "now the PDF for October", then "print it"; after a
   refund is set up, "actually void it"; after one reissue, "same for
   Amir". Each acting part remembers the thing it just acted on here,
   and reads it back when a sentence points at it — by a pronoun
   (his, that, it, তার, ওটা), by "same", or by being a bare follow-up
   with no subject of its own.

   Memory is short on purpose: ten quiet minutes and it is gone, and a
   new name always wins over a remembered one. Remembering the wrong
   person for too long is how an assistant pays the wrong supplier.
   ============================================================ */
const TTL = 10 * 60 * 1000;
let mem = {};   // { party, ticket, passenger, case, period, order: { part, kind, values } , at }

export function remember(patch) { mem = Object.assign({}, fresh(), patch, { at: Date.now() }); return mem; }
export function forgetAll() { mem = {}; }
function fresh() { return mem.at && Date.now() - mem.at < TTL ? mem : {}; }
export function recall(key) { return fresh()[key] || null; }

/* a sentence pointing back: pronouns in both languages, and "same" */
export const POINTS_BACK = /\b(his|her|their|its|it|that|this|them|him|same|same thing|again|also|too|ota|eta|tar|or|oita)\b|তার|তাঁর|ওর|ওনার|উনার|ওটা|এটা|সেটা|ওইটা|একই|আবার|ওটাও|এটাও/i;
export const SAME = /\b(same|the same|do the same|same for|same thing|eki|eki vabe)\b|একই|একইভাবে|ওভাবেই/i;

/** a short order with no subject of its own: "pdf for october", "print it", "এক্সেল দাও" */
export function bareFollowUp(s) {
  const words = String(s || '').trim().split(/\s+/).filter(Boolean);
  return words.length <= 6;
}

if (typeof window !== 'undefined') window.EonContext = { remember, recall, forgetAll };
export default { remember, recall, forgetAll, POINTS_BACK, SAME, bareFollowUp };
