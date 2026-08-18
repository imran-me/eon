<div id="editModal" class="budget-modal">
    <div class="budget-modal-card">
        <div class="budget-modal-header" style="background: linear-gradient(135deg, #0f766e 0%, #134e4a 100%);">
            <h3><i class="fas fa-pen mr-2"></i>Edit Budget</h3>
            <button type="button" class="close-btn modal-close-edit"><i class="fas fa-times"></i></button>
        </div>
        <div class="budget-modal-body">
            <form id="editForm" method="POST" action="">
                @csrf
                @method('PUT')
                <input type="hidden" id="editItemId" name="id">
                <div class="form-grid-2">
                    <div class="form-row">
                        <label for="edit_company_id">Company <span style="color:#ef4444">*</span></label>
                        <select id="edit_company_id" name="company_id" class="select2-modal">
                            <option value="">— Select Company —</option>
                            @foreach ($companies as $company)
                                <option value="{{ $company->id }}">{{ $company->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-row">
                        <label for="edit_expense_category_id">Category <span style="color:#ef4444">*</span></label>
                        <select id="edit_expense_category_id" name="expense_category_id" class="select2-modal">
                            <option value="">— Select Category —</option>
                        </select>
                    </div>
                </div>
                <div class="form-grid-2">
                    <div class="form-row">
                        <label for="edit_period">Period <span style="color:#ef4444">*</span></label>
                        <select id="edit_period" name="period">
                            <option value="Monthly">Monthly</option>
                            <option value="Yearly">Yearly</option>
                            <option value="Quarterly">Quarterly</option>
                            <option value="Weekly">Weekly</option>
                        </select>
                    </div>
                    <div class="form-row">
                        <label for="edit_amount">Budget Amount (৳) <span style="color:#ef4444">*</span></label>
                        <input id="edit_amount" name="amount" type="number" min="0" step="0.01" placeholder="0.00">
                    </div>
                </div>
                <div class="form-row">
                    <label for="edit_threshold">Alert Threshold (%) <span style="color:#ef4444">*</span></label>
                    <input id="edit_threshold" name="threshold" type="number" min="1" max="100" value="80">
                </div>
            </form>
        </div>
        <div class="budget-modal-footer">
            <button type="button" class="btn-cancel modal-close-edit">Cancel</button>
            <button type="button" id="editSubmit" class="btn-save"><i class="fas fa-save mr-1"></i>Update Budget</button>
        </div>
    </div>
</div>