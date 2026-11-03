/* EON · intent-features — the model's features, shared by the trainer and the calibrator; must match server/lib/IntentModel.php */
export function features(q) {
  const t = String(q).toLowerCase().normalize('NFC').replace(/[০-৯]/g, (d) => '০১২৩৪৫৬৭৮৯'.indexOf(d)).replace(/\d+/g, '#');
  const words = t.split(/[^\p{L}\p{M}#]+/u).filter(Boolean);
  const f = new Set();
  for (const w of words) {
    f.add('w:' + w);
    const cs = [...('<' + w + '>')];
    for (let i = 0; i + 3 <= cs.length; i++) f.add('c:' + cs.slice(i, i + 3).join(''));
  }
  for (let i = 0; i + 1 < words.length; i++) f.add('b:' + words[i] + '_' + words[i + 1]);
  return [...f];
}
