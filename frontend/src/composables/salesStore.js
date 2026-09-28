import { ref, computed } from 'vue';
import { catalogApi } from '../api/catalog.api';
import { inventoryApi } from '../api/inventory.api';

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

// Tự động gọi tải danh mục sản phẩm từ catalog-service
loadCatalogProducts();

// Hàm sinh số báo giá quy ước: QT-[mã khách hàng]-[ngày tạo dạng ddmmyy]
export function generateQuotationNumber(customerCode = 'KH01', date = new Date()) {
  const d = String(date.getDate()).padStart(2, '0');
  const m = String(date.getMonth() + 1).padStart(2, '0');
  const y = String(date.getFullYear()).slice(-2);
  const dateStr = `${d}${m}${y}`;
  return `QT-${customerCode}-${dateStr}`;
}

// Danh sách Báo Giá ban đầu (Đã xóa data mẫu hardcode)
export const quotations = ref([]);

// Danh sách Đơn Bán Hàng (SO) (Đã xóa data mẫu)
export const orders = ref([]);

// Danh sách Yêu Cầu Giao Hàng (DR) (Đã xóa data mẫu)
export const deliveryRequests = ref([]);

// Danh sách Chứng Từ Bán Hàng (INV) (Đã xóa data mẫu)
export const invoices = ref([]);

// ----------------------------------------------------
// ACTIONS
// ----------------------------------------------------

export function getQuotationById(id) {
  return quotations.value.find((q) => q.id === id || q.quotation_number === id);
}

export function saveQuotation(quotationData) {
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

export function submitForApproval(quotationId) {
  const q = quotations.value.find((item) => item.id === quotationId || item.quotation_number === quotationId);
  if (q) {
    q.status = 'PENDING_APPROVAL';
  }
  return q;
}

export function approveQuotation(quotationId, approverName = 'admin') {
  const q = quotations.value.find((item) => item.id === quotationId || item.quotation_number === quotationId);
  if (q) {
    q.status = 'APPROVED';
    q.approved_by = approverName;
    q.approved_at = new Date().toLocaleString('vi-VN');
  }
  return q;
}

export function createSOFromQuotation(soPayload) {
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

  // Cập nhật trạng thái báo giá sang Đã tạo đơn bán (CONVERTED)
  const foundQ = quotations.value.find(
    (q) => q.quotation_number === soPayload.quotation_id || q.id === soPayload.quotation_id
  );
  if (foundQ) {
    foundQ.status = 'CONVERTED';
  }

  return newSO;
}

export function createDeliveryRequest(orderId) {
  const order = orders.value.find((o) => o.id === orderId);
  if (!order) return null;

  const randNum = Math.floor(100 + Math.random() * 900);
  const newDR = {
    id: `DR-2026-${randNum}`,
    order_id: order.id,
    customer_id: order.customer_id,
    customer_name: order.customer_name,
    warehouse_id: 1,
    warehouse_name: 'Kho Tổng Miền Bắc (Hà Nội)',
    request_date: new Date().toISOString().split('T')[0],
    expected_delivery_date: new Date(Date.now() + 2 * 86400000).toISOString().split('T')[0],
    contact_phone: '0912 345 678',
    shipping_address: 'KCN Bỉm Sơn, Thanh Hóa',
    status: 'PENDING',
    items: order.items.map((it) => ({ ...it }))
  };

  deliveryRequests.value.unshift(newDR);
  return newDR;
}

export function updateDeliveryStep(drId, nextStep) {
  const dr = deliveryRequests.value.find((d) => d.id === drId);
  if (!dr) return null;

  dr.status = nextStep;

  if (nextStep === 'APPROVED') {
    const invExists = invoices.value.find((i) => i.delivery_request_id === dr.id);
    if (!invExists) {
      const randNum = Math.floor(100 + Math.random() * 900);
      const newInv = {
        id: `INV-2026-${randNum}`,
        invoice_number: `INV-2026-${randNum}`,
        delivery_request_id: dr.id,
        order_id: dr.order_id,
        customer_name: dr.customer_name,
        warehouse_id: dr.warehouse_id,
        warehouse_name: dr.warehouse_name,
        invoice_date: new Date().toISOString().split('T')[0],
        payment_terms: 'Công nợ 30 ngày',
        total_amount: dr.items.reduce((sum, it) => sum + (it.unit_price || 0) * (it.quantity || 1), 0),
        warehouse_status: 'RECEIVED',
        accounting_status: 'UNPOSTED',
        posted_at: null,
        posted_by: null,
        items: dr.items.map((it) => ({ ...it }))
      };
      invoices.value.unshift(newInv);
    }
  }

  return dr;
}

export function postToAccounting(invId, accountantName = 'kt_truong') {
  const inv = invoices.value.find((i) => i.id === invId || i.invoice_number === invId);
  if (!inv) return null;

  inv.accounting_status = 'POSTED';
  inv.posted_at = new Date().toLocaleString('vi-VN');
  inv.posted_by = accountantName;

  return inv;
}

// Backward-compatibility exports for sales flow pages
export function addDeliveryRequest(dr) {
  deliveryRequests.value.unshift(dr);
  return dr;
}

export function addInvoice(inv) {
  invoices.value.unshift(inv);
  return inv;
}

export function updateWarehouseStatus(invId, status) {
  const inv = invoices.value.find((i) => i.id === invId || i.invoice_number === invId);
  if (inv) {
    inv.warehouse_status = status;
  }
  return inv;
}

export function postAccountingInvoice(invId, accountantName = 'kt_truong') {
  return postToAccounting(invId, accountantName);
}
