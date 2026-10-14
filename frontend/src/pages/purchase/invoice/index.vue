<template>
  <div class="flex flex-col gap-4">
    <!-- 1. Page Header Chuẩn -->
    <PageHeader
      title="Hóa Đơn & Đối Chiếu 3 Bên (3-Way Matching)"
      subtitle="Đối soát tự động giá trị thanh toán giữa Hợp đồng (PO), Thực nhận kho (GRN) và Hóa đơn tài chính (Invoice)"
    >
      <template #actions>
        <Button
          label="Nhập Hóa Đơn NCC"
          icon="pi pi-plus"
          size="small"
          class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 !rounded-[6px] !text-xs !font-semibold !px-3 !py-1.5"
          @click="$router.push('/purchase/invoices/create')"
        />
      </template>
    </PageHeader>

    <!-- 2. Thẻ Thống Kê Đối Chiếu 3 Bên (1 Hàng Ngang 4 Thẻ) -->
    <div class="grid grid-cols-4 gap-3">
      <div 
        class="bg-white border border-slate-200 rounded-[6px] p-3 flex items-center justify-between cursor-pointer hover:border-amber-300 transition-colors"
        @click="activeTab = 'pending_invoices'"
      >
        <div>
          <span class="text-xs text-amber-600 font-medium block">Hàng đã nhận chờ hóa đơn</span>
          <span class="text-lg font-bold text-amber-700 font-mono tabular-nums">{{ pendingInvoicingList.length }}</span>
        </div>
        <div class="w-9 h-9 rounded-[6px] bg-amber-50 flex items-center justify-center text-amber-600">
          <i class="pi pi-inbox text-base"></i>
        </div>
      </div>

      <div 
        class="bg-white border border-slate-200 rounded-[6px] p-3 flex items-center justify-between cursor-pointer hover:border-emerald-300 transition-colors"
        @click="activeTab = 'invoice_list'"
      >
        <div>
          <span class="text-xs text-emerald-600 font-medium block">Khớp 3 bên (Hợp lệ)</span>
          <span class="text-lg font-bold text-emerald-700 font-mono tabular-nums">{{ stats.matched }}</span>
        </div>
        <div class="w-9 h-9 rounded-[6px] bg-emerald-50 flex items-center justify-center text-emerald-600">
          <i class="pi pi-check-circle text-base"></i>
        </div>
      </div>

      <div 
        class="bg-white border border-slate-200 rounded-[6px] p-3 flex items-center justify-between cursor-pointer hover:border-rose-300 transition-colors"
        @click="activeTab = 'invoice_list'"
      >
        <div>
          <span class="text-xs text-rose-600 font-medium block">Lệch số liệu (Cần xử lý)</span>
          <span class="text-lg font-bold text-rose-700 font-mono tabular-nums">{{ stats.discrepancy }}</span>
        </div>
        <div class="w-9 h-9 rounded-[6px] bg-rose-50 flex items-center justify-center text-rose-600">
          <i class="pi pi-exclamation-triangle text-base"></i>
        </div>
      </div>

      <div 
        class="bg-white border border-slate-200 rounded-[6px] p-3 flex items-center justify-between cursor-pointer hover:border-slate-300 transition-colors"
        @click="activeTab = 'invoice_list'"
      >
        <div>
          <span class="text-xs text-slate-500 font-medium block">Tổng hóa đơn đã lập</span>
          <span class="text-lg font-bold text-slate-900 font-mono tabular-nums">{{ stats.total }}</span>
        </div>
        <div class="w-9 h-9 rounded-[6px] bg-slate-100 flex items-center justify-center text-slate-600">
          <i class="pi pi-receipt text-base"></i>
        </div>
      </div>
    </div>

    <!-- 3. Thanh Tab Điều Hướng (Hairline Tabs) -->
    <div class="bg-white border border-slate-200 rounded-[6px] overflow-hidden flex flex-col">
      <div class="border-b border-slate-200 bg-slate-50/70 px-3 pt-2 flex items-center justify-between flex-wrap gap-2">
        <div class="flex items-center gap-1">
          <!-- TAB 1: LÔ HÀNG ĐÃ NHẬP KHO CHỜ LẬP HÓA ĐƠN -->
          <button
            type="button"
            class="px-4 py-2 text-xs font-bold rounded-t-[6px] border-t border-l border-r transition-all flex items-center gap-2"
            :class="activeTab === 'pending_invoices' 
              ? 'bg-white text-sky-700 border-slate-200 shadow-sm border-b-transparent -mb-px' 
              : 'text-slate-600 hover:text-slate-900 border-transparent'"
            @click="activeTab = 'pending_invoices'"
          >
            <i class="pi pi-inbox text-xs"></i>
            <span>Hàng Đã Nhập Chờ Lập Hóa Đơn</span>
            <span 
              class="px-1.5 py-0.2 rounded-full text-[10px] font-mono"
              :class="activeTab === 'pending_invoices' ? 'bg-amber-100 text-amber-800 font-bold' : 'bg-slate-200 text-slate-700'"
            >
              {{ pendingInvoicingList.length }}
            </span>
          </button>

          <!-- TAB 2: DANH SÁCH HÓA ĐƠN & ĐỐI CHIẾU 3 BÊN -->
          <button
            type="button"
            class="px-4 py-2 text-xs font-bold rounded-t-[6px] border-t border-l border-r transition-all flex items-center gap-2"
            :class="activeTab === 'invoice_list' 
              ? 'bg-white text-sky-700 border-slate-200 shadow-sm border-b-transparent -mb-px' 
              : 'text-slate-600 hover:text-slate-900 border-transparent'"
            @click="activeTab = 'invoice_list'"
          >
            <i class="pi pi-list text-xs"></i>
            <span>Hóa Đơn Đã Nhập & Đối Chiếu</span>
            <span 
              class="px-1.5 py-0.2 rounded-full text-[10px] font-mono"
              :class="activeTab === 'invoice_list' ? 'bg-sky-100 text-sky-800 font-bold' : 'bg-slate-200 text-slate-700'"
            >
              {{ invoices.length }}
            </span>
          </button>
        </div>

        <div class="flex items-center gap-2 pb-1.5">
          <Button
            label="Làm mới"
            icon="pi pi-refresh"
            size="small"
            text
            class="!rounded-[6px] !text-xs !text-slate-600 hover:!bg-slate-100"
            :loading="loading"
            @click="loadAllData"
          />
        </div>
      </div>

      <!-- 4. NỘI DUNG TAB 1: LÔ HÀNG ĐÃ NHẬP KHO CHỜ LẬP HÓA ĐƠN -->
      <div v-if="activeTab === 'pending_invoices'" class="p-3 flex flex-col gap-3">
        <!-- Toolbar Lọc cho Tab Chờ Lập Hóa Đơn -->
        <div class="flex flex-wrap items-center justify-between gap-3 bg-slate-50/50 p-2 rounded-[6px] border border-slate-100">
          <div class="flex items-center gap-2">
            <IconField>
              <InputIcon class="pi pi-search text-xs" />
              <InputText
                v-model="pendingSearchQuery"
                placeholder="Tìm mã PO, nhà cung cấp, mã phiếu GRN..."
                class="w-80 !rounded-[6px] !text-xs !py-1.5"
                size="small"
              />
            </IconField>
            <Button
              v-if="pendingSearchQuery"
              label="Xóa lọc"
              icon="pi pi-filter-slash"
              size="small"
              text
              severity="secondary"
              class="!rounded-[6px] !text-xs !py-1"
              @click="pendingSearchQuery = ''"
            />
          </div>
          <span class="text-xs text-slate-500">
            Có <strong class="text-slate-800">{{ filteredPendingInvoices.length }}</strong> đơn hàng đã nhập kho chờ lập hóa đơn tài chính
          </span>
        </div>

        <CommonDataTable
          :value="filteredPendingInvoices"
          :columns="pendingColumns"
          :loading="loading"
          :rows="10"
          emptyIcon="pi pi-check-circle"
          emptyTitle="Không có lô hàng nào chờ lập hóa đơn"
          emptySubtitle="Tất cả các lô hàng đã nhập kho đều đã được lập hóa đơn đối chiếu hoặc chưa có hàng nhập kho."
        >
          <!-- Cột Mã PO -->
          <template #col-po_code="{ data }">
            <span
              class="font-mono text-xs font-bold text-sky-700 bg-sky-50 px-2 py-0.5 rounded-[6px] border border-sky-200 cursor-pointer hover:underline inline-block whitespace-nowrap"
              @click="$router.push(`/purchase/orders/${data.po_id}`)"
              title="Nhấp để xem đơn mua hàng PO"
            >
              {{ data.po_code }}
            </span>
          </template>

          <!-- Cột Nhà Cung Cấp -->
          <template #col-supplier_name="{ data }">
            <div class="flex flex-col">
              <span class="text-xs font-semibold text-slate-800">{{ getSupplierName(data.supplier_id) }}</span>
              <span v-if="data.po_notes" class="text-[11px] text-slate-500 truncate max-w-xs">{{ data.po_notes }}</span>
            </div>
          </template>

          <!-- Cột Phiếu Nhập Kho Liên Quan -->
          <template #col-grn_summary="{ data }">
            <div class="flex flex-wrap gap-1 items-center">
              <span 
                v-for="grn in data.grn_list" 
                :key="grn.id"
                class="font-mono text-[11px] font-semibold text-slate-700 bg-slate-100 px-1.5 py-0.5 rounded border border-slate-200"
              >
                {{ grn.code }}
              </span>
              <span v-if="!data.grn_list || data.grn_list.length === 0" class="text-xs text-slate-400 italic">
                Nhập kho toàn bộ
              </span>
            </div>
          </template>

          <!-- Cột Số Lượng Thực Nhận / Chờ Xuất HĐ -->
          <template #col-quantity_summary="{ data }">
            <div class="flex flex-col text-xs">
              <div class="flex items-center gap-1.5">
                <span class="text-slate-500">Đã nhập kho:</span>
                <span class="font-mono font-bold text-slate-800">{{ data.total_received_qty }} sản phẩm</span>
              </div>
              <div class="flex items-center gap-1.5">
                <span class="text-amber-600 font-medium">Chưa lập HĐ:</span>
                <span class="font-mono font-bold text-amber-700">{{ data.uninvoiced_qty }} sản phẩm</span>
              </div>
            </div>
          </template>

          <!-- Cột Giá Trị Tạm Tính Chờ HĐ -->
          <template #col-estimated_amount="{ data }">
            <div class="text-right">
              <span class="font-mono text-xs font-bold text-slate-900 tabular-nums whitespace-nowrap">
                {{ formatCurrency(data.estimated_amount) }}
              </span>
            </div>
          </template>

          <!-- Cột Thao Tác: Lập Hóa Đơn NCC -->
          <Column header="Thao Tác" style="width: 140px" class="text-center">
            <template #body="{ data }">
              <Button
                label="Lập hóa đơn"
                icon="pi pi-receipt"
                size="small"
                class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 !rounded-[6px] !text-xs !font-semibold !px-2.5 !py-1"
                v-tooltip.top="'Lập hóa đơn tài chính và đối chiếu 3 bên'"
                @click="createInvoiceForOrder(data)"
              />
            </template>
          </Column>
        </CommonDataTable>
      </div>

      <!-- 5. NỘI DUNG TAB 2: DANH SÁCH HÓA ĐƠN & ĐỐI CHIẾU -->
      <div v-else class="p-3 flex flex-col gap-3">
        <!-- Toolbar Lọc & Tìm Kiếm Hóa Đơn -->
        <div class="flex flex-wrap items-center justify-between gap-3 bg-slate-50/50 p-2 rounded-[6px] border border-slate-100">
          <div class="flex flex-wrap items-center gap-2">
            <IconField>
              <InputIcon class="pi pi-search text-xs" />
              <InputText
                v-model="searchQuery"
                placeholder="Tìm theo số hóa đơn, mã PINV, mã PO..."
                class="w-80 !rounded-[6px] !text-xs !py-1.5"
                size="small"
              />
            </IconField>

            <Select
              v-model="statusFilter"
              :options="statusOptions"
              optionLabel="label"
              optionValue="value"
              placeholder="Tất cả trạng thái đối chiếu"
              class="w-56 !rounded-[6px] !text-xs"
              size="small"
            />

            <Button
              v-if="searchQuery || statusFilter"
              label="Xóa lọc"
              icon="pi pi-filter-slash"
              size="small"
              text
              severity="secondary"
              class="!rounded-[6px] !text-xs !py-1"
              @click="resetFilters"
            />
          </div>
        </div>

        <CommonDataTable
          :value="filteredInvoices"
          :columns="tableColumns"
          :loading="loading"
          :rows="10"
          emptyIcon="pi pi-receipt"
          emptyTitle="Không tìm thấy hóa đơn mua hàng nào"
          emptySubtitle="Chưa có hóa đơn nào phù hợp với bộ lọc tìm kiếm."
        >
          <!-- Cột Số Hóa Đơn VAT -->
          <template #col-supplier_invoice_number="{ data }">
            <div class="flex flex-col">
              <span
                class="font-mono text-xs font-bold text-sky-700 cursor-pointer hover:underline inline-block whitespace-nowrap"
                @click="$router.push(`/purchase/invoices/${data.id}`)"
                title="Nhấp để xem biên bản đối chiếu 3 bên"
              >
                {{ data.supplier_invoice_number || data.invoice_number || '—' }}
              </span>
              <span class="text-[11px] font-mono text-slate-400">
                {{ data.code }}
              </span>
            </div>
          </template>

          <!-- Cột Số PO -->
          <template #col-po_code="{ data }">
            <span class="font-mono text-xs font-semibold text-slate-800 bg-slate-100 px-2 py-0.5 rounded-[6px] border border-slate-200 inline-block whitespace-nowrap">
              {{ data.po_code || data.po_number || (data.purchase_order_id ? `PO #${data.purchase_order_id}` : '—') }}
            </span>
          </template>

          <!-- Cột Phiếu Nhập Kho (GRN) -->
          <template #col-goods_receipt_code="{ data }">
            <span v-if="data.goods_receipt_code" class="font-mono text-xs text-sky-700 bg-sky-50 px-2 py-0.5 rounded-[6px] border border-sky-200 inline-block whitespace-nowrap">
              {{ data.goods_receipt_code }}
            </span>
            <span v-else class="text-xs text-slate-400 italic">
              Toàn bộ PO
            </span>
          </template>

          <!-- Cột Ngày Hóa Đơn -->
          <template #col-invoice_date="{ data }">
            <span class="text-xs text-slate-600 font-mono whitespace-nowrap">
              {{ formatDate(data.invoice_date) }}
            </span>
          </template>

          <!-- Cột Tổng Tiền Hóa Đơn -->
          <template #col-grand_total="{ data }">
            <div class="text-right">
              <span class="font-mono text-xs font-bold text-slate-900 tabular-nums whitespace-nowrap">
                {{ formatCurrency(data.grand_total ?? data.total_amount) }}
              </span>
            </div>
          </template>

          <!-- Cột Trạng Thái Đối Chiếu 3 Bên -->
          <template #col-matching_status="{ data }">
            <div class="text-center">
              <Tag
                :severity="getMatchingStatusSeverity(data.matching_status)"
                :value="getMatchingStatusLabel(data.matching_status)"
                class="!rounded-[6px] !text-[11px] !font-semibold !px-2 !py-0.5"
              />
            </div>
          </template>

          <!-- Cột Trạng Thái Ghi Sổ -->
          <template #col-status="{ data }">
            <div class="text-center">
              <Tag
                :severity="String(data.status).toUpperCase() === 'POSTED' ? 'success' : 'secondary'"
                :value="String(data.status).toUpperCase() === 'POSTED' ? 'Đã ghi sổ' : 'Dự thảo'"
                class="!rounded-[6px] !text-[11px] !font-semibold !px-2 !py-0.5"
              />
            </div>
          </template>

          <!-- Cột Thao Tác -->
          <Column header="Thao Tác" style="width: 110px" class="text-center">
            <template #body="{ data }">
              <div class="flex items-center justify-center gap-1.5">
                <Button
                  icon="pi pi-search-plus"
                  size="small"
                  outlined
                  severity="secondary"
                  class="!w-7 !h-7 !p-0 !rounded-[6px]"
                  v-tooltip.top="'Xem đối chiếu 3 bên'"
                  @click="$router.push(`/purchase/invoices/${data.id}`)"
                />
                <Button
                  v-if="String(data.matching_status).toUpperCase() === 'MATCHED' && String(data.status).toUpperCase() !== 'POSTED'"
                  icon="pi pi-book"
                  size="small"
                  outlined
                  severity="success"
                  class="!w-7 !h-7 !p-0 !rounded-[6px]"
                  v-tooltip.top="'Ghi sổ kế toán ngay'"
                  @click="postInvoice(data)"
                />
              </div>
            </template>
          </Column>
        </CommonDataTable>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import Column from 'primevue/column';

