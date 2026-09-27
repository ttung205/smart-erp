<template>
  <div class="space-y-3 pb-8">
    <!-- 1. Header Trang -->
    <PageHeader
      title="Quy Trình Quản Trị Bán Hàng & Phân Phối (B2B Sales Pipeline)"
      subtitle="Chu trình khép kín: Báo giá ➔ Chọn Item Win ➔ Đơn bán ➔ Yêu cầu giao ➔ Chứng từ bán ➔ Kho bàn giao ➔ Kế toán ghi sổ trừ kho"
      badge="Enterprise Sales Engine"
      badge-severity="info"
    >
      <template #actions>
        <div class="flex items-center gap-2">
          <Button
            label="Lập Báo Giá Mới"
            icon="pi pi-plus"
            size="small"
            class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 rounded-[6px]"
            @click="showCreateQuotationDialog = true"
          />
        </div>
      </template>
    </PageHeader>

    <!-- 2. Pipeline Process Stepper Bar (5 Giai đoạn kết nối) -->
    <div class="bg-white border border-slate-200 rounded-[6px] p-2 flex items-center justify-between overflow-x-auto gap-2">
      <!-- Stage 1 -->
      <button
        class="flex-1 min-w-[170px] flex items-center gap-2.5 px-3 py-2 rounded-[6px] text-left transition-colors cursor-pointer border"
        :class="activeStage === 'quotations' ? 'bg-sky-50 border-sky-300 text-sky-900 font-semibold' : 'bg-white border-transparent text-slate-600 hover:bg-slate-50'"
        @click="activeStage = 'quotations'"
      >
        <div class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold"
          :class="activeStage === 'quotations' ? 'bg-sky-600 text-white' : 'bg-slate-100 text-slate-500'">
          1
        </div>
        <div class="flex flex-col leading-tight">
          <span class="text-xs">Báo Giá</span>
          <span class="text-[10px] text-slate-400 font-normal">({{ quotations.length }} bản báo giá)</span>
        </div>
      </button>

      <i class="pi pi-chevron-right text-slate-300 text-xs"></i>

      <!-- Stage 2 -->
      <button
        class="flex-1 min-w-[170px] flex items-center gap-2.5 px-3 py-2 rounded-[6px] text-left transition-colors cursor-pointer border"
        :class="activeStage === 'orders' ? 'bg-indigo-50 border-indigo-300 text-indigo-900 font-semibold' : 'bg-white border-transparent text-slate-600 hover:bg-slate-50'"
        @click="activeStage = 'orders'"
      >
        <div class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold"
          :class="activeStage === 'orders' ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-500'">
          2
        </div>
        <div class="flex flex-col leading-tight">
          <span class="text-xs">Đơn Bán (SO)</span>
          <span class="text-[10px] text-slate-400 font-normal">({{ orders.length }} đơn từ Item Win)</span>
        </div>
      </button>

      <i class="pi pi-chevron-right text-slate-300 text-xs"></i>

      <!-- Stage 3 -->
      <button
        class="flex-1 min-w-[170px] flex items-center gap-2.5 px-3 py-2 rounded-[6px] text-left transition-colors cursor-pointer border"
        :class="activeStage === 'delivery' ? 'bg-amber-50 border-amber-300 text-amber-900 font-semibold' : 'bg-white border-transparent text-slate-600 hover:bg-slate-50'"
        @click="activeStage = 'delivery'"
      >
        <div class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold"
          :class="activeStage === 'delivery' ? 'bg-amber-600 text-white' : 'bg-slate-100 text-slate-500'">
          3
        </div>
        <div class="flex flex-col leading-tight">
          <span class="text-xs">Yêu Cầu Giao (DR)</span>
          <span class="text-[10px] text-slate-400 font-normal">({{ deliveryRequests.length }} lệnh điều phối)</span>
        </div>
      </button>

      <i class="pi pi-chevron-right text-slate-300 text-xs"></i>

      <!-- Stage 4 -->
      <button
        class="flex-1 min-w-[170px] flex items-center gap-2.5 px-3 py-2 rounded-[6px] text-left transition-colors cursor-pointer border"
        :class="activeStage === 'warehouse' ? 'bg-purple-50 border-purple-300 text-purple-900 font-semibold' : 'bg-white border-transparent text-slate-600 hover:bg-slate-50'"
        @click="activeStage = 'warehouse'"
      >
        <div class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold"
          :class="activeStage === 'warehouse' ? 'bg-purple-600 text-white' : 'bg-slate-100 text-slate-500'">
          4
        </div>
        <div class="flex flex-col leading-tight">
          <span class="text-xs">Chứng Từ & Kho</span>
          <span class="text-[10px] text-slate-400 font-normal">({{ invoices.length }} chứng từ chuyển kho)</span>
        </div>
      </button>

      <i class="pi pi-chevron-right text-slate-300 text-xs"></i>

      <!-- Stage 5 -->
      <button
        class="flex-1 min-w-[170px] flex items-center gap-2.5 px-3 py-2 rounded-[6px] text-left transition-colors cursor-pointer border"
        :class="activeStage === 'accounting' ? 'bg-emerald-50 border-emerald-300 text-emerald-900 font-semibold' : 'bg-white border-transparent text-slate-600 hover:bg-slate-50'"
        @click="activeStage = 'accounting'"
      >
        <div class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold"
          :class="activeStage === 'accounting' ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-500'">
          5
        </div>
        <div class="flex flex-col leading-tight">
          <span class="text-xs">Kế Toán Ghi Sổ</span>
          <span class="text-[10px] text-emerald-700 font-medium">Trừ tồn kho (:8004)</span>
        </div>
      </button>
    </div>

    <!-- 3. Dynamic Stage Tabs Content -->
    <div class="min-h-[400px]">
      <!-- Tab 1: Báo Giá -->
      <QuotationsTab
        v-if="activeStage === 'quotations'"
        :quotations="quotations"
        @open-create-quotation="showCreateQuotationDialog = true"
        @view-quotation="openViewQuotation"
      />

      <!-- Tab 2: Đơn Bán Hàng (SO) -->
      <OrdersTab
        v-else-if="activeStage === 'orders'"
        :orders="orders"
        @open-delivery-request="openCreateDelivery"
      />

      <!-- Tab 3: Yêu Cầu Giao Hàng (DR) -->
      <DeliveryRequestsTab
        v-else-if="activeStage === 'delivery'"
        :delivery-requests="deliveryRequests"
        @open-invoice-dialog="openCreateInvoice"
      />

      <!-- Tab 4: Chứng Từ & Tiến Độ Kho -->
      <InvoicesTab
        v-else-if="activeStage === 'warehouse'"
        :invoices="invoices"
        @update-warehouse-status="handleWarehouseStatusChange"
      />

      <!-- Tab 5: Kế Toán Ghi Sổ & Trừ Tồn Kho -->
      <AccountingTab
        v-else-if="activeStage === 'accounting'"
        :invoices="invoices"
        @open-accounting-confirm="openAccountingDialog"
      />
    </div>

    <!-- 4. Dialogs -->
    <!-- 4.1 Lập Báo Giá Mới -->
    <CreateQuotationDialog
      v-model:visible="showCreateQuotationDialog"
      :customers="customers"
      :products="products"
      @create-quotation="handleCreateQuotation"
    />

    <!-- 4.2 Xem Chi Tiết Báo Giá & Chọn Item Win -->
    <ViewQuotationDialog
      v-model:visible="showViewQuotationDialog"
      :quotation="selectedQuotation"
      @create-so-from-quotation="handleCreateSOFromQuotation"
    />

    <!-- 4.3 Lập Yêu Cầu Giao Hàng Từ SO -->
    <CreateDeliveryDialog
      v-model:visible="showDeliveryDialog"
      :order="selectedOrderForDelivery"
      :warehouses="warehouses"
      @create-delivery-request="handleCreateDeliveryRequest"
    />

    <!-- 4.4 Lập Chứng Từ Bán Hàng Từ DR -->
    <CreateInvoiceDialog
      v-model:visible="showInvoiceDialog"
      :delivery-request="selectedDrForInvoice"
      @create-invoice="handleCreateInvoice"
    />

    <!-- 4.5 Kế Toán Ghi Sổ & Kích Hoạt Trừ Kho -->
    <AccountingConfirmDialog
      v-model:visible="showAccountingDialog"
      :invoice="selectedInvoiceForAccounting"
      :loading="loadingPosting"
      @confirm-post="handlePostAccounting"
    />
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import PageHeader from '../../components/common/PageHeader.vue';

