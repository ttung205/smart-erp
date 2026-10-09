<template>
  <div class="space-y-4 pb-8">
    <PageHeader
      title="Quản Lý Xuất Hóa Đơn GTGT (VAT Invoice)"
      subtitle="Quản lý nghiệp vụ xuất hóa đơn điện tử GTGT theo từng chứng từ, kiểm soát đối chiếu giữa hàng đã xuất kho và hóa đơn đã lập"
      badge="Kế Toán & Thuế"
      badge-severity="success"
    />

    <!-- Summary KPI Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
      <div class="bg-white border border-slate-200 rounded-[6px] p-3.5 shadow-none flex items-center justify-between">
        <div>
          <div class="text-[11px] font-bold text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
            <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
            Tổng Chứng Từ Xuất Kho
          </div>
          <div class="text-xl font-black text-slate-800 mt-1 font-mono">
            {{ allInvoices.length }} <span class="text-xs font-normal text-slate-500">chứng từ</span>
          </div>
          <div class="text-[11px] text-slate-500 mt-0.5">
            Tổng giá trị xuất: <span class="font-bold text-slate-800 font-mono">{{ formatCurrency(totalConsolidatedIssuedAmount) }}</span>
          </div>
        </div>
        <div class="w-10 h-10 rounded-[6px] bg-slate-50 border border-slate-200 flex items-center justify-center text-indigo-600">
          <i class="pi pi-database text-lg"></i>
        </div>
      </div>

      <div class="bg-white border border-slate-200 rounded-[6px] p-3.5 shadow-none flex items-center justify-between">
        <div>
          <div class="text-[11px] font-bold text-amber-700 uppercase tracking-wider flex items-center gap-1.5">
            <span class="w-2 h-2 rounded-full bg-amber-500"></span>
            Chứng Từ Chờ Xuất HĐ
          </div>
          <div class="text-xl font-black text-slate-800 mt-1 font-mono">
            {{ unbilledInvoices.length }} <span class="text-xs font-normal text-slate-500">chứng từ</span>
          </div>
          <div class="text-[11px] text-slate-500 mt-0.5">
            Giá trị chưa xuất: <span class="font-bold text-amber-700 font-mono">{{ formatCurrency(totalUnbilledAmount) }}</span>
          </div>
        </div>
        <div class="w-10 h-10 rounded-[6px] bg-amber-50 border border-amber-200 flex items-center justify-center text-amber-600">
          <i class="pi pi-file text-lg"></i>
        </div>
      </div>

      <div class="bg-white border border-slate-200 rounded-[6px] p-3.5 shadow-none flex items-center justify-between">
        <div>
          <div class="text-[11px] font-bold text-emerald-700 uppercase tracking-wider flex items-center gap-1.5">
            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
            Hóa Đơn GTGT Đã Lập
          </div>
          <div class="text-xl font-black text-slate-800 mt-1 font-mono">
            {{ processedVatInvoices.length }} <span class="text-xs font-normal text-slate-500">hóa đơn</span>
          </div>
          <div class="text-[11px] text-slate-500 mt-0.5">
            Giá trị đã xuất HĐ: <span class="font-bold text-emerald-700 font-mono">{{ formatCurrency(totalInvoicedRevenue) }}</span>
          </div>
        </div>
        <div class="w-10 h-10 rounded-[6px] bg-emerald-50 border border-emerald-200 flex items-center justify-center text-emerald-600">
          <i class="pi pi-receipt text-lg"></i>
        </div>
      </div>

      <div class="bg-white border border-slate-200 rounded-[6px] p-3.5 shadow-none flex items-center justify-between">
        <div>
          <div class="text-[11px] font-bold text-indigo-700 uppercase tracking-wider flex items-center gap-1.5">
            <span class="w-2 h-2 rounded-full bg-blue-500"></span>
            Tổng Thuế GTGT Đầu Ra
          </div>
          <div class="text-xl font-black text-indigo-900 mt-1 font-mono">
            {{ formatCurrency(totalVatAmount) }}
          </div>
          <div class="text-[11px] text-slate-500 mt-0.5">
            HĐ kế tiếp: <span class="font-bold text-indigo-700 font-mono">{{ nextInvoiceInfo.next_number || '00000001' }}</span>
          </div>
        </div>
        <div class="w-10 h-10 rounded-[6px] bg-indigo-50 border border-indigo-200 flex items-center justify-center text-indigo-600">
          <i class="pi pi-hashtag text-lg"></i>
        </div>
      </div>
    </div>

    <!-- Tabs Container -->
    <div class="bg-white border border-slate-200 rounded-[6px] shadow-none overflow-hidden">
      <div class="border-b border-slate-200 bg-slate-50/70 px-3 pt-2 flex items-center justify-between">
        <div class="flex items-center gap-1">
          <!-- TAB 1: TỔNG HỢP TẤT CẢ CHỨNG TỪ -->
          <button
            type="button"
            class="px-4 py-2 text-xs font-bold rounded-t-[6px] border-t border-l border-r transition-all flex items-center gap-2"
            :class="activeTab === 'all' 
              ? 'bg-white text-indigo-700 border-slate-200 shadow-sm border-b-transparent -mb-px' 
              : 'text-slate-600 hover:text-slate-900 border-transparent'"
            @click="activeTab = 'all'"
          >
            <i class="pi pi-th-large text-xs"></i>
            <span>Tổng Hợp Tất Cả Chứng Từ</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono"
              :class="activeTab === 'all' ? 'bg-indigo-100 text-indigo-800' : 'bg-slate-200 text-slate-700'">
              {{ allInvoices.length }}
            </span>
          </button>

          <!-- TAB 2: CHỨNG TỪ CHỜ XUẤT HÓA ĐƠN -->
          <button
            type="button"
            class="px-4 py-2 text-xs font-bold rounded-t-[6px] border-t border-l border-r transition-all flex items-center gap-2"
            :class="activeTab === 'unbilled' 
              ? 'bg-white text-indigo-700 border-slate-200 shadow-sm border-b-transparent -mb-px' 
              : 'text-slate-600 hover:text-slate-900 border-transparent'"
            @click="activeTab = 'unbilled'"
          >
            <i class="pi pi-inbox text-xs"></i>
            <span>Chứng Từ Chờ Xuất Hóa Đơn</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono"
              :class="activeTab === 'unbilled' ? 'bg-amber-100 text-amber-800' : 'bg-slate-200 text-slate-700'">
              {{ unbilledInvoices.length }}
            </span>
          </button>

          <!-- TAB 3: HÓA ĐƠN GTGT ĐÃ PHÁT HÀNH -->
          <button
            type="button"
            class="px-4 py-2 text-xs font-bold rounded-t-[6px] border-t border-l border-r transition-all flex items-center gap-2"
            :class="activeTab === 'invoiced' 
              ? 'bg-white text-indigo-700 border-slate-200 shadow-sm border-b-transparent -mb-px' 
              : 'text-slate-600 hover:text-slate-900 border-transparent'"
            @click="activeTab = 'invoiced'"
          >
            <i class="pi pi-receipt text-xs"></i>
            <span>Hóa Đơn GTGT Đã Phát Hành</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono"
              :class="activeTab === 'invoiced' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700'">
              {{ processedVatInvoices.length }}
            </span>
          </button>
        </div>

        <div class="pb-1.5 flex items-center gap-2">
          <Button
            icon="pi pi-refresh"
            size="small"
            outlined
            severity="secondary"
            class="!text-xs !py-1 !px-2.5 rounded-[6px]"
            label="Làm mới"
            :loading="loading"
            @click="loadAllData"
          />
        </div>
      </div>

      <!-- TAB 1: DANH SÁCH TỔNG HỢP TẤT CẢ CHỨNG TỪ (ĐỐI SOÁT TOÀN DIỆN) -->
      <div v-if="activeTab === 'all'" class="p-3 space-y-3">
        <!-- Toolbar lọc -->
        <Toolbar class="bg-slate-50/50 border border-slate-200 rounded-[6px] px-3 py-2 shadow-none">
          <template #start>
            <div class="flex items-center gap-2">
              <IconField>
                <InputIcon class="pi pi-search" />
                <InputText
                  v-model="allSearch"
                  placeholder="Tìm theo số chứng từ, khách hàng, MST, mã hàng..."
                  class="w-80 rounded-[6px]"
                  size="small"
                />
              </IconField>
            </div>
          </template>

          <template #end>
            <div class="flex items-center gap-2">
              <span class="text-xs text-slate-500">
                Tổng số: <strong class="text-slate-800">{{ filteredAllInvoices.length }}</strong> chứng từ xuất kho
              </span>
            </div>
          </template>
        </Toolbar>

        <!-- Bảng Tổng Hợp -->
        <CommonDataTable
          :value="filteredAllInvoices"
          :columns="consolidatedColumns"
          :rows="10"
          :loading="loading"
          :paginator="filteredAllInvoices.length > 10"
          emptyIcon="pi pi-inbox"
          emptyTitle="Chưa có chứng từ xuất kho nào"
          emptySubtitle="Các chứng từ xuất kho sau khi ghi sổ sẽ tự động xuất hiện tại đây để đối soát và quản lý xuất hóa đơn GTGT."
        >
          <!-- Số chứng từ bán -->
          <template #col-invoice_number="{ data }">
            <button
              type="button"
              class="font-mono text-xs font-bold text-indigo-700 hover:text-indigo-900 bg-indigo-50 hover:bg-indigo-100 px-2 py-0.5 rounded-[4px] border border-indigo-200 inline-block transition-colors"
              @click="openConsolidatedDetail(data)"
            >
              {{ data.invoice_number }}
            </button>
          </template>

          <!-- Ngày xuất kho / ghi sổ -->
          <template #col-warehouse_posted_at="{ data }">
            <span class="font-mono text-xs text-slate-600">
              {{ formatDate(data.warehouse_posted_at) }}
            </span>
          </template>

          <!-- Khách hàng -->
          <template #col-customer_name="{ data }">
            <div class="flex flex-col text-xs leading-tight">
              <span class="font-semibold text-slate-800 truncate max-w-xs" :title="data.customer_name">
                {{ data.customer_name }}
              </span>
              <span class="text-[10px] font-mono text-slate-500">
                MST: {{ data.customer_tax_code || '---' }}
              </span>
            </div>
          </template>

          <!-- Số lượng mã mặt hàng -->
          <template #col-items_count="{ data }">
            <div class="text-center">
              <button
                type="button"
                class="font-mono text-xs font-semibold text-indigo-700 bg-slate-100 hover:bg-indigo-50 border border-slate-200 px-2.5 py-0.5 rounded transition-colors inline-flex items-center gap-1 mx-auto"
                @click="openConsolidatedDetail(data)"
                v-tooltip.top="'Xem chi tiết đối soát từng mã hàng'"
              >
                <i class="pi pi-box text-[10px]"></i>
                <span>{{ data.items.length }}</span>
              </button>
            </div>
          </template>

          <!-- Tiền hàng -->
          <template #col-subtotal="{ data }">
            <div class="text-right font-mono text-xs text-slate-700">
              {{ formatCurrency(data.total_issued_amount) }}
            </div>
          </template>

          <!-- Thuế VAT -->
          <template #col-vat_total="{ data }">
            <div class="text-right font-mono text-xs text-indigo-700 font-semibold">
              {{ formatCurrency(data.total_issued_vat) }}
            </div>
          </template>

          <!-- Tổng tiền thanh toán -->
          <template #col-total_amount="{ data }">
            <div class="text-right font-mono text-xs font-bold text-slate-900">
              {{ formatCurrency(data.total_issued_amount + data.total_issued_vat) }}
            </div>
          </template>

          <!-- Trạng thái xuất HĐ -->
          <template #col-invoicing_status="{ data }">
            <div class="text-center">
              <Badge
                :severity="data.status_severity"
                :value="data.status_text"
              />
            </div>
          </template>

          <!-- Thao tác: Xem chi tiết đối soát & Lập HĐ nhanh nếu còn hàng -->
          <Column header="Thao Tác" class="text-center" style="width: 100px">
            <template #body="{ data }">
              <div class="flex items-center justify-center gap-1.5">
                <Button
                  icon="pi pi-eye"
                  outlined
                  size="small"
                  severity="info"
                  class="!w-7 !h-7 !p-0 rounded-[6px]"
                  v-tooltip.bottom="'Xem chi tiết mã nào đã xuất, mã nào chưa xuất'"
                  @click="openConsolidatedDetail(data)"
                />

                <Button
                  v-if="data.total_remaining_qty > 0"
                  icon="pi pi-receipt"
                  size="small"
                  severity="success"
                  class="!w-7 !h-7 !p-0 rounded-[6px] !bg-emerald-600 hover:!bg-emerald-700 !text-white border-none"
                  v-tooltip.bottom="'Lập hóa đơn GTGT cho chứng từ này'"
                  @click="openUnbilledItemsDetail(data)"
                />
              </div>
            </template>
          </Column>
        </CommonDataTable>
      </div>

      <!-- TAB 2: DANH SÁCH CHỨNG TỪ CHỜ XUẤT HÓA ĐƠN -->
      <div v-if="activeTab === 'unbilled'" class="p-3 space-y-3">
        <!-- Toolbar lọc -->
        <Toolbar class="bg-slate-50/50 border border-slate-200 rounded-[6px] px-3 py-2 shadow-none">
          <template #start>
            <div class="flex items-center gap-2">
              <IconField>
                <InputIcon class="pi pi-search" />
                <InputText
                  v-model="unbilledSearch"
                  placeholder="Tìm theo số chứng từ, khách hàng, MST..."
                  class="w-80 rounded-[6px]"
                  size="small"
                />
              </IconField>
            </div>
          </template>

          <template #end>
            <div class="flex items-center gap-2">
              <span class="text-xs text-slate-500">
                Tổng số: <strong class="text-slate-800">{{ filteredUnbilledInvoices.length }}</strong> chứng từ chờ lập HĐ
              </span>
            </div>
          </template>
        </Toolbar>

        <!-- Bảng Chứng Từ Chờ Xuất HĐ -->
        <CommonDataTable
          :value="filteredUnbilledInvoices"
          :columns="unbilledInvoiceColumns"
          :rows="10"
          :loading="loading"
          :paginator="filteredUnbilledInvoices.length > 10"
          emptyIcon="pi pi-check-circle"
          emptyTitle="Không có chứng từ nào chờ xuất hóa đơn"
          emptySubtitle="Tất cả các chứng từ xuất kho đều đã được lập hóa đơn GTGT đầy đủ."
        >
          <!-- Số chứng từ bán -->
          <template #col-invoice_number="{ data }">
            <button
              type="button"
              class="font-mono text-xs font-bold text-indigo-700 hover:text-indigo-900 bg-indigo-50 hover:bg-indigo-100 px-2 py-0.5 rounded-[4px] border border-indigo-200 inline-block transition-colors"
              @click="openUnbilledItemsDetail(data)"
            >
              {{ data.invoice_number }}
            </button>
          </template>

          <!-- Ngày xuất kho / ghi sổ -->
          <template #col-warehouse_posted_at="{ data }">
            <span class="font-mono text-xs text-slate-600">
              {{ formatDate(data.warehouse_posted_at) }}
            </span>
          </template>

          <!-- Khách hàng -->
          <template #col-customer_name="{ data }">
            <div class="flex flex-col text-xs leading-tight">
              <span class="font-semibold text-slate-800 truncate max-w-xs" :title="data.customer_name">
                {{ data.customer_name }}
              </span>
              <span class="text-[10px] font-mono text-slate-500">
                MST: {{ data.customer_tax_code || '---' }}
              </span>
            </div>
          </template>

          <!-- Số lượng mã mặt hàng -->
          <template #col-items_count="{ data }">
            <div class="text-center">
              <button
                type="button"
                class="font-mono text-xs font-semibold text-indigo-700 bg-slate-100 hover:bg-indigo-50 border border-slate-200 px-2.5 py-0.5 rounded transition-colors inline-flex items-center gap-1 mx-auto"
                @click="openUnbilledItemsDetail(data)"
                v-tooltip.top="'Xem danh sách mặt hàng & xuất HĐ'"
              >
                <i class="pi pi-box text-[10px]"></i>
                <span>{{ data.items.length }}</span>
              </button>
            </div>
          </template>

          <!-- Tiền hàng -->
          <template #col-subtotal="{ data }">
            <div class="text-right font-mono text-xs text-slate-700">
              {{ formatCurrency(data.subtotal) }}
            </div>
          </template>

          <!-- Thuế VAT -->
          <template #col-vat_total="{ data }">
            <div class="text-right font-mono text-xs text-indigo-700 font-semibold">
              {{ formatCurrency(data.vat_total) }}
            </div>
          </template>

          <!-- Tổng tiền thanh toán -->
          <template #col-total_amount="{ data }">
            <div class="text-right font-mono text-xs font-bold text-emerald-800">
              {{ formatCurrency(data.total_amount) }}
            </div>
          </template>

          <!-- Trạng thái xuất HĐ -->
          <template #col-invoicing_status="{ data }">
            <div class="text-center">
              <Badge
                :severity="data.status_severity"
                :value="data.status_text"
              />
            </div>
          </template>

          <!-- Thao tác: Nút xem chi tiết & Nút icon-only xuất tất cả -->
          <Column header="Thao Tác" class="text-center" style="width: 100px">
            <template #body="{ data }">
              <div class="flex items-center justify-center gap-1.5">
                <!-- Nút 1: Xem chi tiết các mã hàng & chọn xuất ít một -->
                <Button
                  icon="pi pi-eye"
                  outlined
                  size="small"
                  severity="info"
                  class="!w-7 !h-7 !p-0 rounded-[6px]"
                  v-tooltip.bottom="'Xem chi tiết mặt hàng & chọn xuất hóa đơn'"
                  @click="openUnbilledItemsDetail(data)"
                />

                <!-- Nút 2: Xuất toàn bộ nhanh (Chỉ icon, có Confirm Dialog) -->
                <Button
                  icon="pi pi-receipt"
                  size="small"
                  severity="success"
                  class="!w-7 !h-7 !p-0 rounded-[6px] !bg-emerald-600 hover:!bg-emerald-700 !text-white border-none"
                  v-tooltip.bottom="'Xuất toàn bộ hóa đơn VAT cho chứng từ này'"
                  @click="openFullVatConfirm(data)"
                />
              </div>
            </template>
          </Column>
        </CommonDataTable>
      </div>

      <!-- TAB 3: DANH SÁCH HÓA ĐƠN GTGT ĐÃ PHÁT HÀNH -->
      <div v-if="activeTab === 'invoiced'" class="p-3 space-y-3">
        <!-- Toolbar lọc -->
        <Toolbar class="bg-slate-50/50 border border-slate-200 rounded-[6px] px-3 py-2 shadow-none">
          <template #start>
            <div class="flex items-center gap-2">
              <IconField>
                <InputIcon class="pi pi-search" />
                <InputText
                  v-model="invoicedSearch"
                  placeholder="Tìm theo số HĐ (8 số), ký hiệu, khách hàng, MST..."
                  class="w-80 rounded-[6px]"
                  size="small"
                />
              </IconField>
            </div>
          </template>

          <template #end>
            <div class="flex items-center gap-2">
              <span class="text-xs text-slate-500">
                Tổng cộng: <strong class="text-slate-800">{{ filteredVatInvoices.length }}</strong> hóa đơn GTGT
              </span>
            </div>
          </template>
        </Toolbar>

        <!-- Bảng VAT Invoices -->
        <CommonDataTable
          :value="filteredVatInvoices"
          :columns="vatInvoiceColumns"
          :rows="10"
          :loading="loading"
          :paginator="filteredVatInvoices.length > 10"
          emptyIcon="pi pi-receipt"
          emptyTitle="Chưa có hóa đơn GTGT nào"
          emptySubtitle="Chuyển sang tab 'Chứng Từ Chờ Xuất Hóa Đơn' để thực hiện lập hóa đơn cho các mặt hàng đã xuất kho."
        >
          <!-- Số Hóa Đơn (8 chữ số: 00000001, 00000123) -->
          <template #col-invoice_number="{ data }">
            <button
              type="button"
              class="font-mono text-xs font-bold text-indigo-700 hover:text-indigo-900 bg-indigo-50 hover:bg-indigo-100 px-2.5 py-0.5 rounded-[4px] border border-indigo-200 inline-block transition-colors"
              @click="viewInvoiceDetail(data)"
            >
              {{ data.invoice_number }}
            </button>
          </template>

          <!-- Ký hiệu -->
          <template #col-invoice_series="{ data }">
            <span class="font-mono text-xs font-semibold text-slate-700">
              {{ data.invoice_series || '1C26TBB' }}
            </span>
          </template>

          <!-- Ngày hóa đơn -->
          <template #col-invoice_date="{ data }">
            <span class="font-mono text-xs text-slate-600">
              {{ formatDate(data.invoice_date || data.created_at) }}
            </span>
          </template>

          <!-- Chứng từ bán gốc -->
          <template #col-sales_invoice_number="{ data }">
            <div class="flex flex-col text-xs leading-tight">
              <span class="font-mono font-semibold text-indigo-700">
                {{ data.sales_invoice_number || `INV-#${data.sales_invoice_id}` }}
              </span>
            </div>
          </template>

          <!-- Khách hàng -->
          <template #col-customer_name="{ data }">
            <div class="flex flex-col text-xs leading-tight">
              <span class="font-semibold text-slate-800 truncate max-w-xs" :title="data.customer_name">
                {{ data.customer_name }}
              </span>
              <span class="text-[10px] font-mono text-slate-500">
                MST: {{ data.customer_tax_code || '---' }}
              </span>
            </div>
          </template>

          <!-- Số lượng mã trong HĐ -->
          <template #col-items_count="{ data }">
            <div class="text-center">
              <button
                type="button"
                class="font-mono text-xs text-indigo-700 bg-slate-100 hover:bg-indigo-50 border border-slate-200 px-2.5 py-0.5 rounded transition-colors inline-flex items-center gap-1"
                @click="viewInvoiceDetail(data)"
                v-tooltip.top="'Xem chi tiết mặt hàng'"
              >
                <i class="pi pi-box text-[10px]"></i>
                <span><strong>{{ (data.items || []).length }}</strong></span>
              </button>
            </div>
          </template>

          <!-- Giá trị trước thuế -->
          <template #col-subtotal="{ data }">
            <div class="text-right font-mono text-xs text-slate-700">
              {{ formatCurrency(data.subtotal) }}
            </div>
          </template>

          <!-- Tiền thuế VAT -->
          <template #col-vat_total="{ data }">
            <div class="text-right font-mono text-xs text-indigo-700 font-semibold">
              {{ formatCurrency(data.vat_total) }}
            </div>
          </template>

          <!-- Tổng tiền thanh toán -->
          <template #col-total_amount="{ data }">
            <div class="text-right font-mono text-xs font-bold text-emerald-800">
              {{ formatCurrency(data.total_amount) }}
            </div>
          </template>

          <!-- Trạng thái -->
          <template #col-status="{ data }">
            <div class="text-center">
              <Badge severity="success" value="Đã Phát Hành" />
            </div>
          </template>

          <!-- Thao tác -->
          <Column header="Thao Tác" class="text-center" style="width: 80px">
            <template #body="{ data }">
              <Button
                icon="pi pi-eye"
                outlined
                size="small"
                severity="info"
                class="!w-7 !h-7 !p-0 rounded-[6px]"
                v-tooltip.bottom="'Xem chi tiết hóa đơn GTGT'"
                @click="viewInvoiceDetail(data)"
              />
            </template>
          </Column>
        </CommonDataTable>
      </div>
    </div>

    <!-- DIALOG TỔNG HỢP: XEM CHI TIẾT ĐỐI SOÁT TẤT CẢ MÃ HÀNG TRONG CHỨNG TỪ -->
    <VatInvoiceConsolidatedDetailDialog
      v-model:visible="showConsolidatedDialog"
      :document="selectedConsolidatedDoc"
      @create-invoice="handleCreateInvoiceFromConsolidated"
    />

    <!-- DIALOG 1: XEM CHI TIẾT MÃ HÀNG & CHỌN TICK XUẤT HÓA ĐƠN TỪNG PHẦN TRỰC TIẾP -->
    <VatInvoiceUnbilledItemsDialog
      v-model:visible="showUnbilledItemsDialog"
      :invoice="selectedUnbilledInvoice"
      :form="partialForm"
      :submitting="submittingPartial"
      @submit="submitPartialVatInvoice"
    />

    <!-- DIALOG 2: CONFIRM XUẤT NHANH TOÀN BỘ HÓA ĐƠN VAT -->
    <VatInvoiceConfirmFullDialog
      v-model:visible="showConfirmFullDialog"
      :invoice="selectedInvoiceForFullVat"
      :next-invoice-info="nextInvoiceInfo"
      :submitting="submittingFull"
      @submit="executeFullVatInvoice"
    />

    <!-- DIALOG 3: XEM CHI TIẾT HÓA ĐƠN GTGT (E-INVOICE PREVIEW) -->
    <VatInvoiceDetailDialog
      v-model:visible="showDetailDialog"
      :invoice="selectedVatInvoice"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useToast } from 'primevue/usetoast';
