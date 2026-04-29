<?php

namespace App\Services;

use Picqer\Barcode\BarcodeGenerator;
use Picqer\Barcode\BarcodeGeneratorJPG;
use Picqer\Barcode\BarcodeGeneratorPNG;

class BarcodeService
{

    public static function generateCode($barcode, $type = BarcodeGenerator::TYPE_CODE_128)
    {
        $generate = new BarcodeGeneratorJPG();
        $code = $generate->getBarcode($barcode, $type, 1);
        return 'data:image/jpg;base64,' . base64_encode($code);
    }

}
