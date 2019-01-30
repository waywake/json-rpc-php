<?php

namespace JsonRpc\Exception;

class RpcServerException extends \Exception
{
    protected $response;

    public function setResponse($resp)
    {
        $this->response = $resp;
    }

    public function getResponse()
    {
        return $this->response;
    }

}