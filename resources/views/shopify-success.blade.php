<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8" />
        <style>
            .box {
                width: 500px;
                height: 500px;
                margin: 0 auto;
                display: flex;
                justify-content: center;
                flex-direction: column;
                align-items: center;
            }
            .success-pic {
                width: 400px;
                height: 400px;
                background-image: url("/images/shopify_success.png");
            }
            .btn {
                width: 116px;
                height: 38px;
                background-color: #296DF1;
                color: #ffffff;
                border: none;
                display: flex;
                justify-content: center;
                align-items: center;
                cursor: pointer;
                text-decoration-line: none;
            }
        </style>
    </head>
    <body>
        <div class="box">
            <div class="success-pic"></div>
            <h3 style="color: #52c41a">授权成功</h3>
            <a href="{{ getClientDomain() . '/shop/shoplist' }}" class="btn">返回店铺列表</a>
            <span>将在<span id="time">10</span>s之后自动返回店铺列表</span>
        </div>

        <script src="https://code.jquery.com/jquery-3.1.1.min.js"></script>
        <script>
            const interval = setInterval(function() {
                let num = $('#time').text();
                num--;
                $("#time").text(num);
                if(num==0) {
                    clearInterval(interval)
                    window.location.href = {{ getClientDomain() . '/shop/shoplist' }};
                }
            }, 1000)
        </script>
    </body>
</html>
