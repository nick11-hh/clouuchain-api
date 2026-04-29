<?php

namespace App\Jobs;

use App\Helper\CosUtil;
use App\Helper\CurrencyConverter;
use App\Lib\Code;

//use App\Lib\Platform;
use App\Models\CreditCardRechargeRecord;
use App\Models\Custom;
use App\Models\User;
use App\Services\Base\SystemConfigService as BaseService;
use App\Services\Client\FortySeasService;
use App\Services\ExpressCompanies\KuaiDi\Exceptions\Exception;
use App\Services\PaymentPlatform\FortySeas\RequestApi;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\Traits\InvoiceTrait;
use Illuminate\Support\Facades\Log;
use App\Models\InvoiceRecords;
use App\Models\Order;
use App\Models\BalanceRecharge;
use App\Models\RechargeApply;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Html;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Vtiful\Kernel\Excel;
use Vtiful\Kernel\Format;
use GuzzleHttp\Client;

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
        if (empty($customData->invoiceAddress)) {
            info('客户未设置发票信息不存在，暂无法生成发票');
            return [];
        }

        app()->setLocale($customData->default_language ?? 'en_US');

        $data = [
            'invoice_no' => $record['invoice_no'],
            'created_at' => date("Y-m-d", strtotime($record['created_at'])),
            'client_menu_logo' => $logo ?: '',
            'seller_info' => preg_replace('/[\r\n]/', ',', $this->adminInfo['invoice_info']),
            'buyer_info' => $customData->toArray(),
        ];

        $data['seller_info'] = explode(',', $data['seller_info']);

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
            ->with(['paymentType'])
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
                    'payment_method' => $value->paymentType->name ?? '-',
                    'transaction_id' => $value->serial_no,
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
                    'payment_method' => $value->type_name,
                    'transaction_id' => $value->out_trade_no,
                    'amount' => $value->recharge_amount,
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
                switch ($value->type) {
                    case CreditCardRechargeRecord::TYPE_40SEAS:
                        $value->type = '40Seas';
                        break;
                }
                $newList[] = [
                    'id' => $value->id,
                    'create_date' => Carbon::parse($value->created_at)->toDateString(),
                    'payment_method' => $value->type,
                    'transaction_id' => $value->transaction_id,
                    'amount' => $value->amount,
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

        $currencyConverter = new CurrencyConverter();

        if (!empty($list)) {
            $newList = [];
            $subTotal = 0;

            foreach ($list as $value) {
                //SKU单价同理 物流费用和sku物流费用 CNY转换为USD
//                $value->vendor_price = $currencyConverter->reversedCurrenciesExchange($value->vendor_price);
//                $value->other_supplement_price = $currencyConverter->reversedCurrenciesExchange($value->other_supplement_price);
//                $value->vendor_change_price = $currencyConverter->reversedCurrenciesExchange($value->vendor_change_price);
//                $value->favourable_price = $currencyConverter->reversedCurrenciesExchange($value->favourable_price);
//                $value->logistics_fee = $currencyConverter->reversedCurrenciesExchange($value->logistics_fee);
//                $value->sku_logistics_fee = $currencyConverter->reversedCurrenciesExchange($value->sku_logistics_fee);
//                $value->supplement_price = $currencyConverter->reversedCurrenciesExchange($value->supplement_price);
//                $value->refund_price = $currencyConverter->reversedCurrenciesExchange($value->refund_price);
//
//                $value->lineItems->each(function ($sku) use ($currencyConverter) {
//                    $sku->price = $currencyConverter->reversedCurrenciesExchange($sku->price);
//                    $sku->quote_price = $currencyConverter->reversedCurrenciesExchange($sku->quote_price);
//                });


                //开启商品一口价 物流费用为sku的物流总报价，反之就是订单物流费用
                $logisticsFee = $value->order_one_price == 1 ? 0 : $value->logistics_fee;

                $skuList = [];
                foreach ($value->lineItems as $item) {
                    $skuList[] = [
                        'sku_id' => $item->sku,
                        'price' => $item->quote_price ?: 0,
                        'quantity' => $item->quantity,
                    ];
                }
                $originalAmount = (float)sprintf("%.2f", ($value->vendor_price + $logisticsFee + $value->other_supplement_price + $value->supplement_price));
                $itemSubTotal = (float)sprintf('%.2f', ($originalAmount - $value->favourable_price - $value->refund_price));

                $subTotal += $itemSubTotal;

                $newList[] = [
                    'id' => $value->name,
                    'order_sn' => $value->order_id,
                    'sub_total' => $itemSubTotal,
                    'logistics_fee' => $logisticsFee,
                    'country_code' => $value->shippingAddress->country_code ?? '-',
                    'original_amount' => $originalAmount,
                    'other_amount' => $value->other_supplement_price,
                    'additional_amount' => $value->supplement_price,
                    'discount_amount'   => $value->favourable_price,
                    'refund_amount'     => $value->refund_price,
                    'sku_list'          => $skuList,
                    'created_at'        => $value->created_at,
                ];
            }

            $data = ['list' => $newList, 'sub_total' => customNumberFormat($subTotal)];
        }
        return $data;
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

            $filePath = Storage::disk('admin_public')->path($savePath);

            \PDF::loadView(
                'invoice.' . ($this->record['source_type'] == InvoiceRecords::TYPE_ORDER ? 'order' : 'recharge'), ['data' => $data]
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
            $bladeFileName = 'invoice.' . ($this->record['source_type'] == InvoiceRecords::TYPE_ORDER ? 'order' : 'recharge');
            $htmlContent = view($bladeFileName, ['data' => $data])->render();

            //修复html内容
            $config = \HTMLPurifier_Config::createDefault();

            //移动缓存目录
            $config->set('Cache.SerializerPath', '/tmp');

            $purifier = new \HTMLPurifier($config);
            $cleanHtml = $purifier->purify($htmlContent);

            $phpword = new PhpWord();

            $section = $phpword->addSection();
            Html::addHtml($section, $cleanHtml, false, false);

            $tempFile = tempnam(sys_get_temp_dir(), 'PHPWord');

            $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpword, 'Word2007');
            $objWriter->save($tempFile);

            Storage::disk('admin_public')->put($savePath, file_get_contents($tempFile));

            unlink($tempFile);

            // 上传到存储桶
            $url = CosUtil::localUploadToCos($savePath);

            info('word生成发票成功', ['file_path' => $url]);
            return $url;
        } catch (\Exception $e) {
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

        //获取logo
        $logoFilename = basename($data['client_menu_logo']);
        if (!Storage::disk('admin_public')->fileExists($logoFilename)) {
            $clientLogo = CosUtil::download($logoFilename);
        } else {
            $clientLogo = Storage::disk('admin_public')->path($logoFilename);
        }

        //开票抬头等信息
        $list = $data['list'];
        /* $billTo     = $data['buyer_info']['custom_name'] . "\r\n" . $data['buyer_info']['custom_phone'] . "\r\n" .
                         $data['buyer_info']['custom_email'] . "\r\n" . $data['buyer_info']['custom_address'];*/

        //客户开票信息
        $billTo = $data['buyer_info']['invoice_address']['name'];

        //电话
        if (!empty($data['buyer_info']['invoice_address']['phone_number'])) {
            $billTo .= "\r\n " . "+ " . $data['buyer_info']['invoice_address']['phone_area_code'] . $data['buyer_info']['invoice_address']['phone_number'];
        }

        //邮箱
        if (!empty($data['buyer_info']['invoice_address']['email'])) {
            $billTo .= "\r\n " . $data['buyer_info']['invoice_address']['email'];
        }

        //详情地址
        $billTo .= "\r\n " . "{$data['buyer_info']['invoice_address']['country']} {$data['buyer_info']['invoice_address']['province']} {$data['buyer_info']['invoice_address']['city']} {$data['buyer_info']['invoice_address']['address_detail']} {$data['buyer_info']['invoice_address']['postcode']}";

        //税号
        if (!empty($data['buyer_info']['invoice_address']['tax'])) {
            $billTo .= "\r\n " . $data['buyer_info']['invoice_address']['tax'];
        }

        $invoiceNo = 'No.' . $data['invoice_no'];
        $createDate = 'Issued ' . $data['created_at'];
        $sellerInfo = implode("\r\n", $data['seller_info']);

        $shortPath = '/invoice/';
        $savePath = $shortPath . $filename;

        $config = ['path' => Storage::disk('admin_public')->path($shortPath)];
        try {
            $excelObject = new Excel($config);

            $excel = $excelObject->fileName($filename);

            $format = new Format($excel->getHandle());
            $titleStyle = $format
                ->fontSize('24')
                ->bold()
                ->font('微软雅黑')
                ->border(Format::BORDER_THIN)
                ->toResource();

            $format2 = new Format($excel->getHandle());
            $boldStyle = $format2
                ->bold()
                ->font('微软雅黑')
                ->border(Format::BORDER_THIN)
                ->align(Format::FORMAT_ALIGN_VERTICAL_CENTER)
                ->toResource();

            $format3 = new Format($excel->getHandle());
            $globalStyle = $format3
                ->align(Format::FORMAT_ALIGN_CENTER)
                ->font('微软雅黑')
                ->border(Format::BORDER_THIN)
                ->toResource();

            $format4 = new Format($excel->getHandle());
            $mainTitleStyle = $format4
                ->bold()
                ->fontSize(16)
                ->font('微软雅黑')
                ->border(Format::BORDER_THIN)
                ->toResource();

            $format5 = new Format($excel->getHandle());
            $centerDataStyle = $format5
                ->align(Format::FORMAT_ALIGN_CENTER)
                ->align(Format::FORMAT_ALIGN_VERTICAL_CENTER)
                ->font('微软雅黑')
                ->border(Format::BORDER_THIN)
                ->toResource();

            $format6 = new Format($excel->getHandle());
            $amountDataStyle = $format6
                ->font('微软雅黑')
                ->fontSize(14)
                ->fontColor(Format::COLOR_RED)
                ->align(Format::FORMAT_ALIGN_CENTER)
                ->align(Format::FORMAT_ALIGN_VERTICAL_CENTER)
                ->border(Format::BORDER_THIN)
                ->toResource();

            $format7 = new Format($excel->getHandle());
            $fontStyle = $format7
                ->font('微软雅黑')
                ->align(Format::FORMAT_ALIGN_VERTICAL_CENTER)
                ->border(Format::BORDER_THIN)
                ->toResource();

            $format8 = new Format($excel->getHandle());
            $borderStyle = $format8
                ->border(Format::BORDER_THIN)
                ->toResource();

            $format9 = new Format($excel->getHandle());
            $centerTitleStyle = $format9
                ->align(Format::FORMAT_ALIGN_CENTER)
                ->bold()
                ->fontSize(16)
                ->font('微软雅黑')
                ->border(Format::BORDER_THIN)
                ->toResource();

            $format10 = new Format($excel->getHandle());
            $dataStyle = $format10
                ->font('微软雅黑')
                ->border(Format::BORDER_THIN)
                ->align(Format::FORMAT_ALIGN_VERTICAL_CENTER)
                ->toResource();

            //设置列宽
            $excel->setColumn('A:A', 25, $borderStyle)
                ->setColumn('B:B', 50, $borderStyle)
                ->setColumn('C:C', 25, $borderStyle)
                ->setColumn('D:D', 25, $borderStyle)
                ->setColumn('E:E', 25, $borderStyle);

            //设置行高
            $excel->setRow('A1', 45) //LOGO以及标题
            ->setRow('A2', 20) //Seller标题
            ->setRow('A3', 80) //Seller信息
            ->setRow('A4', 20) //Bill To标题
            ->setRow('A5', 80); //Bill To信息

            //合并单元格
            $excel->mergeCells('B1:C1', ' ');
            $excel->insertText(1, 0, 'Seller', null, $mainTitleStyle);
            $excel->mergeCells('A3:C3', $sellerInfo);
            $excel->insertText(3, 0, 'Bill To', null, $mainTitleStyle);
            $excel->mergeCells('A5:D5', $billTo);


            $excel->insertImage(0, 0, $clientLogo); //客户端LOGO
            $excel->insertText(0, 3, "INVOICE", null, $titleStyle); //invoice标题
            $excel->insertText(1, 3, $invoiceNo, null, $fontStyle); //发票编号
            $excel->insertText(2, 3, $createDate, null, $fontStyle); //开票日期

            $count = count($list) + 6;

            //从第7行开始插入主要数据，下标是6
            if ($this->record['source_type'] == InvoiceRecords::TYPE_ORDER) {
                $excel->insertText(5,0, "Ref.no.", null, $mainTitleStyle); //主要数据标题1
                $excel->insertText(5,1, "Item", null, $mainTitleStyle); //主要数据标题2
                $excel->insertText(5,2, "Country", null, $centerTitleStyle); //主要数据标题3
                $excel->insertText(5,3, "Total Price", null, $centerTitleStyle); //主要数据标题4
                $excel->insertText(5,4, "Created At", null, $centerTitleStyle); //主要数据标题4

                for ($i = 0; $i < count($list); $i++) {
                    $value = $list[$i];

                    $orderSn    = $value['id'] . PHP_EOL . $value['order_sn'] . PHP_EOL;
                    $itemData   = "";
                    $country    = $value['country_code'];
                    $totalPrice = "$". $list[$i]['sub_total'];
                    $createdAt   = $value['created_at'];

                    foreach ($value['sku_list'] as $item) {
                        $itemData .= 'SKU：' . $item['sku_id'] . ' Unit Price：$' . $item['price'] . ' QTY：' . $item['quantity'] . "\r\n";
                    };

                    $itemData .= "Shipping Costs: " . ($value['logistics_fee'] > 0 ? '$' . $value['logistics_fee'] : 'Free') . "\r\n";
                    if ($value['other_amount'] > 0) {
                        $itemData .= "Other Costs：$" . $value['other_amount'] . "\r\n";
                    }
                    if ($value['additional_amount'] > 0) {
                        $itemData .= "Additional Costs: $" . $value['additional_amount'] . "\r\n";
                    }
                    if ($value['discount_amount'] > 0) {
                        $itemData .= "Discount Amount：$ -" . $value['discount_amount'] . "\r\n";
                    }
                    if ($value['refund_amount'] > 0) {
                        $itemData .= "Refund Amount：$ -" . $value['refund_amount'] . "\r\n";
                    }

                    $excel->insertText($i+6, 0, $orderSn, null, $dataStyle);
                    $excel->insertText($i+6, 1, $itemData, null, $dataStyle);
                    $excel->insertText($i+6, 2, $country, null, $centerDataStyle);
                    $excel->insertText($i+6, 3, $totalPrice, null, $amountDataStyle);
                    $excel->insertText($i+6, 4, 'T ' . $createdAt, null, $centerDataStyle);
                    $excel->setRow('A'. $i+7, 80);
                }

                $excel->insertText($count, 2, 'Subtotal');
                $excel->insertText($count, 3, '$' . $data['sub_total'], null, $boldStyle);
            } else {
                $excel->insertText(5, 0, "Date", null, $centerTitleStyle); //主要数据标题1
                $excel->insertText(5, 1, "Payment Method", null, $centerTitleStyle); //主要数据标题2
                $excel->insertText(5, 2, "Transaction ID", null, $centerTitleStyle); //主要数据标题3
                $excel->insertText(5, 3, "Amount", null, $centerTitleStyle); //主要数据标题4

                for ($i = 0; $i < count($list); $i++) {
                    $value = $list[$i];

                    $date = $value['create_date'];
                    $paymentMethod = $value['payment_method'];
                    $transactionId = $value['transaction_id'];
                    $amount = '$' . $value['amount'];

                    $excel->insertText($i + 6, 0, $date, null, $centerDataStyle);
                    $excel->insertText($i + 6, 1, $paymentMethod, null, $centerDataStyle);
                    $excel->insertText($i + 6, 2, $transactionId, null, $centerDataStyle);
                    $excel->insertText($i + 6, 3, $amount, null, $centerDataStyle);
                }

                $excel->insertText($count, 2, 'Subtotal');
                $excel->insertText($count, 3, '$' . $data['sub_total'], null, $boldStyle);
                $excel->insertText($count + 1, 2, 'Payments');
                $excel->insertText($count + 1, 3, '$' . $data['confirm_payment'], null, $boldStyle);
                $excel->insertText($count + 2, 2, 'Credit');
                $excel->insertText($count + 2, 3, '$' . $data['buyer_info']['residual_credit'], null, $boldStyle);
                $excel->insertText($count + 3, 2, 'Outstanding Amount');
                $excel->insertText($count + 3, 3, '$' . ($data['buyer_info']['credit_line'] - $data['buyer_info']['residual_credit']), null, $boldStyle);
            }

            $path = $excel->defaultFormat($globalStyle)->output();

            // 上传到存储桶
            return CosUtil::localUploadToCos($savePath);
        } catch (\Exception $e) {
            info('excel生成发票失败', ['msg' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
            return false;
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
            } catch (Exception $e) {
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
