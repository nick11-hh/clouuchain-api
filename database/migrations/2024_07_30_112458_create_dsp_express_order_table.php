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
        Schema::create('dsp_express_order', function (Blueprint $table) {
            $table->id();
            $table->string('package_sn')->default('')->comment('包裹号')->index();
            $table->integer('express_companies_id')->default(0)->comment('物流公司ID');
            $table->string('express_companies_code')->default('')->comment('物流公司编码');
            $table->integer('logistics_channel_id')->default(0)->comment('物流渠道ID')->index();
            $table->integer('express_line_id')->default(0)->comment('渠道路线ID')->index();
            $table->integer('warehouse_id')->default(0)->comment('仓库ID');
            $table->tinyInteger('status')->default(0)->comment('订单状态：0-申请中 1-申请成功 2-申请失败')->index();
            $table->bigInteger('package_weight')->nullable()->comment('包裹重量(g)');
            $table->bigInteger('length')->nullable()->comment('包裹长(cm)');
            $table->bigInteger('width')->nullable()->comment('包裹宽(cm)');
            $table->bigInteger('height')->nullable()->comment('包裹高(cm)');
            $table->integer('package_count')->default(1)->comment('包裹数量');
            $table->tinyInteger('change_type')->default(1)->comment('更换物流原因：1-首次申请 2-修改运单信息 3-订单拆分 4-订单合并 5-更换物流渠道 6-其他');
            $table->text('change_remark')->nullable()->comment('更换物流备注');

            $table->timestamps();
            $table->softDeletes();
            $table->comment('物流订单表');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_express_order');
    }
};
