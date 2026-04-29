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
        Schema::create('dsp_custom_config', function (Blueprint $table) {
            $table->id();
            $table->integer('custom_id')->default(0);
            $table->decimal('default_original_price_ratio')->default(1.00);
            $table->decimal('default_compare_original_price_ratio')->default(1.00);
            $table->softDeletes();
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
        Schema::dropIfExists('dsp_custom_config');
    }
};
