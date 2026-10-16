<template>
  <div class="space-y-4 pb-12 max-w-4xl mx-auto">
    <!-- 1. Header Toolbar -->
    <div class="flex items-center justify-between bg-white border border-slate-200 rounded-[6px] px-5 py-3 shadow-none">
      <div class="flex items-center gap-3">
        <Button
          label="Quay lại"
          icon="pi pi-arrow-left"
          size="small"
          severity="secondary"
          outlined
          class="!text-xs !py-1.5 !px-3 !rounded-[6px] !border-slate-300 !text-slate-700 hover:!bg-slate-100"
          @click="$router.push('/chat')"
        />

        <div class="flex flex-col">
          <h1 class="text-base font-bold text-slate-900 m-0 tracking-tight flex items-center gap-2">
            <span>Bắt Đầu Cuộc Trò Chuyện Mới</span>
          </h1>
          <span class="text-xs text-slate-400">
            Khởi tạo cuộc hội thoại trực tiếp với đồng nghiệp hoặc trao đổi trong kênh phòng ban
          </span>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="flex items-center gap-2.5">
        <Button
          label="Hủy"
          size="small"
          severity="secondary"
          outlined
          class="!text-xs !py-2 !px-3.5 !rounded-[6px] !border-slate-300 !text-slate-700 hover:!bg-slate-100"
          @click="$router.push('/chat')"
        />
        <Button
          label="Bắt đầu trò chuyện"
          icon="pi pi-send"
          size="small"
          :loading="submitting"
          :disabled="!isFormValid"
          class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 !text-xs !py-2 !px-4 !rounded-[6px] !font-semibold"
          @click="handleSubmit"
        />
      </div>
    </div>

    <!-- Error Banner -->
    <div v-if="errorMessage" class="p-3 bg-rose-50 border border-rose-200 text-rose-700 text-xs rounded-[6px] flex items-center justify-between">
      <span>{{ errorMessage }}</span>
      <button type="button" @click="errorMessage = ''" class="text-rose-500 hover:text-rose-700 font-bold">&times;</button>
    </div>

    <!-- 2. Form Nội Dung -->
    <div class="bg-white border border-slate-200 rounded-[6px] p-5 space-y-5">
      <!-- Section A: Phân loại hình thức chat -->
      <div>
        <label class="block text-xs font-bold text-slate-700 mb-2">Hình thức trao đổi</label>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div
            class="p-3.5 border rounded-[6px] cursor-pointer transition-colors flex items-start gap-3"
            :class="form.type === 'DIRECT' ? 'border-sky-600 bg-sky-50/50' : 'border-slate-200 hover:bg-slate-50'"
            @click="form.type = 'DIRECT'"
          >
            <input
              type="radio"
              name="chatType"
              value="DIRECT"
              v-model="form.type"
              class="mt-0.5 text-sky-600"
            />
            <div class="flex flex-col">
              <span class="text-xs font-bold text-slate-800">Tin nhắn trực tiếp 1-1</span>
              <span class="text-[11px] text-slate-500 mt-0.5">Nhắn tin riêng tư với đồng nghiệp trong công ty</span>
            </div>
          </div>

          <div
            class="p-3.5 border rounded-[6px] cursor-pointer transition-colors flex items-start gap-3"
            :class="form.type === 'CHANNEL' ? 'border-sky-600 bg-sky-50/50' : 'border-slate-200 hover:bg-slate-50'"
            @click="form.type = 'CHANNEL'"
          >
            <input
              type="radio"
              name="chatType"
              value="CHANNEL"
              v-model="form.type"
              class="mt-0.5 text-sky-600"
            />
            <div class="flex flex-col">
              <span class="text-xs font-bold text-slate-800">Kênh phòng ban</span>
              <span class="text-[11px] text-slate-500 mt-0.5">Gửi tin nhắn vào kênh nghiệp vụ phòng ban</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Section B1: Chọn đồng nghiệp (nếu là Direct) -->
      <div v-if="form.type === 'DIRECT'" class="space-y-3 pt-2 border-t border-slate-100">
        <label class="block text-xs font-bold text-slate-700">Chọn đồng nghiệp nhận tin</label>
        
        <div class="relative w-full">
          <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
          <input
            v-model="userSearchQuery"
            type="text"
            placeholder="Tìm theo tên hoặc email nhân viên..."
            class="w-full pl-8 pr-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-[6px] focus:outline-none focus:border-sky-500 focus:bg-white text-slate-800"
          />
        </div>

        <div v-if="loadingUsers" class="flex justify-center py-6 text-slate-400">
          <i class="pi pi-spin pi-spinner text-sky-600 text-lg"></i>
        </div>

        <div v-else class="max-h-56 overflow-y-auto divide-y divide-slate-100 border border-slate-200 rounded-[6px]">
          <div
            v-for="user in filteredUsers"
            :key="user.id"
            class="p-2.5 flex items-center justify-between cursor-pointer transition-colors"
            :class="form.selectedUserId === user.id ? 'bg-sky-50 text-slate-900 font-semibold' : 'hover:bg-slate-50 text-slate-700'"
            @click="selectUser(user)"
          >
            <div class="flex items-center gap-2.5">
              <div class="relative">
                <Avatar
                  :label="user.name ? user.name.charAt(0) : 'U'"
                  shape="circle"
                  class="!bg-slate-200 !text-slate-700 !font-bold !text-xs !w-8 !h-8"
                />
                <span
                  class="absolute bottom-0 right-0 w-2.5 h-2.5 rounded-full border-2 border-white"
                  :class="chatStore.isUserOnline(user.id) ? 'bg-emerald-500' : 'bg-slate-300'"
                ></span>
              </div>
              <div class="flex flex-col">
                <span class="text-xs text-slate-800">{{ user.name }}</span>
                <span class="text-[11px] text-slate-400">{{ user.department || user.email }}</span>
              </div>
            </div>

            <span
              v-if="form.selectedUserId === user.id"
              class="text-xs text-sky-600 font-semibold flex items-center gap-1"
            >
              <i class="pi pi-check text-xs"></i> Đã chọn
            </span>
          </div>

          <div v-if="filteredUsers.length === 0" class="p-4 text-center text-xs text-slate-400">
            Không tìm thấy nhân viên phù hợp.
          </div>
        </div>
      </div>

      <!-- Section B2: Chọn Kênh phòng ban (nếu là Channel) -->
      <div v-else class="space-y-3 pt-2 border-t border-slate-100">
        <label class="block text-xs font-bold text-slate-700">Chọn kênh phòng ban</label>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2.5">
          <div
            v-for="channel in channelOptions"
            :key="channel.code"
            class="p-3 border rounded-[6px] cursor-pointer transition-colors flex items-center gap-2.5"
            :class="form.selectedChannelCode === channel.code ? 'border-sky-600 bg-sky-50/70 font-semibold text-sky-700' : 'border-slate-200 hover:bg-slate-50 text-slate-700'"
            @click="form.selectedChannelCode = channel.code"
          >
            <div class="w-7 h-7 rounded-[4px] bg-slate-100 border border-slate-200 flex items-center justify-center font-bold text-xs text-slate-600">
              #
            </div>
            <div class="flex flex-col min-w-0">
              <span class="text-xs truncate font-medium">{{ channel.name }}</span>
              <span class="text-[10px] text-slate-400 truncate">{{ channel.description }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Section C: Nội dung tin nhắn đầu tiên -->
      <div class="space-y-1.5 pt-2 border-t border-slate-100">
        <label class="block text-xs font-bold text-slate-700">
          Nội dung tin nhắn đầu tiên <span class="text-rose-500">*</span>
        </label>
        <textarea
          v-model="form.content"
          rows="4"
          placeholder="Nhập nội dung trao đổi..."
          class="w-full p-3 text-xs bg-slate-50 border border-slate-200 rounded-[6px] focus:outline-none focus:border-sky-500 focus:bg-white text-slate-800 leading-relaxed"
        ></textarea>
      </div>

      <!-- Section D: Đính kèm tài liệu liên kết (tùy chọn) -->
      <div class="space-y-1.5">
        <label class="block text-xs font-bold text-slate-700">Đường dẫn tệp đính kèm (tùy chọn)</label>
        <input
          v-model="form.attachmentUrl"
          type="text"
          placeholder="https://... hoặc đường dẫn tài liệu đính kèm"
          class="w-full px-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-[6px] focus:outline-none focus:border-sky-500 focus:bg-white text-slate-700"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useChatStore } from '../../stores/chatStore';
