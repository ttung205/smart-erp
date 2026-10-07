<template>
  <div class="min-h-screen bg-slate-50 text-slate-800 font-sans">
    <Toast position="bottom-right" />
    <ConfirmDialog />

    <!-- A. MÀN HÌNH ĐĂNG NHẬP CHUẨN KHI CHƯA XÁC THỰC -->
    <LoginPage v-if="!currentUser" :loading="loading.login" @login="submitLogin" />

    <!-- B. GIAO DIỆN CHÍNH ERP KHI ĐÃ ĐĂNG NHẬP THÀNH CÔNG -->
    <div v-else class="flex min-h-screen bg-slate-50">
      <!-- 1. Left Sidebar (Thụt ra thụt vào - Collapsible / Expand) -->
      <AppSidebar :current-tab="activeTab" :current-user="currentUser" :is-collapsed="isSidebarCollapsed" @select-tab="activeTab = $event"
        @toggle-collapse="isSidebarCollapsed = !isSidebarCollapsed" />

      <!-- 2. Main Content Area (Tự động co giãn theo Sidebar) -->
      <div class="flex-1 flex flex-col min-w-0 transition-all duration-200"
        :class="isSidebarCollapsed ? 'ml-[68px]' : 'ml-64'">
        <AppTopbar :current-user="currentUser" :health="health" @refresh-health="checkHealth" @logout="logout" />

        <main class="px-4 py-3 w-full flex-1">
          <router-view v-slot="{ Component }">
            <component
              :is="Component"
              :current-user="currentUser"
              :products="products"
              :loading-create="loading.createProduct"
              :customers="customers"
              :partners="partners"
              :warehouses="warehouses"
              @refresh-products="fetchProducts"
              @create-product="submitCreateProduct"
              @order-created="onOrderCreated"
            />
          </router-view>
        </main>
      </div>

      <!-- 3. Clean Floating AI Assistant -->
      <AiAssistant :user-name="currentUser ? currentUser.name : 'người dùng'" />
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import Toast from 'primevue/toast';
import ConfirmDialog from 'primevue/confirmdialog';

import LoginPage from './pages/auth/LoginPage.vue';
import AppSidebar from './components/layout/AppSidebar.vue';
import AppTopbar from './components/layout/AppTopbar.vue';
import AiAssistant from './components/common/AiAssistant.vue';

import { authApi } from './api/auth.api';
import { catalogApi } from './api/catalog.api';
import { purchaseApi } from './api/purchase.api';
import { inventoryApi } from './api/inventory.api';
import { salesApi } from './api/sales.api';
import { storage } from './utils/storage';
import { parseJwt } from './utils/jwt';

const router = useRouter();
const route = useRoute();
const toast = useToast();
const activeTab = ref('employee');
const isSidebarCollapsed = ref(false);

// Microservices Health State
const health = reactive({
  identity: false,
  catalog: false,
  purchase: false,
  inventory: false,
  sales: false
});

// User Session State
const jwtToken = ref(storage.getToken());
const currentUser = ref(parseJwt(jwtToken.value));

const loading = reactive({
  login: false,
  createProduct: false
});

// Shared Master Data
const products = ref([]);
const partners = ref([]);
const warehouses = ref([]);

const customers = computed(() => {
  return partners.value.filter((p) => p.type === 'CUSTOMER');
});

// Check Services Health Concurrently
const checkHealth = async () => {
  const [hIdentity, hCatalog, hPurchase, hInventory, hSales] = await Promise.all([
    authApi.checkHealth(),
    catalogApi.checkHealth(),
    purchaseApi.checkHealth(),
    inventoryApi.checkHealth(),
    salesApi.checkHealth()
  ]);
  health.identity = hIdentity;
  health.catalog = hCatalog;
  health.purchase = hPurchase;
  health.inventory = hInventory;
  health.sales = hSales;
};

// Submit Login Action
const submitLogin = async ({ email, password }) => {
  loading.login = true;
  try {
    const res = await authApi.login(email, password);
    if (res.data && res.data.token) {
      jwtToken.value = res.data.token;
      storage.setToken(jwtToken.value);
      currentUser.value = parseJwt(jwtToken.value);

      const permsCount = currentUser.value && currentUser.value.permissions ? currentUser.value.permissions.length : 0;
      toast.add({
        severity: 'success',
        summary: 'Đăng nhập thành công',
        detail: `Xin chào ${currentUser.value.name}. Cấp phát ${permsCount} quyền hệ thống.`,
        life: 3000
      });

      fetchAllData();
      router.push(route.query.redirect || '/employee');
    }
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: 'Đăng nhập không thành công',
      detail: error.message || 'Tài khoản hoặc mật khẩu không chính xác.',
      life: 4000
    });
  } finally {
    loading.login = false;
  }
};

