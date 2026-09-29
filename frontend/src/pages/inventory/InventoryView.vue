<template>
  <div class="flex flex-col gap-4 pb-10">
    <!-- 1. Header Trang Chuẩn Smart ERP (Đồng bộ với Catalog, Sales, Accounting...) -->
    <PageHeader
      :title="pageHeaderTitle"
      :subtitle="pageHeaderSubtitle"
    >
      <template #title v-if="selectedWarehouse">
        <div class="flex items-center gap-2 flex-wrap">
          <Button 
            v-if="canSwitchWarehouse"
            icon="pi pi-arrow-left" 
            text 
            size="small" 
            class="!text-slate-600 hover:!text-sky-600 !p-1 !w-7 !h-7 !rounded-[6px]"
            v-tooltip.top="'Quay lại tất cả kho'"
            @click="selectedWarehouseId = null" 
          />
          <span>{{ selectedWarehouse.name }}</span>
          <span class="text-xs font-mono font-bold px-2 py-0.5 bg-sky-50 text-sky-700 rounded-[4px] border border-sky-200">
            {{ selectedWarehouse.code }}
          </span>
          <span class="text-[11px] font-semibold px-2 py-0.5 bg-emerald-50 text-emerald-700 rounded-[4px] border border-emerald-200 inline-flex items-center gap-1">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Đang hoạt động
          </span>
        </div>
      </template>

      <template #actions>
        <div class="flex items-center gap-2">
          <!-- Dropdown chuyển nhanh giữa các kho khi ở Dashboard kho -->
          <div v-if="selectedWarehouse && canSwitchWarehouse && warehouses.length > 1" class="flex items-center gap-1.5 mr-1">
            <Select 
              :modelValue="Number(selectedWarehouseId)" 
              @update:modelValue="selectedWarehouseId = $event"
              :options="warehouseOptions" 
              optionLabel="label" 
              optionValue="value" 
              class="!text-xs !h-8 !rounded-[6px] w-48"
            />
          </div>

          <!-- Nút Điều chuyển kho (Hiện ở Overview và Stocks) -->
          <Button
            v-if="currentSubTab === 'overview' || currentSubTab === 'stocks'"
            label="Điều chuyển kho"
            icon="pi pi-arrows-h"
            size="small"
            outlined
            class="rounded-[6px] !text-xs !py-1.5 !px-3 !border-slate-300 !text-slate-700 hover:!bg-slate-50 font-medium"
            @click="openTransferDialog(selectedWarehouseId)"
          />

          <!-- Nút Nhập kho (Hiện ở Overview và Stocks) -->
          <Button
            v-if="currentSubTab === 'overview' || currentSubTab === 'stocks'"
            :label="selectedWarehouse ? 'Nhập kho tại đây' : 'Lập phiếu nhập kho'"
            icon="pi pi-plus"
            size="small"
            class="rounded-[6px] !text-xs !py-1.5 !px-3 !bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 font-medium"
            @click="openReceiveDialog(selectedWarehouseId)"
          />

          <!-- Nút Khóa giữ hàng mới (Hiện ở Reservations) -->
          <Button
            v-if="currentSubTab === 'reservations'"
            label="Khóa Giữ Hàng Mới (SO)"
            icon="pi pi-lock"
            size="small"
            class="rounded-[6px] !text-xs !py-1.5 !px-3 !bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 font-medium"
            @click="openReserveDialog()"
          />
        </div>
      </template>
    </PageHeader>

    <!-- 2. Khối Nội Dung Thẻ Chính Theo Route Phân Hệ Kho -->
    <div class="bg-white border border-slate-200 rounded-[6px] p-4">
      <!-- 1. TỔNG QUAN KHO -->
      <template v-if="currentSubTab === 'overview'">
        <OverviewTabRegional
          v-if="selectedWarehouseId"
          :warehouseId="selectedWarehouseId"
          :currentUser="currentUser"
          @back="selectedWarehouseId = null"
          @change-warehouse="selectedWarehouseId = $event"
          @navigate="navigateToSubTab"
          @open-receive="openReceiveDialog"
          @open-transfer="openTransferDialog"
          @quick-receive="quickReceiveForStock"
          @view-movement="viewMovementForStock"
        />
        <OverviewTab 
          v-else
          :currentUser="currentUser" 
          @select-warehouse="selectedWarehouseId = $event"
          @navigate="navigateToSubTab"
          @open-transfer="openTransferDialog"
          @quick-receive="quickReceiveForStock"
          @filter-low-stock="goToStocksWithLowFilter"
        />
      </template>

      <!-- 2. BẢNG TỒN KHO THỰC TẾ -->
      <StocksTab 
        v-else-if="currentSubTab === 'stocks'"
        :currentUser="currentUser"
        @open-receive="openReceiveDialog"
        @open-reserve="openReserveDialog"
        @open-release="openReleaseDialog"
        @open-issue="openIssueDialog"
        @open-transfer="openTransferDialog"
        @view-movement="viewMovementForStock"
        @quick-receive="quickReceiveForStock"
      />

      <!-- 3. QUẢN LÝ HÀNG GIỮ CHỖ (SO RESERVATIONS) -->
      <ReservationsTab 
        v-else-if="currentSubTab === 'reservations'"
        :currentUser="currentUser"
        @open-reserve="openReserveDialog"
        @open-release="openReleaseDialog"
        @open-issue="openIssueDialog"
      />

      <!-- 4. SỔ CÁI BIẾN ĐỘNG THẺ KHO -->
      <MovementsTab 
        v-else-if="currentSubTab === 'movements'"
      />
    </div>

    <!-- 3. CÁC MODAL HÀNH ĐỘNG NGHIỆP VỤ SCM TOÀN HỆ THỐNG -->
    <!-- 3.1 Dialog Nhập Kho Thực Tế (PO Receipt) -->
    <Dialog 
      v-model:visible="showReceiveDialog" 
      modal 
      header="Lập Phiếu Nhập Kho Hàng Hóa (Goods Receipt)" 
      :style="{ width: '480px' }"
    >
      <div class="py-2 flex flex-col gap-4">
        <div class="flex flex-col gap-1.5">
          <label class="block text-xs font-semibold text-slate-700">Mã Chứng Từ (PO / Receipt Code) *</label>
          <InputText 
            v-model="receiveForm.referenceDoc" 
            placeholder="Ví dụ: PO-202609-0099" 
            class="!text-xs w-full font-mono !rounded-[6px]" 
          />
        </div>

        <div class="flex flex-col gap-1.5">
          <label class="block text-xs font-semibold text-slate-700">Kho Bãi Đích Nhập *</label>
          <Select 
            v-model="receiveForm.warehouseId" 
            :options="warehouseOptions" 
            optionLabel="label" 
            optionValue="value" 
            placeholder="Chọn kho bãi..." 
            class="w-full !rounded-[6px]" 
          />
        </div>

        <div class="flex flex-col gap-1.5">
          <label class="block text-xs font-semibold text-slate-700">Mặt Hàng Nhập Kho *</label>
          <Select 
            v-model="receiveForm.productId" 
            :options="productOptions" 
            optionLabel="label" 
            optionValue="value" 
            filter
            filterPlaceholder="Tìm nhanh mã SKU hoặc tên sản phẩm..."
            placeholder="Chọn sản phẩm..." 
            class="w-full !rounded-[6px]" 
          />
        </div>

        <div class="flex flex-col gap-1.5">
          <label class="block text-xs font-semibold text-slate-700">Số Lượng Nhập Thực Tế *</label>
          <InputNumber 
            v-model="receiveForm.quantity" 
            :min="1" 
            :max="10000" 
            class="w-full font-mono !rounded-[6px]" 
            placeholder="Nhập số lượng..." 
          />
        </div>

        <div class="flex flex-col gap-1.5">
          <label class="block text-xs font-semibold text-slate-700">Ghi Chú Nghiệp Vụ</label>
          <InputText 
            v-model="receiveForm.notes" 
            placeholder="Ghi chú xuất xứ hoặc hợp đồng..." 
            class="!text-xs w-full !rounded-[6px]" 
          />
        </div>
      </div>

      <template #footer>
        <div class="flex items-center justify-end gap-2 w-full pt-2">
          <Button label="Hủy Bỏ" text size="small" class="!rounded-[6px]" @click="showReceiveDialog = false" />
          <Button 
            label="Xác Nhận Nhập Kho" 
            size="small" 
            class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 !rounded-[6px]" 
            :loading="loading.action" 
            @click="submitReceive" 
          />
        </div>
      </template>
    </Dialog>

    <!-- 3.2 Dialog Giữ Chỗ Tồn Kho (SO Reserve) -->
    <Dialog 
      v-model:visible="showReserveDialog" 
      modal 
      header="Thử Nghiệm Khóa Giữ Chỗ Tồn Kho (Sales Order Reserve)" 
      :style="{ width: '480px' }"
    >
      <div class="py-2 flex flex-col gap-4">
        <p class="text-xs text-slate-500 mb-0 leading-relaxed">
          Form này giả lập sự kiện <code class="font-mono text-sky-700 bg-sky-50 px-1 py-0.5 rounded-[4px]">SalesOrderCreated</code> từ Sales Service. Thử nhập số lượng lớn hơn <strong>Available</strong> để kiểm tra cơ chế chặn bán âm!
        </p>

        <div class="flex flex-col gap-1.5">
          <label class="block text-xs font-semibold text-slate-700">Mã Đơn Bán Hàng (SO Code) *</label>
          <InputText 
            v-model="reserveForm.orderId" 
            placeholder="Ví dụ: SO-202609-0088" 
            class="!text-xs w-full font-mono !rounded-[6px]" 
          />
        </div>

        <div class="flex flex-col gap-1.5">
          <label class="block text-xs font-semibold text-slate-700">Kho Bãi Xuất Hàng *</label>
          <Select 
            v-model="reserveForm.warehouseId" 
            :options="warehouseOptions" 
            optionLabel="label" 
            optionValue="value" 
            placeholder="Chọn kho xuất..." 
            class="w-full !rounded-[6px]" 
          />
        </div>

        <div class="flex flex-col gap-1.5">
          <label class="block text-xs font-semibold text-slate-700">Mặt Hàng Cần Giữ Chỗ *</label>
          <Select 
            v-model="reserveForm.productId" 
            :options="productOptions" 
            optionLabel="label" 
            optionValue="value" 
            placeholder="Chọn mặt hàng..." 
            class="w-full !rounded-[6px]" 
          />
        </div>

        <div class="flex flex-col gap-1.5">
          <label class="block text-xs font-semibold text-slate-700">Số Lượng Giữ Chỗ *</label>
          <InputNumber 
            v-model="reserveForm.quantity" 
            :min="1" 
            :max="10000" 
            class="w-full font-mono !rounded-[6px]" 
            placeholder="Nhập số lượng..." 
          />
        </div>
      </div>

      <template #footer>
        <div class="flex items-center justify-end gap-2 w-full pt-2">
          <Button label="Hủy Bỏ" text size="small" class="!rounded-[6px]" @click="showReserveDialog = false" />
          <Button 
            label="Khóa Giữ Hàng (Reserve)" 
            size="small" 
            class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 !rounded-[6px]" 
            :loading="loading.action" 
            @click="submitReserve" 
          />
        </div>
      </template>
    </Dialog>

    <!-- 3.3 Dialog Hủy Giữ Chỗ Tồn Kho (Release Reservation) -->
    <Dialog 
      v-model:visible="showReleaseDialog" 
      modal 
      header="Hủy Khóa Giữ Chỗ Tồn Kho (Release Reservation)" 
      :style="{ width: '500px' }"
    >
      <div class="py-2 flex flex-col gap-4">
        <p class="text-xs text-slate-500 mb-0 leading-relaxed">
          Giải phóng lượng hàng đang bị khóa giữ chỗ (Reserved) khi Đơn bán hàng (SO) bị hủy, giảm số lượng hoặc hết hạn thanh toán. Lượng hàng này sẽ được hoàn trả trực tiếp về số dư <strong>Khả dụng (Available)</strong> để bán tiếp.
        </p>

        <div class="flex flex-col gap-1.5">
          <label class="block text-xs font-semibold text-slate-700">Mã Đơn Bán Hàng Cần Hủy Giữ *</label>
          <InputText 
            v-model="releaseForm.orderId" 
            placeholder="Ví dụ: SO-202609-0088" 
            class="!text-xs w-full font-mono !rounded-[6px]" 
          />
        </div>

        <div class="flex flex-col gap-1.5">
          <label class="block text-xs font-semibold text-slate-700">Kho Bãi Đang Giữ Hàng *</label>
          <Select 
            v-model="releaseForm.warehouseId" 
            :options="warehouseOptions" 
            optionLabel="label" 
            optionValue="value" 
            placeholder="Chọn kho..." 
            class="w-full !rounded-[6px]" 
          />
        </div>

        <div class="flex flex-col gap-1.5">
          <label class="block text-xs font-semibold text-slate-700">Mặt Hàng Cần Hủy Giữ Chỗ *</label>
          <Select 
            v-model="releaseForm.productId" 
            :options="productOptions" 
            optionLabel="label" 
            optionValue="value" 
            placeholder="Chọn mặt hàng..." 
            class="w-full !rounded-[6px]" 
          />
        </div>

        <div v-if="selectedReleaseStock" class="p-3 rounded-[6px] border text-xs" :class="selectedReleaseStock.quantity_reserved > 0 ? 'bg-amber-50/70 border-amber-200 text-amber-950' : 'bg-slate-50 border-slate-200 text-slate-700'">
          <div class="flex justify-between items-center mb-1.5">
            <span class="text-slate-600">Tồn kho trên kệ (On-Hand):</span>
            <span class="font-mono font-bold text-slate-800">{{ selectedReleaseStock.quantity_on_hand }}</span>
          </div>
          <div class="flex justify-between items-center mb-1.5">
            <span class="text-slate-600">Đang bị khóa giữ chỗ (Reserved):</span>
            <span class="font-mono font-bold text-amber-700">{{ selectedReleaseStock.quantity_reserved }}</span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-slate-600">Khả dụng hiện tại (Available):</span>
            <span class="font-mono font-bold text-emerald-700">{{ selectedReleaseStock.available }}</span>
          </div>
          <div v-if="selectedReleaseStock.quantity_reserved === 0" class="mt-2.5 text-[11px] leading-relaxed text-rose-700 border-t border-rose-200/80 pt-2">
            <span>Mặt hàng này hiện không có số lượng nào đang bị khóa giữ chỗ (Reserved = 0).</span>
          </div>
        </div>

        <div class="flex flex-col gap-1.5">
          <div class="flex justify-between items-center mb-1">
            <label class="block text-xs font-semibold text-slate-700">Số Lượng Hủy Giữ Chỗ *</label>
            <span v-if="selectedReleaseStock && selectedReleaseStock.quantity_reserved > 0" class="text-[11px] text-amber-700 font-medium">
              (Tối đa giải phóng: {{ selectedReleaseStock.quantity_reserved }})
            </span>
          </div>
          <InputNumber 
            v-model="releaseForm.quantity" 
            :min="1" 
            :max="selectedReleaseStock?.quantity_reserved || 10000" 
            class="w-full font-mono !rounded-[6px]" 
            placeholder="Nhập số lượng hủy..." 
          />
        </div>
      </div>

      <template #footer>
        <div class="flex items-center justify-end gap-2 w-full pt-2">
          <Button label="Đóng" text size="small" class="!rounded-[6px]" @click="showReleaseDialog = false" />
          <Button 
            label="Xác Nhận Hủy Giữ Chỗ" 
            size="small" 
            class="!bg-amber-600 hover:!bg-amber-700 !text-white !border-amber-600 !rounded-[6px]" 
            :loading="loading.action" 
            :disabled="!selectedReleaseStock || selectedReleaseStock.quantity_reserved === 0"
            @click="submitRelease" 
          />
        </div>
      </template>
    </Dialog>

    <!-- 3.4 Dialog Xuất Kho Giao Hàng (Goods Issue / Delivery) -->
    <Dialog 
      v-model:visible="showIssueDialog" 
      modal 
      header="Lập Phiếu Xuất Kho Giao Hàng (Goods Issue / Delivery)" 
      :style="{ width: '480px' }"
    >
      <div class="py-2 flex flex-col gap-4">
        <p class="text-xs text-slate-500 mb-0 leading-relaxed">
          Ghi nhận xuất kho thực tế khi hàng rời khỏi kho để giao đến khách hàng, tự động trừ số lượng tồn trên kệ và giải phóng lượng đang giữ chỗ.
        </p>

        <div class="flex flex-col gap-1.5">
          <label class="block text-xs font-semibold text-slate-700">Mã Phiếu Xuất / Vận Đơn *</label>
          <InputText 
            v-model="issueForm.deliveryId" 
            placeholder="Ví dụ: DEL-202609-0099" 
            class="!text-xs w-full font-mono !rounded-[6px]" 
          />
        </div>

        <div class="flex flex-col gap-1.5">
          <label class="block text-xs font-semibold text-slate-700">Mã Đơn Bán Hàng (SO Code)</label>
          <InputText 
            v-model="issueForm.orderId" 
            placeholder="Ví dụ: SO-202609-0042" 
            class="!text-xs w-full font-mono !rounded-[6px]" 
          />
        </div>

        <div class="flex flex-col gap-1.5">
          <label class="block text-xs font-semibold text-slate-700">Kho Bãi Xuất Hàng *</label>
          <Select 
            v-model="issueForm.warehouseId" 
            :options="warehouseOptions" 
            optionLabel="label" 
            optionValue="value" 
            placeholder="Chọn kho xuất..." 
            class="w-full !rounded-[6px]" 
          />
        </div>

        <div class="flex flex-col gap-1.5">
          <label class="block text-xs font-semibold text-slate-700">Mặt Hàng Cần Xuất *</label>
          <Select 
            v-model="issueForm.productId" 
            :options="productOptions" 
            optionLabel="label" 
            optionValue="value" 
            placeholder="Chọn mặt hàng..." 
            class="w-full !rounded-[6px]" 
          />
        </div>

        <div v-if="selectedIssueStock" class="p-3 rounded-[6px] border text-xs" :class="selectedIssueStock.quantity_reserved > 0 ? 'bg-sky-50/60 border-sky-200 text-sky-950' : 'bg-amber-50/80 border-amber-200 text-amber-950'">
          <div class="flex justify-between items-center mb-1.5">
            <span class="text-slate-600">Tồn kho trên kệ (On-Hand):</span>
            <span class="font-mono font-bold text-slate-800">{{ selectedIssueStock.quantity_on_hand }}</span>
          </div>
          <div class="flex justify-between items-center mb-1.5">
            <span class="text-slate-600">Đã khóa giữ chỗ (Reserved):</span>
            <span class="font-mono font-bold" :class="selectedIssueStock.quantity_reserved > 0 ? 'text-sky-700' : 'text-rose-600'">
              {{ selectedIssueStock.quantity_reserved }}
            </span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-slate-600">Khả dụng bán thêm (Available):</span>
            <span class="font-mono font-bold text-emerald-700">{{ selectedIssueStock.available }}</span>
          </div>
          <div v-if="selectedIssueStock.quantity_reserved === 0" class="mt-2.5 text-[11px] leading-relaxed text-amber-800 border-t border-amber-200/80 pt-2">
            <span><strong>Quy tắc SCM:</strong> Nghiệp vụ Xuất kho (Delivery) chỉ xuất các đơn đã <strong>Khóa giữ chỗ (SO Reserve)</strong>. Sản phẩm này đang có Reserved = 0 nên bạn cần bấm nút <em>"Giữ Chỗ (SO)"</em> trước hoặc chọn sản phẩm đã có lượng giữ chỗ.</span>
          </div>
        </div>

        <div class="flex flex-col gap-1.5">
          <div class="flex justify-between items-center mb-1">
            <label class="block text-xs font-semibold text-slate-700">Số Lượng Xuất Thực Tế *</label>
            <span v-if="selectedIssueStock && selectedIssueStock.quantity_reserved > 0" class="text-[11px] text-sky-700 font-medium">
              (Tối đa xuất: {{ selectedIssueStock.quantity_reserved }})
            </span>
          </div>
          <InputNumber 
            v-model="issueForm.quantity" 
            :min="1" 
            :max="selectedIssueStock?.quantity_reserved || 10000" 
            class="w-full font-mono !rounded-[6px]" 
            placeholder="Nhập số lượng xuất..." 
          />
        </div>

        <div class="flex flex-col gap-1.5">
          <label class="block text-xs font-semibold text-slate-700">Ghi Chú Vận Chuyển</label>
          <InputText 
            v-model="issueForm.notes" 
            placeholder="Ghi chú đối tác vận chuyển, tài xế..." 
            class="!text-xs w-full !rounded-[6px]" 
          />
        </div>
      </div>

      <template #footer>
        <div class="flex items-center justify-end gap-2 w-full pt-2">
          <Button label="Hủy Bỏ" text size="small" class="!rounded-[6px]" @click="showIssueDialog = false" />
          <Button 
            label="Xác Nhận Xuất Kho" 
            size="small" 
            class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 !rounded-[6px]" 
            :loading="loading.action" 
            @click="submitIssue" 
          />
        </div>
      </template>
    </Dialog>

    <!-- 3.5 Dialog Điều Chuyển Kho Nội Bộ (Stock Transfer) -->
    <Dialog 
      v-model:visible="showTransferDialog" 
      modal 
      header="Lập Phiếu Điều Chuyển Kho Nội Bộ (Stock Transfer)" 
      :style="{ width: '520px' }"
    >
      <div class="py-2 flex flex-col gap-4">
        <p class="text-xs text-slate-500 mb-0 leading-relaxed">
          Điều chuyển cân đối hàng hóa giữa các kho chi nhánh, tự động cập nhật sổ cái thẻ kho cho cả kho nguồn và kho đích.
        </p>

        <div class="flex flex-col gap-1.5">
          <label class="block text-xs font-semibold text-slate-700">Mã Lệnh Điều Chuyển *</label>
          <InputText 
            v-model="transferForm.transferDoc" 
            placeholder="Ví dụ: TRF-202609-0012" 
            class="!text-xs w-full font-mono !rounded-[6px]" 
          />
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div class="flex flex-col gap-1.5">
            <label class="block text-xs font-semibold text-slate-700">Kho Nguồn (Xuất Đi) *</label>
            <Select 
              v-model="transferForm.sourceWarehouseId" 
              :options="warehouseOptions" 
              optionLabel="label" 
              optionValue="value" 
              placeholder="Chọn kho nguồn..." 
              class="w-full !rounded-[6px]" 
            />
          </div>

          <div class="flex flex-col gap-1.5">
            <label class="block text-xs font-semibold text-slate-700">Kho Đích (Nhận Về) *</label>
            <Select 
              v-model="transferForm.targetWarehouseId" 
              :options="warehouseOptions" 
              optionLabel="label" 
              optionValue="value" 
              placeholder="Chọn kho đích..." 
              class="w-full !rounded-[6px]" 
            />
          </div>
        </div>

        <div class="flex flex-col gap-1.5">
          <label class="block text-xs font-semibold text-slate-700">Mặt Hàng Điều Chuyển *</label>
          <Select 
            v-model="transferForm.productId" 
            :options="productOptions" 
            optionLabel="label" 
            optionValue="value" 
            placeholder="Chọn mặt hàng..." 
            class="w-full !rounded-[6px]" 
          />
        </div>

        <div class="flex flex-col gap-1.5">
          <label class="block text-xs font-semibold text-slate-700">Số Lượng Điều Chuyển *</label>
          <InputNumber 
            v-model="transferForm.quantity" 
            :min="1" 
            :max="10000" 
            class="w-full font-mono !rounded-[6px]" 
            placeholder="Nhập số lượng..." 
          />
        </div>

        <div class="flex flex-col gap-1.5">
          <label class="block text-xs font-semibold text-slate-700">Ghi Chú & Phương Tiện</label>
          <InputText 
            v-model="transferForm.notes" 
            placeholder="Lý do điều chuyển, xe vận tải..." 
            class="!text-xs w-full !rounded-[6px]" 
          />
        </div>
      </div>

      <template #footer>
        <div class="flex items-center justify-end gap-2 w-full pt-2">
          <Button label="Hủy Bỏ" text size="small" class="!rounded-[6px]" @click="showTransferDialog = false" />
          <Button 
            label="Xác Nhận Điều Chuyển" 
            size="small" 
            class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 !rounded-[6px]" 
            :loading="loading.action" 
            @click="submitTransfer" 
          />
        </div>
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import PageHeader from '../../components/common/PageHeader.vue'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Select from 'primevue/select'
import Dialog from 'primevue/dialog'

