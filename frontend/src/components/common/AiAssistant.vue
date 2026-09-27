<template>
  <div>
    <!-- Floating Trigger Button (Solid Color, Bo góc 6px, No Gradient) -->
    <button
      class="assistant-trigger-btn"
      @click="visible = !visible"
      v-tooltip.left="'Trợ lý ERP hỗ trợ tra cứu'"
    >
      <i class="pi pi-sparkles"></i>
      <span>Trợ lý ERP</span>
    </button>

    <!-- Assistant Panel (Solid, Flat, Bo góc 6px, Hairline Border) -->
    <div v-if="visible" class="assistant-panel">
      <div class="panel-header">
        <div class="panel-title-box">
          <i class="pi pi-sparkles text-primary"></i>
          <span class="panel-title">Trợ lý nghiệp vụ ERP</span>
        </div>
        <Button
          icon="pi pi-times"
          text
          size="small"
          severity="secondary"
          @click="visible = false"
        />
      </div>

      <div class="panel-body">
        <div class="ai-message-item">
          <i class="pi pi-info-circle text-primary msg-icon"></i>
          <div class="msg-text">
            <span class="font-semibold text-xs text-main">Hỗ trợ tra cứu nhanh:</span>
            <p class="text-xs text-body m-0 mt-1">
              Xin chào <strong>{{ userName }}</strong>! Bạn có thể yêu cầu tôi kiểm tra quyền hạn RBAC, tra cứu mã SKU hoặc thống kê nhanh danh mục hàng hóa.
            </p>
          </div>
        </div>
      </div>

      <div class="panel-footer">
        <InputText
          v-model="inputQuery"
          placeholder="Nhập nội dung cần hỗ trợ..."
          size="small"
          class="flex-1"
          @keyup.enter="handleSend"
        />
        <Button
          icon="pi pi-send"
          size="small"
          @click="handleSend"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';

defineProps({
  userName: {
    type: String,
    default: 'người dùng'
  }
});

const visible = ref(false);
const inputQuery = ref('');

const handleSend = () => {
  if (!inputQuery.value.trim()) return;
  inputQuery.value = '';
};
</script>

<style scoped>
.assistant-trigger-btn {
  position: fixed;
  bottom: 24px;
  right: 24px;
  z-index: 990;
  background-color: var(--primary);
  color: #ffffff;
  padding: 8px 16px;
  border-radius: 6px;
  border: 1px solid var(--primary-border);
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: background-color 0.15s ease;
}

.assistant-trigger-btn:hover {
  background-color: var(--primary-hover);
}

.assistant-panel {
  position: fixed;
  bottom: 72px;
  right: 24px;
  width: 360px;
  background-color: var(--surface-white);
  border: 1px solid var(--border-subtle);
  border-radius: 6px;
  z-index: 1000;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.panel-header {
  padding: 10px 16px;
  background-color: var(--canvas-bg);
  border-bottom: 1px solid var(--border-subtle);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.panel-title-box {
  display: flex;
  align-items: center;
  gap: 8px;
}

.panel-title {
  font-size: 13px;
  font-weight: 700;
  color: var(--text-main);
}

.panel-body {
  padding: 16px;
  max-height: 320px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.ai-message-item {
  background-color: var(--canvas-bg);
  border: 1px solid var(--border-subtle);
  border-radius: 6px;
  padding: 10px 12px;
  display: flex;
  gap: 10px;
  align-items: flex-start;
}

.msg-icon {
  margin-top: 2px;
  font-size: 14px;
}

.msg-text {
  flex: 1;
}

.panel-footer {
  padding: 10px 16px;
  border-top: 1px solid var(--border-subtle);
  display: flex;
  gap: 8px;
  background-color: var(--surface-white);
}

.flex-1 {
  flex: 1;
}

.mt-1 {
  margin-top: 4px;
}
</style>
