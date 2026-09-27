<template>
  <div class="claims-inspector-panel">
    <div class="panel-header">
      <span class="panel-title">Phiên xác thực hiện tại</span>
      <Tag
        :severity="currentUser ? 'success' : 'danger'"
        :value="currentUser ? 'Đã xác thực' : 'Chưa đăng nhập'"
        class="claims-tag"
      />
    </div>

    <div v-if="currentUser" class="claims-body">
      <div class="claim-item">
        <span class="claim-label">Mã định danh (sub):</span>
        <span class="claim-value">#{{ currentUser.sub }}</span>
      </div>
      <div class="claim-item">
        <span class="claim-label">Họ và tên:</span>
        <span class="claim-value">{{ currentUser.name }}</span>
      </div>
      <div class="claim-item">
        <span class="claim-label">Phòng ban:</span>
        <span class="claim-value">{{ currentUser.department }}</span>
      </div>
      <div class="claim-item">
        <span class="claim-label">Vai trò (Roles):</span>
        <span class="claim-value font-semibold text-primary">{{ currentUser.roles.join(', ') }}</span>
      </div>

      <!-- Token Copy Row -->
      <div class="token-container">
        <div class="token-header">
          <span class="token-label">Chuỗi JWT Bearer Token:</span>
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
          class="token-textarea"
        />
      </div>
    </div>

    <div v-else class="empty-claims">
      <i class="pi pi-lock empty-icon"></i>
      <p class="empty-text">Vui lòng chọn hoặc đăng nhập một tài khoản để xem Claims giải mã.</p>
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

<style scoped>
.claims-inspector-panel {
  background: var(--surface-white);
  border: 1px solid var(--border-subtle);
  border-radius: 6px;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.panel-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-bottom: 8px;
  border-bottom: 1px solid var(--border-subtle);
}

.panel-title {
  font-size: 14px;
  font-weight: 700;
  color: var(--text-main);
}

.claims-tag {
  font-size: 11px;
  padding: 2px 8px;
  border-radius: 6px;
}

.claims-body {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.claim-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 12px;
  background-color: var(--canvas-bg);
  border: 1px solid var(--border-subtle);
  border-radius: 6px;
}

.claim-label {
  font-size: 12px;
  color: var(--text-muted);
}

.claim-value {
  font-size: 12px;
  font-weight: 600;
  color: var(--text-main);
}

.token-container {
  display: flex;
  flex-direction: column;
  gap: 4px;
  margin-top: 4px;
}

.token-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.token-label {
  font-size: 11px;
  font-weight: 600;
  color: var(--text-muted);
}

.token-textarea {
  width: 100%;
  font-family: var(--font-mono);
  font-size: 11px;
  color: var(--text-body);
  border-radius: 6px;
  border: 1px solid var(--border-subtle);
  background-color: var(--canvas-bg);
  resize: none;
}

.empty-claims {
  padding: 24px;
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
}

.empty-icon {
  font-size: 24px;
  color: var(--text-dim);
}

.empty-text {
  font-size: 13px;
  color: var(--text-muted);
  margin: 0;
}
</style>
