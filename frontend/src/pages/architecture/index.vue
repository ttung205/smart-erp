<template>
  <div class="arch-page">
    <!-- Single Page Title -->
    <PageHeader
      title="Kiến Trúc Phân Tán & Transactional Outbox"
      subtitle="Đảm bảo tính nhất quán dữ liệu qua Domain Event và Message Broker (RabbitMQ) theo chuẩn CloudEvents"
    />

    <!-- 3 Architectural Pillars (Flat Panels) -->
    <div class="pillars-grid">
      <div class="pillar-card">
        <div class="pillar-step-badge">1</div>
        <div class="pillar-content">
          <span class="pillar-title">Atomic Database Write</span>
          <p class="pillar-desc">
            Khi phát sinh nghiệp vụ, lưu đồng thời bản ghi kinh doanh và Domain Event vào bảng <code>outbox_events</code> trong cùng một Database Transaction.
          </p>
        </div>
      </div>

      <div class="pillar-card">
        <div class="pillar-step-badge">2</div>
        <div class="pillar-content">
          <span class="pillar-title">Message Relay Worker</span>
          <p class="pillar-desc">
            Tiến trình nền (Background Worker) quét sự kiện <code>PENDING</code> và chuyển tiếp vào RabbitMQ Topic Exchange <code>erp.events</code>.
          </p>
        </div>
      </div>

      <div class="pillar-card">
        <div class="pillar-step-badge">3</div>
        <div class="pillar-content">
          <span class="pillar-title">Idempotent Consumer</span>
          <p class="pillar-desc">
            Các dịch vụ vệ tinh (như Kho, Kế toán) nhận message, kiểm tra bảng <code>processed_events</code> để đảm bảo xử lý chính xác một lần (Exactly-Once).
          </p>
        </div>
      </div>
    </div>

    <!-- CloudEvents Spec Viewer (Flat Container) -->
    <div class="spec-container">
      <div class="spec-header">
        <div class="spec-header-title">
          <i class="pi pi-code text-primary"></i>
          <span>Cấu trúc gói tin sự kiện mẫu (CloudEvents Spec v1.0)</span>
        </div>
        <Tag severity="info" value="RabbitMQ JSON" class="spec-tag" />
      </div>

      <pre class="json-code-block">{{ sampleCloudEvent }}</pre>
    </div>
  </div>
</template>

<script setup>
import Tag from 'primevue/tag';
import PageHeader from '../../components/common/PageHeader.vue';

const sampleCloudEvent = JSON.stringify(
  {
    specversion: '1.0',
    id: 'evt_4812a10b-8025-4c07-b35e-7a918e95089e',
    source: '/services/catalog-service',
    type: 'catalog.product_created',
    datacontenttype: 'application/json',
    time: '2026-09-27T02:40:00Z',
    correlation_id: 'corr_4a3b2c1d0e9f',
    data: {
      product_id: 108,
      sku: 'PROD-DELL-891',
      name: 'Laptop Dell Latitude 7420 Core i7',
      gross_price: 24500000,
      cost_price: 21000000,
      min_stock: 12
    }
  },
  null,
  2
);
</script>

<style scoped>
.arch-page {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.pillars-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
}

.pillar-card {
  background-color: var(--surface-white);
  border: 1px solid var(--border-subtle);
  border-radius: 6px;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.pillar-step-badge {
  width: 28px;
  height: 28px;
  border-radius: 6px;
  background-color: var(--primary);
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 13px;
}

.pillar-content {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.pillar-title {
  font-size: 14px;
  font-weight: 700;
  color: var(--text-main);
}

.pillar-desc {
  font-size: 12px;
  color: var(--text-muted);
  line-height: 1.5;
  margin: 0;
}

.pillar-desc code {
  background-color: var(--canvas-bg);
  border: 1px solid var(--border-subtle);
  padding: 1px 4px;
  border-radius: 4px;
  font-family: var(--font-mono);
  color: var(--primary);
  font-size: 11px;
}

.spec-container {
  background-color: var(--surface-white);
  border: 1px solid var(--border-subtle);
  border-radius: 6px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.spec-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  border-bottom: 1px solid var(--border-subtle);
  background-color: var(--canvas-bg);
}

.spec-header-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  font-weight: 600;
  color: var(--text-main);
}

.spec-tag {
  font-size: 11px;
  padding: 2px 8px;
  border-radius: 6px;
}

.json-code-block {
  margin: 0;
  padding: 16px;
  background-color: var(--surface-white);
  font-family: var(--font-mono);
  font-size: 12px;
  color: var(--text-body);
  line-height: 1.5;
  overflow-x: auto;
}
</style>
