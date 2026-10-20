<template>
  <div class="space-y-3 pb-8">
    <PageHeader
      title="Đơn Bán Hàng (Sales Orders)"
      subtitle="Theo dõi tiến độ đơn hàng đã ký kết, điều phối giao hàng và đối soát hóa đơn GTGT"
      badge="Stage 2: Sales Order"
      badge-severity="success"
    />

    <!-- Toolbar Filter -->
    <Toolbar class="bg-white border border-slate-200 rounded-[6px] px-3 py-2 shadow-none">
      <template #start>
        <div class="flex items-center gap-2">
          <IconField>
            <InputIcon class="pi pi-search" />
            <InputText
              v-model="searchQuery"
              placeholder="Tìm theo mã đơn, số đơn khách hoặc tên đối tác..."
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

    <!-- Data Table using CommonDataTable -->
    <CommonDataTable
      :value="filteredOrders"
      :columns="tableColumns"
      :rows="8"
      :paginator="filteredOrders.length > 8"
      emptyIcon="pi pi-shopping-cart"
      emptyTitle="Chưa có đơn bán hàng nào"
      emptySubtitle="Tất cả đơn bán hàng đều phải được tạo từ Báo giá đã duyệt. Vui lòng chuyển sang trang Báo Giá để tiến hành tạo đơn bán."
    >
      <!-- 0. ID đơn hàng -->
      <template #col-id="{ data }">
        <span class="font-mono text-xs font-bold text-sky-700 bg-sky-50 px-2 py-0.5 rounded-[4px] border border-sky-200 inline-block whitespace-nowrap">
          #{{ data.id }}
        </span>
      </template>

      <!-- 1. Mã đơn nội bộ (OD-...) -->
      <template #col-order_number="{ data }">
        <span
          class="font-mono text-xs font-bold text-indigo-700 bg-indigo-50 px-2.5 py-1 rounded-[4px] border border-indigo-200 cursor-pointer hover:underline inline-block whitespace-nowrap"
          @click="$router.push(`/order/${data._db_id || data.id}/edit`)"
          title="Nhấp để vào chỉnh sửa đơn bán"
        >
          {{ data.order_number || data.id }}
        </span>
      </template>

      <!-- 2. Số đơn của khách (customer_order_number) -->
      <template #col-customer_order_number="{ data }">
        <span
          class="font-mono text-xs font-bold text-slate-800 bg-slate-50 px-2.5 py-1 rounded-[4px] border border-slate-200 inline-block whitespace-nowrap"
          :class="{ '!border-amber-300 !bg-amber-50 !text-amber-800': data.customer_order_number && data.customer_order_number !== data.order_number }"
          :title="data.customer_order_number !== data.order_number ? 'Số đơn riêng do khách hàng yêu cầu' : 'Dùng theo số đơn hệ thống tự sinh'"
        >
          {{ data.customer_order_number || data.order_number || data.id }}
        </span>
      </template>

      <!-- 3. Khách hàng B2B -->
      <template #col-customer_name="{ data }">
        <div class="flex flex-col">
          <span class="font-semibold text-slate-800 text-xs">{{ data.customer_name || data.customer?.name }}</span>
          <span v-if="data.contact_name || data.contact?.name" class="text-[11px] text-slate-400">LH: {{ data.contact_name || data.contact?.name }}</span>
        </div>
      </template>

      <!-- 4. Ngày tạo đơn -->
      <template #col-order_date="{ data }">
        <span class="text-slate-600 text-xs font-mono whitespace-nowrap">{{ formatDate(data.order_date || data.created_at) }}</span>
      </template>

      <!-- 5. Mặt hàng chốt -->
      <template #col-items_count="{ data }">
        <div class="text-center">
          <span class="text-xs font-mono font-medium text-slate-700 bg-slate-100 px-2.5 py-1 rounded-[4px] inline-block whitespace-nowrap">
            {{ data.items?.length || 0 }} items
          </span>
        </div>
      </template>

      <!-- 6. Tổng tiền đơn -->
      <template #col-total_amount="{ data }">
        <div class="text-right">
          <span class="font-mono text-xs font-bold text-slate-900 num-tabular whitespace-nowrap">
            {{ formatCurrency(data.total_amount) }}
          </span>
        </div>
      </template>

      <!-- 7. Trạng thái -->
      <template #col-status="{ data }">
        <div class="text-center whitespace-nowrap">
          <Badge
            :severity="getStatusMeta(data.status).severity"
            :value="getStatusMeta(data.status).label"
          />
        </div>
      </template>

      <!-- 8. Thao tác -->
      <Column header="Thao Tác" class="text-center whitespace-nowrap" headerClass="whitespace-nowrap" style="min-width: 150px; width: 150px">
        <template #body="{ data }">
          <div class="flex items-center justify-center gap-1.5 whitespace-nowrap">
            <!-- Xem nhanh dialog -->
            <Button
              icon="pi pi-eye"
              outlined
              size="small"
              severity="secondary"
              class="!w-7 !h-7 !p-0 rounded-[6px]"
              v-tooltip.bottom="'Xem nhanh chi tiết đơn'"
              @click="openViewOrder(data)"
            />
            <!-- Trao đổi nội bộ -->
            <Button
              icon="pi pi-comments"
              outlined
              size="small"
              severity="help"
              class="!w-7 !h-7 !p-0 rounded-[6px]"
              v-tooltip.bottom="'Trao đổi nội bộ về đơn này'"
              @click="openWorkChat(data)"
            />
            <!-- Chỉnh sửa trang riêng -->
            <Button
              icon="pi pi-pencil"
              outlined
              size="small"
              severity="info"
              class="!w-7 !h-7 !p-0 rounded-[6px]"
              v-tooltip.bottom="'Chỉnh sửa đơn bán (Trang riêng)'"
              @click="$router.push(`/order/${data._db_id || data.id}/edit`)"
            />
            <!-- Gửi xét duyệt khi DRAFT -->
            <Button
              v-if="data.status === 'DRAFT'"
              icon="pi pi-send"
              outlined
              size="small"
              severity="warn"
              class="!w-7 !h-7 !p-0 rounded-[6px]"
              v-tooltip.bottom="'Gửi xét duyệt đơn hàng'"
              @click="confirmSubmitApproval(data)"
            />
            <!-- Duyệt nhanh khi PENDING_APPROVAL -->
            <Button
              v-if="data.status === 'PENDING_APPROVAL' && canApprove"
              icon="pi pi-check-circle"
              outlined
              size="small"
              severity="success"
              class="!w-7 !h-7 !p-0 rounded-[6px]"
              v-tooltip.bottom="'Phê duyệt đơn bán hàng'"
              @click="confirmApproveOrder(data)"
            />
            <!-- Từ chối duyệt khi PENDING_APPROVAL -->
            <Button
              v-if="data.status === 'PENDING_APPROVAL' && canApprove"
              icon="pi pi-times-circle"
              outlined
              size="small"
              severity="danger"
              class="!w-7 !h-7 !p-0 rounded-[6px]"
              v-tooltip.bottom="'Từ chối phê duyệt đơn'"
              @click="confirmRejectOrder(data)"
            />
            <!-- Lập yêu cầu giao hàng khi đã duyệt -->
            <Button
              v-if="data.status === 'APPROVED' || data.status === 'CONFIRMED'"
              icon="pi pi-truck"
              outlined
              size="small"
              severity="help"
              class="!w-7 !h-7 !p-0 rounded-[6px]"
              v-tooltip.bottom="'Lập lệnh yêu cầu giao hàng'"
              @click="openDeliveryDialog(data)"
            />
          </div>
        </template>
      </Column>
    </CommonDataTable>

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
      @create-delivery-request="handleCreateDelivery"
    />

    <!-- Khung Trao đổi nội bộ về Đơn bán hàng -->
    <WorkThreadDrawer
      v-if="workChatOrder"
      v-model:visible="showWorkChat"
      subject-type="Sale"
      :subject-id="workChatOrder.id"
      :subject-code="workChatOrder.order_number || workChatOrder.id"
      :subject-title="workChatOrder.customer_name"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import Column from 'primevue/column';