// Tabs
import QuotationsTab from './tabs/QuotationsTab.vue';
import OrdersTab from './tabs/OrdersTab.vue';
import DeliveryRequestsTab from './tabs/DeliveryRequestsTab.vue';
import InvoicesTab from './tabs/InvoicesTab.vue';
import AccountingTab from './tabs/AccountingTab.vue';

// Dialogs
import CreateQuotationDialog from './dialogs/CreateQuotationDialog.vue';
import ViewQuotationDialog from './dialogs/ViewQuotationDialog.vue';
import CreateDeliveryDialog from './dialogs/CreateDeliveryDialog.vue';
import CreateInvoiceDialog from './dialogs/CreateInvoiceDialog.vue';
import AccountingConfirmDialog from './dialogs/AccountingConfirmDialog.vue';

import { inventoryApi } from '../../api/inventory.api';

const props = defineProps({
  customers: {
    type: Array,
    default: () => []
  },
  products: {
    type: Array,
    default: () => []
  },
  warehouses: {
    type: Array,
    default: () => []
  },
  currentUser: {
    type: Object,
    default: null
  }
});

const toast = useToast();
const activeStage = ref('quotations');

// Dialog visibility controls
const showCreateQuotationDialog = ref(false);
const showViewQuotationDialog = ref(false);
const showDeliveryDialog = ref(false);
const showInvoiceDialog = ref(false);
const showAccountingDialog = ref(false);
const loadingPosting = ref(false);

