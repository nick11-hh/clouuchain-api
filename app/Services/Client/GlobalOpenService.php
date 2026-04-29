<?php


namespace App\Services\Client;


use App\Lib\Code;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Redis;
use App\Exceptions\AccidentException;

class GlobalOpenService
{
    protected $appkey;
    protected $appSecret;
    protected $client;
    protected $url;

    protected $company_id = 0;
    protected $purchaser_id = 0;
    // protected $token = 'f8f937c0-8633-49b0-aa2f-fd58923df19f';
    protected $token = '3b5603c8-bb91-402f-a16d-bd4c1c40e82b';
    // protected string $token;

    protected $languageArr = ['zh_CN' => 'cn', 'en_US' => 'en', 'ru_RU' => 'ru', 'th' => 'th', 'kk' => 'kk'];

    /**
     * GlobalOpenService constructor.
     */
    public function __construct()
    {
        $this->client = new Client();
        $this->url = 'http://gw.open.1688.com/openapi/';
        $this->appkey = config('alibaba.app_key');
        $this->appSecret = config('alibaba.app_secret');
        $this->token = config('alibaba.app_token');
    }

    public function imageQuery()
    {
//        $language = request()->header('language') ?? 'en_US';
//        if($language) {
//            $language = $this->languageArr[$language] ?? 'cn';
//        }
        $language = 'en';
        $params = request()->all();

        $imgRes = $this->uploadImg();
        logger('=========1688 以图搜物 上传图片返回结果==========');
        logger($imgRes);
        if (!isset($imgRes['result']['result']) || $imgRes['result']['result'] == 0) {
            throw new AccidentException('未搜索到商品!', Code::OPERATE_FAIL);
        }
        if (!empty($params['sort'])) {
           $sort = json_encode([$params['sort'] => $params['sort_type']]);
        }
        $data = $this->getRequest(
            'product.search.imageQuery',
            [
                'offerQueryParam'   =>  json_encode([
                    'imageId' => $imgRes['result']['result'],
                    'beginPage' => $params['page'] ?? 1,
                    'pageSize' => $params['page_size'] ?? 48,
                    'country' => 'en',
                    'sort' => $sort ?? null,
                    'priceStart' => !empty($params['price_start']) ? $params['price_start'] * 7.1 :'',
                    'priceEnd' => !empty($params['price_end']) ? $params['price_end'] * 7.1 : '',
                    'category_id' => $params['category_id'] ?? '',
                    'keyword' => $params['keyword'] ?? '',
                ])
            ],
            'en'
        );

        if ($data['result']['success'] && isset($data['result']['result'])) {
            return $this->assembleData($data['result']['result'], $language);
        }

        throw new AccidentException('搜索未搜索到商品!', Code::OPERATE_FAIL);
    }

    public function uploadImg()
    {
        $file = request()->get('img');

        throw_if(!$file, new AccidentException('请上传图片', Code::OPERATE_FAIL));

        $imageContent = file_get_contents($file);

        return $this->postRequest(
            'product.image.upload',
            [
                'uploadImageParam'   =>  json_encode(
                    [
                        'imageBase64'   =>  base64_encode($imageContent)
                    ]
                )
            ]
        );
    }

    /**
     * @param $params
     * @return $this
     */
    public function setCompanyId($params)
    {
        $this->company_id = $params['company_id'];
        $this->purchaser_id = $params['purchaser_id'];

        return $this;
    }

    /**
     * 获取授权链接
     * @return string
     */
    public function alibabaAuthorize()
    {
        $redirect_uri = config('app.url').'/api/client/1688/global/auth';

        $state = auth()->user()->custom_id;

//        $mode = DaigouSettingsModel::where(['company_id' => $state, 'key' => 'auto_purchase_mode'])->value('value');
//        if ($mode === DaigouSettingsModel::AUTO_PURCHASE_MODE_CHILD_1688) {
//            $state .= '_' . auth()->id();
//        }

        $uuid = request()->get('uuid');
        return "https://auth.1688.com/oauth/authorize?client_id={$this->appkey}&site=1688&redirect_uri={$redirect_uri}&state={$state}&uuid={$uuid}";
    }

