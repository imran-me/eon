/* ==========================================================
   EON FOR TEACHER — Voice Assistant (voice.html)
   Fully SIMULATED voice UI: pulsing mic, suggested queries
   that "transcribe" and then answer with REAL numbers computed
   from the seed (T) and the analytics core (A). No microphone
   APIs are ever touched — it must never prompt for permissions.
   ========================================================== */
'use strict';
(function () {

  /* local stop-square icon (not in the global registry) */
  const squareSvg = '<svg class="lucide" viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="5" width="14" height="14" rx="2"/></svg>';

  const CHIPS = [
    'Show CO3 attainment for CSE311',
    'Who is at risk in Section B?',
    'Draft a notice for the Quiz 3 reschedule',
    'Generate leave application for tomorrow',
    'Send class cancellation notice for CSE311',
    'Create weekly attendance report',
    'Show students below 75% attendance',
  ];

  window.initVoicePage = function initVoicePage() {
    const p = document.querySelector('#content');
    let listening = false;
    let idleTimer = null;
    const transcript = [
      { time: '09:12', text: 'Create weekly attendance report', status: 'answered' },
      { time: '09:15', text: 'Who is at risk in Section B?', status: 'answered' },
    ];

    p.innerHTML = `
    <style>
      @keyframes vRing { 0%,100% { opacity:.9 } 50% { opacity:.25 } }
      @keyframes vBar { 0%,100% { opacity:1 } 50% { opacity:.45 } }
      .vpanel { position:relative; overflow:hidden; border-radius:24px; padding:44px 24px; text-align:center;
        background:#131627; color:#f8fafc; border:1px solid rgba(255,255,255,.08);
        background-image:radial-gradient(700px 320px at 12% -10%, rgba(157,92,246,.32), transparent 60%),
                         radial-gradient(560px 300px at 88% 0%, rgba(56,197,238,.22), transparent 60%),
                         radial-gradient(480px 320px at 50% 110%, rgba(240,85,158,.16), transparent 60%); }
      .vgrid { position:absolute; inset:0; opacity:.3; pointer-events:none;
        background-image:linear-gradient(to right, rgba(255,255,255,.04) 1px, transparent 1px),
                         linear-gradient(to bottom, rgba(255,255,255,.04) 1px, transparent 1px);
        background-size:32px 32px; }
      .vmic { position:relative; width:128px; height:128px; border-radius:999px; border:0; cursor:pointer;
        background:linear-gradient(135deg,#9d5cf6,#38c5ee); color:#fff; display:grid; place-items:center; margin:0 auto; }
      .vmic svg { width:48px; height:48px; stroke:#fff; }
      .vmic.on svg { width:40px; height:40px; }
      .vring1 { position:absolute; inset:-12px; border-radius:999px; border:1px solid rgba(82,216,236,.3); animation:vRing 2s ease-in-out infinite; pointer-events:none; }
      .vring2 { position:absolute; inset:-32px; border-radius:999px; border:1px solid rgba(157,92,246,.2); pointer-events:none; }
      .vwave { display:flex; align-items:flex-end; justify-content:center; gap:4px; height:48px; margin-top:24px; }
      .vwave i { width:4px; border-radius:999px; background:linear-gradient(135deg,#9d5cf6,#38c5ee); display:block; }
      .vchip { text-align:left; border-radius:12px; border:1px solid rgba(255,255,255,.14); background:rgba(255,255,255,.05);
        color:#e6e9f5; padding:12px; font-size:14px; cursor:pointer; transition:background .15s; font-family:inherit; }
      .vchip:hover { background:rgba(255,255,255,.12); }
      .vchip b { background:linear-gradient(135deg,#9d5cf6,#52d8ec); -webkit-background-clip:text; background-clip:text; color:transparent; font-weight:600; }
      .vheard { min-height:18px; margin-top:14px; font-size:13px; color:#9ba3bd; font-family:var(--font-mono); }
      .vnotice { white-space:pre-wrap; border:1px solid var(--line); background:var(--bg-soft, #f8fafc); border-radius:12px; padding:14px 16px; font-size:13px; line-height:1.65; }
    </style>

    <div class="vpanel"><div class="vgrid"></div>
      <div style="position:relative">
        <div style="position:relative;display:inline-block;margin-top:8px">
          <span class="vring1"></span><span class="vring2"></span>
          <button class="vmic" id="vMic" aria-label="Toggle simulated listening">${icon('mic')}</button>
        </div>
        <div id="vStatus" style="margin-top:24px;font-size:20px;font-weight:600">Tap to speak</div>
        <div style="font-size:14px;color:#9ba3bd;max-width:448px;margin:4px auto 0">Voice &rarr; speech-to-text &rarr; NLP &rarr; action. Try one of the suggested commands below.</div>
        <div id="vHeard" class="vheard"></div>
        <div id="vWave"></div>
        <div style="margin-top:28px;display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:12px;max-width:760px;margin-left:auto;margin-right:auto">
          ${CHIPS.map((c, i) => `<button class="vchip" data-q="${i}"><b>&ldquo;</b>${esc(c)}<b>&rdquo;</b></button>`).join('')}
        </div>
        <div class="hint" style="margin-top:18px;color:#7d86a3">Simulated demo — no microphone is accessed and no permission is ever requested.</div>
      </div>
    </div>

    <div class="grid g21" style="margin-top:16px">
      <div class="card"><div class="card-head"><span class="sec-title">Answers</span><span class="spacer"></span><span class="hint">computed live from your courses, marks &amp; rosters</span></div>
        <div id="vAnswers"><p class="hint">Ask something — tap a suggested query above. The answer is computed from the same data every other page reads.</p></div>
      </div>
      <div class="card"><div class="card-head"><span class="sec-title">Transcript log</span><span class="spacer"></span><button class="btn ghost sm" id="vClear">Clear</button></div>
        <div id="vLog"></div>
      </div>
    </div>`;

    /* ---------- listening state ---------- */
    const setListening = (on) => {
      listening = on;
      const mic = $('#vMic');
      mic.classList.toggle('on', on);
      mic.classList.toggle('animate-pulse-glow', on);
      mic.innerHTML = on ? squareSvg : icon('mic');
      $('#vStatus').textContent = on ? 'Listening…' : 'Tap to speak';
      $('#vWave').innerHTML = on
        ? `<div class="vwave">${Array.from({ length: 32 }, (_, i) =>
          `<i style="height:${(20 + Math.abs(Math.sin((i + 1) / 2)) * 80).toFixed(1)}%;animation:vBar 1s ${i * 30}ms ease-in-out infinite"></i>`).join('')}</div>`
        : '';
      if (!on) { clearTimeout(idleTimer); $('#vHeard').textContent = ''; }
    };

    $('#vMic').onclick = () => {
      if (listening) { setListening(false); return; }
      setListening(true);
      $('#vHeard').textContent = '';
      idleTimer = setTimeout(() => {
        setListening(false);
        toast('No speech detected — tap a suggested query to simulate one');
      }, 2600);
    };

    /* ---------- transcript log ---------- */
    const drawLog = () => {
      $('#vLog').innerHTML = transcript.length ? transcript.slice().reverse().map(t => `
        <div style="display:flex;gap:10px;align-items:center;padding:8px 0;border-bottom:1px solid var(--line-2)">
          <span class="num" style="font-family:var(--font-mono);font-size:11px;color:var(--text-faint);min-width:38px">${t.time}</span>
          <span style="flex:1;font-size:13px">&ldquo;${esc(t.text)}&rdquo;</span>
          <span class="chip ${t.status === 'answered' ? 'green' : 'slate'}">${t.status}</span>
        </div>`).join('') : '<p class="hint">Nothing heard yet this session.</p>';
    };
    $('#vClear').onclick = () => { transcript.length = 0; drawLog(); toast('Transcript cleared'); };

    /* ---------- answer builders (REAL numbers from T / A) ---------- */
    const barRow = (label, val, color) => `<div style="display:flex;align-items:center;gap:10px;margin:5px 0">
      <span style="flex:0 0 170px;font-size:12px">${esc(label)}</span>
      <span class="bar" style="flex:1"><i style="width:${val}%;background:${color}"></i></span>
      <b class="num" style="font-size:12px;min-width:38px;text-align:right">${val}%</b></div>`;

    function ansCo3() {
      const c = T.course('CSE311');
      const secs = c.sections.map(sec => ({ sec, co: A.cos('CSE311', sec).find(x => x.co === 'CO3') }));
      const weakest = A.topics('CSE311', 'A')[0];
      return {
        title: 'CO3 attainment — CSE311 Database Systems', ic: 'target',
        html: `
          <div class="hint" style="margin-bottom:8px">CO3 — ${esc(c.cos.find(x => x.id === 'CO3').text)} · target ${c.coTarget}%</div>
          ${secs.map(x => barRow(`Section ${x.sec}`, x.co.attain ?? 0, (x.co.attain ?? 0) >= c.coTarget ? 'var(--green)' : 'var(--red)')).join('')}
          <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px">
            ${secs.map(x => `<span class="chip ${(x.co.attain ?? 0) >= c.coTarget ? 'green' : 'red'}">Sec ${x.sec} · ${x.co.attain}% ${(x.co.attain ?? 0) >= c.coTarget ? 'meets target' : 'below ' + c.coTarget + '%'}</span>`).join('')}
          </div>
          <div class="eonline" style="margin-top:10px">Main drag: <b style="text-transform:capitalize">${esc(weakest.topic)}</b> in Sec A — class-wide correct-rate <b>${pct(weakest.rate)}%</b>. Re-covering it before Quiz 3 moves CO3 directly.</div>`,
      };
    }

    function ansRiskB() {
      const roster = T.enrolled['CSE311']['B'];
      const flagged = roster.map(s => ({ s, r: A.risk(s) })).filter(x => x.r.tier !== 'green').sort((x, y) => y.r.score - x.r.score);
      return {
        title: `At-risk students — Section B (${flagged.length} flagged of ${roster.length})`, ic: 'alert-triangle',
        html: `<table class="dt"><thead><tr><th>Student</th><th>Attendance</th><th>Average</th><th>Risk</th></tr></thead><tbody>
          ${flagged.map(({ s, r }) => `<tr><td><b>${esc(s.name)}</b><br><small class="hint">${s.id}</small></td>
            <td class="num">${pct(s.attendanceRate)}%</td><td class="num">${pct(r.avg)}%</td>
            <td><span class="chip ${r.tier}">${r.tier === 'red' ? 'at risk' : 'watch'} · ${r.score}</span></td></tr>`).join('')}
          </tbody></table>
          <div class="eonline" style="margin-top:10px"><b>${flagged.filter(x => x.r.tier === 'red').length}</b> red-tier and <b>${flagged.filter(x => x.r.tier === 'amber').length}</b> amber-tier students in Sec B. The common signal is attendance + late submissions, not just marks — full profiles are on the Students page.</div>`,
      };
    }

    function ansNotice() {
      const u = T.upcoming[0];   // Quiz 3 — Indexing
      const body = `NOTICE — ${u.title} rescheduled\n\nDear students (CSE311 · Sections A & B),\n\nThis is to inform you that ${u.title} will now be held on ${fmtD(u.date)} during your regular class hour. The syllabus (indexing) and the weight (${u.weight}%) remain unchanged. The SQL joins practice sheet and the indexing notes are available in the Resource Hub.\n\nBest regards,\n${T.teacher.name}\n${T.teacher.designation}, Department of ${T.teacher.department}`;
      return {
        title: 'Draft notice — Quiz 3 reschedule', ic: 'bell',
        html: `<div class="vnotice">${esc(body)}</div>
          <div style="display:flex;gap:8px;margin-top:10px">
            <button class="btn sm" data-act="publish">${icon('send', 'sm')} Publish to sections</button>
            <button class="btn ghost sm" data-act="copy">Copy draft</button>
          </div>`,
      };
    }

    function ansLeave() {
      const body = `To\nThe Head, Department of ${T.teacher.department}\n${T.teacher.institution}\n\nSubject: Application for one day's leave (${fmtD(T.day(-1))})\n\nDear Sir,\n\nI would like to request leave for ${fmtD(T.day(-1))} due to a personal engagement. My scheduled classes will be covered as follows: lecture slides and the practice sheet are already published in the Resource Hub, and the section representatives have been informed.\n\nSincerely,\n${T.teacher.name}\n${T.teacher.designation}, Department of ${T.teacher.department}`;
      return {
        title: 'Draft — leave application for tomorrow', ic: 'file-text',
        html: `<div class="vnotice">${esc(body)}</div>
          <div style="display:flex;gap:8px;margin-top:10px">
            <button class="btn sm" data-act="submit-leave">${icon('send', 'sm')} Submit to department</button>
            <button class="btn ghost sm" data-act="copy">Copy draft</button>
          </div>`,
      };
    }

    function ansCancel() {
      const s = SCHEDULE.find(x => x.course === 'CSE311');
      const body = `NOTICE — Class cancellation\n\nDear students (CSE311 · Section ${s.section}),\n\nTomorrow's CSE311 Database Systems class (${s.time}, room ${s.room}) stands cancelled. A make-up class will be announced shortly; meanwhile please work through the SQL joins practice sheet in the Resource Hub.\n\nBest regards,\n${T.teacher.name}\n${T.teacher.designation}, Department of ${T.teacher.department}`;
      return {
        title: 'Draft notice — class cancellation (CSE311)', ic: 'bell',
        html: `<div class="vnotice">${esc(body)}</div>
          <div style="display:flex;gap:8px;margin-top:10px">
            <button class="btn sm" data-act="publish">${icon('send', 'sm')} Send to section</button>
            <button class="btn ghost sm" data-act="copy">Copy draft</button>
          </div>`,
      };
    }

    function ansAttReport() {
      const groups = [];
      T.courses.forEach(c => c.sections.forEach(sec => {
        const roster = T.enrolled[c.code][sec];
        groups.push({ label: `${c.code} · Sec ${sec}`, v: pct(mean(roster.map(s => s.attendanceRate))) });
      }));
      const overall = pct(mean(T.students.map(s => s.attendanceRate)));
      const below = T.students.filter(s => s.attendanceRate < 0.75).length;
      return {
        title: 'Weekly attendance report — all sections', ic: 'calendar-check',
        html: `${groups.map(g => barRow(g.label, g.v, g.v < 75 ? 'var(--amber)' : 'var(--green)')).join('')}
          <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px">
            <span class="chip blue">overall ${overall}%</span>
            <span class="chip ${below ? 'amber' : 'green'}">${below} student${below === 1 ? '' : 's'} below 75%</span>
          </div>
          <div class="eonline" style="margin-top:10px">Attendance correlates with outcomes in this cohort — students at &ge;80% attendance average <b>${(A.correlations('CSE311', 'A').find(x => x.what === 'attendance ≥ 80%') || { with_: '—' }).with_}%</b> in CSE311 Sec A vs <b>${(A.correlations('CSE311', 'A').find(x => x.what === 'attendance ≥ 80%') || { without: '—' }).without}%</b> for the rest.</div>`,
      };
    }

    function ansBelow75() {
      const rows = T.students.filter(s => s.attendanceRate < 0.75).sort((a, b) => a.attendanceRate - b.attendanceRate);
      return {
        title: `Students below 75% attendance (${rows.length})`, ic: 'users',
        html: `<table class="dt"><thead><tr><th>Student</th><th>Batch · Sec</th><th>Attendance</th><th>Risk</th></tr></thead><tbody>
          ${rows.map(s => { const r = A.risk(s); return `<tr><td><b>${esc(s.name)}</b><br><small class="hint">${s.id}</small></td>
            <td>${s.batch} · ${s.section}</td>
            <td style="width:150px"><div class="bar"><i style="width:${pct(s.attendanceRate)}%;background:var(--red)"></i></div><small class="hint">${pct(s.attendanceRate)}%</small></td>
            <td><span class="chip ${r.tier}">${r.tier === 'red' ? 'at risk' : r.tier === 'amber' ? 'watch' : 'on track'} · ${r.score}</span></td></tr>`; }).join('')}
          </tbody></table>
          <div class="eonline" style="margin-top:10px">Early-warning emails can be drafted for all ${rows.length} from the AI Insights page.</div>`,
      };
    }

    const BUILDERS = [ansCo3, ansRiskB, ansNotice, ansLeave, ansCancel, ansAttReport, ansBelow75];

    /* ---------- answer rendering ---------- */
    function renderAnswer(q, built) {
      const box = $('#vAnswers');
      if (box.firstElementChild && box.firstElementChild.tagName === 'P') box.innerHTML = '';
      const wrap = document.createElement('div');
      wrap.style.cssText = 'border:1px solid var(--line);border-radius:12px;padding:13px 15px;margin-bottom:12px';
      wrap.innerHTML = `
        <div style="display:flex;gap:9px;align-items:center;margin-bottom:8px">
          <span style="width:30px;height:30px;border-radius:9px;background:var(--primary-soft);display:grid;place-items:center;color:var(--primary)">${icon(built.ic, 'sm')}</span>
          <b style="flex:1">${esc(built.title)}</b><span class="ai-label">${icon('sparkles', 'sm')} EON</span>
        </div>
        <div class="hint" style="margin-bottom:8px">Heard: &ldquo;${esc(q)}&rdquo;</div>
        ${built.html}`;
      box.prepend(wrap);
      wrap.querySelectorAll('[data-act]').forEach(b => b.onclick = () => toast(
        b.dataset.act === 'copy' ? 'Draft copied to clipboard (demo)'
          : b.dataset.act === 'submit-leave' ? 'Leave application submitted to the department (demo)'
            : 'Notice queued to both sections (demo)'));
    }

    /* ---------- chip → simulated transcription → answer ---------- */
    let busy = false;
    $$('.vchip').forEach(btn => btn.onclick = () => {
      if (busy) return;
      busy = true;
      const i = +btn.dataset.q, q = CHIPS[i];
      clearTimeout(idleTimer);
      setListening(true);
      $('#vHeard').textContent = '';
      setTimeout(() => { $('#vHeard').textContent = 'Transcribing: "' + q + '"'; }, 500);
      setTimeout(() => {
        setListening(false);
        const now = new Date();
        transcript.push({ time: String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0'), text: q, status: 'answered' });
        drawLog();
        renderAnswer(q, BUILDERS[i]());
        busy = false;
      }, 1250);
    });

    drawLog();
  };
})();
