<template>
  <div class="flex flex-col gap-2">
    <!-- 1. Header Toolbar & Statistics Banner (Khớp giao diện ERP thực tế) -->
    <div class="bg-white border border-slate-200 rounded-[6px] p-2.5 flex flex-wrap items-center justify-between gap-3 shadow-sm">
      <!-- Left: Statistics Summary -->
      <div class="flex items-center gap-4 flex-wrap">
        <div class="flex flex-col">
          <span class="text-xs font-bold text-slate-900 flex items-center gap-1">
            <i class="pi pi-box text-sky-600 text-xs"></i>
            Sản phẩm ({{ items.length }})
          </span>
          <span v-if="selectedCount > 0" class="text-[11px] font-semibold text-sky-700 bg-sky-50 px-1.5 py-0.5 rounded border border-sky-200">
            Đã chọn: {{ selectedCount }} dòng
          </span>
        </div>

        <div class="h-6 w-[1px] bg-slate-200 hidden sm:block"></div>

        <div class="flex items-center gap-4 text-xs font-mono">
          <div>
            <span class="text-slate-400 block text-[10px] font-sans">Tiền hàng (Subtotal):</span>
            <span class="font-bold text-slate-800">{{ formatCurrency(summary.subtotal) }}</span>
          </div>

          <div>
            <span class="text-slate-400 block text-[10px] font-sans">Thuế VAT:</span>
            <span class="font-semibold text-slate-700">{{ formatCurrency(summary.vatTotal) }}</span>
          </div>

          <div>
            <span class="text-slate-400 block text-[10px] font-sans">CK Trung Bình:</span>
            <span class="font-bold text-emerald-600">{{ summary.avgDiscount }}%</span>
          </div>

          <div class="bg-sky-50 px-2 py-0.5 rounded-[4px] border border-sky-100">
            <span class="text-sky-600 block text-[10px] font-sans font-semibold">Tổng cộng (Total):</span>
            <span class="font-bold text-sky-700 text-sm">{{ formatCurrency(summary.grandTotal) }}</span>
          </div>
        </div>
      </div>

      <!-- Right: Action Buttons -->
      <div class="flex items-center gap-1.5 flex-wrap">
        <!-- Nút Thêm sản phẩm nhanh 1 dòng -->
        <Button
          label="Thêm sản phẩm"
          icon="pi pi-plus"
          size="small"
          class="!bg-emerald-600 hover:!bg-emerald-700 !text-white !border-emerald-600 !text-xs !py-1 !px-2.5 rounded-[6px]"
          @click="addNewEmptyRow"
        />

        <!-- Nút Tìm & Thêm từ danh mục Catalog -->
        <Button
          label="Tìm & Thêm"
          icon="pi pi-search"
          size="small"
          severity="info"
          outlined
          class="!text-xs !py-1 !px-2.5 rounded-[6px] !border-sky-500 !text-sky-600 hover:!bg-sky-50"
          @click="openSearchDialog"
        />

        <!-- Nút Action Set Trạng Thái (Win / Lose / Pending) khi chọn dòng -->
        <Button
          v-if="showItemStatus"
          :label="selectedCount > 0 ? `Đổi trạng thái (${selectedCount})` : 'Đổi trạng thái'"
          icon="pi pi-sliders-h"
          size="small"
          severity="warn"
          outlined
          class="!text-xs !py-1 !px-2.5 rounded-[6px] !border-amber-500 !text-amber-700 hover:!bg-amber-50 font-medium"
          @click="openStatusDialog"
        />

        <!-- Nút Tạo Đơn Bán Hàng (chỉ cho chọn các item đã có status là WIN) -->
        <Button
          v-if="showCreateOrderButton"
          label="Tạo đơn bán (SO)"
          icon="pi pi-shopping-cart"
          size="small"
          class="!bg-indigo-600 hover:!bg-indigo-700 !text-white !border-indigo-600 !text-xs !py-1 !px-3 rounded-[6px] font-semibold shadow-sm"
          @click="handleCreateOrderAction"
        />

        <!-- Nút Cài đặt cột (Ẩn/Hiện cột) -->
        <Button
          label="Cài đặt cột"
          icon="pi pi-cog"
          size="small"
          severity="secondary"
          text
          class="!text-xs !py-1 !px-2 rounded-[6px] !text-slate-600 hover:!bg-slate-100"
          @click="showColumnConfig = true"
        />

        <!-- Nút Xuất Excel -->
        <Button
          icon="pi pi-file-excel"
          label="Xuất Excel"
          size="small"
          severity="secondary"
          text
          class="!text-xs !py-1 !px-2 rounded-[6px] !text-emerald-700 hover:!bg-emerald-50"
          @click="exportToExcel"
        />
      </div>
    </div>

    <!-- 2. Main Editable Data Table với scroll ngang min-width chuẩn để hiển thị trọn vẹn -->
    <div class="bg-white border border-slate-200 rounded-[6px] overflow-hidden shadow-sm">
      <DataTable
        :value="items"
        showGridlines
        class="clean-datatable item-editor-table"
        responsiveLayout="scroll"
        scrollable
        scrollHeight="580px"
        :tableStyle="{ minWidth: '1980px' }"
      >
        <!-- STT -->
        <Column header="#" style="width: 44px; min-width: 44px" class="text-center">
          <template #body="{ index }">
            <span class="text-xs font-mono text-slate-400 font-semibold">{{ index + 1 }}</span>
          </template>
        </Column>

        <!-- Thao tác xóa dòng -->
        <Column style="width: 40px; min-width: 40px" class="text-center">
          <template #body="{ index }">
            <button
              type="button"
              class="w-6 h-6 rounded flex items-center justify-center text-slate-300 hover:text-red-600 hover:bg-red-50 transition-colors cursor-pointer mx-auto"
              title="Xóa dòng mặt hàng này"
              @click="removeItemRow(index)"
            >
              <i class="pi pi-trash text-xs"></i>
            </button>
          </template>
        </Column>

        <!-- CỘT SELECT Ở SAU CỘT XÓA DÒNG ĐỂ SET ITEM WIN/LOSE HOẶC TẠO ĐƠN -->
        <Column v-if="showItemStatus || showCreateOrderButton" style="width: 44px; min-width: 44px" class="text-center">
          <template #header>
            <div class="flex justify-center items-center w-full">
              <input
                type="checkbox"
                :checked="isAllSelected"
                @change="toggleSelectAll"
                class="w-4 h-4 rounded text-sky-600 focus:ring-sky-500 cursor-pointer"
                title="Chọn tất cả các dòng"
              />
            </div>
          </template>
          <template #body="{ data }">
            <div class="flex justify-center items-center w-full">
              <input
                type="checkbox"
                v-model="data._selected"
                class="w-4 h-4 rounded text-sky-600 focus:ring-sky-500 cursor-pointer"
                title="Chọn dòng này"
              />
            </div>
          </template>
        </Column>

        <!-- CỘT STATUS CHO TỪNG DÒNG (WIN / LOSE / PENDING) -->
        <Column v-if="showItemStatus && columnsVisibility.status" header="STATUS" style="width: 110px; min-width: 110px" class="text-center">
          <template #body="{ data }">
            <span
              class="inline-flex items-center justify-center font-bold text-[11px] px-2 py-0.5 rounded-[4px] border uppercase tracking-wider"
              :class="getStatusBadgeClass(data.status)"
            >
              <i :class="getStatusIcon(data.status)" class="mr-1 text-[10px]"></i>
              {{ getStatusLabel(data.status) }}
            </span>
          </template>
        </Column>

        <!-- 1. Mã nội bộ (SKU / Internal Code) - Không cho sửa -->
        <Column v-if="columnsVisibility.sku" header="INTERNAL CODE" style="width: 160px; min-width: 160px">
          <template #body="{ data }">
            <span class="font-mono text-xs font-bold text-sky-800 truncate block px-1" :title="data.sku">
              {{ data.sku || '-' }}
            </span>
          </template>
        </Column>

        <!-- 2. Designation - Không cho sửa -->
        <Column v-if="columnsVisibility.designation" header="DESIGNATION" style="width: 160px; min-width: 160px">
          <template #body="{ data }">
            <span class="font-mono text-xs text-rose-700 font-semibold truncate block px-1" :title="data.designation">
              {{ data.designation || '-' }}
            </span>
          </template>
        </Column>

        <!-- 3. Brand - Không cho sửa -->
        <Column v-if="columnsVisibility.brand" header="BRAND" style="width: 100px; min-width: 100px">
          <template #body="{ data }">
            <span class="text-xs font-semibold text-slate-800 truncate block px-1" :title="data.brand">
              {{ data.brand || '-' }}
            </span>
          </template>
        </Column>

        <!-- 4. Item Description - CHO PHÉP SỬA -->
        <Column v-if="columnsVisibility.description" header="ITEM DESCRIPTION" style="width: 320px; min-width: 320px">
          <template #body="{ data }">
            <InputText
              v-model="data.description"
              class="w-full text-xs text-slate-800 !py-1.5 !px-2 rounded-[4px] !border-slate-200 hover:!border-slate-300 focus:!border-sky-500 bg-white"
              placeholder="Tên / mô tả chi tiết sản phẩm..."
              :title="data.description"
              @change="notifyChange"
            />
          </template>
        </Column>

        <!-- 5. COM (Xuất xứ) - CHO PHÉP SỬA -->
        <Column v-if="columnsVisibility.com" header="COM" style="width: 130px; min-width: 130px">
          <template #body="{ data }">
            <InputText
              v-model="data.com"
              class="w-full text-xs text-slate-700 !py-1.5 !px-2 rounded-[4px] !border-slate-200 hover:!border-slate-300 focus:!border-sky-500 bg-white"
              placeholder="Xuất xứ (COM)..."
              :title="data.com"
              @change="notifyChange"
            />
          </template>
        </Column>

        <!-- 6. Stock (Tồn kho) - Không cho sửa -->
        <Column v-if="columnsVisibility.stock" header="STOCK" style="width: 85px; min-width: 85px" class="text-center">
          <template #body="{ data }">
            <span
              class="inline-block font-mono text-xs font-bold px-2 py-0.5 rounded-[4px]"
              :class="(data.stock || 0) > 0 ? 'text-emerald-700 bg-emerald-50 border border-emerald-200' : 'text-slate-400 bg-slate-50 border border-slate-200'"
            >
              {{ data.stock ?? 0 }}
            </span>
          </template>
        </Column>

        <!-- 7. Unit (Đơn vị tính) - CHO PHÉP SỬA -->
        <Column v-if="columnsVisibility.unit" header="UNIT" style="width: 85px; min-width: 85px" class="text-center">
          <template #body="{ data }">
            <InputText
              v-model="data.unit"
              class="w-full text-xs text-center !py-1.5 !px-1 rounded-[4px] !border-slate-200 hover:!border-slate-300 focus:!border-sky-500 bg-white"
              placeholder="Cái"
              @change="notifyChange"
            />
          </template>
        </Column>

        <!-- 8. Quantity (Số lượng) - CHO PHÉP SỬA -->
        <Column v-if="columnsVisibility.quantity" header="QTY" style="width: 90px; min-width: 90px" class="text-right">
          <template #body="{ data }">
            <InputNumber
              v-model="data.quantity"
              :min="1"
              class="w-full text-xs font-mono font-bold"
              inputClass="!py-1.5 !px-2 text-right text-xs font-mono font-bold rounded-[4px] !border-slate-200 hover:!border-slate-300 focus:!border-sky-500 bg-white"
              @update:modelValue="onQuantityChange(data)"
            />
          </template>
        </Column>

        <!-- 9. Gross Price (Giá niêm yết) - Không cho sửa -->
        <Column v-if="columnsVisibility.grossPrice" header="GROSS PRICE" style="width: 140px; min-width: 140px" class="text-right">
          <template #body="{ data }">
            <span class="font-mono text-xs font-semibold text-slate-700 px-2 py-1 bg-slate-50 border border-slate-100 rounded-[4px] block text-right">
              {{ formatCurrency(data.gross_price) }}
            </span>
          </template>
        </Column>

        <!-- 10. Discount % (Chiết khấu) - CHO PHÉP SỬA -->
        <Column v-if="columnsVisibility.discount" header="CK %" style="width: 85px; min-width: 85px" class="text-right">
          <template #body="{ data }">
            <InputNumber
              v-model="data.discount_percent"
              :min="0"
              :max="100"
              suffix="%"
              class="w-full text-xs font-mono font-bold"
              inputClass="!py-1.5 !px-2 text-right text-xs font-mono font-bold rounded-[4px] !border-slate-200 hover:!border-slate-300 focus:!border-emerald-500 !text-emerald-700 bg-white"
              @update:modelValue="onDiscountChange(data)"
            />
          </template>
        </Column>

        <!-- 11. Selling Price (Đơn giá bán thực tế) - CHO PHÉP SỬA -->
        <Column v-if="columnsVisibility.sellingPrice" header="SELLING PRICE" style="width: 155px; min-width: 155px" class="text-right">
          <template #body="{ data }">
            <InputNumber
              v-model="data.selling_price"
              :min="0"
              currency="VND"
              locale="vi-VN"
              class="w-full text-xs font-mono font-bold"
              inputClass="!py-1.5 !px-2 text-right text-xs font-mono font-bold rounded-[4px] !border-slate-200 hover:!border-slate-300 focus:!border-sky-500 !text-sky-700 bg-white"
              @update:modelValue="onSellingPriceChange(data)"
            />
          </template>
        </Column>

        <!-- 12. VAT % - CHO PHÉP SỬA (ở báo giá và đơn hàng) -->
        <Column v-if="columnsVisibility.vat" header="VAT %" style="width: 80px; min-width: 80px" class="text-center">
          <template #body="{ data }">
            <InputNumber
              v-model="data.vat_percent"
              :min="0"
              :max="100"
              suffix="%"
              class="w-full text-xs font-mono"
              inputClass="!py-1.5 !px-1 text-center text-xs font-mono font-semibold rounded-[4px] !border-slate-200 hover:!border-slate-300 focus:!border-sky-500 bg-white"
              @update:modelValue="onVatChange(data)"
            />
          </template>
        </Column>

        <!-- 13. Lead Time (Giao hàng) - CHO PHÉP SỬA -->
        <Column v-if="columnsVisibility.leadTime" header="LEAD TIME" style="width: 120px; min-width: 120px">
          <template #body="{ data }">
            <InputText
              v-model="data.lead_time"
              class="w-full text-xs text-slate-700 !py-1.5 !px-2 rounded-[4px] !border-slate-200 hover:!border-slate-300 focus:!border-sky-500 bg-white"
              placeholder="VD: Có sẵn..."
              :title="data.lead_time"
              @change="notifyChange"
            />
          </template>
        </Column>

        <!-- 14. Subtotal (Thành tiền) - Không cho sửa (Tự động tính) -->
        <Column v-if="columnsVisibility.subtotal" header="SUBTOTAL" style="width: 160px; min-width: 160px" class="text-right">
          <template #body="{ data }">
            <span class="font-mono text-xs font-bold text-slate-900 num-tabular px-2 py-1 bg-slate-50 border border-slate-100 rounded-[4px] block text-right">
              {{ formatCurrency(data.subtotal || (data.quantity * data.selling_price)) }}
            </span>
          </template>
        </Column>

        <template #empty>
          <div class="py-10 text-center flex flex-col items-center gap-2">
            <i class="pi pi-box text-3xl text-slate-300"></i>
            <p class="text-xs font-semibold text-slate-700 m-0">Chưa có sản phẩm nào trong danh sách</p>
            <p class="text-[11px] text-slate-400 m-0">Bấm nút "Thêm sản phẩm" hoặc "Tìm & Thêm" ở trên để đưa mặt hàng vào bảng.</p>
          </div>
        </template>
      </DataTable>
    </div>

    <!-- 3. Modal Đổi Trạng Thái Mặt Hàng (Win / Lose / Pending) -->
    <Dialog
      v-model:visible="showStatusDialog"
      modal
      header="Thiết Lập Trạng Thái Mặt Hàng (Win / Lose / Pending)"
      :style="{ width: '450px' }"
      class="rounded-[8px]"
    >
      <div class="flex flex-col gap-3.5 py-1 text-xs">
        <div class="p-2.5 bg-slate-50 border border-slate-200 rounded-[6px] text-slate-700 flex items-center justify-between">
          <span>Số mặt hàng đang chọn:</span>
          <span class="font-bold font-mono text-sky-700 text-sm bg-sky-50 px-2 py-0.5 rounded border border-sky-200">
            {{ selectedCount }} mặt hàng
          </span>
        </div>

        <div class="flex flex-col gap-2">
          <label class="font-bold text-slate-700 uppercase tracking-wide text-[11px]">
            Chọn trạng thái áp dụng cho các dòng đã chọn:
          </label>

          <div class="flex flex-col gap-2.5">
            <!-- Option 1: WIN -->
            <label
              class="flex items-start gap-2.5 p-3 rounded-[6px] border cursor-pointer transition-colors"
              :class="newStatusToSet === 'WIN' ? 'bg-emerald-50 border-emerald-400 text-emerald-900' : 'border-slate-200 hover:bg-slate-50 text-slate-700'"
            >
              <input type="radio" value="WIN" v-model="newStatusToSet" class="mt-0.5 text-emerald-600 focus:ring-emerald-500 cursor-pointer" />
              <div class="flex flex-col">
                <span class="font-bold text-xs flex items-center gap-1.5 text-emerald-700">
                  <i class="pi pi-check-circle"></i> WIN - Trúng thầu (Khách chốt mua)
                </span>
                <span class="text-[11px] text-slate-500 mt-0.5">
                  Mặt hàng khách đồng ý mua, đủ điều kiện để tạo Đơn Bán Hàng (Sales Order).
                </span>
              </div>
            </label>

            <!-- Option 2: PENDING -->
            <label
              class="flex items-start gap-2.5 p-3 rounded-[6px] border cursor-pointer transition-colors"
              :class="newStatusToSet === 'PENDING' ? 'bg-amber-50 border-amber-400 text-amber-900' : 'border-slate-200 hover:bg-slate-50 text-slate-700'"
            >
              <input type="radio" value="PENDING" v-model="newStatusToSet" class="mt-0.5 text-amber-600 focus:ring-amber-500 cursor-pointer" />
              <div class="flex flex-col">
                <span class="font-bold text-xs flex items-center gap-1.5 text-amber-700">
                  <i class="pi pi-clock"></i> PENDING - Chờ phản hồi (Đang chào giá)
                </span>
                <span class="text-[11px] text-slate-500 mt-0.5">
                  Mặt hàng đang trong quá trình thương thảo, chờ khách hàng chốt hoặc duyệt giá.
                </span>
              </div>
            </label>

            <!-- Option 3: LOSE -->
            <label
              class="flex items-start gap-2.5 p-3 rounded-[6px] border cursor-pointer transition-colors"
              :class="newStatusToSet === 'LOSE' ? 'bg-rose-50 border-rose-400 text-rose-900' : 'border-slate-200 hover:bg-slate-50 text-slate-700'"
            >
              <input type="radio" value="LOSE" v-model="newStatusToSet" class="mt-0.5 text-rose-600 focus:ring-rose-500 cursor-pointer" />
              <div class="flex flex-col">
                <span class="font-bold text-xs flex items-center gap-1.5 text-rose-700">
                  <i class="pi pi-times-circle"></i> LOSE - Trượt thầu (Thua / Không mua)
                </span>
                <span class="text-[11px] text-slate-500 mt-0.5">
                  Khách hàng từ chối mặt hàng này hoặc chọn đối thủ cạnh tranh khác.
                </span>
              </div>
            </label>
          </div>
        </div>
      </div>

      <template #footer>
        <div class="flex justify-end gap-2 pt-2">
          <Button
            label="Hủy bỏ"
            text
            size="small"
            severity="secondary"
            class="rounded-[6px]"
            @click="showStatusDialog = false"
          />
          <Button
            label="Xác nhận áp dụng"
            icon="pi pi-check"
            size="small"
            class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 rounded-[6px]"
            @click="applyNewStatus"
          />
        </div>
      </template>
    </Dialog>

    <!-- 4. Modal Tìm & Thêm Sản Phẩm từ Catalog -->
    <Dialog
      v-model:visible="showCatalogSearchDialog"
      modal
      header="Tìm & Thêm Sản Phẩm Từ Danh Mục Hàng Hóa"
      :style="{ width: '880px' }"
      class="rounded-[6px]"
    >
      <div class="flex flex-col gap-3 pt-1">
        <IconField class="w-full">
          <InputIcon class="pi pi-search" />
          <InputText
            v-model="catalogSearchQuery"
            placeholder="Tìm theo mã SKU, Designation, Thương hiệu hoặc Tên sản phẩm..."
            class="w-full rounded-[6px]"
            size="small"
          />
        </IconField>

        <div class="border border-slate-200 rounded-[6px] overflow-hidden max-h-[380px] overflow-y-auto">
          <DataTable
            :value="filteredCatalogProducts"
            v-model:selection="selectedCatalogItems"
            dataKey="sku"
            showGridlines
            class="clean-datatable"
            responsiveLayout="scroll"
          >
            <Column selectionMode="multiple" headerStyle="width: 3rem" class="text-center"></Column>

            <Column field="sku" header="Mã SKU" style="width: 160px">
              <template #body="{ data }">
                <span class="font-mono text-xs font-bold text-sky-700">{{ data.sku }}</span>
              </template>
            </Column>

            <Column field="designation" header="Designation" style="width: 140px">
              <template #body="{ data }">
                <span class="font-mono text-xs text-rose-700 font-medium">{{ data.designation }}</span>
              </template>
            </Column>

            <Column field="brand" header="Hãng" style="width: 80px">
              <template #body="{ data }">
                <span class="text-xs font-semibold text-slate-800">{{ data.brand }}</span>
              </template>
            </Column>

            <Column field="description" header="Mô tả sản phẩm">
              <template #body="{ data }">
                <span class="text-xs text-slate-700">{{ data.description }}</span>
              </template>
            </Column>

            <Column field="stock" header="Tồn kho" class="text-center" style="width: 80px">
              <template #body="{ data }">
                <span
                  class="font-mono text-xs font-semibold px-1.5 py-0.5 rounded-[4px]"
                  :class="data.stock > 0 ? 'text-emerald-700 bg-emerald-50' : 'text-slate-400 bg-slate-50'"
                >
                  {{ data.stock }}
                </span>
              </template>
            </Column>

            <Column field="gross_price" header="Giá Niêm Yết" class="text-right" style="width: 130px">
              <template #body="{ data }">
                <span class="font-mono text-xs font-semibold text-slate-800">{{ formatCurrency(data.gross_price) }}</span>
              </template>
            </Column>
          </DataTable>
        </div>
      </div>

      <template #footer>
        <div class="flex justify-between items-center w-full">
          <span class="text-xs text-slate-500">
            Đã chọn: <strong class="text-sky-700">{{ selectedCatalogItems.length }}</strong> sản phẩm
          </span>
          <div class="flex gap-2">
            <Button
              label="Hủy bỏ"
              icon="pi pi-times"
              size="small"
              text
              severity="secondary"
              class="rounded-[6px]"
              @click="showCatalogSearchDialog = false"
            />
            <Button
              label="Thêm vào danh sách"
              icon="pi pi-plus"
              size="small"
              class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 rounded-[6px]"
              :disabled="selectedCatalogItems.length === 0"
              @click="confirmAddCatalogItems"
            />
          </div>
        </div>
      </template>
    </Dialog>

    <!-- 5. Modal Cài Đặt Cột -->
    <Dialog
      v-model:visible="showColumnConfig"
      modal
      header="Cài Đặt Ẩn / Hiện Cột Mặt Hàng"
      :style="{ width: '450px' }"
      class="rounded-[6px]"
    >
      <div class="grid grid-cols-2 gap-2 pt-1 text-xs">
        <label v-for="(val, key) in columnsVisibility" :key="key" class="flex items-center gap-2 p-1.5 hover:bg-slate-50 rounded cursor-pointer">
          <input
            type="checkbox"
            v-model="columnsVisibility[key]"
            class="rounded text-sky-600 focus:ring-sky-500 w-4 h-4 cursor-pointer"
          />
          <span class="text-slate-700 font-medium">{{ getColumnLabel(key) }}</span>
        </label>
      </div>

      <template #footer>
        <div class="flex justify-end gap-2">
          <Button
            label="Xong"
            icon="pi pi-check"
            size="small"
            class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 rounded-[6px]"
            @click="showColumnConfig = false"
          />
        </div>
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Dialog from 'primevue/dialog';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import { catalogApi } from '../../api/catalog.api';
import { formatCurrency } from '../../utils/format';

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => []
  },
  showCreateOrderButton: {
    type: Boolean,
    default: true
  },
  showItemStatus: {
    type: Boolean,
    default: true
  }
});

