<?php

namespace App\Models;

use App\Models\Traits\Basis;
use App\Services\WechatServices;
use Exception;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;

class PromotionChannel extends Model
{
    use Basis, SoftDeletes;

    protected $table = 'dsp_promotion_channel';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];

    public function category()
    {
        return $this->belongsTo(PromotionChannelCategory::class, 'category_id', 'id');
    }

    /**
     * 一个渠道可能邀请过 n 个人
     */
    public function invitedUser()
    {
        return $this->morphToMany(User::class, 'invite');
    }

    /**
     * 更新 app 的 code
     * @param $companyID
     * @param bool $force
     * @return bool
     * @throws Exception
     */
    public function updateAppCode($companyID, $force = false)
    {
        $files = Storage::disk('admin_public')->files('channels');
        //如果存在,则不更新 直接返回 true -- 并且不是强制更新
        if (!$force) {
            foreach ($files as $key => $file) {
                if (strstr($file, $this->id . '.jpg')) {
                    return true;
                }
            }
        }

        $imageData = (new WechatServices($companyID))->generateAppCode($this->id);

        // $image = Image::make($imageData->getBody()->getContents());

        $randomStr = Str::random(5);

        Storage::disk()->put('admin/channels/' . $randomStr . $this->id . '.jpg', $imageData);

        return $this->update(
            [
                'app_code' => str_replace(
                    config('app.url'),
                    '',
                    Storage::disk('admin_public')->url('channels/' . $randomStr . $this->id . '.jpg')
                ),
            ]
        );
    }
}
