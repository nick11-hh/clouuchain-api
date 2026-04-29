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
        Schema::table('dsp_admin_operation_logs', function (Blueprint $table) {
            $table->bigInteger('relation_id')->nullable()->default(0)->comment('关联ID')->after('custom_id')->index('relation_id');
            $table->bigInteger('relation_sub_id')->nullable()->default(0)->comment('关联子ID')->after('relation_id')->index('relation_sub_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_admin_operation_logs', function (Blueprint $table) {
            $table->dropColumn('relation_id');
            $table->dropColumn('relation_sub_id');
        });
    }
};
