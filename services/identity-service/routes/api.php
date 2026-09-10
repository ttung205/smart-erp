<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'data' => [
            'service' => 'identity-service',
            'framework' => 'Laravel 11 / 12',
            'status' => 'UP',
            'timestamp' => now()->toIso8601String(),
        ],
    ]);
});

Route::post('/login', function (Request $request) {
    $email = trim($request->input('email', ''));
    $password = $request->input('password', '');

    if (empty($email) || empty($password)) {
        return response()->json([
            'success' => false,
            'error' => ['code' => 'VALIDATION_FAILED', 'message' => 'Email và mật khẩu không được để trống.']
        ], 422);
    }

    $user = DB::table('users')->where('email', $email)->where('is_active', 1)->first();

    if (!$user || !Hash::check($password, $user->password_hash)) {
        return response()->json([
            'success' => false,
            'error' => ['code' => 'INVALID_CREDENTIALS', 'message' => 'Email hoặc mật khẩu không chính xác.']
        ], 401);
    }

    $roles = DB::table('roles')
        ->join('user_roles', 'roles.id', '=', 'user_roles.role_id')
        ->where('user_roles.user_id', $user->id)
        ->pluck('code')
        ->all();

    $permissions = DB::table('permissions')
        ->join('role_permissions', 'permissions.id', '=', 'role_permissions.permission_id')
        ->join('user_roles', 'role_permissions.role_id', '=', 'user_roles.role_id')
        ->where('user_roles.user_id', $user->id)
        ->distinct()
        ->pluck('code')
        ->all();

    // Generate JWT
    $header = rtrim(strtr(base64_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256'])), '+/', '-_'), '=');
    $payload = rtrim(strtr(base64_encode(json_encode([
        'iss' => 'identity-service',
        'sub' => (string) $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'roles' => $roles,
        'permissions' => $permissions,
        'iat' => time(),
        'exp' => time() + 86400,
    ])), '+/', '-_'), '=');

    $secret = env('JWT_SECRET', 'smart_erp_super_secure_jwt_secret_key_2026');
    $sig = rtrim(strtr(base64_encode(hash_hmac('sha256', "{$header}.{$payload}", $secret, true)), '+/', '-_'), '=');
    $token = "{$header}.{$payload}.{$sig}";

    return response()->json([
        'success' => true,
        'data' => [
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => 86400,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $roles,
                'permissions' => $permissions,
            ],
        ],
    ]);
});

Route::get('/me', function (Request $request) {
    $auth = $request->header('Authorization', '');
    if (!preg_match('/Bearer\s(\S+)/i', $auth, $m)) {
        return response()->json(['success' => false, 'error' => ['code' => 'UNAUTHENTICATED']], 401);
    }

    $parts = explode('.', $m[1]);
    if (count($parts) !== 3) {
        return response()->json(['success' => false, 'error' => ['code' => 'TOKEN_INVALID']], 401);
    }

    $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
    return response()->json(['success' => true, 'data' => $payload]);
});

Route::get('/users', function () {
    $users = DB::table('users')
        ->leftJoin('departments', 'users.department_id', '=', 'departments.id')
        ->select('users.id', 'users.name', 'users.email', 'users.is_active', 'departments.name as department_name')
        ->get();
    return response()->json(['success' => true, 'data' => $users]);
});

Route::get('/roles', function () {
    $roles = DB::table('roles')->get();
    return response()->json(['success' => true, 'data' => $roles]);
});