<?php

namespace Zan\Framework\Network\Common;

class Response
{
    private $Response;

    public function __construct($statusCode, $headers = null, $body = null)
    {
        $this->Response = new \ZanPHP\HttpClient\Response($statusCode, $headers, $body);
    }

    public function getBody()
    {
        $this->Response->getBody();
    }

    public function getHeaders()
    {
        $this->Response->getHeaders();
    }

    public function getHeader($header)
    {
        $this->Response->getHeader($header);
    }

    public function getStatusCode()
    {
        $this->Response->getStatusCode();
    }
}