const emit = defineEmits(['update:modelValue', 'summary-change', 'create-order']);

const items = ref([]);

watch(
  () => props.modelValue,
  (newVal) => {
    if (newVal) {
      items.value = newVal.map((it) => ({
        ...it,
        status: it.status || (it.won || it.is_won ? 'WIN' : 'PENDING'),
        _selected: !!it._selected
      }));
    }
  },
  { immediate: true, deep: true }
);

// ----------------------------------------------------
// QUẢN LÝ CHỌN DÒNG & TRẠNG THÁI WIN / LOSE / PENDING
// ----------------------------------------------------
const showStatusDialog = ref(false);
const newStatusToSet = ref('WIN');

const selectedCount = computed(() => {
  return items.value.filter((it) => it._selected).length;
});

const isAllSelected = computed(() => {
  return items.value.length > 0 && items.value.every((it) => it._selected);
});

const toggleSelectAll = (e) => {
  const isChecked = e.target.checked;
  items.value.forEach((it) => {
    it._selected = isChecked;
  });
};

const openStatusDialog = () => {
  if (selectedCount.value === 0) {
    alert('Vui lòng tích chọn ít nhất 1 dòng mặt hàng trong bảng trước khi đổi trạng thái!');
    return;
  }
  showStatusDialog.value = true;
};

