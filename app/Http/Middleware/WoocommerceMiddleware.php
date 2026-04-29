<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\RestApiConfig;

class WoocommerceMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        if(! $request->has(['consumer_key', 'consumer_secret'])){

            return response()->json([
                'code' => 'woocommerce_rest_cannot_view',
                'message' => "抱歉，您无法列出资源。",
                'data' => [
                    'status' => 401
                ]
            ]);
        }

        $key = $request->query('consumer_key');

        $secret = $request->query('consumer_secret');

        if(empty($key) || empty($secret)){

            return response()->json([
                'code' => 'woocommerce_rest_cannot_view',
                'message' => "Consumer key 或 Consumer secret 不能为空",
                'data' => [
                    'status' => 401
                ]
            ]);
        }

        try {
            
            $woocommerceConfig = RestApiConfig::where('type', RestApiConfig::TYPE_1)->where('key', $key)->where('secret', $secret)->first();
        } catch (\Illuminate\Database\QueryException $e) {
            
            return response()->json([
                'code' => 'woocommerce_rest_server_error',
                'message' => "Invalid data",
                'data' => [
                    'status' => 500
                ]
            ]);
        }

        if(!$woocommerceConfig){

            return response()->json([
                'code' => 'woocommerce_rest_authentication_error',
                'message' => "Consumer key or Consumer secret is invalid.",
                'data' => [
                    'status' => 401
                ]
            ]);
        }

        // 获取当前路由
        $currentRoute = $request->route();

        $uri = $currentRoute->uri();

        $methods = $currentRoute->methods();

        if($woocommerceConfig->permission == RestApiConfig::PERMISSION_1){

            if($uri == 'wp-json/wc/v3/orders/{id}' && $methods[0] == 'PUT'){

                return response()->json([
                    'code' => 'woocommerce_rest_api_unauthorized',
                    'message' => "No permission",
                    'data' => [
                        'status' => 403
                    ]
                ]);
            }

            if($uri == 'wp-json/wc/v3/orders/{id}/NOTES' && $methods[0] == 'POST'){

                return response()->json([
                    'code' => 'woocommerce_rest_api_unauthorized',
                    'message' => "No permission",
                    'data' => [
                        'status' => 403
                    ]
                ]);
            }
        }

        if($woocommerceConfig->permission == RestApiConfig::PERMISSION_2){
            
            $uriArr = [
                'wp-json/wc/v3/orders', 
                'wp-json/wc/v3/orders/{id}', 
                'wp-json/wc/v3/orders/{id}/NOTES', 
                'wp-json/wc/v3/orders/{id}/NOTES/{noteid}', 
                'wp-json/wc/v3/products', 
                'wp-json/wc/v3/products/{id}'
            ];    

            if(in_array($uri, $uriArr) && $methods[0] == 'GET'){

                return response()->json([
                    'code' => 'woocommerce_rest_api_unauthorized',
                    'message' => "No permission",
                    'data' => [
                        'status' => 403
                    ]
                ]);
            }
        }

        // $request->headers->set('Referer', 'https://localhost:5173/');
        return $next($request);
    }
}
