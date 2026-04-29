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
        Schema::create('dsp_warehouse_address', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->json('warehouse_name')->nullable()->comment('仓库名');
            $table->json('receiver_name')->nullable()->comment('仓库姓名 -- 仓库');
            $table->string('timezone')->default('0031')->comment('联系电话时区');
            $table->string('phone')->comment('仓库电话');
            $table->string('postcode')->comment('仓库邮编');
            $table->json('address')->nullable()->comment('仓库地址');
            $table->string('code')->nullable()->comment('专属编号--预留');
            $table->json('tips')->nullable()->comment('温馨提示');
            $table->decimal('lat', 10, 7)->nullable()->comment('仓库地址纬度');
            $table->decimal('lng', 10, 7)->nullable()->comment('仓库地址经度');
            $table->unsignedTinyInteger('auto_location')->default(0)->comment('是否开启自动货位功能');
            $table->string('short_address', 191)->nullable()->comment('短地址');
            $table->integer('custom_sort')->default(0)->comment('仓库自定义排序');
            $table->boolean('enabled')->default(true);
            $table->boolean('mode')->default(false)->comment('地址模式 0 单段地址 1 多级地址');
            $table->json('province')->nullable();
            $table->json('city')->nullable();
            $table->json('district')->nullable();
            $table->unsignedInteger('free_store_days')->nullable()->comment('免费仓储期');
            $table->unsignedBigInteger('store_fee')->default(0)->comment('仓储费用');
            $table->unsignedTinyInteger('off_shelf_status')->default(0)->comment('货位释放时间');
            $table->unsignedTinyInteger('big_rule')->default(0)->comment('货位限制尺寸方式');
            $table->json('location_size')->nullable()->comment('货位限制尺寸');
            $table->tinyInteger('is_stg')->default(0)->comment('同行货仓库0-否1-是');
            $table->boolean('custom_location')->default(false)->comment('开启自动添加自定义货位');
            $table->bigInteger('location_weight')->nullable()->comment('货位限制重量');
            $table->boolean('size_rule')->default(false)->comment('大货位限制尺寸判断');
            $table->boolean('weight_rule')->default(false)->comment('大货位限制重量判断');
            $table->boolean('show')->default(true)->comment('客户端是否显示');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_warehouse_address');
    }
};
