<?php

namespace App\Exports;

use App\Helper\CurrencyConverter;
use App\Lib\Platform;
use App\Models\Order;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class OrderDianxiaomiExport implements FromArray, WithHeadings
{

    protected $query;

    public function __construct($query)
    {
        $this->query = $query;
    }

    public function array(): array
    {
        $query = $this->query->with([
            'lineItems.mapping.goodsSku',
            'shop',
            'shippingAddress',
            'expressLine:id,name,en_name',
            'warehouse'
        ]);
        $orders = $query->latest()->get();

        if (empty($orders)) {
            return [];
        }

        $currencyConverter = new CurrencyConverter();
        $data = [];
        $orders->each(function ($order) use (&$data, $currencyConverter) {
            $onePrice = $order->order_one_price;
            $order->lineItems->each(function ($item) use (&$data, $order, $onePrice, $currencyConverter) {
                $logisticsFee = $order->logistics_fee;

                $productUrl = $item->product_url ?? '';
                $shopUrl = $order->shop->shop_url ?? '';

                if (empty($productUrl)) {
                    switch ($order->platform) {
                        case "shopify":
                            $uri = str_replace([' ', '.'], '-', $item->title); // 空格 和 . 转 横杠
                            $uri = preg_replace(["/'/", '/:/i', '/@/', '/\[/', '/\]/'], '', $uri); // 去除特殊符号
                            $uri = preg_replace(['/----/', '/---/', '/--/'], '-', $uri);
                            $productUrl = $shopUrl . "/products/" . $uri;
                            break;
                        case "woocommerce":
                            $uri = str_replace([' ', '%'], '-', $item->name); // 空格 和 % 转 横杠
                            $uri = preg_replace(['/----/', '/---/', '/--/'], '-', $uri);

                            $productUrl = $shopUrl . "/product/" . $uri;
                            break;
                    }

                    $productUrl = !(strpos($productUrl, 'http')) && !empty($productUrl) ? 'http://' . $productUrl : $productUrl;
                }

                $data[] = [
                    $order->order_id,  //订单号
                    $order->shop->shop_name ?? '', //店铺账号
                    $item->variant_id, //variant_id
                    $item->title, //属性
                    $item->quantity, //数量
                    $item->price, //单价 报价金额
                    $logisticsFee, //总运费
                    'USD', //币种
                    $order->expressLine->name ?? '', //买家指定物流
                    $order->warehouse->warehouse_name ?? '', //发货仓库
                    ($order->shippingAddress->first_name ?? '') . ' ' . ($order->shippingAddress->last_name ?? ''), //买家姓名
                    $order->shippingAddress->address1 ?? '', //地址1
                    $order->shippingAddress->address2 ?? '', //地址2
                    $order->shippingAddress->city ?? '', //城市
                    $order->shippingAddress->province ?? '', //省/州
                    $order->shippingAddress->country_code ?? '', //国家二字码
                    $order->shippingAddress->zip ?? '', //邮编
                    $order->shippingAddress->phone ?? '', //电话
                    $order->shippingAddress->phone ?? '', //手机
                    $order->shippingAddress->email ?? '', //E-mail
                    $order->shippingAddress->tax ?? '', //买家税号
                    $order->shippingAddress->address2 ?? '', //门牌号
                    $order->shippingAddress->company ?? '', //公司名
                    '客户ID ' . $order->shop?->customer_id ?? '', //订单备注 使用客户ID
                    $item->imgs[0] ?? '', //图片网址
                    $productUrl, //出售链接
                    '', //中文报关名
                    '', //英文报关名
                    '', //申报金额(USD)
                    '', //申报重量(g)
                    '', //材质
                    '', //用途
                    '', //海关编码
                    '', //报关属性
                    '', //卖家税号
                    (string)$order->created_at, //下单时间(北京时间)
                    $order->name, //客服备注-平台编号字段
                    $order->warehouse_remark ?? '', //拣货备注-仓库备注字段
                ];
            });
        });
        return $data;
    }

    public function headings(): array
    {
        return [
            '订单号',
            '店铺账号',
            'sku',
            '属性',
            '数量',
            '单价',
            '总运费',
            '币种',
            '买家指定物流',
            '发货仓库',
            '买家姓名',
            '地址1',
            '地址2',
            '城市',
            '省/州',
            '国家二字码',
            '邮编',
            '电话',
            '手机',
            'E-mail',
            '买家税号',
            '门牌号',
            '公司名',
            '订单备注',
            '图片网址',
            '出售链接',
            '中文报关名',
            '英文报关名',
            '申报金额(USD)',
            '申报重量(g)',
            '材质',
            '用途',
            '海关编码',
            '报关属性',
            '卖家税号',
            '下单时间(北京时间)',
            '客服备注',
            '拣货备注',
        ];
    }
}
