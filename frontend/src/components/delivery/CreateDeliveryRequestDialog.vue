<template>
  <Dialog :visible="visible" modal
    :header="`Gửi Yêu Cầu Giao Hàng - Đơn Bán: ${order?.order_number || order?.customer_order_number || order?.id || ''}`"
    :style="{ width: '1080px' }" class="rounded-[8px]" :draggable="false"
    @update:visible="$emit('update:visible', $event)">
    <div v-if="order" class="flex flex-col gap-4 pt-1">
      <!-- 1. Thông tin tóm tắt đơn bán hàng -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-3 bg-slate-50 border border-slate-200 rounded-[6px] p-3 text-xs">
        <div>
          <span class="text-slate-500 block">Số đơn hàng:</span>
          <span class="font-bold text-slate-800 font-mono text-sm">{{ order.customer_order_number || order.order_number
            || order.id }}</span>
        </div>
        <div>
          <span class="text-slate-500 block">Khách hàng:</span>
          <span class="font-bold text-slate-800 truncate block">{{ order.customer_name }}</span>
        </div>
        <div>
          <span class="text-slate-500 block">Nhân viên kinh doanh:</span>
          <span class="font-semibold text-slate-700 truncate block">{{ order.salesperson || order.created_by_name ||
            'Nhân viên KD' }}</span>
        </div>
      </div>

      <!-- 2. Thông tin điều phối giao nhận -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-3 bg-white border border-slate-200 rounded-[6px] p-3 text-xs">
        <div class="flex flex-col gap-1.5">
          <label class="font-bold text-slate-700 uppercase tracking-wide text-[11px]">
            Kho xuất hàng <span class="text-red-500">*</span>
          </label>
          <Select v-model="form.warehouse_id" :options="warehouseOptions" optionLabel="label" optionValue="id"
            placeholder="Chọn kho xuất..." class="w-full text-xs rounded-[6px] h-9 flex items-center"
            @change="onWarehouseChange" />
        </div>

        <div class="flex flex-col gap-1.5">
          <label class="font-bold text-slate-700 uppercase tracking-wide text-[11px]">
            Hẹn ngày giao hàng <span class="text-red-500">*</span>
          </label>
          <DatePicker
            v-model="expectedDeliveryDateModel"
            dateFormat="dd/mm/yy"
            placeholder="dd/mm/yyyy"
            class="w-full text-xs"
            inputClass="!py-1.5 !px-3 text-xs font-mono rounded-[6px] h-9 border-slate-300"
          />
        </div>

        <div class="flex flex-col gap-1.5">
          <label class="font-bold text-slate-700 uppercase tracking-wide text-[11px]">
            Số điện thoại nhận hàng
          </label>
          <InputText v-model="form.contact_phone"
            class="w-full text-xs font-mono rounded-[6px] h-9 px-3 border-slate-300" placeholder="09xx xxx xxx" />
        </div>

        <!-- Địa chỉ giao hàng: Lấy từ đơn bán hàng, cho phép sửa -->
        <div class="col-span-1 md:col-span-3 flex flex-col gap-1.5 pt-2 border-t border-slate-100">
          <div class="flex items-center justify-between">
            <label class="font-bold text-slate-700 uppercase tracking-wide text-[11px] flex items-center gap-1">
              <i class="pi pi-map-marker text-rose-500"></i>
              <span>Địa chỉ giao hàng thực tế</span>
              <span class="text-[10px] text-slate-400 font-normal lowercase">(lấy từ đơn bán, có thể sửa khi giao kho
                khác)</span>
            </label>
            <span class="text-[10px] text-slate-500 italic">Kiểm tra kỹ trước khi gửi điều phối</span>
          </div>
          <InputText v-model="form.shipping_address"
            class="w-full text-xs rounded-[6px] h-9 px-3 border-slate-300 focus:!border-indigo-500"
            placeholder="Nhập địa chỉ nhận hàng của khách..." />
        </div>
      </div>

      <!-- 3. Bảng các mặt hàng yêu cầu giao -->
      <div class="border border-slate-200 rounded-[6px] overflow-hidden">
        <div class="bg-slate-50 px-3 py-2 border-b border-slate-200 flex items-center justify-between">
          <span class="text-xs font-bold text-slate-700 uppercase tracking-wide">
            Danh Sách Mặt Hàng Yêu Cầu Giao (Nhập số lượng giao đợt này)
          </span>
          <span class="text-[11px] text-slate-500">
            Tổng cộng: <strong class="text-slate-800">{{ orderItems.length }}</strong> mặt hàng
          </span>
        </div>

        <DataTable :value="orderItems" showGridlines responsiveLayout="scroll" class="p-datatable-sm text-xs">
          <Column header="#" style="width: 45px" class="text-center">
            <template #body="{ index }">
              <span class="font-mono text-slate-400 text-xs">{{ index + 1 }}</span>
            </template>
          </Column>

          <!-- 1. Mã nội bộ -->
          <Column field="sku" header="Mã Nội Bộ" style="width: 130px">
            <template #body="{ data }">
              <span
                class="font-mono text-xs font-bold text-sky-700 bg-sky-50 px-1.5 py-0.5 rounded border border-sky-200">
                {{ data.sku }}
              </span>
            </template>
          </Column>

          <!-- 2. Tên sản phẩm (Item description) -->
          <Column field="description" header="Tên Sản Phẩm">
            <template #body="{ data }">
              <div class="flex flex-col">
                <span class="text-xs font-semibold text-slate-800">{{ data.product_name || data.description }}</span>
                <span v-if="data.designation" class="text-[10px] text-slate-400 font-mono">{{ data.designation }}</span>
              </div>
            </template>
          </Column>

          <!-- Ngày hẹn giao theo order_item -->
          <Column header="Ngày Hẹn Giao" style="width: 120px" class="text-center">
            <template #body="{ data }">
              <span
                class="text-xs font-mono text-sky-700 font-semibold bg-sky-50 px-2 py-0.5 rounded border border-sky-200">
                {{ data.delivery_date ? formatDate(data.delivery_date) : 'Theo đơn' }}
              </span>
            </template>
          </Column>

          <!-- 3. Số lượng đặt -->
          <Column field="ordered_quantity" header="SL Đặt" style="width: 90px" class="text-center">
            <template #body="{ data }">
              <span class="font-mono text-xs font-bold text-blue-600">
                {{ data.ordered_quantity }}
              </span>
            </template>
          </Column>

          <!-- 4. Số lượng chưa giao -->
          <Column field="remaining_quantity" header="Chưa Giao" style="width: 100px" class="text-center">
            <template #body="{ data }">
              <span class="font-mono text-xs font-bold text-red-600">
                {{ data.remaining_quantity }}
              </span>
            </template>
          </Column>

          <!-- 5. Tồn kho khả dụng -->
          <Column field="stock" header="Tồn Kho" style="width: 100px" class="text-center">
            <template #body="{ data }">
              <span class="font-mono text-xs font-bold px-2 py-0.5 rounded border"
                :class="data.stock >= data.request_quantity ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-rose-50 text-rose-700 border-rose-200'">
                {{ data.stock }}
              </span>
            </template>
          </Column>

          <!-- 6. Số lượng yêu cầu (CHO SỬA SỐ) -->
          <Column header="SL Yêu Cầu (Sửa)" style="width: 140px" class="text-center">
            <template #body="{ data }">
              <div class="flex items-center justify-center">
                <InputNumber v-model="data.request_quantity" :min="0" :max="data.remaining_quantity"
                  class="w-24 text-center font-mono font-bold text-xs"
                  inputClass="!w-24 !text-center !text-xs !font-mono !font-bold !py-1 !px-2 rounded border border-indigo-300 focus:!border-indigo-600 bg-indigo-50/30 text-indigo-900" />
              </div>
            </template>
          </Column>
        </DataTable>
      </div>

      <!-- Tóm tắt số lượng -->
      <div
        class="flex items-center justify-between px-3 py-2 bg-slate-50 border border-slate-200 rounded-[6px] text-xs">
        <span class="text-slate-600">
          Tổng SL yêu cầu đợt này:
          <strong class="font-mono text-indigo-700 font-bold ml-1 text-sm">{{ totalRequestQuantity }}</strong> chiếc
        </span>
        <span v-if="hasZeroOrOverStockWarning" class="text-rose-600 text-[11px] font-medium flex items-center gap-1">
          <i class="pi pi-exclamation-triangle"></i>
          Lưu ý: Có mặt hàng có SL yêu cầu lớn hơn tồn kho khả dụng.
        </span>
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end gap-2 pt-2">
        <Button label="Hủy bỏ" icon="pi pi-times" text size="small" severity="secondary" class="rounded-[6px]"
          @click="$emit('update:visible', false)" />
        <Button label="Gửi yêu cầu giao" icon="pi pi-send" size="small"
          class="!bg-emerald-600 hover:!bg-emerald-700 !text-white !border-emerald-600 rounded-[6px] !font-semibold shadow-sm"
          :loading="submitting" :disabled="totalRequestQuantity <= 0" @click="submitDeliveryRequest" />
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import { ref, watch, computed } from 'vue';
import Dialog from 'primevue/dialog';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import DatePicker from 'primevue/datepicker';
import { useToast } from 'primevue/usetoast';
import { formatDate } from '../../utils/format';
import { salesApi } from '../../api/sales.api';
import { inventoryApi } from '../../api/inventory.api';
import { catalogApi } from '../../api/catalog.api';
import { FALLBACK_CATALOG_PRODUCTS } from '../../constants/catalogProducts.constant';

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  order: {
    type: Object,
    default: null
  },
  existingDeliveries: {
    type: Array,
    default: () => []
  }
});

