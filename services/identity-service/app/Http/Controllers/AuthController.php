<?php

namespace App\Http\Controllers;

use App\Auth\JwtIssuer;
use App\Auth\UserAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(private readonly JwtIssuer $issuer, private readonly UserAccess $access) {}

    public function health()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'service' => 'identity-service',
                'framework' => 'Laravel 13',
                'status' => 'UP',
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    public function login(Request $request)
    {
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

        $roles = $this->access->roles($user->id);
        $permissions = $this->access->permissions($user->id);
        $token = $this->issuer->issue((string) $user->id, [
            'name' => $user->name,
            'email' => $user->email,
            'department_id' => $user->department_id ? (int) $user->department_id : null,
            'roles' => $roles,
            'permissions' => $permissions,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => $this->issuer->ttl(),
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $roles,
                    'permissions' => $permissions,
                ],
            ],
        ]);
    }

    /** Thông tin mới nhất của người đang đăng nhập; token đã được middleware jwt kiểm tra. */
    public function me(Request $request)
    {
        $user = DB::table('users')
            ->leftJoin('departments', 'users.department_id', '=', 'departments.id')
            ->where('users.id', $request->attributes->get('actor')->id)
            ->where('users.is_active', 1)
            ->select('users.id', 'users.name', 'users.email', 'users.department_id', 'departments.name as department_name')
            ->first();

        if (!$user) {
            return response()->json(['success' => false, 'error' => ['code' => 'USER_NOT_FOUND']], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'sub' => (string)$user->id,
                'name' => $user->name,
                'email' => $user->email,
                'department_id' => $user->department_id,
                'department' => $user->department_name,
                'roles' => $this->access->roles($user->id),
                'permissions' => $this->access->permissions($user->id),
            ]
        ]);
    }
}
