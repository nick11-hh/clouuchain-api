<?php

namespace App\Console\Commands;

use App\Services\RabbitMQService;
use Illuminate\Console\Command;

class RabbitMQListenCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rabbitmq:listen 
                           {queue : Queue name to listen to}
                           {--callback= : Callback function name to handle messages}
                           {--auto-ack=1 : Auto acknowledge messages (1 for true, 0 for false)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Listen to a RabbitMQ queue and process messages';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $queue = $this->argument('queue');
        $autoAck = filter_var($this->option('auto-ack'), FILTER_VALIDATE_BOOLEAN);
        
        $this->info("开始监听队列: {$queue}");
        $this->info("自动确认: " . ($autoAck ? '是' : '否'));
        
        $rabbitMQService = new RabbitMQService();
        
        // 使用默认回调函数处理消息，或者从配置中获取
        $callback = $this->getCallback();
        
        try {
            $rabbitMQService->listenToQueue($queue, $callback, $autoAck);
        } catch (\Exception $e) {
            $this->error('监听过程中发生错误: ' . $e->getMessage());
            \Log::error('RabbitMQ监听错误', [
                'queue' => $queue,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        } finally {
            $rabbitMQService->close();
        }
    }
    
    /**
     * 获取处理消息的回调函数
     * 
     * @return callable
     */
    private function getCallback(): callable
    {
        $callbackOption = $this->option('callback');
        
        if ($callbackOption) {
            // 如果指定了回调函数名，尝试从配置或类中获取
            if (function_exists($callbackOption)) {
                return $callbackOption;
            }
            
            // 尝试解析为类方法格式: Class@method 或 Class::method
            if (strpos($callbackOption, '@') !== false || strpos($callbackOption, '::') !== false) {
                $delimiter = strpos($callbackOption, '@') !== false ? '@' : '::';
                [$class, $method] = explode($delimiter, $callbackOption);
                
                if (class_exists($class) && method_exists($class, $method)) {
                    return [$class, $method];
                }
            }
            
            $this->error("回调函数不存在: {$callbackOption}");
        }
        
        // 默认回调函数 - 输出消息内容到日志
        return function ($data, $message) {
            $this->info('收到消息: ' . json_encode($data));
            
            // 在这里可以添加具体的业务逻辑
            \Log::info('RabbitMQ消息处理', [
                'data' => $data,
                'routing_key' => $message->getRoutingKey(),
                'exchange' => $message->getExchangeName(),
            ]);
            
            // 返回true表示处理成功
            return true;
        };
    }
}