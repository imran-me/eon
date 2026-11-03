{{--
    The Ticketing workbook in a browser tab (owner, 2026-10-11: "it was supposed
    to open in a new tab"). A browser cannot open .xlsx, so this page draws the
    same tabs from the same rows the .xlsx is built from (TicketWorkbook::$views):
    sticky headers, click a header to sort, search the open tab, totals that
    follow the search — and the real Excel file one click away.
    Standalone page (no ERP layout) so it opens fast in its own tab.
--}}
@php
    use PhpOffice\PhpSpreadsheet\Shared\Date as XlDate;
    $money = fn ($v) => $v === null || $v === '' ? '' : ((float) $v == 0 ? '–' : number_format((float) $v, 2));
    $when = function ($v, bool $time) {
        if ($v === null || $v === '') return '';
        try { $d = XlDate::excelToDateTimeObject((float) $v); } catch (\Throwable $e) { return ''; }
        return $d->format($time ? 'd M Y, H:i' : 'd M Y');
    };
    $sortKey = fn ($v, $kind) => in_array($kind, ['money', 'count', 'percent', 'date', 'stamp'], true) ? (float) ($v ?? 0) : mb_strtolower((string) $v);
    $pill = function (?string $s) {
        $map = ['purchased' => 'g', 'cancelled' => 'r', 'refund' => 'r', 'void' => 'r', 'booked' => 'b', 'expired' => 'r', 're-issue' => 'a', 'reschedule' => 'b', 'new ticket' => 'g', 'portal' => 'b', 'vendor' => 'n'];
        $k = mb_strtolower((string) $s);
        foreach ($map as $word => $c) { if (str_starts_with($k, $word)) return $c; }
        return 'n';
    };
    $o = $overview;
    $maxMonth = max(1, (float) $monthly->max(fn ($m) => max($m['revenue'], $m['sold_cost'], $m['cost'])));
@endphp
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Ticketing Workbook</title>
<style>
:root{--brand:#4f46e5;--ink:#1e293b;--dim:#64748b;--line:#e2e8f0;--bg:#f1f5f9;--card:#fff;--g:#15803d;--r:#b91c1c;--a:#b45309;--b:#1d4ed8;}
*{box-sizing:border-box;}
html,body{margin:0;background:var(--bg);color:var(--ink);font:13px/1.45 'Segoe UI',Calibri,Arial,sans-serif;}
.top{position:sticky;top:0;z-index:6;display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding:10px 16px;background:var(--card);border-bottom:1px solid var(--line);}
.top h1{margin:0;font-size:16px;}
.top small{color:var(--dim);}
.sp{flex:1;}
.search{height:34px;width:280px;max-width:100%;padding:0 12px;border:1px solid #cbd5e1;border-radius:8px;font:inherit;}
.btn{display:inline-flex;align-items:center;gap:6px;height:34px;padding:0 14px;border-radius:8px;border:1px solid #16a34a;background:#16a34a;color:#fff;font-weight:700;text-decoration:none;cursor:pointer;font:700 13px 'Segoe UI',Arial;}
.btn.ghost{background:#fff;color:#334155;border-color:#cbd5e1;}
.tabs{position:sticky;top:55px;z-index:5;display:flex;gap:4px;padding:8px 16px 0;background:var(--bg);overflow-x:auto;}
.tab{padding:9px 16px;border:1px solid var(--line);border-bottom:none;border-radius:9px 9px 0 0;background:#e2e8f0;color:#475569;font:700 13px 'Segoe UI',Arial;cursor:pointer;white-space:nowrap;}
.tab span{font-weight:600;color:#94a3b8;margin-left:6px;}
.tab.on{background:var(--card);color:var(--brand);box-shadow:inset 0 3px 0 var(--brand);}
.pane{display:none;margin:0 16px 24px;padding:16px;background:var(--card);border:1px solid var(--line);border-radius:0 10px 10px 10px;}
.pane.on{display:block;}
.pane h2{margin:0 0 2px;font-size:16px;}
.pane .sub{color:var(--dim);margin-bottom:12px;}
/* dashboard */
.tiles{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:10px;margin-bottom:18px;}
.tile{padding:12px 14px;background:#f8fafc;border:1px solid var(--line);border-left:4px solid var(--c);border-radius:8px;}
.tile .k{font-size:11px;font-weight:700;color:var(--dim);text-transform:uppercase;letter-spacing:.03em;}
.tile .v{font-size:20px;font-weight:800;color:var(--c);margin-top:2px;}
.grid2{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px;}
.box{border:1px solid var(--line);border-radius:10px;padding:12px 14px;}
.box h3{margin:0 0 8px;font-size:13px;}
.go{display:flex;justify-content:space-between;gap:10px;padding:7px 0;border-bottom:1px dashed var(--line);cursor:pointer;}
.go:last-child{border-bottom:none;}
.go b{color:var(--brand);white-space:nowrap;}
.go:hover b{text-decoration:underline;}
.go small{color:var(--dim);text-align:right;}
.due{display:flex;justify-content:space-between;padding:4px 0;}
.due b{font-variant-numeric:tabular-nums;}
.chart{display:flex;gap:18px;align-items:flex-end;height:180px;padding:10px 4px 0;border-bottom:1px solid var(--line);overflow-x:auto;}
.mo{display:flex;flex-direction:column;align-items:center;gap:4px;min-width:90px;}
.bars{display:flex;gap:4px;align-items:flex-end;height:150px;}
.bar{width:22px;border-radius:4px 4px 0 0;}
.mo small{color:var(--dim);font-weight:600;}
.legend{display:flex;gap:14px;margin-top:8px;color:var(--dim);font-size:12px;}
.legend i{display:inline-block;width:10px;height:10px;border-radius:2px;margin-right:5px;vertical-align:-1px;}
/* tables */
.tw{overflow:auto;max-height:calc(100vh - 210px);border:1px solid var(--line);border-radius:8px;}
table{border-collapse:separate;border-spacing:0;width:max-content;min-width:100%;}
th{position:sticky;top:0;z-index:2;background:var(--brand);color:#fff;text-align:left;padding:8px 10px;font-size:12px;white-space:nowrap;cursor:pointer;user-select:none;}
th.n{text-align:right;}
th .ar{opacity:.5;margin-left:4px;}
td{padding:6px 10px;border-bottom:1px solid #eef2f7;white-space:nowrap;max-width:380px;overflow:hidden;text-overflow:ellipsis;vertical-align:top;}
td.n{text-align:right;font-variant-numeric:tabular-nums;}
td.note{white-space:normal;min-width:260px;color:#475569;}
tbody tr:nth-child(even) td{background:#f8fafc;}
tbody tr:hover td{background:#eef2ff;}
tfoot td{position:sticky;bottom:0;background:#e0e7ff;font-weight:700;border-top:2px solid var(--brand);}
.pos{color:var(--g);}
.neg{color:var(--r);font-weight:700;}
.pill{display:inline-block;padding:1px 8px;border-radius:999px;font-size:11px;font-weight:700;}
.pill.g{background:#dcfce7;color:var(--g);}
.pill.r{background:#fee2e2;color:var(--r);}
.pill.a{background:#fef3c7;color:var(--a);}
.pill.b{background:#dbeafe;color:var(--b);}
.pill.n{background:#f1f5f9;color:#475569;}
.mono{font-family:Consolas,'Courier New',monospace;}
.none{padding:18px;color:var(--dim);text-align:center;}
@media (max-width:640px){.search{width:100%;}.tabs{top:100px;}}
</style>
</head>
<body>
<div class="top">
    <div><h1>Ticketing Workbook</h1><small>Built from the ERP at {{ $builtAt->format('d M Y, H:i') }}{{ $from || $to ? ' · ' . ($from?->format('d M Y') ?? 'start') . ' – ' . ($to?->format('d M Y') ?? 'today') : '' }} — every entry and edit up to now.</small></div>
    <span class="sp"></span>
    <input class="search" id="q" type="search" placeholder="Search this tab — PNR, passenger, agent, seller…" autocomplete="off">
    <button type="button" class="btn ghost" onclick="location.reload()" title="Rebuild from the database now">↻ Refresh</button>
    <a class="btn" href="{{ $downloadUrl }}" title="The same workbook as an .xlsx file for Excel">⬇ Download Excel</a>
</div>

<div class="tabs" id="tabs">
    <button type="button" class="tab on" data-t="dash">Dashboard</button>
    @foreach ($views as $i => $v)
        <button type="button" class="tab" data-t="{{ $i }}">{{ $v['name'] }}<span>{{ count($v['rows']) }}</span></button>
    @endforeach
</div>

{{-- Dashboard --}}
<div class="pane on" data-t="dash">
    <div class="tiles">
        @foreach ([
            ['Tickets issued', number_format($o['tickets']), '#4f46e5'], ['Ticket cost', number_format($o['cost'], 2), '#b91c1c'], ['Sales revenue', number_format($o['revenue'], 2), '#15803d'],
            ['Profit on sold tickets', number_format($o['sold_profit'], 2), '#15803d'], ['Margin', $o['margin'] === null ? '—' : number_format($o['margin'], 1) . '%', '#0f766e'], ['Cancelled tickets', number_format($o['cancelled']), '#b45309'],
            ['Owed to sellers', number_format($o['supplier_due'], 2), '#b91c1c'], ['Agents owe us', number_format($o['agent_due'], 2), '#1d4ed8'], ['Paid to sellers', number_format($o['supplier_paid'], 2), '#475569'],
        ] as [$k, $val, $c])
            <div class="tile" style="--c:{{ $c }}"><div class="k">{{ $k }}</div><div class="v">{{ $val }}</div></div>
        @endforeach
    </div>
    <div class="grid2">
        <div class="box"><h3>Go to</h3>
            @foreach ($views as $i => $v)
                <div class="go" data-go="{{ $i }}"><b>→ {{ $v['name'] }}</b><small>{{ count($v['rows']) }} rows · {{ \Illuminate\Support\Str::after($v['title'], '— ') }}</small></div>
            @endforeach
        </div>
        <div class="box"><h3>Revenue, cost and profit by month</h3>
            @if ($monthly->isEmpty())
                <div class="none">No months yet.</div>
            @else
                <div class="chart">
                    @foreach ($monthly as $m)
                        <div class="mo" title="{{ $m['label'] }} — revenue {{ number_format($m['revenue'], 2) }}, sold cost {{ number_format($m['sold_cost'], 2) }}, profit {{ number_format($m['profit'], 2) }}">
                            <div class="bars">
                                <div class="bar" style="height:{{ max(2, $m['revenue'] / $maxMonth * 150) }}px;background:#4f46e5"></div>
                                <div class="bar" style="height:{{ max(2, $m['sold_cost'] / $maxMonth * 150) }}px;background:#f87171"></div>
                                <div class="bar" style="height:{{ max(2, max(0, $m['profit']) / $maxMonth * 150) }}px;background:#22c55e"></div>
                            </div>
                            <small>{{ $m['label'] }}</small>
                        </div>
                    @endforeach
                </div>
                <div class="legend"><span><i style="background:#4f46e5"></i>Revenue</span><span><i style="background:#f87171"></i>Sold cost</span><span><i style="background:#22c55e"></i>Profit</span></div>
            @endif
        </div>
        <div class="box"><h3>Biggest seller dues</h3>
            @forelse ($sellerDues as $s)
                <div class="due"><span>{{ $s['name'] }}{{ $s['portal'] ? ' (portal)' : '' }}</span><b class="neg">{{ number_format($s['due'], 2) }}</b></div>
            @empty <div class="none">Nothing owed.</div> @endforelse
        </div>
        <div class="box"><h3>Biggest agent dues</h3>
            @forelse ($agentDues as $a)
                <div class="due"><span>{{ $a['name'] }}</span><b style="color:var(--b)">{{ number_format($a['due'], 2) }}</b></div>
            @empty <div class="none">Nobody owes.</div> @endforelse
        </div>
    </div>
</div>

{{-- one pane per sheet --}}
@foreach ($views as $i => $v)
    @php $num = fn ($k) => in_array($v['kinds'][$k] ?? 'text', ['money', 'count', 'percent'], true); @endphp
    <div class="pane" data-t="{{ $i }}">
        <h2>{{ \Illuminate\Support\Str::before($v['title'], ' — ') }}</h2>
        <div class="sub">{{ \Illuminate\Support\Str::after($v['title'], '— ') }} · click a heading to sort</div>
        @if (empty($v['rows']))
            <div class="none">Nothing yet.</div>
        @else
            <div class="tw"><table data-totals="{{ json_encode($v['totals']) }}">
                <thead><tr>
                    @foreach ($v['head'] as $k => $h)<th class="{{ $num($k) ? 'n' : '' }}" data-k="{{ $k }}" data-kind="{{ $v['kinds'][$k] }}">{{ $h }}<span class="ar">↕</span></th>@endforeach
                </tr></thead>
                <tbody>
                    @foreach ($v['rows'] as $row)
                        <tr>
                            @foreach ($row as $k => $cell)
                                @php
                                    $kind = $v['kinds'][$k] ?? 'text';
                                    $h = $v['head'][$k];
                                    $show = match ($kind) {
                                        'money' => $money($cell), 'count' => $cell === null ? '' : number_format((float) $cell),
                                        'percent' => $cell === null ? '' : number_format((float) $cell * 100, 1) . '%',
                                        'date' => $when($cell, false), 'stamp' => $when($cell, true), default => (string) $cell,
                                    };
                                    $cls = [];
                                    if ($num($k)) $cls[] = 'n';
                                    if (in_array($h, ['Profit', 'Net to revenue'], true) && $cell !== null && (float) $cell != 0) $cls[] = (float) $cell < 0 ? 'neg' : 'pos';
                                    if (in_array($h, ['PNR', 'E-ticket', 'Passport', 'Reference'], true)) $cls[] = 'mono';
                                    if ($h === 'Note') $cls[] = 'note';
                                @endphp
                                <td class="{{ implode(' ', $cls) }}" data-s="{{ $sortKey($cell, $kind) }}" @if($kind === 'money' || $kind === 'count') data-v="{{ (float) ($cell ?? 0) }}" @endif @if(in_array($h, ['Note', 'Route', 'Passenger', 'Seller'], true)) title="{{ $show }}" @endif>@if(in_array($h, ['Status', 'Kind', 'Type'], true) && $show !== '')<span class="pill {{ $pill($show) }}">{{ $show }}</span>@else{{ $show }}@endif</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
                <tfoot><tr>@foreach ($v['head'] as $k => $h)<td class="{{ $num($k) ? 'n' : '' }}" data-k="{{ $k }}">{{ $k === 0 ? 'Total' : '' }}</td>@endforeach</tr></tfoot>
            </table></div>
        @endif
    </div>
@endforeach

<script>
(function () {
  const tabs = [...document.querySelectorAll('.tab')], panes = [...document.querySelectorAll('.pane')], q = document.getElementById('q');
  const fmt = (n, count) => count ? n.toLocaleString('en-US') : (n === 0 ? '–' : n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
  function show(t) {
    tabs.forEach(b => b.classList.toggle('on', b.dataset.t === String(t)));
    panes.forEach(p => p.classList.toggle('on', p.dataset.t === String(t)));
    q.disabled = String(t) === 'dash';
    filter();
    try { sessionStorage.setItem('wbTab', t); } catch (e) {}
    window.scrollTo(0, 0);
  }
  // totals of the rows still showing — a search for one agent totals just that agent
  function totals(table) {
    const cols = JSON.parse(table.dataset.totals || '[]'), rows = [...table.tBodies[0].rows].filter(r => !r.hidden);
    cols.forEach(k => {
      const sum = rows.reduce((a, r) => a + (parseFloat(r.cells[k]?.dataset.v) || 0), 0);
      const kind = table.tHead.rows[0].cells[k]?.dataset.kind;
      const cell = table.tFoot.rows[0].cells[k];
      if (cell) cell.textContent = fmt(Math.round(sum * 100) / 100, kind === 'count');
    });
    table.tFoot.rows[0].cells[0].textContent = 'Total · ' + rows.length + ' row' + (rows.length === 1 ? '' : 's');
  }
  function filter() {
    const pane = document.querySelector('.pane.on'), table = pane && pane.querySelector('table'); if (!table) return;
    const words = q.value.trim().toLowerCase().split(/\s+/).filter(Boolean);
    [...table.tBodies[0].rows].forEach(r => { r.hidden = words.length > 0 && !words.every(w => r.textContent.toLowerCase().includes(w)); });
    totals(table);
  }
  // click a heading: sort by it, again to reverse
  document.querySelectorAll('th').forEach(th => th.addEventListener('click', () => {
    const table = th.closest('table'), k = +th.dataset.k, num = ['money', 'count', 'percent', 'date', 'stamp'].includes(th.dataset.kind);
    const dir = th.dataset.dir === 'asc' ? 'desc' : 'asc';
    table.tHead.querySelectorAll('th').forEach(x => { delete x.dataset.dir; x.querySelector('.ar').textContent = '↕'; });
    th.dataset.dir = dir; th.querySelector('.ar').textContent = dir === 'asc' ? '↑' : '↓';
    const rows = [...table.tBodies[0].rows];
    rows.sort((a, b) => {
      const x = a.cells[k].dataset.s, y = b.cells[k].dataset.s;
      const c = num ? (parseFloat(x) || 0) - (parseFloat(y) || 0) : x.localeCompare(y);
      return dir === 'asc' ? c : -c;
    });
    rows.forEach(r => table.tBodies[0].appendChild(r));
  }));
  tabs.forEach(b => b.addEventListener('click', () => show(b.dataset.t)));
  document.querySelectorAll('[data-go]').forEach(g => g.addEventListener('click', () => show(g.dataset.go)));
  q.addEventListener('input', filter);
  document.querySelectorAll('table').forEach(totals);
  try { const s = sessionStorage.getItem('wbTab'); if (s !== null && tabs.some(b => b.dataset.t === s)) show(s); else q.disabled = true; } catch (e) { q.disabled = true; }
})();
</script>
</body>
</html>
