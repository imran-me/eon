{{--
    Per-passenger builder — shared by the Add Flight and Edit Flight modals.

    $prefix is 'create' or 'edit'. Every id below is namespaced with it so both
    modals can hold a full builder at once without colliding.

    Each card posts as passengers[<row>][...] and is picked up by
    $('#createForm').serialize(), so nothing here needs its own submit path.
    The hidden pb-present marker tells ManageFlightController that this form
    really did render the builder — without it the controller leaves the
    passenger list alone rather than reading "no rows" as "delete everybody".
--}}
@php($prefix = $prefix ?? 'create')

<div class="fcm-section">
    <div class="fcm-section-title" style="justify-content:space-between;">
        <span><i class="fas fa-passport mr-1.5"></i> Passengers &amp; Per-Head Costing</span>
        <div class="pb-rollup">
            <span><b id="{{ $prefix }}_pb_count">0</b> passengers</span>
            <span>Agent charge <b id="{{ $prefix }}_pb_charge">৳0</b></span>
            <span>Travel cost <b id="{{ $prefix }}_pb_cost">৳0</b></span>
            <span>Profit <b id="{{ $prefix }}_pb_profit" class="pb-profit">৳0</b></span>
        </div>
        <button type="button" class="pb-add-btn pb-add-btn-top" data-prefix="{{ $prefix }}">
            <i class="fas fa-plus"></i> Add Passenger
        </button>
    </div>

    <input type="hidden" name="passengers_present" value="1">

    {{-- Each card carries its own "Billed to agent" — one aircraft often
         carries several agents' passengers. The first agent chosen becomes
         the batch default (step 1's dropdown) and seeds the next cards. --}}
    <div class="pb-agent-bar">
        <p class="pb-agent-note">
            <i class="fas fa-circle-info"></i>
            Only the passport holder and their agent are needed to open a card — everything else can come later.
            New cards are billed to <b id="{{ $prefix }}_pb_agent_echo">the first agent you choose</b> unless you pick another on the card.
        </p>
    </div>

    <div id="{{ $prefix }}_passenger_cards" class="pb-cards" data-prefix="{{ $prefix }}"></div>

    <div id="{{ $prefix }}_passenger_empty" class="pb-empty">
        <i class="fas fa-user-plus"></i>
        <div>No passengers yet. Add the first one to start costing this flight.</div>
    </div>

    <p id="{{ $prefix }}_passenger_msg" class="text-red-500 text-xs mt-2 hidden error-message"></p>
</div>