import { identityApi } from '../../api/identity.api';
import Button from 'primevue/button';
import Avatar from 'primevue/avatar';

const props = defineProps({
  currentUser: {
    type: Object,
    default: null
  }
});

const router = useRouter();
const chatStore = useChatStore();

const form = ref({
  type: 'DIRECT', // DIRECT or CHANNEL
  selectedUserId: null,
  selectedUserName: '',
  selectedChannelCode: 'general',
  content: '',
  attachmentUrl: ''
});

const submitting = ref(false);
const errorMessage = ref('');
const usersList = ref([]);
const loadingUsers = ref(false);
const userSearchQuery = ref('');

const channelOptions = [
  { code: 'general', name: '#chung', description: 'Toàn bộ công ty' },
  { code: 'inventory', name: '#kho-van', description: 'Kho Vận & Điều Vận' },
  { code: 'purchase', name: '#thu-mua', description: 'Mua Hàng & Cung Ứng' },
  { code: 'sales', name: '#kinh-doanh', description: 'Kinh Doanh & Bán Hàng' },
  { code: 'finance', name: '#tai-chinh', description: 'Tài Chính & Kế Toán' },
  { code: 'technical', name: '#ky-thuat', description: 'Kỹ Thuật & Công Nghệ' }
];

const filteredUsers = computed(() => {
  const query = userSearchQuery.value.trim().toLowerCase();
  const currentUserId = Number(props.currentUser?.id || props.currentUser?.sub || 0);

  return usersList.value.filter(u => {
    if (Number(u.id) === currentUserId) return false;
    if (!query) return true;
    return (u.name && u.name.toLowerCase().includes(query)) ||
           (u.email && u.email.toLowerCase().includes(query)) ||
           (u.department && u.department.toLowerCase().includes(query));
  });
});

