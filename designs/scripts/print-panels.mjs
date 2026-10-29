/* Print the stall panels to exact-size PDF with headless Chrome over CDP.
 *
 * paperWidth/paperHeight are passed explicitly rather than leaning on
 * preferCSSPageSize: --print-to-pdf silently falls back to Letter and that
 * failure looks like a design bug rather than a flag bug.
 *
 * Usage: node print-panels.mjs <chrome.exe> <baseUrl> <outDir>
 */
import { spawn } from 'node:child_process';
import { writeFileSync, mkdirSync } from 'node:fs';
import { join } from 'node:path';

const [CHROME, BASE, OUT] = process.argv.slice(2);
mkdirSync(OUT, { recursive: true });

const PANELS = [
  { id: 'branding', name: 'EON-branding-area-2.5x8.33ft', trim: [30, 99.96] },
  { id: 'side', name: 'EON-side-panel-2x8.3ft', trim: [24, 99.6] },
];
const BLEED = 1;
const PORT = 9335;

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

const chrome = spawn(CHROME, [
  '--headless=new', `--remote-debugging-port=${PORT}`,
  '--use-angle=swiftshader', '--enable-unsafe-swiftshader',
  '--hide-scrollbars', '--force-device-scale-factor=1',
  '--no-first-run', '--no-default-browser-check',
  '--user-data-dir=' + join(OUT, '.chrome-profile'),
  'about:blank',
], { stdio: 'ignore' });

async function version() {
  for (let i = 0; i < 60; i++) {
    try {
      const r = await fetch(`http://127.0.0.1:${PORT}/json/version`);
      if (r.ok) return await r.json();
    } catch {}
    await sleep(500);
  }
  throw new Error('Chrome did not expose a debugging port');
}

class CDP {
  constructor(ws) { this.ws = ws; this.id = 0; this.waits = new Map(); this.events = []; }
  static async open(url) {
    const ws = new WebSocket(url);
    await new Promise((res, rej) => { ws.onopen = res; ws.onerror = rej; });
    const c = new CDP(ws);
    ws.onmessage = (m) => {
      const msg = JSON.parse(m.data);
      if (msg.id && c.waits.has(msg.id)) {
        const { res, rej } = c.waits.get(msg.id); c.waits.delete(msg.id);
        msg.error ? rej(new Error(JSON.stringify(msg.error))) : res(msg.result);
      } else if (msg.method) c.events.push(msg.method);
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

const v = await version();
const browser = await CDP.open(v.webSocketDebuggerUrl);

let failed = 0;
for (const p of PANELS) {
  const { targetId } = await browser.send('Target.createTarget', { url: 'about:blank' });
  const { sessionId } = await browser.send('Target.attachToTarget', { targetId, flatten: true });

  await browser.send('Page.enable', {}, sessionId);
  const url = `${BASE}/designs/scripts/panel.html?panel=${p.id}`;
  await browser.send('Page.navigate', { url }, sessionId);

  // Wait for the script to finish laying the panel out and for the avatar
  // PNG to decode; a PDF printed before decode shows an empty box.
  let ready = false;
  for (let i = 0; i < 80; i++) {
    await sleep(300);
    const r = await browser.send('Runtime.evaluate', {
      expression: `(()=>{const i=[...document.images];
        return document.title==='PANEL-READY' && i.length>0 && i.every(x=>x.complete && x.naturalWidth>0);})()`,
      returnByValue: true,
    }, sessionId);
    if (r.result && r.result.value) { ready = true; break; }
  }
  if (!ready) { console.error(`FAIL  ${p.name}: assets never finished loading`); failed++; continue; }

  const [w, h] = p.trim;
  const { data } = await browser.send('Page.printToPDF', {
    paperWidth: w + 2 * BLEED,
    paperHeight: h + 2 * BLEED,
    marginTop: 0, marginBottom: 0, marginLeft: 0, marginRight: 0,
    printBackground: true,
    preferCSSPageSize: false,
    scale: 1,
  }, sessionId);

  const file = join(OUT, p.name + '.pdf');
  writeFileSync(file, Buffer.from(data, 'base64'));
  console.log(`ok    ${p.name}.pdf  trim ${w} x ${h} in  (+${BLEED}in bleed)`);
  await browser.send('Target.closeTarget', { targetId });
}

browser.ws.close();
chrome.kill();
process.exit(failed ? 1 : 0);
