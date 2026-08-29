# EON — the demo script

Twenty questions, in the order to ask them, English and বাংলা.
Every answer below was **captured from the live site**, not written by hand:
`https://eon.gulfrabit.com/eon/`, through `EonDomains.answer()` in a real
browser — the same path the Ask box uses. Captured on build `ed9988b`
(2026-08-20, a Thursday) and re-run whole on build `6e5a307` (2026-08-21).

Two answers below — 6 and 15, both about attendance — are kept from the
Thursday capture on purpose: **Friday and Saturday are the weekend**, and on
those two days EON correctly says the office is closed instead of reading a
roll. Each shows both. If you rehearse on a Friday, that is why.

---

## Before you start — read this or the demo will lie to you

**Sign in to the ERP first, in the same browser.** Two things change:

| | signed out | signed in |
| --- | --- | --- |
| the figures | a **generated demo dataset**, regenerated on *every page load* — the same question gives different numbers two minutes apart | the real ERP, through `dataset.php` |
| an order ("assign X a task") | EON says the ERP asked it to sign in, and posts nothing | EON opens the ERP's own form, shows you the fields, and writes on "yes" |

So the numbers printed below are **not** the numbers you will see. The *shape* of
each answer is what is stable — the sentence, the units, the language, the
follow-up it offers. Read them for that.

Three more things worth knowing before you stand up:

- **Nothing here is generated.** There is no language model connected. Every
  sentence is computed from the ERP by `Nlu → Answer → Analytics`, or in the
  browser by the domain stack. That is the claim to make out loud.
- Ask in either language at any point. EON reads the language of the question
  and answers in it, all the way through the sentence — numbers on the
  Bangladeshi scale (লক্ষ, কোটি) and Bengali numerals in Bangla.
- The company rail's bottom tile opens the concept note (`/eon/concept.html`),
  three A4 pages, if a judge asks "what is this, exactly".

---

## Part 1 — the boss's morning (English)

### 1. `give me the brief`

> Good evening, Imran. Thursday 20 August. Cash stands at ৳5.9 Cr; revenue this month ৳1.5 Cr, tracking up 10% on last month, which closed at a net profit of ৳29 L. Overdue: ৳62 L to collect, ৳45 L to pay. 71 of 73 are in today, 2 absent, 6 late. 13 things need you today: ৳45 L payable is past due (49 items); ৳62 L receivable is overdue across 25 dues; 35 open leads worth ৳23 L have gone cold. 45 approvals are waiting on you — ৳17 L in total. If you do one thing: Release the 19 late salaries first (৳5.7 L) — cash covers it 103× — then settle suppliers older than 30 days.

*The point to make:* one sentence spans four layers — money, people, sales,
operations — and it ends with an instruction, not a dashboard.

### 2. `what should I do today`

> 5 decisions in priority order across the group. First: ৳45 L payable is past due (49 items). Release the 19 late salaries first (৳5.7 L) — cash covers it 103× — then settle suppliers older than 30 days.

The detail lines carry the recommendation for each:

> 2. [Finance · high] ৳62 L receivable is overdue across 25 dues → Call Meghna Foods today and put RFL Plastics and Navana Furniture on a written reminder — collecting the 30+ day bucket alone recovers ৳44 L.
> 4. [Operations · high] 2 projects at risk — worst: Corporate Travel Desk — Nova Textiles → Ask Sakib Talukder for a recovery plan by tomorrow: re-baseline the end date, or add client sign-off — and stop new scope.

*The point to make:* every line names a person, a number and a next action.

### 3. `how much cash do we have`

> Cash and bank across the group: ৳5.9 Cr across 23 accounts. Largest: City Bank – Current (TRAVELS) ৳82 L.

Detail breaks it down by company (Epal Travels ৳1,61,67,838; Epal Constructions
৳1,26,12,478; …). Twelve companies, one question.

### 4. `who owes us money`

> Receivables across the group: ৳1.3 Cr open, of which ৳62 L is overdue across 25 dues. Meghna Foods owes the most — ৳9.5 L, ৳8.3 L overdue, oldest 68 days. Aging: current ৳64 L · 1–30 ৳19 L · 31–60 ৳5.4 L · 61–90 ৳18 L · 90+ ৳20 L.

*The point to make:* the ageing buckets are computed, not stored — and EON
reports the ledger position beside the invoice position, so nobody is sent
chasing money already banked.

