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
        Schema::table('dsp_shop_order', function (Blueprint $table) {
            $table->tinyInteger('change_status')->default(0)->comment('换单状态：0-获取新单号 1-待打单 2-发货失败 3-发货成功');
            $table->integer('change_logistics_provider')->default(0)->comment('更换后的物流渠道');
            $table->decimal('change_logistics_fee', 10, 2)->default(0)->comment('更换后的物流费用');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_shop_order', function (Blueprint $table) {
            $table->dropColumn(['change_status', 'change_logistics_provider', 'change_logistics_fee']);
        });
    }
};
