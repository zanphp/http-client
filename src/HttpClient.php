<?php

namespace ZanPHP\HttpClient;


use ZanPHP\Contracts\Config\Repository;
use ZanPHP\Contracts\Debugger\Tracer;
use ZanPHP\Contracts\Foundation\Application;
use ZanPHP\Contracts\Trace\Constant;
use ZanPHP\Contracts\Trace\Trace;
use ZanPHP\Coroutine\Context;
use ZanPHP\Coroutine\Contract\Async;
use ZanPHP\Coroutine\Task;
use ZanPHP\DnsClient\DnsClient;
use ZanPHP\DnsClient\DnsLookupTimeoutException;
use ZanPHP\Exception\System\InvalidArgumentException;
use ZanPHP\HttpClient\Exception\HostNotFoundException;
use ZanPHP\HttpClient\Exception\HttpClientClosedException;
use ZanPHP\HttpClient\Exception\HttpClientTimeoutException;
use ZanPHP\RpcContext\RpcContext;
use ZanPHP\Support\Arr;
use ZanPHP\Timer\Timer;


class HttpClient implements Async
{
    const GET = 'GET';
    const POST = 'POST';

    /** @var  \swoole_http_client */
    private $client;

    private $host;
    private $port;
    private $ssl;

    /**
     * @var int [millisecond]
     */
    private $timeout;

    private $uri;
    private $method;
    private $setting = [];

    private $params;

    //压测请求mock参数
    private $mockParams;

    private $header = [];
    private $body;

    private $callback;

    /** @var RpcContext */
    private $rpcContext;

    /** @var Trace */
    private $trace;
    private $traceHandle;

    /** @var Tracer  */
    private $debuggerTrace;
    private $debuggerTid;

    private $serviceChainValue = null;

    private $useHttpProxy = false;

    public function __construct($host='', $port = 80, $ssl = false)
    {
        $this->host = $host;
        $this->port = $port;
        $this->ssl = $ssl;
    }

    public static function newInstance($host='', $port = 80, $ssl = false)
    {
        return new static($host, $port, $ssl);
    }

    public static function newInstanceUsingProxy($host='', $port = 80, $ssl = false)
    {
        $instance = new static($host, $port, $ssl);
        $instance->useHttpProxy = true;

        return $instance;
    }


    public function get($uri = '', $params = [], $timeout = 3000)
    {
        $this->setMethod(self::GET);
        $this->setTimeout($timeout);
        $this->setUri($uri);
        $this->setParams($params);

        yield $this->build();
    }

    public function getByURL($url = '', $params = [], $timeout = 3000)
    {
        $this->setMethod(self::GET);
        $this->setTimeout($timeout);
        $this->parseUrl($url);
        $this->setParams($params);

        yield $this->build();
    }

    public function post($uri = '', $params = [], $timeout = 3000)
    {
        $this->setMethod(self::POST);
        $this->setTimeout($timeout);
        $this->setUri($uri);
        $this->setParams($params);

        yield $this->build();
    }

    public function postByURL($url = '', $params = [], $timeout = 3000)
    {
        $this->setMethod(self::POST);
        $this->setTimeout($timeout);
        $this->parseUrl($url);
        $this->setParams($params);

        yield $this->build();
    }

    public function postJson($uri = '', $params = [], $timeout = 3000)
    {
        $this->setMethod(self::POST);
        $this->setTimeout($timeout);
        $this->setUri($uri);
        $this->setParams(json_encode($params));

        $this->setHeader([
            'Content-Type' => 'application/json'
        ]);

        yield $this->build();
    }

    public function postJsonByURL($url = '', $params = [], $timeout = 3000)
    {
        $this->setMethod(self::POST);
        $this->setTimeout($timeout);
        $this->parseUrl($url);
        $this->setParams(json_encode($params));
        $this->setHeader([
            'Content-Type' => 'application/json'
        ]);

        yield $this->build();
    }

    public function execute(callable $callback, $task)
    {
        /** @var Task $task */
        $ctx = $task->getContext();
        $this->setCallback($this->getCallback($callback))->handle($ctx);
    }

    public function setMethod($method)
    {
        $this->method = $method;
        return $this;
    }

    public function setHost($host)
    {
        $this->host = $host;
        return $this;
    }

    public function setPort($port)
    {
        $this->port = $port;
        return $this;
    }

    public function setUri($uri)
    {
        if (empty($uri)) {
            $uri .= '/';
        }
        $this->uri = $uri;
        return $this;
    }

    public function setTimeout($timeout)
    {
        if (null !== $timeout) {
            if ($timeout < 0 || $timeout > 60000) {
                throw new InvalidArgumentException("Timeout must be between 0-60 seconds, $timeout is given");
            }
        }
        $this->timeout = $timeout;
        return $this;
    }

    public function setMockParams($params)
    {
        $this->mockParams = $params;
    }

    public function setParams($params)
    {
        $this->params = $params;
        return $this;
    }

    public function setHeader(array $header)
    {
        $this->header = array_merge($this->header, $header);
        return $this;
    }

