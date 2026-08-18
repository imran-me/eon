/* ============================================================
   EON · ask — the conversation, in place, on the ERP page.

   Clicking "Ask EON" on an ERP screen opens this: the same brain the
   panel uses, in a bubble that sits over the ERP without disturbing
   it. Everything the panel can do it can do — grounded answers, the
   detail behind the number, buttons that open the exact screen, the
   confirmation gate before EON writes anything, voice in and out.

   It never navigates itself away: answers that open a screen move the
   ERP (or the left frame in the split workspace), and the thread stays
   where it is. "Dock" hands the whole conversation to the workspace.
   ============================================================ */
(function () {
  'use strict';
  if (window.__EON_ASK_UI) return;
  window.__EON_ASK_UI = true;

  const $ = (sel, root) => (root || document).querySelector(sel);
  const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const BASE = (window.EonEmbed && window.EonEmbed.base) || location.origin;

  /* ---------- the shell ---------- */
  const css = `
  #eon-ask-ui{position:fixed;right:18px;bottom:20px;width:390px;max-width:calc(100vw - 24px);height:min(600px,calc(100vh - 60px));
    z-index:2147483640;display:none;flex-direction:column;background:#fff;border:1px solid #e2e7f0;border-radius:16px;
    box-shadow:0 24px 60px rgba(19,26,46,.22);font:14px/1.5 "Inter","Segoe UI",system-ui,sans-serif;color:#131a2e;overflow:hidden}
  #eon-ask-ui.show{display:flex}
  #eon-ask-ui .h{display:flex;align-items:center;gap:8px;padding:10px 12px;border-bottom:1px solid #e2e7f0;background:#fff}
  #eon-ask-ui .h .dot{width:8px;height:8px;border-radius:50%;background:#0f9265;box-shadow:0 0 8px #0f9265}
  #eon-ask-ui .h b{font-size:13.5px;letter-spacing:.2px}
  #eon-ask-ui .h .where{margin-left:auto;font-size:11px;color:#616c8a;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  #eon-ask-ui .h button{border:1px solid #e2e7f0;background:#fff;border-radius:8px;height:26px;padding:0 8px;cursor:pointer;font:600 11.5px inherit;color:#5b6785}
  #eon-ask-ui .h button:hover{background:#f5f7fc}
  #eon-ask-ui .h button#eon-ask-live.on{background:#dc2626;border-color:#dc2626;color:#fff;animation:eon-ask-pulse 1.6s infinite}
  #eon-ask-ui .thread{flex:1;overflow:auto;padding:12px;display:flex;flex-direction:column;gap:10px;background:#fbfcfe}
  #eon-ask-ui .m{max-width:88%;padding:9px 12px;border-radius:14px;white-space:pre-wrap;overflow-wrap:anywhere}
  #eon-ask-ui .m.me{align-self:flex-end;background:linear-gradient(135deg,#4f46e5,#3b6fe0);color:#fff;border-bottom-right-radius:5px}
  #eon-ask-ui .m.eon{align-self:flex-start;background:#fff;border:1px solid #e2e7f0;border-bottom-left-radius:5px}
  #eon-ask-ui .m.think{align-self:flex-start;color:#616c8a;font-style:italic}
  #eon-ask-ui .m .d{margin-top:7px;padding-top:7px;border-top:1px dashed #e8ecf4;color:#5b6785;font-size:12.5px}
  #eon-ask-ui .m .acts{display:flex;flex-wrap:wrap;gap:6px;margin-top:9px}
  #eon-ask-ui .m .acts button{border:1px solid #d7deea;background:#fff;border-radius:8px;padding:5px 10px;cursor:pointer;font:600 12px inherit;color:#4f46e5}
  #eon-ask-ui .m .acts button.go{background:#4f46e5;border-color:#4f46e5;color:#fff}
  #eon-ask-ui .m .acts button:hover{filter:brightness(.97)}
  #eon-ask-ui .chips{display:flex;gap:6px;overflow-x:auto;padding:8px 10px;border-top:1px solid #e2e7f0;background:#fff;scrollbar-width:none}
  #eon-ask-ui .chips::-webkit-scrollbar{display:none}
  #eon-ask-ui .chips span{flex:0 0 auto;border:1px solid #e2e7f0;border-radius:999px;padding:5px 10px;font-size:11.5px;color:#5b6785;cursor:pointer;white-space:nowrap}
  #eon-ask-ui .chips span:hover{background:#f5f7fc;color:#131a2e}
  #eon-ask-ui .bar{display:flex;gap:7px;align-items:center;padding:10px;border-top:1px solid #e2e7f0;background:#fff}
  #eon-ask-ui .bar input{flex:1;min-width:0;border:1px solid #e2e7f0;border-radius:11px;padding:9px 11px;font:inherit;outline:none}
  #eon-ask-ui .bar input:focus{border-color:#4f46e5;box-shadow:0 0 0 3px rgba(79,70,229,.13)}
  #eon-ask-ui .bar button{border:0;border-radius:11px;padding:9px 13px;cursor:pointer;font:600 13px inherit;background:#4f46e5;color:#fff}
  #eon-ask-ui .bar .mic{background:#fff;border:1px solid #e2e7f0;color:#131a2e;width:38px;padding:0;height:38px;border-radius:50%}
  #eon-ask-ui .bar .mic.on{background:#dc2626;border-color:#dc2626;color:#fff;animation:eon-ask-pulse 1.2s infinite}
  @keyframes eon-ask-pulse{0%,100%{box-shadow:0 0 0 0 rgba(220,38,38,.45)}50%{box-shadow:0 0 0 10px rgba(220,38,38,0)}}
  @media (max-width:520px){#eon-ask-ui{right:8px;left:8px;width:auto;bottom:70px;height:min(70vh,520px)}}
  /* the companion's popups must not sit on top of the conversation */
  .eon-ask-open #eon-standup,.eon-ask-open .eon-standup,.eon-ask-open .eon-card,.eon-ask-open .eon-bubble-card,
  .eon-ask-open #eon-nudge,.eon-ask-open .eon-toast{opacity:0!important;pointer-events:none!important;transition:opacity .15s}
  @media print{#eon-ask-ui{display:none!important}}`;

  const style = document.createElement('style');
  style.setAttribute('data-eon', ''); style.textContent = css;
  document.head.appendChild(style);

  const ui = document.createElement('div');
  ui.id = 'eon-ask-ui'; ui.setAttribute('data-eon', '');
  ui.innerHTML = `
    <div class="h"><span class="dot"></span><b>EON</b>
      <span class="where" id="eon-ask-where"></span>
      <button id="eon-ask-live" title="Full-time conversation — EON keeps listening, say “EON …”">◉ Live</button>
      <button id="eon-ask-dock" title="Work with EON beside the ERP">Dock ⇥</button>
      <button id="eon-ask-close" title="Close (Esc)">✕</button></div>
    <div class="thread" id="eon-ask-thread"></div>
    <div class="chips" id="eon-ask-chips"></div>
    <div class="bar">
      <button class="mic" id="eon-ask-mic" title="Talk to EON">🎙</button>
      <input id="eon-ask-input" placeholder="Ask EON…  (Enter to send)" autocomplete="off">
      <button id="eon-ask-send">Ask</button>
    </div>`;
  document.body.appendChild(ui);

  const thread = $('#eon-ask-thread', ui), input = $('#eon-ask-input', ui);

  /* ---------- rendering ---------- */
  function bubble(role, text, extra) {
    const d = document.createElement('div');
    d.className = 'm ' + role;
    d.innerHTML = esc(text);
    if (extra && extra.detail) {
      const det = Array.isArray(extra.detail) ? extra.detail.join('\n') : extra.detail;
      if (det) { const e = document.createElement('div'); e.className = 'd'; e.textContent = det; d.appendChild(e); }
    }
    if (extra && extra.actions && extra.actions.length) {
      const box = document.createElement('div'); box.className = 'acts';
      extra.actions.forEach((a, i) => {
        const b = document.createElement('button');
        b.textContent = a.label; if (i === 0) b.className = 'go';
        b.onclick = () => runAction(a);
        box.appendChild(b);
      });
      d.appendChild(box);
    }
    thread.appendChild(d);
    thread.scrollTop = thread.scrollHeight;
    return d;
  }

  function runAction(a) {
    if (a.kind === 'erp-open' && a.href) { (window.EonNavigator ? window.EonNavigator.go(a.href) : (location.href = a.href)); return; }
    if (a.kind === 'eon-confirm') { send('yes'); return; }
    if (a.href) window.open(a.href, '_blank', 'noopener');
  }

  /* ---------- asking ---------- */
  let busy = false;
  async function send(qRaw) {
    const q = String(qRaw == null ? input.value : qRaw).trim();
    if (!q || busy) return;
    busy = true; input.value = '';
    bubble('me', q);
    const think = bubble('think', 'thinking…');
    try {
      let r = null;
      if (window.EonDomains && window.EonDomains.answer) r = await window.EonDomains.answer(q, {});
      if (!r && window.EonErp && window.EonErp.answer) r = window.EonErp.answer(q);
      think.remove();
      if (!r || !r.speak) bubble('eon', 'I did not catch that one yet. Ask about the business — cash, receivables, payroll, a person by name — or say “where is payroll”.');
      else {
        bubble('eon', r.speak, r);
        if (r.navigate && window.EonNavigator) window.EonNavigator.go(r.navigate);
        try { if (window.EonVoice && !window.EonVoice.muted) window.EonVoice.say(r.speak, { lang: (window.EonApp && window.EonApp.state && window.EonApp.state.lang) || 'en-US' }); } catch {}
      }
    } catch (e) {
      think.remove();
      bubble('eon', 'Something went wrong answering that: ' + e.message);
    }
    busy = false;
    input.focus();
  }

  /* ---------- the quick chips, aware of the screen ---------- */
  const BASE_CHIPS = ['Brief', 'What should I focus on?', 'Cash position', 'Who owes us money?', 'Any accounting error?', 'Last transaction?', 'Who is absent today?', 'Any ticket sale today?'];
  function chips() {
    const box = $('#eon-ask-chips', ui);
    const path = location.pathname;
    const here = [];
    if (/payroll|salar/i.test(path)) here.push('Payroll', 'Which salaries are unpaid?');
    if (/journal|account|ledger/i.test(path)) here.push('Any accounting error?', 'Last transaction?');
    if (/task|board/i.test(path)) here.push('Overdue tasks', 'Who is overloaded?');
    if (/lead|crm/i.test(path)) here.push('Pipeline', 'Which leads went cold?');
    if (/customer|party|invoice|sale/i.test(path)) here.push('Who owes us money?', 'Any ticket sale today?');
    const all = here.concat(BASE_CHIPS).filter((v, i, a) => a.indexOf(v) === i).slice(0, 10);
    box.innerHTML = all.map((c) => `<span>${esc(c)}</span>`).join('');
    box.querySelectorAll('span').forEach((s) => (s.onclick = () => send(s.textContent)));
  }

  /* ---------- open / close ---------- */
  function open(yes) {
    ui.classList.toggle('show', yes !== false);
    document.documentElement.classList.toggle('eon-ask-open', yes !== false);
    if (yes !== false) {
      chips();
      $('#eon-ask-where', ui).textContent = location.pathname.replace(/^\/[^/]+\//, '');
      if (!thread.children.length) bubble('eon', 'I am on this screen with you. Ask about the business, or tell me to do something — “assign Imran a task: check ledger entries”.');
      setTimeout(() => input.focus(), 50);
    }
  }
  window.EonAskUI = { open, send, isOpen: () => ui.classList.contains('show') };

  $('#eon-ask-close', ui).onclick = () => open(false);
  $('#eon-ask-send', ui).onclick = () => send();
  input.addEventListener('keydown', (e) => { if (e.key === 'Enter') send(); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && ui.classList.contains('show')) open(false); });
  $('#eon-ask-dock', ui).onclick = () => {
    const to = encodeURIComponent(location.pathname + location.search);
    location.href = BASE + '/eon/workspace.html?to=' + to;
  };

  /* ---------- voice ---------- */
  const mic = $('#eon-ask-mic', ui);
  mic.onclick = () => {
    const V = window.EonVoice;
    if (!V || !V.available().stt) { bubble('eon', 'This browser has no speech recognition — Chrome or Edge can listen.'); return; }
    if (mic.classList.contains('on')) { V.stop(); mic.classList.remove('on'); return; }
    mic.classList.add('on');
    V.onTranscript((text, meta) => { if (meta && meta.final && text) { mic.classList.remove('on'); send(text); } });
    V.listen({ continuous: false });
  };

  /* ---------- full-time conversation ----------
     EON keeps the microphone open and answers anything addressed to him.
     He stops listening while he speaks, so he never hears himself, and
     picks up again the moment he finishes. Say "stop listening" to end. */
  let live = false;
  const liveBtn = $('#eon-ask-live', ui);
  function setLive(on) {
    const V = window.EonVoice;
    if (!V || !V.available().stt) { bubble('eon', 'This browser cannot listen — Chrome or Edge can.'); return; }
    live = !!on;
    liveBtn.classList.toggle('on', live);
    liveBtn.textContent = live ? '◉ Listening' : '◉ Live';
    if (!live) { V.stop(); return; }
    bubble('eon', 'I am listening. Just talk — say “EON, …” and I will answer, or “stop listening” when you are done.');
    V.wakeWord(true);
    V.onTranscript((text, meta) => {
      if (!live || !meta || !meta.final || !text) return;
      if (/\b(stop listening|that'?s all|thank you eon|থামো|যথেষ্ট)\b/i.test(text)) { setLive(false); return; }
      send(text);
    });
    V.listen({ continuous: true });
  }
  liveBtn.onclick = () => setLive(!live);
  window.EonAskUI.live = setLive;

  /* ---------- take over the companion's chip ---------- */
  function claimChip() {
    const chip = document.getElementById('eon-ask-chip');
    if (chip && !chip.__eonClaimed) {
      chip.__eonClaimed = true;
      chip.addEventListener('click', (e) => { e.preventDefault(); e.stopPropagation(); open(!ui.classList.contains('show')); }, true);
    }
  }
  claimChip();
  new MutationObserver(claimChip).observe(document.documentElement, { childList: true, subtree: true });
})();
