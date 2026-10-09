<template>
  <div class="space-y-4 pb-8">
    <PageHeader
      title="Quản Lý Xuất Hóa Đơn GTGT (VAT Invoice)"
      subtitle="Quản lý nghiệp vụ xuất hóa đơn điện tử GTGT theo từng chứng từ, kiểm soát đối chiếu giữa hàng đã xuất kho (TK 1312) và hóa đơn đã lập (TK 1311)"
      badge="Kế Toán & Thuế"
      badge-severity="success"
    />

    <!-- Summary KPI Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
      <div class="bg-white border border-slate-200 rounded-[6px] p-3.5 shadow-none flex items-center justify-between">
        <div>
          <div class="text-[11px] font-bold text-amber-700 uppercase tracking-wider flex items-center gap-1.5">
            <span class="w-2 h-2 rounded-full bg-amber-500"></span>
            Chứng Từ Chờ Xuất HĐ (TK 1312)
          </div>
          <div class="text-xl font-black text-slate-800 mt-1 font-mono">
            {{ unbilledInvoices.length }} <span class="text-xs font-normal text-slate-500">chứng từ</span>
          </div>
          <div class="text-[11px] text-slate-500 mt-0.5">
            Trị giá chờ HĐ: <span class="font-bold text-amber-700 font-mono">{{ formatCurrency(totalUnbilledAmount) }} đ</span>
          </div>
        </div>
        <div class="w-10 h-10 rounded-[6px] bg-amber-50 border border-amber-200 flex items-center justify-center text-amber-600">
          <i class="pi pi-file text-lg"></i>
        </div>
      </div>

      <div class="bg-white border border-slate-200 rounded-[6px] p-3.5 shadow-none flex items-center justify-between">
        <div>
          <div class="text-[11px] font-bold text-emerald-700 uppercase tracking-wider flex items-center gap-1.5">
            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
            Hóa Đơn GTGT Đã Lập (TK 1311)
          </div>
          <div class="text-xl font-black text-slate-800 mt-1 font-mono">
            {{ vatInvoices.length }} <span class="text-xs font-normal text-slate-500">hóa đơn</span>
          </div>
          <div class="text-[11px] text-slate-500 mt-0.5">
            Doanh thu đã HĐ: <span class="font-bold text-emerald-700 font-mono">{{ formatCurrency(totalInvoicedRevenue) }} đ</span>
          </div>
        </div>
        <div class="w-10 h-10 rounded-[6px] bg-emerald-50 border border-emerald-200 flex items-center justify-center text-emerald-600">
          <i class="pi pi-receipt text-lg"></i>
        </div>
      </div>

      <div class="bg-white border border-slate-200 rounded-[6px] p-3.5 shadow-none flex items-center justify-between">
        <div>
          <div class="text-[11px] font-bold text-indigo-700 uppercase tracking-wider flex items-center gap-1.5">
            <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
            Tổng Thuế GTGT Đầu Ra
          </div>
          <div class="text-xl font-black text-indigo-900 mt-1 font-mono">
            {{ formatCurrency(totalVatAmount) }} <span class="text-xs font-normal text-slate-500">đ</span>
          </div>
          <div class="text-[11px] text-slate-500 mt-0.5">
            Tài khoản thuế: <span class="font-bold font-mono">TK 33311</span>
          </div>
        </div>
        <div class="w-10 h-10 rounded-[6px] bg-indigo-50 border border-indigo-200 flex items-center justify-center text-indigo-600">
          <i class="pi pi-percentage text-lg"></i>
        </div>
      </div>

      <div class="bg-white border border-slate-200 rounded-[6px] p-3.5 shadow-none flex items-center justify-between">
        <div>
          <div class="text-[11px] font-bold text-slate-600 uppercase tracking-wider flex items-center gap-1.5">
            <span class="w-2 h-2 rounded-full bg-blue-500"></span>
            Số HĐ GTGT Kế Tiếp
          </div>
          <div class="text-xl font-black text-indigo-700 mt-1 font-mono">
            {{ nextInvoiceInfo.next_number || '00000001' }}
          </div>
          <div class="text-[11px] text-slate-500 mt-0.5">
            Ký hiệu: <span class="font-bold text-slate-700 font-mono">{{ nextInvoiceInfo.series || '1C26TBB' }}</span>
          </div>
        </div>
        <div class="w-10 h-10 rounded-[6px] bg-slate-50 border border-slate-200 flex items-center justify-center text-slate-600">
          <i class="pi pi-hashtag text-lg"></i>
        </div>
      </div>
    </div>

    <!-- Tabs Container -->
    <div class="bg-white border border-slate-200 rounded-[6px] shadow-none overflow-hidden">
      <div class="border-b border-slate-200 bg-slate-50/70 px-3 pt-2 flex items-center justify-between">
        <div class="flex items-center gap-1">
          <button
            type="button"
            class="px-4 py-2 text-xs font-bold rounded-t-[6px] border-t border-l border-r transition-all flex items-center gap-2"
            :class="activeTab === 'unbilled' 
              ? 'bg-white text-indigo-700 border-slate-200 shadow-sm border-b-transparent -mb-px' 
              : 'text-slate-600 hover:text-slate-900 border-transparent'"
            @click="activeTab = 'unbilled'"
          >
            <i class="pi pi-inbox text-xs"></i>
            <span>Chứng Từ Chờ Xuất Hóa Đơn (TK 1312)</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono"
              :class="activeTab === 'unbilled' ? 'bg-amber-100 text-amber-800' : 'bg-slate-200 text-slate-700'">
              {{ unbilledInvoices.length }}
            </span>
          </button>

          <button
            type="button"
            class="px-4 py-2 text-xs font-bold rounded-t-[6px] border-t border-l border-r transition-all flex items-center gap-2"
            :class="activeTab === 'invoiced' 
              ? 'bg-white text-indigo-700 border-slate-200 shadow-sm border-b-transparent -mb-px' 
              : 'text-slate-600 hover:text-slate-900 border-transparent'"
            @click="activeTab = 'invoiced'"
          >
            <i class="pi pi-receipt text-xs"></i>
            <span>Hóa Đơn GTGT Đã Phát Hành (TK 1311)</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono"
              :class="activeTab === 'invoiced' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700'">
              {{ vatInvoices.length }}
            </span>
          </button>
        </div>

        <div class="pb-1.5 flex items-center gap-2">
          <Button
            icon="pi pi-refresh"
            size="small"
            outlined
            severity="secondary"
            class="!text-xs !py-1 !px-2.5 rounded-[6px]"
            label="Làm mới"
            :loading="loading"
            @click="loadAllData"
          />
        </div>
      </div>

      <!-- TAB 1: DANH SÁCH THEO TỪNG CHỨNG TỪ BÁN HÀNG CHỜ XUẤT HÓA ĐƠN (TK 1312) -->
      <div v-if="activeTab === 'unbilled'" class="p-3 space-y-3">
        <!-- Toolbar lọc -->
        <Toolbar class="bg-slate-50/50 border border-slate-200 rounded-[6px] px-3 py-2 shadow-none">
          <template #start>
            <div class="flex items-center gap-2">
              <IconField>
                <InputIcon class="pi pi-search" />
                <InputText
                  v-model="unbilledSearch"
                  placeholder="Tìm theo số chứng từ, đơn hàng, khách hàng, MST..."
                  class="w-80 rounded-[6px]"
                  size="small"
                />
              </IconField>
            </div>
          </template>

          <template #end>
            <div class="flex items-center gap-2">
              <span class="text-xs text-slate-500">
                Tổng số: <strong class="text-slate-800">{{ filteredUnbilledInvoices.length }}</strong> chứng từ cần xuất HĐ
              </span>
            </div>
          </template>
        </Toolbar>

        <!-- Bảng Chứng Từ Chờ Xuất HĐ -->
        <CommonDataTable
          :value="filteredUnbilledInvoices"
          :columns="unbilledInvoiceColumns"
          :rows="10"
          :loading="loading"
          :paginator="filteredUnbilledInvoices.length > 10"
          emptyIcon="pi pi-check-circle"
          emptyTitle="Không có chứng từ nào chờ xuất hóa đơn"
          emptySubtitle="Tất cả các chứng từ xuất kho đều đã được lập hóa đơn GTGT đầy đủ."
        >
          <!-- Số chứng từ bán -->
          <template #col-invoice_number="{ data }">
            <button
              type="button"
              class="font-mono text-xs font-bold text-indigo-700 hover:text-indigo-900 bg-indigo-50 hover:bg-indigo-100 px-2 py-0.5 rounded-[4px] border border-indigo-200 inline-block transition-colors"
              @click="openUnbilledItemsDetail(data)"
            >
              {{ data.invoice_number }}
            </button>
          </template>

          <!-- Đơn hàng -->
          <template #col-order_number="{ data }">
            <span class="font-mono text-xs font-semibold text-slate-700">
              {{ data.order_number || data.order_id || '---' }}
            </span>
          </template>

          <!-- Ngày xuất kho / ghi sổ -->
          <template #col-warehouse_posted_at="{ data }">
            <span class="font-mono text-xs text-slate-600">
              {{ formatDate(data.warehouse_posted_at) }}
            </span>
          </template>

          <!-- Khách hàng -->
          <template #col-customer_name="{ data }">
            <div class="flex flex-col text-xs leading-tight">
              <span class="font-semibold text-slate-800 truncate max-w-xs" :title="data.customer_name">
                {{ data.customer_name }}
              </span>
              <span class="text-[10px] font-mono text-slate-500">
                MST: {{ data.customer_tax_code || '---' }}
              </span>
            </div>
          </template>

          <!-- Số lượng mã hàng -->
          <template #col-items_summary="{ data }">
            <div class="text-center">
              <button
                type="button"
                class="font-mono text-xs text-indigo-700 bg-slate-100 hover:bg-indigo-50 border border-slate-200 px-2 py-0.5 rounded transition-colors flex items-center justify-center gap-1 mx-auto"
                @click="openUnbilledItemsDetail(data)"
              >
                <i class="pi pi-list text-[10px]"></i>
                <span><strong>{{ data.items.length }}</strong> mã ({{ formatNumber(data.total_remaining_qty) }} sp)</span>
              </button>
            </div>
          </template>

          <!-- Trị giá xuất kho -->
          <template #col-total_issued_amount="{ data }">
            <div class="text-right font-mono text-xs text-slate-700">
              {{ formatCurrency(data.total_issued_amount) }} đ
            </div>
          </template>

          <!-- Tiền chưa xuất HĐ (TK 1312) -->
          <template #col-total_unbilled_amount="{ data }">
            <div class="text-right">
              <span class="font-mono text-xs font-bold text-amber-700 bg-amber-50 px-2 py-0.5 rounded-[4px] border border-amber-200 inline-block">
                {{ formatCurrency(data.total_unbilled_amount) }} đ
              </span>
            </div>
          </template>

          <!-- Trạng thái xuất HĐ -->
          <template #col-invoicing_status="{ data }">
            <div class="text-center">
              <Badge
                v-if="data.total_invoiced_qty > 0"
                severity="warn"
                value="Xuất HĐ một phần"
              />
              <Badge
                v-else
                severity="danger"
                value="Chưa xuất HĐ (1312)"
              />
            </div>
          </template>

          <!-- Thao tác: Nút xem chi tiết & Nút icon-only xuất tất cả -->
          <Column header="Thao Tác" class="text-center" style="width: 100px">
            <template #body="{ data }">
              <div class="flex items-center justify-center gap-1.5">
                <!-- Nút 1: Xem chi tiết các mã hàng & chọn xuất ít một -->
                <Button
                  icon="pi pi-eye"
                  outlined
                  size="small"
                  severity="info"
                  class="!w-7 !h-7 !p-0 rounded-[6px]"
                  v-tooltip.bottom="'Xem chi tiết mã hàng & chọn xuất hóa đơn từng phần'"
                  @click="openUnbilledItemsDetail(data)"
                />

                <!-- Nút 2: Xuất toàn bộ nhanh (Chỉ icon, có Confirm Dialog) -->
                <Button
                  icon="pi pi-receipt"
                  size="small"
                  severity="success"
                  class="!w-7 !h-7 !p-0 rounded-[6px] !bg-emerald-600 hover:!bg-emerald-700 !text-white border-none"
                  v-tooltip.bottom="'Xuất toàn bộ hóa đơn VAT cho chứng từ này'"
                  @click="openFullVatConfirm(data)"
                />
              </div>
            </template>
          </Column>
        </CommonDataTable>
      </div>

      <!-- TAB 2: DANH SÁCH HÓA ĐƠN GTGT ĐÃ PHÁT HÀNH (TK 1311) -->
      <div v-if="activeTab === 'invoiced'" class="p-3 space-y-3">
        <!-- Toolbar lọc -->
        <Toolbar class="bg-slate-50/50 border border-slate-200 rounded-[6px] px-3 py-2 shadow-none">
          <template #start>
            <div class="flex items-center gap-2">
              <IconField>
                <InputIcon class="pi pi-search" />
                <InputText
                  v-model="invoicedSearch"
                  placeholder="Tìm theo số HĐ (8 số), ký hiệu, khách hàng, MST..."
                  class="w-80 rounded-[6px]"
                  size="small"
                />
              </IconField>
            </div>
          </template>

          <template #end>
            <div class="flex items-center gap-2">
              <span class="text-xs text-slate-500">
                Tổng cộng: <strong class="text-slate-800">{{ filteredVatInvoices.length }}</strong> hóa đơn GTGT
              </span>
            </div>
          </template>
        </Toolbar>

        <!-- Bảng VAT Invoices -->
        <CommonDataTable
          :value="filteredVatInvoices"
          :columns="vatInvoiceColumns"
          :rows="10"
          :loading="loading"
          :paginator="filteredVatInvoices.length > 10"
          emptyIcon="pi pi-receipt"
          emptyTitle="Chưa có hóa đơn GTGT nào"
          emptySubtitle="Chuyển sang tab 'Chứng Từ Chờ Xuất Hóa Đơn' để thực hiện lập hóa đơn cho các mặt hàng đã xuất kho."
        >
          <!-- Số Hóa Đơn (8 chữ số: 00000001, 00000123) -->
          <template #col-invoice_number="{ data }">
            <button
              type="button"
              class="font-mono text-xs font-bold text-indigo-700 hover:text-indigo-900 bg-indigo-50 hover:bg-indigo-100 px-2.5 py-0.5 rounded-[4px] border border-indigo-200 inline-block transition-colors"
              @click="viewInvoiceDetail(data)"
            >
              {{ data.invoice_number }}
            </button>
          </template>

          <!-- Ký hiệu -->
          <template #col-invoice_series="{ data }">
            <span class="font-mono text-xs font-semibold text-slate-700">
              {{ data.invoice_series || '1C26TBB' }}
            </span>
          </template>

          <!-- Ngày hóa đơn -->
          <template #col-invoice_date="{ data }">
            <span class="font-mono text-xs text-slate-600">
              {{ formatDate(data.invoice_date || data.created_at) }}
            </span>
          </template>

          <!-- Chứng từ bán gốc -->
          <template #col-sales_invoice_number="{ data }">
            <div class="flex flex-col text-xs leading-tight">
              <span class="font-mono font-semibold text-indigo-700">
                {{ data.sales_invoice_number || `INV-#${data.sales_invoice_id}` }}
              </span>
              <span class="text-[10px] text-slate-400 font-mono">ĐH: {{ data.order_number || '---' }}</span>
            </div>
          </template>

          <!-- Khách hàng -->
          <template #col-customer_name="{ data }">
            <div class="flex flex-col text-xs leading-tight">
              <span class="font-semibold text-slate-800 truncate max-w-xs" :title="data.customer_name">
                {{ data.customer_name }}
              </span>
              <span class="text-[10px] font-mono text-slate-500">
                MST: {{ data.customer_tax_code || '---' }}
              </span>
            </div>
          </template>

          <!-- Số mã hàng trong HĐ -->
          <template #col-items_count="{ data }">
            <div class="text-center">
              <button
                type="button"
                class="font-mono text-xs text-indigo-700 bg-slate-100 hover:bg-indigo-50 border border-slate-200 px-2 py-0.5 rounded transition-colors inline-flex items-center gap-1"
                @click="viewInvoiceDetail(data)"
              >
                <i class="pi pi-box text-[10px]"></i>
                <span><strong>{{ (data.items || []).length }}</strong> mã</span>
              </button>
            </div>
          </template>

          <!-- Tiền trước thuế -->
          <template #col-subtotal="{ data }">
            <div class="text-right font-mono text-xs text-slate-700">
              {{ formatCurrency(data.subtotal) }} đ
            </div>
          </template>

          <!-- Tiền thuế VAT -->
          <template #col-vat_total="{ data }">
            <div class="text-right font-mono text-xs text-indigo-700 font-semibold">
              {{ formatCurrency(data.vat_total) }} đ
            </div>
          </template>

          <!-- Tổng tiền thanh toán -->
          <template #col-total_amount="{ data }">
            <div class="text-right font-mono text-xs font-bold text-emerald-800">
              {{ formatCurrency(data.total_amount) }} đ
            </div>
          </template>

          <!-- Trạng thái -->
          <template #col-status="{ data }">
            <div class="text-center">
              <Badge severity="success" value="Đã Phát Hành (TK 1311)" />
            </div>
          </template>

          <!-- Thao tác -->
          <Column header="Thao Tác" class="text-center" style="width: 80px">
            <template #body="{ data }">
              <Button
                icon="pi pi-eye"
                outlined
                size="small"
                severity="info"
                class="!w-7 !h-7 !p-0 rounded-[6px]"
                v-tooltip.bottom="'Xem chi tiết hóa đơn GTGT & danh sách mã hàng'"
                @click="viewInvoiceDetail(data)"
              />
            </template>
          </Column>
        </CommonDataTable>
      </div>
    </div>

    <!-- DIALOG 1: XEM CHI TIẾT MÃ HÀNG & CHỌN TICK XUẤT HÓA ĐƠN TỪNG PHẦN TRỰC TIẾP -->
    <VatInvoiceUnbilledItemsDialog
      v-model:visible="showUnbilledItemsDialog"
      :invoice="selectedUnbilledInvoice"
      :form="partialForm"
      :submitting="submittingPartial"
      @submit="submitPartialVatInvoice"
    />

    <!-- DIALOG 2: CONFIRM XUẤT NHANH TOÀN BỘ HÓA ĐƠN VAT -->
    <VatInvoiceConfirmFullDialog
      v-model:visible="showConfirmFullDialog"
      :invoice="selectedInvoiceForFullVat"
      :next-invoice-info="nextInvoiceInfo"
      :submitting="submittingFull"
      @submit="executeFullVatInvoice"
    />

    <!-- DIALOG 3: XEM CHI TIẾT HÓA ĐƠN GTGT (E-INVOICE PREVIEW) -->
    <VatInvoiceDetailDialog
      v-model:visible="showDetailDialog"
      :invoice="selectedVatInvoice"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useToast } from 'primevue/usetoast';
