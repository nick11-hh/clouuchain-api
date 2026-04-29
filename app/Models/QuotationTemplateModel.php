<?php
namespace App\Models;

use App\Models\Traits\Basis;
use App\Models\Traits\HasValidateUnique;
use App\Models\Traits\CustomHasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * 报价模板--模型
 */
class QuotationTemplateModel extends Model
{
    use HasFactory;

    protected $table = 'dsp_quotation_template';

    use Basis,
        HasValidateUnique,
        CustomHasTranslations;

    public $searchable = [];

    public $translatable = ['cn_name', 'en_name', 'name'];

    /**
     * 运费模板
     * @return BelongsToMany
     */
    public function expressLine(): BelongsToMany
    {
        return $this->belongsToMany(
            ExpressLineModel::class,
            'dsp_express_line_quote',
            'quote_id',
            'express_line_id'
        );
    }
}