    /**
     * 获取授权token并保存
     * @throws \Exception
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function generateAccessToken()
    {
        $code = request()->get('code');
        $state = request()->get('state');
        $query = $_SERVER["QUERY_STRING"];
        info('url参数数据', [$query]);

        if (!$code) {
            throw new AccidentException('code 不能为空', Code::OPERATE_FAIL);
        }

        $params = [
            'grant_type' => 'authorization_code',
            'need_refresh_token' => true,
            'client_id' => $this->appkey,
            'client_secret' => $this->appSecret,
            'code' => $code,
        ];

        info('1688授权请求参数', $params);
        $res = $this->getPlatformToken($params);

        if (!empty($res)) {
            Redis::client()->setex('1688access_token_'.$state, abs($res['expires_in']), $res['access_token']);
            return true;
        }

        return false;
    }

    /**
     * 获取平台授权token
     * @param $params
     * @return array|mixed
     * @throws \Exception
     * @throws GuzzleException
     */
    public function getPlatformToken($params)
    {
        try {
            $requestUrl = 'https://gw.open.1688.com/openapi/http/1/system.oauth2/getToken/'.$this->appkey;

            $result = $this->client->request('POST', $requestUrl,
                [
                    'verify'            => false,
                    "headers"           => [
                        "Accept"        =>  "application/json"
                    ],
                    "query"             => $params,
                ]
            );

            $res = json_decode((string)$result->getBody(), true);
            info('1688授权结果', $res);
            if (isset($res['access_token'])) {
                return $res;
            }
            return [];
        } catch (\Exception $e) {
            logger($e->getMessage());
            throw new AccidentException($e->getMessage(), Code::OPERATE_FAIL);
        }
    }

    /**
     * 解析url中参数信息，返回商品id
     */
    public function convertUrlQuery($url)
    {
        $pattern = '/\d+(?=\.html)/';
        preg_match($pattern, $url, $matches);
        if(empty($matches)) {
            throw new AccidentException('商品id不存在', Code::OPERATE_FAIL);
        }

        // 获取匹配到的id
        return $matches[0];
    }

    /**
     * 调用官方接口获取商品列表
     * @param $params
     * @return array
     * @throws \Exception
     */
    public function searchGoods($params)
    {
//        preg_match_all('/[\x{4e00}-\x{9fa5}]+/u', urldecode($params['keyword']), $matches); // 使用正则表达式匹配中文字符

//        // 非中文关键词调用百度翻译接口，翻译成中文再进行搜索
//        if(empty($matches[0])) {
//            $baiduTranslate = new BaiduTranslate();
//            $params['keyword'] = $baiduTranslate->keywordTranslation($params['keyword'], 'zh');
//        }

        $requestParams = [
            'offerQueryParam' => json_encode(
                [
                    'keyword' => $params['keyword'],
                    'beginPage' => $params['page'] ?? 1,
                    'pageSize' => $params['page_size'] ?? 48,
                    'country' => 'en',
                    'sort' => $params['sort'] ?? null,
                    'priceStart' => $params['price_start'] ?? '',
                    'priceEnd' => $params['price_end'] ?? '',
                    'category_id' => $params['category_id'] ?? ''
                ]
            )
        ];

        logger('请求参数', ['params' => $params]);
        $res = $this->getRequest('product.search.keywordQuery', $requestParams, 'en');
        info('获取商品列表结果', $res);
        if (isset($res['result']['result']) && $res['result']['success']) {
            $result = $res['result']['result'];
            return $this->assembleData($result, $params['language']);
        } else {
            if (isset($res['result']) && !$res['result']['success']) {
                throw new AccidentException($res['result']['message'], Code::OPERATE_FAIL);
            }
            throw new AccidentException('未获取到商品列表数据', Code::OPERATE_FAIL);
        }
    }

    /**
     * get请求调用1688官方接口
     * @param $apiName
     * @param array $parameters
     * @param string $language
     * @return mixed
     * @throws GuzzleException
     */
    public function getRequest($apiName, array $parameters = [], string $language = 'en', $nameSpace = 'com.alibaba.fenxiao.crossborder')
    {
        $url = 'https://gw.open.1688.com/openapi';
        $apiInfo = sprintf("param2/1/%s/%s/%s", $nameSpace, $apiName, $this->appkey);

        $url = sprintf("%s/%s", $url, $apiInfo);

        $paramsToSign = [];
        $parameters['access_token'] = $this->token;
        $parameters['language'] = $language;

        foreach ($parameters as $k => $v) {
            $paramToSign = $k . $v;
            Array_push ($paramsToSign, $paramToSign );
        }

        sort($paramsToSign);

        $implodeParams = implode($paramsToSign);
        $pathAndParams = $apiInfo . $implodeParams;
        $sign = hash_hmac("sha1", $pathAndParams, $this->appSecret, true );
        $signHexWithLowcase = bin2hex($sign);
        $signHexUppercase = strtoupper($signHexWithLowcase);

        $requestData = [
            '_aop_signature'    =>  $signHexUppercase,
        ];

        $requestData = array_merge($requestData, $parameters);

        $res = $this->client->request('GET', $url,
            [
                'verify'            => false,
                "headers"           => [
                    "Accept"            =>  "application/json"
                ],
                "query"              => $requestData,
            ]
        );

        $stringBody = (string)$res->getBody();

        return json_decode($stringBody, TRUE);
    }