import Column from 'primevue/column';
import Toolbar from 'primevue/toolbar';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import Badge from 'primevue/badge';

import PageHeader from '../../components/common/PageHeader.vue';
import CommonDataTable from '../../components/common/CommonDataTable.vue';
import VatInvoiceUnbilledItemsDialog from '../../components/invoice/VatInvoiceUnbilledItemsDialog.vue';
import VatInvoiceConfirmFullDialog from '../../components/invoice/VatInvoiceConfirmFullDialog.vue';
import VatInvoiceDetailDialog from '../../components/invoice/VatInvoiceDetailDialog.vue';

import { salesApi } from '../../api/sales.api';
import { formatCurrency, formatDate } from '../../utils/format';

const toast = useToast();

const activeTab = ref('unbilled');
const loading = ref(false);

const unbilledSearch = ref('');
const invoicedSearch = ref('');

const unbilledItems = ref([]);
const vatInvoices = ref([]);
const nextInvoiceInfo = ref({ next_number: '00000001', series: '1C26TBB' });

// Modal states
const showUnbilledItemsDialog = ref(false);
const selectedUnbilledInvoice = ref(null);
const submittingPartial = ref(false);

const partialForm = ref({
  invoice_number: '00000001',
  invoice_series: '1C26TBB',
  invoice_date: new Date().toISOString().split('T')[0]
});

