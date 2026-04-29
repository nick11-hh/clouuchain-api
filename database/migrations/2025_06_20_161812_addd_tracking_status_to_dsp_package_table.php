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
        Schema::table('dsp_package', function (Blueprint $table) {
            $table->string('tracking_status', 50)->default('NotFound')->comment('物流追踪状态')->index();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_package', function (Blueprint $table) {
            $table->dropColumn('tracking_status');
        });
    }
};
