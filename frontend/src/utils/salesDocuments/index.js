import { loadCompanyConfig } from '../../config/companyConfig';
import { useWarehouseDeliveryNotePdf } from './WarehousePDF';
import { useHandoverMinutesPdf } from './HandoverMinutesPDF';
import { useVatInvoicePdf } from './VATInvoicePDF';
import { useWarrantyCardPdf } from './WarrantyCardPDF';

export const salesDocumentTabs = [
  { key: 'warehouse', label: 'Phiếu xuất kho' },
  { key: 'handover', label: 'Biên bản bàn giao' },
  { key: 'vat', label: 'Hóa đơn GTGT mẫu' },
  { key: 'warranty', label: 'Phiếu bảo hành' },
];

const first = (...values) => values.find((value) => value !== undefined && value !== null && value !== '') || '';
const dateParts = (value) => {
  const date = value ? new Date(value) : new Date();
  const valid = Number.isNaN(date.getTime()) ? new Date() : date;
  return { day: String(valid.getDate()), month: String(valid.getMonth() + 1), year: String(valid.getFullYear()) };
};

export function mapSalesInvoiceToSample(record) {
  const order = record.deliveryRequest?.order || record.delivery_request?.order || {};
  const customer = order.customer || {};
  const number = first(record.invoice_number, `INV-${record.id}`);
  const customerName = first(customer.name, record.customer_name, record.deliveryRequest?.customer_name);
  const customerAddress = first(customer.address, record.customer_address, record.shipping_address);
  const deliveryAddress = first(record.shipping_address, record.deliveryRequest?.shipping_address, customerAddress);
  const orderNumber = first(record.customer_order_number, record.order_number, order.customer_order_number, order.order_number);
  const items = (record.items || []).map((item) => ({
    internal_code: first(item.sku, item.product?.sku),
    material_code: first(item.sku, item.product?.sku),
    item_description: first(item.product_name, item.designation, item.description, item.orderItem?.product_name, item.sku),
    designation: first(item.designation, item.product_name),
    unit: first(item.unit, 'Cái'),
    quantity_shipped: Number(first(item.quantity, item.requested_quantity, 0)),
    selling_price: Number(first(item.unit_price, item.selling_price, 0)),
    vat: Number(first(item.vat_percent, item.orderItem?.vat_percent, 0)),
    po_number: orderNumber,
    explanation: first(item.notes, item.description),
    origin: first(item.com, item.origin, item.brand),
  }));
  const sampleInvoice = {
    id: record.id,
    invoice_number: number,
    invoice_date: record.invoice_date,
    currency: record.currency || 'VND',
    exchange_rate: Number(record.exchange_rate) || 1,
    notes: record.notes || '',
    customer: { name: customerName, address: customerAddress, tax_code: first(customer.tax_code, record.customer_tax_code), phone: first(customer.phone, record.contact_phone) },
  };
  const shipment = {
    shipment_code: number,
    shipped_date: record.invoice_date,
    notes: record.notes || '',
    items,
    sale: {
      po_number: orderNumber,
      sale_number: orderNumber,
      buyer: first(order.buyer_name, record.buyer_name),
      tax_address: customerAddress,
      delivery_address: deliveryAddress,
      customer: sampleInvoice.customer,
      saler: { name: first(order.creator?.name, record.created_by) },
      currency: sampleInvoice.currency,
      exchange_rate: sampleInvoice.exchange_rate,
    },
  };
  const warranty = {
    customerName,
    address: deliveryAddress,
    items: items.map((item) => ({ designation: item.item_description, quantity: item.quantity_shipped, origin: item.origin })),
    deliveryDate: record.invoice_date ? new Date(record.invoice_date).toLocaleDateString('vi-VN') : '',
    warrantyPeriod: 'Theo chính sách bảo hành của từng sản phẩm',
    warrantyNumber: number,
    ...dateParts(record.invoice_date),
  };
  return { invoice: sampleInvoice, shipment, warranty, number, orderNumber };
}

export async function generateSalesDocument(key, record) {
  const company = await loadCompanyConfig({ refresh: true });
  if (!company.name) throw new Error('Chưa thiết lập tên công ty. Vui lòng cập nhật tại trang Công ty.');
  const data = mapSalesInvoiceToSample(record);
  const options = {};
  switch (key) {
    case 'warehouse': return useWarehouseDeliveryNotePdf().generateWarehouseNote(data.invoice, data.shipment, options);
    case 'handover': return useHandoverMinutesPdf().generateHandoverMinutes(data.invoice, data.shipment, options);
    case 'vat': return useVatInvoicePdf().generateVatInvoice(data.invoice, data.shipment, options);
    case 'warranty': return { blob: await useWarrantyCardPdf().generateWarrantyCardPdf(data.warranty, { ...options, download: false }), fileName: `Phieu_bao_hanh_${data.number}.pdf` };
    default: throw new Error('Loại chứng từ không hợp lệ');
  }
}
