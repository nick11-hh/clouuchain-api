<?php


namespace App\Services\ThirdPart\HuaLei;


use App\Models\CompanyExpress;
use App\Models\Order;
use App\Models\OrderDockingRecord;

class Response
{

    public $result;

    public function __construct($result)
    {
        $this->result = $result;
    }

    public function isSuccessful(): bool
    {
        return $this->result['ack'];
    }

    /**
     * @return array|string
     */
    public function result()
    {
        return $this->result;
    }

    public function save(string $orderSn)
    {
        $result = $this->result();
        $result['order_sn'] = $orderSn;
        $this->saveToDB($result);
    }

    /**
     * @param array $data
     * @return bool
     * @throws \App\Exceptions\AccidentException
     */
    protected function saveToDB(array $data)
    {
        /** @var Order $order */
        $order = Order::query()->where('order_sn', $data['order_sn'])->first();

        if (!$order) {
            info(sprintf('HuaLei返回数据处理：订单%s不存在', $data['order_sn']));

            return false;
        }

        $this->getOrderLabel($order, $data);

        $this->updateOrderTrackNumber($order, $data['tracking_number']);

        return true;
    }


    /**
     * @param Order $order
     * @param string $mailNo
     * @throws \App\Exceptions\AccidentException
     */
    public function getOrderLabel(Order $order, array $data)
    {
        $labelData = (new HuaLei($order->company_id))->getLabel();
        $order->dockingRecords()->create(
            [
                'type' => OrderDockingRecord::TYPE_HUA_LEI,
                'data' => $labelData,
                'company_id' => $order['company_id']
            ]
        );
    }


    /**
     * @param Order $order
     * @param string $trackNumber
     */
    protected function updateOrderTrackNumber(Order $order, string $trackNumber)
    {
        $expressCompany = CompanyExpress::query()->firstOrCreate(
            [
                'company_id' => $order->company_id,
                'code' => 'HuaLei',
            ],
            [
                'name' => 'HuaLei',
            ]
        );

        if (!$expressCompany) {
            $order->update(['logistics_sn' => $trackNumber]);
        } else {
            $order->update(['logistics_sn' => $trackNumber, 'logistics_company' => $expressCompany['code']]);
        }
    }


}
