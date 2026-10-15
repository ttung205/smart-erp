<template>
  <div class="flex flex-col gap-4">
    <!-- 1. Page Header Chuẩn -->
    <PageHeader
      title="Kiểm Nhận Nhập Kho (GRN)"
      subtitle="Tiếp nhận hàng hóa thực tế từ Nhà cung cấp theo Đơn mua hàng (PO) và kích hoạt cập nhật tồn kho"
    >
      <template #actions>
        <Button
          label="Tạo Phiếu Nhập Kho"
          icon="pi pi-plus"
          size="small"
          class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 !rounded-[6px] !text-xs !font-semibold !px-3 !py-1.5"
          @click="$router.push('/purchase/receipts/create')"
        />
      </template>
    </PageHeader>

    <!-- 2. Thẻ Thống Kê Tổng Quan (Flat KPI Cards - 1 Hàng Ngang 3 Thẻ) -->
    <div class="grid grid-cols-3 gap-3">
      <div 
        class="bg-white border border-slate-200 rounded-[6px] p-3 flex items-center justify-between cursor-pointer hover:border-amber-300 transition-colors"
        @click="activeTab = 'pending_orders'"
      >
        <div>
          <span class="text-xs text-amber-600 font-medium block">Đơn PO chờ nhập kho</span>
          <span class="text-lg font-bold text-amber-700 font-mono tabular-nums">{{ pendingOrders.length }}</span>
        </div>
        <div class="w-9 h-9 rounded-[6px] bg-amber-50 flex items-center justify-center text-amber-600">
          <i class="pi pi-clock text-base"></i>
        </div>
      </div>

      <div 
        class="bg-white border border-slate-200 rounded-[6px] p-3 flex items-center justify-between cursor-pointer hover:border-sky-300 transition-colors"
        @click="activeTab = 'grn_list'"
      >
        <div>
          <span class="text-xs text-sky-600 font-medium block">Phiếu GRN đang chờ đối soát</span>
          <span class="text-lg font-bold text-sky-700 font-mono tabular-nums">{{ stats.draft }}</span>
        </div>
        <div class="w-9 h-9 rounded-[6px] bg-sky-50 flex items-center justify-center text-sky-600">
          <i class="pi pi-file-edit text-base"></i>
        </div>
      </div>

      <div 
        class="bg-white border border-slate-200 rounded-[6px] p-3 flex items-center justify-between cursor-pointer hover:border-emerald-300 transition-colors"
        @click="activeTab = 'grn_list'"
      >
        <div>
          <span class="text-xs text-emerald-600 font-medium block">Đã xác nhận & Nhập kho</span>
          <span class="text-lg font-bold text-emerald-700 font-mono tabular-nums">{{ stats.confirmed }}</span>
        </div>
        <div class="w-9 h-9 rounded-[6px] bg-emerald-50 flex items-center justify-center text-emerald-600">
          <i class="pi pi-check-circle text-base"></i>
        </div>
      </div>
    </div>

    <!-- 3. Thanh Tab Điều Hướng (Hairline Tabs) -->
    <div class="bg-white border border-slate-200 rounded-[6px] overflow-hidden flex flex-col">
      <div class="border-b border-slate-200 bg-slate-50/70 px-3 pt-2 flex items-center justify-between flex-wrap gap-2">
        <div class="flex items-center gap-1">
          <!-- TAB 1: ĐƠN CHỜ NHẬP KHO -->
          <button
            type="button"
            class="px-4 py-2 text-xs font-bold rounded-t-[6px] border-t border-l border-r transition-all flex items-center gap-2"
            :class="activeTab === 'pending_orders' 
              ? 'bg-white text-sky-700 border-slate-200 shadow-sm border-b-transparent -mb-px' 
              : 'text-slate-600 hover:text-slate-900 border-transparent'"
            @click="activeTab = 'pending_orders'"
          >
            <i class="pi pi-inbox text-xs"></i>
            <span>Đơn Mua Chờ Nhập Kho</span>
            <span 
              class="px-1.5 py-0.2 rounded-full text-[10px] font-mono"
              :class="activeTab === 'pending_orders' ? 'bg-amber-100 text-amber-800 font-bold' : 'bg-slate-200 text-slate-700'"
            >
              {{ pendingOrders.length }}
            </span>
          </button>

          <!-- TAB 2: DANH SÁCH PHIẾU GRN ĐÃ LẬP -->
          <button
            type="button"
            class="px-4 py-2 text-xs font-bold rounded-t-[6px] border-t border-l border-r transition-all flex items-center gap-2"
            :class="activeTab === 'grn_list' 
              ? 'bg-white text-sky-700 border-slate-200 shadow-sm border-b-transparent -mb-px' 
              : 'text-slate-600 hover:text-slate-900 border-transparent'"
            @click="activeTab = 'grn_list'"
          >
            <i class="pi pi-list text-xs"></i>
            <span>Phiếu Kiểm Nhận (GRN)</span>
            <span 
              class="px-1.5 py-0.2 rounded-full text-[10px] font-mono"
              :class="activeTab === 'grn_list' ? 'bg-sky-100 text-sky-800 font-bold' : 'bg-slate-200 text-slate-700'"
            >
              {{ receipts.length }}
            </span>
          </button>
        </div>

        <div class="flex items-center gap-2 pb-1.5">
          <Button
            label="Làm mới"
            icon="pi pi-refresh"
            size="small"
            text
            class="!rounded-[6px] !text-xs !text-slate-600 hover:!bg-slate-100"
            :loading="loading"
            @click="loadAllData"
          />
        </div>
      </div>

      <!-- 4. NỘI DUNG TAB 1: ĐƠN MUA CHỜ NHẬP KHO -->
      <div v-if="activeTab === 'pending_orders'" class="p-3 flex flex-col gap-3">
        <!-- Toolbar Lọc cho Đơn chờ nhập kho -->
        <div class="flex flex-wrap items-center justify-between gap-3 bg-slate-50/50 p-2 rounded-[6px] border border-slate-100">
          <div class="flex items-center gap-2">
            <IconField>
              <InputIcon class="pi pi-search text-xs" />
              <InputText
                v-model="pendingSearchQuery"
                placeholder="Tìm mã PO, nhà cung cấp..."
                class="w-72 !rounded-[6px] !text-xs !py-1.5"
                size="small"
              />
            </IconField>
            <Button
              v-if="pendingSearchQuery"
              label="Xóa lọc"
              icon="pi pi-filter-slash"
              size="small"
              text
              severity="secondary"
              class="!rounded-[6px] !text-xs !py-1"
              @click="pendingSearchQuery = ''"
            />
          </div>
          <span class="text-xs text-slate-500">
            Có <strong class="text-slate-800">{{ filteredPendingOrders.length }}</strong> đơn PO sẵn sàng nhập hàng
          </span>
        </div>

        <CommonDataTable
          :value="filteredPendingOrders"
          :columns="pendingColumns"
          :loading="loading"
          :rows="10"
          emptyIcon="pi pi-check-circle"
          emptyTitle="Không có đơn mua nào đang chờ nhập kho"
          emptySubtitle="Tất cả các đơn PO đã được nhập hàng đầy đủ hoặc chưa được phê duyệt."
        >
          <!-- Cột Mã PO -->
          <template #col-code="{ data }">
            <span
              class="font-mono text-xs font-bold text-sky-700 bg-sky-50 px-2 py-0.5 rounded-[6px] border border-sky-200 cursor-pointer hover:underline inline-block whitespace-nowrap"
              @click="$router.push(`/purchase/orders/${data.id}`)"
              title="Nhấp để xem chi tiết đơn PO"
            >
              {{ data.code || data.po_number }}
            </span>
          </template>

          <!-- Cột Nhà Cung Cấp -->
          <template #col-supplier_name="{ data }">
            <div class="flex flex-col">
              <span class="text-xs font-semibold text-slate-800">{{ getSupplierName(data.supplier_id) }}</span>
              <span v-if="data.notes" class="text-[11px] text-slate-500 truncate max-w-xs">{{ data.notes }}</span>
            </div>
          </template>

          <!-- Cột Kho Dự Kiến -->
          <template #col-warehouse_id="{ data }">
            <span class="text-xs text-slate-700 font-medium">
              {{ getWarehouseName(data.warehouse_id) }}
            </span>
          </template>

          <!-- Cột Ngày Đặt -->
          <template #col-order_date="{ data }">
            <span class="text-xs text-slate-600 font-mono whitespace-nowrap">
              {{ formatDate(data.order_date) }}
            </span>
          </template>

          <!-- Cột Tiến Độ Tiếp Nhận Hàng -->
          <template #col-progress="{ data }">
            <div class="flex flex-col gap-1">
              <div class="flex items-center justify-between text-[11px]">
                <span class="text-slate-600 font-medium">Đã nhận:</span>
                <span class="font-mono font-bold" :class="getOrderProgressColor(data)">
                  {{ getOrderReceivedTotal(data) }} / {{ getOrderTotalQuantity(data) }} sản phẩm
                </span>
              </div>
              <div class="w-full bg-slate-200 h-1.5 rounded-full overflow-hidden">
                <div 
                  class="h-full bg-sky-600 rounded-full transition-all"
                  :style="{ width: `${getOrderPercent(data)}%` }"
                ></div>
              </div>
            </div>
          </template>

          <!-- Cột Giá Trị Đơn PO -->
          <template #col-grand_total="{ data }">
            <span class="text-xs font-bold font-mono text-slate-800">
              {{ formatCurrency(data.grand_total) }}
            </span>
          </template>

          <!-- Cột Thao Tác: Tiến hành nhập kho & Đóng đơn PO sớm -->
          <Column header="Thao Tác" style="width: 175px" class="text-center">
            <template #body="{ data }">
              <div class="flex items-center justify-center gap-1.5">
                <Button
                  label="Nhập kho"
                  icon="pi pi-box"
                  size="small"
                  class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 !rounded-[6px] !text-xs !font-semibold !px-2.5 !py-1"
                  v-tooltip.top="'Tiến hành kiểm đếm và lập phiếu nhập kho'"
                  @click="$router.push(`/purchase/receipts/create?poId=${data.id}`)"
                />
                <Button
                  icon="pi pi-check-circle"
                  size="small"
                  outlined
                  severity="secondary"
                  class="!w-7 !h-7 !p-0 !rounded-[6px] hover:!bg-slate-100"
                  v-tooltip.top="'Đóng đơn PO (Không chờ giao bù nữa)'"
                  @click="openCloseDialog(data)"
                />
              </div>
            </template>
          </Column>
        </CommonDataTable>
      </div>

      <!-- 5. NỘI DUNG TAB 2: DANH SÁCH PHIẾU GRN ĐÃ LẬP -->
      <div v-else class="p-3 flex flex-col gap-3">
        <!-- Toolbar Lọc & Tìm Kiếm GRN -->
        <div class="flex flex-wrap items-center justify-between gap-3 bg-slate-50/50 p-2 rounded-[6px] border border-slate-100">
          <div class="flex flex-wrap items-center gap-2">
            <IconField>
              <InputIcon class="pi pi-search text-xs" />
              <InputText
                v-model="searchQuery"
                placeholder="Tìm theo số GRN, số PO, người kiểm..."
                class="w-72 !rounded-[6px] !text-xs !py-1.5"
                size="small"
              />
            </IconField>

            <Select
              v-model="statusFilter"
              :options="statusOptions"
              optionLabel="label"
              optionValue="value"
              placeholder="Tất cả trạng thái"
              class="w-48 !rounded-[6px] !text-xs"
              size="small"
            />

            <Button
              v-if="searchQuery || statusFilter"
              label="Xóa lọc"
              icon="pi pi-filter-slash"
              size="small"
              text
              severity="secondary"
              class="!rounded-[6px] !text-xs !py-1"
              @click="resetFilters"
            />
          </div>
        </div>

        <CommonDataTable
          :value="filteredReceipts"
          :columns="tableColumns"
          :loading="loading"
          :rows="10"
          emptyIcon="pi pi-inbox"
          emptyTitle="Không tìm thấy phiếu kiểm nhận nhập kho nào"
          emptySubtitle="Chưa có phiếu nhập hàng nào phù hợp với bộ lọc tìm kiếm."
        >
          <!-- Cột Số GRN -->
          <template #col-code="{ data }">
            <span
              class="font-mono text-xs font-bold text-sky-700 bg-sky-50 px-2 py-0.5 rounded-[6px] border border-sky-200 cursor-pointer hover:underline inline-block whitespace-nowrap"
              @click="$router.push(`/purchase/receipts/${data.id}`)"
              title="Nhấp để xem chi tiết và đối chiếu phiếu kiểm nhận"
            >
              {{ data.code || data.grn_number }}
            </span>
          </template>

          <!-- Cột Số PO -->
          <template #col-po_code="{ data }">
            <span class="font-mono text-xs font-semibold text-slate-800 bg-slate-100 px-2 py-0.5 rounded-[6px] border border-slate-200 inline-block whitespace-nowrap">
              {{ data.po_code || data.po_number || (data.purchase_order_id ? `PO #${data.purchase_order_id}` : '—') }}
            </span>
          </template>

          <!-- Cột Kho Hàng -->
          <template #col-warehouse_id="{ data }">
            <span class="text-xs text-slate-700 font-medium">
              {{ getWarehouseName(data.warehouse_id) }}
            </span>
          </template>

          <!-- Cột Ngày Nhập Kho -->
          <template #col-received_at="{ data }">
            <span class="text-xs text-slate-600 font-mono whitespace-nowrap">
              {{ formatDate(data.received_at || data.received_date) }}
            </span>
          </template>

          <!-- Cột Người Kiểm Nhận -->
          <template #col-received_by="{ data }">
            <span class="text-xs text-slate-800 font-medium">
              {{ typeof data.received_by === 'string' ? data.received_by : (data.received_by ? `Thủ kho #${data.received_by}` : 'Thủ kho') }}
            </span>
          </template>

          <!-- Cột Trạng Thái -->
          <template #col-status="{ data }">
            <div class="text-center">
              <Tag
                :severity="getStatusSeverity(data.status)"
                :value="getStatusLabel(data.status)"
                class="!rounded-[6px] !text-[11px] !font-semibold !px-2 !py-0.5"
              />
            </div>
          </template>

          <!-- Cột Thao Tác (CHỈ CHO PHÉP XEM & ĐỐI CHIẾU TRƯỚC KHI XÁC NHẬN) -->
          <Column header="Thao Tác" style="width: 100px" class="text-center">
            <template #body="{ data }">
              <div class="flex items-center justify-center gap-1.5">
                <Button
                  icon="pi pi-eye"
                  size="small"
                  outlined
                  severity="secondary"
                  class="!w-7 !h-7 !p-0 !rounded-[6px]"
                  v-tooltip.top="'Xem chi tiết & đối chiếu kiểm nhận'"
                  @click="$router.push(`/purchase/receipts/${data.id}`)"
                />
              </div>
            </template>
          </Column>
        </CommonDataTable>
      </div>
    </div>

    <!-- Dialog Xác nhận Đóng Đơn PO Sớm -->
    <Dialog
      v-model:visible="showCloseDialog"
      modal
      header="Đóng Đơn Mua Hàng Sớm"
      :style="{ width: '480px' }"
      class="p-fluid rounded-[6px]"
    >
      <div class="flex flex-col gap-3 py-2">
        <div class="flex items-start gap-3 bg-amber-50 border border-amber-200 rounded-[6px] p-3">
          <i class="pi pi-exclamation-triangle text-amber-600 text-lg mt-0.5"></i>
          <div class="flex flex-col text-xs text-amber-900 leading-relaxed">
            <span class="font-bold">Xác nhận kết thúc nhập kho đơn #{{ selectedPoToClose?.code }}</span>
            <span class="mt-0.5">
              Đơn hàng này hiện đã nhận <strong>{{ getOrderReceivedTotal(selectedPoToClose) }}/{{ getOrderTotalQuantity(selectedPoToClose) }}</strong> sản phẩm.
              Khi đóng đơn, hệ thống sẽ đánh dấu đơn là <strong>Hoàn tất</strong>, giải phóng phần chưa nhập và <strong>không chờ NCC giao bù</strong> số lượng còn lại nữa.
            </span>
          </div>
        </div>

        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-semibold text-slate-700">Lý do đóng đơn sớm (tùy chọn):</label>
          <Textarea
            v-model="closeReason"
            rows="3"
            placeholder="VD: Nhà cung cấp hết hàng không thể giao bù, Thỏa thuận hủy phần thiếu và khấu trừ công nợ..."
            class="!text-xs !rounded-[6px]"
          />
        </div>
      </div>

      <template #footer>
        <div class="flex justify-end gap-2 pt-2">
          <Button
            label="Hủy bỏ"
            icon="pi pi-times"
            size="small"
            text
            severity="secondary"
            class="!rounded-[6px] !text-xs"
            @click="showCloseDialog = false"
          />
          <Button
            label="Xác nhận đóng đơn"
            icon="pi pi-check-circle"
            size="small"
            severity="warn"
            class="!rounded-[6px] !text-xs font-semibold"
            :loading="closingPo"
            @click="confirmClosePo"
          />
        </div>
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import Dialog from 'primevue/dialog';
import Select from 'primevue/select';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import Column from 'primevue/column';

