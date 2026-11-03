/**
 * board-lazy.js — infinite scroll for a "done" column on the task board.
 *
 * Each such column ends with a [data-kbc-more] sentinel (partials/board-more).
 * When it scrolls near view, the next page of cards is fetched from
 * TaskController::boardColumnTasks and appended; shimmer placeholders show
 * while it loads, a retry button if it fails.
 *
 * The sentinel carries its own state (offset, remaining) because
 * refreshBoardListsInPlace() swaps the list's innerHTML wholesale — a fresh
 * sentinel arrives with each refresh and kbcObserve() picks it up.
 */

const observers = new WeakMap();
const loading = new WeakSet();

function listOf(more) {
    return more.closest('.task-list');
}

/** Cards in the column + those not fetched yet (what the badge should read). */
export function kbcCount(list) {
    const more = list.querySelector('[data-kbc-more]');
    const remaining = more ? parseInt(more.dataset.remaining || '0', 10) : 0;
    return list.querySelectorAll('.task-item').length + remaining;
}

export async function kbcLoadMore(more) {
    if (!more || loading.has(more)) return;
    if (parseInt(more.dataset.remaining || '0', 10) <= 0) return;

    const list = listOf(more);
    const skel = more.querySelector('.kbc-skel-wrap');
    const retry = more.querySelector('.kbc-more-retry');
    const msg = more.querySelector('[data-kbc-msg]');

    loading.add(more);
    if (retry) retry.hidden = true;
    if (skel) skel.hidden = false;

    try {
        const sep = more.dataset.url.includes('?') ? '&' : '?';
        const res = await fetch(`${more.dataset.url}${sep}offset=${more.dataset.offset}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json();

        const tpl = document.createElement('template');
        tpl.innerHTML = data.html.trim();

        // Offsets can shift if something moved into Done meanwhile, so never
        // trust that a card is new: skip any already on screen.
        const have = new Set(
            [...list.querySelectorAll('.task-item[data-id]')].map((el) => el.dataset.id)
        );
        [...tpl.content.children].forEach((card) => {
            const id = card.dataset ? card.dataset.id : null;
            if (id && have.has(id)) return;
            more.before(card);
        });

        more.dataset.offset = String(data.next_offset);
        const left = parseInt(more.dataset.remaining || '0', 10) - data.count;
        more.dataset.remaining = String(data.has_more ? Math.max(left, 1) : 0);

        if (!data.has_more) {
            observers.get(list)?.unobserve(more);
            if (msg) msg.textContent = 'All shown';
        }
    } catch (e) {
        console.error('board lazy load failed', e);
        if (retry) retry.hidden = false;
    } finally {
        if (skel) skel.hidden = true;
        loading.delete(more);
        // Still in view after the append (short page)? keep going.
        const obs = observers.get(list);
        if (obs && more.isConnected && more.dataset.remaining !== '0') {
            obs.unobserve(more);
            obs.observe(more);
        }
    }
}

/** Watch every sentinel currently in the DOM. Safe to call repeatedly. */
export function kbcObserve() {
    if (!('IntersectionObserver' in window)) return;
    document.querySelectorAll('[data-kbc-more]').forEach((el) => {
        if (el.dataset.remaining === '0') return;
        // One observer per column, rooted on that column's own scrolling list:
        // rootMargin only widens the root, so start loading ~300px before the
        // end by making the list the root.
        const list = listOf(el);
        let obs = observers.get(list);
        if (!obs) {
            obs = new IntersectionObserver(
                (entries) => {
                    entries.forEach((entry) => {
                        if (entry.isIntersecting) kbcLoadMore(entry.target);
                    });
                },
                { root: list, rootMargin: '0px 0px 300px 0px', threshold: 0 }
            );
            observers.set(list, obs);
        }
        obs.unobserve(el);
        obs.observe(el);
    });
}

/** Period picker: reload the board with ?done=… (keeps other query params). */
export function kbcDoneFilter(box) {
    const select = box.querySelector('select');
    const dates = box.querySelector('.kbc-filter-dates');
    const key = select.value;
    if (key === 'custom' && dates.hidden) {
        dates.hidden = false;
        return;
    }
    dates.hidden = key !== 'custom';

    const url = new URL(window.location.href);
    ['done', 'done_from', 'done_to'].forEach((k) => url.searchParams.delete(k));
    url.searchParams.set('done', key);
    if (key === 'custom') {
        const from = box.querySelector('[data-kbc-from]').value;
        const to = box.querySelector('[data-kbc-to]').value;
        if (!from && !to) return;
        if (from) url.searchParams.set('done_from', from);
        if (to) url.searchParams.set('done_to', to);
    }
    window.location.href = url.toString();
}

window.kbcCount = kbcCount;
window.kbcLoadMore = kbcLoadMore;
window.kbcObserve = kbcObserve;
window.kbcDoneFilter = kbcDoneFilter;

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', kbcObserve);
} else {
    kbcObserve();
}
