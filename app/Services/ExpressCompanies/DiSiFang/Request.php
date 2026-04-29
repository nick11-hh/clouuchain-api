<?php

namespace App\Services\ExpressCompanies\DiSiFang;

use App\Models\CompanyAdmin;
use App\Models\DeclareOrder;
use App\Models\DeclareOrderBox;
use App\Models\Order;
use Illuminate\Support\Str;

class Request
{
    protected Order $order;

    protected DeclareOrder $declare;

    protected DeclareOrderBox $box;

    protected string $appKey = '';

    protected string $appSecret = '';

    /**
     * Request constructor.
     */
    public function __construct()
    {
    }

    /**
     * @param string $appKey
     * @return Request
     */
    public function setAppKey(string $appKey)
    {
        $this->appKey = $appKey;

        return $this;
    }

    /**
     * @param string $appSecret
     * @return Request
     */
    public function setAppSecret(string $appSecret)
    {
        $this->appSecret = $appSecret;

        return $this;
    }

    /**
     * @param DeclareOrder $declare
     * @return self
     */
    public function setDeclare(DeclareOrder $declare)
    {
        $this->declare = $declare;
        $this->order = $declare->order;

        return $this;
    }

    /**
     * @param DeclareOrderBox $declare
     * @return Request
     */
    public function setDeclareBox(DeclareOrderBox $declare): self
    {
        $this->box = $declare;
        $this->declare = $this->box->declareOrder;
        $this->order = $this->declare->order;

        return $this;
    }

    /**
     * @return array
     */
    public function transformFromDeclare()
    {
        $declare = $this->declare;
        $order = $this->order;
        $warehouse = $order->warehouse;
        $admin = CompanyAdmin::query()->select(['id', 'name'])->find($warehouse->company_id);
        $address = $this->order['address'];

        $items = $this->declare->items()->get()->map(function ($item) {
            return [
                'CName' => $item->cn_name,
                'EName' => $item->en_name,
                'HSCode' => $item->hs_code,
                'Quantity' => $item->quantity,
                'UnitPrice' => round($item->unit_value / 100, 1),
                'UnitWeight' => round($item->weight / 1000, 3),
                'SKU' => $item->sku,
                'CurrencyCode' => $item->currency,
                'InvoicePart' => $item->material,
            ];
        })->all();

        return [
            'CustomerOrderNumber' => $declare->order_sn,
            'ShippingMethodCode' => $order->expressLine->channel_code,
            'TrackingNumber' => null,
            'TransactionNumber' => null,
            'IossCode' => '',
            "BrazilianCode" => null,
            'SizeUnits' => 'cm',
            'PackageCount' => 1, // 包裹数量固定为1
            'Height' => $order->height / 100,
            'Length' => $order->height / 100,
            'Width' => $order->height / 100,
            'Weight' => round($declare->weight / 1000, 3),
            'ApplicationType' => 4,//1-Gift,2-Sameple,3-Documents,4-Others, 默认 4-Other
            'ReturnOption' => 0,//是否退回,包裹无人签收时是否退回，1-退回，0-不退回，默认 0
            'TariffPrepay' => $order->is_tariff,//关税预付服务费，1-参加关税预付，0-不参加关税预付，默认 0 (渠道需开通关税预付服务)
            'InsuranceOption' => $order->is_insurance,//包裹投保类型，0-不参保，1-按件，2-按比例，默认 0，表示不参加运输保险，具体参考包裹运输
            'SourceCode' => 'API',
            'Receiver' => [
                'CountryCode' => strtoupper($address['country']['code']),
                'FirstName' => $address['receiver_name'],
                'LastName' => '',
                'Company' => $address['company'],
                'Street' => $address['street'] == '' ? $address['address'] : $address['street'],
                'City' => $address['city'] == '' ? $address['area']['name'] : $address['city'],
                'State' => $address['province'] == '' ? $address['sub_area']['name'] : $address['province'],
                'Zip' => $address['postcode'],
                'Phone' => $address['phone'],
                'HouseNumber' => $address['door_no'],
                'Email' => $address['email']
            ],
            'Sender' => [
                'CountryCode' => 'CN',// 默认中国
                'FirstName' => $warehouse->receiver_name,
                'LastName' => '',
                'Company' => $admin->name ?? '',
                'Street' => $warehouse->address,
                'City' => $warehouse->city,
                'State' => $warehouse->province,
                'Zip' => $warehouse->postcode,
                'Phone' => $warehouse->phone,
            ],
            'OrderExtra' => [
                [
                    'ExtraCode' => 'V1',
                    'ExtraName' => '云途预缴',
                ]
            ],
            'Parcels' => $items,
            'ChildOrders' => []
        ];
    }

