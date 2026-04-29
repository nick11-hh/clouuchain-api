<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('dsp_shop_order', function (Blueprint $table) {
            $table->tinyInteger('is_refund')->default(0)->comment('是否退款：0-否 1是');
            $table->tinyInteger('is_disable')->default(0)->comment('禁止处理：0-否 1是');
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
            $table->dropColumn([
                                   'is_refund',
                                   'is_disable',
                               ]);
        });
    }
};
