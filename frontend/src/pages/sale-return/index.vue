<template>
  <div class="space-y-4">
    <PageHeader title="Phiếu Trả Hàng Bán" subtitle="Các phiếu đã ghi sổ và cộng tồn kho bán" />
    <div class="space-y-2">
      <Toolbar class="!border-slate-200 !rounded-[6px]"><template #start><IconField><InputIcon class="pi pi-search" /><InputText v-model="search" placeholder="Tìm phiếu, yêu cầu, khách hàng..." size="small" /></IconField></template></Toolbar>
      <CommonDataTable
        :value="filteredRows"
        :columns="tableColumns"
        :loading="loading"
        :rows="20"
        :rows-per-page-options="[10, 20, 50]"
        row-hover
        empty-icon="pi pi-file-check"
        empty-title="Chưa có phiếu trả hàng bán"
        empty-subtitle="Không tìm thấy phiếu phù hợp với điều kiện lọc."
        @row-click="event => router.push(`/sale-return/${event.data.id}`)"
      >
        <template #col-return_number="{ data }"><span class="font-mono font-semibold text-emerald-700">{{ data.return_number }}</span></template>
        <template #col-total_amount="{ data }"><span class="font-semibold">{{ money(data.total_amount) }}</span></template>
        <template #col-actions="{ data }"><Button icon="pi pi-eye" text size="small" @click.stop="router.push(`/sale-return/${data.id}`)" /></template>
      </CommonDataTable>
    </div>
  </div>
</template>
<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import Button from 'primevue/button'; import IconField from 'primevue/iconfield'; import InputIcon from 'primevue/inputicon'; import InputText from 'primevue/inputtext'; import Toolbar from 'primevue/toolbar';
import PageHeader from '../../components/common/PageHeader.vue'; import CommonDataTable from '../../components/common/CommonDataTable.vue'; import { salesApi } from '../../api/sales.api';
const router = useRouter(); const rows = ref([]); const search = ref(''); const loading = ref(false);
const tableColumns = [
  { field: 'return_number', header: 'Số phiếu', width: '150px' },
  { field: 'request_number', header: 'Yêu cầu nguồn', width: '160px' },
  { field: 'customer_name', header: 'Khách hàng', width: '200px' },
  { field: 'sales_warehouse_name', header: 'Kho nhập', sortable: false, width: '180px' },
  { field: 'total_amount', header: 'Giá trị nhập', width: '140px', align: 'right' },
  { field: 'return_date', header: 'Ngày lập', width: '120px' },
  { field: 'created_by_name', header: 'Người ghi sổ', sortable: false, width: '160px' },
  { field: 'actions', header: '', sortable: false, width: '60px', align: 'center' }
];
const filteredRows = computed(() => rows.value.filter(row => !search.value || `${row.return_number} ${row.request_number} ${row.customer_name}`.toLowerCase().includes(search.value.toLowerCase())));
const money = value => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value || 0);
onMounted(async () => { loading.value = true; try { rows.value = (await salesApi.getSaleReturns()).data || []; } finally { loading.value = false; } });
</script>