const applyNewStatus = () => {
  const targetStatus = newStatusToSet.value;
  items.value.forEach((it) => {
    if (it._selected) {
      it.status = targetStatus;
      it.won = targetStatus === 'WIN';
      it.is_won = targetStatus === 'WIN';
      if (targetStatus === 'WIN' && !it.won_quantity) {
        it.won_quantity = it.quantity || 1;
      }
    }
  });

  notifyChange();
  showStatusDialog.value = false;
};

const getStatusBadgeClass = (status) => {
  switch (status) {
    case 'WIN':
      return 'bg-emerald-50 text-emerald-700 border-emerald-300';
    case 'LOSE':
      return 'bg-rose-50 text-rose-700 border-rose-300';
    case 'PENDING':
    default:
      return 'bg-amber-50 text-amber-700 border-amber-300';
  }
};

const getStatusLabel = (status) => {
  switch (status) {
    case 'WIN':
      return 'WIN (Trúng)';
    case 'LOSE':
      return 'LOSE (Trượt)';
    case 'PENDING':
    default:
      return 'PENDING (Chờ)';
  }
};

const getStatusIcon = (status) => {
  switch (status) {
    case 'WIN':
      return 'pi pi-check';
    case 'LOSE':
      return 'pi pi-times';
    case 'PENDING':
    default:
      return 'pi pi-clock';
  }
};

