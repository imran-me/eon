<script>
/* ══════════════════════════════════════════════════════════════════════
   Step navigation for the Add / Edit Flight modal.

   The panes are presentation only — all three live inside ONE <form>, so
   whichever pane is on screen, serialize() still posts the entire flight.
   Nothing here changes what is submitted; it only changes what is shown.
   ══════════════════════════════════════════════════════════════════════ */
(function () {
    const STEPS = ['flight', 'passengers', 'review'];
    const money = v => '৳' + Math.round(Number(v) || 0).toLocaleString();

    function fmGo(prefix, step) {
        const modal = $('#' + prefix + 'Modal');
        modal.find('.fm-pane').removeClass('active')
             .filter('[data-pane="' + prefix + '-' + step + '"]').addClass('active');
        modal.find('.fm-steps[data-prefix="' + prefix + '"] .fm-step').removeClass('active')
             .filter('[data-step="' + step + '"]').addClass('active');

        const index = STEPS.indexOf(step);
        modal.find('#' + prefix + 'Submit').toggle(step === 'review');
        modal.find('.fm-back[data-prefix="' + prefix + '"]').toggle(index > 0);
        modal.find('.fm-next[data-prefix="' + prefix + '"]').toggle(index < STEPS.length - 1);

        // Long passenger lists otherwise leave you halfway down the previous
        // pane with no idea the view changed.
        modal.find('.fcm-body').scrollTop(0);
        fmRefreshContext(prefix);

        // The review is built from the live form each time it is opened, so
        // it can never show something different from what will be posted.
        if (step === 'review') fmRenderReview(prefix);
    }

    function fmCurrent(prefix) {
        const active = $('#' + prefix + 'Modal').find('.fm-step.active').data('step');
        return STEPS.indexOf(active) > -1 ? active : 'flight';
    }

    /**
     * Which step a field belongs to, so a validation failure can reveal the
     * pane holding it instead of flagging an error nobody can see.
     */
    function fmStepOfField(prefix, el) {
        const pane = $(el).closest('.fm-pane').attr('data-pane') || '';
        return pane.replace(prefix + '-', '') || 'flight';
    }

    /**
     * Called after validateFlightForm() has marked its messages. Opens the
     * first step carrying an error and badges every step that has one.
     */
    window.fmRevealErrors = function (prefix) {
        const modal = $('#' + prefix + 'Modal');
        const stepsWithError = {};

        modal.find('.error-message').not('.hidden').each(function () {
            stepsWithError[fmStepOfField(prefix, this)] = true;
        });

        modal.find('.fm-step').each(function () {
            $(this).toggleClass('has-error', !!stepsWithError[$(this).data('step')]);
        });

        const first = STEPS.find(step => stepsWithError[step]);
        if (first && first !== fmCurrent(prefix)) fmGo(prefix, first);
        return !first;
    };

    window.fmClearErrors = function (prefix) {
        $('#' + prefix + 'Modal').find('.fm-step').removeClass('has-error');
    };

    /**
     * The strip under the step bar — keeps the flight in view while you are
     * three passengers deep in step 2.
     */
    window.fmRefreshContext = function (prefix) {
        const routeText = $('#' + prefix + '_ticket_id option:selected').text().trim();
        const flightNo = ($('#' + prefix + '_airline_flight_no').val() || '').trim();
        $('#' + prefix + '_ctx_route').text(
            routeText && !routeText.startsWith('—') ? routeText + (flightNo ? ' · ' + flightNo : '') : (flightNo || '—')
        );

        const departure = $('#' + prefix + '_departure_at').val();
        $('#' + prefix + '_ctx_departure').text(departure
            ? new Date(departure).toLocaleString(undefined, { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })
            : '—');

        const cards = $('#' + prefix + '_passenger_cards').children('.pb-card').length;
        $('#' + prefix + '_ctx_seats').text(cards === 1 ? '1 passenger' : cards + ' passengers');

        const agents = $('#' + prefix + '_agent_ids option:selected').map(function () { return $(this).text().trim(); }).get();
        $('#' + prefix + '_ctx_agent').text(agents.length > 2 ? agents[0] + ' +' + (agents.length - 1) : (agents.join(', ') || 'Direct'));

        $('#' + prefix + '_ctx_profit').text($('#' + prefix + '_pb_profit').text() || '৳0');
    };

    /* ── Step 3: read the form back ─────────────────────────────── */

    function fmText(sel, fallback) {
        const v = ($(sel).val() || '').toString().trim();
        return v === '' ? (fallback || '—') : v;
    }
    function fmSelected(sel, fallback) {
        const opt = $(sel + ' option:selected');
        const t = (opt.text() || '').trim();
        return (!opt.val() || t === '') ? (fallback || '—') : t;
    }
    function fmRow(label, value, missing) {
        return '<dt>' + label + '</dt><dd' + (missing ? ' class="rv-missing"' : '') + '>'
            + $('<div>').text(value).html() + '</dd>';
    }

    window.fmRenderReview = function (prefix) {
        const problems = [];
        const pending = [];

        // ── Flight ──
        const route = fmSelected('#' + prefix + '_ticket_id');
        const departure = $('#' + prefix + '_departure_at').val();
        const arrival = $('#' + prefix + '_arrival_at').val();
        const fmtDT = v => v ? new Date(v).toLocaleString(undefined,
            { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—';

        // Missing route / flight number / category / price are "still to
        // come", not errors — they are listed so nobody forgets, but the
        // batch saves and the passengers wait at the matching stage.
        // Every gap is a link: click it and the modal jumps to that field
        // (owner, 2026-09-21 — nothing is mandatory; the list is the reminder).
        const gap = (text, step, target) => pending.push({ text, step, target });
        if (!$('#' + prefix + '_pricing_airline_id').val()) gap('Airline not chosen yet.', 'flight', '#' + prefix + '_pricing_airline_id');
        if (route === '—') gap('Ticket route not chosen yet — passengers stay before "Route Confirmed".', 'flight', '#' + prefix + '_ticket_id');
        if (!departure) gap('Departure date not known yet — it comes with the ticket.', 'flight', '#' + prefix + '_departure_at');
        if (!$('#' + prefix + '_airline_flight_no').val()) gap('Airline flight number not entered yet.', 'flight', '#' + prefix + '_airline_flight_no');

        $('#' + prefix + '_review_flight').html(
            fmRow('Airline / flight', fmSelected('#' + prefix + '_pricing_airline_id') + ' · ' + fmText('#' + prefix + '_airline_flight_no'))
            + fmRow('Route', route, route === '—')
            + fmRow('Departs', fmtDT(departure), !departure)
            + fmRow('Arrives', fmtDT(arrival))
        );

        $('#' + prefix + '_review_agent').html(
            fmRow('Agents', $('#' + prefix + '_agent_ids option:selected').map(function () { return $(this).text().trim(); }).get().join(', ') || 'Direct — no agent')
            + fmRow('Status', fmSelected('#' + prefix + '_status'))
            + fmRow('Notes', fmText('#' + prefix + '_notes', 'None'))
        );

        // ── Passengers ──
        const tbody = $('#' + prefix + '_review_pax').empty();
        const cards = $('#' + prefix + '_passenger_cards').children('.pb-card');
        let totalCost = 0, totalCharge = 0, anyImmigration = false;

        if (!cards.length) {
            tbody.append('<tr><td colspan="9" class="rv-empty">No passengers added yet — go back to step 2.</td></tr>');
            problems.push({ text: 'No passengers on this flight.', step: 'passengers', target: '#' + prefix + '_add_passenger, .pb-add' });
        }

        cards.each(function (index) {
            const card = $(this);
            const holder = card.find('.pb-holder option:selected').text().trim();
            const pnrs = card.find('.pb-linked .pb-pnr-no').map(function () { return $(this).text(); }).get();
            const cost = Number(card.find('.pb-total-cost').text().replace(/[^0-9.-]/g, '')) || 0;
            const charge = Number(card.find('.pb-total-charge').text().replace(/[^0-9.-]/g, '')) || 0;
            const profit = charge - cost;
            totalCost += cost; totalCharge += charge;

            const who = 'Passenger ' + (index + 1) + (holder ? ' (' + holder.split(' — ')[0] + ')' : '');
            const cgap = (text, sel) => gap(who + ': ' + text, 'passengers', card.find(sel).first());
            if (!card.find('.pb-holder').val()) problems.push({ text: 'Passenger ' + (index + 1) + ' has no passport holder selected.', step: 'passengers', target: card.find('.pb-holder') });
            if (!card.find('.pb-agent').val()) problems.push({ text: who + ' is not billed to any agent.', step: 'passengers', target: card.find('.pb-agent') });
            if (!card.find('.pb-category').val()) cgap('category not chosen yet.', '.pb-category');
            if (card.find('.pb-agent-price').val() === '') cgap('agent price not set yet.', '.pb-agent-price');
            // tickets: one line per slot that is not purchased yet
            (function () {
                const needed = Math.max(1, parseInt(card.find('.pb-tickets-required').val(), 10) || 1);
                const linked = card.data('pbTickets') || [];
                const holds = card.data('pbHolds') || [];
                for (let n = 1; n <= needed; n++) {
                    const t = linked.find(x => needed === 1 || Number(x.leg_sequence) === n) || (needed === 1 ? linked[0] : null);
                    if (t && t.status === 'confirm') continue;
                    const h = holds.find(x => needed === 1 || Number(x.leg_sequence) === n);
                    const label = needed > 1 ? 'ticket ' + n + ' of ' + needed : 'ticket';
                    cgap(label + (t ? ' is ' + t.status + ', not confirmed yet.' : (h ? ' is ' + (h.status === 'booked' ? 'on hold' : h.status) + ' in Ticket Orders — not purchased yet.' : ' not issued or linked yet.')), '.pb-pnr-search');
                }
            })();
            // services: whatever is ticked but not yet costed / assigned
            card.find('.pb-sc-row:visible').each(function () {
                const rowEl = $(this);
                const label = rowEl.find('.pb-sc-label').text().trim() || 'service';
                const costInput = rowEl.closest('.pb-svc').find('.pb-cost').first();
                if (costInput.length && (costInput.val() === '' || Number(costInput.val()) <= 0)) gap(who + ': ' + label + ' fee not entered.', 'passengers', costInput);
                if (!rowEl.find('.pb-sc-vendor').val()) gap(who + ': ' + label + ' — responsible person not chosen.', 'passengers', rowEl.find('.pb-sc-vendor'));
                if (!rowEl.find('.pb-sc-incurred').val()) gap(who + ': ' + label + ' — cost date not set.', 'passengers', rowEl.find('.pb-sc-incurred'));
                const paid = Number(rowEl.find('.pb-sc-paid').val()) || 0, amount = Number(costInput.val()) || 0;
                if (paid < amount && rowEl.find('.pb-sc-due-date').is(':visible') && !rowEl.find('.pb-sc-due-date').val()) gap(who + ': ' + label + ' — payment due date not set.', 'passengers', rowEl.find('.pb-sc-due-date'));
            });

            const services = [];
            if (card.find('.pb-svc-other').is(':checked')) {
                services.push($('<span class="rv-tag rv-tag-svc"></span>').text(card.find('.pb-other-title').val() || 'Others')[0].outerHTML);
            }
            if (card.find('.pb-svc-immigration').is(':checked')) {
                services.push('<span class="rv-tag rv-tag-svc">Immigration training</span>');
                anyImmigration = true;
            }
            if (card.find('.pb-svc-boarding').is(':checked')) services.push('<span class="rv-tag rv-tag-svc">Boarding training</span>');
            else services.push('<span class="rv-tag rv-tag-own">No boarding training</span>');
            if (card.find('.pb-svc-bmet').is(':checked')) services.push('<span class="rv-tag rv-tag-svc">BMET consulting</span>');
            else services.push('<span class="rv-tag rv-tag-own">Has permit</span>');

            tbody.append(
                $('<tr></tr>').append(
                    $('<td></td>').text(index + 1),
                    // The option label is "NAME — PASSPORT — PHONE", and the
                    // sub-line is the phone, so printing the label whole
                    // showed the number twice. Name and passport identify the
                    // traveller; the phone belongs on the second line only.
                    $('<td></td>').append(
                        $('<div class="rv-pax-name"></div>').text(holder ? holder.split(' — ').slice(0, 2).join(' — ') : 'Not selected'),
                        $('<div class="rv-pax-sub"></div>').text(card.find('.pb-phone').val() || '')
                    ),
                    $('<td></td>').append(
                        $('<div></div>').text(card.find('.pb-category option:selected').text().trim() || '—'),
                        $('<div class="rv-pax-sub"></div>').text(card.find('.pb-cat-type').text())
                    ),
                    $('<td></td>').text(card.find('.pb-class option:selected').text().trim()),
                    $('<td></td>').html(services.join(' ')),
                    $('<td></td>').html((function () {
                        const needed = Math.max(1, parseInt(card.find('.pb-tickets-required').val(), 10) || 1);
                        const done = (card.data('pbTickets') || []).filter(t => t.status === 'confirm').length;
                        const progress = needed > 1 ? '<span class="rv-tag ' + (done >= needed ? 'rv-tag-svc' : 'rv-tag-none') + '">' + done + ' / ' + needed + ' tickets</span> ' : '';
                        return progress + (pnrs.length
                            ? pnrs.map(p => '<span class="rv-tag rv-tag-pnr">' + $('<div>').text(p).html() + '</span>').join(' ')
                            : '<span class="rv-tag rv-tag-none">No ticket</span>');
                    })()),
                    $('<td class="r"></td>').text(money(cost)),
                    $('<td class="r"></td>').text(money(charge)),
                    $('<td class="r"></td>').addClass(profit < 0 ? 'rv-neg' : 'rv-pos').text(money(profit))
                )
            );
        });


        $('#' + prefix + '_review_pax_count').text(cards.length);

        const profit = totalCharge - totalCost;
        $('#' + prefix + '_review_totals').html(
            '<div class="rv-total-item"><span class="rv-total-lbl">Passengers</span><span class="rv-total-val">' + cards.length + '</span></div>'
            + '<div class="rv-total-item"><span class="rv-total-lbl">Agent charge</span><span class="rv-total-val">' + money(totalCharge) + '</span></div>'
            + '<div class="rv-total-item"><span class="rv-total-lbl">Travel cost</span><span class="rv-total-val">' + money(totalCost) + '</span></div>'
            + '<div class="rv-total-item"><span class="rv-total-lbl">Batch profit</span><span class="rv-total-val' + (profit < 0 ? ' neg' : '') + '">' + money(profit) + '</span></div>'
        );

        // Both lists are links: the item remembers its step and field.
        const item = (p, i, store) => {
            const o = typeof p === 'string' ? { text: p } : p;
            store.push(o);
            return '<li><a href="#" class="rv-jump" data-i="' + (store.length - 1) + '">' + $('<div>').text(o.text).html() + '</a>'
                + (o.step ? ' <i class="fas fa-arrow-turn-up rv-jump-ic"></i>' : '') + '</li>';
        };
        const box = $('#' + prefix + '_review_problems');
        fmJumpTargets[prefix] = [];
        if (problems.length) {
            box.html('<b><i class="fas fa-triangle-exclamation"></i> ' + problems.length
                + (problems.length === 1 ? ' thing needs' : ' things need') + ' fixing before this can be saved:</b><ul>'
                + problems.map((p, i) => item(p, i, fmJumpTargets[prefix])).join('') + '</ul>').show();
        } else {
            box.hide().empty();
        }
        const later = $('#' + prefix + '_review_pending');
        if (pending.length) {
            later.html('<b><i class="fas fa-hourglass-half"></i> Unfinished — ' + pending.length + ' item' + (pending.length === 1 ? '' : 's')
                + '. Saving is fine; the passenger\'s stage follows what is filled in. Click one to go to it:</b><ul>'
                + pending.map((p, i) => item(p, i, fmJumpTargets[prefix])).join('') + '</ul>').show();
        } else {
            later.hide().empty();
        }
    };

    // Jump from a review item to its field: switch step, open the card, scroll, focus, flash.
    const fmJumpTargets = {};
    function fmJump(prefix, entry) {
        if (!entry || !entry.step) return;
        fmGo(prefix, entry.step);
        let el = entry.target ? $(entry.target) : $();
        el = el.filter(':not(:hidden), select').first().length ? el.first() : el.first();
        if (!el.length) return;
        const card = el.closest('.pb-card');
        if (card.length && typeof window.pbReveal === 'function') window.pbReveal(card);
        setTimeout(function () {
            const box = el.closest('.pb-sub, .fs-card, .pb-svc, .pb-card').first().length ? el.closest('.pb-svc, .pb-sub, .fs-card').first() : el;
            (box.length ? box : el)[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
            const focusable = el.is('select.select2-hidden-accessible') ? el.next('.select2').find('.select2-selection') : el;
            try { focusable.trigger('focus'); } catch (e) { /* ignore */ }
            const flash = el.is('select.select2-hidden-accessible') ? el.next('.select2') : el;
            flash.addClass('rv-flash'); setTimeout(() => flash.removeClass('rv-flash'), 1600);
        }, 120);
    }
    $(document).on('click', '.rv-jump', function (e) {
        e.preventDefault();
        const prefix = $(this).closest('[id$="_review_problems"], [id$="_review_pending"]').attr('id').split('_')[0];
        fmJump(prefix, (fmJumpTargets[prefix] || [])[Number($(this).data('i'))]);
    });

    $(function () {
        $(document).on('click', '.fm-step', function () {
            fmGo($(this).closest('.fm-steps').data('prefix'), $(this).data('step'));
        });
        $(document).on('click', '.fm-next', function () {
            const prefix = $(this).data('prefix');
            fmGo(prefix, STEPS[Math.min(STEPS.indexOf(fmCurrent(prefix)) + 1, STEPS.length - 1)]);
        });
        $(document).on('click', '.fm-back', function () {
            const prefix = $(this).data('prefix');
            fmGo(prefix, STEPS[Math.max(STEPS.indexOf(fmCurrent(prefix)) - 1, 0)]);
        });

        // Anything that changes what the context strip reports.
        $(document).on('change input', [
            '[id$="_ticket_id"]', '[id$="_airline_flight_no"]', '[id$="_departure_at"]',
            '[id$="_agent_ids"]'
        ].join(','), function () {
            const prefix = this.id.startsWith('edit_') ? 'edit' : 'create';
            fmRefreshContext(prefix);
        });

        // Both modals always reopen on step 1 — resuming on step 3 from a
        // previous flight is disorienting.
        $('.create-btn').on('click', function () { fmClearErrors('create'); fmGo('create', 'flight'); });
        $(document).on('click', '.edit-btn', function () { fmClearErrors('edit'); fmGo('edit', 'flight'); });
    });
})();
</script>
