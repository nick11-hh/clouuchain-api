<?php

namespace App\Services\Client;

use App\Exceptions\AccidentException;
use App\Jobs\GenerateInvoice;
use App\Lib\Code;
use App\Models\BalanceRecord;
use App\Models\Country;
use App\Models\CreditCardRechargeRecord;
use App\Models\CreditCardTypes;
use App\Models\Custom;
use App\Models\CustomBalance;
use App\Models\InvoiceRecords;
use App\Models\User;
use App\Models\UserAddress;
use App\Models\WebHookLogs;
use App\Models\WorldCountries;
use App\Services\ApiResponseService;
use App\Services\PaymentPlatform\FortySeas\RequestApi;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use App\Services\Traits\InvoiceTrait;
use App\Services\Base\BalanceService;

class FortySeasService extends BaseService
{
    use InvoiceTrait;

    private $client;
    private $requestApi;

    public function __construct()
    {
        $this->client = new Client();
        $this->requestApi = new RequestApi();
    }


    /**
     * 创建40Seas订单URL
     * @param $amount
     * @param $currency
     * @return \Illuminate\Http\JsonResponse|mixed
     */
    public function checkout($amount, $currency): mixed
    {
        $user = auth('client')->user();
        $buyerId = $user->buyer_id;
        $date = date('Y-m-d H:i:s');
        // 获取信用额度
        $custom = Custom::where('main_user_id', $user->id)->first();
        // 创建买家
        if (empty($buyerId)) {
            $createBuyerResult = $this->createBuyer($user);
            if (!$createBuyerResult) {
                throw new AccidentException('fail', Code::UNKOWN_ERROR);
            }
        }

        $externalId = generateUniqueExternalId('credit_card');

        $checkout = $this->requestApi->checkout(
            (string)$externalId,
            (string)$buyerId,           // 买家信息
            (float)$amount,          // 金额
            (string)$currency,        // 货币类型
            'pending',          // 订单状态
        );

        if (empty($checkout['id'])) {
            throw new AccidentException('fail', Code::UNKOWN_ERROR);
        }
        $result = $this->requestApi->createIntentCheckout(
            $checkout['id'],
        );
        if (empty($result['originalUrl'])) {
            throw new AccidentException('fail', Code::UNKOWN_ERROR);
        }

        $insertResult = CreditCardRechargeRecord::insert([
            'user_id' => $user->id,
            'custom_id' => $custom['id'],
            'type' => CreditCardRechargeRecord::TYPE_40SEAS,
            'external_id' => $externalId,
            'transaction_id' => $checkout['id'],
            'amount' => $amount,
            'currency' => $currency,
            'status' => CreditCardRechargeRecord::STATUS_PENDING,
            'created_at' => $date,
            'updated_at' => $date,
        ]);

        // 检查插入是否成功
        if (!$insertResult) {
            Log::error('Credit card recharge record insertion failed', [
                'external_id' => $externalId,
                'transaction_id' => $checkout['id']
            ]);
            throw new AccidentException('fail', Code::UNKOWN_ERROR);
        }
        return $result['originalUrl'];
    }

