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
        Schema::create('dsp_shipments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 191)->default('')->comment('名字');
            $table->string('sn')->index('dsp_shipments_sn_index')->comment('发货单号');
            $table->string('source_station')->comment('发出站点');
            $table->bigInteger('destination_country_id')->comment('目的国');
            $table->unsignedTinyInteger('status')->comment('状态');
            $table->unsignedInteger('box_count')->default(1)->comment('箱子数量');
            $table->unsignedBigInteger('weight')->comment('重量');
            $table->unsignedBigInteger('volume')->comment('体积');
            $table->unsignedBigInteger('value')->comment('价值');
            $table->string('props')->nullable()->comment('商品属性');
            $table->string('remark')->default('')->comment('备注');
            $table->bigInteger('warehouse_id')->nullable()->comment('仓库ID');
            $table->timestamp('shipped_at')->nullable()->comment('发货时间');
            $table->string('logistics_sn', 191)->nullable()->index('dsp_shipments_logistics_sn_index')->comment('物流单号');
            $table->string('logistics_company', 191)->nullable()->comment('物流公司代码');
            $table->string('mawb', 191)->default('')->comment('MAWB');
            $table->integer('type')->default(0)->comment('高级选项类型');
            $table->integer('print_status')->default(0)->index('dsp_shipments_print_status_index')->comment('打印状态');
            $table->bigInteger('station_id')->nullable()->comment('站点ID');
            $table->string('image', 500)->nullable()->default('')->comment('图片');
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
        Schema::dropIfExists('dsp_shipments');
    }
};
