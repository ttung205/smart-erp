<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Xóa các tài khoản mẫu (email @erp.local) còn sót trên hệ thống đã chạy thật. Không đụng người dùng khác. */
class PurgeDemoUsersCommand extends Command
{
    protected $signature = 'identity:purge-demo-users
        {--force : Bắt buộc, xác nhận xóa thật}
        {--domain=erp.local : Đuôi email của tài khoản mẫu}';

    protected $description = 'Xóa tài khoản mẫu (@erp.local) khỏi hệ thống';

    public function handle(): int
    {
        $pattern = '%@'.ltrim((string) $this->option('domain'), '@');
        $users = DB::table('users')->where('email', 'like', $pattern)->get(['id', 'email']);
        if ($users->isEmpty()) {
            $this->info('Không có tài khoản mẫu nào.');

            return self::SUCCESS;
        }

        $this->table(['id', 'email'], $users->map(fn ($u) => [$u->id, $u->email])->all());
        if (! $this->option('force')) {
            $this->warn('Chạy lại với --force để xóa các tài khoản trên.');

            return self::FAILURE;
        }

        $ids = $users->pluck('id')->all();
        DB::transaction(function () use ($ids): void {
            DB::table('departments')->whereIn('manager_id', $ids)->update(['manager_id' => null]);
            DB::table('users')->whereIn('line_manager_id', $ids)->update(['line_manager_id' => null]);
            DB::table('login_otps')->whereIn('user_id', $ids)->delete();
            DB::table('user_roles')->whereIn('user_id', $ids)->delete();
            DB::table('users')->whereIn('id', $ids)->delete();
        });
        $this->info('Đã xóa '.count($ids).' tài khoản mẫu.');

        return self::SUCCESS;
    }
}
