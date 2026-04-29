<?php

namespace App\Services;

use Exception;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMQService
{
    private ?AMQPStreamConnection $connection = null;
    private string $host;
    private int $port;
    private string $user;
    private string $password;
    private string $vhost;
    private float $connectionTimeout;
    private float $readWriteTimeout;
    private int $heartbeat;

    public function __construct()
    {
        $this->host = config('queue.connections.rabbitmq.host', 'localhost');
        $this->port = config('queue.connections.rabbitmq.port', 5672);
        $this->user = config('queue.connections.rabbitmq.user', 'guest');
        $this->password = config('queue.connections.rabbitmq.password', 'guest');
        $this->vhost = config('queue.connections.rabbitmq.vhost', '/');
        $this->connectionTimeout = (float) config('queue.connections.rabbitmq.connection_timeout', 30.0); // 增加连接超时时间
        $this->readWriteTimeout = (float) config('queue.connections.rabbitmq.read_write_timeout', 30.0); // 增加读写超时时间
        $this->heartbeat = config('queue.connections.rabbitmq.heartbeat', 60);

        $this->initializeConnection();
    }

    private function initializeConnection(): void
    {
        $maxRetries = 3;
        $retryCount = 0;

        while ($retryCount < $maxRetries) {
            try {
                $this->connection = new AMQPStreamConnection(
                    $this->host,
                    $this->port,
                    $this->user,
                    $this->password,
                    $this->vhost,
                    false, // insist
                    'AMQPLAIN', // login_method
                    null, // login_response
                    'en_US', // locale
                    $this->connectionTimeout, // connection_timeout
                    $this->readWriteTimeout, // read_write_timeout
                    null, // context
                    true, // keepalive
                    $this->heartbeat // heartbeat
                );

                // 连接成功，跳出循环
                break;

            } catch (Exception $e) {
                $retryCount++;
                \Log::warning("RabbitMQ连接失败，正在重试 ({$retryCount}/{$maxRetries}): " . $e->getMessage());

                if ($retryCount >= $maxRetries) {
                    \Log::error("RabbitMQ连接失败，已达到最大重试次数: " . $e->getMessage());
                    throw $e; // 重新抛出异常
                }

                // 等待一段时间后重试
                sleep(2);
            }
        }
    }

    /**
     * 检查连接是否有效
     */
    public function isConnected(): bool
    {
        return $this->connection && $this->connection->isConnected();
    }

    /**
     * 获取连接信息
     */
    public function getConnectionInfo(): array
    {
        if (!$this->connection) {
            return [
                'connected' => false,
                'host' => $this->host,
                'port' => $this->port,
                'error' => 'No connection established'
            ];
        }

        return [
            'connected' => $this->connection->isConnected(),
            'host' => $this->host,
            'port' => $this->port,
            'vhost' => $this->vhost,
            'user' => $this->user
        ];
    }

    /**
     * 监听指定队列
     *
     * @param string $queue 队列名称
     * @param callable $callback 处理消息的回调函数
     * @param bool $autoAck 是否自动确认消息
     */
    public function listenToQueue(string $queue, callable $callback, bool $autoAck = true): void
    {
        while (true) {
            try {
                // 检查连接是否有效，如果无效则重新连接
                if (!$this->connection || !$this->connection->isConnected()) {
                    $this->initializeConnection();
                }

                $channel = $this->connection->channel();

                // 声明队列
                $channel->queue_declare($queue, false, true, false, false);

                // 设置QoS，一次只处理一条消息
                $channel->basic_qos(null, 1, null);

                // 注册消费者
                $channel->basic_consume($queue, '', false, $autoAck, false, false, function (AMQPMessage $msg) use ($callback, $autoAck) {
                    try {
                        // 执行回调处理消息
                        $result = $callback(json_decode($msg->getBody(), true), $msg);

                        // 如果未启用自动确认，需要手动确认消息
                        if (!$autoAck && $result !== false) {
                            $msg->ack();
                        }
                    } catch (Exception $e) {
                        \Log::error('RabbitMQ消息处理失败: ' . $e->getMessage(), [
                            'message_body' => $msg->getBody(),
                            'error' => $e->getMessage()
                        ]);

                        // 如果处理失败且未启用自动确认，拒绝消息并重新入队
                        if (!$autoAck) {
                            $msg->nack(true); // requeue = true，重新入队
                        }
                    }
                });

                // 持续监听
                while ($channel->is_consuming()) {
                    $channel->wait(null, 10); // 设置较短的超时时间，以便检测连接状态
                }

                $channel->close();

                break; // 如果正常退出循环，跳出主循环
            } catch (Exception $e) {
                \Log::error('RabbitMQ监听异常: ' . $e->getMessage(), [
                    'queue' => $queue,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                // 如果是连接超时异常，等待更长时间后重试
                if (str_contains($e->getMessage(), 'timeout')) {
                    sleep(10);
                } else {
                    sleep(4);
                }
            }
        }
    }

    /**
     * 发送消息到队列
     *
     * @param string $queue 队列名称
     * @param array $data 消息数据
     * @param array $properties 消息属性
     * @throws Exception
     */
    public function publishToQueue(string $queue, array $data, array $properties = []): void
    {
        $maxRetries = 3;
        $retryCount = 0;

        while ($retryCount < $maxRetries) {
            try {
                // 检查连接是否有效，如果无效则重新连接
                if (!$this->connection || !$this->connection->isConnected()) {
                    $this->initializeConnection();
                }

                $channel = $this->connection->channel();

                // 声明队列
                $channel->queue_declare($queue, false, true, false, false);

                $msg = new AMQPMessage(
                    json_encode($data),
                    array_merge(['content_type' => 'application/json', 'delivery_mode' => 2], $properties)
                );

                $channel->basic_publish($msg, '', $queue);

                $channel->close();

                break; // 成功发送，跳出循环

            } catch (Exception $e) {
                $retryCount++;
                \Log::warning("RabbitMQ消息发送失败，正在重试 ({$retryCount}/{$maxRetries}): " . $e->getMessage());

                if ($retryCount >= $maxRetries) {
                    \Log::error("RabbitMQ消息发送失败，已达到最大重试次数: " . $e->getMessage());
                    throw $e; // 重新抛出异常
                }

                // 等待一段时间后重试
                sleep(2);

                // 重新初始化连接
                $this->initializeConnection();
            }
        }
    }

    /**
     * 监听多个队列
     *
     * @param array $queueCallbacks 队列和回调函数的映射
     * @param bool $autoAck 是否自动确认消息
     */
    public function listenToMultipleQueues(array $queueCallbacks, bool $autoAck = true): void
    {
        while (true) {
            try {
                // 检查连接是否有效，如果无效则重新连接
                if (!$this->connection || !$this->connection->isConnected()) {
                    $this->initializeConnection();
                }

                $channel = $this->connection->channel();

                foreach ($queueCallbacks as $queue => $callback) {
                    // 声明队列
                    $channel->queue_declare($queue, false, true, false, false);

                    // 注册消费者
                    $channel->basic_consume($queue, $queue, false, $autoAck, false, false, function (AMQPMessage $msg) use ($callback, $autoAck) {
                        try {
                            $result = $callback(json_decode($msg->getBody(), true), $msg);

                            if (!$autoAck && $result !== false) {
                                $msg->ack();
                            }
                        } catch (Exception $e) {
                            \Log::error('RabbitMQ消息处理失败: ' . $e->getMessage(), [
                                'message_body' => $msg->getBody(),
                                'error' => $e->getMessage()
                            ]);

                            if (!$autoAck) {
                                $msg->nack(true);
                            }
                        }
                    });
                }

                // 设置QoS，一次只处理一条消息
                $channel->basic_qos(null, 1, null);

                // 持续监听
                while ($channel->is_consuming()) {
                    $channel->wait(null, 10); // 设置较短的超时时间，以便检测连接状态
                }

                $channel->close();

                break; // 如果正常退出循环，跳出主循环
            } catch (Exception $e) {
                \Log::error('RabbitMQ多队列监听异常: ' . $e->getMessage(), [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                // 如果是连接超时异常，等待更长时间后重试
                if (str_contains($e->getMessage(), 'timeout')) {
                    sleep(10);
                } else {
                    sleep(5);
                }
            }
        }
    }

    /**
     * 监听用户相关队列
     *
     * @param callable $callback 处理用户消息的回调函数
     * @param bool $autoAck 是否自动确认消息
     */
    public function listenToUserQueue(callable $callback, bool $autoAck = true): void
    {
        $queue = config('queue.connections.rabbitmq.user_queue', 'user_operations');
        $this->listenToQueue($queue, $callback, $autoAck);
    }

    /**
     * 发送用户相关消息
     *
     * @param array $data 消息数据
     * @param array $properties 消息属性
     */
    public function publishToUserQueue(array $data, array $properties = []): void
    {
        $queue = config('queue.connections.rabbitmq.user_queue', 'user_operations');
        $this->publishToQueue($queue, $data, $properties);
    }

    /**
     * 监听指定交换机的路由键
     *
     * @param string $exchange 交换机名称
     * @param string $routingKey 路由键
     * @param callable $callback 处理消息的回调函数
     * @param string $exchangeType 交换机类型
     * @param bool $autoAck 是否自动确认消息
     */
    public function listenToExchange(string $exchange, string $routingKey, callable $callback, string $exchangeType = 'direct', bool $autoAck = true): void
    {
        while (true) {
            try {
                // 检查连接是否有效，如果无效则重新连接
                if (!$this->connection || !$this->connection->isConnected()) {
                    $this->initializeConnection();
                }

                $channel = $this->connection->channel();

                // 声明交换机
                $channel->exchange_declare($exchange, $exchangeType, false, true, false);

                // 声明临时队列
                list($queueName, ,) = $channel->queue_declare('', false, false, true, false);

                // 绑定队列到交换机
                $channel->queue_bind($queueName, $exchange, $routingKey);

                // 设置QoS，一次只处理一条消息
                $channel->basic_qos(null, 1, null);

                // 注册消费者
                $channel->basic_consume($queueName, '', false, $autoAck, false, false, function (AMQPMessage $msg) use ($callback, $autoAck) {
                    try {
                        // 执行回调处理消息
                        $result = $callback(json_decode($msg->getBody(), true), $msg);

                        // 如果未启用自动确认，需要手动确认消息
                        if (!$autoAck && $result !== false) {
                            $msg->ack();
                        }
                    } catch (Exception $e) {
                        \Log::error('RabbitMQ交换机消息处理失败: ' . $e->getMessage(), [
                            'message_body' => $msg->getBody(),
                            'error' => $e->getMessage()
                        ]);

                        // 如果处理失败且未启用自动确认，拒绝消息并重新入队
                        if (!$autoAck) {
                            $msg->nack(true); // requeue = true，重新入队
                        }
                    }
                });

                // 持续监听
                while ($channel->is_consuming()) {
                    $channel->wait(null, 10); // 设置较短的超时时间，以便检测连接状态
                }

                $channel->close();

                break; // 如果正常退出循环，跳出主循环
            } catch (Exception $e) {
                \Log::error('RabbitMQ交换机监听异常: ' . $e->getMessage(), [
                    'exchange' => $exchange,
                    'routing_key' => $routingKey,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                // 如果是连接超时异常，等待更长时间后重试
                if (str_contains($e->getMessage(), 'timeout')) {
                    sleep(10);
                } else {
                    sleep(4);
                }
            }
        }
    }

    /**
     * 发送消息到交换机
     *
     * @param string $exchange 交换机名称
     * @param string $routingKey 路由键
     * @param array $data 消息数据
     * @param array $properties 消息属性
     * @param string $exchangeType 交换机类型
     */
    public function publishToExchange(string $exchange, string $routingKey, array $data, array $properties = [], string $exchangeType = 'direct'): void
    {
        $maxRetries = 3;
        $retryCount = 0;

        while ($retryCount < $maxRetries) {
            try {
                // 检查连接是否有效，如果无效则重新连接
                if (!$this->connection || !$this->connection->isConnected()) {
                    $this->initializeConnection();
                }

                $channel = $this->connection->channel();

                // 声明交换机
                $channel->exchange_declare($exchange, $exchangeType, false, true, false);

                $msg = new AMQPMessage(
                    json_encode($data),
                    array_merge(['content_type' => 'application/json', 'delivery_mode' => 2], $properties)
                );

                $channel->basic_publish($msg, $exchange, $routingKey);

                $channel->close();

                break; // 成功发送，跳出循环

            } catch (Exception $e) {
                $retryCount++;
                \Log::warning("RabbitMQ交换机消息发送失败，正在重试 ({$retryCount}/{$maxRetries}): " . $e->getMessage());

                if ($retryCount >= $maxRetries) {
                    \Log::error("RabbitMQ交换机消息发送失败，已达到最大重试次数: " . $e->getMessage());
                    throw $e; // 重新抛出异常
                }

                // 等待一段时间后重试
                sleep(2);

                // 重新初始化连接
                $this->initializeConnection();
            }
        }
    }

    /**
     * 关闭连接
     */
    public function close(): void
    {
        if ($this->connection && $this->connection->isConnected()) {
            $this->connection->close();
        }
    }
}
