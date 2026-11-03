{{-- One table bar. The bank feed's bar, element for element; which parts
     appear is decided by the caller.

       $prefix      id prefix — the JS finds every control by it:
                    {prefix}Search, {prefix}SearchWrap, {prefix}Clear,
                    {prefix}Seg, {prefix}Dates, {prefix}From(Text), {prefix}To(Text),
                    {prefix}Apply, {prefix}Print, {prefix}Count
       $placeholder the search box's hint
       $segments    [[key, label], …] for the segmented buttons; [] for none
       $dates       true to draw the from/to boxes and Apply
       $print       true to draw Print and the picking strip; the caller wires
                    it with psPrintSetup (bar-scripts)
       $printHref   optional href for Print (a real print route); default "#"
--}}
@php
    $segments  = $segments ?? [];
    $dates     = $dates ?? false;
    $print     = $print ?? true;
    $printHref = $printHref ?? '#';
@endphp
<div class="pb-bar" id="{{ $prefix }}Bar">
    @if($prefix === 'st')
    {{-- Reversal history lives in this select (owner, 2026-10-27): it opens the popup and the
         select goes back to the view it was on --}}
    <select id="partyTransactionView" onfocus="this.dataset.prev=this.value" onchange="if(this.value==='history'){this.value=this.dataset.prev||'active';showPartyReversalHistory();return;} this.dataset.prev=this.value; if(LAST_STATEMENT) renderStatement(LAST_STATEMENT,true)" aria-label="Transaction view" style="height:34px;padding:0 10px;border:1px solid #dbe2ec;border-radius:8px;background:white;font-size:12px">
        <option value="active">Active transactions</option>
        <option value="all">All transactions</option>
        <option value="reversed">Reversed transactions</option>
        <option value="history">↺ Reversal history</option>
    </select>
    @endif
    <div class="pb-search" id="{{ $prefix }}SearchWrap">
        <i class="fas fa-magnifying-glass"></i>
        <input type="text" id="{{ $prefix }}Search" autocomplete="off"
               placeholder="{{ $placeholder ?? 'Search…' }}" aria-label="Search rows">
        <button type="button" class="pb-clear" id="{{ $prefix }}Clear" title="Clear search"><i class="fas fa-xmark"></i></button>
    </div>
    @if(! empty($typeFilter))
        {{-- Type: what the party deals in (owner, 2026-10-27) — the caller listens for "pb-kind" --}}
        <div class="pb-kind" id="{{ $prefix }}Kind">
            <button type="button" class="pb-kind-btn" aria-haspopup="listbox" aria-label="Filter by type">
                <i class="fas fa-shapes" style="color:#6366f1"></i><span>All types</span><i class="fas fa-chevron-down pb-kind-car"></i>
            </button>
            <div class="pb-kind-menu" role="listbox">
                <button type="button" data-kind="" data-icon="fa-shapes" data-color="#6366f1"><i class="fas fa-shapes" style="color:#6366f1"></i>All types</button>
                @foreach($typeFilter as $key => [$label, $icon, $color])
                <button type="button" data-kind="{{ $key }}" data-icon="{{ $icon }}" data-color="{{ $color }}"><i class="fas {{ $icon }}" style="color:{{ $color }}"></i>{{ $label }}</button>
                @endforeach
            </div>
        </div>
    @endif

    @if($segments)
    <div class="pb-seg" id="{{ $prefix }}Seg">
        @foreach($segments as [$key, $label])
            <button type="button" data-range="{{ $key }}">{{ $label }}</button>
        @endforeach
    </div>
    @endif

    @if($dates)
    <div class="pb-dates" id="{{ $prefix }}Dates">
        {{-- Typed half and picker half of one control: the text box is what you
             read and type (dd/mm/yyyy, whatever the browser locale), the date
             input beside it shows only its calendar button. --}}
        <span class="pb-date">
            <input type="text" id="{{ $prefix }}FromText" class="pb-date-text" placeholder="dd/mm/yyyy"
                   inputmode="numeric" maxlength="10" autocomplete="off" aria-label="From date">
            <input type="date" id="{{ $prefix }}From" class="pb-date-native" aria-label="Pick the from date" title="Pick a date">
        </span>
        <span>to</span>
        <span class="pb-date">
            <input type="text" id="{{ $prefix }}ToText" class="pb-date-text" placeholder="dd/mm/yyyy"
                   inputmode="numeric" maxlength="10" autocomplete="off" aria-label="To date">
            <input type="date" id="{{ $prefix }}To" class="pb-date-native" aria-label="Pick the to date" title="Pick a date">
        </span>
        <button type="button" class="pb-go" id="{{ $prefix }}Apply">Apply</button>
        <button type="button" class="pb-date-reset" id="{{ $prefix }}DateReset" title="Clear date filter" aria-label="Clear date filter">
            <i class="fas fa-xmark"></i> Reset
        </button>
    </div>
    @endif

    @if($print)
    <a href="{{ $printHref }}" id="{{ $prefix }}Print" target="_blank" class="pb-print" title="Print — today, a day, a date range, or the ticked rows"><i class="fas fa-print"></i> Print</a>
    @endif

    <span class="pb-count" id="{{ $prefix }}Count" style="display:none;"></span>
</div>
@if($print)
{{-- Picking mode's strip (psStartPicking): hidden until Print → "Selected …". --}}
<div class="pb-sel" id="{{ $prefix }}Sel">
    <span><i class="fas fa-hand-pointer" style="margin-right:4px;"></i>Tick the rows to print — <span class="pb-sel-n" id="{{ $prefix }}SelN">0</span> selected</span>
    <button type="button" onclick="psPickAll('{{ $prefix }}', true)">Select all shown</button>
    <button type="button" id="{{ $prefix }}SelDay" onclick="psPickWholeDay('{{ $prefix }}')" title="Every row on the days you ticked">Select whole day</button>
    <button type="button" onclick="psPickClear('{{ $prefix }}')">Clear</button>
    <a href="#" id="{{ $prefix }}SelPrint" target="_blank" class="pb-sel-print"><i class="fas fa-print"></i> Print selected</a>
    <button type="button" class="pb-sel-cancel" onclick="psStopPicking('{{ $prefix }}')">Cancel</button>
</div>
@endif
