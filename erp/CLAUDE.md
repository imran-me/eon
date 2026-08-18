# Epal ERP — working rules

## LOCKED: Wood Art Interiors must never leak into another company

Wood Art Interiors lives in `Modules/WoodArt/` (controllers, views, routes and
asset sources) and serves from `/{role}/woodart/*` as `companies.id = 6`. Its
browser assets are published to `public/woodart/`, which is what the views
actually `asset()` — so `Modules/WoodArt/resources/assets/` is the source and
`public/woodart/` is the copy. **Edit the module copy and re-publish; editing
only one of the two lets them drift silently.**

It is being built out inside a shell that eleven other companies share.
**Owner's standing instruction: no change made for Wood Art may affect any other
company's code, UI, features or behaviour — in any respect, 100%.**

That is not a preference to weigh against convenience. It is a hard constraint.
Two earlier attempts were rolled back for violating it, so treat a violation as a
failed change, not a tradeoff.

### What that forbids

1. **No global CSS.** Never ship a bare `*`, `html`, `body`, `:root`, or a naked
   element selector (`a`, `button`, `input`, `table`) from a Wood Art file. Every
   selector must carry a Wood Art prefix: `wa-`, `wap-`, `wa-nav-`, `wa-sub-`.
   Wood Art CSS must also be `<link>`ed/inlined **only** from Wood Art views —
   never added to `resources/css/app.css` or the Vite bundle, because those load
   on every company's page.
2. **No global JS.** Wood Art scripts are published to `public/woodart/`, loaded only
   by Wood Art views, and may only read or write DOM inside their own subtree or
   behind a Wood Art-specific selector. No monkey-patching shared helpers
   (`toggleSubmenu`, the sidebar scroll memory, the command palette), no
   listeners that act on non-Wood Art elements.
3. **Shared files are append-only, behind a condition.** `layout/app.blade.php`,
   `layout/sidebar.blade.php`, `layout/header.blade.php` and `routes/web.php` are
   shared. Wood Art may add to them, but every addition must sit behind a guard
   (`@if($onWoodArtPage)`, `$c->id == 6`, a `woodart/*` route prefix) so that for
   any other company the rendered output is byte-identical to what it was before.
   Never change an existing shared rule "while I'm in there".
   There is exactly ONE unguarded exception, approved by the owner — the page
   transition + preload blocks in `layout/app.blade.php`, described under
   "Smooth navigation everywhere" below. Do not add a second without asking.
4. **No shared dependency swaps.** Do not change how Alpine, Tailwind,
   FontAwesome, Bootstrap Icons, jQuery, DataTables or SweetAlert2 are loaded or
   versioned in order to make Wood Art nicer. Those changes reach all 576 views.
   One owner-approved exception: Alpine is now served from
   `public/vendor/alpine/alpine.min.js` instead of `//unpkg.com/alpinejs`. Same
   library, same build (3.15.12) the unversioned CDN URL resolved to — done for
   the whole ERP's benefit, not Wood Art's, because the cross-origin fetch
   delayed every page's sidebar. Re-download from
   `https://unpkg.com/alpinejs@3/dist/cdn.min.js` to update; do not point it
   back at a CDN.
5. **No edits to another company's views** — not to "clean up", not to fix an
   unrelated bug noticed in passing. Report it instead.
6. **Wood Art code in shared files must be unable to throw.** A display guard
   (`$c->id == 6`) is not a failure guard: the guarded branch still *executes*
   for superadmin, so a PHP exception inside it kills the whole page for the
   whole ERP. Incident 2026-08-10: `sidebar.blade.php` called
   `route('role.woodart.…')` unconditionally; on the live server those route
   names were not registered (deploy caches/autoload not refreshed), Laravel
   threw `RouteNotFoundException` while rendering the shared sidebar, and every
   superadmin page 500'd — the exact outage the isolation rule exists to
   prevent. Rules for any Wood Art code that lives in a shared file:
   - Never call `route()` on a Wood Art route name without
     `Route::has($name)` first — if the name is missing, degrade to the inert
     rendering (plain toggle, no link), never error.
   - Same discipline for anything else that can throw at render time:
     wrap DB lookups, `view()` on module views, config/file reads in an
     existence check or try/catch that falls back to "Wood Art absent".
   - Assume the deployed server can lag the repo (stale route cache, composer
     autoload not dumped, module half-deployed). Shared files must render
     every other company perfectly even when the Wood Art module is broken or
     entirely missing.
   If an error must happen, it may only happen on a `/{role}/woodart/*` page —
   never on any other company's page and never in the shared shell.

### The test before any Wood Art change lands

> Load a Travels / HR / Finance page. Is the HTML, CSS, JS and behaviour
> identical to before the change? If you cannot answer yes with certainty, the
> change is not ready.

`git diff` on shared files should show only guarded additions. A diff that
modifies an existing unguarded line in a shared file needs an explicit reason.

### The blast-radius question — answer it before writing any code

`erp.epal.com.bd` is a LIVE production site serving twelve companies. Before
every change, answer: **if this is wrong, who sees the breakage?** The only
acceptable answer is "someone on a `/{role}/woodart/*` page". If the honest
answer includes superadmin, another company, or the shared shell, the change is
not allowed in that form — redesign it until a failure is contained to Wood Art.

