<?php

namespace App\Listeners;

use App\Events\ShopCreateEvent;
use App\Models\ShopSetting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class CreateShopSettingNotify
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {

    }

    /**
     * Handle the event.
     *
     * @param ShopCreateEvent $event
     * @return void
     */
    public function handle(ShopCreateEvent $event)
    {
        ShopSetting::query()->create([
            'shop_id' => $event->shop->id,
            'send_customer_email' => 1,
            'auto_shop_delivery' => 1,
        ]);
    }
}
