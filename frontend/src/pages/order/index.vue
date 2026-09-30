<template>
  <div class="space-y-3 pb-8">
    <PageHeader
      title="Đơn Bán Hàng (Sales Orders)"
      subtitle="Quản lý danh sách đơn bán hàng chính thức (SO), quy trình xét duyệt và liên kết giao hàng"
      badge="Stage 2: Sales Order"
      badge-severity="info"
    >
      <template #actions>
        <Button
          label="Lập Đơn Bán Mới"
          icon="pi pi-plus"
          size="small"
          class="!bg-indigo-600 hover:!bg-indigo-700 !text-white !border-indigo-600 rounded-[6px]"
          @click="$router.push('/order/create')"
        />
      </template>
    </PageHeader>

    <!-- Toolbar Filter -->
    <Toolbar class="bg-white border border-slate-200 rounded-[6px] px-3 py-2 shadow-none">
      <template #start>
        <div class="flex items-center gap-2">
          <IconField>
            <InputIcon class="pi pi-search" />
            <InputText
              v-model="searchQuery"
              placeholder="Tìm theo mã đơn nội bộ, số đơn khách, khách hàng..."
              class="w-80 rounded-[6px]"
              size="small"
            />
          </IconField>
          <Select
            v-model="statusFilter"
            :options="statusOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Lọc trạng thái..."
            class="w-52 rounded-[6px]"
            size="small"
          />
        </div>
      </template>

      <template #end>
        <div class="flex items-center gap-2 text-xs text-slate-500">
          <span>Tổng số: <strong class="text-slate-800">{{ orders.length }}</strong> đơn bán hàng</span>
        </div>
      </template>
    </Toolbar>

    <!-- Data Table -->
    <div class="bg-white border border-slate-200 rounded-[6px] overflow-hidden shadow-sm">
      <DataTable
        :value="filteredOrders"
        showGridlines
        class="clean-datatable"
        responsiveLayout="scroll"
        :paginator="filteredOrders.length > 8"
        :rows="8"
      >
        <!-- 1. Mã đơn nội bộ (OD-...) -->
        <Column field="order_number" header="Mã Đơn Nội Bộ" :sortable="true" style="width: 170px">
          <template #body="{ data }">
            <span
              class="font-mono text-xs font-bold text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded-[4px] border border-indigo-200 cursor-pointer hover:underline"
              @click="$router.push(`/order/${data._db_id || data.id}/edit`)"
              title="Nhấp để vào chỉnh sửa đơn bán"
            >
              {{ data.order_number || data.id }}
            </span>
          </template>
        </Column>

        <!-- 2. Số đơn của khách (customer_order_number) cạnh cột order_number -->
        <Column field="customer_order_number" header="Số Đơn Khách" :sortable="true" style="width: 170px">
          <template #body="{ data }">
            <span
              class="font-mono text-xs font-bold text-slate-800 bg-slate-50 px-2 py-0.5 rounded-[4px] border border-slate-200"
              :class="{ '!border-amber-300 !bg-amber-50 !text-amber-800': data.customer_order_number && data.customer_order_number !== data.order_number }"
              :title="data.customer_order_number !== data.order_number ? 'Số đơn riêng do khách hàng yêu cầu' : 'Dùng theo số đơn hệ thống tự sinh'"
            >
              {{ data.customer_order_number || data.order_number || data.id }}
            </span>
          </template>
        </Column>

        <!-- 3. Nguồn báo giá (quotation_id / quotation_number) -->
        <Column field="quotation_number" header="Nguồn Báo Giá" style="width: 160px">
          <template #body="{ data }">
            <span v-if="data.quotation_number || data.quotation?.quotation_number" class="font-mono text-xs text-sky-700 bg-sky-50 px-2 py-0.5 rounded-[4px] border border-sky-100 font-semibold">
              {{ data.quotation_number || data.quotation?.quotation_number }}
            </span>
            <span v-else class="text-slate-400 text-xs italic">Đơn lập trực tiếp</span>
          </template>
        </Column>

        <!-- 4. Khách hàng B2B -->
        <Column field="customer_name" header="Khách Hàng B2B" :sortable="true">
          <template #body="{ data }">
            <div class="flex flex-col">
              <span class="font-semibold text-slate-800 text-xs">{{ data.customer_name || data.customer?.name }}</span>
              <span v-if="data.contact_name || data.contact?.name" class="text-[11px] text-slate-400">LH: {{ data.contact_name || data.contact?.name }}</span>
            </div>
          </template>
        </Column>

        <!-- 5. Ngày tạo đơn -->
        <Column field="order_date" header="Ngày Tạo" style="width: 110px">
          <template #body="{ data }">
            <span class="text-slate-600 text-xs font-mono">{{ data.order_date }}</span>
          </template>
        </Column>

        <!-- 6. Mặt hàng chốt -->
        <Column header="Mặt Hàng Chốt" class="text-center" style="width: 110px">
          <template #body="{ data }">
            <span class="text-xs font-mono font-medium text-slate-700 bg-slate-100 px-2 py-0.5 rounded-[4px]">
              {{ data.items?.length || 0 }} items
            </span>
          </template>
        </Column>

        <!-- 7. Tổng tiền đơn -->
        <Column field="total_amount" header="Tổng Tiền Đơn" class="text-right" :sortable="true" style="width: 150px">
          <template #body="{ data }">
            <span class="font-mono text-xs font-bold text-slate-900 num-tabular">
              {{ formatCurrency(data.total_amount) }}
            </span>
          </template>
        </Column>

        <!-- 8. Trạng thái -->
        <Column header="Trạng Thái" class="text-center" style="width: 130px">
          <template #body="{ data }">
            <Tag
              :severity="getStatusMeta(data.status).severity"
              :value="getStatusMeta(data.status).label"
              class="text-[11px] px-2 py-0.5 rounded-[6px]"
            />
          </template>
        </Column>

        <!-- 9. Thao tác -->
        <Column header="Thao Tác" class="text-center" style="width: 200px">
          <template #body="{ data }">
            <div class="flex items-center justify-center gap-1.5">
              <!-- Xem nhanh dialog -->
              <Button
                icon="pi pi-eye"
                text
                size="small"
                severity="secondary"
                class="!w-7 !h-7 !p-0 rounded-[6px] hover:!bg-slate-100"
                v-tooltip.bottom="'Xem nhanh chi tiết đơn'"
                @click="openViewOrder(data)"
              />
              <!-- Chỉnh sửa trang riêng -->
              <Button
                icon="pi pi-pencil"
                text
                size="small"
                class="!w-7 !h-7 !p-0 rounded-[6px] !text-indigo-600 hover:!bg-indigo-50"
                v-tooltip.bottom="'Chỉnh sửa đơn bán (Trang riêng)'"
                @click="$router.push(`/order/${data._db_id || data.id}/edit`)"
              />
              <!-- Gửi xét duyệt khi DRAFT -->
              <Button
                v-if="data.status === 'DRAFT'"
                icon="pi pi-send"
                text
                size="small"
                class="!w-7 !h-7 !p-0 rounded-[6px] !text-sky-600 hover:!bg-sky-50"
                v-tooltip.bottom="'Gửi xét duyệt đơn hàng'"
                @click="confirmSubmitApproval(data)"
              />
              <!-- Duyệt nhanh khi PENDING_APPROVAL -->
              <Button
                v-if="data.status === 'PENDING_APPROVAL' && canApprove"
                icon="pi pi-check-circle"
                text
                size="small"
                class="!w-7 !h-7 !p-0 rounded-[6px] !text-emerald-600 hover:!bg-emerald-50"
                v-tooltip.bottom="'Phê duyệt đơn bán hàng'"
                @click="confirmApproveOrder(data)"
              />
              <!-- Từ chối duyệt khi PENDING_APPROVAL -->
              <Button
                v-if="data.status === 'PENDING_APPROVAL' && canApprove"
                icon="pi pi-times-circle"
                text
                size="small"
                class="!w-7 !h-7 !p-0 rounded-[6px] !text-red-500 hover:!bg-red-50"
                v-tooltip.bottom="'Từ chối phê duyệt đơn'"
                @click="confirmRejectOrder(data)"
              />
              <!-- Lập yêu cầu giao hàng khi đã duyệt -->
              <Button
                v-if="data.status === 'APPROVED' || data.status === 'CONFIRMED'"
                icon="pi pi-truck"
                size="small"
                text
                class="!w-7 !h-7 !p-0 rounded-[6px] !text-sky-600 hover:!bg-sky-50"
                v-tooltip.bottom="'Lập lệnh yêu cầu giao hàng'"
                @click="openDeliveryDialog(data)"
              />
            </div>
          </template>
        </Column>

        <template #empty>
          <div class="py-10 text-center flex flex-col items-center gap-2">
            <i class="pi pi-shopping-cart text-3xl text-slate-300"></i>
            <p class="text-xs font-semibold text-slate-700 m-0">Chưa có đơn bán hàng nào</p>
            <p class="text-[11px] text-slate-400 m-0">Bấm nút "Lập Đơn Bán Mới" ở trên hoặc sang trang Báo Giá chọn Item Win để sinh Đơn bán.</p>
          </div>
        </template>
      </DataTable>
    </div>

    <!-- Modals -->
    <OrderDetailDialog
      v-model:visible="showViewDialog"
      :order="selectedOrder"
      :can-approve="canApprove"
      @order-approved="loadOrders"
      @order-rejected="loadOrders"
      @order-submitted="loadOrders"
    />

    <CreateDeliveryDialog
      v-model:visible="showDeliveryDialog"
      :order="selectedOrder"
      :warehouses="warehouses"
      @create-delivery-request="handleCreateDelivery"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Toolbar from 'primevue/toolbar';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import PageHeader from '../../components/common/PageHeader.vue';

