<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class WarehouseController extends Controller
{
    public function index()
    {
        $warehouses = DB::table('warehouses')->where('is_active', 1)->get();
        return response()->json(['success' => true, 'data' => $warehouses]);
    }
}
