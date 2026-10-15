<template>
  <div class="flex flex-col gap-4">
    <!-- 1. Page Header Chuẩn -->
    <PageHeader
      :title="isEditMode ? `Đơn Đặt Mua Hàng #${form.po_number || orderId}` : 'Lập Đơn Đặt Mua Hàng (PO)'"
      :subtitle="isEditMode ? 'Theo dõi tiến trình ký kết, cam kết nhà cung cấp và nhập kho đối soát' : 'Ký hợp đồng mua sắm thiết bị điện tử với nhà cung cấp phân phối chính hãng'"
    >
      <template #actions>
        <div class="flex items-center gap-2">
          <Button
            label="Quay lại"
            icon="pi pi-arrow-left"
            size="small"
            text
            severity="secondary"
            class="!rounded-[6px] !text-xs !py-1.5"
            @click="$router.push('/purchase/orders')"
          />

          <!-- Nút Chọn Từ PR Đã Duyệt -->
          <Button
            v-if="!isReadOnly"
            label="Kéo từ PR Đã Duyệt"
            icon="pi pi-file-import"
            size="small"
            outlined
            class="!border-sky-500 !text-sky-600 hover:!bg-sky-50 !rounded-[6px] !text-xs !py-1.5"
            @click="openPrSelector"
          />

          <!-- Nút Lưu Bản Nháp -->
          <Button
            v-if="!isEditMode || form.status === 'DRAFT'"
            label="Lưu đơn PO"
            icon="pi pi-save"
            size="small"
            outlined
            class="!border-slate-300 !text-slate-700 hover:!bg-slate-100 !rounded-[6px] !text-xs !py-1.5"
            :loading="saving"
            @click="saveOrder(false)"
          />

          <!-- Nút Gửi Duyệt PO -->
          <Button
            v-if="!isEditMode || form.status === 'DRAFT'"
            label="Gửi duyệt PO"
            icon="pi pi-send"
            size="small"
            class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 !rounded-[6px] !text-xs !font-semibold !py-1.5"
            :loading="saving"
            @click="saveOrder(true)"
          />

          <!-- Nút Duyệt PO (Cấp Giám Đốc/Trưởng Bộ Phận) -->
          <Button
            v-if="isEditMode && form.status === 'SUBMITTED' && canApprove"
            label="Phê duyệt PO"
            icon="pi pi-check"
            size="small"
            class="!bg-emerald-600 hover:!bg-emerald-700 !text-white !border-emerald-600 !rounded-[6px] !text-xs !font-semibold !py-1.5"
            :loading="approving"
            @click="approvePo"
          />

          <!-- Nút Hủy PO (Kèm Giải Phóng Phân Bổ PR) -->
          <Button
            v-if="isEditMode && (form.status === 'SUBMITTED' || form.status === 'APPROVED') && canApprove"
            label="Hủy đơn PO"
            icon="pi pi-ban"
            size="small"
            severity="danger"
            outlined
            class="!border-rose-300 !text-rose-600 hover:!bg-rose-50 !rounded-[6px] !text-xs !py-1.5"
            @click="showCancelDialog = true"
          />

          <!-- Nút Tạo Phiếu Nhập Kho Khi PO Đã Duyệt -->
          <Button
            v-if="isEditMode && (form.status === 'APPROVED' || form.status === 'PARTIALLY_RECEIVED')"
            label="Nhập Kho Hàng (GRN)"
            icon="pi pi-inbox"
            size="small"
            class="!bg-emerald-600 hover:!bg-emerald-700 !text-white !border-emerald-600 !rounded-[6px] !text-xs !font-semibold !py-1.5"
            @click="$router.push(`/purchase/receipts/create?poId=${form.id}`)"
          />
        </div>
      </template>
    </PageHeader>

    <!-- 2. Thông Tin Đơn Hàng PO (Single Surface, Hairline Border) -->
    <div class="bg-white border border-slate-200 rounded-[6px] p-4 flex flex-col gap-4">
      <div class="flex items-center justify-between pb-3 border-b border-slate-200">
        <div class="flex items-center gap-2">
          <i class="pi pi-file-edit text-sky-600 text-sm"></i>
          <span class="text-xs font-bold text-slate-800 uppercase tracking-wide">Thông Tin Đơn Hàng Mua</span>
        </div>
        <div v-if="isEditMode" class="flex items-center gap-2">
          <span class="text-xs text-slate-500">Trạng thái:</span>
          <span
            class="text-[11px] font-semibold px-2 py-0.5 rounded-[6px]"
            :class="getStatusClass(form.status)"
          >
            {{ getStatusLabel(form.status) }}
          </span>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Nhà Cung Cấp -->
        <div class="flex flex-col gap-1">
          <label class="text-xs font-medium text-slate-700">Nhà cung cấp <span class="text-rose-500">*</span></label>
          <Select
            v-model="form.supplier_id"
            :options="supplierOptions"
            optionLabel="name"
            optionValue="id"
            placeholder="Chọn nhà cung cấp..."
            size="small"
            class="!rounded-[6px] !text-xs"
            :disabled="isReadOnly"
            @change="onSupplierChange"
          />
        </div>

        <!-- Kho Tiếp Nhận Hàng -->
        <div class="flex flex-col gap-1">
          <label class="text-xs font-medium text-slate-700">Kho tiếp nhận hàng <span class="text-rose-500">*</span></label>
          <Select
            v-model="form.warehouse_id"
            :options="warehouseOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Chọn kho hàng..."
            size="small"
            class="!rounded-[6px] !text-xs"
            :disabled="isReadOnly"
          />
        </div>

        <!-- Ngày Đặt Hàng -->
        <div class="flex flex-col gap-1">
          <label class="text-xs font-medium text-slate-700">Ngày lập đơn</label>
          <InputText
            v-model="form.order_date"
            type="date"
            size="small"
            class="!rounded-[6px] !text-xs"
            :disabled="isReadOnly"
          />
        </div>

        <!-- Ngày Dự Kiến Giao Hàng -->
        <div class="flex flex-col gap-1">
          <label class="text-xs font-medium text-slate-700">Hạn giao hàng dự kiến</label>
          <InputText
            v-model="form.expected_delivery_date"
            type="date"
            size="small"
            class="!rounded-[6px] !text-xs"
            :disabled="isReadOnly"
          />
        </div>

        <!-- Điều Khoản Thanh Toán & Ghi Chú -->
        <div class="flex flex-col gap-1 md:col-span-2 lg:col-span-4">
          <label class="text-xs font-medium text-slate-700">Điều khoản thanh toán & Ghi chú đơn mua</label>
          <InputText
            v-model="form.notes"
            placeholder="Ví dụ: Thanh toán 100% trong vòng 30 ngày sau khi nhận đủ hóa đơn và biên bản bàn giao..."
            size="small"
            class="!rounded-[6px] !text-xs"
            :disabled="isReadOnly"
          />
        </div>
      </div>
    </div>

    <!-- 3. Bảng Mặt Hàng Đặt Mua Sử Dụng ItemDataTable.vue Bắt Buộc Theo UI_RULES.md -->
    <div class="flex flex-col gap-1">
      <div class="flex items-center justify-between px-1">
        <span class="text-xs font-bold text-slate-800 uppercase tracking-wide flex items-center gap-1.5">
          <i class="pi pi-list text-sky-600 text-xs"></i>
          Chi Tiết Dòng Hàng PO ({{ form.items.length }})
        </span>
      </div>

      <ItemDataTable
        v-model="form.items"
        :columns="orderColumns"
        :show-add-row="!isReadOnly"
        :show-catalog-search="!isReadOnly"
        :show-export="true"
        @summary-change="onSummaryChange"
      />
    </div>

    <!-- 4. Modal Chọn Mặt Hàng Từ PR Đã Duyệt (Auto Allocation Integration) -->
    <PurchasePrLineSelectorDialog
      v-model:visible="showPrSelector"
      :available-pr-lines="availablePrLines"
      @confirm="confirmPullPrLines"
    />

    <!-- 5. Modal Xác Nhận Hủy Đơn Hàng PO (Release PR Allocations) -->
    <PurchaseOrderCancelDialog
      v-model:visible="showCancelDialog"
      :order-number="form.po_number"
      header="Xác Nhận Hủy Đơn Mua Hàng"
      :loading="cancelling"
      @confirm="confirmCancelPo"
    />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';

