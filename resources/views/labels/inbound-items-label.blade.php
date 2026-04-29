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
                padding: 10px;
                /*border: 1px solid #d8d8d8;*/
            }
            .base-info {
                align-items: center;
                text-align: center;
                font-size: 12px;
            }
            .goods-name {
                height: 45px;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .spec-name {
                height: 15px;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .barcode {
                align-items: center;
                text-align: center;
                margin: 10px 0 5px 0;
                width: 100%;
                height: 50px;
            }
            .barcode img {
                width: 100%;
                height: 100%;
            }
        </style>
    </head>
    <body>
        <div class="main">
            <div class="base-info">
                <div class="goods-name">
                    产品名称：{{ $data['goods_name'] }}
                </div>
                <div class="spec-name">
                    规格：{{ $data['spec_name'] }}
                </div>
                <div>
                    SPU：{{ $data['spu'] }}
                </div>
                <div class="barcode">
                    <img alt="" src="{{ $data['sku_barcode'] }}" />
                </div>
                <div>
                    SKU：{{ $data['sku'] }}
                </div>
            </div>
        </div>
    </body>
</html>
