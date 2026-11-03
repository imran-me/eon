/* EON · ml — the page's side of app/ml-worker.js: one shared worker, promise per request. */
let w = null, n = 0; const wait = new Map();
function worker() {
  if (w) return w;
  w = new Worker(new URL('ml-worker.js', import.meta.url), { type: 'module' });
  w.onmessage = ({ data: m }) => { const p = wait.get(m.id); if (!p) return; wait.delete(m.id); m.ok ? p.resolve(m.result) : p.reject(new Error(m.error)); };
  return w;
}
/* phones and small machines skip the models (heat, battery, a 400 MB download): EON keeps its
   light voice and brain there. ?ml=1 forces them on. */
export const capable = typeof navigator !== 'undefined' && (/[?&]ml=1/.test(location.search) || ((navigator.hardwareConcurrency || 2) >= 6 && !/Android|iPhone|iPad|Mobile/i.test(navigator.userAgent)));
export function ml(op, payload = {}, transfer = []) {
  if (!capable) return Promise.reject(new Error('models are off on this device'));
  return new Promise((resolve, reject) => { const id = ++n; wait.set(id, { resolve, reject }); worker().postMessage(Object.assign({ id, op }, payload), transfer); });
}
if (typeof window !== 'undefined') window.EonML = ml;
export default ml;
