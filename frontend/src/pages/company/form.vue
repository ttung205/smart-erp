<template>
  <div class="space-y-4 pb-8">
    <PageHeader title="Chỉnh sửa công ty" subtitle="Thông tin này được dùng chung khi tạo báo giá và chứng từ bán hàng">
      <template #actions>
        <Button label="Quay lại" icon="pi pi-arrow-left" size="small" severity="secondary" outlined @click="router.push('/company')" />
        <Button label="Lưu thông tin" icon="pi pi-save" size="small" :loading="saving" @click="save" />
      </template>
    </PageHeader>

    <div v-if="error" class="bg-red-50 border border-red-200 text-red-700 rounded-[6px] p-3 text-sm">{{ error }}</div>
    <div class="bg-white border border-slate-200 rounded-[6px] p-6 space-y-6">
      <section class="space-y-3">
        <h2 class="font-semibold text-slate-900 border-b border-slate-200 pb-2">Nhận diện công ty</h2>
        <div class="flex flex-wrap items-center gap-4">
          <img v-if="form.logo_url" :src="form.logo_url" alt="Logo công ty" class="w-28 h-28 object-contain border border-slate-200 rounded-[6px] p-2" />
          <div v-else class="w-28 h-28 flex items-center justify-center border border-slate-200 rounded-[6px] bg-slate-50 text-slate-400"><i class="pi pi-image text-3xl" /></div>
          <div class="space-y-2">
            <FileUpload mode="basic" accept="image/png,image/jpeg" :max-file-size="2097152" custom-upload choose-label="Chọn logo" class="rounded-[6px]" @select="uploadLogo" />
            <Button v-if="form.logo_url" label="Xóa logo" icon="pi pi-trash" severity="danger" text size="small" @click="removeLogo" />
            <p class="text-xs text-slate-500">PNG hoặc JPG; tối đa 2 MB.</p>
          </div>
        </div>
      </section>

      <section v-for="section in sections" :key="section.title" class="space-y-3">
        <h2 class="font-semibold text-slate-900 border-b border-slate-200 pb-2">{{ section.title }}</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div v-for="field in section.fields" :key="field.key" class="flex flex-col gap-1">
            <label :for="field.key" class="text-xs font-semibold text-slate-700">{{ field.label }}<span v-if="field.required" class="text-red-600"> *</span></label>
            <InputText :id="field.key" v-model="form[field.key]" size="small" class="w-full rounded-[6px]" :maxlength="field.max || 255" />
          </div>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import FileUpload from 'primevue/fileupload';
import PageHeader from '../../components/common/PageHeader.vue';
import { companyApi } from '../../api/company.api';
import { setCompanyConfig } from '../../config/companyConfig';

const router = useRouter();
const toast = useToast();
const saving = ref(false);
const error = ref('');
const form = ref({});
const sections = [
  { title: 'Thông tin pháp lý', fields: [
    { key: 'name', label: 'Tên công ty', required: true }, { key: 'short_name', label: 'Tên viết tắt', max: 100 },
    { key: 'tax_code', label: 'Mã số thuế', max: 30 }, { key: 'address', label: 'Địa chỉ trụ sở', max: 500 },
    { key: 'office_address', label: 'Địa chỉ văn phòng', max: 500 }
  ] },
  { title: 'Liên hệ', fields: [
    { key: 'tel', label: 'Điện thoại', max: 50 }, { key: 'hotline', label: 'Hotline', max: 50 },
    { key: 'email', label: 'Email' }, { key: 'accounting_email', label: 'Email kế toán' },
    { key: 'website', label: 'Website' }
  ] },
  { title: 'Tài khoản ngân hàng', fields: [
    { key: 'bank_beneficiary', label: 'Chủ tài khoản' }, { key: 'bank_account_number', label: 'Số tài khoản', max: 100 },
    { key: 'bank_name', label: 'Ngân hàng' }, { key: 'bank_branch', label: 'Chi nhánh ngân hàng' }
  ] }
];
const fieldNames = sections.flatMap(section => section.fields.map(field => field.key));

async function load() {
  try {
    const response = await companyApi.get();
    form.value = response.data || {};
    setCompanyConfig(form.value);
  } catch (err) { error.value = err.message || 'Không tải được thông tin công ty.'; }
}
async function save() {
  if (!form.value.name?.trim()) { error.value = 'Vui lòng nhập tên công ty.'; return; }
  saving.value = true;
  error.value = '';
  try {
    const payload = Object.fromEntries(fieldNames.map(key => [key, form.value[key] || null]));
    const response = await companyApi.update(payload);
    form.value = response.data;
    setCompanyConfig(form.value);
    toast.add({ severity: 'success', summary: 'Đã lưu thông tin công ty', life: 3000 });
    router.push('/company');
  } catch (err) { error.value = err.message || 'Không lưu được thông tin công ty.'; }
  finally { saving.value = false; }
}
async function uploadLogo(event) {
  const file = event.files?.[0];
  if (!file) return;
  error.value = '';
  try {
    const response = await companyApi.uploadLogo(file);
    form.value = { ...form.value, ...response.data };
    setCompanyConfig(form.value);
    toast.add({ severity: 'success', summary: 'Đã cập nhật logo', life: 3000 });
  } catch (err) { error.value = err.message || 'Không tải được logo.'; }
}
async function removeLogo() {
  error.value = '';
  try {
    const response = await companyApi.deleteLogo();
    form.value = { ...form.value, ...response.data };
    setCompanyConfig(form.value);
  } catch (err) { error.value = err.message || 'Không xóa được logo.'; }
}
onMounted(load);
</script>
