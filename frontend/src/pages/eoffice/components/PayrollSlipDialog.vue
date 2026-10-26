<template>
  <Dialog :visible="visible" modal :header="`Phiếu lương tháng ${periodLabel}`" :style="{ width: '640px' }" @update:visible="emit('update:visible', $event)">
    <div v-if="slip" class="flex flex-col gap-4 text-xs">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
          <div class="text-sm font-semibold text-slate-900">{{ slip.user_name }}</div>
          <div class="font-mono text-slate-500">{{ slip.employee_code }}</div>
        </div>
        <div class="text-right">
          <div class="text-[11px] text-slate-500">Thực nhận</div>
          <div class="text-xl font-bold font-mono text-emerald-700">{{ formatCurrency(slip.net_pay) }}</div>
        </div>
      </div>

      <div class="grid grid-cols-3 gap-2 text-slate-600">
        <div class="bg-slate-50 rounded-[6px] px-3 py-2">Công chuẩn <strong class="block font-mono text-slate-900">{{ formatDays(slip.standard_days) }}</strong></div>
        <div class="bg-slate-50 rounded-[6px] px-3 py-2">Công hưởng lương <strong class="block font-mono text-slate-900">{{ formatDays(slip.paid_days) }}</strong></div>
        <div class="bg-slate-50 rounded-[6px] px-3 py-2">Giờ OT <strong class="block font-mono text-slate-900">{{ otHours }}</strong></div>
        <div class="bg-slate-50 rounded-[6px] px-3 py-2">Vắng <strong class="block font-mono text-slate-900">{{ slip.absent_days }} ngày</strong></div>
        <div class="bg-slate-50 rounded-[6px] px-3 py-2">Đi trễ / về sớm <strong class="block font-mono text-slate-900">{{ slip.violation_count }} lần</strong></div>
        <div class="bg-slate-50 rounded-[6px] px-3 py-2">Nghỉ không lương <strong class="block font-mono text-slate-900">{{ formatDays(slip.unpaid_days) }} ngày</strong></div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <section class="border border-slate-200 rounded-[6px] overflow-hidden">
          <div class="px-3 py-2 bg-emerald-50 text-emerald-800 font-semibold">Thu nhập</div>
          <div v-for="line in earnings" :key="line.label" class="px-3 py-1.5 border-t border-slate-100 flex justify-between gap-3">
            <span class="text-slate-700">{{ line.label }}<span v-if="line.note" class="block text-[10px] text-slate-400">{{ line.note }}</span></span>
            <span class="font-mono">{{ formatNumber(line.amount) }}</span>
          </div>
          <div class="px-3 py-2 border-t border-slate-200 flex justify-between font-semibold"><span>Tổng thu nhập</span><span class="font-mono">{{ formatNumber(slip.gross_income) }}</span></div>
        </section>
        <section class="border border-slate-200 rounded-[6px] overflow-hidden">
          <div class="px-3 py-2 bg-rose-50 text-rose-800 font-semibold">Khấu trừ</div>
          <div v-for="line in deductions" :key="line.label" class="px-3 py-1.5 border-t border-slate-100 flex justify-between gap-3">
            <span class="text-slate-700">{{ line.label }}<span v-if="line.note" class="block text-[10px] text-slate-400">{{ line.note }}</span></span>
            <span class="font-mono">{{ formatNumber(line.amount) }}</span>
          </div>
          <div class="px-3 py-2 border-t border-slate-200 flex justify-between font-semibold"><span>Tổng khấu trừ</span><span class="font-mono">{{ formatNumber(totalDeductions) }}</span></div>
        </section>
      </div>

      <div v-if="pitBrackets.length" class="border border-slate-200 rounded-[6px] overflow-hidden">
        <div class="px-3 py-2 bg-slate-50 font-semibold text-slate-700">Cách tính thuế TNCN · thu nhập chịu thuế {{ formatNumber(slip.taxable_income) }}</div>
        <div v-for="bracket in pitBrackets" :key="bracket.from" class="px-3 py-1.5 border-t border-slate-100 flex justify-between">
          <span class="text-slate-600">{{ formatNumber(bracket.from) }} – {{ bracket.to === null ? 'trở lên' : formatNumber(bracket.to) }} · {{ Math.round(bracket.rate * 100) }}%</span>
          <span class="font-mono">{{ formatNumber(bracket.amount) }}</span>
        </div>
      </div>
      <p v-if="slip.breakdown?.shortfall" class="m-0 text-amber-700">Khấu trừ vượt thu nhập {{ formatNumber(slip.breakdown.shortfall) }} đ; thực nhận được tính 0 và khoản vượt cần xử lý riêng.</p>
    </div>
    <template #footer>
      <Button label="Đóng" text severity="secondary" size="small" @click="emit('update:visible', false)" />
    </template>
  </Dialog>
</template>

<script setup>
import { computed } from 'vue';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import { formatCurrency, formatNumber } from '../../../utils/format';
import { formatDays } from '../constants';

const props = defineProps({
  visible: { type: Boolean, default: false },
  slip: { type: Object, default: null },
  periodLabel: { type: String, default: '' }
});
const emit = defineEmits(['update:visible']);

const breakdown = computed(() => props.slip?.breakdown || {});
const pitBrackets = computed(() => breakdown.value.pit_brackets || []);
const otHours = computed(() => {
  const slip = props.slip;
  return slip ? Number(slip.ot_hours_weekday) + Number(slip.ot_hours_weekend) + Number(slip.ot_hours_holiday) : 0;
});
const money = value => Number(value || 0);

const earnings = computed(() => {
  const slip = props.slip;
  if (!slip) return [];
  const ot = breakdown.value.overtime || {};
  const note = (type, label) => ot[type]?.hours ? `${label}: ${ot[type].hours} giờ × ${ot[type].multiplier * 100}%` : '';
  return [
    { label: 'Lương theo công', amount: money(slip.salary_amount), note: `${formatNumber(slip.base_salary)} × ${formatDays(slip.paid_days)}/${formatDays(slip.standard_days)}` },
    { label: 'Phụ cấp cố định', amount: money(slip.allowance_amount) },
    { label: 'Làm thêm giờ', amount: money(slip.ot_amount), note: [note('WEEKDAY', 'Ngày thường'), note('WEEKEND', 'Ngày nghỉ'), note('HOLIDAY', 'Ngày lễ')].filter(Boolean).join(' · ') },
    { label: 'Thưởng / điều chỉnh tăng', amount: money(slip.bonus_amount) }
  ].filter(line => line.amount > 0 || line.label === 'Lương theo công');
});
const deductions = computed(() => {
  const slip = props.slip;
  if (!slip) return [];
  return [
    { label: 'Bảo hiểm (BHXH, BHYT, BHTN)', amount: money(slip.insurance_amount), note: breakdown.value.insurance_base ? `Trên mức ${formatNumber(breakdown.value.insurance_base)}` : '' },
    { label: 'Thuế thu nhập cá nhân', amount: money(slip.pit_amount) },
    { label: 'Phạt đi trễ / về sớm', amount: money(slip.penalty_amount) },
    { label: 'Khấu trừ khác', amount: money(slip.deduction_amount) }
  ].filter(line => line.amount > 0);
});
const totalDeductions = computed(() => money(props.slip?.insurance_amount) + money(props.slip?.pit_amount) + money(props.slip?.penalty_amount) + money(props.slip?.deduction_amount));
</script>
