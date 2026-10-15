export const LEAVE_STATUS = {
  DRAFT: { label: 'Nháp', severity: 'secondary' },
  PENDING: { label: 'Chờ duyệt', severity: 'warn' },
  APPROVED: { label: 'Đã duyệt', severity: 'success' },
  REJECTED: { label: 'Từ chối', severity: 'danger' },
  RETURNED: { label: 'Trả lại', severity: 'info' },
  CANCELLED: { label: 'Đã hủy', severity: 'secondary' }
};

export const APPROVAL_ACTION_LABELS = {
  CREATED: 'Tạo đơn',
  SUBMITTED: 'Gửi duyệt',
  APPROVED: 'Duyệt',
  REJECTED: 'Từ chối',
  RETURNED: 'Trả lại',
  CANCELLED: 'Hủy đơn',
  AUTO_APPROVED: 'Tự động duyệt'
};

export const SESSION_OPTIONS = [
  { label: 'Cả ngày', value: 'FULL' },
  { label: 'Buổi sáng', value: 'MORNING' },
  { label: 'Buổi chiều', value: 'AFTERNOON' }
];

export const SESSION_LABELS = { FULL: 'Cả ngày', MORNING: 'Sáng', AFTERNOON: 'Chiều' };

export const formatDays = value => {
  const number = Number(value || 0);
  return Number.isInteger(number) ? String(number) : number.toFixed(1).replace('.', ',');
};

export const toIsoDate = date => {
  if (!date) return null;
  if (typeof date === 'string') return date.substring(0, 10);
  const pad = n => String(n).padStart(2, '0');
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
};

export const fromIsoDate = value => {
  if (!value) return null;
  const [y, m, d] = String(value).substring(0, 10).split('-').map(Number);
  return new Date(y, m - 1, d);
};
