<template>
  <div class="layout-wrapper">
    <Toast position="top-right" />

    <!-- 1. Left Sidebar -->
    <AppSidebar
      :current-tab="activeTab"
      @select-tab="activeTab = $event"
    />

    <!-- 2. Main Content Area -->
    <div class="layout-main">
      <AppTopbar
        :current-user="currentUser"
        :health="health"
        @refresh-health="checkHealth"
        @logout="logout"
      />

      <main class="page-container">
        <!-- Page 1: Identity & RBAC -->
        <IdentityPage
          v-if="activeTab === 'identity'"
          :current-user="currentUser"
          :jwt-token="jwtToken"
          :loading-login="loading.login"
          @quick-login="quickLogin"
          @manual-login="submitLogin"
          @copy-token="copyToken"
        />

        <!-- Page 2: Catalog Master -->
        <CatalogPage
          v-else-if="activeTab === 'catalog'"
          :products="products"
          :current-user="currentUser"
          :loading-create="loading.createProduct"
          @refresh-products="fetchProducts"
          @create-product="submitCreateProduct"
        />

        <!-- Page 3: Inventory Management (Tồn Kho) -->
        <InventoryView
          v-else-if="activeTab === 'inventory'"
          :current-user="currentUser"
        />

        <!-- Page 4: Sales Orders -->
        <SalesPage
          v-else-if="activeTab === 'sales'"
          :customers="customers"
          @order-created="onOrderCreated"
        />

        <!-- Page 5: Master Partners & Warehouses -->
        <MasterPage
          v-else-if="activeTab === 'master'"
          :partners="partners"
          :warehouses="warehouses"
        />

        <!-- Page 6: Architecture & Outbox -->
        <ArchitecturePage
          v-else-if="activeTab === 'architecture'"
        />
      </main>
    </div>

    <!-- 3. Clean Floating AI Assistant -->
    <AiAssistant
      :user-name="currentUser ? currentUser.name : 'người dùng'"
    />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useToast } from 'primevue/usetoast';
import Toast from 'primevue/toast';

import AppSidebar from './components/layout/AppSidebar.vue';
import AppTopbar from './components/layout/AppTopbar.vue';
import AiAssistant from './components/common/AiAssistant.vue';

import IdentityPage from './pages/identity/index.vue';
import CatalogPage from './pages/catalog/index.vue';
import SalesPage from './pages/sales/index.vue';
import MasterPage from './pages/master/index.vue';
import ArchitecturePage from './pages/architecture/index.vue';
import InventoryView from './views/inventory/InventoryView.vue';

import { authApi } from './api/auth.api';
import { catalogApi } from './api/catalog.api';
import { inventoryApi } from './api/inventory.api';
import { storage } from './utils/storage';
import { parseJwt } from './utils/jwt';

const toast = useToast();
const activeTab = ref('identity');

// Microservices Health State
const health = reactive({
  identity: false,
  catalog: false,
  inventory: false
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

// Check Services Health
const checkHealth = async () => {
  health.identity = await authApi.checkHealth();
  health.catalog = await catalogApi.checkHealth();
  health.inventory = await inventoryApi.checkHealth();
};

// Login Actions
const quickLogin = (email) => {
  submitLogin({ email, password: 'password123' });
};

const submitLogin = async ({ email, password }) => {
  loading.login = true;
  try {
    const res = await authApi.login(email, password);
    if (res.data && res.data.token) {
      jwtToken.value = res.data.token;
      storage.setToken(jwtToken.value);
      currentUser.value = parseJwt(jwtToken.value);

      toast.add({
        severity: 'success',
        summary: 'Đăng nhập thành công',
        detail: `Xin chào ${currentUser.value.name}. Cấp phát ${currentUser.value.permissions.length} quyền hệ thống.`,
        life: 3000
      });

      fetchAllData();
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
    detail: 'Phiên làm việc đã kết thúc an toàn.',
    life: 2500
  });
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
        detail: 'Tài khoản hiện tại thiếu quyền catalog.create. Vui lòng đăng nhập với vai trò Trưởng phòng hoặc Quản trị viên.',
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

onMounted(() => {
  checkHealth();
  fetchAllData();
  setInterval(checkHealth, 15000);
});
</script>