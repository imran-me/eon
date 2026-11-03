/**
 * Task Sub-work items — Loading, rendering, and row-level actions for the
 * "Sub-work items" list inside the task drawer.
 *
 * Row-level state/priority/date/assignee controls reuse the exact same
 * data-role/data-context markup as the Kanban card's <x-ui.dropdown> footer,
 * so the existing DropdownEngine, dropdown:change listener (board-show.js)
 * and task-card.js update helpers work on them unmodified. Edit/Delete reuse
 * the same components as the rest of the app (openEditModal, task-card's
 * deleteTask) rather than duplicating that logic here.
 */
import { TaskApi } from './task-api';
import { escapeHtml, formatDateDisplay, formatDatetimeLocal } from './task-utils';

const STATE_BADGE_COLORS = {
    1: { bg: 'bg-gray-100',    text: 'text-gray-600' },
    2: { bg: 'bg-blue-100',    text: 'text-blue-600' },
    3: { bg: 'bg-amber-100',   text: 'text-amber-600' },
    4: { bg: 'bg-emerald-100', text: 'text-emerald-600' },
    5: { bg: 'bg-red-100',     text: 'text-red-600' },
};

const SUBTASK_RING_CIRCUMFERENCE = 2 * Math.PI * 6; // r=6 (see the SVG circle in show.blade.php)

/* ═══════════ Time tracking ═══════════
 *
 * Each sub-work item carries its own Start / Pause / Done clock, the way a
 * phase does in the reference build. Two things are on screen: how long it has
 * run ("3 min", ticking live) and when it ran ("3.59pm:24/08/2026 → in
 * progress").
 *
 * The live counter never reads the browser's wall clock as an absolute. The
 * server sends elapsed_ms as of the moment it answered; we stamp Date.now()
 * beside it as an anchor and only ever add the DIFFERENCE since that stamp. A
 * viewer whose PC clock is an hour out therefore still sees the right elapsed
 * time — only the rate of their own clock matters, not its setting.
 */
let tickerId = null;

/**
 * "8 Hours 0 min" -> "8h 0m", for the board panel's chip only.
 *
 * A done item has to fit its elapsed and its range on one line in ~236px. The
 * long form costs 85px at the top of the range (the 8h runaway cap) and pushed
 * the pair onto two lines; abbreviated it is ~40px and every value fits. The
 * drawer, which has 672px, keeps the long form the reference specifies.
 */
function shortDuration(text) {
    return String(text || '')
        .replace(/(\d+)\s*Hours?/i, '$1h')
        .replace(/(\d+)\s*min/i, '$1m')
        .replace(/(\d+)\s*sec/i, '$1s');
}

function formatDuration(ms) {
    const seconds = Math.floor(Math.max(0, ms) / 1000);
    const hours   = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);

    if (hours > 0) return `${hours} Hour${hours > 1 ? 's' : ''} ${minutes} min`;
    return minutes > 0 ? `${minutes} min` : `${seconds} sec`;
}

/**
 * The same span down to the second. Mirrors Task::humanDurationPrecise().
 *
 * A running clock rounded to the minute sits still for sixty seconds at a time
 * and reads as broken — which is exactly what it was taken for. Anything that
 * counts shows seconds; a total nobody is watching can stay rounded.
 */
function formatPrecise(ms, short = false) {
    const seconds = Math.floor(Math.max(0, ms) / 1000);
    const hours   = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs    = seconds % 60;

    if (short) {
        if (hours > 0)   return `${hours}h ${minutes}m ${secs}s`;
        if (minutes > 0) return `${minutes}m ${secs}s`;
        return `${secs}s`;
    }

    if (hours > 0) return `${hours} Hour${hours > 1 ? 's' : ''} ${minutes} min ${secs} sec`;
    return minutes > 0 ? `${minutes} min ${secs} sec` : `${secs} sec`;
}

/**
 * What the card's chip writes. Mirrors Task::humanDurationCard().
 *
 * Seconds up to an hour, minutes past it: "1h 16m 26s" is wider than the footer
 * can spare, and once the reading is in hours the seconds have stopped being
 * the interesting part. Under an hour they are exactly what makes the chip
 * legible as a live clock. The expanded panel keeps them at every scale.
 */
function formatCard(ms) {
    const seconds = Math.floor(Math.max(0, ms) / 1000);
    const hours   = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);

    if (hours > 0) return `${hours}h ${minutes}m`;
    return minutes > 0 ? `${minutes}m ${seconds % 60}s` : `${seconds % 60}s`;
}

/**
 * How one counter wants to be written. data-fmt travels with the node so the
 * ticker — which paints every live counter on the page, wherever it lives —
 * does not have to know which list it came from.
 */
function formatFor(node, ms) {
    switch (node.dataset.fmt) {
        case 'precise':       return formatPrecise(ms);
        case 'precise-short': return formatPrecise(ms, true);
        case 'card':          return formatCard(ms);
        default:              return formatDuration(ms);
    }
}

/**
 * Every live counter that is actually on screen.
 *
 * Closing the drawer only hides it (closeTaskDrawer adds .hidden, it does not
 * empty the list), so its rows must be skipped or the ticker would fire once a
 * second against invisible nodes for the rest of the page's life. The board's
 * expanded sub-work rows are a second home for these counters and are visible
 * whether the drawer is open or not — filtering by "is it inside a hidden
 * drawer" keeps those ticking, which a blanket "drawer closed → paint nothing"
 * would have silently frozen.
 */
function liveNodes() {
    const drawer = document.getElementById('taskDrawer');
    const drawerHidden = drawer && drawer.classList.contains('hidden');

    return Array.from(document.querySelectorAll('[data-live-elapsed]'))
        .filter(node => !(drawerHidden && drawer.contains(node)));
}

function paintLiveCounters() {
    const nodes = liveNodes();

    // Nothing to paint (drawer closed, or no timer running) — stop ticking rather
    // than leaving a stray interval behind for the rest of the session.
    if (!nodes.length) {
        clearInterval(tickerId);
        tickerId = null;
        return;
    }

    const now = Date.now();
    nodes.forEach(node => {
        const base   = Number(node.dataset.elapsedMs) || 0;
        const anchor = Number(node.dataset.anchor) || now;
        node.textContent = formatFor(node, base + (now - anchor));
    });
}

export function syncTicker() {
    /* A counter rendered by Blade arrives with an elapsed value but no anchor —
     * the server cannot know the viewer's clock. Stamp it the first time it is
     * seen so it counts on from now, instead of sitting frozen (the painter
     * falls back to anchor = now, which makes the delta permanently zero). */
    document.querySelectorAll('[data-live-elapsed]:not([data-anchor])')
        .forEach(node => { node.dataset.anchor = String(Date.now()); });

    const running = liveNodes().length > 0;

    if (running && !tickerId) {
        tickerId = setInterval(paintLiveCounters, 1000);
    } else if (!running && tickerId) {
        clearInterval(tickerId);
        tickerId = null;
    }
}

/**
 * The timer line: elapsed chip · date range chip · Start/Pause · Done.
 * Rebuilt wholesale after every action, so it has exactly one source of truth.
 */
