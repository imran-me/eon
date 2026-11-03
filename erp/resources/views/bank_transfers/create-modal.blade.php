@php
    $defaultChargeAccount = $accounts->firstWhere('code', '8510');
    $oldTime = old('time');
    $timeValue = $oldTime ?: now()->format('H:i');
    [$modalHour24, $modalMinute] = array_map('intval', explode(':', $timeValue));
    $modalHour = $modalHour24 % 12 ?: 12;
    $modalAmPm = $modalHour24 < 12 ? 'AM' : 'PM';
@endphp

<div id="bankTransferModal" class="bt-modal {{ $errors->any() ? 'is-open' : '' }}" aria-hidden="{{ $errors->any() ? 'false' : 'true' }}">
    <div class="bt-modal-backdrop" data-close-transfer-modal></div>
    <section class="bt-modal-panel" role="dialog" aria-modal="true" aria-labelledby="bankTransferModalTitle">
        <header class="bt-modal-header">
            <div class="bt-modal-icon"><i class="fas fa-exchange-alt"></i></div>
            <div>
                <h2 id="bankTransferModalTitle">New Bank Transfer</h2>
                <p>Move funds safely between company accounts</p>
            </div>
            <button type="button" class="bt-modal-close" data-close-transfer-modal aria-label="Close">&times;</button>
        </header>

        <form action="{{ route('role.bank_transfers.store', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" method="POST" id="bankTransferCreateForm">
            @csrf
            <div class="bt-modal-body">
                @if($errors->any())
                    <div class="bt-form-errors">
                        <strong>Please correct the highlighted information.</strong>
                        <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif

                <div class="bt-section">
                    <div class="bt-section-title"><i class="fas fa-route"></i> Transfer route</div>
                    <div class="bt-route-grid">
                        <div class="bt-field">
                            <label>From bank <span>*</span></label>
                            <select name="from_bank_id" id="modal_from_bank_id" required>
                                <option value="">Select source account</option>
                                @include('bank_transfers.partials.bank-options', ['banks' => $banks, 'selected' => old('from_bank_id'), 'withCurrency' => true])
                            </select>
                            <small class="bt-account-balance bt-account-balance--source"><span>Available balance</span><strong id="modalSourceBalance">—</strong></small>
                        </div>
                        <div class="bt-route-arrow"><i class="fas fa-arrow-right"></i></div>
                        <div class="bt-field">
                            <label>To bank <span>*</span></label>
                            <select name="to_bank_id" id="modal_to_bank_id" required>
                                <option value="">Select destination account</option>
                                @include('bank_transfers.partials.bank-options', ['banks' => $banks, 'selected' => old('to_bank_id')])
                            </select>
                            <small class="bt-account-balance bt-account-balance--destination"><span>Available balance</span><strong id="modalDestinationBalance">—</strong></small>
                        </div>
                    </div>
                </div>

                <div class="bt-section">
                    <div class="bt-section-title"><i class="fas fa-receipt"></i> Transfer details</div>
                    <div class="bt-form-grid bt-form-grid--four">
                        <div class="bt-field">
                            <label>Amount <span>*</span></label>
                            <input type="number" name="amount" id="modal_transfer_amount" min="0.01" step="0.01" value="{{ old('amount') }}" placeholder="0.00" required>
                        </div>
                        <div class="bt-field">
                            <label>Payment date <span>*</span></label>
                            <input type="date" name="payment_date" value="{{ old('payment_date', now()->format('Y-m-d')) }}" required>
                        </div>
                        <div class="bt-field">
                            <label>Reference no. <span>*</span></label>
                            <input type="text" name="reference_no" value="{{ old('reference_no', $referenceNo) }}" required>
                        </div>
                        <div class="bt-field">
                            <label>Custom time <span>*</span></label>
                            <input type="hidden" name="time" id="modal_transfer_time" value="{{ $timeValue }}">
                            <div class="bt-time-picker">
                                <select id="modal_transfer_hour" aria-label="Hour" required>
                                    <option value="">hh</option>
                                    @for($hour = 1; $hour <= 12; $hour++)
                                        <option value="{{ $hour }}" @selected($modalHour === $hour)>{{ str_pad($hour, 2, '0', STR_PAD_LEFT) }}</option>
                                    @endfor
                                </select>
                                <span>:</span>
                                <select id="modal_transfer_minute" aria-label="Minute" required>
                                    <option value="">mm</option>
                                    @for($minute = 0; $minute < 60; $minute++)
                                        <option value="{{ $minute }}" @selected($modalMinute === $minute)>{{ str_pad($minute, 2, '0', STR_PAD_LEFT) }}</option>
                                    @endfor
                                </select>
                                <select id="modal_transfer_ampm" aria-label="AM or PM">
                                    <option value="AM" @selected($modalAmPm === 'AM')>AM</option>
                                    <option value="PM" @selected($modalAmPm === 'PM')>PM</option>
                                </select>
                            </div>
                            <small>Required transaction time shown in the bank ledger.</small>
                        </div>
                        <input type="hidden" name="status" value="completed">
                        <input type="hidden" name="payment_method" value="bank_transfer">
                    </div>
                </div>

                <div class="bt-section bt-optional-section">
                    <div class="bt-section-title"><i class="fas fa-file-invoice-dollar"></i> Charges & VAT <em>Optional</em></div>
                    <div class="bt-form-grid bt-form-grid--four">
                        <div class="bt-field"><label>Bank charge</label><input type="number" name="charge_amount" id="modal_charge_amount" min="0" step="0.01" value="{{ old('charge_amount', '0.00') }}"></div>
                        <div class="bt-field"><label>Charge posts to</label><select name="charge_account_id"><option value="">Select account</option>@foreach($accounts as $account)<option value="{{ $account->id }}" @selected((string)old('charge_account_id', optional($defaultChargeAccount)->id) === (string)$account->id)>{{ $account->name }}{{ $account->code ? ' ('.$account->code.')' : '' }}</option>@endforeach</select></div>
                        <div class="bt-field"><label>VAT</label><input type="number" name="vat_amount" id="modal_vat_amount" min="0" step="0.01" value="{{ old('vat_amount', '0.00') }}"></div>
                        <div class="bt-field"><label>VAT posts to</label><select name="vat_account_id"><option value="">Select account</option>@foreach($accounts as $account)<option value="{{ $account->id }}" @selected((string)old('vat_account_id', optional($defaultChargeAccount)->id) === (string)$account->id)>{{ $account->name }}{{ $account->code ? ' ('.$account->code.')' : '' }}</option>@endforeach</select></div>
                    </div>
                </div>

                <div class="bt-field"><label>Remarks</label><textarea name="remarks" rows="2" placeholder="Optional note about this transfer">{{ old('remarks') }}</textarea></div>

                <section class="bt-transfer-summary">
                    <div class="bt-summary-title"><i class="fas fa-calculator"></i> Current Transfer Summary</div>
                    <div class="bt-summary-content">
                        <div class="bt-summary-line"><span>From</span><strong id="modalSummaryFrom">—</strong></div>
                        <div class="bt-summary-line"><span>To</span><strong id="modalSummaryTo">—</strong></div>
                        <div class="bt-summary-line"><span>Source balance</span><strong id="modalSummaryBalance">—</strong></div>
                        <div class="bt-summary-line bt-summary-amount"><span>Amount</span><strong id="modalSummaryAmount">৳0.00</strong></div>
                        <div class="bt-summary-line"><span>Charge / VAT</span><strong id="modalSummaryCharges">৳0.00</strong></div>
                        <div class="bt-summary-line"><span>Total deduction</span><strong id="modalSummaryDeduction">৳0.00</strong></div>
                        <div class="bt-summary-line"><span>Remaining</span><strong id="modalSummaryRemaining">—</strong></div>
                        <div id="modalInsufficientBalance" class="bt-balance-warning"><i class="fas fa-exclamation-triangle"></i> Insufficient balance in source bank.</div>
                    </div>
                </section>
            </div>

            <footer class="bt-modal-footer">
                <div class="bt-modal-total"><span>Total source deduction</span><strong id="modalTransferTotal">৳0.00</strong></div>
                <button type="button" class="bt-btn-secondary" data-close-transfer-modal>Cancel</button>
                <button type="submit" id="createTransferButton" class="bt-btn-primary">
                    <span class="bt-submit-idle"><i class="fas fa-paper-plane"></i> Create Transfer</span>
                    <span class="bt-submit-loading"><i class="fas fa-spinner fa-spin"></i> Creating transfer...</span>
                </button>
            </footer>
        </form>
    </section>
</div>
