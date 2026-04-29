<?php

/**
 * @Author: h9471
 * @Created: 2019/9/9 18:19
 */

namespace App\Http\Controllers\Admin;

use App\Helper\Password;
use App\Http\Resources\Admin\RouteMenuList;
use App\Http\Resources\MenuGroupInfo;
use App\Http\Resources\RouteMenuEnabledList;
use App\Lib\Code;
use App\Lib\Language;
use App\Models\Admin;
use App\Models\AdminGroup;
use App\Models\AdminGroupModel;
use App\Models\AdminLoginLog;
use App\Services\Admin\AdminGroupService;
use App\Services\Admin\JavaAdminAuthService;
use App\Services\Admin\JavaJwtService;
use App\Services\ApiResponseService;
use App\Services\Client\AuthService;
use App\Services\CompanyAuditService;
use Exception;
use GatewayWorker\Lib\Gateway;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    protected $javaAuthService;
    protected $javaJwtService;

    public function __construct(JavaAdminAuthService $javaAuthService, JavaJwtService $javaJwtService)
    {
        $this->javaAuthService = $javaAuthService;
        $this->javaJwtService = $javaJwtService;
    }

    /**
     * @param Request $request
     * @return array|JsonResponse
     */
    public function login(Request $request)
    {
        // 首先尝试调用Java服务进行登录
        $javaResult = $this->javaAuthService->login([
            'username' => $request['username'],
            'password' => $request['password']
        ]);
        $token = null;
        // 如果Java服务登录成功，则返回Java服务的结果
        if (isset($javaResult['code'])) {
            if ($javaResult['code'] == 200) {
                // 直接使用Java服务返回的token
                $token = $javaResult['data'] ?? null;
                // 解析token并注入admin
                $payload = $this->javaJwtService->parseToken($token);
                if ($payload) {
                    $admin = $this->findAdminByUserId($payload['sub']);
                    // 注入admin
                    auth('admin')->login($admin);
                }
            }else{
                return ApiResponseService::error(Code::OPERATE_FAIL, $javaResult['message']);
            }
        }

        if (!$token) {
            return ApiResponseService::error(Code::OPERATE_FAIL, '用户名或密码错误！');
        }

        if (auth('admin')->user()->enable === Admin::ENABLE_FORBID_LOGIN) {
            auth('admin')->logout();

            return ApiResponseService::error(Code::OPERATE_FAIL, '暂时无法登录，请联系管理员');
        }

        $this->updateLastLoginAtAndLog();

        return $this->respondWithToken($token);
    }

    private function findAdminByUserId($user_id)
    {
        // 尝试查找现有的管理员用户
        $admin = Admin::find($user_id);
        if ($admin) {
            return $admin;
        }else{
            return false;
        }
    }

    /**
     * 回退到PHP原生认证逻辑
     * @param Request $request
     * @return mixed
     */
    protected function fallbackToPhpAuth(Request $request)
    {
        $this->validateLogin($request);
        $credentials = [
            $this->username() => $request['username'],
            'password' => Password::decrypt($request['password']),
        ];

        info('当前登录用户：', $credentials);

        $token = $this->guard()->attempt($credentials);
        return $token;
    }

    /**
     * @return JsonResponse
     * @throws AuthenticationException
     */
    public function logout()
    {
        auth('admin')->logout();

        throw new AuthenticationException('Unauthenticated.');
    }

    /**
     * @return array
     */
    public function refresh()
    {
        return $this->respondWithToken(auth('admin')->refresh());
    }

    /**
     * 获得验证码
     * @throws \Exception
     */
    public function getCaptcha()
    {
        try {
            $captcha = app('captcha')->create('default', true);
        } catch (\Exception $e) {
            return ApiResponseService::error(Code::OPERATE_FAIL, '验证码生成失败');
        }

        return ApiResponseService::success(compact('captcha'));
    }

    /**
     * @return bool
     */
    protected function updateLastLoginAtAndLog(): bool
    {
        $admin = auth('admin')->user();

        $admin->last_login_at = now();

        $admin->login_ip = $_SERVER['REMOTE_ADDR'];

        AdminLoginLog::logging($admin, \request());

        return $admin->push();
    }

    /**
     * @return bool
     */
    protected function checkIfAudited(): bool
    {
        return \auth('admin')->user()->is_audited === 1;
    }

    /**
     * @param $token
     * @return array
     */
    protected function respondWithToken($token)
    {
        $user = [
            'id' => auth('admin')->user()->id,
            'username' => auth('admin')->user()->username,
            'email' => auth('admin')->user()->email,
            'last_login_at' => auth('admin')->user()->last_login_at,
            'avatar' => auth('admin')->user()->avatar,
            'login_ip' => auth('admin')->user()->login_ip,
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => auth('admin')->factory()->getTTL() * 60,
            'check_auth' => auth('admin')->user()->check_auth ?? 0,
        ];
        return ApiResponseService::success($user);
    }

    /**
     * Validate the user login request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    protected function validateLogin(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
            'key' => 'required|string',
            'captcha' => 'required|captcha_api:' . ($request->get('key') ?? 'key'),
        ]);
    }

    /**
     * Get the login username to be used by the controller.
     *
     * @return string
     */
    protected function username()
    {
        $username = request()->get('username');

        if (preg_match('/^(?:\+?86)?1(?:3\d{3}|5[^4\D]\d{2}|8\d{3}|7(?:[35678]\d{2}|4(?:0\d|1[0-2]|9\d))|9[189]\d{2}|66\d{2})\d{6}$/', $username)) {
            return 'phone';
        }

        if (filter_var($username, FILTER_VALIDATE_EMAIL)) {
            return 'email';
        }

        return 'username';
    }

    /**
     * Get the guard to be used during authentication.
     *
     * @return \Illuminate\Contracts\Auth\StatefulGuard
     */
    protected function guard()
    {
        return Auth::guard('admin');
    }

    /**
     * @return int
     */
    protected function getGroupBuyingStatus($id)
    {
        return (int) (in_array($id, [9, 37])
            || ! app()->environment('production'));
    }

    /**
     * 获取菜单树
     * @throws Exception
     * @throws BindingResolutionException
     */
    public function getMenuTree(): AnonymousResourceCollection
    {
        /**
         * @var AdminGroupService $adminGroupService
         */
        $adminGroupService = app()->make(AdminGroupService::class);

        if (auth('admin')->user() instanceof Admin && auth('admin')->user()->group_id === AdminGroupModel::first()->id) {
            return RouteMenuList::collection($adminGroupService->getSuperAdminPermissions())
                ->additional(ApiResponseService::success());
        }

        return RouteMenuList::collection($adminGroupService->getPermissions(auth('admin')->user()->group_id))
            ->additional(ApiResponseService::success());
    }
    /**
     * 语言列表
     * @return array
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/9/26 11:54
     */
    public function getLanguageList()
    {
        return ApiResponseService::success(Language::getLanguageList());
    }

    /**
     * 获取手机号国家区号列表
     * @return array
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/9/26 15:13
     */
    public function getPhoneAreaCodeList()
    {
        return ApiResponseService::success((new AuthService())->getPhoneAreaCodeList());
    }


    public function clearOpcache()
    {
        info('清除opcache缓存', [opcache_reset()]);
    }

}
