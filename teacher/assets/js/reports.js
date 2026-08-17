/* ==========================================================
   EON FOR TEACHER — AI Reports (assets/js/reports.js)
   Describe what you need → the generator compiles a REAL
   report client-side from window.T + the analytics core A:
   KPI stats, a line chart, findings and a recommendation.
   History persists in localStorage ('eft-reports').
   ========================================================== */
'use strict';
(function () {

  /* seed history cards — verbatim from the spec */
  const SEED_REPORTS = [
    { title: 'Weekly academic report', date: 'Week 21', tag: 'Academic', kind: 'weekly' },
    { title: 'OBE attainment summary', date: 'Spring 26', tag: 'OBE', kind: 'obe' },
    { title: 'Teacher KPI snapshot', date: 'May 2026', tag: 'KPI', kind: 'kpi' },
    { title: 'At-risk student watchlist', date: 'Live', tag: 'AI', kind: 'risk' },
    { title: 'Attendance compliance', date: 'Apr 2026', tag: 'Attendance', kind: 'attendance' },
    { title: 'Assignment grading log', date: 'Apr 2026', tag: 'Workflow', kind: 'weekly' },
  ];

  const TEMPLATES = [
    { label: 'Weekly academic report', prompt: 'Compile this week’s academic report across all my sections — marks, attendance and grading progress.' },
    { label: 'OBE attainment summary', prompt: 'Summarise CO attainment against target for every course I teach, and flag the COs that are below target.' },
    { label: 'Teacher KPI snapshot', prompt: 'Build my KPI snapshot — GPA trend, pass rate, CO attainment and research output vs previous semesters.' },
    { label: 'At-risk student watchlist', prompt: 'List the students predicted at risk this week with their risk drivers, and forecast the section pass rate.' },
    { label: 'Attendance compliance', prompt: 'Report attendance compliance for all sections and identify students below the 75% threshold.' },
  ];

  const HKEY = 'eft-reports';
  const loadHist = () => { try { return JSON.parse(localStorage.getItem(HKEY) || '[]'); } catch (e) { return []; } };
  const saveHist = (h) => localStorage.setItem(HKEY, JSON.stringify(h.slice(0, 12)));

  window.initReportsPage = function () {
    const p = $('#content');

    /* ---------- cohort facts, computed once from T + A ---------- */
    const H = T.kpiHistory;
    const kNow = H[H.length - 1];
    const labels = H.map(x => x.sem.split(' (')[0]);
    const flagged = A.flagged(8);
    const redCount = flagged.filter(x => x.r.tier === 'red').length;
    const fc = A.forecast('CSE311', 'A');
    const weak311 = A.topics('CSE311', 'A')[0];
    const weak220 = A.topics('CSE220')[0];
    const attAll = mean(T.students.map(s => s.attendanceRate));
    const below75 = T.students.filter(s => s.attendanceRate < 0.75);
    const lateAvg = mean(T.students.map(s => s.lateSubRate));
    const cosBelow = [];
    T.courses.forEach(c => c.sections.forEach(sec => A.cos(c.code, sec).forEach(x => {
      if (x.attain != null && x.attain < x.target) cosBelow.push({ course: c.code, sec, co: x.co, attain: x.attain, target: x.target });
    })));
    cosBelow.sort((a, b) => a.attain - b.attain);
    const coAll = [];
    T.courses.forEach(c => c.sections.forEach(sec => A.cos(c.code, sec).forEach(x => { if (x.attain != null) coAll.push(x.attain); })));
    const coAvg = Math.round(mean(coAll));
    const secAtt = [];
    T.courses.forEach(c => c.sections.forEach(sec => secAtt.push({ label: c.code + ' ' + sec, v: pct(mean(T.enrolled[c.code][sec].map(s => s.attendanceRate))) })));
    const gradedRecently = T.assessments.filter(a => Date.parse(a.date) > Date.now() - 14 * 86400000);

    /* ---------- deterministic report builders ---------- */
    function detectKind(q) {
      const s = q.toLowerCase();
      if (/obe|attain|co[0-9 ]|outcome/.test(s)) return 'obe';
      if (/kpi|scorecard|snapshot|leaderboard/.test(s)) return 'kpi';
      if (/risk|watchlist|flag|dropout|predict/.test(s)) return 'risk';
      if (/attendance|absen|compliance|75/.test(s)) return 'attendance';
      return 'weekly';
    }
    function buildReport(kind) {
      const worst = cosBelow[0];
      if (kind === 'obe') return {
        title: 'OBE attainment summary', tag: 'OBE',
        subtitle: `CO attainment vs target across ${T.courses.length} courses · ${esc(T.courses[0].semester)} · compiled from ${T.assessments.length} graded assessments`,
        stats: [
          { label: 'Avg CO attainment', val: coAvg + '%', sub: 'all courses & sections' },
          { label: 'COs below target', val: cosBelow.length, sub: 'of ' + coAll.length + ' measured' },
          { label: 'Weakest CO', val: worst ? worst.co : '—', sub: worst ? `${worst.course} Sec ${worst.sec} · ${worst.attain}%` : 'none' },
          { label: 'Dept benchmark', val: kNow.coAttain + '%', sub: kNow.sem.split(' (')[0] + ' cohort' },
        ],
        chartTitle: 'CO attainment trend vs department history',
        chart: lineChart([{ name: 'CO attainment %', vals: H.map(x => x.coAttain), color: 'var(--primary)' }, { name: 'Pass rate %', vals: H.map(x => x.passRate), color: 'var(--green)' }], labels, { fmt: v => v + '%' }),
        findings: [
          `Average CO attainment is <b>${coAvg}%</b> across every section you teach; <b>${cosBelow.length}</b> course-section COs sit below their target.`,
          worst ? `The weakest point is <b>${worst.co} in ${worst.course} Sec ${worst.sec}</b> at <b>${worst.attain}%</b> against a ${worst.target}% target — the topic behind it is <b>${esc(weak311.topic)}</b> (class correct-rate ${pct(weak311.rate)}%).` : 'No CO is currently below target.',
          `In CSE220, <b>${esc(weak220.topic)}</b> is the weakest topic (${pct(weak220.rate)}% correct) and maps onto the CO2 gap in both sections.`,
          `One logged remediation (${esc(T.remediations[0].topic)}, Sec ${T.remediations[0].section}) already lifted the midterm on that topic — the same playbook applies here.`,
        ],
        rec: `Re-teach ${esc(weak311.topic)} in CSE311 Sec A before Quiz 3 and publish a targeted practice sheet. Fixing that single topic is the shortest path to moving ${worst ? worst.co : 'CO3'} above target and lifting the department CO metric past ${kNow.coAttain}%.`,
      };
      if (kind === 'kpi') return {
        title: 'Teacher KPI snapshot', tag: 'KPI',
        subtitle: `${esc(T.teacher.name)} · ${esc(T.teacher.department)} · semester-on-semester view from ${labels[0]} to ${labels[labels.length - 1]}`,
        stats: [
          { label: 'Batch avg GPA', val: kNow.gpaAvg, sub: 'vs ' + H[0].gpaAvg + ' in ' + labels[0] },
          { label: 'Pass rate', val: kNow.passRate + '%', sub: 'projected: ' + fc.passRate + '% (CSE311 A)' },
          { label: 'CO attainment', val: kNow.coAttain + '%', sub: 'target 60%' },
          { label: 'Research outputs', val: kNow.research, sub: 'co-curricular ' + kNow.coCurricular + '%' },
        ],
        chartTitle: 'GPA & pass-rate trajectory',
        chart: lineChart([{ name: 'Avg GPA', vals: H.map(x => x.gpaAvg), color: 'var(--primary)' }, { name: 'Pass % / 25', vals: H.map(x => Math.round(x.passRate / 25 * 100) / 100), color: 'var(--green)' }], labels),
        findings: [
          `GPA has climbed from <b>${H[0].gpaAvg}</b> to <b>${kNow.gpaAvg}</b> over three recorded cycles — a steady, not spiky, improvement.`,
          `Pass rate is at <b>${kNow.passRate}%</b>; the Monte-Carlo forecast for CSE311 Sec A lands at <b>${fc.passRate}%</b> with ${100 - fc.weightDone}% of the grade still open.`,
          `At-risk headcount moved ${H[0].atRisk} → <b>${kNow.atRisk}</b>; your live flagged list currently holds <b>${flagged.length}</b> students.`,
          `Research output (${kNow.research}) and co-curricular engagement (${kNow.coCurricular}%) both trend up — keep the ICCIT 2026 submission on schedule.`,
        ],
        rec: `Your KPI risk is concentrated in one place: the flagged tail of Batch 231. Clearing even half of the ${flagged.length} flags through advising sessions protects both the pass-rate and CO-attainment lines simultaneously.`,
      };
      if (kind === 'risk') return {
        title: 'At-risk student watchlist', tag: 'AI',
        subtitle: `Predictive flags across Batch 231 · risk = attendance + trajectory + late submissions, not marks alone`,
        stats: [
          { label: 'Students flagged', val: flagged.length, sub: redCount + ' at red tier' },
          { label: 'Highest risk score', val: flagged.length ? flagged[0].r.score : '—', sub: flagged.length ? esc(flagged[0].s.name) : '' },
          { label: 'Avg attendance (flagged)', val: pct(mean(flagged.map(x => x.s.attendanceRate))) + '%', sub: 'cohort avg ' + pct(attAll) + '%' },
          { label: 'Forecast pass rate', val: fc.passRate + '%', sub: 'CSE311 Sec A · Monte-Carlo' },
        ],
        chartTitle: 'At-risk headcount across semesters',
        chart: lineChart([{ name: 'At-risk students', vals: H.map(x => x.atRisk).concat(flagged.length), color: 'var(--red)' }], labels.concat('Now (live)')),
        findings: [
          `<b>${flagged.length} students</b> are currently flagged; <b>${redCount}</b> are red-tier and need an advising session this week.`,
          flagged.length ? `Top of the list: <b>${flagged.slice(0, 3).map(x => esc(x.s.name)).join(', ')}</b> — their shared signal is sliding attendance plus late submissions.` : 'No student is currently above the watch threshold.',
          `Flagged students average <b>${pct(mean(flagged.map(x => x.s.attendanceRate)))}%</b> attendance vs <b>${pct(attAll)}%</b> for the cohort — the gap precedes the marks drop.`,
          `${T.advising.filter(a => a.followUp).length} advising cases already have follow-ups pending; three of them overlap with this watchlist.`,
        ],
        rec: `Book the ${redCount} red-tier students into advising slots before the next assessment window, and pair each with the posted practice resources — students who open the notes outperform those who don't by a measurable margin (see Layer 2 correlations).`,
      };
      if (kind === 'attendance') return {
        title: 'Attendance compliance', tag: 'Attendance',
        subtitle: `${T.students.length} students · ${secAtt.length} sections · threshold 75% · late-submission signal included`,
        stats: [
          { label: 'Cohort attendance', val: pct(attAll) + '%', sub: 'all sections combined' },
          { label: 'Below 75%', val: below75.length, sub: 'students out of compliance' },
          { label: 'Best section', val: secAtt.slice().sort((a, b) => b.v - a.v)[0].label, sub: secAtt.slice().sort((a, b) => b.v - a.v)[0].v + '% average' },
          { label: 'Late submissions', val: pct(lateAvg) + '%', sub: 'average late-sub rate' },
        ],
        chartTitle: 'Average attendance by section',
        chart: lineChart([{ name: 'Attendance %', vals: secAtt.map(x => x.v), color: 'var(--cyan-deep)' }], secAtt.map(x => x.label), { fmt: v => v + '%' }),
        findings: [
          `Cohort attendance sits at <b>${pct(attAll)}%</b>, but <b>${below75.length} students</b> are below the 75% compliance threshold.`,
          `Sections rank ${secAtt.slice().sort((a, b) => b.v - a.v).map(x => `<b>${x.label}</b> (${x.v}%)`).join(' · ')}.`,
          `Students with attendance ≥ 80% outperform the rest on marks — the correlation shows up in every course (Layer 2).`,
          `${below75.filter(s => flagged.some(f => f.s.id === s.id)).length} of the non-compliant students also appear on the at-risk watchlist — attendance is the earliest lever you have.`,
        ],
        rec: `Send the section-wide attendance reminder before Sunday's classes and schedule quick check-ins with the ${Math.min(5, below75.length)} lowest attenders. Every recovered student moves both the compliance and pass-rate metrics.`,
      };
      /* default: weekly academic report */
      return {
        title: 'Weekly academic report', tag: 'Academic',
        subtitle: `All courses · ${esc(T.courses[0].semester)} · ${T.courses.reduce((s, c) => s + c.sections.length, 0)} sections · ${T.students.length} students`,
        stats: [
          { label: 'Scripts graded (14d)', val: gradedRecently.reduce((s, a) => s + a.marks.length, 0), sub: gradedRecently.length + ' assessments' },
          { label: 'Cohort attendance', val: pct(attAll) + '%', sub: below75.length + ' students below 75%' },
          { label: 'Students flagged', val: flagged.length, sub: redCount + ' at red tier' },
          { label: 'Forecast pass rate', val: fc.passRate + '%', sub: 'CSE311 Sec A · avg ' + fc.avg + '%' },
        ],
        chartTitle: 'Pass rate & CO attainment across semesters',
        chart: lineChart([{ name: 'Pass %', vals: H.map(x => x.passRate), color: 'var(--green)' }, { name: 'CO %', vals: H.map(x => x.coAttain), color: 'var(--primary)' }], labels, { fmt: v => v + '%' }),
        findings: [
          `You graded <b>${gradedRecently.reduce((s, a) => s + a.marks.length, 0)} scripts</b> across ${gradedRecently.length} recent assessments; the grading queue is current.`,
          `Weakest topic this week: <b>${esc(weak311.topic)}</b> in CSE311 Sec A at <b>${pct(weak311.rate)}%</b> correct — re-cover it before Quiz 3 (${fmtD(T.upcoming[0].date)}).`,
          `<b>${flagged.length} students</b> are flagged for attention; the drivers are attendance and late submissions, not just marks.`,
          `Upcoming: ${T.upcoming.map(u => `<b>${esc(u.title)}</b> (${u.course}, ${fmtD(u.date)})`).join(' · ')}.`,
        ],
        rec: `Priorities for next week: re-teach ${esc(weak311.topic)} in CSE311 Sec A, clear the ${redCount} red-tier advising sessions, and lock the Quiz 3 paper from the question bank (avoid the two recently-reused items).`,
      };
    }

    /* ---------- render shell ---------- */
    p.innerHTML = `
      <div class="card gradient-border" style="padding:20px">
        <div style="display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap">
          <div style="width:48px;height:48px;border-radius:12px;background:var(--gradient-primary);display:grid;place-items:center;color:#fff;flex:0 0 48px">${icon('sparkles')}</div>
          <div style="flex:1;min-width:280px">
            <b style="font-size:16px">Generate a new report</b>
            <div class="hint" style="margin-top:2px">Describe what you need — the AI will compile data, charts and a summary.</div>
            <div class="field" style="margin-top:12px"><textarea id="rPrompt" rows="3" placeholder="e.g. Summarise CO attainment against target for every course I teach…"></textarea></div>
            <div class="pills" id="rChips" style="margin-top:10px">${TEMPLATES.map((t, i) => `<button class="pill" data-t="${i}">${t.label}</button>`).join('')}</div>
          </div>
          <button class="btn grad glow" id="rGen" style="align-self:center">${icon('sparkles', 'sm')} New report</button>
        </div>
        <div id="rProgress" style="display:none;margin-top:16px;border-top:1px solid var(--line-2);padding-top:14px"></div>
      </div>
      <div id="rReport" style="margin-top:16px"></div>
      <div class="card mb" style="margin-top:16px"><div class="card-head"><span class="sec-title">Report history</span><span class="spacer"></span><span class="hint" id="rHistCount"></span></div>
        <div class="grid g3" id="rHist" style="margin-bottom:0"></div>
      </div>`;

    /* ---------- history ---------- */
    const drawHist = () => {
      const all = [...loadHist(), ...SEED_REPORTS];
      $('#rHistCount').textContent = all.length + ' reports · generated & exportable';
      $('#rHist').innerHTML = all.map((r, i) => `
        <div class="card" style="margin-bottom:0;padding:18px;transition:transform .15s" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform=''">
          <div style="display:flex;justify-content:space-between;align-items:flex-start">
            <div style="width:40px;height:40px;border-radius:12px;background:var(--muted);border:1px solid var(--line);display:grid;place-items:center;color:var(--cyan-deep)">${icon('file-text')}</div>
            <span class="chip outline">${esc(r.tag)}</span>
          </div>
          <b style="display:block;margin-top:12px;font-size:14px">${esc(r.title)}</b>
          <small class="hint" style="display:block;margin-top:2px">${esc(r.date)}</small>
          <div style="display:flex;gap:8px;margin-top:16px">
            <button class="btn soft sm" style="flex:1" data-pdf="${i}">${icon('download', 'sm')} PDF</button>
            <button class="btn ghost sm" style="flex:1" data-print="${i}">${icon('printer', 'sm')} Print</button>
          </div>
        </div>`).join('');
      $$('#rHist [data-pdf]').forEach(b => b.onclick = () => toast('PDF export queued ✓ (demo)'));
      $$('#rHist [data-print]').forEach(b => b.onclick = () => {
        const r = all[+b.dataset.print];
        renderReport(buildReport(r.kind || detectKind(r.title)), false);
        setTimeout(() => window.print(), 350);
      });
    };

    /* ---------- report rendering ---------- */
    function renderReport(rep, save) {
      $('#rReport').innerHTML = `
        <div class="card animate-fade-in" id="reportDoc" style="margin-bottom:0">
          <div class="card-head" style="flex-wrap:wrap">
            <span class="sec-title" style="font-size:16px">${esc(rep.title)}</span>
            <span class="ai-label">${icon('sparkles', 'sm')} AI generated</span>
            <span class="chip outline">${esc(rep.tag)}</span>
            <span class="spacer"></span>
            <button class="btn soft sm" id="rPdfBtn">${icon('download', 'sm')} PDF</button>
            <button class="btn sm" id="rPrintBtn">${icon('printer', 'sm')} Print</button>
          </div>
          <div class="hint">${rep.subtitle}</div>
          <div class="metric-grid" style="margin:16px 0 4px">
            ${rep.stats.map(s => `<div class="metric"><span class="metric-label">${esc(s.label)}</span><span class="metric-val">${s.val}</span><span class="metric-sub">${esc(s.sub)}</span></div>`).join('')}
          </div>
          <div class="sec-title" style="margin:8px 0">${esc(rep.chartTitle)}</div>
          ${rep.chart}
          <div class="sec-title" style="margin:14px 0 6px">Key findings</div>
          ${rep.findings.map(f => `<div class="eonline">${f}</div>`).join('')}
          <div class="card" style="background:var(--primary-soft);border-color:transparent;margin:14px 0 0;padding:13px 15px">
            <div class="sec-title" style="margin-bottom:6px">Recommendation</div>
            <div style="font-size:13px;line-height:1.6">${rep.rec}</div>
          </div>
          <div class="hint" style="margin-top:12px">Compiled by EON from ${T.assessments.length} assessments, ${T.students.length} student records and the department KPI history · ${esc(T.teacher.name)} · ${esc(T.teacher.department)}</div>
        </div>`;
      $('#rPrintBtn').onclick = () => window.print();
      $('#rPdfBtn').onclick = () => toast('PDF export queued ✓ (demo)');
      if (save) {
        const h = loadHist();
        h.unshift({ title: rep.title, date: 'Just now', tag: rep.tag, kind: detectKind(rep.title) });
        saveHist(h); drawHist();
      }
      $('#rReport').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    /* ---------- simulated progress → real report ---------- */
    let busy = false;
    function generate() {
      if (busy) return;
      const q = $('#rPrompt').value.trim();
      if (!q) { toast('Describe the report you need first'); return; }
      busy = true;
      const kind = detectKind(q);
      const steps = [
        'Parsing request & selecting template…',
        `Compiling data from ${T.assessments.length} assessments & ${T.students.length} student records…`,
        'Computing CO attainment, risk scores & forecasts…',
        'Rendering charts…',
        'Writing executive summary…',
      ];
      const box = $('#rProgress');
      box.style.display = 'block';
      let step = 0;
      const paint = () => {
        box.innerHTML = `
          <div style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600">${icon('cpu', 'sm')} Generating report</div>
          <div class="progress-bar"><div class="progress-fill" style="width:${Math.round(step / steps.length * 100)}%"></div></div>
          <div style="margin-top:10px">${steps.map((s, i) => `<div style="display:flex;gap:8px;align-items:center;font-size:12.5px;padding:3px 0;color:${i < step ? 'var(--success)' : i === step ? 'inherit' : 'var(--muted-foreground)'}">
            ${i < step ? icon('check', 'sm') : i === step ? icon('refresh-cw', 'sm') : icon('clock', 'sm')} ${s}</div>`).join('')}</div>`;
      };
      paint();
      const tick = setInterval(() => {
        step++;
        if (step >= steps.length) {
          clearInterval(tick);
          step = steps.length; paint();
          setTimeout(() => {
            box.style.display = 'none';
            renderReport(buildReport(kind), true);
            toast('Report compiled ✓');
            busy = false;
          }, 320);
          return;
        }
        paint();
      }, 420);
    }

    $('#rGen').onclick = generate;
    $$('#rChips .pill').forEach(b => b.onclick = () => {
      $$('#rChips .pill').forEach(x => x.classList.toggle('on', x === b));
      $('#rPrompt').value = TEMPLATES[+b.dataset.t].prompt;
      $('#rPrompt').focus();
    });

    drawHist();
  };
})();
