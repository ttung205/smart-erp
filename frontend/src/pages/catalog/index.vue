<template>
  <div class="flex flex-col gap-4">
    <!-- Single Page Title -->
    <PageHeader
      title="Danh Mục Hàng Hóa & Sản Phẩm"
      subtitle="Quản lý mã hàng SKU, danh mục, bảng giá bán và định mức tồn kho tối thiểu"
    >
      <template #actions>
        <Button
          label="Tạo sản phẩm mới"
          icon="pi pi-plus"
          size="small"
          class="rounded-[6px]"
          @click="handleOpenCreateModal"
        />
      </template>
    </PageHeader>

    <!-- RBAC Status Notification -->
    <Message
      :closable="false"
      :severity="canCreateCatalog ? 'success' : 'warn'"
      class="rounded-[6px]"
    >
      <div class="text-[13px] leading-normal">
        <span class="font-semibold">Quyền hạn tác vụ: </span>
        <span v-if="canCreateCatalog">
          Tài khoản <strong>{{ currentUser ? currentUser.name : '' }}</strong> có quyền thêm mới sản phẩm (<code>catalog.create</code>).
        </span>
        <span v-else>
          Tài khoản <strong>{{ currentUser ? currentUser.name : 'Chưa đăng nhập' }}</strong> không có quyền tạo sản phẩm. Hệ thống sẽ trả về mã lỗi 403 Forbidden nếu gửi yêu cầu.
        </span>
      </div>
    </Message>

    <!-- Operational Toolbar -->
    <Toolbar class="bg-white border border-slate-200 rounded-[6px] px-3 py-2 shadow-none">
      <template #start>
        <IconField>
          <InputIcon class="pi pi-search" />
          <InputText
            v-model="searchQuery"
            placeholder="Tìm theo SKU hoặc tên sản phẩm..."
            class="w-80 rounded-[6px]"
            size="small"
          />
        </IconField>
      </template>

      <template #end>
        <div class="flex items-center gap-2">
          <Button
            label="Làm mới"
            icon="pi pi-refresh"
            severity="secondary"
            outlined
            size="small"
            class="rounded-[6px]"
            @click="$emit('refresh-products')"
          />
          <Tag
            severity="secondary"
            :value="filteredProducts.length + ' Sản phẩm'"
            class="text-[11px] px-2 py-1 rounded-[6px]"
          />
        </div>
      </template>
    </Toolbar>

    <!-- Products DataTable (Flat Panel Container) -->
    <div class="bg-white border border-slate-200 rounded-[6px] overflow-hidden shadow-none">
      <DataTable
        :value="filteredProducts"
        class="clean-datatable"
        responsiveLayout="scroll"
        :paginator="filteredProducts.length > 10"
        :rows="10"
      >
        <Column field="sku" header="Mã SKU" :sortable="true">
          <template #body="{ data }">
            <span class="font-mono text-[11px] font-semibold text-indigo-600 bg-indigo-50 px-1.5 py-0.5 rounded-[4px] border border-indigo-200">{{ data.sku }}</span>
          </template>
        </Column>

        <Column field="name" header="Tên Sản Phẩm" :sortable="true">
          <template #body="{ data }">
            <span class="font-semibold text-slate-900">{{ data.name }}</span>
          </template>
        </Column>

        <Column field="category_name" header="Danh Mục">
          <template #body="{ data }">
            <Tag severity="secondary" :value="data.category_name || 'Công nghệ'" class="text-[11px] px-1.5 py-0.5 rounded-[6px]" />
          </template>
        </Column>

        <Column field="unit_code" header="ĐVT">
          <template #body="{ data }">
            <span class="text-slate-500 text-xs">{{ data.unit_code || 'PCS' }}</span>
          </template>
        </Column>

        <Column field="gross_price" header="Giá Niêm Yết" class="text-right" :sortable="true">
          <template #body="{ data }">
            <span class="font-semibold text-slate-900 num-tabular">{{ formatCurrency(data.gross_price) }}</span>
          </template>
        </Column>

        <Column field="cost_price" header="Giá Vốn" class="text-right" :sortable="true">
          <template #body="{ data }">
            <span class="text-slate-500 num-tabular">{{ formatCurrency(data.cost_price) }}</span>
          </template>
        </Column>

        <Column field="min_stock" header="Tồn An Toàn" class="text-center" :sortable="true">
          <template #body="{ data }">
            <Tag severity="warn" :value="data.min_stock + ' cái'" class="text-[11px] px-1.5 py-0.5 rounded-[6px]" />
          </template>
        </Column>

        <Column header="Trạng Thái" class="text-center">
          <template #body>
            <Tag severity="success" value="Đang kinh doanh" class="text-[11px] px-1.5 py-0.5 rounded-[6px]" />
          </template>
        </Column>

        <template #empty>
          <div class="py-8 px-4 text-center flex flex-col items-center gap-2">
            <i class="pi pi-box text-3xl text-slate-400"></i>
            <p class="text-sm font-semibold text-slate-900 m-0">Không tìm thấy sản phẩm nào</p>
            <p class="text-xs text-slate-500 m-0">Thử thay đổi từ khóa tìm kiếm hoặc nhấn nút Tạo sản phẩm mới.</p>
          </div>
        </template>
      </DataTable>
    </div>

    <!-- Product Form Modal -->
    <ProductForm
      v-model:visible="showCreateModal"
      :loading="loadingCreate"
      :initial-data="initialFormData"
      @submit="$emit('create-product', $event)"
    />
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Toolbar from 'primevue/toolbar';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import Message from 'primevue/message';
import PageHeader from '../../components/common/PageHeader.vue';
import ProductForm from './form.vue';
import { formatCurrency } from '../../utils/format';

const props = defineProps({
  products: {
    type: Array,
    default: () => []
  },
  currentUser: {
    type: Object,
    default: null
  },
  loadingCreate: {
    type: Boolean,
    default: false
  }
});

defineEmits(['refresh-products', 'create-product']);

const searchQuery = ref('');
const showCreateModal = ref(false);
const initialFormData = ref(null);

const canCreateCatalog = computed(() => {
  return (
    props.currentUser &&
    props.currentUser.permissions &&
    props.currentUser.permissions.includes('catalog.create')
  );
});

const filteredProducts = computed(() => {
  if (!searchQuery.value) return props.products;
  const q = searchQuery.value.toLowerCase().trim();
  return props.products.filter(
    (p) =>
      (p.sku && p.sku.toLowerCase().includes(q)) ||
      (p.name && p.name.toLowerCase().includes(q))
  );
});

const handleOpenCreateModal = () => {
  const randomSuffix = Math.floor(100 + Math.random() * 900);
  initialFormData.value = {
    sku: `PROD-DELL-${randomSuffix}`,
    name: `Thiết Bị Laptop Model #${randomSuffix}`,
    gross_price: 24500000,
    cost_price: 21000000,
    min_stock: 12
  };
  showCreateModal.value = true;
};
</script>
