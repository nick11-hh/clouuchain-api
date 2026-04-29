<?php

namespace App\Services\Collect\Platform\Y1688;

use App\Lib\Code;
use App\Models\SystemConfig;
use App\Services\Base\SystemConfigService;
use App\Services\Collect\CollectAbstract;
use App\Services\Collect\CollectInterface;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use phpDocumentor\Reflection\Types\Self_;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use App\Exceptions\AccidentException;

class Y1688Service extends CollectAbstract implements CollectInterface
{

    protected $platform = '1688';

    protected $collectUrl;

    protected $request;



    protected $languageMapping = [
        self::LANGUAGE_CN => 'en',
        self::LANGUAGE_EN => 'en',
        self::LANGUAGE_RU => 'ru',
        self::LANGUAGE_AR => 'ar',
        self::LANGUAGE_PT => 'pt',
        self::LANGUAGE_VN => 'vi',
        self::LANGUAGE_MN => 'en',
    ];

    public function __construct()
    {
        $this->request = new RequestApi();
    }

    public function analysePlatform($url): bool
    {
        $path = pathinfo($url);
        if (stripos($path['dirname'], '1688') != false) {
            return true;
        }
        return false;
    }


    /**
     * @throws Exception
     */
    public function getProductDataByUrl($url)
    {
        $this->collectUrl = $url;
        $productId = $this->getProductIdByUrl($url);
        $params['offerId'] = $productId;
        $params['country'] = $this->getApiLanguage();
        $res = $this->request->getProductDetail($params, $this->getApiLanguage());
        if (empty($res) || empty($res['result']['success'])) {
            throw new AccidentException('获取1688商品信息失败: ' . $res['result']['message'] ?? '', Code::OPERATE_FAIL);
        }
        $productInfo = $res['result']['result'];
        $data = $this->transformData($productInfo);
        return $this->transformDetailPrice($data);
    }

    /**
     * @param $params
     * @return mixed
     * @throws Exception
     */
    public function getProductList($params)
    {
        if (!empty($params['keyword']) && str_starts_with($params['keyword'], 'http')) {
            return $this->getUrlPurchaseList($params['keyword']);
        }
        $keyword = $params['keyword'] ?? '';
        // 过滤敏感词
        $status = $this->validateParams($keyword);
        if ($status) {
            return [
                'total' => 0,
                'total_page' => 1,
                'data' => [],
            ];
        }

        $requestParams = [
            'keyword' => $keyword,
            'beginPage' => $params['page'] ?? 1,
            'pageSize' => $params['page_size'] ?? 10,
            'country' => $this->getApiLanguage(),
        ];

        if (!empty($params['sort'])) {
            $sort = [$params['sort'] => $params['sort_type'] ?? 'desc'];
            $requestParams['sort'] = json_encode($sort);
        }

        if (!empty($params['price_start'])) $requestParams['priceStart'] = round($params['price_start'] * $rate, 2);
        if (!empty($params['price_end'])) $requestParams['priceEnd'] = round($params['price_end'] * $rate, 2);
        if (!empty($params['category_id'])) $requestParams['categoryId'] = $params['category_id'];
        $result = $this->request->getProductList($requestParams, $this->getApiLanguage());

        $list = [];
        if (isset($result['data'])) {
            $list = $this->transformList($result['data']);
        }
        info('1688 response', $result);
        return [
            'total' => $result['totalRecords'],
            'total_page' => $result['totalPage'] ?? 0,
            'data' => $this->transformListPrice($list),
        ];
    }

    /**
     * @param $productId
     * @return mixed
     * @throws Exception
     */
    public function getProductDetail($productId, $transformPrice = true)
    {
        $requestParams = [
            'offerId' => $productId,
            'country' => $this->getApiLanguage(),
        ];
        $res = $this->request->getProductDetail($requestParams, $this->getApiLanguage());
        info('1688商品信息详情', $res);

        if (empty($res) || empty($res['result']['success']) || empty($res['result']['result'])) {
            throw new AccidentException('获取1688商品信息失败', Code::OPERATE_FAIL);
        }
        $productInfo = $res['result']['result'];
        $productInfo = $this->transformData($productInfo);

        if ($transformPrice) {
            return $this->transformDetailPrice($productInfo);
        }

        return $productInfo;
    }

    /**
     * @param $openUid
     * @return mixed
     */
    public function getWangWangNick($openUid)
    {
        return $this->request->getWangWangNick($openUid);
    }

