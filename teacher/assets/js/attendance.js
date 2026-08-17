/* ==========================================================
   EON FOR TEACHER — Attendance Management (attendance.html)
   Spec: spec-dashboard.md §3 (AttendanceClient) — stat tiles,
   14-day attendance volume, course-wise attendance — plus the
   take-attendance manager (Present/Absent/Late per student,
   persisted to localStorage 'eft-att'). Fully offline; reads
   window.T and the app.js helpers at init time.
   ========================================================== */
'use strict';
(function () {

  /* ---------- spec seed (verbatim from spec-dashboard.md) ---------- */
  /* frozen deterministic 14-day attendance trend (shared table, §2.4 D1 / §3.2) */
  const TREND = [
    { d: 'D1', present: 73, absent: 18 },
    { d: 'D2', present: 77, absent: 17 },
    { d: 'D3', present: 80, absent: 16 },
    { d: 'D4', present: 81, absent: 14 },
    { d: 'D5', present: 80, absent: 12 },
    { d: 'D6', present: 78, absent: 10 },
    { d: 'D7', present: 74, absent: 10 },
    { d: 'D8', present: 70, absent: 10 },
    { d: 'D9', present: 67, absent: 11 },
    { d: 'D10', present: 65, absent: 13 },
    { d: 'D11', present: 65, absent: 15 },
    { d: 'D12', present: 67, absent: 16 },
    { d: 'D13', present: 71, absent: 17 },
    { d: 'D14', present: 75, absent: 17 },
  ];
  /* §3.1 stat tiles — labels & values verbatim */
  const TILES = [
    { label: 'Today', v: '92%' },
    { label: 'This week', v: '87%' },
    { label: 'Below 75%', v: '14' },
    { label: 'Auto alerts', v: '6' },
  ];

  const KEY = 'eft-att';

  /* deterministic per-student pseudo-hash (no Math.random) */
  function hash(s) { let h = 7; for (let i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) >>> 0; return h; }

  window.initAttendancePage = function () {
    const p = $('#content');

    /* course · section groups from OUR seed */
    const GROUPS = [];
    T.courses.forEach(c => c.sections.forEach(sec => GROUPS.push([c.code, sec])));
    let cur = 0;

    /* persisted toggle state: { "CSE311|A|231-35-001": "P"|"A"|"L" } */
    let store = {};
    try { store = JSON.parse(localStorage.getItem(KEY) || '{}'); } catch (e) { store = {}; }
    const save = () => localStorage.setItem(KEY, JSON.stringify(store));

    /* seeded default for today's session — attendanceRate drives it */
    const defStatus = (st) => st.attendanceRate < 0.60 ? 'A' : st.attendanceRate < 0.72 ? 'L' : 'P';
    const kOf = (course, sec, sid) => `${course}|${sec}|${sid}`;
    const getStatus = (course, sec, st) => store[kOf(course, sec, st.id)] || defStatus(st);

    /* seeded 12-session presence history → rolling attendance % (sparkline) */
    function historyVals(st) {
      const h = hash(st.id); const flags = [];
      for (let i = 0; i < 12; i++) flags.push((((h + i * 2654435761) >>> 0) % 1000) / 1000 < st.attendanceRate ? 1 : 0);
      const vals = []; let sum = 0;
      flags.forEach((f, i) => { sum += f; vals.push(Math.round(sum / (i + 1) * 100)); });
      return vals;
    }

    /* grouped bar chart (present violet / absent red, rounded tops) */
    function volumeChart(data, w = 760, h = 260) {
      const max = Math.max(...data.map(r => r.present)) * 1.1;
      const X0 = 40, plotW = w - X0 - 14, plotH = h - 44;
      const gw = plotW / data.length, bw = Math.min(14, gw / 2.6);
      const Y = v => 12 + (1 - v / max) * plotH;
      const grid = [0, .25, .5, .75, 1].map(f => { const v = Math.round(max * f); return `<line x1="${X0}" x2="${w - 14}" y1="${Y(v)}" y2="${Y(v)}" stroke="var(--line)" stroke-dasharray="3 3"/><text x="${X0 - 6}" y="${Y(v) + 3}" text-anchor="end" font-size="9" fill="var(--text-faint)" font-family="JetBrains Mono">${v}</text>`; }).join('');
      const bars = data.map((r, i) => {
        const cx = X0 + i * gw + gw / 2;
        return `<rect x="${(cx - bw - 1.5).toFixed(1)}" y="${Y(r.present).toFixed(1)}" width="${bw}" height="${(12 + plotH - Y(r.present)).toFixed(1)}" rx="3" fill="var(--primary)" opacity=".9"><title>${r.d} — present: ${r.present}</title></rect>
          <rect x="${(cx + 1.5).toFixed(1)}" y="${Y(r.absent).toFixed(1)}" width="${bw}" height="${(12 + plotH - Y(r.absent)).toFixed(1)}" rx="3" fill="var(--red)" opacity=".85"><title>${r.d} — absent: ${r.absent}</title></rect>
          <text x="${cx}" y="${h - 10}" text-anchor="middle" font-size="9" fill="var(--text-faint)" font-family="Inter">${r.d}</text>`;
      }).join('');
      return `<svg viewBox="0 0 ${w} ${h}" width="100%">${grid}${bars}</svg>`;
    }

    /* ---------- static shell ---------- */
    p.innerHTML = `
      <div class="stats" style="grid-template-columns:repeat(auto-fill,minmax(200px,1fr))">
        ${TILES.map((t, i) => `<div class="stat"><div class="ic ${['t-primary', 't-blue', 't-red', 't-amber'][i]}">${icon(['calendar-check', 'users', 'alert-triangle', 'bell'][i])}</div>
          <div class="v gradient-text">${t.v}</div><div class="l" style="text-transform:uppercase;letter-spacing:.16em">${t.label}</div></div>`).join('')}
      </div>

      <div class="card"><div class="card-head">
          <span class="sec-title">Take attendance</span>
          <span class="hint">— ${new Date().toLocaleDateString(undefined, { weekday: 'long', day: '2-digit', month: 'short' })} · toggles save locally</span>
          <span class="spacer"></span>
          <div class="pills" id="attPills">${GROUPS.map((g, i) => `<button class="pill ${i === 0 ? 'on' : ''}" data-i="${i}">${g[0]} · Sec ${g[1]}</button>`).join('')}</div>
        </div>
        <div id="attSummary" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:10px"></div>
        <div id="attRoster" style="max-height:520px;overflow:auto"></div>
        <div style="display:flex;gap:8px;margin-top:12px">
          <button class="btn soft sm" id="attAllPresent">${icon('check')} Mark all present</button>
          <span class="spacer" style="margin-left:auto"></span>
          <button class="btn sm" id="attSave">${icon('square-check')} Save attendance</button>
        </div>
      </div>

      <div class="card"><div class="card-head"><span class="sec-title">14-day attendance volume</span><span class="spacer"></span>
          <span class="chip violet">present</span><span class="chip red">absent</span></div>
        ${volumeChart(TREND)}
      </div>

      <div class="card mb"><div class="card-head"><span class="sec-title">Course-wise attendance</span><span class="spacer"></span>
          <span class="chip outline">Semester · Summer 26</span></div>
        <div id="courseWise"></div>
      </div>`;

    /* ---------- take-attendance roster ---------- */
    const drawRoster = () => {
      const [course, sec] = GROUPS[cur];
      const roster = T.enrolled[course][sec];
      const counts = { P: 0, A: 0, L: 0 };
      roster.forEach(st => counts[getStatus(course, sec, st)]++);
      $('#attSummary').innerHTML = `
        <span class="chip green">${counts.P} present</span>
        <span class="chip red">${counts.A} absent</span>
        <span class="chip amber">${counts.L} late</span>
        <span class="chip outline">session attendance ${pct((counts.P + counts.L) / roster.length)}%</span>
        <span class="hint">· ${course} — ${esc(T.course(course).title)} · Section ${sec} · ${roster.length} students</span>`;
      $('#attRoster').innerHTML = `<table class="dt"><thead><tr><th>Student</th><th>Semester rate</th><th>Rolling trend (12 sessions)</th><th>Today</th></tr></thead><tbody>
        ${roster.map(st => {
          const s = getStatus(course, sec, st);
          const low = st.attendanceRate < 0.75;
          return `<tr>
            <td><b>${esc(st.name)}</b><br><small class="hint">${st.id}</small></td>
            <td style="width:150px"><div class="bar"><i style="width:${pct(st.attendanceRate)}%;background:${low ? 'var(--red)' : 'var(--green)'}"></i></div>
              <small class="hint" style="color:${low ? 'var(--red)' : 'inherit'}">${pct(st.attendanceRate)}%${low ? ' · below 75%' : ''}</small></td>
            <td>${sparkSvg(historyVals(st), 84, 24, low ? 'var(--red)' : 'var(--primary)')}</td>
            <td style="width:230px"><div style="display:flex;gap:6px">
              <button class="chip ${s === 'P' ? 'green' : 'outline'}" style="cursor:pointer" data-sid="${st.id}" data-s="P">Present</button>
              <button class="chip ${s === 'A' ? 'red' : 'outline'}" style="cursor:pointer" data-sid="${st.id}" data-s="A">Absent</button>
              <button class="chip ${s === 'L' ? 'amber' : 'outline'}" style="cursor:pointer" data-sid="${st.id}" data-s="L">Late</button>
            </div></td></tr>`;
        }).join('')}</tbody></table>`;
      $$('#attRoster [data-sid]').forEach(b => b.onclick = () => {
        store[kOf(course, sec, b.dataset.sid)] = b.dataset.s;
        save(); drawRoster();
      });
    };
    $$('#attPills .pill').forEach(b => b.onclick = () => {
      cur = +b.dataset.i;
      $$('#attPills .pill').forEach(x => x.classList.toggle('on', x === b));
      drawRoster();
    });
    $('#attAllPresent').onclick = () => {
      const [course, sec] = GROUPS[cur];
      T.enrolled[course][sec].forEach(st => store[kOf(course, sec, st.id)] = 'P');
      save(); drawRoster(); toast('Everyone marked present for this session');
    };
    $('#attSave').onclick = () => {
      const [course, sec] = GROUPS[cur];
      save(); toast(`Attendance saved — ${course} Sec ${sec} ✓`);
    };

    /* ---------- course-wise compliance (computed from OUR seed) ---------- */
    $('#courseWise').innerHTML = GROUPS.map(([course, sec]) => {
      const roster = T.enrolled[course][sec];
      const v = pct(mean(roster.map(st => st.attendanceRate)));
      const below = roster.filter(st => st.attendanceRate < 0.75).length;
      return `<div style="display:grid;grid-template-columns:minmax(150px,4fr) 7fr 1fr;gap:12px;align-items:center;padding:8px 0;border-bottom:1px solid var(--line-2)">
        <span><b style="font-family:var(--font-mono)">${course} · ${sec}</b><br><small class="hint">${esc(T.course(course).title)} · ${roster.length} students · ${below} below 75%</small></span>
        <div class="bar" style="height:8px"><i style="width:${v}%"></i></div>
        <b class="num" style="text-align:right;color:${v < 75 ? 'var(--red)' : 'var(--green)'}">${v}%</b>
      </div>`;
    }).join('');

    drawRoster();
  };
})();
