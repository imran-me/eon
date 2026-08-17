/* ==========================================================
   EON FOR TEACHER — AI Insights (insights.html)
   Spec: spec-pages.md §6 (InsightsClient) — insight cards,
   "Predicted at-risk students" — extended with risk drivers
   and a per-student "Draft intervention" modal. Rows come from
   A.flagged() so every student matches OUR roster.
   ========================================================== */
'use strict';
(function () {

  /* §6.1 insight cards — text verbatim (course code adapted to our seed) */
  const INSIGHT_CARDS = [
    { tag: 'OBE', title: 'PO4 attainment is low', desc: 'Practical lab activities suggested. 18 students below threshold.' },
    { tag: 'Attendance', title: 'Attendance dropped 12% in CSE311', desc: 'Trigger early-warning emails to 9 at-risk students.' },
    { tag: 'Workflow', title: 'Assignment grading 92% complete', desc: 'Auto-summary ready for the weekly KPI report.' },
  ];

  window.initInsightsPage = function () {
    const p = $('#content');

    const flagged = A.flagged(8);
    const all = A.flagged(99);

    /* the course where this student's running average is weakest */
    const courseOf = (sid) => {
      const by = {};
      A.history(sid).forEach(r => (by[r.course] = by[r.course] || []).push(r.p));
      let worst = 'CSE311', wv = 2;
      Object.entries(by).forEach(([c, ps]) => { const m = mean(ps); if (m < wv) { wv = m; worst = c; } });
      return worst;
    };

    /* risk drivers — prevalence of each signal across the flagged cohort */
    const drivers = [
      { label: 'Attendance below 75%', n: all.filter(x => x.s.attendanceRate < 0.75).length },
      { label: 'Declining trend (recent vs early assessments)', n: all.filter(x => x.r.trend < -0.05).length },
      { label: 'Frequent late submissions (>30%)', n: all.filter(x => x.s.lateSubRate > 0.30).length },
      { label: 'Not opening posted resources', n: all.filter(x => !x.s.opensNotes).length },
      { label: 'Running average below 62%', n: all.filter(x => x.r.avg < 0.62).length },
    ].sort((a, b) => b.n - a.n);
    const corr = A.correlations('CSE311', 'A');

    p.innerHTML = `
      <div class="grid g3">
        ${INSIGHT_CARDS.map(c => `<div class="card" style="margin-bottom:0">
          <div style="display:flex;align-items:flex-start;justify-content:space-between">
            <span class="chip outline" style="font-size:10px">${c.tag}</span>
            <span style="color:var(--cyan-deep)">${icon('brain')}</span>
          </div>
          <div style="font-size:16px;font-weight:600;margin-top:12px">${c.title}</div>
          <div style="font-size:14px;color:var(--muted-foreground);margin-top:4px">${c.desc}</div>
        </div>`).join('')}
      </div>

      <div class="grid g21" style="margin-top:16px">
        <div class="card" style="margin-bottom:0"><div class="card-head"><span class="sec-title">Predicted at-risk students</span><span class="spacer"></span>
            <span class="hint">${flagged.length} flagged of ${T.students.filter(s => s.batch === '231').length} in batch 231 · ranked by risk score</span></div>
          <div id="riskList"></div>
        </div>
        <div class="card" style="margin-bottom:0"><div class="card-head"><span class="sec-title">Risk drivers</span><span class="spacer"></span><span class="hint">across ${all.length} flagged</span></div>
          ${drivers.map(d => `<div style="display:flex;align-items:center;gap:10px;margin:7px 0">
            <span style="flex:0 0 180px;font-size:12px">${esc(d.label)}</span>
            <span class="bar" style="flex:1"><i style="width:${pct(d.n / (all.length || 1))}%;background:${d.n / (all.length || 1) > 0.6 ? 'var(--red)' : 'var(--amber)'}"></i></span>
            <b class="num" style="font-size:12px;min-width:34px;text-align:right">${d.n}/${all.length}</b></div>`).join('')}
          <div class="sec-title" style="margin:14px 0 6px">What actually moves outcomes</div>
          ${corr.map(x => `<div class="eonline">Students who ${esc(x.what)} average <b>${x.with_}%</b> vs <b>${x.without}%</b> without — a <b>${x.gap}-point gap</b> (CSE311 Sec A).</div>`).join('')}
          <div class="hint" style="margin-top:8px">The model blends attendance, running average, trajectory and submission lateness — flags, not verdicts.</div>
        </div>
      </div>`;

    /* ---------- at-risk rows ---------- */
    $('#riskList').innerHTML = flagged.map(({ s, r }, i) => {
      const course = courseOf(s.id);
      return `<div style="display:flex;gap:16px;align-items:center;border:1px solid var(--line);border-radius:12px;padding:12px;margin-bottom:8px">
        <span class="risk-dot" style="background:var(--${r.tier})"></span>
        <span style="flex:1;min-width:0"><b style="font-size:14px">${esc(s.name)}</b><br><small class="hint">${s.id} · ${course}</small></span>
        <span style="flex:0 0 150px"><small class="hint" style="display:block;margin-bottom:4px">Attendance ${pct(s.attendanceRate)}%</small>
          <div class="bar"><i style="width:${pct(s.attendanceRate)}%"></i></div></span>
        <span class="chip red">Risk ${r.score}</span>
        <button class="btn soft sm" data-iv="${i}">${icon('pen-line')} Draft intervention</button>
      </div>`;
    }).join('') || '<p class="hint">No students are currently predicted at risk.</p>';

    /* ---------- Draft intervention modal ---------- */
    function draftPlan(s, r, course) {
      const topics = A.topics(course, s.section);
      const weak = topics.length ? topics[0].topic : 'the weakest topic';
      const trend = `${r.trend >= 0 ? '+' : ''}${pct(r.trend)}%`;
      return `Intervention plan — ${s.name} (${s.id})
Course focus: ${course} — ${T.course(course).title}, Section ${s.section}

Signals: attendance ${pct(s.attendanceRate)}%, running average ${pct(r.avg)}%, trend ${trend}, late submissions ${pct(s.lateSubRate)}%${s.opensNotes ? '' : ', not opening posted resources'}.

Week 1 — One-on-one advising session after the next ${course} class; agree on a simple attendance commitment and share the posted notes pack.
Week 2 — Targeted practice set on "${weak}" (the section's weakest topic); pair with a strong study partner from Section ${s.section}.
Week 3 — Short re-check quiz on "${weak}"; if attendance stays below 75%, escalate to department counseling with this plan attached.

— ${T.teacher.name}, ${T.teacher.designation}, ${T.teacher.department}`;
    }

    $$('#riskList [data-iv]').forEach(b => b.onclick = () => {
      const { s, r } = flagged[+b.dataset.iv];
      const course = courseOf(s.id);
      openModal(`Draft intervention — ${esc(s.name)} <span class="chip ${r.tier}" style="margin-left:6px">risk ${r.score}</span>`, `
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px">
          <span class="chip outline">${s.id}</span>
          <span class="chip blue">${course} · Sec ${s.section}</span>
          <span class="chip ${s.attendanceRate < 0.75 ? 'red' : 'green'}">attendance ${pct(s.attendanceRate)}%</span>
          <span class="chip ${r.avg < 0.5 ? 'red' : 'amber'}">avg ${pct(r.avg)}%</span>
          <span class="chip ${r.trend < 0 ? 'red' : 'green'}">trend ${r.trend >= 0 ? '+' : ''}${pct(r.trend)}%</span>
        </div>
        <div id="ivBody"><p class="hint">${icon('sparkles', 'sm')} Drafting a personalized plan from ${s.name.split(' ')[0]}'s signals…</p></div>`,
        `<button class="btn ghost sm" id="ivLog">${icon('clipboard-list')} Save to counseling log</button>
         <button class="btn sm" id="ivSend">${icon('send')} Send to student</button>`);
      setTimeout(() => {
        const body = $('#ivBody');
        if (body) body.innerHTML = `<div class="field"><label>Generated plan — edit before sending</label><textarea rows="14">${esc(draftPlan(s, r, course))}</textarea></div>`;
      }, 450);
      $('#ivLog').onclick = () => { toast('Plan saved to the counseling log ✓'); };
      $('#ivSend').onclick = () => { toast(`Intervention plan sent to ${s.name.split(' ')[0]} ✓`); document.getElementById('tOverlay').remove(); };
    });
  };
})();
