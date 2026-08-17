# EON at AI Business Summit 2026 — presentation script

**Where:** China–Bangladesh Friendship Conference Center, Dhaka
**Who is listening:** the country's top technology leaders, ministries, the Prime Minister
**Length:** 12–15 minutes (10 of them live on the Command Center)
**Presenter:** Md Imran Hossain, Epal IT Solutions
**What is on the screen:** `index.html` — the EON Command Center — served from
`https://erp.epal.com.bd/eon/`, next to the ERP it reads

Everything below is written to be spoken. Short sentences. Pause where it says
pause. The words in **bold quotes** are the exact things you say *to EON*; the
words after "You'll see" are what the room sees on the big screen.

---

## Timing at a glance

| Minute | Part |
| --- | --- |
| 0:00 – 1:30 | 1. Opening line and the problem |
| 1:30 – 2:15 | 2. EON in one sentence |
| 2:15 – 11:00 | 3. Live demo (thirteen things you say to EON) |
| 11:00 – 12:15 | 4. The architecture, in plain words |
| 12:15 – 13:30 | 5. Why it matters for Bangladesh |
| 13:30 – 14:30 | 7. Closing (part 6 is for you, not the audience) |

---

## 1. Opening line and the problem (1:30)

*Walk to the centre. Screen is dark or shows the EON logo. Do not touch the laptop yet.*

> Honourable Prime Minister, distinguished guests — good morning.
>
> I run a group of twelve companies. Travel, software, construction, an online
> shop, interiors, manufacturing, property. Every one of them runs on the same
> ERP — one database, twelve companies, hundreds of people punching in every
> morning, thousands of journal entries a month.
>
> And every morning I used to open twelve dashboards.
>
> *(pause)*
>
> Twelve dashboards. Trial balances I had to read myself. Receivables I had to
> add up myself. An attendance page here, a payroll page there, a CRM page
> somewhere else. The data was all there. The *decision* was never there. I was
> the integration layer. Every owner in this room is the integration layer.
>
> Bangladesh has more than ten million small and medium enterprises. Their
> bosses are doing exactly what I was doing — reading, not deciding.
>
> So we built the thing that reads for the boss, and hands the boss the decision.

## 2. EON in one sentence (0:45)

*Wake the screen. The Command Center appears on the Brief.*

> This is EON. One brain, multi decision layer, voice-first.
>
> **One brain** — one memory, one place to ask, over the whole ERP: accounts,
> payroll, HR, expenses, CRM, tasks, projects.
> **Multi decision layer** — finance, people, sales, operations each raise their
> own decisions, and EON ranks them into one list.
> **Voice-first** — you talk to it, in English or in Bangla, and it talks back
> with the number, the rule behind the number, and what to do.
>
> Let me stop describing it. Let me just talk to it.

## 3. Live demo (8:45)

*Before you start: the pill next to the title should read **Live · ERP + language
model**. The Scope selector should say **Whole group**. Microphone is the
lapel/handheld mic feeding the laptop, not the laptop's own mic. Speaker volume
tested. Press the mic button (🎙, top right) once for each question, or tick
"conversation mode" and prefix every question with "EON".*

### 3.1 The brief

*Say to EON:* **"EON, brief."**

*You'll see:* the Brief screen — KPI tiles (cash, receivables, payables, revenue,
net profit, headcount, attendance today, payroll, pipeline, overdue tasks,
projects at risk), then the ranked decision list, then the approvals card. EON
speaks the morning brief aloud: cash, revenue trend, overdue to collect and to
pay, who is in today, the two or three things that need you, and "if you do one
thing" — the single most important action.

*While it speaks, say nothing. Let the room hear a company being briefed by its
own data. Then:*

> That is the morning I used to spend forty minutes on. Every line you heard
> came from a ledger row, an attendance punch, a payment schedule — and every
> line came with a rule attached and an action attached. This is also what
> lands in my inbox at eight every morning, from a cron job on the same server.

### 3.2 Money

*Say to EON:* **"Who owes us money?"**

*You'll see:* receivables — total open, total overdue, aging buckets (current,
1–30, 31–60, 61–90, 90+ days), the top debtors, and one suggested action:
"Draft reminder to …". EON names the customer who owes the most and how old the
oldest due is.

> The ERP has no aging report. EON computes it from the payment schedules, live.
> Notice it did not just give me a number — it gave me who, how much, how old,
> and what to do next.

### 3.3 People — right now

