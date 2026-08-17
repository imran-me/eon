# EON — lineage and architecture of the merge

## Where the three eons came from

| Repository | Commits | Span | Specialised in |
| --- | --- | --- | --- |
| `imran-me/OppTracker` | 133 | 2026-06-21 → 2026-08-03 | the personal app shell — opportunities, tasks, documents, contacts, achievements, projects, research, private Accounts ledger, **Work Sheet**, **Google Drive mirror**, responsive pass |
| `imran-me/personalos` | 36 | 2026-07-10 → 2026-07-11 | a snapshot of OppTracker taken 9–10 July that then went hard on **the brain**: intelligence deck, Ask EON, NL→Action agent, finance coach, prover, win-predictor, boardroom, digital twin, crisis, self-correction, 340-entry offline Q&A, academics |
| `imran-me/eonforteacher` | 12 | 2026-07-11 | the same companion inside a **fully offline** teacher portal (no CDN, vendored three.js): command center, OBE analytics, exam-integrity engine, insights, KPI leaderboard, notices, reports, counseling, quizzes, a *simulated* voice page |

None of the three shares git history. Personal OS was a fork of OppTracker; OppTracker
kept moving afterwards; the teacher build forked the Personal-OS-era companion.
Consequently no single repo was a superset.

## How they were merged (commit by commit)

1. `import: canonical ai-companion core from Personal OS` — every byte of
   `ai-companion/` (avatar + `eon-brain/`) is the Personal OS copy (identical to the
   teacher copy except `config.js`; a strict superset of OppTracker's). three.js r160
   vendored from the teacher build so nothing depends on unpkg.
2. `merge: personal space` — a real three-way merge with the fork commit
   (`OppTracker d0f7d29`) as base: OppTracker's app layer (+4 210 lines: Work Sheet,
   Drive mirror, responsive) merged with Personal OS's app deltas (+78 lines: Eon
   Intelligence page, academics, speed-first boot). One conflict
   (`Security.PROTECTED_PAGES`) resolved to the union. Lives in `personal/`.
3. `import: teacher space` — the teacher pages in `teacher/`; its adapter moved to
   `ai-companion/adapters/teacher-adapter.js`; the 2 MB generated bundle dropped in
   favour of the live modules + import map; `config.js` made space-aware.
4. `brain: pluggable domain registry` — `EonDomains` so every space (and the ERP)
   answers Ask EON the same way.
5. From here on: the ERP domain, the server, voice and the Command Center.

## The shared core (`ai-companion/`)

- **Avatar** (`js/`): a three.js companion character on a transparent overlay with a
  CSS "home" corner. `boot.js` is the only entry point pages should load — it
  idle-loads `main.js` (avatar + owner tier: Ask, Coach, Nudger, Whiteboard,
  Backpack, Mind, Games…) and `eon-brain/eon-brain.js`.
- **Brain** (`eon-brain/`): `brain.js` reads one dataset object, auto-discovers
  entities and their deadline/label fields (`discovery.js`), scans deadlines, raises
  reminders, publishes a meditation state, and persists its own state through a
  Firestore-shaped store. `config.js` = defaults + `window.EON_BRAIN_CONFIG` overrides.
- **Modules** register themselves on `window.Eon*` (`EonDeck`, `EonAsk`, `EonAgent`,
  `EonFinance`, `EonWinPredictor`, `EonProver`, `EonBoardroom`, `EonTwin`,
  `EonAnomaly`, `EonGraph`, `EonScholar`, `EonMind`, `EonImpact`, `EonLearn`…).
  `intel/deck.js` is the de-facto manifest.
- **Domains** (`eon-brain/domains.js`): `EonDomains.register({id, priority, claims?,
  answer(q, ctx)})`. Ask EON consults them first, then its generic intents, then a
  connected language model (`window.EonLLM`), then the honest offline fallback.

## The data contract (what an adapter must supply)

The brain reads **one plain object whose keys are entity names and whose values are
flat arrays of records**:

```js
{
  opportunities: [{ id, name, organizer, type, priority, status, deadline, … }],
  tasks:         [{ id, title, status, priority, dueDate, … }],
  courses: [...], assessments: [...], attendance: [...],
  // any other entity — discovered automatically
}
```

Rules: flat arrays; every record has an `id`; a label field (`name/title/label/
subject/reference/ref/code`); dates as ISO `YYYY-MM-DD` (name them `deadline`/
`dueDate`/`expiryDate` to be picked first); a `status`/`stage` using words the DONE
vocabulary recognises (`done|complete|closed|won|lost|paid|approved|…`) so
resolved items go quiet; register deadline entities + link patterns in the space
config. Supply it either as `firestore().collection('opptrack').doc('data')` →
`{ store: data }` or by shimming `window.firebase` exactly as
`adapters/teacher-adapter.js` does — that shim is the whole integration surface.
Money modules additionally read `window.FinanceDB.data.tx = [{type, amount,
category, date, …}]` and `window.fmtBDT`.

## Spaces

| Path | Space | Data | Adapter |
| --- | --- | --- | --- |
| `/` | **Enterprise (Epal ERP)** — the Command Center for the boss | ERP MySQL via `server/`, or the bundled demo dataset | `ai-companion/adapters/erp-adapter.js` |
| `/personal/` | Personal OS — opportunities, tasks, documents, achievements, accounts, academics, work sheet | Firebase Firestore (owner-write, public-read) + Google Drive mirror | built into `personal/assets/js/app.js` |
| `/teacher/` | Teacher portal — courses, students, OBE, integrity, insights, KPI | offline synthetic seed | `ai-companion/adapters/teacher-adapter.js` |

## Modes

1. **Live** — `server/` reachable, ERP DB connected, LLM key set.
2. **Server, no LLM** — real ERP numbers, offline rule brain.
3. **Static** — no server (GitHub Pages / `file://`): demo dataset mirroring the ERP
   schema, offline brain, browser voice.

## Known weak spots carried in (to clean as we go)

- Owner identity is hard-coded in several places (`js/owner-config.js`,
  `eon-brain/config.js`, `personal/assets/js/firebase-config.js`).
- `esc()`/money formatters/`DONE` vocab duplicated across modules with slight drift.
- `brain-qa.js` (210 KB) is parsed on every page load.
- Modules boot on polling intervals rather than a ready-promise registry.
- Personal-space seed data ships in `app.js` for an empty cache.
