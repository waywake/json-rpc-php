<?php

namespace JsonRpc\Server;


use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JsonRpcServer
{
    const Rpc_Error_Parse_Error = -32700;
    const Rpc_Error_Invalid_Request = -32600;
    const Rpc_Error_NOT_FOUND = -32601;
    const Rpc_Error_Invalid_Params = -32602;
    const Rpc_Error_Internal_Error = -32603;


    const ErrorMsg = [
        self::Rpc_Error_NOT_FOUND => 'Method not found',
        self::Rpc_Error_Parse_Error => 'Json parse error',
        self::Rpc_Error_Invalid_Request => 'Invalid request',
        self::Rpc_Error_Invalid_Params => 'Invalid params',
    ];

    /**
     * @var Request
     */
    public $request;

    public function __construct($config)
    {
        $this->request = function_exists('app') ? app('request') : Request::capture();
    }

    public function handler()
    {
        if ($this->request->getContentType() != 'json') {
            return $this->error(self::Rpc_Error_Invalid_Request);
        }

        try {
            list($method, $params, $id) = $this->parseJson($this->request->getContent());
            list($class, $function) = $this->parseMethod($method);

            if (!class_exists($class) || !method_exists($class, $function)) {
                return $this->error(self::Rpc_Error_NOT_FOUND);
            }

            if (!$this->isEnoughParameter($class, $function, $params)) {
                return $this->error(self::Rpc_Error_Invalid_Params);
            }


            $ret = call_user_func_array([(new $class($id, $this->request)), $function], $params);
            return $ret;

        } catch (\InvalidArgumentException $e) {
            return $this->error(self::Rpc_Error_Parse_Error);
        }
    }

    protected function parseJson($data)
    {
        $data = \GuzzleHttp\json_decode($data, true);
        $method = $data['method'];
        $params = $data['params'];
        $id = $data['id'];
        return [$method, $params, $id];
    }

    protected function parseMethod($method)
    {
        $method = explode('.', $method);

        if (count($method) < 2) {
            return ['', ''];
        }

        $function = array_pop($method);
        $class = 'Rpc' . ucwords(array_pop($method));

        foreach ($method as $one) {
            $class = ucwords($one) . '\\' . $class;
        }

        $class = "App\Rpc\\$class";
        return [$class, $function];
    }


    protected function isEnoughParameter($class, $method, $parameters)
    {
        $r = new \ReflectionMethod($class, $method);
        $params = $r->getParameters();
        $n = 0;
        foreach ($params as $param) {
            //$param is an instance of ReflectionParameter
            if (!$param->isOptional()) {
                $n++;
            }
        }
        return count($parameters) >= $n;
    }

    protected function error($code, $msg = null, $id = null)
    {
        if ($msg === null) {
            $msg = isset(self::ErrorMsg[$code]) ? self::ErrorMsg[$code] : 'undefined';
        }

        return JsonResponse::create([
            'jsonrpc' => '2.0',
            'error' => [
                'code' => $code,
                'message' => $msg,
            ],
            'id' => $id
        ]);
    }


}