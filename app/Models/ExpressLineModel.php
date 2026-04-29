<?php

namespace App\Models;

use App\Lib\Code;
use App\Models\Traits\Basis;
use App\Models\Traits\CompanyLimitChecker;
use App\Models\Traits\CustomHasTranslations;
use App\Models\Traits\HasValidateUnique;
use App\Services\Price\Counter;
use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Exceptions\AccidentException;
use Illuminate\Support\Facades\Cache;

class ExpressLineModel extends Model
{
    use HasFactory;

    protected $table = 'dsp_express_line';

    use Basis,
        HasValidateUnique,
        CustomHasTranslations,
        CompanyLimitChecker;

    public const BASE_MODE_WEIGHT = 0;
    public const BASE_MODE_VOLUME = 1;

    public const MODE_1 = 1; //首重续重模式
    public const MODE_2 = 2; //价格档模式
    public const MODE_MIX = 3; //首重加價格儅模式
    public const MODE_GRADE_NEXT = 4; //多重续重
    public const MODE_RANGE_FIRST_NEXT = 5; //范围首重续重

    //免抛条件类型1-所有边长2-单边长度3-三边之和4-长宽高乘积5-体积重量-实际重量
    public const NO_THROW_CONDITION_1 = 1;
    public const NO_THROW_CONDITION_2 = 2;
    public const NO_THROW_CONDITION_3 = 3;
    public const NO_THROW_CONDITION_4 = 4;
    public const NO_THROW_CONDITION_5 = 5;

    public const ORDER_MODE_NORMAL = 0; //正常的订单模式
    // 简化版模式
    // 客户端提交订单后会跳过订单打包步骤
    // 直接生成待支付订单
    public const ORDER_MODE_SIMPLE = 1;
    public const ORDER_MODE_EARLY_PAY = 2;

    public const RULE_FEE_ALL = 0;
    public const RULE_FEE_MAX = 1;

    public const MULTI_BOX_EACH = 1;
    public const MULTI_BOX_SUM = 2;
    public const MULTI_BOX_EACH_NO_CEIL = 3;

    public const RANGE_LEFT_CLOSE = 0;
    public const RANGE_RIGHT_CLOSE = 0;

    //授权客户1-全体客户2-部分客户
    public const AUTH_ALL = 1;
    public const AUTH_PARTY = 2;

    //落地配方式1-单接口2-多接口
    public const CHANNEL_TYPE_SINGLE = 1;
    public const CHANNEL_TYPE_MULTI = 2;

    public $searchable = [
        'cn_name',
        'name',
        'first_weight',
        'first_money',
        'next_weight',
        'next_money',
        'min_weight',
        'max_weight',
        'reference_time',
        'remark',
        'factor',
//        'warehouses.warehouse_name',
        'countries.cn_name',
        'countries.name',
        'types.cn_name',
    ];

    //用于翻译
    public $translatable = ['cn_name', 'en_name', 'remark', 'name', 'reference_time', 'rule_remark', 'overweight_remark'];

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [
        'no_throw_condition' => 'array',
    ];

    /**
     * 线路类型
     * @return BelongsToMany
     */
    public function props(): BelongsToMany
    {
        return $this->belongsToMany(
            PackageProp::class,
            'dsp_express_line_props',
            'express_line_id',
            'prop_id'
        );
    }

    /**
     * 线路属于的仓库
     *
     * @return BelongsToMany
     */
    public function warehouses()
    {
        return $this->belongsToMany(
            WarehouseAddress::class,
            'dsp_warehouse_express_line',
            'express_line_id',
            'warehouse_id'
        );
    }

    /**
     * 线路费用
     *
     * @return BelongsToMany
     */
    public function costs(): BelongsToMany
    {
        return $this->belongsToMany(
            ExpressLineCostModel::class,
            'dsp_express_line_costs_pivot',
            'express_line_id',
            'express_line_cost_id'
        )->withPivot(['price', 'type']);
    }

    /**
     * 线路代理佣金
     * @return BelongsToMany
     */
    public function lineCommissions(): BelongsToMany
    {
        return $this->belongsToMany(
            Agent::class,
            'dsp_agent_commission_express_line',
            'express_line_id',
            'agent_id',
            'id'
        )->withPivot(['type', 'commission']);
    }

