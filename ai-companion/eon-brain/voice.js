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
     v.say(text, { lang })        → speak (returns a promise)
     v.setLang('en-US' | 'bn-BD') → recognition + synthesis language
     v.hasVoiceFor('bn')          → can this machine pronounce that language?
     v.voiceReport()              → what is installed, and what to do if Bangla is missing
     v.wakeWord(true|false)       → require "eon" in continuous mode
   ============================================================ */

const SR = typeof window !== 'undefined' ? (window.SpeechRecognition || window.webkitSpeechRecognition) : null;
const SS = typeof window !== 'undefined' ? window.speechSynthesis : null;

const state = { lang: 'en-US', listening: false, continuous: false, wake: false, rec: null, voices: [], speaking: false, status: 'idle', muted: false, rate: 1.0, pitch: 1.0, sayId: 0 };
const listeners = { transcript: new Set(), state: new Set() };
const emit = (k, ...a) => listeners[k].forEach((fn) => { try { fn(...a); } catch (e) { console.warn('[EON voice] listener', e); } });
const setStatus = (s, detail) => { state.status = s; emit('state', s, detail); };

function loadVoices() { if (!SS) return []; state.voices = SS.getVoices() || []; return state.voices; }
if (SS) { loadVoices(); try { SS.onvoiceschanged = loadVoices; } catch {} }

function pickVoice(lang) {
  const vs = state.voices.length ? state.voices : loadVoices();
  const l = String(lang || state.lang).toLowerCase(); const base = l.split('-')[0];
  const prefer = (v) => (v.lang || '').toLowerCase() === l ? 3 : (v.lang || '').toLowerCase().startsWith(base) ? 2 : 0;
  const quality = (v) => (/natural|neural|premium|enhanced|google|microsoft (aria|guy|jenny|ryan|sonia|neerja|prabhat)/i.test(v.name) ? 1 : 0) + (/female|aria|jenny|zira|samantha|sonia|neerja/i.test(v.name) ? 0.2 : 0);
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

const WAKE = /^\s*(hey |ok |hi )?(eon|ion|eyon|aeon|ইয়ন|ইওন|এওন)[,!.\s]*/i;

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
  /** what this machine can actually speak — for the UI to warn with */
  voiceReport() {
    const vs = loadVoices();
    return {
      total: vs.length,
      english: hasVoiceFor('en'),
      bangla: hasVoiceFor('bn'),
      picked: { en: (pickVoice('en-US') || {}).name || null, bn: (pickVoice('bn-BD') || {}).name || null },
      note: hasVoiceFor('bn') ? '' : 'No Bangla voice is installed, so Bangla answers cannot be read aloud. Windows: Settings → Time & language → Language → add বাংলা with its speech pack.',
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

  hush() { state.sayId++; if (SS && (SS.speaking || SS.pending)) { try { SS.cancel(); } catch {} } state.speaking = false; },
  say(text, { lang, rate, pitch } = {}) {
    text = String(text || '').replace(/\s+/g, ' ').trim();
    if (!SS || !text || state.muted) return Promise.resolve(false);
    // Bengali script with no Bengali voice installed reads as silence — say so
    // rather than appearing to speak and producing nothing.
    const wantsBangla = /[\u0980-\u09FF]/.test(text) || String(lang || state.lang).toLowerCase().startsWith('bn');
    if (wantsBangla && !hasVoiceFor('bn')) {
      setStatus('error', 'No Bangla voice is installed on this device — the answer is on screen. Add বাংলা speech in the system language settings to hear it.');
      return Promise.resolve(false);
    }
    this.hush();
    // pause recognition while speaking so EON doesn't hear itself
    const rec = state.rec; if (rec) { try { rec.stop(); } catch {} }
    const myId = ++state.sayId;
    return new Promise((resolve) => {
      const chunks = text.length > 240 ? text.match(/[^.!?।]+[.!?।]+|[^.!?।]+$/g) || [text] : [text];
      let i = 0; state.speaking = true; setStatus('speaking');
      const next = () => {
        if (state.sayId !== myId) { resolve(false); return; }   // hushed / superseded — stop the chain
        if (i >= chunks.length) { state.speaking = false; if (state.continuous && state.rec === rec && rec) { try { rec.start(); state.listening = true; setStatus('listening'); } catch { setStatus('idle'); } } else setStatus('idle'); resolve(true); return; }
        const u = new SpeechSynthesisUtterance(chunks[i++].trim());
        u.lang = lang || state.lang; u.rate = rate || state.rate; u.pitch = pitch || state.pitch;
        const v = pickVoice(u.lang); if (v) u.voice = v;
        u.onend = next; u.onerror = next;
        SS.speak(u);
      };
      next();
    });
  },
};

if (typeof window !== 'undefined') window.EonVoice = Object.assign(window.EonVoice || {}, EonVoice);
export default EonVoice;
