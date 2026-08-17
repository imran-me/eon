/* ==========================================================
   EON FOR TEACHER — Counseling Management (counseling.html)
   Stat tiles, "Generate Personalized Improvement Plans" panel,
   Student Monitoring List built from A.flagged(), a per-student
   week-by-week plan generator driven by that student's actual
   weak topics (T + A), Schedule-Session persistence and a
   seeded session history.
   ========================================================== */
'use strict';
(function () {

  /* ---------- page seed (verbatim from spec) ---------- */
  const TILES = [
    { label: 'Pending Requests', value: 12, ic: 'message-square', color: '#3b82f6', bg: '#dbeafe' },
    { label: 'At-Risk Students', value: 8, ic: 'alert-triangle', color: '#ef4444', bg: '#fee2e2' },
    { label: 'Low Performance', value: 15, ic: 'activity', color: '#f59e0b', bg: '#fef3c7' },
    { label: 'Sessions Completed', value: 45, ic: 'calendar-check', color: '#10b981', bg: '#d1fae5' },
  ];
  const STATUSES = ['Requested Session', 'Critical Alert', 'Needs Guidance', 'Follow-up'];
  const KEY = 'eft-counsel';

  window.initCounselingPage = function () {
    const p = $('#content');
    const flagged = A.flagged(8);
    const scheduled = () => JSON.parse(localStorage.getItem(KEY) || '[]');
    const saveSession = (rec) => { const all = scheduled(); all.push(rec); localStorage.setItem(KEY, JSON.stringify(all)); };

    /* per-student topic weakness from real marks (T + A) */
    function weakTopics(sid) {
      const agg = {};
      T.assessments.forEach(a => {
        const m = a.marks.find(r => r.sid === sid); if (!m) return;
        a.questions.forEach(q => {
          const g = m.perQ.find(x => x.no === q.no);
          const k = a.course + '|' + q.topic;
          (agg[k] = agg[k] || { course: a.course, topic: q.topic, got: 0, max: 0 });
          agg[k].got += g.got; agg[k].max += q.marks;
        });
      });
      return Object.values(agg).map(x => ({ ...x, rate: x.got / x.max }))
        .sort((a, b) => a.rate - b.rate).filter(x => x.rate < 0.65);
    }
    const primaryCourse = (sid) => { const w = weakTopics(sid); return w.length ? w[0].course : 'CSE311'; };
    const cgpa = (r) => (Math.round((2.0 + r.avg * 2.0) * 100) / 100).toFixed(2);
    const statusOf = (sid, i) => scheduled().some(x => x.sid === sid) ? 'Session Scheduled' : STATUSES[i % STATUSES.length];

    p.innerHTML = `
    <!-- Section 1 — stat tiles -->
    <div class="metric-grid">
      ${TILES.map(t => `<div class="metric glass" style="border-radius:16px;align-items:center;text-align:center">
        <div style="width:40px;height:40px;border-radius:999px;background:${t.bg};color:${t.color};display:grid;place-items:center;margin-bottom:12px">${icon(t.ic)}</div>
        <div style="font-size:24px;font-weight:700;color:#1e293b">${t.value}</div>
        <div class="metric-label" style="margin-top:4px">${t.label}</div>
      </div>`).join('')}
    </div>

    <!-- Section 2 — action panel -->
    <div class="card gradient-border mb" style="border-radius:16px;padding:24px 32px">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:24px;flex-wrap:wrap">
        <div style="flex:1;min-width:280px">
          <h2 style="font-size:20px;font-weight:700;color:#1e293b;margin:0">Generate Personalized Improvement Plans<span class="ai-label">${icon('sparkles', 'sm')} AI</span></h2>
          <p style="font-size:14px;color:#475569;margin:4px 0 0;max-width:672px;line-height:1.6">The AI agent has identified ${flagged.length} students at critical academic risk. Would you like to automatically draft personalized academic guidance plans for these students based on their performance metrics and syllabus requirements?</p>
        </div>
        <button class="btn grad glow" id="draftAll" style="border-radius:12px;padding:12px 20px;white-space:nowrap">${icon('file-text', 'sm')} Draft ${flagged.length} Action Plans</button>
      </div>
    </div>

    <!-- Section 3 — monitoring table -->
    <div class="card glass" style="border-radius:16px;padding:24px">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:24px;flex-wrap:wrap">
        <div>
          <div style="font-size:18px;font-weight:600;color:#1e293b">Student Monitoring List</div>
          <div style="font-size:14px;color:#64748b;margin-top:4px">Counseling requests and at-risk students requiring immediate attention.</div>
        </div>
        <a href="students.html" style="color:#4f46e5;font-size:14px;font-weight:500;display:inline-flex;align-items:center;gap:2px;text-decoration:none" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">View All Students ${icon('chevron-right', 'sm')}</a>
      </div>
      <div style="overflow-x:auto"><table class="dt" id="monTable"><thead>
        <tr style="background:#f8fafc"><th>Student Info</th><th>Risk Level</th><th>Attendance &amp; CGPA</th><th>Status</th><th style="text-align:right">Actions</th></tr>
      </thead><tbody id="monBody"></tbody></table></div>
    </div>

    <!-- Section 4 — session history -->
    <div class="card glass mb" style="border-radius:16px;padding:24px">
      <div class="card-head" style="margin-bottom:14px"><span style="color:#10b981">${icon('calendar-check')}</span><span class="sec-title" style="color:#1e293b;font-size:14px">Session history</span><span class="spacer"></span><span class="hint">advising log + sessions you schedule here</span></div>
      <div id="sessHist"></div>
    </div>`;

    /* ---------- monitoring table ---------- */
    function drawTable() {
      $('#monBody').innerHTML = flagged.map(({ s, r }, i) => {
        const att = pct(s.attendanceRate);
        const high = r.tier === 'red';
        const riskStyle = high ? 'background:#fee2e2;color:#b91c1c;border-color:#fecaca' : 'background:#fef3c7;color:#b45309;border-color:#fde68a';
        const lowAtt = att < 60;
        const status = statusOf(s.id, i);
        return `<tr class="monrow">
          <td><b style="color:#1e293b">${esc(s.name)}</b><br><small style="font-size:12px;color:#64748b">${s.id} &bull; ${primaryCourse(s.id)}</small></td>
          <td><span class="badge" style="${riskStyle}">${high ? 'High' : 'Medium'} Risk</span></td>
          <td style="width:192px;min-width:170px">
            <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px"><span style="color:#475569">Att: ${att}%</span><span style="color:#475569;font-weight:500">CGPA: ${cgpa(r)}</span></div>
            <div style="height:6px;border-radius:999px;background:${lowAtt ? '#fecaca' : '#e2e8f0'};overflow:hidden"><i style="display:block;height:100%;width:${att}%;border-radius:999px;background:${lowAtt ? '#ef4444' : 'var(--primary)'}"></i></div>
          </td>
          <td><span style="font-size:12px;font-weight:500;color:${status === 'Session Scheduled' ? '#047857' : '#334155'};background:${status === 'Session Scheduled' ? '#d1fae5' : '#f1f5f9'};padding:4px 10px;border-radius:999px;white-space:nowrap">${status}</span></td>
          <td style="text-align:right;white-space:nowrap">
            <button class="btn ghost sm" data-plan="${s.id}">${icon('sparkles', 'sm')} Generate Plan</button>
            <button class="btn sm" data-sched="${s.id}" style="background:#eef2ff;color:#4f46e5;border-radius:8px;font-size:12px;box-shadow:none">Schedule Session</button>
          </td></tr>`;
      }).join('');
      $$('#monBody [data-plan]').forEach(b => b.onclick = () => openPlan(b.dataset.plan));
      $$('#monBody [data-sched]').forEach(b => b.onclick = () => openSchedule(b.dataset.sched));
      $$('#monBody .monrow').forEach(tr => { tr.onmouseenter = () => tr.style.background = 'rgba(248,250,252,.5)'; tr.onmouseleave = () => tr.style.background = ''; });
    }

    /* ---------- week-by-week plan generator ---------- */
    function planHtml(st) {
      const r = A.risk(st);
      const weak = weakTopics(st.id).slice(0, 4);
      const resFor = (w) => T.resources.find(x => x.course === w.course && x.title.toLowerCase().includes(w.topic.split(' ')[0])) || T.resources.find(x => x.course === w.course);
      const weeks = weak.map((w, i) => {
        const res = resFor(w);
        const a = A.of(w.course).slice().reverse().find(x => x.questions.some(q => q.topic === w.topic));
        return `<div class="timeline-item">
          <span class="tl-dot" style="background:${i === 0 ? 'var(--red)' : i === 1 ? 'var(--amber)' : 'var(--primary)'}"></span>
          <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#64748b">Week ${i + 1} — ${w.course} · <span style="text-transform:capitalize">${esc(w.topic)}</span> <span class="chip ${w.rate < 0.45 ? 'red' : 'amber'}" style="margin-left:4px">${pct(w.rate)}% correct</span></div>
          <div style="font-size:13px;color:#334155;margin-top:4px;line-height:1.6">
            &bull; Rework ${res ? `<b>${esc(res.title)}</b>` : 'the posted notes'} — 30 min, twice this week.<br>
            &bull; Redo the <b>${esc(w.topic)}</b> questions from ${a ? `<b>${esc(a.title)}</b>` : 'the last assessment'} untimed, then once timed.<br>
            &bull; 15-min check-in with ${esc(T.teacher.name.split(' ')[1] || T.teacher.name)} at office hours; bring the two hardest problems.
          </div></div>`;
      }).join('');
      return `
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px">
          <span class="chip ${r.tier}">risk ${r.score}</span>
          <span class="chip outline">avg ${pct(r.avg)}%</span>
          <span class="chip ${st.attendanceRate < 0.6 ? 'red' : 'slate'}">attendance ${pct(st.attendanceRate)}%</span>
          <span class="chip ${st.opensNotes ? 'green' : 'amber'}">${st.opensNotes ? 'opens resources' : 'not opening resources'}</span>
        </div>
        <div class="sec-title" style="margin-bottom:8px">${weak.length}-week personalized plan — built from ${esc(st.name.split(' ')[0])}'s weakest topics</div>
        <div class="timeline">${weeks || '<p class="hint">No topic below the 65% threshold — recommend a light-touch check-in instead of a full plan.</p>'}</div>
        <div class="eonline" style="margin-top:10px">Sequencing: weakest topic first while motivation is highest; every task maps to a resource that already exists in your hub, so nothing here needs new material.</div>
        <div class="card" style="background:var(--amber-soft);border-color:transparent;margin-top:12px;padding:10px 14px;font-size:12px;color:#6b4d09"><b>Review before sending:</b> the plan is drafted from performance data — adjust for anything you know that the data doesn't (health, family, workload).</div>`;
    }
    function openPlan(sid) {
      const st = T.student(sid); if (!st) return;
      const o = openModal(`Improvement plan — ${esc(st.name)} <span class="hint" style="margin-left:6px">${st.id}</span>`,
        `<div style="display:flex;align-items:center;gap:10px;padding:26px 4px;color:#64748b;font-size:13px">${icon('sparkles')} Analyzing ${esc(st.name.split(' ')[0])}'s marks across ${A.history(sid).length} assessments…</div>`);
      setTimeout(() => {
        const body = o.querySelector('.modal-body'); if (!body) return;
        body.innerHTML = planHtml(st);
        const foot = o.querySelector('.modal-foot');
        const footHtml = `<button class="btn ghost sm" onclick="toast('Plan exported as PDF ✓ (demo)')">${icon('download', 'sm')} Export</button>
           <button class="btn sm" onclick="toast('Plan sent to ${esc(st.name.split(' ')[0])} ✓ (demo)');document.getElementById('tOverlay').remove()">${icon('send', 'sm')} Send to student</button>`;
        if (foot) foot.innerHTML = footHtml;
        else o.querySelector('.modal').insertAdjacentHTML('beforeend', `<div class="modal-foot">${footHtml}</div>`);
      }, 750);
    }

    /* Draft N Action Plans — batch generation over the flagged list */
    $('#draftAll').onclick = () => {
      const b = $('#draftAll');
      b.disabled = true; const orig = b.innerHTML; b.innerHTML = 'Drafting plans…';
      setTimeout(() => {
        b.disabled = false; b.innerHTML = orig;
        openModal(`${flagged.length} action plans drafted <span class="ai-label">${icon('sparkles', 'sm')} AI</span>`, `
          <p class="hint" style="margin:0 0 12px">One plan per flagged student, each sequenced from that student's own weak topics. Open any to review before sending.</p>
          ${flagged.map(({ s, r }) => { const w = weakTopics(s.id); return `<div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--line-2)">
            <span class="risk-dot" style="background:var(--${r.tier})"></span>
            <span style="flex:1"><b>${esc(s.name)}</b> <small class="hint">${s.id}</small><br><small class="hint">${w.length} weak topic${w.length === 1 ? '' : 's'} · starts with <span style="text-transform:capitalize"><b>${esc(w[0] ? w[0].topic : 'check-in')}</b></span>${w[0] ? ` (${pct(w[0].rate)}%)` : ''}</small></span>
            <button class="btn soft sm" data-batchplan="${s.id}">Open plan</button></div>`; }).join('')}`,
          `<button class="btn sm" onclick="toast('${flagged.length} plans queued for review ✓');document.getElementById('tOverlay').remove()">${icon('check', 'sm')} Queue all for review</button>`);
        $$('#tOverlay [data-batchplan]').forEach(x => x.onclick = () => openPlan(x.dataset.batchplan));
      }, 1000);
    };

    /* ---------- schedule session (persists to eft-counsel) ---------- */
    function openSchedule(sid) {
      const st = T.student(sid); if (!st) return;
      const d = new Date(); d.setDate(d.getDate() + 3);
      const iso = d.toISOString().slice(0, 10);
      const o = openModal(`Schedule session — ${esc(st.name)}`, `
        <div class="form-grid">
          <div class="field"><label>Date</label><input type="date" id="ssDate" value="${iso}"></div>
          <div class="field"><label>Time</label><select id="ssTime"><option>10:00</option><option>11:30</option><option selected>14:00</option><option>15:30</option></select></div>
          <div class="field span"><label>Focus</label><input type="text" id="ssTopic" value="${esc(weakTopics(sid)[0] ? 'Recovery plan — ' + weakTopics(sid)[0].topic : 'General academic check-in')}"></div>
          <div class="field span"><label>Notes for the student</label><textarea rows="3" id="ssNote">Bring your ${primaryCourse(sid)} scripts and the practice sheet — we will build a simple weekly plan together.</textarea></div>
        </div>`,
        `<button class="btn sm" id="ssSave">${icon('calendar-check', 'sm')} Confirm session</button>`);
      o.querySelector('#ssSave').onclick = () => {
        saveSession({ sid, name: st.name, date: o.querySelector('#ssDate').value, time: o.querySelector('#ssTime').value, topic: o.querySelector('#ssTopic').value, note: o.querySelector('#ssNote').value });
        o.remove(); toast(`Session with ${st.name.split(' ')[0]} scheduled ✓`);
        drawTable(); drawHistory();
      };
    }

    /* ---------- session history (seeded advising log + scheduled) ---------- */
    function drawHistory() {
      const sched = scheduled().slice().reverse();
      const past = T.advising.slice().sort((a, b) => b.date.localeCompare(a.date));
      $('#sessHist').innerHTML =
        sched.map(x => `<div style="display:flex;gap:10px;align-items:center;padding:8px 0;border-bottom:1px solid var(--line-2)">
          <span class="chip blue">scheduled</span>
          <span style="flex:1"><b>${esc(x.name)}</b> — ${esc(x.topic)}<br><small class="hint">${fmtD(x.date)} · ${esc(x.time)} · ${esc(x.note)}</small></span></div>`).join('') +
        past.map(a => { const st = T.student(a.sid); return `<div style="display:flex;gap:10px;align-items:center;padding:8px 0;border-bottom:1px solid var(--line-2)">
          <span class="chip ${a.followUp ? 'amber' : 'green'}">${a.followUp ? 'follow-up due' : 'completed'}</span>
          <span style="flex:1"><b>${esc(st.name)}</b> — ${esc(a.topic)}<br><small class="hint">${fmtD(a.date)} · ${esc(a.action)}</small></span>
          ${a.followUp ? `<button class="btn ghost sm" onclick="toast('Follow-up check-in drafted ✓ (demo)')">${icon('mail', 'sm')} Check in</button>` : ''}</div>`; }).join('');
    }

    drawTable();
    drawHistory();
  };
})();
