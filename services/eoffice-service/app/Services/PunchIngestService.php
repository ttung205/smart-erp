<?php

namespace App\Services;

use App\Models\AttendanceDay;
use App\Models\AttendanceImportBatch;
use App\Models\EmployeeProfile;
use App\Models\PunchLog;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Nhận log chấm công từ nhiều nguồn (file máy chấm công, simulator, web) vào cùng bảng punch_logs.
 * Nhập lại cùng một dòng không tạo log mới; dòng lỗi được báo lại, các dòng đúng vẫn được nhận.
 */
class PunchIngestService
{
    public const MAX_ROWS = 5000;

    /** Các định dạng thời gian thường gặp khi xuất file từ máy chấm công / Excel. */
    private const FORMATS = ['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d\TH:i:s', 'd/m/Y H:i:s', 'd/m/Y H:i', 'd-m-Y H:i:s', 'd-m-Y H:i', 'j/n/Y G:i:s', 'j/n/Y G:i'];

    private const MAX_ERROR_DETAILS = 200;

    public function __construct(private readonly AttendanceService $attendance) {}

    /**
     * @param  list<array{employee_code?: mixed, punched_at?: mixed, device_id?: mixed}>  $rows
     */
    public function importRows(array $rows, string $source, ?object $actor = null, ?string $fileName = null): AttendanceImportBatch
    {
        if (count($rows) > self::MAX_ROWS) {
            throw ValidationException::withMessages(['rows' => 'Mỗi lần nhập tối đa '.self::MAX_ROWS.' dòng, vui lòng chia nhỏ file.']);
        }

        $profiles = EmployeeProfile::whereIn('employee_code', collect($rows)->pluck('employee_code')->filter()->map(fn ($code) => trim((string) $code))->unique()->all())
            ->pluck('user_id', 'employee_code');

        $imported = 0;
        $duplicates = 0;
        $errors = [];
        $errorCount = 0;
        $affected = [];
        $batch = DB::transaction(function () use ($rows, $source, $actor, $fileName, $profiles, &$imported, &$duplicates, &$errors, &$errorCount, &$affected) {
            $batch = AttendanceImportBatch::create([
                'file_name' => $fileName, 'source' => $source, 'total_rows' => count($rows),
                'created_by' => $actor?->id, 'created_by_name' => $actor?->name,
            ]);

            foreach (array_values($rows) as $index => $row) {
                $line = $index + 1;
                $code = trim((string) ($row['employee_code'] ?? ''));
                $userId = $profiles[$code] ?? null;
                $punchedAt = $this->parseTime($row['punched_at'] ?? null);

                $problem = match (true) {
                    $code === '' => 'Thiếu mã nhân viên.',
                    $userId === null => "Mã nhân viên [{$code}] không tồn tại.",
                    $punchedAt === null => 'Thời gian chấm công không hợp lệ.',
                    $punchedAt->isAfter(CarbonImmutable::now()->addMinutes(5)) => 'Thời gian chấm công nằm ở tương lai.',
                    default => null,
                };
                if ($problem !== null) {
                    $errorCount++;
                    if (count($errors) < self::MAX_ERROR_DETAILS) {
                        $errors[] = ['row' => $line, 'employee_code' => $code, 'message' => $problem];
                    }

                    continue;
                }

                $log = PunchLog::firstOrCreate(
                    ['user_id' => $userId, 'punched_at' => $punchedAt, 'source' => $source],
                    ['work_date' => $punchedAt->toDateString(), 'device_id' => $this->deviceId($row['device_id'] ?? null), 'import_batch_id' => $batch->id],
                );
                if ($log->wasRecentlyCreated) {
                    $imported++;
                    $affected["{$userId}|{$punchedAt->toDateString()}"] = [$userId, $punchedAt->toDateString()];
                } else {
                    $duplicates++;
                }
            }

            $batch->update(['imported_rows' => $imported, 'duplicate_rows' => $duplicates, 'error_rows' => $errorCount, 'errors' => $errors]);

            return $batch;
        });

        foreach ($affected as [$userId, $date]) {
            $this->attendance->recalculate($userId, $date);
        }

        return $batch->fresh();
    }

    /** Nhân viên tự chấm công trên web (chỉ làm việc từ xa), tối thiểu 60 giây giữa hai lần bấm. */
    public function checkInOnWeb(object $actor): AttendanceDay
    {
        $profile = EmployeeProfile::where('user_id', $actor->id)->first();
        if (! $profile?->canCheckInOnWeb()) {
            throw ValidationException::withMessages(['attendance' => 'Chỉ nhân viên làm việc từ xa mới chấm công trên web. Hãy dùng máy chấm công hoặc gửi yêu cầu bổ sung công.']);
        }

        $now = CarbonImmutable::now()->startOfSecond();
        $recent = PunchLog::where('user_id', $actor->id)->where('source', PunchLog::WEB)->whereNull('voided_at')
            ->where('punched_at', '>', $now->subSeconds(60))->exists();
        if ($recent) {
            throw ValidationException::withMessages(['attendance' => 'Bạn vừa chấm công, vui lòng đợi một phút trước khi bấm lại.']);
        }

        PunchLog::create(['user_id' => $actor->id, 'punched_at' => $now, 'work_date' => $now->toDateString(), 'source' => PunchLog::WEB]);

        return $this->attendance->recalculate($actor->id, $now);
    }

    private function parseTime(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value)->startOfSecond();
        }
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }
        foreach (self::FORMATS as $format) {
            try {
                $time = CarbonImmutable::createFromFormat('!'.$format, $text);
            } catch (InvalidFormatException) {
                continue;
            }
            // createFromFormat tự "cuộn" ngày sai (31/02 → 03/03): chỉ nhận khi định dạng lại ra đúng chuỗi gốc
            if ($time->format($format) === $text) {
                return $time;
            }
        }

        return null;
    }

    private function deviceId(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : mb_substr($text, 0, 50);
    }
}
