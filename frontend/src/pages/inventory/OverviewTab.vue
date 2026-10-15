<template>
  <div class="flex flex-col gap-4">
    <!-- Header Toolbar -->
    <div class="flex items-center justify-between flex-wrap gap-3 pb-2 border-b border-slate-100">
      <div>
        <h3 class="text-base font-bold text-slate-800 m-0">Danh Sách Kho Hàng</h3>
        <p class="text-xs text-slate-500 m-0 mt-0.5">
          Quản lý các kho bãi và thiết lập kho được phép xuất bán hàng (tính vào tồn khả dụng bán).
        </p>
      </div>
      <div class="flex items-center gap-2">
        <Button
          label="Làm mới"
          icon="pi pi-refresh"
          size="small"
          text
          class="!rounded-[6px]"
          :loading="loading"
          @click="loadWarehouses"
        />
        <Button
          label="Thêm kho mới"
          icon="pi pi-plus"
          size="small"
          class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 !rounded-[6px]"
          @click="openCreateDialog"
        />
      </div>
    </div>

    <!-- Bảng Danh Sách Các Kho -->
    <CommonDataTable
      :value="warehouses"
      :columns="tableColumns"
      :loading="loading"
      :paginator="false"
      emptyIcon="pi pi-building"
      emptyTitle="Chưa có kho hàng nào"
      emptySubtitle="Nhấn 'Thêm kho mới' để tạo kho hàng đầu tiên."
    >
      <!-- ID -->
      <template #col-id="{ data }">
        <span class="font-mono text-xs font-bold text-slate-600">#{{ data.id }}</span>
      </template>

      <!-- Mã Kho -->
      <template #col-code="{ data }">
        <span class="font-mono text-xs font-bold px-2 py-0.5 bg-slate-100 text-slate-800 rounded-[4px] border border-slate-200">
          {{ data.code }}
        </span>
      </template>

      <!-- Tên Kho -->
      <template #col-name="{ data }">
        <div class="font-semibold text-slate-800 text-sm">{{ data.name }}</div>
        <div v-if="data.location" class="text-xs text-slate-500 mt-0.5">
          <i class="pi pi-map-marker text-[11px] text-slate-400 mr-1"></i>{{ data.location }}
        </div>
      </template>

      <!-- Tồn Thực Tế -->
      <template #col-total_on_hand="{ data }">
        <div class="text-right">
          <span class="font-mono text-sm font-bold text-slate-800">{{ formatNumber(data.total_on_hand) }}</span>
          <span class="text-xs text-slate-400 ml-1">chiếc</span>
        </div>
      </template>

      <!-- Tồn Khả Dụng -->
      <template #col-total_available="{ data }">
        <div class="text-right">
          <span class="font-mono text-sm font-bold text-emerald-600">{{ formatNumber(data.total_available) }}</span>
          <span class="text-xs text-emerald-600 ml-1">chiếc</span>
        </div>
      </template>

      <!-- Bán Hàng -->
      <template #col-can_sell="{ data }">
        <div class="flex items-center justify-center gap-2">
          <Checkbox
            :modelValue="Boolean(data.can_sell)"
            :binary="true"
            @update:modelValue="toggleCanSell(data)"
          />
          <span
            class="text-xs font-semibold cursor-pointer select-none"
            :class="data.can_sell ? 'text-emerald-700' : 'text-slate-400'"
            @click="toggleCanSell(data)"
          >
            {{ data.can_sell ? 'Được bán' : 'Không bán' }}
          </span>
        </div>
      </template>

      <!-- Trạng Thái -->
      <template #col-is_active="{ data }">
        <div class="text-center">
          <Tag
            :value="data.is_active ? 'Hoạt động' : 'Tạm khóa'"
            :severity="data.is_active ? 'success' : 'secondary'"
            class="!text-xs !rounded-[4px]"
          />
        </div>
      </template>

      <!-- Thao Tác -->
      <Column header="Thao Tác" class="text-center" style="width: 120px">
        <template #body="{ data }">
          <div class="flex items-center justify-center gap-1.5">
            <Button
              icon="pi pi-pencil"
              size="small"
              outlined
              severity="secondary"
              class="!w-7 !h-7 !p-0 !rounded-[6px]"
              v-tooltip.top="'Chỉnh sửa kho'"
              @click="openEditDialog(data)"
            />
            <Button
              icon="pi pi-trash"
              size="small"
              outlined
              severity="danger"
              class="!w-7 !h-7 !p-0 !rounded-[6px]"
              v-tooltip.top="'Xóa kho'"
              @click="confirmDelete(data)"
            />
          </div>
        </template>
      </Column>
    </CommonDataTable>

    <!-- Dialog Thêm Mới / Sửa Kho -->
    <Dialog
      v-model:visible="showDialog"
      :header="editingWarehouse ? 'Chỉnh Sửa Kho Hàng' : 'Thêm Mới Kho Hàng'"
      modal
      class="w-full max-w-lg"
    >
      <div class="flex flex-col gap-4 py-2">
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-bold text-slate-700">Mã kho <span class="text-rose-500">*</span></label>
          <InputText
            v-model="form.code"
            placeholder="VD: WH-MAIN, WH-HCM..."
            class="!text-xs font-mono !rounded-[6px]"
          />
        </div>

        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-bold text-slate-700">Tên kho hàng <span class="text-rose-500">*</span></label>
          <InputText
            v-model="form.name"
            placeholder="VD: Kho Chính, Kho Kĩ Thuật..."
            class="!text-xs !rounded-[6px]"
          />
        </div>

        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-bold text-slate-700">Vị trí / Địa chỉ</label>
          <InputText
            v-model="form.location"
            placeholder="Địa chỉ hoặc ghi chú vị trí kho..."
            class="!text-xs !rounded-[6px]"
          />
        </div>

        <div class="flex items-center justify-between p-3 bg-slate-50 border border-slate-200 rounded-[6px]">
          <div>
            <div class="text-xs font-bold text-slate-800">Sử dụng cho Bán Hàng</div>
            <div class="text-[11px] text-slate-500">Tồn khả dụng tại kho này sẽ được tính vào tổng tồn xuất bán</div>
          </div>
          <Checkbox v-model="form.can_sell" :binary="true" />
        </div>

        <div class="flex items-center justify-between p-3 bg-slate-50 border border-slate-200 rounded-[6px]">
          <div>
            <div class="text-xs font-bold text-slate-800">Trạng thái hoạt động</div>
            <div class="text-[11px] text-slate-500">Cho phép thực hiện các thao tác nhập xuất kho</div>
          </div>
          <Checkbox v-model="form.is_active" :binary="true" />
        </div>
      </div>

      <template #footer>
        <div class="flex justify-end gap-2">
          <Button label="Hủy" text severity="secondary" size="small" @click="showDialog = false" />
          <Button
            :label="editingWarehouse ? 'Lưu cập nhật' : 'Tạo mới'"
            size="small"
            class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600"
            :loading="submitting"
            @click="saveWarehouse"
          />
        </div>
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import Column from 'primevue/column'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Checkbox from 'primevue/checkbox'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'
import CommonDataTable from '../../components/common/CommonDataTable.vue'
import { inventoryApi } from '../../api/inventory.api'
import { formatNumber } from '../../utils/format'

