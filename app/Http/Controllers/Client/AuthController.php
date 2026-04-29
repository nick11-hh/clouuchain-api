<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Resources\Client\ClientMenuList;
use App\Lib\Code;
use App\Lib\Language;
use App\Models\SystemConfig;
use App\Models\UserGroup;
use App\Services\ApiResponseService;
use App\Services\Base\SystemConfigService;
use App\Services\Client\AuthService;
use App\Services\Client\UserGroupService;
use App\Services\ThirdPartyApi\GeoIpService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    private $service;

    public function __construct(AuthService $authService)
    {
        $this->service = $authService;
    }

    public function login(Request $request)
    {
        $userData = $this->service->login($request->all());
        if ($userData) {
            return ApiResponseService::success($userData, Code::SUCCESS, '登录成功');
        }
        return ApiResponseService::errorMessage('登录失败');
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function register(Request $request)
    {
        if ($this->service->register($request->all())) {
            return ApiResponseService::successMessage('注册成功');
        }
        return ApiResponseService::errorMessage('注册失败');
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function forgotPassword(Request $request)
    {
        if ($this->service->forgotPassword($request->all())) {
            return ApiResponseService::successMessage();
        }
        return ApiResponseService::errorMessage();
    }

    /**
     * 发送注册邮箱验证码
     */
    public function emailVerificationCode()
    {
        if ($this->service->emailVerificationCode()) {
            return ApiResponseService::successMessage();
        }
        return ApiResponseService::errorMessage();
    }

    /**
     * @return \Illuminate\Http\JsonResponse|array
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

    /** 获取菜单树权限
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     * @throws \Exception
     */
    public function getMenuTree()
    {
        $groupId = auth('client')->user()->group_id;
        $userGroupService = new UserGroupService();
        $group = $userGroupService->show($groupId);
        if ($group->is_default === 1) {
            $data = $userGroupService->getDefaultGroupPermissions();
        } else {
            $data = $userGroupService->getPermissions($groupId);
        }
        if (SystemConfigService::getConfigValue(SystemConfig::SHOPIFY_APP_REVIEW_MODE)) {
            $data = $this->filterMenuTree($data);
        }
        return ClientMenuList::collection($data)->additional(ApiResponseService::success());
    }

    public function filterMenuTree($menuTree)
    {
        $filterArray = [2200, 2300, 2600, 3300, 3400, 6000, 3200];
        $menuList = [];
        foreach ($menuTree as $menu) {
            if (in_array($menu->tag, $filterArray)) continue;
            if (!empty($menu->routes)) {
                $menu->routes = $this->filterMenuTree($menu->routes);
            }
            $menuList[] = $menu;
        }
        return $menuList;
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
        return ApiResponseService::success($this->service->getPhoneAreaCodeList());
    }

    /**
     * 刷新TOKEN并返回登录信息
     * @return array
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/11/22 16:02
     */
    public function refreshToken()
    {
        $token = auth('client')->refresh();
        return ApiResponseService::success($this->service->getLoginInfo($token), Code::SUCCESS, '登录成功');
    }


    public function getIpLocation(Request $request)
    {
        $location = (new GeoIpService())->getCountryCityByIp($request->getClientIp());
        return ApiResponseService::success($location);
    }


    /***
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\AccidentException
     * @throws \Illuminate\Validation\ValidationException
     */
    public function sendResetPasswordEmail(Request $request)
    {
        if ($this->service->sendResetPasswordEmail($request->all())) {
            return ApiResponseService::successMessage();
        }
        return ApiResponseService::errorMessage();
    }

    /**
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\AccidentException
     * @throws \Illuminate\Validation\ValidationException
     */
    public function resetPasswordByEmailLink(Request $request)
    {
        if ($this->service->resetPasswordByEmailLink($request->all())) {
            return ApiResponseService::successMessage();
        }
        return ApiResponseService::errorMessage();
    }

}
