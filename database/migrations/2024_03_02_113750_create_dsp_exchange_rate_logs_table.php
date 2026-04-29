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
        Schema::create('dsp_exchange_rate_logs', function (Blueprint $table) {
            $table->id();
            $table->integer('exchange_rate_id')->default(0)->comment('汇率表id');
            $table->integer('user_id')->default(0)->comment('操作人id');
            $table->tinyInteger('type')->default(0)->comment('操作类型：0-创建 1-修改');
            $table->decimal('old_exchange_rate', 10, 4)->default(0)->comment('原汇率');
            $table->decimal('new_exchange_rate', 10, 4)->default(0)->comment('新汇率');
            $table->index('exchange_rate_id');
            $table->softDeletes();
            $table->timestamps();
            $table->comment('汇率日志表');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_exchange_rate_logs');
    }
};
