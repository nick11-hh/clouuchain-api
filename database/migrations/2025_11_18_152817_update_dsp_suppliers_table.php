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
        Schema::table('dsp_suppliers', function (Blueprint $table) {
            $table->string('supplier_qualification')->default('')->comment('供应商资质（Mate自编）');
            $table->string('main_category')->default('')->comment('主营类目');
            $table->text('certifications')->nullable()->comment('认证资质');
            $table->string('shipping_address')->default(0)->comment('发货地址');
            $table->string('payment_terms')->default('')->comment('付款条款');
            $table->tinyInteger('cooperation_level')->default(0)->comment('供应商配合度: 0=满意,1=一般,2=差');
            $table->string('service_and_after_sales')->default('')->comment('供应商服务及售后');
            $table->integer('tax_point')->default(0)->comment('税点');
            $table->integer('face_value')->default(0)->comment('票面');
            $table->tinyInteger('overall_rating')->default(0)->comment('综合评分: 0=满意,1=一般,2=差');
            $table->string('person_in_charge', 100)->default('')->comment('负责人');
            $table->string('person_in_charge_role', 50)->default('')->comment('负责人职务');
            $table->string('contact_info')->default('')->comment('联系方式');
            $table->text('factory_images')->nullable()->comment('工厂图片');
            $table->integer('factory_scale')->default(0)->comment('工厂规模');
            $table->integer('developer')->default(0)->comment('开发人');
            $table->integer('visit_times')->default(0)->comment('累计拜访次数');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_suppliers', function (Blueprint $table) {
            $table->dropColumn([
                'supplier_qualification',
                'main_category',
                'certifications',
                'shipping_address',
                'payment_terms',
                'cooperation_level',
                'service_and_after_sales',
                'tax_point',
                'face_value',
                'overall_rating',
                'person_in_charge',
                'person_in_charge_role',
                'contact_info',
                'factory_images',
                'factory_scale',
                'developer',
                'visit_times'
            ]);
        });
    }
};
