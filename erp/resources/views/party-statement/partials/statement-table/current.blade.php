{{-- The statement table as it was before the 2026-10-13 redesign — still the
     default. Moved here unchanged so other designs can sit beside it. --}}
    {{-- Ledger table ── --}}
    <table class="ledger">
        <colgroup>
            <col style="width:8%">
            <col style="width:15%">
            <col style="width:25%">
            <col style="width:9%">
            <col style="width:10%">
            <col style="width:10%">
            <col style="width:15%">
        </colgroup>
        <thead>
            <tr>
                <th>Date</th>
                <th>Passenger</th>
                <th>Description</th>
                <th>Type</th>
                <th class="r">Debit (৳)</th>
                <th class="r">Credit (৳)</th>
                <th class="r">Balance (৳)</th>
            </tr>
        </thead>
        <tbody>
            @if($openingBalance != 0 && ! $selection)
            <tr class="opening-row">
                <td class="date">{{ $dateFrom ? \Carbon\Carbon::parse($dateFrom)->format('d M y') : '' }}</td>
                <td>—</td>
                <td colspan="2">Opening Balance Brought Forward</td>
                <td class="r">—</td>
                <td class="r">—</td>
                <td class="r mono">{{ number_format(abs($openingBalance), 2) }} Dr</td>
            </tr>
            @endif

            @forelse($transactions as $t)
            @php
                $badgeCls = match($t->type) {
                    'party_payment','party_payment_reversal','customer_advance_received_reversal','supplier_advance_paid_reversal','bank_transfer' => 'badge-payment',
                    'customer_advance_refund','customer_advance_refund_reversal' => 'badge-payment',
                    'customer_service_refund','customer_service_refund_reversal' => 'badge-payment',
                    'party_invoice','ticket_sale','sale' => 'badge-invoice',
                    default => 'badge-default'
                };
                $badgeLabel = match($t->type) {
                    'contract_file_client'=>'File process', 'contract_file'=>'File process cost', 'contract_file_service_cost'=>'File service cost',
                    'contract_file_client_adjustment'=>'File process adjustment', 'contract_file_vendor_adjustment'=>'File cost adjustment',
                    'party_payment' => 'Payment',
                    'party_payment_reversal' => 'Payment reversal',
                    'customer_advance_refund' => 'Advance returned',
                    'customer_advance_refund_reversal' => 'Advance return reversal',
                    'customer_service_refund' => 'Service refund paid',
                    'customer_service_refund_reversal' => 'Service refund reversal',
                    'customer_advance_received_reversal' => 'Customer advance reversal',
                    'supplier_advance_paid_reversal' => 'Supplier advance reversal',
                    'contract_flight_service_cost' => 'Flight service cost',
                    'contract_flight_passenger_agent' => 'Flight process',
                    'contract_flight_passenger_reissue' => 'Reissue process',
                    'contract_flight_passenger_agent_reversal' => 'Package reversal',
                    'contract_flight_price_revision' => 'Package revised',
                    'vendor_refund' => 'Refund received',
                    'contract_flight_airline_penalty' => 'Airline penalty',
                    'contract_flight_fare_difference' => 'Fare difference',
                    'party_invoice' => 'Invoice',
                    'bank_transfer' => 'Transfer',
                    'ticket_sale'   => 'Ticket',
                    default => ucfirst(str_replace('_',' ',$t->type))
                };
                $bal = (float)$t->balance;
            @endphp
            @php
                $chargePax   = (in_array($t->type, ['contract_flight_passenger_agent', 'contract_flight_passenger_reissue'], true) && $t->invoice_id)
                    ? ($flightChargePassengers[$t->invoice_id] ?? null)
                    : null;
                $beneficiary = $chargePax?->passportHolder;
                // Flight-process rows: show the first leg's travel date, not the
                // payment date — the agent cares when the passenger actually flies.
                $firstLegDate = $chargePax
                    ? optional($chargePax->ticketPurchases->sortBy('leg_sequence')->first()?->legs->first())->travel_date
                    : null;
                $rowDate = $firstLegDate ?: $t->payment_date;
            @endphp
            <tr>
                <td class="date">{{ \Carbon\Carbon::parse($rowDate)->format('d M y') }}</td>
                <td>
                    @if($beneficiary)
                    <div class="voucher-ben">
                        <span class="vb-name">{{ $beneficiary->name ?? '—' }}</span>
                        <span class="vb-pass">{{ $beneficiary->passport_no ?? '—' }}</span>
                    </div>
                    @endif
                </td>
                <td>
                    @if($chargePax)
                        @php
                            // One line per linked ticket (DAC→IST, IST→ECN, ...) — each
                            // leg is its own airline (Ticket::legsArray() is per-sector,
                            // not one airline for the whole route), matched to this
                            // purchase by leg_sequence. Falls back to the flight's own
                            // nominal route when no ticket is linked yet (e.g. mid-reissue).
                            $legLines = [];
                            foreach ($chargePax->ticketPurchases->sortBy('leg_sequence') as $tp) {
                                $legsArr = $tp->ticket?->legsArray() ?: [];
                                $legInfo = $legsArr[max(0, ($tp->leg_sequence ?: 1) - 1)] ?? null;
                                $airlineName = $legInfo['airline'] ?? $tp->ticket?->airline?->name ?? null;
                                // "Gulf Air (GF)" -> "GF" — the code alone is enough
                                // here and leaves room for the date not to get
                                // ellipsis-truncated out of the line entirely.
                                $airline = ($airlineName && preg_match('/\(([^)]+)\)\s*$/', $airlineName, $m)) ? $m[1] : $airlineName;
                                $date = optional($tp->legs->first())->travel_date;

                                // Flight numbers are stored per-leg, same order as the
                                // route's legs — [GF251, GF45, PC1982] for a 3-leg route.
                                // Match this ticket's own leg_sequence, not always index 0,
                                // else every ticket line shows the first flight's number.
                                $flightNos = $chargePax->contractFlight?->airline_flight_no ?: [];
                                $flightNos = is_array($flightNos) ? $flightNos : explode(',', $flightNos);
                                $legFlightNo = trim($flightNos[max(0, ($tp->leg_sequence ?: 1) - 1)] ?? ($flightNos[0] ?? ''));

                                $meta = [$tp->ticket_no ?: '—'];
                                if ($airline) $meta[] = $airline;
                                if ($legFlightNo) $meta[] = $legFlightNo;

                                $legLines[] = [
                                    'from' => $legInfo['from_code'] ?? $tp->ticket?->from_airport?->code ?? '—',
                                    'to' => $legInfo['to_code'] ?? $tp->ticket?->to_airport?->code ?? '—',
                                    'meta' => implode(' · ', $meta),
                                ];

                            }
                            @endphp
                            @if(count($legLines))
                            @foreach($legLines as $leg)
                            <div class="desc-leg">
                                <span class="desc-leg-route">{{ $leg['from'] }}→{{ $leg['to'] }}</span>
                                <span class="desc-leg-meta">{{ $leg['meta'] }}</span>
                            </div>
                            @endforeach
                        @else
                        <span class="desc-flight">{{ $chargePax->contractFlight?->flight_number ?: 'Contract flight' }}</span>
                        @if($chargePax->contractFlight?->departure_at)
                        <span class="desc-when">{{ $chargePax->contractFlight->departure_at->format('d M Y, h:i A') }}</span>
                        @endif
                        @endif
                        <div class="desc-tags">
                            @if($chargePax->flightCategory?->name)
                            <span class="desc-category">{{ $chargePax->flightCategory->name }}</span>
                            @endif
                            <span class="stage-badge">{{ \App\Models\ContractFlightPassenger::stageLabel($chargePax->stage) }}</span>
                            @if($chargePax->exception_flag)
                            <span class="exception-badge">&#9888; {{ \App\Models\ContractFlightPassenger::EXCEPTION_FLAGS[$chargePax->exception_flag] ?? ucfirst(str_replace('_',' ',$chargePax->exception_flag)) }}</span>
                            @endif
                        </div>
                    @else
                    {{ Str::limit($t->display_remarks, 60) }}
                    @endif
                </td>
                <td><span class="badge {{ $badgeCls }}">{{ $badgeLabel }}</span></td>
                <td class="r {{ $t->debit  > 0 ? 'debit'  : '' }}">{{ $t->debit  > 0 ? number_format($t->debit,  2) : '—' }}</td>
                <td class="r {{ $t->credit > 0 ? 'credit' : '' }}">{{ $t->credit > 0 ? number_format($t->credit, 2) : '—' }}</td>
                <td class="r {{ $bal > 0 ? 'balance-dr' : ($bal < 0 ? 'balance-cr' : '') }}">
                    {{ number_format(abs($bal), 2) }} {{ $bal > 0 ? 'Dr' : ($bal < 0 ? 'Cr' : '') }}
                </td>
            </tr>
            @if($t->type === 'ticket_sale' && $t->invoice_id && isset($ticketSaleItems[$t->invoice_id]))
                @foreach($ticketSaleItems[$t->invoice_id]->items as $item)
                @php
                    $tp    = $item->ticketPurchase;
                    $from  = $tp?->ticket?->from_airport?->code;
                    $to    = $tp?->ticket?->to_airport?->code;
                    $route = ($from || $to) ? (($from ?: '—') . ' → ' . ($to ?: '—')) : '—';
                @endphp
                <tr class="sub-row">
                    <td colspan="3">
                        &#8618; <span class="mono">{{ $tp?->ticket_no ?? '—' }}</span>
                        &middot; {{ $tp?->passportHolder?->name ?? '—' }}
                        &middot; {{ $tp?->ticket?->title ?? '—' }}
                        &middot; {{ $route }}
                    </td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td class="r mono">{{ number_format((float) $item->price, 2) }}</td>
                </tr>
                @endforeach
            @endif
            @empty
            <tr>
                <td colspan="7" style="text-align:center;padding:20px;color:#94a3b8;">No transactions found for this period.</td>
            </tr>
            @endforelse
        </tbody>
        <!-- <tfoot>
            <tr>
                <td colspan="4">
                    Closing Balance — {{ $dateTo ? \Carbon\Carbon::parse($dateTo)->format('d M Y') : now()->format('d M Y') }}
                </td>
                <td class="r">৳{{ number_format($totalDebit, 2) }}</td>
                <td class="r">৳{{ number_format($totalCredit, 2) }}</td>
                <td class="r closing-cell">
                    ৳{{ number_format(abs($closingBalance), 2) }}
                    {{ $closingBalance > 0 ? 'Dr' : ($closingBalance < 0 ? 'Cr' : '') }}
                    <span style="display:block; font-size:8px; font-weight:600; text-transform:uppercase; letter-spacing:.05em; opacity:.8; margin-top:2px;">{{ $closingLabel }}</span>
                </td>
            </tr>
        </tfoot> -->
    </table>
