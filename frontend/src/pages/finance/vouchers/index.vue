<template>
  <div class="space-y-4 pb-8">
    <PageHeader :title="title" :subtitle="subtitle"><template #actions><Button :label="createLabel" icon="pi pi-plus" size="small" @click="router.push({ path: '/finance/vouchers/new', query: { type: voucherType } })" /></template></PageHeader>
    <CommonDataTable :value="vouchers" :columns="columns" :loading="loading" :rows="20" empty-icon="pi pi-file" empty-title="Chưa có chứng từ">
      <template #col-voucher_number="{ data }"><span class="font-mono font-semibold text-sky-700">{{ data.voucher_number }}</span></template>
      <template #col-voucher_date="{ data }">{{ formatDate(data.voucher_date) }}</template>
      <template #col-total_amount="{ data }"><div class="text-right font-mono font-bold">{{ formatCurrency(data.total_amount) }}</div></template>
      <template #col-status="{ data }"><Tag :value="data.status === 'POSTED' ? 'Đã ghi sổ' : 'Bản nháp'" :severity="data.status === 'POSTED' ? 'success' : 'warn'" /></template>
    </CommonDataTable>
  </div>
</template>
<script setup>
import { computed, onMounted, ref, watch } from 'vue'; import { useRoute, useRouter } from 'vue-router'; import Button from 'primevue/button'; import Tag from 'primevue/tag';
import CommonDataTable from '../../../components/common/CommonDataTable.vue'; import PageHeader from '../../../components/common/PageHeader.vue'; import { financeApi } from '../../../api/finance.api'; import { formatCurrency, formatDate } from '../../../utils/format';
const route = useRoute(); const router = useRouter(); const vouchers = ref([]); const loading = ref(false); const voucherType = computed(() => route.meta.voucherType);
const labels = { CASH_RECEIPT: ['Phiếu Thu Tiền Mặt', 'Quản lý các khoản thu bằng tiền mặt', 'Lập phiếu thu'], CASH_PAYMENT: ['Phiếu Chi Tiền Mặt', 'Quản lý các khoản chi bằng tiền mặt', 'Lập phiếu chi'], BANK_RECEIPT: ['Thu Tiền Qua Ngân Hàng', 'Quản lý tiền thu vào tài khoản ngân hàng', 'Lập chứng từ thu'], BANK_PAYMENT: ['Chi Tiền Qua Ngân Hàng', 'Quản lý tiền chi từ tài khoản ngân hàng', 'Lập chứng từ chi'], INTERNAL_TRANSFER: ['Chuyển Tiền Nội Bộ', 'Điều chuyển giữa các tài khoản tiền', 'Lập lệnh chuyển'] };
const title = computed(() => labels[voucherType.value]?.[0]); const subtitle = computed(() => labels[voucherType.value]?.[1]); const createLabel = computed(() => labels[voucherType.value]?.[2]);
const columns = [{ field: 'voucher_date', header: 'Ngày', width: '120px' }, { field: 'voucher_number', header: 'Số chứng từ', width: '170px' }, { field: 'party_name', header: 'Đối tượng', width: '240px' }, { field: 'description', header: 'Diễn giải' }, { field: 'total_amount', header: 'Số tiền', width: '170px', align: 'right' }, { field: 'status', header: 'Trạng thái', width: '130px', align: 'center' }];
const load = async () => { loading.value = true; try { vouchers.value = (await financeApi.getVouchers({ voucher_type: voucherType.value })).data || []; } finally { loading.value = false; } }; onMounted(load); watch(voucherType, load);
</script>
