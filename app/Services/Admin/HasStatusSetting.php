<?php
/**
 * @Author: h9471
 * @Created: 2019/12/17 12:00
 */

namespace App\Services\Admin;

trait HasStatusSetting
{
    /**
     * 设置状态
     *
     * @param  int  $id
     * @param  bool  $status
     * @return bool
     */
    public function setStatus(int $id, bool $status): bool
    {
        $setting = $this->model::findOrFail($id);

        return $setting->update(
            [
                'enabled' => (int) $status,
            ]
        ) !== false;
    }
}
