<template>
  <div class="space-y-4 pb-8">
    <PageHeader :title="canViewAll ? 'Lịch Nghỉ Toàn Công Ty' : 'Lịch Nghỉ Của Phòng'" subtitle="Ai vắng mặt trong tháng: đơn đã duyệt và đơn đang chờ duyệt">
      <template #actions>
        <Button label="Quay lại" icon="pi pi-arrow-left" size="small" severity="secondary" outlined @click="router.push('/eoffice/leave')" />
        <Select
          v-if="canViewAll"
          v-model="departmentId"
          :options="departmentOptions"
          option-label="label"
          option-value="value"
          filter
          size="small"
          class="w-60"
          @change="load"
        />
        <Button icon="pi pi-chevron-left" size="small" severity="secondary" outlined @click="shiftMonth(-1)" />
        <DatePicker v-model="month" view="month" date-format="mm/yy" size="small" class="w-32" @update:model-value="load" />
        <Button icon="pi pi-chevron-right" size="small" severity="secondary" outlined @click="shiftMonth(1)" />
      </template>
    </PageHeader>

    <section class="bg-white border border-slate-200 rounded-[6px] overflow-x-auto">
      <div v-if="loading" class="p-8 text-center text-xs text-slate-500"><i class="pi pi-spin pi-spinner mr-2"></i>Đang tải lịch nghỉ...</div>
      <div v-else-if="!people.length" class="py-10 text-center flex flex-col items-center gap-2">
        <i class="pi pi-calendar text-3xl text-slate-300"></i>
        <p class="text-xs font-semibold text-slate-700 m-0">{{ canViewAll && !departmentId ? 'Không ai nghỉ phép trong tháng này' : 'Không ai trong phòng nghỉ phép tháng này' }}</p>
      </div>
      <div v-else class="grid min-w-max text-[11px]" :style="{ gridTemplateColumns: `200px repeat(${days.length}, 32px)` }">
        <div class="sticky left-0 bg-white border-b border-r border-slate-200 px-3 py-2 font-semibold text-slate-700">Nhân viên</div>
        <div
          v-for="day in days"
          :key="day.iso"
          v-tooltip.top="day.holiday"
          class="border-b border-r border-slate-200 py-1 text-center"
          :class="day.off ? 'bg-slate-100 text-slate-400' : 'text-slate-700'"
        >
          <div class="font-mono font-semibold">{{ day.date }}</div>
          <div>{{ day.weekday }}</div>
        </div>

        <template v-for="person in people" :key="person.user_id">
          <div class="sticky left-0 bg-white border-b border-r border-slate-200 px-3 py-1.5 flex flex-col justify-center min-w-0">
            <span class="text-xs text-slate-800 truncate">{{ person.user_name }}</span>
            <span v-if="canViewAll && !departmentId" class="text-[11px] text-slate-500 truncate">{{ departmentName(person.department_id) }}</span>
          </div>
          <div v-for="day in days" :key="day.iso" class="border-b border-r border-slate-200 p-0.5" :class="day.off ? 'bg-slate-100' : ''">
            <div
              v-if="person.slots[day.iso]"
              v-tooltip.top="person.slots[day.iso].tooltip"
              class="h-full min-h-6 flex flex-col rounded-[4px] overflow-hidden cursor-default"
            >
              <div class="flex-1" :class="person.slots[day.iso].am ? cellColor(person.slots[day.iso].status) : ''"></div>
              <div class="flex-1" :class="person.slots[day.iso].pm ? cellColor(person.slots[day.iso].status) : ''"></div>
            </div>
          </div>
        </template>
      </div>
    </section>

    <div class="flex items-center gap-4 text-[11px] text-slate-500">
      <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-[2px] bg-emerald-500"></span>Đã duyệt</span>
      <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-[2px] bg-amber-400"></span>Chờ duyệt</span>
      <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-[2px] bg-slate-100 border border-slate-200"></span>Cuối tuần / Ngày lễ</span>
      <span>Ô chia đôi: nửa trên buổi sáng, nửa dưới buổi chiều</span>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import DatePicker from 'primevue/datepicker';
