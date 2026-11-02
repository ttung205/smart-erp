<template>
  <div class="my-3 rounded-[8px] border border-blue-200 bg-white p-4 shadow-sm text-slate-800 transition-all">
    <!-- Header: Phân loại thẻ & Khách hàng & Sản phẩm -->
    <div class="flex items-start justify-between border-b border-slate-100 pb-3 gap-2">
      <div>
        <div class="flex items-center gap-2 mb-1 flex-wrap">
          <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[11px] font-semibold rounded bg-blue-100 text-blue-700">
            <i class="pi pi-sparkles text-[10px]"></i> AI Sales Copilot
          </span>
          <span class="px-2 py-0.5 text-[11px] font-bold rounded bg-slate-100 text-slate-700 border border-slate-200">
            Mã KH: {{ action.customer_code }} ({{ action.customer_type }})
          </span>
          <span class="px-2 py-0.5 text-[11px] font-bold rounded bg-slate-100 text-slate-700 border border-slate-200">
            SKU: {{ action.sku }}
          </span>
        </div>
        <h4 class="font-bold text-sm text-slate-900 m-0 leading-snug">
          {{ action.customer_name }}
        </h4>
        <div class="text-xs text-slate-500 mt-0.5">Sản phẩm: {{ action.product_name }}</div>
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

    <!-- Trạng thái sau khi đã tạo đơn / báo giá thành công -->
    <div v-if="submitted" class="py-4 text-center">
      <div class="w-10 h-10 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto mb-2">
        <i class="pi pi-check text-lg"></i>
      </div>
      <div class="font-bold text-sm text-emerald-800">
        {{ createdType === 'ORDER' ? 'Đã tạo Đơn Bán Hàng (Draft SO) thành công!' : 'Đã tạo Báo Giá (Draft Quotation) thành công!' }}
      </div>
      <p class="text-xs text-slate-600 mt-1 mb-3">
        Mã chứng từ: <strong class="text-slate-900">{{ createdDocNumber || ('#' + createdDocId) }}</strong> — Khách hàng: {{ action.customer_name }}
      </p>
      <div class="flex justify-center gap-2">
        <router-link
          v-if="createdType === 'ORDER'"
          to="/sales/orders"
          class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded bg-blue-600 text-white hover:bg-blue-700 no-underline"
        >
          <i class="pi pi-external-link"></i> Xem danh sách Đơn bán hàng
        </router-link>
        <router-link
          v-else
          to="/sales/quotations"
          class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded bg-slate-700 text-white hover:bg-slate-800 no-underline"
        >
          <i class="pi pi-external-link"></i> Xem danh sách Báo giá
        </router-link>
      </div>
    </div>

    <!-- Form tương tác khi chưa tạo đơn -->
    <div v-else class="space-y-3 pt-3">
      <!-- Cảnh báo trạng thái Tín dụng, Tồn kho & Biên lãi -->
      <div class="flex flex-wrap gap-2 text-[11px]">
        <!-- Tồn kho -->
        <span
          class="inline-flex items-center gap-1 px-2 py-1 rounded font-medium"
          :class="action.is_stock_sufficient ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200'"
        >
          <i :class="action.is_stock_sufficient ? 'pi pi-check-circle' : 'pi pi-exclamation-triangle'"></i>
          {{ action.is_stock_sufficient ? 'Đủ tồn kho khả dụng' : 'Cảnh báo thiếu hụt tồn kho' }}
        </span>

        <!-- Tín dụng -->
        <span
          class="inline-flex items-center gap-1 px-2 py-1 rounded font-medium"
          :class="action.is_credit_ok ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200'"
        >
          <i :class="action.is_credit_ok ? 'pi pi-credit-card' : 'pi pi-exclamation-circle'"></i>
          {{ action.is_credit_ok ? 'Hạn mức tín dụng an toàn' : 'Có nợ quá hạn hoặc vượt hạn mức' }}
        </span>

        <!-- Biên lợi nhuận -->
        <span
          class="inline-flex items-center gap-1 px-2 py-1 rounded font-medium"
          :class="marginBadgeClass"
        >
          <i class="pi pi-percentage"></i>
          Biên lãi gộp: {{ action.gross_margin_pct }}%
        </span>
      </div>

      <!-- Điều chỉnh Số lượng & Chiết khấu tương tác -->
      <div class="grid grid-cols-2 gap-3 bg-slate-50 p-2.5 rounded-[6px] border border-slate-200">
        <div>
          <label class="block text-[11px] font-medium text-slate-600 mb-1">Số lượng đặt bán:</label>
          <div class="flex items-center border border-slate-300 rounded bg-white overflow-hidden">
            <button
              type="button"
              class="px-2 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs border-none cursor-pointer"
              @click="changeQty(-1)"
            >
              -
            </button>
            <input
              v-model.number="quantity"
              type="number"
              min="1"
              class="w-full text-center text-xs p-1 border-none focus:outline-none font-bold text-slate-800"
            />
            <button
              type="button"
              class="px-2 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs border-none cursor-pointer"
              @click="changeQty(1)"
            >
              +
            </button>
          </div>
        </div>

        <div>
          <label class="block text-[11px] font-medium text-slate-600 mb-1">Tỷ lệ chiết khấu (%):</label>
          <div class="flex items-center border border-slate-300 rounded bg-white overflow-hidden">
            <input
              v-model.number="discountPercent"
              type="number"
              min="0"
              max="50"
              step="0.5"
              class="w-full text-center text-xs p-1 border-none focus:outline-none font-bold text-blue-700"
            />
            <span class="px-2 text-xs text-slate-400 bg-slate-50 border-l border-slate-200">%</span>
          </div>
        </div>
      </div>

      <!-- Bảng giá chốt tự động tính toán -->
      <div class="p-2.5 rounded-[6px] bg-blue-50/60 border border-blue-100 text-xs space-y-1">
        <div class="flex justify-between text-slate-600">
          <span>Giá niêm yết:</span>
          <span>{{ formatCurrency(action.list_price) }}</span>
        </div>
        <div class="flex justify-between text-slate-600">
          <span>Đơn giá chốt (sau CK):</span>
          <span class="font-semibold text-blue-700">{{ formatCurrency(discountedUnitPrice) }}</span>
        </div>
        <div class="flex justify-between text-slate-600">
          <span>Tạm tính (chưa VAT):</span>
          <span>{{ formatCurrency(subtotal) }}</span>
        </div>
        <div class="flex justify-between text-slate-600">
          <span>Thuế VAT (8%):</span>
          <span>{{ formatCurrency(vatAmount) }}</span>
        </div>
        <div class="flex justify-between font-bold text-sm text-slate-900 pt-1 border-t border-blue-200">
          <span>Tổng thanh toán:</span>
          <span class="text-blue-700">{{ formatCurrency(totalAmount) }}</span>
        </div>
      </div>

      <!-- Cảnh báo phân quyền RBAC nếu thiếu quyền -->
      <div v-if="!action.can_execute" class="p-2 rounded bg-amber-50 border border-amber-200 text-amber-800 text-xs flex items-center gap-1.5">
        <i class="pi pi-lock text-amber-600"></i>
        <span>Tài khoản của bạn chỉ có quyền xem báo giá (Cần quyền <strong>order.create</strong> để tạo Đơn bán SO).</span>
      </div>

      <!-- Các nút thao tác -->
      <div class="pt-2 flex items-center justify-end gap-2 border-t border-slate-100 flex-wrap">
        <button
          type="button"
          class="px-3 py-1.5 text-xs text-slate-600 hover:bg-slate-100 rounded border border-slate-200 cursor-pointer bg-white"
          @click="$emit('dismiss')"
        >
          Bỏ qua
        </button>

        <button
          type="button"
          :disabled="isSubmitting"
          class="px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100 rounded border border-slate-300 cursor-pointer bg-white flex items-center gap-1"
          @click="submitCreate('QUOTATION')"
        >
          <i class="pi pi-file-edit text-xs"></i>
          <span>Lập Báo giá (Quotation)</span>
        </button>

        <button
          type="button"
          :disabled="isSubmitting || !action.can_execute"
          class="px-4 py-1.5 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed rounded shadow-sm border-none cursor-pointer flex items-center gap-1.5"
          @click="submitCreate('ORDER')"
        >
          <i v-if="isSubmitting" class="pi pi-spin pi-spinner text-xs"></i>
          <i v-else class="pi pi-shopping-bag text-xs"></i>
          <span>{{ isSubmitting ? 'Đang tạo đơn...' : '🚀 Tạo Đơn Bán Hàng (Draft SO)' }}</span>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useToast } from 'primevue/usetoast';
