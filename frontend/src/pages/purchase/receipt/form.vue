<template>
  <div class="flex flex-col gap-4">
    <!-- 1. Page Header Chuẩn Theo UI_RULES.md -->
    <PageHeader
      :title="isEditMode ? `Phiếu Nhập Kho #${form.code || form.grn_number || receiptId}` : 'Lập Phiếu Kiểm Nhận Nhập Kho (GRN)'"
      :subtitle="isEditMode ? 'Xem chi tiết biên bản kiểm đếm và trạng thái kích hoạt cập nhật tồn kho' : 'Kiểm đếm thực tế thiết bị từ nhà cung cấp theo hợp đồng đơn mua PO'"
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
            @click="$router.push('/purchase/receipts')"
          />

          <!-- Nút Xác Nhận khi đang xem chi tiết đơn DRAFT -->
          <Button
            v-if="isEditMode && String(form.status).toUpperCase() === 'DRAFT'"
            label="Xác nhận Nhập Kho Ngay"
            icon="pi pi-check"
            size="small"
            class="!bg-emerald-600 hover:!bg-emerald-700 !text-white !border-emerald-600 !rounded-[6px] !text-xs !font-semibold !py-1.5"
            :loading="confirming"
            @click="confirmExistingReceipt"
          />

          <!-- Nút Lưu Bản Nháp khi tạo mới -->
          <Button
            v-if="!isEditMode"
            label="Lưu bản nháp"
            icon="pi pi-save"
            size="small"
            outlined
            class="!border-slate-300 !text-slate-700 hover:!bg-slate-100 !rounded-[6px] !text-xs !py-1.5"
            :loading="saving"
            @click="saveReceipt(false)"
          />

          <!-- Nút Xác Nhận Nhập Kho & Phát Outbox Event khi tạo mới -->
          <Button
            v-if="!isEditMode"
            label="Xác nhận & Đồng Bộ Tồn Kho"
            icon="pi pi-check"
            size="small"
            class="!bg-emerald-600 hover:!bg-emerald-700 !text-white !border-emerald-600 !rounded-[6px] !text-xs !font-semibold !py-1.5"
            :loading="saving || confirming"
            :disabled="hasOverReceiptError || !canSubmit"
            @click="saveReceipt(true)"
          />
        </div>
      </template>
    </PageHeader>

    <!-- 2. Cảnh Báo Nếu Có Dòng Nhập Vượt Quá Số Lượng PO (Over-Receipt Guard) -->
    <div
      v-if="hasOverReceiptError"
      class="bg-rose-50 border border-rose-300 rounded-[6px] p-3 text-xs text-rose-700 flex items-center gap-2"
    >
      <i class="pi pi-exclamation-triangle text-base text-rose-600"></i>
      <span>
        <strong>Cảnh báo kiểm đếm:</strong> Tổng số lượng thực nhận và số lượng trả lại của mặt hàng vượt quá số lượng còn lại của đơn PO. Vui lòng điều chỉnh sao cho: <strong>SL Thực nhận + SL Trả lại &le; Số lượng còn lại</strong>.
      </span>
    </div>

    <!-- 3. Thông Tin Phiếu Nhập Kho (Single Surface, Clean Flat Layout) -->
    <div class="bg-white border border-slate-200 rounded-[6px] p-4 flex flex-col gap-4">
      <div class="flex items-center justify-between pb-3 border-b border-slate-200">
        <div class="flex items-center gap-2">
          <i class="pi pi-inbox text-sky-600 text-sm"></i>
          <span class="text-xs font-bold text-slate-800 uppercase tracking-wide">Thông Tin Tiếp Nhận Hàng</span>
        </div>
        <div v-if="isEditMode" class="flex items-center gap-2">
          <span class="text-xs text-slate-500">Trạng thái:</span>
          <Tag
            :severity="getStatusSeverity(form.status)"
            :value="getStatusLabel(form.status)"
            class="!rounded-[6px] !text-[11px] !font-semibold !px-2 !py-0.5"
          />
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Đơn Mua Hàng Tham Chiếu (PO) -->
        <div class="flex flex-col gap-1">
          <label class="text-xs font-medium text-slate-700">Đơn mua hàng (PO) <span class="text-rose-500">*</span></label>
          <Select
            v-if="!isReadOnly"
            v-model="form.purchase_order_id"
            :options="poOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Chọn đơn PO đã duyệt..."
            size="small"
            class="!rounded-[6px] !text-xs"
            :loading="loadingPos"
            @change="onPoSelected"
          />
          <InputText
            v-else
            :value="form.po_code || form.po_number || `PO #${form.purchase_order_id}`"
            size="small"
            class="!rounded-[6px] !text-xs font-mono font-bold"
            disabled
          />
        </div>

        <!-- Kho Tiếp Nhận Thực Tế -->
        <div class="flex flex-col gap-1">
          <label class="text-xs font-medium text-slate-700">Kho lưu trữ <span class="text-rose-500">*</span></label>
          <Select
            v-model="form.warehouse_id"
            :options="warehouseOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Chọn kho bãi..."
            size="small"
            class="!rounded-[6px] !text-xs"
            :disabled="isReadOnly"
          />
        </div>

        <!-- Số Biên Bản Giao Hàng / Vận Đơn -->
        <div class="flex flex-col gap-1">
          <label class="text-xs font-medium text-slate-700">Số biên bản giao nhận / Phiếu xuất NCC</label>
          <InputText
            v-model="form.delivery_note_number"
            placeholder="Ví dụ: BBGN-LG-2026/88..."
            size="small"
            class="!rounded-[6px] !text-xs"
            :disabled="isReadOnly"
          />
        </div>

        <!-- Người Kiểm Đếm Tiếp Nhận -->
        <div class="flex flex-col gap-1">
          <label class="text-xs font-medium text-slate-700">Thủ kho kiểm đếm</label>
          <InputText
            v-model="form.received_by_name"
            placeholder="Họ tên thủ kho..."
            size="small"
            class="!rounded-[6px] !text-xs"
            :disabled="isReadOnly"
          />
        </div>

        <!-- Ghi Chú Tình Trạng Hàng Hóa -->
        <div class="flex flex-col gap-1 md:col-span-2 lg:col-span-4">
          <label class="text-xs font-medium text-slate-700">Tình trạng bao bì & Ghi chú kiểm nhận</label>
          <InputText
            v-model="form.notes"
            placeholder="Tình trạng seal, thùng carton nguyên vẹn, đối soát số serial thiết bị..."
            size="small"
            class="!rounded-[6px] !text-xs"
            :disabled="isReadOnly"
          />
        </div>
      </div>
    </div>

    <!-- 4. Bảng Kiểm Đếm Mặt Hàng Đối Soát Với PO (Hairline Table, Flat White) -->
    <div class="bg-white border border-slate-200 rounded-[6px] p-4 flex flex-col gap-3">
      <div class="flex items-center justify-between pb-2 border-b border-slate-200">
        <span class="text-xs font-bold text-slate-800 uppercase tracking-wide flex items-center gap-1.5">
          <i class="pi pi-list-check text-sky-600 text-xs"></i>
          Danh Sách Mặt Hàng Kiểm Đếm Tại Cửa Kho ({{ form.items.length }})
        </span>
        <span class="text-xs text-slate-500">
          Quy tắc: <strong>SL Thực nhận + SL Trả lại &le; Số lượng còn lại</strong>
        </span>
      </div>

      <div class="border border-slate-200 rounded-[6px] overflow-hidden">
        <table class="w-full text-xs">
          <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
              <th class="p-2.5 text-center font-semibold text-slate-700 w-10">#</th>
              <th class="p-2.5 text-left font-semibold text-slate-700">Mã SKU & Tên Sản Phẩm</th>
              <th class="p-2.5 text-center font-semibold text-slate-700 w-20">ĐVT</th>
              <th class="p-2.5 text-right font-semibold text-slate-700 w-24">SL Đặt PO</th>
              <th class="p-2.5 text-right font-semibold text-slate-700 w-24">Đã Nhập Trước</th>
              <th class="p-2.5 text-right font-semibold text-sky-700 w-24">Còn Lại</th>
              <th class="p-2.5 text-right font-semibold text-emerald-700 w-32 pr-2">SL Thực Nhận</th>
              <th class="p-2.5 text-right font-semibold text-amber-700 w-32 pr-2">SL Trả Lại</th>
              <th class="p-2.5 text-right font-semibold text-slate-700 w-28 pr-3">Tổng Kiểm Đếm</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(item, idx) in form.items"
              :key="idx"
              class="border-b border-slate-100 last:border-none hover:bg-slate-50"
              :class="{ 'bg-rose-50/40': (Number(item.quantity_received || 0) + Number(item.quantity_rejected || 0)) > item.quantity_remaining }"
            >
              <td class="p-2.5 text-center text-slate-400 font-mono">{{ idx + 1 }}</td>
              <td class="p-2.5">
                <div class="font-mono font-semibold text-slate-800">{{ item.sku }}</div>
                <div class="text-[11px] text-slate-500">{{ item.product_name }}</div>
              </td>
              <td class="p-2.5 text-center text-slate-600">{{ item.unit || 'Cái' }}</td>
              <td class="p-2.5 text-right font-mono font-medium text-slate-700">
                {{ item.quantity_ordered }}
              </td>
              <td class="p-2.5 text-right font-mono text-slate-500">
                {{ item.quantity_received_before || 0 }}
              </td>
              <td class="p-2.5 text-right font-mono font-bold text-sky-700">
                {{ item.quantity_remaining }}
              </td>
              <!-- Cột SL Thực Nhận -->
              <td class="p-2 text-right pr-2">
                <div class="flex items-center justify-end">
                  <InputNumber
                    v-if="!isReadOnly"
                    v-model="item.quantity_received"
                    :min="0"
                    size="small"
                    class="w-24 !rounded-[6px]"
                    inputClass="!text-right !font-mono font-bold !py-1 !px-2 text-xs bg-white !border !border-slate-300 focus:!border-emerald-500 !rounded-[6px] w-full text-emerald-800"
                  />
                  <span v-else class="font-mono font-bold text-emerald-700 text-sm">
                    {{ item.quantity_received }}
                  </span>
                </div>
              </td>
              <!-- Cột SL Trả Lại (Hàng lỗi, hỏng, vỡ, giao thừa) -->
              <td class="p-2 text-right pr-2">
                <div class="flex items-center justify-end">
                  <InputNumber
                    v-if="!isReadOnly"
                    v-model="item.quantity_rejected"
                    :min="0"
                    size="small"
                    class="w-24 !rounded-[6px]"
                    inputClass="!text-right !font-mono font-bold !py-1 !px-2 text-xs bg-white !border !border-slate-300 focus:!border-amber-500 !rounded-[6px] w-full text-amber-800"
                  />
                  <span v-else class="font-mono font-bold text-amber-700 text-sm">
                    {{ item.quantity_rejected || 0 }}
                  </span>
                </div>
              </td>
              <!-- Cột Tổng Kiểm Đếm -->
              <td class="p-2.5 text-right font-mono pr-3 font-semibold"
                  :class="(Number(item.quantity_received || 0) + Number(item.quantity_rejected || 0)) > item.quantity_remaining ? 'text-rose-600' : 'text-slate-800'">
                {{ (Number(item.quantity_received || 0) + Number(item.quantity_rejected || 0)) }}
                <span class="text-[10px] text-slate-400 font-normal">/ {{ item.quantity_remaining }}</span>
              </td>
            </tr>
            <tr v-if="form.items.length === 0">
              <td colspan="9" class="p-8 text-center text-slate-400">
                Chưa chọn đơn mua hàng (PO) hoặc đơn hàng chưa có mặt hàng cần nhận.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Thanh Thống Kê Tổng Kết Kiểm Đếm -->
      <div v-if="form.items.length > 0" class="flex flex-wrap items-center justify-between p-3 bg-slate-50 border border-slate-200 rounded-[6px] text-xs gap-3">
        <div class="flex flex-wrap items-center gap-4">
          <div>
            <span class="text-slate-500">Tổng SL thực nhận:</span>
            <strong class="font-mono text-emerald-700 ml-1 text-sm font-bold">{{ totalReceivedQty }}</strong>
            <span class="text-[11px] text-slate-400 ml-1">(Tăng tồn On-Hand kho)</span>
          </div>

          <div v-if="totalRejectedQty > 0" class="flex items-center gap-1.5 px-2.5 py-1 bg-amber-50 border border-amber-200 rounded-[6px] text-amber-800">
            <i class="pi pi-replay text-xs text-amber-600"></i>
            <span>Tổng SL trả lại:</span>
            <strong class="font-mono text-amber-700 font-bold">{{ totalRejectedQty }}</strong>
            <span class="text-[11px] font-medium text-amber-600">(Tự động tạo Phiếu trả hàng RTV nháp)</span>
          </div>

          <div v-if="!isReadOnly && form.purchase_order_id && (totalReceivedQty + totalRejectedQty) < totalRemainingQty" class="flex items-center gap-2 px-2.5 py-1 bg-slate-100 border border-slate-200 rounded-[6px]">
            <input
              id="closePoAfterReceipt"
              type="checkbox"
              v-model="closePoAfterReceipt"
              class="rounded border-slate-300 text-sky-600 focus:ring-sky-500 cursor-pointer w-3.5 h-3.5"
            />
            <label for="closePoAfterReceipt" class="text-xs text-slate-700 cursor-pointer select-none">
              Đóng đơn PO sau lần nhập này <span class="text-slate-400">(NCC không giao bù số lượng thiếu nữa)</span>
            </label>
          </div>
        </div>

        <div class="text-slate-500 font-mono text-[11px]">
          Tổng kiểm đếm: <strong class="text-slate-900">{{ totalReceivedQty + totalRejectedQty }}</strong> sản phẩm
        </div>
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
import { GOODS_RECEIPT_STATUS } from '../../../constants/purchase.constant';
import { useWarehouses } from '../../../composables/useWarehouses';

