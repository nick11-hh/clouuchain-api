<?php

namespace App\Jobs;

use App\Models\ShopModel;
use App\Services\PlatformShop\Platform\Shopify\RequestApi;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CreateShopifyWebhooks implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected ShopModel $shop;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($shop)
    {
        $this->shop = $shop;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $webhookEvents = config('shopify-app.webhooks');
        $logs = [];
        $address = getClientDomain() . '/api/client/shopify/webhook';

        $service = new RequestApi($this->shop);

        // 如果存在webhook先删除
        $webhookList = $service->getWebhooks();
        if (!empty($webhookList)) {
            foreach ($webhookList as $value) {
                $service->deleteWebhook($value['id']);
            }
        }

        foreach ($webhookEvents as $event) {
            try {
                $data = [
                    'webhook' => [
                        'topic'   => $event,
                        'address' => $address,
                        'format'  => 'json'
                    ]
                ];

                $res = $service->createWebhooks($data);
                if (empty($res['errors'])) {
                    $logs[$event] = '创建成功';
                } else {
                    $logs[$event] = $res['errors'];
                }
            }catch (\Exception $e) {
                $logs[$event] = ['创建失败' => $e->getMessage()];
            }
        }
        info("店铺{$this->shop->shop_url}创建webhook完成", $logs);

    }
}
