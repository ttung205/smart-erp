<template>
  <div class="flex flex-col gap-4">
    <!-- 1. Page Header Chuẩn Theo UI_RULES.md -->
    <PageHeader
      :title="isEditMode ? `Đề Nghị Mua Sắm #${form.pr_number || prId}` : 'Tạo Đề Nghị Mua Sắm Mới'"
      :subtitle="isEditMode ? 'Xem chi tiết tiến trình phê duyệt và điều phối lập đơn đặt mua PO' : 'Lập phiếu đề xuất bổ sung thiết bị điện tử phục vụ kinh doanh hoặc dự phòng kho'"
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
            @click="$router.push('/purchase/requisitions')"
          />

          <!-- Nút Lưu Bản Nháp -->
          <Button
            v-if="!isEditMode || form.status === 'DRAFT'"
            label="Lưu bản nháp"
            icon="pi pi-save"
            size="small"
            outlined
            class="!border-slate-300 !text-slate-700 hover:!bg-slate-100 !rounded-[6px] !text-xs !py-1.5"
            :loading="saving"
            @click="saveRequisition(false)"
          />

          <!-- Nút Gửi Duyệt -->
          <Button
            v-if="!isEditMode || form.status === 'DRAFT'"
            label="Gửi duyệt ngay"
            icon="pi pi-send"
            size="small"
            class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 !rounded-[6px] !text-xs !font-semibold !py-1.5"
            :loading="saving"
            @click="saveRequisition(true)"
          />

          <!-- Nút Phê Duyệt (Dành cho cấp quản lý khi trạng thái là SUBMITTED) -->
          <Button
            v-if="isEditMode && form.status === 'SUBMITTED'"
            label="Phê duyệt PR"
            icon="pi pi-check-circle"
            size="small"
            class="!bg-emerald-600 hover:!bg-emerald-700 !text-white !border-emerald-600 !rounded-[6px] !text-xs !font-semibold !py-1.5"
            @click="openApprovalDialog"
          />

          <!-- Nút Từ Chối Duyệt -->
          <Button
            v-if="isEditMode && form.status === 'SUBMITTED'"
            label="Từ chối"
            icon="pi pi-times-circle"
            size="small"
            severity="danger"
            outlined
            class="!border-rose-300 !text-rose-600 hover:!bg-rose-50 !rounded-[6px] !text-xs !py-1.5"
            @click="showRejectDialog = true"
          />

          <!-- Nút Tạo PO nếu PR đã được duyệt -->
          <Button
            v-if="isEditMode && (form.status === 'APPROVED' || form.status === 'PARTIALLY_ORDERED')"
            label="Lập Đơn Đặt Mua (PO)"
            icon="pi pi-shopping-cart"
            size="small"
            class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 !rounded-[6px] !text-xs !font-semibold !py-1.5"
            @click="$router.push(`/purchase/orders/create?prId=${form.id}`)"
          />
        </div>
      </template>
    </PageHeader>

    <!-- 2. Thông Tin Chung Của Phiếu PR (Single Surface, Clean Flat Layout) -->
    <div class="bg-white border border-slate-200 rounded-[6px] p-4 flex flex-col gap-4">
      <div class="flex items-center justify-between pb-3 border-b border-slate-200">
        <div class="flex items-center gap-2">
          <i class="pi pi-info-circle text-sky-600 text-sm"></i>
          <span class="text-xs font-bold text-slate-800 uppercase tracking-wide">Thông Tin Chứng Từ</span>
        </div>
        <div v-if="isEditMode" class="flex items-center gap-2">
          <span class="text-xs text-slate-500">Trạng thái:</span>
          <span
            class="text-[11px] font-semibold px-2 py-0.5 rounded-[6px]"
            :class="getStatusClass(form.status)"
          >
            {{ getStatusLabel(form.status) }}
          </span>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Người Đề Nghị -->
        <div class="flex flex-col gap-1">
          <label class="text-xs font-medium text-slate-700">Người đề nghị <span class="text-rose-500">*</span></label>
          <InputText
            v-model="form.requester_name"
            placeholder="Họ tên nhân viên..."
            size="small"
            class="!rounded-[6px] !text-xs"
            :disabled="isReadOnly"
          />
        </div>

        <!-- Bộ Phận / Phòng Ban -->
        <div class="flex flex-col gap-1">
          <label class="text-xs font-medium text-slate-700">Bộ phận / Phòng ban <span class="text-rose-500">*</span></label>
          <InputText
            v-model="form.department"
            placeholder="Ví dụ: Bộ phận Kho Vận Hà Nội"
            size="small"
            class="!rounded-[6px] !text-xs"
            :disabled="isReadOnly"
          />
        </div>

        <!-- Kho Đích Cần Nhập Về -->
        <div class="flex flex-col gap-1">
          <label class="text-xs font-medium text-slate-700">Kho hàng tiếp nhận <span class="text-rose-500">*</span></label>
          <Select
            v-model="form.warehouse_id"
            :options="warehouseOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Chọn kho..."
            size="small"
            class="!rounded-[6px] !text-xs"
            :disabled="isReadOnly"
          />
        </div>

        <!-- Ngày Cần Hàng (Required Date) -->
        <div class="flex flex-col gap-1">
          <label class="text-xs font-medium text-slate-700">Ngày mong muốn nhận</label>
          <InputText
            v-model="form.required_date"
            type="date"
            size="small"
            class="!rounded-[6px] !text-xs"
            :disabled="isReadOnly"
          />
        </div>

        <!-- Lý Do / Ghi Chú Đề Nghị -->
        <div class="flex flex-col gap-1 md:col-span-2 lg:col-span-4">
          <label class="text-xs font-medium text-slate-700">Lý do đề nghị & Ghi chú mua hàng</label>
          <InputText
            v-model="form.notes"
            placeholder="Ví dụ: Bổ sung tồn kho Tivi & Tủ lạnh phục vụ mùa cao điểm cuối năm..."
            size="small"
            class="!rounded-[6px] !text-xs"
            :disabled="isReadOnly"
          />
        </div>
      </div>
    </div>

    <!-- 3. Danh Sách Chi Tiết Mặt Hàng Dùng Bắt Buộc ItemDataTable.vue Theo UI_RULES.md -->
    <div class="flex flex-col gap-1">
      <div class="flex items-center justify-between px-1">
        <span class="text-xs font-bold text-slate-800 uppercase tracking-wide flex items-center gap-1.5">
          <i class="pi pi-box text-sky-600 text-xs"></i>
          Danh Sách Mặt Hàng Yêu Cầu ({{ form.items.length }})
        </span>
      </div>

      <ItemDataTable
        v-model="form.items"
        :columns="requisitionColumns"
        :show-add-row="!isReadOnly"
        :show-catalog-search="!isReadOnly"
        :show-export="true"
        @summary-change="onSummaryChange"
      />
    </div>

    <!-- 4. Modal Phê Duyệt Phiếu Đề Nghị PR -->
    <Dialog
      v-model:visible="showApproveDialog"
      modal
      header="Phê Duyệt Đề Nghị Mua Sắm"
      :style="{ width: '650px' }"
      class="!rounded-[6px]"
    >
      <div class="flex flex-col gap-3 pt-1 text-xs">
        <p class="text-slate-600 m-0">
          Vui lòng kiểm tra và xác nhận số lượng duyệt mua cho từng mặt hàng của phiếu <strong class="text-slate-900 font-mono">{{ form.pr_number }}</strong>:
        </p>

        <div class="border border-slate-200 rounded-[6px] overflow-hidden">
          <table class="w-full text-xs">
            <thead class="bg-slate-50 border-b border-slate-200">
              <tr>
                <th class="p-2 text-left font-semibold text-slate-700">Mã SKU / Sản Phẩm</th>
                <th class="p-2 text-right font-semibold text-slate-700">SL Xin</th>
                <th class="p-2 text-right font-semibold text-slate-700 w-32">SL Duyệt</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(it, idx) in approvalLines" :key="idx" class="border-b border-slate-100 last:border-none">
                <td class="p-2">
                  <div class="font-mono font-semibold text-slate-800">{{ it.sku }}</div>
                  <div class="text-[11px] text-slate-500">{{ it.item_name }}</div>
                </td>
                <td class="p-2 text-right font-mono font-semibold text-slate-800">
                  {{ it.quantity_requested }}
                </td>
                <td class="p-2 text-right">
                  <InputNumber
                    v-model="it.quantity_approved"
                    :min="0"
                    :max="it.quantity_requested"
                    size="small"
                    class="w-24 !rounded-[6px] !text-xs"
                    inputClass="!text-right !font-mono font-bold !py-1"
                  />
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="flex flex-col gap-1">
          <label class="font-medium text-slate-700">Ý kiến phê duyệt:</label>
          <InputText
            v-model="approvalComments"
            placeholder="Đồng ý duyệt theo số lượng điều chỉnh..."
            size="small"
            class="!rounded-[6px] !text-xs"
          />
        </div>
      </div>

      <template #footer>
        <div class="flex justify-end gap-2">
          <Button
            label="Đóng"
            icon="pi pi-times"
            size="small"
            text
            severity="secondary"
            class="!rounded-[6px] !text-xs"
            @click="showApproveDialog = false"
          />
          <Button
            label="Xác nhận Phê duyệt"
            icon="pi pi-check"
            size="small"
            class="!bg-emerald-600 hover:!bg-emerald-700 !text-white !border-emerald-600 !rounded-[6px] !text-xs"
            :loading="approving"
            @click="confirmApprovePr"
          />
        </div>
      </template>
    </Dialog>

    <!-- 5. Modal Từ Chối Phê Duyệt -->
    <Dialog
      v-model:visible="showRejectDialog"
      modal
      header="Từ Chối Đề Nghị Mua Sắm"
      :style="{ width: '450px' }"
      class="!rounded-[6px]"
    >
      <div class="flex flex-col gap-3 pt-1 text-xs">
        <p class="text-slate-600 m-0">
          Vui lòng nhập lý do từ chối phê duyệt phiếu <strong class="text-slate-900 font-mono">{{ form.pr_number }}</strong>:
        </p>
        <InputText
          v-model="rejectReason"
          placeholder="Lý do từ chối (VD: Tồn kho còn đủ, vượt ngân sách quý...)"
          size="small"
          class="!rounded-[6px] !text-xs"
        />
      </div>

      <template #footer>
        <div class="flex justify-end gap-2">
          <Button
            label="Hủy bỏ"
            icon="pi pi-times"
            size="small"
            text
            severity="secondary"
            class="!rounded-[6px] !text-xs"
            @click="showRejectDialog = false"
          />
          <Button
            label="Xác nhận Từ chối"
            icon="pi pi-times-circle"
            size="small"
            severity="danger"
            class="!bg-rose-600 hover:!bg-rose-700 !text-white !border-rose-600 !rounded-[6px] !text-xs"
            :loading="rejecting"
            @click="confirmRejectPr"
          />
        </div>
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import Dialog from 'primevue/dialog';

