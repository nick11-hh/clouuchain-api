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
        Schema::create('dsp_exchange_rate', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('')->comment('货币名称');
            $table->string('currency_code')->default('')->comment('货币代码');
            $table->string('symbol')->default('')->comment('代币符号');
            $table->decimal('exchange_rate', 10, 4)->default(0)->comment('官方汇率');
            $table->decimal('custom_exchange_rate', 10, 4)->default(0)->comment('自定义汇率');
            $table->tinyInteger('is_main_currency')->default(0)->comment('是否主货币：0-否 1-是');
            $table->index('currency_code');
            $table->softDeletes();
            $table->timestamps();
            $table->comment('汇率表');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_exchange_rate');
    }
};
