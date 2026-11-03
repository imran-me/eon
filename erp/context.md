# Owner context — how to build for this ERP

Read this before any change. It is the owner's way of working, distilled
from real requests. When a new request is ambiguous, the answer is usually
here. Keep adding to it: every time the owner corrects a direction or says
"fantastic", the reason goes in.

---

## 1. How the owner asks

### Party management is shared and View All is group-wide — RAFI (owner, 2026-10-31)

- Party Management is an ERP-wide permission section, not part of Travel or Accounts. Keep one sidebar link with permission-aware Vendor, Agent and Customer tabs, while retaining separate permissions for each party type.
- Party-list output uses a separate browser print page in the same branded sheet family as Party Statement (company header, summary, ledger table, footer and signature); Excel/PDF export buttons are not needed.
- `view all party statement` means consolidated access across every company in the group, even when the accountant belongs to the Group company. Plain `view party statement` remains restricted to the user's own company.

### Employee payment corrections start with an overview — RAFI (owner, 2026-10-28)

- The employee Accounts ledger uses a compact pencil action like the bank ledger. Open an overview first, keep its payment header visible while switching panels, then offer exactly two correction cards: Date & Time and Reversal. Panel switching should use the same short visual transition as the bank correction workspace.
- Every employee-ledger row needs the pencil for Date & Time correction. Show the Reversal card only for a transaction type whose accounting service can safely post the opposite entry. Default the table to Active transactions; reversed originals and reversal rows belong in Reversal history, not the active list.

### Cancelled ticket cost remains correctable — (owner, 2026-10-26)

- A posted cancellation must not reopen, but an operator still needs to correct a mistaken original Net Cost from the ticket edit page. Keep the recorded airline refund immutable in that edit; re-derive the remaining penalty and original purchase ledger/journal from the corrected cost, and refuse a cost below the posted refund.

### Show a void as one ledger lifecycle — (owner, 2026-10-26)

- On a party statement, do not show a single-ticket invoice's original sale and its void postings as apparently duplicated charges. Present the original charge, sale reversal, penalty/service charge and net effect together in one expandable row while retaining every underlying transaction in the audit details. A multi-ticket invoice stays ungrouped because its shared sale row cannot safely be assigned to one passenger.

### Overall credit can outlive its invoices — (owner, 2026-10-26)

- An overall discount/credit note may be intentionally added after every invoice is fully settled. With no open document to identify a company, infer it from the party's ledger when all of that party's transactions belong to exactly one company; keep the credit unallocated on the party account. Continue to require an explicit company when the ledger spans multiple companies.

### Custom service invoices use an explicit posting time — (owner, 2026-10-26)

- The compact custom-service invoice form does not need Destination or No. of Candidates. Use the bank modal's hour/minute/AM-PM Custom Time control instead, and carry that exact time into the invoice, journal and party-ledger row.
- Keep that form minimal: Service Description already explains the charge, so do not repeat it with a Notes box. The Custom Time control should stay compact rather than stretching across the form.
- Match the bank modal's Custom Time UI: three separate compact hour/minute/AM-PM selects with a colon and ledger-time helper text. Use a textarea for Service Description so longer service details remain readable.

- **Screenshots are the spec.** A red box = "exactly this place". An arrow =
  "this element". "Here" means the marked spot, not somewhere similar.
