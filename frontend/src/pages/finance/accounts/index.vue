<template>
  <div class="space-y-4 pb-8">
    <PageHeader title="Hệ Thống Tài Khoản" subtitle="Danh mục tài khoản kế toán theo TT99/2025 và các tiểu khoản quản trị nội bộ" />
    <Toolbar class="!border-slate-200 !rounded-[6px]"><template #start><IconField><InputIcon class="pi pi-search" /><InputText v-model="search" placeholder="Tìm mã hoặc tên tài khoản..." size="small" class="w-80" /></IconField></template></Toolbar>
    <CommonDataTable :value="filteredAccounts" :columns="columns" :loading="loading" :rows="20" empty-title="Chưa có tài khoản kế toán">
      <template #col-code="{ data }"><span class="font-mono font-bold text-sky-700">{{ data.code }}</span></template>
      <template #col-name="{ data }"><span :class="data.is_postable ? '' : 'font-semibold'">{{ data.name }}</span></template>
      <template #col-is_postable="{ data }"><Tag :value="data.is_postable ? 'Được hạch toán' : 'Tài khoản tổng hợp'" :severity="data.is_postable ? 'success' : 'secondary'" /></template>
      <template #col-regime="{ data }"><span class="font-mono text-xs">{{ data.regime }}</span></template>
    </CommonDataTable>
  </div>
</template>
<script setup>
import { computed, onMounted, ref } from 'vue';
import IconField from 'primevue/iconfield'; import InputIcon from 'primevue/inputicon'; import InputText from 'primevue/inputtext'; import Tag from 'primevue/tag'; import Toolbar from 'primevue/toolbar';
import CommonDataTable from '../../../components/common/CommonDataTable.vue'; import PageHeader from '../../../components/common/PageHeader.vue'; import { financeApi } from '../../../api/finance.api';
const accounts = ref([]); const loading = ref(false); const search = ref('');
const columns = [{ field: 'code', header: 'Số hiệu', width: '130px' }, { field: 'name', header: 'Tên tài khoản' }, { field: 'category', header: 'Loại', width: '130px' }, { field: 'normal_balance', header: 'Tính chất', width: '110px' }, { field: 'is_postable', header: 'Hạch toán', width: '160px' }, { field: 'regime', header: 'Chế độ', width: '130px' }];
const filteredAccounts = computed(() => { const value = search.value.trim().toLowerCase(); return value ? accounts.value.filter(item => `${item.code} ${item.name}`.toLowerCase().includes(value)) : accounts.value; });
onMounted(async () => { loading.value = true; try { accounts.value = (await financeApi.getAccounts()).data || []; } finally { loading.value = false; } });
</script>
