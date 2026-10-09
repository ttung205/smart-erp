<template>
  <div class="space-y-4 pb-12">
    <!-- 1. Header Toolbar -->
    <div class="flex items-center justify-between bg-white border border-slate-200 rounded-[8px] px-5 py-3 shadow-sm">
      <div class="flex items-center gap-3">
        <Button
          label="Quay lại"
          icon="pi pi-arrow-left"
          size="small"
          severity="secondary"
          outlined
          class="!text-xs !py-1.5 !px-3 rounded-[6px] !border-slate-300 !text-slate-700 hover:!bg-slate-100"
          @click="$router.push('/customer')"
        />

        <div class="flex flex-col">
          <h1 class="text-base font-bold text-slate-900 m-0 tracking-tight flex items-center gap-2">
            <span>{{ isEditMode ? `Khách Hàng: ${form.code} - ${form.name}` : 'Thêm Khách Hàng Mới' }}</span>
            <Tag
              :severity="form.status === 'ACTIVE' ? 'success' : 'danger'"
              :value="form.status === 'ACTIVE' ? 'Hoạt động' : 'Tạm khóa'"
              class="text-[11px] px-2 py-0.5 rounded-[4px]"
            />
          </h1>
          <span class="text-xs text-slate-400">
            {{ isEditMode ? 'Chỉnh sửa hồ sơ khách hàng, mã số thuế, loại khách hàng và danh sách người liên hệ' : 'Điền đầy đủ thông tin khách hàng doanh nghiệp và các thông tin liên hệ' }}
          </span>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="flex items-center gap-2.5">
        <Button
          label="Hủy"
          size="small"
          severity="secondary"
          outlined
          class="!text-xs !py-2 !px-3.5 rounded-[6px] !border-slate-300 !text-slate-700 hover:!bg-slate-100"
          @click="$router.push('/customer')"
        />
        <Button
          :label="isEditMode ? 'Lưu Thay Đổi' : 'Tạo Khách Hàng'"
          icon="pi pi-save"
          size="small"
          :loading="saving"
          class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 !text-xs !py-2 !px-4 rounded-[6px] !font-semibold shadow-sm"
          @click="handleSaveCustomer"
        />
      </div>
    </div>

    <!-- 2. Thông Tin Khách Hàng (Card Trên) -->
    <div class="bg-white border border-slate-200 rounded-[8px] p-5 shadow-sm space-y-4">
      <div class="flex items-center justify-between border-b border-slate-100 pb-3">
        <div class="flex items-center gap-2">
          <i class="pi pi-building text-sky-600 text-sm"></i>
          <span class="font-bold text-sm text-slate-800">Thông Tin Khách Hàng</span>
        </div>
        <div v-if="form.customer_type" class="flex items-center gap-1.5 text-xs text-slate-500">
          <span>Tiền tố mã định danh:</span>
          <span class="font-mono font-bold text-sky-700 bg-sky-50 px-2 py-0.5 rounded border border-sky-200">
            {{ currentTypePrefix }}
          </span>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
        <!-- Tên khách hàng -->
        <div class="md:col-span-6 flex flex-col gap-1.5">
          <label class="text-xs font-bold text-slate-700 uppercase tracking-wide">
            Tên khách hàng / Doanh nghiệp <span class="text-red-500">*</span>
          </label>
          <InputText
            v-model="form.name"
            placeholder="VD: CÔNG TY TNHH LONG SƠN - NHÀ MÁY XI MĂNG..."
            class="w-full text-xs rounded-[6px] h-10 px-3"
          />
        </div>

        <!-- Mã số thuế -->
        <div class="md:col-span-3 flex flex-col gap-1.5">
          <label class="text-xs font-bold text-slate-700 uppercase tracking-wide">
            Mã số thuế (MST)
          </label>
          <InputText
            v-model="form.tax_code"
            placeholder="VD: 0101234567..."
            class="w-full text-xs font-mono rounded-[6px] h-10 px-3"
          />
        </div>

        <!-- Loại khách hàng -->
        <div class="md:col-span-3 flex flex-col gap-1.5">
          <label class="text-xs font-bold text-slate-700 uppercase tracking-wide">
            Loại khách hàng <span class="text-red-500">*</span>
          </label>
          <Select
            v-model="form.customer_type"
            :options="customerTypeOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="-- Chọn loại khách hàng --"
            class="w-full text-xs rounded-[6px] h-10 flex items-center"
            @change="onCustomerTypeChange"
          />
        </div>

        <!-- Mã khách hàng (Tự động sinh: loại KH + 5 số ngẫu nhiên) -->
        <div class="md:col-span-4 flex flex-col gap-1.5">
          <label class="text-xs font-bold text-slate-700 uppercase tracking-wide flex items-center justify-between">
            <span>Mã khách hàng <span class="text-red-500">*</span></span>
            <span class="text-[11px] font-normal text-slate-400">
              ({{ currentTypePrefix }}-XXXXX)
            </span>
          </label>
          <div class="flex items-center gap-1.5">
            <InputText
              v-model="form.code"
              placeholder="VD: EA-12345..."
              class="w-full text-xs font-mono font-bold text-sky-800 bg-slate-50 rounded-[6px] h-10 px-3 border-slate-200"
              :readonly="isEditMode"
            />
            <Button
              v-if="!isEditMode"
              icon="pi pi-refresh"
              size="small"
              text
              class="!w-10 !h-10 !p-0 rounded-[6px] shrink-0 border border-slate-300 text-sky-600 hover:!bg-sky-50"
              title="Sinh lại mã ngẫu nhiên"
              @click="regenerateCode"
            />
          </div>
        </div>

        <!-- Trạng thái -->
        <div class="md:col-span-3 flex flex-col gap-1.5">
          <label class="text-xs font-bold text-slate-700 uppercase tracking-wide">
            Trạng thái <span class="text-red-500">*</span>
          </label>
          <Select
            v-model="form.status"
            :options="[
              { label: 'Hoạt động', value: 'ACTIVE' },
              { label: 'Tạm khóa', value: 'INACTIVE' }
            ]"
            optionLabel="label"
            optionValue="value"
            class="w-full text-xs rounded-[6px] h-10 flex items-center"
          />
        </div>

        <!-- Điện thoại hotline -->
        <div class="md:col-span-2 flex flex-col gap-1.5">
          <label class="text-xs font-bold text-slate-700 uppercase tracking-wide">
            Số điện thoại
          </label>
          <InputText
            v-model="form.phone"
            placeholder="VD: 024 7300..."
            class="w-full text-xs rounded-[6px] h-10 px-3"
          />
        </div>

        <!-- Email doanh nghiệp -->
        <div class="md:col-span-3 flex flex-col gap-1.5">
          <label class="text-xs font-bold text-slate-700 uppercase tracking-wide">
            Email doanh nghiệp
          </label>
          <InputText
            v-model="form.email"
            placeholder="VD: info@longson.vn..."
            class="w-full text-xs rounded-[6px] h-10 px-3"
          />
        </div>

        <!-- Địa chỉ khách hàng -->
        <div class="md:col-span-12 flex flex-col gap-1.5">
          <label class="text-xs font-bold text-slate-700 uppercase tracking-wide">
            Địa chỉ khách hàng / Nhà máy / Trụ sở
          </label>
          <InputText
            v-model="form.address"
            placeholder="Nhập số nhà, tên đường, khu công nghiệp, quận/huyện, tỉnh/thành phố..."
            class="w-full text-xs rounded-[6px] h-10 px-3"
          />
        </div>
      </div>
    </div>

    <!-- 3. Quản Lý Danh Sách Người Liên Hệ (Card Dưới) -->
    <div class="bg-white border border-slate-200 rounded-[8px] p-5 shadow-sm space-y-4">
      <div class="flex items-center justify-between border-b border-slate-100 pb-3">
        <div class="flex items-center gap-2">
          <i class="pi pi-users text-sky-600 text-sm"></i>
          <div>
            <span class="font-bold text-sm text-slate-800">Danh Sách Người Liên Hệ</span>
            <span class="text-xs text-slate-400 ml-2">({{ form.contacts.length }} người liên hệ)</span>
          </div>
        </div>

        <Button
          label="Thêm Người Liên Hệ"
          icon="pi pi-plus"
          size="small"
          class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 !text-xs !py-1.5 !px-3 rounded-[6px] !font-medium shadow-sm"
          @click="openAddContactDialog"
        />
      </div>

      <!-- Bảng liên hệ -->
      <DataTable
        :value="form.contacts"
        responsiveLayout="scroll"
        class="clean-datatable text-xs"
        showGridlines
      >
        <!-- Họ và tên -->
        <Column field="name" header="Họ và Tên" style="min-width: 180px">
          <template #body="{ data }">
            <div class="flex items-center gap-2">
              <span class="font-bold text-slate-800 text-xs">{{ data.name }}</span>
              <Tag
                v-if="data.is_primary"
                value="Liên hệ chính"
                severity="info"
                class="text-[10px] px-2 py-0.5 rounded-[4px]"
              />
            </div>
          </template>
        </Column>

        <!-- Chức vụ / Phòng ban -->
        <Column field="position" header="Chức Vụ / Phòng Ban" style="width: 180px">
          <template #body="{ data }">
            <span class="text-slate-600 text-xs">{{ data.position || '-' }}</span>
          </template>
        </Column>

        <!-- Điện thoại -->
        <Column field="phone" header="Số Điện Thoại" style="width: 140px">
          <template #body="{ data }">
            <span class="font-mono text-slate-700 text-xs">{{ data.phone || '-' }}</span>
          </template>
        </Column>

        <!-- Email -->
        <Column field="email" header="Email" style="width: 200px">
          <template #body="{ data }">
            <span class="text-slate-600 text-xs truncate block">{{ data.email || '-' }}</span>
          </template>
        </Column>

        <!-- Ghi chú -->
        <Column field="notes" header="Ghi Chú" style="min-width: 160px">
          <template #body="{ data }">
            <span class="text-slate-500 text-xs italic">{{ data.notes || '-' }}</span>
          </template>
        </Column>

        <!-- Thao tác: Sửa bằng Dialog, Xóa -->
        <Column header="Thao Tác" style="width: 100px" align="center">
          <template #body="{ data, index }">
            <div class="flex items-center justify-center gap-1.5">
              <!-- Nút Sửa qua Dialog -->
              <Button
                icon="pi pi-pencil"
                size="small"
                outlined
                severity="info"
                class="!w-7 !h-7 !p-0 rounded-[6px]"
                v-tooltip.bottom="'Chỉnh sửa người liên hệ'"
                @click="openEditContactDialog(data, index)"
              />
              <!-- Nút Xóa -->
              <Button
                icon="pi pi-trash"
                size="small"
                outlined
                severity="danger"
                class="!w-7 !h-7 !p-0 rounded-[6px]"
                v-tooltip.bottom="'Xóa người liên hệ'"
                @click="handleRemoveContact(data, index)"
              />
            </div>
          </template>
        </Column>

        <template #empty>
          <div class="py-8 text-center flex flex-col items-center gap-1.5">
            <i class="pi pi-address-book text-3xl text-slate-300"></i>
            <p class="text-xs font-semibold text-slate-600 m-0">Chưa có người liên hệ nào</p>
            <p class="text-[11px] text-slate-400 m-0">Nhấn "+ Thêm Người Liên Hệ" để thiết lập thông tin liên hệ cho khách hàng này.</p>
          </div>
        </template>
      </DataTable>
    </div>

    <!-- Dialog Thêm / Chỉnh Sửa Người Liên Hệ -->
    <CustomerContactFormDialog
      v-model:visible="showContactDialog"
      :is-edit="editingContactIndex !== null"
      :contact="contactForm"
      @save="handleSaveContact"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';