const isFormValid = computed(() => {
  if (!form.value.content.trim()) return false;
  if (form.value.type === 'DIRECT') {
    return form.value.selectedUserId !== null;
  }
  return !!form.value.selectedChannelCode;
});

onMounted(async () => {
  loadingUsers.value = true;
  try {
    const res = await identityApi.getUsers();
    usersList.value = res.data || [];
  } catch (err) {
    console.error('Lỗi tải danh sách người dùng:', err);
  } finally {
    loadingUsers.value = false;
  }
});

function selectUser(user) {
  form.value.selectedUserId = user.id;
  form.value.selectedUserName = user.name;
}

async function handleSubmit() {
  if (!isFormValid.value) return;

  submitting.value = true;
  errorMessage.value = '';

  try {
    if (form.value.type === 'DIRECT') {
      // 1. Tạo hoặc lấy cuộc hội thoại 1-1
      const conv = await chatStore.startDirectChat(form.value.selectedUserId, form.value.selectedUserName);
      // 2. Gửi tin nhắn đầu tiên
      await chatStore.sendMessage(form.value.content.trim(), form.value.attachmentUrl.trim() || null);
      // 3. Điều hướng về trang chat
      router.push('/chat');
    } else {
      // 1. Tìm kênh theo code
      const channelConv = chatStore.conversations.find(c => 
        c.type === 'Channel' && (c.department_code === form.value.selectedChannelCode || c.code === `dept:${form.value.selectedChannelCode}`)
      );

      if (channelConv) {
        await chatStore.selectConversation(channelConv.id);
        await chatStore.sendMessage(form.value.content.trim(), form.value.attachmentUrl.trim() || null);
        router.push('/chat');
      } else {
        await chatStore.fetchConversations();
        router.push('/chat');
      }
    }
  } catch (err) {
    console.error('Lỗi khởi tạo cuộc trò chuyện:', err);
    errorMessage.value = err.message || 'Không thể khởi tạo cuộc hội thoại. Vui lòng kiểm tra lại kết nối.';
  } finally {
    submitting.value = false;
  }
}
</script>
