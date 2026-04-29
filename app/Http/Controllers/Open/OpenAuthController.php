<?php

namespace App\Http\Controllers\Open;

use App\Exceptions\AccidentException;
use App\Http\Controllers\Controller;
use App\Lib\Code;
use App\Models\OauthClients;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Firebase\JWT\JWT;
use Carbon\Carbon;

class OpenAuthController extends Controller
{
    private string $jwtSecret;
    private string $jwtAlgo;

    public function __construct()
    {
        $this->jwtSecret = env('JWT_SECRET');
        $this->jwtAlgo = env('JWT_ALGO');
    }

    /**
     * @throws AccidentException
     */
    public function token(Request $request): JsonResponse
    {
        $appKey = $request->input('app_key');
        $appSecret = $request->input('app_secret');

        $app = OauthClients::where('name', $appKey)
            ->where('secret', $appSecret)
            ->first();

        if (!$app) {
            throw new AccidentException('unauthorized', Code::USER_NOT_AUTH);
        }

        $now = Carbon::now()->timestamp;
        $exp = Carbon::now()->addYear()->timestamp; // 一年过期时间

        $payload = [
            'iss' => 'dropshipping', // 签发方
            'iat' => $now,
            'exp' => $exp,
            'custom_id' => $app->user_id,
            'app_key' => $app->name,
        ];

        $jwt = JWT::encode($payload, $this->jwtSecret, $this->jwtAlgo);

        return response()->json([
            'access_token' => $jwt,
            'token_type' => 'Bearer',
            'expires_in' => $exp - $now,
        ]);
    }
}
