<template>
  <div>
    <Dialog
      :visible="visible"
      modal
      header="Xem Trước Báo Giá PDF & Tùy Chọn Xuất Bản"
      :style="{ width: '1080px', maxWidth: '96vw' }"
      :closable="true"
      @update:visible="$emit('update:visible', $event)"
    >
      <div class="space-y-4 text-xs">
        <!-- Toolbar Controls -->
        <div class="bg-slate-50 border border-slate-200 rounded-[6px] p-3 flex flex-wrap items-center justify-between gap-4">
          <!-- 1. Hướng in PDF -->
          <div class="flex items-center gap-3">
            <span class="font-bold text-slate-700">Khổ giấy:</span>
            <div class="flex items-center gap-2">
              <label class="flex items-center gap-1.5 cursor-pointer font-medium text-slate-700">
                <RadioButton v-model="orientation" value="landscape" @change="refreshPreview" />
                <span>Khổ Ngang (Landscape)</span>
              </label>
              <label class="flex items-center gap-1.5 cursor-pointer font-medium text-slate-700 ml-2">
                <RadioButton v-model="orientation" value="portrait" @change="refreshPreview" />
                <span>Khổ Dọc (Portrait)</span>
              </label>
            </div>
          </div>

          <!-- 2. Nút Mở Thiết lập Điều Khoản -->
          <Button
            label="Thiết lập điều khoản thương mại"
            icon="pi pi-sliders-h"
            size="small"
            outlined
            severity="info"
            class="!text-xs rounded-[6px]"
            @click="showTermsDialog = true"
          />
        </div>

        <!-- Checkbox chọn các cột hiển thị trong bảng item -->
        <div class="border border-slate-200 bg-white rounded-[6px] p-3 space-y-2">
          <div class="flex items-center justify-between">
            <span class="font-bold text-slate-800">Chọn các cột hiển thị trong bảng vật tư:</span>
            <span class="text-[11px] text-slate-400">Tích chọn để thêm/bớt cột trên bản PDF</span>
          </div>

          <div class="flex flex-wrap items-center gap-4 pt-1">
            <div
              v-for="col in availableColumns"
              :key="col.field"
              class="flex items-center gap-1.5"
            >
              <Checkbox
                v-model="col.selected"
                :binary="true"
                :inputId="`col_${col.field}`"
                @change="refreshPreview"
              />
              <label :for="`col_${col.field}`" class="cursor-pointer text-slate-700 font-medium">
                {{ col.header }}
              </label>
            </div>
          </div>
        </div>

        <!-- PDF Preview Frame -->
        <div class="border border-slate-300 rounded-[6px] overflow-hidden bg-slate-100 flex items-center justify-center min-h-[480px]">
          <div v-if="loadingPreview" class="py-12 flex flex-col items-center gap-2 text-slate-500">
            <i class="pi pi-spin pi-spinner text-2xl text-indigo-600"></i>
            <span>Đang tạo bản xem trước PDF...</span>
          </div>
          <iframe
            v-else-if="previewUrl"
            :src="previewUrl"
            class="w-full h-[520px] border-none"
          ></iframe>
          <div v-else class="py-12 text-slate-400 text-center">
            Không thể tải bản xem trước PDF
          </div>
        </div>
      </div>

      <!-- Footer Controls -->
      <template #footer>
        <div class="flex items-center justify-between w-full pt-2">
          <div class="text-[11px] text-slate-500 flex items-center gap-1.5">
            <i class="pi pi-shield text-emerald-600"></i>
            <span>File PDF được tạo tự động theo chuẩn nhận diện thương hiệu Thanh Tùng</span>
          </div>

          <div class="flex items-center gap-2">
            <Button
              label="Đóng"
              icon="pi pi-times"
              severity="secondary"
              outlined
              size="small"
              class="!text-xs rounded-[6px]"
              @click="$emit('update:visible', false)"
            />
            <Button
              label="Tải File PDF"
              icon="pi pi-download"
              severity="info"
              size="small"
              class="!text-xs rounded-[6px]"
              @click="handleDownloadPdf"
            />
            <Button
              label="Gửi Email Cho Khách Hàng"
              icon="pi pi-send"
              severity="success"
              size="small"
              class="!text-xs !bg-indigo-600 hover:!bg-indigo-700 !text-white rounded-[6px] !font-semibold border-none"
              @click="openSendEmailDialog"
            />
          </div>
        </div>
      </template>
    </Dialog>

    <!-- Dialog Thiết lập điều khoản -->
    <QuotationTermsDialog
      v-model:visible="showTermsDialog"
      v-model="customTerms"
      @apply="handleTermsApplied"
    />

    <!-- Dialog Gửi Email -->
    <QuotationSendEmailDialog
      v-model:visible="showSendEmailDialog"
      :quotation="quotation"
      :pdfBase64="currentPdfBase64"
      :currentUser="currentUser"
    />
  </div>
