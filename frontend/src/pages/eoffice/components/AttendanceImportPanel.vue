<template>
  <div class="space-y-4">
    <div class="bg-white border border-slate-200 rounded-[6px] p-4 flex flex-col gap-3 text-xs">
      <div class="flex flex-wrap items-center gap-3">
        <input ref="fileInput" type="file" accept=".xlsx,.xls,.csv" class="hidden" @change="onFile" />
        <Button label="Chọn file máy chấm công" icon="pi pi-upload" size="small" @click="fileInput.click()" />
        <Button label="Tải file mẫu" icon="pi pi-download" size="small" severity="secondary" outlined @click="downloadTemplate" />
        <span v-if="fileName" class="text-slate-600">{{ fileName }} · <strong class="font-mono">{{ rows.length }}</strong> dòng hợp lệ về định dạng</span>
      </div>
      <p class="m-0 text-slate-500">
        File gồm các cột <strong>Mã nhân viên</strong>, <strong>Thời gian</strong> (ngày giờ, ví dụ 26/10/2026 08:03) và tùy chọn <strong>Mã máy</strong>.
        Cũng nhận file tách cột <em>Ngày</em> và <em>Giờ</em>. Nhập lại cùng một file không tạo log trùng.
      </p>
      <div v-if="parseWarnings.length" class="bg-amber-50 border border-amber-200 rounded-[6px] px-3 py-2 text-amber-900">
        <div v-for="warning in parseWarnings" :key="warning">{{ warning }}</div>
      </div>
      <div v-if="rows.length" class="border border-slate-200 rounded-[6px] overflow-hidden">
        <div class="px-3 py-2 bg-slate-50 font-semibold text-slate-700">Xem trước {{ Math.min(rows.length, 8) }} / {{ rows.length }} dòng</div>
        <table class="w-full">
          <tbody>
            <tr v-for="(row, index) in rows.slice(0, 8)" :key="index" class="border-t border-slate-100">
              <td class="px-3 py-1.5 font-mono">{{ row.employee_code }}</td>
              <td class="px-3 py-1.5 font-mono">{{ row.punched_at }}</td>
              <td class="px-3 py-1.5 text-slate-500">{{ row.device_id }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="flex items-center gap-3">
        <Button label="Nhập vào hệ thống" icon="pi pi-check" size="small" severity="success" :disabled="!rows.length" :loading="importing" @click="runImport" />
        <span v-if="result" class="text-slate-700">
          Đã nhập <strong class="font-mono text-emerald-700">{{ result.imported }}</strong> · trùng <strong class="font-mono">{{ result.duplicates }}</strong> ·
          lỗi <strong class="font-mono" :class="result.errors.length ? 'text-rose-600' : ''">{{ result.errorCount }}</strong>
        </span>
      </div>
      <div v-if="result?.errors.length" class="border border-rose-200 rounded-[6px] overflow-auto max-h-56">
        <div class="px-3 py-2 bg-rose-50 text-rose-800 font-semibold">Dòng lỗi (các dòng đúng vẫn được nhập)</div>
        <div v-for="error in result.errors" :key="error.row + error.message" class="px-3 py-1.5 border-t border-rose-100">
          Dòng {{ error.row }} · <span class="font-mono">{{ error.employee_code || '—' }}</span> · {{ error.message }}
        </div>
      </div>
    </div>

    <CommonDataTable :value="batches" :columns="batchColumns" :rows="10" empty-icon="pi pi-file" empty-title="Chưa có lần nhập file nào">
      <template #col-created_at="{ data }"><span class="text-xs">{{ formatDateTime(data.created_at) }}</span></template>
      <template #col-file_name="{ data }"><span class="text-xs">{{ data.file_name || '(không tên)' }} <span class="text-slate-400">· {{ data.source }}</span></span></template>
      <template #col-imported_rows="{ data }"><span class="font-mono text-xs text-emerald-700">{{ data.imported_rows }}</span></template>
      <template #col-error_rows="{ data }"><span class="font-mono text-xs" :class="data.error_rows ? 'text-rose-600' : 'text-slate-400'">{{ data.error_rows }}</span></template>
    </CommonDataTable>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import * as XLSX from 'xlsx';
import Button from 'primevue/button';
import CommonDataTable from '../../../components/common/CommonDataTable.vue';
import { attendanceApi } from '../../../api/attendance.api';
import { formatDateTime } from '../../../utils/format';

const emit = defineEmits(['imported']);
const toast = useToast();
const CHUNK = 4000;

const fileInput = ref(null);
const fileName = ref('');
const rows = ref([]);
const parseWarnings = ref([]);
const importing = ref(false);
const result = ref(null);
const batches = ref([]);

const batchColumns = [
  { field: 'created_at', header: 'Thời gian nhập', width: '160px', sortable: false },
  { field: 'file_name', header: 'File', sortable: false },
  { field: 'total_rows', header: 'Số dòng', width: '90px', align: 'right', sortable: false },
  { field: 'imported_rows', header: 'Đã nhập', width: '90px', align: 'right', sortable: false },
  { field: 'duplicate_rows', header: 'Trùng', width: '80px', align: 'right', sortable: false },
  { field: 'error_rows', header: 'Lỗi', width: '70px', align: 'right', sortable: false },
  { field: 'created_by_name', header: 'Người nhập', width: '170px', sortable: false }
];

const normalize = value => String(value ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/đ/gi, 'd').toLowerCase().trim();
const pad = n => String(n).padStart(2, '0');

// Excel lưu ngày giờ là số ngày kể từ 30/12/1899; tự đổi để không phụ thuộc múi giờ trình duyệt
const serialToParts = serial => {
  const days = Math.floor(serial);
  const seconds = Math.round((serial - days) * 86400);
  const date = new Date(Date.UTC(1899, 11, 30 + days));
  return { date: `${date.getUTCFullYear()}-${pad(date.getUTCMonth() + 1)}-${pad(date.getUTCDate())}`, time: `${pad(Math.floor(seconds / 3600) % 24)}:${pad(Math.floor(seconds / 60) % 60)}:${pad(seconds % 60)}` };
};

const toText = (value, part) => {
  if (value === null || value === undefined || value === '') return '';
  if (typeof value === 'number') {
    const parts = serialToParts(value);
    if (part === 'date') return parts.date;
    if (part === 'time') return parts.time;
    return value >= 1 ? `${parts.date} ${parts.time}` : parts.time;
  }
  return String(value).trim();
};

const findColumn = (header, names) => header.findIndex(cell => names.some(name => normalize(cell) === name || normalize(cell).includes(name)));

const parseSheet = matrix => {
  parseWarnings.value = [];
  const headerIndex = matrix.findIndex(row => row.some(cell => ['ma nv', 'ma nhan vien', 'employee_code', 'ma', 'code', 'no.'].some(name => normalize(cell) === name || normalize(cell).includes(name))));
  if (headerIndex < 0) {
    parseWarnings.value.push('Không tìm thấy dòng tiêu đề. Cần có cột "Mã nhân viên" và "Thời gian".');
    return [];
  }
  const header = matrix[headerIndex];
  const codeCol = findColumn(header, ['ma nhan vien', 'ma nv', 'employee_code', 'code', 'ma', 'no.']);
  const timeCol = findColumn(header, ['thoi gian', 'ngay gio', 'punched_at', 'datetime', 'timestamp']);
  const dateCol = timeCol < 0 ? findColumn(header, ['ngay', 'date']) : -1;
  const hourCol = timeCol < 0 ? findColumn(header, ['gio', 'time']) : -1;
  const deviceCol = findColumn(header, ['ma may', 'may', 'device']);
  if (codeCol < 0 || (timeCol < 0 && (dateCol < 0 || hourCol < 0))) {
    parseWarnings.value.push('Thiếu cột bắt buộc: "Mã nhân viên" và "Thời gian" (hoặc hai cột "Ngày" + "Giờ").');
    return [];
  }

  const out = [];
  for (const row of matrix.slice(headerIndex + 1)) {
    const code = toText(row[codeCol]);
    const punchedAt = timeCol >= 0 ? toText(row[timeCol]) : `${toText(row[dateCol], 'date')} ${toText(row[hourCol], 'time')}`.trim();
    if (!code && !punchedAt) continue;
    out.push({ employee_code: code, punched_at: punchedAt, device_id: deviceCol >= 0 ? toText(row[deviceCol]) : '' });
  }
  return out;
};

const onFile = async event => {
  const file = event.target.files?.[0];
  event.target.value = '';
  if (!file) return;
  result.value = null;
  try {
    const workbook = XLSX.read(await file.arrayBuffer(), { type: 'array', cellDates: false });
    const sheet = workbook.Sheets[workbook.SheetNames[0]];
    const matrix = XLSX.utils.sheet_to_json(sheet, { header: 1, raw: true, defval: '' });
    rows.value = parseSheet(matrix);
    fileName.value = file.name;
    if (!rows.value.length && !parseWarnings.value.length) parseWarnings.value.push('File không có dòng dữ liệu nào.');
  } catch (error) {
    rows.value = [];
    parseWarnings.value = [`Không đọc được file: ${error.message}`];
  }
};

const downloadTemplate = () => {
  const sheet = XLSX.utils.aoa_to_sheet([
    ['Mã nhân viên', 'Thời gian', 'Mã máy'],
    ['NV0007', '26/10/2026 08:03:10', 'MCC-01'],
    ['NV0007', '26/10/2026 17:12:45', 'MCC-01']
  ]);
  const book = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(book, sheet, 'Cham cong');
  XLSX.writeFile(book, 'mau-nhap-cham-cong.xlsx');
};

const runImport = async () => {
  importing.value = true;
  const total = { imported: 0, duplicates: 0, errorCount: 0, errors: [] };
  try {
    for (let start = 0; start < rows.value.length; start += CHUNK) {
      const { data } = await attendanceApi.importPunches(fileName.value, rows.value.slice(start, start + CHUNK));
      total.imported += data.imported_rows;
      total.duplicates += data.duplicate_rows;
      total.errorCount += data.error_rows;
      total.errors.push(...(data.errors || []).map(error => ({ ...error, row: error.row + start })));
    }
    result.value = total;
    toast.add({ severity: total.errorCount ? 'warn' : 'success', summary: 'Đã nhập file chấm công', detail: `${total.imported} log mới, ${total.duplicates} trùng, ${total.errorCount} lỗi`, life: 5000 });
    emit('imported');
    await loadBatches();
  } catch (error) {
    result.value = total.imported || total.errorCount ? total : null;
    toast.add({ severity: 'error', summary: 'Không nhập được file', detail: error.message, life: 6000 });
  } finally {
    importing.value = false;
  }
};

const loadBatches = async () => {
  try {
    batches.value = (await attendanceApi.getImportBatches()).data || [];
  } catch {
    batches.value = [];
  }
};

onMounted(loadBatches);
</script>
