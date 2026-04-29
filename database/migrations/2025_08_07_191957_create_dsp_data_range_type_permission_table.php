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
        Schema::create('dsp_data_range_type_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('data_range_group_id', 50)->comment('权限范围id');
            $table->string('data_type', 50)->comment('权限数据类型');
            $table->string('range_type', 50)->comment('权限范围类型');
            $table->json('range_value')->nullable()->comment('权限范围值');
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
        Schema::dropIfExists('dsp_data_range_type_permissions');
    }
};
