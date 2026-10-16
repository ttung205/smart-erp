<template>
  <div class="bg-white border border-slate-200 rounded-[6px] p-4 flex flex-col gap-4">
    <div class="flex items-center gap-2 pb-3 border-b border-slate-200">
      <i class="pi pi-info-circle text-sky-600 text-sm"></i>
      <span class="text-xs font-bold text-slate-800 uppercase tracking-wide">Thông tin phiếu hỏi hàng</span>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 text-xs">
      <div class="flex flex-col gap-1">
        <label class="font-medium text-slate-700">Nhà cung cấp <span class="text-rose-500">*</span></label>
        <Select
          :modelValue="supplierId"
          @update:modelValue="value => emit('update:supplierId', value)"
          :options="supplierOptions"
          optionLabel="display"
          optionValue="id"
          placeholder="Chọn nhà cung cấp"
          filter
          size="small"
          class="!rounded-[6px] !text-xs"
          :disabled="disabled"
        />
      </div>

      <div class="flex flex-col gap-1">
        <span class="text-slate-500">Yêu cầu hỏi hàng</span>
        <span
          v-if="request"
          class="font-mono font-semibold text-sky-700 cursor-pointer hover:underline w-fit"
          @click="emit('open-request')"
        >
          {{ request.code }}
        </span>
        <span v-else class="text-slate-400">—</span>
      </div>

      <div class="flex flex-col gap-1">
        <span class="text-slate-500">Khách hàng</span>
        <span class="text-slate-900">{{ request?.customer_name || '—' }}</span>
      </div>

      <div class="flex flex-col gap-1">
        <span class="text-slate-500">Thời hạn hỏi hàng</span>
        <span class="text-slate-900">{{ formatDate(request?.due_date) }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import Select from 'primevue/select';
import { formatDate } from '../../utils/format';

const props = defineProps({
  supplierId: { type: Number, default: null },
  suppliers: { type: Array, default: () => [] },
  request: { type: Object, default: null },
  disabled: Boolean
});

const emit = defineEmits(['update:supplierId', 'open-request']);

const supplierOptions = computed(() => props.suppliers.map(s => ({ ...s, display: `${s.code} · ${s.name}` })));
</script>
