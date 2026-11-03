{{-- Quick-create Ticket Route ("+ New Route" beside the route select in the
     Add / Edit Flight modals). Shared by Flights & Passengers and the Flight
     Dashboard, which both host the Add Flight modal (owner, 2026-09-21).
     Expects jQuery, select2 and SweetAlert2 on the page and the tkr-overlay
     markup from contract-flights.create-modal. --}}
<script>
        // ─── Quick-create Ticket Route (mirrors visa-processing's quick passport-holder create) ───
        const tkrStoreUrl = "{{ route('role.tickets.store', ['role' => $role]) }}";
        let _tkrTargetSelectId = null;

        function openTicketRouteModal(selectId) {
            _tkrTargetSelectId = selectId;
            ['tkrTitle'].forEach(id => { document.getElementById(id).value = ''; });
            // .val('') alone doesn't refresh a select2 widget's own display —
            // it needs the trigger so the box doesn't keep showing the last pick.
            $('#tkrFromAirport, #tkrToAirport, #tkrAirline, #tkrVendor, #tkrPortal').val('').trigger('change');
            $('#tkrPrice').val(0);
            $('#tkrQty').val(0);
            $('#tkrErr').hide();
            $('#tkrOverlay').addClass('open');
        }

        function closeTicketRouteModal() {
            $('#tkrOverlay').removeClass('open');
            _tkrTargetSelectId = null;
        }

        // Backdrop click deliberately does NOT close this (owner, 2026-09-22).
        // Only the ✕ / Cancel closes it, so a stray click beside the box
        // cannot discard a half-typed route.

        function submitTicketRouteModal() {
            const err = $('#tkrErr');
            const saveBtn = $('#tkrSaveBtn');
            err.hide();

            const title = $('#tkrTitle').val().trim();
            const airlineId = $('#tkrAirline').val();
            const vendorId = $('#tkrVendor').val();
            const portalId = $('#tkrPortal').val();

            if (!title) { err.text('Ticket title is required.').show(); return; }
            if (!airlineId) { err.text('Please select an airline.').show(); return; }
            if (!vendorId && !portalId) { err.text('Please select at least a vendor or portal.').show(); return; }

            const payload = {
                title: title,
                airline_id: airlineId,
                from_airport_id: $('#tkrFromAirport').val(),
                to_airport_id: $('#tkrToAirport').val(),
                vendor_id: vendorId,
                portal_id: portalId,
                price: $('#tkrPrice').val() || 0,
                qty: $('#tkrQty').val() || 0,
                status: 1,
            };

            saveBtn.prop('disabled', true).text('Saving…');

            $.ajax({
                url: tkrStoreUrl,
                method: 'POST',
                data: payload,
                success: function(res) {
                    saveBtn.prop('disabled', false).html('💾 Save Route');
                    if (!res.success) {
                        err.text(res.message || 'Failed to save.').show();
                        return;
                    }
                    const t = res.data;
                    const fromCode = t.from_airport ? t.from_airport.code : '';
                    const toCode = t.to_airport ? t.to_airport.code : '';
                    const airlineName = t.airline ? t.airline.name : '';
                    let label = t.title;
                    if (airlineName) label += ' — ' + airlineName;
                    if (fromCode && toCode) label += ' (' + fromCode + ' → ' + toCode + ')';

                    // Add the new route to BOTH create & edit ticket-route selects
                    $('.ticket-route-select').each(function() {
                        if (!$(this).find('option[value="' + t.id + '"]').length) {
                            $(this).append(new Option(label, t.id, false, false));
                        }
                    });

                    // Auto-select in the triggering dropdown
                    if (_tkrTargetSelectId) {
                        $('#' + _tkrTargetSelectId).val(t.id).trigger('change');
                    }

                    closeTicketRouteModal();
                    Swal.fire({ icon: 'success', title: 'Added!', text: 'Ticket route saved.', timer: 1500, showConfirmButton: false });
                },
                error: function(xhr) {
                    saveBtn.prop('disabled', false).html('💾 Save Route');
                    err.text(xhr.responseJSON?.message || 'Server error. Please try again.').show();
                }
            });
        }
</script>
