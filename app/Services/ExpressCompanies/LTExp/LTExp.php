<?php

namespace App\Services\ExpressCompanies\LTExp;

use App\Events\AfterUpdateBoxLogisticsSn;
use App\Events\AfterUpdateLogisticsSn;
use App\Lib\Code;
use App\Models\CompanyDockingInfo;
use App\Models\DeclareOrder;
use App\Models\ExpressCompany;
use App\Models\Order;
use App\Models\OrderBox;
use App\Models\OrderDockingRecord;
use App\Models\ThirdPartyTrackingLog;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Arr;
use App\Exceptions\AccidentException;

class LTExp
{

    public const ACTION_CREATE_ORDER = 'createorder';

    public const ACTION_UPDATE_ORDER = 'updateorder';

    public const ACTION_CANCEL_ORDER = 'removeorder';

    public const ACTION_GET_LABEL = 'getnewlabel';

    public const ACTION_GET_CHANNELS = 'getshippingmethod';

    public $client;

    protected $app_key;

    protected $app_token;

    protected $url;


    protected Order $order;

    protected DeclareOrder $declare;

    public $channelCode = '';


    /**
     * @param int $companyId
     * @param string $channelCode
     * @throws Exception
     */
    public function __construct(int $companyId = 0, string $channelCode = '')
    {
        $this->client = new Client();
        $this->loadConfig();
        $this->channelCode = $channelCode;
    }

    /**
     * @return void
     * @throws Exception
     */
    public function loadConfig()
    {
        $info = CompanyDockingInfo::query()
            ->where('company_id', $this->companyId)
            ->where('type', OrderDockingRecord::TYPE_LT_EXP)
            ->pluck('info')
            ->first();

        if (!$info || !Arr::has($info, ['app_key', 'app_token', 'url'])) {
            throw new AccidentException('参数未配置', Code::OPERATE_FAIL);
        }

        $this->app_key = $info['app_key'];
        $this->app_token = $info['app_token'];
        $this->url = $info['url'];
    }


    /**
     * @param Order $order
     * @return $this
     */
    public function setOrder(Order $order): self
    {
        $this->order = $order;
        return $this;
    }

    /**
     * @param DeclareOrder $declare
     * @return $this
     */
    public function setDeclare(DeclareOrder $declare): self
    {
        $this->declare = $declare;

        return $this;
    }

    protected function transform($operate, $params)
    {
        return [
            'appKey' => $this->app_key,
            'appToken' => $this->app_token,
            'serviceMethod' => $operate,
            'paramsJson' => json_encode($params)
        ];
    }

    public function placeByDeclare()
    {
        $data = (new Request($this->declare, $this->companyId, $this->channelCode))->transformByDeclare($this->declare);
        $result = $this->place($data);

        if ($result === false) {
            return false;
        }

        Order::where('id', $this->declare->order->id)
            ->update([
                'logistics_sn' => $result['shipping_method_no'],
                'logistics_company' => ExpressCompany::CODE_LT_EXP,
            ]);

        $orderList = [
            [
                'id' => $this->declare->order->id,
                'sn' => $result['shipping_method_no'],
                'company' => ExpressCompany::CODE_LT_EXP
            ]
        ];

        event(new AfterUpdateLogisticsSn($orderList, $this->companyId));

        return true;
    }

    public function placeByBoxes()
    {
        $boxes = $this->declare->boxes;

        $successBoxes = null;
        $isRollBack = false;

        $request = new Request($this->declare, $this->companyId, $this->channelCode);

        foreach ($boxes as $box) {

            $data = $request->transformByBox($box);
            $result = $this->place($data);

            if ($result === false) {
                $isRollBack = true;
                break;
            }

            $box->result = $result;

            $successBoxes[] = $box;
        }

        if ($isRollBack) {
            collect($successBoxes)->each(fn($box) => $this->cancel($box->box_sn));
            return false;
        }

        $orderBoxList = [];
        collect($successBoxes)->each(function ($box) use (&$orderBoxList) {

            $result = $box->result;
            OrderBox::query()->where('id', $box->box_id)->update([
                'logistics_sn' => $result['shipping_method_no'],
                'logistics_company' => ExpressCompany::CODE_LT_EXP,
            ]);

            $orderBoxList[] = [
                'id' => $box->box_id,
                'logistics_sn' => $result['shipping_method_no'],
                'company' => ExpressCompany::CODE_LT_EXP,
            ];


        });

        event(new AfterUpdateBoxLogisticsSn($orderBoxList, $this->companyId));
        return true;

    }