import PageHeader from '../../../components/common/PageHeader.vue';
import CommonDataTable from '../../../components/common/CommonDataTable.vue';
import { purchaseApi } from '../../../api/purchase.api';
import { MATCHING_STATUS, DEFAULT_SUPPLIERS } from '../../../constants/purchase.constant';
import { formatCurrency } from '../../../utils/format';

const router = useRouter();
const toast = useToast();

const activeTab = ref('pending_invoices');
const invoices = ref([]);
const orders = ref([]);
const receipts = ref([]);
const loading = ref(false);

const pendingSearchQuery = ref('');
const searchQuery = ref('');
const statusFilter = ref(null);

const statusOptions = [
  { label: 'Tất cả trạng thái', value: null },
  { label: 'Chờ đối chiếu', value: 'Unmatched' },
  { label: 'Khớp 100%', value: 'Matched' },
  { label: 'Lệch số liệu', value: 'Discrepancy' }
];

// Cột cho danh sách chờ lập hóa đơn
const pendingColumns = [
  { field: 'po_code', header: 'Mã Đơn PO', width: '160px' },
  { field: 'supplier_name', header: 'Nhà Cung Cấp', width: '220px' },
  { field: 'grn_summary', header: 'Phiếu Nhập Kho (GRN)', width: '200px' },
  { field: 'quantity_summary', header: 'Hàng Đã Nhập Kho', width: '200px' },
  { field: 'estimated_amount', header: 'Tiền Hàng Chờ HĐ', width: '170px', align: 'right' }
];

