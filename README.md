# EON — One Brain, Multi Decision Layer

EON is the executive intelligence layer for the **Epal ERP** (`erp.epal.com.bd`,
Laravel 12 / MySQL, twelve companies in one database). It is a single brain — one
memory, one voice, one place to ask — that sits on top of a running company and
turns its data into decisions for the person who has to make them: the boss.

Ask it by typing or by speaking. It answers with the numbers behind the answer,
the rule it applied, and the action it recommends — across accounts (chart of
accounts, general ledger, journals, ledgers), payroll, HR and attendance,
expenses and budgets, CRM, tasks and projects, employee monitoring and
evaluation. Every morning it briefs; all day it watches; approvals queue in one
place; it drafts the reminder, the warning letter, the notice.

> Built for presentation at **AI Business Summit 2026**, China–Bangladesh
> Friendship Conference Center, Dhaka.

## How it is built — deliberately polyglot

| Layer | Language | Where |
| --- | --- | --- |
| API, read-only ERP database access, memory, auth, cron, the language-model agent | **PHP 8.2** (plain, Laravel-compatible; a Laravel module shim is in `laravel/`) | `server/` on Hostinger, next to the ERP |
| Forecasting, anomaly detection, employee-evaluation model, Excel/CSV reports | **Python** (`server/py/eon.py`, called by PHP, JSON in/out) | same host (python3) or a VPS |
| Aging, trial balance, monthly roll-ups | **SQL / MySQL** | the ERP database, read-only user |
| The face: companion, voice in/out, Command Center screens, offline fallback | **JavaScript** | `index.html`, `app/`, `ai-companion/` |

The language model is Claude (`claude-opus-5`, official PHP SDK) with **tool use
over the ERP data** — EON reasons, calls SQL-backed tools, and answers in plain
language; the same brain answers voice and text. When no key or no server is
available, a rule-based brain answers so the demo never dies.

## Modes (graceful degradation)

1. **Live** — `server/` reachable, ERP database connected, model key present.
2. **Server** — real ERP numbers, offline rule brain (or demo data if no DB yet).
3. **Static** — no server (GitHub Pages / `file://`): demo dataset that mirrors
   the ERP schema, offline brain, browser voice.

## Where EON comes from

EON lived three lives before this repository — the same AI with slightly
different abilities, embedded in three GitHub-Pages sites:

| Life | Repository | Ability EON gained there |
| --- | --- | --- |
| Personal OS | `imran-me/personalos` | the brain — intelligence deck, Ask EON, NL→Action agent, finance coach, prover (reads PDF/Excel/CSV), win-predictor, boardroom debate, digital twin, self-correction, offline Q&A |
| EON for Teacher | `imran-me/eonforteacher` | reasoning trace, live alerts, insight layers, KPI leaderboards, notices & reports, offline no-CDN build |
| OppTracker | `imran-me/OppTracker` | deadline guard, reminders, escort-to-record, coach, mind (judgment/memory/learning), resilient sync |

Only **EON** was taken from those repositories (`ai-companion/`), not the sites.

## Repository map

```
eon/
├── index.html + app/            Command Center — brief, decisions, approvals, finance, people, CRM, operations, Ask EON (voice + text)
├── ai-companion/                EON core: 3-D companion + eon-brain
│   ├── eon-brain/domains/erp/   ERP knowledge, dataset contract, demo generator, decision layers, answerer
│   ├── eon-brain/voice.js       Web Speech in/out (push-to-talk, hands-free wake word, English/Bangla)
│   └── adapters/erp-adapter.js  feeds the brain from the server or the demo dataset
├── server/                      PHP backend (Hostinger): api/, lib/, cron/, install/schema.sql, py/ (Python analytics)
├── laravel/                     shim to mount EON inside the ERP as a module
├── docs/                        erp-domain-map.md, lineage-and-architecture.md, deploy.md, summit-demo.md
└── CLAUDE.md                    working context: rules, status board, next steps
```

## Quick start

```bash
# static demo — any web server pointed at the repo root, or GitHub Pages
# server — see docs/deploy.md; short version:
cd server && cp config.example.php config.local.php   # fill db / token / anthropic api_key
composer install                                       # anthropic-ai/sdk
mysql -u USER -p DBNAME < install/schema.sql           # EON's own tables
python3 -m pip install -r py/requirements.txt          # optional (xlsx, numpy)
```

Then open `index.html` (served over HTTP). `server/api/health.php` reports what is
connected. `docs/summit-demo.md` is the presentation script.

## Author

Md Imran Hossain — Epal IT Solutions.
