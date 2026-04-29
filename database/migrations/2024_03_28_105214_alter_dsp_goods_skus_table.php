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
        Schema::table('dsp_goods_skus', function (Blueprint $table) {
            $table->string('sku_name')->nullable()->comment('自定义品名')->after('goods_id');
            $table->string('system_sku')->nullable()->comment('系统sku')->after('sku_id');
            $table->float('length', 12)->default(0)->comment('长(cm)');
            $table->float('width', 12)->default(0)->comment('宽(cm)');
            $table->float('height', 12)->default(0)->comment('高(cm)');
            $table->float('weight', 12)->default(0)->comment('重量(g)');
            $table->decimal('original_price', 10)->default(0)->comment('原价(CNY)')->after('sale_price');
            $table->text('sku_remark')->nullable()->comment('SKU备注');
            $table->integer('purchase_days')->default(0)->comment('采购交期(天)');
            $table->integer('min_purchase_quantity')->default(0)->comment('最低采购量');
            $table->bigInteger('purchase_buyer_id')->default(0)->comment('采购员ID dsp_admins.id');
            $table->text('purchase_remark')->nullable()->comment('采购备注');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_goods_skus', function (Blueprint $table) {
            $table->dropColumn([
                'sku_name',
                'length',
                'width',
                'height',
                'weight',
                'original_price',
                'sku_remark',
                'purchase_remark',
                'purchase_day',
                'min_purchase_quantity',
                'purchase_buyer_id'
            ]);
        });
    }
};
