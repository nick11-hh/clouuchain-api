<?php


namespace App\Services\ExpressCompanies\HaiOu;

class Response
{

    public $result;

    public function __construct($result)
    {
        $this->result = $result;
    }

    public function isSuccessful(): bool
    {
        return $this->result['ret'] === 1;
    }

    public function result()
    {
        return $this->result['data'] ?? [];
    }

}
