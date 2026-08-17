/* ==========================================================
   EON FOR TEACHER — the intelligence panel (assets/js/eon.js)
   Five layers, cohort-shaped: diagnostic → behavioural →
   predictive → prescriptive → loop-closing. Same brain as the
   student system, pointed at batches instead of one person.
   Uses the analytics core (A) from app.js. Loaded before app.js;
   the router calls window.initEonPage().
   ========================================================== */
'use strict';

window.initEonPage = function initEonPage() {
  const root = document.querySelector('#content');
  root.innerHTML = '<div id="eonTop"></div><div id="eonBody"></div>';
  const p = document.querySelector('#eonBody');
  const CS = [['CSE311', 'A'], ['CSE311', 'B'], ['CSE220', 'A'], ['CSE220', 'B'], ['ENG103', 'A']];
  let scope = 0;   // index into CS

  /* ── deck top: quick-jump strip + LIVE working area + Ask EON ── */
  function renderTop() {
    const jumps = [['#eonLive', 'Live working'], ['#eonAsk', 'Ask EON'], ['#l1', 'Gaps'], ['#l2', 'Why'], ['#l3', 'Forecast'], ['#l4', 'Actions'], ['#l5', 'Loop'], ['integrity.html', 'Integrity']];
    document.querySelector('#eonTop').innerHTML = `
      <div class="eon-strip">${jumps.map(([h, l]) => `<a class="pill" href="${h}">${l}</a>`).join('')}</div>

      <div class="card mb panel" id="eonLive"><div class="card-head"><span class="sec-title">EON — live working area</span><span class="ai-label">${icon('activity', 'sm')} reasoning trace</span><span class="spacer"></span><span class="chip green" id="eonState"><span style="width:6px;height:6px;border-radius:99px;background:currentColor;display:inline-block"></span> reading</span></div>
        <div class="grid g21">
          <div id="eonTrace" style="font: 500 12.5px var(--font-mono);line-height:2;max-height:198px;overflow-y:auto;background:color-mix(in oklab,var(--muted) 26%,transparent);border:1px solid color-mix(in oklab,var(--border) 60%,transparent);border-radius:12px;padding:10px 14px"></div>
          <div><div class="sec-title" style="margin-bottom:8px">Live alerts</div><div id="eonAlerts"><p class="hint">Waking the brain…</p></div></div>
        </div>
      </div>

      <div class="card mb" id="eonAsk"><div class="card-head"><span class="sec-title">Ask EON</span><span class="spacer"></span><span class="hint">answers inline, grounded in this cohort's live numbers</span></div>
        <div style="display:flex;gap:8px"><input id="eonAskIn" type="text" placeholder="e.g. who is at risk? · what should I re-teach? · CO attainment?" style="flex:1;height:38px;border:1px solid var(--line);border-radius:10px;padding:0 13px;outline:0">
          <button class="btn grad" id="eonAskGo">${icon('send', 'sm')} Ask</button></div>
        <div class="pills" style="margin-top:10px">${['Who is at risk?', 'What should I re-teach?', 'CO attainment', 'Upcoming exams', 'Integrity flags', 'How am I doing?'].map(q => `<button class="pill" data-q="${q}">${q}</button>`).join('')}</div>
        <div id="eonAskOut" style="margin-top:12px"></div>
      </div>`;

    /* ask wiring — inline answers, never a popup */
    const out = document.querySelector('#eonAskOut');
    const ask = (q) => {
      if (!q) return;
      let a = null;
      try { a = window.TeacherQA && TeacherQA.answer(q); } catch (e) { /* fall through */ }
      if (!a) a = 'I have limited data on that one — I\'m still learning this cohort. Try me on risk, re-teach targets, attainment, deadlines or a student\'s name.';
      out.insertAdjacentHTML('afterbegin', `<div class="panel" style="border:1px solid color-mix(in oklab,var(--border) 60%,transparent);background:color-mix(in oklab,var(--muted) 20%,transparent);border-radius:12px;padding:11px 13px;margin-bottom:8px">
        <div style="font-size:11px;color:var(--muted-foreground);margin-bottom:4px">You asked: ${esc(q)}</div><div style="font-size:13.5px;line-height:1.6">${a}</div></div>`);
    };
    document.querySelector('#eonAskGo').onclick = () => { const i = document.querySelector('#eonAskIn'); ask(i.value.trim()); i.value = ''; };
    document.querySelector('#eonAskIn').addEventListener('keydown', e => { if (e.key === 'Enter') { ask(e.target.value.trim()); e.target.value = ''; } });
    document.querySelectorAll('#eonAsk [data-q]').forEach(b => b.onclick = () => ask(b.dataset.q));

    /* live reasoning trace — real numbers, cycling like a meditation pass */
    const traceBox = document.querySelector('#eonTrace');
    const stateChip = document.querySelector('#eonState');
    const flagged = A.flagged();
    const fc = A.forecast('CSE311', 'A');
    const wk = A.topics('CSE311', 'A')[0];
    const nxt = T.upcoming.map(u => ({ ...u, d: daysUntil(u.date) })).sort((a, b) => a.d - b.d)[0];
    const steps = [
      ['read', `reading store — ${T.students.length} students · ${T.assessments.length} assessments · ${T.teacherOpps.length} opportunities`],
      ['schema', `discovery — 6 entities inferred, no config needed (courses · students · assessments · advising · opportunities · classes)`],
      ['deadline', `deadline scan — next: “${nxt ? nxt.title : '—'}” in ${nxt ? nxt.d : '—'}d (${nxt ? nxt.course + ' Sec ' + nxt.section : ''})`],
      ['risk', `risk model — ${flagged.length} students flagged · top: ${flagged[0] ? flagged[0].s.name + ' (' + flagged[0].r.score + ')' : '—'}`],
      ['topics', `topic diagnostics — weakest: “${wk ? wk.topic : '—'}” at ${wk ? pct(wk.rate) : '—'}% in CSE311 Sec A`],
      ['mc', `monte-carlo ×500 — CSE311 Sec A heading to ${fc.passRate}% pass · ${fc.avg}% average`],
      ['integrity', `integrity sweep — ${(T.integrity && T.integrity.scripts || []).length} scripts analysed · 2 high-priority cases awaiting review`],
      ['idle', `cycle complete — watching for changes`],
    ];
    let si = 0, cycle = 1;
    const stamp = () => new Date().toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    function tick() {
      if (!document.body.contains(traceBox)) return;           // page swapped
      const [k, msg] = steps[si];
      traceBox.insertAdjacentHTML('beforeend', `<div><span style="color:var(--text-faint)">${stamp()}</span> <span style="color:var(--primary-700)">[${k}]</span> ${esc(msg)}</div>`);
      traceBox.scrollTop = traceBox.scrollHeight;
      stateChip.className = 'chip ' + (k === 'idle' ? 'slate' : 'green');
      stateChip.innerHTML = `<span style="width:6px;height:6px;border-radius:99px;background:currentColor;display:inline-block"></span> ${k === 'idle' ? 'idle · cycle ' + cycle : 'working'}`;
      si++;
      if (si >= steps.length) { si = 0; cycle++; setTimeout(tick, 14000); return; }
      setTimeout(tick, 900 + Math.random() * 700);
    }
    tick();

    /* live alerts — real EonBrain when the companion has booted, seed fallback meanwhile */
    function renderAlerts() {
      const box = document.querySelector('#eonAlerts');
      if (!box) return;
      let items = [];
      try { if (window.EonBrain && EonBrain.getAlerts) items = (EonBrain.getAlerts() || []).slice(0, 5).map(a => ({ t: a.title || a.label || a.message, d: a.due || a.when || '', link: a.link })); } catch (e) { /* seed */ }
      if (!items.length) {
        items = T.upcoming.map(u => ({ ...u, d: daysUntil(u.date) })).sort((a, b) => a.d - b.d).slice(0, 3)
          .map(u => ({ t: `${u.title} — ${u.course} Sec ${u.section}`, d: u.d + 'd' }));
        items.push({ t: 'Advising follow-up — Rakib Rahman (risk 93)', d: '2d' });
      }
      box.innerHTML = items.map(a => `<div style="display:flex;gap:9px;align-items:center;padding:7px 0;border-bottom:1px solid var(--line-2)">
        <span style="color:var(--warning)">${icon('bell', 'sm')}</span><span style="flex:1;font-size:13px">${esc(a.t)}</span><span class="chip amber">${esc(String(a.d))}</span></div>`).join('');
    }
    renderAlerts();
    setInterval(renderAlerts, 8000);
  }

  const strip = () => `
    <div class="eon-strip" id="eonStrip" style="top:auto;position:static">
      ${CS.map((cs, i) => `<button class="pill ${i === scope ? 'on' : ''}" data-i="${i}">${cs[0]} · Sec ${cs[1]}</button>`).join('')}
    </div>`;

  function layerCard(no, title, sub, body) {
    return `<div class="card mb" id="l${no}"><div class="card-head"><span class="layer-badge">LAYER ${no}</span><b>${title}</b><span class="spacer"></span><span class="hint">${sub}</span></div>${body}</div>`;
  }

  function draw() {
    const [course, section] = CS[scope];
    const c = T.course(course);
    const roster = T.enrolled[course][section];

    /* ── Layer 1: gaps ── */
    const topics = A.topics(course, section);
    const weakest = topics[0];
    const cos = A.cos(course, section).filter(x => x.attain != null);
    const under = cos.filter(x => x.attain < x.target);
    const errs = A.errors(course, section);
    const errTot = errs.reduce((s, e) => s + e.n, 0) || 1;
    const l1 = `
      ${weakest ? `<div class="eonline">Weakest topic for this section: <b style="text-transform:capitalize">${esc(weakest.topic)}</b> at a class-wide <b>${pct(weakest.rate)}%</b> correct-rate — that's a re-teach signal for the whole room, not a few weak students.</div>` : ''}
      <div class="grid g2" style="margin-top:10px">
        <div>
          <div class="sec-title" style="margin-bottom:8px">Topic correct-rate</div>
          ${topics.map(t => `<div style="display:flex;align-items:center;gap:10px;margin:5px 0">
            <span style="flex:0 0 160px;font-size:12px;text-transform:capitalize">${esc(t.topic)}</span>
            <span class="bar" style="flex:1"><i style="width:${pct(t.rate)}%;background:${t.rate < 0.5 ? 'var(--red)' : t.rate < 0.68 ? 'var(--amber)' : 'var(--green)'}"></i></span>
            <b class="num" style="min-width:36px;text-align:right;font-size:12px">${pct(t.rate)}%</b></div>`).join('')}
        </div>
        <div>
          <div class="sec-title" style="margin-bottom:8px">CO attainment vs ${c.coTarget}%</div>
          ${cos.map(x => `<div style="display:flex;align-items:center;gap:10px;margin:6px 0">
            <b class="num" style="flex:0 0 40px">${x.co}</b>
            <span class="bar" style="flex:1"><i style="width:${x.attain}%;background:${x.attain >= x.target ? 'var(--green)' : 'var(--red)'}"></i></span>
            <span class="chip ${x.attain >= x.target ? 'green' : 'red'}">${x.attain}%</span></div>`).join('')}
          <div class="sec-title" style="margin:12px 0 8px">Why marks were lost</div>
          <div style="display:flex;gap:7px;flex-wrap:wrap">${errs.map(e => `<span class="chip ${e.kind.startsWith('conceptual') ? 'red' : e.kind.startsWith('careless') ? 'amber' : 'slate'}">${esc(e.kind)} · ${pct(e.n / errTot)}%</span>`).join('')}</div>
          ${errs[0] && errs[0].kind === 'conceptual gap' ? `<div class="eonline" style="margin-top:8px">Most lost marks are <b>conceptual</b>, not careless — more practice sheets won't fix it; re-explanation will.</div>` : ''}
        </div>
      </div>`;

    /* ── Layer 2: why ── */
    const corr = A.correlations(course, section);
    const flagged = roster.map(s => ({ s, r: A.risk(s) })).filter(x => x.r.tier !== 'green').sort((x, y) => y.r.score - x.r.score);
    const l2 = `
      ${corr.map(x => `<div class="eonline">Students who <b>${esc(x.what)}</b> average <b>${x.with_}%</b> vs <b>${x.without}%</b> for those who don't — a <b style="color:${x.gap >= 8 ? 'var(--red)' : 'var(--amber)'}">${x.gap}-point gap</b>.</div>`).join('')}
      ${corr[0] && corr[0].what.includes('notes') ? `<div class="eonline">Translation: the posted notes ARE working — the problem is who isn't opening them. Nudge the non-readers by name, don't post more material.</div>` : ''}
      <div class="sec-title" style="margin:14px 0 8px">Early-warning list (attendance + lateness + trajectory, before failing)</div>
      ${flagged.length ? `<table class="dt">${flagged.map(({ s, r }) => `<tr>
        <td><span class="risk-dot" style="background:var(--${r.tier})"></span><b>${esc(s.name)}</b> <small class="hint">${s.id}</small></td>
        <td><small class="hint">att ${pct(s.attendanceRate)}% · late ${pct(s.lateSubRate)}% · trend ${r.trend >= 0 ? '+' : ''}${pct(r.trend)}%</small></td>
        <td><span class="chip ${r.tier}">risk ${r.score}</span></td></tr>`).join('')}</table>` : '<p class="hint">No early-warning flags in this section.</p>'}`;

    /* ── Layer 3: forecast ── */
    const fc = A.forecast(course, section);
    const failing = flagged.filter(x => x.r.score >= 55);
    const prev = T.kpiHistory[2];
    const l3 = `
      <div class="stats" style="margin-bottom:10px">
        <div class="stat"><div class="ic t-green">${icon('square-check','lg')}</div><div class="v">${fc.passRate}%</div><div class="l">Projected pass rate (Monte-Carlo, ${100 - fc.weightDone}% of grade left)</div></div>
        <div class="stat"><div class="ic t-primary">Σ</div><div class="v">${fc.avg}%</div><div class="l">Projected final average</div></div>
        <div class="stat"><div class="ic t-red">${icon('trending-down','lg')}</div><div class="v">${failing.length}</div><div class="l">Trending toward failure</div></div>
      </div>
      ${failing.length ? `<div class="eonline">Ranked by urgency: ${failing.map(x => `<b>${esc(x.s.name)}</b> (${x.r.score})`).join(' · ')} — each needs an intervention BEFORE the final, while ${100 - fc.weightDone}% of the grade is still open.</div>` : ''}
      <div class="eonline">Department pace: this batch is tracking ${fc.avg >= Math.round(prev.gpaAvg / 4 * 100) ? '<b style="color:var(--green)">ahead of</b>' : '<b style="color:var(--red)">behind</b>'} the previous batch at the same point (prev GPA ${prev.gpaAvg} ≈ ${Math.round(prev.gpaAvg / 4 * 100)}%).</div>`;

    /* ── Layer 4: actions ── */
    const remTopic = weakest && weakest.rate < 0.62 ? weakest : null;
    const nextRes = T.resources.filter(r => r.course === course && !r.published)[0];
    const l4 = `
      ${remTopic ? `<div class="eonline"><b>Class-wide:</b> re-cover <b style="text-transform:capitalize">${esc(remTopic.topic)}</b> before the next assessment — it's the lowest-scoring topic for this section (${pct(remTopic.rate)}%). One session moves both the class average AND the CO metric.</div>` : '<div class="eonline"><b>Class-wide:</b> no topic is below the re-teach threshold for this section — hold course.</div>'}
      ${nextRes ? `<div class="eonline"><b>Resource:</b> publish “${esc(nextRes.title)}” from your drafts — it maps to the weak area.</div>` : ''}
      <div class="sec-title" style="margin:12px 0 8px">Individual intervention list</div>
      ${flagged.slice(0, 5).map(({ s, r }) => {
        const act = r.score >= 60 ? 'Advising meeting this week' : r.score >= 45 ? 'Direct nudge + pair with a strong peer' : 'Watch + share the practice sheet';
        return `<div style="display:flex;gap:10px;align-items:center;padding:7px 0;border-bottom:1px solid var(--line-2)">
          <span class="chip ${r.tier}">${r.score}</span>
          <span style="flex:1"><b>${esc(s.name)}</b> <small class="hint">— ${act}</small></span>
          <button class="btn ghost sm" data-draft="${s.id}">${icon('mail')} Draft</button></div>`;
      }).join('') || '<p class="hint">No interventions needed.</p>'}`;

    /* ── Layer 5: did it work ── */
    const rem = T.remediations[0];
    let l5body = '<p class="hint">Log a remediation (re-teach, resource, advising) and EON tracks whether the next assessment on that topic actually improved.</p>';
    if (rem && course === rem.course) {
      const ct = A.of(rem.course, rem.section).find(a => a.type === 'CT');
      const mid = A.of(rem.course, rem.section).find(a => a.type === 'Midterm');
      const rate = (a) => { const qs = a.questions.filter(q => q.topic === rem.topic); if (!qs.length) return null; return mean(qs.map(q => mean(a.marks.map(r => r.perQ.find(x => x.no === q.no).got)) / q.marks)); };
      const before = ct ? rate(ct) : null, after = mid ? rate(mid) : null;
      if (before != null && after != null) {
        const up = after - before;
        l5body = `
          <div class="eonline"><b>${fmtD(rem.date)}:</b> you ${esc(rem.action.toLowerCase())} <small class="hint">(trigger: ${esc(rem.trigger)})</small></div>
          <div style="display:flex;gap:14px;align-items:center;margin:12px 0">
            <div style="text-align:center"><div class="hint">before (CT)</div><b class="num" style="font-size:26px;color:var(--red)">${pct(before)}%</b></div>
            <div style="font-size:22px;color:var(--text-faint)">→</div>
            <div style="text-align:center"><div class="hint">after (Midterm)</div><b class="num" style="font-size:26px;color:var(--green)">${pct(after)}%</b></div>
            <span class="chip ${up > 0.05 ? 'green' : 'amber'}" style="font-size:13px">${up > 0 ? '+' : ''}${pct(up)} points on “${esc(rem.topic)}” — ${up > 0.05 ? 'the re-teach WORKED ✓' : 'inconclusive'}</span>
          </div>
          <div class="eonline">Same before/after scorecard as the student side — the loop is closed with evidence, not a feeling.</div>`;
      }
    } else if (rem) {
      l5body = `<div class="eonline">Latest closed loop lives in <b>${rem.course} Sec ${rem.section}</b> (“${esc(rem.topic)}” re-teach → midterm lift). Switch scope to see the scorecard.</div>`;
    }

    p.innerHTML = strip() + `
      <div class="eonline" style="margin:2px 0 12px">One brain, many bodies: the same EON that plans one student's week is reading <b>${roster.length} students</b> in <b>${course} Sec ${section}</b> right now. Every number below recomputes when you switch scope.</div>` +
      layerCard(1, 'Where the gaps are', 'cohort diagnostic', l1) +
      layerCard(2, 'Why it\'s happening', 'behaviour ↔ outcome, whole batch', l2) +
      layerCard(3, 'Where this is heading', 'Monte-Carlo, per section', l3) +
      layerCard(4, 'What to do about it', 'prescriptive — class-wide + per student', l4) +
      layerCard(5, 'Did it work', 'closing the loop', l5body);

    document.querySelectorAll('#eonStrip .pill').forEach(b => b.onclick = () => { scope = +b.dataset.i; draw(); });
    document.querySelectorAll('[data-draft]').forEach(b => b.onclick = () => {
      const st = T.student(b.dataset.draft); const r = A.risk(st);
      openModal('Draft intervention — ' + esc(st.name), `<div class="field"><textarea rows="8">Dear ${st.name.split(' ')[0]},

I've been reviewing the section's progress and I want to make sure the term ends well for you. Your recent trajectory (attendance ${pct(st.attendanceRate)}%, average ${pct(r.avg)}%) tells me a 20-minute conversation now is worth more than any amount of catch-up later.

Please see me after class or book an office-hours slot this week.

— ${T.teacher.name}</textarea></div>`,
        `<button class="btn" onclick="toast('Message queued ✓ (demo)');document.getElementById('tOverlay').remove()">Send</button>`);
    });
  }
  renderTop();
  draw();
};