import PageHeader from '../../../components/common/PageHeader.vue';
import ItemDataTable from '../../../components/common/ItemDataTable.vue';
import { purchaseApi } from '../../../api/purchase.api';
import { REQUISITION_STATUS, DEFAULT_WAREHOUSES } from '../../../constants/purchase.constant';

const route = useRoute();
const router = useRouter();
const toast = useToast();

const prId = computed(() => route.params.id);
const isEditMode = computed(() => !!prId.value);
const saving = ref(false);
const approving = ref(false);
const rejecting = ref(false);

const showApproveDialog = ref(false);
const showRejectDialog = ref(false);
const approvalLines = ref([]);
const approvalComments = ref('');
const rejectReason = ref('');

const warehouseOptions = DEFAULT_WAREHOUSES.map(w => ({ label: w.name, value: w.id }));

const requisitionColumns = [
  { field: 'sku', header: 'MÃ SKU', width: '120px' },
  { field: 'item_name', header: 'TÊN MẶT HÀNG / THIẾT BỊ', width: '220px' },
  { field: 'stock', header: 'TỒN KHO', width: '85px', align: 'center' },
  { field: 'unit', header: 'ĐVT', width: '65px', align: 'center' },
  { field: 'quantity', header: 'SL ĐỀ NGHỊ', width: '90px', align: 'right' },
  { field: 'unit_price', header: 'ĐƠN GIÁ DỰ KIẾN', width: '130px', align: 'right' },
  { field: 'subtotal', header: 'THÀNH TIỀN DỰ KIẾN', width: '135px', align: 'right' }
];

