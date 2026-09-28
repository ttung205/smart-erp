<template>
  <div class="space-y-3 pb-8">
    <PageHeader
      title="Yêu Cầu Giao Hàng (Delivery Requests)"
      subtitle="Quản lý các lệnh điều phối vận chuyển, kho xuất hàng và địa chỉ giao nhận theo đơn bán hàng (SO)"
      badge="Stage 3: Delivery Request"
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
              placeholder="Tìm theo mã yêu cầu hoặc tên khách..."
              class="w-72 rounded-[6px]"
              size="small"
            />
          </IconField>
        </div>
      </template>

      <template #end>
        <div class="flex items-center gap-2 text-xs text-slate-500">
          <span>Tổng số: <strong class="text-slate-800">{{ deliveryRequests.length }}</strong> lệnh giao hàng</span>
        </div>
      </template>
    </Toolbar>

    <!-- Data Table -->
    <div class="bg-white border border-slate-200 rounded-[6px] overflow-hidden">
      <DataTable
        :value="filteredRequests"
        showGridlines
        class="clean-datatable"
        responsiveLayout="scroll"
        :paginator="filteredRequests.length > 8"
        :rows="8"
      >
        <Column field="id" header="Mã Lệnh Giao (DR)" :sortable="true" style="width: 160px">
          <template #body="{ data }">
            <span class="font-mono text-xs font-semibold text-sky-700 bg-sky-50 px-2 py-0.5 rounded-[4px] border border-sky-200">
              {{ data.id }}
            </span>
          </template>
        </Column>

        <Column field="order_id" header="Đơn Bán Gốc" style="width: 150px">
          <template #body="{ data }">
            <span class="font-mono text-xs font-semibold text-indigo-700">
              {{ data.order_id }}
            </span>
          </template>
        </Column>

        <Column field="customer_name" header="Khách Hàng B2B" :sortable="true">
          <template #body="{ data }">
            <span class="font-semibold text-slate-800 text-xs">{{ data.customer_name }}</span>
          </template>
        </Column>

        <Column field="warehouse_name" header="Kho Xuất Hàng" style="width: 200px">
          <template #body="{ data }">
            <div class="flex items-center gap-1.5 text-xs text-slate-700">
              <i class="pi pi-building text-slate-400"></i>
              <span>{{ data.warehouse_name }}</span>
            </div>
          </template>
        </Column>

        <Column field="expected_delivery_date" header="Hẹn Giao" style="width: 120px">
          <template #body="{ data }">
            <span class="text-slate-600 text-xs font-medium">{{ data.expected_delivery_date }}</span>
          </template>
        </Column>

        <Column field="shipping_address" header="Địa Chỉ Nhận">
          <template #body="{ data }">
            <span class="text-slate-500 text-xs truncate max-w-xs block" :title="data.shipping_address">
              {{ data.shipping_address }}
            </span>
          </template>
        </Column>

        <Column header="Trạng Thái" class="text-center" style="width: 140px">
          <template #body="{ data }">
            <Tag
              :severity="getStatusMeta(data.status).severity"
              :value="getStatusMeta(data.status).label"
              class="text-[11px] px-2 py-0.5 rounded-[6px]"
            />
          </template>
        </Column>

        <Column header="Thao Tác" class="text-center" style="width: 210px">
          <template #body="{ data }">
            <div class="flex items-center justify-center gap-1.5">
              <Button
                icon="pi pi-eye"
                text
                size="small"
                severity="secondary"
                class="!w-7 !h-7 !p-0 rounded-[6px]"
                v-tooltip.bottom="'Xem chi tiết lệnh giao'"
                @click="openViewRequest(data)"
              />
              <Button
                v-if="data.status !== 'INVOICED'"
                label="Lập Chứng Từ Bán"
                icon="pi pi-file"
                size="small"
                class="!text-xs !py-1 !px-2 rounded-[6px] !bg-indigo-600 hover:!bg-indigo-700 !text-white !border-indigo-600 !font-semibold"
                v-tooltip.bottom="'Tạo chứng từ bán hàng để chuyển kho nhặt hàng'"
                @click="openInvoiceDialog(data)"
              />
              <Tag
                v-else
                severity="success"
                value="Đã lập chứng từ"
                class="text-[10px] px-2 py-0.5 rounded-[4px]"
              />
            </div>
          </template>
        </Column>

        <template #empty>
          <div class="py-10 text-center flex flex-col items-center gap-2">
            <i class="pi pi-truck text-3xl text-slate-300"></i>
            <p class="text-xs font-semibold text-slate-700 m-0">Chưa có yêu cầu giao hàng nào</p>
            <p class="text-[11px] text-slate-400 m-0">Tạo lệnh giao hàng từ danh sách Đơn Bán Hàng (SO) ở mục trước.</p>
          </div>
        </template>
      </DataTable>
    </div>

    <!-- Modals -->
    <DeliveryDetailForm
      v-model:visible="showViewDialog"
      :delivery-request="selectedRequest"
    />

    <CreateInvoiceDialog
      v-model:visible="showInvoiceDialog"
      :delivery-request="selectedRequest"
      @create-invoice="handleCreateInvoice"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Toolbar from 'primevue/toolbar';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import PageHeader from '../../components/common/PageHeader.vue';