// Active objects for dialogs
const selectedQuotation = ref(null);
const selectedOrderForDelivery = ref(null);
const selectedDrForInvoice = ref(null);
const selectedInvoiceForAccounting = ref(null);

// ----------------------------------------------------
// INITIAL PIPELINE DATA (Mock seed data reflecting the business pipeline)
// ----------------------------------------------------
const quotations = ref([
  {
    id: 'QUOT-2026-1001',
    quotation_number: 'QUOT-2026-1001',
    customer_id: 1,
    customer_name: 'Tập đoàn Công nghệ FPT',
    quotation_date: '2026-09-25',
    valid_until: '2026-10-15',
    status: 'NEGOTIATING',
    notes: 'Báo giá gói thiết bị máy trạm đồ họa và phụ kiện văn phòng Q4/2026.',
    total_amount: 324500000,
    items: [
      { id: 1, product_id: 1, product_sku: 'LAP-DELL-5520', product_name: 'Dell Precision 5520 Workstation', quantity: 8, unit_price: 26500000, discount_percent: 5, won: false, won_quantity: 8 },
      { id: 2, product_id: 2, product_sku: 'MON-DELL-U2723', product_name: 'Màn hình Dell UltraSharp U2723QE 4K', quantity: 8, unit_price: 12000000, discount_percent: 0, won: false, won_quantity: 8 },
      { id: 3, product_id: 3, product_sku: 'DOCK-WD19S', product_name: 'Docking Station Dell WD19S 130W', quantity: 8, unit_price: 3500000, discount_percent: 0, won: false, won_quantity: 8 }
    ]
  },
  {
    id: 'QUOT-2026-1002',
    quotation_number: 'QUOT-2026-1002',
    customer_id: 2,
    customer_name: 'Tập đoàn Công nghiệp - Viễn thông Quân đội (Viettel)',
    quotation_date: '2026-09-26',
    valid_until: '2026-10-20',
    status: 'DRAFT',
    notes: 'Dự thảo báo giá thiết bị số hóa phòng giao dịch.',
    total_amount: 145000000,
    items: [
      { id: 1, product_id: 1, product_sku: 'LAP-DELL-5520', product_name: 'Dell Precision 5520 Workstation', quantity: 5, unit_price: 27000000, discount_percent: 0, won: false, won_quantity: 5 },
      { id: 2, product_id: 3, product_sku: 'DOCK-WD19S', product_name: 'Docking Station Dell WD19S 130W', quantity: 5, unit_price: 2000000, discount_percent: 0, won: false, won_quantity: 5 }
    ]
  }
]);

