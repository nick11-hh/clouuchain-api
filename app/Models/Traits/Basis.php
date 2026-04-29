<?php

/**
 * Created by PhpStorm.
 * User: lin
 * Date: 2019-05-13
 * Time: 18:36
 */

namespace App\Models\Traits;

use Illuminate\Support\Facades\Schema;

trait Basis
{
    /**
     * @param $query
     * @param int $size
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function scopePageSize($query, int $size = 10)
    {
        $size = app('request')->input('size') ? app('request')->input('size') : $size;
        $page = app('request')->input('page') ? app('request')->input('page') : 1;

        $size = ((int) $size) ?: 10;
        $page = ((int) $page) ?: 1;

        return $query->paginate($size, ['*'], 'page', $page);
    }

    public function change($parameter, $change = [])
    {
        if (count($change) === 0) {
            $change = Schema::getColumnListing($this->table);
        }
        foreach ($change as $value) {
            if (isset($parameter[$value])) {
                $this->$value = $parameter[$value];
            }
        }
        return $this->save();
    }

    public function scopeWhereWhen($query, $column, $value, $operator = '=')
    {
        return $query->when($value, function ($query) use ($value, $column, $operator) {
            $query->where($column, $operator, $value);
        });
    }
}