const route = useRoute();
const router = useRouter();
const toast = useToast();

const receiptId = computed(() => route.params.id);
const isEditMode = computed(() => !!receiptId.value);
const saving = ref(false);
const confirming = ref(false);
const loadingPos = ref(false);

const { warehouseOptions, fetchWarehouses } = useWarehouses();

const poOptions = ref([]);

const form = reactive({
  id: null,
  code: '',
  grn_number: '',
  purchase_order_id: null,
  po_code: '',
  po_number: '',
  warehouse_id: 1,
  delivery_note_number: '',
  received_by: 1,
  received_by_name: 'Thủ kho trưởng',
  status: 'Draft',
  notes: '',
  items: []
});

const isReadOnly = computed(() => {
  return isEditMode.value && String(form.status).toUpperCase() === 'CONFIRMED';
});

const hasOverReceiptError = computed(() => {
  return form.items.some(it => (Number(it.quantity_received || 0) + Number(it.quantity_rejected || 0)) > Number(it.quantity_remaining));
});

const totalReceivedQty = computed(() => {
  return form.items.reduce((sum, it) => sum + (Number(it.quantity_received) || 0), 0);
});

const totalRejectedQty = computed(() => {
  return form.items.reduce((sum, it) => sum + (Number(it.quantity_rejected) || 0), 0);
});