const emit = defineEmits(['update:visible', 'created']);

const toast = useToast();
const submitting = ref(false);
const orderItems = ref([]);
const rawStocks = ref([]);
const pidToSkuMap = ref(new Map());
const skuToPidMap = ref(new Map());

const warehouseOptions = [
  { id: 1, name: 'Kho Tổng Miền Bắc (Hà Nội)', label: 'WH-HN-01: Kho Tổng Miền Bắc (Hà Nội)' },
  { id: 2, name: 'Kho Trung Tâm Miền Nam (TP.HCM)', label: 'WH-HCM-01: Kho Trung Tâm Miền Nam (TP.HCM)' },
  { id: 3, name: 'Kho Trung Chuyển Miền Trung (Đà Nẵng)', label: 'WH-DN-01: Kho Trung Chuyển Miền Trung (Đà Nẵng)' }
];

const form = ref({
  warehouse_id: 1,
  warehouse_name: 'Kho Tổng Miền Bắc (Hà Nội)',
  expected_delivery_date: '',
  contact_phone: '',
  shipping_address: ''
});

const expectedDeliveryDateModel = computed({
  get() {
    if (!form.value.expected_delivery_date) return null;
    const parts = String(form.value.expected_delivery_date).split('-');
    if (parts.length === 3) {
      return new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
    }
    return new Date(form.value.expected_delivery_date);
  },
  set(val) {
    if (val instanceof Date && !isNaN(val.getTime())) {
      const y = val.getFullYear();
      const m = String(val.getMonth() + 1).padStart(2, '0');
      const d = String(val.getDate()).padStart(2, '0');
      form.value.expected_delivery_date = `${y}-${m}-${d}`;
    } else {
      form.value.expected_delivery_date = val || '';
    }
  }
});

