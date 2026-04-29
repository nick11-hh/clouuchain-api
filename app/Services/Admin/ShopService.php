<?php

namespace App\Services\Admin;

use App\Jobs\AutoPullOrderJob;
use App\Lib\Code;
use App\Mail\DelayedShipmentEmail;
use App\Mail\MailConfig;
use App\Models\Order;
use App\Models\ShopGroupModel;
use App\Models\ShopGroupsMappingModel;
use App\Models\ShopModel;
use App\Models\ShopSetting;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Exceptions\AccidentException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ShopService extends BaseService
{
    protected $filterRules = [
        'shop_name' => ['like', 'keyword'],
        'status'    => ['=', 'status'],
        'enable'    => ['=', 'enable'],
        'platform'  => ['=', 'platform'],
        'tax,european_union_tax,united_kingdom_tax,norway_tax' => ['like', 'tax'],
    ];

    public function __construct(ShopModel $model)
    {
        $this->model = $model;
        $this->query = $model->newQuery();
        $this->formData = request()->all();
        $this->setFilterRules();
    }

    public function index()
    {
        $this->query->with('customer:id,custom_name');

        if ($this->formData['customer_id'] ?? '') {
            $this->query->where('customer_id', $this->formData['customer_id']);
        }
        if ($this->formData['customer_id2'] ?? '') {
            $this->query->where('customer_id', $this->formData['customer_id2']);
        }
        return parent::index();
    }

    public function update($id)
    {
        validator($this->formData, [
            'shop_name' => 'required|string'
        ])->validate();

        $shop = $this->model::query()->findOrFail($id);
        $data = [
            'shop_name' => $this->formData['shop_name'],
        ];
        $exist = ShopModel::query()->where('shop_name', $this->formData['shop_name'])->where('id', '!=', $shop->id)->first();
        if ($exist) {
            throw new AccidentException('该店铺名称已存在');
        }
        $shop->update($data);

        return true;
    }

    public function getGroups()
    {
        $list = ShopGroupModel::query()->with('shops:shop_group_id,shop_id')->where('admin_id', auth('admin')->id())->get();

        $list->map(function ($v) {
            $v->shops = $v->shops->map(function ($shop) {
              return $shop->shop_id ;
            });

            return $v;
        });

        return $list;
    }

    public function addGroup()
    {
        validator($this->formData, [
            'group_name' => 'required|string|max:20',
            'shop_ids' => 'required|array',
            'description' => 'sometimes|string|nullable|max:200',
        ])->validate();

        $adminId = auth('admin')->id();

        throw_if(
            ShopGroupModel::where('group_name', $this->formData['group_name'])->where('admin_id', $adminId)->exists(),
            new AccidentException('操作失败，分组名称已存在', Code::OPERATE_FAIL)
        );

        DB::beginTransaction();
        try {
            $groupData = ShopGroupModel::init($this->formData);
            $group = ShopGroupModel::query()->create($groupData);

            $mappingsData = [];
            foreach ($this->formData['shop_ids'] as $shopId) {
                $mappings = [
                    'admin_id' => $adminId,
                    'shop_group_id' => $group->id,
                    'shop_id' => $shopId,
                ];

                $mappingsData[] = ShopGroupsMappingModel::init($mappings);
            }

            ShopGroupsMappingModel::query()->insert($mappingsData);

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();

            return false;
        }

        return true;
    }

    public function updateGroup()
    {
        validator($this->formData, [
            'id' => 'required|int',
            'group_name' => 'required|string|max:20',
            'shop_ids' => 'required|array',
            'description' => 'sometimes|string|nullable|max:200',
        ])->validate();

        $group = ShopGroupModel::query()->with('shops:id,shop_group_id,shop_id')->findOrFail($this->formData['id']);

        throw_if(
            ShopGroupModel::query()->where('group_name', $this->formData['group_name'])->whereNot('id', $this->formData['id'])->exists(),
            new AccidentException('操作失败，分组名称已存在', Code::OPERATE_FAIL)
        );

        DB::beginTransaction();
        try {
            if ($this->formData['group_name'] !== $group->group_name) {
                $group->group_name = $this->formData['group_name'];

                if (isset($this->formData['description'])){
                    $group->description = $this->formData['description'];
                }

                $group->save();
            }

            $oldShopIds = $group->shops->pluck('shop_id', 'id')->toArray();

            $delShopIds = array_diff($oldShopIds, $this->formData['shop_ids']);

            $mappingsData = [];
            foreach ($this->formData['shop_ids'] as $shopId) {

                if (in_array($shopId, $oldShopIds)){
                    continue;
                }

                $mappings = [
                    'admin_id' => $group->admin_id,
                    'shop_group_id' => $group->id,
                    'shop_id' => $shopId,
                ];

                $mappingsData[] = ShopGroupsMappingModel::init($mappings);
            }

            if ($mappingsData) {
                ShopGroupsMappingModel::query()->insert($mappingsData);
            }

            if ($delShopIds) {
                ShopGroupsMappingModel::query()->whereIn('id', array_keys($delShopIds))->delete();
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();

            return false;
        }

        return true;
    }

    public function deletesGroup()
    {
        $ids = $this->formData['ids'] ?? [];

        if (empty($ids)) {
            throw new AccidentException('请选择需要删除的数据', Code::OPERATE_FAIL);
        }

        DB::beginTransaction();
        try {
            ShopGroupModel::query()->whereIn('id', $ids)->delete();

            ShopGroupsMappingModel::query()->whereIn('shop_group_id', $ids)->delete();

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();

            return false;
        }

        return true;
    }

    public function batchEditShopTax()
    {
        validator($this->formData, [
            'ids' => 'required|array',
        ])->validate();

        DB::beginTransaction();
        try {
            $europeanUnionTax = $this->formData['european_union_tax'] ?? '';
            $unitedKingdomTax = $this->formData['united_kingdom_tax'] ?? '';
            $norwayTax = $this->formData['norway_tax'] ?? '';

            $data = [];
            if ($europeanUnionTax) {
                $data['european_union_tax'] = $europeanUnionTax;
            }
            if ($unitedKingdomTax) {
                $data['united_kingdom_tax'] = $unitedKingdomTax;
            }
            if ($norwayTax) {
                $data['norway_tax'] = $norwayTax;
            }

            $this->model::query()->whereIn('id', $this->formData['ids'])->update($data);
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();

            return false;
        }

        return true;
    }

    /**
     * 获取平台类型列表
     * @return array
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/11/18 20:25
     */
    public function getPlatformList()
    {
        $list = transformArray($this->model::PLATFORM_LIST);
        foreach ($list as &$value) {
            $value['label'] = $value['label'] == 'All' ? "全部" : $value['label'];
        }

        $lastPlatform = array_pop($list);
        array_unshift($list, $lastPlatform);

        return $list;
    }

    public function syncOrder($id)
    {
        $shop = ShopModel::query()->findOrFail($id);
        dispatch(new AutoPullOrderJob($shop))->onQueue('sync_order_high');
        return true;
    }

    public function manualSendEmail($id)
    {
        $list = Order::query()->with('shippingAddress:id,order_id,first_name,last_name,email')
            ->whereBetween('created_at', ['2026-01-07 00:00:00', '2026-01-13 23:59:59'])
            ->where('shop_id', $id)->whereIn('order_status', [3, 4])->get();
        $successCount = 0;
        $failCount = 0;
        $skipCount = 0;

        foreach ($list as $item) {
            $email = $item->shippingAddress->email;
            if (!$email) {
                Log::error('发送邮件失败，order_id：'.$item->id.'，error：邮箱为空。');
                $skipCount++;
                continue;
            }
            // 检查订单是否已经发送过邮件
            $cacheKey = 'sent_delayed_shipment_email_' . $email;
            if (Cache::has($cacheKey)) {
                info('订单已发送过邮件，跳过', [$email]);
                $skipCount++;
                continue; // 已经发送过邮件，跳过
            }
            try {
                $emailParams = [
                    'name' => $item->shippingAddress->first_name
                ];
                MailConfig::getEmailConfig();
                Mail::to($email)->send(new DelayedShipmentEmail($emailParams));

                // 记录已发送邮件的订单ID到缓存，设置过期时间（例如24小时）
                Cache::put($cacheKey, true, Carbon::now()->addHours(168));

                $successCount++;
            } catch (\Exception $e) {
                $failCount++;
                Log::error('发送邮件失败，email：'.$email.'，error：'.$e->getMessage());
            }
        }
        return [
            'success_count' => $successCount,
            'fail_count' => $failCount,
            'skip_count' => $skipCount,
        ];
    }

    /** 获取店铺设置
     * @param $shopId
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|object|null
     */
    public function getShopSetting($shopId)
    {
        return ShopSetting::query()->where('shop_id', $shopId)->first();
    }

    /** 保存店铺设置
     * @param $shopId
     * @param $params
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model
     * @throws \Illuminate\Validation\ValidationException
     */
    public function saveShopSetting($shopId, $params)
    {
        validator($params, [
            'send_customer_email' => 'required|int',
            'auto_shop_delivery' => 'required|int'
        ])->validate();
        return ShopSetting::query()->updateOrCreate(['shop_id' => $shopId], [
            'send_customer_email' => $params['send_customer_email'],
            'auto_shop_delivery' => $params['auto_shop_delivery'],
            'delivery_type' => $params['delivery_type'],
        ]);
    }

}
