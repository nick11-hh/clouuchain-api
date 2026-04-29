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
        Schema::create('dsp_user_address_audit_records', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('address_id')->comment('地址ID');
            $table->string('remark', 191)->comment('备注');
            $table->string('operator', 191)->comment('操作人');
            $table->index('address_id');
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
        Schema::dropIfExists('dsp_user_address_audit_records');
    }
};
