/* ============================================================
   EON · whisper — hearing with OpenAI's open-source Whisper. One model
   for বাংলা, English and a sentence that mixes them: the language is
   detected per recording, not chosen first.

   Opt-in (the 🎧 chip): the model is ~250 MB, fetched once and kept by
   the browser's cache, and runs in app/ml-worker.js so the page never
   freezes. It transcribes after you stop speaking. Off, the microphone
   is Chrome's own recogniser exactly as before.
   ============================================================ */
import { ml } from './ml.js';

let isReady = false, loading = null, rec = null, chunks = [], stream = null;

function load() {
  if (loading) return loading;
  loading = ml('load', { kind: 'asr' })
    .then(() => { isReady = true; console.info('[EON whisper] ready'); return true; })
    .catch((e) => { console.warn('[EON whisper] unavailable', e); loading = null; return false; });
  return loading;
}

async function start() {
  stream = await navigator.mediaDevices.getUserMedia({ audio: true });
  chunks = [];
  rec = new MediaRecorder(stream);
  rec.ondataavailable = (e) => { if (e.data.size) chunks.push(e.data); };
  rec.start();
}

/** stop recording and return what was said ('' for silence) */
async function stop() {
  if (!rec) return '';
  const done = new Promise((r) => { rec.onstop = r; });
  rec.stop(); await done;
  stream.getTracks().forEach((t) => t.stop());
  rec = null;
  const buf = await new Blob(chunks).arrayBuffer();
  const ctx = new AudioContext({ sampleRate: 16000 });
  const audio = (await ctx.decodeAudioData(buf)).getChannelData(0).slice();
  ctx.close();
  return ml('asr', { audio }, [audio.buffer]);
}

const EonWhisper = {
  load, start, stop,
  ready: () => isReady,
  recording: () => !!rec,
  on: () => { try { return localStorage.getItem('eon_whisper2') === '1'; } catch (e) { return false; } },
  set(on) { try { localStorage.setItem('eon_whisper2', on ? '1' : '0'); } catch (e) {} if (on) load(); },
};
window.EonWhisper = EonWhisper;
/* never downloaded behind the boss's back: the 250 MB pull starved Chrome's own recogniser
   (the mic went red and heard nothing). It loads only when the 🎧 chip is pressed, and the
   old key is retired so a browser that had it on starts with it off. */
export default EonWhisper;
