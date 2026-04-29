<?php

namespace App\Services\Client;

use App\Events\ClientCustomRegister;
use App\Helper\Password;
use App\Lib\Code;
use App\Mail\MailConfig;
use App\Mail\ResetPasswordEmail;
use App\Models\Admin;
use App\Models\AssignDataPermission;
use App\Models\Country;
use App\Models\CTUUserMessage;
use App\Models\Custom;
use App\Models\EmailTemplate;
use App\Models\MailSmtpConfig;
use App\Models\ShoppingCart;
use App\Models\User;
use App\Models\WorldCountries;
use App\Models\CustomInvoiceAddressModel;
use App\Services\Base\PermissionBaseService;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use App\Mail\VerificationCodeEmail;
use App\Exceptions\AccidentException;

class AuthService
{
    public function login($params)
    {
        validator($params, [
            'username' => 'required|string',
            'password' => 'required|string'
        ])->validate();
        logger("登录日志信息", $params);

        $credentials = [
            $this->username($params['username']) => $params['username'],
            'password' => Password::decrypt($params['password']),
        ];
        $token = Auth::guard('client')->attempt($credentials);
        if (!$token) throw new AccidentException(__('用户名或密码错误'), Code::OPERATE_FAIL);
        if (auth('client')->user()->status == User::STATUS_DISABLE) {
            throw new AccidentException(__('账号已注销'), Code::OPERATE_FAIL);
        }
        $this->loginAfter(auth('client')->user());

        return $this->getLoginInfo($token);
    }

    /**
     * @param $params
     * @return mixed
     * @throws \Illuminate\Validation\ValidationException
     */
    public function register($params)
    {
        validator($params, [
            'username' => 'required|string|between:2,30',
            'password' => 'required|string|between:6,20',
            'confirm_password' => 'required|string|same:password',
            'company_name' => 'sometimes|nullable|string',
            'email' => 'required|email',
            'email_verification_code' => 'required|integer',
            'phone' => 'sometimes|nullable|string',
            'phone_area_code' => 'sometimes|nullable|string',
            'invite_id' => 'sometimes|nullable|numeric',//这个邀请人ID 再生成邀请链接的时候就没有传，所以这里是空
            'default_language' => 'sometimes|nullable|string',
            'admin_invite_code'  => 'sometimes|nullable|string',
            'country_code' => 'sometimes|nullable|string',
            // 'privacy_policies' => 'required|accepted'
        ])->validate();

        return DB::transaction(function () use ($params) {
            $user = User::where('username', $params['username'])
                ->orWhere('email', $params['email'])
                ->when(!empty($params['phone']), function ($query) use ($params) {
                    $query->orWhere('phone', $params['phone']);
                })->first();
            if (!empty($user)) {
                if ($user->username === $params['username']) throw new AccidentException('用户名已存在', Code::OPERATE_FAIL);
                if ($user->email === $params['email']) throw new AccidentException('该邮箱已注册', Code::OPERATE_FAIL);
                if ($user->username === $params['phone']) throw new AccidentException('该手机号码已注册', Code::OPERATE_FAIL);
            }

            if (!self::checkEmailVerificationCode(getCurrentUuid(), $params['email_verification_code'], $params['email'])) {
                throw new AccidentException('邮箱验证码错误', Code::OPERATE_FAIL);
            }

            Cache::forget(config('dropshipping.cache_prefix.client_verify_code') . getCurrentUuid() . ':' . $params['email']);


            $params['staff_id'] = $this->getAdminInviteCodeData($params);

            $inviteCustom = Custom::query()->find($params['invite_id']);  // 邀请人
            $data = Custom::init($params, $inviteCustom);
            $custom = Custom::create($data);

            // 分配客户数据权限给对应的员工
            if ($params['staff_id']) {
                PermissionBaseService::assignDataPermission($params['staff_id'], $custom->id, AssignDataPermission::CUSTOMER_PERMISSION);
            }

            $params['custom_id'] = $custom->id;
            $params['is_main'] = 1;

            $userData = User::init($params);
            $user = User::create($userData);

            $custom->customer_number = $custom->id;
            $custom->main_user_id = $user->id;
            $custom->save();

            //再把公司名、电话、邮箱保存进发票地址表
            CustomInvoiceAddressModel::updateOrCreate(
                ['customer_id' => $custom->id],
                [
                    'name' => $params['company_name']??$params['username'],
                    'phone_area_code' => $params['phone_area_code']??'',
                    'phone_number' => $params['phone']??'',
                    'email' => $params['email'],
                ]
            );

            event(new ClientCustomRegister($user));
            return true;
        });
    }

