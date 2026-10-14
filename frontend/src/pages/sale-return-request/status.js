export const saleReturnStatusOptions = [
  { value: 'PENDING_INSPECTION', label: 'Chờ kiểm tra' },
  { value: 'INSPECTING', label: 'Đang kiểm tra' },
  { value: 'REJECTED', label: 'Từ chối' },
  { value: 'TECHNICAL_RECEIVED', label: 'Đã nhập kho KT' },
  { value: 'PENDING_MAIN_APPROVAL', label: 'Chờ duyệt kho chính' },
  { value: 'MAIN_REJECTED', label: 'Kho chính từ chối' },
  { value: 'PENDING_POSTING', label: 'Chờ ghi sổ' },
  { value: 'COMPLETED', label: 'Đã lập phiếu' }
];

export const statusMeta = status => {
  const severities = { PENDING_INSPECTION: 'warn', INSPECTING: 'info', REJECTED: 'danger', TECHNICAL_RECEIVED: 'secondary', PENDING_MAIN_APPROVAL: 'warn', MAIN_REJECTED: 'danger', PENDING_POSTING: 'info', COMPLETED: 'success' };
  return { label: saleReturnStatusOptions.find(option => option.value === status)?.label || status, severity: severities[status] || 'secondary' };
};
