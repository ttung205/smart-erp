<template>
  <Dialog
    :visible="visible"
    @update:visible="$emit('update:visible', $event)"
    modal
    header="Import Danh Mục Sản Phẩm Từ Excel (.xlsx)"
    :style="{ width: '800px' }"
    class="rounded-[6px]"
  >
    <div class="flex flex-col gap-3.5 pt-1 text-xs">
      <div class="p-3 bg-sky-50 border border-sky-200 rounded-[6px] text-sky-800">
        <p class="font-semibold m-0 mb-1 flex items-center gap-1.5">
          <i class="pi pi-info-circle text-sky-600"></i>
          Quy định các cột file Excel:
        </p>
        <p class="m-0 text-[11px] text-slate-600">
          Hệ thống hỗ trợ file mẫu <code>san_pham_mau.xlsx</code> với các cột:
          <strong>sku, item description, brand, designation, category, gross price, specs</strong>.
        </p>
      </div>

      <!-- File Upload Drag & Drop Box -->
      <div
        class="border-2 border-dashed border-slate-300 rounded-[6px] p-6 text-center hover:border-sky-500 transition-colors cursor-pointer bg-slate-50/50"
        @click="fileInputRef?.click()"
      >
        <input
          ref="fileInputRef"
          type="file"
          accept=".xlsx, .xls"
          class="hidden"
          @change="handleFileChange"
        />
        <i class="pi pi-file-excel text-4xl text-emerald-600 mb-2"></i>
        <p class="text-xs font-semibold text-slate-800 m-0">
          {{ selectedFileName ? `Đã chọn: ${selectedFileName}` : 'Nhấn để chọn file Excel (.xlsx)' }}
        </p>
        <p class="text-[11px] text-slate-400 m-0 mt-0.5">Hỗ trợ tệp tin định dạng Excel (.xlsx, .xls)</p>
      </div>

      <!-- Preview Table if file parsed -->
      <div v-if="parsedItems.length > 0" class="flex flex-col gap-2">
        <div class="flex justify-between items-center">
          <span class="font-semibold text-slate-800">
            Xem trước 5 dòng đầu (Tổng cộng: <strong class="text-sky-700">{{ parsedItems.length }}</strong> sản phẩm):
          </span>
        </div>

        <div class="border border-slate-200 rounded-[6px] overflow-hidden max-h-48 overflow-y-auto">
          <table class="w-full text-[11px] text-left border-collapse">
            <thead class="bg-slate-100 text-slate-700 font-semibold sticky top-0">
              <tr>
                <th class="p-1.5 border-b">SKU</th>
                <th class="p-1.5 border-b">Designation</th>
                <th class="p-1.5 border-b">Brand</th>
                <th class="p-1.5 border-b">Category</th>
                <th class="p-1.5 border-b">Description</th>
                <th class="p-1.5 border-b text-right">Gross Price</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(it, idx) in parsedItems.slice(0, 5)" :key="idx" class="border-b hover:bg-slate-50">
                <td class="p-1.5 font-mono font-semibold text-sky-700">{{ it.sku }}</td>
                <td class="p-1.5 font-mono text-rose-700">{{ it.designation }}</td>
                <td class="p-1.5">{{ it.brand }}</td>
                <td class="p-1.5">{{ it.category }}</td>
                <td class="p-1.5 truncate max-w-[200px]">{{ it['item description'] || it.description }}</td>
                <td class="p-1.5 text-right font-mono">{{ formatCurrency(it['gross price'] || it.gross_price) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <template #footer>
      <div class="flex justify-between items-center w-full">
        <Button
          label="Đóng"
          icon="pi pi-times"
          text
          size="small"
          severity="secondary"
          class="rounded-[6px]"
          @click="$emit('update:visible', false)"
        />
        <Button
          label="Xác nhận Import vào Hệ Thống"
          icon="pi pi-upload"
          size="small"
          class="!bg-emerald-600 hover:!bg-emerald-700 !text-white !border-emerald-600 rounded-[6px] !text-xs font-semibold"
          :loading="importing"
          :disabled="parsedItems.length === 0"
          @click="$emit('submit', parsedItems)"
        />
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import { ref } from 'vue';
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';
import * as XLSX from 'xlsx';

defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  importing: {
    type: Boolean,
    default: false
  }
});

const emit = defineEmits(['update:visible', 'submit']);

const fileInputRef = ref(null);
const selectedFileName = ref('');
const parsedItems = ref([]);

const formatCurrency = (val) => {
  if (!val && val !== 0) return '0 ₫';
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val);
};

const handleFileChange = (e) => {
  const file = e.target.files[0];
  if (!file) return;

  selectedFileName.value = file.name;
  const reader = new FileReader();
  reader.onload = (evt) => {
    try {
      const data = new Uint8Array(evt.target.result);
      const workbook = XLSX.read(data, { type: 'array' });
      const sheetName = workbook.SheetNames[0];
      const worksheet = workbook.Sheets[sheetName];
      const json = XLSX.utils.sheet_to_json(worksheet, { defval: '' });
      parsedItems.value = json;
    } catch (err) {
      console.error('Lỗi đọc file Excel:', err);
      alert('Không thể đọc file Excel. Vui lòng kiểm tra lại định dạng tệp!');
    }
  };
  reader.readAsArrayBuffer(file);
};
</script>
