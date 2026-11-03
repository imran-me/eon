/**
 * Task Utilities — Shared helper functions.
 */

export function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

export function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

export function getTimeAgo(timestamp) {
    const now = new Date();
    const past = new Date(timestamp);
    const seconds = Math.floor((now - past) / 1000);

    if (seconds < 60) return 'just now';
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return `${minutes} minute${minutes !== 1 ? 's' : ''} ago`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours} hour${hours !== 1 ? 's' : ''} ago`;
    const days = Math.floor(hours / 24);
    return `${days} day${days !== 1 ? 's' : ''} ago`;
}

/**
 * "17:09:33 24/08/26" — HHMMSS DDMMYY, the owner's format for the task module.
 *
 * Seconds are the point of it, not decoration: without them an item worked on
 * for eight seconds printed the same value on both ends of its range chip
 * ("5.09pm:24/08/2026 → 5.09pm:24/08/2026") and looked broken. 24-hour, so
 * there is no am/pm to read, and a two-digit year to keep it short.
 *
 * This is the single definition for the whole task module — the drawer, the
 * card chips, the sub-work rows and their tooltips all come through here, so
 * they cannot drift apart. Its PHP twin is Task::stampLabel().
 */
export function formatDateDisplay(dateStr) {
    return stamp(dateStr, true);
}

/**
 * The same stamp without seconds — "17:09 24/08/26" — for the Kanban card only.
 *
 * Measured, not guessed: a card gives its footer 270px, and two full chips plus
 * the state pill and the priority icon need 296px. Dropping the seconds brings
 * two chips from 102px to 89px each and the row lands on exactly 270px. That is
 * a real fit but a tight one — a board whose column is named something longer
 * than "PROGRESS" widens the state pill and eats the margin.
 */
export function formatDateShort(dateStr) {
    return stamp(dateStr, false);
}

function stamp(dateStr, withSeconds) {
    if (!dateStr) return '';
    // Parse as local time: replace space with 'T' to avoid UTC interpretation
    const d = new Date(String(dateStr).replace(' ', 'T'));
    if (isNaN(d)) return '';

    const p = n => String(n).padStart(2, '0');
    const time = withSeconds
        ? `${p(d.getHours())}:${p(d.getMinutes())}:${p(d.getSeconds())}`
        : `${p(d.getHours())}:${p(d.getMinutes())}`;

    return `${time} ${p(d.getDate())}/${p(d.getMonth() + 1)}/${String(d.getFullYear()).slice(-2)}`;
}

export function formatDatetimeLocal(dateStr) {
    if (!dateStr) return '';
    // Parse as local time: replace space with 'T' to avoid UTC interpretation
    const normalizedStr = dateStr.replace(' ', 'T');
    const d = new Date(normalizedStr);
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    const h = String(d.getHours()).padStart(2, '0');
    const min = String(d.getMinutes()).padStart(2, '0');
    return `${y}-${m}-${day}T${h}:${min}`;
}

export function getFileIcon(extension) {
    const map = {
        'jpg': 'fa-regular fa-file-image', 'jpeg': 'fa-regular fa-file-image',
        'png': 'fa-regular fa-file-image', 'gif': 'fa-regular fa-file-image',
        'svg': 'fa-regular fa-file-image',
        'pdf': 'fa-regular fa-file-pdf',
        'doc': 'fa-regular fa-file-word', 'docx': 'fa-regular fa-file-word',
        'xls': 'fa-regular fa-file-excel', 'xlsx': 'fa-regular fa-file-excel',
        'ppt': 'fa-regular fa-file-powerpoint', 'pptx': 'fa-regular fa-file-powerpoint',
        'zip': 'fa-regular fa-file-zipper', 'rar': 'fa-regular fa-file-zipper', '7z': 'fa-regular fa-file-zipper',
        'html': 'fa-regular fa-file-code', 'css': 'fa-regular fa-file-code',
        'js': 'fa-regular fa-file-code', 'php': 'fa-regular fa-file-code', 'json': 'fa-regular fa-file-code',
        'txt': 'fa-regular fa-file-lines',
        'mp4': 'fa-regular fa-file-video', 'avi': 'fa-regular fa-file-video', 'mov': 'fa-regular fa-file-video',
        'mp3': 'fa-regular fa-file-audio', 'wav': 'fa-regular fa-file-audio',
    };
    return map[extension] || 'fa-regular fa-file';
}
