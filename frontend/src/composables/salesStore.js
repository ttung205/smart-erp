import { ref } from 'vue';
import { catalogApi } from '../api/catalog.api';
import { salesApi } from '../api/sales.api';

// Danh sách khách hàng và các liên hệ (Mỗi khách hàng có nhiều người liên hệ)
export const customers = ref([
  {
    id: 1,
    code: 'EA-02592',
    name: 'CÔNG TY TNHH LONG SƠN - NHÀ MÁY XI MĂNG',
    contacts: [
      { id: 1, name: 'Mr. Hợp (Mua hàng)', phone: '0912 345 678', email: 'hop.procurement@longson.com' },
      { id: 2, name: 'Mr. Tuấn (Kỹ thuật)', phone: '0988 765 432', email: 'tuan.tech@longson.com' }
    ]
  },
  {
    id: 2,
    code: 'FPT-01',
    name: 'Tập đoàn Công nghệ FPT',
    contacts: [
      { id: 3, name: 'Nguyễn Văn Nam (Trưởng phòng Mua hàng)', phone: '024 7300 7300', email: 'namnv@fpt.com.vn' },
      { id: 4, name: 'Trần Thị Thu (Kế toán)', phone: '0915 223 344', email: 'thutt@fpt.com.vn' }
    ]
  },
  {
    id: 3,
    code: 'VTL-01',
    name: 'Tập đoàn Công nghiệp - Viễn thông Quân đội (Viettel)',
    contacts: [
      { id: 5, name: 'Lê Hoàng Minh (Phụ trách vật tư)', phone: '024 6255 6789', email: 'minhlh@viettel.com.vn' }
    ]
  }
]);

// Danh mục sản phẩm chuẩn lấy từ Catalog Service (Không hardcode data mẫu)
export const catalogProducts = ref([]);
export const isCatalogLoading = ref(false);

export async function loadCatalogProducts() {
  isCatalogLoading.value = true;
  try {
    const res = await catalogApi.getProducts();
    if (res && res.data) {
      catalogProducts.value = res.data.map((p) => ({
        id: p.id,
        sku: p.sku,
        designation: p.designation || '',
        brand: p.brand || '',
        category: p.category || '',
        description: p.description || p.item_description || p.name || '',
        unit: p.unit || 'Cái',
        com: p.com || 'Việt Nam',
        stock: p.stock ?? (p.min_stock || 20),
        gross_price: Number(p.gross_price) || 0,
        lead_time: 'Có sẵn',
        vat_percent: 10,
        spec: p.spec || ''
      }));
    }
  } catch (err) {
    console.warn('loadCatalogProducts: Không thể kết nối Catalog Service API', err);
  } finally {
    isCatalogLoading.value = false;
  }
  return catalogProducts.value;
}

// Tự động tải danh mục sản phẩm từ catalog-service
loadCatalogProducts();

// Hàm sinh số báo giá quy ước: QT-[mã khách hàng]-[ngày tạo dạng ddmmyy]
export function generateQuotationNumber(customerCode = 'KH01', date = new Date()) {
  const d = String(date.getDate()).padStart(2, '0');
  const m = String(date.getMonth() + 1).padStart(2, '0');
  const y = String(date.getFullYear()).slice(-2);
  const dateStr = `${d}${m}${y}`;
  return `QT-${customerCode}-${dateStr}`;
}

// Danh sách Báo Giá (đồng bộ với backend sales-service)
export const quotations = ref([]);
export const isQuotationsLoading = ref(false);

export async function fetchQuotations() {
  isQuotationsLoading.value = true;
  try {
    const res = await salesApi.getQuotations();
    if (res && res.data) {
      quotations.value = res.data;
    }
  } catch (err) {
    console.warn('fetchQuotations: Không thể kết nối Sales Service API', err);
  } finally {
    isQuotationsLoading.value = false;
  }
  return quotations.value;
}

// Tự động tải danh sách báo giá từ backend
fetchQuotations();

// Danh sách Đơn Bán Hàng (SO)
export const orders = ref([]);
export async function fetchOrders() {
  try {
    const res = await salesApi.getOrders();
    if (res && res.data) orders.value = res.data;
  } catch (e) {
    console.warn('fetchOrders error', e);
  }
}
fetchOrders();

// Danh sách Yêu Cầu Giao Hàng (DR)
export const deliveryRequests = ref([]);
export async function fetchDeliveries() {
  try {
    const res = await salesApi.getDeliveries();
    if (res && res.data) deliveryRequests.value = res.data;
  } catch (e) {
    console.warn('fetchDeliveries error', e);
  }
}
fetchDeliveries();

// Danh sách Chứng Từ Bán Hàng (INV)
export const invoices = ref([]);
export async function fetchInvoices() {
  try {
    const res = await salesApi.getInvoices();
    if (res && res.data) invoices.value = res.data;
  } catch (e) {
    console.warn('fetchInvoices error', e);
  }
}
fetchInvoices();

// ----------------------------------------------------
// ACTIONS
// ----------------------------------------------------

export function getQuotationById(id) {
  return quotations.value.find((q) => String(q.id) === String(id) || q.quotation_number === id);
}

