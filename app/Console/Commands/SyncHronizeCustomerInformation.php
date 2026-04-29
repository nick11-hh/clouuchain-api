<?php

namespace App\Console\Commands;

use App\Lib\Code;
use App\Models\Custom;
use App\Models\User;
use App\Models\WorldCountries;
use App\Services\ApiResponseService;
use App\Services\Client\FortySeasService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Spatie\Multitenancy\Commands\Concerns\TenantAware;
use WpOrg\Requests\Exception\Transport;

class SyncHronizeCustomerInformation extends Command
{

    use TenantAware;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:hronize-customer-information {--tenant=*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步客户信息';

    private $fortySeasService;

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        Log::info('开始同步客户信息...');
        $this->fortySeasService = new FortySeasService;
        $failedUserIds = [];
        User::whereNull('buyer_id')->whereNull('deleted_at')->chunk(100, function ($users) use (&$failedUserIds) {
            foreach ($users as $user) {
                try {
                    $result = $this->syncCustomerInformation($user);
                    if (!$result) {
                        $failedUserIds[] = $user->id;
                    }
                } catch (\Throwable $e) {
                    $failedUserIds[] = $user->id;
                }
            }
        });

        if (!empty($failedUserIds)) {
            Log::warning('客户信息同步失败，失败用户ID列表:', ['ids' => $failedUserIds]);
        }

        Log::info('客户信息同步完成!');
        return Command::SUCCESS;
    }

    /**
     * 同步客户信息
     * @param $user
     * @return bool
     */
    public function syncCustomerInformation($user): bool
    {
        // 创建40Seas客户
        return $this->fortySeasService->createBuyer($user);

    }
}
