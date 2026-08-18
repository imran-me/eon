@extends('layout.app')
@section('meta-information')
    <title>Manage Leads</title>
@endsection
@section('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
@include('layout.table-design')
@endsection
@section('main-content')
    <!-- States Table -->    
    <div class="states-table bg-white rounded-lg shadow-md overflow-hidden mt-0">
        <div class="states-table-container">
            <div class="states-table-header bg-blue-600 px-6 py-4 flex justify-between items-center">
                <h2 class="states-table-title text-white text-xl font-semibold" style="color: white">
                    <i class="fas fa-list mr-2"></i>Lead Manager List
                </h2>
                @can('create lead manager')
                <button class="btn btn-primary create-btn bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md transition duration-200">
                    <i class="fas fa-plus mr-2"></i>Add New Lead
                </button>
                @endcan
            </div>

            <div class="states-table-content">
                <!-- Success Alert -->
                <form action="" method="get">
                    <div class="filter-container">
                        <div class="filter-header">
                            <h3><i class="fas fa-filter mr-2"></i>Filter Options</h3>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                        <div class="filter-content">
                            <div class="filter-row">
                                <div class="filter-group">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                                    <input type="text" name="name" id="filter_name" value="{{ request('name') }}" 
                                            class="form-input w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="Search by name">
                                </div>
                                <div class="filter-group">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                                    <input type="text" name="phone" id="filter_phone" value="{{ request('phone') }}" 
                                        class="form-input w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="Search by phone">
                                </div>
                                <div class="filter-group">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                    <input type="text" name="email" id="filter_email" value="{{ request('email') }}" 
                                        class="form-input w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="Search by email">
                                </div>
                                <div class="filter-group">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Lead Source</label>
                                    <select name="lead_source_id" id="filter_lead_source" class="form-select w-full px-3 py-2 border border-gray-300 rounded-md">
                                        <option value="">All Sources</option>
                                        @foreach($leadSources as $source)
                                            <option value="{{ $source->id }}" {{ request('lead_source_id') == $source->id ? 'selected' : '' }}>
                                                {{ $source->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Lead Type</label>
                                    <select name="lead_type" class="form-select w-full px-3 py-2 border border-gray-300 rounded-md">
                                        <option value="">All Types</option>
                                        <option value="air_ticket" {{ request('lead_type') == 'air_ticket' ? 'selected' : '' }}>✈️ Air Ticket</option>
                                        <option value="visa" {{ request('lead_type') == 'visa' ? 'selected' : '' }}>🛂 Visa</option>
                                        <option value="software" {{ request('lead_type') == 'software' ? 'selected' : '' }}>💻 Software</option>
                                        <option value="interior" {{ request('lead_type') == 'interior' ? 'selected' : '' }}>🏠 Interior</option>
                                        <option value="other" {{ request('lead_type') == 'other' ? 'selected' : '' }}>📋 Other</option>
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                    <select name="status" id="filter_status" class="form-select w-full px-3 py-2 border border-gray-300 rounded-md">
                                        <option value="">All Statuses</option>
                                        <option value="new" {{ request('status') == 'new' ? 'selected' : '' }}>New</option>
                                        <option value="contacted" {{ request('status') == 'contacted' ? 'selected' : '' }}>Contacted</option>
                                        <option value="qualified" {{ request('status') == 'qualified' ? 'selected' : '' }}>Qualified</option>
                                        <option value="proposal_sent" {{ request('status') == 'proposal_sent' ? 'selected' : '' }}>Quoted</option>
                                        <option value="negotiation" {{ request('status') == 'negotiation' ? 'selected' : '' }}>Negotiation</option>
                                        <option value="won" {{ request('status') == 'won' ? 'selected' : '' }}>Won</option>
                                        <option value="lost" {{ request('status') == 'lost' ? 'selected' : '' }}>Lost</option>
                                    </select>
                                </div>
                                <div class="filter-actions filter-group" style="margin: 0; flex-direction:column">
                                    <div>                                        
                                        <button type="button" class="btn-sm reset-btn">Reset</button>
                                        <button type="submit" class="btn-sm apply-btn">Apply Filters</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Table with Data -->                 
                <div class="table-responsive overflow-x-auto" style="padding: 15px">
                    <table class="table table-hover min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th style="width: 4%" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sl</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name / Phone</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assigned To</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                @canany(['edit lead manager', 'delete lead manager'])
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                @endcanany
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse ($datas as $key => $value)
                                @php
                                    $typeColors = ['air_ticket'=>'blue','visa'=>'purple','software'=>'green','interior'=>'orange','other'=>'gray'];
                                    $typeIcons  = ['air_ticket'=>'✈️','visa'=>'🛂','software'=>'💻','interior'=>'🏠','other'=>'📋'];
                                    $tc = $typeColors[$value->lead_type] ?? 'gray';
                                    $statusColors = ['new'=>'gray','contacted'=>'indigo','qualified'=>'yellow','proposal_sent'=>'purple','negotiation'=>'orange','won'=>'green','lost'=>'red'];
                                    $sc = $statusColors[$value->status] ?? 'gray';
                                    $statusLabels = ['new'=>'New','contacted'=>'Contacted','qualified'=>'Qualified','proposal_sent'=>'Quoted','negotiation'=>'Negotiation','won'=>'Won','lost'=>'Lost'];
                                    $interiorData = $value->interior ? [
                                        'property_type'    => $value->interior->property_type,
                                        'area_sqft'        => $value->interior->area_sqft,
                                        'style_preference' => $value->interior->style_preference,
                                        'budget_min'       => $value->interior->budget_min,
                                        'budget_max'       => $value->interior->budget_max,
                                        'site_visit_date'  => $value->interior->site_visit_date?->format('d M Y'),
                                        'site_visit_done'  => $value->interior->site_visit_done,
                                        'design_notes'     => $value->interior->design_notes,
                                    ] : null;
                                @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ ($datas->currentPage() - 1) * $datas->perPage() + $key + 1 }}</td>

                                    {{-- Type Badge --}}
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold bg-{{ $tc }}-100 text-{{ $tc }}-700">
                                            {{ $typeIcons[$value->lead_type] ?? '📋' }}
                                            {{ $value->lead_type_label }}
                                        </span>
                                        @if($value->leadSource)
                                        <div class="text-xs text-gray-400 mt-1">{{ $value->leadSource->name }}</div>
                                        @endif
                                    </td>

                                    {{-- Name + Phone --}}
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-blue-600 hover:underline cursor-pointer text-sm"
                                            onclick='openLeadDrawer({{ $value->id }},@json($value->name),@json($value->email),@json($value->phone),@json($value->status),@json($value->notes),@json($value->leadSource->name ?? "N/A"),@json($value->assignedEmployee->name ?? "N/A"),@json($value->customer?->name ?? "N/A"),@json($value->created_at),@json($value->lead_type),@json($interiorData))'>
                                            {{ $value->name }}
                                        </div>
                                        <div class="text-xs text-gray-500">{{ $value->phone }}</div>
                                        @if($value->email)
                                        <div class="text-xs text-gray-400">{{ $value->email }}</div>
                                        @endif
                                    </td>

                                    {{-- Service Details --}}
                                    <td class="px-4 py-3 text-xs text-gray-600">
                                        @if($value->lead_type === 'air_ticket' && $value->airTicket)
                                            @php $at = $value->airTicket; @endphp
                                            <div class="font-semibold text-blue-700">{{ $at->route_display }}</div>
                                            @if($at->travel_date)
                                            <div>📅 {{ $at->travel_date->format('d M Y') }}</div>
                                            @endif
                                            <div>👥 {{ $at->pax_count }} Pax · {{ ucfirst($at->ticket_class) }}</div>
                                            @if($at->quoted_price)
                                            <div class="text-green-700 font-medium">৳{{ number_format($at->quoted_price) }}</div>
                                            @endif
                                        @elseif($value->lead_type === 'visa' && $value->visa)
                                            @php
                                                $v = $value->visa;
                                                $totalDocs     = $value->visaDocuments->count();
                                                $collectedDocs = $value->visaDocuments->where('is_collected', true)->count();
                                                $missingMand   = $value->visaDocuments->where('is_mandatory', true)->where('is_collected', false)->count();
                                            @endphp
                                            <div class="font-semibold text-purple-700">🌍 {{ $v->country->name ?? '-' }}</div>
                                            <div>{{ ucfirst($v->visa_type) }} Visa</div>
                                            @if($v->travel_date)
                                            <div>📅 {{ $v->travel_date->format('d M Y') }}</div>
                                            @endif
                                            <span class="inline-block px-2 py-0.5 rounded-full text-xs bg-{{ $v->document_status_color }}-100 text-{{ $v->document_status_color }}-700 mt-1">
                                                Docs: {{ ucfirst($v->document_status) }}
                                            </span>
                                            @if($totalDocs > 0)
                                            <button onclick="openDocChecklist({{ $value->id }}, '{{ addslashes($value->name) }}')"
                                                class="mt-1 flex items-center gap-1 text-xs font-medium {{ $missingMand > 0 ? 'text-red-600 hover:text-red-800' : 'text-green-600 hover:text-green-800' }}">
                                                @if($missingMand > 0)
                                                    <i class="fas fa-exclamation-circle"></i> {{ $missingMand }} doc(s) missing
                                                @else
                                                    <i class="fas fa-check-circle"></i> {{ $collectedDocs }}/{{ $totalDocs }} collected
                                                @endif
                                            </button>
                                            @else
                                            <button onclick="openDocChecklist({{ $value->id }}, '{{ addslashes($value->name) }}')"
                                                class="mt-1 text-xs text-purple-500 hover:text-purple-700 underline">
                                                <i class="fas fa-folder-open"></i> View Checklist
                                            </button>
                                            @endif
                                        @elseif($value->lead_type === 'interior' && $value->interior)
                                            @php $int = $value->interior; @endphp
                                            <div class="font-semibold text-orange-700">
                                                {{ match($int->property_type) { 'flat'=>'Flat/Apt','house'=>'House','office'=>'Office','shop'=>'Shop','restaurant'=>'Restaurant', default=>'Property' } }}
                                                @if($int->area_sqft) · {{ number_format($int->area_sqft) }} sqft @endif
                                            </div>
                                            @if($int->style_preference)
                                            <div class="text-gray-500">{{ ucfirst($int->style_preference) }} style</div>
                                            @endif
                                            @if($int->budget_max)
                                            <div class="text-green-700 font-medium">
                                                ৳{{ $int->budget_min ? number_format($int->budget_min).' – ' : '' }}{{ number_format($int->budget_max) }}
                                            </div>
                                            @endif
                                            @if($int->site_visit_date)
                                            <div class="mt-1">
                                                @if($int->site_visit_done)
                                                    <span class="inline-flex items-center gap-1 text-xs text-green-600 font-medium">
                                                        <i class="fas fa-check-circle"></i> Visit Done
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center gap-1 text-xs text-amber-600 font-medium">
                                                        <i class="fas fa-calendar-alt"></i> Visit: {{ $int->site_visit_date->format('d M') }}
                                                    </span>
                                                    <button onclick="markSiteVisitDone({{ $value->id }}, this)"
                                                        class="ml-1 text-xs text-blue-500 hover:text-blue-700 underline">Mark done</button>
                                                @endif
                                            </div>
                                            @endif
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>

                                    {{-- Status --}}
                                    <td class="px-4 py-3">
                                        <span class="inline-block px-2 py-1 rounded-full text-xs font-semibold bg-{{ $sc }}-100 text-{{ $sc }}-700">
                                            {{ $statusLabels[$value->status] ?? ucfirst($value->status) }}
                                        </span>
                                    </td>

                                    <td class="px-4 py-3 text-sm text-gray-600">{{ $value->assignedEmployee->name ?? '—' }}</td>

                                    <td class="px-4 py-3 text-xs text-gray-500">
                                        {{ $value->created_at->format('d M Y') }}
                                    </td>

                                    @canany(['edit lead manager', 'delete lead manager'])
                                    <td class="px-4 py-3">
                                        <div class="flex flex-col gap-1">
                                            <div class="flex gap-1">
                                            @can('edit lead manager')
                                            <button class="edit-btn border border-blue-500 text-blue-500 hover:bg-blue-500 hover:text-white px-2 py-1 rounded text-xs"
                                                data-item_id="{{ $value->id }}"
                                                data-lead_type="{{ $value->lead_type }}"
                                                data-lead_source_id="{{ $value->lead_source_id }}"
                                                data-customer_id="{{ $value->customer_id }}"
                                                data-employee_id="{{ $value->assigned_to }}"
                                                data-item_name="{{ $value->name }}"
                                                data-item_email="{{ $value->email }}"
                                                data-item_phone="{{ $value->phone }}"
                                                data-item_status="{{ $value->status }}"
                                                data-item_notes="{{ $value->notes }}"
                                                data-at_route_from="{{ $value->airTicket?->route_from ?? '' }}"
                                                data-at_route_to="{{ $value->airTicket?->route_to ?? '' }}"
                                                data-at_travel_date="{{ $value->airTicket?->travel_date?->format('Y-m-d') ?? '' }}"
                                                data-at_pax_count="{{ $value->airTicket?->pax_count ?? 1 }}"
                                                data-at_ticket_class="{{ $value->airTicket?->ticket_class ?? 'economy' }}"
                                                data-at_preferred_airline="{{ $value->airTicket?->preferred_airline ?? '' }}"
                                                data-at_quoted_price="{{ $value->airTicket?->quoted_price ?? '' }}"
                                                data-at_payment_status="{{ $value->airTicket?->payment_status ?? 'pending' }}"
                                                data-visa_country_id="{{ $value->visa?->country_id ?? '' }}"
                                                data-visa_type="{{ $value->visa?->visa_type ?? 'tourist' }}"
                                                data-visa_travel_date="{{ $value->visa?->travel_date?->format('Y-m-d') ?? '' }}"
                                                data-visa_document_status="{{ $value->visa?->document_status ?? 'pending' }}"
                                                data-visa_payment_status="{{ $value->visa?->payment_status ?? 'pending' }}"
                                                data-visa_remarks="{{ $value->visa?->remarks ?? '' }}"
                                                data-int_property_type="{{ $value->interior?->property_type ?? 'flat' }}"
                                                data-int_area_sqft="{{ $value->interior?->area_sqft ?? '' }}"
                                                data-int_style="{{ $value->interior?->style_preference ?? '' }}"
                                                data-int_budget_min="{{ $value->interior?->budget_min ?? '' }}"
                                                data-int_budget_max="{{ $value->interior?->budget_max ?? '' }}"
                                                data-int_site_visit_date="{{ $value->interior?->site_visit_date?->format('Y-m-d') ?? '' }}"
                                                data-int_design_notes="{{ $value->interior?->design_notes ?? '' }}"
                                                data-action="{{ route('role.lead-manager.update', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'lead_manager' => $value->id]) }}"
                                                title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            @endcan
                                            @can('delete lead manager')
                                            <button class="border border-red-500 text-red-500 hover:bg-red-500 hover:text-white px-2 py-1 rounded text-xs"
                                                onclick="confirmDelete('{{ $value->id }}', '{{ $value->name }}')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            @endcan
                                            </div>
                                            {{-- Convert to Project --}}
                                            @if($value->status === 'won')
                                                @if($value->project)
                                                    <a href="{{ route('role.projects.show', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'project' => $value->project->id]) }}"
                                                       class="inline-flex items-center gap-1 px-2 py-1 bg-green-100 text-green-700 rounded text-xs font-semibold border border-green-300 hover:bg-green-200">
                                                        <i class="fas fa-external-link-alt"></i> Project দেখুন
                                                    </a>
                                                @else
                                                    <button onclick="openConvertModal({{ $value->id }}, '{{ addslashes($value->name) }}')"
                                                        class="inline-flex items-center gap-1 px-2 py-1 bg-teal-600 text-white rounded text-xs font-semibold hover:bg-teal-700">
                                                        <i class="fas fa-bolt"></i> Project এ Convert
                                                    </button>
                                                @endif
                                            @endif
                                        </div>
                                    </td>
                                    @endcanany
                                </tr>
                            @empty
                            <tr>
                                <td colspan="8" class="text-center py-8">
                                    <i class="fas fa-inbox fa-3x text-gray-400 mb-4"></i>
                                    <h4 class="text-gray-500 text-xl font-medium mb-2">No data found</h4>
                                    <p class="text-gray-400 mb-4">Try filtering with different datas.</p>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="p-3 border-t border-gray-200">                    
                    {{ $datas->appends(request()->all())->links() }}
                </div>
            </div>
        </div>
    </div>

    <!-- Lead Details Drawer -->
    <div id="leadDrawerBackdrop" class="fixed inset-0 bg-black/30 hidden z-40" onclick="closeLeadDrawer()"></div>
    <div id="leadDrawer" class="fixed top-0 right-0 h-full w-full sm:w-[600px] bg-white shadow-2xl border-l border-gray-200 hidden z-50">
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200">
            <div class="flex items-center gap-2">
                <button class="p-2 rounded hover:bg-gray-100" onclick="closeLeadDrawer()" title="Close">
                    <svg class="w-4 h-4 text-gray-700" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M7.707 14.707a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414l4-4a1 1 0 111.414 1.414L5.414 9H17a1 1 0 110 2H5.414l2.293 2.293a1 1 0 010 1.414z" clip-rule="evenodd"/>
                    </svg>
                </button>
            </div>
            <h3 class="text-lg font-semibold text-gray-800">Lead Details</h3>
            <div class="w-8"></div>
        </div>

        <div class="h-[calc(100%-56px)] overflow-y-auto">
            <div class="px-6 py-6">
                <!-- Lead Info -->
                <div class="mb-6">
                    <div class="text-xs text-gray-500 font-medium">LEAD-<span id="drawerLeadId"></span></div>
                    <div class="mt-1 text-2xl font-semibold text-gray-900" id="drawerLeadName"></div>
                </div>

                <!-- Tabs -->
                <div class="border-b border-gray-200">
                    <div class="flex gap-6">
                        <button id="tabDetailsBtn"
                                class="py-3 text-sm font-medium border-b-2 border-blue-600 text-blue-600"
                                onclick="showLeadDrawerTab('details')">
                            Details
                        </button>
                        <button id="tabHistoryBtn"
                                class="py-3 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-800"
                                onclick="showLeadDrawerTab('history')">
                            Status History
                        </button>
                    </div>
                </div>

                <!-- Details Tab -->
                <div id="tabDetails" class="mt-5">
                    <div class="space-y-4">
                        <div class="grid grid-cols-3 gap-2">
                            <div class="text-sm text-gray-600 font-medium">Email:</div>
                            <div class="col-span-2 text-sm text-gray-900" id="drawerLeadEmail"></div>
                        </div>
                        <div class="grid grid-cols-3 gap-2">
                            <div class="text-sm text-gray-600 font-medium">Phone:</div>
                            <div class="col-span-2 text-sm text-gray-900" id="drawerLeadPhone"></div>
                        </div>
                        <div class="grid grid-cols-3 gap-2">
                            <div class="text-sm text-gray-600 font-medium">Status:</div>
                            <div class="col-span-2" id="drawerLeadStatus"></div>
                        </div>
                        <div class="grid grid-cols-3 gap-2">
                            <div class="text-sm text-gray-600 font-medium">Lead Source:</div>
                            <div class="col-span-2 text-sm text-gray-900" id="drawerLeadSource"></div>
                        </div>
                        <div class="grid grid-cols-3 gap-2">
                            <div class="text-sm text-gray-600 font-medium">Assigned To:</div>
                            <div class="col-span-2 text-sm text-gray-900" id="drawerAssignedTo"></div>
                        </div>
                        <div class="grid grid-cols-3 gap-2">
                            <div class="text-sm text-gray-600 font-medium">Customer:</div>
                            <div class="col-span-2 text-sm text-gray-900" id="drawerCustomer"></div>
                        </div>
                        <div class="grid grid-cols-3 gap-2">
                            <div class="text-sm text-gray-600 font-medium">Created At:</div>
                            <div class="col-span-2 text-sm text-gray-900" id="drawerCreatedAt"></div>
                        </div>
                        <div class="grid grid-cols-3 gap-2">
                            <div class="text-sm text-gray-600 font-medium">Notes:</div>
                            <div class="col-span-2 text-sm text-gray-700 whitespace-pre-wrap" id="drawerLeadNotes"></div>
                        </div>
                    </div>

                    {{-- Interior Section (shown only for interior leads) --}}
                    <div id="drawerInteriorSection" class="hidden mt-6 pt-5 border-t border-orange-100">
                        <h4 class="text-sm font-bold text-orange-700 mb-3 flex items-center gap-2">
                            🏠 Interior Details
                        </h4>
                        <div class="space-y-3">
                            <div class="grid grid-cols-3 gap-2">
                                <div class="text-sm text-gray-600 font-medium">Property:</div>
                                <div class="col-span-2 text-sm text-gray-900" id="drawerIntProperty"></div>
                            </div>
                            <div class="grid grid-cols-3 gap-2">
                                <div class="text-sm text-gray-600 font-medium">Area:</div>
                                <div class="col-span-2 text-sm text-gray-900" id="drawerIntArea"></div>
                            </div>
                            <div class="grid grid-cols-3 gap-2">
                                <div class="text-sm text-gray-600 font-medium">Style:</div>
                                <div class="col-span-2 text-sm text-gray-900" id="drawerIntStyle"></div>
                            </div>
                            <div class="grid grid-cols-3 gap-2">
                                <div class="text-sm text-gray-600 font-medium">Budget:</div>
                                <div class="col-span-2 text-sm font-semibold text-green-700" id="drawerIntBudget"></div>
                            </div>
                            <div class="grid grid-cols-3 gap-2">
                                <div class="text-sm text-gray-600 font-medium">Site Visit:</div>
                                <div class="col-span-2 text-sm" id="drawerIntVisit"></div>
                            </div>
                            <div class="grid grid-cols-3 gap-2" id="drawerIntNotesRow">
                                <div class="text-sm text-gray-600 font-medium">Design Notes:</div>
                                <div class="col-span-2 text-sm text-gray-700 whitespace-pre-wrap" id="drawerIntNotes"></div>
                            </div>
                        </div>
                    </div>

                    {{-- ── Status Pipeline Visualization ── --}}
                    <div class="mt-5 pt-4 border-t border-gray-100">
                        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Pipeline Progress</h4>
                        <div id="drawerPipeline" class="flex items-start overflow-x-auto pb-2 gap-0">
                            {{-- Rendered by renderPipeline() --}}
                        </div>
                        <div id="drawerPipelineLostBadge" class="hidden mt-2 text-xs text-red-600 font-medium flex items-center gap-1">
                            <span class="w-2 h-2 rounded-full bg-red-500 inline-block"></span>
                            This lead has been marked as Lost
                        </div>
                    </div>

                    {{-- ── Interior Follow-up Workflow (interior leads only) ── --}}
                    <div id="drawerInteriorWorkflow" class="hidden mt-5 pt-4 border-t border-orange-100">
                        <h4 class="text-xs font-semibold text-orange-600 uppercase tracking-wider mb-3">🏠 Interior — Next Steps</h4>
                        <div id="drawerWorkflowSteps" class="space-y-2">
                            {{-- Rendered by renderInteriorWorkflow() --}}
                        </div>
                    </div>
                </div>

                <!-- History Tab -->
                <div id="tabHistory" class="mt-5 hidden">
                    <div id="statusHistoryList" class="space-y-3">
                        <div class="text-center py-8 text-gray-400 text-sm">
                            <svg class="animate-spin h-5 w-5 mx-auto text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span class="mt-2 block">Loading history...</span>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    @include('lead-manager.create-modal')
    @include('lead-manager.edit-modal')
    @include('lead-manager.delete-modal')

    {{-- ── Convert to Project Modal ── --}}
    <div id="convertModalBackdrop" class="fixed inset-0 bg-black/40 hidden z-50" onclick="closeConvertModal()"></div>
    <div id="convertModal" class="fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-md bg-white rounded-2xl shadow-2xl hidden z-50">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200">
            <div>
                <h3 class="text-base font-bold text-gray-800">⚡ Lead কে Project এ Convert করুন</h3>
                <p class="text-xs text-gray-500 mt-0.5" id="convertLeadNameLabel"></p>
            </div>
            <button onclick="closeConvertModal()" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
        </div>
        <form id="convertProjectForm" method="POST">
            @csrf
            <div class="px-5 py-4 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Project Name <span class="text-red-500">*</span></label>
                    <input type="text" name="project_name" id="convertProjectName"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-teal-400"
                        placeholder="Project এর নাম দিন" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Budget (৳)</label>
                    <input type="number" name="budget" id="convertBudget" min="0" step="0.01"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-teal-400"
                        placeholder="0.00">
                </div>
                <p class="text-xs text-gray-400">Start date আজকের তারিখ হবে। Color: Teal। পরে project থেকে edit করা যাবে।</p>
            </div>
            <div class="flex justify-end gap-2 px-5 py-4 border-t border-gray-100">
                <button type="button" onclick="closeConvertModal()"
                    class="px-4 py-2 text-sm border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-50">বাতিল</button>
                <button type="submit"
                    class="px-5 py-2 text-sm bg-teal-600 text-white rounded-lg font-semibold hover:bg-teal-700">
                    <i class="fas fa-bolt mr-1"></i> Convert করুন
                </button>
            </div>
        </form>
    </div>

    {{-- ── Visa Document Checklist Modal ── --}}
    <div id="docChecklistBackdrop" class="fixed inset-0 bg-black/40 hidden z-50" onclick="closeDocChecklist()"></div>
    <div id="docChecklistModal" class="fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-lg bg-white rounded-2xl shadow-2xl hidden z-50 flex flex-col" style="max-height:90vh;">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200">
            <div>
                <h3 class="text-base font-bold text-gray-800">🛂 Visa Document Checklist</h3>
                <p class="text-xs text-gray-500 mt-0.5" id="docLeadNameLabel"></p>
            </div>
            <button onclick="closeDocChecklist()" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
        </div>
        <div id="docChecklistBody" class="overflow-y-auto flex-1 px-5 py-4">
            <div class="text-center py-10 text-gray-400 text-sm" id="docChecklistLoading">
                <svg class="animate-spin h-5 w-5 mx-auto mb-2 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Loading checklist...
            </div>
            <div id="docChecklistContent" class="hidden">
                <div class="flex justify-between text-xs text-gray-500 mb-3" id="docProgressSummary"></div>
                <div class="w-full bg-gray-100 rounded-full h-2 mb-4">
                    <div id="docProgressBar" class="bg-green-500 h-2 rounded-full transition-all duration-300" style="width:0%"></div>
                </div>
                <ul id="docChecklistItems" class="space-y-2"></ul>
            </div>
        </div>
        <div class="px-5 py-3 border-t border-gray-100 text-right">
            <button onclick="closeDocChecklist()" class="px-4 py-2 text-sm font-medium bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg">Close</button>
        </div>
    </div>

@endsection
@section('js')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {

            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });

            // Show create modal
            $('.create-btn').click(function() {
                $('#createModal').removeClass('hidden');
                // Initialize select2 after modal is shown
                $('#createModal .select2').select2();
            });

            // Show edit modal
            $('.edit-btn').click(function() {
                const btn = $(this);
                const lead_type = btn.data('lead_type') || 'other';

                $('#editModal').removeClass('hidden');
                $('#editModal .select2').select2();

                // Core fields
                $('#editItemId').val(btn.data('item_id'));
                $('#edit_name').val(btn.data('item_name'));
                $('#edit_email').val(btn.data('item_email'));
                $('#edit_phone').val(btn.data('item_phone'));
                $('#edit_notes').val(btn.data('item_notes'));
                $('#edit_lead_source_id').val(btn.data('lead_source_id')).trigger('change');
                $('#edit_assigned_to').val(btn.data('employee_id')).trigger('change');
                $('#edit_customer_id').val(btn.data('customer_id')).trigger('change');
                $('#edit_status').val(btn.data('item_status')).trigger('change');

                // Lead type toggle (calls editLeadTypeToggle from edit-modal)
                if (typeof editLeadTypeToggle === 'function') editLeadTypeToggle(lead_type);

                // Air Ticket fields
                if (lead_type === 'air_ticket') {
                    $('#edit_route_from').val(btn.data('at_route_from'));
                    $('#edit_route_to').val(btn.data('at_route_to'));
                    $('#edit_travel_date').val(btn.data('at_travel_date'));
                    $('#edit_pax_count').val(btn.data('at_pax_count'));
                    $('#edit_ticket_class').val(btn.data('at_ticket_class'));
                    $('#edit_preferred_airline').val(btn.data('at_preferred_airline'));
                    $('#edit_quoted_price').val(btn.data('at_quoted_price'));
                    $('#edit_at_payment_status').val(btn.data('at_payment_status'));
                }

                // Visa fields
                if (lead_type === 'visa') {
                    $('#edit_country_id').val(btn.data('visa_country_id')).trigger('change');
                    $('#edit_visa_type').val(btn.data('visa_type'));
                    $('#edit_visa_travel_date').val(btn.data('visa_travel_date'));
                    $('#edit_document_status').val(btn.data('visa_document_status'));
                    $('#edit_visa_payment_status').val(btn.data('visa_payment_status'));
                    $('#edit_visa_remarks').val(btn.data('visa_remarks'));
                }

                // Interior fields
                if (lead_type === 'interior') {
                    $('#edit_property_type').val(btn.data('int_property_type'));
                    $('#edit_area_sqft').val(btn.data('int_area_sqft'));
                    $('#edit_style_preference').val(btn.data('int_style'));
                    $('#edit_budget_min').val(btn.data('int_budget_min'));
                    $('#edit_budget_max').val(btn.data('int_budget_max'));
                    $('#edit_site_visit_date').val(btn.data('int_site_visit_date'));
                    $('#edit_design_notes').val(btn.data('int_design_notes'));
                }

                // Update form action
                const action = btn.data('action');
                $('#editSubmit').data('action', action);
                $('#editForm').attr('action', action);
            });

            // Close modals
            $('.modal-close-create, .modal-backdrop').click(function(e) {
                if ($(e.target).closest('.modal-close-create').length || $(e.target).hasClass('modal-backdrop')) {
                    $('#createModal').addClass('hidden');
                }
            });

            $('.modal-close-edit, .modal-backdrop').click(function(e) {
                if ($(e.target).hasClass('modal-backdrop') || $(e.target).closest('.modal-close-edit').length) {
                    $('#editModal').addClass('hidden');
                }
            });

            $('.modal-close-delete, .modal-backdrop').click(function(e) {
                if ($(e.target).hasClass('modal-backdrop') || $(e.target).closest('.modal-close-delete').length) {
                    $('#deleteModal').addClass('hidden');
                }
            });

            // Close success alert
            $('.close-btn').click(function() {
                $(this).closest('.alert').addClass('hidden');
            });

            // Create state form submission
            $('#createSubmit').click(function(e) {
                e.preventDefault();
                if (validateCreateForm()) {
                    const selectedLeadType = $('#createForm input[name="lead_type"]:checked').val();
                    let formData = new FormData($('#createForm')[0]);
                    $.ajax({
                        url: $('#createForm').attr('action'),
                        method: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function (response) {
                            if (response.success) {
                                $('#createModal').addClass('hidden');
                                $('#createForm')[0].reset();
                                // Reset lead type button styles
                                $('#create_lead_type_group .lead-type-btn').removeClass('border-blue-500 border-purple-500 border-green-500 border-gray-500 bg-blue-50 bg-purple-50 bg-green-50 bg-gray-50 text-blue-700 text-purple-700 text-green-700 text-gray-700').addClass('border-gray-200 text-gray-600');
                                $('#create_air_ticket_fields, #create_visa_fields').addClass('hidden');

                                if (selectedLeadType === 'visa' && response.data && response.data.id) {
                                    window._reloadAfterChecklist = true;
                                    openDocChecklist(response.data.id, response.data.name || 'New Lead');
                                } else {
                                    Swal.fire({ icon: 'success', title: 'Done', text: 'Data created successfully!' });
                                    setTimeout(() => window.location.reload(), 800);
                                }
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Oops...',
                                    text: response.message || 'Something went wrong.'
                                });
                            }
                        },
                        error: function (xhr) {
                            console.error(xhr.responseText);
                            Swal.fire({
                                icon: 'error',
                                title: 'Error!',
                                text: 'Failed to create Lead manager.'
                            });
                        }
                    });
                }
            });            

            // Edit state form submission
            $('#editSubmit').click(function() {
                if (validateEditForm()) {
                    let formData = new FormData($('#editForm')[0]);
                    $.ajax({
                        url: $(this).data('action'),
                        method: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function (response) {
                            if (response.success) {
                                Swal.fire({
                                    icon: "success",
                                    title: "Done",
                                    text: "Data updated successfully!",
                                });
                                $('#editModal').addClass('hidden');
                                setTimeout(() => window.location.reload(), 800);
                            } else {
                                Swal.fire({
                                    icon: "error",
                                    title: "Oops...",
                                    text: response.message || "Update failed.",
                                });
                            }
                        },
                        error: function (xhr) {
                            console.error('❌ Error:', xhr.responseText);
                            Swal.fire({
                                icon: 'error',
                                title: 'Error!',
                                text: 'Something went wrong!'
                            });
                        }
                    }); 
                }
            });

            // Delete confirmation
            $('#confirmDeleteBtn').click(function() {
                const dataId = $(this).data('item-id');
                const deleteUrl = $(this).data('action');
                $.ajax({
                    url: deleteUrl,
                    method: 'DELETE',
                    data: {
                        item_id: dataId,
                    },
                    success: function (response) {
                        console.log(response);
                        if (response.success) {
                            Swal.fire({
                                icon: "success",
                                title: "Done",
                                text: "Data deleted successfully!",
                            });
                            $('#deleteModal').addClass('hidden');
                            console.log('trigger reload');                                
                            setTimeout(() => {
                                window.location.reload();
                            }, 500);
                        } else {
                            Swal.fire({
                                icon: "error",
                                title: "Opps...",
                                text: response.message,
                            });
                        }
                    },
                    error: function (xhr) {
                        console.error('❌ Error:', xhr.responseText);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: 'Something went wrong!'
                        });
                    }
                });  
            });
        });
        
        // Form validation functions
        function validateCreateForm() {
            let isValid = true;
            
            // Reset error messages
            $('#createForm .error-message').addClass('hidden');
            $('#createForm .form-select, #createForm .form-input').removeClass('border-red-500');                        
            
            if (!$('#lead_source_id').val().trim()) {
                console.log('Lead source is empty');
                $('#lead_source_id').parent().find('.error-message').removeClass('hidden');
                $('#lead_source_id').addClass('border-red-500');
                isValid = false;
            }

            if (!$('#create_name').val().trim()) {
                $('#create_name').parent().find('.error-message').removeClass('hidden');
                $('#create_name').addClass('border-red-500');
                isValid = false;
            }

            // if (!$('#create_email').val().trim()) {
            //     $('#create_email').parent().find('.error-message').removeClass('hidden');
            //     $('#create_email').addClass('border-red-500');
            //     isValid = false;
            // }

            if (!$('#create_phone').val().trim()) {
                $('#create_phone').parent().find('.error-message').removeClass('hidden');
                $('#create_phone').addClass('border-red-500');
                isValid = false;
            }

            if (!$('#create_status').val().trim()) {
                $('#create_status').parent().find('.error-message').removeClass('hidden');
                $('#create_status').addClass('border-red-500');
                isValid = false;
            }

            if (!$('#create_assigned_to').val().trim()) {
                $('#create_assigned_to').parent().find('.error-message').removeClass('hidden');
                $('#create_assigned_to').addClass('border-red-500');
                isValid = false;
            }

            // if (!$('#create_customer_id').val().trim()) {
            //     $('#create_customer_id').parent().find('.error-message').removeClass('hidden');
            //     $('#create_customer_id').addClass('border-red-500');
            //     isValid = false;
            // }
            
            return isValid;
        }

        function validateEditForm() {
            let isValid = true;
            
            // Reset error messages
            $('#editForm .error-message').addClass('hidden');
            $('#editForm .form-select, #editForm .form-input').removeClass('border-red-500');
            
            let editLeadSourceId = $('#edit_lead_source_id').val();

            if (!editLeadSourceId || editLeadSourceId === '' || editLeadSourceId === 'undefined' || isNaN(editLeadSourceId)) {
                $('#edit_lead_source_id').parent().find('.error-message').removeClass('hidden');
                $('#edit_lead_source_id').addClass('border-red-500');
                isValid = false;
            }

            if (!$('#edit_name').val().trim()) {
                $('#edit_name').parent().find('.error-message').removeClass('hidden');
                $('#edit_name').addClass('border-red-500');
                isValid = false;
            }

            // if (!$('#edit_email').val().trim()) {
            //     $('#edit_email').parent().find('.error-message').removeClass('hidden');
            //     $('#edit_email').addClass('border-red-500');
            //     isValid = false;
            // }

            if (!$('#edit_phone').val().trim()) {
                $('#edit_phone').parent().find('.error-message').removeClass('hidden');
                $('#edit_phone').addClass('border-red-500');
                isValid = false;
            }

            let editAssignedTo = $('#edit_assigned_to').val();

            if (!editAssignedTo || editAssignedTo === '' || editAssignedTo === 'undefined' || isNaN(editAssignedTo)) {
                $('#edit_assigned_to').parent().find('.error-message').removeClass('hidden');
                $('#edit_assigned_to').addClass('border-red-500');
                isValid = false;
            }

            // let editCustomerId = $('#edit_customer_id').val();

            // if (!editCustomerId || editCustomerId === '' || editCustomerId === 'undefined' || isNaN(editCustomerId)) {
            //     $('#edit_customer_id').parent().find('.error-message').removeClass('hidden');
            //     $('#edit_customer_id').addClass('border-red-500');
            //     isValid = false;
            // }

            let editStatus = $('#edit_status').val();

            if (!editStatus || editStatus === '') {
                $('#edit_status').parent().find('.error-message').removeClass('hidden');
                $('#edit_status').addClass('border-red-500');
                isValid = false;
            }

            return isValid;
        }

        // Reset create form
        function resetCreateForm() {
            $('#createStateForm')[0].reset();
            $('#createStateForm .error-message').addClass('hidden');
            $('#createStateForm .form-select, #createStateForm .form-input').removeClass('border-red-500');
        }

        // Delete confirmation
        function confirmDelete(id, name) {
            $('#deleteName').text(name);
            $('#confirmDeleteBtn').data('item-id', id);
            $('#deleteModal').removeClass('hidden');
        }

        // Lead Drawer Functions
        let currentLeadId = null;

        function openLeadDrawer(leadId, name, email, phone, status, notes, leadSource, assignedTo, customer, createdAt, leadType, interior) {
            currentLeadId = leadId;

            // Populate lead data
            $('#drawerLeadId').text(leadId);
            $('#drawerLeadName').text(name || 'Untitled');
            $('#drawerLeadEmail').text(email || 'N/A');
            $('#drawerLeadPhone').text(phone || 'N/A');
            $('#drawerLeadSource').text(leadSource || 'N/A');
            $('#drawerAssignedTo').text(assignedTo || 'N/A');
            $('#drawerCustomer').text(customer || 'N/A');
            $('#drawerLeadNotes').text(notes || 'No notes');

            // Format created date
            if (createdAt) {
                const date = new Date(createdAt);
                $('#drawerCreatedAt').text(date.toLocaleDateString('en-US', {
                    year: 'numeric', month: 'short', day: 'numeric',
                    hour: '2-digit', minute: '2-digit'
                }));
            }

            // Set status badge
            const statusMap = {
                new:          ['gray',   'New'],
                contacted:    ['blue',   'Contacted'],
                qualified:    ['yellow', 'Qualified'],
                proposal_sent:['purple', 'Quoted'],
                negotiation:  ['orange', 'Negotiation'],
                won:          ['green',  'Won'],
                lost:         ['red',    'Lost'],
            };
            const [sc, sl] = statusMap[status] || ['gray', status];
            $('#drawerLeadStatus').html(`<span class="px-2 py-1 rounded-full text-xs font-semibold bg-${sc}-100 text-${sc}-700">${sl}</span>`);

            // Interior section
            const intSection = document.getElementById('drawerInteriorSection');
            if (leadType === 'interior' && interior) {
                const propLabels = {flat:'Flat/Apartment', house:'House/Villa', office:'Office Space', shop:'Shop/Showroom', restaurant:'Restaurant/Cafe'};
                $('#drawerIntProperty').text(propLabels[interior.property_type] || interior.property_type);
                $('#drawerIntArea').text(interior.area_sqft ? Number(interior.area_sqft).toLocaleString() + ' sq ft' : '—');
                $('#drawerIntStyle').text(interior.style_preference ? interior.style_preference.charAt(0).toUpperCase() + interior.style_preference.slice(1) : '—');

                let budget = '—';
                if (interior.budget_min && interior.budget_max) budget = '৳' + Number(interior.budget_min).toLocaleString() + ' – ৳' + Number(interior.budget_max).toLocaleString();
                else if (interior.budget_max) budget = 'Up to ৳' + Number(interior.budget_max).toLocaleString();
                $('#drawerIntBudget').text(budget);

                let visitHtml = '—';
                if (interior.site_visit_done) {
                    visitHtml = `<span class="text-green-600 font-medium"><i class="fas fa-check-circle mr-1"></i>Done${interior.site_visit_date ? ' · ' + interior.site_visit_date : ''}</span>`;
                } else if (interior.site_visit_date) {
                    visitHtml = `<span class="text-amber-600"><i class="fas fa-calendar-alt mr-1"></i>${interior.site_visit_date}</span>
                        <button onclick="markSiteVisitDone(${leadId}, null)" class="ml-2 text-xs text-blue-500 hover:text-blue-700 underline">Mark done</button>`;
                }
                $('#drawerIntVisit').html(visitHtml);
                $('#drawerIntNotes').text(interior.design_notes || '—');
                intSection.classList.remove('hidden');
            } else {
                intSection.classList.add('hidden');
            }

            // Status pipeline visualization (all lead types)
            renderPipeline(status, leadType);

            // Interior follow-up workflow (interior leads only)
            if (leadType === 'interior') {
                renderInteriorWorkflow(status);
            } else {
                document.getElementById('drawerInteriorWorkflow').classList.add('hidden');
            }

            // Show drawer
            $('#leadDrawerBackdrop').removeClass('hidden');
            $('#leadDrawer').removeClass('hidden');

            // Reset to details tab
            showLeadDrawerTab('details');
        }

        const siteVisitDoneBaseUrl = "{{ route('role.lead-manager.site-visit-done', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'lead' => '__ID__']) }}";

        function markSiteVisitDone(leadId, rowBtn) {
            $.ajax({
                url: siteVisitDoneBaseUrl.replace('__ID__', leadId),
                type: 'PATCH',
                success: function() {
                    // Update table row button → show green "Visit Done"
                    if (rowBtn) {
                        const cell = rowBtn.closest('div');
                        cell.innerHTML = '<span class="inline-flex items-center gap-1 text-xs text-green-600 font-medium"><i class="fas fa-check-circle"></i> Visit Done</span>';
                    }
                    // Update drawer visit row if open
                    const visitEl = document.getElementById('drawerIntVisit');
                    if (visitEl) {
                        visitEl.innerHTML = '<span class="text-green-600 font-medium"><i class="fas fa-check-circle mr-1"></i>Done</span>';
                    }
                },
                error: function() {
                    alert('Failed to update. Please try again.');
                }
            });
        }

        // ── Status Pipeline Visualization ─────────────────────────────────────
        function renderPipeline(status, leadType) {
            const isInterior = leadType === 'interior';
            const stages = [
                {key: 'new',           label: isInterior ? 'New'       : 'New'},
                {key: 'contacted',     label: isInterior ? 'Contacted' : 'Contacted'},
                {key: 'qualified',     label: isInterior ? 'Site Visit': 'Qualified'},
                {key: 'proposal_sent', label: isInterior ? 'Proposal'  : 'Quoted'},
                {key: 'negotiation',   label: isInterior ? 'Negotiation' : 'Negotiation'},
                {key: 'won',           label: 'Won'},
            ];
            const isLost    = status === 'lost';
            const currentIdx = stages.findIndex(s => s.key === status);

            const pipeline = document.getElementById('drawerPipeline');
            const lostBadge = document.getElementById('drawerPipelineLostBadge');
            pipeline.innerHTML = '';
            lostBadge.classList.add('hidden');

            stages.forEach(function(stage, idx) {
                const isPast    = idx < currentIdx;
                const isCurrent = idx === currentIdx && !isLost;
                const isFuture  = idx > currentIdx || (idx === currentIdx && isLost);

                // Step circle + label
                var circleClass = 'w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold border-2 flex-shrink-0 ';
                var labelClass  = 'text-xs mt-1 text-center font-medium leading-tight ';

                if (isCurrent) {
                    circleClass += 'bg-blue-600 border-blue-600 text-white shadow shadow-blue-200';
                    labelClass  += 'text-blue-700';
                } else if (isPast) {
                    circleClass += 'bg-green-500 border-green-500 text-white';
                    labelClass  += 'text-green-600';
                } else {
                    circleClass += 'bg-gray-100 border-gray-300 text-gray-400';
                    labelClass  += 'text-gray-400';
                }

                var stepDiv = document.createElement('div');
                stepDiv.className = 'flex flex-col items-center min-w-[52px]';
                stepDiv.innerHTML =
                    '<div class="' + circleClass + '">' +
                        (isPast ? '<svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>' : (idx + 1)) +
                    '</div>' +
                    '<div class="' + labelClass + '">' + stage.label + '</div>';
                pipeline.appendChild(stepDiv);

                // Connector line (not after last step)
                if (idx < stages.length - 1) {
                    var line = document.createElement('div');
                    line.className = 'flex items-center pb-4 flex-1 min-w-[8px]';
                    line.innerHTML = '<div class="h-0.5 w-full ' + (isPast ? 'bg-green-400' : 'bg-gray-200') + '"></div>';
                    pipeline.appendChild(line);
                }
            });

            if (isLost) lostBadge.classList.remove('hidden');
        }

        // ── Interior Follow-up Workflow ────────────────────────────────────────
        var interiorWorkflows = {
            new: [
                {icon: '📞', text: 'Initial call করুন — requirement জানুন'},
                {icon: '📸', text: 'Portfolio / showcase images share করুন'},
                {icon: '💬', text: 'WhatsApp/SMS মাধ্যমে follow করুন'},
                {icon: '🔄', text: 'Status → <strong>Contacted</strong> করুন'},
            ],
            contacted: [
                {icon: '🏠', text: 'Site visit date schedule করুন'},
                {icon: '⏰', text: 'Visit reminder set করুন (client কে জানান)'},
                {icon: '📋', text: 'Property type ও area নিশ্চিত করুন'},
                {icon: '🔄', text: 'Status → <strong>Site Visit (Qualified)</strong> করুন'},
            ],
            qualified: [
                {icon: '✅', text: 'Site visit complete করুন → <em>Mark Done</em>'},
                {icon: '📐', text: 'Measurement নিন, floor plan তৈরি করুন'},
                {icon: '🗒️', text: 'Room-wise requirement নোট করুন'},
                {icon: '🎨', text: 'Design team কে brief করুন'},
                {icon: '🔄', text: 'Status → <strong>Proposal Sent</strong> করুন'},
            ],
            proposal_sent: [
                {icon: '🖼️', text: '3D design / mood board prepare করুন'},
                {icon: '💰', text: 'Itemized quotation ও material list পাঠান'},
                {icon: '📧', text: 'Follow-up call / email করুন'},
                {icon: '📎', text: 'Proposal PDF attach করুন'},
                {icon: '🔄', text: 'Status → <strong>Negotiation</strong> করুন'},
            ],
            negotiation: [
                {icon: '💬', text: 'Client feedback নোট করুন'},
                {icon: '✏️', text: 'Design revision track করুন'},
                {icon: '💵', text: 'Budget ও payment terms finalize করুন'},
                {icon: '📄', text: 'Revised proposal তৈরি করুন'},
                {icon: '🔄', text: 'Status → <strong>Won</strong> করুন'},
            ],
            won: [
                {icon: '📄', text: 'Agreement / Contract sign করুন'},
                {icon: '💵', text: 'Advance payment (30%) collect করুন'},
                {icon: '🔀', text: 'Lead → Project এ Convert করুন'},
                {icon: '👥', text: 'Project team assign করুন (Designer, Supervisor, PM)'},
                {icon: '🗓️', text: 'Project start date ও deadline set করুন'},
            ],
            lost: [
                {icon: '📝', text: 'Lost reason notes এ লিখুন'},
                {icon: '🔔', text: '3-month future follow-up reminder set করুন'},
                {icon: '💡', text: 'Lead source analytics এ track করুন'},
            ],
        };

        function renderInteriorWorkflow(status) {
            var wfSection = document.getElementById('drawerInteriorWorkflow');
            var container = document.getElementById('drawerWorkflowSteps');
            var steps = interiorWorkflows[status] || [];
            container.innerHTML = '';

            if (steps.length === 0) {
                wfSection.classList.add('hidden');
                return;
            }

            steps.forEach(function(step) {
                var div = document.createElement('div');
                div.className = 'flex items-start gap-2 px-3 py-2 bg-orange-50 rounded-lg border border-orange-100';
                div.innerHTML =
                    '<span class="flex-shrink-0 text-sm leading-5">' + step.icon + '</span>' +
                    '<span class="text-sm text-gray-700 leading-5">' + step.text + '</span>';
                container.appendChild(div);
            });

            wfSection.classList.remove('hidden');
        }

        function closeLeadDrawer() {
            currentLeadId = null;
            $('#leadDrawerBackdrop').addClass('hidden');
            $('#leadDrawer').addClass('hidden');
        }

        function showLeadDrawerTab(tab) {
            const detailsBtn = document.getElementById('tabDetailsBtn');
            const historyBtn = document.getElementById('tabHistoryBtn');
            const detailsTab = document.getElementById('tabDetails');
            const historyTab = document.getElementById('tabHistory');

            if (tab === 'details') {
                detailsTab.classList.remove('hidden');
                historyTab.classList.add('hidden');

                detailsBtn.classList.remove('border-transparent', 'text-gray-500');
                detailsBtn.classList.add('border-blue-600', 'text-blue-600');
        
                historyBtn.classList.remove('border-blue-600', 'text-blue-600');
                historyBtn.classList.add('border-transparent', 'text-gray-500');

            } else if (tab === 'history') {
                historyTab.classList.remove('hidden');
                detailsTab.classList.add('hidden');

                historyBtn.classList.remove('border-transparent', 'text-gray-500');
                historyBtn.classList.add('border-blue-600', 'text-blue-600');
                
                detailsBtn.classList.remove('border-blue-600', 'text-blue-600');
                detailsBtn.classList.add('border-transparent', 'text-gray-500');
                
                // Load status history when tab is opened
                if (currentLeadId) {
                    loadStatusHistory(currentLeadId);
                }
            }
        }

        function loadStatusHistory(leadId) {
            $('#statusHistoryList').html(`
                <div class="text-center py-8 text-gray-400 text-sm">
                    <svg class="animate-spin h-5 w-5 mx-auto text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span class="mt-2 block">Loading history...</span>
                </div>
            `);

            const url = "{{ route('role.lead-manager.status-history', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'lead' => '__LEAD_ID__']) }}".replace('__LEAD_ID__', leadId);
            
            fetch(url, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.histories && data.histories.length > 0) {
                    let html = '<div class="space-y-3">';
                    data.histories.forEach(history => {
                        // Status badge colors
                        let oldStatusClass = 'bg-gray-200 text-gray-800';
                        let newStatusClass = 'bg-green-200 text-green-800';
                        
                        if (history.old_status === 'new') oldStatusClass = 'bg-gray-200 text-gray-800';
                        else if (history.old_status === 'contacted') oldStatusClass = 'bg-blue-200 text-blue-800';
                        else if (history.old_status === 'qualified') oldStatusClass = 'bg-green-200 text-green-800';
                        else if (history.old_status === 'lost') oldStatusClass = 'bg-red-200 text-red-800';
                        
                        if (history.new_status === 'new') newStatusClass = 'bg-gray-200 text-gray-800';
                        else if (history.new_status === 'contacted') newStatusClass = 'bg-blue-200 text-blue-800';
                        else if (history.new_status === 'qualified') newStatusClass = 'bg-green-200 text-green-800';
                        else if (history.new_status === 'lost') newStatusClass = 'bg-red-200 text-red-800';
                        
                        html += `
                            <div class="border-l-4 border-blue-500 pl-4 py-3 bg-gray-50 rounded">
                                <div class="text-sm text-gray-800">
                                    <span class="font-semibold">${history.changed_by_name}</span>
                                    changed status from 
                                    <span class="px-2 py-1 ${oldStatusClass} rounded text-xs font-medium">${history.old_status || 'none'}</span>
                                    to 
                                    <span class="px-2 py-1 ${newStatusClass} rounded text-xs font-medium">${history.new_status}</span>
                                </div>
                                <div class="text-xs text-gray-500 mt-1">
                                    ${history.created_at} • ${history.time_ago}
                                </div>
                            </div>
                        `;
                    });
                    html += '</div>';
                    $('#statusHistoryList').html(html);
                } else {
                    $('#statusHistoryList').html(`
                        <div class="text-center py-8 text-gray-400 text-sm">
                            <i class="fas fa-history fa-3x mb-3"></i>
                            <p>No status history available</p>
                        </div>
                    `);
                }
            })
            .catch(err => {
                console.error('Failed to load status history:', err);
                $('#statusHistoryList').html(`
                    <div class="text-center py-8 text-red-500 text-sm">
                        <i class="fas fa-exclamation-triangle fa-2x mb-3"></i>
                        <p>Failed to load status history</p>
                    </div>
                `);
            });
        }

    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const filterHeader = document.querySelector('.filter-container .filter-header');
            const filterContent = document.querySelector('.filter-container .filter-content');
            
            filterHeader.addEventListener('click', function() {
                this.classList.toggle('active');
                filterContent.classList.toggle('active');
            });
            
            // Reset button functionality
            document.querySelector('.filter-container .reset-btn').addEventListener('click', function() {
                const inputs = document.querySelectorAll('.filter-container select, .filter-container input');
                inputs.forEach(input => {
                    if (input.type === 'date') {
                        input.value = '';
                    } else {
                        input.selectedIndex = 0;
                    }
                });
            });
            document.querySelector('.reset-btn').addEventListener('click', function (e) {
                e.preventDefault();
                window.location = "{{ route('role.lead-manager.index', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}";
            });
        });
    </script>
    <script>
        // ── Visa Document Checklist ───────────────────────────────────────────
        const docChecklistUrl = "{{ route('role.lead-manager.visa-documents', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'lead' => '__LEAD_ID__']) }}";
        const docToggleUrl    = "{{ route('role.lead-manager.visa-document.toggle', ['role' => Str::slug(Auth::user()->getRoleNames()->first()), 'doc' => '__DOC_ID__']) }}";
        const csrfToken       = document.querySelector('meta[name="csrf-token"]').content;

        function openDocChecklist(leadId, leadName) {
            document.getElementById('docLeadNameLabel').textContent = leadName;
            document.getElementById('docChecklistBackdrop').classList.remove('hidden');
            document.getElementById('docChecklistModal').classList.remove('hidden');
            document.getElementById('docChecklistLoading').classList.remove('hidden');
            document.getElementById('docChecklistContent').classList.add('hidden');

            const url = docChecklistUrl.replace('__LEAD_ID__', leadId);
            fetch(url, { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken } })
                .then(r => r.json())
                .then(data => {
                    document.getElementById('docChecklistLoading').classList.add('hidden');
                    document.getElementById('docChecklistContent').classList.remove('hidden');
                    renderDocChecklist(data.documents);
                })
                .catch(() => {
                    document.getElementById('docChecklistLoading').innerHTML = '<p class="text-red-500">Failed to load checklist.</p>';
                });
        }

        function closeDocChecklist() {
            document.getElementById('docChecklistBackdrop').classList.add('hidden');
            document.getElementById('docChecklistModal').classList.add('hidden');
            if (window._reloadAfterChecklist) {
                window._reloadAfterChecklist = false;
                window.location.reload();
            }
        }

        function renderDocChecklist(docs) {
            const list    = document.getElementById('docChecklistItems');
            const summary = document.getElementById('docProgressSummary');
            const bar     = document.getElementById('docProgressBar');
            list.innerHTML = '';

            const total     = docs.length;
            const collected = docs.filter(d => d.is_collected).length;
            const missing   = docs.filter(d => d.is_mandatory && !d.is_collected).length;
            const pct       = total > 0 ? Math.round((collected / total) * 100) : 0;

            summary.innerHTML = `<span class="font-medium">${collected}/${total} collected</span> <span class="${missing > 0 ? 'text-red-500 font-semibold' : 'text-green-600'}">${missing > 0 ? missing + ' mandatory missing' : '✓ All mandatory docs collected'}</span>`;
            bar.style.width = pct + '%';
            bar.className   = 'h-2 rounded-full transition-all duration-300 ' + (missing > 0 ? 'bg-amber-400' : 'bg-green-500');

            docs.forEach(doc => {
                const li = document.createElement('li');
                li.id = 'doc-row-' + doc.id;
                li.className = 'flex items-center justify-between gap-3 px-3 py-2.5 rounded-lg border ' + (doc.is_collected ? 'bg-green-50 border-green-200' : 'bg-white border-gray-200');
                li.innerHTML = `
                    <div class="flex items-center gap-2 flex-1 min-w-0">
                        <button onclick="toggleDoc(${doc.id})" class="flex-shrink-0 w-5 h-5 rounded border-2 flex items-center justify-center transition-colors ${doc.is_collected ? 'bg-green-500 border-green-500' : 'border-gray-400 hover:border-green-500'}" id="doc-chk-${doc.id}">
                            ${doc.is_collected ? '<svg class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>' : ''}
                        </button>
                        <span class="text-sm ${doc.is_collected ? 'line-through text-gray-400' : 'text-gray-800'}" id="doc-name-${doc.id}">${doc.document_name}</span>
                    </div>
                    <div class="flex items-center gap-1 flex-shrink-0">
                        ${doc.is_mandatory ? '<span class="text-xs px-1.5 py-0.5 rounded bg-red-100 text-red-600 font-medium">Required</span>' : '<span class="text-xs px-1.5 py-0.5 rounded bg-gray-100 text-gray-500">Optional</span>'}
                    </div>
                `;
                list.appendChild(li);
            });
        }

        function toggleDoc(docId) {
            const url = docToggleUrl.replace('__DOC_ID__', docId);
            fetch(url, {
                method: 'PATCH',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Content-Type': 'application/json' }
            })
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                const row  = document.getElementById('doc-row-' + docId);
                const chk  = document.getElementById('doc-chk-' + docId);
                const name = document.getElementById('doc-name-' + docId);
                if (data.is_collected) {
                    row.className  = 'flex items-center justify-between gap-3 px-3 py-2.5 rounded-lg border bg-green-50 border-green-200';
                    chk.className  = 'flex-shrink-0 w-5 h-5 rounded border-2 flex items-center justify-center transition-colors bg-green-500 border-green-500';
                    chk.innerHTML  = '<svg class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>';
                    name.className = 'text-sm line-through text-gray-400';
                } else {
                    row.className  = 'flex items-center justify-between gap-3 px-3 py-2.5 rounded-lg border bg-white border-gray-200';
                    chk.className  = 'flex-shrink-0 w-5 h-5 rounded border-2 flex items-center justify-center transition-colors border-gray-400 hover:border-green-500';
                    chk.innerHTML  = '';
                    name.className = 'text-sm text-gray-800';
                }
                // Re-calculate progress
                const allDocs = [...document.querySelectorAll('[id^="doc-row-"]')];
                const total   = allDocs.length;
                const done    = allDocs.filter(el => el.classList.contains('bg-green-50')).length;
                const pct     = total > 0 ? Math.round((done / total) * 100) : 0;
                document.getElementById('docProgressBar').style.width = pct + '%';
            });
        }
    function openConvertModal(leadId, leadName) {
        const baseUrl = '{{ url(Str::slug(Auth::user()->getRoleNames()->first()) . "/lead-manager") }}';
        document.getElementById('convertProjectForm').action = baseUrl + '/' + leadId + '/convert-to-project';
        document.getElementById('convertProjectName').value = leadName;
        document.getElementById('convertBudget').value = '';
        document.getElementById('convertLeadNameLabel').textContent = 'Lead: ' + leadName;
        document.getElementById('convertModalBackdrop').classList.remove('hidden');
        document.getElementById('convertModal').classList.remove('hidden');
        document.getElementById('convertProjectName').focus();
    }

    function closeConvertModal() {
        document.getElementById('convertModalBackdrop').classList.add('hidden');
        document.getElementById('convertModal').classList.add('hidden');
    }
    </script>
@endsection