<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChartOfAccountController extends Controller
{
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
