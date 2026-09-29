<template>
  <div class="space-y-4 pb-12">
    <PageHeader
      title="Quản Lý Nhà Cung Cấp (Suppliers)"
      subtitle="Quản lý hồ sơ nhà cung cấp thiết bị công nghiệp, vật tư, đánh giá NCC và công nợ mua hàng"
      badge="Phân Hệ Đối Tác"
      badge-severity="secondary"
    />

    <div class="bg-white border border-slate-200 rounded-[8px] p-12 text-center flex flex-col items-center justify-center gap-3 shadow-sm">
      <div class="w-16 h-16 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 text-2xl">
        <i class="pi pi-truck text-3xl"></i>
      </div>
      <h3 class="text-base font-bold text-slate-800 m-0">Phân Hệ Quản Lý Nhà Cung Cấp</h3>
      <p class="text-xs text-slate-500 max-w-md m-0">
        Tính năng này đang được phát triển theo phân công nhiệm vụ. Vui lòng quay lại phân hệ <strong>Khách hàng</strong> để quản lý các đối tác B2B hiện tại.
      </p>
      <div class="pt-2">
        <Button
          label="Đi đến Quản lý Khách hàng"
          icon="pi pi-arrow-right"
          size="small"
          class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 text-xs rounded-[6px]"
          @click="$router.push('/customer')"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import PageHeader from '../../components/common/PageHeader.vue';
import Button from 'primevue/button';
</script>
