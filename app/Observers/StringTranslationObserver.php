<?php

namespace App\Observers;

use App\Models\SuperAdminStringTranslation;
use Illuminate\Support\Facades\Cache;

class StringTranslationObserver
{
    /**
     * Handle the SuperAdminStringTranslation "created" event.
     *
     * @param  \App\Models\SuperAdminStringTranslation  $superAdminStringTranslation
     * @return void
     */
    public function created(SuperAdminStringTranslation $superAdminStringTranslation)
    {
        $this->flushCache($superAdminStringTranslation);
    }

    /**
     * Handle the SuperAdminStringTranslation "updated" event.
     *
     * @param  \App\Models\SuperAdminStringTranslation  $superAdminStringTranslation
     * @return void
     */
    public function updated(SuperAdminStringTranslation $superAdminStringTranslation)
    {
        $this->flushCache($superAdminStringTranslation);
    }

    /**
     * Handle the SuperAdminStringTranslation "deleted" event.
     *
     * @param  \App\Models\SuperAdminStringTranslation  $superAdminStringTranslation
     * @return void
     */
    public function deleted(SuperAdminStringTranslation $superAdminStringTranslation)
    {
        $this->flushCache($superAdminStringTranslation);
    }

    /**
     * Handle the SuperAdminStringTranslation "restored" event.
     *
     * @param  \App\Models\SuperAdminStringTranslation  $superAdminStringTranslation
     * @return void
     */
    public function restored(SuperAdminStringTranslation $superAdminStringTranslation)
    {
        //
    }

    /**
     * Handle the SuperAdminStringTranslation "force deleted" event.
     *
     * @param  \App\Models\SuperAdminStringTranslation  $superAdminStringTranslation
     * @return void
     */
    public function forceDeleted(SuperAdminStringTranslation $superAdminStringTranslation)
    {
        //
    }

    /**
     * 重建缓存
     *
     * @param SuperAdminStringTranslation $model
     * @return void
     */
    protected function flushCache(SuperAdminStringTranslation $model)
    {
        $key = 'string-translation-data-'.$model->company_id;

        $model::cacheData($key);

        Cache::forever('StringTranslation_' . $model->company_id . '_flush', 1);
    }
}
