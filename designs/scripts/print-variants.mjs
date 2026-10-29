/* Print the five back-wall variants to exact size.
 * Usage: node print-variants.mjs <chrome.exe> <baseUrl> <outDir> [guide]
 */
import { spawn } from 'node:child_process';
import { writeFileSync, mkdirSync } from 'node:fs';
import { join } from 'node:path';

const [CHROME, BASE, OUT, GUIDE] = process.argv.slice(2);
mkdirSync(OUT, { recursive: true });
const TRIM = [70.8, 99.96], BLEED = 1, PORT = 9347;
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

const chrome = spawn(CHROME, [
  '--headless=new', `--remote-debugging-port=${PORT}`,
  '--use-angle=swiftshader', '--enable-unsafe-swiftshader',
  '--hide-scrollbars', '--no-first-run', '--no-default-browser-check',
  '--user-data-dir=' + join(OUT, '.chrome-profile'), 'about:blank',
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

for (let i = 0; i < 60; i++) {
  try { const r = await fetch(`http://127.0.0.1:${PORT}/json/version`); if (r.ok) break; } catch {}
  await sleep(500);
}
const v = await (await fetch(`http://127.0.0.1:${PORT}/json/version`)).json();
const browser = await CDP.open(v.webSocketDebuggerUrl);

let failed = 0;
for (const n of [1, 2, 3, 4, 5]) {
  const { targetId } = await browser.send('Target.createTarget', { url: 'about:blank' });
  const { sessionId } = await browser.send('Target.attachToTarget', { targetId, flatten: true });
  await browser.send('Page.enable', {}, sessionId);
  const url = `${BASE}/designs/scripts/panel-variants.html?v=${n}${GUIDE ? '&guide=1' : ''}`;
  await browser.send('Page.navigate', { url }, sessionId);

  let ready = false;
  for (let i = 0; i < 90; i++) {
    await sleep(300);
    const r = await browser.send('Runtime.evaluate', {
      expression: `(()=>{const i=[...document.images];
        return document.title==='PANEL-READY' && i.length>0 && i.every(x=>x.complete&&x.naturalWidth>0);})()`,
      returnByValue: true,
    }, sessionId);
    if (r.result && r.result.value) { ready = true; break; }
  }
  if (!ready) { console.error(`FAIL  variant ${n}: assets never loaded`); failed++; continue; }

  const { data } = await browser.send('Page.printToPDF', {
    paperWidth: TRIM[0] + 2 * BLEED, paperHeight: TRIM[1] + 2 * BLEED,
    marginTop: 0, marginBottom: 0, marginLeft: 0, marginRight: 0,
    printBackground: true, preferCSSPageSize: false, scale: 1,
  }, sessionId);

  const name = `EON-backwall-v${n}${GUIDE ? '-GUIDE' : ''}.pdf`;
  writeFileSync(join(OUT, name), Buffer.from(data, 'base64'));
  console.log('ok   ' + name);
  await browser.send('Target.closeTarget', { targetId });
}

browser.ws.close();
chrome.kill();
process.exit(failed ? 1 : 0);
