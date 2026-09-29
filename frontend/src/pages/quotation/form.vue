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
          @click="$router.push('/quotation')"
        />

        <div class="flex flex-col">
          <h1 class="text-base font-bold text-slate-900 m-0 tracking-tight flex items-center gap-2">
            <span>{{ isEditMode ? `Báo Giá: ${form.quotation_number}` : 'Lập Báo Giá Mới' }}</span>
            <Tag
              :severity="getStatusMeta(form.status).severity"
              :value="getStatusMeta(form.status).label"
              class="text-[11px] px-2 py-0.5 rounded-[4px]"
            />
          </h1>
          <span class="text-xs text-slate-400">
            {{ isEditMode ? 'Xem và chỉnh sửa chi tiết báo giá cùng các mặt hàng' : 'Nhập thông tin khách hàng và lựa chọn các mặt hàng cần báo giá' }}
          </span>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="flex items-center gap-2.5">
        <!-- Nút Gửi xét duyệt -->
        <Button
          v-if="form.status === 'DRAFT'"
          label="Gửi xét duyệt"
          icon="pi pi-send"
          size="small"
          class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 !text-xs !py-2 !px-3.5 rounded-[6px] !font-medium"
          @click="handleSubmitForApproval"
        />

        <!-- Nút Phê duyệt (Chỉ hiển thị khi có quyền quotation.approve hoặc Super Admin) -->
        <Button
          v-if="canApprove && form.status === 'PENDING_APPROVAL'"
          label="Duyệt báo giá"
          icon="pi pi-check-circle"
          size="small"
          class="!bg-emerald-600 hover:!bg-emerald-700 !text-white !border-emerald-600 !text-xs !py-2 !px-3.5 rounded-[6px] !font-semibold"
          @click="handleApproveQuotation"
        />

        <!-- Nút Lưu thay đổi -->
        <Button
          label="Lưu thay đổi"
          icon="pi pi-save"
          size="small"
          class="!bg-emerald-700 hover:!bg-emerald-800 !text-white !border-emerald-700 !text-xs !py-2 !px-4 rounded-[6px] !font-semibold shadow-sm"
          @click="handleSaveQuotation"
        />
      </div>
    </div>

    <!-- 2. Form Thông Tin Báo Giá (Bố cục 2 cột: Trái 65% - Phải 35%) -->
    <div class="flex flex-col lg:flex-row gap-4 items-stretch">
      <!-- Cột Trái: 65% (Khách hàng, Liên hệ, Số báo giá, Tiền tệ, Tỷ giá) -->
      <div class="w-full lg:w-[65%] bg-white border border-slate-200 rounded-[8px] p-5 shadow-sm flex flex-col justify-between gap-4">
        <!-- Hàng 1 của cột trái: Khách hàng (6 cols) + Liên hệ (6 cols) -->
        <div class="grid grid-cols-1 md:grid-cols-12 gap-3.5 items-start">
          <!-- Chọn khách hàng -->
          <div class="md:col-span-6 flex flex-col gap-1.5 min-w-0">
            <label class="text-xs font-bold text-slate-700 uppercase tracking-wide truncate">
              Khách hàng <span class="text-red-500">*</span>
            </label>
            <Select
              v-model="form.customer_id"
              :options="customerOptions"
              optionLabel="label"
              optionValue="id"
              filter
              placeholder="-- Chọn khách hàng doanh nghiệp --"
              class="w-full min-w-0 text-xs rounded-[6px] h-10 flex items-center"
              :pt="{ label: { class: '!truncate' }, root: { class: '!min-w-0' } }"
              @change="onCustomerChange"
            >
              <template #value="slotProps">
                <div v-if="slotProps.value" class="truncate text-xs font-medium text-slate-800">
                  {{ customerOptions.find(c => c.id === slotProps.value)?.label || form.customer_name }}
                </div>
                <span v-else class="text-slate-400 text-xs truncate">
                  {{ slotProps.placeholder }}
                </span>
              </template>
            </Select>
          </div>

          <!-- Liên hệ khách -->
          <div class="md:col-span-6 flex flex-col gap-1.5 min-w-0">
            <label class="text-xs font-bold text-slate-700 uppercase tracking-wide truncate">
              Liên hệ khách hàng <span class="text-red-500">*</span>
            </label>
            <div class="flex items-center gap-1.5 w-full min-w-0">
              <Select
                v-model="form.contact_id"
                :options="contactOptions"
                optionLabel="label"
                optionValue="id"
                :placeholder="form.customer_id ? '-- Chọn người liên hệ --' : '(Cần chọn khách hàng)'"
                class="flex-1 min-w-0 text-xs rounded-[6px] h-10 flex items-center"
                :pt="{ label: { class: '!truncate' }, root: { class: '!min-w-0' } }"
                :disabled="!form.customer_id"
                @change="onContactChange"
              >
                <template #value="slotProps">
                  <div v-if="slotProps.value" class="truncate text-xs font-medium text-slate-800">
                    {{ contactOptions.find(c => c.id === slotProps.value)?.label || form.contact_name }}
                  </div>
                  <span v-else class="text-slate-400 text-xs truncate">
                    {{ slotProps.placeholder }}
                  </span>
                </template>
              </Select>
              <Button
                icon="pi pi-plus"
                size="small"
                text
                class="!w-10 !h-10 !p-0 rounded-[6px] shrink-0 border border-slate-300 text-sky-600 hover:!bg-sky-50"
                :disabled="!form.customer_id"
                title="Thêm người liên hệ mới cho khách hàng này"
                @click="showAddContactDialog = true"
              />
            </div>
          </div>
        </div>

        <!-- Hàng 2 của cột trái: Số báo giá (6 cols) + Tiền tệ (3 cols) + Tỷ giá (3 cols) -->
        <div class="grid grid-cols-1 md:grid-cols-12 gap-3.5 items-start pt-2 border-t border-slate-100">
          <!-- Số báo giá -->
          <div class="md:col-span-6 flex flex-col gap-1.5">
            <label class="text-xs font-bold text-slate-700 uppercase tracking-wide">
              Số báo giá
            </label>
            <InputText
              :value="form.quotation_number || (form.customer_code ? previewQuotationNumber : '(Tự động sinh khi lưu)')"
              class="w-full text-xs font-mono font-bold text-slate-700 bg-slate-50 rounded-[6px] h-10 px-3 border-slate-200"
              readonly
            />
          </div>

          <!-- Tiền tệ -->
          <div class="md:col-span-3 flex flex-col gap-1.5">
            <label class="text-xs font-bold text-slate-700 uppercase tracking-wide">Tiền tệ</label>
            <Select
              v-model="form.currency"
              :options="['VND', 'USD', 'EUR']"
              class="w-full text-xs rounded-[6px] h-10 flex items-center"
            />
          </div>

          <!-- Tỷ giá -->
          <div class="md:col-span-3 flex flex-col gap-1.5">
            <label class="text-xs font-bold text-slate-700 uppercase tracking-wide">Tỷ giá</label>
            <InputNumber
              v-model="form.exchange_rate"
              class="w-full text-xs font-mono h-10"
              inputClass="text-xs font-mono py-1 rounded-[6px] h-10"
              :disabled="form.currency === 'VND'"
            />
          </div>
        </div>
      </div>

      <!-- Cột Phải: 35% (Nhân viên phụ trách, Ngày tạo báo giá, Nhân viên theo dõi, Trạng thái) -->
      <div class="w-full lg:w-[35%] bg-white border border-slate-200 rounded-[8px] p-5 shadow-sm flex flex-col justify-between gap-4">
        <!-- Hàng 1 của cột phải: Nhân viên phụ trách (1 col) + Ngày tạo báo giá (1 col) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 items-start">
          <!-- Nhân viên phụ trách -->
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-bold text-slate-700 uppercase tracking-wide">
              Nhân viên phụ trách
            </label>
            <div class="h-10 flex items-center gap-2 px-3 bg-slate-50 border border-slate-200 rounded-[6px]">
              <div class="w-6 h-6 rounded-full bg-slate-200 text-slate-700 flex items-center justify-center text-xs font-bold font-mono">
                {{ (form.created_by || 'A').charAt(0).toUpperCase() }}
              </div>
              <span class="text-xs font-semibold text-slate-800 truncate">{{ form.created_by || 'Quản Trị Viên' }}</span>
            </div>
          </div>

          <!-- Ngày tạo báo giá -->
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-bold text-slate-700 uppercase tracking-wide">
              Ngày tạo báo giá
            </label>
            <div class="h-10 flex items-center px-3 bg-slate-50 border border-slate-200 rounded-[6px]">
              <span v-if="form.created_at || form.quotation_date" class="text-xs font-mono font-medium text-slate-800 truncate">
                {{ form.created_at || form.quotation_date }}
              </span>
              <span v-else class="text-[11px] text-slate-400 italic truncate">
                (Tự động ghi khi tạo)
              </span>
            </div>
          </div>
        </div>

        <!-- Hàng 2 của cột phải: Nhân viên theo dõi (1 col) + Trạng thái (1 col) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 items-start pt-2 border-t border-slate-100">
          <!-- Nhân viên theo dõi -->
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-bold text-slate-700 uppercase tracking-wide">
              Nhân viên theo dõi <span class="text-red-500">*</span>
            </label>
            <Select
              v-model="form.assigned_to"
              :options="['admin', 'sale_lead', 'nv_kinhdoanh_01']"
              placeholder="Chọn nhân viên..."
              class="w-full text-xs rounded-[6px] h-10 flex items-center"
            />
          </div>

          <!-- Trạng thái (Đã bỏ dòng cập nhật qua các nút quy trình) -->
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-bold text-slate-700 uppercase tracking-wide">
              Trạng thái <span class="text-red-500">*</span>
            </label>
            <div class="h-10 flex items-center justify-center px-3 bg-slate-50 border border-slate-200 rounded-[6px]">
              <Tag
                :severity="getStatusMeta(form.status).severity"
                :value="getStatusMeta(form.status).label"
                class="text-xs px-3 py-1 rounded-[4px] font-semibold"
              />
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 3. Bảng Mặt Hàng Báo Giá (Sử dụng component ItemDataTable.vue) -->
    <ItemDataTable v-model="form.items" @summary-change="onItemsSummaryChange" />

    <!-- 4. Modal Thêm Nhanh Người Liên Hệ Mới -->
    <Dialog
      v-model:visible="showAddContactDialog"
      modal
      header="Thêm Người Liên Hệ Mới"
      :style="{ width: '440px' }"
      class="rounded-[8px]"
    >
      <div class="flex flex-col gap-3.5 pt-2 text-xs">
        <div class="flex flex-col gap-1.5">
          <label class="font-semibold text-slate-700">Họ và tên người liên hệ <span class="text-red-500">*</span></label>
          <InputText
            v-model="newContact.name"
            placeholder="Ví dụ: Nguyễn Văn Hùng..."
            class="w-full text-xs rounded-[6px] h-9 px-3"
          />
        </div>
        <div class="flex flex-col gap-1.5">
          <label class="font-semibold text-slate-700">Chức vụ / Phòng ban</label>
          <InputText
            v-model="newContact.position"
            placeholder="Ví dụ: Trưởng phòng Mua hàng, Kỹ sư cơ điện..."
            class="w-full text-xs rounded-[6px] h-9 px-3"
          />
        </div>
        <div class="flex flex-col gap-1.5">
          <label class="font-semibold text-slate-700">Số điện thoại di động</label>
          <InputText
            v-model="newContact.phone"
            placeholder="09xx xxx xxx..."
            class="w-full text-xs rounded-[6px] h-9 px-3"
          />
        </div>
        <div class="flex flex-col gap-1.5">
          <label class="font-semibold text-slate-700">Email công việc</label>
          <InputText
            v-model="newContact.email"
            placeholder="email@doanhnghiep.com..."
            class="w-full text-xs rounded-[6px] h-9 px-3"
          />
        </div>
      </div>

      <template #footer>
        <div class="flex justify-end gap-2 pt-2">
          <Button
            label="Hủy"
            text
            size="small"
            severity="secondary"
            class="rounded-[6px]"
            @click="showAddContactDialog = false"
          />
          <Button
            label="Lưu người liên hệ"
            icon="pi pi-check"
            size="small"
            class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 rounded-[6px]"
            @click="saveNewContact"
          />
        </div>
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import Dialog from 'primevue/dialog';

