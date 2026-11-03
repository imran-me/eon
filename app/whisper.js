/* ============================================================
   EON · whisper — hearing with OpenAI's open-source Whisper, in the
   browser. One model for বাংলা, English and a sentence that mixes
   them: the language is detected per recording, not chosen first.

   Opt-in (the 🎧 chip): the model is ~250 MB, fetched once and kept by
   the browser's cache, and it transcribes after you stop speaking
   rather than while you speak. Off, the microphone is Chrome's own
   recogniser exactly as before.
   ============================================================ */
const LIB = 'https://cdn.jsdelivr.net/npm/@huggingface/transformers@3.5.1/+esm';
const MODEL = 'Xenova/whisper-small';

let asr = null, loading = null, rec = null, chunks = [], stream = null;

function load(onProgress) {
  if (loading) return loading;
  loading = import(/* @vite-ignore */ LIB)
    .then((T) => T.pipeline('automatic-speech-recognition', MODEL, { dtype: 'q8', progress_callback: onProgress }))
    .then((p) => { asr = p; console.info('[EON whisper] ready'); return true; })
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
  const audio = (await ctx.decodeAudioData(buf)).getChannelData(0);
  ctx.close();
  if (!asr) await load();
  const out = await asr(audio, { task: 'transcribe', chunk_length_s: 30 });
  return String((out && out.text) || '').trim();
}

const EonWhisper = {
  load, start, stop,
  ready: () => !!asr,
  recording: () => !!rec,
  on: () => { try { return localStorage.getItem('eon_whisper') === '1'; } catch (e) { return false; } },
  set(on) { try { localStorage.setItem('eon_whisper', on ? '1' : '0'); } catch (e) {} if (on) load(); },
};
window.EonWhisper = EonWhisper;
if (EonWhisper.on()) setTimeout(load, 3000);
export default EonWhisper;
