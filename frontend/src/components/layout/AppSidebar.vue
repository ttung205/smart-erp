<template>
  <aside
    class="fixed top-0 bottom-0 left-0 z-50 flex flex-col bg-white border-r border-slate-200 transition-all duration-200 select-none"
    :class="isCollapsed ? 'w-[68px]' : 'w-64'"
  >
    <!-- Brand Header & Collapse Toggle Button -->
    <div
      class="h-14 flex items-center border-b border-slate-200"
      :class="isCollapsed ? 'justify-center px-0' : 'justify-between px-4'"
    >
      <div
        class="flex items-center gap-2.5 cursor-pointer"
        @click="$emit('toggle-collapse')"
        :title="isCollapsed ? 'Bấm để mở rộng sidebar' : 'Bấm để thu gọn sidebar'"
      >
        <div
          class="w-8 h-8 rounded-[6px] bg-sky-50 border border-sky-200 flex items-center justify-center shrink-0"
          v-tooltip.right="isCollapsed ? `${appConfig.appName} ${appConfig.version} (Mở rộng)` : undefined"
        >
          <i class="pi pi-bolt text-sky-600 text-base"></i>
        </div>
        <div v-show="!isCollapsed" class="flex items-center gap-2 whitespace-nowrap">
          <span class="font-bold text-[15px] text-slate-800 tracking-tight">{{ appConfig.appName }}</span>
          <span class="text-[11px] font-semibold bg-slate-50 border border-slate-200 text-sky-600 px-1.5 py-0.5 rounded-[6px]">{{ appConfig.version }}</span>
        </div>
      </div>

      <Button
        v-if="!isCollapsed"
        icon="pi pi-angle-left"
        text
        size="small"
        severity="secondary"
        class="!w-7 !h-7 !p-0"
        @click="$emit('toggle-collapse')"
        v-tooltip.bottom="'Thu gọn menu'"
      />
    </div>

    <!-- Navigation Menu -->
    <nav
      class="flex-1 flex flex-col gap-2 overflow-y-auto overflow-x-hidden"
      :class="isCollapsed ? 'p-2 items-center' : 'p-3'"
    >
      <template v-for="(cat, idx) in menuCategories" :key="idx">
        <span v-if="!isCollapsed" class="text-[11px] font-bold uppercase text-slate-400 tracking-wider px-2 pt-2 pb-1 whitespace-nowrap">
          {{ cat.category }}
        </span>
        <div v-else class="w-7 h-[1px] bg-slate-200 my-1 mx-auto"></div>

        <div class="flex flex-col gap-1 w-full">
          <template v-for="item in cat.items" :key="item.id">
            <!-- 1. Folder có danh mục con (Children) -->
            <div v-if="item.children && item.children.length > 0" class="flex flex-col w-full">
              <!-- Expanded State Folder Header -->
              <button
                v-if="!isCollapsed"
                type="button"
                class="flex items-center justify-between w-full px-3 py-2 rounded-[6px] border border-transparent text-sm font-semibold transition-colors cursor-pointer text-slate-700 hover:bg-slate-100"
                :class="{ 'text-sky-700 bg-sky-50/70': isFolderActive(item) }"
                @click="toggleFolder(item.id)"
              >
                <div class="flex items-center gap-2.5">
                  <i :class="[item.icon, 'text-base shrink-0', isFolderActive(item) ? 'text-sky-700' : 'text-slate-400']"></i>
                  <span class="whitespace-nowrap">{{ item.label }}</span>
                </div>
                <i :class="['text-xs text-slate-400 transition-transform duration-150', openFolders[item.id] ? 'pi pi-chevron-down' : 'pi pi-chevron-right']"></i>
              </button>

              <!-- Collapsed State Folder Button -->
              <button
                v-else
                type="button"
                class="flex items-center justify-center w-11 h-10 mx-auto rounded-[6px] border text-sm font-medium transition-colors cursor-pointer"
                :class="isFolderActive(item) ? 'bg-sky-50 border-sky-200 text-sky-700 font-semibold' : 'border-transparent text-slate-600 hover:bg-slate-100'"
                @click="handleNavigate(item.children[0])"
                v-tooltip.right="item.label"
              >
                <i :class="[item.icon, 'text-base', isFolderActive(item) ? 'text-sky-700' : 'text-slate-400']"></i>
              </button>

              <!-- Folder Sub-items -->
              <div v-if="!isCollapsed && openFolders[item.id]" class="flex flex-col gap-1 pl-3 my-1 border-l-2 border-slate-100 ml-4">
                <button
                  v-for="child in item.children"
                  :key="child.id"
                  type="button"
                  class="flex items-center rounded-[6px] border text-xs font-medium transition-colors cursor-pointer w-full gap-2 px-2.5 py-1.5 text-left"
                  :class="isItemActive(child) ? 'bg-sky-50 border-sky-200 text-sky-700 font-semibold' : 'border-transparent text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
                  @click="handleNavigate(child)"
                >
                  <i :class="[child.icon, 'text-sm shrink-0', isItemActive(child) ? 'text-sky-700' : 'text-slate-400']"></i>
                  <span class="flex-1 whitespace-nowrap overflow-hidden text-ellipsis">{{ child.label }}</span>
                </button>
              </div>
            </div>

            <!-- 2. Mục độc lập (Single item) -->
            <button
              v-else
              type="button"
              class="flex items-center rounded-[6px] border text-sm font-medium transition-colors cursor-pointer group"
              :class="[
                isItemActive(item)
                  ? 'bg-sky-50 border-sky-200 text-sky-700 font-semibold'
                  : 'border-transparent text-slate-600 hover:bg-slate-100 hover:text-slate-900',
                isCollapsed
                  ? 'justify-center w-11 h-10 mx-auto px-0'
                  : 'w-full gap-3 px-3 py-2 text-left'
              ]"
              @click="handleNavigate(item)"
              v-tooltip.right="isCollapsed ? item.label : undefined"
            >
              <i
                :class="[
                  item.icon,
                  'text-base shrink-0 transition-colors',
                  isItemActive(item) ? 'text-sky-700' : 'text-slate-400 group-hover:text-slate-600'
                ]"
              ></i>
              <span v-if="!isCollapsed" class="flex-1 whitespace-nowrap overflow-hidden text-ellipsis">
                {{ item.label }}
              </span>
            </button>
          </template>
        </div>
      </template>
    </nav>

    <!-- Sidebar Footer -->
    <div
      class="border-t border-slate-200"
      :class="isCollapsed ? 'py-3 flex justify-center' : 'p-3'"
    >
      <div v-if="!isCollapsed" class="flex flex-col gap-0.5">
        <span class="text-[11px] text-slate-400">Kiến trúc hệ thống</span>
        <span class="text-[11px] font-semibold text-slate-700">{{ appConfig.architecture }}</span>
      </div>
      <div
        v-else
        class="flex items-center justify-center cursor-help text-slate-400 hover:text-slate-600"
        v-tooltip.right="'Kiến trúc: Database-per-Service & Outbox'"
      >
        <i class="pi pi-server text-base"></i>
      </div>
    </div>
  </aside>