    public function assemble($params)
    {
        $paramsToSign = [];
        foreach ($params as $k => $v) {
            if(is_array($v)) {
                $paramToSign = $k . json_encode($v, JSON_UNESCAPED_UNICODE);
            }else {
                $paramToSign = $k . $v;
            }
            Array_push ( $paramsToSign, $paramToSign );
        }

        sort($paramsToSign);

        return implode ( $paramsToSign );
    }

    public function postRequest($apiName, array $parameters, $nameSpace='com.alibaba.fenxiao.crossborder') {

        $url = 'https://gw.open.1688.com/openapi';
        $apiInfo = sprintf("param2/1/%s/%s/%s", $nameSpace, $apiName, $this->appkey);

        $url = sprintf("%s/%s", $url, $apiInfo);

        // $paramsToSign = array();

        $parameters['access_token'] = $this->token;
        $parameters['language'] = 'en';

        // foreach ($parameters as $k => $v) {
        //     $paramToSign = $k . $v;
        //     Array_push ( $paramsToSign, $paramToSign );
        // }
        //
        // sort($paramsToSign);

        $implodeParams = $this->assemble($parameters);

        info('签名参数', [$implodeParams]);
        // $implodeParams = implode ( $paramsToSign );
        $pathAndParams = $apiInfo . $implodeParams;
        $sign = hash_hmac ( "sha1", $pathAndParams, $this->appSecret, true );
        $signHexWithLowcase = bin2hex ( $sign );
        $signHexUppercase = strtoupper ( $signHexWithLowcase );

        $requestData = [
            '_aop_signature'    =>  $signHexUppercase,
        ];


        $requestData = array_merge($requestData, $parameters);


        logger('=================请求参数========================');

        $res = $this->client->request('POST', $url,
            [
                // 'http_errors'       => false,
                'verify'            => false,
                "headers"           => [
                    "Accept"            =>  "application/json"
                ],
                "form_params"              => $requestData,
            ]
        );



        $stringBody = (string)$res->getBody();
        $arrayBody = json_decode($stringBody, TRUE);


        return $arrayBody;
    }

    /**
     * 组装列表参数
     * @param $result
     * @return array
     */
    public function assembleData($result, $language)
    {
        logger('请求结果', ['$result' => $result]);
        if (!isset($result['data'])) {
            throw new AccidentException('未搜索到商品信息', Code::OPERATE_FAIL);
        }
        $skus = [];
        $data = $result['data'];
        foreach ($data as $datum) {
            if ($language == 'cn') {
                $title = $datum['subject'];
            } else {
                $title = $datum['subjectTrans'];
            }
            $skus[] = [
                'detail_url' => 'https://detail.1688.com/offer/'.$datum['offerId'].'.html',
                'title' => $title,
                'pic_url' => $datum['imageUrl'],
                'promotion_price' => $datum['priceInfo']['price'],
                'price' => $datum['priceInfo']['price'],
                'sales' => $datum['monthSold'],
                'num_iid' => $datum['offerId'],
                'seller_nick' => $datum['traceInfo'],
                'tag_percent' => $datum['repurchaseRate'],
                'trace_info' => $datum['traceInfo'],
            ];
        }

        return [
            'items' => [
                'item' => $skus,
                'total_results' => $result['totalRecords'],
            ],
            'api_type' => '1688'
        ];
    }

    public function getItemInfo($params)
    {
        $requestParams = [
            'offerDetailParam' => json_encode(
                [
                    'offerId' => $params['id'],
                    'country' => 'en',
                ]
            )
        ];

        $res = $this->getRequest('product.search.queryProductDetail', $requestParams, 'en');
        // logger('官方返回结果', ['res' => $res]);
        info('获取商品详情', $res);
        if (isset($res['result']) && $res['result']['success']) {
            $result = $res['result']['result'];
            return $this->assembleInfoData($result, $params['language']);
        } else {
            if (isset($res['result']) && !$res['result']['success']) {
                throw new AccidentException($res['result']['message'], Code::OPERATE_FAIL);
            }
            throw new AccidentException('未获取到商品列表数据', Code::OPERATE_FAIL);
        }
    }

