<?php
/**
 * @Author: h9471
 * @Created: 2020/2/14 14:24
 */

namespace App\Models\Traits;

trait HasStatusName
{
    /**
     * 获得
     *
     * @return mixed
     */
    public function getStatusNameAttribute()
    {
        $statusName = [
            self::WAIT_CHECK => __('待审核'),
            self::CHECK_SUCCESS => __('审核通过'),
            self::CHECK_FAIL => __('审核拒绝'),
        ];

        return $statusName[$this->status];
    }
}
