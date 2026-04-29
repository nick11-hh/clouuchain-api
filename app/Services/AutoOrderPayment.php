<?php

/**
 * Created by PhpStorm.
 * User: lin
 * Date: 2019-05-21
 * Time: 10:34
 */

namespace App\Services;

use App\Helper\CurrencyConverter;
use App\Lib\Code;
use App\Lib\Platform;
use App\Models\BalanceRecord;
use App\Models\Custom;
use App\Models\CustomBalance;
use App\Models\CustomConfig;
use App\Models\DeclareOrder;
use App\Models\Order;
use App\Models\Order as OrderModel;
use App\Models\OrderItemMapping;
use App\Models\ShopOrderLogs;
use App\Models\Stock;
use App\Services\Admin\PackageService;
use App\Services\Base\BalanceService;
use App\Services\Base\CommissionService;
use App\Services\ExpressCompanies\KuaiDi\Exceptions\Exception;
use Illuminate\Support\Facades\DB;
use App\Exceptions\AccidentException;


class AutoOrderPayment
{
    public function autoOrderPaymentProcess(int $customId, int $orderId, bool $verifyAutoPay = true)
    {
        $customConfigData = CustomConfig::query()->where('custom_id', $customId)->first();
        $isAutoPayment =  $customConfigData->is_auto_payment ?? 0;
        if ($verifyAutoPay && empty($isAutoPayment)) {
            //自动支付开关未开启,请联系客户到(客户端)系统配置开启后在重试
            throw new AccidentException('自动支付开关未开启,支付失败');
        }
        $customBalance = CustomBalance::query()->where('custom_id', $customId)->first();
        if (empty($customBalance)) {
            throw new AccidentException('客户钱包不存在，不能使用余额支付');
        }

        $order = OrderModel::query()->find($orderId);
        if (empty($order)) {
            throw new AccidentException('订单信息不存在');
        }

        if ($order->order_status != OrderModel::STATUS_QUOTED) {
            throw new AccidentException('该订单状态不是待支付状态,无法自动支付');
        }

        if (empty($order->logistics_provider)) {
            throw new AccidentException('未设置物流信息');
        }

        //物流费用
        $logisticsFee = $order->logistics_fee;

        //开启商品一口价 物流费用为sku的物流总报价
        if ($order->order_one_price === 1) {
            $logisticsFee = $order->sku_logistics_fee;
        }

        //支付金额=商品报价+物流报价+其他补价-优惠价格
        $amount = $order->vendor_price + $logisticsFee + $order->other_supplement_price - $order->favourable_price;

        $currencyConverter = new CurrencyConverter();

        //通过购物车购买的订单已经是美元，所以这不需要再进行换算
        //供应商改价
        if ($order->vendor_change_price > 0) {
            $amount = $order->vendor_change_price;
        }

//        $amount = $currencyConverter->reversedCurrenciesExchange($amount);

        //兼容信用额度 不做余额校验
        // $balance = $customBalance->balance / 100;
        // if ($amount > $balance) {
        //     throw new AccidentException("余额不足：订单付款金额为:USD-{$amount},账户余额为:{$balance}");
        // }
        $custom = Custom::query()->findOrFail($customId);

        $commission = new CommissionService($customId);
        DB::beginTransaction();
        try {
            // 操作支付，扣除用户余额
            (new BalanceService($customId))->setRelationId($order->id)->deduction($amount, BalanceRecord::SOURCE_ORDER_PAY, $order->name ? $order->name : $order->order_id);

            // 标记为支付状态,记录支付金额
            $order->update([
                'currency' => DeclareOrder::CURRENCY_USD,
                'order_status' => OrderModel::STATUS_PENDING,
                'financial_status' => OrderModel::FINANCIAL_STATUS_PAID, //财务状态 已支付
                'paymented_at' => now(),
                'payment_price' => $amount,
                'refund_price' => 0,
            ]);

            //新增一条支付日志
            $logData = [
                'order_id' => $order->id,
                'operator_type' => ShopOrderLogs::OPERATOR_TYPE_PAYMENTED,
                'content' => '订单设为已付款，金额' . $amount,
            ];
            ShopOrderLogs::addLog($logData);

            //订单佣金
            $commission->increase($amount, $custom['commission_rate'], $order->order_id);

            //使用客户库存 锁定客户库存
            // if ($order->use_customer_stock) {
            //     $this->lockCustomerStocks($order);
            // }

            $content = "订单付款成功，扣除客户金额：$amount USD";
            //记录日志
            ShopOrderLogs::addLog([
                'order_id' => $order->id,
                'operator_type' => ShopOrderLogs::OPERATOR_TYPE_PAYMENT,
                'content' => $content,
            ]);

            // 创建包裹
            (new PackageService())->createByOrder($order);

            DB::commit();
        } catch (\Throwable $e) {
            info('订单支付失败:', ['error' => $e->getMessage()]);
            DB::rollBack();
            throw new AccidentException('订单支付失败：' . $e->getMessage());
        }
        return true;
    }

    /**
     * 锁定客户库存
     */
    public function lockCustomerStocks($order)
    {
        if (empty($order)) {
            return true;
        }

        $adminOrderService = new \App\Services\Admin\OrderService(new Order());

        $order->lineItems->each(function ($item) use ($order, $adminOrderService) {
            $mapping = OrderItemMapping::query()->with('goodsSku')->where('platform_variant_id', $item['variant_id'])->first();
            if (empty($mapping)) throw new AccidentException('订单未映射本地商品', Code::OPERATE_FAIL);

            //查询客户库存
            $stock = Stock::query()->where([
                'sku_id' => $mapping->goods_sku_id,
                'custom_id' => $order->customer_id,
            ])->first();

            if (empty($stock)) {
                throw new AccidentException('客户库存不足', Code::OPERATE_FAIL);
            }

            if ($stock->quantity < $item->quantity) {
                throw new AccidentException('客户库存不足'. ':' . $stock->spec_name, Code::OPERATE_FAIL);
            }

            //锁定库存
            $adminOrderService->orderItemUseStock($order, $item, $stock);
        });

        return true;
    }

}
