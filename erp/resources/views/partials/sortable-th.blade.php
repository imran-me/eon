{{--
    Sortable column header for a paginated, server-sorted list.

    Usage, inside a <th>:

        @include('partials.sortable-th', ['key' => 'date', 'label' => 'Date'])
        @include('partials.sortable-th', ['key' => 'amount', 'label' => 'Amount', 'dirDefault' => 'desc'])

    The controller decides what 'key' means — it maps the key to a column
    through its own whitelist, so nothing from the URL reaches SQL.

    SERVER-side on purpose. These lists are paginated, so reordering rows in the
    browser would sort only the twenty rows on screen while the header claimed
    the whole table was sorted — on a finance list that is worse than no sorting
    at all, because the top row looks like the largest amount and is not.

    Self-contained: it is a plain link, and its CSS is emitted once per page via
    @once no matter how many columns include it. Nothing is added to app.css or
    any shared layout, so a table that does not include this partial renders
    exactly as it did before.

    Params:
      key        - sort key sent as ?sort=
      label      - the visible column heading
      dirDefault - direction on the FIRST click (default 'asc'; use 'desc' for
                   dates and money, where newest/largest first is expected)
--}}
@php
    $sortKey     = $key;
    $sortLabel   = $label;
    $sortDefault = ($dirDefault ?? 'asc') === 'desc' ? 'desc' : 'asc';

    // is_string before any cast. ?sort[]=x arrives as an array, and casting one
    // to string raises a PHP warning that Laravel promotes to an ErrorException
    // — a 500 while rendering a header. Anything non-string means "no sort".
    $sortCurrentRaw = request('sort');
    $sortDirRaw     = request('dir');
    $sortCurrent    = is_string($sortCurrentRaw) ? $sortCurrentRaw : null;
    $sortDir        = (is_string($sortDirRaw) && strtolower($sortDirRaw) === 'asc') ? 'asc' : 'desc';
    $sortActive     = $sortCurrent !== null && $sortCurrent === $sortKey;

    // Clicking the active column flips it; a new column opens in its own
    // natural direction.
    $sortNext = $sortActive ? ($sortDir === 'asc' ? 'desc' : 'asc') : $sortDefault;

    // Keep every other parameter so filters survive the click, but drop `page`:
    // after re-sorting, page 4 of the previous order means nothing.
    $sortUrl = request()->fullUrlWithQuery(['sort' => $sortKey, 'dir' => $sortNext, 'page' => null]);
@endphp

@once
<style>
    .sort-th { display: inline-flex; align-items: center; gap: 5px; color: inherit; text-decoration: none; }
    .sort-th:hover { color: #4f46e5; text-decoration: none; }
    .sort-th .sort-th-icon { font-size: 0.62rem; opacity: 0; transition: opacity .15s; }
    .sort-th:hover .sort-th-icon { opacity: 0.45; }
    .sort-th.is-active-sort { color: #4f46e5; }
    .sort-th.is-active-sort .sort-th-icon { opacity: 1; }
</style>
@endonce

{{-- The sort state goes in aria-label, not aria-sort: aria-sort is only
     meaningful on the th/columnheader itself, and this partial renders inside
     the th, so on a link it is ignored and a screen reader would announce a
     sorted and an unsorted column identically. --}}
<a href="{{ $sortUrl }}"
   class="sort-th {{ $sortActive ? 'is-active-sort' : '' }}"
   title="Sort by {{ $sortLabel }}"
   aria-label="{{ $sortLabel }}{{ $sortActive ? ', sorted ' . ($sortDir === 'asc' ? 'ascending' : 'descending') . ', activate to reverse' : ', activate to sort' }}">
    {{ $sortLabel }}
    <i class="fas {{ $sortActive ? ($sortDir === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort' }} sort-th-icon" aria-hidden="true"></i>
</a>
