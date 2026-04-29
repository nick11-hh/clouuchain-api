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
        Schema::table('dsp_customs', function (Blueprint $table) {
            $table->decimal('credit_line', 12, 2)->default(0)->comment('信用额度');
            $table->decimal('residual_credit', 12, 2)->default(0)->comment('剩余额度');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_customs', function (Blueprint $table) {
            $table->dropColumn(['credit_line', 'residual_credit']);
        });
    }
};