import { useInventory } from '../../composables/useInventory'
import OverviewTab from './OverviewTab.vue'
import OverviewTabRegional from './OverviewTabRegional.vue'
import StocksTab from './StocksTab.vue'
import ReservationsTab from './ReservationsTab.vue'
import MovementsTab from './MovementsTab.vue'

const props = defineProps({
  currentUser: {
    type: Object,
    default: null
  },
  currentSubTab: {
    type: String,
    default: 'overview'
  }
})

const emit = defineEmits(['update:currentSubTab'])
const toast = useToast()
const router = useRouter()
const route = useRoute()

const {
  stocks,
  warehouses,
  loading,
  filters,
  movementFilters,
  catalogProducts,
  fetchCatalogProducts,
  fetchWarehouses,
  fetchStocks,
  fetchMovements,
  receiveStock,
  reserveStock,
  releaseStock,
  issueStock,
  transferStock
} = useInventory()

const getSubTabFromPath = (path) => {
  if (path) {
    if (path.includes('/inventory/stocks')) return 'stocks'
    if (path.includes('/inventory/reservations')) return 'reservations'
    if (path.includes('/inventory/movements')) return 'movements'
    if (path.includes('/inventory/overview')) return 'overview'
  }
  return 'overview'
}

// State tab đồng bộ 2 chiều với URL route và menu sidebar
const currentSubTab = ref(props.currentSubTab || getSubTabFromPath(route.path))

