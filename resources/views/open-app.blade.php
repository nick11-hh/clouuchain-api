<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Open App</title>
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
    </style>
</head>
<body>
    <div class="box">
        <h3>Opening the App for you, please wait ...</h3>
        <p>If there is no response, <a href="{{ $url }}">click here to download the App</a></p >
    </div>

    <script src="https://code.jquery.com/jquery-3.1.1.min.js"></script>
    <script>
        window.onload = function () {
            setTimeout(function () {
                window.location.href = "{{ $url }}"
            }, 3000);
        }
    </script>
</body>
</html>
