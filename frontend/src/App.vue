<template>
  <div class="layout-wrapper">
    <Toast position="top-right" />

    <!-- 1. LEFT WHITE SIDEBAR (Thụt ra thụt vào khi click vào brand-box) -->
    <aside class="layout-sidebar" :class="{ 'is-collapsed': isSidebarCollapsed }">
      <div class="sidebar-header">
        <div 
          class="brand-box" 
          @click="toggleSidebar" 
          :title="isSidebarCollapsed ? 'Bấm để mở rộng menu' : 'Bấm để thu gọn menu'"
        >
          <div class="brand-dot" v-tooltip.right="isSidebarCollapsed ? 'Smart ERP v2.0 - Bấm để mở rộng' : undefined">
            <i :class="isSidebarCollapsed ? 'pi pi-bars' : 'pi pi-bolt'"></i>
          </div>
          <div v-show="!isSidebarCollapsed" class="brand-name">
            <span>Smart ERP</span>
            <span class="brand-badge">v2.0</span>
          </div>
        </div>
      </div>

      <nav class="sidebar-menu">
        <Menu :model="sidebarMenuItems" class="azure-prime-menu">
          <template #submenulabel="{ item }">
            <span v-if="!isSidebarCollapsed" class="menu-category">{{ item.label }}</span>
            <div v-else class="menu-category-divider"></div>
          </template>
          <template #item="{ item }">
            <div 
              class="sidebar-menu-wrapper"
              @mouseenter="item.tabKey === 'inventory' ? isInventoryHovered = true : null"
              @mouseleave="item.tabKey === 'inventory' ? isInventoryHovered = false : null"
            >
              <button 
                type="button"
                class="menu-item" 
                :class="{ 
                  active: activeTab === item.tabKey && (!item.subItems || isSidebarCollapsed),
                  'has-active-child': activeTab === item.tabKey && item.subItems
                }" 
                @click="item.command()"
                v-tooltip.right="isSidebarCollapsed ? item.label : undefined"
              >
                <i :class="[item.icon, 'menu-icon']"></i>
                <span v-if="!isSidebarCollapsed" class="menu-label-text">{{ item.label }}</span>
                <span 
                  v-if="!isSidebarCollapsed && item.subItems"
                  class="submenu-chevron-btn"
                  @click.stop="toggleInventorySubmenu"
                  :title="isInventoryPinned ? 'Thu gọn menu con' : 'Cố định mở rộng menu con'"
                >
                  <i 
                    class="pi pi-chevron-down submenu-chevron"
                    :class="{ 'chevron-rotated': isInventoryPinned }"
                  ></i>
                </span>
              </button>

              <!-- 4 tag dọc xuất hiện khi mở Quản lý tồn kho trên menu -->
              <div 
                v-if="item.subItems" 
                v-show="isInventoryPinned || isInventoryHovered"
                class="vertical-subtabs"
                :class="{ 'flyout-subtabs': isSidebarCollapsed }"
              >
                <button
                  v-for="sub in item.subItems"
                  :key="sub.subTabKey"
                  type="button"
                  class="vertical-subtab-item"
                  :class="{ active: activeTab === 'inventory' && inventorySubTab === sub.subTabKey }"
                  @click.stop="sub.command()"
                >
                  <i :class="[sub.icon, 'subtab-icon']"></i>
                  <span class="subtab-text">{{ sub.label }}</span>
                </button>
              </div>
            </div>
          </template>
        </Menu>
      </nav>

      <div class="sidebar-footer">
        <div v-if="!isSidebarCollapsed" class="version-box">
          <span class="text-muted text-xs">Microservices Architecture</span>
          <span class="font-semibold text-xs text-dark">Database-per-Service</span>
        </div>
        <div v-else class="version-box-collapsed" v-tooltip.right="'Microservices Architecture: Database-per-Service'">
          <i class="pi pi-server text-muted text-sm"></i>
        </div>
      </div>
    </aside>

    <!-- 2. MAIN LAYOUT (Topbar + Content) -->
    <div class="layout-main" :class="{ 'sidebar-collapsed': isSidebarCollapsed }">
      <!-- White Topbar with Hairline Border -->
      <header class="layout-topbar">
        <div class="topbar-left">
          <span class="page-title">{{ currentTabTitle }}</span>
        </div>

        <div class="service-status-group">
          <div class="soft-pill" :class="health.identity ? 'online' : 'offline'">
            <span class="pulse-circle"></span>
            <span>Identity :8001 • {{ health.identity ? 'UP' : 'DOWN' }}</span>
          </div>

          <div class="soft-pill" :class="health.catalog ? 'online' : 'offline'">
            <span class="pulse-circle"></span>
            <span>Catalog :8002 • {{ health.catalog ? 'UP' : 'DOWN' }}</span>
          </div>

          <div class="soft-pill" :class="health.inventory ? 'online' : 'offline'">
            <span class="pulse-circle"></span>
            <span>Inventory :8004 • {{ health.inventory ? 'UP' : 'DOWN' }}</span>
          </div>

          <Button 
            icon="pi pi-refresh" 
            text 
            rounded 
            size="small" 
            @click="checkHealth" 
            v-tooltip.bottom="'Kiểm tra lại kết nối API'"
          />
        </div>

        <div class="topbar-right">
          <div v-if="currentUser" class="user-profile-widget">
            <div class="user-avatar-circle">{{ currentUser.name.charAt(0) }}</div>
            <div class="user-meta">
              <span class="user-meta-name">{{ currentUser.name }}</span>
              <span class="user-meta-role">{{ currentUser.department }}</span>
            </div>
            <Button icon="pi pi-sign-out" text rounded severity="danger" size="small" @click="logout" />
          </div>
          <div v-else class="soft-pill">
            <i class="pi pi-lock text-muted"></i>
            <span>Chưa đăng nhập</span>
          </div>
        </div>
      </header>

      <!-- Page Canvas Content -->
      <main class="page-container">
        <!-- TAB 1: IDENTITY & RBAC -->
        <div v-if="activeTab === 'identity'" class="tab-pane-content">
          <!-- Persona 1-Click Login Section -->
          <div class="white-card mb-4">
            <div class="card-title-row">
              <h3>
                <i class="pi pi-users text-primary"></i>
                Đăng Nhập Nhanh Theo Vai Trò (6 Personas Mẫu)
              </h3>
              <span class="soft-badge soft-badge-indigo">Seed Data Mẫu</span>
            </div>
            <p class="text-muted text-sm mb-4">
              Click vào từng thẻ vai trò bên dưới để đổi phiên làm việc và kiểm tra ma trận phân quyền động RBAC:
            </p>

            <div class="persona-cards-grid">
              <div 
                v-for="p in personas" 
                :key="p.email" 
                class="clean-persona-card"
                :class="{ active: currentUser && currentUser.email === p.email }"
                @click="quickLogin(p.email)"
              >
                <div class="persona-avatar">{{ p.icon }}</div>
                <div class="persona-content">
                  <span class="persona-name">{{ p.name }}</span>
                  <span class="persona-role-text">{{ p.roleName }}</span>
                  <span class="text-muted text-xs">{{ p.email }}</span>
                </div>
                <span class="soft-badge" :class="p.badgeClass">{{ p.badge }}</span>
              </div>
            </div>

            <!-- Manual Login Form -->
            <div class="manual-login-strip">
              <span class="text-sm font-semibold text-dark">Hoặc đăng nhập tài khoản khác:</span>
              <div class="login-inputs-row">
                <InputText v-model="loginForm.email" placeholder="Email (ví dụ: sales@erp.local)" class="p-inputtext-sm flex-1" />
                <InputText type="password" v-model="loginForm.password" placeholder="Mật khẩu" class="p-inputtext-sm flex-1" />
                <Button label="Đăng Nhập" icon="pi pi-sign-in" size="small" @click="submitLogin" :loading="loading.login" />
              </div>
            </div>
          </div>

          <!-- Token Claims & Permissions Inspector -->
          <div class="grid-2col">
            <!-- Active Claims -->
            <div class="white-card">
              <div class="card-title-row">
                <h3>
                  <i class="pi pi-id-card text-primary"></i>
                  Thông Tin Phiên Xác Thực (JWT Claims)
                </h3>
                <span class="soft-badge" :class="currentUser ? 'soft-badge-success' : 'soft-badge-danger'">
                  {{ currentUser ? 'ĐÃ XÁC THỰC' : 'CHƯA ĐĂNG NHẬP' }}
                </span>
              </div>

              <div v-if="currentUser" class="claims-details-list">
                <div class="claim-row">
                  <span class="text-muted text-xs">Mã Người Dùng (sub):</span>
                  <span class="font-semibold text-dark">#{{ currentUser.sub }}</span>
                </div>
                <div class="claim-row">
                  <span class="text-muted text-xs">Họ và Tên:</span>
                  <span class="font-semibold text-dark">{{ currentUser.name }}</span>
                </div>
                <div class="claim-row">
                  <span class="text-muted text-xs">Phòng Ban:</span>
                  <span class="font-semibold text-dark">{{ currentUser.department }}</span>
                </div>
                <div class="claim-row">
                  <span class="text-muted text-xs">Vai Trò Được Gán (Roles):</span>
                  <span class="font-semibold text-primary">{{ currentUser.roles.join(', ') }}</span>
                </div>

                <!-- Raw Token Preview -->
                <div class="raw-token-box-clean mt-3">
                  <div class="flex justify-between items-center mb-1">
                    <span class="text-xs text-muted font-semibold">Chuỗi JWT Bearer Token:</span>
                    <Button label="Sao chép" icon="pi pi-copy" text size="small" @click="copyToken" />
                  </div>
                  <textarea readonly :value="jwtToken" rows="2" class="token-textarea-clean"></textarea>
                </div>
              </div>

              <div v-else class="p-6 text-center text-muted">
                <i class="pi pi-lock text-3xl mb-2 text-dim block"></i>
                <p>Chọn một vai trò ở trên để xem Claims giải mã.</p>
              </div>
            </div>

            <!-- Permission Matrix Badges -->
            <div class="white-card">
              <div class="card-title-row">
                <h3>
                  <i class="pi pi-check-square text-primary"></i>
                  Ma Trận Quyền Hạn (Stateless Authorization)
                </h3>
                <span class="soft-badge soft-badge-neutral">20 Quyền RBAC</span>
              </div>
              <p class="text-muted text-xs mb-3">
                Catalog Service và các dịch vụ khác giải mã quyền trực tiếp từ token mà không cần gọi mạng sang Identity Service:
              </p>

              <div v-if="currentUser" class="permission-tags-container">
                <span 
                  v-for="perm in systemPermissions" 
                  :key="perm.code"
                  class="soft-badge m-1"
                  :class="currentUser.permissions.includes(perm.code) ? 'soft-badge-success' : 'soft-badge-neutral'"
                >
                  <i :class="currentUser.permissions.includes(perm.code) ? 'pi pi-check text-xs' : 'pi pi-times text-xs'"></i>
                  {{ perm.code }}
                </span>
              </div>
              <div v-else class="p-6 text-center text-muted">
                Chưa có thông tin quyền hạn.
              </div>
            </div>
          </div>
        </div>

        <!-- TAB 2: CATALOG & RBAC TEST -->
        <div v-if="activeTab === 'catalog'" class="tab-pane-content">
          <!-- Action Bar: Search & Create -->
          <div class="white-card mb-4 p-3 flex justify-between items-center">
            <div class="flex items-center gap-2">
              <span class="p-input-icon-left">
                <i class="pi pi-search text-muted ml-2"></i>
                <InputText 
                  v-model="catalogSearch" 
                  placeholder="Tìm theo SKU hoặc Tên sản phẩm..." 
                  class="p-inputtext-sm search-clean"
                />
              </span>
            </div>

            <div class="flex items-center gap-2">
              <Button label="Làm mới" icon="pi pi-refresh" outlined size="small" severity="secondary" @click="fetchProducts" />
              <Button label="Test Tạo Sản Phẩm (Kiểm tra RBAC)" icon="pi pi-plus" size="small" @click="openCreateModal" />
            </div>
          </div>

          <!-- RBAC Status Hint Banner -->
          <div class="rbac-strip-card mb-4" :class="canCreateCatalog ? 'strip-success' : 'strip-warning'">
            <i :class="canCreateCatalog ? 'pi pi-shield text-lg text-emerald' : 'pi pi-exclamation-triangle text-lg text-amber'"></i>
            <div>
              <strong class="text-sm">Trạng thái quyền hạn hiện tại: </strong>
              <span v-if="canCreateCatalog" class="text-sm">
                Tài khoản <strong>{{ currentUser ? currentUser.name : '' }}</strong> CÓ quyền <code>catalog.create</code>. Bạn có thể thêm sản phẩm mới.
              </span>
              <span v-else class="text-sm">
                Tài khoản <strong>{{ currentUser ? currentUser.name : 'Chưa đăng nhập' }}</strong> KHÔNG CÓ quyền <code>catalog.create</code>. Khi ấn tạo, Catalog Service sẽ chặn lại bằng mã lỗi <strong>403 FORBIDDEN</strong>!
              </span>
            </div>
          </div>

          <!-- Master Data Products Table -->
          <div class="white-card">
            <div class="card-title-row">
              <h3>
                <i class="pi pi-list text-primary"></i>
                Danh Sách Sản Phẩm (Master Data Catalog)
              </h3>
              <span class="soft-badge soft-badge-indigo">{{ filteredProducts.length }} Sản phẩm</span>
            </div>

            <DataTable :value="filteredProducts" class="clean-datatable" responsiveLayout="scroll">
              <Column field="sku" header="Mã SKU">
                <template #body="{ data }">
                  <span class="sku-clean">{{ data.sku }}</span>
                </template>
              </Column>
              <Column field="name" header="Tên Sản Phẩm">
                <template #body="{ data }">
                  <span class="font-semibold text-dark">{{ data.name }}</span>
                </template>
              </Column>
              <Column field="category_name" header="Danh Mục">
                <template #body="{ data }">
                  <span class="soft-badge soft-badge-neutral">{{ data.category_name || 'Công nghệ' }}</span>
                </template>
              </Column>
              <Column field="unit_code" header="ĐVT">
                <template #body="{ data }">
                  <span class="text-muted text-xs">{{ data.unit_code || 'PCS' }}</span>
                </template>
              </Column>
              <Column field="gross_price" header="Giá Niêm Yết" class="text-right">
                <template #body="{ data }">
                  <span class="font-semibold text-dark num-tabular">{{ formatCurrency(data.gross_price) }}</span>
                </template>
              </Column>
              <Column field="cost_price" header="Giá Vốn" class="text-right">
                <template #body="{ data }">
                  <span class="text-muted num-tabular">{{ formatCurrency(data.cost_price) }}</span>
                </template>
              </Column>
              <Column field="min_stock" header="Tồn An Toàn" class="text-center">
                <template #body="{ data }">
                  <span class="soft-badge soft-badge-warning num-tabular">{{ data.min_stock }} cái</span>
                </template>
              </Column>
              <Column header="Trạng Thái" class="text-center">
                <template #body>
                  <span class="soft-badge soft-badge-success">ACTIVE</span>
                </template>
              </Column>
            </DataTable>
          </div>

          <!-- Create Product Dialog -->
          <Dialog 
            v-model:visible="showCreateModal" 
            modal 
            header="Thêm Mới Sản Phẩm (Kiểm thử Stateless RBAC)" 
            :style="{ width: '500px' }"
          >
            <p class="text-muted text-xs mb-4">
              Request sẽ gửi kèm Bearer JWT. Nếu tài khoản thiếu quyền <code>catalog.create</code>, hệ thống trả về HTTP 403!
            </p>

            <div class="form-clean-layout">
              <div class="form-group-clean">
                <label>Mã SKU:</label>
                <InputText v-model="newProduct.sku" class="w-full p-inputtext-sm" />
              </div>
              <div class="form-group-clean">
                <label>Tên Sản Phẩm:</label>
                <InputText v-model="newProduct.name" class="w-full p-inputtext-sm" />
              </div>
              <div class="grid-2col">
                <div class="form-group-clean">
                  <label>Giá Niêm Yết (VNĐ):</label>
                  <InputNumber v-model="newProduct.gross_price" class="w-full p-inputtext-sm" currency="VND" locale="vi-VN" />
                </div>
                <div class="form-group-clean">
                  <label>Tồn An Toàn:</label>
                  <InputNumber v-model="newProduct.min_stock" class="w-full p-inputtext-sm" />
                </div>
              </div>
            </div>

            <template #footer>
              <Button label="Hủy" icon="pi pi-times" text size="small" @click="showCreateModal = false" />
              <Button 
                label="Gửi Request POST /api/products" 
                icon="pi pi-send" 
                size="small" 
                @click="submitCreateProduct" 
                :loading="loading.createProduct" 
              />
            </template>
          </Dialog>
        </div>

        <!-- TAB 3: PARTNERS & WAREHOUSES -->
        <div v-if="activeTab === 'master'" class="tab-pane-content">
          <div class="grid-2col">
            <!-- Partners Table -->
            <div class="white-card">
              <div class="card-title-row">
                <h3>
                  <i class="pi pi-briefcase text-primary"></i>
                  Đối Tác B2B (Khách Hàng & NCC)
                </h3>
                <span class="soft-badge soft-badge-indigo">{{ partners.length }} Đối tác</span>
              </div>

              <DataTable :value="partners" class="clean-datatable" responsiveLayout="scroll">
                <Column field="code" header="Mã">
                  <template #body="{ data }">
                    <span class="sku-clean">{{ data.code }}</span>
                  </template>
                </Column>
                <Column field="name" header="Tên Đối Tác">
                  <template #body="{ data }">
                    <span class="font-semibold text-dark">{{ data.name }}</span>
                  </template>
                </Column>
                <Column field="type" header="Loại">
                  <template #body="{ data }">
                    <span class="soft-badge" :class="data.type === 'CUSTOMER' ? 'soft-badge-indigo' : 'soft-badge-warning'">
                      {{ data.type }}
                    </span>
                  </template>
                </Column>
                <Column field="credit_limit" header="Hạn Mức Nợ" class="text-right">
                  <template #body="{ data }">
                    <span class="font-semibold text-dark num-tabular">{{ formatCurrency(data.credit_limit) }}</span>
                  </template>
                </Column>
              </DataTable>
            </div>

            <!-- Warehouses List -->
            <div class="white-card">
              <div class="card-title-row">
                <h3>
                  <i class="pi pi-home text-primary"></i>
                  Danh Mục Kho Bãi (Metadata)
                </h3>
                <span class="soft-badge soft-badge-success">{{ warehouses.length }} Kho hoạt động</span>
              </div>

              <div class="warehouse-cards-col">
                <div v-for="w in warehouses" :key="w.id" class="clean-wh-box">
                  <div class="flex justify-between items-center mb-1">
                    <span class="font-bold text-primary">{{ w.code }}</span>
                    <span class="soft-badge soft-badge-success">ACTIVE</span>
                  </div>
                  <h4 class="font-semibold text-dark text-sm">{{ w.name }}</h4>
                  <p class="text-muted text-xs mt-1">📍 {{ w.address }}</p>
                  <div class="mt-2 pt-2 border-t border-slate-100 flex justify-between text-xs text-muted">
                    <span>Mô hình chuẩn:</span>
                    <span class="font-semibold text-dark">Catalog không lưu số lượng tồn</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- TAB 4: ARCHITECTURE & OUTBOX -->
        <div v-if="activeTab === 'architecture'" class="tab-pane-content">
          <div class="white-card">
            <div class="card-title-row">
              <h3>
                <i class="pi pi-sync text-primary"></i>
                Transactional Outbox Pattern & Event-Driven Architecture
              </h3>
              <span class="soft-badge soft-badge-indigo">RabbitMQ Topic Exchange</span>
            </div>

            <!-- 3 Architectural Pillars -->
            <div class="grid-3col mb-4">
              <div class="arch-box-clean">
                <div class="arch-num-badge">1</div>
                <h4 class="font-semibold text-dark text-sm mb-1">Atomic Database Write</h4>
                <p class="text-muted text-xs">
                  Khi có sự kiện thay đổi dữ liệu, ghi đồng thời bản ghi nghiệp vụ và Domain Event vào bảng <code>outbox_events</code> trong cùng 1 Transaction.
                </p>
              </div>

              <div class="arch-box-clean">
                <div class="arch-num-badge">2</div>
                <h4 class="font-semibold text-dark text-sm mb-1">Message Relay Engine</h4>
                <p class="text-muted text-xs">
                  Worker đọc sự kiện có trạng thái <code>PENDING</code> và bắn vào RabbitMQ Exchange <code>erp.events</code> theo routing key chuẩn CloudEvents.
                </p>
              </div>

              <div class="arch-box-clean">
                <div class="arch-num-badge">3</div>
                <h4 class="font-semibold text-dark text-sm mb-1">Idempotent Consumer</h4>
                <p class="text-muted text-xs">
                  Inventory Service đọc sự kiện, kiểm tra bảng <code>processed_events</code> để đảm bảo mỗi thông điệp chỉ xử lý chính xác 1 lần.
                </p>
              </div>
            </div>

            <!-- CloudEvents Spec Box -->
            <div class="cloudevents-clean-box">
              <span class="text-xs font-bold text-muted uppercase tracking-wider block mb-2">CloudEvents Envelope Chuẩn Phân Tán:</span>
              <pre class="clean-json-view">
{
  "specversion": "1.0",
  "id": "evt_4812a10b-8025-4c07-b35e-7a918e95089e",
  "source": "/services/catalog-service",
  "type": "catalog.product_created",
  "datacontenttype": "application/json",
  "time": "2026-09-10T21:40:00Z",
  "correlation_id": "corr_4a3b2c1d0e9f",
  "data": {
    "product_id": 8,
    "sku": "PROD-LAPTOP-03",
    "name": "MacBook Air M3 15 inch",
    "gross_price": 32990000
  }
}
              </pre>
            </div>
          </div>
        </div>

        <!-- TAB 5: INVENTORY & STOCK LEDGER -->
        <div v-if="activeTab === 'inventory'" class="tab-pane-content">
          <InventoryView 
            :currentUser="currentUser" 
            v-model:currentSubTab="inventorySubTab"
          />
        </div>
      </main>
    </div>

    <!-- 3. AI COPILOT FLOATING WIDGET -->
    <button class="copilot-floating-btn" @click="showCopilot = !showCopilot">
      <i class="pi pi-sparkles"></i>
      <span>AI Copilot</span>
    </button>

    <div v-if="showCopilot" class="copilot-panel">
      <div class="copilot-header">
        <div class="copilot-title-box">
          <i class="pi pi-sparkles"></i>
          <span class="font-bold text-sm">Smart ERP Copilot</span>
          <span class="copilot-badge">AI Assistant</span>
        </div>
        <Button icon="pi pi-times" text rounded size="small" severity="contrast" @click="showCopilot = false" />
      </div>

      <div class="copilot-body">
        <div class="copilot-msg-ai">
          <i class="pi pi-android text-primary mt-1"></i>
          <div>
            <p class="font-semibold text-xs text-dark mb-1">Trợ Lý AI Phân Tán:</p>
            <p class="text-xs">
              Xin chào <strong>{{ currentUser ? currentUser.name : 'bạn' }}</strong>! Tôi có thể hỗ trợ tra cứu tồn kho khả dụng, phân tích cảnh báo Min-Stock hoặc kiểm tra quyền hạn RBAC của bạn.
            </p>
          </div>
        </div>
      </div>

      <div class="copilot-footer">
        <InputText placeholder="Hỏi AI về tồn kho hoặc đơn hàng..." class="p-inputtext-sm flex-1" />
        <Button icon="pi pi-send" size="small" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useToast } from 'primevue/usetoast'

