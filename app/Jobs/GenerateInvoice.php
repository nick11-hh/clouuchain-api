<?php

namespace App\Jobs;

use App\Helper\CosUtil;
use App\Models\ChargePayMethod;
use App\Models\CreditCardRechargeRecord;
use App\Models\Custom;
use App\Models\PaymentSetting;
use App\Models\User;
use App\Services\Base\SystemConfigService as BaseService;
use App\Services\Client\FortySeasService;
use App\Services\PaymentPlatform\FortySeas\RequestApi;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\Traits\InvoiceTrait;
use App\Models\InvoiceRecords;
use App\Models\Order;
use App\Models\BalanceRecharge;
use App\Models\RechargeApply;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

/**
 * 生成发票队列
 * Class GenerateInvoice
 * @package App\Jobs
 * @author DonnyLiu <2365057581@qq.com>
 * @date 2024/9/20 11:36
 */
class GenerateInvoice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, InvoiceTrait;

    /**
     * 生成的发票记录
     */
    protected array $record;

    /**
     * 查询的记录IDS
     * @var array
     */
    protected array $ids;

    /**
     * 公司信息
     * @var
     */
    protected $adminInfo;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(array $record, array $ids, array $adminInfo)
    {
        $this->record = $record;
        $this->ids = $ids;
        $this->adminInfo = $adminInfo;
    }

    /**
     * Execute the job.
     *
     * @return bool
     */
    public function handle()
    {
        info('生成发票队列开始执行');

        $data = $this->getData();
        if (empty($data)) {
            $this->updateRecords(['status' => InvoiceRecords::FAIL_STATUS]);
            info('发票数据不存在，无法生成发票');
            return false;
        }
        info('待生成数据', $data);


        $file = match ($this->record['file_type']) {
            InvoiceRecords::FILE_TYPE_PDF => $this->generatePdf($data),
            InvoiceRecords::FILE_TYPE_WORD => $this->generateWord($data),
            InvoiceRecords::FILE_TYPE_EXCEL => $this->generateExcel($data),
        };

        if (empty($file)) {
            $this->updateRecords(['status' => InvoiceRecords::FAIL_STATUS]);
            info('生成发票文件失败，请重试');
            return false;
        }

        /*$result = $this->mergePdf($pdfFile);
        if (empty($result)) {
            $this->updateRecords(['status' => InvoiceRecords::FAIL_STATUS]);

            info('最后合并PDF文件失败，请重试');
            return false;
        }*/

        $this->updateRecords([
            'seller_info' => json_encode($data['seller_info'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'buyer_info' => json_encode($data['buyer_info'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'total_price' => $data['sub_total'],
            'status' => InvoiceRecords::NORMAL_STATUS,
            'storage_url' => $file,
        ]);

        //信用卡充值
        switch ($this->record['source_type']) {
            case  InvoiceRecords::TYPE_CREDIT_CARD_RECHARGE;
                $list = CreditCardRechargeRecord::query()
                    ->whereIn('id', $this->ids)
                    ->where('status', CreditCardRechargeRecord::STATUS_SUCCESS)
                    ->orderByDesc('created_at')
                    ->get();
                if (!empty($list)) {
                    $fortySeasService = app(FortySeasService::class);
                    $requestApi = app(RequestApi::class);
                    foreach ($list as &$value) {
                        switch ($value['type']) {
                            case CreditCardRechargeRecord::TYPE_40SEAS:
                                $user = User::where('id', $value['user_id'])->first();
                                if (empty($user['buyer_id'])){
                                    // 创建40Seas客户
                                    $buyerId = $fortySeasService->createBuyer($user);
                                    if (empty($buyerId)) {
                                        info('创建40Seas客户失败，请重试，信用卡表中ID：' . $value['id']);
                                        break;
                                    }
                                    $user['buyer_id'] = $buyerId;
                                }

                                $createInvoice = $requestApi->createInvoice($value['currency'], $user['buyer_id'], $value['amount'], $value['transaction_id']);
                                if (empty($createInvoice)) {
                                    info('创建发票失败，请重试，信用卡表中ID：' . $value['id']);
                                    break;
                                }
                                $markInvoiceAsPaid = $requestApi->markInvoiceAsPaid($createInvoice['id'], $value['amount'],Carbon::parse($value['created_at'])->toDateString());
                                if (empty($markInvoiceAsPaid)) {
                                    info('创建发票失败，请重试，信用卡表中ID：' . $value['id']);
                                    break;
                                }
                                break;
                        }

                    }
                }
                break;
        }

        info('执行完成，生成发票成功', ['storage_path' => $file]);
        return true;
    }

    /**
     * 获取PDF生成所需的数据
     * @return array
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/9/24 10:57
     */
    public function getData()
    {
        $logo = BaseService::getConfigValue('client_menu_logo');
        $record = $this->record;

        $customData = Custom::query()->with(['balance', 'invoiceAddress'])->find($record['custom_id']);
        if (empty($customData) || empty($customData->invoiceAddress)) {
            info('客户未设置发票信息不存在，暂无法生成发票');
            return [];
        }

        app()->setLocale($customData->default_language ?? 'en_US');

        $data = [
            'invoice_no' => $record['invoice_no'],
            'created_at' => date("Y-m-d", strtotime($record['created_at'])),
            'client_menu_logo' => $logo ?: '',
            'seller_info' => $this->buildSellerInfoFromSysUser($this->adminInfo),
            'buyer_info' => $this->buildBuyerInfo($customData),
        ];

        $statisticData = match ($record['source_type']) {
            InvoiceRecords::TYPE_ORDER => $this->getOrderInvoiceData(),
            InvoiceRecords::TYPE_TRANSFER_RECHARGE => $this->getRechargeInvoiceData(),
            InvoiceRecords::TYPE_ONLINE_RECHARGE => $this->getOnlineRechargeInvoiceData(),
            InvoiceRecords::TYPE_CREDIT_CARD_RECHARGE => $this->getCreditCardRechargeInvoiceData(),
        };

        return array_merge($data, $statisticData);
    }

    /**
     * 更新发票记录
     * @param array $data
     * @return int|true
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/9/23 18:42
     */
    public function updateRecords(array $data)
    {
        if (empty($data)) {
            return true;
        }
        return InvoiceRecords::query()->whereKey($this->record['id'])->update($data);
    }

    /**
     * 获取充值发票数据
     * @return array
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/9/23 18:43
     */
    public function getRechargeInvoiceData()
    {
        $data = ['list' => [], 'sub_total' => 0, 'confirm_payment' => 0];

        $list = RechargeApply::query()
            ->with(['paymentType', 'payMethod'])
            ->whereIn('id', $this->ids)
            ->orderByDesc('created_at')
            ->get();
        if (!empty($list)) {
            $newList = [];
            $subTotal = 0;
            $confirmPayment = 0;

            foreach ($list as $value) {

                $newList[] = [
                    'id' => $value->id,
                    'create_date' => Carbon::parse($value->created_at)->toDateString(),
                    'payment_method' => $this->transferRechargePaymentMethodName($value),
                    'transaction_id' => (string)$value->serial_no,
                    'amount' => bcdiv($value->apply_amount, 100, 2),
                    'currency' => $value->currency,
                ];

                $subTotal += $value->apply_amount;
                $confirmPayment += $value->confirm_amount;
            }

            $data = [
                'list' => $newList,
                'sub_total' => bcdiv($subTotal, 100, 2),
                'confirm_payment' => bcdiv($confirmPayment, 100, 2)
            ];
        }

        return $data;
    }

    /**
     * 获取在线充值发票数据
     * @return array
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/9/23 18:43
     */
    public function getOnlineRechargeInvoiceData()
    {
        $data = ['list' => [], 'sub_total' => 0, 'confirm_payment' => 0];

        $list = BalanceRecharge::query()
            ->whereIn('id', $this->ids)
            ->where('status', BalanceRecharge::STATUS_SUCCESS)
            ->orderByDesc('created_at')
            ->get();

        if (!empty($list)) {
            $newList = [];
            $subTotal = 0;
            $confirmPayment = 0;

            foreach ($list as $value) {
                $newList[] = [
                    'id' => $value->id,
                    'create_date' => Carbon::parse($value->created_at)->toDateString(),
                    'payment_method' => $this->onlineTypeName($value->type),
                    'transaction_id' => (string)$value->out_trade_no,
                    'amount' => customNumberFormat($value->recharge_amount, 2),
                    'currency' => $value->currency,
                ];

                $subTotal += $value->recharge_amount;
                $confirmPayment += $value->recharge_amount;
            }

            $data = [
                'list' => $newList,
                'sub_total' => $subTotal,
                'confirm_payment' => $confirmPayment
            ];
        }

        return $data;
    }

    /**
     * 获取信用卡充值发票数据
     * @return array
     */
    public function getCreditCardRechargeInvoiceData()
    {
        $data = ['list' => [], 'sub_total' => 0, 'confirm_payment' => 0];

        $list = CreditCardRechargeRecord::query()
            ->whereIn('id', $this->ids)
            ->where('status', CreditCardRechargeRecord::STATUS_SUCCESS)
            ->orderByDesc('created_at')
            ->get();
        if (!empty($list)) {
            $newList = [];
            $subTotal = 0;
            $confirmPayment = 0;

            foreach ($list as $value) {
                $paymentMethod = (int)$value->type === CreditCardRechargeRecord::TYPE_40SEAS ? '40Seas' : (string)$value->type;
                $newList[] = [
                    'id' => $value->id,
                    'create_date' => Carbon::parse($value->created_at)->toDateString(),
                    'payment_method' => $paymentMethod,
                    'transaction_id' => (string)$value->transaction_id,
                    'amount' => customNumberFormat($value->amount, 2),
                    'currency' => $value->currency,
                ];

                $subTotal += $value->amount;
                $confirmPayment += $value->amount;
            }

            $data = [
                'list' => $newList,
                'sub_total' => $subTotal,
                'confirm_payment' => $confirmPayment
            ];
        }
        return $data;
    }

    /**
     * 获取订单发票数据
     * @return array
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/9/23 16:48
     */
    public function getOrderInvoiceData()
    {
        $data = ['list' => [], 'sub_total' => 0];

        $list = Order::query()
            ->with(['lineItems', 'shippingAddress:id,order_id,country_code'])
            ->whereIn('id', $this->ids)
            ->orderByDesc('created_at')
            ->get();

        if (!empty($list)) {
            $newList = [];
            $subTotal = 0;

            foreach ($list as $value) {
                //开启商品一口价 物流费用为sku的物流总报价，反之就是订单物流费用
                $logisticsFee = $value->order_one_price == 1 ? 0 : $value->logistics_fee;

                $skuList = [];
                foreach ($value->lineItems ?? [] as $item) {
                    $skuList[] = [
                        'sku_id' => $item->sku,
                        'price' => $item->quote_price ?: 0,
                        'quantity' => $item->quantity,
                    ];
                }
                $vendorPrice = (float)($value->vendor_price ?? 0);
                $otherSupplementPrice = (float)($value->other_supplement_price ?? 0);
                $supplementPrice = (float)($value->supplement_price ?? 0);
                $favourablePrice = (float)($value->favourable_price ?? 0);
                $refundPrice = (float)($value->refund_price ?? 0);

                $originalAmount = (float)sprintf("%.2f", ($vendorPrice + (float)$logisticsFee + $otherSupplementPrice + $supplementPrice));
                $itemSubTotal = (float)sprintf('%.2f', ($originalAmount - $favourablePrice - $refundPrice));

                $subTotal += $itemSubTotal;

                $newList[] = [
                    'id' => (string)$value->name,
                    'order_sn' => (string)$value->order_id,
                    'sub_total' => customNumberFormat($itemSubTotal, 2),
                    'logistics_fee' => customNumberFormat((float)$logisticsFee, 2),
                    'country_code' => $value->shippingAddress->country_code ?? '-',
                    'original_amount' => $originalAmount,
                    'other_amount' => customNumberFormat($otherSupplementPrice, 2),
                    'additional_amount' => customNumberFormat($supplementPrice, 2),
                    'discount_amount'   => customNumberFormat($favourablePrice, 2),
                    'refund_amount'     => customNumberFormat($refundPrice, 2),
                    'sku_list'          => $skuList,
                    'created_at'        => (string)$value->created_at,
                    'payment_time'      => (string)($value->paymented_at ?? ''),
                    'platform_order_number' => (string)$value->order_id,
                    'system_order_number' => (string)($value->custom_order_id ?? ''),
                    'create_time' => (string)$value->created_at,
                ];
            }

            $data = ['list' => $newList, 'sub_total' => customNumberFormat($subTotal)];
        }
        return $data;
    }

    private function normalizeSellerInfo(string $invoiceInfo): array
    {
        $normalized = preg_replace('/[\r\n]/', ',', $invoiceInfo);
        $parts = array_filter(array_map(static fn ($v) => trim($v), explode(',', (string)$normalized)));
        if (empty($parts)) {
            return ['-'];
        }
        return array_values($parts);
    }

    private function buildSellerInfoFromSysUser(array $adminInfo): array
    {
        $userId = isset($adminInfo['id']) ? (int)$adminInfo['id'] : 0;
        if ($userId > 0) {
            try {
                $user = DB::table('sys_user')
                    ->select(['username', 'invoice_info'])
                    ->where('id', $userId)
                    ->where('deleted', 0)
                    ->first();
                if (!empty($user)) {
                    $raw = trim((string)($user->username ?? '') . PHP_EOL . (string)($user->invoice_info ?? ''));
                    return $this->normalizeSellerInfo($raw);
                }
            } catch (\Throwable $e) {
                info('从sys_user获取发票抬头失败，使用回退数据', ['user_id' => $userId, 'msg' => $e->getMessage()]);
            }
        }

        $raw = trim((string)($adminInfo['username'] ?? '') . PHP_EOL . (string)($adminInfo['invoice_info'] ?? ''));
        return $this->normalizeSellerInfo($raw);
    }

    private function buildBuyerInfo(Custom $customData): array
    {
        $invoiceAddress = (array)($customData->invoiceAddress?->toArray() ?? []);
        $balance = $customData->balance;
        return [
            'id' => $customData->id,
            'custom_name' => (string)($customData->custom_name ?? ''),
            'custom_email' => (string)($customData->custom_email ?? ''),
            'custom_phone' => (string)($customData->custom_phone ?? ''),
            'custom_address' => (string)($customData->custom_address ?? ''),
            'residual_credit' => customNumberFormat((float)($balance->residual_credit ?? 0), 2),
            'credit_line' => customNumberFormat((float)($balance->credit_line ?? 0), 2),
            'invoice_address' => [
                'name' => (string)($invoiceAddress['name'] ?? ''),
                'first_name' => (string)($invoiceAddress['first_name'] ?? ''),
                'last_name' => (string)($invoiceAddress['last_name'] ?? ''),
                'country' => (string)($invoiceAddress['country'] ?? ''),
                'province' => (string)($invoiceAddress['province'] ?? ''),
                'city' => (string)($invoiceAddress['city'] ?? ''),
                'address_detail' => (string)($invoiceAddress['address_detail'] ?? ''),
                'postcode' => (string)($invoiceAddress['postcode'] ?? ''),
                'phone_area_code' => (string)($invoiceAddress['phone_area_code'] ?? ''),
                'phone_number' => (string)($invoiceAddress['phone_number'] ?? ''),
                'email' => (string)($invoiceAddress['email'] ?? ''),
                'tax' => (string)($invoiceAddress['tax'] ?? ''),
            ],
        ];
    }

    private function transferRechargePaymentMethodName(RechargeApply $value): string
    {
        if (!empty($value->payment_type_id)) {
            $name = (string)($value->paymentType->name ?? '');
            if ($name !== '') {
                return $name;
            }
            $paymentSetting = PaymentSetting::query()->find($value->payment_type_id);
            if (!empty($paymentSetting?->name)) {
                return (string)$paymentSetting->name;
            }
        }
        if (!empty($value->pay_method)) {
            $name = (string)($value->payMethod->name ?? '');
            if ($name !== '') {
                return $name;
            }
            $chargePayMethod = ChargePayMethod::query()->find($value->pay_method);
            if (!empty($chargePayMethod?->name)) {
                return (string)$chargePayMethod->name;
            }
        }
        return '-';
    }

    private function onlineTypeName(?int $type): string
    {
        if ($type === BalanceRecharge::TYPE_PAYPAL) {
            return 'PayPal';
        }
        return '-';
    }

    private function getDefaultCheckConfig(bool $orderMode): array
    {
        if ($orderMode) {
            return [
                'platformNo' => true,
                'productName' => true,
                'sku' => true,
                'count' => true,
                'country' => true,
                'amount' => true,
                'paymentTime' => true,
                'platformOrderNumber' => false,
                'systemOrderNumber' => false,
                'createTime' => false,
            ];
        }
        return [
            'serialId' => true,
            'paymentStyle' => true,
            'amount' => true,
            'date' => true,
        ];
    }

    private function buildHeaderColumns(array $checkConfig, bool $orderMode): array
    {
        $columns = [];
        if ($orderMode) {
            if ($checkConfig['platformNo'] ?? true) {
                $columns[] = ['fieldName' => 'platformNo', 'displayName' => 'Platform No'];
            }
            if ($checkConfig['productName'] ?? true) {
                $columns[] = ['fieldName' => 'productName', 'displayName' => 'Product Name'];
            }
            if ($checkConfig['sku'] ?? true) {
                $columns[] = ['fieldName' => 'sku', 'displayName' => 'Sku'];
            }
            if ($checkConfig['count'] ?? true) {
                $columns[] = ['fieldName' => 'count', 'displayName' => 'Count'];
            }
            if ($checkConfig['country'] ?? true) {
                $columns[] = ['fieldName' => 'country', 'displayName' => 'Shipping Country'];
            }
            if ($checkConfig['amount'] ?? true) {
                $columns[] = ['fieldName' => 'amount', 'displayName' => 'Price（$）'];
            }
            if ($checkConfig['paymentTime'] ?? true) {
                $columns[] = ['fieldName' => 'paymentTime', 'displayName' => 'Payment Time'];
            }
            if ($checkConfig['platformOrderNumber'] ?? false) {
                $columns[] = ['fieldName' => 'platformOrderNumber', 'displayName' => 'Platform Order Number'];
            }
            if ($checkConfig['systemOrderNumber'] ?? false) {
                $columns[] = ['fieldName' => 'systemOrderNumber', 'displayName' => 'System Order Number'];
            }
            if ($checkConfig['createTime'] ?? false) {
                $columns[] = ['fieldName' => 'createTime', 'displayName' => 'Create Time'];
            }
        } else {
            if ($checkConfig['serialId'] ?? true) {
                $columns[] = ['fieldName' => 'serialId', 'displayName' => 'serialId'];
            }
            if ($checkConfig['paymentStyle'] ?? true) {
                $columns[] = ['fieldName' => 'paymentStyle', 'displayName' => 'paymentStyle'];
            }
            if ($checkConfig['amount'] ?? true) {
                $columns[] = ['fieldName' => 'amount', 'displayName' => 'amount'];
            }
            if ($checkConfig['date'] ?? true) {
                $columns[] = ['fieldName' => 'date', 'displayName' => 'date'];
            }
        }
        return $columns;
    }

    private function buildPdfHeaderColumns(array $checkConfig, bool $orderMode): array
    {
        $columns = [];
        if ($orderMode) {
            if ($checkConfig['platformNo'] ?? true) {
                $columns[] = ['fieldName' => 'platformNo', 'displayName' => 'Platform No'];
            }
            if ($checkConfig['productName'] ?? true) {
                $columns[] = ['fieldName' => 'productName', 'displayName' => 'Product Name'];
            }
            if ($checkConfig['sku'] ?? true) {
                $columns[] = ['fieldName' => 'sku', 'displayName' => 'Sku'];
            }
            if ($checkConfig['count'] ?? true) {
                $columns[] = ['fieldName' => 'count', 'displayName' => 'Count'];
            }
            if ($checkConfig['country'] ?? true) {
                $columns[] = ['fieldName' => 'country', 'displayName' => 'Shipping Country'];
            }
            if ($checkConfig['amount'] ?? true) {
                $columns[] = ['fieldName' => 'amount', 'displayName' => 'Price（$）'];
            }
            if ($checkConfig['paymentTime'] ?? true) {
                $columns[] = ['fieldName' => 'paymentTime', 'displayName' => 'Payment Time'];
            }
            if ($checkConfig['platformOrderNumber'] ?? false) {
                $columns[] = ['fieldName' => 'platformOrderNumber', 'displayName' => 'Platform Order Number'];
            }
            if ($checkConfig['systemOrderNumber'] ?? false) {
                $columns[] = ['fieldName' => 'systemOrderNumber', 'displayName' => 'System Order Number'];
            }
            if ($checkConfig['createTime'] ?? false) {
                $columns[] = ['fieldName' => 'createTime', 'displayName' => 'Create Time'];
            }
        } else {
            if ($checkConfig['serialId'] ?? true) {
                $columns[] = ['fieldName' => 'serialId', 'displayName' => 'Transaction ID'];
            }
            if ($checkConfig['paymentStyle'] ?? true) {
                $columns[] = ['fieldName' => 'paymentStyle', 'displayName' => 'Payment Method'];
            }
            if ($checkConfig['amount'] ?? true) {
                $columns[] = ['fieldName' => 'amount', 'displayName' => 'Amount'];
            }
            if ($checkConfig['date'] ?? true) {
                $columns[] = ['fieldName' => 'date', 'displayName' => 'Date'];
            }
        }
        return $columns;
    }

    private function coord(int $columnIndex, int $rowIndex): string
    {
        return Coordinate::stringFromColumnIndex($columnIndex + 1) . ($rowIndex + 1);
    }

    private function applyStyleByArray(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $cell, array $style): void
    {
        $sheet->getStyle($cell)->applyFromArray($style);
    }

    private function invoiceDataStyle(string $fontName = 'Arial', bool $bold = false, int $size = 11): array
    {
        return [
            'font' => ['name' => $fontName, 'bold' => $bold, 'size' => $size],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ];
    }

    private function invoiceHeaderStyle(): array
    {
        return [
            'font' => ['bold' => true, 'name' => 'Arial', 'size' => 11],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FFC0CB'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'DDDDDD'],
                ],
            ],
        ];
    }

    private function invoiceTotalStyle(): array
    {
        return [
            'font' => ['bold' => true, 'name' => 'Arial', 'size' => 11],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FFE5EC'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'DDDDDD'],
                ],
            ],
        ];
    }

    private function buildOrderRowsForExport(array $list): array
    {
        $rows = [];
        foreach ($list as $value) {
            $rows[] = [
                'platformNo' => (string)($value['id'] ?? ''),
                'productName' => $this->formatOrderItemText($value),
                'sku' => '',
                'count' => '',
                'country' => (string)($value['country_code'] ?? '-'),
                'amount' => customNumberFormat((float)($value['sub_total'] ?? 0), 2),
                'paymentTime' => (string)($value['payment_time'] ?? ''),
                'platformOrderNumber' => (string)($value['platform_order_number'] ?? ''),
                'systemOrderNumber' => (string)($value['system_order_number'] ?? ''),
                'createTime' => (string)($value['create_time'] ?? ''),
            ];
        }
        return $rows;
    }

    private function buildRechargeRowsForExport(array $list): array
    {
        $rows = [];
        foreach ($list as $value) {
            $rows[] = [
                'serialId' => (string)($value['transaction_id'] ?? ''),
                'paymentStyle' => (string)($value['payment_method'] ?? '-'),
                'amount' => customNumberFormat((float)($value['amount'] ?? 0), 2),
                'date' => (string)($value['create_date'] ?? ''),
            ];
        }
        return $rows;
    }

    private function formatOrderItemText(array $value): string
    {
        $lines = [];
        foreach (($value['sku_list'] ?? []) as $item) {
            $lines[] = 'SKU：' . ($item['sku_id'] ?? '') .
                ' Unit Price：$' . customNumberFormat((float)($item['price'] ?? 0), 2) .
                ' QTY：' . (int)($item['quantity'] ?? 0);
        }
        $lines[] = 'Shipping Costs: ' . (((float)($value['logistics_fee'] ?? 0)) > 0 ? '$' . customNumberFormat((float)$value['logistics_fee'], 2) : 'Free');
        if ((float)($value['other_amount'] ?? 0) > 0) {
            $lines[] = 'Other Costs：$' . customNumberFormat((float)$value['other_amount'], 2);
        }
        if ((float)($value['additional_amount'] ?? 0) > 0) {
            $lines[] = 'Additional Costs: $' . customNumberFormat((float)$value['additional_amount'], 2);
        }
        if ((float)($value['discount_amount'] ?? 0) > 0) {
            $lines[] = 'Discount Amount：$ -' . customNumberFormat((float)$value['discount_amount'], 2);
        }
        if ((float)($value['refund_amount'] ?? 0) > 0) {
            $lines[] = 'Refund Amount：$ -' . customNumberFormat((float)$value['refund_amount'], 2);
        }
        return implode("\n", $lines);
    }

    private function buildOrderBillToText(array $buyerInfo): string
    {
        $addr = $buyerInfo['invoice_address'] ?? [];
        $lines = [];
        $lines[] = (string)($addr['name'] ?? '');
        if (!empty($addr['phone_number'])) {
            $lines[] = '+ ' . trim(((string)($addr['phone_area_code'] ?? '')) . ' ' . ((string)$addr['phone_number']));
        }
        if (!empty($addr['email'])) {
            $lines[] = (string)$addr['email'];
        }
        $lines[] = trim(implode(' ', array_filter([
            (string)($addr['country'] ?? ''),
            (string)($addr['province'] ?? ''),
            (string)($addr['city'] ?? ''),
            (string)($addr['address_detail'] ?? ''),
            (string)($addr['postcode'] ?? ''),
        ])));
        if (!empty($addr['tax'])) {
            $lines[] = (string)$addr['tax'];
        }
        return trim(implode("\n", array_filter($lines, static fn ($v) => $v !== '')));
    }

    private function buildRechargeBillToText(array $buyerInfo): string
    {
        $lines = [];
        $lines[] = (string)($buyerInfo['custom_name'] ?? '');
        if (!empty($buyerInfo['custom_phone'])) {
            $lines[] = (string)$buyerInfo['custom_phone'];
        }
        if (!empty($buyerInfo['custom_email'])) {
            $lines[] = (string)$buyerInfo['custom_email'];
        }
        if (!empty($buyerInfo['custom_address'])) {
            $lines[] = (string)$buyerInfo['custom_address'];
        }
        return trim(implode("\n", $lines));
    }

    private function rowValueByField(array $row, string $fieldName): string
    {
        return (string)($row[$fieldName] ?? '');
    }

    private function wordFontByField(string $fieldName): array
    {
        if (in_array($fieldName, ['platformNo', 'serialId'], true)) {
            return ['name' => 'SimSun', 'bold' => true, 'size' => 14];
        }
        if (in_array($fieldName, ['paymentTime', 'platformOrderNumber', 'systemOrderNumber', 'createTime', 'date'], true)) {
            return ['name' => 'Arial', 'bold' => true, 'size' => 12];
        }
        return ['name' => 'SimSun', 'bold' => false, 'size' => 11];
    }

    private function insertWordInvoiceTable(PhpWord $phpword, \PhpOffice\PhpWord\Element\Section $section, array $rows, array $headerColumns): void
    {
        $styleName = 'invoiceTableExcelLike';
        $phpword->addTableStyle($styleName, [
            'borderSize' => 6,
            'borderColor' => 'DDDDDD',
            'cellMargin' => 80,
            'width' => 9072,
            'unit' => 'dxa',
        ]);
        $table = $section->addTable($styleName);
        $columnCount = max(1, count($headerColumns));
        $cellWidth = (int)floor(9072 / $columnCount);

        $table->addRow();
        foreach ($headerColumns as $column) {
            $table->addCell($cellWidth, ['bgColor' => 'FFC0CB'])
                ->addText((string)$column['displayName'], ['name' => 'Arial', 'bold' => true, 'size' => 11]);
        }

        foreach ($rows as $row) {
            $table->addRow();
            foreach ($headerColumns as $column) {
                $fieldName = (string)$column['fieldName'];
                $font = $this->wordFontByField($fieldName);
                $table->addCell($cellWidth)->addText(
                    $this->rowValueByField($row, $fieldName),
                    ['name' => $font['name'], 'bold' => $font['bold'], 'size' => $font['size']]
                );
            }
        }

        $section->addText('');
    }

    private function resolveLogoLocalPath(string $logoUrl): ?string
    {
        if ($logoUrl === '') {
            return null;
        }
        try {
            $basename = basename(parse_url($logoUrl, PHP_URL_PATH) ?: $logoUrl);
            if ($basename !== '' && Storage::disk('admin_public')->exists($basename)) {
                return Storage::disk('admin_public')->path($basename);
            }
            $content = @file_get_contents($logoUrl);
            if ($content === false) {
                return null;
            }
            $tmpFile = tempnam(sys_get_temp_dir(), 'invoice_logo_');
            if ($tmpFile === false) {
                return null;
            }
            file_put_contents($tmpFile, $content);
            return $tmpFile;
        } catch (\Throwable $e) {
            info('logo下载失败', ['msg' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * 生成PDF
     * @param $data
     * @return false|string
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/9/23 18:17
     */
    public function generatePdf($data)
    {
        $filename = $this->getFileName();
        info('pdf生成数据', $data);

        $savePath = '/invoice/' . $filename;

        try {
            $orderMode = (int)$this->record['source_type'] === InvoiceRecords::TYPE_ORDER;
            $checkConfig = $this->getDefaultCheckConfig($orderMode);
            $pdfData = $data;
            $pdfData['header_columns'] = $this->buildPdfHeaderColumns($checkConfig, $orderMode);
            $pdfData['rows'] = $orderMode
                ? $this->buildOrderRowsForExport($data['list'] ?? [])
                : $this->buildRechargeRowsForExport($data['list'] ?? []);

            Storage::disk('admin_public')->makeDirectory('invoice');
            $filePath = Storage::disk('admin_public')->path($savePath);

            \PDF::loadView(
                'invoice.' . ($orderMode ? 'order' : 'recharge'), ['data' => $pdfData]
            )->setOptions([
                'dpi' => 300,
                'margin-top' => 10,
                'margin-bottom' => 10,
                'margin-left' => 10,
                'margin-right' => 10,
            ])->save($filePath, true);

            // 上传到存储桶
            $url = CosUtil::localUploadToCos($savePath);

            info('pdf生成发票成功', ['file_path' => $url]);
            return $url;
        } catch (\Throwable $throwable) {
            info('pdf生成发票失败', ['msg' => $throwable->getMessage(), 'file' => $throwable->getFile(), 'line' => $throwable->getLine()]);
            return false;
        }
    }

    /**
     * 生成word文件
     * @param $data
     * @return false|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\UrlGenerator|string
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2025/1/3 13:57
     */
    public function generateWord($data)
    {
        $filename = $this->getFileName();
        info('word生成数据', $data);

        $savePath = '/invoice/' . $filename;

        try {
            $orderMode = (int)$this->record['source_type'] === InvoiceRecords::TYPE_ORDER;
            $checkConfig = $this->getDefaultCheckConfig($orderMode);
            $headerColumns = $this->buildHeaderColumns($checkConfig, $orderMode);
            $rows = $orderMode ? $this->buildOrderRowsForExport($data['list'] ?? []) : $this->buildRechargeRowsForExport($data['list'] ?? []);
            $phpword = new PhpWord();
            $section = $phpword->addSection([
                'marginTop' => 720,
                'marginBottom' => 720,
                'marginLeft' => 720,
                'marginRight' => 720,
            ]);
            $section->addText('INVOICE', ['name' => 'Arial', 'bold' => true, 'size' => 20]);
            $section->addText('No.' . ($data['invoice_no'] ?? ''), ['name' => 'Arial', 'size' => 11]);
            $section->addText('Issued ' . ($data['created_at'] ?? ''), ['name' => 'Arial', 'size' => 11]);

            $section->addTextBreak();
            $section->addText('Seller', ['name' => 'Arial', 'bold' => true, 'size' => 14]);
            foreach (($data['seller_info'] ?? ['-']) as $line) {
                $section->addText((string)$line, ['name' => 'Arial', 'size' => 11]);
            }

            $section->addTextBreak();
            $section->addText('Bill To', ['name' => 'Arial', 'bold' => true, 'size' => 14]);
            $billTo = $orderMode ? $this->buildOrderBillToText($data['buyer_info'] ?? []) : $this->buildRechargeBillToText($data['buyer_info'] ?? []);
            foreach (explode("\n", $billTo) as $line) {
                $section->addText($line, ['name' => 'Arial', 'size' => 11]);
            }

            $this->insertWordInvoiceTable($phpword, $section, $rows, $headerColumns);

            $section->addText('Subtotal: $' . customNumberFormat((float)($data['sub_total'] ?? 0), 2), ['name' => 'Arial', 'bold' => true, 'size' => 12]);
            if (!$orderMode) {
                $section->addText('Payments: $' . customNumberFormat((float)($data['confirm_payment'] ?? 0), 2), ['name' => 'Arial', 'bold' => true, 'size' => 11]);
                $section->addText('Credit: $' . customNumberFormat((float)($data['buyer_info']['residual_credit'] ?? 0), 2), ['name' => 'Arial', 'bold' => true, 'size' => 11]);
            }

            Storage::disk('admin_public')->makeDirectory('invoice');
            $filePath = Storage::disk('admin_public')->path($savePath);
            IOFactory::createWriter($phpword, 'Word2007')->save($filePath);

            $url = CosUtil::localUploadToCos($savePath);
            info('word生成发票成功', ['file_path' => $url]);
            return $url;
        } catch (\Throwable $e) {
            info('word生成发票失败', ['msg' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
            return false;
        }
    }

    /**
     * 生成excel发票文件
     * @param $data
     * @return false|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\UrlGenerator|string
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2025/1/11 16:17
     */
    public function generateExcel($data)
    {
        $filename = $this->getFileName();
        info('excel生成数据', $data);
        $savePath = '/invoice/' . $filename;
        $tmpLogoPath = null;
        try {
            $orderMode = (int)$this->record['source_type'] === InvoiceRecords::TYPE_ORDER;
            $checkConfig = $this->getDefaultCheckConfig($orderMode);
            $headerColumns = $this->buildHeaderColumns($checkConfig, $orderMode);
            $rows = $orderMode ? $this->buildOrderRowsForExport($data['list'] ?? []) : $this->buildRechargeRowsForExport($data['list'] ?? []);

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Images');
            $spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(11);
            $rowNum = 1; // 与Java逻辑一致，按0基行号推进

            $tmpLogoPath = $this->resolveLogoLocalPath('https://juanxiaochi.oss-us-east-1.aliyuncs.com/template/Mate_Platform/invoice_icon.jpg');
            if ($tmpLogoPath !== null && is_file($tmpLogoPath)) {
                $sheet->getRowDimension(1)->setRowHeight(40);
                $drawing = new Drawing();
                $drawing->setPath($tmpLogoPath);
                $drawing->setCoordinates('A1');
                $drawing->setWidth(380);
                $drawing->setHeight(40);
                $drawing->setWorksheet($sheet);
            }

            $sellerRows = $data['seller_info'] ?? ['-'];
            $buyerInfo = $data['buyer_info'] ?? [];
            $invoiceDateInt = (int)str_replace('-', '', (string)($data['created_at'] ?? date('Y-m-d')));

            // 第2行空行
            $rowNum++;

            // 第3行公司名
            $companyNameCell = $this->coord(0, $rowNum++);
            $sheet->setCellValue($companyNameCell, (string)($sellerRows[0] ?? '-'));
            $this->applyStyleByArray($sheet, $companyNameCell, $this->invoiceDataStyle('Arial', true, 11));

            // 第4行公司地址
            $companyAddressCell = $this->coord(0, $rowNum++);
            $sheet->setCellValue($companyAddressCell, (string)($sellerRows[1] ?? ''));
            $this->applyStyleByArray($sheet, $companyAddressCell, $this->invoiceDataStyle('Arial', false, 11));

            // 第5行客户信息标题
            $billToCell = $this->coord(0, $rowNum);
            $customerTitleCell = $this->coord(1, $rowNum);
            $customerNameCell = $this->coord(2, $rowNum++);
            $sheet->setCellValue($billToCell, 'BILL TO');
            $sheet->setCellValue($customerTitleCell, '客户店铺名');
            $sheet->setCellValue($customerNameCell, (string)($buyerInfo['custom_name'] ?? ''));
            $this->applyStyleByArray($sheet, $billToCell, $this->invoiceDataStyle('Arial', true, 11));
            $this->applyStyleByArray($sheet, $customerTitleCell, $this->invoiceDataStyle('SimSun', false, 11));

            // 第6行客户地址和发票日期
            $customerAddressTitleCell = $this->coord(1, $rowNum);
            $customerAddressCell = $this->coord(2, $rowNum);
            $invoiceDateTitleCell = $this->coord(4, $rowNum);
            $invoiceDateCell = $this->coord(5, $rowNum++);
            $sheet->setCellValue($customerAddressTitleCell, '客户地址');
            $sheet->setCellValue($customerAddressCell, (string)($buyerInfo['custom_address'] ?? ''));
            $sheet->setCellValue($invoiceDateTitleCell, 'INVOICE DATE');
            $sheet->setCellValue($invoiceDateCell, $invoiceDateInt);
            $this->applyStyleByArray($sheet, $customerAddressTitleCell, $this->invoiceDataStyle('SimSun', false, 11));
            $this->applyStyleByArray($sheet, $invoiceDateTitleCell, $this->invoiceDataStyle('Arial', true, 11));
            $this->applyStyleByArray($sheet, $invoiceDateCell, $this->invoiceDataStyle('Arial', false, 11));

            // 第7行空行
            $rowNum++;

            // 第8行表头
            $headerStyle = $this->invoiceHeaderStyle();
            $headerRowIndex = $rowNum++;
            foreach ($headerColumns as $index => $column) {
                $cell = $this->coord($index, $headerRowIndex);
                $sheet->setCellValue($cell, $column['displayName']);
                $this->applyStyleByArray($sheet, $cell, $headerStyle);
            }

            $orderNumberStyle = $this->invoiceDataStyle('SimSun', true, 14);
            $productStyle = $this->invoiceDataStyle('SimSun', false, 11);
            $orderTimeStyle = $this->invoiceDataStyle('Arial', true, 12);

            foreach ($rows as $row) {
                $dataRowIndex = $rowNum++;
                foreach ($headerColumns as $index => $column) {
                    $fieldName = (string)$column['fieldName'];
                    $cell = $this->coord($index, $dataRowIndex);
                    $rawValue = $row[$fieldName] ?? '';

                    if (in_array($fieldName, ['count'], true)) {
                        $sheet->setCellValue($cell, (int)$rawValue);
                    } elseif (in_array($fieldName, ['amount'], true)) {
                        $sheet->setCellValue($cell, (float)$rawValue);
                    } else {
                        $sheet->setCellValueExplicit($cell, (string)$rawValue, DataType::TYPE_STRING);
                    }

                    if (in_array($fieldName, ['platformNo', 'serialId'], true)) {
                        $this->applyStyleByArray($sheet, $cell, $orderNumberStyle);
                    } elseif (in_array($fieldName, ['productName', 'sku', 'count', 'country', 'amount', 'paymentStyle'], true)) {
                        $this->applyStyleByArray($sheet, $cell, $productStyle);
                    } else {
                        $this->applyStyleByArray($sheet, $cell, $orderTimeStyle);
                    }
                }
            }

            // 总计区域
            $rowNum += 2;
            $subTotal = (float)($data['sub_total'] ?? 0);
            $lastColumnIndex = max(0, count($headerColumns) - 1);
            $summaryColumnIndex = count($headerColumns) > 5 ? 5 : $lastColumnIndex;
            $totalStyle = $this->invoiceTotalStyle();

            $descriptionRowIndex = $rowNum++;
            $descriptionCell = $this->coord(0, $descriptionRowIndex);
            $descriptionAmountCell = $this->coord($summaryColumnIndex, $descriptionRowIndex);
            $sheet->setCellValue($descriptionCell, 'DESCRIPTION');
            $sheet->setCellValue($descriptionAmountCell, $subTotal);
            $this->applyStyleByArray($sheet, $descriptionCell, $totalStyle);
            for ($i = 1; $i <= min(4, $lastColumnIndex); $i++) {
                $blankCell = $this->coord($i, $descriptionRowIndex);
                if ($sheet->getCell($blankCell)->getValue() === null) {
                    $sheet->setCellValue($blankCell, '');
                }
                $this->applyStyleByArray($sheet, $blankCell, $totalStyle);
            }
            $this->applyStyleByArray($sheet, $descriptionAmountCell, $totalStyle);

            $productFeeStyle = $this->invoiceDataStyle('Arial', false, 11);
            $fee1Cell = $this->coord(0, $rowNum++);
            $sheet->setCellValue($fee1Cell, 'Product and fulfillment fee');
            $this->applyStyleByArray($sheet, $fee1Cell, $productFeeStyle);

            $fee2Cell = $this->coord(0, $rowNum++);
            $sheet->setCellValue($fee2Cell, 'Service fee');
            $this->applyStyleByArray($sheet, $fee2Cell, $productFeeStyle);

            $finalTotalStyle = $this->invoiceDataStyle('Arial', false, 11);
            $totalRowIndex = $rowNum++;
            $totalCell = $this->coord(0, $totalRowIndex);
            $totalAmountCell = $this->coord($summaryColumnIndex, $totalRowIndex);
            $sheet->setCellValue($totalCell, 'TOTAL');
            $sheet->setCellValue($totalAmountCell, $subTotal);
            $this->applyStyleByArray($sheet, $totalCell, $finalTotalStyle);
            $this->applyStyleByArray($sheet, $totalAmountCell, $finalTotalStyle);

            for ($i = 0; $i < count($headerColumns); $i++) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i + 1))->setAutoSize(true);
            }

            Storage::disk('admin_public')->makeDirectory('invoice');
            $filePath = Storage::disk('admin_public')->path($savePath);
            $writer = new Xlsx($spreadsheet);
            $writer->save($filePath);

            return CosUtil::localUploadToCos($savePath);
        } catch (\Throwable $e) {
            info('excel生成发票失败', ['msg' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
            return false;
        } finally {
            if (!empty($tmpLogoPath) && str_starts_with($tmpLogoPath, sys_get_temp_dir())) {
                @unlink($tmpLogoPath);
            }
        }
    }

    /**
     * 合并PDF文件
     * @param $files
     * @return false|string
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/9/23 18:03
     */
    public function mergePdf($files)
    {
        $path = storage_path('app/public');
        $mergeFile = '/admin/';

        $name = $this->getFileName() . '_merge.pdf';

        $outputPath = $path . $mergeFile . $name;

        $filePath = [$outputPath];
        // 本地url
        $localUrl = config('app.url');
        foreach ($files as $file) {
            if (!$file) {
                continue;
            }
            // 解析 URL
            $parsed_url = parse_url($file);
            // 获取协议和主机名
            $protocol = $parsed_url['scheme'] ?? '';
            $host = $parsed_url['host'] ?? '';

            $host = $protocol . '://' . $host;

            if ($localUrl === $host) {
                $filePath = str_replace('/storage', '', $parsed_url['path']);
                $filePath[] = $path . $filePath;
            } else {
                $newFile = $path . '/admin/' . Str::random() . '-tmp.pdf';
                $tmpPdf = file_get_contents($file);
                file_put_contents($newFile, $tmpPdf);
                $filePath[] = $newFile;
            }
        }

        $files = implode(' ', $filePath);

        /*$process = Process::fromShellCommandline("gs -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -sOutputFile=" . $files);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }*/

        if (!config('app.local_storage')) {
            try {
                Storage::disk()
                    ->putFileAs(
                        '/admin',
                        $outputPath,
                        $name
                    );
            } catch (\Throwable $e) {
                logger('cos 保存发票PDF文件失败：' . $e->getMessage());
            }
        }

        unset($filePath[0]);

        if (!config('app.local_storage')) {
            foreach ($filePath as $file) {
                unlink($file);
            }
        }

        if (!file_exists($outputPath)) {
            info('发票最后合并失败', ['msg' => '文件不存在']);
            return false;
        }

        $url = '/storage' . $mergeFile . $name;
        info('发票合并成功', ['url' => $url]);

        return $url;
    }


    /**
     * 获取文件名
     * @return string
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/9/23 17:51
     */
    public function getFileName()
    {
        $prefix = match ($this->record['source_type']) {
            InvoiceRecords::TYPE_ORDER => 'order_invoice',
            InvoiceRecords::TYPE_TRANSFER_RECHARGE => 'transfer_recharge_invoice',
            InvoiceRecords::TYPE_ONLINE_RECHARGE => 'online_recharge_invoice',
            InvoiceRecords::TYPE_CREDIT_CARD_RECHARGE => 'credit_card_recharge_invoice',
        };

        $fileSuffix = match ($this->record['file_type']) {
            InvoiceRecords::FILE_TYPE_PDF => 'pdf',
            InvoiceRecords::FILE_TYPE_WORD => 'docx',
            InvoiceRecords::FILE_TYPE_EXCEL => 'xlsx',
        };

        return $prefix . '_' . date("YmdHis") . '_' . Str::random(8) . '.' . $fileSuffix;
    }
}