import Column from 'primevue/column';
import Toolbar from 'primevue/toolbar';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import Badge from 'primevue/badge';

import PageHeader from '../../components/common/PageHeader.vue';
import CommonDataTable from '../../components/common/CommonDataTable.vue';
import VatInvoiceConsolidatedDetailDialog from '../../components/invoice/VatInvoiceConsolidatedDetailDialog.vue';
import VatInvoiceUnbilledItemsDialog from '../../components/invoice/VatInvoiceUnbilledItemsDialog.vue';
import VatInvoiceConfirmFullDialog from '../../components/invoice/VatInvoiceConfirmFullDialog.vue';
import VatInvoiceDetailDialog from '../../components/invoice/VatInvoiceDetailDialog.vue';

import { salesApi } from '../../api/sales.api';
import { catalogApi } from '../../api/catalog.api';
import { formatCurrency, formatDate } from '../../utils/format';

const toast = useToast();

const activeTab = ref('all');
const loading = ref(false);

const allSearch = ref('');
const unbilledSearch = ref('');
const invoicedSearch = ref('');

const customersList = ref([]);
const rawInvoices = ref([]);
const rawRevenueReportItems = ref([]);
const unbilledItems = ref([]);
const vatInvoices = ref([]);
const nextInvoiceInfo = ref({ next_number: '00000001', series: '1C26TBB' });

