<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrderBoxesModel extends Model
{
    use HasFactory;

    protected $table = 'dsp_order_boxes';

    public function getVolumeAttribute()
    {
        if(!$this->length || !$this->width || !$this->height) return 0;
        return (float) sprintf('%.2f', $this->length * $this->width * $this->height / 1000000);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    /**
     * 订单发货快递公司
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function express()
    {
        return $this->hasOne(CompanyExpressModel::class, 'code', 'logistics_company');
    }
}