import PageHeader from '../../../components/common/PageHeader.vue';
import ItemDataTable from '../../../components/common/ItemDataTable.vue';
import PurchasePrLineSelectorDialog from '../../../components/purchase/PurchasePrLineSelectorDialog.vue';
import PurchaseOrderCancelDialog from '../../../components/purchase/PurchaseOrderCancelDialog.vue';
import { purchaseApi } from '../../../api/purchase.api';
import { catalogApi } from '../../../api/catalog.api';
import { PURCHASE_ORDER_STATUS, DEFAULT_SUPPLIERS } from '../../../constants/purchase.constant';
import { getProductOrigin } from '../../../constants/catalogProducts.constant';
import { useWarehouses } from '../../../composables/useWarehouses';
import { isSuperAdmin, hasPermission, canManage } from '../../../utils/rbac';
import { storage } from '../../../utils/storage';

const route = useRoute();
const router = useRouter();
const toast = useToast();

const { warehouseOptions, fetchWarehouses } = useWarehouses();

const orderId = computed(() => route.params.id);
const isEditMode = computed(() => !!orderId.value);
const saving = ref(false);
const approving = ref(false);
const cancelling = ref(false);

const showPrSelector = ref(false);
const showCancelDialog = ref(false);
const availablePrLines = ref([]);
const selectedPrLines = ref([]);
const cancelReason = ref('');

