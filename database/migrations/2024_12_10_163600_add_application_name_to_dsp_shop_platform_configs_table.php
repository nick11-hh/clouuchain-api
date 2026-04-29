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
        Schema::table('dsp_shop_platform_configs', function (Blueprint $table) {
            $table->string('application_name')->nullable()->comment('应用名称');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_shop_platform_configs', function (Blueprint $table) {
            $table->dropColumn('application_name');
        });
    }
};
