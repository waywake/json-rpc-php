<?php

namespace JsonRpc\Server;

use Illuminate\Http\JsonResponse;
use JsonRpc\Exception\RpcServerException;

class JsonRpcMethod extends JsonRpcBase
{
    protected $id;
    protected $request;

    final public function __construct($id, $request)
    {
        $this->id = $id;
        $this->request = $request;
    }

    public function response($result)
    {
        return [
            'jsonrpc' => '2.0',
            'result' => $result,
            'id' => $this->id
        ];
    }

    public function error($code, $msg)
    {

        return is_string($msg)
            ? [
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => $code,
                    'message' => $msg,
                ],
                'id' => $this->id
            ]
            : [
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => $code,
                    'message' => self::ErrorMsg[$code],
                    'data' => $msg
                ],
                'id' => $this->id
            ];
    }

}