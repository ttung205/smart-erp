import { ref, computed } from 'vue';
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

// Danh mục sản phẩm mẫu để tra cứu (Catalog Products)
export const catalogProducts = ref([
  {
    sku: '23236 CC/W33-C&U',
    designation: '23236 CC/W33',
    brand: 'C&U',
    description: 'Vòng bi tang trống tự lựa 23236 CC/W33',
    unit: 'Cái',
    com: 'Trung Quốc',
    stock: 45,
    gross_price: 18500000,
    lead_time: 'Có sẵn',
    vat_percent: 10
  },
  {
    sku: '23236 CC/W33-SKF',
    designation: '23236 CC/W33',
    brand: 'SKF',
    description: 'Vòng bi chính xác cao SKF Explorer 23236 CC/W33',
    unit: 'Cái',
    com: 'Thụy Điển',
    stock: 12,
    gross_price: 32000000,
    lead_time: '1-2 tuần',
    vat_percent: 10
  },
  {
    sku: '32236-C&U',
    designation: '32236',
    brand: 'C&U',
    description: 'Vòng bi côn 32236 C&U tải trọng nặng',
    unit: 'Cái',
    com: 'Trung Quốc',
    stock: 28,
    gross_price: 14200000,
    lead_time: 'Có sẵn',
    vat_percent: 10
  },
  {
    sku: '32236-SKF',
    designation: '32236',
    brand: 'SKF',
    description: 'Vòng bi côn SKF 32236 tiêu chuẩn quốc tế',
    unit: 'Cái',
    com: 'Đức',
    stock: 8,
    gross_price: 24500000,
    lead_time: '2-3 ngày',
    vat_percent: 10
  },
  {
    sku: '22328 CC/C3W33-C&U',
    designation: '22328 CC/C3W33',
    brand: 'C&U',
    description: 'Bearing 22328 CC/C3W33 C&U',
    unit: 'Cái',
    com: 'Trung Quốc',
    stock: 60,
    gross_price: 12800000,
    lead_time: 'Có sẵn',
    vat_percent: 10
  },
  {
    sku: '22328 CC/C3W33-SKF',
    designation: '22328 CC/C3W33',
    brand: 'SKF',
    description: 'Vòng bi 22328 CC/C3W33 SKF',
    unit: 'Cái',
    com: 'Thụy Điển',
    stock: 15,
    gross_price: 26800000,
    lead_time: '3-5 ngày',
    vat_percent: 10
  },
  {
    sku: 'NU 2232 EML/C3-C&U',
    designation: 'NU 2232 EML/C3',
    brand: 'C&U',
    description: 'Vòng bi trụ NU 2232 EML/C3',
    unit: 'Cái',
    com: 'Trung Quốc',
    stock: 35,
    gross_price: 16500000,
    lead_time: 'Có sẵn',
    vat_percent: 10
  }
]);

// Hàm sinh số báo giá quy ước: QT-[mã khách hàng]-[ngày tạo dạng ddmmyy]
export function generateQuotationNumber(customerCode = 'KH01', date = new Date()) {
  const d = String(date.getDate()).padStart(2, '0');
  const m = String(date.getMonth() + 1).padStart(2, '0');
  const y = String(date.getFullYear()).slice(-2);
  const dateStr = `${d}${m}${y}`;
  return `QT-${customerCode}-${dateStr}`;
}