import ItemDataTable from '../../components/common/ItemDataTable.vue';
import { catalogApi } from '../../api/catalog.api';
import { salesApi } from '../../api/sales.api';
import { isSuperAdmin } from '../../utils/rbac';

const route = useRoute();
const router = useRouter();
const toast = useToast();

const props = defineProps({
  currentUser: {
    type: Object,
    default: null
  }
});

const isEditMode = computed(() => !!route.params.id && route.params.id !== 'create');

// Phân quyền duyệt: Super Admin hoặc có quyền quotation.approve
const canApprove = computed(() => {
  if (isSuperAdmin(props.currentUser)) return true;
  if (!props.currentUser) return false;
  return props.currentUser.permissions && props.currentUser.permissions.includes('quotation.approve');
});

const customers = ref([]);

// Dropdown khách hàng (hiển thị Mã - Tên)
const customerOptions = computed(() => {
  return customers.value.map((c) => ({
    id: c.id,
    code: c.code,
    name: c.name,
    label: `${c.code} - ${c.name}`
  }));
});

// Dropdown người liên hệ theo khách hàng đã chọn (Mỗi khách hàng có nhiều người liên hệ)
const contactOptions = computed(() => {
  const currentCust = customers.value.find((c) => c.id === form.value.customer_id);
  if (!currentCust || !currentCust.contacts) return [];
  return currentCust.contacts.map((ct) => {
    let lbl = ct.name;
    if (ct.position && !lbl.toLowerCase().includes(ct.position.toLowerCase())) {
      lbl += ` (${ct.position})`;
    } else if (ct.phone && !lbl.includes(ct.phone)) {
      lbl += ` (${ct.phone})`;
    }
    return {
      id: ct.id,
      name: ct.name,
      label: lbl
    };
  });
});

