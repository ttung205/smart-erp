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
          @click="handleBack"
        />

        <div class="flex flex-col">
          <h1 class="text-base font-bold text-slate-900 m-0 tracking-tight flex items-center gap-2">
            <span>{{ isEditMode ? `Chứng Từ Bán Hàng: ${form.invoice_number}` : 'Lập Chứng Từ Bán Hàng Mới (Sales Invoice)' }}</span>
            <Badge
              :severity="getWarehouseStatusMeta(form.warehouse_status).severity"
              :value="getWarehouseStatusMeta(form.warehouse_status).label"
            />
            <Badge
              v-if="form.accounting_status === 'POSTED'"
              severity="success"
              value="Đã ghi sổ"
            />
          </h1>
          <span class="text-xs text-slate-500">
            {{ isEditMode ? 'Xem chi tiết chứng từ bán hàng, quản lý giữ chỗ tồn kho và theo dõi tiến độ điều phối' : 'Lập chứng từ bán hàng từ yêu cầu giao hàng đã duyệt để giữ chỗ tồn kho và gửi lệnh xuất kho' }}
          </span>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="flex items-center gap-2.5">
        <!-- Nút Đồng bộ / Kích hoạt Giữ Chỗ Tồn Kho (Khi đang ở chế độ xem chứng từ) -->
        <Button
          v-if="isEditMode && form.accounting_status !== 'POSTED'"
          label="Đồng Bộ Giữ Chỗ Kho"
          icon="pi pi-lock"
          size="small"
          severity="help"
          outlined
          class="!text-xs !py-2 !px-3 rounded-[6px] !font-semibold"
          :loading="syncingReserve"
          v-tooltip.bottom="'Gửi lệnh giữ chỗ (Reserve) tới Inventory Service cho chứng từ này'"
          @click="handleSyncReserve"
        />

        <!-- Nút Gửi xuống kho (khi chưa gửi) -->
        <Button
          v-if="form.warehouse_status === 'PENDING_WAREHOUSE' || !isEditMode"
          label="Gửi Xuống Kho"
          icon="pi pi-send"
          size="small"
          class="!bg-indigo-600 hover:!bg-indigo-700 !text-white !border-indigo-600 !text-xs !py-2 !px-3.5 rounded-[6px] !font-bold shadow-sm"
          :loading="saving"
          @click="saveInvoice(true)"
        />

        <!-- Nút Chuyển sang trang Điều Phối Kho khi đã gửi -->
        <Button
          v-else
          label="Trang Yêu Cầu Xuất Kho ➔"
          icon="pi pi-dolly"
          size="small"
          class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 !text-xs !py-2 !px-3.5 rounded-[6px] !font-semibold shadow-sm"
          @click="$router.push('/warehouse-request-sale')"
        />

        <!-- Nút Lưu Chứng Từ (Chờ gửi kho) -->
        <Button
          v-if="form.accounting_status !== 'POSTED'"
          label="Lưu Chứng Từ & Giữ Kho"
          icon="pi pi-save"
          size="small"
          severity="secondary"
          class="!bg-slate-700 hover:!bg-slate-800 !text-white !border-slate-700 !text-xs !py-2 !px-3.5 rounded-[6px] !font-semibold"
          :loading="saving"
          @click="saveInvoice(false)"
        />
      </div>
    </div>

    <!-- Banner Nghiệp Vụ Tồn Kho Chuẩn Quy Trình -->
    <div class="bg-indigo-50/80 border border-indigo-200 rounded-[6px] p-3 text-xs text-indigo-950 flex items-start gap-3">
      <div class="w-7 h-7 rounded-[4px] bg-indigo-100 text-indigo-700 flex items-center justify-center shrink-0">
        <i class="pi pi-lock text-sm"></i>
      </div>
      <div class="space-y-0.5">
        <div class="font-bold text-indigo-900 uppercase tracking-wide text-xs">
          Quy chế Giữ chỗ Tồn kho & Gửi Lệnh Xuất Kho Logistics
        </div>
        <p class="m-0 text-[11px] text-indigo-800 leading-relaxed">
          Khi Người duyệt <strong>lập/lưu chứng từ bán hàng</strong>, hệ thống sẽ tự động gửi lệnh <strong>giữ chỗ tồn kho (Reserve) và trừ tồn khả dụng (Available)</strong> tại Inventory Service (nguyên tắc: <em>ai tạo chứng từ giao hàng trước thì giữ chỗ trước</em>). Khi phòng Logistics thực hiện <strong>"Ghi Sổ Xuất Kho"</strong> tại trang Yêu Cầu Xuất Kho, hệ thống sẽ <strong>trừ tồn kho vật lý (On-Hand)</strong> và giải phóng tồn giữ chỗ.
        </p>
      </div>
    </div>

    <!-- 2. Form Thông Tin Chứng Từ Bán Hàng (Bố cục 2 cột chuẩn ERP) -->
    <div class="flex flex-col lg:flex-row gap-4 items-stretch">
      <!-- Cột Trái: 63% (Số chứng từ, Ngày lập, Khách hàng, Đơn vị tiền tệ, Tỉ giá, Người tạo, Ghi chú) -->
      <div class="w-full lg:w-[63%] bg-white border border-slate-200 rounded-[8px] p-5 shadow-sm space-y-4">
        <div class="text-xs font-bold text-slate-800 uppercase tracking-wide flex items-center gap-1.5 border-b border-slate-100 pb-2">
          <i class="pi pi-file-edit text-indigo-600"></i>
          <span>Thông Tin Chứng Từ Bán Hàng</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-12 gap-3.5 items-start">
          <!-- Số chứng từ -->
          <div class="md:col-span-6 flex flex-col gap-1.5 min-w-0">
            <label class="text-xs font-bold text-slate-700 uppercase tracking-wide">
              Số chứng từ <span class="text-red-500">*</span>
            </label>
            <InputText
              v-model="form.invoice_number"
              class="w-full text-xs font-mono font-bold text-indigo-700 rounded-[6px]"
              size="small"
              placeholder="INV-2026-XXXX"
            />
          </div>

          <!-- Ngày chứng từ (Ngày tạo chứng từ) -->
          <div class="md:col-span-6 flex flex-col gap-1.5 min-w-0">
            <label class="text-xs font-bold text-slate-700 uppercase tracking-wide">
              Ngày chứng từ (Ngày tạo) <span class="text-red-500">*</span>
            </label>
            <InputText
              v-model="form.invoice_date"
              type="date"
              class="w-full text-xs font-mono rounded-[6px]"
              size="small"
            />
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-12 gap-3.5 items-start">
          <!-- Khách hàng (Lấy từ order_id) -->
          <div class="md:col-span-6 flex flex-col gap-1.5 min-w-0">
            <label class="text-xs font-bold text-slate-700 uppercase tracking-wide">
              Khách hàng (Theo Đơn hàng)
            </label>
            <InputText
              :value="form.customer_name"
              readonly
              disabled
              class="w-full text-xs bg-slate-50 font-semibold text-slate-800 rounded-[6px]"
              size="small"
            />
          </div>

          <!-- Người tạo chứng từ -->
          <div class="md:col-span-6 flex flex-col gap-1.5 min-w-0">
            <label class="text-xs font-bold text-slate-700 uppercase tracking-wide">
              Người tạo chứng từ <span class="text-red-500">*</span>
            </label>
            <InputText
              v-model="form.created_by"
              class="w-full text-xs rounded-[6px]"
              size="small"
              placeholder="Tên nhân viên tạo chứng từ..."
            />
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-12 gap-3.5 items-start">
          <!-- Đơn vị tiền tệ (Lấy từ đơn hàng) -->
          <div class="md:col-span-6 flex flex-col gap-1.5 min-w-0">
            <label class="text-xs font-bold text-slate-700 uppercase tracking-wide">
              Đơn vị tiền tệ (Lấy từ Đơn)
            </label>
            <Select
              v-model="form.currency"
              :options="currencyOptions"
              class="w-full text-xs font-mono font-bold rounded-[6px]"
              size="small"
            />
          </div>

          <!-- Tỉ giá quy đổi (Lấy từ đơn hàng, cho phép thay đổi) -->
          <div class="md:col-span-6 flex flex-col gap-1.5 min-w-0">
            <div class="flex items-center justify-between">
              <label class="text-xs font-bold text-slate-700 uppercase tracking-wide">
                Tỉ giá quy đổi
              </label>
              <span class="text-[10px] text-indigo-600 font-semibold">Cho phép điều chỉnh</span>
            </div>
            <InputNumber
              v-model="form.exchange_rate"
              :min="0.0001"
              :maxFractionDigits="4"
              class="w-full text-xs font-mono rounded-[6px]"
              size="small"
              placeholder="1.0"
            />
          </div>
        </div>

        <!-- Ghi chú chứng từ -->
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-bold text-slate-700 uppercase tracking-wide">
            Ghi chú chứng từ
          </label>
          <Textarea
            v-model="form.notes"
            rows="2"
            class="w-full text-xs rounded-[6px]"
            placeholder="Nhập ghi chú xuất hàng hoặc lưu ý cho phòng Logistics..."
          />
        </div>
      </div>

      <!-- Cột Phải: 37% (Tham chiếu Đơn Bán Hàng, Phiếu Giao Hàng, Kho Xuất, Điều khoản) -->
      <div class="w-full lg:w-[37%] bg-white border border-slate-200 rounded-[8px] p-5 shadow-sm space-y-4 flex flex-col justify-between">
        <div>
          <div class="text-xs font-bold text-slate-800 uppercase tracking-wide flex items-center gap-1.5 border-b border-slate-100 pb-2 mb-3">
            <i class="pi pi-link text-sky-600"></i>
            <span>Nguồn Gốc & Điều Phối</span>
          </div>

          <div class="space-y-3 text-xs">
            <!-- Đơn bán hàng tham chiếu -->
            <div class="flex flex-col gap-1">
              <label class="text-slate-500 font-medium">Số đơn hàng (SO):</label>
              <div class="font-mono font-bold text-slate-800 bg-slate-50 border border-slate-200 px-2.5 py-1.5 rounded-[6px]">
                {{ form.customer_order_number || form.order_number || '---' }}
              </div>
            </div>

            <!-- Phiếu yêu cầu giao hàng tham chiếu -->
            <div class="flex flex-col gap-1">
              <label class="text-slate-500 font-medium">Phiếu yêu cầu giao hàng (DR):</label>
              <div class="font-mono font-bold text-sky-700 bg-sky-50 border border-sky-200 px-2.5 py-1.5 rounded-[6px]">
                #{{ form.delivery_request_id || form.delivery_request_number || '---' }}
              </div>
            </div>

            <!-- Kho xuất hàng -->
            <div class="flex flex-col gap-1">
              <label class="text-slate-500 font-medium">Kho xuất hàng:</label>
              <InputText
                v-model="form.warehouse_name"
                class="w-full text-xs rounded-[6px]"
                size="small"
                @change="refreshStockInfo"
              />
            </div>

            <!-- Điều khoản thanh toán -->
            <div class="flex flex-col gap-1">
              <label class="text-slate-500 font-medium">Điều khoản thanh toán:</label>
              <Select
                v-model="form.payment_terms"
                :options="paymentTermsOptions"
                class="w-full text-xs rounded-[6px]"
                size="small"
              />
            </div>
          </div>
        </div>

        <!-- Tóm tắt trạng thái -->
        <div class="bg-slate-50 border border-slate-200 rounded-[6px] p-3 space-y-2 text-xs">
          <div class="flex justify-between items-center">
            <span class="text-slate-500">Tiến độ kho:</span>
            <Tag :severity="getWarehouseStatusMeta(form.warehouse_status).severity" :value="getWarehouseStatusMeta(form.warehouse_status).label" class="text-[10px] px-2 py-0.5 rounded" />
          </div>
          <div class="flex justify-between items-center">
            <span class="text-slate-500">Sổ cái kế toán:</span>
            <Tag :severity="form.accounting_status === 'POSTED' ? 'success' : 'secondary'" :value="form.accounting_status === 'POSTED' ? 'Đã ghi sổ' : 'Chưa ghi sổ'" class="text-[10px] px-2 py-0.5 rounded" />
          </div>
        </div>
      </div>
    </div>

    <!-- 3. Bảng Danh Sách Mặt Hàng (Hiển thị Real-time Tồn Kho & Giữ Chỗ) -->
    <div class="bg-white border border-slate-200 rounded-[8px] shadow-sm overflow-hidden">
      <div class="bg-slate-50 px-4 py-3 border-b border-slate-200 flex justify-between items-center">
        <div class="flex items-center gap-2">
          <i class="pi pi-list text-slate-600"></i>
          <span class="text-xs font-bold text-slate-800 uppercase tracking-wide">
            Bảng Mặt Hàng Xuất Hóa Đơn & Tình Trạng Tồn Kho
          </span>
          <span class="text-[11px] text-slate-500 font-normal">
            (Hệ thống tự động kiểm tra tồn kho tại <strong>{{ form.warehouse_name }}</strong>)
          </span>
        </div>
        <span class="text-xs font-mono font-semibold text-slate-600">
          Tổng cộng: <strong>{{ form.items.length }}</strong> mặt hàng
        </span>
      </div>

      <DataTable
        :value="form.items"
        showGridlines
        responsiveLayout="scroll"
        class="clean-datatable text-xs"
      >
        <Column header="#" style="width: 40px" class="text-center">
          <template #body="{ index }">
            <span class="font-mono text-slate-400">{{ index + 1 }}</span>
          </template>
        </Column>

        <Column field="sku" header="SKU" style="width: 130px">
          <template #body="{ data }">
            <span class="font-mono text-xs font-bold text-sky-700 bg-sky-50 px-1.5 py-0.5 rounded border border-sky-200">
              {{ data.sku }}
            </span>
          </template>
        </Column>

        <Column field="product_name" header="Tên Mặt Hàng" style="min-width: 150px">
          <template #body="{ data }">
            <span class="font-semibold text-slate-800">{{ data.product_name }}</span>
          </template>
        </Column>

        <!-- Tình trạng Tồn Kho Thực Tế & Khả Dụng tại Kho Xuất -->
        <Column header="Tồn Kho Hiện Tại" style="width: 150px" class="text-center">
          <template #body="{ data }">
            <div v-if="stockMap[data.sku]" class="flex flex-col items-center gap-0.5 text-[11px]">
              <div class="flex items-center gap-1 font-mono">
                <span class="text-slate-500">Tồn: <strong>{{ stockMap[data.sku].quantity_on_hand }}</strong></span>
                <span>•</span>
                <span class="text-amber-600 font-semibold">Giữ: {{ stockMap[data.sku].quantity_reserved }}</span>
              </div>
              <div class="font-mono text-xs font-bold" :class="stockMap[data.sku].available >= data.quantity ? 'text-emerald-700' : 'text-red-600'">
                Khả dụng: {{ stockMap[data.sku].available }} {{ data.unit || 'Cái' }}
              </div>
            </div>
            <div v-else class="text-slate-400 text-[11px] font-mono">
              Đang tải...
            </div>
          </template>
        </Column>

        <!-- CỘT 1 ĐƯỢC PHÉP ĐIỀU CHỈNH: Item Description -->
        <Column header="Mô Tả Chi Tiết" style="min-width: 180px">
          <template #body="{ data }">
            <InputText
              v-model="data.description"
              placeholder="Mô tả / ghi chú mặt hàng..."
              class="w-full text-xs rounded py-1 px-2 border-slate-300 focus:border-indigo-500"
              size="small"
            />
          </template>
        </Column>

        <Column field="unit" header="ĐVT" style="width: 60px" class="text-center">
          <template #body="{ data }">
            <span class="text-slate-600">{{ data.unit || 'Cái' }}</span>
          </template>
        </Column>

        <!-- Cột Yêu Cầu Xuất ở Yêu Cầu Giao Hàng (Readonly) -->
        <Column header="Yêu Cầu Xuất" style="width: 100px" class="text-center">
          <template #body="{ data }">
            <span class="font-mono font-bold text-slate-700 bg-slate-100 px-2 py-0.5 rounded border border-slate-200">
              {{ data.requested_quantity }}
            </span>
          </template>
        </Column>

        <!-- CỘT 2 ĐƯỢC PHÉP ĐIỀU CHỈNH: Số lượng xuất thực tế trên chứng từ -->
        <Column header="SL Giữ Chỗ & Xuất" style="width: 130px" class="text-center">
          <template #body="{ data }">
            <InputNumber
              v-model="data.quantity"
              :min="0"
              :max="data.requested_quantity * 2"
              :maxFractionDigits="2"
              class="w-full text-center font-mono font-bold text-indigo-700"
              inputClass="text-center font-mono font-bold text-indigo-700 py-1 px-1 text-xs border-indigo-300 focus:border-indigo-600"
              size="small"
            />
          </template>
        </Column>

        <Column header="Đơn Giá" style="width: 120px" class="text-right">
          <template #body="{ data }">
            <span class="font-mono text-xs text-slate-700">{{ formatCurrency(data.unit_price) }}</span>
          </template>
        </Column>

        <Column header="Thành Tiền" style="width: 130px" class="text-right">
          <template #body="{ data }">
            <span class="font-mono text-xs font-bold text-indigo-700">
              {{ formatCurrency((data.quantity || 0) * (data.unit_price || 0)) }}
            </span>
          </template>
        </Column>
      </DataTable>

      <!-- Footer Tổng Giá Trị -->
      <div class="flex justify-between items-center p-4 bg-slate-50 border-t border-slate-200">
        <div class="text-xs text-slate-500">
          * Số lượng xuất thực tế sẽ là căn cứ giữ chỗ tồn kho (Reserve) và gửi xuống phòng Logistics nhặt hàng.
        </div>
        <div class="flex items-center gap-4 text-xs font-mono">
          <span class="text-slate-600">Tổng Giá Trị Chứng Từ:</span>
          <span class="text-lg font-bold text-indigo-700 num-tabular">
            {{ formatCurrency(calculatedTotal) }} {{ form.currency }}
          </span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import Badge from 'primevue/badge';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Textarea from 'primevue/textarea';
