{{-- Period picker for a "done" column. Scoped to this column only; the other
     columns are never filtered. Needs $doneWindow and $kbcLazy from show. --}}
@php
    $kbcPresets = [
        'today'     => 'Today',
        'yesterday' => 'Yesterday',
        'week'      => 'Last 7 days',
        'month'     => 'This month',
        'days30'    => 'Last 30 days',
        'all'       => 'All time',
        'custom'    => 'Pick dates…',
    ];
    $kbcKey = $doneWindow['key'] ?? 'week';
@endphp
<div class="kbc-filter" data-kbc-filter>
    <select class="kbc-filter-select" onchange="kbcDoneFilter(this.closest('[data-kbc-filter]'))" aria-label="Show completed tasks from">
        @foreach($kbcPresets as $k => $label)
            <option value="{{ $k }}" @selected($kbcKey === $k)>{{ $label }}</option>
        @endforeach
    </select>
    <div class="kbc-filter-dates" @if($kbcKey !== 'custom') hidden @endif>
        <input type="date" class="kbc-filter-date" data-kbc-from value="{{ $doneWindow['from_in'] ?? '' }}" aria-label="From">
        <input type="date" class="kbc-filter-date" data-kbc-to value="{{ $doneWindow['to_in'] ?? '' }}" aria-label="To">
        <button type="button" class="kbc-filter-apply" onclick="kbcDoneFilter(this.closest('[data-kbc-filter]'))">Apply</button>
    </div>
</div>