watch(() => route.path, (newPath) => {
  if (newPath) {
    currentSubTab.value = getSubTabFromPath(newPath)
  }
}, { immediate: true })

watch(() => props.currentSubTab, (val) => {
  if (val && val !== currentSubTab.value) {
    currentSubTab.value = val
  }
})

watch(currentSubTab, (val) => {
  emit('update:currentSubTab', val)
})

const navigateToSubTab = (tab) => {
  router.push(`/inventory/${tab}`)
}

// Phân quyền thủ kho khu vực: Nếu tài khoản được phân cứng vào 1 kho
const assignedWarehouseId = computed(() => {
  return props.currentUser?.warehouse_id || props.currentUser?.assigned_warehouse_id || null
})

// Quản lý kho đang được chọn xem Dashboard chi tiết (null = Toàn hệ thống)
const selectedWarehouseId = ref(null)

// Đối tượng kho đang chọn
const selectedWarehouse = computed(() => {
  if (!selectedWarehouseId.value) return null
  return warehouses.value.find(w => Number(w.id) === Number(selectedWarehouseId.value)) || null
})

// Quyền chuyển kho
const canSwitchWarehouse = computed(() => {
  if (!props.currentUser) return true
  if (['Ban Giám Đốc (BOD)', 'Quản Trị Hệ Thống'].includes(props.currentUser?.department)) return true
  if (props.currentUser?.role === 'admin' || props.currentUser?.role === 'manager') return true
  return !assignedWarehouseId.value
})

