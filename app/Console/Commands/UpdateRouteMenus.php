<?php

namespace App\Console\Commands;

use App\Models\RouteMenuModel as RouteMenu;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Multitenancy\Commands\Concerns\TenantAware;

class UpdateRouteMenus extends Command
{
    use TenantAware;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dsp:update-menu-route {--tenant=*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '更新菜单路由权限';

    protected $method = [
        'GET' => '查询', 'POST' => '新增', 'PUT' => '修改', 'DELETE' => '删除',
    ];

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $routes = [
            [
                'name' => '首页',
                'children' => [
                    [
                        'name' => '控制面板',
                        'path' => '/home/panel',
                    ],
                    [
                        'name' => '基本设置',
                        'path' => '/home/basicsetup',
                    ],
                ],
            ],
            [
                'name' => '产品',
                'children' => [
                    [
                        'name' => '产品开发管理',
                        'path' => '/product/productExploit'
                    ],
                    [
                        'name' => '产品分类',
                        'path' => '/product/classify'
                    ],
                    [
                        'name' => '添加产品',
                        'path' => '/product/addProduct'
                    ],
                    [
                        'name' => '商品详情',
                        'path' => '/product/goodsDetail'
                    ],
                    [
                        'name' => '热销产品管理',
                        'path' => '/product/fieryProduct'
                    ],
                    [
                        'name' => '中国热卖产品管理',
                        'path' => '/product/chinaHotSale'
                    ],
                    [
                        'name' => '采集产品管理',
                        'path' => '/product/collectProduct'
                    ],
                    [
                        'name' => '客户产品报价',
                        'path' => '/product/customerProduct'
                    ],
                    [
                        'name' => '组合SKU',
                        'path' => '/product/group'
                    ]
                ]
            ],
            [
                'name' => '订单',
                'children' => [
                    [
                        'name' => '订单',
                        'path' => '/order/orderLis'
                    ],
                    [
                        'name' => '更换物流管理',
                        'path' => '/order/changeLogistics'
                    ],
                    [
                        'name' => '寻源报价',
                        'path' => '/order/resources'
                    ],
                    [
                        'name' => 'sku报价',
                        'path' => '/order/SkuQuotation'
                    ],
                    [
                        'name' => '售后工单',
                        'path' => '/order/workOrder',
                    ],
                    [
                        'name' => '优惠折扣',
                        'path' => '/marketing/goodsDiscount'
                    ],
                    [
                        'name' => '订单包裹',
                        'path' => '/order/packageList'
                    ],
                    [
                        'name' => '备货订单',
                        'path' => '/order/stockOrderList'
                    ],
                ]
            ],
            [
                'name' => '仓库',
                'children' => [
                    [
                        'name' => '快进快出',
                        'path' => '/warehouse/warehouse'
                    ],
                    [
                        'name' => '仓库库存',
                        'path' => '/warehouse/stock'
                    ],
                    [
                        'name' => '货位库存',
                        'path' => '/warehouse/cargo'
                    ],
                    [
                        'name' => '库存流水',
                        'path' => '/warehouse/inventoryFlow'
                    ],
                    [
                        'name' => '库存盘点',
                        'path' => '/warehouse/inventoryCheck'
                    ],
                    [
                        'name' => '入库单列表',
                        'path' => '/warehouse/inventoryList'
                    ],
                    [
                        'name' => '入库签收',
                        'path' => '/warehouse/signInventoryOrder'
                    ],
                    [
                        'name' => '入库上架',
                        'path' => '/warehouse/shelvesInventory'
                    ],
                    [
                        'name' => '发货管理',
                        'path' => '/warehouse/parcel'
                    ],
                    [
                        'name' => '波次管理',
                        'path' => '/warehouse/wave'
                    ],
                    [
                        'name' => '二次分拣',
                        'path' => '/warehouse/secondSort'
                    ],
                    [
                        'name' => '称重',
                        'path' => '/warehouse/weight'
                    ],
                ],
            ],
            [
                'name' => '财务',
                'children' => [
                    [
                        'name' => '在线充值',
                        'path' => '/finance/onlineRechargeRecord',
                    ],
                    [
                        'name' => '充值记录',
                        'path' => '/finance/recharge'
                    ],
                    [
                        'name' => '交易流水',
                        'path' => '/finance/transaction'
                    ],
                    [
                        'name' => '充值审核',
                        'path' => '/finance/rechargeDetails/:id/:state'
                    ],
                    [
                        'name' => '充值详情',
                        'path' => '/finance/rechargeDetails/:id'
                    ],
                    [
                        'name' => '交易流水详情',
                        'path' => '/finance/transactionDetails/:id'
                    ],
                    [
                        'name' => '佣金结算',
                        'path' => '/finance/commission'
                    ],
                    [
                        'name' => '发票管理',
                        'path' => '/finance/invoice'
                    ],
                    [
                        'name' => '客户钱包',
                        'path' => '/finance/customerWallet'
                    ],
                    [
                        'name' => '备货申请',
                        'path' => '/finance/stockingRequest'
                    ],
                ]
            ],
            [
                'name' => '客户',
                'children' => [
                    [
                        'name' => '客户列表',
                        'path' => '/marketing/clientList'
                    ],
                    [
                        'name' => '客户分类',
                        'path' => '/marketing/clientClassify'
                    ],
                    [
                        'name' => '营销推广',
                        'path' => '/marketing/promotion'
                    ],
                    [
                        'name' => '店铺管理',
                        'path' => '/marketing/shopList'
                    ],
                ]
            ],
            [
                'name' => '配置',
                'children' => [
                    [
                        'name' => '员工组',
                        'path' => '/configuration/staffGroupList'
                    ],
                    [
                        'name' => '员工',
                        'path' => '/staff/staffList'
                    ],
                    [
                        'name' => '支付配置',
                        'path' => '/configuration/paymentConfig'
                    ],
                    [
                        'name' => '1688采购账号管理',
                        'path' => '/configuration/alibabaAccount'
                    ],
                    [
                        'name' => '仓库管理',
                        'path' => '/configuration/warehouse'
                    ],
                    [
                        'name' => '基础配置',
                        'path' => '/configuration/baseConfiguration'
                    ],
                    [
                        'name' => '消息配置',
                        'path' => '/configuration/noticeConfiguration'
                    ],
                    [
                        'name' => '客户端配置',
                        'path' => '/configuration/clientConfiguration'
                    ],
                    [
                        'name' => '开放API',
                        'path' => '/configuration/restApiConfig'
                    ],
                    [
                        'name' => '数据范围',
                        'path' => '/configuration/dataRangeGroup'
                    ],
                ]
            ],
            [
                'name' => '采购',
                'children' => [
                    [
                        'name' => '采购订单',
                        'path' => '/purchase/purchaseOrder'
                    ],
                    [
                        'name' => '供应商管理',
                        'path' => '/purchase/supplier'
                    ],
                    [
                        'name' => '供货关系',
                        'path' => '/purchase/goodSupplier'
                    ],
                    [
                        'name' => '采购计划',
                        'path' => '/purchase/plan'
                    ],
                ]
            ],
            [
                'name' => '物流',
                'children' => [
                    [
                        'name' => '运费试算',
                        'path' => '/logistics/freight'
                    ],
                    [
                        'name' => '运费详情',
                        'path' => '/logistics/freight/detail/:id'
                    ],
                    [
                        'name' => '物流授权',
                        'path' => '/logistics/logisticsConfig'
                    ],
                    [
                        'name' => '报价模板',
                        'path' => '/logistics/quotation'
                    ],
                    [
                        'name' => '运费模板',
                        'path' => '/logistics/line'
                    ],
                    [
                        'name' => '常用报关信息',
                        'path' => '/logistics/customs'
                    ],
                    [
                        'name' => '物流查询授权',
                        'path' => '/logistics/trackingConfig'
                    ],
                ]
            ]
        ];
        DB::statement('TRUNCATE TABLE `dsp_route_menus`');
        DB::transaction(function () use ($routes) {
            foreach ($routes as $key => $route) {
                $name = $route['name'];
                $tag = (int)sprintf('%s%s', $key + 1, '000');

                $parent = RouteMenu::query()->create([
                    'name' => $name,
                    'tag' => $tag,
                    'enabled' => $route['enabled'] ?? 1,
                    'created_at' => now()->toDateTimeString(),
                    'updated_at' => now()->toDateTimeString(),
                ]);

                if ($route['children'] ?? []) {
                    foreach ($route['children'] as $key1 => $route1) {
                        $name = $route1['name'];

                        $subParent = RouteMenu::query()->create([
                            'name' => $name,
                            'tag' => (int)sprintf('%s%s%s', $key + 1, $key1 + 1, '00'),
                            'created_at' => now()->toDateTimeString(),
                            'updated_at' => now()->toDateTimeString(),
                            'parent_id' => $parent->id,
                            'enabled' => $route1['enabled'] ?? 1,
                        ]);

                        if ($route1['children'] ?? []) {
                            $data = [];
                            foreach ($route1['children'] as $key2 => $route2) {
                                $name = $route2['name'];

                                $id = RouteMenu::query()->insertGetId([
                                    'name' => $name,
                                    'tag' => ((int)sprintf('%s%s%s', $key + 1, $key1 + 1, '00')) + $key2 + 1,
                                    'route_path' => $route2['path'],
                                    'created_at' => now()->toDateTimeString(),
                                    'updated_at' => now()->toDateTimeString(),
                                    'parent_id' => $subParent->id,
                                    'enabled' => $route2['enabled'] ?? 1,
                                ]);

                                foreach ($this->method as $method => $name) {
                                    $data[] = [
                                        'name' => $name,
                                        'tag' => ((int)sprintf('%s%s%s', $key + 1, $key1 + 1, '00')) + $key2 + 1,
                                        'route_path' => $route2['path'],
                                        'route_method' => $method,
                                        'created_at' => now()->toDateTimeString(),
                                        'updated_at' => now()->toDateTimeString(),
                                        'parent_id' => $id,
                                    ];
                                }
                            }

                            RouteMenu::query()->insert($data);
                        } else {
                            $data = [];
                            foreach ($this->method as $method => $name) {
                                $data[] = [
                                    'name' => $name,
                                    'tag' => (int)sprintf('%s%s%s', $key + 1, $key1 + 1, '00'),
                                    'route_path' => $route1['path'],
                                    'route_method' => $method,
                                    'created_at' => now()->toDateTimeString(),
                                    'updated_at' => now()->toDateTimeString(),
                                    'parent_id' => $subParent->id,
                                ];
                            }

                            RouteMenu::query()->insert($data);
                        }
                    }
                }
            }
        });
        $this->info(' 菜单初始化成功!');
    }
}
