<template>
  <div class="space-y-4">
    <PageHeader title="Phiếu Trả Hàng Bán" subtitle="Các phiếu đã ghi sổ và cộng tồn kho bán" />
    <div class="bg-white border border-slate-200 rounded-lg shadow-sm">
      <Toolbar class="!border-0 !border-b !border-slate-200"><template #start><IconField><InputIcon class="pi pi-search" /><InputText v-model="search" placeholder="Tìm phiếu, yêu cầu, khách hàng..." size="small" /></IconField></template></Toolbar>
      <DataTable :value="filteredRows" :loading="loading" paginator :rows="20" striped-rows row-hover @row-click="event => router.push(`/sale-return/${event.data.id}`)">
        <Column field="return_number" header="Số phiếu" sortable><template #body="{ data }"><span class="font-mono font-semibold text-emerald-700">{{ data.return_number }}</span></template></Column>
        <Column field="request_number" header="Yêu cầu nguồn" sortable />
        <Column field="customer_name" header="Khách hàng" sortable />
        <Column field="sales_warehouse_name" header="Kho nhập" />
        <Column field="total_amount" header="Giá trị nhập"><template #body="{ data }">{{ money(data.total_amount) }}</template></Column>
        <Column field="return_date" header="Ngày lập" sortable />
        <Column field="created_by_name" header="Người ghi sổ" />
        <Column header=""><template #body="{ data }"><Button icon="pi pi-eye" text size="small" @click.stop="router.push(`/sale-return/${data.id}`)" /></template></Column>
      </DataTable>
    </div>
  </div>
</template>
<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import Button from 'primevue/button'; import Column from 'primevue/column'; import DataTable from 'primevue/datatable'; import IconField from 'primevue/iconfield'; import InputIcon from 'primevue/inputicon'; import InputText from 'primevue/inputtext'; import Toolbar from 'primevue/toolbar';
import PageHeader from '../../components/common/PageHeader.vue'; import { salesApi } from '../../api/sales.api';
const router = useRouter(); const rows = ref([]); const search = ref(''); const loading = ref(false);
const filteredRows = computed(() => rows.value.filter(row => !search.value || `${row.return_number} ${row.request_number} ${row.customer_name}`.toLowerCase().includes(search.value.toLowerCase())));
const money = value => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value || 0);
onMounted(async () => { loading.value = true; try { rows.value = (await salesApi.getSaleReturns()).data || []; } finally { loading.value = false; } });
</script>
