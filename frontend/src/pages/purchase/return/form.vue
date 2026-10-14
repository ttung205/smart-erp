<template>
  <div class="flex flex-col gap-4">
    <!-- 1. Page Header Chuẩn -->
    <PageHeader
      :title="isEditMode ? `Phiếu Trả Hàng Mua #${form.code || returnId}` : 'Lập Phiếu Trả Hàng Mua (Return to Vendor - RTV)'"
      :subtitle="isEditMode ? 'Xem chi tiết biên bản xuất trả, quy trình phê duyệt và xác nhận xuất kho' : 'Lập đề xuất xuất trả hàng lỗi, hỏng hóc hoặc giao thừa cho Nhà cung cấp theo đơn mua PO'"
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
            @click="$router.push('/purchase/returns')"
          />

          <!-- Action buttons for existing return -->
          <template v-if="isEditMode">
            <!-- DRAFT: Gửi duyệt & Hủy -->
            <Button
              v-if="statusUpper === 'DRAFT'"
              label="Hủy Phiếu"
              icon="pi pi-times"
              size="small"
              outlined
              severity="danger"
              class="!rounded-[6px] !text-xs !py-1.5"
              :loading="actionLoading"
              @click="openCancelDialog"
            />
            <Button
              v-if="statusUpper === 'DRAFT'"
              label="Gửi Phê Duyệt"
              icon="pi pi-send"
              size="small"
              class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 !rounded-[6px] !text-xs !font-semibold !py-1.5"
              :loading="actionLoading"
              @click="submitReturn"
            />

            <!-- SUBMITTED: Phê duyệt & Từ chối -->
            <Button
              v-if="statusUpper === 'SUBMITTED'"
              label="Từ Chối / Hủy"
              icon="pi pi-times"
              size="small"
              outlined
              severity="danger"
              class="!rounded-[6px] !text-xs !py-1.5"
              :loading="actionLoading"
              @click="openCancelDialog"
            />
            <Button
              v-if="statusUpper === 'SUBMITTED'"
              label="Phê Duyệt Phiếu Trả"
              icon="pi pi-check"
              size="small"
              class="!bg-emerald-600 hover:!bg-emerald-700 !text-white !border-emerald-600 !rounded-[6px] !text-xs !font-semibold !py-1.5"
              :loading="actionLoading"
              @click="approveReturn"
            />

            <!-- APPROVED: Xác nhận xuất kho trả NCC -->
            <Button
              v-if="statusUpper === 'APPROVED'"
              label="Xác Nhận Xuất Kho Trả NCC"
              icon="pi pi-truck"
              size="small"
              class="!bg-amber-600 hover:!bg-amber-700 !text-white !border-amber-600 !rounded-[6px] !text-xs !font-semibold !py-1.5"
              :loading="actionLoading"
              @click="openDispatchConfirmDialog"
            />
          </template>

          <!-- Nút Lưu khi tạo mới -->
          <Button
            v-if="!isEditMode"
            label="Tạo & Lưu Phiếu Trả"
            icon="pi pi-check"
            size="small"
            class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 !rounded-[6px] !text-xs !font-semibold !py-1.5"
            :loading="saving"
            :disabled="!canSaveNew"
            @click="saveNewReturn"
          />
        </div>
      </template>
    </PageHeader>

    <!-- 2. Banners trạng thái hoàn tất / đã hủy -->
    <div
      v-if="statusUpper === 'DISPATCHED'"
      class="bg-emerald-50 border border-emerald-300 rounded-[6px] p-3 text-xs text-emerald-800 flex items-center justify-between"
    >
      <div class="flex items-center gap-2">
        <i class="pi pi-check-circle text-base text-emerald-600"></i>
        <span>
          <strong>Phiếu đã xuất trả thành công:</strong> Hàng hóa đã được trừ tồn kho vật lý và số lượng nhận trên đơn mua <strong>{{ form.po_code }}</strong> đã được bù trừ.
        </span>
      </div>
      <span v-if="form.dispatched_at" class="font-mono text-[11px] text-emerald-700">
        Xuất kho lúc: {{ formatDate(form.dispatched_at) }}
      </span>
    </div>

    <div
      v-if="statusUpper === 'CANCELLED'"
      class="bg-rose-50 border border-rose-300 rounded-[6px] p-3 text-xs text-rose-800 flex items-center gap-2"
    >
      <i class="pi pi-times-circle text-base text-rose-600"></i>
      <span>
        <strong>Phiếu trả hàng đã bị hủy.</strong> Các thay đổi hoặc đề xuất xuất trả không còn hiệu lực.
      </span>
    </div>

    <!-- 3. Thông Tin Phiếu Trả Hàng (Single Surface Flat Card) -->
    <div class="bg-white border border-slate-200 rounded-[6px] p-4 flex flex-col gap-4">
      <div class="flex items-center justify-between pb-3 border-b border-slate-200">
        <div class="flex items-center gap-2">
          <i class="pi pi-replay text-sky-600 text-sm"></i>
          <span class="text-xs font-bold text-slate-800 uppercase tracking-wide">Thông Tin Phiếu Trả Hàng Mua</span>
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

      <div class="grid grid-cols-4 gap-4">
        <!-- Đơn mua hàng PO tham chiếu -->
        <div class="flex flex-col gap-1">
          <label class="text-xs font-medium text-slate-700">Đơn mua hàng (PO) <span class="text-rose-500">*</span></label>
          <Select
            v-if="!isEditMode"
            v-model="form.purchase_order_id"
            :options="poOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Chọn đơn PO đã nhận hàng..."
            size="small"
            class="!rounded-[6px] !text-xs"
            :loading="loadingPos"
            @change="onPoSelected"
          />
          <InputText
            v-else
            :value="form.po_code || (form.purchase_order_id ? `PO #${form.purchase_order_id}` : '—')"
            disabled
            class="!bg-slate-50 !rounded-[6px] !text-xs !font-mono font-bold text-slate-800"
            size="small"
          />
        </div>

        <!-- Phiếu nhập kho GRN (tùy chọn) -->
        <div class="flex flex-col gap-1">
          <label class="text-xs font-medium text-slate-700">Phiếu kiểm nhận (GRN liên quan)</label>
          <Select
            v-if="!isEditMode"
            v-model="form.goods_receipt_id"
            :options="grnOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Tùy chọn GRN cụ thể..."
            size="small"
            class="!rounded-[6px] !text-xs"
            :disabled="!form.purchase_order_id"
          />
          <InputText
            v-else
            :value="form.goods_receipt_code || (form.goods_receipt_id ? `GRN #${form.goods_receipt_id}` : '— Không gắn GRN —')"
            disabled
            class="!bg-slate-50 !rounded-[6px] !text-xs font-mono text-slate-700"
            size="small"
          />
        </div>

        <!-- Nhà Cung Cấp -->
        <div class="flex flex-col gap-1">
          <label class="text-xs font-medium text-slate-700">Nhà cung cấp tiếp nhận</label>
          <InputText
            :value="supplierNameDisplay"
            disabled
            class="!bg-slate-50 !rounded-[6px] !text-xs font-medium text-slate-800"
            size="small"
          />
        </div>

        <!-- Kho Xuất Trả -->
        <div class="flex flex-col gap-1">
          <label class="text-xs font-medium text-slate-700">Kho xuất trả hàng</label>
          <Select
            v-if="!isEditMode"
            v-model="form.warehouse_id"
            :options="warehouseOptions"
            optionLabel="name"
            optionValue="id"
            placeholder="Chọn kho xuất..."
            size="small"
            class="!rounded-[6px] !text-xs"
          />
          <InputText
            v-else
            :value="warehouseNameDisplay"
            disabled
            class="!bg-slate-50 !rounded-[6px] !text-xs font-medium text-slate-800"
            size="small"
          />
        </div>

        <!-- Lý Do Trả Hàng -->
        <div class="flex flex-col gap-1">
          <label class="text-xs font-medium text-slate-700">Lý do xuất trả hàng <span class="text-rose-500">*</span></label>
          <Select
            v-if="!isReadOnly"
            v-model="form.reason"
            :options="PURCHASE_RETURN_REASONS"
            optionLabel="label"
            optionValue="value"
            placeholder="Chọn lý do chính..."
            size="small"
            class="!rounded-[6px] !text-xs"
          />
          <InputText
            v-else
            :value="getReasonLabel(form.reason)"
            disabled
            class="!bg-slate-50 !rounded-[6px] !text-xs font-medium text-slate-800"
            size="small"
          />
        </div>

        <!-- Số RMA (Return Merchandise Authorization) -->
        <div class="flex flex-col gap-1">
          <label class="text-xs font-medium text-slate-700">Mã RMA / Ủy nhiệm trả của NCC</label>
          <InputText
            v-model="form.rma_number"
            :disabled="isReadOnly"
            placeholder="Ví dụ: RMA-LG-88921"
            class="!rounded-[6px] !text-xs font-mono"
            :class="{ '!bg-slate-50': isReadOnly }"
            size="small"
          />
        </div>

        <!-- Ghi chú giải trình -->
        <div class="flex flex-col gap-1 col-span-2">
          <label class="text-xs font-medium text-slate-700">Ghi chú & Biên bản giải trình</label>
          <InputText
            v-model="form.notes"
            :disabled="isReadOnly"
            placeholder="Ghi chú chi tiết tình trạng hàng, số biên bản kiểm nghiệm kỹ thuật..."
            class="!rounded-[6px] !text-xs"
            :class="{ '!bg-slate-50': isReadOnly }"
            size="small"
          />
        </div>
      </div>
    </div>

    <!-- 4. Bảng Danh Sách Hàng Xuất Trả (Itemized Return Lines) -->
    <div class="bg-white border border-slate-200 rounded-[6px] overflow-hidden flex flex-col">
      <div class="px-4 py-3 border-b border-slate-200 bg-slate-50/70 flex items-center justify-between">
        <div class="flex items-center gap-2">
          <i class="pi pi-list text-slate-700 text-xs"></i>
          <span class="text-xs font-bold text-slate-800 uppercase tracking-wide">Chi Tiết Mặt Hàng Xuất Trả</span>
        </div>
        <span class="text-xs text-slate-500 font-medium">
          {{ form.items.length }} mặt hàng trong danh mục
        </span>
      </div>

      <!-- Không có PO nào được chọn -->
      <div v-if="!form.purchase_order_id && !isEditMode" class="p-8 text-center text-slate-400 text-xs">
        <i class="pi pi-arrow-up text-lg text-slate-300 block mb-2"></i>
        Vui lòng chọn đơn mua hàng (PO) ở trên để tải danh sách các mặt hàng đã nhập kho có thể xuất trả.
      </div>

      <!-- Bảng Mặt Hàng -->
      <div v-else class="overflow-x-auto">
        <table class="w-full text-xs text-left border-collapse">
          <thead>
            <tr class="bg-slate-100 border-b border-slate-200 text-slate-700 font-semibold">
              <th class="py-2.5 px-3 w-10 text-center">#</th>
              <th class="py-2.5 px-3 min-w-[240px]">Sản phẩm / SKU</th>
              <th class="py-2.5 px-3 w-20 text-center">ĐVT</th>
              <th class="py-2.5 px-3 w-28 text-right">SL Đã Nhận</th>
              <th class="py-2.5 px-3 w-36 text-right">SL Xuất Trả</th>
              <th class="py-2.5 px-3 w-32 text-right">Đơn Giá Mua</th>
              <th class="py-2.5 px-3 w-36 text-right">Thành Tiền Trả</th>
              <th class="py-2.5 px-3 min-w-[180px]">Chi Tiết Lỗi / Ghi Chú SKU</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr
              v-for="(item, idx) in form.items"
              :key="item.purchase_order_item_id || item.id || idx"
              class="hover:bg-slate-50/70 transition-colors"
              :class="{ 'bg-rose-50/40': item.quantity_returned > item.max_returnable }"
            >
              <!-- STT -->
              <td class="py-2.5 px-3 text-center text-slate-500 font-mono">{{ idx + 1 }}</td>

              <!-- Sản phẩm & SKU -->
              <td class="py-2.5 px-3">
                <div class="font-medium text-slate-900">{{ item.product_name }}</div>
                <div class="text-[11px] text-slate-500 font-mono">{{ item.sku }}</div>
              </td>

              <!-- ĐVT -->
              <td class="py-2.5 px-3 text-center text-slate-600 font-medium">{{ item.unit || 'Cái' }}</td>

              <!-- SL Đã Nhận -->
              <td class="py-2.5 px-3 text-right font-mono font-semibold text-slate-700">
                {{ item.max_returnable ?? item.quantity_received ?? '—' }}
              </td>

              <!-- SL Xuất Trả (InputNumber) -->
              <td class="py-2.5 px-3 text-right">
                <div v-if="!isReadOnly" class="flex flex-col items-end gap-1">
                  <InputNumber
                    v-model="item.quantity_returned"
                    :min="0"
                    :max="item.max_returnable ?? 99999"
                    :useGrouping="false"
                    class="w-28 text-right font-mono font-bold"
                    inputClass="text-right !text-xs !py-1 !px-2 font-mono font-bold !bg-white !border !border-slate-300 focus:!border-sky-500 !rounded-[6px] w-full"
                  />
                  <span
                    v-if="item.quantity_returned > item.max_returnable"
                    class="text-[10px] text-rose-600 font-medium"
                  >
                    Vượt SL nhận (max {{ item.max_returnable }})
                  </span>
                </div>
                <div v-else class="font-mono font-bold text-rose-600 text-sm">
                  {{ item.quantity_returned }}
                </div>
              </td>

              <!-- Đơn Giá Mua -->
              <td class="py-2.5 px-3 text-right font-mono text-slate-700">
                {{ formatCurrency(item.unit_cost || 0) }}
              </td>

              <!-- Thành Tiền Trả -->
              <td class="py-2.5 px-3 text-right font-mono font-bold text-rose-600">
                {{ formatCurrency((item.quantity_returned || 0) * (item.unit_cost || 0)) }}
              </td>

              <!-- Ghi Chú SKU -->
              <td class="py-2.5 px-3">
                <InputText
                  v-if="!isReadOnly"
                  v-model="item.reason_detail"
                  placeholder="Ghi chú chi tiết lỗi SKU này..."
                  class="w-full !rounded-[6px] !text-xs !py-1"
                  size="small"
                />
                <span v-else class="text-xs text-slate-600">
                  {{ item.reason_detail || '—' }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Tóm Tắt Tổng Giá Trị Trả Hàng (Summary Bar) -->
      <div class="p-4 bg-slate-50 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-4">
        <div class="text-xs text-slate-500">
          <i class="pi pi-info-circle text-sky-600 mr-1"></i>
          Khi xuất kho trả hàng, công nợ mua hàng với Nhà cung cấp sẽ được giảm trừ tương ứng với tổng giá trị hoàn trả.
        </div>

        <div class="flex items-center gap-6">
          <div class="text-right">
            <span class="text-xs text-slate-500 block">Tổng số lượng trả:</span>
            <span class="text-base font-bold font-mono text-slate-800">{{ totalQuantityReturned }}</span>
          </div>

          <div class="text-right">
            <span class="text-xs text-slate-500 block">Tổng giá trị hoàn trả:</span>
            <span class="text-lg font-bold font-mono text-rose-600">{{ formatCurrency(totalAmountReturned) }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Dialog Xác Nhận Xuất Kho Trả NCC (Dispatch Dialog) -->
    <Dialog
      v-model:visible="dispatchDialogVisible"
      modal
      header="Xác Nhận Xuất Kho Trả Hàng Cho NCC"
      :style="{ width: '480px' }"
      class="!rounded-[6px]"
    >
      <div class="flex flex-col gap-3 py-2 text-xs text-slate-700">
        <div class="p-3 bg-amber-50 border border-amber-300 rounded-[6px] flex items-start gap-3">
          <i class="pi pi-exclamation-triangle text-amber-600 text-base mt-0.5"></i>
          <div class="flex flex-col gap-1">
            <span class="font-bold text-amber-900">Cảnh báo tác động hệ thống</span>
            <span class="text-amber-800">
              Thao tác này sẽ ghi nhận hàng đã rời khỏi kho thực tế để bàn giao cho Nhà cung cấp. Hệ thống sẽ:
            </span>
            <ul class="list-disc list-inside text-amber-800 space-y-0.5 mt-1">
              <li>Phát sự kiện xuất giảm tồn kho thực tế ở <strong>{{ warehouseNameDisplay }}</strong>.</li>
              <li>Giảm trừ số lượng thực nhận trên Đơn mua hàng <strong>{{ form.po_code }}</strong>.</li>
              <li>Tạo dữ liệu bù trừ công nợ / hóa đơn điều chỉnh.</li>
            </ul>
          </div>
        </div>

        <p class="font-medium text-slate-800 mt-2">
          Bạn có chắc chắn muốn tiến hành xuất kho cho phiếu <strong>{{ form.code }}</strong> không?
        </p>
      </div>

      <template #footer>
        <div class="flex justify-end gap-2">
          <Button
            label="Hủy bỏ"
            text
            size="small"
            severity="secondary"
            class="!rounded-[6px] !text-xs"
            @click="dispatchDialogVisible = false"
          />
          <Button
            label="Xác Nhận Xuất Kho"
            icon="pi pi-truck"
            size="small"
            class="!bg-amber-600 hover:!bg-amber-700 !text-white !border-amber-600 !rounded-[6px] !text-xs !font-semibold"
            :loading="actionLoading"
            @click="dispatchReturn"
          />
        </div>
      </template>
    </Dialog>

    <!-- Dialog Hủy Phiếu Trả Hàng -->
    <Dialog
      v-model:visible="cancelDialogVisible"
      modal
      header="Hủy Phiếu Trả Hàng Mua"
      :style="{ width: '450px' }"
      class="!rounded-[6px]"
    >
      <div class="flex flex-col gap-3 py-2 text-xs text-slate-700">
        <p>Vui lòng nhập lý do hủy phiếu trả hàng <strong>{{ form.code || returnId }}</strong>:</p>
        <InputText
          v-model="cancelReason"
          placeholder="Nhập lý do hủy phiếu..."
          class="!rounded-[6px] !text-xs"
          size="small"
        />
      </div>

      <template #footer>
        <div class="flex justify-end gap-2">
          <Button
            label="Đóng"
            text
            size="small"
            severity="secondary"
            class="!rounded-[6px] !text-xs"
            @click="cancelDialogVisible = false"
          />
          <Button
            label="Xác Nhận Hủy"
            icon="pi pi-times"
            size="small"
            severity="danger"
            class="!rounded-[6px] !text-xs !font-semibold"
            :loading="actionLoading"
            @click="cancelReturn"
          />
        </div>
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import Dialog from 'primevue/dialog';

import PageHeader from '../../../components/common/PageHeader.vue';
import { purchaseApi } from '../../../api/purchase.api';
import {
  PURCHASE_RETURN_STATUS,
  PURCHASE_RETURN_REASONS,
  DEFAULT_SUPPLIERS
} from '../../../constants/purchase.constant';
import { useWarehouses } from '../../../composables/useWarehouses';
import { formatCurrency } from '../../../utils/format';

const route = useRoute();
const router = useRouter();
const toast = useToast();

const returnId = computed(() => route.params.id);
const isEditMode = computed(() => !!returnId.value);

const form = ref({
  code: '',
  purchase_order_id: null,
  po_code: '',
  goods_receipt_id: null,
  goods_receipt_code: '',
  supplier_id: null,
  warehouse_id: null,
  rma_number: '',
  reason: 'Defective',
  notes: '',
  status: 'Draft',
  items: []
});

const saving = ref(false);
const actionLoading = ref(false);
const loadingPos = ref(false);

const poList = ref([]);
const grnList = ref([]);
const { warehouses, fetchWarehouses } = useWarehouses();

const dispatchDialogVisible = ref(false);
const cancelDialogVisible = ref(false);
const cancelReason = ref('');

const statusUpper = computed(() => String(form.value.status || '').toUpperCase());
const isReadOnly = computed(() => isEditMode.value && statusUpper.value !== 'DRAFT');

const poOptions = computed(() => {
  return poList.value.map((po) => {
    const code = po.po_number || po.code || `PO #${po.id}`;
    return {
      label: `${code} — ${getSupplierName(po.supplier_id)}`,
      value: po.id,
      po
    };
  });
});

const grnOptions = computed(() => {
  return grnList.value.map((grn) => ({
    label: `${grn.code || grn.grn_number} (${formatDate(grn.created_at || grn.received_date)})`,
    value: grn.id
  }));
});

const warehouseOptions = computed(() => warehouses.value);

const supplierNameDisplay = computed(() => {
  if (!form.value.supplier_id) return '— Tự động điền theo PO —';
  return getSupplierName(form.value.supplier_id);
});

const warehouseNameDisplay = computed(() => {
  if (!form.value.warehouse_id) return '— Tự động điền theo PO —';
  return getWarehouseName(form.value.warehouse_id);
});

const totalQuantityReturned = computed(() => {
  return form.value.items.reduce((sum, item) => sum + (Number(item.quantity_returned) || 0), 0);
});

const totalAmountReturned = computed(() => {
  return form.value.items.reduce(
    (sum, item) => sum + (Number(item.quantity_returned) || 0) * (Number(item.unit_cost) || 0),
    0
  );
});

const canSaveNew = computed(() => {
  if (!form.value.purchase_order_id) return false;
  if (!form.value.reason) return false;
  if (totalQuantityReturned.value <= 0) return false;

  // No item exceeds received
  const hasOverReturn = form.value.items.some(
    (item) => Number(item.quantity_returned) > Number(item.max_returnable)
  );
  if (hasOverReturn) return false;

  return true;
});

function getSupplierName(supplierId) {
  const found = DEFAULT_SUPPLIERS.find((s) => s.id === Number(supplierId));
  return found ? found.name : `Nhà cung cấp #${supplierId}`;
}

function getWarehouseName(warehouseId) {
  const found = warehouses.value.find((w) => w.id === Number(warehouseId));
  return found ? found.name : `Kho #${warehouseId}`;
}

function getReasonLabel(reasonCode) {
  const found = PURCHASE_RETURN_REASONS.find((r) => r.value === reasonCode);
  return found ? found.label : reasonCode || 'Chưa xác định';
}

function getStatusSeverity(status) {
  const key = String(status || '').toUpperCase();
  return PURCHASE_RETURN_STATUS[key]?.severity || 'secondary';
}

function getStatusLabel(status) {
  const key = String(status || '').toUpperCase();
  return PURCHASE_RETURN_STATUS[key]?.label || status;
}

function formatDate(dateStr) {
  if (!dateStr) return '—';
  try {
    const d = new Date(dateStr);
    return d.toLocaleDateString('vi-VN', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric'
    });
  } catch {
    return dateStr;
  }
}

async function loadApprovedPOs() {
  loadingPos.value = true;
  try {
    const res = await purchaseApi.getOrders();
    const orders = res?.data || (Array.isArray(res) ? res : []);
    // Lọc các PO đã có hàng về (PARTIALLY_RECEIVED, RECEIVED, COMPLETED, hoặc có items.quantity_received > 0)
    poList.value = orders.filter((o) => {
      const st = String(o.status || '').toUpperCase();
      const fs = String(o.fulfillment_status || '').toUpperCase();
      const hasRcv = (o.items || []).some((i) => (i.quantity_received || i.QuantityReceived || 0) > 0);
      return (
        st === 'RECEIVED' ||
        st === 'COMPLETED' ||
        st === 'PARTIALLY_RECEIVED' ||
        fs === 'RECEIVED' ||
        fs === 'PARTIALLYRECEIVED' ||
        hasRcv
      );
    });
  } catch (err) {
    console.error('Lỗi tải danh sách PO:', err);
  } finally {
    loadingPos.value = false;
  }
}

async function onPoSelected() {
  const selectedPo = poList.value.find((p) => p.id === form.value.purchase_order_id);
  if (!selectedPo) return;

  form.value.po_code = selectedPo.po_number || selectedPo.code || `PO #${selectedPo.id}`;
  form.value.supplier_id = selectedPo.supplier_id;
  form.value.warehouse_id = selectedPo.warehouse_id || (warehouses.value[0]?.id ?? 1);

  // Tải chi tiết đơn PO để lấy đầy đủ items và quantity_received
  try {
    const detailRes = await purchaseApi.getOrderById(selectedPo.id);
    const poDetail = detailRes?.data || detailRes || selectedPo;

    const items = poDetail.items || selectedPo.items || [];
    form.value.items = items
      .filter((i) => (i.quantity_received || i.QuantityReceived || 0) > 0)
      .map((i) => {
        const received = i.quantity_received ?? i.QuantityReceived ?? 0;
        return {
          purchase_order_item_id: i.id,
          product_id: i.product_id || i.ProductId,
          sku: i.sku || i.Sku,
          product_name: i.product_name || i.ProductName,
          unit: i.unit || i.Unit || 'Cái',
          max_returnable: received,
          quantity_received: received,
          quantity_returned: 0,
          unit_cost: i.unit_price ?? i.UnitPrice ?? i.unit_cost ?? 0,
          reason_detail: ''
        };
      });

    // Tải các GRN tương ứng với PO này nếu có
    const grnRes = await purchaseApi.getReceipts({ purchaseOrderId: selectedPo.id });
    grnList.value = grnRes?.data || (Array.isArray(grnRes) ? grnRes : []);
  } catch (err) {
    console.error('Lỗi tải chi tiết PO:', err);
  }
}

async function loadReturnDetail() {
  if (!returnId.value) return;
  try {
    const res = await purchaseApi.getReturnById(returnId.value);
    const data = res?.data || res;
    if (data) {
      form.value = {
        id: data.id,
        code: data.code,
        purchase_order_id: data.purchase_order_id,
        po_code: data.po_code,
        goods_receipt_id: data.goods_receipt_id,
        goods_receipt_code: data.goods_receipt_code,
        supplier_id: data.supplier_id,
        warehouse_id: data.warehouse_id,
        rma_number: data.rma_number || '',
        reason: data.reason || 'Defective',
        notes: data.notes || '',
        status: data.status || 'Draft',
        dispatched_at: data.dispatched_at,
        items: (data.items || []).map((i) => ({
          ...i,
          max_returnable: i.quantity_returned, // Trong view mode
          quantity_received: i.quantity_returned
        }))
      };
    }
  } catch (err) {
    console.error('Lỗi tải chi tiết phiếu trả hàng:', err);
    toast.add({
      severity: 'error',
      summary: 'Lỗi',
      detail: 'Không thể tải chi tiết phiếu trả hàng',
      life: 3000
    });
  }
}

async function saveNewReturn() {
  if (!canSaveNew.value) return;
  saving.value = true;
  try {
    const returnItems = form.value.items
      .filter((i) => Number(i.quantity_returned) > 0)
      .map((i) => ({
        purchase_order_item_id: i.purchase_order_item_id,
        product_id: i.product_id,
        sku: i.sku,
        product_name: i.product_name,
        unit: i.unit || 'Cái',
        quantity_returned: Number(i.quantity_returned),
        unit_cost: Number(i.unit_cost) || 0,
        reason_detail: i.reason_detail || ''
      }));

    const payload = {
      purchase_order_id: form.value.purchase_order_id,
      goods_receipt_id: form.value.goods_receipt_id || null,
      supplier_id: form.value.supplier_id,
      warehouse_id: form.value.warehouse_id,
      rma_number: form.value.rma_number || null,
      reason: form.value.reason,
      notes: form.value.notes || null,
      created_by: 1,
      items: returnItems
    };

    const res = await purchaseApi.createReturn(payload);
    const created = res?.data || res;
    toast.add({
      severity: 'success',
      summary: 'Thành công',
      detail: `Đã tạo phiếu trả hàng #${created.code || created.id}`,
      life: 3000
    });
    router.push(`/purchase/returns/${created.id}`);
  } catch (err) {
    console.error('Lỗi tạo phiếu trả hàng:', err);
    toast.add({
      severity: 'error',
      summary: 'Lỗi',
      detail: err.message || 'Không thể tạo phiếu trả hàng',
      life: 3000
    });
  } finally {
    saving.value = false;
  }
}

async function submitReturn() {
  actionLoading.value = true;
  try {
    const res = await purchaseApi.submitReturn(returnId.value);
    const updated = res?.data || res;
    form.value.status = updated.status || 'Submitted';
    toast.add({
      severity: 'success',
      summary: 'Thành công',
      detail: 'Đã gửi phiếu trả hàng phê duyệt',
      life: 3000
    });
  } catch (err) {
    console.error('Lỗi gửi duyệt:', err);
    toast.add({
      severity: 'error',
      summary: 'Lỗi',
      detail: err.message || 'Không thể gửi duyệt phiếu trả hàng',
      life: 3000
    });
  } finally {
    actionLoading.value = false;
  }
}

async function approveReturn() {
  actionLoading.value = true;
  try {
    const res = await purchaseApi.approveReturn(returnId.value);
    const updated = res?.data || res;
    form.value.status = updated.status || 'Approved';
    toast.add({
      severity: 'success',
      summary: 'Phê duyệt thành công',
      detail: 'Phiếu trả hàng đã được duyệt, sẵn sàng xuất kho',
      life: 3000
    });
  } catch (err) {
    console.error('Lỗi duyệt phiếu:', err);
    toast.add({
      severity: 'error',
      summary: 'Lỗi',
      detail: err.message || 'Không thể duyệt phiếu trả hàng',
      life: 3000
    });
  } finally {
    actionLoading.value = false;
  }
}

function openDispatchConfirmDialog() {
  dispatchDialogVisible.value = true;
}

async function dispatchReturn() {
  actionLoading.value = true;
  try {
    const res = await purchaseApi.dispatchReturn(returnId.value);
    const updated = res?.data || res;
    form.value.status = updated.status || 'Dispatched';
    form.value.dispatched_at = updated.dispatched_at || new Date().toISOString();
    dispatchDialogVisible.value = false;
    toast.add({
      severity: 'success',
      summary: 'Xuất kho thành công',
      detail: 'Đã xuất kho trả NCC & điều chỉnh tồn kho, PO tự động',
      life: 3000
    });
  } catch (err) {
    console.error('Lỗi xuất kho trả NCC:', err);
    toast.add({
      severity: 'error',
      summary: 'Lỗi',
      detail: err.message || 'Không thể xuất kho trả NCC',
      life: 3000
    });
  } finally {
    actionLoading.value = false;
  }
}

function openCancelDialog() {
  cancelReason.value = '';
  cancelDialogVisible.value = true;
}

async function cancelReturn() {
  actionLoading.value = true;
  try {
    const res = await purchaseApi.cancelReturn(returnId.value, cancelReason.value);
    const updated = res?.data || res;
    form.value.status = updated.status || 'Cancelled';
    cancelDialogVisible.value = false;
    toast.add({
      severity: 'warn',
      summary: 'Đã hủy',
      detail: 'Phiếu trả hàng mua đã bị hủy',
      life: 3000
    });
  } catch (err) {
    console.error('Lỗi hủy phiếu:', err);
    toast.add({
      severity: 'error',
      summary: 'Lỗi',
      detail: err.message || 'Không thể hủy phiếu trả hàng',
      life: 3000
    });
  } finally {
    actionLoading.value = false;
  }
}

onMounted(() => {
  fetchWarehouses();
  if (isEditMode.value) {
    loadReturnDetail();
  } else {
    loadApprovedPOs();
  }
});
</script>
