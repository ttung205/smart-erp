<template>
  <Dialog
    :visible="visible"
    modal
    header="Gửi Email Báo Giá Tới Khách Hàng"
    :style="{ width: '680px', maxWidth: '95vw' }"
    :closable="true"
    @update:visible="$emit('update:visible', $event)"
  >
    <div class="space-y-4 text-xs">
      <!-- Info notice -->
      <div class="p-3 bg-blue-50 border border-blue-200 rounded-[6px] text-blue-800 flex items-start gap-2.5">
        <i class="pi pi-info-circle text-base mt-0.5 text-blue-600 shrink-0"></i>
        <div class="space-y-1">
          <p class="font-semibold m-0">Hệ thống sẽ gửi email kèm file PDF báo giá chính thức</p>
          <p class="text-[11px] text-blue-700 m-0 leading-relaxed">
            Trong môi trường thử nghiệm Local, bạn có thể kiểm tra email và tải file PDF đính kèm tại <strong class="font-mono underline">http://localhost:8025 (Mailpit)</strong>.
          </p>
        </div>
      </div>

      <!-- Form Inputs -->
      <div class="space-y-3">
        <!-- Người nhận (To) -->
        <div class="space-y-1">
          <label class="font-semibold text-slate-700">Người nhận (To) <span class="text-red-500">*</span></label>
          <IconField>
            <InputIcon class="pi pi-envelope text-slate-400" />
            <InputText
              v-model="form.to"
              placeholder="VD: khachhang@congty.vn"
              class="w-full !text-xs rounded-[6px]"
              size="small"
            />
          </IconField>
        </div>

        <!-- CC -->
        <div class="space-y-1">
          <label class="font-semibold text-slate-700">Đồng kính gửi (CC)</label>
          <InputText
            v-model="form.cc"
            placeholder="VD: ketoan@thanhtung.vn, seth@thanhtung.vn (cách nhau bởi dấu phẩy)"
            class="w-full !text-xs rounded-[6px]"
            size="small"
          />
        </div>

        <!-- Tiêu đề (Subject) -->
        <div class="space-y-1">
          <label class="font-semibold text-slate-700">Tiêu đề thư (Subject) <span class="text-red-500">*</span></label>
          <InputText
            v-model="form.subject"
            placeholder="Nhập tiêu đề thư..."
            class="w-full !text-xs font-medium rounded-[6px]"
            size="small"
          />
        </div>

        <!-- File đính kèm -->
        <div class="space-y-1">
          <label class="font-semibold text-slate-700">File đính kèm tự động:</label>
          <div class="flex items-center gap-2 p-2 bg-slate-50 border border-slate-200 rounded-[6px]">
            <i class="pi pi-file-pdf text-red-600 text-lg"></i>
            <span class="font-mono text-xs font-semibold text-slate-800">{{ attachmentFileName }}</span>
            <Tag value="PDF Document" severity="danger" class="!text-[10px] !px-1.5 !py-0.2" />
          </div>
        </div>

        <!-- Nội dung thư (Body) -->
        <div class="space-y-1">
          <label class="font-semibold text-slate-700">Nội dung thư (Email Body)</label>
          <Textarea
            v-model="form.body"
            rows="6"
            autoResize
            placeholder="Nội dung lời nhắn gửi tới khách hàng..."
            class="w-full !text-xs rounded-[6px]"
          />
        </div>
      </div>
    </div>

    <!-- Footer Controls -->
    <template #footer>
      <div class="flex items-center justify-end gap-2 pt-2">
        <Button
          label="Hủy bỏ"
          icon="pi pi-times"
          severity="secondary"
          outlined
          size="small"
          class="!text-xs rounded-[6px]"
          :disabled="sending"
          @click="$emit('update:visible', false)"
        />
        <Button
          label="Gửi Email Khách Hàng"
          icon="pi pi-send"
          severity="success"
          size="small"
          class="!text-xs !bg-indigo-600 hover:!bg-indigo-700 !text-white rounded-[6px] !font-semibold border-none"
          :loading="sending"
          @click="handleSendEmail"
        />
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import { ref, reactive, watch, computed } from 'vue';
import { useToast } from 'primevue/usetoast';
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import Tag from 'primevue/tag';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import { notificationApi } from '../../api/notification.api';
import { getCompanyConfig } from '../../config/companyConfig';

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  quotation: {
    type: Object,
    default: () => null
  },
  pdfBase64: {
    type: String,
    default: ''
  },
  currentUser: {
    type: Object,
    default: null
  }
});