import PageHeader from '../../../components/common/PageHeader.vue';
import CommonDataTable from '../../../components/common/CommonDataTable.vue';
import { purchaseApi } from '../../../api/purchase.api';
import { GOODS_RECEIPT_STATUS, DEFAULT_SUPPLIERS } from '../../../constants/purchase.constant';
import { useWarehouses } from '../../../composables/useWarehouses';
import { formatCurrency } from '../../../utils/format';

const router = useRouter();
const toast = useToast();

const { getWarehouseName, fetchWarehouses } = useWarehouses();

const activeTab = ref('pending_orders');
const orders = ref([]);
const receipts = ref([]);
const loading = ref(false);

const pendingSearchQuery = ref('');
const searchQuery = ref('');
const statusFilter = ref(null);

const statusOptions = [
  { label: 'Tất cả trạng thái', value: null },
  { label: 'Bản nháp (Chờ đối chiếu)', value: 'Draft' },
  { label: 'Đã nhập kho & Cập nhật tồn', value: 'Confirmed' }
];

// Cột bảng đơn PO chờ nhập kho
const pendingColumns = [
  { field: 'code', header: 'Mã Đơn PO', width: '150px' },
  { field: 'supplier_name', header: 'Nhà Cung Cấp', width: '220px' },
  { field: 'warehouse_id', header: 'Kho Tiếp Nhận', width: '160px' },
  { field: 'order_date', header: 'Ngày Lập Đơn', width: '120px' },
  { field: 'progress', header: 'Tiến Độ Nhận Hàng', width: '200px' },
  { field: 'grand_total', header: 'Tổng Giá Trị', width: '150px', align: 'right' }
];

