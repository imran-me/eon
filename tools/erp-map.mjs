#!/usr/bin/env node
/* ============================================================
   EON · erp-map — teach EON the ERP it lives in.

   Reads the ERP's own source and produces one file EON can think
   with: every page and its address, the menu the way the boss sees
   it, what each screen can do, which table holds what, and how the
   ledger and payroll rules work.

     node tools/erp-map.mjs                      (routes from Laravel if present)
     node tools/erp-map.mjs --routes routes.json (a route:list --json dump)

   Sources, in order of authority:
     1. php artisan route:list --json   — every route, name and action
     2. resources/views/layout/sidebar.blade.php — the human menu tree
     3. app/Models/*.php                — table + relationships per model
     4. app/Http/Controllers/**         — what each screen can do
     5. the production schema dump      — real tables and columns

   Output: ai-companion/eon-brain/domains/erp/erp-map.json
   (read by the browser plug-in and by the PHP brain alike).
   ============================================================ */
import { readFileSync, writeFileSync, existsSync, readdirSync, statSync } from 'node:fs';
import { join, dirname, basename, relative } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
const REPO = join(HERE, '..');
const ERP = join(REPO, 'erp');
const arg = (k, d) => { const i = process.argv.indexOf(k); return i > 0 ? process.argv[i + 1] : d; };

const read = (p) => { try { return readFileSync(p, 'utf8'); } catch { return ''; } };
const walk = (dir, out = []) => {
  let entries = [];
  try { entries = readdirSync(dir); } catch { return out; }
  for (const e of entries) {
    const p = join(dir, e);
    let st; try { st = statSync(p); } catch { continue; }
    if (st.isDirectory()) walk(p, out); else out.push(p);
  }
  return out;
};

/* ---------------- 1. routes ---------------- */
function loadRoutes() {
  const file = arg('--routes', join(HERE, '..', '..', 'routes.json'));
  for (const cand of [arg('--routes', null), file, join(ERP, 'routes-list.json')].filter(Boolean)) {
    if (existsSync(cand)) {
      try {
        const raw = readFileSync(cand, 'utf8').replace(/^\uFEFF/, '');
        const list = JSON.parse(raw);
        if (Array.isArray(list) && list.length) return list;
      } catch { /* try the next candidate */ }
    }
  }
  return [];
}

const HTTP_VERBS = /^(GET|HEAD|POST|PUT|PATCH|DELETE)/;
function normaliseRoutes(list) {
  const out = [];
  for (const r of list) {
    const uri = '/' + String(r.uri || '').replace(/^\/+/, '');
    const method = String(r.method || '').split('|').filter((m) => HTTP_VERBS.test(m));
    const action = String(r.action || '');
    if (action === 'Closure' && !r.name) continue;
    const [ctrl, fn] = action.split('@');
    out.push({
      uri,
      methods: method,
      name: r.name || null,
      controller: ctrl ? ctrl.split('\\').pop() : null,
      action: fn || null,
      api: uri.startsWith('/api'),
      params: (uri.match(/\{[^}]+\}/g) || []).map((p) => p.replace(/[{}?]/g, '')),
      auth: /auth|role|permission/i.test(String(r.middleware || '')),
    });
  }
  return out;
}

/* ---------------- 2. the menu, as the boss sees it ----------------
   The ERP writes its navigation three ways, and all three are read here:
     a) <a href="{{ route('role.x.index', …) }}">  Label  </a>   — the sidebar links
     b) a declarative PHP array  ['payroll', 'Payroll', 'cash-coin', [[…sub…]]]
        — the per-company menu
     c) in-page tab bars (layout/*-tabs.blade.php) — the buttons across the top
        of payroll, expenses, banks, financing
   Section captions (FINANCE & HR, OPERATIONS…) and submenu groups are tracked
   so EON can say "HRM → Payroll", the way the boss reads it. */
const stripTags = (html) => html
  .replace(/<[^>]*>/g, ' ')
  .replace(/\{\{[\s\S]*?\}\}/g, ' ')
  .replace(/&[a-z]+;/gi, ' ')
  .replace(/\s+/g, ' ')
  .trim();