// Cột cho bảng danh sách hóa đơn
const tableColumns = [
  { field: 'supplier_invoice_number', header: 'Số Hóa Đơn VAT', width: '180px' },
  { field: 'po_code', header: 'Mã Đơn PO', width: '160px' },
  { field: 'goods_receipt_code', header: 'Phiếu Nhập Kho', width: '150px' },
  { field: 'invoice_date', header: 'Ngày Hóa Đơn', width: '130px' },
  { field: 'grand_total', header: 'Tổng Tiền (VNĐ)', width: '160px', align: 'right' },
  { field: 'matching_status', header: 'Đối Chiếu 3 Bên', width: '160px', align: 'center' },
  { field: 'status', header: 'Ghi Sổ', width: '120px', align: 'center' }
];

const loadAllData = async () => {
  loading.value = true;
  try {
    const [invRes, ordRes, recRes] = await Promise.all([
      purchaseApi.getInvoices(),
      purchaseApi.getOrders(),
      purchaseApi.getReceipts()
    ]);

    const iData = invRes?.data || invRes?.items || invRes;
    invoices.value = Array.isArray(iData) ? iData : [];

    const oData = ordRes?.data || ordRes?.items || ordRes;
    orders.value = Array.isArray(oData) ? oData : [];

    const rData = recRes?.data || recRes?.items || recRes;
    receipts.value = Array.isArray(rData) ? rData : [];
  } catch (err) {
    console.error('Lỗi khi tải dữ liệu hóa đơn:', err);
    toast.add({
      severity: 'error',
      summary: 'Lỗi',
      detail: err.message || 'Không thể tải dữ liệu hóa đơn từ máy chủ',
      life: 4000
    });
  } finally {
    loading.value = false;
  }
};