    public function setBody($body)
    {
        $this->body = $body;
        return $this;
    }


    public function set($setting = [])
    {
        $this->setting = array_merge($this->setting, $setting);
    }


    public function build()
    {
        if ($this->useHttpProxy) {
            $value = (yield getContext("service-chain-value"));
            if (is_array($value) && isset($value["zan_test"]) && $value["zan_test"] === true) {

                /**
                 * 压测流量访问外网时丢弃params,使用mock设置的params, GET和DELETE的mock params存于doraemon-real-url头部字段,
                 * POST和PUT的mock params存于body之中
                */
                if ($this->method === 'POST' || $this->method === 'PUT') {
                    $this->setBody($this->mockParams);
                }
                return;
            }
        }

        if ($this->method === 'GET') {
            if (!empty($this->params)) {
                $this->uri = $this->uri . '?' . http_build_query($this->params);
            }
        } else if ($this->method === 'POST') {
            $body = $this->params;

            $this->setBody($body);
        }

        yield $this;
    }

    public function setCallback(Callable $callback)
    {
        $this->callback = $callback;
        return $this;
    }

    public function handle(Context $ctx = null)
    {
        if ($ctx) {
            $this->trace = $ctx->get("trace");
            $this->debuggerTrace = $ctx->get('debugger_trace');
            $this->rpcContext = $ctx->get("rpc-context");
            $this->serviceChainValue = $ctx->get("service-chain-value");
        }

        if ($this->useHttpProxy) {
            $config = make(Repository::class);
            $host = $config->get("zan_http_proxy.host");
            $port = $config->get("zan_http_proxy.port", 80);
            if (empty($host)) {
                throw new \InvalidArgumentException("Missing http proxy config, see: http://zanphpdoc.zanphp.io/libs/network/http.html");
            }
        } else {
            if(empty($this->host)){
                throw new \InvalidArgumentException("Host can't be empty");
            }
            $host = $this->host;
            $port = $this->port;
        }

        $dnsCallbackFn = function($host, $ip) use ($port) {
            if ($ip) {
                $this->request($ip, $port);
            } else {
                $this->whenHostNotFound($host);
            }
        };

        if ($this->timeout === null) {
            DnsClient::lookupWithoutTimeout($host, $dnsCallbackFn);
        } else {
            DnsClient::lookup($host, $dnsCallbackFn, [$this, "dnsLookupTimeout"], $this->timeout);
        }
    }

    public function request($ip, $port)
    {
        if ($this->useHttpProxy) {
            $this->client = new \swoole_http_client($ip, $port);
        } else {
            $this->client = new \swoole_http_client($ip, $port, $this->ssl);
        }
        
        if(!empty($this->setting)){
            $this->client->set($this->setting);
        }
        // $this->client->on("close", [$this, "onClose"]);

        $this->buildHeader();

        if ($this->trace) {
            $this->traceHandle = $this->trace->transactionBegin(Constant::HTTP_CALL, $this->host . $this->uri);
        }
        if ($this->debuggerTrace instanceof Tracer) {
            $scheme = $this->ssl ? "https://" : "http://";
            $name = "{$this->method}-{$scheme}{$this->host}:{$this->port}{$this->uri}";
            $this->debuggerTid = $this->debuggerTrace->beginTransaction(Constant::HTTP, $name, [
                'params' => $this->params,
                'body' => $this->body,
                'header' => $this->header,
                'use_http_proxy' => $this->useHttpProxy,
            ]);
        }

        if (null !== $this->timeout) {
            Timer::after($this->timeout, [$this, 'checkTimeout'], spl_object_hash($this));
        }

        if('GET' === $this->method){
            if ($this->trace) {
                $this->trace->logEvent(Constant::GET, Constant::SUCCESS);
            }
            $this->client->get($this->uri, [$this,'onReceive']);
        } elseif ('POST' === $this->method){
            if ($this->trace) {
                $this->trace->logEvent(Constant::POST, Constant::SUCCESS, $this->body);
            }
            $this->client->post($this->uri,$this->body, [$this, 'onReceive']);
        } else {
            if ($this->trace) {
                $this->trace->logEvent($this->method, Constant::SUCCESS, $this->body);
            }

            $this->client->setMethod($this->method);
            if ($this->body) {
                $this->client->setData($this->body);
            }
            $this->client->execute($this->uri, [$this,'onReceive']);
        }
    }

    private function addMockServerHeader() {
        if ($this->useHttpProxy) {
            $value = $this->serviceChainValue;
            if (is_array($value) && isset($value["zan_test"]) && $value["zan_test"] === true) {
                $this->header["Content-type"] = "application/x-www-form-urlencoded";
                $scheme = $this->ssl ? "https://" : "http://";
                $url = "$scheme{$this->host}:{$this->port}{$this->uri}";
                if ($this->method === 'GET' || $this->method === 'DELETE') {
                    if (!empty($this->mockParams)) {
                        $url = $url . '?' . http_build_query($this->mockParams);
                    }
                }
                $this->header["doraemon-real-url"] = $url;

                /** @var Application $application */
                $application = make(Application::class);
                $this->header['app'] = $application->getName();
            }
        }
    }

