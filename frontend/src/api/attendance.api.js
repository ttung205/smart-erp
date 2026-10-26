import { apiClient } from './client';

const queryString = params => new URLSearchParams(
  Object.entries(params).filter(([, value]) => value !== undefined && value !== null && value !== '')
).toString();

const withQuery = (path, params = {}) => {
  const qs = queryString(params);
  return `${path}${qs ? `?${qs}` : ''}`;
};

const json = (method, body) => ({ method, body: body === undefined ? undefined : JSON.stringify(body) });
const BASE = '/api/v1/eoffice';

/** Chấm công, yêu cầu công, hồ sơ nhân sự và ca làm việc (eoffice-service). */
export const attendanceApi = {
  // Chấm công của tôi
  getMyTimesheet(month) {
    return apiClient(withQuery(`${BASE}/attendance/me`, { month }));
  },
  checkIn() {
    return apiClient(`${BASE}/attendance/check-in`, json('POST'));
  },
  getTimesheet(userId, month) {
    return apiClient(withQuery(`${BASE}/attendance/days`, { user_id: userId, month }));
  },
  getPunches(userId, date) {
    return apiClient(withQuery(`${BASE}/attendance/punches`, { user_id: userId, date }));
  },

  // Tổng hợp & quản lý (HR)
  getSummary(month) {
    return apiClient(withQuery(`${BASE}/attendance/summary`, { month }));
  },
  importPunches(fileName, rows) {
    return apiClient(`${BASE}/attendance/import`, json('POST', { file_name: fileName, rows }));
  },
  getImportBatches() {
    return apiClient(`${BASE}/attendance/import-batches`);
  },
  recalculate(month, userId) {
    return apiClient(`${BASE}/attendance/recalculate`, json('POST', { month, user_id: userId }));
  },
  addManualWork(data) {
    return apiClient(`${BASE}/attendance/manual-work`, json('POST', data));
  },
  clearDay(data) {
    return apiClient(`${BASE}/attendance/clear-day`, json('POST', data));
  },
  voidPunch(id, reason) {
    return apiClient(`${BASE}/attendance/punches/${id}/void`, json('POST', { reason }));
  },
  getAuditLogs(params = {}) {
    return apiClient(withQuery(`${BASE}/attendance/audit-logs`, params));
  },

  // Hồ sơ nhân sự và ca làm việc
  getProfiles() {
    return apiClient(`${BASE}/employee-profiles`);
  },
  syncProfiles() {
    return apiClient(`${BASE}/employee-profiles/sync`, json('POST'));
  },
  updateProfile(id, data) {
    return apiClient(`${BASE}/employee-profiles/${id}`, json('PUT', data));
  },
  getShifts() {
    return apiClient(`${BASE}/shifts`);
  },
  saveShift(data, id) {
    return apiClient(id ? `${BASE}/shifts/${id}` : `${BASE}/shifts`, json(id ? 'PUT' : 'POST', data));
  },

  // Yêu cầu bổ sung công / bù công / remote / công tác / OT
  getRequests(params = {}) {
    return apiClient(withQuery(`${BASE}/attendance-requests`, params));
  },
  getRequest(id) {
    return apiClient(`${BASE}/attendance-requests/${id}`);
  },
  createRequest(data) {
    return apiClient(`${BASE}/attendance-requests`, json('POST', data));
  },
  updateRequest(id, data) {
    return apiClient(`${BASE}/attendance-requests/${id}`, json('PUT', data));
  },
  deleteRequest(id) {
    return apiClient(`${BASE}/attendance-requests/${id}`, json('DELETE'));
  },
  submitRequest(id) {
    return apiClient(`${BASE}/attendance-requests/${id}/submit`, json('POST'));
  },
  cancelRequest(id, reason) {
    return apiClient(`${BASE}/attendance-requests/${id}/cancel`, json('POST', { reason }));
  }
};

/** Kỳ lương, bảng lương, phiếu lương (eoffice-service) và danh mục ngân hàng (finance-service). */
export const payrollApi = {
  getRules() {
    return apiClient(`${BASE}/payroll/rules`);
  },
  updateRule(key, value) {
    return apiClient(`${BASE}/payroll/rules/${key}`, json('PUT', { value }));
  },
  getPeriods() {
    return apiClient(`${BASE}/payroll/periods`);
  },
  createPeriod(year, month) {
    return apiClient(`${BASE}/payroll/periods`, json('POST', { year, month }));
  },
  getPeriod(id) {
    return apiClient(`${BASE}/payroll/periods/${id}`);
  },
  lockAttendance(id) {
    return apiClient(`${BASE}/payroll/periods/${id}/lock-attendance`, json('POST'));
  },
  unlockAttendance(id, reason) {
    return apiClient(`${BASE}/payroll/periods/${id}/unlock-attendance`, json('POST', { reason }));
  },
  calculate(id) {
    return apiClient(`${BASE}/payroll/periods/${id}/calculate`, json('POST'));
  },
  submit(id) {
    return apiClient(`${BASE}/payroll/periods/${id}/submit`, json('POST'));
  },
  approve(id, comment) {
    return apiClient(`${BASE}/payroll/periods/${id}/approve`, json('POST', { comment }));
  },
  returnPeriod(id, comment) {
    return apiClient(`${BASE}/payroll/periods/${id}/return`, json('POST', { comment }));
  },
  pay(id, bankAccountId) {
    return apiClient(`${BASE}/payroll/periods/${id}/pay`, json('POST', { bank_account_id: bankAccountId }));
  },
  getAdjustments(id) {
    return apiClient(`${BASE}/payroll/periods/${id}/adjustments`);
  },
  addAdjustment(id, data) {
    return apiClient(`${BASE}/payroll/periods/${id}/adjustments`, json('POST', data));
  },
  removeAdjustment(adjustmentId) {
    return apiClient(`${BASE}/payroll/adjustments/${adjustmentId}`, json('DELETE'));
  },
  getMySlips() {
    return apiClient(`${BASE}/payroll/my-slips`);
  },
  getSlip(id) {
    return apiClient(`${BASE}/payroll/slips/${id}`);
  },
  // Danh sách tài khoản ngân hàng để chọn nguồn chi lương (cần quyền finance.view)
  getBankAccounts() {
    return apiClient('/api/v1/finance/master-data');
  }
};