import OrderDetailDialog from './view-dialog.vue';
import CreateDeliveryDialog from './delivery-dialog.vue';
import { salesApi } from '../../api/sales.api';
import { formatCurrency } from '../../utils/format';
import { isSuperAdmin, hasPermission } from '../../utils/rbac';

const props = defineProps({
  currentUser: {
    type: Object,
    default: null
  },
  warehouses: {
    type: Array,
    default: () => []
  }
});

const router = useRouter();
const toast = useToast();
const confirm = useConfirm();

const orders = ref([]);
const loading = ref(false);
const searchQuery = ref('');
const statusFilter = ref(null);
const showViewDialog = ref(false);
const showDeliveryDialog = ref(false);
const selectedOrder = ref(null);

const canApprove = computed(() => {
  if (!props.currentUser) return false;
  if (isSuperAdmin(props.currentUser)) return true;
  return hasPermission('order.approve', props.currentUser);
});

const statusOptions = [
  { label: 'Tất cả trạng thái', value: null },
  { label: 'Bản nháp', value: 'DRAFT' },
  { label: 'Chờ duyệt', value: 'PENDING_APPROVAL' },
  { label: 'Đã duyệt', value: 'APPROVED' },
  { label: 'Đã xác nhận', value: 'CONFIRMED' },
  { label: 'Đang xử lý', value: 'PROCESSING' },
  { label: 'Đã hoàn tất', value: 'COMPLETED' },
  { label: 'Từ chối', value: 'REJECTED' },
  { label: 'Đã hủy', value: 'CANCELLED' }
];