const logout = () => {
  jwtToken.value = '';
  currentUser.value = null;
  storage.removeToken();
  toast.add({
    severity: 'info',
    summary: 'Đã đăng xuất',
    detail: 'Phiên làm việc đã kết thúc an toàn. Vui lòng đăng nhập lại.',
    life: 2500
  });
  router.push('/login');
};

const copyToken = () => {
  navigator.clipboard.writeText(jwtToken.value);
  toast.add({
    severity: 'success',
    summary: 'Đã sao chép',
    detail: 'Mã JWT Token đã được lưu vào bộ nhớ tạm.',
    life: 2000
  });
};

// Fetch Data Handlers
const fetchProducts = async () => {
  try {
    const res = await catalogApi.getProducts();
    if (res.data) products.value = res.data;
  } catch (e) {
    console.error('Lỗi tải sản phẩm:', e);
  }
};

const fetchPartners = async () => {
  try {
    const res = await catalogApi.getPartners();
    if (res.data) partners.value = res.data;
  } catch (e) {
    console.error('Lỗi tải đối tác:', e);
  }
};

const fetchWarehouses = async () => {
  try {
    const res = await catalogApi.getWarehouses();
    if (res.data) warehouses.value = res.data;
  } catch (e) {
    console.error('Lỗi tải kho bãi:', e);
  }
};

const fetchAllData = () => {
  fetchProducts();
  fetchPartners();
  fetchWarehouses();
};

// Create Product Handler
const submitCreateProduct = async (productData) => {
  loading.createProduct = true;
  try {
    await catalogApi.createProduct(productData);
    toast.add({
      severity: 'success',
      summary: 'Tạo sản phẩm thành công',
      detail: `Sản phẩm ${productData.sku} đã được lưu và sinh sự kiện Outbox vào hàng đợi.`,
      life: 4000
    });
    fetchProducts();
  } catch (error) {
    if (error.status === 403) {
      toast.add({
        severity: 'warn',
        summary: 'Từ chối truy cập (Thiếu quyền)',
        detail: 'Tài khoản hiện tại thiếu quyền catalog.create. Chỉ Super Admin có toàn quyền thao tác!',
        life: 5000
      });
    } else {
      toast.add({
        severity: 'error',
        summary: 'Thao tác thất bại',
        detail: error.message || 'Không thể tạo mới sản phẩm.',
        life: 4000
      });
    }
  } finally {
    loading.createProduct = false;
  }
};

const onOrderCreated = (order) => {
  toast.add({
    severity: 'success',
    summary: 'Lập đơn hàng thành công',
    detail: `Đơn bán hàng ${order.order_number} đã được khởi tạo trong trạng thái Đã xác nhận.`,
    life: 3500
  });
};

watch(() => route.path, (newPath) => {
  const map = {
    '/department': 'department',
    '/employee': 'employee',
    '/role': 'role',
    '/permission': 'permission',
    '/customer': 'customer',
    '/supplier': 'supplier',
    '/product': 'product',
    '/brand': 'brand',
    '/category': 'category',
    '/inventory/overview': 'inventory-overview',
    '/inventory/stocks': 'inventory-stocks',
    '/inventory/reservations': 'inventory-reservations',
    '/inventory/movements': 'inventory-movements',
    '/warehouse-request-sale': 'warehouse-request-sale',
    '/purchase/requisitions': 'purchase-requisition',
    '/purchase/orders': 'purchase-order',
    '/purchase/receipts': 'purchase-receipt',
    '/purchase/invoices': 'purchase-invoice',
    '/quotation': 'quotation',
    '/order': 'order',
    '/delivery': 'delivery',
    '/delivery-request': 'delivery-request',
    '/invoice': 'invoice',
    '/master': 'master',
    '/service-status': 'service-status',
    '/architecture': 'architecture'
  };
  if (map[newPath]) {
    activeTab.value = map[newPath];
  }
}, { immediate: true });

onMounted(async () => {
  checkHealth();
  await router.isReady();
  if (currentUser.value) {
    fetchAllData();
    if (route.path === '/' || route.path === '/login') {
      router.push('/employee');
    }
  } else if (route.path !== '/login' && route.path !== '/forbidden') {
    router.push('/login');
  }
  setInterval(checkHealth, 15000);
});
</script>