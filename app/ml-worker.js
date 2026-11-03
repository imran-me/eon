/* ============================================================
   EON · ml-worker — the three open-source models, off the page.

   Kokoro (voice), multilingual-e5 (meaning) and Whisper (hearing) are
   WebAssembly and heavy: loading one, or running it, held the page's
   main thread long enough for Chrome to call the page unresponsive.
   Here they run in a worker; the page only sends text or audio and
   gets the result back.

   Messages in:  { id, op: 'tts'|'embed'|'asr'|'load', ... }
   Messages out: { id, ok, result | error }
   ============================================================ */
const TJS = 'https://cdn.jsdelivr.net/npm/@huggingface/transformers@3.5.1/+esm';
const KJS = 'https://cdn.jsdelivr.net/npm/kokoro-js@1.2.1/+esm';
const MODELS = { tts: 'onnx-community/Kokoro-82M-v1.0-ONNX', embed: 'Xenova/multilingual-e5-small', asr: 'onnx-community/whisper-base' };

// heat: two threads, not every core — slower by a beat, but the laptop stays cool through a long day
let tuned = null;
const tune = () => tuned || (tuned = import(TJS).then((T) => { T.env.backends.onnx.wasm.numThreads = 2; return T; }));

const ready = {};
function get(kind) {
  if (ready[kind]) return ready[kind];
  const T0 = tune();
  if (kind === 'tts') ready.tts = T0.then(() => import(KJS)).then(({ KokoroTTS }) => KokoroTTS.from_pretrained(MODELS.tts, { dtype: 'q8', device: 'wasm' }));
  else ready[kind] = T0.then((T) => T.pipeline(kind === 'embed' ? 'feature-extraction' : 'automatic-speech-recognition', MODELS[kind], { dtype: 'q8' }));
  ready[kind].catch(() => { delete ready[kind]; });
  return ready[kind];
}

self.onmessage = async ({ data: m }) => {
  try {
    let result;
    if (m.op === 'load') { await get(m.kind); result = true; }
    else if (m.op === 'tts') {
      const tts = await get('tts');
      const audio = await tts.generate(m.text, { voice: m.voice || 'af_heart', speed: m.speed || 1 });
      result = audio.toBlob();
    } else if (m.op === 'embed') {
      const p = await get('embed');
      const out = await p(m.texts, { pooling: 'mean', normalize: true });
      result = out.data;   // Float32Array, texts.length × 384
    } else if (m.op === 'asr') {
      const p = await get('asr');
      const out = await p(m.audio, { task: 'transcribe', chunk_length_s: 30 });
      result = String((out && out.text) || '').trim();
    }
    self.postMessage({ id: m.id, ok: true, result });
  } catch (e) { self.postMessage({ id: m.id, ok: false, error: String(e && e.message || e) }); }
};
