/* EON · ml — the page's side of app/ml-worker.js: one shared worker, promise per request. */
let w = null, n = 0; const wait = new Map();
function worker() {
  if (w) return w;
  w = new Worker(new URL('ml-worker.js', import.meta.url), { type: 'module' });
  w.onmessage = ({ data: m }) => { const p = wait.get(m.id); if (!p) return; wait.delete(m.id); m.ok ? p.resolve(m.result) : p.reject(new Error(m.error)); };
  return w;
}
export function ml(op, payload = {}, transfer = []) {
  return new Promise((resolve, reject) => { const id = ++n; wait.set(id, { resolve, reject }); worker().postMessage(Object.assign({ id, op }, payload), transfer); });
}
if (typeof window !== 'undefined') window.EonML = ml;
export default ml;
