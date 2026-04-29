<?php

namespace App\Console\Commands;

use App\Models\Admin;
use App\Models\User;
use App\Services\Client\FortySeasService;
use App\Services\Wechat\RequestApi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Spatie\Multitenancy\Commands\Concerns\TenantAware;

class SyncWecomInfo extends Command
{
    use TenantAware;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:wecom-users {--tenant=*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步企业微信人员信息';

    private $wecomApi;

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        Log::info('开始同步企业微信人员信息...');
        $failedUserIds = [];
        $this->wecomApi = new RequestApi;
        Admin::whereNull('wecom_user_id')->whereNull('deleted_at')->chunk(100, function ($users) use (&$failedUserIds) {
            foreach ($users as $user) {
                if (empty($user->phone)) {
                    continue;
                }
                //正则校验手机号
                if (!preg_match('/^1[3456789]\d{9}$/', $user->phone)) {
                    continue;
                }
                try {
                    $wecomUserid = $this->wecomApi->getUserIdByMobile($user->phone);
                    if (empty($wecomUserid)) {
                        $failedUserIds[] = $user->id;
                        continue;
                    }
                    Admin::where('id', $user->id)->update([
                        'wecom_user_id' => $wecomUserid,
                    ]);
                } catch (\Throwable $e) {
                    $failedUserIds[] = $user->id;
                }
            }
        });

        if (!empty($failedUserIds)) {
            Log::warning('企业微信人员信息同步失败，失败管理员ID列表:', ['ids' => $failedUserIds]);
        }
        Log::info('同步企业微信人员信息完成!');
        return Command::SUCCESS;
    }
}
