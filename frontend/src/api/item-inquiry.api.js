import { apiClient } from './client';
import { storage } from '../utils/storage';

const root = '/api/v1/purchase/inquiries';
const json = (data) => ({ headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });

export const itemInquiryApi = {
  requests: () => apiClient(`${root}/requests`),
  request: (id) => apiClient(`${root}/requests/${id}`),
  create: (data) => apiClient(`${root}/requests`, { method: 'POST', ...json(data) }),
  update: (id, data) => apiClient(`${root}/requests/${id}`, { method: 'PUT', ...json(data) }),
  submit: (id) => apiClient(`${root}/requests/${id}/submit`, { method: 'POST' }),
  close: (id) => apiClient(`${root}/requests/${id}/close`, { method: 'POST' }),
  upload: (id, file) => { const body = new FormData(); body.append('file', file); return apiClient(`${root}/requests/${id}/attachments`, { method: 'POST', body }); },
  download: async (id, attachment) => {
    const response = await fetch(`${root}/requests/${id}/attachments/${attachment.id}`, { headers: { Authorization: `Bearer ${storage.getToken()}` } });
    if (!response.ok) throw new Error('Không tải được tệp.');
    const url = URL.createObjectURL(await response.blob());
    const a = document.createElement('a'); a.href = url; a.download = attachment.file_name; a.click();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  },
  inquiries: (id) => apiClient(`${root}/requests/${id}/supplier-inquiries`),
  allInquiries: () => apiClient(`${root}/supplier-inquiries`),
  inquiry: (id) => apiClient(`${root}/supplier-inquiries/${id}`),
  createInquiry: (id, data) => apiClient(`${root}/requests/${id}/supplier-inquiries`, { method: 'POST', ...json(data) }),
  updateInquiry: (id, data) => apiClient(`${root}/supplier-inquiries/${id}`, { method: 'PUT', ...json(data) }),
  send: (id) => apiClient(`${root}/supplier-inquiries/${id}/send`, { method: 'POST' }),
  syncEmail: (id) => apiClient(`${root}/supplier-inquiries/${id}/sync-email`, { method: 'POST' }),
  complete: (id) => apiClient(`${root}/supplier-inquiries/${id}/complete`, { method: 'POST' }),
  select: (requestId, itemId, inquiryItemId) => apiClient(`${root}/requests/${requestId}/items/${itemId}/select-result`, { method: 'POST', ...json({ inquiry_item_id: inquiryItemId }) }),
};
