<?php

namespace App\Models;

use App\Models\Traits\Basis;
use App\Models\Traits\HasValidateUnique;

class DataPermission extends Model
{
    use Basis, HasValidateUnique;

    //group-客户组invitor-所属代理customer-所属客服sale-所属销售
    const FIELD_GROUP = 'group';
    const FIELD_INVITOR = 'invitor';
    const FIELD_CUSTOMER = 'customer';
    const FIELD_SALE = 'sale';

    protected $table = 'dsp_data_permissions';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];

    public function adminUsers()
    {
        return $this->hasMany(DPAdminUser::class, 'dp_id', 'id');
    }

}
