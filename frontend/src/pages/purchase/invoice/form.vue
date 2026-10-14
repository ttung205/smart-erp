<template>
  <div class="flex flex-col gap-4">
    <!-- 1. Page Header Chuẩn -->
    <PageHeader
      :title="isEditMode ? `Hóa Đơn & Đối Chiếu #${form.supplier_invoice_number || form.code || invoiceId}` : 'Nhập Hóa Đơn Tài Chính NCC'"
      :subtitle="isEditMode ? 'Kiểm tra chênh lệch đơn giá, số lượng giữa PO, GRN và Hóa đơn để ghi sổ tài chính' : 'Lưu hóa đơn tài chính VAT từ nhà cung cấp và kích hoạt đối chiếu 3 bên tự động'"
    >
      <template #actions>
        <div class="flex items-center gap-2">
          <Button
            label="Quay lại"
            icon="pi pi-arrow-left"
            size="small"
            text
            severity="secondary"
            class="!rounded-[6px] !text-xs !py-1.5"
            @click="$router.push('/purchase/invoices')"
          />

          <!-- Nút Lưu Hóa Đơn khi tạo mới -->
          <Button
            v-if="!isEditMode"
            label="Lưu & Chạy Đối Chiếu"
            icon="pi pi-save"
            size="small"
            class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 !rounded-[6px] !text-xs !font-semibold !py-1.5"
            :loading="saving"
            @click="saveInvoice"
          />

          <!-- Nút Thực Hiện Đối Chiếu Lại 3 Bên khi xem chi tiết -->
          <Button
            v-if="isEditMode && String(form.status).toUpperCase() !== 'POSTED'"
            label="Chạy Lại Đối Chiếu"
            icon="pi pi-sync"
            size="small"
            outlined
            class="!border-sky-500 !text-sky-600 hover:!bg-sky-50 !rounded-[6px] !text-xs !py-1.5"
            :loading="matching"
            @click="run3WayMatching"
          />

          <!-- Nút Ghi Sổ Kế Toán (Chỉ Cho Phép Khi MATCHED) -->
          <Button
            v-if="isEditMode && String(form.status).toUpperCase() !== 'POSTED'"
            label="Ghi Sổ Kế Toán (Post)"
            icon="pi pi-book"
            size="small"
            class="!bg-emerald-600 hover:!bg-emerald-700 !text-white !border-emerald-600 !rounded-[6px] !text-xs !font-semibold !py-1.5"
            :disabled="String(form.matching_status).toUpperCase() !== 'MATCHED'"
            :loading="posting"
            @click="postToGeneralLedger"
          />

          <span
            v-if="String(form.status).toUpperCase() === 'POSTED'"
            class="bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-semibold px-2.5 py-1 rounded-[6px] inline-flex items-center gap-1.5"
          >
            <i class="pi pi-check-circle"></i> Đã Ghi Sổ Kế Toán
          </span>
        </div>
      </template>
    </PageHeader>

    <!-- 2. Banner Cảnh Báo Nếu Có Lệch Số Liệu 3-Way Matching -->
    <div
      v-if="String(form.matching_status).toUpperCase() === 'DISCREPANCY'"
      class="bg-rose-50 border border-rose-300 rounded-[6px] p-3 text-xs text-rose-700 flex items-center justify-between"
    >
      <div class="flex items-center gap-2">
        <i class="pi pi-exclamation-circle text-base text-rose-600"></i>
        <span>
          <strong>Phát hiện sai lệch đối chiếu 3 bên:</strong> Đơn giá hoặc số lượng trên hóa đơn không khớp với Đơn mua (PO) hoặc Phiếu nhập kho (GRN). Không thể ghi sổ kế toán cho đến khi được điều chỉnh chính xác.
        </span>
      </div>
      <span class="font-bold uppercase tracking-wider text-[11px] px-2 py-0.5 rounded-[4px] bg-rose-200 text-rose-800">
        Discrepancy (Bất đồng)
      </span>
    </div>

    <!-- Banner Báo Khớp Hoàn Toàn -->
    <div
      v-else-if="String(form.matching_status).toUpperCase() === 'MATCHED'"
      class="bg-emerald-50 border border-emerald-300 rounded-[6px] p-3 text-xs text-emerald-800 flex items-center justify-between"
    >
      <div class="flex items-center gap-2">
        <i class="pi pi-check-circle text-base text-emerald-600"></i>
        <span>
          <strong>3-Way Matching Hợp Lệ:</strong> Dữ liệu Hợp đồng PO, Thực nhận GRN và Hóa đơn tài chính đã khớp 100%. Sẵn sàng để kế toán ghi nhận công nợ.
        </span>
      </div>
      <span class="font-bold uppercase tracking-wider text-[11px] px-2 py-0.5 rounded-[4px] bg-emerald-200 text-emerald-900">
        100% Matched (Khớp)
      </span>
    </div>

    <!-- 3. Thông Tin Hóa Đơn (Single Surface, Clean Flat Layout) -->
    <div class="bg-white border border-slate-200 rounded-[6px] p-4 flex flex-col gap-4">
      <div class="flex items-center justify-between pb-3 border-b border-slate-200">
        <div class="flex items-center gap-2">
          <i class="pi pi-receipt text-sky-600 text-sm"></i>
          <span class="text-xs font-bold text-slate-800 uppercase tracking-wide">Chi Tiết Hóa Đơn Tài Chính</span>
        </div>
        <div v-if="isEditMode" class="flex items-center gap-2">
          <span class="text-xs text-slate-500">Đối chiếu 3 bên:</span>
          <Tag
            :severity="getMatchingStatusSeverity(form.matching_status)"
            :value="getMatchingStatusLabel(form.matching_status)"
            class="!rounded-[6px] !text-[11px] !font-semibold !px-2 !py-0.5"
          />
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Số Hóa Đơn VAT Của NCC -->
        <div class="flex flex-col gap-1">
          <label class="text-xs font-medium text-slate-700">Số hóa đơn VAT <span class="text-rose-500">*</span></label>
          <InputText
            v-model="form.supplier_invoice_number"
            placeholder="Ví dụ: VAT-LG-2026/0891"
            size="small"
            class="!rounded-[6px] !text-xs font-mono font-bold"
            :disabled="isReadOnly"
          />
        </div>

        <!-- Đơn Mua Hàng Tham Chiếu (PO) -->
        <div class="flex flex-col gap-1">
          <label class="text-xs font-medium text-slate-700">Đơn mua hàng liên kết (PO) <span class="text-rose-500">*</span></label>
          <Select
            v-if="!isReadOnly"
            v-model="form.purchase_order_id"
            :options="poOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Chọn đơn PO..."
            size="small"
            class="!rounded-[6px] !text-xs"
            :loading="loadingPos"
            @change="onPoChange"
          />
          <InputText
            v-else
            :value="form.po_code || form.po_number || `PO #${form.purchase_order_id}`"
            size="small"
            class="!rounded-[6px] !text-xs font-mono"
            disabled
          />
        </div>

        <!-- Phiếu Nhập Kho Liên Kết (GRN) -->
        <div class="flex flex-col gap-1">
          <label class="text-xs font-medium text-slate-700">Phiếu kiểm nhận nhập kho (GRN)</label>
          <Select
            v-if="!isReadOnly"
            v-model="form.goods_receipt_id"
            :options="grnOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Tất cả đợt nhận hoặc chọn GRN..."
            size="small"
            class="!rounded-[6px] !text-xs"
            :disabled="!form.purchase_order_id"
            @change="onGrnChange"
          />
          <InputText
            v-else
            :value="form.goods_receipt_code || 'Toàn bộ đợt nhận của đơn PO'"
            size="small"
            class="!rounded-[6px] !text-xs font-mono"
            disabled
          />
        </div>

        <!-- Ngày Hóa Đơn -->
        <div class="flex flex-col gap-1">
          <label class="text-xs font-medium text-slate-700">Ngày hóa đơn</label>
          <InputText
            v-model="form.invoice_date"
            type="date"
            size="small"
            class="!rounded-[6px] !text-xs"
            :disabled="isReadOnly"
          />
        </div>

        <!-- Tiền Hàng Chưa Thuế -->
        <div class="flex flex-col gap-1">
          <label class="text-xs font-medium text-slate-700">Tiền hàng chưa thuế (Subtotal)</label>
          <InputNumber
            v-model="form.subtotal_amount"
            mode="currency"
            currency="VND"
            locale="vi-VN"
            size="small"
            class="!rounded-[6px] !text-xs"
            inputClass="!text-right !font-mono font-bold"
            disabled
          />
        </div>

        <!-- Thuế Suất VAT -->
        <div class="flex flex-col gap-1">
          <label class="text-xs font-medium text-slate-700">Tiền thuế GTGT (VAT 10%)</label>
          <InputNumber
            v-model="form.tax_amount"
            mode="currency"
            currency="VND"
            locale="vi-VN"
            size="small"
            class="!rounded-[6px] !text-xs"
            inputClass="!text-right !font-mono font-bold"
            disabled
          />
        </div>

        <!-- Tổng Thanh Toán -->
        <div class="flex flex-col gap-1">
          <label class="text-xs font-medium text-slate-700">Tổng tiền thanh toán (Total)</label>
          <InputNumber
            v-model="form.grand_total"
            mode="currency"
            currency="VND"
            locale="vi-VN"
            size="small"
            class="!rounded-[6px] !text-xs"
            inputClass="!text-right !font-mono font-bold !text-sky-700"
            disabled
          />
        </div>

        <!-- Hạn Thanh Toán -->
        <div class="flex flex-col gap-1">
          <label class="text-xs font-medium text-slate-700">Hạn thanh toán (Due Date)</label>
          <InputText
            v-model="form.due_date"
            type="date"
            size="small"
            class="!rounded-[6px] !text-xs"
            :disabled="isReadOnly"
          />
        </div>
      </div>
    </div>

    <!-- 4. Bảng Kiểm Soát Đối Chiếu 3 Bên (3-Way Matching Inspector Table) -->
    <div class="bg-white border border-slate-200 rounded-[6px] p-4 flex flex-col gap-3">
      <div class="flex items-center justify-between pb-2 border-b border-slate-200">
        <span class="text-xs font-bold text-slate-800 uppercase tracking-wide flex items-center gap-1.5">
          <i class="pi pi-table text-sky-600 text-xs"></i>
          Ma Trận Đối Chiếu Chi Tiết 3 Chiều (PO vs GRN vs Hóa Đơn)
        </span>
        <span class="text-xs text-slate-500">
          Có thể điều chỉnh SL/đơn giá hóa đơn thực tế để kiểm tra tính năng phát hiện sai lệch
        </span>
      </div>

      <div class="border border-slate-200 rounded-[6px] overflow-x-auto">
        <table class="w-full text-xs border-collapse">
          <colgroup>
            <col style="width: 30%;" />
            <col style="width: 9%;" />
            <col style="width: 13%;" />
            <col style="width: 11%;" />
            <col style="width: 11%;" />
            <col style="width: 13%;" />
            <col style="width: 13%;" />
          </colgroup>
          <thead>
            <!-- Dòng tiêu đề chính -->
            <tr class="bg-slate-100 border-b border-slate-200">
              <th rowspan="2" class="p-2.5 text-left font-semibold text-slate-700 border-r border-slate-200">
                Mặt Hàng & SKU
              </th>
              <th colspan="2" class="p-2 text-center font-semibold text-slate-700 border-r border-slate-200 bg-slate-50">
                <div class="flex items-center justify-center gap-1.5">
                  <i class="pi pi-shopping-cart text-[11px] text-slate-500"></i>
                  <span>1. Đơn Mua Hàng (PO)</span>
                </div>
              </th>
              <th rowspan="2" class="p-2 text-center font-semibold text-slate-700 border-r border-slate-200 bg-sky-50/80">
                <div class="flex flex-col items-center gap-0.5">
                  <span class="text-sky-800 font-semibold">2. Nhập Kho (GRN)</span>
                  <span class="text-[11px] font-normal text-sky-600">SL Thực Nhận</span>
                </div>
              </th>
              <th colspan="2" class="p-2 text-center font-semibold text-slate-700 border-r border-slate-200 bg-emerald-50/80">
                <div class="flex items-center justify-center gap-1.5">
                  <i class="pi pi-receipt text-[11px] text-emerald-600"></i>
                  <span class="text-emerald-800">3. Hóa Đơn VAT (Invoice)</span>
                </div>
              </th>
              <th rowspan="2" class="p-2.5 text-center font-semibold text-slate-700">
                Kết Quả Đối Soát
              </th>
            </tr>
            <!-- Dòng tiêu đề phụ cho các nhóm 2 cột -->
            <tr class="bg-slate-50 border-b border-slate-200 text-slate-600">
              <th class="p-2 text-right font-medium border-r border-slate-200">SL Đặt</th>
              <th class="p-2 text-right font-medium border-r border-slate-200">Đơn Giá PO</th>
              <th class="p-2 text-right font-medium text-emerald-800 border-r border-slate-200 bg-emerald-50/40 pr-2.5">SL Hóa Đơn</th>
              <th class="p-2 text-right font-medium text-emerald-800 border-r border-slate-200 bg-emerald-50/40 pr-2.5">Đơn Giá HĐ</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(item, idx) in form.items"
              :key="idx"
              class="border-b border-slate-100 last:border-none hover:bg-slate-50 transition-colors"
            >
              <!-- Mặt Hàng & SKU -->
              <td class="p-2.5 border-r border-slate-100">
                <div class="font-mono font-semibold text-slate-800">{{ item.sku }}</div>
                <div class="text-[11px] text-slate-500">{{ item.product_name }}</div>
              </td>

              <!-- 1. PO: SL Đặt & Đơn Giá -->
              <td class="p-2.5 text-right font-mono font-medium text-slate-700 border-r border-slate-100">
                {{ item.po_quantity }}
              </td>
              <td class="p-2.5 text-right font-mono text-slate-700 border-r border-slate-100">
                {{ formatCurrency(item.po_unit_price) }}
              </td>

              <!-- 2. GRN: SL Thực Nhận -->
              <td class="p-2.5 text-right font-mono font-bold text-sky-700 border-r border-slate-100 bg-sky-50/30">
                {{ item.grn_quantity }}
              </td>

              <!-- 3. Invoice: SL Hóa Đơn & Đơn Giá HĐ -->
              <td class="p-2 text-right font-mono font-bold border-r border-slate-100 bg-emerald-50/20 pr-2.5"
                  :class="{ '!text-rose-600': item.invoice_quantity !== item.grn_quantity }">
                <div class="flex justify-end">
                  <InputNumber
                    v-if="!isReadOnly"
                    v-model="item.invoice_quantity"
                    :min="1"
                    size="small"
                    class="w-24 !rounded-[6px]"
                    inputClass="!text-right !font-mono font-bold !py-1 !px-2 text-xs bg-white !border !border-slate-300 focus:!border-sky-500 !rounded-[6px] w-full"
                    @update:modelValue="recalculateTotals"
                  />
                  <span v-else>{{ item.invoice_quantity }}</span>
                </div>
              </td>
              <td class="p-2 text-right font-mono font-bold border-r border-slate-100 bg-emerald-50/20 pr-2.5"
                  :class="{ '!text-rose-600': item.invoice_unit_price !== item.po_unit_price }">
                <div class="flex justify-end">
                  <InputNumber
                    v-if="!isReadOnly"
                    v-model="item.invoice_unit_price"
                    :min="0"
                    mode="currency"
                    currency="VND"
                    locale="vi-VN"
                    size="small"
                    class="w-32 !rounded-[6px]"
                    inputClass="!text-right !font-mono font-bold !py-1 !px-2 text-xs bg-white !border !border-slate-300 focus:!border-sky-500 !rounded-[6px] w-full"
                    @update:modelValue="recalculateTotals"
                  />
                  <span v-else>{{ formatCurrency(item.invoice_unit_price) }}</span>
                </div>
              </td>

              <!-- Kết Quả Đối Soát -->
              <td class="p-2.5 text-center whitespace-nowrap">
                <div class="flex items-center justify-center">
                  <span
                    v-if="item.match_status === 'MATCHED'"
                    class="bg-emerald-50 text-emerald-700 border border-emerald-200 text-[11px] font-semibold px-2 py-0.5 rounded-[6px] inline-flex items-center gap-1"
                  >
                    <i class="pi pi-check text-[10px]"></i> Khớp 100%
                  </span>
                  <span
                    v-else-if="item.match_status === 'PRICE_DIFF'"
                    class="bg-rose-50 text-rose-700 border border-rose-200 text-[11px] font-semibold px-2 py-0.5 rounded-[6px] inline-flex items-center gap-1"
                    v-tooltip.top="`Lệch giá: PO ${formatCurrency(item.po_unit_price)} vs HĐ ${formatCurrency(item.invoice_unit_price)}`"
                  >
                    <i class="pi pi-times text-[10px]"></i> Lệch Giá
                  </span>
                  <span
                    v-else-if="item.match_status === 'QTY_DIFF'"
                    class="bg-rose-50 text-rose-700 border border-rose-200 text-[11px] font-semibold px-2 py-0.5 rounded-[6px] inline-flex items-center gap-1"
                    v-tooltip.top="`Lệch lượng: Nhập kho ${item.grn_quantity} vs Hóa đơn ${item.invoice_quantity}`"
                  >
                    <i class="pi pi-times text-[10px]"></i> Lệch Số Lượng
                  </span>
                  <span
                    v-else-if="item.match_status === 'BOTH'"
                    class="bg-rose-50 text-rose-700 border border-rose-200 text-[11px] font-semibold px-2 py-0.5 rounded-[6px] inline-flex items-center gap-1"
                  >
                    <i class="pi pi-times text-[10px]"></i> Lệch Cả Giá & SL
                  </span>
                  <span
                    v-else
                    class="bg-slate-100 text-slate-600 text-[11px] font-medium px-2 py-0.5 rounded-[6px] inline-block"
                  >
                    Chưa đối chiếu
                  </span>
                </div>
              </td>
            </tr>
            <tr v-if="form.items.length === 0">
              <td colspan="7" class="p-8 text-center text-slate-400">
                Chưa chọn đơn mua hàng (PO). Vui lòng chọn một đơn PO ở trên để nạp mặt hàng đối chiếu.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';