const form = reactive({
  id: null,
  pr_number: '',
  requester_name: 'Nguyễn Văn Hùng',
  department: 'Bộ phận Kho Vận Hà Nội',
  warehouse_id: 1,
  required_date: new Date(Date.now() + 7 * 86400000).toISOString().split('T')[0],
  status: 'DRAFT',
  notes: 'Bổ sung thiết bị phục vụ nhu cầu kinh doanh và dự phòng kho',
  items: [
    {
      id: 1,
      product_id: 99,
      sku: 'US131-PHI',
      item_name: 'Máy lọc không khí Philips 60 m²',
      quantity: 5,
      unit_price: 8910000,
      unit: 'Cái',
      discount: 0,
      vat: 10,
      stock: 57
    }
  ]
});

const isReadOnly = computed(() => {
  return isEditMode.value && form.status !== 'DRAFT';
});

const loadRequisition = async (id) => {
  try {
    const localList = JSON.parse(localStorage.getItem('smart_erp_mock_pr_list') || '[]');
    let data = localList.find(x => String(x.id) === String(id));

    try {
      const res = await purchaseApi.getRequisitionById(id);
      const apiData = res?.data || res;
      if (apiData && apiData.id) {
        data = apiData;
      }
    } catch (e) {
      console.warn('API getRequisitionById error, using local data:', e);
    }

    if (data) {
      Object.assign(form, {
        id: data.id,
        pr_number: data.pr_number || data.code || `PR-202610-${String(data.id).padStart(4, '0')}`,
        requester_name: data.requester_name || (data.requested_by ? `Nhân viên #${data.requested_by}` : 'Nguyễn Văn Hùng'),
        department: data.department || 'Bộ phận Kho Vận',
        warehouse_id: Number(data.warehouse_id || 1),
        required_date: data.expected_needed_date ? data.expected_needed_date.split('T')[0] : (data.required_date ? data.required_date.split('T')[0] : ''),
        status: (data.approval_status || data.status || 'DRAFT').toUpperCase(),
        notes: data.notes || '',
        items: (data.items || []).map(i => ({
          id: i.id,
          product_id: i.product_id || i.productId || i.id,
          sku: i.sku,
          item_name: i.product_name || i.item_name || i.name,
          quantity: i.quantity_requested || i.quantity || 1,
          quantity_approved: i.quantity_approved,
          unit_price: i.estimated_unit_cost || i.estimated_unit_price || i.unit_price || 0,
          unit: i.unit || 'Cái',
          discount: 0,
          vat: 10
        }))
      });
      return;
    }
  } catch (err) {
    console.warn('loadRequisition error:', err);
  }

  // Fallback nếu không có data
  form.id = id;
  form.pr_number = `PR-202610-000${id}`;
  form.status = id == 1 ? 'APPROVED' : 'SUBMITTED';
  form.items = [
    { id: 1, product_id: 1, sku: 'ELEC-TV-LG55', item_name: 'Smart Tivi LG 4K 55 inch OLED Cinema', quantity: 50, quantity_approved: 40, unit_price: 18500000, unit: 'Cái', discount: 0, vat: 10 },
    { id: 2, product_id: 2, sku: 'ELEC-FR-ELX341', item_name: 'Tủ Lạnh Electrolux Inverter 341L MultiDoor', quantity: 30, quantity_approved: 30, unit_price: 12200000, unit: 'Cái', discount: 0, vat: 10 }
  ];
};

