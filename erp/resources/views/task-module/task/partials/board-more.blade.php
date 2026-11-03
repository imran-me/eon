{{-- Last child of a "done" column's list: the infinite-scroll sentinel.
     It lives INSIDE .task-list so refreshBoardListsInPlace() (which swaps the
     list's innerHTML) brings a fresh, correctly-counted one along with the cards.
     State rides on it for that reason too. --}}
@php
    $kbcRemaining = max(0, $kbcLazy['total'] - $kbcLazy['loaded']);
    $kbcQs = http_build_query(array_filter([
        'done'      => $doneWindow['key'] ?? 'week',
        'done_from' => ($doneWindow['key'] ?? '') === 'custom' ? ($doneWindow['from_in'] ?? null) : null,
        'done_to'   => ($doneWindow['key'] ?? '') === 'custom' ? ($doneWindow['to_in'] ?? null) : null,
    ]));
@endphp
<div class="kbc-more"
     data-kbc-more
     data-url="{{ route('role.tasks.board.column', ['role' => request()->route('role'), 'board' => $board->id, 'column' => $column->column->id]) }}?{{ $kbcQs }}"
     data-offset="{{ $kbcLazy['loaded'] }}"
     data-remaining="{{ $kbcRemaining }}">
    <div class="kbc-skel-wrap" hidden>
        @for($i = 0; $i < 3; $i++)
            <div class="kbc-skel" aria-hidden="true">
                <div class="kbc-skel-line" style="width:82%"></div>
                <div class="kbc-skel-line" style="width:55%"></div>
                <div class="kbc-skel-row"><span class="kbc-skel-dot"></span><span class="kbc-skel-dot"></span></div>
            </div>
        @endfor
    </div>
    <div class="kbc-more-msg" data-kbc-msg>
        @if($kbcLazy['total'] === 0)
            No completed tasks in this period
        @elseif($kbcRemaining === 0)
            All {{ $kbcLazy['total'] }} shown
        @endif
    </div>
    <button type="button" class="kbc-more-retry" hidden onclick="kbcLoadMore(this.closest('[data-kbc-more]'))">
        Couldn't load · Try again
    </button>
</div>
