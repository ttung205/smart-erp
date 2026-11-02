<?php

namespace App\Http\Controllers;

use App\Auth\JwtIssuer;
use App\Auth\UserAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    public function __construct(private readonly JwtIssuer $issuer, private readonly UserAccess $access) {}

    #[OA\Get(
        path: '/health',
        summary: 'Kiểm tra trạng thái dịch vụ',
        tags: ['Hệ thống'],
        security: [],
        responses: [
            new OA\Response(response: 200, description: 'Dịch vụ đang hoạt động'),
        ]
    )]
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

    #[OA\Post(
        path: '/login',
        summary: 'Đăng nhập và nhận JWT',
        tags: ['Xác thực'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'admin@erp.local'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password123'),
                ]
            )
        ),
        security: [],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Đăng nhập thành công, trả về token và thông tin người dùng',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'token', type: 'string'),
                                new OA\Property(property: 'token_type', type: 'string', example: 'Bearer'),
                                new OA\Property(property: 'expires_in', type: 'integer', example: 3600),
                                new OA\Property(property: 'user', type: 'object'),
                            ],
                            type: 'object'
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'INVALID_CREDENTIALS: sai email hoặc mật khẩu'),
            new OA\Response(response: 422, description: 'VALIDATION_FAILED: thiếu email hoặc mật khẩu'),
        ]
    )]
    public function login(Request $request)
    {
        $email = trim($request->input('email', ''));
        $password = $request->input('password', '');

        if (empty($email) || empty($password)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'VALIDATION_FAILED', 'message' => 'Email và mật khẩu không được để trống.'],
            ], 422);
        }

        $user = DB::table('users')->where('email', $email)->where('is_active', 1)->first();

        if (! $user || ! Hash::check($password, $user->password_hash)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'INVALID_CREDENTIALS', 'message' => 'Email hoặc mật khẩu không chính xác.'],
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
    #[OA\Get(
        path: '/me',
        summary: 'Thông tin người đang đăng nhập',
        tags: ['Xác thực'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Thông tin người dùng, phòng ban, vai trò và quyền'),
            new OA\Response(response: 401, description: 'AUTH_TOKEN_MISSING, AUTH_TOKEN_INVALID hoặc AUTH_TOKEN_EXPIRED: thiếu, sai hoặc hết hạn token'),
            new OA\Response(response: 404, description: 'USER_NOT_FOUND: tài khoản không tồn tại hoặc đã bị khóa'),
        ]
    )]
    public function me(Request $request)
    {
        $user = DB::table('users')
            ->leftJoin('departments', 'users.department_id', '=', 'departments.id')
            ->where('users.id', $request->attributes->get('actor')->id)
            ->where('users.is_active', 1)
            ->select('users.id', 'users.name', 'users.email', 'users.department_id', 'departments.name as department_name')
            ->first();

        if (! $user) {
            return response()->json(['success' => false, 'error' => ['code' => 'USER_NOT_FOUND']], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'sub' => (string) $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'department_id' => $user->department_id,
                'department' => $user->department_name,
                'roles' => $this->access->roles($user->id),
                'permissions' => $this->access->permissions($user->id),
            ],
        ]);
    }
}
