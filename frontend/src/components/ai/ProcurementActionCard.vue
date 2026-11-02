<template>
  <div class="my-3 rounded-[8px] border border-violet-200 bg-white p-4 shadow-sm text-slate-800 transition-all">
    <!-- Header: Phân loại thẻ & Tên sản phẩm -->
    <div class="flex items-start justify-between border-b border-slate-100 pb-3 gap-2">
      <div>
        <div class="flex items-center gap-2 mb-1">
          <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[11px] font-semibold rounded bg-violet-100 text-violet-700">
            <i class="pi pi-sparkles text-[10px]"></i> AI Procurement Copilot
          </span>
          <span class="px-2 py-0.5 text-[11px] font-bold rounded bg-slate-100 text-slate-700 border border-slate-200">
            SKU: {{ action.sku }}
          </span>
        </div>
        <h4 class="font-bold text-sm text-slate-900 m-0 leading-snug">{{ action.product_name }}</h4>
      </div>
      <button
        v-if="!submitted"
        type="button"
        class="text-slate-400 hover:text-slate-600 p-1 rounded hover:bg-slate-100 text-xs cursor-pointer border-none bg-transparent"
        title="Đóng thẻ"
        @click="$emit('dismiss')"
      >
        <i class="pi pi-times"></i>
      </button>
    </div>

    <!-- Trạng thái sau khi đã tạo đơn thành công -->
    <div v-if="submitted" class="py-4 text-center">
      <div class="w-10 h-10 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto mb-2">
        <i class="pi pi-check text-lg"></i>
      </div>
      <div class="font-bold text-sm text-emerald-800">Đã tạo Đơn Mua Hàng (Draft PO) thành công!</div>
      <p class="text-xs text-slate-600 mt-1 mb-3">
        Mã PO: <strong class="text-slate-900">{{ createdPoCode || ('PO-#' + createdPoId) }}</strong> — Nhà cung cấp: {{ selectedSupplier?.supplier_name }}
      </p>
      <div class="flex justify-center gap-2">
        <router-link
          to="/purchase/orders"
          class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded bg-violet-600 text-white hover:bg-violet-700 no-underline"
        >
          <i class="pi pi-external-link"></i> Xem danh sách Đơn mua
        </router-link>
      </div>
    </div>

    <!-- Form tương tác khi chưa tạo đơn -->
    <div v-else class="space-y-3 pt-3">
      <!-- Machine Learning Recommendation Pill -->
      <div v-if="action.suggested_quantity" class="p-2.5 rounded-[6px] bg-violet-50/70 border border-violet-100 text-xs">
        <div class="flex justify-between items-center text-violet-800 font-semibold mb-1">
          <span class="flex items-center gap-1">
            <i class="pi pi-chart-line text-violet-600"></i> Đề xuất Machine Learning:
          </span>
          <span class="font-bold text-violet-700">Khuyên nhập: {{ action.suggested_quantity }} {{ action.unit || 'Bộ' }}</span>
        </div>
        <p class="text-[11px] text-slate-600 m-0">
          Dựa trên tốc độ bán thực tế và định mức tồn kho an toàn để chống đứt gãy nguồn hàng.
        </p>
      </div>

      <!-- Chọn Nhà Cung Cấp -->
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">
          Chọn Nhà Cung Cấp (Ưu tiên theo lịch sử giá):
        </label>
        <select
          v-model="selectedSupplierId"
          class="w-full text-xs rounded border border-slate-300 bg-white p-2 text-slate-800 focus:outline-none focus:border-violet-500 font-medium"
        >
          <option v-for="sup in action.suppliers" :key="sup.supplier_id" :value="sup.supplier_id">
            [{{ sup.supplier_code }}] {{ sup.supplier_name }} — {{ formatCurrency(sup.latest_price) }}
            (Biên lãi: {{ sup.expected_margin_pct }}%) {{ sup.is_best_price ? ' ★ GIÁ TỐT NHẤT' : '' }}
          </option>
        </select>
      </div>

      <!-- Số lượng đặt & Tổng tiền tạm tính -->
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Số lượng đặt mua:</label>
          <div class="flex items-center border border-slate-300 rounded overflow-hidden">
            <button
              type="button"
              class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs border-none cursor-pointer"
              @click="changeQty(-1)"
            >
              -
            </button>
            <input
              v-model.number="quantity"
              type="number"
              min="1"
              class="w-full text-center text-xs p-1.5 border-none focus:outline-none font-bold text-slate-800"
            />
            <button
              type="button"
              class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs border-none cursor-pointer"
              @click="changeQty(1)"
            >
              +
            </button>
          </div>
        </div>

        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Tổng tiền tạm tính (gồm VAT):</label>
          <div class="p-1.5 text-xs font-bold text-violet-700 bg-violet-50 rounded border border-violet-100 h-[30px] flex items-center justify-between">
            <span>{{ formatCurrency(calculatedTotal) }}</span>
            <span class="text-[10px] text-slate-500 font-normal">VAT 10%</span>
          </div>
        </div>
      </div>

      <!-- Ghi chú nhanh -->
      <div>
        <input
          v-model="notes"
          type="text"
          placeholder="Ghi chú đơn mua (Tùy chọn)..."
          class="w-full text-xs rounded border border-slate-200 bg-slate-50 p-1.5 text-slate-700 focus:outline-none focus:border-violet-500"
        />
      </div>

      <!-- Cảnh báo phân quyền RBAC nếu thiếu quyền -->
      <div v-if="!action.can_execute" class="p-2 rounded bg-amber-50 border border-amber-200 text-amber-800 text-xs flex items-center gap-1.5">
        <i class="pi pi-lock text-amber-600"></i>
        <span>Tài khoản của bạn chưa được cấp quyền <strong>purchase.create</strong> để tạo Đơn mua hàng trực tiếp.</span>
      </div>

      <!-- Các nút thao tác -->
      <div class="pt-2 flex items-center justify-end gap-2 border-t border-slate-100">
        <button
          type="button"
          class="px-3 py-1.5 text-xs text-slate-600 hover:bg-slate-100 rounded border border-slate-200 cursor-pointer bg-white"
          @click="$emit('dismiss')"
        >
          Bỏ qua
        </button>
        <button
          type="button"
          :disabled="isSubmitting || !action.can_execute"
          class="px-4 py-1.5 text-xs font-bold text-white bg-violet-600 hover:bg-violet-700 disabled:opacity-50 disabled:cursor-not-allowed rounded shadow-sm border-none cursor-pointer flex items-center gap-1.5"
          @click="submitCreatePO"
        >
          <i v-if="isSubmitting" class="pi pi-spin pi-spinner text-xs"></i>
          <i v-else class="pi pi-shopping-cart text-xs"></i>
          <span>{{ isSubmitting ? 'Đang khởi tạo PO...' : '🚀 Xác nhận Tạo Đơn Mua (Draft PO)' }}</span>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useToast } from 'primevue/usetoast';
