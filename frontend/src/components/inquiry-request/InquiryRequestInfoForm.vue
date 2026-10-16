<template>
  <div class="bg-white border border-slate-200 rounded-[6px] p-4 flex flex-col gap-4">
    <div class="flex items-center gap-2 pb-3 border-b border-slate-200">
      <i class="pi pi-info-circle text-sky-600 text-sm"></i>
      <span class="text-xs font-bold text-slate-800 uppercase tracking-wide">Thông tin chung</span>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="flex flex-col gap-1">
        <label class="text-xs font-medium text-slate-700">Khách hàng <span class="text-rose-500">*</span></label>
        <Select
          :modelValue="modelValue.customer_id"
          @update:modelValue="value => update('customer_id', value)"
          :options="customerOptions"
          optionLabel="display"
          optionValue="id"
          placeholder="Chọn khách hàng"
          filter
          size="small"
          class="!rounded-[6px] !text-xs"
        />
      </div>

      <div class="flex flex-col gap-1">
        <label class="text-xs font-medium text-slate-700">Nhân viên phụ trách (Purchase) <span class="text-rose-500">*</span></label>
        <Select
          :modelValue="modelValue.assignee_id"
          @update:modelValue="value => update('assignee_id', value)"
          :options="purchaseUsers"
          optionLabel="name"
          optionValue="id"
          placeholder="Chọn nhân viên"
          filter
          size="small"
          class="!rounded-[6px] !text-xs"
        />
      </div>

      <div class="flex flex-col gap-1">
        <label class="text-xs font-medium text-slate-700">Nhân viên theo dõi</label>
        <Select
          :modelValue="modelValue.watcher_id"
          @update:modelValue="value => update('watcher_id', value)"
          :options="watcherUsers"
          optionLabel="name"
          optionValue="id"
          placeholder="Không chọn"
          showClear
          filter
          size="small"
          class="!rounded-[6px] !text-xs"
        />
      </div>

      <div class="flex flex-col gap-1">
        <label class="text-xs font-medium text-slate-700">Thời hạn hỏi hàng</label>
        <InputText
          :modelValue="modelValue.due_date"
          @update:modelValue="value => update('due_date', value)"
          type="date"
          size="small"
          class="!rounded-[6px] !text-xs"
        />
      </div>

      <div class="flex flex-col gap-1 md:col-span-2 lg:col-span-4">
        <label class="text-xs font-medium text-slate-700">Mô tả</label>
        <Textarea
          :modelValue="modelValue.description"
          @update:modelValue="value => update('description', value)"
          rows="2"
          class="!rounded-[6px] !text-xs"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';

const props = defineProps({
  modelValue: { type: Object, required: true },
  customers: { type: Array, default: () => [] },
  users: { type: Array, default: () => [] }
});

const emit = defineEmits(['update:modelValue']);
const update = (key, value) => emit('update:modelValue', { ...props.modelValue, [key]: value });

const customerOptions = computed(() => props.customers.map(c => ({ ...c, display: `${c.code} · ${c.name}` })));
const purchaseUsers = computed(() => props.users.filter(u => u.department_code === 'PURCHASE' || u.department_id === 3));
const watcherUsers = computed(() => props.users.filter(u =>
  purchaseUsers.value.some(p => p.id === u.id) || u.roles?.some(r => r.code === 'SUPER_ADMIN')
));
</script>
