{{-- data-mode: file (default, every load) or agent — the switch in the banner. --}}
<div id="cf-dashboard-overview" data-mode="file">
 @include('contract-files.dashboard-overview')
<div id="cf-dashboard-ledger" hidden aria-live="polite"></div>
@foreach($clientSummary as $group)
<template data-cf-group-header="{{ $group['client_id'] ?? 'unassigned' }}">
 @include('contract-files.ledger-header',['groupClient'=>$group['files']->first()?->client,'groupFiles'=>$group['files']])
</template>
@endforeach
<script>
(() => {
    const overview = document.getElementById('cf-dashboard-overview');
    const ledger = document.getElementById('cf-dashboard-ledger');
    const groupTable = document.getElementById('cfd-group-table');
    const dashboardUrl = @json(route('role.contract-files.dashboard', ['role'=>$role]));
    let pending;
    let activeUrl;
    const scrollBehavior = () => matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth';

    function bindLedgerActions(content, url) {
        content.querySelector('[data-cf-ledger-refresh]')?.addEventListener('click',()=>openLedger(activeUrl));
        content.querySelectorAll('.create-btn').forEach(button=>button.addEventListener('click',()=>{
            document.querySelector('#cf-dashboard-overview .create-btn')?.click();
            if (typeof jQuery !== 'undefined') {
                const clientId = new URL(url,location.href).pathname.match(/\/agents\/(\d+)\/files/)?.[1];
                if (clientId) jQuery('#create_client_id').val(clientId).trigger('change.select2');
                if (clientId && window.CFFileBuilder) window.CFFileBuilder.setClient(clientId);
            }
        }));
    }

    function closeLedger() {
        pending?.abort();
        ledger.hidden = true;
        ledger.setAttribute('aria-busy','false');
        overview.hidden = false;
        groupTable.hidden = false;
        overview.querySelectorAll('[data-file-card]').forEach(card=>card.setAttribute('aria-pressed','false'));
        overview.scrollIntoView({block:'start'});
    }

    async function openLedger(url) {
        pending?.abort();
        const controller = new AbortController();
        pending = controller;
        activeUrl = url;
        groupTable.hidden = true;
        const selectedFile = new URL(url,location.href).searchParams.get('selected_file');
        const selectedGroup = new URL(url,location.href).pathname.match(/\/agents\/(\d+)\/files/)?.[1] ?? 'unassigned';
        overview.querySelectorAll('[data-file-card]').forEach(card=>card.setAttribute('aria-pressed',String(card.dataset.fileCard===selectedGroup)));
        ledger.hidden = false;
        ledger.setAttribute('aria-busy','true');
        ledger.replaceChildren();
        // Match Contract Flight: paint the known group header before fetching rows.
        const shell = document.createElement('div');
        shell.className = 'cfp-ledger';
        const header = Array.from(document.querySelectorAll('template[data-cf-group-header]'))
            .find(template=>template.dataset.cfGroupHeader===selectedGroup);
        if (header) shell.append(header.content.cloneNode(true));
        const loading = document.createElement('p');
        loading.className = 'cfw-empty';
        loading.setAttribute('role','status');
        loading.textContent = 'Loading files & applicants…';
        shell.append(loading);
        ledger.append(shell);
        bindLedgerActions(shell, url);
        const title = shell.querySelector('.fd-bar h3');
        if (title) { title.tabIndex = -1; title.focus({preventScroll:true}); }
        ledger.scrollIntoView({behavior:scrollBehavior(),block:'start'});
        try {
            const response = await fetch(url, {headers:{'Accept':'text/html'}, signal:controller.signal});
            if (!response.ok) throw new Error('Unable to load file ledger. Please try again.');
            const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
            if (controller.signal.aborted || pending !== controller) return;
            const content = doc.querySelector('[data-file-group-ledger]');
            if (!content) throw new Error('File ledger could not be loaded. Please refresh and sign in if needed.');
            const records = content.querySelector('[data-file-group-records]');
            files = JSON.parse(records.textContent);
            records.remove();
            if (selectedFile) content.querySelector('[data-file-row="'+selectedFile+'"]')?.classList.add('cfw-selected-file');
            ledger.replaceChildren(content);
            content.querySelectorAll('table[data-cf-sort]').forEach(table=>window.cfSortReapply?.(table));
            bindLedgerActions(content, url);
            const loadedTitle = content.querySelector('.fd-bar h3');
            if (loadedTitle) { loadedTitle.tabIndex = -1; loadedTitle.focus({preventScroll:true}); }
        } catch(error) {
            if (error.name === 'AbortError' || pending !== controller) return;
            loading.remove();
            const message = document.createElement('p');
            message.textContent = error.message;
            const back = document.createElement('button');
            back.className = 'cfw-btn'; back.textContent = 'Back to dashboard';
            back.addEventListener('click',closeLedger);
            shell.append(message,back);
        } finally {
            if (pending === controller) ledger.setAttribute('aria-busy','false');
        }
    }

    // Agent wise: a group row / card opens that agent's ledger below.
    overview.querySelectorAll('tbody tr[data-group-row]').forEach(row=>{
        const link = row.querySelector('a[href]');
        if (!link) return;
        row.style.cursor = 'pointer';
        row.tabIndex = 0;
        row.setAttribute('role','link');
        row.addEventListener('click',event=>{
            if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
            event.preventDefault(); openLedger(link.href);
        });
        row.addEventListener('keydown',event=>{
            if (event.target!==row || !['Enter',' '].includes(event.key)) return;
            event.preventDefault(); openLedger(link.href);
        });
    });
    // File wise: a row opens the file record, as its card and eye do (a[data-file-sheet] → record-sheet-modal).
    overview.querySelectorAll('tbody tr[data-file-open]').forEach(row=>{
        const open=()=>row.querySelector('a[data-file-sheet]')?.click();
        row.addEventListener('click',event=>{ if (event.target.closest('a,button')) return; open(); });
        row.addEventListener('keydown',event=>{ if (event.target!==row || !['Enter',' '].includes(event.key)) return; event.preventDefault(); open(); });
    });
    // The mode switch. Not remembered: the page always opens file wise.
    overview.querySelectorAll('[data-cfd-mode-btn]').forEach(button=>button.addEventListener('click',()=>{
        const mode = button.dataset.cfdModeBtn;
        if (overview.dataset.mode === mode) return;
        if (!ledger.hidden) closeLedger();
        overview.dataset.mode = mode;
        overview.querySelectorAll('[data-cfd-mode-btn]').forEach(item=>item.setAttribute('aria-pressed',String(item===button)));
        if (mode === 'file') requestAnimationFrame(()=>window.cfdMarkLastRow?.());
    }));
    overview.querySelectorAll('[data-file-card]').forEach(link=>link.addEventListener('click',event=>{
        if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault(); openLedger(link.href);
    }));
    overview.querySelectorAll('[data-cfd-scroll]').forEach(button=>button.addEventListener('click',()=>{
        if (!groupTable.hidden) document.getElementById(overview.dataset.mode === 'agent' ? 'cfdActiveGroups' : 'cfdActive')?.scrollIntoView({behavior:scrollBehavior(),block:'start'});
        else closeLedger();
    }));
    const filter = document.getElementById('cfd-filter');
    filter.querySelector('[data-cfd-clear]').addEventListener('click',()=>{
        filter.elements.from_date.value='';filter.elements.to_date.value='';filter.requestSubmit();
    });
    filter.querySelectorAll('select,input[type=date]').forEach(input=>input.addEventListener('change',()=>filter.requestSubmit()));
    let searchTimer;
    filter.elements.search.addEventListener('input',()=>{clearTimeout(searchTimer);searchTimer=setTimeout(()=>filter.requestSubmit(),600)});
    const today = @json(now()->toDateString());
    filter.querySelectorAll('[data-cfd-date]').forEach(button=>button.addEventListener('click',()=>{
        const start = new Date(today+'T12:00:00');
        const end = new Date(today+'T12:00:00');
        const key = button.dataset.cfdDate;
        if(key==='yesterday'){start.setDate(start.getDate()-1);end.setDate(end.getDate()-1)}
        if(key==='tomorrow'){start.setDate(start.getDate()+1);end.setDate(end.getDate()+1)}
        if(key==='week'){start.setDate(start.getDate()-((start.getDay()+6)%7));end.setTime(start.getTime());end.setDate(end.getDate()+6)}
        const ymd=d=>[d.getFullYear(),String(d.getMonth()+1).padStart(2,'0'),String(d.getDate()).padStart(2,'0')].join('-');
        filter.elements.from_date.value=ymd(start);filter.elements.to_date.value=ymd(end);filter.requestSubmit();
    }));
    ledger.addEventListener('click',event=>{
        const link = event.target.closest('a[href]');
        if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        const url = new URL(link.href,location.href);
        if (url.pathname === new URL(dashboardUrl).pathname) {
            event.preventDefault(); closeLedger();
        } else if (url.pathname === new URL(activeUrl,location.href).pathname) {
            event.preventDefault(); openLedger(link.href);
        }
    });
    ledger.addEventListener('submit',event=>{
        if (!event.target.matches('[data-group-filter]')) return;
        event.preventDefault();
        const url = new URL(activeUrl,location.href);
        const params = new URLSearchParams(new FormData(event.target));
        if (url.searchParams.get('unassigned') === '1') params.set('unassigned','1');
        url.search = params.toString();
        openLedger(url.href);
    });
})();
</script>
