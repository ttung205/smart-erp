<template>
  <section class="relative isolate min-h-[calc(100vh-48px)] overflow-hidden bg-white">
    <div aria-hidden="true" class="pointer-events-none absolute -right-24 top-10 h-[640px] w-[760px] rounded-full bg-blue-50/70"></div>
    <div aria-hidden="true" class="pointer-events-none absolute -bottom-80 -left-32 h-[490px] w-[900px] rounded-full bg-sky-50/70"></div>

    <div class="relative mx-auto flex min-h-[calc(100vh-48px)] w-full max-w-[1720px] flex-col justify-center px-8 py-12 lg:px-14 xl:px-20 2xl:px-24">
      <div class="grid items-center gap-6 lg:grid-cols-[minmax(0,1.03fr)_minmax(0,0.97fr)] xl:gap-10">
        <div class="relative z-10 pt-3 lg:pt-0">
          <div class="mb-8 inline-flex items-center gap-3 rounded-full bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-700">
            <span>Smart ERP Enterprise</span>
            <span class="rounded-full bg-white px-2 py-0.5 text-xs">v2.0</span>
          </div>

          <p class="mb-2 text-xl font-semibold text-slate-500 md:text-2xl">Chào mừng bạn đến với</p>
          <h1 class="m-0 text-[clamp(2.5rem,4vw,4.25rem)] font-bold leading-tight tracking-tight text-[#0B2458]">
            Smart ERP <span class="text-blue-600">Enterprise</span>
          </h1>
          <p class="mt-5 max-w-[650px] text-lg leading-relaxed text-slate-500 md:text-[22px]">
            Hệ thống quản trị nguồn lực doanh nghiệp toàn diện,<br class="hidden xl:block" />
            đồng hành cùng sự phát triển của doanh nghiệp.
          </p>

          <div class="mt-12 h-px w-full bg-slate-200 lg:mt-14"></div>

          <div class="mt-10 grid grid-cols-2 gap-x-6 gap-y-8 sm:grid-cols-4 lg:mt-11">
            <div v-for="benefit in benefits" :key="benefit.title" class="min-w-0">
              <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full" :class="benefit.background">
                <i :class="[benefit.icon, benefit.color, 'text-2xl']"></i>
              </div>
              <h2 class="mb-2 text-[15px] font-bold leading-snug text-[#10234E]">{{ benefit.title }}</h2>
              <p class="m-0 text-[13px] leading-relaxed text-slate-500">{{ benefit.description }}</p>
            </div>
          </div>
        </div>

        <div class="relative flex items-center justify-center lg:-mr-16 xl:-mr-20">
          <img
            :src="introHero"
            alt="Minh họa hệ thống Smart ERP với biểu đồ và các phân hệ quản trị"
            class="relative z-10 w-full max-w-[820px] object-contain drop-shadow-[0_18px_35px_rgba(54,100,185,0.10)]"
          />
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import introHero from '../../assets/intro-hero.png';

const benefits = [
  {
    icon: 'pi pi-th-large',
    color: 'text-blue-600',
    background: 'bg-blue-100',
    title: 'Quản trị tập trung',
    description: 'Kết nối toàn bộ hoạt động của doanh nghiệp'
  },
  {
    icon: 'pi pi-chart-bar',
    color: 'text-emerald-500',
    background: 'bg-emerald-100',
    title: 'Tối ưu vận hành',
    description: 'Nâng cao hiệu suất và giảm chi phí'
  },
  {
    icon: 'pi pi-shield',
    color: 'text-violet-600',
    background: 'bg-violet-100',
    title: 'Bảo mật & an toàn',
    description: 'Dữ liệu được bảo vệ theo tiêu chuẩn doanh nghiệp'
  },
  {
    icon: 'pi pi-users',
    color: 'text-orange-500',
    background: 'bg-orange-100',
    title: 'Linh hoạt mở rộng',
    description: 'Dễ dàng tích hợp và phát triển theo nhu cầu'
  }
];
</script>
