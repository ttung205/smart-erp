<template>
  <Dialog
    :visible="visible"
    @update:visible="$emit('update:visible', $event)"
    modal
    header="Chọn Mặt Hàng Từ Đề Nghị Mua (PR) Đã Duyệt"
    :style="{ width: '800px' }"
    class="!rounded-[6px]"
  >
    <div class="flex flex-col gap-3 pt-1 text-xs">
      <p class="text-slate-600 m-0">
        Danh sách mặt hàng đã được phê duyệt PR và còn dư hạn mức chưa lên đơn PO:
      </p>

      <div class="border border-slate-200 rounded-[6px] overflow-hidden max-h-80 overflow-y-auto">
        <table class="w-full text-xs">
          <thead class="bg-slate-50 border-b border-slate-200 sticky top-0">
            <tr>
              <th class="p-2 text-center w-10">Chọn</th>
              <th class="p-2 text-left">Mã PR</th>
              <th class="p-2 text-left">SKU & Sản Phẩm</th>
              <th class="p-2 text-left">Xuất Xứ</th>
              <th class="p-2 text-right">SL Duyệt</th>
              <th class="p-2 text-right">Đã Lên PO</th>
              <th class="p-2 text-right text-sky-700">Còn Lại</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="item in availablePrLines"
              :key="item.id"
              class="border-b border-slate-100 last:border-none hover:bg-slate-50"
            >
              <td class="p-2 text-center">
                <input
                  type="checkbox"
                  v-model="selectedLines"
                  :value="item"
                  class="rounded text-sky-600 focus:ring-sky-500 w-4 h-4 cursor-pointer"
                />
              </td>
              <td class="p-2 font-mono font-semibold text-slate-800 whitespace-nowrap">
                {{ item.pr_number }}
              </td>
              <td class="p-2">
                <div class="font-mono font-semibold text-slate-800">{{ item.sku }}</div>
                <div class="text-[11px] text-slate-500">{{ item.item_name }}</div>
              </td>
              <td class="p-2 text-slate-700">
                {{ item.origin || getProductOrigin(item.sku || item.product_id) }}
              </td>
              <td class="p-2 text-right font-mono font-medium text-slate-700">
                {{ item.quantity_approved }}
              </td>
              <td class="p-2 text-right font-mono text-slate-500">
                {{ item.quantity_ordered }}
              </td>
              <td class="p-2 text-right font-mono font-bold text-sky-700">
                {{ item.quantity_available }}
              </td>
            </tr>
            <tr v-if="availablePrLines.length === 0">
              <td colspan="7" class="p-4 text-center text-slate-400">
                Không còn mặt hàng nào trong PR sẵn sàng lên đơn.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <template #footer>
      <div class="flex justify-between items-center w-full">
        <span class="text-xs text-slate-500">
          Đã chọn: <strong class="text-sky-700">{{ selectedLines.length }}</strong> dòng
        </span>
        <div class="flex gap-2">
          <Button
            label="Hủy bỏ"
            icon="pi pi-times"
            size="small"
            text
            severity="secondary"
            class="!rounded-[6px] !text-xs"
            @click="$emit('update:visible', false)"
          />
          <Button
            label="Kéo vào Đơn Hàng"
            icon="pi pi-plus"
            size="small"
            class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 !rounded-[6px] !text-xs font-semibold"
            :disabled="selectedLines.length === 0"
            @click="$emit('confirm', selectedLines)"
          />
        </div>
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import { ref, watch } from 'vue';
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';
import { getProductOrigin } from '../../constants/catalogProducts.constant';

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  availablePrLines: {
    type: Array,
    default: () => []
  }
});

defineEmits(['update:visible', 'confirm']);

const selectedLines = ref([]);

watch(
  () => props.visible,
  (val) => {
    if (val) selectedLines.value = [];
  }
);
</script>
