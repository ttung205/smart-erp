<template>
  <div class="space-y-3 pb-8">
    <PageHeader
      title="Chứng Từ Bán Hàng & Điều Phối Kho (Invoices & Warehouse)"
      subtitle="Theo dõi chứng từ bán hàng, tiến độ nhặt hàng (Pick & Pack), bàn giao vận chuyển và nhận hàng của khách"
      badge="Stage 4: Fulfillment"
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
              placeholder="Tìm theo mã chứng từ, đơn bán hoặc khách hàng..."
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
            class="w-52 rounded-[6px]"
            size="small"
          />
        </div>
      </template>

      <template #end>
        <div class="flex items-center gap-2 text-xs text-slate-500">
          <span>Tổng số: <strong class="text-slate-800">{{ invoices.length }}</strong> chứng từ bán</span>
        </div>
      </template>
    </Toolbar>

    <!-- Data Table -->
    <div class="bg-white border border-slate-200 rounded-[6px] overflow-hidden">
      <DataTable
        :value="filteredInvoices"
        showGridlines
        class="clean-datatable"
        responsiveLayout="scroll"
        :paginator="filteredInvoices.length > 8"
        :rows="8"
      >
        <Column field="invoice_number" header="Mã Chứng Từ (INV)" :sortable="true" style="width: 160px">
          <template #body="{ data }">
            <span class="font-mono text-xs font-semibold text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded-[4px] border border-indigo-200">
              {{ data.invoice_number }}
            </span>
          </template>
        </Column>

        <Column field="order_id" header="Đơn Bán / Lệnh Giao" style="width: 170px">
          <template #body="{ data }">
            <div class="flex flex-col text-xs leading-tight">
              <span class="font-mono font-semibold text-slate-800">{{ data.order_id }}</span>
              <span class="font-mono text-slate-400 text-[10px]">{{ data.delivery_request_id }}</span>
            </div>
          </template>
        </Column>

        <Column field="customer_name" header="Khách Hàng B2B" :sortable="true">
          <template #body="{ data }">
            <span class="font-semibold text-slate-800 text-xs">{{ data.customer_name }}</span>
          </template>
        </Column>

        <Column field="warehouse_name" header="Kho Xuất">
          <template #body="{ data }">
            <span class="text-slate-600 text-xs">{{ data.warehouse_name }}</span>
          </template>
        </Column>

        <Column field="total_amount" header="Tổng Giá Trị" class="text-right" :sortable="true" style="width: 140px">
          <template #body="{ data }">
            <span class="font-mono text-xs font-bold text-slate-900 num-tabular">
              {{ formatCurrency(data.total_amount) }}
            </span>
          </template>
        </Column>

        <!-- Trạng thái tiến độ Kho -->
        <Column header="Tiến Độ Kho Xử Lý" class="text-center" style="width: 170px">
          <template #body="{ data }">
            <Tag
              :severity="getWarehouseStatusMeta(data.warehouse_status).severity"
              :value="getWarehouseStatusMeta(data.warehouse_status).label"
              class="text-[11px] px-2 py-0.5 rounded-[6px]"
            />
          </template>
        </Column>

        <!-- Trạng thái Kế toán -->
        <Column header="Ghi Sổ Kế Toán" class="text-center" style="width: 140px">
          <template #body="{ data }">
            <Tag
              v-if="data.accounting_status === 'POSTED'"
              severity="success"
              value="Đã ghi sổ"
              icon="pi pi-check"
              class="text-[11px] px-2 py-0.5 rounded-[4px]"
            />
            <Tag
              v-else
              severity="secondary"
              value="Chưa ghi sổ"
              icon="pi pi-clock"
              class="text-[11px] px-2 py-0.5 rounded-[4px]"
            />
          </template>
        </Column>

        <!-- Thao tác tiến độ Kho -->
        <Column header="Thao Tác Tại Kho" class="text-center" style="width: 240px">
          <template #body="{ data }">
            <div class="flex items-center justify-center gap-1.5">
              <Button
                icon="pi pi-eye"
                text
                size="small"
                severity="secondary"
                class="!w-7 !h-7 !p-0 rounded-[6px]"
                v-tooltip.bottom="'Xem chi tiết chứng từ'"
                @click="openViewInvoice(data)"
              />

              <!-- Nút 1: Chuyển xuống kho -->
              <Button
                v-if="data.warehouse_status === 'PENDING_WAREHOUSE'"
                label="Gửi Xuống Kho"
                icon="pi pi-send"
                size="small"
                class="!text-xs !py-1 !px-2 rounded-[6px] !bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600"
                @click="onUpdateWarehouse(data, 'PICKING')"
              />

              <!-- Nút 2: Kho nhặt hàng xong -> Bàn giao shipper -->
              <Button
                v-else-if="data.warehouse_status === 'PICKING'"
                label="Bàn Giao Shipper"
                icon="pi pi-box"
                size="small"
                class="!text-xs !py-1 !px-2 rounded-[6px] !bg-amber-600 hover:!bg-amber-700 !text-white !border-amber-600"
                @click="onUpdateWarehouse(data, 'DISPATCHED')"
              />

              <!-- Nút 3: Xác nhận khách đã nhận hàng -->
              <Button
                v-else-if="data.warehouse_status === 'DISPATCHED'"
                label="Khách Đã Nhận Hàng"
                icon="pi pi-check-circle"
                size="small"
                class="!text-xs !py-1 !px-2 rounded-[6px] !bg-emerald-600 hover:!bg-emerald-700 !text-white !border-emerald-600"
                @click="onUpdateWarehouse(data, 'DELIVERED')"
              />

              <!-- Đã giao hàng -->
              <Button
                v-else-if="data.warehouse_status === 'DELIVERED' && data.accounting_status !== 'POSTED'"
                label="Sang Ghi Sổ ➔"
                text
                size="small"
                class="!text-xs !py-1 !px-2 rounded-[6px] !text-emerald-700 !font-bold hover:!bg-emerald-50"
                v-tooltip.bottom="'Chuyển sang trang Kế toán để ghi sổ trừ tồn kho'"
                @click="$router.push('/accounting')"
              />
            </div>
          </template>
        </Column>

        <template #empty>
          <div class="py-10 text-center flex flex-col items-center gap-2">
            <i class="pi pi-file text-3xl text-slate-300"></i>
            <p class="text-xs font-semibold text-slate-700 m-0">Chưa có chứng từ bán hàng nào</p>
            <p class="text-[11px] text-slate-400 m-0">Lập chứng từ bán từ trang Yêu Cầu Giao Hàng để bắt đầu quy trình xuất kho.</p>
          </div>
        </template>
      </DataTable>
    </div>

    <!-- Modal -->
    <InvoiceDetailForm
      v-model:visible="showViewDialog"
      :invoice="selectedInvoice"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useToast } from 'primevue/usetoast';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Toolbar from 'primevue/toolbar';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import PageHeader from '../../components/common/PageHeader.vue';