// Tiêu đề PageHeader động đồng bộ với các trang khác
const pageHeaderTitle = computed(() => {
  if (currentSubTab.value === 'overview' && selectedWarehouse.value) {
    return selectedWarehouse.value.name
  }
  const tabTitles = {
    overview: 'Tổng Quan Kho Bãi',
    stocks: 'Bảng Quản Lý Tồn Kho',
    reservations: 'Quản Lý Hàng Giữ Chỗ',
    movements: 'Biến Động Thẻ Kho'
  }
  return tabTitles[currentSubTab.value] || 'Quản Lý Kho'
})

const pageHeaderSubtitle = computed(() => {
  if (currentSubTab.value === 'overview' && selectedWarehouse.value) {
    return selectedWarehouse.value.location || selectedWarehouse.value.address || 'Kho chi nhánh trực thuộc Smart ERP'
  }
  const tabSubtitles = {
    overview: 'Theo dõi mức lưu trữ thực tế, phân bổ tồn kho các chi nhánh và kiểm soát định mức an toàn',
    stocks: 'Tra cứu số lượng tồn thực tế trên kệ, hàng đang giữ chỗ và khả dụng xuất bán cho toàn bộ sản phẩm',
    reservations: 'Kiểm soát hàng giữ chỗ theo đơn bán hàng SO nhằm ngăn chặn bán âm tồn kho',
    movements: 'Nhật ký kiểm toán truy vết toàn bộ biến động nhập, xuất, giữ chỗ và điều chuyển hàng hóa'
  }
  return tabSubtitles[currentSubTab.value] || 'Hệ thống quản lý kho vận phân tán Smart ERP'
})