export function buildTimerLine(sub, compact = false, handler = 'subtaskTimer') {
    const t = sub.timer || {};
    const anchor = Date.now();

    // The board panel is ~240px once the column's scrollbar takes its cut, and a
    // done item has to fit its elapsed chip and its range chip on one line. At
    // 11px "25 min" + the range needed more than that and wrapped, while "2 sec"
    // squeaked in — the same row rendering two different shapes. 10px with
    // tighter padding leaves room for the widest realistic pair.
    const fs11 = compact ? 'text-[10px]' : 'text-[11px]';
    const padX = compact ? 'px-1.5' : 'px-2';

    // ── elapsed chip ──
    let elapsedChip;
    if (t.running) {
        elapsedChip = `<span class="inline-flex items-center gap-1.5 ${padX} py-0.5 rounded-full border border-emerald-200 bg-emerald-50 ${fs11} font-medium text-emerald-700 shrink-0 whitespace-nowrap">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
            <span data-live-elapsed data-fmt="${compact ? 'precise-short' : 'precise'}"
                  data-elapsed-ms="${t.elapsed_ms || 0}" data-anchor="${anchor}">${escapeHtml(formatPrecise(t.elapsed_ms || 0, compact))}</span>
        </span>`;
    } else if (t.started) {
        elapsedChip = `<span class="inline-flex items-center gap-1.5 ${padX} py-0.5 rounded-full border border-gray-200 bg-gray-50 ${fs11} font-medium text-gray-600 shrink-0 whitespace-nowrap">
            <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>${escapeHtml(formatPrecise(t.elapsed_ms || 0, compact))}
        </span>`;
    } else {
        elapsedChip = `<span class="inline-flex items-center gap-1.5 ${padX} py-0.5 rounded-full border border-dashed border-gray-200 ${fs11} text-gray-400 shrink-0 whitespace-nowrap">not started</span>`;
    }

    // ── date range chip: only once there is a start to show ──
    let rangeChip = '';
    if (t.start_label) {
        const tone = t.is_done
            ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
            : 'border-gray-200 bg-gray-50 text-gray-500';
        let left  = String(t.start_label);
        let right = t.end_label || (t.running ? 'in progress' : 'paused');

        // Work that starts and finishes on one day printed that day twice —
        // "19:06:14 24/08/26 → 19:06:16 24/08/26" — which is 215px of a 256px
        // row, so the chip could not share a line with the elapsed one and a
        // finished item cost three lines. Same day: state the date once, at the
        // end. Spanning midnight still shows both in full.
        if (t.end_label) {
            const [startTime, startDate] = left.split(' ');
            const [endTime, endDate]     = String(t.end_label).split(' ');
            if (startDate && startDate === endDate) {
                left  = startTime;
                right = `${endTime} ${endDate}`;
            }
        }

        rangeChip = `<span class="inline-flex items-center ${padX} py-0.5 rounded-full border ${tone} ${fs11} shrink-0 whitespace-nowrap">
            ${escapeHtml(left)} <span class="mx-1 opacity-60">&rarr;</span> ${escapeHtml(right)}
        </span>`;
    }

    // ── controls ──
    const runBtn = t.running
        ? `<button type="button" title="Pause the timer" data-role="timer"
                onclick="event.stopPropagation(); window.${handler} && window.${handler}(${sub.id}, 'pause')"
                class="w-7 h-7 flex items-center justify-center rounded-md bg-slate-500 hover:bg-slate-600 text-white transition">
                <svg class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor"><path d="M6 4h3v12H6V4zm5 0h3v12h-3V4z"/></svg>
           </button>`
        : `<button type="button" title="Start the timer" data-role="timer"
                onclick="event.stopPropagation(); window.${handler} && window.${handler}(${sub.id}, 'start')"
                class="w-7 h-7 flex items-center justify-center rounded-md bg-slate-100 hover:bg-slate-200 text-slate-600 transition">
                <svg class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor"><path d="M6.3 3.8a1 1 0 011.02.04l7 4.2a1 1 0 010 1.72l-7 4.2A1 1 0 016 13.1V4.66a1 1 0 01.3-.86z"/></svg>
           </button>`;

    const doneBtn = `<button type="button" title="Mark done" data-role="timer"
            onclick="event.stopPropagation(); window.${handler} && window.${handler}(${sub.id}, 'done')"
            class="w-7 h-7 flex items-center justify-center rounded-md bg-blue-600 hover:bg-blue-700 text-white transition">
            <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0L3.3 9.7a1 1 0 111.4-1.4l3.8 3.8 6.8-6.8a1 1 0 011.4 0z" clip-rule="evenodd"/></svg>
        </button>`;

    // A finished item shows no timer controls at all — the row already says DONE
    // in three places and a green tick beside it is noise. Reopening is the State
    // dropdown's job: setting it back to an open column clears completed_at, and
    // the buttons come back with it.
    const controls = t.is_done ? '' :
        `<span class="ml-auto flex items-center gap-1 shrink-0">${runBtn}${doneBtn}</span>`;

    return `${elapsedChip}${rangeChip}${controls}`;
}

/* ═══════════ Load + render ═══════════ */

export function loadSubtasks(config, taskId) {
    const $list = $('#drawerSubtasksList');
    $list.html('<div class="px-3 py-4 text-center text-xs text-gray-400">Loading…</div>');

    TaskApi.getSubtasks(config.role, taskId)
        .then(data => {
            if (!data.success) return;
            renderSubtasks(data.subtasks, data.total, data.done, config, taskId, data.parent_timer);
            paintTracked(data.tracked, taskId);
        })
        .catch(() => {
            $list.html('<div class="px-3 py-4 text-center text-xs text-red-400">Failed to load sub-tasks</div>');
        });
}

function renderSubtasks(subtasks, total, done, config, taskId, parentTimer) {
    const $count = $('#drawerSubtasksCount');
    const $list  = $('#drawerSubtasksList');
    const $prog  = $('#drawerSubtasksProgress');

    $count.text(total ? `${done}/${total}` : '0');

    if (total > 0) {
        const pct = done / total;
        $prog.removeClass('hidden').addClass('flex');
        $prog.find('.subtask-ring').css('stroke-dashoffset', SUBTASK_RING_CIRCUMFERENCE * (1 - pct));
        $prog.find('.subtask-pct').text(`${done}/${total}`);
    } else {
        $prog.addClass('hidden').removeClass('flex');
    }

    // The board card behind the drawer shows the same items — hand it the data
    // we already have so the two lists cannot drift apart.
    syncCardSubtasks(taskId, subtasks, total, done, config, parentTimer);

    if (!subtasks || subtasks.length === 0) {
        $list.html('<div class="px-3 py-6 text-center text-xs text-gray-400">No sub-tasks yet. Click + to create one.</div>');
        syncTicker();
        return;
    }

    $list.html(subtasks.map(sub => renderSubtaskRow(sub, config, taskId)).join(''));
    syncTicker();
}

/**
 * Total time banked across the parent's sub-work items, and the done/total ring.
 * Both come back from every timer action, so neither needs a list refetch.
 */
function paintTracked(tracked, parentId) {
    if (!tracked) return;

    const $chip = $('#drawerSubtasksTracked');
    if (tracked.total_ms > 0) {
        $chip.removeClass('hidden').addClass('inline-flex').find('.tracked-text').text(tracked.total_text);
    } else {
        $chip.addClass('hidden').removeClass('inline-flex');
    }

    const total = tracked.total || 0;
    const $prog = $('#drawerSubtasksProgress');
    $('#drawerSubtasksCount').text(total ? `${tracked.done}/${total}` : '0');

    if (total > 0) {
        $prog.removeClass('hidden').addClass('flex');
        $prog.find('.subtask-ring').css('stroke-dashoffset', SUBTASK_RING_CIRCUMFERENCE * (1 - tracked.done / total));
        $prog.find('.subtask-pct').text(`${tracked.done}/${total}`);
    } else {
        $prog.addClass('hidden').removeClass('flex');
    }

    paintDrawerProgress(tracked.done || 0, total);

    // The drawer's parent may also be a card on the board behind it.
    if (parentId) paintCardProgress(parentId, tracked.done || 0, total);
}

