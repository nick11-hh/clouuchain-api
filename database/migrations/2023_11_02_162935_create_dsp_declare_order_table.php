<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('dsp_declare_order', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('order_id')->default(0)->comment('订单ID');
            $table->bigInteger('user_id')->default(0)->comment('用户ID');
            $table->bigInteger('express_line_id')->default(0)->comment('线路ID');
            $table->bigInteger('country_id')->default(0)->comment('国家');
            $table->string('express_line_name')->default('')->comment('线路名称');
            $table->string('order_sn')->default('')->comment('订单号');
            $table->bigInteger('value')->default(0)->comment('总申报价值');
            $table->tinyInteger('push_type')->default(0)->comment('申报类型1-整单推送2-多箱推送');
            $table->tinyInteger('audit_status')->default(0)->comment('审核状态0-待审核1-已审核');
            $table->tinyInteger('third_status')->default(0)->comment('第三方对接状态0-待对接1-对接中2-对接成功3-对接失败');
            $table->tinyInteger('status')->default(0)->comment('状态0-待提交1-已提交');
            $table->string('tax_number')->default('')->comment('税号');
            $table->bigInteger('weight')->default(0)->comment('重量(g)');
            $table->string('hs_code')->default('')->comment('HS CODE');
            $table->json('data')->nullable();
            $table->bigInteger('agent_amount')->default(0)->comment('代收货款');
            $table->bigInteger('warehouse_id')->default(0)->comment('仓库ID');
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('dsp_declare_order_box_items', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('declare_box_id')->default(0)->comment('申报的箱号ID');
            $table->bigInteger('declare_item_id')->default(0)->comment('申报的明细ID');
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('dsp_declare_order_boxes', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('declare_id')->default(0)->comment('申报ID');
            $table->bigInteger('order_id')->default(0)->comment('订单ID');
            $table->bigInteger('box_id')->default(0)->comment('箱号ID');
            $table->string('box_sn')->default('')->comment('箱号');
            $table->string('tax_number')->default('')->comment('税号');
            $table->bigInteger('weight')->default(0)->comment('重量(g)');
            $table->bigInteger('agent_amount')->default(0)->comment('代收货款');
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('dsp_declare_order_items', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('declare_id')->default(0)->comment('申报ID');
            $table->string('cn_name')->default('')->comment('中文品名');
            $table->string('en_name')->default('')->comment('英文品名');
            $table->integer('quantity')->default(0)->comment('数量');
            $table->string('unit')->default('')->comment('单位');
            $table->bigInteger('unit_value')->default(0)->comment('单价=申报价值/数量');
            $table->bigInteger('value')->default(0)->comment('申报价值');
            $table->string('currency')->default('CNY')->comment('币种');
            $table->bigInteger('weight')->default(0)->comment('重量(g)');
            $table->string('sku')->default('')->comment('sku');
            $table->string('hs_code')->default('')->comment('hs_code');
            $table->string('material')->default('')->comment('材质');
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_declare_order');
        Schema::dropIfExists('dsp_declare_order_box_items');
        Schema::dropIfExists('dsp_declare_order_boxes');
        Schema::dropIfExists('dsp_declare_order_items');
    }
};