*Say to EON:* **"Who is absent today?"**

*You'll see:* today's attendance — present, absent, late, on approved leave, not
punched yet, with names. The late-comers listed with minutes late and check-in
time.

> Present, absent, late, on leave — from the attendance machines and the selfie
> check-ins, this morning. If it is a Friday or a public holiday EON will tell
> me so and not invent an attendance report.

### 3.4 People — one person

*Say to EON:* **"Evaluate Afiqur Rahman."**
*(Live database: use a real employee you have chosen beforehand — a partial name
is enough. `Afiqur Rahman` is the project manager in the demo dataset.)*

*You'll see:* the employee card — score out of 100 and grade, attendance and
punctuality percentages, late days, tasks done / on time / overdue / open, sales
won and lost if the person sells, leave balance, running loan, last net pay —
and a short spoken narrative with strengths and concerns.

> Attendance, punctuality, task delivery, sales, leave, loans, pay — one
> evaluation, thirty days, no HR spreadsheet. The heavy version of this — every
> employee, every department, attrition risk — runs in Python on the same server.

### 3.5 Payroll

*Say to EON:* **"Payroll."**

*You'll see:* the latest payroll run — heads, gross, deductions split into late,
absence, loan recoveries and advances, overtime paid, net, change against the
previous month, how many payslips are still unpaid, and the by-company table.

> Payroll across the group in one sentence, and it knows which payslips are
> still sitting in Salaries Payable.

### 3.6 Forward-looking

*Say to EON:* **"Forecast next quarter."**

*You'll see (Live):* a three-month forecast of income, outflow, net and
month-end cash with an 80% band, growth per month and runway. That table came
from `server/py/eon.py forecast` — Python, called by PHP, on the ledger history.
*(Static fallback: EON answers with the sales forecast — open deals, what closes
within 14 days, what has slipped.)*

> This is where the model stops reading and starts projecting — least-squares
> on the ledger, with an honest band around it, not a promise.

### 3.7 The auditor

*Say to EON:* **"Any anomalies?"**

*You'll see:* spending anomalies — categories running far above their usual
level (like-for-like by day of month), which company, which month, how many
times normal; and, in Live mode, possible duplicate charges.

> Nobody asked the accountant. The system noticed. That is what an always-on
> brain does between your questions — the hourly watcher on the server pushes
> the critical ones to my phone.

### 3.8 EON writes

*Say to EON:* **"Draft a payment reminder."**

*You'll see:* a full letter — subject line, the customer who owes the most, the
amount, days overdue, seven-day settlement request, signed by Accounts — ready
to send. EON says: "Say send and I will queue it."

> It knew who to write to because it had just told me who owes the most.

### 3.9 Approvals

*Say to EON:* **"Approvals."**

*You'll see:* the approvals queue — expenses, leaves, salary advances, employee
requests, the payroll run — count, total amount, the biggest item first, with
red flags on the risky ones.

*Now approve one. Click **Approve** on the first row (or, in Live mode, say
"EON, approve the first one").*

*You'll see:* the row turns to "approved", and a toast: **"Approved: … — queued
for the ERP."**

> This is the important line: *queued for the ERP.* EON is advisory. It reads
> the ERP through a read-only database user; it never writes a journal, never
> touches a payslip. My decision is logged as an EON action and the ERP — the
> system of record — executes it with the permission it already has. The AI
> recommends; the accountable system acts.

### 3.10 The Bangla moment

*Tap the language chip so it reads **বাংলা**. Speak in Bangla:*

*Say to EON:* **"ইয়ন, আজ কে কে অনুপস্থিত?"** *(EON, who is absent today?)*

*You'll see:* the same attendance answer, spoken back in Bangla.

> The same brain. Same data. Bangla in, Bangla out. An owner in Bogura or
> Khulna does not have to speak English to run a company with AI.

*Tap the chip back to **English**. (Bangla understanding lives in the
language-model path — if the pill is not Live, skip this step and show the
backup clip instead of a failed answer.)*

### 3.11 The accountant's questions

*Say to EON:* **"What is 2210?"**

*You'll see:* "2210 is Salaries Payable — a liability account (Accrued &
statutory); it normally carries a credit balance", the account range it sits in
and — in Live mode — its recent postings and closing balance.

*Say to EON:* **"How is late deduction calculated?"**

*You'll see:* the payroll rule — late deduction = late minutes × per-minute
rate, applied only when the month's late minutes reach 120; per-minute = daily
÷ 9 ÷ 60; daily = salary ÷ days in the month.

