<template>
  <div class="space-y-4 pb-12">
    <!-- 1. Header Toolbar -->
    <div class="flex items-center justify-between bg-white border border-slate-200 rounded-[8px] px-5 py-3 shadow-sm">
      <div class="flex items-center gap-3">
        <Button label="Quay lại" icon="pi pi-arrow-left" size="small" severity="secondary" outlined
          class="!text-xs !py-1.5 !px-3 rounded-[6px] !border-slate-300 !text-slate-700 hover:!bg-slate-100"
          @click="handleBack" />

        <div class="flex flex-col">
          <h1 class="text-base font-bold text-slate-900 m-0 tracking-tight flex items-center gap-2">
            <span v-if="isEditMode">Chứng Từ Bán Hàng: {{ form.invoice_number }}</span>
            <span v-else>Lập Chứng Từ Bán Hàng Mới (Sales Invoice)</span>
            <Badge :severity="getWarehouseStatusMeta(form.warehouse_status).severity"
              :value="getWarehouseStatusMeta(form.warehouse_status).label" />
            <Badge v-if="form.accounting_status === 'POSTED'" severity="success" value="Đã ghi sổ" />
          </h1>
          <span v-if="isEditMode" class="text-xs text-slate-500">
            Xem chi tiết chứng từ bán hàng và theo dõi tiến độ điều phối
          </span>
          <span v-else class="text-xs text-slate-500">
            Lập chứng từ bán hàng từ yêu cầu giao hàng đã duyệt để gửi lệnh xuất kho
          </span>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="flex items-center gap-2.5">
        <!-- Nút Đồng bộ / Kích hoạt Giữ Chỗ Tồn Kho (Khi đang ở chế độ xem chứng từ) -->
        <Button v-if="isEditMode && canManageInvoice && form.accounting_status !== 'POSTED'" label="Đồng Bộ Giữ Chỗ Kho" icon="pi pi-lock"
          size="small" severity="help" outlined class="!text-xs !py-2 !px-3 rounded-[6px] !font-semibold"
          :loading="syncingReserve"
          v-tooltip.bottom="'Gửi lệnh giữ chỗ (Reserve) tới Inventory Service cho chứng từ này'"
          @click="handleSyncReserve" />

        <!-- Nút Gửi xuống kho (khi chưa gửi) -->
        <Button v-if="!isEditMode || (canManageInvoice && form.warehouse_status === 'PENDING_WAREHOUSE')" label="Gửi Xuống Kho"
          icon="pi pi-send" size="small"
          class="!bg-indigo-600 hover:!bg-indigo-700 !text-white !border-indigo-600 !text-xs !py-2 !px-3.5 rounded-[6px] !font-bold shadow-sm"
          :loading="saving" @click="saveInvoice(true)" />

        <!-- Nút Lưu Chứng Từ (Chờ gửi kho) -->
        <Button v-if="form.accounting_status !== 'POSTED'" label="Lưu Chứng Từ & Giữ Kho" icon="pi pi-save" size="small"
          severity="secondary"
          class="!bg-slate-700 hover:!bg-slate-800 !text-white !border-slate-700 !text-xs !py-2 !px-3.5 rounded-[6px] !font-semibold"
          :loading="saving" @click="saveInvoice(false)" />
      </div>
    </div>

    <!-- 2. Form Thông Tin Chứng Từ Bán Hàng (Bố cục 2 cột chuẩn ERP) -->
    <div class="flex flex-col lg:flex-row gap-4 items-stretch">
      <!-- Cột Trái: 63% (Số chứng từ, Ngày lập, Khách hàng, Đơn vị tiền tệ, Tỉ giá, Người tạo, Ghi chú) -->
      <div class="w-full lg:w-[63%] bg-white border border-slate-200 rounded-[8px] p-5 shadow-sm space-y-4">
        <div
          class="text-xs font-bold text-slate-800 uppercase tracking-wide flex items-center gap-1.5 border-b border-slate-100 pb-2">
          <i class="pi pi-file-edit text-indigo-600"></i>
          <span>Thông Tin Chứng Từ Bán Hàng</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-12 gap-3.5 items-start">
          <!-- Số chứng từ -->
          <div class="md:col-span-6 flex flex-col gap-1.5 min-w-0">
            <label class="text-xs font-bold text-slate-700 uppercase tracking-wide">
              Số chứng từ <span class="text-red-500">*</span>
            </label>
            <InputText v-model="form.invoice_number"
              class="w-full text-xs font-mono font-bold text-indigo-700 rounded-[6px]" size="small"
              placeholder="INV-2026-XXXX" />
          </div>

          <!-- Ngày chứng từ (Ngày tạo chứng từ) -->
          <div class="md:col-span-6 flex flex-col gap-1.5 min-w-0">
            <label class="text-xs font-bold text-slate-700 uppercase tracking-wide">
              Ngày chứng từ (Ngày tạo) <span class="text-red-500">*</span>
            </label>
            <InputText v-model="form.invoice_date" type="date" class="w-full text-xs font-mono rounded-[6px]"
              size="small" />
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-12 gap-3.5 items-start">
          <!-- Khách hàng (Lấy từ order_id) -->
          <div class="md:col-span-6 flex flex-col gap-1.5 min-w-0">
            <label class="text-xs font-bold text-slate-700 uppercase tracking-wide">
              Khách hàng (Theo Đơn hàng)
            </label>
            <InputText :value="form.customer_name" readonly disabled
              class="w-full text-xs bg-slate-50 font-semibold text-slate-800 rounded-[6px]" size="small" />
          </div>

          <!-- Người tạo chứng từ (Tự động lấy, không cho sửa) -->
          <div class="md:col-span-6 flex flex-col gap-1.5 min-w-0">
            <label class="text-xs font-bold text-slate-700 uppercase tracking-wide">
              Người tạo chứng từ
            </label>
            <InputText :value="form.created_by" readonly disabled
              class="w-full text-xs !bg-slate-50 font-semibold text-slate-700 rounded-[6px]" size="small" />
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-12 gap-3.5 items-start">
          <!-- Đơn vị tiền tệ (Lấy từ đơn hàng) -->
          <div class="md:col-span-6 flex flex-col gap-1.5 min-w-0">
            <label class="text-xs font-bold text-slate-700 uppercase tracking-wide">
              Đơn vị tiền tệ (Lấy từ Đơn)
            </label>
            <Select v-model="form.currency" :options="currencyOptions"
              class="w-full text-xs font-mono font-bold rounded-[6px]" size="small" />
          </div>

          <!-- Tỉ giá quy đổi (Nếu VND là 1 không cho sửa) -->
          <div class="md:col-span-6 flex flex-col gap-1.5 min-w-0">
            <div class="flex items-center justify-between">
              <label class="text-xs font-bold text-slate-700 uppercase tracking-wide">
                Tỉ giá quy đổi
              </label>
              <span v-if="form.currency !== 'VND'" class="text-[10px] text-indigo-600 font-semibold">Cho phép điều chỉnh</span>
            </div>
            <InputNumber v-model="form.exchange_rate" :min="0.0001" :maxFractionDigits="4"
              class="w-full text-xs font-mono rounded-[6px]"
              :class="{ '!bg-slate-50': form.currency === 'VND' }"
              :disabled="form.currency === 'VND'"
              size="small" placeholder="1.0" />
          </div>
        </div>

        <!-- Ghi chú chứng từ -->
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-bold text-slate-700 uppercase tracking-wide">
            Ghi chú chứng từ
          </label>
          <Textarea v-model="form.notes" rows="2" class="w-full text-xs rounded-[6px]"
            placeholder="Nhập ghi chú xuất hàng hoặc lưu ý cho phòng Logistics..." />
        </div>
      </div>

      <!-- Cột Phải: 37% (Tham chiếu Đơn Bán Hàng, Phiếu Giao Hàng, Kho Xuất - Khóa không cho sửa) -->
      <div
        class="w-full lg:w-[37%] bg-white border border-slate-200 rounded-[8px] p-5 shadow-sm space-y-4 flex flex-col">
        <div>
          <div
            class="text-xs font-bold text-slate-800 uppercase tracking-wide flex items-center gap-1.5 border-b border-slate-100 pb-2 mb-3">
            <i class="pi pi-link text-sky-600"></i>
            <span>Nguồn Gốc & Điều Phối</span>
          </div>

          <div class="space-y-3.5 text-xs">
            <!-- Đơn bán hàng tham chiếu (Không sửa) -->
            <div class="flex flex-col gap-1">
              <label class="text-slate-500 font-medium">Số đơn hàng (SO):</label>
              <div
                class="font-mono font-bold text-slate-800 bg-slate-50 border border-slate-200 px-3 py-2 rounded-[6px]">
                {{ form.customer_order_number || form.order_number || '---' }}
              </div>
            </div>

            <!-- Phiếu yêu cầu giao hàng tham chiếu (Không sửa) -->
            <div class="flex flex-col gap-1">
              <label class="text-slate-500 font-medium">Phiếu yêu cầu giao hàng (DR):</label>
              <div
                class="font-mono font-bold text-sky-700 bg-sky-50 border border-sky-200 px-3 py-2 rounded-[6px]">
                #{{ form.delivery_request_id || form.delivery_request_number || '---' }}
              </div>
            </div>

            <!-- Kho xuất hàng (Không sửa) -->
            <div class="flex flex-col gap-1">
              <label class="text-slate-500 font-medium">Kho xuất hàng:</label>
              <div
                class="font-semibold text-slate-800 bg-slate-50 border border-slate-200 px-3 py-2 rounded-[6px]">
                {{ form.warehouse_name || 'Kho Tổng Miền Bắc (Hà Nội)' }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 3. Bảng Danh Sách Mặt Hàng -->
    <div class="space-y-2">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
          <i class="pi pi-list text-slate-700 text-sm"></i>
          <span class="text-xs font-bold text-slate-900 uppercase tracking-wide">
            Bảng Mặt Hàng Chứng Từ Bán Hàng
          </span>
        </div>
      </div>

      <InvoiceItemTable v-model="form.items" :readonly="form.accounting_status === 'POSTED'" />
    </div>

    <!-- 4. Tài Liệu & Chứng Từ Kèm Theo (Sử dụng PrimeVue FileUpload Advanced) -->
    <FormAttachmentUpload
      v-model="form.attachments"
      :record-id="form.id || route.params.id"
      title="Tài Liệu & Chứng Từ Đính Kèm (Sales Invoice)"
      subtitle="Đính kèm biên bản bàn giao (POD), phiếu xuất kho có chữ ký, hóa đơn VAT điện tử hoặc chứng chỉ xuất xưởng (CO/CQ)"
      section-type="invoice"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import Badge from 'primevue/badge';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Textarea from 'primevue/textarea';
import Select from 'primevue/select';
import InvoiceItemTable from '../../components/invoice/InvoiceItemTable.vue';
import FormAttachmentUpload from '../../components/common/FormAttachmentUpload.vue';

import { salesApi } from '../../api/sales.api';
import { inventoryApi } from '../../api/inventory.api';
import { formatCurrency } from '../../utils/format';
import { hasPermission } from '../../utils/rbac';

const props = defineProps({
  currentUser: {
    type: Object,
    default: null
  }
});
const canManageInvoice = computed(() => hasPermission('invoice.manage', props.currentUser));

const route = useRoute();
const router = useRouter();
const toast = useToast();

const isEditMode = computed(() => !!route.params.id && route.params.id !== 'create');
const saving = ref(false);
const syncingReserve = ref(false);
const stockMap = ref({});

const currencyOptions = ['VND', 'USD', 'EUR', 'JPY'];

const form = ref({
  id: null,
  invoice_number: '',
  invoice_date: new Date().toISOString().split('T')[0],
  currency: 'VND',
  exchange_rate: 1.0,
  created_by: 'admin',
  payment_terms: 'Công nợ 30 ngày',
  notes: '',
  delivery_request_id: null,
  delivery_request_number: '',
  warehouse_id: null,
  order_id: null,
  order_number: '',
  customer_order_number: '',
  customer_name: '',
  warehouse_name: 'Kho Tổng Miền Bắc (Hà Nội)',
  warehouse_status: 'PENDING_WAREHOUSE',
  accounting_status: 'UNPOSTED',
  items: [],
  attachments: []
});

const getWarehouseStatusMeta = (st) => {
  const map = {
    PENDING_WAREHOUSE: { label: 'Chưa gửi kho', severity: 'secondary' },
    SENT_TO_WAREHOUSE: { label: 'Chờ xử lý', severity: 'warn' },
    PROCESSED: { label: 'Đã xử lý', severity: 'info' },
    PICKING: { label: 'Đang soạn', severity: 'warn' },
    PACKED: { label: 'Đã đóng gói', severity: 'secondary' },
    DISPATCHED: { label: 'Đang giao', severity: 'warn' },
    DELIVERED: { label: 'Đã xuất kho', severity: 'success' }
  };
  return map[st] || { label: st || 'Chờ gửi kho', severity: 'secondary' };
};

const generateRandomInvoiceNumber = () => {
  const rand = Math.floor(1000 + Math.random() * 9000);
  return `INV-2026-${rand}`;
};

const calculatedTotal = computed(() => {
  return form.value.items.reduce((acc, it) => acc + (Number(it.quantity) || 0) * (Number(it.unit_price) || 0), 0);
});

watch(
  () => form.value.currency,
  (curr) => {
    if (curr === 'VND') {
      form.value.exchange_rate = 1.0;
    }
  }
);

import { FALLBACK_CATALOG_PRODUCTS } from '../../constants/catalogProducts.constant';
import { catalogApi } from '../../api/catalog.api';

// Lấy thông tin tồn kho thời gian thực từ inventory-service
const refreshStockInfo = async () => {
  try {
    // Kho của yêu cầu giao / kho đã giữ chỗ; chỉ đoán theo tên với dữ liệu cũ chưa lưu warehouse_id
    const warehouseName = form.value.warehouse_name || '';
    const warehouseId = form.value.warehouse_id
      || (warehouseName.includes('TP.HCM') || warehouseName.includes('Nam') ? 2 : (warehouseName.includes('Đà Nẵng') || warehouseName.includes('Trung') ? 3 : 1));

    // Tạo map PID <-> SKU từ Catalog
    const pidToSkuMap = new Map();
    FALLBACK_CATALOG_PRODUCTS.forEach(p => {
      if (p.id && p.sku) pidToSkuMap.set(Number(p.id), p.sku.trim().toUpperCase());
    });

    try {
      const catRes = await catalogApi.getProducts({ limit: 500 });
      const catList = catRes?.data || (Array.isArray(catRes) ? catRes : []);
      if (Array.isArray(catList)) {
        catList.forEach(p => {
          if (p.id && (p.sku || p.code)) {
            pidToSkuMap.set(Number(p.id), (p.sku || p.code).trim().toUpperCase());
          }
        });
      }
    } catch { /* Continue with stock data when the product catalog is unavailable. */ }

    const res = await inventoryApi.getStocks({ warehouseId });
    const list = res?.data || (Array.isArray(res) ? res : []);
    if (Array.isArray(list)) {
      const map = {};
      list.forEach(s => {
        const pid = Number(s.product_id ?? s.productId ?? 0);
        const sku = (s.sku || pidToSkuMap.get(pid) || '').trim().toUpperCase();
        if (sku) {
          map[sku] = {
            quantity_on_hand: Number(s.quantity_on_hand ?? s.quantityOnHand ?? 0),
            quantity_reserved: Number(s.quantity_reserved ?? s.quantityReserved ?? 0),
            available: Number(s.available ?? ((Number(s.quantity_on_hand ?? 0)) - (Number(s.quantity_reserved ?? 0))))
          };
        }
      });
      stockMap.value = map;
    }
  } catch (err) {
    console.warn('Không thể tải dữ liệu tồn kho:', err);
  }
};

const enrichInvoiceItem = (it, matchedOrderItem = null) => {
  const oItem = matchedOrderItem || it.orderItem || it.order_item || {};
  const reqQty = Number(it.requested_quantity != null ? it.requested_quantity : (it.quantity != null ? it.quantity : (it.request_quantity || 1)));
  const price = Number(it.unit_price != null ? it.unit_price : (it.selling_price != null ? it.selling_price : (oItem.selling_price || 0)));

  return {
    id: it.id,
    delivery_request_item_id: it.delivery_request_item_id || (it.delivery_request_id ? it.id : null),
    order_item_id: it.order_item_id || oItem.id || null,
    product_id: it.product_id || oItem.product_id,
    sku: it.sku || oItem.sku || '',
    designation: it.designation || oItem.designation || '',
    brand: it.brand || oItem.brand || '',
    description: it.description || oItem.description || it.product_name || '',
    com: it.com || oItem.com || 'Việt Nam',
    unit: it.unit || oItem.unit || 'Cái',
    requested_quantity: reqQty,
    quantity: reqQty,
    selling_price: price,
    unit_price: price,
    vat_percent: Number(it.vat_percent != null ? it.vat_percent : (oItem.vat_percent || 0)),
    lead_time: it.lead_time || oItem.lead_time || '',
    delivery_date: it.delivery_date || oItem.delivery_date || '',
    subtotal: Number(it.subtotal || (reqQty * price))
  };
};

const availableDeliveryRequests = ref([]);
const selectedDeliveryRequestId = ref(null);

const deliveryRequestOptions = computed(() => {
  return availableDeliveryRequests.value.map(dr => ({
    id: dr.id,
    label: `#${dr.id} - ${dr.customer_order_number || dr.order_number || ('Đơn #' + dr.order_id)} (${dr.customer_name || 'Khách hàng'}) - ${dr.items?.length || 0} SP`,
    raw: dr
  }));
});

const applyDeliveryRequest = async (dr, creator = 'admin', pendingItems = null) => {
  if (!dr) return;
  selectedDeliveryRequestId.value = dr.id;

  const effectiveOrderId = dr.order_id;
  let order = dr.order || {};
  let orderItemsMap = new Map();

  if (effectiveOrderId) {
    try {
      const oRes = await salesApi.getOrderById(effectiveOrderId);
      if (oRes?.data) {
        order = oRes.data;
        (order.items || []).forEach(oi => {
          if (oi.id) orderItemsMap.set(oi.id, oi);
          if (oi.sku) orderItemsMap.set(oi.sku, oi);
        });
      }
    } catch (e) {
      console.warn('Lỗi tải chi tiết đơn hàng:', e);
    }
  }

  const sourceItems = (dr.items && dr.items.length > 0) ? dr.items : (pendingItems || []);

  form.value = {
    ...form.value,
    id: null,
    invoice_number: form.value.invoice_number || generateRandomInvoiceNumber(),
    invoice_date: form.value.invoice_date || new Date().toISOString().split('T')[0],
    currency: order.currency || dr.currency || 'VND',
    exchange_rate: Number(order.exchange_rate) || 1.0,
    created_by: creator,
    payment_terms: order.payment_terms || 'Công nợ 30 ngày',
    notes: `Xuất theo Yêu Cầu Giao Hàng #${dr.id || dr.request_number} (${dr.customer_order_number || dr.order_number || order.customer_order_number || order.order_number || ''})`,
    delivery_request_id: dr.id,
    delivery_request_number: dr.request_number || dr.id,
    order_id: effectiveOrderId || null,
    order_number: dr.order_number || order.order_number || '',
    customer_order_number: dr.customer_order_number || order.customer_order_number || order.order_number || '',
    customer_name: dr.customer_name || order.customer_name || '',
    warehouse_id: dr.warehouse_id || null,
    warehouse_name: dr.warehouse_name || 'Kho Tổng Miền Bắc (Hà Nội)',
    warehouse_status: 'PENDING_WAREHOUSE',
    accounting_status: 'UNPOSTED',
    items: sourceItems.map((it) => {
      const matchedOrderItem = it.order_item_id ? orderItemsMap.get(it.order_item_id) : (it.sku ? orderItemsMap.get(it.sku) : null);
      return enrichInvoiceItem(it, matchedOrderItem);
    })
  };

  toast.add({
    severity: 'info',
    summary: 'Đã nạp dữ liệu giao hàng',
    detail: `Đã nạp ${form.value.items.length} mặt hàng từ Yêu Cầu Giao Hàng #${form.value.delivery_request_id}.`,
    life: 3000
  });

  refreshStockInfo();
};

const onSelectDeliveryRequest = (drId) => {
  const selected = availableDeliveryRequests.value.find(d => d.id === drId);
  if (selected) {
    applyDeliveryRequest(selected, form.value.created_by);
  }
};

// Load dữ liệu khi mở form
const initFormData = async () => {
  const user = props.currentUser || JSON.parse(localStorage.getItem('user') || '{}');
  const userCreator = user.name || user.email || 'admin';
  form.value.created_by = userCreator;
  if (!form.value.invoice_number) {
    form.value.invoice_number = generateRandomInvoiceNumber();
  }

  // Tải danh sách Yêu Cầu Giao Hàng để sẵn sàng chọn
  try {
    const drRes = await salesApi.getDeliveries();
    availableDeliveryRequests.value = drRes?.data || (Array.isArray(drRes) ? drRes : []);
  } catch (e) {
    console.warn('Lỗi tải danh sách Delivery Requests:', e);
  }

  // Trường hợp 1: Chế độ Edit / View chi tiết từ invoice ID
  if (isEditMode.value) {
    try {
      const res = await salesApi.getInvoiceById(route.params.id);
      const inv = res.data;
      if (inv) {
        let orderItemsMap = new Map();
        const orderId = inv.order_id || inv.deliveryRequest?.order_id;
        if (orderId) {
          try {
            const orderRes = await salesApi.getOrderById(orderId);
            const o = orderRes.data;
            if (o && Array.isArray(o.items)) {
              o.items.forEach(oi => {
                if (oi.id) orderItemsMap.set(oi.id, oi);
                if (oi.sku) orderItemsMap.set(oi.sku, oi);
              });
            }
          } catch { /* Keep the invoice data when its source order is unavailable. */ }
        }

        form.value = {
          id: inv.id,
          invoice_number: inv.invoice_number,
          invoice_date: inv.invoice_date,
          currency: inv.currency || 'VND',
          exchange_rate: Number(inv.exchange_rate) || 1.0,
          created_by: inv.created_by || userCreator,
          payment_terms: inv.payment_terms || 'Công nợ 30 ngày',
          notes: inv.notes || '',
          delivery_request_id: inv.delivery_request_id,
          delivery_request_number: inv.delivery_request_number,
          order_id: inv.order_id || inv.deliveryRequest?.order_id,
          order_number: inv.order_number || inv.deliveryRequest?.order_number,
          customer_order_number: inv.customer_order_number || inv.deliveryRequest?.customer_order_number,
          customer_name: inv.customer_name || inv.deliveryRequest?.customer_name,
          warehouse_id: inv.inventory_warehouse_id || inv.delivery_request?.warehouse_id || inv.deliveryRequest?.warehouse_id || null,
          warehouse_name: inv.warehouse_name || inv.deliveryRequest?.warehouse_name || 'Kho Tổng Miền Bắc (Hà Nội)',
          warehouse_status: inv.warehouse_status || 'PENDING_WAREHOUSE',
          accounting_status: inv.accounting_status || 'UNPOSTED',
          attachments: inv.attachments || [],
          items: (inv.items || []).map((it) => {
            const matchedOrderItem = it.order_item_id ? orderItemsMap.get(it.order_item_id) : (it.sku ? orderItemsMap.get(it.sku) : null);
            return enrichInvoiceItem(it, matchedOrderItem);
          })
        };
        refreshStockInfo();
      }
    } catch (err) {
      console.error('Lỗi tải chứng từ:', err);
      toast.add({
        severity: 'error',
        summary: 'Lỗi',
        detail: 'Không tìm thấy chứng từ bán hàng yêu cầu.',
        life: 3000
      });
    }
    return;
  }

  // Trường hợp 2: Lập chứng từ mới từ SessionStorage hoặc Query Params
  const pendingDataRaw = sessionStorage.getItem('pending_invoice_creation');
  let pendingData = null;
  if (pendingDataRaw) {
    try {
      pendingData = JSON.parse(pendingDataRaw);
      sessionStorage.removeItem('pending_invoice_creation');
    } catch (e) {
      console.error('Lỗi parse pending_invoice_creation:', e);
    }
  }

  const drId = route.query.delivery_request_id || route.query.dr_id || pendingData?.delivery_id;
  if (drId || pendingData) {
    let dr = availableDeliveryRequests.value.find((d) => String(d.id) === String(drId) || String(d.request_number) === String(drId));
    if (!dr && pendingData) {
      dr = {
        id: pendingData.delivery_id,
        request_number: pendingData.delivery_request_number,
        order_id: pendingData.order_id,
        order_number: pendingData.order_number,
        customer_order_number: pendingData.customer_order_number,
        customer_name: pendingData.customer_name,
        warehouse_name: pendingData.warehouse_name,
        shipping_address: pendingData.shipping_address,
        items: pendingData.items || []
      };
    }

    if (dr) {
      await applyDeliveryRequest(dr, userCreator, pendingData?.items);
      return;
    }
  }

  refreshStockInfo();
};

onMounted(() => {
  initFormData();
});

const handleBack = () => {
  if (route.query.from === 'delivery-request' || route.query.from === 'delivery' || route.query.delivery_request_id) {
    router.push('/delivery-request');
  } else {
    router.push('/invoice');
  }
};

const handleSyncReserve = async () => {
  if (!form.value.id) return;
  syncingReserve.value = true;
  try {
    const res = await salesApi.syncReserve(form.value.id, form.value.warehouse_name);
    toast.add({
      severity: 'success',
      summary: 'Đã Đồng Bộ Giữ Chỗ Tồn Kho',
      detail: `Chứng từ ${form.value.invoice_number} đã được gửi lệnh giữ chỗ thành công tới Inventory Service.`,
      life: 4000
    });
    await refreshStockInfo();
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi giữ chỗ tồn kho',
      detail: err.message || 'Không thể đồng bộ giữ chỗ tồn kho.',
      life: 4000
    });
  } finally {
    syncingReserve.value = false;
  }
};