// Số báo giá dự kiến hiển thị khi đã chọn khách hàng
const previewQuotationNumber = computed(() => {
  if (!form.value.customer_code) return '';
  const now = new Date();
  const d = String(now.getDate()).padStart(2, '0');
  const m = String(now.getMonth() + 1).padStart(2, '0');
  const y = String(now.getFullYear()).slice(-2);
  return `QT-${form.value.customer_code}-${d}${m}${y} (Tự động sinh khi lưu)`;
});

// Trạng thái form
const form = ref({
  id: '',
  quotation_number: '',
  customer_id: null,
  customer_code: '',
  customer_name: '',
  contact_id: null,
  contact_name: '',
  currency: 'VND',
  exchange_rate: 1,
  created_by: 'admin',
  created_at: '',
  assigned_to: 'admin',
  status: 'DRAFT', // DRAFT | PENDING_APPROVAL | APPROVED | CONVERTED
  notes: '',
  total_amount: 0,
  subtotal: 0,
  vat_total: 0,
  items: []
});

// Trạng thái modal
const showNotesDialog = ref(false);
const showAuditDialog = ref(false);
const showAddContactDialog = ref(false);

const newContact = ref({
  name: '',
  position: '',
  phone: '',
  email: ''
});

// Map trạng thái báo giá
const getStatusMeta = (st) => {
  const map = {
    DRAFT: { label: 'Bản nháp', severity: 'secondary' },
    PENDING_APPROVAL: { label: 'Chờ duyệt', severity: 'warn' },
    APPROVED: { label: 'Đã duyệt', severity: 'success' },
    CONVERTED: { label: 'Đã tạo đơn bán', severity: 'info' }
  };
  return map[st] || { label: st, severity: 'secondary' };
};