> This is the difference between an AI that talks *about* accounting and one
> that knows *this* company's chart of accounts and *this* company's payroll
> service, line for line.

### 3.12 Scope

*Change the Scope selector from **Whole group** to **Epal Travels & Consultancy**
— or simply say:*

*Say to EON:* **"Who owes us money at Epal Travels?"**

*You'll see:* the receivables answer again, now for one company only, the answer
prefixed "at Epal Travels & Consultancy".

> Twelve companies, one brain. Group view or one company — same question, same
> voice, one selector.

*Set Scope back to Whole group. Close the demo:*

> Thirteen questions. Not one dashboard opened. Not one report exported. That is
> the whole idea.

## 4. The architecture, in plain words (1:15)

*Architecture slide (or keep the Command Center up — the diagram is simple
enough to say).*

> How is it built? Deliberately boring, deliberately Bangladeshi.
>
> **The ERP** is Laravel and MySQL, on Hostinger, in Dhaka's ordinary shared
> hosting. EON lives in a folder next to it.
> **PHP** — plain PHP 8.2 — is the server: it reads the ERP database through a
> **read-only** user, computes cash, receivables, aging, trial balance,
> attendance and payroll, keeps EON's memory in six small tables of its own,
> and runs two cron jobs — the morning brief at eight and an hourly watcher.
> **Python** does the heavy analytics — forecasting, anomaly detection, the
> employee evaluation model, Excel reports — called by PHP, JSON in, JSON out.
> **The language model** — Claude, through the official PHP SDK — is the
> reasoning layer. It does not guess numbers. It is given tools: get the brief,
> get receivables, find an employee, run the forecast, record an action. It
> calls the tool, reads the result, and answers. Every figure you heard came
> out of a tool, not out of the model's imagination.
> **JavaScript** is the face — the companion, the screens, the voice in and
> out — using the browser's own speech engine, English or Bangla.
>
> And it degrades gracefully. Server and model up: **Live**. Server up, no
> model: **real ERP numbers, rule-based brain**. No server at all — this same
> page on GitHub Pages: **demo data, offline brain, still talking**. The demo
> you just saw could not die.

## 5. Why it matters for Bangladesh (1:15)

> Four reasons this matters here.
>
> **Decision speed for SMEs and enterprises.** The ten million owners who are
> their own integration layer get their mornings back. Not a report — a ranked
> list of what to do today, and the letter already drafted.
>
> **ERP-native AI.** Not a chatbot bolted on the side. It knows the chart of
> accounts, the posting rules, the payroll formula, the approval workflow —
> because it reads them from the running system. Any Laravel-and-MySQL ERP in
> this country can carry it.
>
> **Bangla voice.** The interface is speech, in our language. Literacy in
> English, or in dashboards, is not a requirement to run a business with AI.
>
> **Sovereignty of data.** The company's data does not leave the company's own
> server. The database is read by a read-only user on the same host. The only
> thing that leaves is the model call — the question and the tool results it
> needs — and even that is optional: without a key, EON still answers with its
> own rule brain. Nothing is uploaded, nothing is synced to a foreign SaaS.

## 6. Risk mitigations for the demo (for you, not spoken)

