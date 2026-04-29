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
            $table->tinyInteger('is_shipping')->default(0)->comment('平台是否交运');
            $table->text('send_fail_reason')->nullable()->comment('交运失败原因');
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
            $table->dropColumn('is_shipping');
            $table->dropColumn('send_fail_reason');
        });
    }
};
