<div id="viewModal" class="fcm-overlay hidden modal-backdrop">
    <div class="fcm-box">
        <div class="fcm-head" style="background:linear-gradient(135deg,#1d4ed8,#2563eb);">
            <div class="fcm-head-ic"><i class="fas fa-plane"></i></div>
            <div>
                <div class="fcm-title" id="view_flight_number">Flight</div>
                <div class="fcm-sub">Contract flight details</div>
            </div>
            <button type="button" class="fcm-close modal-close-view">✕</button>
        </div>
        <div class="fcm-body">
            <div class="fcm-grid-4" style="font-size:13px;">
                <div><span class="text-gray-500 text-xs block">Airline / Flight No</span><span id="view_airline" class="font-medium">—</span></div>
                <div><span class="text-gray-500 text-xs block">Route</span><span id="view_route" class="font-medium">—</span></div>
                <div><span class="text-gray-500 text-xs block">Departure</span><span id="view_departure" class="font-medium">—</span></div>
                <div><span class="text-gray-500 text-xs block">Default Category <span class="text-gray-400">(per passenger below)</span></span><span id="view_category" class="font-medium">—</span></div>
                <div><span class="text-gray-500 text-xs block">Arrival (Destination)</span><span id="view_arrival" class="font-medium">-</span></div>
                <div><span class="text-gray-500 text-xs block">Category Type</span><span id="view_category_type" class="font-medium">-</span></div>
                <div><span class="text-gray-500 text-xs block">Default Service <span class="text-gray-400">(per passenger below)</span></span><span id="view_handling_type" class="font-medium">-</span></div>
                <div><span class="text-gray-500 text-xs block">Default Class <span class="text-gray-400">(per passenger below)</span></span><span id="view_ticket_class" class="font-medium">-</span></div>
                <div><span class="text-gray-500 text-xs block">Boarding Officer</span><span id="view_boarding_officer" class="font-medium">-</span></div>
                <div><span class="text-gray-500 text-xs block">Immigration Officer</span><span id="view_immigration_officer" class="font-medium">-</span></div>
                <div><span class="text-gray-500 text-xs block">Passengers</span><span id="view_passenger_count" class="font-medium">0</span></div>
                <div><span class="text-gray-500 text-xs block">Agent</span><span id="view_agent" class="font-medium">—</span></div>
                <div><span class="text-gray-500 text-xs block">Status</span><span id="view_status">—</span></div>
                <div class="fcm-full"><span class="text-gray-500 text-xs block">Notes</span><span id="view_notes" class="font-medium">—</span></div>
            </div>

            <div style="margin-top:14px;display:grid;grid-template-columns:repeat(3,1fr);gap:8px;">
                <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:8px 10px;">
                    <span style="font-size:10.5px;color:#166534;display:block;">Agent charge (total)</span>
                    <span id="view_total_agent_charge" style="font-size:14px;font-weight:700;color:#166534;">৳0</span>
                </div>
                <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:8px 10px;">
                    <span style="font-size:10.5px;color:#991b1b;display:block;">Travel cost (total)</span>
                    <span id="view_total_travel_cost" style="font-size:14px;font-weight:700;color:#991b1b;">৳0</span>
                </div>
                <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:8px 10px;">
                    <span style="font-size:10.5px;color:#1e40af;display:block;">Profit (total)</span>
                    <span id="view_total_profit" style="font-size:14px;font-weight:700;color:#1e40af;">৳0</span>
                </div>
            </div>
            <p style="font-size:10.5px;color:#9ca3af;margin-top:4px;">Summed from each passenger's own agent price, ticket/manpower/boarding cost — not the flight's old estimate.</p>

            <div style="margin-top:16px;">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-gray-500 text-xs font-medium">Passengers &amp; pipeline stage</span>
                    <span id="view_passengers_loading" class="text-[11px] text-gray-400 hidden">Loading…</span>
                </div>
                <p id="view_immigration_tracking_note" class="hidden" style="font-size:10.5px;color:#1e40af;background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px;padding:4px 8px;margin-bottom:6px;">
                    <i class="fas fa-circle-info mr-1"></i>Immigration-wise flight — pipeline stage, document status and ticket tracking below work exactly the same as any other flight.
                </p>
                <div style="max-height:260px;overflow-y:auto;border:1px solid #e5e7eb;border-radius:8px;">
                    <table style="width:100%;font-size:12px;border-collapse:collapse;">
                        <thead style="position:sticky;top:0;background:#f9fafb;">
                            <tr style="text-align:left;">
                                <th style="padding:6px 8px;">Passenger</th>
                                <th style="padding:6px 8px;">Category</th>
                                <th style="padding:6px 8px;">Class</th>
                                <th style="padding:6px 8px;">Stage</th>
                                <th style="padding:6px 8px;">Contract</th>
                                <th style="padding:6px 8px;">Agent</th>
                                <th style="padding:6px 8px;">PNR</th>
                                <th style="padding:6px 8px;text-align:right;">Agent Price</th>
                                <th style="padding:6px 8px;text-align:right;">Travel Cost</th>
                                <th style="padding:6px 8px;text-align:right;">Profit</th>
                                <th style="padding:6px 8px;"></th>
                            </tr>
                        </thead>
                        <tbody id="view_passengers_tbody">
                            <tr><td colspan="11" style="padding:10px 8px;color:#9ca3af;">No passengers on this flight yet.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div id="fcm-cmt-placeholder" style="margin-top:4px;"></div>
        </div>
        <div class="fcm-foot">
            <button type="button" class="fcm-btn fcm-btn-ghost modal-close-view">Close</button>
        </div>
    </div>
</div>
