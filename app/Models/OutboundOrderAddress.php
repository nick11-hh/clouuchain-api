<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OutboundOrderAddress extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_outbound_order_addresses';

    protected $guarded = [];

    protected $casts = [];

    public static function init($outboundId, $params)
    {
        return [
            'outbound_id' => $outboundId,
            'name' => $params['name'],
            'first_name' => $params['first_name'] ?? '',
            'last_name' => $params['last_name'] ?? '',
            'address' => $params['address'] ?? '',
            'address2' => $params['address2'] ?? '',
            'phone' => $params['phone'] ?? '',
            'city' => $params['city'] ?? '',
            'zip' => $params['zip'],
            'province' => $params['province'] ?? '',
            'country' => $params['country'] ?? '',
            'company' => $params['company'] ?? '',
            'latitude' => $params['latitude'] ?? '',
            'longitude' => $params['longitude'] ?? '',
            'tax' => $params['tax'] ?? '',
        ];
    }
}
