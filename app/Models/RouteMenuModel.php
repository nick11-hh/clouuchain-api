<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class RouteMenuModel extends Model
{
    use HasFactory;

    public $table = 'dsp_route_menus';

    public function routes()
    {
        return $this->hasMany(self::class, 'parent_id', 'id')
            ->with('routes');
    }

    public static function boot()
    {
        parent::boot();

        static::addGlobalScope('enabled', function ($builder) {
            $builder->where('enabled', 1);
        });
    }
}