// Confirm Modal for Full Invoice
const showConfirmFullDialog = ref(false);
const selectedInvoiceForFullVat = ref(null);
const submittingFull = ref(false);

// E-invoice detail
const showDetailDialog = ref(false);
const selectedVatInvoice = ref(null);

const formatNumber = (val) => {
  return Number(val || 0).toLocaleString('vi-VN');
};

// Cột bảng Chứng Từ Chờ Xuất HĐ
const unbilledInvoiceColumns = [
  { field: 'invoice_number', header: 'Số Chứng Từ (INV)', sortable: true, width: '150px' },
  { field: 'order_number', header: 'Đơn Hàng (SO)', sortable: true, width: '150px' },
  { field: 'warehouse_posted_at', header: 'Ngày Xuất Kho', sortable: true, width: '120px' },
  { field: 'customer_name', header: 'Khách Hàng & MST', sortable: true },
  { field: 'items_summary', header: 'Danh Sách Mã Hàng', sortable: false, width: '160px', align: 'center' },
  { field: 'total_issued_amount', header: 'Trị Giá Xuất Kho', sortable: true, width: '140px', align: 'right' },
  { field: 'total_unbilled_amount', header: 'Tiền Chờ Xuất HĐ (1312)', sortable: true, width: '170px', align: 'right' },
  { field: 'invoicing_status', header: 'Trạng Thái HĐ', sortable: true, width: '150px', align: 'center' }
];