// ----------------------------------------------------
// TẠO ĐƠN BÁN (SO) TỪ CÁC ITEM CÓ STATUS LÀ WIN
// ----------------------------------------------------
const handleCreateOrderAction = () => {
  // 1. Nếu người dùng có chọn cụ thể các dòng qua checkbox
  const selectedRows = items.value.filter((it) => it._selected);

  if (selectedRows.length > 0) {
    // Kiểm tra xem các dòng được chọn có dòng nào KHÔNG PHẢI WIN không
    const nonWinRows = selectedRows.filter((it) => it.status !== 'WIN');
    if (nonWinRows.length > 0) {
      alert(`Chỉ cho phép chọn các mặt hàng có trạng thái WIN để tạo đơn bán! Bạn đang chọn ${nonWinRows.length} mặt hàng chưa có trạng thái WIN (PENDING hoặc LOSE). Vui lòng cập nhật trạng thái WIN hoặc bỏ chọn các dòng đó.`);
      return;
    }
    // Hợp lệ -> emit các dòng đã chọn
    emit('create-order', selectedRows);
  } else {
    // 2. Nếu người dùng chưa tích checkbox nào -> tự động lọc tất cả các dòng WIN
    const allWinItems = items.value.filter((it) => it.status === 'WIN');
    if (allWinItems.length === 0) {
      alert('Không có mặt hàng nào có trạng thái WIN (Trúng thầu)! Vui lòng chọn các mặt hàng và nhấn "Đổi trạng thái" sang WIN trước khi tạo đơn bán hàng.');
      return;
    }
    emit('create-order', allWinItems);
  }
};

