@php
    // Emitted through one @json($var) — @json() splits a literal array on its
    // commas, so an inline route() call with 3+ keys gets truncated.
    $pbUrls = [
        'holderTickets' => route('role.contract-flight-holders.ticket-purchases', ['role' => $role, 'passportHolder' => '__HID__']),
        'flightPassengers' => route('role.contract-flight-passengers.index', ['role' => $role, 'contractFlight' => '__FID__']),
        'createTicket' => route('role.ticket-direct-sale.store', ['role' => $role]),
        'importPdf' => route('role.ticket-direct-sale.create', ['role' => $role, 'import' => 1, 'embed' => 'cf']),
        'createRoute' => route('role.ticket-quick-create-route', ['role' => $role]),
    ];
    // Picking a passport that is waiting under Cancelled fills the card from
    // his previous one (2026-09-26). Only when installed AND routed: route()
    // on a name a stale route cache lacks would take the whole modal down.
    if (\App\Models\ContractFlightPassengerChange::available()) {
        $pbUrls['offFlight'] = route('role.contract-flight-holders.off-flight', ['role' => $role, 'passportHolder' => '__HID__']);
        $pbUrls['changeCharge'] = route('role.contract-flight-passenger-changes.charge', ['role' => $role, 'contractFlightPassengerChange' => '__CID__']);
    }
    $pbHolders = $passportHolders->map(fn ($h) => [
        'id' => $h->id,
        'text' => trim($h->name . ' — ' . $h->passport_no . ($h->phone ? ' — ' . $h->phone : '')),
        'phone' => $h->phone,
    ])->values();
    $pbVendors = $vendors->map(fn ($v) => ['id' => $v->id, 'text' => $v->name])->values();
    // Responsible people for the training / consulting services: whoever is
    // listed as an Instructor on the agent or vendor ledger, plus Trainer /
    // Consultant agents (ContractFlightPassengerServiceCost::providerQuery —
    // the same query the server validates against). Each carries the ledger
    // it is on and its id, so the picker can be searched by name or id.
    // Guarded — on a server where the roles do not exist yet, the role
    // query would throw and take the whole page down.
    try {
        $pbTrainers = \App\Models\ContractFlightPassengerServiceCost::providerQuery('boarding')
            ->with('roles:id,name')->orderBy('name')->get(['id', 'name', 'phone'])
            ->map(fn ($u) => [
                'id' => $u->id,
                'text' => $u->name . ' · #' . $u->id,
                'group' => \App\Models\ContractFlightPassengerServiceCost::providerLedgerOf($u) . 's',
                'phone' => $u->phone,
            ])->values();
    } catch (\Throwable) {
        $pbTrainers = collect();
    }
    $pbAgents = (isset($agents) ? $agents : collect())->map(fn ($a) => ['id' => $a->id, 'text' => $a->name])->values();
    $pbRoutes = $tickets->map(fn ($t) => ['id' => $t->id, 'text' => $t->title, 'price' => (float) $t->price, 'vendor_id' => $t->vendor_id, 'portal_id' => $t->portal_id, 'legs' => $t->legsArray(), 'sector' => strtoupper(($t->from_airport?->code ?? '').'-'.($t->to_airport?->code ?? '')), 'ff' => strtolower($t->from_airport?->country?->code ?? ''), 'tf' => strtolower($t->to_airport?->country?->code ?? '')])->values();
    $pbPortals = $portals->map(fn ($p) => ['id' => $p->id, 'text' => $p->name])->values();
    // $banks is already loaded for every page this partial is included from
    // (ManageFlightController::index() and FlightScheduleController::index())
    // — it feeds the flight-level cost bank picker, so no new query here.
    $pbBanks = (isset($banks) ? $banks : collect())->map(fn ($b) => ['id' => $b->id, 'text' => $b->name])->values();
@endphp
<script>
/* ══════════════════════════════════════════════════════════════════════
   Per-passenger builder for the Add / Edit Flight modals.

   A flight is booked agent-wise: one agent brings a group of 3-5 people
   whose costs differ per head. So the flight carries the agent, and each
   passenger card carries that person's own PNR and their own money.

   Everything is a plain named input inside #createForm / #editeForm, so
   the existing serialize()-and-post submit handlers pick the rows up with
   no change of their own.
   ══════════════════════════════════════════════════════════════════════ */