A change is not "safe because it is correct". It must be safe **while wrong** —
because deploys lag, caches go stale, and modules arrive half-copied. Design for
the broken state, not the happy path.

## LOCKED: server steps must be stated up front, and block everything after them

The 2026-08-10 outage was not caused by bad code alone. The code was complete and
correct in the repo; it took the site down because a required **server step was
never named** — `composer dump-autoload` after `composer.json` gained the
`Modules\` PSR-4 namespace. Laravel drops a provider it cannot autoload
*silently* (`RegisterProviders::mergeAdditionalProviders()` does
`if (! class_exists($provider)) unset(...)`), so nothing warns you: the module's
routes simply never register, and the first `route()` call on one throws.

**Standing instruction from the owner (2026-08-11):**

1. If a change needs ANY terminal/server action before it is safe to go live —
   `composer dump-autoload`, a migration, `php artisan optimize`, a
   `vendor:publish`, a permissions or `.env` change — say so **explicitly and up
   front, in its own clearly marked block**, not buried in a summary.
2. **Do not proceed to any further work until the owner confirms that step is
   done.** No starting the next module, no "meanwhile I'll also…". Stop and
   wait. An unstated server step is treated as a broken change.
3. Never report a change as finished or deployable while a server step is
   outstanding. Say plainly: *this is not live-ready until you run X.*

#### Deploy checklist for anything that adds routes, classes or namespaces

Run from the app root on the server, in this order:

```
composer dump-autoload -o          # REQUIRED whenever composer.json PSR-4 changed
php artisan optimize:clear
php artisan optimize
php artisan route:list --name=role.woodart     # must list the mounted modules
```

The `route:list` line is the proof step, not a formality — it is the check that
would have caught the outage before users did. If it prints
"doesn't have any routes matching", the module is NOT loaded: the sidebar's
`Route::has()` guard will correctly hide the links, but Wood Art pages will 404
until the autoloader is regenerated.

Assets: after editing anything in `Modules/WoodArt/resources/assets/`, run
`php artisan vendor:publish --tag=woodart-assets --force` — the browser serves
`public/woodart/`, not the module copy.

## LOCKED: navigation inside Wood Art must not reload or blink

Moving between Wood Art sections must not flash, blank out, or re-render the
shell. This is delivered by `Modules/WoodArt/resources/assets/nav/woodart-nav.js`
(published to `public/woodart/nav/`), which swaps only the `[data-wa-view]`
region and is loaded by Wood Art views alone.

Consequences for anyone adding a Wood Art screen:

- Put page content inside `[data-wa-view]`. It is replaced wholesale on
  navigation.
- **Do not put `<script>` inside `[data-wa-view]`.** Scripts go outside it, next
  to the scene/nav includes, so they load once and survive navigation. A script
  inside the swapped region will not re-run and must not be relied on.
- Anything that must persist across sections (the ambient scene, its scroll
  state) lives outside `[data-wa-view]`.
- New Wood Art routes stay under the `woodart/` prefix — that prefix is what the
  nav script matches on. A Wood Art route outside it silently falls back to a
  full page load.

## Smooth navigation everywhere (owner-approved, 2026-08-10)

The owner's requirement is that moving between menus never blinks or reloads —
in **every** company, not just Wood Art. It is delivered in two layers, and they
are deliberately different in strength:

1. **Wood Art only — true no-reload.** `woodart-nav.js` swaps `[data-wa-view]`
   and never touches the document. Only possible there because Wood Art's pages
   are new and carry no legacy page scripts.
2. **Every company — no white flash.** Two unguarded blocks in
   `layout/app.blade.php`: `@view-transition { navigation: auto; }` plus
   `view-transition-name` on `#sidebar` / the header, and a
   `<script type="speculationrules">` that prefetches links on hover.

Layer 2 is safe precisely because it changes nothing about how pages work:
navigation is still a full document load, so every page's scripts run exactly as
before. That is why it could be applied globally when a real SPA retrofit could
not — the ERP has ~184 views with inline `onclick=`, ~116 with DataTables/jQuery
and ~82 declaring top-level `const`/`let`, all of which break when page scripts
are re-executed in a live document. **Do not "upgrade" layer 2 to a client-side
router across the whole ERP without the owner explicitly accepting that risk.**

Two constraints on layer 2:

- `prefetch`, never `prerender`. Prefetch downloads HTML without executing its
  JavaScript. Prerender would run the page early — opening a second Pusher/Echo
  socket and firing anything the page does on load.
- Export/download routes are excluded; a hover must not make the server build a
  PDF or Excel file. Add `data-no-prefetch` to any other link that must not be
  fetched early.

## Reference build

The Wood Art screens are transcribed from the owner's separate static SPA at
`H:\Imran\Modular ERP\modularerp-main (2)\modularerp-main` (live:
https://dev.epal.com.bd/#/woodart/dashboard). `platform/core/config.js` →
`WOODART_MODULES` is the authoritative menu registry (19 modules);
`companies/woodart/module.json` mirrors it but is out of date. Match the
reference by transcribing its design tokens, not by embedding its files — its
`base.css` is full of global selectors and would violate rule 1 on contact.
