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
        Schema::create('dsp_express_company', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->json('name')->comment('记录对应的快递公司名字');
            $table->string('num')->comment('记录对应的快递公司编码');
            $table->tinyInteger('status')->nullable()->default(1)->comment('状态0-禁用1-开启');
            $table->tinyInteger('auto_status')->nullable()->default(0)->comment('状态0-禁用1-开启');
            $table->integer('index')->nullable()->default(0)->comment('排序索引');
            $table->unique('num');
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
        Schema::dropIfExists('dsp_express_company');
    }
};