    /**
     * @param $params
     * @return mixed
     * @throws \Illuminate\Validation\ValidationException
     */
    public function forgotPassword($params)
    {
        validator($params, [
            'password' => 'required|string|between:6,20',
            'confirm_password' => 'required|string|same:password',
            'email' => 'required|email',
            'email_verification_code' => 'required|integer',
        ])->validate();

        return DB::transaction(function () use ($params) {
            $user = User::query()->where('email', $params['email'])->first();

            if (empty($user)) {
                throw new AccidentException('邮箱不存在', Code::OPERATE_FAIL);
            }

            if (!self::checkEmailVerificationCode(getCurrentUuid(), $params['email_verification_code'], $params['email'])) {
                throw new AccidentException('邮箱验证码错误', Code::OPERATE_FAIL);
            }

            Cache::forget(config('dropshipping.cache_prefix.client_verify_code') . getCurrentUuid() . ':' . $params['email']);

            $user->password = bcrypt($params['password']);
            $user->save();

            return true;
        });
    }

    /**
     * 发送注册邮箱验证码
     */
    public function emailVerificationCode()
    {
        $request = request()->toArray();
        try {
            $data = validator($request, [
                'email' => 'required|email',
                'type' => ['required', Rule::in(['register', 'forgot_password', 'change_email'])],
            ])->validate();
        } catch (ValidationException $e) {

            throw new AccidentException($e->validator->messages()->first(), Code::OPERATE_FAIL);
        }

        $key = config('dropshipping.cache_prefix.client_verify_code') . getCurrentUuid() . ':' . $data['email'];
        $antiRefreshKey = $key . ':anti-refresh';

        if (Cache::has($antiRefreshKey)) {
            throw new AccidentException('Frequent operation, please try again later.', Code::OPERATE_FAIL);
        }

        $user = User::query()->where('email', $request['email'])->first();

        switch ($request['type']) {
            case 'register':
                if ($user) {
                    throw new AccidentException('The email has already been taken.', Code::OPERATE_FAIL);
                }

                $emailTemplate = EmailTemplate::REGISTER_CHECK;
                break;
            case 'forgot_password':
                if (empty($user)) {
                    throw new AccidentException('The email cannot be found.', Code::OPERATE_FAIL);
                }

                $emailTemplate = EmailTemplate::FORGOT_PASSWORD;
                break;
            case 'change_email':
                if ($user) {
                    throw new AccidentException('The email has already been taken.', Code::OPERATE_FAIL);
                }

                $emailTemplate = EmailTemplate::CHANGE_EMAIL;
                break;
            default :
                $emailTemplate = EmailTemplate::REGISTER_CHECK;
        }

        try {
            $code = random_int(100000, 999999);

            $emailParams = [
                'user_name' => $user->username ?? '',
                'code' => $code,
            ];

            MailConfig::getEmailConfig();
            Mail::to($data['email'])->send(new VerificationCodeEmail($emailParams, $emailTemplate));

            Cache::put($antiRefreshKey, 1, 60);
        } catch (\Exception $e) {

            info('验证码发送失败', [
                'uuid' => getCurrentUuid(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'msg' => $e->getMessage()
            ]);

            throw new AccidentException('验证码发送失败', Code::OPERATE_FAIL);
        }

        return Cache::put($key, $code, 300);
    }

    /**
     * 校验邮箱验证码
     * @param int $code
     * @param $email
     * @return bool
     */
    public static function checkEmailVerificationCode(string $uuid, int $code, $email): bool
    {
        $key = config('dropshipping.cache_prefix.client_verify_code') . $uuid . ':' . $email;
        $cacheCode = Cache::get($key);

        return $code == $cacheCode;
    }

    /** 登录成功后的处理
     * @param $user
     * @return void
     */
    protected function loginAfter($user)
    {
        $user->last_login_at = Carbon::now()->toDateTimeString();
        $user->save();
    }

    /**
     * @param $username
     * @return string
     */
    protected function username($username)
    {
        if (preg_match('/^(?:\+?86)?1(?:3\d{3}|5[^4\D]\d{2}|8\d{3}|7(?:[35678]\d{2}|4(?:0\d|1[0-2]|9\d))|9[189]\d{2}|66\d{2})\d{6}$/', $username)) {
            return 'phone';
        }
        if (filter_var($username, FILTER_VALIDATE_EMAIL)) {
            return 'email';
        }
        return 'username';
    }

    /**
     * @desc supportBoard 注册登录 贝邦客户单独部署
     * @return string
     */
    protected function supportBoardLogin()
    {
        $isOpen = env('IS_OPEN_SUPPORT_BOARD');
        if (empty($isOpen)) return '';

        $addUserData = [
            'function' => 'add-user',
            'first_name' => auth('client')->user()->username,
            'profile_image' => auth('client')->user()->avatar,
            'email' => auth('client')->user()->email,
            'password' => '12345678'
        ];

        $custom = Custom::query()->findOrFail(getCustomId());
        $extra = [
            [auth('client')->user()->phone, 'phone'],
            [$custom->custom_name, 'company'],
        ];
        $addUserData['extra'] = json_encode($extra);
        $this->supportBoarPost($addUserData);

        $loginData = [
            'function' => 'login',
            'email' => auth('client')->user()->email,
            'password' => '12345678'
        ];
        return $this->supportBoarPost($loginData);
    }

    protected function supportBoarPost($query)
    {
        try {
            info('support board 请求参数', $query);

            $client = new Client([
                'base_uri' => env('SUPPORT_BOARD_BASE_URL'),
                'curl' => [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_USERAGENT => 'Support Board',
                    CURLOPT_CONNECTTIMEOUT => 5,
                ]
            ]);

            $token = env('SUPPORT_BOARD_TOKEN');
            $uri = env('SUPPORT_BOARD_API_URI');
            $data = array_merge(['token' => $token], $query);

            $response = $client->request('POST',  $uri, ['form_params' => $data]);
            $content = $response->getBody()->getContents();

            info('support board 请求返回接口', [$content]);

            $content = json_decode($content, true);

            return $content['response'] ?? '';
        } catch (GuzzleException $e) {
            logger('support board post error', ['file' => $e->getFile(), 'line' => $e->getLine(), 'msg' => $e->getMessage()]);

            return '';
        }
    }

    /**
     * 获取管理端注册邀请的管理员id
     * @param $params
     * @return int|mixed
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/10/28 14:01
     */
    public function getAdminInviteCodeData($params)
    {
        $adminInviteCode = $params['admin_invite_code'] ?? '';
        if (!empty($adminInviteCode)) {
            $admin = Admin::query()->where('invite_code', $adminInviteCode)->first();
            return $admin->id ?? 0;
        }

        return 0;
    }

    /**
     * 获取国家区号列表
     * @return array
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/9/26 15:12
     */
    public function getPhoneAreaCodeList()
    {
        $list = WorldCountries::query()
            ->with(["countryCn"])
            ->get();

        return $list->map(function ($item) {
            return [
                'id'           => $item->id,
                'name'         => $this->getAreaCodeName($item),
                'country_code' => $item->code,
                'code'         => $item->callingcode,
                'code2'        => '+' . $item->callingcode,
            ];
        })->toArray();
    }

    /**
     * 获取按照传递的语言获取区号名称(默认是英文)
     * @param $item
     * @return mixed|string
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/9/27 17:42
     */
    public function getAreaCodeName($item)
    {
        $name = $item->name;
        $countryCnName = $item->countryCn->name ?? '';

        if ($countryCnName) {
            if (isZh()) {
                $name = $countryCnName;
            }
            if (isRu()) {
                $name = Country::getRuName($countryCnName);
            }
            if (isAr()) {
                $name = Country::getArName($countryCnName);
            }
            if (isPt()) {
                $name = $countryCnName;
            }
            if (isVi()) {
                $name = $countryCnName;
            }
        }

        return $name;
    }

    /**
     * 获取登录返回的信息
     * @param $token
     * @return array
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/11/22 16:09
     */
    public function getLoginInfo($token)
    {
        $accountInfo = auth('client')->user();

        // 登录support board
        $supportBoardLogin = $this->supportBoardLogin();

        return [
            'id'            => $accountInfo->id,
            'username'      => $accountInfo->username,
            'email'         => $accountInfo->email,
            'custom_id'     => $accountInfo->custom_id,
            'avatar'        => $accountInfo->avatar,
            'access_token'  => $token,
            'token_type'    => 'Bearer',
            'carts_num'     => ShoppingCart::getCartsNum(),
            'messages_num'  => CTUUserMessage::getMessagesNum(),
            'support_board_login' => $supportBoardLogin,
            'admin_invite_code' => $accountInfo->admin_invite_code,
        ];
    }

    /** 发送重置密码邮箱链接
     * @param $params
     * @return true
     * @throws ValidationException
     * @throws AccidentException
     */
    public function sendResetPasswordEmail($params)
    {
        validator($params, [
            'email' => 'required|email',
        ])->validate();

        $user = User::query()->where('email', $params['email'])->first();
        if (empty($user)) {
            throw new AccidentException('The password reset email does not exist.');
        }

        $token = uuid_create(UUID_TYPE_RANDOM);

        Cache::put($token, ['email' => $params['email']], Carbon::now()->addHour());

        MailConfig::getEmailConfig();
        $emailParams = [
            'link' => getClientDomain() . '/pages/user/reset-password?token='. $token,
        ];
        try {
            Mail::to($params['email'])->send(new ResetPasswordEmail($emailParams));
            return true;
        } catch (\Exception $exception) {
            info('发送重置密码邮件失败', [$exception]);
            throw new AccidentException('发送邮件失败');
        }

    }


    /** 通过邮箱链接重置密码
     * @param $params
     * @return bool
     * @throws AccidentException
     * @throws ValidationException
     */
    public function resetPasswordByEmailLink($params)
    {
        validator($params, [
            'token' => 'required|string',
            'new_password' => 'required|string|between:6,20',
            "confirm_password" => "required|string|between:6,20|same:new_password",
        ])->validate();

        $data = Cache::get($params['token']);
        if (empty($data['email'])) {
            throw new AccidentException('The password reset link has expired.');
        }

        $user = User::query()->where('email', $data['email'])->first();
        if (empty($user)) {
            throw new AccidentException('The password reset email does not exist.');
        }

        Cache::forget($params['token']);

        $user->password = bcrypt($params['new_password']);
        return $user->save();
    }

}
