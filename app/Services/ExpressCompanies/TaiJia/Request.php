<?php


namespace App\Services\ExpressCompanies\TaiJia;

use App\Services\ExpressCompanies\XML;

class Request
{

    protected string $clientId;

    protected string $authToken;

    protected string $billId;

    protected string $content;

    protected string $url;

    protected string $paper;

    /**
     * Request constructor.
     */
    public function __construct()
    {
    }

    public static function build()
    {
        return new static();
    }

    /**
     * @param string $clientId
     * @return self
     */
    public function setClientId(string $clientId): self
    {
        $this->clientId = $clientId;

        return $this;
    }

    /**
     * @param string $authToken
     * @return self
     */
    public function setAuthToken(string $authToken): self
    {
        $this->authToken = $authToken;

        return $this;
    }

    /**
     * @param string $content
     * @return self
     */
    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    /**
     * @param string $paper
     * @return self
     */
    public function setPaper(string $paper): self
    {
        $this->paper = $paper;
        return $this;
    }

    /**
     * @param string $url
     * @return self
     */
    public function setUrl(string $url): self
    {
        $this->url = $url;
        return $this;
    }

    /**
     * @param string $billId
     * @return self
     */
    public function setBillId(string $billId): self
    {
        $this->billId = $billId;

        return $this;
    }

    /**
     * @return string
     */
    public function channelsToXml(): string
    {
        $data = [
            'CreateAndPreAlertOrderService' => [
                'authtoken' => $this->authToken,
                'clientid'  => $this->clientId,
            ],
        ];

        return XML::fromArray($data);
    }

    /**
     * @return string
     */
    public function labelToXml()
    {
        $data = [
            'GeOrdertPrintServiceRequest' => [
                'authtoken'   => $this->authToken,
                'clientid'    => $this->clientId,
                'corpbillid'  => $this->billId,
                'url'         => $this->url,
                'ifprinttime' => 1,
                'content'     => $this->content,
                'paper'       => $this->paper,
            ],
        ];

        return XML::fromArray($data);
    }

}