// Cột bảng danh sách phiếu GRN đã lập
const tableColumns = [
  { field: 'code', header: 'Mã Phiếu GRN', width: '160px' },
  { field: 'po_code', header: 'Theo Đơn PO', width: '160px' },
  { field: 'warehouse_id', header: 'Kho Tiếp Nhận', width: '180px' },
  { field: 'received_at', header: 'Ngày Kiểm Nhận', width: '130px' },
  { field: 'received_by', header: 'Người Kiểm Nhận', width: '160px' },
  { field: 'status', header: 'Trạng Thái', width: '150px', align: 'center' }
];

const loadAllData = async () => {
  loading.value = true;
  try {
    const [resReceipts, resOrders] = await Promise.all([
      purchaseApi.getReceipts(),
      purchaseApi.getOrders()
    ]);

    const rData = resReceipts?.data || resReceipts?.items || resReceipts;
    receipts.value = Array.isArray(rData) ? rData : [];

    const oData = resOrders?.data || resOrders?.items || resOrders;
    orders.value = Array.isArray(oData) ? oData : [];
  } catch (err) {
    console.error('Lỗi tải dữ liệu kiểm nhận nhập kho:', err);
    toast.add({
      severity: 'error',
      summary: 'Lỗi',
      detail: err.message || 'Không thể tải dữ liệu từ máy chủ',
      life: 4000
    });
  } finally {
    loading.value = false;
  }
};

