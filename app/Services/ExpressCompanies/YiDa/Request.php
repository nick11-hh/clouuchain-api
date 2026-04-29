<?php


namespace App\Services\ExpressCompanies\YiDa;


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
        $order = $declare->order;
        $warehouse = $order->warehouse;
        $address = $order->address;
        //发件人信息
        $sender = [
            'shipper_name' => '魏訾强',//preg_replace('/[\xf0-\xf7].{3}/', '', $order->user->name),
            'shipper_company' => '上海必购家信息技术有限公司',//$company->name,
            'shipper_countrycode' => 'CN',
            'shipper_province' => '上海市',//!empty($warehouse->province) ? $warehouse->province : 'none',
            'shipper_city' => '上海市',//!empty($warehouse->city) ? $warehouse->city : 'none',
            'shipper_street' => '松江区九亭镇九泾路1000号',//!empty($warehouse->street) ? $warehouse->street : 'none',
            'shipper_postcode' => '201600',//$warehouse->postcode,
            'shipper_areacode' => '',
            'shipper_telephone' => '13761494620',//$warehouse->phone,
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
                'sku' => '',
                'invoice_enname' => $item['en_name'],
                'invoice_cnname' => $item['cn_name'],
                'invoice_quantity' => $item['quantity'],
                'unit_code' => $item['unit'],
                'invoice_unitcharge' => $item['unit_value'] / 100,
                'invoice_currencycode' => $item['currency'],
                'hs_code' => '',
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
            'VatNum' => '',
            'IossNum' => 'IM3800015204',
            'return_sign' => 'Y',
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
        $order = $box->declareOrder->order;
        $warehouse = $order->warehouse;
        $address = $order->address;
        //发件人信息
        $sender = [
            'shipper_name' => '魏訾强',//preg_replace('/[\xf0-\xf7].{3}/', '', $order->user->name),
            'shipper_company' => '上海必购家信息技术有限公司',//$company->name,
            'shipper_countrycode' => 'CN',
            'shipper_province' => '上海市',//!empty($warehouse->province) ? $warehouse->province : 'none',
            'shipper_city' => '上海市',//!empty($warehouse->city) ? $warehouse->city : 'none',
            'shipper_street' => '松江区九亭镇九泾路1000号',//!empty($warehouse->street) ? $warehouse->street : 'none',
            'shipper_postcode' => '201600',//$warehouse->postcode,
            'shipper_areacode' => '',
            'shipper_telephone' => '13761494620',//$warehouse->phone,
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
                'sku' => '',
                'invoice_enname' => $item['en_name'],
                'invoice_cnname' => $item['cn_name'],
                'invoice_quantity' => $item['quantity'],
                'unit_code' => $item['unit'],
                'invoice_unitcharge' => $item['unit_value'] / 100,
                'invoice_currencycode' => $item['currency'],
                'hs_code' => '',
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
            'VatNum' => '',
            'IossNum' => 'IM3800015204',
            'return_sign' => 'Y',
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
