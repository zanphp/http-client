<?php

namespace ZanPHP\HttpClient\Exception;


use ZanPHP\Exception\SystemException;

class HttpClientClosedException extends SystemException
{
    public function __construct($message = '', $code = 409, \Exception $previous = null, array $metaData = [])
    {
        parent::__construct($message, $code, $previous);
    }
}