import InvoiceDetailForm from './form.vue';
import { salesApi } from '../../api/sales.api';
import { formatCurrency } from '../../utils/format';

const toast = useToast();

const invoices = ref([]);
const loading = ref(false);
const searchQuery = ref('');
const warehouseStatusFilter = ref(null);
const showViewDialog = ref(false);
const selectedInvoice = ref(null);

const statusOptions = [
  { label: 'Tất cả tiến độ', value: null },
  { label: 'Chờ gửi kho', value: 'PENDING_WAREHOUSE' },
  { label: 'Kho đang nhặt hàng', value: 'PICKING' },
  { label: 'Đang vận chuyển', value: 'DISPATCHED' },
  { label: 'Đã giao hàng thành công', value: 'DELIVERED' }
];

const getWarehouseStatusMeta = (st) => {
  const map = {
    PENDING_WAREHOUSE: { label: 'Chờ gửi kho', severity: 'secondary' },
    PICKING: { label: 'Kho đang nhặt hàng', severity: 'warn' },
    DISPATCHED: { label: 'Đang vận chuyển', severity: 'info' },
    DELIVERED: { label: 'Đã giao hàng thành công', severity: 'success' }
  };
  return map[st] || { label: st, severity: 'secondary' };
};

const loadInvoices = async () => {
  loading.value = true;
  try {
    const res = await salesApi.getInvoices();
    invoices.value = (res.data || []).map((inv) => ({
      ...inv,
      order_id: inv.order_number || inv.order_id
    }));
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
    const invNum = String(inv.invoice_number || '');
    const cName = String(inv.customer_name || '');
    const oId = String(inv.order_id || inv.order_number || '');
    const matchSearch =
      !searchQuery.value ||
      invNum.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      cName.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      oId.toLowerCase().includes(searchQuery.value.toLowerCase());
    const matchStatus = !warehouseStatusFilter.value || inv.warehouse_status === warehouseStatusFilter.value;
    return matchSearch && matchStatus;
  });
});

const openViewInvoice = (inv) => {
  selectedInvoice.value = inv;
  showViewDialog.value = true;
};

const onUpdateWarehouse = async (inv, status) => {
  try {
    await salesApi.updateInvoiceWarehouseStatus(inv.id, status);
    inv.warehouse_status = status;

    const messages = {
      PICKING: 'Kho đã nhận chứng từ và đang nhặt hàng đóng gói (Pick & Pack).',
      DISPATCHED: 'Hàng đã được đóng gói và bàn giao cho shipper vận chuyển tới khách.',
      DELIVERED: 'Khách hàng đã nhận hàng và ký biên bản giao nhận thành công! Sẵn sàng để Kế toán ghi sổ.'
    };

    toast.add({
      severity: 'info',
      summary: 'Cập nhật tiến độ kho',
      detail: messages[status] || status,
      life: 3000
    });
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi cập nhật kho',
      detail: err.message || 'Không thể cập nhật trạng thái kho.',
      life: 3000
    });
  }
};
</script>