// Khởi tạo form khi mount
onMounted(() => {
  initFormData();
});

watch(
  () => route.params.id,
  () => {
    initFormData();
  }
);

const initFormData = async () => {
  const qId = route.params.id;

  // Load danh sách khách hàng từ bảng customers của catalog-service
  try {
    const resCust = await catalogApi.getCustomers();
    if (resCust && resCust.data) {
      customers.value = resCust.data;
    }
  } catch (err) {
    console.warn('Lỗi tải khách hàng từ Catalog Service:', err);
  }

  // Nếu đang chỉnh sửa báo giá có sẵn
  if (isEditMode.value) {
    try {
      const res = await salesApi.getQuotationById(qId);
      if (res && res.data) {
        form.value = res.data;
        return;
      }
    } catch (err) {
      console.error('Lỗi tải chi tiết báo giá:', err);
    }
  }

  // Khởi tạo khi Tạo Mới: Không tự động chọn khách hàng, không sinh số trước, không hiển thị ngày tạo trước
  form.value = {
    id: '',
    quotation_number: '',
    customer_id: null,
    customer_code: '',
    customer_name: '',
    contact_id: null,
    contact_name: '',
    currency: 'VND',
    exchange_rate: 1,
    created_by: props.currentUser?.name || 'Quản Trị Viên Tối Cao',
    created_at: '',
    assigned_to: 'admin',
    status: 'DRAFT',
    notes: 'Báo giá thiết bị công nghiệp chính hãng.',
    total_amount: 0,
    subtotal: 0,
    vat_total: 0,
    items: []
  };
};

// Đổi khách hàng -> Cập nhật mã & tên khách hàng, gán người liên hệ đầu tiên
const onCustomerChange = () => {
  const cust = customers.value.find((c) => c.id === form.value.customer_id);
  if (cust) {
    form.value.customer_code = cust.code;
    form.value.customer_name = cust.name;

    // Gán người liên hệ ưu tiên hoặc đầu tiên của khách hàng
    const primaryContact = (cust.contacts || []).find((c) => c.is_primary) || cust.contacts?.[0];
    if (primaryContact) {
      form.value.contact_id = primaryContact.id;
      form.value.contact_name = primaryContact.name;
    } else {
      form.value.contact_id = null;
      form.value.contact_name = '';
    }
  } else {
    form.value.customer_code = '';
    form.value.customer_name = '';
    form.value.contact_id = null;
    form.value.contact_name = '';
  }
};

