// Generates server/storage/data/demo-dataset.json from the JS demo generator so the
// PHP server and the browser see the same synthetic ERP. Run with Node ≥ 18:
//   node tools/make-demo-dataset.mjs
import { writeFileSync, mkdirSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
const here = dirname(fileURLToPath(import.meta.url));
const { generateDemo } = await import(new URL('../ai-companion/eon-brain/domains/erp/demo-data.js', import.meta.url).href);
const D = generateDemo();
const out = join(here, '..', 'server', 'storage', 'data', 'demo-dataset.json');
mkdirSync(dirname(out), { recursive: true });
writeFileSync(out, JSON.stringify(D));
console.log('wrote', out, JSON.stringify(D).length, 'bytes; today', D.meta.today);
