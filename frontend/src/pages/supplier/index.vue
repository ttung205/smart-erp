<template>
  <div class="space-y-4 pb-12">
    <!-- Header -->
    <PageHeader
      title="Quản Lý Nhà Cung Cấp (Supplier Management)"
      subtitle="Hồ sơ đối tác cung ứng thiết bị, vật tư, thông tin tài khoản ngân hàng và điều khoản thanh toán mua hàng"
      badge="Phân Hệ Đối Tác"
      badge-severity="info"
    >
      <template #actions>
        <Button
          label="Thêm Nhà Cung Cấp Mới"
          icon="pi pi-plus"
          size="small"
          class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 !text-xs !py-2 !px-3.5 rounded-[6px] !font-semibold shadow-sm"
          @click="openCreateDialog"
        />
      </template>
    </PageHeader>

    <!-- Toolbar Filters -->
    <Toolbar class="bg-white border border-slate-200 rounded-[8px] px-4 py-3 shadow-none">
      <template #start>
        <div class="flex items-center gap-3 flex-wrap">
          <IconField>
            <InputIcon class="pi pi-search text-slate-400" />
            <InputText
              v-model="searchQuery"
              placeholder="Tìm theo tên, mã NCC, MST, người liên hệ, SĐT..."
              class="w-80 rounded-[6px] text-xs h-9"
              @keydown.enter="fetchSuppliers"
            />
          </IconField>

          <Select
            v-model="typeFilter"
            :options="typeFilterOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Lọc loại NCC..."
            class="w-48 rounded-[6px] text-xs h-9 flex items-center"
            @change="fetchSuppliers"
          />

          <Select
            v-model="groupFilter"
            :options="groupFilterOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Lọc nhóm NCC..."
            class="w-52 rounded-[6px] text-xs h-9 flex items-center"
            @change="fetchSuppliers"
          />

          <Select
            v-model="statusFilter"
            :options="statusFilterOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Lọc trạng thái..."
            class="w-44 rounded-[6px] text-xs h-9 flex items-center"
            @change="fetchSuppliers"
          />

          <Button
            v-if="searchQuery || typeFilter || groupFilter || statusFilter"
            label="Đặt lại"
            icon="pi pi-filter-slash"
            size="small"
            text
            class="!text-xs !text-slate-500 hover:!text-slate-800"
            @click="resetFilters"
          />
        </div>
      </template>

      <template #end>
        <div class="flex items-center gap-2 text-xs text-slate-500 font-medium">
          <span>Tổng số: <strong class="text-slate-800">{{ suppliers.length }}</strong> nhà cung cấp</span>
        </div>
      </template>
    </Toolbar>

    <!-- Data Table -->
    <CommonDataTable
      :value="suppliers"
      :columns="tableColumns"
      :loading="loading"
      :rows="10"
      :rowsPerPageOptions="[10, 20, 50]"
      emptyIcon="pi pi-truck"
      emptyTitle="Chưa có nhà cung cấp nào"
      emptySubtitle="Nhấn nút 'Thêm Nhà Cung Cấp Mới' ở trên để tự tạo hồ sơ nhà cung cấp của bạn."
    >
      <!-- 1. Mã nhà cung cấp -->
      <template #col-code="{ data }">
        <span
          class="font-mono text-xs font-bold text-sky-700 bg-sky-50 px-2.5 py-1 rounded-[4px] border border-sky-200 cursor-pointer hover:underline inline-block"
          @click="openEditDialog(data)"
        >
          {{ data.code }}
        </span>
      </template>

      <!-- 2. Tên nhà cung cấp -->
      <template #col-name="{ data }">
        <div>
          <span
            class="font-bold text-slate-800 text-xs hover:text-sky-700 cursor-pointer block"
            @click="openEditDialog(data)"
          >
            {{ data.name }}
          </span>
          <div v-if="data.address" class="text-[11px] text-slate-500 truncate max-w-xs mt-0.5">
            <i class="pi pi-map-marker text-[10px] mr-1 text-slate-400"></i>{{ data.address }}
          </div>
        </div>
      </template>

      <!-- 3. MST -->
      <template #col-tax_code="{ data }">
        <span v-if="data.tax_code" class="font-mono text-xs text-slate-700 bg-slate-50 px-2 py-0.5 rounded border border-slate-200">
          {{ data.tax_code }}
        </span>
        <span v-else class="text-slate-400 italic text-[11px]">-</span>
      </template>

      <!-- 4. Nhóm NCC -->
      <template #col-supplier_group="{ data }">
        <span class="text-xs text-slate-700 font-medium px-2 py-0.5 rounded bg-slate-100 border border-slate-200">
          {{ data.supplier_group || 'Vật tư & Thiết bị' }}
        </span>
      </template>

      <!-- 5. Người liên hệ & SĐT -->
      <template #col-contact_person="{ data }">
        <div>
          <div class="text-xs font-semibold text-slate-800">
            {{ data.contact_person || '-' }}
          </div>
          <div v-if="data.contact_phone || data.phone" class="text-[11px] text-slate-500 flex items-center gap-1 mt-0.5">
            <i class="pi pi-phone text-[10px] text-sky-600"></i>
            <span class="font-mono">{{ data.contact_phone || data.phone }}</span>
          </div>
        </div>
      </template>

      <!-- 6. Ngân hàng & STK -->
      <template #col-bank_info="{ data }">
        <div v-if="data.bank_name || data.bank_account_number">
          <div class="text-xs font-medium text-slate-800">
            {{ data.bank_name || 'Ngân hàng' }}
          </div>
          <div v-if="data.bank_account_number" class="text-[11px] font-mono text-slate-600">
            STK: <strong class="text-indigo-700">{{ data.bank_account_number }}</strong>
          </div>
        </div>
        <span v-else class="text-slate-400 italic text-[11px]">-</span>
      </template>

      <!-- 7. Điều khoản thanh toán -->
      <template #col-payment_terms="{ data }">
        <Badge
          :value="data.payment_terms || 'Net 30'"
          severity="secondary"
          class="!font-mono !text-[11px]"
        />
      </template>

      <!-- 8. Hạn mức tín dụng -->
      <template #col-credit_limit="{ data }">
        <span
          v-if="data.credit_limit && Number(data.credit_limit) > 0"
          class="font-mono text-xs font-semibold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200 inline-block"
        >
          {{ formatCurrency(data.credit_limit) }}
        </span>
        <span v-else class="text-slate-400 italic text-[11px]">Không giới hạn</span>
      </template>

      <!-- 9. Trạng thái -->
      <template #col-status="{ data }">
        <Badge
          :severity="data.status === 'ACTIVE' ? 'success' : 'danger'"
          :value="data.status === 'ACTIVE' ? 'Hoạt động' : 'Tạm khóa'"
        />
      </template>

      <!-- 9. Hành động -->
      <template #col-actions="{ data }">
        <div class="flex items-center gap-1.5 justify-center">
          <Button
            icon="pi pi-pencil"
            size="small"
            text
            rounded
            class="!w-7 !h-7 !text-sky-600 hover:!bg-sky-50"
            v-tooltip.top="'Chỉnh sửa'"
            @click="openEditDialog(data)"
          />
          <Button
            icon="pi pi-trash"
            size="small"
            text
            rounded
            class="!w-7 !h-7 !text-rose-600 hover:!bg-rose-50"
            v-tooltip.top="'Xóa nhà cung cấp'"
            @click="confirmDelete(data)"
          />
        </div>
      </template>
    </CommonDataTable>

    <!-- Dialog Thêm mới / Chỉnh sửa Nhà Cung Cấp -->
    <Dialog
      v-model:visible="dialogVisible"
      :header="isEditing ? 'Cập Nhật Hồ Sơ Nhà Cung Cấp' : 'Thêm Nhà Cung Cấp Chuẩn Mới'"
      :modal="true"
      :style="{ width: '720px' }"
      class="p-dialog-sm"
    >
      <form @submit.prevent="saveSupplier" class="space-y-4 pt-2">
        <!-- 1. Thông tin pháp lý & Định danh -->
        <div class="bg-slate-50 border border-slate-200 rounded-[8px] p-3.5 space-y-3">
          <div class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
            <i class="pi pi-id-card text-sky-600"></i>
            Thông Tin Pháp Lý & Định Danh
          </div>

          <div class="grid grid-cols-12 gap-3">
            <div class="col-span-5">
              <label class="block text-xs font-semibold text-slate-700 mb-1">
                Phân Loại Nhà Cung Cấp <span class="text-rose-500">*</span>
              </label>
              <Select
                v-model="form.supplier_type"
                :options="supplierTypeOptions"
                optionLabel="label"
                optionValue="value"
                class="w-full text-xs h-9 flex items-center rounded-[6px]"
                @change="onSupplierTypeChange"
              />
            </div>

            <div class="col-span-7">
              <div class="flex items-center justify-between mb-1">
                <label class="block text-xs font-semibold text-slate-700">
                  Mã Nhà Cung Cấp
                </label>
                <Button
                  v-if="!isEditing"
                  type="button"
                  label="Sinh mã ngẫu nhiên"
                  icon="pi pi-refresh"
                  size="small"
                  text
                  class="!text-[11px] !p-0 !text-sky-600 hover:!underline h-auto font-medium"
                  @click="generateRandomCode"
                />
              </div>
              <InputText
                v-model="form.code"
                :placeholder="form.supplier_type === 'MANUFACTURER' ? 'Tự sinh nếu trống (vd: MS-1234)' : 'Tự sinh nếu trống (vd: TS-1234)'"
                class="w-full text-xs font-mono h-9 rounded-[6px]"
              />
            </div>

            <div class="col-span-12">
              <label class="block text-xs font-semibold text-slate-700 mb-1">
                Tên Nhà Cung Cấp / Doanh Nghiệp <span class="text-rose-500">*</span>
              </label>
              <InputText
                v-model="form.name"
                placeholder="Nhập tên đầy đủ của đơn vị cung ứng..."
                class="w-full text-xs h-9 rounded-[6px]"
                required
              />
            </div>

            <div class="col-span-6">
              <label class="block text-xs font-semibold text-slate-700 mb-1">
                Mã Số Thuế (MST)
              </label>
              <InputText
                v-model="form.tax_code"
                placeholder="Mã số thuế doanh nghiệp..."
                class="w-full text-xs font-mono h-9"
              />
            </div>

            <div class="col-span-6">
              <label class="block text-xs font-semibold text-slate-700 mb-1">
                Nhóm / Phân Loại Cung Ứng
              </label>
              <Select
                v-model="form.supplier_group"
                :options="groupOptions"
                class="w-full text-xs h-9 flex items-center"
              />
            </div>

            <div class="col-span-12">
              <label class="block text-xs font-semibold text-slate-700 mb-1">
                Địa Chỉ Trụ Sở / Kho Hàng
              </label>
              <InputText
                v-model="form.address"
                placeholder="Số nhà, đường, phường/xã, quận/huyện, tỉnh/thành..."
                class="w-full text-xs h-9"
              />
            </div>
          </div>
        </div>

        <!-- 2. Thông tin liên hệ -->
        <div class="bg-slate-50 border border-slate-200 rounded-[8px] p-3.5 space-y-3">
          <div class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
            <i class="pi pi-user text-emerald-600"></i>
            Đầu Mối Liên Hệ Giao Dịch
          </div>

          <div class="grid grid-cols-12 gap-3">
            <div class="col-span-4">
              <label class="block text-xs font-semibold text-slate-700 mb-1">
                Người Liên Hệ Đại Diện
              </label>
              <InputText
                v-model="form.contact_person"
                placeholder="Họ và tên..."
                class="w-full text-xs h-9"
              />
            </div>

            <div class="col-span-4">
              <label class="block text-xs font-semibold text-slate-700 mb-1">
                Số Điện Thoại Liên Hệ
              </label>
              <InputText
                v-model="form.contact_phone"
                placeholder="SĐT di động..."
                class="w-full text-xs font-mono h-9"
              />
            </div>

            <div class="col-span-4">
              <label class="block text-xs font-semibold text-slate-700 mb-1">
                Email Liên Hệ
              </label>
              <InputText
                v-model="form.contact_email"
                type="email"
                placeholder="email@ncc.com..."
                class="w-full text-xs h-9"
              />
            </div>
          </div>
        </div>

        <!-- 3. Tài khoản ngân hàng & Thanh toán -->
        <div class="bg-slate-50 border border-slate-200 rounded-[8px] p-3.5 space-y-3">
          <div class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
            <i class="pi pi-credit-card text-indigo-600"></i>
            Thông Tin Tài Khoản Ngân Hàng & Thương Mại
          </div>

          <div class="grid grid-cols-12 gap-3">
            <div class="col-span-4">
              <label class="block text-xs font-semibold text-slate-700 mb-1">
                Tên Ngân Hàng
              </label>
              <InputText
                v-model="form.bank_name"
                placeholder="vd: Vietcombank, Techcombank..."
                class="w-full text-xs h-9"
              />
            </div>

            <div class="col-span-4">
              <label class="block text-xs font-semibold text-slate-700 mb-1">
                Số Tài Khoản (STK)
              </label>
              <InputText
                v-model="form.bank_account_number"
                placeholder="Số tài khoản thụ hưởng..."
                class="w-full text-xs font-mono h-9"
              />
            </div>

            <div class="col-span-4">
              <label class="block text-xs font-semibold text-slate-700 mb-1">
                Chủ Tài Khoản
              </label>
              <InputText
                v-model="form.bank_account_holder"
                placeholder="Tên chủ tài khoản..."
                class="w-full text-xs h-9"
              />
            </div>

            <div class="col-span-4">
              <label class="block text-xs font-semibold text-slate-700 mb-1">
                Chi Nhánh Ngân Hàng
              </label>
              <InputText
                v-model="form.bank_branch"
                placeholder="Chi nhánh mở thẻ..."
                class="w-full text-xs h-9"
              />
            </div>

            <div class="col-span-4">
              <label class="block text-xs font-semibold text-slate-700 mb-1">
                Điều Khoản Thanh Toán
              </label>
              <Select
                v-model="form.payment_terms"
                :options="paymentTermsOptions"
                class="w-full text-xs h-9 flex items-center"
              />
            </div>

            <div class="col-span-4">
              <label class="block text-xs font-semibold text-slate-700 mb-1">
                Hạn Mức Tín Dụng (VNĐ)
              </label>
              <InputNumber
                v-model="form.credit_limit"
                mode="currency"
                currency="VND"
                locale="vi-VN"
                placeholder="0 ₫"
                class="w-full text-xs h-9 flex items-center font-mono"
              />
            </div>

            <div class="col-span-4">
              <label class="block text-xs font-semibold text-slate-700 mb-1">
                Trạng Thái Hoạt Động
              </label>
              <Select
                v-model="form.status"
                :options="[
                  { label: 'Hoạt động (ACTIVE)', value: 'ACTIVE' },
                  { label: 'Tạm khóa (INACTIVE)', value: 'INACTIVE' }
                ]"
                optionLabel="label"
                optionValue="value"
                class="w-full text-xs h-9 flex items-center"
              />
            </div>
          </div>
        </div>

        <!-- 4. Ghi chú -->
        <div>
          <label class="block text-xs font-semibold text-slate-700 mb-1">
            Ghi Chú Đánh Giá / Lưu Ý Bổ Sung
          </label>
          <Textarea
            v-model="form.notes"
            rows="2"
            placeholder="Lưu ý về sản phẩm, năng lực cung ứng, chính sách chiết khấu..."
            class="w-full text-xs rounded-[6px]"
          />
        </div>

        <!-- Nút hành động -->
        <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200">
          <Button
            type="button"
            label="Hủy bỏ"
            size="small"
            severity="secondary"
            class="!text-xs rounded-[6px]"
            @click="dialogVisible = false"
          />
          <Button
            type="submit"
            :label="isEditing ? 'Lưu Thay Đổi' : 'Thêm Nhà Cung Cấp'"
            icon="pi pi-check"
            size="small"
            :loading="saving"
            class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 !text-xs rounded-[6px]"
          />
        </div>
      </form>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useToast } from 'primevue/usetoast';
