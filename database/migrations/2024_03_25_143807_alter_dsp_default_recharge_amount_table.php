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
        Schema::table('dsp_default_recharge_amount', function (Blueprint $table) {
            $table->decimal('amount', 65, 2)->default(0)->comment('预设金额')->change();
            $table->decimal('complimentary_amount', 65, 2)->default(0)->comment('赠送金额')->change();
            $table->string('currency_code')->default('USD')->comment('货币编码');
            $table->string('currency_symbol')->default('$')->comment('货币符号');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_default_recharge_amount', function (Blueprint $table) {
            $table->bigInteger('amount')->default(0)->comment('预设金额')->change();
            $table->bigInteger('complimentary_amount')->default(0)->comment('赠送金额')->change();
            $table->dropColumn('currency_code');
            $table->dropColumn('currency_symbol');
        });
    }
};
