{{--
    One report card on the payroll overview — the Loans/Payslip table treatment,
    wrapped so the six sections cannot drift apart.

    The table itself comes in as $slot content via @slot or a rendered partial,
    because the columns differ per section; everything around it — the card, the
    header, the record count, the Export/PDF pair and the empty state — is here.

    $title    — card heading
    $icon     — Font Awesome class
    $sub      — the line under the heading
    $section  — which section the export buttons should ask for
    $count    — rows in scope; drives the count and disables the buttons at zero
    $right    — optional extra markup in the header (a period picker, say)
    $emptyMsg — what to say when there is nothing
--}}
@php
    $role = $role ?? Str::slug(Auth::user()->getRoleNames()->first());
    $count = $count ?? 0;
@endphp

<div class="states-table bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="states-table-container">
        <div class="px-6 py-4 flex flex-wrap justify-between items-center gap-3 border-b border-gray-100">
            <div>
                <h2 class="text-gray-800 text-base font-bold">
                    <i class="fas {{ $icon }} mr-2 text-blue-500"></i>{{ $title }}
                </h2>
                <p class="text-xs text-gray-400 mt-0.5">{{ $sub }}</p>
            </div>
            <div class="flex items-center gap-3 shrink-0">
                {!! $right ?? '' !!}
                <span class="text-xs text-gray-400">{{ number_format($count) }} {{ Str::plural('record', $count) }}</span>
                @include('payroll.partials.export-buttons', [
                    'routePrefix' => 'role.report.payroll.overview.export',
                    'table'       => null,
                    'count'       => $count,
                    'exportRole'  => $role,
                    'pageKeys'    => ['page'],
                    'extraQuery'  => ['section' => $section],
                ])
            </div>
        </div>

        <div class="states-table-content">
            @if($count > 0)
                <div class="table-responsive overflow-x-auto" style="padding: 15px">
                    {{ $slot }}
                </div>
            @else
                <div class="text-center py-12">
                    <div class="flex flex-col items-center gap-2">
                        <div class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center">
                            <i class="fas {{ $icon }} fa-2x text-gray-300"></i>
                        </div>
                        <h4 class="text-gray-500 text-base font-semibold mt-1">{{ $emptyTitle ?? 'Nothing to show' }}</h4>
                        <p class="text-gray-400 text-sm">{{ $emptyMsg ?? '' }}</p>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
