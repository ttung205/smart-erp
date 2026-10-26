<template>
  <div class="space-y-4 pb-8">
    <PageHeader title="Phiếu Lương Của Tôi" subtitle="Chỉ hiển thị các kỳ lương đã được duyệt">
      <template #actions>
        <Button label="Làm mới" icon="pi pi-refresh" size="small" severity="secondary" outlined :loading="loading" @click="load" />
      </template>
    </PageHeader>

    <CommonDataTable :value="slips" :columns="columns" :loading="loading" :rows="12" empty-icon="pi pi-wallet" empty-title="Chưa có phiếu lương nào được công bố" row-hover @row-click="event => open(event.data)">
      <template #col-period="{ data }"><span class="font-mono text-xs font-semibold text-sky-700">Tháng {{ label(data.period) }}</span></template>
      <template #col-paid_days="{ data }"><div class="text-right font-mono text-xs">{{ formatDays(data.paid_days) }} / {{ formatDays(data.standard_days) }}</div></template>
      <template #col-gross_income="{ data }"><div class="text-right font-mono text-xs">{{ formatNumber(data.gross_income) }}</div></template>
      <template #col-deductions="{ data }"><div class="text-right font-mono text-xs text-rose-600">{{ formatNumber(deductionsOf(data)) }}</div></template>
      <template #col-net_pay="{ data }"><div class="text-right font-mono text-xs font-bold text-emerald-700">{{ formatNumber(data.net_pay) }}</div></template>
      <template #col-status="{ data }"><Tag :value="PAYROLL_STATUS[data.period.status]?.label" :severity="PAYROLL_STATUS[data.period.status]?.severity" /></template>
    </CommonDataTable>

    <PayrollSlipDialog v-model:visible="dialog.visible" :slip="dialog.slip" :period-label="dialog.label" />
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import CommonDataTable from '../../../components/common/CommonDataTable.vue';
import PageHeader from '../../../components/common/PageHeader.vue';
import PayrollSlipDialog from '../components/PayrollSlipDialog.vue';
import { payrollApi } from '../../../api/attendance.api';
import { formatNumber } from '../../../utils/format';
import { formatDays } from '../constants';
import { PAYROLL_STATUS } from '../attendance-constants';

const toast = useToast();
const slips = ref([]);
const loading = ref(false);
const dialog = reactive({ visible: false, slip: null, label: '' });

const columns = [
  { field: 'period', header: 'Kỳ lương', width: '140px', sortable: false },
  { field: 'paid_days', header: 'Công', width: '110px', align: 'right', sortable: false },
  { field: 'gross_income', header: 'Tổng thu nhập', align: 'right', sortable: false },
  { field: 'deductions', header: 'Khấu trừ', align: 'right', sortable: false },
  { field: 'net_pay', header: 'Thực nhận', align: 'right', sortable: false },
  { field: 'status', header: 'Trạng thái', width: '130px', align: 'center', sortable: false }
];

const label = period => `${String(period.month).padStart(2, '0')}/${period.year}`;
const deductionsOf = slip => Number(slip.insurance_amount) + Number(slip.pit_amount) + Number(slip.penalty_amount) + Number(slip.deduction_amount);

const load = async () => {
  loading.value = true;
  try {
    slips.value = (await payrollApi.getMySlips()).data || [];
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Không tải được phiếu lương', detail: error.message, life: 4000 });
  } finally {
    loading.value = false;
  }
};

const open = slip => Object.assign(dialog, { visible: true, slip, label: label(slip.period) });

onMounted(load);
</script>
