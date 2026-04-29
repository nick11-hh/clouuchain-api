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
        Schema::create('dsp_localization', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('weight_name')->comment('重量单位');
            $table->string('weight_symbol')->comment('重量符号');
            $table->string('currency_name')->comment('货币单位');
            $table->string('currency_symbol')->comment('货币符号');
            $table->string('length_name')->nullable()->comment('长度单位名');
            $table->string('length_symbol')->nullable()->comment('长度单位符号');
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
        Schema::dropIfExists('dsp_localization');
    }
};