// Trạng thái hiển thị 5 Dialogs
const showReceiveDialog = ref(false)
const showReserveDialog = ref(false)
const showReleaseDialog = ref(false)
const showIssueDialog = ref(false)
const showTransferDialog = ref(false)

// Form states
const receiveForm = reactive({
  referenceDoc: '',
  warehouseId: 1,
  productId: 1,
  quantity: 10,
  notes: ''
})

const reserveForm = reactive({
  orderId: '',
  warehouseId: 1,
  productId: 1,
  quantity: 5
})

const releaseForm = reactive({
  orderId: '',
  warehouseId: 1,
  productId: 1,
  quantity: 5
})

const issueForm = reactive({
  deliveryId: '',
  orderId: '',
  warehouseId: 1,
  productId: 1,
  quantity: 5,
  notes: ''
})

const transferForm = reactive({
  transferDoc: '',
  sourceWarehouseId: 1,
  targetWarehouseId: 2,
  productId: 1,
  quantity: 5,
  notes: ''
})

// Options cho Select
const warehouseOptions = computed(() => 
  warehouses.value.map(w => ({ label: `${w.code} - ${w.name}`, value: w.id }))
)

const uniqueProductList = computed(() => {
  const map = new Map()
  stocks.value.forEach(s => {
    if (!map.has(s.product_id)) {
      map.set(s.product_id, {
        product_id: s.product_id,
        sku: s.sku || `SKU-${s.product_id}`,
        product_name: s.product_name || `Sản phẩm #${s.product_id}`
      })
    }
  })
  return Array.from(map.values())
})

