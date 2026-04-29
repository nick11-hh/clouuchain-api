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
        Schema::create('dsp_country', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->json('name');
            $table->string('cn_name')->comment('国家中文名');
            $table->string('en_name')->comment('国家英文名');
            $table->unsignedTinyInteger('index')->default(0)->comment('排序值');
            $table->string('code', 191)->nullable();
            $table->string('timezone', 191)->default('0001')->comment('国家对应的区号');
            $table->boolean('enabled')->default(true)->index('dsp_country_enabled_index')->comment('状态');
            $table->json('rgb_color')->nullable();
            $table->boolean('hot')->default(false)->index('dsp_country_hot_index')->comment('是否热门 0-否1-是');
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
        Schema::dropIfExists('dsp_country');
    }
};
