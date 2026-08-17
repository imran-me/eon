/* ============================================================
   EON · teacher-adapter.js — the shim layer that lets the same
   companion that runs on the student system live inside this
   fully-offline teacher demo.

   Provides, in load order (classic script, before the bundle):
     1. window.OWNER_EMAIL + money formatters the brain expects
     2. an offline Firestore-compatible store (localStorage) —
        collection(c).doc(d).get()/.set(v,{merge}) is all the
        brain uses, so that is all we emulate
     3. the teacher dataset mapped into the store shape the
        brain's discovery engine reads (schema is auto-inferred,
        which is the whole point of the portable design)
     4. window.TeacherQA — teacher-shaped answers over live data
     5. demo reminders (seeded once) so Reminders/messages have
        real content on first open
     6. brain configuration for this space (deadline entities +
        link patterns) — the shared core reads window.EON_BRAIN_CONFIG

   The companion itself is loaded by the page as ES modules
   (../ai-companion/js/boot.js + an import map for three) — the
   generated single-file bundle is no longer used.
   ============================================================ */
'use strict';
(function () {
  if (window.__EON_TEACHER_ADAPTER) return;
  window.__EON_TEACHER_ADAPTER = true;
  if (location.search.indexOf('noeon') !== -1) return;   // harness escape hatch

  /* ---------- 1. identity + formatters ---------- */
  const T = window.T;
  if (!T) { console.warn('[EON adapter] seed missing — companion not started'); return; }
  window.OWNER_EMAIL = T.teacher.email || 'me.imran.personal@gmail.com';
  if (!window.fmtBDT) window.fmtBDT = (n) => '৳' + Math.round(+n || 0).toLocaleString('en-IN');
  if (!window.fmtBDTk) window.fmtBDTk = (n) => { n = +n || 0; return n >= 1000 ? '৳' + (n / 1000).toFixed(1).replace(/\.0$/, '') + 'k' : window.fmtBDT(n); };

  /* ---------- 2. offline Firestore shim ---------- */
  const LS = 'eonfs::';
  const read = (k) => { try { return JSON.parse(localStorage.getItem(LS + k)); } catch { return null; } };
  const write = (k, v) => { try { localStorage.setItem(LS + k, JSON.stringify(v)); } catch { /* quota — demo keeps running in-memory */ } };
  const mem = {};
  const deepMerge = (dst, src) => {
    Object.keys(src || {}).forEach((k) => {
      if (src[k] && typeof src[k] === 'object' && !Array.isArray(src[k]) && dst[k] && typeof dst[k] === 'object' && !Array.isArray(dst[k])) deepMerge(dst[k], src[k]);
      else dst[k] = src[k];
    });
    return dst;
  };
  function docRef(col, id) {
    const key = col + '/' + id;
    return {
      async get() {
        const v = mem[key] !== undefined ? mem[key] : read(key);
        return { exists: v != null, data: () => (v == null ? undefined : JSON.parse(JSON.stringify(v))) };
      },
      async set(v, opts) {
        let cur = mem[key] !== undefined ? mem[key] : (read(key) || {});
        const next = (opts && opts.merge) ? deepMerge(JSON.parse(JSON.stringify(cur)), v) : JSON.parse(JSON.stringify(v));
        mem[key] = next; write(key, next);
      },
      async update(v) { return this.set(v, { merge: true }); },
    };
  }
  const AUTH = {
    currentUser: { email: window.OWNER_EMAIL, uid: 'teacher-demo', displayName: T.teacher.name },
    onAuthStateChanged(cb) { setTimeout(() => cb(AUTH.currentUser), 0); return () => {}; },
    async signInAnonymously() { return { user: AUTH.currentUser }; },
  };
  const FS = () => ({ collection: (c) => ({ doc: (d) => docRef(c, d) }) });
  FS.FieldValue = { serverTimestamp: () => new Date().toISOString() };
  window.firebase = { apps: [{ name: '[DEFAULT]' }], initializeApp() { return window.firebase.apps[0]; }, auth: () => AUTH, firestore: FS };

  /* ---------- 3. the teacher dataset, store-shaped ---------- */
  const iso = (d) => d instanceof Date ? d.toISOString().slice(0, 10) : String(d || '');
  const attPct = (s) => Math.round((s.attendanceRate || 0) * 100);
  const store = {
    courses: T.courses.map((c) => ({
      code: c.code, name: c.code + ' — ' + c.title, credits: c.credits || 3,
      sections: (c.sections || []).join(', '), semester: 'Summer 2026',
      topics: (c.topics || []).join(', '),
    })),
    students: T.students.map((s) => ({
      name: s.name, id: s.id, batch: s.batch, section: s.section,
      attendance: attPct(s) + '%', notes: s.clubs && s.clubs.length ? 'Clubs: ' + s.clubs.join(', ') : '',
    })),
    assessments: T.assessments.map((a) => ({
      name: a.title + ' · ' + a.course + ' Sec ' + a.section, course: a.course, section: a.section,
      date: iso(a.date), weight: (a.weight || 0) + '%',
      status: Date.parse(a.date) < Date.now() ? 'Graded' : 'Scheduled',
    })),
    advising: (T.advising || []).map((v, i) => {
      const st = T.students.find((s) => s.id === (v.sid || v.student));
      const who = st ? st.name : (v.student || v.sid || 'student');
      // follow-ups become the *next* touchpoint (future-dated); closed sessions stay history
      let date = v.date, status = 'Completed';
      if (v.followUp) {
        const d = new Date(); d.setDate(d.getDate() + 2 + i * 2);
        date = d; status = 'Follow-up scheduled';
      }
      return { name: 'Advising — ' + who, student: who, date: iso(date), status, note: (v.topic || '') + (v.action ? ' · ' + v.action : '') };
    }),
    opportunities: (T.teacherOpps || []).map((o) => ({
      name: o.title, org: o.org || o.venue || '', type: o.type || 'Opportunity',
      deadline: iso(o.deadline), status: o.status || 'Exploring', fit: o.fit != null ? o.fit + '%' : '',
    })),
    classes: (window.SCHEDULE || T.schedule || []).map((s) => ({
      name: s.course + ' Sec ' + s.section, day: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'][s.day] || s.day,
      time: s.time, room: s.room, status: 'Recurring',
    })),
  };
  // refresh the source doc whenever the seed signature changes (so demo edits show up)
  const ADAPTER_V = 2; // bump to refresh the cached store after mapping changes
  const sig = ADAPTER_V + ':' + JSON.stringify([store.courses.length, store.students.length, store.assessments.length,
    (T.assessments[0] || {}).title, T.teacher.name]).length + ':' + store.students.length;
  const SRC = 'opptrack/data';
  const cur = read(SRC);
  if (!cur || cur.__sig !== sig) write(SRC, { store, __sig: sig, updatedAt: new Date().toISOString() });

  /* ---------- 4. TeacherQA — grounded, teacher-shaped answers ---------- */
  const fmtList = (arr) => arr.filter(Boolean).join(' · ');
  function flaggedTop(n) {
    try { return (window.A ? A.flagged() : []).slice(0, n || 3); } catch { return []; }
  }
  const INTENTS = [
    { re: /at.?risk|attention|struggling|worried|falling behind|who.*(flag|risk)/i, a() {
      const f = flaggedTop(3);
      if (!f.length) return 'No one is above the risk threshold right now — the cohort looks steady.';
      return 'Most at-risk right now: ' + f.map(x => `<b>${x.s.name}</b> (risk ${x.r.score})`).join(', ') +
        '. Their shared signal is attendance + late submissions, not just marks. Full list on the AI Insights page.';
    } },
    { re: /next class|today.*class|class.*today|schedule today|my schedule/i, a() {
      const day = new Date().getDay();
      const today = (window.SCHEDULE || []).filter(s => s.day === day);
      if (!today.length) return 'No classes today — it\'s a grading & research day. Next sessions are on the Dashboard.';
      return 'Today: ' + today.map(s => `<b>${s.course}</b> Sec ${s.section} at ${s.time} in ${s.room}`).join(' · ') + '.';
    } },
    { re: /upcoming (exam|assessment|quiz)|exam.*(when|next)|next (exam|quiz|ct)/i, a() {
      const up = (T.upcoming || []).map(u => ({ ...u, d: Math.ceil((Date.parse(u.date) - Date.now()) / 864e5) })).sort((a, b) => a.d - b.d).slice(0, 3);
      if (!up.length) return 'Nothing scheduled ahead right now.';
      return 'Coming up: ' + up.map(u => `<b>${u.title}</b> (${u.course} Sec ${u.section}) in ${u.d}d`).join(' · ') + '.';
    } },
    { re: /re.?teach|weakest topic|focus.*(class|teach)|what should i teach|where.*gaps?/i, a() {
      try {
        const c = T.courses[0].code; const tp = A.topics(c).sort((a, b) => a.rate - b.rate)[0];
        return `Weakest topic in ${c}: <b>${tp.topic}</b> at ${Math.round(tp.rate * 100)}% correct — that is a re-teach signal for the whole room, not a few weak students. The EON Intelligence page has the full per-course map.`;
      } catch { return 'Open EON Intelligence → Layer 1 for the per-topic gap map of each course.'; }
    } },
    { re: /co.?attain|attainment|obe|po\b/i, a() {
      const k = T.kpiHistory[T.kpiHistory.length - 1];
      return `Department CO attainment is at <b>${k ? k.coAttain + '%' : '—'}</b> this term. Per-CO bars and the CO–PO matrix live on the OBE Analytics page — CO3 is the one under target.`;
    } },
    { re: /integrity|plagiar|copy|cheat|similar/i, a() {
      const n = (T.integrity && T.integrity.scripts || []).length;
      return `Integrity engine has analysed <b>${n} scripts</b> from the CSE311 midterm — 2 high-priority pairs and 1 AI-likelihood flag are waiting for YOUR decision (flags, never verdicts). Open Exam Integrity to review the case files.`;
    } },
    { re: /attendance (of|in|for)?|how.*attendance/i, a() {
      const avg = Math.round(T.students.reduce((s, x) => s + (x.attendanceRate || 0), 0) / T.students.length * 100);
      return `Cohort attendance averages <b>${avg}%</b>. Section-wise breakdown and one-tap marking are on Attendance Management.`;
    } },
    { re: /kpi|how am i (doing|performing)|my (score|rating|performance)/i, a() {
      return 'Your KPI stands at <b>88</b> this semester — rank #4 on the department leaderboard (Gold tier). Biggest positive driver: grading turnaround; biggest drag: CO3 attainment.';
    } },
    { re: /draft.*notice|notice.*(draft|write|compose)/i, a() {
      return 'I can compose that — the Notice Management page turns one line (e.g. "Exam postponed to next Sunday") into a formal notice with course, section and dates pre-filled.';
    } },
    { re: /remind(er)?s?|message|inbox/i, a() {
      return 'Your EON reminders are in the companion bubble (bottom-left) — advising follow-up and script returns are seeded there. Ask "remind me to …" and I\'ll store it.';
    } },
    { re: /^(hi|hello|hey|salam|assalamu)/i, a() {
      const f = flaggedTop(1);
      return `Hello! Quick pulse: ${T.students.length} students across ${T.courses.length} courses, ` +
        (f.length ? `<b>${f[0].s.name}</b> tops the risk list today.` : 'no urgent flags today.') + ' What shall we look at?';
    } },
    { re: /thank/i, a() { return 'Anytime. Teaching is the hard part — the analytics should be the easy part.'; } },
  ];
  window.TeacherQA = {
    answer(q) {
      q = String(q || '');
      // student lookup: "who is <name>" or a roster name mentioned directly
      const st = T.students.find(s => q.toLowerCase().includes(s.name.toLowerCase()));
      if (st) {
        const att = Math.round((st.attendanceRate || 0) * 100);
        return `<b>${st.name}</b> (${st.id}, Sec ${st.section}) — attendance ${att}%, trend ${st.trend > 0 ? 'improving' : 'declining'}. ` +
          (att < 70 ? 'Below the 70% compliance line — worth an advising nudge.' : 'Inside the compliance band.');
      }
      for (const it of INTENTS) { if (it.re.test(q)) { try { return it.a(); } catch { /* next */ } } }
      try { if (window.EonAsk && window.EonAsk.answer) { const r = window.EonAsk.answer(q); if (r) return typeof r === 'string' ? r : (r.text || null); } } catch { }
      return null;
    },
  };

  /* ---------- 5. demo reminders (seeded once) ---------- */
  function seedReminders() {
    if (localStorage.getItem('eft-eon-seeded')) return;
    if (!window.EonBrain || !window.EonBrain.createReminder) { setTimeout(seedReminders, 1200); return; }
    try {
      const t = new Date(); t.setDate(t.getDate() + 1); t.setHours(9, 0, 0, 0);
      window.EonBrain.createReminder({ title: 'Return Quiz 2 scripts to Section B', remindAt: t.toISOString(), link: 'assessments.html' });
      const a = new Date(); a.setDate(a.getDate() + 2); a.setHours(14, 30, 0, 0);
      window.EonBrain.createReminder({ title: 'Advising follow-up — Rakib Rahman (risk 93)', remindAt: a.toISOString(), link: 'counseling.html' });
      localStorage.setItem('eft-eon-seeded', '1');
    } catch { /* brain not ready — retry next visit */ }
  }

  /* ---------- 6. brain config for the teacher space ---------- */
  window.EON_BRAIN_CONFIG = Object.assign({}, window.EON_BRAIN_CONFIG || {}, {
    space: 'teacher',
    deadlineEntities: ['opportunities', 'assessments', 'advising'],
    linkPatterns: {
      opportunities: 'opportunities.html',
      assessments:   'assessments.html',
      advising:      'counseling.html',
      courses:       'courses.html',
      students:      'students.html',
      classes:       'attendance.html',
      default:       'index.html',
    },
  });

  /* seed the demo reminders once the brain (loaded by boot.js) is up */
  function whenIdle() {
    if ('requestIdleCallback' in window) requestIdleCallback(() => setTimeout(seedReminders, 2500), { timeout: 2200 });
    else setTimeout(seedReminders, 3200);
  }
  if (document.readyState === 'complete') whenIdle();
  else window.addEventListener('load', whenIdle, { once: true });
})();
