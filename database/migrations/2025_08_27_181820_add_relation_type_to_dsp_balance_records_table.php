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
            $table->string('relation_type')->nullable()->comment('关联的模型')->after('relation_id');
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
            $table->dropColumn('relation_type');
        });
    }
};
