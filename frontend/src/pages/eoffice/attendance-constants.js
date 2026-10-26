// Nhãn và màu hiển thị cho chấm công, yêu cầu công và bảng lương

export const DAY_STATUS = {
  PRESENT: { label: 'Đủ công', severity: 'success' },
  HALF_DAY: { label: 'Nửa công', severity: 'warn' },
  MISSING_PUNCH: { label: 'Thiếu chấm công', severity: 'danger' },
  ABSENT: { label: 'Vắng', severity: 'danger' },
  LEAVE_PAID: { label: 'Nghỉ phép', severity: 'info' },
  LEAVE_UNPAID: { label: 'Nghỉ không lương', severity: 'secondary' },
  HOLIDAY: { label: 'Ngày lễ', severity: 'info' },
  WEEKEND: { label: 'Cuối tuần', severity: 'secondary' },
  REMOTE: { label: 'Làm từ xa', severity: 'success' },
  BUSINESS_TRIP: { label: 'Công tác', severity: 'success' },
  PENDING: { label: 'Đang diễn ra', severity: 'secondary' },
  OUT_OF_EMPLOYMENT: { label: 'Ngoài thời gian làm việc', severity: 'secondary' }
};

// Ngày có thể gửi yêu cầu sửa công
export const FIXABLE_STATUSES = ['MISSING_PUNCH', 'ABSENT', 'HALF_DAY'];

export const REQUEST_TYPES = {
  ADD_PUNCH: { label: 'Bổ sung chấm công', hint: 'Quên chấm giờ vào hoặc giờ ra' },
  MAKEUP: { label: 'Bù công', hint: 'Không có dữ liệu chấm công nhưng thực tế có đi làm' },
  REMOTE: { label: 'Làm việc từ xa', hint: 'Làm việc tại nhà / ngoài văn phòng' },
  BUSINESS_TRIP: { label: 'Công tác', hint: 'Đi công tác ngoài văn phòng' },
  OVERTIME: { label: 'Làm thêm giờ', hint: 'Đăng ký làm thêm, chỉ phần đã duyệt và có mặt thực tế được tính' }
};

export const REQUEST_TYPE_OPTIONS = Object.entries(REQUEST_TYPES).map(([value, { label }]) => ({ value, label }));

export const REQUEST_STATUS = {
  DRAFT: { label: 'Nháp', severity: 'secondary' },
  PENDING: { label: 'Chờ duyệt', severity: 'warn' },
  APPROVED: { label: 'Đã duyệt', severity: 'success' },
  REJECTED: { label: 'Từ chối', severity: 'danger' },
  RETURNED: { label: 'Trả lại', severity: 'info' },
  CANCELLED: { label: 'Đã hủy', severity: 'secondary' }
};

export const PUNCH_SOURCES = {
  DEVICE_IMPORT: 'Máy chấm công',
  SIMULATOR: 'Máy chấm công (mô phỏng)',
  WEB: 'Chấm công trên web',
  ADJUSTMENT: 'Bổ sung được duyệt / HR'
};

export const WORK_MODES = [
  { value: 'ONSITE', label: 'Tại văn phòng' },
  { value: 'HYBRID', label: 'Kết hợp' },
  { value: 'REMOTE', label: 'Từ xa' }
];

export const SATURDAY_MODES = [
  { value: 'OFF', label: 'Nghỉ thứ 7' },
  { value: 'HALF', label: 'Làm nửa ngày thứ 7' },
  { value: 'FULL', label: 'Làm cả ngày thứ 7' }
];

export const PAYROLL_STATUS = {
  DRAFT: { label: 'Đang lập', severity: 'secondary' },
  CALCULATED: { label: 'Đã tính', severity: 'info' },
  PENDING_APPROVAL: { label: 'Chờ duyệt', severity: 'warn' },
  APPROVED: { label: 'Đã duyệt', severity: 'success' },
  PAID: { label: 'Đã chi lương', severity: 'success' },
  RETURNED: { label: 'Bị trả lại', severity: 'danger' }
};

export const PAYROLL_HISTORY_LABELS = {
  ATTENDANCE_LOCKED: 'Khóa công',
  ATTENDANCE_UNLOCKED: 'Mở khóa công',
  CALCULATED: 'Tính lương',
  SUBMITTED: 'Gửi duyệt',
  APPROVED: 'Duyệt',
  RETURNED: 'Trả lại',
  PAID: 'Chi lương'
};

export const AUDIT_ACTIONS = {
  ADD_WORK: 'Thêm công',
  REMOVE_WORK: 'Xóa công',
  VOID_PUNCH: 'Hủy log chấm công'
};

export const OT_TYPES = { WEEKDAY: 'Ngày thường', WEEKEND: 'Ngày nghỉ', HOLIDAY: 'Ngày lễ' };

const pad = value => String(value).padStart(2, '0');

export const currentMonth = () => {
  const now = new Date();
  return `${now.getFullYear()}-${pad(now.getMonth() + 1)}`;
};

export const shiftMonth = (month, delta) => {
  const [year, mon] = month.split('-').map(Number);
  const date = new Date(year, mon - 1 + delta, 1);
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}`;
};

export const monthLabel = month => {
  const [year, mon] = month.split('-');
  return `${mon}/${year}`;
};

export const formatTime = value => {
  if (!value) return '—';
  const text = String(value);
  const match = text.match(/(\d{2}):(\d{2})/);
  return match ? `${match[1]}:${match[2]}` : text;
};

export const formatMinutes = minutes => {
  const value = Number(minutes || 0);
  if (!value) return '—';
  return value >= 60 ? `${Math.floor(value / 60)}g${pad(value % 60)}` : `${value}'`;
};

export const WEEKDAYS = ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'];

export const weekdayOf = isoDate => WEEKDAYS[new Date(`${String(isoDate).substring(0, 10)}T00:00:00`).getDay()];
