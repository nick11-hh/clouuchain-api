<?php

namespace App\Http\Middleware;

use App\Lib\Code;
use App\Models\OauthClients;
use Closure;
use Exception;
use Illuminate\Http\Request;
use App\Exceptions\AccidentException;

class AccessTokenMiddleware
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
        $secret = $request->get('app_secret', '');
        $auth = OauthClients::where(['name' => $request->get('app_key', ''), 'secret' => $secret])->value('id');

        if(empty($auth)) {
            throw new AccidentException('Authorization failed', Code::USER_NOT_AUTH);
        }

        $request->merge([
            'grant_type' => 'client_credentials',
            'client_id'  => $auth,
            'client_secret' => $secret
        ]);

        return $next($request);
    }
}
