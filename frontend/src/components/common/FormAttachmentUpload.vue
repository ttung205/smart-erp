<template>
  <div class="bg-white border border-slate-200 rounded-[8px] p-4 shadow-sm space-y-3">
    <!-- Header Section -->
    <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
      <div class="flex items-center gap-2.5">
        <div class="w-7 h-7 rounded-[6px] flex items-center justify-center" :class="sectionTheme.iconBg">
          <i :class="[sectionTheme.icon, sectionTheme.iconColor]" class="text-xs"></i>
        </div>
        <div class="flex items-center gap-2">
          <span class="text-xs font-bold text-slate-800 uppercase tracking-wide">
            {{ title || 'Tài Liệu & Tệp Đính Kèm' }}
          </span>
          <Badge
            :value="totalAttachmentCount"
            :severity="totalAttachmentCount > 0 ? 'info' : 'secondary'"
            class="text-[10px] px-1.5 py-0.2"
          />
          <span class="text-[11px] text-slate-400 hidden sm:inline">
            — {{ subtitle || 'Đính kèm tệp tài liệu liên quan' }}
          </span>
        </div>
      </div>

      <!-- Quick stats -->
      <div v-if="totalAttachmentCount > 0" class="flex items-center gap-2">
        <span class="text-[11px] text-slate-500 font-mono">
          Tổng dung lượng: <strong class="text-slate-700">{{ formatTotalSize }}</strong>
        </span>
      </div>
    </div>

    <!-- PrimeVue FileUpload Component (Advanced Mode - Always 3 Buttons) -->
    <div class="space-y-3">
      <FileUpload
        ref="fileUploadRef"
        name="attachments[]"
        mode="advanced"
        :multiple="multiple"
        :accept="accept"
        :maxFileSize="maxFileSize"
        :customUpload="true"
        :auto="false"
        @uploader="onCustomUpload"
        @select="onFileSelect"
        @remove="onFileRemove"
        @clear="onFileClear"
        class="border border-slate-200 rounded-[6px] overflow-hidden"
        :pt="{
          content: { class: '!p-0 !border-0' }
        }"
      >
        <!-- Custom Header with 3 always-visible buttons -->
        <template #header="{ chooseCallback, uploadCallback, clearCallback, files }">
          <div class="flex flex-wrap items-center justify-between gap-2 p-2 bg-slate-50">
            <div class="flex items-center gap-2">
              <Button
                label="Chọn tệp"
                icon="pi pi-plus"
                size="small"
                class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 !text-xs !py-1.5 !px-3 rounded-[6px] !font-medium"
                :disabled="uploading"
                @click="chooseCallback()"
              />
              <Button
                label="Tải lên tệp đã chọn"
                icon="pi pi-cloud-upload"
                size="small"
                class="!bg-emerald-600 hover:!bg-emerald-700 !text-white !border-emerald-600 !text-xs !py-1.5 !px-3 rounded-[6px] !font-medium"
                :class="{ 'opacity-60 cursor-not-allowed': !files || files.length === 0 || uploading }"
                :disabled="!files || files.length === 0 || uploading"
                :loading="uploading"
                @click="uploadCallback()"
              />
              <Button
                label="Hủy tất cả"
                icon="pi pi-times"
                size="small"
                severity="danger"
                outlined
                class="!text-xs !py-1.5 !px-2.5 rounded-[6px] !border-red-300 hover:!bg-red-50 !text-red-600"
                :class="{ 'opacity-60 cursor-not-allowed': !files || files.length === 0 || uploading }"
                :disabled="!files || files.length === 0 || uploading"
                @click="clearCallback()"
              />
            </div>

            <div class="flex items-center gap-2 text-slate-400 text-xs pr-1">
              <i class="pi pi-info-circle text-[11px]"></i>
              <span class="text-[11px]">Tối đa {{ maxFileSizeMb }}MB/tệp (PDF, Word, Excel, Ảnh, ZIP)</span>
            </div>
          </div>
        </template>

        <!-- Staged Files Content (Only shows when files are picked) -->
        <template #content="{ files, removeFileCallback }">
          <div v-if="files && files.length > 0" class="p-2.5 bg-amber-50/60 border-t border-amber-200/60 space-y-2">
            <div class="flex items-center justify-between text-[11px] font-bold text-amber-800">
              <span class="flex items-center gap-1.5">
                <i class="pi pi-clock text-amber-600"></i>
                <span>Tệp chờ lưu ({{ files.length }}) - Bấm "Tải lên tệp đã chọn" để hoàn tất</span>
              </span>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">
              <div
                v-for="(f, idx) in files"
                :key="f.name + idx"
                class="flex items-center justify-between p-2 bg-white border border-amber-200 rounded-[6px] shadow-2xs"
              >
                <div class="flex items-center gap-2.5 min-w-0 flex-1 mr-2">
                  <div
                    class="w-7 h-7 rounded-[4px] flex items-center justify-center shrink-0 font-bold text-[10px]"
                    :class="getFileTypeMeta(f.name).bgClass"
                  >
                    <i :class="getFileTypeMeta(f.name).icon" class="text-xs"></i>
                  </div>
                  <div class="flex flex-col min-w-0 flex-1">
                    <span class="text-xs font-semibold text-slate-800 truncate" :title="f.name">{{ f.name }}</span>
                    <span class="text-[10px] text-slate-400">{{ formatFileSize(f.size) }}</span>
                  </div>
                </div>
                <Button
                  icon="pi pi-times"
                  size="small"
                  text
                  severity="danger"
                  class="!w-6 !h-6 !p-0 rounded-[4px] !text-red-400 hover:!text-red-600 hover:!bg-red-50 shrink-0"
                  title="Hủy tệp này"
                  :disabled="uploading"
                  @click="removeFileCallback(idx)"
                />
              </div>
            </div>
          </div>
        </template>

        <!-- Empty slot intentionally empty to save vertical space -->
        <template #empty>
          <div class="hidden"></div>
        </template>
      </FileUpload>

      <!-- Existing / Uploaded Attachments List (Directly under buttons) -->
      <div v-if="attachments.length > 0" class="space-y-2 pt-1">
        <div class="flex items-center justify-between">
          <span class="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
            <i class="pi pi-paperclip text-sky-600"></i>
            <span>Danh Sách Tệp Đính Kèm ({{ attachments.length }})</span>
          </span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2.5">
          <div
            v-for="(att, idx) in attachments"
            :key="att.id || idx"
            class="flex items-center justify-between p-2.5 bg-slate-50 hover:bg-sky-50/50 border border-slate-200 rounded-[6px] transition-all group"
          >
            <div class="flex items-center gap-2.5 min-w-0 flex-1 mr-2">
              <!-- Icon by file extension -->
              <div
                class="w-8 h-8 rounded-[6px] flex items-center justify-center shrink-0 font-bold text-[10px]"
                :class="getFileTypeMeta(att.name || att.file_name).bgClass"
              >
                <i :class="getFileTypeMeta(att.name || att.file_name).icon" class="text-xs"></i>
              </div>

              <!-- File Name & details -->
              <div class="flex flex-col min-w-0 flex-1">
                <span
                  class="text-xs font-semibold text-slate-800 truncate group-hover:text-sky-700 cursor-pointer"
                  :title="att.name || att.file_name"
                  @click="handlePreviewOrDownload(att)"
                >
                  {{ att.name || att.file_name }}
                </span>
                <div class="flex items-center gap-2 text-[10px] text-slate-400 mt-0.5">
                  <span>{{ formatFileSize(att.size || att.file_size) }}</span>
                  <span>•</span>
                  <span>{{ att.uploaded_at || att.created_at || 'Vừa xong' }}</span>
                </div>
              </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center gap-1 shrink-0">
              <Button
                icon="pi pi-download"
                size="small"
                text
                class="!w-6 !h-6 !p-0 rounded-[4px] !text-slate-500 hover:!text-sky-600 hover:!bg-white"
                title="Tải tệp về máy"
                @click="downloadFileDirectly(att)"
              />
              <Button
                icon="pi pi-eye"
                size="small"
                text
                class="!w-6 !h-6 !p-0 rounded-[4px] !text-slate-500 hover:!text-sky-600 hover:!bg-white"
                title="Xem tệp / hình ảnh"
                @click="handlePreviewOrDownload(att)"
              />
              <Button
                v-if="!readonly"
                icon="pi pi-trash"
                size="small"
                text
                severity="danger"
                class="!w-6 !h-6 !p-0 rounded-[4px] !text-red-400 hover:!text-red-600 hover:!bg-red-50"
                title="Xóa tệp đính kèm"
                @click="removeAttachment(idx, att)"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- Notice when no files uploaded yet -->
      <div v-else-if="!stagedFiles.length" class="py-2.5 px-3 bg-slate-50 border border-dashed border-slate-200 rounded-[6px] flex items-center justify-between text-xs text-slate-400">
        <span>Chưa có tệp đính kèm nào. Nhấn <strong>"Chọn tệp"</strong> ở trên để tải lên tài liệu.</span>
      </div>
    </div>

    <!-- Modal Preview Image/File -->
    <Dialog
      v-model:visible="showPreviewModal"
      modal
      :header="previewTitle"
      :style="{ width: '50vw', maxWidth: '700px' }"
    >
      <div class="flex flex-col items-center justify-center p-4">
        <img
          v-if="previewImageUrl"
          :src="previewImageUrl"
          :alt="previewTitle"
          class="max-h-[60vh] max-w-full rounded-[6px] object-contain shadow-sm border border-slate-200"
        />
        <div v-else class="text-center py-8 space-y-3">
          <div class="w-16 h-16 rounded-full bg-slate-100 flex items-center justify-center mx-auto text-slate-600">
            <i class="pi pi-file text-2xl"></i>
          </div>
          <p class="text-sm font-semibold text-slate-700">{{ previewTitle }}</p>
          <p class="text-xs text-slate-400">Tệp tài liệu này đã sẵn sàng để tải về máy.</p>
          <Button
            label="Tải Xuống Tệp"
            icon="pi pi-download"
            size="small"
            class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 !text-xs !py-2 !px-4 rounded-[6px]"
            @click="triggerSimulatedDownload"
          />
        </div>
      </div>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import FileUpload from 'primevue/fileupload';