import DeliveryDetailForm from './form.vue';
import CreateInvoiceDialog from './invoice-dialog.vue';
import { salesApi } from '../../api/sales.api';

const router = useRouter();
const toast = useToast();

const deliveryRequests = ref([]);
const loading = ref(false);
const searchQuery = ref('');
const showViewDialog = ref(false);
const showInvoiceDialog = ref(false);
const selectedRequest = ref(null);

const getStatusMeta = (st) => {
  const map = {
    APPROVED: { label: 'Chờ lập chứng từ', severity: 'warn' },
    PENDING: { label: 'Chờ lập chứng từ', severity: 'warn' },
    INVOICED: { label: 'Đã lập chứng từ', severity: 'success' },
    CANCELLED: { label: 'Đã hủy', severity: 'danger' }
  };
  return map[st] || { label: st, severity: 'secondary' };
};

const loadDeliveries = async () => {
  loading.value = true;
  try {
    const res = await salesApi.getDeliveries();
    deliveryRequests.value = (res.data || []).map((r) => ({
      ...r,
      id: r.request_number || r.id,
      order_id: r.order_number || r.order_id
    }));
  } catch (err) {
    console.error('Lỗi tải danh sách giao hàng:', err);
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  loadDeliveries();
});

const filteredRequests = computed(() => {
  return deliveryRequests.value.filter((r) => {
    const rId = String(r.id || r.request_number || '');
    const cName = String(r.customer_name || '');
    const oId = String(r.order_id || r.order_number || '');
    return (
      !searchQuery.value ||
      rId.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      cName.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      oId.toLowerCase().includes(searchQuery.value.toLowerCase())
    );
  });
});

const openViewRequest = (r) => {
  selectedRequest.value = r;
  showViewDialog.value = true;
};

const openInvoiceDialog = (r) => {
  selectedRequest.value = r;
  showInvoiceDialog.value = true;
};

const handleCreateInvoice = async (newInv) => {
  try {
    const res = await salesApi.createInvoice(newInv);
    const inv = res.data;
    // Đồng thời cập nhật trạng thái của lệnh giao hàng thành INVOICED
    if (selectedRequest.value?.id) {
      await salesApi.updateDeliveryStatus(selectedRequest.value.id, 'INVOICED');
    }
    toast.add({
      severity: 'success',
      summary: 'Đã lập chứng từ bán hàng',
      detail: `Chứng từ ${inv?.invoice_number || 'INV'} đã chuyển xuống kho. Đang chuyển sang trang Chứng Từ & Kho...`,
      life: 3000
    });
    router.push('/invoice');
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi lập chứng từ bán hàng',
      detail: err.message || 'Không thể tạo chứng từ.',
      life: 3000
    });
  }
};
</script>
