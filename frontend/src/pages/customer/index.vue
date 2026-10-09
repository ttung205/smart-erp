<template>
  <div class="space-y-4 pb-12">
    <!-- Header -->
    <PageHeader title="Quản Lý Khách Hàng (Customer Management)"
      subtitle="Danh mục khách hàng B2B, loại tài khoản MA/EA/TA/RA, quản lý người liên hệ và đối tác kinh doanh"
      badge="Phân Hệ Đối Tác" badge-severity="info">
      <template #actions>
        <Button label="Thêm Khách Hàng Mới" icon="pi pi-plus" size="small"
          class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 !text-xs !py-2 !px-3.5 rounded-[6px] !font-semibold shadow-sm"
          @click="$router.push('/customer/create')" />
      </template>
    </PageHeader>

    <!-- Toolbar Filters -->
    <Toolbar class="bg-white border border-slate-200 rounded-[8px] px-4 py-3 shadow-none">
      <template #start>
        <div class="flex items-center gap-3">
          <IconField>
            <InputIcon class="pi pi-search text-slate-400" />
            <InputText v-model="searchQuery" placeholder="Tìm theo mã, tên khách, MST, người tạo..."
              class="w-80 rounded-[6px] text-xs h-9" />
          </IconField>

          <Select v-model="typeFilter" :options="typeFilterOptions" optionLabel="label" optionValue="value"
            placeholder="Lọc loại khách hàng..." class="w-52 rounded-[6px] text-xs h-9 flex items-center" />

          <Select v-model="statusFilter" :options="statusOptions" optionLabel="label" optionValue="value"
            placeholder="Lọc trạng thái..." class="w-44 rounded-[6px] text-xs h-9 flex items-center" />
        </div>
      </template>

      <template #end>
        <div class="flex items-center gap-2 text-xs text-slate-500 font-medium">
          <span>Tổng số: <strong class="text-slate-800">{{ filteredCustomers.length }}</strong> khách hàng</span>
        </div>
      </template>
    </Toolbar>

    <!-- Data Table using CommonDataTable -->
    <CommonDataTable :value="filteredCustomers" :columns="tableColumns" :loading="loading" :rows="10"
      :rowsPerPageOptions="[10, 20, 50]" emptyIcon="pi pi-users" emptyTitle="Chưa có khách hàng nào"
      emptySubtitle="Nhấn nút 'Thêm Khách Hàng Mới' để tạo hồ sơ khách hàng doanh nghiệp.">
      <!-- 1. Mã khách hàng -->
      <template #col-code="{ data }">
        <span
          class="font-mono text-xs font-bold text-sky-700 bg-sky-50 px-2.5 py-1 rounded-[4px] border border-sky-200 cursor-pointer hover:underline"
          @click="$router.push(`/customer/${data.id}/edit`)">
          {{ data.code }}
        </span>
      </template>

      <!-- 2. Tên khách hàng -->
      <template #col-name="{ data }">
        <span class="font-bold text-slate-800 text-xs hover:text-sky-700 cursor-pointer"
          @click="$router.push(`/customer/${data.id}/edit`)">
          {{ data.name }}
        </span>
      </template>

      <!-- 3. MST -->
      <template #col-tax_code="{ data }">
        <span v-if="data.tax_code" class="font-mono text-xs text-slate-700">
          {{ data.tax_code }}
        </span>
        <span v-else class="text-slate-400 italic text-[11px]">-</span>
      </template>

      <!-- 4. Loại khách hàng -->
      <template #col-customer_type="{ data }">
        <Badge :value="getCustomerTypeLabel(data.customer_type)"
          :severity="getCustomerTypeSeverity(data.customer_type)" />
      </template>

      <!-- 5. Người tạo -->
      <template #col-created_by="{ data }">
        <div class="flex items-center gap-1.5 text-xs text-slate-700">
          <i class="pi pi-user text-slate-400 text-[11px]"></i>
          <span class="font-medium">{{ data.created_by || 'admin' }}</span>
        </div>
      </template>

      <!-- 6. Liên hệ (Chỉ hiển thị số count, click mở Dialog) -->
      <template #col-contacts_count="{ data }">
        <div class="text-center">
          <button type="button"
            class="inline-flex items-center gap-1.5 font-bold font-mono text-xs text-sky-700 bg-sky-50 hover:bg-sky-100 px-2.5 py-1 rounded-[6px] border border-sky-200 transition-colors cursor-pointer"
            title="Nhấn để xem danh sách người liên hệ" @click="openContactsDialog(data)">
            <i class="pi pi-users text-xs text-sky-600"></i>
            <span>{{ data.contacts?.length || 0 }}</span>
            <span class="text-[10px] text-slate-500 font-sans font-normal">(Xem)</span>
          </button>
        </div>
      </template>

      <!-- 7. Trạng thái khách -->
      <template #col-status="{ data }">
        <div class="text-center">
          <Badge :severity="data.status === 'ACTIVE' ? 'success' : 'danger'"
            :value="data.status === 'ACTIVE' ? 'Hoạt động' : 'Tạm khóa'" />
        </div>
      </template>

      <!-- 8. Thao tác -->
      <Column header="Thao Tác" style="width: 100px" align="center">
        <template #body="{ data }">
          <div class="flex items-center justify-center gap-1.5">
            <Button icon="pi pi-pencil" outlined size="small" severity="info" class="!w-7 !h-7 !p-0 rounded-[6px]"
              v-tooltip.bottom="'Chỉnh sửa thông tin khách hàng'" @click="$router.push(`/customer/${data.id}/edit`)" />
            <Button icon="pi pi-trash" outlined size="small" severity="danger" class="!w-7 !h-7 !p-0 rounded-[6px]"
              v-tooltip.bottom="'Xóa khách hàng'" @click="confirmDeleteCustomer(data)" />
          </div>
        </template>
      </Column>
    </CommonDataTable>

    <!-- Modal: Xem danh sách người liên hệ (Contacts) -->
    <CustomerContactsDialog
      v-model:visible="showContactsDialog"
      :customer="selectedCustomer"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';

