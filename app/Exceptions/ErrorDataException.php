<?php

namespace App\Exceptions;

use Exception;

class ErrorDataException extends Exception
{
    protected mixed $errorData = [];

    public function __construct($errorData = [])
    {
        $this->errorData = $errorData;
    }

    public function getErrorData()
    {
        return $this->errorData;
    }

}
