<template>
  <div class="space-y-3 pb-10">
    <!-- 1. Header Toolbar -->
    <div class="flex items-center justify-between bg-white border border-slate-200 rounded-[6px] px-4 py-2.5">
      <div class="flex items-center gap-3">
        <Button label="Quay lại" icon="pi pi-arrow-left" size="small" severity="secondary" outlined
          class="!text-xs !py-1 !px-2.5 rounded-[6px] !border-slate-300 !text-slate-700 hover:!bg-slate-100"
          @click="$router.push('/quotation')" />

        <div class="flex flex-col">
          <h1 class="text-base font-bold text-slate-900 m-0 tracking-tight flex items-center gap-2">
            <span>{{ isEditMode ? `Edit Quotation - ${form.quotation_number}` : `Tạo Báo Giá Mới -
              ${form.quotation_number}` }}</span>
            <Tag :severity="getStatusMeta(form.status).severity" :value="getStatusMeta(form.status).label"
              class="text-[11px] px-2 py-0.5 rounded-[4px]" />
          </h1>
          <span class="text-[11px] text-slate-400">Modify quotation details and items</span>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="flex items-center gap-2">
        <!-- Nút Gửi xét duyệt -->
        <Button v-if="form.status === 'DRAFT'" label="Gửi xét duyệt" icon="pi pi-send" size="small"
          class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 !text-xs !py-1 !px-3 rounded-[6px] !font-medium"
          @click="handleSubmitForApproval" />

        <!-- Nút Phê duyệt (Chỉ hiển thị khi có quyền quotation.approve hoặc Super Admin) -->
        <Button v-if="canApprove && form.status === 'PENDING_APPROVAL'" label="Duyệt báo giá" icon="pi pi-check-circle"
          size="small"
          class="!bg-emerald-600 hover:!bg-emerald-700 !text-white !border-emerald-600 !text-xs !py-1 !px-3 rounded-[6px] !font-semibold"
          @click="handleApproveQuotation" />

        <!-- Nút Lưu thay đổi -->
        <Button label="Lưu thay đổi" icon="pi pi-save" size="small"
          class="!bg-emerald-700 hover:!bg-emerald-800 !text-white !border-emerald-700 !text-xs !py-1 !px-3.5 rounded-[6px] !font-semibold"
          @click="handleSaveQuotation" />
      </div>
    </div>

    <!-- 2. Form Thông Tin Báo Giá (Bố cục 2 hàng chuẩn ảnh ERP) -->
    <div class="bg-white border border-slate-200 rounded-[6px] p-4 flex flex-col gap-3.5">
      <!-- Hàng 1 -->
      <div class="grid grid-cols-1 md:grid-cols-5 gap-3.5">
        <!-- Khách hàng * -->
        <div class="md:col-span-2 flex flex-col gap-1">
          <label class="text-xs font-semibold text-slate-700">Khách hàng <span class="text-red-500">*</span></label>
          <Select v-model="form.customer_id" :options="customerOptions" optionLabel="label" optionValue="id"
            placeholder="Chọn khách hàng doanh nghiệp..." class="w-full text-xs rounded-[6px]" size="small"
            @change="onCustomerChange" />
        </div>

        <!-- Liên hệ khách hàng * -->
        <div class="md:col-span-1 flex flex-col gap-1">
          <div class="flex items-center justify-between">
            <label class="text-xs font-semibold text-slate-700">Liên hệ khách hàng <span
                class="text-red-500">*</span></label>
          </div>
          <div class="flex items-center gap-1.5">
            <Select v-model="form.contact_id" :options="contactOptions" optionLabel="name" optionValue="id"
              placeholder="Chọn người liên hệ..." class="w-full text-xs rounded-[6px]" size="small"
              :disabled="!form.customer_id" @change="onContactChange" />
            <Button icon="pi pi-plus" size="small" text
              class="!w-8 !h-8 !p-0 rounded-[6px] shrink-0 border border-slate-300 text-sky-600 hover:!bg-sky-50"
              title="Thêm người liên hệ mới cho khách hàng này" @click="showAddContactDialog = true" />
          </div>
        </div>

        <!-- Số báo giá (Tự động sinh ra dạng QT-mãkháchhàng-ngày ddmmyy) -->
        <div class="flex flex-col gap-1">
          <label class="text-xs font-semibold text-slate-700">Số báo giá</label>
          <InputText v-model="form.quotation_number"
            class="w-full text-xs font-mono font-bold text-slate-700 bg-slate-50 rounded-[6px]" size="small" readonly />
        </div>

        <!-- Tiền tệ & Tỷ giá -->
        <div class="grid grid-cols-2 gap-2">
          <div class="flex flex-col gap-1">
            <label class="text-xs font-semibold text-slate-700">Tiền tệ</label>
            <Select v-model="form.currency" :options="['VND', 'USD', 'EUR']" class="w-full text-xs rounded-[6px]"
              size="small" />
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-xs font-semibold text-slate-700">Tỷ giá</label>
            <InputNumber v-model="form.exchange_rate" class="w-full text-xs font-mono"
              inputClass="text-xs font-mono py-1 rounded-[6px]" size="small" :disabled="form.currency === 'VND'" />
          </div>
        </div>
      </div>

      <!-- Hàng 2 -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-3.5 pt-2 border-t border-slate-100 items-center">
        <!-- Nhân viên phụ trách (người tạo) -->
        <div class="flex flex-col gap-1">
          <span class="text-xs font-semibold text-slate-500">Nhân viên phụ trách:</span>
          <div class="flex items-center gap-2 mt-0.5">
            <div
              class="w-6 h-6 rounded-full bg-slate-200 text-slate-700 flex items-center justify-center text-xs font-bold font-mono">
              {{ (form.created_by || 'a').charAt(0).toLowerCase() }}
            </div>
            <span class="text-xs font-semibold text-slate-800">{{ form.created_by }}</span>
          </div>
        </div>

        <!-- Ngày tạo báo giá -->
        <div class="flex flex-col gap-1">
          <span class="text-xs font-semibold text-slate-500">Ngày tạo báo giá:</span>
          <span class="text-xs font-mono font-medium text-slate-800 mt-0.5">{{ form.created_at }}</span>
        </div>

        <!-- Nhân viên theo dõi * -->
        <div class="flex flex-col gap-1">
          <label class="text-xs font-semibold text-slate-700">Nhân viên theo dõi <span
              class="text-red-500">*</span></label>
          <Select v-model="form.assigned_to" :options="['admin', 'sale_lead', 'nv_kinhdoanh_01']"
            placeholder="Chọn nhân viên..." class="w-full text-xs rounded-[6px]" size="small" />
        </div>

        <!-- Trạng thái * (Không cho sửa trực tiếp trong form) -->
        <div class="flex flex-col gap-1">
          <label class="text-xs font-semibold text-slate-700">Trạng thái <span class="text-red-500">*</span></label>
          <div class="h-8 flex items-center px-3 bg-slate-50 border border-slate-200 rounded-[6px]">
            <Tag :severity="getStatusMeta(form.status).severity" :value="getStatusMeta(form.status).label"
              class="text-[11px] px-2 py-0.5 rounded-[4px]" />
            <span class="text-[10px] text-slate-400 ml-2 italic">(Cập nhật qua các nút quy trình)</span>
          </div>
        </div>
      </div>
    </div>

    <!-- 3. Bảng Mặt Hàng Báo Giá (Sử dụng component ItemDataTable.vue) -->
    <ItemDataTable v-model="form.items" @summary-change="onItemsSummaryChange" />

    <!-- 4. Modal Thêm Nhanh Người Liên Hệ Mới -->
    <Dialog v-model:visible="showAddContactDialog" modal header="Thêm Người Liên Hệ Mới" :style="{ width: '420px' }"
      class="rounded-[6px]">
      <div class="flex flex-col gap-3 pt-1 text-xs">
        <div class="flex flex-col gap-1">
          <label class="font-semibold text-slate-700">Họ và tên người liên hệ <span
              class="text-red-500">*</span></label>
          <InputText v-model="newContact.name" placeholder="Ví dụ: Mr. Hùng (Phó phòng kỹ thuật)..."
            class="w-full text-xs rounded-[6px]" />
        </div>
        <div class="flex flex-col gap-1">
          <label class="font-semibold text-slate-700">Số điện thoại di động</label>
          <InputText v-model="newContact.phone" placeholder="09xx xxx xxx..." class="w-full text-xs rounded-[6px]" />
        </div>
        <div class="flex flex-col gap-1">
          <label class="font-semibold text-slate-700">Email công việc</label>
          <InputText v-model="newContact.email" placeholder="email@doanhnghiep.com..."
            class="w-full text-xs rounded-[6px]" />
        </div>
      </div>

      <template #footer>
        <div class="flex justify-end gap-2">
          <Button label="Hủy" text size="small" severity="secondary" class="rounded-[6px]"
            @click="showAddContactDialog = false" />
          <Button label="Lưu người liên hệ" icon="pi pi-check" size="small"
            class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 rounded-[6px]" @click="saveNewContact" />
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

