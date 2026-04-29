<?php

namespace App\Models;

class AdminOperationLog extends Model
{

    //模块类型--根据数据表区分模块类型
    const TYPE_1 = 1;//客户模块
    const TYPE_2 = 2;//产品开发管理模块
    const TYPE_3 = 3;//采集产品管理模块

    //具体操作类型
    const OPT_TYPE_1 = 1;//修改客户信息
    const OPT_TYPE_2 = 2;//调整信用额度
    const OPT_TYPE_3 = 3;//手动扣款
    const OPT_TYPE_4 = 4;//注销
    const OPT_TYPE_5 = 5;//允许登录
    const OPT_TYPE_6 = 6;//分配员工
    const OPT_TYPE_7 = 7;//登录客户账号
    const OPT_TYPE_8 = 8;//修改客户产品或物流报价配置
    const OPT_TYPE_9 = 9;//调整冻结额度


    protected $table = 'dsp_admin_operation_logs';

    /**
     * 管理员
     */
    public function admin()
    {
        return $this->hasOne(Admin::class, 'id', 'admin_id');
    }

    /**
     * 操作类型
     * @return string
     */
    public function getOptTypeNameAttribute()
    {
        return [
            self::OPT_TYPE_1 => '修改客户信息',
            self::OPT_TYPE_2 => '调整信用额度',
            self::OPT_TYPE_3 => '手动扣款',
            self::OPT_TYPE_4 => '注销',
            self::OPT_TYPE_5 => '允许登录',
            self::OPT_TYPE_6 => '分配员工',
            self::OPT_TYPE_7 => '登录客户账号',
            self::OPT_TYPE_8 => '修改客户产品或物流报价配置',
            self::OPT_TYPE_9 => '调整冻结额度',
        ][$this->opt_type];
    }

