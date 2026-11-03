{{-- Approve = pay out. Shown only while Status is Approved; the controller
     requires all three then, and AdvancePostingService posts
     Dr <Recorded in> / Cr <Paid from> — the bank ledger and journal entry.
     $p is the id prefix of the including modal: 'create' or 'edit'. --}}
@php
    $apBanks    = $advanceBanks ?? collect();
    $apAccounts = $advanceAccounts ?? collect();
    $apDefault  = $advanceDefaultId ?? null;
@endphp
<div id="{{ $p }}_payment_box" class="mb-4 rounded-lg border border-blue-200 bg-blue-50/60 p-3 hidden">
    <p class="text-xs font-bold text-blue-800 mb-2"><i class="fas fa-money-bill-transfer mr-1"></i>Payment — approving pays the advance out</p>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
            <label for="{{ $p }}_bank_id" class="block text-gray-700 text-xs font-bold mb-1">Paid from (bank / cash) <span class="text-red-500">*</span></label>
            <select id="{{ $p }}_bank_id" name="bank_id" class="form-select w-full px-3 py-2 border border-gray-300 rounded-md text-sm" style="width:100%">
                <option value="">— Select —</option>
                @foreach($apBanks as $apBank)
                    <option value="{{ $apBank->id }}">{{ $apBank->name }}{{ $apBank->branch_name ? ' — ' . $apBank->branch_name : '' }}</option>
                @endforeach
            </select>
            <p class="text-red-500 text-xs mt-1 hidden error-message">Pick where the money was paid from</p>
        </div>
        <div>
            <label for="{{ $p }}_contra_account_id" class="block text-gray-700 text-xs font-bold mb-1">Recorded in (contra account) <span class="text-red-500">*</span></label>
            <select id="{{ $p }}_contra_account_id" name="contra_account_id" class="form-select w-full px-3 py-2 border border-gray-300 rounded-md text-sm" style="width:100%">
                @foreach($apAccounts as $apAccount)
                    <option value="{{ $apAccount->id }}" @selected((int) $apAccount->id === (int) $apDefault)>{{ $apAccount->code }} · {{ $apAccount->name }}</option>
                @endforeach
            </select>
            <p class="text-red-500 text-xs mt-1 hidden error-message">Pick the account the advance is recorded in</p>
        </div>
        <div>
            <label for="{{ $p }}_transaction_date" class="block text-gray-700 text-xs font-bold mb-1">Payment date <span class="text-red-500">*</span></label>
            <input type="date" id="{{ $p }}_transaction_date" name="transaction_date" value="{{ now()->toDateString() }}"
                class="form-input w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
            <p class="text-red-500 text-xs mt-1 hidden error-message">Pick the date the money went out</p>
        </div>
        <div>
            <label for="{{ $p }}_transaction_time" class="block text-gray-700 text-xs font-bold mb-1">Payment time <span class="text-red-500">*</span></label>
            <input type="time" id="{{ $p }}_transaction_time" name="transaction_time" value="{{ now()->format('H:i') }}" required
                class="form-input w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
        </div>
        <div>
            <label for="{{ $p }}_payment_method" class="block text-gray-700 text-xs font-bold mb-1">Payment method <span class="text-red-500">*</span></label>
            <select id="{{ $p }}_payment_method" name="payment_method" class="form-select w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                <option value="">— Select —</option><option value="cash">Cash</option><option value="bank_transfer">Bank Transfer</option><option value="mobile_banking">Mobile Banking</option><option value="cheque">Cheque</option><option value="card">Card</option>
            </select>
        </div>
        <div>
            <label class="block text-gray-700 text-xs font-bold mb-1">Receiving bank / cheque bank</label>
            <input type="text" name="recipient_bank" maxlength="150" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
        </div>
        <div>
            <label class="block text-gray-700 text-xs font-bold mb-1">Receiving account / cheque no.</label>
            <input type="text" name="recipient_account" maxlength="100" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
        </div>
        <div>
            <label class="block text-gray-700 text-xs font-bold mb-1">Reference No. / TRNX ID</label>
            <input type="text" name="reference" maxlength="191" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
        </div>
        <div class="text-xs text-gray-600 self-end pb-2 md:col-span-2">
            Posts <b>Dr</b> the account above, <b>Cr</b> the bank. The whole advance is deducted from that month's salary (the <b>Date</b> above) and clears it again.
        </div>
    </div>
</div>
