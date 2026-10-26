<?php

namespace App\Providers;

use App\Auth\JwtVerifier;
use App\Models\AttendanceRequest;
use App\Models\LeaveRequest;
use App\Models\OfficeRequest;
use App\Models\PayrollPeriod;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(JwtVerifier::class, fn () => new JwtVerifier((string) config('services.identity.jwt_secret')));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Alias lưu ở cột approvable_type, khớp với config('eoffice.approval_handlers')
        Relation::enforceMorphMap([
            'leave_request' => LeaveRequest::class,
            'office_request' => OfficeRequest::class,
            'attendance_request' => AttendanceRequest::class,
            'payroll_period' => PayrollPeriod::class,
        ]);
    }
}
