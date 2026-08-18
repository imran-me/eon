{{--
    A loan table: heading, columns, rows, pager.

    Used twice on every book — once for what is still active and once for the
    complete register — so the two can never fall out of step about which columns
    exist or what they mean.

    Expects: $rows (paginator), $book, $role, $title, $subtitle, $emptyText
    Optional: $sortable (default true), $anchor
--}}
@php
    $sortable = $sortable ?? true;
    $cols     = 9 + (auth()->user()->can('create financing') ? 1 : 0);
@endphp
<div class="fin-card" style="margin-bottom:1.15rem;" @isset($anchor) id="{{ $anchor }}" @endisset>
    <div class="fin-card-head">
        <strong>{{ $title }}</strong>
        <span class="fin-sub" style="margin:0;">{{ $subtitle }}</span>
    </div>

    <div style="overflow-x:auto;">
        <table class="fin-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>
                        @if($sortable)
                            @include('partials.sortable-th', ['key' => 'counterparty_name', 'label' => $book === 'borrowed' ? 'Lender' : 'Borrower'])
                        @else
                            {{ $book === 'borrowed' ? 'Lender' : 'Borrower' }}
                        @endif
                    </th>
                    <th>
                        @if($sortable)
                            @include('partials.sortable-th', ['key' => 'kind', 'label' => 'Kind'])
                        @else
                            Kind
                        @endif
                    </th>
                    <th>
                        @if($sortable)
                            @include('partials.sortable-th', ['key' => 'start_date', 'label' => 'Started', 'dirDefault' => 'desc'])
                        @else
                            Started
                        @endif
                    </th>
                    <th style="text-align:right;">
                        @if($sortable)
                            @include('partials.sortable-th', ['key' => 'principal', 'label' => 'Principal', 'dirDefault' => 'desc'])
                        @else
                            Principal
                        @endif
                    </th>
                    <th style="text-align:right;">
                        @if($sortable)
                            @include('partials.sortable-th', ['key' => 'interest_rate', 'label' => 'Rate', 'dirDefault' => 'desc'])
                        @else
                            Rate
                        @endif
                    </th>
                    <th style="text-align:right;">Outstanding</th>
                    <th>Next due</th>
                    <th>
                        @if($sortable)
                            @include('partials.sortable-th', ['key' => 'status', 'label' => 'Status'])
                        @else
                            Status
                        @endif
                    </th>
                    @can('create financing')
                        <th style="text-align:center;"><i class="fas fa-cogs mr-1 text-gray-400"></i>Actions</th>
                    @endcan
                </tr>
            </thead>
            <tbody>
            @forelse($rows as $i => $loan)
                @include('financing.partials.loan-row', [
                    'loan' => $loan,
                    'idx'  => $rows->firstItem() + $i,
                    'book' => $book,
                    'role' => $role,
                ])
            @empty
                <tr><td colspan="{{ $cols }}" class="fin-empty">
                    <i class="fas {{ $book === 'borrowed' ? 'fa-building-columns' : 'fa-hand-holding-dollar' }}"></i>
                    {{ $emptyText }}
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if($rows->hasPages())
        <div class="fin-pager">{{ $rows->links() }}</div>
    @endif
</div>
