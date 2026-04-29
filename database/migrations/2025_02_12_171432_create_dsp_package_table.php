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
        Schema::dropIfExists('dsp_package');
        Schema::create('dsp_package', function (Blueprint $table) {
            $table->id();
            $table->string('package_sn')->comment('包裹编号');
            $table->bigInteger('express_companies_id')->comment('物流公司id');
            $table->string('express_companies_code')->comment('物流公司code');
            $table->string('express_channel_code')->comment('物流路线code');
            $table->tinyInteger('sku_status')->default(0)->comment('0-单规格单件 1-单规格多件 2-多规格多件');
            $table->tinyInteger('status')->comment('状态');
            $table->string('logistics_status')->default('wait')->comment('运单申请状态');
            $table->string('stock_status')->default('wait')->comment('配货状态');
            $table->tinyInteger('split_merge_status')->default(1)->comment('拆包合包状态');
            $table->integer('weight')->default(0)->comment('重量');
            $table->integer('length')->default(0)->comment('长');
            $table->integer('width')->default(0)->comment('宽');
            $table->integer('height')->default(0)->comment('高');
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
        Schema::dropIfExists('dsp_package');
    }
};
