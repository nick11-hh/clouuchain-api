<?php

namespace App\Events;

use App\Models\Package;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * 更新包裹预警信息
 */
class UpdatedPackageWarning
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Package $package;

    /**
     * Create a new event instance.
     *
     * @param Package $package
     */
    public function __construct(Package $package)
    {
        info('包裹设置为预警状态');

        $this->package = $package;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('channel-name');
    }
}
