<?php
namespace App\Helper;


class Common
{
    public static function arrayToXml($data, $parentKey = '', $escape = true)
    {

        if (is_object($data)) {
            $data = get_object_vars($data);
        }
        $xml = '';
        foreach ($data as $key => $val) {
            if (is_null($val)) {
                $xml .= "<$key/>\n";
            } else {
                if (!is_numeric($key)) {
                    if (preg_match('/DG/', $key)) {
                        $key = preg_match('/\d+/', $key) ? substr($key, 0, -1) : $key;
                        if (preg_match('/\d+/', $key)) {
                            $key = preg_match('/\d+/', $key) ? substr($key, 0, -1) : $key;
                        }
                    }
                    is_array($val) && key($val) == '0' ? '' : $xml .= "<$key>";
                } else {
                    $xml .= "<$parentKey>";
                }
                if (!is_array($val) && $escape) {
                    $val = self::specialCharConvertedToEntityEncoding($val);
                }
                $xml .= (is_array($val) || is_object($val)) ? self::arrayToXml($val, $key, $escape) : $val;
                if (!is_numeric($key)) {
                    is_array($val) && key($val) == '0' ? '' : $xml .= "</$key>";
                } else {
                    $xml .= "</$parentKey>";
                }
            }
        }


        return $xml;
    }

    /**
     * @desc 特殊字符转实体编码
     * @param $string
     * @return string
     */
    public static function specialCharConvertedToEntityEncoding($string)
    {
        $from = array("&", "<", ">", "'", "\"");
        $to = array("&amp;", "&lt;", "&gt;", "&#039;", "&quot;");
        return str_replace($from, $to, $string);
    }
}