// ----------------------------------------------------
// ẨN / HIỆN CỘT
// ----------------------------------------------------
const showColumnConfig = ref(false);
const columnsVisibility = reactive({
  status: true,
  sku: true,
  designation: true,
  brand: true,
  description: true,
  com: true,
  stock: true,
  unit: true,
  quantity: true,
  grossPrice: true,
  discount: true,
  sellingPrice: true,
  vat: true,
  leadTime: true,
  subtotal: true
});

const getColumnLabel = (key) => {
  const map = {
    status: 'Status (Trạng thái Win/Lose)',
    sku: 'Internal Code (SKU)',
    designation: 'Designation (Quy cách)',
    brand: 'Brand (Thương hiệu)',
    description: 'Item Description (Mô tả)',
    com: 'COM (Xuất xứ)',
    stock: 'Stock (Tồn kho)',
    unit: 'Unit (Đơn vị tính)',
    quantity: 'Quantity (Số lượng)',
    grossPrice: 'Gross Price (Giá niêm yết)',
    discount: 'Discount (Chiết khấu %)',
    sellingPrice: 'Selling Price (Giá bán)',
    vat: 'VAT %',
    leadTime: 'Lead Time (Giao hàng)',
    subtotal: 'Subtotal (Thành tiền)'
  };
  return map[key] || key;
};

