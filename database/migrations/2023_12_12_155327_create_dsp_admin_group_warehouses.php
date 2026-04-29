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
        Schema::create('dsp_admin_group_warehouses', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('admin_group_id')->comment('admin分组id');
            $table->bigInteger('warehouse_id')->comment('国家id');
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
        Schema::dropIfExists('dsp_admin_group_warehouses');
    }
};
