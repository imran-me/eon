<div class="payment-modal">
    <form action="{{ route('role.sales.make.payment', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'id' => $sale->id]) }}" method="POST">  
        @csrf

        <!-- Modal Header -->
        <div class="modal-header">
            <div>
                <h2 class="modal-title">Payment Summary</h2>
                <div class="invoice-info">
                    <span>Invoice: <strong>#{{ $sale->invoice_no }}</strong></span>
                </div>
            </div>
            <button type="button" class="close-btn" id="closeModalBtn" onclick="closeModal2()">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Modal Body -->
        <div class="modal-body">           
            <div class="payment-summary-section">            
                <div class="summary-grid">
                    <div class="summary-item">
                        <div class="summary-label">Invoice Total</div>
                        <div class="summary-value total">৳{{ $sale->total_amount }}</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Already Paid</div>
                        <div class="summary-value paid">৳{{ $sale->paid_amount }}</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Due Amount</div>
                        <div class="summary-value due">৳{{ $sale->due_amount }}</div>
                    </div>
                </div>                

                <div class="grid grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label" for="payment_method">Payment Method</label>
                        <select id="payment_method" class="form-control select" name="payment_method" required>
                            <option value="cash" selected>Cash</option>                                                    
                            <option value="card">Card</option>
                            <option value="advance">Advance</option>
                            <option value="checque">Checque</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="mobile_banking">Mobile Banking</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="bank_id">Payment Account</label>
                        <select id="bank_id" class="form-control select" name="bank_id" required>
                            @foreach ($banks as $bank)                                                    
                            <option value="{{ $bank->id }}">{{ $bank->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label" for="payment_date">Payment Date</label>
                        <input type="date" id="payment_date" class="form-control" name="payment_date" value="{{ now()->format('Y-m-d') }}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="reference_no">Reference No.</label>
                        <input type="text" id="reference_no" class="form-control" name="reference_no" placeholder="e.g. TXN-001, Check #123">
                    </div>    
                </div>    
                            
                <div class="amount-input-container">
                    <label class="amount-input-label">Payment Amount</label>
                    <div class="amount-input-wrapper">
                        <span class="currency-symbol">৳</span>
                        <input type="number" class="amount-input" id="paymentAmount" name="payment_amount" value="{{ $sale->due_amount }}" min="0" max="{{ $sale->due_amount }}" step="0.01" required>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="modal-footer text-end text-right" style="justify-content:end">                              
            <button type="submit" class="btn btn-success">
                <i class="fas fa-check-circle"></i> Pay
            </button>
        </div>
    </form>
</div>