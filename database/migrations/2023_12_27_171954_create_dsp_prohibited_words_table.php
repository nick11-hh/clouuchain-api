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
        Schema::create('dsp_prohibited_words', function (Blueprint $table) {
            $table->comment('违禁词配置表');
            $table->bigIncrements('id');
            $table->longText('prohibited_words')->nullable()->comment('违禁词, 以英文逗号(,)隔开');
            $table->integer('match_type')->default(0)->comment('匹配类型 0-全匹配 1-半匹配');
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
        Schema::dropIfExists('dsp_prohibited_words');
    }
};
