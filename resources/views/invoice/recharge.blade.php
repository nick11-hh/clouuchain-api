<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>充值发票</title>
</head>
<body>
<style>
    body{
        page-break-inside: avoid;
        min-height: 1280px;
    }
    h1,
    h5{
        margin: 0;
    }
    .box{
        color: #000;
        background-color: #fff;
    }
    .logo{
        display: -webkit-box;
        -webkit-box-align: center;
        -webkit-box-pack: justify;
        padding-bottom: 10px;
        border-bottom: 2px solid #999;
    }
    .logo img{
        width: 154px;
        height: 42px;
    }
    table{
        margin-top: 20px;
        border-collapse: collapse;
    }
    thead{
        text-align: left;
        border-top: 2px solid #999;
    }
    tr{
        padding: 10px !important;
        border-bottom: 2px solid #999;
    }
    .text-center{
        text-align: center !important;
    }
    .sub-total{
        font-size: 20px;
        font-weight: bold;
    }
    .flex-space-between {
        display: -webkit-box;
        -webkit-box-align: center;
        -webkit-box-pack: justify;
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
            <h3 >Seller</h3>
            <div>Issued <span style="color: #999;">{{ $data['created_at'] }}</span></div>
        </div>
        <!-- 供应商开票信息 -->
        @foreach($data['seller_info'] as $item)
            <div> {{ $item }}</div>
        @endforeach
    </div>
    <div>
        <!-- 卖家发票信息 -->
        <h3>Bill To</h3>
        <div>{{ $data['buyer_info']['custom_name'] }}</div>
        <div>{{ $data['buyer_info']['custom_phone'] }}</div>
        <div>{{ $data['buyer_info']['custom_email'] }}</div>
        <div>{{ $data['buyer_info']['custom_address'] }}</div>
    </div>
    <!-- 充值流水明细 -->
    <table style="width: 100%;" cellpadding="6">
        <thead>
        <tr>
            <th>Date</th>
            <th>Payment Method</th>
            <th class="text-center">Transaction ID</th>
            <th class="text-center">Amount</th>
        </tr>
        </thead>
        <tbody>
        @foreach($data['list'] as $value)
        <tr>
            <td>
                {{ $value['create_date'] }}
            </td>
            <td>
                {{ $value['payment_method'] }}
            </td>
            <td class="text-center">{{ $value['transaction_id'] }}</td>
            <td class="text-center">${{ $value['amount'] }}</td>
        </tr>
        @endforeach
        </tbody>
    </table>
    <div style="text-align: right;margin-top: 5px;">
        <div><span class="sub-total">Subtotal</span> ${{ $data['sub_total'] }}</div>
        <div><span class="sub-total">Payments</span> ${{ $data['confirm_payment'] }}</div>
        <div><span class="sub-total">Credit</span> ${{ $data['buyer_info']['residual_credit'] }}</div>
        <div><span class="sub-total">Outstanding Amount</span> ${{ bcsub($data['buyer_info']['credit_line'], $data['buyer_info']['residual_credit'], 2) }}</div>
    </div>
</div>
<script>

</script>
</body>
</html>
