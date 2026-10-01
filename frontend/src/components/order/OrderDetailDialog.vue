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

      <!-- Địa chỉ giao hàng thực tế -->
      <div v-if="order.shipping_address" class="flex items-start gap-2 px-3 py-2 bg-amber-50/60 border border-amber-200/70 rounded-[6px] text-xs">
        <i class="pi pi-map-marker text-rose-500 mt-0.5"></i>
        <div>
          <span class="font-semibold text-slate-700">Địa chỉ giao hàng: </span>
          <span class="text-slate-800">{{ order.shipping_address }}</span>
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
        <DataTable :value="order.items" showGridlines class="clean-datatable" responsiveLayout="scroll">
          <Column header="#" style="width: 40px" class="text-center">
            <template #body="{ index }">
              <span class="font-mono text-slate-400">{{ index + 1 }}</span>
            </template>
          </Column>

          <Column field="product_sku" header="Mã SKU" style="width: 140px">
            <template #body="{ data }">
              <span class="font-mono text-xs font-semibold text-sky-700">{{ data.product_sku || data.sku }}</span>
            </template>
          </Column>

          <Column field="product_name" header="Tên mặt hàng">
            <template #body="{ data }">
              <span>{{ data.product_name || data.description }}</span>
            </template>
          </Column>

          <Column header="ĐVT" style="width: 70px" class="text-center">
            <template #body="{ data }">
              <span class="text-slate-600">{{ data.unit || 'Cái' }}</span>
            </template>
          </Column>

          <Column header="Số lượng" class="text-right" style="width: 90px">
            <template #body="{ data }">
              <span class="font-mono font-bold">{{ data.quantity }}</span>
            </template>
          </Column>

          <Column header="Ngày hẹn giao" style="width: 130px" class="text-center">
            <template #body="{ data }">
              <span class="font-mono text-xs font-semibold text-sky-800 bg-sky-50 px-2 py-0.5 rounded border border-sky-200">
                {{ data.delivery_date || data.expected_delivery_date || 'Theo đơn' }}
              </span>
            </template>
          </Column>

          <Column header="Đơn giá bán" class="text-right" style="width: 130px">
            <template #body="{ data }">
              <span class="font-mono text-xs text-slate-800">{{ formatCurrency(data.unit_price || data.selling_price) }}</span>
            </template>
          </Column>

          <Column header="Thành tiền" class="text-right" style="width: 140px">
            <template #body="{ data }">
              <span class="font-mono text-xs font-bold text-sky-700">{{ formatCurrency(data.subtotal || (data.quantity * (data.unit_price || data.selling_price))) }}</span>
            </template>
          </Column>
        </DataTable>
      </div>

      <!-- Tổng kết & Tiến độ thực hiện (Giao hàng / Hóa đơn) -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Tiến độ luồng sau bán hàng -->
        <div class="bg-slate-50 border border-slate-200 rounded-[6px] p-3 text-xs space-y-2">
          <span class="font-semibold text-slate-700 block">Tiến độ thực hiện đơn hàng:</span>
          
          <div class="flex items-center justify-between">
            <span class="text-slate-500">Yêu cầu giao hàng (Delivery):</span>
            <Tag
              :value="order.has_delivery ? 'Đã tạo yêu cầu' : 'Chưa giao'"
              :severity="order.has_delivery ? 'success' : 'secondary'"
              class="text-[10px]"
            />
          </div>

          <div class="flex items-center justify-between">
            <span class="text-slate-500">Hóa đơn GTGT (VAT Invoice):</span>
            <Tag
              :value="order.has_invoice ? 'Đã xuất HĐ' : 'Chưa xuất'"
              :severity="order.has_invoice ? 'success' : 'secondary'"
              class="text-[10px]"
            />
          </div>
        </div>

        <!-- Tổng tiền -->
        <div class="flex flex-col justify-center items-end bg-slate-50 border border-slate-200 rounded-[6px] p-3">
          <span class="text-xs text-slate-500 mb-1">Tổng giá trị đơn hàng (đã gồm VAT):</span>
          <span class="text-lg font-bold font-mono text-indigo-700">{{ formatCurrency(order.total_amount) }}</span>
        </div>
      </div>
    </div>

    <template #footer>
      <div class="flex justify-between items-center w-full">
        <Button
          label="Đóng"
          icon="pi pi-times"
          text
          size="small"
          severity="secondary"
          class="rounded-[6px]"
          @click="$emit('update:visible', false)"
        />

        <div class="flex gap-2">
          <Button
            v-if="order?.status === 'APPROVED'"
            label="Lập Yêu Cầu Giao Hàng"
            icon="pi pi-truck"
            size="small"
            class="!bg-emerald-600 hover:!bg-emerald-700 !text-white !border-emerald-600 rounded-[6px]"
            @click="triggerCreateDelivery"
          />
        </div>
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
import { formatCurrency } from '../../utils/format';

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  order: {
    type: Object,
    default: null
  }
});

const emit = defineEmits(['update:visible', 'create-delivery']);

const getStatusMeta = (st) => {
  const map = {
    DRAFT: { label: 'Bản nháp', severity: 'secondary' },
    CONFIRMED: { label: 'Đã xác nhận', severity: 'info' },
    APPROVED: { label: 'Đã duyệt', severity: 'success' },
    PROCESSING: { label: 'Đang xử lý', severity: 'warn' },
    COMPLETED: { label: 'Hoàn tất', severity: 'success' },
    CANCELLED: { label: 'Đã hủy', severity: 'danger' }
  };
  return map[st] || { label: st || 'Chưa rõ', severity: 'secondary' };
};

const triggerCreateDelivery = () => {
  emit('update:visible', false);
  emit('create-delivery', props.order);
};
</script>