    /**
     * @param $data
     * @return false|mixed
     */
    protected function place($data)
    {
        $data = $this->transform(self::ACTION_CREATE_ORDER, $data);

        info('LTExp数据', ['data' => $data, 'url' => $this->url]);

        try {
            $result = $this->client->request('POST', $this->url, [
                'verify' => false,
                'form_params' => $data
            ]);

            $result = json_decode($result->getBody()->getContents(), true);

            info('LTExp响应数据', $result);

            $response = new Response($result ?? []);
            if (!$response->isSuccessful()) {
                $this->recordLog($result);
                return false;
            }

            return $response->result();
        } catch (GuzzleException $ex) {
            info('LTExp创建运单失败', ['exception' => $ex->getMessage()]);
            return false;
        } catch (\Exception $ex) {
            info('LTExp创建运单失败', ['exception' => $ex->getMessage()]);
            return false;
        }
    }

    private function recordLog($result)
    {
        ThirdPartyTrackingLog::query()->create([
            'company_id' => $this->companyId,
            'declare_id' => $this->declare->id,
            'content' => $result['cnmessage'] ?? ''
        ]);
    }

    /**
     * @param $listOrder
     * @return array|null
     */
    public function getLabel($listOrder)
    {
        $data = [
            'configInfo' => [
                'lable_file_type' => "2",
                'lable_paper_type' => "1",
                'lable_content_type' => "1",
                'additional_info' => [
                    'lable_print_invoiceinfo' => "Y",
                    'lable_print_buyerid' => "N",
                    'lable_print_datetime' => 'Y',
                    'customsdeclaration_print_actualweight' => 'N',
                ],
            ],
            'listorder' => $listOrder,
        ];
        info('LTExp数据', $data);
        try {
            $result = $this->client->request('POST', $this->url, [
                'verify' => false,
                'form_params' => $this->transform(self::ACTION_GET_LABEL, $data)
            ]);
            $result = (array)json_decode($result->getBody()->getContents());
            info('LTExp响应数据', $result);
            $response = new Response($result ?? []);
            if (!$response->isSuccessful()) {
                throw new AccidentException('响应失败', Code::OPERATE_FAIL);
            }
            $result = $response->result();
            return (array)($result[0]);
        } catch (GuzzleException $ex) {
            info('LTExp获取标签失败', ['exception' => $ex->getMessage()]);
            return null;
        } catch (\Exception $ex) {
            info('LTExp获取标签失败', ['exception' => $ex->getMessage()]);
            return null;
        }
    }

    /**
     * @param Order $order
     * @return bool
     */
    public function updateOrder(Order $order)
    {
        try {
            $result = $this->client->request('POST', $this->url, [
                'verify' => false,
                'form_params' => $this->transform(self::ACTION_UPDATE_ORDER, [
                    'reference_no' => $order->order_sn,
                    'order_weight' => $order->actual_weight / 1000,
                ])
            ]);

            $result = json_decode($result->getBody()->getContents(), true);

            info('LTExp响应数据', $result);

            $response = new Response($result ?? []);
            if (!$response->isSuccessful()) {
                throw new AccidentException('响应失败', Code::OPERATE_FAIL);
            }
            return true;
        } catch (GuzzleException $ex) {
            info('LTExp创建运单失败', ['exception' => $ex->getMessage()]);
            return false;
        } catch (\Exception $ex) {
            info('LTExp创建运单失败', ['exception' => $ex->getMessage()]);
            return false;
        }
    }

    /**
     * @param $sn
     * @return bool
     */
    public function cancel($sn)
    {
        try {
            $result = $this->client->request('POST', $this->url, [
                'verify' => false,
                'form_params' => $this->transform(self::ACTION_CANCEL_ORDER, ['reference_no' => $sn])
            ]);

            $result = json_decode($result->getBody()->getContents(), true);

            info('LTExp响应数据', $result);
            $response = new Response($result ?? []);
            if (!$response->isSuccessful()) {
                throw new AccidentException('响应失败', Code::OPERATE_FAIL);
            }
            return true;
        } catch (GuzzleException $ex) {
            info('LTExp创建运单失败', ['exception' => $ex->getMessage()]);
            return false;
        } catch (\Exception $ex) {
            info('LTExp创建运单失败', ['exception' => $ex->getMessage()]);
            return false;
        }
    }

    /**
     * @return array
     */
    public function channels()
    {
        try {
            $result = $this->client->request('POST', $this->url, [
                'verify' => false,
                'form_params' => $this->transform(self::ACTION_GET_CHANNELS, [])
            ]);

            $result = json_decode($result->getBody()->getContents(), true);

            info('LTExp响应数据', $result);
            $response = new Response($result ?? []);
            if (!$response->isSuccessful()) {
                throw new AccidentException('响应失败', Code::OPERATE_FAIL);
            }
            return $result['data'];
        } catch (GuzzleException $ex) {
            info('LTExp 获取渠道失败', ['exception' => $ex->getMessage()]);
            return [];
        } catch (\Exception $ex) {
            info('LTExp 获取渠道失败', ['exception' => $ex->getMessage()]);
            return [];
        }
    }
}
