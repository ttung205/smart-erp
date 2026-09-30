<template>
  <Dialog
    :visible="visible"
    modal
    :header="`Chi Tiết Đơn Bán Hàng: ${order?.order_number || order?.customer_order_number || order?.id || ''}`"
    :style="{ width: '880px' }"
    class="rounded-[8px]"
    @update:visible="$emit('update:visible', $event)"
  >
    <div v-if="order" class="flex flex-col gap-4 pt-1">
      <!-- 4 ô thông tin chính: Số đơn nội bộ cạnh số đơn khách -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-3 bg-slate-50 border border-slate-200 rounded-[6px] p-3 text-xs">
        <div>
          <span class="text-slate-500 block">Mã đơn nội bộ:</span>
          <span class="font-bold text-indigo-700 font-mono text-sm">{{ order.order_number || order.id }}</span>
        </div>
        <div>
          <span class="text-slate-500 block">Số đơn của khách:</span>
          <span class="font-bold text-slate-800 font-mono">{{ order.customer_order_number || order.order_number || order.id }}</span>
        </div>
        <div>
          <span class="text-slate-500 block">Khách hàng:</span>
          <span class="font-bold text-slate-800">{{ order.customer_name || order.customer?.name || 'N/A' }}</span>
          <span v-if="order.contact_name || order.contact?.name" class="text-slate-400 block text-[11px]">LH: {{ order.contact_name || order.contact?.name }}</span>
        </div>
        <div>
          <span class="text-slate-500 block">Nguồn báo giá:</span>
          <span class="font-mono text-sky-700 font-semibold">{{ order.quotation_number || order.quotation?.quotation_number || 'Đơn lập trực tiếp' }}</span>
        </div>
      </div>

      <!-- Thanh trạng thái & phân công nhân sự -->
      <div class="flex flex-wrap items-center justify-between gap-2 px-3 py-2 bg-slate-50 border border-slate-200 rounded-[6px] text-xs">
        <div class="flex items-center gap-3">
          <span class="text-slate-500">Trạng thái:</span>
          <Tag
            :severity="getStatusMeta(order.status).severity"
            :value="getStatusMeta(order.status).label"
            class="text-[11px] px-2 py-0.5 rounded-[4px]"
          />
        </div>

        <div class="flex items-center gap-4 text-slate-600">
          <div>
            <span class="text-slate-400">Phụ trách: </span>
            <strong class="text-slate-700">{{ order.created_by_name || order.creator?.name || order.created_by || 'Quản trị viên' }}</strong>
          </div>
          <div>
            <span class="text-slate-400">Theo dõi: </span>
            <strong class="text-slate-700">{{ order.assigned_to_name || order.assignee?.name || order.assigned_to || 'N/A' }}</strong>
          </div>
          <div v-if="order.approved_by_name || order.approved_at">
            <span class="text-emerald-600 font-semibold">Đã duyệt bởi: </span>
            <strong class="text-slate-800">{{ order.approved_by_name || order.approver?.name }} ({{ order.approved_at }})</strong>
          </div>
        </div>
      </div>

      <!-- Danh sách mặt hàng -->
      <div class="border border-slate-200 rounded-[6px] overflow-hidden">
        <div class="bg-slate-100 px-3 py-1.5 border-b border-slate-200 text-xs font-semibold text-slate-700 flex justify-between items-center">
          <span>Danh Sách Mặt Hàng Đơn Bán</span>
          <span class="text-slate-500 font-mono text-[11px]">{{ order.items?.length || 0 }} mặt hàng</span>
        </div>
        <DataTable
          :value="order.items"
          showGridlines
          class="clean-datatable"
          responsiveLayout="scroll"
        >
          <Column field="sku" header="Mã SKU" style="width: 140px">
            <template #body="{ data }">
              <span class="font-mono text-xs font-bold text-sky-700">{{ data.sku || data.product_sku }}</span>
            </template>
          </Column>

          <Column field="description" header="Tên Sản Phẩm / Quy Cách">
            <template #body="{ data }">
              <div class="flex flex-col">
                <span class="text-xs font-semibold text-slate-800">{{ data.description || data.product_name || data.designation }}</span>
                <span v-if="data.brand || data.com" class="text-[11px] text-slate-400">
                  {{ data.brand }} <span v-if="data.brand && data.com">•</span> {{ data.com }}
                </span>
              </div>
            </template>
          </Column>

          <Column header="ĐVT" class="text-center" style="width: 70px">
            <template #body="{ data }">
              <span class="text-xs text-slate-600">{{ data.unit || 'Cái' }}</span>
            </template>
          </Column>

          <Column header="Số Lượng" class="text-center" style="width: 90px">
            <template #body="{ data }">
              <span class="font-mono text-xs font-bold text-slate-900">{{ data.quantity }}</span>
            </template>
          </Column>

          <Column header="Đơn Giá Bán" class="text-right" style="width: 130px">
            <template #body="{ data }">
              <span class="font-mono text-xs text-slate-700">{{ formatCurrency(data.selling_price || data.unit_price) }}</span>
            </template>
          </Column>

          <Column header="VAT" class="text-center" style="width: 65px">
            <template #body="{ data }">
              <span class="font-mono text-xs text-slate-500">{{ data.vat_percent ?? 10 }}%</span>
            </template>
          </Column>

          <Column header="Thành Tiền" class="text-right" style="width: 140px">
            <template #body="{ data }">
              <span class="font-mono text-xs font-bold text-indigo-700">{{ formatCurrency(data.subtotal || (data.quantity * (data.selling_price || data.unit_price))) }}</span>
            </template>
          </Column>
        </DataTable>
      </div>

      <div class="flex justify-between items-center bg-slate-50 border border-slate-200 rounded-[6px] p-3 text-xs">
        <div class="text-slate-500">
          Ngày tạo đơn: <strong class="text-slate-700 font-mono">{{ order.order_date }}</strong>
        </div>
        <div class="text-right">
          <span class="text-slate-500 mr-2">Tổng giá trị đơn hàng:</span>
          <span class="text-base font-bold font-mono text-indigo-700">{{ formatCurrency(order.total_amount) }}</span>
        </div>
      </div>
    </div>

    <template #footer>
      <div class="flex justify-between items-center w-full">
        <div class="flex items-center gap-2">
          <!-- Gửi duyệt khi DRAFT -->
          <Button
            v-if="order?.status === 'DRAFT'"
            label="Gửi xét duyệt"
            icon="pi pi-send"
            size="small"
            class="rounded-[6px] !text-xs !bg-sky-600 hover:!bg-sky-700 !text-white"
            @click="handleSubmitApproval"
          />

          <!-- Duyệt khi PENDING_APPROVAL -->
          <Button
            v-if="order?.status === 'PENDING_APPROVAL' && canApprove"
            label="Duyệt đơn hàng"
            icon="pi pi-check-circle"
            size="small"
            class="rounded-[6px] !text-xs !bg-emerald-600 hover:!bg-emerald-700 !text-white"
            @click="handleApprove"
          />

          <!-- Từ chối khi PENDING_APPROVAL -->
          <Button
            v-if="order?.status === 'PENDING_APPROVAL' && canApprove"
            label="Từ chối"
            icon="pi pi-times-circle"
            size="small"
            severity="danger"
            outlined
            class="rounded-[6px] !text-xs"
            @click="handleReject"
          />

          <Button
            label="Mở trang chỉnh sửa"
            icon="pi pi-external-link"
            size="small"
            outlined
            class="rounded-[6px] !text-xs !text-indigo-600 !border-indigo-300"
            @click="$router.push(`/order/${order?.id}/edit`); $emit('update:visible', false)"
          />
        </div>

        <Button
          label="Đóng"
          icon="pi pi-times"
          text
          size="small"
          severity="secondary"
          class="rounded-[6px] !text-xs"
          @click="$emit('update:visible', false)"
        />
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import Dialog from 'primevue/dialog';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import { useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import { salesApi } from '../../api/sales.api';
import { formatCurrency } from '../../utils/format';

const router = useRouter();
const toast = useToast();
const confirm = useConfirm();

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  order: {
    type: Object,
    default: null
  },
  canApprove: {
    type: Boolean,
    default: true
  }
});

