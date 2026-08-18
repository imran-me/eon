<script>
    const flightPresetMatchUrl = @json(route('role.flight-price-presets.match', ['role' => $role]));
    const flightPassengerStoreUrl = @json(route('role.passport-holder.store', ['role' => $role]));
    let flightPassengerTarget = null;
    let hydratingFlight = false;
    const baseFlightDetails = window.viewFlight;
    window.viewFlight = function (id) {
        if (typeof baseFlightDetails === 'function') baseFlightDetails(id);
        const flight = mfFlights.find(item => String(item.id) === String(id));
        if (!flight) return;
        $('#view_ticket_class').text((flight.ticket_class || 'economy').replace(/\b\w/g, c => c.toUpperCase()));
        $('#view_arrival').text(flight.arrival_at ? new Date(flight.arrival_at).toLocaleString() : '-');
        $('#view_boarding_officer').text(flight.boarding_officer?.user?.name || '-');
        $('#view_immigration_officer').text(flight.immigration_officer?.user?.name || '-');
        $('#view_passenger_count').text((flight.passengers || []).length);
        $('#view_sale_price').text('BDT ' + Number(flight.sale_price_per_pax || 0).toLocaleString());
    };

    $(function () {
        $(document).on('pointerdown', '.edit-btn', function () {
            hydratingFlight = true;
        });
        ['create', 'edit'].forEach(function (prefix) {
            const parent = $('#' + prefix + 'Modal');
            $('#' + prefix + '_pricing_airline_id, #' + prefix + '_boarding_officer_id, #' + prefix + '_immigration_officer_id')
                .select2({ dropdownParent: parent, width: '100%' });
            $('#' + prefix + '_passenger_ids').select2({
                dropdownParent: parent,
                width: '100%',
                placeholder: 'Select passport holders...'
            });
        });

        $(document).on('change', '.pricing-airline, [id$="_ticket_class"], [id$="_flight_category_id"], [id$="_flight_category_type_id"]', function () {
            const prefix = this.id.startsWith('edit_') ? 'edit' : 'create';
            if ($(this).hasClass('pricing-airline')) filterFlightResources(prefix);
            if (!hydratingFlight) loadFlightPreset(prefix);
        });
        $(document).on('change', '.ticket-route-select', function () {
            const prefix = this.id.startsWith('edit_') ? 'edit' : 'create';
            const airlineId = $(this).find(':selected').data('airline-id');
            if (airlineId) $('#' + prefix + '_pricing_airline_id').val(String(airlineId)).trigger('change');
        });
        $(document).on('change input', '.price-input, [id$="_seats_sold"]', function () {
            calculateFlightPrice(this.id.startsWith('edit_') ? 'edit' : 'create');
        });
        $(document).on('change', '.passenger-select', function () {
            const prefix = this.id.startsWith('edit_') ? 'edit' : 'create';
            $('#' + prefix + '_seats_sold').val(($(this).val() || []).length);
            calculateFlightPrice(prefix);
        });
        $(document).on('change', '#createForm input[name="handling_type"], #editeForm input[name="handling_type"]', function () {
            const prefix = $(this).closest('form').attr('id') === 'editeForm' ? 'edit' : 'create';
            applyFlightHandling(prefix);
            if (!hydratingFlight) loadFlightPreset(prefix);
        });

        $('.create-btn').on('click', function () {
            hydratingFlight = true;
            $('#create_pricing_airline_id, #create_boarding_officer_id, #create_immigration_officer_id, #create_passenger_ids').val(null).trigger('change');
            $('#create_ticket_class').val('economy');
            $('#createForm .price-input').val(0);
            hydratingFlight = false;
            applyFlightHandling('create');
            calculateFlightPrice('create');
        });

        $('.edit-btn').on('click', function () {
            const flight = mfFlights.find(item => String(item.id) === String($(this).data('item_id')));
            if (!flight) return;
            hydratingFlight = true;
            $('#edit_pricing_airline_id').val(String(flight.ticket?.airline_id || flight.airline?.id || '')).trigger('change');
            $('#edit_ticket_class').val(flight.ticket_class || 'economy');
            $('#edit_boarding_officer_id').val(flight.boarding_officer_id || '').trigger('change');
            $('#edit_immigration_officer_id').val(flight.immigration_officer_id || '').trigger('change');
            $('#edit_ticket_cost_per_pax').val(flight.ticket_cost_per_pax || 0);
            $('#edit_manpower_cost_per_pax').val(flight.manpower_cost_per_pax || 0);
            $('#edit_boarding_cost_per_pax').val(flight.boarding_cost_per_pax || 0);
            $('#edit_immigration_cost_per_pax').val(flight.immigration_cost_per_pax || 0);
            $('#edit_sale_price_per_pax').val(flight.sale_price_per_pax || 0);
            $('#edit_arrival_at').val(dateTimeLocalValue(flight.arrival_at));
            $('#edit_passenger_ids').val((flight.passengers || []).map(row => String(row.passport_holder_id))).trigger('change');
            hydratingFlight = false;
            applyFlightHandling('edit');
            calculateFlightPrice('edit');
        });
    });

    function loadFlightPreset(prefix) {
        const airlineId = $('#' + prefix + '_pricing_airline_id').val();
        if (!airlineId) return;
        $.get(flightPresetMatchUrl, {
            airline_id: airlineId,
            flight_category_id: $('#' + prefix + '_flight_category_id').val(),
            flight_category_type_id: $('#' + prefix + '_flight_category_type_id').val(),
            ticket_class: $('#' + prefix + '_ticket_class').val() || 'economy',
            handling_type: $('#' + prefix + 'Form input[name="handling_type"]:checked').val()
                || $('#editeForm input[name="handling_type"]:checked').val()
        }).done(function (response) {
            if (!response.success || !response.data) {
                $('#' + prefix + '_preset_hint').text(response.message || 'No active pricing preset matched.');
                return;
            }
            const p = response.data;
            $('#' + prefix + '_ticket_cost_per_pax').val(p.ticket_cost);
            $('#' + prefix + '_manpower_cost_per_pax').val(p.manpower_cost);
            $('#' + prefix + '_boarding_cost_per_pax').val(p.boarding_cost);
            $('#' + prefix + '_immigration_cost_per_pax').val(p.immigration_cost);
            $('#' + prefix + '_sale_price_per_pax').val(p.sale_price);
            $('#' + prefix + '_preset_hint').text('Pricing preset loaded.');
            calculateFlightPrice(prefix);
        });
    }

    function dateTimeLocalValue(value) {
        if (!value) return '';
        const date = new Date(value);
        date.setMinutes(date.getMinutes() - date.getTimezoneOffset());
        return date.toISOString().slice(0, 16);
    }

    function filterFlightResources(prefix) {
        // Airline metadata is informational; selecting a route updates the pricing airline.
        $('#' + prefix + '_ticket_id, #' + prefix + '_boarding_officer_id, #' + prefix + '_immigration_officer_id')
            .find('option')
            .prop('disabled', false)
            .end()
            .trigger('change.select2');
    }

    function applyFlightHandling(prefix) {
        const form = prefix === 'edit' ? '#editeForm' : '#createForm';
        const immigrationWise = $(form + ' input[name="handling_type"]:checked').val() === 'immigration_wise';
        const officer = $('#' + prefix + '_immigration_officer_id');
        officer.prop('disabled', !immigrationWise);
        if (!immigrationWise) officer.val(null).trigger('change.select2');
        $('#' + prefix + '_immigration_cost_per_pax').prop('disabled', !immigrationWise);
        if (!immigrationWise) $('#' + prefix + '_immigration_cost_per_pax').val(0);
        calculateFlightPrice(prefix);
    }

    function calculateFlightPrice(prefix) {
        const number = id => Number($('#' + prefix + '_' + id).val()) || 0;
        const cost = number('ticket_cost_per_pax') + number('manpower_cost_per_pax')
            + number('boarding_cost_per_pax') + number('immigration_cost_per_pax');
        const sale = number('sale_price_per_pax');
        const sold = number('seats_sold');
        $('#' + prefix + '_total_cost_per_pax').val(cost.toFixed(2));
        $('#' + prefix + '_profit_per_pax').val((sale - cost).toFixed(2));
        $('#' + prefix + '_margin').val((sale > 0 ? ((sale - cost) / sale) * 100 : 0).toFixed(1) + '%');
        $('#' + prefix + '_cost_price').val((cost * sold).toFixed(2));
        $('#' + prefix + '_revenue').val((sale * sold).toFixed(2));
    }

    function validateFlightForm(prefix) {
        let isValid = true;
        const form = prefix === 'edit' ? $('#editeForm') : $('#createForm');
        form.find('.error-message').addClass('hidden');

        const requiredFields = [
            ['flight_category_id', 'Please select a flight category.'],
            ['ticket_id', 'Please select a ticket route.'],
            ['airline_flight_no', 'Flight number is required.'],
            ['departure_at', 'Departure date and time are required.'],
            ['boarding_officer_id', 'Please select a boarding officer.'],
            ['total_seats', 'Total seats are required.'],
            ['sale_price_per_pax', 'Sale price is required.']
        ];

        requiredFields.forEach(function (field) {
            const value = $('#' + prefix + '_' + field[0]).val();
            if (value === null || String(value).trim() === '') {
                $('#' + prefix + '_' + field[0] + '_msg').removeClass('hidden').text(field[1]);
                isValid = false;
            }
        });

        const formSelector = prefix === 'edit' ? '#editeForm' : '#createForm';
        const handlingType = $(formSelector + ' input[name="handling_type"]:checked').val();
        if (handlingType === 'immigration_wise' && !$('#' + prefix + '_immigration_officer_id').val()) {
            $('#' + prefix + '_immigration_officer_id_msg').removeClass('hidden');
            isValid = false;
        }

        const departure = $('#' + prefix + '_departure_at').val();
        const arrival = $('#' + prefix + '_arrival_at').val();
        if (departure && arrival && new Date(arrival) < new Date(departure)) {
            $('#' + prefix + '_arrival_at_msg').removeClass('hidden');
            isValid = false;
        }

        const totalSeats = Number($('#' + prefix + '_total_seats').val() || 0);
        const seatsSold = Number($('#' + prefix + '_seats_sold').val() || 0);
        if (seatsSold < 0 || seatsSold > totalSeats) {
            $('#' + prefix + '_seats_sold_msg').removeClass('hidden');
            isValid = false;
        }

        const salePrice = Number($('#' + prefix + '_sale_price_per_pax').val());
        if (salePrice < 0) {
            $('#' + prefix + '_sale_price_per_pax_msg').removeClass('hidden');
            isValid = false;
        }

        return isValid;
    }

    function openFlightPassengerModal(targetId) {
        flightPassengerTarget = targetId;
        $('#flightPassengerName, #flightPassengerPassport, #flightPassengerNationality, #flightPassengerPhone').val('');
        $('#flightPassengerCategory').val('');
        $('#flightPassengerType').val('contract_flight');
        $('#flightPassengerError').hide();
        $('#flightPassengerOverlay').addClass('open');
    }
    function closeFlightPassengerModal() {
        $('#flightPassengerOverlay').removeClass('open');
        flightPassengerTarget = null;
    }
    function saveFlightPassenger() {
        const button = $('#flightPassengerSave').prop('disabled', true);
        $('#flightPassengerError').hide();
        $.ajax({
            url: flightPassengerStoreUrl,
            method: 'POST',
            headers: { Accept: 'application/json' },
            data: {
                name: $('#flightPassengerName').val(),
                passport_no: $('#flightPassengerPassport').val(),
                nationality: $('#flightPassengerNationality').val(),
                phone: $('#flightPassengerPhone').val(),
                category_id: $('#flightPassengerCategory').val(),
                type: $('#flightPassengerType').val(),
                status: 1
            }
        }).done(function (response) {
            if (!response.success) {
                $('#flightPassengerError').text(response.message || 'Unable to save passenger.').show();
                return;
            }
            const item = response.data;
            $('.passenger-select').each(function () {
                if (!$(this).find('option[value="' + item.id + '"]').length) {
                    $(this).append(new Option(item.name + ' - ' + item.passport_no, item.id));
                }
            });
            if (flightPassengerTarget) {
                const select = $('#' + flightPassengerTarget);
                select.val((select.val() || []).concat(String(item.id))).trigger('change');
            }
            closeFlightPassengerModal();
        }).fail(function (xhr) {
            $('#flightPassengerError').text(xhr.responseJSON?.message || 'Unable to save passenger.').show();
        }).always(function () {
            button.prop('disabled', false);
        });
    }
</script>
