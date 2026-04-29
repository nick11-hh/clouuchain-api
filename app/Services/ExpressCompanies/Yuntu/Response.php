<?php declare(strict_types=1);
/**
 * @Author: h9471
 * @Created: 2022/10/26 17:29
 */

namespace App\Services\ExpressCompanies\Yuntu;

use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;

class Response
{
    protected const STATUS_OK = 'success';

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
        return $this->response['statusCode'] === self::STATUS_OK;
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
        $this->data = json_decode($this->response->getBody()->getContents(), true);

        Log::channel('logistics')->info('云途物流 Response', [$this->data]);

        return $this;
    }
}
