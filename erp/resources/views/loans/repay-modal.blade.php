{{--
    Money coming back on a loan, recorded by hand.

    Salary EMI is NOT recorded here — payroll writes it against the payslip that
    withheld it, so offering "salary" as a method would let the same instalment be
    booked twice. This form is for the cash or bank repayment that arrives outside
    a payslip: an early settlement, a lump sum, a hand-in.

    The picker offers only loans that still owe something. A cleared loan has
    nothing to collect, and listing it invites a repayment that would have to be
    rejected anyway.
--}}
<div id="repayModal" class="modal fixed inset-0 flex items-center justify-center z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black opacity-50"></div>
    <div class="bg-white w-11/12 md:max-w-lg mx-auto rounded-xl shadow-lg z-50 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center px-6 py-4 border-b border-gray-100">
            <h3 class="text-lg font-bold text-gray-900">
                <i class="fas fa-arrow-rotate-left text-green-600 mr-2"></i>Record a repayment
            </h3>
            <button class="modal-close-repay text-gray-400 hover:text-gray-700"><i class="fas fa-times"></i></button>
        </div>

        <div class="px-6 py-5">
            <p id="repayError" class="hidden mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm px-3 py-2"></p>

            <form id="repayForm">
                @csrf

                <div class="mb-4">
                    <label for="repay_loan_id" class="block text-gray-700 text-sm font-bold mb-2">Loan</label>
                    <select id="repay_loan_id" name="loan_id"
                            class="form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Select the loan this money is coming back on</option>
                        @foreach($openLoans as $openLoan)
                            <option value="{{ $openLoan->id }}"
                                    data-user="{{ $openLoan->user_id }}"
                                    data-due="{{ $openLoan->outstanding }}">
                                {{ $openLoan->user?->name ?? 'Employee #' . $openLoan->user_id }}
                                — ৳{{ number_format($openLoan->amount) }}
                                taken {{ $openLoan->start_date ? \Carbon\Carbon::parse($openLoan->start_date)->format('M Y') : '' }}
                                (৳{{ number_format($openLoan->outstanding) }} due)
                            </option>
                        @endforeach
                    </select>
                    <p id="repay_due_hint" class="text-xs text-gray-500 mt-1"></p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="mb-4">
                        <label for="repay_amount" class="block text-gray-700 text-sm font-bold mb-2">Amount</label>
                        <input type="number" step="0.01" min="0.01" id="repay_amount" name="amount"
                               class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="0.00">
                    </div>

                    <div class="mb-4">
                        <label for="repay_date" class="block text-gray-700 text-sm font-bold mb-2">Paid on</label>
                        <input type="date" id="repay_date" name="date" value="{{ now()->toDateString() }}"
                               class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div class="mb-4">
                        <label for="repay_method" class="block text-gray-700 text-sm font-bold mb-2">How it came back</label>
                        <select id="repay_method" name="method"
                                class="form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="cash">Cash</option>
                            <option value="bank">Bank</option>
                        </select>
                    </div>

                    <div class="mb-4 hidden" id="repay_bank_row">
                        <label for="repay_bank_id" class="block text-gray-700 text-sm font-bold mb-2">Into which account</label>
                        <select id="repay_bank_id" name="bank_id"
                                class="form-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select an account</option>
                            @foreach($banks as $bank)
                                <option value="{{ $bank->id }}">{{ $bank->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="mb-2">
                    <label for="repay_note" class="block text-gray-700 text-sm font-bold mb-2">Note <span class="font-normal text-gray-400">(optional)</span></label>
                    <input type="text" id="repay_note" name="note" maxlength="255"
                           class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="e.g. settled early in full">
                </div>

                <p class="text-xs text-gray-400 mt-3">
                    Cash and bank repayments post to the ledger as they are recorded. A salary
                    EMI is not recorded here — payroll books it against the payslip that
                    withheld it.
                </p>
            </form>
        </div>

        <div class="flex justify-end gap-2 px-6 py-4 border-t border-gray-100">
            <button type="button" class="modal-close-repay px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-semibold hover:bg-gray-200">
                Cancel
            </button>
            <button id="repaySubmit" type="button" class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-semibold hover:bg-green-700">
                Record repayment
            </button>
        </div>
    </div>
</div>
