<template>
  <div class="space-y-4 pb-12">
    <!-- Header -->
    <PageHeader
      title="Quản Lý Khách Hàng (Customer Management)"
      subtitle="Danh mục khách hàng B2B, quản lý nhiều người liên hệ, công nợ và thông tin đối tác kinh doanh"
      badge="Phân Hệ Đối Tác"
      badge-severity="info"
    >
      <template #actions>
        <Button
          label="Thêm Khách Hàng Mới"
          icon="pi pi-plus"
          size="small"
          class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 !text-xs !py-2 !px-3.5 rounded-[6px] !font-semibold shadow-sm"
          @click="openCreateCustomerDialog"
        />
      </template>
    </PageHeader>

    <!-- Toolbar Filters -->
    <Toolbar class="bg-white border border-slate-200 rounded-[8px] px-4 py-3 shadow-none">
      <template #start>
        <div class="flex items-center gap-3">
          <IconField>
            <InputIcon class="pi pi-search text-slate-400" />
            <InputText
              v-model="searchQuery"
              placeholder="Tìm theo mã, tên công ty, MST, SĐT..."
              class="w-80 rounded-[6px] text-xs h-9"
            />
          </IconField>

          <Select
            v-model="statusFilter"
            :options="statusOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Lọc trạng thái..."
            class="w-48 rounded-[6px] text-xs h-9 flex items-center"
          />
        </div>
      </template>

      <template #end>
        <div class="flex items-center gap-2 text-xs text-slate-500 font-medium">
          <span>Tổng số: <strong class="text-slate-800">{{ filteredCustomers.length }}</strong> khách hàng</span>
        </div>
      </template>
    </Toolbar>

    <!-- Data Table -->
    <div class="bg-white border border-slate-200 rounded-[8px] overflow-hidden shadow-sm">
      <DataTable
        :value="filteredCustomers"
        :loading="loading"
        paginator
        :rows="10"
        :rowsPerPageOptions="[10, 20, 50]"
        responsiveLayout="scroll"
        class="clean-datatable text-xs"
        showGridlines
      >
        <!-- Mã khách hàng -->
        <Column field="code" header="Mã Khách Hàng" style="width: 150px" sortable>
          <template #body="{ data }">
            <span
              class="font-mono text-xs font-bold text-sky-700 bg-sky-50 px-2.5 py-1 rounded-[4px] border border-sky-200 cursor-pointer hover:underline"
              @click="openEditCustomerDialog(data)"
            >
              {{ data.code }}
            </span>
          </template>
        </Column>

        <!-- Tên khách hàng & MST -->
        <Column field="name" header="Tên Khách Hàng Doanh Nghiệp" sortable style="min-width: 280px">
          <template #body="{ data }">
            <div class="flex flex-col gap-0.5">
              <span class="font-bold text-slate-800 text-xs">{{ data.name }}</span>
              <span v-if="data.tax_code" class="text-[11px] text-slate-400 font-mono">
                MST: {{ data.tax_code }}
              </span>
            </div>
          </template>
        </Column>

        <!-- Người liên hệ (Quan hệ 1-N) -->
        <Column header="Người Liên Hệ" style="width: 240px">
          <template #body="{ data }">
            <div class="flex flex-col gap-1">
              <div v-if="getPrimaryContact(data)" class="flex items-center gap-1.5">
                <i class="pi pi-user text-sky-600 text-xs"></i>
                <span class="font-semibold text-slate-800 text-xs">{{ getPrimaryContact(data).name }}</span>
              </div>
              <div v-if="getPrimaryContact(data)?.phone" class="text-[11px] text-slate-500 font-mono">
                ĐT: {{ getPrimaryContact(data).phone }}
              </div>

              <!-- Button mở quản lý danh sách liên hệ -->
              <div class="mt-1">
                <button
                  type="button"
                  class="inline-flex items-center gap-1 text-[11px] text-sky-600 hover:text-sky-800 font-medium hover:underline bg-transparent border-0 p-0 cursor-pointer"
                  @click="openContactsDialog(data)"
                >
                  <i class="pi pi-id-card text-[11px]"></i>
                  <span>{{ data.contacts?.length || 0 }} người liên hệ (Chi tiết)</span>
                </button>
              </div>
            </div>
          </template>
        </Column>

        <!-- Điện thoại & Email công ty -->
        <Column header="Liên Hệ Công Ty" style="width: 200px">
          <template #body="{ data }">
            <div class="flex flex-col gap-0.5 text-xs text-slate-600 font-mono">
              <span v-if="data.phone"><i class="pi pi-phone text-[10px] text-slate-400 mr-1"></i>{{ data.phone }}</span>
              <span v-if="data.email" class="text-slate-500 text-[11px] truncate"><i class="pi pi-envelope text-[10px] text-slate-400 mr-1"></i>{{ data.email }}</span>
              <span v-if="!data.phone && !data.email" class="text-slate-400 italic">Chưa cập nhật</span>
            </div>
          </template>
        </Column>

        <!-- Địa chỉ -->
        <Column field="address" header="Địa Chỉ Trụ Sở / Nhà Máy" style="min-width: 200px">
          <template #body="{ data }">
            <span class="text-slate-700 text-xs line-clamp-2" :title="data.address">
              {{ data.address || '-' }}
            </span>
          </template>
        </Column>

        <!-- Hạn mức công nợ -->
        <Column field="credit_limit" header="Hạn Mức Công Nợ" style="width: 150px" align="right" sortable>
          <template #body="{ data }">
            <span class="font-mono text-xs font-bold text-slate-800">
              {{ formatCurrency(data.credit_limit) }}
            </span>
          </template>
        </Column>

        <!-- Trạng thái -->
        <Column field="status" header="Trạng Thái" style="width: 120px" align="center" sortable>
          <template #body="{ data }">
            <Tag
              :severity="data.status === 'ACTIVE' ? 'success' : 'danger'"
              :value="data.status === 'ACTIVE' ? 'Hoạt động' : 'Tạm khóa'"
              class="text-[11px] px-2 py-0.5 rounded-[4px]"
            />
          </template>
        </Column>

        <!-- Thao tác -->
        <Column header="Thao Tác" style="width: 140px" align="center">
          <template #body="{ data }">
            <div class="flex items-center justify-center gap-1">
              <!-- Nút Quản lý người liên hệ -->
              <Button
                icon="pi pi-users"
                size="small"
                text
                class="!text-xs !p-1.5 rounded-[6px] !text-sky-600 hover:!bg-sky-50"
                v-tooltip.bottom="'Quản lý người liên hệ'"
                @click="openContactsDialog(data)"
              />

              <!-- Nút Sửa -->
              <Button
                icon="pi pi-pencil"
                size="small"
                text
                class="!text-xs !p-1.5 rounded-[6px] !text-emerald-600 hover:!bg-emerald-50"
                v-tooltip.bottom="'Sửa thông tin khách hàng'"
                @click="openEditCustomerDialog(data)"
              />

              <!-- Nút Xóa -->
              <Button
                icon="pi pi-trash"
                size="small"
                text
                class="!text-xs !p-1.5 rounded-[6px] !text-red-600 hover:!bg-red-50"
                v-tooltip.bottom="'Xóa khách hàng'"
                @click="confirmDeleteCustomer(data)"
              />
            </div>
          </template>
        </Column>

        <template #empty>
          <div class="py-12 text-center flex flex-col items-center gap-2">
            <i class="pi pi-users text-4xl text-slate-300"></i>
            <p class="text-sm font-semibold text-slate-700 m-0">Chưa có khách hàng nào</p>
            <p class="text-xs text-slate-400 m-0">Nhấn nút "Thêm Khách Hàng Mới" để tạo hồ sơ khách hàng doanh nghiệp.</p>
          </div>
        </template>
      </DataTable>
    </div>

    <!-- Modal 1: Thêm / Sửa Khách Hàng -->
    <Dialog
      v-model:visible="showCustomerDialog"
      modal
      :header="isEditMode ? 'Chỉnh Sửa Hồ Sơ Khách Hàng' : 'Thêm Khách Hàng Doanh Nghiệp Mới'"
      :style="{ width: '600px' }"
      class="rounded-[8px]"
    >
      <div class="flex flex-col gap-4 pt-2 text-xs">
        <div class="grid grid-cols-2 gap-3.5">
          <!-- Mã khách hàng -->
          <div class="flex flex-col gap-1.5">
            <label class="font-bold text-slate-700 uppercase tracking-wide">
              Mã khách hàng <span class="text-red-500">*</span>
            </label>
            <InputText
              v-model="customerForm.code"
              placeholder="VD: EA-02592, FPT-01..."
              class="w-full text-xs font-mono font-bold rounded-[6px] h-9 px-3"
              :disabled="isEditMode"
            />
          </div>

          <!-- Trạng thái -->
          <div class="flex flex-col gap-1.5">
            <label class="font-bold text-slate-700 uppercase tracking-wide">
              Trạng thái <span class="text-red-500">*</span>
            </label>
            <Select
              v-model="customerForm.status"
              :options="[
                { label: 'Hoạt động', value: 'ACTIVE' },
                { label: 'Tạm khóa', value: 'INACTIVE' }
              ]"
              optionLabel="label"
              optionValue="value"
              class="w-full text-xs rounded-[6px] h-9 flex items-center"
            />
          </div>
        </div>

        <!-- Tên khách hàng -->
        <div class="flex flex-col gap-1.5">
          <label class="font-bold text-slate-700 uppercase tracking-wide">
            Tên doanh nghiệp khách hàng <span class="text-red-500">*</span>
          </label>
          <InputText
            v-model="customerForm.name"
            placeholder="VD: CÔNG TY TNHH LONG SƠN - NHÀ MÁY XI MĂNG..."
            class="w-full text-xs rounded-[6px] h-9 px-3"
          />
        </div>

        <div class="grid grid-cols-2 gap-3.5">
          <!-- Mã số thuế -->
          <div class="flex flex-col gap-1.5">
            <label class="font-bold text-slate-700 uppercase tracking-wide">Mã số thuế (MST)</label>
            <InputText
              v-model="customerForm.tax_code"
              placeholder="0101234567..."
              class="w-full text-xs font-mono rounded-[6px] h-9 px-3"
            />
          </div>

          <!-- Hạn mức công nợ -->
          <div class="flex flex-col gap-1.5">
            <label class="font-bold text-slate-700 uppercase tracking-wide">Hạn mức công nợ (VNĐ)</label>
            <InputNumber
              v-model="customerForm.credit_limit"
              placeholder="0"
              class="w-full text-xs font-mono h-9"
              inputClass="text-xs font-mono py-1 rounded-[6px] h-9"
            />
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3.5">
          <!-- Điện thoại hotline -->
          <div class="flex flex-col gap-1.5">
            <label class="font-bold text-slate-700 uppercase tracking-wide">Số điện thoại hotline</label>
            <InputText
              v-model="customerForm.phone"
              placeholder="024 7300 7300..."
              class="w-full text-xs rounded-[6px] h-9 px-3"
            />
          </div>

          <!-- Email -->
          <div class="flex flex-col gap-1.5">
            <label class="font-bold text-slate-700 uppercase tracking-wide">Email liên hệ</label>
            <InputText
              v-model="customerForm.email"
              placeholder="contact@doanhnghiep.com..."
              class="w-full text-xs rounded-[6px] h-9 px-3"
            />
          </div>
        </div>

        <!-- Địa chỉ -->
        <div class="flex flex-col gap-1.5">
          <label class="font-bold text-slate-700 uppercase tracking-wide">Địa chỉ trụ sở / Nhà máy</label>
          <InputText
            v-model="customerForm.address"
            placeholder="Số nhà, đường, quận/huyện, tỉnh/thành phố..."
            class="w-full text-xs rounded-[6px] h-9 px-3"
          />
        </div>
      </div>

      <template #footer>
        <div class="flex justify-end gap-2 pt-3">
          <Button
            label="Hủy"
            text
            size="small"
            severity="secondary"
            class="rounded-[6px]"
            @click="showCustomerDialog = false"
          />
          <Button
            :label="isEditMode ? 'Lưu Thay Đổi' : 'Tạo Khách Hàng'"
            icon="pi pi-check"
            size="small"
            class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 rounded-[6px] !px-4"
            @click="handleSaveCustomer"
          />
        </div>
      </template>
    </Dialog>

    <!-- Modal 2: Quản Lý Danh Sách Người Liên Hệ (Contacts) của 1 Khách Hàng -->
    <Dialog
      v-model:visible="showContactsDialog"
      modal
      :header="`Danh Sách Người Liên Hệ - ${selectedCustomer?.name || ''}`"
      :style="{ width: '750px' }"
      class="rounded-[8px]"
    >
      <div class="space-y-4 pt-1 text-xs">
        <!-- Form thêm người liên hệ mới -->
        <div class="bg-slate-50 border border-slate-200 rounded-[6px] p-3.5 space-y-3">
          <div class="font-bold text-slate-800 text-xs flex items-center gap-1.5">
            <i class="pi pi-user-plus text-sky-600"></i>
            <span>Thêm người liên hệ mới cho khách hàng này</span>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-4 gap-2.5">
            <InputText
              v-model="newContact.name"
              placeholder="Họ và tên *..."
              class="w-full text-xs rounded-[4px] h-8 px-2.5"
            />
            <InputText
              v-model="newContact.position"
              placeholder="Chức vụ/Phòng ban..."
              class="w-full text-xs rounded-[4px] h-8 px-2.5"
            />
            <InputText
              v-model="newContact.phone"
              placeholder="Điện thoại di động..."
              class="w-full text-xs rounded-[4px] h-8 px-2.5"
            />
            <InputText
              v-model="newContact.email"
              placeholder="Email công việc..."
              class="w-full text-xs rounded-[4px] h-8 px-2.5"
            />
          </div>

          <div class="flex items-center justify-between pt-1">
            <label class="flex items-center gap-2 cursor-pointer text-slate-600 select-none">
              <input type="checkbox" v-model="newContact.is_primary" class="rounded text-sky-600" />
              <span>Đặt làm người liên hệ chính</span>
            </label>

            <Button
              label="Thêm Người Liên Hệ"
              icon="pi pi-plus"
              size="small"
              class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 !text-xs !py-1 !px-3 rounded-[4px]"
              @click="handleAddContactToCustomer"
            />
          </div>
        </div>

        <!-- Bảng danh sách liên hệ -->
        <DataTable
          :value="selectedCustomer?.contacts || []"
          responsiveLayout="scroll"
          class="clean-datatable text-xs"
          showGridlines
        >
          <Column field="name" header="Họ và Tên" style="min-width: 160px">
            <template #body="{ data }">
              <div class="flex items-center gap-1.5">
                <span class="font-bold text-slate-800">{{ data.name }}</span>
                <Tag v-if="data.is_primary" value="Chính" severity="info" class="text-[10px] px-1.5 py-0 rounded" />
              </div>
            </template>
          </Column>

          <Column field="position" header="Chức Vụ / Phòng Ban" style="width: 170px">
            <template #body="{ data }">
              <span class="text-slate-600">{{ data.position || '-' }}</span>
            </template>
          </Column>

          <Column field="phone" header="Số Điện Thoại" style="width: 130px font-mono">
            <template #body="{ data }">
              <span class="font-mono text-slate-700">{{ data.phone || '-' }}</span>
            </template>
          </Column>

          <Column field="email" header="Email" style="width: 170px">
            <template #body="{ data }">
              <span class="text-slate-600 truncate block">{{ data.email || '-' }}</span>
            </template>
          </Column>

          <Column header="Xóa" style="width: 60px" align="center">
            <template #body="{ data }">
              <Button
                icon="pi pi-trash"
                size="small"
                text
                class="!text-xs !p-1 text-red-500 hover:!bg-red-50 rounded"
                v-tooltip.bottom="'Xóa người liên hệ'"
                @click="handleDeleteContact(data)"
              />
            </template>
          </Column>

          <template #empty>
            <div class="py-4 text-center text-slate-400 text-xs italic">
              Chưa có thông tin người liên hệ nào cho khách hàng này.
            </div>
          </template>
        </DataTable>
      </div>

      <template #footer>
        <div class="flex justify-end pt-2">
          <Button
            label="Đóng"
            size="small"
            severity="secondary"
            class="rounded-[6px]"
            @click="showContactsDialog = false"
          />
        </div>
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useToast } from 'primevue/usetoast';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Toolbar from 'primevue/toolbar';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import Dialog from 'primevue/dialog';

