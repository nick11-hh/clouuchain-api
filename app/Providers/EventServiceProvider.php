<?php

namespace App\Providers;

use App\Events\AfterUpdateLogisticsSn;
use App\Events\ClientCustomRegister;
use App\Events\TrackingSubscribe;
use App\Listeners\InitCustomData;
use App\Listeners\Tracking\Subscribe;
use App\Listeners\UpdateOrderLabelList;
use App\Events\UserInfoInjected;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        // 客户端用户注册成功
        ClientCustomRegister::class => [
            InitCustomData::class  // 创建默认分组
        ],
        //订单面单获取
        AfterUpdateLogisticsSn::class => [
            UpdateOrderLabelList::class,
            \App\Listeners\SendOrderTrackNumberUpdatedNotify::class
        ],
        //物流订阅
        TrackingSubscribe::class => [
            Subscribe::class
        ],
        \App\Events\UpdatedPackageWarning::class => [
            \App\Listeners\SendPackageWarningNotify::class,
        ],
        \App\Events\UserInfoInjected::class => [
            \App\Listeners\HandleUserInfoInjection::class,
        ]
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     *
     * @return bool
     */
    public function shouldDiscoverEvents()
    {
        return false;
    }
}