const getStatusMeta = (st) => {
  const map = {
    DRAFT: { label: 'Bản nháp', severity: 'secondary' },
    PENDING_APPROVAL: { label: 'Chờ duyệt', severity: 'warn' },
    APPROVED: { label: 'Đã duyệt', severity: 'info' },
    CONFIRMED: { label: 'Đã xác nhận (SO)', severity: 'success' },
    PROCESSING: { label: 'Đang xử lý', severity: 'warn' },
    COMPLETED: { label: 'Đã hoàn tất', severity: 'success' },
    REJECTED: { label: 'Từ chối', severity: 'danger' },
    CANCELLED: { label: 'Đã hủy', severity: 'danger' }
  };
  return map[st] || { label: st, severity: 'secondary' };
};

const loadOrders = async () => {
  loading.value = true;
  try {
    const res = await salesApi.getOrders();
    orders.value = (res.data || []).map((o) => ({
      ...o,
      _db_id: o.id,
      order_number: o.order_number || o.id,
      customer_order_number: o.customer_order_number || o.order_number || o.id,
      quotation_number: o.quotation_number || o.quotation?.quotation_number || o.quotation_id
    }));
  } catch (err) {
    console.error('Lỗi tải danh sách đơn hàng:', err);
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  loadOrders();
});

const filteredOrders = computed(() => {
  return orders.value.filter((o) => {
    const oId = String(o.order_number || o.id || '');
    const cOrderNo = String(o.customer_order_number || '');
    const cName = String(o.customer_name || o.customer?.name || '');
    const qNo = String(o.quotation_number || '');
    const q = searchQuery.value.toLowerCase().trim();

    const matchSearch =
      !q ||
      oId.toLowerCase().includes(q) ||
      cOrderNo.toLowerCase().includes(q) ||
      cName.toLowerCase().includes(q) ||
      qNo.toLowerCase().includes(q);

    const matchStatus = !statusFilter.value || o.status === statusFilter.value;
    return matchSearch && matchStatus;
  });
});

