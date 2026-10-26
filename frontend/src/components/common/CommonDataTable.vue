<template>
  <div class="bg-white border border-slate-200 rounded-[6px] overflow-hidden">
    <DataTable
      :value="filteredData"
      showGridlines
      class="clean-datatable"
      responsiveLayout="scroll"
      :paginator="paginator"
      :rows="rows"
      :first="first"
      :lazy="lazy"
      :totalRecords="totalRecords"
      :rowsPerPageOptions="rowsPerPageOptions"
      :loading="loading"
      :sortField="defaultSortField"
      :sortOrder="defaultSortOrder"
      :scrollable="scrollable"
      :scrollHeight="scrollHeight"
      :tableStyle="tableStyle"
      :resizableColumns="resizableColumns"
      :columnResizeMode="columnResizeMode"
      :rowHover="rowHover"
      @row-click="emit('row-click', $event)"
      @page="emit('page', $event)"
    >
      <!-- Optional slot for custom columns or prepend -->
      <slot name="prepend-columns"></slot>

      <!-- Default columns loop if columns prop is provided -->
      <template v-if="columns && columns.length > 0">
        <Column
          v-for="col in columns"
          :key="col.field"
          :field="col.field"
          :header="col.header"
          :sortable="col.sortable ?? true"
          :style="{ width: col.width || 'auto' }"
          :class="col.align ? `text-${col.align}` : ''"
        >
          <template #body="{ data, field }">
            <slot :name="`col-${field}`" :data="data" :value="data[field]">
              <span class="text-xs text-slate-800">{{ data[field] }}</span>
            </slot>
          </template>
        </Column>
      </template>

      <!-- Dynamic slot for custom columns (action, etc.) -->
      <slot></slot>

      <!-- Empty state template with custom slot support -->
      <template #empty>
        <slot name="empty">
          <div class="py-10 text-center flex flex-col items-center gap-2">
            <i :class="[emptyIcon || 'pi pi-inbox', 'text-3xl text-slate-300']"></i>
            <p class="text-xs font-semibold text-slate-700 m-0">{{ emptyTitle || 'Chưa có dữ liệu nào' }}</p>
            <p class="text-[11px] text-slate-400 m-0">{{ emptySubtitle || 'Không tìm thấy bản ghi phù hợp với điều kiện lọc.' }}</p>
          </div>
        </slot>
      </template>
    </DataTable>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';

const props = defineProps({
  value: {
    type: Array,
    default: () => []
  },
  columns: {
    type: Array,
    default: () => []
  },
  loading: {
    type: Boolean,
    default: false
  },
  paginator: {
    type: Boolean,
    default: true
  },
  rows: {
    type: Number,
    default: 10
  },
  first: {
    type: Number,
    default: 0
  },
  lazy: {
    type: Boolean,
    default: false
  },
  totalRecords: {
    type: Number,
    default: 0
  },
  rowsPerPageOptions: {
    type: Array,
    default: () => [10, 20, 50]
  },
  scrollable: {
    type: Boolean,
    default: false
  },
  scrollHeight: {
    type: String,
    default: null
  },
  tableStyle: {
    type: Object,
    default: null
  },
  resizableColumns: {
    type: Boolean,
    default: false
  },
  columnResizeMode: {
    type: String,
    default: 'fit'
  },
  rowHover: {
    type: Boolean,
    default: false
  },
  searchQuery: {
    type: String,
    default: ''
  },
  searchFields: {
    type: Array,
    default: () => []
  },
  defaultSortField: {
    type: String,
    default: ''
  },
  defaultSortOrder: {
    type: Number,
    default: -1
  },
  emptyIcon: {
    type: String,
    default: 'pi pi-inbox'
  },
  emptyTitle: {
    type: String,
    default: 'Chưa có dữ liệu nào'
  },
  emptySubtitle: {
    type: String,
    default: 'Không tìm thấy dữ liệu phù hợp.'
  }
});

const emit = defineEmits(['row-click', 'page']);

const filteredData = computed(() => {
  if (!props.searchQuery || props.searchQuery.trim() === '') {
    return props.value;
  }
  const q = props.searchQuery.toLowerCase().trim();
  const fields = props.searchFields.length > 0 ? props.searchFields : props.columns.map((c) => c.field);

  return props.value.filter((item) => {
    return fields.some((f) => {
      const val = item[f];
      if (val === null || val === undefined) return false;
      return String(val).toLowerCase().includes(q);
    });
  });
});
</script>
