/* ============================================================
   EON · ERP domain — entry point. Loads the knowledge, the four
   decision layers, the Multi Decision Layer and the answerer, and
   exposes one object the app and the companion both use:

     window.EonErp = {
       ready,           // Promise → dataset loaded
       dataset(),       // the current dataset object
       setDataset(D),   // called by the adapter (server or demo)
       company(),       // active company scope (id|null) — the boss's lens
       setCompany(id),
       kpis(), brief(), decisions(), approvals(),
       finance, people, crm, ops, knowledge, qa, draft
     }
   ============================================================ */
import * as K from './knowledge.js';
import * as F from './finance.js';
import * as P from './people.js';
import * as C from './crm.js';
import * as O from './ops.js';
import * as X from './decisions.js';
import * as Q from './qa.js';
import { generateDemo } from './demo-data.js';
import './plugins/index.js';   // EON 2 abilities — each plug-in registers itself (answerer, decisions, screen)
import { fmtBDT, fmtBDTk } from './dataset.js';

let _D = null, _company = null, _resolve;
const _ready = new Promise((r) => { _resolve = r; });
const _listeners = new Set();
function emit() { _listeners.forEach((fn) => { try { fn(_D); } catch (e) { console.warn('[EON erp] listener failed:', e); } }); }

export const EonErp = {
  ready: _ready,
  dataset() { return _D; },
  setDataset(D) { _D = D; try { window.dispatchEvent(new CustomEvent('eon:erp-data', { detail: { source: D && D.meta && D.meta.source } })); } catch {} _resolve(D); emit(); return D; },
  onData(fn) { _listeners.add(fn); if (_D) fn(_D); return () => _listeners.delete(fn); },
  company() { return _company; },
  setCompany(id) { _company = id == null ? null : +id; try { window.dispatchEvent(new CustomEvent('eon:erp-company', { detail: { company: _company } })); } catch {} emit(); },
  companies() { return _D ? _D.companies : []; },
  scope(o) { return Object.assign({ company: _company }, o || {}); },
  kpis(o) { return _D ? X.kpis(_D, this.scope(o)) : null; },
  brief(o) { return _D ? X.brief(_D, this.scope(o)) : null; },
  decisions(o) { return _D ? X.all(_D, this.scope(o)) : []; },
  approvals(o) { return _D ? X.approvals(_D, this.scope(o)) : { items: [], count: 0, amount: 0, byKind: [] }; },
  answer(q) { return Q.answer(q); },
  draft: Q.draft,
  finance: F, people: P, crm: C, ops: O, knowledge: K, qa: Q, decisionsLayer: X,
  demo(opts) { return this.setDataset(generateDemo(opts)); },
  fmtBDT, fmtBDTk,
  source() { return _D && _D.meta ? _D.meta.source : null; },
};

if (typeof window !== 'undefined') {
  window.EonErp = Object.assign(window.EonErp || {}, EonErp);
  // the adapter may have published before this module loaded
  if (window.__EON_ERP_PENDING) { try { EonErp.setDataset(window.__EON_ERP_PENDING); } catch {} delete window.__EON_ERP_PENDING; }
  // no adapter on the page at all → demo dataset after a short grace period
  setTimeout(() => { if (!_D && !window.EonErpAdapter) { console.info('[EON erp] no adapter — using the demo dataset'); EonErp.demo(); } }, 1500);
}
export default EonErp;
