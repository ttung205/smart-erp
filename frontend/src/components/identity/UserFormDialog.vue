<template>
  <Dialog
    :visible="visible"
    @update:visible="$emit('update:visible', $event)"
    modal
    :header="isEditing ? 'Chỉnh Sửa Thông Tin Nhân Viên' : 'Thêm Nhân Viên Mới'"
    :style="{ width: '500px' }"
  >
    <div class="py-2 flex flex-col gap-4 text-xs">
      <!-- Họ tên -->
      <div class="flex flex-col gap-1.5">
        <label class="block text-xs font-semibold text-slate-700">Họ và Tên *</label>
        <InputText
          v-model="form.name"
          placeholder="Nhập họ và tên nhân viên..."
          size="small"
          class="!rounded-[6px]"
        />
      </div>

      <!-- Mã nhân viên: chỉ Super Admin được đổi 4 số, tiền tố do công ty cấu hình -->
      <div v-if="isEditing" class="flex flex-col gap-1.5">
        <label class="block text-xs font-semibold text-slate-700">Mã Nhân Viên</label>
        <div v-if="codePrefix" class="flex items-center gap-2">
          <span class="px-3 py-1.5 rounded-[6px] border border-slate-200 bg-slate-50 font-mono text-xs font-semibold text-slate-700">{{ codePrefix }}-</span>
          <InputText
            v-model="form.employee_code_digits"
            placeholder="4 chữ số"
            size="small"
            maxlength="4"
            inputmode="numeric"
            class="!rounded-[6px] font-mono w-28"
            :disabled="!canEditLogin"
          />
        </div>
        <span v-else class="font-mono text-xs text-slate-500">Chưa có mã. Cấu hình tiền tố ở màn hình Công ty.</span>
        <span v-if="!canEditLogin" class="text-[11px] text-slate-500">Chỉ Super Admin được đổi mã nhân viên.</span>
      </div>

      <!-- Email -->
      <div class="flex flex-col gap-1.5">
        <label class="block text-xs font-semibold text-slate-700">Địa Chỉ Email *</label>
        <InputText
          v-model="form.email"
          placeholder="email@example.com"
          size="small"
          class="!rounded-[6px] font-mono"
          :disabled="isEditing && !canEditLogin"
        />
        <span v-if="isEditing && !canEditLogin" class="text-[11px] text-slate-500">Chỉ Super Admin được đổi email. OTP đăng nhập gửi về email này.</span>
      </div>

      <!-- Mật khẩu -->
      <div class="flex flex-col gap-1.5">
        <label class="block text-xs font-semibold text-slate-700">
          {{ isEditing ? 'Mật Khẩu Mới (Để trống nếu giữ nguyên)' : 'Mật Khẩu Khởi Tạo *' }}
        </label>
        <InputText
          v-model="form.password"
          type="password"
          placeholder="Tối thiểu 6 ký tự..."
          size="small"
          class="!rounded-[6px]"
        />
      </div>

      <!-- Phòng ban -->
      <div class="flex flex-col gap-1.5">
        <label class="block text-xs font-semibold text-slate-700">Phòng Ban</label>
        <Select
          v-model="form.department_id"
          :options="departments"
          optionLabel="name"
          optionValue="id"
          placeholder="Chọn phòng ban trực thuộc..."
          size="small"
          class="w-full !rounded-[6px]"
        />
      </div>

      <!-- Quản lý trực tiếp -->
      <div class="flex flex-col gap-1.5">
        <label class="block text-xs font-semibold text-slate-700">Quản Lý Trực Tiếp (Line Manager)</label>
        <Select
          v-model="form.line_manager_id"
          :options="managerOptions"
          optionLabel="name"
          optionValue="id"
          placeholder="Để trống: đơn E-Office do trưởng phòng duyệt"
          filter
          showClear
          size="small"
          class="w-full !rounded-[6px]"
        >
          <template #option="{ option }">
            <div class="flex flex-col">
              <span class="text-xs text-slate-800">{{ option.name }}</span>
              <span class="text-[11px] text-slate-500">{{ option.department_name || option.email }}</span>
            </div>
          </template>
        </Select>
      </div>

      <!-- Phân vai trò -->
      <div class="flex flex-col gap-1.5">
        <label class="block text-xs font-semibold text-slate-700">Vai Trò Đảm Nhiệm</label>
        <MultiSelect
          v-model="form.role_ids"
          :options="roles"
          optionLabel="name"
          optionValue="id"
          placeholder="Chọn một hoặc nhiều vai trò..."
          size="small"
          display="chip"
          class="w-full !rounded-[6px]"
        />
      </div>

      <!-- Trạng thái hoạt động -->
      <div class="flex items-center gap-2 pt-1">
        <Checkbox
          v-model="form.is_active"
          :binary="true"
          inputId="userActiveInput"
        />
        <label for="userActiveInput" class="text-xs font-medium text-slate-700 cursor-pointer select-none">
          Tài khoản đang hoạt động (Cho phép đăng nhập)
        </label>
      </div>
    </div>

    <template #footer>
      <div class="flex items-center justify-end gap-2 w-full pt-2">
        <Button
          label="Hủy Bỏ"
          text
          size="small"
          severity="secondary"
          class="!rounded-[6px]"
          @click="$emit('update:visible', false)"
        />
        <Button
          :label="isEditing ? 'Lưu Thay Đổi' : 'Tạo Tài Khoản'"
          size="small"
          :loading="loading"
          class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 !rounded-[6px]"
          @click="$emit('save', form)"
        />
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import MultiSelect from 'primevue/multiselect';
import Checkbox from 'primevue/checkbox';
import Button from 'primevue/button';

defineProps({
  canEditLogin: {
    type: Boolean,
    default: false
  },
  codePrefix: {
    type: String,
    default: ''
  },
  visible: {
    type: Boolean,
    default: false
  },
  isEditing: {
    type: Boolean,
    default: false
  },
  departments: {
    type: Array,
    default: () => []
  },
  roles: {
    type: Array,
    default: () => []
  },
  managerOptions: {
    type: Array,
    default: () => []
  },
  loading: {
    type: Boolean,
    default: false
  }
});

defineEmits(['update:visible', 'save']);

// Form do trang cha sở hữu; defineModel cho phép v-model trên các trường mà không sửa prop trực tiếp
const form = defineModel('form', { type: Object, default: () => ({}) });
</script>
