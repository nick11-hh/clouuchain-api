<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dsp_supplier_visits', function (Blueprint $table) {
            $table->id()->comment('主键ID');

            $table->unsignedBigInteger('supplier_id')->comment('供应商ID');
            $table->date('visit_date_start')->comment('拜访开始日期');
            $table->date('visit_date_end')->comment('拜访结束日期');
            $table->string('product')->nullable()->comment('产品');
            $table->text('key_results')->nullable()->comment('关键成果');

            $table->timestamps();
            $table->softDeletes();

            $table->comment('供应商拜访记录表');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dsp_supplier_visits');
    }
};
