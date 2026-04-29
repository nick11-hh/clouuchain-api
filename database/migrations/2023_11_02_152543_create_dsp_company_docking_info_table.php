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
        Schema::create('dsp_company_docking_info', function (Blueprint $table) {
            $table->id();
            $table->integer('type')->default(0)->comment('物流商类型');
            $table->json('info')->nullable()->comment('物流配置信息');
            $table->json('data')->nullable();
            $table->bigInteger('share_id')->nullable();
            $table->json('sender')->nullable();
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
        Schema::dropIfExists('dsp_company_docking_info');
    }
};
