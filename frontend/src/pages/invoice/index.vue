<template>
  <div class="space-y-3 pb-8">
    <PageHeader
      title="Chứng Từ Bán Hàng (Sales Invoices)"
      subtitle="Quản lý danh sách chứng từ bán hàng lập từ yêu cầu giao, theo dõi tiến độ gửi xuống kho và đối soát kế toán"
      badge="Stage 4: Sales Invoices"
      badge-severity="info"
    />

    <!-- Toolbar Filter -->
    <Toolbar class="bg-white border border-slate-200 rounded-[6px] px-3 py-2 shadow-none">
      <template #start>
        <div class="flex items-center gap-2">
          <IconField>
            <InputIcon class="pi pi-search" />
            <InputText
              v-model="searchQuery"
              placeholder="Tìm theo số chứng từ, đơn bán, khách hàng..."
              class="w-80 rounded-[6px]"
              size="small"
            />
          </IconField>
          <Select
            v-model="warehouseStatusFilter"
            :options="statusOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Lọc tiến độ kho..."
            class="w-56 rounded-[6px]"
            size="small"
          />
        </div>
      </template>

      <template #end>
        <div class="flex items-center gap-2">
          <Button
            label="Trang Yêu Cầu Xuất Kho ➔"
            icon="pi pi-dolly"
            size="small"
            class="!text-xs !py-1 !px-2.5 rounded-[6px] !bg-indigo-50 hover:!bg-indigo-100 !text-indigo-700 !border-indigo-200 !font-semibold"
            @click="$router.push('/warehouse-request-sale')"
          />
          <span class="text-xs text-slate-500">
            Tổng số: <strong class="text-slate-800">{{ invoices.length }}</strong> chứng từ bán
          </span>
        </div>
      </template>
    </Toolbar>

    <!-- Data Table using CommonDataTable -->
    <CommonDataTable
      :value="filteredInvoices"
      :columns="tableColumns"
      :rows="8"
      :loading="loading"
      :paginator="filteredInvoices.length > 8"
      emptyIcon="pi pi-file"
      emptyTitle="Chưa có chứng từ bán hàng nào"
      emptySubtitle="Lập chứng từ bán từ trang Yêu Cầu Giao Hàng để bắt đầu quy trình xuất kho."
    >
      <!-- Custom column: invoice_number -->
      <template #col-invoice_number="{ data }">
        <router-link
          :to="`/invoice/${data.id}`"
          class="font-mono text-xs font-bold text-indigo-700 hover:text-indigo-900 bg-indigo-50 hover:bg-indigo-100 px-2 py-0.5 rounded-[4px] border border-indigo-200 inline-block transition-colors"
        >
          {{ data.invoice_number }}
        </router-link>
      </template>

      <!-- Custom column: invoice_date -->
      <template #col-invoice_date="{ data }">
        <span class="font-mono text-xs text-slate-600">{{ formatDate(data.invoice_date || data.created_at) }}</span>
      </template>

      <!-- Custom column: order_id -->
      <template #col-order_id="{ data }">
        <div class="flex flex-col text-xs leading-tight">
          <span class="font-mono font-semibold text-slate-800">{{ data.customer_order_number || data.order_number || data.order_id }}</span>
          <span class="font-mono text-slate-400 text-[10px]">YC: #{{ data.delivery_request_id || data.delivery_request_number || '---' }}</span>
        </div>
      </template>

      <!-- Custom column: customer_name -->
      <template #col-customer_name="{ data }">
        <span class="font-semibold text-slate-800 text-xs truncate max-w-xs block" :title="data.customer_name">
          {{ data.customer_name }}
        </span>
      </template>

      <!-- Custom column: warehouse_name -->
      <template #col-warehouse_name="{ data }">
        <span class="text-slate-600 text-xs">{{ data.warehouse_name || 'Kho Tổng Miền Bắc' }}</span>
      </template>

      <!-- Custom column: total_amount -->
      <template #col-total_amount="{ data }">
        <div class="text-right">
          <span class="font-mono text-xs font-bold text-slate-900 num-tabular">
            {{ formatCurrency(data.total_amount) }} {{ data.currency || 'VND' }}
          </span>
        </div>
      </template>

      <!-- Custom column: warehouse_status -->
      <template #col-warehouse_status="{ data }">
        <div class="text-center">
          <Badge
            :severity="getWarehouseStatusMeta(data.warehouse_status).severity"
            :value="getWarehouseStatusMeta(data.warehouse_status).label"
          />
        </div>
      </template>

      <!-- Custom column: accounting_status -->
      <template #col-accounting_status="{ data }">
        <div class="text-center">
          <Badge
            v-if="data.accounting_status === 'POSTED'"
            severity="success"
            value="Đã ghi sổ"
          />
          <Badge
            v-else
            severity="secondary"
            value="Chưa ghi sổ"
          />
        </div>
      </template>

      <!-- Column Thao Tác -->
      <Column header="Thao Tác" class="text-center" style="width: 100px">
        <template #body="{ data }">
          <div class="flex items-center justify-center gap-1.5">
            <!-- Xem / Chỉnh sửa trên trang riêng -->
            <Button
              icon="pi pi-pencil"
              outlined
              size="small"
              severity="info"
              class="!w-7 !h-7 !p-0 rounded-[6px]"
              v-tooltip.bottom="'Xem / Chỉnh sửa chứng từ'"
              @click="$router.push(`/invoice/${data.id}`)"
            />

            <!-- Nút: Gửi xuống kho (nếu chưa gửi) -->
            <Button
              v-if="data.warehouse_status === 'PENDING_WAREHOUSE'"
              icon="pi pi-send"
              outlined
              size="small"
              severity="primary"
              class="!w-7 !h-7 !p-0 rounded-[6px]"
              v-tooltip.bottom="'Chuyển lệnh xuất kho xuống phòng Logistics'"
              @click="sendToWarehouse(data)"
            />

            <!-- Nút: Lập Hóa Đơn VAT khi đã ghi sổ xuất kho -->
            <Button
              v-if="data.accounting_status === 'POSTED'"
              icon="pi pi-receipt"
              outlined
              size="small"
              severity="success"
              class="!w-7 !h-7 !p-0 rounded-[6px]"
              v-tooltip.bottom="'Lập hóa đơn GTGT (VAT Invoice)'"
              @click="openVatInvoiceDialog(data)"
            />

            <!-- Nút: Xem tiến độ tại kho Logistics (nếu chưa ghi sổ) -->
            <Button
              v-else-if="data.warehouse_status !== 'PENDING_WAREHOUSE'"
              icon="pi pi-truck"
              outlined
              size="small"
              severity="secondary"
              class="!w-7 !h-7 !p-0 rounded-[6px]"
              v-tooltip.bottom="'Xem tiến độ nhặt hàng & xuất kho tại phòng Logistics'"
              @click="$router.push('/warehouse-request-sale')"
            />
          </div>
        </template>
      </Column>
    </CommonDataTable>

    <!-- Dialog Lập Hóa Đơn GTGT (VAT Invoice) -->
    <CreateVatInvoiceDialog
      v-model:visible="showVatDialog"
      :sales-invoice="selectedInvoiceForVat"
      @created="loadInvoices"
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
import CreateVatInvoiceDialog from '../../components/invoice/CreateVatInvoiceDialog.vue';