import Toast from 'primevue/toast'
import Menu from 'primevue/menu'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Dialog from 'primevue/dialog'
import InventoryView from './views/inventory/InventoryView.vue'

const toast = useToast()

const activeTab = ref('identity')
const inventorySubTab = ref('overview')
const isInventoryHovered = ref(false)
const isInventoryPinned = ref(false)
const showCopilot = ref(false)

const toggleInventorySubmenu = (event) => {
  if (event) event.stopPropagation()
  isInventoryPinned.value = !isInventoryPinned.value
}

// Trạng thái thụt ra thụt vào của Sidebar khi click vào brand-box
const isSidebarCollapsed = ref(localStorage.getItem('smart_erp_sidebar_collapsed') === 'true')

const toggleSidebar = () => {
  isSidebarCollapsed.value = !isSidebarCollapsed.value
  localStorage.setItem('smart_erp_sidebar_collapsed', isSidebarCollapsed.value ? 'true' : 'false')
}

// Health State
const health = reactive({
  identity: false,
  catalog: false,
  inventory: false
})

// Current User State
const jwtToken = ref(localStorage.getItem('smart_erp_token') || '')
const currentUser = ref(null)

const loginForm = reactive({
  email: 'sales@erp.local',
  password: 'password123'
})

const loading = reactive({
  login: false,
  createProduct: false
})

