{{--
    The period "Where the money went" is read over.

    It rides in the card header beside Export/PDF rather than on a row of its own:
    this report has exactly two variables — the company and the period — and the
    company is already chosen by the toolbar above.
--}}
<form method="get" class="flex items-center gap-2">
    @if($companyId)
        <input type="hidden" name="company_id" value="{{ $companyId }}">
    @endif
    <select name="months" onchange="this.form.submit()"
            class="border border-gray-300 rounded-lg px-3 py-1.5 text-xs font-semibold text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
        @foreach(\App\Services\PayrollOverviewService::PERIODS as $value => $label)
            <option value="{{ $value }}" {{ (int) $months === $value ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
    </select>
</form>