// Dropdown khách hàng
const customerOptions = computed(() => {
  return customers.value.map((c) => ({
    id: c.id,
    code: c.code,
    name: c.name,
    label: `${c.code} - ${c.name}`
  }));
});

// Dropdown người liên hệ theo khách hàng đã chọn
const contactOptions = computed(() => {
  const currentCust = customers.value.find((c) => c.id === form.value.customer_id);
  return currentCust ? currentCust.contacts || [] : [];
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
  phone: '',
  email: ''
});

// Sinh số báo giá theo quy ước QT-mãkháchhàng-ngày ddmmyy
const generateQuotationNumber = (customerCode = 'KH01', date = new Date()) => {
  const d = String(date.getDate()).padStart(2, '0');
  const m = String(date.getMonth() + 1).padStart(2, '0');
  const y = String(date.getFullYear()).slice(-2);
  const dateStr = `${d}${m}${y}`;
  return `QT-${customerCode}-${dateStr}`;
};

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

  // Load danh sách khách hàng từ catalog-service
  try {
    const resPartners = await catalogApi.getPartners();
    if (resPartners && resPartners.data) {
      customers.value = resPartners.data
        .filter((p) => !p.type || p.type === 'CUSTOMER' || p.type === 'BOTH')
        .map((p) => ({
          ...p,
          contacts: [
            {
              id: 1,
              name: p.phone ? `Người đại diện (${p.phone})` : 'Phòng Vật tư / Mua hàng',
              phone: p.phone || '',
              email: p.email || ''
            }
          ]
        }));
    }
  } catch (err) {
    console.warn('Lỗi tải khách hàng từ Catalog Service:', err);
  }

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

  // Khởi tạo khi Tạo Mới
  const now = new Date();
  const d = String(now.getDate()).padStart(2, '0');
  const m = String(now.getMonth() + 1).padStart(2, '0');
  const y = now.getFullYear();
  const hh = String(now.getHours()).padStart(2, '0');
  const mm = String(now.getMinutes()).padStart(2, '0');
  const formattedDate = `${d}/${m}/${y} ${hh}:${mm}`;

  const firstCust = customers.value[0];
  const autoNumber = generateQuotationNumber(firstCust?.code || 'KH01', now);

  form.value = {
    id: autoNumber,
    quotation_number: autoNumber,
    customer_id: firstCust?.id || null,
    customer_code: firstCust?.code || '',
    customer_name: firstCust?.name || '',
    contact_id: firstCust?.contacts?.[0]?.id || null,
    contact_name: firstCust?.contacts?.[0]?.name || '',
    currency: 'VND',
    exchange_rate: 1,
    created_by: props.currentUser?.name || 'admin',
    created_at: formattedDate,
    assigned_to: 'admin',
    status: 'DRAFT',
    notes: 'Báo giá thiết bị công nghiệp chính hãng.',
    total_amount: 0,
    subtotal: 0,
    vat_total: 0,
    items: []
  };
};

