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
        Schema::create('dsp_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('')->comment('权限名称');
            $table->bigInteger('group_id')->default(0)->comment('权限组ID');
            $table->string('http_method')->default('')->comment('路由方法');
            $table->string('http_path')->default('')->comment('路由路径');
            $table->timestamp('deleted_at')->nullable();
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
        Schema::dropIfExists('dsp_permissions');
    }
};