// Cột bảng Hóa Đơn GTGT Đã Phát Hành
const vatInvoiceColumns = [
  { field: 'invoice_number', header: 'Số Hóa Đơn (8 Số)', sortable: true, width: '140px' },
  { field: 'invoice_series', header: 'Ký Hiệu', sortable: true, width: '100px' },
  { field: 'invoice_date', header: 'Ngày Lập HĐ', sortable: true, width: '110px' },
  { field: 'sales_invoice_number', header: 'Chứng Từ Bán', sortable: true, width: '140px' },
  { field: 'customer_name', header: 'Khách Hàng & MST', sortable: true },
  { field: 'items_count', header: 'Số Mã Hàng', sortable: false, width: '110px', align: 'center' },
  { field: 'subtotal', header: 'Tiền Hàng', sortable: true, width: '130px', align: 'right' },
  { field: 'vat_total', header: 'Thuế VAT', sortable: true, width: '120px', align: 'right' },
  { field: 'total_amount', header: 'Tổng Thanh Toán', sortable: true, width: '140px', align: 'right' },
  { field: 'status', header: 'Trạng Thái HĐ', sortable: true, width: '170px', align: 'center' }
];

// Nhóm các dòng hàng unbilledItems theo từng Chứng Từ Bán Hàng (Sales Invoice)
const unbilledInvoices = computed(() => {
  const map = new Map();

  unbilledItems.value.forEach((it) => {
    const invId = it.invoice_id;
    if (!invId) return;

    if (!map.has(invId)) {
      map.set(invId, {
        id: invId,
        invoice_number: it.invoice_number || `INV-#${invId}`,
        order_id: it.order_id,
        order_number: it.order_number,
        customer_id: it.customer_id,
        customer_name: it.customer_name || it.customer?.name || 'Khách hàng',
        customer_tax_code: it.customer_tax_code || it.customer?.tax_code || '',
        customer_address: it.customer_address || it.customer?.address || '',
        warehouse_posted_at: it.posted_at || it.created_at,
        currency: 'VND',
        items: [],
        total_issued_qty: 0,
        total_invoiced_qty: 0,
        total_remaining_qty: 0,
        total_unbilled_amount: 0,
        total_issued_amount: 0
      });
    }

    const group = map.get(invId);
    const issuedQty = Number(it.quantity || 0);
    const invoicedQty = Number(it.invoiced_quantity || 0);
    const remainingQty = Number(it.remaining_invoice_quantity || (issuedQty - invoicedQty));
    const price = Number(it.unit_price || 0);

    group.items.push({
      ...it,
      _selected: true,
      issued_quantity: issuedQty,
      invoiced_quantity: invoicedQty,
      remaining_invoice_quantity: remainingQty,
      invoice_now_quantity: remainingQty,
      unit_price: price,
      unbilled_amount: remainingQty * price
    });

    group.total_issued_qty += issuedQty;
    group.total_invoiced_qty += invoicedQty;
    group.total_remaining_qty += remainingQty;
    group.total_issued_amount += (issuedQty * price);
    group.total_unbilled_amount += (remainingQty * price);
  });

  return Array.from(map.values());
});

