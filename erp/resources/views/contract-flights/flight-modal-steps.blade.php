{{--
    Step bar + running context strip for the Add / Edit Flight modal.

    Three steps: the flight itself (airline, route, departure, officers,
    default agent) first — that is what the batch is named after and what
    seeds every passenger card — then the people, their tickets, services
    and money, then the whole thing read back before it is saved. Nothing on
    step 1 is mandatory: a batch can still be opened with passengers alone
    and the flight details filled in when the ticket arrives (owner,
    2026-09-21: flight details first, as the original form had it).

    $prefix is 'create' or 'edit'. The panes it drives are the .fm-pane blocks
    inside the matching form, addressed by data-pane="{prefix}-{step}".
--}}
@php($prefix = $prefix ?? 'create')

<div class="fm-bar">
<div class="fm-steps" data-prefix="{{ $prefix }}">
    <button type="button" class="fm-step active" data-step="flight">
        <span class="fm-step-n">1</span>
        <span class="fm-step-txt">Flight Details<span class="fm-step-sub">Airline, route, departure, officers</span></span>
    </button>
    <button type="button" class="fm-step" data-step="passengers">
        <span class="fm-step-n">2</span>
        <span class="fm-step-txt">Passengers &amp; Agent<span class="fm-step-sub">Who is travelling, billed to whom</span></span>
    </button>
    <button type="button" class="fm-step" data-step="review">
        <span class="fm-step-n">3</span>
        <span class="fm-step-txt">Review<span class="fm-step-sub">Check, then confirm</span></span>
    </button>
</div>

<div class="fm-context" id="{{ $prefix }}_context">
    <span class="fm-ctx-item"><span class="fm-ctx-muted">Route</span> <b id="{{ $prefix }}_ctx_route">—</b></span>
    <span class="fm-ctx-item"><span class="fm-ctx-muted">Departs</span> <b id="{{ $prefix }}_ctx_departure">—</b></span>
    <span class="fm-ctx-item"><span class="fm-ctx-muted">Agent</span> <b id="{{ $prefix }}_ctx_agent">Direct</b></span>
    <span class="fm-ctx-item"><span class="fm-ctx-muted">Carrying</span> <b id="{{ $prefix }}_ctx_seats">0 passengers</b></span>
    <span class="fm-ctx-item"><span class="fm-ctx-muted">Batch profit</span> <b id="{{ $prefix }}_ctx_profit">৳0</b></span>
</div>
</div>
