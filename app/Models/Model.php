<?php

/**
 * @Author: h9471
 * @Created: 2019/10/21 17:03
 */

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model as BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class Model extends BaseModel
{
    use SoftDeletes;

    //protected static $logAttributes = ['*'];
    public $translatable = [];

    public $searchable = [];

    protected $guarded = [];

    protected $hidden = ['deleted_at'];

    /**
     * @param  array  $ids
     * @return bool
     */
    public static function isValid(array $ids): bool
    {
        return static::whereIn('id', $ids)->count() === count(array_unique($ids));
    }

    /**
     * 获得国际化名字
     *
     * @return mixed
     */
    public function getINameAttribute()
    {
        $local = app('translator')->getLocale();

        if ($local !== 'zh_CN') {
            if ($this->en_name) {
                return $this->en_name;
            }
            return $this->name_en;
        }

        return $this->name_cn ?? $this->cn_name;
    }


    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {

        });
    }

    /**
     * 为数组 / JSON 序列化准备日期。
     *
     * @param  \DateTimeInterface  $date
     * @return string
     */
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format($this->dateFormat ?: 'Y-m-d H:i:s');
    }

    /**
     * Convert the model instance to an array.
     *
     * @return array
     */
    public function toBaseArray()
    {
        return array_merge($this->attributesToArrayBase(), $this->relationsToArray());
    }

    /**
     * Convert the model's attributes to an array.
     *
     * @return array
     */
    public function attributesToArrayBase()
    {
        // If an attribute is a date, we will cast it to a string after converting it
        // to a DateTime / Carbon instance. This is so we will get some consistent
        // formatting while accessing attributes vs. arraying / JSONing a model.
        $attributes = $this->addDateAttributesToArray(
            $attributes = $this->getArrayableAttributes()
        );

        $attributes = $this->addMutatedAttributesToArray(
            $attributes, $mutatedAttributes = $this->getMutatedAttributes()
        );

        // Next we will handle any casts that have been setup for this model and cast
        // the values to their appropriate type. If the attribute has a mutator we
        // will not perform the cast on those attributes to avoid any confusion.
        $attributes = $this->addCastAttributesToArray(
            $attributes, $mutatedAttributes
        );

        // Here we will grab all of the appended, calculated attributes to this model
        // as these attributes are not really in the attributes array, but are run
        // when we need to array or JSON the model for convenience to the coder.
        foreach ($this->getArrayableAppends() as $key) {
            $attributes[$key] = $this->mutateAttributeForArray($key, null);
        }

        return $attributes;
    }
}