const tableColumns = [
  { field: 'id', header: 'ID', width: '70px', sortable: true },
  { field: 'code', header: 'Mã Kho', width: '140px', sortable: true },
  { field: 'name', header: 'Tên Kho Hàng', width: 'auto', sortable: true },
  { field: 'total_on_hand', header: 'Tồn Thực Tế', width: '140px', align: 'right', sortable: true },
  { field: 'total_available', header: 'Tồn Khả Dụng', width: '140px', align: 'right', sortable: true },
  { field: 'can_sell', header: 'Bán Hàng', width: '160px', align: 'center', sortable: false },
  { field: 'is_active', header: 'Trạng Thái', width: '130px', align: 'center', sortable: true }
]

const warehouses = ref([])
const loading = ref(false)
const submitting = ref(false)
const showDialog = ref(false)
const editingWarehouse = ref(null)

const form = ref({
  code: '',
  name: '',
  location: '',
  can_sell: true,
  is_active: true
})

import { useWarehouses } from '../../composables/useWarehouses'

const { fetchWarehouses } = useWarehouses()

const loadWarehouses = async () => {
  loading.value = true
  try {
    const res = await inventoryApi.getWarehouses()
    warehouses.value = res.data || []
    fetchWarehouses(true)
  } catch (err) {
    console.error('Lỗi tải danh sách kho:', err)
  } finally {
    loading.value = false
  }
}

const openCreateDialog = () => {
  editingWarehouse.value = null
  form.value = {
    code: '',
    name: '',
    location: '',
    can_sell: true,
    is_active: true
  }
  showDialog.value = true
}

const openEditDialog = (wh) => {
  editingWarehouse.value = wh
  form.value = {
    code: wh.code,
    name: wh.name,
    location: wh.location || '',
    can_sell: Boolean(wh.can_sell),
    is_active: Boolean(wh.is_active)
  }
  showDialog.value = true
}

const toggleCanSell = async (wh) => {
  const newCanSell = !wh.can_sell
  wh.can_sell = newCanSell
  try {
    await inventoryApi.updateWarehouse(wh.id, {
      can_sell: newCanSell,
      name: wh.name,
      code: wh.code,
      location: wh.location,
      is_active: wh.is_active
    })
  } catch (err) {
    console.error('Lỗi cập nhật cấu hình bán hàng:', err)
    wh.can_sell = !newCanSell
  }
}

const saveWarehouse = async () => {
  if (!form.value.name.trim()) return
  submitting.value = true
  try {
    if (editingWarehouse.value) {
      await inventoryApi.updateWarehouse(editingWarehouse.value.id, form.value)
    } else {
      await inventoryApi.createWarehouse(form.value)
    }
    showDialog.value = false
    await loadWarehouses()
  } catch (err) {
    alert(err.message || 'Lỗi lưu thông tin kho')
  } finally {
    submitting.value = false
  }
}

const confirmDelete = async (wh) => {
  if (!confirm(`Bạn có chắc muốn xóa kho "${wh.name}"?`)) return
  try {
    await inventoryApi.deleteWarehouse(wh.id)
    await loadWarehouses()
  } catch (err) {
    alert(err.message || 'Lỗi xóa kho')
  }
}

onMounted(() => {
  loadWarehouses()
})
</script>