const productOptions = computed(() => {
  if (catalogProducts.value && catalogProducts.value.length > 0) {
    return catalogProducts.value.map(p => ({
      label: `${p.sku} - ${p.name || p.description || ('Sản phẩm #' + p.id)}`,
      value: p.id
    }))
  }
  return uniqueProductList.value.map(p => ({
    label: `${p.sku} - ${p.product_name}`,
    value: p.product_id
  }))
})

const selectedIssueStock = computed(() => {
  if (!issueForm.warehouseId || !issueForm.productId) return null
  return stocks.value.find(s => 
    s.warehouse_id === Number(issueForm.warehouseId) && 
    s.product_id === Number(issueForm.productId)
  ) || null
})

const selectedReleaseStock = computed(() => {
  if (!releaseForm.warehouseId || !releaseForm.productId) return null
  return stocks.value.find(s => 
    s.warehouse_id === Number(releaseForm.warehouseId) && 
    s.product_id === Number(releaseForm.productId)
  ) || null
})

// Mở các Dialogs
const openReceiveDialog = (prefillWarehouseId = null) => {
  receiveForm.referenceDoc = `PO-${new Date().getFullYear()}${String(new Date().getMonth() + 1).padStart(2, '0')}-${Math.floor(1000 + Math.random() * 9000)}`
  if (prefillWarehouseId && typeof prefillWarehouseId === 'number') {
    receiveForm.warehouseId = prefillWarehouseId
  } else if (warehouses.value.length > 0) {
    receiveForm.warehouseId = warehouses.value[0].id
  }
  if (productOptions.value.length > 0) {
    receiveForm.productId = productOptions.value[0].value
  } else if (uniqueProductList.value.length > 0) {
    receiveForm.productId = uniqueProductList.value[0].product_id
  }
  receiveForm.quantity = 10
  receiveForm.notes = 'Nhập kho bổ sung từ nhà cung cấp'
  showReceiveDialog.value = true
}

const openReserveDialog = (stock) => {
  reserveForm.orderId = `SO-${new Date().getFullYear()}${String(new Date().getMonth() + 1).padStart(2, '0')}-${Math.floor(1000 + Math.random() * 9000)}`
  if (stock) {
    reserveForm.warehouseId = stock.warehouse_id
    reserveForm.productId = stock.product_id
    reserveForm.quantity = Math.max(1, Math.min(5, stock.available || 5))
  } else {
    if (warehouses.value.length > 0) reserveForm.warehouseId = warehouses.value[0].id
    if (uniqueProductList.value.length > 0) reserveForm.productId = uniqueProductList.value[0].product_id
    reserveForm.quantity = 5
  }
  showReserveDialog.value = true
}