    /**
     * @return array
     */
    public function transformFromBox()
    {
        $order = $this->order;
        $warehouse = $order->warehouse;
        $box = $this->box->box;
        $admin = CompanyAdmin::query()->select(['id', 'name'])->find($warehouse->company_id);
        $address = $this->order['address'];

        $items = $this->box->items()->get()->map(function ($item) {
            return [
                'CName' => $item->cn_name,
                'EName' => $item->en_name,
                'HSCode' => $item->hs_code,
                'Quantity' => $item->quantity,
                'UnitPrice' => round($item->unit_value / 100, 1),
                'UnitWeight' => round($item->weight / 1000, 3),
                'SKU' => $item->sku,
                'CurrencyCode' => $item->currency,
                'InvoicePart' => $item->material,
            ];
        })->all();

        return [
            'CustomerOrderNumber' => $box->sn,
            'ShippingMethodCode' => $order->expressLine->channel_code,
            'TrackingNumber' => null,
            'TransactionNumber' => null,
            'IossCode' => '',
            "BrazilianCode" => null,
            'SizeUnits' => 'cm',
            'PackageCount' => 1, // 包裹数量固定为1
            'Height' => $box->height / 100,
            'Length' => $box->height / 100,
            'Width' => $box->height / 100,
            'Weight' => round($box->weight / 1000, 3),
            'ApplicationType' => 4,//1-Gift,2-Sameple,3-Documents,4-Others, 默认 4-Other
            'ReturnOption' => 0,//是否退回,包裹无人签收时是否退回，1-退回，0-不退回，默认 0
            'TariffPrepay' => $order->is_tariff,//关税预付服务费，1-参加关税预付，0-不参加关税预付，默认 0 (渠道需开通关税预付服务)
            'InsuranceOption' => $order->is_insurance,//包裹投保类型，0-不参保，1-按件，2-按比例，默认 0，表示不参加运输保险，具体参考包裹运输
            'SourceCode' => 'API',
            'Receiver' => [
                'CountryCode' => strtoupper($address['country']['code']),
                'FirstName' => $address['receiver_name'],
                'LastName' => '',
                'Company' => $address['company'],
                'Street' => $address['street'] == '' ? $address['address'] : $address['street'],
                'City' => $address['city'] == '' ? $address['area']['name'] : $address['city'],
                'State' => $address['province'] == '' ? $address['sub_area']['name'] : $address['province'],
                'Zip' => $address['postcode'],
                'Phone' => $address['phone'],
                'HouseNumber' => $address['door_no'],
                'Email' => $address['email']
            ],
            'Sender' => [
                'CountryCode' => 'CN',// 默认中国
                'FirstName' => $warehouse->receiver_name,
                'LastName' => '',
                'Company' => $admin->name ?? '',
                'Street' => $warehouse->address,
                'City' => $warehouse->city,
                'State' => $warehouse->province,
                'Zip' => $warehouse->postcode,
                'Phone' => $warehouse->phone,
            ],
            'OrderExtra' => [
                [
                    'ExtraCode' => 'V1',
                    'ExtraName' => '云途预缴',
                ]
            ],
            'Parcels' => $items,
            'ChildOrders' => []
        ];
    }
}
