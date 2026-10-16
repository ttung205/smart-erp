<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/** Đọc dữ liệu tổ chức (phòng ban, nhân viên) từ identity-service. */
class IdentityClient
{
    public function departments(): array
    {
        return $this->get('/api/departments');
    }

    public function department(int $id): array
    {
        return $this->get("/api/departments/{$id}", 'Không tìm thấy phòng ban của người tạo đơn.');
    }

    public function user(int $id): array
    {
        return $this->get("/api/users/{$id}", 'Không tìm thấy tài khoản người tạo đơn.');
    }

    public function users(): array
    {
        return $this->get('/api/users');
    }

    /**
     * Nhân viên nhận $managerId làm line manager. Trả về rỗng nếu identity-service không phản hồi
     * (danh sách đơn vẫn hiển thị đơn của chính mình và đơn chờ mình duyệt).
     *
     * @return list<int>
     */
    public function directReportIds(int $managerId): array
    {
        try {
            $users = $this->users();
        } catch (ValidationException) {
            return [];
        }

        return collect($users)->where('line_manager_id', $managerId)->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
    }

    private function get(string $path, string $notFound = 'Không đọc được dữ liệu từ identity-service.'): array
    {
        try {
            $response = Http::baseUrl((string) config('services.identity.url'))
                ->withHeaders(['X-Internal-Service-Key' => (string) config('services.internal.key')])
                ->acceptJson()->timeout(5)->get($path);
        } catch (ConnectionException) {
            throw ValidationException::withMessages(['identity' => 'Không kết nối được identity-service.']);
        }
        if (! $response->successful()) {
            throw ValidationException::withMessages(['identity' => $notFound]);
        }

        return (array) $response->json('data', []);
    }
}