const onSummaryChange = () => {
  // Synchronized via ItemDataTable
};

const saveRequisition = async (andSubmit = false) => {
  if (!form.requester_name || !form.warehouse_id) {
    toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Vui lòng nhập tên người đề nghị và chọn kho hàng', life: 3000 });
    return;
  }
  if (!form.items || form.items.length === 0) {
    toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Vui lòng thêm ít nhất 1 mặt hàng yêu cầu', life: 3000 });
    return;
  }

  saving.value = true;
  try {
    const targetStatus = andSubmit ? 'SUBMITTED' : 'DRAFT';
    const payload = {
      warehouse_id: Number(form.warehouse_id || 1),
      requested_by: 1,
      expected_needed_date: form.required_date ? new Date(form.required_date).toISOString() : new Date().toISOString(),
      priority: 'Medium',
      reason: form.notes || 'Bổ sung thiết bị cho kho',
      notes: form.notes || '',
      requester_name: form.requester_name,
      department: form.department,
      items: form.items.map((it, idx) => ({
        product_id: Number(it.product_id || it.productId || it.id || (idx + 1)),
        sku: it.sku || `SKU-PR-${idx + 1}`,
        product_name: it.item_name || it.product_name || 'Thiết bị đề nghị',
        unit: it.unit || 'Cái',
        quantity_requested: Number(it.quantity || it.quantity_requested || 1),
        estimated_unit_cost: Number(it.unit_price || it.estimated_unit_cost || 0),
        notes: it.notes || ''
      }))
    };

    let createdId = null;
    let createdCode = null;

    try {
      const res = await purchaseApi.createRequisition(payload);
      const created = res?.data || res;
      createdId = created?.id;
      createdCode = created?.code;

      if (andSubmit && createdId) {
        await purchaseApi.submitRequisition(createdId);
      }
    } catch (apiErr) {
      console.warn('API createRequisition warning, fallback to local storage:', apiErr);
    }

    const localId = createdId || Date.now();
    const localCode = createdCode || `PR-202610-${String(localId).slice(-4)}`;
    const newPr = {
      id: localId,
      pr_number: localCode,
      code: localCode,
      requester_name: form.requester_name,
      department: form.department,
      warehouse_id: Number(form.warehouse_id),
      status: targetStatus,
      approval_status: targetStatus,
      created_at: new Date().toISOString(),
      notes: form.notes,
      items_count: form.items.length,
      items: form.items.map(it => ({
        ...it,
        quantity_requested: Number(it.quantity || 1),
        quantity_approved: 0,
        estimated_unit_price: Number(it.unit_price || 0)
      }))
    };

    const existingLocal = JSON.parse(localStorage.getItem('smart_erp_mock_pr_list') || '[]');
    const updatedLocal = [newPr, ...existingLocal.filter(x => x.id !== localId)];
    localStorage.setItem('smart_erp_mock_pr_list', JSON.stringify(updatedLocal));

    toast.add({
      severity: 'success',
      summary: 'Thành công',
      detail: andSubmit ? 'Đã tạo và gửi duyệt PR thành công' : 'Đã lưu bản nháp PR thành công',
      life: 3000
    });

    router.push('/purchase/requisitions');
  } catch (err) {
    console.error('Error saving requisition:', err);
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể lưu đề nghị mua sắm', life: 3000 });
  } finally {
    saving.value = false;
  }
};

