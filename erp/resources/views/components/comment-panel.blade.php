@php $cmtRole = Str::slug(auth()->user()->getRoleNames()->first()); @endphp
<script>
(function () {
    const CMT_LIST_URL = '/{{ $cmtRole }}/comments';
    const CMT_STORE_URL = '/{{ $cmtRole }}/comments';
    const CMT_DEL_BASE  = '/{{ $cmtRole }}/comments/';
    const CMT_TOKEN     = '{{ csrf_token() }}';

    /* ── Panel shell ─────────────────────────────────────────── */
    window.cmtHtml = function (type, id) {
        return `
        <div id="cmt-wrap-${type}-${id}" class="border-t border-slate-100 mt-5 pt-4">

            <div class="flex items-center gap-2 mb-3">
                <div class="flex items-center justify-center w-6 h-6 rounded-lg bg-indigo-50 text-indigo-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                </div>
                <span class="text-[10px] font-extrabold uppercase tracking-widest text-slate-400">Comments</span>
            </div>

            <div id="cmt-list-${type}-${id}" class="flex flex-col gap-2 mb-3">
                <div class="flex items-center justify-center gap-2 py-4 text-slate-400">
                    <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                    <span class="text-xs">Loading…</span>
                </div>
            </div>

            <div class="flex gap-2 items-end">
                <textarea id="cmt-input-${type}-${id}" rows="2"
                    placeholder="Write a comment… (Enter to post, Shift+Enter for new line)"
                    class="flex-1 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-700 placeholder-slate-400 resize-none outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 transition-all font-medium leading-relaxed"
                    onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();cmtPost('${type}',${id});}"></textarea>
                <button onclick="cmtPost('${type}',${id})"
                    class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white text-xs font-bold rounded-xl px-4 py-2.5 transition-colors cursor-pointer border-0 flex-shrink-0 shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                    Post
                </button>
            </div>
        </div>`;
    };

    /* ── Single comment bubble ───────────────────────────────── */
    function cmtBubble(c) {
        const initials = escCmt(c.user).split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase();
        const avatarColors = ['bg-violet-100 text-violet-600','bg-blue-100 text-blue-600','bg-emerald-100 text-emerald-600','bg-amber-100 text-amber-600','bg-rose-100 text-rose-600','bg-cyan-100 text-cyan-600'];
        const colorClass   = avatarColors[c.id % avatarColors.length];

        return `
        <div id="cmt-item-${c.id}" class="flex gap-2.5 items-start group">
            <div class="w-7 h-7 rounded-full ${colorClass} flex-shrink-0 flex items-center justify-center text-[10px] font-black">${initials}</div>
            <div class="flex-1 min-w-0">
                <div class="bg-slate-50 border border-slate-100 rounded-xl px-3 py-2.5 hover:border-slate-200 transition-colors">
                    <div class="flex items-center justify-between gap-2 mb-1">
                        <span class="text-[11px] font-bold text-slate-700 truncate">${escCmt(c.user)}</span>
                        <span class="text-[10px] text-slate-400 flex-shrink-0">${c.created_at}</span>
                    </div>
                    <p class="text-xs text-slate-600 leading-relaxed m-0" style="white-space:pre-wrap;">${escCmt(c.body)}</p>
                </div>
            </div>
            ${c.is_mine ? `
            <button onclick="cmtDelete(${c.id})" title="Delete"
                class="opacity-0 group-hover:opacity-100 flex-shrink-0 w-6 h-6 flex items-center justify-center rounded-lg text-slate-300 hover:text-red-500 hover:bg-red-50 transition-all cursor-pointer border-0 bg-transparent mt-0.5">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>` : '<div class="w-6 flex-shrink-0"></div>'}
        </div>`;
    }

    function escCmt(s) {
        return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    /* ── Empty / error states ────────────────────────────────── */
    function cmtEmpty() {
        return `<div class="flex flex-col items-center gap-2 py-5 text-slate-400">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
            <span class="text-xs font-medium">No comments yet. Be the first!</span>
        </div>`;
    }

    function cmtError() {
        return `<div class="flex items-center justify-center gap-2 py-4 text-red-400">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span class="text-xs">Failed to load comments.</span>
        </div>`;
    }

    /* ── Load comments ───────────────────────────────────────── */
    window.loadComments = function (type, id) {
        const $list = $(`#cmt-list-${type}-${id}`);
        if (!$list.length) return;

        $.get(CMT_LIST_URL, { type, id })
            .done(function (data) {
                $list.html(data.length ? data.map(cmtBubble).join('') : cmtEmpty());
            })
            .fail(function () {
                $list.html(cmtError());
            });
    };

    /* ── Post comment ────────────────────────────────────────── */
    window.cmtPost = function (type, id) {
        const $input = $(`#cmt-input-${type}-${id}`);
        const body   = ($input.val() || '').trim();
        if (!body) { $input.focus(); return; }

        const $btn = $input.closest('[id^="cmt-wrap"]').find('button[onclick^="cmtPost"]');
        $input.prop('disabled', true);
        $btn.prop('disabled', true).addClass('opacity-60 cursor-not-allowed');

        $.ajax({
            url: CMT_STORE_URL,
            method: 'POST',
            contentType: 'application/json',
            headers: { 'X-CSRF-TOKEN': CMT_TOKEN },
            data: JSON.stringify({ body, type, id }),
        })
        .done(function (c) {
            const $list = $(`#cmt-list-${type}-${id}`);
            if ($list.find('[class*="No comments"]').length || $list.children().length === 1 && $list.find('svg').length) {
                $list.empty();
            }
            $list.append(cmtBubble(c));
            $input.val('');
        })
        .fail(function () {
            alert('Failed to post comment. Please try again.');
        })
        .always(function () {
            $input.prop('disabled', false).focus();
            $btn.prop('disabled', false).removeClass('opacity-60 cursor-not-allowed');
        });
    };

    /* ── Delete comment ──────────────────────────────────────── */
    window.cmtDelete = function (id) {
        if (!confirm('Delete this comment?')) return;

        $.ajax({
            url: CMT_DEL_BASE + id,
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': CMT_TOKEN },
        })
        .done(function () {
            const $item = $(`#cmt-item-${id}`);
            $item.addClass('opacity-0 transition-opacity duration-200');
            setTimeout(() => $item.remove(), 200);
        })
        .fail(function () {
            alert('Could not delete comment.');
        });
    };
})();
</script>
