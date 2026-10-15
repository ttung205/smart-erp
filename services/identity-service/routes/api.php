<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\PermissionController;

// 1. Health check & Authentication
Route::get('/health', [AuthController::class, 'health']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/me', [AuthController::class, 'me']);

// 2. Departments (Phòng ban)
Route::apiResource('departments', DepartmentController::class);

// 3. Users (Nhân viên)
Route::get('/users/by-permission', [UserController::class, 'byPermission']);
Route::apiResource('users', UserController::class);

// 4. Roles (Vai trò)
Route::put('/roles/{id}/permissions', [RoleController::class, 'updatePermissions']);
Route::apiResource('roles', RoleController::class);

// 5. Permissions (Quyền hạn)
Route::apiResource('permissions', PermissionController::class);