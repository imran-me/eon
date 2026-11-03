                        @php
                            /* The super admin's verdict on this card — lock, red flag, rating.

                               relationLoaded() and not $task->oversight: an unloaded relation
                               would fire one query per card, which is exactly the N+1 the
                               board was just cured of. When the eager load is absent (the
                               table has not been migrated, or a view that does not ask for
                               it) every card simply renders with no marks, which is what it
                               did before this feature existed. */
                            $etoV    = $task->relationLoaded('oversight') ? $task->oversight : null;
                            $etoFlag = $etoV && $etoV->red_flag;
                            $etoLock = $etoV && $etoV->is_restricted;
                            $etoRate = $etoV ? $etoV->rating : null;
                            $etoRail = trim(($etoFlag ? 'eto-flagged ' : '') . ($etoLock ? 'eto-locked' : ''));
                        @endphp
                        {{-- Compact Task Card --}}
                        <div class="tk-card bg-white rounded-lg shadow-sm border border-gray-200 hover:border-blue-400 hover:shadow-md cursor-grab active:cursor-grabbing task-item transition-all group relative {{ $etoRail }}"
                            style="overflow: visible;"
                            data-id="{{ $task->id }}">

                            {{-- The chevrons stay 10px, but each one is clickable well beyond the
                                 glyph: the ::after below stretches the hit area to roughly 26x24
                                 without the button occupying any more room.

                                 Done this way rather than with padding because padding grows the
                                 box, and the box has only ~14px of clearance before it touches the
                                 last footer line — measured. Padding big enough to feel right left
                                 zero gap. A pseudo-element takes no layout space at all, so the
                                 target can be generous while the arrows stay small and the footer
                                 stays untouched. --}}

                            {{-- 3-Dot Menu Button — revealed by hovering the top-right corner
                                 itself, not the whole card. The ribbon below wants the same
                                 corner, so the two take turns: the corner is the ribbon's until
                                 you reach for the menu, and the peer-hover rule on the ribbon
                                 slot fades it out of the way.

                                 The wrapper carries no opacity of its own — the open dropdown
                                 lives inside it, and fading the wrapper would fade an open menu
                                 away with it. The button is 28px of hover target around a 16px
                                 glyph that has not moved a pixel (top-2/p-1 -> top-1.5/p-1.5). --}}
                            <div data-role="card-menu" class="peer/menu absolute top-1.5 right-1.5 z-10">
                                <button onclick="event.stopPropagation(); toggleDropdown('task-menu-{{ $task->id }}')"
                                        class="p-1.5 rounded opacity-0 hover:opacity-100 focus-visible:opacity-100 transition-opacity hover:bg-gray-100 text-gray-600">
                                    <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                                        <path d="M10 6a2 2 0 110-4 2 2 0 010 4zm0 6a2 2 0 110-4 2 2 0 010 4zm0 6a2 2 0 110-4 2 2 0 010 4z"/>
                                    </svg>
                                </button>

                                {{-- Dropdown Menu --}}
                                <div id="task-menu-{{ $task->id }}" class="hidden absolute right-0 top-full mt-1 w-40 bg-white rounded-lg shadow-xl border border-gray-200 z-50 py-1">
                                    <button onclick='event.stopPropagation(); openEditModal({{ $task->id }}, @json($task->title), @json($task->description), @json($task->users->pluck("id")->toArray()), @json($task->start_date), @json($task->due_date), @json($task->priority), {{ $task->column_id }}, @json($task->labels->pluck("id")->toArray()))'
                                            class="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 flex items-center gap-2">
                                        <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                                            <path d="M17.414 2.586a2 2 0 00-2.828 0L7 10.172V13h2.828l7.586-7.586a2 2 0 000-2.828z"/>
                                            <path fill-rule="evenodd" d="M2 6a2 2 0 012-2h4a1 1 0 010 2H4v10h10v-4a1 1 0 112 0v4a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" clip-rule="evenodd"/>
                                        </svg>
                                        Edit
                                    </button>
                                    <button onclick="event.stopPropagation(); copyTask({{ $task->id }})" 
                                            class="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 flex items-center gap-2">
                                        <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                                            <path d="M4 3a1 1 0 00-1 1v11a1 1 0 001 1h5v2H4a3 3 0 01-3-3V4a3 3 0 013-3h11a3 3 0 013 3v5h-2V4a1 1 0 00-1-1H4z"/>
                                            <path d="M9 9a1 1 0 011-1h5.586l-2.293-2.293a1 1 0 111.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 11-1.414-1.414L15.586 10H10a1 1 0 01-1-1z"/>
                                        </svg>
                                        Duplicate
                                    </button>
                                    <button onclick="event.stopPropagation(); deleteTask({{ $task->id }})" 
                                            class="w-full text-left px-3 py-2 text-sm text-red-600 hover:bg-red-50 flex items-center gap-2">
                                        <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                        </svg>
                                        Delete
                                    </button>
                                </div>
                            </div>

                            {{-- The corner ribbon. One slot, two possible words, and which one
                                 shows says what the card wants from you:

                                 AT REST — an admin created this task, or commented on it after
                                 the last time you opened it. That is news you have not read, so
                                 it shows without being asked for. It clears the moment you open
                                 the task (markSeen), and only for you: everyone who has not
                                 looked still sees it.

                                 ON HOVER — priority. Priority is a label, not news; it does not
                                 change and it does not need reading, so it stays out of the way
                                 until you look at a card. The chip in row 1 is still the thing
                                 you CLICK to change it.

                                 ON THE MENU — nothing. The ⋯ button wants this exact corner,
                                 and dots over a coloured band read as neither.

                                 Clipped by its own 56px box, NOT by the card: the card is
                                 style="overflow: visible" on purpose so the date and label
                                 dropdowns can escape it, and an overflow-hidden card would
                                 guillotine every one of them.

                                 Geometry is not free-hand. With the band 96px wide, right:-26px
                                 and top:15px put the TEXT centre on the centre of the visible
                                 chord (the pair has to sum to 41 for that); off-centre values
                                 crowd the word against the thin end. --}}
                            @php
                                $prioRibbon = [
                                    'high'   => ['High',   'linear-gradient(135deg,#fb7185,#e11d48)'],
                                    'medium' => ['Medium', 'linear-gradient(135deg,#fbbf24,#d97706)'],
                                    'low'    => ['Low',    'linear-gradient(135deg,#60a5fa,#2563eb)'],
                                ][$task->priority] ?? null;

                                // Null on the my-tasks list, which renders a different card and
                                // is never handed the map — the ?? is what keeps it from erroring.
                                $alertRibbon = ($adminAlerts ?? [])[$task->id] ?? null;

                                $band = 'absolute w-24 rotate-45 py-[3px] text-center text-[8px] font-bold uppercase tracking-[0.08em] text-white transition-opacity duration-150';

                                /* Progress — the share of sub-work items done, the same pair the
                                   ☑ chip in the footer counts. Computed up here, before the
                                   ribbon renders, because the two want the same corner and the
                                   ribbon has to know which of them is having it.

                                   Order of precedence in that corner:
                                     1. unread admin news  — news you have not read outranks
                                        everything, and it clears itself the moment you open the
                                        task, so the dial comes back on its own;
                                     2. the progress dial  — on any card that has sub-work items;
                                     3. the priority band  — which is pure decoration here:
                                        priority is already on the card, in row 1, as the chip
                                        you actually click to change it. */
                                $tpDone  = (int) ($task->done_subtasks_count ?? 0);
                                $tpTot   = (int) ($task->subtasks_count ?? 0);
                                $tpPct   = $tpTot ? (int) round($tpDone / $tpTot * 100) : 0;
                                $hasDial = $tpTot > 0 && ! $alertRibbon;
                            @endphp
                            @if($prioRibbon || $alertRibbon)
                                <div data-role="ribbon-slot"
                                     class="pointer-events-none absolute top-0 right-0 z-0 h-14 w-14 overflow-hidden rounded-tr-[7px] transition-opacity duration-150 peer-hover/menu:opacity-0"
                                     aria-hidden="true">
                                    @if($alertRibbon)
                                        {{-- Steps aside on hover only when there is a priority
                                             band to replace it — otherwise the corner would just
                                             go blank under the cursor. --}}
                                        <span data-role="ribbon-alert"
                                              class="{{ $band }} {{ $prioRibbon && ! $hasDial ? 'group-hover:opacity-0' : '' }}"
                                              style="right:-26px; top:15px; background: {{ $alertRibbon[1] }}">{{ $alertRibbon[0] }}</span>
                                    @endif
                                    {{-- Not on a card that has a dial: the band would sweep in
                                         underneath it, and priority is already on the card as the
                                         row-1 chip. --}}
                                    @if($prioRibbon && ! $hasDial)
                                        <span data-role="ribbon-priority"
                                              class="{{ $band }} opacity-0 group-hover:opacity-100"
                                              style="right:-26px; top:15px; background: {{ $prioRibbon[1] }}">{{ $prioRibbon[0] }}</span>
                                    @endif
                                </div>
                                <span class="sr-only">
                                    @if($alertRibbon){{ $alertRibbon[0] === 'Comment' ? 'New comment from an admin.' : 'Newly assigned by an admin.' }}@endif
                                    @if($prioRibbon)Priority: {{ $prioRibbon[0] }}.@endif
                                </span>
                            @endif

                            {{-- The title row: title on the left, progress dial on the right.

                                 The dial is style 06 "Ring" from the owner's
                                 task-card-20-styles comparison page — 32px across, 3px
                                 stroke. r=14.5 with a 3px stroke is what makes the ring meet
                                 the 32-unit viewBox exactly: the stroke straddles the path, so
                                 it runs r13 → r16. Change one and the other has to follow.

                                 It sits BESIDE the title, never inside it: .tk-title is clamped
                                 to two lines with overflow:hidden, which cut a dial carried
                                 within it. The row reserves the lane and centres the dial
                                 against the title, whatever height that title comes out at. --}}
                            {{-- The verdict, above the title.

                                 Shown on EVERY board, not only in oversight: a lock the
                                 employee cannot see is a lock that only surfaces later as a
                                 baffling "you cannot delete this". The marks are read-only
                                 here — the controls live in the drawer and only a super admin
                                 gets them. Nothing renders at all on a card with no verdict,
                                 so an ordinary card is byte-identical to what it was. --}}
                            @if($etoFlag || $etoLock || $etoRate)
                                <div class="flex items-center gap-1 mb-1 flex-wrap" data-role="eto-marks">
                                    @if($etoFlag)
                                        <span class="eto-mark flag" title="Red-flagged by the Super Admin{{ $etoV->red_flag_note ? ': ' . $etoV->red_flag_note : '' }}">
                                            <svg viewBox="0 0 20 20" fill="currentColor"><path d="M3 2a1 1 0 011-1h12l-3 4 3 4H5v9a1 1 0 11-2 0V2z"/></svg>FLAG
                                        </span>
                                    @endif
                                    @if($etoLock)
                                        <span class="eto-mark lock" title="Restricted by the Super Admin — cannot be edited or deleted{{ $etoV->restriction_note ? ': ' . $etoV->restriction_note : '' }}">
                                            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>LOCKED
                                        </span>
                                    @endif
                                    @if($etoRate)
                                        <span class="eto-mark rate" title="Rated {{ $etoRate }} out of 5 by the Super Admin{{ $etoV->rating_note ? ': ' . $etoV->rating_note : '' }}">
                                            <svg viewBox="0 0 20 20" fill="currentColor"><path d="M9.05 2.93c.3-.92 1.6-.92 1.9 0l1.29 3.98a1 1 0 00.95.69h4.18c.97 0 1.37 1.24.59 1.81l-3.39 2.46a1 1 0 00-.36 1.12l1.3 3.98c.3.92-.76 1.69-1.54 1.12l-3.39-2.46a1 1 0 00-1.18 0l-3.39 2.46c-.78.57-1.84-.2-1.54-1.12l1.3-3.98a1 1 0 00-.36-1.12L2.02 9.41c-.78-.57-.38-1.81.59-1.81H6.8a1 1 0 00.95-.69l1.3-3.98z"/></svg>{{ $etoRate }}/5
                                        </span>
                                    @endif
                                </div>
                            @endif

                            <div class="tk-head mb-1.5">
                                <p class="tk-title font-semibold text-gray-900 text-[16px] leading-snug cursor-pointer hover:text-blue-600 transition {{ $hasDial ? '' : 'pr-8' }}"
                                onclick='openTaskDrawer({{ $task->id }}, @json($task->title), @json($task->description), @json($task->users->pluck("id")), @json($task->start_date), @json($task->due_date), @json($task->priority), @json($task->column_id), @json($task->labels->pluck("id")), @json($task->creator ? $task->creator->name : "Unknown"), null, null)'>
                                    {{ $task->title }}
                                </p>

                                @if($hasDial)
                                    <svg class="tp-ring {{ $tpPct >= 100 ? 'tp-full' : '' }}" data-task-progress="{{ $task->id }}"
                                         viewBox="0 0 36 36" style="--tp-p: {{ $tpPct }}"
                                         role="img"
                                         aria-label="{{ $tpDone }} of {{ $tpTot }} sub-work items done ({{ $tpPct }}%)">
                                        <title>{{ $tpDone }}/{{ $tpTot }} sub-work items done</title>
                                        {{-- Baby blue → sky → blue → dark blue, along the band. The id carries
                                             the task id because a gradient is referenced by id, and every card
                                             on the board is drawing one of these. --}}
                                        <defs>
                                            {{-- Colour ALONG the band: baby blue → sky → blue → dark blue. --}}
                                            <linearGradient id="tpGrad-{{ $task->id }}" x1="0" y1="1" x2="1" y2="0">
                                                <stop offset="0%"   stop-color="#5cbcff"></stop>
                                                <stop offset="38%"  stop-color="#2a88f2"></stop>
                                                <stop offset="72%"  stop-color="#1857c6"></stop>
                                                <stop offset="100%" stop-color="#0f2f74"></stop>
                                            </linearGradient>
                                            {{-- Shading ACROSS the band, and the reason this reads as a tube: a
                                                 radial gradient in user space varies with RADIUS, so its stops
                                                 land on the band's inner edge, its crown and its outer edge.
                                                 Offsets are r/18 — .72 is the inner edge at r13, 1.0 the outer at
                                                 r18. Alpha only, so the colour underneath keeps its hue. --}}
                                            <radialGradient id="tpTube-{{ $task->id }}" gradientUnits="userSpaceOnUse"
                                                            cx="18" cy="18" r="18">
                                                <stop offset=".72" stop-color="#08204f" stop-opacity=".40"></stop>
                                                <stop offset=".80" stop-color="#08204f" stop-opacity=".06"></stop>
                                                <stop offset=".86" stop-color="#ffffff" stop-opacity=".34"></stop>
                                                <stop offset=".92" stop-color="#ffffff" stop-opacity=".46"></stop>
                                                <stop offset="1"   stop-color="#08204f" stop-opacity=".26"></stop>
                                            </radialGradient>
                                            {{-- The same idea inverted for the groove: shaded where the band would
                                                 cast into it, lit at the outer lip. --}}
                                            <radialGradient id="tpGroove-{{ $task->id }}" gradientUnits="userSpaceOnUse"
                                                            cx="18" cy="18" r="18">
                                                <stop offset=".72" stop-color="#0a2450" stop-opacity=".16"></stop>
                                                <stop offset=".84" stop-color="#0a2450" stop-opacity=".05"></stop>
                                                <stop offset="1"   stop-color="#ffffff" stop-opacity=".85"></stop>
                                            </radialGradient>
                                            {{-- The dished plate. Tinted, never white: a white plate on a white
                                                 card is a hole, which is what made the dial look half-there. --}}
                                            <radialGradient id="tpPlate-{{ $task->id }}" cx="36%" cy="28%" r="80%">
                                                <stop offset="0%"   stop-color="#fbfdff"></stop>
                                                <stop offset="62%"  stop-color="#edf2fa"></stop>
                                                <stop offset="100%" stop-color="#d7e1f2"></stop>
                                            </radialGradient>
                                        </defs>
                                        <circle class="tp-plate" cx="18" cy="18" r="12.7"
                                                style="fill: url(#tpPlate-{{ $task->id }})"></circle>
                                        <circle class="tp-well" cx="18" cy="18" r="13.1"></circle>
                                        <circle class="tp-bg" cx="18" cy="18" r="15.5" pathLength="100"></circle>
                                        <circle class="tp-bg-shade" cx="18" cy="18" r="15.5" pathLength="100"
                                                style="stroke: url(#tpGroove-{{ $task->id }})"></circle>
                                        <circle class="tp-fg" cx="18" cy="18" r="15.5" pathLength="100"
                                                style="stroke: url(#tpGrad-{{ $task->id }})"></circle>
                                        <circle class="tp-fg tp-fg-tube" cx="18" cy="18" r="15.5" pathLength="100"
                                                style="stroke: url(#tpTube-{{ $task->id }})"></circle>
                                        <text class="tp-pct" x="18" y="21.6" text-anchor="middle">{{ $tpPct }}%</text>
                                    </svg>
                                @endif
                            </div>

                            {{-- ROW 1 — state, priority, start date, due date. Nothing else.

                                 Two fixed rows, not one wrapping row. The footer used to be a
                                 single flex-wrap line holding all six controls plus the arrows, so
                                 where it broke depended on the column name, whether the task had
                                 labels, and how wide the avatar stack was: every card in a column
                                 broke in a different place and one item was forever left orphaned
                                 on a line of its own. That is what looked untidy, and no amount of
                                 pixel-shaving fixes it — the content is variable and the card is
                                 not.

                                 So the row is now flex-nowrap and carries only these four. They
                                 come to ~215px of the 272px available with "PROGRESS", the longest
                                 column name in use, and both dates set. Assignee and labels moved
                                 to row 2 below. Every card in the column now breaks in exactly the
                                 same place: never. --}}
                            <div class="tk-meta mt-2 flex items-center justify-between gap-2 overflow-visible">
                                <div class="flex items-center flex-nowrap gap-0.5 min-w-0 overflow-hidden">
                                    {{-- 1. State Badge — HIDDEN, not removed.

                                         The card already sits in its column, so the pill repeated
                                         what the board was showing anyway, and it was the single
                                         widest thing on the row: 77px of the 270px available. The
                                         dates could not carry a full "17:09:33 24/08/26" stamp
                                         until it went (302px needed vs 270px). Hiding it frees the
                                         room for both of them with ~49px to spare.

                                         It stays in the DOM on purpose. Reporting reads the state,
                                         the dropdown engine registers this element on load, and
                                         task-card.js's updateStateBadgeColor() writes to it after
                                         every column change — deleting it would break all three.
                                         `hidden` only takes it off the screen; state is still
                                         changed by dragging, the ‹ › arrows, or the drawer. --}}
                                    <div class="hidden">
                                    <x-ui.dropdown
                                        type="state"
                                        context="card"
                                        :task-id="$task->id"
                                        :search="true"
                                        :multi="false"
                                        :selected="(string)$task->column_id"
                                        width="w-44"
                                        >
                                        <x-slot:toggle>
                                            {{-- gap-0.5 / px-1.5 / 10px rather than gap-1 / px-2 /
                                                 11px: see the note on the footer row above. A dozen
                                                 pixels here is the difference between "PROGRESS"
                                                 keeping the due date on its line and pushing it
                                                 below — "PROGRESS" is the longest column name in
                                                 use and is what the row has to survive. --}}
                                            <button type="button" class="inline-flex items-center gap-0.5 px-1.5 py-1 rounded-md text-[10px] font-medium tracking-tight hover:opacity-80 transition max-w-[104px]
                                            @switch($task->column->position ?? 1)
                                                @case(1) bg-gray-100 text-gray-600 @break
                                                @case(2) bg-blue-100 text-blue-600 @break
                                                @case(3) bg-amber-100 text-amber-600 @break
                                                @case(4) bg-emerald-100 text-emerald-600 @break
                                                @case(5) bg-red-100 text-red-600 @break
                                                @default bg-gray-100 text-gray-600
                                            @endswitch
                                            ">
                                            <span class="inline-block w-3 h-3 shrink-0 rounded-full border-2 border-gray-400"></span>
                                            <span data-role="dropdown-label" class="truncate">{{ $task->column->name }}</span>
                                            </button>
                                        </x-slot:toggle>

                                        @foreach($board->columns as $col)
                                            <x-ui.dropdown-item value="{{ $col->id }}" :selected="$task->column_id == $col->id" :attrs="['data-position' => $col->position ?? 1]">
                                                @switch($col->position ?? 1)
                                                    @case(1) <span class="inline-block w-3 h-3 rounded-full border-2 border-gray-400"></span> @break
                                                    @case(2) <span class="inline-block w-3 h-3 rounded-full border-2 border-blue-500"></span> @break
                                                    @case(3) <span class="w-3 h-3 rounded-full border-2 border-amber-500 flex items-center justify-center"><span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span></span> @break
                                                    @case(4) <svg class="w-3 h-3 text-emerald-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg> @break
                                                    @case(5) <svg class="w-3 h-3 text-red-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg> @break
                                                    @default <span class="inline-block w-3 h-3 rounded-full border-2 border-gray-400"></span>
                                                @endswitch
                                            <span>{{ $col->name }}</span>
                                            </x-ui.dropdown-item>
                                        @endforeach
                                    </x-ui.dropdown>
                                    </div>

                                    {{-- 2. Priority Icon --}}
                                    <x-ui.dropdown
                                        type="priority"
                                        context="card"
                                        :task-id="$task->id"
                                        :search="true"
                                        :multi="false"
                                        :selected="(string)($task->priority ?? '')"
                                        width="w-32"
                                        >

                                        <x-slot:toggle>
                                            <button type="button"
                                            class="inline-flex items-center gap-1 p-1 rounded hover:bg-gray-100 transition text-[11px]"
                                            title="Priority: {{ $task->priority ? ucfirst($task->priority) : 'None' }}"
                                            >
                                            <i id="priority-icon-{{ $task->id }}" class="fa-solid fa-signal
                                                @if($task->priority === 'high') text-red-500
                                                @elseif($task->priority === 'medium') text-yellow-500
                                                @elseif($task->priority === 'low') text-blue-500
                                                @else text-gray-400 @endif"></i>
                                            {{-- The word, beside the bars. Only when a priority is
                                                 actually set: "None" printed on every unset card
                                                 would be a column of noise, and the grey bars
                                                 already say "no priority — click to set one".
                                                 updatePriorityUI() in task-card.js keeps this in
                                                 step with the icon when the value changes. --}}
                                            <span id="priority-text-{{ $task->id }}"
                                                  class="text-[10px] font-semibold leading-none tracking-tight
                                                @if($task->priority === 'high') text-red-500
                                                @elseif($task->priority === 'medium') text-yellow-600
                                                @elseif($task->priority === 'low') text-blue-500
                                                @else hidden @endif">{{ $task->priority ? ucfirst($task->priority) : '' }}</span>
                                            <!-- <svg class="w-4 h-4
                                                @if($task->priority === 'high') text-red-500
                                                @elseif($task->priority === 'medium') text-amber-500
                                                @elseif($task->priority === 'low') text-emerald-500
                                                @else text-gray-400 @endif"
                                                fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9"/>
                                            </svg> -->
                                            </button>
                                        </x-slot:toggle>

                                        <x-ui.dropdown-item value="high" :selected="$task->priority==='high'">
                                            <i class="fa-solid fa-signal text-red-500"></i> <span>High</span>
                                        </x-ui.dropdown-item>

                                        <x-ui.dropdown-item value="medium" :selected="$task->priority==='medium'">
                                            <i class="fa-solid fa-signal text-yellow-500"></i> <span>Medium</span>
                                        </x-ui.dropdown-item>

                                        <x-ui.dropdown-item value="low" :selected="$task->priority==='low'">
                                            <i class="fa-solid fa-signal text-blue-500"></i> <span>Low</span>
                                        </x-ui.dropdown-item>

                                        <x-ui.dropdown-item value="" :selected="!$task->priority">
                                            <i class="fa-solid fa-minus text-gray-400"></i> <span>None</span>
                                        </x-ui.dropdown-item>
                                    </x-ui.dropdown>

                                    {{-- 3. Start Date --}}
                                    <div class="relative overflow-visible">
                                        {{-- The label is the day alone; the full stamp with the time
                                             is on the title, in the popover's input, and in the
                                             drawer. Two "Aug 24, 11:29 AM" chips cannot share a
                                             272px line with the state pill — see the footer note. --}}
                                        <button onclick="event.stopPropagation(); toggleDropdown('startdate-{{ $task->id }}')"
                                                class="inline-flex items-center gap-0.5 px-1 py-1 rounded hover:bg-gray-100 cursor-pointer transition text-[10px] text-gray-600 shrink-0 whitespace-nowrap"
                                                id="startdate-btn-{{ $task->id }}"
                                                data-dropdown-toggle="startdate-{{ $task->id }}"
                                                title="Start date{{ $task->start_date ? ': ' . \Carbon\Carbon::parse($task->start_date)->format('H:i:s d/m/y') : '' }}">
                                            <svg class="w-3 h-3 text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <rect x="3" y="4" width="18" height="18" rx="2"></rect>
                                                <path d="M16 2v4M8 2v4M3 10h18"></path>
                                            </svg>
                                            <span id="startdate-label-{{ $task->id }}">{{ $task->start_date ? \Carbon\Carbon::parse($task->start_date)->format('H:i:s d/m/y') : '' }}</span>
                                        </button>
                                        <div id="startdate-{{ $task->id }}" class="hidden fixed w-48 bg-white rounded-lg shadow-xl border border-gray-200 z-[9999] p-3">
                                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Start Date</label>
                                            <input type="datetime-local" value="{{ $task->start_date ? \Carbon\Carbon::parse($task->start_date)->format('Y-m-d\TH:i') : '' }}" class="w-full text-xs border border-gray-200 rounded px-2 py-1.5 focus:outline-none focus:border-blue-400" onclick="event.stopPropagation()" onblur="event.stopPropagation(); updateTaskFieldNoClose({{ $task->id }}, 'start_date', this.value, null);">
                                            <div class="flex gap-2 mt-2">
                                                <button onclick="event.stopPropagation(); setQuickStartDate({{ $task->id }}, 'today')" class="flex-1 text-[10px] px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded">Today</button>
                                                <button onclick="event.stopPropagation(); setQuickStartDate({{ $task->id }}, 'tomorrow')" class="flex-1 text-[10px] px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded">Tomorrow</button>
                                            </div>
                                            {{-- Clear lives here now: on the chip it was a "×" fighting
                                                 for the same pixels the due date needed. --}}
                                            <button onclick="event.stopPropagation(); updateTaskField({{ $task->id }}, 'start_date', '', null);"
                                                    class="w-full mt-2 text-[10px] px-2 py-1 rounded text-gray-500 hover:text-red-600 hover:bg-red-50 border border-gray-200">
                                                Clear start date
                                            </button>
                                        </div>
                                    </div>

                                    {{-- 4. Due Date --}}
                                    <div class="relative overflow-visible">
                                        <button onclick="event.stopPropagation(); toggleDropdown('duedate-{{ $task->id }}')"
                                                class="inline-flex items-center gap-0.5 px-1 py-1 rounded hover:bg-gray-100 cursor-pointer transition text-[10px] text-gray-600 shrink-0 whitespace-nowrap"
                                                id="duedate-btn-{{ $task->id }}"
                                                data-dropdown-toggle="duedate-{{ $task->id }}"
                                                title="Due date{{ $task->due_date ? ': ' . \Carbon\Carbon::parse($task->due_date)->format('H:i:s d/m/y') : '' }}">
                                            <svg class="w-3 h-3 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <rect x="3" y="4" width="18" height="18" rx="2"></rect>
                                                <path d="M16 2v4M8 2v4M3 10h18"></path>
                                            </svg>
                                            <span id="duedate-label-{{ $task->id }}">{{ $task->due_date ? \Carbon\Carbon::parse($task->due_date)->format('H:i:s d/m/y') : '' }}</span>
                                        </button>
                                        <div id="duedate-{{ $task->id }}" class="hidden fixed w-48 bg-white rounded-lg shadow-xl border border-gray-200 z-[9999] p-3">
                                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Due Date</label>
                                            <input type="datetime-local" value="{{ $task->due_date ? \Carbon\Carbon::parse($task->due_date)->format('Y-m-d\TH:i') : '' }}" class="w-full text-xs border border-gray-200 rounded px-2 py-1.5 focus:outline-none focus:border-blue-400" onclick="event.stopPropagation()" onblur="event.stopPropagation(); updateTaskFieldNoClose({{ $task->id }}, 'due_date', this.value, null);">
                                            <div class="flex gap-2 mt-2">
                                                <button onclick="event.stopPropagation(); setQuickDueDate({{ $task->id }}, 'today')" class="flex-1 text-[10px] px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded">Today</button>
                                                <button onclick="event.stopPropagation(); setQuickDueDate({{ $task->id }}, 'tomorrow')" class="flex-1 text-[10px] px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded">Tomorrow</button>
                                            </div>
                                            <button onclick="event.stopPropagation(); updateTaskField({{ $task->id }}, 'due_date', '', null);"
                                                    class="w-full mt-2 text-[10px] px-2 py-1 rounded text-gray-500 hover:text-red-600 hover:bg-red-50 border border-gray-200">
                                                Clear due date
                                            </button>
                                        </div>
                                    </div>

                                </div>
                            </div>

                            {{-- ROW 2 — who and what, then the actions.

                                 Left: assignee and labels, allowed to shrink and clip rather than
                                 push anything onto a line of its own (named label badges are
                                 unbounded — a task with three of them would otherwise break the
                                 row). Right: the sub-work toggle and the move arrows, always in
                                 the same place on every card in the column.

                                 It reads as a footer rather than a third line of content: the
                                 full-bleed rule above it splits the card into what the task IS
                                 above and what you can DO to it below, and it lights up left to
                                 right when the card is hovered. Both live in .tk-foot at the top
                                 of this file.

                                 A rule only, no tinted band — the sub-work panel opens directly
                                 underneath, and a shaded strip would stop dead in the middle of
                                 the card the moment it does. --}}
                            <div class="tk-foot flex items-center gap-1" data-role="card-people">
                                <div class="flex items-center gap-1 min-w-0 overflow-hidden">

                                    {{-- 6. Assignee Avatar - Always visible on right --}}
                                    @php
                                        $selectedUsers = $task->users ?? collect();
                                        $count = $selectedUsers->count();
                                        $maxAvatars = ($count <= 3) ? $count : 2;
                                        $extra = ($count > 3) ? ($count - 2) : 0;
                                        $selectedCsv = $selectedUsers->pluck('id')->implode(',');
                                    @endphp

                                    <x-ui.dropdown
                                        type="assignees"
                                        context="card"
                                        :task-id="$task->id"
                                        :search="true"
                                        :multi="true"
                                        :selected="$selectedCsv"
                                        width="w-44"
                                        >
                                        <x-slot:toggle>
                                            <button type="button" class="cursor-pointer inline-flex items-center">
                                            @if($count)
                                                <div class="flex items-center">
                                                <div class="flex -space-x-2" data-role="dropdown-label">
                                                    @foreach($selectedUsers->take($maxAvatars) as $u)
                                                    <div class="w-5 h-5 rounded-full bg-indigo-500 text-white text-[10px] font-semibold flex items-center justify-center ring-2 ring-white"
                                                        title="{{ $u->name }}">
                                                        @if($u->image)
                                                            <img src="{{ asset($u->image) }}" alt="{{ $u->name }}" class="w-full h-full object-cover rounded-full">
                                                        @else
                                                            {{ strtoupper(substr($u->name, 0, 1)) }}
                                                        @endif
                                                    </div>
                                                    @endforeach
                                                </div>
                                                @if($extra > 0)
                                                    <span class="ml-1 text-[10px] font-semibold text-gray-600 bg-gray-100 border border-gray-200 px-1.5 py-0.5 rounded-full">
                                                    +{{ $extra }}
                                                    </span>
                                                @endif
                                                </div>
                                            @else
                                                <div class="w-5 h-5 rounded-full bg-gray-300 flex items-center justify-center text-gray-500 ring-2 ring-white"
                                                    title="Unassigned">
                                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                                                </svg>
                                                </div>
                                            @endif
                                            </button>
                                        </x-slot:toggle>
                                        @foreach($users as $user)
                                            @php $isSelected = $selectedUsers->contains('id', $user->id); @endphp
                                            <x-ui.dropdown-item value="{{ $user->id }}" :selected="$isSelected" :attrs="['data-name' => $user->name, 'data-image' => $user->image ? asset($user->image) : '']" class="{{ $isSelected ? 'bg-blue-50' : '' }}">
                                                <div class="w-6 h-6 rounded-full bg-indigo-500 flex items-center justify-center text-xs text-white font-semibold">
                                                    @if($user->image)
                                                        <img src="{{ asset($user->image) }}" alt="{{ $user->name }}" class="w-full h-full object-cover rounded-full">
                                                    @else
                                                        {{ strtoupper(substr($user->name, 0, 1)) }}
                                                    @endif
                                                </div>
                                                <span>{{ $user->name }}</span>
                                            </x-ui.dropdown-item>
                                        @endforeach
                                    </x-ui.dropdown>

                                    {{-- 5. Labels --}}
                                    @php
                                        $labelCount = $task->labels->count();
                                        $selectedCsv = $task->labels->pluck('id')->implode(',');
                                    @endphp

                                    <x-ui.dropdown
                                        type="labels"
                                        context="card"
                                        :task-id="$task->id"
                                        :multi="true"
                                        :search="true"
                                        :selected="$selectedCsv"
                                        width="w-48"
                                        >
                                        <x-slot:toggle>
                                            <button type="button" class="cursor-pointer inline-flex items-center"
                                            title="Labels: {{ $task->labels->pluck('name')->join(', ') ?: 'None' }}">
                                            @if($labelCount == 0)
                                                <div class="w-5 h-5 rounded bg-gray-300 flex items-center justify-center text-gray-500 ring-2 ring-white">
                                                    <svg class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor">
                                                        <path d="M17.707 9.293 10.707 2.293A1 1 0 0 0 10 2H3a1 1 0 0 0-1 1v7a1 1 0 0 0 .293.707l7 7a1 1 0 0 0 1.414 0l7-7a1 1 0 0 0 0-1.414ZM5.5 6.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2Z"/>
                                                    </svg>
                                                </div>
                                            @elseif($labelCount <= 3)
                                                <div class="flex flex-wrap gap-1" data-role="dropdown-label">
                                                @foreach($task->labels as $label)
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-medium {{ $label->color_details['badge'] }} {{ $label->color_details['text'] }}" style="background: {{ $label->color_details['gradient'] }}">
                                                    {{ Str::limit($label->name, 12) }}
                                                    </span>
                                                @endforeach
                                                </div>
                                            @else
                                                <div class="inline-flex items-center gap-1 px-2 py-1 rounded hover:bg-gray-100 text-[11px] text-gray-600" data-role="dropdown-label">
                                                    <svg class="w-3.5 h-3.5 text-gray-500" viewBox="0 0 20 20" fill="currentColor">
                                                        <path d="M17.707 9.293 10.707 2.293A1 1 0 0 0 10 2H3a1 1 0 0 0-1 1v7a1 1 0 0 0 .293.707l7 7a1 1 0 0 0 1.414 0l7-7a1 1 0 0 0 0-1.414ZM5.5 6.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2Z"/>
                                                    </svg>
                                                    <span>{{ $labelCount }} Labels</span>
                                                </div>
                                            @endif
                                            </button>
                                        </x-slot:toggle>

                                        @if(isset($labels) && $labels->count() > 0)
                                            @foreach($labels as $label)
                                                <x-ui.dropdown-item value="{{ $label->id }}" :selected="$task->labels?->contains($label->id)" class="{{ $task->labels->contains($label->id) ? 'bg-blue-50' : '' }}" :attrs="['data-name' => $label->name, 'data-color' => $label->color]">
                                                    <span class="w-3 h-3 rounded-sm" style="background: {{ $label->color_details['gradient'] ?? '#999' }}"></span>
                                                    <span class="flex-1 truncate">{{ $label->name }}</span>
                                                </x-ui.dropdown-item>
                                            @endforeach
                                        @else
                                            <div class="px-3 py-2 text-xs text-gray-500">No labels available</div>
                                        @endif

                                    </x-ui.dropdown>

                                </div>

                            {{-- The actions, hard right in the footer: the sub-work chip, then the
                                 move arrows, in that order on every card.

                                 The chip is a ring and the count — the word "sub-work" cost 46px
                                 and said nothing the ring and the tooltip do not. That is what
                                 lets this row hold assignee, labels, chip and arrows without
                                 wrapping, which is the whole point of the two fixed rows.

                                 It is rendered on EVERY card and reads 0/0 on a task with nothing
                                 under it. Fixed furniture, not a badge that comes and goes: a chip
                                 that appeared only once the first sub-item existed made the whole
                                 right-hand group shift sideways card to card, and left the count
                                 looking like a decoration rather than a control. It is also now
                                 the only way in — clicking it opens the list, and the list carries
                                 "Add sub-work item". The separate + button is gone; it took up
                                 permanent space for something wanted occasionally, and one target
                                 that opens the sub-work list beats two that do different things a
                                 few pixels apart.

                                 The ring is the same done/total pair as the bar under the title,
                                 drawn small enough to be read as a state, not a statistic: empty
                                 at 0, a closed blue circle when every item is done.

                                 Arrows: same call as the drag, BoardMotion.send() → performMove().
                                 Guarded with `window.BoardMotion &&` so a page served before the new
                                 bundle does nothing instead of throwing. Their disabled state at
                                 the ends of the board is set on hover by board-show.js. --}}
                                @php
                                    $swTotal = (int) ($task->subtasks_count ?? 0);

                                    /* TIME ON THIS CARD — one chip, two sources.
                                     *
                                     *   has sub-work items  →  the SUM of their time, which is
                                     *                          where a split-up task's hours
                                     *                          actually live;
                                     *   has none            →  the task's own clock, started by
                                     *                          the move into In Progress
                                     *                          (TaskController::autoStartFields).
                                     *
                                     * Shown whenever there is time to show — including after
                                     * Done, which is the point: a finished card states what it
                                     * cost. Hidden only when nothing has ever run, so a card
                                     * nobody has started looks exactly as it did before.
                                     *
                                     * Every read here is null-safe on a server whose timer
                                     * migration has not run: the columns are absent, the
                                     * withSum/withMax are skipped, and $tmShow comes out false. */
                                    $tmRunSince = $task->sub_running_since ?? null;

                                    if ($swTotal > 0) {
                                        // The banked sum, plus the part still ticking under the
                                        // one running child (only one can run — startTimer banks
                                        // the siblings). Capped the same way elapsedMs() caps a
                                        // single run, so a tab left open overnight cannot claim
                                        // fourteen hours.
                                        $tmMs  = (int) ($task->sub_accum_ms ?? 0);
                                        $tmRun = !is_null($tmRunSince);

                                        if ($tmRun) {
                                            $tmRunMs = (int) max(0, now()->diffInMilliseconds(\Carbon\Carbon::parse($tmRunSince), true));
                                            $tmMs   += min($tmRunMs, \App\Models\Task::TIMER_MAX_RUN_MS);
                                        }
                                    } else {
                                        $tmRun = $task->isTimerRunning();
                                        $tmMs  = (int) $task->elapsedMs();
                                    }

                                    $tmShow = $tmRun || $tmMs > 0;
                                    // Short precise on the chip, full words in the tooltip. A clock
                                    // rounded to the minute stands still for sixty seconds at a time
                                    // and reads as broken — and "55m 12s" is no wider than "55 min".
                                    $tmText = $tmShow ? \App\Models\Task::humanDurationCard($tmMs) : '';
                                    $tmFull = $tmShow ? \App\Models\Task::humanDurationPrecise($tmMs) : '';
                                    $tmWhat = $swTotal > 0 ? 'across its sub-work items' : 'on this task';
                                    $swDone  = (int) ($task->done_subtasks_count ?? 0);
                                    // r=7 in a 20-unit box → circumference 43.98. The fill is drawn
                                    // by hiding the share NOT done, so offset 43.98 is an empty ring.
                                    $swOff   = round(43.98 * (1 - ($swTotal ? $swDone / $swTotal : 0)), 2);
                                @endphp
                                <div class="ml-auto flex items-center gap-0.5 shrink-0" data-role="card-actions">
                                    {{-- Comments. Opens the drawer straight onto its Activity
                                         tab, where the thread and the comment box live, and puts
                                         the cursor in the box — the click means "I want to say
                                         something", not "show me this task".

                                         The focus is deferred one tick because the drawer's own
                                         open animation moves the panel; focusing mid-slide scrolls
                                         the page to chase it.

                                         The count only prints when there is one. A zero next to
                                         every icon on the board is noise, and comments_count is
                                         absent on any path that renders this card without the
                                         board query's withCount. --}}
                                    <span data-role="card-time" data-card-time="{{ $task->id }}"
                                          class="h-6 items-center gap-1 pl-1 pr-1.5 rounded-md text-[11px] font-medium tabular-nums {{ $tmRun ? 'text-emerald-600' : 'text-gray-500' }}"
                                          style="display: {{ $tmShow ? 'inline-flex' : 'none' }}"
                                          title="{{ $tmShow ? ($tmRun ? 'Running — ' : 'Tracked ') . $tmFull . ' ' . $tmWhat : '' }}">
                                        {{-- A pulsing dot while it counts, a clock face when it
                                             does not: the state has to be readable without
                                             watching the number change. --}}
                                        <span data-role="ct-dot" class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse shrink-0"
                                              style="display: {{ $tmRun ? 'block' : 'none' }}"></span>
                                        <svg data-role="ct-icon" class="w-3.5 h-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"
                                             style="display: {{ $tmRun ? 'none' : 'block' }}">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.5 2.5a1 1 0 001.414-1.414L11 9.586V6z" clip-rule="evenodd"/>
                                        </svg>
                                        {{-- data-live-elapsed is present only while it runs — the
                                             ticker paints every node carrying it, so leaving it on
                                             a stopped chip would walk the number up for ever. It
                                             carries no data-anchor: the server cannot know the
                                             viewer's clock, so syncTicker() stamps one on first
                                             sight and it counts on from there. --}}
                                        <span data-role="ct-val" data-fmt="card" @if($tmRun) data-live-elapsed @endif data-elapsed-ms="{{ $tmMs }}">{{ $tmText }}</span>
                                    </span>

                                    <button type="button"
                                            id="subtask-toggle-{{ $task->id }}"
                                            data-role="subtask-toggle"
                                            data-expanded="0"
                                            onclick="event.stopPropagation(); window.toggleCardSubtasks && window.toggleCardSubtasks({{ $task->id }})"
                                            class="inline-flex h-6 items-center gap-1 pl-1 pr-1.5 rounded-md text-[11px] font-medium tabular-nums text-gray-500 hover:text-gray-800 hover:bg-gray-100 transition-colors"
                                            title="{{ $swTotal ? $swDone . ' of ' . $swTotal . ' sub-work items done — click to show them' : 'No sub-work items — click to open this task\'s own clock' }}">
                                        <svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                            <circle cx="10" cy="10" r="7" stroke="currentColor" stroke-width="3" opacity="0.25"/>
                                            <circle data-role="subtask-ring" cx="10" cy="10" r="7"
                                                    stroke="currentColor" stroke-width="3" stroke-linecap="round"
                                                    transform="rotate(-90 10 10)"
                                                    class="tk-ring-fill {{ $swTotal && $swDone >= $swTotal ? 'text-blue-600' : 'text-blue-500' }}"
                                                    style="stroke-dasharray: 43.98; stroke-dashoffset: {{ $swOff }};"/>
                                        </svg>
                                        <span data-subtask-progress="{{ $task->id }}">{{ $swDone }}/{{ $swTotal }}</span>
                                    </button>

                                    {{-- Comments sits AFTER the ring, not before the time.

                                         The time chip is the widest thing in this row and the
                                         thing being read, so it leads; the two round controls
                                         group together on its right, next to the arrows they
                                         belong with. --}}
                                    @php $cmCount = (int) ($task->comments_count ?? 0); @endphp
                                    <button type="button"
                                            data-role="card-comments"
                                            onclick='event.stopPropagation(); openTaskDrawer({{ $task->id }}, @json($task->title), @json($task->description), @json($task->users->pluck("id")), @json($task->start_date), @json($task->due_date), @json($task->priority), @json($task->column_id), @json($task->labels->pluck("id")), @json($task->creator ? $task->creator->name : "Unknown"), null, null); window.showDrawerTab && window.showDrawerTab("activity"); setTimeout(function () { var b = document.getElementById("drawerCommentInput"); if (b) b.focus({ preventScroll: true }); }, 380)'
                                            class="inline-flex h-6 items-center gap-1 pl-1 pr-1.5 rounded-md text-[11px] font-medium tabular-nums text-gray-500 hover:text-gray-800 hover:bg-gray-100 transition-colors"
                                            title="{{ $cmCount ? $cmCount . ' comment' . ($cmCount === 1 ? '' : 's') . ' — click to open the thread' : 'Add a comment' }}">
                                        <svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            {{-- The tail hangs below the bubble's body, so the
                                                 path's ink sits lower than the viewBox's centre:
                                                 measured, it printed 0.44px below every other
                                                 control in the row. 0.63 units up (the viewBox is
                                                 20 units rendered at 14px) puts the ink back on
                                                 the row's centre line. --}}
                                            <g transform="translate(0 -0.63)">
                                                <path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a9.06 9.06 0 01-2.347-.306l-3.223 1.53a.5.5 0 01-.699-.57l.66-2.752C2.905 13.63 2 11.913 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7z" clip-rule="evenodd"/>
                                            </g>
                                        </svg>
                                        @if($cmCount)<span>{{ $cmCount }}</span>@endif
                                        <span class="sr-only">Comments</span>
                                    </button>

                                    {{-- TIME — between the comments and the sub-work ring.

                                         Its own chip rather than a second face on the ring: the
                                         two say different things and a finished card has to show
                                         both what it cost AND how many items it took. Absent
                                         entirely when nothing has ever run, so it costs no width
                                         on a card that has not started.

                                         Shown/hidden by inline display and NOT the `hidden`
                                         class, because `hidden` and `inline-flex` are Tailwind
                                         utilities of equal specificity and which one wins depends
                                         on their order in the bundle.

                                         Not a button: the whole card opens the drawer, and the
                                         ring beside it already owns the click that opens the
                                         clock's controls. --}}
                                    <div class="flex items-center gap-0.5 shrink-0" data-role="card-move">
                                        <button type="button" data-move="-1" title="Move to previous column (or press ←)"
                                                onclick="event.stopPropagation(); window.BoardMotion && window.BoardMotion.send(this.closest('.task-item'), { delta: -1 })"
                                                class="inline-flex h-6 w-6 items-center justify-center rounded-md text-gray-400 hover:text-blue-600 hover:bg-gray-100 transition-colors disabled:opacity-25 disabled:cursor-default disabled:hover:bg-transparent disabled:hover:text-gray-400">
                                            <svg class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                        </button>
                                        <button type="button" data-move="1" title="Move to next column (or press →)"
                                                onclick="event.stopPropagation(); window.BoardMotion && window.BoardMotion.send(this.closest('.task-item'), { delta: 1 })"
                                                class="inline-flex h-6 w-6 items-center justify-center rounded-md text-gray-400 hover:text-blue-600 hover:bg-gray-100 transition-colors disabled:opacity-25 disabled:cursor-default disabled:hover:bg-transparent disabled:hover:text-gray-400">
                                            <svg class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            {{-- Sub-work items, filled in on first expand by toggleCardSubtasks.

                                 Empty in the server response on purpose: a board here carries over
                                 1,300 cards, and rendering every card's sub-items up front would
                                 pay for lists nobody opens. Sits after the footer so an expanded
                                 card grows downwards and the footer never moves.

                                 On every card, including ones with nothing under them: the chip
                                 above is always clickable, and what it opens on an empty task is
                                 this panel holding "Add sub-work item" — that is where creating
                                 the first one now happens. --}}
                            <div id="card-subtasks-{{ $task->id }}"
                                 data-subtask-panel="{{ $task->id }}"
                                 class="hidden mt-2.5 pt-2 border-t border-gray-100"></div>
                        </div>