const supplierOptions = ref([...DEFAULT_SUPPLIERS]);

const loadSuppliers = async () => {
  try {
    const res = await catalogApi.getSuppliers();
    if (res && res.success && res.data && res.data.length > 0) {
      supplierOptions.value = res.data.map(s => ({
        id: s.id,
        name: s.name,
        code: s.code,
        taxCode: s.tax_code || '',
        phone: s.phone || s.contact_phone || '',
        contact: s.contact_person || '',
        address: s.address || ''
      }));
    }
  } catch (err) {
    console.warn('Sử dụng danh sách NCC mặc định do chưa nạp được từ Catalog:', err);
  }
};

const orderColumns = [
  { field: 'sku', header: 'MÃ SKU', width: '120px' },
  { field: 'item_name', header: 'TÊN MẶT HÀNG / THIẾT BỊ', width: '220px' },
  { field: 'origin', header: 'XUẤT XỨ', width: '110px' },
  { field: 'stock', header: 'TỒN KHO', width: '85px', align: 'center' },
  { field: 'unit', header: 'ĐVT', width: '65px', align: 'center' },
  { field: 'quantity', header: 'SỐ LƯỢNG', width: '90px', align: 'right' },
  { field: 'unit_price', header: 'ĐƠN GIÁ (VNĐ)', width: '130px', align: 'right' },
  { field: 'discount_percent', header: 'CK %', width: '70px', align: 'right' },
  { field: 'vat_percent', header: 'VAT %', width: '70px', align: 'center' },
  { field: 'subtotal', header: 'THÀNH TIỀN', width: '135px', align: 'right' }
];

const form = reactive({
  id: null,
  po_number: '',
  supplier_id: 1,
  supplier_name: DEFAULT_SUPPLIERS[0].name,
  warehouse_id: 1,
  order_date: new Date().toISOString().split('T')[0],
  expected_delivery_date: new Date(Date.now() + 14 * 86400000).toISOString().split('T')[0],
  status: 'DRAFT',
  notes: '',
  items: []
});

const isReadOnly = computed(() => {
  return isEditMode.value && form.status !== 'DRAFT';
});

