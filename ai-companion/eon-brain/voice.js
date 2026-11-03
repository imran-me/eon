/* ============================================================
   EON · voice.js — real voice in and out, in the browser.

   In:  Web Speech API SpeechRecognition (Chrome/Edge/Android;
        Safari partial). Push-to-talk or continuous conversation
        mode with an optional wake word ("EON" / "হেই ইয়ন").
   Out: speechSynthesis with a chosen voice (English or Bangla when
        the OS has one), rate/pitch tuned for a calm executive tone.

   The transcript goes to the same place a typed question goes
   (window.EonAsk / the app's ask handler), and the answer's `speak`
   is read back — voice and text are the same brain.

     const v = window.EonVoice;
     v.available()                → { stt: bool, tts: bool }
     v.onTranscript(fn)           → fn(text, { final, confidence })
     v.onState(fn)                → fn('idle'|'listening'|'thinking'|'speaking'|'error', detail)
     v.listen({ continuous })     → start listening (push-to-talk if !continuous)
     v.stop()                     → stop listening
     v.say(text, { lang, raw })   → speak (returns a promise); raw skips the warmth
     v.setLang('en-US' | 'bn-BD') → recognition + synthesis language
     v.hasVoiceFor('bn')          → can this machine pronounce that language?
     v.voiceReport()              → what is installed, and where Bangla comes from
     v.setTts(url)                → EON's own speech endpoint (default /server/api/tts.php)
     v.serverVoice('auto'|'always'|'never')
     v.wakeWord(true|false)       → require "eon" in continuous mode
   ============================================================ */

const SR = typeof window !== 'undefined' ? (window.SpeechRecognition || window.webkitSpeechRecognition) : null;
const SS = typeof window !== 'undefined' ? window.speechSynthesis : null;

const state = { lang: 'en-US', listening: false, continuous: false, wake: false, rec: null, voices: [], speaking: false, status: 'idle', muted: false, rate: 1.0, pitch: 1.0, sayId: 0,
  tts: '/server/api/tts.php', serverVoice: 'auto', audio: null };   // 'auto' | 'always' | 'never'
const listeners = { transcript: new Set(), state: new Set() };
const emit = (k, ...a) => listeners[k].forEach((fn) => { try { fn(...a); } catch (e) { console.warn('[EON voice] listener', e); } });
const setStatus = (s, detail) => { state.status = s; emit('state', s, detail); };

function loadVoices() { if (!SS) return []; state.voices = SS.getVoices() || []; return state.voices; }
if (SS) { loadVoices(); try { SS.onvoiceschanged = loadVoices; } catch {} }

function pickVoice(lang) {
  const vs = state.voices.length ? state.voices : loadVoices();
  const l = String(lang || state.lang).toLowerCase(); const base = l.split('-')[0];
  const prefer = (v) => (v.lang || '').toLowerCase() === l ? 3 : (v.lang || '').toLowerCase().startsWith(base) ? 2 : 0;
  // the warmest voice the machine has: neural/natural first, then the warm
  // named voices, and the old robotic ones last
  const quality = (v) => (/natural|neural|premium|enhanced/i.test(v.name) ? 1.2 : /google|microsoft (aria|guy|jenny|emma|ryan|sonia|neerja|prabhat|nabanita|tanishaa)/i.test(v.name) ? 1 : 0)
    + (/aria|jenny|emma|ava|sonia|neerja|nabanita|tanishaa|samantha|female/i.test(v.name) ? 0.4 : /zira/i.test(v.name) ? 0.1 : 0)
    + (/david|mark|compact|espeak/i.test(v.name) ? -0.3 : 0);
  return vs.slice().sort((a, b) => (prefer(b) + quality(b)) - (prefer(a) + quality(a)))[0] || null;
}

/* Is there a voice that can actually pronounce this language? A machine with no
   Bangla voice will happily accept Bengali text and read it with an English
   voice — which produces silence or nonsense. Better to know and say so. */
function hasVoiceFor(lang) {
  const base = String(lang || state.lang).toLowerCase().split('-')[0];
  const vs = state.voices.length ? state.voices : loadVoices();
  return vs.some((v) => (v.lang || '').toLowerCase().split('-')[0] === base);
}

/* ------------------------------------------------------------------
   Warmth — the browser half of server/lib/Persona.php.

   Answers the panel computes itself never pass through the server, so
   the same rules live here: an opener ("hmm, let me see…", "আচ্ছা,
   দেখি…") on about two answers in five, a softener before bad news, an
   acknowledgement after an order, a closer now and then. Only at the
   edges, never on a confirmation gate or a refusal, seeded by the text
   (same answer, same words), never the same filler twice running, and
   never twice on one answer — the server's speak is already warm.
   ------------------------------------------------------------------ */
