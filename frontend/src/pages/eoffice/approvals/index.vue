<template>
  <div class="space-y-4 pb-8">
    <PageHeader title="Chờ Tôi Duyệt" subtitle="Các đơn nội bộ được gửi tới bạn với vai trò trưởng phòng">
      <template #actions>
        <Button label="Làm mới" icon="pi pi-refresh" size="small" severity="secondary" outlined :loading="loading" @click="load" />
      </template>
    </PageHeader>

    <div class="bg-white border border-slate-200 rounded-[6px] px-3 py-2 flex items-center gap-2">
      <SelectButton v-model="statusFilter" :options="statusTabs" option-label="label" option-value="value" :allow-empty="false" size="small" @change="load" />
      <span class="ml-auto text-xs text-slate-500">Đang chờ: <strong class="font-mono text-amber-600">{{ pendingCount }}</strong> đơn</span>
    </div>

    <CommonDataTable
      :value="steps"
      :columns="columns"
      :loading="loading"
      :rows="20"
      empty-icon="pi pi-check-square"
      :empty-title="statusFilter === 'PENDING' ? 'Không có đơn nào chờ bạn duyệt' : 'Chưa có đơn nào'"
    >
      <template #col-type="{ data }">
        <span class="text-xs text-slate-700">{{ data.document?.type_label }}</span>
      </template>
      <template #col-code="{ data }">
        <span class="font-mono text-xs font-semibold text-sky-700">{{ data.document?.code }}</span>
      </template>
      <template #col-requester="{ data }">
        <span class="text-xs text-slate-800">{{ data.document?.requester_name }}</span>
      </template>
      <template #col-title="{ data }">
        <span class="text-xs text-slate-800">{{ data.document?.title }}</span>
      </template>
      <template #col-created_at="{ data }">
        <span class="text-xs text-slate-600">{{ formatDateTime(data.created_at) }}</span>
      </template>
      <template #col-status="{ data }">
        <Tag :value="stepStatus[data.status]?.label" :severity="stepStatus[data.status]?.severity" />
      </template>

      <Column header="Thao tác" class="text-center" style="width: 170px">
        <template #body="{ data }">
          <div class="flex items-center justify-center gap-1">
            <Button v-tooltip.top="'Xem chi tiết đơn'" icon="pi pi-eye" text size="small" severity="secondary" class="!w-7 !h-7 !p-0" @click="openDocument(data)" />
            <template v-if="data.status === 'PENDING'">
              <Button v-tooltip.top="'Duyệt'" icon="pi pi-check" text size="small" severity="success" class="!w-7 !h-7 !p-0" @click="openDecision(data, 'APPROVED')" />
              <Button v-tooltip.top="'Trả lại để bổ sung'" icon="pi pi-reply" text size="small" class="!w-7 !h-7 !p-0" @click="openDecision(data, 'RETURNED')" />
              <Button v-tooltip.top="'Từ chối'" icon="pi pi-times" text size="small" severity="danger" class="!w-7 !h-7 !p-0" @click="openDecision(data, 'REJECTED')" />
            </template>
            <span v-else class="text-[11px] text-slate-400">{{ formatDateTime(data.acted_at) }}</span>
          </div>
        </template>
      </Column>
    </CommonDataTable>

    <Dialog v-model:visible="decision.visible" modal :header="decisionTitle" :style="{ width: '460px' }">
      <div class="flex flex-col gap-3 text-xs">
        <div class="bg-slate-50 border border-slate-200 rounded-[6px] px-3 py-2">
          <div class="font-mono font-semibold text-sky-700">{{ decision.step?.document?.code }}</div>
          <div class="text-slate-800">{{ decision.step?.document?.requester_name }} · {{ decision.step?.document?.title }}</div>
        </div>
        <label class="font-semibold text-slate-700">
          Ý kiến<span v-if="decision.type !== 'APPROVED'" class="text-rose-500"> *</span>
        </label>
        <Textarea v-model="decision.comment" rows="3" auto-resize class="w-full text-sm" :placeholder="decision.type === 'APPROVED' ? 'Không bắt buộc' : 'Nêu rõ lý do để người tạo đơn nắm được'" />
      </div>
      <template #footer>
        <Button label="Đóng" text severity="secondary" size="small" @click="decision.visible = false" />
        <Button :label="decisionMeta.button" :severity="decisionMeta.severity" size="small" :loading="decision.saving" @click="confirmDecision" />
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import Column from 'primevue/column';
import Dialog from 'primevue/dialog';
import SelectButton from 'primevue/selectbutton';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import CommonDataTable from '../../../components/common/CommonDataTable.vue';
import PageHeader from '../../../components/common/PageHeader.vue';
import { eofficeApi } from '../../../api/eoffice.api';
import { formatDateTime } from '../../../utils/format';

