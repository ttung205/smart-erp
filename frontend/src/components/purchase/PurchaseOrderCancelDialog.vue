<template>
  <Dialog
    :visible="visible"
    @update:visible="$emit('update:visible', $event)"
    modal
    :header="header || 'Từ Chối / Hủy Đơn Đặt Mua PO'"
    :style="{ width: '460px' }"
    class="border border-slate-200 shadow-xl rounded-[6px]"
  >
    <div class="flex flex-col gap-3 py-2 text-xs">
      <div class="p-3 bg-rose-50 border border-rose-200 rounded-[6px] text-xs text-rose-800 leading-relaxed">
        <i class="pi pi-exclamation-triangle mr-1 text-rose-600 font-bold"></i>
        Bạn đang thao tác hủy đơn đặt mua <strong>#{{ orderNumber }}</strong>. Khi hủy, các số lượng nhu cầu đã gom từ Đề nghị mua sắm (PR) sẽ tự động được hoàn trả về trạng thái chưa đặt mua.
      </div>
      <div class="flex flex-col gap-1.5">
        <label class="text-xs font-semibold text-slate-700">Lý do từ chối / hủy đơn <span class="text-rose-500">*</span></label>
        <InputText
          v-model="reason"
          placeholder="Nhập lý do từ chối hoặc hủy đơn mua..."
          class="text-xs !rounded-[6px] w-full"
        />
      </div>
    </div>
    <template #footer>
      <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
        <Button
          label="Đóng"
          icon="pi pi-times"
          size="small"
          text
          severity="secondary"
          class="!rounded-[6px] !text-xs"
          @click="$emit('update:visible', false)"
        />
        <Button
          label="Xác nhận hủy đơn"
          icon="pi pi-ban"
          size="small"
          severity="danger"
          class="!rounded-[6px] !text-xs !bg-rose-600 hover:!bg-rose-700 !text-white !border-rose-600"
          :loading="loading"
          @click="$emit('confirm', reason)"
        />
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import { ref, watch } from 'vue';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  orderNumber: {
    type: String,
    default: ''
  },
  header: {
    type: String,
    default: 'Từ Chối / Hủy Đơn Đặt Mua PO'
  },
  loading: {
    type: Boolean,
    default: false
  }
});

defineEmits(['update:visible', 'confirm']);

const reason = ref('');

watch(
  () => props.visible,
  (val) => {
    if (val) reason.value = '';
  }
);
</script>
