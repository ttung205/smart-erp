<template>
  <Dialog
    :visible="visible"
    modal
    :header="isEdit ? `Chỉnh Sửa Sản Phẩm: ${form.sku}` : 'Thêm Mới Sản Phẩm'"
    :style="{ width: '680px' }"
    :closable="!loading"
    class="rounded-[6px]"
    @update:visible="$emit('update:visible', $event)"
  >
    <div class="flex flex-col gap-3 pt-1 text-xs">
      <div class="grid grid-cols-2 gap-3">
        <!-- SKU -->
        <div class="flex flex-col gap-1">
          <label for="sku" class="font-semibold text-slate-700">Mã SKU <span class="text-red-600">*</span></label>
          <InputText
            id="sku"
            v-model="form.sku"
            placeholder="Ví dụ: XQ530-LG"
            class="w-full rounded-[6px] font-mono font-bold"
            size="small"
            :disabled="isEdit"
          />
        </div>

        <!-- Designation -->
        <div class="flex flex-col gap-1">
          <label for="designation" class="font-semibold text-slate-700">Designation (Quy cách)</label>
          <InputText
            id="designation"
            v-model="form.designation"
            placeholder="Ví dụ: XQ530"
            class="w-full rounded-[6px] font-mono text-rose-700"
            size="small"
          />
        </div>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <!-- Brand -->
        <div class="flex flex-col gap-1">
          <label for="brand" class="font-semibold text-slate-700">Thương hiệu (Brand)</label>
          <Select
            id="brand"
            v-model="form.brand_id"
            :options="brands"
            option-label="name"
            option-value="id"
            filter
            filter-placeholder="Tìm thương hiệu..."
            placeholder="Chọn thương hiệu..."
            :loading="loadingOptions"
            class="w-full rounded-[6px]"
            size="small"
          />
        </div>

        <!-- Category -->
        <div class="flex flex-col gap-1">
          <label for="category" class="font-semibold text-slate-700">Danh mục (Category)</label>
          <Select
            id="category"
            v-model="form.category_id"
            :options="categories"
            option-label="name"
            option-value="id"
            filter
            filter-placeholder="Tìm danh mục..."
            placeholder="Chọn danh mục..."
            :loading="loadingOptions"
            class="w-full rounded-[6px]"
            size="small"
          />
        </div>
      </div>

      <!-- Item Description -->
      <div class="flex flex-col gap-1">
        <label for="description" class="font-semibold text-slate-700">Mô tả sản phẩm (Item Description) <span class="text-red-600">*</span></label>
        <InputText
          id="description"
          v-model="form.description"
          placeholder="Ví dụ: Tủ lạnh LG Ngăn đá dưới 350 lít"
          class="w-full rounded-[6px]"
          size="small"
        />
      </div>

      <div class="grid grid-cols-3 gap-3">
        <!-- COM (Xuất xứ) -->
        <div class="flex flex-col gap-1">
          <label for="com" class="font-semibold text-slate-700">Xuất xứ (COM)</label>
          <InputText
            id="com"
            v-model="form.com"
            placeholder="Việt Nam"
            class="w-full rounded-[6px]"
            size="small"
          />
        </div>

        <!-- Unit -->
        <div class="flex flex-col gap-1">
          <label for="unit" class="font-semibold text-slate-700">Đơn vị tính (Unit)</label>
          <InputText
            id="unit"
            v-model="form.unit"
            placeholder="Cái"
            class="w-full rounded-[6px]"
            size="small"
          />
        </div>

        <!-- Gross Price -->
        <div class="flex flex-col gap-1">
          <label for="gross_price" class="font-semibold text-slate-700">Giá gross</label>
          <InputNumber
            id="gross_price"
            v-model="form.gross_price"
            class="w-full rounded-[6px]"
            size="small"
            currency="VND"
            locale="vi-VN"
          />
        </div>
      </div>

      <!-- Specs -->
      <div class="flex flex-col gap-1">
        <label for="spec" class="font-semibold text-slate-700">Thông số kỹ thuật (Specs)</label>
        <Textarea
          id="spec"
          v-model="form.spec"
          rows="2"
          placeholder="Dung tích: 350L; Inverter; Điện áp: 220V..."
          class="w-full rounded-[6px] text-xs font-mono"
        />
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end gap-2">
        <Button
          label="Hủy bỏ"
          icon="pi pi-times"
          text
          size="small"
          severity="secondary"
          :disabled="loading"
          class="rounded-[6px]"
          @click="$emit('update:visible', false)"
        />
        <Button
          :label="isEdit ? 'Lưu thay đổi' : 'Tạo sản phẩm'"
          icon="pi pi-check"
          size="small"
          :loading="loading"
          class="rounded-[6px] !bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600"
          @click="$emit('submit', form)"
        />
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import { ref, reactive, watch, computed } from 'vue';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import Button from 'primevue/button';
import { useToast } from 'primevue/usetoast';
import { catalogApi } from '../../api/catalog.api';

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  loading: {
    type: Boolean,
    default: false
  },
  initialData: {
    type: Object,
    default: () => ({})
  }
});

defineEmits(['update:visible', 'submit']);

const toast = useToast();
const isEdit = computed(() => Boolean(props.initialData && props.initialData.id));
const brands = ref([]);
const categories = ref([]);
const loadingOptions = ref(false);

const form = reactive({
  id: null,
  sku: '',
  designation: '',
  brand_id: null,
  category_id: null,
  description: '',
  com: 'Việt Nam',
  unit: 'Cái',
  gross_price: 0,
  spec: ''
});

watch(
  () => props.initialData,
  (val) => {
    if (val && Object.keys(val).length > 0) {
      Object.assign(form, {
        id: val.id || null,
        sku: val.sku || '',
        designation: val.designation || '',
        brand_id: val.brand_id ?? null,
        category_id: val.category_id ?? null,
        description: val.description || val.item_description || val.name || '',
        com: val.com || 'Việt Nam',
        unit: val.unit || 'Cái',
        gross_price: Number(val.gross_price) || 0,
        spec: val.spec || ''
      });
    } else {
      Object.assign(form, {
        id: null,
        sku: '',
        designation: '',
        brand_id: null,
        category_id: null,
        description: '',
        com: 'Việt Nam',
        unit: 'Cái',
        gross_price: 0,
        spec: ''
      });
    }
  },
  { immediate: true }
);

watch(() => props.visible, async (open) => {
  if (!open) return;
  loadingOptions.value = true;
  const [brandResult, categoryResult] = await Promise.allSettled([
    catalogApi.getBrands(),
    catalogApi.getCategories()
  ]);
  brands.value = brandResult.status === 'fulfilled' ? brandResult.value.data || [] : [];
  categories.value = categoryResult.status === 'fulfilled' ? categoryResult.value.data || [] : [];
  if (brandResult.status === 'rejected' || categoryResult.status === 'rejected') {
    toast.add({ severity: 'error', summary: 'Không tải được danh mục', detail: 'Vui lòng đóng và mở lại form để thử lại.', life: 3000 });
  }
  loadingOptions.value = false;
}, { immediate: true });
</script>