import { catalogApi } from '../../api/catalog.api';

import PageHeader from '../../components/common/PageHeader.vue';
import CommonDataTable from '../../components/common/CommonDataTable.vue';
import Toolbar from 'primevue/toolbar';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import InputNumber from 'primevue/inputnumber';
import Button from 'primevue/button';
import Badge from 'primevue/badge';
import Dialog from 'primevue/dialog';
import Textarea from 'primevue/textarea';
import { formatCurrency } from '../../utils/format';

const toast = useToast();

const loading = ref(false);
const saving = ref(false);
const suppliers = ref([]);
const searchQuery = ref('');
const typeFilter = ref('');
const groupFilter = ref('');
const statusFilter = ref('');

const dialogVisible = ref(false);
const isEditing = ref(false);
const editingId = ref(null);

const form = reactive({
  code: '',
  name: '',
  tax_code: '',
  supplier_group: 'Vật tư & Thiết bị',
  supplier_type: 'TRADING',
  phone: '',
  email: '',
  address: '',
  contact_person: '',
  contact_phone: '',
  contact_email: '',
  bank_name: '',
  bank_account_number: '',
  bank_account_holder: '',
  bank_branch: '',
  payment_terms: 'Net 30',
  credit_limit: 0,
  status: 'ACTIVE',
  notes: ''
});