// ----------------------------------------------------
// TÍNH TOÁN CÔNG THỨC 2 CHIỀU & THAY ĐỔI GIÁ TRỊ
// ----------------------------------------------------
// Khi nhập chiết khấu % -> tính ra selling_price
const onDiscountChange = (row) => {
  const gross = Number(row.gross_price) || 0;
  const disc = Number(row.discount_percent) || 0;
  row.selling_price = Math.round(gross * (1 - disc / 100));
  row.subtotal = (Number(row.quantity) || 1) * row.selling_price;
  notifyChange();
};

// Khi nhập đơn giá bán selling_price -> tự tính ngược lại chiết khấu %
const onSellingPriceChange = (row) => {
  const gross = Number(row.gross_price) || 0;
  const selling = Number(row.selling_price) || 0;
  if (gross > 0) {
    const calculatedDisc = ((gross - selling) / gross) * 100;
    row.discount_percent = Math.max(0, Math.round(calculatedDisc * 10) / 10);
  } else {
    row.discount_percent = 0;
  }
  row.subtotal = (Number(row.quantity) || 1) * selling;
  notifyChange();
};

// Khi sửa số lượng Quantity
const onQuantityChange = (row) => {
  const qty = Number(row.quantity) || 1;
  const selling = Number(row.selling_price) || 0;
  row.subtotal = qty * selling;
  notifyChange();
};