const customersMap = computed(() => {
  const map = new Map();
  customersList.value.forEach((c) => {
    if (c.id) {
      map.set(Number(c.id), c);
      map.set(String(c.id), c);
    }
  });
  return map;
});

const resolveCustomerInfo = (customerId, fallbackName = '', fallbackTaxCode = '', fallbackAddress = '') => {
  if (customerId && customersMap.value.has(Number(customerId))) {
    const c = customersMap.value.get(Number(customerId));
    return {
      name: c.name || fallbackName || 'Khách hàng',
      tax_code: c.tax_code || fallbackTaxCode || '',
      address: c.address || fallbackAddress || ''
    };
  }
  if (fallbackName) {
    const c = customersList.value.find(cust => cust.name && cust.name.trim().toLowerCase() === fallbackName.trim().toLowerCase());
    if (c) {
      return {
        name: c.name,
        tax_code: c.tax_code || fallbackTaxCode || '',
        address: c.address || fallbackAddress || ''
      };
    }
  }
  return {
    name: fallbackName || 'Khách hàng',
    tax_code: fallbackTaxCode || '',
    address: fallbackAddress || ''
  };
};

// Modal states
const showConsolidatedDialog = ref(false);
const selectedConsolidatedDoc = ref(null);

const showUnbilledItemsDialog = ref(false);
const selectedUnbilledInvoice = ref(null);
const submittingPartial = ref(false);

