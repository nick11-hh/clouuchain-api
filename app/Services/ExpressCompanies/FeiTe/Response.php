<?php


namespace App\Services\ExpressCompanies\FeiTe;

class Response
{

    public $result;

    public function __construct($result)
    {
        $this->result = $result;
    }

    public function isSuccessful(): bool
    {
        return $this->result['Success'] == true;
    }

    public function statusSuccessful(): bool
    {
        $status = $this->result['status'] ?? $this->result['Status'];
        return $status == 1;
    }

    public function result()
    {
        return $this->result['data'] ?? $this->result['Data'];
    }

}