(function () {
    const PB_URLS = @json($pbUrls);
    const PB_HOLDERS = @json($pbHolders);
    const PB_FLAGS = @json(asset('flags')), PB_AIRLOGOS = @json(asset('airline-logos'));   // itinerary flags and airline marks (2026-10-08)
    const PB_VENDORS = @json($pbVendors);
    const PB_TRAINERS = @json($pbTrainers);
    const PB_AGENTS = @json($pbAgents);
    const PB_ROUTES = @json($pbRoutes);
    const PB_PORTALS = @json($pbPortals);
    const PB_BANKS = @json($pbBanks);
    const PB_SPLIT_FARE = @json(\App\Models\ContractFlightPassengerChange::splitFareAvailable());
    const PB_FLAG_BASE = @json(asset('flags'));
    const PB_SERVICE_COST_INPUTS = {
        manpower: '.pb-manpower',
        boarding: '.pb-boarding',
        immigration: '.pb-immigration',
        other: '.pb-other-cost'
    };

    // Monotonic so a removed row's name never collides with a later one.
    let pbRowSeq = 0;
    // Blocks Save while an edit modal is still fetching its passengers —
    // saving an empty builder would delete the whole batch.
    const pbLoading = { create: false, edit: false };

    const money = v => '৳' + (Number(v) || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const moneyShort = v => '৳' + Math.round(Number(v) || 0).toLocaleString();
    const num = v => Number(v) || 0;

    function pbContainer(prefix) { return $('#' + prefix + '_passenger_cards'); }
    function pbPrefixOf($el) { return $el.closest('.pb-cards').data('prefix') || 'create'; }
    /**
     * Rates are looked up per passenger, not per flight.
     *
     * A preset is keyed on airline + category + class + service mix. Only the
     * airline is a property of the batch; the other three now live on the
     * card, so the lookup belongs here — the same three passports on one
     * aircraft can legitimately match three different rate cards.
     *
     * Fills only boxes the user has not typed in (pbMarkEdited below), so
     * changing a card's category never overwrites a figure someone entered.
     */
    function pbLoadPreset(node, prefix) {
        const airlineId = $('#' + prefix + '_pricing_airline_id').val();
        const categoryId = node.find('.pb-category').val();
        if (!airlineId || !categoryId) return;

        $.get(window.flightPresetMatchUrl, {
            airline_id: airlineId,
            flight_category_id: categoryId,
            ticket_class: node.find('.pb-class').val() || 'economy',
            handling_type: node.find('.pb-handling').val() || 'manpower_wise'
        }).done(function (response) {
            if (!response.success || !response.data) return;
            const p = response.data;
            const fill = (sel, value) => {
                const input = node.find(sel);
                if (input.attr('data-edited') === '1') return;
                input.val(value || '');
            };
            // Ticket cost is never seeded — it only ever comes from the PNR.
            fill('.pb-manpower', p.manpower_cost);
            fill('.pb-boarding', p.boarding_cost);
            fill('.pb-immigration', p.immigration_cost);
            fill('.pb-agent-price', p.sale_price);
            pbApplyCardRules(node);
            pbRefresh(prefix);
        });
    }

    /* ── Building a card ──────────────────────────────────────────── */

    function pbAddCard(prefix, data) {
        data = data || {};
        const template = document.getElementById('pbCardTemplate');
        if (!template) {
            // passenger-builder-assets was not included on this page. Fail
            // visibly on this one control rather than throwing, so the rest
            // of the flight form still works.
            $('#' + prefix + '_passenger_msg').removeClass('hidden')
                .text('The passenger form could not load on this page. Reload, and report it if it persists.');
            return $();
        }
        const row = pbRowSeq++;
        const node = $(template.content.cloneNode(true)).children().first();
        node.attr('data-row', row);
        node.find('.pb-holder').attr('name', 'passengers[' + row + '][passport_holder_id]');

        // Costs and identity post as named inputs; ticket links are appended
        // as hidden inputs by pbRenderLinked() since their count varies.
        const named = {
            '.pb-class': 'ticket_class',
            '.pb-handling': 'handling_type',
            '.pb-category': 'flight_category_id',
            '.pb-phone': 'contact_phone',
            '.pb-tickets-required': 'tickets_required',
            '.pb-remarks': 'remarks',
            '.pb-manpower': 'manpower_cost',
            '.pb-boarding': 'boarding_cost',
            '.pb-immigration': 'immigration_cost',
            '.pb-other-title': 'other_title',
            '.pb-other-cost': 'other_cost',
            '.pb-reissue': 'reissue_cost',

            '.pb-agent-price': 'agent_price',
            '.pb-bmet': 'has_bmet',
            '.pb-boarding-on': 'boarding_training'
        };
        Object.keys(named).forEach(function (sel) {
            node.find(sel).attr('name', 'passengers[' + row + '][' + named[sel] + ']');
        });
        node.find('.pb-sc-row').each(function () {
            const costType = $(this).data('cost-type');
            $(this).find('[data-sc-field]').each(function () {
                $(this).attr('name', 'passengers[' + row + '][service_costs][' + costType + '][' + $(this).data('sc-field') + ']');
            });
        });

        // The agent is per passenger — that is the key the Party Statement
        // and Flight Accounts bill on, and one aircraft routinely carries
        // several agents' people. Step 1's agent only seeds a new card.
        node.find('.pb-agent').attr('name', 'passengers[' + row + '][agent_id]');

        pbContainer(prefix).append(node);

        pbFillSelect(node.find('.pb-holder'), PB_HOLDERS, 'Select passport holder…');
        pbFillSelect(node.find('.pb-nt-vendor'), PB_VENDORS, '— none —');
        pbFillSelect(node.find('.pb-nt-route'), PB_ROUTES, 'Select from Manage Tickets');
        pbFillSelect(node.find('.pb-nt-portal'), PB_PORTALS, '— none —');
        pbFillSelect(node.find('.pb-nt-bank'), PB_BANKS, '— select bank —');
        // pristine per-ticket block (options filled, no select2 yet) — cloned per leg
        node.data('pbNtTemplate', node.find('.pb-nt-ticket').first().clone());

        pbFillSelect(node.find('.pb-agent'), PB_AGENTS, 'Select agent…');
        node.find('.pb-agent').val(data.agent_id ? String(data.agent_id) : ($('#' + prefix + '_agent_id').val() || ''));
        node.find('.pb-sc-vendor').each(function () {
            const trainer = $(this).closest('.pb-sc-row').data('provider') === 'trainer';
            if (trainer) pbFillGroupedSelect($(this), PB_TRAINERS, '— select instructor —');
            else pbFillSelect($(this), PB_VENDORS, '— select vendor —');
        });
        node.find('.pb-sc-bank').each(function () { pbFillSelect($(this), PB_BANKS, '— select bank / cash —'); });

        node.find('.pb-holder').select2({
            dropdownParent: $('#' + prefix + 'Modal'),
            width: '100%',
            placeholder: 'Select passport holder…',
            dropdownCssClass: 'pb-select2-dropdown',
            templateResult: pbHolderResultTemplate
        });
        node.find('.pb-agent').select2({ dropdownParent: $('#' + prefix + 'Modal'), width: '100%', dropdownCssClass: 'pb-select2-dropdown' });
        node.find('.pb-nt-vendor, .pb-nt-portal, .pb-nt-bank').select2({ dropdownParent: $('#' + prefix + 'Modal'), width: '100%', dropdownCssClass: 'pb-select2-dropdown' });
        node.find('.pb-nt-route, .pb-route-airline, .pb-route-from, .pb-route-to').select2({ dropdownParent: $('#' + prefix + 'Modal'), width: '100%', dropdownCssClass: 'pb-select2-dropdown' });
        node.find('.pb-sc-vendor, .pb-sc-bank').select2({ dropdownParent: $('#' + prefix + 'Modal'), width: '100%', dropdownCssClass: 'pb-select2-dropdown' });

        pbHydrate(node, prefix, data);
        pbRefresh(prefix);
        return node;
    }

    function pbFillSelect($select, items, placeholder) {
        $select.append(new Option(placeholder, '', true, true));
        items.forEach(function (item) {
            const option = new Option(item.text, item.id);
            // Route flags for the Ticketing-style picker (partials/ticketing-dropdowns).
            if (item.ff) option.dataset.ff = item.ff;
            if (item.tf) option.dataset.tf = item.tf;
            $select.append(option);
        });
    }

    /**
     * Same, with the options under <optgroup>s by `item.group` — the
     * responsible-person picker lists Agents and Vendors under their own
     * ledger heading. select2's search matches the option text, which
     * carries "Name · #id", so typing either a name or an id finds them.
     */
    function pbFillGroupedSelect($select, items, placeholder) {
        $select.append(new Option(placeholder, '', true, true));
        const groups = {};
        items.forEach(function (item) {
            const key = item.group || '';
            if (!groups[key]) {
                groups[key] = key ? $('<optgroup>').attr('label', key).appendTo($select) : $select;
            }
            groups[key].append(new Option(item.text, item.id));
        });
    }

    /**
     * Passport holder dropdown rows as name (bold) + passport/phone (muted)
     * on their own line, instead of one long "Name — Passport — Phone"
     * string wrapping mid-word in the narrow list. The closed select box
     * keeps select2's plain single-line text — only the open list gets this.
     */
    function pbHolderResultTemplate(state) {
        if (!state.id) return state.text; // placeholder row
        const parts = String(state.text).split(' — ');
        const $row = $('<div class="pb-holder-opt"></div>');
        $('<div class="pb-holder-opt-name"></div>').text(parts[0] || state.text).appendTo($row);
        if (parts.length > 1) {
            $('<div class="pb-holder-opt-meta"></div>').text(parts.slice(1).join('  ·  ')).appendTo($row);
        }
        return $row;
    }

    /** Seed a card: an existing passenger's saved values, else the flight's Price Builder rates. */
    /**
     * Re-booking (owner, 2026-09-26): "just search his name and all info
     * will be filled, just the new ticket has to be done again, with an
     * additional charge as he cancelled his flight or ticket earlier".
     * If the picked passport is waiting under Cancelled, fill this card from
     * his previous card and add the Re-booking box; the save then moves THAT
     * passenger onto this flight (ManageFlightController::rebookFromRow).
     */
    function pbCheckRebook(node) {
        const holderId = node.find('.pb-holder').val();
        if (!PB_URLS.offFlight || !holderId) return;
        $.get(PB_URLS.offFlight.replace('__HID__', holderId)).done(function (r) {
            const p = r && r.passenger;
            // Gone, or the passport was changed again while this was loading.
            if (!p || !p.rebook || String(node.find('.pb-holder').val()) !== String(holderId)) return;
            const prefix = pbPrefixOf(node);
            pbHydrate(node, prefix, p);
            node.find('.pb-agent').val(p.agent_id ? String(p.agent_id) : '').trigger('change');
            pbRebookBox(node, p);
            pbRefresh(prefix);
        });
    }

    /**
     * Re-booking someone from Cancelled (2026-09-26). The visible box was
     * removed (owner, 2026-09-30) — the refund, its penalty and any charge
     * were already decided and posted on Re-assign, and the fare difference
     * sits in the card's money area. What stays is hidden, and it matters:
     * rebook_passenger_id makes Save MOVE him instead of adding a duplicate.
     * "How" is decided so nothing is ever cancelled silently:
     *   refunded through Re-assign → new ticket, the legs he kept untouched;
     *   plain cancel, still holding a live ticket → same ticket (reschedule);
     *   nothing live → new ticket.
     */
    function pbRebookBox(node, p) {
        const rb = p.rebook, row = node.attr('data-row');
        node.data('pbReissue', p.reissue_money || null);
        setTimeout(() => pbRenderReissueMoney(node), 0);
        const name = f => 'passengers[' + row + '][' + f + ']';
        const live = rb.live_tickets || [];
        const kind = rb.reason_key === 'new_ticket' || !live.length ? 'reissue' : 'reschedule';
        node.find('.pb-rebook').remove();
        const box = $('<div class="pb-rebook" hidden></div>').html(
            '<input type="hidden" name="' + name('rebook_passenger_id') + '" value="' + Number(rb.passenger_id) + '">'
            + '<input type="hidden" class="pb-rebook-kind" name="' + name('rebook_kind') + '" value="' + kind + '">');
        node.find('.pb-card-body').prepend(box);
        // His ticket count is his own (owner, 2026-10-13: the flight's route is
        // only a label) — the route he is issued on sets it, not the flight's.
        node.find('.pb-tickets-required').val(String(Math.max(1, parseInt(p.tickets_required, 10) || 1)));
    }

    function pbHydrate(node, prefix, data) {
        const rate = id => $('#' + prefix + '_' + id).val();
        const seed = (saved, fallback) => (saved === null || saved === undefined || saved === '') ? fallback : saved;

        // Read by pbApplyServiceDefault(): a saved passenger keeps exactly
        // what was recorded, so the standing service fees seed new cards only.
        node.attr('data-pb-saved', data.id ? '1' : '0');
        // The saved passenger's id (the Re-Issue / Refund endpoint is keyed on
        // it) and his flight changes, which pbLinkedRow() reads to show each
        // ticket's own adjustments without a second request (from live, 2026-09-29).
        node.data('pbPassengerId', data.id || null);
        node.data('pbFlightChanges', data.flight_changes || []);
        node.data('pbReissue', data.reissue_money || null);

        if (data.passport_holder_id) {
            node.find('.pb-holder').val(String(data.passport_holder_id)).trigger('change.select2');
            // Already two fields here, so no need to split the option label.
            node.find('.pb-card-name').text(data.name || 'Passenger');
            node.data('pbPassport', data.passport_no || '');
        }
        // Class, category and service mix all belong to the traveller — there
        // is no flight-level default to fall back to any more. A new card
        // starts at the safe minimum and the person entering it answers.
        node.find('.pb-class').val(data.ticket_class || 'economy');
        node.find('.pb-category').val(data.flight_category_id || '');
        pbShowCategoryType(node);
        node.find('.pb-phone').val(data.contact_phone || data.phone || '');
        // A saved passenger keeps their count; a new card takes one ticket
        // per leg of the flight's route (step 1), one for a direct route.
        node.find('.pb-tickets-required').val(String(Math.max(1, parseInt(data.tickets_required, 10) || 1)));
        node.find('.pb-remarks').val(data.remarks || '');

        // Service mix. The checkboxes are what people click; the hidden
        // selects are what the form posts, so set both from the same source.
        const handling = data.handling_type || 'manpower_wise';
        node.find('.pb-handling').val(handling);
        node.find('.pb-svc-immigration').prop('checked', handling === 'immigration_wise');

        node.find('.pb-bmet').val(data.has_bmet ? '1' : '0');
        node.find('.pb-svc-bmet').prop('checked', !data.has_bmet);

        // Boarding Training defaults on for a new card; a saved passenger
        // keeps exactly what was recorded.
        const boardingOn = data.id ? Boolean(data.boarding_training) : true;
        node.find('.pb-boarding-on').val(boardingOn ? '1' : '0');
        node.find('.pb-svc-boarding').prop('checked', boardingOn);

        // A saved passenger keeps exactly what was recorded, including a
        // deliberate blank. A new card is filled by the rate preset once its
        // category and class are known (pbLoadPreset), never from the flight.
        node.find('.pb-manpower').val(seed(data.manpower_cost, ''));
        node.find('.pb-boarding').val(seed(data.boarding_cost, ''));
        node.find('.pb-immigration').val(seed(data.immigration_cost, ''));
        node.find('.pb-other-title').val(data.other_title || '');
        node.find('.pb-other-cost').val(seed(data.other_cost, ''));
        node.find('.pb-svc-other').prop('checked', Boolean(data.other_title || data.other_cost != null));
        node.find('.pb-reissue').val(seed(data.reissue_cost, ''));
        node.find('.pb-agent-price').val(seed(data.agent_price, ''));
        // Charges from his moves between flights (re-assign, 2026-09-30):
        // billed to the agent → in the charge; reissue fees / ours → in the
        // cost. Read-only here — they change only through Re-assign.
        node.data('penBilled', Number(data.penalty_billed) || 0).data('penBorne', Number(data.penalty_borne) || 0)
            .data('penParts', data.penalty_parts || null);

        const today = new Date().toISOString().slice(0, 10);
        const serviceCosts = data.service_costs || {};
        node.find('.pb-sc-row').each(function () {
            const accountingRow = $(this);
            const saved = serviceCosts[accountingRow.data('cost-type')] || {};
            accountingRow.find('.pb-sc-vendor').val(saved.vendor_id ? String(saved.vendor_id) : '').trigger('change.select2');
            accountingRow.find('.pb-sc-status').val(saved.payment_status && saved.payment_status !== 'unassigned' ? saved.payment_status : 'due');
            accountingRow.find('.pb-sc-paid').val(seed(saved.paid_amount, 0));
            accountingRow.find('.pb-sc-bank').val(saved.bank_id ? String(saved.bank_id) : '').trigger('change.select2');
            accountingRow.find('.pb-sc-incurred').val(saved.incurred_on || today);
            accountingRow.find('.pb-sc-payment-date').val(saved.payment_date || (num(saved.paid_amount) > 0 ? today : ''));
            accountingRow.find('.pb-sc-due-date').val(saved.due_date || '');
        });

        // Anything already recorded counts as typed, so a later preset lookup
        // cannot overwrite a saved passenger's real figures.
        if (data.id) {
            node.find('.pb-manpower, .pb-boarding, .pb-immigration, .pb-agent-price')
                .filter(function () { return $(this).val() !== ''; })
                .attr('data-edited', '1');
        }

        node.find('.pb-nt-date').val(new Date().toISOString().slice(0, 10));

        pbRenderLinked(node, data.ticket_purchases || []);
        if (data.passport_holder_id) pbLoadHolderTickets(node);
        pbApplyCardRules(node);
        pbSyncServiceCostRows(node);
        if (node.find('.pb-category').val()) pbLoadPreset(node, prefix);
    }

    function pbServiceCostAmount(node, type) {
        const selector = PB_SERVICE_COST_INPUTS[type];
        return selector ? num(node.find(selector).val()) : 0;
    }

    function pbApplyServicePaymentRules(accountingRow, forcePaidAmount) {
        const node = accountingRow.closest('.pb-card');
        const amount = pbServiceCostAmount(node, accountingRow.data('cost-type'));
        const status = accountingRow.find('.pb-sc-status').val() || 'due';
        const paidInput = accountingRow.find('.pb-sc-paid');

        if (status === 'due') paidInput.val(0);
        if (status === 'paid' && forcePaidAmount !== false) paidInput.val(amount ? amount.toFixed(2) : 0);

        const paid = num(paidInput.val());
        const hasPayment = status !== 'due' || paid > 0;
        accountingRow.find('.pb-sc-payment-date-wrap').toggle(hasPayment);
        accountingRow.find('.pb-sc-due-date-wrap').toggle(status !== 'paid');
        if (!hasPayment) {
            accountingRow.find('.pb-sc-bank').val('').trigger('change.select2');
            accountingRow.find('.pb-sc-payment-date').val('');
        } else if (!accountingRow.find('.pb-sc-payment-date').val()) {
            accountingRow.find('.pb-sc-payment-date').val(new Date().toISOString().slice(0, 10));
        }
        if (status === 'paid') accountingRow.find('.pb-sc-due-date').val('');
    }

    function pbSyncServiceCostRows(node) {
        node.find('.pb-sc-row').each(function () {
            const accountingRow = $(this);
            const type = accountingRow.data('cost-type');
            const amount = pbServiceCostAmount(node, type);
            // A row per TICKED service, so the responsible person can be
            // named before (or without) a figure; Others follows its tick too.
            const ticked = type === 'boarding' ? node.find('.pb-svc-boarding').is(':checked')
                : type === 'immigration' ? node.find('.pb-svc-immigration').is(':checked')
                : type === 'manpower' ? node.find('.pb-svc-bmet').is(':checked')
                : type === 'other' ? node.find('.pb-svc-other').is(':checked')
                : amount > 0;
            const shown = ticked || amount > 0;
            accountingRow.toggle(shown);
            if (!shown) return;
            if (type === 'other') {
                accountingRow.find('.pb-sc-label').text(node.find('.pb-other-title').val().trim() || 'Other service');
            }
            pbApplyServicePaymentRules(accountingRow, true);
        });
    }

    /* ── Route legs ───────────────────────────────────────────────── */

    // The flight's route is only a label (owner, 2026-10-13): changing it no
    // longer touches any card's "tickets on route" — each passenger's count
    // follows the route HIS ticket is issued on (pbApplyRouteLegs) or a hand-set value.

    // A route with stops is one ticket per leg (owner, 2026-09-21). Picking
    // such a route sets "tickets on route" to the leg count and shows a leg
    // picker in the new-ticket form: Ticket 1 · DAC → IST, Ticket 2 · IST →
    // ECN, each with the leg's default cost. The next leg without a PNR is
    // preselected, so issuing twice walks the route in order.
    function pbApplyRouteLegs(node, route) {
        const legs = (route && route.legs) || [];
        if (legs.length > 1) {
            const required = node.find('.pb-tickets-required');
            if (required.val() !== String(legs.length)) required.val(String(Math.min(5, legs.length))).trigger('change');
        }
        pbEnsureTicketBlocks(node, route);
    }

    /**
     * One block per ticket of the chosen route, as Ticket Entry lays them out:
     * direct = one block; via = one per leg with the "Ticket i of N · A → B"
     * strip, the leg's From / To / Travel date / Cost row and the leg's own
     * seller and price seeded. Extra blocks are dropped when the route changes.
     */
    /** Shows the "+ Issue New Ticket" panel, seeding its route if empty. Shared by a manual click and pbReissueLeg(). */
    function pbShowNewTicketPanel(node) {
        node.find('.pb-mode-new').addClass('active');
        node.find('.pb-mode-existing').removeClass('active');
        node.find('.pb-newticket').show();
        node.find('.pb-existing').hide();
        if (!node.find('.pb-nt-route').val()) {
            node.find('.pb-nt-route').val($('#' + pbPrefixOf(node) + '_ticket_id').val() || '').trigger('change');
        } else {
            // Route already chosen: rebuild so only the legs without a live
            // ticket are offered (the 'change' trigger above does it otherwise).
            const rt = PB_ROUTES.find(t => String(t.id) === String(node.find('.pb-nt-route').val()));
            if (rt) pbEnsureTicketBlocks(node, rt);
        }
        pbSeedTicketSource(node, false);
    }

    /**
     * "New PNR" on one linked ticket: the airline swapped this PNR for a new
     * one, but his OTHER leg still flies on its own live ticket. Unlinks just
     * this ticket (nothing is posted — unlinking never touches money) and
     * opens Issue New Ticket scoped to its leg alone (pbScopedLeg).
     */
    function pbReissueLeg(node, ticketId, legNumber) {
        const tickets = node.data('pbTickets') || [];
        pbRenderLinked(node, tickets.filter(t => String(t.id) !== String(ticketId)));
        pbLoadHolderTickets(node, node.find('.pb-pnr-search').val());
        node.data('pbScopedLeg', legNumber || null);
        pbShowNewTicketPanel(node);
        const route = PB_ROUTES.find(t => String(t.id) === String(node.find('.pb-nt-route').val()));
        pbEnsureTicketBlocks(node, route);
        pbRefresh(pbPrefixOf(node));
    }

    function pbEnsureTicketBlocks(node, route) {
        const legs = (route && route.legs) || [];
        const via = legs.length > 1;
        // pbScopedLeg: only ONE leg needs a new PNR — build exactly that
        // leg's block, tagged with its real index so it saves on the right
        // leg_sequence.
        const scopedLeg = via ? node.data('pbScopedLeg') : null; // 1-based
        // Legs that already fly on a live ticket are left out (owner,
        // 2026-09-30): after refunding DAC → IST only, Issue New Ticket is for
        // DAC → IST alone; on a new passenger who got leg 1 earlier, only
        // leg 2 is asked for now. All legs covered = all legs (a replacement).
        const missing = via ? pbMissingLegs(node, legs) : [];
        const legIndexes = scopedLeg ? [scopedLeg - 1]
            : (via ? (missing.length && missing.length < legs.length ? missing : legs.map((_, i) => i)) : [null]);
        const partial = via && !scopedLeg && missing.length && missing.length < legs.length;
        const n = legIndexes.length;
        const wrap = node.find('.pb-nt-tickets');
        const prefix = pbPrefixOf(node);
        wrap.children('.pb-nt-ticket').slice(n).remove();
        while (wrap.children('.pb-nt-ticket').length < n) {
            const block = node.data('pbNtTemplate').clone();
            wrap.append(block);
            block.find('.pb-nt-vendor, .pb-nt-portal, .pb-nt-bank').select2({ dropdownParent: $('#' + prefix + 'Modal'), width: '100%', dropdownCssClass: 'pb-select2-dropdown' });
        }
        const flag = cc => cc ? '<img class="pb-flag" src="' + PB_FLAG_BASE + '/' + String(cc).replace(/[^a-z]/g, '') + '.png" alt="" onerror="this.remove()">' : '';
        const esc = t => $('<span>').text(t || '').html();
        const departure = ($('#' + prefix + '_departure_at').val() || '').slice(0, 10);
        wrap.children('.pb-nt-ticket').each(function (pos) {
            const legIdx = legIndexes[pos];
            const i = legIdx == null ? 0 : legIdx;
            const block = $(this).attr('data-leg', i);
            const leg = legIdx != null ? legs[legIdx] : null;
            const strip = block.find('.pb-nt-strip');
            const legsBox = block.find('.pb-nt-legs');
            if (leg) {
                strip.show();
                strip.find('.k').text('Ticket ' + (i + 1) + ' of ' + legs.length);
                strip.find('.sec').html(flag(leg.from_flag) + esc(leg.from_code) + ' <i class="fas fa-arrow-right-long"></i> ' + flag(leg.to_flag) + esc(leg.to_code) + (leg.airline ? ' <span style="color:#a5b4fc">·</span> ' + esc(leg.airline) : ''));
                strip.find('.note').text('part of route ' + (route.text || '')
                    + (scopedLeg ? ' — new PNR for this leg only, the other is untouched' : (partial ? ' — the other leg already has its ticket' : '')));
                // "Issue this leg now" — untick to issue it later (same switch
                // as "Reissue this leg" / "Refund this leg").
                if (!block.find('.pb-nt-include').length) {
                    strip.append('<label class="pb-nt-include-wrap" style="display:inline-flex;align-items:center;gap:5px;margin-left:10px;font-size:11px;font-weight:700;color:#5b21b6;cursor:pointer;"><input type="checkbox" class="pb-nt-include" checked> Issue this leg now</label>');
                }
                block.find('.pb-nt-include-wrap').toggle(legIndexes.length > 1);
                block.find('.pb-nt-legs-hd').hide();
                legsBox.empty().append(pbLegRow({ from: leg.from_code, to: leg.to_code, date: block.find('.pb-nt-dep').val() || departure, cost: leg.cost }).addClass('is-fixed'));
                block.find('.pb-nt-amount').val(leg.cost != null ? num(leg.cost).toFixed(2) : (route ? num(route.price).toFixed(2) : ''));
                const v = leg.vendor_id ? String(leg.vendor_id) : '', p = !leg.vendor_id && leg.portal_id ? String(leg.portal_id) : '';
                const ntVendor = block.find('.pb-nt-vendor'), ntPortal = block.find('.pb-nt-portal');
                const untouched = (!ntVendor.val() && !ntPortal.val()) || (ntVendor.data('pbLegSeeded') === (ntVendor.val() || '') && ntPortal.data('pbLegSeeded') === (ntPortal.val() || ''));
                if (untouched) {
                    ntVendor.data('pbLegSeeded', v).val(v).trigger('change');
                    ntPortal.data('pbLegSeeded', p).val(p).trigger('change');
                }
            } else {
                strip.hide();
                block.find('.pb-nt-legs-hd').show();
                legsBox.children('.is-fixed').remove();
                block.find('.pb-nt-amount').val(route ? num(route.price).toFixed(2) : '');
            }
            if (!block.find('.pb-nt-date').val()) block.find('.pb-nt-date').val(new Date().toISOString().slice(0, 10));
            pbSyncNewTicketPayStatus(block);
        });
        pbPrefillFromRefunded(node, legs);
    }

    /**
     * Does this PNR fly the route's leg? One of its flights leaves the leg's airport and the journey
     * reaches the leg's end — DAC → BAH → IST on one e-ticket covers "DAC → IST", BAH being only a
     * transit. Its FIRST flight alone (DAC → BAH) was compared before, so a transit ticket covered no
     * leg: the card said "no ticket yet" under a linked ticket and Issue New Ticket asked for both
     * legs again (owner, 2026-10-18).
     */
    function pbCoversLeg(t, leg) {
        const from = String(leg.from_code || '').toUpperCase(), to = String(leg.to_code || '').toUpperCase();
        const fl = (t.legs || []).map(l => [String(l.from_location || '').toUpperCase().trim(), String(l.to_location || '').toUpperCase().trim()]);
        for (let s = 0; s < fl.length; s++) {
            if (fl[s][0] !== from) continue;
            for (let k = s; k < fl.length; k++) {
                if (k > s && fl[k][0] !== fl[k - 1][1]) break;   // not one journey
                if (fl[k][1] === to) return true;
                if (fl[k][1] === from) break;                    // came back (a round trip's return)
            }
        }
        return false;
    }

    /** 0-based indexes of the route's legs that no live linked ticket covers yet. */
    function pbMissingLegs(node, legs) {
        const tickets = (node.data('pbTickets') || []).filter(t => t.status !== 'cancelled');
        const used = new Set();
        const missing = [];
        legs.forEach(function (leg, i) {
            const t = tickets.find(x => !used.has(x.id) && ((x.leg_sequence && Number(x.leg_sequence) === i + 1) || pbCoversLeg(x, leg)));
            if (t) used.add(t.id); else missing.push(i);
        });
        return missing;
    }

    /**
     * After a refund (owner, 2026-09-30): each new-ticket block starts from
     * the ticket it replaces — same vendor or portal, trip and price — matched
     * by sector (DAC-IST), or the only one when there is one. Every field
     * stays editable; nothing already typed is overwritten.
     */
    function pbPrefillFromRefunded(node, legs) {
        const rm = node.data('pbReissue');
        const old = (rm && rm.old_tickets) || [];
        if (!old.length) return;
        node.find('.pb-nt-ticket').each(function () {
            const block = $(this);
            if (block.data('pbPrefilled')) return;
            const leg = legs[Number(block.attr('data-leg')) || 0];
            const sector = leg ? String(leg.from_code || '').toUpperCase() + '-' + String(leg.to_code || '').toUpperCase() : route?.sector;
            const t = old.find(o => sector && o.sector === sector) || (old.length === 1 ? old[0] : null);
            if (!t) return;
            block.data('pbPrefilled', true);
            if (t.vendor_id) block.find('.pb-nt-vendor').val(String(t.vendor_id)).trigger('change');
            else if (t.portal_id) block.find('.pb-nt-portal').val(String(t.portal_id)).trigger('change');
            if (t.trip_type && block.find('.pb-nt-trip option[value="' + t.trip_type + '"]').length) block.find('.pb-nt-trip').val(t.trip_type).trigger('change');
            if (Number(t.amount) > 0) block.find('.pb-nt-amount').val(Number(t.amount).toFixed(2)).trigger('input');
            block.find('.pb-nt-notes').filter(function () { return !this.value; }).val('Replaces ' + (t.no || 'refunded ticket') + ' (refunded)');
        });
    }

    /**
     * Every figure behind Cost · Charge · Profit, line by line (owner,
     * 2026-09-30: "show all the cost breakdown there"). Live parts (tickets,
     * services, agent price) come from the card's own fields; the parts from
     * his moves between flights come from the server (penalty_parts), so the
     * card and the posted journals read the same numbers.
     */
    function pbRenderCostBreakdown(node, t) {
        const esc = v => $('<span>').text(v == null ? '' : String(v)).html();
        const parts = node.data('penParts') || {};
        const line = (label, v, cls) => '<div style="display:flex;justify-content:space-between;gap:10px;' + (cls === 'sub' ? 'color:#64748b;padding-left:12px;' : '') + '"><span>' + label + '</span><span' + (cls === 'red' ? ' style="color:#dc2626;"' : '') + '>' + money(v) + '</span></div>';
        const cost = [], charge = [];

        const tickets = (node.data('pbTickets') || []).filter(x => x.status !== 'cancelled');
        const ticketTotal = num(node.find('.pb-ticket-cost').val());
        if (tickets.length) {
            cost.push(line('Tickets', ticketTotal));
            tickets.forEach(x => cost.push(line(esc(x.ticket_no || ('#' + x.id)) + (x.route ? ' <small>' + esc(x.route) + '</small>' : ''), x.amount, 'sub')));
        } else if (ticketTotal) cost.push(line('Tickets', ticketTotal));
        [['.pb-manpower', 'Manpower'], ['.pb-boarding', 'Boarding training'], ['.pb-immigration', 'Immigration training'],
         ['.pb-other-cost', node.find('.pb-other-title').val() || 'Other service']].forEach(([sel, label]) => {
            const v = num(node.find(sel).val());
            if (v) cost.push(line(esc(label), v));
        });
        if (num(parts.reissue_fees)) cost.push(line('Reissue fees (owed to vendor / portal)', parts.reissue_fees, 'red'));
        if (num(parts.penalties_kept)) cost.push(line('Penalties kept by the airline', parts.penalties_kept, 'red'));
        if (num(parts.our_charges)) cost.push(line('Change charges we bear', parts.our_charges, 'red'));
        if (num(parts.legacy_fare)) cost.push(line('Fare difference (older same-PNR re-issues)', parts.legacy_fare, 'red'));

        charge.push(line('Package (agent price)', num(node.find('.pb-agent-price').val())));
        if (num(parts.charges_billed)) charge.push(line('Change / reissue charges billed to agent', parts.charges_billed, 'red'));
        if (num(parts.penalties_billed)) charge.push(line('Penalties billed to agent', parts.penalties_billed, 'red'));
        if (num(parts.fare_billed)) charge.push(line('Fare difference billed to agent', parts.fare_billed, 'red'));
        if (num(t.fd)) charge.push(line('Fare difference billed to agent <small>(on link)</small>', t.fd, 'red'));

        const bagCost = pbBaggageTotal(node, 'extra_baggage_cost'), bagCharge = pbBaggageTotal(node, 'extra_baggage_agent_charge');
        if (bagCost) cost.push(line('Extra baggage', bagCost, 'red'));
        if (bagCharge) charge.push(line('Extra baggage', bagCharge, 'red'));
        const profit = t.charge - t.cost;
        const box = node.find('.pb-cost-breakdown').length ? node.find('.pb-cost-breakdown')
            : $('<div class="pb-cost-breakdown" style="margin-top:8px;border:1px solid #e2e8f0;background:#fff;border-radius:8px;padding:8px 11px;font-size:11.5px;line-height:1.7;display:grid;grid-template-columns:1fr 1fr;gap:4px 18px;"></div>').insertAfter(node.find('.pb-money'));
        const head = (label, v, color) => '<div style="display:flex;justify-content:space-between;font-weight:700;border-top:1px solid #e2e8f0;margin-top:2px;padding-top:2px;color:' + color + ';"><span>' + label + '</span><span>' + money(v) + '</span></div>';
        box.html(
            '<div><div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:#94a3b8;">Cost</div>' + cost.join('') + head('Cost', t.cost, '#0f172a') + '</div>'
            + '<div><div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:#94a3b8;">Charge</div>' + charge.join('') + head('Charge', t.charge, '#0f172a')
            + head('Profit = Charge − Cost', profit, profit < 0 ? '#dc2626' : '#0f766e') + '</div>');
    }

    /**
     * The refund's money on the card (owner, 2026-09-30): the penalty kept
     * (as posted, by side) and the fare difference — old fare → new fare —
     * with whose side bears it: the agent (his ledger, Charge goes up) or us
     * (Company Ledger, profit goes down).
     */
    function pbRenderReissueMoney(node) {
        node.find('.pb-reissue-box').remove();
        const rm = node.data('pbReissue');
        if (!rm) return;
        const draft = pbReplacementFare(node, true);
        const diff = rm.settled ? num(rm.fare_difference) : draft;
        const row = node.attr('data-row');
        const name = f => 'passengers[' + row + '][' + f + ']';
        const split = rm.agent_fare_amount != null;
        const bearer = split ? 'split' : (rm.fare_bearer || '');
        const side = rm.settled
            ? '<span>Posted: agent ' + money(rm.fare_bearer === 'agent' ? (rm.agent_fare_amount ?? diff) : 0) + ' / we bear ' + money(diff - (rm.fare_bearer === 'agent' ? (rm.agent_fare_amount ?? diff) : 0)) + '</span>'
            : '<select class="pb-input pb-fare-side cfr-sw-src" style="width:100%;height:36px"><option value="">Choose who bears it</option><option value="agent">Agent pays</option><option value="company">We bear it</option>'
                + (PB_SPLIT_FARE ? '<option value="split">Split</option>' : '') + '</select>';
        const box = $('<div class="pb-reissue-box" style="margin-top:8px;border:1px solid #fecaca;background:#fff7f7;border-radius:8px;padding:10px;font-size:12px;display:grid;gap:8px;min-width:0;"></div>').html(
            '<div>Refund penalty: <b style="color:#dc2626">' + money(num(rm.penalty_agent) + num(rm.penalty_ours)) + '</b> <small>(posted: agent ' + money(rm.penalty_agent) + ' / us ' + money(rm.penalty_ours) + ')</small></div>'
            + '<div>Fare difference <b style="color:#dc2626" class="pb-fare-diff">' + money(diff) + '</b><div style="font-size:11px;color:#64748b">Old ' + money(rm.old_cost) + ' to new ' + money(num(rm.old_cost) + diff) + '</div></div>'
            + side
            + (rm.settled ? '' : '<input type="hidden" class="pb-fare-bearer-value" name="' + name('rebook_fare_bearer') + '"><input type="hidden" class="pb-fare-mode-value" name="' + name('rebook_fare_mode') + '">'
                + '<div class="pb-fare-split-fields" style="display:none"><label class="pb-lbl">Agent bears</label><input type="number" min="0" step="0.01" class="pb-input pb-fare-agent" style="width:100%;box-sizing:border-box;color:#dc2626" name="' + name('rebook_agent_fare_amount') + '"><div class="pb-fare-ours" style="margin-top:5px;color:#b45309"></div></div>'
                + '<small style="color:#64748b">Posted when the flight is saved. The difference is already included in the new ticket cost.</small>'));
        box.insertAfter(node.find('.pb-money'));
        box.find('.pb-fare-side').val(bearer);
        box.find('.pb-fare-agent').val(rm.agent_fare_amount ?? '');
        const paint = () => {
            const value = box.find('.pb-fare-side').val();
            box.find('.pb-fare-bearer-value').val(value === 'split' ? 'agent' : value);
            box.find('.pb-fare-mode-value').val(value);
            box.find('.pb-fare-split-fields').toggle(value === 'split');
            box.find('.pb-fare-agent').prop('disabled', value !== 'split').attr('max', diff);
            box.find('.pb-fare-ours').text('We bear ' + money(Math.max(0, diff - num(box.find('.pb-fare-agent').val()))));
        };
        box.find('.pb-fare-side, .pb-fare-agent').on('input change', function () {
            rm.fare_bearer = box.find('.pb-fare-side').val() === 'company' ? 'company' : (box.find('.pb-fare-side').val() ? 'agent' : '');
            rm.agent_fare_amount = box.find('.pb-fare-side').val() === 'split' ? box.find('.pb-fare-agent').val() : null;
            node.data('pbReissue', rm);
            paint();
            pbCardTotals(node);
        });
        paint();
        return { diff, bearer, settled: !!rm.settled };
    }

    // Include the ticket being typed in the preview; saved totals use linked tickets only.
    function pbReplacementFare(node, includeDraft) {
        const rm = node.data('pbReissue');
        if (!rm) return 0;
        const sectors = (rm.old_tickets || []).map(o => o.sector).filter(Boolean);
        const sectorOf = t => { const l = (t.legs || [])[0]; return l ? String(l.from_location || '').toUpperCase() + '-' + String(l.to_location || '').toUpperCase() : null; };
        let cost = (node.data('pbTickets') || []).filter(t => t.status !== 'cancelled'
            && !(rm.old_tickets || []).some(o => String(o.id) === String(t.id))
            && (!sectors.length || sectors.includes(sectorOf(t)))).reduce((sum, t) => sum + num(t.amount), 0);
        if (includeDraft) node.find('.pb-nt-ticket:visible').each(function () {
            const block = $(this);
            if (block.find('.pb-nt-include').length && !block.find('.pb-nt-include').prop('checked')) return;
            const route = PB_ROUTES.find(t => String(t.id) === String(node.find('.pb-nt-route').val()));
            const legs = route?.legs || [];
            const leg = legs[Number(block.attr('data-leg')) || 0];
            const sector = leg ? String(leg.from_code || '').toUpperCase() + '-' + String(leg.to_code || '').toUpperCase() : route?.sector;
            if (sectors.length && !sectors.includes(sector)) return;
            cost += num(block.find('.pb-nt-amount').val());
        });
        return Math.max(0, Math.round((cost - num(rm.old_cost)) * 100) / 100);
    }

    /**
     * Keep the segment cost on the ticket cost (owner, 2026-09-22).
     *
     * Both are seeded from the same figure when a route is picked — the via
     * leg's own price out of Ticketing — so they start equal and then drifted
     * apart the moment someone corrected the ticket cost, leaving the card
     * claiming a sector price the ticket was not bought at.
     *
     * Only ever with ONE segment on the card, which is the case this can be
     * right about: that segment IS the ticket, so it is the same money under
     * two labels. With several segments the split across them is a real
     * decision and guessing one would be worse than leaving them be. A
     * segment someone typed is left alone too.
     */
    function pbSyncSegmentCost(block) {
        const legs = block.find('.pb-nt-leg');
        if (legs.length !== 1) return;
        const cost = legs.find('.pb-leg-cost');
        if (cost.attr('data-edited') === '1') return;
        const amount = block.find('.pb-nt-amount').val();
        cost.val(amount === '' ? '' : num(amount));
    }

    // Same as Ticket Entry: % and ৳ fill each other, base + taxes − commission = cost.
    $(document).on('input', '.pb-nt-fare-in, .pb-nt-tax, .pb-nt-compct, .pb-nt-comamt, .pb-nt-taxcodes', function () {
        const b = $(this).closest('.pb-nt-ticket');
        const n = v => parseFloat(v) || 0;
        if ($(this).hasClass('pb-nt-taxcodes')) {
            let sum = 0, m; const re = /([A-Z0-9]{2})\s*[:=]?\s*(\d[\d,]*(?:\.\d+)?)/gi;
            while ((m = re.exec(this.value)) !== null) sum += n(m[2].replace(/,/g, ''));
            if (!sum) return;
            b.find('.pb-nt-tax').val(sum.toFixed(2));
        }
        const fare = n(b.find('.pb-nt-fare-in').val()), tax = n(b.find('.pb-nt-tax').val());
        const pct = b.find('.pb-nt-compct'), com = b.find('.pb-nt-comamt');
        if (this === com[0]) pct.val(com.val() !== '' && fare ? String(+(n(com.val()) / fare * 100).toFixed(4)) : (com.val() === '' ? '' : pct.val()));
        else if (n(pct.val())) com.val((fare * n(pct.val()) / 100).toFixed(2));
        else if (this === pct[0]) com.val('');
        else if (com.val() !== '' && fare) pct.val(String(+(n(com.val()) / fare * 100).toFixed(4)));
        pct.attr('placeholder', !fare && com.val() !== '' ? 'needs base fare' : '0');
        if (fare || tax) b.find('.pb-nt-amount').val((fare + tax - n(com.val())).toFixed(2)).trigger('input');
    });

    function pbLegRow(v) {
        const row = $('<div class="pb-nt-leg pb-grid-2"></div>');
        [['From', 'text', 'pb-leg-from', v.from], ['To', 'text', 'pb-leg-to', v.to],
         ['Travel date', 'text', 'pb-leg-date', v.date], ['Segment cost', 'number', 'pb-leg-cost', v.cost]].forEach(function (field) {
            const input = $('<input class="pb-input">').attr('type', field[1]).addClass(field[2]).val(field[3] == null ? '' : field[3]);
            if (field[1] === 'number') input.attr({ min: '0', step: '0.01' });
            if (field[1] === 'text') input.attr('maxlength', '255');
            if (field[2] === 'pb-leg-date') input.attr('data-dt', '');   // date + optional time (partials.datetime-picker)
            row.append($('<label class="pb-lbl"></label>').append($('<span class="pb-field-title"></span>').text(field[0]), input));
        });
        row.append('<button type="button" class="pb-link-btn pb-leg-remove">Remove leg</button>');
        return row;
    }
    // The route the PNR is bought on: the picked leg's own route for a
    // chain, the route itself when direct.
    function pbTicketRouteId(node) {
        return node.find('.pb-nt-route').val();
    }
    // The sector the PNR is for, sent as its (single) leg so the tracker can
    // tell which slot it fills. A leg someone typed by hand wins.
    function pbLegPayload(block) {
        return block.find('.pb-nt-leg').map(function () {
            const leg = $(this);
            return { from_location: leg.find('.pb-leg-from').val(), to_location: leg.find('.pb-leg-to').val(),
                     travel_date: leg.find('.pb-leg-date').val() || null, cost: leg.find('.pb-leg-cost').val() || null };
        }).get();
    }

    /* ── Step 1 ⇄ cards ───────────────────────────────────────────── */

    // Step 1's agent list = its own picks ∪ every agent on a card. Order is
    // kept, so the default (first) never changes underneath the user unless
    // the batch had no agent at all.
    function pbSyncFlightAgents(prefix) {
        const list = $('#' + prefix + '_agent_ids');
        if (!list.length) return;
        const chosen = (list.val() || []).map(String);
        pbContainer(prefix).find('.pb-agent').each(function () {
            const id = String($(this).val() || '');
            if (id && !chosen.includes(id)) chosen.push(id);
        });
        const current = (list.val() || []).map(String);
        if (chosen.length !== current.length || chosen.some((id, i) => id !== current[i])) {
            list.val(chosen).trigger('change');   // runs the .pb-agent-select handler above
        }
    }

    // Vendor OR portal, never both: picking one locks the other until it is
    // cleared — the same rule the card's own new-ticket form applies.
    function pbApplyTicketSourceRule(prefix) {
        const vendor = $('#' + prefix + '_vendor_id'), portal = $('#' + prefix + '_portal_id');
        if (!vendor.length || !portal.length) return;
        const v = Boolean(vendor.val()), p = Boolean(portal.val());
        if (v && p) portal.val('').trigger('change.select2');
        portal.prop('disabled', v).trigger('change.select2');
        vendor.prop('disabled', p && !v).trigger('change.select2');
    }
    window.pbApplyTicketSourceRule = pbApplyTicketSourceRule;

    // Copy step 1's vendor or portal into a card's new-ticket form when that
    // form has no seller yet (or, on a step-1 change, still carries the old
    // step-1 value). A seller someone chose by hand on the card is kept.
    function pbSeedTicketSource(node, fromChange) {
        const prefix = pbPrefixOf(node);
        const vendor = $('#' + prefix + '_vendor_id').val() || '';
        const portal = $('#' + prefix + '_portal_id').val() || '';
        node.find('.pb-nt-ticket').each(function () {
            const ntVendor = $(this).find('.pb-nt-vendor'), ntPortal = $(this).find('.pb-nt-portal');
            const untouched = !ntVendor.val() && !ntPortal.val()
                || (fromChange && ntVendor.data('pbSeeded') === (ntVendor.val() || '') && ntPortal.data('pbSeeded') === (ntPortal.val() || ''));
            if (!untouched) return;
            ntVendor.data('pbSeeded', vendor).val(vendor).trigger('change');
            ntPortal.data('pbSeeded', portal).val(portal).trigger('change');
        });
    }

    /* ── Ticket linking ───────────────────────────────────────────── */

    /**
     * Tickets of this passenger that exist in Ticket Orders but are not
     * purchased yet — holds, drafts, pending. They are not linked to the
     * flight (nothing to link: no PNR, no cost); ticketSlots() finds them by
     * passenger + route + leg, so the tracker shows "Ticket 2 on hold".
     */
    function pbRenderHolds(node, holds) {
        node.data('pbHolds', holds);
        const wrap = node.find('.pb-holds').empty();
        (holds || []).forEach(function (h) {
            const st = h.status === 'booked' ? 'On hold' : (h.status || 'draft');
            const meta = [h.leg_label, h.sector || h.route, h.expires_at ? 'expires ' + h.expires_at : null, h.amount ? moneyShort(h.amount) : null].filter(Boolean).join(' · ');
            wrap.append(
                $('<div class="pb-hold"></div>').append(
                    $('<span class="pb-hold-st"></span>').addClass('is-' + (h.status || 'draft')).text(st),
                    $('<span></span>').text(h.reference || ''),
                    $('<span class="pb-hold-meta"></span>').text(meta),
                    h.url ? $('<a target="_blank" rel="noopener"></a>').attr('href', h.url).text('Open') : null
                )
            );
        });
    }

    /** Re-Issue / Refund adjustments recorded against ONE ticket, oldest first — read from the cached flight changes. */
    function pbTicketAdjustments(node, ticketId) {
        const all = (node && node.data('pbFlightChanges')) || [];
        return all.filter(c => String(c.ticket_purchase_id) === String(ticketId)
            && (c.kind === 'ticket_reissue' || c.kind === 'ticket_refund'));
    }

    function pbLinkedRow(t, passengerId, legNumber, node) {
        const row = $('<div class="pb-pnr"></div>').append(
            $('<span class="pb-pnr-no"></span>').text(t.ticket_no || ('#' + t.id)),
            pbChip(t.status),
            $('<span class="pb-pnr-meta"></span>').text(t.route || ''),
            $('<span class="pb-pnr-amt"></span>').text(moneyShort(t.amount))
        );
        // Re-Issue (same PNR, penalty / fare difference / new date) and Refund
        // (ticket cancelled, airline keeps a loss) — two buttons, never one
        // dropdown, so a wrong pick can't cancel a live ticket. Only once the
        // passenger is saved and the server has the route.
        if (passengerId && t.status !== 'cancelled' && typeof cfpTicketAdjustmentUrlTpl !== 'undefined' && cfpTicketAdjustmentUrlTpl) {
            const tDate = (t.legs && t.legs[0] && t.legs[0].travel_date) ? String(t.legs[0].travel_date).slice(0, 10) : '';
            row.append($('<button type="button" class="pb-unlink" style="color:#0f766e;">Re-Issue</button>')
                .on('click', function () { cfpAdjustTicket(t.id, passengerId, node, Number(t.amount) || 0, 'reissue', tDate, t.ticket_no); }));
            row.append($('<button type="button" class="pb-unlink" style="color:#dc2626;">Refund</button>')
                .on('click', function () { cfpAdjustTicket(t.id, passengerId, node, Number(t.amount) || 0, 'refund', tDate, t.ticket_no); }));
        }
        // A genuinely NEW PNR from the airline: unlink this one and issue
        // the new ticket for this leg only.
        row.append($('<button type="button" class="pb-unlink" style="color:#b45309;">New PNR</button>')
            .on('click', function () { pbReissueLeg($(this).closest('.pb-card'), t.id, legNumber || null); }));
        row.append($('<button type="button" class="pb-unlink">Unlink</button>').attr('data-tid', t.id));

        const wrap = $('<div></div>').append(row);
        const adjustments = pbTicketAdjustments(node, t.id);
        if (adjustments.length) {
            const reissues = adjustments.filter(c => c.kind === 'ticket_reissue');
            const refunds = adjustments.filter(c => c.kind === 'ticket_refund');
            const addTotal = reissues.reduce((s, c) => s + (Number(c.charge) || 0), 0);
            const lossTotal = refunds.reduce((s, c) => s + (Number(c.airline_penalty) || 0), 0);
            const parts = [];
            if (addTotal > 0) parts.push('+' + moneyShort(addTotal) + ' reissue');
            if (lossTotal > 0) parts.push('-' + moneyShort(lossTotal) + ' refund loss');
            const summaryId = 'pbAdj' + t.id + '_' + Math.random().toString(36).slice(2, 7);
            const summary = $('<div style="display:flex;align-items:center;gap:8px;font-size:11px;color:#92400e;background:#fffbeb;border:1px solid #fde68a;border-radius:6px;padding:4px 8px;margin-top:2px;"></div>').append(
                $('<span></span>').html('<i class="fas fa-rotate"></i> ' + adjustments.length + ' adjustment' + (adjustments.length > 1 ? 's' : '') + (parts.length ? ' · ' + parts.join(' · ') : '')),
                $('<button type="button" style="margin-left:auto;color:#92400e;text-decoration:underline;font-size:11px;"></button>').text('Details').on('click', function () { $('#' + summaryId).toggle(); })
            );
            const detail = $('<div id="' + summaryId + '" style="display:none;margin-top:4px;flex-direction:column;gap:3px;"></div>');
            adjustments.slice().reverse().forEach(function (c) {
                const isAdd = c.kind === 'ticket_reissue';
                const amount = isAdd ? c.charge : c.airline_penalty;
                const who = isAdd ? c.bearer_label : c.fault_label;
                detail.append(
                    $('<div style="font-size:10.5px;color:#78716c;padding-left:14px;border-left:2px solid #fde68a;"></div>').html(
                        (isAdd ? 'Reissue' : 'Refund loss') + ' — <span style="color:#dc2626;">' + moneyShort(amount) + '</span>' +
                        (who ? ' · ' + $('<span>').text(who).html() : '') +
                        (c.at ? ' · ' + $('<span>').text(c.at).html() : '') +
                        (c.note ? '<br>' + $('<span>').text(c.note).html() : '')
                    )
                );
            });
            wrap.append(summary, detail);
        }
        return wrap;
    }

    function pbChip(status) {
        const known = ['confirm', 'pending', 'draft', 'cancelled'];
        const cls = known.indexOf(status) > -1 ? status : 'draft';
        return '<span class="pb-chip pb-chip-' + cls + '">' + (status || 'draft') + '</span>';
    }

    /** Linked PNRs are the source of the ticket cost — never typed by hand. */
    const PB_BAG_FIELDS = ['free_baggage_kg', 'extra_baggage_kg', 'extra_baggage_cost', 'extra_baggage_agent_charge', 'extra_baggage_bearer'];
    const PB_BAG_READY = @json(\App\Services\TicketBaggage::ready());
    function pbLinkedBaggage(ticket, row) {
        if (!PB_BAG_READY) return $();
        const box = $('<details class="pb-linked-baggage" style="padding:8px 10px;border-top:1px solid #e2e8f0"><summary style="cursor:pointer;font-size:11px;color:#7c3aed">Baggage</summary></details>').data('ticketId', String(ticket.id));
        const fields = $('<div class="pb-zr c-fare" style="margin-top:10px"></div>').appendTo(box);
        [['free_baggage_kg', 'Free KG'], ['extra_baggage_kg', 'Extra KG'], ['extra_baggage_cost', 'Cost'], ['extra_baggage_agent_charge', 'Agent charge'], ['extra_baggage_bearer', 'Who pays']].forEach(([field, label]) => {
            const cell = $('<div></div>').appendTo(fields);
            $('<label class="pb-lbl"></label>').text(label).appendTo(cell);
            const input = field === 'extra_baggage_bearer'
                ? $('<select class="pb-select"><option value="">Choose</option><option value="customer">Agent pays</option><option value="company">We bear it</option></select>')
                : $('<input type="number" min="0" step="0.01" class="pb-input">');
            input.addClass('pb-linked-bag-field').attr('data-field', field)
                .attr('name', 'passengers[' + row + '][ticket_baggage][' + ticket.id + '][' + field + ']')
                .val(ticket[field] ?? '').appendTo(cell);
            if (field === 'extra_baggage_agent_charge') input.prop('readonly', ticket.extra_baggage_bearer === 'company');
        });
        $('<div class="pb-linked-bag-profit" style="text-align:right;font-size:11px"></div>')
            .text('Baggage profit ' + money((ticket.extra_baggage_bearer === 'customer' ? num(ticket.extra_baggage_agent_charge) : 0) - num(ticket.extra_baggage_cost))).appendTo(box);
        return box;
    }
    function pbBaggageTotal(node, field) {
        return (node.data('pbTickets') || []).reduce((sum, ticket) => sum + (field === 'extra_baggage_agent_charge' && ticket.extra_baggage_bearer !== 'customer' ? 0 : num(ticket[field])), 0);
    }
    function pbNewBaggage(block) {
        const company = block.find('.pb-nt-bag-bearer').val() === 'company';
        const charge = block.find('.pb-nt-bag-charge');
        if (company) charge.val('0');
        charge.prop('readonly', company);
        const profit = (company ? 0 : num(charge.val())) - num(block.find('.pb-nt-bag-cost').val());
        block.find('.pb-nt-bag-profit').val(profit.toFixed(2)).css('color', profit < 0 ? '#dc2626' : '#0f766e');
    }
    function pbRenderLinked(node, tickets) {
        node.data('pbTickets', tickets);
        setTimeout(() => pbRenderReissueMoney(node), 0);
        const wrap = node.find('.pb-linked').empty();
        node.find('.pb-ticket-hidden').remove();

        // Nothing is drawn for "no ticket yet" — .pb-ticket-hint below the list
        // already says what to do about it, and two notices saying the same
        // thing read as an error rather than an empty state.

        const row = node.attr('data-row');
        // A via route has one slot per ticket: "Ticket 1 of 2 · DAC → IST" with
        // the PNR that covers it (by leg number, else by sector), or an empty
        // slot saying so — the same reading as the tracker's ticketSlots().
        const route = PB_ROUTES.find(t => String(t.id) === String($('#' + pbPrefixOf(node) + '_ticket_id').val() || node.find('.pb-nt-route').val()));
        const legs = (route && route.legs) || [];
        const passengerId = node.data('pbPassengerId') || null;
        if (legs.length > 1) {
            const used = new Set();
            // its leg number when it was issued as "Ticket n of m", else the journey it flies (transits allowed)
            const legOf = (t, i, leg) => (t.leg_sequence && Number(t.leg_sequence) === i + 1) || (!t.leg_sequence && pbCoversLeg(t, leg));
            legs.forEach(function (leg, i) {
                // A cancelled PNR (e.g. the old ticket of a re-booked passenger,
                // cancelled at 0) covers no leg — same rule as ticketSlots().
                const t = tickets.find(x => x.status !== 'cancelled' && !used.has(x.id) && legOf(x, i, leg));
                if (t) used.add(t.id);
                wrap.append($('<div class="pb-slot-hd"></div>').addClass(t ? 'is-done' : '').html(
                    '<i class="fas ' + (t ? 'fa-circle-check' : 'fa-circle') + '"></i> Ticket ' + (i + 1) + ' of ' + legs.length + ' · ' + $('<span>').text(leg.sector || '').html()
                    + (t ? '' : ' <em>— no ticket yet: link one below or issue it</em>')));
                if (t) wrap.append(pbLinkedRow(t, passengerId, i + 1, node));
            });
            tickets.filter(t => !used.has(t.id)).forEach(t => wrap.append(pbLinkedRow(t, passengerId, t.leg_sequence || null, node)));
        }
        tickets.forEach(function (t) {
            if (legs.length <= 1) wrap.append(pbLinkedRow(t, passengerId, null, node));   // via: drawn under its slot above
            node.append($('<input type="hidden" class="pb-ticket-hidden">')
                .attr('name', 'passengers[' + row + '][ticket_purchase_ids][]')
                .val(t.id));
            // Tidy ticket summary (owner, 2026-10-08): route header, seller, a money strip
            // and the itinerary as a timeline — instead of a two-column label list.
            const esc = v => $('<span>').text(v == null || v === '' ? '—' : String(v)).html();
            const date = value => value ? String(value).slice(0, 10) : '—';
            const money = value => (t.currency === 'BDT' || !t.currency ? '৳' : t.currency + ' ') + num(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const due = num(t.due_amount), ps = String(t.payment_status || 'due').toLowerCase();
            const flag = cc => cc ? '<img class="pbs-flag" src="' + PB_FLAGS + '/' + cc + '.png" alt="" onerror="this.remove()">' : '';
            const logo = name => {
                // the dashboard's own airline marks when loaded; elsewhere the logo file named after the airline
                const slug = !name ? null : (typeof window.airlineBrand === 'function' && (window.airlineBrand(name) || {}).slug)
                    || String(name).toLowerCase().replace(/\(.*?\)/g, '').replace(/(airlines?|airways)/g, '').trim().replace(/[^a-z]+/g, '-').replace(/^-|-$/g, '');
                const code = esc(((String(name).match(/\(([A-Z0-9]{2})\)/) || [])[1]) || String(name).trim().slice(0, 2).toUpperCase());
                return slug ? '<img class="pbs-logo" src="' + PB_AIRLOGOS + '/' + slug + '.png" alt="" title="' + esc(name) + '" onerror="this.outerHTML=\'&lt;span class=&quot;pbs-logo pbs-logo-x&quot;&gt;' + code + '&lt;/span&gt;\'">'
                    : (name ? '<span class="pbs-logo pbs-logo-x" title="' + esc(name) + '">' + esc(String(name).replace(/\(.*\)/, '').trim().slice(0, 2).toUpperCase()) + '</span>' : '');
            };
            const legsHtml = (t.legs || []).map(function (leg) {
                const air = leg.airline || t.carrier;
                return '<li>' + (logo(air) || '<span class="pbs-logo"></span>')
                    + '<span class="pbs-ap">' + flag(leg.from_cc) + esc(leg.from_location) + '</span>'
                    + '<span class="pbs-line"><i class="fas fa-plane"></i></span>'
                    + '<span class="pbs-ap">' + flag(leg.to_cc) + esc(leg.to_location) + '</span>'
                    + '<span class="pbs-lmeta">' + (air ? '<b>' + esc(String(air).replace(/\s*\(.*\)\s*$/, '')) + '</b>' : '') + esc(date(leg.travel_date)) + (num(leg.cost) ? ' · ' + money(leg.cost) : '') + '</span></li>';
            }).join('');
            const missing = t.status === 'cancelled' ? [] : [
                !t.name_ticket_no && 'E-ticket no.',
                !(t.carrier || (t.legs || []).some(l => l.airline)) && 'Airline',
                !t.flight_no && 'Flight no.',
                !(t.departure_at || (t.legs || []).some(l => l.travel_date)) && 'Departure',
            ].filter(Boolean);
            const details = $('<div class="pbs-card' + (t.status === 'cancelled' ? ' is-cancelled' : '') + '">'
                + '<div class="pbs-top"><div class="pbs-route"><i class="fas fa-route"></i> ' + esc(t.route) + '</div>'
                + '<div class="pbs-tags"><span>' + esc(t.ticket_class) + '</span><span>' + esc(t.trip_type) + '</span><span><i class="far fa-calendar"></i> ' + esc(date(t.purchase_date)) + '</span></div></div>'
                + '<div class="pbs-ids"><span title="PNR"><i class="fas fa-hashtag"></i>' + esc(t.ticket_no) + '</span>'
                + (t.name_ticket_no ? '<span title="E-ticket number"><i class="fas fa-ticket"></i>' + esc(t.name_ticket_no) + '</span>' : '')
                + (t.carrier ? '<span title="Validating carrier">' + logo(t.carrier) + esc(String(t.carrier).replace(/\s*\(.*\)\s*$/, '')) + '</span>' : '') + '</div>'
                // Contract Flight (owner, 2026-10-17): only passenger, ticket PNR, route, vendor and cost are
                // required — whatever else the ticket lacks is listed here, to be completed on the ticket
                + (missing.length ? '<div class="pbs-missing" title="Optional for a contract flight — complete it on the ticket in Ticket Orders"><i class="fas fa-circle-exclamation"></i> Not on file yet: ' + missing.map(esc).join(' · ') + '</div>' : '')
                + '<div class="pbs-seller"><i class="fas fa-store"></i> ' + esc(t.vendor || t.portal) + (t.vendor && t.portal ? ' · ' + esc(t.portal) : '') + (t.vendor_id ? '' : (t.portal ? ' <em>portal</em>' : '')) + '</div>'
                + '<div class="pbs-money">'
                + '<div><small>Ticket cost</small><b>' + money(t.amount) + '</b></div>'
                + '<div><small>Paid</small><b class="g">' + money(t.paid_amount) + '</b></div>'
                + '<div><small>Due</small><b class="' + (due > 0 ? 'r' : 'g') + '">' + money(due) + '</b></div>'
                + '<div><small>Status</small><span class="pbs-pill ' + ps + '">' + esc(ps) + '</span></div></div>'
                + '<div class="pbs-meta"><span><i class="fas fa-building-columns"></i> ' + esc(t.bank) + '</span><span><i class="far fa-clock"></i> Due by ' + esc(date(t.cost_due_date)) + '</span></div>'
                + (legsHtml ? '<ul class="pbs-legs">' + legsHtml + '</ul>' : '')
                + '</div>');
            details.append(pbLinkedBaggage(t, row));
            wrap.append(details);
        });

        // Same rule as ContractFlightPassenger::getTotalTravelCostAttribute():
        // a cancelled PNR stays on the card (its chip says so) but costs nothing.
        node.find('.pb-ticket-cost').val(tickets.reduce((s, t) => s + (t.status === 'cancelled' ? 0 : num(t.amount)), 0).toFixed(2));
        pbCardTotals(node);
    }

    function pbLoadHolderTickets(node, query) {
        const holderId = node.find('.pb-holder').val();
        const hint = node.find('.pb-ticket-hint');
        const results = node.find('.pb-results').empty();

        if (!holderId) {
            hint.text('Choose a passport holder to see the tickets already on file for them.');
            return;
        }

        hint.text('Looking up tickets on file…');
        $.get(PB_URLS.holderTickets.replace('__HID__', holderId), { q: query || '' })
            .done(function (response) {
                if (!response.success) return;
                pbRenderHolds(node, response.ticket_bookings || []);
                const linked = (node.data('pbTickets') || []).map(t => String(t.id));
                // A cancelled PNR is history, not something to link onto a slot.
                const available = response.ticket_purchases.filter(t => linked.indexOf(String(t.id)) === -1 && t.status !== 'cancelled');

                if (!available.length) {
                    hint.text(query
                        ? 'No ticket matching “' + query + '” for this passenger.'
                        : 'No unlinked ticket on file for this passenger — issue a new one on the other tab.');
                    return;
                }
                hint.text('Tickets already on file for this passenger — select one to link it.');
                available.forEach(function (t) {
                    results.append(
                        $('<div class="pb-pnr pb-pick"></div>').attr('data-ticket', JSON.stringify(t)).append(
                            $('<span class="pb-pnr-no"></span>').text(t.ticket_no || ('#' + t.id)),
                            pbChip(t.status),
                            $('<span class="pb-pnr-meta"></span>').text(t.route || ''),
                            $('<span class="pb-pnr-amt"></span>').text(moneyShort(t.amount)),
                            '<button type="button" class="pb-link-btn">Link</button>'
                        )
                    );
                });
            })
            .fail(function () { hint.text('Could not load this passenger’s tickets.'); });
    }

    /**
     * Cost paid vs ticket cost → due amount (implied, not posted — the
     * server derives it) + pay status, the same rule the standalone Ticket
     * Purchase screen's own "calculatePaidAmount" applies. Runs on every
     * keystroke so the Pay status select is never stale when Create & link
     * is clicked.
     */
    function pbSyncNewTicketPayStatus(node) {
        const cost = num(node.find('.pb-nt-amount').val());
        let paid = num(node.find('.pb-nt-paid').val());
        if (paid > cost) {
            paid = cost;
            node.find('.pb-nt-paid').val(cost || 0);
        }
        const due = Math.max(0, cost - paid);
        node.find('.pb-nt-pay-status').val(due <= 0 && cost > 0 ? 'paid' : (paid > 0 ? 'partial' : 'due'));
    }

    /**
     * Issue a PNR through the Ticketing screen's own endpoint, then link it.
     * Same fields as the Ticketing (Direct Sale) form's per-passenger row —
     * class, trip, vendor/portal, cost, cost paid, pay status, cost bank and
     * cost due date — so a ticket booked from inside a flight looks exactly
     * like one booked from the Ticketing screen, and appears in that list.
     */
    function pbCreateTicket(node, prefix) {
        const err = node.find('.pb-nt-err').hide();
        const button = node.find('.pb-nt-save').prop('disabled', true);
        const holderId = node.find('.pb-holder').val();
        const ticketId = node.find('.pb-nt-route').val();
        const fail = msg => { err.text(msg).show(); button.prop('disabled', false); };

        if (!holderId) return fail('Choose the passport holder first.');

        const route = PB_ROUTES.find(t => String(t.id) === String(ticketId));
        const via = Boolean(route && (route.legs || []).length > 1);
        const blocks = node.find('.pb-nt-ticket');
        const rows = [];
        const files = [];

        // Ticket status decides what Ticketing writes (owner, 2026-09-21): a
        // Purchased row is a full purchase; anything else is a Ticket Orders
        // record and needs nothing but the person (a hold needs its deadline).
        if (via && blocks.length > 1 && !blocks.filter(function () { return !$(this).find('.pb-nt-include').length || $(this).find('.pb-nt-include').prop('checked'); }).length) {
            return fail('Tick "Issue this leg now" on at least one leg.');
        }
        for (let i = 0; i < blocks.length; i++) {
            const b = blocks.eq(i);
            // Unticked "Issue this leg now": issued later, not in this save.
            if (b.find('.pb-nt-include').length && !b.find('.pb-nt-include').prop('checked')) continue;
            // The block's real leg number — not its position, which is 0 for
            // a single scoped block even when it is leg 2 of 2 (New PNR).
            const legNum = via ? (Number(b.attr('data-leg')) + 1) : null;
            const tag = via ? 'Ticket ' + legNum + ': ' : '';
            const status = b.find('.pb-nt-status').val() || 'purchased';
            const isPurchase = status === 'purchased';
            pbSyncNewTicketPayStatus(b);
            const cost = num(b.find('.pb-nt-amount').val());
            const paid = num(b.find('.pb-nt-paid').val());
            const bankId = b.find('.pb-nt-bank').val() || null;
            const portalId = b.find('.pb-nt-portal').val() || null;

            if (isPurchase && !ticketId) return fail(tag + 'select a ticket route before issuing a ticket.');
            if (isPurchase && !b.find('.pb-nt-no').val()) return fail(tag + 'a PNR / ticket number is required.');
            if (isPurchase && !b.find('.pb-nt-date').val()) return fail(tag + 'a purchase date is required.');
            if (isPurchase && (b.find('.pb-nt-amount').val() === '' || !Number.isFinite(Number(b.find('.pb-nt-amount').val())) || cost < 0)) return fail(tag + 'enter a valid ticket cost.');
            if (!Number.isFinite(Number(b.find('.pb-nt-paid').val() || 0)) || paid < 0) return fail(tag + 'enter a valid cost paid.');
            if (isPurchase && !b.find('.pb-nt-vendor').val() && !portalId) return fail(tag + 'choose a vendor or a portal — the cost has to be owed to someone.');
            if (b.find('.pb-nt-vendor').val() && portalId) return fail(tag + 'choose either a vendor or a portal, not both.');
            if (isPurchase && paid > 0 && !bankId && !portalId) return fail(tag + 'choose a cost bank or a portal to record the payment from.');
            if (status === 'booked' && !b.find('.pb-nt-expires-at').val()) return fail(tag + 'a booked ticket needs the hold deadline.');

            const row = {
                passport_holder_id: holderId,
                ticket_id: ticketId || null,
                leg_sequence: legNum,
                ticket_no: b.find('.pb-nt-no').val() || null,
                name_ticket_no: b.find('.pb-nt-name-no').val() || null,
                cost_amount: b.find('.pb-nt-amount').val() === '' ? null : cost,
                purchase_date: b.find('.pb-nt-date').val() || null,
                vendor_id: b.find('.pb-nt-vendor').val() || null,
                portal_id: portalId,
                purchase_bank_id: bankId,
                trip_type: b.find('.pb-nt-trip').val(),
                // the passenger's own Class (Traveller section) — a ticket is
                // booked in the class the person actually travels
                ticket_class: node.find('.pb-class').val() || 'economy',
                ticket_type: 'air',
                status: status,
                purchase_paid: paid,
                purchase_pay_status: b.find('.pb-nt-pay-status').val(),
                cost_due_date: b.find('.pb-nt-due-date').val() || null,
                legs: pbLegPayload(b),
                // fare build-up + flight detail, same fields Ticket Entry sends (2026-10-08)
                base_fare: b.find('.pb-nt-fare-in').val() || null,
                tax_amount: b.find('.pb-nt-tax').val() || null,
                tax_breakdown: b.find('.pb-nt-taxcodes').val() || null,
                commission_pct: b.find('.pb-nt-compct').val() || null,
                commission_amount: b.find('.pb-nt-comamt').val() || null,
                validating_carrier_id: b.find('.pb-nt-vc').val() || null,
                flight_no: (b.find('.pb-nt-flightno').val() || '').toUpperCase() || null,
                arrival_at: b.find('.pb-nt-arr').val() || null,
                rbd: (b.find('.pb-nt-rbd').val() || '').toUpperCase() || null,
                free_baggage_kg: b.find('.pb-nt-free-bag').val() || null,
                extra_baggage_kg: b.find('.pb-nt-extra-bag').val() || null,
                extra_baggage_cost: b.find('.pb-nt-bag-cost').val() || null,
                extra_baggage_agent_charge: b.find('.pb-nt-bag-charge').val() || null,
                extra_baggage_bearer: b.find('.pb-nt-bag-bearer').val() || null,
                // hold fields (read by Ticketing only when status = booked)
                booking_at: b.find('.pb-nt-booking-at').val() || null,
                departure_at: b.find('.pb-nt-dep').val() || b.find('.pb-nt-departure-at').val() || null,
                return_at: b.find('.pb-nt-return-at').val() || null,
                expires_at: b.find('.pb-nt-expires-at').val() || null,
                notification_recipient_id: b.find('.pb-nt-recipient').val() || null,
                notes: b.find('.pb-nt-notes').val() || null
            };
            b.find('.pb-nt-ch').each(function () { row[$(this).data('key')] = $(this).is(':checked') ? 1 : 0; });
            const bagActive = num(row.extra_baggage_kg) || num(row.extra_baggage_cost) || num(row.extra_baggage_agent_charge);
            if (PB_BAG_FIELDS.filter(field => field !== 'extra_baggage_bearer').some(field => row[field] !== null && (!Number.isFinite(Number(row[field])) || Number(row[field]) < 0))) return fail(tag + 'baggage KG and amounts must be zero or more.');
            if (bagActive && !row.extra_baggage_bearer) return fail(tag + 'choose who pays for extra baggage.');
            if (bagActive && num(row.extra_baggage_kg) <= 0) return fail(tag + 'enter extra baggage KG.');
            if (row.extra_baggage_bearer === 'company' && num(row.extra_baggage_agent_charge) > 0) return fail(tag + 'clear the agent baggage charge when we bear it.');
            rows.push(row);
            const file = b.find('.pb-nt-attach')[0];
            files.push(file && file.files && file.files[0] ? file.files[0] : null);
        }

        // multipart, so attachments travel exactly as they do from Ticket Entry
        const fd = new FormData();
        fd.append('_token', $('meta[name="csrf-token"]').attr('content'));
        fd.append('currency', 'BDT');
        const put = (key, value) => {
            if (value === null || value === undefined) return;
            if (Array.isArray(value)) { value.forEach((v, i) => Object.keys(v).forEach(k => put(key + '[' + i + '][' + k + ']', v[k]))); return; }
            fd.append(key, value);
        };
        rows.forEach((row, i) => {
            Object.keys(row).forEach(k => {
                // The passenger and agent are not linked until the flight save.
                if (row.status === 'purchased' && PB_BAG_FIELDS.includes(k) && k !== 'free_baggage_kg') return;
                put('passengers[' + i + '][' + k + ']', row[k]);
            });
            if (files[i]) fd.append('passengers[' + i + '][attachment]', files[i]);
        });

        $.ajax({ url: PB_URLS.createTicket, method: 'POST', data: fd, processData: false, contentType: false }).done(function (response) {
            if (!response.success) return fail(response.message || 'Could not create the ticket.');

            const purchasedRows = rows.filter(row => row.status === 'purchased');
            (response.ticket_purchases || []).forEach((ticket, i) => {
                PB_BAG_FIELDS.forEach(field => ticket[field] = purchasedRows[i]?.[field] ?? null);
            });
            const tickets = (node.data('pbTickets') || []).concat(response.ticket_purchases || []);
            pbRenderLinked(node, tickets);
            // The PNR the server just allocated may be XYZ (b) — its base is
            // what says this passenger travels on someone else's booking.
            pbCopyServicesFromTwin(node);
            if ((response.ticket_bookings || []).length) {
                pbRenderHolds(node, (node.data('pbHolds') || []).concat(response.ticket_bookings));
            }
            pbRefresh(prefix);
            blocks.each(function () {
                const b = $(this);
                b.find('.pb-nt-no, .pb-nt-name-no, .pb-nt-amount, .pb-nt-due-date, .pb-nt-notes, .pb-nt-departure-at, .pb-nt-return-at, .pb-nt-fare-in, .pb-nt-tax, .pb-nt-compct, .pb-nt-comamt, .pb-nt-taxcodes, .pb-nt-flightno, .pb-nt-dep, .pb-nt-arr, .pb-nt-rbd, .pb-nt-bag, .pb-nt-free-bag, .pb-nt-extra-bag, .pb-nt-bag-cost, .pb-nt-bag-charge, .pb-nt-bag-bearer, .pb-nt-bag-profit').val('');
                b.find('.pb-nt-bag-charge').prop('readonly', false);
                b.find('.pb-nt-vc').val('').trigger('change');
                b.find('.pb-nt-paid').val('0');
                b.find('.pb-nt-legs').empty();
                b.find('.pb-nt-bank').val(null).trigger('change');
                b.find('.pb-nt-pay-status').val('due');
                const file = b.find('.pb-nt-attach')[0]; if (file) file.value = '';
                b.find('.pb-nt-status').val('purchased').trigger('change');
            });
            node.find('.pb-mode-existing').trigger('click');
            pbLoadHolderTickets(node);
            button.prop('disabled', false);
        }).fail(function (xhr) {
            // The PNR is booked through the Ticketing screen's own endpoint,
            // so it needs that screen's permission. Say so — otherwise a 403
            // reads as a mysterious failure of the flight form.
            if (xhr.status === 403) {
                return fail('You do not have permission to create tickets. Ask an admin for the “create ticket direct sale” right, or link a PNR the ticketing team has already issued.');
            }
            fail(xhr.responseJSON?.message || 'Could not create the ticket. Nothing was saved.');
        });
    }

    /* ── Import PDF (2026-10-08) ──────────────────────────────────
     * Ticket Entry's own Import PDF page in an overlay (?embed=cf hides its shell).
     * It reads, matches and saves exactly as it does in Ticketing — purchase, vendor
     * bill, journal, commission. When its save succeeds the page posts the saved
     * tickets back here and they are linked to this passenger card, as Create & link does.
     */
    let pbImportNode = null, pbImportPrefix = null;
    $(document).on('click', '.pb-import-pdf', function () {
        pbImportNode = $(this).closest('.pb-card');
        pbImportPrefix = pbPrefixOf(pbImportNode);
        // Takes over the flight modal's whole body (owner, 2026-10-08): the import's wizard
        // needs full width, and a second modal or a narrow column both failed. Same modal;
        // "Back to passenger" (or a successful save) returns to the card exactly as it was.
        $('.pb-imp-take').remove();
        const holder = pbImportNode.find('.pb-holder option:selected').text().trim();
        const box = $('<div class="pb-imp-take"><div class="pb-imp-hd"><button type="button" class="pb-imp-back"><i class="fas fa-arrow-left"></i> Back to passenger</button><span><i class="fas fa-file-pdf"></i> Import ticket PDF — saved through Ticketing, then linked to <b></b></span></div><iframe></iframe></div>');
        box.find('b').text(holder && !/select/i.test(holder) ? holder : 'this passenger');
        box.find('iframe').attr('src', PB_URLS.importPdf);
        box.find('.pb-imp-back').on('click', () => box.remove());
        const host = $(this).closest('.fcm-body, .modal-body').parent();
        (host.length ? host : $('body')).css('position', host.length ? 'relative' : '').append(box);
    });
    window.addEventListener('message', function (e) {
        if (e.origin !== location.origin || !e.data || e.data.type !== 'tk-ticket-saved') return;
        $('.pb-imp-take').remove();
        const node = pbImportNode; if (!node || !node.length) return;
        const got = e.data.ticket_purchases || [];
        const prefix = pbImportPrefix || pbPrefixOf(node);
        // One PDF can carry several people, and several legs per person (owner, 2026-10-08).
        // Group the saved tickets by passenger: each person's tickets — every leg — go onto
        // THEIR card: the card this was opened from (if it is them, or still empty), else the
        // card already holding that passport holder, else a new card added for them.
        const groups = {};
        got.forEach(t => { const k = String(t.passport_holder_id || ''); (groups[k] = groups[k] || []).push(t); });
        const clicked = String(node.find('.pb-holder').val() || '');
        const order = Object.keys(groups).sort((x, y) => (y === clicked) - (x === clicked));
        let usedClicked = false;
        const placed = [];
        order.forEach(function (hid) {
            const list = groups[hid].sort((x, y) => (x.leg_sequence || 1) - (y.leg_sequence || 1));
            let card = null;
            if (hid && clicked === hid) { card = node; usedClicked = true; }
            if (!card && hid) pbContainer(prefix).find('.pb-card').each(function () { if (!card && String($(this).find('.pb-holder').val() || '') === hid) card = $(this); });
            if (!card && !clicked && !usedClicked) { card = node; usedClicked = true; }
            if (!card) card = pbAddCard(prefix, { agent_id: node.find('.pb-agent').val() || null });
            if (!card || !card.length) return;
            if (hid && String(card.find('.pb-holder').val() || '') !== hid) {
                const sel = card.find('.pb-holder');
                const name = list[0].passenger || ('Passenger #' + hid);
                if (!PB_HOLDERS.some(h => String(h.id) === hid)) PB_HOLDERS.push({ id: Number(hid), text: name });   // made by the import just now
                if (!sel.find('option[value="' + hid + '"]').length) sel.append(new Option(name, hid, false, false));
                sel.val(hid).trigger('change');
            }
            // the route's ticket count follows what was issued (DAC-BAH-IST-ECN on 2 PNRs = 2)
            const legsNeeded = Math.max(...list.map(t => t.leg_sequence || 1));
            const req = card.find('.pb-tickets-required');
            if (req.length && legsNeeded > Number(req.val() || 1) && req.find('option[value="' + legsNeeded + '"]').length) req.val(String(legsNeeded)).trigger('change');
            const already = new Set((card.data('pbTickets') || []).map(t => String(t.id)));
            pbRenderLinked(card, (card.data('pbTickets') || []).concat(list.filter(t => !already.has(String(t.id)))));
            pbCopyServicesFromTwin(card);
            pbLoadHolderTickets(card);
            placed.push((list[0].passenger || 'Passenger') + ' — ' + list.length + ' ticket' + (list.length > 1 ? 's' : ''));
        });
        if (prefix) pbRefresh(prefix);
        if (placed.length > 1 && window.Swal) Swal.fire({ icon: 'success', title: 'Linked to ' + placed.length + ' passengers', html: placed.map(x => $('<div>').text(x).html()).join('<br>'), timer: 3500 });
    });

    /* ── Totals ───────────────────────────────────────────────────── */

    /**
     * Travel cost deliberately excludes reissue (an exception cost recorded
     * for reporting, not part of the margin the trip was sold on) — same rule
     * ContractFlightPassenger::getTotalTravelCostAttribute() applies, so the
     * figure here and the one the server stores never disagree.
     */
    function pbCardTotals(node) {
        let cost = num(node.find('.pb-ticket-cost').val())
            + num(node.find('.pb-manpower').val())
            + num(node.find('.pb-boarding').val())
            + num(node.find('.pb-immigration').val())
            + num(node.find('.pb-other-cost').val());
        const penBilled = Number(node.data('penBilled')) || 0, penBorne = Number(node.data('penBorne')) || 0;
        cost += penBorne;
        // A fare difference not yet posted: preview it where it will land.
        const fd = node.data('pbReissue') ? (function () {
            const rm = node.data('pbReissue');
            if (rm.settled) return 0;   // posted: already inside penBilled
            const sectors = (rm.old_tickets || []).map(o => o.sector).filter(Boolean);
            const sectorOf = t => { const l = (t.legs || [])[0]; return l ? String(l.from_location || '').toUpperCase() + '-' + String(l.to_location || '').toUpperCase() : null; };
            const newCost = (node.data('pbTickets') || []).filter(t => t.status !== 'cancelled' && !(rm.old_tickets || []).some(o => String(o.id) === String(t.id))
                && (!sectors.length || sectors.indexOf(sectorOf(t)) > -1)).reduce((s, t) => s + num(t.amount), 0);
            return rm.fare_bearer === 'agent' ? (rm.agent_fare_amount != null ? num(rm.agent_fare_amount) : Math.max(0, newCost - num(rm.old_cost))) : 0;
        })() : 0;
        cost += pbBaggageTotal(node, 'extra_baggage_cost');
        const charge = num(node.find('.pb-agent-price').val()) + penBilled + fd + pbBaggageTotal(node, 'extra_baggage_agent_charge');
        const profit = charge - cost;

        node.find('.pb-pen-note').remove();
        pbRenderCostBreakdown(node, { cost: cost, charge: charge, fd: fd });

        node.find('.pb-total-cost').text(money(cost));
        node.find('.pb-total-charge').text(money(charge));
        node.find('.pb-total-profit').text(money(profit)).toggleClass('pb-neg', profit < 0);
        // A card nobody has touched yet reported "cost ৳0 · profit ৳0", which
        // reads as a costed passenger worth nothing rather than an empty row.
        node.find('.pb-card-sum').html(node.find('.pb-holder').val()
            ? 'cost ' + moneyShort(cost) + ' · profit <b class="' + (profit < 0 ? 'neg' : '') + '">' + moneyShort(profit) + '</b>'
            : '<i class="pb-sum-empty">nothing filled in yet</i>');
        pbCardTags(node);
        return { cost: cost, charge: charge, profit: profit };
    }

    /**
     * What a collapsed card says about itself. With three to five passengers
     * only the one being worked on stays open, so this line is all that is
     * left of the other four — it has to carry enough to spot the wrong
     * category or a missing ticket without expanding anything.
     */
    function pbCardTags(node) {
        const wrap = node.find('.pb-card-tags').empty();
        const add = (text, cls) => wrap.append($('<span class="pb-tag"></span>').addClass(cls || '').text(text));

        // Nothing chosen yet: one prompt beats five "No …" warnings shouting
        // at someone who has not started the row.
        if (!node.find('.pb-holder').val()) {
            add('Choose a passport holder to begin');
            return;
        }

        const passport = node.data('pbPassport');
        if (passport) add(passport);

        const category = node.find('.pb-category option:selected');
        category.val() ? add(category.text()) : add('No category', 'pb-tag-warn');

        const cls = node.find('.pb-class option:selected').text();
        if (cls && cls.toLowerCase() !== 'economy') add(cls);

        // Only what makes this passenger different from the rest of the batch.
        // Arranging BMET, the card and boarding is the ordinary case, so a
        // chip for each would fire on every card and say nothing; what is
        // worth seeing on a collapsed row is who already holds their papers
        // and who needs an immigration officer.
        if (node.find('.pb-svc-immigration').is(':checked')) add('Immigration training');
        if (!node.find('.pb-svc-boarding').is(':checked')) add('No boarding training');
        if (!node.find('.pb-svc-bmet').is(':checked')) add('Has permit');

        const tickets = node.data('pbTickets') || [];
        const legsNeeded = Math.max(1, parseInt(node.find('.pb-tickets-required').val(), 10) || 1);
        const legsDone = tickets.filter(t => t.status === 'confirm').length;
        if (tickets.length) {
            add(tickets.map(t => t.ticket_no || ('#' + t.id)).join(', '), legsDone >= legsNeeded ? 'pb-tag-ok' : 'pb-tag-warn');
        } else {
            add('No ticket', 'pb-tag-warn');
        }
        if (legsNeeded > 1) add(legsDone + ' / ' + legsNeeded + ' tickets', legsDone >= legsNeeded ? 'pb-tag-ok' : 'pb-tag-warn');

        if (node.find('.pb-agent-price').val() === '') add('No price', 'pb-tag-warn');

        // A card billed to someone other than step 1's agent says so on its
        // header, so a mixed batch reads correctly while collapsed.
        const agentSel = node.find('.pb-agent');
        const flightAgent = $('#' + pbPrefixOf(node) + '_agent_id').val() || '';
        if (agentSel.length && (agentSel.val() || '') !== flightAgent) {
            add(agentSel.val() ? agentSel.find('option:selected').text().trim() : 'No agent', agentSel.val() ? 'pb-tag-agent' : 'pb-tag-warn');
        }
    }

    /**
     * The holder option reads "NAME — PASSPORT — PHONE". Putting all of it in
     * the header truncated mid-passport ("AL MAMUN — A042…"), which is the
     * half worth reading. The name goes in the title; the passport becomes a
     * chip, where it is either shown whole or not at all.
     */
    function pbSetCardIdentity(node, text) {
        const parts = String(text || '').split(' — ');
        node.find('.pb-card-name').text(parts[0] || 'New passenger');
        node.data('pbPassport', parts[1] || '');
        pbCardTags(node);
    }

    /**
     * Bring a card into view WITHOUT the jump scrollIntoView causes here: a
     * card is taller than the modal body on a laptop screen, so "nearest"
     * always counts it as out of view and scrolls the section heading off the
     * top even when the card was already sitting right there. Only scroll if
     * the card's own header is actually outside the scrollport, and then put
     * it just below the top edge.
     */
    function pbRevealCard(node) {
        const body = node.closest('.fcm-body')[0];
        if (!body || !node[0]) return;
        const cardTop = node[0].getBoundingClientRect().top;
        const view = body.getBoundingClientRect();
        if (cardTop >= view.top && cardTop <= view.bottom - 60) return;
        body.scrollTop += cardTop - view.top - 8;
    }

    /** Chevron and header click must not be able to disagree about the state. */
    function pbSetCollapsed(node, collapsed) {
        node.toggleClass('pb-collapsed', collapsed);
        node.find('.pb-collapse')
            .attr('title', collapsed ? 'Expand' : 'Collapse')
            .find('i').toggleClass('fa-chevron-up', !collapsed).toggleClass('fa-chevron-down', collapsed);
    }

    function pbRefresh(prefix) {
        let cost = 0, charge = 0, profit = 0, count = 0;
        pbContainer(prefix).children('.pb-card').each(function (index) {
            const node = $(this);
            node.find('.pb-seq').text(index + 1);
            const totals = pbCardTotals(node);
            cost += totals.cost; charge += totals.charge; profit += totals.profit; count++;
        });

        $('#' + prefix + '_pb_count').text(count);
        $('#' + prefix + '_pb_charge').text(moneyShort(charge));
        $('#' + prefix + '_pb_cost').text(moneyShort(cost));
        $('#' + prefix + '_pb_profit').text(moneyShort(profit)).toggleClass('neg', profit < 0);
        $('#' + prefix + '_passenger_empty').toggle(count === 0);

        // No flight-level price readout to update any more — every figure is
        // per card, and the roll-up above is the batch total.
        // Keeps the step-bar strip's seat count and batch profit honest while
        // you are working inside step 2.
        if (typeof window.fmRefreshContext === 'function') window.fmRefreshContext(prefix);
    }

    /**
     * A cost box only exists while the service it pays for is switched on.
     * Hiding it is not enough — the value is cleared too, so a charge can
     * never outlive the service, which is the same rule the server applies in
     * ManageFlightController::syncPassengerRows().
     */
    /**
     * The standing fee a service starts at (data-default in the blade:
     * Immigration 25000, Boarding 15000 — owner, 2026-09-22).
     *
     * It is a starting figure, never an override. Four things stop it
     * fighting the machinery that already fills these boxes:
     *   - a saved passenger never gets one, because a blank recorded against
     *     a real passenger is a deliberate blank (same reason hydrate() seeds
     *     saved figures verbatim);
     *   - it only fills a box that is empty, so a rate preset wins — the
     *     preset runs first and pbApplyCardRules() follows it;
     *   - once someone types in the box, data-edited is set and the default
     *     is never applied again, so clearing a fee to nothing sticks;
     *   - it fills only while the service is on, so it can never leave a
     *     charge behind a switched-off service.
     */
    function pbApplyServiceDefault(node, selector) {
        if (node.attr('data-pb-saved') === '1') return;
        const input = node.find(selector);
        const preset = input.attr('data-default');
        if (!preset || input.attr('data-edited') === '1' || input.val() !== '') return;
        input.val(preset);
    }

    /**
     * One PNR, one service package (owner, 2026-09-22).
     *
     * An agent's group travels on one booking — XYZ, XYZ (a), XYZ (b) — and
     * in practice buys the same services, arranged by the same trainer, paid
     * the same way. So the second and third cards copy the service block off
     * whichever card already carries that PNR instead of being typed again.
     *
     * Matched on the BASE PNR, our " (a)" suffix stripped, because that
     * suffix is exactly what distinguishes two people on one booking — see
     * TicketPurchase::basePnr(), which this mirrors.
     *
     * SERVICES ONLY. Not the agent, category, class, ticket, agent price or
     * reissue: those are the things that legitimately differ between two
     * people on one booking, and copying them is how a batch's money stops
     * matching reality.
     */
    function pbBasePnr(pnr) {
        return String(pnr == null ? '' : pnr).trim().replace(/\s*\([a-z]+\)\s*$/i, '').trim();
    }

    /**
     * The first other card on this flight whose PNR shares a base with mine.
     * Compared lower-cased: the ticket_no column is matched case-insensitively
     * in MySQL, so "xyz" and "XYZ" are one booking to the server and must be
     * one booking here too.
     */
    function pbServiceTwin(node) {
        const key = t => pbBasePnr(t.ticket_no).toLowerCase();
        const mine = new Set((node.data('pbTickets') || []).map(key).filter(Boolean));
        if (!mine.size) return null;
        let twin = null;
        pbContainer(pbPrefixOf(node)).children('.pb-card').each(function () {
            if (twin || this === node[0]) return;
            const other = $(this);
            if ((other.data('pbTickets') || []).some(t => mine.has(key(t)))) twin = other;
        });
        return twin;
    }

    function pbCopyServicesFromTwin(node) {
        // A saved passenger keeps what was recorded, and a card whose fees
        // someone has already typed is not overwritten — only the untouched
        // ones, which is the case this is meant for.
        if (node.attr('data-pb-saved') === '1') return;
        if (node.find('.pb-cost[data-edited="1"]').length) return;
        const twin = pbServiceTwin(node);
        if (!twin) return;

        ['.pb-svc-immigration', '.pb-svc-boarding', '.pb-svc-bmet', '.pb-svc-other'].forEach(function (sel) {
            node.find(sel).prop('checked', twin.find(sel).is(':checked'));
        });
        // Marked as edited: these are now deliberate figures for this
        // passenger, so a later rate-preset lookup must not overwrite them.
        ['.pb-immigration', '.pb-boarding', '.pb-manpower', '.pb-other-title', '.pb-other-cost'].forEach(function (sel) {
            node.find(sel).val(twin.find(sel).val()).attr('data-edited', '1');
        });
        node.find('.pb-sc-row').each(function () {
            const to = $(this);
            const from = twin.find('.pb-sc-row[data-cost-type="' + to.attr('data-cost-type') + '"]');
            if (!from.length) return;
            ['.pb-sc-status', '.pb-sc-paid', '.pb-sc-incurred', '.pb-sc-due-date', '.pb-sc-payment-date'].forEach(function (sel) {
                to.find(sel).val(from.find(sel).val());
            });
            ['.pb-sc-vendor', '.pb-sc-bank'].forEach(function (sel) {
                to.find(sel).val(from.find(sel).val() || '').trigger('change.select2');
            });
        });

        pbApplyCardRules(node);
        pbRefresh(pbPrefixOf(node));
    }

    function pbApplyCardRules(node) {
        // Boarding Training: a ticked service like the others (2026-09-21);
        // its cost box exists only while it is on.
        const boarding = node.find('.pb-svc-boarding').is(':checked');
        node.find('.pb-boarding-on').val(boarding ? '1' : '0');
        node.find('.pb-boarding-wrap').toggle(boarding);
        if (!boarding) node.find('.pb-boarding').val('');
        else pbApplyServiceDefault(node, '.pb-boarding');

        const weArrangeBmet = node.find('.pb-svc-bmet').is(':checked');
        node.find('.pb-bmet').val(weArrangeBmet ? '0' : '1');
        node.find('.pb-manpower-wrap').toggle(weArrangeBmet);
        if (!weArrangeBmet) node.find('.pb-manpower').val('');

        const other = node.find('.pb-svc-other').is(':checked');
        node.find('.pb-other-fields').toggle(other);
        if (!other) node.find('.pb-other-title, .pb-other-cost').val('');
        const immigration = node.find('.pb-svc-immigration').is(':checked');
        node.find('.pb-handling').val(immigration ? 'immigration_wise' : 'manpower_wise');
        node.find('.pb-immigration-wrap').toggle(immigration);
        if (!immigration) node.find('.pb-immigration').val('');
        else pbApplyServiceDefault(node, '.pb-immigration');

        pbSyncServiceCostRows(node);
        pbCardTotals(node);
    }

    function pbApplyAllCardRules(prefix) {
        pbContainer(prefix).children('.pb-card').each(function () { pbApplyCardRules($(this)); });
        pbRefresh(prefix);
    }

    /** The category owns its type, so the card shows it rather than asking. */
    function pbShowCategoryType(node) {
        node.find('.pb-cat-type').text(
            node.find('.pb-category option:selected').data('type') || ''
        );
    }

    /** Any passenger taking immigration support — decides if an officer is needed. */
    // Open a collapsed card and scroll to it (used by the Review step's jump links).
    window.pbReveal = function (node) { pbSetCollapsed($(node), false); pbRevealCard($(node)); };

    window.pbAnyImmigration = function (prefix) {
        return pbContainer(prefix).find('.pb-svc-immigration:checked').length > 0;
    };

    /* ── Wiring ───────────────────────────────────────────────────── */

    $(function () {
        $(document).on('click', '.pb-add-btn', function () {
            const prefix = $(this).data('prefix');
            const cards = pbContainer(prefix).children('.pb-card');

            // Adding on top of a card nobody has filled in just stacks empty
            // rows. Open that one instead — it is the card they meant.
            const blank = cards.filter(function () { return !$(this).find('.pb-holder').val(); }).first();
            if (blank.length) {
                pbSetCollapsed(blank, false);
                pbRevealCard(blank);
                // Nothing visibly happens otherwise, which reads as a dead
                // button — the flash points at the row already waiting.
                blank.removeClass('pb-flash');
                void blank[0].offsetWidth; // restart the animation
                blank.addClass('pb-flash');
                return;
            }

            // One card open at a time. A finished passenger is ~500px of form
            // nobody is reading any more, and five of them buried the one
            // being typed into under a page and a half of scroll.
            cards.each(function () { pbSetCollapsed($(this), true); });
            // Deliberately NOT opening the holder dropdown: select2 renders it
            // as a floating panel that flips above the field when the modal is
            // short, which reads as a second modal opening by itself.
            const node = pbAddCard(prefix, {});
            pbRevealCard(node);
        });

        $(document).on('click', '.pb-remove', function () {
            const node = $(this).closest('.pb-card');
            const prefix = pbPrefixOf(node);
            const name = node.find('.pb-card-name').text();
            Swal.fire({
                icon: 'warning',
                title: 'Remove passenger?',
                text: name + ' will be taken off this flight when you save. Any ticket already issued stays in Ticketing.',
                showCancelButton: true,
                confirmButtonText: 'Remove',
                confirmButtonColor: '#dc2626'
            }).then(function (result) {
                if (!result.isConfirmed) return;
                node.remove();
                pbRefresh(prefix);
            });
        });

        $(document).on('click', '.pb-collapse', function (event) {
            event.stopPropagation(); // the header behind it toggles too
            const node = $(this).closest('.pb-card');
            pbSetCollapsed(node, !node.hasClass('pb-collapsed'));
        });

        // The whole header strip is the toggle — a 26px chevron is a small
        // target to hit five times while checking a batch.
        $(document).on('click', '.pb-card-head', function (event) {
            if ($(event.target).closest('button').length) return;
            const node = $(this).closest('.pb-card');
            pbSetCollapsed(node, !node.hasClass('pb-collapsed'));
        });

        $(document).on('change', '.pb-holder', function () {
            const node = $(this).closest('.pb-card');
            // A re-booking box belongs to the passport it was opened for.
            node.find('.pb-rebook').remove();
            const holder = PB_HOLDERS.find(h => String(h.id) === String($(this).val()));
            pbSetCardIdentity(node, holder ? holder.text : '');
            if (holder && holder.phone && !node.find('.pb-phone').val()) node.find('.pb-phone').val(holder.phone);
            // A different person's tickets are not this person's tickets.
            pbRenderLinked(node, []);
            node.find('.pb-pnr-search').val('');
            pbLoadHolderTickets(node);
            pbCheckRebook(node);
        });

        $(document).on('change', '.pb-nt-status', function () {
            const node = $(this).closest('.pb-nt-ticket');
            node.attr('data-status', $(this).val() || 'purchased');
            const st = $(this).val() || 'purchased';
            node.find('.pb-nt-hold').toggle(st === 'booked');
            node.find('.pb-nt-save-txt').html(st === 'purchased' ? 'Create &amp; link' : (st === 'booked' ? 'Book &amp; hold' : 'Save as ' + st));
            node.find('.pb-nt-status-hint').text(st === 'purchased'
                ? 'Purchased posts to the vendor / portal ledger. Anything else is a record in Ticket Orders only.'
                : (st === 'booked' ? 'A hold: no money moves; the deadline, reminders and alerts run from Ticket Orders.' : 'Saved to Ticket Orders as ' + st + ' — no money moves.'));
        });

        $(document).on('click', '.pb-mode-existing', function () {
            const node = $(this).closest('.pb-card');
            node.find('.pb-mode-existing').addClass('active');
            node.find('.pb-mode-new').removeClass('active');
            node.find('.pb-existing').show();
            node.find('.pb-newticket').hide();
            // A manual choice leaves the scoped "New PNR" mode.
            node.data('pbScopedLeg', null);
        });
        $(document).on('change', '.pb-nt-include', function () {
            const block = $(this).closest('.pb-nt-ticket');
            block.children().not('.pb-nt-strip').css('opacity', this.checked ? '' : '.45');
            block.find('input, select, textarea').not('.pb-nt-include').prop('disabled', !this.checked);
        });

        $(document).on('click', '.pb-mode-new', function () {
            const node = $(this).closest('.pb-card');
            // A manual "+ Issue New Ticket" means the whole route, not one leg.
            node.data('pbScopedLeg', null);
            pbShowNewTicketPanel(node);
        });

        $(document).on('change', '.pb-nt-route', function () {
            const node = $(this).closest('.pb-card');
            const route = PB_ROUTES.find(t => String(t.id) === String($(this).val()));
            pbApplyRouteLegs(node, route);
        });

        $(document).on('click', '.pb-route-toggle', function () {
            $(this).closest('.pb-card').find('.pb-route-create').toggle();
        });

        $(document).on('click', '.pb-route-save', function () {
            const node = $(this).closest('.pb-card');
            const button = $(this);
            const error = node.find('.pb-route-error').text('');
            const title = node.find('.pb-route-title').val().trim();
            if (!title) return error.text('Enter a route title.');
            const price = Number(node.find('.pb-route-price').val());
            if (!Number.isFinite(price) || price < 0) return error.text('Enter a valid default cost.');
            button.prop('disabled', true);
            $.post(PB_URLS.createRoute, {
                _token: $('meta[name="csrf-token"]').attr('content'),
                title: title,
                price: price,
                airline_id: node.find('.pb-route-airline').val() || null,
                from_airport_id: node.find('.pb-route-from').val() || null,
                to_airport_id: node.find('.pb-route-to').val() || null
            }).done(function (response) {
                if (!response.success) return error.text(response.message || 'Could not create the route.');
                const ticket = response.ticket;
                PB_ROUTES.push({ id: ticket.id, text: ticket.title, price: ticket.price });
                $('.pb-nt-route').each(function () {
                    $(this).append(new Option(ticket.title, ticket.id));
                });
                node.find('.pb-nt-route').val(String(ticket.id)).trigger('change');
                node.find('.pb-route-create').hide();
                node.find('.pb-route-title, .pb-route-price').val('');
                node.find('.pb-route-airline, .pb-route-from, .pb-route-to').val('').trigger('change.select2');
            }).fail(function (xhr) {
                error.text(xhr.responseJSON?.message || 'Could not create the route. Check your permission and try again.');
            }).always(function () { button.prop('disabled', false); });
        });

        $(document).on('click', '.pb-leg-add', function () {
            $(this).closest('.pb-nt-ticket').find('.pb-nt-legs').append(pbLegRow({}));
        });

        $(document).on('click', '.pb-leg-remove', function () {
            $(this).closest('.pb-nt-leg').remove();
        });

        // The Flight panel's Departure and the first leg's Travel date are the
        // same moment: editing either one fills the other (owner, 2026-11-01).
        $(document).on('input change', '.pb-nt-dep', function () {
            $(this).closest('.pb-nt-ticket').find('.pb-nt-leg').first().find('.pb-leg-date').val($(this).val());
        });
        $(document).on('input change', '.pb-nt-leg .pb-leg-date', function () {
            const leg = $(this).closest('.pb-nt-leg');
            if (!leg.is(leg.parent().children('.pb-nt-leg').first())) return;
            leg.closest('.pb-nt-ticket').find('.pb-nt-dep').val($(this).val());
        });

        let pbSearchTimer = null;
        $(document).on('input', '.pb-pnr-search', function () {
            const node = $(this).closest('.pb-card');
            const value = $(this).val();
            clearTimeout(pbSearchTimer);
            pbSearchTimer = setTimeout(() => pbLoadHolderTickets(node, value), 300);
        });

        $(document).on('change', '.pb-nt-vendor, .pb-nt-portal', function () {
            const node = $(this).closest('.pb-nt-ticket');
            const other = node.find($(this).hasClass('pb-nt-vendor') ? '.pb-nt-portal' : '.pb-nt-vendor');
            const selected = Boolean($(this).val());
            if (selected) other.val('').trigger('change.select2');
            other.prop('disabled', selected);
            const portalSelected = Boolean(node.find('.pb-nt-portal').val());
            const bank = node.find('.pb-nt-bank').prop('disabled', portalSelected);
            if (portalSelected) bank.val('').trigger('change.select2');
            const dueDate = node.find('.pb-nt-due-date');
            dueDate.parent().toggle(!portalSelected);
            if (portalSelected) dueDate.val('');
        });

        $(document).on('change', '.pb-nt-pay-status', function () {
            const node = $(this).closest('.pb-nt-ticket');
            if ($(this).val() === 'paid') node.find('.pb-nt-paid').val(node.find('.pb-nt-amount').val() || 0);
            if ($(this).val() === 'due') node.find('.pb-nt-paid').val(0);
            if ($(this).val() !== 'partial') pbSyncNewTicketPayStatus(node);
        });

        $(document).on('input change', '.pb-linked-bag-field', function () {
            const box = $(this).closest('.pb-linked-baggage'), node = box.closest('.pb-card');
            const ticket = (node.data('pbTickets') || []).find(t => String(t.id) === box.data('ticketId'));
            if (!ticket) return;
            box.find('.pb-linked-bag-field').each(function () { ticket[$(this).attr('data-field')] = $(this).val() || null; });
            const company = ticket.extra_baggage_bearer === 'company';
            const charge = box.find('[data-field="extra_baggage_agent_charge"]');
            if (company) { charge.val('0'); ticket.extra_baggage_agent_charge = 0; }
            charge.prop('readonly', company);
            box.find('.pb-linked-bag-profit').text('Baggage profit ' + money((company ? 0 : num(ticket.extra_baggage_agent_charge)) - num(ticket.extra_baggage_cost)));
            pbRefresh(pbPrefixOf(node));
        });

        $(document).on('input change', '.pb-nt-extra-bag, .pb-nt-bag-cost, .pb-nt-bag-charge, .pb-nt-bag-bearer', function () {
            pbNewBaggage($(this).closest('.pb-nt-ticket'));
        });

        $(document).on('input', '.pb-nt-amount, .pb-nt-paid', function () {
            const block = $(this).closest('.pb-nt-ticket');
            pbSyncNewTicketPayStatus(block);
            if ($(this).hasClass('pb-nt-amount')) { pbSyncSegmentCost(block); pbRenderReissueMoney(block.closest('.pb-card')); }
        });

        // Typing a segment cost by hand takes it off the ticket cost — from
        // then on the two are independent, the same discipline data-edited
        // gives the service fees and the rate presets.
        $(document).on('input', '.pb-leg-cost', function () {
            $(this).attr('data-edited', '1');
        });

        $(document).on('click', '.pb-pnr.pb-pick', function () {
            const node = $(this).closest('.pb-card');
            const ticket = JSON.parse($(this).attr('data-ticket'));
            pbRenderLinked(node, (node.data('pbTickets') || []).concat([ticket]));
            pbCopyServicesFromTwin(node);
            pbLoadHolderTickets(node, node.find('.pb-pnr-search').val());
            pbRefresh(pbPrefixOf(node));
        });

        $(document).on('click', '.pb-unlink', function (event) {
            event.stopPropagation();
            const node = $(this).closest('.pb-card');
            const id = String($(this).data('tid'));
            pbRenderLinked(node, (node.data('pbTickets') || []).filter(t => String(t.id) !== id));
            pbLoadHolderTickets(node, node.find('.pb-pnr-search').val());
            pbRefresh(pbPrefixOf(node));
        });

        $(document).on('click', '.pb-nt-save', function () {
            const node = $(this).closest('.pb-card');
            pbCreateTicket(node, pbPrefixOf(node));
        });

        $(document).on('input change', '.pb-cost, .pb-agent-price', function () {
            const node = $(this).closest('.pb-card');
            pbSyncServiceCostRows(node);
            pbRefresh(pbPrefixOf(node));
        });

        $(document).on('change', '.pb-sc-status', function () {
            pbApplyServicePaymentRules($(this).closest('.pb-sc-row'), true);
        });

        $(document).on('input', '.pb-sc-paid', function () {
            const accountingRow = $(this).closest('.pb-sc-row');
            const amount = pbServiceCostAmount(accountingRow.closest('.pb-card'), accountingRow.data('cost-type'));
            const paid = num($(this).val());
            accountingRow.find('.pb-sc-status').val(paid <= 0 ? 'due' : (paid >= amount ? 'paid' : 'partial'));
            pbApplyServicePaymentRules(accountingRow, false);
        });

        $(document).on('input', '.pb-other-title', function () {
            pbSyncServiceCostRows($(this).closest('.pb-card'));
        });

        // Ticking a service reveals its cost box; unticking clears it. Taking
        // immigration support on or off also re-keys this card's rate lookup.
        $(document).on('change', '.pb-svc-bmet, .pb-svc-boarding, .pb-svc-immigration, .pb-svc-other', function () {
            const node = $(this).closest('.pb-card');
            pbApplyCardRules(node);
            pbRefresh(pbPrefixOf(node));
            if ($(this).hasClass('pb-svc-immigration')) pbLoadPreset(node, pbPrefixOf(node));
        });

        // Step 1's agent is the default: changing it re-stamps only the cards
        // that still carried the previous default, never one someone set by
        // hand to a different agent.
        // Step 1 lists every agent on the batch; the FIRST is the default
        // new cards are billed to (posted as agent_id). Changing the default
        // re-stamps only the cards that still carried the previous default,
        // never one someone set by hand. Removing an agent from the list
        // never touches a card — the card is the billing record.
        $(document).on('change', '.pb-agent-select', function () {
            const prefix = this.id.startsWith('edit_') ? 'edit' : 'create';
            const chosen = $(this).val() || [];
            const next = chosen[0] || '';
            const previous = $(this).data('pbPrev') || '';
            const name = next ? $(this).find('option[value="' + next + '"]').text().trim() : '';
            $('#' + prefix + '_agent_id').val(next);
            if (previous !== next) {
                pbContainer(prefix).find('.pb-agent').each(function () {
                    if (($(this).val() || '') === previous) $(this).val(next).trigger('change.select2');
                });
            }
            $(this).data('pbPrev', next);
            $('#' + prefix + '_pb_agent_echo').text(next ? name : 'the first agent you choose');
            pbContainer(prefix).children('.pb-card').each(function () { pbCardTags($(this)); });
            if (typeof fmRefreshContext === 'function') fmRefreshContext(prefix);
        });

        // Step 1's vendor / portal: one or the other, and offered to every
        // card's new-ticket form that has not chosen its own seller yet.
        $(document).on('change', '.fs-ticket-source', function () {
            const prefix = this.id.startsWith('edit_') ? 'edit' : 'create';
            pbApplyTicketSourceRule(prefix);
            pbContainer(prefix).children('.pb-card').each(function () { pbSeedTicketSource($(this), true); });
        });
        $(document).on('change', '.pb-agent, .pb-tickets-required', function () {
            pbCardTags($(this).closest('.pb-card'));
        });
        // An agent picked on a card is added to step 1's list automatically
        // — the flight page always names everyone billed on the batch — and
        // becomes the default if the batch had none yet.
        $(document).on('change', '.pb-agent', function () {
            pbSyncFlightAgents(pbPrefixOf($(this)));
        });

        // Category, class and the service mix each re-key the rate lookup for
        // that one passenger. There is no flight-level default to propagate
        // any more — the card is the only place these are answered.
        $(document).on('change', '.pb-category', function () {
            const node = $(this).closest('.pb-card');
            pbShowCategoryType(node);
            pbLoadPreset(node, pbPrefixOf(node));
        });
        $(document).on('change', '.pb-class', function () {
            const node = $(this).closest('.pb-card');
            pbLoadPreset(node, pbPrefixOf(node));
        });

        // Once a cost is typed it is that passenger's own figure, and no later
        // preset lookup may overwrite it.
        $(document).on('input', '.pb-manpower, .pb-boarding, .pb-immigration, .pb-agent-price', function () {
            $(this).attr('data-edited', '1');
        });

        // Changing the flight's airline re-keys every card's rate lookup.
        $(document).on('change', '.pricing-airline', function () {
            const prefix = this.id.startsWith('edit_') ? 'edit' : 'create';
            pbContainer(prefix).children('.pb-card').each(function () {
                pbLoadPreset($(this), prefix);
            });
        });

        $(document).on('click', '.pb-new-holder', function () {
            pbNewHolderCard = $(this).closest('.pb-card');
            if (typeof openFlightPassengerModal === 'function') openFlightPassengerModal(null);
        });

        // ── Add Flight: start clean with one empty card ──
        $('.create-btn').on('click', function () {
            pbContainer('create').empty();
            $('#create_agent_ids').val([]).data('pbPrev', '').trigger('change.select2');
            $('#create_agent_id').val('');
            $('#create_vendor_id, #create_portal_id').val('').prop('disabled', false).trigger('change.select2');
            $('#create_pb_agent_echo').text('the first agent you choose');
            $('#create_passenger_msg').addClass('hidden');
            pbAddCard('create', {});
        });

        // ── Edit Flight: load the saved passengers into the builder ──
        $(document).on('click', '.edit-btn', function () {
            const flightId = $(this).data('item_id');
            const container = pbContainer('edit').empty();
            $('#edit_passenger_msg').addClass('hidden');
            pbLoading.edit = true;
            container.append('<div class="pb-empty pb-loading"><i class="fas fa-spinner fa-spin"></i>Loading this flight’s passengers…</div>');

            $.get(PB_URLS.flightPassengers.replace('__FID__', flightId))
                .done(function (response) {
                    container.empty();
                    if (!response.success) return;
                    // The flight's agent is whatever its passengers are billed
                    // to; falling back to the flight column covers a batch
                    // that has no passengers on it yet.
                    const flight = (typeof mfFlights !== 'undefined')
                        ? mfFlights.find(f => String(f.id) === String(flightId)) : null;
                    // Step 1's list: the flight's default first, then every
                    // agent its passengers are billed to.
                    const agentIds = [];
                    const push = id => { if (id && !agentIds.includes(String(id))) agentIds.push(String(id)); };
                    push(flight && flight.agent_id);
                    response.passengers.forEach(p => push(p.agent_id));
                    $('#edit_agent_ids').val(agentIds).data('pbPrev', agentIds[0] || '').trigger('change.select2');
                    $('#edit_agent_id').val(agentIds[0] || '');
                    $('#edit_pb_agent_echo').text(agentIds[0] ? $('#edit_agent_ids option[value="' + agentIds[0] + '"]').text().trim() : 'the first agent you choose');
                    $('#edit_vendor_id').val(flight && flight.vendor_id ? String(flight.vendor_id) : '').trigger('change.select2');
                    $('#edit_portal_id').val(flight && flight.portal_id ? String(flight.portal_id) : '').trigger('change.select2');
                    pbApplyTicketSourceRule('edit');
                    response.passengers.forEach(p => pbAddCard('edit', p));
                    pbRefresh('edit');
                    // A saved batch opens as a readable list, not five open
                    // forms — the chips on each header say what is inside,
                    // and you expand only the passenger you came to change.
                    if (response.passengers.length > 1) {
                        container.children('.pb-card').each(function () { pbSetCollapsed($(this), true); });
                    }
                    // After a Refund / New ticket re-assign (2026-09-30): his
                    // card open, Issue New Ticket ready, scrolled into view.
                    const issueFor = new URLSearchParams(window.location.search).get('issue');
                    const legFor = new URLSearchParams(window.location.search).get('leg');
                    if (issueFor) {
                        // used once — a later reload must not open his card again
                        const clean = new URL(window.location.href);
                        clean.searchParams.delete('issue');
                        clean.searchParams.delete('leg');
                        window.history.replaceState(window.history.state, '', clean.toString());
                    }
                    if (issueFor) {
                        const card = container.children('.pb-card').filter(function () { return String($(this).data('pbPassengerId')) === String(issueFor); }).first();
                        if (card.length) {
                            pbSetCollapsed(card, false);
                            // Only one leg refunded: issue that leg alone (the other flies on its live ticket).
                            const legParam = Number(legFor) || null;
                            card.data('pbScopedLeg', legParam);
                            pbShowNewTicketPanel(card);
                            const rt = PB_ROUTES.find(t => String(t.id) === String(card.find('.pb-nt-route').val()));
                            if (rt) pbEnsureTicketBlocks(card, rt);
                            setTimeout(() => card[0].scrollIntoView({ behavior: 'smooth', block: 'start' }), 300);
                        }
                    }
                })
                .fail(function () {
                    container.empty();
                    $('#edit_passenger_msg').removeClass('hidden')
                        .text('Could not load this flight’s passengers. Close and reopen before saving, or the passenger list may be written back empty.');
                })
                .always(function () { pbLoading.edit = false; });
        });
    });

    /* ── Validation, hooked into the existing form validator ──────── */

    let pbNewHolderCard = null;

    /**
     * Called by validateFlightForm() before the form is posted. Returns false
     * to stop the save.
     */
    window.pbValidate = function (prefix) {
        const message = $('#' + prefix + '_passenger_msg').addClass('hidden');
        const cards = pbContainer(prefix).children('.pb-card');

        if (pbLoading[prefix]) {
            message.removeClass('hidden').text('Still loading this flight’s passengers — wait a moment before saving.');
            return false;
        }

        let ok = true;
        const seen = {};

        /**
         * Cards collapse as soon as the next one is added, so a message at the
         * bottom of the list could name a problem the person cannot see. Open
         * the card that failed, scroll to it, and say which passenger it is.
         */
        function fail(node, text) {
            pbSetCollapsed(node, false);
            const seq = node.find('.pb-seq').text();
            const name = node.find('.pb-card-name').text().trim();
            message.removeClass('hidden').text('Passenger ' + seq + (name && name !== 'New passenger' ? ' (' + name + ')' : '') + ': ' + text);
            pbRevealCard(node);
            ok = false;
            return false; // stops the .each()
        }

        cards.each(function () {
            const node = $(this);
            if (node.find('.pb-svc-other').is(':checked') && node.find('.pb-other-cost').val() !== ''
                && (!Number.isFinite(Number(node.find('.pb-other-cost').val())) || Number(node.find('.pb-other-cost').val()) < 0)) {
                message.removeClass('hidden').text('Enter a valid cost for the Other service.');
                ok = false; return false;
            }
            let serviceIssue = null;
            node.find('.pb-sc-row:visible').each(function () {
                const accountingRow = $(this);
                const label = accountingRow.find('.pb-sc-label').text().trim();
                const amount = pbServiceCostAmount(node, accountingRow.data('cost-type'));
                const paid = num(accountingRow.find('.pb-sc-paid').val());
                const status = accountingRow.find('.pb-sc-status').val();

                // Nothing here is mandatory (owner, 2026-09-21): a service with no
                // responsible person or date yet is saved as unassigned and listed
                // on the Review step. Only money that contradicts itself is blocked.
                if (paid > amount) serviceIssue = label + ' paid amount cannot exceed its cost.';
                else if (status === 'partial' && (paid <= 0 || paid >= amount)) serviceIssue = 'enter a partial payment between zero and the ' + label + ' cost.';
                else if (status === 'paid' && Math.abs(paid - amount) > 0.005) serviceIssue = label + ' is marked Paid, so the paid amount must equal its cost.';
                else if (paid > 0 && !accountingRow.find('.pb-sc-bank').val()) serviceIssue = 'select the bank/cash account used to pay ' + label + '.';
                else if (paid > 0 && !accountingRow.find('.pb-sc-payment-date').val()) serviceIssue = 'enter the payment date for ' + label + '.';

                return !serviceIssue;
            });
            if (serviceIssue) return fail(node, serviceIssue);

            const holderId = node.find('.pb-holder').val();
            if (!holderId) return fail(node, 'choose the passport holder travelling on this card.');
            if (seen[holderId]) return fail(node, 'this passenger is already on the flight — remove the duplicate card.');
            seen[holderId] = true;
            if (!node.find('.pb-agent').val()) return fail(node, 'choose the agent this passenger is billed to.');
            const rm = node.data('pbReissue');
            if (rm && !rm.settled) {
                if (pbReplacementFare(node, false) > 0 && !rm.fare_bearer) return fail(node, 'choose who bears the fare difference.');
                if (rm.agent_fare_amount != null && (rm.agent_fare_amount === '' || !Number.isFinite(Number(rm.agent_fare_amount)) || num(rm.agent_fare_amount) < 0 || num(rm.agent_fare_amount) > pbReplacementFare(node, false))) return fail(node, 'enter an agent share between zero and the actual fare difference.');
            }

            // Category and agent price are not blockers: a card saved with
            // only the passport holder and agent waits at Passenger Received
            // and moves forward as those are filled in.
        });
        return ok;
    };

    /**
     * A passport holder created from the quick-add popup lands on the card
     * that opened it. The popup's own handler also appends the option to
     * every .passenger-select on the page; this adds it to the builder's
     * selects, which are built from a JS list rather than server-rendered.
     */
    window.pbRegisterHolder = function (holder) {
        PB_HOLDERS.push({ id: holder.id, text: holder.name + ' — ' + holder.passport_no, phone: holder.phone || '' });
        $('.pb-holder').each(function () {
            if (!$(this).find('option[value="' + holder.id + '"]').length) {
                $(this).append(new Option(holder.name + ' — ' + holder.passport_no, holder.id));
            }
        });
        if (pbNewHolderCard && pbNewHolderCard.length) {
            pbNewHolderCard.find('.pb-holder').val(String(holder.id)).trigger('change');
            pbNewHolderCard = null;
        }
    };
})();
</script>
