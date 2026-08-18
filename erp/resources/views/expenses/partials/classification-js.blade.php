{{--
    The behaviour behind the classification picker. Included once; it wires every
    block on the page that carries data-classification, so the create and edit
    modals share one implementation rather than two that drift.
--}}
<script>
(function () {
    'use strict';

    // The whole graph, sent with the page. A few kilobytes, and it means the
    // search filters and the levels resolve without a round trip per keystroke.
    const GRAPH = @json($classification);

    const byId = (list, id) => list.find(x => String(x.id) === String(id)) || null;

    /* Which category means "none of the above".
     *
     * Matched by NAME rather than id, because ids differ between this database
     * and live and a hard-coded one would quietly point at another company's
     * category there. A rename costs only the relabel — the row stays an
     * ordinary category and everything else keeps working.
     *
     * Its ledger account is whatever the Classification tab says it posts to,
     * so where an unclassified expense lands stays an accounting decision, not
     * something buried in this file. */
    const OTHERS_CATEGORY = 'miscellaneous';
    const isOthersCategory = (row) => !!row && String(row.name).trim().toLowerCase() === OTHERS_CATEGORY;

    function wire(prefix) {
        const root = document.querySelector('[data-classification="' + prefix + '"]');
        if (!root) return;

        const el = {
            search:  document.getElementById(prefix + '_cls_search'),
            results: document.getElementById(prefix + '_cls_results'),
            company: document.getElementById(prefix + '_company_id'),
            dept:    document.getElementById(prefix + '_expense_department_id'),
            cat:     document.getElementById(prefix + '_expense_category_id'),
            sub:     document.getElementById(prefix + '_expense_sub_category_id'),
            other:   document.getElementById(prefix + '_other_note'),
            otherW:  document.getElementById(prefix + '_other_wrap'),
            path:    document.getElementById(prefix + '_cls_path'),
        };

        if (!el.company || !el.cat) return;

        // A row with no company is shared by the whole group, not orphaned.
        const SHARED = 'All companies';

        // Shared rows are offered to everyone; a company's own rows only to it.
        // Narrowing to a company therefore adds to the shared list rather than
        // replacing it, so choosing a company never empties the dropdown.
        const visible = (row, companyId) =>
            !companyId || row.company_id == null || String(row.company_id) === String(companyId);

        const companyName = (id) => {
            if (!id) return SHARED;
            const opt = [...el.company.options].find(o => o.value === String(id));
            return opt ? opt.text : SHARED;
        };

        /* ── A shared CATEGORY does not make the company optional ──
           The blank option used to relabel itself to "All companies" once a shared
           category was chosen, on the reading that empty had become the answer.
           It has not: an expense is real money leaving one company's cash or bank,
           and journal_entries.company_id is NOT NULL — so a blank company does not
           file the expense for everyone, it fails on the journal insert.

           All nine categories here are shared, so that relabel fired almost every
           time and made a required, unanswered field look settled.

           The fact it was trying to convey is still worth saying, so it is said
           beside the field instead of inside it: the label stays a question, and a
           note explains why the category could not answer it. */
        const companyNote = document.getElementById(prefix + '_company_note');
        const companyMsg  = document.getElementById(prefix + '_company_msg');

        function markCompanyScope() {
            if (!companyNote) return;

            const cat = byId(GRAPH.categories, el.cat.value);
            const sharedCat = cat && !cat.company_id;
            const needsAnswer = sharedCat && !el.company.value;

            companyNote.classList.toggle('hidden', !needsAnswer);

            if (needsAnswer) {
                /* Leads with the action. The first wording opened with "shared by
                   every company", which read as permission to leave the field
                   blank — the exact opposite of what it meant. The shortcut is
                   named too, because picking a department is the faster answer. */
                companyNote.querySelector('span').innerHTML =
                    '<strong>Pick the company that paid</strong> — or choose a Department and it fills in. '
                    + '"' + cat.name + '" is used by every company, so it cannot fill this in for you.';
            }

            // Clear the error the moment it is answered.
            if (companyMsg && el.company.value) companyMsg.classList.add('hidden');
        }

        /* Called by the payment source when a petty cash float names its company:
           a float belongs to exactly one, so picking one is an answer. */
        window['clsSetCompany' + prefix] = function (companyId) {
            if (!companyId || String(el.company.value) === String(companyId)) return false;

            el.company.value = String(companyId);
            refreshDepartments();
            refreshCategories();
            changed();
            flash(el.company);

            return true;
        };

        /* ── Fill a level's options, grouped by owner only when that tells rows apart ──
           Most categories are shared, so the common list is nine flat rows and a
           heading on every one of them would be noise. Grouping earns its place
           the moment the visible rows have more than one owner — a company with
           its own category sees the shared vocabulary plus its own, and the
           headings are what say which is which. Departments run through the same
           helper because they will hit this the moment a second company gets one. */
        function fill(sel, rows, decorate) {
            const add = (row, into) => {
                const o = new Option(row.name, row.id);
                if (decorate) decorate(o, row);
                into.appendChild(o);
            };

            const groups = new Map();
            rows.forEach(r => {
                const key = String(r.company_id || '');
                if (!groups.has(key)) groups.set(key, []);
                groups.get(key).push(r);
            });

            if (groups.size < 2) {
                rows.forEach(r => add(r, sel));
                return;
            }

            // Shared first — it is what most people are reaching for.
            [...groups.keys()]
                .sort((a, b) => (a ? 1 : 0) - (b ? 1 : 0) || companyName(a).localeCompare(companyName(b)))
                .forEach(key => {
                    const g = document.createElement('optgroup');
                    g.label = companyName(key);
                    groups.get(key).forEach(r => add(r, g));
                    sel.appendChild(g);
                });
        }

        /* ── Rebuild the lists below whichever level changed ────────────────
           Category is filtered by company; sub-category by category. Both keep
           the current value when it is still valid, so narrowing the company
           does not silently wipe a correct category. */
        function refreshCategories(keep) {
            const companyId = el.company.value;
            const want = keep !== undefined ? keep : el.cat.value;

            el.cat.innerHTML = '<option value="">Select category</option>';

            /* No FAKE "Others…" here, and none on Department either — see below
               and the note in classification.blade.php. Both columns are NOT NULL
               foreign keys, so the literal "__other" that option posted could
               never be stored: it reached accountFor() and 500d the save with
               "must be of type ?int, string given".

               The real one takes its place. Miscellaneous is an actual shared
               category with its own ledger account, so choosing it files the
               expense somewhere true instead of nowhere — it is relabelled here
               so people looking for "Others" find it, and it opens the note box
               exactly as the fake option did. */
            fill(el.cat, GRAPH.categories.filter(c => visible(c, companyId)), (o, c) => {
                if (isOthersCategory(c)) o.text = 'Others (' + c.name + ')';
            });

            // An Others option belongs at the bottom of the list, not filed
            // under M. Moving the node keeps its value and its selected state.
            const others = [...el.cat.options].find(o => isOthersCategory(byId(GRAPH.categories, o.value)));
            if (others) el.cat.appendChild(others);

            el.cat.value = want || '';

            // The kept value did not survive the new filter.
            if (el.cat.value !== String(want || '')) el.cat.value = '';

            refreshSubCategories();
        }

        function refreshSubCategories(keep) {
            const catId = el.cat.value;
            const want = keep !== undefined ? keep : el.sub.value;

            el.sub.innerHTML = '<option value="">Select sub-category</option>';

            GRAPH.subCategories
                .filter(s => String(s.category_id) === String(catId))
                .forEach(s => el.sub.add(new Option(s.name, s.id)));

            el.sub.add(new Option('Others…', '__other'));
            el.sub.value = want || '';
            if (el.sub.value !== String(want || '')) el.sub.value = '';
        }

        /* ── Resolve upward ──
           A sub-category always names its category. A category names a company
           only when it is that company's own — most are shared and name none, so
           this fills the company in where it is a stored fact and stays quiet
           where there is nothing to know, rather than guessing one. */
        function fillUpFromCategory(catId) {
            const cat = byId(GRAPH.categories, catId);
            if (cat && cat.company_id && !el.company.value) {
                el.company.value = cat.company_id;
                refreshCategories(catId);
            }
        }

        /* Departments are filtered by company, exactly as categories are — an
           expense department belongs to one company. */
        function refreshDepartments(keep) {
            const companyId = el.company.value;
            const want = keep !== undefined ? keep : el.dept.value;

            el.dept.innerHTML = '<option value="">Select department</option>';

            fill(
                el.dept,
                GRAPH.departments.filter(d => visible(d, companyId)),
                (o, d) => { o.dataset.company = d.company_id; }
            );

            /* The markup dropped its "Others…" option for the reason above; this
               rebuild was still adding it back on every refresh, so it was never
               actually gone. A missing department is a setup gap — add it on the
               Classification tab, where it takes one click. */
            el.dept.value = want || '';
            if (el.dept.value !== String(want || '')) el.dept.value = '';
        }

        /* Pick the department, get the company — a HARD link now that expense
           departments carry their own company. This used to be inferred from what
           had previously been booked, because it read HR's group-wide department
           list, which cannot name a company at all. */
        function fillUpFromDepartment() {
            const opt = el.dept.selectedOptions[0];
            const owner = opt && opt.dataset.company;

            if (owner && String(el.company.value) !== String(owner)) {
                el.company.value = owner;
                refreshCategories();
                flash(el.company);
            }
        }

        // A brief highlight, so a field that filled itself in is noticed.
        function flash(node) {
            node.classList.add('ring-2', 'ring-blue-400');
            setTimeout(() => node.classList.remove('ring-2', 'ring-blue-400'), 900);
        }

        function toggleOther() {
            // The two places the list admits it cannot say what this was: the
            // Others category, and "Others…" on a sub-category. Either way the
            // note is the only record of what the money actually went on.
            const on = el.sub.value === '__other' || isOthersCategory(byId(GRAPH.categories, el.cat.value));
            el.otherW.classList.toggle('hidden', !on);
            if (!on && el.other) el.other.value = '';
        }

        function renderPath() {
            const bits = [];
            const text = (sel) => (sel.value && sel.value !== '__other')
                ? sel.selectedOptions[0].text : (sel.value === '__other' ? 'Others' : null);

            [el.company, el.dept, el.cat, el.sub].forEach(sel => {
                const t = text(sel);
                if (t) bits.push(t);
            });

            el.path.textContent = bits.length ? bits.join('  ›  ') : '';
            el.path.classList.toggle('hidden', !bits.length);
        }

        function changed() { markCompanyScope(); toggleOther(); renderPath(); }

        el.company.addEventListener('change', () => { refreshDepartments(); refreshCategories(); changed(); });
        el.dept.addEventListener('change', () => { fillUpFromDepartment(); changed(); });
        el.cat.addEventListener('change', () => {
            if (el.cat.value && el.cat.value !== '__other') fillUpFromCategory(el.cat.value);
            refreshSubCategories();
            changed();
        });
        el.sub.addEventListener('change', changed);

        /* ── Search ──
           Matches departments, sub-categories AND categories, and shows the full
           path on each result so two similarly-named things in different companies
           are told apart before they are picked, not after.

           Departments are searched too, and listed first, because they are the only
           result that ANSWERS the company — typing "common" fills the department
           and the company in one go, where a category match can fill in neither. */
        function search(term) {
            term = term.trim().toLowerCase();
            if (term.length < 2) return [];

            const depts = GRAPH.departments
                .filter(d => d.name.toLowerCase().includes(term))
                .map(d => ({
                    kind: 'Department',
                    label: d.name,
                    path: companyName(d.company_id),
                    deptId: d.id, catId: null, subId: null,
                }));

            const subs = GRAPH.subCategories
                .filter(s => s.name.toLowerCase().includes(term))
                .map(s => {
                    const cat = byId(GRAPH.categories, s.category_id);
                    return {
                        kind: 'Sub-category',
                        label: s.name,
                        path: [companyName(cat && cat.company_id), cat && cat.name].filter(Boolean).join('  ›  '),
                        catId: s.category_id, subId: s.id, deptId: null,
                    };
                });

            const cats = GRAPH.categories
                .filter(c => c.name.toLowerCase().includes(term))
                .map(c => ({
                    kind: 'Category',
                    label: c.name,
                    path: companyName(c.company_id),
                    catId: c.id, subId: null, deptId: null,
                }));

            return [...depts, ...subs, ...cats].slice(0, 12);
        }

        function apply(hit) {
            // A department names its company, so this one hit answers both.
            if (hit.deptId) {
                el.dept.value = String(hit.deptId);
                fillUpFromDepartment();

                el.results.classList.add('hidden');
                el.search.value = '';
                [el.dept, el.company].forEach(flash);
                changed();
                return;
            }

            const cat = byId(GRAPH.categories, hit.catId);

            // Only a category that names a company can set one. A shared category
            // names none, and clearing the field on its account would throw away a
            // company the user had already picked — the search is meant to fill
            // things in, never to undo them.
            if (cat && cat.company_id) el.company.value = cat.company_id;

            refreshCategories(hit.catId);
            refreshSubCategories(hit.subId || '');

            el.results.classList.add('hidden');
            el.search.value = '';
            [el.company, el.cat, el.sub].forEach(flash);
            changed();
        }

        el.search.addEventListener('input', function () {
            const hits = search(this.value);

            if (!hits.length) {
                el.results.classList.add('hidden');
                return;
            }

            el.results.innerHTML = '';

            hits.forEach(h => {
                const row = document.createElement('button');
                row.type = 'button';
                row.className = 'w-full text-left px-3 py-2 hover:bg-blue-50 border-b border-gray-100 last:border-0';
                /* The kind is named on every row now that three of them share one
                   list — "Common" as a department and "Common" as a category would
                   otherwise be two identical-looking rows that do different things. */
                const chip = h.kind
                    ? '<span class="ml-2 align-middle text-[9px] font-bold uppercase tracking-wide px-1.5 py-0.5 rounded '
                      + (h.deptId ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500')
                      + '">' + h.kind + '</span>'
                    : '';

                row.innerHTML = '<span class="block text-sm font-medium text-gray-800">' + h.label + chip + '</span>'
                    + (h.path ? '<span class="block text-[11px] text-gray-400">' + h.path + '</span>' : '');
                row.addEventListener('click', () => apply(h));
                el.results.appendChild(row);
            });

            el.results.classList.remove('hidden');
        });

        document.addEventListener('click', (e) => {
            if (!el.results.contains(e.target) && e.target !== el.search) {
                el.results.classList.add('hidden');
            }
        });

        // Populate from whatever the fields already hold — the edit modal sets
        // them before this runs.
        root.addEventListener('classification:sync', function (e) {
            const d = e.detail || {};
            el.company.value = d.company_id || '';
            refreshDepartments(d.expense_department_id || '');
            refreshCategories(d.expense_category_id || '');
            refreshSubCategories(d.expense_sub_category_id || '');
            if (el.other) el.other.value = d.other_note || '';
            changed();
        });

        refreshDepartments();
        refreshCategories();
        changed();
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-classification]').forEach(n => wire(n.dataset.classification));
    });
})();
</script>
