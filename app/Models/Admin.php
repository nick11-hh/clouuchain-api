<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class Admin extends Authenticatable implements JWTSubject
{
    use SoftDeletes;

    public const DATA_PERMISSION_ALL = 0;
    public const DATA_PERMISSION_PART = 1;

    const ENABLE_LOGIN = 1;
    const ENABLE_FORBID_LOGIN = 0;

    protected $table = 'dsp_admins';

    protected $guarded = [];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'forbid_login' => 'bool',
    ];

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [
            'role' => 'admin',
        ];
    }


    /**
     * Get all permissions of user.
     *
     * @return mixed
     */
    public function allPermissions()
    {
        return $this->group()->with('permissions')->get()->pluck('permissions')->flatten();
    }

    /**
     * Get all permissions of user.
     *
     * @return mixed
     */
    public function allRouteMenus()
    {
        return $this->group()->with('routeMenus')->get()->pluck('routeMenus')->flatten();
    }


    /**
     * 生成统一的用户名
     * @param string $phone
     * @return string
     */
    public static function makeCommonUserName(string $phone): string
    {
        return 'DSP' . substr($phone, 4, 7) . '_' . mt_rand(10, 99);
    }

    public static function init($data, $operate = 1)
    {
        $returnData = [
            'name'     => $data['name'],
            'username' => $data['username'],
        ];

        if ($operate == 2) {
            if ($data['phone'] ?? '') {
                $returnData['phone'] = $data['phone'];
            }
            if ($data['email'] ?? '') {
                $returnData['email'] = $data['email'];
            }
            if (is_numeric($data['group_id'] ?? '')) {
                $returnData['group_id'] = $data['group_id'];
            }
            if ($data['password'] ?? '') {
                $returnData['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
            }
            if ($data['phone_area_code'] ?? '') {
                $returnData['phone_area_code'] = $data['phone_area_code'] ?? '';
            }
            if (is_numeric($data['check_auth'] ?? '')) {
                $returnData['check_auth'] = $data['check_auth'];
            }
        } else {
            $returnData = array_merge($returnData, [
                'phone'    => $data['phone'] ?? '',
                'email'    => $data['email'] ?? '',
                'group_id' => $data['group_id'] ?? 0,
                'password' => isset($data['password']) ?
                    password_hash($data['password'], PASSWORD_DEFAULT)
                    : password_hash('12345678', PASSWORD_DEFAULT),
                'phone_area_code' => $data['phone_area_code'] ?? '',
                'check_auth' => $data['check_auth'] ?? 0,
            ]);
        }

        return $returnData;
    }

    public function group()
    {
        return $this->belongsTo(AdminGroupModel::class, 'group_id', 'id');
    }

    public function adminDepartment()
    {
        return $this->belongsTo(AdminDepartment::class, 'id', 'admin_id');
    }

    public function assignDataPermissions()
    {
        return $this->hasMany(AssignDataPermission::class, 'admin_id', 'id');
    }

    public function dataRangeGroups()
    {
        return $this->hasMany(DataRangeGroupAdmin::class, 'admin_id', 'id');
    }

    public function dataRangeGroup()
    {
        return $this->hasOne(DataRangeGroupAdmin::class, 'admin_id', 'id')->latest();
    }

}
