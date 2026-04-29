<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLandlordTenantsTable extends Migration
{
    public function up()
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('client_domain')->unique()->comment('客户端域名');
            $table->string('admin_domain')->unique()->comment('管理端域名');
            $table->string('uuid')->unique()->comment('用户唯一id标识');
            $table->string('database')->comment('数据库');
            $table->json('mysql_info')->nullable()->comment('数据详细信息');
            $table->tinyInteger('status')->default(1)->comment('房客状态 1 正常  0 禁用');
            $table->timestamps();
        });
    }
}
