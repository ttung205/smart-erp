<template>
  <div class="master-page">
    <!-- Single Page Title -->
    <PageHeader
      title="Đối Tác B2B & Hệ Thống Kho Bãi"
      subtitle="Quản lý hồ sơ khách hàng, nhà cung cấp, hạn mức công nợ và mạng lưới trung tâm lưu kho"
    />

    <!-- Sibling Panels Grid (Zero Nested Cards) -->
    <div class="master-grid">
      <!-- Panel 1: Partners Table -->
      <div class="master-panel">
        <div class="panel-header">
          <span class="panel-title">Danh sách đối tác B2B</span>
          <Tag
            severity="info"
            :value="partners.length + ' Đối tác'"
            class="panel-tag"
          />
        </div>

        <DataTable
          :value="partners"
          class="clean-datatable"
          responsiveLayout="scroll"
        >
          <Column field="code" header="Mã">
            <template #body="{ data }">
              <span class="code-badge">{{ data.code }}</span>
            </template>
          </Column>

          <Column field="name" header="Tên Đối Tác">
            <template #body="{ data }">
              <span class="font-semibold text-main">{{ data.name }}</span>
            </template>
          </Column>

          <Column field="type" header="Loại" class="text-center">
            <template #body="{ data }">
              <Tag
                :severity="data.type === 'CUSTOMER' ? 'info' : 'warn'"
                :value="data.type === 'CUSTOMER' ? 'Khách hàng' : 'Nhà cung cấp'"
                class="cell-tag"
              />
            </template>
          </Column>

          <Column field="credit_limit" header="Hạn Mức Nợ" class="text-right">
            <template #body="{ data }">
              <span class="font-semibold text-main num-tabular">{{ formatCurrency(data.credit_limit) }}</span>
            </template>
          </Column>

          <template #empty>
            <div class="empty-state">
              <p class="empty-text">Chưa có thông tin đối tác.</p>
            </div>
          </template>
        </DataTable>
      </div>

      <!-- Panel 2: Warehouses Table (Converted from nested cards to clean DataTable!) -->
      <div class="master-panel">
        <div class="panel-header">
          <span class="panel-title">Danh mục kho bãi & Điểm tập kết</span>
          <Tag
            severity="success"
            :value="warehouses.length + ' Kho hoạt động'"
            class="panel-tag"
          />
        </div>

        <DataTable
          :value="warehouses"
          class="clean-datatable"
          responsiveLayout="scroll"
        >
          <Column field="code" header="Mã Kho">
            <template #body="{ data }">
              <span class="code-badge">{{ data.code }}</span>
            </template>
          </Column>

          <Column field="name" header="Tên Kho Bãi">
            <template #body="{ data }">
              <span class="font-semibold text-main">{{ data.name }}</span>
            </template>
          </Column>

          <Column field="address" header="Địa Chỉ">
            <template #body="{ data }">
              <span class="text-muted text-xs">{{ data.address }}</span>
            </template>
          </Column>

          <Column header="Trạng Thái" class="text-center">
            <template #body>
              <Tag severity="success" value="Hoạt động" class="cell-tag" />
            </template>
          </Column>

          <template #empty>
            <div class="empty-state">
              <p class="empty-text">Chưa có thông tin kho bãi.</p>
            </div>
          </template>
        </DataTable>
      </div>
    </div>
  </div>
</template>

<script setup>
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import PageHeader from '../../components/common/PageHeader.vue';
import { formatCurrency } from '../../utils/format';

defineProps({
  partners: {
    type: Array,
    default: () => []
  },
  warehouses: {
    type: Array,
    default: () => []
  }
});
</script>

<style scoped>
.master-page {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.master-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.master-panel {
  background-color: var(--surface-white);
  border: 1px solid var(--border-subtle);
  border-radius: 6px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.panel-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  border-bottom: 1px solid var(--border-subtle);
  background-color: var(--canvas-bg);
}

.panel-title {
  font-size: 14px;
  font-weight: 700;
  color: var(--text-main);
}

.panel-tag {
  font-size: 11px;
  padding: 2px 8px;
  border-radius: 6px;
}

.code-badge {
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

.empty-state {
  padding: 24px;
  text-align: center;
}

.empty-text {
  font-size: 13px;
  color: var(--text-muted);
  margin: 0;
}
</style>