import PageHeader from '../../../components/common/PageHeader.vue';
import { purchaseApi } from '../../../api/purchase.api';
import { MATCHING_STATUS } from '../../../constants/purchase.constant';
import { formatCurrency } from '../../../utils/format';

const route = useRoute();
const router = useRouter();
const toast = useToast();

const invoiceId = computed(() => route.params.id);
const isEditMode = computed(() => !!invoiceId.value);
const saving = ref(false);
const matching = ref(false);
const posting = ref(false);
const loadingPos = ref(false);

const poOptions = ref([]);
const grnOptions = ref([]);
const currentPo = ref(null);
const currentGrnList = ref([]);

const form = reactive({
  id: null,
  code: '',
  supplier_invoice_number: '',
  purchase_order_id: null,
  po_code: '',
  po_number: '',
  goods_receipt_id: null,
  goods_receipt_code: '',
  supplier_id: 1,
  invoice_date: new Date().toISOString().split('T')[0],
  due_date: new Date(Date.now() + 30 * 86400000).toISOString().split('T')[0],
  subtotal_amount: 0,
  tax_amount: 0,
  grand_total: 0,
  matching_status: 'Unmatched',
  status: 'Draft',
  notes: '',
  items: []
});

const isReadOnly = computed(() => {
  return isEditMode.value && String(form.status).toUpperCase() === 'POSTED';
});