const openReleaseDialog = (stock) => {
  releaseForm.orderId = `SO-${new Date().getFullYear()}${String(new Date().getMonth() + 1).padStart(2, '0')}-${Math.floor(1000 + Math.random() * 9000)}`
  if (stock) {
    releaseForm.warehouseId = stock.warehouse_id
    releaseForm.productId = stock.product_id
    releaseForm.quantity = Math.max(1, stock.quantity_reserved || 1)
  } else {
    const stockWithReservation = stocks.value.find(s => (s.quantity_reserved || 0) > 0)
    if (stockWithReservation) {
      releaseForm.warehouseId = stockWithReservation.warehouse_id
      releaseForm.productId = stockWithReservation.product_id
      releaseForm.quantity = stockWithReservation.quantity_reserved
    } else {
      if (warehouses.value.length > 0) releaseForm.warehouseId = warehouses.value[0].id
      if (uniqueProductList.value.length > 0) releaseForm.productId = uniqueProductList.value[0].product_id
      releaseForm.quantity = 1
    }
  }
  showReleaseDialog.value = true
}

const openIssueDialog = (stock) => {
  issueForm.deliveryId = `DEL-${new Date().getFullYear()}${String(new Date().getMonth() + 1).padStart(2, '0')}-${Math.floor(1000 + Math.random() * 9000)}`
  issueForm.orderId = `SO-${new Date().getFullYear()}${String(new Date().getMonth() + 1).padStart(2, '0')}-${Math.floor(1000 + Math.random() * 9000)}`
  
  if (stock) {
    issueForm.warehouseId = stock.warehouse_id
    issueForm.productId = stock.product_id
    issueForm.quantity = Math.max(1, stock.quantity_reserved || 1)
  } else {
    const stockWithReservation = stocks.value.find(s => (s.quantity_reserved || 0) > 0)
    if (stockWithReservation) {
      issueForm.warehouseId = stockWithReservation.warehouse_id
      issueForm.productId = stockWithReservation.product_id
      issueForm.quantity = Math.min(5, stockWithReservation.quantity_reserved)
    } else {
      if (warehouses.value.length > 0) issueForm.warehouseId = warehouses.value[0].id
      if (uniqueProductList.value.length > 0) issueForm.productId = uniqueProductList.value[0].product_id
      issueForm.quantity = 1
    }
  }
  issueForm.notes = 'Xuất kho giao vận chuyển cho khách hàng'
  showIssueDialog.value = true
}

const openTransferDialog = (sourceWarehouseId = null) => {
  transferForm.transferDoc = `TRF-${new Date().getFullYear()}${String(new Date().getMonth() + 1).padStart(2, '0')}-${Math.floor(1000 + Math.random() * 9000)}`
  if (sourceWarehouseId && typeof sourceWarehouseId === 'number') {
    transferForm.sourceWarehouseId = sourceWarehouseId
    const other = warehouses.value.find(w => w.id !== sourceWarehouseId)
    if (other) transferForm.targetWarehouseId = other.id
  } else if (warehouses.value.length >= 2) {
    transferForm.sourceWarehouseId = warehouses.value[0].id
    transferForm.targetWarehouseId = warehouses.value[1].id
  } else if (warehouses.value.length === 1) {
    transferForm.sourceWarehouseId = warehouses.value[0].id
    transferForm.targetWarehouseId = warehouses.value[0].id
  }
  if (uniqueProductList.value.length > 0) {
    transferForm.productId = uniqueProductList.value[0].product_id
  }
  transferForm.quantity = 5
  transferForm.notes = 'Điều chuyển cân đối hàng giữa các kho'
  showTransferDialog.value = true
}

const quickReceiveForStock = (stock) => {
  receiveForm.referenceDoc = `GR-${new Date().getFullYear()}${String(new Date().getMonth() + 1).padStart(2, '0')}-${Math.floor(1000 + Math.random() * 9000)}`
  receiveForm.warehouseId = stock.warehouse_id
  receiveForm.productId = stock.product_id
  receiveForm.quantity = stock.min_stock || 10
  receiveForm.notes = `Nhập kho bổ sung cho mặt hàng ${stock.sku}`
  showReceiveDialog.value = true
}

const goToStocksWithLowFilter = () => {
  filters.lowStockOnly = true
  router.push('/inventory/stocks')
}

const viewMovementForStock = (stock) => {
  if (stock) {
    movementFilters.search = stock.sku || ''
  }
  movementFilters.type = null
  movementFilters.warehouseId = null
  router.push('/inventory/movements')
}

// Submit Handlers
const submitReceive = async () => {
  if (!receiveForm.referenceDoc || !receiveForm.quantity || receiveForm.quantity <= 0) {
    toast.add({
      severity: 'warn',
      summary: 'Thiếu thông tin',
      detail: 'Vui lòng nhập đầy đủ mã chứng từ và số lượng nhập > 0.',
      life: 3000
    })
    return
  }

  try {
    await receiveStock({
      referenceDoc: receiveForm.referenceDoc,
      warehouseId: receiveForm.warehouseId,
      productId: receiveForm.productId,
      quantity: receiveForm.quantity,
      notes: receiveForm.notes
    })

    showReceiveDialog.value = false
    toast.add({
      severity: 'success',
      summary: 'Nhập kho thành công',
      detail: `Đã nhập +${receiveForm.quantity} sản phẩm vào kho.`,
      life: 3500
    })
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi nhập kho',
      detail: err.message || 'Không thể thực hiện nhập kho.',
      life: 5000
    })
  }
}

const submitReserve = async () => {
  if (!reserveForm.orderId || !reserveForm.quantity || reserveForm.quantity <= 0) {
    toast.add({
      severity: 'warn',
      summary: 'Thiếu thông tin',
      detail: 'Vui lòng nhập mã đơn hàng SO và số lượng giữ chỗ > 0.',
      life: 3000
    })
    return
  }

  try {
    await reserveStock({
      orderId: reserveForm.orderId,
      warehouseId: reserveForm.warehouseId,
      productId: reserveForm.productId,
      quantity: reserveForm.quantity
    })

    showReserveDialog.value = false
    toast.add({
      severity: 'success',
      summary: 'Khóa giữ chỗ thành công',
      detail: `Đã giữ chỗ ${reserveForm.quantity} sản phẩm cho đơn ${reserveForm.orderId}.`,
      life: 3500
    })
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi giữ chỗ',
      detail: err.message || 'Không thể thực hiện giữ chỗ.',
      life: 5000
    })
  }
}

