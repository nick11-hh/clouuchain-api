<!DOCTYPE html>
<html>
<head>
    <title>成功</title>
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
        .pic {
            width: 198px;
            height: 188px;
            background-image: url('/images/auth-error.png');
            background-repeat: no-repeat;
            background-position: center;
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
    <div class="pic"></div>
    <h3 style="color: red">授权失败~</h3>
    <span><span id="time">10</span>s之后自动返回</span>
</div>

<script src="https://code.jquery.com/jquery-3.1.1.min.js"></script>
<script>
    const interval = setInterval(function() {
        let num = $('#time').text();
        num--;
        $("#time").text(num);
        if(num==0) {
            clearInterval(interval)
            window.location.href = 'https://{{ $url }}/configuration/alibabaAccount';
        }
    }, 1000)
</script>
</body>
</html>
