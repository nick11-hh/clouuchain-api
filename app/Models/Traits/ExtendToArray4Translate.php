<?php

namespace App\Models\Traits;

/**
 * 这个 trait 用于扩展 model 的 toArray 方法使其适应 translate 包
 */
trait ExtendToArray4Translate
{
    use BatchUpdate;

    /**
     *
     */
    public function attributesToArray()
    {
        $attributes = parent::attributesToArray();

        if (isset(self::$useOrigin) && self::$useOrigin === false) {
            //扩展部分
            foreach ($this->translatable as $value) {
                if (array_key_exists($value, $attributes)) {
                    $attributes[$value] = $this->getAttributeValue($value);
                }
            }
        }

        return $attributes;
    }
}