const partialForm = ref({
  invoice_number: '00000001',
  invoice_series: '1C26TBB',
  invoice_date: new Date().toISOString().split('T')[0]
});

// Confirm Modal for Full Invoice
const showConfirmFullDialog = ref(false);
const selectedInvoiceForFullVat = ref(null);
const submittingFull = ref(false);

// E-invoice detail
const showDetailDialog = ref(false);
const selectedVatInvoice = ref(null);

// Cột bảng Tổng Hợp Tất Cả Chứng Từ
const consolidatedColumns = [
  { field: 'invoice_number', header: 'Số Chứng Từ', sortable: true, width: '140px' },
  { field: 'warehouse_posted_at', header: 'Ngày Xuất Kho', sortable: true, width: '120px' },
  { field: 'customer_name', header: 'Khách Hàng & MST', sortable: true },
  { field: 'items_count', header: 'Số Mã Hàng', sortable: false, width: '100px', align: 'center' },
  { field: 'subtotal', header: 'Tiền Hàng', sortable: true, width: '130px', align: 'right' },
  { field: 'vat_total', header: 'Thuế VAT', sortable: true, width: '120px', align: 'right' },
  { field: 'total_amount', header: 'Tổng Thanh Toán', sortable: true, width: '140px', align: 'right' },
  { field: 'invoicing_status', header: 'Trạng Thái', sortable: true, width: '130px', align: 'center' }
];

