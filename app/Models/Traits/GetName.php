<?php

namespace App\Models\Traits;

trait GetName
{
    public function getNameAttribute()
    {
        return isEn() ? $this->en_name : $this->cn_name;
    }
}
