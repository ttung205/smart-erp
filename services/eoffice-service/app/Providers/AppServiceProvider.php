<?php

namespace App\Providers;

use App\Models\LeaveRequest;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Alias lưu ở cột approvable_type, khớp với config('eoffice.approval_handlers')
        Relation::enforceMorphMap([
            'leave_request' => LeaveRequest::class,
        ]);
    }
}
