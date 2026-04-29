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
        Schema::table('dsp_invoice_records', function (Blueprint $table) {
            $table->tinyInteger('file_type')->default(1)->comment('文件类型 1=pdf 2=word 3=excel');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_invoice_records', function (Blueprint $table) {
            $table->dropColumn(['file_type']);
        });
    }
};
