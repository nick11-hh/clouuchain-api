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
        Schema::create('dsp_platform_virtual_skus', function (Blueprint $table) {
            $table->id();
            $table->string('platform')->comment('平台')->index();
            $table->string('platform_variant_id')->comment('平台sku id')->index();
            $table->bigInteger('staff_id')->default(0)->comment('员工id')->index();
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
        Schema::dropIfExists('dsp_platform_virtual_skus');
    }
};