// Load danh sách đơn PO
const loadPurchaseOrders = async () => {
  loadingPos.value = true;
  try {
    const res = await purchaseApi.getOrders();
    const list = res?.data || res?.items || res || [];
    poOptions.value = list
      .filter(p => ['APPROVED', 'PARTIALLY_RECEIVED', 'RECEIVED'].includes(String(p.status).toUpperCase()))
      .map(p => ({
        label: `${p.code || 'PO #' + p.id} - ${p.notes || 'Đơn mua hàng'}`,
        value: p.id,
        supplier_id: p.supplier_id,
        code: p.code
      }));
  } catch (err) {
    console.error('Lỗi khi tải PO:', err);
  } finally {
    loadingPos.value = false;
  }
};

const onPoChange = async () => {
  if (!form.purchase_order_id) {
    form.items = [];
    grnOptions.value = [];
    return;
  }
  try {
    // 1. Lấy chi tiết PO
    const res = await purchaseApi.getOrderById(form.purchase_order_id);
    const po = res?.data || res;
    currentPo.value = po;
    form.po_code = po.code;
    form.supplier_id = po.supplier_id;

    // 2. Lấy các phiếu GRN của đơn PO này
    const grnRes = await purchaseApi.getReceipts({ purchaseOrderId: form.purchase_order_id });
    const grns = grnRes?.data || grnRes?.items || grnRes || [];
    currentGrnList.value = grns;
    grnOptions.value = [
      { label: 'Toàn bộ các đợt nhận (Tự động)', value: null },
      ...grns.map(g => ({
        label: `${g.code} (${formatDate(g.received_at)} - ${g.status})`,
        value: g.id,
        code: g.code
      }))
    ];
    form.goods_receipt_id = null;

    buildItemsMatrix();
  } catch (err) {
    console.error('Lỗi khi chọn PO:', err);
  }
};

