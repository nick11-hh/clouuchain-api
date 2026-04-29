<?php

namespace App\Services\Shopify;

use App\Lib\Code;
use Osiset\BasicShopifyAPI\Session;
use Osiset\ShopifyApp\Contracts\ApiHelper as IApiHelper;
use Osiset\ShopifyApp\Objects\Enums\AuthMode;
use Osiset\ShopifyApp\Objects\Values\ShopDomain;
use stdClass;
use App\Exceptions\AccidentException;

/**
 * 权限验证
 */
class AuthorizeShop
{
    /**
     * Execution
     *
     * @param string $shopDomain The shop ID.
     * @param string|null $code The code from Shopify.
     *
     * @return stdClass|bool
     */
    public function __invoke($shopDomain, ?string $code): stdClass
    {
        try {
            //组装 apiHelper
            $session = new Session(
                ShopDomain::fromNative($shopDomain)->toNative(),
                null
            );

            /**@var \Osiset\ShopifyApp\Contracts\ApiHelper $apiHelper */
            $apiHelper = resolve(IApiHelper::class)->make($session);

            //生成授权URL
            $return['url'] = $apiHelper->buildAuthUrl(AuthMode::OFFLINE(), config('shopify-app.shop_scope'));

            // 如果code为空，就直接返回
            if (empty($code)) {
                return (object)$return;
            }

            //获取token
            $return = array_merge($return, $apiHelper->getAccessData($code)->toArray());

        } catch (\Exception $e) {
            info('shop-auth', ['exception' => $e->getMessage()]);
            throw new AccidentException('认证失败,请重新操作', Code::OPERATE_FAIL);
        }

        return (object)$return;
    }
}
