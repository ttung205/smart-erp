<template>
  <div class="bg-white border border-slate-200 rounded-[6px] p-4 flex flex-col gap-3 shadow-none">
    <div class="flex justify-between items-center pb-2 border-b border-slate-200">
      <span class="text-sm font-bold text-slate-900">Phiên xác thực hiện tại</span>
      <Tag
        :severity="currentUser ? 'success' : 'danger'"
        :value="currentUser ? 'Đã xác thực' : 'Chưa đăng nhập'"
        class="text-[11px] px-2 py-0.5 rounded-[6px]"
      />
    </div>

    <div v-if="currentUser" class="flex flex-col gap-2">
      <div class="flex justify-between items-center px-3 py-2 bg-slate-50 border border-slate-200 rounded-[6px]">
        <span class="text-xs text-slate-500">Mã định danh (sub):</span>
        <span class="text-xs font-semibold text-slate-900">#{{ currentUser.sub }}</span>
      </div>
      <div class="flex justify-between items-center px-3 py-2 bg-slate-50 border border-slate-200 rounded-[6px]">
        <span class="text-xs text-slate-500">Họ và tên:</span>
        <span class="text-xs font-semibold text-slate-900">{{ currentUser.name }}</span>
      </div>
      <div class="flex justify-between items-center px-3 py-2 bg-slate-50 border border-slate-200 rounded-[6px]">
        <span class="text-xs text-slate-500">Email:</span>
        <span class="text-xs font-semibold font-mono text-slate-900">{{ currentUser.email }}</span>
      </div>
      <div class="flex justify-between items-center px-3 py-2 bg-slate-50 border border-slate-200 rounded-[6px]">
        <span class="text-xs text-slate-500">Vai trò (Roles):</span>
        <span class="text-xs font-semibold text-indigo-600">{{ currentUser.roles ? currentUser.roles.join(', ') : 'Chưa gán' }}</span>
      </div>

      <!-- Token Copy Row -->
      <div class="flex flex-col gap-1 mt-1">
        <div class="flex justify-between items-center">
          <span class="text-[11px] font-semibold text-slate-500">Chuỗi JWT Bearer Token:</span>
          <Button
            label="Sao chép"
            icon="pi pi-copy"
            text
            size="small"
            @click="$emit('copy-token')"
          />
        </div>
        <Textarea
          readonly
          :value="jwtToken"
          rows="2"
          class="w-full font-mono text-[11px] text-slate-700 rounded-[6px] border border-slate-200 bg-slate-50 resize-none"
        />
      </div>
    </div>

    <div v-else class="p-6 text-center flex flex-col items-center gap-2">
      <i class="pi pi-lock text-2xl text-slate-400"></i>
      <p class="text-xs text-slate-500 m-0">Vui lòng đăng nhập để xem thông tin Claims giải mã.</p>
    </div>
  </div>
</template>

<script setup>
import Tag from 'primevue/tag';
import Button from 'primevue/button';
import Textarea from 'primevue/textarea';

defineProps({
  currentUser: {
    type: Object,
    default: null
  },
  jwtToken: {
    type: String,
    default: ''
  }
});

defineEmits(['copy-token']);
</script>
