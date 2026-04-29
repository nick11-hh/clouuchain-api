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
        Schema::create('dsp_logistics_apply', function (Blueprint $table) {
            $table->id();
            $table->string('order_id')->default('')->comment('订单id');
            $table->tinyInteger('track_type')->nullable()->default(1)->comment('跟踪号类型：1-已产生跟踪号，2-等待后续更新跟踪号,3-不需要跟踪号');
            $table->string('remark')->default('')->comment('申请失败原因');
            $table->tinyInteger('sender_address')->default(0)->comment('0-不需要分配地址，1-需要分配地址');
            $table->string('agent_number')->nullable()->default('')->comment('代理单号');
            $table->string('way_bill_number')->nullable()->default('')->comment('运单号');
            $table->string('tracking_number')->nullable()->default('')->comment('跟踪号');
            $table->json('shipper_boxs')->nullable()->comment('箱子信息');
            $table->string('label_url')->default('')->comment('面单url');
            $table->integer('print')->default(0)->comment('打印面单次数');
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
            $table->index('order_id');
        });

        DB::statement("alter table `dsp_logistics_apply` comment '运单号申请信息反馈表' ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_logistics_apply');
    }
};
