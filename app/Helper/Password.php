<?php

namespace App\Helper;

class Password
{
    protected const PRIVATE_KEY = <<<KEY
-----BEGIN RSA PRIVATE KEY-----
Proc-Type: 4,ENCRYPTED
DEK-Info: AES-128-CBC,F32538500B16AADC2DF98CFFBAC18AA2

j6noLChDEGYifUdtdzjXqgq9MHERpcA4/5Tr4HWrnswUfrL4L5b9LePmfKo64uPd
F1oI5MSZ48uTTI3YZvpHP6JTdjLNWirzgw3gZS8pIxwzNjUnKfpQEQYIyJDny3MZ
8BuyU5b52VH2QZsf/24OR4J+KfMVmeZ322s37OD9jl/Oxbsg+A49Iv7Hof1rzvOs
/7nSl+G60lO85gU3gvqvc4G9XTWV/kgM6YK80JtOqkKGC58BFTM8Do5zUlhg0eWV
HGwlhr6ANe2AUpqHF3n60sj/UPh2WjvsqNlTjmPw3j+5aHcSv2x3gcyqe+OILCNy
sT1LeKO7Rbp186CBK0LAj8MFKJ8nD7fc3eTF0pHUTfgS60dxDkTOMO/o3QF4lCA8
aCGVDS9QO1WNwgarJDBYfDOmaOBpFqoSu5lTqy9mVZ1kSkHxeI9slKWFB4P/8DhP
IGGKj3HPcmrX//1zS9QGOSOv7rIqV/Dw31pxDCst0XhSqS5pQuFyEzCyNkalzRpC
TZsk0Fgjv7SbV+3l/LUr6UQYsj95BiwoJrq4SfmLyw99j/vXig5DmmILdNcdCwJx
ECqLZ0Mn+AGBz+zTTCwkuosG71GDMHv3gWHQkI1rJ1NnQyuFepwj46cLV53spL5g
syMPi7MBglA1t1ce8Akf50Exp1VBUVX8oWaaNybNvUhqhf/k+1t20jnSbp8L+paz
AxP4uGJ9584YauIlOlanQjF/xGpmbiohBzY84Gh8sLl+6T20pTzI4TCdpBimDDpr
jFwevZg8rM75EetIXqMzxheXcYjdGkm6NRzM8YYa8mGJ6HI5WcR6c0e9GHOObfQ1
XB3q46ffJf7YcHD8iBE5Bogv0Zq48O1Yvxhel6QnTpgIPZRmwEpeQK/SUHML9vsZ
GpHzjCrTQA5Sg0oKZmfvDJD5TO/VY4nzAvTgPp9U3eGO8ZYzQF9JrnSBtipdKApX
FWCnaqM5PMPMP26BY+Vg97YHDGZogjEg63tc+GIRY9JJdwu9gcAgNyuhVIzzYgmr
XV3oM+6HzCcae5Jg0FBuFVsidzDf+GP7IB4vmhTIy7zDi/TWmYiEo6BJ3xzJluIK
wpWjqaB3jMh+XV92U8iPJBl64n6G3t9PPA8y7rAg5cnjGKzLke2HRE6VrBC3rAtr
FPtuGNaoNN/jXyWZ5SfrZ9Wk4JCJ/TTyGPTh6OSlp4c0G1AHWmxlqo+rcsYvLUq6
jdjiWFoeUqqx5WP75VJCZN5h0pjRZySYMwlQD0tTJaicmAmH+WHNdOlfSI6YVvD8
nwKPJX/bNb6byHllYBbI9gXeD2E6NIUwR8+bv+2mwjxMpkqpSPndm6TxoGgdtjAT
NHglojVU6dXhT9ujdUDgzHTN1YRSiMwQGOcCTjvQSST3LgbCjRXiHp7pbzlDq9ow
upC5bY0JwRUPR+2DksXp5kz4jOHVKbqXe/VvL+i6JtBnuMMWPDbnrKgJ3LTuxfHB
mA2ntNSSXM9wwq/sMAkPlQHpdsDm7K2IkLmYapXxNSJR2bbHpyRDMp2NB3AXC5cJ
J+LZfJk5Pi/b7cU+xWZdv18bRgB+e7h2O4s9p06jmW4mhhpQIOOi1K/iS7q662IP
-----END RSA PRIVATE KEY-----
KEY;

    /**
     * @param string $password
     * @return mixed|string
     */
    public static function decrypt(string $password)
    {
        if (mb_strlen($password) < 30) {
            return $password;
        }

        $key = openssl_pkey_get_private(self::PRIVATE_KEY, config('jiyun.key_password'));

        if (openssl_private_decrypt(base64_decode($password), $decrypted, $key)) {
            return $decrypted;
        }

        return '';
    }
}
