/* ==========================================================
   EON FOR TEACHER — synthetic dataset (DIU-flavoured)
   Deterministic generation (seeded RNG) + hand-crafted special
   cases, so the demo is realistic, stable across reloads, and
   fully offline. Exposes window.T.
   ========================================================== */
(function () {
  'use strict';

  /* ---------- deterministic RNG ---------- */
  function mulberry32(a) { return function () { a |= 0; a = a + 0x6D2B79F5 | 0; let t = Math.imul(a ^ a >>> 15, 1 | a); t = t + Math.imul(t ^ t >>> 7, 61 | t) ^ t; return ((t ^ t >>> 14) >>> 0) / 4294967296; }; }
  const rnd = mulberry32(20260711);
  const pick = (a) => a[Math.floor(rnd() * a.length)];
  const clamp = (x, a, b) => Math.max(a, Math.min(b, x));

  /* ---------- names (Bangladeshi) ---------- */
  const FIRST = ['Rahim', 'Karim', 'Sadia', 'Nusrat', 'Tanvir', 'Fahim', 'Mehjabin', 'Riya', 'Arif', 'Sabbir', 'Mim', 'Jannat', 'Rakib', 'Hasib', 'Farhan', 'Lamia', 'Tasnim', 'Nabila', 'Shakil', 'Rafi', 'Anika', 'Mahir', 'Sumaiya', 'Adnan', 'Priya', 'Naim', 'Israt', 'Tonmoy', 'Shrabonti', 'Siam', 'Afsana', 'Rifat', 'Mithila', 'Emon', 'Bushra', 'Sajid', 'Nazia', 'Turag', 'Maliha', 'Shanto'];
  const LAST = ['Ahmed', 'Hossain', 'Rahman', 'Islam', 'Akter', 'Chowdhury', 'Khan', 'Sarker', 'Mia', 'Uddin', 'Begum', 'Karim', 'Bhuiyan', 'Molla', 'Sheikh', 'Talukder', 'Haque', 'Mahmud', 'Siddique', 'Roy'];

  /* ---------- teacher ---------- */
  const teacher = { name: 'Md Imran Hossain', designation: 'Lecturer', department: 'Computer Science & Engineering', institution: 'Daffodil International University', email: 'imran.cse@diu.edu.bd' };

  /* ---------- courses (COs + topics) ---------- */
  const courses = [
    { code: 'CSE311', title: 'Database Systems', credit: 3, semester: 'Summer 2026', sections: ['A', 'B'], color: '#4f46e5',
      topics: ['er model', 'sql joins', 'normalization', 'transactions', 'indexing'],
      cos: [
        { id: 'CO1', text: 'Design ER models for real-world domains' },
        { id: 'CO2', text: 'Write complex SQL queries including joins & subqueries' },
        { id: 'CO3', text: 'Normalize relations up to BCNF' },
        { id: 'CO4', text: 'Explain transaction management & indexing' }],
      coTarget: 60 },
    { code: 'CSE220', title: 'Data Structures', credit: 3, semester: 'Summer 2026', sections: ['A', 'B'], color: '#0ea5e9',
      topics: ['arrays & linked lists', 'stacks & queues', 'trees', 'graphs', 'sorting'],
      cos: [
        { id: 'CO1', text: 'Implement linear data structures' },
        { id: 'CO2', text: 'Apply trees and graphs to problems' },
        { id: 'CO3', text: 'Analyze algorithm complexity' }],
      coTarget: 60 },
    { code: 'ENG103', title: 'English Composition', credit: 3, semester: 'Summer 2026', sections: ['A'], color: '#0f9d58',
      topics: ['paragraph structure', 'essay writing', 'summarising', 'grammar'],
      cos: [
        { id: 'CO1', text: 'Compose coherent academic paragraphs' },
        { id: 'CO2', text: 'Write structured argumentative essays' }],
      coTarget: 65 },
  ];

  /* ---------- students ----------
     Batch 231 (Sec A: 28, Sec B: 27) take CSE311 + CSE220.
     Batch 233 (Sec A: 26) take ENG103. Deterministic traits drive
     attendance, lateness, notes engagement, ability & trajectory. */
  const students = [];
  let serial = 1;
  function makeStudent(batch, section, i) {
    const name = pick(FIRST) + ' ' + pick(LAST);
    const ability = clamp(0.38 + rnd() * 0.55, 0.3, 0.95);
    // engineered at-risk cluster: last 4 of each 231 section decline hard
    const declining = (batch === '231' && i >= (section === 'A' ? 24 : 23));
    const s = {
      id: `${batch}-35-${String(serial++).padStart(3, '0')}`,
      name, batch, section,
      ability,
      trend: declining ? -0.16 : (rnd() < 0.22 ? 0.10 : (rnd() < 0.18 ? -0.07 : 0.01)),
      attendanceRate: clamp((declining ? 0.55 : 0.72) + rnd() * (declining ? 0.18 : 0.26), 0.4, 0.99),
      lateSubRate: clamp((declining ? 0.45 : 0.08) + rnd() * 0.25, 0, 0.85),
      opensNotes: rnd() < (declining ? 0.25 : 0.68),
      clubs: rnd() < 0.4 ? [pick(['CPC (Programming Club)', 'DIU Robotics', 'Debate Society', 'Cultural Club', 'Sports Club', 'CDS'])] : [],
      awards: rnd() < 0.12 ? [pick(['Intra-DIU Hackathon finalist', 'Inter-university debate — 2nd', 'ICPC preliminary participant'])] : [],
    };
    students.push(s); return s;
  }
  for (let i = 0; i < 28; i++) makeStudent('231', 'A', i);
  for (let i = 0; i < 27; i++) makeStudent('231', 'B', i);
  for (let i = 0; i < 26; i++) makeStudent('233', 'A', i);
  const roster = (batch, section) => students.filter(s => s.batch === batch && s.section === section);
  const enrolled = { CSE311: { A: roster('231', 'A'), B: roster('231', 'B') }, CSE220: { A: roster('231', 'A'), B: roster('231', 'B') }, ENG103: { A: roster('233', 'A') } };

  /* ---------- assessments (questions → topics/COs/Bloom; per-student marks) ----------
     Engineered cohort gaps: CSE311 Sec A weak on NORMALIZATION, Sec B weak on
     SQL JOINS (fixed after a logged re-teach → layer-5 evidence); CSE220 both
     sections shaky on GRAPHS. Marks correlate with attendance + notes access. */
  const BLOOM = ['Remember', 'Understand', 'Apply', 'Analyze', 'Evaluate'];
  function q(no, topic, co, bloom, marks) { return { no, topic, co, bloom, marks }; }
  const assessDefs = [
    { id: 'A1', course: 'CSE311', type: 'Quiz', title: 'Quiz 1 — ER Modeling', daysAgo: 66, weight: 5, questions: [q(1, 'er model', 'CO1', 'Understand', 5), q(2, 'er model', 'CO1', 'Apply', 5)] },
    { id: 'A2', course: 'CSE311', type: 'Quiz', title: 'Quiz 2 — SQL', daysAgo: 45, weight: 5, questions: [q(1, 'sql joins', 'CO2', 'Apply', 5), q(2, 'sql joins', 'CO2', 'Analyze', 5)] },
    { id: 'A3', course: 'CSE311', type: 'CT', title: 'Class Test — SQL + Normalization', daysAgo: 28, weight: 10, questions: [q(1, 'sql joins', 'CO2', 'Apply', 7), q(2, 'normalization', 'CO3', 'Analyze', 8), q(3, 'normalization', 'CO3', 'Apply', 5)] },
    { id: 'A4', course: 'CSE311', type: 'Midterm', title: 'Midterm — ER to Transactions', daysAgo: 8, weight: 25, questions: [q(1, 'er model', 'CO1', 'Apply', 8), q(2, 'sql joins', 'CO2', 'Analyze', 8), q(3, 'normalization', 'CO3', 'Analyze', 9), q(4, 'transactions', 'CO4', 'Understand', 5)] },
    { id: 'B1', course: 'CSE220', type: 'Quiz', title: 'Quiz 1 — Linked Lists', daysAgo: 60, weight: 5, questions: [q(1, 'arrays & linked lists', 'CO1', 'Apply', 10)] },
    { id: 'B2', course: 'CSE220', type: 'Quiz', title: 'Quiz 2 — Stacks & Trees', daysAgo: 38, weight: 5, questions: [q(1, 'stacks & queues', 'CO1', 'Apply', 5), q(2, 'trees', 'CO2', 'Understand', 5)] },
    { id: 'B3', course: 'CSE220', type: 'Midterm', title: 'Midterm — Trees & Graphs', daysAgo: 10, weight: 25, questions: [q(1, 'trees', 'CO2', 'Apply', 10), q(2, 'graphs', 'CO2', 'Analyze', 10), q(3, 'sorting', 'CO3', 'Apply', 10)] },
    { id: 'E1', course: 'ENG103', type: 'Quiz', title: 'Quiz — Paragraph Writing', daysAgo: 40, weight: 10, questions: [q(1, 'paragraph structure', 'CO1', 'Apply', 10)] },
    { id: 'E2', course: 'ENG103', type: 'Midterm', title: 'Midterm — Essay', daysAgo: 12, weight: 25, questions: [q(1, 'essay writing', 'CO2', 'Apply', 15), q(2, 'grammar', 'CO1', 'Remember', 10)] },
  ];
  const weakMap = {
    'CSE311|A|normalization': -0.22, 'CSE311|B|sql joins': -0.20, 'CSE220|A|graphs': -0.17, 'CSE220|B|graphs': -0.19,
    'CSE311|B|sql joins|late': +0.14,   // post-remediation lift on the midterm (layer 5)
  };
  const ERR = ['conceptual gap', 'careless slip', 'incomplete answer'];
  const day = (n) => { const d = new Date(); d.setDate(d.getDate() - n); return d.toISOString().slice(0, 10); };

  const assessments = [];
  assessDefs.forEach((def, ai) => {
    const course = courses.find(c => c.code === def.course);
    course.sections.forEach(section => {
      const rows = enrolled[def.course][section].map(st => {
        const perQ = def.questions.map(qq => {
          let p = st.ability + st.trend * (5 - Math.min(4, Math.floor(def.daysAgo / 18)));   // trajectory over the term
          p += st.opensNotes ? 0.05 : -0.05;
          p += (st.attendanceRate - 0.78) * 0.35;
          const wk = weakMap[`${def.course}|${section}|${qq.topic}`] || 0;
          const lift = (def.type === 'Midterm' && weakMap[`${def.course}|${section}|${qq.topic}|late`]) || 0;
          p += wk + lift + (rnd() - 0.5) * 0.22;
          const got = clamp(Math.round(qq.marks * clamp(p, 0.05, 1) * 2) / 2, 0, qq.marks);
          const lostFrac = 1 - got / qq.marks;
          return { no: qq.no, got, err: lostFrac > 0.4 ? (st.ability > 0.7 ? 'careless slip' : pick(ERR)) : null };
        });
        return { sid: st.id, perQ, total: perQ.reduce((s, x) => s + x.got, 0) };
      });
      assessments.push({ id: def.id + '-' + section, defId: def.id, course: def.course, section, type: def.type, title: def.title, date: day(def.daysAgo), weight: def.weight, questions: def.questions, marks: rows, totalMarks: def.questions.reduce((s, x) => s + x.marks, 0) });
    });
  });
  // upcoming (for dashboard/deadlines)
  const upcoming = [
    { course: 'CSE311', section: 'A+B', type: 'Quiz', title: 'Quiz 3 — Indexing', date: day(-4), weight: 5 },
    { course: 'CSE220', section: 'A+B', type: 'CT', title: 'CT — Graph algorithms', date: day(-7), weight: 10 },
    { course: 'ENG103', section: 'A', type: 'Final', title: 'Final — Composition', date: day(-19), weight: 40 },
  ];

  /* ---------- question bank ---------- */
  const bank = [];
  const bankSeed = [
    ['CSE311', 'er model', 'Understand', 'Draw an ER diagram for a hospital OPD.', 62],
    ['CSE311', 'er model', 'Apply', 'Convert the given ER diagram into relational schema.', 66],
    ['CSE311', 'sql joins', 'Apply', 'Write a query joining 3 tables to list borrowers with fines.', 45],
    ['CSE311', 'sql joins', 'Analyze', 'Explain why a LEFT JOIN returns NULLs here; fix the query.', 28],
    ['CSE311', 'normalization', 'Analyze', 'Decompose relation R(A,B,C,D) with FDs {A→B, B→C} to 3NF.', 8],
    ['CSE311', 'normalization', 'Apply', 'Identify the highest normal form of the given relation.', 8],
    ['CSE311', 'transactions', 'Understand', 'Explain ACID with a bKash transfer example.', 66],
    ['CSE311', 'indexing', 'Understand', 'When does a B+ tree index hurt performance?', null],
    ['CSE220', 'arrays & linked lists', 'Apply', 'Reverse a singly linked list in place.', 60],
    ['CSE220', 'stacks & queues', 'Apply', 'Evaluate a postfix expression using a stack.', 38],
    ['CSE220', 'trees', 'Apply', 'Insert keys into a BST and give the in-order traversal.', 38],
    ['CSE220', 'trees', 'Analyze', 'Why does an unbalanced BST degrade to O(n)?', 10],
    ['CSE220', 'graphs', 'Analyze', 'Trace BFS vs DFS on the given graph; compare uses.', 10],
    ['CSE220', 'sorting', 'Apply', 'Sort with merge sort; count comparisons.', 10],
    ['ENG103', 'paragraph structure', 'Apply', 'Write a paragraph with a clear topic sentence on metro rail.', 40],
    ['ENG103', 'essay writing', 'Apply', 'Argue for/against remote exams in 300 words.', 12],
    ['ENG103', 'grammar', 'Remember', 'Correct the ten sentences (articles & prepositions).', 12],
    ['CSE311', 'transactions', 'Analyze', 'Given a schedule, is it conflict-serializable? Show the graph.', null],
  ];
  bankSeed.forEach((b, i) => bank.push({ id: 'Q' + (i + 1), course: b[0], topic: b[1], bloom: b[2], text: b[3], difficulty: pick(['Easy', 'Medium', 'Medium', 'Hard']), lastUsedDaysAgo: b[4] }));

  /* ---------- resource hub (engagement feeds layer 2) ---------- */
  const resources = [
    { id: 'R1', course: 'CSE311', title: 'Lecture 6 — Normalization walkthrough (PDF)', kind: 'Notes', published: true, opened: 31 },
    { id: 'R2', course: 'CSE311', title: 'SQL joins practice sheet (20 problems)', kind: 'Practice', published: true, opened: 42 },
    { id: 'R3', course: 'CSE311', title: 'Past midterm — Spring 2026', kind: 'Past paper', published: true, opened: 51 },
    { id: 'R4', course: 'CSE311', title: 'Transactions & ACID — slide deck', kind: 'Slides', published: false, opened: 0 },
    { id: 'R5', course: 'CSE220', title: 'Graphs: BFS/DFS animated notes', kind: 'Notes', published: true, opened: 19 },
    { id: 'R6', course: 'CSE220', title: 'Tree problems — practice set', kind: 'Practice', published: true, opened: 33 },
    { id: 'R7', course: 'CSE220', title: 'Sorting cheat-sheet', kind: 'Notes', published: true, opened: 40 },
    { id: 'E8', course: 'ENG103', title: 'Model argumentative essays (5 samples)', kind: 'Notes', published: true, opened: 22 },
    { id: 'R9', course: 'CSE311', title: 'Fundamentals of Database Systems — Elmasri (reference)', kind: 'Book', published: true, opened: 12 },
  ];

  /* ---------- advising log ---------- */
  const secA = roster('231', 'A'), secB = roster('231', 'B');
  const advising = [
    { sid: secA[24].id, date: day(6), topic: 'Attendance slide + missed CT', action: 'Weekly check-in agreed; shared notes pack', followUp: true },
    { sid: secA[25].id, date: day(41), topic: 'Family issue affecting studies', action: 'Referred to student affairs; reduce load', followUp: true },
    { sid: secB[23].id, date: day(9), topic: 'Failing trajectory in CSE311', action: 'Pair-study plan with topper; retake CT', followUp: true },
    { sid: secA[2].id, date: day(15), topic: 'Research assistantship interest', action: 'Gave dataset-cleaning starter task', followUp: false },
    { sid: secB[5].id, date: day(58), topic: 'ICPC team formation', action: 'Approved team; practice slot booked', followUp: false },
    { sid: secA[10].id, date: day(37), topic: 'Sliding quiz scores', action: 'Advised join SQL practice group', followUp: true },
  ];

  /* ---------- teacher's own opportunities ---------- */
  const teacherOpps = [
    { title: 'ICCIT 2026 — paper submission', kind: 'Conference', deadline: day(-24), note: 'Extend the DB-education analytics paper' },
    { title: 'UGC Research Grant — small projects', kind: 'Grant', deadline: day(-38), note: 'Learning-analytics for OBE attainment' },
    { title: 'Journal of Computing Education — special issue', kind: 'Journal', deadline: day(-61), note: 'Exam-integrity tooling study' },
    { title: 'DIU Teaching Excellence Award — portfolio', kind: 'Award', deadline: day(-12), note: 'Compile student feedback + KPI trends' },
    { title: 'Erasmus+ staff mobility — partner visit', kind: 'Mobility', deadline: day(-80), note: 'EU partner university, summer window' },
  ];

  /* ---------- department KPI history (trend food) ---------- */
  const kpiHistory = [
    { sem: 'Fall 2025', gpaAvg: 3.02, passRate: 84, atRisk: 14, coAttain: 58, research: 6, coCurricular: 31 },
    { sem: 'Spring 2026', gpaAvg: 3.08, passRate: 86, atRisk: 12, coAttain: 61, research: 9, coCurricular: 36 },
    { sem: 'Summer 2026 (prev batch @ same point)', gpaAvg: 3.11, passRate: 87, atRisk: 11, coAttain: 63, research: 11, coCurricular: 40 },
  ];

  /* ---------- remediation log (layer 5 evidence) ---------- */
  const remediations = [
    { date: day(22), course: 'CSE311', section: 'B', topic: 'sql joins', action: 'Re-taught joins with live examples + published practice sheet R2', trigger: 'CT: Sec B joins correct-rate 48%' },
  ];

  /* ==========================================================
     EXAM INTEGRITY — CSE311 Midterm, Section A, Question 3:
     “Given R(A,B,C,D) with FDs {A→B, B→C}: explain normalization,
      decompose R, and state the HIGHEST normal form of R as given.”
     Correct final answer: 1NF (R as given is only in 1NF).
     Hand-crafted scripts with engineered overlaps:
       • s07 & s12  — near-identical + SAME WRONG ANSWER (2NF) + seat-adjacent
       • s03 & s21  — high textual overlap, far apart (shared source), both 3NF (wrong)
       • s05 & s09  — innocent textbook-similar, both CORRECT (1NF)
       • s17        — AI-styled, uniform polished prose vs their rough baseline
     ========================================================== */
  const sA = secA;   // 28 students; scripts for first 25
  const SCRIPTS_RAW = [
    /*0*/ 'Normalization means we remove redundancy step by step. R has A→B and B→C so there is transitive dependency, B depend on A and C depend on B. First R is in 1NF becuase values are atomic. It is not 2NF fully... actually partial dependency is not here but transitive is, so highest is 1NF I think. Decompose: R1(A,B), R2(B,C), R3(A,D).',
    /*1*/ 'To normalize we split the relation. Since A→B→C we get transitive dependancy. R given is in 1NF only. For 3NF we make R1(A,B), R2(B,C) and keep D with key: R3(A,D). This removes update anomalies like changing C in many rows.',
    /*2*/ 'Normalization is the systematic process of organizing attributes to minimise redundancy. Given FDs A→B and B→C, a transitive dependency exists, therefore the relation as provided remains in third normal form after decomposition into R1(A,B), R2(B,C), R3(A,D). Highest normal form: 3NF.',
    /*3*/ 'we do normalization for remove duplicate data. A→B, B→C means if we know A we know B and from B we know C. i decompose R1(A,B) R2(B,C) R3(A,D). the relation is 1NF because atomic values but transitive dep stops 3NF.',
    /*4*/ 'A relation is in 1NF when all attributes are atomic. It is in 2NF when there is no partial dependency on the key, and in 3NF when no transitive dependency exists. Here A→B and B→C give a transitive chain, so R as given is only in 1NF. Decomposition: R1(A,B), R2(B,C), R3(A,D). Final answer: 1NF.',
    /*5*/ 'Normalization reduce anomaly. insertion anomaly, deletion anomaly, update anomaly. because B→C is transitive through A→B, the table is not 3NF. splitting into (A,B), (B,C), (A,D) fixes it. as given, highest form = 1NF.',
    /*6*/ 'Normalization is organizing data to reduce redundancy and improve integrity. Given A→B and B→C we find a transitive dependency, so we must decompose. Taking R1(A,B), R2(B,C) and R3(A,D) preserves the dependencies. Since no partial dependency exists the relation is already in second normal form, therefore the highest normal form of R is 2NF.',
    /*7*/ 'Normalizing avoids storing same fact twice. Transitive: A→B→C. Decompose R into (A,B),(B,C),(A,D) which are each 3NF. But R itself as given: atomic values so 1NF, no partial dep so 2NF, transitive dep present so NOT 3NF → answer 2NF? no wait — with a single-attribute key there is no partial dependency question... I will say 1NF to be safe. Not sure.',
    /*8*/ 'A relation is in 1NF when all attributes are atomic. It is in 2NF when there is no partial dependency on the key, and 3NF when no transitive dependency exists. With FDs A→B and B→C there is a transitive chain, so R as given is only in 1NF. We decompose: R1(A,B), R2(B,C), R3(A,D). Final answer: 1NF. This removes the update and deletion anomalies.',
    /*9*/ 'Redundancy makes anomalies. Here knowing A gives B, and B gives C, so C indirectly depends on A. That transitive dependency must be removed for 3NF. My decomposition: R1(A,B), R2(B,C), R3(A,D). The original relation only satisfies 1NF.',
    /*10*/ 'Normalization = breaking big table into smaller good tables. FD A→B and B→C, so transitive. If key is A then B and C both reachable. Decompose (A,B) (B,C) (A,D). Given relation: 1NF highest. Also BCNF for each decomposed one.',
    /*11*/ 'Normalization is organizing data to reduce redundancy and improve integrity. Given A→B and B→C we find a transitive dependency, so we must decompose. Taking R1(A,B), R2(B,C) and R3(A,D) preserves dependencies. Since no partial dependency exists the relation is already in second normal form, so the highest normal form of R is 2NF.',
    /*12*/ 'first normal form need atomic value. second need no partial dependence. third need no transitive. our R has transitive (A to B to C) so it stay in 1NF. break into R1(A,B), R2(B,C), R3(A,D). done.',
    /*13*/ 'The goal of normalization is to eliminate anomalies. Since A determines B and B determines C, C transitively depends on A. Hence R is not in 3NF; as all values are atomic it is in 1NF (and 2NF holds with single-attribute key, but exam answer: 1NF as given per definition chain). Decompose: (A,B), (B,C), (A,D).',
    /*14*/ 'normalization removes repeat data. i think R is in 2NF because no partial dependency. transitive dependency A→B→C means not 3NF. so highest 2NF. decompose R1(A,B) R2(B,C) R3(A,D). anomalies: update anomaly example if C changes.',
    /*15*/ 'Marks: normalization = process of structuring relations. 1NF atomic ✓. Key A single attribute so 2NF ✓ automatically. Transitive dep A→B→C breaks 3NF ✗. So highest = 2NF. R1(A,B), R2(B,C), R3(A,D). BCNF also achieved after decomposition.',
    /*16*/ 'Normalization is a fundamental technique in relational database design that systematically reduces redundancy and prevents anomalies. Furthermore, the functional dependencies A→B and B→C introduce a transitive dependency, which violates the requirements of third normal form. Consequently, the relation should be decomposed into R1(A,B), R2(B,C), and R3(A,D). In conclusion, the relation as given resides in first normal form, and the proposed decomposition elegantly achieves third normal form while preserving all dependencies.',
    /*17*/ 'sir i studied but confusing. normalization mean make table small small. A→B B→C so we cut table three piece (A,B) (B,C) (A,D). i think answer 3NF becuse after cutting it become 3NF.',
    /*18*/ 'Since values are atomic R is 1NF. No composite key so 2NF too. Transitive dependency by A→B→C so not 3NF. Highest as-given: I write 2NF. Decomposition R1(A,B), R2(B,C), R3(A,D) achieves 3NF/BCNF.',
    /*19*/ 'Normalization theory: anomalies happen when facts stored together. Chain A→B→C is the classic transitive case from lecture 6. Decompose to (A,B),(B,C),(A,D). As given the relation sits at 1NF only. Each decomposed relation is in BCNF since the left side of each FD is a key.',
    /*20*/ 'Normalization is the systematic process of organizing attributes to minimise redundancy. Given the FDs A→B and B→C, a transitive dependency exists, therefore the relation as provided remains in third normal form after decomposition into R1(A,B), R2(B,C), R3(A,D). Highest normal form: 3NF.',
    /*21*/ 'remove redundancy → normalization. transitive dependency present so not 3NF. atomic so 1NF. so answer 1NF. tables: R1(A,B), R2(B,C), R3(A,D). example anomaly: delete a row lose B→C info.',
    /*22*/ 'R(A,B,C,D), FDs A→B, B→C. Assume key = AD? If key is AD then B has PARTIAL dependency on A, so 2NF fails → highest 1NF. Decompose (A,B),(B,C),(AD). I think key must include D since nothing determines D. So answer 1NF.',
    /*23*/ 'Normalization মানে data সাজানো — sorry sir, writing eng: it means arranging data to stop redundancy. A→B→C is transitive so not 3NF. All atomic so 1NF yes. Final: 1NF. Split: R1(A,B), R2(B,C), R3(A,D).',
    /*24*/ 'dont remember full. normalization stop copy of data. we make (A,B) and (B,C) table and (A,D). maybe 2NF answer. transitive things break third one.',
  ];
  const scripts = SCRIPTS_RAW.map((text, i) => ({
    id: 'scr-' + (i + 1), sid: sA[i].id,
    text,
    finalAnswer: (i === 6 || i === 11 || i === 14 || i === 15 || i === 18 || i === 24) ? '2NF' : (i === 2 || i === 20 || i === 17) ? '3NF' : '1NF',
    correctAnswer: '1NF',
  }));
  /* per-student writing baseline (stylometry) — avg sentence length (words) &
     type-token ratio from "previous assignments". s17 (idx 16) has a ROUGH
     baseline that clashes with the polished script above. */
  const baselines = {};
  sA.forEach((st, i) => {
    baselines[st.id] = {
      avgSentLen: i === 16 ? 9.2 : 10 + rnd() * 8,
      ttr: i === 16 ? 0.52 : 0.55 + rnd() * 0.2,
      note: i === 16 ? 'Usually short, choppy sentences with frequent spelling slips' : null,
    };
  });
  /* seat map for the exam room (5 columns × 5 rows, row-major).
     s07 (idx 6) and s12 (idx 11) are ADJACENT; s03 (idx 2) and s21 (idx 20) far apart. */
  const seatMap = {};
  const order = [0, 1, 2, 3, 4, 5, 6, 11, 7, 19, 9, 10, 12, 13, 14, 15, 16, 17, 18, 8, 22, 23, 20, 24, 21];
  order.forEach((sIdx, seatNo) => { if (sA[sIdx]) seatMap[sA[sIdx].id] = { row: Math.floor(seatNo / 5) + 1, col: seatNo % 5 + 1 }; });

  const integrity = { exam: 'CSE311 Midterm — Section A · Question 3 (Normalization)', course: 'CSE311', section: 'A', correctAnswer: '1NF', scripts, baselines, seatMap };

  /* ---------- export ---------- */
  window.T = { teacher, courses, students, enrolled, assessments, upcoming, bank, resources, advising, teacherOpps, kpiHistory, remediations, integrity,
    roster, day,
    course: (code) => courses.find(c => c.code === code),
    student: (id) => students.find(s => s.id === id),
  };
})();
