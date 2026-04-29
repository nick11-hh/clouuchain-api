<?php

namespace App\Http\Middleware\Shopify;

use App\Services\Client\ShopService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Response;
use App\Models\ShopModel;

/**
 * 这个主要是验证WEBHOOK
 */
class AuthWebhook
{
    /**
     * Handle an incoming request to ensure webhook is valid.
     *
     * @param Request  $request The request object.
     * @param \Closure $next    The next action.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        info('店铺webhook接收 '. $request->header('x-shopify-shop-domain'));
        $hmac = $request->header('x-shopify-hmac-sha256') ?: '';
        $data = $request->getContent();

        (new ShopService(new ShopModel()))->setShopifyKeySecret();
        $apiSecret =  Config::get('shopify-app.api_secret');

        $verify = $this->verifySign($data, $apiSecret, $hmac);

        if (!$verify) {
            $shopDomain = $request->header('x-shopify-shop-domain');

            info('shopify verify signature error Invalid webhook signature');
            info('verify sign params', ['shop_domain' => $shopDomain, 'data' => $data, 'shopify_hmac' => $hmac, 'api_secret' => $apiSecret]);

            //兼容签名校验失败，但店铺存在的情况
//            $shop = ShopModel::query()->where('shop_url', $shopDomain)->exists();
//            if (empty($shop)) {
//                // Issue with HMAC or missing shop header
                return Response::make('Invalid webhook signature.', 401);
//            }

        }

        // All good, process webhook
        return $next($request);
    }

    public function create_hmac(array $opts, string $secret)
    {
        // Setup defaults
        $data = $opts['data'];
        $raw = $opts['raw'] ?? false;
        $buildQuery = $opts['buildQuery'] ?? false;
        $buildQueryWithJoin = $opts['buildQueryWithJoin'] ?? false;
        $encode = $opts['encode'] ?? false;

        if ($buildQuery) {
            //Query params must be sorted and compiled
            ksort($data);
            $queryCompiled = [];
            foreach ($data as $key => $value) {
                $queryCompiled[] = "{$key}=".(is_array($value) ? implode(',', $value) : $value);
            }
            $data = implode(
                ($buildQueryWithJoin ? '&' : ''),
                $queryCompiled
            );
        }

        // Create the hmac all based on the secret
        $hmac = hash_hmac('sha256', $data, $secret, $raw);

        // Return based on options
        return $encode ? base64_encode($hmac) : $hmac;
    }

    public function verifySign($body, $secret, $hmac)
    {
        $calculatedHmac = base64_encode(hash_hmac('sha256', $body, $secret, true));
        return hash_equals($calculatedHmac, $hmac);
    }
}
