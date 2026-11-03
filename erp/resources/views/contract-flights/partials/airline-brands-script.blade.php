// ── Airline marks ────────────────────────────────────────────────────────
// Which logo and which colour an airline name earns. Matched on the IATA
// code in the name's parentheses first — "(BS)" — and on the name second, so
// "Us Bangla  (BS)" and "US-Bangla Airlines" both land on the same mark.
// The name patterns are deliberately loose because the airlines table spells
// a few of them its own way: "Chian Eastern", "Golf Air", "Himalya".
//
// Logo files live in public/airline-logos/<slug>.png, used exactly as
// dropped there — fitMarks() zooms each one to its ink at runtime, so a
// mark with a wide empty margin needs no editing. To add an airline: drop
// its mark in that folder and add one line here — no CSS, no template
// change. A missing file degrades to initials on `color`, never a broken
// image.
// Logos uploaded from Ticketing → Airlines (2026-10-01) come first, matched
// on the airline's exact name or its IATA code, so an upload wins over a
// hand-placed mark. Read behind a guard: a failure means "no uploads".
@php
    try { $airlineUploadedLogos = \App\Models\Airline::uploadedLogoBrands(); } catch (\Throwable) { $airlineUploadedLogos = []; }
@endphp
const AIRLINE_UPLOADED = @json($airlineUploadedLogos);
const AIRLINE_BRANDS = [
    ...AIRLINE_UPLOADED.map(u => ({ slug: u.slug, code: u.code, color: '#4f46e5',
        re: new RegExp('^\\s*' + String(u.name).replace(/[.*+?^${}()|[\]\\]/g, '\\$&').replace(/\s+/g, '\\s+') + '\\s*$', 'i') })),
    { slug: 'us-bangla',      code: 'BS', color: '#0b5ed7', re: /us[\s_-]*bangla/i },
    { slug: 'biman',          code: 'BG', color: '#006a4e', re: /biman/i },
    { slug: 'emirates',       code: 'EK', color: '#d71921', re: /emirates/i },
    { slug: 'flydubai',       code: 'FZ', color: '#f58220', re: /fly\s*dubai/i },
    { slug: 'turkish',        code: 'TK', color: '#c8102e', re: /turkish/i },
    { slug: 'thai',           code: 'TG', color: '#4b2e83', re: /thai\s*air/i },
    { slug: 'malaysia',       code: 'MH', color: '#0b2c6b', re: /malaysia/i },
    { slug: 'malindo',        code: 'OD', color: '#d0232b', re: /malindo|batik/i },
    { slug: 'air-asia',       code: 'AK', color: '#e2231a', re: /air\s*asia/i },
    { slug: 'indigo',         code: '6E', color: '#1b2f6b', re: /indigo/i },
    { slug: 'air-india',      code: 'AI', color: '#c8102e', re: /air\s*india/i },
    { slug: 'srilankan',      code: 'UL', color: '#0b3d91', re: /sri\s*lankan/i },
    { slug: 'saudia',         code: 'SV', color: '#0a6e4e', re: /saudia|saudi\s*arabian/i },
    { slug: 'egyptair',       code: 'MS', color: '#1a3e7a', re: /egypt\s*air/i },
    { slug: 'ethiopian',      code: 'ET', color: '#0b7a3b', re: /ethiop/i },
    { slug: 'kuwait',         code: 'KU', color: '#0b3d91', re: /kuwait/i },
    { slug: 'china-southern', code: 'CZ', color: '#1f3a93', re: /china\s*southern/i },
    { slug: 'china-eastern',  code: 'MU', color: '#c8102e', re: /chi[an]{2}\s*eastern/i },
    { slug: 'air-china',      code: 'CA', color: '#c8102e', re: /air\s*china/i },
    { slug: 'fitsair',        code: '8D', color: '#1a3e7a', re: /fits\s*air/i },
    { slug: 'gulf-air',       code: 'GF', color: '#a88b4c', re: /gu?[lo]f\s*air/i },
    { slug: 'himalaya',       code: 'H9', color: '#1f6fb2', re: /himala?ya/i },
    { slug: 'jazeera',        code: 'J9', color: '#1e88e5', re: /jazeera/i },
    { slug: 'qatar',          code: 'QR', color: '#5c0632', re: /qatar/i },
];

// The IATA code the airlines table keeps in the name's parentheses, if any.
function airlineCode(name) {
    const m = String(name || '').match(/\(([A-Z0-9]{2,3})\)\s*$/i);
    return m ? m[1].toUpperCase() : '';
}

function airlineBrand(name) {
    const code = airlineCode(name);
    const hit = (code && AIRLINE_BRANDS.find(b => b.code === code))
             || AIRLINE_BRANDS.find(b => b.re.test(String(name || '')));
    return hit ? { slug: hit.slug, color: hit.color } : { slug: null, color: '#4f46e5' };
}
