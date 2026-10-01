<template>
  <ItemDataTable
    :model-value="modelValue"
    @update:model-value="$emit('update:modelValue', $event)"
    :show-item-status="computedShowStatus"
    :show-create-order-button="computedShowStatus"
    v-bind="$attrs"
    @summary-change="$emit('summary-change', $event)"
    @create-order="$emit('create-order', $event)"
  >
    <!-- Chuyển tiếp toàn bộ slots nếu có -->
    <template v-for="(_, slotName) in $slots" #[slotName]="slotProps">
      <slot :name="slotName" v-bind="slotProps" />
    </template>
  </ItemDataTable>
</template>

<script setup>
import { computed } from 'vue';
import ItemDataTable from '../common/ItemDataTable.vue';

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => []
  },
  // Có thể truyền trực tiếp boolean isApproved hoặc chuỗi quotationStatus
  isApproved: {
    type: Boolean,
    default: false
  },
  quotationStatus: {
    type: String,
    default: ''
  }
});

const emit = defineEmits(['update:modelValue', 'summary-change', 'create-order']);

// Cột Status mặt hàng và nút Tạo đơn bán (SO) chỉ hiển thị khi báo giá ở trạng thái APPROVED
const computedShowStatus = computed(() => {
  return props.isApproved || props.quotationStatus === 'APPROVED';
});
</script>