const filteredUnbilledInvoices = computed(() => {
  if (!unbilledSearch.value) return unbilledInvoices.value;
  const q = unbilledSearch.value.toLowerCase().trim();
  return unbilledInvoices.value.filter((inv) => {
    const matchInvNo = (inv.invoice_number || '').toLowerCase().includes(q);
    const matchOrdNo = (inv.order_number || '').toLowerCase().includes(q);
    const matchCustName = (inv.customer_name || '').toLowerCase().includes(q);
    const matchTaxCode = (inv.customer_tax_code || '').toLowerCase().includes(q);
    const matchItem = inv.items.some((it) => 
      (it.sku && it.sku.toLowerCase().includes(q)) || 
      (it.product_name && it.product_name.toLowerCase().includes(q))
    );
    return matchInvNo || matchOrdNo || matchCustName || matchTaxCode || matchItem;
  });
});

const filteredVatInvoices = computed(() => {
  if (!invoicedSearch.value) return vatInvoices.value;
  const q = invoicedSearch.value.toLowerCase().trim();
  return vatInvoices.value.filter((inv) => {
    return (
      (inv.invoice_number && inv.invoice_number.toLowerCase().includes(q)) ||
      (inv.invoice_series && inv.invoice_series.toLowerCase().includes(q)) ||
      (inv.customer_name && inv.customer_name.toLowerCase().includes(q)) ||
      (inv.customer_tax_code && inv.customer_tax_code.toLowerCase().includes(q)) ||
      (inv.sales_invoice_number && inv.sales_invoice_number.toLowerCase().includes(q)) ||
      (inv.order_number && inv.order_number.toLowerCase().includes(q))
    );
  });
});

