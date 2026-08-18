@php
    $currentUser = auth()->user();
    $chatRoleSlug = \Illuminate\Support\Str::slug($currentUser->getRoleNames()->first() ?? 'user');

    $chatEmployeesQuery = \App\Models\User::query()
        ->where('id', '!=', $currentUser->id)
        ->where('status', 'active')
        ->role(['employee', 'super admin']);

    $chatLastMessageSubQuery = \App\Models\Chat::query()
        ->selectRaw(
            'CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END as other_user_id, MAX(created_at) as last_message_at',
            [$currentUser->id]
        )
        ->where(function ($q) use ($currentUser) {
            $q->where('sender_id', $currentUser->id)
                ->orWhere('receiver_id', $currentUser->id);
        })
        ->groupBy('other_user_id');

    $chatEmployees = $chatEmployeesQuery
        ->leftJoinSub($chatLastMessageSubQuery, 'last_chat', function ($join) {
            $join->on('users.id', '=', 'last_chat.other_user_id');
        })
        ->select('users.*', 'last_chat.last_message_at')
        ->orderByRaw('CASE WHEN last_chat.last_message_at IS NULL THEN 1 ELSE 0 END')
        ->orderByDesc('last_chat.last_message_at')
        ->orderBy('users.name')
        ->get();

    $chatUnreadBySender = \App\Models\Chat::where('receiver_id', $currentUser->id)
        ->whereNull('read_at')
        ->select('sender_id', \Illuminate\Support\Facades\DB::raw('COUNT(*) as unread_count'))
        ->groupBy('sender_id')
        ->pluck('unread_count', 'sender_id');

    $chatEmployees->transform(function ($employee) use ($chatUnreadBySender) {
        $employee->unread_count = (int) ($chatUnreadBySender[$employee->id] ?? 0);
        return $employee;
    });

    $chatTotalUnread = (int) $chatEmployees->sum('unread_count');
@endphp

<style>
    #chatWindowGlobal {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        transform: translateY(20px);
        opacity: 0;
        pointer-events: none;
        visibility: hidden;
        height: 550px;
        max-height: calc(100vh - 7rem);
        width: min(420px, calc(100vw - 1rem));
        right: 0.5rem;
        max-width: 100%;
        overflow-x: hidden;
    }

    #chatGlobalMessages {
        overflow-x: hidden;
    }

    #chatWindowGlobal.active {
        transform: translateY(0);
        opacity: 1;
        pointer-events: auto;
        visibility: visible;
    }

    #chatToggleGlobal {
        position: fixed;
        right: 24px;
        bottom: 24px;
        z-index: 9999;
    }

    .chat-global-bubble {
        position: relative;
        max-width: 85%;
        padding: 8px 14px;
        border-radius: 16px;
        font-size: 13px;
        line-height: 1.5;
        margin-bottom: 4px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        min-width: 0;
        white-space: normal;
        overflow-wrap: anywhere;
        word-break: break-word;
    }

    .chat-global-msg-menu-btn {
        position: absolute;
        top: 2px;
        right: 4px;
        width: 18px;
        height: 18px;
        border: none;
        border-radius: 9999px;
        background: rgba(0, 0, 0, 0.15);
        color: inherit;
        font-size: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        opacity: 0;
        transition: opacity 0.15s;
    }

    .chat-global-bubble:hover .chat-global-msg-menu-btn {
        opacity: 1;
    }

    .chat-global-msg-menu {
        position: absolute;
        top: 22px;
        right: 4px;
        min-width: 110px;
        background: #fff;
        color: #1e293b;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        overflow: hidden;
        z-index: 20;
    }

    .chat-global-msg-menu-item {
        display: flex;
        align-items: center;
        gap: 6px;
        width: 100%;
        padding: 6px 10px;
        font-size: 12px;
        background: none;
        border: none;
        text-align: left;
        cursor: pointer;
        color: #1e293b;
    }

    .chat-global-msg-menu-item:hover {
        background: #f1f5f9;
    }

    .chat-global-msg-menu-item-danger {
        color: #dc2626;
    }

    .chat-global-edited-tag {
        margin-left: 6px;
        font-style: italic;
        color: #94a3b8;
    }

    .chat-global-edit-textarea {
        display: block;
        width: 100%;
        min-width: 160px;
        font-size: 13px;
        line-height: 1.5;
        font-family: inherit;
        border-radius: 8px;
        padding: 6px 8px;
        color: inherit;
        background: rgba(255, 255, 255, 0.15);
        border: 1px solid rgba(255, 255, 255, 0.4);
        resize: vertical;
    }

    .chat-global-received .chat-global-edit-textarea {
        background: #fff;
        color: #1e293b;
        border-color: #cbd5e1;
    }

    .chat-global-edit-actions {
        display: flex;
        justify-content: flex-end;
        gap: 6px;
        margin-top: 6px;
    }

    .chat-global-edit-actions button {
        font-size: 11px;
        padding: 3px 10px;
        border-radius: 6px;
        border: none;
        cursor: pointer;
    }

    .chat-global-edit-save {
        background: #22c55e;
        color: #fff;
    }

    .chat-global-edit-cancel {
        background: rgba(0, 0, 0, 0.15);
        color: inherit;
    }

    .chat-global-received .chat-global-edit-cancel {
        background: #e2e8f0;
        color: #1e293b;
    }

    .chat-global-bubble * {
        max-width: 100%;
        overflow-wrap: anywhere;
        word-break: break-word;
    }

    .chat-global-row-sent {
        padding-right: 8px;
        max-width: 100%;
        min-width: 0;
    }

    .chat-global-row-received {
        padding-left: 2px;
        max-width: 100%;
        min-width: 0;
    }

    .chat-global-sent {
        align-self: flex-end;
        background: #4f46e5;
        color: #fff;
        border-bottom-right-radius: 2px;
    }

    .chat-global-received {
        align-self: flex-start;
        background: #f1f5f9;
        color: #1e293b;
        border-bottom-left-radius: 2px;
    }

    .chat-global-time {
        font-size: 10px;
        color: #94a3b8;
        margin-top: 2px;
        display: none;
    }

    .chat-global-meta-status {
        margin-left: 6px;
        font-weight: 600;
        color: #64748b;
    }

    .chat-global-seen-indicator {
        margin-top: 2px;
        display: flex;
        justify-content: flex-end;
    }

    .chat-global-seen-avatar {
        width: 14px;
        height: 14px;
        border-radius: 9999px;
        object-fit: cover;
        border: 1px solid #e2e8f0;
    }

    .chat-global-seen-fallback {
        width: 14px;
        height: 14px;
        border-radius: 9999px;
        background: #e0e7ff;
        color: #4338ca;
        font-size: 9px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #c7d2fe;
    }

    .chat-global-user.active {
        background-color: #ffffff;
        border-left: 4px solid #4f46e5;
        box-shadow: inset 0 0 10px rgba(0, 0, 0, 0.02);
    }

    .chat-global-status-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        display: inline-block;
        margin-right: 4px;
    }

    .chat-global-status-online {
        background: #22c55e;
        box-shadow: 0 0 8px rgba(34, 197, 94, 0.5);
    }

    .chat-global-status-offline {
        background: #cbd5e1;
    }

    /* File attachment preview bar */
    #chatGlobalFilePreview {
        display: none;
        align-items: center;
        gap: 8px;
        padding: 6px 12px;
        background: #eef2ff;
        border-top: 1px solid #c7d2fe;
        font-size: 11px;
        color: #3730a3;
    }

    #chatGlobalFilePreview .file-preview-name {
        flex: 1;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    #chatGlobalFilePreview .file-preview-remove {
        cursor: pointer;
        color: #6366f1;
        font-size: 13px;
        line-height: 1;
        flex-shrink: 0;
    }

    /* Chat image in bubble */
    .chat-file-image {
        max-width: 180px;
        max-height: 160px;
        border-radius: 10px;
        object-fit: cover;
        cursor: pointer;
        display: block;
        margin-top: 4px;
    }

    .chat-file-pdf {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-top: 4px;
        padding: 5px 10px;
        background: rgba(255,255,255,0.2);
        border-radius: 8px;
        text-decoration: none;
        font-size: 11px;
        font-weight: 600;
        color: inherit;
        border: 1px solid rgba(255,255,255,0.3);
        word-break: break-all;
    }

    .chat-file-pdf:hover {
        background: rgba(255,255,255,0.35);
    }

    .chat-global-received .chat-file-pdf {
        background: rgba(79,70,229,0.08);
        border-color: rgba(79,70,229,0.2);
        color: #3730a3;
    }

    .chat-global-received .chat-file-pdf:hover {
        background: rgba(79,70,229,0.15);
    }

    .chat-global-link {
        color: inherit;
        text-decoration: underline;
        word-break: break-all;
    }

    .chat-global-sent .chat-global-link {
        color: #e0e7ff;
    }

    .chat-global-received .chat-global-link {
        color: #4338ca;
    }