import Select from 'primevue/select';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';

import { salesApi } from '../../api/sales.api';
import { inventoryApi } from '../../api/inventory.api';
import { formatCurrency } from '../../utils/format';

const props = defineProps({
  currentUser: {
    type: Object,
    default: null
  }
});

const route = useRoute();
const router = useRouter();
const toast = useToast();

const isEditMode = computed(() => !!route.params.id && route.params.id !== 'create');
const saving = ref(false);
const syncingReserve = ref(false);
const stockMap = ref({});

const paymentTermsOptions = [
  'Thanh toán ngay (Tiền mặt / CK)',
  'Công nợ 15 ngày',
  'Công nợ 30 ngày',
  'Công nợ 45 ngày',
  'Công nợ 60 ngày'
];

const currencyOptions = ['VND', 'USD', 'EUR', 'JPY'];

const form = ref({
  id: null,
  invoice_number: '',
  invoice_date: new Date().toISOString().split('T')[0],
  currency: 'VND',
  exchange_rate: 1.0,
  created_by: 'admin',
  payment_terms: 'Công nợ 30 ngày',
  notes: '',
  delivery_request_id: null,
  delivery_request_number: '',
  order_id: null,
  order_number: '',
  customer_order_number: '',
  customer_name: '',
  warehouse_name: 'Kho Tổng Miền Bắc (Hà Nội)',
  warehouse_status: 'PENDING_WAREHOUSE',
  accounting_status: 'UNPOSTED',
  items: []
});

