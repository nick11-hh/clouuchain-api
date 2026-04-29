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
        Schema::table('dsp_system_config_operate_logs', function (Blueprint $table) {
            $table->text('content')->nullable()->comment('操作内容')->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_system_config_operate_logs', function (Blueprint $table) {
            $table->json('content')->nullable()->comment('操作内容')->change();
        });
    }
};
