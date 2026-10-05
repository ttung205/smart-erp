<template>
  <div class="space-y-3">
    <!-- 1. Header -->
    <PageHeader
      title="Trạng Thái Dịch Vụ Hệ Thống"
      subtitle="Giám sát thời gian thực tính sẵn sàng, cổng mạng, độ trễ và kết nối các microservices"
      badge="Health Monitor"
      badge-severity="info"
    >
      <template #actions>
        <div class="flex items-center gap-2">
          <!-- Auto-refresh interval dropdown -->
          <div class="flex items-center gap-1.5 bg-white px-2.5 py-1 rounded-[6px] border border-slate-200 text-xs text-slate-600 font-medium shadow-xs">
            <i class="pi pi-sync text-slate-400 text-xs" :class="{ 'animate-spin': autoRefreshInterval > 0 && isRefreshing }"></i>
            <span>Tự động:</span>
            <select
              v-model="autoRefreshInterval"
              class="bg-transparent text-xs font-semibold text-sky-700 outline-none cursor-pointer border-none p-0 focus:ring-0"
              @change="setupAutoRefresh"
            >
              <option :value="0">Tắt</option>
              <option :value="10">10 giây</option>
              <option :value="30">30 giây</option>
              <option :value="60">60 giây</option>
            </select>
          </div>

          <Button
            label="Kiểm tra lại toàn bộ"
            icon="pi pi-refresh"
            size="small"
            :loading="loadingAll"
            class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 !rounded-[6px]"
            @click="checkAllServices"
          />
        </div>
      </template>
    </PageHeader>

    <!-- 2. KPI Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
      <!-- Card 1: Tổng số dịch vụ -->
      <div class="bg-white border border-slate-200 rounded-[6px] p-3.5 flex items-center justify-between">
        <div class="flex flex-col">
          <span class="text-xs text-slate-500 font-medium">Tổng số dịch vụ</span>
          <span class="text-xl font-bold text-slate-900 mt-1 font-mono">{{ services.length }}</span>
        </div>
        <div class="w-9 h-9 rounded-[6px] bg-slate-100 text-slate-600 flex items-center justify-center">
          <i class="pi pi-server text-base"></i>
        </div>
      </div>

      <!-- Card 2: Dịch vụ trực tuyến -->
      <div class="bg-white border border-slate-200 rounded-[6px] p-3.5 flex items-center justify-between">
        <div class="flex flex-col">
          <span class="text-xs text-slate-500 font-medium">Đang hoạt động (Online)</span>
          <span class="text-xl font-bold text-emerald-600 mt-1 font-mono">{{ onlineCount }} / {{ services.length }}</span>
        </div>
        <div class="w-9 h-9 rounded-[6px] bg-emerald-50 text-emerald-600 border border-emerald-200 flex items-center justify-center">
          <i class="pi pi-check-circle text-base"></i>
        </div>
      </div>

      <!-- Card 3: Mất kết nối -->
      <div class="bg-white border border-slate-200 rounded-[6px] p-3.5 flex items-center justify-between">
        <div class="flex flex-col">
          <span class="text-xs text-slate-500 font-medium">Mất kết nối (Offline)</span>
          <span class="text-xl font-bold mt-1 font-mono" :class="offlineCount > 0 ? 'text-red-600' : 'text-slate-400'">
            {{ offlineCount }}
          </span>
        </div>
        <div class="w-9 h-9 rounded-[6px] flex items-center justify-center" :class="offlineCount > 0 ? 'bg-red-50 text-red-600 border border-red-200' : 'bg-slate-50 text-slate-400 border border-slate-200'">
          <i class="pi pi-exclamation-triangle text-base"></i>
        </div>
      </div>

      <!-- Card 4: Lần kiểm tra cuối -->
      <div class="bg-white border border-slate-200 rounded-[6px] p-3.5 flex items-center justify-between">
        <div class="flex flex-col">
          <span class="text-xs text-slate-500 font-medium">Cập nhật gần nhất</span>
          <span class="text-xs font-bold text-slate-700 mt-2 font-mono">{{ lastCheckedTime }}</span>
        </div>
        <div class="w-9 h-9 rounded-[6px] bg-sky-50 text-sky-600 border border-sky-200 flex items-center justify-center">
          <i class="pi pi-clock text-base"></i>
        </div>
      </div>
    </div>

    <!-- 3. DataTable Danh Sách Dịch Vụ -->
    <div class="bg-white border border-slate-200 rounded-[6px] overflow-hidden">
      <DataTable
        :value="services"
        showGridlines
        responsiveLayout="scroll"
      >
        <!-- Cột 1: Tên dịch vụ -->
        <Column header="Dịch Vụ (Service Name)" style="min-width: 220px">
          <template #body="{ data }">
            <div class="flex items-center gap-2.5">
              <div class="w-8 h-8 rounded-[6px] flex items-center justify-center shrink-0" :class="data.iconBg">
                <i :class="[data.icon, data.iconColor, 'text-sm']"></i>
              </div>
              <div class="flex flex-col">
                <span class="text-xs font-bold text-slate-900 leading-tight">{{ data.name }}</span>
                <span class="text-[11px] text-slate-500 font-mono">{{ data.id }}</span>
              </div>
            </div>
          </template>
        </Column>

        <!-- Cột 2: Công nghệ & Phân hệ -->
        <Column header="Công Nghệ & Nhiệm Vụ" style="min-width: 260px">
          <template #body="{ data }">
            <div class="flex flex-col gap-0.5">
              <div class="flex items-center gap-1.5 flex-wrap">
                <span class="text-xs font-semibold text-slate-800">{{ data.tech }}</span>
                <span class="text-[10px] px-1.5 py-0.2 rounded border bg-slate-50 text-slate-600 font-mono border-slate-200">{{ data.runtime }}</span>
                <span v-if="data.swagger" class="text-[10px] px-1.5 py-0.2 rounded border bg-emerald-50 text-emerald-700 font-mono border-emerald-200 font-bold">Swagger UI</span>
              </div>
              <span class="text-[11px] text-slate-500 leading-tight">{{ data.scope }}</span>
            </div>
          </template>
        </Column>

        <!-- Cột 3: Cổng mạng & URL Endpoint -->
        <Column header="Cổng & Endpoint" style="min-width: 200px">
          <template #body="{ data }">
            <div class="flex flex-col gap-1">
              <div class="flex items-center gap-1.5">
                <span class="font-mono text-xs font-bold text-sky-700 bg-sky-50 border border-sky-200 px-1.5 py-0.5 rounded-[4px]">
                  :{{ data.port }}
                </span>
                <span class="text-xs font-mono text-slate-600 truncate">{{ data.endpoint }}</span>
              </div>
              <div v-if="data.payload" class="text-[10px] text-slate-400 font-mono flex items-center gap-1.5 truncate">
                <span v-if="data.payload.framework" class="text-slate-500">{{ data.payload.framework }}</span>
                <span v-if="data.payload.version" class="text-slate-500">v{{ data.payload.version }}</span>
                <span v-if="data.payload.status" :class="data.online ? 'text-emerald-600 font-semibold' : 'text-red-500'">[{{ data.payload.status }}]</span>
              </div>
            </div>
          </template>
        </Column>

        <!-- Cột 4: Độ trễ phản hồi -->
        <Column header="Độ Trễ" style="width: 120px; text-align: center">
          <template #body="{ data }">
            <span v-if="data.online" class="font-mono text-xs font-bold text-slate-700">
              {{ data.latency }} ms
            </span>
            <span v-else class="text-xs text-slate-400 font-mono">—</span>
          </template>
        </Column>

        <!-- Cột 5: Trạng thái -->
        <Column header="Trạng Thái" style="width: 160px; text-align: center">
          <template #body="{ data }">
            <Tag
              :value="data.online ? 'Sẵn sàng (Online)' : 'Mất kết nối (Offline)'"
              :severity="data.online ? 'success' : 'danger'"
              class="!text-xs !rounded-[6px] !px-2.5 !py-1 font-medium"
            />
          </template>
        </Column>

        <!-- Cột 6: Hành động -->
        <Column header="Kiểm Tra" style="width: 100px; text-align: center">
          <template #body="{ data }">
            <div class="flex items-center justify-center gap-1">
              <Button
                icon="pi pi-refresh"
                size="small"
                text
                severity="secondary"
                :loading="data.loading"
                class="!w-7 !h-7 !p-0 !rounded-[6px]"
                v-tooltip.top="'Kiểm tra kết nối tới dịch vụ này'"
                @click="pingService(data)"
              />
            </div>
          </template>
        </Column>
      </DataTable>
    </div>

    <!-- 4. Hạ Tầng Bổ Trợ (Message Broker, Database & Gateway) -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
      <!-- Box 1: API Gateway Reverse Proxy -->
      <div class="bg-white border border-slate-200 rounded-[6px] p-3.5 flex flex-col gap-2">
        <div class="flex items-center justify-between pb-2 border-b border-slate-200">
          <div class="flex items-center gap-2">
            <div class="w-7 h-7 rounded-[4px] bg-emerald-50 border border-emerald-200 text-emerald-600 flex items-center justify-center font-bold text-xs">
              <i class="pi pi-compass"></i>
            </div>
            <div class="flex flex-col leading-tight">
              <span class="text-xs font-bold text-slate-900">API Gateway Nginx</span>
              <span class="text-[11px] text-slate-500 font-mono">Port :8000 • Reverse Proxy</span>
            </div>
          </div>
          <Tag severity="success" value="Active Proxy" class="!text-[10px] !rounded-[4px]" />
        </div>
        <div class="text-xs text-slate-600 leading-relaxed">
          Cổng điều phối tập trung, quản lý định tuyến HTTP, xử lý CORS và tự động đính kèm mã định danh vết <code>X-Correlation-ID</code>.
        </div>
        <div class="flex items-center gap-2 pt-1 text-[11px] text-slate-500 font-mono flex-wrap">
          <span>/api/v1/auth</span>
          <span>•</span>
          <span>/api/v1/catalog</span>
          <span>•</span>
          <span>/api/v1/purchase</span>
          <span>•</span>
          <span>/api/v1/inventory</span>
          <span>•</span>
          <span>/api/v1/sales</span>
        </div>
      </div>

      <!-- Box 2: RabbitMQ Message Broker -->
      <div class="bg-white border border-slate-200 rounded-[6px] p-3.5 flex flex-col gap-2">
        <div class="flex items-center justify-between pb-2 border-b border-slate-200">
          <div class="flex items-center gap-2">
            <div class="w-7 h-7 rounded-[4px] bg-orange-50 border border-orange-200 text-orange-600 flex items-center justify-center font-bold text-xs">
              <i class="pi pi-envelope"></i>
            </div>
            <div class="flex flex-col leading-tight">
              <span class="text-xs font-bold text-slate-900">RabbitMQ Message Broker</span>
              <span class="text-[11px] text-slate-500 font-mono">AMQP :5672 • Management :15672</span>
            </div>
          </div>
          <Tag severity="info" value="Event-Driven" class="!text-[10px] !rounded-[4px]" />
        </div>
        <div class="text-xs text-slate-600 leading-relaxed">
          Đảm nhận vai trò truyền tin phi đồng bộ cho sự kiện nghiệp vụ (Transactional Outbox) giữa các service độc lập.
        </div>
        <div class="flex items-center gap-2 pt-1 text-[11px] text-slate-500 font-mono flex-wrap">
          <span>Exchange: <strong>erp.events</strong></span>
          <span>•</span>
          <span>Queue: <strong>erp.*.events</strong></span>
        </div>
      </div>

      <!-- Box 3: Database Storage -->
      <div class="bg-white border border-slate-200 rounded-[6px] p-3.5 flex flex-col gap-2">
        <div class="flex items-center justify-between pb-2 border-b border-slate-200">
          <div class="flex items-center gap-2">
            <div class="w-7 h-7 rounded-[4px] bg-sky-50 border border-sky-200 text-sky-600 flex items-center justify-center font-bold text-xs">
              <i class="pi pi-database"></i>
            </div>
            <div class="flex flex-col leading-tight">
              <span class="text-xs font-bold text-slate-900">Database per Service</span>
              <span class="text-[11px] text-slate-500 font-mono">SQLite (Local) / MySQL 8.0</span>
            </div>
          </div>
          <Tag severity="success" value="Isolated Schemas" class="!text-[10px] !rounded-[4px]" />
        </div>
        <div class="text-xs text-slate-600 leading-relaxed">
          Mỗi microservice sở hữu cơ sở dữ liệu riêng biệt. Tuyệt đối không JOIN trực tiếp qua database của nhau.
        </div>
        <div class="flex items-center gap-1.5 pt-1 text-[10px] text-slate-500 font-mono flex-wrap">
          <span class="bg-slate-100 px-1 py-0.5 rounded">identity.sqlite</span>
          <span class="bg-slate-100 px-1 py-0.5 rounded">catalog.sqlite</span>
          <span class="bg-slate-100 px-1 py-0.5 rounded">purchase.db</span>
          <span class="bg-slate-100 px-1 py-0.5 rounded">inventory.db</span>
          <span class="bg-slate-100 px-1 py-0.5 rounded">sales.sqlite</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import PageHeader from '../../components/common/PageHeader.vue';

