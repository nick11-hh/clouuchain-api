<?php

namespace App\Services;

use App\Exceptions\AccidentException;
use App\Helper\CosUtil;
use App\Models\Admin;
use App\Models\BalanceRechargeRecord;
use App\Models\CompanyProp;
use App\Models\DaiGouOrder;
use App\Models\ExchangeRate;
use App\Models\MiniprogramSetting;
use App\Models\Order;
use App\Models\OrderPaymentUuid;
use App\Models\PaymentServiceFeeConfig;
use App\Models\SerialNo;
use App\Models\Traits\HasCompanyId;
use App\Models\TransactionRecord;
use App\Models\User;
use App\Models\WechatAppConfig;
use App\Models\WeChatOAConfig;
use App\Models\WechatPayment;
use App\Models\WeChatWorkConfig;
use App\Services\Admin\PaymentServiceFeeService;
use App\Services\Payment\WechatService;
use EasyWeChat\Factory;
use EasyWeChat\Kernel\Exceptions\Exception;
use EasyWeChat\Kernel\Http\StreamResponse;
use EasyWeChat\MiniProgram\Application as MiniProgram;
use EasyWeChat\Payment\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class WechatServices
{
    use RechargeTrait;
    use GrowthValueTrait,
        DaiGouOrderTrait {
        GrowthValueTrait::payCallback insteadof DaiGouOrderTrait;
        DaiGouOrderTrait::payCallback as dgOrderPayCallback;
    }

    public const JSAPI = 1; //小程序端支付
    public const NATIVE = 2; // 二维码支付
    public const MWEB = 3; // h5支付
    public const APP = 4; // APP 支付

    public const MAP = [
        self::JSAPI => 'JSAPI',
        self::NATIVE => 'NATIVE',
        self::MWEB => 'MWEB',
        self::APP => 'APP',
    ];

    public const INVITE = 'IN';
    public const CHANNEL = 'CH';
    public const SELF_BIND = 'BI';
    /**
     * @var MiniProgram
     */
    protected $miniProgram;

    /**
     * @var Application
     */
    protected $payment;

    /**
     * @var \EasyWeChat\OfficialAccount\Application
     */
    protected $oa;

    /**
     * @var \EasyWeChat\Work\Application
     */
    protected \EasyWeChat\Work\Application $weWork;

    protected $companyId;

    protected int $isOversea;

    protected $notifyUrl;

    //此处添加了小程序表和支付配置表以及模板表之后进行相应修改
    public function __construct($companyId = null, bool $required = true)
    {
        app('log')->info('初始化微信服务配置');
        $this->companyId = $companyId ?? self::getCompanyId();

        if (!$this->companyId) {
            app('log')->info('初始化微信没有有效的公司id');
            eRet('没有有效的公司标识');
        }
        info('当前公司ID', ['id' => $this->companyId]);

        $this->setMiniProgramFromComID($this->companyId, $required);
        $this->setWechatPaymentFromComID($this->companyId, $required);
    }

    /**
     * 根据 company id 初始化小程序配置
     * @param $companyId
     * @param bool $required
     * @throws AccidentException
     */
    public function setMiniProgramFromComID($companyId, bool $required = true)
    {
        $miniProgramSetting = MiniprogramSetting::where('company_id', $companyId)->first();

        if (!$miniProgramSetting) {
            if (! $required) {
                return;
            }

            eRet('该公司暂无小程序配置');
        }

        try {
            $miniProgramConfig = [
                'app_id' => $miniProgramSetting->app_id,
                'secret' => $miniProgramSetting->secret,
                'response_type' => 'array',
                'log' => [
                    'default' => app()->environment('production') ? 'production' : 'dev',
                    'channels' => [
                        // 测试环境
                        'dev' => [
                            'driver' => 'single',
                            'path' => storage_path('logs/wechat/wechat-mp.log'),
                            'level' => 'debug',
                        ],
                        // 生产环境
                        'production' => [
                            'driver' => 'daily',
                            'path' => storage_path('logs/wechat/wechat-mp.log'),
                            'level' => 'debug',
                        ],
                    ],
                ],
            ];

            $this->miniProgram = Factory::miniProgram($miniProgramConfig);
            $this->isOversea = $miniProgramSetting['is_oversea'];
        } catch (\Exception $exception) {
            info('当前公司的小程序配置信息有误', $miniProgramSetting->toArray());

            eRet('该公司小程序配置信息有误');
        }
    }

    /**
     * 根据 company id 初始化微信支付配置
     * @throws \App\Exceptions\AccidentException
     */
    public function setWechatPaymentFromComID($companyId, bool $required = true)
    {
        $paymentConfig = WechatPayment::where('company_id', $companyId)->first();

        if (!$paymentConfig) {
            if (app()->runningInConsole()) {
                return false;
            }

            if (! $required) {
                return false;
            }

            eRet('尚未配置微信支付');
        }

        // 预下载支付证书
        CosUtil::downloadCert($paymentConfig->cert_path);
        CosUtil::downloadCert($paymentConfig->key_path);

        $paymentConfig = [
            // 必要配置
            'app_id' => $paymentConfig->app_id,
            'mch_id' => $paymentConfig->mch_id,
            'key' => $paymentConfig->key,   // API 密钥

            // 如需使用敏感接口（如退款、发送红包等）需要配置 API 证书路径(登录商户平台下载 API 证书)
            'cert_path' => Storage::disk('admin_private_cert')->path($paymentConfig->cert_path),     // XXX: 绝对路径！！！！
            'key_path' => Storage::disk('admin_private_cert')->path($paymentConfig->key_path),      // XXX: 绝对路径！！！！

            'notify_url' => $paymentConfig->notify_url,     // 你也可以在下单时单独设置来想覆盖它
        ];

        $this->payment = Factory::payment($paymentConfig);
    }

    /**
     * 获得officialAccount实例
     *
     * @return \EasyWeChat\OfficialAccount\Application
     * @throws AccidentException
     */
    public function getOfficialAccount()
    {
        $this->setOfficialAccountConfig($this->companyId);

        return $this->oa;
    }

    /**
     * @return \EasyWeChat\Work\Application
     * @throws AccidentException
     */
    public function getWeWork()
    {
        $this->setWeWorkConfig($this->companyId);

        return $this->weWork;
    }

    /**
     * @return Application
     */
    public function getPayment()
    {
        return $this->payment;
    }

    /**
     * @param $companyId
     * @throws AccidentException
     */
    protected function setOfficialAccountConfig($companyId)
    {
        $config = WeChatOAConfig::query()
            ->where('company_id', $companyId)
            ->first();

        info('当前配置信息', [$config?->toArray(), $companyId]);

        if (!$config) {
            eRet('尚未配置公众号配置信息');
        }

        $config = [
            // 必要配置
            'app_id' => $config->app_id,
            'secret' => $config->secret,
            'token' => $config->token,
            'aes_key' => $config->aes_key,
            'log' => [
                'default' => app()->environment('production') ? 'production' : 'dev',
                'channels' => [
                    // 测试环境
                    'dev' => [
                        'driver' => 'single',
                        'path' => storage_path('logs/wechat/wechat-oa.log'),
                        'level' => 'debug',
                    ],
                    // 生产环境
                    'production' => [
                        'driver' => 'daily',
                        'path' => storage_path('logs/wechat/wechat-oa.log'),
                        'level' => 'debug',
                    ],
                ],
            ],
        ];

        $this->oa = Factory::officialAccount($config);
    }

    /**
     * @param $companyId
     * @throws AccidentException
     */
    protected function setWeWorkConfig($companyId)
    {
        $config = WeChatWorkConfig::query()
            ->where('company_id', $companyId)
            ->first();

        info('当前配置信息', [$config?->toArray(), $companyId]);

        if (!$config) {
            eRet('尚未配置企业微信信息');
        }

        $config = [
            'corp_id' => $config->corp_id,
            'agent_id' => $config->agent_id,
            'secret'   => $config->secret,

            'log' => [
                'default' => app()->environment('production') ? 'production' : 'dev',
                'channels' => [
                    // 测试环境
                    'dev' => [
                        'driver' => 'single',
                        'path' => storage_path('logs/wechat/wework.log'),
                        'level' => 'debug',
                    ],
                    // 生产环境
                    'production' => [
                        'driver' => 'daily',
                        'path' => storage_path('logs/wechat/wework.log'),
                        'level' => 'debug',
                    ],
                ],
            ],
        ];

        $this->weWork = Factory::work($config);
    }

    /**
     * 使用公众号的支付信息
     * @throws \App\Exceptions\AccidentException
     */
    public function useOAPaymentConfig()
    {
        $config = WechatPayment::query()
            ->where('company_id', $this->companyId)
            ->first();

        if (!$config) {
            if (app()->runningInConsole()) {
                return false;
            }
            eRet('尚未配置微信支付');
        }

        if (empty($config->oa_app_id)) {
            throw new AccidentException('公众号微信支付未开启');
        }

        // 预下载支付证书
        CosUtil::downloadCert($config->cert_path);
        CosUtil::downloadCert($config->key_path);

        $config = [
            // 必要配置
            'app_id' => $config->oa_app_id,
            'mch_id' => $config->mch_id,
            'key' => $config->key,   // API 密钥

            // 如需使用敏感接口（如退款、发送红包等）需要配置 API 证书路径(登录商户平台下载 API 证书)
            'cert_path' => Storage::disk('admin_private_cert')->path($config->cert_path),     // XXX: 绝对路径！！！！
            'key_path' => Storage::disk('admin_private_cert')->path($config->key_path),      // XXX: 绝对路径！！！！

            'notify_url' => $config->notify_url,     // 你也可以在下单时单独设置来想覆盖它
        ];

        $this->payment = Factory::payment($config);

        return $this;
    }


    /**
     * 使用APP的支付信息
     * @throws \App\Exceptions\AccidentException
     */
    public function useAppPaymentConfig()
    {
        $paymentConfig = WechatPayment::query()
            ->where('company_id', $this->companyId)
            ->first();

        if (!$paymentConfig) {
            if (app()->runningInConsole()) {
                return false;
            }
            eRet('尚未配置微信支付');
        }

        $appConfig = WechatAppConfig::query()
            ->where('company_id', $this->companyId)
            ->first();
        if (!$appConfig) {
            if (app()->runningInConsole()) {
                return false;
            }
            eRet('尚未配置微信App支付');
        }

        if (empty($appConfig->app_id)) {
            throw new AccidentException('微信App支付未开启');
        }

        $this->notifyUrl = $paymentConfig->notify_url;

        // 预下载支付证书
        CosUtil::downloadCert($paymentConfig->cert_path);
        CosUtil::downloadCert($paymentConfig->key_path);

        $paymentConfig = [
            // 必要配置
            'app_id' => $appConfig->app_id,
            'mch_id' => $paymentConfig->mch_id,
            'key' => $paymentConfig->key,   // API 密钥

            // 如需使用敏感接口（如退款、发送红包等）需要配置 API 证书路径(登录商户平台下载 API 证书)
            'cert_path' => Storage::disk('admin_private_cert')->path($paymentConfig->cert_path),     // XXX: 绝对路径！！！！
            'key_path' => Storage::disk('admin_private_cert')->path($paymentConfig->key_path),      // XXX: 绝对路径！！！！

            'notify_url' => $paymentConfig->notify_url,     // 你也可以在下单时单独设置来想覆盖它
        ];

        $this->payment = Factory::payment($paymentConfig);

        return $this;
    }

    /**
     * 是否海外小程序
     *
     * @return bool
     */
    public function isOverSea()
    {
        return (bool)$this->isOversea;
    }

    public function getMiniProgram(): MiniProgram
    {
        return $this->miniProgram;
    }

    // 微信小程序支付
    // https://blog.csdn.net/createNo_1/article/details/82377998
    // https://learnku.com/articles/8613/wechat-small-programs-pay-api-configuration-under-laravel

    /**
     * 处理支付成功回调
     */
    public function dealPayNotify()
    {
        try {
            return $this->payment->handlePaidNotify(function ($message, $fail) {
                app('log')->info('返回的消息为：', $message);
                // 使用通知里的 "微信支付订单号" 或者 "商户订单号" 去自己的数据库找到订单
                /** @var Order $order */
                $order = Order::where('uuid4wechat', $message['out_trade_no'])->first();
                // 兜底查询，避免并发查询没有查询到
                if (!$order) {
                    $uuid = OrderPaymentUuid::query()->where('uuid', $message['out_trade_no'])->first();

                    if ($uuid) {
                        $order = Order::where('id', $uuid->order_id)->first();
                    }
                }
                //只能通过微信下单订单号来判断 不能通过订单号判断
                if ($order && TransactionRecord::where('out_serial_no', $message['transaction_id'])->first()) {
                    return true;
                }

                // 进入以下代表订单肯定是未支付状态 或者支付失败状态
                if ($message['return_code'] === 'SUCCESS') {
                    app('log')->debug('支付成功回调');

                    DB::beginTransaction();
                    try {
                        if ($order) {
                            $serial_no = SerialNo::genSerialNo(SerialNo::ORDER_PAY);
                            if ($order->payment_mode === 2) {
                                $order->setOnDeliveryPaid($serial_no, TransactionRecord::WECHAT, $message['transaction_id'], outTradeNo: $message['out_trade_no']);
                            } else {
                                $order->setPaied($serial_no, TransactionRecord::WECHAT, $message['transaction_id'], outTradeNo: $message['out_trade_no']);
                            }
                        } else {
                            return $fail('通信失败，请稍后再通知我');
                        }
                    } catch (\Exception $e) {
                        DB::rollBack();
                        app('log')->info('支付回调出错,错误原因为:' . $e->getMessage());
                        return $fail('通信失败，请稍后再通知我');
                    }
                    DB::commit();
                }

                app('log')->info('进入此处代表回调完成');
                return true; // 返回处理完成
            });
        } catch (Exception | \Throwable $e) {
            info('微信支付回调数据异常', ['message' => $e->getMessage()]);

            return false;
        }
    }

    // 微信小程序支付
    // https://blog.csdn.net/createNo_1/article/details/82377998
    // https://learnku.com/articles/8613/wechat-small-programs-pay-api-configuration-under-laravel

    /**
     * 处理支付成功回调
     */
    public function dealPayGrowthValueNotify()
    {
        return $this->payment->handlePaidNotify(function ($message, $fail) {
            app('log')->info('返回的消息为：', $message);
            // 使用通知里的 "微信支付订单号" 或者 "商户订单号" 去自己的数据库找到订单
            if ($message['return_code'] !== 'SUCCESS') {
                return false;
            }

            app('log')->debug('支付成功回调');

            DB::beginTransaction();
            try {
                $bool = $this->payCallback($message['out_trade_no'], TransactionRecord::WECHAT, $message['transaction_id'] ?? '');
                if(!$bool){
                    DB::rollBack();
                    return $fail('通信失败，请稍后再通知我');
                }
            } catch (\Exception $e) {
                DB::rollBack();
                app('log')->info('支付回调出错,错误原因为:' . $e->getMessage());
                return $fail('通信失败，请稍后再通知我');
            }
            DB::commit();
            app('log')->info('进入此处代表回调完成');
            return true; // 返回处理完成
        });
    }


    // 微信小程序支付
    // https://blog.csdn.net/createNo_1/article/details/82377998
    // https://learnku.com/articles/8613/wechat-small-programs-pay-api-configuration-under-laravel

    /**
     * 处理支付成功回调
     */
    public function dealPayDaiGouOrderNotify()
    {
        return $this->payment->handlePaidNotify(function ($message, $fail) {
            app('log')->info('返回的消息为：', $message);
            // 使用通知里的 "微信支付订单号" 或者 "商户订单号" 去自己的数据库找到订单
            if ($message['return_code'] !== 'SUCCESS') {
                return false;
            }

            app('log')->debug('支付成功回调');

            DB::beginTransaction();
            try {
                $bool = $this->dgOrderPayCallback($message['out_trade_no'], TransactionRecord::WECHAT, $message['transaction_id'] ?? '');
                if(!$bool){
                    DB::rollBack();
                    return $fail('通信失败，请稍后再通知我');
                }
            } catch (\Exception $e) {
                DB::rollBack();
                app('log')->info('支付回调出错,错误原因为:' . $e->getMessage());
                return $fail('通信失败，请稍后再通知我');
            }
            DB::commit();
            app('log')->info('进入此处代表回调完成');
            return true; // 返回处理完成
        });
    }


    /**
     * 处理充值支付成功回调
     */
    public function dealRechargeNotify()
    {
        try {
            return $this->payment->handlePaidNotify(function ($message, $fail) {
                app('log')->info('充值回调返回的消息为：', $message);
                // 使用通知里的 "微信支付订单号" 或者 "商户订单号" 去自己的数据库找到订单
                /** @var BalanceRechargeRecord $record */
                $record = BalanceRechargeRecord::withTrashed()->where('serial_no', $message['out_trade_no'])->first();

                $record->restore(); // 恢复被软删除的模型

                if ($record && $record->status === BalanceRechargeRecord::CHECK_PASS) {
                    return true;
                }

                // 进入以下代表订单肯定是未支付状态 或者支付失败状态
                if ($message['return_code'] === 'SUCCESS') {
                    app('log')->debug('支付成功回调');

                    $bool = $this->rechargeSuccess($record, $message['transaction_id'] ?? '', '微信支付');
                    if (!$bool) {
                        app('log')->info('进入此处代表回调失败');
                        return false;
                    }
                }

                app('log')->info('进入此处代表回调完成');

                return true; // 返回处理完成
            });
        } catch (Exception | \Throwable $e) {
            info('微信支付回调数据异常', ['message' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * @return false|\Symfony\Component\HttpFoundation\Response
     */
    public function dealCommonNotify()
    {
        try {
            return $this->payment->handlePaidNotify(function ($message, $fail) {
                info('微信支付回调返回的消息为：', $message);
                $outTradeNo = $message['out_trade_no'];
                // 订单补充款费用
                if (str_starts_with($outTradeNo, 'AF')) {
                    return \App\Services\Payment\Callback\OrderAdditionalFee::wechat($message, $fail);
                }

                return true;
            });
        } catch (\Throwable $e) {
            info('微信支付回调数据异常', ['message' => $e->getMessage()]);

            return false;
        }
    }

    public function sdkConfig($prepayId)
    {
        return $this->payment->jssdk->sdkConfig($prepayId);
    }

    /**
     * 下微信单
     * @param $code
     * @param $total_fee
     * @param string $body
     * @param string $trade_type
     * @param null $notify_url
     * @param string $version
     * @return array|\EasyWeChat\Kernel\Support\Collection|object|\Psr\Http\Message\ResponseInterface|string
     * @throws AccidentException
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidArgumentException
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function order($code, $total_fee, $body = 'jiyun-订单支付', $trade_type = 'JSAPI', $notify_url = null, $version = 'V2')
    {
        //汇率转换
        info('转换前:' . $total_fee);
        $total_fee = ExchangeRate::rateConvert($total_fee);
        info('转换后:' . $total_fee);

        $total_fee = PaymentServiceFeeService::calculateFee($total_fee, TransactionRecord::WECHAT, code: $code);

        $unify = [
            'body' => $body,
            'out_trade_no' => $code, // test
            'total_fee' => $total_fee,
            'fee_type' => $this->getFeeType(),
        ];

        if ($notify_url) {
            $unify['notify_url'] = $notify_url;
        }

        switch ($trade_type) {
            case 'JSAPI':
                // 如果是主动提供了openid参数
                // 视为公众号类型的支付
                // 否则视为小程序内支付
                if ($openId = request()->open_id) {
                    $open_id = $openId;
                    $this->useOAPaymentConfig();
                } else {
                    $open_id = request()->user()->open_id;
                }
                $unify['trade_type'] = 'JSAPI';
                $unify['openid'] = $open_id;
                break;
            case 'NATIVE':
                $unify['trade_type'] = 'NATIVE';
                $unify['product_id'] = $code;
                break;
            case 'APP' :
                $this->useAppPaymentConfig();
                $unify['trade_type'] = $trade_type;
                break;
            default:
                $unify['trade_type'] = $trade_type;
                break;
        }

        app('log')->info('微信下单参数为：', $unify);

        if ((strtoupper($version) == 'V3') && ($trade_type == 'APP')) {
            $notify_url = $notify_url ?: $this->notifyUrl;
            $result = (new WechatService())->app($code, $total_fee, $body, $notify_url . '/v3');
            app('log')->info('返回结果为：', $result);
            return $result;
        } else {
            $result = $this->payment->order->unify($unify);
            app('log')->info('返回结果为：', $result);
            if ($result['return_code'] === 'SUCCESS' && $result['result_code'] === 'SUCCESS') {
                // $res['prepay_id'] = $result['prepay_id'] ?? null;
                return $result;
            }
            eRet($result['return_msg']);
        }
    }

    public function generateAuthAppCode(): StreamResponse
    {
        $url = 'pages/authorize/authorize';

        return $this->miniProgram->app_code->get($url, ['width' => 120]);
    }

    /**
     * @return array|\EasyWeChat\Kernel\Support\Collection|object|\Psr\Http\Message\ResponseInterface|string
     */
    public function generateIndexAppCode()
    {
        $url = 'pages/index/index';

        return $this->miniProgram->app_code->get($url, ['width' => 120]);
    }

    /**
     * 生成小程序码
     * 保存示例     $filename = $response->saveAs('/path/to/directory', 'appcode.png');
     * @param $userId
     * @param array $optional
     * @return StreamResponse
     * @throws AccidentException
     */
    public function generateAppCode($userId, array $optional = []): StreamResponse
    {
        $url = $this->getQrcodePath($userId, $userId);

        info('当前小程序码的path', ['path' => $url]);

        $response = $this->miniProgram->app_code->get($url, array_merge(['width' => 120], $optional));

        if ($response instanceof StreamResponse) {
            return $response;
        }

        info('生成小程序码失败：', ['return' => $response]);

        throw new AccidentException('生成小程序码失败');
    }

    /**
     * 生成临时小程序码 -- 暂定用于分享赠券
     * 保存示例     $filename = $response->saveAs('/path/to/directory', 'appcode.png');
     * @param $inviteID
     * @return StreamResponse
     * @throws AccidentException
     */
    public function generateUnlimitCode($inviteID): StreamResponse
    {
        $scene = $inviteID;

        $path = 'pages/index/index';

        $response = $this->miniProgram->app_code->getUnlimit($scene, ['width' => 120, 'page' => $path]);

        if (!$response instanceof StreamResponse) {
            app('log')->debug('当前的结果为：' . json_encode($response));
            eRet('生成临时二维码失败');
        }

        return $response;
    }

    /**
     * 生成优惠券临时小程序码
     *
     * @param string $scene
     * @return StreamResponse
     * @throws AccidentException
     */
    public function generateCouponCode(string $scene): StreamResponse
    {
        $path = 'pages/coupon/index';

        $response = $this->miniProgram->app_code->getUnlimit($scene, ['width' => 120, 'page' => $path]);

        if (!$response instanceof StreamResponse) {
            app('log')->debug('当前的结果为：' . json_encode($response));
            eRet('生成优惠券二维码失败');
        }

        return $response;
    }

    public function getQrcodePath($agentID = null, $inviteID = null): string
    {
        $companyUuid = Admin::where('id', $this->companyId)->first()->uuid;
        $prop = CompanyProp::where('company_id', $this->companyId)->where('type', CompanyProp::MINI_PATH)->first();

        return ($prop ? $prop->prop : 'pages/index/index') . '?uuid=' . $companyUuid . '&inviteid=' . $inviteID;
    }

    /**
     * 微信支付的订单退款
     *
     * @param Order $order
     * @param $refundFee int 退款金额以分为单位
     * @param string $serialNo
     * @return bool
     * @throws AccidentException
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     */
    public function orderRefund(DaiGouOrder|Order $order, $refundFee, string $serialNo = '')
    {
        app('log')->debug('订单' . $order->order_sn . '申请退款,金额为:' . $refundFee);

        $paymentConfig = WechatPayment::where('company_id', $order->company_id)->first();

        if (!$paymentConfig) {
            eRet('加载微信支付失败: 微信支付尚未配置');
        }

        app('log')->debug('当前的支付配置为:' . json_encode($paymentConfig));

        if (!$paymentConfig->cert_path || !$paymentConfig->key_path) {
            eRet('微信支付退款失败: 微信支付退款需要额外配置证书');
        }

        // 预下载支付证书
        CosUtil::downloadCert($paymentConfig->cert_path);
        CosUtil::downloadCert($paymentConfig->key_path);

        $paymentConfig = [
            // 必要配置
            'app_id' => $paymentConfig->app_id,
            'mch_id' => $paymentConfig->mch_id,
            'key' => $paymentConfig->key,   // API 密钥
            // 退款需要配置 API 证书路径(登录商户平台下载 API 证书)
            'cert_path' => Storage::disk('admin_private_cert')->path($paymentConfig->cert_path), // 绝对路径
            'key_path' => Storage::disk('admin_private_cert')->path($paymentConfig->key_path),  // 绝对路径
            'notify_url' => $paymentConfig->refund_notify_url,  // 你也可以在下单时单独设置来想覆盖它
        ];

        $payment = Factory::payment($paymentConfig);

        $transaction = TransactionRecord::where('order_sn', $order->order_sn)->where('mod4pay', 0)->first();

        if (!$transaction) {
            eRet('该订单不是微信支付');
        }

        $serial_no = $serialNo ?: SerialNo::genSerialNo(SerialNo::ORDER_REFUND);

        info('汇率转换前', [$transaction->amount, $refundFee]);
        $amount = ExchangeRate::rateConvertWithRate($transaction->amount, $transaction->rate);
        $wechatRefundFee = ExchangeRate::rateConvertWithRate($refundFee, $transaction->rate);
        info('汇率转换前', [$amount, $refundFee, $transaction->rate]);

        // 参数分别为：微信订单号、商户退款单号、订单金额、退款金额、其他参数
        $result = $payment->refund->byTransactionId(
            $transaction->out_serial_no,
            $serial_no,
            $amount,  //传给微信的是汇率转换后的金额
            $wechatRefundFee
        );

        if (isset($result['result_code']) && $result['result_code'] === 'SUCCESS') {
            $out_serial_no = $result['refund_id'];
            //生成退款流水
            TransactionRecord::create([
                'user_id' => $order->user_id,
                'type' => TransactionRecord::REFUND,
                'amount' => $refundFee,
                'order_sn' => $order->order_sn,
                'wechat_sn' => '',
                'company_id' => $order->company_id,
                'serial_no' => $serial_no,
                'mod4pay' => TransactionRecord::WECHAT,
                'out_serial_no' => $out_serial_no,
            ]);

            app('log')->debug('订单退款成功');

            return true;
        }
        //退款失败才记录返回
        app('log')->debug('退款失败的实时返回结果为:' . json_encode($result));

        return false;
    }

    /**
     * 处理充值支付成功回调
     */
    public function dealRefundNotify()
    {
        return $this->payment->handleRefundedNotify(function ($message, $reqInfo, $fail) {
            app('log')->info('退款返回的消息为：', $message);
            app('log')->info('退款返回的解密消息为：', $reqInfo);

            //此处需要更新流水添加微信流水号到流水记录
            // if (false) {
            //     return $fail('失败返回给微信');
            // }

            app('log')->info('进入此处代表回调完成');
            return true; // 返回处理完成
        });
    }

    /**
     * 微信转账
     */
    public function wechatTran(User $user, $amount, $desc = '退款')
    {
        app('log')->debug('用户' . $user->id . '申请退款,金额为:' . $amount);
        $paymentConfig = WechatPayment::where('company_id', $user->company_id)->first();

        if (!$paymentConfig) {
            eRet('该公司暂无支付配置');
        }

        app('log')->debug('当前的支付配置为:' . json_encode($paymentConfig));

        // 预下载支付证书
        CosUtil::downloadCert($paymentConfig->cert_path);
        CosUtil::downloadCert($paymentConfig->key_path);

        $paymentConfig = [
            // 必要配置
            'app_id' => $paymentConfig->app_id,
            'mch_id' => $paymentConfig->mch_id,
            'key' => $paymentConfig->key,   // API 密钥

            // 如需使用敏感接口（如退款、发送红包等）需要配置 API 证书路径(登录商户平台下载 API 证书)
            'cert_path' => Storage::disk('admin_private_cert')->path($paymentConfig->cert_path),     // XXX: 绝对路径！！！！
            'key_path' => Storage::disk('admin_private_cert')->path($paymentConfig->key_path),      // XXX: 绝对路径！！！！

            'notify_url' => $paymentConfig->refund_notify_url,     // 你也可以在下单时单独设置来想覆盖它
        ];

        $payment = Factory::payment($paymentConfig);

        $payment->transfer->toBalance([
            'partner_trade_no' => 'testtest' . time(), // 商户订单号，需保持唯一性(只能是字母或者数字，不能包含有符号)
            'openid' => $user->open_id,
            'check_name' => 'NO_CHECK', // NO_CHECK：不校验真实姓名, FORCE_CHECK：强校验真实姓名
            're_user_name' => '王小帅', // 如果 check_name 设置为FORCE_CHECK，则必填用户真实姓名
            'amount' => $amount, // 企业付款金额，单位为分
            'desc' => $desc, // 企业付款操作说明信息。必填
        ]);
        return true;
    }

    protected function getFeeType(): string
    {
        return match ($this->companyId) {
            878 => 'GBP',
            default => 'CNY',
        };
    }
}
