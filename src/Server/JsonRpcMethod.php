<?php

namespace JsonRpc\Server;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use JsonRpc\Exception\RpcServerException;
use JsonRpc\JsonRpc;

class JsonRpcMethod extends JsonRpc
{
    protected mixed $id;
    protected Request $request;

    final public function __construct($id, Request $request)
    {
        $this->id = $id;
        $this->request = $request;
    }

    public function response(array $result): array
    {
        return [
            'jsonrpc' => '2.0',
            'result' => $result,
            'id' => $this->id
        ];
    }

    public function error(int $code, string|array $msg): array
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
                    'message' => self::ErrorMsg[$code] ?? 'Unknown error',
                    'data' => $msg
                ],
                'id' => $this->id
            ];
    }

}