</style>

<div id="chatToggleGlobal"
    class="bg-indigo-600 text-white w-14 h-14 rounded-full shadow-2xl flex items-center justify-center cursor-pointer transition-all relative hover:scale-110">
    <i class="fas fa-comments text-xl"></i>
    <span id="chatGlobalTotalUnreadCount"
        class="absolute -top-1 -right-1 min-w-5 h-5 px-1 rounded-full bg-red-500 text-white text-[10px] leading-5 text-center font-bold {{ $chatTotalUnread > 0 ? '' : 'hidden' }}">{{ $chatTotalUnread }}</span>
</div>

<div id="chatWindowGlobal"
    class="fixed bottom-24 right-6 w-[420px] h-[550px] bg-white rounded-2xl shadow-2xl border border-gray-100 flex flex-col overflow-hidden z-[9998]">
    <div class="bg-indigo-600 p-4 text-white flex justify-between items-center">
        <div class="flex items-center gap-2">
            <button id="chatGlobalSidebarToggle" type="button"
                class="hover:bg-indigo-500 rounded-full w-6 h-6 flex items-center justify-center"
                title="Toggle user list">
                <i class="fas fa-bars text-xs"></i>
            </button>
            <h3 id="chatGlobalHeaderTitle" class="font-semibold text-sm tracking-wide">Messaging</h3>
            <div id="chatGlobalHeaderUserMeta" class="hidden items-center gap-2 min-w-0">
                <img id="chatGlobalHeaderUserAvatar" src="" alt="" class="hidden w-6 h-6 rounded-full object-cover border border-white/50">
                <div id="chatGlobalHeaderUserFallback"
                    class="hidden w-6 h-6 rounded-full bg-white/20 text-white text-[10px] font-bold items-center justify-center"></div>
                <div class="min-w-0">
                    <p id="chatGlobalHeaderUserName" class="text-xs font-semibold truncate"></p>
                    <div class="flex items-center">
                        <span id="chatGlobalHeaderUserDot" class="chat-global-status-dot"></span>
                        <span id="chatGlobalHeaderUserStatus" class="text-[9px] text-white/80"></span>
                    </div>
                </div>
            </div>
        </div>
        <button id="closeChatGlobal" class="hover:bg-indigo-500 rounded-full w-6 h-6 flex items-center justify-center">&times;</button>
    </div>

    <div class="flex flex-1 overflow-hidden">
        <div id="chatGlobalSidebar" class="w-1/3 border-r border-gray-50 flex flex-col bg-gray-50/50">
            <div class="p-2 border-b border-gray-100">
                <input type="text" id="chatGlobalUserSearch"
                    class="w-full text-[11px] bg-white border border-gray-200 rounded-lg px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-indigo-400"
                    placeholder="Search staff...">
            </div>
            <div class="px-2 py-1 border-b border-gray-100 bg-white/70">
                <div class="flex gap-1 text-[10px]">
                    <button type="button" class="chat-global-filter-btn px-2 py-1 rounded bg-indigo-600 text-white" data-filter="all">All</button>
                    <button type="button" class="chat-global-filter-btn px-2 py-1 rounded bg-gray-100 text-gray-600" data-filter="online">Online</button>
                    <button type="button" class="chat-global-filter-btn px-2 py-1 rounded bg-gray-100 text-gray-600" data-filter="offline">Offline</button>
                </div>
            </div>
            <div id="chatGlobalUsers" class="flex-1 overflow-y-auto">
                @foreach ($chatEmployees as $emp)
                    @php
                        $avatarPath = null;
                        if (!empty($emp->image)) {
                            if (\Illuminate\Support\Str::startsWith($emp->image, ['http://', 'https://'])) {
                                $avatarPath = $emp->image;
                            } elseif (file_exists(public_path('image/' . $emp->image))) {
                                $avatarPath = asset('image/' . $emp->image);
                            } else {
                                $avatarPath = asset($emp->image);
                            }
                        }
                    @endphp
                    <div class="chat-global-user p-3 border-b border-gray-100 cursor-pointer hover:bg-white transition-all"
                        data-id="{{ $emp->id }}" data-name="{{ $emp->name }}" data-avatar="{{ $avatarPath ?? '' }}" data-online="{{ $emp->is_online ? 1 : 0 }}" data-has-chat="{{ $emp->last_message_at ? 1 : 0 }}">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-start gap-2 min-w-0 flex-1">
                                @if ($avatarPath)
                                    <img src="{{ $avatarPath }}" alt="{{ $emp->name }}"
                                        class="w-7 h-7 rounded-full object-cover border border-gray-200">
                                @else
                                    <div
                                        class="w-7 h-7 rounded-full bg-indigo-100 text-indigo-600 text-[10px] font-bold flex items-center justify-center border border-indigo-200">
                                        {{ strtoupper(mb_substr($emp->name, 0, 1)) }}
                                    </div>
                                @endif
                                <div class="min-w-0">
                                    <p class="text-[11px] font-bold text-gray-700 truncate mb-0 chat-global-user-name">{{ $emp->name }}</p>
                                    <div class="flex items-center mt-0.5">
                                        <span class="chat-global-status-dot {{ $emp->is_online ? 'chat-global-status-online' : 'chat-global-status-offline' }}"></span>
                                        <span class="text-[9px] text-gray-400 truncate">
                                            @if ($emp->is_online)
                                                Online
                                            @else
                                                Last active {{ $emp->last_seen_at ? $emp->last_seen_at->diffForHumans() : 'never' }}
                                            @endif
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <span class="chat-global-unread min-w-4 h-4 px-1 rounded-full bg-red-500 text-white text-[9px] leading-4 text-center {{ ($emp->unread_count ?? 0) > 0 ? '' : 'hidden' }}">{{ $emp->unread_count ?? 0 }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="flex-1 min-w-0 flex flex-col bg-white">
            <div id="chatGlobalMessages" class="flex-1 px-4 pt-5 pb-4 overflow-y-auto space-y-2 flex flex-col bg-slate-50/30">
                <div class="flex flex-col items-center justify-center h-full opacity-40">
                    <i class="fas fa-user-circle text-4xl mb-2 text-gray-300"></i>
                    <p class="text-[10px] uppercase tracking-widest text-gray-400">Select a staff</p>
                </div>
            </div>

            {{-- File attachment preview bar --}}
            <div id="chatGlobalFilePreview">
                <i class="fas fa-paperclip text-xs"></i>
                <span class="file-preview-name" id="chatGlobalFilePreviewName"></span>
                <span class="file-preview-remove" id="chatGlobalFileRemove" title="Remove file">&times;</span>
            </div>

            <div class="p-3 border-t border-gray-100 flex items-center gap-2 bg-white relative">
                {{-- Hidden file input --}}
                <input type="file" id="chatGlobalFileInput" accept=".png,.jpg,.jpeg,.pdf" class="hidden">

                {{-- File attach button --}}
                <button id="chatGlobalAttachBtn" type="button"
                    class="shrink-0 w-10 h-10 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 transition flex items-center justify-center"
                    title="Attach file (PNG, JPG, JPEG, PDF — max 3 MB)">
                    <i class="fas fa-paperclip text-sm"></i>
                </button>

                <input type="text" id="chatGlobalMessageInput"
                    class="flex-1 min-w-0 bg-gray-100 border-none rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-indigo-500"
                    placeholder="Type a message...">
                <button id="chatGlobalEmojiBtn" type="button"
                    class="shrink-0 w-10 h-10 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 transition flex items-center justify-center"
                    title="Add emoji">
                    <i class="far fa-smile"></i>
                </button>
                <button id="chatGlobalSendBtn" class="shrink-0 w-10 h-10 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition flex items-center justify-center">
                    <i class="fas fa-paper-plane text-xs"></i>
                </button>

                <div id="chatGlobalEmojiPanel"
                    class="hidden absolute bottom-full right-3 mb-2 bg-white border border-gray-200 rounded-xl shadow-lg p-2 w-56 z-[9999]">
                    <div class="grid grid-cols-8 gap-1 text-lg">
                        <button type="button" class="chat-global-emoji-item hover:bg-gray-100 rounded" data-emoji="😀">😀</button>
                        <button type="button" class="chat-global-emoji-item hover:bg-gray-100 rounded" data-emoji="😁">😁</button>
                        <button type="button" class="chat-global-emoji-item hover:bg-gray-100 rounded" data-emoji="😂">😂</button>
                        <button type="button" class="chat-global-emoji-item hover:bg-gray-100 rounded" data-emoji="🤣">🤣</button>
                        <button type="button" class="chat-global-emoji-item hover:bg-gray-100 rounded" data-emoji="😊">😊</button>
                        <button type="button" class="chat-global-emoji-item hover:bg-gray-100 rounded" data-emoji="😍">😍</button>
                        <button type="button" class="chat-global-emoji-item hover:bg-gray-100 rounded" data-emoji="😘">😘</button>
                        <button type="button" class="chat-global-emoji-item hover:bg-gray-100 rounded" data-emoji="😎">😎</button>
                        <button type="button" class="chat-global-emoji-item hover:bg-gray-100 rounded" data-emoji="😢">😢</button>
                        <button type="button" class="chat-global-emoji-item hover:bg-gray-100 rounded" data-emoji="😭">😭</button>
                        <button type="button" class="chat-global-emoji-item hover:bg-gray-100 rounded" data-emoji="😡">😡</button>
                        <button type="button" class="chat-global-emoji-item hover:bg-gray-100 rounded" data-emoji="👍">👍</button>
                        <button type="button" class="chat-global-emoji-item hover:bg-gray-100 rounded" data-emoji="👎">👎</button>
                        <button type="button" class="chat-global-emoji-item hover:bg-gray-100 rounded" data-emoji="👏">👏</button>
                        <button type="button" class="chat-global-emoji-item hover:bg-gray-100 rounded" data-emoji="🙏">🙏</button>
                        <button type="button" class="chat-global-emoji-item hover:bg-gray-100 rounded" data-emoji="🎉">🎉</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (function() {
        function runChatGlobalWidget() {
            const currentUserId = {{ (int) auth()->id() }};
            const storageKey = 'chat_global_selected_receiver_id';
            let receiverId = null;
            let currentGlobalFilter = 'all';
            let pendingFile = null; // holds the selected File object

            const chatSendUrl = "{{ route('role.chat.send', ['role' => $chatRoleSlug]) }}";
            const chatPresignUrl = "{{ route('role.chat.presign', ['role' => $chatRoleSlug]) }}";
            const chatFetchTemplate = "{{ route('role.chat.fetch', ['role' => $chatRoleSlug, 'receiver_id' => 'PLACEHOLDER']) }}";
            const chatMessageUpdateTemplate = "{{ route('role.chat.message.update', ['role' => $chatRoleSlug, 'chat' => 'PLACEHOLDER']) }}";
            const chatMessageDeleteTemplate = "{{ route('role.chat.message.destroy', ['role' => $chatRoleSlug, 'chat' => 'PLACEHOLDER']) }}";

            const MAX_FILE_SIZE = 3 * 1024 * 1024; // 3 MB
            const ALLOWED_TYPES = ['image/png', 'image/jpeg', 'application/pdf'];
            const IMAGE_TYPES = ['image/png', 'image/jpeg'];

            function escHtml(str) {
                return String(str || '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            }

            function linkifyText(str) {
                const escaped = escHtml(str);
                const urlPattern = /(\bhttps?:\/\/[^\s<]+[^\s<.,:;!?)'"\]])|(\bwww\.[^\s<]+[^\s<.,:;!?)'"\]])/gi;
                return escaped.replace(urlPattern, (match) => {
                    const href = /^https?:\/\//i.test(match) ? match : `https://${match}`;
                    return `<a href="${href}" target="_blank" rel="noopener noreferrer" class="chat-global-link">${match}</a>`;
                });
            }

            function userItemById(userId) {
                return document.querySelector(`#chatGlobalUsers .chat-global-user[data-id="${userId}"]`);
            }
            
            /* Paste handling for images */
            function handlePasteImage(e) {
                const items = e.clipboardData && e.clipboardData.items;
                if (!items) return;
                
                Array.from(items).forEach((item) => {
                    if (item.kind === 'file') {
                        const file = item.getAsFile();
                        if (!file) return;

                        if (!ALLOWED_TYPES.includes(file.type)) {
                            alert('Only PNG, JPG/JPEG, and PDF files are allowed.');
                            return;
                        }

                        if (file.size > MAX_FILE_SIZE) {
                            alert('File size must not exceed 3 MB.');
                            return;
                        }

                        // Optional: give a default name for pasted images
                        if (!file.name) {
                            const ext = file.type === 'image/png' ? 'png' : 'jpg';
                            file.name = `screenshot-${Date.now()}.${ext}`;
                        }

                        showFilePreview(file);
                        e.preventDefault();
                    }
                });
            }

            document.getElementById('chatGlobalMessageInput')?.addEventListener('paste', handlePasteImage);
            document.getElementById('chatGlobalMessages')?.addEventListener('paste', handlePasteImage);
            /* End of paste handling */


            function receiverName(userId) {
                const item = userItemById(userId);
                return item ? (item.dataset.name || 'Unknown') : 'Unknown';
            }

            function receiverAvatar(userId) {
                const item = userItemById(userId);
                return item ? (item.dataset.avatar || '') : '';
            }

            function receiverInitial(userId) {
                const name = receiverName(userId);
                if (!name) return 'U';
                return String(name).trim().charAt(0).toUpperCase() || 'U';
            }

            function updateGlobalHeaderUser(userId) {
                const item = userItemById(userId);

                if (!item) {
                    document.getElementById('chatGlobalHeaderUserMeta')?.classList.add('hidden');
                    document.getElementById('chatGlobalHeaderUserMeta')?.classList.remove('flex');
                    document.getElementById('chatGlobalHeaderTitle')?.classList.remove('hidden');
                    return;
                }

                const name = item.dataset.name || 'Unknown';
                const avatar = item.dataset.avatar || '';
                const isOnline = String(item.dataset.online || '0') === '1';
                const initial = String(name).trim().charAt(0).toUpperCase() || 'U';

                const nameEl = document.getElementById('chatGlobalHeaderUserName');
                const statusEl = document.getElementById('chatGlobalHeaderUserStatus');
                const dotEl = document.getElementById('chatGlobalHeaderUserDot');
                const avatarEl = document.getElementById('chatGlobalHeaderUserAvatar');
                const fallbackEl = document.getElementById('chatGlobalHeaderUserFallback');

                if (nameEl) nameEl.textContent = name;
                if (statusEl) statusEl.textContent = isOnline ? 'Online' : 'Offline';
                if (dotEl) {
                    dotEl.classList.remove('chat-global-status-online', 'chat-global-status-offline');
                    dotEl.classList.add(isOnline ? 'chat-global-status-online' : 'chat-global-status-offline');
                }

                if (avatarEl && fallbackEl) {
                    if (avatar) {
                        avatarEl.src = avatar;
                        avatarEl.classList.remove('hidden');
                        fallbackEl.classList.add('hidden');
                        fallbackEl.classList.remove('flex');
                    } else {
                        avatarEl.src = '';
                        avatarEl.classList.add('hidden');
                        fallbackEl.textContent = initial;
                        fallbackEl.classList.remove('hidden');
                        fallbackEl.classList.add('flex');
                    }
                }

                document.getElementById('chatGlobalHeaderTitle')?.classList.add('hidden');
                document.getElementById('chatGlobalHeaderUserMeta')?.classList.remove('hidden');
                document.getElementById('chatGlobalHeaderUserMeta')?.classList.add('flex');
            }

            function setUnread(userId, count) {
                const item = userItemById(userId);
                if (!item) return;
                item.dataset.hasChat = '1';
                const badge = item.querySelector('.chat-global-unread');
                if (!badge) return;

                const safeCount = Math.max(0, parseInt(count || 0, 10));
                badge.textContent = String(safeCount);
                badge.classList.toggle('hidden', safeCount === 0);
                syncTotalUnread();
            }

            function incUnread(userId) {
                const item = userItemById(userId);
                if (!item) return;
                const badge = item.querySelector('.chat-global-unread');
                const current = parseInt((badge && badge.textContent) || '0', 10) || 0;
                setUnread(userId, current + 1);
            }

            function syncTotalUnread() {
                let total = 0;
                document.querySelectorAll('#chatGlobalUsers .chat-global-unread').forEach((el) => {
                    if (!el.classList.contains('hidden')) {
                        total += parseInt(el.textContent || '0', 10) || 0;
                    }
                });

                const totalBadge = document.getElementById('chatGlobalTotalUnreadCount');
                if (!totalBadge) return;
                totalBadge.textContent = String(total);
                totalBadge.classList.toggle('hidden', total === 0);
            }

            /* Browser (desktop) notifications for incoming chat messages */
            const defaultNotificationIcon = "{{ site_setting_url('favicon', asset('airplane-ticket.png')) }}";

            function requestNotificationPermission() {
                if (!('Notification' in window)) return;
                if (Notification.permission === 'default') {
                    Notification.requestPermission();
                }
            }

            function notifyIncomingMessage(userId, chat) {
                if (!('Notification' in window) || Notification.permission !== 'granted') return;
                // Don't interrupt with a desktop notification if the user is already looking at the tab.
                if (!document.hidden && document.hasFocus()) return;

                const senderName = receiverName(userId);
                const body = (chat && chat.message && chat.message.trim())
                    ? chat.message.trim()
                    : 'Sent an attachment';

                const notification = new Notification(senderName, {
                    body: body.length > 120 ? body.slice(0, 117) + '...' : body,
                    icon: receiverAvatar(userId) || defaultNotificationIcon,
                    tag: `chat-${userId}`,
                });

                notification.onclick = function() {
                    window.focus();
                    document.getElementById('chatWindowGlobal')?.classList.add('active');
                    document.getElementById('chatGlobalSidebar')?.classList.remove('hidden');
                    receiverId = userId;
                    activateUser(userId);
                    updateGlobalHeaderUser(userId);
                    loadMessages(userId, receiverName(userId));
                    notification.close();
                };
            }

            function buildFileHtml(msg, isMine) {
                if (!msg.file_url || !msg.file_type) return '';

                const url = escHtml(msg.file_url);
                const isImage = IMAGE_TYPES.includes(msg.file_type);

                if (isImage) {
                    return `<a href="${url}" target="_blank" rel="noopener"><img src="${url}" class="chat-file-image" alt="Image attachment" loading="lazy"></a>`;
                }

                // PDF
                const filename = msg.file_url.split('/').pop() || 'document.pdf';
                return `<a href="${url}" target="_blank" rel="noopener" class="chat-file-pdf" download>
                    <i class="fas fa-file-pdf"></i> ${escHtml(decodeURIComponent(filename))}
                </a>`;
            }

            function renderMessages(name, messages) {
                const wrap = document.getElementById('chatGlobalMessages');
                if (!wrap) return;

                let html = `<div class="text-center mb-4"><span class="text-[10px] bg-indigo-50 text-indigo-600 px-3 py-1 rounded-full font-bold uppercase tracking-wider">Chat with ${escHtml(name)}</span></div>`;

                messages.forEach((msg) => {
                    const isMine = parseInt(msg.sender_id, 10) === currentUserId;
                    const sideClass = isMine ? 'chat-global-sent' : 'chat-global-received';
                    const alignClass = isMine ? 'items-end' : 'items-start';
                    const timeText = msg.created_at ? new Date(msg.created_at).toLocaleString() : '';
                    const statusText = isMine ? (msg.read_at ? 'Seen' : 'Sent') : '';
                    const seenAvatar = receiverAvatar(msg.receiver_id);
                    const seenInitial = receiverInitial(msg.receiver_id);
                    const seenIndicator = (isMine && msg.read_at)
                        ? `<div class="chat-global-seen-indicator">${seenAvatar
                            ? `<img src="${escHtml(seenAvatar)}" class="chat-global-seen-avatar" alt="seen">`
                            : `<span class="chat-global-seen-fallback">${escHtml(seenInitial)}</span>`}</div>`
                        : '';

                    const textContent = msg.message ? `<span>${linkifyText(msg.message)}</span>` : '';
                    const fileContent = buildFileHtml(msg, isMine);
                    const editedTag = msg.edited_at ? `<span class="chat-global-edited-tag">(edited)</span>` : '';

                    const menuHtml = isMine ? `
                        <button type="button" class="chat-global-msg-menu-btn" data-menu-toggle="${msg.id}" title="More">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <div class="chat-global-msg-menu hidden" data-menu-for="${msg.id}">
                            <button type="button" class="chat-global-msg-menu-item" data-action="edit" data-id="${msg.id}"><i class="fas fa-pen"></i> Edit</button>
                            <button type="button" class="chat-global-msg-menu-item chat-global-msg-menu-item-danger" data-action="delete" data-id="${msg.id}"><i class="fas fa-trash"></i> Delete</button>
                        </div>
                    ` : '';

                    html += `<div class="flex flex-col ${alignClass} ${isMine ? 'chat-global-row-sent' : 'chat-global-row-received'}" data-id="${msg.id}">
                        <div class="chat-global-bubble ${sideClass}" data-message-text="${escHtml(msg.message || '')}">${menuHtml}${textContent}${fileContent}</div>
                        <div class="chat-global-time">${escHtml(timeText)}${editedTag}${statusText ? `<span class="chat-global-meta-status">${escHtml(statusText)}</span>` : ''}</div>
                        ${seenIndicator}
                    </div>`;
                });

                wrap.innerHTML = html;
                wrap.scrollTop = wrap.scrollHeight;
            }

            function closeAllMsgMenus() {
                document.querySelectorAll('.chat-global-msg-menu').forEach((m) => m.classList.add('hidden'));
            }

            function startEditMessage(id) {
                const row = document.querySelector(`[data-id="${id}"]`);
                const bubble = row?.querySelector('.chat-global-bubble');
                if (!bubble) return;

                const currentText = bubble.dataset.messageText || '';
                bubble.innerHTML = `
                    <textarea class="chat-global-edit-textarea" rows="2">${escHtml(currentText)}</textarea>
                    <div class="chat-global-edit-actions">
                        <button type="button" class="chat-global-edit-cancel" data-edit-cancel="${id}">Cancel</button>
                        <button type="button" class="chat-global-edit-save" data-edit-save="${id}">Save</button>
                    </div>
                `;
                const textarea = bubble.querySelector('textarea');
                textarea?.focus();
                if (textarea) textarea.selectionStart = textarea.value.length;
            }

            async function saveEditedMessage(id, textarea) {
                const message = (textarea?.value || '').trim();
                if (!message) { alert('Message cannot be empty.'); return; }

                try {
                    const res = await fetch(chatMessageUpdateTemplate.replace('PLACEHOLDER', id), {
                        method: 'PUT',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({ message })
                    });

                    if (!res.ok) {
                        const data = await res.json().catch(() => ({}));
                        throw new Error(data.message || data.error || 'Failed to update message.');
                    }

                    if (receiverId) loadMessages(receiverId, receiverName(receiverId));
                } catch (err) {
                    alert(err.message || 'Failed to update message.');
                }
            }

            async function deleteMessage(id) {
                if (!confirm('Delete this message?')) return;

                try {
                    const res = await fetch(chatMessageDeleteTemplate.replace('PLACEHOLDER', id), {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        credentials: 'same-origin'
                    });

                    if (!res.ok) {
                        const data = await res.json().catch(() => ({}));
                        throw new Error(data.message || data.error || 'Failed to delete message.');
                    }

                    if (receiverId) loadMessages(receiverId, receiverName(receiverId));
                } catch (err) {
                    alert(err.message || 'Failed to delete message.');
                }
            }

            document.getElementById('chatGlobalMessages')?.addEventListener('click', function(e) {
                const menuToggle = e.target.closest('[data-menu-toggle]');
                if (menuToggle) {
                    const id = menuToggle.dataset.menuToggle;
                    const menu = document.querySelector(`.chat-global-msg-menu[data-menu-for="${id}"]`);
                    const wasHidden = menu?.classList.contains('hidden');
                    closeAllMsgMenus();
                    if (wasHidden) menu?.classList.remove('hidden');
                    return;
                }

                const actionBtn = e.target.closest('[data-action]');
                if (actionBtn) {
                    closeAllMsgMenus();
                    const id = actionBtn.dataset.id;
                    if (actionBtn.dataset.action === 'edit') startEditMessage(id);
                    else if (actionBtn.dataset.action === 'delete') deleteMessage(id);
                    return;
                }

                const editSave = e.target.closest('[data-edit-save]');
                if (editSave) {
                    const id = editSave.dataset.editSave;
                    const textarea = editSave.closest('.chat-global-bubble')?.querySelector('textarea');
                    saveEditedMessage(id, textarea);
                    return;
                }

                const editCancel = e.target.closest('[data-edit-cancel]');
                if (editCancel) {
                    if (receiverId) loadMessages(receiverId, receiverName(receiverId));
                    return;
                }

                const bubble = e.target.closest('.chat-global-bubble');
                if (!bubble || bubble.querySelector('textarea')) return;

                const time = bubble.nextElementSibling;
                if (!time || !time.classList.contains('chat-global-time')) return;

                document.querySelectorAll('.chat-global-time').forEach((item) => {
                    if (item !== time) item.style.display = 'none';
                });

                time.style.display = (time.style.display === 'block') ? 'none' : 'block';
            });

            // Close any open per-message menu when clicking outside it
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.chat-global-msg-menu') && !e.target.closest('[data-menu-toggle]')) {
                    closeAllMsgMenus();
                }
            });

            function loadMessages(userId, name) {
                const url = chatFetchTemplate.replace('PLACEHOLDER', userId);
                fetch(url, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                }).then((res) => res.json())
                    .then((messages) => {
                        renderMessages(name, messages);
                        setUnread(userId, 0);
                    })
                    .catch(() => {
                        const wrap = document.getElementById('chatGlobalMessages');
                        if (wrap) {
                            wrap.innerHTML = '<div class="text-center text-xs text-red-500 py-4">Failed to load messages.</div>';
                        }
                    });
            }

            function activateUser(userId) {
                document.querySelectorAll('#chatGlobalUsers .chat-global-user').forEach((item) => item.classList.remove('active'));
                const item = userItemById(userId);
                if (item) item.classList.add('active');
            }

            document.getElementById('chatToggleGlobal')?.addEventListener('click', function() {
                requestNotificationPermission();

                const chatWindow = document.getElementById('chatWindowGlobal');
                if (!chatWindow) return;

                chatWindow.classList.toggle('active');
                if (chatWindow.classList.contains('active')) {
                    document.getElementById('chatGlobalSidebar')?.classList.remove('hidden');

                    if (!receiverId) {
                        receiverId = localStorage.getItem(storageKey);
                    }
                    if (receiverId) {
                        activateUser(receiverId);
                        updateGlobalHeaderUser(receiverId);
                        loadMessages(receiverId, receiverName(receiverId));
                    } else {
                        updateGlobalHeaderUser(null);
                    }
                }
            });

            document.getElementById('closeChatGlobal')?.addEventListener('click', function() {
                document.getElementById('chatWindowGlobal')?.classList.remove('active');
            });

            document.getElementById('chatGlobalSidebarToggle')?.addEventListener('click', function() {
                document.getElementById('chatGlobalSidebar')?.classList.toggle('hidden');
            });

            function applyGlobalUserFilters() {
                const searchInput = document.getElementById('chatGlobalUserSearch');
                const value = (searchInput?.value || '').toLowerCase();

                document.querySelectorAll('#chatGlobalUsers .chat-global-user').forEach((item) => {
                    const name = (item.querySelector('.chat-global-user-name')?.textContent || '').toLowerCase();
                    const isOnline = String(item.dataset.online || '0') === '1';
                    const hasChat = String(item.dataset.hasChat || '0') === '1';

                    const nameMatched = name.includes(value);
                    const statusMatched = currentGlobalFilter === 'all'
                        ? hasChat
                        : (currentGlobalFilter === 'online' ? isOnline : !isOnline);

                    item.style.display = (nameMatched && statusMatched) ? '' : 'none';
                });
            }

            document.getElementById('chatGlobalUserSearch')?.addEventListener('keyup', function() {
                applyGlobalUserFilters();
            });

            document.querySelectorAll('.chat-global-filter-btn').forEach((btn) => {
                btn.addEventListener('click', function() {
                    currentGlobalFilter = this.dataset.filter || 'all';

                    document.querySelectorAll('.chat-global-filter-btn').forEach((item) => {
                        item.classList.remove('bg-indigo-600', 'text-white');
                        item.classList.add('bg-gray-100', 'text-gray-600');
                    });

                    this.classList.remove('bg-gray-100', 'text-gray-600');
                    this.classList.add('bg-indigo-600', 'text-white');

                    applyGlobalUserFilters();
                });
            });

            document.querySelectorAll('#chatGlobalUsers .chat-global-user').forEach((item) => {
                item.addEventListener('click', function() {
                    receiverId = this.dataset.id;
                    this.dataset.hasChat = '1';
                    localStorage.setItem(storageKey, receiverId);
                    activateUser(receiverId);
                    updateGlobalHeaderUser(receiverId);
                    loadMessages(receiverId, this.dataset.name || 'Unknown');
                });
            });

            // ── File attachment logic ──────────────────────────────────────────

            function showFilePreview(file) {
                pendingFile = file;
                const bar = document.getElementById('chatGlobalFilePreview');
                const nameEl = document.getElementById('chatGlobalFilePreviewName');
                if (bar) bar.style.display = 'flex';
                if (nameEl) nameEl.textContent = file.name;
            }

            function clearFilePreview() {
                pendingFile = null;
                const bar = document.getElementById('chatGlobalFilePreview');
                const nameEl = document.getElementById('chatGlobalFilePreviewName');
                const fileInput = document.getElementById('chatGlobalFileInput');
                if (bar) bar.style.display = 'none';
                if (nameEl) nameEl.textContent = '';
                if (fileInput) fileInput.value = '';
            }

            async function requestPresignUpload(file) {
                const res = await fetch(chatPresignUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        filename: file.name,
                        content_type: file.type,
                        size: file.size
                    })
                });

                if (!res.ok) {
                    const data = await res.json().catch(() => ({}));
                    throw new Error(data.error || 'Presign failed.');
                }

                return res.json();
            }

            async function uploadFileToR2(file, uploadUrl) {
                const put = await fetch(uploadUrl, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': file.type
                    },
                    body: file
                });

                if (!put.ok) {
                    throw new Error('Upload failed.');
                }
            }

            document.getElementById('chatGlobalAttachBtn')?.addEventListener('click', function() {
                document.getElementById('chatGlobalFileInput')?.click();
            });

            document.getElementById('chatGlobalFileInput')?.addEventListener('change', function() {
                const file = this.files && this.files[0];
                if (!file) return;

                if (!ALLOWED_TYPES.includes(file.type)) {
                    alert('Only PNG, JPG/JPEG, and PDF files are allowed.');
                    this.value = '';
                    return;
                }

                if (file.size > MAX_FILE_SIZE) {
                    alert('File size must not exceed 3 MB.');
                    this.value = '';
                    return;
                }

                showFilePreview(file);
            });

            document.getElementById('chatGlobalFileRemove')?.addEventListener('click', function() {
                clearFilePreview();
            });

            // ── Send message (text + optional file) ───────────────────────────

            async function sendMessage() {
                const input = document.getElementById('chatGlobalMessageInput');
                const msg = (input?.value || '').trim();

                if (!receiverId) return;
                if (!msg && !pendingFile) return;

                const sendBtn = document.getElementById('chatGlobalSendBtn');
                if (sendBtn) sendBtn.disabled = true;

                let fileUrl = null;
                let fileType = null;

                try {
                    if (pendingFile) {
                        const presign = await requestPresignUpload(pendingFile);
                        await uploadFileToR2(pendingFile, presign.upload_url);
                        fileUrl = presign.public_url;
                        fileType = pendingFile.type;
                    }

                    const formData = new FormData();
                    formData.append('receiver_id', receiverId);
                    if (msg) formData.append('message', msg);
                    if (fileUrl) {
                        formData.append('file_url', fileUrl);
                        if (fileType) formData.append('file_type', fileType);
                    }

                    const res = await fetch(chatSendUrl, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        credentials: 'same-origin',
                        body: formData
                    });

                    if (!res.ok) {
                        const data = await res.json().catch(() => ({}));
                        throw new Error(data.error || 'Send failed');
                    }

                    await res.json();
                    if (input) input.value = '';
                    clearFilePreview();
                    loadMessages(receiverId, receiverName(receiverId));
                } catch (err) {
                    alert(err.message || 'Failed to send message.');
                } finally {
                    if (sendBtn) sendBtn.disabled = false;
                }
            }

            document.getElementById('chatGlobalSendBtn')?.addEventListener('click', sendMessage);
            document.getElementById('chatGlobalMessageInput')?.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    sendMessage();
                }
            });

            document.getElementById('chatGlobalEmojiBtn')?.addEventListener('click', function(e) {
                e.stopPropagation();
                document.getElementById('chatGlobalEmojiPanel')?.classList.toggle('hidden');
            });

            document.querySelectorAll('.chat-global-emoji-item').forEach((item) => {
                item.addEventListener('click', function() {
                    const input = document.getElementById('chatGlobalMessageInput');
                    if (!input) return;
                    const emoji = this.dataset.emoji || '';
                    input.value = (input.value || '') + emoji;
                    input.focus();
                });
            });

            document.addEventListener('click', function(e) {
                const panel = document.getElementById('chatGlobalEmojiPanel');
                const btn = document.getElementById('chatGlobalEmojiBtn');
                if (!panel || !btn) return;

                const target = e.target;
                if (target instanceof Element && !panel.contains(target) && !btn.contains(target)) {
                    panel.classList.add('hidden');
                }
            });

            if (window.Echo) {
                window.Echo.private(`chat.{{ auth()->id() }}`)
                    .listen('.message.sent', (e) => {
                        const isActiveChat = String(receiverId || '') === String(e.chat.sender_id || '');
                        const windowOpen = document.getElementById('chatWindowGlobal')?.classList.contains('active');

                        if (isActiveChat && windowOpen) {
                            loadMessages(receiverId, receiverName(receiverId));
                        } else {
                            const incomingId = String(e.chat.sender_id || '');
                            const item = userItemById(incomingId);
                            if (item) {
                                item.classList.add('bg-indigo-50', 'border-r-4', 'border-indigo-500');
                            }
                            incUnread(incomingId);
                            notifyIncomingMessage(incomingId, e.chat);
                        }
                    })
                    .listen('.message.read', (e) => {
                        const isCurrentConversation = String(receiverId || '') === String(e.reader_id || '');
                        if (isCurrentConversation) {
                            loadMessages(receiverId, receiverName(receiverId));
                        }
                    })
                    .listen('.message.updated', (e) => {
                        const isActiveChat = String(receiverId || '') === String(e.chat.sender_id || '');
                        const windowOpen = document.getElementById('chatWindowGlobal')?.classList.contains('active');
                        if (isActiveChat && windowOpen) {
                            loadMessages(receiverId, receiverName(receiverId));
                        }
                    })
                    .listen('.message.deleted', (e) => {
                        const isActiveChat = String(receiverId || '') === String(e.sender_id || '');
                        const windowOpen = document.getElementById('chatWindowGlobal')?.classList.contains('active');
                        if (isActiveChat && windowOpen) {
                            loadMessages(receiverId, receiverName(receiverId));
                        }
                    });
            }

            const lastReceiverId = localStorage.getItem(storageKey);
            if (lastReceiverId && userItemById(lastReceiverId)) {
                receiverId = lastReceiverId;
            }

            syncTotalUnread();
            applyGlobalUserFilters();
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', runChatGlobalWidget);
        } else {
            runChatGlobalWidget();
        }
    })();
</script>