const totalUnbilledAmount = computed(() => {
  return unbilledItems.value.reduce((sum, it) => {
    const rem = Number(it.remaining_invoice_quantity || it.quantity || 0);
    const price = Number(it.unit_price || 0);
    return sum + (rem * price);
  }, 0);
});

const totalInvoicedRevenue = computed(() => {
  return vatInvoices.value.reduce((sum, it) => sum + Number(it.subtotal || 0), 0);
});

const totalVatAmount = computed(() => {
  return vatInvoices.value.reduce((sum, it) => sum + Number(it.vat_total || 0), 0);
});

const loadAllData = async () => {
  loading.value = true;
  try {
    const [unbilledRes, vatRes, nextNumRes] = await Promise.all([
      salesApi.getUnbilledRevenueItems(),
      salesApi.getVatInvoices(),
      salesApi.getNextVatInvoiceNumber()
    ]);

    unbilledItems.value = unbilledRes.data || [];
    vatInvoices.value = vatRes.data || [];
    if (nextNumRes) {
      nextInvoiceInfo.value = nextNumRes;
      partialForm.value.invoice_number = nextNumRes.next_number || '00000001';
      partialForm.value.invoice_series = nextNumRes.series || '1C26TBB';
    }
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi tải dữ liệu hóa đơn',
      detail: err.message || 'Không thể tải danh sách hóa đơn GTGT.',
      life: 3000
    });
  } finally {
    loading.value = false;
  }
};

