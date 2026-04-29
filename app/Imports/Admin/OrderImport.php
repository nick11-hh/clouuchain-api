<?php

namespace App\Imports\Admin;

use App\Lib\Code;
use App\Models\Country;
use App\Models\Order;
use App\Models\OrderLineItem;
use App\Models\OrderShippingAddress;
use App\Models\ShopModel;
use App\Models\Custom;
use App\Models\ShopOrderLogs;
use App\Services\ExpressCompanies\KuaiDi\Exceptions\Exception;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Validators\Failure;
use App\Exceptions\AccidentException;

/**
 * 管理端订单导入
 * Class OrderImport
 * @package App\Imports\Admin
 * @author DonnyLiu <2365057581@qq.com>
 * @date 2024/9/13 15:49
 */
class OrderImport implements ToModel, WithValidation, SkipsOnFailure, WithStartRow, WithMapping, SkipsEmptyRows
{
    use Importable, SkipsFailures;

    public function model(array $row)
    {
        Validator::make($row, [
            'customer_id' => 'required|integer',
            'shop_name' => 'required|string',
            'order_id' => 'required|string',
            'first_name' => 'required|string',
            'last_name' => 'sometimes|nullable|string',
            'country' => 'required|string',
            'city' => 'required|string',
            'address1' => 'required|string',
            'zip' => 'required|string',
            'product_name' => 'required|string',
            'variant_title' => 'required|string',
            'price' => 'required|string',
            'quantity' => 'required|string',
            'product_url' => 'required|string',
        ])->validate();

        $custom = Custom::query()->whereKey($row['customer_id'])->first();
        if (empty($custom)) {
            throw new AccidentException("客户ID：{$row['customer_id']} 不存在", Code::OPERATE_FAIL);
        }

        $shop = ShopModel::query()->where('shop_name', $row['shop_name'])->where('customer_id', $row['customer_id'])->first();
        if (empty($shop)){
            throw new AccidentException("店铺名称：{$row['shop_name']} 不存在", Code::OPERATE_FAIL);
        }

        $country = Country::query()->where('cn_name', $row['country'])->orWhere('en_name', $row['country'])->orWhere('code', $row['country'])->first();
        if (empty($country)) {
            throw new AccidentException("国家：{$row['country']}，不支持", Code::OPERATE_FAIL);
        }

        $orderData = [
            'customer_id' => $row['customer_id'],
            'order_id' => $row['order_id'] . '_' . $shop->id,
            'shop_id' => $shop->id,
            'platform' => $shop->platform,
            'currency' => 'USD',
            'order_status' => Order::STATUS_QUOTE_NO,
            'custom_order_id' => generateOrderId(),
            'payment_info' => [],
            'name' => $row['name'],
            'remark' => $row['remark'],
            'current_total_price' => $row['current_total_price'] ?: 0,
        ];

        $exist = Order::query()->where(['order_id' => $row['order_id']])->first();

        if (!empty($exist)) throw new AccidentException('订单号' . $row['order_id'] . '已存在');

        //保存订单基础数据
        $order = Order::query()->create($orderData);

        $shippingData = [
            'order_id' => $order->id,
            'first_name' => $row['first_name'] ?? '',
            'last_name' => $row['last_name'],
            'name' => $row['first_name'] . ' ' . $row['last_name'],
            'country' => $row['country'],
            'country_code' => strtoupper($country->code),
            'province' => $row['province'],
            'city' => $row['city'],
            'address1' => $row['address1'],
            'address2' => $row['address2'],
            'phone' => $row['phone'],
            'email' => $row['email'],
            'zip' => $row['zip'],
            'tax' => $row['tax']
        ];

        $shipping = OrderShippingAddress::query()->where('order_id', $order->id)->first();
        if(empty($shipping)) {
            //保存订单收货地址数据
            OrderShippingAddress::query()->create($shippingData);
        }

        $lineItems = [
            'order_id' => $order->id,
            'name' => $row['product_name'],
            'title' => $row['product_name'],
            'variant_title' => $row['variant_title'],
            'variant_id' => $row['sku'],
            'sku' => $row['sku'],
            'quantity' => $row['quantity'],
            'price' => $row['price'] ?: 0,
            'total_discount' => 0,
            'product_url' => $row['product_url'],
            'imgs' => empty($row['image_url']) ? '' : [$row['image_url']]
        ];

        $item = OrderLineItem::query()->where(['order_id' => $order->id, 'sku' => $row['sku']])->first();
        if(empty($item)) {
            //保存订单sku数据
            OrderLineItem::query()->create($lineItems);
        }

        ShopOrderLogs::addLog([
            'order_id' => $order->id,
            'operator_type' => ShopOrderLogs::OPERATOR_TYPE_PULL_ORDER,
            'content' => '管理端excel导入订单',
        ]);
    }

    public function startRow(): int
    {
        return 2;
    }


    public function map($row): array
    {
        if (count($row) < 24) {
            throw new AccidentException('缺少Excel数据', Code::OPERATE_FAIL);
        }

        return [
            'customer_id' => trim($row[0]),
            'shop_name' => trim($row[1]),
            'order_id' => trim($row[2]),
            'name' => trim($row[3]),
            'current_total_price' => trim($row[4]),
            'remark' => trim($row[5]),
            'first_name' => trim($row[7]),
            'last_name' => trim($row[6]),
            'country' => trim($row[8]),
            'province' => trim($row[9]),
            'city' => trim($row[10]),
            'address1' => trim($row[11]),
            'address2' => trim($row[12]),
            'phone' => trim($row[13]),
            'email' => trim($row[14]),
            'zip' => trim($row[15]),
            'tax' => trim($row[16]),
            'sku' => trim($row[17]),
            'image_url' => trim($row[18]),
            'product_name' => trim($row[19]),
            'variant_title' => trim($row[20]),
            'price' => trim($row[21]),
            'quantity' => trim($row[22]),
            'product_url' => trim($row[23] ?? ''),
        ];
    }

    public function rules(): array
    {
        return [];
//        return [
//            'shop_name' => 'required|string',
//            'order_id' => 'required|string',
//            'first_name' => 'required|string',
//            'last_name' => 'required|string',
//            'country' => 'required|string',
//            'city' => 'required|string',
//            'address1' => 'required|string',
//            'zip' => 'required|string',
//            'product_name' => 'required|string',
//            'variant_title' => 'required|string',
//            'price' => 'required|string',
//            'quantity' => 'required|string',
//            'product_url' => 'required|string',
//        ];
    }

}
