<template>
  <div class="h-full flex flex-col min-h-0">
    <div ref="scroller" class="flex-1 overflow-y-auto bg-slate-50/50" :class="large ? 'px-6 py-6' : 'px-3 py-3'">
      <div :class="large ? 'max-w-4xl mx-auto space-y-5' : 'space-y-3'">
        <div v-if="!ai.messages.length" :class="large ? 'pt-10' : 'pt-4'">
          <div v-if="large" class="flex flex-col items-center text-center mb-6">
            <span class="w-14 h-14 rounded-full bg-violet-100 text-violet-700 flex items-center justify-center mb-3">
              <i class="pi pi-sparkles text-2xl"></i>
            </span>
            <div class="text-lg font-semibold text-slate-800">Xin chào {{ firstName }}</div>
            <p class="text-sm text-slate-500 m-0 mt-1">Hỏi về doanh số, tồn kho, mua hàng, công nợ... Trợ lý chỉ đọc dữ liệu theo quyền của bạn.</p>
          </div>
          <div v-else class="text-xs text-slate-600 mb-2">Xin chào {{ firstName }}, bạn có thể hỏi ví dụ:</div>
          <div :class="large ? 'grid grid-cols-1 md:grid-cols-2 gap-2' : 'flex flex-col gap-1.5'">
            <button
              v-for="s in suggestions"
              :key="s"
              type="button"
              class="text-left rounded-[6px] border border-slate-200 bg-white hover:border-violet-300 hover:bg-violet-50 text-slate-700 cursor-pointer"
              :class="large ? 'text-sm px-4 py-3' : 'text-xs px-3 py-2'"
              @click="send(s)"
            >
              {{ s }}
            </button>
          </div>
        </div>

        <div v-for="(m, idx) in ai.messages" :key="idx" class="flex" :class="m.role === 'user' ? 'justify-end' : 'justify-start'">
          <div
            v-if="m.role === 'user'"
            class="max-w-[85%] rounded-[6px] bg-violet-600 text-white whitespace-pre-wrap break-words"
            :class="textSize"
          >{{ m.content }}</div>
          <div
            v-else
            class="rounded-[6px] bg-white border border-slate-200 text-slate-800 break-words"
            :class="[textSize, large ? 'max-w-full w-full' : 'max-w-[95%]']"
          >
            <div v-if="m.error" class="text-rose-600 flex items-start gap-1.5">
              <i class="pi pi-exclamation-circle mt-0.5"></i><span>{{ m.content }}</span>
            </div>
            <div v-else class="leading-relaxed" :class="large ? 'ai-answer-lg' : ''" v-html="renderMarkdown(m.content)"></div>
            <div v-if="m.tools?.length" class="mt-1.5 text-slate-400" :class="large ? 'text-xs' : 'text-[10px]'">
              Nguồn: {{ m.tools.map((t) => TOOL_LABELS[t] || t).join(', ') }}
            </div>
          </div>
        </div>

        <div v-if="ai.sending" class="flex justify-start">
          <div class="rounded-[6px] bg-white border border-slate-200 text-slate-500 flex items-center gap-2" :class="textSize">
            <i class="pi pi-spin pi-spinner text-violet-600"></i> Đang tra cứu dữ liệu...
          </div>
        </div>
      </div>
    </div>

    <form class="border-t border-slate-200 shrink-0 bg-white" :class="large ? 'p-4' : 'p-2'" @submit.prevent="send()">
      <div class="flex items-end gap-2" :class="large ? 'max-w-4xl mx-auto' : ''">
        <textarea
          ref="input"
          v-model="draft"
          :rows="large ? 2 : 1"
          maxlength="2000"
          placeholder="Nhập câu hỏi... (Enter để gửi, Shift+Enter xuống dòng)"
          class="flex-1 resize-none border border-slate-300 rounded-[6px] focus:outline-none focus:border-violet-500"
          :class="large ? 'text-sm px-3 py-2.5 max-h-48' : 'text-xs px-2.5 py-2 max-h-28'"
          :disabled="ai.sending"
          @keydown.enter.exact.prevent="send()"
        ></textarea>
        <Button
          type="submit"
          icon="pi pi-send"
          :size="large ? undefined : 'small'"
          :label="large ? 'Gửi' : undefined"
          :disabled="!draft.trim() || ai.sending"
          class="!bg-violet-600 !border-violet-600"
        />
      </div>
    </form>
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import Button from 'primevue/button';
import { useAiStore } from '../../stores/aiStore';
import { hasPermission, canView } from '../../utils/rbac';
import { renderMarkdown } from './aiMarkdown';

const props = defineProps({
  currentUser: { type: Object, default: null },
  large: { type: Boolean, default: false }
});

const TOOL_LABELS = {
  sales_report: 'Báo cáo doanh số',
  compare_sales: 'So sánh doanh số',
  inventory_status: 'Tồn kho',
  product_info: 'Danh mục sản phẩm',
  product_trend: 'Xu hướng sản phẩm',
  customer_info: 'Khách hàng',
  receivables_report: 'Công nợ',
  purchase_report: 'Mua hàng',
  supplier_info: 'Nhà cung cấp',
  order_list: 'Đơn bán hàng',
  demand_forecast: 'Dự báo doanh số ML',
  rfm_customer_segmentation: 'Phân cụm khách hàng RFM',
  credit_risk_radar: 'Đánh giá rủi ro tín dụng',
  suggest_restock_plan: 'Kế hoạch nhập kho tự động',
  suggest_churn_retention: 'Kế hoạch giữ chân khách hàng',
  suggest_credit_action: 'Đề xuất xử lý công nợ'
};

const ai = useAiStore();
const draft = ref('');
const scroller = ref(null);
const input = ref(null);

const textSize = computed(() => (props.large ? 'text-sm px-4 py-3' : 'text-xs px-3 py-2'));
const firstName = computed(() => (props.currentUser?.name || '').split(' ').pop());

// Gợi ý câu hỏi theo quyền của người dùng
const suggestions = computed(() => {
  const u = props.currentUser;
  const list = [];
  if (canView('order', u) || hasPermission('report.view', u)) {
    list.push('Doanh thu tháng 9 theo ngành hàng so với tháng 8?');
    list.push('Top 5 đại lý mua nhiều nhất quý 3?');
  }
  if (canView('inventory', u)) list.push('Những mã nào sắp hết hàng cần nhập thêm?');
  if (canView('finance', u)) list.push('Khách nào đang nợ quá hạn nhiều nhất?');
  if (canView('purchase', u)) list.push('Nhà cung cấp nào hay giao trễ nhất?');
  if (canView('catalog', u)) list.push('Tra cứu giá và tồn kho điều hòa Daikin 1 HP');
  return list.slice(0, props.large ? 6 : 5);
});

async function scrollToEnd() {
  await nextTick();
  if (scroller.value) scroller.value.scrollTop = scroller.value.scrollHeight;
}

function focus() {
  nextTick(() => input.value?.focus());
}

async function send(text) {
  const message = (text ?? draft.value).trim();
  if (!message || ai.sending) return;
  draft.value = '';
  const pending = ai.send(message);
  scrollToEnd();
  await pending;
  scrollToEnd();
  focus();
}

watch(() => ai.messages.length, scrollToEnd);
watch(() => ai.conversationId, () => { scrollToEnd(); focus(); });

onMounted(() => {
  scrollToEnd();
  focus();
});

defineExpose({ focus });
</script>

<style scoped>
.ai-answer-lg :deep(table) {
  font-size: 13px;
}
</style>
