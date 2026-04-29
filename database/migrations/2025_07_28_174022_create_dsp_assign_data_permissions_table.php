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
        Schema::create('dsp_assign_data_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('admin_id')->comment('员工账号id');
            $table->string('permission_type')->comment('权限类型');
            $table->bigInteger('permission_id')->comment('权限id');
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
        Schema::dropIfExists('dsp_assign_data_permissions');
    }
};
