/* ==========================================================
   EON FOR TEACHER — OBE Analytics & Intelligence Center v3.5
   Flagship OBE suite: CO-PO alignment matrix, CO achievement,
   section heatmap, risk prognosis, question analytics, teaching
   effectiveness, course health engine, accreditation assistant,
   voice assistant and the AI predictive intelligence sidebar.
   Course selector wired to THIS teacher's three courses; the
   default course carries the full spec dataset, the other two
   recompute live from window.T via the analytics core A.
   ========================================================== */
'use strict';
(function () {

  /* ---------- extra lucide-style icons (local, 24×24, sw2) ---------- */
  const XICONS = {
    'bot': '<path d="M12 8V4H8"/><rect width="16" height="12" x="4" y="8" rx="2"/><path d="M2 14h2"/><path d="M20 14h2"/><path d="M15 13v2"/><path d="M9 13v2"/>',
    'grid-2x2': '<rect width="18" height="18" x="3" y="3" rx="2"/><path d="M3 12h18"/><path d="M12 3v18"/>',
    'bar-chart-2': '<line x1="18" x2="18" y1="20" y2="10"/><line x1="12" x2="12" y1="20" y2="4"/><line x1="6" x2="6" y1="20" y2="14"/>',
    'layout-grid': '<rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/>',
    'file-question': '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 10.3c.2-.4.5-.8.9-1a2.1 2.1 0 0 1 2.6.4c.3.4.5.8.5 1.3 0 1.3-2 2-2 2"/><path d="M12 17h.01"/>',
    'info': '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
    'shield-alert': '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="M12 8v4"/><path d="M12 16h.01"/>',
    'search-code': '<path d="m13 13.5 2-2.5-2-2.5"/><path d="m21 21-4.3-4.3"/><path d="M9 8.5 7 11l2 2.5"/><circle cx="11" cy="11" r="8"/>',
    'sliders': '<line x1="21" x2="14" y1="4" y2="4"/><line x1="10" x2="3" y1="4" y2="4"/><line x1="21" x2="12" y1="12" y2="12"/><line x1="8" x2="3" y1="12" y2="12"/><line x1="21" x2="16" y1="20" y2="20"/><line x1="12" x2="3" y1="20" y2="20"/><line x1="14" x2="14" y1="2" y2="6"/><line x1="8" x2="8" y1="10" y2="14"/><line x1="16" x2="16" y1="18" y2="22"/>',
    'file-down': '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M12 18v-6"/><path d="m9 15 3 3 3-3"/>',
    'shield-check': '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>',
    'volume-2': '<polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14"/>',
    'mic-off': '<line x1="2" x2="22" y1="2" y2="22"/><path d="M18.89 13.23A7.12 7.12 0 0 0 19 12v-2"/><path d="M5 10v2a7 7 0 0 0 12 5"/><path d="M15 9.34V5a3 3 0 0 0-5.68-1.33"/><path d="M9 9v3a3 3 0 0 0 5.12 2.12"/><line x1="12" x2="12" y1="19" y2="22"/>',
    'compass': '<circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/>',
    'chevron-down': '<path d="m6 9 6 6 6-6"/>',
    'chevron-up': '<path d="m18 15-6-6-6 6"/>',
    'percent': '<line x1="19" x2="5" y1="5" y2="19"/><circle cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/>',
    'user-check': '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><polyline points="16 11 18 13 22 9"/>',
    'check-circle-2': '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
    'alert-circle': '<circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/>',
  };
  /* sized icon — prefers the local set, falls back to the app registry */
  function xi(name, sz, extra) {
    const body = XICONS[name] || (window.ICONS && window.ICONS[name]) || XICONS['info'];
    return `<svg class="lucide${extra ? ' ' + extra : ''}" viewBox="0 0 24 24" style="width:${sz}px;height:${sz}px" aria-hidden="true">${body}</svg>`;
  }

  /* ---------- verbatim spec constants ---------- */
  const PO_DESC = {
    PO1: 'Engineering Knowledge: Mathematics, science, and engineering fundamentals.',
    PO2: 'Problem Analysis: Diagnose engineering problems using first principles.',
    PO3: 'Design/Development: Fabricate technical solutions meeting specified constraints.',
    PO4: 'Investigations: Execute experiment-driven tests and synthesize dataset findings.',
    PO5: 'Modern Tool Usage: Apply professional engineering platforms like IDEs, Git, and Docker.',
    PO6: 'The Engineer & Society: Evaluate societal health, security, and safety constraints.',
    PO7: 'Environment & Sustainability: Assess long-term ecological impact profiles.',
    PO8: 'Ethics: Adhere to ethical standards and academic engineering frameworks.',
    PO9: 'Individual & Team Work: Perform optimally in diverse collaborative operations.',
    PO10: 'Communication: Speak fluently, document architecture, and draft presentation charts.',
    PO11: 'Project Management: Coordinate project schedules, budgets, and lifecycle milestones.',
    PO12: 'Life-long Learning: Engage in self-learning methodologies in an evolving workforce.',
  };
  const POS = Object.keys(PO_DESC);
  const CYCLE = ['None', 'Low', 'Medium', 'High'];
  const INT_OF = { H: 'High', M: 'Medium', L: 'Low', N: 'None' };

  /* flagship course dataset (spec seed, verbatim numbers) */
  const CO_311 = [
    { id: 'CO1', name: 'Programming Fundamentals', desc: 'Formulate algorithms and translate them into typed data structures and procedural scripts.', ach: 88, pass: 106, fail: 14, trend: 'up', tp: 4.2 },
    { id: 'CO2', name: 'Problem Solving', desc: 'Analyze complex problem specifications and design optimized logical paradigms.', ach: 79, pass: 95, fail: 25, trend: 'stable', tp: 0.5 },
    { id: 'CO3', name: 'Communication', desc: 'Deliver professional spoken interactive design defenses and clear technical reviews.', ach: 58, pass: 70, fail: 50, trend: 'down', tp: -8.4 },
    { id: 'CO4', name: 'Team Collaboration', desc: 'Operate within professional version-controlled frameworks in multi-member engineering teams.', ach: 72, pass: 86, fail: 34, trend: 'up', tp: 2.1 },
    { id: 'CO5', name: 'Software Design', desc: 'Construct UML mapping diagrams and deploy reusable object-oriented design patterns.', ach: 81, pass: 97, fail: 23, trend: 'up', tp: 3.8 },
  ];
  const MATRIX_311 = { CO1: 'HMLNMNNNLNNL', CO2: 'MHMLNNNNNNNM', CO3: 'NNNNNLNLHMLL', CO4: 'NLLLLNNMMLHL', CO5: 'MMMLMNLNLNNM' };
  const MATRIX_220 = { CO1: 'HMMNMNNNNNNL', CO2: 'MHMLLNNNLNNM', CO3: 'MHLMNNNNNNLM' };
  const MATRIX_ENG = { CO1: 'NLNNNMNLLHNM', CO2: 'LMNLNMNMLHNM' };
  const HEAT_311 = { sections: ['Section A', 'Section B', 'Section C'], rows: [['CO1', 92, 81, 91], ['CO2', 84, 68, 85], ['CO3', 65, 43, 66], ['CO4', 76, 62, 78], ['CO5', 85, 72, 86]] };
  const RISK_311 = [
    { att: 52, comp: 45, score: 92, grade: 'F', reasons: ['Critical attendance deficit (52%)', 'Missed peer assessment review for CO3', 'Weak assignment execution for software planning (CO4)'] },
    { att: 61, comp: 60, score: 78, grade: 'D', reasons: ['Low attendance threshold violation (61%)', 'Underperformed in logic structuring assignments (CO2)', 'Incomplete quiz cycles'] },
    { att: 68, comp: 74, score: 61, grade: 'C', reasons: ['Declining attendance pattern (68%)', 'Mild underachievement in teamwork presentations (CO3)'] },
    { att: 74, comp: 70, score: 48, grade: 'C', reasons: ['Average attendance', 'Slight dip in problem solving quiz grades'] },
    { att: 98, comp: 100, score: 4, grade: 'A', reasons: ['Consistent perfect execution', 'All outcomes fully exceeded benchmarks'] },
  ];
  /* scatter: [attendance, result, riskIdx|-1] — riskIdx maps onto RISK rows */
  const SCATTER_311 = [
    [95, 92, -1], [88, 84, -1], [92, 89, -1], [52, 45, 0], [78, 72, -1], [61, 54, 1], [84, 81, -1],
    [68, 64, 2], [96, 95, -1], [45, 38, -1], [70, 68, -1], [85, 79, -1], [90, 91, -1], [58, 51, -1],
    [74, 70, 3], [98, 97, 4], [63, 56, -1], [82, 78, -1], [77, 75, -1],
  ];
  const QA_311 = [
    { id: 'Q1', co: 'CO1', att: 98, suc: 92, diff: 'Easy', reasons: ['Topic widely reviewed in lab sessions.', 'Syntax exercises were closely aligned.'], rec: 'Maintain current test vectors; baseline is healthy.' },
    { id: 'Q4', co: 'CO2', att: 94, suc: 78, diff: 'Medium', reasons: ['Required minor pointer algorithm tracking.', 'Adequate time allocation permitted students to finish.'], rec: 'Excellent benchmark question.' },
    { id: 'Q8', co: 'CO4', att: 88, suc: 70, diff: 'Medium', reasons: ['Evaluated concurrency synchronization issues in teams.', 'Minor theoretical confusion existed.'], rec: 'Slightly clarify thread-safety requirements.' },
    { id: 'Q12', co: 'CO3', att: 0, suc: 0, diff: 'Extreme', reasons: ['Assigned complex UML soft-communication alignment flowchart with no clear code target.', 'Out of standard interactive software engineering lab syllabus scope.', 'Extremely dense phrasing that caused immediate fatigue.'], rec: 'CRITICAL: Urgent syllabus alignment review suggested. Suppress question or shift metrics to optional homework vectors next semester.' },
  ];
  const WEAK_311 = [
    { icon: 'shield-alert', pr: 'High', title: 'Critical Outcome Fail: CO3', chip: 'Syllabus Mapped ✕ Presentation Rubrics', insight: 'Students underperformed significantly on peer presentations (average 49%) and interactive soft-skills assessments. CO3 attainment dropped well below the target 70% to 58%.', impact: '-12% under baseline targets', cure: 'Syllabus presentation workshop remedial session next Wednesday.' },
    { icon: 'square-check', pr: 'High', title: 'Attendance correlation threshold failure', chip: 'Attendance Logs ✕ Assignment Scores', insight: 'AI pattern detection identified that students with lecture attendance below 65% had a 40% lower assignment completion rate. Underperforming students primarily cluster in Section B.', impact: "Pearson's Correlation Coefficient R = 0.82", cure: 'Activate daily automatic engagement trigger reminders dynamically.' },
    { icon: 'book-open', pr: 'Medium', title: 'Mild Outcome Attainment Dip: CO4', chip: 'Team Collaboration ✕ Peer Reviews', insight: 'A dip in the team collaboration outcome (72%) was registered due to individual free-rider effects in large group assignments. Peer grading indicated unbalanced efforts on software diagrams.', impact: 'Diverging peer evaluations detected', cure: 'Implement individual code contribution trackers via Git commits.' },
  ];
  const FEEDBACK_311 = { score: 89, cat: 'Excellent', rating: 8.4, engagement: 85, completion: 91, impact: 'Strong positive impact: Students with attendance > 80% achieve 24% higher attainment averages.' };
  const METRICS_311 = { attendance: 84, assignment: 91, exam: 73, coAchievement: 75.6, engagement: 85 };
  const DEFAULT_WEIGHTS = { attendance: 25, assignment: 20, exam: 30, coAchievement: 15, engagement: 10 };
  const SLIDER_ROWS = [
    ['attendance', 'Attendance Weight', '#3b82f6'], ['assignment', 'Assignment Weight', '#6366f1'],
    ['exam', 'Exam Outcome Weight', '#a855f7'], ['coAchievement', 'CO Attainment Weight', '#ec4899'],
    ['engagement', 'Engagement Weight', '#eab308'],
  ];
  const CARD_THEMES = {
    blue: { txt: '#2563eb', g: '#3b82f6', bg: 'rgba(239,246,255,.5)', bd: '#dbeafe' },
    green: { txt: '#16a34a', g: '#10b981', bg: 'rgba(240,253,244,.5)', bd: '#dcfce7' },
    purple: { txt: '#9333ea', g: '#8b5cf6', bg: 'rgba(250,245,255,.5)', bd: '#f3e8ff' },
    indigo: { txt: '#4f46e5', g: '#6366f1', bg: 'rgba(238,242,255,.5)', bd: '#e0e7ff' },
    red: { txt: '#dc2626', g: '#ef4444', bg: 'rgba(254,242,242,.5)', bd: '#fee2e2' },
    orange: { txt: '#ea580c', g: '#f97316', bg: 'rgba(255,247,237,.5)', bd: '#ffedd5' },
  };
  const ACC_STEPS = [
    'Harvesting overall scores & attendance records...',
    'Correlating CO1-CO5 attainment margins...',
    'Drafting CQI improvement matrices for CO3 presentation gaps...',
    'Validating outcomes against ABET/NBA alignment guidelines...',
    'Assembling official educational accreditation portfolio...',
  ];
  const VOICE_PRESETS = ['Show weak outcomes', 'Analyze CO3', 'Predict student risks', 'Generate accreditation report'];
  const CO_SHORT = {
    CSE220: { CO1: 'Linear Structures', CO2: 'Trees & Graphs', CO3: 'Complexity Analysis' },
    ENG103: { CO1: 'Academic Paragraphs', CO2: 'Argumentative Essays' },
  };

  /* ---------- tiny math/chart helpers (no libs) ---------- */
  function pearsonR(pts) {
    const n = pts.length; if (n < 2) return 0;
    const mx = pts.reduce((s, p) => s + p[0], 0) / n, my = pts.reduce((s, p) => s + p[1], 0) / n;
    let num = 0, dx = 0, dy = 0;
    pts.forEach(p => { num += (p[0] - mx) * (p[1] - my); dx += (p[0] - mx) ** 2; dy += (p[1] - my) ** 2; });
    return dx && dy ? num / Math.sqrt(dx * dy) : 0;
  }
  const ramp = (v) => [v - 8, v - 6, v - 7, v - 5, v - 3, v - 2, v].map(x => Math.max(0, Math.round(x * 10) / 10));
  function areaSpark(vals, color, uid, w = 96, h = 40) {
    const min = Math.min(...vals), max = Math.max(...vals), span = (max - min) || 1;
    const X = i => 2 + i / (vals.length - 1) * (w - 4);
    const Y = v => h - 2 - (v - min) / span * (h - 4);
    const pts = vals.map((v, i) => `${X(i).toFixed(1)},${Y(v).toFixed(1)}`).join(' ');
    return `<svg width="${w}" height="${h}" aria-hidden="true"><defs><linearGradient id="og-${uid}" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="${color}" stop-opacity=".4"/><stop offset="1" stop-color="${color}" stop-opacity="0"/></linearGradient></defs><polygon points="2,${h - 2} ${pts} ${w - 2},${h - 2}" fill="url(#og-${uid})"/><polyline points="${pts}" fill="none" stroke="${color}" stroke-width="2"/></svg>`;
  }

  function scatterSvg(points) {
    const w = 640, h = 256;
    const X = a => 48 + (a - 30) / 70 * (w - 62);
    const Y = r => 10 + (1 - (r - 30) / 70) * (h - 42);
    let grid = '';
    for (let t = 30; t <= 100; t += 10) {
      grid += `<line x1="${X(t)}" x2="${X(t)}" y1="10" y2="${h - 32}" stroke="#e4e4e7" stroke-dasharray="3 3"/>`;
      grid += `<line x1="48" x2="${w - 14}" y1="${Y(t)}" y2="${Y(t)}" stroke="#e4e4e7" stroke-dasharray="3 3"/>`;
      grid += `<text x="${X(t)}" y="${h - 16}" text-anchor="middle" font-size="10" fill="#a1a1aa" font-family="var(--font-mono)">${t}%</text>`;
      grid += `<text x="42" y="${Y(t) + 3}" text-anchor="end" font-size="10" fill="#a1a1aa" font-family="var(--font-mono)">${t}%</text>`;
    }
    const dots = points.map(p => {
      const col = (p.att < 60 || p.res < 50) ? '#f43f5e' : (p.att < 75 || p.res < 70) ? '#f59e0b' : '#3b82f6';
      const stroke = p.att < 60 ? 'stroke="#fff" stroke-width="2"' : '';
      const note = p.att < 65 ? 'At critical risk zone' : 'Target safety profile';
      return `<circle cx="${X(p.att).toFixed(1)}" cy="${Y(p.res).toFixed(1)}" r="5" fill="${col}" ${stroke} style="cursor:crosshair"><title>${esc(p.name)} (${esc(p.sid)})
Attendance Rate: ${p.att}%
Term Exam Grade: ${p.res}%
${note}</title></circle>`;
    }).join('');
    return `<svg viewBox="0 0 ${w} ${h}" width="100%" role="img">${grid}${dots}</svg>`;
  }

  /* ---------- mini markdown (chat + feed) ---------- */
  const mdInline = (s) => esc(s)
    .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
    .replace(/\*(.+?)\*/g, '<em>$1</em>');
  function md(text) {
    return String(text).trim().split(/\n\s*\n/).map(p => {
      p = p.trim();
      if (p.startsWith('### ')) return `<h6 class="omd-h">${mdInline(p.slice(4))}</h6>`;
      if (/^(\*|-)\s+/.test(p)) return `<div class="omd-ul">${p.split('\n').map(l => `<div>${mdInline(l.replace(/^\s*(\*|-)\s+/, ''))}</div>`).join('')}</div>`;
      if (/^\d+\.\s/.test(p)) return `<div class="omd-ul">${p.split('\n').map(l => `<div>${mdInline(l.replace(/^\s*\d+\.\s+/, ''))}</div>`).join('')}</div>`;
      return `<p class="omd-p">${mdInline(p)}</p>`;
    }).join('');
  }

  /* ---------- spec toast (emerald, top-center) ---------- */
  function obeToast(msg) {
    document.querySelector('#obeToast')?.remove();
    const t = document.createElement('div');
    t.id = 'obeToast';
    t.innerHTML = `${xi('sparkles', 16, 'o-spin')}<span>${esc(msg)}</span>`;
    document.body.appendChild(t);
    requestAnimationFrame(() => t.classList.add('in'));
    setTimeout(() => { t.classList.remove('in'); setTimeout(() => t.remove(), 300); }, 3500);
  }

  /* ---------- scroll + 3s focus ring ---------- */
  function scrollFocus(id, tone) {
    const el = document.getElementById(id); if (!el) return;
    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    el.classList.add('o-focused'); if (tone) el.classList.add('o-' + tone);
    clearTimeout(el._fr);
    el._fr = setTimeout(() => el.classList.remove('o-focused', 'o-rose', 'o-emerald'), 3000);
  }

  /* ---------- page CSS (scoped, light theme) ---------- */
  const CSS = `
  #content{--ob:#e4e4e7;--omut:#f4f4f5;--omf:#71717a;--ofg:#0f172a}
  .o-head{position:sticky;top:64px;z-index:15;display:flex;align-items:center;justify-content:space-between;gap:10px;background:rgba(255,255,255,.7);backdrop-filter:blur(24px);border:1px solid var(--ob);border-radius:14px;box-shadow:0 4px 20px rgba(0,0,0,.05);padding:10px 16px;margin-bottom:4px}
  .o-logo{padding:10px;border-radius:12px;color:#fff;background:linear-gradient(to bottom right,#6366f1,#9333ea);box-shadow:0 10px 15px -3px rgba(99,102,241,.3);position:relative;overflow:hidden;display:grid;place-items:center}
  .o-logo::after{content:"";position:absolute;inset:0;background:rgba(255,255,255,.2);animation:o-pulse 2.4s ease-in-out infinite}
  .o-vbadge{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;background:#e0e7ff;color:#4338ca;padding:2px 6px;border-radius:2px;margin-left:8px}
  .o-userpill{display:inline-flex;align-items:center;gap:8px;font-family:var(--font-mono);font-size:11px;background:rgba(15,23,42,.05);padding:6px 12px;border-radius:9999px;border:1px solid rgba(15,23,42,.1)}
  .o-dot{width:6px;height:6px;border-radius:999px;background:#10b981;animation:o-pulse 2s cubic-bezier(.4,0,.6,1) infinite}
  .o-hero{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:16px;padding:20px 2px 22px;border-bottom:1px solid var(--ob);margin-bottom:20px}
  .o-eyebrow{font-size:11px;font-weight:800;color:#4f46e5;letter-spacing:.12em;text-transform:uppercase;font-family:var(--font-mono)}
  .o-hero h2{font-size:24px;font-weight:900;letter-spacing:-.03em;margin-top:2px;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
  .o-hero .sub{font-size:12px;color:var(--omf);margin-top:4px}
  #obeCourse{font:900 20px/1.2 var(--font-display);letter-spacing:-.03em;border:1px dashed #c7d2fe;background:#eef2ff66;color:var(--ofg);border-radius:10px;padding:4px 8px;cursor:pointer}
  .o-actions{display:flex;flex-wrap:wrap;gap:8px}
  .o-abtn{padding:8px 14px;border-radius:8px;display:inline-flex;align-items:center;gap:6px;font:700 12px var(--font-ui);color:#fff;border:0;transition:all .15s}
  .o-abtn:active{transform:scale(.95)}
  .o-abtn.em{background:#059669}.o-abtn.em:hover{background:#047857}
  .o-abtn.in{background:#4f46e5}.o-abtn.in:hover{background:#4338ca}
  .o-abtn.pu{background:#9333ea}.o-abtn.pu:hover{background:#7e22ce}
  .o-abtn.re{background:#dc2626}.o-abtn.re:hover{background:#b91c1c}
  .o-abtn.sec{background:var(--omut);color:var(--ofg);border:1px solid var(--ob)}.o-abtn.sec:hover{opacity:.8}
  .o-cards{display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));margin-bottom:24px}
  .o-card{position:relative;overflow:hidden;border-radius:12px;border:1px solid var(--ob);padding:20px;background:rgba(255,255,255,.9);box-shadow:0 1px 2px rgba(0,0,0,.05);transition:all .2s}
  .o-card:hover{box-shadow:0 10px 30px rgba(16,24,40,.08);transform:translateY(-4px)}
  .o-card .tt{font-size:12px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:var(--omf)}
  .o-card .vv{margin-top:8px;font-size:30px;font-weight:800;letter-spacing:-.03em;font-family:var(--font-display)}
  .o-card .ictile{padding:10px;border-radius:8px;display:grid;place-items:center}
  .o-card .bot{margin-top:16px;display:flex;align-items:center;justify-content:space-between;gap:8px}
  .o-tpill{display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:9999px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;border:1px solid}
  .o-tpill.up{background:rgba(16,185,129,.1);border-color:rgba(16,185,129,.2);color:#059669;box-shadow:0 0 15px rgba(16,185,129,.15)}
  .o-tpill.dn{background:rgba(244,63,94,.1);border-color:rgba(244,63,94,.2);color:#e11d48;box-shadow:0 0 15px rgba(244,63,94,.15)}
  .o-main{display:grid;grid-template-columns:2fr 1fr;gap:24px;align-items:start}
  @media (max-width:1100px){.o-main{grid-template-columns:1fr}}
  .o-col{display:flex;flex-direction:column;gap:24px;min-width:0}
  .o-sec{padding:2px;border-radius:16px;transition:box-shadow .35s,transform .35s}
  .o-sec.o-focused{box-shadow:0 0 0 4px #6366f1;transform:scale(1.01)}
  .o-sec.o-focused.o-rose{box-shadow:0 0 0 4px #f43f5e}
  .o-sec.o-focused.o-emerald{box-shadow:0 0 0 4px #10b981}
  .o-panel{border-radius:12px;border:1px solid var(--ob);background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.05);padding:20px;position:relative}
  .o-ph{display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px}
  .o-ph .lft{display:flex;gap:10px;align-items:flex-start}
  .o-ic{padding:6px;border-radius:8px;display:grid;place-items:center;flex:0 0 auto}
  .o-ph h4{font-size:15.5px;font-weight:600;letter-spacing:-.01em}
  .o-ph .ps{font-size:12px;color:var(--omf);margin-top:1px;max-width:520px}
  .o-monopill{font-size:10px;text-transform:uppercase;font-family:var(--font-mono);font-weight:700;letter-spacing:.05em;color:var(--omf);background:var(--omut);padding:4px 12px;border-radius:6px;white-space:nowrap}
  table.o-mx{width:100%;min-width:700px;border-collapse:collapse;text-align:center;font-size:12px}
  .o-mx thead th{background:rgba(244,244,245,.5);font:600 11px var(--font-mono);text-transform:uppercase;letter-spacing:.05em;color:var(--omf);border-bottom:1px solid var(--ob);padding:12px 4px;position:relative}
  .o-mx thead th.oc{ text-align:left;padding:12px 8px;border-right:1px solid var(--ob);width:64px}
  .o-mx thead th u{text-decoration:underline dotted #d4d4d8;cursor:help}
  .o-pop{display:none;position:absolute;bottom:100%;left:50%;transform:translateX(-50%);margin-bottom:8px;width:192px;padding:8px;font:400 10px/1.35 var(--font-ui);text-transform:none;letter-spacing:0;color:#fff;background:#09090b;border-radius:8px;box-shadow:0 10px 30px rgba(16,24,40,.2);z-index:30}
  .o-mx thead th:hover .o-pop{display:block}
  .o-mx tbody td{border-bottom:1px solid #f4f4f5;padding:4px}
  .o-mx tbody tr:hover{background:rgba(244,244,245,.3)}
  .o-mx tbody td.oc{text-align:left;padding:10px 8px;font:800 12px var(--font-mono);border-right:1px solid var(--ob)}
  .o-mxb{width:100%;padding:10px 0;border:0;border-radius:8px;font-size:10px;cursor:pointer;transition:all .15s}
  .o-mxb.H{background:#4f46e5;color:#fff;font-weight:800}.o-mxb.H:hover{background:#4338ca;box-shadow:0 0 0 2px #818cf8}
  .o-mxb.M{background:rgba(199,210,254,.9);color:#312e81;font-weight:600}.o-mxb.M:hover{background:#a5b4fc;box-shadow:0 0 0 2px #a5b4fc}
  .o-mxb.L{background:#eef2ff;color:#4338ca;font-weight:500}.o-mxb.L:hover{background:rgba(224,231,255,.8);box-shadow:0 0 0 2px rgba(199,210,254,.5)}
  .o-mxb.N{background:var(--omut);color:#d4d4d8;font-weight:400}.o-mxb.N:hover{background:#f4f4f5;box-shadow:0 0 0 2px #e4e4e7}
  .o-sw{width:14px;height:14px;border-radius:6px;display:inline-block;vertical-align:-2px;margin-right:6px}
  .o-intel{position:absolute;bottom:20px;right:20px;width:320px;padding:16px;border-radius:12px;border:1px solid #e0e7ff;background:rgba(238,242,255,.95);box-shadow:0 10px 30px rgba(16,24,40,.12);backdrop-filter:blur(4px);z-index:20;pointer-events:none;opacity:0;transform:translateY(10px);transition:all .2s}
  .o-intel.show{opacity:1;transform:translateY(0)}
  .o-corow{padding:18px 8px;border-bottom:1px solid #f4f4f5;border-radius:8px;display:flex;flex-wrap:wrap;gap:16px;align-items:center}
  .o-corow:hover{background:rgba(244,244,245,.3)}
  .o-corow:last-child{border-bottom:0}
  .o-cobadge{font-size:12px;padding:2px 10px;border-radius:6px;border:1px solid;font-weight:800;letter-spacing:.04em}
  .o-bar{height:8px;width:100%;background:#f4f4f5;border-radius:999px;overflow:hidden}
  .o-bar i{display:block;height:100%;border-radius:999px;width:0;transition:width 1s ease-out}
  .o-heatcell{padding:10px 0;border-radius:8px;cursor:help;transition:transform .15s;text-align:center;font-size:12px}
  .o-heatcell:hover{transform:scale(1.05)}
  .o-tab{padding:4px 12px;border-radius:6px;border:0;background:transparent;color:var(--omf);font:500 12px var(--font-ui);cursor:pointer}
  .o-tab.on{background:#fff;color:var(--ofg);box-shadow:0 1px 2px rgba(0,0,0,.05)}
  .o-riskrow{padding:14px;border-radius:12px;border:1px solid var(--ob);cursor:pointer;display:flex;justify-content:space-between;align-items:center;gap:16px;transition:all .15s}
  .o-riskrow:hover{background:var(--omut)}
  .o-riskrow.sel{border-color:#6366f1;background:rgba(238,242,255,.4)}
  .o-rlab{font:600 10px var(--font-mono);color:var(--omf);text-transform:uppercase}
  .o-detrow{display:flex;justify-content:space-between;gap:10px;font-size:12px;margin-top:4px}
  .o-factor{display:flex;gap:8px;font-size:12px;background:#fff;padding:10px;border-radius:8px;border:1px solid #ececee;margin-top:6px;line-height:1.5}
  .o-wcard{padding:16px;border-radius:12px;border:1px solid var(--ob);background:rgba(244,244,245,.5);display:flex;gap:18px;transition:transform .15s}
  .o-wcard:hover{transform:scale(1.01)}
  .o-wico{width:44px;height:44px;flex:0 0 auto;background:#fff;border:1px solid var(--ob);border-radius:12px;box-shadow:0 1px 2px rgba(0,0,0,.05);color:#4f46e5;display:grid;place-items:center}
  .o-prpill{font:800 10px var(--font-mono);text-transform:uppercase;letter-spacing:.05em;padding:2px 10px;border-radius:999px;border:1px solid;user-select:none;white-space:nowrap}
  .o-prpill.High{background:#ffe4e6;color:#be123c;border-color:#fecdd3}
  .o-prpill.Medium{background:#fef3c7;color:#b45309;border-color:#fde68a}
  .o-prpill.Low{background:#dbeafe;color:#1d4ed8;border-color:#bfdbfe}
  .o-qrow{cursor:pointer}
  .o-qrow.sel td{background:rgba(238,242,255,.35)}
  .o-diffpill{padding:2px 8px;border-radius:999px;font-size:10px;font-weight:700;border:1px solid;white-space:nowrap}
  .o-diffpill.Extreme{background:#ffe4e6;color:#a80e3e;border-color:#fecdd3;text-transform:uppercase;letter-spacing:.1em;font-family:var(--font-mono);animation:o-pulse 2s cubic-bezier(.4,0,.6,1) infinite}
  .o-diffpill.Hard{background:#ffedd5;color:#c2410c;border-color:#fed7aa}
  .o-diffpill.Medium{background:#fef3c7;color:#b45309;border-color:#fde68a}
  .o-diffpill.Easy{background:#d1fae5;color:#047857;border-color:#a7f3d0}
  .o-qtable{width:100%;border-collapse:collapse;font-size:12px;text-align:left}
  .o-qtable th{background:rgba(244,244,245,.5);font:700 10px var(--font-mono);text-transform:uppercase;letter-spacing:.05em;color:var(--omf);padding:10px 12px;border-bottom:1px solid var(--ob)}
  .o-qtable td{padding:12px;border-bottom:1px solid #f4f4f5}
  .o-slider{width:100%;height:6px;accent-color:#4f46e5;cursor:pointer}
  .o-mlabel{font:700 11px var(--font-mono);text-transform:uppercase;letter-spacing:.05em;color:var(--omf)}
  .o-chatwrap{border-radius:12px;border:1px solid var(--ob);background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.05);display:flex;flex-direction:column;height:550px;overflow:hidden;position:sticky;top:150px;z-index:5}
  .o-msgs{flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:16px}
  .o-msg{display:flex;gap:12px;max-width:85%}
  .o-msg.me{margin-left:auto}
  .o-av{width:32px;height:32px;border-radius:999px;background:#eef2ff;color:#4f46e5;border:1px solid rgba(224,231,255,.5);display:grid;place-items:center;flex:0 0 auto}
  .o-bub{padding:12px;border-radius:12px;border:1px solid var(--ob);background:rgba(244,244,245,.5);font-size:12px;line-height:1.6}
  .o-msg.me .o-bub{background:#4f46e5;color:#fff;border-color:transparent}
  .o-ts{font:400 9px var(--font-mono);text-align:right;margin-top:6px;color:var(--omf)}
  .o-msg.me .o-ts{color:#c7d2fe}
  .omd-h{font-size:14px;font-weight:800;margin:0 0 6px}
  .omd-p{margin:6px 0}
  .omd-ul{padding-left:16px;border-left:2px solid rgba(199,210,254,.5);margin:6px 0;display:flex;flex-direction:column;gap:4px}
  .o-sugg{padding:8px;border:1px solid var(--ob);border-radius:8px;text-align:left;font:500 11px var(--font-ui);background:#fff;cursor:pointer;display:flex;align-items:center;gap:6px}
  .o-sugg:hover{background:var(--omut)}
  .o-chatin{padding:12px;background:rgba(244,244,245,.5);border-top:1px solid var(--ob);display:flex;gap:8px}
  .o-chatin input{flex:1;font-size:12px;padding:10px 14px;border-radius:8px;border:1px solid var(--ob);background:#fff;outline:0}
  .o-chatin input:focus{border-color:#6366f1}
  .o-chatin button{padding:10px;background:#4f46e5;border:0;border-radius:8px;color:#fff;display:grid;place-items:center}
  .o-chatin button:hover{background:#4338ca}
  .o-chatin button:active{transform:scale(.95)}
  .o-feedcard{padding:12px;border-radius:8px;display:flex;gap:10px;font-size:12px;line-height:1.55}
  .o-feedh{font:700 10px var(--font-mono);text-transform:uppercase;letter-spacing:.06em;margin:0}
  .o-mic{width:80px;height:80px;border-radius:999px;border:0;color:#fff;display:grid;place-items:center;box-shadow:0 10px 15px -3px rgba(0,0,0,.15);position:relative;z-index:2}
  .o-mic.idle{background:#4f46e5}.o-mic.idle:hover{background:#4338ca}
  .o-mic.live{background:#e11d48}.o-mic.live:hover{background:#be123c}
  .o-mic:active{transform:scale(.95)}
  .o-ripple{position:absolute;width:96px;height:96px;border-radius:999px;pointer-events:none}
  .o-ripple.r1{background:rgba(244,63,94,.2);animation:o-ripple1 1.5s ease-out infinite}
  .o-ripple.r2{background:rgba(244,63,94,.1);animation:o-ripple2 1.5s ease-out .5s infinite}
  .o-vchip{font-size:12px;padding:8px 12px;border:1px solid var(--ob);border-radius:8px;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.05);cursor:pointer}
  .o-vchip:hover{background:#eef2ff;border-color:#c7d2fe}
  .o-foot{margin-top:48px;background:#fff;border:1px solid var(--ob);border-radius:12px;height:40px;padding:0 24px;display:flex;align-items:center;justify-content:space-between;font-size:10px;color:var(--omf);font-weight:500;gap:12px;flex-wrap:wrap}
  #obeToast{position:fixed;top:20px;left:50%;transform:translate(-50%,-20px) scale(.95);z-index:250;background:#059669;color:#fff;box-shadow:0 20px 40px rgba(0,0,0,.25);border-radius:12px;padding:14px 24px;border:1px solid #34d399;font:600 12px var(--font-ui);display:flex;align-items:center;gap:8px;opacity:0;transition:all .3s}
  #obeToast.in{opacity:1;transform:translate(-50%,0) scale(1)}
  .o-spin{animation:o-spinf 3s linear infinite}
  .o-spinfast{animation:o-spinf 1s linear infinite}
  .o-pulse{animation:o-pulse 2s cubic-bezier(.4,0,.6,1) infinite}
  @keyframes o-spinf{to{transform:rotate(360deg)}}
  @keyframes o-pulse{0%,100%{opacity:1}50%{opacity:.5}}
  @keyframes o-ripple1{from{transform:scale(.8);opacity:.5}to{transform:scale(1.8);opacity:0}}
  @keyframes o-ripple2{from{transform:scale(.8);opacity:.3}to{transform:scale(2.2);opacity:0}}
  @media (max-width:900px){.o-heatgrid,.o-scgrid,.o-riskgrid,.o-qagrid,.o-tegrid,.o-hegrid,.o-vagrid{grid-template-columns:1fr!important}.o-hidesm{display:none}}
  `;

  /* ==========================================================
     PAGE INIT
     ========================================================== */
  window.initObePage = function initObePage() {
    const root = document.querySelector('#content');

    /* ---------- per-course dataset builders ---------- */
    const gradeOf = (avg) => avg >= 0.8 ? 'A' : avg >= 0.7 ? 'B' : avg >= 0.55 ? 'C' : avg >= 0.45 ? 'D' : 'F';
    const rosterOf = (code) => T.course(code).sections.flatMap(s => T.enrolled[code][s]);
    const rankedOf = (code) => rosterOf(code).map(st => ({ st, r: A.risk(st) })).sort((a, b) => b.r.score - a.r.score);

    function build311() {
      const c = T.course('CSE311');
      const ranked = rankedOf('CSE311');
      const riskSt = [ranked[0], ranked[1], ranked[2], ranked[3], ranked[ranked.length - 1]].map(x => x.st);
      const risks = RISK_311.map((r, i) => ({ ...r, id: riskSt[i].id, name: riskSt[i].name }));
      const used = new Set(riskSt.map(s => s.id));
      const rest = rosterOf('CSE311').filter(s => !used.has(s.id));
      let k = 0;
      const scatter = SCATTER_311.map((p) => {
        const st = p[2] >= 0 ? riskSt[p[2]] : rest[(k += 3) % rest.length];
        return { att: p[0], res: p[1], sid: st.id, name: st.name };
      });
      return {
        code: 'CSE311', title: c.title, target: 70, live: true,
        cos: CO_311.map(x => ({ ...x })),
        matrixSeed: MATRIX_311,
        heat: { sections: HEAT_311.sections.slice(), rows: HEAT_311.rows.map(r => r.slice()) },
        heatDiag: {
          h5: 'Section B Attainment Deficit',
          p: 'Critical gaps identified under **Section B**:',
          bullets: [['CO3 (Communication): 43%', '#e11d48'], ['CO2 (Problem Solving): 68%', '#d97706']],
          note: 'Attendance average in Section B is 63%, compared to 85% in Section A and C.',
        },
        risks, scatter, pearson: '0.82',
        scatterInsight1: 'Students with <strong>below 60%</strong> attendance show a <strong>40% lower</strong> overall outcome achievement rate.',
        qa: QA_311.map(x => ({ ...x })), qaDefault: 'Q12',
        weak: WEAK_311,
        feedback: { ...FEEDBACK_311 },
        metrics: { ...METRICS_311 },
        alert: {
          coId: 'CO3',
          body: '<strong>CO3 (Communication Skills)</strong> attainment has fallen to <strong>58%</strong>. Cross-examination of grading files indicates systemic student difficulties with presentation-heavy, collaborative speech rubrics.',
          sim: { ach: 74, pass: 89, fail: 31, tp: 16.0, heatCell: ['CO3', 'Section B', 68], toast: 'Sandbox Simulation Success: Communication Workshop applied! CO3 Attainment boosted to 74%!' },
        },
        cards: [
          { id: 'total-students', title: 'Total Students', value: '81', trend: 'Baseline Standard', pos: true, icon: 'users', theme: 'indigo', spark: [74, 76, 78, 79, 80, 81, 81] },
          { id: 'active-enrolled', title: 'Active Enrolled', value: '77', trend: '95% Active engagement', pos: true, icon: 'user-check', theme: 'blue', spark: [68, 70, 73, 74, 75, 76, 77] },
          { id: 'co-attainment', title: 'CO attainment', value: '75.6%', trend: '+1.2% improvement', pos: true, icon: 'percent', theme: 'purple', spark: [72, 74, 73, 75, 74, 75, 75.6] },
          { id: 'po-mapping-score', title: 'PO mapping score', value: '73.1%', trend: 'Awaiting cycle completion', pos: true, icon: 'award', theme: 'orange', spark: [68, 70, 71, 70, 72, 71, 73.1] },
          { id: 'at-risk-students', title: 'At-Risk Students', value: '8 Students', trend: '28.5% drop in risk density', pos: true, icon: 'shield-alert', theme: 'red', spark: [14, 13, 12, 11, 10, 9, 8] },
        ],
        healthSpark: [78, 80, 81, 82, 83, 83, 84],
        initialHealth: 84,
        accSection1: [
          ['CO1 (Fundamental)', '88%', 'Exceeded'], ['CO2 (Logic Dev)', '79%', 'Achieved'],
          ['CO3 (Comm)', '58%', 'Critically Failed'], ['CO4 (Teamwork)', '72%', 'Achieved'], ['CO5 (UML Design)', '81%', 'Exceeded'],
        ],
        accS2: 'Mapped program variables are evaluated at an average intensity tier of <strong>73.1%</strong>. Strong conversion was registered for <strong>PO1 (Knowledge)</strong> and <strong>PO9 (Team Work)</strong>. However, <strong>PO10 (Communication)</strong> is at a critical deficit of <strong>58%</strong> as mapped directly to CO3.',
        accS3: 'Detailed diagnostic reviews indicate poor performance in Section B (CO3 communication rating average levels was only 43%). Correlation matrices verify strong linear dependency (Pearson R = 0.82) between interactive lecture attendance variables and output final submissions.',
        accCqi: [
          ['Integrate peer presentation blocks:', ' Rebundle final evaluation grading scales to prioritize incremental feedback workshops before actual oral submissions.'],
          ['Address attendance drop:', ' Trigger automatic alerts for Section B students failing the class engagement threshold level (<75%).'],
          ['Syllabus realignment on Question Q12:', ' Realternate question blocks or exclude extremely dense design scenarios in next term exam papers.'],
        ],
        accQuote: '"Instructor demonstrates premium teaching efficiency metrics (Teaching Effectiveness Index of 89%). Recommended course status is Fully Compliant, with the immediate requirement to file the CO3 remediation plan before the Week 12 evaluation deadline."',
      };
    }

    function buildComputed(code) {
      const c = T.course(code);
      const roster = rosterOf(code);
      const cosBySec = c.sections.map(s => A.cos(code, s));
      const short = CO_SHORT[code] || {};
      const cos = c.cos.map((co, i) => {
        const vals = cosBySec.map(x => x[i].attain).filter(v => v != null);
        const ach = vals.length ? Math.round(mean(vals)) : 0;
        const pass = Math.round(ach / 100 * roster.length);
        const gap = ach - c.coTarget;
        return {
          id: co.id, name: short[co.id] || co.id, desc: co.text, ach, pass, fail: roster.length - pass,
          trend: gap >= 8 ? 'up' : gap >= 0 ? 'stable' : 'down',
          tp: gap >= 8 ? +(gap / 4).toFixed(1) : gap >= 0 ? 0.5 : -Math.abs(+(gap / 3).toFixed(1)),
        };
      });
      const coAvg = +mean(cos.map(x => x.ach)).toFixed(1);
      const heat = {
        sections: c.sections.map(s => 'Section ' + s),
        rows: cos.map((co, i) => [co.id, ...cosBySec.map(x => x[i].attain == null ? 0 : x[i].attain)]),
      };
      /* weakest cell drives the diagnostic sidebar */
      let minV = 101, minCo = cos[0], minSec = heat.sections[0];
      heat.rows.forEach((r, i) => r.slice(1).forEach((v, si) => { if (v < minV) { minV = v; minCo = cos[i]; minSec = heat.sections[si]; } }));
      const attBySec = c.sections.map(s => Math.round(mean(T.enrolled[code][s].map(st => st.attendanceRate)) * 100));
      const sorted = cos.slice().sort((a, b) => a.ach - b.ach);
      const heatDiag = {
        h5: `${minSec} Attainment Deficit`,
        p: `Critical gaps identified under **${minSec}**:`,
        bullets: [[`${sorted[0].id} (${sorted[0].name}): ${sorted[0].ach}%`, '#e11d48'],
                  [`${sorted[1] ? sorted[1].id : sorted[0].id} (${sorted[1] ? sorted[1].name : sorted[0].name}): ${sorted[1] ? sorted[1].ach : sorted[0].ach}%`, '#d97706']],
        note: `Attendance average across sections: ${c.sections.map((s, i) => s + ' ' + attBySec[i] + '%').join(', ')}.`,
      };
      /* risk rows from the analytics core */
      const ranked = rankedOf(code);
      const chosen = [...ranked.slice(0, 4), ranked[ranked.length - 1]];
      const risks = chosen.map(({ st, r }) => {
        const reasons = [];
        if (st.attendanceRate < 0.75) reasons.push(`Attendance below threshold (${pct(st.attendanceRate)}%)`);
        if (r.trend < -0.05) reasons.push(`Declining assessment trajectory (${pct(r.trend)}% trend)`);
        if (st.lateSubRate > 0.3) reasons.push(`Frequent late submissions (${pct(st.lateSubRate)}%)`);
        if (!st.opensNotes) reasons.push('Not opening posted course resources');
        if (!reasons.length) reasons.push('Consistent performance across assessments', 'All outcomes tracking above benchmarks');
        return {
          id: st.id, name: st.name, att: pct(st.attendanceRate), comp: pct(1 - st.lateSubRate),
          score: Math.max(2, r.score), grade: gradeOf(r.avg), reasons,
        };
      });
      /* scatter from real cohort performance */
      const scatter = roster.map(st => {
        const h = A.history(st.id).filter(x => x.course === code);
        if (!h.length) return null;
        return { att: pct(st.attendanceRate), res: pct(mean(h.map(x => x.p))), sid: st.id, name: st.name };
      }).filter(Boolean);
      const R = pearsonR(scatter.map(p => [p.att, p.res]));
      /* question analytics from the latest heavyweight assessment */
      const latest = A.of(code, c.sections[0]).slice(-1)[0];
      const qa = latest.questions.map(q => {
        const rate = mean(latest.marks.map(r => r.perQ.find(x => x.no === q.no).got)) / q.marks;
        const attempted = Math.round(mean(latest.marks.map(r => r.perQ.find(x => x.no === q.no).got > 0 ? 1 : 0)) * 100);
        const suc = pct(rate);
        const diff = suc >= 75 ? 'Easy' : suc >= 55 ? 'Medium' : suc >= 35 ? 'Hard' : 'Extreme';
        const recs = {
          Easy: 'Maintain current test vectors; baseline is healthy.',
          Medium: 'Excellent benchmark question.',
          Hard: `Schedule a review session on ${q.topic} before reuse.`,
          Extreme: `CRITICAL: Urgent syllabus alignment review suggested for ${q.topic}.`,
        };
        return {
          id: 'Q' + q.no, co: q.co, att: attempted, suc, diff,
          reasons: [`Mapped to ${q.co} · ${q.bloom} level on "${q.topic}".`, `Class correct-rate ${suc}% across ${latest.marks.length} scripts (${latest.title}).`],
          rec: recs[diff],
        };
      });
      /* health metrics from cohort traits */
      const attendanceM = Math.round(mean(roster.map(st => st.attendanceRate)) * 100);
      const assignmentM = Math.round(mean(roster.map(st => 1 - st.lateSubRate)) * 100);
      const examM = pct(mean(latest.marks.map(r => r.total / latest.totalMarks)));
      const engagementM = pct(roster.filter(st => st.opensNotes).length / roster.length);
      const metrics = { attendance: attendanceM, assignment: assignmentM, exam: examM, coAchievement: coAvg, engagement: engagementM };
      /* teaching effectiveness */
      const eff = Math.round(0.35 * coAvg + 0.35 * attendanceM + 0.3 * assignmentM);
      const cat = eff >= 85 ? 'Excellent' : eff >= 75 ? 'Good' : eff >= 65 ? 'Average' : 'Needs Improvement';
      const corr = A.correlations(code, c.sections[0]);
      const attCorr = corr.find(x => x.what.includes('attendance'));
      const feedback = {
        score: eff, cat, rating: +(coAvg / 10).toFixed(1), engagement: engagementM, completion: assignmentM,
        impact: attCorr
          ? `Strong positive impact: Students with attendance > 80% achieve ${attCorr.gap}% higher attainment averages.`
          : 'Attendance signal is stable across the cohort this term.',
      };
      /* weak outcome detector cards */
      const topics = A.topics(code);
      const worstT = topics[0], secondT = topics[1] || topics[0];
      const weak = [
        { icon: 'shield-alert', pr: 'High', title: `Critical Outcome Fail: ${sorted[0].id}`, chip: `Syllabus Mapped ✕ ${worstT.topic}`,
          insight: `Students underperformed on "${worstT.topic}" items (class correct-rate ${pct(worstT.rate)}%). ${sorted[0].id} attainment sits at ${sorted[0].ach}%, against the ${c.coTarget}% target.`,
          impact: `${sorted[0].ach - c.coTarget}% under baseline targets`, cure: `Re-teach ${worstT.topic} with a targeted practice set next week.` },
        { icon: 'square-check', pr: 'High', title: 'Attendance correlation threshold failure', chip: 'Attendance Logs ✕ Assignment Scores',
          insight: attCorr
            ? `AI pattern detection identified that students with attendance ≥ 80% average ${attCorr.with_}% versus ${attCorr.without}% for the rest — a ${attCorr.gap}-point attainment gap.`
            : 'AI pattern detection found attendance and attainment moving together across the cohort this term.',
          impact: `Pearson's Correlation Coefficient R = ${R.toFixed(2)}`, cure: 'Activate daily automatic engagement trigger reminders dynamically.' },
        { icon: 'book-open', pr: 'Medium', title: `Mild Outcome Attainment Dip: ${sorted[1] ? sorted[1].id : sorted[0].id}`, chip: `${sorted[1] ? sorted[1].name : sorted[0].name} ✕ ${secondT.topic}`,
          insight: `A dip on "${secondT.topic}" (correct-rate ${pct(secondT.rate)}%) is dragging ${sorted[1] ? sorted[1].id : sorted[0].id} to ${sorted[1] ? sorted[1].ach : sorted[0].ach}%. The miss pattern is concentrated, not spread evenly.`,
          impact: 'Diverging per-question evaluations detected', cure: `Add a scaffolded ${secondT.topic} problem set to the resource hub.` },
      ];
      const health = Math.round((attendanceM * 25 + assignmentM * 20 + examM * 30 + coAvg * 15 + engagementM * 10) / 100);
      const atRisk = ranked.filter(x => x.r.tier === 'red').length;
      const active = roster.filter(st => st.attendanceRate >= 0.6).length;
      const poScore = +(coAvg - 2.5).toFixed(1);
      const cards = [
        { id: 'total-students', title: 'Total Students', value: String(roster.length), trend: 'Baseline Standard', pos: true, icon: 'users', theme: 'indigo', spark: ramp(roster.length) },
        { id: 'active-enrolled', title: 'Active Enrolled', value: String(active), trend: `${pct(active / roster.length)}% Active engagement`, pos: true, icon: 'user-check', theme: 'blue', spark: ramp(active) },
        { id: 'co-attainment', title: 'CO attainment', value: coAvg + '%', trend: '+1.2% improvement', pos: coAvg >= c.coTarget, icon: 'percent', theme: 'purple', spark: ramp(coAvg) },
        { id: 'po-mapping-score', title: 'PO mapping score', value: poScore + '%', trend: 'Awaiting cycle completion', pos: true, icon: 'award', theme: 'orange', spark: ramp(poScore) },
        { id: 'at-risk-students', title: 'At-Risk Students', value: `${atRisk} Students`, trend: atRisk <= 4 ? '28.5% drop in risk density' : 'Rising risk density flagged', pos: atRisk <= 4, icon: 'shield-alert', theme: 'red', spark: ramp(Math.max(1, atRisk)) },
      ];
      return {
        code, title: c.title, target: c.coTarget, live: false,
        cos, matrixSeed: code === 'CSE220' ? MATRIX_220 : MATRIX_ENG,
        heat, heatDiag, risks, scatter, pearson: R.toFixed(2),
        scatterInsight1: `Students with <strong>below 60%</strong> attendance in ${code} trail the cohort attainment mean by a visible margin.`,
        qa, qaDefault: qa.slice().sort((a, b) => a.suc - b.suc)[0].id,
        weak, feedback, metrics, cards,
        healthSpark: ramp(health), initialHealth: health,
        alert: {
          coId: sorted[0].id,
          body: `<strong>${sorted[0].id} (${esc(sorted[0].name)})</strong> attainment is at <strong>${sorted[0].ach}%</strong> against the ${c.coTarget}% target. Cross-examination of grading files points at "${worstT.topic}" as the dominant miss pattern.`,
          sim: { ach: Math.min(95, sorted[0].ach + 16), pass: Math.round(Math.min(95, sorted[0].ach + 16) / 100 * roster.length), fail: roster.length - Math.round(Math.min(95, sorted[0].ach + 16) / 100 * roster.length), tp: 16.0, heatCell: null, toast: `Sandbox Simulation Success: ${worstT.topic} workshop applied! ${sorted[0].id} Attainment boosted to ${Math.min(95, sorted[0].ach + 16)}%!` },
        },
        accSection1: cos.map(co => [`${co.id} (${co.name})`, co.ach + '%', co.ach >= 80 ? 'Exceeded' : co.ach >= c.coTarget ? 'Achieved' : 'Critically Failed']),
        accS2: `Mapped program variables are evaluated at an average intensity tier of <strong>${poScore}%</strong>. Strong conversion was registered for <strong>PO1 (Knowledge)</strong> and <strong>PO9 (Team Work)</strong>. The weakest mapped deficit traces to <strong>${sorted[0].id} (${esc(sorted[0].name)})</strong> at <strong>${sorted[0].ach}%</strong>.`,
        accS3: `Detailed diagnostic reviews indicate the softest performance in ${minSec} (${minCo.id} attainment ${minV}%). Correlation matrices verify a linear dependency (Pearson R = ${R.toFixed(2)}) between interactive lecture attendance variables and output final submissions.`,
        accCqi: [
          [`Re-teach ${worstT.topic}:`, ` Run a remedial block with a scaffolded practice set before the next heavyweight assessment.`],
          ['Address attendance drop:', ` Trigger automatic alerts for ${minSec} students failing the class engagement threshold level (<75%).`],
          [`Question realignment:`, ` Review low-success items from "${latest.title}" and rebalance Bloom coverage in the next paper.`],
        ],
        accQuote: `"Instructor demonstrates ${cat === 'Excellent' ? 'premium' : 'solid'} teaching efficiency metrics (Teaching Effectiveness Index of ${eff}%). Recommended course status is Fully Compliant, with the immediate requirement to file the ${sorted[0].id} remediation plan before the Week 12 evaluation deadline."`,
      };
    }

    const buildData = (code) => code === 'CSE311' ? build311() : buildComputed(code);

    /* ---------- state ---------- */
    let course = 'CSE311';
    let D = buildData(course);
    const uiByCourse = {};
    function uiFor(code) {
      if (!uiByCourse[code]) {
        const d = code === course ? D : buildData(code);
        const matrix = {};
        Object.entries(d.matrixSeed).forEach(([co, row]) => row.split('').forEach((ch, i) => matrix[co + '|' + POS[i]] = INT_OF[ch]));
        uiByCourse[code] = {
          matrix, weights: { ...DEFAULT_WEIGHTS },
          selQ: d.qaDefault, selRisk: d.risks[0].id, riskFilter: 'All',
          cardHealth: d.initialHealth, simDone: false,
        };
      }
      return uiByCourse[code];
    }
    let S = uiFor(course);
    const nowTs = () => new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    const topRisk = () => D.risks[0];
    let chat = [];
    let chatTyping = false;
    let feedOpen = true;
    let voice = { listening: false, processing: false, transcript: '', response: '' };
    let accTimer = null;

    const chatSeed = () => ({
      me: false, ts: '09:25 AM',
      text: `### Welcome to the OBE Intelligence Copilot\n\nI am analyzing **Summer 2026 course records**. Currently mapping **81 Students** and **12 Program Outcomes**.\n\n*   **Alert**: CO3 (Communication Skills) is at **58%**, falling below your threshold profile.\n*   **Risk**: ${topRisk().name} is predicted to fail (F) due to a 52% attendance deficit.\n\nAsk me anything! For example: *'What is causing the CO3 drop?'* or *'Draft an accreditation CQI plan.'*`,
    });
    chat = [chatSeed()];

    function chatReply(q) {
      const s = q.toLowerCase();
      if (s.includes('data structure') || s.includes('cse220')) return `### Data Structures (CSE220) Analysis\n\nHere are some automatically generated insights for your Data Structures course:\n\n*   **CO1 (Foundations):** Performance is stable at 82%.\n*   **CO2 (Graph Algorithms):** We're seeing a slight dip (down to 65%). I recommend incorporating more interactive graph-visualizer labs.\n\nWould you like me to generate a remedial quiz on graph traversals?`;
      if (s.includes('co3') || s.includes('diagnose') || s.includes('explain')) return `### CO3 Diagnostic Report\n\nCO3 (Communication Skills) is currently falling behind primarily due to **low participation in group presentations** (Week 5 & 6).\n\n*   **Key Factor:** 14 students missed the presentation sessions.\n*   **Recommendation:** Schedule a makeup peer-review session to lift attainment by an estimated 10%.`;
      if (s.includes('cqi') || s.includes('accreditation') || s.includes('draft')) return `### Accreditation CQI Plan Drafted\n\nI have drafted a continuous quality improvement (CQI) plan for the upcoming NBA accreditation:\n\n1.  **Target Area:** Enhancing lab-based assessment criteria.\n2.  **Action Plan:** Integrate real-world industry case studies into mid-term evaluations.\n3.  **Expected Outcome:** Improved CO-PO mapping correlation by +0.15 points.`;
      if (/\bhi\b|\bhello\b/.test(s)) return `Hello! I am your OBE Intelligence Copilot. How can I assist you with your academic metrics today?`;
      return `Based on the latest data analysis, the course outcomes are currently aligned with the syllabus. However, feel free to ask about specific modules, students, or accreditation reports.`;
    }

    function voiceReply(cmd) {
      const s = cmd.toLowerCase();
      if (s.includes('weak')) { scrollFocus('section-weak-outcome'); return `Highlighting the AI Weak Outcome Detector. Three live diagnostics are active — the most severe is "${D.weak[0].title}" with impact ${D.weak[0].impact}.`; }
      if (s.includes('risk') || s.includes('student')) { scrollFocus('section-risk-prediction', 'rose'); return `${D.risks.filter(r => r.score >= 75).length} student(s) sit in the high-risk band right now. ${topRisk().name} leads the list with a ${topRisk().score}% failure probability and predicted grade ${topRisk().grade}.`; }
      if (s.includes('accreditation') || s.includes('report')) { scrollFocus('section-accreditation', 'emerald'); return `The accreditation compiler is armed. Press "Calculate & Compile Accreditation Report" to assemble the ABET & NBA attainment package for ${D.code}.`; }
      if (s.includes('co3') || s.includes('communication')) { scrollFocus('section-co-achievement'); return `${D.alert.coId} attainment is currently ${D.cos.find(c => c.id === D.alert.coId).ach}% against the ${D.target}% target. I have highlighted the CO Achievement Analytics panel — the Adjustment Sandbox can simulate a remediation lift.`; }
      return `Command digested. The ${D.code} dashboard is synchronized — CO attainment averages ${D.metrics.coAchievement}% with ${D.risks.filter(r => r.score >= 75).length} high-risk students flagged.`;
    }

    /* ---------- shared bits ---------- */
    const coTone = (ach) => ach >= 90
      ? { bg: '#ecfdf5', tx: '#047857', bd: '#d1fae5', bar: '#10b981', pc: '#059669' }
      : ach >= 70
        ? { bg: '#fffbeb', tx: '#b45309', bd: '#fef3c7', bar: '#f59e0b', pc: '#d97706' }
        : { bg: '#fff1f2', tx: '#be123c', bd: '#ffe4e6', bar: '#f43f5e', pc: '#e11d48' };
    const heatTone = (v) => v >= 90 ? 'background:#10b981;color:#fff;font-weight:800;box-shadow:0 1px 2px rgba(0,0,0,.08)'
      : v >= 80 ? 'background:#34d399;color:#09090b;font-weight:600'
      : v >= 70 ? 'background:#fcd34d;color:#09090b;font-weight:500'
      : v >= 60 ? 'background:#fdba74;color:#09090b;font-weight:400'
      : 'background:#f43f5e;color:#fff;font-weight:800;box-shadow:0 1px 2px rgba(0,0,0,.08);animation:o-pulse 2s cubic-bezier(.4,0,.6,1) infinite';
    const riskTone = (score) => score >= 75 ? { dot: '#f43f5e', tx: '#e11d48' } : score >= 45 ? { dot: '#f59e0b', tx: '#d97706' } : { dot: '#10b981', tx: '#059669' };
    const phead = (iconName, iconBg, iconColor, title, sub, right) => `
      <div class="o-ph"><div class="lft">
        <span class="o-ic" style="background:${iconBg};color:${iconColor}">${xi(iconName, 18)}</span>
        <span><h4>${title}</h4><p class="ps">${sub}</p></span></div>${right || ''}</div>`;

    /* ---------- header / hero / overview cards ---------- */
    function headHtml() {
      return `
      <div class="o-head">
        <div style="display:flex;align-items:center;gap:10px;min-width:0">
          <span class="o-logo">${xi('bot', 20, 'o-pulse')}</span>
          <span style="min-width:0">
            <span style="display:flex;align-items:center"><h2 style="font-size:16px;font-weight:800;letter-spacing:-.02em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">OBE Analytics &amp; Intelligence Center</h2><span class="o-vbadge">v3.5</span></span>
            <span style="display:block;font:400 10px var(--font-mono);color:var(--omf)">University Teacher Portal • Summer 2026</span>
          </span>
        </div>
        <span class="o-userpill"><span class="o-dot"></span>User: <b>${esc(T.teacher.email)}</b></span>
      </div>`;
    }
    function heroHtml() {
      return `
      <div class="o-hero">
        <div>
          <div class="o-eyebrow">OUTCOME BASED EDUCATION INTELLIGENCE PORTAL</div>
          <h2><select id="obeCourse" aria-label="Course">${T.courses.map(c => `<option value="${c.code}" ${c.code === course ? 'selected' : ''}>${c.code}: ${esc(c.title)}</option>`).join('')}</select></h2>
          <div class="sub">Continuous accreditation mapping, AI failure prognosis, exam question diagnostics, and CQI planning logs.</div>
        </div>
        <div class="o-actions">
          <button class="o-abtn em" id="btn-quick-reports">${xi('file-text', 13)}Generate Accreditation Report</button>
          <button class="o-abtn in" id="btn-quick-pdf">${xi('printer', 13)}Export PDF</button>
          <button class="o-abtn pu" id="btn-ai-insights">${xi('sparkles', 13)}AI Insights</button>
          <button class="o-abtn re" id="btn-voice-assist">${xi('volume-2', 13)}Voice Assistant</button>
          <button class="o-abtn sec" id="btn-download-copo">${xi('download', 13)}Download CO-PO Report</button>
        </div>
      </div>`;
    }
    function cardHtml(cfg, i) {
      const th = CARD_THEMES[cfg.theme];
      return `
      <div class="o-card" id="card-${cfg.id}">
        <div style="display:flex;align-items:flex-start;justify-content:space-between">
          <div><div class="tt">${cfg.title}</div><div class="vv" data-cardval>${cfg.value}</div></div>
          <span class="ictile" data-cardic style="background:${th.bg};border:1px solid ${th.bd};color:${th.txt}">${xi(cfg.icon, 20)}</span>
        </div>
        <div class="bot">
          <span class="o-tpill ${cfg.pos ? 'up' : 'dn'}" data-cardpill>${cfg.pos ? '↑' : '↓'} ${cfg.trend}</span>
          ${areaSpark(cfg.spark, th.g, D.code + '-' + i)}
        </div>
      </div>`;
    }
    function cardsHtml() {
      const healthCfg = {
        id: 'course-health', title: 'Course Health', value: Math.round(S.cardHealth) + '%',
        trend: S.cardHealth >= 80 ? '+6.3% premium health' : 'Attention needed on CO3',
        pos: S.cardHealth >= 80, icon: 'percent', theme: S.cardHealth >= 80 ? 'green' : 'orange', spark: D.healthSpark,
      };
      return `<div class="o-cards" id="section-course-overview">${[...D.cards, healthCfg].map(cardHtml).join('')}</div>`;
    }

    /* ---------- CO Achievement Analytics ---------- */
    function coAchHtml() {
      const legend = `<div style="display:flex;gap:12px;font-size:12px;font-weight:500;color:var(--omf);align-items:center;flex-wrap:wrap">
        <span style="display:inline-flex;align-items:center;gap:6px"><i style="width:10px;height:10px;border-radius:999px;background:#10b981"></i>&gt;=90%</span>
        <span style="display:inline-flex;align-items:center;gap:6px"><i style="width:10px;height:10px;border-radius:999px;background:#f59e0b"></i>70-89%</span>
        <span style="display:inline-flex;align-items:center;gap:6px"><i style="width:10px;height:10px;border-radius:999px;background:#f43f5e"></i>&lt;70%</span></div>`;
      const rows = D.cos.map(co => {
        const t = coTone(co.ach);
        const trendChip = co.trend === 'up'
          ? `<span style="color:#10b981;font-size:12px;display:inline-flex;align-items:center;gap:3px">${xi('trending-up', 12)}+${co.tp}%</span>`
          : co.trend === 'down'
            ? `<span style="color:#f43f5e;font-size:12px;display:inline-flex;align-items:center;gap:3px">${xi('trending-down', 12)}${co.tp}%</span>` : '';
        return `
        <div class="o-corow">
          <div style="flex:1;min-width:230px;max-width:512px">
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
              <span class="o-cobadge" style="background:${t.bg};color:${t.tx};border-color:${t.bd}">${co.id}</span>
              <b style="font-size:14px;letter-spacing:-.01em">${esc(co.name)}</b>${trendChip}
            </div>
            <div style="font-size:12px;color:var(--omf);margin-top:5px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden">${esc(co.desc)}</div>
          </div>
          <div style="width:288px;max-width:100%;display:flex;flex-direction:column;gap:6px">
            <div style="display:flex;justify-content:space-between;font-size:12px"><span style="font-weight:500;color:var(--omf)">Attainment Rate</span><b style="font-weight:800;color:${t.pc}">${co.ach}%</b></div>
            <div class="o-bar"><i data-cobar="${co.ach}" style="background:${t.bar}"></i></div>
            <div style="display:flex;justify-content:space-between;font:400 11px var(--font-mono);color:var(--omf)"><span>Pass: ${co.pass} students</span><span>Gap: ${co.fail} failed</span></div>
          </div>
          <button class="btn ghost sm" id="btn-analyze-${co.id.toLowerCase()}" data-analyze="${co.id}" style="color:#4f46e5;border-color:transparent">Analyze Outbound</button>
        </div>`;
      }).join('');
      const alert = `
        <div style="margin:0 20px 20px;padding:16px;border-radius:12px;background:rgba(255,241,242,.7);border:1px solid #ffe4e6;display:flex;gap:12px;align-items:flex-start">
          <span class="o-ic o-pulse" style="background:rgba(255,228,230,.8);color:#e11d48;padding:8px;margin-top:2px">${xi('alert-circle', 18)}</span>
          <div style="flex:1">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap">
              <h5 style="font-size:14px;font-weight:700;color:#881337;margin:0">AI Alert: Performance Deficit in Mapped Outcomes</h5>
              <span style="font:700 10px var(--font-mono);letter-spacing:.05em;text-transform:uppercase;background:rgba(254,205,211,.8);color:#9f1239;padding:2px 8px;border-radius:999px">Action Required</span>
            </div>
            <p style="font-size:12px;color:#9f1239;margin:4px 0 0">${D.alert.body}</p>
            <button class="o-abtn" id="btn-ai-optimize-co3" style="margin-top:12px;background:#e11d48;font-size:11px;padding:4px 12px" ${S.simDone ? 'disabled' : ''}>${xi('refresh-cw', 12, 'o-spin')}Run ${D.alert.coId} Adjustment Sandbox</button>
          </div>
        </div>`;
      return `
      <div class="o-sec" id="section-co-achievement"><div class="o-panel" style="padding:0;overflow:hidden">
        <div style="padding:20px;border-bottom:1px solid var(--ob);background:rgba(244,244,245,.5)">
          ${phead('bar-chart-2', '#eef2ff', '#4f46e5', 'CO Achievement Analytics', `Course outcome mapping against target thresholds of ${D.target}% attainment`, legend)}
        </div>
        <div style="padding:2px 20px">${rows}</div>
        ${alert}
      </div></div>`;
    }
    function wireCoAch() {
      root.querySelectorAll('[data-analyze]').forEach(b => b.onclick = () => {
        scrollFocus('section-co-po-mapping');
        obeToast(`Analyzing aligned alignments for ${b.dataset.analyze}`);
      });
      const sim = root.querySelector('#btn-ai-optimize-co3');
      if (sim) sim.onclick = () => {
        const co = D.cos.find(c => c.id === D.alert.coId);
        const s = D.alert.sim;
        co.ach = s.ach; co.pass = s.pass; co.fail = s.fail; co.trend = 'up'; co.tp = s.tp;
        if (s.heatCell) {
          const row = D.heat.rows.find(r => r[0] === s.heatCell[0]);
          const si = D.heat.sections.indexOf(s.heatCell[1]);
          if (row && si >= 0) row[si + 1] = s.heatCell[2];
        }
        S.simDone = true;
        redraw('section-co-achievement', coAchHtml, wireCoAch);
        redraw('section-obe-heatmap', heatHtml, null);
        obeToast(s.toast);
      };
      requestAnimationFrame(() => root.querySelectorAll('[data-cobar]').forEach(i => i.style.width = i.dataset.cobar + '%'));
    }

    /* ---------- CO-PO Attainment Alignment Matrix ---------- */
    function matrixHtml() {
      const cos = D.cos.map(c => c.id);
      const head = `<tr><th class="oc">Outcome</th>${POS.map(po => `<th><u>${po}</u><span class="o-pop">${esc(PO_DESC[po])}</span></th>`).join('')}</tr>`;
      const body = cos.map(co => `<tr><td class="oc">${co}</td>${POS.map(po => {
        const v = S.matrix[co + '|' + po];
        const cls = v === 'High' ? 'H' : v === 'Medium' ? 'M' : v === 'Low' ? 'L' : 'N';
        const label = v === 'None' ? '-' : v.slice(0, 3);
        return `<td data-mxcell data-co="${co}" data-po="${po}"><button class="o-mxb ${cls}" id="cell-${co.toLowerCase()}-${po.toLowerCase()}">${label}</button></td>`;
      }).join('')}</tr>`).join('');
      return `
      <div class="o-sec" id="section-co-po-mapping"><div class="o-panel">
        ${phead('grid-2x2', '#eef2ff', '#4f46e5', 'CO-PO Attainment Alignment Matrix', 'Heatmap representation of Course Outcomes (CO) mapping weights to Program Outcomes (PO 1-12)', '<span class="o-monopill">Click Cell to Rotate Weight</span>')}
        <div style="overflow-x:auto;border:1px solid var(--ob);border-radius:8px"><table class="o-mx">${head}${body}</table></div>
        <div style="margin-top:16px;display:flex;justify-content:space-between;flex-wrap:wrap;gap:10px;font-size:12px;align-items:center">
          <div style="display:flex;gap:12px;flex-wrap:wrap">
            <span><i class="o-sw" style="background:#4f46e5"></i>High (Weight: 3)</span>
            <span><i class="o-sw" style="background:#c7d2fe"></i>Medium (Weight: 2)</span>
            <span><i class="o-sw" style="background:#eef2ff;border:1px solid #c7d2fe"></i>Low (Weight: 1)</span>
            <span><i class="o-sw" style="background:var(--omut);border:1px solid var(--ob)"></i>Unmapped (-)</span>
          </div>
          <span style="font-size:11px;font-style:italic;color:var(--omf)">Tip: Hover over PO column headers to view ABET definitions.</span>
        </div>
        <div class="o-intel" id="mxIntel"></div>
      </div></div>`;
    }
    function wireMatrix() {
      const intel = root.querySelector('#mxIntel');
      root.querySelectorAll('[data-mxcell]').forEach(td => {
        const co = td.dataset.co, po = td.dataset.po;
        const btn = td.querySelector('button');
        btn.onclick = () => {
          const cur = S.matrix[co + '|' + po];
          const next = CYCLE[(CYCLE.indexOf(cur) + 1) % 4];
          S.matrix[co + '|' + po] = next;
          btn.className = 'o-mxb ' + (next === 'High' ? 'H' : next === 'Medium' ? 'M' : next === 'Low' ? 'L' : 'N');
          btn.textContent = next === 'None' ? '-' : next.slice(0, 3);
          showIntel(co, po);
        };
        td.addEventListener('mouseenter', () => showIntel(co, po));
        td.addEventListener('mouseleave', () => intel.classList.remove('show'));
      });
      function showIntel(co, po) {
        const v = S.matrix[co + '|' + po];
        const why = v === 'High' ? `CO holds direct critical testing parameters strictly measuring progress for ${po}.`
          : v === 'Medium' ? `Students execute exercises mapping intermediate indicators of ${po}.`
          : v === 'Low' ? 'Slight secondary context is developed inside course lectures.'
          : 'No formal curriculum mappings configured.';
        intel.innerHTML = `
          <h5 style="font:700 12px var(--font-mono);color:#312e81;text-transform:uppercase;letter-spacing:.12em;margin:0 0 8px">Alignment Intel: ${co} ✕ ${po}</h5>
          <div style="font-size:12px;font-weight:600">${po} Definition:</div>
          <div style="font-size:12px;font-style:italic;color:#52525b;margin-top:2px">${esc(PO_DESC[po])}</div>
          <div style="border-top:1px solid rgba(224,231,255,.5);margin-top:8px;padding-top:8px;font-size:11px"><b>Intensity:</b> ${v}</div>
          <div style="font-size:10px;color:var(--omf);margin-top:3px">${why}</div>`;
        intel.classList.add('show');
      }
    }

    /* ---------- University Class OBE Heatmap ---------- */
    function heatHtml() {
      const nSec = D.heat.sections.length;
      const gridCols = `grid-template-columns:repeat(${nSec + 1},1fr)`;
      const headRow = `<div style="display:grid;${gridCols};gap:8px;margin-bottom:8px;font:700 12px var(--font-mono);color:var(--omf);text-align:center">
        <span style="text-align:left;padding-left:8px">Outcome</span>
        ${D.heat.sections.map(s => `<span style="background:#fff;padding:6px 0;border-radius:6px;border:1px solid rgba(228,228,231,.5);box-shadow:0 1px 2px rgba(0,0,0,.04)">${s}</span>`).join('')}</div>`;
      const rows = D.heat.rows.map(r => `<div style="display:grid;${gridCols};gap:8px;align-items:center;font-size:12px;text-align:center">
        <span style="text-align:left;font:800 12px var(--font-mono);text-transform:uppercase;padding:10px 0 10px 8px;background:#f4f4f5;border-radius:8px">${r[0]}</span>
        ${r.slice(1).map((v, si) => `<span class="o-heatcell" style="${heatTone(v)}" title="${r[0]} Attainment in ${D.heat.sections[si]}: ${v}%">${v}%</span>`).join('')}</div>`).join('');
      const dg = D.heatDiag;
      return `
      <div class="o-sec" id="section-obe-heatmap"><div class="o-panel">
        ${phead('layout-grid', '#fff1f2', '#e11d48', 'University Class OBE Heatmap', 'Section-wise attainment density showing weak student groups quickly', '')}
        <div style="display:grid;grid-template-columns:3fr 1fr;gap:24px" class="o-heatgrid">
          <div style="overflow-x:auto"><div style="min-width:400px;border:1px solid var(--ob);border-radius:8px;padding:12px;background:rgba(244,244,245,.3);display:flex;flex-direction:column;gap:8px">${headRow}${rows}</div></div>
          <div style="display:flex;flex-direction:column;justify-content:space-between;gap:14px;min-width:190px">
            <div style="padding:16px;border-radius:12px;border:1px solid #ffe4e6;background:rgba(244,244,245,.5)">
              <div style="display:flex;align-items:center;gap:6px;color:#e11d48;font:700 12px var(--font-mono);text-transform:uppercase;letter-spacing:.05em">${xi('alert-triangle', 14)}Diagnostic Warning</div>
              <h5 style="font-size:14px;font-weight:700;margin:8px 0 0">${esc(dg.h5)}</h5>
              <p style="font-size:12px;color:var(--omf);line-height:1.6;margin:6px 0 0">${esc(dg.p)}</p>
              <ul style="padding-left:16px;font-size:12px;margin:6px 0 0;display:flex;flex-direction:column;gap:4px">
                ${dg.bullets.map(b => `<li><strong style="color:${b[1]}">${esc(b[0])}</strong></li>`).join('')}
              </ul>
              <div style="font-size:10px;font-style:italic;color:#8d8d97;border-top:1px solid var(--ob);margin-top:12px;padding-top:8px">${esc(dg.note)}</div>
            </div>
            <div style="padding:12px;background:rgba(244,244,245,.3);border-radius:8px;border:1px solid var(--ob);font-size:10px;display:flex;flex-direction:column;gap:4px">
              <div style="display:flex;justify-content:space-between;gap:8px"><span><i class="o-sw" style="width:8px;height:8px;border-radius:2px;background:#10b981"></i>Excellent (&gt;=90%)</span><span><i class="o-sw" style="width:8px;height:8px;border-radius:2px;background:#34d399"></i>Good (80-89%)</span></div>
              <div style="display:flex;justify-content:space-between;gap:8px"><span><i class="o-sw" style="width:8px;height:8px;border-radius:2px;background:#fcd34d"></i>Satisfactory (70-79%)</span><span><i class="o-sw" style="width:8px;height:8px;border-radius:2px;background:#fdba74"></i>Warning (60-69%)</span></div>
              <div><span><i class="o-sw" style="width:8px;height:8px;border-radius:2px;background:#f43f5e"></i>At Risk (&lt;60%)</span></div>
            </div>
          </div>
        </div>
      </div></div>`;
    }

    /* ---------- AI Weak Outcome Detector ---------- */
    function weakHtml() {
      const badge = `<span class="o-pulse" style="display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:700;color:#db2777;background:#fdf2f8;padding:2px 8px;border-radius:6px">${xi('sparkles', 12)}Live AI Diagnostics Mode</span>`;
      const cards = D.weak.map(w => `
        <div class="o-wcard">
          <span class="o-wico">${xi(w.icon, 20)}</span>
          <div style="flex:1;min-width:0">
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;justify-content:space-between">
              <span style="display:inline-flex;align-items:center;gap:8px;flex-wrap:wrap"><b style="font-size:14px">${esc(w.title)}</b>
              <span style="font:400 10px var(--font-mono);color:var(--omf);background:#f4f4f5;padding:2px 8px;border-radius:6px">${esc(w.chip)}</span></span>
              <span class="o-prpill ${w.pr}">${w.pr} Priority</span>
            </div>
            <p style="font-size:12px;color:#52525b;margin:8px 0 0;line-height:1.6">${esc(w.insight)}</p>
            <div style="border-top:1px solid var(--ob);margin-top:14px;padding-top:12px;display:grid;grid-template-columns:1fr 1fr;gap:16px;font-size:12px">
              <span><span style="display:block;font:400 11px var(--font-mono);color:var(--omf)">Attainment Gap Impact:</span><b style="font-weight:800">${esc(w.impact)}</b></span>
              <span><span style="display:block;font:400 11px var(--font-mono);color:var(--omf)">Cure Plan Intervention:</span><b style="color:#4f46e5">${esc(w.cure)}</b></span>
            </div>
          </div>
        </div>`).join('');
      return `
      <div class="o-sec" id="section-weak-outcome"><div class="o-panel">
        ${phead('search-code', '#faf5ff', '#9333ea', 'AI Weak Outcome Detector', 'Automated multi-factor evaluation of quizzes, homework assignments, and attendance logs', badge)}
        <div style="display:flex;flex-direction:column;gap:16px">${cards}</div>
      </div></div>`;
    }

    /* ---------- Student Failure risk prediction ---------- */
    function riskListHtml() {
      const f = S.riskFilter;
      const list = D.risks.filter(r => f === 'All' ? true : f === 'High' ? r.score >= 75 : f === 'Medium' ? (r.score >= 45 && r.score < 75) : r.score < 45);
      if (!list.find(r => r.id === S.selRisk) && list.length) S.selRisk = list[0].id;
      return list.map(r => {
        const t = riskTone(r.score);
        return `
        <div class="o-riskrow ${r.id === S.selRisk ? 'sel' : ''}" id="student-row-${r.id.toLowerCase()}" data-risk="${r.id}">
          <span style="display:flex;align-items:center;gap:10px;min-width:0">
            <i style="width:12px;height:12px;border-radius:999px;background:${t.dot};flex:0 0 auto"></i>
            <span style="min-width:0"><b style="font-size:14px;display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${esc(r.name)}</b><span style="font:400 10px var(--font-mono);color:var(--omf)">${r.id}</span></span>
          </span>
          <span style="display:flex;gap:18px;font-size:12px;text-align:right;flex:0 0 auto">
            <span><span class="o-rlab">Attendance</span><br><b style="color:${r.att < 75 ? '#e11d48' : 'inherit'}">${r.att}%</b></span>
            <span class="o-hidesm"><span class="o-rlab">Completion</span><br><b>${r.comp}%</b></span>
            <span><span class="o-rlab" style="background:#f4f4f5;padding:2px 4px;border-radius:2px">Pred</span><br><b style="font-weight:800;color:${r.grade === 'F' ? '#e11d48' : 'inherit'}">${r.grade}</b></span>
            <span><span class="o-rlab">Risk</span><br><b style="font-weight:800;color:${t.tx}">${r.score}%</b></span>
          </span>
        </div>`;
      }).join('') || '<p class="hint" style="padding:10px">No students in this risk band.</p>';
    }
    function riskPanelHtml() {
      const r = D.risks.find(x => x.id === S.selRisk) || D.risks[0];
      return `
        <div style="display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--ob);padding-bottom:12px">
          <span style="display:inline-flex;align-items:center;gap:6px;font-size:14px;font-weight:700;text-transform:uppercase;letter-spacing:.03em;color:#4f46e5">${xi('sparkles', 14, 'o-spin')}Risk Explainability AI</span>
          <span style="font:400 10px var(--font-mono);color:#a1a1aa">OBE Pipeline v3</span>
        </div>
        <div style="margin-top:16px">
          <div class="o-detrow"><span style="color:var(--omf)">Student Entity:</span><b>${esc(r.name)}</b></div>
          <div class="o-detrow"><span style="color:var(--omf)">Predicted Semester Grade:</span><b style="font:800 14px var(--font-mono);color:#4f46e5">${r.grade}</b></div>
          <div class="o-detrow"><span style="color:var(--omf)">Total Risk Probability:</span><b style="font-weight:800">${r.score}%</b></div>
        </div>
        <div style="font:600 12px var(--font-mono);color:var(--omf);text-transform:uppercase;letter-spacing:.12em;margin:20px 0 8px">AI Attribution Factors:</div>
        ${r.reasons.map(x => `<div class="o-factor"><span style="color:#818cf8;margin-top:1px">${xi('info', 14)}</span><span>${esc(x)}</span></div>`).join('')}
        <div style="border-top:1px solid var(--ob);margin-top:20px;padding-top:12px">
          <button class="o-abtn in" id="btn-email-${r.id.toLowerCase()}" data-riskaction="${r.id}" style="width:100%;justify-content:center;padding:8px 0">Trigger Targeted Academic Action</button>
        </div>`;
    }
    function riskHtml() {
      const tabs = `<div style="background:#f4f4f5;border-radius:8px;padding:2px;display:inline-flex;white-space:nowrap">${['All', 'High', 'Medium', 'Low'].map(t => `<button class="o-tab ${S.riskFilter === t ? 'on' : ''}" data-rtab="${t}">${t}</button>`).join('')}</div>`;
      return `
      <div class="o-sec" id="section-risk-prediction"><div class="o-panel">
        ${phead('shield-alert', '#fff1f2', '#e11d48', 'Student Failure risk prediction', 'Deep Learning risk prognosis model detecting students likely to underperform', tabs)}
        <div style="display:grid;grid-template-columns:7fr 5fr;gap:20px" class="o-riskgrid">
          <div id="riskList" style="max-height:380px;overflow-y:auto;padding-right:4px;display:flex;flex-direction:column;gap:8px">${riskListHtml()}</div>
          <div id="student-explainability-panel" style="padding:20px;border-radius:12px;border:1px solid rgba(224,231,255,.8);background:rgba(238,242,255,.25);display:flex;flex-direction:column;justify-content:space-between">
            <div id="riskPanel">${riskPanelHtml()}</div>
          </div>
        </div>
      </div></div>`;
    }
    function wireRisk() {
      root.querySelectorAll('[data-rtab]').forEach(b => b.onclick = () => {
        S.riskFilter = b.dataset.rtab;
        redraw('section-risk-prediction', riskHtml, wireRisk);
      });
      root.querySelectorAll('[data-risk]').forEach(row => row.onclick = () => {
        S.selRisk = row.dataset.risk;
        root.querySelectorAll('[data-risk]').forEach(x => x.classList.toggle('sel', x === row));
        const panel = root.querySelector('#riskPanel');
        panel.style.opacity = '0'; panel.style.transform = 'scale(.98)'; panel.style.transition = 'all .18s';
        setTimeout(() => { panel.innerHTML = riskPanelHtml(); wireRiskAction(); panel.style.opacity = '1'; panel.style.transform = 'scale(1)'; }, 120);
      });
      wireRiskAction();
    }
    function wireRiskAction() {
      root.querySelectorAll('[data-riskaction]').forEach(b => b.onclick = () => {
        const r = D.risks.find(x => x.id === b.dataset.riskaction);
        toast(`Targeted academic action queued for ${r.name} — advising invite + resource pack sent`);
      });
    }

    /* ---------- Attendance vs Result scatter ---------- */
    function scatterHtml() {
      const badge = `<span class="o-monopill" style="font-size:12px">Pearson Correlation Score: <b style="color:#059669;font-size:14px;font-weight:800">${D.pearson}</b></span>`;
      return `
      <div class="o-sec" id="section-attendance-result"><div class="o-panel">
        ${phead('trending-up', '#eff6ff', '#2563eb', 'Attendance vs Result Analysis', 'Correlation review mapping students by their daily class attendance against result totals', badge)}
        <div style="display:grid;grid-template-columns:2fr 1fr;gap:24px" class="o-scgrid">
          <div style="background:rgba(244,244,245,.5);padding:16px;border-radius:12px;border:1px solid var(--ob)">${scatterSvg(D.scatter)}
            <div style="display:flex;gap:14px;font-size:10px;color:var(--omf);margin-top:6px;flex-wrap:wrap">
              <span><i class="o-sw" style="width:8px;height:8px;border-radius:999px;background:#3b82f6"></i>Target</span>
              <span><i class="o-sw" style="width:8px;height:8px;border-radius:999px;background:#f59e0b"></i>Warning</span>
              <span><i class="o-sw" style="width:8px;height:8px;border-radius:999px;background:#f43f5e"></i>At risk</span>
            </div>
          </div>
          <div id="correlation-summary-panel" style="padding:18px;border-radius:12px;border:1px solid #eff6ff;background:rgba(239,246,255,.1);display:flex;flex-direction:column;justify-content:space-between">
            <div>
              <span style="display:inline-flex;align-items:center;gap:6px;color:#2563eb;font:700 12px var(--font-mono);text-transform:uppercase;letter-spacing:.05em">${xi('info', 14)}AI Correlation Analysis</span>
              <h5 style="font-size:14px;font-weight:700;margin:6px 0 0">Strong positive correlation detected.</h5>
              <p style="font-size:12px;color:#52525b;margin:8px 0 0;line-height:1.65">An active linear relationship exists between lecturing attendance indices and final course attainment metrics (R = <strong>${D.pearson}</strong>).</p>
              <div style="display:flex;flex-direction:column;gap:10px;margin-top:16px">
                <div style="background:#fff;padding:10px;border-radius:8px;border:1px solid var(--ob);font-size:12px;display:flex;gap:8px;align-items:flex-start"><span style="color:#f43f5e;margin-top:1px">${xi('percent', 14)}</span><span>${D.scatterInsight1}</span></div>
                <div style="background:#fff;padding:10px;border-radius:8px;border:1px solid var(--ob);font-size:12px;display:flex;gap:8px;align-items:flex-start"><span style="color:#10b981;margin-top:1px">${xi('award', 14)}</span><span>Classroom outliers (high result, low attendance) are minimal, indicating that classroom lecture delivery is highly informative.</span></div>
              </div>
            </div>
            <div style="margin-top:16px;padding-top:12px;border-top:1px solid rgba(219,234,254,.5);font-size:10px;color:var(--omf);font-style:italic">Pearson R Score updated dynamically on weekly assessment synchronizations.</div>
          </div>
        </div>
      </div></div>`;
    }

    /* ---------- Question Analytics Engine ---------- */
    function qaPanelHtml() {
      const q = D.qa.find(x => x.id === S.selQ) || D.qa[D.qa.length - 1];
      return `
        <div style="display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--ob);padding-bottom:12px;gap:8px">
          <span style="display:inline-flex;align-items:center;gap:6px"><span style="color:#f59e0b">${xi('sparkles', 14)}</span><h5 style="font-size:14px;font-weight:700;margin:0">Question Diagnostic: ${q.id}</h5></span>
          <span class="o-diffpill ${q.diff}" style="border-radius:6px;text-transform:uppercase">${q.diff}</span>
        </div>
        <div style="margin-top:14px;font-size:12px;display:flex;flex-direction:column;gap:6px">
          <span><strong>Goal Target Mapping:</strong> ${q.co}</span>
          <span><strong>Total Attempts:</strong> ${q.att}%
            ${q.att === 0 ? `<span style="display:flex;align-items:center;gap:5px;color:#f43f5e;font:700 12px var(--font-mono);margin-top:4px">${xi('alert-triangle', 13)}Alarm Alert: Critical NULL attempts registered!</span>` : ''}</span>
          <span><strong>Attainment Average:</strong> ${q.suc}%</span>
        </div>
        <div style="margin-top:16px">
          <div style="font:800 10px var(--font-mono);text-transform:uppercase;letter-spacing:.05em;color:var(--omf);margin-bottom:8px">Causal Hypothesis:</div>
          <div style="display:flex;flex-direction:column;gap:6px">
            ${q.reasons.map(r => `<div style="background:#fff;padding:10px;border-radius:8px;border:1px solid #ececee;font-size:11px;line-height:1.6;display:flex;gap:8px"><span style="color:#f59e0b;flex:0 0 auto;margin-top:1px">${xi('info', 14)}</span><span>${esc(r)}</span></div>`).join('')}
          </div>
        </div>
        <div style="margin-top:16px;padding-top:12px;border-top:1px solid var(--ob)">
          <div style="font:600 10px var(--font-mono);text-transform:uppercase;letter-spacing:.12em;color:var(--omf)">Action Plan recommendation:</div>
          <div style="margin-top:6px;font-size:12px;color:#4338ca;font-weight:600;line-height:1.65">${esc(q.rec)}</div>
        </div>`;
    }
    function qaHtml() {
      const rows = D.qa.map(q => `
        <tr class="o-qrow ${q.id === S.selQ ? 'sel' : ''}" data-q="${q.id}">
          <td style="font:800 12px var(--font-mono)">${q.id}</td>
          <td><span style="background:#f4f4f5;padding:2px 8px;border-radius:4px;font:700 10px var(--font-mono)">${q.co}</span></td>
          <td style="font-family:var(--font-mono)">${q.att}%</td>
          <td style="font-family:var(--font-mono)">${q.suc}%</td>
          <td><span class="o-diffpill ${q.diff}">${q.diff}</span></td>
        </tr>`).join('');
      return `
      <div class="o-sec" id="section-question-analytics"><div class="o-panel">
        ${phead('file-question', '#eff6ff', '#2563eb', 'Question Analytics Engine', 'Granular exam-level evaluations mapping item responses against outcomes and syllabus weights', '')}
        <div style="display:grid;grid-template-columns:7fr 5fr;gap:20px" class="o-qagrid">
          <div style="overflow-x:auto;border:1px solid var(--ob);border-radius:8px;align-self:start">
            <table class="o-qtable"><thead><tr><th>Question ID</th><th>CO Mapping</th><th>Attempt Rate</th><th>Success Rate</th><th>Difficulty</th></tr></thead><tbody>${rows}</tbody></table>
          </div>
          <div id="question-diagnosis-card" style="padding:20px;border:1px solid #fef3c7;background:rgba(255,251,235,.25);border-radius:12px;display:flex;flex-direction:column">
            <div id="qaPanel">${qaPanelHtml()}</div>
          </div>
        </div>
      </div></div>`;
    }
    function wireQa() {
      root.querySelectorAll('[data-q]').forEach(tr => tr.onclick = () => {
        S.selQ = tr.dataset.q;
        root.querySelectorAll('[data-q]').forEach(x => x.classList.toggle('sel', x === tr));
        const panel = root.querySelector('#qaPanel');
        panel.style.transition = 'all .18s'; panel.style.opacity = '0'; panel.style.transform = 'translateX(-10px)';
        setTimeout(() => { panel.innerHTML = qaPanelHtml(); panel.style.transform = 'translateX(10px)'; requestAnimationFrame(() => { panel.style.opacity = '1'; panel.style.transform = 'translateX(0)'; }); }, 150);
      });
    }

    /* ---------- AI Teaching Effectiveness Score ---------- */
    function teachHtml() {
      const F = D.feedback;
      const C = 2 * Math.PI * 34;
      const off = (C - F.score / 100 * C).toFixed(3);
      const catTone = F.cat === 'Excellent' ? ['#10b981', '#ecfdf5', '#d1fae5'] : F.cat === 'Good' ? ['#3b82f6', '#eff6ff', '#dbeafe'] : F.cat === 'Average' ? ['#f59e0b', '#fffbeb', '#fef3c7'] : ['#f43f5e', '#fff1f2', '#ffe4e6'];
      return `
      <div class="o-sec" id="section-teaching-effectiveness"><div class="o-panel">
        ${phead('zap', '#fefce8', '#ca8a04', 'AI Teaching Effectiveness Score', 'Holistic performance index derived from student outcome achievements, assignment ratios, and active engagement metrics', '')}
        <div style="display:grid;grid-template-columns:1fr 2fr;gap:24px;align-items:center" class="o-tegrid">
          <div style="display:flex;flex-direction:column;align-items:center">
            <div id="effectiveness-radial-gauge" style="position:relative;width:144px;height:144px">
              <svg viewBox="0 0 100 100" width="144" height="144" style="transform:rotate(-90deg)">
                <circle cx="50" cy="50" r="34" fill="transparent" stroke="#e4e4e7" stroke-width="8"/>
                <circle cx="50" cy="50" r="34" fill="transparent" stroke="#8b5cf6" stroke-width="8" stroke-linecap="round"
                  stroke-dasharray="${C.toFixed(3)} ${C.toFixed(3)}" stroke-dashoffset="${C.toFixed(3)}" data-gauge="${off}" style="transition:stroke-dashoffset 1.2s ease-out"/>
              </svg>
              <div style="position:absolute;inset:0;display:grid;place-items:center;text-align:center">
                <div><div style="font:800 36px var(--font-display);letter-spacing:-.03em;color:#9333ea">${F.score}</div>
                <div style="font:700 10px var(--font-mono);text-transform:uppercase;letter-spacing:.12em;color:var(--omf)">Out of 100</div></div>
              </div>
            </div>
            <span style="margin-top:10px;padding:6px 12px;border-radius:999px;font-size:12px;font-weight:700;color:${catTone[0]};background:${catTone[1]};border:1px solid ${catTone[2]};box-shadow:0 1px 2px rgba(0,0,0,.05);display:inline-flex;align-items:center;gap:6px">${xi('award', 13)}Rating: ${F.cat}</span>
          </div>
          <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px" class="o-temetrics">
            <div style="padding:16px;border-radius:12px;border:1px solid rgba(228,228,231,.6);background:rgba(244,244,245,.5)">
              <div class="o-mlabel">Outcomes Average</div>
              <div style="font:800 24px var(--font-display);margin-top:4px">${F.rating} <span style="font:500 12px var(--font-ui);color:var(--omf)">/ 10</span></div>
              <div style="font-size:11px;color:var(--omf);margin-top:8px">Aggregate CO-PO achievement scores from student finals.</div>
            </div>
            <div style="padding:16px;border-radius:12px;border:1px solid rgba(228,228,231,.6);background:rgba(244,244,245,.5)">
              <div class="o-mlabel">Activity rate</div>
              <div style="font:800 24px var(--font-display);margin-top:4px;color:#4f46e5">${F.engagement}%</div>
              <div style="font-size:11px;color:var(--omf);margin-top:8px">Continuous lecture room interactions and active participation logs.</div>
            </div>
            <div style="padding:16px;border-radius:12px;border:1px solid rgba(228,228,231,.6);background:rgba(244,244,245,.5)">
              <div class="o-mlabel">Completion</div>
              <div style="font:800 24px var(--font-display);margin-top:4px;color:#9333ea">${F.completion}%</div>
              <div style="font-size:11px;color:var(--omf);margin-top:8px">Overall homework submission compliance across all sections.</div>
            </div>
            <div style="grid-column:1/-1;padding:16px;border-radius:12px;border:1px solid #eef2ff;background:rgba(238,242,255,.1);display:flex;gap:12px">
              <span style="padding:8px;background:rgba(224,231,255,.6);border-radius:8px;color:#4f46e5;align-self:flex-start">${xi('heart-handshake', 16)}</span>
              <span><span style="display:block;font-size:12px;font-weight:700;color:#312e81;text-transform:uppercase;letter-spacing:.05em">Curriculum Attendance Impact AI Observation:</span>
              <span style="display:block;font-size:12px;color:#52525b;margin-top:4px">${esc(F.impact)} Regular lectures contribute fundamentally to practical outcome conversions.</span></span>
            </div>
          </div>
        </div>
      </div></div>`;
    }
    function wireTeach() {
      requestAnimationFrame(() => requestAnimationFrame(() => {
        root.querySelectorAll('[data-gauge]').forEach(c => c.style.strokeDashoffset = c.dataset.gauge);
      }));
    }

    /* ---------- University Course Health Engine ---------- */
    const healthScore = () => {
      const w = S.weights, m = D.metrics;
      const sum = w.attendance + w.assignment + w.exam + w.coAchievement + w.engagement;
      if (!sum) return 0;
      return (m.attendance * w.attendance + m.assignment * w.assignment + m.exam * w.exam + m.coAchievement * w.coAchievement + m.engagement * w.engagement) / sum;
    };
    function healthPresenterHtml() {
      const h = healthScore();
      const sum = Object.values(S.weights).reduce((a, b) => a + b, 0);
      return `
        <div style="font:800 10px var(--font-mono);text-transform:uppercase;letter-spacing:.12em;color:var(--omf)">Calculated Health Score</div>
        <div style="font:800 36px var(--font-display);letter-spacing:-.03em;color:#4f46e5;margin-top:8px">${Math.round(h)}%</div>
        <div style="margin-top:12px">${h >= 80
          ? `<span style="font-size:12px;font-weight:700;padding:2px 12px;border-radius:999px;background:#ecfdf5;color:#047857;display:inline-flex;align-items:center;gap:5px">${xi('sparkles', 12)}Premium Health Status</span>`
          : `<span style="font-size:12px;font-weight:700;padding:2px 12px;border-radius:999px;background:#fff7ed;color:#c2410c;display:inline-flex;align-items:center;gap:5px">${xi('alert-triangle', 12)}Needs Outcome Review</span>`}</div>
        <div style="border-top:1px solid var(--ob);margin-top:14px;padding-top:10px">
          <div style="font:600 10px var(--font-mono);text-transform:uppercase;color:var(--omf)">Health Index Weights Check:</div>
          <div style="margin-top:4px">${sum === 100
            ? `<span style="font-size:12px;font-weight:700;color:#059669;display:inline-flex;align-items:center;gap:5px">${xi('check-circle-2', 13)}Complete (100%)</span>`
            : `<span class="o-pulse" style="font-size:12px;font-weight:700;color:#d97706;display:inline-flex;align-items:center;gap:5px">${xi('alert-circle', 13)}Active (${sum}% / 100%)</span>`}</div>
        </div>`;
    }
    function healthHtml() {
      const rows = SLIDER_ROWS.map(([key, label, dot]) => `
        <div>
          <div style="display:flex;justify-content:space-between;align-items:center;font-size:12px;margin-bottom:5px">
            <span style="display:inline-flex;align-items:center;gap:7px;font-weight:600"><i style="width:8px;height:8px;border-radius:999px;background:${dot}"></i>${label}</span>
            <span style="font-family:var(--font-mono)"><span style="color:var(--omf)">(Score: ${D.metrics[key]}%)</span>
            <b data-wval="${key}" style="font-weight:800;background:#f4f4f5;padding:2px 8px;border-radius:2px;margin-left:6px">${S.weights[key]}%</b></span>
          </div>
          <input type="range" class="o-slider" min="0" max="50" step="5" value="${S.weights[key]}" data-weight="${key}">
        </div>`).join('');
      return `
      <div class="o-sec" id="section-health-engine"><div class="o-panel">
        <div style="border-bottom:1px solid var(--ob);padding-bottom:16px;margin-bottom:20px">
        ${phead('sliders', '#eef2ff', '#4f46e5', 'University Course Health Engine', 'Tweak core auditing assessment weights to evaluate overall semester status',
          `<button class="o-abtn sec" id="btn-baseline-reset" style="font-size:12px;padding:6px 12px">${xi('refresh-cw', 13)}Return Baseline Weights</button>`)}
        </div>
        <div style="display:grid;grid-template-columns:1fr 2fr;gap:32px;align-items:center" class="o-hegrid">
          <div id="health-calculation-presenter" style="padding:20px;border-radius:16px;background:rgba(244,244,245,.5);border:1px solid #ececee;min-height:220px;text-align:center;display:flex;flex-direction:column;justify-content:center">${healthPresenterHtml()}</div>
          <div style="display:flex;flex-direction:column;gap:16px">
            ${rows}
            <div style="padding:12px;background:rgba(238,242,255,.5);border:1px solid rgba(224,231,255,.5);font-size:11px;color:#4338ca;border-radius:8px"><b>AI Summary:</b> Course is performing well overall, but CO3 and CO4 require structured teaching interventions under typical accreditation scenarios.</div>
          </div>
        </div>
      </div></div>`;
    }
    function syncHealthCard() {
      const card = root.querySelector('#card-course-health'); if (!card) return;
      const h = S.cardHealth;
      const theme = CARD_THEMES[h >= 80 ? 'green' : 'orange'];
      card.querySelector('[data-cardval]').textContent = Math.round(h) + '%';
      const pill = card.querySelector('[data-cardpill]');
      pill.className = 'o-tpill ' + (h >= 80 ? 'up' : 'dn');
      pill.innerHTML = (h >= 80 ? '↑ ' : '↓ ') + (h >= 80 ? '+6.3% premium health' : 'Attention needed on CO3');
      const tile = card.querySelector('[data-cardic]');
      tile.style.background = theme.bg; tile.style.borderColor = theme.bd; tile.style.color = theme.txt;
    }
    function wireHealth() {
      root.querySelectorAll('[data-weight]').forEach(sl => sl.oninput = () => {
        S.weights[sl.dataset.weight] = +sl.value;
        root.querySelector(`[data-wval="${sl.dataset.weight}"]`).textContent = sl.value + '%';
        root.querySelector('#health-calculation-presenter').innerHTML = healthPresenterHtml();
        S.cardHealth = healthScore();
        syncHealthCard();
      });
      const reset = root.querySelector('#btn-baseline-reset');
      if (reset) reset.onclick = () => {
        S.weights = { ...DEFAULT_WEIGHTS };
        S.cardHealth = D.live ? D.initialHealth : healthScore();
        redraw('section-health-engine', healthHtml, wireHealth);
        syncHealthCard();
        toast('Baseline weights restored');
      };
    }

    /* ---------- AI Accreditation Assistant ---------- */
    function accDocHtml() {
      const sh = 'font:800 12px var(--font-mono);text-transform:uppercase;letter-spacing:.12em;border-bottom:1px solid var(--ob);padding-bottom:4px;margin:0 0 10px';
      return `
      <div id="printable-accreditation-docket" style="line-height:1.65;font-size:12px">
        <div style="text-align:center;padding-bottom:20px;border-bottom:2px solid rgba(24,24,27,.1);margin-bottom:20px">
          <h2 style="font-size:20px;font-weight:900;text-transform:uppercase;letter-spacing:.05em;margin:0">Outcome Based Education Attainment Docket</h2>
          <h4 style="font:600 12px var(--font-mono);color:var(--omf);text-transform:uppercase;margin:4px 0 0">Official AQIP/ABET Academic Report Portfolio</h4>
          <div style="display:flex;justify-content:center;gap:20px;font:400 10px var(--font-mono);color:#a1a1aa;margin-top:14px;flex-wrap:wrap">
            <span>COURSE: ${D.code}</span><span>SEMESTER: SUMMER 2026</span><span>DEPT: COMPUTER SCIENCE</span>
          </div>
        </div>
        <div style="display:flex;flex-direction:column;gap:22px">
          <div><h5 style="${sh}">1. Course Outcomes (CO) Attainment</h5>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(110px,1fr));gap:12px;text-align:center">
              ${D.accSection1.map(c => `<div style="padding:10px;border-radius:8px;border:1px solid var(--ob);background:rgba(244,244,245,.5)">
                <div style="font:700 10px var(--font-mono);color:#a1a1aa">${esc(c[0])}</div>
                <div style="font-size:16px;font-weight:800;margin-top:4px">${c[1]}</div>
                <span style="font-size:9px;font-weight:700;padding:2px 6px;border-radius:4px;background:${c[2] === 'Critically Failed' ? '#ffe4e6' : '#d1fae5'};color:${c[2] === 'Critically Failed' ? '#be123c' : '#047857'}">${c[2]}</span>
              </div>`).join('')}
            </div></div>
          <div><h5 style="${sh}">2. Program Outcomes (PO) Attainment Weights</h5>
            <p style="color:var(--omf);margin:0">${D.accS2}</p></div>
          <div><h5 style="${sh}">3. Weak Outcome Diagnostic Correlation</h5>
            <p style="color:var(--omf);margin:0">${esc(D.accS3)}</p></div>
          <div><h5 style="${sh}">4. Continuous Quality Improvement (CQI) Plan</h5>
            <ol style="padding-left:16px;margin:0;display:flex;flex-direction:column;gap:8px">
              ${D.accCqi.map(x => `<li><strong>${esc(x[0])}</strong>${esc(x[1])}</li>`).join('')}
            </ol></div>
          <div><h5 style="${sh}">5. Faculty Committee Recommendation</h5>
            <blockquote style="font-style:italic;border-left:4px solid #10b981;padding-left:14px;color:var(--omf);margin:0">${esc(D.accQuote)}</blockquote></div>
        </div>
        <div style="margin-top:44px;padding-top:28px;border-top:1px solid var(--ob);display:flex;justify-content:space-between;font:400 10px var(--font-mono);color:var(--omf);gap:16px;flex-wrap:wrap">
          <span><span style="display:inline-block;border-bottom:1px solid var(--omf);width:144px;text-transform:uppercase;letter-spacing:.05em;padding-bottom:2px">Committee Chairman Signature</span><br><span style="font-size:9px">Date: May 30, 2026</span></span>
          <span style="text-align:right"><span style="display:inline-block;border-bottom:1px solid var(--omf);width:144px;text-transform:uppercase;letter-spacing:.05em;padding-bottom:2px">Faculty Head Signature</span><br><span style="font-size:9px">University Center OBE Portal</span></span>
        </div>
      </div>`;
    }
    function openAccModal() {
      const o = openModal(
        `<span style="display:inline-flex;align-items:center;gap:8px"><span style="color:#10b981">${xi('shield-check', 16)}</span><span style="font-family:var(--font-mono);font-size:12px;color:var(--omf)">Official Accreditation Document Preview</span></span>`,
        accDocHtml(),
        `<button class="o-abtn em" id="accPrint" style="font-size:11px;padding:6px 12px">${xi('printer', 12)}Print or Save as PDF</button>`);
      o.querySelector('.modal').id = 'accreditation-modal-view';
      o.querySelector('#accPrint').onclick = () => window.print();
    }
    function accHtml() {
      return `
      <div class="o-sec" id="section-accreditation"><div class="o-panel">
        ${phead('award', '#ecfdf5', '#059669', 'AI Accreditation Assistant', 'Instantly package academic records into formal accreditation formats', '')}
        <div style="padding:16px;border-radius:12px;border:1px dashed var(--ob);background:rgba(244,244,245,.5);display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap;position:relative;overflow:hidden">
          <span style="position:absolute;top:-14px;right:6px;color:rgba(228,228,231,.4);pointer-events:none">${xi('award', 120)}</span>
          <div style="position:relative;z-index:1;min-width:230px;flex:1">
            <div style="display:inline-flex;align-items:center;gap:6px;font:800 12px var(--font-mono);color:#059669;text-transform:uppercase;letter-spacing:.05em"><span class="o-pulse">${xi('sparkles', 13)}</span>Self-Assessment Portfolio Engine</div>
            <h5 style="font-size:16px;font-weight:700;margin:4px 0 0">ABET &amp; NBA Attainment Package Ready</h5>
            <p style="font-size:12px;color:var(--omf);max-width:576px;margin:4px 0 0">Our diagnostic pipeline has validated overall data completeness. Continuous Quality Improvement (CQI) documentation is fully drafted.</p>
          </div>
          <div id="accAction" style="position:relative;z-index:1;min-width:230px">
            <button class="o-abtn em" id="btn-trigger-accreditation" style="padding:10px 20px">${xi('file-down', 14)}Calculate &amp; Compile Accreditation Report</button>
          </div>
        </div>
      </div></div>`;
    }
    function wireAcc() {
      const btn = root.querySelector('#btn-trigger-accreditation');
      if (!btn) return;
      btn.onclick = () => {
        const box = root.querySelector('#accAction');
        let step = 0;
        const drawStep = () => {
          box.innerHTML = `
            <div style="width:288px;max-width:100%">
              <div style="background:#f4f4f5;height:8px;border-radius:999px;overflow:hidden"><i style="display:block;height:100%;background:#10b981;border-radius:999px;transition:width .4s;width:${(step + 1) / ACC_STEPS.length * 100}%"></i></div>
              <div class="o-pulse" style="font:400 10px var(--font-mono);font-style:italic;color:#a1a1aa;text-align:center;margin-top:8px">${esc(ACC_STEPS[step])}</div>
            </div>`;
        };
        drawStep();
        clearInterval(accTimer);
        accTimer = setInterval(() => {
          step++;
          if (step >= ACC_STEPS.length) {
            clearInterval(accTimer);
            setTimeout(() => { redraw('section-accreditation', accHtml, wireAcc); openAccModal(); }, 450);
            return;
          }
          drawStep();
        }, 600);
      };
    }

    /* ---------- Voice-Controlled OBE Assistant ---------- */
    function voiceLogHtml() {
      return `
        <div style="display:flex;align-items:center;gap:6px;font:700 11px var(--font-mono);text-transform:uppercase;letter-spacing:.05em;color:var(--omf)">${xi('sparkles', 11)}Transcription Logs</div>
        <div style="margin-top:10px;font-size:12px"><span style="color:var(--omf)">Oral Input: </span>
          ${voice.transcript ? `<b style="font-style:italic;font-weight:800">${esc(voice.transcript)}</b>` : `<span style="color:var(--omf)">None recorded yet. Select suggestions to start.</span>`}</div>
        ${voice.processing || voice.response ? `
        <div style="border-top:1px solid var(--ob);margin-top:12px;padding-top:12px;display:flex;gap:8px">
          <span style="color:#6366f1;flex:0 0 auto;margin-top:2px">${xi('volume-2', 16)}</span>
          <span style="min-width:0">
            <span style="display:block;font:700 10px var(--font-mono);text-transform:uppercase;color:#312e81">OBE Assistant Speaking:</span>
            ${voice.processing
              ? `<span class="o-pulse" style="display:inline-flex;align-items:center;gap:6px;font-size:11px;font-style:italic;color:#a1a1aa;margin-top:4px">${xi('refresh-cw', 11, 'o-spinfast')}Digested command, synthesizing response...</span>`
              : `<span style="display:block;font-size:12px;color:#52525b;line-height:1.65;margin-top:4px">${esc(voice.response)}</span>`}
          </span>
        </div>` : ''}`;
    }
    function voiceMicHtml() {
      return `
        ${voice.listening ? '<span class="o-ripple r1"></span><span class="o-ripple r2"></span>' : ''}
        <button class="o-mic ${voice.listening ? 'live' : 'idle'}" id="btn-voice-mic" aria-label="Microphone">${xi(voice.listening ? 'mic-off' : 'mic', 32)}</button>
        <div style="margin-top:16px;font:700 10px var(--font-mono);text-transform:uppercase;letter-spacing:.12em;color:#a1a1aa">${voice.listening ? 'Active listening...' : 'Tap to Speak'}</div>`;
    }
    function voiceHtml() {
      return `
      <div class="o-sec" id="section-voice-assistant"><div class="o-panel">
        ${phead('mic', '#fff1f2', '#e11d48', 'Voice-Controlled OBE Assistant', 'Speak or trigger simulated voice queries to quickly filter curriculum variables', '')}
        <div style="display:grid;grid-template-columns:1fr 2fr;gap:24px;align-items:center" class="o-vagrid">
          <div id="mic-control-center" style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:16px 0;background:rgba(244,244,245,.5);border-radius:16px;border:1px solid #ececee;min-height:200px;position:relative;overflow:hidden">${voiceMicHtml()}</div>
          <div style="min-height:200px;display:flex;flex-direction:column;justify-content:space-between">
            <div>
              <div style="font:800 10px var(--font-mono);text-transform:uppercase;letter-spacing:.12em;color:var(--omf);margin-bottom:8px">Suggested teacher directives (Click to simulate speech):</div>
              <div style="display:flex;flex-wrap:wrap;gap:8px">
                ${VOICE_PRESETS.map((p, i) => `<button class="o-vchip" id="btn-voice-preset-${i}" data-vp="${i}">&quot; ${p} &quot;</button>`).join('')}
              </div>
            </div>
            <div id="voice-transcription-log" style="margin-top:20px;padding:16px;border-radius:12px;border:1px solid var(--ob);background:var(--omut);font-size:12px">${voiceLogHtml()}</div>
          </div>
        </div>
      </div></div>`;
    }
    function simulateSpeech(cmd) {
      voice.listening = true; voice.transcript = 'Listening...'; voice.response = ''; voice.processing = false;
      const mic = root.querySelector('#mic-control-center'); const log = root.querySelector('#voice-transcription-log');
      mic.innerHTML = voiceMicHtml(); wireMic(); log.innerHTML = voiceLogHtml();
      setTimeout(() => {
        voice.listening = false; voice.transcript = `"${cmd}"`; voice.processing = true;
        mic.innerHTML = voiceMicHtml(); wireMic(); log.innerHTML = voiceLogHtml();
        setTimeout(() => {
          voice.processing = false;
          voice.response = voiceReply(cmd);
          log.innerHTML = voiceLogHtml();
        }, 1100);
      }, 700);
    }
    function wireMic() {
      const m = root.querySelector('#btn-voice-mic');
      if (m) m.onclick = () => { if (!voice.listening) simulateSpeech('Show weak outcomes'); };
    }
    function wireVoice() {
      wireMic();
      root.querySelectorAll('[data-vp]').forEach(b => b.onclick = () => simulateSpeech(VOICE_PRESETS[+b.dataset.vp]));
    }

    /* ---------- AI Insights Sidebar (feed + copilot chat) ---------- */
    function feedHtml() {
      const items = [
        { bg: 'rgba(255,241,242,.5)', bd: 'rgba(255,228,230,.5)', ic: xi('alert-triangle', 16, 'o-pulse'), icc: '#f43f5e', h: 'Immediate Danger Warnings', hc: '#881337', body: 'Attendance drop in <strong>Section B (63%)</strong> is causing an attainment collapse. High student density at-risk zone identified.' },
        { bg: 'rgba(250,245,255,.5)', bd: 'rgba(243,232,255,.5)', ic: xi('bot', 16), icc: '#a855f7', h: 'Statistical Future Predictions', hc: '#581c87', body: '<strong>CO3 achievement</strong> is expected to <strong>decline by 7%</strong> next semester if current Section B attendance patterns continue.' },
        { bg: 'rgba(236,253,245,.5)', bd: 'rgba(209,250,229,.5)', ic: xi('compass', 16), icc: '#10b981', h: 'Curriculum Remediations', hc: '#064e3b', body: 'Flipping the Week 9 presentations into classroom peer-led review blocks is predicted to lift CO3 levels by <strong>14.2%</strong>.' },
      ];
      return `
      <div style="border-radius:12px;border:1px solid var(--ob);background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.05);overflow:hidden">
        <button id="feedToggle" style="width:100%;padding:16px;display:flex;justify-content:space-between;align-items:center;font:600 14px var(--font-display);border:0;background:rgba(244,244,245,.4);border-bottom:1px solid var(--ob);cursor:pointer">
          <span style="display:inline-flex;align-items:center;gap:8px"><span style="color:#a855f7">${xi('sparkles', 16)}</span>AI Predictive Intelligence Feed</span>
          ${xi(feedOpen ? 'chevron-up' : 'chevron-down', 16)}
        </button>
        <div id="predictions-collapsible-feed" style="padding:16px;display:${feedOpen ? 'flex' : 'none'};flex-direction:column;gap:14px;font-size:12px">
          ${items.map(it => `<div class="o-feedcard" style="background:${it.bg};border:1px solid ${it.bd}">
            <span style="color:${it.icc};flex:0 0 auto;margin-top:2px">${it.ic}</span>
            <span><h6 class="o-feedh" style="color:${it.hc}">${it.h}</h6><p style="color:#52525b;margin:4px 0 0">${it.body}</p></span>
          </div>`).join('')}
        </div>
      </div>`;
    }
    function msgHtml(m) {
      return `<div class="o-msg ${m.me ? 'me' : ''}">
        ${m.me ? '' : `<span class="o-av">${xi('bot', 15)}</span>`}
        <span style="min-width:0"><span class="o-bub" style="display:block">${md(m.text)}<div class="o-ts">${m.ts}</div></span></span>
      </div>`;
    }
    function chatMsgsHtml() {
      return chat.map(msgHtml).join('') + (chatTyping ? `
        <div class="o-msg"><span class="o-av">${xi('bot', 15)}</span>
        <span class="o-bub" style="display:inline-flex;align-items:center;gap:8px"><span style="color:#6366f1">${xi('refresh-cw', 12, 'o-spinfast')}</span><em style="color:#71717a;font-size:12px">Synthesizing evaluation files...</em></span></div>` : '');
    }
    function chatHtml() {
      return `
      <div class="o-chatwrap" id="chatbot-mainframe">
        <div style="padding:16px;border-bottom:1px solid var(--ob);background:rgba(244,244,245,.5);display:flex;justify-content:space-between;align-items:center">
          <span style="display:flex;gap:10px;align-items:center"><span style="color:#4f46e5">${xi('bot', 18)}</span>
            <span><b style="font-size:14px;font-family:var(--font-display);display:block;line-height:1.2">OBE Intelligence Center</b>
            <span style="font:400 9px var(--font-mono);color:var(--omf)">Summer 2026 Academic Evaluator</span></span></span>
          <span style="display:inline-flex;align-items:center;gap:6px;font:700 10px var(--font-mono);text-transform:uppercase;color:#a1a1aa"><span class="o-dot" style="width:8px;height:8px"></span>Copilot Live</span>
        </div>
        <div class="o-msgs" id="chatMsgs">${chatMsgsHtml()}</div>
        ${chat.length <= 1 ? `
        <div style="padding:12px 16px;border-top:1px solid var(--ob);background:rgba(244,244,245,.2)" id="chatSuggs">
          <div style="font:700 10px var(--font-mono);text-transform:uppercase;letter-spacing:.12em;color:var(--omf);margin-bottom:8px">Suggested Inquiries:</div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
            <button class="o-sugg" data-sugg="Explain why CO3 (Communication) is falling behind.">${xi('search', 13)}Diagnose CO3 collapse</button>
            <button class="o-sugg" data-sugg="Draft a Continuous Quality Improvement (CQI) plan for accreditation.">${xi('file-text', 13)}Draft Accreditation CQI</button>
          </div>
        </div>` : ''}
        <div class="o-chatin">
          <input id="chatInput" type="text" placeholder="Conversate with OBE Intelligence...">
          <button id="btn-chatbot-send" aria-label="Send">${xi('send', 15)}</button>
        </div>
      </div>`;
    }
    function sidebarHtml() {
      return `<div class="o-sec" id="section-insights-copilot" style="align-self:stretch"><div style="display:flex;flex-direction:column;gap:20px">${feedHtml()}${chatHtml()}</div></div>`;
    }
    function refreshChat() {
      redraw('section-insights-copilot', sidebarHtml, wireSidebar);
      const box = root.querySelector('#chatMsgs');
      if (box) box.scrollTop = box.scrollHeight;
    }
    function sendChat(text) {
      const q = String(text || '').trim(); if (!q || chatTyping) return;
      chat.push({ me: true, text: q, ts: nowTs() });
      chatTyping = true;
      refreshChat();
      setTimeout(() => {
        let reply;
        try { reply = chatReply(q); }
        catch (e) { reply = '**Connection error**: Falling back to smart diagnostic heuristics. Please review standard accreditation plans or trigger simulated voice directives.'; }
        chatTyping = false;
        chat.push({ me: false, text: reply, ts: nowTs() });
        refreshChat();
      }, 1200);
    }
    function wireSidebar() {
      const tog = root.querySelector('#feedToggle');
      if (tog) tog.onclick = () => { feedOpen = !feedOpen; refreshChat(); };
      root.querySelectorAll('[data-sugg]').forEach(b => b.onclick = () => sendChat(b.dataset.sugg));
      const inp = root.querySelector('#chatInput');
      const send = root.querySelector('#btn-chatbot-send');
      if (send) send.onclick = () => { sendChat(inp.value); };
      if (inp) inp.addEventListener('keydown', e => { if (e.key === 'Enter') sendChat(inp.value); });
      const box = root.querySelector('#chatMsgs');
      if (box) box.scrollTop = box.scrollHeight;
    }

    /* ---------- footer status bar ---------- */
    function footHtml() {
      return `
      <div class="o-foot">
        <span style="display:flex;gap:16px;flex-wrap:wrap;align-items:center">
          <span>Course: <b style="color:var(--ofg)">${D.code} ${esc(D.title)}</b></span>
          <span>Semester: <b style="color:var(--ofg)">Summer 2026</b></span>
          <span style="color:#10b981">● Live Engine: Online</span>
        </span>
        <span style="display:flex;gap:16px;align-items:center;flex-wrap:wrap">
          <span style="background:rgba(244,244,245,.6);border:1px solid var(--ob);padding:2px 8px;border-radius:4px">Last AI Sync: 2 mins ago</span>
          <span style="font-weight:700;text-transform:uppercase;letter-spacing:.14em">© EDU-OBE-SYSTEM v3.5</span>
        </span>
      </div>`;
    }

    /* ---------- CSV export ---------- */
    function downloadCsv() {
      const rows = [['CO', 'PO', 'Intensity']];
      D.cos.forEach(co => POS.forEach(po => rows.push([co.id, po, S.matrix[co.id + '|' + po]])));
      const blob = new Blob([rows.map(r => r.join(',')).join('\n')], { type: 'text/csv' });
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = 'CO_PO_Mapping_Matrix_Report.csv';
      document.body.appendChild(a); a.click(); a.remove();
      setTimeout(() => URL.revokeObjectURL(a.href), 2000);
      obeToast('CO-PO alignment matrix downloaded as CSV successfully!');
    }

    /* ---------- partial redraw helper ---------- */
    function redraw(id, htmlFn, wireFn) {
      const el = root.querySelector('#' + id);
      if (!el) return;
      const tmp = document.createElement('div');
      tmp.innerHTML = htmlFn();
      el.replaceWith(tmp.firstElementChild);
      if (wireFn) wireFn();
    }

    /* ---------- full render ---------- */
    function renderAll() {
      root.innerHTML = `
        <style>${CSS}</style>
        ${headHtml()}
        ${heroHtml()}
        ${cardsHtml()}
        <div class="o-main">
          <div class="o-col">
            ${coAchHtml()}
            ${matrixHtml()}
            ${heatHtml()}
            ${weakHtml()}
            ${riskHtml()}
            ${scatterHtml()}
            ${qaHtml()}
            ${teachHtml()}
            ${healthHtml()}
            ${accHtml()}
            ${voiceHtml()}
          </div>
          ${sidebarHtml()}
        </div>
        ${footHtml()}`;
      wireAll();
    }
    function wireAll() {
      /* hero actions */
      root.querySelector('#obeCourse').onchange = (e) => {
        course = e.target.value;
        D = buildData(course);
        S = uiFor(course);
        voice = { listening: false, processing: false, transcript: '', response: '' };
        renderAll();
        toast(`Switched to ${course} — ${D.title}`);
      };
      root.querySelector('#btn-quick-reports').onclick = () => scrollFocus('section-accreditation', 'emerald');
      root.querySelector('#btn-quick-pdf').onclick = () => window.print();
      root.querySelector('#btn-ai-insights').onclick = () => scrollFocus('section-insights-copilot');
      root.querySelector('#btn-voice-assist').onclick = () => scrollFocus('section-voice-assistant', 'rose');
      root.querySelector('#btn-download-copo').onclick = downloadCsv;
      wireCoAch(); wireMatrix(); wireRisk(); wireQa(); wireTeach(); wireHealth(); wireAcc(); wireVoice(); wireSidebar();
      syncHealthCard();
    }

    renderAll();
  };
})();








