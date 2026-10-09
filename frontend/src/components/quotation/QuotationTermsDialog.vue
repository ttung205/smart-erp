<template>
  <Dialog
    :visible="visible"
    modal
    header="Điều khoản thanh toán và giao hàng"
    :style="{ width: '920px', maxWidth: '95vw' }"
    :closable="true"
    @update:visible="$emit('update:visible', $event)"
  >
    <div class="space-y-4 text-xs">
      <!-- 1. Header Row: Template Selector & Save New Template -->
      <div class="bg-slate-50 border border-slate-200 rounded-[6px] p-3 flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
        <!-- Template đã lưu -->
        <div class="flex-1 flex items-center gap-2">
          <span class="font-semibold text-slate-700 shrink-0">Template đã lưu:</span>
          <Select
            v-model="selectedTemplate"
            :options="templates"
            optionLabel="name"
            placeholder="Chọn template..."
            class="flex-1 !text-xs rounded-[6px]"
            size="small"
            @change="handleSelectTemplate"
          />
          <Button
            icon="pi pi-trash"
            severity="danger"
            text
            size="small"
            class="!p-1.5 shrink-0"
            v-tooltip.bottom="'Xóa template này'"
            :disabled="templates.length <= 1"
            @click="handleDeleteTemplate"
          />
        </div>

        <!-- Template mới -->
        <div class="flex-1 flex items-center gap-2">
          <InputText
            v-model="newTemplateName"
            placeholder="Nhập tên template mới..."
            class="flex-1 !text-xs rounded-[6px]"
            size="small"
          />
          <Button
            label="Lưu template"
            icon="pi pi-save"
            size="small"
            outlined
            severity="secondary"
            class="!text-xs shrink-0 rounded-[6px]"
            :disabled="!newTemplateName.trim()"
            @click="handleSaveNewTemplate"
          />
        </div>
      </div>

      <!-- 2. Main Two-Column Layout -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Cột Trái -->
        <div class="border border-slate-200 rounded-[6px] p-3.5 space-y-3 bg-white">
          <div class="flex items-center justify-between border-b border-slate-100 pb-2">
            <span class="font-bold text-slate-800 uppercase tracking-wide">Điều khoản - Cột trái</span>
            <Button
              label="Thêm dòng"
              icon="pi pi-plus"
              size="small"
              text
              severity="success"
              class="!text-xs !p-1 font-semibold"
              @click="addLeftTerm"
            />
          </div>

          <div class="space-y-2.5 max-h-[380px] overflow-y-auto pr-1">
            <div
              v-for="(item, idx) in localTerms.leftTerms"
              :key="item.id || idx"
              class="flex items-start gap-2 group"
            >
              <InputText
                v-model="item.label"
                placeholder="Nhãn (VD: Địa điểm:)"
                class="w-36 shrink-0 !text-xs font-semibold rounded-[6px]"
                size="small"
              />
              <Textarea
                v-model="item.value"
                rows="2"
                autoResize
                placeholder="Nội dung điều khoản..."
                class="flex-1 !text-xs rounded-[6px]"
              />
              <Button
                icon="pi pi-trash"
                severity="danger"
                text
                size="small"
                class="!p-1 text-slate-400 hover:text-red-600 shrink-0 mt-1"
                @click="removeLeftTerm(idx)"
              />
            </div>
          </div>
        </div>

        <!-- Cột Phải -->
        <div class="border border-slate-200 rounded-[6px] p-3.5 space-y-3 bg-white flex flex-col justify-between">
          <div class="space-y-3">
            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
              <span class="font-bold text-slate-800 uppercase tracking-wide">Điều khoản - Cột phải</span>
              <Button
                label="Thêm dòng"
                icon="pi pi-plus"
                size="small"
                text
                severity="success"
                class="!text-xs !p-1 font-semibold"
                @click="addRightTerm"
              />
            </div>

            <div class="space-y-2.5 max-h-[220px] overflow-y-auto pr-1">
              <div
                v-for="(item, idx) in localTerms.rightTerms"
                :key="item.id || idx"
                class="flex items-start gap-2 group"
              >
                <InputText
                  v-model="item.label"
                  placeholder="Nhãn (VD: Thanh toán:)"
                  class="w-36 shrink-0 !text-xs font-semibold rounded-[6px]"
                  size="small"
                />
                <Textarea
                  v-model="item.value"
                  rows="2"
                  autoResize
                  placeholder="Nội dung điều khoản..."
                  class="flex-1 !text-xs rounded-[6px]"
                />
                <Button
                  icon="pi pi-trash"
                  severity="danger"
                  text
                  size="small"
                  class="!p-1 text-slate-400 hover:text-red-600 shrink-0 mt-1"
                  @click="removeRightTerm(idx)"
                />
              </div>
            </div>
          </div>

          <!-- Box Thông Tin Chuyển Khoản -->
          <div class="bg-blue-50/70 border border-blue-200 rounded-[6px] p-2.5 space-y-1.5 text-xs text-slate-700 mt-2">
            <div class="flex items-center gap-1.5 font-bold text-blue-700">
              <i class="pi pi-lock text-[11px]"></i>
              <span>Thông tin chuyển khoản</span>
            </div>
            <div class="space-y-1">
              <div>
                <span class="text-slate-500">Đơn vị nhận:</span>
                <strong class="ml-1 text-slate-800">{{ localTerms.bankAccount?.beneficiary || 'Công Ty Thanh Tùng' }}</strong>
              </div>
              <div class="flex items-center gap-2">
                <div>
                  <span class="text-slate-500">Số tài khoản:</span>
                  <strong class="ml-1 text-indigo-700 font-mono">{{ localTerms.bankAccount?.accountNumber || '22028888888' }}</strong>
                </div>
              </div>
              <div>
                <span class="text-slate-500">Ngân hàng:</span>
                <span class="ml-1 text-slate-800">{{ localTerms.bankAccount?.bankName || 'BIDV' }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Footer Controls -->
    <template #footer>
      <div class="flex items-center justify-between w-full pt-2">
        <Button
          label="Đặt lại mặc định"
          icon="pi pi-refresh"
          severity="secondary"
          outlined
          size="small"
          class="!text-xs rounded-[6px]"
          @click="resetToDefault"
        />

        <div class="flex items-center gap-2">
          <Button
            label="Hủy"
            icon="pi pi-times"
            severity="secondary"
            outlined
            size="small"
            class="!text-xs rounded-[6px]"
            @click="$emit('update:visible', false)"
          />
          <Button
            label="Áp dụng"
            icon="pi pi-check"
            severity="success"
            size="small"
            class="!text-xs !bg-emerald-600 hover:!bg-emerald-700 !text-white rounded-[6px] !font-semibold border-none"
            @click="applyTerms"
          />
        </div>
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue';
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import Select from 'primevue/select';
import {
  defaultQuotationTerms,
  getSavedQuotationTerms,
  saveQuotationTerms,
  getSavedTermsTemplates,
  saveTermsTemplates
} from '../../config/companyConfig';

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  modelValue: {
    type: Object,
    default: () => null
  }
});