// Danh sách các lô hàng / đơn PO đã nhập kho (GRN) nhưng chưa xuất hết hóa đơn
const pendingInvoicingList = computed(() => {
  const result = [];

  for (const po of orders.value) {
    if (!po.items || po.items.length === 0) continue;

    const totalReceived = po.items.reduce((s, it) => s + Number(it.quantity_received || 0), 0);
    const totalInvoiced = po.items.reduce((s, it) => s + Number(it.quantity_invoiced || 0), 0);
    const unInvoiced = totalReceived - totalInvoiced;

    // Nếu đã có hàng thực nhận vào kho và còn phần chưa xuất hóa đơn
    if (totalReceived > 0 && unInvoiced > 0) {
      // Tìm các phiếu GRN của PO này
      const relatedGrns = receipts.value.filter(r => 
        (r.purchase_order_id && Number(r.purchase_order_id) === Number(po.id)) ||
        (r.po_code && r.po_code === po.code)
      );

      // Tính tổng tiền ước tính cho số lượng chưa lập hóa đơn
      let estimatedAmount = 0;
      for (const it of po.items) {
        const itemUnInvoiced = Math.max(0, Number(it.quantity_received || 0) - Number(it.quantity_invoiced || 0));
        if (itemUnInvoiced > 0) {
          const itemCost = Number(it.unit_cost || 0);
          const taxRate = Number(it.tax_rate ?? 10) / 100;
          estimatedAmount += itemUnInvoiced * itemCost * (1 + taxRate);
        }
      }

      result.push({
        po_id: po.id,
        po_code: po.code || po.po_number || `PO #${po.id}`,
        supplier_id: po.supplier_id,
        po_notes: po.notes,
        total_received_qty: totalReceived,
        uninvoiced_qty: unInvoiced,
        estimated_amount: estimatedAmount,
        grn_list: relatedGrns,
        latest_grn_id: relatedGrns.length > 0 ? relatedGrns[relatedGrns.length - 1].id : null
      });
    }
  }

  return result;
});