function menuFromBlade(file, out) {
  const src = read(file);
  if (!src) return;
  const where = basename(file, '.blade.php');
  const isTabs = /-tabs$/.test(where);

  // section captions and submenu group buttons, in document order
  const marks = [];
  const capRe = /(?:^|\n)[^\n<]*<[^>]*>\s*(COMPANIES|BUSINESS|FINANCE\s*&(?:amp;)?\s*HR|OPERATIONS|COMMUNICATIONS|SETTINGS|REPORTS|MARKETING|INVENTORY)\s*</gi;
  let m;
  while ((m = capRe.exec(src))) marks.push({ at: m.index, kind: 'section', text: m[1].replace(/&amp;/g, '&').replace(/\s+/g, ' ').trim() });
  const grpRe = /<button[^>]*toggleSubmenu\('([^']+)'[\s\S]{0,400}?<span[^>]*>([^<]{2,40})<\/span>/g;
  while ((m = grpRe.exec(src))) marks.push({ at: m.index, kind: 'group', text: m[2].trim() });
  marks.sort((a, b) => a.at - b.at);
  const contextAt = (pos) => {
    let section = null, group = null;
    for (const k of marks) { if (k.at > pos) break; if (k.kind === 'section') { section = k.text; group = null; } else group = k.text; }
    return { section, group };
  };

  // (a) every anchor, with its visible text as the label
  // attributes carry Blade with '>' inside ({{ route('x', ['role' => $role]) }}),
  // so a bare [^>]* cuts the tag in half and the link is lost — allow whole {{ … }} runs
  const aRe = /<a\b((?:\{\{[\s\S]*?\}\}|[^>])*)>([\s\S]*?)<\/a>/g;
  while ((m = aRe.exec(src))) {
    const attrs = m[1];
    const label = stripTags(m[2]);
    if (!label || label.length < 2 || label.length > 44) continue;
    const rt = attrs.match(/route\('([a-z0-9_.\-]+)'/i);
    const href = attrs.match(/href="([^"{}]+)"/);
    if (!rt && !href) continue;
    const { section, group } = contextAt(m.index);
    out.push({
      label,
      route: rt ? rt[1] : null,
      href: rt ? null : href[1],
      section: isTabs ? (group || section) : section,
      group: isTabs ? where.replace(/-tabs$/, '') : group,
      kind: isTabs ? 'tab' : 'menu',
      icon: (attrs.match(/\b(bi-[a-z0-9-]+|fa-[a-z0-9-]+)/) || [])[1] || (m[2].match(/\b(bi-[a-z0-9-]+|fa-[a-z0-9-]+)/) || [])[1] || null,
      source: where,
    });
  }

  // (b) the declarative company menu: ['slug', 'Label', 'icon', [ ['sub','Sub'], … ]]
  const decRe = /\[\s*'([a-z0-9_-]+)'\s*,\s*'([^']{2,40})'\s*,\s*'([a-z0-9-]*)'\s*,\s*\[([\s\S]{0,900}?)\]\s*\]/g;
  while ((m = decRe.exec(src))) {
    const { section, group } = contextAt(m.index);
    out.push({ label: m[2], route: null, href: null, slug: m[1], section: section || 'COMPANIES', group, kind: 'company-menu', icon: m[3] || null, source: where });
    const subRe = /\[\s*'([a-z0-9_-]+)'\s*,\s*'([^']{2,40})'\s*\]/g;
    let s;
    while ((s = subRe.exec(m[4]))) {
      out.push({ label: s[2], route: null, href: null, slug: s[1], section: section || 'COMPANIES', group: m[2], kind: 'company-menu', icon: null, source: where });
    }
  }
}

function menuTree() {
  const out = [];
  const dir = join(ERP, 'resources/views/layout');
  for (const f of walk(dir).filter((p) => p.endsWith('.blade.php'))) menuFromBlade(f, out);
  // partials elsewhere that also carry navigation
  for (const extra of ['resources/views/layouts/app.blade.php', 'resources/views/components/sidebar.blade.php']) {
    const p = join(ERP, extra);
    if (existsSync(p)) menuFromBlade(p, out);
  }
  const seen = new Set();
  return out.filter((it) => {
    if (/^(×|x|close|toggle|back|next|previous)$/i.test(it.label)) return false;
    const k = it.label.toLowerCase() + '|' + (it.route || it.href || it.slug || '');
    if (seen.has(k)) return false;
    seen.add(k);
    return true;
  });
}