const totalRemainingQty = computed(() => {
  return form.items.reduce((sum, it) => sum + (Number(it.quantity_remaining) || 0), 0);
});

const closePoAfterReceipt = ref(false);

const canSubmit = computed(() => {
  return form.purchase_order_id && form.warehouse_id && (totalReceivedQty.value > 0 || totalRejectedQty.value > 0) && !hasOverReceiptError.value;
});

// Load danh sách đơn PO để chọn
const loadPurchaseOrders = async () => {
  loadingPos.value = true;
  try {
    const res = await purchaseApi.getOrders();
    const list = res?.data || res?.items || res || [];
    poOptions.value = list
      .filter(p => ['APPROVED', 'PARTIALLY_RECEIVED', 'APPROVED_BY_MANAGER'].includes(String(p.status).toUpperCase()))
      .map(p => ({
        label: `${p.code || 'PO #' + p.id} - ${p.notes || 'Đơn mua hàng'}`,
        value: p.id,
        warehouse_id: p.warehouse_id,
        code: p.code
      }));
  } catch (err) {
    console.error('Không thể tải danh sách PO:', err);
  } finally {
    loadingPos.value = false;
  }
};

const onPoSelected = async () => {
  if (!form.purchase_order_id) {
    form.items = [];
    return;
  }
  try {
    const res = await purchaseApi.getOrderById(form.purchase_order_id);
    const po = res?.data || res;
    if (po) {
      form.po_code = po.code;
      form.po_number = po.code;
      if (po.warehouse_id) {
        form.warehouse_id = po.warehouse_id;
      }
      form.items = (po.items || []).map(it => {
        const ordered = it.quantity_ordered || 0;
        const received = it.quantity_received || 0;
        const remaining = Math.max(0, ordered - received);
        return {
          purchase_order_item_id: it.id,
          product_id: it.product_id,
          sku: it.sku,
          product_name: it.product_name,
          unit: it.unit || 'Cái',
          quantity_ordered: ordered,
          quantity_received_before: received,
          quantity_remaining: remaining,
          quantity_received: remaining,
          quantity_rejected: 0,
          unit_cost: it.unit_cost || 0
        };
      });
    }
  } catch (err) {
    console.error('Lỗi khi tải chi tiết PO:', err);
    toast.add({
      severity: 'error',
      summary: 'Lỗi',
      detail: err.message || 'Không thể tải chi tiết đơn PO đã chọn',
      life: 4000
    });
  }
};