const loadingAll = ref(false);
const isRefreshing = ref(false);
const lastCheckedTime = ref('Đang kiểm tra...');
const autoRefreshInterval = ref(30);
let timerId = null;

const services = ref([
  {
    id: 'identity-service',
    name: 'Identity & Access Management',
    tech: 'Laravel 12 / 13 PHP',
    runtime: 'PHP 8.2+ / SQLite',
    port: 8001,
    endpoint: '/api/v1/auth/health',
    scope: 'Xác thực tài khoản, phiên JWT phi trạng thái, phân quyền nhân sự & phòng ban',
    icon: 'pi pi-shield',
    iconBg: 'bg-indigo-50 border border-indigo-200',
    iconColor: 'text-indigo-600',
    online: false,
    latency: 0,
    loading: false,
    payload: null
  },
  {
    id: 'catalog-service',
    name: 'Catalog Master Data Service',
    tech: 'Laravel 12 / 13 PHP',
    runtime: 'PHP 8.2+ / SQLite',
    port: 8002,
    endpoint: '/api/v1/catalog/health',
    scope: 'Quản lý mã SKU sản phẩm, danh mục hàng hóa, thương hiệu, đối tác & bảng giá',
    icon: 'pi pi-box',
    iconBg: 'bg-emerald-50 border border-emerald-200',
    iconColor: 'text-emerald-600',
    online: false,
    latency: 0,
    loading: false,
    payload: null
  },
  {
    id: 'purchase-service',
    name: 'Purchase & Procurement Engine',
    tech: '.NET 10 Web API',
    runtime: 'C# 13 / EF Core / SQLite',
    port: 8003,
    endpoint: '/api/v1/purchase/health',
    scope: 'Quản lý yêu cầu mua sắm (PR), đơn mua hàng (PO), nhập kho mua hàng (GRPO) & công nợ NCC',
    icon: 'pi pi-shopping-cart',
    iconBg: 'bg-violet-50 border border-violet-200',
    iconColor: 'text-violet-600',
    swagger: true,
    online: false,
    latency: 0,
    loading: false,
    payload: null
  },
  {
    id: 'inventory-service',
    name: 'Inventory & Stock Engine',
    tech: '.NET 10 Web API',
    runtime: 'C# 13 / EF Core / SQLite',
    port: 8004,
    endpoint: '/api/v1/inventory/health',
    scope: 'Quản lý thẻ kho thực tế On-Hand, giữ chỗ Reserved, Available và sổ cái biến động',
    icon: 'pi pi-server',
    iconBg: 'bg-sky-50 border border-sky-200',
    iconColor: 'text-sky-600',
    swagger: true,
    online: false,
    latency: 0,
    loading: false,
    payload: null
  },
  {
    id: 'sales-service',
    name: 'Sales & Distribution Service',
    tech: 'Laravel 12 / 13 PHP',
    runtime: 'PHP 8.2+ / SQLite',
    port: 8005,
    endpoint: '/api/v1/sales/health',
    scope: 'Quản lý báo giá (Quotation), đơn bán hàng (Sales Order), xuất kho bán lẻ & hóa đơn VAT',
    icon: 'pi pi-chart-line',
    iconBg: 'bg-rose-50 border border-rose-200',
    iconColor: 'text-rose-600',
    online: false,
    latency: 0,
    loading: false,
    payload: null
  },
  {
    id: 'frontend-service',
    name: 'Frontend Web Application',
    tech: 'Vue 3 + PrimeVue 4',
    runtime: 'Vite 6 / Tailwind CSS',
    port: 5173,
    endpoint: '/',
    scope: 'Giao diện bảng điều khiển ERP tập trung theo chuẩn kiến trúc phẳng SPA',
    icon: 'pi pi-desktop',
    iconBg: 'bg-amber-50 border border-amber-200',
    iconColor: 'text-amber-600',
    online: true,
    latency: 1,
    loading: false,
    payload: { status: 'READY', framework: 'Vue 3' }
  }
]);