    /**
     * 准备日志数据
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  int  $type
     * @param  int  $optType
     * @return array
     */
    public static function make(\Illuminate\Database\Eloquent\Model $model, int $type=self::TYPE_1, int $optType=self::OPT_TYPE_1)
    {

        $insertData = [];

        $adminId = getAdminId();

        //客户模块修改
        if($type == self::TYPE_1){

            //修改客户信息
            if($optType == self::OPT_TYPE_1){

                $customId = $model->id;

                if($model->isDirty('custom_name')){

                    $originalData = $model->getOriginal('custom_name');
                    $newData = $model->custom_name;

                    $insertData[] = [
                        'type' => self::TYPE_1,
                        'opt_type' => self::OPT_TYPE_1,
                        'admin_id' => $adminId,
                        'custom_id' => $customId,
                        'description' => '公司名称由【' . $originalData . '】更新为【' . $newData . '】',
                        'created_at' => now()
                    ];
                }

                if($model->isDirty('custom_email')){

                    $originalData = $model->getOriginal('custom_email');
                    $newData = $model->custom_email;

                    $insertData[] = [
                        'type' => self::TYPE_1,
                        'opt_type' => self::OPT_TYPE_1,
                        'admin_id' => $adminId,
                        'custom_id' => $customId,
                        'description' => '邮箱由【' . $originalData . '】更新为【' . $newData . '】',
                        'created_at' => now()
                    ];

                }

                if($model->isDirty('custom_phone')){

                    $originalData = $model->getOriginal('custom_phone');
                    $newData = $model->custom_phone;

                    $insertData[] = [
                        'type' => self::TYPE_1,
                        'opt_type' => self::OPT_TYPE_1,
                        'admin_id' => $adminId,
                        'custom_id' => $customId,
                        'description' => '电话号码由【' . $originalData . '】更新为【' . $newData . '】',
                        'created_at' => now()
                    ];
                }

                if($model->isDirty('phone_area_code')){

                    $originalData = $model->getOriginal('phone_area_code');
                    $newData = $model->phone_area_code;

                    $insertData[] = [
                        'type' => self::TYPE_1,
                        'opt_type' => self::OPT_TYPE_1,
                        'admin_id' => $adminId,
                        'custom_id' => $customId,
                        'description' => '电话区号由【' . $originalData . '】更新为【' . $newData . '】',
                        'created_at' => now()
                    ];
                }

                if($model->isDirty('group_id')){

                    $originalData = CustomGroup::where('id', $model->getOriginal('group_id'))->value('group_name');
                    $newData = CustomGroup::where('id', $model->group_id)->value('group_name');

                    $insertData[] = [
                        'type' => self::TYPE_1,
                        'opt_type' => self::OPT_TYPE_1,
                        'admin_id' => $adminId,
                        'custom_id' => $customId,
                        'description' => '客户分组由【' . $originalData . '】更新为【' . $newData . '】',
                        'created_at' => now()
                    ];
                }

                if($model->isDirty('commission_rate')){

                    $originalData = $model->getOriginal('commission_rate');
                    $newData = $model->commission_rate;

                    $insertData[] = [
                        'type' => self::TYPE_1,
                        'opt_type' => self::OPT_TYPE_1,
                        'admin_id' => $adminId,
                        'custom_id' => $customId,
                        'description' => '佣金比例由【' . $originalData . '%】更新为【' . $newData . '%】',
                        'created_at' => now()
                    ];
                }

                if($model->isDirty('commission_amount')){

                    $originalData = (float) $model->getOriginal('commission_amount');
                    $newData = (float) $model->commission_amount;

                    if($originalData != $newData){

                        $insertData[] = [
                            'type' => self::TYPE_1,
                            'opt_type' => self::OPT_TYPE_1,
                            'admin_id' => $adminId,
                            'custom_id' => $customId,
                            'description' => '佣金由【' . $originalData . '】更新为【' . $newData . '】',
                            'created_at' => now()
                        ];
                    }
                }

                if($model->isDirty('default_language')){

                    $originalData = $model->getOriginal('default_language');
                    $newData = $model->default_language;

                    $insertData[] = [
                        'type' => self::TYPE_1,
                        'opt_type' => self::OPT_TYPE_1,
                        'admin_id' => $adminId,
                        'custom_id' => $customId,
                        'description' => '默认语言由【' . $originalData . '】更新为【' . $newData . '】',
                        'created_at' => now()
                    ];
                }

                if($model->isDirty('remark')){

                    $originalData = $model->getOriginal('remark');
                    $newData = $model->remark;

                    $insertData[] = [
                        'type' => self::TYPE_1,
                        'opt_type' => self::OPT_TYPE_1,
                        'admin_id' => $adminId,
                        'custom_id' => $customId,
                        'description' => '备注由【' . $originalData . '】更新为【' . $newData . '】',
                        'created_at' => now()
                    ];
                }

                if($model->isDirty('customer_number')){

                    $originalData = $model->getOriginal('customer_number');
                    $newData = $model->customer_number;

                    $insertData[] = [
                        'type' => self::TYPE_1,
                        'opt_type' => self::OPT_TYPE_1,
                        'admin_id' => $adminId,
                        'custom_id' => $customId,
                        'description' => '客户号由【' . $originalData . '】更新为【' . $newData . '】',
                        'created_at' => now()
                    ];
                }

                if($model->isDirty('goods_once_price')){

                    $originalData = $model->getOriginal('goods_once_price');
                    $newData = $model->goods_once_price;

                    $insertData[] = [
                        'type' => self::TYPE_1,
                        'opt_type' => self::OPT_TYPE_1,
                        'admin_id' => $adminId,
                        'custom_id' => $customId,
                        'description' => '商品一口价由【' . Custom::$goodsOncePriceStatus[$originalData] . '】更新为【' . Custom::$goodsOncePriceStatus[$newData] . '】',
                        'created_at' => now()
                    ];
                }

                //自动支付单独判断

            }

            //调整信用额度
            if($optType == self::OPT_TYPE_2){

                $customId = $model->id;

                if($model->isDirty('credit_line')){
                    // 原信用额度
                    $originalData = (float) $model->getOriginal('credit_line');
                    // 调整后信用额度
                    $newData = (float) $model->credit_line;
                    // 调整额度
                    $adjustedLimit = (float) $model->adjusted_limit;
                    // 原账户余额
                    $originalBalance = $model->original_balance;
                    // 调整后账户余额
                    $newBalance = $model->balance['balance'] / 100;

                    if ($model->type === 1) {
                        $description = '信用额度 +【' . $adjustedLimit . '】，由原信用额度【' . $originalData . '】更新为【' . $newData . '】';
                        $description1 = '账户余额 +【' . $adjustedLimit . '】，由原账户余额【' . $originalBalance . '】更新为【' . $newBalance . '】';
                    } else {
                        $description = '信用额度 -【' . $adjustedLimit . '】，由原信用额度【' . $originalData . '】更新为【' . $newData . '】';
                        $description1 = '账户余额 -【' . $adjustedLimit . '】，由原账户余额【' . $originalBalance . '】更新为【' . $newBalance . '】';
                    }

                    if($originalData != $newData){
//                        $insertData[] = [
//                            'type' => self::TYPE_1,
//                            'opt_type' => self::OPT_TYPE_2,
//                            'admin_id' => $adminId,
//                            'custom_id' => $customId,
//                            'description' => $description,
//                            'created_at' => now()
//                        ];
                        $insertData = [
                            [
                                'type' => self::TYPE_1,
                                'opt_type' => self::OPT_TYPE_2,
                                'admin_id' => $adminId,
                                'custom_id' => $customId,
                                'description' => $description,
                                'created_at' => now()
                            ],
                            [
                                'type' => self::TYPE_1,
                                'opt_type' => self::OPT_TYPE_2,
                                'admin_id' => $adminId,
                                'custom_id' => $customId,
                                'description' => $description1,
                                'created_at' => now()
                            ]
                        ];
                    }
                }

//                if($model->isDirty('residual_credit')){
//
//                    $originalData = (float) $model->getOriginal('residual_credit');
//                    $newData = (float) $model->residual_credit;
//
//                    if($originalData != $newData){
//
//                        $insertData[] = [
//                            'type' => self::TYPE_1,
//                            'opt_type' => self::OPT_TYPE_2,
//                            'admin_id' => $adminId,
//                            'custom_id' => $customId,
//                            'description' => '剩余额度由【' . $originalData . '】更新为【' . $newData . '】',
//                            'created_at' => now()
//                        ];
//                    }
//                }
            }

            //手动扣款
            if($optType == self::OPT_TYPE_3){
                //单独处理
            }

            //禁止登录
            if($optType == self::OPT_TYPE_4){

                $customId = $model->id;

                $insertData[] = [
                    'type' => self::TYPE_1,
                    'opt_type' => self::OPT_TYPE_4,
                    'admin_id' => $adminId,
                    'custom_id' => $customId,
                    'description' => '账号被注销',
                    'created_at' => now()
                ];
            }

            //允许登录
            if($optType == self::OPT_TYPE_5){

                $customId = $model->id;

                $insertData[] = [
                    'type' => self::TYPE_1,
                    'opt_type' => self::OPT_TYPE_5,
                    'admin_id' => $adminId,
                    'custom_id' => $customId,
                    'description' => '设置允许登录',
                    'created_at' => now()
                ];
            }

            //分配员工
            if($optType == self::OPT_TYPE_6){

                $customId = $model->id;
                $name = $model->assign_admin_name;

                $insertData[] = [
                    'type' => self::TYPE_1,
                    'opt_type' => self::OPT_TYPE_6,
                    'admin_id' => $adminId,
                    'custom_id' => $customId,
                    'description' => '分配员工：' . $name,
                    'created_at' => now()
                ];
            }

            //登录客户账号
            if($optType == self::OPT_TYPE_7){

                $customId = $model->custom_id;

                if($model->isDirty('last_login_at')){

                    $insertData[] = [
                        'type' => self::TYPE_1,
                        'opt_type' => self::OPT_TYPE_7,
                        'admin_id' => $adminId,
                        'custom_id' => $customId,
                        'description' => '登录客户账号',
                        'created_at' => now()
                    ];
                }
            }


            //修改客户产品或运费报价配置
            if($optType == self::OPT_TYPE_8){

                $customId = $model->customer_id;

                if($model->isDirty('product_quote_default_profit_rate')){

                    $originalData = (float) $model->getOriginal('product_quote_default_profit_rate');
                    $newData = (float) $model->product_quote_default_profit_rate;

                    if($originalData != $newData){

                        $insertData[] = [
                            'type' => self::TYPE_1,
                            'opt_type' => self::OPT_TYPE_8,
                            'admin_id' => $adminId,
                            'custom_id' => $customId,
                            'description' => '产品报价利润率由【' . $originalData . '】更新为【' . $newData . '】',
                            'created_at' => now()
                        ];
                    }
                }

                if($model->isDirty('product_quote_default_fixed_amount')){

                    $originalData = (float) $model->getOriginal('product_quote_default_fixed_amount');
                    $newData = (float) $model->product_quote_default_fixed_amount;

                    if($originalData != $newData){

                        $insertData[] = [
                            'type' => self::TYPE_1,
                            'opt_type' => self::OPT_TYPE_8,
                            'admin_id' => $adminId,
                            'custom_id' => $customId,
                            'description' => '产品报价固定金额由【' . $originalData . '】更新为【' . $newData . '】',
                            'created_at' => now()
                        ];
                    }
                }

                if($model->isDirty('freight_quote_default_profit_rate')){

                    $originalData = (float) $model->getOriginal('freight_quote_default_profit_rate');
                    $newData = (float) $model->freight_quote_default_profit_rate;

                    if($originalData != $newData){

                        $insertData[] = [
                            'type' => self::TYPE_1,
                            'opt_type' => self::OPT_TYPE_8,
                            'admin_id' => $adminId,
                            'custom_id' => $customId,
                            'description' => '物流报价利润率由【' . $originalData . '】更新为【' . $newData . '】',
                            'created_at' => now()
                        ];
                    }
                }

                if($model->isDirty('freight_quote_default_fixed_amount')){

                    $originalData = (float) $model->getOriginal('freight_quote_default_fixed_amount');
                    $newData = (float) $model->freight_quote_default_fixed_amount;

                    if($originalData != $newData){

                        $insertData[] = [
                            'type' => self::TYPE_1,
                            'opt_type' => self::OPT_TYPE_8,
                            'admin_id' => $adminId,
                            'custom_id' => $customId,
                            'description' => '物流报价固定金额由【' . $originalData . '】更新为【' . $newData . '】',
                            'created_at' => now()
                        ];
                    }
                }
            }

            // 调整冻结额度
            if($optType == self::OPT_TYPE_9){
                $customId = $model->id;

                if($model->isDirty('frozen_limit')){
                    // 原冻结额度
                    $originalData = (float) $model->getOriginal('frozen_limit');
                    // 调整后冻结额度
                    $newData = (float) $model->frozen_limit;
                    // 调整额度
                    $adjustedLimit = (float) $model->adjusted_limit;
                    // 原账户余额
                    $originalBalance = $model->original_balance;
                    // 调整后账户余额
                    $newBalance = $model->balance['balance'] / 100;

                    if ($model->type === 1) {
                        $description = '冻结额度 +【' . $adjustedLimit . '】，由原冻结额度【' . $originalData . '】更新为【' . $newData . '】';
                        $description1 = '账户余额 -【' . $adjustedLimit . '】，由原账户余额【' . $originalBalance . '】更新为【' . $newBalance . '】';
                    } else {
                        $description = '冻结额度 -【' . $adjustedLimit . '】，由原冻结额度【' . $originalData . '】更新为【' . $newData . '】';
                        $description1 = '账户余额 +【' . $adjustedLimit . '】，由原账户余额【' . $originalBalance . '】更新为【' . $newBalance . '】';
                    }

                    if($originalData != $newData){
                        $insertData = [
                            [
                                'type' => self::TYPE_1,
                                'opt_type' => self::OPT_TYPE_9,
                                'admin_id' => $adminId,
                                'custom_id' => $customId,
                                'description' => $description,
                                'created_at' => now()
                            ],
                            [
                                'type' => self::TYPE_1,
                                'opt_type' => self::OPT_TYPE_9,
                                'admin_id' => $adminId,
                                'custom_id' => $customId,
                                'description' => $description1,
                                'created_at' => now()
                            ]
                        ];
                    }
                }
            }
        }

        return $insertData;
    }

    public static function optTypeList()
    {

        return [
            [
                'label' => '修改客户信息',
                'value' => self::OPT_TYPE_1
            ],
            [
                'label' => '手动扣款',
                'value' => self::OPT_TYPE_3
            ],
            [
                'label' => '禁止登录',
                'value' => self::OPT_TYPE_4
            ],
            // [
            //     'label' => '允许登录',
            //     'value' => self::OPT_TYPE_5
            // ],
            [
                'label' => '分配员工',
                'value' => self::OPT_TYPE_6
            ],
            [
                'label' => '登录客户账号',
                'value' => self::OPT_TYPE_7
            ],
            [
                'label' => '调整信用额度',
                'value' => self::OPT_TYPE_2
            ],
            [
                'label' => '调整冻结额度',
                'value' => self::OPT_TYPE_9
            ],
        ];
    }

    public static function addLog(int $type, int $subType, int $adminId, int $relationId, int $relationSubId, $description=null)
    {
        return self::query()->create([
            'type' => $type,
            'opt_type' => $subType,
            'admin_id' => $adminId,
            'relation_id' => $relationId,
            'relation_sub_id' => $relationSubId,
            'description' => $description,
        ]);
    }
}
