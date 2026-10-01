<template>
  <ItemDataTable
    :model-value="modelValue"
    @update:model-value="$emit('update:modelValue', $event)"
    :show-item-status="false"
    :show-create-order-button="false"
    v-bind="$attrs"
    @summary-change="$emit('summary-change', $event)"
  >
    <!-- Chuyển tiếp toàn bộ slots nếu có -->
    <template v-for="(_, slotName) in $slots" #[slotName]="slotProps">
      <slot :name="slotName" v-bind="slotProps" />
    </template>
  </ItemDataTable>
</template>

<script setup>
import ItemDataTable from '../common/ItemDataTable.vue';

defineProps({
  modelValue: {
    type: Array,
    default: () => []
  }
});

defineEmits(['update:modelValue', 'summary-change']);
</script>
