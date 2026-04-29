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
        Schema::create('dsp_express_line_regions', function (Blueprint $table) {
            $table->comment('路线-渠道-分区');
            $table->bigIncrements('id');
            $table->bigInteger('express_line_id');
            $table->json('name');
            $table->json('reference_time');
            $table->boolean('enabled')->default(false)->comment('开启状态');
            $table->unsignedTinyInteger('type')->default(1)->comment('分区类型');
            $table->bigInteger('country_id')->nullable()->comment('邮编分区国家 可为空');
            $table->bigInteger('index')->default(0)->comment('排序');
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
        Schema::dropIfExists('dsp_express_line_regions');
    }
};
