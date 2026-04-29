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
        Schema::create('dsp_user_balance', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_id')->index('dsp_user_balance_user_id_index')->comment('用户 id');
            $table->bigInteger('balance')->default(0)->comment('用户余额');
            $table->bigInteger('history_income')->default(0)->comment('历史收入');
            $table->bigInteger('history_outlay')->default(0)->comment('历史支出');

            $table->unique(['user_id'], 'dsp_user_balance_user_id_unique');
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
        Schema::dropIfExists('dsp_user_balance');
    }
};
