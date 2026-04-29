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
        Schema::table('dsp_shop_order', function (Blueprint $table) {
            $table->string('logistics_provider_code')->default('')->comment('物流商编码');
            $table->timestamp('paymented_at')->nullable()->comment('付款时间');
            $table->timestamp('commited_at')->nullable()->comment('提交时间');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_shop_order', function (Blueprint $table) {
            $table->dropColumn(['logistics_provider_code', 'paymented_at', 'commited_at']);
        });
    }
};
