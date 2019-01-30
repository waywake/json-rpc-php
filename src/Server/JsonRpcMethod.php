<?php

namespace JsonRpc\Server;

use Illuminate\Http\JsonResponse;
use JsonRpc\Exception\RpcServerException;
use JsonRpc\JsonRpc;

class JsonRpcMethod extends JsonRpc
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

        return [
            'jsonrpc' => '2.0',
            'error' => [
                'code' => $code,
                'message' => is_array($msg) ? json_encode($msg) : $msg,
            ],
            'id' => $this->id
        ];
    }

}