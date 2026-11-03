@php
    /**
     * The five dimensions, each with its bar, its weight and — the part that
     * matters — the sentence saying what the number was read from.
     *
     * The basis line is never optional and never truncated. A score with no
     * stated basis is a rumour with a decimal point, and this one ends up in a
     * conversation about somebody's job.
     *
     * @var array $pillars  Pillar[] keyed by key
     */
    /* Type-checked — see sparkline.blade.php. */
    $pillars = is_array($pillars ?? null) ? $pillars : [];
@endphp

@forelse ($pillars as $p)
    <div class="eon-pillar">
        <div class="eon-pillar-h">
            <div>
                <span class="eon-pillar-n">{{ $p->label }}</span>
                <span class="eon-pillar-w">· weight {{ (int) $p->weight }}</span>
            </div>
            <div class="eon-pillar-v" style="color:{{ $p->known() ? '#111827' : '#d1d5db' }}">
                {{ $p->known() ? $p->rounded() : 'n/a' }}
            </div>
        </div>

        <div class="eon-bar">
            <span class="{{ $p->tone() }}" style="width:{{ $p->known() ? max(2, $p->rounded()) : 100 }}%;{{ $p->known() ? '' : 'opacity:.35' }}"></span>
        </div>

        <div class="eon-pillar-b">{{ $p->basis }}</div>
    </div>
@empty
    <div class="eon-empty"><i class="fas fa-scale-balanced"></i>Nothing scored yet.</div>
@endforelse
