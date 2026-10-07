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
          @click="$router.push('/order')"
        />

        <div class="flex flex-col">
          <h1 class="text-base font-bold text-slate-900 m-0 tracking-tight flex items-center gap-2">
            <span>{{ isEditMode ? `Đơn Bán Hàng: ${form.order_number || form.customer_order_number}` : 'Lập Đơn Bán Hàng Mới (SO)' }}</span>
            <Tag
              :severity="getStatusMeta(form.status).severity"
              :value="getStatusMeta(form.status).label"
              class="text-[11px] px-2 py-0.5 rounded-[4px]"
            />
          </h1>
          <span class="text-xs text-slate-400">
            {{ isEditMode ? 'Xem và chỉnh sửa chi tiết đơn bán hàng cùng các mặt hàng trúng thầu' : 'Đơn bán hàng chính thức (Sales Order) chuyển đổi từ các mặt hàng Item Win hoặc tạo trực tiếp' }}
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
          @click="confirmRejectOrder"
        />

        <!-- Nút Phê duyệt (khi PENDING_APPROVAL và canApprove) -->
        <Button
          v-if="canApprove && form.status === 'PENDING_APPROVAL'"
          label="Duyệt đơn hàng"
          icon="pi pi-check-circle"
          size="small"
          class="!bg-emerald-600 hover:!bg-emerald-700 !text-white !border-emerald-600 !text-xs !py-2 !px-3.5 rounded-[6px] !font-semibold"
          @click="confirmApproveOrder"
        />

        <!-- Nút Chuyển sang yêu cầu giao hàng khi đã duyệt -->
        <Button
          v-if="form.status === 'APPROVED' || form.status === 'CONFIRMED'"
          label="Lập Yêu Cầu Giao Hàng"
          icon="pi pi-truck"
          size="small"
          severity="info"
          outlined
          class="!text-xs !py-2 !px-3.5 rounded-[6px] !font-medium !border-sky-500 !text-sky-600 hover:!bg-sky-50"
          @click="$router.push('/delivery')"
        />

        <!-- Nút Lưu thay đổi (chỉ khi chưa duyệt / chưa khóa) -->
        <Button
          v-if="!isLocked"
          label="Lưu đơn bán hàng"
          icon="pi pi-save"
          size="small"
          class="!bg-emerald-700 hover:!bg-emerald-800 !text-white !border-emerald-700 !text-xs !py-2 !px-4 rounded-[6px] !font-semibold shadow-sm"
          @click="handleSaveOrder"
        />

        <!-- Huy hiệu khóa form khi đã duyệt -->
        <div v-else class="flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 border border-slate-200 rounded-[6px] text-slate-700 text-xs font-semibold">
          <i class="pi pi-lock text-slate-500"></i>
          <span>Đơn đã duyệt (Đã khóa)</span>
        </div>
      </div>
    </div>

    <!-- 2. Form Thông Tin Đơn Bán Hàng (Bố cục 2 cột: Trái 63% - Phải 37% chuẩn ERP) -->
    <div class="flex flex-col lg:flex-row gap-4 items-stretch">
      <!-- Cột Trái: 63% (Khách hàng, Liên hệ, Số đơn nội bộ, Số đơn của khách, Tiền tệ, Tỷ giá) -->
      <div class="w-full lg:w-[63%] bg-white border border-slate-200 rounded-[8px] p-5 shadow-sm flex flex-col justify-between gap-4">
        <!-- Hàng 1 của cột trái: Khách hàng (6 cols) + Liên hệ (6 cols) -->
        <div class="grid grid-cols-1 md:grid-cols-12 gap-3.5 items-start">
          <!-- Chọn khách hàng (Khóa khi kế thừa từ Báo giá hoặc đơn đã duyệt) -->
          <div class="md:col-span-6 flex flex-col gap-1.5 min-w-0">
            <label class="text-xs font-bold text-slate-700 uppercase tracking-wide truncate flex items-center gap-1">
              <span>Khách hàng</span>
              <span class="text-red-500">*</span>
              <i v-if="isFromQuotation" class="pi pi-lock text-[10px] text-slate-400" title="Kế thừa từ Báo giá (Khóa)"></i>
            </label>
            <Select
              v-model="form.customer_id"
              :options="customerOptions"
              optionLabel="label"
              optionValue="id"
              filter
              placeholder="-- Chọn khách hàng doanh nghiệp --"
              class="w-full min-w-0 text-xs rounded-[6px] h-10 flex items-center"
              :class="{ '!bg-slate-50': isLocked || isFromQuotation }"
              :disabled="isLocked || isFromQuotation"
              :pt="{ label: { class: '!truncate' }, root: { class: '!min-w-0' } }"
              @change="onCustomerChange"
            >
              <template #value="slotProps">
                <div v-if="slotProps.value" class="truncate text-xs font-medium text-slate-800">
                  {{ customerOptions.find(c => c.id === slotProps.value)?.label || (form.customer_code ? `${form.customer_code} - ${form.customer_name}` : form.customer_name) }}
                </div>
                <span v-else class="text-slate-400 text-xs truncate">
                  {{ slotProps.placeholder }}
                </span>
              </template>
            </Select>
          </div>

          <!-- Liên hệ khách (Khóa khi kế thừa từ Báo giá hoặc đơn đã duyệt) -->
          <div class="md:col-span-6 flex flex-col gap-1.5 min-w-0">
            <label class="text-xs font-bold text-slate-700 uppercase tracking-wide truncate flex items-center gap-1">
              <span>Liên hệ khách hàng</span>
              <i v-if="isFromQuotation" class="pi pi-lock text-[10px] text-slate-400" title="Kế thừa từ Báo giá (Khóa)"></i>
            </label>
            <div class="flex items-center gap-1.5 w-full min-w-0">
              <Select
                v-model="form.contact_id"
                :options="contactOptions"
                optionLabel="label"
                optionValue="id"
                :placeholder="form.customer_id ? '-- Chọn người liên hệ --' : '(Cần chọn khách hàng)'"
                class="flex-1 min-w-0 text-xs rounded-[6px] h-10 flex items-center"
                :class="{ '!bg-slate-50': isLocked || isFromQuotation }"
                :pt="{ label: { class: '!truncate' }, root: { class: '!min-w-0' } }"
                :disabled="!form.customer_id || isLocked || isFromQuotation"
                @change="onContactChange"
              >
                <template #value="slotProps">
                  <div v-if="slotProps.value" class="truncate text-xs font-medium text-slate-800">
                    {{ contactOptions.find(c => c.id === slotProps.value)?.label || form.contact_name }}
                  </div>
                  <span v-else-if="form.contact_name" class="truncate text-xs font-medium text-slate-800">
                    {{ form.contact_name }}
                  </span>
                  <span v-else class="text-slate-400 text-xs truncate">
                    {{ slotProps.placeholder }}
                  </span>
                </template>
              </Select>
              <Button
                v-if="!isLocked && !isFromQuotation"
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

        <!-- Hàng 2 của cột trái: Địa chỉ giao hàng (Cho phép sửa + Nút áp dụng địa chỉ khách hàng) -->
        <div class="flex flex-col gap-1.5 pt-2 border-t border-slate-100">
          <div class="flex items-center justify-between">
            <label class="text-xs font-bold text-slate-700 uppercase tracking-wide flex items-center gap-1.5 truncate">
              <i class="pi pi-map-marker text-rose-500 text-xs"></i>
              <span>Địa chỉ giao hàng</span>
              <span class="text-[10px] font-normal text-slate-400 lowercase truncate">(có thể sửa nếu giao kho/công trình khác)</span>
            </label>
            <Button
              v-if="!isLocked"
              type="button"
              label="Áp dụng địa chỉ của khách hàng"
              icon="pi pi-copy"
              size="small"
              text
              class="!text-[11px] !py-0.5 !px-2 rounded-[4px] !text-sky-600 hover:!bg-sky-50 !font-semibold shrink-0"
              :disabled="!form.customer_id"
              @click="applyCustomerAddress"
            />
          </div>
          <InputText
            v-model="form.shipping_address"
            placeholder="Nhập địa chỉ giao hàng hoặc bấm 'Áp dụng địa chỉ của khách hàng'..."
            class="w-full text-xs rounded-[6px] h-10 px-3 border-slate-300 focus:!border-indigo-500"
            :class="isLocked ? 'bg-slate-50 text-slate-700' : 'bg-white'"
            :disabled="isLocked"
          />
        </div>

        <!-- Hàng 3 của cột trái: Số đơn nội bộ (4 cols) + Số đơn khách (4 cols) + Tiền tệ (2 cols) + Tỷ giá (2 cols) chống vỡ layout -->
        <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-start pt-2 border-t border-slate-100">
          <!-- Số đơn hàng nội bộ (luôn tự sinh để quản lý nội bộ, không sửa) -->
          <div class="md:col-span-4 min-w-0 flex flex-col gap-1.5">
            <label class="text-xs font-bold text-slate-700 uppercase tracking-wide flex items-center gap-1 truncate" title="Luôn tự sinh theo quy tắc OD-MÃKH-DDMMYY để quản lý nội bộ">
              <span>Số đơn nội bộ</span>
              <i class="pi pi-lock text-[10px] text-slate-400"></i>
            </label>
            <InputText
              :value="form.order_number || (form.customer_code ? previewOrderNumber : '(Tự động sinh khi lưu)')"
              class="w-full min-w-0 text-xs font-mono font-bold text-indigo-700 bg-slate-50 rounded-[6px] h-10 px-3 border-slate-200 truncate"
              readonly
            />
          </div>

          <!-- Số đơn hàng của khách (cạnh cột số đơn nội bộ, cho phép sửa) -->
          <div class="md:col-span-4 min-w-0 flex flex-col gap-1.5">
            <label class="text-xs font-bold text-slate-700 uppercase tracking-wide flex items-center gap-1 truncate" title="Số đơn theo yêu cầu của khách hàng (nếu khách yêu cầu). Để trống sẽ tự động dùng số đơn nội bộ">
              <span>Số đơn của khách</span>
              <span v-if="!isLocked" class="text-[10px] font-normal text-slate-400 lowercase">(sửa)</span>
            </label>
            <InputText
              v-model="form.customer_order_number"
              :placeholder="form.order_number || (form.customer_code ? previewOrderNumber : '(Để trống tự lấy số nội bộ)')"
              class="w-full min-w-0 text-xs font-mono font-bold rounded-[6px] h-10 px-3 border-slate-300 focus:!border-indigo-500"
              :class="isLocked ? 'bg-slate-50 text-slate-700' : 'bg-white text-slate-800'"
              :disabled="isLocked"
            />
          </div>

          <!-- Tiền tệ (Khóa khi kế thừa từ Báo giá) -->
          <div class="md:col-span-2 min-w-0 flex flex-col gap-1.5">
            <label class="text-xs font-bold text-slate-700 uppercase tracking-wide truncate flex items-center gap-1">
              <span>Tiền tệ</span>
              <i v-if="isFromQuotation" class="pi pi-lock text-[10px] text-slate-400" title="Kế thừa từ Báo giá (Khóa)"></i>
            </label>
            <Select
              v-model="form.currency"
              :options="['VND', 'USD', 'EUR']"
              class="w-full min-w-0 text-xs rounded-[6px] h-10 flex items-center"
              :class="{ '!bg-slate-50': isLocked || isFromQuotation }"
              :disabled="isLocked || isFromQuotation"
              :pt="{ root: { class: '!min-w-0 !w-full' } }"
            />
          </div>

          <!-- Tỷ giá (Cho phép sửa, chỉ khóa khi đơn đã duyệt) -->
          <div class="md:col-span-2 min-w-0 flex flex-col gap-1.5">
            <label class="text-xs font-bold text-slate-700 uppercase tracking-wide truncate flex items-center justify-between">
              <span>Tỷ giá</span>
              <span v-if="!isLocked" class="text-[10px] font-normal text-slate-400 lowercase">(sửa)</span>
            </label>
            <InputNumber
              v-model="form.exchange_rate"
              :min="0.0001"
              :minFractionDigits="0"
              :maxFractionDigits="4"
              class="w-full min-w-0 text-xs font-mono h-10 flex"
              inputClass="!w-full !min-w-0 text-xs font-mono py-1 px-2.5 rounded-[6px] h-10 box-border border-slate-300 focus:!border-indigo-500"
              :disabled="isLocked"
            />
          </div>
        </div>
      </div>

      <!-- Cột Phải: 37% (Nhân viên phụ trách, Nhân viên theo dõi, Ngày tạo đơn, Nguồn báo giá, Trạng thái) -->
      <div class="w-full lg:w-[37%] bg-white border border-slate-200 rounded-[8px] p-5 shadow-sm flex flex-col justify-between gap-4">
        <!-- Hàng 1 của cột phải: Nhân viên phụ trách (50%) + Nhân viên theo dõi (50%) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 items-start">
          <div class="flex flex-col gap-1.5 min-w-0">
            <label class="text-xs font-bold text-slate-700 uppercase tracking-wide truncate">
              Nhân viên phụ trách
            </label>
            <div class="h-10 flex items-center gap-2 px-3 bg-slate-50 border border-slate-200 rounded-[6px] min-w-0">
              <div class="w-6 h-6 rounded-full bg-slate-200 text-slate-700 flex items-center justify-center text-xs font-bold font-mono shrink-0">
                {{ (creatorDisplayName || 'A').charAt(0).toUpperCase() }}
              </div>
              <span class="text-xs font-semibold text-slate-800 truncate">{{ creatorDisplayName || 'Quản Trị Viên' }}</span>
            </div>
          </div>

          <div class="flex flex-col gap-1.5 min-w-0">
            <label class="text-xs font-bold text-slate-700 uppercase tracking-wide truncate">
              Nhân viên theo dõi <span class="text-red-500">*</span>
            </label>
            <Select
              v-model="form.assigned_to"
              :options="userOptions"
              optionLabel="label"
              optionValue="id"
              placeholder="Chọn nhân viên..."
              class="w-full min-w-0 text-xs rounded-[6px] h-10 flex items-center"
              :class="{ '!bg-slate-50': isLocked }"
              :disabled="isLocked"
              :pt="{ label: { class: '!truncate' }, root: { class: '!min-w-0' } }"
            />
          </div>
        </div>

        <!-- Hàng 2 của cột phải: TÁCH RIÊNG 3 CỘT (Ngày tạo đơn, Nguồn báo giá, Trạng thái) -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 items-start pt-2 border-t border-slate-100">
          <!-- 1. Ngày tạo đơn -->
          <div class="flex flex-col gap-1.5 min-w-0">
            <label class="text-xs font-bold text-slate-700 uppercase tracking-wide truncate">
              Ngày tạo đơn
            </label>
            <div class="h-10 flex items-center px-2.5 bg-slate-50 border border-slate-200 rounded-[6px] min-w-0">
              <span class="text-xs font-mono font-medium text-slate-800 truncate" :title="formattedOrderDate">
                {{ formattedOrderDate }}
              </span>
            </div>
          </div>

          <!-- 2. Nguồn báo giá (Tách riêng biệt) -->
          <div class="flex flex-col gap-1.5 min-w-0">
            <label class="text-xs font-bold text-slate-700 uppercase tracking-wide truncate">
              Nguồn báo giá
            </label>
            <div class="h-10 flex items-center px-2.5 bg-slate-50 border border-slate-200 rounded-[6px] min-w-0">
              <span v-if="form.quotation_number" class="text-xs font-mono font-bold text-sky-700 truncate" :title="form.quotation_number">
                {{ form.quotation_number }}
              </span>
              <span v-else class="text-xs text-slate-400 italic truncate">Trực tiếp</span>
            </div>
          </div>

          <!-- 3. Trạng thái (Tách riêng biệt) -->
          <div class="flex flex-col gap-1.5 min-w-0">
            <label class="text-xs font-bold text-slate-700 uppercase tracking-wide truncate">
              Trạng thái
            </label>
            <div class="h-10 flex items-center justify-center px-2 bg-slate-50 border border-slate-200 rounded-[6px] min-w-0">
              <Tag
                :severity="getStatusMeta(form.status).severity"
                :value="getStatusMeta(form.status).label"
                class="text-[11px] px-2 py-0.5 rounded-[4px] font-semibold"
              />
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 3. Bảng Mặt Hàng Đơn Bán (Sử dụng component OrderItemTable.vue với prop readonly) -->
    <OrderItemTable
      v-model="form.items"
      :readonly="isLocked"
      @summary-change="onItemsSummaryChange"
    />

    <!-- Dialog Thêm Người Liên Hệ Nhanh -->
    <OrderAddContactDialog
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

