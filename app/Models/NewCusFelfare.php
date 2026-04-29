<?php

namespace App\Models;

use App\Models\Traits\Basis;

/**
 * 新用户福利
 */
class NewCusFelfare extends Model
{
    use Basis;

    protected $table = 'company_new_cus_felfare';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];

    /**
     * 福利配置所属公司
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function company()
    {
        return $this->belongsTo(Admin::class, 'company_id', 'id');
    }

    public static function getFromCompanyID($companyId)
    {
        $config = self::where('company_id', $companyId)->first();
        if (!$config) {
            $config = new self();
            $config->new_cus_send = 0;
            $config->invitor_send = 0;
            $config->invited_send = 0;
        }
        return $config;
    }
}
