<template>
  <div class="bg-white border border-slate-200 rounded-[6px] p-4 flex flex-col gap-4">
    <div class="flex items-center justify-between pb-3 border-b border-slate-200">
      <div class="flex items-center gap-2">
        <i class="pi pi-info-circle text-sky-600 text-sm"></i>
        <span class="text-xs font-bold text-slate-800 uppercase tracking-wide">Thông tin chung</span>
      </div>
      <span class="text-xs" :class="allPriced ? 'text-emerald-700 font-semibold' : 'text-slate-500'">
        {{ pricedCount }}/{{ request.items.length }} item đã có giá
      </span>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 text-xs text-slate-700">
      <div v-for="field in fields" :key="field.label" class="flex flex-col gap-1">
        <span class="text-slate-500">{{ field.label }}</span>
        <span class="text-slate-900">{{ field.value }}</span>
      </div>

      <div class="flex flex-col gap-1 md:col-span-2 lg:col-span-4">
        <span class="text-slate-500">Phiếu hỏi hàng nhà cung cấp</span>
        <div class="flex flex-wrap items-center gap-3">
          <span v-if="!request.inquiries?.length" class="text-slate-400">Chưa có phiếu hỏi hàng</span>
          <span v-for="inquiry in request.inquiries" :key="inquiry.id" class="inline-flex items-center gap-1.5">
            <span
              class="font-mono font-semibold text-sky-700"
              :class="{ 'cursor-pointer hover:underline': linkable }"
              @click="linkable && emit('open-inquiry', inquiry.id)"
            >
              {{ inquiry.code }}
            </span>
            <span class="text-slate-500">{{ inquiry.supplier_name }}</span>
            <Tag
              :value="getStatusMeta(SUPPLIER_INQUIRY_STATUS, inquiry.status).label"
              :severity="getStatusMeta(SUPPLIER_INQUIRY_STATUS, inquiry.status).severity"
              class="!text-[10px] !py-0 !rounded-[6px]"
            />
          </span>
        </div>
      </div>

      <div v-if="request.description" class="flex flex-col gap-1 md:col-span-2 lg:col-span-4">
        <span class="text-slate-500">Mô tả</span>
        <span class="text-slate-900 whitespace-pre-line">{{ request.description }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import Tag from 'primevue/tag';
import { formatDate } from '../../utils/format';
import { SUPPLIER_INQUIRY_STATUS, getStatusMeta } from '../../constants/inquiry.constant';

const props = defineProps({
  request: { type: Object, required: true },
  users: { type: Array, default: () => [] },
  // Chỉ phòng mua hàng mới mở được phiếu hỏi hàng NCC
  linkable: Boolean
});

const emit = defineEmits(['open-inquiry']);

const userName = id => props.users.find(u => u.id === id)?.name || id || '—';
const pricedCount = computed(() => props.request.items.filter(item => item.gross_price != null).length);
const allPriced = computed(() => props.request.items.length > 0 && pricedCount.value === props.request.items.length);

const fields = computed(() => [
  { label: 'Khách hàng', value: props.request.customer_name },
  { label: 'Người yêu cầu', value: userName(props.request.requested_by) },
  { label: 'Nhân viên phụ trách', value: userName(props.request.assignee_id) },
  { label: 'Nhân viên theo dõi', value: userName(props.request.watcher_id) },
  { label: 'Thời hạn hỏi hàng', value: formatDate(props.request.due_date) },
  { label: 'Ngày tạo', value: formatDate(props.request.created_at) },
  { label: 'Cập nhật lần cuối', value: formatDate(props.request.updated_at) }
]);
</script>
