<?php

namespace App\Services\ExpressCompanies\LTExp;

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

    /**
     * @return bool
     */
    public function isSuccessful(): bool
    {
        return $this->result['success'] == 1;
    }

    /**
     * @return mixed
     */
    public function result()
    {
        return $this->result['data'];
    }

    /**
     * @param string $orderSn
     * @return void
     * @throws \App\Exceptions\AccidentException
     */
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
        $order = Order::query()->where('order_sn', $data['refrence_no'])->first();

        if (!$order) {
            info(sprintf('LTEXP返回数据处理：订单%s不存在', $data['refrence_no']));

            return false;
        }

        $this->getOrderLabel($order, $data);

        $this->updateOrderTrackNumber($order, $data['shipping_method_no']);

        return true;
    }


    /**
     * @param Order $order
     * @param array $data
     * @throws \App\Exceptions\AccidentException
     */
    public function getOrderLabel(Order $order, array $data)
    {
        $labelData = (new LTExp($order->company_id))->getLabel([['reference_no' => $data['refrence_no']]]);

        if (!$labelData) {
            return;
        }

        $order->dockingRecords()->create(
            [
                'type' => OrderDockingRecord::TYPE_LT_EXP,
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
                'code' => 'LTEXP',
            ],
            [
                'name' => 'LTEXP',
            ]
        );

        if (!$expressCompany) {
            $order->update(['logistics_sn' => $trackNumber]);
        } else {
            $order->update(['logistics_sn' => $trackNumber, 'logistics_company' => $expressCompany['code']]);
        }
    }
}
