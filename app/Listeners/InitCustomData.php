<?php

namespace App\Listeners;

use App\Jobs\BrevoEmailJob;
use App\Models\CustomBalance;
use App\Models\Landlord\Tenant;
use App\Models\ThirdPartySystemConfigModel;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Artisan;

class InitCustomData
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {
        $user = $event->user;
        $this->createDefaultUserGroup($user);
        $this->createCustomBalance($user);
        // $this->initClientMenu($user);
        $this->createBrevoEmail($user);
    }

    /** 创建默认分组
     * @param $user
     * @return void
     */
    public function createDefaultUserGroup($user)
    {
        $userGroup = new UserGroup();
        $userGroup->custom_id = $user->custom_id;
        $userGroup->group_name = 'Administrator';
        $userGroup->description = 'Administrator';
        $userGroup->menu_limit = [];
        $userGroup->is_default = 1;
        $userGroup->save();
        $user->group_id = $userGroup->id;
        $user->save();
    }

    /** 创建用户钱包
     * @param $user
     * @return boolean
     */
    public function createCustomBalance($user)
    {
        $balance = CustomBalance::query()->where('custom_id', $user->custom_id)->first();
        if (!empty($balance)) return false;
        $balance = new CustomBalance();
        $balance->custom_id = $user->custom_id;
        $balance->balance = 0;
        $balance->commission = 0;
        return $balance->save();

    }

    public function createBrevoEmail($user)
    {
        //推送brevo邮件营销
        $brevo = ThirdPartySystemConfigModel::getBrevoConfig();
        if ($user->email && $brevo) {
            $data = [
                'type' => 'createContact',
                'custom_ids' => [$user->custom_id],
            ];

            dispatch(new BrevoEmailJob($data));
        }
    }

    public function initClientMenu($user)
    {
        Artisan::call('dsp:update-client-menu', ['customId' => $user->custom_id, '--tenant' => Tenant::current()->id]);
    }
}
