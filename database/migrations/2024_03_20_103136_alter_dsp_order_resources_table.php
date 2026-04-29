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
        Schema::table('dsp_order_resources', function (Blueprint $table) {
            $table->text('url')->nullable()->comment('商品链接')->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_quotation_record', function (Blueprint $table) {
            $table->string('url')->default('')->comment('商品链接')->change();
        });
    }
};
