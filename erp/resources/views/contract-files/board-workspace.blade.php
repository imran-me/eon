{{-- Passenger Board — Contract Flight's board (contract-flights/board.blade.php),
     same header, filter panel, columns and cards (owner, 2026-10-25: "make the
     contract file according to the Contract Flight"). A card is dragged to
     another column to change the file's stage, saved through the FILE stage
     endpoint; a click opens the file record. kb- styles are copied from the
     flight board and scoped under #cfb-workspace so they reach nothing else. --}}
@php
 $cfbFiltered = collect(['search','client_id','contract_file_category_id','from_date','to_date'])->contains(fn($k) => filled(request($k)));
 $cfbCanMove = (bool) auth()->user()?->can('edit contract file');
 $cfbColors = [
  'file_received' => '#94a3b8', 'information_entered' => '#64748b',
  'documents_pending' => '#0ea5e9', 'documents_verified' => '#0284c7',
  'application_prepared' => '#6366f1', 'submitted_to_vendor' => '#4f46e5',
  'biometrics_appointment' => '#2563eb', 'under_process' => '#7c3aed', 'additional_documents' => '#ea580c',
  'decision_received' => '#9333ea', 'approved' => '#0d9488', 'passport_ready' => '#0891b2',
  'delivered' => '#16a34a', 'case_closed' => '#15803d',
 ];
@endphp
<div class="bg-white rounded-lg shadow-md overflow-hidden mt-0" id="cfb-workspace">
 <div class="kb-board-header">
  <div class="kb-board-title">
   <i class="fas fa-columns text-blue-600"></i>
   Contract File — Passenger Board
   <span style="font-size:12px;font-weight:500;color:#64748b;">{{ $boardFiles->count() }} total</span>
  </div>
  <div class="kb-board-actions">
   <button class="kb-btn-filter" id="cfbToggleFilter" type="button" aria-expanded="{{ $cfbFiltered ? 'true' : 'false' }}" aria-controls="cfbFilterPanel"><i class="fas fa-filter"></i> Filter</button>
   <a href="{{ route('role.contract-files.index', ['role' => $role]) }}" class="kb-btn-new"><i class="fas fa-clipboard-list"></i> Files &amp; Passengers</a>
   @can('create contract file')<button type="button" class="kb-btn-new create-btn"><i class="fas fa-plus"></i> Add File</button>@endcan
  </div>
 </div>

 <form action="" method="get" id="cfbFilterForm">
  <div class="kb-filter-panel{{ $cfbFiltered ? ' open' : '' }}" id="cfbFilterPanel">
   <div class="kb-filter-group kb-filter-wide"><label for="cfbSearch">Search</label><input type="search" id="cfbSearch" name="search" value="{{ request('search') }}" placeholder="Applicant, passport, file, agent or vendor"></div>
   <div class="kb-filter-group"><label for="cfbClient">Agent / Client</label><select id="cfbClient" name="client_id"><option value="">All</option>@foreach($clients as $client)<option value="{{ $client->id }}" @selected(request('client_id')==$client->id)>{{ $client->name }}</option>@endforeach</select></div>
   <div class="kb-filter-group"><label for="cfbCategory">Category</label><select id="cfbCategory" name="contract_file_category_id"><option value="">All</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(request('contract_file_category_id')==$category->id)>{{ $category->name }}</option>@endforeach</select></div>
   <div class="kb-filter-group"><label for="cfbFrom">Submit from</label><input type="date" id="cfbFrom" name="from_date" value="{{ request('from_date') }}"></div>
   <div class="kb-filter-group"><label for="cfbTo">Submit to</label><input type="date" id="cfbTo" name="to_date" value="{{ request('to_date') }}"></div>
   @if(request()->boolean('unassigned'))<input type="hidden" name="unassigned" value="1">@endif
   <div class="kb-filter-actions">
    <button type="submit" class="kb-filter-apply">Apply</button>
    <a href="{{ route('role.contract-files.board', ['role' => $role]) }}" class="kb-filter-reset">Reset</a>
    <label class="kb-filter-check"><input type="checkbox" id="cfb-hide-empty"> Hide empty stages</label>
   </div>
  </div>
 </form>

 <div class="kb-board" id="cfbBoard" aria-label="File processing stages">
  @foreach($statuses as $key => $label)
   @php $cards = $boardFiles->where('status', $key); @endphp
   <div class="kb-col" data-stage="{{ $key }}">
    <div class="kb-col-h">
     <span class="kb-col-dot" style="background:{{ $cfbColors[$key] ?? '#94a3b8' }}"></span>
     {{ $label }}
     <span class="kb-col-count">{{ $cards->count() }}</span>
    </div>
    <div class="kb-col-cards">
    @forelse($cards as $file)
     <div class="kb-card" @if($cfbCanMove) draggable="true" @endif tabindex="0" role="button"
          data-file-id="{{ $file->id }}" data-stage="{{ $key }}" data-label="{{ $file->file_number }} · {{ $file->applicant_name }}"
          data-url="{{ route('role.contract-files.stage', ['role' => $role, 'contractFile' => $file]) }}"
          data-show="{{ route('role.contract-files.show', ['role' => $role, 'contractFile' => $file]) }}"
          aria-label="Open {{ $file->file_number }} · {{ $file->applicant_name }}"
          style="border-left-color:{{ $cfbColors[$key] ?? '#94a3b8' }}">
      <div class="kb-card-name">{{ $file->applicant_name ?: '—' }}</div>
      <div class="kb-card-meta">
       {{ $file->file_number }}@if($file->country) · {{ $file->country->name }}@endif
       @if($file->expected_out_date)<br><i class="fas fa-calendar-day" style="opacity:.6"></i> Expected {{ $file->expected_out_date->format('d M Y') }}@endif
       <br>Agent: {{ $file->client?->name ?? 'Direct' }}
       @if($file->passport_no)<br><span style="color:#7c3aed;"><i class="fas fa-passport"></i> {{ $file->passport_no }}</span>@endif
       <br><span style="color:#64748b;"><i class="fas fa-file-alt" style="opacity:.6"></i> Documents {{ $file->documents_received }} / {{ $file->documents_total }}</span>
       @if($file->net_client_charge > 0)
        @php $cfbProfit = $file->net_client_charge - $file->total_file_cost; @endphp
        <br><span style="color:#2563eb;font-weight:600;">৳{{ number_format($file->net_client_charge, 0) }}</span>
        <span style="color:{{ $cfbProfit >= 0 ? '#16a34a' : '#dc2626' }};font-size:10px;"> · Profit ৳{{ number_format($cfbProfit, 0) }}</span>
       @endif
       @if($file->client_due > 0)<br><span style="color:#dc2626;font-size:10px;font-weight:600;">Due ৳{{ number_format($file->client_due, 0) }}</span>@endif
       @if($file->exception_flag)<br><span class="kb-exc-badge">{{ \App\Models\ContractFile::EXCEPTION_FLAGS[$file->exception_flag] ?? $file->exception_flag }}</span>@endif
      </div>
      <div class="kb-card-foot"><span>{{ $file->created_at?->diffForHumans() }}</span></div>
     </div>
    @empty
     <div class="kb-empty"><i class="fas fa-inbox"></i><br>No records</div>
    @endforelse
    </div>
   </div>
  @endforeach
 </div>
</div>
<style>
/* contract-flights/board.blade.php's sheet, under #cfb-workspace. */
#cfb-workspace .kb-board { display: flex; gap: 14px; overflow-x: auto; padding: 16px; min-height: calc(100vh - 200px); align-items: flex-start; }
#cfb-workspace .kb-col { flex: 0 0 230px; min-width: 230px; background: #f8fafc; border-radius: 12px; padding: 10px; display: flex; flex-direction: column; gap: 8px; max-height: calc(100vh - 260px); }
#cfb-workspace .kb-col[hidden] { display: none; }
#cfb-workspace .kb-col-h { display: flex; align-items: center; gap: 7px; font-size: 11px; font-weight: 700; letter-spacing: .06em; color: #475569; padding: 4px 4px 8px; border-bottom: 1px solid #e2e8f0; margin-bottom: 2px; flex-shrink: 0; }
#cfb-workspace .kb-col-cards { display: flex; flex-direction: column; gap: 8px; overflow-y: auto; flex: 1; min-height: 0; }
#cfb-workspace .kb-col-dot { width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0; }
#cfb-workspace .kb-col-count { margin-left: auto; background: #e2e8f0; color: #475569; font-size: 10px; font-weight: 700; padding: 1px 6px; border-radius: 20px; }
#cfb-workspace .kb-card { background: #fff; border-radius: 9px; border-left: 3px solid #94a3b8; box-shadow: 0 1px 4px rgba(0,0,0,.07); padding: 10px 11px; cursor: pointer; transition: box-shadow .15s, transform .1s; }
#cfb-workspace .kb-card:hover { box-shadow: 0 4px 14px rgba(0,0,0,.12); transform: translateY(-1px); }
#cfb-workspace .kb-card:focus-visible { outline: 2px solid #6366f1; outline-offset: 2px; }
#cfb-workspace .kb-card-name { font-size: 13px; font-weight: 600; color: #1e293b; margin-bottom: 4px; }
#cfb-workspace .kb-card-meta { font-size: 11px; color: #64748b; line-height: 1.5; margin-bottom: 6px; }
#cfb-workspace .kb-card-foot { display: flex; justify-content: space-between; font-size: 10px; color: #94a3b8; border-top: 1px solid #f1f5f9; padding-top: 5px; margin-top: 4px; }
#cfb-workspace .kb-empty { text-align: center; color: #cbd5e1; font-size: 11px; padding: 20px 0; }
#cfb-workspace .kb-board-header { background: #fff; border-bottom: 1px solid #e2e8f0; padding: 14px 18px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; }
#cfb-workspace .kb-board-title { font-size: 17px; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 8px; }
#cfb-workspace .kb-board-actions { display: flex; gap: 8px; }
#cfb-workspace .kb-btn-filter { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; border-radius: 7px; padding: 6px 14px; font-size: 12px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 5px; }
#cfb-workspace .kb-btn-new { background: #2563eb; color: #fff; border: none; border-radius: 7px; padding: 7px 16px; font-size: 12px; font-weight: 600; cursor: pointer; text-decoration: none; display: flex; align-items: center; gap: 5px; }
#cfb-workspace .kb-btn-new:hover { background: #1d4ed8; color: #fff; text-decoration: none; }
#cfb-workspace .kb-filter-panel { background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 12px 18px; display: none; gap: 12px; flex-wrap: wrap; align-items: flex-end; }
#cfb-workspace .kb-filter-panel.open { display: flex; }
#cfb-workspace .kb-filter-group { display: flex; flex-direction: column; gap: 4px; min-width: 160px; flex: 1; }
#cfb-workspace .kb-filter-group.kb-filter-wide { flex: 2 1 220px; }
#cfb-workspace .kb-filter-group label { font-size: 11px; font-weight: 600; color: #64748b; }
#cfb-workspace .kb-filter-group select, #cfb-workspace .kb-filter-group input { font-size: 12px; border: 1px solid #e2e8f0; border-radius: 6px; padding: 5px 8px; background: #fff; color: #1e293b; box-sizing: border-box; width: 100%; }
#cfb-workspace .kb-filter-actions { display: flex; gap: 6px; padding-top: 1px; align-items: center; }
#cfb-workspace .kb-filter-apply { background: #2563eb; color: #fff; border: none; border-radius: 6px; padding: 6px 14px; font-size: 12px; font-weight: 600; cursor: pointer; }
#cfb-workspace .kb-filter-reset { background: #e2e8f0; color: #475569; border: none; border-radius: 6px; padding: 6px 14px; font-size: 12px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; }
#cfb-workspace .kb-filter-check { display: inline-flex; align-items: center; gap: 5px; margin-left: 6px; font-size: 11px; font-weight: 600; color: #64748b; white-space: nowrap; cursor: pointer; }
#cfb-workspace .kb-card.dragging { opacity: .4; transform: rotate(1.5deg) scale(1.02); box-shadow: 0 8px 24px rgba(0,0,0,.18); }
#cfb-workspace .kb-col.drag-over { background: #eff6ff; outline: 2px dashed #3b82f6; outline-offset: -4px; }
#cfb-workspace .kb-col.drag-over .kb-col-h { color: #2563eb; }
#cfb-workspace .kb-exc-badge { display: inline-block; margin-top: 4px; padding: 1px 7px; border-radius: 9px; font-size: 9px; font-weight: 700; background: #fee2e2; color: #dc2626; }
@media print { #cfb-workspace .kb-board-actions, #cfb-workspace .kb-filter-panel { display: none !important; } #cfb-workspace .kb-board { flex-wrap: wrap; overflow: visible; } #cfb-workspace .kb-col { max-height: none; } #cfb-workspace .kb-col-cards { overflow: visible; } }
@media (prefers-reduced-motion: reduce) { #cfb-workspace .kb-card { transition: none; } }
</style>
<script>
(() => {
 const root = document.getElementById('cfb-workspace');
 if (!root) return;
 const colors = @json($cfbColors);
 const toggleBtn = document.getElementById('cfbToggleFilter'), panel = document.getElementById('cfbFilterPanel');
 toggleBtn.addEventListener('click', () => { const open = panel.classList.toggle('open'); toggleBtn.setAttribute('aria-expanded', String(open)); });
 const hideEmpty = document.getElementById('cfb-hide-empty');
 function refresh() {
  root.querySelectorAll('.kb-col').forEach(col => {
   const box = col.querySelector('.kb-col-cards'), count = box.querySelectorAll('.kb-card').length;
   col.querySelector('.kb-col-count').textContent = count;
   const empty = box.querySelector('.kb-empty');
   if (count === 0 && !empty) { const e = document.createElement('div'); e.className = 'kb-empty'; e.innerHTML = '<i class="fas fa-inbox"></i><br>No records'; box.appendChild(e); }
   if (count > 0 && empty) empty.remove();
   col.hidden = hideEmpty.checked && count === 0;
  });
 }
 hideEmpty.addEventListener('change', refresh);

 // A click (not a drag) opens the file record — the record sheet where the page has it.
 let didDrag = false;
 const open = card => { if (window.openFileSheet) openFileSheet(card.dataset.fileId); else location.href = card.dataset.show; };
 root.querySelectorAll('.kb-card').forEach(card => {
  card.addEventListener('click', () => { if (didDrag) { didDrag = false; return; } open(card); });
  card.addEventListener('keydown', e => { if (e.target === card && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); open(card); } });
 });

 // Drag-and-drop stage update — the flight board's mechanics, the file's endpoint.
 let dragged = null;
 root.querySelectorAll('.kb-card[draggable="true"]').forEach(card => {
  card.addEventListener('dragstart', () => { dragged = card; didDrag = true; setTimeout(() => card.classList.add('dragging'), 0); });
  card.addEventListener('dragend', () => { card.classList.remove('dragging'); root.querySelectorAll('.kb-col').forEach(c => c.classList.remove('drag-over')); dragged = null; });
 });
 root.querySelectorAll('.kb-col').forEach(col => {
  col.addEventListener('dragover', e => { if (!dragged) return; e.preventDefault(); col.classList.add('drag-over'); });
  col.addEventListener('dragleave', e => { if (!col.contains(e.relatedTarget)) col.classList.remove('drag-over'); });
  col.addEventListener('drop', async e => {
   e.preventDefault(); col.classList.remove('drag-over');
   const card = dragged; if (!card) return;
   const from = card.closest('.kb-col'), oldStage = card.dataset.stage, newStage = col.dataset.stage;
   if (newStage === oldStage) return;
   const move = (target, stage) => { target.querySelector('.kb-col-cards').appendChild(card); card.dataset.stage = stage; card.style.borderLeftColor = colors[stage] || '#94a3b8'; refresh(); };
   move(col, newStage);
   try {
    const response = await fetch(card.dataset.url, { method: 'PATCH', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' }, body: JSON.stringify({ stage: newStage }) });
    const result = await response.json().catch(() => ({}));
    if (!response.ok || !result.success) throw new Error(result.message || 'Stage update failed.');
   } catch (error) {
    move(from, oldStage);
    if (typeof Swal !== 'undefined') Swal.fire('Error', error.message, 'error'); else alert(error.message);
   }
  });
 });
})();
</script>