const WARM = {
  think: { en: ['Hmm, let me see…', 'Right, let me check…', 'Okay, here it is…', 'Let me look…', 'Alright…', 'So…'],
           bn: ['হুম, দেখছি…', 'আচ্ছা, দেখি…', 'এই যে…', 'জি, দেখছি…', 'আচ্ছা…', 'হুম…'] },
  ack:   { en: ['Got it.', 'Okay, done.', 'Sure.', 'Right away.', 'Alright.'],
           bn: ['ঠিক আছে।', 'জি, অবশ্যই।', 'আচ্ছা।', 'জি।', 'হয়ে গেছে।'] },
  soft:  { en: ['Hmm, one thing —', 'Okay, heads up —', 'Right, so —', 'Hmm…'],
           bn: ['তবে একটা কথা —', 'হুম, একটু খেয়াল করুন —', 'আচ্ছা, একটা ব্যাপার —', 'তাই তো…'] },
  close: { en: ['Anything else?', 'Want me to dig deeper?', 'Shall I look at anything else?'],
           bn: ['আর কিছু লাগবে?', 'আর কিছু?', 'আরও দেখব?'] },
};
const WARM_ALL = Object.values(WARM).flatMap((k) => [...k.en, ...k.bn]);
const GATE_RX = /\b(say yes|shall i|should i|do you want me|confirm|cannot|can't|could not|unable|not allowed|refus|sign in|i do not know|i don't know|not sure)\b|হ্যাঁ বল|নিশ্চিত কর|করব কি|পোস্ট করব|পারছি না|পারব না|পারিনি|জানি না|সাইন ইন|অনুমতি নেই|[?？]\s*$/i;
const DONE_RX = /\b(done|posted|assigned|created|sent|saved|recorded|opened|opening|marked)\b|সম্পন্ন|করা হয়েছে|দেওয়া হয়েছে|পাঠানো হয়েছে|পাঠিয়েছি|দিয়েছি|খুলছি|খুলে দিলাম/i;
const BAD_RX = /\b(overdue|loss|losing|short|shortfall|warning|risk|behind|late|absent|anomal\w*|deficit|negative|unpaid|gap)\b|বকেয়া|লোকসান|ক্ষতি|ঝুঁকি|ঘাটতি|সতর্ক|দেরি|অনুপস্থিত|গরমিল|বাকি/i;
const warmLast = { open: null, close: null };

// CRC-32 over the UTF-8 bytes — the same seed PHP's crc32() gives Persona, so
// a sentence the server chose to leave plain is left plain here too
const CRC = Array.from({ length: 256 }, (_, n) => { let c = n; for (let k = 0; k < 8; k++) c = c & 1 ? 0xEDB88320 ^ (c >>> 1) : c >>> 1; return c >>> 0; });
function seed(t) { let c = 0xFFFFFFFF; for (const b of new TextEncoder().encode(t)) c = CRC[(c ^ b) & 0xFF] ^ (c >>> 8); return (c ^ 0xFFFFFFFF) >>> 0; }
function warmPick(list, n, slot) { let i = n % list.length; if (list[i] === warmLast[slot]) i = (i + 1) % list.length; return (warmLast[slot] = list[i]); }

export function warmKind(t) {
  if (GATE_RX.test(t)) return 'gate';
  const first = t.split(/(?<=[।.!?])\s+/)[0] || t;
  if (DONE_RX.test(first)) return 'ack';
  if (BAD_RX.test(t)) return 'soft';
  return 'think';
}

export function humanize(text, lang) {
  const t = String(text || '').trim();
  if (t.length < 12) return t;
  const l = lang === 'bn' || lang === 'en' ? lang : (/[\u0980-\u09FF]/.test(t) ? 'bn' : 'en');
  if (WARM_ALL.some((f) => t.startsWith(f) || t.endsWith(f))) return t;   // already warm
  const kind = warmKind(t);
  if (kind === 'gate') return t;
  const h = seed(t), roll = h % 20, n = Math.floor(h / 20);
  const hasLead = /^[^.:।,—…]{1,32}[:,—]/.test(t);                       // "Good news:", "একটা বিষয় বলি,"
  if (roll < 8 && !hasLead) return warmPick(WARM[kind][l], n, 'open') + ' ' + t;
  if (roll < 11 && kind !== 'ack') return t + (/[।.!?…]$/.test(t) ? '' : (l === 'bn' ? '।' : '.')) + ' ' + warmPick(WARM.close[l], n, 'close');
  return t;
}
export function resetWarmth() { warmLast.open = null; warmLast.close = null; }

/* how each language is said: a little slower than the engine's default, and
   pauses where a person breathes — after a sentence, an ellipsis, a dash */