import OrderItemTable from '../../components/order/OrderItemTable.vue';
import OrderAddContactDialog from '../../components/order/OrderAddContactDialog.vue';
import { catalogApi } from '../../api/catalog.api';
import { salesApi } from '../../api/sales.api';
import { identityApi } from '../../api/identity.api';
import { isSuperAdmin, hasPermission } from '../../utils/rbac';
import { formatDate } from '../../utils/format';

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

// Đơn bán hàng bị khóa hoàn toàn khi đã được phê duyệt / hoàn tất
const isLocked = computed(() => {
  const lockedStatuses = ['APPROVED', 'CONFIRMED', 'PROCESSING', 'COMPLETED'];
  return lockedStatuses.includes(form.value.status);
});

// Đơn bán hàng kế thừa từ Báo Giá (khóa Khách hàng, Liên hệ, Tiền tệ; cho phép sửa Tỷ giá)
const isFromQuotation = computed(() => {
  return !!form.value.quotation_id || !!form.value.quotation_number;
});

const canApprove = computed(() => {
  if (!props.currentUser) return false;
  if (isSuperAdmin(props.currentUser)) return true;
  return hasPermission('order.approve', props.currentUser);
});

const customers = ref([]);
const users = ref([]);

// Format ngày tạo đơn chuẩn d/m/Y thay vì ISO chuỗi thô
const formattedOrderDate = computed(() => {
  const d = form.value.order_date || form.value.created_at;
  if (!d) return nowDateFormat();
  if (d.includes('T')) {
    const dt = new Date(d);
    if (!isNaN(dt.getTime())) {
      const day = String(dt.getDate()).padStart(2, '0');
      const mon = String(dt.getMonth() + 1).padStart(2, '0');
      const yr = dt.getFullYear();
      return `${day}/${mon}/${yr}`;
    }
  }
  return d;
});