import { salesApi } from '../../api/sales.api';
import { formatCurrency, formatDate } from '../../utils/format';

const router = useRouter();
const toast = useToast();

const invoices = ref([]);
const loading = ref(false);
const searchQuery = ref('');
const warehouseStatusFilter = ref(null);

const showVatDialog = ref(false);
const selectedInvoiceForVat = ref(null);

const openVatInvoiceDialog = (inv) => {
  selectedInvoiceForVat.value = inv;
  showVatDialog.value = true;
};

const tableColumns = [
  { field: 'invoice_number', header: 'Số Chứng Từ (INV)', sortable: true, width: '150px' },
  { field: 'invoice_date', header: 'Ngày Lập', sortable: true, width: '110px' },
  { field: 'order_id', header: 'Đơn Bán / Lệnh Giao', sortable: false, width: '160px' },
  { field: 'customer_name', header: 'Khách Hàng', sortable: true },
  { field: 'warehouse_name', header: 'Kho Xuất', sortable: false, width: '150px' },
  { field: 'total_amount', header: 'Tổng Giá Trị', sortable: true, width: '140px', align: 'right' },
  { field: 'warehouse_status', header: 'Tiến Độ Kho', sortable: true, width: '130px', align: 'center' },
  { field: 'accounting_status', header: 'Ghi Sổ KT', sortable: true, width: '120px', align: 'center' }
];

