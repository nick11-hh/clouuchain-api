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
        Schema::table('dsp_charge_types', function (Blueprint $table) {
            $table->json('name_translate')->nullable()->comment('名称翻译')->after('name');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_charge_types', function (Blueprint $table) {
            $table->dropColumn(['name_translate']);
        });
    }
};