// 6 Seeded Personas
const personas = [
  {
    name: 'Nguyễn Văn Giám Đốc',
    roleName: 'Ban Giám Đốc (BOD)',
    email: 'director@erp.local',
    icon: '👑',
    badge: 'Toàn Quyền',
    badgeClass: 'soft-badge-success'
  },
  {
    name: 'Trần Thị Trưởng Phòng Sales',
    roleName: 'Trưởng Phòng Kinh Doanh',
    email: 'sales_mgr@erp.local',
    icon: '💼',
    badge: 'Catalog Manager',
    badgeClass: 'soft-badge-indigo'
  },
  {
    name: 'Lê Văn Nhân Viên Sales',
    roleName: 'Nhân Viên Kinh Doanh',
    email: 'sales@erp.local',
    icon: '🛒',
    badge: 'Chỉ Xem (No Create)',
    badgeClass: 'soft-badge-warning'
  },
  {
    name: 'Hoàng Văn Thủ Kho',
    roleName: 'Bộ Phận Kho Vận',
    email: 'warehouse@erp.local',
    icon: '📦',
    badge: 'Thủ Kho',
    badgeClass: 'soft-badge-neutral'
  },
  {
    name: 'Phạm Văn Mua Hàng',
    roleName: 'Phòng Mua Hàng & Cung Ứng',
    email: 'purchase_mgr@erp.local',
    icon: '🛍️',
    badge: 'Purchase Manager',
    badgeClass: 'soft-badge-indigo'
  },
  {
    name: 'Quản Trị Viên Hệ Thống',
    roleName: 'Hệ Thống & IT',
    email: 'admin@erp.local',
    icon: '⚙️',
    badge: 'Admin',
    badgeClass: 'soft-badge-success'
  }
]

