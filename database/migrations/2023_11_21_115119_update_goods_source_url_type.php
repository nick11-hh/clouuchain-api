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
        Schema::table('dsp_goods', function (Blueprint $table) {
            $table->text('purchase_url')->nullable()->change();
        });
        Schema::table('dsp_client_goods', function (Blueprint $table) {
            $table->text('source_url')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_goods', function (Blueprint $table) {
            $table->string('purchase_url')->nullable()->change();
        });
        Schema::table('dsp_client_goods', function (Blueprint $table) {
            $table->string('source_url')->nullable()->change();
        });
    }
};