/**
 * The drawer's overall progress bar — share of sub-work items done, the same
 * number the ring shows, drawn the way the reference build draws it.
 *
 * The width is set one frame late on purpose. Painting straight to the final
 * value gives no transition to run, because the element is being shown in the
 * same frame; going 0 → value across a frame boundary is what makes it fill.
 */
function paintDrawerProgress(done, total) {
    const box = document.getElementById('drawerTaskProgress');
    if (!box) return;

    if (!total) {
        box.classList.add('hidden');
        box.classList.remove('flex');
        return;
    }

    const pct  = Math.round((done / total) * 100);
    const fill = box.querySelector('.tp-fill');
    const pctEl = box.querySelector('[data-role="tp-pct"]');

    box.classList.remove('hidden');
    box.classList.add('flex');
    if (pctEl) pctEl.textContent = `${pct}%`;
    if (!fill) return;

    fill.style.width = '0%';
    requestAnimationFrame(() => requestAnimationFrame(() => { fill.style.width = `${pct}%`; }));
}

/**
 * The dial in the Kanban card's corner. Unlike the drawer's bar it does not
 * re-draw from zero — the card is already on screen and a ring that emptied
 * itself every time a sub-item was ticked would read as a glitch. One custom
 * property is the whole update: --tp-p is the percentage, the CSS turns it into
 * the arc's dash offset, and the transition on stroke-dashoffset slides it.
 *
 * Nothing here touches the colour: the arc takes the column accent
 * (--kbc-strong) it already inherits, so a card that changes column changes
 * ring colour on its own.
 */
function paintCardProgress(parentId, done, total) {
    const ring = document.querySelector(`[data-task-progress="${parentId}"]`);
    if (!ring) return;

    const pct = total ? Math.round((done / total) * 100) : 0;

    ring.style.setProperty('--tp-p', pct);

    // "100%" is a size wider than the well, so full values print a step smaller.
    ring.classList.toggle('tp-full', pct >= 100);

    const pctEl = ring.querySelector('.tp-pct');
    if (pctEl) pctEl.textContent = `${pct}%`;

    // An SVG's tooltip is a <title> child, not an attribute.
    const label = ring.querySelector('title');
    if (label) label.textContent = `${done}/${total} sub-work items done`;
    ring.setAttribute('aria-label', `${done} of ${total} sub-work items done (${pct}%)`);
}

/**
 * Start / Pause / Done on one sub-work item.
 *
 * Only the affected row's timer line is repainted — a full list reload would
 * close any dropdown the user had open and jump the scroll position. Starting
 * one item pauses its siblings server side, so those rows are repainted from
 * the fresh state too rather than being left showing a clock that has stopped.
 */
export function subtaskTimer(config, subtaskId, action) {
    TaskApi.timerAction(config.role, subtaskId, action)
        .then(data => {
            if (!data.success) {
                alert(data.message || 'Could not update the timer.');
                return;
            }

            // The same item can be on screen twice — as a row in the drawer's list
            // and as a row in its parent card's expanded panel on the board. Both
            // are found and repainted below (the selectors match on the sub-task's
            // id, not on which list it is in), so the two can never disagree.
            const parentId = window.currentDrawerTaskId;
            const boardParentId = boardParentOf(subtaskId);

            // Start and Done both reach past the timer line: Done moves the item
            // into the board's Done column and stamps the Due chip, Start stamps
            // the Start chip, and Start also pauses every sibling server side. A
            // patched timer line beside a stale "PROGRESS" pill or a stale date
            // would be a lie, so those two reload the list(s) wholesale. Pause
            // touches nothing but the clock and is patched in place below.
            if ((action === 'done' || action === 'start') && (parentId || boardParentId)) {
                if (parentId) loadSubtasks(config, parentId);
                if (boardParentId) loadCardSubtasks(config, boardParentId);
                return;
            }

            const $line = $(`[data-timer-line="${subtaskId}"]`);
            if ($line.length) $line.html(buildTimerLine({ id: subtaskId, timer: data.timer }));
            paintRowState(subtaskId, data.timer);

            paintTracked(data.tracked, parentId || boardParentId);

            // The parent card's chip is the sum of these items, so it moves
            // every time one of them does.
            if (boardParentId) paintCardTrackedTime(boardParentId, data.tracked);
            if (parentId && parentId !== boardParentId) paintCardTrackedTime(parentId, data.tracked);

            syncTicker();
        })
        .catch(err => { console.error(err); alert(err.message || 'Could not update the timer.'); });
}

/** Strikethrough + the running accent stripe, kept in step with the timer. */
function paintRowState(subtaskId, timer) {
    const $row = $(`[data-subtask-id="${subtaskId}"]`);

    $row.find('[data-subtask-title]')
        .toggleClass('line-through text-gray-400', !!timer.is_done)
        .toggleClass('text-gray-800', !timer.is_done);

    $row.attr('data-running', timer.running ? '1' : null)
        .attr('data-done', timer.is_done ? '1' : null);
}

/* ═══════════ Row-level live components ═══════════ */

function stateItemIcon(position) {
    switch (position) {
        case 2: return '<span class="inline-block w-3 h-3 rounded-full border-2 border-blue-500"></span>';
        case 3: return '<span class="w-3 h-3 rounded-full border-2 border-amber-500 flex items-center justify-center"><span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span></span>';
        case 4: return '<svg class="w-3 h-3 text-emerald-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>';
        case 5: return '<svg class="w-3 h-3 text-red-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>';
        default: return '<span class="inline-block w-3 h-3 rounded-full border-2 border-gray-400"></span>';
    }
}

function dropdownItem(value, selected, innerHtml, attrs = '') {
    return `<div data-role="dropdown-item" data-value="${value}" ${attrs} class="flex items-center gap-2 px-3 py-2 hover:bg-gray-50 cursor-pointer text-xs ${selected ? 'bg-gray-100' : ''}">
        ${innerHtml}
        <svg data-role="dropdown-check" class="w-3 h-3 ml-auto text-gray-400 ${selected ? '' : 'hidden'}" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
    </div>`;
}

function buildStateDropdown(sub, config) {
    const colors = STATE_BADGE_COLORS[sub.column_position] || STATE_BADGE_COLORS[1];
    const items = (config.columns || []).map(col =>
        dropdownItem(col.id, String(col.id) === String(sub.column_id), `${stateItemIcon(col.position)}<span>${escapeHtml(col.name)}</span>`, `data-position="${col.position}"`)
    ).join('');

    return `<div class="relative" data-dropdown="state" data-context="card" data-dropdown-id="substate-${sub.id}" data-task-id="${sub.id}" data-multi="false" style="overflow: visible;">
        <input type="hidden" data-role="dropdown-selected" value="${sub.column_id ?? ''}">
        <div data-role="dropdown-toggle" class="inline-block w-full">
            <button type="button" class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-[11px] font-medium hover:opacity-80 transition ${colors.bg} ${colors.text}">
                <span class="inline-block w-3 h-3 rounded-full border-2 border-gray-400"></span>
                <span data-role="dropdown-label">${escapeHtml(sub.column_name)}</span>
            </button>
        </div>
        <div id="panel-substate-${sub.id}" data-role="dropdown-panel" class="hidden fixed w-44 bg-white rounded-lg shadow-xl border border-gray-200 z-[9999]">
            <div class="p-2 border-b border-gray-100"><input type="text" data-role="dropdown-search" placeholder="Search..." class="w-full text-xs px-2 py-1 border rounded focus:outline-none focus:border-blue-400"></div>
            <div data-role="dropdown-items" class="py-1 overflow-y-auto" style="max-height: 280px; min-height: 50px;">${items}</div>
        </div>
    </div>`;
}

