# EON — One Brain, Multi Decision Layer

EON is the executive intelligence layer for the **Epal ERP** (`erp.epal.com.bd`).
It is a single brain — one memory, one voice, one place to ask — that sits on
top of a running company and turns its data into decisions for the person who
has to make them: the boss.

Ask it by typing or by speaking. It answers with the numbers behind the answer,
the rule it applied, and the action it recommends — across accounts, payroll,
HR, CRM, expenses, tasks and employee monitoring.

> Built for presentation at **AI Business Summit 2026**, China–Bangladesh
> Friendship Conference Center, Dhaka.

## Where EON comes from

EON has lived three lives before this repository — the same AI, with slightly
different abilities, embedded in three GitHub-Pages sites:

| Life | Repository | Ability EON gained there |
| --- | --- | --- |
| Personal OS | `imran-me/personalos` | the brain — intelligence deck, Ask EON, NL→Action agent, finance coach, prover (reads PDF/Excel/CSV), win-predictor, boardroom debate, digital twin, self-correction, offline Q&A |
| EON for Teacher | `imran-me/eonforteacher` | reasoning trace, live alerts, insight layers, KPI leaderboards, notices & reports, offline no-CDN build |
| OppTracker | `imran-me/OppTracker` | deadline guard, reminders, escort-to-record, coach, mind (judgment/memory/learning), resilient sync |

Only **EON** was taken from those repositories — the companion and its brain
(`ai-companion/`). The host sites were not. What EON never had before is a
server: with Hostinger (PHP 8.2 + MySQL) it can now read the ERP directly,
remember across sessions and call a language model when the offline brain is
not enough.

## Shape of the system

```
eon/
├── ai-companion/        EON — the 3-D companion + eon-brain (deck, ask, agent, analytics, intel, knowledge)
│   ├── eon-brain/domains/erp/   ERP knowledge + decision layers (finance, payroll, HR, CRM, tasks, monitoring)
│   └── adapters/erp-adapter.js  feeds the brain from the ERP (server) or the bundled demo dataset
├── app/                 EON’s own screens — Command Center, Ask, Voice, decision pages
├── server/              PHP backend — read-only ERP adapters, memory, LLM proxy (Hostinger / inside the ERP’s public/)
└── docs/                ERP domain map, architecture, deploy guide, summit script
```

EON works in three modes and degrades gracefully between them:

1. **Live** — `server/` reachable, ERP database connected, LLM key present.
2. **Server, no LLM** — real ERP numbers, offline rule-based brain.
3. **Static** — no server at all: demo dataset that mirrors the ERP schema,
   offline brain, voice still works in the browser.

## Hosting inside the ERP

EON is designed to be dropped into the Epal ERP without changing the ERP’s
code: publish this folder under the ERP’s `public/eon/`, include one script tag
in the shared layout, point `server/config.local.php` at the same MySQL
database with a read-only user. Details in `docs/deploy.md`.

## Author

Md Imran Hossain — Epal IT Solutions.
