<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PartnerController extends Controller
{
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