const nowDateFormat = () => {
  const now = new Date();
  const day = String(now.getDate()).padStart(2, '0');
  const mon = String(now.getMonth() + 1).padStart(2, '0');
  const yr = now.getFullYear();
  return `${day}/${mon}/${yr}`;
};

// Danh sách nhân viên cho Select assigned_to
const userOptions = computed(() => {
  return users.value.map((u) => ({
    id: u.id,
    name: u.name,
    label: `${u.name} (${u.email})`
  }));
});

// Dropdown khách hàng
const customerOptions = computed(() => {
  return customers.value.map((c) => ({
    id: c.id,
    code: c.code,
    name: c.name,
    label: `${c.code} - ${c.name}`
  }));
});

// Dropdown người liên hệ
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

// Tên hiển thị người tạo
const creatorDisplayName = computed(() => {
  if (form.value.created_by_name) return form.value.created_by_name;
  const u = users.value.find((usr) => usr.id === form.value.created_by);
  if (u) return u.name;
  return props.currentUser?.name || 'Quản Trị Viên';
});

// Số đơn hàng nội bộ dự kiến: OD-{customer_code}-{ddmmyy}
const previewOrderNumber = computed(() => {
  if (!form.value.customer_code) return '';
  const now = new Date();
  const d = String(now.getDate()).padStart(2, '0');
  const m = String(now.getMonth() + 1).padStart(2, '0');
  const y = String(now.getFullYear()).slice(-2);
  return `OD-${form.value.customer_code}-${d}${m}${y} (Tự động sinh khi lưu)`;
});