// Cột bảng Chứng Từ Chờ Xuất HĐ
const unbilledInvoiceColumns = [
  { field: 'invoice_number', header: 'Số Chứng Từ', sortable: true, width: '140px' },
  { field: 'warehouse_posted_at', header: 'Ngày Xuất Kho', sortable: true, width: '120px' },
  { field: 'customer_name', header: 'Khách Hàng & MST', sortable: true },
  { field: 'items_count', header: 'Số Mã Hàng', sortable: false, width: '100px', align: 'center' },
  { field: 'subtotal', header: 'Tiền Hàng', sortable: true, width: '130px', align: 'right' },
  { field: 'vat_total', header: 'Thuế VAT', sortable: true, width: '120px', align: 'right' },
  { field: 'total_amount', header: 'Tổng Thanh Toán', sortable: true, width: '140px', align: 'right' },
  { field: 'invoicing_status', header: 'Trạng Thái', sortable: true, width: '130px', align: 'center' }
];

// Cột bảng Hóa Đơn GTGT Đã Phát Hành
const vatInvoiceColumns = [
  { field: 'invoice_number', header: 'Số Hóa Đơn (8 Số)', sortable: true, width: '140px' },
  { field: 'invoice_series', header: 'Ký Hiệu', sortable: true, width: '100px' },
  { field: 'invoice_date', header: 'Ngày Lập HĐ', sortable: true, width: '110px' },
  { field: 'sales_invoice_number', header: 'Chứng Từ Bán', sortable: true, width: '140px' },
  { field: 'customer_name', header: 'Khách Hàng & MST', sortable: true },
  { field: 'items_count', header: 'Số Mã Hàng', sortable: false, width: '100px', align: 'center' },
  { field: 'subtotal', header: 'Tiền Hàng', sortable: true, width: '130px', align: 'right' },
  { field: 'vat_total', header: 'Thuế VAT', sortable: true, width: '120px', align: 'right' },
  { field: 'total_amount', header: 'Tổng Thanh Toán', sortable: true, width: '140px', align: 'right' },
  { field: 'status', header: 'Trạng Thái', sortable: true, width: '130px', align: 'center' }
];

