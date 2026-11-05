<?php

namespace App\Http\Controllers;

use App\Auth\JwtIssuer;
use App\Auth\UserAccess;
use App\Services\LoginOtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;
use Throwable;

class AuthController extends Controller
{
    /** Băm hợp lệ nhưng không khớp mật khẩu nào, để thời gian phản hồi không lộ mã nhân viên nào tồn tại. */
    private const DUMMY_HASH = '$2y$12$US0ipQNOqpMlAaEJ6crxduWdoshWjkCvlLK./E1ywlo1vbqJX7EN6';

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
        summary: 'Đăng nhập bằng mã nhân viên và mật khẩu',
        description: 'Nhận employee_code (ví dụ AC-2005). Vẫn chấp nhận email thay cho employee_code để các tích hợp cũ không gãy.',
        tags: ['Xác thực'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['employee_code', 'password'],
                properties: [
                    new OA\Property(property: 'employee_code', type: 'string', example: 'AC-2005'),
                    new OA\Property(property: 'password', type: 'string', format: 'password'),
                ]
            )
        ),
        security: [],
        responses: [
            new OA\Response(response: 200, description: 'Đăng nhập thành công, trả về token và thông tin người dùng'),
            new OA\Response(response: 401, description: 'INVALID_CREDENTIALS: sai mã nhân viên hoặc mật khẩu'),
            new OA\Response(response: 422, description: 'VALIDATION_FAILED: thiếu mã nhân viên hoặc mật khẩu'),
        ]
    )]
    public function login(Request $request)
    {
        $identifier = trim((string) ($request->input('employee_code') ?? $request->input('email', '')));
        $password = (string) $request->input('password', '');

        if ($identifier === '' || $password === '') {
            return $this->failure('VALIDATION_FAILED', 'Mã nhân viên và mật khẩu không được để trống.', 422);
        }

        $user = $this->findActiveUser($identifier);
        $valid = Hash::check($password, $user->password_hash ?? self::DUMMY_HASH);
        if (! $user || ! $valid) {
            return $this->failure('INVALID_CREDENTIALS', 'Mã nhân viên hoặc mật khẩu không chính xác.', 401);
        }

        return $this->session($user);
    }

    #[OA\Post(
        path: '/login/otp/request',
        summary: 'Gửi mã OTP đăng nhập về email nhân viên',
        description: 'Luôn trả 200 với cùng một thông điệp dù mã nhân viên có tồn tại hay không, để không lộ danh sách nhân viên.',
        tags: ['Xác thực'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['employee_code'],
                properties: [new OA\Property(property: 'employee_code', type: 'string', example: 'AC-2005')]
            )
        ),
        security: [],
        responses: [
            new OA\Response(response: 200, description: 'Đã xử lý yêu cầu'),
            new OA\Response(response: 422, description: 'VALIDATION_FAILED: thiếu mã nhân viên'),
            new OA\Response(response: 503, description: 'DEPENDENCY_UNAVAILABLE: không gửi được email'),
        ]
    )]
    public function requestOtp(Request $request, LoginOtpService $otps)
    {
        $code = trim((string) $request->input('employee_code', ''));
        if ($code === '') {
            return $this->failure('VALIDATION_FAILED', 'Vui lòng nhập mã nhân viên.', 422);
        }

        $user = $this->findActiveUser($code, byEmployeeCodeOnly: true);
        if ($user) {
            try {
                $otps->issue($user);
            } catch (Throwable $exception) {
                Log::error('Không gửi được OTP đăng nhập', [
                    'service' => 'identity-service',
                    'user_id' => $user->id,
                    'correlation_id' => $request->header('X-Correlation-ID'),
                    'error' => $exception->getMessage(),
                ]);

                return $this->failure('DEPENDENCY_UNAVAILABLE', 'Chưa gửi được email xác thực. Vui lòng thử lại sau ít phút.', 503);
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'message' => 'Nếu mã nhân viên hợp lệ, mã OTP đã được gửi tới email đăng ký của nhân viên.',
                'expires_in' => LoginOtpService::TTL_MINUTES * 60,
                'resend_after' => LoginOtpService::RESEND_COOLDOWN_SECONDS,
            ],
        ]);
    }

    #[OA\Post(
        path: '/login/otp/verify',
        summary: 'Đăng nhập bằng mã nhân viên và OTP',
        tags: ['Xác thực'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['employee_code', 'otp'],
                properties: [
                    new OA\Property(property: 'employee_code', type: 'string', example: 'AC-2005'),
                    new OA\Property(property: 'otp', type: 'string', example: '123456'),
                ]
            )
        ),
        security: [],
        responses: [
            new OA\Response(response: 200, description: 'Đăng nhập thành công, trả về token và thông tin người dùng'),
            new OA\Response(response: 401, description: 'INVALID_OTP: OTP sai, hết hạn hoặc đã dùng'),
            new OA\Response(response: 422, description: 'VALIDATION_FAILED: thiếu mã nhân viên hoặc OTP sai định dạng'),
        ]
    )]
    public function verifyOtp(Request $request, LoginOtpService $otps)
    {
        $code = trim((string) $request->input('employee_code', ''));
        $otp = trim((string) $request->input('otp', ''));
        if ($code === '' || ! preg_match('/^\d{6}$/', $otp)) {
            return $this->failure('VALIDATION_FAILED', 'Vui lòng nhập mã nhân viên và mã OTP gồm 6 chữ số.', 422);
        }

        $user = $this->findActiveUser($code, byEmployeeCodeOnly: true);
        if (! $user || ! $otps->verify((int) $user->id, $otp)) {
            return $this->failure('INVALID_OTP', 'Mã OTP không đúng hoặc đã hết hạn.', 401);
        }

        return $this->session($user);
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
            ->select('users.id', 'users.employee_code', 'users.name', 'users.email', 'users.department_id', 'departments.name as department_name')
            ->first();

        if (! $user) {
            return response()->json(['success' => false, 'error' => ['code' => 'USER_NOT_FOUND']], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'sub' => (string) $user->id,
                'employee_code' => $user->employee_code,
                'name' => $user->name,
                'email' => $user->email,
                'department_id' => $user->department_id,
                'department' => $user->department_name,
                'roles' => $this->access->roles($user->id),
                'permissions' => $this->access->permissions($user->id),
            ],
        ]);
    }

    private function findActiveUser(string $identifier, bool $byEmployeeCodeOnly = false): ?object
    {
        $query = DB::table('users')->where('is_active', 1);
        if ($byEmployeeCodeOnly || ! str_contains($identifier, '@')) {
            $query->where('employee_code', strtoupper($identifier));
        } else {
            $query->where('email', $identifier);
        }

        return $query->first(['id', 'employee_code', 'name', 'email', 'password_hash', 'department_id']);
    }

    private function session(object $user)
    {
        $roles = $this->access->roles($user->id);
        $permissions = $this->access->permissions($user->id);
        $token = $this->issuer->issue((string) $user->id, [
            'name' => $user->name,
            'email' => $user->email,
            'employee_code' => $user->employee_code,
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
                    'employee_code' => $user->employee_code,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $roles,
                    'permissions' => $permissions,
                ],
            ],
        ]);
    }

    private function failure(string $code, string $message, int $status)
    {
        return response()->json(['success' => false, 'error' => ['code' => $code, 'message' => $message]], $status);
    }
}
