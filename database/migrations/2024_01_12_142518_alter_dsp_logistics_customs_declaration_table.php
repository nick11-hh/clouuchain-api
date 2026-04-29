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
        Schema::table('dsp_logistics_customs_declaration', function (Blueprint $table) {
            $table->string('material')->default('')->comment('材质');
            $table->string('use_to')->default('')->comment('用途');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_logistics_customs_declaration', function (Blueprint $table) {
            $table->dropColumn('material');
            $table->dropColumn('use_to');
        });
    }
};