/* ---------------- 3. models → tables ---------------- */
function models() {
  const dir = join(ERP, 'app/Models');
  const out = [];
  for (const f of walk(dir).filter((p) => p.endsWith('.php'))) {
    const src = read(f);
    const name = basename(f, '.php');
    const table = (src.match(/protected\s+\$table\s*=\s*'([^']+)'/) || [])[1]
      || name.replace(/([a-z0-9])([A-Z])/g, '$1_$2').toLowerCase().replace(/y$/, 'ie') + 's';
    const fillable = (src.match(/protected\s+\$fillable\s*=\s*\[([\s\S]*?)\]/) || [])[1];
    const rels = [...src.matchAll(/public\s+function\s+(\w+)\s*\(\s*\)[\s\S]{0,120}?\$this->(hasMany|belongsTo|hasOne|belongsToMany|morphMany|hasManyThrough)\s*\(\s*([A-Za-z_]+)::class/g)]
      .map((m) => ({ name: m[1], kind: m[2], model: m[3] }));
    out.push({
      model: name,
      table,
      fields: fillable ? [...fillable.matchAll(/'([^']+)'/g)].map((m) => m[1]) : [],
      relations: rels,
    });
  }
  return out;
}

/* ---------------- 4. what each screen can do ---------------- */
function controllers() {
  const dir = join(ERP, 'app/Http/Controllers');
  const out = [];
  for (const f of walk(dir).filter((p) => p.endsWith('.php'))) {
    const src = read(f);
    const name = basename(f, '.php');
    const methods = [...src.matchAll(/public\s+function\s+(\w+)\s*\(/g)].map((m) => m[1]).filter((m) => m !== '__construct');
    if (!methods.length) continue;
    out.push({ controller: name, path: relative(ERP, f).replace(/\\/g, '/'), actions: methods });
  }
  return out;
}

/* ---------------- 5. the real schema, from the production dump ---------------- */
function schema(dumpPath) {
  if (!dumpPath || !existsSync(dumpPath)) return [];
  const sql = readFileSync(dumpPath, 'utf8');
  const out = [];
  const re = /CREATE TABLE `([a-z0-9_]+)` \(([\s\S]*?)\n\) ENGINE/g;
  let m;
  while ((m = re.exec(sql))) {
    const cols = [...m[2].matchAll(/^\s*`([a-z0-9_]+)`\s+([a-z]+(?:\([^)]*\))?)/gim)].map((c) => ({ name: c[1], type: c[2] }));
    out.push({ table: m[1], columns: cols });
  }
  return out;
}

/* ---------------- assemble ---------------- */
const routes = normaliseRoutes(loadRoutes());
const menu = menuTree();
const mods = models();
const ctrls = controllers();
const tables = schema(arg('--dump', 'C:/Users/USER/Downloads/ERP Database.sql'));

/* Nearly every ERP screen lives under a {role} segment — /super-admin/payroll,
   /accountant/journals. That is a scope, not a record: EON fills it from the role
   of whoever is signed in (the URL it is running on already carries it). So a
   "page" is a route whose only parameter is {role}. */
const SCOPE = new Set(['role']);
const recordParams = (r) => r.params.filter((p) => !SCOPE.has(p.replace(/\?$/, '')));

const pages = routes
  .filter((r) => r.methods.includes('GET') && !r.api && r.name && recordParams(r).length === 0)
  .map((r) => ({
    uri: r.uri,
    name: r.name,
    role_prefixed: r.params.some((p) => SCOPE.has(p)),
    controller: r.controller,
    action: r.action,
  }));

// a screen for one record: {role} plus exactly one id
const details = routes
  .filter((r) => r.methods.includes('GET') && !r.api && r.name && recordParams(r).length === 1)
  .map((r) => ({
    uri: r.uri,
    name: r.name,
    param: recordParams(r)[0],
    role_prefixed: r.params.some((p) => SCOPE.has(p)),
    controller: r.controller,
    action: r.action,
  }));

const map = {
  meta: {
    generated_at: new Date().toISOString().slice(0, 19) + 'Z',
    source: 'epal_erp_soft',
    routes: routes.length,
    pages: pages.length,
    details: details.length,
    menu_items: menu.length,
    models: mods.length,
    controllers: ctrls.length,
    tables: tables.length,
  },
  menu,
  pages,
  details,
  routes: routes.filter((r) => !r.api),
  models: mods,
  controllers: ctrls,
  tables,
};

const out = join(REPO, 'ai-companion/eon-brain/domains/erp/erp-map.json');
writeFileSync(out, JSON.stringify(map, null, 0));
console.log('erp-map.json written:', JSON.stringify(map.meta, null, 2));
console.log('size:', (statSync(out).size / 1024).toFixed(0) + ' KB');