import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';

import CustomerContactFormDialog from '../../components/customer/CustomerContactFormDialog.vue';
import { catalogApi } from '../../api/catalog.api';
import { storage } from '../../utils/storage';
import { parseJwt } from '../../utils/jwt';

const route = useRoute();
const router = useRouter();
const toast = useToast();

const isEditMode = computed(() => !!route.params.id);
const saving = ref(false);

const customerTypeOptions = [
  { label: 'Retail (RE) - Khách mua lẻ / người tiêu dùng cuối', value: 'Retail', prefix: 'RE' },
  { label: 'Dealer (DE) - Đại lý / cửa hàng bán lại', value: 'Dealer', prefix: 'DE' },
  { label: 'Distributor (DI) - Nhà phân phối', value: 'Distributor', prefix: 'DI' },
  { label: 'Project / Business (PR) - Khách mua cho dự án / doanh nghiệp', value: 'Project / Business', prefix: 'PR' }
];

const typePrefixMap = {
  'Retail': 'RE',
  'Dealer': 'DE',
  'Distributor': 'DI',
  'Project / Business': 'PR',
  'RE': 'RE',
  'DE': 'DE',
  'DI': 'DI',
  'PR': 'PR'
};

const currentTypePrefix = computed(() => {
  return typePrefixMap[form.value.customer_type] || 'RE';
});