- **"Same as X" means reuse X.** Reuse the existing code, partial and look
  of X — never rebuild a lookalike. ("the eye in Flights & Passengers must
  be the same as the dashboard's eye" → the same sheet code, shared.)
- **"Just the icon" / "just move it" means only that.** No extra logic,
  no extra UI. Moving a button = the button's existing handler goes along.
- **Short and fast.** Answers stay brief; long runs and long explanations
  frustrate. Do the work, then say what changed in a few lines.
- **"Before was great, reverse it"** — if the owner reverts a redesign, go
  back exactly and keep only the part they named. Do not redesign what was
  not asked.
- **Plain words.** The owner mixes English with typos; read for intent.
  "reissue" = same PNR, airline penalty/date change. "new PNR" = the airline
  swapped the ticket. "refund" = ticket cancelled, airline returns money.

## 2. UI patterns the owner likes

**Layout**
- Compact row actions: 24px icons, **two per row**; a wide action (e.g.
  Re-assign) spans both columns as an icon with one short word under it.
- Rarely used actions leave the table and live in the **eye sheet's bar**.
  Hide the table copy (don't remove it — the bar clicks the original), and
  put the hide rule in `<head>` so nothing flashes on load.
- Big modals, near full window; plenty of **breathing space** between
  fields; same design, more room — never cramped.
- Modals close only on the ✕.
- **Two airlines on one card** (via route): leg 1's logo full size, the
  other leg as a small white **badge on its bottom-right corner** (coin for
  a roundel, pill for a wordmark). Rejected 2026-10-01: logos overlapping
  in a pile, and a two-row white tile.
- Airline logos are **uploaded from Ticketing → Airlines**, never copied to
  the server by hand.
- **A section with several pages = ONE sidebar link + a tab band** on top
  of every page of it ("like we always do", 2026-10-04, Ticketing). The
  band is a `layout/*-tabs` partial in the shared underline `.ex-navband`
  look (contract-flight-tabs, ticketing-tabs), included first in
  `main-content`; the sidebar link lands on the first tab the user may
  open. Same permission gates as the old dropdown.
- **Every table gets the standard filter bar from the first build** — the
  Passenger Ledger's `fdl-bar`: search · quick-filter segment (states that
  fit the data) · date range dd/mm/yyyy with Apply · Print (A4 sheet).
  Reuse the bar's classes; never ship a bare table (owner, 2026-10-01: "why
  are you not remembering my style??").
- **Same row height for every row.** Long lists inside a cell (agents) show
  max 2, one line each, then "… +N more" that expands the row in place.
- **No empty zones, no repeats.** A header zone takes only the width its
  text needs; spare width goes to the zones whose text is being cut. If a
  new block names something, drop the old line that said it.
- **Form cards: one shared column grid** (owner approved 2026-10-05, Ticket
  Entry): every row of a card sits on the same 12 tracks so field edges run
  straight down the card — "less messy without more space or fewer fields".
  Asked to "think more creatively" on top of it: related boxes join into one
  capsule (seam badges → + ⇄ = / were tried and REJECTED 2026-10-05:
  "these icons look bad" — plain shared border only), money right-aligned, computed totals
  tinted as a result, "+" buttons docked onto their dropdown. Owner:
  "fantastic … more creative" — then added: zones turn green when their
  required boxes are filled (CSS `:invalid`, no script), PNR / e-ticket in
  monospace like a boarding pass, pay status coloured due / partial / paid.
  An entered total (Net Cost) must never LOOK derived — no "=" badge.
  No placeholder/example text inside entry boxes ("remove these shadow
  text", 2026-10-05) — the notched title names the box.
- **Logos sit on a white floating tile** (rounded, soft shadow), not bare on
  a gradient.
- **Don't say a thing twice.** One indicator per fact: a ticket shows a
  green ✓ circle when purchased, an empty ring when not — no separate
  "issued" pill, no "purchased" tag. Only a state the tick can't show
  (booked, pending) keeps its tag. REJECTED 2026-10-03: "1 / 2 ●" counter
  pills ("not 1/1 2/2… tick mark if done").
- **Fewest lines per item** (2026-10-03, Tickets column): each ticket is
  two lines — PNR, then a white pill with the airline's small logo (flag
  size) + code, every pill starting at the same x down the column; then
  `DAC → IST · 01 Oct, 09:55 AM`
  (year only if not this year); full names go in the tooltip. Legs of one
  route are joined by a dotted journey line (green once every leg is
  bought). `DAC ✈ IST`, not `→`; the group suffix "(a)" small and pale so
  the PNR reads first; a not-bought leg's state tag gets its own line —
  never squeeze the aligned pill. Owner: "great, I liked it". The
  dashboard's Passenger Ledger shows the SAME cell ("why is this not
  reflecting the passenger ledger?") — shared `partials/ticket-legs-styles`
  + `ticket-logos-script`; markup twice (passenger-row Blade,
  `ledgerTicketLegs()` JS) — change both.
- **Every cell, the same discipline** (owner, 2026-10-03: "minimal yet
  informational, noise free"): a bold first line, then short grey lines
  with NO "Label:" prefix — the tooltip names the field; an empty value
  ("Type: —") is not drawn; no pills in these cells — colour on plain
  text does the work (REJECTED 2026-10-03: CF-019 / via IST / Rescheduled
  as three pills — "looks odd"). Passenger = name /
  passport / category · handling. Agent = name / EP-AG-id · class (· type)
  / each ticket's seller, small, once per seller, max 2: "🏪 BSP IATA
  EP-VN-139" (a portal reads "Portal"); the name shortens, the code never.
  Route = flags DAC ✈ ECN / date / ✈ CF-019 · via IST (indigo · amber
  text) / ⚠ Rescheduled (red text) — the flight lives in
  Route, airport names and "was" history in tooltips. Profit = a small
  green / red pill. Twins: passenger-row + off-flight-row (Blade) and
  `ledgerPassengerCells()` / routeCell (JS); CSS in ticket-legs-styles.
- **The dashboard's Passenger Ledger IS the Flights & Passengers table**
  (owner, 2026-10-03: "should look like the passenger table, same"): same
  columns (SL · Passenger · Agent · Tickets · Route · Charge & Cost
  Breakdown · Cost / Profit · Actions), and the table wears `.mfp-table` so
  passenger-table-styles dresses both. JS rows in dashboard-ledger-script
  mirror passenger-row.blade.php — change both. Ledger actions: print + eye.
- **Click a row's empty space → that passenger's journey strip opens /
  closes** (only that row; owner, 2026-10-03). Links, buttons and controls
  keep their own job. Status button still opens / closes all (on the
  ledger it also resets one-row flips). Both tables.
- **The journey strip is a boarding-pass card** (owner, 2026-10-03: "make it
  more creative"): left stub = % ring · NOW stage · Next → stage · flag,
  torn off by a dashed perforation; track = done stages as small green ✓
  dots, the current stage a pulsing indigo pin with a flowing line into it,
  upcoming on a dashed flight path. Each stage draws its own half-
  connectors so the green ends exactly at "now". All legs bought while
  still at the ticket stage → the last leg is "now". The strip keeps a WHITE
  background (owner: "bg like before" — not the row's tint), NO coloured
  side edge; the card's BORDER takes the row's KPI colour (purple / blue /
  red, "give the border color"), roomy padding above / inside ("need more space"), even 12px gutters, labels
  never leave their slot. Hover ("more fun and advanced"): pin lifts and
  glows in its colour, a dark bubble with the stage / state / "Click to set
  this stage", other labels fade, and on an upcoming stage the path from
  "now" up to it lights and flows indigo. SAME card on the
  Flight Dashboard ledger ("same here"): CSS once in
  partials/journey-card-styles; markup in passenger-journey-row (Blade)
  and ledgerPassengerRoute() (JS) — change both. The ✎ (edit stages) sits
  on the stub EVERYWHERE ("keep the edit option of the stages,
  everywhere"); the editor is one shared partial, partials/stage-editor
  (dashboard repaints via window.onStageEditorSaved, F&P reloads).
- **"No PNR yet" needs no "not ticketed" tag** — one indicator per fact;
  only real states (booked, pending, hold expired) keep a tag.
- **No Stage column on Flights & Passengers** (owner, 2026-10-03: "stage
  not needed, as we have status bar at the bottom"). Only the exception
  flag survives, as red text on its OWN line under `CF-019 · via IST` in
  Route (owner: "it will under" — never beside them). Flight Accounts
  keeps its Stage column (no status bar there).
- Header lines that get cut are moved, not ellipsised: airline flight
  numbers sit under the batch line; several vendors stack one per line; a
  via route's second airline is a small white badge on the logo tile.

**Records and confirmations**
- An **A4 sheet** (the `fdl-sheet` look) for a record (eye) and for every
  money action's confirmation: old vs new, per-leg table, money boxes,
  "What will be posted". Nothing posts until **Confirm & post** — a bright,
  green, raised, gently pulsing button.

**Per-leg cards** (the shape the owner approved — reuse it)
- Left: route with flags `🇧🇩 DAC ✈ 🇹🇷 IST`, the PNR as a small tag,
  `was 26 Sep 2026` in grey, and a switch under it: "Reissue this leg" /
  "Refund this leg" / "Issue this leg now". Off = card greyed, inputs locked.
- Right: the fields in a row (New date · Ticket number · Charge · Who pays),
  a pill toggle **Agent pays / We bear it**.
- Teal left border, light border, regular (not bold) text.

**Numbers**
- **Charge / penalty amounts are red, everywhere.**
- **Charge & Cost Breakdown is one column** (Flights & Passengers,
  2026-10-01): the agent's charge on top with Paid / Due, then each cost
  line under a rule as "− amount". Cost / Profit stays its own column.
  REJECTED the same day: merging Agent Charge with Cost / Profit into a
  "Charge − Cost = Profit" statement — "I said Cost Breakdown and Agent
  Charge". Merge exactly the columns named. Flight Accounts keeps all
  columns separate (its footer totals sit under Agent Charge).
- **Show the old under the new**: current value, and beneath it in grey
  `was 26 Sep 2026` / `was CF-015 · 26 Sep 2026, 09:55 AM`.
  EXCEPT the Passenger Ledger's Route cell (owner, 2026-10-03: "history
  not need here") — no "was CF-019 · …" lines there; the eye sheet keeps
  the history.
- **Show every breakdown line by line** so totals never confuse: Cost =
  each ticket by PNR + each service + fees + penalties; Charge = package +
  everything billed to the agent; Profit = Charge − Cost. The lines must
  always add up to the totals.

**Forms**
- **Entry screens = compact card + sticky summary rail** (owner,
  2026-10-04: "fantastic" — Ticket Entry). Each item a card of titled zones
  (small violet icon + grey uppercase title, dashed divider) on one grid;
  36px fields; totals, warnings and Save in a right rail that stays in view.
  Smart checks as soft chips (red / amber / indigo), never blocking.
- Pre-fill from what is already known (the refunded ticket's vendor, trip,
  price; the previous card), and **keep every field editable**.
- Nothing mandatory beyond what the owner names; required choices (e.g.
  whose side a penalty is) are **never pre-selected**.

## 3. Business rules the owner insists on

**Ledgers are sacred**
- Every required and connected ledger and journal must be hit
  **immediately, automatically and accurately** — agent, vendor, portal
  wallet, Company Ledger, bank. A missing posting is a bug, not a gap.
- **Nothing is deleted.** Corrections and cancellations are reversals *by
  addition*. Tickets stay in Ticket Orders at their amount with a note.
- **One event = one line** on the agent's statement (penalty + our fee on
  the same "Cancellation / Reissue charge" line).
- Every journal balances; every party ledger's running balance continues
  from its previous row. Test that before claiming anything works.

**Who pays**
- **Whoever is selected bears it** — agent side → his ledger (Charge goes
  up); our side → Company Ledger (profit goes down). Never a fixed party,
  never pre-selected.
- Granularity is **per leg / per ticket**, not per passenger or flight.
  If something can differ between legs or people, it is chosen per leg.

**Contract flight money**
- A passenger is the unit of work and money; the flight is only a batch.
- **Refund reverses the package fully**, then the penalty goes to the side
  chosen for each leg. A refunded person who flies again is charged the
  package again on the new flight.
- A **reissue fee** (same PNR) is owed to the **ticket's seller** — the
  vendor's ledger, or the portal's wallet if bought through a portal —
  whoever ends up paying it.
- **Fare difference = new − old on the same sectors only.** It is inside
  the new ticket's price; never add it to cost a second time.
- Cancelled tickets are unlinked from the passenger automatically.
- **The flight card follows the FIRST leg** (owner, 2026-10-04). Each leg
  is reissued / refunded on its own — both, one, or crossed (one reissue,
  one refund). A later leg's change posts exactly as before but he STAYS on
  his flight; only a change to leg 1 moves him. Leg dates never block each
  other (a later leg may be dated before leg 1) — the date field stays, only
  the condition went.
- **Dashboard flights are date-wise, earliest first** (owner, 2026-10-03:
  "it will be date wise" — 1 Oct, 3 Oct, 4 Oct, 6 Oct; undated last) —
  cards and table, `fdNearestKey()` in dashboard.blade.php. REJECTED the
  same day: "upcoming first, then past" (put 1 Oct last). The Departed
  Flights table stays newest first. Flights & Passengers keeps
  `scopeNearestFirst()` unchanged.
- **The ledger header always shows the airline flight number** under the
  batch line (2026-10-03: "flight number? show here"); not set → a pale
  "Flight no. not set" that opens Edit flight. Tickets carry no flight
  numbers, so the flight's `airline_flight_no` is the only source.
- **Agent names in the flights table use the route line's font** (regular
  weight, slate) — bold read as "so bold" (2026-10-03).
- **A flight is finished when every passenger is Case Closed** (owner,
  2026-10-01) — the flight status is usually left at Open, so never trust it
  alone. Finished flights leave the dashboard cards and sit in the Departed
  Flights table under it.
- Flight change or refund starts from the row's **Re-assign**; a same-flight
  change to one ticket uses the ticket buttons in Edit Flight.

**Source of truth**
- **The local code is the reference; live may be wrong.** When bringing a
  feature over from live, bring only that feature and rewrite its logic to
  match ours (see 2026-09-30: live's Re-Issue posted Dr 5171 / Cr 5110 —
  replaced by our vendor-owed fee posting).
- One vendor can also be a portal (BSP IATA): choose the seller correctly
  on the ticket, and the money follows the ticket.
- **Build our own, no outside AI service** (2026-10-05, ticket PDF import):
  asked whether unknown ticket layouts could be read by an AI service, the
  owner said "no, build our own". Each layout gets its own reader
  (`app/Services/TicketImport/Readers/`); an unknown layout or a scanned
  picture is reported as such and entered by hand — never guessed.

## 4. How to work here

- **Test money with a rolled-back run** (`DB::beginTransaction()` …
  `rollBack()`): check every new journal balances and every ledger chain
  continues. Report the actual postings, not assumptions.
- **Prefer no migrations.** If one is needed, state it up front as a server
  step with `--path`, and stop until the owner confirms (see CLAUDE.md).
- **Guard new routes/views** so a lagging server never breaks a page.
- **Don't claim UI works without seeing it** — say "reload with Ctrl+F5"
  and what to look for. Numbers shown on a card must be traced to data.
- The owner runs **several sessions at once**: re-read a file before editing
  it; commit / push / deploy only when asked.
- After a fix, name the files and the server steps (usually just
  `php artisan optimize:clear && php artisan optimize`).
## Contract File workflow and accounting (owner, 2026-10-03)

- A contract file remains one applicant / passport. It is never a Contract Flight passenger and must not reuse ticket, route, rebooking, or flight-stage business logic.
- Files may be grouped for display by the agent/direct client who supplied them; the group is not a new financial document.
- Both agent and direct-customer files are supported. The client's full charge reaches the party due ledger as soon as the file is created.
- The vendor's full cost reaches the vendor due ledger as soon as the file is created. It does not wait for submission, approval, or delivery.
- Rejected, cancelled, returned, resubmitted, payer-changed, vendor-changed, and amount-changed cases use append-only reversal/adjustment entries. Never erase or silently rewrite the financial history.
- The Contract Flight journey strip is a UI reference only. Contract File owns a separate application-processing journey and history.
- Manage Sales is retired for new entries. Agent-wise file grouping uses the file's client_id. Existing sales remain in Sales History with invoice viewing/printing and outstanding settlement; their accounting records must be preserved.
- Contract File navigation follows File Dashboard → Agent/Client group → file/applicant ledger with an inline journey per file. Files & Passengers is the separate all-files list. Grouping is a read-only presentation of individual files and never creates accounting entries.
- Contract File top tabs must be ordered: File Dashboard, Files & Passengers, Passenger Board, File Accounts, File Category. Sales History is accessible within File Accounts rather than as a top tab.
- Dashboard group rows and cards open their file/applicant ledger inline in the dashboard, keeping its URL and active tab. Back restores the overview; search, filters and pagination stay inside the inline ledger.
- Dashboard cards and rows each represent ONE agent/client group, not individual files. Show grouped counts, dates and money in the overview; individual file details and stage strips appear in the opened group ledger.
- Dashboard card opening follows Contract Flight's header-first flow: show the selected group summary immediately, smooth-scroll to it, then populate file/applicant content after loading. Keep the header visible on a loading failure with reload available.
- File Accounts follows Flight Accounts' report layout: six financial summaries, Profit & Loss (monthly and individual files), Agent/Client Due Sheet, Vendor Due Sheet, Transactions and expandable Journal History. Net posted revenue/cost comes from file journals including adjustments; never invent journal totals from file prices when postings are absent. Date filtering is by file submission date, not flight departure.
- Contract File Immigration Training is independent of Contract Flight even for the same passport holder: separate service record, trainer payable, payment and journal source. Only identity/documents may be reused. The multi-applicant builder creates separate files and one package charge per applicant, with training as an additional company cost, never an extra automatic client charge.
- The builder uses Setup → Applicants/Documents/Training → Review/Confirm. Submission tokens prevent retry duplicates, documents live on a private disk and links require matching passport ownership. Do not use crypto.randomUUID alone: the owner's local .test site uses HTTP, where that method is unavailable; crypto.getRandomValues-based UUIDs work there.
- File-only Others services are additional company costs inside the initial per-file package. Post-creation Others / Re-process actions explicitly select Agent/Client or Company bearer every time; no preselection. Client and vendor refund approvals are entered separately. The retained vendor penalty stays in cost (not charged as another expense) and is recovered from the client only when that side is selected. Client refund is the gross charge reversal before the separately selected penalty recovery.
- Refund approvals append party/journal credits; actual cash refunds are separate party-statement settlements. Preserve original package, vendor cost, service and payment records. Each selected processing/service cost may be refunded once; a re-processing action creates a new independently refundable service cost. Confirmation tokens prevent retries from duplicating postings. Flags after explicit money actions must not produce a second automatic reversal.

## Ticketing ↔ Contract Flight (owner, 2026-10-06)

- "If anything is done in Ticketing that relates to the contract flight, it has to take effect there too." A change to a CF passenger's ticket made from Ticket Orders runs the contract flight's OWN code — never a second posting path. Prove it by comparing the postings with the card's button (same journals, same rows).
- Ticketing is ticket-wise, the contract flight is passenger-wise: the Ticketing side must be "smoother, easy, automated, ledger connected" — one dialog, one Confirm & post, every journal hit, no duplicate.
- After a refund / re-issue of a CF ticket he stays on his flight; Re-assign (Flights & Passengers) moves him — the success notice links straight to his row.
- Clickable scenario KPIs on registers ("reissue, void, due, …"): **one row only, one fact per tile** (owner, 2026-10-06: "why that many?? why redundancy, why two rows??" — a second row of 7 under the old 5 was rejected). No tile may equal another (All = Purchases) or be a sum of others (Cancelled = Refunded + Void), and a state the status dropdown already filters is not a tile. Sub-lines of 1–3 words so nothing is cut at 1241px.
- **Every cost gets its own "who bears it"** (owner, 2026-10-06, Ticket Orders Change): penalty, fare difference, what the airline keeps — each its own Agent pays / We bear it pill, never pre-selected; our own fee is always the agent's. The agent is named once (section header), the pills keep the owner's words "Agent pays / We bear it" — a long agent name inside a pill gets cut.
- **Modals: one fixed size for every tab** — near the full window (the owner's red box: ~1280 CSS px wide, 12px from top and bottom; he works at 90% zoom on 1365). Content scrolls inside; the frame never jumps between tabs. The owner called the "What will be posted" panel "fantastic" — keep it in every money dialog, with a totals block at its foot.
- **One border colour inside a dialog** (owner, 2026-10-06: "same border color that we are using other where"): every box, tab, pill and card takes Ticket Entry's field border `#d2d8e7` (focus `#7c3aed` + soft violet ring); no lighter greys mixed in, no accent side-borders, and empty rows are never dimmed with opacity — that faded their borders.

## Wood Art site costs (owner, 2026-10-05)

- **One set of books.** Wood Art (company 6) site costs live in the ERP's own Expense desk — never a parallel `wa_` ledger. A **project = an Expense Department** of company 6 (Mr. Sogib Miah Project, Mr. Mustaq Ahmed Project, …). Wood Art's Accounts tabs are READ-ONLY windows over those expenses (`Modules/WoodArt/Modules/Accounts/Support/SiteCosts`); Accounts → **Project P&L** is the boss's one-click project cost list, each row opens the project's ledger sheet.
- **Entry is the Expense desk's own flow.** Wood Art → Accounts → New Entry is a *bulk* grid (paste from Excel supported) that files + approves every row through `ExpenseController::store()/approve()` in ONE transaction, after a review page ("Confirm & post", one-time token). Owner: "multiple entry, double-entry journal, quickly".
- **Who paid** = the person whose hand the money went through. Owner: sometimes the company advanced money (petty cash float), sometimes it was their own pocket and the company repays later — both already exist (float / reimburse). History defaults to *own pocket* (credits 2200 owed to the person, needs no cash); their ledger is the Petty Cash / Reimbursement desks.
- **Phase order (owner):** costs + projects first, supplier due/paid second, client receipts third. Supplier due needs new code (the Expense desk always posts fully-paid); Party Statement vendors are global (shared with Travels) and `VendorController` hard-codes company 2 — do not reuse them for Wood Art.
- **Scope (owner, 2026-10-06): Munshi Villa ONLY.** Mr. Sogib Miah's site is already in its completion process and is NOT to be entered. Owner: load everything that exists first, then sit with the accountant over the cost summary.
- **`php artisan woodart:seed-munshi-villa [--dry-run]`** loads the sheet's "Over all Accounts" tab: 92 cost entries (৳ 32,21,891) as approved company-6 expenses under "Munshi Villa Project" (data in `Modules/WoodArt/Database/Seeders/data/munshi-villa.php`, refs WA-MV-001…, re-runnable, ONE transaction). Each is booked as the REF. NAME person's own money (Cr 2240 owed to them, no cash/bank): first-named person wins (RONY -> Md Habibur Rahman, owner-confirmed; BOSS/unknown/Talim -> Md Mohsin with "via Talim"). The original REF. NAME stays in every description. The Overall tab is ~7.7 lakh above the sheet's own Summary tab (the Summary is stale: e.g. Rod bills stop at 27 Jun, Aug-Sep rod purchases 3,12,529 are only in Overall) — the accountant reconciles; wrong entries are reversed in the Expense desk, never deleted.
- **Statement tables** (`wa_site_terms`, `wa_site_statement`, migration needs `--path`): contract value (Munshi ৳ 70,00,000), client installments (4, ৳ 50,00,000 received, ৳ 20,00,000 due) and supplier balances noted "up to 10.09.2026" (Asia Bricks 1,60,000; Helal 66,680; Haji (Rod) 2,31,900; Mostofa/Talim wood 10,000; Hossen 45,000 = ৳ 5,13,580). A row starts as a statement entry and is POSTED to the real books only by an explicit "Post to books" on that row (Modules/WoodArt/Modules/Accounts/Support/SitePosting): client bill Dr 1315 / Cr 4410; installment Dr Wood Art's bank / Cr 1315 (owner, 2026-10-06: what a client pays is RECEIVABLE 1315, never advance 2310); supplier due Dr cost head / Cr 2111 (supplier) or 2112 (contractor); supplier payment Dr 2111/2112 / Cr Wood Art's bank. Wood Art money moves ONLY through Wood Art's own bank (company 6) — another company's bank is refused; the one exception is a receipt that already landed in another company's account (history), booked to 1353 Inter-Company Receivable. Posted rows are REVERSED by an opposite journal, never edited or deleted; a supplier is paid only what the books owe; bank-out uses Bank::assertCanCover. The seeder posts NONE of the history (it does not know which account received the 4 installments) — the accountant posts each row. Bank 20 (Dhaka Bank - Interiors) was relinked from account 84 Machinery to 396 "1150 Dhaka Bank - Interiors" (11 journal lines moved, 2 of them Travels' inter-company transfers) — ledger now 510. Shown on Accounts → Overview / Payables / Project P&L and the project sheet (Client due sheet + Supplier dues, with add / void forms).
- Younus/Hossen contractor ledger shows many payments came from OTHER companies' banks (EPAL TRAVELS IBBL, EPAL IT BRAC, EMI IBBL…): an inter-company matter for the accountant; the Expense desk already records an inter-company liability when another company's bank pays.

## Project Accounts — one engine, each company's own books (owner, 2026-10-06/07)

- **Munshi Villa is a CONSTRUCTION project (Epal Constructions, company 3), not Interiors.** An earlier seeder filed it under Wood Art (company 6) by mistake; `woodart:undo-munshi-wrong-company` reverses it (opposite journals, then soft-delete). Each company's books stay its own; Interiors must never carry construction money.
- **One set of screens, mounted per company:** `/{role}/woodart/accounts/...` = company 6, `/{role}/construction/accounts/...` = company 3, chosen from the URL by `Support\ProjectContext` (never from user input). Company 3 gets ONE guarded sidebar link ("Project Accounts") so it need not wait for a dashboard. Per company: receivable/revenue accounts (6: 1315/4410 own; 3: shared 1314/4310), cost-head category (6: 91; 3: 92). To add a company, add it to `ProjectContext::COMPANIES` + `SEGMENTS`, a heads plan in `SeedCostHeads`, a route prefix in `Accounts/routes.php`, and a guarded sidebar link.
- **Who may look:** `view all expense` or belonging to that company. (The Wood Art pages used to be open to any signed-in user.)
- **Epal Constructions has NO bank of its own.** Its costs were paid from Travels/Group banks and shared cash, and the ERP recorded inter-company liabilities (Constructions owes Travels ~৳9.74 lakh, Group ~৳0.57 lakh, none settled). Project Accounts therefore refuses a bank for company 3 ("has no bank account of its own"); a client receipt that already landed in another company's account is booked to 1353 Inter-Company Receivable. Opening a Constructions bank is the accountant's decision.
- **History goes in PENDING, the accountant confirms.** `woodart:seed-munshi-villa` files the sheet's rows as pending Expense-desk entries (NO journal) under Munshi Villa Project; the accountant checks who paid / from which account and presses the Expense desk's own Approve. Only rows dated BEFORE the first entry already in the system are filed; the rest (the overlap) go to `storage/app/munshi-villa-overlap-review.csv` with the existing entry each probably duplicates. A bulk "Confirm selected" button does NOT exist yet — approval is per expense.
- Tables are neutral now: `project_terms`, `project_statement` (renamed from `wa_site_*` by a migration that is safe from any starting state).

## Vendors & Clients — one record, used by many companies and projects (owner, 2026-10-07)

- A vendor or client is entered ONCE (`project_parties`, one row per name; `name_key` = the name without case/spaces/punctuation, UNIQUE). Where he is USED is `project_party_roles`: party × company × type (vendor|client) × project (project_key 0 = the company in general, else an expense_departments id). Each company's screens list only the parties with a role in THAT company and filter by project / type / search; another company's party opened by id is a 404.
- **Money is never on the party.** A due, payment or installment sits on a `project_statement` row that belongs to a project, so to a company: a shared party's balance is always per company and per project (Constructions' dues to Asia Bricks are never added to Interiors'). Contact details (phone, address) are the shared record's: a change is seen by every company that uses him. Two different people with the same name must be told apart in the name (e.g. "Asia Bricks – Dhaka"): the name key is unique.
- Vendor statement = Bill (a statement due, or a cost entry linked to him — paid when bought, so both bill and payment and the balance is unchanged) minus Paid (a statement payment). Client = contract value minus installments. A cost entry is tied to its vendor on the "Link costs to vendors" page (suggestion from the vendor's name in the entry text; never applied unless someone presses Apply; ambiguous or no match = the person picks).
- Journals are NOT tagged with a party: the shared journal screens resolve an unknown party type to the wrong person. The supplier's name is in the journal description / beneficiary; the supplier-wise ledger is the party statement, the control account is 2111/2112.
- Do not reuse the ERP's `suppliers`/vendor users for this: they have no company, feed Travels' Party Statement and would appear on other companies' screens.
- Party Management is Group-owned. Vendor, Agent and Client create/edit assign one canonical user to companies through `company_party`; Construction/Interiors projects are assigned separately through `project_party` (`expense_department_id`, because Project Accounts defines projects as Expense Departments). Company is required, project is optional and must belong to a selected company. Project sheets attach typed suppliers/clients automatically, and Party Statement can narrow a company roster to one project such as Munshi Villa.
- Gotcha: Blade does not see `@if` glued to a word (`vendors@if(...)`) — put a space before it, or the `@endif` is orphaned and the page 500s.
- **Ticketing table headings = Contract Flight's filter bar, live** (owner, 2026-10-06: "all tables heading should be same style like in the contract flight, with live search, no enter needed"): `<x-ticketing-filter-bar>` (drop-in for `<x-list-filter-bar>`, same props) — labelled fields on one row, presets, From / To, Reset only while filtered; typing / select / preset / date swaps the page's `data-tfb-swap` parts without reloading. Row buttons on those pages must use delegated handlers (`$(document).on('click', …)`), or swapped-in rows lose them.

- **Re-issue / Reschedule = same ticket + the airline's charge, no fare difference** (owner, 2026-10-07).
  The two were one tab already ("both are same"). Fare difference belongs only to refund → new ticket:
  the client refunds, the old ticket is cancelled, he buys a new ticket on another date, and the new
  ticket is compared with the old one. After a refund there must be an option to make the new ticket.
  Built same day as the owner described it: the Change button on a refunded row → Refund tab shows the
  refund history + "Make new ticket" (PNR, new price, agent charge, fare difference, our charge, who
  bears). History of each kind sits on the RIGHT side of its tab ("multiple reissue can happen").
- **Agent pays a penalty + our charge = ONE penalty line** (owner, 2026-10-07): "reissue penalty 10K, our
  charge 4K → agent's ledger a single 14K entry as penalty; company ledger 10K recovered against the
  penalty, and 4K separately as profit". Penalty recovered → 4181, our charge → 4183 (profit), never
  both into one head. Applies to re-issue, refund, void and refund → new ticket.
- **Ticket Entry detects an earlier refund** (owner, 2026-10-07): picking a passenger with a refunded
  ticket shows it on the card — fare difference (new − old), who bears it, our charge.

- **2026-10-08 — "who bears it" is always the pill switch, never a dropdown.** In the contract-flight re-assign system every agent/company choice uses the Agent pays / We bear it switch (`partials/who-pays-switch`, add class `cfr-sw-src` to the select). Whose side stays un-preselected: a blank option means no pill is lit.

## Notes added from now on — ONE BLOCK PER PERSON

<!--
  TWO PEOPLE (AND CLAUDE) EDIT THIS FILE. To keep git from conflicting:
  1. Write ONLY inside YOUR OWN block below, at its bottom, just above its "END" marker.
     (Claude: the block of whoever `git config user.name` is — Afiqur Rahman = RAFI, Imran Hossain = IMRAN.)
  2. Never edit, reorder or reformat the other person's block, or the older sections above.
  3. Heading of a note: "### Topic — (who asked, date)" and say WHY, not only what.
  Two blocks edited in different places merge cleanly, even on a GitHub pull request;
  .gitattributes also marks this file merge=union as a second safety net.
-->

<!-- ===== RAFI (Afiqur Rahman) — add your notes at the bottom of this block ===== -->

### Financial statements — Zoho-style, one company at a time (owner, 2026-10-08)

- Reference: the owner's Zoho PDFs (P&L, Balance Sheet, Cash Flow, AR/AP Aging, Expenses by Category, Monthly Report). Built in three steps; step 1 = P&L, Balance Sheet, Cash Flow, Expenses by Category. Step 2 = AR/AP Aging, step 3 = Monthly Report (snapshot, health score, key gaps).
- `App\Services\Reports\StatementBuilder` reads the journals with ONE aggregate query per statement; sections come from CODE RANGES in `config/account_reports.php` (the chart is 1xxx assets … 4xxx revenue, 5xxx COGS, 6xxx/7xxx operating, 8xxx non-operating, 9xxx tax & control). An account no range claims lands in a visible "Other" line, so totals always tie. Fix a section by editing the config, not the code.
- **Inactive accounts with a balance are INCLUDED** (shown "(inactive)"): the classic reports dropped them, which is why some balance sheets did not balance. Where numbers differ from the classic page, that is the whole difference.
- Company: `AccountingReportController::scope()` — no "view all account" → locked to own company; with it → dropdown, default own company, "All companies (whole group)" is explicit and warned (inter-company counted both sides). The account-ledger drill-down only knows `scope=all|company`, so the link is shown only when the report matches one of those.
- The existing P&L (`profitLossV1`) and Balance Sheet (`balanceSheetV1`) now delegate to the new screens; `?classic=1` keeps the earlier layout. Print = layout-free `report/statements/print.blade.php` (letterhead like the expense report); CSV via `*/export`. Permissions reused (profit loss / balance sheet report), none new.
- The Cash Flow and Balance Sheet pages say plainly when the ledger itself does not reconcile (an unbalanced journal entry). Local DB example: entry #2255 VISA-036 (Travels) is out by 4,000.
- Constructions' own balance sheet shows NEGATIVE cash: its Expense-desk entries were paid from other companies' bank accounts (Travels / Dutch-Bangla / Dhaka / Islami / City). That is the inter-company funding question, now visible on a statement.
- **Step 2 — AR / AP Aging** (`App\Services\Reports\AgingReport`): reads the PARTY ledger (`transactions`, debit − credit), the same one the Party Statement uses, so a party's total always equals his statement balance. Credits settle the OLDEST debit first (FIFO — the ledger has no per-invoice link for most postings); a debit is aged from its document's `due_date` where it has one (ticket sales/purchases, party invoices), otherwise from the entry date. Credit balances are ADVANCES, listed separately, never netted. Receivable = agents + customers, payable = vendors. (Deviation from the first plan: documents' own paid/due columns are NOT used — they can disagree with the ledger.)
- **Step 3 — Monthly Report** (`FinancialSnapshot`): this month vs last (a running month is month-to-date against the SAME number of days of the previous one). KPIs, six-area health score, key gaps and recommendations are produced by explicit rules (thresholds in `config/account_reports.php → health`), each line naming the figures that triggered it. Receivable and Payable KPIs are the party-ledger totals (agree with the aging reports); cash = cash accounts, bank = bank ranges.
- Reports tab bar (`account-report-tabs`) now also lists Cash Flow, Expenses by Category, AR Aging, AP Aging, Monthly Report; sidebar "Account Reports" active-state lists include the new route patterns.
- **Report menu regrouped (owner, 2026-10-08: "too many reports — what is duplicate?")**: `account-report-tabs` shows four labelled groups — Statements (P&L, Balance Sheet, Cash Flow, Trial Balance) · Receivables & Payables (AR, AP Aging) · Analysis (Monthly Report, Expenses by Category, Service Profitability) · Ledger (General Ledger) — and a **More** menu for the overlapping ones: Account Ledger / Account Statement (same journal lines of ONE account as the General Ledger), Account Balance (the trial balance again), Journal Entries (= Accounts → Manage Journals). Nothing was removed: every route, permission and drill-down link (the statements link to the Account Ledger) is unchanged. To drop a duplicate for good later, remove it from `$reportTabItems` and decide who loses a bookmark.
- **Report menu is now two-level (owner's idea, 2026-10-08)**: four MAIN tabs — Statements · Receivables & Payables · Analysis · Ledgers — and a thin sub-row of that group's reports under the active one (Ledgers: General Ledger, Account Ledger, Account Statement, Account Balance, Journal Entries). A main tab opens its group's first report. No "More" menu, nothing removed; same routes, permissions and drill-downs.
- **Expenses by Category — Excel** (owner, 2026-10-08): the accountant's "July-2026 Expense Summary by Category.xlsx" IS this report (same title lines, `account_id | account_name | amount | amount_with_tax`, A→Z, a Total row), so no new report was made — only `expenses-by-category/excel` (`App\Exports\ExpensesByCategoryExport`) beside the CSV button. `account_code` is the ERP chart code; `amount_with_tax` = `amount` because VAT is booked to its own expense accounts. Same scope as the screen (every expense account, so the total ties to the P&L).


- 2026-10-10: Expense entry supports multiple chart expense heads in one payment.
  Keep company/payment source shared; choose a searchable code/name head per cost
  line, group debits by head, and credit the total through the existing settlement
  flow. Legacy lines without a head retain the expense classification's head.
  Owner approved the required migration and explicitly authorised running it.
- 2026-10-10: Ticket route quick-create temporarily needs title only. Owner
  requested defaults from the most common existing complete route: Gulf Air
  (GF), DAC to ECN. Editable UI and backend use ticket-route-defaults config.
  Do not treat these defaults as airports inferred from a route title. Owner
  plans to make details required again later.
- 2026-10-10: Employee ledger advance rows need Reverse for erroneous/duplicate
  advance postings when the real payment was already recorded as salary payment.
  Require a reason, preserve the original journal, post a linked opposite journal,
  stop future recovery, block payroll-recovered advances and repeat reversals.
  This is not a cash refund or conversion; leave the correct salary payment alone.
- 2026-10-10: Expense payment source uses one compact Paid From dropdown instead
  of three large cards. Cash/petty cash, bank and own-pocket reimbursement retain
  their existing posting and permission rules; accounts are manually selected.
- 2026-10-11: Dashboard payment schedules use three distinct action windows:
  Today (today only), Overdue (all open schedules before today), and Upcoming
  7 Days (tomorrow through seven days ahead). Overdue must stay visible with a
  count but must not be mixed into Today; both the main and company dashboards
  follow the same rule so missed obligations never disappear.
- 2026-10-11: Dashboard Payment History has its own Today / Last 7 Days / Last
  30 Days / All Paid filter. It must not inherit the open-schedule tab because
  selecting Overdue previously made reconciled historical payments look absent.
### Pint CI failures: required local formatting check (owner, 2026-10-18)

Before handing off or committing PHP changes, run `php vendor/bin/pint` on the
task's changed files, then `php vendor/bin/pint --test` on those same files.
Before a PR, match CI's `origin/main...HEAD` added/modified PHP-file scope,
excluding `database/migrations/` (Blade files are included). Include outstanding
task edits too. Never format the whole legacy repository. PHP lint, unit tests
and `view:cache` do not replace this check. Review the formatting diff and do not
disable CI to get a green result. Root `AGENTS.md` contains the PowerShell recipe.
This rule follows a failure in PartyLedgerService / PartyNotifier formatting.

### Agent package includes tickets (owner, 2026-10-18)

Ticket-only: bill the agent for that ticket. Flight package: bill the package
once, including tickets, immigration, manpower and other services. A linked
ticket sold to the SAME agent is included, never an additional agent charge.
`FlightPackageTicketBilling` appends `ticket_package_inclusion` party/GL
adjustments against ticket revenue/AR; never changes vendor cost or cash paid.
Unlinking restores a standalone ticket charge. Repeated saves reconcile only
the difference. Ticket invoice due and receipt schedules exclude included value;
inclusion credits are NOT cash receipts. Package price is entered by the operator,
not automatically increased by ticket sale price. Existing invoices remain in
the audit trail; link/edit reconciles them, there is no automatic bulk backfill.
Local integration check: `php tests/integration/flight-package-ticket-billing.php`
uses a disposable MySQL database with cloned schemas. Its connection config MUST
override both `database` AND `name`; verify hydrated model/relationship connections
before mutations. Otherwise a copied `name=mysql` can reconnect models to live DB.

### Shared party ledger appearance across departments (owner, 2026-10-18)

Ticket/Flight/Visa Accounts embed Party Statement's same ledger view, service,
header, toolbar and pagination. Do not create separate department ledger styles.
The shared statement partial marks the `shared-party-ledger` Blade section;
layout/app skips the department-wide automatic Ticketing dropdown enhancer on
these pages. That enhancer converted the compact Active dropdown into a 100%-wide
Select2 control and broke the shared toolbar. Keep ledger control ownership in
the shared partials when adding new embedded entry points.

### Print/PDF omits entry mistakes (owner, 2026-10-18)

Party Statement print/PDF must use PartyLedgerService::activeRows, like shared
statements, before applying selected entry IDs. Hide both sides of complete
cancelled pairs (including duplicate-posting corrections), keep the valid payment,
and retain unmatched period-edge adjustments so balances stay accurate. No data
is deleted; reversal history remains available on screen.

### Construction expense-first workspace (owner, 2026-10-18)

The daily task is entering expenses and viewing their list. Opening a project
should land on Expenses. Client due sheets belong in Client Collections;
supplier dues belong in Supplier Payables. Cost by head, who paid, cost by month
and journals belong in Reports. Contextual tabs must render only their own
content, rather than linking to anchors in one long financial sheet.

Construction project expense printing must match the Main Expense print modal's
date choices and centered layout, and reuse the same A4 statement template
(owner correction, 2026-10-18). A small custom preview dialog is insufficient.

### Agent/customer invoice discounts (owner, 2026-10-18)

Give Discount is a non-cash credit note, never Record Payment/Return Advance.
Super-admin can discount a posted Ticket sale, Flight passenger package or Custom
invoice up to its remaining due. Journal: Dr 4195 Sales Discounts & Allowances
(shared debit-balance contra-revenue), Cr 1311 Receivable with party. New table
party_discounts stores invoice link, reason, author, unique request key and
reversal audit. Payment amounts, bank balances and vendor costs never change.
Reverse adds the opposite journal/party row; complete reversed pairs are hidden
by the existing active/shared-statement service. Discounts are not receipts.
Invoice price/party/company/cancellation/deletion must reverse discounts first.
Single receivable schedule is supported; multiple instalments require consolidation
before applying a credit note. Tax recalculation, commission and bad-debt write-off
are outside this feature. Run the 2026_10_18_200000 migration before using the UI.
Regression: tests/integration/party-discounts.php uses a disposable MySQL database,
disables notices and verifies every model connection before any fixture write.

- Owner (2026-10-18): Give Discount has **no amount limit** (any amount, even above the invoice due or the
  party's balance) and **no confirm() / alert()** — Swal like Record Payment. Super admin only, agents and
  customers only. **Overall discount** = one amount spread over the party's unpaid invoices oldest-first
  (`PartyDiscountService::postOverall()`), one `party_discounts` row per invoice sharing `batch_key`; any
  remainder stays as party credit (`document_type = party_account`, `charge_transaction_id = 0`). Reversed
  as a whole batch. Needs migration `2026_10_18_210000_add_batch_key_to_party_discounts`.
### Schedule bank transaction corrections (owner, 2026-10-19)

Payment Schedule settlements in the bank ledger need the same correction
workspace as deposits/withdrawals: searchable party selection in Details and
principal / bank charge correction. Standalone ad-hoc schedule corrections add
balanced adjustment journals and party-ledger rows, preserve original postings,
and synchronize paid_amount with the actual principal. Require an audit reason
and typed CONFIRM for money changes. Invoice and payroll allocations remain
owned by their source modules; do not bypass their allocation rules.

- Owner correction (2026-10-19): a manual bank deposit/withdrawal later linked
  directly to a party uses `party_payment` with `invoice_id = journal id`; this
  is beneficiary classification, not invoice allocation. Its Amount & Posting
  must stay editable and synchronize that party row's principal and running
  balance with the bank/journal correction.
- Legacy schedules may carry one company id while their journal's selected bank belongs to another group
  company. Resolve the bank from the journal account globally (the account link is authoritative), but allow
  this cross-company repair only to a Super Admin; ordinary users stay within their company boundary.
### Custom bank purpose appears only for Others — (owner, 2026-10-19)

In the bank Cash In / Cash Out modal, Purpose remains a controlled list. Selecting **Others** reveals a
required "Specify purpose" field; its typed text, rather than the generic word "Others", is saved as the
journal purpose. Changing back to a preset hides and clears the custom field so it cannot leak into another
transaction. This applies to both directions because they share the transaction modal.

### Sold ticket route edits must reach the agent ledger — (owner, 2026-10-19)

The ticket-sale ledger is linked live through `ticket_sale_items → ticket_purchases → tickets`; no duplicate
route should be stored on the transaction. Its screen, expanded Details, print and email must render the
current ticket route title. Reading only `from_airport → to_airport` loses the return half of a same-ticket
round trip (for example, edited `ECN-DAC-ECN` incorrectly appeared as `ECN → DAC`).

### Posted ticket refunds need an audited correction — (owner, 2026-10-19)

Ticket Orders → a refunded Ticketing ticket exposes **Correct refund** for the agent refund/credit, airline
penalty bearer and our service charge. It requires a reason plus typed `CONFIRM`. Never rewrite or delete the
old agent/journal postings: add balanced component deltas, update the refund record's current totals, and add
a `refund_correction` history row. Example R2LXWU (b): agent refund 170,000 → 142,000; airline-kept penalty
25,813 changes from company-borne to agent-borne; mistaken service fee 142,000 → 0.
- Owner UI correction: do not use SweetAlert's loose default form layout. Match the bank/party correction
  workspace: left-aligned identity header, three read-only money cards, one bordered two-column editing panel,
  amber audit-reason panel, and a separate dashed CONFIRM block with a footer action bar.
- Owner ledger correction: delta transactions must sit beside the original component, not at today's top of
  the ledger. Use the original sale-reversal date for an agent-refund delta and the original change-charge date
  for penalty/service-fee deltas. Copy the original component's posting time too, so other same-day entries do
  not split the pair; keep `created_at` current so the audit still says when the correction occurred.
- Owner accounting correction (2026-10-19): when the entered agent refund is already the airline's net refund,
  the airline-kept penalty is informational and must not be debited to the agent again. Example: agent sale
  170,000 minus refund 142,000 already leaves 28,000; adding the kept 25,813 again is a double charge.

### Contract-file wrong-entry deletion — (owner, 2026-10-19)

Expose the permission-gated delete action in the current File & Passenger Ledger, matching Contract Flight's
compact row action. Deletion is only for a wrong file entry: reverse its outstanding client/vendor postings and
move it to trash. Refuse deletion once refund, cancellation, re-processing or other financial-change history
exists; those records must remain visible and be corrected through their financial workflow.
- Owner confirmation correction: deletion is never a direct one-click action. Match bank reversal: require an
  audit reason and exact typed `CONFIRM`, enforce both again on the server, and persist the reason in the event.

### Corrected ticket refunds are one reconciled statement event — (owner, 2026-10-24)

Keep the append-only journal/reversal/correction postings for audit, but do not make an agent add those raw
rows to understand a refund. The active agent statement and its PDF show one net **Ticket refund** row. Its
details reconcile the full original agent price as `refund to agent + retained/deduction = original agent
price` (MD YASIN: `42,000 + 127,500 = 169,500`). Only collapse a complete posting set; a period/type filter
that contains only part of the event must keep the raw rows so the displayed balance cannot become false.

### Discounts preserve paid schedule history — (owner, 2026-10-24)

An invoice may have several historical paid schedules and one open remainder. That is not an ambiguous
multi-instalment discount: leave every paid schedule untouched and reduce only the single open schedule.
Refuse the discount only when more than one genuinely open schedule would make allocation ambiguous.

### Discount corrections include posting date and time — (owner, 2026-10-24)

The discount row's pencil uses the same correction discipline and two-step UI as Manage Bank: first show
the read-only summary and current balanced journal, then offer Date & time and Reverse as correction cards.
Keep one fixed modal shell open while switching between overview, Date & time, and Reverse panels; Back
returns to the overview inside that modal instead of closing and reopening SweetAlert.
The modal header (workspace label, reference, party and status) is permanent like the Manage Bank pencil
workspace; only the content below it switches between the overview and correction forms.
Date & time requires a reason and exact `CONFIRM`, and writes audit rows. Move the discount
record, every active allocation transaction in an overall batch, and every linked journal together; never
move only the visible party row and leave its journal on another posting date.

### Active party transactions exclude voided and reversed rows — (owner, 2026-10-25)

The default Active transactions view shows only live accounting activity. Zero-value void rows and entries
whose movement is classified as Reversed stay out of Active, but remain available under Reversed/All and
in reversal history; filtering is presentation only and never deletes or rewrites ledger history.

### Main Expense is operating-expense only; other heads use Payment Entry — (owner, 2026-10-25)

The ERP Expense desk accepts only the owner's named Operating Expense heads (including their named detail
heads). Other chart-of-accounts expenses are posted from Accounting > Payment Entry. Payment Entry has
Payment In and Payment Out, selects any bank/card/cash account and any active contra account, and calls the
same bank transaction posting engine as the selected account's own Deposit/Withdraw buttons. Both entry
doors must therefore keep identical journals, balance checks, charges and reversal behaviour.

Owner correction: Payment Entry is a standalone item under the main Accounts menu, not a Bank tab and not
a second nested Bank-menu item. The form may select a bank/card/cash account, but the workspace represents
the central accounting entry point; Manage Banks remains the account-management and per-bank workflow.
The selected money account determines Company (shown read-only). Department is required only for Payment
Out to an expense account and is stored on that expense journal line; Payment In and non-expense balance
entries carry no department. Payment Entry's account and department dropdowns are searchable Select2 fields.
The central Payment Entry must retain the individual bank Deposit/Withdraw form's full accounting detail:
searchable agent/vendor/customer, party movement type, advance or invoice allocation, paid/received-via bank
or cheque details, and separate Bank Charge and VAT amounts with their own posting accounts. Central and
per-bank entry are two doors into the same posting engine, never reduced journal-only and full-detail versions.
Owner correction for the CA-operated central desk: Payment Entry does not choose a service invoice/refund,
party movement type or allocation mode, and selecting a party must never replace the accountant's contra
account. Party is optional identity metadata; AR/AP or advance control heads still create the connected
unallocated party-ledger row, while any other manually chosen head remains exactly as selected.
The CA desk uses one free-text Purpose / Remarks narration, not Ticket/Flight/Visa/File operational choices.
Reference No. is pre-generated exactly like the bank form (`BANK-DEPOSIT-*` / `BANK-WITHDRAW-*`) but remains
editable; switching direction refreshes it only while it is still an automatically generated reference.
Payment Entry keeps a sticky right-side **Posting overview**, like Bank Transfer: live money flow, exact debit/credit rows including charge and VAT, balanced totals, company/reference/purpose, and the selected money account's projected balance. It is guidance only; Review and Confirm remain the posting gate.

### Recurring monthly expenses (owner, 2026-10-25)

- Fixed monthly costs belong under the Main Expense desk as **Recurring Expenses**, not Payment Entry. Payment Entry is for one-off accountant-controlled money movements; recurring schedules define operational expenses.
- One schedule can carry multiple category/sub-category lines and amounts. It owns company, expense department, payment method, optional bank, start/end month and generation day (default 1).
- The scheduler runs daily at 01:10 so a missed first-of-month cron catches up. A unique schedule + month register makes retries idempotent.
- Generation creates a **pending expense draft** dated on the first day of that month. It never auto-approves or withdraws bank/cash; the normal Expense approval performs the accounting posting after review.
- Editing/stopping a schedule never rewrites or deletes expenses already generated from it.
- Owner correction: generation day/start/end are system concerns, not form choices. Every active schedule runs on day 1, starts in the current month and continues until paused/stopped. Creating an active schedule mid-month immediately creates that current month's pending draft (for example, an October rent schedule created on 25 October still counts October); the unique month register keeps the later cron run from duplicating it.
- A separate schedule-name field is redundant. The server names the schedule from its first sub-category/category (for example `Office Rent`), adding `+ N more` when it contains several lines.
- Recurring Expenses opens New/Edit in one large modal; the page itself leads with the schedule list and a single `+ New recurring expense` action. The modal closes only from its × control and reopens automatically when server validation returns an error.

### Party payment defaults to overall advance — (owner, 2026-10-25)

- The party-statement payment modal now defaults to **Overall Advance / Unallocated Payment**. This keeps a general vendor payment as one supplier-advance ledger movement and leaves individual invoice/ticket dues unchanged. Auto/manual invoice allocation remains available only when the accountant deliberately selects it.
- This is posting behavior, not a schema change; do not repair already allocated payments with raw SQL because their journal, bank cache, party ledger, and source invoice paid/due values must be reversed together through an audited workflow.
- Owner requested a one-off SQL correction for verified reference `RCT-2026-0113` (vendor 175, 15 ticket allocations, total 220,500) as copy/paste SQL rather than a repository file. The correction is guarded by those exact facts, snapshots every affected table, restores ticket/schedule dues, consolidates the party rows, and reclassifies the journal from payable 2111 to supplier advance 1456 without moving bank/cash again.

### Custom invoice resolves its company from the party — (owner, 2026-10-25)

- Super Admin has no fixed `company_id`, and the group-wide Party Statement can have no `?company=` scope. Custom invoice creation therefore resolves a missing company from `company_party` when the selected agent/customer has exactly one company assignment. Multiple assignments require an explicit company selection; missing/stale assignments return readable 422 JSON instead of leaking `Company::findOrFail(null)` as a model-not-found error.

### Cancelled ticket vendor correction moves the complete audit chain — (owner, 2026-10-26)

- An already-cancelled ticket may correct its vendor and purchase cost from Ticket Orders. A vendor correction moves the purchase/refund party-ledger rows, purchase/cancellation journal beneficiary and vendor-tagged journal lines, and any payable schedule, then rebuilds both vendor balances; unrelated flight service costs remain untouched. A cost correction updates the original purchase ledger/journal but preserves the already-posted airline refund, so the residual remains the penalty; cost cannot be reduced below that refund (correct the refund first).

### Employee Accounts ledger displays newest activity first — (owner, 2026-10-28)

- The employee Accounts transaction table is a working history, so show rows in descending transaction date/id order. Calculate every historical running balance in chronological order first, then reverse only the filtered display collection before pagination; otherwise the Net Due beside an older row becomes incorrect.

### Active employee-ledger balances exclude hidden reversals — RAFI (owner, 2026-10-28)

- In the Active employee-ledger view, hiding reversed originals and reversal audit rows is not enough: rebuild every displayed running balance from that same active-only collection. A hidden reversed payment must never temporarily appear as an employee advance. Keep the complete reversal pair intact for Reversal history and audit; this is a display calculation correction, not a SQL data rewrite.

### Reversal history uses an icon-only filter action — RAFI (owner, 2026-10-28)

- Beside the employee-ledger Active transactions selector, keep Reversal history as one compact icon-only action. Preserve its meaning and count in the tooltip/accessible label instead of widening the filter bar with visible text.

### Construction financial workspace modernization — (RAFI, 2026-10-28)

- The new reference calls for opening a Construction project on Overview, with compact financial KPIs, a project switcher and horizontal tabs. This supersedes the earlier expense-first landing preference for the Construction project-name link; the Expenses tab and existing expense URL remain available.
- Preserve the Expense Department project identity, company isolation and existing posting workflows. Never borrow a general Project's manager/team/tasks by matching names or IDs, or invent physical progress, budgets or financial forecasts. Clearly distinguish recorded statements from posted books and Expense-desk cost from all-source GL cost.

### Main Construction overview must visibly change — (RAFI, 2026-10-28)

- The owner opens `/construction/accounts/overview` from Project Accounts. Modernizing only the project-specific summary leaves their landing screen unchanged. Apply the compact financial dashboard to this company overview too, and make each project name open its project Overview rather than Expenses.

### Construction list uses the compact screenshot — (RAFI, 2026-10-28)

- Match the supplied Projects List reference with four compact count cards, a white page header, a small filter row, tight table rows and page size/pagination in the footer. Keep contract/received portfolio total cards on the financial overview. Do not invent In Progress/On Hold/Completed states or manager/physical-progress values before the project register supports them.

### Project Accounts opens the list first — (RAFI, 2026-10-28)

- Approved flow: Project Accounts → All Projects → click a project name → that project's Overview dashboard. Keep the combined company dashboard as a separate, second tab named Overall Overview. The sidebar and sectionless Construction URL must land on the list; Wood Art keeps its existing default.

### Only project tabs inside a project — (RAFI, 2026-10-28)

- Remove the redundant company-level Financial/Overall Overview, All Projects, All Expenses, Client Receivables and Supplier Payables tab band from specific Construction project screens. Keep their contextual project tabs and the existing All Projects return links. Company-level pages still show the company navigation; other companies retain their existing layout.

### Keep the Construction portfolio menu to two tabs — (RAFI, 2026-10-28)

- Remove All Expenses, Client Receivables and Supplier Payables from the company-level Construction tab band. Keep only All Projects and Overall Overview. Combined financial reports remain accessible from Overall Overview; each project's contextual financial tabs stay inside that project.

### Project information and team must be discoverable — (RAFI, 2026-10-29)

- Put Project Information and Team Information beside Overview in the Construction project tabs. Show the existing project's description, company, client and contract in Information. Team must distinguish assigned members (no supported assignment relation yet) from people with site claims/funds; financial participation is not proof of a project role. Do not silently treat a claimant as project manager or assigned staff.

### Contract flight dashboard separates overdue and exception work ? RAFI (owner, 2026-10-29)

- The first flight table shows active/upcoming flights only. Past departures whose physical progress has not been updated belong in a similar Overdue table, followed by Flight Missed and Offloaded / Deported; keep departed history separate.
- Each table needs Today, Tomorrow, Yesterday, custom departure dates and Print. Flight reports reuse the bank/party statement format, including the company header and signature.

### Contract flight tables stay compact and descending - RAFI (owner, 2026-10-29)

- Every flight dashboard section needs independent pagination with 5 or 10 flights per page (default 5). All sections start by departure descending, newest first, and column sorts stay descending. Printing continues to include the complete filtered result.

### Active flights include today and upcoming together - RAFI (owner, 2026-10-29)

- Default the active/upcoming flight view to today AND all future departure dates in the same table, without selecting a separate filter. Keep the start date at today and the end date open. Put search, agent, date filters and Print directly below the Active / Upcoming Flights heading; choosing an agent filters the table in place.
- Dress that active filter in the same compact `fdl-bar` used by Overdue: one-line search, quick dates, typed custom range with Apply, and Print. Keep the default All dates state meaning today onward for this operational table.

### Flight-table dates use readable month names - RAFI (owner, 2026-10-29)

- In every Flight Dashboard table, show departure dates as day + short month + year (`3 Dec 2026`) with the time underneath. Use the same format in the corresponding printed flight list; date-entry filters remain numeric.

### Flight statements include the actual passengers - RAFI (owner, 2026-10-29)

- A Flight Dashboard print cannot stop at a passenger count. After its flight summary, list the passengers grouped by flight with name, passport, agent, ticket progress, and current stage/exception. This is essential on Overdue because the print must identify whose operational status still needs updating.
- Remove the separate Flight Missed dashboard table. Every remaining dashboard Print button opens the same configurable **Print Contract Flight** modal used by Flights & Passengers, populated with that section's complete filtered passenger list (not only Overdue and not only the current pagination page).

### Flight Dashboard section headings use quiet status colors - RAFI (owner, 2026-10-29)

- Make Active, Overdue, Offloaded / Deported and Departed easy to distinguish with the same soft indigo, amber, slate and sky palette used by Flights & Passengers. Tint the heading strip and text with a slim matching edge; avoid saturated blocks or aggressive colors.

### Contract Flight printing stays statement-dense - RAFI (owner, 2026-10-29)

- The shared Contract Flight print used by Flights & Passengers and every Flight Dashboard section must reuse the actual Bank Statement document language, not merely make a plain table smaller: centered A4 card, EPAL logo/company masthead, pale blue title band, scope/generated metadata, bordered summary strip, indigo striped ledger and formal signature area. Keep all selected passenger information.
- Passenger-detailed Contract Flight reports are wide documents: when six or more columns are selected, default the print designer to landscape and give Passenger, Agent, Ticket, Route and financial columns purpose-specific widths. Equal-width portrait columns make names and financial breakdowns unreadable and are not an acceptable Bank Statement match.
- Keep the Authorized Signatory footer small and right-aligned exactly like the Bank Statement, with a short line and `For, [Company]` beneath it; never center this block under the Contract Flight ledger.
- Finish the Contract Flight print like the Bank Statement: a full-width indigo closing strip with clearly labelled financial totals (or record totals when that section has no financial columns), followed by a muted generated-sheet note on the left and the compact Authorized Signatory on the right.
- Contract Flight is a Travels statement even when a user from another group concern opens it. Its shared print masthead must always use the EPAL TRAVELS & CONSULTANCY company record (travel logo, full Khilgaon address, travel phone and email), with the Bank Statement's left-logo/right-company alignment and a compact metadata table pinned to the right of the scope row.
- Contract Flight is also Travels-scoped operationally, not only in branding. Preserve `company=2` across its tab band, populate its agent/vendor selectors from Travels' `company_party` assignments, use only Travels banks and staff recipients, and post its payment schedules/journals to company 2 even when the operator is a group Super Admin.
- Contract Flight printing must open a real named print-preview page like Party Statement, never an `about:blank` document. Transfer the configured sheet with a short-lived one-use browser token, retain the Bank-style page controls, and invoke print only after that routed page, its logo and fonts are ready.
- The routed Contract Flight print preview must not leave a separate “Preparing…” card above the statement. Open directly into the document; show a message only when its one-use token is missing or expired.
- Active-flight cards are a next-flight rail, so order them chronologically nearest-first (November before December); the data tables keep their required descending order. Every dashboard flight table needs its own visible Status column with section-aware labels, so staff can see Open, Boarding, Update Required, Offloaded/Deported, Departed or Case Closed without opening the record.
- Flight Dashboard filtering has two deliberate levels: one clearly labelled Overall Dashboard Filter above the cards applies search, operational section, agent and departure dates to the cards and every table; each table's existing bar then narrows only that section. Local Active/Upcoming filtering must never silently change the card rail, and section Print uses the final intersection of overall and local filters.
- Keep the Overall Filter as one compact desktop row (responsive wrapping only on narrower screens). Agent belongs only in this overall row; do not repeat an Agent selector inside Active/Upcoming, whose local controls are search and departure date.
- Do not put an operational Status selector in the Overall Filter: the dashboard's four sections and visible row Status column already provide that distinction. Keep only the useful cross-section controls, and force the overall Agent select to the exact compact height and visual rhythm of the adjacent date buttons.
- In the one-row Overall Filter, search owns the flexible/wide area and Agent stays in a small fixed-width wrapper. Use a contained custom chevron rather than the browser's drifting select arrow so the dropdown control remains visibly inside its border.
- The compact Agent control must be visibly identical in height to search, quick-date and action controls in the rendered dashboard, not only in its nominal CSS. Override shared/native select sizing by the field ID and constrain any injected Select2 wrapper so it cannot grow outside the one-row filter.
- Do not render a custom chevron on top of the native select arrow. The Overall Agent field uses one visible shell with a vertically centred label and a single chevron; the transparent native select supplies only the interaction.
- The compact Overall Agent trigger and its opened menu have different jobs: keep the trigger narrow in the one-row filter, but open a wider anchored Select2 list like the previous dashboard control so full agent names are readable instead of truncating every option to the trigger width.
- The Overall Agent dropdown must stack above the flight card rail and every dashboard panel. Its closed label must use true flex centering rather than a line-height approximation, so the text stays vertically centred across browsers.
- Keep the opened Overall Agent menu flush beneath its trigger. Selection-container sizing rules must target only the Select2 sibling generated for the closed field; applying relative positioning to Select2's separate dropdown container creates a second control-height gap.
- Improve dashboard table recognition with restrained section palettes rather than saturated rows: Active uses indigo, Overdue amber, Offloaded/Deported slate with rose status, and Departed sky/green. Carry each palette into the column header, very light alternating rows, a slim left row edge, matching status badges and a soft hover; keep the three financial columns on a neutral white surface for number readability.
- Contract Flight CI must keep Larastan green as the dashboard expands. Import dashboard services from `App\Services` explicitly and declare Eloquent relation return types for Contract Flight passengers/creator and passenger flight/passport-holder relations; do not repair new relation errors by increasing baseline occurrence counts.
- The Overall Filter must never overflow its dashboard container or paint over the account/profile menu. Let its flex controls wrap whenever their real available space runs out (including browser zoom and narrow sidebars), give the search/date groups mobile fallbacks, and do not raise the entire filter panel's z-index just to lift the Agent dropdown.
- Bank Statement corrections must refresh the current account in place without resetting navigation. If an admin corrects date/time while reviewing page 5, reload the fresh statement data but preserve page 5, the current filters/sort and the exact window scroll position; only an intentional account or period change starts at page 1.
- The Active/Upcoming card rail is airline-based, not flight-based: show one aggregate card per airline. Opening that card keeps the full blue flight header, merges passengers from every matching flight for the airline, and adds Departure date/time as the first column only in this grouped airline view so each passenger's flight remains identifiable. A normal single-flight ledger keeps its original columns.
- Passenger journey strips in the dashboard ledger start collapsed and open per passenger only when the main row is clicked. Keep Current Status visible in a compact main-row column so staff do not need to expand the journey merely to identify the passenger's stage.
- In the grouped airline passenger ledger, order rows by departure date/time descending and make the current-stage badge clearly noticeable with a restrained indigo tint, border and dot rather than an aggressive solid color.
- Airline cards are operational summaries: show airline name, total upcoming flights/passengers, a clearly labelled Next departure and the Latest departure instead of an ambiguous standalone date. The expanded blue header is also airline-level, showing flight count and departure range; hide single-flight Edit/Board actions there and provide View flights, while passenger rows retain their own flight/date identity.
- The passenger-ledger filter is context-aware: airline groups filter dates by flight departure, while a single flight filters by ticket purchase date and labels that distinction. Keep All/Ticketed/No PNR/Flagged and one Reset action. “Show all stages” controls journey expansion. Airline Print uses the currently filtered aggregate passengers, not the representative flight invoice.
- Airline summaries must handle mixed operational data without borrowing one representative flight's identity. Show a common route directly, otherwise a route count with the complete list in its tooltip; show one agent/vendor by name or aggregate counts with list-level links; show one shared status or “Mixed status” with counts. Exact batch, route, party and status details remain on each passenger row and in View flights.
- Do not add an All stages selector to the passenger-ledger filter. The visible Status column already provides stage visibility; keep the filter row to search, All/Ticketed/No PNR/Flagged, contextual dates, Apply, Reset and Print.

### Ticket View All is group-wide — RAFI (owner, 2026-10-31)

- `view all ticket purchase` / `view all ticket direct sale` must show ticket records across every company in the group, including to an accountant whose own company is EPAL GROUP. Plain `view` stays restricted to the user's company; a query-string company value must not bypass that restriction.

### Travel operations always belong to EPAL Travels - RAFI (owner, 2026-10-31)

- Ticketing, Contract Flight, Visa and Contract File records and all of their schedules, ledgers and journals always use EPAL TRAVELS & CONSULTANCY (company 2), regardless of the operator's home company. An EPAL IT user may enter the work, but remains only the `created_by` / audit user; their company must never become the operational record owner.
- Ticket selectors and Contract Flight linking expose and accept only company 2 tickets. Reject a cross-company ticket before baggage or accounting work starts, with a clear ownership error.

### Main Expense is only for operating costs - RAFI (owner, 2026-10-31)

- The Accounts > Expenses entry form is the Main Expense desk and must show only company-wide operating-cost departments (`description = Overall Cost`). Project cost centres such as Munshi Villa belong to their project accounts and must not appear or be accepted on Main Expense.
- Expense entry modals scroll only their content body. Keep the teal header and action footer fixed inside the modal, and lock the page behind it so an outside browser scrollbar does not appear.
- Main Expense cost lines use the same single searchable `Expense Head / Category` picker as project expense entry: category fallback and sub-category choices are grouped by category and show the resolved journal code. One selection fills the stored category, sub-category and journal account together; do not make staff coordinate three separate dropdowns.

### Project expenses record the actual payment account - RAFI (owner, 2026-10-31)

- A project expense paid directly by the company must ask which company-owned bank/cash account funded it. Keep this as a row-level settlement choice beside Own pocket and Company advance; the selected account must be the one credited in the journal, not display-only metadata.
- The project Expense Entry workspace uses the compact card composition from the approved reference: Basic Information, Payment Details and Approval Workflow above an Items / Details grid, with a sticky totals/review rail. Preserve real ERP fields and posting behavior; do not add decorative inputs that are not saved.
- The refined New Expense composition starts with the selected-project ribbon and Back to Project, then numbered Basic Information and Expense Items & Payment Details sections. The right rail contains live totals, the real review/post workflow and recent expenses from that project. Reference-only Supplier, Tax/Withholding and Attachment controls remain absent until their values can survive review and post through the existing Expense desk.
- New Expense must not fall back to the old bulk spreadsheet appearance: hide the company-level All Projects / Overall Overview band, start with one primary expense line, and render each line as labelled form fields (date, head/category, description, payer/account, payment source and amount). Additional lines appear only when Add Item is used.
- Match the approved single-entry hierarchy visibly: show a New Expense Entry heading, separate Quantity & Rate from Payment Details, calculate total amount from the saved quantity and unit price, and keep summary, approval workflow and recent project expenses in the right rail.

### Construction project screens share one compact visual system - RAFI (owner, 2026-10-31)

- The Construction Projects list follows the approved portfolio reference: no redundant company-level tab strip; a compact header, seven real project/financial KPI cards, one-line filters, and a dense bordered table with selection, row number, project identity, status, financial columns and compact action. Do not invent manager, schedule or physical-progress values when Project Accounts does not store them.
- Construction portfolio metadata is now stored in `project_terms`: project code, manager, start/end dates, progress, construction status and project type. The owner-approved demo seed supplies 12 idempotent construction projects so the list can reproduce the full reference table; these fields must remain database-backed and filterable rather than display-only placeholders.
- New Project uses a dedicated full-page construction workflow matching the approved four-step reference. Basic information, financial configuration, description/tags/notes, project settings, team assignments and uploaded documents all persist through `project_terms`; the right-side preview is live and the footer stays fixed. Do not send this action back to the generic Expense Department setup screen.
- Specific-project Overview, Project Information and Cost Analysis should closely follow the approved reference: a compact project header and underline tabs; Overview uses two KPI rows plus progress, approved-cost distribution and recent activity; Information uses a wide basic-information card with dates/details beside it; Cost Analysis uses a dense category table followed by comparison and distribution visuals. Missing project dates, roles, images, budgets or progress must be labelled unavailable rather than invented.
- The updated specific-project Overview removes the separate green suite heading. Its compact header carries the project switcher, circular assigned-team avatars, construction status, dates/progress and New Expense / Record Collection / More actions. Below it, keep six summary cards, project information, client progress, cost donut/categories, assigned team and three recent-transaction tables in the approved dashboard structure.
- Expense Register, Client Collections and Supplier Payables continue the same visual system: compact filter/action area, three truthful financial summary cards where applicable, dense tables, status badges and minimal row actions. Preserve the real posting/reversal controls; do not add filter controls that the server does not actually apply.
- The same project tab band also exposes Budget & Profitability, Team & Tasks, Timeline & Milestones, Materials, Documents and Activity in the reference's compact table/card style. Where the construction cost-centre schema has no verified tasks, milestones, quantities, documents or budgets, show an explicit unavailable/empty state rather than demo records; Budget may show approved actual cost, and Activity may show verified Expense-desk events only.
- Every specific-project screen, including New Expense Entry, uses the Overview project header and tab band unchanged: breadcrumb/switcher, client and internal ID, team, construction status, start/end dates, progress, New Expense, Record Collection, More, and the complete project menu. Do not introduce page-specific duplicate project ribbons or alternate headers.

### New Expense uses the seven-card reference hierarchy - RAFI (owner, 2026-11-01)

- The project New Expense workspace visibly follows the approved seven-part composition: Basic Information, Quantity & Rate, Payment Details, Tax / Withholding, Attachment, Notes and Expense Items. Each section has its own pale teal header card and compact, consistently styled fields; totals, approval workflow and recent similar expenses remain in the right rail.
- Keep every existing real posting field and the row-level company bank/cash selector working. Until tax, withholding and attachments are supported by the review/post payload, their cards must state that limitation rather than presenting decorative inputs that appear to save.
- Company Bank follows the Main Expense payment-source rules: choose Payment Method first, show only matching bank/mobile-wallet accounts, and credit the selected account in the journal. An optional bank charge is added to that account's deduction and debited to the selected charge account; default to chart code 8510 Bank Charges when it exists. Carry method, bank and charge through review/edit/post rather than treating them as display-only fields.
- Main Expense continues to reject project cost centres. The shared ExpenseController accepts one narrow exception only for an internal request attribute set by the Project Expense poster, a supported project company (Construction 3 or Interiors 6), and a department verified in `project_terms`; browser input cannot opt into that exception.
- Construction has no dedicated bank account today. A Project Expense user with group-wide expense access can therefore choose any active group bank/money account, exactly like Main Expense; the account label must show its owning company. Ordinary company-scoped expense users remain restricted by the shared ExpenseController. When another company's bank pays, inter-company liability equals the full bank deduction (expense plus bank charge), not only the base expense.
- New Expense has one Basic Information card, not a separate project card plus another identically named card. Its real fields are Project, Expense Date, readonly auto Voucher No, broad Expense Head, filtered specific Expense Category, Supplier / Vendor and Description. The voucher becomes `expenses.reference`; Supplier / Vendor becomes `expenses.reference_user_name`, while the actual payer remains independently recorded by bank, float or reimbursement relation.

### AI design skills + DESIGN.md workflow - RAFI (owner, 2026-11-01)

- Frontend design tooling is set up for Claude Code and Codex: skills `impeccable`, `design-taste-frontend` (Taste Skill) and `web-design-guidelines`, the Playwright MCP (`.mcp.json`), and the design tokens in `DESIGN.md` (project root, taken from the existing Wood Art / Construction CSS).
- **Setup on a new machine (each person once):** pull, then run `npx skills experimental_install` in the project root (restores the skills from `skills-lock.json`; `.agents/` and `.claude/` are gitignored so the skill files never travel through git), restart Claude Code, approve the `playwright` MCP when asked, and check with `/mcp`. First browser run may need `npx playwright install chromium`. For Codex also add `[mcp_servers.playwright]` (`command = "npx"`, `args = ["@playwright/mcp@latest"]`) to `~/.codex/config.toml`. `.mcp.json` uses `cmd /c npx` (Windows).
- **Scope is the hard part:** `DESIGN.md` and the design skills apply to NEW screens and `Modules/WoodArt/` only. On any other company's existing view run audits as REPORT-ONLY (`web-design-guidelines`, `/impeccable audit`); never `/impeccable polish` or restyle it. This is the Wood Art "no leak into other companies" lock, unchanged.
- **How to ask:** "Read DESIGN.md, then design <screen> with impeccable; `wap-` prefixed CSS, no shared-file edits. Afterwards open the local URL with Playwright, screenshot at 1365x718, fix overflow/overlap." Audit-only: "Audit <file> with web-design-guidelines, report only, edit nothing."
- Playwright QA runs against the local Laragon URL only, never `erp.epal.com.bd`. Add a token to `DESIGN.md` before using a new hard-coded colour.

<!-- ===== END RAFI ===== -->


<!-- ===== IMRAN (Imran Hossain) — add your notes at the bottom of this block ===== -->

### Contract flight tickets = one lane per ticket; the FIRST ticket decides the flight — (owner, 2026-10-08)

Why: a route like DAC-BAH-IST (ticket 1, BAH transit) + IST-ECN (ticket 2) gets re-issued, refunded,
re-bought and re-issued again, each ticket on its own dates — entering that through separate buttons
and Re-assign "messes up the mind". So the passenger card (Flights & Passengers → edit) shows one LANE
per ticket: its live ticket, its own Re-Issue / Refund / (after a refund) New ticket, and its full history.
Lanes never affect each other. **The passenger stays on the flight his FIRST ticket refers to** — lane 1's
live date is his flight's date; later tickets never move him. When lane 1's date leaves his flight's day,
staff are ASKED (never automatic) "Move him to the flight on that date?" — an existing flight on his route
that day, or a new one; a move only, no money. Lanes live on the passenger card in the flight only.
Built: `ContractFlightPassengerController::lanes()` (read-only, from tickets + change rows + ticket_changes).

**Dropdowns = Ticketing's, everywhere in Contract Flight (2026-10-08).** Owner: "pixel to pixel same",
then angry twice — once because route/airline had no flags/logos, once because the copy was "smooth
dreamy" with no separators. The reference is `layout/ticketing-tabs.blade.php`'s "Ticketing dropdowns"
block (hairline between options, 8px crisp edge, indigo left bar on hover, ✓ on selected) — NOT the
ticket entry page's older rounded purple CSS. Every option shows a logo (airline, bank), flags (route)
or, where no logo exists, an icon. Lives in `contract-flights/partials/ticketing-dropdowns.blade.php`.
Lesson: when he says "same as X", find the newest rules X actually renders, not the first CSS you see.

**Lane → New ticket after refund (2026-10-10).** A refunded lane's New ticket button opens one dialog:
pick his new PNR, see new − old, choose who bears the difference (Agent pays / We bear it, never
pre-selected), optional our fee. `laneNewTicket()` links the PNR and writes a `reissue` change row with
`reason = lane_new_ticket` against the refunded ticket — once per refund. Fare difference posts through
the same `postChangeCosts(['fare'])` as Re-assign. Owner decided 2026-10-10: the agent sees the fee and
the fare difference as TWO lines (unlike Ticketing's one). A CHEAPER new ticket: staff choose who takes
the saving — "Agent gets it" = a credit row of the same `contract_flight_fare_difference` type
(Dr 4182 / Cr AR), "We keep it" = nothing posts. `fare_difference` is stored signed (negative = saving).
Also warned (no money): a later ticket departing before the one before it (`clashes` in `lanes()`).

**Refund amount editable later (owner, 2026-10-10):** refunds still post immediately with the amount typed;
"Edit refund" on a lane's Refunded line corrects it BY ADDITION — seller refund-difference row + journal
(`TicketPurchaseController::correctRefundAmount()`), penalty difference on 5171, the agent's line via
`correctChangeCharge()`. Bearer kept; asked only when a full refund turns into a penalty. Single-ticket
Refund rows only; portal-paid tickets refused (wallet correction not built).
Superseded the same day: it also works on a refund that covered several tickets (Cancel from flight /
Re-assign — the change's penalty moves by that one ticket's difference) and on portal-paid tickets (wallet
row + Dr portal / Cr 5110). Owner: "most of the cases we put the amount while doing" — Edit refund is the
exception path.

**Undo (owner, 2026-10-10 "solve all"):** a Re-Issue or a New ticket can be undone from its lane line —
reversing rows beside every posting (seller rows, journals, agent line via `correctChangeCharge()`), the
date moved back, the new PNR unlinked; the change row stays with reason `undone`. Only the LATEST entry
on a ticket. A refund is NOT undone (a cancelled ticket cannot be reopened — owner's rule); Edit refund
fixes its amount. `penaltyTotals()['fees']` now nets the expense heads (5173/5172) so a reversal counts 0.

**Lanes also show:** a booked hold when nothing is purchased; "Covered by PNR" when one through ticket
replaced several legs; a ticket re-issued from another airport matched by its destination. The Tickets
column lists refunded PNRs his change rows name. A flight move shows once, on lane 1.

**Ticket PDF import — partial is fine (2026-10-11):** never refuse a ticket because one part
could not be read. Fill whatever the PDF gave (even only a name, or only a PNR), tell staff what is
missing ("Not in the PDF — enter before saving: route, seller…"), and let them type the rest
before Save. Superseded the same day: nothing is refused any more — any PDF dropped
on the import is read by its labels, and the import **learns** (owner: "like a continuous
learner"): what staff type that the PDF did not give is found in the PDF on Save and its label
remembered, so the next ticket with that label fills itself. Labels only are stored, never
passenger data.

**Ticket PDF import — any layout, learns continuously (owner, 2026-10-11):** "identify PNR,
passenger name, airports, routes … from any kind of PDF, no matter the layout"; "learn after each
import, each entry … saves the learnings in the db". The dev had proposed smalot/pdfparser and
tesseract — owner was told why neither fixes it (text already comes out via pdf.js; the failures
were in understanding the text, not extracting it). Built: `Detector` reads every value by its
TYPE (flight no., date, time, person, 13-digit ticket, passport) and assigns each to the flight leg
printed nearest it; `Lessons` ranks taught label > type > common label, remembers each ticket
DESIGN by its label fingerprint (`ticket_import_layouts`), and on every Save learns what staff
typed, blames the rule behind a value staff corrected (`misses`; more misses than hits = that rule
is dropped on that design) and credits a taught rule that was right (`hits`). Still no AI, no
outside service. Measured: the 7 real layouts read 100% with every dedicated reader switched off.

**Ticket Orders → Change → Refund / Void: penalty is typeable (owner, 2026-10-11).** "Airline keeps
(penalty)" was read-only (cost − refund). Now either box can be typed: penalty → refund = cost −
penalty; refund → penalty follows. Staff often know the airline's penalty, not the refund.

**Dropdowns across all of Travels = Ticketing's, exactly (2026-10-11).** Owner liked Ticketing's
dropdown (hairline between options, purple bar + ✓ on the selected row, airline logo / route flag /
bank logo / country flag / coloured icon on every option) and wants that one style on every Travels
screen — copy it, never redesign it. It lives in ONE partial, `contract-flights/partials/ticketing-dropdowns`,
loaded by Ticketing's tab bar, the Contract Flight pages, and (guarded by Travels route names) the
layout for the rest. A new Travels screen gets it by being in that route list.

**Contract flight route is a label, tickets are per head (owner, 2026-10-13, handwritten note).**
Only the destination country's own airline flies direct; other airlines get there on transit / multi
tickets. So on a multi-leg route the flight's route has no connection with a passenger's tickets:
`tickets_required` is his own (set by the route HIS ticket is issued on, or by hand — never by the
flight's route), and `ticketSlots()` fills slots with his PNRs in travel order, whatever their sectors.

**Penalty vs our charge — three cases, every screen (owner, 2026-10-13).** Vendor always gets the
original penalty only. (1) We bear, no charge → penalty in Company Ledger as Borne, nothing on the agent.
(2) Agent bears, no charge → agent ledger gets the penalty; Company Ledger Borne + Recovered (4181) = 0.
(3) Agent bears + our charge → agent gets ONE line "… penalty" = sum, no breakdown shown to him;
Company Ledger: penalty Recovered (4181), our charge = profit (4183). "Our charge" box sits beside the
penalty on Re-assign (reissue + refund cards) and Ticket Orders → Change (CF tickets too).
Agent's Party Statement / PDF never shows the split: change-row notes are shown without any ৳ segment.
"Who pays" flip on a per-ticket charge moves only the penalty (to a we-bear row, −penalty on 4181); our
charge stays billed to the agent. Corrections on per-ticket rows follow the journal's own 4181/4183 split.

**Party Statement print table = design 19 (owner, 2026-10-13/14, after ~19 rounds).** Header / summary
/ footer stay; only the ledger table changed. What the owner demanded on the way, so a future print
table starts from it: (1) **every row the same shape and height, every cell filled** — a payment and a
passenger service must look alike ("no rows and columns can be empty"); (2) fresh, quiet, not
"polluted": one accent, colour only where it means money, no stripes/badges/zebra, no mono fonts,
small bold text smudges in print — use weight 500; (3) still very informational; (4) Date · Entry ·
Particulars · Flight/Bank · **Debit · Credit separate** · Balance — no Voucher column; (5) PNR **bold
on a yellow highlight**; (6) table header **fully filled** in the theme indigo. Lives in
`party-statement/partials/statement-table/v19.blade.php` (row facts: `App\Support\StatementRows`);
`&design=current` prints the old table. Vendor/trainer rows read Billed / Paid, agents Charged /
Received; balance tag Due / Advance (matches the header). Agent rows never show a ৳ breakdown.
The emailed / WhatsApp-link PDF (dompdf, `pdf-ledger-statement`) carries the same table as
`statement-table/v19-email` — same words via `StatementRows::cell()`; text is measured with dompdf's
font metrics and cut to the column, because dompdf wraps an overflowing line instead of clipping it.
Particulars of a file / visa / ticket / flight-change row = the screen's Details column (owner,
2026-10-14: "show these" — the entry name is already in Entry, the voucher was printed twice): name in
bold, then what · how, from `PartyStatementController::reasonsFor()` (made public for this).

**Sales invoice (owner, 2026-10-14).** Agents need an invoice for their SALES, not only the ledger. The
party card's **Invoice** button now offers two things: (1) *Invoice for sales already made* — picked like
Print (all / date range / ticked rows), one PDF; (2) *New invoice for another service* — the old Create
Invoice (a service we don't list anywhere else; posts a new charge) — owner: "I need that too". No INV
series: each line is numbered by its own ledger ref (CF-033-94, INV000037, VINV-021). Payments / advances
never appear; reversals show as "Less". Vendors only get Create Invoice. The after-posting email attaches
the invoice when its new entries include a sale. One dompdf sheet for both: `pdf-sales-invoice`
(`App\Services\SalesInvoice`, route `party-statement.sales-invoice`).
Same day, owner: "make a big modal, then switcher, all happens in that modal" — the Invoice button opens
ONE window (`partials/invoice-window`): switcher Sales invoice | New invoice for another service; the sales
list (filter bar, ticks, newest first) with an "On this invoice" panel + totals, Preview inside the window,
Open / Download; the New tab has a "What will be posted" panel and returns to Sales with the new charge ticked.
A small two-option menu and Print-style picking in the table were tried first and replaced by this.
**Invoice sheet = "3.08 Sky blue, split header"** (owner, 2026-10-14, picked from 5 designs, then 15 looks of the
one he called standard — #3 "grouped by service"). Owner: the invoice must NOT look like the ledger print —
"more informational, more visible layouts, fonts, writings, descriptions". So: big INVOICE + company card,
a section per service with its own columns and subtotal (flight: route·PNR, flight·departs, category·services;
tickets/visas: one row per person with its own price; files: submitted·expected, status), a service summary,
amount in words, balance today. DejaVu Sans only. What an agent may see = `SalesInvoice::details()` allow-list
(never vendor / provider / cost / Handling). No "Issued by" line (owner removed it).

**Cancel return (owner, 2026-10-14).** Family flight / family visa only: DAC → ECN with an ECN → DAC
return, and after he flies he chooses to stay longer, so the unused return is cancelled — penalty + our
charge, a choice of who bears the penalty, a time window to cancel in, and the agent's total either kept
or rewritten. In BOTH Contract Flight and Ticketing. Owner's answers: the return can be a round-trip ticket
OR a separate ticket ("both can happen"); window = a deadline + reminders (3 days, 1 day, the day — in-app
to whoever entered the ticket); "family categories only" (CF category name / a family visa; a Ticketing
ticket by its Family tick); rewrite = "give option to choose one, from the both" — type a new total OR type
an amount to give back, beside "keep". Round trip: only the return half's refund posts and the ticket stays
live; separate ticket: cancelled exactly like a Refund. One agent line (penalty he bears + our charge); a
lowered total is its own credit line, the original charge never edited.

### Accounts P&L = the current month; past months at the bottom — (owner, 2026-10-15)

Why: Visa, Ticketing, Flight and File Accounts opened on all-time totals, which mixes this month's work
with history. The owner: "all have to be in current month, and in the below of all table, we have the
past months, clicking that will reveal that month all scenarios" — then red-boxed the KPI tiles: "current
months". So: the tiles (also on the Agent / Vendor / Company Ledger tabs) are the current month
(`$kpi` from each controller; `overview` stays all-time for the Ticketing workbook). The P&L tab opens on
the current month (date boxes prefilled; a custom range still works, "This month" brings it back, empty
boxes = all months). **Past months** is the last table; opening a month shows that month's whole P&L —
statement, by agent, losses, its rows — the tab's own cards copied by `accounts-pl-scripts`, every figure
still drillable. Future months (Contract Flight departures) sit on top as "upcoming". File Accounts does
the same server-side (`account-kpis`, `account-file-table`); with a submit-date filter its top is that range.
All-months figures that stay on the tab say so ("Books check · all months", "Not invoiced · all months").

### Ticket Entry: Family on the return row; one click = one save — (owner, 2026-10-15)

Why: the Family tick and "Cancel return by" sat on a row of their own under the return line, beside an
empty "Return leg of the same ticket" note — the owner marked them into that empty space. Now ONE row:
Return Flight · Return Departure · Cancel return by · Family ticket at the right end; a one-way ticket
(a separate return ticket) shows only the Family pair. The bell hint moved into the tick's tooltip.
Same day the owner's Save "got stuck" (page dimmed, no dialog) and was clicked three times: three tickets
(223–225, PNR XYZ012345), three invoices. The server saved correctly each time (0.15 s); the page did not
move on. Save is now locked from the click ("Saving…" → "Saved ✓"), released only on a failure.
Then (owner: "stuck-like screen, no loading, taking so long" — a dim backdrop with no dialog box, never
reproduced headless): a "Saving…" loading dialog (spinner) from the click, the same dialog turns
"Saved ✓ … Opening Ticket Orders…" and the page goes there AT ONCE — the separate "Saved!" dialog and
its 900 ms wait are gone; Ticket Orders' highlighted new row is the confirmation. The Family tick is a
<span>, not a <label>: only the box ticks (owner: clicking the empty space beside it ticked it).

### Return window line + refund ⇄ penalty — (owner, 2026-10-15)

- Ticket Orders' return-window line is YELLOW always (a marker highlight); the last 5 hours it turns a
  brighter yellow, bold, pulsing, and says "4h 58m left"; passed = red, done = grey
  (`ReturnCancel::rowBadge`, tone `urgent`). It was cut to "Return cancel …" in the 130px Status cell, so
  the text is label-free ("28 Oct · 13d left") with a landing-plane icon, the full sentence in the tooltip —
  measured to fit in every state. The owner wrote "turns yellow" for the last hours too; read as a stronger
  yellow + animation, not red — ask if he meant red.
- Cancel return on a SEPARATE return ticket: refund and penalty fill each other (cost − the other), in
  Ticket Orders → Change and the contract flight lane. On a ROUND TRIP (one ticket) they stay the
  airline's two figures: refund + penalty = the return half's value (CF cost takes both off), and the
  ticket has no price for its return half (route DAC-ECN-DAC has no leg prices). Owner asked "if the
  ticket is one, what happens?" — explained; he accepted it as is: on one ticket staff type both figures
  from the airline's refund notice. No return-fare box.

### Each department's accounts list only its own agents / vendors — (owner, 2026-10-17)

Owner: "a ticket's agent / vendor won't be in the visa accounts" — then "but if they have taken cross
sales or services, then it's okay". `App\Services\DepartmentParties::ids($dept)`: a party belongs when one
of the department's records names him (sales, purchases, passengers, services, files, refunds / re-issues)
or the department posted to his ledger (transactions.type prefix visa_ / ticket_ / contract_flight /
contract_file). Payments, advances, opening balances, bank transfers name no department — they make
nobody belong. Applied to the Agent / Vendor Ledger tabs of Visa, Ticketing and Contract Flight accounts
(request attribute `party_scope_dept` → PartyStatementController filters the roster; its print carries
&dept=) and to File Accounts' Agent / Client filter. Party Statement on its own is unchanged (no
department → everyone); a failed lookup also shows everyone. The ledger of a party stays his WHOLE ledger.

### Ticket Entry "+" route = Manage Tickets' via airports — (owner, 2026-10-17)

Owner: "DAC-BAH-IST — via airport add option, can be multiple … we already may have this, need this feature in
the add ticket, the + icon". Reused, not rebuilt: the "+" route modal (#qcModal, data-tk-prefix="qc") includes
`tickets.route-via` + `tickets.route-via-script` (prefixOf reads data-tk-prefix; Manage Tickets unchanged) and
`quickCreateTicket` calls TicketController's own mergeViaDefaults / syncRouteLegs (made public, unchanged). Each
stop = TRANSIT inside the ticket or where a NEW TICKET starts (default "New ticket", as on Manage Tickets). The
new route's option carries data-legs, so a several-ticket route expands on the card at once. The PDF import's
"+ route" brings the PDF's stops in as TRANSITS (one PDF = one ticket). Title-only quick routes unchanged.

### Import PDF: the TICKET PNR, not the airline's; Contract Flight's required five — (owner, 2026-10-17)

- "Import PDF e Airline PNR detect kore Galileo PNR (Ticket PNR) detect korbe": every import (Ticketing and
  Contract Flight) now puts the ticket's own PNR (Galileo / Sabre RESERVATION CODE / booking code = the readers'
  `gds_pnr`) in the PNR box; the airline's is `airline_pnr` — on the reader strip and the Ticket cell's tooltip,
  and still matched for "already on file" (TicketPdfImporter::read swaps; Patterns::pnr now prefers a booking /
  GDS label over an "Airline PNR" label). Measured on the owner's PDFs: Galileo HOSSAIN → J9J15F (was 5L6F85
  QR), Sabre → IVJVUA (was 5DWLNU). Re-checked 2026-10-18 on 27 PDFs, committed reader vs new: only the 5 that
  print both PNRs change (+ AFRIDEMDMR → J8QWBD, Sabre AL AMIN → GMJATJ); passengers, flights, fares, warnings
  and the "already on file" matches identical on all 27. Earlier note (2026-09-22) called the PNR "the
  airline's booking reference" — superseded.
- Contract Flight import (?embed=cf): required = passenger, ticket PNR, route, vendor (seller), vendor cost —
  `tiMissing` adds 'pnr' only when TI_CF; a missing PNR is typed in the wizard table's Ticket cell. Everything
  else optional; the passenger card's ticket box lists what a ticket still lacks ("Not on file yet: E-ticket
  no. · Airline · Flight no. · Departure"; flightTicketDetails now carries flight_no / departure_at). The
  "already entered" decision still asks (it prevents duplicate tickets).

### Watch out: a route set from code expanded twice — duplicate ticket cards (found 2026-10-18)

On Ticket Entry, `$(routeSelect).val(id).trigger('change')` runs the select's inline `onchange` TWICE: jQuery
calls it, then Ticketing's dropdown helper (contract-flights/partials/ticketing-dropdowns, `select[data-cf-auto]`)
re-sends the trigger as a real change event. A pick by hand comes once. So a route of several tickets set from
code — the PDF import, Confirm booking, the "+" new route — expanded twice: Ticket 2 appeared twice, and
confirming a leg-2 booking left TWO leg-2 cards = the ticket bought twice on Save. Fixed in `onTicketChange`:
a card already expanded for this same route (`data-chain-route`), all its cards still on the page, is not
expanded again. Measured committed vs fixed: from code 3 → 2 cards, "+" 4 → 2, booking leg 2: 2 → 1; by hand
and direct routes identical. Never "fix" this by removing the helper — it is what lets native listeners hear
select2 picks across Ticketing.

### Every change: security check + Pint + Larastan, before calling it done — (owner, 2026-10-18)

Owner: "check security, php stan / lara stan check, always, for every change". Why: CI's Pint and Larastan
jobs went red on this branch (11 unformatted files; `Airport::with('country')` and
`TicketPurchase::$departure_at` unknown to Larastan) and were only noticed on GitHub. Run all four before
reporting ANY change as finished, and say in the report what ran and what it found:

1. **Security read of the diff.** Every new route/action checks who may use it (role / super-admin guard
   in the controller, not only the menu); input is validated; raw SQL binds its values (never a request
   value concatenated into `DB::raw` / `whereRaw` / `DB::statement`); Blade prints user data with
   `{{ }}`, never `{!! !!}`; models fill only `$fillable`; forms carry `@csrf`; uploads check type and
   size; a user cannot read or change another company's or another party's rows by editing an id; no
   secret / token / password hash reaches the page, a log or an export.
2. **Pint** on the files CI checks — the branch's own PHP changes, committed or not, minus migrations:
   ```
   B=$(git merge-base origin/main HEAD)
   F=$( (git diff --name-only --diff-filter=AM $B -- '*.php'; git ls-files --others --exclude-standard -- '*.php') | grep -v '^database/migrations/' | sort -u)
   if [ -n "$F" ]; then vendor/bin/pint --test $F; else echo "no PHP changes"; fi
   ```
   Diff against the MERGE-BASE, never plain `origin/main` (that pulls in every file main gained since).
   Never let the list be empty: `pint` with no files formats the WHOLE repo (CI guards this too).
   Touching a file puts the WHOLE file in that list, so an old unformatted file you edit one line of
   must be formatted too.
3. **Larastan** — `vendor/bin/phpstan analyse --memory-limit=2G --no-progress` (level 1 + baseline,
   phpstan.neon). Zero errors = pass. Fix a new error; do not add it to `phpstan-baseline.neon`. The
   two usual causes here: a relation method with no return type (give it `: BelongsTo` / `: HasMany`),
   and a column added by a guarded / helper-closure migration Larastan cannot read (add
   `@property` on the model).
4. **`php -l`** on every changed PHP file.

Larastan was in composer.json but not installed locally; installed 2026-10-18 (`composer install`
added only phpstan, larastan, sql-parser). On a fresh machine run `composer install` first.

### A transit e-ticket covers its leg — (owner, 2026-10-18)

Owner (screenshot: FURKXU DAC → BAH → IST linked, yet "Ticket 1 of 2 · DAC → IST — no ticket yet" and Issue
New Ticket asked for both legs): a PNR covers a route leg when one of its flights leaves the leg's airport and
the journey reaches the leg's end — transits in between (BAH, DOH) are part of the ticket. The contract flight
card read only the PNR's FIRST flight. Fixed in passenger-builder-script `pbCoversLeg()`, used by the slots
(`pbRenderLinked`) and by Issue New Ticket's missing legs (`pbMissingLegs`); a ticket issued as "Ticket n of m"
still goes by its leg number first. Not changed: the refund fare-difference matcher (`ticketSector()` and its
two client twins) still reads the first flight on BOTH sides — change all three together or not at all.

### An order is one passenger on one route; via choice after the airport — (owner, 2026-10-18)

- Owner: "why the second ticket of Ridoy shows under someone else": UFUMMB was saved on SALA UDDIN's card
  (same holder + route = same `order_group` by design), then moved to MD RIDOY MIA on the Edit page and kept
  Sala's order — Ticket Orders drew it as "↳ same passenger" under him. Now `TicketPurchaseController::update()`
  gives a ticket its own order when its order also holds another passenger's or another route's live ticket
  (on every save — so re-saving #257 once heals it); the list's "same passenger" also requires the same name.
  It was the only such order in the live data (2026-10-18 dump).
- Owner (angry): "solve the LOGIC, and the wrong entries too — not a db solve". Root cause: the 17 Oct import
  made 6 holders for 7 cards (206–211 at 19:34:49–51; Ridoy 212 typed by hand at 19:57) — the import gives one
  created holder to every card with the same passport key (or the same `chainGroup`, id was `'g'+Date.now()`,
  equal for cards built in one millisecond). Fixed: `tiCreateHolders()` stops when one passport is read for two
  different people (`tiNameKey`, MD/MOHAMMAD-insensitive — one person's two legs still share one holder), and
  the chain id is unique. Wrong entries: migration `2026_10_18_000001_split_mixed_ticket_order_groups` (data
  only, idempotent) splits every mixed order — #257 → `…-1s2`. Run locally 2026-10-18.
- Owner: "these why not posted into the ledgers??" (17 Oct rows of Ticket Orders): they WERE posted — seller,
  agent and journal, all of them. Ticket Orders filters by the day a ticket was ENTERED; ledgers date a row by
  its ISSUE date (7 DAC-BAH-IST tickets entered 17 Oct, issued 12 Oct → under 12 Oct), and the agent sees ONE
  line per invoice (INV000051 = 7 tickets ৳483,000), not one per ticket. Owner's answer pending.
- Owner: via airport row — "Not Selected, Transit or New Ticket, need after select apply option": the New
  ticket / Transit switch shows only once the stop has its airport (route-via-script `paint()`, class
  `tk-wait` = visibility hidden, so the row does not move). Manage Tickets and Ticket Entry's "+" alike.
- Local DB = the live dump of 2026-10-18 (epal_erp_1018, `.env` switched; epal_erp_1012 kept). phpMyAdmin
  dumps need `SET FOREIGN_KEY_CHECKS=0` first: live holds a contract flight whose route was deleted.
- Owner: agent ledger "+5 more passengers" hid NTXOCA, UFUMMB … of INV000051 — read as "not posted". Party
  Statement now lists EVERY ticket of a sale invoice on the agent's line (reasonsFor ticket_sale).

### Round-trip route — (owner, 2026-10-18: "I need an option to make round trip route — DAC-ECN-DAC, in one ticket or multiple ticket"; "not a quick fix")

- A route stays ONE DIRECTION in its From / To (origin → destination). DAC-ECN-DAC is From DAC, To ECN,
  **Trip: Round trip** (`tickets.trip_type = 'two-way'`, migration 2026_10_18_000002, read behind
  `Ticket::roundTripAvailable()`). Never a From = To route: the import, contract-flight slots and Cancel
  return all read From / To as origin / destination.
- **Return: Same ticket** — no extra legs; every ticket of the route is a round-trip ticket, so Ticket Entry
  sets its card(s) to Round Trip (the Return Flight row shows). **Separate ticket** — the return tickets
  (To → From, cut by their own return via airports: transit / new ticket) follow the outbound ones in
  `ticket_route_legs`; `legsArray()` marks them `direction: 'return'` (the legs after the one reaching To);
  Ticket Entry adds a card per ticket ("Return · Ticket 2 of 2"), each One-way.
- One form for all three screens: `tickets.route-trip` (Trip / Return switches + return stops) under Via
  airports in Manage Tickets Add / Edit and Ticket Entry's "+"; `route-via-script` cuts out + return; the
  server is TicketController's own `syncRouteLegs` (+ `cutRoute`, `separateReturn`, `tripAttributes`),
  reused by quickCreateTicket. The title fills itself (DAC-ECN-DAC) only while empty or still automatic.
- A route the card set to Round Trip goes back to One-way on a one-way route; a Round Trip chosen by hand
  on an ordinary route is kept. The PDF import prefers a round-trip route for a round-trip PDF, and its
  "+ route" for a round-trip PDF opens as Round trip · Same ticket.

### Import PDF: a remark is not a flight — (owner, 2026-10-18: "I already have the route, why is it not taking it automatically")

Pegasus prints a service remark "TOW/ECN/P784 -"; the general reader read TOW → ECN as a second flight, so
IST → ECN became IST-ECN-ECN, route #44 IST-ECN lost 70 points ("other stops") and was never auto-picked
("+ New IST-ECN-ECN" offered). `TicketPdfImporter::withoutPhantoms()` drops a flight with no airline, no
flight number and no time when another flight has them. Checked on 29 PDFs: only the two Pegasus files
change; they now pick #44 by themselves. Not done on purpose: dropping a via equal to the destination in
the matcher — it made a broken read (1.pdf) auto-pick a wrong route; a broken read stays staff's call.

### Ticket Import = PDF + CSV / Excel; the daily sheet — (owner, 2026-10-19)

- "Rename this section to Ticket Import, then split into two entry options, import PDF, import CSV / Excel":
  the tab, the header button and the panel say Ticket Import; the panel holds two drop zones side by side
  (PDF | CSV / Excel). A file dropped on either zone or anywhere on the page goes to its own reader. The
  contract flight's PDF overlay (?embed=cf) keeps only the PDF zone.
- **A sheet row is a PDF ticket.** `TicketSheetImporter` turns each row into the readers' result shape, then
  MasterData + matcher as for a PDF, so cards, the Complete-details table and Save are the PDF import's own.
  The sheet is never attached to a ticket (it holds everyone's rows) and never stored. Columns are found by
  heading, any order; vague headings ("Date", "Amount", "Price", "Source") count only when no clearer
  column names the same thing. Seller / Agent are matched by name in the browser, ignoring "[FLIGHT]" /
  "(CYP)" tags and Mr / Vai / Bhai / Apa — one clear hit only, else a note; the row's seller beats the
  route's default seller, never one picked by hand.
- **The daily sheet** (owner: "just single excel sheet … our employee enter the tickets there and give
  everyday, we will import that"): his draft was the "Demo" sheet (Issue Date · Flight Date · PNR · Pax Name ·
  Ticket Number · Route · Airline · Agent Name · Vendor Name · Agent Price · Vendor Price · Profit Loss ·
  Segment · Status · Source · Office Ref. · Remarks). That layout imports as it is. The template
  ("Daily sheet template", one sheet "Tickets") keeps his columns minus what repeats or is worked out
  (Profit Loss, Source = Vendor, Office Ref., Segment) plus Passport No (the certain passenger match) and
  Return Date (round trips wrote it in Status). Date columns are TEXT cells. Owner, same day: "do not make
  any multiple sheet, instruction etc. just main data" — ONE sheet, the heading row, nothing else (no notes
  on headings, no Instructions sheet). Empty + sample copies were saved to his Downloads.
- Same day, two looks fixed by red boxes: each drop zone is a fixed ~84px strip ("maintain this size
  exactly") — badge left, three one-line rows, only the grey list cut (full text in its tooltip); and on the
  Ticket Import tab the "Ticket Entry" header card is hidden ("dont need this part" — the tab band says it).
  Plain Ticket Entry and Ticket Services keep their header. Then: tabs → import card 40px → 20px ("reduce
  by 50%"). Then the white box behind the cards (#tab-sale's background, border, shadow, 20px padding) is
  REMOVED on this tab ("remove that actually" / "i said to remove this too" — I first misread it as
  "stretch it up"): the import card, the passengers card and the summary rail sit on the page, edge to edge
  with the tab band, 20px below it.
- "Scrolling is not working" in the Complete-imported-tickets table: over its header / footer / dim edge
  the wheel scrolled the page BEHIND it. Now any wheel on the open table scrolls the table, its end never
  chains to the page. "Aren't those created automatically???": yes — new passengers are made by
  tiCreateHolders() on Save (page or table); the card now says "created automatically on Save" so its
  "+ Create" no longer reads as a job to do.
- **Why text dates:** his Excel reads the month first. Staff typed 4/10/2026 … 7/10/2026 (4–7 Oct) and
  Excel stored 10 Apr … 10 Jul; the issue date dates the ledger rows. The reader turns such a column round
  only on strong evidence (2+ dates, much tighter and nearer today read day-first) and says so once on the
  file row. Also: Excel drops a ticket number's leading zero (0724883925053 → 724883925053) — 12 digits get
  it back. Status / Remarks are notes on the card; a date in Status on a round trip is its return day.
- Rafi's earlier Excel import (3d459373, 11 Oct: 41-column "Tickets" + "Instructions" template, whole file
  validated and saved at once) is no longer in the code on any branch; its template still sits in the
  owner's demo workbook. Not revived — this import fills cards instead.

**Passenger record (eye) bar = one clean row** (owner picked it, 2026-10-19, over "two rows" and
"icons only"): a solid dark strip on the A4 width; title · Edit passenger · Edit flight · Cancel
from flight · Delete (red) · divider · Print and ✕ as icon squares. Labels never wrap (a long
name wraps the title instead). Solid, because the bar lands on the page header and the header's
own buttons showed through. Shared by the dashboard's eye (`passenger-sheet-*` partials).

### Correct a posted contract-flight entry — (owner, 2026-10-19: "I accidentally selected we bear … make an edit option, not just ticket or info edit, but the oldest entries of penalties etc too"; "amounts, which ledger to hit, who bears")

- Where: Flights & Passengers → eye → Edit passenger → **Flight changes → Correct** (super admin only, checked in
  `ContractFlightChangeCorrectionController`; others keep the old Edit charge). Owner's answers: edit who bears each
  part, amounts, the seller (which ledger), the agent billed; super admin only; the post date is chosen in the
  dialog (default today).
- `App\Services\ContractFlightChangeCorrection`: reads what each ledger holds for the entry FROM THE POSTINGS (seller
  journals, the agent's change line + its 4181/4183 journal heads, fare, penalty reclass), compares with the corrected
  entry and ADDS one row per difference under the same sources the originals used — so the card, penaltyTotals,
  Company Ledger, Flight Accounts and Party Statement read it with no change. A wrong ledger goes to 0 by an added
  reversal, the right one up; nothing is edited or deleted. Refunds go through Ticketing's own
  `correctRefundAmount()` (now takes an optional date). Every journal is refused unless balanced; a second run of the
  same correction finds nothing (tested on 11 real entries, rolled back, and once through the UI on a sandbox copy).
- Owner's two rules made explicit in the dialog: **We bear → Agent pays** shows the actual penalty (seller's charge,
  per ticket, with its seller and amount) AND our charge — the agent gets ONE line = penalty + our charge (4181 +
  4183). **Agent pays → We bear**: only the penalty moves (Dr 4181 / Cr 1311); our charge stays billed to the agent
  unless changed in the same dialog. The seller is never touched by who bears — we owe them either way.
- Who bears is PER TICKET (as Re-assign records it): the agent's tickets + our charge ride on the entry, our tickets
  on a linked "we bear" half (`Charge per ticket — we bear: … · same move as change #N`) — the editor treats the pair
  as one entry. Reading "who pays" from the entry's NOTE was wrong (the old Edit charge left "we bear" in the note
  after a flip; it now rewrites it) — the entry's `bearer` field is the truth.
- Agent statement: the correction row reads as the penalty with its one resulting figure ("Reschedule penalty — …
  — corrected to ৳2,000 (was ৳11,747)"); Party Statement shows note segments without ৳ to the agent, so the
  "Corrected … (agent line ৳…) — reason" stamp carries a ৳ figure and stays staff-only.
- Look: section headings are coloured bands by meaning (agent indigo = the Agent pays pill, seller amber, our charge
  green, airline kept red, fare sky); "What will be posted" has a violet bar (owner: "so white, irritating").
- Watch out (testing): `DB_DATABASE=x php artisan serve` does NOT isolate here — the env var never reached Laravel and
  the "sandbox" server was on the real DB (caught before anything posted). Use the php -S router that forces the DB
  after config loads and refuses requests otherwise.

### Ticket sold directly to a client, no agent — (owner, 2026-10-25)

- "Select the clients from here … the customer name at the below of all agents": Ticket Entry's **Sold to**
  lists Agents, then **Clients** (role `customer` = Party Management → Customer; the group was labelled
  "Customers" — the owner says clients). A client wears a blue person icon, agents the green tie. The sale
  posts exactly as an agent's (invoice, his ledger on Party Statement → Client, Dr 1311 / Cr 4110).
- **A new passenger is a client automatically** — "after entering new passport, he should be added
  automatically to the client or customer list, then can be selected from the Sold to". A "+" on Sold to
  with its own "Make a New Client" dialog was built first and REJECTED the same day ("not this"). Now:
  saving a passenger through the passport holder "+" also calls `TicketDirectSaleController::quickCreateClient()`
  (= CustomerRoleController::store's record: role customer, Travels via company_party, service "ticket";
  needs `create customer`) and adds him under Clients in every Sold to — listed, NOT selected (an agent's
  passenger is still sold to the agent). The same name (case / spaces / no-break spaces ignored) reuses the
  existing client, never a second one. Skipped silently without the permission or the route.
- **Every "+" on the ticket card is the full height of its box, docked as its square end** ("this style looks
  bad, make like second ss" — a 32px "+" beside the 42px box). The shared Ticketing dropdown style forces
  42px / radius 6px `!important`, which is why the page's own docking had stopped working — the fix is
  page-only CSS with `!important`, never an edit to the shared partial.

### Contract File looks exactly like Contract Flight — (owner, 2026-10-25)

- "Match the contract file with the contract flight, in terms of button sizes, colors, fonts, font size,
  button style, table style, header styles, filter styles … even a bit font difference" → Contract Flight is
  the **design source** for Contract File; File follows it, never the other way round.
- How it is held: File reuses the flight's own partials where they are partials (`passenger-table-styles`,
  `journey-card-styles`, `dashboard-ledger-styles`, `accounts-styles`, `table-sort`) and copies the inline
  sheets value for value where they are not (`contract-files/flight-dashboard-styles`, `flight-list-styles`,
  the `kb-` board rules). The flight's markup classes are used as-is (`fd-`, `fdl-`, `mfp-`, `mj-`, `cfa-`).
  Verified by computed-style comparison in headless Chrome — every probed element "same".
- File-only content stays (Documents, Stage, due sheets, Sales History tab); where it has one more field than
  the flight (the list's Stage select) the bar wraps instead of clipping. Money on File Accounts is whole taka
  like Flight Accounts. The board's KPI tiles and stage dropdown went: the flight board has neither — a card
  is dragged to change stage.
- **Filter bar = one row, always** ("why like this?" at a two-row bar, same day). Files & Passengers' bar is
  the flight's seven slots: Search, Agent / Client, Stage, presets, Submit from/to, Filter / Reset. Category
  went (search matches the category name, every row shows it) and **Tomorrow** went on both file bars
  (submission dates are never tomorrow). Measured: one row down to a 1460px viewport, i.e. 90% zoom on a
  1365 window WITH the page scrollbar (~1498px) — the flight's own bar needs 1243px there and clips Reset.
- **File Dashboard is FILE / PASSPORT wise by default** ("all will be file / passport wise, not agent wise"
  + "a button to see modes, agent wise or passport / file wise; by default file / passport wise"). A two-way
  switch sits where the flight has its card-view button. File wise = one card and one row per file (like one
  per flight): destination flag as the mark, applicant, expected date, CF number tag; a click opens the file
  record. Agent wise = the old agent groups, a click opens that agent's ledger. Not remembered — every load
  opens file wise. Stats: Countries in file mode, Agent / Client Groups in agent mode. Strip order (owner,
  same day): count · Agent wise | File wise · Add File.
- File cards OPEN ON HOVER like flight cards ("like the flights, hovering should open details version"): the
  flight's grid-row span-2 mechanics, bottom row opening upwards, the expand / compact / all-open button
  (remembered per browser like the flight's). Open face: applicant + CF · stage + agent, passport ··✈·· country
  code + category, expected date + countdown pill, Docs / Client due / Vendor due strip.

### Ticket change rows: who and which ticket go in Details — (owner, 2026-10-26)

Party Statement rows posted by Ticket Orders → Change (`ticket_change_charge`, `ticket_sale_reversal`,
seller `ticket_change_fee` / `_fare`) showed "—" under Details while Entry carried
"Re-issue penalty — VD57QU · NAME" + voucher VD57QU; the owner underlined the PNR and name: "show these in
the details". Details now = name / PNR · route / (agent re-issue) new date · was old date — the same shape
as a ticket row. Entry keeps only what happened ("Re-issue penalty", "Ticket refunded — agent price reversed
(INV000053)"): the PNR and name are cut from the remark (`reasonsFor()` 4th return, `$entry`), never from
the stored row. Print/PDF unchanged on purpose — those rows get the same name-only fact
`StatementRows::facts()` already looked up.

### Ticket Trail: every posting of a ticket, editable, wrong entries REPLACED — (owner, 2026-10-26)

Asked: "a section … that shows every edits history, amounts ledgers, reissue, void, refund, vendor, amounts,
where posted what amounts … every tiny thing editable, decide what to post, agent change, amount change, type
change (was void but I am changing it to reissue) … previous entries will be replaced … side by side will show
while edits, what it is, if changed, where will it hit, what will be changed." Decisions the same day:
- **Replace, not reverse** — "wrong entry get vanished and the edited take their places". This is the owner's
  explicit exception to "nothing is deleted / reversal by addition" for THIS tool (like Delete wrong entry,
  2026-10-12). Cancellations elsewhere still only add rows. The Trail keeps a copy of every replaced row (who,
  when, reason), because the same request asked for every edit's history.
- **Payments editable here too** (bank / portal payment to the seller, receipts) — not just shown.
- **Contract-flight tickets editable from here too** — "edit from everywhere, as these are connected"; the
  edit must still go through the flight's own posting code so both screens agree.
- **Where it opens** (owner's screenshot, same day): NOT a new button on the Ticket Orders row. Ticket Orders →
  Change → top-right of the right panel ("What will be posted" header) = **All Trails and Edit**; clicking it
  closes the Change dialog and opens the detailed Trail window (all trails, times, edit, compare). And the Change
  dialog's own HISTORY box shows **all history** of the ticket (entered, sold, paid, changed, corrected), not only
  its re-issue / refund rows.
- **The page behind a modal must not scroll** (owner, same day, on the Trail: "the scroll bar of the behind main
  page won't work"): a custom (non-SweetAlert) modal locks the page while open — its scrollbar hidden and padded
  back so nothing shifts, wheel and keys stop at the modal — and gives it back on close. SweetAlert does this itself.
  He had to ask TWICE (16:04, screenshot with the bar still showing): the first lock was inline styles and
  passed the headless test, but not in his browser. When he repeats a request, the fix did not hold where HE
  is — make it unbeatable (a class with `!important`, padding included), test how it can be undone, not just
  that it works once.
- **Contract-flight ticket on the Trail, as built:** the ticket side (cost, seller, PNR, dates, seller
  payments) is editable and flows into his travel cost and the flight's cost; passenger and agent sale are
  locked (billed per head on the flight). Once he has flight changes, his card's Correct is the place.

### Contract File: fewer required fields — (owner, 2026-10-26)
- **New passport holder from Contract File: only the name is required** ("name ta only mandatory"). Passport,
  nationality, phone, category, type may be blank; the shared passport-holder endpoint relaxes only when the
  form sends `source=contract_file` — every other screen keeps its rules.
- **File Category: the job name is optional.** "For Malaysia I can have different jobs — a fixed one means
  creating it again and again." A blank job = one category for the whole country, stored as `ALL JOBS`.
- **A ticket route needs no airline** ("route ektai thakbe, I want to choose the airline each time"): one
  route (e.g. DAC → KUL), the airline picked on each ticket ("Airline that issues"). Contract Flight's and
  Flight Schedules' quick route forms still ask for one (flight cards and rate presets use it).
- Files & Passengers: the "0 / 3 received" count badge removed ("dont need this"); the document lines stay.
- **Deported: the package switch is a row under the costs, with its amount** ("we already select who bears
  every amount — why is it here?"). Above the table, unlabelled, it read as a second bearer for the same
  charges. It decides only his package (agent pays it / it is given back) — say it with the name and the ৳.
  Explaining it in ledger words ("whose side decides the package… reversed") did not land; a choice popup
  was rejected. Answer such a question plainly, then fix the screen so it no longer needs explaining.
- Re-assign → Offload / Deported: no "Charge for the change" heading ("isn't it redundancy? remove the bottom
  one") — their charge is in their own table (Our deport charge / our charge per item). The note stays, full
  width, as the note saved in his Flight changes history.
- **A closed case is reversible** ("reverse option needed", on the Cancelled list's Closed rows): the row keeps
  its Closed tag and gets Re-assign (+ Undo when his old flight still exists) — the same actions as any
  cancelled passenger; the package is billed again as a new line, nothing the close posted is removed.
  When his old flight was deleted, Undo shows greyed — an empty slot read as "no reverse" ("where") — and,
  clicked, says "CF-044 was deleted" and offers "Re-assign him" (a dead greyed button read as "not
  working": never leave a control that does nothing when clicked). He also asked: the ledger must match, no double entries, never
  unbalanced — proven before handing over (close → Undo cycles, Re-assign, double clicks refused, ledger
  moved = 1311 moved, journals balance).

### Contract Flight printing everywhere (owner, 2026-10-28)
- Every Contract Flight section needs a Print option like the ledger; Flights & Passengers also needs it beside Filter. Print the current visible filtered records on an A4 sheet without the ERP shell or action controls.

- Contract Flight Print placement correction (2026-10-28): Flights & Passengers must have only one list Print button, beside Filter / Reset at the marked place; do not add another above the page header.

- Advanced Contract Flight print (2026-10-28): copy the specific-agent Party Statement ledger print designer's layout and controls, adapting categories, columns and departure dates to Contract Flight records. Keep the single Print beside Filter / Reset; support selected records, sheet styling, stamps, drawn/uploaded signatures, seal and preview.

### Ticket baggage allowance and extra baggage accounting (owner, 2026-10-29)
- Record free baggage KG separately from extra baggage KG, with baggage conditions and an explicit Agent pays / We bear it choice per ticket. Extra baggage has separate Cost and Agent Charge fields.
- Extra baggage cost belongs to the same seller as the ticket; the agent charge belongs to the same agent buying the ticket. Agent pays posts the charge to that agent's ledger; We bear it leaves no agent charge and reduces company profit. Baggage profit = agent charge minus baggage cost, included in company profit.
- Migration `2026_10_29_000001_add_baggage_pricing_to_ticket_entries.php` adds nullable fields to purchases and bookings, preserving old baggage text. Confirmed applied locally on continuation. Entry and edit use one baggage-fields partial; `TicketBaggage` posts separate additive cost/charge adjustments, follows seller/buyer edits and uses the ticket's portal wallet when applicable. Company-paid baggage requires zero agent charge; who pays is never pre-selected.
- Baggage stays separate from ticket fare/refund amounts. It is included in Ticket Orders profit, Ticket Accounts, Company Ledger and TicketingProfit; ticket history shows its journals. Wrong-entry purge includes baggage postings (portal money still blocks deletion). Use the baggage fields to correct its own amounts; a ticket refund does not assume the baggage was refunded too.
- Regression check: `php tests/integration/ticket-baggage-posting.php` uses rollback-only fixtures and disables notifications, covering actual entry/edit saves, seller/buyer changes, company-paid costs, portal adjustments, reports, balanced journals and continuing party balances.
- Baggage form correction (owner screenshot, 2026-10-29): remove the Conditions box and swap Agent Charge with Who Pays. Shared entry/edit order: Extra KG, Cost, Agent Charge, Who Pays, Baggage Profit.
- Baggage sizing correction (owner screenshot, 2026-10-29): Who Pays must match the adjacent input height. Native select uses border-box sizing and no minimum-height override so the entry/edit field height includes its padding and border.

### Contract Flight extra baggage (owner screenshot, 2026-10-29)
- Add the same baggage fields inside the Contract Flight passenger builder's ticket form: Free KG, Extra KG, Cost, Agent Charge, Who Pays and Baggage Profit. No Conditions box; no preselected payer. Existing linked tickets expose their baggage in an expandable Baggage section.
- New-ticket baggage travels with the builder until the passenger is saved; then `TicketBaggage::forPassenger()` verifies the ticket belongs to this passenger/company, saves its baggage and posts to its seller and the passenger's selected agent. The package remains separate. Repeated saves post no duplicates; agent changes credit the old agent and bill the new one.
- Baggage cost/charge contribute to passenger and flight cost/revenue/profit, Contract Flight Accounts and Profit & Funds. Linked baggage is excluded from TicketingProfit to avoid counting company profit twice. Baggage adjustment credits are not cash receipts; actual receipts can cover the baggage charge.
- Uses the existing baggage migration; no new migration. Regression: `php tests/integration/contract-flight-baggage.php` exercises real flight create/edit transactions, company-paid baggage, agent changes, ticket ownership, journal balance, receipt exclusions, profit counted once, and dashboard rendering; all fixtures roll back and notifications are suppressed.

- Ticket Orders parse-error correction (owner screenshot, 2026-10-29): baggage text ending in `free@endif` left a Blade directive uncompiled and broke the whole list. Put baggage conditionals on their own lines. `view:cache` alone does not validate generated PHP; `TicketOrderRowCompilationTest` parses the compiled row with `TOKEN_PARSE`.

### Flights & Passengers Stage / Status column restored — (owner screenshot, 2026-10-29)

- The owner's latest screenshot overrides the earlier removal: show `Stage / Status` between Tickets / PNR and Route. The cell carries the passenger's current journey stage plus exception status; off-flight passengers show their stage and `Off flight`. Keep the journey row and all empty/section rows aligned to the nine-column table.

### Ticket 1 / Ticket 2 is the LEG, never the date — (owner, 2026-11-01)

- Restated: on DAC-IST-ECN, the IST-ECN ticket can be re-issued, re-dated or refunded any number of times and he stays on the flight of his DAC-IST ticket; only a change to leg 1 moves him, and leg 2 goes along unchanged. Leg 1 refunded + new ticket touches leg 1 only.
- Found broken and fixed: `ticketSlots()` sorted live tickets by date, so leg 2 re-dated before leg 1, or leg 1 refunded, made IST-ECN "Ticket 1" (wrong leg-1 notice and move prompt, wrong tracker); and `lanes()` matched by route id, which both legs of a via route share, so every leg-2 ticket sat in lane 1. Now `ContractFlightPassenger::slotOrder()`: `leg_sequence` holds its slot, the rest fill free slots in journey order; a live ticket's lane = its slot.
- Then built the same day — **Contract Flight Trail** (owner: "all edit trail of contract flight, any edit, that can be re-edited, and affect, replace, delete and create new entry in the ledgers"; "it should mirror what it is connected, the rest are in the contract flight — agent price, services"). Owner's answers: the button sits in the **Re-assign window's header** (his screenshot, red box left of ✕); **delete removes the money AND its effect** (refunded ticket live again, date back, him back on his flight); **the Trail replaces Correct** (Flight changes → Edit opens it). Ticket side (cost, seller, PNR, payments) stays on Ticketing's Trail and is now editable there for a live CF ticket even with flight changes. Rule kept from the Ticketing Trail: a replace with no date edit moves no date.
- Same day, owner's screenshot (only "Package" listed): "its not full info … two ticket, airlines can be changed, amount can be changed, date, vendor, can be deleted one ticket, or both … all possible edits" → each ticket (route, PNR, airline, class, travel date, cost, seller, payments; delete) and each service (who is paid, how much; remove) is its own entry in the Trail. Then "make the modal more bigger, making space for Every entry": the Trail is near the whole window (`min(1760px, 100vw − 24px)` × `100vh − 16px`) — wider than the 1280px dialog standard, on his ask — and the columns at HIS drawn split (two red lines on his screenshot): entries ~28% · editor ~43% · what will change ~29% (`0.96fr 1.5fr 1fr`). A fixed-px list column was not it: the extra width went to the other columns. Then "make this section timeline wise, and session wise … in which session, via which functionalities": the left column is a TIMELINE of sessions (one person, no gap over 5 min), newest session first, in order inside one; header = day · time range · who · the functions used; each entry card says "via <screen>" + what the screen recorded (passenger event log); corrections (Trail, Correct, Ticketing Trail, ticket edits, Ticket Orders changes) and stage moves are lines in their own session, a correction line opens the entry it changed (`ContractFlightTrail::timeline()`, `viaOf()`).

### One date, one value — builder's new-ticket card (owner screenshot, 2026-11-01)

- Ticket Orders → Change on a refunded ticket: only the **Correct refund** (edit) button is removed. The "Refunded — airline refund …" notice and the "New ticket · Same passenger …" heading STAY — a red box around an area can mean one control inside it; ask or remove the least.
- Ticket Import → Excel (2026-11-01): the daily sheet `Daily Tickets - Empty (1) (1).xlsx` is what the data entry operator fills "exactly like this" — 14 columns, dates typed as text dd/mm/yyyy, "Optional" left in Passport No / Remarks. "Optional", N/A, NA, nil, none, null, "-" in any cell = empty (a passport "OPTIONAL" had blocked the passenger's name match).
- Same rule in Re-assign ("mirror needs"): the new flight's date box (`#cfr_nf_dep`) and leg 1's **New date** mirror each other; leg 2's date stays its own. General rule: two boxes that hold the same date on one screen must mirror.
- The Flight panel's **Departure** and the leg row's **Travel date** "should reflect each other": editing either one fills the other (first leg only; hand-added later legs keep their own dates). The leg row is seeded from Departure when it has one. Labels must be readable against their strip: "Issue this leg now" was #e0e7ff on #f5f3ff — use the strip's own violet.

### Flight Dashboard: empty flights leave the wall; airline cards keep "N flights" — (owner, 2026-11-01)

- "why kuddus still in the same flight while his flight got changed": he HAD moved (CF-056 → CF-057), but the airline card grouped the now-empty CF-056 with CF-057 — "2 flights · next 4 Nov". A flight everyone was moved off (a change row has it as from_flight, none left on it) is left off the dashboard (`ManageFlightController::dashboard()`); it stays in Flights & Passengers to close or delete. A new flight still waiting for its first passenger stays on the wall.
- Asked "where is the tag of flight number?? previously there was like CF…", then saw the cards are AIRLINE-wise and had the CF-number tag reverted: the airline card keeps its "N flights" chip. Do not put CF numbers back on the airline card.
- Then: "make a button here to decide, how to see, airlines wise, or our previous like Flight Wise" (red box in the purple KPI band, left of the expand icon) → **Airline-wise | Flight-wise** switch (`setFlightCardGroup()`), remembered per browser (`flightCardGroupMode`), Airline-wise by default. Flight-wise = the old wall: one card per flight with its CF tag, opening that flight's ledger.
### User Role Setting — a NEW permission system beside the old one (owner, 2026-11-01)

- After the Travels-by-role work was reverted ("just reverse all your edits of role permission things"), the owner started fresh: a separate settings page, tried in LIVE on ONE employee only. The existing Role Permission screen, Spatie roles and every other user must not change at all.
- User Management → **User Role Setting** (last item, super admin only). Step 1 (built): the access map — "a root map like the sidebar": company → section → module → page, each with View / Create / Edit / Delete; access is module-wise and can cross companies. Registry: `App\Support\UserRoleSetting\ModuleMap` (taken from the rendered sidebar + every tab band). Travels first, Group-wide (shared Accounts, HRM, Party Management…) last; "goes with" = suggestions only, never auto-granted.
- Then: Assign Role | Employees with Roles tabs, people picker + Save (`user_role_settings`), Basic employee access block (always on, locked: the employee role's own-data permissions minus view users / lead projects).
- **REPLACES, one person at a time** (owner, 2026-11-01: "only his earlier role turns off … only he, not others, keep them unbothered"). Employees with Roles → **Apply new system** per person + a master switch. Enforced in ONE place: `User::hasPermissionTo()` asks `App\Support\UserRoleSetting\Access::decide()` first — null (not switched on, master off, super admin, any error) → Spatie's own answer unchanged. Page → permission names via `ModuleMap::SUBJECTS` (v = view + view all). Tested: 185 other users × 431 permissions identical with the system on.
- **Module access = every button in the module** (owner, angry: "we are giving module wise, not modules every buttons … if I add a new button I have to change the role settings code"). Super-admin-only tools inside a module (Trail, Correct, Undo, Delete wrong entry) check `Access::allows('travels.contract-flight', 'e'|'d')`: super admin yes, a switched-on person by his module ticks, everyone else no (as before). **Any NEW button inside a module checks `Access::allows('<module key>', letter)` — never a new map entry.**

### Split refund penalties and replacement fare differences (owner, 2026-11-02)

- Each refunded ticket can split its penalty by amount: for 8,666, the agent can bear 5,000 and we bear 3,666. Keep Agent pays / We bear it and add Split; enter the agent share and calculate our remainder.
- The replacement fare split belongs in the existing fare-difference box on the right of Add Contract Flight's passenger card, where the new ticket cost is known. Preserve the existing box and bearer buttons. Bill only the agent share; the full difference is already inside ticket cost.

### Split belongs across every Re-assign section (owner, 2026-11-02)

- Split must be available throughout Reschedule / Reissue, Refund / New Ticket, Offload / Deported, and Suspend withdraw. It is per cost or penalty, including refundable services, deportation costs and the deported passenger's package. A seller is owed the full cost, the agent is billed only his share, and our fee remains billed to the agent.

### Flights & Passengers separates Offload and Deported cards (owner, 2026-11-02)

- Show separate clickable KPI cards for Offload and Deported instead of combining them. Offload means stopped by immigration or denied boarding in our country; Deported means sent back from the destination country. Missed flights are neither category. Agent-side and company-side deportations both belong under Deported.

<!-- ===== END IMRAN ===== -->