import PageHeader from '../../components/common/PageHeader.vue';
import { catalogApi } from '../../api/catalog.api';
import { formatCurrency } from '../../utils/format';

const toast = useToast();

const customers = ref([]);
const loading = ref(false);
const searchQuery = ref('');
const statusFilter = ref(null);

const showCustomerDialog = ref(false);
const showContactsDialog = ref(false);
const isEditMode = ref(false);
const selectedCustomer = ref(null);

const statusOptions = [
  { label: 'Tất cả trạng thái', value: null },
  { label: 'Đang hoạt động', value: 'ACTIVE' },
  { label: 'Tạm khóa', value: 'INACTIVE' }
];

const customerForm = ref({
  id: null,
  code: '',
  name: '',
  tax_code: '',
  phone: '',
  email: '',
  address: '',
  credit_limit: 0,
  status: 'ACTIVE'
});

const newContact = ref({
  name: '',
  position: '',
  phone: '',
  email: '',
  is_primary: false
});

const loadCustomers = async () => {
  loading.value = true;
  try {
    const res = await catalogApi.getCustomers();
    customers.value = res.data || [];
  } catch (err) {
    console.error('Lỗi tải danh sách khách hàng:', err);
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  loadCustomers();
});

const filteredCustomers = computed(() => {
  return customers.value.filter((c) => {
    const q = searchQuery.value.toLowerCase().trim();
    const matchSearch =
      !q ||
      c.code.toLowerCase().includes(q) ||
      c.name.toLowerCase().includes(q) ||
      (c.tax_code && c.tax_code.toLowerCase().includes(q)) ||
      (c.phone && c.phone.toLowerCase().includes(q));

    const matchStatus = !statusFilter.value || c.status === statusFilter.value;
    return matchSearch && matchStatus;
  });
});

