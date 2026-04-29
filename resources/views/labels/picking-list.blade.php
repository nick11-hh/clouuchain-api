<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>拣货单</title>
	<style>
		.container {
			width: 680px;
		}
		.header {
			display: flex;
			justify-content: space-between;
		}
		.header .barcode {
			width: 260px;
		}
		.header .title {}
        .header .title .title-name {
			font-size: 18px;
	        font-weight: 600;
        }
        .header .info {
	        font-size: 12px;
	        font-weight: 550;
        }
        .header .info .info-label {
			width: 82px;
	        display: inline-block;
        }


        .content {
	        margin-top: 20px;
        }
		.sku-table {
			width: 100%;
			font-size: 12px;
		}
        .sku-table th,td {
	        text-align: center;
        }
	</style>
</head>
<body>
	<div class="container">
		<div class="header">
			<div class="barcode">
				<img src="{{ $pickingData['barcode'] }}" alt="" style="width: 100%">
				<div style="text-align: center; font-weight: 600">{{ $pickingData['picking_sn']  }}</div>
			</div>
			<div class="title">
				<div class="title-name">Pick List</div>
			</div>
			<div class="info">
				<div><span class="info-label">Total Order:</span> {{ $pickingData['order_count']  }}</div>
				<div><span class="info-label">Total SKU:</span> {{ $pickingData['sku_count'] }}</div>
				<div><span class="info-label">Total Picks:</span> {{ $pickingData['all_quantity'] }}</div>
				<div><span class="info-label">Print Time:</span> {{ now() }}</div>
			</div>
		</div>
		<div class="content">
			<table class="sku-table" border="1" cellspacing="0">
				<tr>
					<th>Location</th>
                    <th>Owner</th>
                    <th>SKU</th>
					<th>Barcode</th>
					<th>Name</th>
					<th>QTY</th>
				</tr>
				@foreach($pickingData['sku_data'] as $sku)
					<tr>
						<td>{{ $sku['location_code'] }}</td>
                        <td>{{ $sku['owner'] }}</td>
                        <td>{{ $sku['sku'] }}</td>
						<td width="180px">
							<img src="{{ $sku['barcode'] }}" alt="" style="width: 80%">
						</td>
						<td>
{{--							<p>{{ $sku['goods_name'] }}</p>--}}
							<p>{{ $sku['spec_name'] }}</p>
						</td>
						<td>{{ $sku['quantity'] }}</td>
					</tr>
				@endforeach
			</table>
		</div>
	</div>
</body>
</html>