    /**
     * @param $params
     * @return mixed
     * @throws Exception
     */
    public function productImageSearch($params)
    {
        $imgRes = $this->request->uploadImage($params['img']);
        if (!isset($imgRes['result']['result']) || $imgRes['result']['result'] == 0) {
            throw new AccidentException('上传图片到1688失败!', Code::OPERATE_FAIL);
        }

        $requestParams = [
            'imageId' => $imgRes['result']['result'],
            'beginPage' => $params['page'] ?? 1,
            'pageSize' => $params['page_size'] ?? 10,
            'country' => $this->getApiLanguage(),
        ];
        if (!empty($params['sort'])) {
            $sort = [$params['sort'] => $params['sort_type'] ?? 'desc'];
            $requestParams['sort'] = json_encode($sort);
        }

        $rate = $this->currency === 'CNY' ? 1 : $this->getExchangeRate();
        if (!empty($params['price_start'])) $requestParams['priceStart'] = round($params['price_start'] * $rate, 2);
        if (!empty($params['price_end'])) $requestParams['priceEnd'] = round($params['price_end'] * $rate, 2);
        if (!empty($params['category_id'])) $requestParams['priceEnd'] = $params['category_id'];
        if (!empty($params['keyword'])) $requestParams['keyword'] = $params['keyword'];

        $filter = [];

        if (!empty($params['shipIn48Hours'])) $filter[] = 'shipIn48Hours';
        if (!empty($filter)) $requestParams['filter'] = implode(',', $filter);

        $result = $this->request->productImageSearch($requestParams, $this->getApiLanguage());
        $list = $this->transformList($result['data'] ?? []);

        return [
            'total' => $result['totalRecords'],
            'total_page' => $result['totalPage'],
            'data' => $this->transformListPrice($list),
        ];
    }

    /**
     * @param $url
     * @return array
     * @throws Exception
     */
    protected function getUrlPurchaseList($url)
    {
        $productId = $this->getProductIdByUrl($url);
        $product = $this->getProductDetail($productId);
        $list = [
            [
                'name' => $product['goods_name'],
                'price' => $product['price'],
                'cover_image' => $product['cover_image'],
                'sale_count' => 0,
                'product_id' => $product['goods_id'],
                'detail_url' => 'https://detail.1688.com/offer/' . $product['goods_id'] . '.html',
//                'min_order_quantity' => $value['minOrderQuantity'] ?? 1
            ]
        ];
        return [
            'total' => 1,
            'total_page' => 1,
            'data' => $list,
        ];
    }


    /**
     * @param $url
     * @return mixed
     * @throws Exception
     */
    protected function getProductIdByUrl($url)
    {
        $pattern = '/\d+(?=\.html)/';
        preg_match($pattern, $url, $matches);
        if (empty($matches)) {
            throw new AccidentException('商品id不存在');
        }
        // 获取匹配到的id
        return $matches[0];
    }

    /**
     * @param $list
     * @return array
     */
    public function transformList($list)
    {
        $result = [];
        $isTrans = in_array($this->language, [self::LANGUAGE_CN, self::LANGUAGE_MN]) ? '' : 'Trans';
        foreach ($list as $value) {
            $price = $value['priceInfo']['price'] ?? 0;
            $result[] = [
                'name' => $value['subject' . $isTrans],
                'price' => $price,
                'cover_image' => $value['imageUrl'],
                'sale_count' => $value['monthSold'],
                'product_id' => $value['offerId'],
                'detail_url' => 'https://detail.1688.com/offer/' . $value['offerId'] . '.html',
            ];
        }
        return $result;
    }

