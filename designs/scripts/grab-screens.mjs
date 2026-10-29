/* Grab high-resolution screenshots of the live EON panel for the stall artwork.
 *
 * Runs headless against eon.gulfrabit.com/eon/ at deviceScaleFactor 3, asks a
 * couple of real questions through the panel's own UI, then captures the
 * conversation. Signed out the figures come from the demo dataset; the chrome,
 * type and layout are the real product.
 *
 * Usage: node grab-screens.mjs <chrome.exe> <outDir>
 */
import { spawn } from 'node:child_process';
import { writeFileSync, mkdirSync } from 'node:fs';
import { join } from 'node:path';

const [CHROME, OUT] = process.argv.slice(2);
mkdirSync(OUT, { recursive: true });
const PORT = 9341;
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

const chrome = spawn(CHROME, [
  '--headless=new', `--remote-debugging-port=${PORT}`,
  '--use-angle=swiftshader', '--enable-unsafe-swiftshader',
  '--hide-scrollbars', '--no-first-run', '--no-default-browser-check',
  '--user-data-dir=' + join(OUT, '.chrome-profile'),
  'about:blank',
], { stdio: 'ignore' });

class CDP {
  constructor(ws) { this.ws = ws; this.id = 0; this.waits = new Map(); }
  static async open(url) {
    const ws = new WebSocket(url);
    await new Promise((res, rej) => { ws.onopen = res; ws.onerror = rej; });
    const c = new CDP(ws);
    ws.onmessage = (m) => {
      const msg = JSON.parse(m.data);
      if (msg.id && c.waits.has(msg.id)) {
        const { res, rej } = c.waits.get(msg.id); c.waits.delete(msg.id);
        msg.error ? rej(new Error(JSON.stringify(msg.error))) : res(msg.result);
      }
    };
    return c;
  }
  send(method, params = {}, sessionId) {
    const id = ++this.id;
    return new Promise((res, rej) => {
      this.waits.set(id, { res, rej });
      this.ws.send(JSON.stringify({ id, method, params, sessionId }));
    });
  }
}

async function version() {
  for (let i = 0; i < 60; i++) {
    try { const r = await fetch(`http://127.0.0.1:${PORT}/json/version`); if (r.ok) return r.json(); } catch {}
    await sleep(500);
  }
  throw new Error('no debugging port');
}

const v = await version();
const browser = await CDP.open(v.webSocketDebuggerUrl);
const { targetId } = await browser.send('Target.createTarget', { url: 'about:blank' });
const { sessionId } = await browser.send('Target.attachToTarget', { targetId, flatten: true });
const S = (m, p) => browser.send(m, p, sessionId);

await S('Page.enable');
await S('Runtime.enable');
// 3x pixel ratio: the panel is a UI screenshot that will be printed, so it
// needs far more pixels than a screen capture would.
await S('Emulation.setDeviceMetricsOverride', {
  width: 1180, height: 1480, deviceScaleFactor: 3, mobile: false,
});

await S('Page.navigate', { url: 'https://eon.gulfrabit.com/eon/' });
await sleep(9000);

const evaluate = async (expression) => {
  const r = await S('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
  return r.result && r.result.value;
};

// Ask through the panel's own controls, so what is captured is the real flow.
const VIEW = process.argv[4] || 'brief';

if (VIEW === 'ask') {
  await evaluate(`(async () => {
    const nav = [...document.querySelectorAll('a,button,li,[role=button]')]
      .find(b => /ask eon/i.test((b.textContent || '').trim()));
    if (nav) nav.click();
    await new Promise(r => setTimeout(r, 2500));
  })()`);
  const asked = await evaluate(`(async () => {
    const out = [];
    for (const q of ['Cash position', 'Who owes us money?', 'Any anomalies?']) {
      const b = [...document.querySelectorAll('button,a,[role=button],.chip')]
        .find(x => (x.textContent || '').trim().toLowerCase() === q.toLowerCase());
      if (b) { b.click(); out.push(q); await new Promise(r => setTimeout(r, 4000)); }
    }
    return out.join(' | ') || 'none';
  })()`);
  console.log('asked:', asked);
} else {
  const asked = await evaluate(`(async () => {
    const b = [...document.querySelectorAll('button,a,[role=button],li')]
      .find(x => (x.textContent || '').trim().toLowerCase() === 'brief');
    if (b) { b.click(); await new Promise(r => setTimeout(r, 3000)); }
    return 'brief';
  })()`);
  console.log('view:', asked);
}

await sleep(2500);
const info = await evaluate(`JSON.stringify({ title: document.title,
  text: (document.body.innerText || '').replace(/\s+/g,' ').slice(0, 200) })`);
console.log('page:', info);

const shot = await S('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false });
writeFileSync(join(OUT, `eon-panel-${VIEW}.png`), Buffer.from(shot.data, 'base64'));
console.log('saved eon-panel-' + VIEW + '.png');

browser.ws.close();
chrome.kill();
