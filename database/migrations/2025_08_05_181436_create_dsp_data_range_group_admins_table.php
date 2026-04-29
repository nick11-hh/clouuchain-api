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
        Schema::create('dsp_data_range_group_admins', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('data_range_group_id')->comment('分组id');
            $table->bigInteger('admin_id')->comment('员工id');
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
        Schema::dropIfExists('dsp_data_range_group_admins');
    }
};