### 5. `anything wrong in the books?`

> I checked 2348 journal entries — every one balances, every account exists, none are future-dated. Nothing is wrong in the books.

*The point to make:* this is the one that surprises accountants. On the real
data it does **not** come back clean — it names the trial-balance gap, invoiced
sales that never reached the ledger, payslip arithmetic and duplicate expenses,
each with the remedy. Ask "how do I solve that" and it re-checks rather than
relying on remembering.

### 6. `who is absent today`

> Today across the group: 71 of 73 present (97%), 2 absent, 6 late, 0 on approved leave. Absent: Tuli Mia, Rumana Uddin.
> Late: Rony Sultana (Sales & Marketing) +71 min, in at 10:11 · Fahim Akter +68 min · Arif Islam +55 min …

On a Friday or a Saturday the same question answers:

> Today is the weekend — no attendance expected.

*The point to make if it lands on a weekend:* it knows the working week, so it
refuses to report 73 absences. Follow it with `who came late today`, which
still answers from the 30-day pattern.

### 7. `what is Md Imran Hossain's salary`

> Md Imran Hossain — salary ৳2,50,000/month, 2026-07 paid.
> Managing Director · Management

Then, without naming him again, ask **`his attendance`** — EON remembers who you
were talking about, in either language. Any of 87 staff, 24 parties, 68
passengers, 29 projects and 445 accounts answers the same way.

### 8. `where is payroll`

> Payroll is at FINANCE & HR → Payroll — /super-admin/report/payroll. There you can see the list, overview, get an Excel file.

*The point to make:* EON read the ERP's own source — 971 routes, 205 menu
entries, 203 tables — so it knows the software it lives inside. Say
`open the chart of accounts` and it takes you there.

### 9. `which company is weakest`

> Weakest is Epal Constructions & Interiors at 48 (grade D); healthiest is Epal Group at 73. Epal Constructions & Interiors: finance 52.6, people 43.3, sales 23.2, operations 79.2. Main driver: 71% of receivables overdue (৳19 L of ৳27 L).

Follow with **`why is Epal Constructions & Interiors at 48`** for the full
formula and every driver with the points it cost.

### 10. `what if we cut expenses by 10%`

> If opex down 10%: cash at the end of Nov 2026 is ৳12 Cr instead of ৳11 Cr — better by ৳18 L. Monthly net rises ৳6 L to ৳1.9 Cr.
> Baseline from 2026-07, 2026-06, 2026-05: income ৳2.5 Cr/month, outflow ৳60 L/month.

*The point to make:* the baseline is named, so the projection can be argued with.

### 11. `assign Md Imran Hossain a task: check the August ledger`

**Signed in**, EON shows the exact fields it is about to post into the ERP's own
task form and waits. Say **`yes`** and it writes — through `role.tasks.store`,
with your session and the form's CSRF token, so every rule the ERP enforces
still applies. Money and deletions always ask; a half-filled form stops.

**Signed out** (what was captured):

> I have created nothing: the ERP asked me to sign in before it would show the form. Sign in to the ERP in this browser and ask me again.

*The point to make either way:* EON is not a second write path into the database.
It fills in the ERP's own form. And it never reports a 200 as success — a
Laravel 422, a 419 and the layout's red flash toast are all reported as refusals.

---

## Part 2 — the same system, in বাংলা

Switch language mid-conversation. Nothing is translated: each sentence is written
in Bangla from the same functions the English side reads.

### 12. `আজকের ব্রিফ দাও`

> শুভ সন্ধ্যা, স্যার। আজ বৃহস্পতিবার, ২০ আগস্ট। হাতে নগদ ৳৫.৯১ কোটি; এ মাসে আয় ৳১.৫১ কোটি, গত মাসের তুলনায় ১০ শতাংশ বেশি। গত মাস শেষ হয়েছে ৳২৮.৬ লক্ষ নিট মুনাফায়। বকেয়া: আদায় করতে হবে ৳৬২.৫ লক্ষ, পরিশোধ করতে হবে ৳৪৪.৮ লক্ষ। আজ ৯৭ শতাংশ উপস্থিত — ২ জন অনুপস্থিত, ৬ জন দেরিতে। আজ ১৩টি বিষয়ে আপনার নজর দরকার: ৳৪৪.৮ লক্ষ পরিশোধের সময় পার হয়ে গেছে — ৪৯টি বিলে; ৳৬২.৫ লক্ষ পাওনার সময় পার হয়ে গেছে — ২৫টি বিলে; ৩৫টি খোলা লিড ঠান্ডা হয়ে গেছে, মূল্য ৳২৩ লক্ষ। ৪৫টি অনুমোদন আপনার অপেক্ষায়, মোট ৳১৬.৮ লক্ষ। একটি কাজ যদি করেন: আগে ১৯ জনের বকেয়া বেতন ছাড়ুন (৳৫.৭২ লক্ষ) — হাতের নগদ তার ১০৩ গুণ — তারপর ৩০ দিনের বেশি পুরনো সরবরাহকারীদের মেটান।