// Tải danh mục Catalog để ánh xạ ID <-> SKU
const loadCatalogMapping = async () => {
  const pMap = new Map();
  const sMap = new Map();

  // Khởi tạo từ Fallback trước
  FALLBACK_CATALOG_PRODUCTS.forEach(p => {
    const sku = (p.sku || '').trim().toUpperCase();
    const pid = Number(p.id);
    if (pid && sku) {
      pMap.set(pid, sku);
      sMap.set(sku, pid);
    }
  });

  try {
    const res = await catalogApi.getProducts({ limit: 500 });
    const list = res?.data || (Array.isArray(res) ? res : []);
    if (Array.isArray(list) && list.length > 0) {
      list.forEach(p => {
        const sku = (p.sku || p.code || '').trim().toUpperCase();
        const pid = Number(p.id);
        if (pid && sku) {
          pMap.set(pid, sku);
          sMap.set(sku, pid);
        }
      });
    }
  } catch (err) {
    console.warn('[CreateDeliveryRequestDialog] Dùng Fallback Catalog mapping:', err);
  }

  pidToSkuMap.value = pMap;
  skuToPidMap.value = sMap;
};

// Tải toàn bộ danh sách tồn kho từ Inventory API (:8004)
const loadStockMap = async () => {
  await loadCatalogMapping();
  try {
    const res = await inventoryApi.getStocks({ pageSize: 1000 });
    const list = res?.data || (Array.isArray(res) ? res : []);
    rawStocks.value = Array.isArray(list) ? list : [];
  } catch (err) {
    console.warn('Không thể tải tồn kho cho popup giao hàng:', err);
    rawStocks.value = [];
  }
};