import { salesApi } from '../../api/sales.api';

const props = defineProps({
  action: { type: Object, required: true }
});

const emit = defineEmits(['order-created', 'dismiss']);
const toast = useToast();

const quantity = ref(props.action.quantity || 1);
const discountPercent = ref(props.action.discount_percent || 0);
const isSubmitting = ref(false);
const submitted = ref(false);
const createdType = ref(''); // 'ORDER' hoặc 'QUOTATION'
const createdDocId = ref(null);
const createdDocNumber = ref('');

const discountedUnitPrice = computed(() => {
  const listPrice = props.action.list_price || 0;
  const pct = Number(discountPercent.value) || 0;
  return Math.round(listPrice * (1.0 - pct / 100.0));
});

const subtotal = computed(() => {
  return discountedUnitPrice.value * (Number(quantity.value) || 0);
});

const vatAmount = computed(() => {
  return Math.round(subtotal.value * 0.08); // 8% VAT
});

const totalAmount = computed(() => {
  return subtotal.value + vatAmount.value;
});

const marginBadgeClass = computed(() => {
  if (props.action.margin_status === 'LO_VON') {
    return 'bg-rose-100 text-rose-800 border border-rose-300 font-bold';
  }
  if (props.action.margin_status === 'CANH_BAO_BIEN_THAP') {
    return 'bg-amber-100 text-amber-800 border border-amber-300 font-bold';
  }
  return 'bg-blue-50 text-blue-700 border border-blue-200';
});