const supplierTypeOptions = [
  { label: 'Trading Supplier (Thương mại - TS)', value: 'TRADING' },
  { label: 'Manufacturer Supplier (Sản xuất - MS)', value: 'MANUFACTURER' }
];

const typeFilterOptions = [
  { label: 'Tất cả phân loại', value: '' },
  { label: 'Trading Supplier (TS)', value: 'TRADING' },
  { label: 'Manufacturer Supplier (MS)', value: 'MANUFACTURER' }
];

const groupOptions = [
  'Vật tư & Thiết bị',
  'Nguyên vật liệu điện tử',
  'Máy móc & Công nghệ',
  'Vật liệu xây dựng & Cơ khí',
  'Dịch vụ kỹ thuật & Logistics'
];

const paymentTermsOptions = [
  'Net 15',
  'Net 30',
  'Net 45',
  'Net 60',
  'COD (Thanh toán khi nhận hàng)',
  'T/T 100% (Trả trước)'
];

const groupFilterOptions = [
  { label: 'Tất cả nhóm NCC', value: '' },
  ...groupOptions.map(g => ({ label: g, value: g }))
];

const statusFilterOptions = [
  { label: 'Tất cả trạng thái', value: '' },
  { label: 'Hoạt động', value: 'ACTIVE' },
  { label: 'Tạm khóa', value: 'INACTIVE' }
];