// Tính toán tồn kho khả dụng cho 1 item dựa trên kho đang chọn
const getItemAvailableStock = (item, targetWarehouseId) => {
  const targetSku = (item.sku || '').trim().toUpperCase();
  const targetPid = Number(item.product_id || skuToPidMap.value.get(targetSku) || 0);

  const matchedStocks = rawStocks.value.filter(s => {
    const wid = Number(s.warehouse_id ?? s.warehouseId ?? 0);
    if (targetWarehouseId && wid !== Number(targetWarehouseId)) {
      return false;
    }
    const pid = Number(s.product_id ?? s.productId ?? 0);
    const sku = (s.sku || pidToSkuMap.value.get(pid) || '').trim().toUpperCase();
    return (targetPid && pid === targetPid) || (targetSku && sku === targetSku);
  });

  if (matchedStocks.length === 0) {
    // Nếu không lọc theo kho hoặc chưa tìm thấy, lấy tổng tồn của SKU đó
    const allWhStocks = rawStocks.value.filter(s => {
      const pid = Number(s.product_id ?? s.productId ?? 0);
      const sku = (s.sku || pidToSkuMap.value.get(pid) || '').trim().toUpperCase();
      return (targetPid && pid === targetPid) || (targetSku && sku === targetSku);
    });
    if (allWhStocks.length > 0) {
      return allWhStocks.reduce((sum, s) => {
        const avail = Number(s.available ?? (Number(s.quantity_on_hand || 0) - Number(s.quantity_reserved || 0)));
        return sum + avail;
      }, 0);
    }
    return 100; // Fallback an toàn nếu chưa có bản ghi kho
  }

  return matchedStocks.reduce((sum, s) => {
    const avail = Number(s.available ?? (Number(s.quantity_on_hand || 0) - Number(s.quantity_reserved || 0)));
    return sum + avail;
  }, 0);
};

// Cập nhật lại tồn kho hiển thị cho tất cả items
const updateOrderItemsStock = () => {
  if (!orderItems.value || orderItems.value.length === 0) return;
  orderItems.value.forEach(it => {
    it.stock = getItemAvailableStock(it, form.value.warehouse_id);
  });
};

watch(
  () => props.visible,
  async (newVal) => {
    if (newVal && props.order) {
      await loadStockMap();
      initItems();
    }
  }
);

