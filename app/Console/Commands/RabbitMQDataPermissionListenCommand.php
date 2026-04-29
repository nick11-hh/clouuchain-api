<?php

namespace App\Console\Commands;

use App\Services\RabbitMQService;
use App\Services\RabbitMQDataPermissionConsumer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RabbitMQDataPermissionListenCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rabbitmq:data-permission-listen 
                           {--queue= : 指定要监听的数据权限队列名称}
                           {--connection= : 指定RabbitMQ连接配置}
                           {--help-info : 显示帮助信息}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '监听RabbitMQ数据权限相关消息队列';

    /**
     * 数据权限消费者实例
     *
     * @var RabbitMQDataPermissionConsumer
     */
    private RabbitMQDataPermissionConsumer $dataPermissionConsumer;

    /**
     * RabbitMQ服务实例
     *
     * @var RabbitMQService
     */
    private RabbitMQService $rabbitMQService;

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        if ($this->option('help-info')) {
            $this->showHelpInfo();
            return 0;
        }

        try {
            $this->info('正在初始化RabbitMQ数据权限监听服务...');

            // 初始化服务
            $this->rabbitMQService = new RabbitMQService();
            $this->dataPermissionConsumer = new RabbitMQDataPermissionConsumer();

            // 获取队列名称
            $queue = $this->option('queue') ?? config('queue.connections.rabbitmq.data_permission_queue', 'data_permission_operations');

            $this->info("开始监听队列: {$queue}");

            // 启动监听
            $this->rabbitMQService->listenToQueue($queue, [$this->dataPermissionConsumer, 'handleMessage']);

            return 0;
        } catch (\Exception $e) {
            Log::error('RabbitMQ数据权限监听命令执行失败: ' . $e->getMessage(), [
                'exception' => $e->getTraceAsString()
            ]);
            
            $this->error('监听服务启动失败: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * 显示帮助信息
     */
    private function showHelpInfo(): void
    {
        $this->info('RabbitMQ数据权限监听命令');
        $this->line('');
        $this->info('用法:');
        $this->info('  php artisan rabbitmq:data-permission-listen [选项]');
        $this->line('');
        $this->info('选项:');
        $this->info('  --queue=QUEUE           指定要监听的队列名称');
        $this->info('  --connection=CONNECTION 指定RabbitMQ连接配置');
        $this->info('  --help-info             显示此帮助信息');
        $this->line('');
        $this->info('示例:');
        $this->info('  php artisan rabbitmq:data-permission-listen');
        $this->info('  php artisan rabbitmq:data-permission-listen --queue=data_permission_operations');
    }
}