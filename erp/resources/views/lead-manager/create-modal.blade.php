<div id="createModal" class="modal fixed inset-0 flex items-center justify-center z-50 hidden" style="margin-top: -50px">
    <div class="modal-backdrop fixed inset-0 bg-black opacity-50"></div>
    <div class="modal-container bg-white w-11/12 md:max-w-2xl mx-auto rounded shadow-lg z-50 overflow-y-auto" style="max-height:90vh">
        <div class="modal-content py-4 text-left px-6">
            <div class="modal-header flex justify-between items-center pb-3">
                <h3 class="text-xl font-semibold">Add New Lead</h3>
                <button class="modal-close-create z-50"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <form class="closest" id="createForm"
                    action="{{ route('role.lead-manager.store', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]) }}"
                    method="POST" enctype="multipart/form-data">
                    @csrf

                    {{-- Lead Type (full width, prominent) --}}
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-medium mb-2">Lead Type <span class="text-red-500">*</span></label>
                        <div class="flex gap-3 flex-wrap" id="create_lead_type_group">
                            @foreach(['air_ticket'=>['✈️','Air Ticket','blue'], 'visa'=>['🛂','Visa','purple'], 'software'=>['💻','Software','green'], 'interior'=>['🏠','Interior','orange'], 'other'=>['📋','Other','gray']] as $val=>[$icon,$label,$color])
                            <label class="lead-type-btn cursor-pointer flex items-center gap-2 px-4 py-2 rounded-lg border-2 border-gray-200 text-sm font-semibold text-gray-600 transition hover:border-{{ $color }}-400" data-value="{{ $val }}">
                                <input type="radio" name="lead_type" value="{{ $val }}" class="hidden create-lead-type-radio">
                                <span>{{ $icon }}</span> {{ $label }}
                            </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-700 text-sm font-medium mb-2">Lead Source <span class="text-red-500">*</span></label>
                            <select name="lead_source_id" id="lead_source_id" class="form-select select2 w-full px-3 py-2 border border-gray-300 rounded-md">
                                <option value="">Select Lead Source</option>
                                @foreach($leadSources as $source)
                                    <option value="{{ $source->id }}">{{ $source->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-gray-700 text-sm font-medium mb-2">Name <span class="text-red-500">*</span></label>
                            <input type="text" name="name" id="create_name" placeholder="Client name"
                                class="form-input w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label class="block text-gray-700 text-sm font-medium mb-2">Phone <span class="text-red-500">*</span></label>
                            <input type="text" name="phone" id="create_phone" placeholder="01XXXXXXXXX"
                                class="form-input w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label class="block text-gray-700 text-sm font-medium mb-2">Email</label>
                            <input type="text" name="email" placeholder="email@example.com"
                                class="form-input w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label class="block text-gray-700 text-sm font-medium mb-2">Status <span class="text-red-500">*</span></label>
                            <select name="status" id="create_status" class="form-select w-full px-3 py-2 border border-gray-300 rounded-md">
                                <option value="new">New</option>
                                <option value="contacted">Contacted</option>
                                <option value="qualified">Qualified</option>
                                <option value="proposal_sent">Proposal Sent</option>
                                <option value="negotiation">Negotiation</option>
                                <option value="won">Won</option>
                                <option value="lost">Lost</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-gray-700 text-sm font-medium mb-2">Assigned To <span class="text-red-500">*</span></label>
                            <select name="assigned_to" id="create_assigned_to" class="form-select select2 w-full px-3 py-2 border border-gray-300 rounded-md">
                                <option value="">Select Agent</option>
                                @foreach($employees as $emp)
                                    <option value="{{ $emp->id }}">{{ $emp->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-gray-700 text-sm font-medium mb-2">Customer</label>
                            <select name="customer_id" class="form-select select2 w-full px-3 py-2 border border-gray-300 rounded-md">
                                <option value="">Select Customer (optional)</option>
                                @foreach($customers as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-gray-700 text-sm font-medium mb-2">Notes</label>
                            <textarea name="notes" rows="2" placeholder="Notes..."
                                class="form-input w-full px-3 py-2 border border-gray-300 rounded-md"></textarea>
                        </div>
                    </div>

                    {{-- ── INTERIOR FIELDS ── --}}
                    <div id="create_interior_fields" class="hidden mt-4 p-4 bg-orange-50 rounded-lg border border-orange-200">
                        <p class="text-sm font-bold text-orange-700 mb-3">🏠 Interior Details</p>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Property Type</label>
                                <select name="property_type" class="form-select w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                                    <option value="flat">Flat / Apartment</option>
                                    <option value="house">House / Villa</option>
                                    <option value="office">Office Space</option>
                                    <option value="shop">Shop / Showroom</option>
                                    <option value="restaurant">Restaurant / Cafe</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Area (sq ft)</label>
                                <input type="number" name="area_sqft" step="0.01" placeholder="e.g. 1200"
                                    class="form-input w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Style Preference</label>
                                <select name="style_preference" class="form-select w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                                    <option value="">Select Style</option>
                                    <option value="modern">Modern</option>
                                    <option value="classic">Classic</option>
                                    <option value="minimalist">Minimalist</option>
                                    <option value="contemporary">Contemporary</option>
                                    <option value="traditional">Traditional</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Site Visit Date</label>
                                <input type="date" name="site_visit_date"
                                    class="form-input w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Project Duration</label>
                                <select name="site_visit_duration" class="form-select w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                                    <option value="">Select Duration</option>
                                    <option value="1 month">1 month</option>
                                    <option value="2 months">2 months</option>
                                    <option value="3 months">3 months</option>
                                    <option value="4 months">4 months</option>
                                    <option value="6 months">6 months</option>
                                    <option value="12 months">12 months</option>
                                    <option value="12+ months">12+ months</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Budget Min (BDT)</label>
                                <input type="number" name="budget_min" step="1000" placeholder="0"
                                    class="form-input w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Budget Max (BDT)</label>
                                <input type="number" name="budget_max" step="1000" placeholder="0"
                                    class="form-input w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                            </div>
                            <div class="col-span-2">
                                <label class="block text-xs font-medium text-gray-600 mb-1">Design Notes / Room Requirements</label>
                                <textarea name="design_notes" rows="2" placeholder="e.g. 3 bedroom, living room, modern style with warm tones..."
                                    class="form-input w-full px-3 py-2 border border-gray-300 rounded-md text-sm"></textarea>
                            </div>
                        </div>
                    </div>

                    {{-- ── AIR TICKET FIELDS ── --}}
                    <div id="create_air_ticket_fields" class="hidden mt-4 p-4 bg-blue-50 rounded-lg border border-blue-200">
                        <p class="text-sm font-bold text-blue-700 mb-3">✈️ Air Ticket Details</p>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">From (Airport Code)</label>
                                <input type="text" name="route_from" placeholder="DAC" maxlength="10"
                                    class="form-input w-full px-3 py-2 border border-gray-300 rounded-md uppercase text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">To (Airport Code)</label>
                                <input type="text" name="route_to" placeholder="DXB" maxlength="10"
                                    class="form-input w-full px-3 py-2 border border-gray-300 rounded-md uppercase text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Travel Date</label>
                                <input type="date" name="travel_date"
                                    class="form-input w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Passengers</label>
                                <input type="number" name="pax_count" value="1" min="1" max="50"
                                    class="form-input w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Class</label>
                                <select name="ticket_class" class="form-select w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                                    <option value="economy">Economy</option>
                                    <option value="business">Business</option>
                                    <option value="first">First</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Preferred Airline</label>
                                <input type="text" name="preferred_airline" placeholder="e.g. Biman, Emirates"
                                    class="form-input w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Quoted Price (BDT)</label>
                                <input type="number" name="quoted_price" step="0.01" placeholder="0.00"
                                    class="form-input w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Payment Status</label>
                                <select name="at_payment_status" class="form-select w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                                    <option value="pending">Pending</option>
                                    <option value="partial">Partial</option>
                                    <option value="paid">Paid</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    {{-- ── VISA FIELDS ── --}}
                    <div id="create_visa_fields" class="hidden mt-4 p-4 bg-purple-50 rounded-lg border border-purple-200">
                        <p class="text-sm font-bold text-purple-700 mb-3">🛂 Visa Application Details</p>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Destination Country</label>
                                <select name="country_id" class="form-select select2 w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                                    <option value="">Select Country</option>
                                    @foreach($countries as $country)
                                        <option value="{{ $country->id }}">{{ $country->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Visa Type</label>
                                <select name="visa_type" class="form-select w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                                    <option value="tourist">Tourist</option>
                                    <option value="business">Business</option>
                                    <option value="student">Student</option>
                                    <option value="work">Work</option>
                                    <option value="medical">Medical</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Travel Date</label>
                                <input type="date" name="visa_travel_date"
                                    class="form-input w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Document Status</label>
                                <select name="document_status" class="form-select w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                                    <option value="pending">Pending</option>
                                    <option value="collected">Collected</option>
                                    <option value="complete">Complete</option>
                                    <option value="submitted">Submitted to Embassy</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Payment Status</label>
                                <select name="visa_payment_status" class="form-select w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                                    <option value="pending">Pending</option>
                                    <option value="partial">Partial</option>
                                    <option value="paid">Paid</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Remarks</label>
                                <input type="text" name="visa_remarks" placeholder="Any notes..."
                                    class="form-input w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                            </div>
                        </div>
                    </div>

                </form>
            </div>
            <div class="modal-footer flex justify-end pt-3 mt-2 border-t">
                <button type="button" class="btn btn-secondary px-4 py-2 bg-gray-300 text-gray-700 rounded-md mr-2 modal-close-create">Cancel</button>
                <button id="createSubmit" type="button" class="btn btn-primary px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                    <i class="fas fa-save mr-1"></i> Save Lead
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Lead Type toggle — Create Modal
document.querySelectorAll('#create_lead_type_group .lead-type-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        // Radio select
        this.querySelector('input[type=radio]').checked = true;
        // Style toggle
        document.querySelectorAll('#create_lead_type_group .lead-type-btn').forEach(b => {
            b.classList.remove('border-blue-500','border-purple-500','border-green-500','border-orange-500','border-gray-500','bg-blue-50','bg-purple-50','bg-green-50','bg-orange-50','bg-gray-50','text-blue-700','text-purple-700','text-green-700','text-orange-700','text-gray-700');
            b.classList.add('border-gray-200','text-gray-600');
        });
        var val = this.dataset.value;
        var colorMap = {air_ticket:'blue', visa:'purple', software:'green', interior:'orange', other:'gray'};
        var c = colorMap[val];
        this.classList.remove('border-gray-200','text-gray-600');
        this.classList.add('border-'+c+'-500','bg-'+c+'-50','text-'+c+'-700');
        // Show/hide dynamic fields
        document.getElementById('create_interior_fields').classList.add('hidden');
        document.getElementById('create_air_ticket_fields').classList.add('hidden');
        document.getElementById('create_visa_fields').classList.add('hidden');
        if (val === 'interior')  document.getElementById('create_interior_fields').classList.remove('hidden');
        if (val === 'air_ticket') document.getElementById('create_air_ticket_fields').classList.remove('hidden');
        if (val === 'visa') document.getElementById('create_visa_fields').classList.remove('hidden');
    });
});
// Auto-uppercase airport codes
document.querySelectorAll('input[name=route_from], input[name=route_to]').forEach(function(inp) {
    inp.addEventListener('input', function() { this.value = this.value.toUpperCase(); });
});
</script>