// Danh sách Báo Giá ban đầu (Quotations)
export const quotations = ref([
  {
    id: 'QT-EA-02592-260926',
    quotation_number: 'QT-EA-02592-260926',
    customer_id: 1,
    customer_code: 'EA-02592',
    customer_name: 'CÔNG TY TNHH LONG SƠN - NHÀ MÁY XI MĂNG',
    contact_id: 1,
    contact_name: 'Mr. Hợp (Mua hàng)',
    quotation_date: '26/09/2026',
    created_at: '26/09/2026 14:11',
    created_by: 'admin',
    assigned_to: 'admin',
    currency: 'VND',
    exchange_rate: 1,
    status: 'DRAFT', // DRAFT: Nháp, PENDING_APPROVAL: Chờ duyệt, APPROVED: Đã duyệt, CONVERTED: Đã tạo đơn bán
    notes: 'Báo giá vật tư vòng bi đại tu dây chuyền nghiền xi măng Nhà máy Long Sơn.',
    total_amount: 185838732,
    subtotal: 172072900,
    vat_total: 13765832,
    items: [
      {
        id: 1,
        sku: '23236 CC/W33-C&U',
        designation: '23236 CC/W33',
        brand: 'C&U',
        description: 'Vòng bi 23236 CC/W33',
        com: 'Trung Quốc',
        stock: 45,
        unit: 'Cái',
        quantity: 1,
        gross_price: 18500000,
        discount_percent: 15,
        selling_price: 15725000,
        vat_percent: 10,
        lead_time: 'Có sẵn',
        subtotal: 15725000,
        won: true,
        won_quantity: 1
      },
      {
        id: 2,
        sku: '23236 CC/W33-SKF',
        designation: '23236 CC/W33',
        brand: 'SKF',
        description: 'Vòng bi 23236 CC/W33',
        com: 'Thụy Điển',
        stock: 12,
        unit: 'pcs',
        quantity: 1,
        gross_price: 32000000,
        discount_percent: 10,
        selling_price: 28800000,
        vat_percent: 10,
        lead_time: '1-2 tuần',
        subtotal: 28800000,
        won: true,
        won_quantity: 1
      },
      {
        id: 3,
        sku: '32236-C&U',
        designation: '32236',
        brand: 'C&U',
        description: 'Vòng bi 32236',
        com: 'Trung Quốc',
        stock: 28,
        unit: 'Cái',
        quantity: 1,
        gross_price: 14200000,
        discount_percent: 20,
        selling_price: 11360000,
        vat_percent: 10,
        lead_time: 'Có sẵn',
        subtotal: 11360000,
        won: false,
        won_quantity: 1
      },
      {
        id: 4,
        sku: '32236-SKF',
        designation: '32236',
        brand: 'SKF',
        description: 'Vòng bi 32236',
        com: 'Đức',
        stock: 8,
        unit: 'pcs',
        quantity: 1,
        gross_price: 24500000,
        discount_percent: 10,
        selling_price: 22050000,
        vat_percent: 10,
        lead_time: 'Có sẵn',
        subtotal: 22050000,
        won: true,
        won_quantity: 1
      },
      {
        id: 5,
        sku: '22328 CC/C3W33-C&U',
        designation: '22328 CC/C3W33',
        brand: 'C&U',
        description: 'Bearing 22328 CC/C3W33',
        com: 'Trung Quốc',
        stock: 60,
        unit: 'Cái',
        quantity: 1,
        gross_price: 12800000,
        discount_percent: 15,
        selling_price: 10880000,
        vat_percent: 10,
        lead_time: 'Có sẵn',
        subtotal: 10880000,
        won: false,
        won_quantity: 1
      },
      {
        id: 6,
        sku: '22328 CC/C3W33-SKF',
        designation: '22328 CC/C3W33',
        brand: 'SKF',
        description: 'Vòng bi 22328 CC/C3W33',
        com: 'Thụy Điển',
        stock: 15,
        unit: 'pcs',
        quantity: 1,
        gross_price: 26800000,
        discount_percent: 12,
        selling_price: 23584000,
        vat_percent: 10,
        lead_time: '3-5 ngày',
        subtotal: 23584000,
        won: true,
        won_quantity: 1
      },
      {
        id: 7,
        sku: 'NU 2232 EML/C3-C&U',
        designation: 'NU 2232 EML/C3',
        brand: 'C&U',
        description: 'Cylindrical roller bearings NU 2232',
        com: 'Trung Quốc',
        stock: 35,
        unit: 'pcs',
        quantity: 1,
        gross_price: 16500000,
        discount_percent: 10,
        selling_price: 14850000,
        vat_percent: 10,
        lead_time: 'Có sẵn',
        subtotal: 14850000,
        won: false,
        won_quantity: 1
      }
    ]
  },
  {
    id: 'QT-FPT-01-280926',
    quotation_number: 'QT-FPT-01-280926',
    customer_id: 2,
    customer_code: 'FPT-01',
    customer_name: 'Tập đoàn Công nghệ FPT',
    contact_id: 3,
    contact_name: 'Nguyễn Văn Nam (Trưởng phòng Mua hàng)',
    quotation_date: '28/09/2026',
    created_at: '28/09/2026 08:30',
    created_by: 'admin',
    assigned_to: 'admin',
    currency: 'VND',
    exchange_rate: 1,
    status: 'APPROVED',
    notes: 'Báo giá máy trạm và linh kiện máy chủ quý IV.',
    total_amount: 145200000,
    subtotal: 132000000,
    vat_total: 13200000,
    items: [
      {
        id: 1,
        sku: '23236 CC/W33-SKF',
        designation: '23236 CC/W33',
        brand: 'SKF',
        description: 'Vòng bi chính xác cao SKF Explorer',
        com: 'Thụy Điển',
        stock: 12,
        unit: 'Cái',
        quantity: 4,
        gross_price: 33000000,
        discount_percent: 0,
        selling_price: 33000000,
        vat_percent: 10,
        lead_time: 'Có sẵn',
        subtotal: 132000000,
        won: true,
        won_quantity: 4
      }
    ]
  }
]);

