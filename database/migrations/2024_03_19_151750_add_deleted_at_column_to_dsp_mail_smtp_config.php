<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('dsp_mail_smtp_config', function (Blueprint $table) {
            $table->dropIndex('jiyun_mail_smtp_config_company_id_index');
            $table->dropColumn('company_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_mail_smtp_config', function (Blueprint $table) {
            $table->bigInteger('company_id');
            $table->index('jiyun_mail_smtp_config_company_id_index');
        });
    }
};
