<template>
  <Dialog
    :visible="visible"
    @update:visible="$emit('update:visible', $event)"
    modal
    header="Phê Duyệt Đề Nghị Mua Sắm"
    :style="{ width: '650px' }"
    class="!rounded-[6px]"
  >
    <div class="flex flex-col gap-3 pt-1 text-xs">
      <p class="text-slate-600 m-0">
        Vui lòng kiểm tra và xác nhận số lượng duyệt mua cho từng mặt hàng của phiếu <strong class="text-slate-900 font-mono">{{ prNumber }}</strong>:
      </p>

      <div class="border border-slate-200 rounded-[6px] overflow-hidden">
        <table class="w-full text-xs">
          <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
              <th class="p-2 text-left font-semibold text-slate-700">Mã SKU / Sản Phẩm</th>
              <th class="p-2 text-right font-semibold text-slate-700">SL Xin</th>
              <th class="p-2 text-right font-semibold text-slate-700 w-32">SL Duyệt</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(it, idx) in lines" :key="idx" class="border-b border-slate-100 last:border-none">
              <td class="p-2">
                <div class="font-mono font-semibold text-slate-800">{{ it.sku }}</div>
                <div class="text-[11px] text-slate-500">{{ it.item_name }}</div>
              </td>
              <td class="p-2 text-right font-mono font-semibold text-slate-800">
                {{ it.quantity_requested }}
              </td>
              <td class="p-2 text-right">
                <InputNumber
                  v-model="it.quantity_approved"
                  :min="0"
                  :max="it.quantity_requested"
                  size="small"
                  class="w-24 !rounded-[6px] !text-xs"
                  inputClass="!text-right !font-mono font-bold !py-1"
                />
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="flex flex-col gap-1">
        <label class="font-medium text-slate-700">Ý kiến phê duyệt:</label>
        <InputText
          v-model="comments"
          placeholder="Đồng ý duyệt theo số lượng điều chỉnh..."
          size="small"
          class="!rounded-[6px] !text-xs"
        />
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end gap-2">
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
          label="Xác nhận Phê duyệt"
          icon="pi pi-check"
          size="small"
          class="!bg-emerald-600 hover:!bg-emerald-700 !text-white !border-emerald-600 !rounded-[6px] !text-xs"
          :loading="loading"
          @click="$emit('confirm', { lines, comments })"
        />
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import { ref } from 'vue';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';

defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  prNumber: {
    type: String,
    default: ''
  },
  lines: {
    type: Array,
    default: () => []
  },
  loading: {
    type: Boolean,
    default: false
  }
});

defineEmits(['update:visible', 'confirm']);

const comments = ref('');
</script>