// 1. TỔNG HỢP TOÀN BỘ CHỨNG TỪ XUẤT KHO (ĐỐI SOÁT TIẾN ĐỘ XUẤT HĐ GTGT)
const allInvoices = computed(() => {
  const map = new Map();

  // Khởi tạo từ danh sách sales invoices
  rawInvoices.value.forEach((inv) => {
    const custId = inv.customer_id || inv.customer?.id || inv.deliveryRequest?.order?.customer_id;
    const rawCustName = inv.customer_name || inv.deliveryRequest?.order?.customer?.name || '';
    const rawCustTax = inv.customer_tax_code || inv.deliveryRequest?.order?.customer?.tax_code || '';
    const rawCustAddr = inv.shipping_address || inv.deliveryRequest?.order?.customer?.address || '';
    const custInfo = resolveCustomerInfo(custId, rawCustName, rawCustTax, rawCustAddr);

    const items = (inv.items || []).map((it) => {
      const issuedQty = Number(it.quantity || it.delivered_quantity || 0);
      const price = Number(it.unit_price || 0);
      const vatRate = Number(it.vat_percent || 10);
      return {
        id: it.id,
        invoice_item_id: it.id,
        product_id: it.product_id,
        sku: it.sku,
        product_name: it.product_name || it.description || it.sku,
        unit_price: price,
        vat_rate: vatRate,
        issued_quantity: issuedQty,
        quantity: issuedQty,
        invoiced_quantity: 0,
        remaining_invoice_quantity: issuedQty,
        invoice_now_quantity: issuedQty,
        _selected: true
      };
    });

    map.set(inv.id, {
      id: inv.id,
      invoice_number: inv.invoice_number,
      order_id: inv.deliveryRequest?.order_id || inv.order_id,
      order_number: inv.deliveryRequest?.order?.order_number || inv.order_number,
      customer_id: custId,
      customer_name: custInfo.name,
      customer_tax_code: custInfo.tax_code,
      customer_address: custInfo.address,
      warehouse_posted_at: inv.warehouse_posted_at || inv.posted_at || inv.invoice_date || inv.created_at,
      currency: inv.currency || 'VND',
      items: items,
      linked_vat_invoices: [],
      total_issued_qty: 0,
      total_invoiced_qty: 0,
      total_remaining_qty: 0,
      total_issued_amount: 0,
      total_issued_vat: 0,
      total_invoiced_amount: 0,
      total_unbilled_amount: 0,
      subtotal: 0,
      vat_total: 0,
      total_amount: 0,
      status_text: '',
      status_severity: 'secondary'
    });
  });

  // Khớp thông tin đã xuất hóa đơn từ danh sách VAT Invoices đã phát hành
  vatInvoices.value.forEach((vInv) => {
    const invId = vInv.sales_invoice_id;
    if (invId && map.has(invId)) {
      const group = map.get(invId);
      if (vInv.invoice_number && !group.linked_vat_invoices.includes(vInv.invoice_number)) {
        group.linked_vat_invoices.push(vInv.invoice_number);
      }

      (vInv.items || []).forEach((vItem) => {
        const matchItem = group.items.find((it) => 
          (vItem.sales_invoice_item_id && it.id === vItem.sales_invoice_item_id) ||
          (it.sku && vItem.sku && it.sku.toLowerCase() === vItem.sku.toLowerCase())
        );

        if (matchItem) {
          matchItem.invoiced_quantity = (matchItem.invoiced_quantity || 0) + Number(vItem.quantity || 0);
          matchItem.remaining_invoice_quantity = Math.max(0, matchItem.issued_quantity - matchItem.invoiced_quantity);
          matchItem.invoice_now_quantity = matchItem.remaining_invoice_quantity;
        }
      });
    }
  });

  // Khớp bổ sung từ unbilled revenue items nếu có thông tin chính xác
  unbilledItems.value.forEach((uIt) => {
    const invId = uIt.invoice_id;
    if (invId && map.has(invId)) {
      const group = map.get(invId);
      const matchItem = group.items.find((it) => 
        (uIt.invoice_item_id && it.id === uIt.invoice_item_id) ||
        (it.sku && uIt.sku && it.sku.toLowerCase() === uIt.sku.toLowerCase())
      );

      if (matchItem && uIt.remaining_invoice_quantity !== undefined) {
        matchItem.remaining_invoice_quantity = Number(uIt.remaining_invoice_quantity);
        matchItem.invoiced_quantity = Math.max(matchItem.invoiced_quantity || 0, matchItem.issued_quantity - matchItem.remaining_invoice_quantity);
        matchItem.invoice_now_quantity = matchItem.remaining_invoice_quantity;
      }
    }
  });

  // Khớp bổ sung từ rawRevenueReportItems (chứa cả 1311 và 1312)
  rawRevenueReportItems.value.forEach((rIt) => {
    const invId = rIt.invoice_id;
    if (invId && map.has(invId)) {
      const group = map.get(invId);
      if (rIt.vat_invoice_number && !group.linked_vat_invoices.includes(rIt.vat_invoice_number)) {
        group.linked_vat_invoices.push(rIt.vat_invoice_number);
      }

      const matchItem = group.items.find((it) => 
        (rIt.invoice_item_id && it.id === rIt.invoice_item_id) ||
        (it.sku && rIt.sku && it.sku.toLowerCase() === rIt.sku.toLowerCase())
      );

      if (matchItem) {
        if (rIt.account_code === '1311' || rIt.is_vat_invoiced) {
          const invQty = Number(rIt.invoiced_quantity || rIt.quantity || 0);
          matchItem.invoiced_quantity = Math.max(matchItem.invoiced_quantity || 0, invQty);
          matchItem.remaining_invoice_quantity = Math.max(0, matchItem.issued_quantity - matchItem.invoiced_quantity);
          matchItem.invoice_now_quantity = matchItem.remaining_invoice_quantity;
        }
      }
    }
  });

  // Tính toán tổng số lượng, thành tiền và trạng thái
  map.forEach((group) => {
    let issuedAmount = 0;
    let issuedVat = 0;
    let invoicedAmount = 0;
    let unbilledAmount = 0;
    let totalRemainingQty = 0;
    let totalInvoicedQty = 0;
    let totalIssuedQty = 0;

    group.items.forEach((it) => {
      const qty = Number(it.issued_quantity || it.quantity || 0);
      const remQty = Number(it.remaining_invoice_quantity !== undefined ? it.remaining_invoice_quantity : qty);
      const invQty = Number(it.invoiced_quantity !== undefined && it.invoiced_quantity > 0 ? it.invoiced_quantity : Math.max(0, qty - remQty));
      
      it.issued_quantity = qty;
      it.remaining_invoice_quantity = remQty;
      it.invoiced_quantity = invQty;
      it.invoice_now_quantity = remQty;

      const price = Number(it.unit_price || 0);
      const vatRate = Number(it.vat_rate || 10);

      totalIssuedQty += qty;
      totalInvoicedQty += invQty;
      totalRemainingQty += remQty;

      const lineIssuedSub = qty * price;
      const lineIssuedVat = Math.round(lineIssuedSub * (vatRate / 100));

      issuedAmount += lineIssuedSub;
      issuedVat += lineIssuedVat;
      invoicedAmount += (invQty * price);
      unbilledAmount += (remQty * price);
    });

    group.total_issued_qty = totalIssuedQty;
    group.total_invoiced_qty = totalInvoicedQty;
    group.total_remaining_qty = totalRemainingQty;
    group.total_issued_amount = issuedAmount;
    group.total_issued_vat = issuedVat;
    group.total_invoiced_amount = invoicedAmount;
    group.total_unbilled_amount = unbilledAmount;
    group.subtotal = issuedAmount;
    group.vat_total = issuedVat;
    group.total_amount = issuedAmount + issuedVat;

    const totalItems = group.items.length;
    const fullyInvoicedItems = group.items.filter((it) => (it.remaining_invoice_quantity || 0) === 0).length;
    const invoicedItems = group.items.filter((it) => (it.invoiced_quantity || 0) > 0).length;

    if (totalRemainingQty === 0 || (totalItems > 0 && fullyInvoicedItems === totalItems)) {
      group.status_text = 'Đã xuất đủ';
      group.status_severity = 'success';
    } else if (invoicedItems === 0) {
      group.status_text = 'Chưa xuất';
      group.status_severity = 'danger';
    } else {
      group.status_text = `Đã xuất ${invoicedItems}/${totalItems}`;
      group.status_severity = 'warn';
    }
  });

  return Array.from(map.values());
});