const getPrimaryContact = (cust) => {
  if (!cust.contacts || cust.contacts.length === 0) return null;
  return cust.contacts.find((c) => c.is_primary) || cust.contacts[0];
};

const openCreateCustomerDialog = () => {
  isEditMode.value = false;
  customerForm.value = {
    id: null,
    code: '',
    name: '',
    tax_code: '',
    phone: '',
    email: '',
    address: '',
    credit_limit: 0,
    status: 'ACTIVE'
  };
  showCustomerDialog.value = true;
};

const openEditCustomerDialog = (cust) => {
  isEditMode.value = true;
  customerForm.value = { ...cust };
  showCustomerDialog.value = true;
};

const handleSaveCustomer = async () => {
  if (!customerForm.value.code || !customerForm.value.name) {
    alert('Vui lòng nhập đầy đủ Mã và Tên khách hàng!');
    return;
  }

  try {
    if (isEditMode.value) {
      await catalogApi.updateCustomer(customerForm.value.id, customerForm.value);
      toast.add({
        severity: 'success',
        summary: 'Cập nhật thành công',
        detail: `Hồ sơ khách hàng ${customerForm.value.code} đã được cập nhật.`,
        life: 3000
      });
    } else {
      await catalogApi.createCustomer(customerForm.value);
      toast.add({
        severity: 'success',
        summary: 'Thêm mới thành công',
        detail: `Khách hàng ${customerForm.value.code} đã được tạo thành công.`,
        life: 3000
      });
    }

    showCustomerDialog.value = false;
    loadCustomers();
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi lưu khách hàng',
      detail: err.message || 'Không thể lưu hồ sơ khách hàng.',
      life: 4000
    });
  }
};