// Form data (các trường liên kết dùng ID, không lưu trùng lặp tên)
const form = ref({
  id: '',
  order_number: '',
  customer_order_number: '',
  quotation_id: null,
  quotation_number: '',
  customer_id: null,
  customer_code: '',
  customer_name: '',
  contact_id: null,
  contact_name: '',
  currency: 'VND',
  exchange_rate: 1,
  created_by: props.currentUser?.id || 6,
  created_by_name: '',
  created_at: '',
  order_date: nowDateFormat(),
  assigned_to: props.currentUser?.id || 6,
  assigned_to_name: '',
  status: 'DRAFT',
  notes: '',
  shipping_address: '',
  total_amount: 0,
  subtotal: 0,
  vat_total: 0,
  items: []
});

const showAddContactDialog = ref(false);
const newContact = ref({
  name: '',
  position: '',
  phone: '',
  email: ''
});

const getStatusMeta = (st) => {
  const map = {
    DRAFT: { label: 'Bản nháp', severity: 'secondary' },
    PENDING_APPROVAL: { label: 'Chờ duyệt', severity: 'warn' },
    APPROVED: { label: 'Đã duyệt', severity: 'info' },
    CONFIRMED: { label: 'Đã xác nhận (SO)', severity: 'success' },
    PROCESSING: { label: 'Đang xử lý', severity: 'warn' },
    COMPLETED: { label: 'Hoàn thành', severity: 'success' },
    REJECTED: { label: 'Từ chối', severity: 'danger' },
    CANCELLED: { label: 'Đã hủy', severity: 'danger' }
  };
  return map[st] || { label: st, severity: 'secondary' };
};

