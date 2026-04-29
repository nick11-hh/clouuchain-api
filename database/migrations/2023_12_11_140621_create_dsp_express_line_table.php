<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('dsp_express_line', function (Blueprint $table) {
            $table->id();
            $table->json('cn_name')->nullable()->comment('线路中文名');
            $table->json('en_name')->nullable()->comment('线路英文名');
            $table->tinyInteger('mode')->default(1)->comment('计费模式: 1首重续重模式');
            $table->tinyInteger('is_great_value')->default(0)->comment('是否超值线路');
            $table->bigInteger('icon_id')->default(0)->comment('快递线路图标ID');
            $table->bigInteger('first_weight')->default(0)->comment('首重 单位 g');
            $table->bigInteger('first_money')->default(0)->comment('首费 单位 分');
            $table->bigInteger('first_cost_money')->default(0)->comment('首重成本价格');
            $table->bigInteger('next_weight')->default(0)->comment('续重 单位 g');
            $table->bigInteger('next_money')->default(0)->comment('续费 单位 分');
            $table->bigInteger('next_cost_money')->default(0)->comment('首重成本价格');
            $table->mediumInteger('min_weight')->default(0)->comment('最小重 g');
            $table->bigInteger('max_weight')->default(0)->comment('最大重 g');
            $table->json('reference_time')->nullable()->comment('参考时效');
            $table->tinyInteger('enabled')->default(1)->comment('是否启用');
            $table->json('remark')->nullable()->comment('备注');
            $table->integer('factor')->default(600)->comment('体积重量系数');
            $table->tinyInteger('has_factor')->default(1)->comment('是否需要体积系数');
            $table->tinyInteger('extra_remark_enabled')->default(0)->comment('开启额外收录信息');
            $table->string('extra_remark_name')->default('')->comment('显示名称');
            $table->string('extra_remark_instruction')->default('')->comment('额外信息说明');
            $table->tinyInteger('need_clearance_code')->default(0)->comment('是否需要清关编码');
            $table->string('clearance_code_remark')->default('')->comment('清关编码备注说明');
            $table->tinyInteger('need_id_card')->default(0)->comment('是否需要身份证或者护照');
            $table->json('name')->nullable()->comment('线路名称');
            $table->tinyInteger('is_delivery')->default(0)->comment('是否货到付款线路');
            $table->bigInteger('default_pickup_station_id')->nullable()->comment('默认自提点');
            $table->tinyInteger('should_auto_delivery')->default(0)->comment('是否自动生成货到付款订单');
            $table->tinyInteger('ceil_weight')->default(0)->comment('重量不足调整为最小重量');
            $table->double('weight_rise')->default(0)->comment('重量向上取值');
            $table->tinyInteger('multi_boxes')->default(0)->comment('订单多箱单独计费');
            $table->double('multi_boxes_ceil')->default(0)->comment('多箱打包重量分别向上取值');
            $table->tinyInteger('need_personal_code')->default(0);
            $table->tinyInteger('is_hidden')->default(0)->comment('是否隐藏');
            $table->bigInteger('docking_type')->default(0)->comment('线路订单自动对接类型');
            $table->bigInteger('express_company_id')->default(0)->comment('对接快递物流公司ID');
            $table->tinyInteger('is_unique')->default(0)->comment('是否过滤');
            $table->tinyInteger('order_mode')->default(0);
            $table->bigInteger('multi_box_min_weight')->default(0)->comment('多箱打包时的最小重量');
            $table->tinyInteger('is_avg_weight')->default(0)->comment('是否半抛计费');
            $table->json('no_throw_condition')->nullable()->comment('免抛条件配置');
            $table->tinyInteger('rule_fee_mode')->default(0)->comment('渠道规则收费 0 同时收取 1 按最高项');
            $table->bigInteger('max_rule_fee')->default(0)->comment('所有渠道规则最高收费');
            $table->bigInteger('group_id')->default(0)->comment('分组ID 即渠道所属线路');
            $table->bigInteger('base_mode')->default(0)->comment('基础计费模式 0 按重量和体积重计费 1 按体积计费');
            $table->string('channel_code')->default('')->comment('渠道代码');
            $table->tinyInteger('range')->default(0);
            $table->tinyInteger('push_type')->default(1)->comment('推送方式1-整单推送2-多箱推送');
            $table->tinyInteger('third_push_now')->default(0)->comment('是否直接推送0-否1-是');
            $table->tinyInteger('require_size')->default(0)->comment('订单打包是否必填尺寸');
            $table->json('rule_remark')->nullable();
            $table->tinyInteger('weight_trans')->default(0)->comment('重量体积换算');
            $table->bigInteger('weight_factor')->default(1)->comment('重量体积换算系数');
            $table->tinyInteger('auth_target')->default(1)->comment('授权客户1-全体2-部分');
            $table->tinyInteger('channel_type')->default(1)->comment('对接方式1-单接口2-多接口');
            $table->string('tips')->default('')->comment('提示内容');
            $table->string('code')->default('')->comment('编码');
            $table->bigInteger('auto_sn_express_id')->default(0)->comment('自动订单号的快递公司ID');
            $table->integer('auto_sn_mode')->default(0)->comment('自动订单号的快递公司ID');
            $table->tinyInteger('prop_mode')->default(0)->comment('属性模式：0-存在模式 1-等于模式');
            $table->integer('payment_weight_int')->default(0)->comment('计费重量100g以下，取整');
            $table->integer('overweight_status')->default(0)->comment('超重提示状态');
            $table->bigInteger('overweight_weight')->default(0)->comment('超重重量');
            $table->json('overweight_remark')->nullable()->comment('超重提示备注');
            $table->index('group_id');
            $table->timestamps();
            $table->softDeletes();
        });
        DB::statement("alter table `dsp_express_line` comment '快递路线表' ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_express_line');
    }
};