const filteredPendingInvoices = computed(() => {
  let list = pendingInvoicingList.value || [];
  if (pendingSearchQuery.value) {
    const q = pendingSearchQuery.value.toLowerCase().trim();
    list = list.filter(item => {
      const code = item.po_code.toLowerCase();
      const sup = getSupplierName(item.supplier_id).toLowerCase();
      const grnMatch = (item.grn_list || []).some(g => (g.code || '').toLowerCase().includes(q));
      return code.includes(q) || sup.includes(q) || grnMatch;
    });
  }
  return list;
});

const stats = computed(() => {
  const list = invoices.value || [];
  return {
    total: list.length,
    pending: list.filter(x => String(x.matching_status).toUpperCase() === 'UNMATCHED' || String(x.matching_status).toUpperCase() === 'PENDING').length,
    matched: list.filter(x => String(x.matching_status).toUpperCase() === 'MATCHED').length,
    discrepancy: list.filter(x => String(x.matching_status).toUpperCase() === 'DISCREPANCY').length
  };
});

const filteredInvoices = computed(() => {
  let list = invoices.value || [];
  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase().trim();
    list = list.filter(
      x =>
        (x.supplier_invoice_number && x.supplier_invoice_number.toLowerCase().includes(q)) ||
        (x.code && x.code.toLowerCase().includes(q)) ||
        (x.po_code && x.po_code.toLowerCase().includes(q)) ||
        (x.goods_receipt_code && x.goods_receipt_code.toLowerCase().includes(q))
    );
  }
  if (statusFilter.value) {
    const filterUpper = statusFilter.value.toUpperCase();
    list = list.filter(x => String(x.matching_status).toUpperCase() === filterUpper);
  }
  return list;
});

