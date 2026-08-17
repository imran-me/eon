/* ==========================================================
   EON FOR TEACHER — Exam Integrity Intelligence (flagship)
   TF-IDF pairwise similarity + same-wrong-answer clustering +
   stylometric baseline deviation + AI-likelihood heuristics +
   seat-proximity cross-reference → case files for HUMAN review.
   Every screen says it plainly: flags, never verdicts.
   ========================================================== */
'use strict';

window.initIntegrityPage = function initIntegrityPage() {
  const p = document.querySelector('#content');
  const I = T.integrity;
  const scripts = I.scripts;
  const N = scripts.length;
  const stOf = (sid) => T.student(sid);

  /* ---------- text machinery ---------- */
  const tok = (s) => String(s).toLowerCase().replace(/[^a-z0-9\s]/g, ' ').split(/\s+/).filter(w => w.length > 1);
  const sentences = (s) => String(s).split(/(?<=[.!?])\s+/).filter(x => x.trim().length > 2);

  /* TF-IDF vectors + cosine similarity matrix */
  const docs = scripts.map(s => tok(s.text));
  const df = {};
  docs.forEach(d => [...new Set(d)].forEach(w => df[w] = (df[w] || 0) + 1));
  const vec = docs.map(d => {
    const tf = {}; d.forEach(w => tf[w] = (tf[w] || 0) + 1);
    const v = {}; let norm = 0;
    Object.entries(tf).forEach(([w, c]) => { const x = (c / d.length) * Math.log(N / df[w]); v[w] = x; norm += x * x; });
    return { v, norm: Math.sqrt(norm) || 1 };
  });
  const sim = (i, j) => {
    let dot = 0; const a = vec[i], b = vec[j];
    const [small, big] = Object.keys(a.v).length < Object.keys(b.v).length ? [a, b] : [b, a];
    Object.entries(small.v).forEach(([w, x]) => { if (big.v[w]) dot += x * big.v[w]; });
    return dot / (a.norm * b.norm);
  };
  const M = [];
  for (let i = 0; i < N; i++) { M[i] = []; for (let j = 0; j < N; j++) M[i][j] = i === j ? 1 : (j < i ? M[j][i] : sim(i, j)); }

  /* shared 5-gram passages (for side-by-side highlighting) */
  function sharedGrams(i, j, n = 5) {
    const g = (d) => { const out = new Set(); for (let k = 0; k <= d.length - n; k++) out.add(d.slice(k, k + n).join(' ')); return out; };
    const A5 = g(docs[i]), B5 = g(docs[j]);
    return [...A5].filter(x => B5.has(x));
  }
  function highlight(text, grams) {
    let html = esc(text);
    const words = [...new Set(grams.flatMap(g => g.split(' ')))].filter(w => w.length > 3);
    if (!grams.length) return html;
    // mark the longest grams first so highlights read as passages
    grams.sort((a, b) => b.length - a.length).slice(0, 6).forEach(g => {
      const rx = new RegExp(g.split(' ').map(w => w.replace(/[.*+?^${}()|[\]\\]/g, '')).join('[^a-z0-9]+'), 'i');
      html = html.replace(rx, m => `<mark style="background:#fde68a;border-radius:3px;padding:0 2px">${m}</mark>`);
    });
    return html;
  }

  /* AI-likelihood heuristics (0–100, with named signals) */
  const TRANSITIONS = ['furthermore', 'consequently', 'moreover', 'in conclusion', 'additionally', 'it is important to note', 'fundamental', 'systematically', 'elegantly'];
  const TYPOS = ['becuase', 'becuse', 'dependancy', 'dependance', 'seperate', 'recieve', 'wich', 'teh', 'dont remember'];
  function aiScore(i) {
    const t = scripts[i].text; const sents = sentences(t); const words = tok(t);
    const lens = sents.map(s => tok(s).length);
    const meanLen = lens.reduce((a, b) => a + b, 0) / (lens.length || 1);
    const variance = lens.length > 1 ? Math.sqrt(lens.map(l => (l - meanLen) ** 2).reduce((a, b) => a + b, 0) / lens.length) : 0;
    const burstiness = variance / (meanLen || 1);                          // low = uniform = AI-ish
    const ttr = new Set(words).size / (words.length || 1);
    const trans = TRANSITIONS.filter(x => t.toLowerCase().includes(x)).length;
    const typos = TYPOS.filter(x => t.toLowerCase().includes(x)).length;
    const signals = [];
    let score = 0;
    if (burstiness < 0.34 && sents.length >= 3) { score += 30; signals.push(`uniform sentence rhythm (burstiness ${burstiness.toFixed(2)})`); }
    if (trans >= 2) { score += 25; signals.push(`${trans} generic transition phrases`); }
    if (typos === 0 && words.length > 45) { score += 15; signals.push('zero informal slips in a long answer'); }
    if (meanLen > 16) { score += 15; signals.push(`long polished sentences (avg ${meanLen.toFixed(0)} words)`); }
    if (ttr > 0.72 && words.length > 45) { score += 15; signals.push('unusually rich vocabulary'); }
    return { score: Math.min(100, score), signals, meanLen, ttr };
  }
  const ai = scripts.map((_, i) => aiScore(i));

  /* stylometric deviation vs each student's own baseline */
  function styloDev(i) {
    const b = I.baselines[scripts[i].sid]; if (!b) return { dev: 0 };
    const dLen = Math.abs(ai[i].meanLen - b.avgSentLen) / (b.avgSentLen || 1);
    const dTtr = Math.abs(ai[i].ttr - b.ttr) / (b.ttr || 1);
    return { dev: Math.round((dLen * 0.65 + dTtr * 0.35) * 100), base: b };
  }
  const stylo = scripts.map((_, i) => styloDev(i));

  /* seat proximity */
  const seatDist = (a, b) => { const s1 = I.seatMap[a], s2 = I.seatMap[b]; if (!s1 || !s2) return 99; return Math.abs(s1.row - s2.row) + Math.abs(s1.col - s2.col); };

  /* ---------- case building ---------- */
  const cases = [];
  for (let i = 0; i < N; i++) for (let j = i + 1; j < N; j++) {
    const s = M[i][j]; if (s < 0.30) continue;   // low bar on purpose: LOW-tier cases give contrast (innocent similarity exists!)
    const a = scripts[i], b = scripts[j];
    const sameWrong = a.finalAnswer === b.finalAnswer && a.finalAnswer !== I.correctAnswer;
    const dist = seatDist(a.sid, b.sid);
    const adjacent = dist <= 1;
    const bothCorrect = a.finalAnswer === I.correctAnswer && b.finalAnswer === I.correctAnswer;
    let tier = 'Low', why = [];
    why.push(`text similarity ${pct(s)}%`);
    if (sameWrong) why.push(`IDENTICAL WRONG final answer (“${a.finalAnswer}”)`);
    if (adjacent) why.push('seated adjacent in the exam room');
    else if (dist < 99) why.push(`seated ${dist} seats apart${s > 0.7 ? ' — similarity without proximity points to a shared source, not peeking' : ''}`);
    if (bothCorrect && !adjacent) why.push('both answers CORRECT — consistent with standard textbook phrasing');
    if (s >= 0.62 && (sameWrong || adjacent)) tier = 'High';
    else if (s >= 0.62 || sameWrong) tier = 'Medium';
    else if (s >= 0.5) tier = 'Medium';
    // fairness rule: two CORRECT answers matching is weak evidence (textbook
    // phrasing legitimately converges) — demote one tier, never High.
    if (bothCorrect) tier = tier === 'High' ? 'Medium' : 'Low';
    cases.push({ i, j, s, sameWrong, adjacent, dist, bothCorrect, tier, why });
  }
  cases.sort((a, b) => ({ High: 3, Medium: 2, Low: 1 }[b.tier] - { High: 3, Medium: 2, Low: 1 }[a.tier]) || b.s - a.s);
  const aiFlagged = ai.map((x, i) => ({ i, ...x, stylo: stylo[i] })).filter(x => x.score >= 55).sort((a, b) => b.score - a.score);

  /* review state (human-in-the-loop) persists locally */
  const reviewKey = 'eft-review';
  const review = JSON.parse(localStorage.getItem(reviewKey) || '{}');
  const setReview = (id, v) => { review[id] = v; localStorage.setItem(reviewKey, JSON.stringify(review)); drawCases(); };

  /* ---------- render ---------- */
  const heatColor = (v) => v >= 0.75 ? '#d6453d' : v >= 0.6 ? '#ef8f61' : v >= 0.45 ? '#f5c97b' : v >= 0.3 ? '#dfe6f5' : '#f3f5fa';
  const label = (i) => initials(stOf(scripts[i].sid).name) + '·' + scripts[i].sid.slice(-2);

  p.innerHTML = `
    <div class="card" style="border-left:4px solid var(--amber);background:var(--amber-soft)">
      <b>${icon('shield')} Flags, never verdicts.</b>
      <div style="font-size:12.5px;margin-top:4px;color:#6b4d09">Everything below is <b>flagged for your review</b> — similarity has innocent explanations (shared lectures, textbook phrasing, group study). The tool assembles evidence and you decide: <b>Cleared</b>, <b>Needs discussion</b>, or <b>Confirmed</b>. No score on this page is an accusation.</div>
    </div>

    <div class="stats" style="margin-top:16px">
      <div class="stat"><div class="ic t-primary">${icon('file-text','lg')}</div><div class="v">${N}</div><div class="l">Scripts analysed — ${esc(I.exam)}</div></div>
      <div class="stat"><div class="ic t-amber">${icon('activity','lg')}</div><div class="v">${cases.length}</div><div class="l">Pairs above similarity threshold</div></div>
      <div class="stat"><div class="ic t-red">${icon('flag','lg')}</div><div class="v">${cases.filter(c => c.tier === 'High').length}</div><div class="l">High-priority reviews</div></div>
      <div class="stat"><div class="ic t-violet">${icon('cpu','lg')}</div><div class="v">${aiFlagged.length}</div><div class="l">AI-likelihood flags</div></div>
      <div class="stat"><div class="ic t-blue">${icon('check','lg')}</div><div class="v">${scripts.filter(s => s.finalAnswer === I.correctAnswer).length}/${N}</div><div class="l">Correct final answers (${I.correctAnswer})</div></div>
    </div>

    <div class="grid g21">
      <div class="card"><div class="card-head"><span class="sec-title">Pairwise similarity heatmap</span><span class="spacer"></span><span class="hint">TF-IDF cosine · click any cell</span></div>
        <div style="overflow:auto"><table class="heat"><tr><th></th>${scripts.map((_, i) => `<th>${label(i).split('·')[1]}</th>`).join('')}</tr>
        ${scripts.map((_, i) => `<tr><th style="text-align:right;padding-right:5px">${label(i)}</th>${scripts.map((_, j) => i === j
          ? '<td class="self"></td>'
          : `<td style="background:${heatColor(M[i][j])}" data-i="${i}" data-j="${j}" title="${label(i)} × ${label(j)} — ${pct(M[i][j])}%">${M[i][j] >= 0.42 ? pct(M[i][j]) : ''}</td>`).join('')}</tr>`).join('')}
        </table></div>
        <div class="hint" style="margin-top:8px">Warm cells = high textual overlap. The two hot clusters you can see are the engineered review cases.</div>
      </div>

      <div class="card"><div class="card-head"><span class="sec-title">AI-likelihood flags</span><span class="spacer"></span><span class="hint">heuristics, not a detector</span></div>
        ${aiFlagged.length ? aiFlagged.map(x => { const st = stOf(scripts[x.i].sid); return `
          <div style="border:1px solid var(--line);border-radius:12px;padding:11px 13px;margin-bottom:10px">
            <div style="display:flex;gap:9px;align-items:center"><b>${esc(st.name)}</b><small class="hint">${st.id}</small><span class="spacer" style="margin-left:auto"></span><span class="chip violet">AI-likelihood ${x.score}</span></div>
            <div style="font-size:12px;color:var(--text-soft);margin-top:6px">${x.signals.map(s => `• ${esc(s)}`).join('<br>')}</div>
            ${x.stylo.dev > 45 ? `<div class="eonline" style="margin-top:7px">Stylometric clash: this script runs <b>${x.meanLen.toFixed(0)}-word polished sentences</b>, but ${esc(st.name.split(' ')[0])}'s own baseline is <b>${x.stylo.base.avgSentLen.toFixed(0)} words</b>${x.stylo.base.note ? ` (${esc(x.stylo.base.note.toLowerCase())})` : ''} — a <b>${x.stylo.dev}% deviation from THEIR OWN writing</b>, which is the fairer comparison.</div>` : ''}
            <button class="btn ghost sm" style="margin-top:8px" data-view="${x.i}">Read script</button>
          </div>`; }).join('') : '<p class="hint">No script crosses the AI-likelihood threshold.</p>'}
      </div>
    </div>

    <div class="card mb"><div class="card-head"><span class="sec-title">Case files — ranked for review</span><span class="spacer"></span><span class="hint">${cases.length} cases · evidence, side-by-side text, your decision</span></div>
      <div id="caseList"></div>
    </div>`;

  function drawCases() {
    document.querySelector('#caseList').innerHTML = cases.map((c, idx) => {
      const a = scripts[c.i], b = scripts[c.j];
      const sa = stOf(a.sid), sb = stOf(b.sid);
      const rid = a.sid + '|' + b.sid;
      const rv = review[rid];
      const tierChip = c.tier === 'High' ? 'red' : c.tier === 'Medium' ? 'amber' : 'slate';
      return `<div style="border:1px solid var(--line);border-left:4px solid var(--${c.tier === 'High' ? 'red' : c.tier === 'Medium' ? 'amber' : 'slate'});border-radius:12px;padding:12px 15px;margin-bottom:10px">
        <div style="display:flex;gap:9px;align-items:center;flex-wrap:wrap">
          <span class="chip ${tierChip}">${c.tier} priority</span>
          <b>${esc(sa.name)}</b> <span class="hint">×</span> <b>${esc(sb.name)}</b>
          <span class="chip blue">similarity ${pct(c.s)}%</span>
          ${c.sameWrong ? `<span class="chip red">same wrong answer “${a.finalAnswer}”</span>` : ''}
          ${c.adjacent ? '<span class="chip red">seat-adjacent</span>' : c.dist < 99 ? `<span class="chip slate">${c.dist} seats apart</span>` : ''}
          ${c.bothCorrect ? '<span class="chip green">both correct — likely innocent</span>' : ''}
          <span class="spacer" style="margin-left:auto"></span>
          ${rv ? `<span class="chip ${rv === 'Cleared' ? 'green' : rv === 'Confirmed' ? 'red' : 'amber'}">${rv} ✓</span>` : ''}
          <button class="btn soft sm" data-case="${idx}">Open case file</button>
        </div>
        <div class="hint" style="margin-top:6px">${c.why.map(esc).join(' · ')}</div>
      </div>`;
    }).join('') || '<p class="hint">No pairs above the review threshold.</p>';
    document.querySelectorAll('[data-case]').forEach(btn => btn.onclick = () => openCase(cases[+btn.dataset.case]));
  }

  function openCase(c) {
    const a = scripts[c.i], b = scripts[c.j];
    const sa = stOf(a.sid), sb = stOf(b.sid);
    const grams = sharedGrams(c.i, c.j);
    const rid = a.sid + '|' + b.sid;
    openModal(`Case file — ${esc(sa.name)} × ${esc(sb.name)} <span class="chip ${c.tier === 'High' ? 'red' : c.tier === 'Medium' ? 'amber' : 'slate'}" style="margin-left:6px">${c.tier}</span>`, `
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px">
        <span class="chip blue">similarity ${pct(c.s)}%</span>
        <span class="chip ${a.finalAnswer === I.correctAnswer ? 'green' : 'red'}">${esc(sa.name.split(' ')[0])}: “${a.finalAnswer}”</span>
        <span class="chip ${b.finalAnswer === I.correctAnswer ? 'green' : 'red'}">${esc(sb.name.split(' ')[0])}: “${b.finalAnswer}”</span>
        <span class="chip outline">correct: “${I.correctAnswer}”</span>
        ${c.adjacent ? '<span class="chip red">adjacent seats — in-room signal</span>' : c.dist < 99 ? `<span class="chip slate">${c.dist} seats apart — points to shared source if similar</span>` : ''}
      </div>
      <div class="grid g2">
        <div><div class="sec-title" style="margin-bottom:6px">${esc(sa.name)} <span class="hint">(${a.sid} · seat R${I.seatMap[a.sid]?.row}C${I.seatMap[a.sid]?.col})</span></div>
          <div style="border:1px solid var(--line);border-radius:10px;padding:11px;font-size:12.5px;line-height:1.65">${highlight(a.text, grams)}</div></div>
        <div><div class="sec-title" style="margin-bottom:6px">${esc(sb.name)} <span class="hint">(${b.sid} · seat R${I.seatMap[b.sid]?.row}C${I.seatMap[b.sid]?.col})</span></div>
          <div style="border:1px solid var(--line);border-radius:10px;padding:11px;font-size:12.5px;line-height:1.65">${highlight(b.text, grams)}</div></div>
      </div>
      <div class="hint" style="margin-top:10px">Highlighted passages are word-for-word 5-gram overlaps. ${c.bothCorrect ? 'Both being CORRECT with textbook phrasing is often innocent — weigh accordingly.' : c.sameWrong ? 'An identical WRONG final answer is the strongest signal here — correct answers can coincide; identical mistakes rarely do.' : ''}</div>
      <div class="card" style="background:var(--amber-soft);border-color:transparent;margin-top:12px;padding:10px 14px;font-size:12px;color:#6b4d09"><b>Your decision, not the tool's:</b> record the outcome after you've spoken with the students.</div>`,
      `<button class="btn ghost sm" data-rv="Cleared">✓ Cleared</button>
       <button class="btn soft sm" data-rv="Needs discussion">${icon('message-square')} Needs discussion</button>
       <button class="btn danger sm" data-rv="Confirmed">Confirmed</button>`);
    document.querySelectorAll('[data-rv]').forEach(btn => btn.onclick = () => { setReview(rid, btn.dataset.rv); toast(`Marked “${btn.dataset.rv}” ✓`); document.getElementById('tOverlay').remove(); });
  }

  document.querySelectorAll('.heat td[data-i]').forEach(td => td.onclick = () => {
    const i = +td.dataset.i, j = +td.dataset.j;
    const c = cases.find(x => (x.i === i && x.j === j) || (x.i === j && x.j === i));
    if (c) openCase(c); else toast(`${label(i)} × ${label(j)}: ${pct(M[i][j])}% — below review threshold`);
  });
  document.querySelectorAll('[data-view]').forEach(b => b.onclick = () => {
    const i = +b.dataset.view; const st = stOf(scripts[i].sid);
    openModal('Script — ' + esc(st.name), `<div style="border:1px solid var(--line);border-radius:10px;padding:12px;font-size:13px;line-height:1.7">${esc(scripts[i].text)}</div>
      <div class="hint" style="margin-top:8px">Final answer: “${scripts[i].finalAnswer}” (correct: “${I.correctAnswer}”)</div>`);
  });
  drawCases();
};