import Column from 'primevue/column';
import Toolbar from 'primevue/toolbar';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Button from 'primevue/button';
import Badge from 'primevue/badge';

import PageHeader from '../../components/common/PageHeader.vue';
import CommonDataTable from '../../components/common/CommonDataTable.vue';
import CustomerContactsDialog from '../../components/customer/CustomerContactsDialog.vue';
import { catalogApi } from '../../api/catalog.api';

const router = useRouter();
const toast = useToast();

const tableColumns = [
  { field: 'code', header: 'Mã Khách Hàng', sortable: true, width: '140px' },
  { field: 'name', header: 'Tên Khách Hàng', sortable: true },
  { field: 'tax_code', header: 'MST', sortable: true, width: '140px' },
  { field: 'customer_type', header: 'Loại Khách Hàng', sortable: true, width: '160px' },
  { field: 'created_by', header: 'Người Tạo', sortable: true, width: '140px' },
  { field: 'contacts_count', header: 'Liên Hệ', sortable: false, width: '110px', align: 'center' },
  { field: 'status', header: 'Trạng Thái', sortable: true, width: '120px', align: 'center' }
];

const customers = ref([]);
const loading = ref(false);
const searchQuery = ref('');
const statusFilter = ref(null);
const typeFilter = ref(null);

const showContactsDialog = ref(false);
const selectedCustomer = ref(null);

const statusOptions = [
  { label: 'Tất cả trạng thái', value: null },
  { label: 'Đang hoạt động', value: 'ACTIVE' },
  { label: 'Tạm khóa', value: 'INACTIVE' }
];

const typeFilterOptions = [
  { label: 'Tất cả loại khách hàng', value: null },
  { label: 'Retail (RE) - Mua lẻ / người tiêu dùng', value: 'Retail' },
  { label: 'Dealer (DE) - Đại lý / cửa hàng bán lại', value: 'Dealer' },
  { label: 'Distributor (DI) - Nhà phân phối', value: 'Distributor' },
  { label: 'Project / Business (PR) - Dự án / doanh nghiệp', value: 'Project / Business' }
];

const loadCustomers = async () => {
  loading.value = true;
  try {
    const res = await catalogApi.getCustomers();
    customers.value = res.data || [];
  } catch (err) {
    console.error('Lỗi tải danh sách khách hàng:', err);
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  loadCustomers();
});

const filteredCustomers = computed(() => {
  return customers.value.filter((c) => {
    const q = searchQuery.value.toLowerCase().trim();
    const matchSearch =
      !q ||
      c.code.toLowerCase().includes(q) ||
      c.name.toLowerCase().includes(q) ||
      (c.tax_code && c.tax_code.toLowerCase().includes(q)) ||
      (c.created_by && c.created_by.toLowerCase().includes(q));

    const matchStatus = !statusFilter.value || c.status === statusFilter.value;
    const matchType = !typeFilter.value || c.customer_type === typeFilter.value;
    return matchSearch && matchStatus && matchType;
  });
});

const getCustomerTypeLabel = (type) => {
  switch (type) {
    case 'Retail':
    case 'RE':
      return 'Retail (RE)';
    case 'Dealer':
    case 'DE':
      return 'Dealer (DE)';
    case 'Distributor':
    case 'DI':
      return 'Distributor (DI)';
    case 'Project / Business':
    case 'PR':
      return 'Project/Business (PR)';
    default:
      return type || 'Retail (RE)';
  }
};

const getCustomerTypeSeverity = (type) => {
  switch (type) {
    case 'Retail':
    case 'RE':
      return 'secondary';
    case 'Dealer':
    case 'DE':
      return 'info';
    case 'Distributor':
    case 'DI':
      return 'warn';
    case 'Project / Business':
    case 'PR':
      return 'success';
    default:
      return 'secondary';
  }
};

const confirmDeleteCustomer = async (cust) => {
  if (!confirm(`Bạn có chắc chắn muốn xóa khách hàng ${cust.code} - ${cust.name}?`)) {
    return;
  }

  try {
    await catalogApi.deleteCustomer(cust.id);
    toast.add({
      severity: 'success',
      summary: 'Đã xóa khách hàng',
      detail: `Đã xóa thành công ${cust.code}.`,
      life: 3000
    });
    loadCustomers();
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi xóa khách hàng',
      detail: err.message || 'Không thể xóa khách hàng này.',
      life: 4000
    });
  }
};

// Mở Dialog xem danh sách liên hệ khi click vào số count
const openContactsDialog = (cust) => {
  selectedCustomer.value = cust;
  showContactsDialog.value = true;
};
</script>
