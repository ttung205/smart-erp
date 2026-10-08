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
        <!-- Nút Gửi xét duyệt (khi trạng thái DRAFT) -->
        <Button
          v-if="form.status === 'DRAFT'"
          label="Gửi xét duyệt"
          icon="pi pi-send"
          size="small"
          class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 !text-xs !py-2 !px-3.5 rounded-[6px] !font-medium"
          @click="confirmSubmitApproval"
        />

        <!-- Nút Từ chối (khi PENDING_APPROVAL và canApprove) -->
        <Button
          v-if="canApprove && form.status === 'PENDING_APPROVAL'"
          label="Từ chối"
          icon="pi pi-times-circle"
          size="small"
          severity="danger"
          outlined
          class="!text-xs !py-2 !px-3.5 rounded-[6px] !font-semibold !border-red-300 hover:!bg-red-50 !text-red-600"
          @click="confirmRejectQuotation"
        />

        <!-- Nút Phê duyệt (khi PENDING_APPROVAL và canApprove) -->
        <Button
          v-if="canApprove && form.status === 'PENDING_APPROVAL'"
          label="Duyệt báo giá"
          icon="pi pi-check-circle"
          size="small"
          class="!bg-emerald-600 hover:!bg-emerald-700 !text-white !border-emerald-600 !text-xs !py-2 !px-3.5 rounded-[6px] !font-semibold"
          @click="confirmApproveQuotation"
        />

        <!-- Nút Tạo đơn bán (SO) - Chỉ hiển thị khi báo giá Đã duyệt (APPROVED) -->
        <Button
          v-if="form.status === 'APPROVED'"
          label="Tạo đơn bán (SO)"
          icon="pi pi-shopping-cart"
          size="small"
          class="!bg-indigo-600 hover:!bg-indigo-700 !text-white !border-indigo-600 !text-xs !py-2 !px-3.5 rounded-[6px] !font-semibold shadow-sm"
          @click="handleTopCreateOrder"
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

          <!-- Tỷ giá (Nếu VND là 1 không cho sửa) -->
          <div class="md:col-span-3 flex flex-col gap-1.5">
            <label class="text-xs font-bold text-slate-700 uppercase tracking-wide">Tỷ giá</label>
            <InputNumber
              v-model="form.exchange_rate"
              class="w-full text-xs font-mono h-10"
              inputClass="text-xs font-mono py-1 rounded-[6px] h-10"
              :class="{ '!bg-slate-50': form.currency === 'VND' }"
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
              :options="userOptions"
              optionLabel="label"
              optionValue="id"
              placeholder="Chọn nhân viên sale..."
              class="w-full text-xs rounded-[6px] h-10 flex items-center"
            >
              <template #value="slotProps">
                <div v-if="slotProps.value" class="truncate text-xs font-medium text-slate-800">
                  {{ userOptions.find(u => u.id === slotProps.value || u.name === slotProps.value)?.label || slotProps.value }}
                </div>
                <span v-else class="text-slate-400 text-xs truncate">
                  {{ slotProps.placeholder }}
                </span>
              </template>
            </Select>
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

    <!-- 3. Bảng Mặt Hàng Báo Giá (Sử dụng component QuotationItemTable.vue) -->
    <QuotationItemTable
      v-model="form.items"
      :is-approved="form.status === 'APPROVED'"
      @summary-change="onItemsSummaryChange"
      @create-order="onCreateOrderFromItems"
    />

    <!-- 4. Modal Thêm Nhanh Người Liên Hệ Mới -->
    <QuotationAddContactDialog
      v-model:visible="showAddContactDialog"
      @save="saveNewContact"
    />
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import Dialog from 'primevue/dialog';

import QuotationItemTable from '../../components/quotation/QuotationItemTable.vue';
import QuotationAddContactDialog from '../../components/quotation/QuotationAddContactDialog.vue';
import { catalogApi } from '../../api/catalog.api';
import { salesApi } from '../../api/sales.api';
import { identityApi } from '../../api/identity.api';
import { isSuperAdmin, hasPermission } from '../../utils/rbac';

const route = useRoute();
const router = useRouter();
const toast = useToast();
const confirm = useConfirm();

const props = defineProps({
  currentUser: {
    type: Object,
    default: null
  }
});

const isEditMode = computed(() => !!route.params.id && route.params.id !== 'create');

// Phân quyền duyệt: Super Admin hoặc có quyền quotation.approve
const canApprove = computed(() => {
  if (!props.currentUser) return false;
  if (isSuperAdmin(props.currentUser)) return true;
  return hasPermission('quotation.approve', props.currentUser);
});