const onContactChange = () => {
  const c = contactOptions.value.find((item) => item.id === form.value.contact_id);
  if (c) {
    form.value.contact_name = c.name;
  }
};

const saveNewContact = async () => {
  if (!newContact.value.name) {
    alert('Vui lòng nhập tên người liên hệ!');
    return;
  }
  const currentCust = customers.value.find((c) => c.id === form.value.customer_id);
  if (currentCust) {
    try {
      // Lưu vào CSDL bảng customer_contacts của catalog-service
      const res = await catalogApi.addCustomerContact(currentCust.id, newContact.value);
      const savedContact = res.data;

      if (!currentCust.contacts) currentCust.contacts = [];
      currentCust.contacts.push(savedContact);

      form.value.contact_id = savedContact.id;
      form.value.contact_name = savedContact.name;

      toast.add({
        severity: 'success',
        summary: 'Thêm người liên hệ',
        detail: `Đã thêm ${savedContact.name} vào danh sách liên hệ của ${currentCust.name}.`,
        life: 3000
      });
      newContact.value = { name: '', position: '', phone: '', email: '' };
      showAddContactDialog.value = false;
    } catch (err) {
      toast.add({
        severity: 'error',
        summary: 'Lỗi thêm người liên hệ',
        detail: err.message || 'Không thể lưu người liên hệ vào máy chủ.',
        life: 3000
      });
    }
  }
};

const onItemsSummaryChange = (summary) => {
  form.value.subtotal = summary.subtotal;
  form.value.vat_total = summary.vatTotal;
  form.value.total_amount = summary.grandTotal;
};

// ----------------------------------------------------
// THAO TÁC LƯU / GỬI DUYỆT / DUYỆT
// ----------------------------------------------------
const handleSaveQuotation = async () => {
  if (!form.value.customer_id) {
    alert('Vui lòng chọn khách hàng!');
    return;
  }
  if (!form.value.items || form.value.items.length === 0) {
    alert('Vui lòng thêm ít nhất 1 dòng mặt hàng vào báo giá!');
    return;
  }

  try {
    let savedQ;
    if (isEditMode.value) {
      const res = await salesApi.updateQuotation(form.value.id, form.value);
      savedQ = res.data;
    } else {
      const res = await salesApi.createQuotation(form.value);
      savedQ = res.data;
    }

    toast.add({
      severity: 'success',
      summary: 'Lưu báo giá thành công',
      detail: `Báo giá ${savedQ?.quotation_number || form.value.quotation_number} đã được lưu thành công vào CSDL.`,
      life: 4000
    });
    router.push('/quotation');
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi lưu báo giá',
      detail: err.message || 'Không thể lưu báo giá lên máy chủ.',
      life: 4000
    });
  }
};

const handleSubmitForApproval = async () => {
  if (!form.value.customer_id) {
    alert('Vui lòng chọn khách hàng trước khi gửi duyệt!');
    return;
  }

  try {
    let savedQ;
    if (isEditMode.value) {
      await salesApi.submitApproval(form.value.id);
      form.value.status = 'PENDING_APPROVAL';
    } else {
      form.value.status = 'PENDING_APPROVAL';
      const res = await salesApi.createQuotation(form.value);
      savedQ = res.data;
    }
    toast.add({
      severity: 'info',
      summary: 'Đã gửi xét duyệt',
      detail: `Báo giá ${savedQ?.quotation_number || form.value.quotation_number} đã được chuyển sang trạng thái Chờ duyệt.`,
      life: 3000
    });
    router.push('/quotation');
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi gửi duyệt',
      detail: err.message || 'Thao tác không thành công.',
      life: 3000
    });
  }
};

const handleApproveQuotation = async () => {
  try {
    await salesApi.approveQuotation(form.value.id, props.currentUser?.name || 'admin');
    form.value.status = 'APPROVED';
    toast.add({
      severity: 'success',
      summary: 'Đã duyệt báo giá',
      detail: `Báo giá ${form.value.quotation_number} đã được phê duyệt thành công!`,
      life: 3000
    });
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi phê duyệt',
      detail: err.message || 'Không thể duyệt báo giá.',
      life: 3000
    });
  }
};
</script>
