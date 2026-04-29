<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class ExpressOrderAddressModel extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_express_order_address';

    protected $guarded = [];

    public static function init($params): array
    {
        return [
            'express_order_id' => $params['express_order_id'] ?? 0, //物流订单ID
            'consignee_name' => $params['consignee_name'] ?? '', //收件人全称
            'consignee_company' => $params['consignee_company'] ?? '', //收件人公司
            'consignee_first_name' => $params['consignee_first_name'] ?? '', //收件人名
            'consignee_last_name' => $params['consignee_last_name'] ?? '', //收件人姓
            'consignee_address1' => $params['consignee_address1'] ?? '', //收件人地址1
            'consignee_address2' => $params['consignee_address2'] ?? '', //收件人地址2
            'consignee_phone' => $params['consignee_phone'] ?? '', //收件人电话
            'consignee_email' => $params['consignee_email'] ?? '', //收件人邮箱
            'consignee_city' => $params['consignee_city'] ?? '', //收件人城市
            'consignee_zip' => $params['consignee_zip'] ?? '', //收件人邮编
            'consignee_province' => $params['consignee_province'] ?? '', //收件人省/州
            'consignee_country' => $params['consignee_country'] ?? '', //收件人国家
            'consignee_country_code' => $params['consignee_country_code'] ?? '', //收件人国家代码
            'consignee_tax' => $params['consignee_tax'] ?? '', //收件人税号
        ];
    }

}
