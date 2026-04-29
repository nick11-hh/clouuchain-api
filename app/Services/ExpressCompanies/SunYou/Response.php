<?php


namespace App\Services\ExpressCompanies\SunYou;

class Response
{

    public $result;

    public function __construct($result)
    {
        $this->result = $result;
    }

    public function isSuccessful(): bool
    {
        return strtolower($this->result['ack']) === 'success';
    }

    public function result()
    {
        return $this->result['data'];
    }

}
