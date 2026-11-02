<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class PartnerController extends Controller
{
    #[OA\Get(
        path: '/partners',
        summary: 'Danh sách đối tác',
        tags: ['Đối tác'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'type', in: 'query', required: false, description: 'Loại đối tác, không phân biệt hoa thường', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Danh sách đối tác'),
            new OA\Response(response: 401, description: 'UNAUTHENTICATED hoặc AUTH_TOKEN_MISSING: thiếu hoặc sai token'),
        ]
    )]
    public function index(Request $request)
    {
        $type = $request->input('type');
        $query = DB::table('partners');
        if ($type) {
            $query->where('type', strtoupper($type));
        }

        return response()->json(['success' => true, 'data' => $query->get()]);
    }
}
