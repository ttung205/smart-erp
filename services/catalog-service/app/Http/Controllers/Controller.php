<?php

namespace App\Http\Controllers;

use App\Auth\Actor;
use Illuminate\Http\Request;

abstract class Controller
{
    /** Người dùng đã được middleware jwt xác thực. */
    protected function actor(Request $request): Actor
    {
        return $request->attributes->get('actor');
    }
}
