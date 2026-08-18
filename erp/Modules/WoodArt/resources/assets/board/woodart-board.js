/* ============================================================================
 * WOOD ART · STAGE BOARD — drag a project card to change its stage.
 * ----------------------------------------------------------------------------
 * WHY THIS FILE LIVES HERE, AND NOT IN THE VIEW
 *
 * CLAUDE.md forbids a <script> inside [data-wa-view]: woodart-nav.js replaces
 * that region wholesale on every in-suite navigation, so a script inside it
 * would be discarded without ever re-running. Scripts belong OUTSIDE the
 * swapped region — loaded once by the suite shell, surviving every swap. This
 * is that script, published to public/woodart/board/ and referenced only by
 * Wood Art views.
 *
 * WHAT IT IS ALLOWED TO TOUCH (CLAUDE.md rule 2)
 *
 * Every listener below is delegated on `document` — it has to be, because the
 * board it acts on is destroyed and rebuilt by the nav script — but each one
 * immediately narrows to `[data-wa-board]` and returns otherwise. That
 * attribute is emitted by exactly one Blade file, Wood Art's own stage board.
 * No other company's markup carries it, so nothing else on any page can be
 * read or written by this file.
 *
 * PROGRESSIVE ENHANCEMENT
 *
 * The board works fully without this script: every card also carries posted
 * arrow buttons that do the same thing with a normal form submit. Dragging is
 * the faster path for a mouse; the arrows remain the keyboard and touch path,
 * and the only path if this file fails to load. Nothing here is load-bearing.
 * ==========================================================================*/
(function () {
    'use strict';

    var BOARD = '[data-wa-board]';
    var dragged = null;          /* the card element currently in hand */
    var fromList = null;         /* where it started, so a failure can undo   */

    function boardOf(node) {
        return node && node.closest ? node.closest(BOARD) : null;
    }

    function csrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }

    /* Column counts are derived from the DOM after every move, so they cannot
       drift from what is actually on the board. */
    function recount(board) {
        board.querySelectorAll('[data-wa-drop]').forEach(function (list) {
            var n = list.querySelectorAll('[data-wa-card]').length;
            var badge = list.parentNode.querySelector('[data-wa-count]');
            if (badge) badge.textContent = String(n);

            /* The authored "Nothing at …" placeholder is a sibling of the cards;
               hide it while the column has any, restore it when it empties. */
            var empty = list.querySelector('.wap-empty');
            if (empty) empty.style.display = n ? 'none' : '';
        });
    }

    /* ---- drag ------------------------------------------------------------ */

    document.addEventListener('dragstart', function (e) {
        var card = e.target.closest ? e.target.closest('[data-wa-card]') : null;
        if (!card || !boardOf(card)) return;

        dragged = card;
        fromList = card.parentNode;
        card.classList.add('wap-dragging');
        /* Firefox refuses to start a drag unless something is set. */
        try { e.dataTransfer.setData('text/plain', card.getAttribute('data-wa-card')); } catch (err) {}
        e.dataTransfer.effectAllowed = 'move';
    });

    document.addEventListener('dragend', function () {
        if (dragged) dragged.classList.remove('wap-dragging');
        document.querySelectorAll('.wap-drag-over').forEach(function (c) {
            c.classList.remove('wap-drag-over');
        });
        dragged = null;
        fromList = null;
    });

    document.addEventListener('dragover', function (e) {
        if (!dragged) return;
        var list = e.target.closest ? e.target.closest('[data-wa-drop]') : null;
        if (!list || !boardOf(list)) return;

        e.preventDefault();                      /* this is what permits a drop */
        e.dataTransfer.dropEffect = 'move';
        var col = list.closest('.wap-kb-col');
        if (col) col.classList.add('wap-drag-over');
    });

    document.addEventListener('dragleave', function (e) {
        var list = e.target.closest ? e.target.closest('[data-wa-drop]') : null;
        if (!list || !boardOf(list)) return;
        /* Ignore the events fired while crossing the column's own children. */
        if (list.contains(e.relatedTarget)) return;
        var col = list.closest('.wap-kb-col');
        if (col) col.classList.remove('wap-drag-over');
    });

    document.addEventListener('drop', function (e) {
        if (!dragged) return;
        var list = e.target.closest ? e.target.closest('[data-wa-drop]') : null;
        var board = boardOf(list);
        if (!list || !board) return;

        e.preventDefault();
        var col = list.closest('.wap-kb-col');
        if (col) col.classList.remove('wap-drag-over');

        var stage = list.getAttribute('data-wa-drop');
        var card = dragged;
        var origin = fromList;
        if (list === origin) return;             /* dropped where it began */

        /* Move it immediately — waiting on the network before the card follows
           the cursor is what makes a board feel broken — then persist, and put
           it back if the server refuses. */
        var placeholder = list.querySelector('.wap-empty');
        if (placeholder) list.insertBefore(card, placeholder); else list.appendChild(card);
        recount(board);
        save(board, card, stage, origin);
    });

    /* ---- persist --------------------------------------------------------- */

    function save(board, card, stage, origin) {
        var tpl = board.getAttribute('data-wa-stage-url') || '';
        var url = tpl.replace('__ID__', encodeURIComponent(card.getAttribute('data-wa-card')));

        /* Which field the endpoint expects. Projects moves a `stage`; Workshop
           moves a `status`. The default keeps Projects' markup untouched, so a
           board that predates this line behaves exactly as it did. */
        var field = board.getAttribute('data-wa-field') || 'stage';

        /* Built key-by-key rather than with a computed literal: this file is
           deliberately ES5, like every other script in the suite. */
        var payload = { _method: 'PATCH' };
        payload[field] = stage;

        fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf(),
                'X-HTTP-Method-Override': 'PATCH'
            },
            body: JSON.stringify(payload)
        })
            .then(function (r) { return r.ok ? r.json() : Promise.reject(r); })
            .then(function (data) {
                card.classList.add('wap-landed');
                setTimeout(function () { card.classList.remove('wap-landed'); }, 1200);

                /* Landing on Completed rewrites progress server-side; reflect
                   the number the server actually stored rather than guessing. */
                if (typeof data.progress === 'number') {
                    var bar = card.querySelector('[data-wa-bar]');
                    var pct = card.querySelector('[data-wa-pct]');
                    if (bar) bar.style.width = data.progress + '%';
                    if (pct) pct.textContent = data.progress + '%';
                }
            })
            .catch(function () {
                /* Put it back exactly where it came from and say so — a silent
                   failure would leave the screen disagreeing with the database,
                   which is worse than no drag at all. */
                if (origin) {
                    var ph = origin.querySelector('.wap-empty');
                    if (ph) origin.insertBefore(card, ph); else origin.appendChild(card);
                }
                recount(board);
                card.classList.add('wap-reverted');
                setTimeout(function () { card.classList.remove('wap-reverted'); }, 1000);
            });
    }
})();
