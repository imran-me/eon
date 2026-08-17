/* ==========================================================
   EON FOR TEACHER — Teacher Growth & Research (growth.html)
   Research interests, skill development monitoring, publication
   performance (h-index), AI-curated venue suggestions with fit
   scores, and a career roadmap timeline. All numbers hand-seeded.
   ========================================================== */
'use strict';
(function () {

  /* ---------- page seed (verbatim from spec) ---------- */
  const SKILLS = [
    { name: 'AI in Education', v: 85 },
    { name: 'Machine Learning Models', v: 70 },
    { name: 'Curriculum Design', v: 95 },
    { name: 'Data Structures', v: 90 },
  ];
  const PUBS = [
    { title: 'Predictive Analytics in Student Dropout Rates', journal: 'IEEE Access', year: 2025, cites: 12, doi: '10.1109/ACCESS.2025.3412876' },
    { title: 'Enhancing OBE with LLMs', journal: 'Journal of Educational Technology', year: 2024, cites: 45, doi: '10.1007/s11423-024-10289-4' },
    { title: 'Automated Assessment Systems', journal: 'Springer', year: 2023, cites: 89, doi: '10.1007/978-3-031-28776-3_12' },
  ];
  const H_INDEX = 14;
  const INTERESTS = ['Educational Technology', 'Machine Learning', 'Learning Analytics', 'LLMs in Pedagogy', 'OBE & Assessment', 'Database Education'];
  const SUGGESTIONS = [
    { title: 'Call for Papers: AI for Academic Success', venue: 'ICML 2026', deadline: 'Oct 15, 2026', type: 'Conference', fit: 94,
      why: 'Directly extends your dropout-prediction line (IEEE Access 2025) into intervention modelling — the workshop track explicitly invites learning-analytics deployments.' },
    { title: 'Special Issue on RAG Systems in Education', venue: 'Nature Machine Intelligence', deadline: 'Nov 30, 2026', type: 'Journal', fit: 89,
      why: 'Your "Enhancing OBE with LLMs" paper (45 citations) is the strongest match in your profile; a RAG-grounded OBE attainment system would be a natural sequel.' },
    { title: 'Deep Learning for Cognitive Modeling', venue: 'NeurIPS 2026', deadline: 'Sep 20, 2026', type: 'Conference', fit: 86,
      why: 'Bridges your Machine Learning Models skill track with pedagogy — cognitive-state modelling of at-risk students maps onto the data you already collect.' },
  ];
  const ROADMAP = [
    { when: 'Now · Summer 2026', title: 'Lecturer, CSE — consolidate teaching analytics', desc: 'Ship the OBE attainment study on CSE311/CSE220 cohorts; keep the h-index climbing past 14.', state: 'now' },
    { when: 'Q4 2026', title: 'Submit LLM-assisted pedagogy paper', desc: 'Target ICML 2026 (Oct 15) or the NeurIPS cognitive-modeling track (Sep 20) with the improvement-plan generator as the case study.', state: 'next' },
    { when: '2027', title: 'UGC grant execution + Assistant Professor track', desc: 'Run the learning-analytics-for-OBE grant; two journal submissions; h-index target 18.', state: '' },
    { when: '2028', title: 'PhD enrollment — LLMs applied to pedagogy', desc: 'Formalize the research line the AI assistant identified; build the annotated cohort dataset as the thesis backbone.', state: '' },
    { when: '2029', title: 'Lead a Learning Analytics research group', desc: 'Supervise undergraduate RAs (two current students already flagged as candidates), anchor a lab at DIU.', state: '' },
  ];

  /* ---------- tiny local pieces ---------- */
  const bar = (v, h) => `<div style="height:${h || 8}px;border-radius:999px;background:color-mix(in oklab, var(--primary) 20%, transparent);overflow:hidden"><i style="display:block;height:100%;width:${v}%;background:var(--primary);border-radius:999px"></i></div>`;

  window.initGrowthPage = function () {
    const p = $('#content');

    p.innerHTML = `
    <!-- Section 1 — AI hero -->
    <div class="card gradient-border mb" style="border-radius:16px;padding:24px 32px">
      <div style="display:flex;gap:24px;align-items:center;flex-wrap:wrap">
        <div style="width:64px;height:64px;border-radius:999px;background:var(--gradient-primary);display:grid;place-items:center;color:#fff;flex:0 0 64px">${icon('sparkles', 'xl')}</div>
        <div style="flex:1;min-width:280px">
          <h2 style="font-size:20px;font-weight:700;letter-spacing:-.02em;color:#1e293b;margin:0 0 6px">AI Research Assistant Roadmap<span class="ai-label">${icon('sparkles', 'sm')} AI</span></h2>
          <p style="font-size:14px;color:#475569;max-width:768px;line-height:1.6;margin:0">Based on your recent publications in <b style="color:#1e293b">Educational Technology</b> and <b style="color:#1e293b">Machine Learning</b>, your next career milestone should be focusing on <span class="gradient-text" style="font-weight:500">Large Language Models (LLMs) applied to pedagogy</span>. I found 3 highly relevant conferences matching this area.</p>
          <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
            <span class="hint" style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;align-self:center">Research interests</span>
            ${INTERESTS.map(t => `<span class="chip violet">${esc(t)}</span>`).join('')}
          </div>
        </div>
        <button class="btn grad glow" id="genRoadmap" style="border-radius:12px;padding:10px 20px;white-space:nowrap">Generate Full Roadmap</button>
      </div>
    </div>

    <!-- Section 2 — skills | publications -->
    <div class="grid g2">
      <div class="card glass" style="border-radius:16px;padding:24px">
        <div class="card-head" style="margin-bottom:24px"><span style="color:#6366f1">${icon('trending-up')}</span><span class="sec-title" style="color:#1e293b;font-size:14px">Skill Development Monitoring</span></div>
        ${SKILLS.map(s => `<div style="margin-bottom:20px">
          <div style="display:flex;justify-content:space-between;font-size:14px;margin-bottom:6px"><span style="font-weight:500;color:#334155">${esc(s.name)}</span><span style="color:#64748b">${s.v}%</span></div>
          ${bar(s.v)}
        </div>`).join('')}
        <div class="eonline" style="margin-top:2px">EON suggestion: your <b>Machine Learning Models</b> track (70%) is the gap between you and the LLM-pedagogy milestone — the two flagged venues below both close it.</div>
      </div>

      <div class="card glass" style="border-radius:16px;padding:24px">
        <div class="card-head" style="margin-bottom:24px">
          <span style="color:#f59e0b">${icon('award')}</span><span class="sec-title" style="color:#1e293b;font-size:14px">Publication Performance</span>
          <span class="spacer"></span>
          <span class="badge" style="background:#fffbeb;color:#d97706;border-color:#fde68a">h-index: ${H_INDEX}</span>
        </div>
        ${PUBS.map(pub => `<div class="pubrow" style="padding:12px;border-radius:12px;background:#f8fafc;border:1px solid #f1f5f9;margin-bottom:16px;transition:border-color .15s">
          <div style="font-size:14px;font-weight:500;color:#1e293b;line-height:1.35">${esc(pub.title)}</div>
          <div style="display:flex;justify-content:space-between;gap:10px;margin-top:4px;font-size:12px;color:#64748b;flex-wrap:wrap">
            <span style="display:inline-flex;align-items:center;gap:5px">${icon('book-open', 'sm')} ${esc(pub.journal)} (${pub.year})</span>
            <span style="display:inline-flex;align-items:center;gap:5px">${icon('file-text', 'sm')} ${pub.cites} citations</span>
          </div>
          <div style="font-size:11px;color:#94a3b8;margin-top:3px;font-family:var(--font-mono)">DOI: ${esc(pub.doi)}</div>
        </div>`).join('')}
        <div class="eonline">Citations are compounding on the assessment-automation line — <b>89 → 45 → 12</b> follows the normal 3-year citation ramp; the 2025 paper is pacing ahead of both.</div>
      </div>
    </div>

    <!-- Section 3 — suggestions -->
    <div class="card glass mb" style="border-radius:16px;padding:24px">
      <div style="margin-bottom:24px">
        <div style="font-size:18px;font-weight:600;color:#1e293b">Journal &amp; Conference Suggestions<span class="ai-label">${icon('sparkles', 'sm')} AI-curated</span></div>
        <div style="font-size:14px;color:#64748b;margin-top:4px">AI-curated opportunities matching your research profile.</div>
      </div>
      <div class="grid g3">
        ${SUGGESTIONS.map((s, i) => `<div class="sugcard" style="display:flex;flex-direction:column;padding:16px;border-radius:12px;border:1px solid rgba(226,232,240,.6);background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.05);transition:box-shadow .15s">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
            <span class="badge" style="${s.type === 'Conference' ? 'background:#dbeafe;color:#1d4ed8;border-color:#bfdbfe' : 'background:#f3e8ff;color:#7e22ce;border-color:#e9d5ff'}">${s.type}</span>
            <span style="font-size:12px;font-weight:500;color:#ef4444;display:inline-flex;align-items:center;gap:4px">${icon('check', 'sm')} ${esc(s.deadline)}</span>
          </div>
          <div style="font-size:14px;font-weight:600;color:#1e293b;margin-bottom:4px">${esc(s.title)}</div>
          <div style="font-size:12px;color:#64748b;margin-bottom:10px">${esc(s.venue)}</div>
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px">
            <span style="flex:1">${bar(s.fit, 6)}</span>
            <span class="chip ${s.fit >= 90 ? 'green' : 'blue'}">fit ${s.fit}%</span>
          </div>
          <button class="btn ghost sm" data-view="${i}" style="margin-top:auto;width:100%;justify-content:center;border-radius:8px;background:#f8fafc;border-color:#e2e8f0;color:#334155">View Details ${icon('chevron-right', 'sm')}</button>
        </div>`).join('')}
      </div>
    </div>

    <!-- Section 4 — career roadmap timeline -->
    <div class="card glass mb" id="roadmapCard" style="border-radius:16px;padding:24px">
      <div class="card-head" style="margin-bottom:18px"><span style="color:var(--primary)">${icon('graduation-cap')}</span><span class="sec-title" style="color:#1e293b;font-size:14px">Career Roadmap</span><span class="spacer"></span><span class="hint">assembled from your publication profile + skill tracks</span></div>
      <div class="timeline" id="roadmapTl">
        ${ROADMAP.map(r => `<div class="timeline-item">
          <span class="tl-dot" style="background:${r.state === 'now' ? 'var(--green)' : r.state === 'next' ? 'var(--primary)' : '#cbd5e1'}"></span>
          <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:${r.state === 'now' ? 'var(--green)' : '#64748b'}">${esc(r.when)}${r.state === 'now' ? ' · current' : ''}</div>
          <div style="font-size:14px;font-weight:600;color:#1e293b;margin-top:2px">${esc(r.title)}</div>
          <div style="font-size:12.5px;color:#64748b;margin-top:2px;line-height:1.5">${esc(r.desc)}</div>
        </div>`).join('')}
      </div>
    </div>`;

    /* hover polish (spec: pub cards border slate-100→200, suggestion cards shadow-sm→md) */
    $$('.pubrow').forEach(el => { el.onmouseenter = () => el.style.borderColor = '#e2e8f0'; el.onmouseleave = () => el.style.borderColor = '#f1f5f9'; });
    $$('.sugcard').forEach(el => { el.onmouseenter = () => el.style.boxShadow = '0 4px 6px -1px rgba(0,0,0,.1),0 2px 4px -2px rgba(0,0,0,.1)'; el.onmouseleave = () => el.style.boxShadow = '0 1px 2px rgba(0,0,0,.05)'; });

    /* Generate Full Roadmap — simulated analysis, then land on the timeline */
    $('#genRoadmap').onclick = () => {
      const b = $('#genRoadmap');
      b.disabled = true; const orig = b.textContent; b.textContent = 'Analyzing publication profile…';
      setTimeout(() => {
        b.disabled = false; b.textContent = orig;
        const card = $('#roadmapCard');
        card.classList.remove('animate-fade-in'); void card.offsetWidth; card.classList.add('animate-fade-in');
        card.scrollIntoView({ behavior: 'smooth', block: 'start' });
        toast('Roadmap regenerated from 3 publications + 4 skill tracks ✓');
      }, 900);
    };

    /* View Details — venue modal with fit rationale */
    $$('#content [data-view]').forEach(btn => btn.onclick = () => {
      const s = SUGGESTIONS[+btn.dataset.view];
      openModal(`${esc(s.title)} <span class="chip ${s.fit >= 90 ? 'green' : 'blue'}" style="margin-left:6px">fit ${s.fit}%</span>`, `
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px">
          <span class="badge" style="${s.type === 'Conference' ? 'background:#dbeafe;color:#1d4ed8;border-color:#bfdbfe' : 'background:#f3e8ff;color:#7e22ce;border-color:#e9d5ff'}">${s.type}</span>
          <span class="chip outline">${esc(s.venue)}</span>
          <span class="chip red">deadline ${esc(s.deadline)}</span>
        </div>
        <div class="sec-title" style="margin-bottom:6px">Why this matches your profile</div>
        <div style="font-size:13px;line-height:1.6;color:#334155">${esc(s.why)}</div>
        <div class="eonline" style="margin-top:12px">Fit score blends topical overlap with your ${INTERESTS.length} interest areas, citation trajectory and the venue's acceptance history for education-track papers.</div>`,
        `<button class="btn soft sm" onclick="toast('Added to My Opportunities ✓');document.getElementById('tOverlay').remove()">${icon('briefcase', 'sm')} Add to My Opportunities</button>
         <button class="btn sm" onclick="toast('Deadline reminder set ✓');document.getElementById('tOverlay').remove()">${icon('bell', 'sm')} Remind me</button>`);
    });
  };
})();
