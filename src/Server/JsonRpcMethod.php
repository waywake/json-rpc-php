<?php

namespace JsonRpc\Server;

use Illuminate\Http\JsonResponse;

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

    /**
     *
     * @param $code
     * @param $msg
     * @return static
     */
    public function error($code, $msg)
    {
        return JsonResponse::create([
            'jsonrpc' => '2.0',
            'error' => [
                'code' => $code,
                'message' => is_array($msg) ? json_encode($msg) : $msg,
            ],
            'id' => $this->id
        ]);
    }

}