    /**
     * 支持的国家
     * @return BelongsToMany
     */
    public function countries(): BelongsToMany
    {
        return $this->belongsToMany(
            Country::class,
            ExpressLineCountryModel::class,
            'express_line_id',
            'country_id'
        )->withPivot(['area_id', 'sub_area_id']);
    }

    /**
     * 线路图标
     *
     * @return HasOne
     */
    public function icon(): HasOne
    {
        return $this->hasOne(ExpressLineIconsModel::class, 'id', 'icon_id')
            ->withDefault(static::getDefaultIcon());
    }

    /**
     * 默认线路图标
     *
     * @return array
     */
    public static function getDefaultIcon(): array
    {
        return [
            'name' => '系统默认',
            'icon' => '/storage/admin/icon/default.png',
        ];
    }

    /**
     * 一个线路可能存在多条价格档
     */
    public function priceGrade(): HasMany
    {
        return $this->hasMany(ExpressLinePriceGradeModel::class, 'express_line_id', 'id');
    }

    /**
     * 线路标签
     *
     * @return BelongsToMany
     */
    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(
            ExpressLineLabelsModel::class,
            'dsp_express_line_labels_temp',
            'express_line_id',
            'label_id',
            'id',
            'id'
        );
    }

    /**
     * 线路分区
     *
     * @return HasMany
     */
    public function regions(): HasMany
    {
        return $this->hasMany(ExpressLineRegion::class, 'express_line_id', 'id');
    }

    /**
     * 价格规则
     *
     * @return HasMany
     */
    public function priceRules(): HasMany
    {
        return $this->hasMany(ExpressLinePriceRulesModel::class, 'express_line_id', 'id');
    }

    /**
     * 渠道增值服务
     *
     * @return HasMany
     */
    public function services(): HasMany
    {
        return $this->hasMany(ExpressLineVAS::class, 'express_line_id', 'id');
    }

    /**
     * 可用自提点
     *
     * @return BelongsToMany
     */
    public function selfPickupStations(): BelongsToMany
    {
        return $this->belongsToMany(
            SelfPickupStation::class,
            'dsp_self_pickup_station_expresslines',
            'express_line_id',
            'station_id'
        );
    }

    /**
     * 价格信息
     *
     * @return HasMany
     */
    public function prices(): HasMany
    {
        return $this->hasMany(ExpressLinePrice::class, 'express_line_id', 'id');
    }

    /**
     * 线路默认自提点
     *
     * @return HasOne
     */
    public function defaultStation(): HasOne
    {
        return $this->hasOne(SelfPickupStation::class, 'id', 'default_pickup_station_id');
    }

    /**
     * @param $region
     * @param $countWeight
     * @param bool $split
     * @param bool $noException
     * @param bool $box
     * @param User|null $user
     * @param bool $useVolume
     * @return array|int|null
     */
    public function getExpressFeeNew(
        $region,
        $countWeight,
        bool $split = false,
        bool $noException = false,
        bool $box = false,
        ?User $user = null,
        bool $useVolume = false
    )
    {
        if (! $region) {
            throw new AccidentException('地址未匹配到可用渠道分区', Code::OPERATE_FAIL);
        }

        if ($useVolume) {
            return Counter::getVolumeFreightFee($region, $countWeight, $split, $noException, $box, $user);
        }

        return Counter::getFreightFee($region, $countWeight, $split, $noException, $box, $user);
    }

    public function group()
    {
        return $this->belongsTo(ExpressLineGroupsModel::class, 'group_id', 'id');
    }

    public function authUserGroups()
    {
        return $this->belongsToMany(
            CustomGroup::class,
            'dsp_express_line_to_user_groups_table',
            'express_line_id',
            'user_group_id'
        );
    }

    public function authMemberLevels()
    {
        return $this->belongsToMany(
            MemberLevel::class,
            'dsp_express_line_to_member_levels_table',
            'express_line_id',
            'member_level_id'
        );
    }

    public function authUserTags()
    {
        return $this->belongsToMany(
            UserTag::class,
            'dsp_express_line_to_user_tags_table',
            'express_line_id',
            'tag_id'
        );
    }

    public function authUsers()
    {
        return $this->belongsToMany(
            Custom::class,
            'dsp_express_line_to_users_table',
            'express_line_id',
            'user_id'
        );
    }

    /**
     * 线路拼团配置
     *
     * @return HasOne
     */
    public function groupConfig(): HasOne
    {
        return $this->hasOne(ExpressLineGroupConfigModel::class, 'express_line_id', 'id');
    }

    /**
     * @param ExpressLineRegionAreasModel $area
     * @param array|UserAddress $address
     * @return bool
     */
    public static function verifyArea(ExpressLineRegionAreasModel $area, array|UserAddress $address)
    {
        if ($area->country_id != $address['country_id'] && $area->country_id != 0) {
            return false;
        }

        if ($area->area_id && $area->area_id != ($address['area_id'] ?? null)) {
            return false;
        }

        if ($area->sub_area_id && $area->sub_area_id != ($address['sub_area_id'] ?? null)) {
            return false;
        }

        return true;
    }

    /**
     * @param int $countryId
     * @param int|null $areaId
     * @param int|null $subAreaId
     * @param string $postcode
     * @param bool $isStation
     * @param bool $alwaysPostcode
     * @return null|ExpressLineRegion
     */
    public function getRegionByArea(
        int $countryId,
        int $areaId = null,
        int $subAreaId = null,
        string $postcode = '',
        bool $isStation = false,
        bool $alwaysPostcode = false
    )
    {
        $address = [
            'country_id' => $countryId,
            'area_id' => $areaId,
            'sub_area_id' => $subAreaId,
            'postcode' => $postcode,
        ];

        $this->load(['regions', 'regions.areas', 'regions.postcodeAreas']);

        return $this->regions->first(function ($region) use ($address, $isStation, $alwaysPostcode) {
            if ($region->type === ExpressLineRegion::TYPE_AREA) {
                return $region->areas->contains(function ($area) use ($address) {
                    return self::verifyArea($area, $address);
                });
            } else {
                return $region->country_id == $address['country_id']
                    && ($region->postcodeAreas->contains(function ($area) use ($address) {
                            if ($area->type === ExpressLineRegionPostcodeArea::TYPE_RANGE) {
                                return postcode_integer($address['postcode']) >= postcode_integer($area->start)
                                    && postcode_integer($address['postcode']) <= postcode_integer($area->end);
                            } elseif ($area->type === ExpressLineRegionPostcodeArea::TYPE_FIXED) {
                                return $address['postcode'] == $area->start && !$area->end;
                            }

                            return false;
                        }) || $isStation || $alwaysPostcode
                    );
            }
        });
    }

    public function getRegionByAreas(
        int $countryId,
        int $areaId = null,
        int $subAreaId = null,
        string $postcode = '',
        bool $isStation = false,
        bool $alwaysPostcode = false
    )
    {
        $address = [
            'country_id' => $countryId,
            'area_id' => $areaId,
            'sub_area_id' => $subAreaId,
            'postcode' => $postcode,
        ];

        $this->load(['regions', 'regions.areas', 'regions.postcodeAreas']);

        return $this->regions->filter(function ($region) use ($address, $isStation, $alwaysPostcode) {
//            dd($region);
            return $region->areas->contains(function ($area) use ($address) {
                return self::verifyArea($area, $address);
            });
        });
    }

    /**
     * 生成运费模板编码 MATE+4位企业ID+4位自增数值 ps：MATE10010001
     */
    public static function generateCode()
    {
        $pre = 'MATE1001';
        $cacheKey = 'adminExpressLineCode';
        if (Cache::has($cacheKey)) {
            $increment = Cache::increment($cacheKey);
        } else {
            $increment = self::withTrashed()->count();

            if (empty($increment)) {
                $increment = 1;
            } else {
                $increment++;
            }

            // 首次写入使用 set，避免在 Redis::incrBy 中传入非法类型
            Cache::set($cacheKey, (int) $increment);
        }

        return $pre . str_pad($increment, 4, 0, STR_PAD_LEFT);
    }
}