export async function saveQuotation(quotationData) {
  try {
    if (quotationData.id && typeof quotationData.id === 'number') {
      const res = await salesApi.updateQuotation(quotationData.id, quotationData);
      await fetchQuotations();
      return res.data;
    } else {
      const res = await salesApi.createQuotation(quotationData);
      await fetchQuotations();
      return res.data;
    }
  } catch (err) {
    console.warn('saveQuotation API failed, saving to local state fallback', err);
    const index = quotations.value.findIndex(
      (q) => q.id === quotationData.id || q.quotation_number === quotationData.quotation_number
    );
    if (index >= 0) {
      quotations.value[index] = { ...quotations.value[index], ...quotationData };
      return quotations.value[index];
    } else {
      quotations.value.unshift(quotationData);
      return quotationData;
    }
  }
}

export async function submitForApproval(quotationId) {
  try {
    const res = await salesApi.submitApproval(quotationId);
    await fetchQuotations();
    return res.data;
  } catch (err) {
    console.warn('submitForApproval API failed, fallback local state', err);
    const q = quotations.value.find((item) => String(item.id) === String(quotationId) || item.quotation_number === quotationId);
    if (q) q.status = 'PENDING_APPROVAL';
    return q;
  }
}

export async function approveQuotation(quotationId, approverName = 'admin') {
  try {
    const res = await salesApi.approveQuotation(quotationId, approverName);
    await fetchQuotations();
    return res.data;
  } catch (err) {
    console.warn('approveQuotation API failed, fallback local state', err);
    const q = quotations.value.find((item) => String(item.id) === String(quotationId) || item.quotation_number === quotationId);
    if (q) {
      q.status = 'APPROVED';
      q.approved_by = approverName;
      q.approved_at = new Date().toLocaleString('vi-VN');
    }
    return q;
  }
}

export async function createSOFromQuotation(soPayload) {
  try {
    const qId = soPayload.quotation_id;
    const res = await salesApi.createSOFromQuotation(qId, soPayload);
    await fetchQuotations();
    await fetchOrders();
    return res.data;
  } catch (err) {
    console.warn('createSOFromQuotation API failed, fallback local state', err);
    const randNum = Math.floor(1000 + Math.random() * 9000);
    const newSO = {
      id: `SO-2026-${randNum}`,
      quotation_id: soPayload.quotation_id,
      customer_id: soPayload.customer_id,
      customer_name: soPayload.customer_name,
      order_date: new Date().toISOString().split('T')[0],
      status: 'CONFIRMED',
      total_amount: soPayload.total_amount,
      items: soPayload.items
    };
    orders.value.unshift(newSO);
    const foundQ = quotations.value.find(
      (q) => q.quotation_number === soPayload.quotation_id || String(q.id) === String(soPayload.quotation_id)
    );
    if (foundQ) foundQ.status = 'CONVERTED';
    return newSO;
  }
}

export async function createDeliveryRequest(orderId) {
  const order = orders.value.find((o) => o.id === orderId || o.order_number === orderId);
  if (!order) return null;

  const payload = {
    order_id: order.id || order.order_number,
    customer_name: order.customer_name,
    warehouse_name: 'Kho Tổng Miền Bắc (Hà Nội)',
    contact_phone: '0912 345 678',
    shipping_address: 'KCN Bỉm Sơn, Thanh Hóa',
    items: order.items ? order.items.map((it) => ({ ...it })) : []
  };

  try {
    const res = await salesApi.createDelivery(payload);
    await fetchDeliveries();
    return res.data;
  } catch (err) {
    console.warn('createDeliveryRequest API failed, fallback local state', err);
    const randNum = Math.floor(100 + Math.random() * 900);
    const newDR = {
      id: `DR-2026-${randNum}`,
      request_number: `DR-2026-${randNum}`,
      order_number: order.id,
      customer_name: order.customer_name,
      warehouse_name: 'Kho Tổng Miền Bắc (Hà Nội)',
      request_date: new Date().toISOString().split('T')[0],
      status: 'PENDING',
      items: payload.items
    };
    deliveryRequests.value.unshift(newDR);
    return newDR;
  }
}

export async function updateDeliveryStep(drId, nextStep) {
  try {
    const res = await salesApi.updateDeliveryStatus(drId, nextStep);
    await fetchDeliveries();
    await fetchInvoices();
    return res.data;
  } catch (err) {
    console.warn('updateDeliveryStep API failed, fallback local state', err);
    const dr = deliveryRequests.value.find((d) => d.id === drId || d.request_number === drId);
    if (!dr) return null;
    dr.status = nextStep;
    return dr;
  }
}

export async function postToAccounting(invId, accountantName = 'kt_truong') {
  try {
    const res = await salesApi.postAccounting(invId, accountantName);
    await fetchInvoices();
    return res.data;
  } catch (err) {
    console.warn('postToAccounting API failed, fallback local state', err);
    const inv = invoices.value.find((i) => i.id === invId || i.invoice_number === invId);
    if (!inv) return null;
    inv.accounting_status = 'POSTED';
    inv.posted_at = new Date().toLocaleString('vi-VN');
    inv.posted_by = accountantName;
    return inv;
  }
}

// Backward-compatibility exports
export function addDeliveryRequest(dr) {
  deliveryRequests.value.unshift(dr);
  return dr;
}

export function addInvoice(inv) {
  invoices.value.unshift(inv);
  return inv;
}

export async function updateWarehouseStatus(invId, status) {
  try {
    await salesApi.updateInvoiceWarehouseStatus(invId, status);
    await fetchInvoices();
  } catch {
    const inv = invoices.value.find((i) => i.id === invId || i.invoice_number === invId);
    if (inv) inv.warehouse_status = status;
  }
}

export function postAccountingInvoice(invId, accountantName = 'kt_truong') {
  return postToAccounting(invId, accountantName);
}
