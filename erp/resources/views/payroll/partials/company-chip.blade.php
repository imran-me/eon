{{--
    Which concern a loan belongs to — the same chip Salary Manage puts in its
    Company column, coloured by companyBadgeColor() so one company keeps one
    colour across the whole app.
--}}
@php $chip = companyBadgeColor($company?->id); @endphp

@if($company)
    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold border"
          style="background:{{ $chip['bg'] }};color:{{ $chip['text'] }};border-color:{{ $chip['border'] }};"
          title="{{ $company->name }}">
        {{ $company->short_name ?: $company->name }}
    </span>
@else
    <span class="text-sm text-gray-300">—</span>
@endif
