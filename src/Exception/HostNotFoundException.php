<?php

namespace ZanPHP\HttpClient\Exception;

use ZanPHP\Exception\SystemException;

class HostNotFoundException extends SystemException
{
    public function __construct($message = '', $code = 408, \Exception $previous = null, array $metaData = [])
    {
        parent::__construct($message, $code, $previous);
    }
}