const customers = ref([]);
const users = ref([]);

const isSaleUser = (u) => {
  if (!u) return false;
  if (Array.isArray(u.roles) && u.roles.length > 0) {
    const hasSaleRole = u.roles.some((r) => {
      const code = String(r.code || '').toUpperCase();
      const name = String(r.name || '').toLowerCase();
      return code === 'SALE' || code === 'SALE_MANAGER' || code.includes('SALE') || name.includes('kinh doanh') || name.includes('sale');
    });
    if (hasSaleRole) return true;
  }
  if (u.department_code === 'SALES' || (u.department_name && u.department_name.toLowerCase().includes('kinh doanh'))) {
    return true;
  }
  if (typeof u.role === 'string') {
    const r = u.role.toUpperCase();
    if (r === 'SALE' || r === 'SALE_MANAGER' || r.includes('SALE')) return true;
  }
  return false;
};

// Dropdown nhân viên theo dõi: Chỉ hiển thị các nhân viên có role Sale
const userOptions = computed(() => {
  const saleUsers = users.value.filter(isSaleUser);
  const list = saleUsers.length > 0 ? saleUsers : users.value.filter(u => String(u.name || u.email || '').toLowerCase().includes('sale'));
  
  if (list.length === 0) {
    return [
      { id: 2, name: 'Chuyên Viên Kinh Doanh (Sale)', label: 'Chuyên Viên Kinh Doanh (sale@erp.local)' },
      { id: 3, name: 'Trưởng Phòng Kinh Doanh (Sale Manager)', label: 'Trưởng Phòng Kinh Doanh (sale.manager@erp.local)' }
    ];
  }

  return list.map((u) => ({
    id: u.id,
    name: u.name,
    label: `${u.name} (${u.email || 'Sale'})`
  }));
});

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
    REJECTED: { label: 'Từ chối', severity: 'danger' },
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

watch(
  () => form.value.currency,
  (newCurr) => {
    if (newCurr === 'VND') {
      form.value.exchange_rate = 1.0;
    }
  }
);

