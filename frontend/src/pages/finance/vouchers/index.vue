<template>
  <div class="space-y-4 pb-8">
    <PageHeader :title="title" :subtitle="subtitle">
      <template #actions>
        <Button
          v-if="canCreate"
          :label="createLabel"
          icon="pi pi-plus"
          size="small"
          @click="router.push({ path: '/finance/vouchers/new', query: { type: voucherType } })"
        />
      </template>
    </PageHeader>

    <CommonDataTable
      :value="vouchers"
      :columns="columns"
      :loading="loading"
      :rows="rowsPerPage"
      :first="(page - 1) * rowsPerPage"
      :total-records="totalRecords"
      lazy
      empty-icon="pi pi-file"
      @page="changePage"
      empty-title="Chưa có chứng từ"
    >
      <template #col-voucher_number="{ data }">
        <span class="font-mono font-semibold text-sky-700">{{ data.voucher_number }}</span>
      </template>
      <template #col-voucher_date="{ data }">{{ formatDate(data.voucher_date) }}</template>
      <template #col-total_amount="{ data }">
        <div class="text-right font-mono font-bold">{{ formatVoucherAmount(data) }}</div>
      </template>
      <template #col-status="{ data }">
        <Tag
          :value="data.status === 'POSTED' ? 'Đã ghi sổ' : 'Bản nháp'"
          :severity="data.status === 'POSTED' ? 'success' : 'warn'"
        />
      </template>

      <Column header="Thao tác" class="text-center" style="width: 130px; min-width: 130px">
        <template #body="{ data }">
          <Button
            v-if="data.status === 'DRAFT' && canPost"
            label="Ghi sổ"
            icon="pi pi-check-circle"
            size="small"
            severity="success"
            :loading="postingId === data.id"
            :disabled="postingId !== null && postingId !== data.id"
            @click.stop="postVoucher(data)"
          />
          <span v-else-if="data.status === 'POSTED'" class="text-xs text-emerald-600 font-semibold">
            <i class="pi pi-check-circle mr-1"></i>Hoàn tất
          </span>
          <span v-else class="text-xs text-slate-400">—</span>
        </template>
      </Column>
    </CommonDataTable>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import CommonDataTable from '../../../components/common/CommonDataTable.vue';
import PageHeader from '../../../components/common/PageHeader.vue';
import { financeApi } from '../../../api/finance.api';
import { formatDate } from '../../../utils/format';
import { storage } from '../../../utils/storage';
import { parseJwt } from '../../../utils/jwt';
import { hasPermission } from '../../../utils/rbac';

const route = useRoute();
const router = useRouter();
const toast = useToast();
const currentUser = parseJwt(storage.getToken());
const vouchers = ref([]);
const loading = ref(false);
const postingId = ref(null);
const page = ref(1);
const rowsPerPage = ref(20);
const totalRecords = ref(0);
const voucherType = computed(() => route.meta.voucherType);
const canCreate = computed(() => hasPermission('finance.create', currentUser));
const canPost = computed(() => hasPermission('finance.post', currentUser) || hasPermission('finance.approve', currentUser));

const labels = {
  CASH_RECEIPT: ['Phiếu Thu Tiền Mặt', 'Tạo phiếu nháp, kiểm tra và ghi sổ các khoản thu bằng tiền mặt', 'Lập phiếu thu'],
  CASH_PAYMENT: ['Phiếu Chi Tiền Mặt', 'Tạo phiếu nháp, kiểm tra và ghi sổ các khoản chi bằng tiền mặt', 'Lập phiếu chi'],
  BANK_RECEIPT: ['Thu Tiền Qua Ngân Hàng', 'Tạo chứng từ nháp, kiểm tra và ghi sổ tiền thu qua ngân hàng', 'Lập chứng từ thu'],
  BANK_PAYMENT: ['Chi Tiền Qua Ngân Hàng', 'Tạo chứng từ nháp, kiểm tra và ghi sổ tiền chi qua ngân hàng', 'Lập chứng từ chi'],
  INTERNAL_TRANSFER: ['Chuyển Tiền Nội Bộ', 'Tạo lệnh nháp, kiểm tra và ghi sổ điều chuyển nội bộ', 'Lập lệnh chuyển']
};
const title = computed(() => labels[voucherType.value]?.[0]);
const subtitle = computed(() => labels[voucherType.value]?.[1]);
const createLabel = computed(() => labels[voucherType.value]?.[2]);
const columns = [
  { field: 'voucher_date', header: 'Ngày', width: '120px' },
  { field: 'voucher_number', header: 'Số chứng từ', width: '170px' },
  { field: 'party_name', header: 'Đối tượng', width: '240px' },
  { field: 'description', header: 'Diễn giải' },
  { field: 'total_amount', header: 'Số tiền', width: '170px', align: 'right' },
  { field: 'status', header: 'Trạng thái', width: '130px', align: 'center' }
];

const formatVoucherAmount = voucher => new Intl.NumberFormat('vi-VN', {
  style: 'currency',
  currency: voucher.currency || 'VND',
  maximumFractionDigits: 2
}).format(Number(voucher.total_amount || 0));

const load = async () => {
  loading.value = true;
  try {
    const result = await financeApi.getVouchers({
      voucher_type: voucherType.value,
      page: page.value,
      per_page: rowsPerPage.value
    });
    vouchers.value = result.data || [];
    totalRecords.value = result.meta?.total || 0;
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Không tải được chứng từ', detail: error.message, life: 4000 });
  } finally {
    loading.value = false;
  }
};

const changePage = event => {
  page.value = event.page + 1;
  rowsPerPage.value = event.rows;
  load();
};

const postVoucher = async voucher => {
  postingId.value = voucher.id;
  try {
    const response = await financeApi.postVoucher(voucher.id);
    const index = vouchers.value.findIndex(item => item.id === voucher.id);
    if (index >= 0) vouchers.value[index] = response.data;
    toast.add({ severity: 'success', summary: 'Đã ghi sổ', detail: `Chứng từ ${voucher.voucher_number} đã được ghi sổ.`, life: 3000 });
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Không thể ghi sổ', detail: error.message, life: 4000 });
  } finally {
    postingId.value = null;
  }
};

onMounted(load);
watch(voucherType, () => {
  page.value = 1;
  load();
});
</script>
