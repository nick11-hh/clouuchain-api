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
        Schema::table('dsp_custom_config', function (Blueprint $table) {
            $table->tinyInteger('is_auto_payment')->default(0)->comment('是否自动支付:1:是,0:否');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_custom_config', function (Blueprint $table) {
            $table->dropColumn('is_auto_payment');
        });
    }
};