const getWarehouseStatusMeta = (st) => {
  const map = {
    PENDING_WAREHOUSE: { label: 'Chưa gửi kho', severity: 'secondary' },
    SENT_TO_WAREHOUSE: { label: 'Chờ xử lý', severity: 'warn' },
    PROCESSED: { label: 'Đã xử lý', severity: 'info' },
    PICKING: { label: 'Đang soạn', severity: 'warn' },
    PACKED: { label: 'Đã đóng gói', severity: 'secondary' },
    DISPATCHED: { label: 'Đang giao', severity: 'warn' },
    DELIVERED: { label: 'Đã xuất kho', severity: 'success' }
  };
  return map[st] || { label: st || 'Chờ gửi kho', severity: 'secondary' };
};

const generateRandomInvoiceNumber = () => {
  const rand = Math.floor(1000 + Math.random() * 9000);
  return `INV-2026-${rand}`;
};

const calculatedTotal = computed(() => {
  return form.value.items.reduce((acc, it) => acc + (Number(it.quantity) || 0) * (Number(it.unit_price) || 0), 0);
});

import { FALLBACK_CATALOG_PRODUCTS } from '../../constants/catalogProducts.constant';
import { catalogApi } from '../../api/catalog.api';

// Lấy thông tin tồn kho thời gian thực từ inventory-service
const refreshStockInfo = async () => {
  try {
    const warehouseId = form.value.warehouse_name.includes('TP.HCM') || form.value.warehouse_name.includes('Nam') ? 2 : (form.value.warehouse_name.includes('Đà Nẵng') || form.value.warehouse_name.includes('Trung') ? 3 : 1);
    
    // Tạo map PID <-> SKU từ Catalog
    const pidToSkuMap = new Map();
    FALLBACK_CATALOG_PRODUCTS.forEach(p => {
      if (p.id && p.sku) pidToSkuMap.set(Number(p.id), p.sku.trim().toUpperCase());
    });

    try {
      const catRes = await catalogApi.getProducts({ limit: 500 });
      const catList = catRes?.data || (Array.isArray(catRes) ? catRes : []);
      if (Array.isArray(catList)) {
        catList.forEach(p => {
          if (p.id && (p.sku || p.code)) {
            pidToSkuMap.set(Number(p.id), (p.sku || p.code).trim().toUpperCase());
          }
        });
      }
    } catch {}

    const res = await inventoryApi.getStocks({ warehouseId });
    const list = res?.data || (Array.isArray(res) ? res : []);
    if (Array.isArray(list)) {
      const map = {};
      list.forEach(s => {
        const pid = Number(s.product_id ?? s.productId ?? 0);
        const sku = (s.sku || pidToSkuMap.get(pid) || '').trim().toUpperCase();
        if (sku) {
          map[sku] = {
            quantity_on_hand: Number(s.quantity_on_hand ?? s.quantityOnHand ?? 0),
            quantity_reserved: Number(s.quantity_reserved ?? s.quantityReserved ?? 0),
            available: Number(s.available ?? ((Number(s.quantity_on_hand ?? 0)) - (Number(s.quantity_reserved ?? 0))))
          };
        }
      });
      stockMap.value = map;
    }
  } catch (err) {
    console.warn('Không thể tải dữ liệu tồn kho:', err);
  }
};