function buildPriorityDropdown(sub) {
    const colorClass = { high: 'text-red-500', medium: 'text-yellow-500', low: 'text-blue-500' }[sub.priority] || 'text-gray-400';
    const options = [
        { value: 'high', label: 'High', icon: '<i class="fa-solid fa-signal text-red-500"></i>' },
        { value: 'medium', label: 'Medium', icon: '<i class="fa-solid fa-signal text-yellow-500"></i>' },
        { value: 'low', label: 'Low', icon: '<i class="fa-solid fa-signal text-blue-500"></i>' },
        { value: '', label: 'None', icon: '<i class="fa-solid fa-minus text-gray-400"></i>' },
    ];
    const items = options.map(o => dropdownItem(o.value, (sub.priority || '') === o.value, `${o.icon}<span>${o.label}</span>`)).join('');

    return `<div class="relative" data-dropdown="priority" data-context="card" data-dropdown-id="subpriority-${sub.id}" data-task-id="${sub.id}" data-multi="false" style="overflow: visible;">
        <input type="hidden" data-role="dropdown-selected" value="${sub.priority ?? ''}">
        <div data-role="dropdown-toggle" class="inline-block w-full">
            <button type="button" class="p-1.5 rounded hover:bg-gray-100 transition" title="Priority: ${sub.priority ? escapeHtml(sub.priority.charAt(0).toUpperCase() + sub.priority.slice(1)) : 'None'}">
                <i id="priority-icon-${sub.id}" class="fa-solid fa-signal ${colorClass}"></i>
            </button>
        </div>
        <div id="panel-subpriority-${sub.id}" data-role="dropdown-panel" class="hidden fixed w-32 bg-white rounded-lg shadow-xl border border-gray-200 z-[9999]">
            <div data-role="dropdown-items" class="py-1 overflow-y-auto" style="max-height: 280px; min-height: 50px;">${items}</div>
        </div>
    </div>`;
}

function buildDateButton(sub, type) {
    const isStart = type === 'start_date';
    // Prefixed with "sub" so these ids never collide with the Kanban card's own
    // startdate-/duedate- elements when the same task is rendered as a card AND
    // as a sub-work item row at the same time (see dropdown.js for the matching
    // positioning/closing special-cases).
    const prefix = isStart ? 'substartdate' : 'subduedate';
    const raw = isStart ? sub.start_date : sub.due_date;
    const iconColor = isStart ? 'text-blue-500' : 'text-red-500';
    const label = raw ? formatDateDisplay(raw) : '';
    const inputVal = raw ? formatDatetimeLocal(raw) : '';
    const quickFn = isStart ? 'setQuickStartDate' : 'setQuickDueDate';

    return `<div class="relative overflow-visible">
        <button onclick="event.stopPropagation(); toggleDropdown('${prefix}-${sub.id}')"
                class="inline-flex items-center gap-1 px-2 py-1 rounded hover:bg-gray-100 cursor-pointer transition text-[11px] text-gray-600"
                id="${prefix}-btn-${sub.id}" data-dropdown-toggle="${prefix}-${sub.id}" title="${isStart ? 'Start' : 'Due'} Date">
            <svg class="w-3.5 h-3.5 ${iconColor}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"></rect><path d="M16 2v4M8 2v4M3 10h18"></path></svg>
            <span id="${prefix}-label-${sub.id}">${label}</span>
            ${raw ? `<span onclick="event.stopPropagation(); updateTaskField(${sub.id}, '${type}', '', null);" class="text-gray-400 hover:text-red-500 ml-0.5">&times;</span>` : ''}
        </button>
        <div id="${prefix}-${sub.id}" class="hidden fixed w-48 bg-white rounded-lg shadow-xl border border-gray-200 z-[9999] p-3">
            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">${isStart ? 'Start' : 'Due'} Date</label>
            <input type="datetime-local" value="${inputVal}" class="w-full text-xs border border-gray-200 rounded px-2 py-1.5 focus:outline-none focus:border-blue-400" onclick="event.stopPropagation()" onblur="event.stopPropagation(); updateTaskFieldNoClose(${sub.id}, '${type}', this.value, null);">
            <div class="flex gap-2 mt-2">
                <button onclick="event.stopPropagation(); ${quickFn}(${sub.id}, 'today')" class="flex-1 text-[10px] px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded">Today</button>
                <button onclick="event.stopPropagation(); ${quickFn}(${sub.id}, 'tomorrow')" class="flex-1 text-[10px] px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded">Tomorrow</button>
            </div>
        </div>
    </div>`;
}

function buildAssigneesDropdown(sub, config) {
    const assignees = sub.assignees || [];
    const selectedCsv = assignees.map(a => a.id).join(',');
    const selectedIds = new Set(assignees.map(a => String(a.id)));

    let toggleHtml;
    if (assignees.length) {
        const maxAvatars = assignees.length <= 3 ? assignees.length : 2;
        const extra = assignees.length > 3 ? assignees.length - 2 : 0;
        const avatars = assignees.slice(0, maxAvatars).map(a =>
            a.image
                ? `<img src="${escapeHtml(a.image)}" alt="${escapeHtml(a.name)}" class="w-5 h-5 rounded-full object-cover ring-2 ring-white -ml-2 first:ml-0" title="${escapeHtml(a.name)}">`
                : `<div class="w-5 h-5 rounded-full bg-indigo-500 text-white text-[10px] font-semibold flex items-center justify-center ring-2 ring-white -ml-2 first:ml-0" title="${escapeHtml(a.name)}">${escapeHtml(a.initial)}</div>`
        ).join('');
        const extraHtml = extra > 0 ? `<span class="ml-1 text-[10px] font-semibold text-gray-600 bg-gray-100 border border-gray-200 px-1.5 py-0.5 rounded-full">+${extra}</span>` : '';
        toggleHtml = `<div class="flex items-center"><div class="flex -space-x-2" data-role="dropdown-label">${avatars}</div>${extraHtml}</div>`;
    } else {
        toggleHtml = `<div class="w-5 h-5 rounded-full bg-gray-300 flex items-center justify-center text-gray-500 ring-2 ring-white" title="Unassigned"><svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/></svg></div>`;
    }

    const items = (config.users || []).map(u => {
        const selected = selectedIds.has(String(u.id));
        const avatar = u.image
            ? `<img src="${escapeHtml(u.image)}" alt="${escapeHtml(u.name)}" class="w-full h-full object-cover rounded-full">`
            : escapeHtml(u.name.charAt(0).toUpperCase());
        return dropdownItem(
            u.id, selected,
            `<div class="w-6 h-6 rounded-full bg-indigo-500 flex items-center justify-center text-xs text-white font-semibold">${avatar}</div><span>${escapeHtml(u.name)}</span>`,
            `data-name="${escapeHtml(u.name)}" data-image="${u.image ? escapeHtml(u.image) : ''}"`
        );
    }).join('');

    return `<div class="relative" data-dropdown="assignees" data-context="card" data-dropdown-id="subassignees-${sub.id}" data-task-id="${sub.id}" data-multi="true" style="overflow: visible;">
        <input type="hidden" data-role="dropdown-selected" value="${selectedCsv}">
        <div data-role="dropdown-toggle" class="inline-block w-full">
            <button type="button" class="cursor-pointer inline-flex items-center">${toggleHtml}</button>
        </div>
        <div id="panel-subassignees-${sub.id}" data-role="dropdown-panel" class="hidden fixed w-44 bg-white rounded-lg shadow-xl border border-gray-200 z-[9999]">
            <div class="p-2 border-b border-gray-100"><input type="text" data-role="dropdown-search" placeholder="Search members..." class="w-full text-xs px-2 py-1 border rounded focus:outline-none focus:border-blue-400"></div>
            <div data-role="dropdown-items" class="py-1 overflow-y-auto" style="max-height: 280px; min-height: 50px;">${items}</div>
        </div>
    </div>`;
}

