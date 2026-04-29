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
        Schema::create('dsp_warehouse_country', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('warehouse_id')->comment('记录对应的仓库 id');
            $table->bigInteger('country_id')->comment('记录对应的国家 id');
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
        Schema::dropIfExists('dsp_warehouse_country');
    }
};
