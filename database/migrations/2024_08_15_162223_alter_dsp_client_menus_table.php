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
            $table->dropColumn(['name_cn', 'name_en']);
            $table->json('name_translate')->nullable()->comment('菜单名称-多语言');
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

        });
    }
};