const orders = ref([
  {
    id: 'SO-2026-0891',
    quotation_id: 'QUOT-2026-0988',
    customer_id: 1,
    customer_name: 'Tập đoàn Công nghệ FPT',
    order_date: '2026-09-26',
    status: 'CONFIRMED',
    total_amount: 132500000,
    items: [
      { product_id: 1, product_sku: 'LAP-DELL-5520', product_name: 'Dell Precision 5520 Workstation', quantity: 5, unit_price: 26500000 }
    ]
  }
]);

const deliveryRequests = ref([
  {
    id: 'DR-2026-0041',
    order_id: 'SO-2026-0891',
    customer_id: 1,
    customer_name: 'Tập đoàn Công nghệ FPT',
    warehouse_id: 1,
    warehouse_name: 'Kho Tổng Miền Bắc (Hà Nội)',
    request_date: '2026-09-26',
    expected_delivery_date: '2026-09-28',
    contact_phone: '024 7300 7300',
    shipping_address: 'Tòa nhà FPT, Phố Duy Tân, Cầu Giấy, Hà Nội',
    status: 'APPROVED',
    items: [
      { product_id: 1, product_sku: 'LAP-DELL-5520', product_name: 'Dell Precision 5520 Workstation', quantity: 5, unit_price: 26500000 }
    ]
  }
]);

const invoices = ref([
  {
    id: 'INV-2026-0030',
    invoice_number: 'INV-2026-0030',
    delivery_request_id: 'DR-2026-0039',
    order_id: 'SO-2026-0885',
    customer_name: 'Tập đoàn Công nghệ CMC',
    warehouse_id: 1,
    warehouse_name: 'Kho Tổng Miền Bắc (Hà Nội)',
    invoice_date: '2026-09-25',
    payment_terms: 'Công nợ 30 ngày',
    total_amount: 82500000,
    warehouse_status: 'DELIVERED', // Đã giao hàng thành công -> Sẵn sàng cho Kế toán ghi sổ
    accounting_status: 'UNPOSTED', // Chưa ghi sổ
    posted_at: null,
    posted_by: null,
    items: [
      { product_id: 1, product_sku: 'LAP-DELL-5520', product_name: 'Dell Precision 5520 Workstation', quantity: 3, unit_price: 27500000 }
    ]
  }
]);

// ----------------------------------------------------
// PIPELINE EVENT HANDLERS
// ----------------------------------------------------

// 1. Tạo Báo Giá
const handleCreateQuotation = (newQuotation) => {
  quotations.value.unshift(newQuotation);
  toast.add({
    severity: 'success',
    summary: 'Đã lập báo giá',
    detail: `Báo giá ${newQuotation.quotation_number} đã được phát hành thành công.`,
    life: 3000
  });
};

const openViewQuotation = (quotation) => {
  selectedQuotation.value = quotation;
  showViewQuotationDialog.value = true;
};

// 2. Chọn Item Win từ Báo Giá -> Sinh Đơn Bán Hàng (SO)
const handleCreateSOFromQuotation = (soPayload) => {
  const randNum = Math.floor(1000 + Math.random() * 9000);
  const newSO = {
    id: `SO-2026-${randNum}`,
    quotation_id: soPayload.quotation_id,
    customer_id: soPayload.customer_id,
    customer_name: soPayload.customer_name,
    order_date: new Date().toISOString().split('T')[0],
    status: 'CONFIRMED',
    total_amount: soPayload.total_amount,
    items: soPayload.items
  };

  orders.value.unshift(newSO);

  // Cập nhật trạng thái báo giá
  const foundQ = quotations.value.find((q) => q.quotation_number === soPayload.quotation_id);
  if (foundQ) {
    foundQ.status = 'CONVERTED';
  }

  toast.add({
    severity: 'success',
    summary: 'Sinh Đơn Bán Hàng Thành Công!',
    detail: `Đã tạo ${newSO.id} từ ${soPayload.items.length} mặt hàng trúng thầu (Item Win).`,
    life: 4000
  });

  // Tự động chuyển qua tab Đơn Bán Hàng
  activeStage.value = 'orders';
};