import Button from 'primevue/button';
import Badge from 'primevue/badge';
import Dialog from 'primevue/dialog';
import { useToast } from 'primevue/usetoast';
import { salesApi } from '../../api/sales.api';

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => []
  },
  recordId: {
    type: [Number, String],
    default: null
  },
  title: {
    type: String,
    default: ''
  },
  subtitle: {
    type: String,
    default: ''
  },
  sectionType: {
    type: String,
    default: 'quotation' // quotation | sales_order | invoice
  },
  readonly: {
    type: Boolean,
    default: false
  },
  accept: {
    type: String,
    default: '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.png,.jpg,.jpeg,.zip,.rar'
  },
  maxFileSize: {
    type: Number,
    default: 20971520 // 20MB
  },
  multiple: {
    type: Boolean,
    default: true
  }
});

const emit = defineEmits(['update:modelValue', 'upload', 'remove']);
const toast = useToast();

const fileUploadRef = ref(null);
const stagedFiles = ref([]);
const uploading = ref(false);
const showPreviewModal = ref(false);
const previewTitle = ref('');
const previewImageUrl = ref('');
const activePreviewItem = ref(null);

const attachments = computed({
  get: () => props.modelValue || [],
  set: (val) => emit('update:modelValue', val)
});

const maxFileSizeMb = computed(() => Math.round(props.maxFileSize / (1024 * 1024)));

