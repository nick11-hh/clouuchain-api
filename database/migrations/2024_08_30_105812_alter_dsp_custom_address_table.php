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
        Schema::table('dsp_custom_address', function (Blueprint $table) {
            $table->string('name')->default('')->comment('姓名')->after('tax_id');
            $table->string('country_code')->default('')->comment('国家代码')->after('name');
            $table->string('province_code')->default('')->comment('省份代码')->after('country_code');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_custom_address', function (Blueprint $table) {
            $table->dropColumn(['name', 'country_code', 'province_code']);
        });
    }
};
