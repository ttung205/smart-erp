<template>
  <Dialog
    :visible="visible"
    @update:visible="$emit('update:visible', $event)"
    modal
    :header="`Danh Sách Người Liên Hệ - ${customer?.name || ''}`"
    :style="{ width: '1000px' }"
    class="rounded-[8px]"
  >
    <div class="space-y-3 pt-1 text-xs">
      <div class="flex items-center justify-between bg-slate-50 border border-slate-200 rounded-[6px] px-3.5 py-2">
        <div class="flex items-center gap-2 text-slate-700">
          <span class="font-bold">Mã KH:</span>
          <span class="font-mono font-bold text-sky-700">{{ customer?.code }}</span>
          <span class="text-slate-300">|</span>
          <span>Tổng số: <strong>{{ customer?.contacts?.length || 0 }}</strong> người liên hệ</span>
        </div>

        <Button
          label="Chỉnh sửa tại trang Form"
          icon="pi pi-external-link"
          size="small"
          text
          class="!text-xs !p-0 text-sky-600 hover:underline"
          @click="$router.push(`/customer/${customer?.id}/edit`)"
        />
      </div>

      <!-- Bảng danh sách liên hệ (Read-only quick view) -->
      <DataTable
        :value="customer?.contacts || []"
        responsiveLayout="scroll"
        class="clean-datatable text-xs"
        showGridlines
      >
        <Column field="name" header="Họ và Tên" style="min-width: 160px">
          <template #body="{ data }">
            <div class="flex items-center gap-1.5">
              <span class="font-bold text-slate-800">{{ data.name }}</span>
              <Tag v-if="data.is_primary" value="Chính" severity="info" class="text-[10px] px-1.5 py-0 rounded" />
            </div>
          </template>
        </Column>

        <Column field="position" header="Chức Vụ / Phòng Ban" style="width: 160px">
          <template #body="{ data }">
            <span class="text-slate-600">{{ data.position || '-' }}</span>
          </template>
        </Column>

        <Column field="phone" header="Số Điện Thoại" style="width: 130px">
          <template #body="{ data }">
            <span class="font-mono text-slate-700">{{ data.phone || '-' }}</span>
          </template>
        </Column>

        <Column field="email" header="Email" style="width: 180px">
          <template #body="{ data }">
            <span class="text-slate-600 truncate block">{{ data.email || '-' }}</span>
          </template>
        </Column>

        <Column field="notes" header="Ghi Chú" style="min-width: 120px">
          <template #body="{ data }">
            <span class="text-slate-500 text-xs italic">{{ data.notes || '-' }}</span>
          </template>
        </Column>

        <template #empty>
          <div class="py-6 text-center text-slate-400 text-xs italic">
            Chưa có thông tin người liên hệ nào cho khách hàng này.
          </div>
        </template>
      </DataTable>
    </div>

    <template #footer>
      <div class="flex justify-between items-center pt-2">
        <Button
          label="Mở trang chỉnh sửa khách hàng"
          icon="pi pi-pencil"
          size="small"
          class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 !text-xs rounded-[6px]"
          @click="$router.push(`/customer/${customer?.id}/edit`)"
        />
        <Button
          label="Đóng"
          size="small"
          severity="secondary"
          class="rounded-[6px]"
          @click="$emit('update:visible', false)"
        />
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import Dialog from 'primevue/dialog';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import { useRouter } from 'vue-router';

const router = useRouter();

defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  customer: {
    type: Object,
    default: null
  }
});

defineEmits(['update:visible']);
</script>