// Khi sửa VAT %
const onVatChange = (row) => {
  notifyChange();
};

// Thêm dòng mới
const addNewEmptyRow = () => {
  const newRow = {
    id: Date.now() + Math.random(),
    sku: '',
    designation: '',
    brand: '',
    description: '',
    com: 'Việt Nam',
    stock: 0,
    unit: 'Cái',
    quantity: 1,
    gross_price: 0,
    discount_percent: 0,
    selling_price: 0,
    vat_percent: 10,
    lead_time: 'Có sẵn',
    subtotal: 0,
    status: 'PENDING',
    won: false,
    _selected: false
  };
  items.value.push(newRow);
  notifyChange();
};

// Xóa dòng
const removeItemRow = (idx) => {
  if (items.value.length > 1) {
    items.value.splice(idx, 1);
    notifyChange();
  } else {
    alert('Danh sách cần có ít nhất 1 dòng sản phẩm!');
  }
};

// ----------------------------------------------------
// TÌM & THÊM TỪ CATALOG MODAL
// ----------------------------------------------------
const catalogProducts = ref([]);
const showCatalogSearchDialog = ref(false);
const catalogSearchQuery = ref('');
const selectedCatalogItems = ref([]);

const loadCatalogProducts = async () => {
  try {
    const res = await catalogApi.getProducts();
    if (res && res.data) {
      catalogProducts.value = res.data.map((p) => ({
        id: p.id,
        sku: p.sku,
        designation: p.designation || '',
        brand: p.brand || '',
        category: p.category || '',
        description: p.description || p.item_description || p.name || '',
        unit: p.unit || 'Cái',
        com: p.com || 'Việt Nam',
        stock: p.stock ?? (p.min_stock || 20),
        gross_price: Number(p.gross_price) || 0,
        lead_time: 'Có sẵn',
        vat_percent: 10,
        spec: p.spec || ''
      }));
    }
  } catch (err) {
    console.warn('loadCatalogProducts error:', err);
  }
};

