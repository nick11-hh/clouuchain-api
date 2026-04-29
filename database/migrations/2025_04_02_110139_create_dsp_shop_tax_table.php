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
        Schema::create('dsp_shop_tax', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('shop_id')->comment('店铺id');
            $table->tinyInteger('tax_region')->comment('税收地区，1 英国VAT 2 欧盟VAT 3 寄件人税号');
            $table->string('tax_type')->comment('税号类型');
            $table->string('tax_number')->comment('税号');
            $table->string('country_code')->nullable()->comment('税收国家，寄件人税号时须填');
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
        Schema::dropIfExists('dsp_shop_tax');
    }
};