const loadReceipt = async (id) => {
  try {
    const res = await purchaseApi.getReceiptById(id);
    const data = res?.data || res;
    if (data) {
      Object.assign(form, {
        id: data.id,
        code: data.code,
        grn_number: data.code,
        purchase_order_id: data.purchase_order_id,
        po_code: data.po_code,
        po_number: data.po_code,
        warehouse_id: data.warehouse_id,
        delivery_note_number: data.delivery_note_number || '',
        received_by: data.received_by,
        received_by_name: typeof data.received_by === 'string' ? data.received_by : `Thủ kho #${data.received_by || 1}`,
        status: data.status,
        notes: data.notes || '',
        items: (data.items || []).map(i => ({
          purchase_order_item_id: i.purchase_order_item_id,
          product_id: i.product_id,
          sku: i.sku,
          product_name: i.product_name,
          unit: i.unit || 'Cái',
          quantity_ordered: i.quantity_ordered,
          quantity_received_before: 0,
          quantity_remaining: i.quantity_ordered,
          quantity_received: i.quantity_received,
          quantity_rejected: i.quantity_rejected || 0,
          unit_cost: i.unit_cost
        }))
      });
    }
  } catch (err) {
    console.error('Lỗi tải chi tiết phiếu nhập:', err);
    toast.add({
      severity: 'error',
      summary: 'Lỗi',
      detail: err.message || 'Không tìm thấy phiếu kiểm nhận nhập kho',
      life: 4000
    });
  }
};

