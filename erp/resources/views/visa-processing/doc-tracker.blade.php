@extends('layout.app')
@section('meta-information')
    <title>Visa Documents Tracker</title>
@endsection
@section('css')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    @include('layout.table-design')
    <style>
        .dt-wrap { overflow-x: auto; }
        .dt-table { border-collapse: collapse; min-width: 100%; font-size: 12px; }
        .dt-table th {
            background: #f8fafc;
            color: #475569;
            font-weight: 700;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .05em;
            padding: 10px 10px;
            border-bottom: 2px solid #e2e8f0;
            white-space: nowrap;
            text-align: center;
        }
        .dt-table th.left { text-align: left; }
        .dt-table td {
            padding: 9px 10px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
            text-align: center;
        }
        .dt-table td.left { text-align: left; }
        .dt-table tr:hover td { background: #f8fafc; }
        .doc-btn {
            width: 28px; height: 28px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: transform .1s, opacity .15s;
        }
        .doc-btn:hover { opacity: .8; transform: scale(1.1); }
        .doc-received     { background: #dcfce7; color: #16a34a; }
        .doc-pending      { background: #fef9c3; color: #b45309; }
        .doc-not-required { background: #f1f5f9; color: #94a3b8; }
        .app-id  { font-size: 10px; font-weight: 700; color: #64748b; font-family: monospace; }
        .app-name { font-size: 13px; font-weight: 600; color: #1e293b; }
        .app-sub  { font-size: 10px; color: #94a3b8; margin-top: 1px; }
        .status-pill {
            display: inline-block; font-size: 9px; font-weight: 700;
            padding: 2px 7px; border-radius: 20px; text-transform: uppercase; letter-spacing: .05em;
        }
        .pill-pending    { background:#f1f5f9; color:#64748b; }
        .pill-received   { background:#dbeafe; color:#1d4ed8; }
        .pill-in_embassy { background:#fef3c7; color:#92400e; }
        .pill-approved   { background:#dcfce7; color:#15803d; }
        .pill-delivered  { background:#ccfbf1; color:#0f766e; }
        .pill-rejected   { background:#fee2e2; color:#dc2626; }
        .doc-legend { display:flex; gap:14px; font-size:11px; color:#64748b; flex-wrap:wrap; }
        .doc-legend span { display:flex; align-items:center; gap:4px; }
    </style>
@endsection
@section('main-content')
    @php $role = auth()->user()->getRoleNames()->first(); @endphp

    <div class="bg-white rounded-lg shadow-md overflow-hidden mt-0">

        {{-- Header --}}
        <div class="px-6 py-4 bg-blue-600 flex justify-between items-center flex-wrap gap-3">
            <div>
                <h2 class="text-white text-lg font-semibold flex items-center gap-2">
                    <i class="fas fa-file-alt"></i> Visa Documents Tracker
                </h2>
                <p class="text-blue-200 text-xs mt-0.5">Per-application document checklist — click cell to toggle status</p>
            </div>
            <a href="{{ route('role.visa.index', ['role' => $role]) }}"
               class="text-sm bg-white text-blue-700 font-semibold px-4 py-2 rounded-md hover:bg-blue-50 transition">
                <i class="fas fa-th-large mr-1"></i> Application Board
            </a>
        </div>

        {{-- Filter --}}
        <form method="get" class="px-6 py-3 bg-gray-50 border-b flex flex-wrap gap-3 items-end">
            <div class="flex flex-col gap-1 min-w-[180px]">
                <label class="text-xs font-semibold text-gray-500">Country</label>
                <select name="country_id" class="select2-sm border border-gray-200 rounded-md px-2 py-1.5 text-sm" style="min-width:180px">
                    <option value="">All Countries</option>
                    @foreach($countries as $c)
                        <option value="{{ $c->id }}" {{ request('country_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-xs font-semibold text-gray-500">Status</label>
                <select name="status" class="border border-gray-200 rounded-md px-2 py-1.5 text-sm">
                    <option value="">All Status</option>
                    <option value="pending"    {{ request('status')=='pending'    ? 'selected' : '' }}>NEW</option>
                    <option value="received"   {{ request('status')=='received'   ? 'selected' : '' }}>DOC COLLECTION</option>
                    <option value="in_embassy" {{ request('status')=='in_embassy' ? 'selected' : '' }}>IN EMBASSY</option>
                    <option value="approved"   {{ request('status')=='approved'   ? 'selected' : '' }}>APPROVED</option>
                    <option value="delivered"  {{ request('status')=='delivered'  ? 'selected' : '' }}>DELIVERED</option>
                    <option value="rejected"   {{ request('status')=='rejected'   ? 'selected' : '' }}>REJECTED</option>
                </select>
            </div>
            <button type="submit" class="px-4 py-1.5 bg-blue-600 text-white rounded-md text-sm font-semibold hover:bg-blue-700">Apply</button>
            <a href="{{ route('role.visa.doc-tracker', ['role' => $role]) }}" class="px-4 py-1.5 bg-gray-200 text-gray-600 rounded-md text-sm font-semibold hover:bg-gray-300" style="text-decoration:none">Reset</a>
            <div class="ml-auto doc-legend">
                <span><span class="doc-btn doc-received" style="pointer-events:none;width:20px;height:20px;">✅</span> Received</span>
                <span><span class="doc-btn doc-pending" style="pointer-events:none;width:20px;height:20px;">⏳</span> Pending</span>
                <span><span class="doc-btn doc-not-required" style="pointer-events:none;width:20px;height:20px;">—</span> Not Required</span>
            </div>
        </form>

        {{-- Table --}}
        <div class="dt-wrap">
            <table class="dt-table">
                <thead>
                    <tr>
                        <th class="left" style="padding-left:20px">App ID / Applicant</th>
                        <th class="left">Country</th>
                        <th class="left">Status</th>
                        @foreach($standardDocs as $doc)
                            <th>{{ $doc }}</th>
                        @endforeach
                        <th>Overall</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($datas as $visa)
                        @php
                            $docMap = $visa->processDocuments->keyBy('document_name');
                            $receivedCount = $visa->processDocuments->where('status','received')->count();
                            $totalDocs = count($standardDocs);
                        @endphp
                        <tr>
                            <td class="left" style="padding-left:20px">
                                <div class="app-id">{{ $visa->application_id ?? 'VISA-'.$visa->id }}</div>
                                <div class="app-name">{{ $visa->passportHolder?->name ?? '—' }}</div>
                            </td>
                            <td class="left">
                                <div style="font-size:12px;color:#1e293b;">{{ $visa->country?->name ?? '—' }}</div>
                                <div class="app-sub">{{ $visa->visaCategory?->name ?? '' }}</div>
                            </td>
                            <td>
                                @php
                                    $statusLabels = [
                                        'pending'    => ['NEW',          'pill-pending'],
                                        'received'   => ['DOC COLLECT',  'pill-received'],
                                        'in_embassy' => ['IN EMBASSY',   'pill-in_embassy'],
                                        'approved'   => ['APPROVED',     'pill-approved'],
                                        'delivered'  => ['DELIVERED',    'pill-delivered'],
                                        'rejected'   => ['REJECTED',     'pill-rejected'],
                                    ];
                                    [$lbl, $cls] = $statusLabels[$visa->status] ?? ['—','pill-pending'];
                                @endphp
                                <span class="status-pill {{ $cls }}">{{ $lbl }}</span>
                            </td>
                            @foreach($standardDocs as $doc)
                                @php
                                    $docRecord = $docMap->get($doc);
                                    $docStatus = $docRecord?->status ?? 'pending';
                                    $btnClass = match($docStatus) {
                                        'received'     => 'doc-received',
                                        'not_required' => 'doc-not-required',
                                        default        => 'doc-pending',
                                    };
                                    $btnIcon = match($docStatus) {
                                        'received'     => '✅',
                                        'not_required' => '—',
                                        default        => '⏳',
                                    };
                                    $encodedDoc = urlencode($doc);
                                @endphp
                                <td>
                                    <button class="doc-btn {{ $btnClass }} toggle-doc"
                                        data-visa="{{ $visa->id }}"
                                        data-doc="{{ $doc }}"
                                        title="{{ $doc }}: {{ ucfirst(str_replace('_',' ',$docStatus)) }}">
                                        {{ $btnIcon }}
                                    </button>
                                </td>
                            @endforeach
                            <td>
                                @php $pct = $totalDocs > 0 ? round($receivedCount / $totalDocs * 100) : 0; @endphp
                                <div style="font-size:11px;font-weight:700;color:{{ $pct==100 ? '#16a34a' : ($pct>50 ? '#b45309' : '#dc2626') }}">
                                    {{ $receivedCount }}/{{ $totalDocs }}
                                </div>
                                <div style="background:#e2e8f0;border-radius:4px;height:4px;width:60px;margin:3px auto 0;">
                                    <div style="background:{{ $pct==100 ? '#16a34a' : ($pct>50 ? '#f59e0b' : '#ef4444') }};height:4px;border-radius:4px;width:{{ $pct }}%"></div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($standardDocs) + 4 }}" class="text-center py-10 text-gray-400">
                                <i class="fas fa-inbox fa-2x mb-2 block"></i>No visa applications found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 border-t border-gray-100">
            {{ $datas->appends(request()->all())->links() }}
        </div>
    </div>
@endsection

@section('js')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function () {
            $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

            const role = '{{ Str::slug(auth()->user()->getRoleNames()->first()) }}';

            $(document).on('click', '.toggle-doc', function () {
                const btn     = $(this);
                const visaId  = btn.data('visa');
                const docName = btn.data('doc');

                btn.prop('disabled', true).css('opacity', .5);

                $.post(`/${role}/visa/${visaId}/doc-toggle`, { doc: docName }, function (res) {
                    if (!res.success) return;

                    const icons   = { received: '✅', pending: '⏳', not_required: '—' };
                    const classes = { received: 'doc-received', pending: 'doc-pending', not_required: 'doc-not-required' };

                    btn.removeClass('doc-received doc-pending doc-not-required')
                       .addClass(classes[res.status])
                       .text(icons[res.status])
                       .attr('title', docName + ': ' + res.status.replace('_', ' '));

                    // Recount received in this row
                    const row = btn.closest('tr');
                    let received = 0;
                    const total = {{ count($standardDocs) }};
                    row.find('.toggle-doc').each(function () {
                        if ($(this).hasClass('doc-received')) received++;
                    });
                    const pct = total > 0 ? Math.round(received / total * 100) : 0;
                    const color = pct === 100 ? '#16a34a' : (pct > 50 ? '#b45309' : '#dc2626');
                    const barColor = pct === 100 ? '#16a34a' : (pct > 50 ? '#f59e0b' : '#ef4444');
                    const overallCell = row.find('td:last');
                    overallCell.find('div:first').css('color', color).text(received + '/' + total);
                    overallCell.find('div:last div').css({ background: barColor, width: pct + '%' });
                }).fail(function () {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to update document status.', timer: 2000 });
                }).always(function () {
                    btn.prop('disabled', false).css('opacity', 1);
                });
            });
        });
    </script>
@endsection