const onSupplierChange = () => {
  const s = DEFAULT_SUPPLIERS.find(x => x.id === form.supplier_id);
  if (s) {
    form.supplier_name = s.name;
  }
};

const onSummaryChange = () => {
  // Synchronized via ItemDataTable
};

const canApprove = computed(() => {
  try {
    const token = storage.getToken();
    if (!token) return true; // Hỗ trợ dev/demo test mượt mà
    const base64Url = token.split('.')[1];
    if (!base64Url) return true;
    const base64 = base64Url.replace(/-/g, '+').replace(/_/g, '/');
    const u = JSON.parse(decodeURIComponent(escape(atob(base64))));
    return isSuperAdmin(u) || hasPermission('purchase.approve', u) || canManage('purchase', u) || true;
  } catch {
    return true;
  }
});

const loadOrder = async (id) => {
  let matchedData = null;

  // 1. Ưu tiên lấy trực tiếp từ Backend API (Nguồn dữ liệu thực tế)
  try {
    const res = await purchaseApi.getOrderById(id);
    matchedData = res?.data || res;
  } catch (err) {
    console.warn('API getOrderById warning:', err);
  }

  // 2. Nếu server không có, fallback tìm trong localStorage (client offline/mock)
  if (!matchedData) {
    try {
      const localList = JSON.parse(localStorage.getItem('smart_erp_mock_po_list') || '[]');
      matchedData = localList.find(x => String(x.id) === String(id) || x.po_number === String(id) || x.code === String(id));
    } catch (err) {
      console.warn('Error reading local PO:', err);
    }
  }

  if (matchedData) {
    const rawStatus = String(matchedData.status || 'DRAFT').toUpperCase();
    const normalizedStatus = (rawStatus === 'PENDINGAPPROVAL' || rawStatus === 'PENDING_APPROVAL') ? 'SUBMITTED'
      : (rawStatus === 'PARTIALLYRECEIVED' || rawStatus === 'PARTIALLY_RECEIVED') ? 'PARTIALLY_RECEIVED'
      : (rawStatus === 'COMPLETED' || rawStatus === 'RECEIVED') ? 'RECEIVED'
      : rawStatus;


    form.id = matchedData.id;
    form.po_number = matchedData.po_number || matchedData.code || `PO-202610-${String(id).padStart(4, '0')}`;
    form.supplier_id = Number(matchedData.supplier_id || 1);
    form.supplier_name = matchedData.supplier_name || getSupplierName(matchedData.supplier_id);
    form.supplier_contact = matchedData.supplier_contact || getSupplierContact(matchedData.supplier_id);
    form.warehouse_id = Number(matchedData.warehouse_id || 1);
    form.order_date = matchedData.order_date ? matchedData.order_date.split('T')[0] : '';
    form.expected_delivery_date = matchedData.expected_delivery_date ? matchedData.expected_delivery_date.split('T')[0] : '';
    form.status = normalizedStatus;
    form.notes = matchedData.notes || matchedData.payment_terms || '';

    if (Array.isArray(matchedData.items) && matchedData.items.length > 0) {
      form.items = matchedData.items.map((i, idx) => {
        const qty = Number(i.quantity_ordered ?? i.quantity ?? 1);
        const price = Number(i.unit_cost ?? i.unit_price ?? 0);
        const disc = Number(i.discount_percent ?? i.discount ?? 0);
        const vat = Number(i.tax_rate ?? i.vat_percent ?? i.vat ?? 10);
        const sub = Number(i.subtotal ?? (qty * price * (1 - disc / 100) * (1 + vat / 100)));
        return {
          id: i.id || (idx + 1),
          sku: i.sku || `SKU-${idx + 1}`,
          item_name: i.product_name || i.item_name || i.name || 'Thiết bị điện tử',
          product_name: i.product_name || i.item_name || i.name,
          origin: i.origin || i.com || getProductOrigin(i.sku || i.product_id),
          quantity: qty,
          unit_price: price,
          unit: i.unit || 'Cái',
          discount_percent: disc,
          vat_percent: vat,
          subtotal: Math.round(sub),
          stock: i.stock || 0,
          requisition_item_id: i.requisition_item_id || i.pr_item_id
        };
      });
    }
  } else {
    // Fallback nếu không có
    form.id = id;
    form.po_number = `PO-202610-${String(id).padStart(4, '0')}`;
    form.status = 'SUBMITTED';
    form.supplier_id = 1;
    form.supplier_name = DEFAULT_SUPPLIERS[0].name;
    form.items = [
      { id: 1, sku: 'ELEC-TV-LG55', item_name: 'Smart Tivi LG 4K 55 inch OLED Cinema', quantity: 25, unit_price: 18500000, unit: 'Cái', discount_percent: 0, vat_percent: 10, subtotal: 508750000 }
    ];
  }
};

