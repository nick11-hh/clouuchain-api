<?php

namespace App\Models;

use App\Models\Traits\Basis;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpressLineRegionTemplatePostcodeArea extends Model
{
    use Basis;

    public $translatable = [];

    protected $table = 'dsp_express_line_region_template_postcode_areas';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];

    protected $appends = [];

    /**
     * 所属分区
     *
     * @return BelongsTo
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(ExpressLineRegionTemplate::class, 'region_id', 'id');
    }
}
