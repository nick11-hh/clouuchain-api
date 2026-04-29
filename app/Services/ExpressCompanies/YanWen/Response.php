<?php

namespace App\Services\ExpressCompanies\YanWen;

use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;

class Response
{
    protected const STATUS_OK = true;

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
            // ->info('燕文 Response'.$this->response->getBody()->getContents());
        // dd($this->response->getBody()->getContents());
        // $body = $this->response->getBody();
        // $stringBody = (string) $body;
        $arrayBody = json_decode($this->response->getBody()->getContents(), TRUE);

        $this->data = $arrayBody ?? [];

        return $this;
    }
}