// Đổi khách hàng -> Tự động sinh lại số báo giá theo quy ước QT-mãkháchhàng-ngày ddmmyy
const onCustomerChange = () => {
  const cust = customers.value.find((c) => c.id === form.value.customer_id);
  if (cust) {
    form.value.customer_code = cust.code;
    form.value.customer_name = cust.name;

    // Sinh số báo giá theo mã khách hàng
    if (!isEditMode.value) {
      form.value.quotation_number = generateQuotationNumber(cust.code);
      form.value.id = form.value.quotation_number;
    }

    // Gán người liên hệ đầu tiên của khách đó
    if (cust.contacts && cust.contacts.length > 0) {
      form.value.contact_id = cust.contacts[0].id;
      form.value.contact_name = cust.contacts[0].name;
    } else {
      form.value.contact_id = null;
      form.value.contact_name = '';
    }
  }
};

const onContactChange = () => {
  const c = contactOptions.value.find((item) => item.id === form.value.contact_id);
  if (c) {
    form.value.contact_name = c.name;
  }
};

const saveNewContact = () => {
  if (!newContact.value.name) {
    alert('Vui lòng nhập tên người liên hệ!');
    return;
  }
  const currentCust = customers.value.find((c) => c.id === form.value.customer_id);
  if (currentCust) {
    if (!currentCust.contacts) currentCust.contacts = [];
    const contactObj = {
      id: Date.now(),
      name: newContact.value.name,
      phone: newContact.value.phone,
      email: newContact.value.email
    };
    currentCust.contacts.push(contactObj);
    form.value.contact_id = contactObj.id;
    form.value.contact_name = contactObj.name;

    toast.add({
      severity: 'success',
      summary: 'Thêm người liên hệ',
      detail: `Đã thêm ${contactObj.name} vào danh sách liên hệ của ${currentCust.name}.`,
      life: 3000
    });
    newContact.value = { name: '', phone: '', email: '' };
    showAddContactDialog.value = false;
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
    if (isEditMode.value) {
      await salesApi.updateQuotation(form.value.id, form.value);
    } else {
      await salesApi.createQuotation(form.value);
    }
    toast.add({
      severity: 'success',
      summary: 'Lưu báo giá thành công',
      detail: `Báo giá ${form.value.quotation_number} đã được lưu thành công vào CSDL.`,
      life: 3000
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
  try {
    if (isEditMode.value) {
      await salesApi.submitApproval(form.value.id);
    } else {
      form.value.status = 'PENDING_APPROVAL';
      await salesApi.createQuotation(form.value);
    }
    form.value.status = 'PENDING_APPROVAL';
    toast.add({
      severity: 'info',
      summary: 'Đã gửi xét duyệt',
      detail: `Báo giá ${form.value.quotation_number} đã được chuyển sang trạng thái Chờ duyệt.`,
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
