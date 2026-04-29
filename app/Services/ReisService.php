<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class ReisService
{
    /**
     * 获取数据
     * @param $key
     * @return int|mixed|string|null
     */
    public function get($key)
    {
        $value = Redis::get($key);
        return !is_null($value) ? $this->unserialize($value) : '';
    }

    /**
     * 暂时存储
     * @param $key
     * @param $value
     * @param int $seconds
     * @return mixed
     */
    public function put($key, $value, $seconds = 3600)       //时间默认半小时
    {
        return Redis::setex($key, $seconds, $this->serialize($value));
    }

    /**
     * 永久存储
     * @param $key
     * @param $value
     * @return mixed
     */
    public function set($key, $value)
    {
        return Redis::set($key, $this->serialize($value));
    }

    /**
     * 删除
     * @param $key
     * @return bool
     */
    public function forget($key)
    {
        return Redis::del($key);
    }

    protected function serialize($value)
    {
        return is_numeric($value) ? $value : serialize($value);
    }

    protected function unserialize($value)
    {
        return is_numeric($value) ? $value : unserialize($value);
    }
}
