<div id="editModal" class="tk-create-overlay modal-backdrop" style="display:none;">
    <div class="tk-create-box">
        <div class="tk-create-head" style="background:linear-gradient(135deg,#1d4ed8,#2563eb);">
            <span style="font-size:18px;">✏️</span>
            <span class="tk-create-title">Edit Ticket Route</span>
            <button type="button" class="tk-create-close modal-close-edit">✕</button>
        </div>
        <div class="tk-create-body">
            <div class="tk-create-err" id="editModalErr"></div>
            <form id="editForm" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <input type="hidden" id="editItemId" name="id" value="">
                <div class="tk-create-grid">
                    <div class="tk-full">
                        <label class="tk-lbl" for="edit_title">Ticket Title <span class="req">*</span></label>
                        <input type="text" id="edit_title" name="title" class="tk-input" placeholder="e.g. Dhaka to Kuala Lumpur">
                        <p id="edit_title_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please enter a title</p>
                    </div>

                    <div>
                        <label class="tk-lbl" for="edit_from_airport_id">From Airport <span class="req">*</span></label>
                        <select id="edit_from_airport_id" name="from_airport_id" class="select2" style="width:100%">
                            <option value="">— Select airport —</option>
                            @foreach ($airports as $from_airport)
                                <option value="{{ $from_airport->id }}">{{ $from_airport->name }}</option>
                            @endforeach
                        </select>
                        <p id="edit_from_airport_id_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please choose an airport</p>
                    </div>

                    <div>
                        <label class="tk-lbl" for="edit_to_airport_id">To Airport <span class="req">*</span></label>
                        <select id="edit_to_airport_id" name="to_airport_id" class="select2" style="width:100%">
                            <option value="">— Select airport —</option>
                            @foreach ($airports as $to_airport)
                                <option value="{{ $to_airport->id }}">{{ $to_airport->name }}</option>
                            @endforeach
                        </select>
                        <p id="edit_to_airport_id_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please choose an airport</p>
                    </div>

                    <div>
                        <label class="tk-lbl" for="edit_airline_id">Airline <span class="req">*</span></label>
                        <select id="edit_airline_id" name="airline_id" class="select2" style="width:100%">
                            <option value="">— Select airline —</option>
                            @foreach ($airlines as $airline)
                                <option value="{{ $airline->id }}">{{ $airline->name }}</option>
                            @endforeach
                        </select>
                        <p id="edit_airline_id_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please choose an airline</p>
                    </div>

                    <div>
                        <label class="tk-lbl" for="edit_vendor_id">Vendor <span class="req">*</span></label>
                        <select id="edit_vendor_id" name="vendor_id" class="select2" style="width:100%">
                            <option value="">— Select vendor —</option>
                            @foreach ($vendors as $vendor)
                                <option value="{{ $vendor->id }}">{{ $vendor->name }}</option>
                            @endforeach
                        </select>
                        <p id="edit_vendor_id_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please choose a vendor</p>
                    </div>

                    <div>
                        <label class="tk-lbl" for="edit_portal_id">Portal <span class="req">*</span></label>
                        <select id="edit_portal_id" name="portal_id" class="select2" style="width:100%">
                            <option value="">— Select portal —</option>
                            @foreach ($allPortals as $portal)
                                <option value="{{ $portal->id }}">{{ $portal->name }}</option>
                            @endforeach
                        </select>
                        <p id="edit_portal_id_msg" class="text-red-500 text-xs mt-1 hidden error-message">Please choose a portal</p>
                    </div>

                    <div>
                        <label class="tk-lbl" for="edit_price">Default Cost Price</label>
                        <input type="number" id="edit_price" name="price" min="0" step="0.01" class="tk-input" placeholder="0.00">
                    </div>

                    <div>
                        <label class="tk-lbl" for="edit_qty">Total Stock Quantity</label>
                        <input type="number" id="edit_qty" name="qty" min="0" class="tk-input" placeholder="0">
                    </div>

                    <div style="display:flex;flex-direction:column;justify-content:flex-end;">
                        <label class="tk-lbl">&nbsp;</label>
                        <label class="tk-check-wrap">
                            <input type="checkbox" id="edit_status" name="status">
                            <span>Active</span>
                        </label>
                    </div>
                </div>
            </form>
        </div>
        <div class="tk-create-foot">
            <button type="button" class="tk-btn tk-btn-ghost modal-close-edit">✕ Cancel</button>
            <button type="button" id="editSubmit" data-action="" class="tk-btn" style="background:#2563eb;color:#fff;">💾 Update Ticket</button>
        </div>
    </div>
</div>