const statusOptions = [
  { label: 'Tất cả tiến độ', value: null },
  { label: 'Chưa gửi kho', value: 'PENDING_WAREHOUSE' },
  { label: 'Chờ xử lý', value: 'SENT_TO_WAREHOUSE' },
  { label: 'Đã xử lý', value: 'PROCESSED' },
  { label: 'Đang soạn', value: 'PICKING' },
  { label: 'Đã đóng gói', value: 'PACKED' },
  { label: 'Đang giao', value: 'DISPATCHED' },
  { label: 'Đã xuất kho', value: 'DELIVERED' }
];

const getWarehouseStatusMeta = (st) => {
  const map = {
    PENDING_WAREHOUSE: { label: 'Chưa gửi kho', severity: 'secondary' },
    SENT_TO_WAREHOUSE: { label: 'Chờ xử lý', severity: 'warn' },
    PROCESSED: { label: 'Đã xử lý', severity: 'info' },
    PICKING: { label: 'Đang soạn', severity: 'warn' },
    PACKED: { label: 'Đã đóng gói', severity: 'secondary' },
    DISPATCHED: { label: 'Đang giao', severity: 'warn' },
    DELIVERED: { label: 'Đã xuất kho', severity: 'success' }
  };
  return map[st] || { label: st || 'Chờ gửi kho', severity: 'secondary' };
};

const loadInvoices = async () => {
  loading.value = true;
  try {
    const res = await salesApi.getInvoices();
    invoices.value = res.data || [];
  } catch (err) {
    console.error('Lỗi tải danh sách chứng từ:', err);
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  loadInvoices();
});

const filteredInvoices = computed(() => {
  return invoices.value.filter((inv) => {
    const invNum = String(inv.invoice_number || '').toLowerCase();
    const cName = String(inv.customer_name || '').toLowerCase();
    const oId = String(inv.customer_order_number || inv.order_number || inv.order_id || '').toLowerCase();
    const q = searchQuery.value.toLowerCase().trim();

    const matchSearch =
      !q ||
      invNum.includes(q) ||
      cName.includes(q) ||
      oId.includes(q);

    const matchStatus = !warehouseStatusFilter.value || inv.warehouse_status === warehouseStatusFilter.value;
    return matchSearch && matchStatus;
  });
});

const sendToWarehouse = async (inv) => {
  try {
    await salesApi.sendInvoiceToWarehouse(inv.id);
    inv.warehouse_status = 'SENT_TO_WAREHOUSE';

    toast.add({
      severity: 'success',
      summary: 'Đã gửi xuống kho',
      detail: `Chứng từ ${inv.invoice_number} đã được gửi xuống phòng Logistics. Đang chuyển sang trang Yêu Cầu Xuất Kho...`,
      life: 3000
    });

    setTimeout(() => {
      router.push('/warehouse-request-sale');
    }, 800);
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi gửi kho',
      detail: err.message || 'Không thể gửi chứng từ xuống kho.',
      life: 3000
    });
  }
};
</script>
