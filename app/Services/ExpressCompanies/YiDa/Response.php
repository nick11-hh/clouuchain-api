<?php


namespace App\Services\ExpressCompanies\YiDa;

class Response
{

    public $result;

    public function __construct($result)
    {
        $this->result = $result;
    }

    public function isSuccessful(): bool
    {
        return $this->result['success'] == 1;
    }

    public function result()
    {
        return $this->result['data'];
    }

}
