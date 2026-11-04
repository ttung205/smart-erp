<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ChartOfAccountController extends Controller
{
    #[OA\Get(
        path: '/accounts',
        summary: 'Hệ thống tài khoản kế toán',
        description: 'Yêu cầu quyền finance.view. Sắp xếp theo mã tài khoản.',
        tags: ['Tài khoản kế toán'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, description: 'Tìm theo mã hoặc tên tài khoản', schema: new OA\Schema(type: 'string', example: '131')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Danh sách tài khoản kèm tài khoản cha'),
            new OA\Response(response: 401, description: 'UNAUTHENTICATED: thiếu hoặc sai token'),
            new OA\Response(response: 403, description: 'FORBIDDEN: thiếu quyền finance.view'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = ChartOfAccount::with('parent')->orderBy('code');
        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(fn ($item) => $item->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"));
        }

        return response()->json(['success' => true, 'data' => $query->get()]);
    }
}
