<?php

namespace App\Jobs;

use App\Models\Custom;
use App\Models\ThirdPartySystemConfigModel;
use App\Services\ThirdPartyApi\Brevo\BrevoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BrevoEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $params;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($params = [])
    {
        $this->params = $params;
    }

    /**
     *
     */
    public function handle()
    {
        $brevo = ThirdPartySystemConfigModel::getBrevoConfig();
        if (empty($brevo)) {
            return true;
        }
        $brevoService = new BrevoService($brevo);

        $type = $this->params['type'] ?? '';
        if ($type === 'createContact') {
            $customIds = $this->params['custom_ids'] ?? [];
            $customers = Custom::query()->whereIn('id', $customIds)->get();

            foreach ($customers as $customer) {
                $brevoService->createOrUpdateContact($customer);
            }
        }

        return true;
    }
}
