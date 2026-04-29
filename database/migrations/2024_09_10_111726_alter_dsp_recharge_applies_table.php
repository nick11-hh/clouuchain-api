<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('dsp_recharge_applies', function (Blueprint $table) {
            $table->integer('revocation_operator')->default(0)->comment('撤销操作员');
            $table->string('revocation_remark')->default('')->comment('撤销备注');
            $table->timestamp('revocation_at')->nullable()->comment('撤销时间');
            $table->string('revocation_no')->default('')->comment('撤销流水号');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_recharge_applies', function (Blueprint $table) {
            $table->dropColumn([
                                   'revocation_operator_id',
                                   'is_revocation',
                                   'revocation_remark',
                                   'revocation_at',
                                   'revocation_no',
                               ]);
        });
    }
};
