<template>
  <div class="bg-white border border-slate-200 rounded-[6px] overflow-hidden">
    <!-- Tìm theo ID (lọc trên dữ liệu đã tải; bảng phân trang phía máy chủ thì ẩn vì chỉ lọc được trang hiện tại) -->
    <div v-if="idSearchVisible" class="flex items-center justify-end gap-2 px-3 py-2 border-b border-slate-200 bg-white">
      <IconField>
        <InputIcon class="pi pi-hashtag" />
        <InputText
          v-model="idQuery"
          size="small"
          class="w-56 rounded-[6px]"
          placeholder="Tìm theo ID, ví dụ 120 hoặc 120, 135"
          aria-label="Tìm theo ID"
        />
      </IconField>
      <Button
        v-if="idQuery"
        icon="pi pi-times"
        text
        size="small"
        severity="secondary"
        aria-label="Xóa tìm theo ID"
        @click="idQuery = ''"
      />
    </div>
    <DataTable
      :value="filteredData"
      showGridlines
      class="clean-datatable"
      responsiveLayout="scroll"
      :paginator="paginator"
      :rows="rows"
      :first="firstRow"
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
      @page="onPage"
    >
      <!-- Optional slot for custom columns or prepend -->
      <slot name="prepend-columns"></slot>

      <!-- Cột ID tự thêm khi dữ liệu có id và bảng chưa tự khai báo cột ID -->
      <Column v-if="idColumnVisible" field="id" header="ID" sortable style="width: 90px" class="text-center">
        <template #body="{ data }">
          <slot name="col-id" :data="data" :value="data.id">
            <span class="font-mono text-xs font-bold text-sky-700 bg-sky-50 px-2 py-0.5 rounded-[4px] border border-sky-200 inline-block whitespace-nowrap">
              #{{ data.id }}
            </span>
          </slot>
        </template>
      </Column>

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
import { computed, ref, watch } from 'vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';

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
  },
  // Tự thêm cột ID khi dữ liệu có trường id và `columns` chưa có cột field = 'id'
  showIdColumn: {
    type: Boolean,
    default: true
  },
  // Ô tìm theo ID phía trên bảng; tự ẩn khi bảng phân trang phía máy chủ (lazy) hoặc dữ liệu không có id
  showIdSearch: {
    type: Boolean,
    default: true
  }
});

const emit = defineEmits(['row-click', 'page']);

const idQuery = ref('');
const firstRow = ref(props.first);

const hasIdData = computed(() => props.value.some((item) => item && item.id !== undefined && item.id !== null));
const hasIdColumn = computed(() => props.columns.some((c) => c.field === 'id'));
const idColumnVisible = computed(() => props.showIdColumn && hasIdData.value && !hasIdColumn.value);
const idSearchVisible = computed(() => props.showIdSearch && !props.lazy && hasIdData.value);

/** "120, 135" hoặc "#120 135" -> ['120', '135'] (không phân biệt hoa thường, bỏ dấu # đầu mỗi mã). */
const idTokens = computed(() =>
  idQuery.value
    .split(/[\s,;]+/)
    .map((token) => token.replace(/^#/, '').trim().toLowerCase())
    .filter(Boolean)
);

const filteredData = computed(() => {
  let list = props.value;

  if (props.searchQuery && props.searchQuery.trim() !== '') {
    const q = props.searchQuery.toLowerCase().trim();
    const fields = props.searchFields.length > 0 ? props.searchFields : props.columns.map((c) => c.field);
    list = list.filter((item) => {
      return fields.some((f) => {
        const val = item[f];
        if (val === null || val === undefined) return false;
        return String(val).toLowerCase().includes(q);
      });
    });
  }

  // ID khớp chính xác (một hoặc nhiều mã); chỉ áp dụng khi ô tìm theo ID đang hiện
  if (idSearchVisible.value && idTokens.value.length > 0) {
    list = list.filter((item) => item && item.id !== undefined && item.id !== null
      && idTokens.value.includes(String(item.id).toLowerCase()));
  }

  return list;
});

// Bảng tự quản vị trí trang để về trang đầu khi lọc theo ID; bảng lazy vẫn theo `first` do trang cha điều khiển
watch(() => props.first, (value) => { firstRow.value = value; });
watch(idQuery, () => { firstRow.value = 0; });

const onPage = (event) => {
  firstRow.value = event.first;
  emit('page', event);
};
</script>
