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
        Schema::table('dsp_admin_collect_goods_skus', function (Blueprint $table) {
            $table->float('length', 12)->default(0)->comment('长(cm)');
            $table->float('width', 12)->default(0)->comment('宽(cm)');
            $table->float('height', 12)->default(0)->comment('高(cm)');
            $table->float('weight', 12)->default(0)->comment('重量(g)');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_admin_collect_goods_skus', function (Blueprint $table) {
            $table->dropColumn([
               'length',
               'width',
               'height',
               'weight',
           ]);
        });
    }
};