const initItems = () => {
  const o = props.order;
  if (!o) return;

  // Lấy ngày giao dự kiến mặc định (hôm nay + 2 ngày)
  const targetDate = new Date();
  targetDate.setDate(targetDate.getDate() + 2);
  const yyyy = targetDate.getFullYear();
  const mm = String(targetDate.getMonth() + 1).padStart(2, '0');
  const dd = String(targetDate.getDate()).padStart(2, '0');

  form.value = {
    warehouse_id: 1,
    warehouse_name: 'Kho Tổng Miền Bắc (Hà Nội)',
    expected_delivery_date: `${yyyy}-${mm}-${dd}`,
    contact_phone: o.contact?.phone || o.contact_phone || '',
    shipping_address: o.shipping_address || o.customer?.address || ''
  };

  // Tính số lượng đã giao cho từng SKU từ các yêu cầu giao hàng trước đó của đơn này
  const deliveredMap = new Map();
  const orderDeliveries = (props.existingDeliveries || []).filter(
    (dr) => (dr.order_number === o.order_number || dr.order_id === o.id) && dr.status !== 'CANCELLED'
  );

  orderDeliveries.forEach((dr) => {
    (dr.items || []).forEach((it) => {
      const sku = (it.sku || it.product_sku || '').trim().toUpperCase();
      const qty = Number(it.quantity || 0);
      deliveredMap.set(sku, (deliveredMap.get(sku) || 0) + qty);
    });
  });

  orderItems.value = (o.items || []).map((it) => {
    const sku = (it.sku || '').trim().toUpperCase();
    const ordered = Number(it.quantity || 1);
    const delivered = deliveredMap.get(sku) || Number(it.delivered_quantity || 0);
    const remaining = Math.max(0, ordered - delivered);
    const pid = it.product_id || skuToPidMap.value.get(sku) || null;
    const itemObj = { ...it, sku, product_id: pid };
    const stock = getItemAvailableStock(itemObj, form.value.warehouse_id);

    return {
      order_item_id: it.id,
      product_id: pid,
      delivery_date: it.delivery_date || it.lead_time || '',
      sku: it.sku,
      product_name: it.product_name || it.description || 'Sản phẩm',
      description: it.description || it.product_name || '',
      designation: it.designation || '',
      ordered_quantity: ordered,
      delivered_quantity: delivered,
      remaining_quantity: remaining,
      stock: stock,
      request_quantity: remaining,
      unit_price: Number(it.selling_price || it.unit_price || 0)
    };
  });
};

const onWarehouseChange = () => {
  const w = warehouseOptions.find((item) => item.id === form.value.warehouse_id);
  if (w) form.value.warehouse_name = w.name;
  updateOrderItemsStock();
};

const totalRequestQuantity = computed(() => {
  return orderItems.value.reduce((sum, it) => sum + (Number(it.request_quantity) || 0), 0);
});

const hasZeroOrOverStockWarning = computed(() => {
  return orderItems.value.some((it) => it.request_quantity > it.stock);
});

const submitDeliveryRequest = async () => {
  const validItems = orderItems.value.filter((it) => Number(it.request_quantity) > 0);
  if (validItems.length === 0) {
    toast.add({
      severity: 'warn',
      summary: 'Thiếu số lượng giao',
      detail: 'Vui lòng nhập số lượng yêu cầu giao lớn hơn 0 cho ít nhất 1 mặt hàng.',
      life: 3000
    });
    return;
  }

  if (!form.value.shipping_address) {
    toast.add({
      severity: 'warn',
      summary: 'Thiếu địa chỉ nhận',
      detail: 'Vui lòng nhập địa chỉ giao hàng thực tế.',
      life: 3000
    });
    return;
  }

  submitting.value = true;
  try {
    const payload = {
      order_id: props.order.id,
      order_number: props.order.order_number || props.order.id,
      customer_order_number: props.order.customer_order_number || props.order.order_number,
      customer_name: props.order.customer_name,
      salesperson: props.order.salesperson || props.order.created_by_name || 'Nhân viên KD',
      warehouse_name: form.value.warehouse_name,
      shipping_address: form.value.shipping_address,
      contact_phone: form.value.contact_phone,
      expected_delivery_date: form.value.expected_delivery_date,
      items: validItems.map((it) => ({
        order_item_id: it.order_item_id,
        product_id: it.product_id,
        sku: it.sku,
        product_name: it.description || it.product_name,
        ordered_quantity: it.ordered_quantity,
        quantity: Number(it.request_quantity),
        delivered_quantity: 0,
        unit_price: it.unit_price,
        delivery_date: it.delivery_date
      }))
    };

    const res = await salesApi.createDelivery(payload);
    emit('created', res.data);
    emit('update:visible', false);
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi gửi yêu cầu giao hàng',
      detail: err.message || 'Không thể tạo yêu cầu giao hàng.',
      life: 3000
    });
  } finally {
    submitting.value = false;
  }
};
</script>
