{{-- Behaviour for tickets/route-via (several via airports since 2026-10-11) and tickets/route-trip
     (round trip, 2026-10-18).
     No via airport: the form is exactly the direct form. Each via airport is a
     row: the airport, and whether a NEW TICKET starts there or it is only a
     TRANSIT inside the ticket it falls in. The route is cut into tickets at the
     "new ticket" stops; one card per ticket (own airline, seller, price), and the
     total follows. DAC → DOH (transit) → ESB (new ticket) → ECN = Ticket 1
     DAC → DOH → ESB, Ticket 2 ESB → ECN.
     Round trip: Return on the SAME ticket(s) changes no card (each ticket is a round-trip
     ticket); a SEPARATE return adds the return tickets To → From, cut the same way by the
     return via airports, after the outbound cards — legs[] posts them in that order.
     Posts route_stops[i][airport_id|new_ticket], return_stops[i][…], trip_type, return_mode and
     legs[k][...]; the hidden via_airport_id = the first stop, so the server's "has stops" rules hold.
     window.tkVia.set(prefix, viaId, legs, tripType) hydrates edit; tkVia.reset(prefix) clears create;
     tkVia.trip(prefix, trip, mode) sets the round trip from outside (the PDF import). --}}
<script>
window.tkVia = (function () {
    const FLAGS = @json(asset('flags'));
    const esc = t => $('<span>').text(t == null ? '' : String(t)).html();
    const codeOf = text => { const m = /\(([A-Z0-9]{3,4})\)/.exec(text || ''); return m ? m[1] : (text || '—').trim().split(' ')[0]; };
    // an airport's code + flag, read from the From select's own options
    function ap(prefix, id) {
        const opt = $('#' + prefix + '_from_airport_id option[value="' + id + '"]');
        if (!id || !opt.length) return { code: '—', flag: '' };
        return { code: codeOf(opt.text()), flag: String(opt.data('flag') || '').toLowerCase() };
    }
    const tag = a => (a.flag ? `<img class="tk-flag" src="${FLAGS}/${a.flag}.png" alt="" onerror="this.remove()">` : '') + esc(a.code);
    const arrow = '<i class="fas fa-arrow-right-long arrow"></i>';

    // outbound stops live in #{prefix}_stops, a separate return's in #{prefix}_rstops
    const box = (prefix, ret) => $('#' + prefix + (ret ? '_rstops' : '_stops'));
    function stops(prefix, ret) {
        return box(prefix, ret).find('.tk-stop').map(function () {
            return { id: $(this).find('.tk-stop-ap').val(), isNew: $(this).find('.tk-stop-new').val() === '1' };
        }).get().filter(s => s.id);
    }
    // the round trip (tickets/route-trip); without it on the page every route is one-way, as before
    const tripOf = prefix => $('#' + prefix + '_trip_type').val() === 'two-way' ? 'two-way' : 'one-way';
    const modeOf = prefix => $('#' + prefix + '_return_mode').val() === 'separate' ? 'separate' : 'same';
    const separate = prefix => tripOf(prefix) === 'two-way' && modeOf(prefix) === 'separate';

    function addStop(prefix, id, isNew, ret) {
        const b = box(prefix, ret);
        const row = $('<div class="tk-stop"><span class="k"></span><select class="tk-stop-ap" style="width:100%"></select>'
            + '<span class="tk-stop-kind"><button type="button" class="new" data-v="1">New ticket</button><button type="button" class="transit" data-v="0">Transit</button></span>'
            + '<input type="hidden" class="tk-stop-new" value="1"><button type="button" class="tk-stop-del" title="Remove"><i class="fas fa-xmark"></i></button></div>');
        const sel = row.find('.tk-stop-ap');
        sel.append(new Option('— Select airport —', ''));
        $('#' + prefix + '_from_airport_id option').each(function () { if (this.value) sel.append($(this).clone().prop('selected', false)); });
        sel.val(id ? String(id) : '');
        row.find('.tk-stop-new').val(isNew === false ? '0' : '1');
        b.append(row);
        if ($.fn.select2) sel.select2({ width: '100%', dropdownParent: b.closest('.tk-create-modal, .tk-modal, [id$="Modal"]').first().length ? b.closest('.tk-create-modal, .tk-modal, [id$="Modal"]').first() : $(document.body) });
        paint(prefix);
    }

    // snapshot / restore card values so adding a stop keeps what was typed
    function snap(prefix) {
        return $('#' + prefix + '_leg_cards .tk-leg-card').map(function () {
            const c = $(this);
            return { airline: c.find('[name$="[airline_id]"]').val(), vendor: c.find('[name$="[vendor_id]"]').val(), portal: c.find('[name$="[portal_id]"]').val(), cost: c.find('.tk-leg-cost').val() };
        }).get();
    }
    function buildCards(prefix, n, values) {
        const wrap = $('#' + prefix + '_leg_cards').empty();
        const tpl = document.getElementById(prefix + '_leg_tpl');
        if (!tpl) return;
        for (let i = 0; i < n; i++) {
            const html = tpl.innerHTML.replace(/__I__/g, i).replace(/__N__/g, i + 1);
            const card = $(html);
            wrap.append(card);
            const v = (values || [])[i] || {};
            card.find('[name$="[airline_id]"]').val(v.airline || '');
            card.find('[name$="[vendor_id]"]').val(v.vendor || '');
            card.find('[name$="[portal_id]"]').val(v.portal || '');
            card.find('.tk-leg-cost').val(v.cost == null ? '' : v.cost);
            // inside a host that names its prefix (Ticket Entry's "+" route modal, 2026-10-17) the
            // dropdowns open in that modal — it sits above the page; on Manage Tickets they open in
            // #createModal / #editModal, as the via stops do — on <body> they fell behind the overlay
            const host = wrap.closest('[data-tk-prefix]');
            const modal = host.length ? host : wrap.closest('.tk-create-modal, .tk-modal, [id$="Modal"]').first();
            if ($.fn.select2) card.find('.tk-leg-sel').select2(modal.length ? { width: '100%', dropdownParent: modal } : { width: '100%' });
        }
    }

    // one journey cut into tickets at its "new ticket" stops
    function cut(from, to, list) {
        const segs = []; let start = from, through = [];
        list.forEach(s => { if (s.isNew) { segs.push({ from: start, to: s.id, through }); start = s.id; through = []; } else through.push(s.id); });
        segs.push({ from: start, to, through });
        return segs;
    }

    // The route title follows the airports — DAC-ECN, DAC-BAH-ECN-DAC — while it is empty or still the
    // one written here; a title someone typed is never touched.
    function autoTitle(prefix, from, to, list, rlist) {
        const t = $('#' + prefix + '_title').length ? $('#' + prefix + '_title') : $('[data-tk-prefix="' + prefix + '"] #tkTitle');
        if (!t.length || !from || !to) return;
        const ids = [from].concat(list.map(s => s.id), [to]);
        if (tripOf(prefix) === 'two-way') ids.push(...(separate(prefix) ? rlist.map(s => s.id) : []), from);
        const title = ids.map(id => ap(prefix, id).code).join('-');
        const cur = String(t.val() || '').trim();
        if (cur === '' || cur === t.data('tkAuto')) { t.val(title); t.data('tkAuto', title); }
    }

    function paint(prefix) {
        const list = stops(prefix, false), rlist = stops(prefix, true);
        const rt = tripOf(prefix) === 'two-way', sep = separate(prefix);
        // the rows: numbers, which kind is lit, and the names that post
        [[false, 'route_stops'], [true, 'return_stops']].forEach(([ret, field]) => box(prefix, ret).find('.tk-stop').each(function (i) {
            const r = $(this), isNew = r.find('.tk-stop-new').val() === '1';
            r.find('.k').text(i + 1);
            r.find('.tk-stop-kind .new').toggleClass('on', isNew);
            r.find('.tk-stop-kind .transit').toggleClass('on', !isNew);
            // New ticket / Transit only once the stop has its airport (owner, 2026-10-18: "Not Selected,
            // Transit or New Ticket — need after select apply option"); its place is kept, so nothing moves
            r.find('.tk-stop-kind').toggleClass('tk-wait', !r.find('.tk-stop-ap').val());
            r.find('.tk-stop-ap').attr('name', field + '[' + i + '][airport_id]');
            r.find('.tk-stop-new').attr('name', field + '[' + i + '][new_ticket]');
            // a return's stops post only while the return is a separate ticket
            if (ret) r.find('.tk-stop-ap, .tk-stop-new').prop('disabled', !sep);
        }));
        // the trip switches and what they mean
        const wrap = $('[data-trip-prefix="' + prefix + '"]');
        wrap.find('.tk-trip-btn').each(function () { $(this).toggleClass('on', $(this).data('trip') === tripOf(prefix)); });
        wrap.find('.tk-return-btn').each(function () { $(this).toggleClass('on', $(this).data('mode') === modeOf(prefix)); });
        $('#' + prefix + '_return').prop('hidden', !rt);
        $('#' + prefix + '_return_sep').prop('hidden', !sep);
        const from = $('#' + prefix + '_from_airport_id').val(), to = $('#' + prefix + '_to_airport_id').val();
        const fc = from ? ap(prefix, from).code : 'From', tc = to ? ap(prefix, to).code : 'To';
        $('#' + prefix + '_return_note').text(sep
            ? 'The return is its own ticket, ' + tc + ' → ' + fc + ' — its own airline, seller and price on the cards below. Ticket Entry adds a card for it.'
            : 'One round-trip ticket ' + fc + ' ⇄ ' + tc + ' at the airline\'s round-trip fare. Ticket Entry sets Round Trip on its card and asks for the return flight.');

        $('#' + prefix + '_via_airport_id').val(list.length ? list[0].id : '');
        autoTitle(prefix, from, to, list, rlist);
        const on = list.length > 0 || sep;
        $('#' + prefix + '_via_legs').toggleClass('is-on', on);
        $('#' + prefix + 'Modal .tk-direct-only, #' + prefix + 'Form .tk-direct-only').toggleClass('tk-direct-hide', on);
        if (!on) { $('#' + prefix + '_leg_cards').empty(); return; }

        // cut the route into tickets at every "new ticket" stop; a separate return follows, To → From
        const segs = cut(from, to, list), outN = segs.length;
        if (sep) segs.push(...cut(to, from, rlist));

        if ($('#' + prefix + '_leg_cards .tk-leg-card').length !== segs.length) buildCards(prefix, segs.length, snap(prefix));
        segs.forEach((g, i) => {
            const path = [tag(ap(prefix, g.from))].concat(g.through.map(id => tag(ap(prefix, id)) + ' <span class="transit">transit</span>'), [tag(ap(prefix, g.to))]);
            $('#' + prefix + '_leg' + i + '_sector').html((i >= outN ? '<span class="transit" style="background:#f5f3ff;color:#6d28d9">return</span> ' : '') + path.join(arrow));
            // a ticket with a transit asks only its airline and price (owner, 2026-10-27) — no vendor / portal
            const seller = $('#' + prefix + '_leg_cards .tk-leg-card').eq(i).find('.tk-leg-seller');
            const transit = g.through.length > 0;
            seller.toggle(!transit);
            if (transit) seller.find('select').each(function () { if ($(this).val()) $(this).val('').trigger('change'); });
        });
        const all = [from].concat(list.map(s => s.id), [to], sep ? rlist.map(s => s.id).concat([from]) : (rt ? [from] : [])).map(id => tag(ap(prefix, id)));
        $('#' + prefix + '_via_title').html(all.join(' <span style="opacity:.6">·</span> '));
        $('#' + prefix + '_via_lbl').text(segs.length > 1 ? 'Total cost, ' + segs.length + ' tickets' : 'Ticket cost (one ticket, with transit)');
        total(prefix);
    }
    function total(prefix) {
        let t = 0;
        $('#' + prefix + '_leg_cards .tk-leg-cost').each(function () { t += Number($(this).val()) || 0; });
        $('#' + prefix + '_via_total').text('৳' + t.toLocaleString());
        $('#' + prefix + '_price').val(t.toFixed(2));
    }
    // a host may name its own prefix (data-tk-prefix — Ticket Entry's "+" route modal uses "qc"; the round
    // trip block names its own, data-trip-prefix); otherwise Manage Tickets' edit / create, as before
    const prefixOf = el => {
        const h = el.closest && el.closest('[data-tk-prefix]');
        if (h) return h.getAttribute('data-tk-prefix');
        const t = el.closest && el.closest('[data-trip-prefix]');
        if (t) return t.getAttribute('data-trip-prefix');
        return el.closest('[id^="edit"]') ? 'edit' : 'create';
    };

    $(document).on('click', '.tk-stop-add', function () { addStop($(this).data('prefix'), '', true, String($(this).data('ret') || '') === '1'); });
    $(document).on('click', '.tk-stop-del', function () { const p = prefixOf(this); $(this).closest('.tk-stop').remove(); paint(p); });
    $(document).on('click', '.tk-stop-kind button', function () {
        const p = prefixOf(this);
        $(this).closest('.tk-stop').find('.tk-stop-new').val($(this).data('v') === 1 || $(this).data('v') === '1' ? '1' : '0');
        paint(p);
    });
    $(document).on('click', '.tk-trip-btn', function () {
        const p = $(this).closest('[data-trip-prefix]').attr('data-trip-prefix');
        $('#' + p + '_trip_type').val($(this).data('trip') === 'two-way' ? 'two-way' : 'one-way');
        paint(p);
    });
    $(document).on('click', '.tk-return-btn', function () {
        const p = $(this).closest('[data-trip-prefix]').attr('data-trip-prefix');
        $('#' + p + '_return_mode').val($(this).data('mode') === 'separate' ? 'separate' : 'same');
        paint(p);
    });
    $(document).on('change', '.tk-stop-ap, [id$="_from_airport_id"], [id$="_to_airport_id"]', function () {
        const p = this.id && this.id.startsWith('edit_') ? 'edit' : prefixOf(this);
        if ($('#' + p + '_via_legs').length) paint(p);
    });
    // one seller per ticket: vendor OR portal
    $(document).on('change', '.tk-leg-card [name$="[vendor_id]"], .tk-leg-card [name$="[portal_id]"]', function () {
        if (!$(this).val()) return;
        const other = $(this).closest('.tk-leg-card').find(this.name.endsWith('[vendor_id]') ? '[name$="[portal_id]"]' : '[name$="[vendor_id]"]');
        if (other.val()) other.val('').trigger('change');
    });
    $(document).on('input', '.tk-leg-cost', function () { total(prefixOf(this)); });

    function setTrip(prefix, trip, mode) {
        $('#' + prefix + '_trip_type').val(trip === 'two-way' ? 'two-way' : 'one-way');
        $('#' + prefix + '_return_mode').val(mode === 'separate' ? 'separate' : 'same');
    }
    return {
        // one stop added from outside (the PDF import fills its transits, 2026-10-17)
        add(prefix, id, isNew) { addStop(prefix, id, isNew, false); },
        // the round trip set from outside (the PDF import: a round-trip ticket, 2026-10-18)
        trip(prefix, trip, mode) { setTrip(prefix, trip, mode); paint(prefix); },
        reset(prefix) {
            box(prefix, false).empty();
            box(prefix, true).empty();
            $('#' + prefix + '_leg_cards').empty();
            setTrip(prefix, 'one-way', 'same');
            const t = $('#' + prefix + '_title').length ? $('#' + prefix + '_title') : $('[data-tk-prefix="' + prefix + '"] #tkTitle');
            t.removeData('tkAuto');
            paint(prefix);
        },
        // legs = Ticket::legsArray(): each ticket with its transit stops; a stop between two tickets is
        // "new ticket"; legs marked direction 'return' are a separate return's tickets
        set(prefix, viaId, legs, tripType) {
            box(prefix, false).empty();
            box(prefix, true).empty();
            $('#' + prefix + '_leg_cards').empty();
            legs = legs || [];
            const out = legs.filter(l => l.direction !== 'return'), back = legs.filter(l => l.direction === 'return');
            setTrip(prefix, tripType, back.length ? 'separate' : 'same');
            out.forEach((l, k) => {
                (l.stops || []).forEach(s => addStop(prefix, s.id, false, false));
                if (k < out.length - 1) addStop(prefix, l.to_id, true, false);
            });
            back.forEach((l, k) => {
                (l.stops || []).forEach(s => addStop(prefix, s.id, false, true));
                if (k < back.length - 1) addStop(prefix, l.to_id, true, true);
            });
            if (!legs.length && viaId) addStop(prefix, viaId, true, false);
            buildCards(prefix, Math.max(legs.length, box(prefix, false).find('.tk-stop').length ? 1 : 0),
                legs.map(l => ({ airline: l.airline_id, vendor: l.vendor_id, portal: l.portal_id, cost: l.cost })));
            paint(prefix);
        }
    };
})();
</script>
