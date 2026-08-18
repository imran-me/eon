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

export function formatDateDisplay(dateStr) {
    if (!dateStr) return '';
    // Parse as local time: replace space with 'T' to avoid UTC interpretation
    const normalizedStr = dateStr.replace(' ', 'T');
    const d = new Date(normalizedStr);
    return d.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
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
