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
        Schema::table('dsp_client_menus', function (Blueprint $table) {
            $table->string('name_cn')->default('')->comment('中文菜单名称')->after('name');
            $table->tinyInteger('is_show')->default(1)->comment('是否显示：1-是 0-否')->after('enabled');
            $table->tinyInteger('level')->default(0)->comment('菜单级别')->after('is_show');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_client_menus', function (Blueprint $table) {
            $table->dropColumn(['name_en', 'is_show', 'level']);
        });
    }
};
