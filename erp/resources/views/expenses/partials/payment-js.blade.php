{{--
    Payment source behaviour, for both modals from one place.

    The form asks one question — which pot did the money leave — as two cards, and
    only then asks the details that pot needs. payment_mode is still what the
    database stores; the cards set it, and the hidden select keeps it posting
    exactly as before.

    Cash posts against Office Cash (or a custodian's float) and needs no bank.
    Anything else moves money out of a named account, so the bank picker appears
    and becomes required.
--}}
@php
    // Today's cash ceiling per company, keyed by company id.
    //
    // Built here rather than inline in @json() because Blade parses a directive's
    // argument by matching brackets and an array literal inside one trips it.
    //
    // Defensive `?? []` because this partial is shared: a caller that forgot to
    // pass $dailyFundToday must degrade to "no meter", never to a 500 on the
    // whole expense desk.
    $expFundMap = [];

    foreach (collect(data_get($dailyFundToday ?? [], 'rows', [])) as $expFundRow) {
        $expFundMap[(int) $expFundRow['company_id']] = [
            'name'     => $expFundRow['company_name'],
            'has_fund' => (bool) $expFundRow['has_fund'],
            'fund'     => (float) $expFundRow['fund'],
            'spent'    => (float) $expFundRow['spent'],
        ];
    }

    // Where the meter re-reads the fund when the date changes.
    //
    // Route::has first, and null when it is missing. This partial renders on the
    // shared expense desk, and route() on an unregistered name throws — which on
    // 2026-08-10 was exactly how a missing server step took every page down. A
    // stale route cache here must degrade to "the meter keeps describing today",
    // never to a 500 on the expense list.
    $expFundUrl = \Illuminate\Support\Facades\Route::has('role.expenses.daily-fund')
        ? route('role.expenses.daily-fund', [
            'role' => \Illuminate\Support\Str::slug(auth()->user()?->getRoleNames()->first() ?? ''),
        ])
        : null;
@endphp
<script>
/* The daily cost fund for the date the form is currently showing, keyed by
   company id — a spending CEILING, not money. Read only by the meter below: it
   changes no field, blocks no save and is never posted. A company absent from
   this map, or present with has_fund false, simply has no meter.

   Seeded with today because that is what the form opens on; replaced wholesale
   when the date field moves. */
window.EXP_DAILY_FUND = @json($expFundMap);
window.EXP_DAILY_FUND_URL = @json($expFundUrl);
window.EXP_DAILY_FUND_DATE = @json(data_get($dailyFundToday ?? [], 'date', now()->toDateString()));

/* The account a cash expense with no custodian actually posts to — the small
   petty cash pool when config/accounts.php names one that exists, Office Cash
   otherwise. Named from the chart because showHeld() rewrites these labels on
   every change and would otherwise keep asserting "Office Cash" long after the
   pot had moved. */
window.EXP_CASH_POT = @json($cashPotName ?? 'Office Cash');

/* What is actually in that pot, from the ledger. null means "could not be read"
   — the form then shows the pot with no figure rather than an authoritative
   zero, because "empty" and "unknown" are different answers and only one of
   them should stop someone. */
window.EXP_CASH_POT_BALANCE = @json($cashPotBalance ?? null);
</script>
<script>
(function () {
    'use strict';

    // Which methods actually move money out of a named account — a bank is
    // REQUIRED for these.
    const NEEDS_BANK = ['mobile_banking', 'bank_transfer', 'digital_wallet'];

    // Which methods belong under the Bank Account card. "other" is here but not
    // above: it can name a bank, it just does not have to. Keeping the two lists
    // apart is what lets an existing "other" expense open on the right card
    // instead of flipping to cash and dropping its bank.
    const BANK_BRANCH = NEEDS_BANK.concat(['other']);

    /* A payment method and an account kind are the same fact said twice, and the
       banks table already records which kind each account is. So the method picks
       the list: Mobile Banking cannot offer a current account, and Bank Transfer
       cannot offer a bKash wallet.

       "other" maps to nothing on purpose — it means "none of the above", so every
       account stays reachable rather than becoming un-selectable. */
    const BANK_TYPE_FOR = {
        bank_transfer:  'bank',
        mobile_banking: 'mobile_banking',
        digital_wallet: 'digital_wallet',
    };

    const TYPE_LABEL = {
        bank:           'bank',
        mobile_banking: 'mobile banking',
        digital_wallet: 'digital wallet',
    };

    function wire(prefix) {
        const mode = document.getElementById(prefix + '_payment_mode');
        const wrap = document.getElementById(prefix + '_bank_wrap');
        const bank = document.getElementById(prefix + '_bank_id');
        const hint = document.getElementById(prefix + '_cash_hint');

        // Which cash — the drawer or a custodian's pocket.
        const floatWrap = document.getElementById(prefix + '_float_wrap');
        const float = document.getElementById(prefix + '_petty_cash_float_id');

        // The classification block's company. A float and a bank both belong to
        // one, and so does the expense, so the two halves of the form have to
        // agree — the server refuses them when they do not.
        const company = document.getElementById(prefix + '_company_id');

        // The two cards and the branches they reveal.
        const cardCash   = document.getElementById(prefix + '_source_cash');
        const cardBank   = document.getElementById(prefix + '_source_bank');
        const cardOwn    = document.getElementById(prefix + '_source_own');
        const cashBranch = document.getElementById(prefix + '_cash_branch');
        const bankBranch = document.getElementById(prefix + '_bank_branch');
        const ownBranch  = document.getElementById(prefix + '_own_branch');
        const method     = document.getElementById(prefix + '_method');

        // Whose money it was. Empty = the company's. Carries the claimant's id
        // when it was theirs, and that value is the own-pocket state itself.
        const reimburse = document.getElementById(prefix + '_reimburse_to_user_id');
        const SELF_ID   = '{{ auth()->id() }}';

        // The live "what is in that pot" panel.
        const panel  = document.getElementById(prefix + '_fund_panel');
        const pCo    = document.getElementById(prefix + '_fund_company');
        const pLabel = document.getElementById(prefix + '_fund_label');
        const pNote  = document.getElementById(prefix + '_fund_note');
        const pFig   = document.getElementById(prefix + '_fund_figure');
        const pAmt   = document.getElementById(prefix + '_fund_amount');
        const pCap   = document.getElementById(prefix + '_fund_cap');

        if (!mode || !wrap || !bank) return;

        const money = n => '৳ ' + Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        /* ── The meter follows the DATE FIELD, not the clock ──────────────
           Back-dating is routine here: yesterday's receipt is entered this
           morning. The fund is versioned by date (effective_from/effective_to)
           and the spend is measured per day, so an expense dated the 12th must
           be judged against the 12th's ceiling and the 12th's spend — not
           today's. Before this the form always described today, which meant a
           back-dated entry was measured against the wrong allowance and the
           save-time "over the limit" prompt was answering about the wrong day.

           EXP_DAILY_FUND is replaced wholesale on each date change so
           dailyFundState() needs no notion of dates at all. */
        const dateEl = document.getElementById(prefix + '_expense_date');
        const todayISO = String(window.EXP_DAILY_FUND_DATE || '');
        let fundDate = todayISO;

        // Newest request wins. Two quick date changes otherwise race, and the
        // slower one lands last with figures for a date nobody is looking at.
        let fundToken = 0;

        const isToday = () => !fundDate || fundDate === todayISO;

        /* "today's" / "12 Aug's" — a possessive so every sentence below reads
           the same for both cases. */
        function whenPoss() {
            if (isToday()) return "today's";

            const d = new Date(fundDate + 'T00:00:00');
            if (isNaN(d)) return "that day's";

            return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' }) + "'s";
        }

        const whenAdverb = () => (isToday() ? 'today' : 'that day');

        function refreshFundForDate() {
            const url = window.EXP_DAILY_FUND_URL;
            const picked = dateEl ? String(dateEl.value || '') : '';

            // No route (stale cache), no date field, or a half-typed date: keep
            // whatever the meter already has rather than blanking it.
            if (!url || !/^\d{4}-\d{2}-\d{2}$/.test(picked)) return;
            if (picked === fundDate) return;

            const token = ++fundToken;

            fetch(url + '?date=' + encodeURIComponent(picked), {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                credentials: 'same-origin',
            })
                .then(r => (r.ok ? r.json() : Promise.reject(r.status)))
                .then(data => {
                    if (token !== fundToken) return;   // A later change already won.

                    const map = {};
                    (data.rows || []).forEach(row => {
                        map[String(row.company_id)] = {
                            name: row.company_name,
                            has_fund: !!row.has_fund,
                            fund: Number(row.fund) || 0,
                            spent: Number(row.spent) || 0,
                        };
                    });

                    window.EXP_DAILY_FUND = map;
                    fundDate = data.date || picked;
                    renderDailyFund();
                })
                .catch(() => { /* Leave the meter as it was — it is guidance, not a gate. */ });
        }

        if (dateEl) dateEl.addEventListener('change', refreshFundForDate);

        /* Reflect payment_mode into the cards and branches. Driven FROM the mode,
           not from which card was last clicked, so the edit modal gets the right
           card simply by setting the mode and re-syncing. */
        function sync() {
            const onBank = BANK_BRANCH.includes(mode.value);

            /* Three sources now, but payment_mode only knows two branches: an
               own-pocket expense is still paid in cash, so it and Petty Cash both
               sit on mode 'cash'. The hidden reimburse field is what tells them
               apart — it is the state, not a copy of it. */
            const onOwn = !onBank && !!(reimburse && reimburse.value);
            const onPot = !onBank && !onOwn;

            if (cardCash) cardCash.classList.toggle('is-active', onPot);
            if (cardOwn)  cardOwn.classList.toggle('is-active', onOwn);
            if (cardBank) cardBank.classList.toggle('is-active', onBank);
            if (cashBranch) cashBranch.classList.toggle('hidden', !onPot);
            if (ownBranch)  ownBranch.classList.toggle('hidden', !onOwn);
            if (bankBranch) bankBranch.classList.toggle('hidden', !onBank);

            wrap.classList.toggle('hidden', !onBank);
            if (hint) hint.classList.toggle('hidden', onBank);
            if (floatWrap) floatWrap.classList.toggle('hidden', !onPot);

            // Clearing on the way out matters: a bank left behind from an earlier
            // choice would send the posting to that bank while the form said
            // "Cash". settlementAccountId() reads the bank, not the mode.
            if (!onBank) {
                bank.value = '';
                const msg = document.getElementById(prefix + '_bank_msg');
                if (msg) msg.classList.add('hidden');
            } else if (float) {
                // Same hazard in the other direction, and worse: a float left set
                // behind a bank choice outranks the bank in settlementAccountId(),
                // so the posting would credit a custodian who never paid.
                float.value = '';
            }

            // The reimbursement outranks BOTH in settlementAccountId(), so a float
            // or bank left behind it is harmless to the posting but would trip the
            // server's "one pot only" rule and refuse the save. Clear them here,
            // and drop the claim itself the moment any other card is chosen.
            if (onOwn) {
                if (float) float.value = '';
                bank.value = '';
            } else if (reimburse) {
                reimburse.value = '';
            }

            // Keep the method select showing whatever mode actually holds.
            if (method && onBank && method.value !== mode.value) {
                method.value = mode.value;
            }

            filterFloats();
            filterBanks();
            showHeld();
            renderDailyFund();
        }

        /* Cash and banks both belong to a company, and so does the expense. Show
           only what the chosen company actually holds — the server refuses the rest
           anyway, so offering them turns a correct list into a validation error
           discovered after the click.

           With no company chosen yet, everything is offered: picking a float is
           then what ANSWERS the company question, which is the whole point of the
           cascade. */
        function narrow(select, wantCompany, extraKeep) {
            if (!select) return 0;

            let all = select._pcAllOptions;

            if (!all) {
                /* Every data-* attribute is cached, keyed by its short name, not
                   just the couple this function reads. The first version kept only
                   `company`, so filterBanks()'s `o.type === want` compared undefined
                   to 'bank' and silently dropped every account — a picker that said
                   "No bank account for this company" while five sat behind it. */
                all = Array.prototype.map.call(select.options, (o) => {
                    const attrs = Array.prototype.filter.call(o.attributes, a => a.name.indexOf('data-') === 0)
                                      .map(a => [a.name, a.value]);
                    const data = {};
                    attrs.forEach(([n, v]) => { data[n.slice(5)] = v; });

                    return { value: o.value, label: o.textContent, data: data, attrs: attrs };
                });
                select._pcAllOptions = all;
            }

            const previous = select.value;
            select.innerHTML = '';
            let kept = 0;

            all.forEach((o) => {
                if (o.value) {
                    if (wantCompany && String(o.data.company) !== String(wantCompany)) return;
                    if (extraKeep && !extraKeep(o)) return;
                }

                const el = document.createElement('option');
                el.value = o.value;
                el.textContent = o.label;
                o.attrs.forEach(([n, v]) => el.setAttribute(n, v));
                select.appendChild(el);

                if (o.value) kept++;
            });

            const survived = Array.prototype.some.call(select.options, o => o.value === previous);
            select.value = survived ? previous : '';

            return kept;
        }

        function filterFloats() {
            if (!float) return;
            narrow(float, company && company.value ? company.value : null);
        }

        /* Narrow the account list to the kind the chosen method uses, and to the
           company paying. Options are rebuilt rather than hidden: `hidden` on an
           <option> is advisory — the option can still be selected programmatically,
           and a select2 upgrade later would ignore it outright. */
        function filterBanks() {
            if (!bank) return;

            const want = BANK_TYPE_FOR[mode.value] || null;
            const kept = narrow(
                bank,
                company && company.value ? company.value : null,
                want ? (o => o.data.type === want) : null
            );

            // Say why the list is empty, rather than showing a picker with nothing
            // in it and letting the required-field error explain it later.
            if (bank.options.length) {
                bank.options[0].textContent = kept === 0
                    ? (want ? 'No ' + (TYPE_LABEL[want] || want) + ' account for this company' : 'No account for this company')
                    : '— Select Bank —';
            }
        }

        /* What is actually in the chosen pot, read from the ledger. The server
           refuses an expense larger than a float — this is so nobody finds that
           out only after clicking Save. */
        const floatHint = document.getElementById(prefix + '_float_hint');

        function showHeld() {
            if (!float) return;

            const opt   = float.options[float.selectedIndex];
            const held  = opt ? parseFloat(opt.getAttribute('data-held') || '') : NaN;
            const today = opt ? parseFloat(opt.getAttribute('data-today') || '') : NaN;
            const name  = opt ? (opt.getAttribute('data-name') || '') : '';

            // Office cash — the drawer.
            if (!float.value || isNaN(held)) {
                if (floatHint) {
                    floatHint.textContent = "Bought from someone's float? Pick them, and their balance drops by this amount.";
                    floatHint.className = 'text-[11px] text-gray-500 mt-1';
                }
                if (panel) {
                    panel.classList.remove('is-empty');
                    /* The pot, named from the chart — see window.EXP_CASH_POT.
                       Escaped through a detached node rather than jQuery: this
                       partial uses plain DOM throughout and must not acquire a
                       dependency on a library the page happens to load. */
                    const pot = window.EXP_CASH_POT || 'Office Cash';
                    const potEl = document.createElement('span');
                    potEl.textContent = pot;

                    /* What is in the pot, treated exactly like a custodian's
                       float: shown before the receipt is filed rather than
                       discovered when the save is refused. null means the figure
                       could not be read, which is not the same as zero — an
                       unknown balance must not be presented as an empty pot. */
                    const potBal = window.EXP_CASH_POT_BALANCE;
                    const potKnown = potBal !== null && potBal !== undefined && !isNaN(potBal);
                    const potEmpty = potKnown && Number(potBal) <= 0;

                    panel.classList.toggle('is-empty', potEmpty);

                    pLabel.textContent = pot + ' — the pot';
                    pNote.innerHTML = potEmpty
                        ? 'There is nothing in <strong>' + potEl.innerHTML + '</strong> right now, so a cash '
                          + 'expense cannot be settled from it. Top the pot up on the <strong>Petty Cash</strong> '
                          + 'page, or pay this from a bank.'
                        : 'This entry posts against <strong>' + potEl.innerHTML + '</strong>. No bank needed.';

                    pFig.hidden = !potKnown;
                    if (potKnown) {
                        pAmt.textContent = money(potBal);
                        pCap.textContent = 'in the pot right now';
                    }
                    if (pCo) pCo.classList.add('hidden');
                }
                return;
            }

            if (pCo) {
                const coName = opt.getAttribute('data-company-name') || '';
                pCo.textContent = coName;
                pCo.classList.toggle('hidden', !coName);
            }

            if (floatHint) {
                floatHint.className = 'text-[11px] mt-1 ' + (held <= 0 ? 'text-red-600' : 'text-amber-700');
                floatHint.textContent = held <= 0
                    ? name + ' is holding nothing right now — issue cash on the Petty Cash page first.'
                    : name + ' is holding ' + held.toFixed(2) + '. This expense cannot be more than that.';
            }

            if (panel) {
                panel.classList.toggle('is-empty', held <= 0);
                pLabel.textContent = name + "'s petty cash";
                pNote.innerHTML = held <= 0
                    ? 'Nothing is held right now, so nothing can be spent from it. Issue cash on the <strong>Petty Cash</strong> page first.'
                    : 'This entry is deducted from what ' + name + ' is holding. It is not taken from the drawer again.';
                pFig.hidden = false;
                pAmt.textContent = money(held);
                pCap.textContent = isNaN(today) || today <= 0
                    ? 'held right now'
                    : 'held right now · ' + money(today) + ' spent today';
            }
        }

        /* ── Today's cash ceiling ──────────────────────────────────────────
           A LIMIT, not a pot. Nothing below writes to a field, changes what is
           posted, or stops a save — it only says, while the amount is still
           being typed, how much of today's allowance the chosen company has
           left. Removing this whole block would leave the form behaving exactly
           as it did before it existed.

           Shown on the cash branch only: a bank payment is a different decision
           and the fund does not govern it. */
        const dfund      = document.getElementById(prefix + '_dfund');
        const dfundLabel = document.getElementById(prefix + '_dfund_label');
        const dfundFig   = document.getElementById(prefix + '_dfund_fig');
        const dfundFill  = document.getElementById(prefix + '_dfund_fill');
        const dfundNote  = document.getElementById(prefix + '_dfund_note');
        const itemsBody  = document.getElementById(prefix + '_items_body');

        /* What the cost lines currently add up to. Read from the inputs rather
           than the total cell so it is a number, not a formatted string. */
        function typedAmount() {
            let total = 0;

            if (itemsBody) {
                itemsBody.querySelectorAll('.item-amount').forEach(function (el) {
                    const v = parseFloat(el.value);
                    if (!isNaN(v)) total += v;
                });
            }

            return total;
        }

        /* The numbers behind the meter, or null when there is nothing to show.
           Shared by the meter and by the save-time confirmation so the two can
           never disagree about whether something is over the limit.

           `project` — whether to add what is being typed to what is already
           spent. True on the create form, where this expense is new. FALSE on
           the edit form, where today's spend ALREADY INCLUDES the expense being
           edited: adding the typed amount there would count it twice and warn
           about a limit that has not moved. */
        function dailyFundState() {
            if (!company || !company.value) return null;

            const info = (window.EXP_DAILY_FUND || {})[String(company.value)];
            if (!info || !info.has_fund) return null;

            const project = prefix === 'create';
            const fund    = Number(info.fund) || 0;
            const already = Number(info.spent) || 0;
            const typing  = project ? typedAmount() : 0;
            const after   = already + typing;

            return {
                name:    info.name,
                fund:    fund,
                already: already,
                typing:  typing,
                after:   after,
                left:    fund - after,
                over:    (fund - after) < -0.001,
                project: project,
                percent: fund > 0 ? Math.min((after / fund) * 100, 100) : 100,
            };
        }

        function renderDailyFund() {
            if (!dfund) return;

            const onBank = BANK_BRANCH.includes(mode.value);
            const s = onBank ? null : dailyFundState();

            if (!s) {
                dfund.classList.add('hidden');
                return;
            }

            dfund.classList.remove('hidden', 'is-near', 'is-over');
            if (s.over)             dfund.classList.add('is-over');
            else if (s.percent >= 80) dfund.classList.add('is-near');

            // Every sentence names the day it is talking about. A back-dated
            // entry showing "today's limit" was the whole problem.
            const poss = whenPoss();
            const adverb = whenAdverb();

            dfundLabel.textContent = s.name + ' — ' + poss + ' cash limit';
            dfundFig.textContent   = money(s.after) + ' / ' + money(s.fund);
            dfundFill.style.width  = s.percent + '%';

            if (s.over) {
                dfundNote.innerHTML = 'That would go <strong>' + money(-s.left)
                    + ' past</strong> ' + poss + ' limit. It can still be saved — you will be asked to confirm.';
            } else if (s.typing > 0) {
                dfundNote.innerHTML = 'Leaves <strong>' + money(s.left)
                    + '</strong> of ' + poss + ' allowance. An allowance, not cash in hand.';
            } else if (s.project) {
                dfundNote.innerHTML = '<strong>' + money(s.left) + '</strong> of ' + poss + ' allowance left'
                    + (s.already > 0 ? ' — ' + money(s.already) + ' already spent ' + adverb + '.' : '.');
            } else {
                // Edit form: no projection, so say plainly that the figure
                // already contains this expense.
                dfundNote.innerHTML = money(s.already) + ' of ' + money(s.fund)
                    + ' spent ' + adverb + ', this expense included.';
            }
        }

        if (itemsBody) {
            // Delegated to the tbody, which survives rows being added and removed.
            itemsBody.addEventListener('input', renderDailyFund);
        }

        /* Read by the save button so it can ask before going over. Returns null
           when there is nothing to warn about, which is the common case. */
        window['expDailyFundVerdict' + prefix] = function () {
            const s = dailyFundState();
            return (s && s.over && !BANK_BRANCH.includes(mode.value)) ? s : null;
        };

        if (float) {
            float.addEventListener('change', function () {
                /* A float belongs to exactly one company, so picking one answers
                   the Company question above — the same hard link a department
                   already has. Without this, choosing a shared category (all nine
                   of them are shared) left Company blank and the save failed with
                   "that float belongs to a different company", which was true only
                   because no company had been named at all. */
                const opt = float.options[float.selectedIndex];
                const owner = opt && opt.getAttribute('data-company');
                const setCompany = window['clsSetCompany' + prefix];

                if (owner && setCompany) setCompany(owner);

                showHeld();
            });
        }

        // Company changed upstream — the pickers below have to follow it.
        if (company) {
            company.addEventListener('change', function () {
                filterFloats();
                filterBanks();
                showHeld();
                renderDailyFund();
            });
        }

        // The cards are the control; they write payment_mode and re-sync.
        if (cardCash) {
            cardCash.addEventListener('click', function () {
                mode.value = 'cash';
                if (reimburse) reimburse.value = '';   // sync() reads this to tell the two cash cards apart
                sync();
            });
        }

        if (cardOwn) {
            cardOwn.addEventListener('click', function () {
                // Own money is still cash; what changes is who it belonged to.
                mode.value = 'cash';
                if (reimburse) reimburse.value = SELF_ID;
                sync();
            });
        }

        if (cardBank) {
            cardBank.addEventListener('click', function () {
                // Keep the method already chosen if it belongs here, so clicking
                // between the cards does not silently reset Mobile Banking.
                mode.value = (method && BANK_BRANCH.includes(method.value)) ? method.value : 'bank_transfer';
                if (reimburse) reimburse.value = '';
                sync();
            });
        }

        if (method) {
            method.addEventListener('change', function () {
                mode.value = method.value;
                sync();
            });
        }

        mode.addEventListener('change', sync);
        sync();

        // Exposed so the edit modal can re-sync after it fills the fields in.
        window['sync' + prefix + 'Payment'] = sync;
    }

    document.addEventListener('DOMContentLoaded', function () {
        ['create', 'edit'].forEach(wire);
    });
})();
</script>
