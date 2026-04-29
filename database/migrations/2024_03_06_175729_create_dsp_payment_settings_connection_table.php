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
        Schema::create('dsp_payment_settings_connection', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('payment_settings_id')->comment('支付配置id');
            $table->json('name')->comment('提示名称');
            $table->json('content')->comment('提示内容');
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_payment_settings_connection');
    }
};