// 1. Logic trong Dialog Xem & Xuất Từng Mã Hàng
const openUnbilledItemsDetail = async (invoiceGroup) => {
  // Lấy số hóa đơn mới nhất
  let nextNo = nextInvoiceInfo.value.next_number || '00000001';
  let nextSer = nextInvoiceInfo.value.series || '1C26TBB';
  try {
    const numRes = await salesApi.getNextVatInvoiceNumber();
    if (numRes?.next_number) {
      nextNo = numRes.next_number;
      nextSer = numRes.series || '1C26TBB';
    }
  } catch (e) {
    // fallback
  }

  partialForm.value = {
    invoice_number: nextNo,
    invoice_series: nextSer,
    invoice_date: new Date().toISOString().split('T')[0]
  };

  // Sao chép sâu danh sách items để người dùng thao tác tick chọn & sửa số lượng
  const clonedItems = (invoiceGroup.items || []).map((it) => ({
    ...it,
    _selected: true,
    invoice_now_quantity: it.remaining_invoice_quantity || it.quantity
  }));

  selectedUnbilledInvoice.value = {
    ...invoiceGroup,
    items: clonedItems
  };

  showUnbilledItemsDialog.value = true;
};

const isAllSelected = computed(() => {
  if (!selectedUnbilledInvoice.value?.items?.length) return false;
  return selectedUnbilledInvoice.value.items.every((it) => it._selected);
});

const toggleSelectAll = (checked) => {
  if (!selectedUnbilledInvoice.value?.items) return;
  selectedUnbilledInvoice.value.items.forEach((it) => {
    it._selected = checked;
    if (checked && Number(it.invoice_now_quantity) <= 0) {
      it.invoice_now_quantity = it.remaining_invoice_quantity || it.quantity;
    }
  });
};

const onItemSelectionChange = (it) => {
  if (it._selected && Number(it.invoice_now_quantity) <= 0) {
    it.invoice_now_quantity = it.remaining_invoice_quantity || it.quantity;
  }
};

const onQtyChange = (it) => {
  const max = Number(it.remaining_invoice_quantity || it.quantity || 0);
  if (Number(it.invoice_now_quantity) > max) {
    it.invoice_now_quantity = max;
  }
  it._selected = Number(it.invoice_now_quantity) > 0;
};

const selectedItemsCount = computed(() => {
  if (!selectedUnbilledInvoice.value?.items) return 0;
  return selectedUnbilledInvoice.value.items.filter((it) => it._selected && Number(it.invoice_now_quantity) > 0).length;
});

const partialSubtotal = computed(() => {
  if (!selectedUnbilledInvoice.value?.items) return 0;
  return selectedUnbilledInvoice.value.items.reduce((sum, it) => {
    if (!it._selected) return sum;
    const qty = Number(it.invoice_now_quantity) || 0;
    const price = Number(it.unit_price) || 0;
    return sum + (qty * price);
  }, 0);
});

const partialVatTotal = computed(() => {
  if (!selectedUnbilledInvoice.value?.items) return 0;
  return selectedUnbilledInvoice.value.items.reduce((sum, it) => {
    if (!it._selected) return sum;
    const qty = Number(it.invoice_now_quantity) || 0;
    const price = Number(it.unit_price) || 0;
    const vatRate = Number(it.vat_rate || it.vat_percent || 10);
    return sum + Math.round(qty * price * (vatRate / 100));
  }, 0);
});

const partialTotalAmount = computed(() => {
  return partialSubtotal.value + partialVatTotal.value;
});

