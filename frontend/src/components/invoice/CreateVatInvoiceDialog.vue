<template>
  <Dialog
    :visible="visible"
    modal
    :header="`Lập Hóa Đơn GTGT (VAT Invoice) - Chứng Từ: ${salesInvoice?.invoice_number || ''}`"
    :style="{ width: '1050px' }"
    class="rounded-[8px]"
    :draggable="false"
    @update:visible="$emit('update:visible', $event)"
  >
    <div v-if="salesInvoice" class="flex flex-col gap-4 pt-1">
      <!-- 1. Thông tin hóa đơn & Bên mua hàng -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-3 bg-white border border-slate-200 rounded-[6px] p-3.5 text-xs">
        <div class="flex flex-col gap-1.5">
          <label class="font-bold text-slate-700 uppercase tracking-wide text-[11px]">
            Số hóa đơn GTGT <span class="text-red-500">*</span>
          </label>
          <InputText
            v-model="form.invoice_number"
            class="w-full text-xs font-mono font-bold text-indigo-700 rounded-[6px] h-9 px-3 border-slate-300"
            placeholder="VD: 0001234"
          />
        </div>

        <div class="flex flex-col gap-1.5">
          <label class="font-bold text-slate-700 uppercase tracking-wide text-[11px]">
            Ký hiệu hóa đơn (Series) <span class="text-red-500">*</span>
          </label>
          <InputText
            v-model="form.invoice_series"
            class="w-full text-xs font-mono font-bold rounded-[6px] h-9 px-3 border-slate-300"
            placeholder="VD: 1C26TBB"
          />
        </div>

        <div class="flex flex-col gap-1.5">
          <label class="font-bold text-slate-700 uppercase tracking-wide text-[11px]">
            Ngày hóa đơn <span class="text-red-500">*</span>
          </label>
          <InputText
            v-model="form.invoice_date"
            type="date"
            class="w-full text-xs font-mono rounded-[6px] h-9 px-3 border-slate-300"
          />
        </div>

        <!-- Khách hàng, MST, Địa chỉ (Tự động lấy theo khách hàng, không cho sửa) -->
        <div class="flex flex-col gap-1.5">
          <label class="font-bold text-slate-700 uppercase tracking-wide text-[11px] flex items-center justify-between">
            <span>Tên đơn vị mua hàng</span>
            <span class="text-[10px] text-slate-400 font-normal lowercase">(tự động)</span>
          </label>
          <InputText
            :value="form.customer_name"
            class="w-full text-xs rounded-[6px] h-9 px-3 border-slate-200 !bg-slate-50 font-semibold text-slate-700"
            readonly
            disabled
          />
        </div>

        <div class="flex flex-col gap-1.5">
          <label class="font-bold text-slate-700 uppercase tracking-wide text-[11px] flex items-center justify-between">
            <span>Mã số thuế (MST)</span>
            <span class="text-[10px] text-slate-400 font-normal lowercase">(tự động)</span>
          </label>
          <InputText
            :value="form.customer_tax_code"
            class="w-full text-xs font-mono font-bold rounded-[6px] h-9 px-3 border-slate-200 !bg-slate-50 text-slate-700"
            readonly
            disabled
          />
        </div>

        <div class="flex flex-col gap-1.5">
          <label class="font-bold text-slate-700 uppercase tracking-wide text-[11px] flex items-center justify-between">
            <span>Địa chỉ xuất hóa đơn</span>
            <span class="text-[10px] text-slate-400 font-normal lowercase">(tự động)</span>
          </label>
          <InputText
            :value="form.customer_address"
            class="w-full text-xs rounded-[6px] h-9 px-3 border-slate-200 !bg-slate-50 text-slate-700"
            readonly
            disabled
          />
        </div>
      </div>

      <!-- 2. Danh sách mặt hàng xuất hóa đơn (Cho phép chọn & chỉnh số lượng xuất HĐ) -->
      <div class="border border-slate-200 rounded-[6px] overflow-hidden">
        <div class="bg-slate-50 px-3 py-2 border-b border-slate-200 flex items-center justify-between">
          <div class="flex items-center gap-2">
            <span class="text-xs font-bold text-slate-700 uppercase tracking-wide">
              Danh Sách Mặt Hàng Xuất Hóa Đơn (Chọn & Điều Chỉnh Số Lượng)
            </span>
            <span class="text-[10px] bg-emerald-100 text-emerald-800 font-semibold px-2 py-0.5 rounded">
              Chuyển TK 1312 ➔ 1311
            </span>
          </div>
          <span class="text-[11px] text-slate-500">
            Tổng cộng: <strong class="text-slate-800">{{ invoiceItems.length }}</strong> mặt hàng
          </span>
        </div>

        <DataTable :value="invoiceItems" showGridlines responsiveLayout="scroll" class="p-datatable-sm text-xs">
          <Column header="#" style="width: 45px" class="text-center">
            <template #body="{ index }">
              <span class="font-mono text-slate-400 text-xs">{{ index + 1 }}</span>
            </template>
          </Column>

          <Column field="sku" header="Mã SKU" style="width: 120px">
            <template #body="{ data }">
              <span class="font-mono text-xs font-bold text-sky-700 bg-sky-50 px-1.5 py-0.5 rounded border border-sky-200">
                {{ data.sku }}
              </span>
            </template>
          </Column>

          <Column field="product_name" header="Tên Hàng Hóa / Dịch Vụ">
            <template #body="{ data }">
              <span class="text-xs font-semibold text-slate-800">{{ data.product_name }}</span>
            </template>
          </Column>

          <Column header="Đã Xuất Kho" style="width: 95px" class="text-center">
            <template #body="{ data }">
              <span class="font-mono text-xs font-bold text-slate-700">
                {{ data.issued_quantity }}
              </span>
            </template>
          </Column>

          <Column header="Đã Lập HĐ" style="width: 90px" class="text-center">
            <template #body="{ data }">
              <span class="font-mono text-xs font-semibold text-emerald-600">
                {{ data.invoiced_quantity }}
              </span>
            </template>
          </Column>

          <Column header="Chưa Lập HĐ" style="width: 95px" class="text-center">
            <template #body="{ data }">
              <span class="font-mono text-xs font-bold text-rose-600">
                {{ data.remaining_quantity }}
              </span>
            </template>
          </Column>

          <Column header="Đơn Giá" style="width: 120px" class="text-right">
            <template #body="{ data }">
              <span class="font-mono text-xs text-slate-700 font-semibold">
                {{ formatCurrency(data.unit_price) }}
              </span>
            </template>
          </Column>

          <Column header="Thuế VAT" style="width: 80px" class="text-center">
            <template #body="{ data }">
              <span class="font-mono text-xs text-slate-600">
                {{ data.vat_percent }}%
              </span>
            </template>
          </Column>

          <!-- Ô nhập số lượng xuất hóa đơn đợt này -->
          <Column header="SL Xuất HĐ Lần Này" style="width: 140px" class="text-center">
            <template #body="{ data }">
              <div class="flex items-center justify-center">
                <InputNumber
                  v-model="data.invoice_now_quantity"
                  :min="0"
                  :max="data.remaining_quantity"
                  class="w-24 text-center font-mono font-bold text-xs"
                  inputClass="!w-24 !text-center !text-xs !font-mono !font-bold !py-1 !px-2 rounded border border-emerald-400 focus:!border-emerald-600 bg-emerald-50/40 text-emerald-900"
                />
              </div>
            </template>
          </Column>
        </DataTable>
      </div>

      <!-- 3. Tóm tắt tiền hóa đơn -->
      <div class="flex flex-wrap items-center justify-between px-4 py-3 bg-slate-50 border border-slate-200 rounded-[6px] text-xs">
        <div class="flex items-center gap-6">
          <span class="text-slate-600">
            Tiền hàng (Subtotal):
            <strong class="font-mono text-slate-800 ml-1 text-sm">{{ formatCurrency(totalSubtotal) }}</strong>
          </span>
          <span class="text-slate-600">
            Tiền thuế VAT:
            <strong class="font-mono text-slate-800 ml-1 text-sm">{{ formatCurrency(totalVat) }}</strong>
          </span>
        </div>
        <div>
          <span class="text-slate-700 font-bold text-sm">
            Tổng cộng tiền thanh toán:
            <strong class="font-mono text-indigo-700 font-bold ml-1 text-base">{{ formatCurrency(totalAmount) }}</strong>
          </span>
        </div>
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end gap-2 pt-2">
        <Button
          label="Hủy bỏ"
          icon="pi pi-times"
          text
          size="small"
          severity="secondary"
          class="rounded-[6px]"
          @click="$emit('update:visible', false)"
        />
        <Button
          label="Xác nhận lập hóa đơn VAT"
          icon="pi pi-check"
          size="small"
          class="!bg-emerald-600 hover:!bg-emerald-700 !text-white !border-emerald-600 rounded-[6px] !font-semibold shadow-sm"
          :loading="submitting"
          :disabled="totalAmount <= 0"
          @click="submitVatInvoice"
        />
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import Dialog from 'primevue/dialog';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import { useToast } from 'primevue/usetoast';
import { salesApi } from '../../api/sales.api';
import { formatCurrency } from '../../utils/format';

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  salesInvoice: {
    type: Object,
    default: null
  },
  currentUser: {
    type: Object,
    default: null
  }
});