// 2. DANH SÁCH CHỨNG TỪ CHỜ XUẤT HÓA ĐƠN (Chỉ các chứng từ còn tồn đọng remaining > 0)
const unbilledInvoices = computed(() => {
  return allInvoices.value.filter((inv) => inv.total_remaining_qty > 0 || inv.total_unbilled_amount > 0);
});

const filteredAllInvoices = computed(() => {
  if (!allSearch.value) return allInvoices.value;
  const q = allSearch.value.toLowerCase().trim();
  return allInvoices.value.filter((inv) => {
    const matchInvNo = (inv.invoice_number || '').toLowerCase().includes(q);
    const matchCustName = (inv.customer_name || '').toLowerCase().includes(q);
    const matchTaxCode = (inv.customer_tax_code || '').toLowerCase().includes(q);
    const matchItem = inv.items.some((it) => 
      (it.sku && it.sku.toLowerCase().includes(q)) || 
      (it.product_name && it.product_name.toLowerCase().includes(q))
    );
    return matchInvNo || matchCustName || matchTaxCode || matchItem;
  });
});

const filteredUnbilledInvoices = computed(() => {
  if (!unbilledSearch.value) return unbilledInvoices.value;
  const q = unbilledSearch.value.toLowerCase().trim();
  return unbilledInvoices.value.filter((inv) => {
    const matchInvNo = (inv.invoice_number || '').toLowerCase().includes(q);
    const matchCustName = (inv.customer_name || '').toLowerCase().includes(q);
    const matchTaxCode = (inv.customer_tax_code || '').toLowerCase().includes(q);
    const matchItem = inv.items.some((it) => 
      (it.sku && it.sku.toLowerCase().includes(q)) || 
      (it.product_name && it.product_name.toLowerCase().includes(q))
    );
    return matchInvNo || matchCustName || matchTaxCode || matchItem;
  });
});

const processedVatInvoices = computed(() => {
  const rawInvMap = new Map();
  rawInvoices.value.forEach((inv) => {
    rawInvMap.set(inv.id, inv);
  });

  return vatInvoices.value.map((vInv) => {
    const sInv = vInv.sales_invoice_id ? rawInvMap.get(vInv.sales_invoice_id) : null;
    const custId = vInv.customer_id || vInv.customer?.id || sInv?.customer_id || sInv?.customer?.id || sInv?.deliveryRequest?.order?.customer_id;
    const rawCustName = vInv.customer_name || sInv?.customer_name || '';
    const rawCustTax = vInv.customer_tax_code || '';
    const rawCustAddr = vInv.customer_address || sInv?.shipping_address || '';

    const custInfo = resolveCustomerInfo(custId, rawCustName, rawCustTax, rawCustAddr);

    return {
      ...vInv,
      customer_id: custId,
      customer_name: custInfo.name,
      customer_tax_code: custInfo.tax_code,
      customer_address: custInfo.address
    };
  });
});

const filteredVatInvoices = computed(() => {
  if (!invoicedSearch.value) return processedVatInvoices.value;
  const q = invoicedSearch.value.toLowerCase().trim();
  return processedVatInvoices.value.filter((inv) => {
    return (
      (inv.invoice_number && inv.invoice_number.toLowerCase().includes(q)) ||
      (inv.invoice_series && inv.invoice_series.toLowerCase().includes(q)) ||
      (inv.customer_name && inv.customer_name.toLowerCase().includes(q)) ||
      (inv.customer_tax_code && inv.customer_tax_code.toLowerCase().includes(q)) ||
      (inv.sales_invoice_number && inv.sales_invoice_number.toLowerCase().includes(q))
    );
  });
});

const totalConsolidatedIssuedAmount = computed(() => {
  return allInvoices.value.reduce((sum, it) => sum + Number(it.total_issued_amount || 0), 0);
});

const totalUnbilledAmount = computed(() => {
  return unbilledInvoices.value.reduce((sum, it) => sum + Number(it.total_unbilled_amount || 0), 0);
});

const totalInvoicedRevenue = computed(() => {
  return processedVatInvoices.value.reduce((sum, it) => sum + Number(it.subtotal || 0), 0);
});

const totalVatAmount = computed(() => {
  return processedVatInvoices.value.reduce((sum, it) => sum + Number(it.vat_total || 0), 0);
});

const loadAllData = async () => {
  loading.value = true;
  try {
    const [customersRes, invoicesRes, revItemsRes, unbilledRes, vatRes, nextNumRes] = await Promise.all([
      catalogApi.getCustomers(),
      salesApi.getInvoices(),
      salesApi.getRevenueReportItems(),
      salesApi.getUnbilledRevenueItems(),
      salesApi.getVatInvoices(),
      salesApi.getNextVatInvoiceNumber()
    ]);

    customersList.value = customersRes.data || [];
    rawInvoices.value = invoicesRes.data || [];
    rawRevenueReportItems.value = revItemsRes.data || [];
    unbilledItems.value = unbilledRes.data || [];
    vatInvoices.value = vatRes.data || [];
    if (nextNumRes) {
      nextInvoiceInfo.value = nextNumRes;
      partialForm.value.invoice_number = nextNumRes.next_number || '00000001';
      partialForm.value.invoice_series = nextNumRes.series || '1C26TBB';
    }
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi tải dữ liệu hóa đơn',
      detail: err.message || 'Không thể tải danh sách hóa đơn GTGT.',
      life: 3000
    });
  } finally {
    loading.value = false;
  }
};