function buildSubtaskMenu(sub, parentTaskId) {
    // "sub-task-menu-" (not "task-menu-") so this never collides with the Kanban
    // card's own 3-dot menu id when the same task renders as a card AND a row at once.
    return `<div class="relative shrink-0">
        <button id="sub-task-menu-btn-${sub.id}" onclick="event.stopPropagation(); toggleDropdown('sub-task-menu-${sub.id}')" class="p-1 rounded hover:bg-gray-100 text-gray-500 opacity-0 group-hover:opacity-100 transition-opacity" title="More">
            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zm0 6a2 2 0 110-4 2 2 0 010 4zm0 6a2 2 0 110-4 2 2 0 010 4z"/></svg>
        </button>
        <div id="sub-task-menu-${sub.id}" class="hidden fixed w-48 bg-white rounded-lg shadow-xl border border-gray-200 z-[9999] py-1">
            <button onclick="event.stopPropagation(); closeAllDropdowns(); window.editSubtaskItem && window.editSubtaskItem(${sub.id})" class="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 flex items-center gap-2">
                <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path d="M17.414 2.586a2 2 0 00-2.828 0L7 10.172V13h2.828l7.586-7.586a2 2 0 000-2.828z"/><path fill-rule="evenodd" d="M2 6a2 2 0 012-2h4a1 1 0 010 2H4v10h10v-4a1 1 0 112 0v4a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" clip-rule="evenodd"/></svg>
                Edit work item
            </button>
            <button onclick="event.stopPropagation(); closeAllDropdowns(); window.copySubtaskLink && window.copySubtaskLink(${sub.id})" class="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 flex items-center gap-2">
                <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path d="M12.586 2.586a2 2 0 012.828 0l2 2a2 2 0 010 2.828l-5 5a2 2 0 01-2.828 0 1 1 0 011.414-1.414.5.5 0 00.707 0l5-5a.5.5 0 000-.707l-2-2a.5.5 0 00-.707 0l-2 2A1 1 0 019.172 4l2.414-2.414z"/><path d="M7.414 7.414a2 2 0 012.828 0 1 1 0 11-1.414 1.414.5.5 0 00-.707 0l-5 5a.5.5 0 000 .707l2 2a.5.5 0 00.707 0l2-2A1 1 0 019.828 16l-2.414 2.414a2 2 0 01-2.828 0l-2-2a2 2 0 010-2.828l5-5z"/></svg>
                Copy work item link
            </button>
            <button onclick="event.stopPropagation(); closeAllDropdowns(); window.removeSubtaskFromParent && window.removeSubtaskFromParent(${sub.id}, ${parentTaskId})" class="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 flex items-center gap-2">
                <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd"/></svg>
                Remove work item
            </button>
            <button onclick="event.stopPropagation(); closeAllDropdowns(); window.deleteTask && window.deleteTask(${sub.id})" class="w-full text-left px-3 py-2 text-sm text-red-600 hover:bg-red-50 flex items-center gap-2">
                <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                Delete work item
            </button>
        </div>
    </div>`;
}

export function renderSubtaskRow(sub, config, parentTaskId) {
    // Two lines, the same shape the reference build uses for a phase: the name
    // on top, and the clock plus its controls on the line beneath. Keeping the
    // timer off the first line means the existing state/priority/date/assignee
    // controls are not squeezed by it — a squeezed date chip clips its own text.
    //
    // The left edge carries the state at a glance: emerald while the clock runs,
    // grey once done. data-running / data-done are toggled by paintRowState, so
    // the stripe follows the timer without re-rendering the row.
    //
    // The TASK-nn chip is inline, NOT a flex sibling of the title: as a flex item
    // it squeezes the title to min-content and a six-word title wraps to six lines.
    const t = sub.timer || {};
    return `
    <div class="relative pl-4 pr-3 py-2.5 border-l-2 border-transparent transition-colors cursor-pointer group
                hover:bg-gray-50/80
                data-[running=1]:border-emerald-400 data-[running=1]:bg-emerald-50/40
                data-[done=1]:border-gray-300 data-[done=1]:bg-gray-50/60"
        data-subtask-id="${sub.id}"${t.running ? ' data-running="1"' : ''}${t.is_done ? ' data-done="1"' : ''}
        onclick="if (!event.target.closest('button, input, [data-role], a')) { window.openSubtaskDrawer && window.openSubtaskDrawer(${sub.id}); }">
        <div class="flex items-center gap-2">
            <div class="flex-1 min-w-0">
                <span class="inline-block align-middle mr-1 px-1 py-px rounded bg-gray-100 text-[10px] font-semibold text-gray-500 group-hover:bg-gray-200 transition-colors">TASK-${sub.id}</span>
                <span class="text-sm ${t.is_done ? 'line-through text-gray-400' : 'text-gray-800'}" data-subtask-title>${escapeHtml(sub.title)}</span>
            </div>
            <div class="flex items-center gap-1 shrink-0">
                ${buildStateDropdown(sub, config)}
                ${buildPriorityDropdown(sub)}
                ${buildDateButton(sub, 'start_date')}
                ${buildDateButton(sub, 'due_date')}
                ${buildAssigneesDropdown(sub, config)}
                ${buildSubtaskMenu(sub, parentTaskId)}
            </div>
        </div>
        <div class="flex items-center gap-2 mt-2" data-timer-line="${sub.id}">${buildTimerLine(sub)}</div>
    </div>`;
}

/* ═══════════ Row actions ═══════════ */

export function openSubtaskDrawer(config, subtaskId) {
    // Open drawer with minimal info; the drawer fetches activities/attachments/links itself.
    // The promise is returned so a caller can act once the drawer is actually open —
    // the deep link in board-show.js selects a tab straight afterwards.
    return TaskApi.fetchTask(config.role, subtaskId)
        .then(data => {
            if (data.success && data.task) {
                window.openTaskDrawer(
                    subtaskId, data.task.title || 'Sub-task', data.task.description || '',
                    data.task.assignee_ids || [], data.task.start_date || null, data.task.due_date || null,
                    data.task.priority || null, data.task.column_id || null, data.task.label_ids || [],
                    data.task.created_by_name || '', data.task.parent_id || null, data.task.parent_title || null
                );
            }
        })
        .catch(err => { console.error(err); alert('Failed to open sub-work item.'); });
}

export function openParentDrawer(config, parentId) {
    TaskApi.fetchTask(config.role, parentId)
        .then(data => {
            if (data.success && data.task) {
                window.openTaskDrawer(
                    parentId, data.task.title || 'Task', data.task.description || '',
                    data.task.assignee_ids || [], data.task.start_date || null, data.task.due_date || null,
                    data.task.priority || null, data.task.column_id || null, data.task.label_ids || [],
                    data.task.created_by_name || '', data.task.parent_id || null, data.task.parent_title || null
                );
            }
        })
        .catch(err => { console.error(err); alert('Failed to open parent work item.'); });
}

export function editSubtaskItem(config, taskId) {
    // Reuses the existing "Edit work item" modal component (task-modal.js), the same one
    // used for regular Kanban cards — just prefilled with this sub-task's data.
    TaskApi.fetchTask(config.role, taskId)
        .then(data => {
            if (data.success && data.task) {
                window.openEditModal(
                    taskId, data.task.title || '', data.task.description || '',
                    data.task.assignee_ids || [], data.task.start_date || null, data.task.due_date || null,
                    data.task.priority || null, data.task.column_id || null, data.task.label_ids || []
                );
            }
        })
        .catch(err => { console.error(err); alert('Failed to open edit form.'); });
}

