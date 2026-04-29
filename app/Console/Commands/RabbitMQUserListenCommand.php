<?php

namespace App\Console\Commands;

use App\Services\RabbitMQService;
use App\Services\RabbitMQUserConsumer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RabbitMQUserListenCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rabbitmq:user-listen 
                           {--queue= : 指定要监听的队列名称}
                           {--connection= : 指定RabbitMQ连接配置}
                           {--help-info : 显示帮助信息}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '监听RabbitMQ用户相关消息队列';

    /**
     * 用户消费者实例
     *
     * @var RabbitMQUserConsumer
     */
    private RabbitMQUserConsumer $userConsumer;

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
            $this->info('正在初始化RabbitMQ用户监听服务...');

            // 初始化服务
            $this->rabbitMQService = new RabbitMQService();
            $this->userConsumer = new RabbitMQUserConsumer();

            // 获取队列名称
            $queue = $this->option('queue') ?? config('queue.connections.rabbitmq.user_queue', 'user_operations');

            $this->info("开始监听队列: {$queue}");

            // 启动监听
            $this->rabbitMQService->listenToQueue($queue, [$this->userConsumer, 'handleMessage']);

            return 0;
        } catch (\Exception $e) {
            Log::error('RabbitMQ用户监听命令执行失败: ' . $e->getMessage(), [
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
        $this->info('RabbitMQ用户监听命令');
        $this->line('');
        $this->info('用法:');
        $this->info('  php artisan rabbitmq:user-listen [选项]');
        $this->line('');
        $this->info('选项:');
        $this->info('  --queue=QUEUE           指定要监听的队列名称');
        $this->info('  --connection=CONNECTION 指定RabbitMQ连接配置');
        $this->info('  --help-info             显示此帮助信息');
        $this->line('');
        $this->info('示例:');
        $this->info('  php artisan rabbitmq:user-listen');
        $this->info('  php artisan rabbitmq:user-listen --queue=user_operations');
    }
}