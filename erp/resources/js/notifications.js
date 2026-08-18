class NotificationManager {
    constructor() {
        // Laravel renders the role-prefixed root, which is the only reliable source
        // once the app is served from a subdirectory: reading the role off
        // pathname[1] picks up the folder name there, and every fetch 404s.
        const roleBaseUrl = document.querySelector('meta[name="role-base-url"]')?.content;
        this.baseUrl = roleBaseUrl || `/${window.location.pathname.split('/')[1] || 'employee'}`;
        this.dropdownOpen = false;
        this.previousCount = null;
        this.pollInterval = 30000; // 30 seconds
        this.init();
    }

    init() {
        this.loadUnreadCount();
        this.setupEventListeners();
        this.startPolling();
        this.subscribeRealTime();
    }

    setupEventListeners() {
        const notificationBtn = document.getElementById('notificationButton');
        const notificationDropdown = document.getElementById('notificationDropdown');

        if (notificationDropdown) {
            notificationDropdown.classList.add('hidden');
        }

        if (notificationBtn) {
            notificationBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.toggleDropdown();
            });
        }

        const markAllReadBtn = document.getElementById('markAllReadBtn');
        if (markAllReadBtn) {
            markAllReadBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.markAllAsRead();
            });
        }

        document.addEventListener('click', (e) => {
            if (this.dropdownOpen &&
                !notificationDropdown?.contains(e.target) &&
                !notificationBtn?.contains(e.target)) {
                this.closeDropdown();
            }
        });
    }

    toggleDropdown() {
        this.dropdownOpen ? this.closeDropdown() : this.openDropdown();
    }

    /**
     * The live bulletin lists the same unread notifications, so any read, delete
     * or arrival here has to be reflected there straight away.
     */
    syncBulletin() {
        if (window.EpalHeadlineTicker) {
            window.EpalHeadlineTicker.refresh();
        }
    }

    openDropdown() {
        const dropdown = document.getElementById('notificationDropdown');
        if (!dropdown) return;
        dropdown.classList.remove('hidden');
        dropdown.classList.add('notification-dropdown-enter');
        this.dropdownOpen = true;
        this.loadRecentNotifications();
    }

    closeDropdown() {
        const dropdown = document.getElementById('notificationDropdown');
        if (!dropdown) return;
        dropdown.classList.add('hidden');
        dropdown.classList.remove('notification-dropdown-enter');
        this.dropdownOpen = false;
    }

    async loadUnreadCount() {
        try {
            const res = await fetch(`${this.baseUrl}/notifications/unread-count`);
            const data = await res.json();
            if (!data.success) return;

            const count = data.count;

            // Show toast for newly arrived notifications
            if (this.previousCount !== null && count > this.previousCount) {
                const newCount = count - this.previousCount;
                this.showToast(
                    `You have ${newCount} new notification${newCount > 1 ? 's' : ''}`,
                    'info'
                );
                this.animateBell();
                // Refresh dropdown list silently if it's open
                if (this.dropdownOpen) {
                    this.loadRecentNotifications();
                }
            }

            this.previousCount = count;
            this.updateBadge(count);
        } catch (_) {
            // silent fail — network hiccup
        }
    }

    async loadRecentNotifications() {
        const listContainer = document.getElementById('notificationList');
        if (!listContainer) return;

        listContainer.innerHTML = this.skeletonHTML();

        try {
            const res = await fetch(`${this.baseUrl}/notifications/recent`);
            const data = await res.json();
            if (data.success) {
                this.renderNotifications(data.notifications);
            } else {
                listContainer.innerHTML = this.errorHTML('Could not load notifications.');
            }
        } catch (_) {
            listContainer.innerHTML = this.errorHTML('Network error. Please try again.');
        }
    }

    renderNotifications(notifications) {
        const listContainer = document.getElementById('notificationList');
        const countBadge = document.getElementById('notificationDropdownCount');
        if (!listContainer) return;

        const unreadCount = notifications.filter(n => !n.is_read).length;

        if (countBadge) {
            if (unreadCount > 0) {
                countBadge.textContent = unreadCount;
                countBadge.classList.remove('hidden');
            } else {
                countBadge.classList.add('hidden');
            }
        }

        if (notifications.length === 0) {
            listContainer.innerHTML = this.emptyHTML();
            return;
        }

        // Group by date label
        const groups = this.groupByDate(notifications);
        let html = '';

        for (const [label, items] of Object.entries(groups)) {
            html += `<div class="px-3 py-1.5 text-[10px] font-semibold uppercase tracking-wider text-gray-400 bg-gray-50 border-b border-gray-100">${this.escapeHtml(label)}</div>`;
            html += items.map(n => this.notificationItemHTML(n)).join('');
        }

        listContainer.innerHTML = html;

        // Attach event listeners
        listContainer.querySelectorAll('.notif-mark-read').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.markAsRead(btn.dataset.id, btn.closest('.notification-item'));
            });
        });

        listContainer.querySelectorAll('.notif-delete').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.deleteNotification(btn.dataset.id, btn.closest('.notification-item'));
            });
        });

        // Ensure clicking the notification link marks it as read before navigating
        listContainer.querySelectorAll('.notification-item > a').forEach(anchor => {
            anchor.addEventListener('click', (e) => {
                const itemEl = anchor.closest('.notification-item');
                const id = itemEl?.dataset?.id;
                const href = anchor.getAttribute('href') || '#';

                // If no id or href is '#', just allow default behaviour
                if (!id || href === '#') return;

                e.preventDefault();

                // Mark as read, then navigate
                this.markAsRead(id, itemEl).finally(() => {
                    window.location.href = href;
                });
            });
        });
    }

    notificationItemHTML(n) {
        const tabSuffix = n.type === 'comment_added' ? '&tab=activity' : '';
        const taskUrl = n.task_id && n.board_id
            ? `${this.baseUrl}/board/${n.board_id}?task=${n.task_id}${tabSuffix}`
            : '#';

        const unreadDot = !n.is_read
            ? `<span class="w-2 h-2 rounded-full bg-blue-500 flex-shrink-0 mt-0.5"></span>`
            : `<span class="w-2 h-2 flex-shrink-0"></span>`;

        const rowBg = !n.is_read ? 'bg-blue-50/60' : 'bg-white';

        const markReadBtn = !n.is_read
            ? `<button class="notif-mark-read text-[10px] text-blue-500 hover:text-blue-700 whitespace-nowrap transition-colors" data-id="${n.id}">Mark read</button>`
            : '';

        return `
        <div class="notification-item ${rowBg} group relative border-b border-gray-50 last:border-0" data-id="${n.id}">
            <a href="${taskUrl}" class="flex items-start gap-3 px-4 py-3 hover:bg-gray-50/80 transition-colors duration-150">
                <div class="${n.icon_bg} w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 shadow-sm">
                    <i class="fas ${n.icon} text-white text-xs"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-gray-800 leading-snug line-clamp-2">${this.escapeHtml(n.message)}</p>
                    <p class="text-[10px] text-gray-400 mt-1">${n.created_at}</p>
                </div>
                ${unreadDot}
            </a>
            <div class="absolute right-2 top-1/2 -translate-y-1/2 hidden group-hover:flex items-center gap-1 bg-white/90 backdrop-blur-sm border border-gray-200 rounded-lg px-2 py-1 shadow-sm">
                ${markReadBtn}
                ${markReadBtn ? '<span class="text-gray-200 text-xs">|</span>' : ''}
                <button class="notif-delete text-[10px] text-red-400 hover:text-red-600 whitespace-nowrap transition-colors" data-id="${n.id}">Delete</button>
            </div>
        </div>`;
    }

    groupByDate(notifications) {
        const groups = {};
        const now = new Date();
        const todayStart = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        const yesterdayStart = new Date(todayStart - 86400000);

        notifications.forEach(n => {
            const raw = n.created_at_raw;
            let label = 'Older';

            if (raw) {
                const d = new Date(raw);
                if (d >= todayStart) label = 'Today';
                else if (d >= yesterdayStart) label = 'Yesterday';
            }

            if (!groups[label]) groups[label] = [];
            groups[label].push(n);
        });

        // Sort: Today first, Yesterday, then Older
        const order = ['Today', 'Yesterday', 'Older'];
        const sorted = {};
        order.forEach(k => { if (groups[k]) sorted[k] = groups[k]; });
        // Include any keys not in order
        Object.keys(groups).forEach(k => { if (!sorted[k]) sorted[k] = groups[k]; });
        return sorted;
    }

    async markAsRead(id, itemEl) {
        try {
            const res = await fetch(`${this.baseUrl}/notifications/${id}/mark-as-read`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                }
            });
            const data = await res.json();
            if (data.success && itemEl) {
                itemEl.classList.remove('bg-blue-50/60');
                
                // Remove the mark read button specifically, not the whole action container
                const markReadBtn = itemEl.querySelector('.notif-mark-read');
                if (markReadBtn) {
                    markReadBtn.remove();
                    // Also remove the separator if it exists
                    const separator = itemEl.querySelector('.text-gray-200.text-xs');
                    if (separator) separator.remove();
                }
                
                // Update the unread dot
                const dot = itemEl.querySelector('.w-2.h-2.rounded-full.bg-blue-500');
                if (dot) {
                    dot.classList.remove('bg-blue-500', 'rounded-full');
                }
                
                // Refresh count badge
                this.loadUnreadCount();
                this.syncBulletin();
            }
        } catch (_) {}
    }

    async markAllAsRead() {
        try {
            const res = await fetch(`${this.baseUrl}/notifications/mark-all-read`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                }
            });
            const data = await res.json();
            if (data.success) {
                this.loadUnreadCount();
                this.loadRecentNotifications();
                this.syncBulletin();
            }
        } catch (_) {}
    }

    async deleteNotification(id, itemEl) {
        try {
            const res = await fetch(`${this.baseUrl}/notifications/${id}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                }
            });
            const data = await res.json();
            if (data.success && itemEl) {
                itemEl.style.transition = 'opacity 0.2s, transform 0.2s';
                itemEl.style.opacity = '0';
                itemEl.style.transform = 'translateX(16px)';
                setTimeout(() => {
                    itemEl.remove();
                    // Re-check if list is empty
                    const list = document.getElementById('notificationList');
                    const remaining = list?.querySelectorAll('.notification-item');
                    if (remaining && remaining.length === 0) {
                        list.innerHTML = this.emptyHTML();
                    }
                }, 200);
                this.loadUnreadCount();
                this.syncBulletin();
            }
        } catch (_) {}
    }

    updateBadge(count) {
        const badge = document.getElementById('notificationBadge');
        if (!badge) return;
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.style.display = 'flex';
        } else {
            badge.style.display = 'none';
        }
    }

    animateBell() {
        const icon = document.getElementById('notificationBellIcon');
        if (!icon) return;
        icon.classList.add('bell-ring');
        setTimeout(() => icon.classList.remove('bell-ring'), 1000);
    }

    showToast(message, type = 'info') {
        const container = document.getElementById('notificationToastContainer');
        if (!container) return;

        const colors = {
            info: 'bg-blue-600',
            success: 'bg-emerald-600',
            warning: 'bg-amber-500',
            error: 'bg-red-600',
        };
        const icons = {
            info: 'fa-bell',
            success: 'fa-check-circle',
            warning: 'fa-triangle-exclamation',
            error: 'fa-circle-xmark',
        };

        const toast = document.createElement('div');
        toast.className = `pointer-events-auto flex items-center gap-3 px-4 py-3 rounded-xl shadow-lg text-white text-sm font-medium ${colors[type] || colors.info} toast-enter`;
        toast.style.maxWidth = '340px';
        toast.innerHTML = `
            <i class="fas ${icons[type] || icons.info} text-white/90 flex-shrink-0"></i>
            <span class="flex-1">${this.escapeHtml(message)}</span>
            <button class="ml-2 opacity-70 hover:opacity-100 transition-opacity text-xs" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>`;

        container.appendChild(toast);

        // Auto-dismiss after 5s
        setTimeout(() => {
            toast.style.transition = 'opacity 0.3s, transform 0.3s';
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(20px)';
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    }

    startPolling() {
        setInterval(() => {
            this.loadUnreadCount();
        }, this.pollInterval);
    }

    subscribeRealTime() {
        const userId = document.querySelector('meta[name="user-id"]')?.content;
        if (!userId || !window.Echo) return;

        window.Echo.private(`notifications.${userId}`)
            .listen('.notification.created', (data) => {
                this.showToast(data.message || 'New notification', 'info');
                this.animateBell();
                this.loadUnreadCount();
                this.syncBulletin();
                if (this.dropdownOpen) {
                    this.loadRecentNotifications();
                }
            });
    }

    skeletonHTML() {
        const item = `
        <div class="flex items-start gap-3 px-4 py-3 border-b border-gray-50 animate-pulse">
            <div class="w-8 h-8 rounded-full bg-gray-200 flex-shrink-0"></div>
            <div class="flex-1 space-y-1.5">
                <div class="h-3 bg-gray-200 rounded w-3/4"></div>
                <div class="h-2.5 bg-gray-200 rounded w-1/2"></div>
            </div>
        </div>`;
        return item.repeat(4);
    }

    emptyHTML() {
        return `
        <div class="py-10 flex flex-col items-center text-center text-gray-400">
            <div class="w-14 h-14 rounded-full bg-gray-100 flex items-center justify-center mb-3">
                <i class="fas fa-bell-slash text-2xl text-gray-300"></i>
            </div>
            <p class="text-sm font-medium text-gray-500">All caught up!</p>
            <p class="text-xs text-gray-400 mt-1">No new notifications right now.</p>
        </div>`;
    }

    errorHTML(msg) {
        return `
        <div class="py-8 flex flex-col items-center text-center text-red-400">
            <i class="fas fa-circle-exclamation text-2xl mb-2"></i>
            <p class="text-sm">${this.escapeHtml(msg)}</p>
        </div>`;
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

document.addEventListener('DOMContentLoaded', function () {
    window.notificationManager = new NotificationManager();
});
