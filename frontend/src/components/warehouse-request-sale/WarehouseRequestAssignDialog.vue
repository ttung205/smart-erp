<template>
  <Dialog
    :visible="visible"
    @update:visible="$emit('update:visible', $event)"
    modal
    :header="`Phân Công Điều Phối Kho: ${invoice?.invoice_number || ''}`"
    :style="{ width: '560px' }"
    class="rounded-[8px]"
  >
    <div v-if="invoice" class="space-y-3.5 pt-1 text-xs">
      <p class="text-slate-600 m-0">
        Chỉ định nhân sự thực hiện toàn bộ khâu <strong>Soạn hàng</strong>, <strong>Đóng gói</strong> và <strong>Phương thức vận chuyển</strong> cho chứng từ <strong>{{ invoice.invoice_number }}</strong>.
      </p>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <!-- Soạn hàng (Role Logistic + filter) -->
        <div class="flex flex-col gap-1">
          <label class="font-semibold text-slate-700 flex items-center gap-1.5">
            <i class="pi pi-user text-amber-500"></i>
            <span>Nhân viên soạn hàng (Picker)</span>
          </label>
          <Select
            v-model="form.picker_id"
            :options="logisticEmployees"
            optionLabel="name"
            optionValue="id"
            :filter="true"
            filterPlaceholder="Tìm nhân viên..."
            placeholder="Chọn nhân viên soạn hàng"
            class="w-full text-xs rounded-[6px]"
            size="small"
          />
        </div>

        <!-- Đóng gói (Role Logistic + filter) -->
        <div class="flex flex-col gap-1">
          <label class="font-semibold text-slate-700 flex items-center gap-1.5">
            <i class="pi pi-box text-indigo-500"></i>
            <span>Người đóng gói (Packer)</span>
          </label>
          <Select
            v-model="form.packer_id"
            :options="logisticEmployees"
            optionLabel="name"
            optionValue="id"
            :filter="true"
            filterPlaceholder="Tìm nhân viên..."
            placeholder="Chọn người đóng gói"
            class="w-full text-xs rounded-[6px]"
            size="small"
          />
        </div>
      </div>

      <!-- Phương thức vận chuyển -->
      <div class="flex flex-col gap-1">
        <label class="font-semibold text-slate-700 flex items-center gap-1.5">
          <i class="pi pi-truck text-sky-500"></i>
          <span>Phương thức vận chuyển</span>
        </label>
        <Select
          v-model="form.shipping_method"
          :options="shippingMethodOptions"
          optionLabel="label"
          optionValue="value"
          placeholder="Chọn phương thức vận chuyển"
          class="w-full text-xs rounded-[6px]"
          size="small"
        />
      </div>

      <!-- Trường hợp 1: Kho giao -> Phân công người vận chuyển / giao hàng -->
      <div v-if="form.shipping_method === 'WAREHOUSE_DELIVERY'" class="flex flex-col gap-1 bg-sky-50/50 p-2.5 rounded-[6px] border border-sky-100">
        <label class="font-semibold text-sky-800 flex items-center gap-1.5">
          <i class="pi pi-user-plus text-sky-600"></i>
          <span>Người vận chuyển / Giao hàng (Kho phân công)</span>
        </label>
        <Select
          v-model="form.shipper_id"
          :options="logisticEmployees"
          optionLabel="name"
          optionValue="id"
          :filter="true"
          filterPlaceholder="Tìm người giao hàng..."
          placeholder="Chọn nhân sự giao hàng của kho..."
          class="w-full text-xs rounded-[6px]"
          size="small"
        />
      </div>

      <!-- Trường hợp 2: Gửi vận chuyển -> Nhập đơn vị VC & Mã vận đơn / Ghi chú -->
      <div v-else-if="form.shipping_method === 'THIRD_PARTY_CARRIER'" class="space-y-2.5 bg-amber-50/40 p-2.5 rounded-[6px] border border-amber-100">
        <div class="flex flex-col gap-1">
          <label class="font-semibold text-slate-700 flex items-center gap-1.5">
            <i class="pi pi-building text-amber-600"></i>
            <span>Đơn vị vận chuyển (Hãng chuyển phát)</span>
          </label>
          <Select
            v-model="form.shipper_name"
            :options="carrierOptions"
            :editable="true"
            placeholder="Chọn hoặc nhập đơn vị VC (Viettel Post, GHTK, GHN...)"
            class="w-full text-xs rounded-[6px]"
            size="small"
          />
        </div>

        <div class="flex flex-col gap-1">
          <label class="font-semibold text-slate-700">Mã vận đơn / Ghi chú gửi hàng</label>
          <InputText
            v-model="form.shipping_note"
            placeholder="Ví dụ: Mã vận đơn VTP-888999, giao giờ hành chính"
            class="w-full text-xs rounded-[6px]"
            size="small"
          />
        </div>
      </div>

      <!-- Trường hợp 3: Khách tới lấy -> Không cần người giao hay mã vận đơn -->
      <div v-else-if="form.shipping_method === 'CUSTOMER_PICKUP'" class="bg-emerald-50/50 p-2.5 rounded-[6px] border border-emerald-100 flex items-center gap-2 text-emerald-800 text-[11px]">
        <i class="pi pi-check-circle text-emerald-600 text-sm"></i>
        <span>Khách hàng sẽ chủ động tới kho nhận hàng. Không cần phân người vận chuyển hay mã vận đơn.</span>
      </div>
    </div>
    <template #footer>
      <Button label="Hủy" text size="small" severity="secondary" @click="$emit('update:visible', false)" />
      <Button
        label="Xác Nhận Đã Xử Lý"
        icon="pi pi-check"
        size="small"
        class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 rounded-[6px] !text-xs !font-semibold"
        @click="$emit('submit', form)"
      />
    </template>
  </Dialog>
</template>

<script setup>
import Dialog from 'primevue/dialog';
import Select from 'primevue/select';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';

defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  invoice: {
    type: Object,
    default: null
  },
  form: {
    type: Object,
    default: () => ({})
  },
  logisticEmployees: {
    type: Array,
    default: () => []
  },
  shippingMethodOptions: {
    type: Array,
    default: () => []
  },
  carrierOptions: {
    type: Array,
    default: () => []
  }
});

defineEmits(['update:visible', 'submit']);
</script>