// 20 System Permissions for Matrix Visualizer
const systemPermissions = [
  { code: 'sales.view' },
  { code: 'sales.create' },
  { code: 'sales.approve' },
  { code: 'delivery.create' },
  { code: 'purchase.view' },
  { code: 'purchase.create' },
  { code: 'purchase.approve' },
  { code: 'inventory.view' },
  { code: 'inventory.receipt' },
  { code: 'inventory.issue' },
  { code: 'inventory.reserve' },
  { code: 'catalog.view' },
  { code: 'catalog.create' },
  { code: 'catalog.edit' },
  { code: 'partner.view' },
  { code: 'partner.create' },
  { code: 'warehouse.view' },
  { code: 'users.view' },
  { code: 'roles.view' },
  { code: 'ai.use' },
]

// Catalog State
const products = ref([])
const catalogSearch = ref('')
const partners = ref([])
const warehouses = ref([])

const showCreateModal = ref(false)
const newProduct = reactive({
  sku: '',
  name: '',
  gross_price: 26000000,
  min_stock: 12
})

// Computeds
const sidebarMenuItems = computed(() => [
  {
    label: 'Phân Hệ Cốt Lõi',
    items: [
      {
        label: '1. Identity & RBAC',
        icon: 'pi pi-shield',
        tabKey: 'identity',
        command: () => { activeTab.value = 'identity' }
      },
      {
        label: '2. Catalog Sản Phẩm',
        icon: 'pi pi-box',
        tabKey: 'catalog',
        command: () => { activeTab.value = 'catalog' }
      },
      {
        label: '3. Đối Tác & Kho Bãi',
        icon: 'pi pi-building',
        tabKey: 'master',
        command: () => { activeTab.value = 'master' }
      }
    ]
  },
  {
    label: 'Hạ Tầng & Tích Hợp',
    items: [
      {
        label: '4. Outbox & RabbitMQ',
        icon: 'pi pi-share-alt',
        tabKey: 'architecture',
        command: () => { activeTab.value = 'architecture' }
      }
    ]
  },
  {
    label: 'Kho Vận & Động Cơ Tồn',
    items: [
      {
        label: '5. Quản Lý Tồn Kho',
        icon: 'pi pi-server',
        tabKey: 'inventory',
        command: () => { 
          activeTab.value = 'inventory'
          inventorySubTab.value = 'overview'
        },
        subItems: [
          {
            label: 'Tổng Quan',
            icon: 'pi pi-chart-pie',
            subTabKey: 'overview',
            command: () => {
              activeTab.value = 'inventory'
              inventorySubTab.value = 'overview'
            }
          },
          {
            label: 'Tồn Kho',
            icon: 'pi pi-box',
            subTabKey: 'stocks',
            command: () => {
              activeTab.value = 'inventory'
              inventorySubTab.value = 'stocks'
            }
          },
          {
            label: 'Đơn Giữ Hàng',
            icon: 'pi pi-lock',
            subTabKey: 'reservations',
            command: () => {
              activeTab.value = 'inventory'
              inventorySubTab.value = 'reservations'
            }
          },
          {
            label: 'Lịch Sử',
            icon: 'pi pi-history',
            subTabKey: 'movements',
            command: () => {
              activeTab.value = 'inventory'
              inventorySubTab.value = 'movements'
            }
          }
        ]
      }
    ]
  }
])