const onlineCount = computed(() => services.value.filter(s => s.online).length);
const offlineCount = computed(() => services.value.filter(s => !s.online).length);

const pingService = async (service) => {
  service.loading = true;
  const start = performance.now();
  try {
    const res = await fetch(service.endpoint, {
      signal: AbortSignal.timeout(3000),
      cache: 'no-cache'
    });
    service.latency = Math.round(performance.now() - start);
    service.online = res.ok;
    if (res.ok) {
      try {
        const json = await res.json();
        service.payload = json.data || json;
      } catch {
        // Non-JSON response like frontend index HTML
        if (service.id === 'frontend-service') {
          service.payload = { status: 'READY', framework: 'Vue 3' };
        }
      }
    } else {
      service.payload = null;
    }
  } catch {
    service.online = false;
    service.latency = 0;
    service.payload = null;
  } finally {
    service.loading = false;
  }
};

const checkAllServices = async () => {
  loadingAll.value = true;
  isRefreshing.value = true;
  try {
    await Promise.all(services.value.map(s => pingService(s)));
    lastCheckedTime.value = new Date().toLocaleTimeString('vi-VN');
  } finally {
    loadingAll.value = false;
    setTimeout(() => {
      isRefreshing.value = false;
    }, 400);
  }
};

const setupAutoRefresh = () => {
  if (timerId) {
    clearInterval(timerId);
    timerId = null;
  }
  if (autoRefreshInterval.value > 0) {
    timerId = setInterval(() => {
      checkAllServices();
    }, autoRefreshInterval.value * 1000);
  }
};

onMounted(() => {
  checkAllServices();
  setupAutoRefresh();
});

onUnmounted(() => {
  if (timerId) {
    clearInterval(timerId);
  }
});
</script>
