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
        Schema::table('dsp_third_party_warehouse_configs', function (Blueprint $table) {
            $table->tinyInteger('push_product')->default(0)->nullable()->comment('推送产品 0关闭 1开启')->after('status');
            $table->tinyInteger('product_update_sync')->default(0)->nullable()->comment('产品变更同步 0关闭 1开启')->after('push_product');
            $table->string('relation_warehouse_id', 300)->default(null)->nullable()->comment('产品关联仓库')->after('product_update_sync');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_third_party_warehouse_configs', function (Blueprint $table) {
            $table->dropColumn('push_product');
            $table->dropColumn('product_update_sync');
            $table->dropColumn('relation_warehouse_id');
        });
    }
};
