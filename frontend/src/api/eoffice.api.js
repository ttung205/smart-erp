import { apiClient } from './client';

const queryString = params => new URLSearchParams(
  Object.entries(params).filter(([, value]) => value !== undefined && value !== null && value !== '')
).toString();

const withQuery = (path, params = {}) => {
  const qs = queryString(params);
  return `${path}${qs ? `?${qs}` : ''}`;
};

export const eofficeApi = {
  // Đơn xin nghỉ phép
  getLeaveRequests(params = {}) {
    return apiClient(withQuery('/api/v1/eoffice/leave-requests', params));
  },
  getLeaveRequest(id) {
    return apiClient(`/api/v1/eoffice/leave-requests/${id}`);
  },
  createLeaveRequest(data) {
    return apiClient('/api/v1/eoffice/leave-requests', { method: 'POST', body: JSON.stringify(data) });
  },
  updateLeaveRequest(id, data) {
    return apiClient(`/api/v1/eoffice/leave-requests/${id}`, { method: 'PUT', body: JSON.stringify(data) });
  },
  deleteLeaveRequest(id) {
    return apiClient(`/api/v1/eoffice/leave-requests/${id}`, { method: 'DELETE' });
  },
  submitLeaveRequest(id) {
    return apiClient(`/api/v1/eoffice/leave-requests/${id}/submit`, { method: 'POST' });
  },
  cancelLeaveRequest(id, reason) {
    return apiClient(`/api/v1/eoffice/leave-requests/${id}/cancel`, { method: 'POST', body: JSON.stringify({ reason }) });
  },
  previewLeaveDays(data) {
    return apiClient('/api/v1/eoffice/leave-requests/preview', { method: 'POST', body: JSON.stringify(data) });
  },

  // Danh mục & quỹ phép
  getLeaveTypes() {
    return apiClient('/api/v1/eoffice/leave-types');
  },
  getHolidays(year) {
    return apiClient(withQuery('/api/v1/eoffice/holidays', { year }));
  },
  getMyLeaveBalance(year) {
    return apiClient(withQuery('/api/v1/eoffice/leave-balances/me', { year }));
  },
  getLeaveCalendar(params = {}) {
    return apiClient(withQuery('/api/v1/eoffice/leave-calendar', params));
  },

  // Quản lý quỹ phép (HR, quyền eoffice.manage)
  getLeaveBalances(year) {
    return apiClient(withQuery('/api/v1/eoffice/leave-balances', { year }));
  },
  updateLeaveBalance(userId, data) {
    return apiClient(`/api/v1/eoffice/leave-balances/${userId}`, { method: 'PUT', body: JSON.stringify(data) });
  },
  getProRataLeave(periodStart) {
    return apiClient(withQuery('/api/v1/eoffice/leave-balances/pro-rata', { period_start: periodStart }));
  },
  getUserLeaveRequests(userId, year) {
    return apiClient(withQuery(`/api/v1/eoffice/leave-balances/${userId}/requests`, { year }));
  },

  // Hộp duyệt dùng chung
  getApprovalInbox(params = {}) {
    return apiClient(withQuery('/api/v1/eoffice/approvals/inbox', params));
  },
  decideApproval(stepId, decision, comment) {
    const action = { APPROVED: 'approve', REJECTED: 'reject', RETURNED: 'return' }[decision];
    return apiClient(`/api/v1/eoffice/approvals/${stepId}/${action}`, { method: 'POST', body: JSON.stringify({ comment }) });
  }
};