*Ask a judge to compare this with #1.* Same numbers, Bengali numerals, লক্ষ and
কোটি, and the only Latin text anywhere is a name the ERP itself stores in
English.

### 13. `ক্যাশ কত আছে?`

> এই মুহূর্তে মোট নগদ ও ব্যাংক ৳৫.৯১ কোটি, ২৩টি হিসাবে।
> City Bank – Current: ৳৮২.২ লক্ষ · bKash Merchant: ৳৭৫.২ লক্ষ · …

Sloppy works too — **`ক্যাশ?`** on its own gets the same answer.

### 14. `কে আমাদের টাকা দেবে?`

> মোট পাওনা ৳১.২৭ কোটি, তার মধ্যে ৳৬২.৫ লক্ষ সময় পার হয়ে গেছে। সবচেয়ে বড় বকেয়া Meghna Foods — ৳৮.২৬ লক্ষ, ৬৮ দিন পার।
> ১. Meghna Foods — ৳৮.২৬ লক্ষ · ৬৮ দিন বকেয়া · ২. RFL Plastics — ৳৬.৩৬ লক্ষ · ১৬০ দিন বকেয়া · …

### 15. `আজ কে অনুপস্থিত?`

> আজ ৭৩ জনের মধ্যে ৭১ জন উপস্থিত, ২ জন অনুপস্থিত এবং ৬ জন দেরিতে এসেছেন।
> অনুপস্থিত: Tuli Mia — Sales & Marketing · অনুপস্থিত: Rumana Uddin — Sales & Marketing

On a Friday or a Saturday, in বাংলা:

> আজ সাপ্তাহিক ছুটি — অফিস বন্ধ, তাই কারও হাজিরা প্রত্যাশিত নয়।

### 16. `আজ কী করা উচিত`

> ৩২টি বিষয় সামনে আছে, তার মধ্যে ১৩টি জরুরি। সবচেয়ে আগে: ৳৪৪.৮ লক্ষ পরিশোধের সময় পার হয়ে গেছে — ৪৯টি বিলে। পরামর্শ — আগে ১৯ জনের বকেয়া বেতন ছাড়ুন (৳৫.৭২ লক্ষ) — হাতের নগদ তার ১০৩ গুণ — তারপর ৩০ দিনের বেশি পুরনো সরবরাহকারীদের মেটান।

### 17. `কোম্পানির স্বাস্থ্য স্কোর`

> গ্রুপের স্বাস্থ্য স্কোর ৫৯ (গ্রেড C)। সবচেয়ে ভালো Epal Group — ৭৩; সবচেয়ে দুর্বল Epal Constructions & Interiors — ৪৮। উপ-স্কোর: আর্থিক ৫৯.৭, জনবল ৭৫.৮, বিক্রয় ৩৫.২, পরিচালন ৫৯.৯।
> ১. Epal Group: ৭৩ (B) — গত মাসে নিট মার্জিন ১৩৯ শতাংশ ঋণাত্মক (৳৩.৫ লক্ষ আয়ে লোকসান ৳৪.৮৬ লক্ষ)
> ২. Epal Properties: ৬৯ (C) — ১২টি খোলা লিডের মধ্যে ৬টি ঠান্ডা হয়ে গেছে (৫০ শতাংশ)

### 18. `Imran এর বেতন কত`

> Md Imran Hossain — বেতন ৳২.৫ লক্ষ/মাস, জুলাই ২০২৬ পরিশোধিত।

*The point to make:* a Latin name inside a Bangla sentence, and a Bangla name
reaching a Latin record, both resolve — রাশেদুল finds Rashedul.

### 19. `প্রজেক্ট কয়টা ঝুঁকিতে?`

