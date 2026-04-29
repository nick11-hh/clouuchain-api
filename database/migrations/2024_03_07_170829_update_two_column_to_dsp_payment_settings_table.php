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
        Schema::table('dsp_payment_settings', function (Blueprint $table) {
            $table->json('name')->comment('支付方式名称')->change();
            $table->json('remark')->nullable()->comment('备注信息')->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_payment_settings', function (Blueprint $table) {
            $table->string('name')->comment('支付方式名称')->change();
            $table->string('remark')->nullable()->comment('备注信息')->change();
        });
    }
};