const emit = defineEmits(['update:visible', 'sent']);

const toast = useToast();
const sending = ref(false);

const company = getCompanyConfig();

const form = reactive({
  to: '',
  cc: '',
  subject: '',
  body: ''
});

const attachmentFileName = computed(() => {
  const quoteNo = props.quotation?.quotation_number || props.quotation?.id || 'Bao_Gia';
  return `Bao_Gia_${quoteNo}.pdf`;
});

const initFormData = () => {
  if (!props.quotation) return;
  const q = props.quotation;
  const quoteNo = q.quotation_number || q.id || 'QT-DRAFT';
  const custName = q.customer_name || q.customer?.name || 'Quý Khách Hàng';
  const contactName = q.contact_name || q.contact?.name || 'Quý Khách';
  const targetEmail = q.customer_email || q.contact_email || q.email || '';

  form.to = targetEmail;
  form.cc = 'sales@thanhtung.vn';
  form.subject = `[${company.shortName || 'Thanh Tùng'}] Báo giá thương mại #${quoteNo} - ${custName}`;
  form.body = `Kính gửi: ${contactName} - ${custName},\n\n` +
    `${company.name} xin trân trọng gửi tới Quý Công ty bản báo giá chi tiết #${quoteNo} đính kèm theo thư này.\n\n` +
    `Quý khách vui lòng xem xét các điều kiện thương mại, thời gian giao hàng và danh mục sản phẩm chi tiết trong file PDF đính kèm.\n` +
    `Nếu cần thêm thông tin hoặc đàm phán chính sách giá dự án, xin vui lòng phản hồi email này hoặc liên hệ hotline: ${company.hotline}.\n\n` +
    `Trân trọng cảm ơn và kính chúc Quý Công ty ngày càng phát triển thịnh vượng!\n\n` +
    `--\n` +
    `${props.currentUser?.name || 'Phòng Kinh Doanh'}\n` +
    `${company.name}\n` +
    `Hotline: ${company.hotline} | Website: ${company.website}`;
};

watch(
  () => props.visible,
  (val) => {
    if (val) initFormData();
  }
);

const handleSendEmail = async () => {
  if (!form.to || !form.to.trim()) {
    toast.add({
      severity: 'warn',
      summary: 'Thiếu email nhận',
      detail: 'Vui lòng nhập địa chỉ email người nhận (To).',
      life: 3000
    });
    return;
  }

  if (!form.subject || !form.subject.trim()) {
    toast.add({
      severity: 'warn',
      summary: 'Thiếu tiêu đề',
      detail: 'Vui lòng nhập tiêu đề thư (Subject).',
      life: 3000
    });
    return;
  }

  sending.value = true;
  try {
    const payload = {
      to: form.to.split(',').map(s => s.trim()).filter(Boolean),
      cc: form.cc ? form.cc.split(',').map(s => s.trim()).filter(Boolean) : [],
      subject: form.subject.trim(),
      body: form.body,
      senderName: company.shortName ? `${company.shortName} ERP` : 'Smart ERP',
      attachments: []
    };

    if (props.pdfBase64) {
      payload.attachments.push({
        filename: attachmentFileName.value,
        content: props.pdfBase64,
        contentType: 'application/pdf'
      });
    }

    const res = await notificationApi.sendEmail(payload);

    toast.add({
      severity: 'success',
      summary: 'Đã gửi email thành công',
      detail: `Email báo giá đã được chuyển tiếp tới ${form.to}. Bạn có thể kiểm tra tại Mailpit (http://localhost:8025).`,
      life: 5000
    });

    emit('sent', res);
    emit('update:visible', false);
  } catch (err) {
    console.error('Lỗi gửi email:', err);
    toast.add({
      severity: 'error',
      summary: 'Lỗi gửi email',
      detail: err.message || 'Không thể kết nối tới Notification Service để gửi mail.',
      life: 4000
    });
  } finally {
    sending.value = false;
  }
};
</script>
