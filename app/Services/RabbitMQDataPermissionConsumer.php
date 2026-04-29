<?php

namespace App\Services;

use App\Models\DataRangeGroup;
use App\Models\DataRangeGroupAdmin;
use App\Models\DataRangeTypePermission;
use App\Models\Landlord\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * RabbitMQ数据权限消费者类
 * 处理数据权限相关的消息队列操作
 */
class RabbitMQDataPermissionConsumer
{
    /**
     * 处理数据权限更新的消息
     *
     * @param array $data 消息数据
     * @return bool
     * @throws Throwable
     */
    public function handleDataPermissionUpdate(array $data): bool
    {
        try {
            Log::info('接收到数据权限更新消息', ['data' => $data]);

            // 执行数据权限更新
            $result = $this->updateDataPermission($data);

            if ($result) {
                Log::info('数据权限更新成功', [
                    'admin_id' => $data['admin_id'] ?? null,
                    'permission_type' => $data['permission_type'] ?? null
                ]);
                return true;
            } else {
                Log::error('数据权限更新失败', ['data' => $data]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('处理数据权限更新时发生异常: ' . $e->getMessage(), [
                'data' => $data,
                'exception' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * 执行数据权限更新
     *
     * @param array $data
     * @return bool
     * @throws Throwable
     */
    private function updateDataPermission(array $data): bool
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
            // 获取数据权限ID和更新内容
            $adminIds = $data['roleUserIds'] ?? [];
            $permissionIds = $data['dataPermissionUserIds'] ?? [];

            // 验证必要参数
            if (empty($permissionIds) || empty($adminIds)) {
                Log::error('数据权限ID和管理员ID不能为空', [
                    'admin_ids' => $adminIds,
                    'permission_ids' => $permissionIds,
                    'data' => $data
                ]);
                DB::rollBack();
                return false;
            }

            // 确保permissionIds是数组格式
            if (!is_array($permissionIds)) {
                $permissionIds = [$permissionIds];
            }

            // 确保adminIds是数组格式
            if (!is_array($adminIds)) {
                $adminIds = [$adminIds];
            }

            // 查找或创建数据权限分组
            $groupId = $this->findOrCreatePermissionGroup($permissionIds);

            if ($groupId) {
                // 将分组数据和adminid关联，避免重复插入
                $this->associateAdminsWithPermissionGroup($groupId, $adminIds);
            }

            DB::commit();
            Log::info('数据权限更新完成', [
                'admin_ids_count' => count($adminIds),
                'permission_ids_count' => count($permissionIds),
                'permission_group_id' => $groupId
            ]);
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('数据权限更新数据库操作失败: ' . $e->getMessage(), [
                'data' => $data,
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * 查找或创建权限分组
     *
     * @param array $permissionIds
     * @return int|null
     */
    private function findOrCreatePermissionGroup(array $permissionIds): ?int
    {
        // 使用range_value字段来查找匹配的权限分组，该字段会自动处理数组到JSON的转换
        // 比较JSON字符串来检查数组是否完全匹配
        $permissionIdsJson = json_encode($permissionIds);
        $existingPermission = DataRangeTypePermission::query()
            ->whereRaw('range_value = ?', [$permissionIdsJson])
            ->first();

        if ($existingPermission) {
            Log::info('找到现有的权限分组', ['group_id' => $existingPermission['group_id'], 'range_value' => $existingPermission['range_value']]);
            return $existingPermission['id'];
        }

        // 创建新的权限分组
        $permission_group_data = [
            'name' => 'Java权限分组' . time(), // 添加时间戳避免重复名称
            'creator_id' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        $groupId = DataRangeGroup::query()->insertGetId($permission_group_data);

        $permission_range_data = [
            'data_range_group_id' => (string)$groupId,
            'data_type' => 'customer',
            'range_type' => 'part',
            'range_value' => $permissionIdsJson,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $permissionId = DataRangeTypePermission::query()->insertGetId($permission_range_data);

        // 验证插入是否成功
        if (!$permissionId) {
            Log::error('创建权限分组失败', ['permission_range_data' => $permission_range_data]);
            return null;
        }

        Log::info('创建新的权限分组', [
            'group_id' => $groupId,
            'permission_id' => $permissionId,
            'permission_ids' => $permissionIds
        ]);

        return $groupId;
    }

    /**
     * 将管理员与权限分组关联，如果已存在关联则更新
     *
     * @param int $groupId
     * @param array $adminIds
     * @return void
     */
    private function associateAdminsWithPermissionGroup(int $groupId, array $adminIds): void
    {
        foreach ($adminIds as $adminId) {
            // 检查是否已存在关联，如果存在则更新，否则新增
            $existing = DataRangeGroupAdmin::query()
                ->where('admin_id', $adminId)
                ->first();

            if ($existing) {
                // 更新现有记录的data_range_group_id
                $existing->update([
                    'data_range_group_id' => $groupId,
                    'updated_at' => now(),
                ]);

                Log::info('更新管理员与权限分组关联', [
                    'admin_id' => $adminId,
                    'permission_group_id' => $groupId
                ]);
            } else {
                // 创建新记录
                $insertData = [
                    'data_range_group_id' => $groupId,
                    'admin_id' => $adminId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                DataRangeGroupAdmin::query()->insert($insertData);

                Log::info('关联管理员与权限分组', [
                    'admin_id' => $adminId,
                    'permission_group_id' => $groupId
                ]);
            }
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
        Log::info('处理数据权限消息 - Action值调试', [
            'raw_action' => $action,
            'action_type' => gettype($action),
            'action_length' => strlen(is_string($action) ? $action : ''),
            'action_escaped' => addcslashes($action ?? '', "\0\t\n\r\f\v\"\\"),
            'data' => $data
        ]);

        switch (trim(strtoupper((string)$action))) {
            case 'DATA_PERMISSION_USER_IDS_UPDATE':
                return $this->handleDataPermissionUpdate($data);
            default:
                Log::warning('未知的数据权限操作类型', [
                    'action' => $action,
                    'data' => $data
                ]);
                return false;
        }
    }
}
