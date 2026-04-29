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
        Schema::create('dsp_data_range_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('数据范围组名称');
            $table->string('description')->nullable()->comment('数据范围组描述');
            $table->bigInteger('creator_id')->comment('创建人');
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
        Schema::dropIfExists('dsp_data_range_groups');
    }
};