const onGrnChange = () => {
  buildItemsMatrix();
};

const buildItemsMatrix = () => {
  if (!currentPo.value || !currentPo.value.items) return;

  const selectedGrn = currentGrnList.value.find(g => g.id === form.goods_receipt_id);
  form.goods_receipt_code = selectedGrn?.code || '';

  form.items = currentPo.value.items.map(it => {
    let grnQty = it.quantity_received || 0;
    let grnItemId = null;

    if (selectedGrn && selectedGrn.items) {
      const gItem = selectedGrn.items.find(gi => gi.purchase_order_item_id === it.id);
      if (gItem) {
        grnQty = gItem.quantity_received;
        grnItemId = gItem.id;
      }
    }

    const defaultInvQty = grnQty > 0 ? grnQty : it.quantity_ordered;
    const defaultUnitPrice = it.unit_cost || 0;

    return {
      purchase_order_item_id: it.id,
      goods_receipt_item_id: grnItemId,
      product_id: it.product_id,
      sku: it.sku,
      product_name: it.product_name,
      unit: it.unit || 'Cái',
      po_quantity: it.quantity_ordered,
      po_unit_price: defaultUnitPrice,
      grn_quantity: grnQty,
      invoice_quantity: defaultInvQty,
      invoice_unit_price: defaultUnitPrice,
      tax_rate: 10.00,
      match_status: 'MATCHED'
    };
  });

  recalculateTotals();
};

