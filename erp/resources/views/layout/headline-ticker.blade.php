@auth
@php
$headlineRole = \Illuminate\Support\Str::slug(Auth::user()->getRoleNames()->first());
@endphp

<style>
    .headline-ticker {
        --hn-accent: #7c3aed;
        --hn-ink: #475569;
        --hn-border: #e5e7eb;
        display: flex;
        align-items: center;
        height: 42px;
        margin: 0 16px 12px;
        overflow: hidden;
        border: 1px solid var(--hn-border);
        border-radius: 10px;
        background: #fff;
        box-shadow: 0 8px 22px rgba(15, 23, 42, .06);
    }

    .headline-ticker-label {
        display: flex;
        align-items: center;
        gap: 8px;
        align-self: stretch;
        padding: 0 14px;
        color: #fff;
        background: linear-gradient(135deg, #4f46e5, #a855f7);
        font-size: 11px;
        font-weight: 900;
        letter-spacing: .8px;
        white-space: nowrap;
    }

    .headline-ticker-pulse {
        width: 8px;
        height: 8px;
        border-radius: 999px;
        background: #fff;
        box-shadow: 0 0 0 0 rgba(255, 255, 255, .75);
        animation: headlinePulse 1.5s infinite;
    }

    .headline-ticker-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 22px;
        height: 22px;
        padding: 0 7px;
        border-radius: 999px;
        background: rgba(255, 255, 255, .22);
        font-size: 11px;
    }

    .headline-ticker-viewport {
        position: relative;
        flex: 1 1 auto;
        height: 100%;
        overflow: hidden;
        display: flex;
        align-items: center;
    }

    .headline-ticker-track {
        display: flex;
        width: max-content;
        will-change: transform;
    }

    /* Only a track that overruns the viewport scrolls - and only that track gets
       the duplicate group behind it. A short list sits still, printed once. */
    .headline-ticker.is-scrolling .headline-ticker-track {
        animation: headlineScroll var(--hn-scroll-duration, 52s) linear infinite;
    }

    .headline-ticker.paused .headline-ticker-track,
    .headline-ticker:hover .headline-ticker-track {
        animation-play-state: paused;
    }

    /* Separators divide items from the ones that follow; a still track has
       nothing after its last row. */
    .headline-ticker:not(.is-scrolling) .headline-ticker-sep:last-child {
        display: none;
    }

    .headline-ticker-group {
        display: flex;
        align-items: center;
        flex: 0 0 auto;
    }

    .headline-ticker-item {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        flex: 0 0 auto;
        border: 0;
        background: transparent;
        color: var(--hn-ink);
        font-size: 13px;
        font-weight: 600;
        font-style: italic;
        line-height: 1;
        padding: 0 2px;
        cursor: pointer;
        white-space: nowrap;
    }

    .headline-ticker-item:hover .headline-ticker-text {
        color: #4f46e5;
        text-decoration: underline;
        text-underline-offset: 3px;
    }

    .headline-ticker-icon {
        width: 18px;
        height: 18px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 5px;
        background: #eef2ff;
        color: #4f46e5;
        font-style: normal;
        font-weight: 900;
    }

    .headline-ticker-badge {
        padding: 4px 9px;
        border-radius: 7px;
        background: #f3edff;
        color: #7c3aed;
        font-size: 10px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: .4px;
        font-style: normal;
    }

    .headline-ticker-item.type-reminder .headline-ticker-badge {
        background: #f3edff;
        color: #7c3aed;
    }

    .headline-ticker-item.type-notice .headline-ticker-badge {
        background: #e0e7ff;
        color: #4f46e5;
    }

    .headline-ticker-item.type-followup .headline-ticker-badge {
        background: #ccfbf1;
        color: #0f766e;
    }

    .headline-ticker-item.type-approval .headline-ticker-badge {
        background: #fef3c7;
        color: #b45309;
    }

    .headline-ticker-item.type-deadline .headline-ticker-badge {
        background: #ffedd5;
        color: #c2410c;
    }

    .headline-ticker-item.type-renewal .headline-ticker-badge {
        background: #e0f2fe;
        color: #0369a1;
    }

    /* Personal alerts are the only rows the reader can clear, so they read as unread mail. */
    .headline-ticker-item.type-alert .headline-ticker-badge {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .headline-ticker-item.type-alert .headline-ticker-icon {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .headline-ticker-item.type-alert .headline-ticker-text {
        color: #1e293b;
        font-weight: 800;
        font-style: normal;
    }

    .headline-ticker-item.type-alert .headline-ticker-text::before {
        content: "";
        display: inline-block;
        width: 6px;
        height: 6px;
        margin-right: 7px;
        border-radius: 999px;
        background: #2563eb;
        vertical-align: middle;
    }

    html.dark .headline-ticker-item.type-alert .headline-ticker-text {
        color: #e2e8f0;
    }

    .headline-ticker-item.is-overdue .headline-ticker-text {
        color: #be123c;
        font-weight: 800;
    }

    .headline-ticker-item.is-overdue .headline-ticker-badge {
        background: #fee2e2;
        color: #b91c1c;
    }

    .headline-ticker-due {
        padding: 4px 8px;
        border-radius: 7px;
        background: #f1f5f9;
        color: #64748b;
        font-size: 10px;
        font-weight: 900;
        font-style: normal;
        text-transform: uppercase;
    }

    .headline-ticker-sep {
        width: 1px;
        height: 18px;
        margin: 0 18px;
        background: #e5e7eb;
        flex: 0 0 auto;
    }

    .headline-ticker-empty {
        padding-left: 16px;
        color: #16a34a;
        font-size: 12px;
        font-weight: 700;
        font-style: italic;
    }

    .headline-ticker-toggle {
        width: 34px;
        height: 100%;
        border: 0;
        border-left: 1px solid var(--hn-border);
        background: #fafafa;
        color: #7c3aed;
        cursor: pointer;
        font-size: 12px;
        font-weight: 900;
    }

    .headline-focus {
        outline: 3px solid #7c3aed !important;
        outline-offset: -3px;
        animation: headlineFocus 2.7s ease-out 1;
    }

    @keyframes headlineScroll {
        from {
            transform: translateX(0);
        }

        to {
            transform: translateX(-50%);
        }
    }

    @keyframes headlinePulse {
        70% {
            box-shadow: 0 0 0 8px rgba(255, 255, 255, 0);
        }

        100% {
            box-shadow: 0 0 0 0 rgba(255, 255, 255, 0);
        }
    }

    @keyframes headlineFocus {

        0%,
        100% {
            box-shadow: none;
        }

        15%,
        78% {
            box-shadow: 0 0 0 5px rgba(124, 58, 237, .18), 0 16px 32px rgba(15, 23, 42, .18);
        }
    }

    html.dark .headline-ticker {
        background: #1e293b;
        border-color: #334155;
        --hn-ink: #dbeafe;
    }

    html.dark .headline-ticker-toggle {
        background: #0f172a;
    }

    @media (max-width:768px) {
        .headline-ticker {
            margin: 0 12px 10px;
            height: 40px;
            border-radius: 8px;
        }

        .headline-ticker-label {
            padding: 0 10px;
        }

        .headline-ticker-label span:nth-child(2) {
            display: none;
        }

        .headline-ticker-item {
            font-size: 12px;
        }

        .headline-ticker-toggle {
            display: none;
        }
    }
</style>

<div id="headline-ticker"
    class="headline-ticker no-print"
    data-source-url="{{ route('role.headline-notices.index', ['role' => $headlineRole]) }}"
    role="region"
    aria-label="Live reminders, notices, follow-ups, approvals and deadlines">
    <div class="headline-ticker-label">
        <span class="headline-ticker-pulse"></span>
        <span>LIVE BULLETIN</span>
        <span id="headline-ticker-count" class="headline-ticker-count">0</span>
    </div>
    <div class="headline-ticker-viewport">
        <div id="headline-ticker-track" class="headline-ticker-track"></div>
        <div id="headline-ticker-empty" class="headline-ticker-empty">All caught up - no pending reminders, approvals or deadlines.</div>
    </div>
    <button id="headline-ticker-toggle" class="headline-ticker-toggle" type="button" title="Pause / resume">II</button>
</div>

<script>
    (function() {
        const ticker = document.getElementById('headline-ticker');
        if (!ticker) return;

        const sourceUrl = ticker.dataset.sourceUrl;
        const track = document.getElementById('headline-ticker-track');
        const viewport = track.parentElement;
        const count = document.getElementById('headline-ticker-count');
        const empty = document.getElementById('headline-ticker-empty');
        const toggle = document.getElementById('headline-ticker-toggle');
        let items = [];

        function esc(value) {
            return String(value == null ? '' : value).replace(/[&<>"']/g, function(char) {
                return {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                } [char];
            });
        }

        function cssEscape(value) {
            if (window.CSS && CSS.escape) return CSS.escape(value);
            return String(value).replace(/[^a-zA-Z0-9_-]/g, '\\$&');
        }

        function typeLabel(item) {
            return item.label || item.type || 'Notice';
        }

        function itemHtml(item) {
            const classes = ['headline-ticker-item'];
            if (item.overdue) classes.push('is-overdue');
            classes.push('type-' + String(item.type || 'notice').replace(/[^a-z0-9_-]/gi, ''));
            return '<button type="button" class="' + classes.join(' ') + '" data-id="' + esc(item.id) + '" title="' + esc(item.tooltip || item.text) + '">' +
                '<span class="headline-ticker-icon">' + esc(item.icon || '!') + '</span>' +
                '<span class="headline-ticker-badge">' + esc(typeLabel(item)) + '</span>' +
                '<span class="headline-ticker-text">' + esc(item.text || '') + '</span>' +
                '<span class="headline-ticker-due">' + esc(item.due || '') + '</span>' +
                '</button><span class="headline-ticker-sep" aria-hidden="true"></span>';
        }

        function render(nextItems) {
            items = Array.isArray(nextItems) ? nextItems : [];
            count.textContent = items.length;

            if (!items.length) {
                track.innerHTML = '';
                ticker.classList.remove('is-scrolling');
                empty.style.display = 'block';
                return;
            }

            empty.style.display = 'none';
            const html = items.map(itemHtml).join('');

            // Lay out one group and measure it. The second copy only exists so the
            // loop can wrap without a gap, so adding it to a list that already fits
            // just prints the same rows twice, side by side.
            track.innerHTML = '<div class="headline-ticker-group">' + html + '</div>';

            const groupWidth = track.scrollWidth;
            const scrolls = groupWidth > viewport.clientWidth + 1;
            ticker.classList.toggle('is-scrolling', scrolls);

            if (scrolls) {
                // One loop travels exactly one group width, so tie the duration to
                // that width - otherwise two items crawl and twenty items race.
                ticker.style.setProperty('--hn-scroll-duration',
                    Math.max(18, Math.round(groupWidth / 55)) + 's');
                track.insertAdjacentHTML('beforeend',
                    '<div class="headline-ticker-group" aria-hidden="true">' + html + '</div>');
            }
        }

        function focusTarget(selector) {
            if (!selector) return false;
            const target = document.querySelector(selector);
            if (!target) return false;
            // Renewal cards sit inside a pane that may be switched away from.
            if (window.EpalRenewalCenter && window.EpalRenewalCenter.reveal) {
                window.EpalRenewalCenter.reveal(target);
            }
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
            target.classList.remove('headline-focus');
            void target.offsetWidth;
            target.classList.add('headline-focus');
            setTimeout(function() {
                target.classList.remove('headline-focus');
            }, 3000);
            return true;
        }

        function dismiss(id) {
            render(items.filter(function(notice) {
                return notice.id !== id;
            }));
        }

        function dismissAlert(item) {
            return fetch(item.dismiss_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                }
            }).catch(function() {
                // A failed write only means the row comes back on the next poll.
            });
        }

        function openItem(id) {
            const item = items.find(function(notice) {
                return notice.id === id;
            });
            if (!item) return;

            // Only personal alerts carry a dismiss_url. Clicking one clears it from
            // the bulletin alone - it stays unread in the notification bell. Every
            // other row (renewals, approvals, deadlines) sticks around until the
            // work behind it is resolved.
            if (item.dismiss_url) {
                dismiss(id);
                dismissAlert(item).then(function() {
                    if (item.target_url) window.location.href = item.target_url;
                });
                return;
            }

            if (focusTarget(item.target_selector)) return;
            if (item.target_url) window.location.href = item.target_url;
        }

        function refresh() {
            fetch(sourceUrl, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(function(response) {
                    return response.ok ? response.json() : {
                        items: []
                    };
                })
                .then(function(data) {
                    render(data.items || []);
                })
                .catch(function() {
                    render([]);
                });
        }

        track.addEventListener('click', function(event) {
            const button = event.target.closest('.headline-ticker-item');
            if (button) openItem(button.dataset.id);
        });

        toggle.addEventListener('click', function() {
            ticker.classList.toggle('paused');
            toggle.textContent = ticker.classList.contains('paused') ? '>' : 'II';
        });

        // A narrower window can turn a list that fitted into one that has to scroll.
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                render(items);
            }, 200);
        });

        window.EpalHeadlineTicker = {
            refresh: refresh,
            render: render,
            dismiss: dismiss,
            focusTarget: focusTarget
        };
        refresh();
        setInterval(refresh, 30000);

        const params = new URLSearchParams(window.location.search);
        const headline = params.get('headline');
        if (headline) {
            setTimeout(function() {
                // Most rows carry an id; renewal cards render twice, so they use
                // a data attribute instead of a duplicated DOM id.
                focusTarget('#' + cssEscape(headline) + ', [data-headline-ref="' + cssEscape(headline) + '"]');
            }, 350);
        }
    })();
</script>
@endauth