const tableColumns = [
  { field: 'code', header: 'Mã NCC', sortable: true, width: '130px' },
  { field: 'name', header: 'Tên Nhà Cung Cấp', sortable: true, width: '260px' },
  { field: 'tax_code', header: 'MST', sortable: true, width: '120px' },
  { field: 'supplier_group', header: 'Nhóm Cung Ứng', sortable: true, width: '160px' },
  { field: 'contact_person', header: 'Đầu Mối Liên Hệ', sortable: true, width: '180px' },
  { field: 'bank_info', header: 'Tài Khoản Ngân Hàng', width: '180px' },
  { field: 'payment_terms', header: 'Điều Khoản', width: '110px' },
  { field: 'credit_limit', header: 'Hạn Mức Tín Dụng', sortable: true, width: '150px' },
  { field: 'status', header: 'Trạng Thái', width: '110px' },
  { field: 'actions', header: 'Thao Tác', width: '100px' }
];

const generateRandomCode = async () => {
  try {
    const res = await catalogApi.generateSupplierCode(form.supplier_type);
    if (res && res.data && res.data.code) {
      form.code = res.data.code;
      return;
    }
  } catch (err) {
    console.warn('Lỗi khi sinh mã từ máy chủ, fallback sinh mã tại client:', err);
  }
  const prefix = form.supplier_type === 'MANUFACTURER' ? 'MS-' : 'TS-';
  const rand = Math.floor(1000 + Math.random() * 9000);
  form.code = `${prefix}${rand}`;
};