const submitRelease = async () => {
  if (!releaseForm.orderId || !releaseForm.quantity || releaseForm.quantity <= 0) {
    toast.add({
      severity: 'warn',
      summary: 'Thiếu thông tin',
      detail: 'Vui lòng nhập mã đơn hàng SO và số lượng hủy giữ hợp lệ.',
      life: 3000
    })
    return
  }

  const currentStock = selectedReleaseStock.value
  const reserved = currentStock ? (currentStock.quantity_reserved || 0) : 0

  if (reserved === 0) {
    toast.add({
      severity: 'warn',
      summary: 'Không có lượng giữ chỗ',
      detail: 'Mặt hàng này hiện không có số lượng nào đang bị khóa giữ chỗ (Reserved = 0).',
      life: 4000
    })
    return
  }

  if (releaseForm.quantity > reserved) {
    toast.add({
      severity: 'warn',
      summary: 'Vượt quá lượng giữ chỗ',
      detail: `Chỉ có thể hủy giữ tối đa ${reserved} sản phẩm đã được giữ chỗ.`,
      life: 4000
    })
    return
  }

  try {
    await releaseStock({
      orderId: releaseForm.orderId,
      warehouseId: releaseForm.warehouseId,
      productId: releaseForm.productId,
      quantity: releaseForm.quantity
    })

    showReleaseDialog.value = false
    toast.add({
      severity: 'success',
      summary: 'Hủy giữ chỗ thành công',
      detail: `Đã giải phóng ${releaseForm.quantity} sản phẩm cho đơn ${releaseForm.orderId}.`,
      life: 3500
    })
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi hủy giữ chỗ',
      detail: err.message || 'Không thể hủy giữ chỗ.',
      life: 5000
    })
  }
}

const submitIssue = async () => {
  if (!issueForm.deliveryId || !issueForm.quantity || issueForm.quantity <= 0) {
    toast.add({
      severity: 'warn',
      summary: 'Thiếu thông tin',
      detail: 'Vui lòng nhập mã vận đơn và số lượng xuất hợp lệ.',
      life: 3000
    })
    return
  }

  const currentStock = selectedIssueStock.value
  const reserved = currentStock ? (currentStock.quantity_reserved || 0) : 0

  if (reserved === 0) {
    toast.add({
      severity: 'warn',
      summary: 'Chưa có lượng giữ chỗ (Reserved = 0)',
      detail: 'Theo chuẩn SCM, Xuất kho chỉ dành cho hàng đã được đặt trước. Vui lòng bấm "Giữ Chỗ (SO)" trước hoặc chọn sản phẩm đã được giữ chỗ.',
      life: 5000
    })
    return
  }

  if (issueForm.quantity > reserved) {
    toast.add({
      severity: 'warn',
      summary: 'Vượt quá số lượng giữ chỗ',
      detail: `Mặt hàng này hiện chỉ có ${reserved} sản phẩm được giữ chỗ. Vui lòng giảm số lượng xuất <= ${reserved}.`,
      life: 5000
    })
    return
  }

  try {
    await issueStock({
      orderId: issueForm.orderId,
      deliveryId: issueForm.deliveryId,
      warehouseId: issueForm.warehouseId,
      productId: issueForm.productId,
      quantity: issueForm.quantity,
      notes: issueForm.notes
    })

    showIssueDialog.value = false
    toast.add({
      severity: 'success',
      summary: 'Xuất kho thành công',
      detail: `Đã xuất ${issueForm.quantity} sản phẩm theo phiếu ${issueForm.deliveryId}.`,
      life: 3500
    })
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi xuất kho',
      detail: err.message || 'Không thể thực hiện xuất kho.',
      life: 5000
    })
  }
}

const submitTransfer = async () => {
  if (!transferForm.transferDoc) {
    toast.add({
      severity: 'warn',
      summary: 'Thiếu thông tin',
      detail: 'Vui lòng nhập mã lệnh điều chuyển.',
      life: 3000
    })
    return
  }
  if (transferForm.sourceWarehouseId === transferForm.targetWarehouseId) {
    toast.add({
      severity: 'warn',
      summary: 'Kho không hợp lệ',
      detail: 'Kho nguồn và kho đích phải khác nhau.',
      life: 3000
    })
    return
  }
  if (!transferForm.quantity || transferForm.quantity <= 0) {
    toast.add({
      severity: 'warn',
      summary: 'Thiếu số lượng',
      detail: 'Vui lòng nhập số lượng điều chuyển hợp lệ.',
      life: 3000
    })
    return
  }

  try {
    await transferStock({
      transferDoc: transferForm.transferDoc,
      sourceWarehouseId: transferForm.sourceWarehouseId,
      targetWarehouseId: transferForm.targetWarehouseId,
      productId: transferForm.productId,
      quantity: transferForm.quantity,
      notes: transferForm.notes
    })

    showTransferDialog.value = false
    toast.add({
      severity: 'success',
      summary: 'Điều chuyển thành công',
      detail: `Đã lập lệnh điều chuyển ${transferForm.quantity} đơn vị giữa 2 kho.`,
      life: 3500
    })
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi điều chuyển',
      detail: err.message || 'Không thể thực hiện lệnh điều chuyển kho.',
      life: 4000
    })
  }
}

onMounted(async () => {
  await Promise.all([
    fetchWarehouses(),
    fetchCatalogProducts(),
    fetchStocks(),
    fetchMovements()
  ])
})
</script>
