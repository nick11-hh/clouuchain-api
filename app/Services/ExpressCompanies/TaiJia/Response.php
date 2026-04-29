<?php


namespace App\Services\ExpressCompanies\TaiJia;

use App\Services\ExpressCompanies\XML;

class Response
{
    public $response;

    public array $data;

    public function __construct($response)
    {
        $this->response = $response;
    }

    /**
     * @param $response
     * @return static
     */
    public static function from($response)
    {
        return (new static($response))->toArray();
    }

    /**
     * @param string $action
     * @return bool
     */
    public function isSuccessful(string $action): bool
    {
        return match ($action) {
            TaiJiaService::ACTION_GET_SHIPPING_METHOD => !empty($this->data['Channel']['channelid']),
            TaiJiaService::ACTION_CREATE_ORDER => !empty($this->data['CreateOrderService']['CreateOrderServiceResponseArray']['CreateOrderServiceResponse']['OrderItem']),
            TaiJiaService::ACTION_GET_LABEL => !empty($this->data['printlabel']['printurl']),

            default => true,
        };
    }

    /**
     * @param string $action
     * @return bool
     */
    public function isFailed(string $action): bool
    {
        return !$this->isSuccessful($action);
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
        $ack   = $this->data['CreateOrderService']['CreateOrderServiceResponseArray']['CreateOrderServiceResponse']['Ack'] ?? '';
        $error = $this->data['CreateOrderService']['CreateOrderServiceResponseArray']['CreateOrderServiceResponse']['Error'] ?? '';
        $labelError = $this->data['printlabel']['Errror'] ?? '';

        $message = '';

        if (!empty($ack)) $message = $ack;

        if (!empty($error)) $message = $error;

        if (!empty($labelError)) $message = $labelError;

        if (!empty($ack) && !empty($error)) $message = "$ack, $error";

        return $message;
    }

    /**
     * @return $this
     */
    public function toArray(): self
    {
        $this->data = XML::toArray($this->response->getBody()->getContents());

        return $this;
    }
}