const onSupplierTypeChange = () => {
  if (!isEditing.value) {
    generateRandomCode();
  }
};

const fetchSuppliers = async () => {
  loading.value = true;
  try {
    const params = {};
    if (searchQuery.value) params.search = searchQuery.value;
    if (typeFilter.value) params.supplier_type = typeFilter.value;
    if (groupFilter.value) params.supplier_group = groupFilter.value;
    if (statusFilter.value) params.status = statusFilter.value;

    const res = await catalogApi.getSuppliers(params);
    if (res && res.success) {
      suppliers.value = res.data || [];
    }
  } catch (err) {
    console.error('Lỗi khi tải danh sách nhà cung cấp:', err);
    toast.add({
      severity: 'error',
      summary: 'Lỗi',
      detail: 'Không thể kết nối danh sách nhà cung cấp.',
      life: 3000
    });
  } finally {
    loading.value = false;
  }
};

const resetFilters = () => {
  searchQuery.value = '';
  typeFilter.value = '';
  groupFilter.value = '';
  statusFilter.value = '';
  fetchSuppliers();
};

const openCreateDialog = () => {
  isEditing.value = false;
  editingId.value = null;
  Object.assign(form, {
    code: '',
    name: '',
    tax_code: '',
    supplier_group: 'Vật tư & Thiết bị',
    supplier_type: 'TRADING',
    phone: '',
    email: '',
    address: '',
    contact_person: '',
    contact_phone: '',
    contact_email: '',
    bank_name: '',
    bank_account_number: '',
    bank_account_holder: '',
    bank_branch: '',
    payment_terms: 'Net 30',
    credit_limit: 0,
    status: 'ACTIVE',
    notes: ''
  });
  generateRandomCode();
  dialogVisible.value = true;
};