const confirmDeleteCustomer = async (cust) => {
  if (!confirm(`Bạn có chắc chắn muốn xóa khách hàng ${cust.code} - ${cust.name}?`)) {
    return;
  }

  try {
    await catalogApi.deleteCustomer(cust.id);
    toast.add({
      severity: 'success',
      summary: 'Đã xóa khách hàng',
      detail: `Đã xóa thành công ${cust.code}.`,
      life: 3000
    });
    loadCustomers();
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi xóa khách hàng',
      detail: err.message || 'Không thể xóa khách hàng này.',
      life: 4000
    });
  }
};

// Quản lý người liên hệ
const openContactsDialog = (cust) => {
  selectedCustomer.value = cust;
  newContact.value = {
    name: '',
    position: '',
    phone: '',
    email: '',
    is_primary: false
  };
  showContactsDialog.value = true;
};

const handleAddContactToCustomer = async () => {
  if (!newContact.value.name) {
    alert('Vui lòng nhập họ tên người liên hệ!');
    return;
  }

  try {
    const res = await catalogApi.addCustomerContact(selectedCustomer.value.id, newContact.value);
    const added = res.data;

    if (!selectedCustomer.value.contacts) selectedCustomer.value.contacts = [];
    selectedCustomer.value.contacts.push(added);

    // Cập nhật vào mảng chính
    const found = customers.value.find((c) => c.id === selectedCustomer.value.id);
    if (found) {
      found.contacts = [...selectedCustomer.value.contacts];
    }

    toast.add({
      severity: 'success',
      summary: 'Đã thêm người liên hệ',
      detail: `Đã thêm ${added.name} vào danh sách liên hệ.`,
      life: 3000
    });

    newContact.value = {
      name: '',
      position: '',
      phone: '',
      email: '',
      is_primary: false
    };
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi thêm người liên hệ',
      detail: err.message || 'Không thể thêm người liên hệ.',
      life: 3000
    });
  }
};

const handleDeleteContact = async (contact) => {
  if (!confirm(`Bạn có chắc muốn xóa người liên hệ ${contact.name}?`)) {
    return;
  }

  try {
    await catalogApi.deleteCustomerContact(selectedCustomer.value.id, contact.id);

    selectedCustomer.value.contacts = selectedCustomer.value.contacts.filter((c) => c.id !== contact.id);

    const found = customers.value.find((c) => c.id === selectedCustomer.value.id);
    if (found) {
      found.contacts = [...selectedCustomer.value.contacts];
    }

    toast.add({
      severity: 'success',
      summary: 'Đã xóa người liên hệ',
      detail: `Đã xóa ${contact.name}.`,
      life: 3000
    });
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi xóa người liên hệ',
      detail: err.message || 'Không thể xóa người liên hệ.',
      life: 3000
    });
  }
};
</script>
