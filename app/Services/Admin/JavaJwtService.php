<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\Log;
use PHPOpenSourceSaver\JWTAuth\Providers\JWT\Lcobucci;
use PHPOpenSourceSaver\JWTAuth\Providers\JWT\Provider;
use InvalidArgumentException;

class JavaJwtService
{
    /**
     * Java服务使用的密钥
     */
    private const JAVA_JWT_SECRET = 'mK9xR2yL5nQ8wT3vB6cF1dG4hJ7pS0zA9xC2vN5mL8qT4wR7yU1kH3gF6jS9pD2n';

    /**
     * Java服务使用的算法
     */
    private const JAVA_JWT_ALGO = 'HS512';

    /**
     * JWT提供者实例
     *
     * @var Provider
     */
    protected Provider $jwtProvider;

    public function __construct()
    {
        $this->jwtProvider = new Lcobucci(
            self::JAVA_JWT_SECRET,
            self::JAVA_JWT_ALGO,
            ['public' => null, 'private' => null, 'passphrase' => null]
        );
    }

    /**
     * 解析来自Java服务的JWT token
     *
     * @param string $token JWT token
     * @return array|null 解析后的载荷数据
     */
    public function parseToken(string $token): ?array
    {
        try {
            $payload = $this->jwtProvider->decode($token);
            return (array) $payload;
        } catch (\Exception $e) {
            Log::warning('Failed to decode Java JWT token: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 验证token是否有效且未过期
     *
     * @param string $token JWT token
     * @return bool Token是否有效
     */
    public function validateToken(string $token): bool
    {
        try {
            $payload = $this->parseToken($token);

            if (!$payload) {
                return false;
            }

            // 检查是否已过期
            if (isset($payload['exp'])) {
                $exp = $payload['exp'];
                // 如果exp是时间戳
                if (is_numeric($exp)) {
                    return $exp > time();
                }

                // 如果exp是日期字符串
                if (is_string($exp)) {
                    return strtotime($exp) > time();
                }
            }

            return true;
        } catch (\Exception $e) {
            Log::warning('Failed to validate Java JWT token: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 从token中提取用户名
     *
     * @param string $token JWT token
     * @return string|null 用户名
     */
    public function getUsernameFromToken(string $token): ?string
    {
        $payload = $this->parseToken($token);

        if (!$payload) {
            return null;
        }

        // 根据Java代码，用户名存储在subject(主题)字段中
        return $payload['sub'] ?? $payload['username'] ?? null;
    }

    /**
     * 获取用于签名的密钥
     *
     * @return string
     */
    public function getSigningKey(): string
    {
        return self::JAVA_JWT_SECRET;
    }

    /**
     * 获取签名算法
     *
     * @return string
     */
    public function getAlgorithm(): string
    {
        return self::JAVA_JWT_ALGO;
    }
}
