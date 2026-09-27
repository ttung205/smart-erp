import { ref, computed } from 'vue';
import { inventoryApi } from '../api/inventory.api';

// Shared state across all sales sub-pages
export const quotations = ref([
  {
    id: 'QUOT-2026-1001',
    quotation_number: 'QUOT-2026-1001',
    customer_id: 1,
    customer_name: 'Tập đoàn Công nghệ FPT',
    quotation_date: '2026-09-25',
    valid_until: '2026-10-15',
    status: 'NEGOTIATING',
    notes: 'Báo giá gói thiết bị máy trạm đồ họa và phụ kiện văn phòng Q4/2026.',
    total_amount: 324500000,
    items: [
      { id: 1, product_id: 1, product_sku: 'LAP-DELL-5520', product_name: 'Dell Precision 5520 Workstation', quantity: 8, unit_price: 26500000, discount_percent: 5, won: true, won_quantity: 8 },
      { id: 2, product_id: 2, product_sku: 'MON-DELL-U2723', product_name: 'Màn hình Dell UltraSharp U2723QE 4K', quantity: 8, unit_price: 12000000, discount_percent: 0, won: true, won_quantity: 8 },
      { id: 3, product_id: 3, product_sku: 'DOCK-WD19S', product_name: 'Docking Station Dell WD19S 130W', quantity: 8, unit_price: 3500000, discount_percent: 0, won: false, won_quantity: 8 }
    ]
  },
  {
    id: 'QUOT-2026-1002',
    quotation_number: 'QUOT-2026-1002',
    customer_id: 2,
    customer_name: 'Tập đoàn Công nghiệp - Viễn thông Quân đội (Viettel)',
    quotation_date: '2026-09-26',
    valid_until: '2026-10-20',
    status: 'DRAFT',
    notes: 'Dự thảo báo giá thiết bị số hóa phòng giao dịch.',
    total_amount: 145000000,
    items: [
      { id: 1, product_id: 1, product_sku: 'LAP-DELL-5520', product_name: 'Dell Precision 5520 Workstation', quantity: 5, unit_price: 27000000, discount_percent: 0, won: false, won_quantity: 5 },
      { id: 2, product_id: 3, product_sku: 'DOCK-WD19S', product_name: 'Docking Station Dell WD19S 130W', quantity: 5, unit_price: 2000000, discount_percent: 0, won: false, won_quantity: 5 }
    ]
  }
]);

export const orders = ref([
  {
    id: 'SO-2026-0891',
    quotation_id: 'QUOT-2026-0988',
    customer_id: 1,
    customer_name: 'Tập đoàn Công nghệ FPT',
    order_date: '2026-09-26',
    status: 'CONFIRMED',
    total_amount: 132500000,
    items: [
      { product_id: 1, product_sku: 'LAP-DELL-5520', product_name: 'Dell Precision 5520 Workstation', quantity: 5, unit_price: 26500000 }
    ]
  }
]);

export const deliveryRequests = ref([
  {
    id: 'DR-2026-0041',
    order_id: 'SO-2026-0891',
    customer_id: 1,
    customer_name: 'Tập đoàn Công nghệ FPT',
    warehouse_id: 1,
    warehouse_name: 'Kho Tổng Miền Bắc (Hà Nội)',
    request_date: '2026-09-26',
    expected_delivery_date: '2026-09-28',
    contact_phone: '024 7300 7300',
    shipping_address: 'Tòa nhà FPT, Phố Duy Tân, Cầu Giấy, Hà Nội',
    status: 'APPROVED',
    items: [
      { product_id: 1, product_sku: 'LAP-DELL-5520', product_name: 'Dell Precision 5520 Workstation', quantity: 5, unit_price: 26500000 }
    ]
  }
]);

export const invoices = ref([
  {
    id: 'INV-2026-0030',
    invoice_number: 'INV-2026-0030',
    delivery_request_id: 'DR-2026-0039',
    order_id: 'SO-2026-0885',
    customer_name: 'Tập đoàn Công nghệ CMC',
    warehouse_id: 1,
    warehouse_name: 'Kho Tổng Miền Bắc (Hà Nội)',
    invoice_date: '2026-09-25',
    payment_terms: 'Công nợ 30 ngày',
    total_amount: 82500000,
    warehouse_status: 'DELIVERED', // Đã giao hàng thành công -> Sẵn sàng cho Kế toán ghi sổ
    accounting_status: 'UNPOSTED', // Chưa ghi sổ
    posted_at: null,
    posted_by: null,
    items: [
      { product_id: 1, product_sku: 'LAP-DELL-5520', product_name: 'Dell Precision 5520 Workstation', quantity: 3, unit_price: 27500000 }
    ]
  }
]);

// ----------------------------------------------------
// ACTIONS
// ----------------------------------------------------

export function addQuotation(newQuotation) {
  quotations.value.unshift(newQuotation);
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

  // Cập nhật trạng thái báo giá
  const foundQ = quotations.value.find((q) => q.quotation_number === soPayload.quotation_id);
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

  // Gọi API trừ kho sang Inventory Service (:8004)
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

  // Khóa chứng từ
  invoice.accounting_status = 'POSTED';
  invoice.posted_at = new Date().toLocaleString('vi-VN');
  invoice.posted_by = currentUserName;
  invoice.accounting_note = note;

  // Cập nhật trạng thái đơn SO thành COMPLETED
  const relatedOrder = orders.value.find((o) => o.id === invoice.order_id);
  if (relatedOrder) {
    relatedOrder.status = 'COMPLETED';
  }

  return invoice;
}
