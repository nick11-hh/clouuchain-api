<?php

namespace App\Services\Admin;

use App\Services\Collect\Platform\Y1688\Y1688Service;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class AlibabaService extends BaseService
{
    public Y1688Service $service;
    public function __construct()
    {
        $this->service = new Y1688Service();
    }

    /**
     * 获取授权链接
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function alibabaAuthorize($id): string
    {
        return $this->service->alibabaAuthorize($id);
    }

    /**
     * 获取token
     * @return bool
     */
    public function generateAccessToken(): bool
    {
        return $this->service->generateAccessToken();
    }
}