    /** 转换详情数据
     * @param $product
     * @return array
     */
    public function transformData($product)
    {
        $skuList = [];
        $isTrans = $this->language == self::LANGUAGE_CN ? '' : 'Trans';
        if (empty($product['productSkuInfos'])) {  // 单规格
            $skuPrice = $product['productSaleInfo']['consignPrice'] ?? 0;
            $skuList[] = [
                'sku_id' => '',
                'spec_id' => '',
                'sale_price' => $skuPrice,
                'quote_price' => $skuPrice,
                'compare_price' => $skuPrice,
                'spec_info' => [],
                'spec_name' => '单规格',
                'images' => [$product['productImage']['images'][0] ?? '']
            ];
        } else {
            foreach ($product['productSkuInfos'] as $skuInfo) {
                $skuAttr = [];
                $skuName = [];
                $skuImage = '';
                foreach ($skuInfo['skuAttributes'] as $attr) {
                    $skuAttr[] = [
                        'name' => $attr['attributeName' . $isTrans],
                        'value' => $attr['value' . $isTrans],
                    ];
                    $skuName[] = $attr['value' . $isTrans];
                    if (!empty($attr['skuImageUrl'])) $skuImage = $attr['skuImageUrl'];
                }
                $skuList[] = [

                    'sku_id' => $skuInfo['skuId'],
                    'spec_id' => $skuInfo['specId'],
                    'sale_price' => $skuInfo['price'] ?? $skuInfo['consignPrice'],
                    'quote_price' => $skuInfo['price'] ?? $skuInfo['consignPrice'],
                    'compare_price' => $skuInfo['price'] ?? $skuInfo['consignPrice'],
                    'spec_info' => $skuAttr,
                    'spec_name' => implode('/', $skuName),
                    'images' => [$skuImage]
                ];
            }
        }

        $price = min(array_column($skuList, 'sale_price'));
        return [
            'goods_id' => $product['offerId'],
            'spu' => $product['offerId'],
            'goods_name' => $product['subject' . $isTrans],
            'main_images' => $product['productImage']['images'],
            'cover_image' => $product['productImage']['images'][0] ?? '',
            'main_video' => $product['mainVideo'] ?? '',
            'price' => $price,
            'props' => $this->getAttribute($product['productAttribute'] ?? [], $isTrans),
            'options' => $this->getOptions($product['productSkuInfos'] ?? [], $isTrans),
            'detail' => $product['description'],
            'collect_platform' => '1688',
//            'collect_url' => $this->collectUrl,
            'collect_url' => 'https://detail.1688.com/offer/' . $product['offerId'] . '.html',
            'sku_list' => $skuList,
            'shop_id' => $product['sellerOpenId'],
        ];
    }

    protected function getOptions($skuInfo, $isTrans)
    {
        $options = [];
        foreach ($skuInfo as $sku) {
            foreach ($sku['skuAttributes'] as $key => $attr) {
                $specs = [
                    'name' => $attr['value' . $isTrans],
                    'image' => $attr['skuImageUrl'] ?? ''
                ];
                if (isset($options[$key])) {
                    $nameArray = array_column($options[$key]['specs'], 'name');
                    if (!in_array($specs['name'], $nameArray)) {
                        $options[$key]['specs'][] = $specs;
                    }
                } else {
                    $options[$key] = [
                        'name' => $attr['attributeName' . $isTrans],
                        'specs' => [
                            $specs
                        ]
                    ];
                }
            }
        }
        return $options;
    }

    public function getAttribute($attribute, $isTrans)
    {
        $props = [];
        foreach ($attribute as $value) {
            $props[] = [
                'name' => $value['attributeName' . $isTrans],
                'value' => $value['value' . $isTrans],
            ];
        }
        return $props;
    }

    /**
     * 创建采购订单
     * @param $data // 商品数据
     * @return bool
     * @throws Exception
     */
    public function createCrossOrder($id, $data): bool
    {
        return $this->request->createCrossOrder($id, $data);
    }

    /**
     * 同步1688订单物流信息
     * @param $orderItem
     * @return bool
     */
    public function syncOrdersStatus($orderItem): bool
    {
        return $this->request->syncOrdersStatus($orderItem);
    }

    /**
     * 获取授权链接
     * @return string
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function alibabaAuthorize($id): string
    {
        return $this->request->alibabaAuthorize($id);
    }

    /**
     * 获取授权token并保存
     * @return bool
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws GuzzleException
     */
    public function generateAccessToken(): bool
    {
        return $this->request->generateAccessToken();
    }

    public function validateParams($keyword)
    {
        $config = SystemConfigService::getConfigValue(SystemConfig::SHOPIFY_APP_REVIEW_MODE);

        if ($config) {
            $keyword1 = strtolower(str_replace(' ', '', $keyword));

            $sensitive = explode(',', file_get_contents(base_path('resources/lang/sensitive.json')));
            if (preg_match('/sex/', $keyword1) || preg_match('/adult/', $keyword1) || preg_match('/penis/', $keyword1) || preg_match('/condom/', $keyword1) || preg_match('/jb/', $keyword1) || preg_match('/breast/', $keyword1) || preg_match('/drugs/', $keyword1) || preg_match('/wine/', $keyword1) || preg_match('/alcohol/', $keyword1)) {
                return true;
            }
            if (in_array($keyword1, $sensitive)) {
                return true;
            }

        }
        return false;
    }
}
