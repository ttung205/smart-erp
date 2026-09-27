<template>
  <div class="catalog-page">
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
          @click="handleOpenCreateModal"
        />
      </template>
    </PageHeader>

    <!-- RBAC Status Notification -->
    <Message
      :closable="false"
      :severity="canCreateCatalog ? 'success' : 'warn'"
      class="rbac-message"
    >
      <div class="rbac-message-content">
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
    <Toolbar class="catalog-toolbar">
      <template #start>
        <IconField>
          <InputIcon class="pi pi-search" />
          <InputText
            v-model="searchQuery"
            placeholder="Tìm theo SKU hoặc tên sản phẩm..."
            class="search-input"
            size="small"
          />
        </IconField>
      </template>

      <template #end>
        <div class="toolbar-actions">
          <Button
            label="Làm mới"
            icon="pi pi-refresh"
            severity="secondary"
            outlined
            size="small"
            @click="$emit('refresh-products')"
          />
          <Tag
            severity="secondary"
            :value="filteredProducts.length + ' Sản phẩm'"
            class="count-tag"
          />
        </div>
      </template>
    </Toolbar>

    <!-- Products DataTable (Flat Panel Container) -->
    <div class="table-container">
      <DataTable
        :value="filteredProducts"
        class="clean-datatable"
        responsiveLayout="scroll"
        :paginator="filteredProducts.length > 10"
        :rows="10"
      >
        <Column field="sku" header="Mã SKU" :sortable="true">
          <template #body="{ data }">
            <span class="sku-badge">{{ data.sku }}</span>
          </template>
        </Column>

        <Column field="name" header="Tên Sản Phẩm" :sortable="true">
          <template #body="{ data }">
            <span class="font-semibold text-main">{{ data.name }}</span>
          </template>
        </Column>

        <Column field="category_name" header="Danh Mục">
          <template #body="{ data }">
            <Tag severity="secondary" :value="data.category_name || 'Công nghệ'" class="cell-tag" />
          </template>
        </Column>

        <Column field="unit_code" header="ĐVT">
          <template #body="{ data }">
            <span class="text-muted text-xs">{{ data.unit_code || 'PCS' }}</span>
          </template>
        </Column>

        <Column field="gross_price" header="Giá Niêm Yết" class="text-right" :sortable="true">
          <template #body="{ data }">
            <span class="font-semibold text-main num-tabular">{{ formatCurrency(data.gross_price) }}</span>
          </template>
        </Column>

        <Column field="cost_price" header="Giá Vốn" class="text-right" :sortable="true">
          <template #body="{ data }">
            <span class="text-muted num-tabular">{{ formatCurrency(data.cost_price) }}</span>
          </template>
        </Column>

        <Column field="min_stock" header="Tồn An Toàn" class="text-center" :sortable="true">
          <template #body="{ data }">
            <Tag severity="warn" :value="data.min_stock + ' cái'" class="cell-tag" />
          </template>
        </Column>

        <Column header="Trạng Thái" class="text-center">
          <template #body>
            <Tag severity="success" value="Đang kinh doanh" class="cell-tag" />
          </template>
        </Column>

        <template #empty>
          <div class="empty-table-state">
            <i class="pi pi-box empty-icon"></i>
            <p class="empty-title">Không tìm thấy sản phẩm nào</p>
            <p class="empty-subtitle">Thử thay đổi từ khóa tìm kiếm hoặc nhấn nút Tạo sản phẩm mới.</p>
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

<style scoped>
.catalog-page {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.rbac-message {
  border-radius: 6px;
}

.rbac-message-content {
  font-size: 13px;
  line-height: 1.4;
}

.catalog-toolbar {
  background-color: var(--surface-white);
  border: 1px solid var(--border-subtle);
  border-radius: 6px;
  padding: 8px 12px;
}

.search-input {
  width: 320px;
}

.toolbar-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.count-tag {
  font-size: 11px;
  padding: 4px 8px;
  border-radius: 6px;
}

.table-container {
  background-color: var(--surface-white);
  border: 1px solid var(--border-subtle);
  border-radius: 6px;
  overflow: hidden;
}

.sku-badge {
  font-family: var(--font-mono);
  font-size: 11px;
  font-weight: 600;
  color: var(--primary);
  background-color: var(--primary-subtle);
  padding: 2px 6px;
  border-radius: 6px;
}

.cell-tag {
  font-size: 11px;
  padding: 2px 6px;
  border-radius: 6px;
}

.empty-table-state {
  padding: 32px 16px;
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
}

.empty-icon {
  font-size: 28px;
  color: var(--text-dim);
}

.empty-title {
  font-size: 14px;
  font-weight: 600;
  color: var(--text-main);
  margin: 0;
}

.empty-subtitle {
  font-size: 12px;
  color: var(--text-muted);
  margin: 0;
}
</style>