// Lọc các PO đã duyệt và còn sản phẩm chưa nhập đủ
const pendingOrders = computed(() => {
  return orders.value.filter(po => {
    const status = String(po.status || '').toUpperCase();
    if (!['APPROVED', 'PARTIALLY_RECEIVED', 'PARTIALLYRECEIVED'].includes(status)) {
      return false;
    }
    if (!po.items || po.items.length === 0) return true;
    const totalOrdered = po.items.reduce((s, it) => s + Number(it.quantity_ordered || 0), 0);
    const totalReceived = po.items.reduce((s, it) => s + Number(it.quantity_received || 0), 0);
    return totalOrdered > totalReceived;
  });
});

const filteredPendingOrders = computed(() => {
  let list = pendingOrders.value || [];
  if (pendingSearchQuery.value) {
    const q = pendingSearchQuery.value.toLowerCase().trim();
    list = list.filter(po => {
      const code = String(po.code || po.po_number || '').toLowerCase();
      const sup = getSupplierName(po.supplier_id).toLowerCase();
      const notes = String(po.notes || '').toLowerCase();
      return code.includes(q) || sup.includes(q) || notes.includes(q);
    });
  }
  return list;
});

const stats = computed(() => {
  const list = receipts.value || [];
  return {
    total: list.length,
    draft: list.filter(x => String(x.status).toUpperCase() === 'DRAFT').length,
    confirmed: list.filter(x => String(x.status).toUpperCase() === 'CONFIRMED').length
  };
});