> ১১টি প্রকল্প চলমান, তার মধ্যে ২টি ঝুঁকিতে আছে।
> Corporate Travel Desk — Nova Textiles — সময় পার · অগ্রগতি ৮১ শতাংশ
> Gulshan Penthouse — Kitchen & Wardrobes — পিছিয়ে আছে · অগ্রগতি ১৬ শতাংশ

### 20. `Imran কে টাস্ক দাও: আগস্টের লেজার চেক করো`

**Signed in**, the same proposal card as #11, written in Bangla, and **`হ্যাঁ`**
commits it. **Signed out** (what was captured):

> আমি কিছুই তৈরি করিনি — ইআরপি ফর্মটা দেখানোর আগে সাইন ইন চেয়েছে। এই ব্রাউজারে ইআরপিতে লগইন করে আবার বলুন।

---

## Spares, if there is time or a judge pushes

All verified; use them if the room wants something specific.

| ask | what comes back |
| --- | --- |
| `how much did we spend in June` | June's figures, not this month's — a named month is honoured |
| `জুন মাসে লাভ কত` | June's P&L, in Bangla |
| `rank the employees` / `সেরা কর্মী কে` | the leaderboard with scores and grades, in the language asked |
| `what tasks are overdue` | 14 overdue of 80 open, who holds them, and who has spare capacity |
| `ভ্যাট রিটার্ন কবে` | the statutory calendar with the section of the Act behind it |
| `আজ কে দেরিতে এসেছে` | the late list — and on a weekend, the 30-day pattern instead of a roll |
| `what changed since yesterday` | the movement, not the position — **but only from the second day on a given browser**. On a fresh profile it says so plainly: "This is the first day I have a snapshot for." Open the panel once the day before, or skip this one |
| `where do I create a journal entry` | the address — `/super-admin/journals/create` — and what you can do there. Screens that sit on the menu (`where is payroll`) also give the menu path; this one is reached from inside a screen, so it gives the route |
| `cash?` · `due?` · `খরচ` · `বকেয়া` · `cash koto ache` | one-word and Banglish both work |

---

## Do not ask these

Not because they crash — because the answer is **wider than the question**, or
absent, and a judge will notice. Each one has been run; this is what it does.

| do not ask | what actually happens | ask this instead |
| --- | --- | --- |
| `compare this year with last year` · `গত বছরের সাথে তুলনা করো` | no answer at all | `how much did we spend in June` — one period at a time |
| `how many customers do we have in Chittagong` | answers about **all** customers; the place filter is ignored, not refused | `top customers` |
| `forecast next quarter revenue` · `আগামী মাসে আয় কত হবে` | returns the deal pipeline / this month's actuals — not a forecast | `what if we cut expenses by 10%` — the simulator does project, and names its baseline |
| `export the payroll to excel` · `এক্সেল ফাইল দাও` | gives the payroll figures and no file. Python is not on the host | `where is payroll` — the ERP's own screen has the Excel button |
| `draft an email to the auditor` | produces a generic notice with an invented subject | let the decision layer draft: it writes real reminders, warnings and notices off real rows |
| `what is the VAT rate in Bangladesh` | answers with the VAT *calendar* — when Mushak 9.1 is due — not the percentage | `what compliance is due` |
| `show me the cash flow statement` | opens Party Statement. The ERP has no cash-flow statement | `how much cash do we have` |
| `who is the best salesperson this quarter` | offers a dossier instead of a ranking (the quarter is what it cannot scope) | `rank the employees` or `top customers` |
| `give me a five year plan` | no answer — correctly, but it is dead air | — |
| anything about a person by first name only when two people share it | it will pick one | say both names |

**And a general rule for the day:** if EON does not know, it says so. It never
invents a plausible paragraph, because there is no model behind it to invent
one. That is worth saying before someone tries to catch it out — it turns the
one limitation into the headline.

---

## If something goes wrong on stage

- **Numbers look like nonsense / change between questions** → you are signed out
  and looking at the demo dataset. Sign in to the ERP in the same browser and
  reload.
- **An order says it created nothing** → same cause. Sign in.
- **A Bangla answer is not spoken** → the workstation has no Bangla voice.
  `server/api/tts.php` renders it server-side; check
  `GET /server/api/tts.php?status=1` beforehand and add the Azure key.
- **A red Pusher error in the console** → that is the ERP's own bundle (its
  `.env` has no Pusher credentials), not EON. Nothing is affected.
- **The first page load 403s** → Hostinger's CDN browser check. Reload once.
