<template>
  <Dialog :visible="visible" modal :header="`Chi Tiết Yêu Cầu Giao Hàng: #${deliveryRequest?.id || ''}`"
    :style="{ width: '1040px' }" class="rounded-[8px]" @update:visible="$emit('update:visible', $event)"
    :draggable="false">
    <div v-if="deliveryRequest" class="flex flex-col gap-4 pt-1">
      <!-- 1. Header context info: Số đơn hàng, khách hàng, nhân viên KD -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 bg-slate-50 border border-slate-200 rounded-[6px] p-3 text-xs">
        <div>
          <span class="text-slate-500 block">Số đơn hàng:</span>
          <span class="font-bold text-slate-800 font-mono text-sm">{{ deliveryRequest.customer_order_number ||
            deliveryRequest.order_number || deliveryRequest.order_id }}</span>
        </div>
        <div>
          <span class="text-slate-500 block">Khách hàng:</span>
          <span class="font-bold text-slate-800 truncate block">{{ deliveryRequest.customer_name }}</span>
        </div>
        <div>
          <span class="text-slate-500 block">Nhân viên kinh doanh:</span>
          <span class="font-semibold text-slate-700 truncate block">{{ deliveryRequest.salesperson || 'Nhân viên KD'
            }}</span>
        </div>
      </div>

      <!-- 2. Thông tin giao nhận và trạng thái -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-3 bg-slate-50 border border-slate-200 rounded-[6px] p-3 text-xs">
        <div>
          <span class="text-slate-500 block">Kho xuất hàng:</span>
          <span class="font-semibold text-slate-800">{{ deliveryRequest.warehouse_name || 'Kho Tổng Miền Bắc (Hà Nội)'
            }}</span>
        </div>
        <div>
          <span class="text-slate-500 block">Hẹn ngày giao:</span>
          <span class="font-mono font-semibold text-sky-700">{{ deliveryRequest.expected_delivery_date ? formatDate(deliveryRequest.expected_delivery_date) : 'N/A' }}</span>
        </div>
        <div>
          <span class="text-slate-500 block">Trạng thái yêu cầu:</span>
          <Tag :severity="getStatusMeta(deliveryRequest.status).severity"
            :value="getStatusMeta(deliveryRequest.status).label" class="text-[11px] px-2 py-0.5 rounded-[4px] mt-0.5" />
        </div>

        <div class="col-span-1 md:col-span-3 flex items-start gap-1.5 pt-2 border-t border-slate-200/60">
          <i class="pi pi-map-marker text-rose-500 mt-0.5 text-xs"></i>
          <div>
            <span class="font-semibold text-slate-600">Địa chỉ nhận hàng: </span>
            <span class="font-medium text-slate-800">{{ deliveryRequest.shipping_address || 'Chưa có địa chỉ cụ thể'
              }}</span>
            <span v-if="deliveryRequest.contact_phone" class="text-slate-500 ml-2 font-mono">
              (SĐT: {{ deliveryRequest.contact_phone }})
            </span>
          </div>
        </div>
      </div>

      <!-- 3. Bảng các mã hàng yêu cầu giao -->
      <div class="border border-slate-200 rounded-[6px] overflow-hidden">
        <div class="bg-slate-100 px-3 py-1.5 border-b border-slate-200 flex items-center justify-between text-xs">
          <span class="font-semibold text-slate-700 uppercase tracking-wide">
            Danh Sách Mặt Hàng Yêu Cầu Giao
          </span>
          <span class="text-slate-500 text-[11px]">
            Tổng số: <strong class="text-slate-800">{{ itemsList.length }}</strong> mặt hàng
          </span>
        </div>

        <DataTable :value="itemsList" showGridlines responsiveLayout="scroll" class="p-datatable-sm text-xs">
          <Column header="#" style="width: 45px" class="text-center">
            <template #body="{ index }">
              <span class="font-mono text-slate-400">{{ index + 1 }}</span>
            </template>
          </Column>

          <!-- 1. Mã nội bộ -->
          <Column field="sku" header="Mã Nội Bộ" style="width: 130px">
            <template #body="{ data }">
              <span
                class="font-mono text-xs font-bold text-sky-700 bg-sky-50 px-1.5 py-0.5 rounded border border-sky-200">
                {{ data.sku || data.product_sku }}
              </span>
            </template>
          </Column>

          <!-- 2. Tên sản phẩm -->
          <Column field="product_name" header="Tên Sản Phẩm">
            <template #body="{ data }">
              <span class="text-xs font-semibold text-slate-800">{{ data.product_name }}</span>
            </template>
          </Column>

          <!-- Ngày hẹn giao từ order_item -->
          <Column header="Ngày Hẹn Giao" style="width: 125px" class="text-center">
            <template #body="{ data }">
              <span
                class="font-mono text-xs font-semibold text-sky-700 bg-sky-50 px-2 py-0.5 rounded border border-sky-200">
                {{ data.delivery_date ? formatDate(data.delivery_date) : 'Theo đơn' }}
              </span>
            </template>
          </Column>

          <!-- 3. Số lượng đặt -->
          <Column field="ordered_quantity" header="SL Đặt" style="width: 100px" class="text-center">
            <template #body="{ data }">
              <span class="font-mono text-xs font-bold text-blue-600">
                {{ data.ordered_quantity ?? data.order_quantity ?? data.quantity }}
              </span>
            </template>
          </Column>

          <!-- 4. Số lượng yêu cầu -->
          <Column field="quantity" header="SL Yêu Cầu" style="width: 110px" class="text-center">
            <template #body="{ data }">
              <span class="font-mono text-xs font-bold text-blue-600">
                {{ data.quantity }}
              </span>
            </template>
          </Column>

          <!-- 5. Số đã giao -->
          <Column field="delivered_quantity" header="Đã Giao" style="width: 100px" class="text-center">
            <template #body="{ data }">
              <span class="font-mono text-xs font-bold text-green-600">
                {{ data.delivered_quantity }}
              </span>
            </template>
          </Column>

          <!-- 6. Số chưa giao (tính theo số đã yêu cầu) -->
          <Column field="remaining_quantity" header="Chưa Giao" style="width: 100px" class="text-center">
            <template #body="{ data }">
              <span class="font-mono text-xs font-bold text-red-600">
                {{ data.remaining_quantity }}
              </span>
            </template>
          </Column>
        </DataTable>
      </div>

      <!-- Tóm tắt số lượng -->
      <div
        class="grid grid-cols-3 gap-3 p-2.5 bg-slate-50 border border-slate-200 rounded-[6px] text-xs text-center font-mono">
        <div>
          <span class="text-slate-500 text-[11px] block">Tổng SL yêu cầu</span>
          <span class="font-bold text-indigo-700 text-sm">{{ totalRequested }}</span>
        </div>
        <div>
          <span class="text-slate-500 text-[11px] block">Tổng SL đã giao</span>
          <span class="font-bold text-emerald-700 text-sm">{{ totalDelivered }}</span>
        </div>
        <div>
          <span class="text-slate-500 text-[11px] block">Tổng SL chưa giao</span>
          <span class="font-bold text-amber-700 text-sm">{{ totalRemaining }}</span>
        </div>
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end gap-2">
        <Button label="Đóng" icon="pi pi-times" text size="small" severity="secondary" class="rounded-[6px]"
          @click="$emit('update:visible', false)" />
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import { computed } from 'vue';
import Dialog from 'primevue/dialog';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import { formatDate } from '../../utils/format';

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  deliveryRequest: {
    type: Object,
    default: null
  }
});

