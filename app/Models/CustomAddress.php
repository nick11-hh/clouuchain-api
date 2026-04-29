<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 客户地址表
 * Class CustomAddress
 * @package App\Models
 * @author DonnyLiu <2365057581@qq.com>
 * @date 2024/8/29 19:33
 */
class CustomAddress extends Model
{
    use HasFactory,
        SoftDeletes;

    protected $table = 'dsp_custom_address';

    protected $guarded = [];

    protected $casts = [];

    protected $hidden = [];

    /**
     * 关联客户表
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/8/29 19:34
     */
    public function custom()
    {
        return $this->belongsTo(Custom::class, 'custom_id', 'id');
    }

    /**
     * 国家
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/8/29 20:14
     */
    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id', 'id');
    }


}