const currentTabTitle = computed(() => {
  switch (activeTab.value) {
    case 'identity': return '1. Quản Lý Định Danh & Phân Quyền (Identity & RBAC)'
    case 'catalog': return '2. Danh Mục Hàng Hóa & Kiểm Thử Quyền (Catalog Master)'
    case 'master': return '3. Danh Mục Đối Tác B2B & Hệ Thống Kho Bãi'
    case 'architecture': return '4. Kiến Trúc Phân Tán & Transactional Outbox'
    case 'inventory': return '5. Động Cơ Tồn Kho & Sổ Cái Thẻ Kho (Inventory Service :8004)'
    default: return 'Smart Distributed ERP'
  }
})

const canCreateCatalog = computed(() => {
  return currentUser.value && currentUser.value.permissions && currentUser.value.permissions.includes('catalog.create')
})

const filteredProducts = computed(() => {
  if (!catalogSearch.value) return products.value
  const q = catalogSearch.value.toLowerCase()
  return products.value.filter(p => 
    (p.sku && p.sku.toLowerCase().includes(q)) || 
    (p.name && p.name.toLowerCase().includes(q))
  )
})

// Helpers
const formatCurrency = (val) => {
  if (!val) return '0 ₫'
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val)
}

const parseJwt = (token) => {
  try {
    const base64Url = token.split('.')[1]
    const base64 = base64Url.replace(/-/g, '+').replace(/_/g, '/')
    const jsonPayload = decodeURIComponent(atob(base64).split('').map(function(c) {
      return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2)
    }).join(''))
    return JSON.parse(jsonPayload)
  } catch (e) {
    return null
  }
}