const saveInvoice = async (sendImmediately = false) => {
  if (!form.value.invoice_number) {
    toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Vui lòng nhập số chứng từ!', life: 3000 });
    return;
  }

  saving.value = true;
  try {
    const payload = {
      invoice_number: form.value.invoice_number,
      delivery_request_id: form.value.delivery_request_id,
      delivery_request_number: form.value.delivery_request_number,
      order_number: form.value.order_number,
      customer_name: form.value.customer_name,
      warehouse_name: form.value.warehouse_name,
      invoice_date: form.value.invoice_date,
      currency: form.value.currency,
      exchange_rate: form.value.exchange_rate,
      created_by: form.value.created_by,
      payment_terms: form.value.payment_terms,
      notes: form.value.notes,
      total_amount: calculatedTotal.value,
      send_to_warehouse: sendImmediately,
      attachment_ids: (form.value.attachments || []).map(a => a.id).filter(Boolean),
      items: form.value.items
    };

    let res;
    if (isEditMode.value && form.value.id) {
      if (sendImmediately) {
        await salesApi.sendInvoiceToWarehouse(form.value.id);
      }
      res = { data: form.value };
    } else {
      res = await salesApi.createInvoice(payload);
    }

    const savedInv = res.data;

    toast.add({
      severity: 'success',
      summary: sendImmediately ? 'Đã Gửi Xuống Kho & Giữ Chỗ Thành Công' : 'Đã Lưu Chứng Từ & Giữ Chỗ Tồn Kho',
      detail: sendImmediately
        ? `Chứng từ ${savedInv.invoice_number} đã giữ chỗ hàng và chuyển sang phòng Logistics (Yêu Cầu Xuất Kho).`
        : `Chứng từ ${savedInv.invoice_number} đã được tạo: Đã cộng Tồn giữ chỗ (Reserved) và trừ Tồn khả dụng (Available) theo quy định!`,
      life: 5000
    });

    router.push('/invoice');
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi lưu chứng từ',
      detail: err.message || 'Không thể lưu chứng từ bán hàng.',
      life: 4000
    });
  } finally {
    saving.value = false;
  }
};
</script>
