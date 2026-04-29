<?php
namespace App\Models;

use App\Models\Traits\Basis;
use App\Models\Traits\HasValidateUnique;
use App\Models\Traits\CustomHasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ExpressLineQuoteModel extends Model
{
    use HasFactory;

    protected $table = 'dsp_express_line_quote';

    use Basis,
        HasValidateUnique,
        CustomHasTranslations;
}
