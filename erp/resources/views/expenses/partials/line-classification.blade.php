{{-- One searchable operating-cost picker, matching the project expense entry. --}}
<div id="expenseClassificationQuick" class="fixed inset-0 hidden" style="z-index:80;background:rgba(15,23,42,.45)">
    <div class="bg-white rounded-xl shadow-xl mx-auto" style="width:min(580px,92vw);margin-top:10vh">
        <div class="flex items-center justify-between p-5 border-b">
            <h3 id="expenseQuickTitle" class="font-semibold text-lg text-teal-800">New Category</h3>
            <button type="button" id="expenseQuickClose" aria-label="Close classification form">✕</button>
        </div>
        <form id="expenseQuickForm" class="p-5 space-y-4">
            <p class="text-xs text-slate-500">Saved for the selected company. The new choice will be selected on this line.</p>
            <div><label class="block text-sm font-semibold mb-1">Name *</label><input id="expenseQuickName" required maxlength="255" class="w-full border rounded-lg p-2"></div>
            <div id="expenseQuickParentWrap"><label class="block text-sm font-semibold mb-1">Parent Category *</label><select id="expenseQuickParent" class="w-full border rounded-lg p-2"></select></div>
            <div><label class="block text-sm font-semibold mb-1">Posts to Account *</label><select id="expenseQuickHead" required class="w-full border rounded-lg p-2"></select></div>
            <p id="expenseQuickError" class="text-sm text-red-600" role="alert"></p>
            <div class="flex justify-end gap-2 pt-3 border-t">
                <button type="button" id="expenseQuickCancel" class="border rounded-lg px-4 py-2">Cancel</button>
                <button type="submit" id="expenseQuickSave" class="bg-teal-600 text-white rounded-lg px-4 py-2">Save & Select</button>
            </div>
        </form>
    </div>