</template>

<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue';
import { useToast } from 'primevue/usetoast';
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';
import RadioButton from 'primevue/radiobutton';
import Checkbox from 'primevue/checkbox';
import QuotationTermsDialog from './QuotationTermsDialog.vue';
import QuotationSendEmailDialog from './QuotationSendEmailDialog.vue';
import {
  defaultPdfColumns,
  getQuotationPdfBlob,
  getQuotationPdfBase64,
  exportQuotationPdf
} from '../../utils/quotationPdf';
import { getSavedQuotationTerms } from '../../config/companyConfig';

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  quotation: {
    type: Object,
    default: () => null
  },
  currentUser: {
    type: Object,
    default: null
  }
});

defineEmits(['update:visible']);

const toast = useToast();

const orientation = ref('portrait');
const availableColumns = ref(JSON.parse(JSON.stringify(defaultPdfColumns)));
const customTerms = ref(getSavedQuotationTerms());

const showTermsDialog = ref(false);
const showSendEmailDialog = ref(false);

const loadingPreview = ref(false);
const previewUrl = ref('');
const currentPdfBase64 = ref('');

const cleanupPreviewUrl = () => {
  if (previewUrl.value && previewUrl.value.startsWith('blob:')) {
    URL.revokeObjectURL(previewUrl.value);
    previewUrl.value = '';
  }
};

const refreshPreview = async () => {
  if (!props.quotation) return;
  loadingPreview.value = true;
  cleanupPreviewUrl();

  try {
    const opts = {
      orientation: orientation.value,
      columns: availableColumns.value.filter(c => c.selected),
      terms: customTerms.value,
      currentUser: props.currentUser
    };

    const [blob, base64] = await Promise.all([
      getQuotationPdfBlob(props.quotation, opts),
      getQuotationPdfBase64(props.quotation, opts)
    ]);

    previewUrl.value = URL.createObjectURL(blob);
    currentPdfBase64.value = base64;
  } catch (err) {
    console.error('Lỗi tạo PDF preview:', err);
  } finally {
    loadingPreview.value = false;
  }
};

watch(
  () => props.visible,
  (val) => {
    if (val) {
      customTerms.value = getSavedQuotationTerms();
      refreshPreview();
    } else {
      cleanupPreviewUrl();
    }
  }
);

onMounted(() => {
  if (props.visible) refreshPreview();
});

onUnmounted(() => {
  cleanupPreviewUrl();
});

const handleTermsApplied = (newTerms) => {
  customTerms.value = newTerms;
  refreshPreview();
  toast.add({
    severity: 'info',
    summary: 'Đã cập nhật điều khoản',
    detail: 'Bản xem trước PDF đã áp dụng các điều khoản mới.',
    life: 3000
  });
};

const handleDownloadPdf = async () => {
  if (!props.quotation) return;
  try {
    const opts = {
      orientation: orientation.value,
      columns: availableColumns.value.filter(c => c.selected),
      terms: customTerms.value,
      currentUser: props.currentUser
    };
    await exportQuotationPdf(props.quotation, opts);
    toast.add({
      severity: 'success',
      summary: 'Đã xuất file PDF',
      detail: 'File báo giá PDF đã được tải về máy tính thành công.',
      life: 3000
    });
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi xuất PDF',
      detail: err.message || 'Không thể tạo file PDF.',
      life: 3000
    });
  }
};

const openSendEmailDialog = async () => {
  if (!currentPdfBase64.value) {
    await refreshPreview();
  }
  showSendEmailDialog.value = true;
};

</script>