const updateLocalPrStatus = (status, extra = {}) => {
  const currentPrId = String(form.id || prId.value || '');
  const currentCode = form.pr_number || `PR-202610-${currentPrId.padStart(4, '0')}`;

  const localList = JSON.parse(localStorage.getItem('smart_erp_mock_pr_list') || '[]');
  const idx = localList.findIndex(x => String(x.id) === currentPrId || (currentCode && x.pr_number === currentCode));

  if (idx >= 0) {
    localList[idx].status = status;
    localList[idx].approval_status = status;
    Object.assign(localList[idx], extra);
    localStorage.setItem('smart_erp_mock_pr_list', JSON.stringify(localList));
  } else {
    const newPr = {
      id: currentPrId || Date.now(),
      pr_number: currentCode,
      code: currentCode,
      requester_name: form.requester_name,
      department: form.department,
      warehouse_id: Number(form.warehouse_id || 1),
      status: status,
      approval_status: status,
      created_at: new Date().toISOString(),
      notes: form.notes,
      items_count: form.items.length,
      items: form.items,
      ...extra
    };
    localList.unshift(newPr);
    localStorage.setItem('smart_erp_mock_pr_list', JSON.stringify(localList));
  }
};

const openApprovalDialog = () => {
  approvalLines.value = form.items.map(it => ({
    id: it.id,
    sku: it.sku,
    item_name: it.item_name,
    quantity_requested: it.quantity || it.quantity_requested || 1,
    quantity_approved: it.quantity_approved || it.quantity || 1
  }));
  approvalComments.value = '';
  showApproveDialog.value = true;
};

