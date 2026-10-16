const pad = (n) => String(n).padStart(2, '0');

const sameDay = (a, b) => a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();

export function formatClock(iso) {
  if (!iso) return '';
  const d = new Date(iso);
  return `${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

/** Giờ cho danh sách hội thoại: hôm nay hiện giờ, trong tuần hiện thứ, cũ hơn hiện ngày. */
export function formatListTime(iso) {
  if (!iso) return '';
  const d = new Date(iso);
  const now = new Date();
  if (sameDay(d, now)) return formatClock(iso);
  const days = Math.floor((now - d) / 86400000);
  if (days < 7) return ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'][d.getDay()];
  return `${pad(d.getDate())}/${pad(d.getMonth() + 1)}${d.getFullYear() === now.getFullYear() ? '' : `/${d.getFullYear()}`}`;
}

export function formatDayLabel(iso) {
  const d = new Date(iso);
  const now = new Date();
  if (sameDay(d, now)) return 'Hôm nay';
  const yesterday = new Date(now);
  yesterday.setDate(now.getDate() - 1);
  if (sameDay(d, yesterday)) return 'Hôm qua';
  return `${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${d.getFullYear()}`;
}

export function dayKey(iso) {
  const d = new Date(iso);
  return `${d.getFullYear()}-${d.getMonth()}-${d.getDate()}`;
}

export function formatFileSize(bytes) {
  if (!bytes) return '0 KB';
  if (bytes < 1024 * 1024) return `${Math.max(1, Math.round(bytes / 1024))} KB`;
  return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

const URL_PATTERN = /(https?:\/\/[^\s<]+)/g;

/**
 * Tách nội dung thành các đoạn {type: text|link|mention, value} để render bằng v-for (không dùng v-html).
 * mentionNames: danh sách tên được nhắc ("@Tên" trong nội dung sẽ được tô màu).
 */
export function toSegments(content, mentionNames = []) {
  if (!content) return [];
  const names = [...new Set(mentionNames.filter(Boolean))].sort((a, b) => b.length - a.length);
  const escaped = names.map((n) => n.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));
  const mentionPattern = escaped.length ? new RegExp(`@(${escaped.join('|')}|Tất cả)`, 'g') : /@(Tất cả)/g;

  const segments = [];
  for (const part of content.split(URL_PATTERN)) {
    if (!part) continue;
    if (/^https?:\/\//.test(part)) {
      segments.push({ type: 'link', value: part });
      continue;
    }
    let last = 0;
    for (const match of part.matchAll(mentionPattern)) {
      if (match.index > last) segments.push({ type: 'text', value: part.slice(last, match.index) });
      segments.push({ type: 'mention', value: match[0] });
      last = match.index + match[0].length;
    }
    if (last < part.length) segments.push({ type: 'text', value: part.slice(last) });
  }
  return segments;
}

export function fileIcon(contentType = '', name = '') {
  const ext = name.split('.').pop().toLowerCase();
  if (contentType.startsWith('image/')) return 'pi pi-image text-amber-600';
  if (ext === 'pdf') return 'pi pi-file-pdf text-red-600';
  if (['xls', 'xlsx', 'csv'].includes(ext)) return 'pi pi-file-excel text-emerald-600';
  if (['doc', 'docx'].includes(ext)) return 'pi pi-file-word text-blue-600';
  if (['zip', 'rar', '7z'].includes(ext)) return 'pi pi-box text-violet-600';
  return 'pi pi-file text-slate-500';
}
