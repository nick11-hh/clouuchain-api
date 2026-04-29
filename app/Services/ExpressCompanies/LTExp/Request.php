<?php

namespace App\Services\ExpressCompanies\LTExp;

use App\Models\Company;
use App\Models\DeclareOrder;
use App\Models\DeclareOrderBox;
use App\Models\Order;

class Request
{
    public Order $order;

    public DeclareOrder $declare;

    public $companyId;

    public $items;

    public $channelCode = '';

    public function __construct(DeclareOrder $declare, int $companyId, $channelCode)
    {
        $this->companyId = $companyId;
        $this->declare = $declare;
        $this->channelCode = $channelCode;
    }


    public function transformByDeclare(DeclareOrder $declare)
    {
        $company = Company::query()->where('id', $this->companyId)->first();
        $order = $declare->order;
        $warehouse = $order->warehouse;
        $address = $order->address;
        //发件人信息
        $sender = [
            'shipper_name' => preg_replace('/[\xf0-\xf7].{3}/', '', $order->user->name) ?: 'LT',
            'shipper_company' => $company->name,
            'shipper_countrycode' => 'CN',
            'shipper_province' => !empty($warehouse->province) ? $warehouse->province : 'none',
            'shipper_city' => !empty($warehouse->city) ? $warehouse->city : 'none',
            'shipper_street' => !empty($warehouse->street) ? $warehouse->street : 'none',
            'shipper_postcode' => $warehouse->postcode,
            'shipper_areacode' => '',
            'shipper_telephone' => $warehouse->phone,
            'shipper_mobile' => '',
            'shipper_email' => '',
            'shipper_fax' => '',
        ];

        //收件人信息
        $receiver = [
            'consignee_name' => $address['receiver_name'],
            'consignee_company' => $address['address'] ?? '',
            'consignee_countrycode' => strtoupper($address['country']['code']),
            'consignee_province' => !empty($address['province']) ? $address['province'] : $address['area']['name'] ?? '',
            'consignee_city' => !empty($address['city']) ? $address['city'] : $address['area']['name'] ?? '',
            'consignee_street' => ltrim(($address['door_no'] ?? '') . ' ' . (is_array($address['area'] ?? []) ? '' : ($address['area'] ?? '')) . ' ' . $address['street'] ?? ''),
            'consignee_postcode' => $address['postcode'],
            'consignee_doorplate' => '',
            'consignee_areacode' => '',
            'consignee_telephone' => $address['phone'],
            'consignee_mobile' => '',
            'consignee_email' => '',
            'consignee_fax' => '',
            'consignee_certificatetype' => 'ID',
            'consignee_certificatecode' => '',
            'consignee_credentials_period' => '',
            'consignee_tariff' => $declare->tax_number ?? '',
        ];
        //包裹详情
        $invoice = [];
        foreach ($declare->items as $item) {
            $invoice[] = [
                'sku' => $item['sku'] ?: 'sku',
                'invoice_enname' => $item['en_name'],
                'invoice_cnname' => $item['cn_name'],
                'invoice_quantity' => $item['quantity'],
                'unit_code' => $item['unit'],
                'invoice_unitcharge' => $item['unit_value'] / 100,
                'invoice_currencycode' => $item['currency'],
                'hs_code' => $item['hs_code'],
                'invoice_note' => '',
                'invoice_url' => '',
                'invoice_info' => '',
                'invoice_material' => '',
                'invoice_spec' => '',
            ];
        }
        //额外服务
        $extraService = [
            'extra_servicecode' => '',
            'extra_servicevalue' => '',
            'extra_servicenote' => '',
        ];

        return [
            'reference_no' => $order->order_sn,
            'shipping_method' => $this->channelCode,
            'shipping_method_no' => '',
            'order_weight' => bcdiv($declare->weight, 1000, 3),
            'order_pieces' => 1,
            'cargotype' => 'W',
            'mail_cargo_type' => 4,
            'buyer_id' => '',
            'order_info' => $order->remark ?? '',
            'platform_id' => '',
            'custom_hawbcode' => '',
            //发件人信息
            'shipper' => $sender,
            //收件人信息
            'consignee' => $receiver,
            'invoice' => $invoice,
            //额外服务
            //'extra_service' => $extraService,
        ];

    }

