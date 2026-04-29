<?php

namespace App\Services\Client;

use App\Lib\Code;
use App\Models\OauthClients;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Exceptions\AccidentException;

class ApplicationService extends BaseService
{
    /**
     * @throws AccidentException
     */
    public function create(Request $request)
    {
        $custom_id = $request->get('custom_id');
        $license_url = $request->get('license_url');
        $model = new OauthClients();
        $auth = $model::where('user_id', $custom_id)->first();

        try {
            if(empty($auth)) {
                $authData = [
                    'user_id' => $custom_id,
                    'name'    => Str::random(10),
                    'secret'  => Str::random(64),
                    'redirect'=> '',
                    'personal_access_client' => 0,
                    'password_client' => 0,
                    'revoked' => 0,
                    'license_url' => $license_url
                ];

                $model::create($authData);
            }
        } catch (Exception $e) {

            info('生成授权app失败', [
                'msg' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);

            throw new AccidentException('生成授权应用失败', Code::OPERATE_FAIL);
        }

        return true;
    }

    public function getAuthInfo()
    {
        $uuid = getCurrentUuid();
        $custom_id = auth()->user()->custom_id;
        $model = new OauthClients();
        $authInfo = $model->where('user_id', $custom_id)->select('name', 'secret')->first();

        if(empty($authInfo)) {
            return [
                'url'  => '',
                'uuid' => '',
                'name' => '',
                'secret' => ''
            ];
        }

        return [
            'url' => config('app.url'),
            'uuid' => $uuid,
            'name' => $authInfo->name,
            'secret' => $authInfo->secret
        ];
    }
}