    /**
     * 创建40Seas买家
     * @param $user
     * @return bool|string
     */
    public function createBuyer($user): bool|string
    {
        if (empty($user->phone_area_code)) {
            Log::error("用户 {$user->id} 缺少有效的电话区号");
            return false;
        }
        $code = ltrim($user->phone_area_code, '+');
        if ($code === '') {
            Log::error("用户 {$user->id} 提供的电话区号格式非法: " . $user->phone_area_code);
            return false;
        }
        $custom = Custom::where('main_user_id', $user->id)->first();
        $worldCountry = WorldCountries::where('callingcode', $code)->first();
        if (!$worldCountry) {
            Log::error("用户 {$user->id} 所属地区未找到对应国家码: +{$code}");
            return false;
        }

        $country_count = WorldCountries::where('callingcode', $code)->count();
        if ($country_count > 1) {
            Log::error("用户 {$user->id} 存在多个国家码: +{$code}");
            //当存在多个国家码时,用custom表中的country_code定位国家
            $country = Country::query()->where('id', $custom->country_code)->first();
            $worldCountry['code'] = $country['code'];
        }

        if (!$custom || empty($custom->custom_name)) {
            Log::error("用户 {$user->id} 对应 custom 记录不存在或 custom_name 为空");
            return false;
        }

        $buyerData = $this->requestApi->createBuyer(
            strtoupper($worldCountry['code']),
            'ein',
            str_pad(rand(100000000, 999999999), 9, '0', STR_PAD_LEFT),
            $custom['custom_name'] . $user->id,
            $user->id,
        );
        if (empty($buyerData)) {
            Log::error("用户 {$user->id} 创建 buyer 失败");
            return false;
        }

        $user->update([
            'buyer_id' => $buyerData['id'],
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return $buyerData['id'];
    }

    /**
     * 验证 webhook 签名
     * @param string $svixId
     * @param string $svixTimestamp
     * @param string $svixSignature
     * @param string $body
     * @return bool
     */
    public function verifyWebhookSignature(string $svixId, string $svixTimestamp, string $svixSignature, string $body): bool
    {
        if (empty($svixId) || empty($svixTimestamp) || empty($svixSignature) || empty($body)) {
            return false;
        }

        $CreditCardType = CreditCardTypes::where('name', CreditCardTypes::TYPE_40SEAS)->where('status', CreditCardTypes::STATUS_NORMAL)->first();
        if (empty($CreditCardType['webhook_secret'])) {
            Log::info('40Seas API webhook_secret is empty.');
            return false;
        }
        // 获取签名密钥
        $signingSecret = $CreditCardType['webhook_secret'];
        if (empty($signingSecret)) {
            Log::error('40Seas webhook secret not configured');
            return false;
        }

        // 构建签名内容
        $signedContent = $svixId . '.' . $svixTimestamp . '.' . $body;

        // 提取密钥（去掉 whsec_ 前缀）
        $secretKey = substr($signingSecret, 6); // 去掉 "whsec_" 前缀
        $secretBytes = base64_decode($secretKey);
        // 计算期望的签名
        $expectedSignature = base64_encode(hash_hmac('sha256', $signedContent, $secretBytes, true));

        // 解析 svix-signature 头部中的签名
        $signatures = explode(' ', $svixSignature);

        foreach ($signatures as $signature) {
            // 移除版本前缀（如 v1,）
            $parts = explode(',', $signature);
            if (count($parts) === 2) {
                $signatureValue = $parts[1];
                // 使用 hash_equals 进行安全比较
                if (hash_equals($expectedSignature, $signatureValue)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * 40Seas webhook
     * @param string $type
     * @param array $data
     * @param string $svixId
     * @param string $body
     * @param string $headers
     * @return array
     */
    public function webhook(string $type, array $data, string $svixId, string $body, string $headers): array
    {
        $fortySeasName = CreditCardRechargeRecord::getPlatformName(CreditCardRechargeRecord::TYPE_40SEAS);
        // 使用 SETNX 实现分布式锁
        $lockKey = 'webhook:' . $fortySeasName . ':' . $svixId;
        $lockTimeout = 30; // 锁超时时间（秒）
        // 尝试获取锁
        $lockAcquired = Redis::set($lockKey, $svixId, 'EX', $lockTimeout, 'NX');

        if (!$lockAcquired) {
            // 获取锁失败，说明正在处理中
            Log::info('Webhook is being processed by another instance', ['svix_id' => $svixId]);
            return [];
        }
        try {
            $webHookLogsExists = WebHookLogs::where('platform', $fortySeasName)->where('external_id', $svixId)->where('status', WebHookLogs::STATUS_SUCCESS)->count();
            if ($webHookLogsExists >= 3) {
                return [];
            }
            $webHookLogsId = WebHookLogs::insertGetId([
                'platform' => $fortySeasName,
                'external_id' => $svixId,
                'event_type' => $type,
                'request_headers' => $headers,
                'request_body' => $body,
                'status' => WebHookLogs::STATUS_PENDING,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $result = false;
            DB::beginTransaction();
            try {
                switch ($type) {
                    // 支付到账
                    case 'payout.ready':
                        // 解析body获取额外参数
                        $parsedBody = json_decode($body, true);
                        $amount = $parsedBody['data']['amount'] ?? 0;
                        $grossAmount = $parsedBody['data']['grossAmount'] ?? 0;

                        $result = $this->payoutReady($data, $amount, $grossAmount);
                        break;
                }
                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                Log::error('Webhook processing failed: ' . $e->getMessage());
            }

            // 更新日志状态
            $status = $result ? WebHookLogs::STATUS_SUCCESS : WebHookLogs::STATUS_FAIL;
            WebHookLogs::where('id', $webHookLogsId)->update([
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            Redis::del($lockKey);
        } catch (\Throwable $e) {
            Log::error('Webhook processing failed: ' . $e->getMessage());
            Redis::del($lockKey);
        }
        return [];
    }

    /**
     * 支付到账
     * @param array $data
     * @param float $amount
     * @param float $grossAmount
     * @return bool
     */
    public function payoutReady(array $data, float $amount, float $grossAmount): bool
    {
        $creditCardRechargeRecord = CreditCardRechargeRecord::where('type', CreditCardRechargeRecord::TYPE_40SEAS)
            ->where('transaction_id', $data['checkout'])
            ->where('status', CreditCardRechargeRecord::STATUS_PENDING)
            ->first();
        if (empty($creditCardRechargeRecord)) {
            Log::info('credit card recharge record not found：' . $data['checkout']);
            return false;
        }
        // 更新充值状态记录
        CreditCardRechargeRecord::where('id', $creditCardRechargeRecord['id'])->update([
            'status' => CreditCardRechargeRecord::STATUS_SUCCESS,
            'amount_received' => $amount,
            'gross_amount' => $grossAmount,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        //客户钱包充值
        $custom = Custom::where('main_user_id', $creditCardRechargeRecord['user_id'])->lockForUpdate()->first();
        // 使用传入的amount参数，如果为0则使用原始记录中的金额
        $useAmount = ($amount > 0) ? $amount : $creditCardRechargeRecord['amount'];
        Custom::where('id', $custom['id'])->increment('cumulative_top_up', $useAmount);
        //因为表中是按100倍存储的，所以要乘100
        $balanceAmount = bcmul($useAmount, 100);
        $customBalance = CustomBalance::where('custom_id', $custom['id'])->lockForUpdate()->first();
        //交易流水
        BalanceRecord::query()->create([
            'custom_id' => $custom['id'],
            'type' => BalanceRecord::CHANGE_INCREASE,
            'source_type' => BalanceRecord::SOURCE_CREDIT_CARD_RECHARGE,
            'amount' => $balanceAmount,
            'after_change_balance' => bcadd($customBalance['balance'], $balanceAmount) ?? 0,
            'relation_id' => 0,
            'relation_type' => '',
            'order_sn' => '',
            'remark' => '',
            'serial_no' => BalanceRecord::getSerialNo(BalanceRecord::SOURCE_CREDIT_CARD_RECHARGE),
            'out_serial_no' => '',
            'actual_amount' => 0, //仅在调整信用额度和冻结额度的时候有值
            'charge_type_id' => 0,//仅在source_type=9有值
            'operate_admin_id' => 0,
            'attachment_files' => [],
        ]);
        CustomBalance::where('custom_id', $custom['id'])->increment('balance', $balanceAmount);
        return true;
    }
}