const submitPartialVatInvoice = async () => {
  if (!selectedUnbilledInvoice.value) return;

  const validItems = selectedUnbilledInvoice.value.items.filter(
    (it) => it._selected && Number(it.invoice_now_quantity) > 0
  );

  if (validItems.length === 0) {
    toast.add({
      severity: 'warn',
      summary: 'Chưa chọn mặt hàng',
      detail: 'Vui lòng tick chọn ít nhất 1 mặt hàng có số lượng xuất lớn hơn 0.',
      life: 3000
    });
    return;
  }

  submittingPartial.value = true;
  try {
    const payload = {
      invoice_number: partialForm.value.invoice_number,
      invoice_series: partialForm.value.invoice_series,
      invoice_date: partialForm.value.invoice_date,
      sales_invoice_id: selectedUnbilledInvoice.value.id,
      customer_name: selectedUnbilledInvoice.value.customer_name,
      customer_tax_code: selectedUnbilledInvoice.value.customer_tax_code,
      customer_address: selectedUnbilledInvoice.value.customer_address,
      items: validItems.map((it) => ({
        sales_invoice_item_id: it.invoice_item_id || it.sales_invoice_item_id,
        revenue_report_item_id: it.id,
        order_item_id: it.order_item_id,
        product_id: it.product_id,
        sku: it.sku,
        product_name: it.product_name,
        quantity: Number(it.invoice_now_quantity),
        unit_price: Number(it.unit_price),
        vat_percent: Number(it.vat_rate || it.vat_percent || 10)
      }))
    };

    const res = await salesApi.createVatInvoice(payload);

    toast.add({
      severity: 'success',
      summary: 'Xuất Hóa Đơn GTGT Thành Công!',
      detail: `Đã phát hành HĐ ${partialForm.value.invoice_number} và chuyển doanh thu sang TK 1311.`,
      life: 4000
    });

    showUnbilledItemsDialog.value = false;
    loadAllData();
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi xuất hóa đơn VAT',
      detail: err.message || 'Không thể tạo hóa đơn GTGT.',
      life: 3000
    });
  } finally {
    submittingPartial.value = false;
  }
};

// 2. Logic Xuất Toàn Bộ Nhanh với Confirm Dialog
const openFullVatConfirm = (invoiceGroup) => {
  selectedInvoiceForFullVat.value = invoiceGroup;
  showConfirmFullDialog.value = true;
};

const executeFullVatInvoice = async () => {
  if (!selectedInvoiceForFullVat.value) return;

  submittingFull.value = true;
  try {
    let nextNo = nextInvoiceInfo.value.next_number || '00000001';
    let nextSer = nextInvoiceInfo.value.series || '1C26TBB';
    try {
      const numRes = await salesApi.getNextVatInvoiceNumber();
      if (numRes?.next_number) {
        nextNo = numRes.next_number;
        nextSer = numRes.series || '1C26TBB';
      }
    } catch (e) {
      // fallback
    }

    const payload = {
      invoice_number: nextNo,
      invoice_series: nextSer,
      invoice_date: new Date().toISOString().split('T')[0],
      sales_invoice_id: selectedInvoiceForFullVat.value.id,
      customer_name: selectedInvoiceForFullVat.value.customer_name,
      customer_tax_code: selectedInvoiceForFullVat.value.customer_tax_code,
      customer_address: selectedInvoiceForFullVat.value.customer_address,
      items: selectedInvoiceForFullVat.value.items.map((it) => ({
        sales_invoice_item_id: it.invoice_item_id || it.sales_invoice_item_id,
        revenue_report_item_id: it.id,
        order_item_id: it.order_item_id,
        product_id: it.product_id,
        sku: it.sku,
        product_name: it.product_name,
        quantity: Number(it.remaining_invoice_quantity || it.quantity),
        unit_price: Number(it.unit_price),
        vat_percent: Number(it.vat_rate || it.vat_percent || 10)
      }))
    };

    await salesApi.createVatInvoice(payload);

    toast.add({
      severity: 'success',
      summary: 'Xuất Toàn Bộ HĐ VAT Thành Công!',
      detail: `Đã cấp Hóa Đơn GTGT ${nextNo} cho toàn bộ chứng từ ${selectedInvoiceForFullVat.value.invoice_number}.`,
      life: 4000
    });

    showConfirmFullDialog.value = false;
    loadAllData();
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi xuất toàn bộ hóa đơn',
      detail: err.message || 'Không thể xuất hóa đơn VAT.',
      life: 3000
    });
  } finally {
    submittingFull.value = false;
  }
};

// 3. Xem Chi Tiết Hóa Đơn GTGT Đã Phát Hành
const viewInvoiceDetail = (inv) => {
  selectedVatInvoice.value = inv;
  showDetailDialog.value = true;
};

onMounted(() => {
  loadAllData();
});
</script>
