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
        Schema::table('dsp_balance_records', function (Blueprint $table) {
            $table->integer('charge_type_id')->default(0)->comment('费用类型ID source_type=9有值')->after('remark');
            $table->bigInteger('operate_admin_id')->default(0)->comment('操作管理员ID source_type=9有值')->after('remark');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_balance_records', function (Blueprint $table) {
            $table->dropColumn(['charge_type_id', 'operate_admin_id']);
        });
    }
};