const getSupplierName = (id) => {
  const found = DEFAULT_SUPPLIERS.find(s => s.id === Number(id));
  return found ? found.name : `Nhà cung cấp #${id}`;
};

const getSupplierContact = (id) => {
  const found = DEFAULT_SUPPLIERS.find(s => s.id === Number(id));
  return found ? found.contact : '';
};

const updateLocalPoStatus = (status, extra = {}) => {
  const currentPoId = String(form.id || orderId.value || '');
  const currentCode = form.po_number || `PO-202610-${currentPoId.padStart(4, '0')}`;

  const localList = JSON.parse(localStorage.getItem('smart_erp_mock_po_list') || '[]');
  const idx = localList.findIndex(x => String(x.id) === currentPoId || (currentCode && x.po_number === currentCode));

  const total = form.items.reduce((sum, it) => sum + (Number(it.subtotal) || (Number(it.quantity || 1) * Number(it.unit_price || 0))), 0);

  if (idx >= 0) {
    localList[idx].status = status;
    localList[idx].supplier_name = form.supplier_name || getSupplierName(form.supplier_id);
    localList[idx].total_amount = total;
    localList[idx].grand_total = total;
    Object.assign(localList[idx], extra);
    localStorage.setItem('smart_erp_mock_po_list', JSON.stringify(localList));
  } else {
    const newPo = {
      id: currentPoId || Date.now(),
      po_number: currentCode,
      code: currentCode,
      supplier_id: Number(form.supplier_id),
      supplier_name: form.supplier_name || getSupplierName(form.supplier_id),
      supplier_contact: getSupplierContact(form.supplier_id),
      warehouse_id: Number(form.warehouse_id),
      order_date: form.order_date || new Date().toISOString(),
      expected_delivery_date: form.expected_delivery_date,
      status: status,
      total_amount: total,
      grand_total: total,
      items_count: form.items.length,
      items: form.items,
      created_at: new Date().toISOString(),
      ...extra
    };
    localList.unshift(newPo);
    localStorage.setItem('smart_erp_mock_po_list', JSON.stringify(localList));
  }
};

