<?php

namespace Zan\Framework\Network\Common\Exception;

use ZanPHP\Exception\SystemException;

class HttpClientTimeoutException extends SystemException
{
    public function __construct($message = '', $code = 408, \Exception $previous = null, array $metaData = [])
    {
        parent::__construct($message, $code, $previous);
    }
}