const confirmApprovePr = async () => {
  approving.value = true;
  const currentPrId = Number(form.id || prId.value || 1);
  try {
    const payload = {
      approved_by: 1,
      items: approvalLines.value.map(l => ({
        item_id: Number(l.id || 1),
        quantity_approved: Number(l.quantity_approved)
      }))
    };

    try {
      await purchaseApi.approveRequisition(currentPrId, payload);
    } catch (apiErr) {
      console.warn('API approveRequisition warning, saving locally:', apiErr);
    }

    form.status = 'APPROVED';
    showApproveDialog.value = false;

    // Cập nhật số lượng đã duyệt vào từng dòng mặt hàng
    form.items.forEach(it => {
      const found = approvalLines.value.find(l => l.id === it.id || l.sku === it.sku);
      if (found) {
        it.quantity_approved = Number(found.quantity_approved);
      }
    });

    // Cập nhật trạng thái vào localStorage ngay lập tức
    updateLocalPrStatus('APPROVED', {
      approval_notes: approvalComments.value,
      items: form.items
    });

    toast.add({ severity: 'success', summary: 'Phê duyệt thành công', detail: 'Đã duyệt đề nghị mua sắm PR', life: 3000 });
  } catch (err) {
    console.error('confirmApprovePr error:', err);
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể phê duyệt PR', life: 3000 });
  } finally {
    approving.value = false;
  }
};

const confirmRejectPr = async () => {
  if (!rejectReason.value) {
    toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Vui lòng nhập lý do từ chối', life: 3000 });
    return;
  }
  rejecting.value = true;
  const currentPrId = Number(form.id || prId.value || 1);
  try {
    const payload = {
      rejected_by: 1,
      reason: rejectReason.value
    };

    try {
      await purchaseApi.rejectRequisition(currentPrId, payload);
    } catch (apiErr) {
      console.warn('API rejectRequisition warning, saving locally:', apiErr);
    }

    form.status = 'REJECTED';
    showRejectDialog.value = false;

    // Cập nhật trạng thái vào localStorage ngay lập tức
    updateLocalPrStatus('REJECTED', {
      reject_reason: rejectReason.value
    });

    toast.add({ severity: 'info', summary: 'Từ chối duyệt', detail: 'Đã chuyển trạng thái từ chối PR', life: 3000 });
  } catch (err) {
    console.error('confirmRejectPr error:', err);
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể từ chối PR', life: 3000 });
  } finally {
    rejecting.value = false;
  }
};

const getStatusClass = (status) => {
  return REQUISITION_STATUS[status]?.class || 'bg-slate-100 text-slate-700 border border-slate-200';
};

const getStatusLabel = (status) => {
  return REQUISITION_STATUS[status]?.label || status;
};

onMounted(() => {
  if (isEditMode.value) {
    loadRequisition(prId.value);
  } else {
    // Check query params if redirected from StocksTab
    if (route.query.sku) {
      form.items.push({
        sku: route.query.sku,
        item_name: route.query.name || `Sản phẩm ${route.query.sku}`,
        quantity: Number(route.query.qty || 10),
        unit_price: 5000000,
        unit: 'Cái',
        discount: 0,
        vat: 10
      });
    }
    if (route.query.warehouseId) {
      form.warehouse_id = Number(route.query.warehouseId);
    }
  }
});
</script>