const initFormData = async () => {
  const qId = route.params.id;

  // Load danh sách khách hàng và nhân viên
  try {
    const [resCust, resUsers] = await Promise.all([
      catalogApi.getCustomers().catch(() => ({ data: [] })),
      identityApi.getUsers().catch(() => ({ data: [] }))
    ]);
    if (resCust && resCust.data) {
      customers.value = resCust.data;
    }
    if (resUsers && resUsers.data) {
      users.value = resUsers.data;
    }
  } catch (err) {
    console.warn('Lỗi tải dữ liệu cơ sở:', err);
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
  const defaultSaleUser = userOptions.value[0]?.id || 2;
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
    assigned_to: defaultSaleUser,
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

const saveNewContact = async (contactData) => {
  const data = contactData || newContact.value;
  if (!data || !data.name) {
    alert('Vui lòng nhập tên người liên hệ!');
    return;
  }
  const currentCust = customers.value.find((c) => c.id === form.value.customer_id);
  if (currentCust) {
    try {
      // Lưu vào CSDL bảng customer_contacts của catalog-service
      const res = await catalogApi.addCustomerContact(currentCust.id, data);
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

const hasWinItems = computed(() => {
  return (form.value.items || []).some((it) => it.status === 'WIN' || it.won || it.is_won);
});

// ----------------------------------------------------
// TẠO ĐƠN BÁN HÀNG TỪ CÁC ITEM WIN
// ----------------------------------------------------
const onCreateOrderFromItems = (winItems) => {
  if (!winItems || winItems.length === 0) {
    alert('Vui lòng chọn ít nhất 1 mặt hàng có trạng thái WIN để tạo đơn bán hàng!');
    return;
  }

  const currentCust = customers.value.find((c) => c.id === form.value.customer_id);
  const currentContact = (currentCust?.contacts || []).find((ct) => ct.id === form.value.contact_id);

  // Chuẩn bị payload đồng bộ sang đơn bán hàng
  const payload = {
    quotation_id: form.value.id || null,
    quotation_number: form.value.quotation_number || previewQuotationNumber.value,
    customer_id: form.value.customer_id,
    customer_code: form.value.customer_code || currentCust?.code || '',
    customer_name: form.value.customer_name || currentCust?.name || '',
    contact_id: form.value.contact_id || null,
    contact_name: form.value.contact_name || currentContact?.name || '',
    shipping_address: currentCust?.address || '',
    currency: form.value.currency || 'VND',
    exchange_rate: Number(form.value.exchange_rate) || 1,
    assigned_to: form.value.assigned_to || 'admin',
    items: winItems.map((it) => ({
      ...it,
      status: 'WIN',
      won: true,
      _selected: false
    }))
  };

  sessionStorage.setItem('pending_so_creation', JSON.stringify(payload));
  router.push('/order/create');
};

const handleTopCreateOrder = () => {
  const winItems = (form.value.items || []).filter((it) => it.status === 'WIN' || it.won || it.is_won);
  if (winItems.length === 0) {
    alert('Chưa có mặt hàng nào có trạng thái WIN (Trúng thầu). Vui lòng tích chọn các dòng và bấm "Đổi trạng thái" sang WIN trước khi tạo đơn bán hàng!');
    return;
  }
  onCreateOrderFromItems(winItems);
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
      if (savedQ && savedQ.id) {
        form.value = { ...form.value, ...savedQ };
        router.replace(`/quotation/${savedQ.id}/edit`);
      }
    }

    toast.add({
      severity: 'success',
      summary: 'Lưu báo giá thành công',
      detail: `Báo giá ${savedQ?.quotation_number || form.value.quotation_number} đã được lưu thành công vào CSDL.`,
      life: 4000
    });
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi lưu báo giá',
      detail: err.message || 'Không thể lưu báo giá lên máy chủ.',
      life: 4000
    });
  }
};

// ----------------------------------------------------
// THAO TÁC XÁC NHẬN QUA CONFIRM DIALOG CỦA PRIMEVUE
// ----------------------------------------------------
const confirmSubmitApproval = () => {
  if (!form.value.customer_id) {
    alert('Vui lòng chọn khách hàng trước khi gửi duyệt!');
    return;
  }
  if (!form.value.items || form.value.items.length === 0) {
    alert('Vui lòng thêm ít nhất 1 dòng mặt hàng vào báo giá trước khi gửi duyệt!');
    return;
  }

  confirm.require({
    header: 'Xác Nhận Gửi Xét Duyệt',
    message: `Bạn có chắc chắn muốn gửi báo giá ${form.value.quotation_number || previewQuotationNumber.value} đi xét duyệt không?`,
    icon: 'pi pi-send',
    rejectLabel: 'Hủy bỏ',
    rejectProps: {
      severity: 'secondary',
      outlined: true
    },
    acceptLabel: 'Xác nhận gửi duyệt',
    acceptProps: {
      severity: 'info'
    },
    accept: async () => {
      await handleSubmitForApproval();
    }
  });
};

const confirmApproveQuotation = () => {
  confirm.require({
    header: 'Xác Nhận Phê Duyệt Báo Giá',
    message: `Bạn có chắc chắn muốn phê duyệt báo giá ${form.value.quotation_number} không? Sau khi duyệt có thể tiến hành tạo Đơn Bán Hàng (SO).`,
    icon: 'pi pi-check-circle',
    rejectLabel: 'Hủy bỏ',
    rejectProps: {
      severity: 'secondary',
      outlined: true
    },
    acceptLabel: 'Đồng ý phê duyệt',
    acceptProps: {
      severity: 'success'
    },
    accept: async () => {
      await handleApproveQuotation();
    }
  });
};

const confirmRejectQuotation = () => {
  confirm.require({
    header: 'Xác Nhận Từ Chối Báo Giá',
    message: `Bạn có chắc chắn muốn từ chối báo giá ${form.value.quotation_number} không? Trạng thái sẽ được chuyển sang 'Từ chối'.`,
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: 'Hủy bỏ',
    rejectProps: {
      severity: 'secondary',
      outlined: true
    },
    acceptLabel: 'Xác nhận từ chối',
    acceptProps: {
      severity: 'danger'
    },
    accept: async () => {
      await handleRejectQuotation();
    }
  });
};

const handleRejectQuotation = async () => {
  try {
    await salesApi.rejectQuotation(form.value.id);
    form.value.status = 'REJECTED';
    toast.add({
      severity: 'warn',
      summary: 'Đã từ chối báo giá',
      detail: `Báo giá ${form.value.quotation_number} đã chuyển sang trạng thái Từ chối.`,
      life: 3000
    });
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi từ chối báo giá',
      detail: err.message || 'Không thể từ chối báo giá.',
      life: 3000
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
      if (savedQ && savedQ.id) {
        form.value = { ...form.value, ...savedQ, status: 'PENDING_APPROVAL' };
        router.replace(`/quotation/${savedQ.id}/edit`);
      }
    }
    toast.add({
      severity: 'info',
      summary: 'Đã gửi xét duyệt',
      detail: `Báo giá ${savedQ?.quotation_number || form.value.quotation_number} đã được chuyển sang trạng thái Chờ duyệt.`,
      life: 3000
    });
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
