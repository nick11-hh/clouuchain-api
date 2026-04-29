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
        Schema::create('dsp_third_withdraw_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('sn', 100)->nullable()->default('')->comment('单号');
            $table->text('content')->nullable()->comment('内容');
            $table->bigInteger('withdraw_id')->nullable()->default(0)->comment('提现记录ID');
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
        Schema::dropIfExists('dsp_third_withdraw_log');
    }
};