const emit = defineEmits(['update:visible', 'order-approved', 'order-rejected', 'order-submitted']);

const getStatusMeta = (st) => {
  const map = {
    DRAFT: { label: 'Bản nháp', severity: 'secondary' },
    PENDING_APPROVAL: { label: 'Chờ duyệt', severity: 'warn' },
    APPROVED: { label: 'Đã duyệt', severity: 'info' },
    CONFIRMED: { label: 'Đã xác nhận (SO)', severity: 'success' },
    PROCESSING: { label: 'Đang xử lý', severity: 'warn' },
    COMPLETED: { label: 'Hoàn thành', severity: 'success' },
    REJECTED: { label: 'Từ chối', severity: 'danger' },
    CANCELLED: { label: 'Đã hủy', severity: 'danger' }
  };
  return map[st] || { label: st, severity: 'secondary' };
};

const handleSubmitApproval = () => {
  confirm.require({
    header: 'Xác Nhận Gửi Xét Duyệt',
    message: `Gửi xét duyệt đơn hàng ${props.order?.order_number}?`,
    icon: 'pi pi-send',
    accept: async () => {
      try {
        await salesApi.submitOrderApproval(props.order.id);
        toast.add({ severity: 'info', summary: 'Đã gửi duyệt', detail: 'Đơn hàng đã chuyển sang Chờ duyệt', life: 3000 });
        emit('order-submitted');
        emit('update:visible', false);
      } catch (err) {
        toast.add({ severity: 'error', summary: 'Lỗi', detail: err.message, life: 3000 });
      }
    }
  });
};

const handleApprove = () => {
  confirm.require({
    header: 'Xác Nhận Phê Duyệt',
    message: `Phê duyệt đơn hàng ${props.order?.order_number}?`,
    icon: 'pi pi-check-circle',
    accept: async () => {
      try {
        await salesApi.approveOrder(props.order.id);
        toast.add({ severity: 'success', summary: 'Đã duyệt', detail: 'Đơn hàng đã được duyệt thành công', life: 3000 });
        emit('order-approved');
        emit('update:visible', false);
      } catch (err) {
        toast.add({ severity: 'error', summary: 'Lỗi', detail: err.message, life: 3000 });
      }
    }
  });
};

const handleReject = () => {
  confirm.require({
    header: 'Xác Nhận Từ Chối',
    message: `Từ chối duyệt đơn hàng ${props.order?.order_number}?`,
    icon: 'pi pi-times-circle',
    accept: async () => {
      try {
        await salesApi.rejectOrder(props.order.id);
        toast.add({ severity: 'warn', summary: 'Đã từ chối', detail: 'Đơn hàng đã chuyển sang trạng thái Từ chối', life: 3000 });
        emit('order-rejected');
        emit('update:visible', false);
      } catch (err) {
        toast.add({ severity: 'error', summary: 'Lỗi', detail: err.message, life: 3000 });
      }
    }
  });
};
</script>