// Danh sách Đơn Bán Hàng (SO)
export const orders = ref([
  {
    id: 'SO-2026-0891',
    quotation_id: 'QT-EA-02592-260926',
    customer_id: 1,
    customer_name: 'CÔNG TY TNHH LONG SƠN - NHÀ MÁY XI MĂNG',
    order_date: '2026-09-27',
    status: 'CONFIRMED',
    total_amount: 88204000,
    items: [
      { product_id: 1, product_sku: '23236 CC/W33-C&U', product_name: 'Vòng bi 23236 CC/W33', quantity: 1, unit_price: 15725000 },
      { product_id: 2, product_sku: '23236 CC/W33-SKF', product_name: 'Vòng bi 23236 CC/W33 SKF', quantity: 1, unit_price: 28800000 }
    ]
  }
]);

// Danh sách Yêu Cầu Giao Hàng (DR)
export const deliveryRequests = ref([
  {
    id: 'DR-2026-0041',
    order_id: 'SO-2026-0891',
    customer_id: 1,
    customer_name: 'CÔNG TY TNHH LONG SƠN - NHÀ MÁY XI MĂNG',
    warehouse_id: 1,
    warehouse_name: 'Kho Tổng Miền Bắc (Hà Nội)',
    request_date: '2026-09-27',
    expected_delivery_date: '2026-09-29',
    contact_phone: '0912 345 678',
    shipping_address: 'Khu công nghiệp Bỉm Sơn, Thanh Hóa',
    status: 'APPROVED',
    items: [
      { product_id: 1, product_sku: '23236 CC/W33-C&U', product_name: 'Vòng bi 23236 CC/W33', quantity: 1, unit_price: 15725000 }
    ]
  }
]);

// Danh sách Chứng Từ Bán Hàng (INV)
export const invoices = ref([
  {
    id: 'INV-2026-0030',
    invoice_number: 'INV-2026-0030',
    delivery_request_id: 'DR-2026-0041',
    order_id: 'SO-2026-0891',
    customer_name: 'CÔNG TY TNHH LONG SƠN - NHÀ MÁY XI MĂNG',
    warehouse_id: 1,
    warehouse_name: 'Kho Tổng Miền Bắc (Hà Nội)',
    invoice_date: '2026-09-27',
    payment_terms: 'Công nợ 30 ngày',
    total_amount: 88204000,
    warehouse_status: 'DELIVERED', // Đã giao hàng thành công -> Sẵn sàng cho Kế toán ghi sổ
    accounting_status: 'UNPOSTED', // Chưa ghi sổ
    posted_at: null,
    posted_by: null,
    items: [
      { product_id: 1, product_sku: '23236 CC/W33-C&U', product_name: 'Vòng bi 23236 CC/W33', quantity: 1, unit_price: 15725000 }
    ]
  }
]);

// ----------------------------------------------------
// ACTIONS
// ----------------------------------------------------

export function getQuotationById(id) {
  return quotations.value.find((q) => q.id === id || q.quotation_number === id);
}

export function saveQuotation(quotationData) {
  const index = quotations.value.findIndex((q) => q.id === quotationData.id || q.quotation_number === quotationData.quotation_number);
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
  const foundQ = quotations.value.find((q) => q.quotation_number === soPayload.quotation_id || q.id === soPayload.quotation_id);
  if (foundQ) {
    foundQ.status = 'CONVERTED';
  }

  return newSO;
}

export function addDeliveryRequest(newDr) {
  deliveryRequests.value.unshift(newDr);

  const foundOrder = orders.value.find((o) => o.id === newDr.order_id);
  if (foundOrder) {
    foundOrder.status = 'DELIVERING';
  }
  return newDr;
}

export function addInvoice(newInv) {
  invoices.value.unshift(newInv);

  const foundDr = deliveryRequests.value.find((d) => d.id === newInv.delivery_request_id);
  if (foundDr) {
    foundDr.status = 'INVOICED';
  }
  return newInv;
}

export function updateWarehouseStatus(invoiceId, newStatus) {
  const found = invoices.value.find((i) => i.id === invoiceId || i.invoice_number === invoiceId);
  if (found) {
    found.warehouse_status = newStatus;
  }
}

export async function postAccountingInvoice(invoiceId, note = '', currentUserName = 'Kế toán trưởng') {
  const invoice = invoices.value.find((i) => i.id === invoiceId || i.invoice_number === invoiceId);
  if (!invoice) throw new Error('Không tìm thấy chứng từ!');

  for (const item of invoice.items) {
    try {
      await inventoryApi.issueStock({
        referenceDoc: invoice.invoice_number,
        warehouseId: invoice.warehouse_id || 1,
        productId: item.product_id || 1,
        quantity: item.quantity,
        orderId: invoice.order_id
      });
    } catch (err) {
      console.warn('Inventory service issueStock notice:', err);
    }
  }

  invoice.accounting_status = 'POSTED';
  invoice.posted_at = new Date().toLocaleString('vi-VN');
  invoice.posted_by = currentUserName;
  invoice.accounting_note = note;

  const relatedOrder = orders.value.find((o) => o.id === invoice.order_id);
  if (relatedOrder) {
    relatedOrder.status = 'COMPLETED';
  }

  return invoice;
}
