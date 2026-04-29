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
        Schema::create('dsp_company_prop', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->mediumInteger('type')->default(1)->comment('公司唯一属性对应的类型 预留 1 为分享图片');
            $table->string('prop')->comment('对应的公司唯一属性');
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
        Schema::dropIfExists('dsp_company_prop');
    }
};