const extract5Digits = (code) => {
  if (!code) return null;
  const match = code.match(/(\d{5})$/);
  if (match) return match[1];
  const anyDigits = code.match(/\d+/);
  if (anyDigits) {
    return anyDigits[0].padStart(5, '0').slice(-5);
  }
  return null;
};

const generateRandomCode = (type) => {
  const prefix = typePrefixMap[type] || 'RE';
  const random5 = Math.floor(10000 + Math.random() * 90000);
  return `${prefix}-${random5}`;
};

const form = ref({
  id: null,
  code: '',
  name: '',
  customer_type: 'Retail',
  tax_code: '',
  phone: '',
  email: '',
  address: '',
  credit_limit: 0,
  status: 'ACTIVE',
  contacts: []
});

// Dialog liên hệ
const showContactDialog = ref(false);
const editingContactIndex = ref(null);
const contactForm = ref({
  id: null,
  name: '',
  position: '',
  phone: '',
  email: '',
  notes: '',
  is_primary: false
});

const onCustomerTypeChange = () => {
  const prefix = typePrefixMap[form.value.customer_type] || 'EA';
  const existingDigits = extract5Digits(form.value.code);
  const digits = existingDigits || Math.floor(10000 + Math.random() * 90000).toString();
  form.value.code = `${prefix}-${digits}`;
};

