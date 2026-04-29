<?php

namespace App\Listeners;

use App\Events\UserInfoInjected;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class HandleUserInfoInjection
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(UserInfoInjected $event): void
    {
        try {
            Log::info('处理用户信息注入事件开始', [
                'user_id' => $event->user->id,
                'email' => $event->user->email,
                'original_data' => $event->originalData
            ]);

            // 更新用户相关统计信息
//            $this->updateUserStatistics($event->user);

            // 触发其他相关事件
//            $this->triggerRelatedEvents($event->user, $event->originalData);

            Log::info('处理用户信息注入事件成功', [
                'user_id' => $event->user->id,
            ]);
        } catch (\Exception $e) {
            Log::error('处理用户信息注入事件失败: ' . $e->getMessage(), [
                'user_id' => $event->user->id,
                'original_data' => $event->originalData,
                'exception' => $e->getTraceAsString()
            ]);
            throw $e; // 重新抛出异常，以便系统能够正确处理错误
        }
    }

    /**
     * 更新用户统计信息
     */
    private function updateUserStatistics($user): void
    {
        try {
            // 实现用户统计信息更新逻辑
            // 例如：更新用户活跃度、积分等
            Log::debug('更新用户统计信息', [
                'user_id' => $user->id
            ]);
        } catch (\Exception $e) {
            Log::error('更新用户统计信息失败: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'exception' => $e
            ]);
            throw $e;
        }
    }

    /**
     * 触发相关事件
     */
    private function triggerRelatedEvents($user, array $originalData): void
    {
        try {
            // 根据原始数据触发其他相关事件
            // 例如：如果包含特殊字段，触发特殊处理
            Log::debug('触发相关事件', [
                'user_id' => $user->id,
                'original_data_keys' => array_keys($originalData)
            ]);
        } catch (\Exception $e) {
            Log::error('触发相关事件失败: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'exception' => $e
            ]);
            throw $e;
        }
    }
}