// Load dữ liệu khi mở form
const initFormData = async () => {
  const user = props.currentUser || JSON.parse(localStorage.getItem('user') || '{}');
  const userCreator = user.name || user.email || 'admin';

  // Trường hợp 1: Chế độ Edit / View chi tiết từ invoice ID
  if (isEditMode.value) {
    try {
      const res = await salesApi.getInvoiceById(route.params.id);
      const inv = res.data;
      if (inv) {
        form.value = {
          id: inv.id,
          invoice_number: inv.invoice_number,
          invoice_date: inv.invoice_date,
          currency: inv.currency || 'VND',
          exchange_rate: Number(inv.exchange_rate) || 1.0,
          created_by: inv.created_by || userCreator,
          payment_terms: inv.payment_terms || 'Công nợ 30 ngày',
          notes: inv.notes || '',
          delivery_request_id: inv.delivery_request_id,
          delivery_request_number: inv.delivery_request_number,
          order_id: inv.order_id,
          order_number: inv.order_number,
          customer_order_number: inv.customer_order_number,
          customer_name: inv.customer_name,
          warehouse_name: inv.warehouse_name || 'Kho Tổng Miền Bắc (Hà Nội)',
          warehouse_status: inv.warehouse_status || 'PENDING_WAREHOUSE',
          accounting_status: inv.accounting_status || 'UNPOSTED',
          items: (inv.items || []).map((it) => ({
            id: it.id,
            delivery_request_item_id: it.delivery_request_item_id,
            order_item_id: it.order_item_id,
            product_id: it.product_id || it.orderItem?.product_id,
            sku: it.sku,
            product_name: it.product_name,
            description: it.description || it.product_name,
            unit: it.unit || 'Cái',
            requested_quantity: Number(it.requested_quantity || it.quantity),
            quantity: Number(it.quantity),
            unit_price: Number(it.unit_price)
          }))
        };
        refreshStockInfo();
      }
    } catch (err) {
      console.error('Lỗi tải chứng từ:', err);
      toast.add({
        severity: 'error',
        summary: 'Lỗi',
        detail: 'Không tìm thấy chứng từ bán hàng yêu cầu.',
        life: 3000
      });
    }
    return;
  }

  // Trường hợp 2: Lập chứng từ mới từ Delivery Request ID query param
  const drId = route.query.delivery_request_id || route.query.dr_id;
  if (drId) {
    try {
      const res = await salesApi.getDeliveries();
      const dr = (res.data || []).find((d) => String(d.id) === String(drId) || String(d.request_number) === String(drId));
      if (dr) {
        const order = dr.order || {};
        form.value = {
          id: null,
          invoice_number: generateRandomInvoiceNumber(),
          invoice_date: new Date().toISOString().split('T')[0],
          currency: order.currency || dr.currency || 'VND',
          exchange_rate: Number(order.exchange_rate) || 1.0,
          created_by: userCreator,
          payment_terms: order.payment_terms || 'Công nợ 30 ngày',
          notes: `Xuất theo Yêu Cầu Giao Hàng #${dr.id} (${dr.customer_order_number || dr.order_number || ''})`,
          delivery_request_id: dr.id,
          delivery_request_number: dr.request_number || dr.id,
          order_id: dr.order_id,
          order_number: dr.order_number,
          customer_order_number: dr.customer_order_number || dr.order_number,
          customer_name: dr.customer_name,
          warehouse_name: dr.warehouse_name || 'Kho Tổng Miền Bắc (Hà Nội)',
          warehouse_status: 'PENDING_WAREHOUSE',
          accounting_status: 'UNPOSTED',
          items: (dr.items || []).map((it) => {
            const reqQty = Number(it.quantity || it.request_quantity || 1);
            return {
              delivery_request_item_id: it.id,
              order_item_id: it.order_item_id,
              product_id: it.product_id || it.orderItem?.product_id,
              sku: it.sku,
              product_name: it.product_name,
              description: it.description || it.product_name || '',
              unit: it.unit || it.orderItem?.unit || 'Cái',
              requested_quantity: reqQty,
              quantity: reqQty,
              unit_price: Number(it.unit_price || it.orderItem?.selling_price || 0)
            };
          })
        };
        refreshStockInfo();
        return;
      }
    } catch (err) {
      console.error('Lỗi tải thông tin phiếu giao:', err);
    }
  }

  // Mặc định tạo mới rỗng
  form.value.invoice_number = generateRandomInvoiceNumber();
  form.value.created_by = userCreator;
  refreshStockInfo();
};