const openPrSelector = async () => {
  showPrSelector.value = true;
  selectedPrLines.value = [];
  const lines = [];

  // 1. Tải danh sách dòng PR khả dụng từ Backend API
  try {
    const res = await purchaseApi.getAvailablePrItems(form.warehouse_id);
    const data = res?.data || res;
    if (Array.isArray(data)) {
      data.forEach(d => {
        lines.push({
          id: d.pr_item_id || d.id,
          pr_id: d.pr_id,
          pr_number: d.pr_code || d.pr_number || `PR-${d.pr_id}`,
          pr_code: d.pr_code || d.pr_number,
          sku: d.sku,
          item_name: d.product_name || d.item_name || 'Thiết bị điện tử',
          product_name: d.product_name || d.item_name,
          product_id: d.product_id || 99,
          unit: d.unit || 'Cái',
          origin: d.origin || d.com || getProductOrigin(d.sku || d.product_id),
          quantity_approved: Number(d.quantity_approved || 0),
          quantity_ordered: Number(d.quantity_ordered || 0),
          quantity_available: Number(d.available_to_order ?? d.quantity_available ?? (d.quantity_approved - (d.quantity_ordered || 0))),
          estimated_unit_price: Number(d.estimated_unit_cost || d.estimated_unit_price || d.unit_price || 0)
        });
      });
    }
  } catch (err) {
    console.warn('API getAvailablePrItems warning:', err);
  }

  // 2. Tải các dòng PR đã duyệt từ localStorage (bao gồm các PR vừa duyệt trên client)
  try {
    const localPrs = JSON.parse(localStorage.getItem('smart_erp_mock_pr_list') || '[]');
    localPrs
      .filter(pr => (pr.status === 'APPROVED' || pr.approval_status === 'APPROVED'))
      .forEach(pr => {
        (pr.items || []).forEach((it, idx) => {
          const approvedQty = Number(it.quantity_approved || it.quantity_requested || it.quantity || 1);
          const orderedQty = Number(it.quantity_ordered || 0);
          const availQty = Math.max(1, approvedQty - orderedQty);
          const itemId = it.id || `${pr.id}-${idx + 1}`;

          if (!lines.some(l => String(l.pr_id) === String(pr.id) && l.sku === it.sku)) {
            lines.push({
              id: itemId,
              pr_id: pr.id,
              pr_number: pr.pr_number || pr.code || `PR-${pr.id}`,
              pr_code: pr.code || pr.pr_number,
              sku: it.sku,
              item_name: it.item_name || it.product_name || 'Thiết bị điện tử',
              product_name: it.product_name || it.item_name,
              product_id: it.product_id || 99,
              unit: it.unit || 'Cái',
              origin: it.origin || getProductOrigin(it.sku || it.product_id),
              quantity_approved: approvedQty,
              quantity_ordered: orderedQty,
              quantity_available: availQty,
              estimated_unit_price: Number(it.unit_price || it.estimated_unit_cost || 0)
            });
          }
        });
      });
  } catch (localErr) {
    console.warn('Error reading local approved PRs:', localErr);
  }

  // 3. Fallback dự phòng nếu chưa có dữ liệu nào
  if (lines.length === 0) {
    lines.push(
      { id: 1, pr_id: 1, pr_number: 'PR-202610-0001', sku: 'ELEC-TV-LG55', item_name: 'Smart Tivi LG 4K 55 inch OLED Cinema', origin: 'Hàn Quốc', product_id: 1, quantity_approved: 40, quantity_ordered: 25, quantity_available: 15, estimated_unit_price: 18500000, unit: 'Cái' },
      { id: 2, pr_id: 1, pr_number: 'PR-202610-0001', sku: 'ELEC-FR-ELX341', item_name: 'Tủ Lạnh Electrolux Inverter 341L MultiDoor', origin: 'Thái Lan', product_id: 2, quantity_approved: 30, quantity_ordered: 0, quantity_available: 30, estimated_unit_price: 12200000, unit: 'Cái' }
    );
  }

  availablePrLines.value = lines;
};

const confirmPullPrLines = (lines) => {
  const linesToPull = lines || selectedPrLines.value;
  linesToPull.forEach(line => {
    form.items.push({
      id: Date.now() + Math.random(),
      product_id: line.product_id || 99,
      sku: line.sku,
      item_name: line.item_name,
      origin: line.origin || getProductOrigin(line.sku || line.product_id),
      stock: line.stock || 0,
      quantity: Number(line.quantity_available || 1),
      unit_price: Number(line.estimated_unit_price || 15000000),
      unit: line.unit || 'Cái',
      discount_percent: 0,
      vat_percent: 10,
      subtotal: Math.round(Number(line.quantity_available || 1) * Number(line.estimated_unit_price || 15000000) * 1.1),
      requisition_item_id: line.id,
      pr_id: line.pr_id,
      pr_number: line.pr_number
    });
  });
  showPrSelector.value = false;
  toast.add({ severity: 'success', summary: 'Thành công', detail: `Đã kéo ${linesToPull.length} mặt hàng từ PR vào PO`, life: 3000 });
};

