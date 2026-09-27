<template>
  <div class="space-y-3">
    <!-- Action Toolbar -->
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

    <!-- Table -->
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

        <Column field="order_id" header="Đơn Bán Gốc" style="width: 140px">
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

        <Column field="warehouse_name" header="Kho Xuất Hàng" style="width: 180px">
          <template #body="{ data }">
            <div class="flex items-center gap-1.5 text-xs text-slate-700">
              <i class="pi pi-building text-slate-400"></i>
              <span>{{ data.warehouse_name }}</span>
            </div>
          </template>
        </Column>

        <Column field="expected_delivery_date" header="Hẹn Giao" style="width: 110px">
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

        <Column header="Trạng Thái" class="text-center" style="width: 130px">
          <template #body="{ data }">
            <Tag
              :severity="getStatusMeta(data.status).severity"
              :value="getStatusMeta(data.status).label"
              class="text-[11px] px-2 py-0.5 rounded-[6px]"
            />
          </template>
        </Column>

        <Column header="Thao Tác" class="text-center" style="width: 180px">
          <template #body="{ data }">
            <div class="flex items-center justify-center gap-1">
              <Button
                v-if="data.status !== 'INVOICED'"
                label="Lập Chứng Từ Bán"
                icon="pi pi-file"
                size="small"
                class="!text-xs !py-1 !px-2 rounded-[6px] !bg-indigo-600 hover:!bg-indigo-700 !text-white !border-indigo-600 !font-medium"
                v-tooltip.bottom="'Tạo chứng từ bán hàng để chuyển kho nhặt hàng'"
                @click="$emit('open-invoice-dialog', data)"
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
            <p class="text-[11px] text-slate-400 m-0">Tạo lệnh giao hàng từ danh sách Đơn Bán Hàng (SO) ở tab trước.</p>
          </div>
        </template>
      </DataTable>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Toolbar from 'primevue/toolbar';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import Tag from 'primevue/tag';

const props = defineProps({
  deliveryRequests: {
    type: Array,
    default: () => []
  }
});

defineEmits(['open-invoice-dialog']);

const searchQuery = ref('');

const getStatusMeta = (st) => {
  const map = {
    APPROVED: { label: 'Chờ lập chứng từ', severity: 'warn' },
    INVOICED: { label: 'Đã lập chứng từ', severity: 'success' },
    CANCELLED: { label: 'Đã hủy', severity: 'danger' }
  };
  return map[st] || { label: st, severity: 'secondary' };
};

const filteredRequests = computed(() => {
  return props.deliveryRequests.filter((r) => {
    return (
      !searchQuery.value ||
      r.id.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      r.customer_name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      r.order_id.toLowerCase().includes(searchQuery.value.toLowerCase())
    );
  });
});
</script>