| Risk | Mitigation |
| --- | --- |
| Venue Wi-Fi drops or the server is unreachable | The page falls back by itself: `erp-adapter.js` probes `server/api/health.php` with a 3.5-second timeout; if it fails, EON loads the demo dataset and the offline brain. Every question in section 3 (except the Bangla ask) has an offline answer. Keep the pill in view; if it says **Static** or **Server**, carry on — the script still works. Also keep a phone hotspot up as the second network. |
| Model call slow or failing | `ask.php` falls back to the server's rule brain (`Brain::askOffline`) automatically and tells you in the trace line. If the model is down for good, switch to the static copy of the page (same repo on GitHub Pages / local `file://`) — nothing in the flow changes except forecast and anomalies use the JavaScript layers. |
| Cold start (first call slow) | Pre-warm ten minutes before: open the page, ask "brief" once and "forecast next quarter" once. The dataset cache is 5 minutes on the server (`cache_ttl` in `config.local.php`) and 5 minutes in the browser (`cacheMinutes`); the system prompt is cached on the model side. Ask "brief" again at T-2 minutes to keep both warm. |
| Speech recognition mishears | Speak the exact phrases in section 3 — they are the ones the intents are tuned for. If EON hears wrong, the "heard" line under the toolbar shows the transcript; click the mic and say it again. Fallback: type the same phrase in the Ask EON box — same brain, same answer. |
| Text-to-speech has no voice / no Bangla voice on the laptop | Test both languages in the pre-flight. If the machine has no `bn-BD` voice, EON still speaks the Bangla answer text with the default voice; if that sounds wrong, click the mute chip (🔇) and read the answer from the screen yourself. The screen always shows the answer text. |
| Room noise triggers conversation mode | Do not tick conversation mode in the hall. Use push-to-talk (click the mic per question). |
| Everything fails at once | The backup video (recorded from a Live session, same script, 9 minutes, English + the Bangla moment) is on the laptop desktop and on a USB stick with the AV desk. Say: "Let me show you this morning's run," and narrate over it. |
| Real ERP data shows something sensitive | Decide beforehand: Live on the real database with the Scope set to a company you are comfortable showing, or the demo dataset (`server/storage/data/demo-dataset.json`, `db.enabled = false`) which mirrors the schema with synthetic names. Either way rehearse with the same setting you will present with. |
| Approving something real | In Live mode "Approve" only logs an EON action (`eon_actions`, status queued); nothing is written to the ERP. Say so on stage — it is a feature. |

## 7. Closing (1:00)

*Return to the Brief screen. Stand still.*

> Every business in this room already has the data. What it lacks is the
> forty minutes every morning to turn that data into a decision.
>
> EON gives those forty minutes back — in the owner's own language, from the
> owner's own server, on the ERP the owner already runs.
>
> One brain. Multi decision layer. Voice-first.
>
> It is running today at erp.epal.com.bd. It is built to run on any Laravel ERP
> in Bangladesh tomorrow.
>
> Honourable Prime Minister, distinguished guests — thank you.

*(If time remains and questions are invited: the honest answers are — read-only
database user; the model sees the question and the tool results, not the
database; the ERP executes, EON recommends; runs on ordinary shared hosting; the
static demo is public on GitHub Pages.)*

---

## Pre-flight checklist

Do this the night before **and** again in the hall, one hour before you go on.

**Server (SSH into Hostinger)**

- [ ] `curl -s https://erp.epal.com.bd/eon/server/api/health.php` → `"ok":true`, `"db":true`, `"llm":true`, `"sdk":true`, `"source":"erp"` (or `"demo"` if that is the plan)
- [ ] `server/config.local.php` — `db.enabled`, `db.user = eon_readonly`, `anthropic.api_key` set (or `ANTHROPIC_API_KEY` in the environment), `anthropic.model = claude-opus-5`, `token` set and the same token stored in the browser (`localStorage.eon_token`) if you enabled auth, `origins` includes the page's origin, `python.bin` pointing at `python3`
- [ ] `composer install` done in `server/` (`vendor/anthropic-ai/sdk` present)
- [ ] `mysql … < server/install/schema.sql` applied — `eon_settings` shows `installed`
- [ ] `python3 server/py/eon.py health` → ok; `python3 -m pip install -r server/py/requirements.txt` done (openpyxl for xlsx export)
- [ ] `php server/cron/morning-brief.php` runs clean; today's brief mail arrived; hPanel cron rows exist for `morning-brief.php` (08:00) and `watch.php` (hourly)
- [ ] `server/storage/{cache,logs,data}` writable; `storage/logs/eon.log` has no errors from the last hour
- [ ] `server/.htaccess` in place — `lib/`, `cron/`, `install/`, `storage/`, `vendor/`, `py/` return 403 from the browser; only `api/` answers

**Data**

- [ ] The employee name for 3.4 chosen and tested with `brief.php?what=employee&name=…`
- [ ] The company for 3.12 chosen; the Scope selector lists it
- [ ] Ask "who owes us money" once and check the top debtor is a name you are happy to show
- [ ] If demo mode: `tools/make-demo-dataset.mjs` regenerated so "today" is today

**Laptop / browser**

