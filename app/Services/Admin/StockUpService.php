<?php

namespace App\Services\Admin;

use App\Exceptions\AccidentException;
use App\Http\Resources\Admin\AdminList;
use App\Http\Resources\Admin\CustomInfo;
use App\Http\Resources\Admin\stockProcessLogList;
use App\Http\Resources\Admin\StockUpProductItemList;
use App\Http\Resources\Admin\StockUpPurchaseItemList;
use App\Jobs\Export\CreditCardRechargeRecordExport;
use App\Jobs\Export\CreditStockUpExport;
use App\Jobs\Export\OfflineRechargeRecordExport;
use App\Jobs\Export\OnlineRechargeRecordExport;
use App\Lib\Code;
use App\Models\Admin;
use App\Models\BalanceRecharge;
use App\Models\BalanceRecord;
use App\Models\CreditCardRechargeRecord;
use App\Models\Custom;
use App\Models\CustomBalance;
use App\Models\CustomsQuoteConfig;
use App\Models\ExcelExport;
use App\Models\ExchangeRateModel;
use App\Models\StockProcessLog;
use App\Models\StockUp;
use App\Models\StockUpProductItem;
use App\Models\StockUpPurchaseItem;
use App\Models\User;
use App\Services\ApiResponseService;
use App\Services\Wechat\RequestApi;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class StockUpService extends BaseService
{

    public function __construct(public OrderService $orderService, public RequestApi $requestApi)
    {
    }

    /**
     * 保存备货单
     * @param $stockUpId
     * @param $stockUpDescribe
     * @param $customId
     * @param $stockUpType
     * @param $stockUpProductItems
     * @param $process
     * @return bool|JsonResponse
     * @throws AccidentException
     */
    public function saveStockUp($stockUpId, $stockUpDescribe, $customId, $stockUpType, $stockUpProductItems, $process): bool|JsonResponse
    {
        $date = date('Y-m-d H:i:s');
        // 获取客户信息
        $custom = Custom::where('id', $customId)->first();
        if (!$custom) {
            throw new AccidentException("客户不存在");
        }

        // 备货单状态判断
        if (!empty($stockUpId)) {
            $stockUp = StockUp::where('id', $stockUpId)->whereNull('deleted_at')->first();
            if (empty($stockUp)) {
                throw new AccidentException("备货单不存在");
            }
            if ($stockUp->process != StockUp::PROCESS_DRAFT) {
                throw new AccidentException("备货单状态异常");
            }
        }

        // 汇率获取
        $exchangeRate = ExchangeRateModel::where('currency_code', 'CNY')->value('custom_exchange_rate');
        if (empty($exchangeRate)) {
            throw new AccidentException("汇率获取失败");
        }

        // 获取余额
        $balance = CustomBalance::where('custom_id', $customId)->value('balance');

        $adminId = auth('admin')->user()->id;

        $admin = Admin::where('id', $adminId)->first();


        // 构造明细项及累计总额
        $productItemsData = [];
        $stockUpAmountStr = '0';
        foreach ($stockUpProductItems as $item) {
            if (!isset($item['sku']) || !isset($item['num']) || !isset($item['price'])) {
                return ApiResponseService::errorMessage("商品项参数不完整");
            }

            $total = bcmul((string)$item['num'], (string)$item['price'], 2);

            $productItemsData[] = [
                'sku' => $item['sku'],
                'num' => $item['num'],
                'price' => $item['price'],
                'total' => $total,
                'created_at' => $date,
            ];

            $stockUpAmountStr = bcadd($stockUpAmountStr, $total, 2);
        }

        $customBalance = CustomBalance::where('custom_id', $customId)->lockForUpdate()->first();
        if (empty($customBalance['balance']) && $customBalance['balance'] !== 0 && $customBalance['balance'] !== '0') {
            throw new AccidentException("找不到该客户余额");
        }

        $purchaseAmount = bcdiv((string)$stockUpAmountStr, (string)$exchangeRate, 2);
        $amountInCents = bcmul($purchaseAmount, '100', 2);
        if ($customBalance['balance'] < $amountInCents) {
            throw new AccidentException("客户余额不够");
        }

        DB::beginTransaction();
        try {
            $commonFields = [
                'stock_up_type' => $stockUpType,
                'custom_id' => $customId,
                'exchange_rate' => $exchangeRate,
                'stock_up_amount' => $purchaseAmount,
                'credit_line' => $custom['credit_line'],
                'balance' => $balance,
                'frozen_limit' => $custom['frozen_limit'],
                'process' => $process,
                'stock_up_describe' => $stockUpDescribe,
                'updated_at' => $date,
            ];

            if (empty($stockUpId)) {
                // 生成备货单号
                $stockUpNumber = $this->createStockUpNumber($customId, $custom['customer_number']);
                $commonFields['stock_up_number'] = $stockUpNumber;
                $commonFields['submitter_id'] = $admin['id'];
                $commonFields['created_at'] = $date;
                $stockUpId = StockUp::insertGetId($commonFields);
            } else {
                $stockUpNumber = StockUp::where('id', $stockUpId)->whereNull('deleted_at')->value('stock_up_number');
                StockUp::where('id', $stockUpId)->update($commonFields);
            }

            // 删除原有明细项
            StockUpProductItem::where('stock_up_id', $stockUpId)->update([
                'deleted_at' => $date,
            ]);

            foreach ($productItemsData as &$item) {
                $item['stock_up_id'] = $stockUpId;
            }

            StockUpProductItem::insert($productItemsData);


            if ($process == StockUp::PROCESS_PENDING) {

                if (empty($admin['phone'])) {
                    throw new AccidentException("手机号码未设置");
                }

                if (empty($admin['wecom_user_id'])) {
                    $wecomUserid = $this->requestApi->getUserIdByMobile($admin['phone']);
                    if (!$wecomUserid) {
                        throw new AccidentException('获取手机号码对应的用户ID失败');
                    }
                } else {
                    $wecomUserid = $admin['wecom_user_id'];
                }


                $form = [
                    'stock_up_number' => $stockUpNumber, // 备货单号
                    'stock_up_describe' => $stockUpDescribe, // 备货单描述
                    'customer_number' => $custom->customer_number, // 客户编号
                    'stock_up_type' => $stockUpType == 1 ? 'option-1764838143322' : 'option-1764838143326', // 备货单类型 option-1764838143322=国内仓公司备货 option-1764838143326=海外仓公司备货
                    'products' => array_map(function ($item) {
                        return [
                            'sku' => $item['sku'], // 商品SKU
                            'num' => $item['num'], // 商品数量
                            'price' => $item['price'], // 商品单价
                            'total' => $item['total'], // 商品总价
                        ];
                    }, $productItemsData),
                    'stock_up_amount' => $commonFields['stock_up_amount'],
                ];

                $payload = [
                    'creator_userid' => $wecomUserid, // 申请人
                    'template_id' => config('wecom.approval.stock_template_id'),
                    'use_template_approver' => 1,                       // 用模版里配置的审批人
                    'apply_data' => $this->buildApplyData($form), //审批申请数据
                ];
                $resultSubmitApproval = $this->requestApi->submitApproval($payload);
                if (!$resultSubmitApproval || empty($resultSubmitApproval['sp_no'])) {
                    throw new AccidentException('创建企业微信审批失败');
                }

                StockUp::where('id', $stockUpId)->update([
                    'sp_no' => $resultSubmitApproval['sp_no'],
                    'updated_at' => $date,
                ]);
            }

            DB::commit();
            return true;

        } catch (\Throwable $e) {
            Log::error('Save stock up error: ', ['exception' => $e]);
            DB::rollBack();
            throw new AccidentException("系统异常，请稍后再试");
        }

    }

    /**
     * 构造备货申请数据
     * @param array $form
     * @return array[]
     */
    protected function buildApplyData(array $form): array
    {

        $children = [];
        foreach ($form['products'] as $product) {
            $children[] = [
                'list' => [
                    // 列1：SKU（文本）
                    [
                        'control' => 'Text',
                        'id' => 'Text-1764838289602',
                        "title" => [
                            [
                                "text" => "SKU",
                                "lang" => "zh_CN"
                            ]
                        ],
                        'value' => [
                            'text' => (string)$product['sku'],
                        ],
                    ],
                    // 列2：件数（数字）
                    [
                        'control' => 'Number',
                        'id' => 'Number-1764839133159',
                        "title" => [
                            [
                                "text" => "件数",
                                "lang" => "zh_CN"
                            ]
                        ],
                        'value' => [
                            'new_number' => (string)$product['num'],
                        ],
                    ],
                    // 列3：预计采购单价（金额）
                    [
                        'control' => 'Money',
                        'id' => 'Money-1764839099058',
                        "title" => [
                            [
                                "text" => "预计采购单价",
                                "lang" => "zh_CN"
                            ]
                        ],
                        'value' => [
                            'new_money' => (string)$product['price']
                        ],
                    ],
                    // 列4：小计(CRM)（金额）
                    [
                        'control' => 'Money',
                        'id' => 'Money-1764839124521',
                        "title" => [
                            [
                                "text" => "小计（CNY）",
                                "lang" => "zh_CN"
                            ]
                        ],
                        'value' => [
                            'new_money' => (string)$product['total']
                        ],
                    ],
                ]
            ];
        }


        return [
            'contents' => [
                // *备货编号（文本）
                [
                    'control' => 'Text',
                    'id' => 'Text-1764837707889',
                    'value' => [
                        'text' => $form['stock_up_number'],
                    ],
                ],

                // *备货描述（文本）
                [
                    'control' => 'Text',
                    'id' => 'Text-1764837867889',
                    'value' => [
                        'text' => $form['stock_up_describe'],
                    ],
                ],

                // *客户编号（文本）
                [
                    'control' => 'Text',
                    'id' => 'Text-1764837884050',
                    'value' => [
                        'text' => $form['customer_number'],
                    ],
                ],
                // *备货类型（下拉选择器）
                [
                    'control' => 'Selector',
                    'id' => 'Selector-1764838024799',
                    'value' => [
                        'selector' => [
                            'type' => 'single', // 单选
                            'options' => [
                                // key 必须是模版里该选项的 key
                                ['key' => $form['stock_up_type']],
                            ],
                        ],
                    ],
                ],
                // 备货产品
                [
                    'control' => 'Table',
                    'id' => 'Table-1764838284807',
                    'value' => [
                        'children' => $children,
                    ]
                ],
                // *预计备货金额（USD）
                [
                    'control' => 'Text',
                    'id' => 'Text-1764837915529',
                    'value' => [
                        'text' => $form['stock_up_amount'],
                    ],
                ],
            ],
        ];
    }


    /**
     * 生成备货单号
     * @param $customId
     * @param $customerNumber
     * @return string
     */
    public function createStockUpNumber($customId, $customerNumber): string
    {

        $count = StockUp::where('custom_id', $customId)->whereDate('created_at', date('Y-m-d'))->count();

        return 'Stock' . $customerNumber . '-' . date('Ymd') . '-' . $count + 1;
    }

    /**
     * 保存采购信息
     * @param $stockUpId
     * @param $stockUpPurchaseItems
     * @param $purchaseOpinion
     * @return bool
     * @throws AccidentException
     */
    public function saveStockUpPurchase($stockUpId, $stockUpPurchaseItems, $purchaseOpinion): bool
    {
        if (!in_array(auth('admin')->user()->id, [1, 9, 19, 41, 81, 87])) {
            throw new AccidentException("无权限操作");
        }
        $stockUp = StockUp::where('id', $stockUpId)->whereNull('deleted_at')->first();
        if (empty($stockUp)) {
            throw new AccidentException("找不到该备货单");
        }
        if ($stockUp->process != StockUp::PROCESS_APPROVED) {
            throw new AccidentException("当前审批状态不允许采购");
        }
        $stockProcessLog = StockProcessLog::where('stock_up_id', $stockUpId)->orderBy('created_at', 'desc')->whereNull('deleted_at')->first();
        if (!$stockProcessLog) {
            throw new AccidentException("无法找到对应的流程日志");
        }


        DB::beginTransaction();
        try {
            $date = date('Y-m-d H:i:s');

            $purchaseAmount = 0;
            foreach ($stockUpPurchaseItems as &$item) {
                $item['stock_up_id'] = $stockUpId;
                $item['created_at'] = $date;
                $purchaseAmount += $item['total'];
            }

            $stockUp::where('id', $stockUpId)->update([
                'process' => StockUp::PROCESS_ACCOUNTING_RECONCILIATION,
                'purchase_amount' => $purchaseAmount, // 实际采购金额
                'purchase_opinion' => $purchaseOpinion,
                'updated_at' => $date,
            ]);

            StockUpPurchaseItem::insert($stockUpPurchaseItems);

            StockProcessLog::where('stock_up_id', $stockUpId)->where('node', StockProcessLog::NODE_PURCHASE_START)->whereNull('deleted_at')->update([
                'approve_name' => auth('admin')->user()->name,
                'approval_opinion' => $purchaseOpinion,
                'process' => StockUp::PROCESS_APPROVED,
                'created_at' => $date,
            ]);

            DB::commit();
            return true;

        } catch (\Throwable $e) {
            Log::error('Save stock up error: ', ['exception' => $e]);
            DB::rollBack();
            throw new AccidentException("系统异常，请稍后再试");
        }
    }

    /**
     * 删除备货单
     * @param $stockUpId
     * @return bool
     * @throws AccidentException
     */
    public function deleteStockUp($stockUpId): bool
    {
        $stockUp = StockUp::where('id', $stockUpId)->whereNull('deleted_at')->first();
        if (empty($stockUp)) {
            throw new AccidentException("找不到该备货单");
        }
        if ($stockUp->process == StockUp::PROCESS_DRAFT && $stockUp->process == StockUp::PROCESS_REJECTED && $stockUp->process == StockUp::PROCESS_CANCELLED) {
            throw new AccidentException("当前审批状态不允许删除");
        }

        DB::beginTransaction();
        try {
            $date = date('Y-m-d H:i:s');

            StockUp::where('id', $stockUpId)->update([
                'deleted_at' => $date
            ]);

            StockUpProductItem::where('stock_up_id', $stockUpId)->update([
                'deleted_at' => $date
            ]);

            StockUpPurchaseItem::where('stock_up_id', $stockUpId)->update([
                'deleted_at' => $date
            ]);

            StockProcessLog::where('stock_up_id', $stockUpId)->update([
                'deleted_at' => $date
            ]);

            DB::commit();
            return true;

        } catch (\Throwable $e) {
            Log::error('delete stock up error: ', ['exception' => $e]);
            DB::rollBack();
            throw new AccidentException("系统异常，请稍后再试");
        }
    }

    /**
     * 财务核账
     * @param $stockUpId
     * @return bool
     * @throws AccidentException
     */
    public function accountingReconciliation($stockUpId): bool
    {
        if (!in_array(auth('admin')->user()->id, [1, 16, 33])) {
            throw new AccidentException("无权限操作");
        }
        $stockUp = StockUp::where('id', $stockUpId)->whereNull('deleted_at')->first();
        if (empty($stockUp)) {
            throw new AccidentException("找不到该备货单");
        }
        if ($stockUp->process != StockUp::PROCESS_ACCOUNTING_RECONCILIATION) {
            throw new AccidentException("当前审批状态不允许财务核账");
        }

        $stockProcessLog = StockProcessLog::where('stock_up_id', $stockUpId)->orderBy('created_at', 'desc')->whereNull('deleted_at')->first();
        if (!$stockProcessLog) {
            throw new AccidentException("无法找到对应的流程日志");
        }

        $custom = Custom::where('id', $stockUp['custom_id'])->whereNull('deleted_at')->lockForUpdate()->first();
        if (empty($custom)) {
            throw new AccidentException("找不到该客户");
        }

        $customBalance = CustomBalance::where('custom_id', $stockUp['custom_id'])->lockForUpdate()->first();
        if (empty($customBalance['balance']) && $customBalance['balance'] !== 0 && $customBalance['balance'] !== '0') {
            throw new AccidentException("找不到该客户余额");
        }


        // 汇率获取
        $exchangeRate = ExchangeRateModel::where('currency_code', 'CNY')->value('custom_exchange_rate');
        if (empty($exchangeRate)) {
            throw new AccidentException("汇率获取失败");
        }
        $purchaseAmount = bcdiv((string)$stockUp->purchase_amount, $exchangeRate, 2);
        $amountInCents = bcmul($purchaseAmount, '100', 2);
        if ($customBalance['balance'] < $amountInCents) {
            throw new AccidentException("客户余额不够");
        }

        DB::beginTransaction();
        try {
            $date = date('Y-m-d H:i:s');

            StockUp::where('id', $stockUpId)->update([
                'process' => StockUp::PROCESS_COMPLETE,
                'updated_at' => $date,
            ]);


            StockProcessLog::where('stock_up_id', $stockUpId)->where('node', StockProcessLog::NODE_FINANCE_APPROVE)->whereNull('deleted_at')->update([
                'approve_name' => auth('admin')->user()->name,
                'process' => StockProcessLog::PROCESS_COMPLETE,
                'created_at' => $date,
            ]);

            //交易流水
            BalanceRecord::query()->create([
                'custom_id' => $stockUp['custom_id'],
                'type' => BalanceRecord::CHANGE_INCREASE,
                'source_type' => BalanceRecord::SOURCE_ADJUST_FROZEN_LIMIT,
                'amount' => $amountInCents,
                'after_change_balance' => bcadd($customBalance['balance'], $amountInCents) ?? 0,
                'relation_id' => 0,
                'relation_type' => '',
                'order_sn' => '',
                'remark' => '',
                'serial_no' => BalanceRecord::getSerialNo(BalanceRecord::SOURCE_ADJUST_FROZEN_LIMIT),
                'out_serial_no' => '',
                'actual_amount' => 0, //仅在调整信用额度和冻结额度的时候有值
                'charge_type_id' => 0,//仅在source_type=9有值
                'operate_admin_id' => 0,
                'attachment_files' => [],
            ]);

            Custom::where('id', $stockUp['custom_id'])->update([
                'frozen_limit' => bcadd($custom->frozen_limit, $purchaseAmount, 2),
                'cumulative_frozen' => bcadd($custom->cumulative_frozen, $purchaseAmount, 2),
            ]);

            CustomBalance::where('custom_id', $stockUp['custom_id'])->update([
                'balance' => bcsub($customBalance['balance'], $amountInCents),
                'updated_at' => $date
            ]);

            DB::commit();
            return true;

        } catch (\Throwable $e) {
            Log::error('accounting reconciliation stock up error: ', ['exception' => $e]);
            DB::rollBack();
            throw new AccidentException("系统异常，请稍后再试");
        }
    }


    /**
     * 企业微信回调验证
     * @param $msgSignature
     * @param $timestamp
     * @param $nonce
     * @param $echostr
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response
     */
    public function verification($msgSignature, $timestamp, $nonce, $echostr)
    {
        if (!$this->requestApi->checkSignature($msgSignature, $timestamp, $nonce, $echostr)) {
            return response('invalid signature', 403);
        }
        try {
            $echoStr = $this->requestApi->decrypt($echostr);
        } catch (\Throwable $e) {
            Log::error('WeCom echostr decrypt error', ['e' => $e->getMessage()]);
            return response('decrypt error', 500);
        }

        // 按文档要求原样返回解密后的字符串
        return response($echoStr, 200)->header('Content-Type', 'text/plain');
    }


    /**
     * 微信审批回调
     * @param $msgSignature
     * @param $timestamp
     * @param $nonce
     * @param $rawXml
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response
     */
    public function approval($msgSignature, $timestamp, $nonce, $rawXml)
    {
        $xmlObj = simplexml_load_string($rawXml, 'SimpleXMLElement', LIBXML_NOCDATA);
        if ($xmlObj === false || !isset($xmlObj->Encrypt)) {
            return response('bad xml', 400);
        }

        $encrypt = (string)$xmlObj->Encrypt;

        if (!$this->requestApi->checkSignature($msgSignature, $timestamp, $nonce, $encrypt)) {
            return response('invalid signature', 403);
        }

        try {
            $plainXml = $this->requestApi->decrypt($encrypt);
        } catch (\Throwable $e) {
            Log::error('WeCom msg decrypt error', [
                'e' => $e->getMessage(),
                'raw' => $rawXml,
            ]);
            return response('decrypt error', 500);
        }

        Log::info('WeCom approval callback plain xml', ['xml' => $plainXml]);

        $msg = simplexml_load_string($plainXml, 'SimpleXMLElement', LIBXML_NOCDATA);
        if ($msg === false) {

            return response('bad inner xml', 400);
        }


        // === 这里开始根据业务处理审批事件 ===
        $msgType = (string)($msg->MsgType ?? '');
        $event = (string)($msg->Event ?? '');


        switch ($msgType) {
            case 'event': // 回调事件
                switch ($event) {
                    // 审批事件
                    case 'sys_approval_change':
                        $this->handleApprovalEvent($msg);
                        break;
                }
                break;

        }

        // 企业微信一般只要求 200 + 'success' 即可
        return response('success', 200)->header('Content-Type', 'text/plain');
    }


    /**
     * 处理审批事件
     *
     * @param \SimpleXMLElement $msg 解密后的 XML 对象
     */
    protected function handleApprovalEvent(\SimpleXMLElement $msg): void
    {
        $info = $msg->ApprovalInfo ?? null;
        if (empty($info)) {
            return;
        }

        $spNo = (string)($info->SpNo ?? ''); // 审批单号
        $spStatus = (int)($info->SpStatus ?? 0); // 申请单状态：1-审批中；2-已通过；3-已驳回；4-已撤销；6-通过后撤销；7-已删除；
        $applyTime = (int)($info->ApplyTime ?? 0);

        if (empty($spNo)) {
            return;
        }

        if (empty($applyTime)) {
            return;
        }

        // 申请人
        $applyerUserId = (string)($info->Applyer->UserId ?? '');
        if (empty($applyerUserId)) {
            return;
        }

        $adminInfo = admin::where('wecom_user_id', $applyerUserId)->whereNull('deleted_at')->first();
        if (empty($adminInfo)) {
            return;
        }

        $stockUp = StockUp::where('sp_no', $spNo)->whereNull('deleted_at')->first();
        if (empty($stockUp)) {
            return;
        }

        $date = date('Y-m-d H:i:s');

        StockProcessLog::where('stock_up_id', $stockUp['id'])
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => $date
            ]);


        StockProcessLog::insert([
            'stock_up_id' => $stockUp['id'],
            'node' => StockProcessLog::NODE_START,
            'approve_name' => $adminInfo['name'],
            'supervisor' => $adminInfo['name'],
            'submitter_id' => $adminInfo['id'],
            'process' => StockProcessLog::PROCESS_APPROVED,
            'created_at' => date('Y-m-d H:i:s', $applyTime)
        ]);


        switch ($spStatus) {
            case 1:  // 审批中
            case 2:  // 已通过
                $this->handleApprovalInProgressOrApproved($info, $stockUp, $adminInfo, $date, $spStatus);
                break;
            case 3:
                $this->handleRejectedApproval($info, $stockUp, $adminInfo, $date);
                break;
            case 4:  // 已撤销
            case 6:  // 通过后撤销
                $this->handleCancelledApproval($stockUp, $date);
                break;

            case 7:  // 已删除
                $this->handleDeletedApproval($stockUp, $date);
                break;
        }
    }

    /**
     * 处理审批中或已通过的状态
     */
    private function handleApprovalInProgressOrApproved($info, $stockUp, $adminInfo, $date, $spStatus)
    {
        // 审批流程记录
        foreach ($info->SpRecord as $record) {
            // 审批节点状态：1-审批中；2-已同意；3-已驳回；4-已转审

            if (empty($record->Details->Approver->UserId)) {
                continue;
            }

            $recordSpStatus = $record->SpStatus;

            switch ($recordSpStatus) {
                case 1:
                    $process = StockUp::PROCESS_PENDING;
                    break;
                case 2:
                    $process = StockUp::PROCESS_APPROVED;
                    break;
                case 3:
                    $process = StockUp::PROCESS_REJECTED;
                    break;
                case 4:
                    $process = StockUp::PROCESS_REVIEW_TRANSFER;
                    break;
            }

            $userInfo = $this->requestApi->getUserInfo($record->Details->Approver->UserId);
            $approveName = $userInfo['name'] ?? '';

            StockProcessLog::insert([
                'stock_up_id' => $stockUp['id'],
                'node' => StockProcessLog::NODE_WECOM_APPROVE,
                'approve_name' => $approveName,
                'approval_opinion' => $record->Details->Speech ?? '',
                'supervisor' => $approveName,
                'submitter_id' => $adminInfo['id'],
                'process' => $process,
                'created_at' => $date,
            ]);
        }

        if ($spStatus == 2) {
            StockUp::where('id', $stockUp['id'])
                ->update([
                    'process' => StockUp::PROCESS_APPROVED,
                    'updated_at' => $date,
                ]);
            //采购审批
            StockProcessLog::insert([
                'stock_up_id' => $stockUp['id'],
                'node' => StockProcessLog::NODE_PURCHASE_START,
                'supervisor' => '周乐薇, 卢霖薇, 李浩, 管柯妤, 叶兆霖',
                'submitter_id' => $adminInfo['id'],
                'process' => StockProcessLog::PROCESS_PENDING,
                'created_at' => $date,
            ]);
            //财务核账
            StockProcessLog::insert([
                'stock_up_id' => $stockUp['id'],
                'node' => StockProcessLog::NODE_FINANCE_APPROVE,
                'supervisor' => '王艳云，张飘',
                'submitter_id' => $adminInfo['id'],
                'process' => StockProcessLog::PROCESS_PENDING,
                'created_at' => $date,
            ]);
        }
    }


    /**
     * 处理已驳回的审批
     */
    private function handleRejectedApproval($info, $stockUp, $adminInfo, $date): void
    {
        StockUp::where('id', $stockUp['id'])->update([
            'process' => StockUp::PROCESS_REJECTED,
            'updated_at' => $date,
        ]);

        // 审批流程记录
        foreach ($info->SpRecord as $record) {
            // 审批节点状态：1-审批中；2-已同意；3-已驳回；4-已转审

            if (empty($record->Details->Approver->UserId)) {
                continue;
            }

            $recordSpStatus = $record->SpStatus;

            switch ($recordSpStatus) {
                case 1:
                    $process = StockUp::PROCESS_PENDING;
                    break;
                case 2:
                    $process = StockUp::PROCESS_APPROVED;
                    break;
                case 3:
                    $process = StockUp::PROCESS_REJECTED;
                    break;
                case 4:
                    $process = StockUp::PROCESS_REVIEW_TRANSFER;
                    break;
            }

            $userInfo = $this->requestApi->getUserInfo($record->Details->Approver->UserId);
            $approveName = $userInfo['name'] ?? '';

            StockProcessLog::insert([
                'stock_up_id' => $stockUp['id'],
                'node' => StockProcessLog::NODE_WECOM_APPROVE,
                'approve_name' => $approveName,
                'approval_opinion' => $record->Details->Speech ?? '',
                'supervisor' => $approveName,
                'submitter_id' => $adminInfo['id'],
                'process' => $process,
                'created_at' => $date,
            ]);
        }


    }

    /**
     * 处理已撤销的审批
     */
    private function handleCancelledApproval($stockUp, $date): void
    {
        StockUp::where('id', $stockUp['id'])->update([
            'process' => StockUp::PROCESS_CANCELLED,
            'updated_at' => $date,
        ]);
    }

    /**
     * 处理已删除的审批
     */
    private function handleDeletedApproval($stockUp, $date): void
    {
        $updates = ['deleted_at' => $date];

        StockUp::where('id', $stockUp['id'])->update($updates);
        StockUpProductItem::where('stock_up_id', $stockUp['id'])->update($updates);
        StockUpPurchaseItem::where('stock_up_id', $stockUp['id'])->update($updates);
        StockProcessLog::where('stock_up_id', $stockUp['id'])->update($updates);
    }


    /**
     * @param $params
     * @param $isReturnQueryBuilder
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Database\Eloquent\Builder
     */
    public function list($params, $isReturnQueryBuilder = false): \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Database\Eloquent\Builder
    {
        $pageSize = $params['size'] ?? 10;
        $customerNumber = $params['customer_number'] ?? '';
        $beginDate = $params['begin_date'] ?? '';
        $endDate = $params['end_date'] ?? '';
        $stockUpNumber = $params['stock_up_number'] ?? '';
        $stockUpDescribe = $params['stock_up_describe'] ?? '';
        $stockUpType = $params['stock_up_type'] ?? '';
        $submitterId = $params['submitter_id'] ?? '';
        $process = isset($params['process']) && $params['process'] !== '' ? explode(',', $params['process']) : null;
        $productSku = $params['product_sku'] ?? '';//产品sku
        $purchaseSku = $params['purchase_sku'] ?? ''; //采购sku
        $node = $params['node'] ?? '';

        $query = StockUp::with(['custom.customGroup', 'stockUpProductItem', 'stockUpPurchaseItem', 'stockProcessLog', 'admin', 'latestProcessLog']);

        //备货编号
        $query->when($stockUpNumber, function ($query) use ($stockUpNumber) {
            return $query->where('stock_up_number', 'like', '%' . $stockUpNumber . '%');
        });

        //备货描述
        $query->when($stockUpDescribe, function ($query) use ($stockUpDescribe) {
            return $query->where('stock_up_describe', 'like', '%' . $stockUpDescribe . '%');
        });

        //客户编号
        if ($customerNumber) {
            $query->whereHas('custom', function ($query) use ($customerNumber) {
                return $query->where('customer_number', 'like', '%' . $customerNumber . '%');
            });
        }

        //备货类型
        $query->when($stockUpType, function ($query) use ($stockUpType) {
            return $query->where('stock_up_type', $stockUpType);
        });

        //备货产品sku
        if ($productSku) {
            $query->whereHas('stockUpProductItem', function ($query) use ($productSku) {
                return $query->where('sku', 'like', '%' . $productSku . '%');
            });
        }

        //采购sku
        if ($purchaseSku) {
            $query->whereHas('stockUpPurchaseItem', function ($query) use ($purchaseSku) {
                return $query->where('sku', 'like', '%' . $purchaseSku . '%');
            });
        }

        //提交人
        $query->when($submitterId, function ($query) use ($submitterId) {
            return $query->where('submitter_id', $submitterId);
        });

        //流程状态
        $query->when($process, function ($query) use ($process) {
            return $query->whereIn('process', $process);
        });

        $query->when($node, function ($query) use ($node) {
            return $query->whereHas('latestProcessLog', function ($subQuery) use ($node) {
                return $subQuery->where('node', $node);
            });
        });

        //提交时间
        $query->when($beginDate && $endDate, function ($query) use ($beginDate, $endDate) {
            return $query->whereBetween('created_at', [$beginDate, Carbon::parse($endDate)->addDay()->toDate()]);
        });

        $query->latest();

        return $isReturnQueryBuilder ? $query : $query->paginate($pageSize);
    }


    // 如果是要返回所有流程状态，应该这样定义：
    public function process(): array
    {
        return StockUp::PROCESS;
    }

    public function export($params)
    {
        $fileName = 'stock_up' . Carbon::now()->format('YmdHis') . '_' . Str::random(6) . '.xlsx';

        $query = $this->list($params, true);
        $data = [];
        $query->chunk(100, function ($items) use (&$data) {
            foreach ($items as $item) {
                $latestNode = null;

                if ($item->latestProcessLog && $item->latestProcessLog->isNotEmpty()) {
                    $latestLog = $item->latestProcessLog->first();
                    $latestNode = $latestLog->node ?? null;
                }

                switch ($item->process) {
                    case StockUp::PROCESS_DRAFT:
                        $process = '暂存';
                        break;
                    case StockUp::PROCESS_PENDING:
                    case StockUp::PROCESS_APPROVED:
                    case StockUp::PROCESS_ACCOUNTING_RECONCILIATION:
                        $process = '进行中';
                        break;
                    case StockUp::PROCESS_REJECTED:
                        $process = '已驳回';
                        break;
                    case StockUp::PROCESS_CANCELLED:
                        $process = '已取消';
                        break;
                    case StockUp::PROCESS_COMPLETE:
                        $process = '完成';
                        break;
                    default:
                        $process = '未知状态';
                        break;
                }


                switch ($latestNode) {
                    case StockProcessLog::NODE_START:
                        $nodeName = '流程发起';
                        break;
                    case StockProcessLog::NODE_WECOM_APPROVE:
                        $nodeName = '企微审批';
                        break;
                    case StockProcessLog::NODE_PURCHASE_START:
                        $nodeName = '发起采购';
                        break;
                    case StockProcessLog::NODE_FINANCE_APPROVE:
                        $nodeName = '财务核账';
                        break;
                    default:
                        $nodeName = '未知节点';
                        break;
                }

                $name = Admin::where('id', $item->submitter_id)->value('name');

                $data[] = [
                    'stock_up_number' => $item->stock_up_number,//备货编号
                    'stock_up_describe' => $item->stock_up_describe,//备货描述
                    'custom' => CustomInfo::make($item->custom)->customer_number ?? [],//客户编号
                    'stock_up_type' => $item->stock_up_type == StockUp::STOCK_UP_TYPE_HOME ? '国内仓全款备货' : '海外仓全款备货',//备货类型
                    'stock_up_amount' => $item->stock_up_amount,//备货金额
                    'purchase_amount' => $item->purchase_amount ?? '',//采购金额
                    'submitter_id' => $name,//提交人
                    'process' => $process,//流程状态
                    'latest_node' => $nodeName,//当前节点

                ];
            }
        });

        /** @var $excelExport ExcelExport */
        $excelExport = ExcelExport::query()->create([
            'name' => $fileName,
            'type' => ExcelExport::TYPE_STOCK_REPORT,
            'url' => '',
        ]);


        dispatch(new CreditStockUpExport($excelExport, $data));


        return true;
    }


}