const openViewOrder = (order) => {
  selectedOrder.value = order;
  showViewDialog.value = true;
};

const openDeliveryDialog = (order) => {
  selectedOrder.value = order;
  showDeliveryDialog.value = true;
};

const confirmSubmitApproval = (order) => {
  confirm.require({
    header: 'Xác Nhận Gửi Xét Duyệt Đơn Bán Hàng',
    message: `Bạn có chắc chắn muốn gửi xét duyệt đơn bán hàng ${order.order_number} không?`,
    icon: 'pi pi-send',
    rejectLabel: 'Hủy bỏ',
    rejectProps: { severity: 'secondary', outlined: true },
    acceptLabel: 'Xác nhận gửi duyệt',
    acceptProps: { severity: 'info' },
    accept: async () => {
      try {
        await salesApi.submitOrderApproval(order.id);
        toast.add({
          severity: 'info',
          summary: 'Đã gửi xét duyệt',
          detail: `Đơn bán hàng ${order.order_number} đã chuyển sang trạng thái Chờ duyệt.`,
          life: 3000
        });
        await loadOrders();
      } catch (err) {
        toast.add({
          severity: 'error',
          summary: 'Lỗi gửi xét duyệt',
          detail: err.message || 'Không thể gửi xét duyệt đơn hàng.',
          life: 3000
        });
      }
    }
  });
};

const confirmApproveOrder = (order) => {
  confirm.require({
    header: 'Xác Nhận Phê Duyệt Đơn Bán Hàng',
    message: `Bạn có chắc chắn muốn phê duyệt đơn bán hàng ${order.order_number} không? Sau khi duyệt có thể tiến hành lập yêu cầu giao hàng.`,
    icon: 'pi pi-check-circle',
    rejectLabel: 'Hủy bỏ',
    rejectProps: { severity: 'secondary', outlined: true },
    acceptLabel: 'Đồng ý phê duyệt',
    acceptProps: { severity: 'success' },
    accept: async () => {
      try {
        await salesApi.approveOrder(order.id, props.currentUser?.id || 6);
        toast.add({
          severity: 'success',
          summary: 'Đã phê duyệt',
          detail: `Đơn bán hàng ${order.order_number} đã được duyệt thành công!`,
          life: 3000
        });
        await loadOrders();
      } catch (err) {
        toast.add({
          severity: 'error',
          summary: 'Lỗi phê duyệt',
          detail: err.message || 'Không thể duyệt đơn bán hàng.',
          life: 3000
        });
      }
    }
  });
};

const confirmRejectOrder = (order) => {
  confirm.require({
    header: 'Xác Nhận Từ Chối Đơn Bán Hàng',
    message: `Bạn có chắc chắn muốn từ chối đơn bán hàng ${order.order_number} không?`,
    icon: 'pi pi-times-circle',
    rejectLabel: 'Hủy bỏ',
    rejectProps: { severity: 'secondary', outlined: true },
    acceptLabel: 'Xác nhận từ chối',
    acceptProps: { severity: 'danger' },
    accept: async () => {
      try {
        await salesApi.rejectOrder(order.id);
        toast.add({
          severity: 'warn',
          summary: 'Đã từ chối',
          detail: `Đơn bán hàng ${order.order_number} đã chuyển sang trạng thái Từ chối.`,
          life: 3000
        });
        await loadOrders();
      } catch (err) {
        toast.add({
          severity: 'error',
          summary: 'Lỗi từ chối',
          detail: err.message || 'Không thể từ chối đơn bán hàng.',
          life: 3000
        });
      }
    }
  });
};

const handleCreateDelivery = async (newDr) => {
  try {
    const res = await salesApi.createDelivery(newDr);
    const dr = res.data;
    toast.add({
      severity: 'success',
      summary: 'Đã lập lệnh yêu cầu giao hàng',
      detail: `Lệnh giao ${dr?.request_number || dr?.id || 'DR'} đã phát hành thành công. Đang chuyển sang trang Giao Hàng...`,
      life: 3000
    });
    router.push('/delivery');
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi tạo yêu cầu giao hàng',
      detail: err.message || 'Không thể tạo yêu cầu giao hàng.',
      life: 3000
    });
  }
};
</script>
