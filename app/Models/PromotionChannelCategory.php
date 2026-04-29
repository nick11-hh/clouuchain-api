<?php

namespace App\Models;

use App\Models\Traits\Basis;
use App\Models\Traits\HasValidateUnique;
use App\Services\WechatServices;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;

class PromotionChannelCategory extends Model
{
    use Basis, HasValidateUnique;

    protected $table = 'dsp_promotion_channel_categories';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];
}