// Health Check
const checkHealth = async () => {
  try {
    const resId = await fetch('/api/v1/auth/health', { signal: AbortSignal.timeout(3000) })
    health.identity = resId.ok
  } catch (e) {
    health.identity = false
  }

  try {
    const resCat = await fetch('/api/v1/catalog/health', { signal: AbortSignal.timeout(3000) })
    health.catalog = resCat.ok
  } catch (e) {
    health.catalog = false
  }

  try {
    const resInv = await fetch('/api/v1/inventory/health', { signal: AbortSignal.timeout(3000) })
    health.inventory = resInv.ok
  } catch (e) {
    health.inventory = false
  }
}

// Quick Login
const quickLogin = (email) => {
  loginForm.email = email
  loginForm.password = 'password123'
  submitLogin()
}

// Submit Login
const submitLogin = async () => {
  loading.login = true
  try {
    const res = await fetch('/api/v1/auth/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        email: loginForm.email,
        password: loginForm.password
      })
    })
    const data = await res.json()
    if (res.ok && data.data && data.data.token) {
      jwtToken.value = data.data.token
      localStorage.setItem('smart_erp_token', jwtToken.value)
      currentUser.value = parseJwt(jwtToken.value)
      
      toast.add({
        severity: 'success',
        summary: 'Đăng nhập thành công',
        detail: `Xin chào ${currentUser.value.name}. Cấp phát ${currentUser.value.permissions.length} quyền.`,
        life: 3500
      })
      
      fetchProducts()
      fetchPartners()
      fetchWarehouses()
    } else {
      toast.add({
        severity: 'error',
        summary: 'Đăng nhập thất bại',
        detail: data.error ? data.error.message : 'Tài khoản hoặc mật khẩu không chính xác.',
        life: 4000
      })
    }
  } catch (e) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi kết nối',
      detail: 'Không thể kết nối tới Identity Service (:8001).',
      life: 4000
    })
  } finally {
    loading.login = false
  }
}

