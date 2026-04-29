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
        Schema::table('dsp_balance_recharges', function (Blueprint $table) {
            $table->bigInteger('check_admin_id')->default(0)->comment('核账管理员id');
            $table->json('check_images')->nullable()->comment('核账图片');
            $table->string('check_desc')->default('')->comment('核账描述');
            $table->tinyInteger('check_status')->default(0)->comment('核账状态 0未核账 1已核账');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_balance_recharges', function (Blueprint $table) {
            $table->dropColumn(['check_admin_id', 'check_images', 'check_desc', 'check_status']);
        });
    }
};
