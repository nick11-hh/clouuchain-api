<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('订单发票') }}</title>
</head>
<body>
<style>
    body {
        page-break-inside: avoid;
        min-height: 1280px;
    }

    h1,
    h5 {
        margin: 0;
    }

    .box {
        color: #000;
        background-color: #fff;
    }

    .logo {
        display: -webkit-box;
        -webkit-box-align: center;
        -webkit-box-pack: justify;
        padding-bottom: 10px;
        border-bottom: 2px solid #999;
    }

    .logo img {
        width: 154px;
        height: 42px;
    }

    table {
        margin-top: 20px;
        border-collapse: collapse;
    }

    thead {
        text-align: left;
        border-top: 2px solid #999;
    }

    tr {
        padding: 10px !important;
        border-bottom: 2px solid #999;
    }

    .text-center {
        text-align: center !important;
    }

    .sub-total {
        font-size: 20px;
        font-weight: bold;
    }

    .flex-space-between {
        display: -webkit-box;
        -webkit-box-align: center;
        -webkit-box-pack: justify;
    }

    .sku-item {
        display: -webkit-box;
    }

    .sku-item div {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        margin-right: 3px;
    }

    .sku-item div:nth-child(1) {
        width: 200px;
    }

    .sku-item div:nth-child(2) {
        width: 150px;
    }

    .sku-item div:nth-child(3) {
        width: 100px;
    }
</style>
<div class="box">
    <div class="logo">
        @if($data['client_menu_logo'] != '')
            <img src="{{ $data['client_menu_logo'] }}" class="img" alt=""/>
        @endif
        <div style="text-align: right">
            <h1>INVOICE</h1>
            <h5>No.{{ $data['invoice_no'] }}</h5>
        </div>
    </div>
    <div>
        <div class="flex-space-between">
            <h3>{{ __('卖方') }}</h3>
            <div>{{ __('发出') }} <span style="color: #999;">{{ $data['created_at'] }}</span></div>
        </div>
        <!-- 供应商开票信息 -->
        @foreach($data['seller_info'] as $item)
            <div> {{ $item }}</div>
        @endforeach
    </div>
    <div>
        <!-- 卖家发票信息 -->
        <h3>{{ __('账单抬头') }}</h3>
        <div>{{ $data['buyer_info']['invoice_address']['name'] }}</div>
        @if($data['buyer_info']['invoice_address']['phone_number'])
            <div >+{{ $data['buyer_info']['invoice_address']['phone_area_code'] }} {{ $data['buyer_info']['invoice_address']['phone_number'] }}</div>
        @endif

        @if($data['buyer_info']['invoice_address']['email'])
            <div >{{ $data['buyer_info']['invoice_address']['email'] }}</div>
        @endif

        <div>
            {{ $data['buyer_info']['invoice_address']['country'] }}
            {{ $data['buyer_info']['invoice_address']['province'] }}
            {{ $data['buyer_info']['invoice_address']['city'] }}
            {{ $data['buyer_info']['invoice_address']['address_detail'] }}
            {{ $data['buyer_info']['invoice_address']['postcode'] }}
        </div>

        @if($data['buyer_info']['invoice_address']['tax'])
            <div >{{ $data['buyer_info']['invoice_address']['tax'] }}</div>
        @endif
    </div>
    <!-- 订单记录明细 -->
    <table style="width: 100%;" cellpadding="6">
        <thead>
        <tr>
            <th>{{ __('参考编号') }}</th>
            <th>{{ __('项目') }}</th>
            <th class="text-center">{{ __('国家') }}</th>
            <th class="text-center">{{ __('总价') }}</th>
            <th class="text-center">{{ __('创建时间') }}</th>
        </tr>
        </thead>
        <tbody>
        @foreach($data['list'] as $value)
            <tr>
                <td>
                    <div>{{ $value['id'] }}</div>
                    <div>{{ $value['order_sn'] }}</div>
                </td>
                <td style="width: 500px">
                    @foreach($value['sku_list'] as $item)
                        <div class="sku-item">
                            <div>SKU：{{ $item['sku_id'] }}</div>
                            <div>{{ __('单价') }}: ${{ $item['price'] }}</div>
                            <div>{{ __('数量') }}: {{ $item['quantity'] }}</div>
                        </div>
                    @endforeach
                    <div>
                        <span>{{ __('物流费') }}: {{ $value['logistics_fee'] > 0 ? '$'. $value['logistics_fee'] : 'Free' }}</span>
                    </div>
                    @if($value['other_amount'] > 0)
                        <div>
                            <span>{{ __('其他费用') }}: ${{ $value['other_amount'] }}</span>
                        </div>
                    @endif
                    @if($value['additional_amount'] > 0)
                        <div>
                            <span>{{ __('额外费用') }}: ${{ $value['additional_amount'] }}</span>
                        </div>
                    @endif
                    @if($value['discount_amount'] > 0)
                        <div>
                            <span>{{ __('优惠金额') }}: $ -{{ $value['discount_amount'] }}</span>
                        </div>
                    @endif
                    @if($value['refund_amount'] > 0)
                        <div>
                            <span>{{ __('退款金额') }}: $ -{{ $value['refund_amount'] }}</span>
                        </div>
                    @endif
                </td>
                <td class="text-center">{{ $value['country_code'] }}</td>
                <td class="text-center">${{ $value['sub_total'] }}</td>
                <td class="text-center">{{ $value['created_at'] }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <div style="text-align: right;margin-top: 5px;">
        <div><span class="sub-total">{{ __('小计') }}</span> ${{ $data['sub_total'] }}</div>
    </div>
</div>
<script>

</script>
</body>
</html>