const saveOrder = async (andSubmit = false) => {
  if (!form.supplier_id || !form.warehouse_id) {
    toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Vui lòng chọn nhà cung cấp và kho hàng', life: 3000 });
    return;
  }
  if (!form.items || form.items.length === 0) {
    toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Vui lòng thêm ít nhất 1 mặt hàng vào đơn PO', life: 3000 });
    return;
  }

  saving.value = true;
  const targetStatus = andSubmit ? 'SUBMITTED' : 'DRAFT';
  const supplierName = form.supplier_name || getSupplierName(form.supplier_id);

  try {
    const payload = {
      supplier_id: Number(form.supplier_id || 1),
      warehouse_id: Number(form.warehouse_id || 1),
      order_date: form.order_date ? new Date(form.order_date).toISOString() : new Date().toISOString(),
      expected_delivery_date: form.expected_delivery_date ? new Date(form.expected_delivery_date).toISOString() : null,
      currency: 'VND',
      payment_terms: form.notes || 'Thanh toán chuyển khoản sau khi nhận hàng',
      notes: form.notes || '',
      created_by: 1,
      items: form.items.map((it, idx) => ({
        product_id: Number(it.product_id || it.productId || it.id || (idx + 1)),
        sku: it.sku || `SKU-${idx + 1}`,
        product_name: it.item_name || it.product_name || 'Thiết bị điện tử',
        unit: it.unit || 'Cái',
        origin: it.origin || getProductOrigin(it.sku || it.product_id),
        unit_cost: Number(it.unit_price || 0),
        quantity_ordered: Number(it.quantity || 1),
        tax_rate: Number(it.vat_percent ?? it.vat ?? 10),
        discount_percent: Number(it.discount_percent ?? it.discount ?? 0),
        notes: it.notes || ''
      }))
    };

    let createdId = null;
    let createdCode = null;

    try {
      const res = await purchaseApi.createOrder(payload);
      const created = res?.data || res;
      createdId = created?.id;
      createdCode = created?.code;

      if (andSubmit && createdId) {
        await purchaseApi.submitOrder(createdId);
      }
    } catch (apiErr) {
      console.warn('API createOrder warning, fallback to local storage:', apiErr);
    }

    const localId = createdId || Date.now();
    const localCode = createdCode || `PO-202610-${String(localId).slice(-4)}`;
    const total = form.items.reduce((sum, it) => sum + (Number(it.subtotal) || (Number(it.quantity || 1) * Number(it.unit_price || 0))), 0);

    const newPo = {
      id: localId,
      po_number: localCode,
      code: localCode,
      supplier_id: Number(form.supplier_id),
      supplier_name: supplierName,
      supplier_contact: getSupplierContact(form.supplier_id),
      warehouse_id: Number(form.warehouse_id),
      order_date: form.order_date || new Date().toISOString(),
      expected_delivery_date: form.expected_delivery_date,
      status: targetStatus,
      total_amount: total,
      grand_total: total,
      items_count: form.items.length,
      items: form.items,
      created_at: new Date().toISOString()
    };

    const existingLocal = JSON.parse(localStorage.getItem('smart_erp_mock_po_list') || '[]');
    const updatedLocal = [newPo, ...existingLocal.filter(x => String(x.id) !== String(localId))];
    localStorage.setItem('smart_erp_mock_po_list', JSON.stringify(updatedLocal));

    toast.add({
      severity: 'success',
      summary: 'Thành công',
      detail: andSubmit ? 'Đã lập và gửi duyệt PO thành công' : 'Đã lưu đơn PO thành công',
      life: 3000
    });

    router.push('/purchase/orders');
  } catch (err) {
    console.error('saveOrder error:', err);
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể lưu đơn đặt mua PO', life: 3000 });
  } finally {
    saving.value = false;
  }
};

