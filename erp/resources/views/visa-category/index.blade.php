@extends('layout.app')
@section('meta-information')
    <title>Visa Categories</title>
@endsection
@section('css')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        .select2-container--open, .select2-dropdown { z-index: 99999; }
        .swal2-container { z-index: 100000 !important; }
        span[aria-current="page"] span { background-color: #2563eb !important; color: white; border-color: #2563eb; }
    </style>
@endsection

@section('main-content')
@php
    $total        = $datas->total();
    $active       = $datas->getCollection()->where('is_active', 1)->count();
    $inactive     = $datas->getCollection()->where('is_active', 0)->count();
    $countryCount = $datas->getCollection()->pluck('country_id')->filter()->unique()->count();
@endphp

{{-- Stat Cards --}}
<section class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
    <div class="rounded-xl bg-blue-50 p-4">
        <div class="h-12 w-12 rounded-xl bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center text-white text-xl mb-3 shadow-md"><i class="fas fa-layer-group"></i></div>
        <div class="text-3xl font-black text-blue-900 leading-none mb-1">{{ $total }}</div>
        <div class="text-[10px] font-bold uppercase tracking-wide text-blue-700">Total Categories</div>
    </div>
    <div class="rounded-xl bg-emerald-50 p-4">
        <div class="h-12 w-12 rounded-xl bg-gradient-to-br from-emerald-400 to-emerald-600 flex items-center justify-center text-white text-xl mb-3 shadow-md"><i class="fas fa-check-circle"></i></div>
        <div class="text-3xl font-black text-emerald-900 leading-none mb-1">{{ $active }}</div>
        <div class="text-[10px] font-bold uppercase tracking-wide text-emerald-700">Active</div>
    </div>
    <div class="rounded-xl bg-amber-50 p-4">
        <div class="h-12 w-12 rounded-xl bg-gradient-to-br from-amber-400 to-amber-600 flex items-center justify-center text-white text-xl mb-3 shadow-md"><i class="fas fa-pause-circle"></i></div>
        <div class="text-3xl font-black text-amber-900 leading-none mb-1">{{ $inactive }}</div>
        <div class="text-[10px] font-bold uppercase tracking-wide text-amber-700">Inactive</div>
    </div>
    <div class="rounded-xl bg-violet-50 p-4">
        <div class="h-12 w-12 rounded-xl bg-gradient-to-br from-violet-400 to-violet-600 flex items-center justify-center text-white text-xl mb-3 shadow-md"><i class="fas fa-globe"></i></div>
        <div class="text-3xl font-black text-violet-900 leading-none mb-1">{{ $countryCount }}</div>
        <div class="text-[10px] font-bold uppercase tracking-wide text-violet-700">Countries</div>
        <div class="text-xs text-slate-500 mt-1">This page</div>
    </div>
</section>

{{-- Page header --}}
<header class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm mb-4">
    <span class="flex h-11 w-11 items-center justify-center rounded-lg bg-blue-50 text-blue-600 flex-shrink-0"><i class="fas fa-layer-group"></i></span>
    <div class="flex-1">
        <h1 class="text-lg font-bold text-slate-900">Visa Categories</h1>
        <p class="text-xs text-slate-500">Manage visa category pricing, types and processing timelines</p>
    </div>
    <button type="button" class="create-btn rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-2 text-xs font-semibold text-emerald-700">
        <i class="fas fa-plus mr-1"></i> Add Category
    </button>
</header>

{{-- Filter + Table --}}
<div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <form action="" method="get" class="flex flex-wrap items-center gap-2 bg-slate-50 px-4 py-3">
        <div class="flex min-w-44 flex-1 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2">
            <i class="fas fa-search text-xs text-slate-400"></i>
            <input type="text" name="name" value="{{ request('name') }}" placeholder="Search by name…" class="flex-1 border-0 bg-transparent text-sm outline-none">
        </div>
        <select name="country_id" class="h-9 rounded-lg border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-400 select2-vc-filter" style="min-width:200px">
            <option value="">All Countries</option>
            @foreach($countries as $c)
                <option value="{{ $c->id }}" {{ request('country_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
            @endforeach
        </select>
        <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-xs font-semibold text-white">Filter</button>
        <a href="{{ route('role.visa-category.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600">Reset</a>
    </form>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">#</th>
                    <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Country</th>
                    <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Category / Type</th>
                    <th class="px-4 py-3 text-right text-[10px] font-bold uppercase tracking-wide text-slate-500">Costing</th>
                    <th class="px-4 py-3 text-right text-[10px] font-bold uppercase tracking-wide text-slate-500">Sale Price</th>
                    <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Margin</th>
                    <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Processing</th>
                    <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">Status</th>
                    <th class="px-4 py-3 text-center text-[10px] font-bold uppercase tracking-wide text-slate-500">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($datas as $key => $value)
                    @php
                        $cost   = (float) $value->costing_price;
                        $sale   = (float) $value->sale_price;
                        $margin = $sale - $cost;
                        $pct    = $cost > 0 ? round(($margin / $cost) * 100) : null;

                        $typeClass = match(true) {
                            str_contains($value->visa_type ?? '', 'eVisa')    => 'bg-blue-100 text-blue-700',
                            str_contains($value->visa_type ?? '', 'Multiple') => 'bg-violet-100 text-violet-700',
                            str_contains($value->visa_type ?? '', 'Umrah')    => 'bg-orange-100 text-orange-700',
                            str_contains($value->visa_type ?? '', 'Transit')  => 'bg-sky-100 text-sky-700',
                            str_contains($value->visa_type ?? '', 'Arrival')  => 'bg-yellow-100 text-yellow-700',
                            default                                            => 'bg-slate-100 text-slate-600',
                        };
                    @endphp
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 text-xs text-slate-400">{{ ($datas->currentPage() - 1) * $datas->perPage() + $key + 1 }}</td>
                        <td class="px-4 py-3">
                            @if($value->country)
                                <span class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2.5 py-1 text-[10px] font-semibold text-blue-700">
                                    <i class="fas fa-globe text-[9px]"></i> {{ $value->country->name }}
                                </span>
                            @else
                                <span class="text-slate-300 text-sm">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="text-sm font-semibold text-slate-800">{{ $value->name }}</div>
                            @if($value->visa_type)
                                <span class="mt-1 inline-block rounded-full px-2.5 py-1 text-[10px] font-semibold {{ $typeClass }}">{{ $value->visa_type }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right text-sm text-slate-500">৳{{ number_format($cost, 0) }}</td>
                        <td class="px-4 py-3 text-right text-sm font-semibold text-slate-800">৳{{ number_format($sale, 0) }}</td>
                        <td class="px-4 py-3">
                            @if($margin > 0)
                                <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-[10px] font-semibold text-emerald-700">+৳{{ number_format($margin, 0) }}{{ $pct ? ' ('.$pct.'%)' : '' }}</span>
                            @elseif($margin < 0)
                                <span class="rounded-full bg-red-100 px-2.5 py-1 text-[10px] font-semibold text-red-600">৳{{ number_format($margin, 0) }}</span>
                            @else
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-semibold text-slate-500">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if($value->avg_processing_days)
                                <span class="inline-flex items-center gap-1 rounded-lg bg-slate-100 px-2 py-1 text-xs text-slate-600">
                                    <i class="fas fa-clock text-[9px]"></i> {{ $value->avg_processing_days }} days
                                </span>
                            @else
                                <span class="text-slate-300 text-sm">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if($value->is_active)
                                <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-[10px] font-semibold text-emerald-700">Active</span>
                            @else
                                <span class="rounded-full bg-amber-100 px-2.5 py-1 text-[10px] font-semibold text-amber-700">Inactive</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-1">
                                <button type="button" class="flex h-8 w-8 items-center justify-center rounded-lg border border-blue-200 bg-blue-50 text-blue-600 hover:bg-blue-100 edit-btn"
                                    data-item_id="{{ $value->id }}"
                                    data-item_name="{{ $value->name }}"
                                    data-item_country_id="{{ $value->country_id }}"
                                    data-item_visa_type="{{ $value->visa_type }}"
                                    data-item_costing_price="{{ $value->costing_price }}"
                                    data-item_sale_price="{{ $value->sale_price }}"
                                    data-item_avg_processing_days="{{ $value->avg_processing_days }}"
                                    data-item_description="{{ $value->description }}"
                                    data-item_is_active="{{ $value->is_active }}"
                                    title="Edit">
                                    <i class="fas fa-edit text-xs"></i>
                                </button>
                                <button type="button" class="flex h-8 w-8 items-center justify-center rounded-lg border border-red-200 bg-red-50 text-red-600 hover:bg-red-100"
                                    onclick="confirmDelete('{{ $value->id }}', '{{ addslashes($value->name) }}')"
                                    title="Delete">
                                    <i class="fas fa-trash text-xs"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="p-12 text-center">
                            <i class="fas fa-layer-group mb-3 block text-4xl text-slate-300"></i>
                            <p class="text-sm font-semibold text-slate-500">No categories found</p>
                            <p class="mt-1 text-xs text-slate-400">Try adjusting your filters or add a new visa category.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-4">{{ $datas->appends(request()->all())->links() }}</div>
</div>

@include('visa-category.create-modal')
@include('visa-category.edit-modal')
@include('visa-category.delete-modal')
@endsection

@section('js')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"
        integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous">
    </script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        // Auto-calculate and display profit margin in modals
        function calcVcMargin(prefix) {
            const cost   = parseFloat(document.getElementById(prefix === 'c' ? 'c_costing_price' : 'edit_costing_price')?.value) || 0;
            const sale   = parseFloat(document.getElementById(prefix === 'c' ? 'c_sale_price'    : 'edit_sale_price')?.value)    || 0;
            const box    = document.getElementById(prefix + '_margin_display');
            const txt    = document.getElementById(prefix + '_margin_text');
            if (!box || !txt) return;
            if (cost <= 0 && sale <= 0) { box.style.display = 'none'; return; }
            const margin = sale - cost;
            const pct    = cost > 0 ? ((margin / cost) * 100).toFixed(1) : null;
            if (margin > 0) {
                box.style.cssText = 'display:flex;align-items:center;gap:8px;background:#dcfce7;color:#15803d;border-radius:8px;padding:8px 14px;font-size:.84rem;font-weight:500';
                txt.textContent = 'Profit: ৳' + margin.toLocaleString('en-BD') + (pct ? ' (' + pct + '% markup)' : '');
            } else if (margin < 0) {
                box.style.cssText = 'display:flex;align-items:center;gap:8px;background:#fee2e2;color:#dc2626;border-radius:8px;padding:8px 14px;font-size:.84rem;font-weight:500';
                txt.textContent = 'Loss: ৳' + Math.abs(margin).toLocaleString('en-BD') + ' (selling below cost)';
            } else {
                box.style.cssText = 'display:flex;align-items:center;gap:8px;background:#f1f5f9;color:#64748b;border-radius:8px;padding:8px 14px;font-size:.84rem;font-weight:500';
                txt.textContent = 'Break-even — no profit margin';
            }
        }

        $(document).ready(function () {
            $('.select2-vc, .select2-vc-edit, .select2-vc-filter').select2();

            $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

            // Open create modal
            $('.create-btn').click(function () {
                $('#createForm')[0].reset();
                if ($('.select2-vc').length) $('#create_country_id').val('').trigger('change');
                document.getElementById('c_margin_display').style.display = 'none';
                $('#createModal').removeClass('hidden');
            });

            // Open edit modal
            $('.edit-btn').click(function () {
                const id   = $(this).data('item_id');
                const role = '{{ Str::slug(auth()->user()->getRoleNames()->first()) }}';
                $('#editItemId').val(id);
                $('#editSubmit').data('action', `/${role}/visa-category/${id}`);
                $('#edit_name').val($(this).data('item_name'));
                $('#edit_country_id').val($(this).data('item_country_id')).trigger('change');
                $('#edit_visa_type').val($(this).data('item_visa_type'));
                $('#edit_costing_price').val($(this).data('item_costing_price'));
                $('#edit_sale_price').val($(this).data('item_sale_price'));
                $('#edit_avg_processing_days').val($(this).data('item_avg_processing_days'));
                $('#edit_description').val($(this).data('item_description'));
                $('#edit_is_active').val($(this).data('item_is_active') == 1 ? '1' : '0');
                calcVcMargin('e');
                $('#editModal').removeClass('hidden');
            });

            // Close modals
            $(document).on('click', '.modal-close-create, .modal-close-edit, .modal-close-delete', function () {
                $('#createModal, #editModal, #deleteModal').addClass('hidden');
            });
            $('#createModal, #editModal, #deleteModal').on('click', function (e) {
                if (e.target === this) $(this).addClass('hidden');
            });

            // Create submit
            $('#createSubmit').click(function (e) {
                e.preventDefault();
                const btn = $(this);
                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Saving…');
                $.ajax({
                    url: $('#createForm').attr('action'),
                    method: 'POST',
                    data: new FormData($('#createForm')[0]),
                    processData: false,
                    contentType: false,
                    success: function (r) {
                        if (r.success) {
                            Swal.fire({ icon:'success', title:'Created!', text:'Visa category saved.', timer:1500, showConfirmButton:false });
                            $('#createModal').addClass('hidden');
                            setTimeout(() => location.reload(), 800);
                        } else {
                            Swal.fire({ icon:'error', title:'Error', text: r.message || 'Something went wrong.' });
                        }
                    },
                    error: function (xhr) {
                        Swal.fire({ icon:'error', title:'Error', text: xhr.responseJSON?.message || 'Failed to save.' });
                    },
                    complete: function () {
                        btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Save Category');
                    }
                });
            });

            // Edit submit
            $('#editSubmit').click(function (e) {
                e.preventDefault();
                const btn = $(this);
                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Updating…');
                $.ajax({
                    url: $(this).data('action'),
                    method: 'POST',
                    data: new FormData($('#editForm')[0]),
                    processData: false,
                    contentType: false,
                    success: function (r) {
                        if (r.success) {
                            Swal.fire({ icon:'success', title:'Updated!', text:'Category updated successfully.', timer:1500, showConfirmButton:false });
                            $('#editModal').addClass('hidden');
                            setTimeout(() => location.reload(), 800);
                        } else {
                            Swal.fire({ icon:'error', title:'Error', text: r.message || 'Update failed.' });
                        }
                    },
                    error: function (xhr) {
                        Swal.fire({ icon:'error', title:'Error', text: xhr.responseJSON?.message || 'Failed to update.' });
                    },
                    complete: function () {
                        btn.prop('disabled', false).html('<i class="fas fa-check mr-1"></i> Update Category');
                    }
                });
            });

            // Delete submit
            $('#confirmDeleteBtn').click(function () {
                const btn = $(this);
                btn.prop('disabled', true).text('Deleting…');
                $.ajax({
                    url: $(this).data('action'),
                    method: 'DELETE',
                    data: { item_id: $(this).data('item-id') },
                    success: function (r) {
                        if (r.success) {
                            Swal.fire({ icon:'success', title:'Deleted!', text:'Category removed.', timer:1200, showConfirmButton:false });
                            $('#deleteModal').addClass('hidden');
                            setTimeout(() => location.reload(), 700);
                        } else {
                            Swal.fire({ icon:'error', title:'Error', text: r.message || 'Delete failed.' });
                        }
                    },
                    error: function (xhr) {
                        Swal.fire({ icon:'error', title:'Error', text: xhr.responseJSON?.message || 'Failed to delete.' });
                    },
                    complete: function () { btn.prop('disabled', false).text('Delete'); }
                });
            });
        });

        function confirmDelete(id, name) {
            const role = '{{ Str::slug(auth()->user()->getRoleNames()->first()) }}';
            $('#deleteName').text(name);
            $('#confirmDeleteBtn').data('item-id', id).data('action', `/${role}/visa-category/${id}`);
            $('#deleteModal').removeClass('hidden');
        }
    </script>
@endsection