// Tự động tính toán tổng tiền trực tiếp từ items
const calculateTotals = () => {
  let sub = 0;
  let vat = 0;
  (form.value.items || []).forEach((it) => {
    const qty = Number(it.quantity) || 1;
    const price = Number(it.selling_price) || Number(it.unit_price) || 0;
    let lineSub = Number(it.subtotal) || 0;
    if (lineSub <= 0) {
      lineSub = qty * price;
    }
    const vatP = Number(it.vat_percent) ?? 10;
    const lineVat = Math.round(lineSub * (vatP / 100));
    sub += lineSub;
    vat += lineVat;
  });
  form.value.subtotal = sub;
  form.value.vat_total = vat;
  form.value.total_amount = sub + vat;
};

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
  // 1. Tải danh sách khách hàng và danh sách nhân viên
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

  const defaultUserId = props.currentUser?.id || 6;

  // 2. Nếu đang chỉnh sửa đơn hàng đã có
  if (isEditMode.value) {
    try {
      const res = await salesApi.getOrderById(route.params.id);
      if (res && res.data) {
        const so = res.data;
        form.value = {
          ...so,
          order_date: so.order_date || nowDateFormat(),
          created_by: isNaN(Number(so.created_by)) ? defaultUserId : Number(so.created_by),
          assigned_to: isNaN(Number(so.assigned_to)) ? defaultUserId : Number(so.assigned_to),
          customer_name: so.customer_name || so.customer?.name || '',
          customer_code: so.customer_code || so.customer?.code || '',
          contact_name: so.contact_name || so.contact?.name || '',
          quotation_number: so.quotation_number || so.quotation?.quotation_number || '',
          shipping_address: so.shipping_address || '',
          items: (so.items || []).map((it) => ({
            ...it,
            delivery_date: it.delivery_date || it.expected_delivery_date || '',
            status: it.status || 'WIN',
            won: true,
            _selected: false
          }))
        };

        // Tính lại tổng tiền nếu trước đó đang lưu là 0
        if (Number(form.value.total_amount) <= 0) {
          calculateTotals();
        }
        return;
      }
    } catch (err) {
      console.error('Lỗi tải chi tiết đơn hàng:', err);
    }
  }

  // 3. Nếu chuyển đổi từ báo giá sang (có pending_so_creation trong sessionStorage)
  const pendingData = sessionStorage.getItem('pending_so_creation');
  if (pendingData) {
    try {
      const parsed = JSON.parse(pendingData);
      sessionStorage.removeItem('pending_so_creation');

      const parsedAssigned = isNaN(Number(parsed.assigned_to)) ? defaultUserId : Number(parsed.assigned_to);

      form.value = {
        id: '',
        order_number: '',
        customer_order_number: '',
        quotation_id: parsed.quotation_id || parsed.id || null,
        quotation_number: parsed.quotation_number || '',
        customer_id: parsed.customer_id || null,
        customer_code: parsed.customer_code || '',
        customer_name: parsed.customer_name || '',
        contact_id: parsed.contact_id || null,
        contact_name: parsed.contact_name || '',
        currency: parsed.currency || 'VND',
        exchange_rate: parsed.exchange_rate || 1,
        created_by: defaultUserId,
        created_at: '',
        order_date: nowDateFormat(),
        assigned_to: parsedAssigned,
        status: 'DRAFT',
        notes: `Đơn bán hàng chuyển đổi từ Báo giá ${parsed.quotation_number || ''}`,
        shipping_address: parsed.shipping_address || '',
        total_amount: 0,
        subtotal: 0,
        vat_total: 0,
        items: (parsed.items || []).map((it) => ({
          ...it,
          delivery_date: it.delivery_date || it.expected_delivery_date || '',
          status: 'WIN',
          won: true,
          _selected: false
        }))
      };

      calculateTotals();

      toast.add({
        severity: 'info',
        summary: 'Nhận dữ liệu từ Báo Giá',
        detail: `Đã đưa thông tin khách hàng và ${form.value.items.length} mặt hàng trúng thầu (WIN) vào đơn bán hàng mới.`,
        life: 4000
      });
      return;
    } catch (err) {
      console.error('Lỗi phân tích pending_so_creation:', err);
    }
  }

  // 4. Bắt buộc đơn bán hàng phải đi từ Báo giá đã duyệt
  toast.add({
    severity: 'warn',
    summary: 'Yêu cầu đi từ Báo Giá',
    detail: 'Tất cả đơn bán hàng đều phải đi từ Báo giá đã duyệt. Đang chuyển hướng sang trang Báo Giá...',
    life: 3500
  });
  router.replace('/quotation');
};

