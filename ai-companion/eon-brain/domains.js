/* ============================================================
   EON · domains.js — the one place every space plugs its
   knowledge into Ask EON.

   A *domain* is anything that can answer a question about its
   field: the ERP (accounts, payroll, HR, CRM, tasks…), the teacher
   space, academics, the personal library. Ask EON consults the
   registered domains first — highest priority first — and only
   then falls through to its generic deadline/entity intents and,
   last, to the language-model fallback if one is connected.

   Register:
     window.EonDomains.register({
       id: 'erp.finance',          // unique
       priority: 80,               // higher = asked earlier (default 50)
       claims(q)  → boolean,       // optional: "this is mine, don't hand it to the agent"
       answer(q, ctx) → { speak, detail?, items?, actions?, trace? } | null | Promise
     });

   ctx = { data, records, brain, space, nq } — the brain's discovered
   data, the flattened records, window.EonBrain, the current space id
   and the normalised (lower-cased, trimmed) question.
   ============================================================ */

const _domains = [];
const _trace = [];

function norm(res, d) {
  if (!res) return null;
  if (typeof res === 'string') res = { speak: res };
  if (!res.speak) return null;
  res.detail = Array.isArray(res.detail) ? res.detail.join('\n') : (res.detail || '');
  res.domain = d.id;
  return res;
}

export const EonDomains = {
  register(d) {
    if (!d || !d.id || typeof d.answer !== 'function') throw new Error('EonDomains.register: {id, answer()} required');
    const i = _domains.findIndex((x) => x.id === d.id);
    const entry = Object.assign({ priority: 50 }, d);
    if (i >= 0) _domains[i] = entry; else _domains.push(entry);
    _domains.sort((a, b) => (b.priority || 0) - (a.priority || 0));
    return entry;
  },
  unregister(id) { const i = _domains.findIndex((x) => x.id === id); if (i >= 0) _domains.splice(i, 1); },
  list() { return _domains.map((d) => ({ id: d.id, priority: d.priority })); },
  /** true when some domain wants the question routed to it verbatim */
  claims(q) {
    for (const d of _domains) { try { if (d.claims && d.claims(String(q || ''))) return d.id; } catch {} }
    return null;
  },
  /** ask every domain in priority order; first non-null wins */
  async answer(q, ctx = {}) {
    const nq = String(q || '').toLowerCase().trim();
    const c = Object.assign({ nq, space: (window.EON_BRAIN_CONFIG && window.EON_BRAIN_CONFIG.space) || 'personal' }, ctx);
    for (const d of _domains) {
      try {
        const r = norm(await d.answer(String(q || ''), c), d);
        if (r) { _trace.unshift({ at: new Date().toISOString(), q, domain: d.id }); _trace.length = Math.min(_trace.length, 50); return r; }
      } catch (e) { console.warn('[EON domains] ' + d.id + ' failed:', e); }
    }
    return null;
  },
  trace() { return _trace.slice(); },
};

if (typeof window !== 'undefined') {
  window.EonDomains = Object.assign(window.EonDomains || {}, EonDomains);
  // Classic scripts that load before this module queue their domains on
  // window.__eonDomainQueue; drain it, then make future pushes register directly.
  try {
    const q = Array.isArray(window.__eonDomainQueue) ? window.__eonDomainQueue : [];
    q.forEach((d) => { try { EonDomains.register(d); } catch (e) { console.warn('[EON domains] queued register failed:', e); } });
    window.__eonDomainQueue = { push: (d) => { try { EonDomains.register(d); } catch (e) { console.warn('[EON domains] register failed:', e); } } };
  } catch {}
}
export default EonDomains;