const emit = defineEmits(['update:visible', 'created']);

const toast = useToast();
const submitting = ref(false);
const invoiceItems = ref([]);

const form = ref({
  invoice_number: '',
  invoice_series: '1C26TBB',
  invoice_date: new Date().toISOString().split('T')[0],
  customer_name: '',
  customer_tax_code: '',
  customer_address: ''
});

const initItems = async () => {
  const inv = props.salesInvoice;
  if (!inv) return;

  // Lấy số hóa đơn tiếp theo theo quy tắc 8 chữ số tăng dần (00000001, 00000002,...)
  let nextInvNumber = '00000001';
  let nextSeries = '1C26TBB';
  try {
    const numRes = await salesApi.getNextVatInvoiceNumber();
    if (numRes && numRes.next_number) {
      nextInvNumber = numRes.next_number;
    }
    if (numRes && numRes.series) {
      nextSeries = numRes.series;
    }
  } catch (err) {
    console.warn('Lỗi lấy số HĐ tiếp theo:', err);
  }

  form.value = {
    invoice_number: nextInvNumber,
    invoice_series: nextSeries,
    invoice_date: new Date().toISOString().split('T')[0],
    customer_name: inv.customer_name || inv.customer?.name || '',
    customer_tax_code: inv.customer_tax_code || inv.customer?.tax_code || '0101234567',
    customer_address: inv.customer_address || inv.shipping_address || inv.customer?.address || ''
  };

  // Tải danh sách mặt hàng chưa xuất hóa đơn VAT từ revenue_report_items
  try {
    const res = await salesApi.getUnbilledRevenueItems({ invoice_id: inv.id });
    const unbilledList = res.data || [];
    
    if (unbilledList.length > 0) {
      invoiceItems.value = unbilledList.map((r) => ({
        revenue_report_item_id: r.id,
        sales_invoice_item_id: r.invoice_item_id,
        order_item_id: r.order_item_id,
        product_id: r.product_id,
        sku: r.sku,
        product_name: r.product_name,
        issued_quantity: Number(r.quantity || 0),
        invoiced_quantity: Number(r.invoiced_quantity || 0),
        remaining_quantity: Number(r.remaining_invoice_quantity || r.quantity || 0),
        invoice_now_quantity: Number(r.remaining_invoice_quantity || r.quantity || 0),
        unit_price: Number(r.unit_price || 0),
        vat_percent: Number(r.vat_rate || 10)
      }));
    } else {
      // Fallback lấy trực tiếp từ items của sales invoice
      invoiceItems.value = (inv.items || []).map((it) => {
        const qty = Number(it.quantity || 1);
        return {
          sales_invoice_item_id: it.id,
          order_item_id: it.order_item_id,
          product_id: it.product_id,
          sku: it.sku,
          product_name: it.product_name || it.description || 'Sản phẩm',
          issued_quantity: qty,
          invoiced_quantity: 0,
          remaining_quantity: qty,
          invoice_now_quantity: qty,
          unit_price: Number(it.unit_price || it.selling_price || 0),
          vat_percent: Number(it.vat_percent || 10)
        };
      });
    }
  } catch (err) {
    console.warn('Lỗi tải danh sách mặt hàng chưa xuất HĐ:', err);
  }
};

