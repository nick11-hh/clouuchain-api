<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TrackingSubscribe
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $companyId;

    public $type;

    public $trackingNumber = '';

    public $expressCompanyCode = '';

    public $phone = '';

    /**
     * TrackingSubscribe constructor.
     * @param int $trackingType
     * @param int $companyId
     * @param string $trackingNumber
     * @param string $expressCompanyCode
     */
    public function __construct(int $trackingType, int $companyId, string $trackingNumber, string $expressCompanyCode = '')
    {
        $this->type = $trackingType;
        $this->companyId = $companyId;
        $this->trackingNumber = $trackingNumber;
        $this->expressCompanyCode = $expressCompanyCode;
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
