<?php

namespace App\Console\Commands;

use App\Lib\Language;
use App\Models\Admin;
use App\Models\ClientMenu;
use App\Models\Custom;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Multitenancy\Commands\Concerns\TenantAware;

class ClientMenuRoute extends Command
{
    use TenantAware;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dsp:update-client-menu {customId=0}  {--tenant=*}';

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
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $routes = [
            [
                'name' => '首页',
                'name_translate' => [
                    Language::CHINESE => '首页',
                    Language::ENGLISH => 'Home',
                    Language::RUSSIAN => 'Главная страница',
                    Language::ARABIC => 'الصفحة الأولى',
                    Language::PORTUGAL => 'Página de Inicio',
                    Language::VIETNAM => 'Trang Chủ',
                ],
                'children' => [
                    [
                        'name' => '控制面板',
                        'name_translate' => [
                            Language::CHINESE => '控制面板',
                            Language::ENGLISH => 'Control Panel',
                            Language::RUSSIAN => 'Панель управления',
                            Language::ARABIC => 'لوحة التحكم',
                            Language::PORTUGAL => 'Panel de Control',
                            Language::VIETNAM => 'Bảng Điều Khiển',

                        ],
                        'path' => '/statistics',
                    ]
                ],
            ],
            [
                'name' => '产品管理',
                'name_translate' => [
                    Language::CHINESE => '产品管理',
                    Language::ENGLISH => 'Product',
                    Language::RUSSIAN => 'Управление продуктом',
                    Language::ARABIC => 'إدارة المنتجات',
                    Language::PORTUGAL => 'Gestión de Productos',
                    Language::VIETNAM => 'Quản Lý Sản Phẩm',
                ],
                'children' => [
                    [
                        'name' => '热销产品库',
                        'name_translate' => [
                            Language::CHINESE => '热销产品库',
                            Language::ENGLISH => 'Hot Sales',
                            Language::RUSSIAN => 'Склад товаров для сбыта',
                            Language::ARABIC => 'بنك المبيعات الحرارية',
                            Language::PORTUGAL => 'Inventario de Productos Populares',
                            Language::VIETNAM => 'Kho Sản Phẩm Bán Chạy',
                        ],
                        'path' => '/statistics',
                    ],
                    [
                        'name' => '寻源下单',
                        'name_translate' => [
                            Language::CHINESE => '寻源下单',
                            Language::ENGLISH => 'Sourcing',
                            Language::RUSSIAN => 'Поиск источника',
                            Language::ARABIC => 'طلب المصدر',
                            Language::PORTUGAL => 'Búsqueda y Pedido',
                            Language::VIETNAM => 'Tìm Nguồn & Đặt Hàng',    
                        ],
                        'path' => '/statistics',
                    ],
                    [
                        'name' => '1688',
                        'name_translate' => [
                            Language::CHINESE => '1688',
                            Language::ENGLISH => '1688',
                            Language::RUSSIAN => '1688',
                            Language::ARABIC => '1688',
                            Language::PORTUGAL => '1688',
                            Language::VIETNAM => '1688',
                        ],
                        'path' => '/statistics',
                    ],
                    [
                        'name' => '已选择产品',
                        'name_translate' => [
                            Language::CHINESE => '已选择产品',
                            Language::ENGLISH => 'Selected Products',
                            Language::RUSSIAN => 'Выбирать продукт',
                            Language::ARABIC => 'تم اختيار المنتج',
                            Language::PORTUGAL => 'Productos Seleccionados',
                            Language::VIETNAM => 'Sản Phẩm Đã Chọn',
                        ],
                        'path' => '/statistics',
                    ],
                    [
                        'name' => '店铺产品',
                        'name_en' => 'Shop Products',
                        'name_translate' => [
                            Language::CHINESE => '店铺产品',
                            Language::ENGLISH => 'Shop Products',
                            Language::RUSSIAN => 'Продукция магазина',
                            Language::ARABIC => 'منتجات المتاجر',
                            Language::PORTUGAL => 'Productos de Tienda',
                            Language::VIETNAM => 'Sản Phẩm Cửa Hàng',
                        ],
                        'path' => '/statistics',
                    ],
                    [
                        'name' => '我的库存',
                        'name_translate' => [
                            Language::CHINESE => '我的库存',
                            Language::ENGLISH => 'My Inventory',
                            Language::RUSSIAN => 'Мой инвентарь',
                            Language::ARABIC => 'مخزوني',
                            Language::PORTUGAL => 'Mi Inventario',
                            Language::VIETNAM => 'Kho Hàng Của Tôi',
                        ],
                        'path' => '/statistics',
                    ],
                ]
            ],
            [
                'name' => '订单',
                'name_translate' => [
                    Language::CHINESE => '订单',
                    Language::ENGLISH => 'Order',
                    Language::RUSSIAN => 'заказ',
                    Language::ARABIC => 'أوامر',
                    Language::PORTUGAL => 'Pedidos',
                    Language::VIETNAM => 'Đơn Hàng',
                ],
                'children' => [
                    [
                        'name' => '订单',
                        'name_translate' => [
                            Language::CHINESE => '订单',
                            Language::ENGLISH => 'Order',
                            Language::RUSSIAN => 'заказ',
                            Language::ARABIC => 'أوامر',
                            Language::PORTUGAL => 'Pedidos',
                            Language::VIETNAM => 'Đơn Hàng',
                        ],
                        'path' => '/statistics',
                    ],
                    [
                        'name' => '批量下单',
                        'name_translate' => [
                            Language::CHINESE => '批量下单',
                            Language::ENGLISH => 'Bulk Order',
                            Language::RUSSIAN => 'оптом',
                            Language::ARABIC => 'حجم الطلب',
                            Language::PORTUGAL => 'Pedido Masivo',
                            Language::VIETNAM => 'Đặt Hàng Hàng Loạt',
                        ],
                        'path' => '/statistics',
                    ],
                    [
                        'name' => '备货订单',
                        'name_translate' => [
                            Language::CHINESE => '备货订单',
                            Language::ENGLISH => 'Stock Order',
                            Language::RUSSIAN => 'Заказ на запасные товары',
                            Language::ARABIC => 'طلب الإحتياطي',
                            Language::PORTUGAL => 'Pedidos de Reabastecimiento',
                            Language::VIETNAM => 'Đơn Đặt Hàng Dự Phòng',
                        ],
                        'path' => '/statistics',
                    ],
                    [
                        'name' => '售后工单',
                        'name_translate' => [
                            Language::CHINESE => '售后工单',
                            Language::ENGLISH => 'Work Order',
                            Language::RUSSIAN => 'Рабочий заказ',
                            Language::ARABIC => 'فاتورة ما بعد البيع',
                            Language::PORTUGAL => 'Tickets de Postventa',
                            Language::VIETNAM => 'Phiếu Hỗ Trợ Sau Bán Hàng',
                        ],
                        'path' => '/statistics',
                    ]
                ]
            ],
            [
                'name' => '店铺',
                'name_translate' => [
                    Language::CHINESE => '店铺',
                    Language::ENGLISH => 'Shop',
                    Language::RUSSIAN => 'магазин',
                    Language::ARABIC => 'من المتاجر',
                    Language::PORTUGAL => 'Tienda',
                    Language::VIETNAM => 'Cửa Hàng',
                ],
                'children' => [
                    [
                        'name' => '店铺',
                        'name_translate' => [
                            Language::CHINESE => '店铺',
                            Language::ENGLISH => 'Shop',
                            Language::RUSSIAN => 'магазин',
                            Language::ARABIC => 'من المتاجر',
                            Language::PORTUGAL => 'Tienda',
                            Language::VIETNAM => 'Cửa Hàng',
                        ],
                        'path' => '/statistics',
                    ]
                ]
            ],
            [
                'name' => '钱包',
                'name_translate' => [
                    Language::CHINESE => '钱包',
                    Language::ENGLISH => 'Wallet',
                    Language::RUSSIAN => 'Кошелёк',
                    Language::ARABIC => 'المحفظة',
                    Language::PORTUGAL => 'Cartera',
                    Language::VIETNAM => 'Ví Tiền',
                ],
                'children' => [
                    [
                        'name' => '账户余额',
                        'name_translate' => [
                            Language::CHINESE => '账户余额',
                            Language::ENGLISH => 'Account Balance',
                            Language::RUSSIAN => 'остаток счета',
                            Language::ARABIC => 'رصيد الحساب',
                            Language::PORTUGAL => 'Saldo de Cuenta',
                            Language::VIETNAM => 'Số Dư Tài Khoản',
                        ],
                        'path' => '/statistics',
                    ],
                    [
                        'name' => '账户流水',
                        'name_translate' => [
                            Language::CHINESE => '账户流水',
                            Language::ENGLISH => 'Account Transactions',
                            Language::RUSSIAN => 'Текущий поток',
                            Language::ARABIC => 'تدفق الحساب',
                            Language::PORTUGAL => 'Historial de Cuenta',
                            Language::VIETNAM => 'Lịch Sử Tài Khoản',
                        ],
                        'path' => '/statistics',
                    ],
                    [
                        'name' => '发票管理',
                        'name_translate' => [
                            Language::CHINESE => '发票管理',
                            Language::ENGLISH => 'Invoice management',
                            Language::RUSSIAN => 'Управление счетами',
                            Language::ARABIC => 'إدارة الفواتير',
                            Language::PORTUGAL => 'Gestión de Facturas',
                            Language::VIETNAM => 'Quản Lý Hóa Đơn',
                        ],
                        'path' => '/statistics',
                    ]
                ]
            ],
            [
                'name' => '推广',
                'name_translate' => [
                    Language::CHINESE => '推广',
                    Language::ENGLISH => 'Promotion',
                    Language::RUSSIAN => 'Продвижение',
                    Language::ARABIC => 'الترويج',
                    Language::PORTUGAL => 'Promoción',
                    Language::VIETNAM => 'Quảng Cáo',
                ],
                'children' => [
                    [
                        'name' => '推广',
                        'name_translate' => [
                            Language::CHINESE => '推广',
                            Language::ENGLISH => 'Promotion',
                            Language::RUSSIAN => 'Продвижение',
                            Language::ARABIC => 'الترويج',
                            Language::PORTUGAL => 'Promoción',
                            Language::VIETNAM => 'Quảng Cáo',
                        ],
                        'path' => '/statistics',
                    ]
                ]
            ],
            [
                'name' => '员工',
                'name_translate' => [
                    Language::CHINESE => '员工',
                    Language::ENGLISH => 'Employee',
                    Language::RUSSIAN => 'служащий',
                    Language::ARABIC => 'الموظفين',
                    Language::PORTUGAL => 'Empleado',
                    Language::VIETNAM => 'Nhân Viên',
                ],
                'children' => [
                    [
                        'name' => '员工组',
                        'name_translate' => [
                            Language::CHINESE => '员工组',
                            Language::ENGLISH => 'Employee Group',
                            Language::RUSSIAN => 'Персонал',
                            Language::ARABIC => 'مجموعة الموظفين',
                            Language::PORTUGAL => 'Grupo de Empleados',
                            Language::VIETNAM => 'Nhóm Nhân Viên',
                        ],
                        'path' => '/statistics',
                    ],
                    [
                        'name' => '员工列表',
                        'name_translate' => [
                            Language::CHINESE => '员工列表',
                            Language::ENGLISH => 'Employee List',
                            Language::RUSSIAN => 'Список сотрудников',
                            Language::ARABIC => 'قائمة الموظفين',
                            Language::PORTUGAL => 'Lista de Empleados',
                            Language::VIETNAM => 'Danh Sách Nhân Viên',
                        ],
                        'path' => '/statistics',
                    ]
                ]
            ],
        ];

