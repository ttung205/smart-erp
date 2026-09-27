<template>
  <div>
    <!-- Floating Trigger Button -->
    <button
      class="fixed bottom-6 right-6 z-40 bg-sky-600 hover:bg-sky-700 text-white px-4 py-2 rounded-[6px] border border-sky-500 flex items-center gap-2 text-[13px] font-semibold cursor-pointer transition-colors"
      @click="visible = !visible"
      v-tooltip.left="'Trợ lý ERP hỗ trợ tra cứu'"
    >
      <i class="pi pi-sparkles"></i>
      <span>Trợ lý ERP</span>
    </button>

    <!-- Assistant Panel -->
    <div v-if="visible" class="fixed bottom-16 right-6 w-96 bg-white border border-slate-200 rounded-[6px] z-50 flex flex-col overflow-hidden">
      <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-200 flex justify-between items-center">
        <div class="flex items-center gap-2 text-xs font-bold text-slate-800">
          <i class="pi pi-sparkles text-sky-600"></i>
          <span>Trợ lý nghiệp vụ ERP</span>
        </div>
        <Button
          icon="pi pi-times"
          text
          size="small"
          severity="secondary"
          class="!w-6 !h-6 !p-0"
          @click="visible = false"
        />
      </div>

      <div class="p-4 max-h-80 overflow-y-auto flex flex-col gap-3">
        <div class="bg-slate-50 border border-slate-200 rounded-[6px] p-3 flex gap-2.5 items-start">
          <i class="pi pi-info-circle text-sky-600 mt-0.5 text-sm"></i>
          <div class="flex-1">
            <span class="font-semibold text-xs text-slate-800">Hỗ trợ tra cứu nhanh:</span>
            <p class="text-xs text-slate-600 m-0 mt-1 leading-relaxed">
              Xin chào <strong>{{ userName }}</strong>! Bạn có thể yêu cầu tôi kiểm tra quyền hạn RBAC, tra cứu mã SKU hoặc thống kê nhanh danh mục hàng hóa.
            </p>
          </div>
        </div>
      </div>

      <div class="p-3 border-t border-slate-200 flex gap-2 bg-white">
        <InputText
          v-model="inputQuery"
          placeholder="Nhập nội dung cần hỗ trợ..."
          size="small"
          class="flex-1 !rounded-[6px]"
          @keyup.enter="handleSend"
        />
        <Button
          icon="pi pi-send"
          size="small"
          class="!rounded-[6px]"
          @click="handleSend"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';

defineProps({
  userName: {
    type: String,
    default: 'người dùng'
  }
});

const visible = ref(false);
const inputQuery = ref('');

const handleSend = () => {
  if (!inputQuery.value.trim()) return;
  inputQuery.value = '';
};
</script>