import Select from 'primevue/select';
import PageHeader from '../../../components/common/PageHeader.vue';
import { eofficeApi } from '../../../api/eoffice.api';
import { identityApi } from '../../../api/identity.api';
import { parseJwt } from '../../../utils/jwt';
import { hasPermission } from '../../../utils/rbac';
import { storage } from '../../../utils/storage';
import { formatDays, toIsoDate } from '../constants';

const router = useRouter();
const toast = useToast();
const WEEKDAYS = ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'];
// Super Admin (và quyền eoffice.manage) xem được lịch nghỉ toàn công ty; quyền thật kiểm tra ở eoffice-service
const canViewAll = hasPermission('eoffice.manage', parseJwt(storage.getToken()));

const month = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1));
const requests = ref([]);
const holidays = ref([]);
const departments = ref([]);
const departmentId = ref(null);
const loading = ref(false);

const departmentOptions = computed(() => [
  { label: 'Tất cả phòng ban', value: null },
  ...departments.value.map(department => ({ label: department.name, value: department.id }))
]);
const departmentName = id => departments.value.find(department => department.id === id)?.name || 'Chưa gán phòng ban';

const days = computed(() => {
  const year = month.value.getFullYear();
  const monthIndex = month.value.getMonth();
  const count = new Date(year, monthIndex + 1, 0).getDate();
  return Array.from({ length: count }, (_, index) => {
    const date = new Date(year, monthIndex, index + 1);
    const iso = toIsoDate(date);
    const holiday = holidays.value.find(item => item.date === iso)?.name || null;
    return { iso, date: index + 1, weekday: WEEKDAYS[date.getDay()], off: [0, 6].includes(date.getDay()) || !!holiday, holiday };
  });
});

const people = computed(() => {
  const byUser = new Map();
  for (const request of requests.value) {
    if (!byUser.has(request.user_id)) {
      byUser.set(request.user_id, { user_id: request.user_id, user_name: request.user_name, department_id: request.department_id, slots: {} });
    }
    const person = byUser.get(request.user_id);
    for (const day of days.value) {
      if (day.off || day.iso < request.start_date || day.iso > request.end_date) continue;
      const isStart = day.iso === request.start_date;
      const isEnd = day.iso === request.end_date;
      const session = isStart ? request.start_session : isEnd ? request.end_session : 'FULL';
      const slot = person.slots[day.iso] || { am: false, pm: false, status: request.status, tooltip: '' };
      slot.am ||= session !== 'AFTERNOON';
      slot.pm ||= session !== 'MORNING';
      if (request.status === 'APPROVED') slot.status = 'APPROVED';
      slot.tooltip = `${request.code} · ${request.leave_type?.name} · ${formatDays(request.total_days)} ngày`;
      person.slots[day.iso] = slot;
    }
  }
  return [...byUser.values()].sort((a, b) =>
    departmentName(a.department_id).localeCompare(departmentName(b.department_id), 'vi') || a.user_name.localeCompare(b.user_name, 'vi'));
});

const cellColor = status => (status === 'APPROVED' ? 'bg-emerald-500' : 'bg-amber-400');

const load = async () => {
  if (!month.value) return;
  loading.value = true;
  const monthKey = toIsoDate(month.value).substring(0, 7);
  try {
    const [calendar, holidayList] = await Promise.all([
      eofficeApi.getLeaveCalendar({ month: monthKey, department_id: departmentId.value }),
      eofficeApi.getHolidays(month.value.getFullYear())
    ]);
    requests.value = calendar.data || [];
    holidays.value = holidayList.data || [];
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Không tải được lịch nghỉ', detail: error.message, life: 4000 });
  } finally {
    loading.value = false;
  }
};

const shiftMonth = delta => {
  month.value = new Date(month.value.getFullYear(), month.value.getMonth() + delta, 1);
  load();
};

onMounted(async () => {
  load();
  if (canViewAll) {
    try {
      departments.value = (await identityApi.getDepartments()).data || [];
    } catch {
      departments.value = [];
    }
  }
});
</script>
