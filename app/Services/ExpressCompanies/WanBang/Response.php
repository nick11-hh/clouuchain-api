<?php
namespace App\Services\ExpressCompanies\WanBang;

class Response
{
    public array $response = [];

    public function __construct(array $response)
    {
        $this->response = $response;
    }

    /**
     * @return bool
     */
    public function isSuccessful(): bool
    {
        return !empty($this->response['Succeeded']);
    }

    /**
     * @return bool
     */
    public function isFailed(): bool
    {
        return ! $this->isSuccessful();
    }

    /**
     * @return array
     */
    public function result(): array
    {
        return $this->response;
    }

    /**
     * @return string
     */
    public function message(): string
    {
        $error = $this->response['Error'] ?? '';
        if (is_array($error)) {
            return $error['Message'] ?? json_encode($error, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        }

        return $error;
    }

    /**
     * @return array
     */
    public function data(): array
    {
        return $this->response['Data'] ?? [];
    }
}