const filteredReceipts = computed(() => {
  let list = receipts.value || [];
  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase().trim();
    list = list.filter(
      x =>
        (x.code && x.code.toLowerCase().includes(q)) ||
        (x.grn_number && x.grn_number.toLowerCase().includes(q)) ||
        (x.po_code && x.po_code.toLowerCase().includes(q)) ||
        (x.po_number && x.po_number.toLowerCase().includes(q)) ||
        (String(x.received_by).toLowerCase().includes(q))
    );
  }
  if (statusFilter.value) {
    const filterUpper = statusFilter.value.toUpperCase();
    list = list.filter(x => String(x.status).toUpperCase() === filterUpper);
  }
  return list;
});

const getSupplierName = (supplierId) => {
  const found = DEFAULT_SUPPLIERS.find(s => s.id === Number(supplierId));
  return found ? found.name : `Nhà cung cấp #${supplierId}`;
};

const getOrderTotalQuantity = (order) => {
  if (!order.items) return 0;
  return order.items.reduce((s, it) => s + Number(it.quantity_ordered || 0), 0);
};

const getOrderReceivedTotal = (order) => {
  if (!order.items) return 0;
  return order.items.reduce((s, it) => s + Number(it.quantity_received || 0), 0);
};

const getOrderPercent = (order) => {
  const total = getOrderTotalQuantity(order);
  if (!total) return 0;
  const received = getOrderReceivedTotal(order);
  return Math.min(100, Math.round((received / total) * 100));
};

