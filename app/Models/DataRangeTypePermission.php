<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DataRangeTypePermission extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_data_range_type_permissions';

    protected $guarded = [];

    protected $casts = [
        'range_value' => 'array',
    ];

    const DATA_TYPE_CUSTOMER = 'customer';
    const DATA_TYPE_LIST = [
      self::DATA_TYPE_CUSTOMER => '客户数据'
    ];

    const RANGE_TYPE_MYSELF = 'myself';
    const RANGE_TYPE_PART = 'part';
    const RANGE_TYPE_ALL = 'all';

    const RANGE_TYPE_LIST = [
        self::RANGE_TYPE_MYSELF => 'myself',
        self::RANGE_TYPE_PART => 'part',
        self::RANGE_TYPE_ALL => 'all',
    ];

    public function getDataTypeNameAttribute()
    {
        return self::DATA_TYPE_LIST[$this->data_type] ?? '-';
    }

    public function getRangeTypeNameAttribute()
    {
        return self::RANGE_TYPE_LIST[$this->range_type] ?? '-';
    }

}
