<?php

namespace App\Models;

use App\Models\Views\VUserMember;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_users';

    protected $guarded = [];

    const STATUS_ENABLE = 1;
    const STATUS_DISABLE = 0;

    static public function init($params)
    {
        return [
            'username' => $params['username'],
            'email' => $params['email'] ?? '',
            'phone' => $params['phone'] ?? '',
            'phone_area_code' => $params['phone_area_code'] ?? '',
            'password' => bcrypt($params['password']),
            'status' => $params['status'] ?? self::STATUS_ENABLE,
            'custom_id' => $params['custom_id'],
            'group_id' => $params['group_id'] ?? 0,
            'name' => $params['name'] ?? '',
            'is_main' => $params['is_main'] ?? 0
        ];
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [
            'role' => 'client',
        ];
    }

    public function custom()
    {
        return $this->belongsTo(Custom::class, 'custom_id', 'id');
    }

    public function userGroup()
    {
        return $this->belongsTo(UserGroup::class, 'group_id', 'id');
    }

    public function getStatusNameAttribute()
    {
        return $this->statusList()[$this->status];
    }

    /** 用户状态
     * @return array
     */
    public static function statusList()
    {
       return [
           self::STATUS_DISABLE => __('禁用'),
           self::STATUS_ENABLE => __('启用'),
       ];
    }

    public function tags()
    {
        return $this->belongsToMany(
            UserTag::class,
            'dsp_user_to_tags',
            'user_id',
            'tag_id'
        );
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function orders()
    {
        return $this->hasMany(Order::class, 'customer_id', 'id');
    }

    /**
     * 用户组
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function group()
    {
        return $this->belongsTo(UserGroup::class, 'user_group_id', 'id');
    }

    /**
     * 邀请人
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function invitor()
    {
        return $this->hasOne(static::class, 'id', 'invite_id');
    }

    public function inviteUser()
    {
        return $this->hasMany(self::class, 'invite_id', 'id');
    }

    /**
     * 一个用户存在一个余额记录
     */
    public function balance()
    {
        return $this->hasOne(UserBalance::class, 'user_id', 'id')
            ->withDefault(['balance' => 0]);
    }

    /**
     * 会员信息
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function member()
    {
        //return $this->hasOne(UserMember::class, 'user_id', 'id');
        return $this->hasOne(VUserMember::class, 'user_id', 'id');
    }

    /**
     * 客服
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function customer()
    {
        return $this->belongsTo(Admin::class,'customer_id','id');
    }

    /**
     * 销售
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function sale()
    {
        return $this->belongsTo(Admin::class,'sale_id','id');
    }

    public function userMember()
    {
        return $this->hasOne(UserMember::class, 'user_id', 'id');
    }

    public function channel()
    {
        return  $this->belongsTo(PromotionChannel::class, 'invite_id', 'id');
    }

    /**
     * 获取推荐人的邀请码
     * @return string|null
     */
    public function getAdminInviteCodeAttribute()
    {
        if (isset($this->custom->id)) {
            $admin_id = AssignDataPermission::where('permission_id', $this->custom->id)->value('admin_id');
            $admin = Admin::find($admin_id);
            return $admin ? $admin->invite_code : null;
        }
        return null;
    }
}