const regenerateCode = () => {
  const prefix = typePrefixMap[form.value.customer_type] || 'EA';
  const newDigits = Math.floor(10000 + Math.random() * 90000);
  form.value.code = `${prefix}-${newDigits}`;
};

const loadCustomerDetail = async () => {
  if (!isEditMode.value) {
    form.value.code = generateRandomCode(form.value.customer_type);
    return;
  }

  try {
    const res = await catalogApi.getCustomerById(route.params.id);
    const data = res.data;
    if (data) {
      form.value = {
        id: data.id,
        code: data.code,
        name: data.name,
        customer_type: data.customer_type || 'Retail',
        tax_code: data.tax_code || '',
        phone: data.phone || '',
        email: data.email || '',
        address: data.address || '',
        credit_limit: data.credit_limit || 0,
        status: data.status || 'ACTIVE',
        contacts: data.contacts || []
      };
    }
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi tải khách hàng',
      detail: err.message || 'Không thể tải thông tin khách hàng.',
      life: 4000
    });
    router.push('/customer');
  }
};

onMounted(() => {
  loadCustomerDetail();
});

// Lưu khách hàng
const handleSaveCustomer = async () => {
  if (!form.value.name?.trim()) {
    toast.add({
      severity: 'warn',
      summary: 'Thiếu thông tin',
      detail: 'Vui lòng nhập Tên khách hàng doanh nghiệp.',
      life: 3000
    });
    return;
  }

  if (!form.value.code?.trim()) {
    form.value.code = generateRandomCode(form.value.customer_type);
  }

  saving.value = true;
  try {
    if (isEditMode.value) {
      await catalogApi.updateCustomer(form.value.id, {
        code: form.value.code,
        name: form.value.name,
        customer_type: form.value.customer_type,
        tax_code: form.value.tax_code,
        phone: form.value.phone,
        email: form.value.email,
        address: form.value.address,
        credit_limit: form.value.credit_limit,
        status: form.value.status
      });

      toast.add({
        severity: 'success',
        summary: 'Cập nhật thành công',
        detail: `Hồ sơ khách hàng ${form.value.code} đã được cập nhật.`,
        life: 3000
      });
    } else {
      let createdBy = 'admin';
      try {
        const token = storage.getToken();
        if (token) {
          const u = parseJwt(token);
          createdBy = u?.name || u?.username || 'admin';
        }
      } catch (e) {}

      const res = await catalogApi.createCustomer({
        code: form.value.code,
        name: form.value.name,
        customer_type: form.value.customer_type,
        tax_code: form.value.tax_code,
        phone: form.value.phone,
        email: form.value.email,
        address: form.value.address,
        credit_limit: form.value.credit_limit,
        status: form.value.status,
        created_by: createdBy,
        contacts: form.value.contacts
      });

      toast.add({
        severity: 'success',
        summary: 'Tạo mới thành công',
        detail: `Khách hàng ${res.data?.code || form.value.code} đã được tạo.`,
        life: 3000
      });

      if (res.data && res.data.id) {
        form.value.id = res.data.id;
        if (res.data.contacts) form.value.contacts = res.data.contacts;
        router.replace(`/customer/${res.data.id}/edit`);
      }
    }
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi lưu khách hàng',
      detail: err.message || 'Không thể lưu hồ sơ khách hàng.',
      life: 4000
    });
  } finally {
    saving.value = false;
  }
};

