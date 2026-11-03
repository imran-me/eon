# Epal ERP — working rules

**Read [context.md](context.md) first** — the owner's design patterns, how
requests are phrased, and the business rules every change must follow.
Add to it whenever the owner corrects a direction or approves one —
**inside YOUR OWN person block at the end of context.md** (two people edit this
file; the blocks are marked RAFI / IMRAN — use the one for `git config user.name`,
and never touch the other's), so git never conflicts.

## Every new feature: run the `erp-feature-standard` skill

[.claude/skills/erp-feature-standard/SKILL.md](.claude/skills/erp-feature-standard/SKILL.md) —
before building and again before reporting done. It holds the map of every screen that reads a new
money entry (passenger row, flight totals, CF Accounts, reports, receipts, Profit & Funds, Company
Ledger, Party Statement Details…). A feature whose own dialog works but a reader is missing is NOT
done. Add any reader you find that the map lacks.

## Local machine: never cache config from Git Bash

On this Windows machine, run `php artisan config:cache` / `optimize` from **PowerShell only**. Git Bash
rewrites `/` into `C:/Program Files/Git/`, the cached `session.path` breaks the login cookie, and the
owner gets ERR_TOO_MANY_REDIRECTS or links that bounce to the dashboard (happened 2026-10-25 and
three times on 2026-11-01). Check: `session.path` in `bootstrap/cache/config.php` must be `/`.

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

## Frontend design workflow (new screens and Wood Art only)

Tooling: skills `impeccable`, `design-taste-frontend`, `web-design-guidelines`
(restore with `npx skills experimental_install`), the Playwright MCP in
`.mcp.json`, and the tokens in [DESIGN.md](DESIGN.md).

1. Read `DESIGN.md` before building or changing UI, and use its tokens. It
   applies ONLY to new screens and files under `Modules/WoodArt/`. **Never
   restyle another company's existing view** — on those, run audits as
   report-only (`web-design-guidelines`, `/impeccable audit`); no
   `/impeccable polish`. The Wood Art LOCKED rules above still win.
2. Anti-slop: no purple-to-blue gradient headlines, no emoji tiles above
   headings, no global selectors; Wood Art CSS stays `wa-` / `wap-` prefixed.
3. Accessibility: hit targets >= 24px (44px touch), never disable zoom, every
   async button shows a loading state.
4. After UI work, open the LOCAL URL with Playwright MCP (never the live
   site), click through, screenshot at 1365x718, fix overflow/overlap, then
   finish. If a design change needs a server step, state it up front.

## Reference build

The Wood Art screens are transcribed from the owner's separate static SPA at
`H:\Imran\Modular ERP\modularerp-main (2)\modularerp-main` (live:
https://dev.epal.com.bd/#/woodart/dashboard). `platform/core/config.js` →
`WOODART_MODULES` is the authoritative menu registry (19 modules);
`companies/woodart/module.json` mirrors it but is out of date. Match the
reference by transcribing its design tokens, not by embedding its files — its
`base.css` is full of global selectors and would violate rule 1 on contact.

## EON — the evaluation intelligence (`Modules/EON/`)

EON reads the task module and reports on how people are working: score, trend,
drawdown against their own peak, month-on-month movement, the standing backlog,
and the human record of ratings, red flags and locks that Employee Task
Oversight already keeps. It serves from `/{role}/eon/*`, **super admin only**,
checked in `EonController::guard()` at every entry point — a route is a door,
the lock belongs on the room.

It is a **pure addition**. The host application touches EON in exactly two
places, and both are guarded:

1. `bootstrap/providers.php` — one provider line.
2. `layout/sidebar.blade.php` — one `Route::has('role.eon.index')`-guarded link,
   plus `role.eon.*` added to the Task submenu's existing `routeIs()` lists
   (a disjunct that is false on every non-EON route, so other companies' output
   is byte-identical).

Delete those and the feature detaches with no trace. EON adds nothing to
`routes/web.php`, no shared asset, no global CSS or JS, and writes only
`eon_reviews` and `eon_snapshots`. Every other table it touches is read-only.

### The rules that must not regress

1. **Machine and human are never merged.** EON's score is an observation; the
   authority's stars and red flags are a judgement. They sit in separate blocks
   on every page and in separate columns in the database. The interesting case
   is when they disagree — averaging them destroys the only signal that would
   ever show the engine is wrong. `eon_reviews.machine_*` records what EON was
   saying at the moment a human decided otherwise.
2. **Unknown is not zero.** A pillar with no evidence scores `null` and the
   remaining weights re-normalise. Quality with no ratings on file reads "not
   known", never 100 and never 0 — a shipped bug where it read 100 handed a
   fifth of everybody's score away for the absence of evidence.
3. **Every score carries its confidence**, everywhere it appears — row, dossier,
   print sheet, snapshot. A number that does not say how much it knows is the
   one that gets misused.
4. **Scored windows roll, reported windows are calendar.** The score uses the
   last 30 days so it means the same thing on the 1st as on the 28th. "This
   month against last" is month-to-date against the *same days* of last month —
   never a part-month against a whole one.
5. **On time means the due DAY, not the due timestamp.** Due dates here carry a
   time of day that is rarely a hard deadline; judging by the timestamp marks
   ~80% of all work late and the number stops distinguishing anybody. The
   stricter reading is reported separately as `late_by_hour`.
6. **Cards parked in DONE without `completed_at` are counted as neither
   completed nor overdue.** There are ~394 of them and they were 365 of the
   company's 417 "past due" items. Counting them as overdue marks the workforce
   down for a recording gap; counting them as done adopts a definition of
   "complete" no other screen uses. They are reported as their own figure
   (`unmarked_done`) and listed on the dossier so they can be cleared.
7. **Analyzers never query.** All data arrives through `TaskMetrics`, in bulk,
   grouped by user — the org page scores ~40 people in around 18 queries. Each
   analyzer is isolated in its own try/catch, so one that throws loses its own
   dimension and the page still renders.
8. **`Modules\EON\Intelligence\People` is a deliberate twin** of
   `EmployeeTaskOversightController::boardablePeople()`. That method is private
   and EON may not edit a working controller, so the query is duplicated —
   **change one and change the other**, or the two screens list different people.
9. **Styles are inlined via `eon::partials.styles`, not published.** That is why
   EON needs no `vendor:publish` step to look right. Every selector is `eon-`
   prefixed. Blade's `@include` inherits the caller's scope, so the chart
   partials type-check their own parameters — a loop variable named `$w`
   silently became the sparkline's width once already.

### Adding to it

A new dimension of the evaluation is one class implementing
`Intelligence\Analyzers\Analyzer` plus one line in `EonEngine::analyzers()`.
`RiskAnalyzer` stays last: it reads what the others produced. Nothing else in
EON, and nothing at all in the ERP, has to change.

### Deploy

`Modules\` is already in composer.json's PSR-4 map, so no `dump-autoload` is
needed for the namespace itself — but `bootstrap/providers.php` changed, so:

```
php artisan optimize:clear
php artisan optimize
php artisan migrate --path=Modules/EON/Database/Migrations   # eon_ tables only
php artisan route:list --name=role.eon                        # must list 6 routes
```

The `--path` on the migration is not optional: a bare `migrate` would also run
whatever other pending migrations are sitting in `database/migrations`, several
of which alter shared tables. Until the migration runs, EON works in full and
the written-review panel says it is not installed yet.

## EON AI — the external assistant's connection point (`Modules/EonAi/`)

Not the same thing as `Modules/EON` above. EON AI is the owner's separate
assistant: its own GitHub repository, its own Hostinger server and database.
**None of its code or "brain" comes into this repository** — this module is only
the ERP side of the connection. Serves from `/{role}/eon-ai/*`, super admin only
(`EonAiController::guard()` on every action). Owner's instruction: everything
EON happens on `/super-admin/eon-ai`; the live ERP must not be hampered at all.

Host touch points, both guarded, exactly like `Modules/EON`: the provider line
in `bootstrap/providers.php`, and one `Route::has('role.eon-ai.connection')`
"EON Connection" link in the Settings submenu (+ `role.eon-ai.*` in that
group's `routeIs()` lists). Proven 2026-09-27: rendered HTML of other pages is
byte-identical except that one link for super admin; employee pages identical.

What exists today (step 1): the connection page — EON server URL (public https
only; private/local addresses refused outside `local`/`testing`), a shared key
(generated or pasted, stored with `Crypt`, shown in full once), an on/off
switch, and a **signed two-way handshake** (`Support\Handshake`: ERP signs
HMAC-SHA256, EON must return a proof of the same key). It sends **no ERP data**.
Its only table is `eon_ai_settings`, read behind an existence check.

Rules for the next steps (agreed with the owner, not yet built):
1. **EON may never edit or delete anything.** A data gateway, when built, is
   read-only by four independent locks: no write endpoints, EON never sends SQL
   (the ERP builds queries from an allowlist), a read-only DB session, and
   secrets (password hashes, tokens, sessions, keys) never leave.
2. **EON must never slow the live site**: small pages, query timeouts, rate
   limits, incremental "changed since" sync, full copy off-peak.
3. **No page outside `/eon-ai` ever calls or waits on the EON server.** If EON
   is down, only `/eon-ai` says so.
4. "Go to a page on command" (later): EON may only *ask*; the eon-ai page checks
   the request against an allowlist and the user's own permissions.

Deploy (step 1): `composer dump-autoload -o`, `php artisan migrate
--path=Modules/EonAi/Database/Migrations`, `php artisan optimize:clear &&
php artisan optimize`, then `php artisan route:list --name=role.eon-ai` (must
list 5 routes).

## Contract Flight — what the business actually does (`company_id = 2`, Travels)

A contract flight is **not** a seat-block we resell. An **agent** brings us a
group of 3–5 passports and contracts us to get those people onto a plane and
through to arrival. We are paid per head, by the agent, for the *service* —
ticket plus whatever clearance, boarding and immigration handling each person
needs. So:

- The **flight is the batch**, not the product. It carries the aircraft, the
  route, the times and the officers. The agent on the flight is only the
  **default**: the real ops sheet has one aircraft carrying five agents'
  people, so the agent is stored and chosen **per passenger** (2026-09-21).
- The **passenger is the unit of work and the unit of money.** Everything that
  can differ between two people in the same group lives on the passenger row:
  category, cabin class, service mix, every cost, and the agent price.

That last point is the rule people keep breaking. One agent's group routinely
mixes cases — one man already holds a work permit so there is no BMET service
to sell him; the next needs it. One goes through immigration alone; the next
buys our officer. Anything modelled as a *flight-level* default gets silently
applied to people it is wrong for, and the batch's cost stops matching reality.
**If a field can differ per person, it belongs on the passenger.**

Served from `/{role}/contract-flights` (Manage Flights) and
`/{role}/flight-schedules` (the same module, scheduling view), with the
passenger pipeline board at `/{role}/contract-flight-board`.

### The flow we are building for

1. **Agent hands over passports.** Pick the default agent on the flight;
   each passenger card can be billed to a different agent.
2. **Per passenger:** choose the passport holder, their category and class,
   then either link a PNR that already exists in Ticketing or issue a new one
   **through Ticketing's own endpoint** — never a second ticket table.
3. **Per passenger:** tick the services we actually perform for that person
   and cost only what was ticked. Since 2026-09-21 the services are
   **Immigration Training, Boarding Training, BMET / Work Permit Consulting,
   Others** (the manpower card is retired; boarding is a tick like the rest,
   `boarding_training`, default on). The first three are delivered by a
   **responsible person** — an agent of type Trainer or Consultant (extra
   Spatie role beside `agent`, set on the Agent form) — whom **we pay**: the
   cost box is their fee, recorded in the card's "Responsible person &
   payment" box (`contract_flight_passenger_service_costs.vendor_id`) and
   posted to *their* ledger as a payable (`resolvePartyUserType()` → supplier).
   The agent is still charged the package (agent price); profit = agent
   price − (tickets + fees). `ContractFlightPassengerServiceCost::providerRoles()`
   says which kind of person each service accepts and the validator enforces it.
4. **Review, then confirm.** Nothing is written until Save.

Then the passenger moves through the pipeline (`contract_flight_stages`,
editable from the dashboard; `STAGES` on `ContractFlightPassenger` is the
fallback). Stages only ever move forward — `advanceStageTo()` never regresses
one. **Nothing is mandatory up front except the passport holder and the
agent they are billed to** —
not the route, not the flight number, not even the date, which only exists
once a ticket does (2026-09-21): a card saved with only a name waits at Passenger
Received, and `syncDerivedStage()` carries it forward as data arrives —
category → Information Entered, agent price → Service Defined, route on the
flight → Route Confirmed, every leg's PNR confirmed → Ticket Issued. Stages
after that are physical events and stay manual (tracker, board, edit modal).
It runs after every builder save, passenger edit, PNR link and flight edit. Exception flags
(cancelled, rescheduled, transit, deported) are an overlay, not a stage.

### Rules that must not regress

1. **Ticketing owns tickets.** The builder's "issue new ticket" posts to
   `TicketDirectSaleController`, so the PNR, the vendor liability and the
   journal entry appear on the Ticketing screen exactly as if typed there.
   Never write a parallel ticket record for contract flights.
2. **Ticket cost comes from the linked PNR and is never typed.** The field is
   readonly and recomputed from the links; a hand-typed ticket cost would
   silently disagree with Ticketing. A route can take more than one ticket
   (DAC → ECN = DAC-IST + IST-ECN): `tickets_required` on the passenger says
   how many, and "ticketed" (`isFullyTicketed()` / `fullyTicketedSql()`)
   means every leg has a confirmed PNR — never "any confirmed PNR". Since
   2026-09-21 **a route is direct, or has a VIA airport and is then two
   tickets**: DAC-IST-ECN = leg 1 DAC→IST + leg 2 IST→ECN, each leg on
   `ticket_route_legs` with **its own airline, vendor/portal and price**
   (Manage Tickets → Via box; `Ticket::legsArray()/legCount()/viaAirportId()`,
   guarded so a missing table reads as direct; the route's own airline /
   seller / price mirror leg 1, price = sum). Ticket Entry expands a via
   route into one ticket card per leg (same passenger, `leg_sequence` 1 / 2,
   one `order_group`); the contract flight card sets `tickets_required` from
   `legCount()` and its "Which ticket" picker issues each leg.
   `ContractFlightPassenger::ticketSlots()` matches PNRs to legs by sector,
   so "1 / 2 tickets issued" shows on the list row, card tag, ledger row and
   Review, and the tracker shows "Ticket 1 Issued · DAC → IST" / "Ticket 2
   Issued · IST → ECN" as separate steps. `syncPassengerTickets()` bumps to
   Ticket Issued only when `isFullyTicketed()`.
   **Each ticket has its own status** (owner, 2026-09-21): the single entry
   screen (`ticket_direct_sales/create`, "Add Entry" on Ticket Orders) has a
   Ticket status on every card — *Purchased* writes `ticket_purchases` and
   hits the ledger / stock / schedule exactly as before; *Booked* (hold with
   deadline, reminders, expiry — `TicketBooking`'s own logic, unchanged),
   *Pending*, *Draft*, *Cancelled* write a `ticket_bookings` row and touch no
   money. So "purchase leg 1, book leg 2" is just two cards. Nothing is
   mandatory at entry except the passport holder; only a Purchased row must
   carry route + PNR + seller + cost (`TicketDirectSaleController::store`,
   `entryStatus()` / `storeBookingRow()`). Confirming a booking (draft,
   pending, booked or expired) opens the entry with only that leg's card and
   links `confirmed_purchase_id` to the purchase of that leg. Ticket Orders
   lists one row per ticket, siblings of an order together ("Ticket 1 of 2 ·
   DAC → IST"). Not yet done: a hold does not appear on the contract-flight
   tracker — `ticketSlots()` reads purchases only.
3. **Flight-level totals are derived, never entered.**
   `ManageFlightController::applyPassengerDerivedTotals()` sums the passenger
   rows into the flight's revenue, cost and counts. The NOT NULL columns are
   seeded at insert only so the row can be created, then settled from the rows.
4. **Cancelled and deported-on-our-side void the agent charge; rescheduled,
   transit and deported-on-the-agent's-side do not.** The passenger still travelled on our arrangement in the latter two,
   so the agent still owes. Encoded as `AGENT_CHARGE_VOIDING_FLAGS` and applied
   by `ReconcilesContractFlightAgent`. Since 2026-09-28 a void is a REVERSAL:
   the package row keeps its amount and a credit row
   (`AGENT_REVERSAL_TYPE`, own journal) is added; charging him again adds a
   debit row. Rows voided the old way (package at 0, no reversal) are left
   alone. See "Nothing is deleted on a cancel" below.
5. **A cost box exists only while the service it pays for is switched on**, and
   clearing the service clears the figure — same rule client-side in
   `pbApplyCardRules()` and server-side in `syncPassengerRows()`. A charge must
   never outlive its service.
6. **An empty passenger list is not "delete everybody".**
   `syncPassengers()` acts only when the request actually carries a
   `passengers` (builder) or `passenger_ids` (legacy) key; with neither, it
   leaves the existing passengers untouched. A form that never rendered the
   builder must not be read as "this flight now has no passengers" and wipe a
   batch's pipeline state. (The view also posts a `passengers_present` hidden
   input, but nothing reads it today — the guard above is the real one. Don't
   rely on the marker without wiring it up first.)
7. **Rate presets are matched per passenger**, on airline + category + class +
   handling — only the airline belongs to the batch. A preset never overwrites
   a figure someone typed (`data-edited`).
8. **Reissue is recorded but excluded from travel cost** — it is an exception
   cost for reporting, not part of the margin the trip was sold on. Same rule
   in `getTotalTravelCostAttribute()` and in the builder's totals, so the
   screen and the database never disagree.

### One passenger off a flight, onto another (2026-09-26)

A whole-flight date/time change needs nothing special — the date lives on the
flight, everyone moves with it, the ledger is untouched (owner confirmed).
For ONE person who will not travel (owner's example: GF014 has 5, one is not
going): row action **Cancel from flight** → he is *off flight*
(`contract_flight_id` NULL, flag `OFF_FLIGHT_FLAG` = `pax_cancelled`, package
charge posted as zero) and waits under the **Cancelled** card. Later he goes
onto another flight (GF017) with a charge. `ContractFlightRebookController`
+ `RebooksContractFlightPassengers` (shared); history and charges in
`contract_flight_passenger_changes`.

1. **The old ticket stays, at its amount, with a note and a refund row**
   (superseded 2026-09-28 — it used to go to ৳0 and its postings were
   deleted). Cancel from flight has "Cancel his ticket too", on by default,
   with an airline-refund box per ticket; a Reissue cancels any ticket still
   live (full refund). Always through Ticketing's own code —
   `TicketPurchaseController::cancelFromContractFlight()` and `update()`'s
   cancel share `postCancellationReversal()`. Since 2026-09-30 (owner) a
   cancelled PNR is **unlinked** from the passenger (`cancelPassengerTickets()`
   detaches it); it stays in Ticket Orders with its note, and the change row's
   `previous_ticket_purchase_ids` is the history every reader uses. A cancelled PNR is neither cost nor ticket, and `ticketSlots()`
   skips it so it cannot hide the new PNR.
2. **Two ways back** (`MOVE_KINDS`): *reschedule* = same live ticket;
   *reissue* = new ticket (old live ones cancelled at 0). Two doors, one code
   path (`completeRebooking()`): **Re-assign** on the Cancelled row, or — what
   the owner described — **pick his passport on the new flight's builder**:
   `offFlightForHolder()` fills the card from his previous one and adds the
   Re-booking box (how / charge / who pays / note); the card posts
   `rebook_passenger_id` and `rebookFromRow()` moves THAT passenger onto the
   flight (no duplicate row). It refuses an id whose passport differs from
   the card's.
3. **Each move's charge is its own ledger row** — `LEDGER_TYPE`
   `contract_flight_passenger_change`, `invoice_id` = the CHANGE id (never the
   passenger id — that collides with the passenger-keyed types). Bearer agent
   → posted + journal; company → recorded only. Correctable from the edit
   modal's "Flight changes" panel; `removePassengers()` voids them. The
   Cancel dialog takes an optional **cancellation charge** (2026-09-27): the
   `cancelled` change row carries it, always billed to the agent. Party
   Statement details these rows, and the voided package row, with a red
   line (`'tone' => 'danger'` on a detail line; the query left-joins the
   flight because an off-flight passenger has none).
4. **The flag and the missing flight never disagree.** Only the rebook code
   sets/clears `pax_cancelled`; `update()` forces it on an off-flight
   passenger and refuses it on one with a flight; `moveFlight()` refuses
   off-flight passengers; the edit modal's dropdown hides it.
5. **A re-booking restarts the stage from `derivedStage()`** — the one
   sanctioned step backwards: the physical stages belonged to the old departure.
6. **Guarded for a lagging server**: `ContractFlightPassengerChange::available()`
   (migration ran AND all six routes registered) gates every button, the
   scripts that call `route()`, the builder's lookup URL and the Cancelled
   rows; Ticket Orders selects `cancel_note` only when the column exists.
   Verified: with the routes stripped every page renders 200 with none of it.

Deploy: `php artisan migrate --path=database/migrations/2026_09_26_000001_add_flight_changes_to_contract_flight_passengers.php`,
then `php artisan optimize:clear && php artisan optimize`, then
`php artisan route:list --name=contract-flight` (must include
`cancel-from-flight`, `reassign`, `reassign-flights`, `restore`,
`contract-flight-passenger-changes.charge`, `contract-flight-holders.off-flight`).

### Re-assign → Offload / Deported (owner, 2026-10-07)

Third option in the Re-assign dialog (passenger still on a flight only):
**Immigration offload** (flag `immigration_offload`), **Boarding offload**
(`boarding_denied`), **Deported**. `ContractFlightRebookController::offload()`.

- **Offload** lists every live ticket AND every paid service (training /
  consulting, `refundableServices()`): cost, refund (blank = not refunded,
  ticket kept), penalty = cost − refund, who bears it, our charge. Ticket
  refund = Ticketing's own cancel; service refund = provider row
  `contract_flight_service_refund` Cr + Dr 2111 / Cr 5150; penalty Dr 5171 /
  Cr 5110 or 5150. What next: re-book (follow-up `reassign()`), keep flagged,
  or close (`will_not_fly`).
- **Deported** refunds nothing (he flew). Deported from, then return ticket /
  deporting airport / our airport costs (`DEPORT_COSTS`), each with its own
  bearer and optional payee (vendor row `contract_flight_deport_cost` Dr),
  Dr 5171 / Cr 2111; plus our deport charge. Whose side = the package:
  ours → `deported_we_bear` (reversed).
- The agent gets **ONE line** for the event: everything he bears + our
  charges (Dr 1311 / Cr 4181 / Cr 4183 — 4183 = our profit); our share is a
  second `flagged` row (`splitLegPenalty`).

Guarded by `offloadAvailable()` — no migration. Deploy: `php artisan
optimize:clear && php artisan optimize`, `php artisan route:list
--name=offload` (must list 1).

### Re-assign → Suspend withdraw (owner, 2026-11-02)

Fourth card in Re-assign (on a flight, holding a live ticket): an airline / portal suspended his
ticket and another vendor lifted it. **Owner: copy the Offload UI, NOT its logic** — own controller
(`ContractFlightSuspendWithdrawController`), own table (`contract_flight_suspend_withdrawals`), own
script (`partials/suspend-withdraw-script`; reassign-script only calls the `window.cfsw*` hooks).
Vendor row `contract_flight_suspend_withdraw` Dr cost. We bear: Dr 5173 / Cr 2111 (+ our charge
alone to the agent). Agent bears: ONE agent row `contract_flight_suspend_withdraw_charge` = cost +
our charge, journal Dr 1311 / Cr 2111 / Cr 4183. He stays on his flight, ticket live, no flag.
`ContractFlightPassenger::suspendWithdrawTotals()` folds it in like baggage: `agent_total` (+ what the
agent is billed → Charge / Due), `total_travel_cost` (+ vendor cost), flight revenue (+ billed).
Party Statement `reasonsFor()` details both types (name · "Suspend withdraw — PNR (on CF-…)" · note);
the agent's remark carries no cost/charge breakdown (ONE figure). Not yet: Ticketing side, Trail/Undo.
Deploy: `php artisan migrate --path=database/migrations/2026_11_02_000001_create_contract_flight_suspend_withdrawals_table.php`,
`php artisan optimize:clear && php artisan optimize`, `php artisan route:list --name=suspend-withdraw` (must list 1).

### Nothing is deleted on a cancel (owner, 2026-09-28)

"No data should be deleted at all, upon cancellation — reversed, by addition
or minusing the amount." Every cancel ADDS rows; none is edited or removed.

- **Ticketing** (`postCancellationReversal()`): purchase rows, payments and
  journals stay. Added: vendor row `ticket_purchase_refund` Cr refund +
  journal `ticket_purchase_cancel` Dr AP / Cr 5110 (portal: wallet credited,
  Dr portal account). No bank moves — the refund sits on the vendor as an
  Advance. `refund_amount` / `cancelled_at` on `ticket_purchases`;
  amount − refund = the airline's penalty, which stays in ticket cost. A
  cancelled ticket cannot be reopened (`update()` refuses) — enter it again.
- **Contract flight** (`PostsContractFlightAdjustments`, append-only):
  package reversal (above); `fault` on the change decides the penalty —
  company: Dr 5171 / Cr 5110 (Company Ledger, Borne); agent: that plus agent
  row `contract_flight_airline_penalty` Dr AR / Cr 4181 (Recovered). Fare
  difference on a re-booking the same way (5172 / 4182,
  `contract_flight_fare_difference`, `fare_bearer`). Re-assign can create a
  **new flight** inline (`createFlightForRebook()`, next GF no., open).
- **One cancellation = ONE line on the agent's statement** (owner,
  2026-09-28: an agent will not accept two charges for one cancellation):
  `reconcileChangeCharge()` posts `contract_flight_passenger_change` =
  service fee (typed charge) + airline penalty (when fault = agent), e.g.
  "Cancellation charge (airline penalty ৳6,000 + service fee ৳2,500)". The
  journal splits it: Dr AR / Cr 4181 (penalty recovered) / Cr 4183 (our
  fee = profit). No separate penalty row any more (older ones stay). The
  vendor only ever sees the ticket and its refund — never the fee.
- **Company Ledger** (top table) = journal lines under 5170 (Borne) and
  4180 (Recovered — 4181/4182 penalty & fare back, 4183 our fee), shown as
  Borne / Recovered / Net cost. The owner wants NO profit columns here
  (2026-09-28: "the previous version was great") — profit is in Profit &
  Funds below. `CompanyLedger` still maps each line to its passenger.
- **Profit & Funds** (lower half of the Company Ledger, `App\Services\CompanyFunds`):
  per-head trip P&L for every contract-flight passenger (revenue = package
  unless voided + what the agent is billed for changes; cost = live tickets +
  services + what the airline kept; received = `agent_paid_amount`), owner
  withdrawals / investments read from 3210 / 3110 journal lines of company 2
  wherever entered, and the Travels banks' journal balances summed.
  **Deposit / Withdraw** (`companyFunds()`) posts owner purposes through the
  Capital & Financing desk (`FinancingCapital` + `FinancingPostingService::
  postCapital()`) and a vendor charge as Dr 7400 / Cr bank
  (`company_ledger_cash`). Purposes live in `CompanyFunds::PURPOSES` — add
  new ones there. **Manage Bank is never edited** (owner, 2026-09-28): it
  only reads these journals. `CAPITAL_OWNERS` twins the Financing desk list.
- Readers that must know the new types: Party Statement reasons + labels,
  `ContractFlightAgentReceipts` (reversal credits are NOT receipts),
  agent-overall report (invoiced = debit − credit incl. reversals, paid
  excludes reversals), Contract Flight accounts page.
- A money-affecting flag raised in the edit modal (flight cancelled,
  missed, boarding denied, deported — `MONEY_FLAGS`) first opens
  `cfpMoneyEventDialog`: cancel ticket or not, refund per ticket, fault. It
  posts exactly like Cancel from flight and records a `flagged` change row
  (`applyMoneyEvent()`).
- Fare difference on a reissue may be left blank: the change keeps its
  `fare_bearer` with `fare_difference` NULL (= pending), and
  `settlePendingFareDifference()` works it out (new live tickets − the ones
  lost) on the ticket link that makes him fully ticketed. Called from both
  link points (`syncPassengerTickets()`, `linkTicket()`); settles once.
- A vendor paying a refund back: Party Statement → vendor → **Receive
  Refund** (`recordVendorRefund()`): vendor row `vendor_refund` Dr, journal
  Dr Bank / Cr AP, bank balance up.
- **Deported depends on whose side** (owner, 2026-09-28): the dialog's
  fault decides the flag — agent side → `deported` (he repays, package
  stands, case closes); our side → `deported_we_bear`
  (`DEPORTED_BORNE_FLAG`, in `AGENT_CHARGE_VOIDING_FLAGS`, package
  reversed). Migration `2026_09_28_000004` moved existing `deported` rows to
  `deported_we_bear` so nobody's statement changed.
- Owner decided: nothing is pre-selected for "whose side" — not for airline
  cancellation, missed flight or denied boarding. Staff choose every time.

### Decided: a contract flight is sold PER HEAD (owner, 2026-09-28)

The flight's revenue is the sum of its passengers' agent prices, written only
by `applyPassengerDerivedTotals()` / `ContractFlight::syncMoneyFromPassengers()`.
`ContractFlightBookingController::syncFlightSeats()` writes `seats_sold` only
(the seat-block sales screen's own counter) and must never write `revenue`
again. The seat-block "Manage Sales" booking flow still posts its own client
invoices and sale journal; using it for a flight whose passengers are billed
per head would book the revenue twice — it had 0 bookings when this was
decided. Do not build new money on it without asking the owner.

### Ticket Orders → Change (owner, 2026-10-06)

A **Change** button on every purchased row of Ticket Orders: Re-issue /
Reschedule / Refund / Void ONE ticket (`TicketChangeController`, dialog
`ticket_direct_sales/partials/ticket-change-script`). Two roads, never both:

1. **Linked to a contract-flight passenger** → it calls the flight's own
   `ContractFlightPassengerController::ticketAdjustment()`; the postings are
   byte-for-byte what his card's Re-Issue / Refund would post (tested). A
   reschedule with no charge only moves the date and logs it on his card.
   He stays on his flight; the notice links Flights & Passengers
   (`?search=passport`) for **Re-assign**. No service fee here (CF charges its
   own way).
2. **Ticketing-only** → refund/void = Ticketing's cancel
   (`cancelFromContractFlight` → `postCancellationReversal`) + if sold, the
   agent price reversed (`ticket_sale_reversal`: Cr agent, Dr 4110 / Cr 1311)
   + if his side, ONE line `ticket_change_charge` (penalty + our fee, Dr 1311 /
   Cr 4110). Re-issue/reschedule = airline penalty + fare difference owed to
   the seller (`ticket_change_fee`: vendor Dr / portal wallet Cr, Dr 5110 /
   Cr AP-or-portal). Ticket cost heads only — never the Company Ledger (that
   one is contract-flight only).

**Each cost has its own bearer** (owner, same day): `penalty_bearer`,
`fare_bearer` (a refund's kept amount uses `penalty_bearer`); our service fee
is always billed to the agent. The seller is owed the whole airline amount
either way; the agent's ONE line = the parts he bears + our fee. On a CF
ticket a split becomes one `ticketAdjustment()` call per bearer (= two
presses of the flight's button; tested identical); `bearer` on the register
is customer / company / mixed. The dialog is one fixed frame for every tab —
`min(1280px, 100vw − 32px)` × `100vh − 24px` (owner works at 90% zoom on a
1365 window: 1280 CSS px is the red box he drew).

Rules: every change ADDS rows; `ticket_changes` is the register (invoice_id /
source_id of the rows above = its id; `token` unique = one click one change).
A date move updates the first leg AND `departure_at` together
(`TicketPurchase::moveTravelDate()`, also used by the CF Re-Issue and
Re-assign now). A ticket with changes cannot be deleted; a sale holding a
changed ticket cannot be edited/deleted (would reverse twice).
`ticket_sale_reversal` is in `NOT_RECEIPT_TYPES`. Ticket Accounts folds the
changes into revenue / cost of sold / agent due. Tiles: ONE row of 7, one
fact each (owner: "why that many, why redundancy, why two rows"). 2026-10-07: All,
Not Sold, Rescheduled removed too (All = the filter bar) → Booked (hold) · Issued (purchased) · Re-issued · Refunded · Void · Departed (date passed) · Seller Due
Refunded + Void = Cancelled, so Cancelled / Purchases / Expired are
not tiles (the status dropdown has them).

**2026-10-07 (owner):** Re-issue and Reschedule are ONE tab — same ticket, only the
airline's charge, **no fare difference** (the kind is picked on send: a charge =
`reissue`, a date move alone = `reschedule`). **Fare difference exists only on
refund → new ticket**: a refunded/voided row keeps its Change button and opens on
the Refund tab (its history + "Make new ticket": PNR, date, cost, agent charge,
fare difference = new − old with its bearer, our fee). Kind `newticket`, recorded
on the OLD ticket (`newTicketAfterRefund()`); the new ticket is issued by
`TicketDirectSaleController::store()` (purchase + sale as if typed), fare
difference Dr 5172 / Cr 5110, agent's share + fee ONE `ticket_change_charge` line
(Cr 4182 / 4183). One per refund. A contract-flight ticket (`wasContractFlight()`)
is refused → Re-assign. History sits on the right, per tab.

**Delete wrong entry (owner, 2026-10-12)** — the one exception to "a ticket
with changes cannot be deleted". Ticket edit page → "Delete wrong entry" under
the summary card, super admin only (`TicketPurgeController`). Not a
cancellation (those still only ADD rows): it removes ONE ticket that should
never have existed (owner: "I should be able to delete individually") — its
`ticket_changes` and every ledger row / journal they and the purchase posted.
If sold, it comes off its invoice: invoice total/due, the agent's charge row
and the Dr/Cr sale journal all drop by its price; the invoice goes only with
its last ticket. Each party's running balance is re-chained. Refused when
money moved (paid_amount on ticket / invoice, a bank `account_id` row, a
portal debit), when the ticket is or was a contract-flight passenger's, or
when the invoice's postings are not the plain one-row / one-pair shape.
Preview first; DELETE must be typed.

Deploy: `php artisan migrate --path=database/migrations/2026_10_06_000001_create_ticket_changes_table.php`,
`php artisan optimize:clear && php artisan optimize`,
`php artisan route:list --name=ticket-changes` (must list 2). Without the
migration the button is hidden and every page still renders (tested).

### Cancel return (owner, 2026-10-14)

A family passenger flies DAC → ECN and stays on; his unused return is
cancelled inside the airline's window. **Family only** — a CF passenger by his
flight category (name contains "famil") or a family visa, never by a tick on
his ticket; a Ticketing ticket by its Family tick (`family_return`) or a family
visa. `App\Services\ReturnCancel` answers who (family), what (`shape`:
`round_trip` = a two-way ticket, only its return half goes and the ticket
STAYS LIVE; `return_ticket` = a separate ticket, cancelled like a Refund and
unlinked) and when (`return_cancel_by`; a date alone runs to the end of that
day). Three doors, one posting each side: Ticket Orders → Change → **Cancel
return** tab, the CF lane's **Cancel return** button (+ clock = the window),
Ticket Entry's Family tick + "Cancel return by". A CF ticket from Ticket
Orders goes through `ContractFlightPassengerController::cancelReturn()`.

1. **Round trip: refund the half, keep the ticket.** `refundReturnPart()`
   posts Ticketing's own refund (vendor row + Dr AP / Cr 5110, or the wallet)
   and stores `return_refund_amount` / `return_penalty` on the live ticket.
   Every cost reader takes them off (`TicketPurchase::returnTakenOff()`):
   CF travel cost and accounts (refund + penalty — the penalty is already in
   "airline kept"), Ticket Accounts / TicketingProfit / Ticket Orders row
   (refund only — there the penalty stays inside ticket cost).
2. **One agent line** — penalty he bears + our charge, as every change
   (CF: `reconcileChangeCharge`, Ticketing: `ticket_change_charge`).
3. **Agent total: keep, a new total, or an amount given back** — an ADDED
   credit, never an edit: CF `contract_flight_price_revision` (invoice_id =
   the change id, Dr 4150 / Cr 1311, `price_revision` on the change row);
   Ticketing `ticket_sale_reversal` (Dr 4110 / Cr 1311). It must NOT be
   `AGENT_REVERSAL_TYPE`: `reconcileAgentLedger()` re-levels that one to the
   package on every save and would undo it. `chargedPackage()` = price −
   revisions; `package_charged`, flight revenue, receipts allocation, the Due
   scope (`priceRevisionSql()`) and CF accounts all read it.
4. **Reminders** — `tickets:return-cancel-reminders` (every 15 min, scheduler),
   in-app to the ticket's `created_by`, 3 days / 1 day / the day, each once
   (`return_reminders`); a new deadline resets them.

Guarded by `ReturnCancel::ready()` (columns exist) + `Route::has()`: without
the migration nothing shows and every page renders (tested 2026-10-14).
Deploy: `php artisan migrate --path=database/migrations/2026_10_14_000010_add_return_cancel_to_tickets.php`,
`php artisan optimize:clear && php artisan optimize`,
`php artisan route:list --name=return` (must include `cancel-return` and
`return-window`); the server's scheduler (cron) must be running for reminders.

### Ticket Trail — every posting of a ticket, corrected by REPLACING (owner, 2026-10-26)

Ticket Orders → Change → **All Trails and Edit** (top-right of "What will be
posted"; super admin only). Change's own History box now lists the ticket's
whole history (`Timeline`). The Trail window: history (left), editor (middle),
"Posted now" → "What will change" per ledger and account head (right).
`App\Services\TicketTrail\{Footprint, Timeline, Rewriter, Diff, EditLog}`,
`TicketTrailController`, partial `ticket_direct_sales/partials/ticket-trail-script`.

1. **Replace, not reverse** — the owner's exception to "nothing is deleted",
   for this tool only. `Rewriter::run()` in ONE transaction: rewind (remove
   every row/journal/wallet row the ticket's changes posted, put the ticket
   back live on its first date) → ticket fields through `TicketPurchaseController::update()`
   → seller payments re-posted (`postSellerBankPayment()` = make_payment's own
   code) → this ticket's invoice line via `TicketSaleController::resyncFromItems()`
   or a new sale through `store()` → each change replayed through
   `TicketChangeController::replay()` on its own day. A change left as it was
   keeps every row's original date/time and its note, author and moment
   (`restoreDates()`). Every removed row is copied verbatim into `ticket_trail_logs`.
2. **Preview = the same run rolled back** — never a second set of formulas.
   Invariant (tested on every changed ticket): a run with no edits gives the
   same books; only a refund that had Correct-refund rows folds them into one.
3. Replays post on a given day through `TicketChangeController::$clock`
   (null on every request = today, unchanged). `replay` may pass a refund's
   `agent_reversal` and `penalty_billed = false` (a corrected refund that kept
   the penalty inside the reduced refund) — the Change dialog never does.
4. Corrections send no email/SMS unless ticked: `suppress_party_notifications`
   plus a silent `kind = trail` watermark row so the next notice does not list them.
5. Running balances: the Trail walks its own way — an `opening_balance` row is
   an ANCHOR (never rewritten; 27 of 30 restart at 0). The older helpers
   (`rebuildPartyRunningBalancesFrom`) flatten them; do not reuse those here.
6. Every journal the ticket carries must balance or nothing posts; ids from
   the browser are checked (seller = vendor/portal, buyer = agent/customer,
   bank, passenger, route).
7. `EditLog` (TicketPurchase `updated` hook, never throws): every field edit
   from any screen becomes one history line per request. Quiet for Change,
   contract-flight and Trail routes (they keep their own record).
8. A ticket taken off an invoice gives its seat back and a re-price moves the
   route's sold value (`tickets.qty / total_sale_qty / total_sale_amount`,
   counted on the route it was sold on) — as Manage Sales' `update()` does.
9. A "New ticket" change needs its refund / void before it; removing or
   re-kinding that refund while the New ticket stays is refused.
10. The airline-refund journal (`ticket_purchase_cancel`) is dated on its
    PURCHASE journal's day, whatever day the change posts on: `JournalEntry::booted()`
    copies a reversal's date and time from the entry it reverses. Not the
    Trail's rule — Change and the Edit page do the same.

11. **A contract-flight ticket** is edited on its ticket side only (cost,
    seller, PNR, dates, seller payments); the change flows into his travel
    cost and the flight's cost, never its revenue. The passenger is locked
    and it can never be sold to an agent (he is billed per head). The
    flight's own rows for HIM (package, services, his change charges) are
    shown, marked whole-passenger. Read-only, pointing to his card's Flight
    changes → Correct, when he has flight changes or ticket changes, or the
    ticket was taken off his flight.
12. The seller goes to `update()` as BOTH `vendor_id` and `portal_id`, the
    unchosen one null: `update()` fills with `$request->only()`, which skips
    an absent key, so a vendor → portal switch left both set (QA 2026-10-26).
13. The page behind does not scroll while the Trail is open (owner asked
    twice): class `ttr-lock` on `<html>`, `!important`, padding included —
    an inline lock was undone in his browser. A column that holds select2
    pickers is redrawn only after `releasePickers()`: an open select2 pins
    every scrollable box around it, and a redraw from its change event (which
    fires BEFORE it closes) left both columns stuck (live, 2026-10-26).

Tested 2026-10-26 on a copy of the live dump: 17 scenarios, 249 checks
(movement per party / account / bank / wallet / invoice / schedule / seat
stock against hand-worked figures, journals balance, chains continue, no-edit
replay changes nothing, Party Statement and Company Ledger agree) + one post
through the browser; then portal-wallet and contract-flight tickets, 10
scenarios, 146 checks (tickets made through Ticket Entry's and Change's own
`store()`), and a contract-flight ticket's Trail clicked through.
Harness: `qa.php` + `qa-scenarios.php` / `qa2-scenarios.php` (not in the repo).

Not built yet: agent receipts, adding a NEW change from the Trail (Change does
that). Sellers are almost never paid per ticket (party payments) — those stay
on Party Statement.

Guarded by `TicketTrailLog::available()` (table + routes) — without the
migration the button, the routes' pages and EditLog are all silent.
Deploy: `php artisan migrate --path=database/migrations/2026_10_26_000001_create_ticket_trail_logs_table.php`,
`php artisan optimize:clear && php artisan optimize`,
`php artisan route:list --name=ticket-trail` (must list 3).

### Undo of a wrong cancellation (owner, 2026-10-26)

The second exception to "nothing is deleted": super admin's **Undo** on a Cancelled row
REMOVES everything the cancellation posted (package reversal, charge, penalty,
ticket refund) — `App\Services\ContractFlightCancellationUndo` +
`TicketPurchaseController::undoCancelFromContractFlight()` (the mirror of
`cancelFromContractFlight`) — puts his tickets back live and him back on his flight,
restoring it if it was deleted after he left. Every removed row is copied to
`contract_flight_undo_logs`. It refuses, touching nothing, when anything happened
after the cancellation, when it was corrected (FIX_SOURCE), or when the package /
agent receivable would not come back exactly. Others keep the add-a-line Undo.
Tested: cancel → Undo leaves every live ledger line, balance, wallet and flight
identical to before (Ticketing's re-sync leaves 2 soft-deleted journal lines).
Without the migration it falls back to the old Undo.

### Contract Flight Trail — every entry of a passenger, corrected by REPLACING (owner, 2026-11-01)

Re-assign window header → **All Trails and Edit** (super admin; Flight changes → **Edit** opens it at
that entry — it replaces Correct, which stays only as the fallback when the Trail is not installed).
`App\Services\ContractFlightTrail`, `ContractFlightTrailController`, `partials/cf-trail`. The ticket's
own side (cost, seller, PNR, payments) stays on the Ticketing Trail — now editable there for a live CF
ticket even when he has flight changes (the Rewriter never rewinds/replays a CF ticket's changes).

1. **Edit = read from the postings, remove, post once.** `ContractFlightChangeCorrection::posted()/plan()`
   give what each ledger holds + the edit; `entryRows()` finds exactly the entry's rows (agent line,
   seller rows paired to their journal by party/ticket/amount/text, wallet rows, the 5110 penalty / fare
   reclasses, fare rows, Correct's added rows, Undo's reversals); they are removed (copied to
   `contract_flight_undo_logs`, summary "Trail — …") and posted again in the FIRST posting's shape
   (`reconcileChangeCharge`, postReissueFee's shape). A seller journal first posted bare (no party /
   wallet row, older Re-assign) is re-posted bare while its seller is unchanged.
2. **Dates do not move unless edited**: each new row takes the date of the row it replaced; a refund
   re-posted through Ticketing keeps its row's date; an edited "Posted on" stamps every row.
3. **Delete = money + effect** (owner): ticket_refund → ticket live + relinked; ticket_reissue → date
   back; lane New ticket → PNR off him; cancelled → `ContractFlightCancellationUndo`, then a per-party
   AND per-company re-walk (the Undo's own walk mixes a multi-company party's chains). Refused when a
   later entry is on the same ticket. Moves between flights, flags, offload / deport: not deletable yet.
4. **His tickets and services are entries too** (owner, same day: "two ticket, airlines can be changed,
   amount, date, vendor, can be deleted one ticket, or both"). A ticket's route / PNR / class / travel
   date / cost / seller / payments go through the Ticketing Trail's `Rewriter::run()` (same rows as Ticket
   Orders); its airline (no money) is set on `validating_carrier_id` + its legs. Delete removes every row
   it posted (wallet given back), unlinks and deletes it — refused while a flight entry names it, it has
   bank payments to the seller, or it is on a Ticketing invoice. A service (trainer / consultant / other):
   who is paid and how much, through `reconcilePassengerServiceCosts()`; remove = cost 0.
   Date boxes are `type="text" data-dt` (the shared picker): a `datetime-local` box handed to the picker
   loses a midnight value — it writes "Y-m-d" into the box before switching it to text, Chrome blanks it.
5. **Not editable yet**: offload / deported (they also post deport costs + service refunds under the
   penalty source — only the 5110 reclasses are the Trail's), entries "recorded the old way", an
   agent user that no longer exists.
6. Tested 2026-11-01 (rolled back): every editable entry (78) replaced with the time moved → no ledger
   or account head changed; 6 deletes; refund / bearer / seller / package edits by hand-worked figures;
   clicked through at 1365×718.

Guarded by `ContractFlightTrail::available()` (Correct's columns + `contract_flight_undo_logs` +
routes). No migration. Deploy: `php artisan optimize:clear && php artisan optimize`,
`php artisan route:list --name=contract-flight-trail` (must list 3).

### Watch out

- **Ticket 1 / Ticket 2 is the LEG, never the date** (2026-11-01): `ContractFlightPassenger::slotOrder()`
  — `leg_sequence` holds its slot; a ticket without one fills a free slot in journey order. Both legs
  of a via route share one route id, so never match a lane by route id alone.
- **A route (`tickets`) may have no airline** since 2026-10-26 (owner: one route, the airline chosen on
  each ticket). Read it as `$ticket->airline?->name`; Ticket Orders already falls back leg airline →
  the ticket's "Airline that issues" → the route's.
- `Eloquent\Collection::except()` filters by **primary key**, not by the key
  you passed to `keyBy()`. Using it with passport-holder ids matched almost
  nothing and marked a whole batch for deletion. `passengersNotIn()` exists
  because of that.
- The modal's fields are `width:100%` inside `1fr` grid tracks, so
  `box-sizing: border-box` is stated explicitly rather than inherited from
  Tailwind's preflight. Without it the last field in every row spills past the
  card and is clipped by `overflow:hidden`.
- On a laptop (≈620px viewport) the modal body is under 400px. Check any modal
  change at 1365×718, not just at desktop height.

### Contract Flight cost splits (owner, 2026-11-02)

Re-assign refund cards accept `faults[ticket_id] = split` plus `penalty_agent_amounts[ticket_id]`.
`penaltyByLeg()` validates the share against that ticket's kept amount; existing `splitLegPenalty()`
records the agent and company portions, keeping one agent charge line and the full airline-loss reclass.

The passenger builder's existing fare-difference box offers Agent pays / We bear it / Split.
`rebook_fare_mode`, `rebook_fare_bearer`, and `rebook_agent_fare_amount` travel with the flight save;
existing pending reissues are updated before ticket linking settles the fare. The lane New Ticket
flow accepts `agent_fare_amount` too. A nullable `agent_fare_amount` on the change preserves historic
all-or-nothing rows; `agentFareAmount()` is the shared reader for totals, SQL scopes, receipts,
postings, and the Trail. A split must not exceed the actual same-sector fare difference. The full
fare stays inside ticket cost; only the chosen share becomes agent revenue. Trail / Correct retain
and can amend the saved share. No new ledger or journal type.

Deploy: `php artisan migrate --path=database/migrations/2026_11_02_000010_add_agent_fare_share_to_contract_flight_changes.php`,
`php artisan optimize:clear`, `php artisan optimize`. The split fare UI is guarded until the column exists.

Re-assign splits also cover every section (owner, 2026-11-02): `ContractFlightCostSplit::agentAmount()`
validates each chosen share. Reissue `ticket_charges.*.agent_amount` splits the existing per-ticket note
and companion row, retaining one seller bill; Trail / Correct preserve that per-ticket share. Offload
uses `penalty_agent_amounts` and `services.*.agent_amount`; deportation uses
`deport_costs.*.agent_amount`. The deported package may split through `package_agent_amount`: keep
the package active, add a `PRICE_REVISION_TYPE` credit for the company portion, record `price_revision`
on the event, and reset `forgetRevisions()`. Suspend withdrawal stores bearer `split`; its existing
`agent_total - our_charge` is the agent cost share, with the remaining cost debited to 5173 in the
same balanced journal. No additional migration is needed for these sections.
