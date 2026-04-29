<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        Schema::table('dsp_shop', function (Blueprint $table) {
            // 删除可能存在的旧索引
            $schemaManager = DB::getDoctrineSchemaManager();
            $indexesFound = $schemaManager->listTableIndexes('dsp_shop');
            
            if (array_key_exists('shop_url_customer_id_index', $indexesFound)) {
                $table->dropIndex('shop_url_customer_id_index');
            }
            
            // 添加唯一索引：确保同一店铺网址 + 客户 ID 的唯一性
            // 这样可以防止同一客户对同一店铺重复授权
            $table->unique(['shop_url', 'customer_id'], 'uniq_shop_url_customer');
            
            // 添加索引：用于快速查找已被授权的店铺（不论哪个客户）
            $table->index(['shop_url', 'status'], 'idx_shop_url_status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_shop', function (Blueprint $table) {
            $table->dropUnique('uniq_shop_url_customer');
            $table->dropIndex('idx_shop_url_status');
        });
    }
};
