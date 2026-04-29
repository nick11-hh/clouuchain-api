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
        Schema::create('dsp_default_recharge_amount', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('amount')->default(0)->comment('金额');
            $table->bigInteger('complimentary_amount')->default(0)->comment('赠送金额');
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_default_recharge_amount');
    }
};