import { purchaseApi } from '../../api/purchase.api';

const props = defineProps({
  action: { type: Object, required: true }
});

const emit = defineEmits(['order-created', 'dismiss']);
const toast = useToast();

const selectedSupplierId = ref(props.action.default_supplier_id || (props.action.suppliers?.[0]?.supplier_id));
const quantity = ref(props.action.default_quantity || 10);
const notes = ref('Đơn mua tự động khởi tạo từ Trợ lý AI Procurement Copilot');
const isSubmitting = ref(false);
const submitted = ref(false);
const createdPoId = ref(null);
const createdPoCode = ref('');

const selectedSupplier = computed(() => {
  return props.action.suppliers?.find((s) => s.supplier_id === selectedSupplierId.value) || props.action.suppliers?.[0];
});

const calculatedTotal = computed(() => {
  if (!selectedSupplier.value) return 0;
  const unitPrice = selectedSupplier.value.latest_price || 0;
  const subtotal = unitPrice * (quantity.value || 0);
  return Math.round(subtotal * 1.10); // gồm 10% VAT
});

function formatCurrency(val) {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val || 0);
}

function changeQty(delta) {
  const current = Number(quantity.value) || 1;
  const next = current + delta;
  if (next >= 1) quantity.value = next;
}

async function submitCreatePO() {
  if (!selectedSupplier.value) {
    toast?.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Vui lòng chọn Nhà cung cấp.', life: 3000 });
    return;
  }
  if (!quantity.value || quantity.value < 1) {
    toast?.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Số lượng mua phải lớn hơn 0.', life: 3000 });
    return;
  }

  isSubmitting.value = true;
  try {
    const payload = {
      supplier_id: Number(selectedSupplierId.value),
      warehouse_id: 1, // Mặc định Kho Tổng Hà Nội
      payment_terms: selectedSupplier.value.payment_terms || 'Thỏa thuận',
      notes: notes.value,
      items: [
        {
          product_id: Number(props.action.product_id),
          sku: props.action.sku,
          product_name: props.action.product_name,
          unit: props.action.unit || 'Bộ',
          unit_cost: Number(selectedSupplier.value.latest_price),
          quantity_ordered: Number(quantity.value),
          tax_rate: 10.0
        }
      ]
    };

    const res = await purchaseApi.createOrder(payload);
    const data = res?.data || res;
    createdPoId.value = data?.id;
    createdPoCode.value = data?.po_code || data?.po_number || '';
    submitted.value = true;

    toast?.add({
      severity: 'success',
      summary: 'Thành công',
      detail: `Đã tạo đơn mua hàng ${createdPoCode.value || ('ID ' + createdPoId.value)} thành công!`,
      life: 5000
    });
    emit('order-created', data);
  } catch (err) {
    console.error('Lỗi khi tạo PO từ Action Card:', err);
    toast?.add({
      severity: 'error',
      summary: 'Lỗi tạo đơn mua',
      detail: err.message || 'Không thể tạo đơn mua hàng, vui lòng kiểm tra lại quyền hạn hoặc dữ liệu.',
      life: 5000
    });
  } finally {
    isSubmitting.value = false;
  }
}
</script>