        // 初始化前先删除旧的数据，否则授权菜单会重复
        DB::statement('TRUNCATE TABLE `dsp_client_menus`');
        DB::transaction(function () use ($routes) {
            // if ($this->argument('customId')) {
            //     $customIds = [$this->argument('customId')];
            // } else {
            //     $customIds = Custom::query()->get()->modelKeys();
            // }
            // foreach ($customIds as $customId) {
            $customId = 0;
                //初始化前先删除旧的数据，否则授权菜单会重复
                // ClientMenu::query()->where('custom_id', $customId)->forceDelete();

                foreach ($routes as $key => $route) {
                    $level = 1;
                    $name = $route['name'];
                    $nameTranslate = $route['name_translate'];
                    $tag = (int)sprintf('%s%s', $key + 1, '000');

                    $parent = ClientMenu::query()->create([
                        'name' => $name,
                        'tag' => $tag,
                        'enabled' => $route['enabled'] ?? 1,
                        'created_at' => now()->toDateTimeString(),
                        'updated_at' => now()->toDateTimeString(),
                        'custom_id' => $customId,
                        'level' => $level,
                        'name_translate' => $nameTranslate,
                    ]);

                    if ($route['children'] ?? []) {
                        foreach ($route['children'] as $key1 => $route1) {
                            $level = 2;
                            $name = $route1['name'];
                            $nameTranslate = $route1['name_translate'];

                            $subParent = ClientMenu::query()->create([
                                'name' => $name,
                                'tag' => (int)sprintf('%s%s%s', $key + 1, $key1 + 1, '00'),
                                'created_at' => now()->toDateTimeString(),
                                'updated_at' => now()->toDateTimeString(),
                                'custom_id' => $customId,
                                'parent_id' => $parent->id,
                                'enabled' => $route1['enabled'] ?? 1,
                                'level' => $level,
                                'name_translate' => $nameTranslate,
                            ]);

                            if ($route1['children'] ?? []) {
                                $data = [];
                                foreach ($route1['children'] as $key2 => $route2) {
                                    $level = 3;
                                    $name = $route2['name'];

                                    $id = ClientMenu::query()->insertGetId([
                                        'name' => $name,
                                        'tag' => ((int)sprintf('%s%s%s', $key + 1, $key1 + 1, '00')) + $key2 + 1,
                                        'route_path' => $route2['path'],
                                        'created_at' => now()->toDateTimeString(),
                                        'updated_at' => now()->toDateTimeString(),
                                        'custom_id' => $customId,
                                        'parent_id' => $subParent->id,
                                        'enabled' => $route2['enabled'] ?? 1,
                                        'level' => $level,
                                    ]);

                                    foreach ($this->method as $method => $name) {
                                        $level = 4;

                                        $data[] = [
                                            'name' => $name,
                                            'tag' => ((int)sprintf('%s%s%s', $key + 1, $key1 + 1, '00')) + $key2 + 1,
                                            'route_path' => $route2['path'],
                                            'route_method' => $method,
                                            'created_at' => now()->toDateTimeString(),
                                            'updated_at' => now()->toDateTimeString(),
                                            'custom_id' => $customId,
                                            'parent_id' => $id,
                                            'level' => $level,
                                        ];
                                    }
                                }

                                ClientMenu::query()->insert($data);
                            } else {
                                $data = [];
                                foreach ($this->method as $method => $name) {
                                    $level = 3;

                                    /*$data[] = [
                                        'name' => $name,
                                        'tag' => (int)sprintf('%s%s%s', $key + 1, $key1 + 1, '00'),
                                        'route_path' => $route1['path'],
                                        'route_method' => $method,
                                        'created_at' => now()->toDateTimeString(),
                                        'updated_at' => now()->toDateTimeString(),
                                        'custom_id' => $customId,
                                        'parent_id' => $subParent->id,
                                        'level' => $level,
                                        'name_translate' => [
                                            Language::CHINESE => $name,
                                            Language::ENGLISH => $method,
                                            Language::RUSSIAN => $method,
                                        ],
                                    ];*/

                                    ClientMenu::query()->create([
                                        'name' => $name,
                                        'tag' => (int)sprintf('%s%s%s', $key + 1, $key1 + 1, '00'),
                                        'route_path' => $route1['path'],
                                        'route_method' => $method,
                                        'created_at' => now()->toDateTimeString(),
                                        'updated_at' => now()->toDateTimeString(),
                                        'custom_id' => $customId,
                                        'parent_id' => $subParent->id,
                                        'level' => $level,
                                        'name_translate' => [
                                            Language::CHINESE => $name,
                                            Language::ENGLISH => $method,
                                            Language::RUSSIAN => $method,
                                            Language::ARABIC => $method,
                                            Language::PORTUGAL => $method,
                                            Language::VIETNAM => $method,
                                        ],
                                    ]);
                                }

                                // ClientMenu::query()->insert($data);
                            }
                        }
                    }
                }
                $this->info('客户ID：' . $customId . ' 菜单初始化成功!');
            // }
        });

        return true;
    }
}
