<template>
  <Dialog
    :visible="visible"
    modal
    maximizable
    :style="{ width: '96vw', maxWidth: '1600px' }"
    :content-style="{ padding: '0' }"
    class="rounded-[6px]"
    @update:visible="emit('update:visible', $event)"
  >
    <template #header>
      <div class="flex flex-wrap items-center justify-between gap-3 w-full pr-3">
        <Tabs v-model:value="activeTab" class="min-w-0 max-w-full">
          <TabList class="overflow-x-auto">
            <Tab v-for="tab in salesDocumentTabs" :key="tab.key" :value="tab.key" class="!text-xs !px-3 !py-2 whitespace-nowrap">
              {{ tab.label }}
            </Tab>
          </TabList>
        </Tabs>
        <div class="flex items-center gap-2">
          <Button label="Tải PDF" icon="pi pi-download" size="small" class="rounded-[6px]" :disabled="!previewUrl || loading" @click="downloadCurrent" />
        </div>
      </div>
    </template>

    <div class="bg-slate-800 min-h-[70vh] flex items-center justify-center">
      <div v-if="loading" class="flex items-center gap-2 text-white text-sm">
        <i class="pi pi-spin pi-spinner" /> Đang tạo bản xem trước PDF...
      </div>
      <div v-else-if="errorMessage" class="bg-white rounded-[6px] p-6 text-center space-y-3">
        <p class="text-sm text-red-700">{{ errorMessage }}</p>
        <Button label="Thử lại" icon="pi pi-refresh" size="small" outlined @click="refreshPreview" />
      </div>
      <iframe v-else-if="previewUrl" :key="previewUrl" :src="previewUrl" title="Xem trước chứng từ PDF" class="w-full h-[76vh] border-0 bg-white" />
    </div>
  </Dialog>
</template>

<script setup>
import { ref, watch, onBeforeUnmount } from 'vue';
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';
import Tabs from 'primevue/tabs';
import TabList from 'primevue/tablist';
import Tab from 'primevue/tab';
import { salesApi } from '../../api/sales.api';
import { generateSalesDocument, salesDocumentTabs } from '../../utils/salesDocuments';

const props = defineProps({
  visible: { type: Boolean, default: false },
  invoiceId: { type: [String, Number], default: null },
});
const emit = defineEmits(['update:visible']);
const activeTab = ref('warehouse');
const loading = ref(false);
const errorMessage = ref('');
const previewUrl = ref('');
const fileName = ref('');
let requestSequence = 0;

const clearPreview = () => {
  if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
  previewUrl.value = '';
};

const refreshPreview = async () => {
  if (!props.visible || !props.invoiceId) return;
  const sequence = ++requestSequence;
  loading.value = true;
  errorMessage.value = '';
  try {
    const response = await salesApi.getInvoiceById(props.invoiceId);
    const record = response?.data;
    if (!record) throw new Error('Không tìm thấy chứng từ bán hàng.');
    const result = await generateSalesDocument(activeTab.value, record);
    if (sequence !== requestSequence) return;
    clearPreview();
    previewUrl.value = URL.createObjectURL(result.blob);
    fileName.value = result.fileName;
  } catch (error) {
    if (sequence === requestSequence) {
      clearPreview();
      errorMessage.value = error?.message || 'Không thể tạo PDF.';
    }
  } finally {
    if (sequence === requestSequence) loading.value = false;
  }
};

const downloadCurrent = () => {
  if (!previewUrl.value) return;
  const link = document.createElement('a');
  link.href = previewUrl.value;
  link.download = fileName.value;
  link.click();
};

watch(() => [props.visible, props.invoiceId, activeTab.value], refreshPreview);
watch(() => props.visible, (open) => {
  if (!open) {
    requestSequence += 1;
    clearPreview();
    errorMessage.value = '';
  }
});
onBeforeUnmount(() => {
  requestSequence += 1;
  clearPreview();
});
</script>