const getOrderProgressColor = (order) => {
  const pct = getOrderPercent(order);
  if (pct === 0) return 'text-amber-600';
  if (pct < 100) return 'text-sky-600';
  return 'text-emerald-600';
};

const resetFilters = () => {
  searchQuery.value = '';
  statusFilter.value = null;
};

const getStatusSeverity = (status) => {
  const upper = String(status || '').toUpperCase();
  return GOODS_RECEIPT_STATUS[upper]?.severity || 'secondary';
};

const getStatusLabel = (status) => {
  const upper = String(status || '').toUpperCase();
  return GOODS_RECEIPT_STATUS[upper]?.label || status;
};

const formatDate = (dateStr) => {
  if (!dateStr) return '—';
  try {
    const d = new Date(dateStr);
    return d.toLocaleDateString('vi-VN', { year: 'numeric', month: '2-digit', day: '2-digit' });
  } catch {
    return dateStr;
  }
};

const showCloseDialog = ref(false);
const selectedPoToClose = ref(null);
const closeReason = ref('');
const closingPo = ref(false);

const openCloseDialog = (order) => {
  selectedPoToClose.value = order;
  closeReason.value = '';
  showCloseDialog.value = true;
};

const confirmClosePo = async () => {
  if (!selectedPoToClose.value) return;
  closingPo.value = true;
  try {
    await purchaseApi.closeOrder(selectedPoToClose.value.id, {
      reason: closeReason.value || 'Đóng đơn hoàn tất nhập kho sớm'
    });
    toast.add({
      severity: 'success',
      summary: 'Thành công',
      detail: `Đã đóng đơn PO #${selectedPoToClose.value.code}. Đơn hàng đã hoàn tất và kết thúc luồng nhập kho.`,
      life: 4000
    });
    showCloseDialog.value = false;
    await loadAllData();
  } catch (err) {
    console.error('Lỗi đóng đơn PO:', err);
    toast.add({
      severity: 'error',
      summary: 'Lỗi',
      detail: err.message || 'Không thể đóng đơn mua hàng',
      life: 4000
    });
  } finally {
    closingPo.value = false;
  }
};

onMounted(() => {
  fetchWarehouses();
  loadAllData();
});
</script>