    public function assembleInfoData($detail, $language)
    {
//        var_dump($detail);die();
        $prices = [];
        $props = [];
        $props_img = [];
        $skus = [];
        $sku = [];
        $props_list = [];

        foreach ($detail['productAttribute'] as $item) {
            if ($language == 'cn') {
                $props[] = [
                    'name' => $item['attributeName'],
                    'value' => $item['value'],
                ];
            } else {
                $props[] = [
                    'name' => $item['attributeNameTrans'],
                    'value' => $item['valueTrans'],
                ];
            }
        }

        $skuAttributes = [];
        foreach ($detail['productSkuInfos'] as $k => $productSkuInfo) {
            $properties = '';
            $properties_name = '';
            foreach ($productSkuInfo['skuAttributes'] as $ke => $skuAttribute) {
                if ($language == 'cn') {
                    $attributeName = $skuAttribute['attributeName'];
                    $value = $skuAttribute['value'];
                } else {
                    $attributeName = $skuAttribute['attributeNameTrans'];
                    $value = $skuAttribute['valueTrans'];
                }
                $properties .= $attributeName.':'.$value.';';
                $properties_name .= $attributeName.':'.$value.':'.$attributeName.':'.$value.';';

                if (isset($skuAttribute['skuImageUrl'])) {

                    $props_img[$attributeName.':'.$value] = $skuAttribute['skuImageUrl'];
                }

                // 拼接属性列表
            }
            $skuAttributes[] = $productSkuInfo['skuAttributes'];
            $properties = rtrim($properties, ';');
            $properties_name = rtrim($properties_name, ';');
            $prices[] = $productSkuInfo['price'] ?? $productSkuInfo['consignPrice'];
            $sku[] = [
                'original_price' => $productSkuInfo['consignPrice'],
                'price' => $productSkuInfo['price'] ?? $productSkuInfo['consignPrice'],
                'properties' => $properties,
                'properties_name' => $properties_name,
                'quantity' => $productSkuInfo['amountOnSale'],
                'sales' => 0,
                'sku_id' => $productSkuInfo['skuId'],
                'total_price' => $productSkuInfo['amountOnSale'],
            ];
        }
        if ($language == 'cn') {
            $title = $detail['subject'];
        } else {
            $title = $detail['subjectTrans'];
        }
        $skus['title'] = $title;
        $skus['sku'] = $sku;

        foreach ($skuAttributes as $key => $skuAttribute) { // 次键

            foreach ($skuAttribute as $ke => $item) { // 主键
                if ($language == 'cn') {
                    $name = $item['attributeName'];
                    $value = $item['value'];
                } else {
                    $name = $item['attributeNameTrans'];
                    $value = $item['valueTrans'];
                }
                if ($key == 0) {
                    $children = [
                        'id' => $value,
                        'name' => $value,
                    ];
                    $list = [
                        'id' => $name,
                        'name' => $name,
                    ];
                    $list['children'][] = $children;
                    $props_list[] = $list;
                } else {
                    foreach ($props_list as &$props_li) {
                        if ($props_li['id'] == $name && $props_li['name'] == $name) {
                            $children = $props_li['children'];
                            $child = [
                                'id' => $value,
                                'name' => $value,
                            ];
                            $children[] = $child;

                            $props_li['children'] = $children;
                        }
                    }
                }

            }
        }
        foreach ($props_list as &$item) {
            $item['children'] = array_values(array_unique($item['children'], SORT_REGULAR));
        }

        return [
            'num_iid'               => $detail['offerId'],
            'title'                 => $title,
            'price'                 => min($prices) ?? 0, // 只取原价 -》即RMB
            'price_pange_list'      => $detail['productSaleInfo'] ?? [], // 只取原价 -》即RMB
            'min_order_quantity'    => $detail['minOrderQuantity'] ?? 1, // 最小起批量
            'batch_number'          => $detail['batchNumber'] ?? 1, // 每批数量
            'nick'                  => $detail['sellerOpenId'],
            'detail_url'            => 'https://detail.1688.com/offer/'.$detail['offerId'].'.html',
            'pic_url'               => $detail['productImage']['images'][0],
            'desc'                  => $detail['description'] ?? '',
            'item_imgs'             => $detail['productImage']['images'],
            'props'                 => $props,
            'props_img'             => $props_img,
            'skus'                  => $skus,
            'props_list'            => $props_list,
            'is_platform_api'       => 1,
            'sales'                 => $detail['productSaleInfo']['amountOnSale'] ?? 0,
            'platform'              => '1688',
            'shop_id'               => $detail['sellerOpenId']
        ];
    }

