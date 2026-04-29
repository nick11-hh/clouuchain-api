<?php

namespace App\Services\Base;

use Milon\Barcode\Facades\DNS1DFacade;

class BarcodeService
{
    public static function getBarcodeBase64($barcode, $w = 1.8, $h = 50)
    {
        $code = DNS1DFacade::getBarcodePng($barcode, 'C128', $w, $h);
        return 'data:image/png;base64,' . $code;
    }
}