const getSupplierName = (supplierId) => {
  const found = DEFAULT_SUPPLIERS.find(s => s.id === Number(supplierId));
  return found ? found.name : `Nhà cung cấp #${supplierId}`;
};

const createInvoiceForOrder = (item) => {
  let url = `/purchase/invoices/create?poId=${item.po_id}`;
  if (item.latest_grn_id) {
    url += `&grnId=${item.latest_grn_id}`;
  }
  router.push(url);
};

const resetFilters = () => {
  searchQuery.value = '';
  statusFilter.value = null;
};

const getMatchingStatusSeverity = (status) => {
  const upper = String(status || '').toUpperCase();
  if (upper === 'UNMATCHED') return 'secondary';
  return MATCHING_STATUS[upper]?.severity || 'secondary';
};

const getMatchingStatusLabel = (status) => {
  const upper = String(status || '').toUpperCase();
  if (upper === 'UNMATCHED') return 'Chưa đối chiếu';
  return MATCHING_STATUS[upper]?.label || status;
};

const formatDate = (dateStr) => {
  if (!dateStr) return '—';
  try {
    const d = new Date(dateStr);
    return d.toLocaleDateString('vi-VN', { year: 'numeric', month: '2-digit', day: '2-digit' });
  } catch {
    return dateStr;
  }
};

const postInvoice = async (item) => {
  try {
    await purchaseApi.postInvoice(item.id);
    item.status = 'Posted';
    toast.add({
      severity: 'success',
      summary: 'Thành công',
      detail: `Đã ghi sổ kế toán hóa đơn ${item.supplier_invoice_number || item.code}`,
      life: 3500
    });
    loadAllData();
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi ghi sổ',
      detail: err.message || 'Không thể ghi sổ hóa đơn',
      life: 4500
    });
  }
};

onMounted(() => {
  loadAllData();
});
</script>
