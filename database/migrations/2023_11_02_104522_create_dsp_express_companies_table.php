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
        Schema::create('dsp_express_companies', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('')->comment('物流商名称');
            $table->string('freight_forwarder')->default('')->comment('贷代名称');
            $table->string('customer_code')->default('')->comment('客户编号');
            $table->string('api_secret')->default('')->comment('ApiSecret');
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_express_companies');
    }
};