defineEmits(['update:visible']);

const getStatusMeta = (st) => {
  const map = {
    PENDING: { label: 'Chờ duyệt', severity: 'warn' },
    APPROVED: { label: 'Đã duyệt (Chờ xuất kho)', severity: 'info' },
    INVOICED: { label: 'Đã lập chứng từ', severity: 'success' },
    DELIVERED: { label: 'Đã giao hàng', severity: 'success' },
    REJECTED: { label: 'Từ chối', severity: 'danger' },
    CANCELLED: { label: 'Đã hủy', severity: 'danger' }
  };
  return map[st] || { label: st, severity: 'secondary' };
};

const itemsList = computed(() => {
  if (!props.deliveryRequest || !props.deliveryRequest.items) return [];
  const isDeliveredOrInvoiced = ['DELIVERED', 'INVOICED'].includes(props.deliveryRequest.status);

  return props.deliveryRequest.items.map((it) => {
    const qty = Number(it.quantity || 0);
    // Nếu status là DELIVERED/INVOICED thì đã giao đủ số lượng yêu cầu này
    let del = Number(it.delivered_quantity || 0);
    if (del === 0 && isDeliveredOrInvoiced) {
      del = qty;
    }
    const rem = Math.max(0, qty - del);

    return {
      ...it,
      sku: it.sku || it.product_sku || '',
      product_name: it.product_name || '',
      ordered_quantity: it.ordered_quantity ?? it.order_quantity ?? qty,
      quantity: qty,
      delivered_quantity: del,
      remaining_quantity: rem
    };
  });
});

const totalRequested = computed(() => itemsList.value.reduce((s, it) => s + it.quantity, 0));
const totalDelivered = computed(() => itemsList.value.reduce((s, it) => s + it.delivered_quantity, 0));
const totalRemaining = computed(() => itemsList.value.reduce((s, it) => s + it.remaining_quantity, 0));
</script>
