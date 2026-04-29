<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;

class Export implements FromArray
{
    public array $data;
    
    public function __construct($data)
    {
        $this->data = $data;
    }
    
    /**
    * @return array
    */
    public function array(): array
    {
        return $this->data;
    }
}
