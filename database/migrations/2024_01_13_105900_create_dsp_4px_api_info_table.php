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
        Schema::create('dsp_4px_api_info', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('order_id')->nullable()->comment('订单id')->unique('order_id');
            $table->string('ds_consignment_no')->nullable()->comment('直发委托单号');
            $table->string('4px_tracking_no')->nullable()->comment('4PX跟踪号');
            $table->string('label_barcode')->nullable()->comment('标签条码号');
            $table->string('ref_no')->nullable()->comment('客户单号/客户参考号');
            $table->string('logistics_channel_no')->nullable()->comment('物流渠道号码/末端服务商单号');
            $table->string('get_no_mode')->nullable()->comment('获取末端服务商单号的方式(创建订单时取号：C；仓库作业时取号：U)');
            $table->string('get_no_exmsg')->nullable()->comment('获取服务商单号抛的异常信息');
            $table->string('logistics_product_code')->nullable()->comment('运输方式代码');
            $table->string('logistics_product_name')->nullable()->comment('运输方式名称');
            $table->string('consignment_status')->nullable()->comment('委托单状态（草稿：D；已预报：P；已交接/已交货：V；库内作业中：H；已出库：C；已关闭：X；）');
            $table->string('insure_status')->nullable()->comment('投保状态（Y 已投保；N 未投保）');
            $table->string('insure_type')->nullable()->comment('投保类型');
            $table->string('has_check_oda')->nullable()->comment('是否进行ODA校验（Y：表示已经校验过；N：表示尚未校验）');
            $table->string('oda_result_sign')->nullable()->comment('ODA标识(偏远地址：Y ；非偏远地址：N)');
            $table->string('is_hold_sign')->nullable()->comment('拦截标识（申请拦截：Y ；拦截成功：S； 放行：N ）');
            $table->string('consignment_create_date')->nullable()->comment('创建委托单时间');
            $table->string('4px_inbound_date')->nullable()->comment('4PX收货时间');
            $table->string('4px_outbound_date')->nullable()->comment('4PX出库时间');
            $table->string('confirm_parcel_qty')->nullable()->comment('订单的实际包裹数');
            $table->string('confirm_parcel_weight')->nullable()->comment('订单实重（默认g）');
            $table->string('confirm_parcel_volume_weight')->nullable()->comment('订单体积重/材积重（默认g，若有才返回）');
            $table->string('confirm_parcel_charge_weight')->nullable()->comment('订单计费重（默认g）');
            $table->string('confirm_weight')->nullable()->comment('核实重量');
            $table->string('confirm_volume_weight')->nullable()->comment('包裹体积重（默认g）');
            $table->string('confirm_length')->nullable()->comment('核实包裹长');
            $table->string('confirm_width')->nullable()->comment('核实包裹宽');
            $table->string('confirm_high')->nullable()->comment('核实包裹高');
            $table->string('confirm_charge_weight')->nullable()->comment('包裹计费重（默认g）');
            $table->string('confirm_include_battery')->nullable()->comment('核实是否含电池（Y/N）');
            $table->string('confirm_battery_type')->nullable()->comment('核实带电类型（内置电池966：1；配套电池967：2）');
            $table->string('parcel_total_value_confirm')->nullable()->comment('核实包裹价值');
            $table->string('currency_code')->nullable()->comment('币别');

            $table->string('label_barcode2')->nullable()->comment('面单条码(①普通客户返回面单号(可能是4PX单号，也可能是服务商单号)；②特定客户且特定产品，直接返回物流服务商单号)');
            $table->string('logistics_label')->nullable()->comment('面单链接(①普通客户：返回4PX标准物流链接；若需打印报关标签&配货标签，也在此链接中；即多标签合并成了一个文件，返回一个链接②特定客户且特定产品，返回物流服务商标签链接)');
            $table->string('custom_label')->nullable()->comment('报关标签链接(暂时只支持特定客户且特定产品，单独返回需要找业务人员申请)');
            $table->string('package_label')->nullable()->comment('配货标签链接(暂时只支持特定客户且特定产品，单独返回需要找业务人员申请)');
            $table->string('invoice_label')->nullable()->comment('DHL发票链接(暂时只支持返回DHL发票文件，只有特定客户且特定产品，单独返回需要找业务人员申请)');
            $table->string('child_label_barcode')->nullable()->comment('子面单号');
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement("ALTER TABLE `dsp_4px_api_info` comment '递四方api返回信息表'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_4px_api_info');
    }
};
