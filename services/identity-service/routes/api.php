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
            'framework' => 'Laravel 13',
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

// --- DEPARTMENTS ---
Route::get('/departments', function () {
    $departments = DB::table('departments')->orderBy('name')->get();
    return response()->json(['success' => true, 'data' => $departments]);
});

// --- USERS (NHÂN VIÊN) ---
Route::get('/users', function () {
    $users = DB::table('users')
        ->leftJoin('departments', 'users.department_id', '=', 'departments.id')
        ->select(
            'users.id',
            'users.name',
            'users.email',
            'users.is_active',
            'users.department_id',
            'departments.name as department_name',
            'departments.code as department_code',
            'users.created_at'
        )
        ->orderBy('users.id')
        ->get();

    $userRoles = DB::table('user_roles')
        ->join('roles', 'user_roles.role_id', '=', 'roles.id')
        ->select('user_roles.user_id', 'roles.id as role_id', 'roles.code', 'roles.name')
        ->get()
        ->groupBy('user_id');

    foreach ($users as $user) {
        $user->roles = $userRoles->get($user->id, collect())->values()->all();
        $user->role_ids = collect($user->roles)->pluck('role_id')->all();
    }

    return response()->json(['success' => true, 'data' => $users]);
});

Route::post('/users', function (Request $request) {
    $validated = $request->validate([
        'name' => 'required|string|max:150',
        'email' => 'required|email|max:150|unique:users,email',
        'password' => 'nullable|string|min:6',
        'department_id' => 'nullable|exists:departments,id',
        'is_active' => 'nullable|boolean',
        'role_ids' => 'nullable|array',
        'role_ids.*' => 'exists:roles,id'
    ]);

    $id = DB::table('users')->insertGetId([
        'name' => $validated['name'],
        'email' => $validated['email'],
        'password_hash' => Hash::make($validated['password'] ?? 'password123'),
        'department_id' => $validated['department_id'] ?? null,
        'is_active' => array_key_exists('is_active', $validated) ? ($validated['is_active'] ? 1 : 0) : 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    if (!empty($validated['role_ids'])) {
        foreach ($validated['role_ids'] as $roleId) {
            DB::table('user_roles')->insert([
                'user_id' => $id,
                'role_id' => $roleId,
            ]);
        }
    }

    return response()->json(['success' => true, 'message' => 'Tạo nhân viên thành công', 'data' => ['id' => $id]], 201);
});

Route::put('/users/{id}', function (Request $request, $id) {
    $user = DB::table('users')->where('id', $id)->first();
    if (!$user) {
        return response()->json(['success' => false, 'message' => 'Không tìm thấy người dùng'], 404);
    }

    $validated = $request->validate([
        'name' => 'sometimes|required|string|max:150',
        'email' => 'sometimes|required|email|max:150|unique:users,email,' . $id,
        'password' => 'nullable|string|min:6',
        'department_id' => 'nullable|exists:departments,id',
        'is_active' => 'nullable|boolean',
        'role_ids' => 'nullable|array',
        'role_ids.*' => 'exists:roles,id'
    ]);

    $updateData = [];
    if (isset($validated['name'])) $updateData['name'] = $validated['name'];
    if (isset($validated['email'])) $updateData['email'] = $validated['email'];
    if (!empty($validated['password'])) $updateData['password_hash'] = Hash::make($validated['password']);
    if (array_key_exists('department_id', $validated)) $updateData['department_id'] = $validated['department_id'];
    if (array_key_exists('is_active', $validated)) $updateData['is_active'] = $validated['is_active'] ? 1 : 0;
    $updateData['updated_at'] = now();

    DB::table('users')->where('id', $id)->update($updateData);

    if (isset($validated['role_ids'])) {
        DB::table('user_roles')->where('user_id', $id)->delete();
        foreach ($validated['role_ids'] as $roleId) {
            DB::table('user_roles')->insert([
                'user_id' => $id,
                'role_id' => $roleId,
            ]);
        }
    }

    return response()->json(['success' => true, 'message' => 'Cập nhật nhân viên thành công']);
});

// --- ROLES (VAI TRÒ) ---
Route::get('/roles', function () {
    $roles = DB::table('roles')->orderBy('id')->get();

    $userCounts = DB::table('user_roles')
        ->select('role_id', DB::raw('count(user_id) as total_users'))
        ->groupBy('role_id')
        ->pluck('total_users', 'role_id');

    $rolePerms = DB::table('role_permissions')
        ->join('permissions', 'role_permissions.permission_id', '=', 'permissions.id')
        ->select('role_permissions.role_id', 'permissions.id as permission_id', 'permissions.code', 'permissions.description', 'permissions.module')
        ->get()
        ->groupBy('role_id');

    foreach ($roles as $r) {
        $r->users_count = (int) ($userCounts[$r->id] ?? 0);
        $perms = $rolePerms->get($r->id, collect());
        $r->permissions = $perms->values()->all();
        $r->permission_ids = $perms->pluck('permission_id')->all();
        $r->permission_codes = $perms->pluck('code')->all();
    }

    return response()->json(['success' => true, 'data' => $roles]);
});

Route::post('/roles', function (Request $request) {
    $validated = $request->validate([
        'code' => 'required|string|max:50|unique:roles,code',
        'name' => 'required|string|max:150',
        'description' => 'nullable|string|max:255',
        'permission_ids' => 'nullable|array',
        'permission_ids.*' => 'exists:permissions,id'
    ]);

    $roleId = DB::table('roles')->insertGetId([
        'code' => strtoupper(trim($validated['code'])),
        'name' => $validated['name'],
        'description' => $validated['description'] ?? null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    if (!empty($validated['permission_ids'])) {
        foreach ($validated['permission_ids'] as $permId) {
            DB::table('role_permissions')->insert([
                'role_id' => $roleId,
                'permission_id' => $permId,
            ]);
        }
    }

    return response()->json(['success' => true, 'message' => 'Tạo vai trò thành công', 'data' => ['id' => $roleId]], 201);
});

Route::put('/roles/{id}', function (Request $request, $id) {
    $role = DB::table('roles')->where('id', $id)->first();
    if (!$role) {
        return response()->json(['success' => false, 'message' => 'Không tìm thấy vai trò'], 404);
    }

    $validated = $request->validate([
        'name' => 'sometimes|required|string|max:150',
        'description' => 'nullable|string|max:255',
        'permission_ids' => 'nullable|array',
        'permission_ids.*' => 'exists:permissions,id'
    ]);

    $updateData = [];
    if (isset($validated['name'])) $updateData['name'] = $validated['name'];
    if (array_key_exists('description', $validated)) $updateData['description'] = $validated['description'];
    $updateData['updated_at'] = now();

    DB::table('roles')->where('id', $id)->update($updateData);

    if (isset($validated['permission_ids'])) {
        DB::table('role_permissions')->where('role_id', $id)->delete();
        foreach ($validated['permission_ids'] as $permId) {
            DB::table('role_permissions')->insert([
                'role_id' => $id,
                'permission_id' => $permId,
            ]);
        }
    }

    return response()->json(['success' => true, 'message' => 'Cập nhật vai trò thành công']);
});

Route::delete('/roles/{id}', function ($id) {
    $role = DB::table('roles')->where('id', $id)->first();
    if (!$role) {
        return response()->json(['success' => false, 'message' => 'Không tìm thấy vai trò'], 404);
    }
    if ($role->code === 'SUPER_ADMIN') {
        return response()->json(['success' => false, 'message' => 'Không được phép xóa vai trò Super Admin'], 400);
    }

    DB::table('role_permissions')->where('role_id', $id)->delete();
    DB::table('user_roles')->where('role_id', $id)->delete();
    DB::table('roles')->where('id', $id)->delete();

    return response()->json(['success' => true, 'message' => 'Đã xóa vai trò']);
});

Route::put('/roles/{id}/permissions', function (Request $request, $id) {
    $role = DB::table('roles')->where('id', $id)->first();
    if (!$role) {
        return response()->json(['success' => false, 'message' => 'Không tìm thấy vai trò'], 404);
    }

    $validated = $request->validate([
        'permission_ids' => 'required|array',
        'permission_ids.*' => 'exists:permissions,id'
    ]);

    DB::table('role_permissions')->where('role_id', $id)->delete();
    foreach ($validated['permission_ids'] as $permId) {
        DB::table('role_permissions')->insert([
            'role_id' => $id,
            'permission_id' => $permId,
        ]);
    }

    return response()->json(['success' => true, 'message' => 'Đã cập nhật danh sách quyền cho vai trò']);
});

// --- PERMISSIONS (QUYỀN HẠN) ---
Route::get('/permissions', function () {
    $perms = DB::table('permissions')->orderBy('module')->orderBy('code')->get();
    return response()->json(['success' => true, 'data' => $perms]);
});

Route::post('/permissions', function (Request $request) {
    $validated = $request->validate([
        'code' => 'required|string|max:100|unique:permissions,code',
        'module' => 'required|string|max:50',
        'description' => 'required|string|max:255',
    ]);

    $id = DB::table('permissions')->insertGetId([
        'code' => strtolower(trim($validated['code'])),
        'module' => strtolower(trim($validated['module'])),
        'description' => $validated['description'],
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return response()->json(['success' => true, 'message' => 'Tạo quyền hạn thành công', 'data' => ['id' => $id]], 201);
});

Route::put('/permissions/{id}', function (Request $request, $id) {
    $perm = DB::table('permissions')->where('id', $id)->first();
    if (!$perm) {
        return response()->json(['success' => false, 'message' => 'Không tìm thấy quyền hạn'], 404);
    }

    $validated = $request->validate([
        'code' => 'sometimes|required|string|max:100|unique:permissions,code,' . $id,
        'module' => 'sometimes|required|string|max:50',
        'description' => 'sometimes|required|string|max:255',
    ]);

    $updateData = [];
    if (isset($validated['code'])) $updateData['code'] = strtolower(trim($validated['code']));
    if (isset($validated['module'])) $updateData['module'] = strtolower(trim($validated['module']));
    if (isset($validated['description'])) $updateData['description'] = $validated['description'];
    $updateData['updated_at'] = now();

    DB::table('permissions')->where('id', $id)->update($updateData);

    return response()->json(['success' => true, 'message' => 'Cập nhật quyền hạn thành công']);
});

Route::delete('/permissions/{id}', function ($id) {
    $perm = DB::table('permissions')->where('id', $id)->first();
    if (!$perm) {
        return response()->json(['success' => false, 'message' => 'Không tìm thấy quyền hạn'], 404);
    }

    DB::table('role_permissions')->where('permission_id', $id)->delete();
    DB::table('permissions')->where('id', $id)->delete();

    return response()->json(['success' => true, 'message' => 'Đã xóa quyền hạn']);
});