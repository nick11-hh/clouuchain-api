<?php


namespace App\Services\ThirdPart\HuaLei;


use App\Models\Company;
use App\Models\Order;

class Request
{

    public Order $order;

    public $companyId;

    public function __construct(Order $order, int $companyId)
    {
        $this->companyId = $companyId;
        $this->order = $order;
    }

    public function transform($customer)
    {
        $company = Company::query()->where('id', $this->companyId)->first();
        $warehouse = $this->order->warehouse;
        $address = $this->order->address;
        $packageList = $this->order->packages;
        $items = $volumeItem =  [];
        foreach ($packageList as $tempPackage) {
            $items[] = [
                'invoice_amount' => $tempPackage->package_value / 100,
                'invoice_pcs' => '1',
                'invoice_title' => 'invoice title',
                'invoice_weight' => $tempPackage->package_weight / 1000,
                'sku' => 'sku',
                'sku_code' => 'sku_code',
                'hs_code' => 'hs_code',
                'transaction_url' => '',
                'invoiceunit_code' => '',
                'invoice_imgurl' => '',
                'invoice_brand' => $tempPackage->brand_name,
                'invoice_rule' => '',
                'invoice_currency' => 'CNY',
                'invoice_taxno' => '',
                'origin_country' => '',
                'invoice_material' => '',
                'invoice_purpose' => '',
            ];
            $volumeItem[] = [
                'volume_height' => $tempPackage->height / 100,
                'volume_length' => $tempPackage->length / 100,
                'volume_width' => $tempPackage->width / 100,
                'volume_weight' => $tempPackage->package_weight / 1000,
            ];
        }
        return [
            'buyerid' => '',
            'order_piece' => $this->order->packages()->count(),
            'consignee_mobile' => '',
            'order_returnsign' => 'N',
            'trade_type' => 'ZYXT',
            'duty_type' => 'DDU',
            'battery_type' => '',
            'consignee_name' => $this->order->address['receiver_name'],
            'consignee_companyname' => $company->name,
            'consignee_address' => $this->order->fullAddressString,
            'consignee_telephone' => '0410310530',//$this->order->address['phone'],
            'country' => $address['country']['code'],
            'consignee_state' => "ACT",!empty($address['province']) ? $address['province'] : $address['area']['name'],
            'consignee_city' => !empty($address['city']) ? $address['city'] : $address['area']['name'],
            'consignee_suburb' => '',
            'consignee_postcode' => $address['postcode'],
            'consignee_passportno' => '',
            'consignee_email' => '',
            'consignee_taxno' => '',
            'consignee_streetno' => '',
            'consignee_doorno' => '',
            'shipper_name' => 'test warehouse',//$warehouse->wareohuse_name,
            'shipper_companyname' => 'JiYun',
            'shipper_address1' => '518100',//$warehouse->address,
            'shipper_address2' => '',
            'shipper_city' => 'shenzhen',//$warehouse->city,
            'shipper_state' => 'GD',//$warehouse->province,
            'shipper_postcode' => '518100',//$warehouse->postcode,
            'shipper_country' => 'CN',
            'shipper_telephone' => '5654354433',//$warehouse->phone,
            'shipper_taxnotype' => 'IOSS',
            'shipper_taxno' => '',
            'customer_id' => $customer['customer_id'],
            'customer_userid' => $customer['customer_userid'],
            'order_customerinvoicecode' => $this->order->order_sn,
            'product_id' => '1801',
            'weight' => $this->order->actual_weight / 1000,
            'product_imagepath' => '',
            'order_transactionurl' => '',
            'order_cargoamount' => (int)($this->order->packages->sum('package_value')) / 100,
            'order_insurance' => (int)($this->order->insurance_fee) / 100,
            'cargo_type' => 'P',
            'order_customnote' => '',
            'orderInvoiceParam' => $items,
            'orderVolumeParam' => $volumeItem,
        ];
    }

}