const PACE = { en: { rate: 0.95, pitch: 1.04, gap: 160 }, bn: { rate: 0.9, pitch: 1.0, gap: 220 } };
export function phrases(text) {
  return (String(text).match(/[^.!?।…—]+(?:[.!?।…]+|\s—\s?|—|$)/g) || [text]).map((p) => p.trim()).filter(Boolean);
}

const WAKE = /^\s*(hey |ok |hi )?(eon|ion|eyon|aeon|ইয়ন|ইওন|এওন)[,!.\s]*/i;

/* ------------------------------------------------------------------
   EON's own voice.

   This machine has English voices and nothing else — checked: Windows
   ships David/Mark/Zira, Chrome adds nineteen Google voices and none
   of them is Bengali. So for Bangla the browser is asked to play an
   MP3 that the EON server rendered, instead of pretending it can
   pronounce the script itself.
   ------------------------------------------------------------------ */
function ttsUrl(text, lang) {
  const base = state.tts;
  const sep = base.indexOf('?') === -1 ? '?' : '&';
  // an <audio> element cannot carry an Authorization header, so the token that
  // the panel sends as a bearer has to travel in the query instead. Signed into
  // the ERP on the same origin, the session cookie covers it and this is unused.
  let tok = '';
  try { tok = localStorage.getItem('eon_token') || ''; } catch (e) {}
  return base + sep + 'lang=' + encodeURIComponent(lang)
       + (tok ? '&token=' + encodeURIComponent(tok) : '')
       + '&text=' + encodeURIComponent(text);
}

function sayViaServer(text, lang, myId) {
  return new Promise((resolve) => {
    let a;
    try { a = new Audio(ttsUrl(text, lang)); } catch (e) { resolve(false); return; }
    a.crossOrigin = 'use-credentials';
    state.audio = a;
    const done = (ok) => {
      if (state.audio === a) state.audio = null;
      resolve(ok);
    };
    a.onended = () => done(true);
    a.onerror = () => {
      setStatus('error', 'EON could not reach its own voice service, so the answer is on screen only.');
      done(false);
    };
    a.play().catch(() => {
      // browsers block audio until the page has been interacted with
      setStatus('error', 'The browser blocked audio until you interact with the page — click anywhere, then ask again.');
      done(false);
    });
    // a hush while the clip is playing must stop it
    const watch = setInterval(() => {
      if (state.sayId !== myId) { try { a.pause(); } catch (e) {} clearInterval(watch); done(false); }
      else if (a.ended || state.audio !== a) clearInterval(watch);
    }, 120);
  });
}