    public function transformByBox(DeclareOrderBox $box)
    {
        $company = Company::query()->where('id', $this->companyId)->first();
        $order = $box->declareOrder->order;
        $warehouse = $order->warehouse;
        $address = $order->address;
        //发件人信息
        $sender = [
            'shipper_name' => preg_replace('/[\xf0-\xf7].{3}/', '', $order->user->name) ?: 'LT',
            'shipper_company' => $company->name,
            'shipper_countrycode' => 'CN',
            'shipper_province' => !empty($warehouse->province) ? $warehouse->province : 'none',
            'shipper_city' => !empty($warehouse->city) ? $warehouse->city : 'none',
            'shipper_street' => !empty($warehouse->street) ? $warehouse->street : 'none',
            'shipper_postcode' => $warehouse->postcode,
            'shipper_areacode' => '',
            'shipper_telephone' => $warehouse->phone,
            'shipper_mobile' => '',
            'shipper_email' => '',
            'shipper_fax' => '',
        ];
        //收件人信息
        $receiver = [
            'consignee_name' => $address['receiver_name'],
            'consignee_company' => $address['address'] ?? '',
            'consignee_countrycode' => strtoupper($address['country']['code']),
            'consignee_province' => !empty($address['province']) ? $address['province'] : $address['area']['name'] ?? '',
            'consignee_city' => !empty($address['city']) ? $address['city'] : $address['area']['name'] ?? '',
            'consignee_street' => ltrim(($address['door_no'] ?? '') . ' ' . (is_array($address['area'] ?? []) ? '' : ($address['area'] ?? '')) . ' ' . $address['street'] ?? ''),
            'consignee_postcode' => $address['postcode'],
            'consignee_doorplate' => '',
            'consignee_areacode' => '',
            'consignee_telephone' => $address['phone'],
            'consignee_mobile' => '',
            'consignee_email' => '',
            'consignee_fax' => '',
            'consignee_certificatetype' => 'ID',
            'consignee_certificatecode' => '',
            'consignee_credentials_period' => '',
            'consignee_tariff' => $box->tax_number ?? '',
        ];
        //包裹详情
        $invoice = [];
        foreach ($box->items as $item) {
            $invoice[] = [
                'sku' => $item['sku'] ?: 'sku',
                'invoice_enname' => $item['en_name'],
                'invoice_cnname' => $item['cn_name'],
                'invoice_quantity' => $item['quantity'],
                'unit_code' => $item['unit'],
                'invoice_unitcharge' => $item['unit_value'] / 100,
                'invoice_currencycode' => $item['currency'],
                'hs_code' => $item['hs_code'],
                'invoice_note' => '',
                'invoice_url' => '',
                'invoice_info' => '',
                'invoice_material' => '',
                'invoice_spec' => '',
            ];
        }
        //额外服务
        $extraService = [
            'extra_servicecode' => '',
            'extra_servicevalue' => '',
            'extra_servicenote' => '',
        ];

        return [
            'reference_no' => $box->box_sn,
            'shipping_method' => $this->channelCode,
            'shipping_method_no' => '',
            'order_weight' => bcdiv($box->weight, 1000, 3),
            'order_pieces' => 1,
            'cargotype' => 'W',
            'mail_cargo_type' => 4,
            'buyer_id' => '',
            'order_info' => $order->remark ?? '',
            'platform_id' => '',
            'custom_hawbcode' => '',
            //发件人信息
            'shipper' => $sender,
            //收件人信息
            'consignee' => $receiver,
            'invoice' => $invoice,
            //额外服务
            //'extra_service' => $extraService,
        ];
    }
}
