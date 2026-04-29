<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8" />
        <style>
            .main {
                width: 100%;
                height: 100%;
                margin: 0 auto;
                box-sizing: border-box;
            }
            h3 {
                padding: 0;
                margin: 0 0 10px 0;
                height: 30px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .base-info {
                display: flex;
                border-bottom: 1px solid #d8d8d8;
                padding-bottom: 10px;
            }
            .info {
                width: 50%;
            }
            .barcode {
                width: 60%;
                display: flex;
                flex-direction: column;
                align-items: center;
            }
            .title, .value {
                font-size: 13px;
            }
            .title {
                width: 65px;
                display: inline-block;
            }
            .print-time {
                font-size: 14px;
                margin-bottom: 5px;
            }
            .order-code {
                margin-top: 5px;
                font-size: 14px;
            }
            .order-info {

            }
            .order-info-header {
                display: flex;
                padding: 10px 0;
            }
            .sku-num, .total-num {
                font-size: 14px;
            }
            .total-num {
                margin-left: 15px;
            }
            table {
                width: 100%;
                border-collapse: collapse;
            }
            th {
                font-size: 14px;
            }
            table, th, td {
                border: 1px solid #d8d8d8;
            }
        </style>
    </head>
    <body>
        <div class="main">
            <h3>发货单</h3>
            <div class="base-info">
                <div class="info">
                    <div class="row">
                        <label class="title">平台序列号</label>
                        <label class="value">{{empty($data['name']) ? '-' : $data['name']}}</label>
                    </div>
                    <div class="row">
                        <label class="title">平台单号</label>
                        <label class="value">{{empty($data['order_id']) ? '' : $data['order_id']}}</label>
                    </div>
                    <div class="row">
                        <label class="title">国际运单</label>
                        <label class="value">{{empty($data['way_bill_number']) ? '-' : $data['way_bill_number']}}</label>
                    </div>
                    <div class="row">
                        <label class="title">备注</label>
                        <label class="value">{{ empty($data['remark']) ? '-' :  $data['remark']}}</label>
                    </div>
                </div>
                <div class="barcode">
                    <div class="print-time">打印时间：{{ $data['print_time'] }}</div>
                    <img src="{{ $data['brcode'] }}" />
                    <div class="order-code">{{ $data['order_id'] }}</div>
                </div>
            </div>
            <div class="order-info">
                <div class="order-info-header">
                    <div class="sku-num">SKU数：{{$data['sku_num']}}</div>
                    <div class="total-num">总数：{{$data['total']}}</div>
                </div>
                <table>
                    <thead>
                        <th>图片</th>
                        <th>产品名称</th>
                        <th>规格</th>
                        <th>数量</th>
                        <th>库位</th>
                    </thead>
                    <tbody>
                        @foreach($data['items'] as $goods)
                        <tr>
                            <td style="width: 80px;text-align: center">
                                @if($goods['img'])
                                <img style="width: 60px" src="{{$goods['img']}}" />
                                @endif
                            </td>
                            <td style="width: 150px;font-size: 12px;text-align: center">{{ $goods['name'] }}</td>
                            <td style="width: 55px;font-size: 12px;text-align: center">{{ $goods['spec'] }}</td>
                            <td style="width: 45px;font-size: 12px;text-align: center">{{ $goods['quantity'] }}</td>
                            <td></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </body>
</html>
