<template>
  <div class="flex flex-col gap-4">
    <!-- Single Page Title -->
    <PageHeader
      title="Kiến Trúc Phân Tán & Transactional Outbox"
      subtitle="Đảm bảo tính nhất quán dữ liệu qua Domain Event và Message Broker (RabbitMQ) theo chuẩn CloudEvents"
    />

    <!-- 3 Architectural Pillars (100% Tailwind, Flat Panels) -->
    <div class="grid grid-cols-3 gap-4">
      <div class="bg-white border border-slate-200 rounded-[6px] p-4 flex flex-col gap-3 shadow-none">
        <div class="w-7 h-7 rounded-[6px] bg-indigo-600 text-white flex items-center justify-center font-bold text-[13px] shrink-0">1</div>
        <div class="flex flex-col gap-1.5">
          <span class="text-sm font-bold text-slate-900">Atomic Database Write</span>
          <p class="text-xs text-slate-500 leading-relaxed m-0">
            Khi phát sinh nghiệp vụ, lưu đồng thời bản ghi kinh doanh và Domain Event vào bảng <code class="bg-slate-50 border border-slate-200 px-1 py-0.5 rounded font-mono text-indigo-600 text-[11px]">outbox_events</code> trong cùng một Database Transaction.
          </p>
        </div>
      </div>

      <div class="bg-white border border-slate-200 rounded-[6px] p-4 flex flex-col gap-3 shadow-none">
        <div class="w-7 h-7 rounded-[6px] bg-indigo-600 text-white flex items-center justify-center font-bold text-[13px] shrink-0">2</div>
        <div class="flex flex-col gap-1.5">
          <span class="text-sm font-bold text-slate-900">Message Relay Worker</span>
          <p class="text-xs text-slate-500 leading-relaxed m-0">
            Tiến trình nền (Background Worker) quét sự kiện <code class="bg-slate-50 border border-slate-200 px-1 py-0.5 rounded font-mono text-indigo-600 text-[11px]">PENDING</code> và chuyển tiếp vào RabbitMQ Topic Exchange <code class="bg-slate-50 border border-slate-200 px-1 py-0.5 rounded font-mono text-indigo-600 text-[11px]">erp.events</code>.
          </p>
        </div>
      </div>

      <div class="bg-white border border-slate-200 rounded-[6px] p-4 flex flex-col gap-3 shadow-none">
        <div class="w-7 h-7 rounded-[6px] bg-indigo-600 text-white flex items-center justify-center font-bold text-[13px] shrink-0">3</div>
        <div class="flex flex-col gap-1.5">
          <span class="text-sm font-bold text-slate-900">Idempotent Consumer</span>
          <p class="text-xs text-slate-500 leading-relaxed m-0">
            Các dịch vụ vệ tinh (như Kho, Kế toán) nhận message, kiểm tra bảng <code class="bg-slate-50 border border-slate-200 px-1 py-0.5 rounded font-mono text-indigo-600 text-[11px]">processed_events</code> để đảm bảo xử lý chính xác một lần (Exactly-Once).
          </p>
        </div>
      </div>
    </div>

    <!-- CloudEvents Spec Viewer (Flat Container) -->
    <div class="bg-white border border-slate-200 rounded-[6px] overflow-hidden flex flex-col shadow-none">
      <div class="flex justify-between items-center px-4 py-3 border-b border-slate-200 bg-slate-50">
        <div class="flex items-center gap-2 text-[13px] font-semibold text-slate-900">
          <i class="pi pi-code text-indigo-600"></i>
          <span>Cấu trúc gói tin sự kiện mẫu (CloudEvents Spec v1.0)</span>
        </div>
        <Tag severity="info" value="RabbitMQ JSON" class="text-[11px] px-2 py-0.5 rounded-[6px]" />
      </div>

      <pre class="m-0 p-4 bg-white font-mono text-xs text-slate-700 leading-relaxed overflow-x-auto">{{ sampleCloudEvent }}</pre>
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