/* ── Copy link (mirrors task-link.js's copyLinkToClipboard) ── */
export function copySubtaskLink(taskId) {
    const url = `${window.location.origin}${window.location.pathname}?task=${taskId}`;
    const done = () => {
        if (typeof Swal !== 'undefined') Swal.fire({ icon: 'success', title: 'Link copied', timer: 1200, showConfirmButton: false });
    };

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(done).catch(err => console.error('Copy failed:', err));
    } else {
        // Fallback for browsers that don't support the Clipboard API
        const textArea = document.createElement('textarea');
        textArea.value = url;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        textArea.style.top = '-999999px';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        try {
            document.execCommand('copy');
            textArea.remove();
            done();
        } catch (err) {
            console.error('Fallback copy failed:', err);
            textArea.remove();
        }
    }
}

export function removeSubtaskFromParent(config, taskId, parentId) {
    if (!confirm('Remove this sub-work item? It will become a top-level task.')) return;

    TaskApi.updateField(config.role, taskId, { parent_id: null })
        // A top-level task is a card, and a card is server-rendered — its state,
        // priority, date, assignee and label dropdowns are Blade components, not
        // something this module can build. Reload so it appears in its column
        // properly instead of being invisible on the board until the next visit.
        .then(() => window.location.reload())
        .catch(err => { console.error(err); alert(err.message || 'Error removing sub-work item.'); });
}

/* ═══════════ Sub-work items on the board card ═══════════
 *
 * A sub-item is no longer a card of its own (TaskController@index lists
 * top-level tasks only), so this is where it appears: click the "0/2 sub-work"
 * chip on a parent card and its items open underneath the footer.
 *
 * A board row is the title and the clock, and nothing else — owner's call: the
 * card is a summary, not a second editor. State, priority, assignee, the dates
 * and the "..." menu all stay in the drawer, which the row opens on click; the
 * drawer's own list is untouched and still carries the lot.
 *
 * buildTimerLine is reused exactly as the drawer uses it, so Start/Pause/Done
 * behave identically wherever the row is read from, and an item on screen in
 * both places repaints in both (the selector matches on the sub-task's id, not
 * on which list it is in).
 */

/**
 * The parent's total from the sub-item list already in hand — same sum
 * trackedPayload() does server side, so the chip can repaint without a second
 * request. Null when there is nothing to add up, which is the signal to fall
 * back to the parent's own clock.
 */
function cardTrackedFrom(subtasks, total) {
    if (!subtasks || !subtasks.length) return null;

    let ms = 0;
    let running = false;

    subtasks.forEach(sub => {
        const t = sub.timer || {};
        ms += t.elapsed_ms || 0;
        if (t.running) running = true;
    });

    return { total_ms: ms, total_text: formatDuration(ms), running, total: total ?? subtasks.length };
}

/** The parent card id a sub-item is expanded under, or null if it is not. */
function boardParentOf(subtaskId) {
    const row = document.querySelector(`[data-subtask-panel] [data-subtask-id="${subtaskId}"]`);
    return row ? (row.closest('[data-subtask-panel]')?.dataset.subtaskPanel || null) : null;
}

/** Circumference of the chip's ring: r=7 in a 20-unit box. Keep in step with
 *  the <circle> in task-module/task/show.blade.php. */
const RING_C = 43.98;

/** "1/2" on the card's chip — the one thing a collapsed card still shows. */
function paintCardChip(parentId, done, total) {
    const chip = document.querySelector(`[data-subtask-progress="${parentId}"]`);
    if (chip) chip.textContent = `${done ?? 0}/${total ?? 0}`;

    // The ring beside the count says the same thing without being read. Drawn
    // by hiding the share NOT done, so 0/0 and 0/5 both leave a bare track and
    // a finished task leaves a closed circle.
    const ring = document.querySelector(`#subtask-toggle-${parentId} [data-role="subtask-ring"]`);
    if (ring) {
        const pct = total ? (done ?? 0) / total : 0;
        ring.style.strokeDashoffset = (RING_C * (1 - pct)).toFixed(2);
        ring.classList.toggle('text-blue-600', total > 0 && (done ?? 0) >= total);
        ring.classList.toggle('text-blue-500', !(total > 0 && (done ?? 0) >= total));
    }

    // The bar above the chip counts the same pair — repaint it here so the two
    // can never disagree, whichever path changed the counts.
    paintCardProgress(parentId, done ?? 0, total ?? 0);

    // The count is the whole chip now, so the tooltip is where "sub-work items"
    // is actually spelled out — it has to follow the count, not go stale.
    const toggle = document.getElementById(`subtask-toggle-${parentId}`);
    if (toggle) {
        const shown = toggle.dataset.expanded === '1' ? 'hide them' : 'click to show them';
        toggle.title = `${done ?? 0} of ${total ?? 0} sub-work items done — ${shown}`;
    }
}

/**
 * The card's time chip — the one between the comments and the sub-work ring.
 *
 * One chip, two sources, exactly as the Blade builds it:
 *   has sub-work items  →  the SUM of their time
 *   has none            →  the task's own clock
 *
 * Shown whenever there is time to show, INCLUDING after Done: a finished card
 * has to state what it cost. Hidden only while nothing has ever run.
 *
 * `ms`/`text`/`running` rather than a payload shape, because it is fed from
 * three different server answers — timerPayload (elapsed_ms/elapsed_text),
 * trackedPayload (total_ms/total_text) and the move response — and the chip
 * should not have to know which.
 *
 * Everything is toggled by inline display and NOT the `hidden` class: `hidden`
 * and `inline-flex` are Tailwind utilities of equal specificity, so which wins
 * depends on their order in the bundle.
 */
export function paintCardTime(parentId, { ms = 0, text = '', running = false, what = '' } = {}) {
    const chip = document.querySelector(`[data-card-time="${parentId}"]`);
    if (!chip) return;                                // markup predates this

    const show = running || ms > 0;
    chip.style.display = show ? 'inline-flex' : 'none';
    if (!show) return;

    const dot  = chip.querySelector('[data-role="ct-dot"]');
    const icon = chip.querySelector('[data-role="ct-icon"]');
    const val  = chip.querySelector('[data-role="ct-val"]');

    if (dot)  dot.style.display  = running ? 'block' : 'none';
    if (icon) icon.style.display = running ? 'none' : 'block';

    chip.classList.toggle('text-emerald-600', running);
    chip.classList.toggle('text-gray-500', !running);

    if (val) {
        val.dataset.elapsedMs = String(ms);
        /* Its own form rather than the server's rounded elapsed_text: a chip
           that only changes once a minute cannot be told apart from a broken
           one, and "55m 12s" is no wider than "55 min". Past an hour the
           seconds are dropped — see formatCard. */
        val.textContent = formatCard(ms);

        /* The ticker paints every node carrying data-live-elapsed, so the
           attribute has to come OFF when the clock stops — left on, a paused
           chip would go on counting up on screen while the server had it
           standing still. */
        if (running) {
            val.dataset.liveElapsed = '';
            val.dataset.anchor = String(Date.now());
        } else {
            delete val.dataset.liveElapsed;
            delete val.dataset.anchor;
        }
    }

    // The tooltip spells it out in full; the chip stays short.
    const label = formatPrecise(ms);
    chip.title = `${running ? 'Running — ' : 'Tracked '}${label} ${what || 'on this task'}`;

    syncTicker();
}