const saveReceipt = async (andConfirm = false) => {
  if (hasOverReceiptError.value) {
    toast.add({ severity: 'error', summary: 'Không hợp lệ', detail: 'Tổng số lượng thực nhận và trả lại không được vượt quá số lượng còn lại của PO', life: 3500 });
    return;
  }
  if (!form.purchase_order_id || !form.warehouse_id) {
    toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Vui lòng chọn đơn PO và kho lưu trữ', life: 3000 });
    return;
  }
  const validItems = form.items.filter(it => (Number(it.quantity_received || 0) + Number(it.quantity_rejected || 0)) > 0);
  if (validItems.length === 0) {
    toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Vui lòng nhập số lượng thực nhận hoặc trả lại lớn hơn 0 cho ít nhất 1 mặt hàng', life: 3000 });
    return;
  }

  saving.value = true;
  try {
    const payload = {
      purchase_order_id: form.purchase_order_id,
      warehouse_id: form.warehouse_id,
      received_by: form.received_by || 1,
      delivery_note_number: form.delivery_note_number || null,
      notes: form.notes || null,
      items: validItems.map(it => ({
        purchase_order_item_id: it.purchase_order_item_id,
        product_id: it.product_id,
        sku: it.sku,
        product_name: it.product_name,
        unit: it.unit || 'Cái',
        quantity_received: Number(it.quantity_received || 0),
        quantity_rejected: Number(it.quantity_rejected || 0),
        unit_cost: it.unit_cost || 0
      }))
    };

    const res = await purchaseApi.createReceipt(payload);
    const created = res?.data || res;
    const newId = created?.id;

    if (andConfirm && newId) {
      try {
        const confirmRes = await purchaseApi.confirmReceipt(newId);
        const confirmedData = confirmRes?.data || confirmRes;

        if (confirmedData?.auto_purchase_return_code) {
          toast.add({
            severity: 'success',
            summary: 'Nhập kho & Tạo phiếu trả hàng',
            detail: `Nhập kho thành công ${totalReceivedQty.value} sản phẩm. Đã tự động tạo Phiếu trả hàng nháp #${confirmedData.auto_purchase_return_code} cho ${confirmedData.auto_purchase_return_qty || totalRejectedQty.value} sản phẩm trả lại.`,
            life: 6000
          });
        } else {
          toast.add({
            severity: 'success',
            summary: 'Đã nhập kho thành công',
            detail: `Nhập kho thành công ${totalReceivedQty.value} sản phẩm. Đã cập nhật tồn kho On-Hand qua Outbox!`,
            life: 4000
          });
        }

        if (closePoAfterReceipt.value && form.purchase_order_id) {
          try {
            await purchaseApi.closeOrder(form.purchase_order_id, {
              reason: 'Đóng đơn hoàn tất nhập kho theo yêu cầu thủ kho (không giao bù)'
            });
          } catch (closeErr) {
            console.warn('Không thể tự động đóng đơn PO:', closeErr);
          }
        }
      } catch (confirmErr) {
        toast.add({
          severity: 'warn',
          summary: 'Đã lưu phiếu nháp',
          detail: 'Phiếu đã tạo thành công nhưng chưa thể xác nhận ngay: ' + (confirmErr.message || ''),
          life: 4000
        });
      }
    } else {
      toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đã lưu bản nháp phiếu kiểm nhận nhập kho (GRN)', life: 3000 });
    }

    router.push('/purchase/receipts');
  } catch (err) {
    console.error('Lỗi tạo phiếu nhập kho:', err);
    toast.add({
      severity: 'error',
      summary: 'Thất bại',
      detail: err.message || 'Không thể tạo phiếu nhập kho',
      life: 4500
    });
  } finally {
    saving.value = false;
  }
};