const recalculateTotals = () => {
  let subtotal = 0;
  for (const item of form.items) {
    const qty = Number(item.invoice_quantity) || 0;
    const price = Number(item.invoice_unit_price) || 0;
    subtotal += qty * price;

    // Cập nhật trạng thái đối soát tức thời trên UI
    const qtyMatch = qty === item.grn_quantity;
    const priceMatch = price === item.po_unit_price;

    if (qtyMatch && priceMatch) {
      item.match_status = 'MATCHED';
    } else if (!qtyMatch && !priceMatch) {
      item.match_status = 'BOTH';
    } else if (!priceMatch) {
      item.match_status = 'PRICE_DIFF';
    } else {
      item.match_status = 'QTY_DIFF';
    }
  }

  form.subtotal_amount = subtotal;
  form.tax_amount = Math.round(subtotal * 0.1);
  form.grand_total = form.subtotal_amount + form.tax_amount;

  const hasMismatch = form.items.some(i => i.match_status !== 'MATCHED');
  form.matching_status = hasMismatch ? 'Discrepancy' : 'Matched';
};

const loadInvoice = async (id) => {
  try {
    const res = await purchaseApi.getInvoiceById(id);
    const data = res?.data || res;
    if (data) {
      Object.assign(form, {
        id: data.id,
        code: data.code,
        supplier_invoice_number: data.supplier_invoice_number,
        purchase_order_id: data.purchase_order_id,
        po_code: data.po_code,
        goods_receipt_id: data.goods_receipt_id,
        goods_receipt_code: data.goods_receipt_code,
        supplier_id: data.supplier_id || 1,
        invoice_date: data.invoice_date ? data.invoice_date.split('T')[0] : '',
        due_date: data.due_date ? data.due_date.split('T')[0] : '',
        subtotal_amount: data.subtotal_amount,
        tax_amount: data.tax_amount,
        grand_total: data.grand_total,
        matching_status: data.matching_status,
        status: data.status,
        notes: data.notes || '',
        items: (data.items || []).map(i => ({
          purchase_order_item_id: i.purchase_order_item_id,
          goods_receipt_item_id: i.goods_receipt_item_id,
          product_id: i.product_id,
          sku: i.sku,
          product_name: i.product_name,
          unit: i.unit || 'Cái',
          po_quantity: i.quantity_invoiced, // Backend snapshot
          po_unit_price: i.unit_cost,
          grn_quantity: i.quantity_invoiced,
          invoice_quantity: i.quantity_invoiced,
          invoice_unit_price: i.unit_cost,
          tax_rate: i.tax_rate,
          match_status: i.matching_status || 'MATCHED'
        }))
      });
    }
  } catch (err) {
    console.error('Lỗi khi tải hóa đơn:', err);
    toast.add({
      severity: 'error',
      summary: 'Lỗi',
      detail: err.message || 'Không tìm thấy hóa đơn tài chính',
      life: 4000
    });
  }
};