const totalAttachmentCount = computed(() => (attachments.value?.length || 0) + (stagedFiles.value?.length || 0));

const formatTotalSize = computed(() => {
  const total = (attachments.value || []).reduce((acc, curr) => acc + (Number(curr.size || curr.file_size) || 0), 0);
  return formatFileSize(total);
});

// Section specific theming
const sectionTheme = computed(() => {
  switch (props.sectionType) {
    case 'order':
    case 'sales_order':
      return {
        icon: 'pi pi-shopping-bag',
        iconBg: 'bg-indigo-50 text-indigo-700',
        iconColor: 'text-indigo-600'
      };
    case 'invoice':
      return {
        icon: 'pi pi-file-check',
        iconBg: 'bg-emerald-50 text-emerald-700',
        iconColor: 'text-emerald-600'
      };
    case 'quotation':
    default:
      return {
        icon: 'pi pi-paperclip',
        iconBg: 'bg-sky-50 text-sky-700',
        iconColor: 'text-sky-600'
      };
  }
});

// Helper for format file size
const formatFileSize = (bytes) => {
  if (!bytes || isNaN(bytes)) return '0 KB';
  const k = 1024;
  const sizes = ['Bytes', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  if (i < 0) return '0 KB';
  return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
};

// File type meta helper
const getFileTypeMeta = (filename = '') => {
  const ext = (filename.split('.').pop() || '').toLowerCase();
  switch (ext) {
    case 'pdf':
      return { icon: 'pi pi-file-pdf', bgClass: 'bg-red-50 text-red-600 border border-red-200' };
    case 'xls':
    case 'xlsx':
    case 'csv':
      return { icon: 'pi pi-file-excel', bgClass: 'bg-emerald-50 text-emerald-600 border border-emerald-200' };
    case 'doc':
    case 'docx':
      return { icon: 'pi pi-file-word', bgClass: 'bg-blue-50 text-blue-600 border border-blue-200' };
    case 'png':
    case 'jpg':
    case 'jpeg':
    case 'webp':
      return { icon: 'pi pi-image', bgClass: 'bg-amber-50 text-amber-600 border border-amber-200' };
    case 'zip':
    case 'rar':
    case '7z':
      return { icon: 'pi pi-box', bgClass: 'bg-purple-50 text-purple-600 border border-purple-200' };
    default:
      return { icon: 'pi pi-file', bgClass: 'bg-slate-100 text-slate-600 border border-slate-200' };
  }
};

// Selection event
const onFileSelect = (event) => {
  stagedFiles.value = event.files || [];
  toast.add({
    severity: 'info',
    summary: 'Đã chọn tệp',
    detail: `Đã chọn ${event.files.length} tệp. Nhấn "Tải lên tệp đã chọn" để lưu lên hệ thống.`,
    life: 3000
  });
};

const onFileRemove = (event) => {
  stagedFiles.value = stagedFiles.value.filter(f => f.name !== event.file.name);
};

const onFileClear = () => {
  stagedFiles.value = [];
};

// Handle Real Upload to Backend API
const onCustomUpload = async (event) => {
  const files = event.files || [];
  if (files.length === 0) return;

  uploading.value = true;
  try {
    const formData = new FormData();
    files.forEach((file) => {
      formData.append('files[]', file);
    });

    const secType = props.sectionType === 'order' ? 'sales_order' : props.sectionType;
    formData.append('attachable_type', secType);

    if (props.recordId && props.recordId !== 'create') {
      formData.append('attachable_id', props.recordId);
    }

    const res = await salesApi.uploadAttachments(formData);
    const data = res?.data || res;
    const uploadedList = Array.isArray(data) ? data : [data];

    const formatted = uploadedList.map((item) => ({
      id: item.id,
      name: item.file_name,
      file_name: item.file_name,
      size: item.file_size,
      file_size: item.file_size,
      file_path: item.file_path,
      file_url: item.file_url || item.url,
      mime_type: item.mime_type,
      uploaded_at: item.uploaded_at || new Date().toLocaleString('vi-VN'),
      status: 'UPLOADED'
    }));

    const updated = [...(attachments.value || []), ...formatted];
    emit('update:modelValue', updated);
    emit('upload', formatted);

    // Clear primevue upload component queue
    if (fileUploadRef.value) {
      fileUploadRef.value.clear();
    }
    stagedFiles.value = [];

    toast.add({
      severity: 'success',
      summary: 'Tải lên thành công',
      detail: `Đã lưu thành công ${files.length} tệp đính kèm vào hệ thống.`,
      life: 3000
    });
  } catch (err) {
    console.error('Lỗi tải lên tệp:', err);
    toast.add({
      severity: 'error',
      summary: 'Lỗi tải lên',
      detail: err?.message || 'Không thể tải lên tệp đính kèm. Vui lòng thử lại.',
      life: 4000
    });
  } finally {
    uploading.value = false;
  }
};

// Remove already uploaded attachment
const removeAttachment = async (index, item) => {
  try {
    if (item.id) {
      await salesApi.deleteAttachment(item.id).catch((e) => {
        console.warn('Lỗi xóa trên server:', e);
      });
    }

    const updated = [...(attachments.value || [])];
    updated.splice(index, 1);
    emit('update:modelValue', updated);
    emit('remove', item);

    toast.add({
      severity: 'warn',
      summary: 'Đã xóa tệp',
      detail: `Đã gỡ bỏ tệp "${item.name || item.file_name}" khỏi danh sách đính kèm.`,
      life: 2500
    });
  } catch (e) {
    console.error('Lỗi khi xóa tệp:', e);
  }
};

// Download file directly
const downloadFileDirectly = async (item) => {
  if (item.id) {
    const blob = await salesApi.downloadAttachment(item.id);
    const downloadUrl = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = downloadUrl;
    link.setAttribute('download', item.name || item.file_name || 'attachment');
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(downloadUrl);
  } else if (item.file_url || item.url) {
    window.open(item.file_url || item.url, '_blank');
  }
};

// Preview or download modal
const handlePreviewOrDownload = (item) => {
  activePreviewItem.value = item;
  previewTitle.value = item.name || item.file_name;
  const isImg = (item.mime_type && item.mime_type.startsWith('image/')) || /\.(png|jpg|jpeg|webp|gif)$/i.test(item.name || item.file_name);
  if (isImg && (item.file_url || item.url)) {
    previewImageUrl.value = item.file_url || item.url;
  } else {
    previewImageUrl.value = '';
  }
  showPreviewModal.value = true;
};

const triggerSimulatedDownload = () => {
  if (activePreviewItem.value) {
    downloadFileDirectly(activePreviewItem.value);
  }
  showPreviewModal.value = false;
};
</script>
