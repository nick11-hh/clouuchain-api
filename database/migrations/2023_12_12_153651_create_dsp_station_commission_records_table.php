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
        Schema::create('dsp_station_commission_records', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('station_id')->index('dsp_station_commission_records_station_id_index');
            $table->string('year', 191);
            $table->string('month', 191);
            $table->unsignedInteger('order_count')->default(0);
            $table->unsignedBigInteger('amount')->default(0);
            $table->unsignedTinyInteger('status')->default(0);
            $table->unsignedBigInteger('confirm_amount')->default(0);
            $table->string('payment_method', 191)->nullable();
            $table->json('payment_images')->nullable();
            $table->string('serial_no', 191)->default('');
            $table->string('operator', 191)->default('');
            $table->unique(['year', 'month', 'station_id'], 'month_unique');
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
        Schema::dropIfExists('dsp_station_commission_records');
    }
};