const router = useRouter();
const toast = useToast();

const steps = ref([]);
const pendingCount = ref(0);
const loading = ref(false);
const statusFilter = ref('PENDING');
const statusTabs = [{ label: 'Chờ duyệt', value: 'PENDING' }, { label: 'Tất cả', value: 'ALL' }];
const decision = reactive({ visible: false, step: null, type: 'APPROVED', comment: '', saving: false });

const stepStatus = {
  PENDING: { label: 'Chờ duyệt', severity: 'warn' },
  APPROVED: { label: 'Đã duyệt', severity: 'success' },
  REJECTED: { label: 'Từ chối', severity: 'danger' },
  RETURNED: { label: 'Trả lại', severity: 'info' },
  CANCELLED: { label: 'Người tạo đã hủy', severity: 'secondary' }
};
const decisionTypes = {
  APPROVED: { title: 'Duyệt Đơn', button: 'Duyệt', severity: 'success' },
  RETURNED: { title: 'Trả Lại Đơn Để Bổ Sung', button: 'Trả lại', severity: 'info' },
  REJECTED: { title: 'Từ Chối Đơn', button: 'Từ chối', severity: 'danger' }
};
const decisionMeta = computed(() => decisionTypes[decision.type]);
const decisionTitle = computed(() => `${decisionMeta.value.title} ${decision.step?.document?.code || ''}`);

const columns = [
  { field: 'type', header: 'Loại đơn', width: '150px', sortable: false },
  { field: 'code', header: 'Mã đơn', width: '140px', sortable: false },
  { field: 'requester', header: 'Người đề nghị', width: '220px', sortable: false },
  { field: 'title', header: 'Nội dung', sortable: false },
  { field: 'created_at', header: 'Ngày gửi', width: '160px' },
  { field: 'status', header: 'Trạng thái', width: '140px', align: 'center' }
];

const documentRoutes = { leave_request: id => `/eoffice/leave/${id}` };
const openDocument = step => {
  const toRoute = documentRoutes[step.document?.type];
  if (toRoute) router.push(toRoute(step.document.id));
};

const load = async () => {
  loading.value = true;
  try {
    const response = await eofficeApi.getApprovalInbox({ status: statusFilter.value, per_page: 100 });
    steps.value = response.data || [];
    pendingCount.value = response.pending_count || 0;
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Không tải được hộp duyệt', detail: error.message, life: 4000 });
  } finally {
    loading.value = false;
  }
};

const openDecision = (step, type) => Object.assign(decision, { visible: true, step, type, comment: '', saving: false });

const confirmDecision = async () => {
  if (decision.type !== 'APPROVED' && !decision.comment.trim()) {
    toast.add({ severity: 'warn', summary: 'Thiếu ý kiến', detail: 'Vui lòng nhập lý do khi từ chối hoặc trả lại đơn.', life: 3500 });
    return;
  }
  decision.saving = true;
  try {
    await eofficeApi.decideApproval(decision.step.id, decision.type, decision.comment);
    toast.add({ severity: 'success', summary: `Đã ${decisionMeta.value.button.toLowerCase()} đơn`, detail: decision.step.document?.code, life: 3000 });
    decision.visible = false;
    await load();
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Thao tác thất bại', detail: error.message, life: 5000 });
  } finally {
    decision.saving = false;
  }
};

onMounted(load);
</script>
