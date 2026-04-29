<?php

namespace App\Models;

use App\Models\Traits\Basis;

class WorkOrderDiscuss extends Model
{
    use Basis;

    protected $table = 'dsp_work_order_discuss';

    protected $guarded = [];

    protected $hidden = [];

    protected $appends = [];

    protected $fillable = [];

    protected $casts = [
        'file' => 'array',
    ];

    /**
     * 关联客户
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function evaluator()
    {
        return $this->belongsTo(Admin::class, 'evaluator_id', 'id');
    }

    public static function init($mainId, $childId, $data)
    {
        return [
            'main_id' => $mainId,
            'child_id' => $childId,
            'evaluator_id' => auth()->id(),
            'content' => $data['content'],
            'file' => $data['file'] ?? null,
        ];
    }

}