    /**
     * 创建1688采购订单
     * @return void
     */
    public function createCrossOrder()
    {
        $res = $this->getBuyerOrderList();
        return $res;
        $addressId = $this->getReceiveAddress();

        $params = [
            // '_aop_timestamp' => time()*1000,
            'flow' => 'general',
            'addressParam' => json_encode([
                'addressId' => $addressId
                // 'fullName' => '张三',
                // 'mobile' => '15251667788',
                // 'phone' => '0755-88990077',
                // 'postCode' => '55667788',
                // 'cityText' => '杭州市',
                // 'provinceText' => '浙江省',
                // 'areaText' => '滨江区',
                // 'townText' => '长河镇',
                // 'address' => '网商路699号',
                // 'districtCode' => '310107',
            ], JSON_UNESCAPED_UNICODE),
            'cargoParamList' => json_encode([
                [
                    'offerId' => 622500410936,
                    'specId'  => '345076b8fecff6972f850a9b692d9af2',
                    'quantity'=> 1
                ]
            ]),
        ];

        try {
            $res = $this->postRequest('alibaba.trade.createCrossOrder', $params, 'com.alibaba.trade');
        } catch (\Exception $e) {
            throw new AccidentException($e->getMessage(), Code::OPERATE_FAIL);
        }

        if(!$res['success']) {
            throw new AccidentException($res['message'], Code::OPERATE_FAIL);
        }

        info('创建1688采购订单返回结果', $res);
        return true;
    }

    /**
     * 获取买家保存的收货地址信息列表
     * 返回默认收货地址id
     * @return int
     * @throws Exception
     */
    public function getReceiveAddress(): int
    {
        $uri = 'alibaba.trade.receiveAddress.get';

        try {
            $res = $this->getRequest($uri, nameSpace: 'com.alibaba.trade');
            info('买家获取保存的收货地址信息列表', $res);

            if(!$res['success']) {
                throw new AccidentException('收货地址获取失败', Code::OPERATE_FAIL);
            }
            if(empty($res['result']['receiveAddressItems'])) {
                throw new AccidentException('请在买家工作台先填写收货地址', Code::OPERATE_FAIL);
            }

            $addrs = array_filter($res['result']['receiveAddressItems'], function($item) {
                return $item['isDefault'];
            });

            if(empty($addrs)) {
                throw new AccidentException('自动采购请先在买家工作台设置默认收货地址', Code::OPERATE_FAIL);
            }

            return $addrs[0]['id'];
        } catch (GuzzleException $e) {
            throw new AccidentException($e->getMessage(), Code::OPERATE_FAIL);
        }
    }

    /**
     * 取消1688采购订单
     * @return void
     */
    public function cancelOrder()
    {
        $uri = 'alibaba.trade.cancel';
        $params = [
            'webSite' => 1688,
            'tradeID' => 3651927015130705038,
            'cancelReason' => 'buyerCancel'
        ];

        $res = $this->postRequest($uri, $params, nameSpace: 'com.alibaba.trade');

        var_dump($res);die();
    }

    /**
     * 订单列表
     * @return void
     */
    public function getBuyerOrderList()
    {
        $uri = 'alibaba.trade.getBuyerOrderList';

        $res = $this->getRequest($uri, nameSpace: 'com.alibaba.trade');

        info('订单列表', $res);

        return $res;
    }

    /**
     * 1688订单发起免密支付
     * @return void
     */
    public function preparePay()
    {
        $uri = 'alibaba.trade.pay.protocolPay.preparePay';
        $params = [
            'tradeWithholdPreparePayParam' => json_encode(['orderId' => 3651754500830705038])
        ];

        $res = $this->postRequest($uri, $params, 'com.alibaba.trade');

        var_dump($res);die();
    }

    public function getProductFreight($params)
    {
        try {
            $uri = 'product.freight.estimate';

            $res = $this->getRequest($uri, $params, nameSpace: 'com.alibaba.fenxiao.crossborder');
            if (isset($res['result']) && $res['result']['success']) {
                $result = $res['result']['result'];
            }

            return [
                'weight' => $result['singleProductWeight'] ?? 0,
                'length' => $result['singleProductLength'] ?? 0,
                'width' => $result['singleProductWidth'] ?? 0,
                'height' => $result['singleProductHeight'] ?? 0,
            ];

        } catch (GuzzleException $e) {
            throw new AccidentException($e->getMessage(), Code::OPERATE_FAIL);
        }

    }
}
