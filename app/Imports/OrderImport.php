<?php

namespace App\Imports;

use App\Lib\Code;
use App\Models\Country;
use App\Models\Order;
use App\Models\OrderLineItem;
use App\Models\OrderShippingAddress;
use App\Models\ShopModel;
use App\Models\ShopOrderLogs;
use App\Services\ExpressCompanies\KuaiDi\Exceptions\Exception;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\Importable;
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

class OrderImport implements ToModel, WithValidation, SkipsOnFailure, WithStartRow, WithMapping
{
    use Importable, SkipsFailures;

    public function model(array $row)
    {
        Validator::make($row, [
            'platform' => 'required|string',
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

        $shop = ShopModel::query()->where('platform', $row['platform'])->where('shop_name', $row['shop_name'])->where('customer_id', getCustomId())->first();
        if (empty($shop)) throw new AccidentException("The {$row['shop_name']} store not exist on the {$row['platform']} platform", Code::OPERATE_FAIL);

        $country = Country::query()->where('cn_name', $row['country'])->orWhere('en_name', $row['country'])->orWhere('code', $row['country'])->first();
        if (empty($country)) throw new AccidentException("The {$row['country']} country not support", Code::OPERATE_FAIL);

        $orderData = [
            'customer_id' => getCustomId(),
            'order_id' => $row['order_id'],
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

        $order = Order::query()->where(['order_id' => $row['order_id'], 'customer_id' => getCustomId(), 'shop_id' => $shop->id])->first();
        if(empty($order)) {
            $order = Order::query()->create($orderData);
        }

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
            OrderLineItem::query()->create($lineItems);
        }

        ShopOrderLogs::addLog([
            'order_id' => $order->id,
            'operator_type' => ShopOrderLogs::OPERATOR_TYPE_PULL_ORDER,
            'content' => '客户excel导入订单',
        ]);
    }

    public function startRow(): int
    {
        return 2;
    }


    public function map($row): array
    {
        if (count($row) < 23) {
            throw new AccidentException('The Excel data missing', Code::OPERATE_FAIL);
        }
        return [
            'platform' => trim($row[0]),
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

//    public function rules(): array
//    {
//        return [
//            '0' => function($attribute, $value, $onFailure) {
//                if(empty($value)) {
//                    $onFailure('店铺名称不能为空');
//                }
//            },
//            '1' => function($attribute, $value, $onFailure) {
//                if(empty($value)) {
//                    $onFailure('订单号不能为空');
//                }
//            },
//            '5' => function($attribute, $value, $onFailure) {
//                if(empty($value)) {
//                    $onFailure('客户名不能为空');
//                }
//            },
//            '6' => function($attribute, $value, $onFailure) {
//                if(empty($value)) {
//                    $onFailure('客户姓不能为空');
//                }
//            },
//            '7' => function($attribute, $value, $onFailure) {
//                if(empty($value)) {
//                    $onFailure('收件人国家不能为空');
//                }
//            },
//            '9' => function($attribute, $value, $onFailure) {
//                if(empty($value)) {
//                    $onFailure('收件人城市不能为空');
//                }
//            },
//            '10' => function($attribute, $value, $onFailure) {
//                if(empty($value)) {
//                    $onFailure('收件人详细地址不能为空');
//                }
//            },
//            '14' => function($attribute, $value, $onFailure) {
//                if(empty($value)) {
//                    $onFailure('收件人邮编不能为空');
//                }
//            },
//            '16' => function($attribute, $value, $onFailure) {
//                if(empty($value)) {
//                    $onFailure('产品SKU不能为空');
//                }
//            },
//            '18' => function($attribute, $value, $onFailure) {
//                if(empty($value)) {
//                    $onFailure('产品名称不能为空');
//                }
//            },
//            '19' => function($attribute, $value, $onFailure) {
//                if(empty($value)) {
//                    $onFailure('产品规格不能为空');
//                }
//            },
//            'price' => function($attribute, $value, $onFailure) {
//                if(empty($value)) {
//                    $onFailure('产品单价不能为空');
//                }
//            },
//            'quantity' => function($attribute, $value, $onFailure) {
//                if(empty($value)) {
//                    $onFailure('产品数量不能为空');
//                }
//            },
//        ];
//    }

//    public function sheets(): array
//    {
//        return [
//            0 => $this,
//        ];
//    }

//    public function onFailure(Failure ...$failures)
//    {
//        $this->failures = $failures;
//    }
}
