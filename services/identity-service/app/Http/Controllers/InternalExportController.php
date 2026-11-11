<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Xuất danh sách nhân viên và vai trò cho kho phân tích của ai-service (chỉ service nội bộ, X-Internal-Service-Key).
 * Phân trang theo khóa chính: ?after_id=0&limit=500, next_after_id = null khi hết dữ liệu.
 */
class InternalExportController extends Controller
{
    private const DEFAULT_LIMIT = 500;

    private const MAX_LIMIT = 1000;

    public function staff(Request $request): JsonResponse
    {
        $afterId = max(0, (int) $request->query('after_id', 0));
        $limit = min(self::MAX_LIMIT, max(1, (int) $request->query('limit', self::DEFAULT_LIMIT)));

        $users = DB::table('users')
            ->select(['id', 'name', 'email'])
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $roleNames = DB::table('user_roles as ur')
            ->join('roles as r', 'r.id', '=', 'ur.role_id')
            ->whereIn('ur.user_id', $users->pluck('id'))
            ->orderBy('r.name')
            ->get(['ur.user_id', 'r.name'])
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->pluck('name')->implode(', '));

        $items = $users->map(fn ($u) => [
            'id' => (int) $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'roles' => $roleNames[$u->id] ?? '',
        ])->values()->all();

        $nextAfterId = count($items) === $limit && $items ? $items[count($items) - 1]['id'] : null;

        return response()->json(['success' => true, 'data' => ['items' => $items, 'next_after_id' => $nextAfterId]]);
    }
}
