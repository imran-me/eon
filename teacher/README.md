# Eon for Teacher

A faculty companion demo built for the 5th National Data Science Summit 2026 (theme: AI in Entrepreneurship). It shows what the Eon intelligence layer looks like from the teacher's side of the classroom: courses, batches, grading, and an exam-integrity workflow — all running on analysis done in the browser.

**Live demo:** open `index.html`. That's it — no server, no install, no account. The whole thing runs offline from the file system, so it works the same on a projector laptop with no internet.

All data is synthetic and deterministically generated (a seeded RNG in `data/seed.js`), so every visit shows the same 81 students, the same marks, the same flagged scripts. Nothing here describes a real person.

## What's inside

| Page | What it does |
|---|---|
| Dashboard | Today's classes, upcoming exams, students needing attention, grading queue, Eon's daily brief |
| Courses & Batches | 3 courses, 5 sections, topic plans and CO targets |
| Students | Full roster with risk scores, attendance, trend — click any student for a profile |
| Exams & Question Bank | Assessment builder, per-question topic/CO/Bloom tagging, bank with reuse warnings |
| Results & Grading | Marks distributions, CO–PO attainment, section comparisons |
| KPI & Growth | Semester-over-semester teaching KPIs |
| My Opportunities | Grants, conferences and training matched to the teacher's field |
| EON Intelligence | Five analysis layers: topic/CO diagnostics → correlations & early warning → Monte-Carlo pass-rate forecast → re-teach plan → before/after remediation check |
| Exam Integrity | The flagship. TF-IDF similarity heatmap, shared-passage highlighting, AI-likelihood heuristics, stylometric baseline comparison, seat proximity — assembled into ranked case files |

## On the integrity tool

Everything on that page is a **flag, never a verdict**. Text similarity has innocent explanations — shared lectures, textbook phrasing, group study — so the tool only gathers evidence and ranks it for a human to review. Two matching *correct* answers are deliberately treated as weaker evidence than two matching *wrong* ones, and every case ends in a teacher decision: Cleared, Needs discussion, or Confirmed.

## Tech

Plain HTML/CSS/JS, no framework, no build step, no CDN. The similarity engine (TF-IDF + cosine), the Monte-Carlo forecaster, and the risk models are small hand-written implementations in `assets/js/`.

Built by Md Imran Hossain — Dept. of CIS, Daffodil International University.
