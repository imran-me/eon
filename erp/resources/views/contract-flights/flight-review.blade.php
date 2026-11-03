{{--
    Step 3 — read the whole thing back before it is saved.

    Everything here is rendered from the live form by fmRenderReview() when
    the step is opened, so it can never drift from what is about to be posted.
    Nothing in this pane is an input; it holds no name= fields at all.
--}}
@php($prefix = $prefix ?? 'create')

<div class="fcm-section">
    <div class="fcm-section-title">
        <i class="fas fa-clipboard-check mr-1.5"></i> Review &amp; Confirm
        <span class="fm-sub-note" style="margin-left:auto;">Nothing is saved until you press Save</span>
    </div>

    <div id="{{ $prefix }}_review_problems" class="rv-problems" style="display:none;"></div>
    <div id="{{ $prefix }}_review_pending" class="rv-pending" style="display:none;"></div>

    <div class="rv-grid">
        <div class="rv-block">
            <div class="rv-block-title">Flight</div>
            <dl class="rv-dl" id="{{ $prefix }}_review_flight"></dl>
        </div>
        <div class="rv-block">
            <div class="rv-block-title">Agent &amp; status</div>
            <dl class="rv-dl" id="{{ $prefix }}_review_agent"></dl>
        </div>
    </div>

    <div class="rv-block" style="margin-top:14px;">
        <div class="rv-block-title">
            Passengers <span id="{{ $prefix }}_review_pax_count" class="rv-count">0</span>
        </div>
        <div class="rv-table-wrap">
            <table class="rv-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Passenger</th>
                        <th>Category</th>
                        <th>Class</th>
                        <th>Services</th>
                        <th>Ticket</th>
                        <th class="r">Cost</th>
                        <th class="r">Charge</th>
                        <th class="r">Profit</th>
                    </tr>
                </thead>
                <tbody id="{{ $prefix }}_review_pax"></tbody>
            </table>
        </div>
    </div>

    <div class="rv-totals" id="{{ $prefix }}_review_totals"></div>
</div>
