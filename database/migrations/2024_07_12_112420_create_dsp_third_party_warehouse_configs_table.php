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
        Schema::create('dsp_third_party_warehouse_configs', function (Blueprint $table) {
            $table->id();
            $table->string('platform')->comment('平台 mabang');
            $table->string('app_key')->comment('应用key');
            $table->string('app_secret')->comment('应用密钥');
            $table->tinyInteger('status')->default(0)->comment('是否开启');
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
        Schema::dropIfExists('dsp_third_party_warehouse_configs');
    }
};
