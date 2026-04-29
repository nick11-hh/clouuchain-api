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
        Schema::create('dsp_sale_prices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 191);
            $table->unsignedTinyInteger('scope')->comment('适用范围');
            $table->timestamp('effect_at')->nullable()->comment('生效时间');
            $table->timestamp('expire_at')->nullable()->comment('失效时间');
            $table->bigInteger('index')->default(0)->comment('排序值');
            $table->boolean('enabled')->default(false)->comment('状态');
            $table->decimal('discount', 10, 3)->comment('折扣力度');
            $table->unsignedTinyInteger('discount_type')->default(1)->comment('折扣方式');
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
        Schema::dropIfExists('dsp_sale_prices');
    }
};
