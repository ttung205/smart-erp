<template>
  <Dialog
    :visible="visible"
    @update:visible="$emit('update:visible', $event)"
    modal
    :header="`Ghi Sổ Xuất Kho & Trừ Tồn Kho: ${invoice?.invoice_number || ''}`"
    :style="{ width: '780px' }"
    class="rounded-[8px]"
  >
    <div v-if="invoice" class="space-y-3 pt-1 text-xs">
      <!-- Banner cảnh báo trừ tồn -->
      <div class="bg-rose-50 border border-rose-200 rounded-[6px] p-3 text-rose-900 flex items-start gap-2.5">
        <i class="pi pi-exclamation-triangle text-rose-600 text-lg mt-0.5 shrink-0"></i>
        <div>
          <span class="font-bold text-rose-800 uppercase tracking-wide block mb-1">
            Xác Nhận Xuất Kho & Trừ Tồn Kho Vật Lý
          </span>
          <p class="m-0 leading-relaxed text-rose-700 text-[11px]">
            Hành động này sẽ <strong>chính thức trừ tồn kho vật lý (On-Hand)</strong> theo chứng từ <strong>{{ invoice.invoice_number }}</strong>, đồng thời ghi sổ kế toán và hoàn tất chu trình giao hàng.
          </p>
        </div>
      </div>

      <div class="border border-slate-200 rounded-[6px] overflow-hidden">
        <div class="bg-slate-100 px-3 py-1.5 border-b border-slate-200 text-xs font-semibold text-slate-700">
          Mặt Hàng Trừ Tồn Kho Thật
        </div>
        <DataTable :value="invoice.items" showGridlines class="clean-datatable text-xs">
          <Column field="sku" header="SKU" style="width: 130px">
            <template #body="{ data }">
              <span class="font-mono font-semibold text-sky-700">{{ data.sku }}</span>
            </template>
          </Column>
          <Column field="product_name" header="Tên Mặt Hàng"></Column>
          <Column header="Số Lượng Trừ Kho" style="width: 150px" class="text-center">
            <template #body="{ data }">
              <span class="font-mono font-bold text-rose-600 bg-rose-50 border border-rose-200 px-2.5 py-0.5 rounded">
                -{{ data.quantity }} chiếc
              </span>
            </template>
          </Column>
        </DataTable>
      </div>

      <div class="flex flex-col gap-1">
        <label class="text-xs font-semibold text-slate-700">Người ghi sổ / Thủ kho xác nhận <span class="text-rose-500">*</span></label>
        <InputText
          v-model="form.posted_by"
          placeholder="Tên thủ kho / quản lý kho xác nhận..."
          class="w-full text-xs rounded-[6px]"
          size="small"
        />
      </div>
    </div>
    <template #footer>
      <Button label="Hủy" text size="small" severity="secondary" @click="$emit('update:visible', false)" />
      <Button
        label="Xác Nhận Ghi Sổ & Trừ Tồn"
        icon="pi pi-check-circle"
        size="small"
        :loading="postingLoading"
        class="!bg-emerald-600 hover:!bg-emerald-700 !text-white !border-emerald-600 rounded-[6px] !text-xs !font-bold"
        @click="$emit('submit', form)"
      />
    </template>
  </Dialog>
</template>

<script setup>
import Dialog from 'primevue/dialog';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';

defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  invoice: {
    type: Object,
    default: null
  },
  form: {
    type: Object,
    default: () => ({ posted_by: '' })
  },
  postingLoading: {
    type: Boolean,
    default: false
  }
});

defineEmits(['update:visible', 'submit']);
</script>
