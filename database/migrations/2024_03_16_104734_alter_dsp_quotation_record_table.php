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
        Schema::table('dsp_quotation_record', function (Blueprint $table) {
            $table->string('country')->default('')->comment('国家');
            $table->string('country_code')->default('')->comment('国家编号');
            $table->integer('logistics_provider')->default(0)->comment('国家编号');
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
            $table->dropColumn(['country', 'country_code', 'logistics_provider']);
        });
    }
};
