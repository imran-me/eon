@extends('layout.app')

@section('meta-information')
    <title>{{ $bank->name }} — Reconciliation</title>
@endsection

@section('main-content')
@include('layout.bank-tabs')

@php $role = Str::slug(Auth::user()->getRoleNames()->first()); @endphp

<div class="bg-white rounded-lg shadow-md p-4 md:p-6">

    <a href="{{ route('role.banks.dashboard', ['role' => $role]) }}"
       class="inline-flex items-center gap-1 text-sm font-semibold text-indigo-600 hover:text-indigo-700 mb-4">
        <i class="fas fa-arrow-left"></i> Back to Bank Accounts
    </a>

    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-gradient-to-r from-indigo-600 to-indigo-500 text-white px-6 py-6 mb-5">
        <div>
            <h2 class="text-xl font-bold"><i class="fas fa-scale-balanced mr-2"></i>{{ $bank->name }} — Reconciliation</h2>
            <div class="text-sm opacity-85 mt-1">
                Record the bank statement's real closing balance for a day; it's compared here against
                what the ledger says as of that day.
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-5 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3">
            {{ session('success') }}
        </div>
    @endif

    {{-- Compare the bank's own PDF statement with the ledger.
         The PDF is read here in the browser (pdf.js) and only its text lines
         are posted — see compareStatement() and the script under @section('js'). --}}
    <div class="rounded-xl bg-white shadow-sm border border-gray-100 px-6 py-5 mb-5" id="bankStmtCard">
        <h3 class="text-sm font-bold text-gray-700 mb-1"><i class="fas fa-file-pdf mr-1"></i>Compare with the Bank's Statement (PDF)</h3>
        <p class="text-xs text-gray-400 mb-3">
            Upload the statement the bank gave you (e.g. Islami Bank's account statement PDF). Its transactions are lined up
            against the ledger day by day — by date, time and closing balance — and every difference is pointed out.
            The file stays on this computer; only the text read from it is sent.
        </p>
        <form action="{{ route('role.banks.reconciliation.compare', ['role' => $role, 'bank' => $bank->id]) }}" method="POST"
              id="bankStmtForm" class="flex flex-wrap items-end gap-4">
            @csrf
            <input type="hidden" name="statement_text" id="bankStmtText">
            <input type="hidden" name="statement_layout" id="bankStmtLayout">
            <input type="hidden" name="file_name" id="bankStmtFileName">
            <input type="hidden" name="pages" id="bankStmtPages">
            <div class="flex-1 min-w-[260px]">
                <label class="block mb-1.5 text-sm font-medium text-gray-700">Bank statement PDF</label>
                <input type="file" id="bankStmtFile" accept="application/pdf,.pdf"
                    class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div>
                <button type="button" id="bankStmtCompare" disabled
                    class="px-4 py-2 rounded-md text-sm font-medium bg-indigo-600 text-white hover:bg-indigo-700"
                    style="opacity:.5;cursor:not-allowed;">
                    <i class="fas fa-columns mr-1"></i>Compare with Ledger
                </button>
            </div>
        </form>
        <p class="text-xs mt-2" id="bankStmtStatus" style="min-height:1.25em;color:#6b7280;"></p>
    </div>

    {{-- Add / update a checkpoint --}}
    <div class="rounded-xl bg-white shadow-sm border border-gray-100 px-6 py-5 mb-5">
        <h3 class="text-sm font-bold text-gray-700 mb-3"><i class="fas fa-plus mr-1"></i>Add / Update a Checkpoint</h3>
        <form action="{{ route('role.banks.reconciliation.store', ['role' => $role, 'bank' => $bank->id]) }}" method="POST"
              class="flex flex-wrap items-end gap-4">
            @csrf
            <div class="flex-1 min-w-[160px]">
                <label class="block mb-1.5 text-sm font-medium text-gray-700">Date</label>
                <input type="date" name="date" required max="{{ now()->toDateString() }}"
                    class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div class="flex-1 min-w-[200px]">
                <label class="block mb-1.5 text-sm font-medium text-gray-700">Actual Closing Balance (from statement)</label>
                <input type="number" step="0.01" name="actual_closing_balance" required
                    class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div class="flex-[2] min-w-[220px]">
                <label class="block mb-1.5 text-sm font-medium text-gray-700">Note (optional)</label>
                <input type="text" name="note" maxlength="500"
                    class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div>
                <button type="submit" class="px-4 py-2 rounded-md text-sm font-medium bg-indigo-600 text-white hover:bg-indigo-700">
                    <i class="fas fa-check mr-1"></i>Save
                </button>
            </div>
        </form>
        <p class="text-xs text-gray-400 mt-2">Saving a date that already has a checkpoint updates it instead of creating a duplicate.</p>
    </div>

    {{-- Checkpoints table --}}
    <div class="rounded-xl bg-white shadow-sm border border-gray-100 overflow-hidden">
        @if($rows->isEmpty())
            <div class="text-center py-12 text-gray-400">
                <i class="fas fa-inbox text-4xl mb-3 block"></i>
                <p>No reconciliation checkpoints recorded yet for this account.</p>
            </div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 border-b border-gray-200">Date</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 border-b border-gray-200">Actual Balance</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 border-b border-gray-200">System Balance</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 border-b border-gray-200">Difference</th>
                        <th class="px-4 py-2.5 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 border-b border-gray-200">Status</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 border-b border-gray-200">Note</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 border-b border-gray-200">Entered By</th>
                        <th class="px-4 py-2.5 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 border-b border-gray-200">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                    @php $matched = abs($row['difference']) < 0.01; @endphp
                    <tr class="border-b border-gray-100 hover:bg-gray-50 {{ $matched ? '' : 'bg-red-50/40' }}">
                        <td class="px-4 py-2.5 whitespace-nowrap text-gray-700">{{ \Carbon\Carbon::parse($row['date'])->format('d M Y') }}</td>
                        <td class="px-4 py-2.5 text-right font-mono">{{ number_format($row['actual_closing_balance'], 2) }}</td>
                        <td class="px-4 py-2.5 text-right font-mono">{{ number_format($row['system_closing_balance'], 2) }}</td>
                        <td class="px-4 py-2.5 text-right font-mono font-semibold {{ $matched ? 'text-gray-400' : 'text-red-600' }}">
                            {{ $matched ? '—' : number_format($row['difference'], 2) }}
                        </td>
                        <td class="px-4 py-2.5 text-center">
                            @if($matched)
                                <span style="display:inline-block;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:700;background:#dcfce7;color:#15803d;">Matched</span>
                            @else
                                <span style="display:inline-block;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:700;background:#fee2e2;color:#b91c1c;">Mismatch ৳{{ number_format(abs($row['difference']), 2) }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-gray-500">{{ $row['note'] ?: '—' }}</td>
                        <td class="px-4 py-2.5 text-gray-500">{{ $row['created_by'] ?: '—' }}</td>
                        <td class="px-4 py-2.5 text-center">
                            <form action="{{ route('role.banks.reconciliation.destroy', ['role' => $role, 'bank' => $bank->id, 'reconciliation' => $row['id']]) }}"
                                  method="POST" onsubmit="return confirm('Remove this checkpoint?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" style="padding:4px 10px;border-radius:6px;border:1px solid #fecaca;background:#fef2f2;color:#b91c1c;font-size:11px;font-weight:600;cursor:pointer;">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>
@endsection

@section('js')
{{-- pdf.js (Mozilla) from the same CDN the layout already uses for
     FontAwesome and SweetAlert2. Version pinned; the UMD build so it works
     as a plain script. The worker file is the same version. --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
(function () {
    var fileInput = document.getElementById('bankStmtFile');
    var button    = document.getElementById('bankStmtCompare');
    var status    = document.getElementById('bankStmtStatus');
    var form      = document.getElementById('bankStmtForm');
    if (!fileInput || !button || !form) return;

    var WORKER = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

    function say(text, isError) {
        status.textContent = text;
        status.style.color = isError ? '#b91c1c' : '#6b7280';
    }
    function enable(on) {
        button.disabled = !on;
        button.style.opacity = on ? '' : '.5';
        button.style.cursor  = on ? '' : 'not-allowed';
    }

    fileInput.addEventListener('change', function () {
        var f = fileInput.files && fileInput.files[0];
        if (!f) { enable(false); say(''); return; }
        if (!/\.pdf$/i.test(f.name) && f.type !== 'application/pdf') {
            enable(false); say('Please choose a PDF file.', true); return;
        }
        enable(true);
        say(f.name + ' — ' + Math.round(f.size / 1024) + ' KB. Click "Compare with Ledger".');
    });

    // Rebuild the statement's visual lines from pdf.js's positioned text
    // pieces: pieces on the same baseline (within half a line-height) form
    // one line, ordered left to right, with a space wherever there is a
    // visible gap between two pieces. Pages top to bottom. Each line also
    // reports its page and baseline, which is how the server attaches a
    // wrapped description to the right row when the bank centres the date
    // between the description's lines (Islami Bank does).
    async function extractLines(buffer) {
        if (!window.pdfjsLib) throw new Error('The PDF reader could not be loaded (no internet access to the CDN?). Try again.');
        window.pdfjsLib.GlobalWorkerOptions.workerSrc = WORKER;
        var pdf = await window.pdfjsLib.getDocument({ data: buffer }).promise;
        var lines  = [];
        var layout = [];
        for (var p = 1; p <= pdf.numPages; p++) {
            var page    = await pdf.getPage(p);
            var content = await page.getTextContent();
            var items   = content.items
                // Rotated pieces are watermarks ("e-Statement"), not rows.
                .filter(function (it) {
                    return typeof it.str === 'string' && it.str.trim() !== ''
                        && Math.abs(it.transform[1]) < 0.01 && Math.abs(it.transform[2]) < 0.01;
                })
                .map(function (it) {
                    return { x: it.transform[4], y: it.transform[5], w: it.width || 0, h: it.height || 8, s: it.str };
                })
                .sort(function (a, b) { return (b.y - a.y) || (a.x - b.x); });

            var current = null;
            var rows    = [];
            items.forEach(function (it) {
                var tol = Math.max(2, it.h * 0.5);
                if (!current || Math.abs(current.y - it.y) > tol) {
                    current = { y: it.y, items: [] };
                    rows.push(current);
                }
                current.items.push(it);
            });

            rows.forEach(function (row) {
                row.items.sort(function (a, b) { return a.x - b.x; });
                var text = '';
                var endX = null;
                row.items.forEach(function (it) {
                    if (endX !== null) {
                        var gap = it.x - endX;
                        if (gap > 1.5 && !/\s$/.test(text) && !/^\s/.test(it.s)) text += ' ';
                    }
                    text += it.s;
                    endX = it.x + it.w;
                });
                text = text.replace(/\s+/g, ' ').trim();
                if (text) {
                    lines.push(text);
                    layout.push([p, Math.round(row.y * 10) / 10]);
                }
            });
            say('Reading page ' + p + ' of ' + pdf.numPages + '…');
        }
        return { lines: lines, layout: layout, pages: pdf.numPages };
    }
    // Reachable from the console for checking what a given PDF reads as.
    window.bankStmtExtractLines = extractLines;

    button.addEventListener('click', function () {
        var f = fileInput.files && fileInput.files[0];
        if (!f) return;
        enable(false);
        say('Reading ' + f.name + '…');
        f.arrayBuffer().then(extractLines).then(function (result) {
            if (!result.lines.length) {
                enable(true);
                say('No text could be read from this PDF — it looks like a scanned image. Ask the bank for the digital (text) statement.', true);
                return;
            }
            document.getElementById('bankStmtText').value     = result.lines.join('\n');
            document.getElementById('bankStmtLayout').value   = JSON.stringify(result.layout);
            document.getElementById('bankStmtFileName').value = f.name;
            document.getElementById('bankStmtPages').value    = result.pages;
            say('Comparing ' + result.lines.length + ' lines from ' + result.pages + ' page(s) with the ledger…');
            form.submit();
        }).catch(function (err) {
            enable(true);
            say((err && err.message) ? err.message : 'The PDF could not be read.', true);
        });
    });
})();
</script>
@endsection
