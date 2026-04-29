<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class PackageAddress extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_package_address';

    protected $guarded = [];

    public function package()
    {
        return $this->belongsTo(Package::class, 'package_id', 'id');
    }

    public function mergeAddress()
    {
        return $this->hasMany(PackageAddress::class, 'unique_key', 'unique_key');
    }

    public static function init($packageId, $params, $create = 1)
    {
        $data = [
            'name'          => $params['name'],
            'company'       => $params['company'],
            'first_name'    => $params['first_name'],
            'last_name'     => $params['last_name'],
            'address1'      => $params['address1'],
            'address2'      => $params['address2'],
            'phone'         => $params['phone'],
            'email'         => $params['email'],
            'city'          => $params['city'],
            'zip'           => $params['zip'],
            'province'      => $params['province'],
            'province_code' => $params['province_code'],
            'country'       => $params['country'],
            'country_code'  => $params['country_code'],
            'tax'           => $params['tax'],
            'unique_key'    => self::generateUniqueKey($params)
        ];
        if ($create) {
            $data['package_id'] = $packageId;
        }
        return $data;
    }

    public static function generateUniqueKey($params)
    {
        $address = "{$params['name']}-{$params['phone']}-{$params['email']}-{$params['country_code']}-{$params['province']}-{$params['city']}-{$params['address1']}-{$params['address2']}-{$params['zip']}";
        return md5($address);
    }

}
