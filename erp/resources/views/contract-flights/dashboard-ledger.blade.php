{{-- The passenger ledger — opened by clicking a flight card or a row of the
     flights table. The Manage Banks account layout (owner, 2026-09-16):
     the control strip above, then the bank's account card (band 1: who;
     band 2: the figures rail), then the bank's filter bar on the table.
     Styles: dashboard-ledger-styles. Script: dashboard-ledger-script, which
     writes every value; the markup only holds the shape and the labels. --}}
<section id="fdLedger" hidden style="margin-top:24px; scroll-margin-top:95px;" aria-label="Flight passenger ledger">
    {{-- The bank card has no back button, so the way back lives on the
         page's own control strip — the same 30px tools that sit above the
         card wall — with the reload beside it. --}}
    <div class="fd-bar">
        <button type="button" class="fd-tool" onclick="closeFlightLedger()" aria-label="Back to active flights" title="Back to active flights"><i class="fas fa-arrow-left"></i></button>
        <h3 id="fdLedgerTitle" tabindex="-1">Passenger Ledger</h3>
        <span class="fd-count" id="fdLedgerCount" aria-live="polite"></span>
        {{-- Status: show / hide every passenger's journey strip (owner, 2026-09-21).
             Remembered per browser; default collapsed. --}}
        <button type="button" class="fdl-status-btn" id="fdlStatusBtn" style="margin-left:auto;" onclick="toggleLedgerJourneys()" aria-pressed="false" title="Show every passenger journey"><i class="fas fa-route"></i> <span>Show all stages</span></button>
        <button type="button" class="fd-tool" onclick="reloadPassengerLedger()" aria-label="Reload passengers" title="Reload passengers"><i class="fas fa-rotate"></i></button>
    </div>

    <div class="fdl-header">
        {{-- Band 1 — WHO. Three zones split by hairlines, then the actions. --}}
        <div class="fdl-head-top">
            <div class="fdl-zone fdl-zone-id">
                {{-- The mark plate. All three layers are always in the DOM and
                     paintLedgerMark() flips between them: the airline's mark,
                     its initials, or the plane glyph when no airline is on the
                     batch. A 404'd mark falls back to the initials — never a
                     broken image. No src= until script sets one. --}}
                <span class="fdl-avatar is-mono" id="fdlAvatar">
                    <span class="fdl-crop"><img id="fdlMark" alt="" onerror="this.closest('.fdl-avatar').classList.add('is-mono');"></span>
                    <span class="fdl-mono" id="fdlInitials"></span>
                    <i class="fas fa-plane" aria-hidden="true"></i>
                    {{-- A via route on two airlines: the other leg's mark as a
                         small white badge on the tile's corner — the card's rule. --}}
                    <span class="fdl-badge" id="fdlBadge" hidden><img alt="" onerror="this.closest('.fdl-badge').classList.add('is-mono');"><span class="fdl-badge-mono"></span></span>
                </span>
                <div class="fdl-idcol">
                    <h2 class="fdl-name" id="fdlName">—</h2>
                    <div class="fdl-line"><i class="fas fa-location-dot"></i><span id="fdlRoute">—</span></div>
                    <div class="fdl-line"><span class="fdl-k">Batch</span><span class="fdl-ac" id="fdlNumber">—</span><span class="fdl-type" id="fdlStatus">—</span></div>
                    {{-- Airline flight numbers under the batch (owner, 2026-10-01),
                         not on the route line where they were always cut off. --}}
                    <div class="fdl-line fdl-flightno" id="fdlFlightNoLine" hidden onclick="if (this.classList.contains('is-unset')) document.getElementById('fdlEditBtn')?.click()"><i class="fas fa-plane-up"></i><span id="fdlFlightNo"></span></div>
                </div>
            </div>

            {{-- Every agent on a batch billed to several (owner, 2026-10-01).
                 Only painted when there are two or more; one agent is already
                 said in full beside it. --}}
            <div class="fdl-zone fdl-zone-agents" id="fdlAgentsZone" hidden>
                <span class="fdl-lbl">Agents <span class="fdl-qual" id="fdlAgentsCount"></span></span>
                <div class="fdl-agents" id="fdlAgents"></div>
            </div>

            <div class="fdl-zone fdl-zone-co">
                <div>
                    <span class="fdl-lbl">Agent</span>
                    <div class="fdl-co" style="margin-top:6px;">
                        <span class="ci"><i class="fas fa-user-tie"></i></span>
                        <span class="fdl-co-name" id="fdlAgent">—</span>
                    </div>
                </div>
                <div>
                    {{-- The countdown rides in the label's qualifier, where the
                         bank puts "(in)" / "(out)". --}}
                    <span class="fdl-lbl">Departure <span class="fdl-qual" id="fdlDepQual"></span></span>
                    <span class="fdl-fig" id="fdlDeparture">—</span>
                </div>
            </div>

            <div class="fdl-zone fdl-zone-last">
                <span class="fdl-lbl">Vendor Due</span>
                <div class="fdl-last-row">
                    <span class="fdl-pill fdl-pill-none" id="fdlVendorPill"><i class="fas fa-minus"></i><span id="fdlVendorDir">None</span></span>
                    <span class="fdl-last-amt" id="fdlVendorDue">—</span>
                </div>
                <div class="fdl-last-note" id="fdlVendorName">—</div>
                <div class="fdl-last-when" id="fdlVendorWhen">—</div>
            </div>

            {{-- Green is money in (the agent pays us), red is money out (we pay
                 the vendor) — the bank's Deposit / Withdraw inks, kept to the
                 same meaning. Links, not buttons: each opens another screen.
                 An agent or vendor that is not on record disables its link. --}}
            <div class="fdl-actions">
                <a href="#" class="fdl-btn fdl-btn-in" id="fdlAgentBtn"><i class="fas fa-user-tie"></i> Agent</a>
                <a href="#" class="fdl-btn fdl-btn-out" id="fdlVendorBtn"><i class="fas fa-handshake"></i> Vendor</a>
                <a href="#" class="fdl-btn fdl-btn-alt" id="fdlEditBtn"><i class="fas fa-pencil-alt"></i> Edit flight</a>
                <a href="#" class="fdl-btn fdl-btn-alt" id="fdlManageBtn"><i class="fas fa-list"></i> Manage</a>
                <a href="#" class="fdl-btn fdl-btn-alt" id="fdlBoardBtn"><i class="fas fa-table-columns"></i> Board</a>
            </div>
        </div>

        {{-- Band 2 — HOW MUCH. Readiness left to right, then the money in the
             order it flows: what the agent is charged, less what the trip
             costs, ending on the profit the rail builds towards. Whole batch,
             always — the filter bar below narrows the rows, not these. --}}
        <div class="fdl-meta">
            <div>
                <span class="fdl-lbl"><i class="fas fa-users"></i>Passengers</span>
                <span class="fdl-fig" id="fdlPax">—</span>
            </div>
            <div class="is-in">
                <span class="fdl-lbl"><i class="fas fa-ticket"></i>Ticketed</span>
                <span class="fdl-fig" id="fdlTicketed">—</span>
            </div>
            <div class="is-out">
                <span class="fdl-lbl"><i class="fas fa-hourglass-half"></i>Pending <span class="fdl-qual">(no PNR)</span></span>
                <span class="fdl-fig" id="fdlPending">—</span>
            </div>
            <div>
                <span class="fdl-lbl"><i class="fas fa-flag"></i>Flagged</span>
                <span class="fdl-fig" id="fdlFlagged">—</span>
            </div>
            <div class="is-in">
                <span class="fdl-lbl"><i class="fas fa-arrow-down"></i>Agent Charge <span class="fdl-qual">(in)</span></span>
                <span class="fdl-fig" id="fdlCharge">—</span>
            </div>
            <div class="is-out">
                <span class="fdl-lbl"><i class="fas fa-arrow-up"></i>Travel Cost <span class="fdl-qual">(out)</span></span>
                <span class="fdl-fig" id="fdlCost">—</span>
            </div>
            <div class="is-close">
                <span class="fdl-lbl"><i class="fas fa-flag-checkered"></i>Profit</span>
                <span class="fdl-fig" id="fdlProfit">—</span>
            </div>
        </div>
    </div>

    <div class="rounded-xl bg-white shadow-sm border border-gray-100 overflow-hidden">
        {{-- The bank statement's filter bar. Search and ticket state filter
             the loaded rows. Airline groups use departure dates;
             a single flight uses ticket-purchase dates. --}}
        <div class="fdl-bar">
            <div class="fdl-search" id="fdlSearchWrap">
                <i class="fas fa-magnifying-glass"></i>
                <input type="text" id="fdPassengerSearch" autocomplete="off"
                       placeholder="Search passenger, passport, agent, PNR or stage…" aria-label="Search passengers">
                <button type="button" class="fdl-clear" id="fdlClear" title="Clear search"><i class="fas fa-xmark"></i></button>
            </div>

            <div class="fdl-seg" id="fdlSeg">
                <button type="button" data-state="all">All</button>
                <button type="button" data-state="ticketed" title="Passengers holding a PNR">Ticketed</button>
                <button type="button" data-state="pending" title="Passengers with no PNR yet">No PNR</button>
                <button type="button" data-state="flagged" title="Cancelled, rescheduled, transit or deported">Flagged</button>
            </div>

            <div class="fdl-dates" id="fdlDates">
                <span class="fdl-date-kind" id="fdlDateKind">Departure</span>
                <span class="fdl-date">
                    <input type="text" id="fdlFromText" class="fdl-date-text" placeholder="dd/mm/yyyy"
                           inputmode="numeric" maxlength="10" autocomplete="off" aria-label="Ticket purchase date from" title="Ticket purchase date — from">
                    <input type="date" id="fdlFrom" class="fdl-date-native" aria-label="Pick the from date" title="Pick a date">
                </span>
                <span>to</span>
                <span class="fdl-date">
                    <input type="text" id="fdlToText" class="fdl-date-text" placeholder="dd/mm/yyyy"
                           inputmode="numeric" maxlength="10" autocomplete="off" aria-label="Ticket purchase date to" title="Ticket purchase date — to">
                    <input type="date" id="fdlTo" class="fdl-date-native" aria-label="Pick the to date" title="Pick a date">
                </span>
                <button type="button" class="fdl-go" onclick="applyPassengerRange('custom')">Apply</button>
                <button type="button" class="fdl-reset" onclick="resetPassengerFilters(true)"><i class="fas fa-rotate-left"></i> Reset</button>
                {{-- The flight's invoice: every passenger on the batch, priced.
                     data-no-prefetch — a hover must not make the server build it. --}}
                <a href="#" id="fdlPrint" target="_blank" class="fdl-print" data-no-prefetch title="Print the current passenger list" onclick="return printPassengerLedger(event)"><i class="fas fa-print"></i> Print</a>
            </div>

            <span class="fdl-count" id="fdPassengerCount" aria-live="polite" style="display:none;"></span>
        </div>

        <div id="fdLedgerMessage" class="fdl-message" role="status"></div>
        <div class="fd-table-scroll">
            <table class="fd-table mfp-table" id="fdPassengerTable" data-cf-sort hidden>
                <caption class="sr-only">Passenger identities, travel progress, ticket purchases and costs for the selected flight</caption>
                {{-- The Flights & Passengers table, column for column (owner, 2026-10-03:
                     "should look like the passenger table, same"). It wears .mfp-table,
                     so passenger-table-styles dresses it exactly as that page. --}}
                <colgroup><col id="fdPassengerDepartureCol" style="width:8%"><col style="width:3%"><col style="width:13%"><col style="width:14%"><col style="width:16%"><col style="width:11%"><col style="width:9%"><col style="width:11%"><col style="width:8%"><col style="width:7%"></colgroup>
                <thead><tr>
                    <th scope="col" id="fdPassengerDepartureHeading">Departure</th><th scope="col">SL</th><th scope="col">Passenger / Passport</th><th scope="col">Agent / Travel Details</th>
                    <th scope="col">Tickets / PNR</th><th scope="col">Route</th><th scope="col">Status</th>
                    <th scope="col" title="What the agent is charged, then each cost line">Charge &amp; Cost Breakdown</th>
                    <th scope="col" class="mfp-num">Cost / Profit</th>
                    <th scope="col" class="mfp-center" data-nosort>Actions</th>
                </tr></thead>
                <tbody id="fdPassengerBody"></tbody>
            </table>
        </div>
    </div>
</section>

@include('contract-flights.partials.stage-editor')
