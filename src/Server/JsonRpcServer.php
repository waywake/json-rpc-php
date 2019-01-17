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
    const Rpc_Success = 0;


    const ErrorMsg = [
        self::Rpc_Error_NOT_FOUND => 'Method not found',
        self::Rpc_Error_Parse_Error => 'Json parse error',
        self::Rpc_Error_Invalid_Request => 'Invalid request',
        self::Rpc_Error_Invalid_Params => 'Invalid params',
        self::Rpc_Success => 'Success'
    ];

    /**
     * @var Request
     */
    public $request;

    public function __construct($config)
    {
        $this->config = $config;
        $this->request = function_exists('app') ? app('request') : Request::capture();

        $this->map = require_once $config['map'];
    }

    public function handler()
    {
        if ($this->request->getContentType() != 'json') {
            return $this->error(self::Rpc_Error_Invalid_Request);
        }

        try {

            if ($this->request->method() == Request::METHOD_GET) {
                $method = $this->request->input('method');
                $id = $this->request->input('id');
                $params = \GuzzleHttp\json_decode($this->request->input('params'),true);
            } else {
                list($method, $params, $id) = $this->parseJson($this->request->getContent());
            }

            list($class, $function) = $this->parseMethodWithMap($method);

            if (!class_exists($class) || !method_exists($class, $function)) {
                return $this->error(self::Rpc_Error_NOT_FOUND);
            }

            if (!$this->isEnoughParameter($class, $function, $params)) {
                return $this->error(self::Rpc_Error_Invalid_Params);
            }

            app('log')->info('rpc ser', [$method, $params, $id, $class, $this->request->header('client_app')]);
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

    protected function parseMethodWithMap($method)
    {
        return isset($this->map[$method]) ? $this->map[$method] : ['', ''];
    }

    /**
     * thisis
     * @param string $method 参数名称
     * @return array 返回结果
     */
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