const emit = defineEmits(['update:visible', 'update:modelValue', 'apply']);

const localTerms = ref(JSON.parse(JSON.stringify(defaultQuotationTerms)));
const templates = ref([]);
const selectedTemplate = ref(null);
const newTemplateName = ref('');

const loadData = () => {
  templates.value = getSavedTermsTemplates();
  if (props.modelValue) {
    localTerms.value = JSON.parse(JSON.stringify(props.modelValue));
  } else {
    localTerms.value = getSavedQuotationTerms();
  }
};

watch(
  () => props.visible,
  (val) => {
    if (val) loadData();
  }
);

onMounted(() => {
  loadData();
});

const addLeftTerm = () => {
  localTerms.value.leftTerms.push({
    id: `l_${Date.now()}`,
    label: 'Điều khoản mới:',
    value: ''
  });
};

const removeLeftTerm = (idx) => {
  localTerms.value.leftTerms.splice(idx, 1);
};

const addRightTerm = () => {
  localTerms.value.rightTerms.push({
    id: `r_${Date.now()}`,
    label: 'Điều khoản mới:',
    value: ''
  });
};

const removeRightTerm = (idx) => {
  localTerms.value.rightTerms.splice(idx, 1);
};

const handleSelectTemplate = (e) => {
  if (e.value && e.value.terms) {
    localTerms.value = JSON.parse(JSON.stringify(e.value.terms));
  }
};

const handleSaveNewTemplate = () => {
  if (!newTemplateName.value.trim()) return;
  const newTpl = {
    name: newTemplateName.value.trim(),
    terms: JSON.parse(JSON.stringify(localTerms.value))
  };
  templates.value.push(newTpl);
  saveTermsTemplates(templates.value);
  selectedTemplate.value = newTpl;
  newTemplateName.value = '';
};

const handleDeleteTemplate = () => {
  if (!selectedTemplate.value) return;
  templates.value = templates.value.filter(t => t.name !== selectedTemplate.value.name);
  saveTermsTemplates(templates.value);
  selectedTemplate.value = templates.value[0] || null;
  if (selectedTemplate.value) {
    localTerms.value = JSON.parse(JSON.stringify(selectedTemplate.value.terms));
  }
};

const resetToDefault = () => {
  localTerms.value = JSON.parse(JSON.stringify(defaultQuotationTerms));
};

const applyTerms = () => {
  saveQuotationTerms(localTerms.value);
  emit('update:modelValue', JSON.parse(JSON.stringify(localTerms.value)));
  emit('apply', JSON.parse(JSON.stringify(localTerms.value)));
  emit('update:visible', false);
};
</script>
