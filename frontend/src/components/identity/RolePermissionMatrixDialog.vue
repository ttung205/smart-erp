<template>
  <Dialog
    :visible="visible"
    @update:visible="$emit('update:visible', $event)"
    modal
    :header="`Phân Quyền Cho Vai Trò: ${role ? role.name : ''}`"
    :style="{ width: '720px' }"
  >
    <div v-if="role" class="py-2 flex flex-col gap-4 text-xs">
      <!-- Info Banner -->
      <div class="p-3 bg-sky-50 border border-sky-200 rounded-[6px] text-xs flex items-center justify-between">
        <div class="flex items-center gap-2">
          <i class="pi pi-shield text-sky-600"></i>
          <span class="text-slate-700">
            Mã vai trò: <strong class="font-mono text-sky-800">{{ role.code }}</strong>
          </span>
        </div>
        <div class="text-sky-700 font-semibold font-mono">
          Đã chọn: {{ selectedPermIds.length }} / {{ allPermissions.length }} quyền
        </div>
      </div>

      <!-- Modules Accordion / Groups -->
      <div class="max-h-[460px] overflow-y-auto space-y-4 pr-1">
        <div
          v-for="(modulePerms, moduleName) in permissionsByModule"
          :key="moduleName"
          class="border border-slate-200 rounded-[6px] overflow-hidden"
        >
          <!-- Module Header with Select All -->
          <div class="bg-slate-50 px-4 py-2.5 border-b border-slate-200 flex items-center justify-between">
            <span class="text-xs font-bold text-slate-800 uppercase tracking-wider">
              Phân hệ: {{ formatModuleName(moduleName) }}
            </span>
            <button
              type="button"
              class="text-xs text-sky-600 hover:text-sky-800 font-medium cursor-pointer"
              @click="toggleSelectModule(modulePerms)"
            >
              {{ isAllModuleSelected(modulePerms) ? 'Bỏ chọn cả nhóm' : 'Chọn tất cả nhóm' }}
            </button>
          </div>

          <!-- Permissions in module -->
          <div class="p-3 grid grid-cols-1 md:grid-cols-2 gap-2 bg-white">
            <div
              v-for="p in modulePerms"
              :key="p.id"
              class="flex items-start gap-2 p-2 rounded-[6px] border border-slate-100 hover:bg-slate-50 cursor-pointer transition-colors"
              @click="togglePerm(p.id)"
            >
              <Checkbox
                :modelValue="selectedPermIds.includes(p.id)"
                :binary="true"
                class="mt-0.5"
              />
              <div class="flex flex-col select-none">
                <span class="text-xs font-mono font-semibold text-slate-800">{{ p.code }}</span>
                <span class="text-[11px] text-slate-500 leading-tight">{{ p.description }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <template #footer>
      <div class="flex items-center justify-end gap-2 w-full pt-2">
        <Button
          label="Hủy Bỏ"
          text
          size="small"
          severity="secondary"
          class="!rounded-[6px]"
          @click="$emit('update:visible', false)"
        />
        <Button
          label="Cập Nhật Phân Quyền"
          size="small"
          :loading="loading"
          class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 !rounded-[6px]"
          @click="$emit('save', selectedPermIds)"
        />
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import Dialog from 'primevue/dialog';
import Checkbox from 'primevue/checkbox';
import Button from 'primevue/button';

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  role: {
    type: Object,
    default: null
  },
  selectedPermIds: {
    type: Array,
    default: () => []
  },
  allPermissions: {
    type: Array,
    default: () => []
  },
  permissionsByModule: {
    type: Object,
    default: () => ({})
  },
  loading: {
    type: Boolean,
    default: false
  }
});

const emit = defineEmits(['update:visible', 'update:selectedPermIds', 'save']);

const formatModuleName = (name) => {
  const map = {
    catalog: 'Danh Mục Sản Phẩm (Catalog)',
    customer: 'Khách Hàng (Customer)',
    quotation: 'Báo Giá (Quotation)',
    order: 'Đơn Hàng Bán (Order)',
    purchase: 'Mua Hàng (Purchase)',
    warehouse: 'Kho Vận & Soạn Hàng (Warehouse)',
    inventory: 'Tồn Kho & Thẻ Kho (Inventory)',
    delivery: 'Giao Hàng (Delivery)',
    invoice: 'Hóa Đơn & Tài Chính (Invoice)',
    accounting: 'Kế Toán Tổng Hợp (Accounting)',
    identity: 'Quản Trị Người Dùng & Phân Quyền (Identity)'
  };
  return map[name] || name?.toUpperCase();
};

const isAllModuleSelected = (perms) => {
  if (!perms || perms.length === 0) return false;
  return perms.every((p) => props.selectedPermIds.includes(p.id));
};

const toggleSelectModule = (perms) => {
  if (!perms) return;
  const current = [...props.selectedPermIds];
  const allSelected = isAllModuleSelected(perms);
  let updated;
  if (allSelected) {
    const idsToRemove = perms.map((p) => p.id);
    updated = current.filter((id) => !idsToRemove.includes(id));
  } else {
    const idsToAdd = perms.map((p) => p.id).filter((id) => !current.includes(id));
    updated = [...current, ...idsToAdd];
  }
  emit('update:selectedPermIds', updated);
};

const togglePerm = (permId) => {
  const current = [...props.selectedPermIds];
  const idx = current.indexOf(permId);
  if (idx > -1) {
    current.splice(idx, 1);
  } else {
    current.push(permId);
  }
  emit('update:selectedPermIds', current);
};
</script>
