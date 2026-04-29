<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Landlord\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * RabbitMQ用户注入消费者类
 * 处理用户相关的消息队列操作
 */
class RabbitMQUserConsumer
{
    /**
     * 处理用户信息注入的消息
     *
     * @param array $data 消息数据
     * @return bool
     * @throws Throwable
     */
    public function handleUserInjection(array $data): bool
    {
        try {
            Log::info('接收到用户信息注入消息', ['data' => $data]);

            // 执行用户信息注入
            $result = $this->injectUserInfo($data);

            if ($result) {
                Log::info('用户信息注入成功', [
                    'user_id' => $data['user_id'] ?? $data['userId'] ?? null,
                    'email' => $data['email'] ?? null
                ]);
                return true;
            } else {
                Log::error('用户信息注入失败', ['data' => $data]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('处理用户信息注入时发生异常: ' . $e->getMessage(), [
                'data' => $data,
                'exception' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * 处理用户信息删除的消息
     *
     * @param array $data 消息数据
     * @return bool
     * @throws Throwable
     */
    public function handleUserDeletion(array $data): bool
    {
        try {
            Log::info('接收到用户信息删除消息', ['data' => $data]);

            // 执行用户信息删除
            $result = $this->deleteUserInfo($data);

            if ($result) {
                Log::info('用户信息删除成功', [
                    'user_id' => $data['user_id'] ?? $data['userId'] ?? null,
                    'email' => $data['email'] ?? null
                ]);
                return true;
            } else {
                Log::error('用户信息删除失败', ['data' => $data]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('处理用户信息删除时发生异常: ' . $e->getMessage(), [
                'data' => $data,
                'exception' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * 处理用户禁用/启用状态同步的消息
     *
     * @param array $data 消息数据
     * @return bool
     * @throws Throwable
     */
    public function handleUserStatusSync(array $data): bool
    {
        try {
            Log::info('接收到用户禁用状态同步消息', ['data' => $data]);

            // 执行用户状态同步
            $result = $this->syncUserStatus($data);

            if ($result) {
                Log::info('用户禁用状态同步成功', [
                    'user_id' => $data['user_id'] ?? $data['userId'] ?? null,
                    'status' => $data['status'] ?? $data['forbid_login'] ?? null,
                    'email' => $data['email'] ?? null
                ]);
                return true;
            } else {
                Log::error('用户禁用状态同步失败', ['data' => $data]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('处理用户禁用状态同步时发生异常: ' . $e->getMessage(), [
                'data' => $data,
                'exception' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * 执行用户信息注入
     *
     * @param array $data
     * @return bool
     * @throws Throwable
     */
    private function injectUserInfo(array $data): bool
    {
        // 检查并设置当前租户
        $tenantId = $data['tenantId'] ?? $data['tenant_id'] ?? null;
        if ($tenantId) {
            $tenant = Tenant::find(1);
            if ($tenant) {
                $tenant->makeCurrent();
            } else {
                Log::error('找不到指定的租户', ['tenant_id' => $tenantId, 'data' => $data]);
                return false;
            }
        } else {
            Log::warning('未提供租户ID，可能影响数据库操作', ['data' => $data]);
        }

        DB::beginTransaction();
        try {
            // 准备用户数据，确保字段映射正确
            $userData = [
                'email' => $data['email'] ?? null,
                'name' => $data['userName'] ?? null,
                'phone' => $data['phoneNumber'] ?? null,
                'username' => $data['loginName'] ?? ($data['email'] ?? null),
                'phone_area_code' => $data['phone_area_code'] ?? '+86',
                'group_id' => $data['group_id'] ?? 0,
                'invite_code' => $data['inviteCode'] ?? null,
                'updated_at' => now(),
            ];

            // 特别处理用户ID字段，兼容不同的字段名
            $userId = $data['userId'] ?? $data['user_id'] ?? null;

            if ($userId === null) {
                Log::error('用户ID不能为空', ['data' => $data]);
                DB::rollBack();
                return false;
            }

            // 根据User模型结构查找或创建用户
            Admin::updateOrCreate(
                ['id' => $userId],
                $userData
            );

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('用户信息注入数据库操作失败: ' . $e->getMessage(), [
                'data' => $data,
                'exception' => $e
            ]);
            return false;
        }
    }

    private function deleteUserInfo(array $data): bool
    {
        Log::info('接收到用户信息删除消息', ['data' => $data]);

        // 检查并设置当前租户
        $tenantId = $data['tenantId'] ?? $data['tenant_id'] ?? null;
        if ($tenantId) {
            $tenant = Tenant::find(1);
            if ($tenant) {
                $tenant->makeCurrent();
            } else {
                Log::error('找不到指定的租户', ['tenant_id' => $tenantId, 'data' => $data]);
                return false;
            }
        } else {
            Log::warning('未提供租户ID，可能影响数据库操作', ['data' => $data]);
        }

        DB::beginTransaction();
        try {
            // 获取用户ID
            $userId = $data['userId'] ?? $data['user_id'] ?? null;

            if ($userId === null) {
                Log::error('用户ID不能为空', ['data' => $data]);
                DB::rollBack();
                return false;
            }

            // 查找并删除用户
            $user = Admin::find($userId);
            if (!$user) {
                Log::warning('找不到指定用户', ['user_id' => $userId, 'data' => $data]);
                DB::rollBack();
                return false;
            }

            // 执行删除操作（软删除）
            $user->delete();

            DB::commit();
            Log::info('用户信息删除成功', ['user_id' => $userId]);
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('用户信息删除数据库操作失败: ' . $e->getMessage(), [
                'data' => $data,
                'exception' => $e
            ]);
            return false;
        }
    }

    /**
     * 执行用户状态同步
     *
     * @param array $data
     * @return bool
     * @throws Throwable
     */
    private function syncUserStatus(array $data): bool
    {
        // 检查并设置当前租户
        $tenantId = $data['tenantId'] ?? $data['tenant_id'] ?? null;
        if ($tenantId) {
            $tenant = Tenant::find(1);
            if ($tenant) {
                $tenant->makeCurrent();
            } else {
                Log::error('找不到指定的租户', ['tenant_id' => $tenantId, 'data' => $data]);
                return false;
            }
        } else {
            Log::warning('未提供租户ID，可能影响数据库操作', ['data' => $data]);
        }

        DB::beginTransaction();
        try {
            // 获取用户ID
            $userId = $data['userId'] ?? $data['user_id'] ?? null;

            if ($userId === null) {
                Log::error('用户ID不能为空', ['data' => $data]);
                DB::rollBack();
                return false;
            }

            $forbidLogin = match ($data['action']) {
                'USER_DISABLED' => 0,
                'USER_ENABLED' => 1,
                default => null,
            };

            // 确保状态值是有效的布尔值或整数
            if ($forbidLogin === null) {
                Log::error('用户禁用状态不能为空', ['data' => $data]);
                DB::rollBack();
                return false;
            }

            // 将状态值转换为正确的格式 (1禁用，0启用)
            $forbidLogin = $forbidLogin ? 1 : 0;

            // 查找用户并更新状态
            $user = Admin::find($userId);
            if (!$user) {
                Log::warning('找不到指定用户', ['user_id' => $userId, 'data' => $data]);
                DB::rollBack();
                return false;
            }

            // 更新用户禁用状态
            $user->enable = $forbidLogin;
            $user->save();

            DB::commit();
            Log::info('用户禁用状态更新成功', ['user_id' => $userId, 'forbid_login' => $forbidLogin]);
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('用户禁用状态同步数据库操作失败: ' . $e->getMessage(), [
                'data' => $data,
                'exception' => $e
            ]);
            return false;
        }
    }

    /**
     * 通用消息处理器
     * 根据消息类型分发到不同的处理方法
     *
     * @param array $data
     * @return bool
     * @throws Throwable
     */
    public function handleMessage(array $data): bool
    {
        $action = $data['action'] ?? '';

        // 添加日志来调试实际的action值，包括类型和长度
        Log::info('处理消息 - Action值调试', [
            'raw_action' => $action,
            'action_type' => gettype($action),
            'action_length' => strlen(is_string($action) ? $action : ''),
            'action_escaped' => addcslashes($action ?? '', "\0\t\n\r\f\v\"\\"),
            'data' => $data
        ]);

        switch (trim(strtoupper((string)$action))) {
            case 'USER_CREATED':
                return $this->handleUserInjection($data);
            case 'USER_DELETED':
                return $this->handleUserDeletion($data);
            case 'USER_DISABLED':
            case 'USER_ENABLED':
                return $this->handleUserStatusSync($data);
            default:
                Log::warning('未知的用户操作类型', [
                    'action' => $action,
                    'data' => $data
                ]);
                return false;
        }
    }
}