</div>
<script>
(function () {
    const graph = @json($classification);
    const accounts = {{ Illuminate\Support\Js::from($accounts->map(fn ($a) => [
        'id' => $a->id,
        'code' => $a->code,
        'name' => $a->name,
        'company_id' => $a->company_id,
    ])) }};
    const accountById = new Map(accounts.map(account => [String(account.id), account]));
    const urls = {
        @can('create expense category')
        category: @json(route('role.expense-categories.store', ['role' => Str::slug(Auth::user()->getRoleNames()->first())])),
        @endcan
        @can('create expense sub-category')
        sub: @json(route('role.expense-subcategories.store', ['role' => Str::slug(Auth::user()->getRoleNames()->first())])),
        @endcan
    };
    const quickModal = document.getElementById('expenseClassificationQuick');
    const quickForm = document.getElementById('expenseQuickForm');
    const quickName = document.getElementById('expenseQuickName');
    const quickParent = document.getElementById('expenseQuickParent');
    const quickHead = document.getElementById('expenseQuickHead');
    const quickError = document.getElementById('expenseQuickError');
    let quickTarget = null;

    const company = prefix => {
        const department = document.getElementById(prefix + '_expense_department_id');
        return department?.selectedOptions?.[0]?.dataset?.company
            || document.getElementById(prefix + '_company_id')?.value
            || '';
    };
    const visible = (item, prefix) => !item.company_id || String(item.company_id) === String(company(prefix));
    const accountFor = (category, subCategory) => accountById.get(String(subCategory?.account_id || category?.account_id || ''));

    function optionLabel(name, account, categoryFallback) {
        return (categoryFallback ? 'Use category: ' : '') + name
            + (account?.code ? ' · [' + account.code + ']' : '');
    }

    function fill(select, prefix, selectedValue) {
        select.replaceChildren(new Option('Select cost head...', ''));

        graph.categories
            .filter(category => visible(category, prefix))
            .forEach(category => {
                const group = document.createElement('optgroup');
                group.label = category.name;
                const categoryAccount = accountFor(category, null);

                if (categoryAccount) {
                    group.appendChild(new Option(
                        optionLabel(category.name, categoryAccount, true),
                        'category:' + category.id
                    ));
                }

                graph.subCategories
                    .filter(sub => String(sub.category_id) === String(category.id) && visible(sub, prefix))
                    .forEach(sub => {
                        const account = accountFor(category, sub);
                        if (!account) return;
                        group.appendChild(new Option(optionLabel(sub.name, account, false), 'sub:' + sub.id));
                    });

                if (group.children.length) select.appendChild(group);
            });

        select.value = selectedValue || '';
        if (select.value !== String(selectedValue || '')) select.value = '';
        $(select).trigger('change.select2');
    }

    function fillQuick(select, rows, placeholder, selected) {
        select.replaceChildren(new Option(placeholder, ''));
        rows.forEach(item => select.add(new Option(item.name, item.id)));
        select.value = selected || '';
        $(select).trigger('change.select2');
    }

    function resolve(select, categoryInput, subInput, accountInput) {
        const [kind, id] = String(select.value || '').split(':');
        const sub = kind === 'sub'
            ? graph.subCategories.find(item => String(item.id) === String(id))
            : null;
        const categoryId = sub?.category_id || (kind === 'category' ? id : '');
        const category = graph.categories.find(item => String(item.id) === String(categoryId));
        const account = accountFor(category, sub);

        categoryInput.value = categoryId || '';
        subInput.value = sub?.id || '';
        accountInput.value = account?.id || '';
    }

    function wire(row, prefix, categoryId, subId, accountId) {
        const select = row.querySelector('.line-cost-head');
        const categoryInput = row.querySelector('.line-category');
        const subInput = row.querySelector('.line-sub');
        const accountInput = row.querySelector('.expense-line-head');
        const selectedValue = subId ? 'sub:' + subId : (categoryId ? 'category:' + categoryId : '');

        fill(select, prefix, selectedValue);
        $(select).select2({
            width: '100%',
            dropdownParent: $('#' + prefix + 'Modal'),
            placeholder: 'Select cost head...',
        });

        categoryInput.value = categoryId || '';
        subInput.value = subId || '';
        accountInput.value = accountId || '';
        if (!accountInput.value) resolve(select, categoryInput, subInput, accountInput);

        $(select).on('change.expenseLine', () => resolve(select, categoryInput, subInput, accountInput));
        row.querySelector('.line-new-category')?.addEventListener('click', () => openQuick(row, prefix, 'category'));
        row.querySelector('.line-new-sub')?.addEventListener('click', () => openQuick(row, prefix, 'sub'));
        row.expenseClassificationRefresh = () => {
            const current = select.value;
            fill(select, prefix, current);
            resolve(select, categoryInput, subInput, accountInput);
        };
    }

    function closeQuick() {
        if (document.getElementById('expenseQuickSave').disabled) return;
        quickModal.classList.add('hidden');
        quickTarget = null;
    }

    function openQuick(row, prefix, type) {
        if (!urls[type]) return;
        if (!company(prefix)) {
            alert('Choose the expense company first.');
            return;
        }

        quickTarget = { row, prefix, type, companyId: company(prefix) };
        quickForm.reset();
        quickError.textContent = '';
        document.getElementById('expenseQuickTitle').textContent = type === 'sub' ? 'New Sub-category' : 'New Category';
        document.getElementById('expenseQuickParentWrap').hidden = type !== 'sub';
        quickParent.required = type === 'sub';
        fillQuick(
            quickParent,
            graph.categories.filter(category => visible(category, prefix)),
            'Select parent category',
            row.querySelector('.line-category').value
        );
        fillQuick(
            quickHead,
            accounts
                .filter(account => visible(account, prefix))
                .map(account => ({ id: account.id, name: '[' + account.code + '] ' + account.name })),
            'Search code or name',
            row.querySelector('.expense-line-head').value
        );
        if ($(quickHead).data('select2')) $(quickHead).select2('destroy');
        quickModal.classList.remove('hidden');
        $(quickHead).select2({ width: '100%', dropdownParent: $(quickModal) });
        quickName.focus();
    }

    ['expenseQuickClose', 'expenseQuickCancel'].forEach(id => document.getElementById(id).addEventListener('click', closeQuick));
    quickForm.addEventListener('submit', async event => {
        event.preventDefault();
        if (!quickTarget || !quickForm.reportValidity()) return;

        const save = document.getElementById('expenseQuickSave');
        save.disabled = true;
        quickError.textContent = '';
        try {
            const result = await $.ajax({
                url: urls[quickTarget.type],
                method: 'POST',
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    name: quickName.value.trim(),
                    company_id: quickTarget.companyId,
                    account_id: quickHead.value,
                    status: 1,
                    expense_category_id: quickTarget.type === 'sub' ? quickParent.value : '',
                },
            });
            if (!result.success) throw new Error(result.message || 'Could not save.');

            const data = result.data;
            const item = {
                id: data.id,
                name: data.name,
                company_id: data.company_id,
                account_id: data.account_id,
                category_id: data.expense_category_id,
            };
            graph[quickTarget.type === 'sub' ? 'subCategories' : 'categories'].push(item);
            document.querySelectorAll('.item-row').forEach(row => row.expenseClassificationRefresh?.());

            const choice = quickTarget.type + ':' + item.id;
            const select = quickTarget.row.querySelector('.line-cost-head');
            fill(select, quickTarget.prefix, choice);
            $(select).val(choice).trigger('change');
            save.disabled = false;
            closeQuick();
        } catch (error) {
            quickError.textContent = error.responseJSON?.message || error.message || 'Could not save.';
        } finally {
            save.disabled = false;
        }
    });

    document.addEventListener('DOMContentLoaded', () => {
        ['create', 'edit'].forEach(prefix => {
            ['_company_id', '_expense_department_id'].forEach(suffix => {
                document.getElementById(prefix + suffix)?.addEventListener('change', () => {
                    queueMicrotask(() => {
                        document.querySelectorAll('#' + prefix + '_items_body .item-row')
                            .forEach(row => row.expenseClassificationRefresh?.());
                    });
                });
            });
        });
    });

    window.expenseLineClassification = { wire };
})();
</script>
