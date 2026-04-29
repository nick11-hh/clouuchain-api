<?php

/**
 * @Author: h9471
 * @Created: 2020/3/19 13:48
 */

namespace App\Services\Admin;

trait HasBatchDelete
{
    /**
     * @return bool
     */
    public function batchDelete(): bool
    {
        $this->batchDeleteValidator();

        $ids = $this->formData['ids'] ?? [];

        return $this->model::whereIn('id', $ids)->delete() !== false;
    }

    private function batchDeleteValidator()
    {
        validator(
            $this->formData,
            [
                'ids' => 'sometimes|nullable|array',
            ]
        )->validate();
    }
}