</template>

<script setup>
import { ref, watch, computed } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import Button from 'primevue/button';
import { appConfig } from '../../config/app.config';
import { menuCategories as rawMenuCategories } from '../../config/menu.config';
import { canView } from '../../utils/rbac';

const router = useRouter();
const route = useRoute();

const props = defineProps({
  currentTab: {
    type: String,
    default: ''
  },
  isCollapsed: {
    type: Boolean,
    default: false
  },
  currentUser: {
    type: Object,
    default: null
  }
});

const emit = defineEmits(['select-tab', 'toggle-collapse']);

const openFolders = ref({
  organization: true,
  sales: true,
  'inventory-hub': true
});

const ACTIVE_MENU_STORAGE_KEY = 'smart_erp_active_menu_id';
const activeMenuId = ref(sessionStorage.getItem(ACTIVE_MENU_STORAGE_KEY) || '');

const toggleFolder = (folderId) => {
  openFolders.value[folderId] = !openFolders.value[folderId];
};

// Lọc menu theo quyền người dùng (chỉ hiển thị những trang có quyền .view)
const menuCategories = computed(() => {
  if (!props.currentUser) return rawMenuCategories;
  
  // Super Admin thấy tất cả
  if (props.currentUser.roles && props.currentUser.roles.includes('SUPER_ADMIN')) {
    return rawMenuCategories;
  }

  return rawMenuCategories.map(cat => {
    const filteredItems = cat.items.map(item => {
      if (item.children) {
        const visibleChildren = item.children.filter(child => {
          return !child.resource || canView(child.resource, props.currentUser);
        });
        return { ...item, children: visibleChildren };
      }
      return item;
    }).filter(item => {
      if (item.children) return item.children.length > 0;
      return !item.resource || canView(item.resource, props.currentUser);
    });

    return { ...cat, items: filteredItems };
  }).filter(cat => cat.items.length > 0);
});

const routeMatchesItem = (item) => {
  if (!item.path) return false;
  // 1. Khớp chính xác đường dẫn
  if (route.path === item.path) return true;
  // 2. Khớp các trang con dạng sub-path (ví dụ /delivery/create, /delivery/123/edit)
  if (item.path !== '/' && route.path.startsWith(item.path + '/')) {
    return true;
  }
  // 3. Khớp chính xác tên route
  if (route.name && route.name === item.id) {
    return true;
  }
  // 4. Khớp các trang create / edit theo convention `${item.id}-create`, `${item.id}-edit`, `${item.id}-detail`
  if (route.name && (route.name === `${item.id}-create` || route.name === `${item.id}-edit` || route.name === `${item.id}-detail`)) {
    return true;
  }
  return false;
};

const matchingRouteItems = computed(() => menuCategories.value.flatMap(category =>
  category.items.flatMap(item => item.children || [item])
).filter(routeMatchesItem));

const resolvedActiveMenuId = computed(() => {
  const matches = matchingRouteItems.value;
  if (matches.some(item => item.id === activeMenuId.value)) {
    return activeMenuId.value;
  }
  return matches[0]?.id || '';
});

const isItemActive = (item) => routeMatchesItem(item) && item.id === resolvedActiveMenuId.value;

const isFolderActive = (item) => {
  if (!item.children || item.children.length === 0) return isItemActive(item);
  return item.children.some(child => isItemActive(child));
};

const handleNavigate = (item) => {
  activeMenuId.value = item.id;
  sessionStorage.setItem(ACTIVE_MENU_STORAGE_KEY, item.id);
  emit('select-tab', item.id);
  if (item.path) {
    router.push(item.path);
  }
};

// Tự động mở folder nếu một tab con đang được chọn
watch([() => route.path, matchingRouteItems], ([newPath]) => {
  if (!newPath) return;
  if (resolvedActiveMenuId.value) {
    activeMenuId.value = resolvedActiveMenuId.value;
    sessionStorage.setItem(ACTIVE_MENU_STORAGE_KEY, resolvedActiveMenuId.value);
  }
  for (const cat of menuCategories.value) {
    for (const item of cat.items) {
      if (item.children && item.children.some(c => isItemActive(c))) {
        openFolders.value[item.id] = true;
      }
    }
  }
}, { immediate: true });
</script>