// Quản lý Contact (Dialog)
const openAddContactDialog = () => {
  editingContactIndex.value = null;
  contactForm.value = {
    id: null,
    name: '',
    position: '',
    phone: '',
    email: '',
    notes: '',
    is_primary: form.value.contacts.length === 0
  };
  showContactDialog.value = true;
};

const openEditContactDialog = (contact, index) => {
  editingContactIndex.value = index;
  contactForm.value = { ...contact };
  showContactDialog.value = true;
};

const handleSaveContact = async (savedData) => {
  if (savedData) {
    contactForm.value = { ...savedData };
  }
  if (!contactForm.value.name?.trim()) {
    toast.add({
      severity: 'warn',
      summary: 'Thiếu thông tin',
      detail: 'Vui lòng nhập Họ và tên người liên hệ.',
      life: 3000
    });
    return;
  }

  // Nếu đặt là primary thì bỏ primary của các liên hệ khác
  if (contactForm.value.is_primary) {
    form.value.contacts.forEach((c, idx) => {
      if (editingContactIndex.value === null || idx !== editingContactIndex.value) {
        c.is_primary = false;
      }
    });
  }

  // Nếu đang ở Edit mode và đã có ID khách hàng trên backend
  if (isEditMode.value && form.value.id) {
    try {
      if (editingContactIndex.value !== null && contactForm.value.id) {
        // Cập nhật người liên hệ qua API
        const res = await catalogApi.updateCustomerContact(form.value.id, contactForm.value.id, contactForm.value);
        form.value.contacts[editingContactIndex.value] = { ...res.data };
        toast.add({
          severity: 'success',
          summary: 'Đã cập nhật',
          detail: `Đã cập nhật thông tin ${contactForm.value.name}.`,
          life: 3000
        });
      } else {
        // Thêm mới người liên hệ qua API
        const res = await catalogApi.addCustomerContact(form.value.id, contactForm.value);
        form.value.contacts.push(res.data);
        toast.add({
          severity: 'success',
          summary: 'Đã thêm',
          detail: `Đã thêm ${contactForm.value.name} vào danh sách liên hệ.`,
          life: 3000
        });
      }
    } catch (err) {
      toast.add({
        severity: 'error',
        summary: 'Lỗi lưu liên hệ',
        detail: err.message || 'Không thể lưu người liên hệ.',
        life: 4000
      });
      return;
    }
  } else {
    // Chưa tạo khách hàng, lưu vào local state của form
    if (editingContactIndex.value !== null) {
      form.value.contacts[editingContactIndex.value] = { ...contactForm.value };
    } else {
      form.value.contacts.push({ ...contactForm.value });
    }
  }

  showContactDialog.value = false;
};

const handleRemoveContact = async (contact, index) => {
  if (!confirm(`Bạn có chắc muốn xóa người liên hệ ${contact.name}?`)) {
    return;
  }

  if (isEditMode.value && form.value.id && contact.id) {
    try {
      await catalogApi.deleteCustomerContact(form.value.id, contact.id);
      form.value.contacts.splice(index, 1);
      toast.add({
        severity: 'success',
        summary: 'Đã xóa',
        detail: `Đã xóa người liên hệ ${contact.name}.`,
        life: 3000
      });
    } catch (err) {
      toast.add({
        severity: 'error',
        summary: 'Lỗi xóa liên hệ',
        detail: err.message || 'Không thể xóa người liên hệ.',
        life: 4000
      });
    }
  } else {
    form.value.contacts.splice(index, 1);
  }
};
</script>