const openEditDialog = (data) => {
  isEditing.value = true;
  editingId.value = data.id;
  const deducedType = data.supplier_type || (data.code && data.code.startsWith('MS-') ? 'MANUFACTURER' : 'TRADING');
  Object.assign(form, {
    code: data.code || '',
    name: data.name || '',
    tax_code: data.tax_code || '',
    supplier_group: data.supplier_group || 'Vật tư & Thiết bị',
    supplier_type: deducedType,
    phone: data.phone || '',
    email: data.email || '',
    address: data.address || '',
    contact_person: data.contact_person || '',
    contact_phone: data.contact_phone || '',
    contact_email: data.contact_email || '',
    bank_name: data.bank_name || '',
    bank_account_number: data.bank_account_number || '',
    bank_account_holder: data.bank_account_holder || '',
    bank_branch: data.bank_branch || '',
    payment_terms: data.payment_terms || 'Net 30',
    credit_limit: data.credit_limit || 0,
    status: data.status || 'ACTIVE',
    notes: data.notes || ''
  });
  dialogVisible.value = true;
};

const saveSupplier = async () => {
  if (!form.name.trim()) {
    toast.add({
      severity: 'warn',
      summary: 'Cảnh báo',
      detail: 'Vui lòng nhập tên nhà cung cấp.',
      life: 3000
    });
    return;
  }

  saving.value = true;
  try {
    let res;
    if (isEditing.value && editingId.value) {
      res = await catalogApi.updateSupplier(editingId.value, form);
    } else {
      res = await catalogApi.createSupplier(form);
    }

    if (res && res.success) {
      toast.add({
        severity: 'success',
        summary: 'Thành công',
        detail: res.message || 'Đã lưu thông tin nhà cung cấp thành công.',
        life: 3000
      });
      dialogVisible.value = false;
      await fetchSuppliers();
    } else {
      toast.add({
        severity: 'error',
        summary: 'Lỗi',
        detail: res?.error?.message || 'Không thể lưu nhà cung cấp.',
        life: 4000
      });
    }
  } catch (err) {
    console.error('Lỗi khi lưu nhà cung cấp:', err);
    toast.add({
      severity: 'error',
      summary: 'Lỗi hệ thống',
      detail: err.message || 'Có lỗi xảy ra khi lưu nhà cung cấp.',
      life: 4000
    });
  } finally {
    saving.value = false;
  }
};

const confirmDelete = async (data) => {
  if (!confirm(`Bạn có chắc chắn muốn xóa nhà cung cấp "${data.name}" (${data.code})?`)) {
    return;
  }

  try {
    const res = await catalogApi.deleteSupplier(data.id);
    if (res && res.success) {
      toast.add({
        severity: 'success',
        summary: 'Đã xóa',
        detail: `Đã xóa nhà cung cấp ${data.name}.`,
        life: 3000
      });
      await fetchSuppliers();
    }
  } catch (err) {
    console.error('Lỗi khi xóa nhà cung cấp:', err);
    toast.add({
      severity: 'error',
      summary: 'Lỗi',
      detail: 'Không thể xóa nhà cung cấp.',
      life: 3000
    });
  }
};

onMounted(() => {
  fetchSuppliers();
});
</script>
