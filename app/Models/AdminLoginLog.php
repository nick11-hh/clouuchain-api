<?php

namespace App\Models;

use App\Models\Traits\Basis;
use App\Models\Traits\HasValidateUnique;
use Illuminate\Http\Request;
class AdminLoginLog extends Model
{
    use Basis, HasValidateUnique;

    protected $table = 'dsp_admin_login_logs';

    protected $guarded = [];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'headers' => 'array',
    ];

    /**
     * @param Admin $admin
     * @param Request $request
     */
    public static function logging(Admin $admin, Request $request)
    {
        $ip = $request->getClientIp();
        // $location = IpLocation::getLocation($request->getClientIp());
        $headers = $request->headers->all();

        dispatch(function () use ($admin, $ip, $headers) {
            // if (isset($location['error'])) {
            //     $location = '';
            // } else {
            //     $location = sprintf(
            //         '%s%s%s %s',
            //         $location['country'],
            //         $location['province'],
            //         $location['city'],
            //         $location['area']
            //     );
            // }

            static::query()->create([
                'admin_id' => $admin->getKey(),
                'ip' => $ip,
                // 'ip_location' => $location,
                'headers' => $headers,
            ]);
        });
    }
}
