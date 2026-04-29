<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_suppliers';

    protected $guarded = [];

    CONST STATUS_ENABLE = 1;
    CONST STATUS_DISABLE = 0;

    CONST TYPE_ALIBABA = 1;
    CONST TYPE_OFFLINE = 2;
    CONST TYPE_FACTORY = 3;
    CONST TYPE_TWO = 4;
    CONST TYPE_THREE = 5;

    CONST OVERALL_RATING_ONE = 0;
    CONST OVERALL_RATING_TWO = 1;
    CONST OVERALL_RATING_THREE = 2;

    CONST COOPERATION_LEVEL_ONE = 0;
    CONST COOPERATION_LEVEL_TWO = 1;
    CONST COOPERATION_LEVEL_THREE = 2;

    CONST FACTORY_SCALE_ONE = 0;
    CONST FACTORY_SCALE_TWO = 1;
    CONST FACTORY_SCALE_THREE = 2;

    CONST PAYMENT_NOW = 1;
    CONST PAYMENT_MONTH = 2;

    public static function statusList()
    {
        return [
            self::STATUS_ENABLE => __('启用'),
            self::STATUS_DISABLE => __('禁用'),
        ];
    }

    public static function typeList()
    {
        return [
            self::TYPE_ALIBABA => __('1688'),
            self::TYPE_OFFLINE => __('线下'),
            self::TYPE_FACTORY => __('厂商'),
            self::TYPE_TWO => __('贸易商'),
            self::TYPE_THREE => __('工贸一体'),
        ];
    }

    public static function cooperationLevelList()
    {
        return [
            self::COOPERATION_LEVEL_ONE => __('满意'),
            self::COOPERATION_LEVEL_TWO => __('一般'),
            self::COOPERATION_LEVEL_THREE => __('差'),
        ];
    }

    public static function overallRatingList()
    {
        return [
            self::OVERALL_RATING_ONE => __('满意'),
            self::OVERALL_RATING_TWO => __('一般'),
            self::OVERALL_RATING_THREE => __('差'),
        ];
    }

    public static function factoryScaleList()
    {
        return [
            self::FACTORY_SCALE_ONE => __('小于50人'),
            self::FACTORY_SCALE_TWO => __('50-100人'),
            self::FACTORY_SCALE_THREE => __('大于100人'),
        ];
    }

    public static function paymentMethodList()
    {
        return [
            self::PAYMENT_NOW => __('现结'),
            self::PAYMENT_MONTH => __('月结'),
        ];
    }

    public function goodsSupplier()
    {
        return $this->hasMany(GoodsSupplier::class, 'supplier_id', 'id');
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class, 'developer', 'id');
    }

    public function getStatusNameAttribute()
    {
        return self::statusList()[$this->status] ?? '-';
    }

    public function getTypeNameAttribute()
    {
        return self::typeList()[$this->type] ?? '-';
    }

    public function getCooperationLevelNameAttribute()
    {
        return self::cooperationLevelList()[$this->cooperation_level] ?? '-';
    }

    public function getOverallRatingNameAttribute()
    {
        return self::overallRatingList()[$this->overall_rating] ?? '-';
    }

    public function getFactoryScaleNameAttribute()
    {
        return self::factoryScaleList()[$this->factory_scale] ?? '-';
    }

    public function getPaymentMethodNameAttribute()
    {
        return self::paymentMethodList()[$this->payment_method] ?? '-';
    }


    public static function init($params)
    {
        return [
            'supplier_name' => $params['supplier_name'] ?? '',
            'supplier_code' => $params['supplier_code'] ?? '',
            'type' => $params['type'] ?? '',
            'status' => $params['status'] ?? 1,
            'supplier_url' => $params['supplier_url'] ?? '',
            'remark' => $params['remark'] ?? '',
            'contact_name' => $params['contact_name'] ?? '',
            'contact_phone' => $params['contact_phone'] ?? '',
            'contact_email' => $params['contact_email'] ?? '',
            'contact_wechat' => $params['contact_wechat'] ?? '',
            'contact_address' => $params['contact_address'] ?? '',
            'payment_method' => $params['payment_method'] ?? 1,
            'payment_name' => $params['payment_name'] ?? '',
            'payment_bank' => $params['payment_bank'] ?? '',
            'payment_account' => $params['payment_account'] ?? '',
            'supplier_qualification' => $params['supplier_qualification'] ?? '',
            'main_category' => $params['main_category'] ?? '',
            'certifications' => $params['certifications'] ?? '',
            'shipping_address' => $params['shipping_address'] ?? '',
            'payment_terms' => $params['payment_terms'] ?? '',
            'cooperation_level' => $params['cooperation_level'] ?? 0,
            'service_and_after_sales' => $params['service_and_after_sales'] ?? '',
            'tax_point' => $params['tax_point'] ?? 0,
            'face_value' => $params['face_value'] ?? 0,
            'overall_rating' => $params['overall_rating'] ?? 0,
            'person_in_charge' => $params['person_in_charge'] ?? '',
            'person_in_charge_role' => $params['person_in_charge_role'] ?? '',
            'contact_info' => $params['contact_info'] ?? '',
            'factory_images' => $params['factory_images'] ?? '',
            'factory_scale' => $params['factory_scale'] ?? 0,
            'developer' => $params['developer'] ?? '',
        ];
    }

    public static function generateSupplierCode()
    {
        return 'DSP' . date('Ymd') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }
}
