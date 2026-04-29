<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShopTax extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_shop_tax';

    protected $guarded = [];

    const REGION_EUROPEAN = 1;
    const REGION_ENGLAND = 2;
    const REGION_OTHER = 3;

    const REGION_LIST = [
      self::REGION_EUROPEAN => '欧盟税号',
      self::REGION_ENGLAND => '英国税号',
      self::REGION_OTHER => '寄件人税号',
    ];

    const TYPE_IOSS = 'ioss';
    const TYPE_EORI = 'eori';
    const TYPE_PAID_BY_AGENT = 'paid_by_agent';
    const TYPE_ENGLAND = 'england';

    const TYPE_LIST = [
        self::TYPE_IOSS => 'IOSS税号',
        self::TYPE_EORI => '欧盟EORI税号',
        self::TYPE_PAID_BY_AGENT => '物流商代付',
        self::TYPE_ENGLAND => '英国税号',
    ];

    public function shop()
    {
        return $this->belongsTo(ShopModel::class, 'shop_id', 'id');
    }

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_code', 'code');
    }

    public function getTaxRegionNameAttribute()
    {
        return self::REGION_LIST[$this->tax_region] ?? '-';
    }

    public function getTaxTypeNameAttribute()
    {
        return self::TYPE_LIST[$this->tax_type] ?? '-';
    }

}