watch(
  () => props.visible,
  (val) => {
    if (val && props.salesInvoice) {
      initItems();
    }
  }
);

const totalSubtotal = computed(() => {
  return invoiceItems.value.reduce((sum, it) => {
    const qty = Number(it.invoice_now_quantity) || 0;
    const price = Number(it.unit_price) || 0;
    return sum + (qty * price);
  }, 0);
});

const totalVat = computed(() => {
  return invoiceItems.value.reduce((sum, it) => {
    const qty = Number(it.invoice_now_quantity) || 0;
    const price = Number(it.unit_price) || 0;
    const vatRate = Number(it.vat_percent) || 10;
    return sum + Math.round(qty * price * (vatRate / 100));
  }, 0);
});

const totalAmount = computed(() => {
  return totalSubtotal.value + totalVat.value;
});

const submitVatInvoice = async () => {
  if (!form.value.invoice_number) {
    toast.add({
      severity: 'warn',
      summary: 'Thiếu số hóa đơn',
      detail: 'Vui lòng nhập số hóa đơn GTGT.',
      life: 3000
    });
    return;
  }

  const validItems = invoiceItems.value.filter((it) => Number(it.invoice_now_quantity) > 0);
  if (validItems.length === 0) {
    toast.add({
      severity: 'warn',
      summary: 'Chưa chọn mặt hàng',
      detail: 'Vui lòng chọn số lượng xuất hóa đơn lớn hơn 0 cho ít nhất 1 mặt hàng.',
      life: 3000
    });
    return;
  }

  submitting.value = true;
  try {
    const payload = {
      invoice_number: form.value.invoice_number,
      invoice_series: form.value.invoice_series,
      invoice_date: form.value.invoice_date,
      sales_invoice_id: props.salesInvoice.id,
      customer_name: form.value.customer_name,
      customer_tax_code: form.value.customer_tax_code,
      customer_address: form.value.customer_address,
      created_by: props.currentUser?.name || 'Kế toán viên',
      items: validItems.map((it) => ({
        sales_invoice_item_id: it.sales_invoice_item_id,
        revenue_report_item_id: it.revenue_report_item_id,
        order_item_id: it.order_item_id,
        product_id: it.product_id,
        sku: it.sku,
        product_name: it.product_name,
        quantity: Number(it.invoice_now_quantity),
        unit_price: Number(it.unit_price),
        vat_percent: Number(it.vat_percent)
      }))
    };

    const res = await salesApi.createVatInvoice(payload);
    emit('created', res.data);
    emit('update:visible', false);

    toast.add({
      severity: 'success',
      summary: 'Xuất hóa đơn VAT thành công',
      detail: `Đã lập hóa đơn GTGT ${form.value.invoice_number} và chuyển doanh thu sang TK 1311.`,
      life: 4000
    });
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi lập hóa đơn VAT',
      detail: err.message || 'Không thể tạo hóa đơn GTGT.',
      life: 3000
    });
  } finally {
    submitting.value = false;
  }
};
</script>
