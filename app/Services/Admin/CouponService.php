<?php

/**
 * @Author: h9471
 * @Created: 2019/10/24 11:40
 */

namespace App\Services\Admin;

use App\Exceptions\AccidentException;
use App\Helper\CosUtil;
use App\Jobs\CouponReceivedNotify;
use App\Jobs\Export\CouponExport;
use App\Lib\Code;
use App\Models\Coupon;
use App\Models\CouponCode;
use App\Models\ExcelExport;
use App\Models\ExpressLine;
use App\Models\ExpressLineModel;
use App\Models\MemberLevel;
use App\Models\Model;
use App\Models\NewCusFelfare;
use App\Models\Order;
use App\Models\User;
use App\Models\UserCoupon;
use App\Models\UserGroup;
use App\Models\UserTag;
use App\Models\Views\VUserMember;
use App\Services\WechatServices;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class CouponService extends BaseService
{
    protected $filterRules = [
        'userCoupons.user:id;name,amount' => ['like', 'keyword'],
        'template_id' => ['=', 'template_id'],
    ];

    protected $orderBy = ['id' => 'desc'];

    protected $newCusWelfare;

    public function __construct(Coupon $coupon, NewCusFelfare $newCusWelfare)
    {
        $this->request = request();
        $this->formData = $this->request->all();
        $this->model = $coupon;
        $this->query = $coupon->newQuery();
        $this->newCusWelfare = $newCusWelfare;
        $this->setFilterRules();
    }

    /**
     * 列表
     * @return mixed
     */
    public function index()
    {
        $this->setStatusFilter();

        $s0 = UserCoupon::query()
            ->selectRaw('count(id) as count')
            ->whereColumn('coupon_id', '=', (new Coupon())->getTable() . '.id')
            ->whereNull('used_at')
            ->where('enabled', true);

        $s3 = UserCoupon::query()
            ->selectRaw('count(id) as count')
            ->whereColumn('coupon_id', '=', (new Coupon())->getTable() . '.id')
            ->where('enabled', false);

        $s2 = UserCoupon::query()
            ->selectRaw('count(id) as count')
            ->whereColumn('coupon_id', '=', (new Coupon())->getTable() . '.id')
            ->where('expired_at', '<',now());

        $this->query->addSelect( ['unused_count' => $s0, 'invalid_count' => $s3, 'expired_count' => $s2])
            ->withCount('userCoupons');

        return parent::index();
    }

    /**
     * 用户优惠券列表
     * @param  int  $coupon_id
     * @return mixed
     */
    public function indexOfUsers(int $coupon_id)
    {
        $this->query = UserCoupon::with(
            [
                'coupon',
            ]
        )->where('coupon_id', $coupon_id);

        return parent::index();
    }

    /**
     * @return mixed
     */
    public function getEnabledLines()
    {
        return ExpressLineModel::with('countries')
            ->where('enabled', 1)
            ->when(request()->input('keyword'), function ($query) {
                $word = request()->input('keyword');

                $query->where('name', 'like', "%$word%");
            })
            ->selectRaw('id, name')
            ->pageSize();
    }

    /**
     * 按用户投放优惠券
     * @param  int  $id
     * @param  array  $data
     * @return bool
     * @throws Throwable
     */
    public function launchByUser(int $id, array $data): bool
    {
        validator($data, $this->launchByUserRules())->validate();

        $coupon = $this->model::findOrFail($id);

        $this->canLaunch($coupon);

        throw_unless(
            User::isAllBelongsTo(auth()->user()->company_id, $data['user_id']),
            new AccidentException('用户ID错误', Code::OPERATE_FAIL)
        );

        $create = array_map(function ($value) use (&$coupon) {
            $coupon->total_count++;

            //不忽略分享券的手动分发计数
            if ($coupon->coupon_type_id === Coupon::TYPE_SHARED && ! $coupon->ignore_launch_count) {
                $coupon->share_count++;
            }

            return [
                'user_id' => $value,
                'coupon_id' => $coupon->id,
                'coupon_code' => Str::random(10),
            ];
        }, $data['user_id']);

        $coupon->save();

        return $coupon->userCoupons()->createMany($create)->count() === count($create);
    }

    /**
     * @param int $id
     * @param array $data
     * @return bool
     * @throws Exception
     */
    public function launch(int $id, array $data)
    {
        $data = validator($data, $this->launchRules())->validate();

        $coupon = $this->model::findOrFail($id);

        $this->canLaunch($coupon);

        return DB::transaction(function () use ($data, $coupon) {
            $mSub = VUserMember::query()->select(['user_id', 'level_id']);

            if (empty($data['group_ids']) && empty($data['level_ids']) && empty($data['tag_ids']) && empty($data['user_ids'])) {
                throw new AccidentException('请选择需要投放的用户', Code::OPERATE_FAIL);
            }
            // 按时间投放的优惠券
            if ($coupon->days) {
                $effectedAt = now()->toDateTimeString();
                $expiredAt = Carbon::now()->addDays($coupon->days)->toDateTimeString();
            } else {
                $effectedAt = $coupon->effected_at;
                $expiredAt = $coupon->expired_at;
            }

            User::query()
                ->leftJoinSub($mSub, 'm', 'm.user_id', '=', (new User())->getTable() . '.id')
                ->where(function ($query) use ($data) {
                    if ($data['group_ids'] ?? []) {
                        $query->orWhereIn('user_group_id', $data['group_ids']);
                    }
                    if ($data['level_ids'] ?? []) {
                        $query->orWhereIn('level_id', $data['level_ids']);
                    }
                    if ($data['tag_ids'] ?? []) {
                        $query->orWhereHas('tags', function ($query) use ($data) {
                            $query->whereKey($data['tag_ids']);
                        });
                    }
                    if ($data['user_ids'] ?? []) {
                        $query->orWhereIn('id', $data['user_ids']);
                    }
                })->select(['id', 'user_group_id'])
                ->chunkById(350, function ($users) use (&$coupon, $data, $effectedAt, $expiredAt) {
                    $create = [];

                    foreach ($users as $user) {
                        $coupon->total_count += ($data['count'] ?? 1);
                        //不忽略分享券的手动分发计数
                        if ($coupon->coupon_type_id === Coupon::TYPE_SHARED && !$coupon->ignore_launch_count) {
                            $coupon->share_count += ($data['count'] ?? 1);
                        }

                        for ($i = 1; $i <= ($data['count'] ?? 1); $i++) {
                            $create[] = [
                                'user_id' => $user->id,
                                'coupon_id' => $coupon->id,
                                'coupon_code' => Str::random(10),
                                'created_at' => now(),
                                'updated_at' => now(),
                                'company_id' => $coupon->company_id,
                                'effected_at' => $effectedAt,
                                'expired_at' => $expiredAt,
                            ];
                        }
                    }

                    $coupon->userCoupons()->insert($create);

                    dispatch(new CouponReceivedNotify($coupon, $users))->afterCommit();
                });

            $coupon->save();

            return true;
        });
    }

    /**
     * @return array
     */
    public function getUserRelations()
    {
        $groups = UserGroup::query()
            ->selectRaw('id, name_cn as name')
            ->withCount('users')
            ->get();

        $tags = UserTag::query()
            ->select('id', 'name')
            ->withCount('users')
            ->get();

        $levels = MemberLevel::query()
            ->selectRaw('id, name')
            ->selectSub(VUserMember::query()
                ->selectRaw('count(*)')
                ->whereColumn('level_id', '=', (new MemberLevel())->getTable().'.id'),
                'users_count')
            ->get();

        return compact('groups', 'tags', 'levels');
    }

    /**
     * 添加优惠券
     *
     * @param  array  $data
     * @return bool
     * @throws Throwable
     */
    public function add(array $data): bool
    {
        validator($data, $this->addRules())->validate();

        // 过滤重量券
        $this->filtersFirstHeavy($data);

        throw_unless(
            ExpressLineModel::isValid($data['usable_line_ids'] ?? []),
            new AccidentException('线路ID不存在', Code::OPERATE_FAIL)
        );

        return DB::transaction(function () use ($data) {

            /** @var Coupon $coupon */
            $coupon = $this->model::create([
                'name' => $data['name'],
                'coupon_type_id' => Coupon::VOUCHER,
                'discount_type' => $data['discount_type'] ?? Coupon::DISCOUNT_TYPE_MONEY,
                'amount' => ($data['amount'] ?? 0) * 100,
                'threshold' => ($data['threshold'] ?? 0) * 100,
                'enabled' => true,
                'total_count' => 0,
                'used_count' => 0,
                'effected_at' => empty($data['effected_at'])
                    ? null
                    : Carbon::parse($data['effected_at'])->startOfMinute(),
                'expired_at' => empty($data['expired_at'])
                    ? null
                    : Carbon::parse($data['expired_at'])->endOfMinute(),
                'scope' => $data['scope'],
                'weight' => ($data['weight'] ?? 0) * 1000,
                'min_weight' => ($data['min_weight'] ?? 0) * 1000,
                'max_weight' => ($data['max_weight'] ?? 0) * 1000,
                'remark' => $data['remark'] ?? '',
                'days' => $data['days'] ?? 0,
                'ceiling' => ($data['ceiling'] ?? 0) * 100,
                'discount_method' => $data['discount_method'] ?? 0,
            ]);

            if ($data['is_shared'] ?? 0) {
                $code = Str::random(20);

                $codeStream = (new WechatServices())->generateCouponCode($code);

                $codeStream->saveAs(Storage::disk('admin_public')->path('coupon_code'), "$code.jpeg");

                CosUtil::send("/coupon_code/$code.jpeg");

                $coupon->update([
                    'coupon_type_id' => Coupon::TYPE_SHARED,
                    'is_shared' => 1,
                    'share_code' => $code,
                    'share_qr_code' => "/storage/admin/coupon_code/$code.jpeg",
                    'share_total_count' => $data['share_total_count'],
                    'share_each_count' => $data['share_each_count'] ?? 1,
                    'share_begin_at' => Carbon::parse($data['share_begin_at'])->startOfMinute(),
                    'share_end_at' => Carbon::parse($data['share_end_at'])->endOfMinute(),
                    'ignore_launch_count' => $data['ignore_launch_count'],
                ]);
            }

            $coupon->usableLines()->attach($data['usable_line_ids'] ?? []);
            $coupon->usableCountries()->attach($data['usable_country_ids'] ?? []);

            return true;
        });
    }

    /**
     * 过滤重量券
     *
     * @param $data
     * @return bool
     * @throws Exception
     */
    public function filtersFirstHeavy($data)
    {
        // 不是重量券直接返回
        if (empty($data['discount_type']) || $data['discount_type'] != 1) {
            return true;
        }

        // 验证是否是全路线
        if ($data['scope'] == 0) {
            throw new AccidentException('首重券必须指定路线', Code::OPERATE_FAIL);
        }
        if (empty($data['usable_line_ids'])) {
            throw new AccidentException('首重券必须指定路线', Code::OPERATE_FAIL);
        }

        // 校验路线是否都是首重续重模式
        $expressLine = ExpressLineModel::query()->whereIn('id', $data['usable_line_ids'])->where('mode', '!=', ExpressLineModel::MODE_1)->first();
        if (!empty($expressLine)) {
            throw new AccidentException('首重券路线计费模式必须为：首重续重模式', Code::OPERATE_FAIL);
        }

        return true;
    }

    /**
     * @param  int  $id
     * @return bool
     * @deprecated maybe
     */
    public function setEnable(int $id): bool
    {
        return $this->model::findOrFail($id)->update(
            [
                'enabled' => true,
            ]
        ) !== false;
    }

    /**
     * 获得新用户福利配置信息
     * @return mixed
     */
    public function getNewCustomWelfareConfiguration()
    {
        return $this->newCusWelfare::first();
    }

    /**
     * 更新新用户福利配置信息
     * @param  array  $data
     * @return bool
     * @throws \Illuminate\Validation\ValidationException
     */
    public function updateNewCustomWelfareConfiguration(array $data): bool
    {
        validator($data, $this->welfareRules())->validate();

        return $this->newCusWelfare::updateOrCreate(
            [
                'company_id' => auth()->user()->company_id,
            ],
            [
                'new_cus_send' => $data['new_cus_send'],
                'invitor_send' => $data['invitor_send'],
                'invited_send' => $data['invited_send'],
                'name' => $data['name'],
                'amount' => $data['amount'] * 100,
                'threshold' => $data['threshold'] * 100,
                'day' => $data['day'],
            ]
        ) !== false;
    }

    /**
     * @param  int  $id
     * @return bool
     */
    public function setDisabled(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            //优惠券设置失效
            $coupon = $this->model::findOrFail($id);

            $coupon->update(
                [
                    'enabled' => false,
                ]
            );
            //用户优惠券设置失效
            $coupon->usercoupons()->chunkById(500, function ($items) {
                $this->setUserCouponDisabled($items->modelKeys());
            });
            // 优惠券码设置失效
            $coupon->codes()->update(['status' => CouponCode::STATUS_INVALID]);

            if ($coupon->code) {
                Cache::delete($coupon->code);
            }

            return true;
        });
    }

    /**
     * 设置用户优惠券失效
     * @param  array  $userCouponId
     * @return bool
     */
    public function setUserCouponDisabled(array $userCouponId): bool
    {
        return DB::transaction(function () use ($userCouponId) {
            UserCoupon::whereIn('id', $userCouponId)->update(['enabled' => false]);
            //绑定了当前优惠券的待审核订单设置成没有优惠券
            Order::query()
                ->whereIn('coupon_id', $userCouponId)
                ->where(function ($query) {
                    // 待支付 待审核 货到付款待审核
                    $query->whereIn('status', [Order::WAIT_USER_PAY, Order::WAIT_CHECK])
                        ->orWhere('on_delivery_status', Order::DELIVERY_WAIT_CHECK);
                })->update([
                    'coupon_id' => 0,
                    'coupon_discount_fee' => 0,
                ]);

            return true;
        });
    }

    /**
     * 更新翻译字段
     *
     * @param  int  $id
     * @param  array  $data
     * @return bool
     * @throws \Illuminate\Validation\ValidationException
     */
    public function updateTranslateData(int $id, array $data): bool
    {
        validator($data, $this->translateRules())->validate();
        /** @var Coupon $coupon */
        $coupon = $this->model::query()->findOrFail($id);

        $coupon->setTranslation('name', $data['language'], $data['name']);

        return $coupon->save();
    }

    /**
     * @return bool
     */
    public function export()
    {
        $this->setStatusFilter();

        $this->setFilter()->setOrderBy();

        $data = $this->query->with('userCoupons')->get()
            ->map(function ($v) {
                return [
                    $v->name,
                    $v->typeName,
                    $v->couponStatusName,
                    match ($v->scope) {
                        Coupon::SCOPE_SPECIFY_LINES => $v->usableLines->pluck('name')->join(' '),
                        Coupon::SCOPE_SPECIFY_COUNTRIES => $v->usableCountries->pluck('name')->join(' '),
                        default => '不限',
                    },
                    $v->amount / 100,
                    $v->threshold / 100,
                    $v->total_count,
                    $v->userCoupons->filter(fn ($v) => $v->statusCode === 0)->count(),
                    $v->used_count,
                    $v->userCoupons->filter(fn ($v) => $v->statusCode === 2)->count(),
                    $v->userCoupons->filter(fn ($v) => $v->statusCode === 3)->count(),
                    (string)$v->effected_at,
                    (string)$v->expired_at,
                ];
            })
            ->toArray();

        $fileName = 'Coupons_' . Carbon::now()->format('Ymd') . '_' . Str::random(4) . '.xlsx';
        $filePath = "/excel/coupons/$fileName";

        $url = secure_asset(Storage::disk('admin_public')->url($filePath));

        /** @var ExcelExport $export */
        $export = ExcelExport::query()->create([
            'type' => ExcelExport::TYPE_COUPONS,
            'name' => $fileName,
            'url' => $url,
            'status' => ExcelExport::STATUS_EXPORTING,
        ]);

        dispatch(new CouponExport($export, $data));

        return true;
    }

    /**
     * @param int $id
     * @return mixed
     */
    public function couponCodeIndex(int $id)
    {
        $coupon = Coupon::query()->findOrFail($id);

        return $coupon->codes()->pageSize();
    }

    /**
     * @param int $id
     * @return int
     */
    public function disable(int $id)
    {
        return CouponCode::query()
            ->whereKey($id)
            ->update(['status' => CouponCode::STATUS_INVALID]);
    }

    /**
     * @param int $id
     * @param array $data
     * @return Model
     * @throws Exception
     */
    public function createCouponCode(int $id, array $data)
    {
        $data = validator(
            $data,
            [
                'code' => 'sometimes|nullable|max:32',
                'remark' => 'sometimes|nullable|string|max:200',
                'total_count' => 'required|integer|gt:0|max:9999',
                'each_count' => 'sometimes|nullable|integer|gte:1',
            ]
        )->validate();

        $coupon = Coupon::query()->findOrFail($id);

        if (empty($data['code'])) {
            $data['code'] = Str::random(10);
        }

        if (CouponCode::query()->where('code', $data['code'])->count()) {
            throw new AccidentException('当前兑换码已存在', Code::OPERATE_FAIL);
        }

        return CouponCode::query()->create([
            'coupon_id' => $coupon->getKey(),
            'code' => $data['code'],
            'remark' => $data['remark'] ?? '',
            'total_count' => $data['total_count'],
            'each_count' => $data['each_count'] ?? 1,
        ]);
    }

    /**
     * @param int $id
     * @return mixed
     */
    public function destroy(int $id)
    {
        return DB::transaction(function () use ($id) {
            $coupon = Coupon::query()->with('userCoupons')->findOrFail($id);

            if ($coupon->userCoupons->filter(fn($v) => $v->paid_at)->count()) {
                throw new AccidentException('当前优惠券已有用户使用，无法删除', Code::OPERATE_FAIL);
            }

            Order::query()->whereIn('coupon_id', $coupon->userCoupons->modelKeys())->update([
                'coupon_id' => 0,
                'coupon_discount_fee' => 0,
            ]);

            info('删除优惠券', [
                'admin' => auth()->id(),
                'coupon' => $coupon->toArray(),
                'user_coupons' => $coupon->userCoupons->count(),
            ]);

            $coupon->userCoupons()->delete();
            $coupon->codes()->delete();

            return $coupon->delete();
        });
    }

    /**
     * 状态过滤
     * @return void
     */
    protected function setStatusFilter(): void
    {
        if ($this->formData['status'] ?? []) {
            switch ($this->formData['status']) {
                case Coupon::COUPON_NOT_EFFECTED:
                    $this->query->notEffected();
                    break;
                case Coupon::COUPON_EFFECTED:
                    $this->query->where(function ($query) {
                        $query->effected()->orWhere(function ($query) {
                            $query->whereNull('effected_at')
                                ->whereNull('expired_at')
                                ->where('days', '>', 0);
                        });
                    });
                    break;
                case Coupon::COUPON_EXPIRED:
                    $this->query->expired();
                    break;
                case Coupon::COUPON_USE_DAYS:
                    $this->query->whereNull('effected_at')
                        ->whereNull('expired_at')
                        ->where('days', '>', 0);
                    break;
                default:
                    break;
            }
        }

        if ($this->formData['type'] ?? null) {
            if (($this->formData['type'] ?? null) == Coupon::TYPE_NEW_CUSTOM) {
                $this->query->with('userCoupons.user');
            }
            $this->query->where('coupon_type_id', $this->formData['type']);
        } else {
            $this->query->where('coupon_type_id', '!=', Coupon::TYPE_NEW_CUSTOM);
        }

        if ($this->formData['discount_type'] ?? null) {
            $this->query->where('discount_type', $this->formData['discount_type']);
        }
    }

    /**
     * 新用户福利券分发检查
     * @param  Coupon  $coupon
     * @throws Exception
     */
    protected function canLaunch(Coupon $coupon)
    {
        if ($coupon->coupon_type_id === Coupon::TYPE_NEW_CUSTOM) {
            throw new AccidentException('新用户福利券不能被分发', Code::OPERATE_FAIL);
        }
    }

    protected function translateRules()
    {
        return parent::translateRules() + [
            'name' => 'required|string|max:50',
        ];
    }

    private function launchByUserRules()
    {
        return [
            'user_id' => 'required|array',
            'user_id.*' => 'required|integer',
        ];
    }

    private function launchRules()
    {
        return [
            'group_ids' => 'sometimes|nullable|array',
            'group_ids.*' => 'required|integer',
            'level_ids' => 'sometimes|nullable|array',
            'level_ids.*' => 'required|integer',
            'tag_ids' => 'sometimes|nullable|array',
            'tag_ids.*' => 'required|integer',
            'user_ids' => 'sometimes|nullable|array',
            'user_ids.*' => 'required|integer',
            'count' => 'sometimes|nullable|integer|gt:0|max:99',
        ];
    }

    private function addRules()
    {
        return [
            'name' => 'required|string|max:50',
            'amount' => 'sometimes|nullable|numeric|gte:0',
            'threshold' => 'sometimes|nullable|numeric|gte:0',
            'days' => 'required_without:effected_at|nullable|integer||gt:0|max:999',
            'effected_at' => 'required_without:days|nullable|date',
            'expired_at' => 'required_without:days|nullable|date|after:effected_at',
            'scope' => 'required|integer',
            'is_shared' => 'sometimes|nullable|in:0,1',
            'discount_type' => 'nullable|in:0,1,2,3',
            'share_total_count' => 'required_if:is_shared,1|nullable|integer|gt:0|max:99999',
            'share_each_count' => 'required_if:is_shared,1||nullable|integer|gt:0|max:999',
            'share_begin_at' => 'required_if:is_shared,1|date',
            'share_end_at' => 'required_if:is_shared,1|after:share_begin_at|date',
            'ignore_launch_count' => 'required_if:is_shared,1|in:0,1',
            'usable_line_ids' => 'array|required_if:scope,1',
            'usable_line_ids.*' => 'required_if:scope,1|integer',
            'weight' => 'sometimes|nullable|numeric',
            'min_weight' => 'sometimes|nullable|numeric',
            'max_weight' => 'sometimes|nullable|numeric',
            'remark' => 'sometimes|nullable|string|max:500',
            'ceiling' => 'sometimes|nullable|numeric|gt:0',
            'discount_method' => 'sometimes|nullable|integer|in:0,1',
            'usable_country_ids' => 'array|required_if:scope,2',
            'usable_country_ids.*' => 'required_if:scope,2|integer',
        ];
    }

    private function welfareRules()
    {
        return [
            'new_cus_send' => 'required|boolean',
            'invitor_send' => 'required|boolean',
            'invited_send' => 'required|boolean',
            'name' => 'required|string|max:150',
            'amount' => 'required|numeric|gt:0',
            'threshold' => 'required|numeric|gte:0',
            'day' => 'required|integer|gt:0',
        ];
    }
}
