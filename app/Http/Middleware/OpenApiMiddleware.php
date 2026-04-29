<?php

namespace App\Http\Middleware;

use App\Lib\Code;
use Closure;
use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Exceptions\AccidentException;
use Illuminate\Http\Response;

class OpenApiMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure(Request): (Response|RedirectResponse) $next
     * @return Response|RedirectResponse
     * @throws AccidentException
     */
    public function handle(Request $request, Closure $next)
    {
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            throw new AccidentException('unauthorized', Code::USER_NOT_AUTH);
        }
        $token = substr($authHeader, 7);
        try {
            $decoded = JWT::decode($token, new Key(env('JWT_SECRET'), env('JWT_ALGO')));
            // 把 app_id 注入 request
            $request->merge([
                'custom_id' => $decoded->custom_id,
                'app_key' => $decoded->app_key,
            ]);
        } catch (Exception $e) {
            throw new AccidentException('unauthorized', Code::USER_NOT_AUTH);
        }

        return $next($request);
    }
}
