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
        Schema::create('dsp_paypal_payment', function (Blueprint $table) {
            $table->id();
            $table->string('account')->default('')->comment('账户');
            $table->string('client_id')->default('')->comment('客户端 id');
            $table->string('secret')->default('')->comment('秘钥');
            $table->string('notify_url')->nullable()->comment('回调地址,预留');
            $table->tinyInteger('enabled')->default(1)->comment('是否启用');
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
        Schema::dropIfExists('dsp_paypal_payment');
    }
};
