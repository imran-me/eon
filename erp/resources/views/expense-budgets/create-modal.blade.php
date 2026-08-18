<div id="createModal" class="budget-modal">
    <div class="budget-modal-card">
        <div class="budget-modal-header" style="background: linear-gradient(135deg, #2d3eb5 0%, #1f2a8a 100%);">
            <h3><i class="fas fa-chart-pie mr-2"></i>Add Budget</h3>
            <button type="button" class="close-btn modal-close-create"><i class="fas fa-times"></i></button>
        </div>
        <div class="budget-modal-body">
            <form id="createForm" method="POST" action="{{ route('role.expenses.budget-setup.store', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}">
                @csrf
                <div class="form-grid-2">
                    <div class="form-row">
                        <label for="create_company_id">Company <span style="color:#ef4444">*</span></label>
                        <select id="create_company_id" name="company_id" class="select2-modal">
                            <option value="">— Select Company —</option>
                            @foreach ($companies as $company)
                                <option value="{{ $company->id }}">{{ $company->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-row">
                        <label for="create_expense_category_id">Category <span style="color:#ef4444">*</span></label>
                        <select id="create_expense_category_id" name="expense_category_id" class="select2-modal">
                            <option value="">— Select Category —</option>
                        </select>
                    </div>
                </div>
                <div class="form-grid-2">
                    <div class="form-row">
                        <label for="create_period">Period <span style="color:#ef4444">*</span></label>
                        <select id="create_period" name="period">
                            <option value="Monthly">Monthly</option>
                            <option value="Yearly">Yearly</option>
                            <option value="Quarterly">Quarterly</option>
                            <option value="Weekly">Weekly</option>
                        </select>
                    </div>
                    <div class="form-row">
                        <label for="create_amount">Budget Amount (৳) <span style="color:#ef4444">*</span></label>
                        <input id="create_amount" name="amount" type="number" min="0" step="0.01" placeholder="0.00">
                    </div>
                </div>
                <div class="form-row">
                    <label for="create_threshold">Alert Threshold (%) <span style="color:#ef4444">*</span></label>
                    <input id="create_threshold" name="threshold" type="number" min="1" max="100" value="80">
                </div>
            </form>
        </div>
        <div class="budget-modal-footer">
            <button type="button" class="btn-cancel modal-close-create">Cancel</button>
            <button type="button" id="createSubmit" class="btn-save"><i class="fas fa-save mr-1"></i>Save Budget</button>
        </div>
    </div>
</div>