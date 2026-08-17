/* ==========================================================
   EON FOR TEACHER — Notice Management (assets/js/notices.js)
   AI notice composer: one line in → a formal, editable notice
   draft assembled deterministically from templates + the seed
   (course / section / dates). Publish appends to Recent
   notices; persistence via localStorage ('eft-notices').
   ========================================================== */
'use strict';
(function () {

  /* seeded recent notices — verbatim from the spec */
  const SEED_NOTICES = [
    { title: 'Mid-term exam schedule published', time: '2h ago', tag: 'Exam' },
    { title: 'AI workshop registration open', time: 'Yesterday', tag: 'Event' },
    { title: 'Reward cycle ends Friday', time: '2d ago', tag: 'KPI' },
  ];

  const CATS = ['Exam', 'Result', 'Event', 'General'];
  const NKEY = 'eft-notices';
  const loadN = () => { try { return JSON.parse(localStorage.getItem(NKEY) || '[]'); } catch (e) { return []; } };
  const saveN = (n) => localStorage.setItem(NKEY, JSON.stringify(n.slice(0, 15)));

  window.initNoticesPage = function () {
    const p = $('#content');
    let cat = 'Exam';

    /* ---------- deterministic composer (templates + T) ---------- */
    const signature = `Best regards,\n${T.teacher.name}\n${T.teacher.designation}, Department of ${T.teacher.department}\n${T.teacher.institution}`;
    function detail(category) {
      if (category === 'Exam') {
        const u = T.upcoming[0];
        return ` The affected assessment is ${u.title} (${u.course}, Section ${u.section}, weight ${u.weight}%), currently scheduled for ${fmtD(u.date)}; any revised date will be posted on the portal and announced in class.`;
      }
      if (category === 'Result') {
        const a = T.assessments.slice().sort((x, y) => y.date.localeCompare(x.date))[0];
        return ` Marks for ${a.title} (${a.course}, Section ${a.section}) have been finalised and will be visible under Results & Grading; re-check requests are accepted within three working days.`;
      }
      if (category === 'Event') {
        const s = SCHEDULE[0];
        return ` The session will take place in Room ${s.room}; seats are limited, so please register with the department office in advance.`;
      }
      return '';
    }
    function compose(input, category) {
      return `Dear Students,\n\nThis is to inform you that ${input}.${detail(category)} Please plan accordingly and contact the department office for any clarifications.\n\n${signature}`;
    }
    const deriveTitle = (input) => {
      let t = input.trim().replace(/\s+/g, ' ');
      t = t.charAt(0).toUpperCase() + t.slice(1);
      return t.length > 64 ? t.slice(0, 61).trimEnd() + '…' : t;
    };

    /* ---------- render ---------- */
    p.innerHTML = `
      <div class="grid g21">
        <div class="card" style="align-self:start">
          <div class="card-head"><span style="color:var(--cyan-deep);display:inline-flex">${icon('sparkles')}</span><span class="sec-title" style="font-size:14px">AI notice composer</span><span class="ai-label">${icon('sparkles', 'sm')} Deterministic draft</span></div>
          <div class="pills" id="nCats" style="margin-bottom:10px">${CATS.map(c => `<button class="pill ${c === cat ? 'on' : ''}" data-cat="${c}">${c}</button>`).join('')}</div>
          <div class="field"><textarea id="nInput" rows="4" placeholder="e.g. Exam postponed to next Sunday"></textarea></div>
          <div style="display:flex;justify-content:flex-end;margin-top:12px">
            <button class="btn grad glow" id="nGen">${icon('send', 'sm')} Generate notice</button>
          </div>
          <div id="nDraft" style="display:none;margin-top:16px">
            <div class="card-head"><span class="sec-title">Draft — review &amp; edit before publishing</span><span class="spacer"></span><span class="chip outline" id="nDraftTag">${cat}</span></div>
            <div class="field"><textarea id="nPreview" rows="12" style="font-size:13px;line-height:1.6"></textarea></div>
            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px">
              <button class="btn ghost sm" id="nRegen">${icon('refresh-cw', 'sm')} Regenerate</button>
              <button class="btn sm" id="nPublish">${icon('check', 'sm')} Publish to sections</button>
            </div>
          </div>
        </div>
        <div class="card" style="align-self:start">
          <div class="card-head"><span class="sec-title" style="font-size:14px">Recent notices</span><span class="spacer"></span><span class="hint" id="nCount"></span></div>
          <div id="nList"></div>
        </div>
      </div>`;

    /* ---------- recent notices list ---------- */
    const drawList = () => {
      const all = [...loadN(), ...SEED_NOTICES];
      $('#nCount').textContent = all.length + ' published';
      $('#nList').innerHTML = all.map((n, i) => `
        <div class="${n.body ? 'rowlink' : ''}" data-n="${i}" style="border:1px solid var(--line);border-radius:12px;padding:12px;margin-bottom:12px;${n.body ? 'cursor:pointer' : ''}">
          <div style="display:flex;justify-content:space-between;align-items:center">
            <span class="chip outline">${esc(n.tag)}</span>
            <small class="hint" style="font-size:10px">${esc(n.time)}</small>
          </div>
          <div style="margin-top:8px;font-size:14px"><b>${esc(n.title)}</b></div>
          ${n.body ? `<small class="hint">Published by you · click to view</small>` : ''}
        </div>`).join('');
      $$('#nList [data-n]').forEach(el => el.onclick = () => {
        const n = all[+el.dataset.n];
        if (!n.body) { toast('Departmental notice — read-only in this demo'); return; }
        openModal(`${esc(n.title)} <span class="chip outline" style="margin-left:6px">${esc(n.tag)}</span>`,
          `<div style="white-space:pre-wrap;border:1px solid var(--line);border-radius:12px;padding:14px;font-size:13px;line-height:1.65">${esc(n.body)}</div>
           <div class="hint" style="margin-top:8px">Published ${esc(n.time)} to your sections.</div>`,
          `<button class="btn ghost sm" onclick="window.print()">${icon('printer', 'sm')} Print</button>`);
      });
    };

    /* ---------- interactions ---------- */
    $$('#nCats .pill').forEach(b => b.onclick = () => {
      cat = b.dataset.cat;
      $$('#nCats .pill').forEach(x => x.classList.toggle('on', x === b));
      $('#nDraftTag').textContent = cat;
      if ($('#nDraft').style.display !== 'none' && $('#nInput').value.trim()) {
        $('#nPreview').value = compose($('#nInput').value.trim().replace(/[.\s]+$/, ''), cat);
      }
    });

    const generate = () => {
      const raw = $('#nInput').value.trim();
      if (!raw) { toast('Write one line first — the AI drafts the rest'); return; }
      const input = raw.replace(/[.\s]+$/, '');
      const btn = $('#nGen');
      btn.disabled = true;
      btn.innerHTML = `${icon('refresh-cw', 'sm')} Drafting…`;
      setTimeout(() => {
        $('#nDraft').style.display = 'block';
        $('#nDraftTag').textContent = cat;
        $('#nPreview').value = compose(input, cat);
        btn.disabled = false;
        btn.innerHTML = `${icon('send', 'sm')} Generate notice`;
        $('#nPreview').focus();
        toast('Draft composed — edit freely, then publish');
      }, 480);
    };
    $('#nGen').onclick = generate;
    $('#nRegen').onclick = generate;
    $('#nInput').addEventListener('keydown', e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); generate(); } });

    $('#nPublish').onclick = () => {
      const body = $('#nPreview').value.trim();
      const raw = $('#nInput').value.trim();
      if (!body) { toast('Nothing to publish yet'); return; }
      const list = loadN();
      list.unshift({ title: deriveTitle(raw || body.split('\n')[0]), time: 'Just now', tag: cat, body });
      saveN(list);
      drawList();
      $('#nDraft').style.display = 'none';
      $('#nInput').value = '';
      toast('Notice published to your sections ✓');
    };

    drawList();
  };
})();
