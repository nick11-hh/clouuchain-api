<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        Schema::create('dsp_logistics_track_info', function (Blueprint $table) {
            $table->id();
            $table->string('order_id')->default('')->comment('订单id');
            $table->string('country_code')->default('')->comment('目的地国家简码');
            $table->string('waybill_number')->default('')->comment('运单号');
            $table->string('tracking_number')->default('')->comment('运单号');
            $table->string('provider_name')->default('')->comment('末端服务商名称');
            $table->string('provider_telephone')->default('')->comment('末端服务商联系方式');
            $table->string('provider_site')->default('')->comment('末端服务商官网');
            $table->string('pod')->default('')->comment('POD链接(妥投证明)信息(URL地址)');
            $table->string('created_by')->nullable()->comment('创建人');
            $table->tinyInteger('package_state')->default(0)->comment('包裹状态 0-未知，1-已提交 2-运输中 3-已签收，4-已收货，5-订单取消，6-投递失败，7-已退回');
            $table->integer('interval_days')->default(0)->comment('包裹签收天数');
            $table->json('order_tracking_details')->nullable()->comment('订单跟踪详情');
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
            $table->index('order_id');
        });

        DB::statement("alter table `dsp_logistics_track_info` comment '物流轨迹信息表' ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_logistics_track_info');
    }
};
