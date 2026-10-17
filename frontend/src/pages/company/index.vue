<template>
  <div class="space-y-4 pb-8">
    <PageHeader title="Thông tin công ty" subtitle="Thông tin pháp lý và nhận diện sử dụng trên các chứng từ PDF">
      <template #actions>
        <Button label="Làm mới" icon="pi pi-refresh" size="small" severity="secondary" outlined :loading="loading" @click="load" />
        <Button v-if="canEdit" label="Chỉnh sửa" icon="pi pi-pencil" size="small" @click="router.push('/company/edit')" />
      </template>
    </PageHeader>

    <div v-if="error" class="bg-red-50 border border-red-200 text-red-700 rounded-[6px] p-4 text-sm">{{ error }}</div>
    <div v-else-if="!loading" class="bg-white border border-slate-200 rounded-[6px] p-6">
      <div class="flex flex-wrap items-center gap-6 border-b border-slate-200 pb-6 mb-6">
        <img v-if="company.logo_url" :src="company.logo_url" alt="Logo công ty" class="w-28 h-28 object-contain border border-slate-200 rounded-[6px] p-2" />
        <div v-else class="w-28 h-28 flex items-center justify-center border border-slate-200 rounded-[6px] bg-slate-50 text-slate-400"><i class="pi pi-image text-3xl" /></div>
        <div>
          <h2 class="text-lg font-bold text-slate-900">{{ company.name || 'Chưa thiết lập tên công ty' }}</h2>
          <p class="text-sm text-slate-500">{{ company.short_name || 'Chưa có tên viết tắt' }}</p>
          <p v-if="!company.name" class="text-xs text-amber-700 mt-2">Super Admin cần nhập thông tin công ty trước khi xuất chứng từ PDF.</p>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4 text-sm">
        <div v-for="field in fields" :key="field.key" class="border-b border-slate-100 pb-3">
          <div class="text-xs font-semibold text-slate-500 mb-1">{{ field.label }}</div>
          <div class="text-slate-900 break-words">{{ company[field.key] || '—' }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import Button from 'primevue/button';
import PageHeader from '../../components/common/PageHeader.vue';
import { companyApi } from '../../api/company.api';
import { setCompanyConfig } from '../../config/companyConfig';
import { hasPermission } from '../../utils/rbac';
import { storage } from '../../utils/storage';
import { parseJwt } from '../../utils/jwt';

const router = useRouter();
const company = ref({});
const loading = ref(false);
const error = ref('');
const canEdit = computed(() => hasPermission('company.manage', parseJwt(storage.getToken())));
const fields = [
  { key: 'tax_code', label: 'Mã số thuế' }, { key: 'address', label: 'Địa chỉ trụ sở' },
  { key: 'office_address', label: 'Địa chỉ văn phòng' }, { key: 'tel', label: 'Điện thoại' },
  { key: 'hotline', label: 'Hotline' }, { key: 'email', label: 'Email' },
  { key: 'accounting_email', label: 'Email kế toán' }, { key: 'website', label: 'Website' },
  { key: 'bank_beneficiary', label: 'Chủ tài khoản' }, { key: 'bank_account_number', label: 'Số tài khoản' },
  { key: 'bank_name', label: 'Ngân hàng' }, { key: 'bank_branch', label: 'Chi nhánh ngân hàng' }
];
async function load() {
  loading.value = true;
  error.value = '';
  try {
    const response = await companyApi.get();
    company.value = response.data || {};
    setCompanyConfig(company.value);
  } catch (err) { error.value = err.message || 'Không tải được thông tin công ty.'; }
  finally { loading.value = false; }
}
onMounted(load);
</script>