/** From a timerPayload — a task's own clock. */
export function paintCardOwnTime(parentId, timer) {
    if (!timer) return;
    paintCardTime(parentId, {
        ms: timer.elapsed_ms || 0,
        text: timer.elapsed_text === 'not started' ? '' : timer.elapsed_text,
        running: !!timer.running,
        what: 'on this task',
    });
}

/** From a trackedPayload — the sum across a parent's sub-work items. */
export function paintCardTrackedTime(parentId, tracked) {
    if (!tracked) return;
    paintCardTime(parentId, {
        ms: tracked.total_ms || 0,
        text: tracked.total_text,
        running: !!tracked.running,
        what: 'across its sub-work items',
    });
}

/**
 * Whichever of the two this card is. The server sends both on a move and after
 * a sub-item action; the count decides which one the chip is showing, and it is
 * the same rule the Blade applies.
 */
export function paintCardTimeFor(parentId, { timer, tracked, total }) {
    const hasSubs = typeof total === 'number'
        ? total > 0
        : (tracked ? (tracked.total || 0) > 0 : false);

    if (hasSubs) paintCardTrackedTime(parentId, tracked);
    else paintCardOwnTime(parentId, timer);
}

/**
 * The task's own Start / Pause / Done, at the top of its expanded panel.
 *
 * Only drawn when there are no sub-work items: from the first sub-item on, the
 * parent's time is the sum of its children's and a clock on the parent would
 * count the same work twice (storeSubtask stops it server side for the same
 * reason).
 */
function cardOwnTimerRow(parentId, timer) {
    return `
    <div class="rounded-lg border border-gray-300 border-l-2 border-l-emerald-300 bg-white px-2 py-1.5 mb-1.5"
         data-own-timer="${parentId}"${timer && timer.running ? ' data-running="1"' : ''}>
        <div class="flex items-start flex-nowrap gap-1.5">
            <span class="mt-px inline-block px-1 py-px rounded bg-gray-100 text-[9px] font-semibold text-gray-500 shrink-0">THIS TASK</span>
            <span class="flex-1 min-w-0 text-[11px] leading-snug text-gray-500">Nothing under it — the task itself is the work</span>
        </div>
        <div class="flex items-center flex-wrap gap-1.5 mt-1.5 [&>span:nth-child(2)]:order-last"
             data-timer-line="own-${parentId}">${buildTimerLine({ id: parentId, timer }, true, 'ownTaskTimer')}</div>
    </div>`;
}

/**
 * Start / Pause / Done on the task itself.
 *
 * Same three endpoints the sub-items use — they take any task — but the repaint
 * is different: there is no row in a list to patch, so the panel is rebuilt and
 * the card's chip follows the clock.
 */
export function ownTaskTimer(config, taskId, action) {
    TaskApi.timerAction(config.role, taskId, action)
        .then(data => {
            if (!data.success) {
                alert(data.message || 'Could not update the timer.');
                return;
            }

            const $line = $(`[data-timer-line="own-${taskId}"]`);
            if ($line.length) $line.html(buildTimerLine({ id: taskId, timer: data.timer }, true, 'ownTaskTimer'));

            $(`[data-own-timer="${taskId}"]`).attr('data-running', data.timer.running ? '1' : null);
            paintCardOwnTime(taskId, data.timer);

            /* Done drops the task into the Done column and Start can lift it
               out of To Do. Neither is visible from inside the panel, so a card
               that actually moved is stale in every other way too — reload. A
               Pause, or a Start on a task already in progress, moves nothing
               and must not cost the user their scroll position. */
            const card = document.querySelector(`.task-item[data-id="${taskId}"]`);
            const wasIn = card?.closest('.task-list')?.dataset.column;
            if (data.column_id && wasIn && String(data.column_id) !== String(wasIn)) {
                window.location.reload();
                return;
            }

            syncTicker();
        })
        .catch(err => { console.error(err); alert(err.message || 'Could not update the timer.'); });
}

/**
 * The chip and the panel are now server-rendered on EVERY card — a task with
 * nothing under it shows 0/0 rather than nothing at all — so in the normal case
 * this has no work to do and returns after one lookup.
 *
 * It is kept for the card whose HTML predates that change: a tab left open
 * across the deploy, or anything else holding the old markup where the chip
 * only existed once the first sub-item did. Adding that first item from the
 * drawer has to bring the chip and panel into being, otherwise the new item is
 * invisible on the board until a refresh — the exact hiding this whole change
 * set out to stop.
 *
 * It no longer REMOVES anything: deleting the last sub-item leaves the chip
 * reading 0/0, because the chip is now the way back in.
 *
 * Mirrors the markup in task-module/task/show.blade.php; keep the two in step.
 */
function ensureCardChip(parentId, total) {
    const card = document.querySelector(`.task-item[data-id="${parentId}"]`);
    if (!card) return;

    const actions = card.querySelector('[data-role="card-actions"]');
    if (!actions) return;

    if (!card.querySelector('[data-role="subtask-toggle"]')) {
        // Left of the move arrows, the same place Blade puts it, so a chip added
        // live and one rendered on load sit in the same order.
        actions.insertAdjacentHTML('afterbegin', `
            <button type="button" id="subtask-toggle-${parentId}" data-role="subtask-toggle" data-expanded="0"
                    onclick="event.stopPropagation(); window.toggleCardSubtasks && window.toggleCardSubtasks(${parentId})"
                    class="inline-flex h-6 items-center gap-1 pl-1 pr-1.5 rounded-md text-[11px] font-medium tabular-nums text-gray-500 hover:text-gray-800 hover:bg-gray-100 transition-colors"
                    title="0 of ${total ?? 0} sub-work items done — click to show them">
                <svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <circle cx="10" cy="10" r="7" stroke="currentColor" stroke-width="3" opacity="0.25"/>
                    <circle data-role="subtask-ring" cx="10" cy="10" r="7"
                            stroke="currentColor" stroke-width="3" stroke-linecap="round"
                            transform="rotate(-90 10 10)" class="tk-ring-fill text-blue-500"
                            style="stroke-dasharray: ${RING_C}; stroke-dashoffset: ${RING_C};"/>
                </svg>
                <span data-subtask-progress="${parentId}">0/${total ?? 0}</span>
            </button>`);
    }

    // The + button this chip replaced, on any card still carrying it.
    card.querySelector('[data-role="add-subwork"]')?.remove();

    if (!card.querySelector(`[data-subtask-panel="${parentId}"]`)) {
        card.insertAdjacentHTML('beforeend',
            `<div id="card-subtasks-${parentId}" data-subtask-panel="${parentId}" class="hidden mt-2.5 pt-2 border-t border-gray-100"></div>`);
    }
}

/**
 * One sub-work item inside a card's expanded panel on the board.
 *
 * Title and clock only. Priority and assignee are deliberately NOT here — the
 * board is for seeing where work stands, and the panel row has 256px to say it
 * in. Both live one click away on the same item's row inside the task drawer.
 */
