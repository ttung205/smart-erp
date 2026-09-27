<template>
  <div class="space-y-3">
    <!-- 1. Explanatory Enterprise Rule Banner -->
    <div class="bg-indigo-50/70 border border-indigo-200 rounded-[6px] p-3 text-xs text-indigo-900 flex items-start gap-3">
      <div class="w-7 h-7 rounded-[4px] bg-indigo-100 text-indigo-700 flex items-center justify-center shrink-0">
        <i class="pi pi-book text-sm"></i>
      </div>
      <div class="space-y-0.5">
        <div class="font-bold text-indigo-900 text-xs uppercase tracking-wide">
          Chính Sách Đối Soát & Ghi Sổ Trừ Kho (Accounting Posting Policy)
        </div>
        <p class="m-0 text-indigo-800 text-[11px] leading-relaxed">
          Theo nguyên tắc kế toán tài chính ERP: Khi hàng hóa được kho giao thành công tới khách hàng (có biên bản bàn giao), Kế toán tiến hành đối soát chứng từ và bấm nút <strong>"Ghi Sổ Kế Toán & Trừ Kho"</strong>. Lúc này hệ thống mới chính thức gửi lệnh gọi API <code>POST /api/v1/inventory/stocks/issue</code> sang <strong>Inventory Service (:8004)</strong> để trừ lượng tồn thực tế (On-Hand) và ghi nhận giá vốn hàng bán (COGS).
        </p>
      </div>
    </div>

    <!-- 2. Action Toolbar -->
    <Toolbar class="bg-white border border-slate-200 rounded-[6px] px-3 py-2 shadow-none">
      <template #start>
        <div class="flex items-center gap-2">
          <IconField>
            <InputIcon class="pi pi-search" />
            <InputText
              v-model="searchQuery"
              placeholder="Tìm theo mã chứng từ, khách hàng..."
              class="w-72 rounded-[6px]"
              size="small"
            />
          </IconField>
          <Select
            v-model="filterStatus"
            :options="filterOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Lọc trạng thái sổ..."
            class="w-48 rounded-[6px]"
            size="small"
          />
        </div>
      </template>

      <template #end>
        <div class="flex items-center gap-2 text-xs">
          <span class="text-amber-700 font-semibold bg-amber-50 border border-amber-200 px-2 py-0.5 rounded-[4px]">
            Chờ ghi sổ: {{ pendingCount }}
          </span>
          <span class="text-emerald-700 font-semibold bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-[4px]">
            Đã ghi sổ: {{ postedCount }}
          </span>
        </div>
      </template>
    </Toolbar>

    <!-- 3. Table -->
    <div class="bg-white border border-slate-200 rounded-[6px] overflow-hidden">
      <DataTable
        :value="filteredInvoices"
        showGridlines
        class="clean-datatable"
        responsiveLayout="scroll"
        :paginator="filteredInvoices.length > 8"
        :rows="8"
      >
        <Column field="invoice_number" header="Mã Chứng Từ (INV)" :sortable="true" style="width: 160px">
          <template #body="{ data }">
            <span class="font-mono text-xs font-semibold text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded-[4px] border border-indigo-200">
              {{ data.invoice_number }}
            </span>
          </template>
        </Column>

        <Column field="customer_name" header="Khách Hàng B2B" :sortable="true">
          <template #body="{ data }">
            <span class="font-semibold text-slate-800 text-xs">{{ data.customer_name }}</span>
          </template>
        </Column>

        <Column field="warehouse_name" header="Kho Xuất Trừ">
          <template #body="{ data }">
            <span class="text-slate-600 text-xs">{{ data.warehouse_name }}</span>
          </template>
        </Column>

        <Column header="Tình Trạng Giao" class="text-center" style="width: 140px">
          <template #body="{ data }">
            <Tag
              v-if="data.warehouse_status === 'DELIVERED'"
              severity="success"
              value="Giao thành công"
              icon="pi pi-check"
              class="text-[10px] px-1.5 py-0.5 rounded-[4px]"
            />
            <Tag
              v-else
              severity="warn"
              value="Đang xử lý tại kho"
              class="text-[10px] px-1.5 py-0.5 rounded-[4px]"
            />
          </template>
        </Column>

        <Column field="total_amount" header="Tổng Doanh Thu" class="text-right" :sortable="true" style="width: 140px">
          <template #body="{ data }">
            <span class="font-mono text-xs font-bold text-slate-900 num-tabular">
              {{ formatCurrency(data.total_amount) }}
            </span>
          </template>
        </Column>

        <Column header="Trạng Thái Sổ Cái" class="text-center" style="width: 170px">
          <template #body="{ data }">
            <div v-if="data.accounting_status === 'POSTED'" class="flex flex-col items-center gap-0.5">
              <Tag
                severity="success"
                value="Đã ghi sổ & Trừ kho"
                icon="pi pi-check-circle"
                class="text-[11px] px-2 py-0.5 rounded-[4px]"
              />
              <span class="text-[10px] font-mono text-slate-400">{{ data.posted_at }}</span>
            </div>
            <Tag
              v-else
              severity="danger"
              value="Chưa ghi sổ (Chưa trừ tồn)"
              icon="pi pi-exclamation-circle"
              class="text-[11px] px-2 py-0.5 rounded-[4px]"
            />
          </template>
        </Column>

        <Column header="Thao Tác Kế Toán" class="text-center" style="width: 190px">
          <template #body="{ data }">
            <div class="flex items-center justify-center gap-1">
              <!-- Nút Ghi sổ -->
              <Button
                v-if="data.accounting_status !== 'POSTED'"
                label="Ghi Sổ & Trừ Kho"
                icon="pi pi-file-edit"
                size="small"
                :disabled="data.warehouse_status !== 'DELIVERED'"
                class="!text-xs !py-1 !px-2.5 rounded-[6px] !bg-emerald-600 hover:!bg-emerald-700 !text-white !border-emerald-600 !font-semibold disabled:!opacity-40"
                v-tooltip.bottom="data.warehouse_status === 'DELIVERED' ? 'Bấm để ghi sổ kế toán và kích hoạt trừ tồn kho thực tế' : 'Cần đợi kho hoàn tất giao hàng thành công'"
                @click="$emit('open-accounting-confirm', data)"
              />

              <div v-else class="text-xs text-emerald-700 font-semibold flex items-center gap-1">
                <i class="pi pi-lock text-[11px]"></i>
                <span>Đã khóa chứng từ</span>
              </div>
            </div>
          </template>
        </Column>

        <template #empty>
          <div class="py-10 text-center flex flex-col items-center gap-2">
            <i class="pi pi-calculator text-3xl text-slate-300"></i>
            <p class="text-xs font-semibold text-slate-700 m-0">Chưa có chứng từ nào sẵn sàng ghi sổ</p>
            <p class="text-[11px] text-slate-400 m-0">Chứng từ cần được chuyển qua kho và giao hàng thành công trước khi kế toán ghi sổ.</p>
          </div>
        </template>
      </DataTable>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Toolbar from 'primevue/toolbar';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import { formatCurrency } from '../../../utils/format';

const props = defineProps({
  invoices: {
    type: Array,
    default: () => []
  }
});

defineEmits(['open-accounting-confirm']);

const searchQuery = ref('');
const filterStatus = ref(null);

const filterOptions = [
  { label: 'Tất cả chứng từ', value: null },
  { label: 'Chờ ghi sổ (Chưa trừ kho)', value: 'UNPOSTED' },
  { label: 'Đã ghi sổ & trừ kho', value: 'POSTED' }
];

const pendingCount = computed(() => {
  return props.invoices.filter((i) => i.accounting_status !== 'POSTED').length;
});

const postedCount = computed(() => {
  return props.invoices.filter((i) => i.accounting_status === 'POSTED').length;
});

const filteredInvoices = computed(() => {
  return props.invoices.filter((inv) => {
    const matchSearch =
      !searchQuery.value ||
      inv.invoice_number.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      inv.customer_name.toLowerCase().includes(searchQuery.value.toLowerCase());
    const matchStatus = !filterStatus.value || inv.accounting_status === filterStatus.value;
    return matchSearch && matchStatus;
  });
});
</script>
