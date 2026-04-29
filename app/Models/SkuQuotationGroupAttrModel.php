<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class SkuQuotationGroupAttrModel extends Model
{
    use HasFactory;

    protected $table = 'dsp_sku_quotation_group_attr';


    public static function init($data)
    {
        return [
            'group_id' => self::generateGroupId(),
            'sku_id' => $data['sku_id'] ?? 0,
        ];
    }


    public static function generateGroupId()
    {
        return md5(uniqid() . rand(1000000, 9999999) . microtime(true));
    }

    public function goodsSKu(){

    }
}
