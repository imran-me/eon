<section id="fd{{ $section }}" class="fd-table-panel fd-table-panel--{{ $section }}" aria-labelledby="fd{{ $section }}Title">
    <div class="fd-departed-head fd-section-head--{{ $section }}">
        <h3 id="fd{{ $section }}Title"><i class="fas fa-plane-departure" aria-hidden="true"></i>{{ $title }}</h3>
        <span id="fd{{ $section }}Count" aria-live="polite"></span>
    </div>
    {{-- The Passenger Ledger's filter bar, reused (owner, 2026-10-01): every
         table carries search, quick filters, a date range and Print. This
         table follows this bar only, not the master bar at the top. The date
         range reads the DEPARTURE date. --}}
    <div class="fdl-bar">
        <div class="fdl-search" id="fd{{ $section }}FilterSearchWrap">
            <i class="fas fa-magnifying-glass"></i>
            <input type="text" id="fd{{ $section }}FilterSearch" autocomplete="off"
                   placeholder="Search flight, airline, route, agent or vendor…" aria-label="Search {{ strtolower($title) }}">
            <button type="button" class="fdl-clear" id="fd{{ $section }}FilterClear" title="Clear search"><i class="fas fa-xmark"></i></button>
        </div>
        <div class="fdl-seg">
            @foreach(['today' => 'Today', 'tomorrow' => 'Tomorrow', 'yesterday' => 'Yesterday', 'clear' => 'All dates'] as $preset => $label)
                <button type="button" data-preset="{{ $preset }}" onclick="flightSectionPreset('{{ $section }}', '{{ $preset }}')">{{ $label }}</button>
            @endforeach
        </div>
        @if($section === 'departed')
            <div class="fdl-seg">
                @foreach(['closed' => 'Case Closed', 'departed' => 'Departed', 'due' => 'Vendor Due', 'all' => 'All'] as $state => $label)
                    <button type="button" data-state="{{ $state }}" class="{{ $state === 'all' ? 'is-on' : '' }}" onclick="setDepartedState('{{ $state }}')">{{ $label }}</button>
                @endforeach
            </div>
        @endif
        <div class="fdl-dates" id="fd{{ $section }}FilterDates">
            <span class="fdl-date">
                <input type="text" id="fd{{ $section }}FilterFromText" class="fdl-date-text" placeholder="dd/mm/yyyy"
                       inputmode="numeric" maxlength="10" autocomplete="off" aria-label="Departure date from" title="Departure date — from">
                <input type="date" id="fd{{ $section }}FilterFrom" class="fdl-date-native" aria-label="Pick the from date" title="Pick a date">
            </span>
            <span>to</span>
            <span class="fdl-date">
                <input type="text" id="fd{{ $section }}FilterToText" class="fdl-date-text" placeholder="dd/mm/yyyy"
                       inputmode="numeric" maxlength="10" autocomplete="off" aria-label="Departure date to" title="Departure date — to">
                <input type="date" id="fd{{ $section }}FilterTo" class="fdl-date-native" aria-label="Pick the to date" title="Pick a date">
            </span>
            <button type="button" class="fdl-go" onclick="applyFlightSectionDates('{{ $section }}')">Apply</button>
            <button type="button" class="fdl-print" onclick="openDashboardPrint('{{ $section }}')" title="Print all filtered {{ strtolower($title) }}"><i class="fas fa-print"></i> Print</button>
        </div>
    </div>
    <div class="fd-table-scroll">
        <table class="fd-table" id="fd{{ $section }}Table" aria-labelledby="fd{{ $section }}Title">
            <colgroup><col style="width:8%"><col style="width:14%"><col style="width:17%"><col style="width:16%"><col style="width:9%"><col style="width:10%"><col style="width:8.5%"><col style="width:8.5%"><col style="width:9%"></colgroup>
            <thead>
                <tr>
                    <th scope="col" data-sort-column="departure" aria-sort="none"><button type="button" class="fd-column-sort" onclick="toggleFlightSectionSort('{{ $section }}', 'departure')"><span>Departure</span><i class="fas fa-sort" aria-hidden="true"></i></button></th>
                    <th scope="col" data-sort-column="airline" aria-sort="none"><button type="button" class="fd-column-sort" onclick="toggleFlightSectionSort('{{ $section }}', 'airline')"><span>Flight / Airline</span><i class="fas fa-sort" aria-hidden="true"></i></button></th>
                    <th scope="col" data-sort-column="route" aria-sort="none"><button type="button" class="fd-column-sort" onclick="toggleFlightSectionSort('{{ $section }}', 'route')"><span>Route</span><i class="fas fa-sort" aria-hidden="true"></i></button></th>
                    <th scope="col" data-sort-column="agent" aria-sort="none"><button type="button" class="fd-column-sort" onclick="toggleFlightSectionSort('{{ $section }}', 'agent')"><span>Agent / Vendor</span><i class="fas fa-sort" aria-hidden="true"></i></button></th>
                    <th scope="col" class="fd-passengers" data-sort-column="passengers" aria-sort="none"><button type="button" class="fd-column-sort" onclick="toggleFlightSectionSort('{{ $section }}', 'passengers')"><span>Passengers</span><i class="fas fa-sort" aria-hidden="true"></i></button></th>
                    <th scope="col" data-sort-column="status" aria-sort="none"><button type="button" class="fd-column-sort" onclick="toggleFlightSectionSort('{{ $section }}', 'status')"><span>Status</span><i class="fas fa-sort" aria-hidden="true"></i></button></th>
                    <th scope="col" class="fd-num" data-sort-column="revenue" aria-sort="none"><button type="button" class="fd-column-sort" onclick="toggleFlightSectionSort('{{ $section }}', 'revenue')"><span>Agent Charge</span><i class="fas fa-sort" aria-hidden="true"></i></button></th>
                    <th scope="col" class="fd-num" data-sort-column="cost" aria-sort="none"><button type="button" class="fd-column-sort" onclick="toggleFlightSectionSort('{{ $section }}', 'cost')"><span>Travel Cost</span><i class="fas fa-sort" aria-hidden="true"></i></button></th>
                    <th scope="col" class="fd-num" data-sort-column="vendor_due" aria-sort="none"><button type="button" class="fd-column-sort" onclick="toggleFlightSectionSort('{{ $section }}', 'vendor_due')"><span>Vendor Due</span><i class="fas fa-sort" aria-hidden="true"></i></button></th>
                </tr>
            </thead>
            <tbody id="fd{{ $section }}Body"></tbody>
        </table>
    </div>
    @include('contract-flights.dashboard-pagination', ['paginationSection' => $section, 'paginationTitle' => $title])
</section>
