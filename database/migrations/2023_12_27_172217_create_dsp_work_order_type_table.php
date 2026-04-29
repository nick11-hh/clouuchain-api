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
        Schema::create('dsp_work_order_type', function (Blueprint $table) {
            $table->comment('工单类型表');
            $table->bigIncrements('id');
            $table->bigInteger('company_id')->default(0)->comment('公司 ID');
            $table->string('name', 191)->default('')->comment('工单类型名称');
            $table->longText('remark')->nullable()->comment('备注');
            $table->integer('is_del')->default(0)->comment('是否删除 0-否 1-是');
            $table->integer('status')->default(1)->comment('工单状态 0-禁用 1-启用');
            $table->integer('type')->default(1)->comment('工单类型 1-自定义工单 2-系统工单');
            $table->bigInteger('creator_id')->nullable()->index('dsp_work_order_type_creator_id_index')->comment('默认创建人id');
            $table->bigInteger('assigned_by')->nullable()->index('dsp_work_order_type_assigned_by_index')->comment('默认指派人id');
            $table->json('copy_to')->nullable()->comment('抄送给');
            $table->integer('priority')->default(0)->index('dsp_work_order_type_priority_index')->comment('默认优先级 0-普通 1-紧急 2-非常紧急');
            $table->integer('service_type')->default(0)->comment('服务类型 0-自定义服务 1-拆包清点, 2-打包加固, 3-异常包裹, 4-异常订单, 5-高货值未购保险, 6-仓储超期提醒');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_work_order_type');
    }
};