export function renderCardSubtaskRow(sub) {
    const t = sub.timer || {};

    // The timer line is buildTimerLine's own output — elapsed chip, date range
    // chip, then the buttons. At 272px the range chip alone fills a line, which
    // left the buttons stranded on a third one; sending the second child last
    // puts the buttons up beside the elapsed chip and drops the range beneath,
    // costing one line. The rule is a no-op when there is no range chip yet
    // (nothing started), because then the second child IS the button group.

    return `
    <div class="rounded-lg border border-gray-300 border-l-2 border-l-gray-300 bg-white px-2 py-1.5 mb-1.5 last:mb-0 cursor-pointer transition-colors hover:bg-gray-50
                data-[running=1]:border-l-emerald-400 data-[running=1]:bg-emerald-50/50
                data-[done=1]:bg-gray-50"
        data-subtask-id="${sub.id}"${t.running ? ' data-running="1"' : ''}${t.is_done ? ' data-done="1"' : ''}
        onclick="if (!event.target.closest('button, input, [data-role], a')) { window.openSubtaskDrawer && window.openSubtaskDrawer(${sub.id}); }">
        <div class="flex items-start flex-nowrap gap-1.5">
            <span class="mt-px inline-block px-1 py-px rounded bg-gray-100 text-[9px] font-semibold text-gray-500 shrink-0">TASK-${sub.id}</span>
            <span class="flex-1 min-w-0 text-[12px] leading-snug break-words ${t.is_done ? 'line-through text-gray-400' : 'text-gray-800'}" data-subtask-title>${escapeHtml(sub.title)}</span>
        </div>
        <div class="flex items-center flex-wrap gap-1.5 mt-1.5 [&>span:nth-child(2)]:order-last"
             data-timer-line="${sub.id}">${buildTimerLine(sub, true)}</div>
    </div>`;
}

/**
 * Paint one card's panel from data already in hand. Shared by the fetch below
 * and by the drawer's own render, so working in the drawer keeps the card
 * behind it honest instead of leaving a stale list on the board.
 */
function renderCardSubtasks(panel, subtasks, total, done, config, parentTimer) {
    const parentId = panel.dataset.subtaskPanel;
    paintCardChip(parentId, done, total);
    paintCardTimeFor(parentId, {
        timer: parentTimer,
        tracked: cardTrackedFrom(subtasks, total),
        total,
    });

    const empty = !subtasks || !subtasks.length;

    /* Nothing under it: the task IS the work item, so the panel opens on its
       own clock rather than on "No sub-work items yet." — which is what the
       chip already said. Add one sub-item and this row goes, because the time
       then comes from the children. */
    const rows = empty
        ? (parentTimer
            ? cardOwnTimerRow(parentId, parentTimer)
            : '<div class="px-2 py-2 text-center text-[11px] text-gray-400">No sub-work items yet.</div>')
        : subtasks.map(sub => renderCardSubtaskRow(sub)).join('');

    panel.innerHTML = rows + cardSubtaskAddRow(parentId);
    syncTicker();
}

/**
 * "Add sub-work item", at the foot of the expanded list.
 *
 * This is where creating one from the board lives now that the footer's +
 * button is gone: the chip opens the list, the list offers the add. It costs a
 * click over the old + but it puts the action next to the thing it adds to, and
 * it is the only reason the chip is rendered on a task with no sub-items — 0/0
 * has to open onto something you can act on.
 *
 * Same modal the drawer's "Add sub-work item ▸ Create new" opens;
 * openCreateSubtaskModal takes the parent id, so no drawer is needed behind it.
 */
function cardSubtaskAddRow(parentId) {
    return `
    <button type="button" data-role="panel-add-subwork"
            onclick="event.stopPropagation(); window.openCreateSubtaskModal && window.openCreateSubtaskModal(${parentId})"
            class="mt-1.5 w-full inline-flex items-center justify-center gap-1 rounded-lg border border-dashed border-gray-300 px-2 py-1.5 text-[11px] font-medium text-gray-500 hover:border-blue-400 hover:text-blue-600 hover:bg-blue-50 transition-colors">
        <svg class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/>
        </svg>
        Add sub-work item
    </button>`;
}

/**
 * Bring a card in line after a sub-item was created or deleted elsewhere.
 *
 * Unlike loadCardSubtasks this does NOT give up when the card has no panel: a
 * card with no sub-items has neither chip nor panel, and creating its first one
 * from the board's + button has to bring both into existence. syncCardSubtasks
 * → ensureCardChip does exactly that, then paints the panel if it is open.
 */
export function refreshCardSubtasks(config, parentId) {
    if (!document.querySelector(`.task-item[data-id="${parentId}"]`)) return;

    TaskApi.getSubtasks(config.role, parentId)
        .then(data => {
            if (!data.success) return;
            syncCardSubtasks(parentId, data.subtasks, data.total, data.done, config, data.parent_timer);
        })
        .catch(() => { /* the board is stale but intact; a refresh fixes it */ });
}

export function loadCardSubtasks(config, parentId) {
    const panel = document.querySelector(`[data-subtask-panel="${parentId}"]`);
    if (!panel) return;

    TaskApi.getSubtasks(config.role, parentId)
        .then(data => {
            if (!data.success) return;
            renderCardSubtasks(panel, data.subtasks, data.total, data.done, config, data.parent_timer);
        })
        .catch(() => {
            panel.innerHTML = '<div class="px-2 py-3 text-center text-[11px] text-red-400">Failed to load sub-work items.</div>';
        });
}

/**
 * The drawer just re-rendered its list for this parent — if the same parent's
 * card is expanded on the board behind it, give it the same data rather than
 * a second request, and keep its chip right either way.
 */
export function syncCardSubtasks(parentId, subtasks, total, done, config, parentTimer) {
    ensureCardChip(parentId, total);

    // The chip follows the clock whether the panel is open or not — a card can
    // be counting with nothing expanded.
    paintCardTimeFor(parentId, {
        timer: parentTimer,
        tracked: cardTrackedFrom(subtasks, total),
        total,
    });

    const panel = document.querySelector(`[data-subtask-panel="${parentId}"]`);
    if (!panel) return;

    paintCardChip(parentId, done, total);
    if (panel.classList.contains('hidden')) return;

    renderCardSubtasks(panel, subtasks, total, done, config, parentTimer);
}

/**
 * Expand / collapse one card's sub-work list.
 *
 * Collapsing empties the panel rather than just hiding it: the rows carry live
 * clocks, and an emptied panel takes its ticker nodes with it (syncTicker then
 * stops the interval) instead of leaving them counting behind a hidden div.
 * The re-fetch on the next expand is one small request and guarantees the list
 * is current.
 */
export function toggleCardSubtasks(config, parentId) {
    const panel  = document.querySelector(`[data-subtask-panel="${parentId}"]`);
    const toggle = document.getElementById(`subtask-toggle-${parentId}`);
    if (!panel) return;

    const expand = panel.classList.contains('hidden');

    // A collapsed card is a fixed 283 × 150 box (.tk-card in show.blade.php) —
    // the standard size the board is built on. An expanded one cannot be: the
    // sub-work list has to grow it, and the fixed height would cut the list off
    // at whatever fell past 150px. .tk-expanded releases the height for exactly
    // as long as the panel is open.
    panel.closest('.tk-card')?.classList.toggle('tk-expanded', expand);

    if (expand) {
        panel.classList.remove('hidden');
        panel.innerHTML = '<div class="px-2 py-3 text-center text-[11px] text-gray-400">Loading…</div>';
        loadCardSubtasks(config, parentId);
    } else {
        panel.classList.add('hidden');
        panel.innerHTML = '';
        syncTicker();
    }

    if (toggle) {
        toggle.dataset.expanded = expand ? '1' : '0';
        // The chip has no border or resting fill any more, so expanded is said
        // with colour alone — and the resting grey is taken off rather than left
        // underneath, where whichever rule the stylesheet emits last would win.
        toggle.classList.toggle('bg-blue-50', expand);
        toggle.classList.toggle('text-blue-600', expand);
        toggle.classList.toggle('text-gray-500', !expand);
        // Keep the count, swap only the instruction — paintCardChip writes the
        // same sentence when the numbers change.
        const counts = toggle.querySelector('[data-subtask-progress]')?.textContent?.split('/') || ['0', '0'];
        toggle.title = `${counts[0]} of ${counts[1]} sub-work items done — ${expand ? 'hide them' : 'click to show them'}`;
    }
}
