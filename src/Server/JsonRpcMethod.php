<?php

namespace JsonRpc\Server;

class JsonRpcMethod
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
        return JsonResponse::create([
            'jsonrpc' => '2.0',
            'result' => $result,
            'id' => $this->id
        ]);
    }

    public function error($code, $msg)
    {
        return JsonResponse::create([
            'jsonrpc' => '2.0',
            'error' => [
                'code' => $code,
                'message' => $msg,
            ],
            'id' => $this->id
        ]);
    }

}