- [ ] Chrome or Edge (Web Speech API), latest; the page open in one tab, nothing else
- [ ] Page loads at `https://erp.epal.com.bd/eon/`; pill reads **Live · ERP + language model**; footer reads `erp · server ok · voice on`
- [ ] Microphone permission granted for the site; the right input device selected in the OS (the venue's mic feed)
- [ ] English voice and Bangla (`bn-BD`) voice installed and audible through the hall speakers — click **🔊 Read brief** to test output; ask "brief" by voice to test input
- [ ] Mute chip shows 🔊 speaks; conversation mode unticked; language chip on English
- [ ] Zoom the browser so the KPI tiles and the chat are readable from the back row (typically 125–150 %)
- [ ] Notifications, screen-saver, sleep, auto-updates off; power connected; second network (phone hotspot) ready
- [ ] Static fallback ready: the same repo checked out locally and served (any local web server on the repo root) — opens with the pill on **Static · demo data · offline brain**; keep it in a second window
- [ ] Backup video on the desktop and on the AV desk's USB; you have played it once on this laptop with sound

**Ten minutes before**

- [ ] Ask "brief" and "forecast next quarter" once each to warm the caches; check the trace line shows `language model · claude-opus-5 · tools: …`
- [ ] Set Scope back to **Whole group**; clear the chat (reload the page); pill still **Live**
- [ ] Bring the Brief screen up; dim the laptop screen; wait for the cue

---

## Voice command cheat sheet (one page)

Say the phrase as written; press the mic (🎙) first, or start with "EON," in
conversation mode. Typing the same words in the Ask box gives the same answer.

**Every morning**
- **"Brief."** — the ranked morning briefing (also 🔊 Read brief)
- **"What should I focus on?"** — decisions in priority order
- **"Approvals."** — everything waiting for you; then click Approve / Reject
- **"Any anomalies?"** — unusual spending, duplicate charges

**Money**
- **"Cash position."** — bank and cash by account and company
- **"Who owes us money?"** / **"Receivables."** — debtors, overdue, aging
- **"What do we owe?"** / **"Payables."** — creditors, late salaries, due in 7 days
- **"Overdue."** — both sides, oldest first
- **"Profit this month."** / **"Revenue last month."** — P&L, margin, run-rate
- **"Trial balance."** / **"Balance sheet."**
- **"Budget."** / **"Where does the money go?"** — expenses vs budget
- **"Runway."** — how long the cash lasts
- **"Top customers."** / **"Top suppliers."**
- **"Forecast next quarter."** — Python forecast (Live) / deals closing (offline)

**People**
- **"Who is absent today?"** / **"Who came late?"** / **"Who is online?"**
- **"Headcount."** — staff and payroll by company / department
- **"Payroll."** / **"Payroll for June."** / **"Salary of Sadia Sultana."** (one payslip)
- **"Leave requests."** / **"Leave balance of Tanvir Ahmed."**
- **"Loans and advances."**
- **"Evaluate ‹name›."** / **"Who is ‹name›?"** / **"Best performers."** / **"Worst performers."**

**Sales**
- **"Pipeline."** / **"Stale leads."** / **"Follow-ups today."** / **"Deals closing soon."** / **"Best sales agent."**

**Operations**
- **"Overdue tasks."** / **"Tasks due today."** / **"Projects at risk."** / **"Who is overloaded?"** / **"Office to-dos."**

**Companies**
- **"Compare companies."** — net by company this month
- Add a company name to any question: **"… at Epal Travels"**, **"… at Wood Art"**, **"… at Epal IT"** — or use the Scope selector

**EON writes**
- **"Draft a payment reminder."** (optionally "… to ‹customer›")
- **"Draft a warning letter."** (optionally "… to ‹employee›")
- **"Draft a meeting agenda."** / **"Draft a notice about ‹subject›."**
- Live mode: **"Export receivables to Excel."** — Python report, download link

**Explain**
- **"What is 2210?"** — any 4-digit account code
- **"How is late deduction calculated?"** / **"How is payroll calculated?"**
- **"How is an expense posted?"** / **"What is a payment schedule?"** / **"What can you do?"**

**Bangla** — tap the language chip (**বাংলা**), then:
- **"ইয়ন, আজ কে কে অনুপস্থিত?"** — who is absent today
- **"ইয়ন, কারা আমাদের টাকা পাওনা রেখেছে?"** — who owes us money
- **"ইয়ন, ব্রিফ দাও।"** — give me the brief
- *(Bangla answers need Live mode; tap the chip back to English afterwards.)*

**Controls**
- 🎙 mic — push-to-talk (click per question)
- Conversation mode — hands-free, every sentence starts with "EON"
- 🔊 / 🔇 chip — speak or mute answers
- Scope selector — Whole group or one company
- 🔊 Read brief — speak today's brief again