import Toolbar from 'primevue/toolbar';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Button from 'primevue/button';
import Badge from 'primevue/badge';
import PageHeader from '../../components/common/PageHeader.vue';
import CommonDataTable from '../../components/common/CommonDataTable.vue';

import OrderDetailDialog from '../../components/order/OrderDetailDialog.vue';
import CreateDeliveryDialog from '../../components/order/OrderDeliveryDialog.vue';
import WorkThreadDrawer from '../../components/chat/WorkThreadDrawer.vue';
import { salesApi } from '../../api/sales.api';
import { formatCurrency, formatDate } from '../../utils/format';
import { isSuperAdmin, hasPermission } from '../../utils/rbac';

const props = defineProps({
  currentUser: {
    type: Object,
    default: null
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

const showWorkChat = ref(false);
const workChatOrder = ref(null);

function openWorkChat(order) {
  workChatOrder.value = order;
  showWorkChat.value = true;
}

// Cấu hình các cột cho CommonDataTable (đồng bộ với các trang danh sách khác)
const tableColumns = [
  { field: 'id', header: 'ID', sortable: true, width: '70px', align: 'center' },
  { field: 'order_number', header: 'Mã Đơn Nội Bộ', sortable: true, width: '195px' },
  { field: 'customer_order_number', header: 'Số Đơn Khách', sortable: true, width: '165px' },
  { field: 'customer_name', header: 'Khách Hàng B2B', sortable: true },
  { field: 'order_date', header: 'Ngày Tạo', sortable: true, width: '110px', align: 'center' },
  { field: 'items_count', header: 'Số Item', sortable: false, width: '95px', align: 'center' },
  { field: 'total_amount', header: 'Tổng Tiền Đơn', sortable: true, width: '160px', align: 'right' },
  { field: 'status', header: 'Trạng Thái', sortable: true, width: '130px', align: 'center' }
];

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
    CONFIRMED: { label: 'Đã xác nhận', severity: 'success' },
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
