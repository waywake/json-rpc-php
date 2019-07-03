<?php

namespace JsonRpc\Server;


use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use JsonRpc\JsonRpc;
use Psr\Log\LoggerInterface;

class JsonRpcServer extends JsonRpc
{
    /**
     * @var Request
     */
    public $request;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var  array 配置
     */
    protected $config;

    /**
     * @var array rpc.server.map rpc方法
     */
    protected $map;

    public function __construct($config)
    {
        $this->config = $config;
        $this->request = function_exists('app') ? app('request') : Request::capture();
        $this->map = $config['map'];
    }

    public function setLogger($logger){
        $this->logger = $logger;
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
                $params = \GuzzleHttp\json_decode($this->request->input('params'), true);
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
            $this->request->attributes->add(['tunnel_method' => $method, 'tunnel_params' => $params]);
            $this->logger && $this->logger->info('server', [$id, $class, $method, $params]);
            $ret = call_user_func_array([(new $class($id, $this->request)), $function], $params);
            $this->logger && $this->logger->info('server_result', [$id, $ret]);

            return JsonResponse::create($ret);

        } catch (\InvalidArgumentException $e) {
            return $this->error(self::Rpc_Error_Parse_Error);
        }
    }

    /**
     * 处理json rpc post body
     * @param $data
     * @return array
     */
    protected function parseJson($data)
    {
        $data = \GuzzleHttp\json_decode($data, true);
        $method = $data['method'];
        $params = $data['params'];
        $id = $data['id'];
        return [$method, $params, $id];
    }

    /**
     * 根据method解析出对应的class
     * @param $method
     * @return array|mixed
     */
    protected function parseMethodWithMap($method)
    {
        return isset($this->map[$method]) ? $this->map[$method] : ['', ''];
    }

    /**
     * 检查调用方式是否足够
     * @param $class
     * @param $method
     * @param $parameters
     * @return bool
     */
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