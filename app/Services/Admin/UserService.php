<?php

/**
 * @Author: h9471
 * @Created: 2019/9/10 11:40
 */

namespace App\Services\Admin;

use App\Models\Order;
use App\Models\User;
use App\Models\UserBalance;
use App\Models\UserMember;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class UserService extends BaseService
{
    protected $filterRules = [
        'user_group_id' => ['=', 'user_group_id'],
        'created_at' => ['between',['begin_date','end_date']],
        'last_login_at' => ['between',['last_begin_date','last_end_date']],
        'source' => ['=', 'user_source']
    ];

    protected $orderBy = ['id' => 'desc'];

    public function __construct(User $user)
    {
        $this->request = request();
        $this->formData = $this->request->all();
        $this->model = $user;
        $this->query = $user->newQuery();
        $this->setFilterRules();
    }

    public function userSourceList()
    {
        return $this->formatList(User::$userSource);
    }

    public function indexInit()
    {
        $minBalance = UserBalance::query()->min('balance');
        $maxBalance = UserBalance::query()->max('balance');

        $minPoint = UserMember::query()->min('point');
        $maxPoint = UserMember::query()->max('point');

        $minOrderCount = Order::query()
            ->selectRaw('COUNT(*) AS nc_count,user_id')
            ->groupBy('user_id')->get()->min('nc_count');

        $maxOrderCount = Order::query()
            ->selectRaw('COUNT(*) AS nc_count,user_id')
            ->groupBy('user_id')->get()->max('nc_count');

        return [
            'min_balance' => $minBalance,
            'max_balance' => $maxBalance,
            'min_point' => $minPoint,
            'max_point' => $maxPoint,
            'min_order_count' => $minOrderCount,
            'max_order_count' => $maxOrderCount
        ];
    }

    /**
     * 列表
     * @return mixed
     */
    public function index()
    {
        $this->query->with(['group', 'invitor', 'inviteUser', 'balance', 'member', 'member.level', 'customer', 'sale', 'userMember', 'tags', 'channel']);

        $this->query->withCount(['orders']);

        $this->customFilter();

        $this->order();

        return parent::index();
    }

    public function show($id)
    {
        if ($this->formData['with_address'] ?? null ) {
            return $this->model::query()
                ->with('addresses')
                ->findOrFail($id);
        }

        return parent::show($id);
    }

    /**
     * 邀请列表
     * @param  int  $userId
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function indexOfInvitation(int $userId)
    {
        $this->query->where('invite_id', $userId);

        return parent::index();
    }

    /**
     * @param array $data
     * @return int
     * @throws \Throwable
     */
    public function makeTags($data)
    {
        validator($data, $this->makeTagsRules())->validate();

        throw_if(
            !UserTag::isValid($data['tag_ids'] ?? []),
            new AccidentException('部分选择的标签错误,请重新操作')
        );

        throw_if(
            !User::isValid($data['user_ids']),
            new AccidentException('部分选择的用户错误,请重新操作')
        );

        $users = User::query()
            ->whereKey($data['user_ids'])
            ->get();

        return DB::transaction(function () use ($users, $data) {
            $users->each(function ($user) use ($data) {
                if (empty($data['tag_ids'] ?? [])) {
                    $user->tags()->detach();
                } else {
                    if ($data['add_mode'] ?? 0) {
                        $user->tags()->sync($data['tag_ids'], false);
                    } else {
                        $user->tags()->sync($data['tag_ids']);
                    }
                }
            });

            return true;
        });
    }

    public function wechatAuth($data)
    {
        validator($data, $this->wechatAuthRules())->validate();

        $userIds = User::query()->select(['id','wechat_auth'])
            ->whereKey($data['ids'])
            ->where('wechat_auth', 0)
            ->get()
            ->modelKeys();

        User::query()->whereKey($userIds)->update([
            'wechat_auth' => 1
        ]);

        $this->createByWechatAuth($userIds);
    }

    /**
     * 更新用户组信息
     * @param int $id
     * @param int $groupId
     * @return bool
     */
    public function updateGroup(int $id, int $groupId): bool
    {
        $group = UserGroup::findOrFail($groupId);

        $res = $this->model::where('id', $id)
            ->update(
                [
                    'id' => $id,
                    'user_group_id' => $group->id,
                ]
            ) !== false;

        if ($res) {
            event(UserUpdated::class);
        }

        return $res;
    }

    /**
     * 更新用户UID
     *
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function updateUid(int $id, array $data): bool
    {
        validator($data, [
            'uid' => 'required|string'
        ])->validate();

        DB::transaction(function () use ($id, $data) {
            if ($exist = User::query()
                ->lockForUpdate()
                ->where('uid', $data['uid'])
                ->first()
            ) {
                throw new AccidentException('该被已被用户ID :exist占用', replace: ['exist' => $exist->id]);
            }

            $user = User::query()->lockForUpdate()->findOrFail($id);

            $user->update(['uid' => $data['uid']]);
            // 更新映射
            UserUidRuleService::setUidMapper($user->company_id, [$data['uid'] => $user->id]);

            Cache::put('UID_UPDATE_TIME_'.$user->company_id, now()->toDateTimeString());

            return true;
        });

        return true;
    }

    /**
     * 更新用户组信息
     * @param int $id
     * @param int $groupId
     * @return bool
     */
    public function batchUpdateGroup($data): bool
    {
        $data = validator($data,[
            'ids' => 'required|array',
            'ids.*' => 'required|int',
            'group_id' => 'required|int'
        ])->validated();

        $group = UserGroup::findOrFail($data['group_id']);

        $res = $this->model::query()->whereKey($data['ids'])
                ->update(
                    [
                        'user_group_id' => $group->id,
                    ]
                ) !== false;

        if ($res) {
            event(UserUpdated::class);
        }

        return $res;
    }


    /**
     * 根据ID搜索用户
     * @param  string  $id
     * @param  int  $limit
     * @return Collection
     */
    public function searchUserById(string $id, int $limit = 15): Collection
    {
        return User::query()
            ->with('member.level')
            ->where(function ($query) use ($id) {
                $query->where('id', 'like', "%{$id}%")
                    ->orWhere('name', 'like', "%{$id}%");

                if (defined('UID_ENABLED')) {
                    $query->orWhere('uid', 'like', "%{$id}%");
                }
            })
            ->select(['id', 'uid', 'name'])
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /**
     * 根据ID搜索用户
     * @param  string  $id
     * @param  int  $limit
     * @return Collection
     */
    public function searchAgentById(string $id, int $limit = 15): Collection
    {
        return Agent::query()
            ->where('agent_id', 'like', "%{$id}%")
            ->selectRaw('agent_id as id, agent_name as name')
            ->orderBy('id')
            ->limit($limit)
            ->get()->each->setAppends([]);
    }

    /**
     * 按用户余额排序
     */
    protected function order()
    {
        if ($this->request->get('order') !== null) {
            $order = $this->request->input('order');

            if ($order === 'balance') {
                $this->query->selectRaw('jiyun_users.*, b.balance')
                    ->join('jiyun_user_balance As b', 'jiyun_users.id', '=', 'b.user_id')
                    ->orderByDesc('b.balance');
            }
            if ($order === 'consume_amount') {
                $this->query->orderByDesc('consume_amount');
            }
        } elseif (is_numeric($this->request->get('keyword'))) {
            $this->orderBy = ['id' => 'asc'];
        } else {
            $this->orderBy = ['id' => 'desc'];
        }
    }

    /**
     * @param bool $isTemplate
     * @return string
     */
    public function userExport(bool $isTemplate = false)
    {
        $fileName = 'Users_' . Carbon::now()->format('Ymd') . '_' . Str::random(6);

        $filePath = "/excel/users/$fileName.xlsx";

        Excel::store(new UserExport($this->getUserExportDataWithFilter(), $isTemplate), admin_path($filePath), 'cos');

        return secure_asset(Storage::disk('admin_public')->url($filePath));
    }

    public function user2GroupExport()
    {
        $fileName = 'Users2Group_' . Carbon::now()->format('Ymd') . '_' . Str::random(6);

        $filePath = "/excel/users/{$fileName}.xlsx";

        Excel::store(new User2GroupExport($this->getUserGroupExportData()), admin_path($filePath), 'cos');

        return secure_asset(Storage::disk('admin_public')->url($filePath));
    }

    public function user2TagTemplate()
    {
        $fileName = 'Users2tag_' . Carbon::now()->format('Ymd') . '_' . Str::random(6);

        $filePath = "/excel/users/{$fileName}.xlsx";

        Excel::store(new User2TagExport($this->getUserTagExportData()), admin_path($filePath), 'cos');

        return secure_asset(Storage::disk('admin_public')->url($filePath));
    }

    /**
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function changePassword(int $id, array $data)
    {
        $data = validator($data, $this->passwordRules())->validate();

        /** @var User $user */
        $user = User::query()->findOrFail($id);

        return $user->update(['password' => bcrypt($data['confirm_password'])]);
    }

    /**
     * @param array $ids
     * @return mixed
     */
    public function destroy(array $ids)
    {
        $users = User::with(['balance'])
            ->withCount(['packages', 'orders'])
            ->whereKey($ids)
            ->get();

        return DB::transaction(function () use ($users) {
            /** @var User $user */
            foreach ($users as $user) {
                if ($user->balance->balance || $user->packages_count || $user->agent|| $user->orders_count) {
                    throw new AccidentException("用户:$user->id 存在交易数据，不支持删除");
                }
            }

            UserBalance::query()->whereIn('user_id', $users->modelKeys())->delete();
            UserCoupon::query()->whereIn('user_id', $users->modelKeys())->delete();
            User::query()->whereKey($users->modelKeys())->delete();

            return true;
        });
    }

    protected static function getUserExportData()
    {
        return User::with(['invitor','sale','customer','group','tags'])->get();
    }

    protected function getUserExportDataWithFilter()
    {
        $this->query->with(['group:id,name_cn', 'invitor:id,name', 'inviteUser:id,name', 'balance:id,user_id,balance',
            'member.level:id,name', 'customer', 'sale:id,name', 'userMember:id,user_id,point,growth_value',
            'tags:id,name', 'channel:id,channel_name'
        ])->with('packages', function ($query) {
            $query->whereNotNull('in_storage_at')->select(['id', 'in_storage_at', 'user_id']);
        })->with('orders', function ($query) {
            $query->whereNotNull('paid_at')->select(['id', 'paid_at', 'user_id']);
        });

        $this->query->withCount(['orders']);

        $this->customFilter();

        $this->setFilter()->setOrderBy();

        return $this->all();
    }

    protected function getUserTagExportData()
    {
        $this->query->with(['tags:id,name']);

        $this->customFilter();

        $this->setFilter()->setOrderBy();

        return $this->all();
    }

    protected function getUserGroupExportData()
    {
        $this->query->with(['group:id,name_cn']);

        $this->customFilter();

        $this->setFilter()->setOrderBy();

        return $this->all();
    }

    /**
     * @param int $id
     * @return array|mixed
     */
    public function profile(int $id)
    {
        /** @var User $user */
        $user = User::query()->findOrFail($id);

        return $user->profile ?: [];
    }

    public function store($data)
    {
        $payload = validator($data, $this->createRules())->validate();

        $user = User::query()
            ->when($payload['email'] ?? null, function ($query) use ($payload) {
                $query->where('email', $payload['email']);
            })
            ->when($payload['phone'] ?? null, function ($query) use ($payload) {
                if ($payload['email'] ?? null) {
                    $query->orWhere('phone', $payload['phone']);
                } else {
                    $query->where('phone', $payload['phone']);
                }
            })
            ->first();

        throw_if($user != null, new AccidentException('用户已存在'));

        $userPayload = [
                'timezone' => '0086',
                'phone' => $payload['phone'] ?? '',
                'email' => $payload['email'] ?? '',
                'name' => $payload['name'] ?? '',
                'user_group_id' => UserGroup::first()->id,
                'uid' => SerialNumber::generate(SerialNumber::TYPE_USER),
                'last_login_at' => now(),
                'source' => User::USER_SOURCE_ADMIN,
                'company_id' => self::getCompanyId(),
            ] + (($payload['password'] ?? null) ? ['password' => Hash::make($payload['password'])] : []);

        $user = User::create($userPayload);

        //同步标签
        !empty($payload['tag_ids']) && $this->makeTags(['tag_ids' => $payload['tag_ids'], 'user_ids' => [$user->id]]);

        event(new AfterUserRegistered($user));
        event(new UserUpdated);

        return $user;
    }

    /**
     * @param  int  $id
     * @param  array  $data
     * @return bool
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(int $id, array $data)
    {
        $data = validator($data, $this->rules())->validate();

        /** @var User $user */
        $user = User::query()->findOrFail($id);

        DB::transaction(function () use ($user, $data) {
            $user->update(['remark_name' => $data['remark_name'] ?? '']);

            $user->logs()->create(
                [
                    'log' => sprintf(
                        '%s 修改备注用户名为：%s',
                        auth()->user()->username,
                        $data['remark_name'] ?? ''
                    ),
                ]
            );

            if ($data['invitor_id'] ?? false) {
                // 邀请人是代理才去处理
                if (Agent::query()->where('agent_id', $data['invitor_id'])->count()) {
                    if ((int) $data['invitor_id'] === $user->getKey()) {
                        throw new AccidentException('不能设置邀请人为客户自己');
                    }

                    $originInviteId = $user->invite_id;
                    $user->update(
                        [
                            'invite_id' => $data['invitor_id'],
                            'is_agent_invite' => 1,
                        ]
                    );

                    $user->logs()->create(
                        [
                            'log' => sprintf(
                                '%s 修改用户邀请代理为：%s,原代理为：%s',
                                auth()->user()->username,
                                $data['invitor_id'],
                                $originInviteId ?? ''
                            ),
                        ]
                    );
                } else {
                    info('设置邀请人非代理');
                }
            }
            // 分配客服
            if ($data['customer_id'] ?? 0) {
                $this->assignCustomer(['customer_id' => $data['customer_id'], 'user_ids' => [$user->getKey()]]);
            }
            // 分配销售
            if ($data['sale_id'] ?? 0) {
                $this->assignSale(['sale_id' => $data['sale_id'], 'user_ids' => [$user->getKey()]]);
            }

            if ($data['user_group_id'] ?? 0) {
                $user->update(['user_group_id' => $data['user_group_id']]);
            }

            $originProfile = $user->profile ?? [];

            $originProfile['country'] = is_numeric($data['country_id'] ?? null) ? Country::query()->find($data['country_id'])->toArray() : null;
            $originProfile['receiver_name'] = $data['receiver_name'] ?? '';
            $originProfile['city'] = $data['city'] ?? '';
            $originProfile['phone'] = $data['phone'] ?? '';
            $originProfile['street'] = $data['street'] ?? '';
            $originProfile['id_card'] = $data['id_card'] ?? '';
            $originProfile['door_no'] = $data['door_no'] ?? '';
            $originProfile['wechat_id'] = $data['wechat_id'] ?? '';
            $originProfile['postcode'] = $data['postcode'] ?? '';
            $originProfile['user_id'] = $user->getKey();
            $originProfile['timezone'] = $originProfile['country']['timezone'] ?? '';
            $originProfile['country_name'] = $originProfile['country']['cn_name'] ?? '';
            $originProfile['remark'] = $data['remark'] ?? '';
            $originProfile['email'] = $data['email'] ?? '';
            $originProfile['address'] = $data['address'] ?? '';

            $user->update(['profile' => $originProfile]);

            //同步标签
            !empty($data['tag_ids']) && $this->makeTags(['tag_ids' => $data['tag_ids'], 'user_ids' => [$user->id]]);
        });

        return true;
    }

    /**
     * @param  int  $id
     * @return \Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection
     */
    public function userLogs(int $id)
    {
        return UserLog::query()
            ->where('user_id', $id)
            ->orderByDesc('id')
            ->get();
    }

    /**
     * 更新用户邮箱和手机号
     *
     * @param int $id
     * @param array $data
     * @return bool
     * @throws AccidentException
     */
    public function updateBindInfo(int $id, array $data): bool
    {
        $user = User::query()->findOrFail($id);

        if (!empty($data['email'])) {
            if (User::query()->whereKeyNot($user->id)->where('email', $data['email'])->exists()) {
                throw new AccidentException('该邮箱已被其他用户绑定');
            }

            if ($data['email'] == $user->email) {
                throw new AccidentException('绑定邮箱不能与原邮箱相同');
            }

            $user->logs()->create(
                [
                    'log' => sprintf(
                        '%s 修改用户邮箱为：%s，原邮箱为：%s',
                        auth()->user()->username,
                        $data['email'],
                        $user->email
                    ),
                ]
            );

            $user->update(['email' => $data['email']]);
        }

        if (!empty($data['phone']) && !empty($data['timezone'])) {
            if (User::query()->whereKeyNot($user->id)->where('phone', $data['phone'])->exists()) {
                throw new AccidentException('该手机号已被其他用户绑定');
            }

            if ($data['phone'] == $user->phone && $data['timezone'] == $user->timezone) {
                throw new AccidentException('绑定手机号不能与原手机号相同');
            }

            $user->logs()->create(
                [
                    'log' => sprintf(
                        '%s 修改用户手机号为：%s，原手机号为：%s',
                        auth()->user()->username,
                        $data['timezone'].$data['phone'],
                        $user->timezone.$user->phone
                    ),
                ]
            );

            $user->update(['phone' => $data['phone'], 'timezone' => $data['timezone']]);
        }

        return true;
    }

    /**
     * 合并客户
     *
     * @param int $userId
     * @param int $targetId
     * @return bool|mixed|null
     * @throws Exception
     */
    // public function merge(int $userId, int $targetId)
    // {
    //     if ($userId == $targetId) {
    //         throw new AccidentException('不能合并相同的客户');
    //     }

    //     return DB::transaction(function () use ($userId, $targetId) {
    //         /** @var User $user */
    //         $user = User::query()->findOrFail($userId);
    //         /** @var User $target */
    //         $target = User::query()->findOrFail($targetId);

    //         if ($user->forbid_login || $target->forbid_login) {
    //             throw new AccidentException('不能合并禁止登录用户');
    //         }

    //         $balance = UserBalance::query()->where('user_id',$target['id'])->first();
    //         if ($balance) {
    //             $user->balance()->update([
    //                 'balance' => DB::raw("balance + $balance->balance"),
    //                 'history_income' => DB::raw("history_income + $balance->balance"),
    //             ]);
    //             //插入一条余额记录
    //             BalanceRecord::query()->create([
    //                 'user_id' => $userId,
    //                 'type' => BalanceRecord::TYPE_INCOME,
    //                 'resource' => '客户合并',
    //                 'amount' => $balance->balance,
    //             ]);
    //         }

    //         //处理成长值和积分
    //         $this->mergeIncome($user, $target);
    //         $target->userMember()->delete();

    //         $target->balance()->delete();
    //         BalanceRecord::query()->where('user_id', $targetId)->delete();
    //         //消费总额合并
    //         $user->update(['consume_amount' => $user->consume_amount + $target->consume_amount]);
    //         //订单
    //         Order::query()
    //             ->where('user_id', $targetId)
    //             ->update(['user_id' => $userId]);
    //         //包裹
    //         Package::query()
    //             ->where('user_id', $targetId)
    //             ->update(['user_id' => $userId]);
    //         //收件地址
    //         UserAddress::query()
    //             ->where('user_id', $targetId)
    //             ->update(['user_id' => $userId]);
    //         //用户邀请关系
    //         User::query()
    //             ->where('invite_id', $targetId)
    //             ->update(['invite_id' => $userId]);
    //         /** @var Agent $targetAgent */
    //         $targetAgent = Agent::query()
    //             ->where('agent_id', $targetId)
    //             ->first();

    //         if ($targetAgent) {
    //             $agent = $user->agent;
    //             //更新代理佣金归属
    //             $targetAgent->commissions()->update(['agent_id' => $userId]);
    //             //更新代理佣金提现申请
    //             AgentCommissionWithdrawRecord::query()
    //                 ->where('user_id', $targetId)
    //                 ->update(['user_id' => $userId]);
    //             //如果已经是代理了 直接删除原来的 因为佣金和邀请关系已经转移了
    //             if ($agent) {
    //                 $targetAgent->delete();
    //             } else {
    //                 // 如果原来不是代理 那就在合并的客户的代理信息上进行修改
    //                 // 修改代理ID 重新重新生成邀请码图片
    //                 $path = 'agents/' . $userId . '.jpg';
    //                 try {
    //                     $imageData = (new WechatServices($user->company_id))->generateAppCode($userId);

    //                     throw_unless(
    //                         $imageData instanceof StreamResponse,
    //                         new AccidentException('代理小程序码生成失败，请稍后重试')
    //                     );

    //                     $image = Image::make($imageData->getBody()->getContents());
    //                     Storage::disk('cos')->put(admin_path($path), $imageData);
    //                 } catch (\Exception $exception) {
    //                     $image = null;
    //                 }

    //                 $targetAgent->update([
    //                     'agent_id' => $userId,
    //                     'agent_name' => $user->name,
    //                     'remark' => sprintf('从代理 %s迁移合并', $targetId),
    //                     'qr_code' => $image ? $image->encode('data-url') : '',
    //                     'qr_code_url' => $image ? str_replace(
    //                         config('app.url'),
    //                         '',
    //                         Storage::disk('admin_public')->url($path)
    //                     ) : '',
    //                 ]);
    //             }
    //         }
    //         //流水
    //         TransactionRecord::query()
    //             ->where('user_id', $targetId)
    //             ->update(['user_id' => $userId]);
    //         //充值记录
    //         BalanceRechargeRecord::query()
    //             ->where('user_id', $targetId)
    //             ->update(['user_id' => $userId]);
    //         // 迁移原来的认证信息
    //         foreach (['unionid', 'open_id', 'oa_open_id', 'phone', 'email'] as $field) {
    //             // 如果当前用户没有该字段，而被合并的用户有该字段，那么就迁移过来
    //             if ($target->$field && !$user->$field) {
    //                 $user->$field = $target->$field;
    //             }
    //         }

    //         $target->update([
    //             'phone' => null,
    //             'email' => null,
    //             'open_id' => null,
    //             'unionid' => null,
    //             'oa_open_id' => null,
    //             'forbid_login' => 1,
    //             'merged' => 1,
    //             'consume_amount' => 0,
    //             'merge_id' => $userId,
    //         ]);
    //         // 更新迁移信息
    //         $user->save();

    //         $user->logs()->create(['log' => sprintf('用户 %s 被合并到当前用户', $targetId)]);
    //         $target->logs()->create(['log' => sprintf('用户被合并到 %s', $userId)]);

    //         Redis::client()->sAdd('forbid_login_users', $target->getKey());

    //         return true;
    //     });
    // }

    protected function mergeIncome(User $user, User $target)
    {
        $targetMember = UserMember::query()->where('user_id', $target->id)->first();
        if(!$targetMember) return true;

        throw_if(
            $targetMember->lock_point > 0,
            new AccidentException('目标客户有订单已经锁定积分，请先支付订单')
        );

        //处理成长值
        if($targetMember->growth_value > 0){
            $rule = GrowthValueIncrease::query()
                ->where('income_outlay_rule_code', IncomeOutlayRule::RULE_CODE_GROWTH_INCREASE)
                ->first();
            if (empty($rule->valid_time)) {
                $endTime = null;
            } elseif ($rule->valid_time > 0) {
                $endTime = Carbon::parse(date('Y-m-d'))->addMonths($rule->valid_time)->format('Y-m-d');
            } else {
                $endTime = Carbon::parse(date('Y-m-d'))->addDays(abs($rule->valid_time))->format('Y-m-d');
            }
            IncomeOutlayRecord::query()->create([
                'user_id' => $user->id,
                'income_outlay_rule_id' => 0,
                'income_outlay_rule_code' => IncomeOutlayRule::RULE_CODE_RECHARGE,
                'serial_no' => SerialNo::genSerialNo(SerialNo::INCOME_OUT_RECORD),
                'resource_type' => IncomeOutlayRule::RESOURCE_TYPE_GROWTH,
                'type' => IncomeOutlayRule::TYPE_INCOME,
                'amount' => 0,
                'value' => $targetMember->growth_value,
                'enable_value' => $targetMember->growth_value,
                'valid_time' => $rule->valid_time ?? null,
                'start_time' => date('Y-m-d'),
                'end_time' => $endTime,
                'operator' => Admin::query()->where('id', auth()->user()->id)->first()?->name ?? '',
                'remark' => '客户合并' . $target->id
            ]);
            UserMember::query()->where('user_id', $user->id)->increment('growth_value', $targetMember->growth_value);
        }

        //处理积分
        if($targetMember->point > 0){
            $rule = PointsIncrease::query()
                ->where('income_outlay_rule_code', IncomeOutlayRule::RULE_CODE_POINT_DECREASE)
                ->first();
            if (empty($rule->valid_time)) {
                $endTime = null;
            } elseif ($rule->valid_time > 0) {
                $endTime = Carbon::parse(date('Y-m-d'))->addMonths($rule->valid_time)->format('Y-m-d');
            } else {
                $endTime = Carbon::parse(date('Y-m-d'))->addDays(abs($rule->valid_time))->format('Y-m-d');
            }
            IncomeOutlayRecord::query()->create([
                'user_id' => $user->id,
                'income_outlay_rule_id' => 0,
                'income_outlay_rule_code' => IncomeOutlayRule::RULE_CODE_RECHARGE,
                'serial_no' => SerialNo::genSerialNo(SerialNo::INCOME_OUT_RECORD),
                'resource_type' => IncomeOutlayRule::RESOURCE_TYPE_POINT,
                'type' => IncomeOutlayRule::TYPE_INCOME,
                'amount' => 0,
                'value' => $targetMember->point,
                'enable_value' => $targetMember->point,
                'valid_time' => $rule->valid_time ?? null,
                'start_time' => date('Y-m-d'),
                'end_time' => $endTime,
                'operator' => Admin::query()->where('id', auth()->user()->id)->first()?->name ?? '',
                'remark' => '客户合并' . $target->id
            ]);
            UserMember::query()->where('user_id', $user->id)->increment('point', $targetMember->point);
        }

        return true;
    }

    /**
     * 允许登陆
     * @param  array  $ids
     * @return bool
     * @throws AccidentException|\RedisException
     */
    public function allowLogin(array $ids): bool
    {
        $this->initAdminProtect($ids);

        User::query()->whereKey($ids)
            ->get()
            ->each(function ($user) {
                if ($user['merged'] && $user['forbid_login']) {
                    throw new AccidentException('用户已被合并，不能开启登录');
                }
            });

        Redis::client()->sRem('forbid_login_users', ...$ids);

        return $this->model::whereIn('id', $ids)
                ->update([
                    'forbid_login' => 0,
                ]) !== false;
    }

    public function assignCustomer($data)
    {
        $data = validator($data, [
                'customer_id' => 'required|int',
                'user_ids' => 'required|array'
            ]
        )->validate();

        //验证
        Admin::isValid([$data['customer_id']]);
        User::isValid($data['user_ids']);
        return User::query()->whereIn('id', $data['user_ids'])->update(['customer_id'=>$data['customer_id']]);
    }

    public function assignSale($data)
    {
        $data = validator($data, [
                'sale_id' => 'required|int',
                'user_ids' => 'required|array'
            ]
        )->validate();

        //验证
        Admin::isValid([$data['sale_id']]);
        User::isValid($data['user_ids']);

        return User::query()->whereIn('id', $data['user_ids'])->update(['sale_id'=>$data['sale_id']]);
    }

    public function templateAssign(UploadedFile $file)
    {
        $data = $this->parseExcel($file);

        try {
            $data = collect($data)->map(function ($user){
                return [
                    'user_id' => $user[0],
                    'customer_name' => $user[5],
                    'sale_name' => $user[6]
                ];
            });

            $customerNames = array_filter(array_unique(array_column($data->toArray(), 'customer_name')));
            $customers = Admin::query()->whereIn('name',$customerNames)->get(['id','name']);
            throw_if(
                $customers->count() !== count($customerNames),
                new AccidentException('请检查客服数据是否正确')
            );
            $customers = $customers->keyBy('name')->toArray();

            $saleNames = array_filter(array_unique(array_column($data->toArray(), 'sale_name')));
            $sales = Admin::query()->whereIn('name', $saleNames)->get(['id','name']);
            throw_if(
                $sales->count() !== count($saleNames),
                new AccidentException('请检查销售数据是否正确')
            );
            $sales = $sales->keyBy('name')->toArray();

            return DB::transaction(function () use ($data,$customers,$sales){

                $dataCustomers = $data->filter(fn($c) => !empty($c['customer_name']))->groupBy('customer_name')->toArray();
                foreach ($dataCustomers as $username => $customerUsers){
                    User::query()
                        ->whereIn('id',array_column($customerUsers,'user_id'))
                        ->update(['customer_id'=>$customers[$username]['id']]);
                }

                $dataSales = $data->filter(fn($c) => !empty($c['sale_name']))->groupBy('sale_name')->toArray();
                foreach ($dataSales as $username => $saleUsers){
                    User::query()
                        ->whereIn('id', array_column($saleUsers,'user_id'))
                        ->update(['sale_id'=>$sales[$username]['id']]);
                }
                return true;

            });


        } catch (AccidentException $e) {
            throw $e;
        } catch (\Throwable $e) {
            info(__METHOD__.'导入失败', ['err' => $e->getMessage()]);
            throw new AccidentException('导入失败，请检查模板数据是否正确');
        }
    }


    public function templateUpdateGroup(UploadedFile $file)
    {
        $data = $this->parseExcel($file);

        try {
            $data = collect($data)->map(function ($user){
                return [
                    'user_id' => $user[0],
                    'group_name' => $user[5],
                ];
            });

            $groupNames = array_filter(array_unique(array_column($data->toArray(), 'group_name')));
            $groups = UserGroup::query()->whereIn('name_cn', $groupNames)->get(['id','name_cn', 'name_en']);

            info('用户组导入', ['groupNames' => $groupNames, 'groups' => $groups]);

            throw_if(
                $groups->count() !== count($groupNames),
                new AccidentException('请检查客户组数据是否正确')
            );
            $groups = $groups->keyBy('name_cn')->toArray();

            return DB::transaction(function () use ($data, $groups){

                $dataUsers = $data->filter(fn($c) => !empty($c['group_name']))->groupBy('group_name')->toArray();
                foreach ($dataUsers as $groupName => $users){
                    User::query()
                        ->whereIn('id', array_column($users,'user_id'))
                        ->update(['user_group_id' => $groups[$groupName]['id']]);
                }
                return true;

            });
        } catch (AccidentException $e) {
            throw $e;
        } catch (\Throwable $e) {
            info(__METHOD__.'导入失败', ['err' => $e->getMessage()]);
            throw new AccidentException('导入失败，请检查模板数据是否正确');
        }
    }

    public function templateUpdateTag(UploadedFile $file)
    {
        $data = $this->parseExcel($file);

//        throw_if(
//            $data->count() > 500,
//            new AccidentException('最大不能超过500行')
//        );

        try {
            $data = collect($data)->map(function ($user){
                return [
                    'user_id' => $user[0],
                    'tag_names' => !empty($user[5]) ? explode(',', $user[5]) : [],
                ];
            });

            $tagNames = collect(array_column($data->toArray(),'tag_names'))->flatten()->unique()->values()->all();
            $tagNames = array_filter($tagNames);
            $tags = UserTag::query()->whereIn('name', $tagNames)->get(['id','name']);
            info('tagNames debug', [$tagNames, $tags->toArray()]);

            throw_if(
                $tags->count() !== count($tagNames),
                new AccidentException('请检查标签组数据是否正确')
            );
            $tags = $tags->keyBy('name')->toArray();

            return DB::transaction(function () use ($data, $tags){

                $data->filter(fn($c) => !empty($c['tag_names']))->chunk(100)->each(function ($dataUsers) use ($tags){

                    $dataUsers = $dataUsers->keyBy('user_id')->toArray();
                    $users = User::query()->whereKey(array_keys($dataUsers))->get();

                    $users->each(function ($user) use ($dataUsers, $tags){
                        /**@var User $user*/
                        $userTagNames = $dataUsers[$user->id]['tag_names'] ?? [];
                        if(!$userTagNames) return true;

                        $userTagIds = array_column(array_values(Arr::only($tags,$userTagNames)), 'id');
                        $user->tags()->sync($userTagIds);
                        return true;
                    });
                });

                return true;
            });
        } catch (AccidentException $e) {
            throw $e;
        } catch (\Throwable $e) {
            info(__METHOD__.'导入失败', ['err' => $e->getMessage()]);
            throw new AccidentException('导入失败，请检查模板数据是否正确');
        }
    }



    /**
     * @param $file
     * @return \Illuminate\Support\Collection|\Yansongda\Supports\Collection
     * @throws AccidentException
     */
    protected function parseExcel($file)
    {
        try {
            $config = ['path' => $file->getPath()];

            $excel = (new \Vtiful\Kernel\Excel($config))
                ->openFile($file->getFilename())
                ->openSheet();

            $items = collect([]);
            while (($row = $excel->nextRow()) !== null) {
                if (empty($row[0])) {
                    break;
                }
                $items->push(collect($row));
            }
            // 删除第一行说明性数据
            unset($items[0]);

            return $items;
        } catch (\Throwable $throwable) {
            throw new AccidentException('模板数据异常，请检查模板数据是否填写完整与正确');
        }
    }

    /**
     * @return void
     */
    public function customFilter()
    {
        //用户标签
        $this->query->when($this->formData['tag_id'] ?? null,function (Builder $query){
            $query->whereHas('tags',function ($query){
                $query->where('id', $this->formData['tag_id']);
            });
        });

        //会员等级
        $this->query->when($this->formData['level_id'] ?? null,function (Builder $query){
            $query->whereHas('member',function ($query){
                $query->where('level_id',$this->formData['level_id']);
            });
        });

        //邀请人
        $this->query->when($this->formData['invite_id'] ?? null,function (Builder $query){
            $inviteId = ($this->formData['invite_id'] == -1) ? null : $this->formData['invite_id'];
            $query->where('invite_id', $inviteId);
        });

        //客服ID
        $this->query->when($this->formData['customer_id'] ?? null,function (Builder $query){
            $customerId = ($this->formData['customer_id'] == -1) ? null : $this->formData['customer_id'];
            $query->where('customer_id', $customerId);
        });

        //销售ID
        $this->query->when($this->formData['sale_id'] ?? null,function (Builder $query){
            $saleId = ($this->formData['sale_id'] == -1) ? null : $this->formData['sale_id'];
            $query->where('sale_id', $saleId);
        });

        //余额范围查询
        $minBalance = $this->formData['min_balance'] ?? -1;
        $maxBalance = $this->formData['max_balance'] ?? -1;
        $this->query->when(($minBalance >= 0) && ($maxBalance >= 0),function (Builder $query) use ($minBalance,$maxBalance){
            $query->whereHas('balance',function ($query) use ($minBalance,$maxBalance){
                $query->whereBetween('balance',[$minBalance,$maxBalance]);
            });
        });

        //积分范围查询
        $minPoint = $this->formData['min_point'] ?? -1;
        $maxPoint = $this->formData['max_point'] ?? -1;
        $this->query->when(($minPoint >= 0) && ($maxPoint >= 0),function (Builder $query) use ($minPoint, $maxPoint){
            $query->whereHas('userMember',function ($query) use ($minPoint, $maxPoint){
                $query->whereBetween('point',[$minPoint, $maxPoint]);
            });
        });

        //订单数量范围查询
        $minOrderCount = $this->formData['min_order_count'] ?? -1;
        $maxOrderCount = $this->formData['max_order_count'] ?? -1;
        $this->query->when(($minOrderCount >= 0) && ($maxOrderCount >= 0),function (Builder $query) use ($minOrderCount,$maxOrderCount){
            $this->query->when(($minOrderCount >= 0) && ($maxOrderCount >= 0),function (Builder $query) use ($minOrderCount,$maxOrderCount){
                $query ->havingBetween('orders_count',[$minOrderCount, $maxOrderCount]);
            });
        });

        //渠道过滤
        $this->query->when($this->formData['channel_id'] ?? null,function (Builder $query){
            $query->where('invite_type', 'channels');
            $query->where('invite_id', $this->formData['channel_id']);
        });

        if (!empty($this->formData['keyword'])) {
            $keyword = $this->formData['keyword'];

            $this->query->where(function (Builder $query) use ($keyword) {
                $query->where('phone', 'like', "%$keyword%")
                    ->orWhere('name', 'like', "%$keyword%")
                    ->orWhere('email', 'like', "%$keyword%")
                    ->orWhere('remark_name', 'like', "$keyword%")
                    ->orWhere('jiyun_users.id', 'like', "%$keyword%");

                if (defined('UID_ENABLED')) {
                    $query->orWhere('uid', 'like', "%$keyword%");
                }
            });
        }

    }


    /**
     * @param UploadedFile $file
     * @return mixed
     * @throws AccidentException
     */
    public function import(UploadedFile $file)
    {
        try {
            $config = ['path' => $file->getPath()];

            $excel = (new \Vtiful\Kernel\Excel($config))
                ->openFile($file->getFilename())
                ->openSheet();

            $items = collect([]);
            while (($row = $excel->nextRow()) !== null) {
                if (empty($row[2])) {
                    break;
                }

                $row[3] = trim($row[3]);

                $items->push(collect($row));
            }

            return $this->batch($items->skip(1));
        } catch (AccidentException $exception) {
            throw $exception;
        } catch (\Throwable $throwable) {
            info('数据解析失败', [$throwable->getMessage(), $throwable->getTraceAsString()]);
            throw new AccidentException('模板数据异常，请检查模板数据是否填写完整');
        }
    }

    /**
     * 优质客户列表
     * @return array
     */
    public function highQualityUsers()
    {
        $this->query->with(['group', 'invitor', 'inviteUser', 'balance'])
            ->withSum('records', 'amount');

        if (isset($this->formData['day'])) {
            $day = $this->formData['day'];
            // $time = date('Y-m-d H:i:s', strtotime('-'.$day.'day'));
            $time = now()->subDays($day);
            $amount = $this->formData['amount'] * 100;

            $transactions = TransactionRecord::where('created_at', '>', $time)->where('type', 1)->get();
            $userSpending = $transactions->groupBy('user_id')->map(function ($transactions) {
                return $transactions->sum('amount');
            });
            $this->query->whereIn('id', $userSpending->filter(function ($totalSpent) use ($amount) {
                return $totalSpent > $amount;
            })->keys());

        }

        if (isset($this->formData['login_day'])) {
            // $time = date('Y-m-d H:i:s', strtotime('-'.$this->formData['login_day'].'day'));
            $time = now()->subDays($this->formData['login_day']);
            $this->query->where('last_login_at', '>', $time);
        }

        if (isset($this->formData['date_type'], $this->formData['last_begin_date'], $this->formData['last_end_date'])) {
            $this->query->whereBetween(
                $this->formData['date_type'],
                [
                    $this->formData['last_begin_date'],
                    $this->formData['last_end_date']
                ]
            );
        }

        if (isset($this->formData['agent_id'])) {
            $this->query->where('invite_id', $this->formData['agent_id']);
        }
        if (isset($this->formData['keyword'])) {
            $keyword = $this->formData['keyword'];
            $this->query->where(function ($query) use ($keyword) {
                $query->where('id', 'like', '%'.$keyword.'%')->orWhere('name', 'like', '%'.$keyword.'%');
            });
        }

        return $this->query->pageSize();
    }

    public function balanceExport($data)
    {
        $balanceTime = $data['balance_time'] ?? now();

        $fileName = 'Balance_' . Carbon::parse($balanceTime)->format('YmdHis') . '_' . Str::random(6) . '.xlsx';

        $data = $this->getBalanceData($balanceTime);

        /** @var ExcelExport $excelExport */
        $excelExport = ExcelExport::query()->create([
            'type' => ExcelExport::TYPE_USER_BALANCE,
            'name' => $fileName,
            'url' => '',
        ]);

        dispatch(new UserBalanceExport($excelExport, $data))->onQueue('export');

        return true;
    }

    private function getBalanceData($balanceTime)
    {
        $balances = [];
        User::query()
            ->select(['id','name'])
            ->chunkById(100, function ($users) use ($balanceTime, &$balances) {

                $records = BalanceRecord::query()
                    ->selectRaw('user_id,SUM( CASE WHEN type=1 THEN amount ELSE -amount END ) as balance')
                    ->whereIn('user_id', $users->modelKeys())
                    ->where('created_at', '<=' , $balanceTime)
                    ->groupBy('user_id')
                    ->get()
                    ->keyBy('user_id');

                foreach ($users as $user){
                    $balances[] = [
                        'id' => $user->id,
                        'name' => $user->name,
                        'balance' => bcdiv($records[$user->id]->balance ?? 0, '100', 2),
                    ];
                }
            });

        return $balances;
    }

    protected function batch(Collection $items)
    {

        DB::beginTransaction();

        try {
            $items->map(function ($item){
                return [
                    'uid' => $item[0],
                    'name' => $item[1],
                    'email' => $item[2],
                    'balance' => $item[3],
                    'user_group_id' => 9, //623
                    'created_at' => $item[4] ?: now()->toDateTimeString(),
                    'updated_at' => $item[5] ?: now()->toDateTimeString(),
                    'last_login_at' => $item[5] ?: now()->toDateTimeString(),
                    'company_id' => auth()->user()->company_id,
                ];
            })->chunk(300)->each(function ($chunkItems){

                $userData = $chunkItems->map(function ($item){
                    unset($item['balance']);
                    return $item;
                })->toArray();
                User::query()->insert($userData);

                $users = User::query()
                    ->select(['id', 'uid'])
                    ->whereIn('uid', Arr::pluck($chunkItems->toArray(), 'uid'))
                    ->get()->keyBy('uid');

                $rechargeRecords = $transactionRecords = $balanceRecords = $userBalances = [];
                $growthValueRecords = $pointRecords = $userMembers = [];
                $chunkItems->each(function ($item) use (
                    $users,
                    &$rechargeRecords, &$transactionRecords, &$balanceRecords, &$userBalances,
                    &$growthValueRecords, &$pointRecords, &$userMembers
                ){
                    $userId = $users[$item['uid']]->id;
                    list($rechargeRecord, $transactionRecord, $balanceRecord, $userBalance) = self::baseBalanceRecharge($userId, $item);
//                    if($rechargeRecord){
//                        $rechargeRecords[] = $rechargeRecord;
//                    }
//                    if($transactionRecord){
//                        $transactionRecords[] = $transactionRecord;
//                    }
//                    if($balanceRecord){
//                        $balanceRecords[] = $balanceRecord;
//                    }
                    $userBalances[] = $userBalance;
                    list($growthValueRecord, $pointRecord, $userMember) = self::baseInOutRecharge($userId, $item);
//                    if($growthValueRecord){
//                        $growthValueRecords[] = $growthValueRecord;
//                    }
//                    if($pointRecord){
//                        $pointRecords[] = $pointRecord;
//                    }
                    $userMembers[] = $userMember;
                });

//                $rechargeRecords && BalanceRechargeRecord::query()->insert($rechargeRecords);
//                $transactionRecords && TransactionRecord::query()->insert($transactionRecords);
//                $balanceRecords && BalanceRecord::query()->insert($balanceRecords);
                UserBalance::query()->insert($userBalances);
//
//                $growthValueRecords && IncomeOutlayRecord::query()->insert($growthValueRecords);
//                $pointRecords && IncomeOutlayRecord::query()->insert($pointRecords);
                UserMember::query()->insert($userMembers);

            });
        }catch (\Throwable $exception){
            DB::rollBack();
            info('user-import', ['exception' => $exception->getMessage()]);
            throw new AccidentException('数据错误' . $exception->getMessage());
        }

        DB::commit();
        return true;
    }

    public static function baseBalanceRecharge($userId, $item)
    {
        $companyId = auth()->user()->company_id;
        $now = now();

        $userBalance = [
            'user_id' => $userId,
            'balance' => $item['balance'] ? $item['balance'] * 100 : 0,
            'history_income' => $item['balance'] ? $item['balance'] * 100 : 0,
            'company_id' => $companyId,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if(empty($item['balance'])){
            return [[], [], [], $userBalance];
        }

        $record = [
            'user_id' => $userId,
            'payment_type_id' => 1,
            'confirm_amount' => $item['balance'] * 100,
            'status' => BalanceRechargeRecord::CHECK_PASS,
            'images' => json_encode([]),
            'serial_no' => SerialNo::genSerialNo(SerialNo::BALANCE_RECHARGE),
            'info' => json_encode([
                'transfer_account' => '001',
                'tran_amount' => $item['balance'] * 100,
                'remark' => '基础导入',
            ]),
            'company_id' => $companyId,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $transactionRecord = [
            'user_id' => $userId,
            'type' => TransactionRecord::RECHARGE,
            'amount' => $record['confirm_amount'],
            'order_sn' => '',
            'wechat_sn' => '',
            'serial_no' => $record['serial_no'],
            'mod4pay' => $record['payment_type_id'],
            'company_id' => $companyId,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        //插入一条余额记录
        $balanceRecord = [
            'user_id' => $userId,
            'type' => 1,
            'resource' => '银行卡支付',
            'amount' => $record['confirm_amount'],
            'company_id' => $companyId,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        return [$record, $transactionRecord, $balanceRecord, $userBalance];
    }

    public static function baseInOutRecharge($userId, $item)
    {
        $companyId = auth()->user()->company_id;
        $now = now();
        $date = date('Y-m-d');

        $userMember = [
            'user_id' => $userId,
            'growth_value' => $item['growth_value'] ?? 0,
            'point' => $item['point'] ?? 0,
            'company_id' => $companyId,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        return [[],[],$userMember];

        if(empty($item['growth_value'])){
            $growthValueRecord = [];
        }else{
            $growthValueRecord = [
                'user_id' => $userId,
                'income_outlay_rule_id' => 0,
                'income_outlay_rule_code' => IncomeOutlayRule::RULE_CODE_RECHARGE,
                'serial_no' => SerialNo::genSerialNo(SerialNo::INCOME_OUT_RECORD),
                'resource_type' => IncomeOutlayRule::RESOURCE_TYPE_GROWTH,
                'type' => IncomeOutlayRule::TYPE_INCOME,
                'amount' => 0,
                'value' => $item['growth_value'],
                'enable_value' => $item['growth_value'],
                'order_sn' => '',
                'valid_time' => 0,
                'start_time' => $date,
                'end_time' => null,
                'operator' => '基础导入',
                'remark' => '基础导入',
                'company_id' => $companyId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if(empty($item['point'])){
            $pointRecord = [];
        }else{
            $pointRecord = [
                'user_id' => $userId,
                'income_outlay_rule_id' => 0,
                'income_outlay_rule_code' => IncomeOutlayRule::RULE_CODE_RECHARGE,
                'serial_no' => SerialNo::genSerialNo(SerialNo::INCOME_OUT_RECORD),
                'resource_type' => IncomeOutlayRule::RESOURCE_TYPE_POINT,
                'type' => IncomeOutlayRule::TYPE_INCOME,
                'amount' => 0,
                'value' => $item['point'],
                'enable_value' => $item['point'],
                'order_sn' => '',
                'valid_time' => 0,
                'start_time' => $date,
                'end_time' => null,
                'operator' => '基础导入',
                'remark' => '基础导入',
                'company_id' => $companyId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return [$growthValueRecord, $pointRecord, $userMember];
    }


    protected function passwordRules()
    {
        return [
            'password' => 'required|string|between:8,20',
            'confirm_password' => 'required|same:password',
        ];
    }

    private function createRules()
    {
        return [
            'email' => 'nullable|email',
            'phone' => 'required_without:email',
            'name' => 'required',
            'password' => 'nullable',
            'tag_ids' => 'sometimes|nullable|array',
            'tag_ids.*' => 'sometimes|nullable|int'
        ];
    }

    private function rules()
    {
        return [
            'country_id' => 'sometimes|nullable|integer',
            'receiver_name' => 'sometimes|nullable',
            'city' => 'sometimes|nullable|string',
            'phone' => 'sometimes|nullable|string|max:30',
            'email' => 'sometimes|nullable|string|max:30',
            'street' => 'sometimes|nullable|string',
            'id_card' => 'sometimes|nullable|string',
            'door_no' => 'sometimes|nullable|string',
            'wechat_id' => 'sometimes|nullable|string',
            'postcode' => 'sometimes|nullable|string',
            'invitor_id' => 'sometimes|nullable',
            'remark_name' => 'sometimes|nullable',
            'remark' => 'sometimes|nullable',
            'customer_id' => 'sometimes|nullable',
            'sale_id' => 'sometimes|nullable',
            'user_group_id' => 'sometimes|nullable',
            'tag_ids' => 'sometimes|nullable|array',
            'tag_ids.*' => 'sometimes|nullable|int',
            'address' => 'sometimes|nullable|string',
        ];
    }

    private function makeTagsRules()
    {
        return [
            'tag_ids' => 'sometimes|nullable|array',
            'user_ids' => 'required|array',
            'user_ids.*' => 'required|int',
            'add_mode' => 'sometimes|nullable|boolean',
        ];
    }

    private function wechatAuthRules()
    {
        return [
            'ids' => 'required|array',
            'ids.*' => 'required|int'
        ];
    }
}
