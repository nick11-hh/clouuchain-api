<?php

namespace App\Models;

use App\Models\Traits\CustomHasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientMenu extends Model
{
    use HasFactory;
    use SoftDeletes;
    use CustomHasTranslations;

    protected $table = 'dsp_client_menus';

    protected $guarded = [];

    //用于翻译
    public $translatable = ['name_translate'];

    protected $casts = [
        'name_translate' => 'array',
    ];

    public function routes()
    {
        return $this->hasMany(self::class, 'parent_id', 'id')
            ->with('routes')->where('enabled', 1)->where('is_show', 1);
    }

    public function allRoutes()
    {
        return $this->hasMany(self::class, 'parent_id', 'id')->with('allRoutes');
    }

    public static function boot()
    {
        parent::boot();

        // static::addGlobalScope('enabled', function ($builder) {
        //     $builder->where('enabled', 1);
        // });
    }

}