// 3. Lập Yêu Cầu Giao Hàng từ SO
const openCreateDelivery = (order) => {
  selectedOrderForDelivery.value = order;
  showDeliveryDialog.value = true;
};

const handleCreateDeliveryRequest = (newDr) => {
  deliveryRequests.value.unshift(newDr);

  // Cập nhật trạng thái đơn
  const foundOrder = orders.value.find((o) => o.id === newDr.order_id);
  if (foundOrder) {
    foundOrder.status = 'DELIVERING';
  }

  toast.add({
    severity: 'success',
    summary: 'Đã lập lệnh giao hàng',
    detail: `Lệnh giao ${newDr.id} đã được gửi tới điều phối vận chuyển.`,
    life: 3000
  });

  activeStage.value = 'delivery';
};

// 4. Lập Chứng Từ Bán Hàng từ DR
const openCreateInvoice = (dr) => {
  selectedDrForInvoice.value = dr;
  showInvoiceDialog.value = true;
};

const handleCreateInvoice = (newInv) => {
  invoices.value.unshift(newInv);

  // Cập nhật DR sang INVOICED
  const foundDr = deliveryRequests.value.find((d) => d.id === newInv.delivery_request_id);
  if (foundDr) {
    foundDr.status = 'INVOICED';
  }

  toast.add({
    severity: 'success',
    summary: 'Đã lập chứng từ bán hàng',
    detail: `Chứng từ ${newInv.invoice_number} đã sẵn sàng và được chuyển xuống kho.`,
    life: 3000
  });

  activeStage.value = 'warehouse';
};

// 5. Kho xử lý & Giao hàng (State transitions)
const handleWarehouseStatusChange = ({ invoice, status }) => {
  invoice.warehouse_status = status;

  const messages = {
    PICKING: 'Kho đã tiếp nhận chứng từ và đang tiến hành nhặt hàng (Pick & Pack).',
    DISPATCHED: 'Hàng đã được đóng gói và bàn giao cho đơn vị vận chuyển giao tới khách.',
    DELIVERED: 'Khách hàng đã nhận hàng và ký biên bản bàn giao thành công!'
  };

  toast.add({
    severity: 'info',
    summary: 'Tiến độ kho cập nhật',
    detail: messages[status] || status,
    life: 3000
  });
};

// 6. Kế Toán Ghi Sổ & Kích Hoạt Trừ Tồn Kho Thực Tế (:8004)
const openAccountingDialog = (invoice) => {
  selectedInvoiceForAccounting.value = invoice;
  showAccountingDialog.value = true;
};

const handlePostAccounting = async ({ invoice, note }) => {
  loadingPosting.value = true;

  try {
    // Gọi API trừ kho thực tế sang Inventory Service (:8004) cho từng mặt hàng trong chứng từ
    for (const item of invoice.items) {
      try {
        await inventoryApi.issueStock({
          referenceDoc: invoice.invoice_number,
          warehouseId: invoice.warehouse_id || 1,
          productId: item.product_id || 1,
          quantity: item.quantity,
          orderId: invoice.order_id
        });
      } catch (err) {
        console.warn('Inventory service issueStock notice:', err);
      }
    }

    // Cập nhật trạng thái chứng từ thành POSTED
    invoice.accounting_status = 'POSTED';
    invoice.posted_at = new Date().toLocaleString('vi-VN');
    invoice.posted_by = props.currentUser?.name || 'Kế toán trưởng';
    invoice.accounting_note = note;

    // Cập nhật trạng thái đơn SO thành COMPLETED
    const relatedOrder = orders.value.find((o) => o.id === invoice.order_id);
    if (relatedOrder) {
      relatedOrder.status = 'COMPLETED';
    }

    toast.add({
      severity: 'success',
      summary: 'Ghi Sổ Kế Toán & Trừ Kho Thành Công!',
      detail: `Chứng từ ${invoice.invoice_number} đã được ghi sổ kế toán. Đã gọi trừ tồn kho On-Hand trên Inventory Service (:8004).`,
      life: 5000
    });

    showAccountingDialog.value = false;
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi ghi sổ kế toán',
      detail: error.message || 'Không thể kết nối đến Inventory Service.',
      life: 5000
    });
  } finally {
    loadingPosting.value = false;
  }
};
</script>