onMounted(() => {
  initFormData();
});

const handleBack = () => {
  if (route.query.from === 'delivery' || route.query.delivery_request_id) {
    router.push({ path: '/delivery', query: { tab: 'requests' } });
  } else {
    router.push('/invoice');
  }
};

const handleSyncReserve = async () => {
  if (!form.value.id) return;
  syncingReserve.value = true;
  try {
    const res = await salesApi.syncReserve(form.value.id, form.value.warehouse_name);
    toast.add({
      severity: 'success',
      summary: 'Đã Đồng Bộ Giữ Chỗ Tồn Kho',
      detail: `Chứng từ ${form.value.invoice_number} đã được gửi lệnh giữ chỗ thành công tới Inventory Service.`,
      life: 4000
    });
    await refreshStockInfo();
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi giữ chỗ tồn kho',
      detail: err.message || 'Không thể đồng bộ giữ chỗ tồn kho.',
      life: 4000
    });
  } finally {
    syncingReserve.value = false;
  }
};

const saveInvoice = async (sendImmediately = false) => {
  if (!form.value.invoice_number) {
    toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Vui lòng nhập số chứng từ!', life: 3000 });
    return;
  }

  saving.value = true;
  try {
    const payload = {
      invoice_number: form.value.invoice_number,
      delivery_request_id: form.value.delivery_request_id,
      delivery_request_number: form.value.delivery_request_number,
      order_number: form.value.order_number,
      customer_name: form.value.customer_name,
      warehouse_name: form.value.warehouse_name,
      invoice_date: form.value.invoice_date,
      currency: form.value.currency,
      exchange_rate: form.value.exchange_rate,
      created_by: form.value.created_by,
      payment_terms: form.value.payment_terms,
      notes: form.value.notes,
      total_amount: calculatedTotal.value,
      send_to_warehouse: sendImmediately,
      items: form.value.items
    };

    let res;
    if (isEditMode.value && form.value.id) {
      if (sendImmediately) {
        await salesApi.sendInvoiceToWarehouse(form.value.id);
      }
      res = { data: form.value };
    } else {
      res = await salesApi.createInvoice(payload);
    }

    const savedInv = res.data;

    toast.add({
      severity: 'success',
      summary: sendImmediately ? 'Đã Gửi Xuống Kho & Giữ Chỗ Thành Công' : 'Đã Lưu Chứng Từ & Giữ Chỗ Tồn Kho',
      detail: sendImmediately
        ? `Chứng từ ${savedInv.invoice_number} đã giữ chỗ hàng và chuyển sang phòng Logistics (Yêu Cầu Xuất Kho).`
        : `Chứng từ ${savedInv.invoice_number} đã được tạo: Đã cộng Tồn giữ chỗ (Reserved) và trừ Tồn khả dụng (Available) theo quy định!`,
      life: 5000
    });

    if (sendImmediately) {
      router.push('/warehouse-request-sale');
    } else {
      router.push('/invoice');
    }
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi lưu chứng từ',
      detail: err.message || 'Không thể lưu chứng từ bán hàng.',
      life: 4000
    });
  } finally {
    saving.value = false;
  }
};
</script>