// 1. Xem Chi Tiết Đối Soát Tổng Hợp
const openConsolidatedDetail = (doc) => {
  selectedConsolidatedDoc.value = doc;
  showConsolidatedDialog.value = true;
};

const handleCreateInvoiceFromConsolidated = (doc) => {
  showConsolidatedDialog.value = false;
  openUnbilledItemsDetail(doc);
};

// 2. Logic trong Dialog Xem & Xuất Từng Mã Hàng
const openUnbilledItemsDetail = async (invoiceGroup) => {
  let nextNo = nextInvoiceInfo.value.next_number || '00000001';
  let nextSer = nextInvoiceInfo.value.series || '1C26TBB';
  try {
    const numRes = await salesApi.getNextVatInvoiceNumber();
    if (numRes?.next_number) {
      nextNo = numRes.next_number;
      nextSer = numRes.series || '1C26TBB';
    }
  } catch (e) {
    // fallback
  }

  partialForm.value = {
    invoice_number: nextNo,
    invoice_series: nextSer,
    invoice_date: new Date().toISOString().split('T')[0]
  };

  const clonedItems = (invoiceGroup.items || []).map((it) => ({
    ...it,
    _selected: (it.remaining_invoice_quantity || 0) > 0,
    invoice_now_quantity: it.remaining_invoice_quantity !== undefined ? it.remaining_invoice_quantity : it.quantity
  }));

  selectedUnbilledInvoice.value = {
    ...invoiceGroup,
    items: clonedItems
  };

  showUnbilledItemsDialog.value = true;
};

const submitPartialVatInvoice = async () => {
  if (!selectedUnbilledInvoice.value) return;

  const validItems = selectedUnbilledInvoice.value.items.filter(
    (it) => it._selected && Number(it.invoice_now_quantity) > 0
  );

  if (validItems.length === 0) {
    toast.add({
      severity: 'warn',
      summary: 'Chưa chọn mặt hàng',
      detail: 'Vui lòng tick chọn ít nhất 1 mặt hàng có số lượng xuất lớn hơn 0.',
      life: 3000
    });
    return;
  }

  submittingPartial.value = true;
  try {
    const payload = {
      invoice_number: partialForm.value.invoice_number,
      invoice_series: partialForm.value.invoice_series,
      invoice_date: partialForm.value.invoice_date,
      sales_invoice_id: selectedUnbilledInvoice.value.id,
      customer_id: selectedUnbilledInvoice.value.customer_id,
      customer_name: selectedUnbilledInvoice.value.customer_name,
      customer_tax_code: selectedUnbilledInvoice.value.customer_tax_code,
      customer_address: selectedUnbilledInvoice.value.customer_address,
      items: validItems.map((it) => ({
        sales_invoice_item_id: it.invoice_item_id || it.sales_invoice_item_id || it.id,
        revenue_report_item_id: it.id,
        order_item_id: it.order_item_id,
        product_id: it.product_id,
        sku: it.sku,
        product_name: it.product_name,
        quantity: Number(it.invoice_now_quantity),
        unit_price: Number(it.unit_price),
        vat_percent: Number(it.vat_rate || it.vat_percent || 10)
      }))
    };

    await salesApi.createVatInvoice(payload);

    toast.add({
      severity: 'success',
      summary: 'Xuất Hóa Đơn GTGT Thành Công!',
      detail: `Đã phát hành HĐ ${partialForm.value.invoice_number}.`,
      life: 4000
    });

    showUnbilledItemsDialog.value = false;
    loadAllData();
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi xuất hóa đơn VAT',
      detail: err.message || 'Không thể tạo hóa đơn GTGT.',
      life: 3000
    });
  } finally {
    submittingPartial.value = false;
  }
};

// 3. Logic Xuất Toàn Bộ Nhanh với Confirm Dialog
const openFullVatConfirm = (invoiceGroup) => {
  selectedInvoiceForFullVat.value = invoiceGroup;
  showConfirmFullDialog.value = true;
};

const executeFullVatInvoice = async () => {
  if (!selectedInvoiceForFullVat.value) return;

  submittingFull.value = true;
  try {
    let nextNo = nextInvoiceInfo.value.next_number || '00000001';
    let nextSer = nextInvoiceInfo.value.series || '1C26TBB';
    try {
      const numRes = await salesApi.getNextVatInvoiceNumber();
      if (numRes?.next_number) {
        nextNo = numRes.next_number;
        nextSer = numRes.series || '1C26TBB';
      }
    } catch (e) {
      // fallback
    }

    const payload = {
      invoice_number: nextNo,
      invoice_series: nextSer,
      invoice_date: new Date().toISOString().split('T')[0],
      sales_invoice_id: selectedInvoiceForFullVat.value.id,
      customer_id: selectedInvoiceForFullVat.value.customer_id,
      customer_name: selectedInvoiceForFullVat.value.customer_name,
      customer_tax_code: selectedInvoiceForFullVat.value.customer_tax_code,
      customer_address: selectedInvoiceForFullVat.value.customer_address,
      items: selectedInvoiceForFullVat.value.items
        .filter((it) => (it.remaining_invoice_quantity !== undefined ? it.remaining_invoice_quantity : it.quantity) > 0)
        .map((it) => ({
          sales_invoice_item_id: it.invoice_item_id || it.sales_invoice_item_id || it.id,
          revenue_report_item_id: it.id,
          order_item_id: it.order_item_id,
          product_id: it.product_id,
          sku: it.sku,
          product_name: it.product_name,
          quantity: Number(it.remaining_invoice_quantity !== undefined ? it.remaining_invoice_quantity : it.quantity),
          unit_price: Number(it.unit_price),
          vat_percent: Number(it.vat_rate || it.vat_percent || 10)
        }))
    };

    await salesApi.createVatInvoice(payload);

    toast.add({
      severity: 'success',
      summary: 'Xuất Toàn Bộ HĐ VAT Thành Công!',
      detail: `Đã cấp Hóa Đơn GTGT ${nextNo} cho chứng từ ${selectedInvoiceForFullVat.value.invoice_number}.`,
      life: 4000
    });

    showConfirmFullDialog.value = false;
    loadAllData();
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi xuất toàn bộ hóa đơn',
      detail: err.message || 'Không thể xuất hóa đơn VAT.',
      life: 3000
    });
  } finally {
    submittingFull.value = false;
  }
};

// 4. Xem Chi Tiết Hóa Đơn GTGT Đã Phát Hành
const viewInvoiceDetail = (inv) => {
  selectedVatInvoice.value = inv;
  showDetailDialog.value = true;
};

onMounted(() => {
  loadAllData();
});
</script>
