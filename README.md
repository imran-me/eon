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

EON has lived three lives before this repository, each as a static,
GitHub-Pages-hosted, JavaScript-only assistant:

| Life | Repository | What it learned |
| --- | --- | --- |
| Personal OS | `imran-me/personalos` | the brain itself — deck, ask, agent, finance coach, prover, opportunity radar, offline Q&A |
| EON for Teacher | `imran-me/eonforteacher` | reasoning trace, live alerts, insights, KPI leaderboards, voice, notices, reports |
| OppTracker | `imran-me/OppTracker` | Work Sheet, Google Drive mirror, resilient tokens, responsive shell |

This repository merges the three into **one** EON and gives it what it never
had before: a server. With Hostinger (PHP 8.2 + MySQL) available, EON can now
read the ERP directly, remember across sessions, and call a large language
model when the offline brain is not enough.

## Shape of the system

```
eon/
├── app/            the EON web app — command center, ask, voice, decision pages
├── ai-companion/   the shared core: 3-D companion + eon-brain (modules, deck, agent)
│   └── eon-brain/
│       ├── core/        registry, config, discovery, ask router
│       ├── domains/erp/ ERP knowledge + decision layers (finance, payroll, HR, CRM, tasks, monitoring)
│       ├── adapters/    data adapters — local, teacher, opptracker, erp (server)
│       └── …            analytics, intel, knowledge, models, owner
├── server/         PHP backend for Hostinger — read-only ERP adapters, LLM proxy, memory
└── docs/           architecture, deploy guide, summit script
```

EON works in three modes and degrades gracefully between them:

1. **Live** — `server/` reachable, ERP database connected, LLM key present.
2. **Server, no LLM** — real ERP numbers, offline rule-based brain.
3. **Static** — no server at all (GitHub Pages): demo dataset that mirrors the
   ERP schema, offline brain, voice still works in the browser.

## Author

Md Imran Hossain — Epal IT Solutions.
