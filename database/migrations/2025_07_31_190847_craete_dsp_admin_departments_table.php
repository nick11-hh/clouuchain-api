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
        Schema::create('dsp_admin_departments', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('admin_id')->comment('员工id');
            $table->bigInteger('department_id')->comment('部门id');
            $table->tinyInteger('is_main')->comment('是否为部门负责人');
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
        Schema::dropIfExists('dsp_admin_departments');
    }
};