const saveInvoice = async () => {
  if (!form.supplier_invoice_number || !form.purchase_order_id) {
    toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Vui lòng nhập số hóa đơn VAT và chọn đơn PO liên kết', life: 3000 });
    return;
  }
  if (form.items.length === 0) {
    toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Hóa đơn phải có ít nhất 1 dòng mặt hàng', life: 3000 });
    return;
  }

  saving.value = true;
  try {
    const payload = {
      purchase_order_id: form.purchase_order_id,
      goods_receipt_id: form.goods_receipt_id || null,
      supplier_id: form.supplier_id || 1,
      supplier_invoice_number: form.supplier_invoice_number.trim(),
      invoice_date: form.invoice_date ? new Date(form.invoice_date).toISOString() : new Date().toISOString(),
      due_date: form.due_date ? new Date(form.due_date).toISOString() : null,
      notes: form.notes || null,
      items: form.items.map(it => ({
        purchase_order_item_id: it.purchase_order_item_id,
        goods_receipt_item_id: it.goods_receipt_item_id || null,
        product_id: it.product_id,
        sku: it.sku,
        product_name: it.product_name,
        unit: it.unit || 'Cái',
        quantity_invoiced: Number(it.invoice_quantity),
        unit_cost: Number(it.invoice_unit_price),
        tax_rate: Number(it.tax_rate || 10)
      }))
    };

    const res = await purchaseApi.createInvoice(payload);
    const created = res?.data || res;
    
    toast.add({
      severity: String(created.matching_status).toUpperCase() === 'MATCHED' ? 'success' : 'warn',
      summary: 'Đã lưu hóa đơn thành công',
      detail: `Mã hóa đơn: ${created.code}. Kết quả đối chiếu 3 bên: ${getMatchingStatusLabel(created.matching_status)}`,
      life: 4000
    });

    router.push('/purchase/invoices');
  } catch (err) {
    console.error('Lỗi khi lưu hóa đơn:', err);
    toast.add({
      severity: 'error',
      summary: 'Thất bại',
      detail: err.message || 'Không thể tạo hóa đơn tài chính',
      life: 4500
    });
  } finally {
    saving.value = false;
  }
};