const filteredCatalogProducts = computed(() => {
  if (!catalogSearchQuery.value) return catalogProducts.value;
  const q = catalogSearchQuery.value.toLowerCase().trim();
  return catalogProducts.value.filter(
    (p) =>
      p.sku.toLowerCase().includes(q) ||
      (p.designation && p.designation.toLowerCase().includes(q)) ||
      (p.brand && p.brand.toLowerCase().includes(q)) ||
      p.description.toLowerCase().includes(q)
  );
});

const openSearchDialog = async () => {
  if (catalogProducts.value.length === 0) {
    await loadCatalogProducts();
  }
  selectedCatalogItems.value = [];
  catalogSearchQuery.value = '';
  showCatalogSearchDialog.value = true;
};

const fillRowWithCatalogProduct = (row, product) => {
  row.sku = product.sku;
  row.designation = product.designation;
  row.brand = product.brand;
  row.description = product.description;
  row.unit = product.unit || 'Cái';
  row.com = product.com || 'Chính hãng';
  row.stock = product.stock || 0;
  row.gross_price = product.gross_price || 0;
  row.vat_percent = product.vat_percent || 10;
  row.lead_time = product.lead_time || 'Có sẵn';
  row.status = row.status || 'PENDING';

  const disc = Number(row.discount_percent) || 0;
  row.selling_price = Math.round(row.gross_price * (1 - disc / 100));
  row.subtotal = (Number(row.quantity) || 1) * row.selling_price;
  notifyChange();
};

const confirmAddCatalogItems = () => {
  selectedCatalogItems.value.forEach((prod) => {
    const lastItem = items.value[items.value.length - 1];
    if (lastItem && !lastItem.sku && items.value.length === 1) {
      fillRowWithCatalogProduct(lastItem, prod);
    } else {
      const newLine = {
        id: Date.now() + Math.random(),
        sku: prod.sku,
        designation: prod.designation,
        brand: prod.brand,
        description: prod.description,
        unit: prod.unit || 'Cái',
        com: prod.com || 'Chính hãng',
        stock: prod.stock || 0,
        quantity: 1,
        gross_price: prod.gross_price || 0,
        discount_percent: 10,
        selling_price: Math.round(prod.gross_price * 0.9),
        vat_percent: prod.vat_percent || 10,
        lead_time: prod.lead_time || 'Có sẵn',
        subtotal: Math.round(prod.gross_price * 0.9),
        status: 'PENDING',
        won: false,
        _selected: false
      };
      items.value.push(newLine);
    }
  });

  notifyChange();
  showCatalogSearchDialog.value = false;
};

// ----------------------------------------------------
// THỐNG KÊ TỔNG HỢP BANNER
// ----------------------------------------------------
const summary = computed(() => {
  let subtotal = 0;
  let vatTotal = 0;
  let totalGross = 0;

  items.value.forEach((it) => {
    const qty = Number(it.quantity) || 1;
    const lineSubtotal = Number(it.subtotal) || qty * (Number(it.selling_price) || 0);
    const lineVat = Math.round(lineSubtotal * ((Number(it.vat_percent) || 0) / 100));

    subtotal += lineSubtotal;
    vatTotal += lineVat;
    totalGross += (Number(it.gross_price) || Number(it.selling_price) || 0) * qty;
  });

  const avgDiscount = totalGross > 0 ? Math.round(((totalGross - subtotal) / totalGross) * 100) : 0;
  const grandTotal = subtotal + vatTotal;

  return {
    subtotal,
    vatTotal,
    avgDiscount,
    grandTotal
  };
});

watch(
  summary,
  (newSum) => {
    emit('summary-change', newSum);
  },
  { immediate: true, deep: true }
);

const notifyChange = () => {
  emit('update:modelValue', items.value);
  emit('summary-change', summary.value);
};

// Xuất file CSV
const exportToExcel = () => {
  let csv = 'STT,Status,SKU,Designation,Brand,Mô Tả,COM,Tồn Kho,ĐVT,Số Lượng,Giá Niêm Yết,Chiết Khấu %,Giá Bán,VAT %,Giao Hàng,Thành Tiền\n';
  items.value.forEach((it, idx) => {
    csv += `${idx + 1},"${it.status || 'PENDING'}","${it.sku}","${it.designation}","${it.brand}","${it.description}","${it.com}",${it.stock},"${it.unit}",${it.quantity},${it.gross_price},${it.discount_percent},${it.selling_price},${it.vat_percent},"${it.lead_time}",${it.subtotal}\n`;
  });

  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  const link = document.createElement('a');
  link.href = URL.createObjectURL(blob);
  link.setAttribute('download', `Items_${Date.now()}.csv`);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
};

onMounted(() => {
  if (catalogProducts.value.length === 0) {
    loadCatalogProducts();
  }
});
</script>
