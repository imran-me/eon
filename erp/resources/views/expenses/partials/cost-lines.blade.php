{{--
    COST LINES — what the expense is actually made of.

    One expense is often several things on one payment: a market run is rice 500 +
    oil 300 + gas 1200. These lines are that breakdown, and their sum IS the
    expense amount — the total at the top of the form only mirrors it.

    Shared by the create and the edit modal on purpose. The edit modal previously
    had no lines table at all while update() still recomputed the amount from
    submitted lines, so editing an expense sent none and zeroed it.

    $prefix — 'create' or 'edit'
--}}
@php
    $prefix = $prefix ?? 'create';
@endphp

<div class="md:col-span-2">
    <div class="flex items-center justify-between gap-3 mb-2">
        <label class="block text-sm font-semibold text-gray-700">
            Cost Lines <span class="text-red-500">*</span>
        </label>
        <button type="button" onclick="addItemRow('{{ $prefix }}')"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold border border-teal-300 bg-teal-50 text-teal-700 hover:bg-teal-600 hover:text-white hover:border-teal-600 transition-colors">
            <i class="fas fa-plus"></i> Add Line
        </button>
    </div>

    <div class="overflow-x-auto rounded border border-gray-200">
        <table class="w-full text-sm" style="min-width: 400px">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-3 py-2 text-left border-b text-gray-600 font-medium">Description</th>
                    <th class="px-3 py-2 text-right border-b text-gray-600 font-medium w-36">Amount</th>
                    <th class="px-3 py-2 border-b w-8"></th>
                </tr>
            </thead>
            <tbody id="{{ $prefix }}_items_body"></tbody>
            <tfoot>
                <tr class="bg-gray-50">
                    <td class="px-3 py-2 text-right font-bold text-gray-700 border-t">Total</td>
                    <td class="px-3 py-2 text-right font-bold text-blue-700 border-t" id="{{ $prefix }}_items_total">0.00</td>
                    <td class="border-t"></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <p class="text-[11px] text-gray-500 mt-1">
        <i class="fas fa-circle-info text-gray-400"></i>
        Break the payment into lines if it covered several things — the total above is their sum.
    </p>
    <p id="{{ $prefix }}_items_msg" class="text-red-500 text-xs mt-1 hidden">Add at least one line with an amount above zero</p>
</div>
