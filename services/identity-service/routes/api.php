<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// 1. Health check & Authentication (công khai)
Route::get('/health', [AuthController::class, 'health']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/company/logo', [CompanyController::class, 'logo']);
Route::get('/me', [AuthController::class, 'me'])->middleware('jwt');

// 2. Đọc dữ liệu tổ chức: người dùng đã đăng nhập hoặc service nội bộ
Route::middleware('user_or_service')->group(function () {
    Route::get('/company', [CompanyController::class, 'show']);
    Route::get('/users/by-permission', [UserController::class, 'byPermission']);
    Route::apiResource('departments', DepartmentController::class)->only(['index', 'show']);
    Route::apiResource('users', UserController::class)->only(['index', 'show']);
    Route::apiResource('roles', RoleController::class)->only(['index', 'show']);
    Route::apiResource('permissions', PermissionController::class)->only(['index', 'show']);
});

// 3. Quản trị: chỉ người dùng có quyền tương ứng
Route::middleware('jwt')->group(function () {
    Route::put('/company', [CompanyController::class, 'update'])->middleware('permission:company.manage');
    Route::post('/company/logo', [CompanyController::class, 'uploadLogo'])->middleware('permission:company.manage');
    Route::delete('/company/logo', [CompanyController::class, 'deleteLogo'])->middleware('permission:company.manage');
    Route::apiResource('departments', DepartmentController::class)->except(['index', 'show'])
        ->middleware('permission:department.manage');
    Route::apiResource('users', UserController::class)->except(['index', 'show'])
        ->middleware('permission:employee.manage,users.manage');
    Route::put('/roles/{id}/permissions', [RoleController::class, 'updatePermissions'])
        ->middleware('permission:role.manage');
    Route::apiResource('roles', RoleController::class)->except(['index', 'show'])
        ->middleware('permission:role.manage');
    Route::apiResource('permissions', PermissionController::class)->except(['index', 'show'])
        ->middleware('permission:permission.manage');
});
