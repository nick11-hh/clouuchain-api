<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('dsp_after_sales_work_order', function (Blueprint $table) {
            $table->renameColumn('claim_admin_id', 'handle_admin_id');

            $table->index('status');
            $table->index('handle_admin_id');
        });

        DB::statement("ALTER TABLE `dsp_after_sales_work_order` MODIFY COLUMN `attachment_url` json NULL COMMENT '上传的附件URL地址' AFTER `type`");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_after_sales_work_order', function (Blueprint $table) {
            $table->string('attachment_url')->default('')->change();
            $table->renameColumn('handle_admin_id', 'claim_admin_id');

            $table->dropIndex('status');
            $table->dropIndex('handle_admin_id');
        });
    }
};