const onCustomerChange = () => {
  const cust = customers.value.find((c) => c.id === form.value.customer_id);
  if (cust) {
    form.value.customer_code = cust.code;
    form.value.customer_name = cust.name;
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

const applyCustomerAddress = () => {
  const cust = customers.value.find((c) => c.id === form.value.customer_id);
  if (cust && cust.address) {
    form.value.shipping_address = cust.address;
    toast.add({
      severity: 'info',
      summary: 'Địa chỉ giao hàng',
      detail: `Đã áp dụng địa chỉ khách hàng: ${cust.address}`,
      life: 3000
    });
  } else {
    toast.add({
      severity: 'warn',
      summary: 'Không có địa chỉ',
      detail: 'Khách hàng này chưa có địa chỉ lưu trong hệ thống.',
      life: 3000
    });
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
      const res = await catalogApi.addCustomerContact(currentCust.id, data);
      const savedContact = res.data;
      if (!currentCust.contacts) currentCust.contacts = [];
      currentCust.contacts.push(savedContact);

      form.value.contact_id = savedContact.id;
      form.value.contact_name = savedContact.name;

      toast.add({
        severity: 'success',
        summary: 'Thêm người liên hệ',
        detail: `Đã thêm ${savedContact.name} vào liên hệ của ${currentCust.name}.`,
        life: 3000
      });
      newContact.value = { name: '', position: '', phone: '', email: '' };
      showAddContactDialog.value = false;
    } catch (err) {
      toast.add({
        severity: 'error',
        summary: 'Lỗi thêm người liên hệ',
        detail: err.message || 'Không thể lưu người liên hệ.',
        life: 3000
      });
    }
  }
};

const onItemsSummaryChange = (summary) => {
  if (summary && summary.grandTotal !== undefined) {
    form.value.subtotal = summary.subtotal;
    form.value.vat_total = summary.vatTotal;
    form.value.total_amount = summary.grandTotal;
  } else {
    calculateTotals();
  }
};

// 1. Gửi xét duyệt
const confirmSubmitApproval = () => {
  confirm.require({
    header: 'Xác Nhận Gửi Xét Duyệt Đơn Bán Hàng',
    message: `Bạn có chắc chắn muốn gửi xét duyệt đơn bán hàng ${form.value.customer_order_number || form.value.order_number} không?`,
    icon: 'pi pi-send',
    rejectLabel: 'Hủy bỏ',
    rejectProps: { severity: 'secondary', outlined: true },
    acceptLabel: 'Xác nhận gửi duyệt',
    acceptProps: { severity: 'info' },
    accept: async () => {
      await handleSubmitApproval();
    }
  });
};

const handleSubmitApproval = async () => {
  if (!form.value.customer_id) {
    alert('Vui lòng chọn khách hàng trước khi gửi duyệt!');
    return;
  }
  if (!form.value.items || form.value.items.length === 0) {
    alert('Vui lòng thêm ít nhất 1 dòng mặt hàng trước khi gửi duyệt!');
    return;
  }

  calculateTotals();

  try {
    let savedSO;
    if (isEditMode.value) {
      await salesApi.updateOrder(form.value.id, form.value);
      await salesApi.submitOrderApproval(form.value.id);
      form.value.status = 'PENDING_APPROVAL';
    } else {
      form.value.status = 'PENDING_APPROVAL';
      const res = await salesApi.createOrder(form.value);
      savedSO = res.data;
      if (savedSO && savedSO.id) {
        form.value = { ...form.value, ...savedSO, status: 'PENDING_APPROVAL' };
        router.replace(`/order/${savedSO.id}/edit`);
      }
    }
    toast.add({
      severity: 'info',
      summary: 'Đã gửi xét duyệt',
      detail: `Đơn bán hàng ${savedSO?.order_number || form.value.order_number} đã được chuyển sang trạng thái Chờ duyệt.`,
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

// 2. Phê duyệt
const confirmApproveOrder = () => {
  confirm.require({
    header: 'Xác Nhận Phê Duyệt Đơn Bán Hàng',
    message: `Bạn có chắc chắn muốn phê duyệt đơn bán hàng ${form.value.customer_order_number || form.value.order_number} không? Sau khi duyệt có thể lập lệnh giao hàng.`,
    icon: 'pi pi-check-circle',
    rejectLabel: 'Hủy bỏ',
    rejectProps: { severity: 'secondary', outlined: true },
    acceptLabel: 'Đồng ý phê duyệt',
    acceptProps: { severity: 'success' },
    accept: async () => {
      await handleApproveOrder();
    }
  });
};

const handleApproveOrder = async () => {
  try {
    await salesApi.approveOrder(form.value.id, props.currentUser?.id || 6);
    form.value.status = 'APPROVED';
    toast.add({
      severity: 'success',
      summary: 'Đã duyệt đơn bán hàng',
      detail: `Đơn bán hàng ${form.value.order_number} đã được phê duyệt thành công!`,
      life: 3000
    });
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi phê duyệt',
      detail: err.message || 'Không thể duyệt đơn bán hàng.',
      life: 3000
    });
  }
};

// 3. Từ chối
const confirmRejectOrder = () => {
  confirm.require({
    header: 'Xác Nhận Từ Chối Đơn Bán Hàng',
    message: `Bạn có chắc chắn muốn từ chối đơn bán hàng ${form.value.customer_order_number || form.value.order_number} không? Trạng thái sẽ chuyển sang 'Từ chối'.`,
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: 'Hủy bỏ',
    rejectProps: { severity: 'secondary', outlined: true },
    acceptLabel: 'Xác nhận từ chối',
    acceptProps: { severity: 'danger' },
    accept: async () => {
      await handleRejectOrder();
    }
  });
};

const handleRejectOrder = async () => {
  try {
    await salesApi.rejectOrder(form.value.id);
    form.value.status = 'REJECTED';
    toast.add({
      severity: 'warn',
      summary: 'Đã từ chối đơn hàng',
      detail: `Đơn bán hàng ${form.value.order_number} đã chuyển sang trạng thái Từ chối.`,
      life: 3000
    });
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi từ chối đơn hàng',
      detail: err.message || 'Không thể từ chối đơn bán hàng.',
      life: 3000
    });
  }
};

// 4. Lưu đơn bán hàng
const handleSaveOrder = async () => {
  if (!form.value.customer_id) {
    alert('Vui lòng chọn khách hàng!');
    return;
  }
  if (!form.value.items || form.value.items.length === 0) {
    alert('Vui lòng thêm ít nhất 1 dòng mặt hàng vào đơn bán hàng!');
    return;
  }

  // Tự động tính toán lại toàn bộ subtotal, vat_total, total_amount trước khi gửi
  calculateTotals();

  try {
    let savedSO;
    if (isEditMode.value) {
      const res = await salesApi.updateOrder(form.value.id, form.value);
      savedSO = res.data;
    } else {
      const res = await salesApi.createOrder(form.value);
      savedSO = res.data;
      if (savedSO && savedSO.id) {
        form.value = { ...form.value, ...savedSO };
        router.replace(`/order/${savedSO.id}/edit`);
      }
    }

    toast.add({
      severity: 'success',
      summary: 'Lưu đơn bán hàng thành công',
      detail: `Đơn bán ${savedSO?.customer_order_number || savedSO?.order_number || form.value.order_number} đã được lưu thành công. Tổng tiền: ${new Intl.NumberFormat('vi-VN').format(form.value.total_amount)} đ`,
      life: 4000
    });
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi lưu đơn hàng',
      detail: err.message || 'Không thể lưu đơn bán hàng lên máy chủ.',
      life: 4000
    });
  }
};
</script>
