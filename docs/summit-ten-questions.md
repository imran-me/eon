# The ten questions to ask EON in front of the judges

Every question below was asked against the **live** system and the answers here
are what came back. Re-run `python tools/demo-warm.py --token …` an hour before
and again five minutes before: it checks all ten still answer and caches their
audio, because a cold clip takes ~3 seconds to render and a warm one under one.

The order is an argument, not a list. It starts in Bangla to establish the
system is real, moves to reasoning no screen performs, lands on EON finding
money nobody reported, and ends on it knowing the ERP better than the room does.

---

## 1. `আজকের ব্রিফ দাও`
**Proves:** it opens in Bangla, grounded in the live ledger — not a demo dataset.

> সরাসরি বলি — হাতে আছে ৳১.৮৯ লাখ। ৳১৯.৬ লাখ পেমেন্টের তারিখ পেরিয়ে গেছে। ৳১৫.৫ লাখ পাওনা আটকে আছে। আপনার অনুমোদনের অপেক্ষায় ৭ টা বিষয়, মোট ৳৩.২৫ লাখ।

**Say after:** "Nobody prepared that. It read the database when I asked."

## 2. `ক্যাশ কত আছে?`
**Proves:** money in Bangladeshi units — লাখ, কোটি, Bengali numerals — and it
*speaks* it. This machine has no Bangla voice; EON renders its own.

> হাতে আর ব্যাংকে মিলিয়ে আছে এক লাখ ঊননব্বই হাজার টাকা, চোদ্দটা অ্যাকাউন্টে।

**Press the speaker.** The point lands when the room hears it.

## 3. `why is cash so tight`
**Proves:** arithmetic no screen in the ERP performs.

> Cash ৳1.89 L against a burn of ৳2.17 L a month — about 0.9 months, and 99% of that burn is payroll. ৳15.5 L is owed to you and all of it is past due. Against that you owe ৳20.7 L. **So the gap is ৳17.8 L, and collecting every overdue taka would still not close it.**

**Say after:** "That last sentence is the one a director needs, and no report prints it."

## 4. `any accounts error?` — **the centrepiece**
**Proves:** it audits the books and finds what nobody reported.

> **2 things that need an accountant, not a manager.** The desks invoiced ৳9.12 L across 4 invoices this month, but only ৳3.85 L reached the ledger — ৳5.27 L is not journalised, so every profit figure understates the business. **23 of 80 payslips do not add up on their own numbers.** The worst is Md Kamal Hossain for February 2026: gross ৳50k less ৳7.14k of deductions should net ৳42.9k, but the slip says ৳72.9k.

**Pause here.** A ৳30,000 discrepancy on one payslip, found unprompted.

## 5. `how to solve that error`
**Proves:** advisory, not just analytical — and it re-checks rather than
relying on remembering, so it is right even asked cold.

> Post the missing sales — debit 1311 Customer Receivable, credit the 4xxx income account, direct cost to 5xxx… Do not pay against those slips until they are recomputed; check whether an allowance is added outside `gross_salary`…

## 6. `হিসাবে কোনো ভুল আছে`
**Proves:** the same finding in Bangla. Not a translation layer — the reasoning
runs in both languages.

> ২ টা বিষয় আছে যেগুলো ম্যানেজার না, হিসাবরক্ষকের দেখা দরকার…

## 7. `which company is burning money`
**Proves:** it ranks the group honestly, including the caveat.

> EPAL GROUP −৳1.92 L, EPAL IT SOLUTIONS −৳78.3k … EPAL TRAVELS & CONSULTANCY +৳79.6k. **Treat that ranking carefully: ৳5.27 L of invoiced sales is not journalised, so the ledger holds only 42.2% of the real trading.**

**Say after:** "It qualified its own answer. That is the difference between a dashboard and an adviser."

## 8. `why is profit down`
**Proves:** it decomposes until it reaches something actionable.

> The part that moved is overheads: it rose by ৳5.49 L against July. Overheads alone are 1.51 times the income — the loss is structural, not a pricing problem. Payroll is the real overhead: ৳3.35 L, about 58%. And 7 items worth ৳3.25 L have not been approved yet, so they are not in these numbers at all.

## 9. `where do I find payslips`
**Proves:** it knows the ERP itself — 1,376 routes read from the source.

> HR → Payslips — `/super-admin/payslips`. One person's full history is under Payslips → Statement.

## 10. `how is late deduction calculated`
**Proves:** it knows the *rule*, not just the number.

> Late is counted in minutes against the shift start, forgiven up to two hours a month. The moment a person crosses 120 minutes, **the whole amount is charged — not just the excess**.

**Close on:** "It knows the policy, the data, and what to do. In two languages."

---

## Spares, if the room pushes

`which company has the most revenue` · `how long will cash last` ·
`what am I missing` · `what do we owe` · `hate cash koto ache` (roman Bangla —
worth showing that it works) · `beton porishodh hoyeche`

## Do not ask on stage

- **`anything unusual`** — currently finds nothing, which reads as a failure.
  Ask `any accounts error?` instead; it is the same idea with a real answer.
- **Attendance questions before the shift starts** — "today's attendance has not
  come through yet" is true but anticlimactic. Only 27 of 87 staff are on the
  device, so this is EON's weakest ground.
- **`forecast next quarter`** — honest but hedged, because only six months of
  history exist. It says so, which is right, but it is not a winning moment.

## Before you walk on

1. `python tools/demo-warm.py --token …` — all ten answer, audio cached.
2. `python tools/audit-access.py --token …` — 33/33.
3. Add `tts.azure.key` + `region` to `server/config.local.php` if you can. The
   keyless speech endpoint works but is undocumented and rate-limited; Azure's
   `bn-BD-NabanitaNeural` is a real Bangladeshi voice and removes the risk.
4. Server answers land in **15–60 ms**. If you have a slide, that number belongs
   on it.