    private function buildHeader()
    {
        if ($this->port !== 80) {
            $this->header['Host'] = $this->host . ':' . $this->port;
        } else {
            $this->header['Host'] = $this->host;
        }

        if ($this->ssl) {
            $this->header['Scheme'] = 'https';
        }

        if ($this->debuggerTrace instanceof Tracer) {
            $this->header[Tracer::KEY] = $this->debuggerTrace->getKey();
        }

        if ($this->rpcContext instanceof RpcContext) {
            $pairs = $this->rpcContext->get();
            foreach ($pairs as $key => $value) {
                if (is_scalar($value)) {
                    $this->header[$key] = strval($value);
                } else {
                    $this->header[$key] = json_encode($value) ?: "";
                }
            }
        }

        //压测流量增加首部
        $this->addMockServerHeader();

        $this->client->setHeaders($this->header);
    }

    public function whenHostNotFound($host)
    {
        $ex = new HostNotFoundException("", 408, null, [ "host" => $host ]);
        call_user_func($this->callback, null, $ex);
    }

    private function getCallback(callable $callback)
    {
        return function($response, $exception = null) use ($callback) {
            call_user_func($callback, $response, $exception);
        };
    }

    public function onReceive($cli)
    {
        Timer::clearAfterJob(spl_object_hash($this));
        if ($this->callback === null) {
            return;
        }

        try {
            $this->commitTrace(Constant::SUCCESS, "info", [
                "code" => $cli->statusCode,
                "header" => $cli->headers,
                "body" => mb_convert_encoding($cli->body, 'UTF-8', 'UTF-8'),
            ]);
            $response = new Response($cli->statusCode, $cli->headers, $cli->body);
            call_user_func($this->callback, $response);
        } finally {
            $this->callback = null;
            $this->client->close();
        }
    }

    public function onClose()
    {
        Timer::clearAfterJob(spl_object_hash($this));
        if ($this->callback === null) {
            return;
        }

        list($message, $metaData) = $this->getMetaData("closed");
        $exception = new HttpClientClosedException($message, 0, null, $metaData);
        $this->commitTrace($exception, "error", $exception);
        call_user_func($this->callback, null, $exception);
        $this->callback = null;
    }

    public function checkTimeout()
    {
        if ($this->callback === null) {
            return;
        }

        try {
            list($message, $metaData) = $this->getMetaData("timeout");
            $exception = new HttpClientTimeoutException($message, 408, null, $metaData);
            $this->commitTrace($exception, "warn", $exception);
            call_user_func($this->callback, null, $exception);
        } finally {
            $this->callback = null;
            $this->client->close();
        }
    }

    public function dnsLookupTimeout()
    {
        list($message, $metaData) = $this->getMetaData("dns lookup timeout");
        $exception = new DnsLookupTimeoutException($message, 408, null, $metaData);
        call_user_func($this->callback, null, $exception);
    }

    private function commitTrace($traceStatus, $dbgTraceLevel, $dbgTraceRes)
    {
        if ($this->trace) {
            $this->trace->commit($this->traceHandle, $traceStatus);
        }
        if ($this->debuggerTrace instanceof Tracer) {
            $this->debuggerTrace->commit($this->debuggerTid, $dbgTraceLevel, $dbgTraceRes);
        }

    }

    private function getMetaData($type)
    {
        $message = sprintf(
            '[http client ' . $type . '] host:%s port:%s uri:%s method:%s ',
            $this->host,
            $this->port,
            $this->uri,
            $this->method
        );
        $metaData = [
            'host' => $this->host,
            'port' => $this->port,
            'ssl' => $this->ssl,
            'uri' => $this->uri,
            'method' => $this->method,
            'params' => $this->params,
            'body' => $this->body,
            'header' => $this->header,
            'timeout' => $this->timeout,
            'use_http_proxy' => $this->useHttpProxy,
        ];

        return [$message, $metaData];
    }


    /**
     * @return array
     */
    public function getHeader()
    {
        return $this->header;
    }
  
    public function parseUrl($url)
    {
        $urlInfo = parse_url($url);
        if (empty($urlInfo['host'])) {
            throw new InvalidArgumentException("URL is not complete, {$url} is given");
        }

        $this->setHost($urlInfo['host']);

        if (empty($urlInfo['scheme'])) {
            $this->ssl = false;
        } else {
            $this->ssl = ($urlInfo['scheme'] == 'http') ? false : true;
        }
        if (empty($urlInfo['port'])) {
            $port = $this->ssl ? 443 : 80;
        } else {
            $port = $urlInfo['port'];
        }
        $this->setPort($port);

        if (empty($urlInfo['path'])) {
            $uri = "/";
        } else {
            $uri = $urlInfo['path'];
        }
        if (!empty($urlInfo['query'])) {
            $uri .= "?{$urlInfo['query']}";
        }
        $this->setUri($uri);
        return $this;
    }
}