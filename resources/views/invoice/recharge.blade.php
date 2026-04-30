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
    .amount-line {
        font-size: 16px;
        font-weight: bold;
        margin-top: 6px;
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
            @foreach(($data['header_columns'] ?? []) as $hc)
                @php
                    $field = $hc['fieldName'] ?? '';
                    $center = in_array($field, ['serialId', 'amount'], true);
                @endphp
                <th class="{{ $center ? 'text-center' : '' }}">{{ $hc['displayName'] }}</th>
            @endforeach
        </tr>
        </thead>
        <tbody>
        @foreach(($data['rows'] ?? []) as $row)
            <tr>
                @foreach(($data['header_columns'] ?? []) as $hc)
                    @php
                        $field = $hc['fieldName'] ?? '';
                        $cell = (string)($row[$field] ?? '');
                        $fontStyle = 'font-family: SimSun; font-size: 11px;';
                        $alignClass = '';
                        if ($field === 'date') {
                            $fontStyle = 'font-family: Arial; font-size: 12px; font-weight: bold;';
                        } elseif ($field === 'serialId') {
                            $fontStyle = 'font-family: SimSun; font-size: 14px; font-weight: bold;';
                            $alignClass = 'text-center';
                        } elseif ($field === 'amount') {
                            $cell = '$' . $cell;
                            $alignClass = 'text-center';
                        }
                    @endphp
                    <td class="{{ $alignClass }}" style="{{ $fontStyle }}">{{ $cell }}</td>
                @endforeach
            </tr>
        @endforeach
        </tbody>
    </table>
    <div style="text-align: right;margin-top: 5px;">
        <div class="amount-line">Subtotal ${{ $data['sub_total'] }}</div>
        <div class="amount-line">Payments ${{ $data['confirm_payment'] }}</div>
        <div class="amount-line">Credit ${{ $data['buyer_info']['residual_credit'] }}</div>
    </div>
</div>
<script>

</script>
</body>
</html>