const run3WayMatching = async () => {
  matching.value = true;
  try {
    const res = await purchaseApi.matchInvoice(form.id);
    const data = res?.data || res;
    const overall = data?.overall_status || data?.matching_status;
    if (overall) {
      form.matching_status = overall;
    }
    toast.add({
      severity: String(form.matching_status).toUpperCase() === 'MATCHED' ? 'success' : 'warn',
      summary: 'Kết quả đối chiếu 3 bên',
      detail: String(form.matching_status).toUpperCase() === 'MATCHED' 
        ? 'Dữ liệu PO, GRN và Hóa đơn khớp 100%! Có thể ghi sổ kế toán.' 
        : `Phát hiện ${data?.items_discrepancy_count || 1} sai lệch giữa hóa đơn và đơn mua/nhập kho`,
      life: 4000
    });
    loadInvoice(form.id);
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi đối chiếu',
      detail: err.message || 'Không thể thực hiện đối chiếu 3 bên',
      life: 4000
    });
  } finally {
    matching.value = false;
  }
};

const postToGeneralLedger = async () => {
  posting.value = true;
  try {
    await purchaseApi.postInvoice(form.id);
    form.status = 'Posted';
    toast.add({
      severity: 'success',
      summary: 'Thành công',
      detail: 'Đã ghi sổ kế toán công nợ nhà cung cấp thành công!',
      life: 4000
    });
    router.push('/purchase/invoices');
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Ghi sổ thất bại',
      detail: err.message || 'Không thể ghi sổ kế toán',
      life: 4500
    });
  } finally {
    posting.value = false;
  }
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

onMounted(async () => {
  await loadPurchaseOrders();
  if (isEditMode.value) {
    loadInvoice(invoiceId.value);
  } else {
    if (route.query.poId) {
      form.purchase_order_id = Number(route.query.poId);
      await onPoChange();
      if (route.query.grnId) {
        form.goods_receipt_id = Number(route.query.grnId);
        onGrnChange();
      }
    }
  }
});
</script>
