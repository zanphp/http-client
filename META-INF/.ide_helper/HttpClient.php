<?php

namespace Zan\Framework\Network\Common;

use Zan\Framework\Foundation\Contract\Async;
use Zan\Framework\Utilities\DesignPattern\Context;

class HttpClient implements Async
{
    private $HttpClient;

    public function __construct($host, $port = 80, $ssl = false)
    {
        $this->HttpClient = new \ZanPHP\HttpClient\HttpClient($host, $port, $ssl);
    }

    public static function newInstance($host, $port = 80, $ssl = false)
    {
        \ZanPHP\HttpClient\HttpClient::newInstance($host, $port, $ssl);
    }

    public static function newInstanceUsingProxy($host, $port = 80, $ssl = false)
    {
        \ZanPHP\HttpClient\HttpClient::newInstanceUsingProxy($host, $port, $ssl);
    }

    public function get($uri = '', $params = [], $timeout = 3000)
    {
        $this->HttpClient->get($uri, $params, $timeout);
    }

    public function post($uri = '', $params = [], $timeout = 3000)
    {
        $this->HttpClient->post($uri, $params, $timeout);
    }

    public function postJson($uri = '', $params = [], $timeout = 3000)
    {
        $this->HttpClient->postJson($uri, $params, $timeout);
    }

    public function execute(callable $callback, $task)
    {
        $this->HttpClient->execute($callback, $task);
    }

    public function setMethod($method)
    {
        $this->HttpClient->setMethod($method);
    }

    public function setUri($uri)
    {
        $this->HttpClient->setUri($uri);
    }

    public function setTimeout($timeout)
    {
        $this->HttpClient->setTimeout($timeout);
    }

    public function setParams($params)
    {
        $this->HttpClient->setParams($params);
    }

    public function setHeader(array $header)
    {
        $this->HttpClient->setHeader($header);
    }

    public function setBody($body)
    {
        $this->HttpClient->setBody($body);
    }

    public function build()
    {
        $this->HttpClient->build();
    }

    public function setCallback(Callable $callback)
    {
        $this->HttpClient->setCallback($callback);
    }

    public function handle(Context $ctx = null)
    {
        $this->HttpClient->handle($ctx);
    }

    public function request($ip, $port)
    {
        $this->HttpClient->request($ip, $port);
    }

    public function onReceive($cli)
    {
        $this->HttpClient->onReceive($cli);
    }

    public function whenHostNotFound($host)
    {
        $this->HttpClient->whenHostNotFound($host);
    }

    public function checkTimeout()
    {
        $this->HttpClient->checkTimeout();
    }

    public function dnsLookupTimeout()
    {
        $this->HttpClient->dnsLookupTimeout();
    }
}