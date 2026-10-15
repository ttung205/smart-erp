<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

/**
 * Xác định người duyệt từ identity-service, theo thứ tự ưu tiên:
 * 1. Quản lý trực tiếp (line manager) của người tạo đơn
 * 2. Trưởng phòng của người tạo đơn
 * 3. Trưởng phòng cấp cao nhất (BOD); nếu chính người tạo là cấp cao nhất thì đơn được duyệt thẳng
 */
class ApproverResolver
{
    public function __construct(private readonly IdentityClient $identity) {}

    /**
     * @return array{id: int, name: string}|null null khi người tạo là cấp cao nhất (đơn được duyệt thẳng)
     */
    public function resolve(int $requesterId, ?int $departmentId): ?array
    {
        $requester = $this->identity->user($requesterId);
        $lineManagerId = (int) ($requester['line_manager_id'] ?? 0);
        if ($lineManagerId && $lineManagerId !== $requesterId) {
            return ['id' => $lineManagerId, 'name' => (string) ($requester['line_manager_name'] ?? 'Quản lý trực tiếp')];
        }

        $departmentId = (int) ($requester['department_id'] ?? 0) ?: $departmentId;
        if (! $departmentId) {
            throw ValidationException::withMessages(['approver' => 'Tài khoản chưa thuộc phòng ban nào nên không xác định được người duyệt.']);
        }

        $department = $this->identity->department($departmentId);
        $managerId = (int) ($department['manager_id'] ?? 0);
        if ($managerId && $managerId !== $requesterId) {
            return ['id' => $managerId, 'name' => (string) ($department['manager_name'] ?? 'Trưởng phòng')];
        }

        // Người tạo là trưởng phòng (hoặc phòng chưa có trưởng phòng) → chuyển lên phòng cấp cao nhất
        $topCode = (string) config('eoffice.top_department_code');
        $top = collect($this->identity->departments())->firstWhere('code', $topCode);
        $topManagerId = (int) ($top['manager_id'] ?? 0);
        if (! $topManagerId) {
            throw ValidationException::withMessages(['approver' => 'Phòng ban chưa có trưởng phòng để duyệt đơn.']);
        }

        return $topManagerId === $requesterId ? null : ['id' => $topManagerId, 'name' => (string) ($top['manager_name'] ?? 'Ban Giám Đốc')];
    }
}