// Logout
const logout = () => {
  jwtToken.value = ''
  currentUser.value = null
  localStorage.removeItem('smart_erp_token')
  toast.add({
    severity: 'info',
    summary: 'Đã đăng xuất',
    detail: 'Đã xóa JWT Token khỏi phiên làm việc.',
    life: 2500
  })
}

// Copy Token
const copyToken = () => {
  navigator.clipboard.writeText(jwtToken.value)
  toast.add({
    severity: 'success',
    summary: 'Đã copy',
    detail: 'Token JWT đã lưu vào Clipboard.',
    life: 2000
  })
}

// Fetch Products
const fetchProducts = async () => {
  try {
    const headers = { 'Accept': 'application/json' }
    if (jwtToken.value) headers['Authorization'] = `Bearer ${jwtToken.value}`
    
    const res = await fetch('/api/v1/catalog/products', { headers })
    const data = await res.json()
    if (res.ok && data.data) {
      products.value = data.data
    }
  } catch (e) {
    console.error('Fetch products error:', e)
  }
}

// Fetch Partners
const fetchPartners = async () => {
  try {
    const res = await fetch('/api/v1/catalog/partners')
    const data = await res.json()
    if (res.ok && data.data) {
      partners.value = data.data
    }
  } catch (e) {
    console.error('Fetch partners error:', e)
  }
}

// Fetch Warehouses
const fetchWarehouses = async () => {
  try {
    const res = await fetch('/api/v1/catalog/warehouses')
    const data = await res.json()
    if (res.ok && data.data) {
      warehouses.value = data.data
    }
  } catch (e) {
    console.error('Fetch warehouses error:', e)
  }
}

// Open Create Modal
const openCreateModal = () => {
  newProduct.sku = 'PROD-DELL-' + Math.floor(100 + Math.random() * 900)
  newProduct.name = 'Thiết Bị Mới ' + newProduct.sku
  newProduct.gross_price = 19500000
  newProduct.min_stock = 10
  showCreateModal.value = true
}