const confirmExistingReceipt = async () => {
  if (!form.id) return;
  confirming.value = true;
  try {
    const res = await purchaseApi.confirmReceipt(form.id);
    const confirmedData = res?.data || res;
    form.status = 'Confirmed';

    if (confirmedData?.auto_purchase_return_code) {
      toast.add({
        severity: 'success',
        summary: 'Nhập kho & Tạo phiếu trả hàng',
        detail: `Nhập kho thành công ${totalReceivedQty.value} sản phẩm. Đã tự động tạo Phiếu trả hàng nháp #${confirmedData.auto_purchase_return_code} cho ${confirmedData.auto_purchase_return_qty || totalRejectedQty.value} sản phẩm trả lại.`,
        life: 6000
      });
    } else {
      toast.add({
        severity: 'success',
        summary: 'Thành công',
        detail: `Đã xác nhận nhập kho thành công ${totalReceivedQty.value} sản phẩm và cập nhật tồn kho tự động.`,
        life: 4000
      });
    }
    router.push('/purchase/receipts');
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Thất bại',
      detail: err.message || 'Không thể xác nhận phiếu nhập kho',
      life: 4000
    });
  } finally {
    confirming.value = false;
  }
};

const getStatusSeverity = (status) => {
  const upper = String(status || '').toUpperCase();
  return GOODS_RECEIPT_STATUS[upper]?.severity || 'secondary';
};

const getStatusLabel = (status) => {
  const upper = String(status || '').toUpperCase();
  return GOODS_RECEIPT_STATUS[upper]?.label || status;
};

onMounted(async () => {
  await fetchWarehouses();
  await loadPurchaseOrders();

  if (isEditMode.value) {
    loadReceipt(receiptId.value);
  } else {
    if (route.query.poId) {
      form.purchase_order_id = Number(route.query.poId);
      onPoSelected();
    }
  }
});
</script>
