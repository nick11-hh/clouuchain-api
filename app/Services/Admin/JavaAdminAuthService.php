<?php

namespace App\Services\Admin;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class JavaAdminAuthService
{
    protected $httpClient;
    protected $baseUrl;

    public function __construct()
    {
        $this->httpClient = new Client();
        $this->baseUrl = config('services.java_admin_auth.base_url');
    }

    /**
     * 调用Java服务的管理端登录接口
     *
     * @param array $credentials 管理员凭证
     * @return array|null
     */
    public function login(array $credentials)
    {
        try {
            $response = $this->httpClient->post("{$this->baseUrl}/api/auth/login", [
                'json' => $credentials,
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ]
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            Log::error('Java Admin Auth Service Login Error: ' . $e->getMessage(), [
                'credentials' => $credentials,
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Login failed: Unable to connect to authentication service'
            ];
        }
    }

    public function dataPermission(String $token)
    {
        try {
            $response = $this->httpClient->post("{$this->baseUrl}/api/admin/role/queryPHPDataPermissionByUserId", [
                'json' => (object) [],
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => $token
                ]
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            Log::error('Java Admin Auth Service Login Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Login failed: Unable to connect to authentication service'
            ];
        }
    }
}