// Submit Create Product
const submitCreateProduct = async () => {
  loading.createProduct = true
  try {
    const headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json'
    }
    if (jwtToken.value) headers['Authorization'] = `Bearer ${jwtToken.value}`

    const res = await fetch('/api/v1/catalog/products', {
      method: 'POST',
      headers,
      body: JSON.stringify({
        sku: newProduct.sku,
        name: newProduct.name,
        gross_price: Number(newProduct.gross_price),
        min_stock: Number(newProduct.min_stock),
        category_id: 1,
        unit_id: 1
      })
    })

    const data = await res.json()

    if (res.status === 201 || (res.ok && data.success)) {
      showCreateModal.value = false
      toast.add({
        severity: 'success',
        summary: 'HTTP 201 CREATED',
        detail: `Sản phẩm ${newProduct.sku} đã được lưu và sinh Transactional Outbox Event!`,
        life: 5000
      })
      fetchProducts()
    } else if (res.status === 403) {
      toast.add({
        severity: 'warn',
        summary: 'HTTP 403 FORBIDDEN (RBAC Blocked)',
        detail: `Từ chối vì tài khoản thiếu quyền 'catalog.create'. Hãy đăng nhập bằng Sales Manager hoặc Admin!`,
        life: 6000
      })
    } else if (res.status === 401) {
      toast.add({
        severity: 'error',
        summary: 'HTTP 401 UNAUTHENTICATED',
        detail: 'Chưa có Token JWT hợp lệ.',
        life: 4000
      })
    } else {
      toast.add({
        severity: 'error',
        summary: 'Lỗi',
        detail: data.error ? data.error.message : 'Có lỗi khi tạo sản phẩm.',
        life: 4000
      })
    }
  } catch (e) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi kết nối',
      detail: 'Không thể kết nối tới Catalog Service (:8002).',
      life: 4000
    })
  } finally {
    loading.createProduct = false
  }
}

onMounted(() => {
  if (jwtToken.value) {
    currentUser.value = parseJwt(jwtToken.value)
  }
  checkHealth()
  fetchProducts()
  fetchPartners()
  fetchWarehouses()

  setInterval(checkHealth, 10000)
})
</script>

<style scoped>
/* Page Layout Styles */
.tab-pane-content {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.mb-4 { margin-bottom: 16px; }
.mt-1 { margin-top: 4px; }
.mt-2 { margin-top: 8px; }
.mt-3 { margin-top: 12px; }
.p-3 { padding: 12px 16px; }
.flex { display: flex; }
.justify-between { justify-content: space-between; }
.items-center { align-items: center; }
.gap-2 { gap: 8px; }

.grid-2col {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.grid-3col {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
}

.text-xs { font-size: 11px; }
.text-sm { font-size: 13px; }
.text-emerald { color: #047857; }
.text-amber { color: #b45309; }

/* Manual Login Strip */
.manual-login-strip {
  border-top: 1px solid var(--border-light);
  padding-top: 14px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.login-inputs-row {
  display: flex;
  gap: 10px;
}

/* Claims List */
.claims-details-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.claim-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 12px;
  background: var(--canvas-bg);
  border-radius: 6px;
}

.raw-token-box-clean {
  background: var(--canvas-bg);
  border: 1px solid var(--border-subtle);
  border-radius: 8px;
  padding: 10px 12px;
}

.token-textarea-clean {
  width: 100%;
  background: var(--surface-white);
  border: 1px solid var(--border-subtle);
  border-radius: 6px;
  padding: 8px;
  font-family: var(--font-mono);
  font-size: 11px;
  color: var(--text-muted);
}

/* Permission Badges */
.permission-tags-container {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

/* RBAC Strip Card */
.rbac-strip-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 18px;
  border-radius: 8px;
  border: 1px solid var(--border-subtle);
}

.strip-success {
  background-color: var(--badge-success-bg);
  border-color: rgba(4, 120, 87, 0.25);
  color: #065f46;
}

.strip-warning {
  background-color: var(--badge-warning-bg);
  border-color: rgba(180, 83, 9, 0.25);
  color: #92400e;
}

/* SKU Clean Tag */
.sku-clean {
  font-family: var(--font-mono);
  font-size: 11px;
  font-weight: 600;
  color: var(--primary);
  background: var(--primary-subtle);
  padding: 2px 6px;
  border-radius: 4px;
}

.search-clean {
  width: 320px;
  background: var(--canvas-bg) !important;
  border: 1px solid var(--border-subtle) !important;
}

/* Warehouses */
.warehouse-cards-col {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.clean-wh-box {
  border: 1px solid var(--border-subtle);
  border-radius: 8px;
  padding: 12px;
  background: var(--surface-white);
}

/* Architecture Boxes */
.arch-box-clean {
  border: 1px solid var(--border-subtle);
  border-radius: 8px;
  padding: 16px;
  background: var(--canvas-bg);
  position: relative;
}

.arch-num-badge {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: var(--primary);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 12px;
  margin-bottom: 8px;
}

.cloudevents-clean-box {
  background: var(--canvas-bg);
  border: 1px solid var(--border-subtle);
  border-radius: 8px;
  padding: 14px;
}

.clean-json-view {
  background: var(--surface-white);
  border: 1px solid var(--border-subtle);
  border-radius: 6px;
  padding: 12px;
  font-family: var(--font-mono);
  font-size: 11px;
  color: var(--primary);
  overflow-x: auto;
}

/* Dialog Form */
.form-clean-layout {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.form-group-clean label {
  display: block;
  font-size: 12px;
  font-weight: 600;
  color: var(--text-body);
  margin-bottom: 4px;
}
</style>