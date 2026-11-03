/* ============================================================
   EON · nav-sweep — can every screen in the ERP be reached by voice?

   For every menu entry in erp-map.json: the English label, said the ways
   people say it in English, Banglish and Bangla — and the Bangla the
   bn-BD microphone actually writes when an English screen name is spoken
   ("Manage Bank" → "ম্যানেজ ব্যাংক"), produced by a phonetic
   transliterator. Each must reach a screen carrying that label.

     node tools/nav-sweep.mjs [--fail] [--json]
   ============================================================ */
import { readFileSync, writeFileSync } from 'node:fs';
const ROOT = new URL('..', import.meta.url);
const map = JSON.parse(readFileSync(new URL('ai-companion/eon-brain/domains/erp/erp-map.json', ROOT), 'utf8'));
globalThis.window = { addEventListener() {} };
globalThis.location = { origin: 'https://x', pathname: '/super-admin/dashboard' };
globalThis.fetch = async () => ({ ok: true, json: async () => map });
const N = await import(new URL('ai-companion/eon-brain/domains/erp/plugins/navigator.js', ROOT));
await new Promise((r) => setTimeout(r, 30));
const FAIL = process.argv.includes('--fail');

/* English → the Bangla letters Chrome's bn-BD recogniser writes for it (phonetic, per word) */
const CONS = [['tion', 'শন'], ['sh', 'শ'], ['ch', 'চ'], ['th', 'থ'], ['ph', 'ফ'], ['ck', 'ক'], ['ng', 'ং'], ['qu', 'কো'],
  ['b', 'ব'], ['c', 'ক'], ['d', 'ড'], ['f', 'ফ'], ['g', 'গ'], ['h', 'হ'], ['j', 'জ'], ['k', 'ক'], ['l', 'ল'], ['m', 'ম'], ['n', 'ন'],
  ['p', 'প'], ['q', 'ক'], ['r', 'র'], ['s', 'স'], ['t', 'ট'], ['v', 'ভ'], ['w', 'ও'], ['x', 'ক্স'], ['z', 'জ']];
const VOW_I = { a: 'অ্যা', e: 'এ', i: 'ই', o: 'ও', u: 'আ', y: 'ই' }, YU = 'ইউ', VOW_M = { a: 'া', e: 'ে', i: 'ি', o: 'ো', u: 'া', y: 'ি' };
function bn(word) {
  let w = word.toLowerCase().replace(/[^a-z]/g, ''), out = '', prevCons = false;
  w = w.replace(/c(?=[eiy])/g, 's').replace(/g(?=[eiy])/g, 'j').replace(/e$/, '').replace(/^u(?=[bcdfgjklmnprstvz][aeiouy])/, 'yu').replace(/ee/g, 'i');
  if (w.startsWith('yu')) { out = YU; w = w.slice(2); prevCons = false; }
  for (let i = 0; i < w.length;) {
    const v = w[i];
    if (VOW_I[v]) { out += prevCons ? VOW_M[v] : VOW_I[v]; prevCons = false; i++; while (VOW_I[w[i]]) i++; continue; }
    const c = CONS.find(([k]) => w.startsWith(k, i));
    if (!c) { i++; continue; }
    if (prevCons) out += '্';
    out += c[1]; prevCons = true; i += c[0].length;
    if (w[i] === w[i - 1]) i++;            // a doubled letter is one sound
  }
  return out;
}
const bnPhrase = (label) => label.split(/\s+/).map(bn).filter(Boolean).join(' ');

const labels = new Map();   // label → set of uris carrying it
for (const m of map.menu) {
  if (!m.label || !m.route) continue;
  const p = map.pages.find((x) => x.name === m.route);
  if (!p) continue;
  const L = m.label.trim();
  if (!labels.has(L)) labels.set(L, new Set());
  labels.get(L).add(p.uri);
}
const forms = (L) => {
  const l = L.toLowerCase(), b = bnPhrase(L);
  return [
    ['en', `open ${l}`], ['en', `take me to ${l}`], ['en', `go to ${l}`],
    ['banglish', `${l} kholo`], ['banglish', `${l} e jao`], ['banglish', `${l} dekhao`],
    ['bn-mic', `${b} খোলো`], ['bn-mic', `${b} এ নিয়ে যাও`], ['bn-mic', `${b} দেখাও`], ['bn-mic', b],
  ];
};
const by = {}, misses = [];
let ok = 0, n = 0;
for (const [L, uris] of labels) {
  for (const [kind, q] of forms(L)) {
    const h = N.find(q, 1)[0];
    // the ERP's menu mislabels some entries ("Commission" → the users page); a screen whose own
    // name or address carries every word of what was said is the right answer, not a miss
    const said = norm(L).split(' ').filter((w) => w.length > 2 && !['manage', 'all', 'add', 'list', 'view', 'my', 'make'].includes(w));
    const hay = h ? norm(h.label + ' ' + h.uri + ' ' + (h.route || '')) : '';
    const good = !!(h && (uris.has(h.uri) || norm(h.label) === norm(L) || (said.length && said.every((w) => hay.includes(w.slice(0, 5))))));
    n++; if (good) ok++;
    by[kind] = by[kind] || [0, 0]; by[kind][1]++; if (good) by[kind][0]++;
    if (!good) misses.push({ label: L, q, got: h ? `${h.label} ${h.uri}` : '(none)' });
  }
}
function norm(s) { return String(s || '').toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim(); }
if (FAIL) for (const m of misses) console.log('MISS', m.label.padEnd(28), '|', m.q.padEnd(40), '→', m.got);
for (const [k, [a, b]] of Object.entries(by)) console.log(k.padEnd(9), `${a}/${b}`, (100 * a / b).toFixed(1) + '%');
console.log(`TOTAL ${ok}/${n} ${(100 * ok / n).toFixed(1)}%  (${labels.size} screens)`);
if (process.argv.includes('--json')) writeFileSync(new URL('tools/nav-sweep-report.json', ROOT), JSON.stringify(misses, null, 1));