function formatCurrency(val) {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val || 0);
}

function changeQty(delta) {
  const current = Number(quantity.value) || 1;
  const next = current + delta;
  if (next >= 1) quantity.value = next;
}

async function submitCreate(type) {
  if (!quantity.value || quantity.value < 1) {
    toast?.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Số lượng đặt bán phải lớn hơn 0.', life: 3000 });
    return;
  }

  isSubmitting.value = true;
  createdType.value = type;

  try {
    if (type === 'ORDER') {
      const payload = {
        customer_id: Number(props.action.customer_id),
        items: [
          {
            product_id: Number(props.action.product_id),
            sku: props.action.sku,
            quantity: Number(quantity.value),
            selling_price: discountedUnitPrice.value,
            vat_percent: 8.0
          }
        ]
      };
      const res = await salesApi.createOrder(payload);
      const data = res?.data || res;
      createdDocId.value = data?.id;
      createdDocNumber.value = data?.order_number || '';
      submitted.value = true;

      toast?.add({
        severity: 'success',
        summary: 'Thành công',
        detail: `Đã tạo Đơn Bán Hàng ${createdDocNumber.value || ('ID ' + createdDocId.value)} thành công!`,
        life: 5000
      });
      emit('order-created', data);
    } else {
      // QUOTATION
      const payload = {
        customer_id: Number(props.action.customer_id),
        currency: 'VND',
        exchange_rate: 1.0,
        items: [
          {
            product_id: Number(props.action.product_id),
            sku: props.action.sku,
            quantity: Number(quantity.value),
            unit_price: discountedUnitPrice.value,
            discount_percent: Number(discountPercent.value) || 0
          }
        ]
      };
      const res = await salesApi.createQuotation(payload);
      const data = res?.data || res;
      createdDocId.value = data?.id;
      createdDocNumber.value = data?.quotation_number || '';
      submitted.value = true;

      toast?.add({
        severity: 'success',
        summary: 'Thành công',
        detail: `Đã tạo Báo Giá ${createdDocNumber.value || ('ID ' + createdDocId.value)} thành công!`,
        life: 5000
      });
      emit('order-created', data);
    }
  } catch (err) {
    console.error('Lỗi khi tạo chứng từ bán hàng từ Action Card:', err);
    toast?.add({
      severity: 'error',
      summary: 'Lỗi tạo chứng từ',
      detail: err.message || 'Không thể tạo chứng từ, vui lòng kiểm tra lại quyền hạn hoặc dữ liệu.',
      life: 5000
    });
  } finally {
    isSubmitting.value = false;
  }
}
</script>