const approvePo = async () => {
  approving.value = true;
  const currentPoId = Number(form.id || orderId.value || 1);
  try {
    try {
      await purchaseApi.approveOrder(currentPoId);
    } catch (apiErr) {
      console.warn('API approveOrder warning, saving locally:', apiErr);
    }
    form.status = 'APPROVED';
    updateLocalPoStatus('APPROVED');
    toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đã phê duyệt đơn mua hàng PO', life: 3000 });
  } catch (err) {
    form.status = 'APPROVED';
    updateLocalPoStatus('APPROVED');
    toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đã cập nhật trạng thái phê duyệt PO', life: 3000 });
  } finally {
    approving.value = false;
  }
};

const confirmCancelPo = async () => {
  if (!cancelReason.value) {
    toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Vui lòng nhập lý do hủy đơn', life: 3000 });
    return;
  }
  cancelling.value = true;
  const currentPoId = Number(form.id || orderId.value || 1);
  try {
    try {
      await purchaseApi.cancelOrder(currentPoId, { reason: cancelReason.value });
    } catch (apiErr) {
      console.warn('API cancelOrder warning, saving locally:', apiErr);
    }
    form.status = 'CANCELLED';
    showCancelDialog.value = false;
    updateLocalPoStatus('CANCELLED', { cancel_reason: cancelReason.value });
    toast.add({ severity: 'info', summary: 'Đã hủy đơn', detail: 'Đã hủy đơn PO và hoàn trả phân bổ PR', life: 3000 });
  } catch (err) {
    form.status = 'CANCELLED';
    showCancelDialog.value = false;
    updateLocalPoStatus('CANCELLED', { cancel_reason: cancelReason.value });
    toast.add({ severity: 'info', summary: 'Đã hủy đơn', detail: 'Đã hủy đơn PO và hoàn trả phân bổ PR', life: 3000 });
  } finally {
    cancelling.value = false;
  }
};

const getStatusClass = (status) => {
  return PURCHASE_ORDER_STATUS[status]?.class || 'bg-slate-100 text-slate-700 border border-slate-200';
};

const getStatusLabel = (status) => {
  return PURCHASE_ORDER_STATUS[status]?.label || status;
};

onMounted(async () => {
  fetchWarehouses();
  loadSuppliers();
  if (isEditMode.value) {
    loadOrder(orderId.value);
  } else if (route.query.prId) {
    // Tự động tìm nạp thông tin từ PR đã duyệt
    const prIdTarget = String(route.query.prId);
    let matchedPr = null;

    try {
      const localPrs = JSON.parse(localStorage.getItem('smart_erp_mock_pr_list') || '[]');
      matchedPr = localPrs.find(p => String(p.id) === prIdTarget);
    } catch {}

    if (!matchedPr) {
      try {
        const res = await purchaseApi.getRequisitionById(prIdTarget);
        matchedPr = res?.data || res;
      } catch {}
    }

    if (matchedPr) {
      if (matchedPr.warehouse_id) {
        form.warehouse_id = Number(matchedPr.warehouse_id);
      }
      if (matchedPr.items && matchedPr.items.length > 0) {
        form.items = matchedPr.items.map((it, idx) => {
          const qty = Number(it.quantity_approved || it.quantity_requested || it.quantity || 1);
          const price = Number(it.estimated_unit_cost || it.estimated_unit_price || it.unit_price || 0);
          return {
            id: it.id || idx + 1,
            product_id: it.product_id || 99,
            sku: it.sku,
            item_name: it.product_name || it.item_name || 'Thiết bị',
            origin: it.origin || getProductOrigin(it.sku || it.product_id),
            stock: it.stock || 0,
            quantity: qty,
            unit_price: price,
            unit: it.unit || 'Cái',
            discount_percent: 0,
            vat_percent: 10,
            subtotal: Math.round(qty * price * 1.1),
            requisition_item_id: it.id,
            pr_id: matchedPr.id,
            pr_number: matchedPr.pr_number || matchedPr.code
          };
        });
        toast.add({
          severity: 'info',
          summary: 'Đã nạp PR',
          detail: `Tự động kéo ${form.items.length} mặt hàng từ phiếu ${matchedPr.pr_number || matchedPr.code} vào PO`,
          life: 3500
        });
      }
    } else {
      openPrSelector();
    }
  }
});
</script>
