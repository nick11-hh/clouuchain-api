<?php

/**
 * @Author: h9471
 * @Created: 2019/9/12 14:49
 */

namespace App\Services\Admin;

use Illuminate\Database\Eloquent\Model;

trait HasNameUniqueValidation
{
    public function validateNames(?Model $model = null, ?int $exclude_id = null, ?string $attr = null, array $data = [])
    {
        if ($attr === null && $data === []) {
            if ($exclude_id === null) {
                ($model ?? $this->model)::validateUniqueOrFail('cn_name', $this->formData['cn_name']);
                ($model ?? $this->model)::validateUniqueOrFail('en_name', $this->formData['en_name']);
            } else {
                ($model ?? $this->model)::validateUniqueOrFail('cn_name', $this->formData['cn_name'], $exclude_id);
                ($model ?? $this->model)::validateUniqueOrFail('en_name', $this->formData['en_name'], $exclude_id);
            }
        }
    }
}
