<?php

namespace App\Services\ExpressCompanies\DiSiFang;

use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;

class Response
{
    protected const STATUS_OK = '1';

    public ResponseInterface $response;

    public array $data;

    public function __construct(ResponseInterface $response)
    {
        $this->response = $response;
    }

    /**
     * @param ResponseInterface $response
     * @return static
     */
    public static function from(ResponseInterface $response)
    {
        return (new static($response))->toArray();
    }

    /**
     * @return bool
     */
    public function isSuccessful(): bool
    {
        return $this->response['result'] === self::STATUS_OK;
    }

    /**
     * @return bool
     */
    public function isFailed(): bool
    {
        return !$this->isSuccessful();
    }

    /**
     * @return array
     */
    public function result(): array
    {
        return $this->data;
    }

    /**
     * @return string
     */
    public function message(): string
    {
        return $this->response['message'];
    }

    /**
     * @return $this
     */
    public function toArray(): self
    {
        // Log::channel('logistics')
            // ->info('递四方 Response'.$this->response->getBody()->getContents());

        #这个递四方返回的数据直接转换不了数组。
        $body = $this->response->getBody();
        $stringBody = (string) $body;
        $arrayBody = json_decode($stringBody, TRUE);

        $this->data = $arrayBody ?? [];

        return $this;
    }
}