export const EonVoice = {
  available() { return { stt: !!SR, tts: !!SS }; },
  status() { return state.status; },
  lang() { return state.lang; },
  setLang(l) { state.lang = l || 'en-US'; if (state.rec) state.rec.lang = state.lang; return state.lang; },
  wakeWord(on) { state.wake = !!on; return state.wake; },
  onTranscript(fn) { listeners.transcript.add(fn); return () => listeners.transcript.delete(fn); },
  onState(fn) { listeners.state.add(fn); return () => listeners.state.delete(fn); },
  voices() { return loadVoices(); },
  hasVoiceFor(lang) { return hasVoiceFor(lang); },
  /** where EON's own speech comes from, and when to use it */
  setTts(url) { if (url) state.tts = String(url); return state.tts; },
  serverVoice(mode) { if (mode) state.serverVoice = String(mode); return state.serverVoice; },
  /** what this machine can actually speak — for the UI to warn with */
  voiceReport() {
    const vs = loadVoices();
    return {
      total: vs.length,
      english: hasVoiceFor('en'),
      bangla: hasVoiceFor('bn'),
      picked: { en: (pickVoice('en-US') || {}).name || null, bn: (pickVoice('bn-BD') || {}).name || null },
      serverVoice: state.serverVoice,
      ttsEndpoint: state.tts,
      note: hasVoiceFor('bn')
        ? ''
        : 'No Bangla voice is installed on this machine, so EON renders Bangla speech on the server and plays it back — nothing to install.',
    };
  },
  mute(on) { state.muted = !!on; if (on) this.hush(); },

  listen({ continuous = false } = {}) {
    if (!SR) { setStatus('error', 'Speech recognition is not supported in this browser — use Chrome or Edge.'); return false; }
    if (state.listening) return true;
    this.hush();
    const rec = new SR();
    rec.lang = state.lang; rec.continuous = continuous; rec.interimResults = true; rec.maxAlternatives = 1;
    state.continuous = continuous; state.rec = rec;
    let finalText = '';
    rec.onstart = () => { state.listening = true; setStatus('listening'); };
    rec.onresult = (ev) => {
      let interim = '';
      for (let i = ev.resultIndex; i < ev.results.length; i++) {
        const r = ev.results[i]; const t = r[0].transcript;
        if (r.isFinal) finalText += t; else interim += t;
      }
      const shown = (finalText + ' ' + interim).trim();
      if (shown) emit('transcript', shown, { final: false });
      if (finalText.trim()) {
        let text = finalText.trim(); finalText = '';
        if (state.continuous && state.wake) { if (!WAKE.test(text)) { emit('transcript', '', { final: false, ignored: text }); return; } text = text.replace(WAKE, '').trim(); if (!text) return; }
        emit('transcript', text, { final: true, confidence: ev.results[ev.results.length - 1][0].confidence });
      }
    };
    rec.onerror = (e) => { const msg = e.error === 'not-allowed' ? 'Microphone permission was denied.' : e.error === 'no-speech' ? 'No speech heard.' : e.error === 'network' ? 'Speech service unreachable (needs internet in Chrome).' : ('Speech error: ' + e.error); if (e.error !== 'no-speech' && e.error !== 'aborted') setStatus('error', msg); };
    rec.onend = () => { state.listening = false; if (state.continuous && state.rec === rec && !state.speaking) { try { rec.start(); return; } catch {} } setStatus(state.speaking ? 'speaking' : 'idle'); };
    try { rec.start(); } catch (e) { setStatus('error', 'Could not start the microphone: ' + e.message); return false; }
    return true;
  },
  stop() { state.continuous = false; const r = state.rec; state.rec = null; if (r) { try { r.stop(); } catch {} } state.listening = false; setStatus(state.speaking ? 'speaking' : 'idle'); },
  toggle(opts) { return state.listening ? (this.stop(), false) : this.listen(opts); },

  humanize(text, lang) { return humanize(text, lang); },
  hush() { state.sayId++; if (SS && (SS.speaking || SS.pending)) { try { SS.cancel(); } catch {} } if (state.audio) { try { state.audio.pause(); } catch {} state.audio = null; } state.speaking = false; },
  say(text, { lang, rate, pitch, raw } = {}) {
    text = String(text || '').replace(/\s+/g, ' ').trim();
    if (!raw && text) text = humanize(text, /[\u0980-\u09FF]/.test(text) ? 'bn' : 'en');
    if (!SS || !text || state.muted) return Promise.resolve(false);
    const wantsBangla = /[\u0980-\u09FF]/.test(text) || String(lang || state.lang).toLowerCase().startsWith('bn');
    const code = wantsBangla ? 'bn' : 'en';
    // no local voice for this language (or the caller wants EON's own): let the
    // server render it and simply play the audio back
    const needServer = state.serverVoice === 'always'
      || (state.serverVoice !== 'never' && !hasVoiceFor(code));
    if (needServer) {
      this.hush();
      const rec0 = state.rec; if (rec0) { try { rec0.stop(); } catch {} }
      const myId = ++state.sayId;
      state.speaking = true; setStatus('speaking');
      return sayViaServer(text, code, myId).then((ok) => {
        state.speaking = false;
        if (state.continuous && state.rec === rec0 && rec0) { try { rec0.start(); state.listening = true; setStatus('listening'); } catch (e) { setStatus('idle'); } }
        else if (state.status === 'speaking') setStatus('idle');
        return ok;
      });
    }
    this.hush();
    // pause recognition while speaking so EON doesn't hear itself
    const rec = state.rec; if (rec) { try { rec.stop(); } catch {} }
    const myId = ++state.sayId;
    return new Promise((resolve) => {
      // one utterance per phrase, with a short breath between them — a single
      // long utterance runs every sentence together
      const chunks = phrases(text);
      const pace = PACE[code];
      let i = 0; state.speaking = true; setStatus('speaking');
      const next = () => {
        if (state.sayId !== myId) { resolve(false); return; }   // hushed / superseded — stop the chain
        if (i >= chunks.length) { state.speaking = false; if (state.continuous && state.rec === rec && rec) { try { rec.start(); state.listening = true; setStatus('listening'); } catch { setStatus('idle'); } } else setStatus('idle'); resolve(true); return; }
        const u = new SpeechSynthesisUtterance(chunks[i++].trim());
        u.lang = lang || state.lang;
        u.rate = rate || (state.rate !== 1.0 ? state.rate : pace.rate);
        u.pitch = pitch || (state.pitch !== 1.0 ? state.pitch : pace.pitch);
        const v = pickVoice(u.lang); if (v) u.voice = v;
        const after = () => setTimeout(next, i < chunks.length ? pace.gap : 0);
        u.onend = after; u.onerror = after;
        SS.speak(u);
      };
      next();
    });
  },
};

if (typeof window !== 'undefined') window.EonVoice = Object.assign(window.EonVoice || {}, EonVoice);
export default EonVoice;
